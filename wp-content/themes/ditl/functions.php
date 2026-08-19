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

define( 'DITL_THEME_VERSION', '0.13.0' );

/*
 * Metaboxes des gabarits sur mesure (remplacement progressif d'Elementor).
 */
require_once get_stylesheet_directory() . '/inc/metaboxes/helpers.php';
require_once get_stylesheet_directory() . '/inc/metaboxes/banniere.php';
require_once get_stylesheet_directory() . '/inc/metaboxes/projet-ditl.php';
require_once get_stylesheet_directory() . '/inc/metaboxes/resultats.php';
require_once get_stylesheet_directory() . '/inc/metaboxes/accueil.php';
require_once get_stylesheet_directory() . '/inc/metaboxes/partenaires.php';
require_once get_stylesheet_directory() . '/inc/metaboxes/contact.php';
require_once get_stylesheet_directory() . '/inc/metaboxes/livrable.php';

/*
 * Ajustements SEO (iso-SEO du <head> apres activation de Yoast).
 */
require_once get_stylesheet_directory() . '/inc/seo.php';

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
 * Convertit une URL stockee en meta en URL de lien prete pour le rendu.
 *
 * Les URLs internes sont stockees RELATIVES en meta (portables entre les
 * environnements local, preprod et prod) : elles sont prefixees par l'URL
 * du site au rendu. Les URLs externes (absolues) sont laissees intactes.
 *
 * @param mixed $url URL issue d'une meta de gabarit.
 * @return string URL prete pour un attribut href (a echapper via esc_url).
 */
function ditl_href_from_meta_url( $url ) {
	$url = is_string( $url ) ? $url : '';

	if ( '' !== $url && 0 === strpos( $url, '/' ) ) {
		return home_url( $url );
	}

	return $url;
}

/**
 * Requete des dernieres actualites affichees par les gabarits.
 *
 * Requete partagee entre les gabarits Actualites (carrousel) et Accueil
 * (bloc actualites) : les 6 derniers articles publies ; Polylang limite
 * la requete a la langue courante de la page.
 *
 * @return WP_Query Les 6 derniers articles publies de la langue courante.
 */
function ditl_query_dernieres_actus() {
	return new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 6,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
}

/**
 * Indique si la page rendue est en francais.
 *
 * Centralise le test de langue des gabarits (libelles et variantes de
 * structure FR/EN, site multilingue sans fichiers de traduction du theme).
 * NB : cette logique sera revisitee en phase 2 (fichiers .po) ; ce helper
 * centralise seulement le point de decision.
 *
 * @return bool True si la locale courante est francaise.
 */
function ditl_page_est_francaise() {
	return 0 === strpos( (string) get_locale(), 'fr' );
}

/**
 * URL Google Fonts d'une famille utilisee par les gabarits.
 *
 * Meme source et meme forme d'URL que les feuilles de polices qu'Elementor
 * chargeait avant sa desactivation (iso-comportement, donc acceptable
 * aujourd'hui ; l'hebergement local des polices reste a arbitrer en
 * phase 1/2, pour la performance ET pour le RGPD : l'appel a
 * fonts.googleapis.com transmet l'adresse IP du visiteur a Google,
 * point releve par la CNIL). Seule la
 * graisse 400 est demandee : c'est la seule reellement utilisee par les
 * blocs concernes (intitules Roboto du gabarit Contact a graisse 400
 * explicite, textes Jost au 400 du corps de page, titres Roboto du gabarit
 * Livrable a 400 explicite ou herite, sans gras ni italique imbriques),
 * la ou Elementor chargeait les 18 variantes de chaque famille.
 *
 * @param string $famille Nom de la famille (ex. "Roboto", "Jost").
 * @return string URL de la feuille de style Google Fonts.
 */
