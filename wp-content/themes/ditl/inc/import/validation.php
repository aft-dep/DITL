<?php
/**
 * Import des fiches : preparation et validation (aucune ecriture).
 *
 * ditl_import_preparer() lit le fichier, associe les colonnes au schema,
 * convertit et valide chaque ligne, puis applique les controles de fichier,
 * de doublons, de coherence avec la base et de groupe. Le "plan" obtenu
 * alimente ditl_import_executer() (simulation ou ecriture) ; il est
 * recalcule a chaque lot de l'ecran d'administration (le fichier fait foi).
 *
 * Niveaux de messages :
 * - fatal         : rien n'est importe (fichier illisible, trop gros, sans
 *                   ligne, colonne obligatoire absente, modele absent) ;
 * - erreur        : la ligne est rejetee, les autres passent ;
 * - avertissement : la ligne est importee, le point est signale ;
 * - info          : renseignement sans consequence.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prepare un import : lecture, conversion et validation completes.
 *
 * @param string $type    "fiches" ou "referentiels".
 * @param string $chemin  Chemin absolu du fichier CSV.
 * @param array  $options Options { images : dossier d'images, ecraser : bool,
 *                        nom : nom du fichier pour les messages }.
 * @return array Plan d'import (voir ditl_import_plan_vide()).
 */
function ditl_import_preparer( $type, $chemin, $options = array() ) {
	ditl_import_cache_vider();

	$plan = ditl_import_plan_vide( $type, $options );

	if ( ! in_array( $type, array( 'fiches', 'referentiels' ), true ) ) {
		$plan['fatal'][] = 'Type d\'import inconnu (fiches ou référentiels).';
		return $plan;
	}

	$erreur_modele = ditl_import_verifier_modele();

	if ( '' !== $erreur_modele ) {
		$plan['fatal'][] = $erreur_modele;
		return $plan;
	}

	if ( '' === $plan['fichier'] ) {
		$plan['fichier'] = wp_basename( (string) $chemin );
	}

	$csv                = ditl_import_lire_csv( $chemin, $plan['regles'] );
	$plan['fatal']      = array_merge( $plan['fatal'], $csv['fatal'] );
	$plan['messages']   = array_merge( $plan['messages'], $csv['messages'] );
	$plan['separateur'] = $csv['separateur'];
	$plan['encodage']   = $csv['encodage'];

	if ( array() !== $plan['fatal'] ) {
		return $plan;
	}

	if ( array() === $csv['lignes'] ) {
		$plan['fatal'][] = 'Aucune ligne de données sous l\'en-tête.';
		return $plan;
	}

	if ( 'fiches' === $type ) {
		ditl_import_valider_fiches( $plan, $csv );
	} else {
		ditl_import_valider_referentiels( $plan, $csv );
	}

	if ( array() !== $plan['fatal'] ) {
		$plan['lignes'] = array();
		$plan['ordre']  = array();
	}

	return $plan;
}

/**
 * Plan d'import vide.
 *
 * @param string $type    Type d'import.
 * @param array  $options Options brutes.
 * @return array Plan { type, fichier, options, regles, fatal, messages,
 *               groupes, lignes, ordre, separateur, encodage }.
 */
function ditl_import_plan_vide( $type, $options ) {
	$options = is_array( $options ) ? $options : array();

	return array(
		'type'       => (string) $type,
		'fichier'    => isset( $options['nom'] ) && is_scalar( $options['nom'] ) ? sanitize_file_name( (string) $options['nom'] ) : '',
		'options'    => array(
			'images'  => isset( $options['images'] ) && is_string( $options['images'] ) ? $options['images'] : '',
			'ecraser' => ! empty( $options['ecraser'] ),
		),
		'regles'     => ditl_import_regles(),
		'fatal'      => array(),
		'messages'   => array(),
		'groupes'    => array(),
		'lignes'     => array(),
		'ordre'      => array(),
		'separateur' => '',
		'encodage'   => '',
	);
}

/**
 * Ajoute un message a une ligne (et la rejette si c'est une erreur).
 *
 * @param array  $ligne  Ligne (par reference).
 * @param string $niveau erreur, avertissement ou info.
 * @param string $texte  Message.
 */
function ditl_import_message( &$ligne, $niveau, $texte ) {
	$ligne['messages'][] = array(
		'niveau' => $niveau,
		'texte'  => $texte,
	);

	if ( 'erreur' === $niveau ) {
		$ligne['erreur'] = true;
	}
}

/**
 * Valeur d'une colonne pour un enregistrement.
 *
 * @param array  $enregistrement Enregistrement lu ({ cellules }).
 * @param array  $presentes      Colonnes presentes [nom => index].
 * @param string $nom            Nom canonique.
 * @return string|null Valeur (chaine), ou null si la colonne est absente.
 */
function ditl_import_valeur( $enregistrement, $presentes, $nom ) {
	if ( ! isset( $presentes[ $nom ] ) ) {
		return null;
	}

	$index = $presentes[ $nom ];

	return isset( $enregistrement['cellules'][ $index ] ) ? (string) $enregistrement['cellules'][ $index ] : '';
}

/**
 * Indique si une valeur est un entier strictement positif.
 *
 * @param string|null $valeur Valeur saisie.
 * @return bool True si entier positif (18 chiffres au plus).
 */
function ditl_import_entier_positif( $valeur ) {
	$valeur = ditl_import_rogner( (string) $valeur );

	return '' !== $valeur && strlen( $valeur ) <= 18 && ctype_digit( $valeur ) && (int) $valeur > 0;
}

/**
 * Terme d'un code dans une langue (resolution memorisee pour la requete).
 *
 * @param string $taxonomie Taxonomie.
 * @param string $code      Code normalise.
 * @param string $lang      Langue.
 * @return WP_Term|null Terme, ou null.
 */
function ditl_import_terme( $taxonomie, $code, $lang ) {
	$cle   = $taxonomie . '|' . $code . '|' . $lang;
	$terme = ditl_import_cache_lire( 'termes', $cle, false );

	if ( false === $terme ) {
		$terme = ditl_boite_terme_par_code( $taxonomie, $code, $lang );
		$terme = $terme instanceof WP_Term ? $terme : null;
		ditl_import_cache_ecrire( 'termes', $cle, $terme );
	}

	return $terme;
}

/**
 * Libelles des colonnes de choix, pour les messages.
 *
 * @return array Tableau [colonne => libelle].
 */
function ditl_import_libelles_colonnes() {
	return array(
		'type_compensation' => 'type de compensation',
		'sous_type'         => 'sous-type',
		'difficulte'        => 'difficulté',
		'empechement'       => 'empêchement',
		'metier'            => 'métier',
		'tache'             => 'tâche',
		'environnement'     => 'environnement',
	);
}

/**
 * Valide le fichier des fiches.
 *
 * @param array $plan Plan (par reference).
 * @param array $csv  Resultat de ditl_import_lire_csv().
 */
