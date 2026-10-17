<?php
/**
 * Import des fiches : dossier de travail, verrous et execution d'un lot.
 *
 * Un "travail" = un import en cours depuis l'admin : le CSV envoye, les
 * images extraites du zip, l'etat d'avancement. Il vit dans
 * <racine>/<jeton>/, la racine etant <uploads>/ditl-import par defaut, ou
 * le chemin absolu de la constante DITL_IMPORT_DOSSIER (a definir dans
 * wp-config.php, de preference HORS de la racine web) :
 * - jeton de 32 caracteres [a-z0-9] (aleatoire), valide par expression
 *   reguliere AVANT toute construction de chemin ;
 * - .htaccess (acces HTTP refuse) et index.php vides a la racine et dans
 *   chaque travail (les fichiers du client ne sont jamais servis par
 *   Apache). SERVEUR NGINX (le .htaccess y est ignore) : poser la regle
 *     location ^~ /wp-content/uploads/ditl-import/ { deny all; }
 *   ou definir DITL_IMPORT_DOSSIER hors de la racine web ;
 * - travail.json : utilisateur proprietaire, type, nom du fichier, date,
 *   options, position, compteurs, statut ;
 * - rapport.json (analyse) et resultats.json (lignes executees) ;
 * - nettoyage des travaux de plus de 24 h (ouverture de l'ecran et une
 *   fois par jour en administration) ; une nouvelle analyse supprime les
 *   analyses non lancees du meme utilisateur ;
 * - verrou de travail (un seul lot a la fois pour un travail) et verrou
 *   GLOBAL d'ecriture <racine>/verrou-ecriture (un seul import a la fois,
 *   ecran d'administration et WP-CLI confondus) ;
 * - execution d'un lot (ditl_import_lot_travail) : position tenue cote
 *   serveur, fichier revalide a chaque lot, budget de temps.
 * Toute suppression est confinee sous la racine des travaux (realpath).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Racine des travaux d'import.
 *
 * @return string Chemin absolu (sans barre finale).
 */
function ditl_import_racine_travaux() {
	if ( defined( 'DITL_IMPORT_DOSSIER' ) && is_string( DITL_IMPORT_DOSSIER ) && '' !== DITL_IMPORT_DOSSIER && path_is_absolute( DITL_IMPORT_DOSSIER ) ) {
		return untrailingslashit( wp_normalize_path( DITL_IMPORT_DOSSIER ) );
	}

	$uploads = wp_upload_dir( null, false );

	return untrailingslashit( $uploads['basedir'] ) . '/ditl-import';
}

/**
 * Cree et protege la racine des travaux si besoin.
 *
 * @return bool True si la racine existe (protegee).
 */
function ditl_import_preparer_racine() {
	$racine = ditl_import_racine_travaux();

	if ( ! wp_mkdir_p( $racine ) ) {
		return false;
	}

	ditl_import_proteger_dossier( $racine );

	return true;
}

/**
 * Pose le verrou GLOBAL d'ecriture (un seul import a la fois). Reentrant
 * dans une meme requete (un lot appelle l'execution, qui le reprend).
 *
 * @return bool True si le verrou est tenu, false si un autre import le tient
 *              (ou si la racine des travaux est inaccessible).
 */
