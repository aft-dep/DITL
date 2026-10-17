<?php
/**
 * Mention de l'etat de conformite dans le pied de page (lot a11y B6).
 *
 * CONSIGNE : pour une entite soumise a l'article 47 de la loi n° 2005-102
 * du 11 fevrier 2005 (assujettissement a confirmer par le client), les
 * consignes Access42 de publication demandent d'afficher, au moins sur la
 * page d'accueil, le niveau d'accessibilite constate selon le taux RGAA 4.1 :
 * "Accessibilite : non conforme" sous 50 % (48,28 % a l'audit du
 * 09/06/2026), "partiellement conforme" de 50 a 99,99 %, "totalement
 * conforme" a 100 %, si possible en lien vers la declaration
 * d'accessibilite. Arbitrage du 17/10/2026 : la mention est placee dans le
 * pied de page, donc sur toutes les pages, en lien vers la page de la
 * langue courante.
 *
 * IMPLEMENTATION : un element est ajoute en fin du menu de pied de page
 * d'Astra (emplacement footer_menu), avec le balisage des elements natifs
 * (li.menu-item > a.menu-link) : meme police, meme couleur, meme survol,
 * meme indicateur de focus (assets/css/a11y.css). La mention est portee par
 * le code et non par un element de menu en base : elle suit l'etat de
 * conformite et ne peut pas etre retiree par megarde depuis
 * Apparence > Menus. Aucune feuille de style n'est ajoutee.
 *
 * PAGES CIBLES : creees par cli/creer-page-accessibilite.php, qui enregistre
 * leurs identifiants (propres a chaque environnement) dans l'option
 * ditl_page_accessibilite (tableau langue => ID). Les langues sans page
 * dediee (es, pt, de) recoivent la mention traduite, en lien vers la page
 * anglaise (hreflang="en") : leur pied de page renvoie deja entierement aux
 * pages anglaises (menu partage). Choix a faire confirmer par le client ;
 * la migration WPML de la phase 2 reprendra le sujet. Sans page publiee
 * pour la langue ni pour l'anglais (script non joue sur l'environnement),
 * rien n'est affiche : pas de lien mort.
 *
 * APRES UN NOUVEL AUDIT : changer la valeur de ditl_accessibilite_statut()
 * (ou le filtre du meme nom) et mettre a jour la declaration dans le script
 * cli/creer-page-accessibilite.php, puis le rejouer.
 *
 * La langue est lue dans la locale (meme principe que
 * ditl_page_est_francaise()) : aucune dependance a l'API de Polylang, le
 * code restera valable apres la migration vers WPML.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Etat de conformite affiche, selon le dernier audit publie.
 *
 * @return string "non-conforme", "partiellement-conforme" ou "totalement-conforme".
 */
function ditl_accessibilite_statut() {
	/**
	 * Permet de changer l'etat affiche sans toucher au theme.
	 *
	 * @param string $statut Etat de conformite.
	 */
	return (string) apply_filters( 'ditl_accessibilite_statut', 'non-conforme' );
}

/**
 * Libelles de la mention, par etat de conformite puis par langue.
 *
 * Francais : formulations exactes des consignes Access42, avec espace
 * insecable avant le deux-points. Autres langues : traductions courtes, a
 * faire confirmer par le client.
 *
 * @return array Tableau etat => ( langue => libelle ).
 */
function ditl_accessibilite_libelles() {
	$insecable = "\u{00A0}";

	return array(
		'non-conforme'           => array(
			'fr' => 'Accessibilité' . $insecable . ': non conforme',
			'en' => 'Accessibility: not compliant',
			'es' => 'Accesibilidad: no conforme',
			'pt' => 'Acessibilidade: não conforme',
			'de' => 'Barrierefreiheit: nicht konform',
		),
		'partiellement-conforme' => array(
			'fr' => 'Accessibilité' . $insecable . ': partiellement conforme',
			'en' => 'Accessibility: partially compliant',
			'es' => 'Accesibilidad: parcialmente conforme',
			'pt' => 'Acessibilidade: parcialmente conforme',
			'de' => 'Barrierefreiheit: teilweise konform',
		),
		'totalement-conforme'    => array(
			'fr' => 'Accessibilité' . $insecable . ': totalement conforme',
			'en' => 'Accessibility: fully compliant',
			'es' => 'Accesibilidad: plenamente conforme',
			'pt' => 'Acessibilidade: plenamente conforme',
			'de' => 'Barrierefreiheit: vollständig konform',
		),
	);
}

