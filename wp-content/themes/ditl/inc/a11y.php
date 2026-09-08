<?php
/**
 * Corrections d'accessibilite portees par des hooks sur Astra.
 *
 * Regroupe les ajustements de structure du document que le theme parent
 * n'offre pas en option et qui ne changent aucun pixel (RGAA / WCAG,
 * suite a l'audit Access42 de juillet 2026). Chaque correction est
 * documentee avec le critere vise. Seule exception visible, validee : le
 * titre de la page de resultats de recherche traduit en francais (8.7).
 *
 * Styles et script d'accompagnement : assets/css/a11y.css, assets/js/ditl-menu.js.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Place le titre H1 de la page de resultats de recherche dans <main>.
 *
 * RGAA 9.2 : Astra rend l'en-tete d'archive (section.ast-archive-description
 * et son h1.page-title) via le hook astra_archive_header, appele par
 * search.php AVANT astra_content_loop() qui ouvre la zone <main>. Le titre
 * principal de la page est donc hors de tout landmark. Le rappel du meme
 * callback en tete de la boucle (avec resultats : astra_template_parts_content_top ;
 * sans resultat : astra_template_parts_content_none) le rend au meme
 * endroit visuel, mais a l'interieur de <main> : la section garde ses
 * marges et sa largeur (main n'a ni marge ni remplissage, les marges
 * fusionnent a l'identique). Limite aux recherches : les autres archives
 * ne sont pas dans le perimetre.
 */
function ditl_a11y_recherche_titre_dans_main() {
	if ( ! is_search() || ! function_exists( 'astra_archive_page_info' ) ) {
		return;
	}

	// En disposition "layout-2" (banniere), Astra retire lui-meme le titre
	// d'archive : le rejouer ici produirait un second h1.
	if ( function_exists( 'astra_get_option' ) && 'layout-1' !== astra_get_option( 'section-search-page-title-layout', 'layout-1' ) ) {
		return;
	}

	remove_action( 'astra_archive_header', 'astra_archive_page_info' );

	// Priorite basse : avant le contenu d'Astra (10) et son ouverture de
	// conteneur (25), pour rester le premier enfant de <main>.
	add_action( 'astra_template_parts_content_top', 'astra_archive_page_info', 5 );
	add_action( 'astra_template_parts_content_none', 'astra_archive_page_info', 5 );
}
add_action( 'wp', 'ditl_a11y_recherche_titre_dans_main' );

/**
 * Feuille de style et script des corrections d'accessibilite.
 *
 * Charges sur toutes les pages : le menu principal est present partout et
 * gabarits-communs.css ne l'est que sur les gabarits du registre. Priorite
 * 11 pour succeder a ditl-style (dependance).
 */
