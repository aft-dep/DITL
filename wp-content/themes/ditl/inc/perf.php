<?php
/**
 * Optimisation du chargement (phase 1 de la refonte).
 *
 * Degraissage des assets charges sur le front : chaque retrait ci-dessous a
 * ete PROUVE sur les pages temoins (/, /fr/accueil/, /fr/contact/, /news/)
 * avant d'etre code - inventaire des handles, recherche des consommateurs
 * reels dans le HTML rendu, diff avant/apres du <head>.
 *
 * Ce fichier ne retire QUE ce qui est demontre inutile :
 * - emojis WordPress (aucun emoji dans les contenus, remplacement client
 *   twemoji superflu) ;
 * - jquery-migrate (aucun script charge n'utilise d'API jQuery supprimee :
 *   seul consommateur jQuery, ivory-search.min.js n'emploie que .click(),
 *   toujours present en jQuery 3.7 ; jquery.validate 1.21 et WPForms 1.9.9
 *   supportent jQuery 3 sans migrate) ;
 * - feuille commune des blocs wp-block-library (aucun consommateur : le
 *   body n'utilise ni .wp-element-button, ni classes .has-*, ni variables
 *   --wp--preset-- ; les feuilles PAR BLOC reellement consommees
 *   (wp-block-image, wp-block-list, wp-block-paragraph) et global-styles
 *   (marge body, text-decoration des liens) sont CONSERVEES) ;
 * - script Site Kit de suivi des formulaires WPForms sur les pages sans
 *   formulaire (aucun hook natif de Site Kit ne le permet : dequeue cible).
 *
 * Complements de chargement :
 * - preconnect vers googletagmanager.com (seule origine tierce restante
 *   apres l'hebergement local des polices) ;
 * - preload de l'image de banniere des gabarits DiTL (image LCP).
 *
 * CONSERVES volontairement (decisions documentees dans le rapport du
 * chantier) : global-styles, feuilles par bloc, prefetch speculatif du
 * coeur (bloc speculationrules, benefique), CSS dynamique Astra inline
 * (la generation en fichier appartient a Astra Pro via Astra_Cache,
 * absente du theme gratuit ; gzip le compresse bien).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Desactive les emojis WordPress (front, admin, flux et mails).
 *
 * Aucun contenu du site n'utilise d'emoji : le detecteur (JSON de reglages
 * + script module en pied de page, environ 3 Ko) et sa feuille de style
 * inline (340 octets) ne servent a rien. Le filtre emoji_svg_url coupe
 * aussi le dns-prefetch vers s.w.org. Retrait classique complet, identique
 * a celui que propose l'option dediee de WP Fastest Cache (non activee :
 * le retrait est porte par le theme pour rester actif quel que soit le
 * plugin de cache).
 */
function ditl_desactiver_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'ditl_desactiver_emojis' );

/**
 * Retire jquery-migrate des dependances de jQuery sur le front.
 *
 * jQuery Migrate 3.4.1 (7,7 Ko) ne retablit que des APIs supprimees de
 * jQuery 3.x. Inventaire des scripts charges sur le front : le seul
 * consommateur de jQuery est ivory-search.min.js (recherche du header),
 * qui n'emploie que l'alias .click(), toujours fonctionnel en jQuery
 * 3.7.1 ; sur le gabarit Contact s'ajoutent jquery.validate 1.21 et
 * WPForms 1.9.9, tous deux compatibles jQuery 3 sans migrate. La recherche
 * du header (ouverture du champ + soumission) a ete testee apres retrait.
 *
 * L'admin n'est pas touche (les extensions y chargent leurs propres
 * scripts, hors perimetre).
 *
 * @param WP_Scripts $scripts Registre des scripts en cours d'initialisation.
 */
function ditl_retirer_jquery_migrate( $scripts ) {
	if ( is_admin() ) {
		return;
	}

	if ( isset( $scripts->registered['jquery'] ) && $scripts->registered['jquery'] instanceof _WP_Dependency ) {
		$scripts->registered['jquery']->deps = array_values(
			array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) )
		);
	}
}
add_action( 'wp_default_scripts', 'ditl_retirer_jquery_migrate' );

/**
 * Retire la feuille commune des blocs (wp-block-library), sans consommateur.
 *
 * Astra charge les assets de blocs SEPAREMENT (une petite feuille inline
 * par bloc reellement present : wp-block-image, wp-block-list,
 * wp-block-paragraph - toutes trois consommees par les contenus migres et
 * les widgets de pied de page, donc conservees). La feuille commune
 * wp-block-library (3,6 Ko inline par page) ne contient que des regles
 * sans consommateur dans le HTML rendu : .wp-element-button, classes
 * .has-* (couleurs et degrades de la palette), variables d'admin et
 * tailles --wp--preset--font-size-* (utilisees nulle part, ni dans les
 * body, ni dans les CSS du theme). global-styles est CONSERVEE : ses
 * regles hors presets s'appliquent reellement (marge du body,
 * text-decoration des liens, layouts). classic-theme-styles n'est pas
 * enqueue sur ce site (rien a retirer).
 */
