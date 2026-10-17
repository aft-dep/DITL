<?php
/**
 * Mise en place de l'integration e-learning (phase 5 du devis, option
 * iframe), a jouer sur chaque environnement.
 *
 * Deux etapes, idempotentes :
 *
 * 1. Dossier des modules (DITL_ELEARNING_DIR, par defaut wp-content/elearning,
 *    voir inc/elearning.php) : cree s'il manque, et son .htaccess recoit le
 *    bloc "# BEGIN DITL E-learning" :
 *    - aucun script serveur execute dans le dossier (PHP, CGI...) : il ne
 *      doit contenir que les exports HTML statiques d'eXeLearning ;
 *    - pas de liste des fichiers ;
 *    - modules affichables en iframe par le site lui-meme uniquement
 *      (X-Frame-Options SAMEORIGIN + CSP frame-ancestors 'self', comme les
 *      pages WordPress) et X-Content-Type-Options nosniff. Consequence : un
 *      autre site (plateforme d'un partenaire, par exemple) ne peut pas
 *      integrer les modules ; retirer ces deux lignes si cela devient un
 *      besoin ;
 *    - en-tete X-Robots-Tag "noindex, indexifembedded" sur les pages HTML :
 *      les modules ne sortent pas seuls dans les resultats des moteurs
 *      (pages sans navigation du site), mais leur contenu reste indexable a
 *      travers la page du site qui les integre ;
 *    - cache navigateur de 24 h sur les ressources des modules (au lieu des
 *      120 jours du bloc WP Fastest Cache) : un export corrige puis redepose
 *      sous les memes noms de fichiers est vu par tous sous 24 h. La page
 *      index.html n'est jamais mise en cache (regle de WP Fastest Cache sur
 *      les fichiers index.html, inchangee).
 *    Les exports eux-memes ne sont ni crees ni modifies ni supprimes par ce
 *    script : ils se deposent a la main (sFTP), un sous-dossier par export
 *    (ex. wp-content/elearning/module-2-fr/index.html).
 *    CONTROLE DES EXPORTS (lecture seule, avertissements) : liens
 *    symboliques, fichiers caches (.htaccess, .user.ini...), extensions hors
 *    liste des fichiers statiques attendus, iframes et scripts externes dans
 *    index.html.
 *
 * MODELE DE CONFIANCE : les modules sont servis par le site lui-meme (meme
 * origine, condition de la hauteur automatique) : le JavaScript d'un export
 * s'execute avec les droits du site, y compris ceux d'un administrateur
 * connecte qui consulte la page. DEPOSER UN EXPORT EQUIVAUT DONC A DONNER
 * DES DROITS D'ADMINISTRATEUR : n'accepter que des exports produits par
 * l'AFT avec eXeLearning, jamais retouches a la main, et lire les
 * avertissements du controle ci-dessus avant toute mise en ligne.
 *
 * 2. Gabarit "E-learning" (page-templates/elearning.php) applique aux pages
 *    "Parcours e-learning" des 5 langues (livrable 3). Le gabarit reprend le
 *    modele de page d'Astra : tant qu'aucun module n'est choisi dans la
 *    metabox, la page rend exactement comme avant (preuve au pixel du
 *    17/10/2026). Chaque page est identifiee par son ID ET son slug : une
 *    page dont le slug ne correspond pas est ignoree avec un avertissement.
 *    Une page qui porte deja un autre modele que le modele par defaut n'est
 *    pas touchee (choix manuel a respecter). Le modele d'origine est
 *    sauvegarde dans la meta _ditl_elearning_template_avant (mode annuler).
 *
 * Le choix des modules de chaque page se fait ensuite dans l'administration
 * (metabox "Modules e-learning" de la page), une fois les exports deposes.
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod) : un rejeu
 * sans changement n'ecrit rien (bilan des ecritures en fin de passage).
 *
 * ============================ RUNBOOK PREPROD / PROD ============================
 * 1. Deployer le code du theme.
 * 2. Simulation :   wp eval-file wp-content/themes/ditl/cli/configurer-elearning.php dry-run
 * 3. Application :  wp eval-file wp-content/themes/ditl/cli/configurer-elearning.php
 * 4. Deposer les exports eXeLearning par sFTP dans wp-content/elearning/
 *    (un sous-dossier par export, nom en minuscules, chiffres, tirets et
 *    soulignes ; deposer le CONTENU du dossier exporte, index.html a la
 *    racine du sous-dossier). Le dossier n'est pas versionne (comme
 *    uploads/) : la chaine de deploiement ne doit jamais le supprimer.
 * 5. Dans l'administration, pour chaque page "Parcours e-learning" : choisir
 *    le ou les modules de sa langue dans la metabox "Modules e-learning",
 *    puis mettre a jour la page.
 * 6. Purger le cache de page (wp-content/cache/all), puis verifier :
 *    - la page de chaque langue -> 200, module affiche, iframe a la hauteur
 *      du module (une seule barre de defilement) ;
 *    - curl -I https://<hote>/wp-content/elearning/<dossier>/index.html ->
 *      200 avec X-Robots-Tag: noindex, indexifembedded ;
 *    - curl -I https://<hote>/wp-content/elearning/ -> 403 (pas de liste) ;
 *    - un fichier .php puis un fichier .php.jpg deposes pour test dans le
 *      dossier -> 403 (puis les supprimer) ;
 *    - aucun avertissement du controle des exports (sinon, faire corriger
 *      l'export par l'AFT avant de choisir le module).
 * 7. Si Apache refuse le .htaccess du dossier (erreur 500 sur les modules :
 *    directive non autorisee par l'hebergeur), relancer en mode annuler,
 *    signaler le cas et adapter le bloc.
 * ===============================================================================
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/configurer-elearning.php dry-run
 *   wp eval-file wp-content/themes/ditl/cli/configurer-elearning.php
 *   wp eval-file wp-content/themes/ditl/cli/configurer-elearning.php annuler
 *
 * Mode annuler : restaure le modele d'origine des pages modifiees par ce
 * script (une page dont le modele a ete change a la main depuis garde ce
 * choix) et retire le bloc du .htaccess du dossier des modules (le fichier
 * est supprime s'il ne contient plus rien). Le dossier des modules, les
 * exports et la meta des modules choisis (_ditl_elearning_modules) sont
 * conserves.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Usage : wp eval-file <script> [dry-run] [annuler] - voir le docblock.
	// En acces web direct, reponse muette pour ne rien reveler du fichier.
	http_response_code( 404 );
	exit( 1 );
}

// Bibliotheque commune des scripts CLI du theme.
require_once __DIR__ . '/commun.php';

if ( ! defined( 'DITL_TPL_ELEARNING' ) || ! function_exists( 'ditl_elearning_dossier_valide' ) ) {
	WP_CLI::error( 'Theme DiTL inactif ou sans le module e-learning (inc/elearning.php) : script arrete.' );
}

// ---------------------------------------------------------------------------
// Lecture des arguments.
// ---------------------------------------------------------------------------

$ditl_modes   = ditl_cli_lire_modes( $args );
$ditl_dry_run = $ditl_modes['dry_run'];
$ditl_annuler = $ditl_modes['annuler'];

if ( $ditl_dry_run ) {
	WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucune ecriture (base, fichiers) ===' );
}

if ( $ditl_annuler ) {
	WP_CLI::log( '=== MODE ANNULER : retour a l\'etat d\'avant le script ===' );
}

// Compteur d'ecritures effectives (idempotence : rejeu = zero ecriture).
$ditl_ecritures = 0;

// Pages "Parcours e-learning" (livrable 3) : ID => slug attendu. Les IDs
// sont ceux de la base de production (preprod et prod en sont issues).
$ditl_pages_elearning = array(
	1856 => 'livrable-3',
	4137 => 'deliverable-3',
	4139 => 'produkt-3',
	4141 => 'entregable-3',
	4143 => 'entregavel-3',
);

// ---------------------------------------------------------------------------
// Outils locaux.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'ditl_elearning_cli_bloc_htaccess' ) ) {
	/**
	 * Contenu du bloc .htaccess du dossier des modules.
	 *
	 * Les deux formes de directive d'acces (Apache 2.4 mod_authz_core et
	 * 2.2) sont posees, comme dans les blocs AIOS et DITL Acces du site.
	 *
	 * @return string Bloc complet, marqueurs inclus, termine par un saut de ligne.
	 */
	function ditl_elearning_cli_bloc_htaccess() {
		$lignes = array(
			'# BEGIN DITL E-learning',
			'# Modules e-learning (exports HTML statiques eXeLearning). Bloc gere par',
			'# le script WP-CLI wp-content/themes/ditl/cli/configurer-elearning.php',
			'# - ne pas editer a la main.',
			'Options -Indexes',
			'# Aucun script serveur n\'est execute dans ce dossier (y compris en double',
			'# extension pour la famille PHP : x.php.jpg).',
			'<FilesMatch "(?i)\.(php[0-9]*|phtml|phar|pht|phps)(\.|$)|\.(cgi|pl|py|sh|shtml|asp|aspx|jsp)$">',
			'<IfModule mod_authz_core.c>',
			'Require all denied',
			'</IfModule>',
			'<IfModule !mod_authz_core.c>',
			'Order deny,allow',
			'Deny from all',
			'</IfModule>',
			'</FilesMatch>',
			'<IfModule mod_headers.c>',
			'# Modules affichables en iframe par le site lui-meme uniquement (comme les',
			'# pages WordPress), types de fichiers non devines par le navigateur.',
			'Header set X-Frame-Options "SAMEORIGIN"',
			'Header set Content-Security-Policy "frame-ancestors \'self\'"',
			'Header set X-Content-Type-Options "nosniff"',
			'# Modules indexables uniquement a travers la page du site qui les integre.',
			'<FilesMatch "(?i)\.html?$">',
			'Header set X-Robots-Tag "noindex, indexifembedded"',
			'</FilesMatch>',
			'# Cache court des ressources : un export redepose est vu sous 24 h.',
			'<FilesMatch "(?i)\.(css|js|json|png|jpe?g|gif|svg|webp|avif|ico|woff2?|ttf|otf|eot|mp3|mp4|webm|ogg|vtt|pdf)$">',
			'Header set Cache-Control "public, max-age=86400"',
			'Header unset Expires',
			'</FilesMatch>',
			'</IfModule>',
			'# END DITL E-learning',
		);

		return implode( "\n", $lignes ) . "\n";
	}
}

