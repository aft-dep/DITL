<?php
/**
 * Configuration de Yoast SEO (gratuit) - phase 1 de la refonte.
 *
 * Exigence : ISO-SEO strict. Rien de ce que le coeur de WordPress et
 * Polylang emettaient dans le <head> (titres, canonical, hreflang, meta
 * robots) ne doit changer ; Yoast ne fait qu'AJOUTER (sitemap XML plus
 * riche, Open Graph / Twitter, JSON-LD, saisie future des meta
 * descriptions). Ce script pose :
 * - le separateur de titre "sc-ndash" (le tiret demi-cadratin &#8211; que le
 *   coeur emettait) a la place du tiret simple par defaut de Yoast ;
 * - les gabarits de titre des archives alignes sur les titres du coeur
 *   (categorie/etiquette sans le suffixe " Archives", auteur sans
 *   ", Author at", recherche sur le modele "Search Results for ..." avec
 *   guillemets typographiques) ;
 * - le titre de la page d'accueil : le coeur affichait "nom du site - slogan"
 *   sur la page de garde ; la page de garde etant une page statique, Yoast
 *   applique sinon le gabarit des pages ("home - nom du site"). Le gabarit
 *   %%sitename%% %%sep%% %%sitedesc%% est pose en meta _yoast_wpseo_title
 *   sur la page de garde ET ses traductions Polylang (5 langues) ;
 * - breadcrumbs desactives (le theme n'en affiche pas) ;
 * - telemetrie et notifications marketing coupees (tracking, generateur IA,
 *   avis de premiere configuration - la configuration se fait entierement
 *   ici, jamais par l'assistant de demarrage de Yoast) ;
 * - verification des valeurs deja correctes par defaut (sitemap actif,
 *   Open Graph / Twitter actifs, redirection des pages de fichiers joints
 *   active - identique au comportement actuel du coeur avec
 *   wp_attachment_pages_enabled a 0, qui redirige deja en 301 vers le
 *   fichier).
 *
 * AUCUNE meta description n'est saisie (travail editorial ulterieur).
 *
 * Les ecarts que les options ne couvrent pas (meta robots etendue par
 * Yoast, robots.txt virtuel remplace) sont neutralises par le theme dans
 * inc/seo.php (filtre wp_robots + regles robots.txt reinjectees).
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod).
 *
 * IMPORTANT : toute (re)activation de l'extension Yoast rearme les options
 * first_time_install et should_redirect_after_install_free (via
 * _wpseo_activate dans wp-seo-main.php). Ce script est donc a rejouer APRES
 * chaque activation de Yoast - c'est exactement le scenario du deploiement
 * en preprod puis en production.
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/configurer-yoast.php dry-run
 *   wp eval-file wp-content/themes/ditl/cli/configurer-yoast.php
 *
 * Le mode simulation accepte "dry-run" ou "--dry-run". Pas de mode
 * annuler : l'argument "annuler" est refuse avec une erreur explicite.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Usage : wp eval-file <script> [dry-run] - voir le docblock.
	// En acces web direct, reponse muette pour ne rien reveler du fichier.
	http_response_code( 404 );
	exit( 1 );
}

// Bibliotheque commune des scripts CLI du theme.
require_once __DIR__ . '/commun.php';

if ( ! class_exists( 'WPSEO_Options' ) ) {
	WP_CLI::error( 'Yoast SEO n\'est pas actif : activer l\'extension wordpress-seo avant de rejouer ce script.' );
}

// ---------------------------------------------------------------------------
// Lecture des arguments : mode simulation eventuel.
// ---------------------------------------------------------------------------

$ditl_modes   = ditl_cli_lire_modes( $args );
$ditl_dry_run = $ditl_modes['dry_run'];

// Pas de mode annuler ici : la configuration Yoast se rejoue, elle ne
// s'annule pas. Refus explicite (avant factorisation, "annuler" n'etait
// qu'un argument inconnu signale par un warning, puis le script
// s'executait quand meme en mode normal).
if ( $ditl_modes['annuler'] ) {
	WP_CLI::error( 'Ce script n\'a pas de mode annuler : argument refuse.' );
}

if ( $ditl_dry_run ) {
	WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucune ecriture en base ===' );
}

// ---------------------------------------------------------------------------
// Options Yoast a poser (cle => valeur cible).
// ---------------------------------------------------------------------------

// Sentinelle pour distinguer une cle absente d'une valeur nulle.
$ditl_absente = '__DITL_CLE_ABSENTE__';

// Compteur d'ecritures effectives : le flush final des regles de reecriture
// n'a lieu que si quelque chose a reellement change (rejeu sans ecriture).
$ditl_ecritures = 0;

$ditl_options_cibles = array(
	// Titres : reproduire EXACTEMENT ceux du coeur de WordPress.
	// Le separateur du coeur est le tiret demi-cadratin (&#8211;).
	'separator'             => 'sc-ndash',
	// Archives de taxonomie : le coeur n'ajoute pas " Archives" au terme.
	'title-tax-category'    => '%%term_title%% %%page%% %%sep%% %%sitename%%',
	'title-tax-post_tag'    => '%%term_title%% %%page%% %%sep%% %%sitename%%',
	'title-tax-post_format' => '%%term_title%% %%page%% %%sep%% %%sitename%%',
	// Archive d'auteur : le coeur affiche "nom - nom du site".
	'title-author-wpseo'    => '%%name%% %%page%% %%sep%% %%sitename%%',
	// Recherche : chaine du coeur (locale EN du site), guillemets
	// typographiques inclus. Le coeur localise cette chaine par langue,
	// pas Yoast : ecart residuel assume sur les recherches non anglaises
	// (pages noindex de toute facon).
	'title-search-wpseo'    => 'Search Results for “%%searchphrase%%” %%page%% %%sep%% %%sitename%%',
	// Breadcrumbs : le theme n'en affiche pas, on n'active rien.
	'breadcrumbs-enable'    => false,
	// Telemetrie et notifications marketing.
	'tracking'              => false,
	'enable_ai_generator'   => false,
	'first_time_install'    => false,
	'dismiss_configuration_workout_notice' => true,
	'should_redirect_after_install_free'   => false,
);

// Valeurs par defaut de Yoast deja correctes : verifiees (jamais ecrites),
// toute derive est signalee au rejeu.
$ditl_options_attendues = array(
	'title-post'          => '%%title%% %%page%% %%sep%% %%sitename%%',
	'title-page'          => '%%title%% %%page%% %%sep%% %%sitename%%',
	'title-archive-wpseo' => '%%date%% %%page%% %%sep%% %%sitename%%',
	'title-404-wpseo'     => 'Page not found %%sep%% %%sitename%%',
	'enable_xml_sitemap'  => true,
	'opengraph'           => true,
	'twitter'             => true,
	// Redirection 301 des URLs de fichiers joints vers le fichier :
	// comportement identique a l'existant (wp_attachment_pages_enabled = 0).
	'disable-attachment'  => true,
);

WP_CLI::log( '--- Options Yoast : valeurs cibles ---' );

foreach ( $ditl_options_cibles as $ditl_cle => $ditl_valeur_cible ) {
	$ditl_valeur_actuelle = WPSEO_Options::get( $ditl_cle, $ditl_absente );

	if ( $ditl_absente === $ditl_valeur_actuelle ) {
		WP_CLI::warning( sprintf( 'Option "%s" inconnue de cette version de Yoast : ignoree (verifier a la montee de version).', $ditl_cle ) );
		continue;
	}

	if ( $ditl_valeur_actuelle === $ditl_valeur_cible ) {
		WP_CLI::log( sprintf( '  %s : deja a %s, rien a faire.', $ditl_cle, var_export( $ditl_valeur_cible, true ) ) );
		continue;
	}

	if ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] %s : %s serait remplacee par %s.', $ditl_cle, var_export( $ditl_valeur_actuelle, true ), var_export( $ditl_valeur_cible, true ) ) );
		continue;
	}

	if ( WPSEO_Options::set( $ditl_cle, $ditl_valeur_cible ) ) {
		WP_CLI::log( sprintf( '  %s : %s -> %s.', $ditl_cle, var_export( $ditl_valeur_actuelle, true ), var_export( $ditl_valeur_cible, true ) ) );
		$ditl_ecritures++;
	} else {
		WP_CLI::warning( sprintf( 'Option "%s" : ecriture refusee par Yoast (valeur %s non validee ?).', $ditl_cle, var_export( $ditl_valeur_cible, true ) ) );
	}
}

WP_CLI::log( '--- Options Yoast : valeurs par defaut attendues (controle) ---' );

foreach ( $ditl_options_attendues as $ditl_cle => $ditl_valeur_attendue ) {
	$ditl_valeur_actuelle = WPSEO_Options::get( $ditl_cle, $ditl_absente );

	if ( $ditl_valeur_actuelle === $ditl_valeur_attendue ) {
		WP_CLI::log( sprintf( '  %s : %s, conforme.', $ditl_cle, var_export( $ditl_valeur_attendue, true ) ) );
	} else {
		WP_CLI::warning( sprintf( 'Option "%s" : valeur %s au lieu de %s attendue - a corriger manuellement ou a documenter.', $ditl_cle, var_export( $ditl_valeur_actuelle, true ), var_export( $ditl_valeur_attendue, true ) ) );
	}
}

// ---------------------------------------------------------------------------
// Titre de la page d'accueil : meta _yoast_wpseo_title sur la page de garde
// et toutes ses traductions Polylang.
// ---------------------------------------------------------------------------

// Le coeur affichait "nom du site - slogan" sur la page de garde. La page de
// garde etant une page statique, Yoast lui appliquerait le gabarit des pages
// ("home - nom du site") : la meta ci-dessous retablit le titre du coeur.
// Le gabarit (et non le texte en dur) suit les evolutions du nom / slogan.
$ditl_gabarit_titre_accueil = '%%sitename%% %%sep%% %%sitedesc%%';

WP_CLI::log( '--- Titre de la page d\'accueil (meta _yoast_wpseo_title) ---' );

$ditl_page_garde = (int) get_option( 'page_on_front' );

if ( 'page' !== get_option( 'show_on_front' ) || $ditl_page_garde <= 0 ) {
	WP_CLI::warning( 'Pas de page de garde statique : la meta de titre d\'accueil n\'a pas ete posee (verifier title-home-wpseo).' );
} else {
	$ditl_pages_accueil = function_exists( 'pll_get_post_translations' )
		? array_values( pll_get_post_translations( $ditl_page_garde ) )
		: array( $ditl_page_garde );

	if ( array() === $ditl_pages_accueil ) {
		$ditl_pages_accueil = array( $ditl_page_garde );
	}

	foreach ( $ditl_pages_accueil as $ditl_id_accueil ) {
		$ditl_id_accueil = (int) $ditl_id_accueil;
		$ditl_langue     = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $ditl_id_accueil ) : '';
		$ditl_etiquette  = sprintf( 'page %d%s', $ditl_id_accueil, '' !== $ditl_langue ? ' [' . $ditl_langue . ']' : '' );
		$ditl_meta       = get_post_meta( $ditl_id_accueil, '_yoast_wpseo_title', true );

		if ( $ditl_meta === $ditl_gabarit_titre_accueil ) {
			WP_CLI::log( sprintf( '  %s : meta deja posee, rien a faire.', $ditl_etiquette ) );
			continue;
		}

		if ( '' !== $ditl_meta ) {
			// Une valeur differente a ete saisie (probablement en admin) :
			// on ne l'ecrase pas, on signale.
			WP_CLI::warning( sprintf( '%s : meta _yoast_wpseo_title deja definie a "%s", non ecrasee.', $ditl_etiquette, $ditl_meta ) );
			continue;
		}

		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] %s : la meta "%s" serait posee.', $ditl_etiquette, $ditl_gabarit_titre_accueil ) );
			continue;
		}

		update_post_meta( $ditl_id_accueil, '_yoast_wpseo_title', $ditl_gabarit_titre_accueil );
		WP_CLI::log( sprintf( '  %s : meta "%s" posee.', $ditl_etiquette, $ditl_gabarit_titre_accueil ) );
		$ditl_ecritures++;
	}
}

// ---------------------------------------------------------------------------
// Regles de reecriture : le sitemap Yoast (sitemap_index.xml) doit repondre.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Regles de reecriture ---' );

if ( $ditl_dry_run ) {
	WP_CLI::log( '  [dry-run] flush_rewrite_rules() serait execute si une ecriture avait lieu (URLs du sitemap Yoast).' );
} elseif ( $ditl_ecritures > 0 ) {
	flush_rewrite_rules( false );
	WP_CLI::log( '  flush_rewrite_rules() execute : sitemap_index.xml servi par Yoast (l\'ancien /wp-sitemap.xml est redirige en 301 par Yoast).' );
} else {
	WP_CLI::log( '  Aucune ecriture lors de ce passage : flush_rewrite_rules() inutile, ignore.' );
}

// ---------------------------------------------------------------------------
// Rappels (aucune modification ici).
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Rappels ---' );
WP_CLI::log( '  Aucune meta description saisie : travail editorial ulterieur, page par page.' );
WP_CLI::log( '  Le filtre wp_robots et les regles robots.txt iso-coeur sont portes par le theme (inc/seo.php).' );
WP_CLI::log( '  Les tables d\'indexables Yoast (wp_yoast_*) se construisent automatiquement a la navigation ; "wp yoast index" les remplit en une passe si besoin.' );
WP_CLI::log( '  Apres deploiement en production : declarer sitemap_index.xml dans Search Console (l\'ancien /wp-sitemap.xml reste redirige en 301).' );

WP_CLI::log( '' );
WP_CLI::log( $ditl_dry_run ? 'Simulation terminee.' : 'Configuration terminee.' );
