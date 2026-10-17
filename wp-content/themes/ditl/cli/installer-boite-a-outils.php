<?php
/**
 * Pages de la boite a outils (phase 3).
 *
 * CONSTAT : le moteur de la boite a outils (inc/boite/) s'affiche sur une
 * page portant le modele DITL_TPL_BOITE (page-templates/boite-a-outils.php),
 * une par langue. Ces pages sont des DONNEES : elles n'existent pas dans le
 * depot et doivent etre creees sur chaque environnement.
 *
 * CE QUE FAIT LE SCRIPT (operation de donnees, aucun fichier modifie) :
 * 1. cree, pour chaque langue demandee, une page vide (les textes par defaut
 *    du questionnaire s'appliquent, voir inc/boite/commun.php) :
 *    - FR "Boîte à outils", slug boite-a-outils -> /fr/boite-a-outils/
 *    - EN "Toolbox", slug toolbox -> /toolbox/
 *    (PT / ES / DE : titres et slugs prepares ci-dessous mais A FAIRE
 *    VALIDER, crees seulement sur demande : langues=fr,en,pt,es,de) ;
 * 2. leur pose le modele de page DITL_TPL_BOITE, leur langue (adaptateur
 *    multilingue du theme) et les lie comme traductions ;
 * 3. enregistre leurs identifiants dans l'option ditl_boite_pages (tableau
 *    langue => ID).
 * Une page qui porte deja le modele dans une langue est reprise telle
 * quelle (aucun doublon) ; son titre, son slug et son contenu ne sont
 * jamais modifies (ils sont editables par le client).
 *
 * STATUT (PROVISOIRE, decision client attendue sur la mise en ligne) :
 * - par defaut, une page creee est PRIVEE (visible des seuls comptes
 *   connectes habilites) ; une page existante garde son statut (une page
 *   en brouillon, en attente ou sortie de la corbeille passe en privee) ;
 * - argument "publier" : pages publiees ;
 * - argument "depublier" : pages repassees en privees.
 * AUCUNE MISE EN MENU : l'emplacement de la boite a outils dans la
 * navigation est une decision client (les pages Livrable "Boite a outils
 * interactive en ligne" / "Interactive solutions toolbox" existent deja et
 * pourraient servir de porte d'entree).
 *
 * GARDE-FOUS :
 * - AJOUT AUTOMATIQUE AUX MENUS NEUTRALISE : le menu principal FR est en
 *   "ajout automatique des nouvelles pages" (option nav_menu_options,
 *   auto_add = [55]) ; sans precaution, la page FR publiee entrerait dans
 *   l'en-tete du site. Le hook du coeur _wp_auto_add_pages_to_menu est
 *   retire pendant l'execution, et un controle final signale tout element
 *   de menu qui viserait ces pages.
 * - COLLISION DE SLUG : si une autre page (ou un media) porte deja le slug
 *   vise, corbeille comprise, le script s'arrete AVANT toute ecriture.
 * - MODELE DE PAGE : pose directement en meta (_wp_page_template), lue par
 *   le chargement des gabarits (page-templates/boite-a-outils.php). Le
 *   coeur refuse un modele dont le fichier n'existe pas (wp_insert_post le
 *   remettrait a "default") : le gabarit doit etre deploye AVANT de jouer
 *   ce script.
 *
 * Script idempotent : un rejeu sans changement n'ecrit rien.
 *
 * MODE ANNULER : met les pages A LA CORBEILLE (jamais de suppression
 * definitive). L'option ditl_boite_pages est CONSERVEE volontairement : un
 * rejeu du mode normal sort les memes pages de la corbeille au lieu d'en
 * creer des doublons.
 *
 * ============================ RUNBOOK PREPROD / PROD ============================
 * 1. Deployer le code du theme (inc/boite/ charge par functions.php).
 * 2. Simulation :   wp eval-file wp-content/themes/ditl/cli/installer-boite-a-outils.php dry-run
 *    Verifier : "AUCUNE COLLISION", langues presentes, aucune page en double.
 * 3. Application :  wp eval-file wp-content/themes/ditl/cli/installer-boite-a-outils.php
 *    (pages PRIVEES : recette par un compte connecte) ;
 *    mise en ligne, sur decision du client :
 *                   wp eval-file wp-content/themes/ditl/cli/installer-boite-a-outils.php publier
 * 4. Purger le cache de page (wp-content/cache/all).
 * 5. Recette : /fr/boite-a-outils/ et /toolbox/ (200 une fois publiees,
 *    404 deconnecte tant qu'elles sont privees) ; liens de langue entre
 *    les deux ; en-tete INCHANGE (aucune entree ajoutee au menu) ; la page
 *    sans parametre est indexable, /fr/boite-a-outils/?metier=x porte
 *    "noindex, follow" et le canonical de la page.
 * 6. Un rejeu doit afficher "0 ecriture(s)".
 * 7. Retour arriere :
 *    wp eval-file wp-content/themes/ditl/cli/installer-boite-a-outils.php annuler
 * ===============================================================================
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/installer-boite-a-outils.php dry-run
 *   wp eval-file wp-content/themes/ditl/cli/installer-boite-a-outils.php
 *   wp eval-file wp-content/themes/ditl/cli/installer-boite-a-outils.php publier
 *   wp eval-file wp-content/themes/ditl/cli/installer-boite-a-outils.php depublier
 *   wp eval-file wp-content/themes/ditl/cli/installer-boite-a-outils.php langues=fr,en,pt
 *   wp eval-file wp-content/themes/ditl/cli/installer-boite-a-outils.php annuler
 *
 * Le mode simulation accepte "dry-run" ou "--dry-run", l'annulation
 * "annuler" ou "--annuler" (combinables). "langues=" restreint ou etend les
 * langues traitees (fr,en par defaut) ; en mode annuler, toutes les pages
 * connues de l'option et des langues demandees partent a la corbeille.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Usage : wp eval-file <script> [dry-run] [annuler] [publier] [depublier] [langues=fr,en] - voir le docblock.
	// En acces web direct, reponse muette pour ne rien reveler du fichier.
	http_response_code( 404 );
	exit( 1 );
}

// Bibliotheque commune des scripts CLI du theme.
require_once __DIR__ . '/commun.php';

// ---------------------------------------------------------------------------
// Reperes fixes du chantier.
// ---------------------------------------------------------------------------

// Option : langue => ID de la page Boite a outils.
$ditl_option_pages = 'ditl_boite_pages';

// Pages par langue. PT, ES et DE : PROVISOIRE, titres et slugs a faire
// valider par le client avant toute creation.
$ditl_definitions = array(
	'fr' => array(
		'titre' => 'Boîte à outils',
		'slug'  => 'boite-a-outils',
	),
	'en' => array(
		'titre' => 'Toolbox',
		'slug'  => 'toolbox',
	),
	'pt' => array(
		'titre' => 'Caixa de ferramentas',
		'slug'  => 'caixa-de-ferramentas',
	),
	'es' => array(
		'titre' => 'Caja de herramientas',
		'slug'  => 'caja-de-herramientas',
	),
	'de' => array(
		'titre' => 'Werkzeugkasten',
		'slug'  => 'werkzeugkasten',
	),
);

// ---------------------------------------------------------------------------
// Lecture des arguments.
// ---------------------------------------------------------------------------

// Les arguments propres au script sont retires avant la lecture commune des
// modes, qui signale tout argument qu'elle ne connait pas.
$ditl_args_modes = array();
$ditl_publier    = false;
$ditl_depublier  = false;
$ditl_langues    = array( 'fr', 'en' );

foreach ( (array) $args as $ditl_arg ) {
	if ( 'publier' === $ditl_arg || '--publier' === $ditl_arg ) {
		$ditl_publier = true;
		continue;
	}

	if ( 'depublier' === $ditl_arg || '--depublier' === $ditl_arg ) {
		$ditl_depublier = true;
		continue;
	}

	if ( 0 === strpos( $ditl_arg, 'langues=' ) || 0 === strpos( $ditl_arg, '--langues=' ) ) {
		$ditl_valeur  = substr( $ditl_arg, strpos( $ditl_arg, '=' ) + 1 );
		$ditl_langues = array_values( array_unique( array_filter( array_map( 'trim', explode( ',', strtolower( $ditl_valeur ) ) ) ) ) );
		continue;
	}

	$ditl_args_modes[] = $ditl_arg;
}

$ditl_modes   = ditl_cli_lire_modes( $ditl_args_modes );
$ditl_dry_run = $ditl_modes['dry_run'];
$ditl_annuler = $ditl_modes['annuler'];

if ( $ditl_dry_run ) {
	WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucune ecriture en base ===' );
}

if ( $ditl_publier && $ditl_depublier ) {
	WP_CLI::error( 'Arguments "publier" et "depublier" incompatibles. Aucune ecriture.' );
}

if ( empty( $ditl_langues ) ) {
	WP_CLI::error( 'Argument langues= vide. Aucune ecriture.' );
}

foreach ( $ditl_langues as $ditl_langue ) {
	if ( ! isset( $ditl_definitions[ $ditl_langue ] ) ) {
		WP_CLI::error( sprintf( 'Langue "%s" sans titre ni slug definis dans ce script (%s). Aucune ecriture.', $ditl_langue, implode( ', ', array_keys( $ditl_definitions ) ) ) );
	}
}

// Compteur d'ecritures effectives (idempotence : rejeu = zero ecriture).
$ditl_ecritures = 0;

// ---------------------------------------------------------------------------
// Dependances : boite a outils du theme et extension multilingue.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'ditl_boite_charge' ) || ! defined( 'DITL_TPL_BOITE' ) || ! function_exists( 'ditl_ml_definir_langue_post' ) ) {
	WP_CLI::error( 'Boite a outils du theme non chargee (inc/boite/boite.php) : deployer le theme et l\'activer avant de rejouer. Aucune ecriture.' );
}

if ( '' === ditl_ml_extension() ) {
	WP_CLI::error( 'Aucune extension multilingue active (Polylang ou WPML) : langues et traductions impossibles a poser. Aucune ecriture.' );
}

$ditl_langues_site = ditl_ml_langues();

foreach ( $ditl_langues as $ditl_langue ) {
	if ( ! ditl_ml_langue_valide( $ditl_langue ) ) {
		WP_CLI::error( sprintf( 'Langue "%s" absente du site (%s). Aucune ecriture.', $ditl_langue, implode( ', ', $ditl_langues_site ) ) );
	}
}

WP_CLI::log( sprintf( 'Extension multilingue : %s ; langues du site : %s ; langues traitees : %s.', ditl_ml_extension(), implode( ', ', $ditl_langues_site ), implode( ', ', $ditl_langues ) ) );

// ---------------------------------------------------------------------------
// Outils locaux.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'ditl_boite_cli_trouver' ) ) {
	/**
	 * Retrouve la page Boite a outils d'une langue (option, puis modele).
	 *
	 * Corbeille comprise : sinon une annulation suivie d'un rejeu creerait
	 * un doublon si l'option avait ete effacee entre-temps.
	 *
	 * @param string $langue Langue.
	 * @param array  $option Valeur de l'option ditl_boite_pages.
	 * @return int ID de la page, 0 si aucune.
	 */
	function ditl_boite_cli_trouver( $langue, $option ) {
		$memo = isset( $option[ $langue ] ) && is_scalar( $option[ $langue ] ) ? absint( $option[ $langue ] ) : 0;

		if ( $memo > 0 && 'page' === get_post_type( $memo ) && DITL_TPL_BOITE === get_post_meta( $memo, '_wp_page_template', true ) && ditl_ml_langue_post( $memo ) === $langue ) {
			return $memo;
		}

		if ( $memo > 0 ) {
			WP_CLI::warning( sprintf( 'Option : la page %d (%s) n\'est plus une page Boite a outils de cette langue, recherche par modele de page.', $memo, $langue ) );
		}

		// Toutes langues (filtres de langue leves), puis tri par langue via
		// l'adaptateur : quelques pages au plus portent ce modele.
		$trouves = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'meta_key'         => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => DITL_TPL_BOITE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'posts_per_page'   => 100,
				'suppress_filters' => true,
				'lang'             => '',
			)
		);

		$de_la_langue = array();

		foreach ( $trouves as $id ) {
			if ( ditl_ml_langue_post( $id ) === $langue ) {
				$de_la_langue[] = (int) $id;
			}
		}

		if ( count( $de_la_langue ) > 1 ) {
			WP_CLI::warning( sprintf( 'Plusieurs pages Boite a outils %s en base (%s) : la plus ancienne est retenue.', $langue, implode( ', ', $de_la_langue ) ) );
		}

		return empty( $de_la_langue ) ? 0 : $de_la_langue[0];
	}
}

