<?php
/**
 * Corrections d'accessibilite portees par des hooks sur Astra.
 *
 * Regroupe les ajustements de structure du document que le theme parent
 * n'offre pas en option et qui ne changent aucun pixel (RGAA / WCAG,
 * suite a l'audit Access42 de juillet 2026). Chaque correction est
 * documentee avec le critere vise.
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
