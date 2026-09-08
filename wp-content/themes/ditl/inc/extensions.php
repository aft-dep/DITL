<?php
/**
 * Reglages d'extensions tierces portes par le theme (phase 1 de la refonte).
 *
 * Complement du script cli/configurer-extensions.php : ce qui n'a PAS
 * d'option en base chez l'extension concernee et ne peut donc etre pose que
 * par un hook ou une constante. Tout ce qui a une option est pose par le
 * script (rejouable par environnement), pas ici.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * WPForms 2.0 : Form Analytics coupe.
 *
 * Depuis la 2.0, WPForms charge assets/js/frontend/analytics.min.js sur
 * toute page contenant un formulaire et envoie les interactions de champs
 * (focus, saisie, abandon) a admin-ajax (wpforms_analytics_snapshot, endpoint
 * nopriv) en sendBeacon, sans cookie mais sans base legale declaree dans la
 * politique de confidentialite ni dans la banniere Complianz. Aucune option
 * d'administration ne le desactive, deux filtres se completent :
 * - wpforms_analytics_should_track_user : evalue a chaque requete
 *   (Frontend\Analytics::enqueue_footer_assets au pied de page,
 *   Analytics\Process a la reception des envois) ; a false, le script n'est
 *   plus charge et les envois eventuels sont ignores cote serveur. C'est le
 *   filtre EFFECTIF depuis le theme.
 * - wpforms_analytics_is_enabled : interrupteur general, mais evalue par le
 *   Loader des plugins_loaded, AVANT le chargement du theme : sans effet sur
 *   l'enregistrement des classes, il ne porte ici que sur les controles
 *   posterieurs (tache d'agregation a init, controles futurs). Pose pour
 *   exprimer l'intention et rester efficace si WPForms deplace son controle.
 * Effet front verifie : plus de analytics.min.js sur le gabarit Contact.
 */
add_filter( 'wpforms_analytics_should_track_user', '__return_false' );
add_filter( 'wpforms_analytics_is_enabled', '__return_false' );

/*
 * All In One WP Security 5.4.9 : retention du journal d'audit a 30 jours.
 *
 * La purge des evenements d'audit (cron aiowps_clean_old_events, methode
 * AIOWPSecurity_Audit_Event_Handler::delete_old_events) n'a pas d'option :
 * elle ne lit que la constante AIOWPSEC_PURGE_AUDIT_LOGS_AFTER_DAYS (90 jours
 * par defaut). Le theme est charge avant l'execution des crons, la constante
 * est donc vue par la purge. Un define anterieur (wp-config.php) garde la
 * priorite.
 */
if ( ! defined( 'AIOWPSEC_PURGE_AUDIT_LOGS_AFTER_DAYS' ) ) {
	define( 'AIOWPSEC_PURGE_AUDIT_LOGS_AFTER_DAYS', 30 );
}

/*
 * Complianz 7.5 + Site Kit : consent mode "basique" pour Google Analytics.
 *
 * L'integration Site Kit de Complianz met gtag.js en liste blanche du
 * bloqueur de cookies (google_gtagjs-js et son inline "after") : le script
 * est charge avant tout choix et emet des pings sans cookie vers Google, avec
 * l'adresse IP du visiteur (consent mode "avance", que la CNIL ne considere
 * pas comme exemptant de consentement). En retirant ces deux entrees, le
 * bloqueur ne libere gtag.js qu'au consentement "statistiques" : rien ne
 * part vers Google avant. Les gtag('consent','default') poses par Site Kit et
 * Complianz restent en place et s'appliquent des que le script est libere.
 */
add_filter( 'cmplz_whitelisted_script_tags', 'ditl_ext_gtag_hors_liste_blanche', 11 );
function ditl_ext_gtag_hors_liste_blanche( $tags ) {
	return array_values( array_diff( (array) $tags, array( 'google_gtagjs-js', 'google_gtagjs-js-after' ) ) );
}
