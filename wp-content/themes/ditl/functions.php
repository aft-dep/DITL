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

define( 'DITL_THEME_VERSION', '0.17.0' );

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

/*
 * Optimisation du chargement (degraissage des assets, preloads).
 */
require_once get_stylesheet_directory() . '/inc/perf.php';

/*
 * Jumeaux WebP des images : coeur de conversion partage (CLI + hooks) et
 * conversion automatique a l'upload. Aucun cout front : hooks medias
 * uniquement (upload, regeneration, suppression).
 */
require_once get_stylesheet_directory() . '/inc/webp.php';

/*
 * Habillage de l'ecran de connexion (wp-login) aux couleurs du site.
 */
require_once get_stylesheet_directory() . '/inc/connexion.php';

/*
 * Corrections d'accessibilite portees par des hooks sur Astra (structure du
 * document), sans effet visuel.
 */
require_once get_stylesheet_directory() . '/inc/a11y.php';

/**
 * Applique au HTML riche des metas le meme traitement que le widget
 * texte d'Elementor (shortcodes puis typographie WordPress), afin de
 * conserver un rendu identique a l'existant (ex. wptexturize transforme
 * un tiret simple entoure d'espaces en tiret demi-cadratin).
 *
 * Filet d'accessibilite (RGAA 8.9 / 9.1) applique au passage : les titres
 * vides (rien, espaces ou &nbsp; seulement, residus de collage depuis un
 * editeur externe) sont retires, ainsi que les attributs data-* (marqueurs
 * de l'editeur d'origine, sans role dans le rendu). Le contenu stocke en
 * base n'est pas modifie.
 *
 * @param string $content HTML riche issu d'une meta de gabarit.
 * @return string HTML pret a etre affiche (a echapper via wp_kses_post).
 */
function ditl_format_rich_text( $content ) {
	$content = ditl_nettoyer_html_colle( $content );
	$content = shortcode_unautop( $content );
	$content = do_shortcode( $content );

	return wptexturize( $content );
}

/**
 * Retire d'un HTML riche les titres vides et les attributs data-*.
 *
 * - Titre vide : <hN ...></hN> dont le contenu ne comporte que des espaces
 *   (y compris &nbsp; sous ses formes entite ou caractere U+00A0) ou des
 *   <br>. Un titre sans texte n'a aucun sens pour la structure du document
 *   et est annonce comme titre vide par les lecteurs d'ecran.
 * - Attribut data-* : jamais porteur d'information pour le visiteur ici
 *   (aucun script du theme n'en lit dans les contenus des metas).
 *
 * Les chaines de remplacement sont fixes (aucune reference arriere), le
 * contenu traite ne peut donc pas etre interprete.
 *
 * @param string $content HTML riche.
 * @return string HTML sans titres vides ni attributs data-*.
 */