function ditl_import_valider_fiches( &$plan, $csv ) {
	$schema = ditl_import_schema_fiches();
	$carte  = ditl_import_mapper_entetes( $csv['entetes'], $schema, true, $plan['regles']['fabricants_max'] );

	if ( array() !== $carte['manquantes'] ) {
		$plan['fatal'][] = sprintf( 'Colonne(s) obligatoire(s) absente(s) de l\'en-tête : %s.', implode( ', ', $carte['manquantes'] ) );
	}

	if ( array() !== $carte['doublons'] ) {
		$plan['fatal'][] = sprintf( 'Colonne(s) présente(s) plusieurs fois (alias compris) : %s.', implode( ', ', $carte['doublons'] ) );
	}

	if ( array() !== $plan['fatal'] ) {
		return;
	}

	if ( array() !== $carte['inconnues'] ) {
		$plan['messages'][] = array(
			'niveau' => 'avertissement',
			'texte'  => sprintf( 'Colonne(s) inconnue(s), ignorée(s) : %s.', implode( ', ', array_map( 'ditl_import_extrait', $carte['inconnues'] ) ) ),
		);
	}

	$absentes = array();

	foreach ( array( 'type_compensation', 'difficulte', 'empechement', 'metier', 'tache', 'environnement', 'apport', 'points_vigilance' ) as $colonne ) {
		if ( ! isset( $carte['presentes'][ $colonne ] ) ) {
			$absentes[] = $colonne;
		}
	}

	if ( array() !== $absentes ) {
		$plan['messages'][] = array(
			'niveau' => 'avertissement',
			'texte'  => sprintf( 'Colonne(s) absente(s) du fichier : %s. Fiches existantes : valeurs conservées ; nouvelles fiches : champs vides.', implode( ', ', $absentes ) ),
		);
	}

	foreach ( $csv['lignes'] as $enregistrement ) {
		$plan['lignes'][] = ditl_import_analyser_fiche( $enregistrement, $carte, $schema, $plan );
	}

	ditl_import_controler_doublons_fiches( $plan );
	ditl_import_controler_base_fiches( $plan );
	ditl_import_controler_groupes( $plan );

	$plan['ordre'] = array_keys( $plan['lignes'] );
}

/**
 * Convertit et valide une ligne du fichier des fiches.
 *
 * Etapes, dans cet ordre (ordre des messages et des cles de "valeurs", qui
 * entre dans l'empreinte de la ligne) : identite (id, group_id, langue),
 * titre, statut, slug, image, champs de texte et de choix, fabricants,
 * champs de classement vides.
 *
 * @param array $enregistrement Enregistrement lu.
 * @param array $carte          Correspondance des colonnes.
 * @param array $schema         Schema des fiches.
 * @param array $plan           Plan en cours (regles, options).
 * @return array Ligne { ligne, id, groupe, lang, titre, valeurs, codes,
 *               termes, image, champs, existant, messages, erreur }.
 */
function ditl_import_analyser_fiche( $enregistrement, $carte, $schema, $plan ) {
	$regles    = $plan['regles'];
	$presentes = $carte['presentes'];
	$ligne     = array(
		'ligne'    => (int) $enregistrement['ligne'],
		'id'       => '',
		'groupe'   => '',
		'lang'     => '',
		'titre'    => '',
		'valeurs'  => array( 'metas' => array() ),
		'codes'    => array(),
		'termes'   => array(),
		'image'    => null,
		'champs'   => array( 'post_title', 'lang' ),
		'existant' => null,
		'messages' => array(),
		'erreur'   => false,
	);

	$id     = ditl_import_rogner( (string) ditl_import_valeur( $enregistrement, $presentes, 'id' ) );
	$groupe = ditl_import_rogner( (string) ditl_import_valeur( $enregistrement, $presentes, 'group_id' ) );
	$lang   = strtolower( ditl_import_rogner( (string) ditl_import_valeur( $enregistrement, $presentes, 'lang' ) ) );
	$titre  = (string) ditl_import_valeur( $enregistrement, $presentes, 'title' );

	$ligne['titre'] = ditl_import_extrait( sanitize_text_field( $titre ), 80 );

	if ( '' !== $enregistrement['erreur'] ) {
		ditl_import_message( $ligne, 'erreur', $enregistrement['erreur'] );
		$ligne['id']   = ditl_import_extrait( $id, 20 );
		$ligne['lang'] = ditl_import_extrait( $lang, 10 );
		return $ligne;
	}

	// Langue validee (chaine vide si refusee : les codes ne sont alors pas resolus).
	$lang = ditl_import_analyser_identite( $ligne, $id, $groupe, $lang );

	ditl_import_analyser_titre( $ligne, $titre, $regles );
	ditl_import_analyser_statut( $ligne, ditl_import_valeur( $enregistrement, $presentes, 'statut' ) );
	ditl_import_analyser_slug( $ligne, ditl_import_valeur( $enregistrement, $presentes, 'slug' ) );
	ditl_import_analyser_image( $ligne, ditl_import_valeur( $enregistrement, $presentes, 'image' ), $plan );
	ditl_import_analyser_champs( $ligne, $enregistrement, $presentes, $schema, $lang, $regles );
	ditl_import_analyser_fabricants( $ligne, $enregistrement, $carte, $regles );
	ditl_import_controler_classement( $ligne );

	$ligne['champs'] = array_values( array_unique( $ligne['champs'] ) );
	sort( $ligne['champs'] );

	return $ligne;
}

/**
 * Controle l'identite d'une ligne : id, group_id (entiers positifs) et
 * langue (active sur le site).
 *
 * @param array  $ligne  Ligne (par reference).
 * @param string $id     id saisi (rogne).
 * @param string $groupe group_id saisi (rogne).
 * @param string $lang   Langue saisie (rognee, en minuscules).
 * @return string Langue validee, chaine vide si elle est refusee.
 */
function ditl_import_analyser_identite( &$ligne, $id, $groupe, $lang ) {
	if ( ditl_import_entier_positif( $id ) ) {
		$ligne['id'] = (string) (int) $id;
	} else {
		$ligne['id'] = ditl_import_extrait( $id, 20 );
		ditl_import_message( $ligne, 'erreur', sprintf( 'id « %s » invalide (entier positif attendu).', ditl_import_extrait( $id, 20 ) ) );
	}

	if ( ditl_import_entier_positif( $groupe ) ) {
		$ligne['groupe'] = (string) (int) $groupe;
	} else {
		$ligne['groupe'] = ditl_import_extrait( $groupe, 20 );
		ditl_import_message( $ligne, 'erreur', sprintf( 'group_id « %s » invalide (entier positif attendu).', ditl_import_extrait( $groupe, 20 ) ) );
	}

	$ligne['lang'] = ditl_import_extrait( $lang, 10 );

	if ( ! ditl_ml_langue_valide( $lang ) ) {
		ditl_import_message( $ligne, 'erreur', sprintf( 'langue « %1$s » inconnue ou inactive (langues du site : %2$s).', ditl_import_extrait( $lang, 10 ), implode( ', ', ditl_ml_langues() ) ) );
		$lang = '';
	}

	return $lang;
}

/**
 * Controle le titre (texte simple : balises retirees) et le retient.
 *
 * @param array  $ligne  Ligne (par reference).
 * @param string $titre  Titre saisi.
 * @param array  $regles Regles de l'import.
 */