function ditl_a11y_enqueue_assets() {
	wp_enqueue_style(
		'ditl-a11y',
		get_stylesheet_directory_uri() . '/assets/css/a11y.css',
		array( 'ditl-style' ),
		DITL_THEME_VERSION
	);

	// Sans dependance, en pied de page : ecoute par delegation, donc
	// indifferent a l'ordre de chargement par rapport au script d'Astra.
	wp_enqueue_script(
		'ditl-menu',
		get_stylesheet_directory_uri() . '/assets/js/ditl-menu.js',
		array(),
		DITL_THEME_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'ditl_a11y_enqueue_assets', 11 );

/**
 * Menu principal : la fleche de sous-menu et les icones sont decoratives.
 *
 * RGAA 6.1 / 7.1 : Astra (astra_dropdown_icon_to_menu_link, priorite 10)
 * insere dans le lien parent un <span role="application" tabindex="0"
 * aria-expanded aria-label="Permutateur de Menu"> : un faux composant
 * interactif lu dans l'intitule du lien, et un svg par sous-item. Le span
 * est conserve pour le glyphe (aucun pixel ne bouge) mais depouille de son
 * role, de son focus et de ses attributs ARIA, puis masque aux technologies
 * d'assistance ; le vrai controle est le <button class="ast-menu-toggle">
 * que rend deja Astra apres le lien (voir a11y.css et ditl-menu.js).
 *
 * @param string   $title Intitule HTML de l'element de menu.
 * @param WP_Post  $item  Element de menu.
 * @param stdClass $args  Arguments de wp_nav_menu().
 * @param int      $depth Profondeur de l'element.
 * @return string
 */
function ditl_a11y_menu_glyphes_decoratifs( $title, $item, $args, $depth ) {
	// Dans la copie mobile du menu (menu_id suffixe "-mobile"), Astra rend le
	// span fleche sans svg : tester les deux marqueurs.
	if ( ! is_string( $title ) || ( false === strpos( $title, 'ast-header-navigation-arrow' ) && false === strpos( $title, 'ast-icon icon-arrow' ) ) ) {
		return $title;
	}

	$fleche = preg_replace(
		'#<span role="[^"]*" class="dropdown-menu-toggle ast-header-navigation-arrow"[^>]*>#',
		'<span class="dropdown-menu-toggle ast-header-navigation-arrow" aria-hidden="true">',
		$title
	);
	if ( null !== $fleche ) {
		$title = $fleche;
	}

	return str_replace(
		'<span class="ast-icon icon-arrow">',
		'<span class="ast-icon icon-arrow" aria-hidden="true">',
		$title
	);
}
add_filter( 'nav_menu_item_title', 'ditl_a11y_menu_glyphes_decoratifs', 11, 4 );

/**
 * Menu principal : retire aria-expanded du lien parent.
 *
 * RGAA 7.1 : Astra pose aria-expanded="false" sur le <a> des elements a
 * sous-menu (priorite 10) alors que l'etat est porte par le bouton qu'il
 * ajoute ensuite (priorite 20). Un lien n'a pas vocation a etre deplie :
 * l'attribut n'est retire que si le bouton est bien present.
 *
 * @param string   $output Markup de l'element de menu.
 * @param WP_Post  $item   Element de menu.
 * @param int      $depth  Profondeur.
 * @param stdClass $args   Arguments de wp_nav_menu().
 * @return string
 */
function ditl_a11y_menu_lien_sans_aria_expanded( $output, $item, $depth, $args ) {
	$classes = empty( $item->classes ) ? array() : (array) $item->classes;
	if ( ! in_array( 'menu-item-has-children', $classes, true ) || ! is_string( $output ) ) {
		return $output;
	}
	if ( false === strpos( $output, 'class="ast-menu-toggle"' ) ) {
		return $output;
	}

	return str_replace( '<a aria-expanded="false"', '<a', $output );
}
add_filter( 'walker_nav_menu_start_el', 'ditl_a11y_menu_lien_sans_aria_expanded', 30, 4 );

/**
 * Menu principal : nom explicite du bouton de sous-menu.
 *
 * RGAA 6.1 : "Permutateur de menu" repete deux fois ne dit pas quel
 * sous-menu s'ouvre. Le nom reprend l'intitule de l'element parent
 * (balises retirees : le selecteur de langue de Polylang contient un
 * drapeau). type="button" ajoute pour la forme.
 *
 * @param array  $attributes Attributs du bouton (class, aria-expanded, aria-label).
 * @param string $context    Contexte astra_attr ("ast-menu-toggle").
 * @param mixed  $item       Element de menu (WP_Post) transmis par Astra.
 * @return array
 */
function ditl_a11y_menu_bouton_sous_menu( $attributes, $context, $item ) {
	if ( ! is_array( $attributes ) || ! is_object( $item ) || empty( $item->title ) ) {
		return $attributes;
	}

	$intitule = trim( wp_strip_all_tags( (string) $item->title ) );
	if ( '' === $intitule ) {
		return $attributes;
	}

	$attributes['type']       = 'button';
	$attributes['aria-label'] = sprintf(
		/* translators: %s: intitule de l'element de menu parent */
		ditl_page_est_francaise() ? 'Sous-menu %s' : __( 'Submenu %s', 'ditl' ),
		$intitule
	);

	return $attributes;
}
add_filter( 'astra_attr_ast-menu-toggle', 'ditl_a11y_menu_bouton_sous_menu', 10, 3 );

/**
 * Pied de page : le menu n'est plus un landmark de navigation.
 *
 * RGAA 9.2 : Astra enveloppe le menu de pied de page d'un <nav> en dur
 * (class-astra-footer-menu-component.php), juge non pertinent par l'audit.
 * role="presentation" retire le landmark, la liste de liens reste. Un
 * attribut ARIA global (aria-label) annulerait ce role d'apres la
 * specification ARIA : il est retire avec.
 *
 * @param array $attributes Attributs du <nav>.
 * @return array
 */
function ditl_a11y_footer_nav_sans_landmark( $attributes ) {
	if ( is_array( $attributes ) && isset( $attributes['id'] ) && 'footer-site-navigation' === $attributes['id'] ) {
		$attributes['role'] = 'presentation';
		unset( $attributes['aria-label'] );
	}

	return $attributes;
}
add_filter( 'astra_attr_site-navigation', 'ditl_a11y_footer_nav_sans_landmark' );

/**
 * Article : remplace la navigation precedent / suivant d'Astra.
 *
 * Sur 'wp' : le theme enfant est charge avant le parent, l'action d'Astra
 * n'existe pas encore au chargement de ce fichier.
 */
function ditl_a11y_navigation_article_remplacer() {
	if ( ! is_single() ) {
		return;
	}

	remove_action( 'astra_entry_after', 'astra_single_post_navigation_markup' );
	add_action( 'astra_entry_after', 'ditl_a11y_navigation_article' );
}
add_action( 'wp', 'ditl_a11y_navigation_article_remplacer' );

/**
 * Article : navigation precedent / suivant en liste, intitules complets.
 *
 * RGAA 6.1 / 9.3 : Astra (astra_single_post_navigation_markup) rend deux
 * <div> et des liens "Article precedent" dont le title reprend le titre de
 * l'article cible sans l'intitule visible. Ici : <ul class="nav-links"> et
 * <li class="nav-previous|nav-next"> (memes classes, meme CSS), lien sans
 * title dont le nom complet est "Article precedent : Titre" via un span
 * screen-reader-text. Les libelles visibles restent ceux d'Astra (traduits
 * dans les cinq langues du site) ; les liens adjacents sont ceux de
 * WordPress (Polylang filtre par langue).
 */
function ditl_a11y_navigation_article() {
	if ( ! is_single() || ! apply_filters( 'astra_single_post_navigation_enabled', true ) ) {
		return;
	}

	$precedent = get_previous_post();
	$suivant   = get_next_post();
	if ( ! ( $precedent instanceof WP_Post ) && ! ( $suivant instanceof WP_Post ) ) {
		return;
	}

	$francais  = ditl_page_est_francaise();
	$objet     = get_post_type_object( (string) get_post_type() );
	$singulier = ( $objet && ! empty( $objet->labels->singular_name ) ) ? $objet->labels->singular_name : '';

	if ( function_exists( 'astra_default_strings' ) ) {
		$libelle_precedent = sprintf( (string) astra_default_strings( 'string-single-navigation-previous', false ), $singulier );
		$libelle_suivant   = sprintf( (string) astra_default_strings( 'string-single-navigation-next', false ), $singulier );
	} else {
		$libelle_precedent = '<span class="ast-left-arrow" aria-hidden="true">&larr;</span> ' . ( $francais ? 'Article précédent' : __( 'Previous post', 'ditl' ) );
		$libelle_suivant   = ( $francais ? 'Article suivant' : __( 'Next post', 'ditl' ) ) . ' <span class="ast-right-arrow" aria-hidden="true">&rarr;</span>';
	}

	$separateur = $francais ? ' : ' : ': ';
	$liens      = array();

	if ( $precedent instanceof WP_Post ) {
		$liens[] = sprintf(
			'<li class="nav-previous"><a href="%1$s" rel="prev">%2$s<span class="screen-reader-text">%3$s</span></a></li>',
			esc_url( get_permalink( $precedent ) ),
			wp_kses_post( $libelle_precedent ),
			esc_html( $separateur . get_the_title( $precedent ) )
		);
	}
	if ( $suivant instanceof WP_Post ) {
		$liens[] = sprintf(
			'<li class="nav-next"><a href="%1$s" rel="next">%2$s<span class="screen-reader-text">%3$s</span></a></li>',
			esc_url( get_permalink( $suivant ) ),
			wp_kses_post( $libelle_suivant ),
			esc_html( $separateur . get_the_title( $suivant ) )
		);
	}

	printf(
		'<nav class="navigation post-navigation" aria-label="%1$s"><ul class="nav-links">%2$s</ul></nav>',
		esc_attr( $francais ? 'Articles' : __( 'Posts', 'astra' ) ),
		implode( '', $liens ) // Deja echappe element par element.
	);
}

/**
 * Pagination des archives et de la recherche : liste et liens explicites.
 *
 * RGAA 6.1 / 9.3 : le markup de paginate_links() (WordPress 7.0) aligne des
 * liens "1", "2" dans un <div class="nav-links">. Chaque lien numerote
 * recoit aria-label="Page N" et le conteneur devient <ul class="nav-links">
 * avec un <li> par element, sans toucher aux blancs entre elements (les
 * .page-numbers sont des inline-block : voir a11y.css). Filtre Astra sur la
 * sortie complete d'astra_number_pagination().
 *
 * @param string $output Markup de la pagination.
 * @return string
 */
function ditl_a11y_pagination_liste( $output ) {
	if ( ! is_string( $output ) || false === strpos( $output, '<div class="nav-links">' ) ) {
		return $output;
	}

	/* translators: %s: numero de page */
	// Chaine du coeur WordPress : traduite dans les 5 langues du site.
	$format = __( 'Page %s' );

	$resultat = preg_replace_callback(
		'#<a class="page-numbers" href="([^"]*)">(\d+)</a>#',
		static function ( $m ) use ( $format ) {
			return sprintf(
				'<a class="page-numbers" href="%1$s" aria-label="%2$s">%3$s</a>',
				$m[1],
				esc_attr( sprintf( $format, $m[2] ) ),
				$m[2]
			);
		},
		$output
	);
	if ( null === $resultat ) {
		return $output;
	}

	$resultat = preg_replace_callback(
		'#<div class="nav-links">(.*?)</div>(\s*</nav>)#s',
		static function ( $m ) {
			$elements = preg_replace_callback(
				'#<a [^>]*>.*?</a>|<span [^>]*>.*?</span>#s',
				static function ( $e ) {
					return '<li>' . $e[0] . '</li>';
				},
				$m[1]
			);
			if ( null === $elements ) {
				return $m[0];
			}
			return '<ul class="nav-links">' . $elements . '</ul>' . $m[2];
		},
		$resultat
	);

	return null === $resultat ? $output : $resultat;
}
add_filter( 'astra_pagination_markup', 'ditl_a11y_pagination_liste' );

/**
 * Recherche : titre de la page de resultats en francais.
 *
 * RGAA 8.7 : l'option Astra "section-search-page-title-custom-title" est
 * unique pour toutes les langues ("Search Results for:"). Seule correction
 * du lot qui change un texte visible, validee : le h1 des resultats FR
 * devient "Resultats de recherche pour :", l'anglais reste inchange. Le
 * <title> de la page (gabarit de recherche Yoast) reste a traiter cote
 * configuration Yoast.
 *
 * @param mixed $valeur Valeur de l'option.
 * @return mixed
 */
function ditl_a11y_titre_recherche_fr( $valeur ) {
	// Front uniquement : en admin (customizer), la valeur EN commune doit
	// rester visible et enregistrable telle quelle.
	if ( ! is_admin() && ditl_page_est_francaise() ) {
		return 'Résultats de recherche pour :';
	}

	return $valeur;
}
add_filter( 'astra_get_option_section-search-page-title-custom-title', 'ditl_a11y_titre_recherche_fr' );
