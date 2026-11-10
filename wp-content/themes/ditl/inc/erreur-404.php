<?php
/**
 * Page d'erreur 404 sur mesure (maquettes finales du 10/11/2026, p. 33).
 *
 * - Gabarit : 404.php a la racine du theme enfant (remplace celui d'Astra),
 *   illustration dans template-parts/erreur-404-illustration.php.
 * - Mise en page Astra : sans barre laterale, pleine largeur (le gabarit
 *   gere son conteneur et son fond, comme la boite a outils) ; en-tete et
 *   pied de page du site inchanges.
 * - Feuille assets/css/erreur-404.css chargee sur la seule page 404.
 * - Textes : chaines traduisibles (textdomain 'ditl', languages/*.po) ; la
 *   langue est celle que Polylang deduit de l'URL demandee (/fr/..., /de/...,
 *   anglais sans prefixe).
 * - Statut HTTP 404 conserve (requete 404 du coeur) ; jamais mise en cache
 *   par WP Fastest Cache, qui ecarte les reponses 404.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Charge la feuille de la page 404 sur cette seule page.
 */
function ditl_404_enqueue() {
	if ( ! is_404() ) {
		return;
	}

	wp_enqueue_style(
		'ditl-erreur-404',
		get_stylesheet_directory_uri() . '/assets/css/erreur-404.css',
		array( 'ditl-style' ),
		DITL_THEME_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'ditl_404_enqueue' );

/**
 * Pas de barre laterale sur la page 404 (filtre Astra).
 *
 * @param string $layout Mise en page laterale calculee par Astra.
 * @return string "no-sidebar" sur la page 404.
 */
function ditl_404_astra_sans_barre_laterale( $layout ) {
	return is_404() ? 'no-sidebar' : $layout;
}
add_filter( 'astra_page_layout', 'ditl_404_astra_sans_barre_laterale', 20 );

/**
 * Pleine largeur sans conteneur Astra ("page-builder") : le gabarit 404 gere
 * sa largeur et son fond.
 *
 * @param string $layout Mise en page du contenu calculee par Astra.
 * @return string "page-builder" sur la page 404.
 */
function ditl_404_astra_pleine_largeur( $layout ) {
	return is_404() ? 'page-builder' : $layout;
}
add_filter( 'astra_get_content_layout', 'ditl_404_astra_pleine_largeur', 20 );

/**
 * URL de l'accueil dans la langue de la page 404 (bouton "Revenir a la page
 * d'accueil"), par l'adaptateur multilingue de la boite a outils (Polylang
 * aujourd'hui, WPML apres la phase 2).
 *
 * @return string URL (non echappee).
 */
function ditl_404_url_accueil() {
	if ( function_exists( 'ditl_boite_accueil_langue' ) && function_exists( 'ditl_boite_langue' ) ) {
		return ditl_boite_accueil_langue( ditl_boite_langue() );
	}

	return home_url( '/' );
}