function ditl_url_google_font( $famille ) {
	return 'https://fonts.googleapis.com/css?family=' . rawurlencode( $famille ) . ':400&display=swap';
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

	if ( is_page_template( DITL_TPL_PARTENAIRES ) ) {
		wp_enqueue_style(
			'ditl-gabarit-partenaires',
			get_stylesheet_directory_uri() . '/assets/css/gabarit-partenaires.css',
			array( 'ditl-gabarits-communs' ),
			DITL_THEME_VERSION
		);
	}

	if ( is_page_template( DITL_TPL_CONTACT ) ) {
		wp_enqueue_style(
			'ditl-gabarit-contact',
			get_stylesheet_directory_uri() . '/assets/css/gabarit-contact.css',
			array( 'ditl-gabarits-communs' ),
			DITL_THEME_VERSION
		);

		// Polices des blocs de coordonnees : Roboto (intitules) et Jost
		// (textes), auparavant chargees par les feuilles de polices
		// d'Elementor. Voir ditl_url_google_font() pour le choix des
		// graisses.
		wp_enqueue_style( 'ditl-police-roboto', ditl_url_google_font( 'Roboto' ), array(), null );
		wp_enqueue_style( 'ditl-police-jost', ditl_url_google_font( 'Jost' ), array(), null );
	}

	if ( is_page_template( DITL_TPL_LIVRABLE ) ) {
		wp_enqueue_style(
			'ditl-gabarit-livrable',
			get_stylesheet_directory_uri() . '/assets/css/gabarit-livrable.css',
			array( 'ditl-gabarits-communs' ),
			DITL_THEME_VERSION
		);

		// Police des titres de la page francaise (Roboto), auparavant
		// chargee par les feuilles de polices d'Elementor.
		wp_enqueue_style( 'ditl-police-roboto', ditl_url_google_font( 'Roboto' ), array(), null );
	}

	if ( is_page_template( DITL_TPL_ACCUEIL ) ) {
		wp_enqueue_style(
			'ditl-gabarit-accueil',
			get_stylesheet_directory_uri() . '/assets/css/gabarit-accueil.css',
			array( 'ditl-gabarits-communs' ),
			DITL_THEME_VERSION
		);

		// Carrousel maison uniquement si le bloc Partenaires est regle en
		// carrousel (page anglaise) ; la grille statique n'en a pas besoin.
		$ditl_accueil_partenaires = ditl_get_meta_json( get_queried_object_id(), '_ditl_accueil_partenaires' );

		if ( ! empty( $ditl_accueil_partenaires['carrousel'] ) && ! empty( $ditl_accueil_partenaires['logo_ids'] ) ) {
			wp_enqueue_script(
				'ditl-carousel',
				get_stylesheet_directory_uri() . '/assets/js/ditl-carousel.js',
				array(),
				DITL_THEME_VERSION,
				true
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'ditl_enqueue_assets_gabarits' );

/**
 * Charge la feuille des articles migres depuis Elementor.
 *
 * Seuls les articles convertis en post_content classique utilisent des
 * classes ditl-art-* : la feuille n'est chargee que si le contenu en
 * porte une. Le test se fait sur le contenu deja en memoire (aucune
 * requete supplementaire) et laisse le HTML des articles classiques
 * strictement identique (pas de balise link inutile).
 */
function ditl_enqueue_assets_articles() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$ditl_post = get_queried_object();

	if ( ! $ditl_post instanceof WP_Post || false === strpos( $ditl_post->post_content, 'ditl-art-' ) ) {
		return;
	}

	wp_enqueue_style(
		'ditl-gabarit-article',
		get_stylesheet_directory_uri() . '/assets/css/gabarit-article.css',
		array( 'ditl-style' ),
		DITL_THEME_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'ditl_enqueue_assets_articles' );

/**
 * Retire le chargement paresseux de l'image du logo du site (header).
 *
 * Le logo du header est au-dessus de la ligne de flottaison sur toutes les
 * pages : son chargement paresseux penalise le LCP. Symptome constate : le
 * loading="lazy" observe sur le logo venait du module d'optimisation des
 * images d'Elementor (option elementor_optimized_image_loading, active par
 * defaut), qui reecrit les balises img du header dans un tampon de sortie ;
 * il disparait avec la desactivation d'Elementor. Ce filtre garantit
 * ensuite que le logo reste charge immediatement quel que soit le chemin
 * de rendu : get_custom_logo() omet deja l'attribut, mais les variantes de
 * logo d'Astra (header mobile, header transparent) passent par
 * wp_get_attachment_image() sans cette protection, et le chargement
 * paresseux natif de WordPress redevient actif sans Elementor.
 *
 * Cible uniquement l'attachement declare comme logo du site (les autres
 * images gardent leur chargement paresseux). La cle est retiree du tableau
 * (un false y deviendrait loading="" a l'echappement) : aucun impact
 * visuel, seul l'attribut disparait.
 *
 * @param array   $attributs  Attributs de l'image.
 * @param WP_Post $attachment Attachement en cours de rendu.
 * @return array Attributs, sans chargement paresseux pour le logo.
 */
function ditl_logo_sans_lazy( $attributs, $attachment ) {
	$ditl_logo_id = (int) get_theme_mod( 'custom_logo' );

	if ( $ditl_logo_id > 0 && $attachment instanceof WP_Post && $ditl_logo_id === (int) $attachment->ID ) {
		unset( $attributs['loading'] );
	}

	return $attributs;
}
add_filter( 'wp_get_attachment_image_attributes', 'ditl_logo_sans_lazy', 20, 2 );

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
 *
 * NOTE (desactivation d'Elementor, phase 1) : une fois Elementor et ses
 * addons desactives par cli/desactiver-elementor.php, ces dequeues ne
 * trouvent plus rien a retirer et deviennent sans effet. Ils sont conserves
 * volontairement pour proteger le cas ou le theme serait deploye avant la
 * desactivation des extensions (production) ; a retirer en phase 2 avec la
 * purge des metas Elementor.
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
	wp_dequeue_style( 'upk-buzz-list' );
	wp_dequeue_style( 'upk-banner' );
	wp_dequeue_style( 'swiper' );
	wp_dequeue_style( 'e-swiper' );
}
// Priorite superieure a celle d'Ultimate Post Kit (99999).
add_action( 'wp_enqueue_scripts', 'ditl_retire_scripts_elementor_gabarit', 100000 );
