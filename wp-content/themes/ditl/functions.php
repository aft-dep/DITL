<?php
/**
 * Fonctions du theme enfant DiTL.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DITL_THEME_VERSION', '0.1.0' );

/**
 * Charge la feuille de style du theme enfant apres celle d'Astra.
 */
function ditl_enqueue_styles() {
	wp_enqueue_style(
		'ditl-style',
		get_stylesheet_uri(),
		array( 'astra-theme-css' ),
		DITL_THEME_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'ditl_enqueue_styles' );