if ( ! function_exists( 'ditl_elearning_cli_htaccess' ) ) {
	/**
	 * Pose, met a jour ou retire le bloc DITL E-learning du .htaccess du
	 * dossier des modules.
	 *
	 * Mise a jour par str_replace du bloc capture : jamais de preg_replace
	 * avec du contenu en chaine de remplacement (lecon du 20/08). Tout
	 * contenu du fichier hors du bloc est conserve.
	 *
	 * @param string $dossier Dossier des modules (existant).
	 * @param bool   $annuler True pour retirer le bloc.
	 * @param bool   $dry_run True pour simuler sans ecrire.
	 * @return int Nombre d'ecritures effectuees (0 ou 1).
	 */
	function ditl_elearning_cli_htaccess( $dossier, $annuler, $dry_run ) {
		$fichier = $dossier . '/.htaccess';
		$contenu = file_exists( $fichier ) ? file_get_contents( $fichier ) : '';

		if ( false === $contenu ) {
			WP_CLI::warning( sprintf( '.htaccess illisible (%s) : bloc non touche.', $fichier ) );
			return 0;
		}

		if ( substr_count( $contenu, '# BEGIN DITL E-learning' ) !== substr_count( $contenu, '# END DITL E-learning' ) ) {
			WP_CLI::warning( 'Marqueurs "DITL E-learning" desequilibres (edition manuelle ?) : bloc non touche, a reparer a la main.' );
			return 0;
		}

		preg_match( '/# BEGIN DITL E-learning.*?# END DITL E-learning\n?/s', $contenu, $existant );
		$present = ! empty( $existant );
		$bloc    = ditl_elearning_cli_bloc_htaccess();

		if ( $annuler ) {
			if ( ! $present ) {
				WP_CLI::log( '  Bloc absent, rien a retirer.' );
				return 0;
			}

			$nouveau = str_replace( $existant[0], '', $contenu );
		} else {
			if ( $present && trim( $existant[0] ) === trim( $bloc ) ) {
				WP_CLI::log( '  Bloc deja en place et a jour, rien a faire.' );
				return 0;
			}

			$nouveau = $present ? str_replace( rtrim( $existant[0], "\n" ), rtrim( $bloc, "\n" ), $contenu ) : ( '' === trim( $contenu ) ? $bloc : rtrim( $contenu, "\n" ) . "\n\n" . $bloc );
		}

		if ( $dry_run ) {
			WP_CLI::log( $annuler ? '  [dry-run] le bloc serait retire.' : ( $present ? '  [dry-run] le bloc serait mis a jour.' : '  [dry-run] le bloc serait pose.' ) );
			return 0;
		}

		// Fichier vide apres retrait du bloc : supprime (il n'existait pas
		// avant le script, ou ne contenait que ce bloc).
		if ( $annuler && '' === trim( $nouveau ) ) {
			if ( ! @unlink( $fichier ) ) {
				WP_CLI::warning( sprintf( 'Suppression de %s impossible.', $fichier ) );
				return 0;
			}

			WP_CLI::log( '  Bloc retire, .htaccess du dossier supprime (vide).' );
			return 1;
		}

		if ( false === @file_put_contents( $fichier, $nouveau ) ) {
			WP_CLI::warning( sprintf( 'Ecriture de %s impossible (droits ?) : bloc non pose.', $fichier ) );
			return 0;
		}

		WP_CLI::log( $annuler ? '  Bloc retire.' : ( $present ? '  Bloc mis a jour.' : '  Bloc pose.' ) );
		return 1;
	}
}