function ditl_retirer_css_blocs_sans_consommateur() {
	wp_dequeue_style( 'wp-block-library' );
}
add_action( 'wp_enqueue_scripts', 'ditl_retirer_css_blocs_sans_consommateur', 100 );

/**
 * Retire le script Site Kit de suivi WPForms sur les pages sans formulaire.
 *
 * Site Kit enqueue googlesitekit-events-provider-wpforms sur TOUTES les
 * pages des que WPForms est actif (Conversion_Tracking::maybe_enqueue_scripts,
 * priorite 30), alors que le script ne fait rien sans formulaire dans la
 * page. Aucun filtre natif de Site Kit ne permet de le conditionner :
 * dequeue cible, avec double garde pour ne jamais perdre le suivi des
 * conversions (evenement submit_lead_form) :
 * - le gabarit Contact (seules pages a formulaire aujourd'hui) le garde
 *   toujours ;
 * - toute page ou WPForms enqueue ses propres assets le garde aussi
 *   (protege un futur formulaire insere hors gabarit Contact).
 *
 * LIMITE CONNUE : la garde wp_script_is ne voit pas un formulaire dont
 * WPForms n'enqueue les assets qu'au rendu (apres priorite 60), par exemple
 * en widget. Si un formulaire est un jour ajoute hors gabarit et hors
 * contenu de page, verifier que son suivi Site Kit remonte toujours.
 */
function ditl_retirer_sitekit_wpforms_sans_formulaire() {
	if ( defined( 'DITL_TPL_CONTACT' ) && is_page_template( DITL_TPL_CONTACT ) ) {
		return;
	}

	if ( wp_script_is( 'wpforms', 'enqueued' ) ) {
		return;
	}

	wp_dequeue_script( 'googlesitekit-events-provider-wpforms' );
}
add_action( 'wp_enqueue_scripts', 'ditl_retirer_sitekit_wpforms_sans_formulaire', 60 );

/**
 * Ajoute un preconnect vers googletagmanager.com.
 *
 * Apres l'hebergement local des polices (Exo via Astra, Roboto et Jost via
 * le theme), gtag.js est la seule ressource tierce restante : le preconnect
 * (DNS + TCP + TLS anticipes) complete le dns-prefetch que Site Kit emet
 * deja, pour les navigateurs qui le supportent.
 *
 * @param array  $urls     URLs des indications de ressources.
 * @param string $relation Type d'indication (dns-prefetch, preconnect...).
 * @return array URLs completees.
 */
function ditl_preconnect_gtag( $urls, $relation ) {
	if ( 'preconnect' === $relation && ! is_admin() ) {
		$urls[] = 'https://www.googletagmanager.com';
	}

	return $urls;
}
add_filter( 'wp_resource_hints', 'ditl_preconnect_gtag', 10, 2 );

/**
 * Precharge l'image de banniere des gabarits DiTL (image LCP).
 *
 * Sur les pages migrees, la banniere (template-parts/gabarit-hero.php) est
 * l'element LCP : un preload dans le <head> lance son telechargement avant
 * que le parseur n'atteigne le body. Le srcset et le sizes du preload
 * reprennent exactement ceux de la balise img rendue par
 * wp_get_attachment_image (meme taille "full") : le navigateur reutilise
 * la ressource prechargee, aucun double telechargement.
 *
 * Priorite 2 : avant l'impression des feuilles de style (wp_head prio 8),
 * pour que le preload parte le plus tot possible.
 */
function ditl_preload_banniere_gabarit() {
	if ( ! function_exists( 'ditl_gabarits_templates' ) || ! is_page_template( ditl_gabarits_templates() ) ) {
		return;
	}

	$ditl_hero_id = absint( get_post_meta( get_queried_object_id(), '_ditl_hero_image_id', true ) );

	if ( $ditl_hero_id <= 0 ) {
		return;
	}

	$ditl_src = wp_get_attachment_image_url( $ditl_hero_id, 'full' );

	if ( ! $ditl_src ) {
		return;
	}

	$ditl_srcset = wp_get_attachment_image_srcset( $ditl_hero_id, 'full' );
	$ditl_sizes  = $ditl_srcset ? wp_get_attachment_image_sizes( $ditl_hero_id, 'full' ) : false;

	$ditl_preload = '<link rel="preload" as="image" href="' . esc_url( $ditl_src ) . '"';

	if ( $ditl_srcset && $ditl_sizes ) {
		$ditl_preload .= ' imagesrcset="' . esc_attr( $ditl_srcset ) . '" imagesizes="' . esc_attr( $ditl_sizes ) . '"';
	}

	echo $ditl_preload . ">\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Balise construite ci-dessus, attributs echappes individuellement.
}
add_action( 'wp_head', 'ditl_preload_banniere_gabarit', 2 );
