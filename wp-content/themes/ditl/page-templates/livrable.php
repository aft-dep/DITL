<?php
/**
 * Template Name: Gabarit Livrable
 *
 * Gabarit sur mesure remplacant le rendu Elementor des pages "Livrable 1" /
 * "Deliverable 1". Trois blocs en boite (largeur de contenu 1140px) :
 * - le titre H1 de la page (meta _ditl_hero_title ; ce gabarit n'a pas
 *   d'image de banniere, le H1 est rendu directement, sans le hero commun) ;
 * - la carte interactive, rendue par le shortcode du plugin Interactive Geo
 *   Maps (conserve au perimetre : la carte elle-meme n'est pas touchee, seul
 *   son emplacement est gere ici), precedee d'une alternative textuelle
 *   reservee aux lecteurs d'ecran (correction d'accessibilite du gabarit :
 *   l'information des infobulles de la carte, inaccessible au clavier,
 *   devient disponible aux technologies d'assistance, sans effet visuel) ;
 * - les sections de livrables : titre H2 (HTML riche leger), contenu HTML
 *   riche, bouton de telechargement centre ; un filet horizontal separe
 *   deux sections (jamais avant la premiere ni apres la derniere).
 *
 * Le contenu est lu dans les metas de la page (voir inc/metaboxes/livrable.php),
 * le rendu reproduit a l'identique la mise en page d'origine (iso-design).
 * Les variantes propres a chaque page (grille de la section 2 francaise,
 * typographies des titres) sont scopees par page dans
 * assets/css/gabarit-livrable.css, comme sur les autres gabarits.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header(); ?>

<?php if ( astra_page_layout() === 'left-sidebar' ) { ?>

	<?php get_sidebar(); ?>

<?php } ?>

	<div id="primary" <?php astra_primary_class(); ?>>

		<?php astra_primary_content_top(); ?>

		<main id="main" class="site-main">

			<?php
			while ( have_posts() ) {
				the_post();

				$ditl_post_id    = get_the_ID();
				$ditl_hero_title = (string) get_post_meta( $ditl_post_id, '_ditl_hero_title', true );
				$ditl_carte      = ditl_get_meta_json( $ditl_post_id, '_ditl_livrable_carte' );
				$ditl_livrables  = ditl_get_meta_json( $ditl_post_id, '_ditl_livrables' );

				// Garde de type partagee avec la metabox : un ID qui ne
				// correspond pas a une carte du plugin est ramene a 0.
				$ditl_map_id      = isset( $ditl_carte['map_id'] ) ? ditl_livrable_map_id_valide( $ditl_carte['map_id'] ) : 0;
				$ditl_alternative = isset( $ditl_carte['alternative'] ) ? (string) $ditl_carte['alternative'] : '';

				// La carte n'est rendue que si le shortcode du plugin est
				// disponible : plugin desactive, on n'affiche rien plutot
				// que le shortcode en clair (meme garde que WPForms sur le
				// gabarit Contact). Une carte non publiee (brouillon, privee)
				// n'est pas rendue non plus : pas de divulgation sur le front.
				$ditl_carte_html = '';

				if ( $ditl_map_id > 0 && 'publish' === get_post_status( $ditl_map_id ) && shortcode_exists( 'display-map' ) ) {
					$ditl_carte_html = do_shortcode( '[display-map id="' . $ditl_map_id . '"]' );
				}

				astra_entry_before();
				?>
				<article
				<?php
				echo wp_kses_post(
					astra_attr(
						'article-page',
						array(
							'id'    => 'post-' . $ditl_post_id,
							'class' => join( ' ', get_post_class() ),
						)
					)
				);
				?>
				>

					<header class="entry-header ast-no-title ast-header-without-markup"></header> <!-- .entry-header -->

					<div class="entry-content clear" itemprop="text">

						<?php if ( '' !== $ditl_hero_title ) { ?>
						<div class="ditl-boxed ditl-liv-entete">
							<div class="ditl-boxed__inner">
								<h1 class="ditl-liv-titre-page"><?php echo esc_html( $ditl_hero_title ); ?></h1>
							</div>
						</div>
						<?php } ?>

						<?php if ( '' !== $ditl_carte_html ) { ?>
						<div class="ditl-boxed ditl-liv-carte-bloc">
							<div class="ditl-boxed__inner">
								<?php if ( '' !== $ditl_alternative ) { ?>
								<p class="screen-reader-text"><?php echo wp_kses( nl2br( esc_html( $ditl_alternative ) ), array( 'br' => array() ) ); ?></p>
								<?php } ?>
								<div class="ditl-liv-carte">
									<?php echo $ditl_carte_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- carte generee par le shortcode du plugin Interactive Geo Maps. ?>
								</div>
							</div>
						</div>
						<?php } ?>

						<?php if ( array() !== $ditl_livrables ) { ?>
						<div class="ditl-boxed ditl-liv-corps">
							<div class="ditl-boxed__inner ditl-liv-sections">

								<?php
								// Rang de la section (la section 2 de la page
								// francaise est en grille, voir le CSS).
								$ditl_rang = 0;

								foreach ( $ditl_livrables as $ditl_livrable ) {
									++$ditl_rang;

									$ditl_titre        = isset( $ditl_livrable['titre'] ) ? (string) $ditl_livrable['titre'] : '';
									$ditl_contenu      = isset( $ditl_livrable['contenu'] ) ? (string) $ditl_livrable['contenu'] : '';
									$ditl_bouton_texte = isset( $ditl_livrable['bouton_texte'] ) ? (string) $ditl_livrable['bouton_texte'] : '';
									$ditl_bouton_url   = isset( $ditl_livrable['bouton_url'] ) ? (string) $ditl_livrable['bouton_url'] : '';

									// URL relative en meta (portable entre environnements) :
									// prefixee par l'URL du site au rendu (helper partage).
									$ditl_bouton_href = ditl_href_from_meta_url( $ditl_bouton_url );

									// Filet horizontal entre deux sections uniquement.
									if ( $ditl_rang > 1 ) {
										?>
								<hr class="ditl-liv-separateur" aria-hidden="true" />
										<?php
									}
									?>
								<section class="ditl-liv-section ditl-liv-section--<?php echo esc_attr( (string) $ditl_rang ); ?>">
									<?php if ( '' !== trim( wp_strip_all_tags( $ditl_titre ) ) ) { ?>
									<?php // Titre imprime sans wpautop ni wptexturize, comme le widget titre d'Elementor (certains titres portent un span avec style inline, conserve tel quel). ?>
									<h2 class="ditl-liv-titre"><?php echo wp_kses_post( $ditl_titre ); ?></h2>
									<?php } ?>
									<?php if ( '' !== trim( wp_strip_all_tags( $ditl_contenu ) ) ) { ?>
									<div class="ditl-liv-contenu"><?php echo wp_kses_post( ditl_format_rich_text( $ditl_contenu ) ); ?></div>
									<?php } ?>
									<?php if ( '' !== $ditl_bouton_texte && '' !== $ditl_bouton_href ) { ?>
									<div class="ditl-liv-action">
										<a class="ditl-liv-bouton" href="<?php echo esc_url( $ditl_bouton_href ); ?>"><?php echo esc_html( $ditl_bouton_texte ); ?></a>
									</div>
									<?php } ?>
								</section>
									<?php
								}
								?>

							</div>
						</div>
						<?php } ?>

					</div><!-- .entry-content .clear -->

				</article><!-- #post-## -->
				<?php
				astra_entry_after();
			}
			?>

		</main><!-- #main -->

		<?php astra_primary_content_bottom(); ?>

	</div><!-- #primary -->

<?php if ( astra_page_layout() === 'right-sidebar' ) { ?>

	<?php get_sidebar(); ?>

<?php } ?>

<?php get_footer(); ?>