if ( ! function_exists( 'ditl_elearning_cli_lisible' ) ) {
	/**
	 * Neutralise les caracteres de controle d'une chaine issue d'un export
	 * avant affichage dans le terminal (sequences d'echappement).
	 *
	 * @param string $texte Texte a afficher.
	 * @return string Texte sans caractere de controle.
	 */
	function ditl_elearning_cli_lisible( $texte ) {
		return (string) preg_replace( '/[\x00-\x1F\x7F]/', '?', (string) $texte );
	}
}

if ( ! function_exists( 'ditl_elearning_cli_controler_export' ) ) {
	/**
	 * Controle en lecture seule d'un export depose (voir le modele de
	 * confiance dans le docblock du script).
	 *
	 * Signale : liens symboliques (un lien vers wp-config.php exposerait les
	 * identifiants de la base), fichiers ou dossiers caches (.htaccess,
	 * .user.ini : changeraient la configuration du serveur), extensions hors
	 * liste des fichiers statiques d'un export eXeLearning, iframes et scripts
	 * externes dans index.html (contenus tiers charges sans consentement).
	 * Rien n'est modifie ni supprime.
	 *
	 * @param string $dossier Chemin absolu de l'export.
	 * @return string[] Alertes (vide si rien a signaler).
	 */
	function ditl_elearning_cli_controler_export( $dossier ) {
		$alertes    = array();
		$autorisees = array( 'html', 'htm', 'css', 'js', 'json', 'xml', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'mp3', 'mp4', 'webm', 'ogg', 'oga', 'ogv', 'wav', 'vtt', 'srt', 'pdf', 'map' );
		$hors_liste = array();

		$iterateur = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dossier, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterateur as $element ) {
			$relatif = substr( $element->getPathname(), strlen( $dossier ) + 1 );

			if ( $element->isLink() ) {
				$alertes[] = sprintf( 'lien symbolique %s (a supprimer)', $relatif );
				continue;
			}

			if ( '.' === substr( $element->getFilename(), 0, 1 ) ) {
				if ( '.DS_Store' !== $element->getFilename() ) {
					$alertes[] = sprintf( 'fichier ou dossier cache %s (a supprimer)', $relatif );
				}
				continue;
			}

			if ( $element->isFile() && ! in_array( strtolower( $element->getExtension() ), $autorisees, true ) ) {
				$hors_liste[] = $relatif;
			}
		}

		if ( array() !== $hors_liste ) {
			$alertes[] = sprintf( '%d fichier(s) hors des types statiques attendus : %s', count( $hors_liste ), implode( ', ', array_slice( $hors_liste, 0, 10 ) ) );
		}

		$index = is_readable( $dossier . '/index.html' ) ? file_get_contents( $dossier . '/index.html' ) : false;

		if ( is_string( $index ) && preg_match_all( '/<(iframe|script|embed|object)\b[^>]*\s(?:src|data)=["\']((?:https?:)?\/\/[^"\']+)/i', $index, $externes, PREG_SET_ORDER ) ) {
			foreach ( $externes as $externe ) {
				$alertes[] = sprintf( 'contenu externe <%s> %s (charge sans consentement, a remplacer par un lien si possible)', strtolower( $externe[1] ), $externe[2] );
			}
		}

		return $alertes;
	}
}