if ( ! function_exists( 'ditl_boite_cli_collisions' ) ) {
	/**
	 * Contenus qui occupent deja le slug vise.
	 *
	 * Pages de premier niveau et medias (le coeur controle l'unicite d'un
	 * slug de page contre les deux), tous statuts, corbeille comprise.
	 *
	 * @param string $slug    Slug vise.
	 * @param int    $exclure ID de la page Boite a outils (0 si aucune).
	 * @return array Lignes { ID, post_type, post_status, post_name }.
	 */
	function ditl_boite_cli_collisions( $slug, $exclure ) {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ID, post_type, post_status, post_name FROM {$wpdb->posts}
				WHERE post_name IN ( %s, %s ) AND post_type IN ( 'page', 'attachment' ) AND post_parent = 0 AND ID != %d",
				$slug,
				$slug . '__trashed',
				$exclure
			),
			ARRAY_A
		);
	}
}

if ( ! function_exists( 'ditl_boite_cli_menus_visant' ) ) {
	/**
	 * Elements de menu qui visent l'une des pages donnees.
	 *
	 * @param int[] $ids IDs de pages.
	 * @return array Lignes { item, menu_object_id }.
	 */
	function ditl_boite_cli_menus_visant( $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );

		if ( empty( $ids ) ) {
			return array();
		}

		$place = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.ID AS item, m.meta_value AS menu_object_id FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_menu_item_object_id'
				INNER JOIN {$wpdb->postmeta} o ON o.post_id = p.ID AND o.meta_key = '_menu_item_object' AND o.meta_value = 'page'
				WHERE p.post_type = 'nav_menu_item' AND m.meta_value IN ( $place )", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				$ids
			),
			ARRAY_A
		);
	}
}

