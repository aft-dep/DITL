<?php
/**
 * Corrections d'accessibilite portees par des hooks sur Astra, WPForms et
 * Complianz.
 *
 * Regroupe les ajustements de structure du document que le theme parent et
 * les extensions n'offrent pas en option et qui ne changent aucun pixel
 * (RGAA / WCAG, suite a l'audit Access42 de juillet 2026). Chaque correction est
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

/**
 * Formulaire de contact WPForms : finalite des champs a completer.
 *
 * RGAA 11.13 : le formulaire 6 (Contact, FR et EN) est bati sur des champs
 * generiques de WPForms Lite (texte, nombre, liste) dont la finalite n'est
 * portee que par l'intitule. La table associe, par identifiant de
 * formulaire puis de champ, la valeur autocomplete HTML attendue. Un champ
 * Nombre associe a 'tel' ou 'tel-national' est de plus rendu en
 * type="tel" (voir ditl_a11y_wpforms_tel_fin). Les champs types de WPForms
 * (email, name, phone) sont couverts par leur type, sans passer par la table.
 *
 * @return array Tableau formulaire => ( champ => valeur autocomplete ).
 */
function ditl_a11y_wpforms_champs_autocomplete() {
	$champs = array(
		6 => array(
			7  => 'given-name',
			1  => 'family-name',
			3  => 'email',
			2  => 'tel-national',
			8  => 'organization',
			10 => 'country-name',
		),
	);

	/**
	 * Permet d'ajouter un formulaire (par exemple la version francaise
	 * dupliquee) sans toucher au theme.
	 *
	 * @param array $champs Tableau formulaire => ( champ => valeur autocomplete ).
	 */
	return (array) apply_filters( 'ditl_a11y_wpforms_champs_autocomplete', $champs );
}

/**
 * Valeur autocomplete d'un champ WPForms, ou chaine vide.
 *
 * @param array $field     Reglages du champ.
 * @param array $form_data Reglages du formulaire.
 * @return string
 */
function ditl_a11y_wpforms_autocomplete_champ( $field, $form_data ) {
	if ( ! is_array( $field ) || ! is_array( $form_data ) ) {
		return '';
	}

	$form_id  = isset( $form_data['id'] ) ? (int) $form_data['id'] : 0;
	$field_id = isset( $field['id'] ) ? (int) $field['id'] : -1;
	$champs   = ditl_a11y_wpforms_champs_autocomplete();

	if ( isset( $champs[ $form_id ][ $field_id ] ) && is_string( $champs[ $form_id ][ $field_id ] ) ) {
		return $champs[ $form_id ][ $field_id ];
	}

	$type = isset( $field['type'] ) ? (string) $field['type'] : '';
	if ( 'email' === $type ) {
		return 'email';
	}
	if ( 'phone' === $type ) {
		return 'tel';
	}

	return '';
}

/**
 * Indique si une valeur autocomplete designe un numero de telephone.
 *
 * @param string $autocomplete Valeur autocomplete.
 * @return bool
 */
function ditl_a11y_wpforms_est_telephone( $autocomplete ) {
	return in_array( $autocomplete, array( 'tel', 'tel-national' ), true );
}

/**
 * Pose l'attribut autocomplete sur les champs du formulaire.
 *
 * Priorite 20 : apres les proprietes propres a chaque type de champ
 * (filtre wpforms_field_properties_{type}, puis Number::field_properties qui
 * ajoute step="any"), avant le rendu.
 *
 * @param array $properties Proprietes du champ (attributs des inputs).
 * @param array $field      Reglages du champ.
 * @param array $form_data  Reglages du formulaire.
 * @return array
 */
