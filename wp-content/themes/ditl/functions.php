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

define( 'DITL_THEME_VERSION', '0.2.0' );

/*
 * Metaboxes des gabarits sur mesure (remplacement progressif d'Elementor).
 */
require_once get_stylesheet_directory() . '/inc/metaboxes/helpers.php';
require_once get_stylesheet_directory() . '/inc/metaboxes/projet-ditl.php';

/**
 * Applique au HTML riche des metas le meme traitement que le widget
 * texte d'Elementor (shortcodes puis typographie WordPress), afin de
 * conserver un rendu identique a l'existant (ex. wptexturize transforme
 * un tiret simple entoure d'espaces en tiret demi-cadratin).
 *
 * @param string $content HTML riche issu d'une meta de gabarit.
 * @return string HTML pret a etre affiche (a echapper via wp_kses_post).
 */
function ditl_format_rich_text( $content ) {
	$content = shortcode_unautop( $content );
	$content = do_shortcode( $content );

	return wptexturize( $content );
}

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

/**
 * Charge la feuille de style du gabarit "Projet DiTL".
 */
function ditl_enqueue_gabarit_projet_ditl() {
	if ( ! is_page_template( DITL_TPL_PROJET_DITL ) ) {
		return;
	}

	wp_enqueue_style(
		'ditl-gabarit-projet-ditl',
		get_stylesheet_directory_uri() . '/assets/css/gabarit-projet-ditl.css',
		array( 'ditl-style' ),
		DITL_THEME_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'ditl_enqueue_gabarit_projet_ditl' );

/**
 * Retire les scripts Elementor sur les gabarits sur mesure.
 *
 * Les metas Elementor restent en base (sauvegarde dormante), Elementor
 * considere donc la page comme construite avec lui et charge frontend.min.js
 * sans son objet de configuration (rien n'est rendu par lui), ce qui
 * provoque une erreur JavaScript. Rien sur ces pages n'en depend.
 */
function ditl_retire_scripts_elementor_gabarit() {
	if ( ! is_page_template( DITL_TPL_PROJET_DITL ) ) {
		return;
	}

	wp_dequeue_script( 'elementor-frontend' );
	wp_dequeue_script( 'elementor-frontend-modules' );
	wp_dequeue_script( 'elementor-webpack-runtime' );
}
add_action( 'wp_enqueue_scripts', 'ditl_retire_scripts_elementor_gabarit', 99 );
