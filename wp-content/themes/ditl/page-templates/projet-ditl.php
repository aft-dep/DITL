<?php
/**
 * Template Name: Gabarit Projet DiTL
 *
 * Gabarit sur mesure remplacant le rendu Elementor de la page "Projet DiTL".
 * Le contenu est lu dans les metas de la page (voir inc/metaboxes/projet-ditl.php),
 * le rendu reproduit a l'identique la mise en page d'origine (iso-design).
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

				$ditl_post_id      = get_the_ID();
				$ditl_intro_title  = (string) get_post_meta( $ditl_post_id, '_ditl_intro_title', true );
				$ditl_sections     = ditl_get_meta_json( $ditl_post_id, '_ditl_sections' );
				$ditl_carousel_ids = ditl_get_meta_json( $ditl_post_id, '_ditl_carousel_ids' );

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

						<?php get_template_part( 'template-parts/gabarit-hero' ); ?>

						<?php if ( '' !== $ditl_intro_title ) { ?>
						<div class="ditl-boxed ditl-intro">
							<div class="ditl-boxed__inner">
								<h2 class="ditl-intro__title"><?php echo esc_html( $ditl_intro_title ); ?></h2>
							</div>
						</div>
						<?php } ?>

						<?php if ( ! empty( $ditl_sections ) ) { ?>
						<div class="ditl-boxed ditl-sections">
							<div class="ditl-boxed__inner">
								<?php
								foreach ( $ditl_sections as $ditl_section ) {
									$ditl_section_title   = isset( $ditl_section['title'] ) ? (string) $ditl_section['title'] : '';
									$ditl_section_content = isset( $ditl_section['content'] ) ? (string) $ditl_section['content'] : '';

									if ( '' !== $ditl_section_title ) {
										?>
										<h3 class="ditl-section__title"><?php echo esc_html( $ditl_section_title ); ?></h3>
										<?php
									}

									if ( '' !== $ditl_section_content ) {
										?>
										<div class="ditl-section__content"><?php echo wp_kses_post( ditl_format_rich_text( $ditl_section_content ) ); ?></div>
										<?php
									}
								}
								?>
							</div>
						</div>
						<?php } ?>

						<?php
						// Galerie statique (decision du 26/07 : l'ancien carrousel
						// Elementor n'apportait rien, ses 3 images etaient toutes
						// visibles ; on les affiche cote a cote, empilees en mobile).
						if ( ! empty( $ditl_carousel_ids ) ) {
							?>
						<div class="ditl-boxed ditl-galerie-zone">
							<div class="ditl-boxed__inner">
								<ul class="ditl-galerie">
									<?php
									// Comme l'original : un seul fichier par image, pas de srcset.
									add_filter( 'wp_calculate_image_srcset', '__return_false' );
									foreach ( $ditl_carousel_ids as $ditl_image_id ) {
										$ditl_image_attrs = array();
										$ditl_image_alt   = trim( (string) get_post_meta( $ditl_image_id, '_wp_attachment_image_alt', true ) );

										// Comme l'original : repli sur le titre du media si l'alt est vide.
										if ( '' === $ditl_image_alt ) {
											$ditl_image_attrs['alt'] = trim( wp_strip_all_tags( get_the_title( $ditl_image_id ) ) );
										}
										?>
									<li class="ditl-galerie__item">
										<?php echo wp_get_attachment_image( $ditl_image_id, 'medium_large', false, $ditl_image_attrs ); ?>
									</li>
										<?php
									}
									remove_filter( 'wp_calculate_image_srcset', '__return_false' );
									?>
								</ul>
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