function ditl_a11y_wpforms_autocomplete( $properties, $field, $form_data ) {
	if ( ! is_array( $properties ) || ! is_array( $field ) || ! is_array( $form_data ) ) {
		return $properties;
	}

	$type = isset( $field['type'] ) ? (string) $field['type'] : '';

	// Champ Nom de WPForms : un sous-champ par partie du nom.
	if ( 'name' === $type ) {
		$parties = array(
			'primary' => 'name',
			'first'   => 'given-name',
			'middle'  => 'additional-name',
			'last'    => 'family-name',
		);
		foreach ( $parties as $cle => $valeur ) {
			if ( isset( $properties['inputs'][ $cle ] ) && is_array( $properties['inputs'][ $cle ] ) ) {
				$properties['inputs'][ $cle ]['attr']['autocomplete'] = $valeur;
			}
		}

		return $properties;
	}

	$autocomplete = ditl_a11y_wpforms_autocomplete_champ( $field, $form_data );
	if ( '' === $autocomplete ) {
		return $properties;
	}

	// Liste deroulante : les attributs du <select> sont ceux du conteneur.
	if ( 'select' === $type ) {
		if ( isset( $properties['input_container'] ) && is_array( $properties['input_container'] ) ) {
			$properties['input_container']['attr']['autocomplete'] = $autocomplete;
		}

		return $properties;
	}

	if ( ! isset( $properties['inputs']['primary'] ) || ! is_array( $properties['inputs']['primary'] ) ) {
		return $properties;
	}

	$properties['inputs']['primary']['attr']['autocomplete'] = $autocomplete;

	// Champ Nombre rendu en telephone : step="any" ferait lever une exception
	// a jQuery Validate sur un type="tel" ("Step attribute on input type tel
	// is not supported"), et n'a pas de sens pour un numero.
	if ( 'number' === $type && ditl_a11y_wpforms_est_telephone( $autocomplete ) ) {
		// jQuery Validate applique la regle "step" au champ rendu en tel et
		// la fait echouer (valeur non numerique) : l'attribut est retire.
		unset( $properties['inputs']['primary']['attr']['step'] );

		// WPForms ne borne pas la longueur d'un champ Nombre : un numero de
		// telephone tient largement dans 20 caracteres.
		$properties['inputs']['primary']['attr']['maxlength'] = 20;
	}

	return $properties;
}
add_filter( 'wpforms_field_properties', 'ditl_a11y_wpforms_autocomplete', 20, 3 );

/**
 * Indique si le champ est un champ Nombre de WPForms employe comme telephone.
 *
 * @param array $field     Reglages du champ.
 * @param array $form_data Reglages du formulaire.
 * @return bool
 */
function ditl_a11y_wpforms_champ_nombre_telephone( $field, $form_data ) {
	return is_array( $field )
		&& isset( $field['type'] )
		&& 'number' === $field['type']
		&& ditl_a11y_wpforms_est_telephone( ditl_a11y_wpforms_autocomplete_champ( $field, $form_data ) );
}

/**
 * Champ Nombre employe comme telephone : ouvre la capture de son rendu.
 *
 * RGAA 11.13 : WPForms Lite n'a pas de champ Telephone et le champ Nombre
 * ecrit type="number" en dur (class-number.php, field_display), sans filtre
 * sur son markup et sans pouvoir le surcharger par les proprietes (un second
 * attribut type serait ignore par le navigateur). Le rendu du champ est donc
 * capture entre les deux actions qui l'encadrent, puis type="number" devient
 * type="tel". Priorite 99 : apres l'intitule et la description (20).
 *
 * @param array $field     Reglages du champ.
 * @param array $form_data Reglages du formulaire.
 */
function ditl_a11y_wpforms_tel_debut( $field, $form_data ) {
	if ( ditl_a11y_wpforms_champ_nombre_telephone( $field, $form_data ) ) {
		ditl_a11y_wpforms_tel_capture( true );
		ob_start();
	}
}

/**
 * Drapeau de capture du rendu du champ telephone.
 *
 * Seule la fermeture d'une capture reellement ouverte est autorisee : le
 * tampon d'un tiers ne peut pas etre vide par erreur si la condition
 * d'ouverture et celle de fermeture divergeaient.
 *
 * @param bool|null $etat True pour lever le drapeau, false pour le baisser,
 *                        null pour le lire.
 * @return bool
 */
function ditl_a11y_wpforms_tel_capture( $etat = null ) {
	static $ouverte = false;

	if ( null !== $etat ) {
		$ouverte = (bool) $etat;
	}

	return $ouverte;
}
add_action( 'wpforms_display_field_before', 'ditl_a11y_wpforms_tel_debut', 99, 2 );

