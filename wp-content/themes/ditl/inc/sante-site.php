<?php
/**
 * Outil "Sante du site" : retrait des controles qui signalent des choix
 * assumes, pour ne laisser a l'administrateur que les alertes utiles.
 *
 * - theme_version (tous environnements) : aucun theme par defaut Twenty*
 *   n'est installe, volontairement (Astra + theme enfant seulement). Les mises
 *   a jour de themes restent signalees par Tableau de bord > Mises a jour.
 * - Hors production (preprod) : mises a jour automatiques
 *   (AUTOMATIC_UPDATER_DISABLED), taches planifiees (DISABLE_WP_CRON) et
 *   indexation (noindex) sont coupees expres. En production, ces trois
 *   controles restent actifs : ils y signaleraient un vrai probleme.
 *
 * L'environnement est lu par wp_get_environment_type() (constante
 * WP_ENVIRONMENT_TYPE, "staging" en preprod ; "production" par defaut).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'site_status_tests', 'ditl_sante_retirer_tests' );

/**
 * Retire les controles de la Sante du site non pertinents ici.
 *
 * @param array $tests Controles, classes en 'direct' et 'async'.
 * @return array Controles sans ceux des choix assumes.
 */
function ditl_sante_retirer_tests( $tests ) {
	unset( $tests['direct']['theme_version'] );

	if ( 'production' !== wp_get_environment_type() ) {
		unset(
			$tests['async']['background_updates'],
			$tests['direct']['scheduled_events'],
			$tests['direct']['search_engine_visibility']
		);
	}

	return $tests;
}
