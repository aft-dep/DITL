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

/*
 * WP Fastest Cache : aucune page affichant un formulaire WPForms n'est mise
 * en cache.
 *
 * Le formulaire embarque un jeton anti-spam dans le HTML (raison de
 * l'exclusion des pages /fr/contact/ et /contact-us/ posee par
 * cli/configurer-cache.php). Les pages Contact ES, PT et DE (/es/contacto/,
 * /pt/contato/, /de/kontakt/) n'y figuraient pas et etaient mises en cache
 * avec leur jeton (constate en local le 30/10/2026). Plutot qu'une liste
 * d'URL a tenir a jour (langues, futures URL WPML), le rendu du formulaire
 * exclut lui-meme la page en cours : wpfc_exclude_current_page() est la
 * fonction publique prevue par le plugin pour cela (aucun fichier de cache
 * cree, commentaire "This page has been excluded" en fin de page).
 * Le hook wpforms_frontend_output_before est declenche a chaque affichage
 * d'un formulaire, quel que soit le mode d'insertion (bloc, code court).
 */
add_action( 'wpforms_frontend_output_before', 'ditl_ext_exclure_page_formulaire_du_cache' );
function ditl_ext_exclure_page_formulaire_du_cache() {
	if ( function_exists( 'wpfc_exclude_current_page' ) ) {
		wpfc_exclude_current_page();
	}
}

/*
 * WP Fastest Cache : les pages de resultats de recherche ne sont pas mises en
 * cache.
 *
 * Le plugin cache les recherches en URL propre (/search/<terme>/) : chaque
 * terme saisi creerait un fichier, sans limite, et rien ne les purge sur un
 * environnement sans WP-Cron (preprod). Contenu public, aucun gain utile :
 * meme exclusion par la fonction publique du plugin.
 */
add_action( 'template_redirect', 'ditl_ext_exclure_recherche_du_cache' );
function ditl_ext_exclure_recherche_du_cache() {
	if ( is_search() && function_exists( 'wpfc_exclude_current_page' ) ) {
		wpfc_exclude_current_page();
	}
}
