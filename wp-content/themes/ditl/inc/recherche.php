<?php
/**
 * Recherche de l'en-tete : composant natif du theme.
 *
 * Remplace l'extension Ivory Search (add-search-to-menu), qui inserait dans
 * le menu principal un <a role="button"> sans etat, un formulaire anglais
 * sans etiquette et un <div> de fermeture non focalisable, le tout pilote en
 * jQuery. Le composant rendu ici est un motif ARIA Disclosure complet :
 * conteneur role="search", bouton d'ouverture avec aria-expanded et
 * aria-controls, formulaire natif get_search_form() avec etiquette associee,
 * bouton de fermeture reel, langue alignee sur celle de la page.
 *
 * Criteres RGAA leves : 7.1 (script compatible avec les technologies
 * d'assistance), 7.3 (fermeture au clavier), 11.1 (champ etiquete),
 * 11.9 (intitule de bouton pertinent), 12.6 (zone de regroupement
 * atteignable).
 *
 * Rendu iso-design : la geometrie, les couleurs et l'animation d'Ivory Search
 * sont reproduites a l'identique dans assets/css/recherche.css (mesures CDP
 * du 08/09/2026). Seul texte visible modifie, valide : le texte indicatif du
 * champ, en francais sur les pages francaises.
 *
 * Comportement : assets/js/ditl-recherche.js (vanilla, sans jQuery).
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emplacements de menu recevant la recherche.
 *
 * Reprend le reglage d'Ivory Search en base (option is_menu_search :
 * menus => primary). L'emplacement 'primary' est partage par les cinq menus
 * de langue de Polylang : un seul emplacement couvre donc tout le site.
 *
 * @return array Liste d'emplacements de menu (theme_location).
 */
function ditl_recherche_emplacements() {
	/**
	 * Emplacements de menu ou la recherche est inseree.
	 *
	 * @param array $emplacements Liste d'emplacements.
	 */
	return (array) apply_filters( 'ditl_recherche_emplacements', array( 'primary' ) );
}

/**
 * Libelles du composant selon la langue de la page.
 *
 * Les pages francaises recoivent des libelles francais, les autres langues
 * l'anglais du site d'origine. Le texte indicatif anglais reprend exactement
 * la chaine servie jusqu'ici par Ivory Search ("Search here...") : le rendu
 * des pages non francaises ne change pas d'un pixel.
 *
 * @return array Libelles indexes par cle.
 */
function ditl_recherche_textes() {
	if ( ditl_page_est_francaise() ) {
		$textes = array(
			'region'      => 'Recherche',
			'ouvrir'      => 'Afficher la recherche',
			'fermer'      => 'Fermer la recherche',
			'etiquette'   => 'Rechercher sur le site',
			'indication'  => 'Rechercher...',
			'soumettre'   => 'Rechercher',
		);
	} else {
		$textes = array(
			'region'      => 'Search',
			'ouvrir'      => 'Show the search field',
			'fermer'      => 'Close the search field',
			'etiquette'   => 'Search the site',
			'indication'  => 'Search here...',
			'soumettre'   => 'Search',
		);
	}

	/**
	 * Permet d'ajuster les libelles (autres langues, formulations).
	 *
	 * @param array $textes Libelles indexes par cle.
	 */
	return (array) apply_filters( 'ditl_recherche_textes', $textes );
}

/**
 * Numero d'instance du composant.
 *
 * Astra rend le menu principal deux fois (en-tete desktop et menu hors ecran
 * mobile) : le composant est donc present deux fois dans la page. Ivory
 * Search servait les memes identifiants aux deux exemplaires (doublon d'id
 * dans le document) ; ce compteur les rend uniques.
 *
 * @return int Numero de l'instance rendue.
 */
function ditl_recherche_instance_suivante() {
	static $instance = 0;

	$instance++;

	return $instance;
}

/**
 * Icone de loupe du bouton d'ouverture.
 *
 * Trace, dimensions et viewBox repris tels quels d'Ivory Search : la zone
 * d'affichage 20x20 ne montre que la tranche 2 9 20 5 du trace, c'est ce
 * cadrage qui donne le glyphe visible aujourd'hui. Decorative : le nom du
 * composant est porte par le bouton.
 *
 * @return string Markup SVG.
 */
function ditl_recherche_icone_loupe() {
	return '<svg class="ditl-recherche__loupe" width="20" height="20" viewBox="2 9 20 5" aria-hidden="true" focusable="false">'
		. '<path class="ditl-recherche__loupe-trace" d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"></path>'
		. '</svg>';
}

/**
 * Icone de loupe du bouton d'envoi.
 *
 * Meme trace, cadrage complet 0 0 24 24 et largeur 24 comme dans le
 * formulaire d'origine (le CSS le ramene a 22 px). Decorative : le bouton
 * porte son intitule en texte masque.
 *
 * @return string Markup SVG.
 */
function ditl_recherche_icone_envoi() {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24px" aria-hidden="true" focusable="false">'
		. '<path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"></path>'
		. '</svg>';
}