/**
 * Champ Nombre employe comme telephone : restitue le rendu en type="tel".
 *
 * Priorite 1 : avant le message d'erreur (3) et la fermeture du conteneur.
 * Meme CSS des deux cotes (Astra et WPForms stylent number et tel dans les
 * memes regles ; les fleches du champ Nombre n'apparaissent qu'au survol).
 * Cote serveur, le champ reste un champ Nombre : WPForms retire tout
 * caractere hors chiffres, du point et du signe moins, puis exige un
 * nombre. Un numero saisi avec espaces, parentheses ou indicatif est donc
 * accepte (et stocke sans mise en forme), mais un numero saisi avec des
 * points (01.23.45.67.89) est refuse a l'envoi : le message de refus est
 * traduit ci-dessous. Correction de fond a arbitrer avec le client :
 * passer ce champ en type Texte dans le formulaire, ce qui rendrait cette
 * capture inutile.
 *
 * @param array $field     Reglages du champ.
 * @param array $form_data Reglages du formulaire.
 */
function ditl_a11y_wpforms_tel_fin( $field, $form_data ) {
	if ( ! ditl_a11y_wpforms_champ_nombre_telephone( $field, $form_data ) ) {
		return;
	}

	if ( ! ditl_a11y_wpforms_tel_capture() ) {
		return;
	}

	ditl_a11y_wpforms_tel_capture( false );

	$html = ob_get_clean();
	if ( false === $html ) {
		return;
	}

	// Markup produit et echappe par WPForms, seul le type change.
	echo str_replace( '<input type="number" ', '<input type="tel" ', $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wpforms_display_field_after', 'ditl_a11y_wpforms_tel_fin', 1, 2 );

/**
 * Champ telephone : message de refus du serveur en francais.
 *
 * Le champ reste un champ Nombre cote serveur : une saisie que sa
 * validation rejette (points de separation, par exemple) produit le
 * message anglais du plugin quand la traduction fr_FR n'est pas installee
 * (wp-content/languages n'est pas versionne). Le message est traduit ici
 * et rendu explicite sur le format attendu.
 *
 * @param string $label Message du plugin.
 * @return string
 */
function ditl_a11y_wpforms_tel_message_serveur( $label ) {
	if ( is_admin() || ! ditl_page_est_francaise() ) {
		return $label;
	}

	return 'Veuillez saisir un numéro sans point de séparation, par exemple 01 23 45 67 89.';
}
add_filter( 'wpforms_valid_number_label', 'ditl_a11y_wpforms_tel_message_serveur' );

/**
 * Asterisque des champs obligatoires : explicite pour les lecteurs d'ecran.
 *
 * RGAA 11.10 : WPForms rend <span class="wpforms-required-label">*</span>
 * sans explication. L'etoile reste affichee a l'identique mais est masquee
 * aux technologies d'assistance, remplacee par " (obligatoire)" en
 * screen-reader-text (classe d'Astra et du coeur : aucun pixel). Priorite 11
 * pour passer apres le moteur "moderne" de WPForms, qui pose sa propre
 * version a 10. Front uniquement.
 *
 * @param string $label_html Markup de l'asterisque.
 * @return string
 */
function ditl_a11y_wpforms_asterisque( $label_html ) {
	if ( is_admin() ) {
		return $label_html;
	}

	$mention = ditl_page_est_francaise() ? 'obligatoire' : __( 'required', 'ditl' );

	return ' <span class="wpforms-required-label" aria-hidden="true">*</span><span class="screen-reader-text"> (' . esc_html( $mention ) . ')</span>';
}
add_filter( 'wpforms_get_field_required_label', 'ditl_a11y_wpforms_asterisque', 11 );

/**
 * Messages de validation du formulaire : francais et exemple de saisie.
 *
 * RGAA 11.11 / 11.10 : les messages de jQuery Validate sont transmis par
 * WPForms dans wpforms_settings (wp_localize_script). Sur les pages
 * francaises, ils sont poses ici en francais quelle que soit la traduction
 * installee (wp-content/languages/ n'est pas versionne : la traduction fr_FR
 * de WPForms presente en local n'est pas garantie sur les autres
 * environnements) ; le message de l'e-mail donne un exemple de format. Sur
 * les pages anglaises, seul le message de l'e-mail change (exemple ajoute).
 * Aucun texte n'est visible tant qu'aucune erreur n'est commise. Seules les
 * cles deja presentes sont remplacees.
 *
 * @param array $strings Chaines transmises au script front de WPForms.
 * @return array
 */
function ditl_a11y_wpforms_messages( $strings ) {
	if ( ! is_array( $strings ) ) {
		return $strings;
	}

	if ( ditl_page_est_francaise() ) {
		$francais = array(
			'val_required'               => 'Ce champ est obligatoire.',
			'val_email'                  => 'Veuillez saisir une adresse e-mail valide, par exemple prenom.nom@exemple.fr',
			'val_email_suggestion'       => 'Vouliez-vous dire {suggestion} ?',
			'val_email_suggestion_title' => 'Cliquez pour accepter cette suggestion.',
			'val_email_restricted'       => 'Cette adresse e-mail n’est pas autorisée.',
			'val_number'                 => 'Veuillez saisir un nombre valide.',
			'val_number_positive'        => 'Veuillez saisir un nombre positif valide.',
			'val_minimum_price'          => 'Le montant saisi est inférieur au minimum requis.',
			'val_confirm'                => 'Les valeurs des deux champs ne correspondent pas.',
			'val_checklimit'             => 'Vous avez dépassé le nombre de sélections autorisées : {#}.',
			'val_limit_characters'       => '{count} caractères sur {limit} maximum.',
			'val_limit_words'            => '{count} mots sur {limit} maximum.',
			'val_min'                    => 'Veuillez saisir une valeur supérieure ou égale à {0}.',
			'val_max'                    => 'Veuillez saisir une valeur inférieure ou égale à {0}.',
			// Cles des champs de la version Pro : sans effet ici (WPForms
			// Lite ne les emet pas), posees pour rester couvert si le
			// client passe un jour a la version payante.
			'val_phone'                  => 'Veuillez saisir un numéro de téléphone valide, par exemple 01 23 45 67 89.',
			'val_url'                    => 'Veuillez saisir une adresse web valide, par exemple https://www.exemple.fr',
			'val_fileextension'          => 'Ce type de fichier n’est pas autorisé.',
			'val_filesize'               => 'Ce fichier dépasse la taille maximale autorisée.',
			'val_time12h'                => 'Veuillez saisir une heure au format 12 heures, par exemple 09:30 am.',
			'val_time24h'                => 'Veuillez saisir une heure au format 24 heures, par exemple 21:30.',
			'val_time_limit'             => 'Veuillez saisir une heure comprise entre {minTime} et {maxTime}.',
			'val_password_strength'      => 'Veuillez saisir un mot de passe plus robuste.',
			'val_recaptcha_fail_msg'     => 'La vérification Google reCAPTCHA a échoué, veuillez réessayer plus tard.',
			'val_turnstile_fail_msg'     => 'La vérification Cloudflare Turnstile a échoué, veuillez réessayer plus tard.',
			'val_inputmask_incomplete'   => 'Veuillez remplir le champ au format attendu.',
			'val_requiredpayment'        => 'Le paiement est obligatoire.',
			'val_creditcard'             => 'Veuillez saisir un numéro de carte bancaire valide.',
		);
		foreach ( $francais as $cle => $message ) {
			if ( isset( $strings[ $cle ] ) ) {
				$strings[ $cle ] = $message;
			}
		}

		return $strings;
	}

	if ( ditl_page_est_anglaise() && isset( $strings['val_email'] ) ) {
		$strings['val_email'] = 'Please enter a valid email address, for example first.last@example.com';
	}

	return $strings;
}
add_filter( 'wpforms_frontend_strings', 'ditl_a11y_wpforms_messages', 20 );

/**
 * Bandeau Complianz : retire l'attribut de presentation size="40".
 *
 * RGAA 10.1 : le gabarit du bandeau (cookiebanner/templates/cookiebanner.php)
 * pose size="40" sur les quatre cases a cocher des categories, attribut de
 * presentation sans effet sur une case a cocher (sa taille vient du CSS du
 * bandeau). Retire au rendu, blancs compris, sur le HTML complet du bandeau.
 *
 * @param mixed $html Markup du bandeau.
 * @return mixed
 */
function ditl_a11y_complianz_sans_size( $html ) {
	if ( ! is_string( $html ) ) {
		return $html;
	}

	$resultat = preg_replace( '#\s+size="40"#', '', $html );

	return null === $resultat ? $html : $resultat;
}
add_filter( 'cmplz_banner_html', 'ditl_a11y_complianz_sans_size' );