function ditl_import_verrouiller_ecriture() {
	if ( ! empty( $GLOBALS['ditl_import_verrou_global']['poignee'] ) ) {
		++$GLOBALS['ditl_import_verrou_global']['niveau'];
		return true;
	}

	if ( ! ditl_import_preparer_racine() ) {
		return false;
	}

	$poignee = @fopen( ditl_import_racine_travaux() . '/verrou-ecriture', 'c' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- verrou local, echec gere.

	if ( false === $poignee ) {
		return false;
	}

	if ( ! flock( $poignee, LOCK_EX | LOCK_NB ) ) {
		fclose( $poignee ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- verrou local.
		return false;
	}

	$GLOBALS['ditl_import_verrou_global'] = array(
		'poignee' => $poignee,
		'niveau'  => 1,
	);

	return true;
}

/**
 * Libere le verrou global d'ecriture (au dernier niveau).
 */
function ditl_import_liberer_ecriture() {
	if ( empty( $GLOBALS['ditl_import_verrou_global']['poignee'] ) ) {
		return;
	}

	--$GLOBALS['ditl_import_verrou_global']['niveau'];

	if ( $GLOBALS['ditl_import_verrou_global']['niveau'] > 0 ) {
		return;
	}

	flock( $GLOBALS['ditl_import_verrou_global']['poignee'], LOCK_UN );
	fclose( $GLOBALS['ditl_import_verrou_global']['poignee'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- verrou local.
	$GLOBALS['ditl_import_verrou_global'] = array();
}

/**
 * Valide un jeton de travail.
 *
 * @param mixed $jeton Jeton candidat.
 * @return bool True si le jeton est bien forme.
 */
function ditl_import_jeton_valide( $jeton ) {
	return is_string( $jeton ) && 1 === preg_match( '/^[a-z0-9]{32}$/', $jeton );
}

/**
 * Dossier d'un travail.
 *
 * @param string $jeton Jeton.
 * @return string Chemin absolu, chaine vide si jeton invalide.
 */
function ditl_import_dossier_travail( $jeton ) {
	return ditl_import_jeton_valide( $jeton ) ? ditl_import_racine_travaux() . '/' . $jeton : '';
}

/**
 * Pose les fichiers de protection d'un dossier (.htaccess, index.php).
 *
 * @param string $dossier Dossier existant.
 */
function ditl_import_proteger_dossier( $dossier ) {
	$htaccess = $dossier . '/.htaccess';
	$index    = $dossier . '/index.php';

	if ( ! file_exists( $htaccess ) ) {
		file_put_contents( $htaccess, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fichier de protection local.
	}

	if ( ! file_exists( $index ) ) {
		file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fichier de protection local.
	}
}

/**
 * Cree un travail (dossier protege + travail.json).
 *
 * @param array $donnees Donnees initiales (type, fichier, options...).
 * @return string|WP_Error Jeton du travail, ou erreur.
 */
function ditl_import_creer_travail( $donnees ) {
	if ( ! ditl_import_preparer_racine() ) {
		return new WP_Error( 'ditl_import_dossier', 'Dossier de travail impossible à créer (droits du dossier des médias).' );
	}

	$jeton   = bin2hex( random_bytes( 16 ) );
	$dossier = ditl_import_dossier_travail( $jeton );

	if ( '' === $dossier || ! wp_mkdir_p( $dossier . '/images' ) ) {
		return new WP_Error( 'ditl_import_dossier', 'Dossier de travail impossible à créer.' );
	}

	ditl_import_proteger_dossier( $dossier );
	ditl_import_proteger_dossier( $dossier . '/images' );

	$travail = array_merge(
		array(
			'jeton'       => $jeton,
			'utilisateur' => get_current_user_id(),
			'type'        => '',
			'fichier'     => '',
			'date'        => time(),
			'options'     => array( 'ecraser' => false ),
			'position'    => 0,
			'total'       => 0,
			'compteurs'   => array(),
			'statut'      => 'cree',
			'sha1'        => '',
		),
		(array) $donnees
	);

	if ( ! ditl_import_ecrire_travail( $jeton, $travail ) ) {
		ditl_import_supprimer_travail( $jeton );
		return new WP_Error( 'ditl_import_dossier', 'Écriture du dossier de travail impossible.' );
	}

	return $jeton;
}

/**
 * Lit un fichier JSON d'un travail.
 *
 * @param string $jeton  Jeton.
 * @param string $fichier Nom du fichier (travail, rapport, resultats).
 * @return array|null Donnees, ou null si absent / illisible.
 */
function ditl_import_lire_json( $jeton, $fichier ) {
	$dossier = ditl_import_dossier_travail( $jeton );

	if ( '' === $dossier || ! in_array( $fichier, array( 'travail', 'rapport', 'resultats' ), true ) ) {
		return null;
	}

	$chemin = $dossier . '/' . $fichier . '.json';

	if ( ! is_file( $chemin ) ) {
		return null;
	}

	$donnees = json_decode( (string) file_get_contents( $chemin ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichier local.

	return is_array( $donnees ) ? $donnees : null;
}

/**
 * Ecrit un fichier JSON d'un travail (ecriture atomique par renommage).
 *
 * @param string $jeton   Jeton.
 * @param string $fichier Nom du fichier (travail, rapport, resultats).
 * @param array  $donnees Donnees.
 * @return bool True si ecrit.
 */
function ditl_import_ecrire_json( $jeton, $fichier, $donnees ) {
	$dossier = ditl_import_dossier_travail( $jeton );

	if ( '' === $dossier || ! is_dir( $dossier ) || ! in_array( $fichier, array( 'travail', 'rapport', 'resultats' ), true ) ) {
		return false;
	}

	$chemin     = $dossier . '/' . $fichier . '.json';
	$temporaire = $chemin . '.tmp';
	$json       = wp_json_encode( $donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	if ( ! is_string( $json ) || false === file_put_contents( $temporaire, $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fichier local.
		return false;
	}

	return rename( $temporaire, $chemin ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- ecriture atomique.
}

/**
 * Lit travail.json.
 *
 * @param string $jeton Jeton.
 * @return array|null Travail, ou null.
 */
function ditl_import_lire_travail( $jeton ) {
	$travail = ditl_import_lire_json( $jeton, 'travail' );

	if ( null === $travail ) {
		return null;
	}

	// Cles toujours presentes (fichier relu depuis le disque).
	$travail = array_merge(
		array(
			'utilisateur'  => 0,
			'type'         => '',
			'fichier'      => '',
			'date'         => 0,
			'options'      => array(),
			'position'     => 0,
			'total'        => 0,
			'compteurs'    => array(),
			'statut'       => '',
			'sha1'         => '',
			'fatal'        => false,
			'zip'          => array(),
			'publications' => false,
		),
		$travail
	);

	$travail['options']   = is_array( $travail['options'] ) ? $travail['options'] : array();
	$travail['compteurs'] = is_array( $travail['compteurs'] ) ? $travail['compteurs'] : array();
	$travail['zip']       = is_array( $travail['zip'] ) ? $travail['zip'] : array();
	$travail['statut']    = is_string( $travail['statut'] ) ? $travail['statut'] : '';

	return $travail;
}

/**
 * Ecrit travail.json.
 *
 * @param string $jeton   Jeton.
 * @param array  $travail Travail.
 * @return bool True si ecrit.
 */
function ditl_import_ecrire_travail( $jeton, $travail ) {
	return ditl_import_ecrire_json( $jeton, 'travail', $travail );
}

/**
 * Supprime recursivement un dossier situe sous la racine des travaux.
 *
 * @param string $dossier Dossier a supprimer.
 * @return bool True si le dossier n'existe plus.
 */
function ditl_import_supprimer_dossier( $dossier ) {
	$racine = realpath( ditl_import_racine_travaux() );
	$reel   = realpath( $dossier );

	if ( false === $reel ) {
		return true;
	}

	// Garde-fou : jamais hors de la racine des travaux, jamais la racine.
	if ( false === $racine || 0 !== strpos( $reel, $racine . DIRECTORY_SEPARATOR ) ) {
		return false;
	}

	$elements = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $reel, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $elements as $element ) {
		if ( $element->isDir() && ! $element->isLink() ) {
			rmdir( $element->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- dossier de travail.
		} else {
			unlink( $element->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- dossier de travail.
		}
	}

	return rmdir( $reel ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- dossier de travail.
}

/**
 * Supprime un travail.
 *
 * @param string $jeton Jeton.
 * @return bool True si le travail n'existe plus.
 */
function ditl_import_supprimer_travail( $jeton ) {
	$dossier = ditl_import_dossier_travail( $jeton );

	return '' === $dossier ? false : ditl_import_supprimer_dossier( $dossier );
}

/**
 * Supprime les travaux plus vieux que l'age donne (abandonnes).
 *
 * @param int $age Age maximum en secondes (24 h par defaut).
 * @return int Nombre de travaux supprimes.
 */
function ditl_import_nettoyer_travaux( $age = DAY_IN_SECONDS ) {
	$racine = ditl_import_racine_travaux();

	if ( ! is_dir( $racine ) ) {
		return 0;
	}

	$supprimes = 0;
	$entrees   = scandir( $racine );

	foreach ( is_array( $entrees ) ? $entrees : array() as $entree ) {
		if ( ! ditl_import_jeton_valide( $entree ) || ! is_dir( $racine . '/' . $entree ) ) {
			continue;
		}

		$travail = ditl_import_lire_travail( $entree );
		$date    = is_array( $travail ) && isset( $travail['date'] ) ? (int) $travail['date'] : (int) filemtime( $racine . '/' . $entree );

		if ( time() - $date > $age && ditl_import_supprimer_travail( $entree ) ) {
			++$supprimes;
		}
	}

	return $supprimes;
}

/**
 * Supprime les analyses NON LANCEES (statut "analyse") d'un utilisateur :
 * une nouvelle analyse remplace les precedentes.
 *
 * @param int $utilisateur ID de l'utilisateur.
 * @return int Nombre de travaux supprimes.
 */
function ditl_import_supprimer_analyses( $utilisateur ) {
	$racine = ditl_import_racine_travaux();

	if ( ! is_dir( $racine ) ) {
		return 0;
	}

	$supprimes = 0;
	$entrees   = scandir( $racine );

	foreach ( is_array( $entrees ) ? $entrees : array() as $entree ) {
		if ( ! ditl_import_jeton_valide( $entree ) ) {
			continue;
		}

		$travail = ditl_import_lire_travail( $entree );

		if ( null === $travail || (int) $travail['utilisateur'] !== (int) $utilisateur || 'analyse' !== $travail['statut'] ) {
			continue;
		}

		$verrou = ditl_import_verrouiller( $entree );

		if ( null === $verrou ) {
			continue;
		}

		if ( ditl_import_supprimer_travail( $entree ) ) {
			++$supprimes;
		}

		ditl_import_deverrouiller( $verrou );
	}

	return $supprimes;
}

/**
 * Pose le verrou d'execution d'un travail (une seule execution a la fois).
 *
 * @param string $jeton Jeton.
 * @return resource|null Poignee du verrou, ou null si deja pris.
 */
function ditl_import_verrouiller( $jeton ) {
	$dossier = ditl_import_dossier_travail( $jeton );

	if ( '' === $dossier || ! is_dir( $dossier ) ) {
		return null;
	}

	$poignee = fopen( $dossier . '/verrou', 'c' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- verrou local.

	if ( false === $poignee ) {
		return null;
	}

	if ( ! flock( $poignee, LOCK_EX | LOCK_NB ) ) {
		fclose( $poignee ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- verrou local.
		return null;
	}

	return $poignee;
}

/**
 * Libere le verrou d'execution.
 *
 * @param resource|null $poignee Poignee du verrou.
 */
function ditl_import_deverrouiller( $poignee ) {
	if ( is_resource( $poignee ) ) {
		flock( $poignee, LOCK_UN );
		fclose( $poignee ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- verrou local.
	}
}

/**
 * Borne le budget d'un lot : jamais plus de la moitie du temps d'execution
 * autorise par PHP (max_execution_time), au moins une seconde.
 *
 * @param int $budget Budget demande (secondes).
 * @return int Budget retenu.
 */
function ditl_import_borner_budget( $budget ) {
	$budget = max( 1, (int) $budget );
	$limite = (int) ini_get( 'max_execution_time' );

	if ( $limite > 0 ) {
		$budget = min( $budget, max( 1, intdiv( $limite, 2 ) ) );
	}

	return $budget;
}

/**
 * Plan d'un travail : preparation du CSV envoye, avec les messages de
 * l'extraction du zip.
 *
 * @param string $jeton   Jeton.
 * @param array  $travail Travail.
 * @return array Plan.
 */
function ditl_import_plan_travail( $jeton, $travail ) {
	$dossier = ditl_import_dossier_travail( $jeton );
	$type    = 'referentiels' === $travail['type'] ? 'referentiels' : 'fiches';
	$plan    = ditl_import_preparer(
		$type,
		$dossier . '/source.csv',
		array(
			'images'  => 'fiches' === $type ? $dossier . '/images' : '',
			'ecraser' => ! empty( $travail['options']['ecraser'] ),
			'nom'     => (string) $travail['fichier'],
		)
	);

	if ( ! empty( $travail['zip']['messages'] ) && is_array( $travail['zip']['messages'] ) ) {
		$plan['messages'] = array_merge( $travail['zip']['messages'], $plan['messages'] );
	}

	if ( ! empty( $travail['zip']['fatal'] ) && is_array( $travail['zip']['fatal'] ) ) {
		$plan['fatal']  = array_merge( $plan['fatal'], $travail['zip']['fatal'] );
		$plan['lignes'] = array();
		$plan['ordre']  = array();
	}

	return $plan;
}

/**
 * Traite un lot d'un travail (verrou deja pose par l'appelant).
 *
 * Relit le travail (position a jour), verifie que le fichier n'a pas
 * change depuis l'analyse, revalide tout le fichier puis traite des lignes
 * pendant le budget de temps.
 *
 * @param string   $jeton  Jeton.
 * @param int|null $budget Budget en secondes (null = regle "budget").
 * @return array Reponse { position, total, termine, compteurs, lignes,
 *               rapport } ou { erreur, code }.
 */
function ditl_import_lot_travail( $jeton, $budget = null ) {
	$travail = ditl_import_lire_travail( $jeton );

	if ( null === $travail || ! in_array( $travail['statut'], array( 'analyse', 'en_cours' ), true ) || ! empty( $travail['fatal'] ) ) {
		return array( 'erreur' => __( 'Cet import ne peut pas être lancé (erreur bloquante ou déjà terminé).', 'ditl' ) );
	}

	$source = ditl_import_dossier_travail( $jeton ) . '/source.csv';

	if ( ! is_file( $source ) || (string) sha1_file( $source ) !== (string) $travail['sha1'] ) {
		return array( 'erreur' => __( 'Le fichier envoyé a changé depuis l\'analyse : abandonner et recommencer.', 'ditl' ) );
	}

	$plan = ditl_import_plan_travail( $jeton, $travail );

	if ( array() !== $plan['fatal'] ) {
		return array( 'erreur' => implode( ' ', $plan['fatal'] ) );
	}

	if ( null === $budget ) {
		/**
		 * Filtre le budget de temps d'un lot de l'ecran d'import (secondes).
		 *
		 * @param int $budget Budget (regle "budget", 15 s par defaut).
		 */
		$budget = (int) apply_filters( 'ditl_import_budget', $plan['regles']['budget'] );
	}

	$budget = ditl_import_borner_budget( $budget );

	// Une fiche publiee peut etre ecrite par ce travail : purge du cache a la
	// fin du travail (couvre un lot interrompu avant sa propre purge).
	if ( 'fiches' === $plan['type'] && empty( $travail['publications'] ) ) {
		foreach ( $plan['lignes'] as $ligne ) {
			$publie_fichier = isset( $ligne['valeurs']['statut'] ) && 'publish' === $ligne['valeurs']['statut'];
			$publie_base    = null !== $ligne['existant'] && 'publish' === $ligne['existant']['statut'];

			if ( ! $ligne['erreur'] && ( $publie_fichier || $publie_base ) ) {
				$travail['publications'] = true;
				ditl_import_ecrire_travail( $jeton, $travail );
				break;
			}
		}
	}

	$execution = ditl_import_executer( $plan, (int) $travail['position'], max( 1, (int) $budget ), false );

	if ( $execution['occupe'] ) {
		return array(
			'erreur' => __( 'Un autre import est en cours d\'exécution (écran d\'administration ou ligne de commande). Réessayer dans quelques minutes : l\'import reprendra là où il s\'est arrêté.', 'ditl' ),
			'code'   => 'import_en_cours',
		);
	}

	$compteurs = ditl_import_compter( $execution['resultats'] );
	$resultats = ditl_import_lire_json( $jeton, 'resultats' );
	$resultats = is_array( $resultats ) ? array_merge( $resultats, $execution['resultats'] ) : $execution['resultats'];

	$travail['position']  = $execution['position'];
	$travail['total']     = $execution['total'];
	$travail['compteurs'] = ditl_import_cumuler( is_array( $travail['compteurs'] ) ? $travail['compteurs'] : array(), $compteurs );
	$travail['statut']    = $execution['termine'] ? 'termine' : 'en_cours';
	$travail['date']      = time();

	ditl_import_ecrire_json( $jeton, 'resultats', $resultats );
	ditl_import_ecrire_travail( $jeton, $travail );

	$lignes = array();

	foreach ( $execution['resultats'] as $resultat ) {
		if ( array() !== $resultat['messages'] ) {
			$lignes[] = $resultat;
		}
	}

	$reponse = array(
		'type'      => $plan['type'],
		'position'  => $execution['position'],
		'total'     => $execution['total'],
		'termine'   => $execution['termine'],
		'traitees'  => count( $execution['resultats'] ),
		'compteurs' => array_merge( ditl_import_compter( array() ), $travail['compteurs'] ),
		'lignes'    => $lignes,
	);

	if ( $execution['termine'] ) {
		if ( ! empty( $travail['publications'] ) ) {
			ditl_import_purger_cache();
		}

		$a_signaler = array();

		foreach ( $resultats as $resultat ) {
			if ( array() !== $resultat['messages'] ) {
				$a_signaler[] = $resultat;
			}
		}

		$reponse['rapport'] = array(
			'messages' => $plan['messages'],
			'groupes'  => $plan['groupes'],
			'lignes'   => $a_signaler,
		);
	}

	return $reponse;
}