// ---------------------------------------------------------------------------
// Etat actuel (aucune ecriture).
// ---------------------------------------------------------------------------

$ditl_option = get_option( $ditl_option_pages, array() );

if ( ! is_array( $ditl_option ) ) {
	$ditl_option = array();
}

$ditl_ids = array();

foreach ( $ditl_langues as $ditl_langue ) {
	$ditl_ids[ $ditl_langue ] = ditl_boite_cli_trouver( $ditl_langue, $ditl_option );
}

// ---------------------------------------------------------------------------
// MODE ANNULER : pages a la corbeille.
// ---------------------------------------------------------------------------

if ( $ditl_annuler ) {
	WP_CLI::log( '--- Annulation : pages Boite a outils a la corbeille ---' );

	$ditl_a_traiter = $ditl_ids;

	foreach ( $ditl_option as $ditl_langue => $ditl_id ) {
		$ditl_id = is_scalar( $ditl_id ) ? absint( $ditl_id ) : 0;

		// Seules les pages portant encore le modele de la boite a outils.
		if ( $ditl_id > 0 && ! in_array( $ditl_id, $ditl_a_traiter, true ) && 'page' === get_post_type( $ditl_id ) && DITL_TPL_BOITE === get_post_meta( $ditl_id, '_wp_page_template', true ) && empty( $ditl_a_traiter[ (string) $ditl_langue ] ) ) {
			$ditl_a_traiter[ (string) $ditl_langue ] = $ditl_id;
		}
	}

	foreach ( $ditl_a_traiter as $ditl_langue => $ditl_id ) {
		if ( $ditl_id <= 0 ) {
			WP_CLI::log( sprintf( '  %s : aucune page en base, rien a faire.', $ditl_langue ) );
		} elseif ( 'trash' === get_post_status( $ditl_id ) ) {
			WP_CLI::log( sprintf( '  %s : page %d deja en corbeille, rien a faire.', $ditl_langue, $ditl_id ) );
		} elseif ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] %s : page %d serait mise en corbeille.', $ditl_langue, $ditl_id ) );
		} elseif ( wp_trash_post( $ditl_id ) ) {
			WP_CLI::log( sprintf( '  %s : page %d mise en corbeille.', $ditl_langue, $ditl_id ) );
			$ditl_ecritures++;
		} else {
			WP_CLI::warning( sprintf( '%s : echec de la mise en corbeille de la page %d.', $ditl_langue, $ditl_id ) );
		}
	}

	WP_CLI::log( sprintf( '  Option %s conservee (rejeu = restauration des memes pages).', $ditl_option_pages ) );

	if ( $ditl_dry_run ) {
		WP_CLI::success( 'Simulation terminee : aucune ecriture.' );
		return;
	}

	WP_CLI::success( sprintf( 'Annulation terminee : %d ecriture(s). Purger le cache de page (wp-content/cache/all).', $ditl_ecritures ) );
	return;
}