// ---------------------------------------------------------------------------
// Etape 1 : dossier des modules et son .htaccess.
// ---------------------------------------------------------------------------

WP_CLI::log( sprintf( '--- Dossier des modules (%s) ---', DITL_ELEARNING_DIR ) );

if ( ! is_dir( DITL_ELEARNING_DIR ) ) {
	if ( $ditl_annuler ) {
		WP_CLI::log( '  Dossier absent, rien a annuler.' );
	} elseif ( $ditl_dry_run ) {
		WP_CLI::log( '  [dry-run] le dossier serait cree, puis son .htaccess pose.' );
	} elseif ( ! wp_mkdir_p( DITL_ELEARNING_DIR ) ) {
		WP_CLI::warning( 'Creation du dossier impossible (droits ?) : etape ignoree.' );
	} else {
		WP_CLI::log( '  Dossier cree.' );
		++$ditl_ecritures;
	}
}

if ( is_dir( DITL_ELEARNING_DIR ) ) {
	$ditl_ecritures += ditl_elearning_cli_htaccess( DITL_ELEARNING_DIR, $ditl_annuler, $ditl_dry_run );

	// Etat des exports deposes (information, aucune ecriture).
	$ditl_disponibles = ditl_elearning_modules_disponibles();

	foreach ( $ditl_disponibles['modules'] as $ditl_dossier => $ditl_infos ) {
		WP_CLI::log( sprintf( '  Module present : %s (titre "%s", langue %s)', $ditl_dossier, ditl_elearning_cli_lisible( $ditl_infos['titre'] ), '' !== $ditl_infos['langue'] ? $ditl_infos['langue'] : '?' ) );

		foreach ( ditl_elearning_cli_controler_export( DITL_ELEARNING_DIR . '/' . $ditl_dossier ) as $ditl_alerte ) {
			WP_CLI::warning( sprintf( 'Export %s : %s', $ditl_dossier, ditl_elearning_cli_lisible( $ditl_alerte ) ) );
		}
	}

	foreach ( $ditl_disponibles['ignores'] as $ditl_dossier ) {
		WP_CLI::warning( sprintf( 'Dossier ignore (nom non conforme : minuscules, chiffres, tirets et soulignes) : %s', ditl_elearning_cli_lisible( $ditl_dossier ) ) );
	}
}