function ditl_nettoyer_html_colle( $content ) {
	$content = (string) $content;

	if ( '' === $content || false === strpos( $content, '<' ) ) {
		return $content;
	}

	// Attributs data-* des balises ouvrantes. La balise n'est reecrite que si
	// sa liste d'attributs se decompose entierement en attributs bien formes
	// (nom, puis valeur optionnelle entre guillemets ou nue) : les attributs
	// sont recopies un a un, sauf ceux dont le nom commence par data-. Une
	// balise atypique ne correspond pas au motif et reste intacte.
	// En cas d'echec PCRE (UTF-8 invalide, limite de retour arriere), le
	// contenu est conserve tel quel plutot que vide silencieusement.
	$resultat = preg_replace_callback(
		'/<([a-zA-Z][a-zA-Z0-9:-]*)((?:\s+[^\s=\/>"\']+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+))?)*)\s*(\/?)>/',
		static function ( $balise ) {
			if ( false === stripos( $balise[2], 'data-' ) ) {
				return $balise[0];
			}

			preg_match_all( '/\s+([^\s=\/>"\']+)(\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+))?/', $balise[2], $attributs, PREG_SET_ORDER );

			$conserves = '';

			foreach ( $attributs as $attribut ) {
				if ( 0 === stripos( $attribut[1], 'data-' ) ) {
					continue;
				}

				$conserves .= $attribut[0];
			}

			return '<' . $balise[1] . $conserves . $balise[3] . '>';
		},
		$content
	);
	$content  = null === $resultat ? $content : $resultat;

	// Titres dont le contenu se limite a des blancs, &nbsp; ou <br>.
	$resultat = preg_replace(
		'/<h([1-6])(?:\s[^<>]*)?>(?:\s|&nbsp;|&#160;|&#xA0;|\x{00A0}|<br\s*\/?>)*<\/h\1\s*>/iu',
		'',
		$content
	);

	return null === $resultat ? $content : $resultat;
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
 * Indique si la page rendue est en anglais.
 *
 * Sert a signaler les passages anglais laisses en dur dans les gabarits
 * (RGAA 8.7 : attribut lang sur un changement de langue) : l'attribut n'est
 * emis que si la langue de la page n'est pas deja l'anglais.
 *
 * @return bool True si la locale courante est anglaise.
 */
function ditl_page_est_anglaise() {
	return 0 === strpos( (string) get_locale(), 'en' );
}

/**
 * Attributs d'image completant un alt vide par le titre du media.
 *
 * RGAA 1.1 : une image porteuse d'information (logo de partenaire) doit
 * avoir une alternative. Quand le champ "Texte alternatif" de la mediatheque
 * est vide, le titre de l'attachment sert de repli (meme regle que la
 * galerie du gabarit Projet DiTL). Le tableau retourne est vide si l'alt de
 * la mediatheque est renseigne : wp_get_attachment_image() l'emet alors
 * lui-meme.
 *
 * @param int $attachment_id ID du media.
 * @return array Attributs a passer a wp_get_attachment_image() (alt ou rien).
 */
function ditl_attributs_alt_repli( $attachment_id ) {
	$attachment_id = absint( $attachment_id );
	$alt           = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

	if ( '' !== $alt ) {
		return array();
	}

	return array( 'alt' => trim( wp_strip_all_tags( get_the_title( $attachment_id ) ) ) );
}

/**
 * URL de la feuille de police locale d'une famille utilisee par les gabarits.
 *
 * Les polices Roboto et Jost sont hebergees dans le theme (assets/fonts/,
 * woff2 jeux latin + latin-ext) : plus aucune requete vers
 * fonts.googleapis.com (performance : requete tierce en moins ; RGPD :
 * l'adresse IP du visiteur n'est plus transmise a Google, point releve par
 * la CNIL). Les feuilles reprennent a l'identique les declarations que
 * Google servait (font-display: swap, unicode-range). Seule la graisse 400
 * est embarquee : c'est la seule reellement utilisee par les blocs
 * concernes (intitules Roboto du gabarit Contact a graisse 400 explicite,
 * textes Jost au 400 du corps de page, titres Roboto du gabarit Livrable a
 * 400 explicite ou herite, sans gras ni italique imbriques), la ou
 * Elementor chargeait les 18 variantes de chaque famille.
 *
 * @param string $famille Nom de la famille en minuscules ("roboto", "jost").
 * @return string URL de la feuille de style locale du theme.
 */
function ditl_url_police_locale( $famille ) {
	return get_stylesheet_directory_uri() . '/assets/css/police-' . $famille . '.css';
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
		// (textes), hebergees localement dans le theme. Voir
		// ditl_url_police_locale() pour le choix des graisses.
		wp_enqueue_style( 'ditl-police-roboto', ditl_url_police_locale( 'roboto' ), array(), DITL_THEME_VERSION );
		wp_enqueue_style( 'ditl-police-jost', ditl_url_police_locale( 'jost' ), array(), DITL_THEME_VERSION );
	}

	if ( is_page_template( DITL_TPL_LIVRABLE ) ) {
		wp_enqueue_style(
			'ditl-gabarit-livrable',
			get_stylesheet_directory_uri() . '/assets/css/gabarit-livrable.css',
			array( 'ditl-gabarits-communs' ),
			DITL_THEME_VERSION
		);

		// Police des titres de la page francaise (Roboto), hebergee
		// localement dans le theme.
		wp_enqueue_style( 'ditl-police-roboto', ditl_url_police_locale( 'roboto' ), array(), DITL_THEME_VERSION );

		// Neutralisation de la carte interactive pour les technologies
		// d'assistance (voir le gabarit) : script charge uniquement si une
		// carte est reglee sur la page.
		$ditl_livrable_carte = ditl_get_meta_json( get_queried_object_id(), '_ditl_livrable_carte' );

		if ( ! empty( $ditl_livrable_carte['map_id'] ) ) {
			wp_enqueue_script(
				'ditl-carte',
				get_stylesheet_directory_uri() . '/assets/js/ditl-carte.js',
				array(),
				DITL_THEME_VERSION,
				true
			);
		}
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