// ---------------------------------------------------------------------------
// Controles prealables : collisions de slug (creations seulement).
// ---------------------------------------------------------------------------

$ditl_bloquant = false;

foreach ( $ditl_langues as $ditl_langue ) {
	if ( $ditl_ids[ $ditl_langue ] > 0 ) {
		continue;
	}

	foreach ( ditl_boite_cli_collisions( $ditl_definitions[ $ditl_langue ]['slug'], 0 ) as $ditl_c ) {
		WP_CLI::warning( sprintf( 'Collision %s : le slug "%s" est deja pris par %s %d (statut %s, slug %s).', $ditl_langue, $ditl_definitions[ $ditl_langue ]['slug'], $ditl_c['post_type'], $ditl_c['ID'], $ditl_c['post_status'], $ditl_c['post_name'] ) );
		$ditl_bloquant = true;
	}
}

if ( $ditl_bloquant ) {
	WP_CLI::error( 'Slug deja occupe : choisir un autre slug ou liberer celui-ci, puis rejouer. Aucune ecriture.' );
}

WP_CLI::log( 'Slugs : AUCUNE COLLISION (pages et medias, corbeille comprise).' );

// ---------------------------------------------------------------------------
// Creation ou mise en conformite des pages.
// ---------------------------------------------------------------------------

