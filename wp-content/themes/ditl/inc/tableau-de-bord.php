<?php
/**
 * Tableau de bord de l'administration : retrait des widgets sans usage pour
 * l'equipe du client (promotion d'extensions, statistiques redondantes,
 * actualites WordPress), tous environnements.
 *
 * Retires :
 * - panneau "Bienvenue" (welcome_panel) ;
 * - "Evenements et nouveautes WordPress" (dashboard_primary) ;
 * - "Brouillon rapide" (dashboard_quick_press) ;
 * - Yoast SEO : "Posts Overview" (wpseo-dashboard-overview) et
 *   "Wincher: Top Keyphrases" (wpseo-wincher-dashboard-overview) ;
 * - Wordfence : activite de la semaine (wordfence_activity_report_widget) ;
 * - WPForms (wpforms_reports_widget_lite) ;
 * - WP Mail SMTP (wp_mail_smtp_reports_widget_lite).
 *
 * Les ecrans des extensions restent accessibles par leurs menus.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Priorite maximale : apres l'enregistrement des widgets par les extensions
// (wp_dashboard_setup, priorite 10).
add_action( 'wp_dashboard_setup', 'ditl_tdb_retirer_widgets', PHP_INT_MAX );

/**
 * Retire les widgets du tableau de bord et le panneau "Bienvenue".
 */
function ditl_tdb_retirer_widgets() {
	$widgets = array(
		'dashboard_primary',
		'dashboard_quick_press',
		'wpseo-dashboard-overview',
		'wpseo-wincher-dashboard-overview',
		'wordfence_activity_report_widget',
		'wpforms_reports_widget_lite',
		'wp_mail_smtp_reports_widget_lite',
	);

	// Contexte d'enregistrement variable selon l'extension : retrait dans
	// toutes les colonnes du tableau de bord.
	foreach ( $widgets as $widget ) {
		foreach ( array( 'normal', 'side', 'column3', 'column4' ) as $contexte ) {
			remove_meta_box( $widget, 'dashboard', $contexte );
		}
	}

	remove_action( 'welcome_panel', 'wp_welcome_panel' );
}
