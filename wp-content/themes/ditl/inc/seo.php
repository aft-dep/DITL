<?php
/**
 * Ajustements SEO du theme (iso-SEO avec l'existant apres activation de Yoast).
 *
 * Yoast SEO n'est introduit que pour AJOUTER (sitemap XML plus riche, balises
 * Open Graph / Twitter, donnees structurees JSON-LD, saisie future des meta
 * descriptions) : rien de ce que le coeur de WordPress et Polylang emettaient
 * deja dans le <head> (titres, canonical, hreflang, meta robots) ne doit
 * changer. Ce fichier neutralise les seuls ecarts que la configuration par
 * options ne couvre pas.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ramene la meta robots de Yoast a la valeur emise par le coeur de WordPress.
 *
 * Sans Yoast, le coeur emet "max-image-preview:large". Yoast y ajoute par
 * defaut "index, follow, max-snippet:-1, max-video-preview:-1" : directives
 * strictement equivalentes pour les moteurs (ce sont leurs valeurs par
 * defaut en l'absence de directive), mais l'exigence d'iso-SEO de la refonte
 * est une identite au byte pres. Les directives redondantes sont donc
 * retirees UNIQUEMENT quand la page reste indexable : si une restriction
 * (noindex, nofollow, max-snippet personnalise...) est posee un jour via
 * Yoast, elle passe intacte.
 *
 * Sans Yoast actif, le tableau du coeur ne contient aucune de ces cles :
 * le filtre est alors sans effet (ordre d'activation indifferent).
 *
 * @param array $robots Directives robots (cle => valeur ou true).
 * @return array Directives filtrees.
 */
function ditl_seo_robots_iso( $robots ) {
	if ( ! is_array( $robots ) || ! empty( $robots['noindex'] ) || ! empty( $robots['nofollow'] ) ) {
		return $robots;
	}

	// "index" et "follow" sont le comportement par defaut des moteurs :
	// leur absence est equivalente (c'est ce que le coeur emettait).
	unset( $robots['index'], $robots['follow'] );

	// -1 = illimite = valeur par defaut des moteurs : redondant aussi.
	if ( isset( $robots['max-snippet'] ) && '-1' === (string) $robots['max-snippet'] ) {
		unset( $robots['max-snippet'] );
	}

	if ( isset( $robots['max-video-preview'] ) && '-1' === (string) $robots['max-video-preview'] ) {
		unset( $robots['max-video-preview'] );
	}

	return $robots;
}
// Yoast injecte ses directives a PHP_INT_MAX - 10 : la normalisation doit
// passer apres lui.
add_filter( 'wp_robots', 'ditl_seo_robots_iso', PHP_INT_MAX );

/**
 * Restaure dans le robots.txt virtuel les regles par defaut de WordPress
 * que Yoast retire.
 *
 * Sans Yoast, WordPress genere :
 *   User-agent: *
 *   Disallow: /wp-admin/
 *   Allow: /wp-admin/admin-ajax.php
 * Yoast supprime ce bloc et le remplace par un "Disallow:" vide (sa
 * philosophie : ne rien bloquer). Iso-SEO oblige, les memes regles sont
 * reinjectees dans le bloc Yoast via l'action prevue a cet effet. La ligne
 * "Sitemap:" pointe desormais vers sitemap_index.xml (Yoast), l'ancienne
 * URL /wp-sitemap.xml etant redirigee en 301 par Yoast lui-meme.
 *
 * Sans Yoast actif, l'action n'est jamais declenchee : le robots.txt du
 * coeur reste intact (ordre d'activation indifferent).
 *
 * @param Yoast\WP\SEO\Helpers\Robots_Txt_Helper $robots_txt_helper Collecteur de regles de Yoast.
 */
function ditl_seo_robots_txt_regles_wp( $robots_txt_helper ) {
	if ( ! is_object( $robots_txt_helper )
		|| ! method_exists( $robots_txt_helper, 'add_disallow' )
		|| ! method_exists( $robots_txt_helper, 'add_allow' ) ) {
		return;
	}

	$robots_txt_helper->add_disallow( '*', '/wp-admin/' );
	$robots_txt_helper->add_allow( '*', '/wp-admin/admin-ajax.php' );
}
add_action( 'Yoast\WP\SEO\register_robots_rules', 'ditl_seo_robots_txt_regles_wp' );
