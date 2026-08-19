<?php
/**
 * Desactivation d'Elementor et de ses addons (phase 1 de la refonte).
 *
 * Les 7 gabarits, les pages simples et les articles sont rendus par le
 * theme : Elementor ne rend plus rien sur le site. Ce script :
 * - desactive elementor, post-grid-elementor-addon et ultimate-post-kit
 *   (les fichiers restent sur le disque : reactivation possible) ;
 * - passe l'option elementor_unfiltered_files_upload a 0 : l'upload de SVG
 *   etait ouvert a tout compte disposant de upload_files, et le filtre
 *   upload_mimes comme le sanitizer SVG d'Elementor disparaissent avec sa
 *   desactivation. L'ancienne valeur est memorisee dans l'option de
 *   sauvegarde ditl_sauvegarde_svg_elementor (reversibilite) ;
 * - purge les 4 options residuelles du mode sans echec (elementor_safe_mode,
 *   elementor_safe_mode_token - jeton en clair en base -,
 *   elementor_safe_mode_allowed_plugins, elementor_safe_mode_created_mu_dir).
 *   Elementor ne les purge via disable_safe_mode() que si l'utilisateur
 *   courant a install_plugins, ce qui echoue en WP-CLI sans --user : la
 *   suppression directe rend le nettoyage independant de l'utilisateur
 *   d'execution. Combinees au mu-plugin, ces options rearmaient le mode
 *   sans echec pre-authentification par cookie jeton ;
 * - NE TOUCHE PAS aux metas _elementor_* : elles restent en base comme
 *   sauvegarde dormante (purge prevue en phase 2).
 *
 * PRECONDITION avant rejeu en PRODUCTION : tous les contenus rendus par
 * Elementor doivent avoir ete migres. Les pages ES/PT/DE ne le sont pas
 * encore (prevu en phase 2) : leur rendu casse tant qu'elles dependent
 * d'Elementor. Le script le verifie et l'affiche avant de desactiver
 * (controle informatif, il n'interrompt pas l'execution).
 *
 * Mode annulation : reactive les trois extensions et restaure l'ancienne
 * valeur de l'option d'upload depuis la sauvegarde (qui est alors
 * supprimee). Les options du mode sans echec ne sont PAS recreees : si le
 * mode sans echec redevenait necessaire apres reactivation, Elementor
 * regenere un jeton frais de lui-meme.
 *
 * Le mu-plugin wp-content/mu-plugins/elementor-safe-mode.php ne peut pas
 * etre desactive par ce script (les mu-plugins sont toujours charges) :
 * sa suppression est une operation FICHIER a part, a faire sur chaque
 * environnement (retire du versionnement en local ; en production, le
 * fichier sera a supprimer lors du deploiement). Suppression du fichier
 * ET rejeu de ce script = menage complet du mode sans echec (le fichier
 * seul se rearme avec les options, les options seules sont inertes).
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod).
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/desactiver-elementor.php dry-run
 *   wp eval-file wp-content/themes/ditl/cli/desactiver-elementor.php
 *   wp eval-file wp-content/themes/ditl/cli/desactiver-elementor.php annuler
 *
 * Le mode simulation accepte "dry-run" ou "--dry-run", l'annulation
 * "annuler" ou "--annuler" (combinables).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Usage : wp eval-file <script> [annuler] [dry-run] - voir le docblock.
	// En acces web direct, reponse muette pour ne rien reveler du fichier.
	http_response_code( 404 );
	exit( 1 );
}

// Bibliotheque commune des scripts CLI du theme.
require_once __DIR__ . '/commun.php';

require_once ABSPATH . 'wp-admin/includes/plugin.php';

// ---------------------------------------------------------------------------
// Lecture des arguments : mode annulation et mode simulation eventuels.
// ---------------------------------------------------------------------------

$ditl_modes   = ditl_cli_lire_modes( $args );
$ditl_dry_run = $ditl_modes['dry_run'];
$ditl_annuler = $ditl_modes['annuler'];

if ( $ditl_dry_run ) {
	WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucune ecriture en base ===' );
}

// Extensions concernees : Elementor et ses deux addons (plus rien sur le
// site n'est rendu par eux, tous les gabarits sont rendus par le theme).
$ditl_extensions = array(
	'elementor/elementor.php'                                 => 'Elementor',
	'post-grid-elementor-addon/post-grid-elementor-addon.php' => 'Post Grid Elementor Addon',
	'ultimate-post-kit/ultimate-post-kit.php'                 => 'Ultimate Post Kit',
);

$ditl_option_svg        = 'elementor_unfiltered_files_upload';
$ditl_option_sauvegarde = 'ditl_sauvegarde_svg_elementor';

if ( $ditl_annuler ) {

	// -----------------------------------------------------------------------
	// Mode annulation : reactivation des extensions, restauration de l'option.
	// -----------------------------------------------------------------------

	WP_CLI::log( '--- Annulation : reactivation d\'Elementor et de ses addons ---' );

	foreach ( $ditl_extensions as $ditl_fichier => $ditl_nom ) {
		if ( is_plugin_active( $ditl_fichier ) ) {
			WP_CLI::log( sprintf( '  %s : deja active, rien a faire.', $ditl_nom ) );
			continue;
		}

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $ditl_fichier ) ) {
			WP_CLI::warning( sprintf( '%s : fichier %s absent du disque, reactivation impossible.', $ditl_nom, $ditl_fichier ) );
			continue;
		}

		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] %s : serait reactivee.', $ditl_nom ) );
			continue;
		}

		$ditl_resultat = activate_plugin( $ditl_fichier );

		if ( is_wp_error( $ditl_resultat ) ) {
			WP_CLI::warning( sprintf( '%s : echec de reactivation - %s', $ditl_nom, $ditl_resultat->get_error_message() ) );
		} else {
			WP_CLI::log( sprintf( '  %s : REACTIVEE.', $ditl_nom ) );
		}
	}

	$ditl_sauvegarde = get_option( $ditl_option_sauvegarde, null );

	if ( null === $ditl_sauvegarde ) {
		WP_CLI::log( sprintf( '  Option %s : aucune sauvegarde trouvee, valeur laissee telle quelle (%s).', $ditl_option_svg, var_export( get_option( $ditl_option_svg, null ), true ) ) );
	} elseif ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] Option %s : serait restauree a "%s" (et la sauvegarde supprimee).', $ditl_option_svg, $ditl_sauvegarde ) );
	} else {
		update_option( $ditl_option_svg, $ditl_sauvegarde );
		delete_option( $ditl_option_sauvegarde );
		WP_CLI::log( sprintf( '  Option %s : restauree a "%s" (sauvegarde supprimee).', $ditl_option_svg, $ditl_sauvegarde ) );
	}

	WP_CLI::log( '' );
	WP_CLI::log( $ditl_dry_run ? 'Simulation d\'annulation terminee.' : 'Annulation terminee.' );

	return;
}

// ---------------------------------------------------------------------------
// Controle informatif : contenus publies encore rendus par Elementor.
// ---------------------------------------------------------------------------

// Un contenu casse a la desactivation s'il est encore construit par
// Elementor (_elementor_edit_mode = builder avec des donnees non vides)
// sans etre rendu par un gabarit du theme (les pages migrees gardent leur
// flag dormant mais leur _wp_page_template pointe vers page-templates/).
// Controle informatif : le script continue, mais celui qui rejoue en
// preprod ou en prod sait ce qui va casser (pages ES/PT/DE en phase 1).
WP_CLI::log( '--- Controle : contenus publies encore rendus par Elementor ---' );

// eval-file n'execute pas forcement le script dans la portee globale.
global $wpdb;

$ditl_encore_elementor = $wpdb->get_results(
	"SELECT p.ID, p.post_title, p.post_type
	FROM {$wpdb->posts} p
	INNER JOIN {$wpdb->postmeta} mode ON mode.post_id = p.ID
		AND mode.meta_key = '_elementor_edit_mode' AND mode.meta_value = 'builder'
	INNER JOIN {$wpdb->postmeta} donnees ON donnees.post_id = p.ID
		AND donnees.meta_key = '_elementor_data'
		AND donnees.meta_value != '' AND donnees.meta_value != '[]'
	LEFT JOIN {$wpdb->postmeta} gabarit ON gabarit.post_id = p.ID
		AND gabarit.meta_key = '_wp_page_template'
		AND gabarit.meta_value LIKE 'page-templates/%'
	WHERE p.post_status = 'publish' AND gabarit.post_id IS NULL
	ORDER BY p.ID"
);

if ( array() === $ditl_encore_elementor || null === $ditl_encore_elementor ) {
	WP_CLI::log( '  Aucun : tous les contenus publies sont rendus par le theme.' );
} else {
	foreach ( $ditl_encore_elementor as $ditl_contenu ) {
		$ditl_langue = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( (int) $ditl_contenu->ID ) : '';

		WP_CLI::warning( sprintf(
			'Contenu %d (%s%s, "%s") encore rendu par Elementor : son rendu casse a la desactivation (migration prevue en phase 2).',
			$ditl_contenu->ID,
			$ditl_contenu->post_type,
			'' !== $ditl_langue ? ' ' . $ditl_langue : '',
			$ditl_contenu->post_title
		) );
	}
}

// ---------------------------------------------------------------------------
// Mode normal : desactivation des extensions.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Desactivation d\'Elementor et de ses addons ---' );

foreach ( $ditl_extensions as $ditl_fichier => $ditl_nom ) {
	if ( ! is_plugin_active( $ditl_fichier ) ) {
		WP_CLI::log( sprintf( '  %s : deja inactive, rien a faire.', $ditl_nom ) );
		continue;
	}

	if ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] %s : serait desactivee.', $ditl_nom ) );
		continue;
	}

	// Desactivation standard (les hooks de desactivation s'executent,
	// comme depuis l'ecran des extensions). Les fichiers restent sur le
	// disque : la reactivation via le mode annuler reste possible.
	deactivate_plugins( $ditl_fichier );
	WP_CLI::log( sprintf( '  %s : DESACTIVEE (fichiers conserves sur le disque).', $ditl_nom ) );
}

// ---------------------------------------------------------------------------
// Fermeture de l'upload SVG : elementor_unfiltered_files_upload -> 0.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Option d\'upload de fichiers non filtres (SVG) ---' );

$ditl_valeur_svg = get_option( $ditl_option_svg, null );

if ( null === $ditl_valeur_svg ) {
	WP_CLI::log( sprintf( '  Option %s absente : rien a fermer (Elementor la considere desactivee).', $ditl_option_svg ) );
} elseif ( '0' === (string) $ditl_valeur_svg || '' === (string) $ditl_valeur_svg ) {
	WP_CLI::log( sprintf( '  Option %s deja a "%s" : rien a faire.', $ditl_option_svg, $ditl_valeur_svg ) );
} elseif ( $ditl_dry_run ) {
	WP_CLI::log( sprintf( '  [dry-run] Option %s : "%s" serait sauvegardee dans %s puis passee a "0".', $ditl_option_svg, $ditl_valeur_svg, $ditl_option_sauvegarde ) );
} else {
	// La sauvegarde n'est ecrite que si elle n'existe pas encore : un
	// rejeu apres modification manuelle ne doit pas ecraser la valeur
	// d'origine memorisee.
	if ( false === get_option( $ditl_option_sauvegarde, false ) ) {
		update_option( $ditl_option_sauvegarde, (string) $ditl_valeur_svg, false );
		WP_CLI::log( sprintf( '  Ancienne valeur "%s" sauvegardee dans %s.', $ditl_valeur_svg, $ditl_option_sauvegarde ) );
	}

	update_option( $ditl_option_svg, '0' );
	WP_CLI::log( sprintf( '  Option %s passee a "0" : upload SVG referme.', $ditl_option_svg ) );
}

// ---------------------------------------------------------------------------
// Purge des options residuelles du mode sans echec Elementor.
// ---------------------------------------------------------------------------

// Residu des crashs Elementor de 2025 : jeton en clair en base et etat
// "arme" du mode sans echec. Inertes sans le mu-plugin, mais rearmables
// pre-authentification si le fichier reapparaissait (vieux checkout,
// oubli de deploiement). Voir le docblock : Elementor ne les purge que
// pour un utilisateur ayant install_plugins, jamais en WP-CLI sans --user.
WP_CLI::log( '--- Purge des options du mode sans echec Elementor ---' );

$ditl_options_safe_mode = array(
	'elementor_safe_mode',
	'elementor_safe_mode_token',
	'elementor_safe_mode_allowed_plugins',
	'elementor_safe_mode_created_mu_dir',
);

foreach ( $ditl_options_safe_mode as $ditl_option_safe_mode ) {
	if ( null === get_option( $ditl_option_safe_mode, null ) ) {
		WP_CLI::log( sprintf( '  Option %s : deja absente.', $ditl_option_safe_mode ) );
	} elseif ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] Option %s : serait purgee.', $ditl_option_safe_mode ) );
	} else {
		delete_option( $ditl_option_safe_mode );
		WP_CLI::log( sprintf( '  Option %s : PURGEE.', $ditl_option_safe_mode ) );
	}
}

// ---------------------------------------------------------------------------
// Rappels (aucune modification ici).
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Rappels ---' );
WP_CLI::log( '  Les metas _elementor_* restent en base (sauvegarde dormante, purge en phase 2).' );

if ( file_exists( WP_CONTENT_DIR . '/mu-plugins/elementor-safe-mode.php' ) ) {
	WP_CLI::log( '  Le mu-plugin wp-content/mu-plugins/elementor-safe-mode.php est encore present : sa suppression est une operation fichier a part (hors de ce script). Fichier supprime + options purgees ci-dessus = menage complet du mode sans echec.' );
}

WP_CLI::log( '' );
WP_CLI::log( $ditl_dry_run ? 'Simulation terminee.' : 'Desactivation terminee.' );
