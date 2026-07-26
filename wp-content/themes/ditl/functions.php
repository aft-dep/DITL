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

define( 'DITL_THEME_VERSION', '0.4.0' );

/*
 * Metaboxes des gabarits sur mesure (remplacement progressif d'Elementor).
 */
require_once get_stylesheet_directory() . '/inc/metaboxes/helpers.php';
require_once get_stylesheet_directory() . '/inc/metaboxes/banniere.php';
require_once get_stylesheet_directory() . '/inc/metaboxes/projet-ditl.php';
require_once get_stylesheet_directory() . '/inc/metaboxes/resultats.php';

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
 * Charge les assets publics des gabarits DiTL sur mesure.
 *
 * Tous les gabarits du registre recoivent la feuille commune (banniere,
 * conteneurs en boite), puis chaque gabarit charge ses assets specifiques.
 */
function ditl_enqueue_assets_gabarits() {
	if ( ! is_page_template( ditl_gabarits_templates() ) ) {
		return;
	}

	wp_enqueue_style(
		'ditl-gabarits-communs',
		get_stylesheet_directory_uri() . '/assets/css/gabarits-communs.css',
		array( 'ditl-style' ),
		DITL_THEME_VERSION
	);

	if ( is_page_template( DITL_TPL_PROJET_DITL ) ) {
		wp_enqueue_style(
			'ditl-gabarit-projet-ditl',
			get_stylesheet_directory_uri() . '/assets/css/gabarit-projet-ditl.css',
			array( 'ditl-gabarits-communs' ),
			DITL_THEME_VERSION
		);
	}

	if ( is_page_template( DITL_TPL_ACTUALITES ) ) {
		wp_enqueue_style(
			'ditl-gabarit-actualites',
			get_stylesheet_directory_uri() . '/assets/css/gabarit-actualites.css',
			array( 'ditl-gabarits-communs' ),
			DITL_THEME_VERSION
		);

		// Carrousel maison, sans dependance, charge en pied de page.
		wp_enqueue_script(
			'ditl-carousel',
			get_stylesheet_directory_uri() . '/assets/js/ditl-carousel.js',
			array(),
			DITL_THEME_VERSION,
			true
		);
	}

	if ( is_page_template( DITL_TPL_RESULTATS ) ) {
		wp_enqueue_style(
			'ditl-gabarit-resultats',
			get_stylesheet_directory_uri() . '/assets/css/gabarit-resultats.css',
			array( 'ditl-gabarits-communs' ),
			DITL_THEME_VERSION
		);
	}
}
add_action( 'wp_enqueue_scripts', 'ditl_enqueue_assets_gabarits' );

/**
 * Retire les scripts Elementor et les assets UPK / Swiper sur les
 * gabarits sur mesure.
 *
 * Les metas Elementor restent en base (sauvegarde dormante), Elementor
 * considere donc la page comme construite avec lui et charge frontend.min.js
 * sans son objet de configuration (rien n'est rendu par lui), ce qui
 * provoque une erreur JavaScript. De meme, les styles et scripts du widget
 * carrousel d'Ultimate Post Kit et de Swiper restent charges alors que le
 * carrousel des gabarits est rendu maison (ditl-carousel). Rien sur ces
 * pages n'en depend : aucune classe upk-* ni swiper-* dans leur rendu.
 */
function ditl_retire_scripts_elementor_gabarit() {
	if ( ! is_page_template( ditl_gabarits_templates() ) ) {
		return;
	}

	wp_dequeue_script( 'elementor-frontend' );
	wp_dequeue_script( 'elementor-frontend-modules' );
	wp_dequeue_script( 'elementor-webpack-runtime' );

	wp_dequeue_script( 'upk-site' );
	wp_dequeue_script( 'upk-alex-carousel' );
	wp_dequeue_script( 'swiper' );

	wp_dequeue_style( 'upk-site' );
	wp_dequeue_style( 'upk-font' );
	wp_dequeue_style( 'upk-alex-carousel' );
	wp_dequeue_style( 'upk-banner' );
	wp_dequeue_style( 'swiper' );
	wp_dequeue_style( 'e-swiper' );
}
// Priorite superieure a celle d'Ultimate Post Kit (99999).
add_action( 'wp_enqueue_scripts', 'ditl_retire_scripts_elementor_gabarit', 100000 );