/**
 * Formulaire de recherche du composant.
 *
 * Passe par get_search_form() : la fonction du coeur declenche ses actions
 * (pre_get_search_form) et laisse la main aux filtres tiers. Le markup est
 * fourni par un filtre pose puis retire immediatement autour de l'appel,
 * plutot que par un searchform.php de theme : le formulaire des pages sans
 * resultat et de la page 404, servi par Astra, reste inchange (iso-rendu).
 *
 * Priorite 98, juste avant celle de Polylang (99) : c'est l'extension
 * multilingue qui reecrit l'action du formulaire vers l'URL de recherche de
 * la langue courante (https://.../fr/), exactement comme elle le fait pour
 * tous les autres formulaires de recherche du site. home_url() ne convient
 * pas ici : sous Polylang, il renvoie la page d'accueil traduite
 * (/fr/accueil/) et non la racine de la langue.
 *
 * Le formulaire ne porte pas role="search" : le landmark est sur le
 * conteneur du composant, bouton d'ouverture compris (RGAA 12.6). Sans nom
 * accessible, un <form> n'est de toute facon pas un point de repere.
 *
 * Le champ est rendu vide, y compris sur la page de resultats : le
 * formulaire d'origine ne reaffichait pas la requete (iso-rendu).
 *
 * @param int $instance Numero de l'instance.
 * @return string Markup du formulaire.
 */
function ditl_recherche_formulaire( $instance ) {
	$textes  = ditl_recherche_textes();
	$id_form = 'ditl-recherche-formulaire-' . $instance;
	$id_champ = 'ditl-recherche-champ-' . $instance;

	$markup = '<form class="ditl-recherche__formulaire" id="' . esc_attr( $id_form ) . '"'
		. ' action="' . esc_url( home_url( '/' ) ) . '" method="get">'
		. '<label class="ditl-recherche__etiquette" for="' . esc_attr( $id_champ ) . '">'
		. '<span class="screen-reader-text">' . esc_html( $textes['etiquette'] ) . '</span>'
		. '<input type="search" class="ditl-recherche__champ" id="' . esc_attr( $id_champ ) . '"'
		. ' name="s" value=""'
		. ' placeholder="' . esc_attr( $textes['indication'] ) . '" autocomplete="off" />'
		. '</label>'
		. '<button type="submit" class="ditl-recherche__envoyer">'
		. '<span class="screen-reader-text">' . esc_html( $textes['soumettre'] ) . '</span>'
		. '<span class="ditl-recherche__envoyer-icone">' . ditl_recherche_icone_envoi() . '</span>'
		. '</button>'
		. '</form>';

	$remplacer = function () use ( $markup ) {
		return $markup;
	};

	add_filter( 'get_search_form', $remplacer, 98 );
	$html = get_search_form( array( 'echo' => false ) );
	remove_filter( 'get_search_form', $remplacer, 98 );

	return is_string( $html ) ? $html : $markup;
}

/**
 * Markup complet du composant de recherche.
 *
 * @return string Markup du conteneur role="search".
 */
function ditl_recherche_composant() {
	$textes   = ditl_recherche_textes();
	$instance = ditl_recherche_instance_suivante();
	$id_form  = 'ditl-recherche-formulaire-' . $instance;

	// Le landmark porte le libelle du bouton d'ouverture : les deux instances
	// (en-tete et menu hors ecran) restent distinguables par un lecteur
	// d'ecran qui liste les regions.
	$html = '<div class="ditl-recherche" role="search" aria-label="' . esc_attr( $textes['region'] ) . '">'
		. '<button type="button" class="ditl-recherche__bascule" aria-expanded="false"'
		. ' aria-controls="' . esc_attr( $id_form ) . '"'
		. ' aria-label="' . esc_attr( $textes['ouvrir'] ) . '">'
		. ditl_recherche_icone_loupe()
		. '</button>'
		. ditl_recherche_formulaire( $instance )
		. '<button type="button" class="ditl-recherche__fermer"'
		. ' aria-label="' . esc_attr( $textes['fermer'] ) . '"></button>'
		. '</div>';

	return $html;
}

/**
 * Insere la recherche en dernier element du menu principal.
 *
 * Position et enveloppe reprises d'Ivory Search : un <li class="menu-item">
 * en fin de liste, dont la mise en forme vient des regles Astra
 * .main-header-menu .menu-item (flex colonne centree, hauteur de ligne de la
 * barre d'en-tete). Le conteneur role="search" est a l'interieur du <li> :
 * l'element de liste reste un element de liste.
 *
 * @param string   $items Markup des elements du menu.
 * @param stdClass $args  Arguments de wp_nav_menu().
 * @return string
 */
function ditl_recherche_menu( $items, $args ) {
	$emplacement = isset( $args->theme_location ) ? $args->theme_location : '';

	if ( ! in_array( $emplacement, ditl_recherche_emplacements(), true ) ) {
		return $items;
	}

	return $items . '<li class="menu-item ditl-recherche-item">' . ditl_recherche_composant() . '</li>';
}
add_filter( 'wp_nav_menu_items', 'ditl_recherche_menu', 10, 2 );

/**
 * Feuille de style et script du composant.
 *
 * Charges sur toutes les pages, comme le menu. La feuille depend de
 * ditl-a11y pour passer en dernier, apres le CSS d'Astra (inline) et les
 * feuilles des extensions : les regles du composant reprennent la
 * specificite juste necessaire pour battre les regles Astra sur input,
 * button et label, sans !important.
 */
function ditl_recherche_enqueue_assets() {
	wp_enqueue_style(
		'ditl-recherche',
		get_stylesheet_directory_uri() . '/assets/css/recherche.css',
		array( 'ditl-a11y' ),
		DITL_THEME_VERSION
	);

	wp_enqueue_script(
		'ditl-recherche',
		get_stylesheet_directory_uri() . '/assets/js/ditl-recherche.js',
		array(),
		DITL_THEME_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'ditl_recherche_enqueue_assets', 12 );
