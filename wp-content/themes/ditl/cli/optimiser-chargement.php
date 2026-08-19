<?php
/**
 * Optimisation du chargement - options en base (phase 1 de la refonte).
 *
 * Volet BASE DE DONNEES du chantier "optimisation du chargement" : active
 * l'hebergement local des polices Google d'Astra. Les volets CODE du
 * chantier (inc/perf.php, polices Roboto/Jost du theme, preloads) sont
 * versionnes dans le theme et deployes par git ; ce script ne pose que ce
 * qui vit en base et doit donc etre REJOUE en preprod et en production.
 *
 * Ce que fait le mode normal :
 * - active l'option Astra "Load Google Fonts Locally" (self_hosted_gfonts
 *   dans l'option astra_admin_settings) : au premier rendu d'une page du
 *   front, Astra telecharge la feuille Google Fonts d'Exo (graisses 300,
 *   400, 500, 600, font-display: fallback inchange) et ses woff2 dans
 *   wp-content/astra-local-fonts/, puis sert le tout localement - plus
 *   aucune requete des visiteurs vers fonts.googleapis.com/fonts.gstatic.com
 *   (performance + RGPD) ;
 * - active l'option Astra "Preload Local Fonts" (preload_local_fonts) :
 *   Astra emet un <link rel="preload" as="font"> par famille locale.
 *
 * IMPORTANT apres execution : visiter une page du front (ou la recharger
 * apres purge du cache de page) pour declencher le telechargement initial
 * des polices par le serveur - il necessite un acces HTTPS sortant vers
 * fonts.googleapis.com et fonts.gstatic.com DEPUIS le serveur. Verifier
 * ensuite que le <head> ne reference plus fonts.googleapis.com.
 *
 * Mode annuler : desactive les deux options, purge les caches d'Astra
 * (entree astra_font_url de l'option astra-settings, site option
 * astra_local_font_files) et supprime le dossier
 * wp-content/astra-local-fonts/. Le site recharge alors les polices
 * depuis Google comme avant.
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod).
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/optimiser-chargement.php dry-run
 *   wp eval-file wp-content/themes/ditl/cli/optimiser-chargement.php
 *   wp eval-file wp-content/themes/ditl/cli/optimiser-chargement.php annuler
 *
 * Les modes acceptent les formes "dry-run"/"--dry-run" et
 * "annuler"/"--annuler" et sont combinables (annuler en simulation).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Usage : wp eval-file <script> [dry-run|annuler] - voir le docblock.
	// En acces web direct, reponse muette pour ne rien reveler du fichier.
	http_response_code( 404 );
	exit( 1 );
}

// Bibliotheque commune des scripts CLI du theme.
require_once __DIR__ . '/commun.php';

if ( ! defined( 'ASTRA_THEME_VERSION' ) || ! class_exists( 'Astra_API_Init' ) ) {
	WP_CLI::error( 'Le theme Astra n\'est pas actif (ou trop ancien) : impossible de configurer ses options de polices locales.' );
}

// ---------------------------------------------------------------------------
// Lecture des arguments : modes simulation / annulation.
// ---------------------------------------------------------------------------

$ditl_modes   = ditl_cli_lire_modes( $args );
$ditl_dry_run = $ditl_modes['dry_run'];
$ditl_annuler = $ditl_modes['annuler'];

if ( $ditl_dry_run ) {
	WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucune ecriture en base, aucun fichier touche ===' );
}

if ( $ditl_annuler ) {
	WP_CLI::log( '=== MODE ANNULER : retour aux polices Google distantes ===' );
}

// ---------------------------------------------------------------------------
// Options Astra visees (option unique astra_admin_settings, cles booleennes).
// ---------------------------------------------------------------------------

// Valeurs cibles selon le mode.
$ditl_cible = $ditl_annuler ? false : true;

$ditl_cles = array(
	'self_hosted_gfonts'  => 'hebergement local des polices Google (Exo)',
	'preload_local_fonts' => 'preload des polices locales',
);

$ditl_ecritures = 0;

WP_CLI::log( '--- Options Astra (astra_admin_settings) ---' );

foreach ( $ditl_cles as $ditl_cle => $ditl_libelle ) {
	$ditl_actuelle = (bool) Astra_API_Init::get_admin_settings_option( $ditl_cle, false );

	if ( $ditl_actuelle === $ditl_cible ) {
		WP_CLI::log( sprintf( '  %s (%s) : deja a %s, rien a faire.', $ditl_cle, $ditl_libelle, var_export( $ditl_cible, true ) ) );
		continue;
	}

	if ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] %s (%s) : %s serait remplacee par %s.', $ditl_cle, $ditl_libelle, var_export( $ditl_actuelle, true ), var_export( $ditl_cible, true ) ) );
		continue;
	}

	Astra_API_Init::update_admin_settings_option( $ditl_cle, $ditl_cible );
	WP_CLI::log( sprintf( '  %s (%s) : %s -> %s.', $ditl_cle, $ditl_libelle, var_export( $ditl_actuelle, true ), var_export( $ditl_cible, true ) ) );
	$ditl_ecritures++;
}

// ---------------------------------------------------------------------------
// Caches de polices d'Astra (a purger en mode annuler uniquement).
// ---------------------------------------------------------------------------

// Dossier des polices locales : wp-content/astra-local-fonts par defaut
// (voir Astra_WebFont_Loader::get_fonts_folder, filtrable).
$ditl_dossier_polices = apply_filters( 'astra_local_fonts_base_path', WP_CONTENT_DIR ) . '/' . apply_filters( 'astra_local_fonts_directory_name', 'astra-local-fonts' );

if ( $ditl_annuler ) {
	WP_CLI::log( '--- Purge des caches de polices d\'Astra ---' );

	// Entree astra_font_url de l'option astra-settings : URL de la feuille
	// locale mise en cache par astra_get_webfont_url().
	if ( false !== astra_get_option( 'astra_font_url', false ) ) {
		if ( $ditl_dry_run ) {
			WP_CLI::log( '  [dry-run] l\'entree astra_font_url (astra-settings) serait supprimee.' );
		} else {
			astra_delete_option( 'astra_font_url' );
			WP_CLI::log( '  Entree astra_font_url (astra-settings) supprimee.' );
			$ditl_ecritures++;
		}
	} else {
		WP_CLI::log( '  Entree astra_font_url absente, rien a faire.' );
	}

	// Site option des fichiers a precharger.
	if ( false !== get_site_option( 'astra_local_font_files', false ) ) {
		if ( $ditl_dry_run ) {
			WP_CLI::log( '  [dry-run] la site option astra_local_font_files serait supprimee.' );
		} else {
			delete_site_option( 'astra_local_font_files' );
			WP_CLI::log( '  Site option astra_local_font_files supprimee.' );
			$ditl_ecritures++;
		}
	} else {
		WP_CLI::log( '  Site option astra_local_font_files absente, rien a faire.' );
	}

	// Dossier des woff2 telecharges.
	if ( is_dir( $ditl_dossier_polices ) ) {
		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] le dossier %s serait supprime.', $ditl_dossier_polices ) );
		} else {
			global $wp_filesystem;

			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ( $wp_filesystem && $wp_filesystem->delete( $ditl_dossier_polices, true ) ) {
				WP_CLI::log( sprintf( '  Dossier %s supprime.', $ditl_dossier_polices ) );
			} else {
				WP_CLI::warning( sprintf( 'Suppression du dossier %s impossible : a retirer manuellement.', $ditl_dossier_polices ) );
			}
		}
	} else {
		WP_CLI::log( sprintf( '  Dossier %s absent, rien a faire.', $ditl_dossier_polices ) );
	}
}

// ---------------------------------------------------------------------------
// Etat des polices locales (informatif, aucune modification).
// ---------------------------------------------------------------------------

if ( ! $ditl_annuler ) {
	WP_CLI::log( '--- Etat des polices locales ---' );

	if ( is_dir( $ditl_dossier_polices ) ) {
		$ditl_fichiers = glob( $ditl_dossier_polices . '/*/*.woff2' );
		$ditl_nb       = is_array( $ditl_fichiers ) ? count( $ditl_fichiers ) : 0;
		WP_CLI::log( sprintf( '  Dossier %s present, %d fichier(s) woff2.', $ditl_dossier_polices, $ditl_nb ) );
	} else {
		WP_CLI::log( sprintf( '  Dossier %s pas encore cree : le telechargement se fait au premier rendu d\'une page du front (purger le cache de page puis visiter le site).', $ditl_dossier_polices ) );
	}
}

// ---------------------------------------------------------------------------
// Rappels (aucune modification ici).
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Rappels ---' );

if ( $ditl_annuler ) {
	WP_CLI::log( '  Purger le cache de page (wp-content/cache/all) : les pages en cache referencent la feuille de police locale supprimee.' );
} else {
	WP_CLI::log( '  Purger le cache de page (wp-content/cache/all) puis visiter une page du front pour declencher le telechargement des polices.' );
	WP_CLI::log( '  Le serveur doit pouvoir sortir en HTTPS vers fonts.googleapis.com et fonts.gstatic.com (telechargement initial uniquement).' );
	WP_CLI::log( '  Verifier ensuite l\'absence de fonts.googleapis.com dans le <head> du front.' );
}

WP_CLI::log( '' );

if ( $ditl_dry_run ) {
	WP_CLI::log( 'Simulation terminee.' );
} else {
	WP_CLI::log( sprintf(
		'%s (%d ecriture(s) lors de ce passage).',
		$ditl_annuler ? 'Annulation terminee' : 'Configuration terminee',
		$ditl_ecritures
	) );
}