// Ajout automatique aux menus "auto_add" (menu principal FR) neutralise :
// la publication d'une page ne doit rien changer a l'en-tete.
remove_action( 'transition_post_status', '_wp_auto_add_pages_to_menu', 10 );

// Auteur : celui de la page d'accueil (0 si aucune page d'accueil statique).
$ditl_auteur = (int) get_post_field( 'post_author', (int) get_option( 'page_on_front' ) );

foreach ( $ditl_langues as $ditl_langue ) {
	$ditl_def = $ditl_definitions[ $ditl_langue ];
	$ditl_id  = $ditl_ids[ $ditl_langue ];

	WP_CLI::log( '' );
	WP_CLI::log( sprintf( '--- Page %s : "%s" (slug %s) ---', $ditl_langue, $ditl_def['titre'], $ditl_def['slug'] ) );

	// --- Creation -----------------------------------------------------------
	if ( $ditl_id <= 0 ) {
		$ditl_statut = $ditl_publier ? 'publish' : 'private';

		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] Page absente : serait creee (statut %s, contenu vide, commentaires fermes), puis modele, langue et traductions.', $ditl_statut ) );
			continue;
		}

		$ditl_resultat = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => $ditl_statut,
				'post_title'     => wp_slash( $ditl_def['titre'] ),
				'post_name'      => $ditl_def['slug'],
				'post_content'   => '',
				'post_author'    => $ditl_auteur,
				'post_parent'    => 0,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( is_wp_error( $ditl_resultat ) ) {
			WP_CLI::error( sprintf( 'Echec de la creation de la page %s : %s', $ditl_langue, $ditl_resultat->get_error_message() ) );
		}

		$ditl_id                  = (int) $ditl_resultat;
		$ditl_ids[ $ditl_langue ] = $ditl_id;
		$ditl_ecritures++;
		WP_CLI::log( sprintf( '  Page creee : ID %d (statut %s).', $ditl_id, $ditl_statut ) );
	} else {
		// --- Page existante ---------------------------------------------------
		WP_CLI::log( sprintf( '  Page existante : ID %d (statut %s) ; titre, slug et contenu laisses tels quels.', $ditl_id, get_post_status( $ditl_id ) ) );

		// Sortie de corbeille (le coeur restaure le slug et repasse la page
		// en brouillon ; le statut est regle juste apres).
		if ( 'trash' === get_post_status( $ditl_id ) ) {
			if ( $ditl_dry_run ) {
				WP_CLI::log( '  [dry-run] Serait sortie de la corbeille.' );
			} else {
				wp_untrash_post( $ditl_id );
				$ditl_ecritures++;
				WP_CLI::log( '  Sortie de la corbeille.' );
			}
		}

		$ditl_actuel = get_post_status( $ditl_id );

		if ( $ditl_publier ) {
			$ditl_cible = 'publish';
		} elseif ( $ditl_depublier ) {
			$ditl_cible = 'private';
		} elseif ( in_array( $ditl_actuel, array( 'publish', 'private' ), true ) ) {
			$ditl_cible = $ditl_actuel;
		} else {
			$ditl_cible = 'private';
		}

		if ( $ditl_dry_run && 'trash' === $ditl_actuel ) {
			WP_CLI::log( sprintf( '  [dry-run] Statut apres sortie de corbeille : %s.', $ditl_cible ) );
		} elseif ( $ditl_cible === $ditl_actuel ) {
			WP_CLI::log( sprintf( '  Statut %s : conforme.', $ditl_actuel ) );
		} elseif ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] Statut : %s -> %s.', $ditl_actuel, $ditl_cible ) );
		} else {
			// page_template vide : wp_update_post ne revalide pas le modele
			// stocke (s'il manquait, le coeur refuserait la mise a jour APRES
			// avoir ecrit la ligne en base, sans declencher les hooks
			// Polylang, Yoast et cache). La meta du modele n'est pas touchee.
			$ditl_resultat = wp_update_post(
				array(
					'ID'            => $ditl_id,
					'post_status'   => $ditl_cible,
					'page_template' => '',
				),
				true
			);

			if ( is_wp_error( $ditl_resultat ) ) {
				WP_CLI::error( sprintf( 'Echec du changement de statut de la page %d : %s', $ditl_id, $ditl_resultat->get_error_message() ) );
			}

			$ditl_ecritures++;
			WP_CLI::log( sprintf( '  Statut : %s -> %s.', $ditl_actuel, $ditl_cible ) );
		}
	}

	// --- Modele de page (meta directe : voir le docblock) ----------------
	$ditl_modele = (string) get_post_meta( $ditl_id, '_wp_page_template', true );

	if ( DITL_TPL_BOITE === $ditl_modele ) {
		WP_CLI::log( sprintf( '  Modele %s : conforme.', DITL_TPL_BOITE ) );
	} elseif ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] Modele "%s" -> "%s".', $ditl_modele, DITL_TPL_BOITE ) );
	} else {
		update_post_meta( $ditl_id, '_wp_page_template', DITL_TPL_BOITE );
		$ditl_ecritures++;
		WP_CLI::log( sprintf( '  Modele "%s" -> "%s".', $ditl_modele, DITL_TPL_BOITE ) );
	}

	// --- Langue -------------------------------------------------------------
	$ditl_langue_actuelle = ditl_ml_langue_post( $ditl_id );

	if ( $ditl_langue_actuelle === $ditl_langue ) {
		WP_CLI::log( sprintf( '  Langue %s : conforme.', $ditl_langue ) );
	} elseif ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] Langue : %s -> %s.', '' !== $ditl_langue_actuelle ? $ditl_langue_actuelle : '(aucune)', $ditl_langue ) );
	} else {
		ditl_ml_definir_langue_post( $ditl_id, $ditl_langue );
		$ditl_ecritures++;
		WP_CLI::log( sprintf( '  Langue : %s -> %s.', '' !== $ditl_langue_actuelle ? $ditl_langue_actuelle : '(aucune)', $ditl_langue ) );
	}

	// --- Verification apres ecriture ---------------------------------------
	if ( ! $ditl_dry_run ) {
		clean_post_cache( $ditl_id );

		if ( DITL_TPL_BOITE !== get_page_template_slug( $ditl_id ) ) {
			WP_CLI::warning( sprintf( 'Page %d : modele relu "%s" au lieu de "%s". A VERIFIER.', $ditl_id, get_page_template_slug( $ditl_id ), DITL_TPL_BOITE ) );
		}

		if ( ditl_ml_langue_post( $ditl_id ) !== $ditl_langue ) {
			WP_CLI::warning( sprintf( 'Page %d : langue relue "%s" au lieu de "%s". A VERIFIER.', $ditl_id, ditl_ml_langue_post( $ditl_id ), $ditl_langue ) );
		}

		if ( get_post_field( 'post_name', $ditl_id ) !== $ditl_def['slug'] ) {
			WP_CLI::warning( sprintf( 'Page %d : slug "%s" (attendu par defaut : "%s"), a verifier si la page vient d\'etre creee.', $ditl_id, get_post_field( 'post_name', $ditl_id ), $ditl_def['slug'] ) );
		}
	}
}