function ditl_import_analyser_titre( &$ligne, $titre, $regles ) {
	$titre_propre = sanitize_text_field( $titre );

	if ( '' === $titre_propre ) {
		ditl_import_message( $ligne, 'erreur', 'titre vide.' );
	} else {
		if ( ditl_import_contient_html( $titre ) ) {
			ditl_import_message( $ligne, 'avertissement', 'titre : balises HTML retirées.' );
		}

		$mots = ditl_import_compter_mots( $titre_propre );

		if ( $mots < $regles['titre_mots_min'] || $mots > $regles['titre_mots_max'] ) {
			ditl_import_message( $ligne, 'avertissement', sprintf( 'titre de %1$d mot(s) (attendu : %2$d à %3$d).', $mots, $regles['titre_mots_min'], $regles['titre_mots_max'] ) );
		}
	}

	$ligne['valeurs']['post_title'] = $titre_propre;
}

/**
 * Interprete le statut (colonne facultative).
 *
 * @param array       $ligne  Ligne (par reference).
 * @param string|null $statut Statut saisi, null si la colonne est absente.
 */
function ditl_import_analyser_statut( &$ligne, $statut ) {
	if ( null === $statut ) {
		return;
	}

	$interprete = ditl_import_statut( $statut );

	if ( null === $interprete ) {
		ditl_import_message( $ligne, 'avertissement', sprintf( 'statut « %s » inconnu (brouillon ou publié) : brouillon pour une nouvelle fiche, inchangé sinon.', ditl_import_extrait( $statut, 20 ) ) );
		$interprete = '';
	}

	$ligne['valeurs']['statut'] = $interprete;
}

/**
 * Nettoie le slug (colonne facultative).
 *
 * @param array       $ligne Ligne (par reference).
 * @param string|null $slug  Slug saisi, null si la colonne est absente.
 */
function ditl_import_analyser_slug( &$ligne, $slug ) {
	if ( null === $slug ) {
		return;
	}

	$slug_propre = sanitize_title( $slug );

	if ( '' !== $slug && '' === $slug_propre ) {
		ditl_import_message( $ligne, 'avertissement', 'slug inexploitable : ignoré.' );
	}

	$ligne['valeurs']['post_name'] = $slug_propre;
}

/**
 * Resout l'image d'une ligne (colonne facultative) : vide = illustration
 * retiree ; nom refuse ou image introuvable = illustration non modifiee.
 *
 * @param array       $ligne Ligne (par reference).
 * @param string|null $image Nom de fichier saisi, null si la colonne est absente.
 * @param array       $plan  Plan en cours (regles, dossier d'images).
 */
function ditl_import_analyser_image( &$ligne, $image, $plan ) {
	if ( null === $image ) {
		return;
	}

	$image = ditl_import_rogner( $image );

	if ( '' === $image ) {
		$ligne['image']    = array( 'nom' => '' );
		$ligne['champs'][] = '_thumbnail_id';
		return;
	}

	$refus = ditl_import_controler_nom_image( $image );

	if ( '' !== $refus ) {
		ditl_import_message( $ligne, 'avertissement', sprintf( 'image « %1$s » : %2$s : illustration non modifiée.', ditl_import_extrait( $image, 60 ), $refus ) );
		return;
	}

	$resolution = ditl_import_resoudre_image( $image, $plan['options']['images'], $plan['regles'] );

	if ( '' === $resolution['source'] ) {
		ditl_import_message( $ligne, 'avertissement', $resolution['message'] . '.' );
		return;
	}

	$ligne['image']    = array_merge( array( 'nom' => $image ), $resolution );
	$ligne['champs'][] = '_thumbnail_id';
}

/**
 * Convertit les champs de texte et de choix presents dans le fichier
 * (titre, slug et image exceptes) vers leur cible (champ de la fiche ou
 * meta).
 *
 * @param array  $ligne          Ligne (par reference).
 * @param array  $enregistrement Enregistrement lu.
 * @param array  $presentes      Colonnes presentes [nom => index].
 * @param array  $schema         Schema des fiches.
 * @param string $lang           Langue validee (vide : codes non resolus).
 * @param array  $regles         Regles de l'import.
 */
function ditl_import_analyser_champs( &$ligne, $enregistrement, $presentes, $schema, $lang, $regles ) {
	$libelles = ditl_import_libelles_colonnes();

	foreach ( $schema as $nom => $definition ) {
		// Titre, slug et image : traites a part.
		if ( ! isset( $presentes[ $nom ] ) || empty( $definition['cible'] ) || in_array( $nom, array( 'title', 'slug', 'image' ), true ) ) {
			continue;
		}

		$brut  = (string) ditl_import_valeur( $enregistrement, $presentes, $nom );
		$cible = (array) $definition['cible'];
		$type  = isset( $definition['type'] ) ? $definition['type'] : 'texte';

		if ( 'codes' === $type && 'taxonomie' === $cible[0] ) {
			ditl_import_analyser_codes( $ligne, $nom, $brut, $cible, isset( $libelles[ $nom ] ) ? $libelles[ $nom ] : $nom, $lang );
			continue;
		}

		$propre = ditl_import_convertir_champ( $ligne, $nom, $brut, $type, $definition, $regles );

		if ( null === $propre ) {
			continue;
		}

		if ( 'post' === $cible[0] ) {
			if ( 'post_title' !== $cible[1] && 'post_name' !== $cible[1] ) {
				$ligne['valeurs'][ $cible[1] ] = $propre;
				$ligne['champs'][]             = $cible[1];
			}
		} elseif ( 'meta' === $cible[0] ) {
			$ligne['valeurs']['metas'][ $cible[1] ] = $propre;
			$ligne['champs'][]                      = $cible[1];
		}
	}
}

/**
 * Resout les codes d'une colonne de choix en termes de la langue de la
 * ligne (taxonomie et niveau donnes par la cible du schema).
 *
 * @param array  $ligne   Ligne (par reference).
 * @param string $nom     Nom canonique de la colonne.
 * @param string $brut    Cellule (codes separes par "|").
 * @param array  $cible   Cible { 'taxonomie', taxonomie, niveau }.
 * @param string $libelle Libelle de la colonne pour les messages.
 * @param string $lang    Langue validee (vide : codes non resolus).
 */