/**
 * Langue de la page rendue (code a deux lettres tire de la locale).
 *
 * @return string Code de langue ("fr", "en", "es", "pt", "de"...).
 */
function ditl_accessibilite_langue() {
	return strtolower( substr( (string) get_locale(), 0, 2 ) );
}

/**
 * Page de declaration a viser depuis une langue.
 *
 * Page de la langue si elle existe et est publiee, sinon page anglaise.
 * Seule une page portant le marqueur pose par le script de creation est
 * retenue : une option alteree ne peut pas lier la mention a une autre page.
 *
 * @param string $langue Code de langue de la page rendue.
 * @return array Tableau { id => int, langue => string } ; id a 0 si aucune page.
 */
function ditl_accessibilite_page_cible( $langue ) {
	$pages = get_option( 'ditl_page_accessibilite', array() );
	if ( ! is_array( $pages ) ) {
		$pages = array();
	}

	foreach ( array( $langue, 'en' ) as $candidate ) {
		$id = isset( $pages[ $candidate ] ) && is_scalar( $pages[ $candidate ] ) ? absint( $pages[ $candidate ] ) : 0;

		if ( $id > 0 && 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id )
			&& get_post_meta( $id, '_ditl_declaration_accessibilite', true ) === $candidate ) {
			return array(
				'id'     => $id,
				'langue' => $candidate,
			);
		}
	}

	return array(
		'id'     => 0,
		'langue' => '',
	);
}

/**
 * Ajoute la mention en fin du menu de pied de page.
 *
 * Filtre wp_nav_menu_items, limite a l'emplacement footer_menu (menu de
 * pied d'Astra, une seule instance par page, toutes langues). Balisage
 * calque sur les elements natifs ; sur la page de declaration elle-meme,
 * memes marqueurs d'element courant que WordPress (classes et
 * aria-current="page").
 *
 * @param string   $items Markup des elements du menu.
 * @param stdClass $args  Arguments de wp_nav_menu().
 * @return string
 */
function ditl_accessibilite_mention_pied( $items, $args ) {
	if ( ! is_string( $items ) || ! is_object( $args ) || empty( $args->theme_location ) || 'footer_menu' !== $args->theme_location ) {
		return $items;
	}

	$libelles = ditl_accessibilite_libelles();
	$statut   = ditl_accessibilite_statut();
	if ( ! isset( $libelles[ $statut ] ) ) {
		return $items;
	}

	$langue  = ditl_accessibilite_langue();
	$libelle = isset( $libelles[ $statut ][ $langue ] ) ? $libelles[ $statut ][ $langue ] : $libelles[ $statut ]['en'];
	$cible   = ditl_accessibilite_page_cible( $langue );
	if ( $cible['id'] <= 0 ) {
		return $items;
	}

	$url = get_permalink( $cible['id'] );
	if ( ! $url ) {
		return $items;
	}

	$classes = 'menu-item menu-item-ditl-accessibilite';
	$courant = '';
	if ( is_page( $cible['id'] ) ) {
		$classes .= ' current-menu-item current_page_item';
		$courant  = ' aria-current="page"';
	}

	// Page visee dans une autre langue que la page rendue (es, pt, de).
	$hreflang = $cible['langue'] !== $langue ? sprintf( ' hreflang="%s"', esc_attr( $cible['langue'] ) ) : '';

	return $items . sprintf(
		'<li class="%1$s"><a href="%2$s"%3$s%4$s class="menu-link">%5$s</a></li>' . "\n",
		esc_attr( $classes ),
		esc_url( $url ),
		$hreflang, // Deja echappe.
		$courant, // Chaine fixe.
		esc_html( $libelle )
	);
}
add_filter( 'wp_nav_menu_items', 'ditl_accessibilite_mention_pied', 10, 2 );