// ---------------------------------------------------------------------------
// Traductions, option, controle des menus.
// ---------------------------------------------------------------------------

WP_CLI::log( '' );

if ( in_array( 0, $ditl_ids, true ) ) {
	// Simulation sur un environnement ou une page reste a creer.
	WP_CLI::log( '[dry-run] Traductions et option seraient posees apres creation des pages.' );
	WP_CLI::success( 'Simulation terminee : aucune ecriture.' );
	return;
}

// Groupe de traductions cible : groupes existants des pages traitees (une
// traduction posee a la main dans une autre langue est conservee), puis
// pages de ce script dans leurs langues.
if ( count( $ditl_ids ) > 1 ) {
	$ditl_groupe = array();

	foreach ( $ditl_ids as $ditl_id ) {
		$ditl_groupe = array_merge( $ditl_groupe, ditl_ml_traductions_post( $ditl_id ) );
	}

	$ditl_groupe = array_merge( $ditl_groupe, $ditl_ids );
	ksort( $ditl_groupe );

	$ditl_conforme = true;

	foreach ( $ditl_ids as $ditl_id ) {
		$ditl_actuelles = ditl_ml_traductions_post( $ditl_id );
		ksort( $ditl_actuelles );

		if ( $ditl_actuelles !== $ditl_groupe ) {
			$ditl_conforme = false;
		}
	}

	if ( $ditl_conforme ) {
		WP_CLI::log( sprintf( 'Traductions : %s, conformes.', wp_json_encode( $ditl_groupe ) ) );
	} elseif ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '[dry-run] Traductions seraient liees : %s.', wp_json_encode( $ditl_groupe ) ) );
	} elseif ( ditl_ml_lier_traductions_posts( $ditl_groupe ) ) {
		$ditl_ecritures++;
		WP_CLI::log( sprintf( 'Traductions liees : %s.', wp_json_encode( $ditl_groupe ) ) );
	} else {
		WP_CLI::warning( sprintf( 'Liaison des traductions impossible : %s.', wp_json_encode( $ditl_groupe ) ) );
	}
} else {
	WP_CLI::log( 'Traductions : une seule langue traitee, rien a lier.' );
}