function ditl_import_analyser_codes( &$ligne, $nom, $brut, $cible, $libelle, $lang ) {
	$taxonomie = (string) $cible[1];
	$niveau    = isset( $cible[2] ) ? (int) $cible[2] : 1;

	if ( ! isset( $ligne['termes'][ $taxonomie ] ) ) {
		$ligne['termes'][ $taxonomie ] = array();
		$ligne['champs'][]             = 'tax:' . $taxonomie;
	}

	$ligne['codes'][ $nom ] = array();

	foreach ( ditl_import_decouper( $brut ) as $code_brut ) {
		$code = ditl_import_normaliser_code( $code_brut );

		if ( '' === $code ) {
			ditl_import_message( $ligne, 'erreur', sprintf( '%1$s : code « %2$s » invalide.', $libelle, ditl_import_extrait( $code_brut, 40 ) ) );
			continue;
		}

		$ligne['codes'][ $nom ][] = $code;

		if ( '' === $lang ) {
			continue;
		}

		$terme = ditl_import_terme( $taxonomie, $code, $lang );

		if ( null === $terme ) {
			ditl_import_message( $ligne, 'erreur', sprintf( '%1$s : code « %2$s » inconnu des référentiels en langue %3$s.', $libelle, $code, $lang ) );
			continue;
		}

		if ( 1 === $niveau && 0 !== (int) $terme->parent ) {
			ditl_import_message( $ligne, 'avertissement', sprintf( '%1$s : « %2$s » est un élément de niveau 2 (rattaché à un parent).', $libelle, $code ) );
		} elseif ( 2 === $niveau && 0 === (int) $terme->parent ) {
			ditl_import_message( $ligne, 'avertissement', sprintf( '%1$s : « %2$s » est un élément de niveau 1 (sans parent).', $libelle, $code ) );
		}

		if ( ! in_array( (int) $terme->term_id, $ligne['termes'][ $taxonomie ], true ) ) {
			$ligne['termes'][ $taxonomie ][] = (int) $terme->term_id;
		}
	}
}

/**
 * Convertit une cellule de texte selon son type.
 *
 * @param array  $ligne      Ligne (par reference).
 * @param string $nom        Nom canonique de la colonne.
 * @param string $brut       Cellule.
 * @param string $type       texte, texte_long, texte_riche ou urls.
 * @param array  $definition Definition de la colonne (schema).
 * @param array  $regles     Regles de l'import.
 * @return string|null Valeur convertie, null pour un type non gere.
 */
function ditl_import_convertir_champ( &$ligne, $nom, $brut, $type, $definition, $regles ) {
	if ( 'texte' === $type || 'texte_long' === $type ) {
		$propre = 'texte' === $type ? sanitize_text_field( $brut ) : sanitize_textarea_field( $brut );

		if ( ditl_import_contient_html( $brut ) ) {
			ditl_import_message( $ligne, 'avertissement', sprintf( '%s : balises HTML retirées.', $nom ) );
		}

		return $propre;
	}

	if ( 'texte_riche' === $type ) {
		return ditl_import_convertir_texte_riche( $ligne, $nom, $brut, $definition, $regles );
	}

	if ( 'urls' === $type ) {
		return ditl_import_convertir_urls( $ligne, $nom, $brut );
	}

	return null;
}

/**
 * Convertit un texte riche (convention du 07/09) et controle sa longueur
 * minimale (regle nommee par la definition de la colonne).
 *
 * @param array  $ligne      Ligne (par reference).
 * @param string $nom        Nom canonique de la colonne.
 * @param string $brut       Cellule.
 * @param array  $definition Definition de la colonne (schema).
 * @param array  $regles     Regles de l'import.
 * @return string HTML.
 */
function ditl_import_convertir_texte_riche( &$ligne, $nom, $brut, $definition, $regles ) {
	$propre = ditl_import_texte_riche( $brut );

	if ( ditl_import_contient_html( $brut ) ) {
		ditl_import_message( $ligne, 'avertissement', sprintf( '%s : HTML détecté, affiché tel quel (mise en forme : retour à la ligne, « - » pour une puce, **gras**).', $nom ) );
	}

	if ( ! empty( $definition['minimum'] ) && isset( $regles[ $definition['minimum'] ] ) && $regles[ $definition['minimum'] ] > 0 ) {
		$longueur = ditl_import_longueur( $brut );

		if ( 0 === $longueur ) {
			ditl_import_message( $ligne, 'avertissement', sprintf( '%s vide.', $nom ) );
		} elseif ( $longueur < $regles[ $definition['minimum'] ] ) {
			ditl_import_message( $ligne, 'avertissement', sprintf( '%1$s : %2$d caractères (minimum attendu : %3$d).', $nom, $longueur, $regles[ $definition['minimum'] ] ) );
		}
	}

	return $propre;
}

/**
 * Convertit une liste d'URL separees par "|" (invalides ignorees, doublons
 * retires, 100 au plus).
 *
 * @param array  $ligne Ligne (par reference).
 * @param string $nom   Nom canonique de la colonne.
 * @param string $brut  Cellule.
 * @return string Liste JSON.
 */