// ---------------------------------------------------------------------------
// Etape 2 : gabarit "E-learning" sur les pages "Parcours e-learning".
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Gabarit E-learning des pages "Parcours e-learning" ---' );

foreach ( $ditl_pages_elearning as $ditl_page_id => $ditl_slug ) {
	$ditl_page = get_post( $ditl_page_id );

	if ( ! $ditl_page || 'page' !== $ditl_page->post_type || $ditl_slug !== $ditl_page->post_name ) {
		WP_CLI::warning( sprintf( 'Page %d introuvable ou de slug different de "%s" : ignoree.', $ditl_page_id, $ditl_slug ) );
		continue;
	}

	$ditl_actuel = (string) get_post_meta( $ditl_page_id, '_wp_page_template', true );
	$ditl_avant  = get_post_meta( $ditl_page_id, '_ditl_elearning_template_avant', true );

	if ( $ditl_annuler ) {
		if ( '' === $ditl_avant ) {
			WP_CLI::log( sprintf( '  Page %d (%s) : non modifiee par ce script, rien a annuler.', $ditl_page_id, $ditl_slug ) );
			continue;
		}

		// Modele change a la main depuis le passage du script : il est
		// respecte, seule la sauvegarde devenue sans objet est retiree.
		if ( DITL_TPL_ELEARNING !== $ditl_actuel ) {
			if ( $ditl_dry_run ) {
				WP_CLI::log( sprintf( '  [dry-run] page %d (%s) : modele "%s" choisi a la main depuis, conserve ; sauvegarde retiree.', $ditl_page_id, $ditl_slug, $ditl_actuel ) );
				continue;
			}

			delete_post_meta( $ditl_page_id, '_ditl_elearning_template_avant' );
			WP_CLI::log( sprintf( '  Page %d (%s) : modele "%s" choisi a la main depuis, conserve ; sauvegarde retiree.', $ditl_page_id, $ditl_slug, $ditl_actuel ) );
			++$ditl_ecritures;
			continue;
		}

		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] page %d (%s) : modele "%s" restaure.', $ditl_page_id, $ditl_slug, $ditl_avant ) );
			continue;
		}

		update_post_meta( $ditl_page_id, '_wp_page_template', $ditl_avant );
		delete_post_meta( $ditl_page_id, '_ditl_elearning_template_avant' );
		WP_CLI::log( sprintf( '  Page %d (%s) : modele "%s" restaure.', $ditl_page_id, $ditl_slug, $ditl_avant ) );
		++$ditl_ecritures;
		continue;
	}

	if ( DITL_TPL_ELEARNING === $ditl_actuel ) {
		WP_CLI::log( sprintf( '  Page %d (%s) : gabarit deja en place.', $ditl_page_id, $ditl_slug ) );
		continue;
	}

	if ( '' !== $ditl_actuel && 'default' !== $ditl_actuel ) {
		WP_CLI::warning( sprintf( 'Page %d (%s) : modele "%s" choisi a la main, page non touchee.', $ditl_page_id, $ditl_slug, $ditl_actuel ) );
		continue;
	}

	if ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] page %d (%s) : gabarit E-learning applique.', $ditl_page_id, $ditl_slug ) );
		continue;
	}

	update_post_meta( $ditl_page_id, '_ditl_elearning_template_avant', '' !== $ditl_actuel ? $ditl_actuel : 'default' );
	update_post_meta( $ditl_page_id, '_wp_page_template', DITL_TPL_ELEARNING );
	WP_CLI::log( sprintf( '  Page %d (%s) : gabarit E-learning applique.', $ditl_page_id, $ditl_slug ) );
	++$ditl_ecritures;
}

// ---------------------------------------------------------------------------
// Bilan.
// ---------------------------------------------------------------------------

if ( $ditl_dry_run ) {
	WP_CLI::success( 'Simulation terminee : aucune ecriture.' );
} else {
	WP_CLI::success( sprintf( 'Termine : %d ecriture(s). Purger le cache de page (wp-content/cache/all).', $ditl_ecritures ) );
}