$ditl_option_cible = array_merge( $ditl_option, $ditl_ids );

if ( $ditl_option === $ditl_option_cible ) {
	WP_CLI::log( sprintf( 'Option %s : %s, conforme.', $ditl_option_pages, wp_json_encode( $ditl_option ) ) );
} elseif ( $ditl_dry_run ) {
	WP_CLI::log( sprintf( '[dry-run] Option %s serait posee a %s.', $ditl_option_pages, wp_json_encode( $ditl_option_cible ) ) );
} else {
	update_option( $ditl_option_pages, $ditl_option_cible, false );
	$ditl_ecritures++;
	WP_CLI::log( sprintf( 'Option %s = %s.', $ditl_option_pages, wp_json_encode( $ditl_option_cible ) ) );
}

$ditl_menus = ditl_boite_cli_menus_visant( array_values( $ditl_ids ) );

if ( empty( $ditl_menus ) ) {
	WP_CLI::log( 'Menus : aucun element de menu ne vise les pages Boite a outils (en-tete inchange).' );
} else {
	foreach ( $ditl_menus as $ditl_m ) {
		WP_CLI::warning( sprintf( 'Element de menu %d vise la page %d : a retirer si l\'en-tete ne doit pas changer.', $ditl_m['item'], $ditl_m['menu_object_id'] ) );
	}
}

foreach ( $ditl_ids as $ditl_langue => $ditl_id ) {
	WP_CLI::log( sprintf( 'URL %s : %s (statut %s)', $ditl_langue, get_permalink( $ditl_id ), get_post_status( $ditl_id ) ) );
}

if ( $ditl_dry_run ) {
	WP_CLI::success( 'Simulation terminee : aucune ecriture.' );
	return;
}

WP_CLI::success( sprintf( 'Termine : %d ecriture(s). Purger le cache de page (wp-content/cache/all).', $ditl_ecritures ) );