function ditl_import_convertir_urls( &$ligne, $nom, $brut ) {
	$urls = array();

	foreach ( ditl_import_decouper( $brut ) as $url_brute ) {
		$url = ditl_import_url( $url_brute );

		if ( '' === $url ) {
			ditl_import_message( $ligne, 'avertissement', sprintf( '%1$s : URL invalide ignorée « %2$s ».', $nom, ditl_import_extrait( $url_brute, 60 ) ) );
		} elseif ( ! in_array( $url, $urls, true ) ) {
			$urls[] = $url;
		}
	}

	return (string) wp_json_encode( array_slice( $urls, 0, 100 ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Fabricants : colonnes fabricant_N / fabricant_N_url, puis colonne
 * "fabricants" (liste de noms, format de juillet). Rien si aucune de ces
 * colonnes n'est presente.
 *
 * @param array $ligne          Ligne (par reference).
 * @param array $enregistrement Enregistrement lu.
 * @param array $carte          Correspondance des colonnes.
 * @param array $regles         Regles de l'import.
 */
function ditl_import_analyser_fabricants( &$ligne, $enregistrement, $carte, $regles ) {
	$presentes = $carte['presentes'];

	if ( ! isset( $presentes['fabricants'] ) && array() === $carte['fabricants'] ) {
		return;
	}

	$fabricants = array();

	foreach ( $carte['fabricants'] as $numero => $parties ) {
		$nom   = isset( $parties['nom'] ) && isset( $enregistrement['cellules'][ $parties['nom'] ] ) ? sanitize_text_field( (string) $enregistrement['cellules'][ $parties['nom'] ] ) : '';
		$brute = isset( $parties['url'] ) && isset( $enregistrement['cellules'][ $parties['url'] ] ) ? ditl_import_rogner( (string) $enregistrement['cellules'][ $parties['url'] ] ) : '';
		$url   = '' === $brute ? '' : ditl_import_url( $brute );

		if ( '' !== $brute && '' === $url ) {
			ditl_import_message( $ligne, 'avertissement', sprintf( 'fabricant %1$d : URL invalide ignorée « %2$s ».', $numero, ditl_import_extrait( $brute, 60 ) ) );
		}

		if ( '' === $nom && '' !== $url ) {
			ditl_import_message( $ligne, 'avertissement', sprintf( 'fabricant %d : URL sans nom.', $numero ) );
		}

		if ( '' !== $nom || '' !== $url ) {
			$fabricants[] = array(
				'nom' => $nom,
				'url' => $url,
			);
		}
	}

	if ( isset( $presentes['fabricants'] ) ) {
		foreach ( ditl_import_decouper( (string) ditl_import_valeur( $enregistrement, $presentes, 'fabricants' ) ) as $nom ) {
			$nom = sanitize_text_field( $nom );

			if ( '' !== $nom ) {
				$fabricants[] = array(
					'nom' => $nom,
					'url' => '',
				);
			}
		}
	}

	$ligne['valeurs']['metas']['_ditl_fabricants'] = (string) wp_json_encode( array_slice( $fabricants, 0, $regles['fabricants_max'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$ligne['champs'][]                             = '_ditl_fabricants';
}

/**
 * Signale les champs de classement presents mais vides (fiche absente du
 * questionnaire ou des filtres).
 *
 * @param array $ligne Ligne (par reference).
 */
function ditl_import_controler_classement( &$ligne ) {
	$vides = array(
		'type_compensation' => array( array( 'type_compensation' ), 'aucun type de compensation.' ),
		'aptitude'          => array( array( 'difficulte', 'empechement' ), 'aucune difficulté ni aucun empêchement (questionnaire).' ),
		'metier'            => array( array( 'metier', 'tache' ), 'aucun métier ni aucune tâche (questionnaire).' ),
		'environnement'     => array( array( 'environnement' ), 'aucun environnement (filtre des résultats).' ),
	);

	foreach ( $vides as $controle ) {
		$presente = false;
		$remplie  = false;

		foreach ( $controle[0] as $colonne ) {
			if ( isset( $ligne['codes'][ $colonne ] ) ) {
				$presente = true;
				$remplie  = $remplie || array() !== $ligne['codes'][ $colonne ];
			}
		}

		if ( $presente && ! $remplie ) {
			ditl_import_message( $ligne, 'avertissement', $controle[1] );
		}
	}
}

/**
 * Controle les doublons du fichier : id, couple group_id + langue.
 *
 * Toutes les occurrences d'un doublon sont rejetees (impossible de savoir
 * laquelle fait foi).
 *
 * @param array $plan Plan (par reference).
 */
function ditl_import_controler_doublons_fiches( &$plan ) {
	$par_id     = array();
	$par_groupe = array();

	foreach ( $plan['lignes'] as $index => $ligne ) {
		if ( ditl_import_entier_positif( $ligne['id'] ) ) {
			$par_id[ $ligne['id'] ][] = $index;
		}

		if ( ditl_import_entier_positif( $ligne['groupe'] ) && ditl_ml_langue_valide( $ligne['lang'] ) ) {
			$par_groupe[ $ligne['groupe'] . '|' . $ligne['lang'] ][] = $index;
		}
	}

	foreach ( $par_id as $id => $indices ) {
		if ( count( $indices ) < 2 ) {
			continue;
		}

		$numeros = ditl_import_numeros_lignes( $plan, $indices );

		foreach ( $indices as $index ) {
			ditl_import_message( $plan['lignes'][ $index ], 'erreur', sprintf( 'id %1$s en double (lignes %2$s).', $id, $numeros ) );
		}
	}

	foreach ( $par_groupe as $cle => $indices ) {
		if ( count( $indices ) < 2 ) {
			continue;
		}

		list( $groupe, $lang ) = explode( '|', $cle );
		$numeros               = ditl_import_numeros_lignes( $plan, $indices );

		foreach ( $indices as $index ) {
			ditl_import_message( $plan['lignes'][ $index ], 'erreur', sprintf( 'groupe %1$s : langue %2$s en double (lignes %3$s).', $groupe, $lang, $numeros ) );
		}
	}
}

/**
 * Liste lisible des numeros de lignes physiques.
 *
 * @param array $plan    Plan.
 * @param int[] $indices Index de lignes.
 * @return string Numeros separes par des virgules.
 */
function ditl_import_numeros_lignes( $plan, $indices ) {
	$numeros = array();

	foreach ( $indices as $index ) {
		$numeros[] = (string) $plan['lignes'][ $index ]['ligne'];
	}

	return implode( ', ', $numeros );
}

/**
 * Fiches deja importees, indexees par id d'import.
 *
 * @return array Tableau [id d'import => liste de { ID, statut, groupe, langue }].
 */
function ditl_import_fiches_existantes() {
	global $wpdb;

	$lignes = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- une requete par preparation.
		$wpdb->prepare(
			"SELECT p.ID, p.post_status, mi.meta_value AS import_id, mg.meta_value AS groupe, ml.meta_value AS langue
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} mi ON mi.post_id = p.ID AND mi.meta_key = '_ditl_import_id'
			LEFT JOIN {$wpdb->postmeta} mg ON mg.post_id = p.ID AND mg.meta_key = '_ditl_import_groupe'
			LEFT JOIN {$wpdb->postmeta} ml ON ml.post_id = p.ID AND ml.meta_key = '_ditl_import_langue'
			WHERE p.post_type = %s
			ORDER BY p.ID ASC",
			ditl_import_type_fiche()
		)
	);

	$existantes = array();
	$vus        = array();

	foreach ( (array) $lignes as $ligne ) {
		$id = (int) $ligne->ID;

		if ( isset( $vus[ $id ] ) ) {
			continue;
		}

		$vus[ $id ] = true;

		$existantes[ (string) $ligne->import_id ][] = array(
			'ID'     => $id,
			'statut' => (string) $ligne->post_status,
			'groupe' => (string) $ligne->groupe,
			'langue' => (string) $ligne->langue,
		);
	}

	return $existantes;
}

/**
 * Controle la coherence des lignes avec les fiches deja importees.
 *
 * - plusieurs fiches en base pour un meme id : erreur ;
 * - id deja importe avec un autre groupe ou une autre langue : erreur
 *   (protege contre des ids renumerotes apres un tri du fichier) ;
 * - couple groupe + langue deja occupe par une autre fiche : erreur.
 *
 * @param array $plan Plan (par reference).
 */
function ditl_import_controler_base_fiches( &$plan ) {
	$existantes = ditl_import_fiches_existantes();
	$occupes    = array();

	foreach ( $existantes as $id => $fiches ) {
		foreach ( $fiches as $fiche ) {
			if ( 'trash' !== $fiche['statut'] && '' !== $fiche['groupe'] && '' !== $fiche['langue'] ) {
				$occupes[ $fiche['groupe'] . '|' . $fiche['langue'] ][] = array(
					'id' => (string) $id,
					'ID' => $fiche['ID'],
				);
			}
		}
	}

	foreach ( $plan['lignes'] as $index => $ligne ) {
		if ( ! ditl_import_entier_positif( $ligne['id'] ) ) {
			continue;
		}

		$ligne_ref = &$plan['lignes'][ $index ];

		if ( isset( $existantes[ $ligne['id'] ] ) ) {
			$fiches = $existantes[ $ligne['id'] ];

			if ( count( $fiches ) > 1 ) {
				ditl_import_message( $ligne_ref, 'erreur', sprintf( 'plusieurs fiches en base pour l\'id %1$s (fiches %2$s) : en supprimer une.', $ligne['id'], implode( ', ', wp_list_pluck( $fiches, 'ID' ) ) ) );
			} else {
				$fiche = $fiches[0];

				if ( ( '' !== $fiche['groupe'] && $fiche['groupe'] !== $ligne['groupe'] ) || ( '' !== $fiche['langue'] && $fiche['langue'] !== $ligne['lang'] ) ) {
					ditl_import_message( $ligne_ref, 'erreur', sprintf( 'l\'id %1$s a déjà été importé pour la fiche %2$d (groupe %3$s, langue %4$s) : ne jamais renuméroter les id.', $ligne['id'], $fiche['ID'], $fiche['groupe'], $fiche['langue'] ) );
				} else {
					$ligne_ref['existant'] = $fiche;
				}
			}
		}

		$cle = $ligne['groupe'] . '|' . $ligne['lang'];

		if ( isset( $occupes[ $cle ] ) ) {
			foreach ( $occupes[ $cle ] as $occupant ) {
				if ( $occupant['id'] !== $ligne['id'] ) {
					ditl_import_message( $ligne_ref, 'erreur', sprintf( 'le groupe %1$s a déjà une fiche en langue %2$s (fiche %3$d, id %4$s).', $ligne['groupe'], $ligne['lang'], $occupant['ID'], $occupant['id'] ) );
					break;
				}
			}
		}

		unset( $ligne_ref );
	}
}

/**
 * Controles de groupe (lignes valides d'un meme group_id) : langues
 * manquantes, image differente, codes differents entre langues ; et
 * application des metas communes (ditl_import_metas_communes) : la valeur
 * de la ligne de la langue source vaut pour tout le groupe.
 *
 * @param array $plan Plan (par reference).
 */
function ditl_import_controler_groupes( &$plan ) {
	$groupes = array();
	$indices = array();

	foreach ( $plan['lignes'] as $index => $ligne ) {
		if ( ! $ligne['erreur'] ) {
			$groupes[ $ligne['groupe'] ][] = $ligne;
			$indices[ $ligne['groupe'] ][] = $index;
		}
	}

	$defaut = ditl_ml_langue_defaut();

	foreach ( $indices as $groupe => $liste ) {
		// Ligne de reference : langue par defaut, sinon fr, sinon la premiere.
		$reference = $liste[0];

		foreach ( array( 'fr', $defaut ) as $prioritaire ) {
			foreach ( $liste as $index ) {
				if ( $plan['lignes'][ $index ]['lang'] === $prioritaire ) {
					$reference = $index;
					break;
				}
			}
		}

		foreach ( ditl_import_metas_communes() as $cle ) {
			if ( ! isset( $plan['lignes'][ $reference ]['valeurs']['metas'][ $cle ] ) ) {
				continue;
			}

			$valeur     = $plan['lignes'][ $reference ]['valeurs']['metas'][ $cle ];
			$differente = false;

			foreach ( $liste as $index ) {
				if ( isset( $plan['lignes'][ $index ]['valeurs']['metas'][ $cle ] ) && $plan['lignes'][ $index ]['valeurs']['metas'][ $cle ] !== $valeur ) {
					$differente = true;
				}

				if ( isset( $plan['lignes'][ $index ]['valeurs']['metas'][ $cle ] ) ) {
					$plan['lignes'][ $index ]['valeurs']['metas'][ $cle ] = $valeur;
				}
			}

			if ( $differente ) {
				$plan['groupes'][ (string) $groupe ][] = array(
					'niveau' => 'avertissement',
					'texte'  => sprintf( '%1$s : valeurs différentes selon la langue ; champ commun à toutes les langues, la valeur de la ligne %2$s (langue %3$s) est appliquée au groupe.', '_ditl_fabricants' === $cle ? 'fabricants' : ( '_ditl_sources' === $cle ? 'sources' : $cle ), $plan['lignes'][ $reference ]['ligne'], $plan['lignes'][ $reference ]['lang'] ),
				);
			}
		}
	}

	$langues  = ditl_ml_langues();
	$libelles = ditl_import_libelles_colonnes();

	foreach ( $groupes as $groupe => $lignes ) {
		$messages  = array();
		$presentes = array();
		$images    = array();
		$codes     = array();

		foreach ( $lignes as $ligne ) {
			$presentes[] = $ligne['lang'];

			if ( null !== $ligne['image'] ) {
				$images[ ditl_import_cle_image( $ligne['image']['nom'] ) ] = $ligne['image']['nom'];
			}

			foreach ( $ligne['codes'] as $colonne => $liste ) {
				sort( $liste );
				$codes[ $colonne ][ implode( '|', $liste ) ] = true;
			}
		}

		$manquantes = array_diff( $langues, $presentes );

		if ( array() !== $manquantes ) {
			$messages[] = array(
				'niveau' => 'avertissement',
				'texte'  => sprintf( 'langue(s) manquante(s) : %s.', implode( ', ', $manquantes ) ),
			);
		}

		if ( count( $images ) > 1 ) {
			$messages[] = array(
				'niveau' => 'avertissement',
				'texte'  => sprintf( 'image différente selon la langue (%s) : elle devrait être identique.', implode( ', ', array_map( 'ditl_import_extrait', array_values( $images ) ) ) ),
			);
		}

		foreach ( $codes as $colonne => $variantes ) {
			if ( count( $variantes ) > 1 ) {
				$messages[] = array(
					'niveau' => 'avertissement',
					'texte'  => sprintf( '%s : codes différents selon la langue (chaque langue garde les siens).', isset( $libelles[ $colonne ] ) ? $libelles[ $colonne ] : $colonne ),
				);
			}
		}

		if ( array() !== $messages ) {
			$plan['groupes'][ (string) $groupe ] = array_merge( $messages, isset( $plan['groupes'][ (string) $groupe ] ) ? $plan['groupes'][ (string) $groupe ] : array() );
		}
	}
}

/**
 * Valide le fichier des referentiels.
 *
 * @param array $plan Plan (par reference).
 * @param array $csv  Resultat de ditl_import_lire_csv().
 */
function ditl_import_valider_referentiels( &$plan, $csv ) {
	$schema  = ditl_import_schema_referentiels();
	$carte   = ditl_import_mapper_entetes( $csv['entetes'], $schema, false );
	$langues = ditl_ml_langues();

	if ( array() !== $carte['manquantes'] ) {
		$plan['fatal'][] = sprintf( 'Colonne(s) obligatoire(s) absente(s) de l\'en-tête : %s.', implode( ', ', $carte['manquantes'] ) );
	}

	if ( array() !== $carte['doublons'] ) {
		$plan['fatal'][] = sprintf( 'Colonne(s) présente(s) plusieurs fois (alias compris) : %s.', implode( ', ', $carte['doublons'] ) );
	}

	$colonnes_langues = array_values( array_intersect( $langues, array_keys( $carte['presentes'] ) ) );

	if ( array() === $colonnes_langues ) {
		$plan['fatal'][] = sprintf( 'Aucune colonne de libellé : une colonne par langue est attendue (%s).', implode( ', ', $langues ) );
	}

	if ( array() !== $plan['fatal'] ) {
		return;
	}

	if ( array() !== $carte['inconnues'] ) {
		$plan['messages'][] = array(
			'niveau' => 'avertissement',
			'texte'  => sprintf( 'Colonne(s) inconnue(s), ignorée(s) : %s (les langues inactives du site sont ignorées).', implode( ', ', array_map( 'ditl_import_extrait', $carte['inconnues'] ) ) ),
		);
	}

	$listes = ditl_import_listes_referentiels();
	$rangs  = array();

	foreach ( $csv['lignes'] as $enregistrement ) {
		// Fichier issu de l'export : neutralisation des formules retiree.
		$enregistrement['cellules'] = array_map( 'ditl_import_deneutraliser_cellule', $enregistrement['cellules'] );

		$ligne = ditl_import_analyser_referentiel( $enregistrement, $carte, $listes, $langues );

		// Rang dans la taxonomie (ordre du fichier) si pas de colonne ordre.
		if ( ! $ligne['erreur'] && null === $ligne['ordre'] ) {
			$rangs[ $ligne['taxonomie'] ] = isset( $rangs[ $ligne['taxonomie'] ] ) ? $rangs[ $ligne['taxonomie'] ] + 1 : 1;
			$ligne['ordre']               = $rangs[ $ligne['taxonomie'] ];
		}

		$plan['lignes'][] = $ligne;
	}

	ditl_import_controler_referentiels( $plan );

	// Ordre de traitement : niveau 1 d'abord (parents crees avant les enfants).
	$premiers = array();
	$seconds  = array();

	foreach ( $plan['lignes'] as $index => $ligne ) {
		if ( '' === $ligne['parent'] ) {
			$premiers[] = $index;
		} else {
			$seconds[] = $index;
		}
	}

	$plan['ordre'] = array_merge( $premiers, $seconds );
}

/**
 * Convertit et valide une ligne du fichier des referentiels.
 *
 * @param array    $enregistrement Enregistrement lu.
 * @param array    $carte          Correspondance des colonnes.
 * @param array    $listes         Listes acceptees (ditl_import_listes_referentiels).
 * @param string[] $langues        Langues actives.
 * @return array Ligne { ligne, liste, taxonomie, niveau, code, parent,
 *               couleur, ordre, libelles, titre, id, lang, messages, erreur }.
 */
function ditl_import_analyser_referentiel( $enregistrement, $carte, $listes, $langues ) {
	$presentes = $carte['presentes'];
	$ligne     = array(
		'ligne'     => (int) $enregistrement['ligne'],
		'liste'     => '',
		'taxonomie' => '',
		'niveau'    => 1,
		'code'      => '',
		'parent'    => '',
		'couleur'   => null,
		'ordre'     => null,
		'libelles'  => array(),
		'titre'     => '',
		'id'        => '',
		'lang'      => '',
		'messages'  => array(),
		'erreur'    => false,
	);

	$liste_brute = (string) ditl_import_valeur( $enregistrement, $presentes, 'taxonomie' );
	$code_brut   = (string) ditl_import_valeur( $enregistrement, $presentes, 'code' );

	$ligne['liste'] = ditl_import_extrait( $liste_brute, 40 );
	$ligne['id']    = ditl_import_extrait( $code_brut, 40 );

	if ( '' !== $enregistrement['erreur'] ) {
		ditl_import_message( $ligne, 'erreur', $enregistrement['erreur'] );
		return $ligne;
	}

	$liste = ditl_import_normaliser_entete( $liste_brute );

	if ( ! isset( $listes[ $liste ] ) ) {
		ditl_import_message( $ligne, 'erreur', sprintf( 'liste « %s » inconnue (attendu : type_compensation, sous_type, difficulte, empechement, metier, tache, environnement).', ditl_import_extrait( $liste_brute, 40 ) ) );
		return $ligne;
	}

	$taxonomie          = $listes[ $liste ][0];
	$niveau             = (int) $listes[ $liste ][1];
	$definitions        = ditl_boite_taxonomies();
	$definition         = isset( $definitions[ $taxonomie ] ) ? $definitions[ $taxonomie ] : array(
		'niveaux' => 1,
		'couleur' => false,
	);
	$ligne['taxonomie'] = $taxonomie;
	$ligne['liste']     = $liste;

	$code = ditl_import_normaliser_code( $code_brut );

	if ( '' === $code ) {
		ditl_import_message( $ligne, 'erreur', sprintf( 'code « %s » vide ou invalide (lettres minuscules, chiffres, _ et - seulement).', ditl_import_extrait( $code_brut, 40 ) ) );
		return $ligne;
	}

	if ( $code !== strtolower( ditl_import_rogner( $code_brut ) ) ) {
		ditl_import_message( $ligne, 'avertissement', sprintf( 'code « %1$s » lu comme « %2$s ».', ditl_import_extrait( $code_brut, 40 ), $code ) );
	}

	$ligne['code'] = $code;
	$ligne['id']   = $code;

	$parent_brut = ditl_import_rogner( (string) ditl_import_valeur( $enregistrement, $presentes, 'parent' ) );
	$parent      = '' === $parent_brut ? '' : ditl_import_normaliser_code( $parent_brut );

	if ( '' !== $parent_brut && '' === $parent ) {
		ditl_import_message( $ligne, 'erreur', sprintf( 'code parent « %s » invalide.', ditl_import_extrait( $parent_brut, 40 ) ) );
	}

	if ( 0 === $niveau ) {
		$niveau = '' === $parent ? 1 : 2;
	}

	if ( 1 === $niveau && '' !== $parent ) {
		ditl_import_message( $ligne, 'erreur', sprintf( 'un élément « %s » n\'a pas de parent.', $liste ) );
	} elseif ( 2 === $niveau && '' === $parent ) {
		ditl_import_message( $ligne, 'erreur', sprintf( 'un élément « %s » doit indiquer son parent (colonne parent).', $liste ) );
	} elseif ( 2 === $niveau && (int) $definition['niveaux'] < 2 ) {
		ditl_import_message( $ligne, 'erreur', 'cette liste n\'a qu\'un niveau : colonne parent à laisser vide.' );
	}

	$ligne['niveau'] = $niveau;
	$ligne['parent'] = $parent;

	// Couleur (environnements seulement).
	$couleur = ditl_import_valeur( $enregistrement, $presentes, 'couleur' );

	if ( null !== $couleur ) {
		$couleur = ditl_import_rogner( $couleur );

		if ( ! empty( $definition['couleur'] ) ) {
			$propre = ditl_boite_sanitize_couleur( $couleur );

			if ( '' !== $couleur && '' === $propre ) {
				ditl_import_message( $ligne, 'avertissement', sprintf( 'couleur « %s » invalide (format #rrggbb attendu) : ignorée.', ditl_import_extrait( $couleur, 20 ) ) );
			} else {
				$ligne['couleur'] = $propre;
			}
		} elseif ( '' !== $couleur ) {
			ditl_import_message( $ligne, 'avertissement', 'couleur ignorée (seuls les environnements ont une couleur).' );
		}
	}

	// Ordre explicite.
	$ordre = ditl_import_rogner( (string) ditl_import_valeur( $enregistrement, $presentes, 'ordre' ) );

	if ( '' !== $ordre ) {
		if ( preg_match( '/^-?\d{1,4}$/', $ordre ) ) {
			$ligne['ordre'] = ditl_boite_sanitize_ordre( $ordre );
		} else {
			ditl_import_message( $ligne, 'avertissement', sprintf( 'ordre « %s » invalide (entier attendu) : rang dans le fichier utilisé.', ditl_import_extrait( $ordre, 20 ) ) );
		}
	}

	// Libelles : langue de la ligne, sinon francais, sinon anglais, sinon code.
	$saisis = array();

	foreach ( $langues as $lang ) {
		$brut = ditl_import_valeur( $enregistrement, $presentes, $lang );

		if ( null === $brut ) {
			continue;
		}

		if ( ditl_import_contient_html( $brut ) ) {
			ditl_import_message( $ligne, 'avertissement', sprintf( 'libellé %s : balises HTML retirées.', $lang ) );
		}

		$propre = sanitize_text_field( $brut );

		if ( '' !== $propre ) {
			$saisis[ $lang ] = $propre;
		}
	}

	$replis = array();

	foreach ( $langues as $lang ) {
		if ( isset( $saisis[ $lang ] ) ) {
			$ligne['libelles'][ $lang ] = $saisis[ $lang ];
		} elseif ( isset( $saisis['fr'] ) ) {
			$ligne['libelles'][ $lang ] = $saisis['fr'];
			$replis[]                   = $lang . ' (fr)';
		} elseif ( isset( $saisis['en'] ) ) {
			$ligne['libelles'][ $lang ] = $saisis['en'];
			$replis[]                   = $lang . ' (en)';
		} else {
			$ligne['libelles'][ $lang ] = $code;
			$replis[]                   = $lang . ' (code)';
		}
	}

	if ( array() !== $replis ) {
		ditl_import_message( $ligne, 'avertissement', sprintf( 'libellé(s) manquant(s), repli utilisé : %s.', implode( ', ', $replis ) ) );
	}

	$ligne['titre'] = isset( $ligne['libelles']['fr'] ) ? $ligne['libelles']['fr'] : (string) reset( $ligne['libelles'] );

	return $ligne;
}

/**
 * Code de referentiel present en base (toutes langues confondues).
 *
 * @param string $taxonomie Taxonomie.
 * @param string $code      Code.
 * @return array|null Tableau { term_id, parent } du premier terme, ou null.
 */
function ditl_import_code_en_base( $taxonomie, $code ) {
	global $wpdb;

	$ligne = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- controle ponctuel.
		$wpdb->prepare(
			"SELECT tt.term_id, tt.parent FROM {$wpdb->term_taxonomy} tt
			INNER JOIN {$wpdb->termmeta} tm ON tm.term_id = tt.term_id AND tm.meta_key = '_ditl_code'
			WHERE tt.taxonomy = %s AND tm.meta_value = %s
			ORDER BY tt.term_id ASC LIMIT 1",
			$taxonomie,
			$code
		)
	);

	return $ligne ? array(
		'term_id' => (int) $ligne->term_id,
		'parent'  => (int) $ligne->parent,
	) : null;
}

/**
 * Controles du fichier des referentiels : doublons de code dans une
 * taxonomie, parents (fichier ou base), codes en base absents du fichier.
 *
 * @param array $plan Plan (par reference).
 */
function ditl_import_controler_referentiels( &$plan ) {
	global $wpdb;

	$par_code = array();

	foreach ( $plan['lignes'] as $index => $ligne ) {
		if ( '' !== $ligne['taxonomie'] && '' !== $ligne['code'] ) {
			$par_code[ $ligne['taxonomie'] . '|' . $ligne['code'] ][] = $index;
		}
	}

	foreach ( $par_code as $cle => $indices ) {
		if ( count( $indices ) < 2 ) {
			continue;
		}

		list( $taxonomie, $code ) = explode( '|', $cle );
		$numeros                  = ditl_import_numeros_lignes( $plan, $indices );

		foreach ( $indices as $index ) {
			ditl_import_message( $plan['lignes'][ $index ], 'erreur', sprintf( 'code « %1$s » en double dans la taxonomie %2$s (lignes %3$s) : un code est unique dans toute sa liste, niveaux compris.', $code, $taxonomie, $numeros ) );
		}
	}

	foreach ( $plan['lignes'] as $index => $ligne ) {
		if ( $ligne['erreur'] || '' === $ligne['parent'] ) {
			continue;
		}

		$cle = $ligne['taxonomie'] . '|' . $ligne['parent'];

		if ( isset( $par_code[ $cle ] ) ) {
			$parent = $plan['lignes'][ $par_code[ $cle ][0] ];

			if ( $parent['erreur'] ) {
				ditl_import_message( $plan['lignes'][ $index ], 'erreur', sprintf( 'parent « %s » en erreur dans le fichier.', $ligne['parent'] ) );
			} elseif ( '' !== $parent['parent'] ) {
				ditl_import_message( $plan['lignes'][ $index ], 'erreur', sprintf( 'parent « %s » lui-même rattaché à un parent (2 niveaux maximum).', $ligne['parent'] ) );
			}
			continue;
		}

		$en_base = ditl_import_code_en_base( $ligne['taxonomie'], $ligne['parent'] );

		if ( null === $en_base ) {
			ditl_import_message( $plan['lignes'][ $index ], 'erreur', sprintf( 'parent « %s » introuvable (ni dans le fichier, ni en base).', $ligne['parent'] ) );
		} elseif ( 0 !== $en_base['parent'] ) {
			ditl_import_message( $plan['lignes'][ $index ], 'erreur', sprintf( 'parent « %s » lui-même rattaché à un parent (2 niveaux maximum).', $ligne['parent'] ) );
		}
	}

	// Codes en base absents du fichier : jamais supprimes (information).
	$taxonomies = array_values( array_unique( array_filter( wp_list_pluck( $plan['lignes'], 'taxonomie' ) ) ) );

	if ( array() === $taxonomies ) {
		return;
	}

	$marques = implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) );
	$lignes  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- une requete par preparation.
		$wpdb->prepare(
			"SELECT DISTINCT tt.taxonomy, tm.meta_value AS code FROM {$wpdb->term_taxonomy} tt
			INNER JOIN {$wpdb->termmeta} tm ON tm.term_id = tt.term_id AND tm.meta_key = '_ditl_code'
			WHERE tt.taxonomy IN ($marques)", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- marques %s generees.
			$taxonomies
		)
	);

	$absents = array();

	foreach ( (array) $lignes as $ligne ) {
		if ( ! isset( $par_code[ $ligne->taxonomy . '|' . $ligne->code ] ) ) {
			$absents[] = $ligne->taxonomy . ' / ' . $ligne->code;
		}
	}

	if ( array() !== $absents ) {
		sort( $absents );
		$plan['messages'][] = array(
			'niveau' => 'info',
			'texte'  => sprintf( '%1$d code(s) présent(s) en base et absent(s) du fichier, conservé(s) (jamais supprimés par l\'import) : %2$s.', count( $absents ), implode( ', ', array_slice( $absents, 0, 50 ) ) . ( count( $absents ) > 50 ? '...' : '' ) ),
		);
	}
}
