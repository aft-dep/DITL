<?php
/**
 * Template Name: Gabarit Resultats
 *
 * Gabarit sur mesure remplacant le rendu Elementor des pages "Resultats" /
 * "Results". Banniere commune (metas, voir inc/metaboxes/banniere.php),
 * introduction, sections d'activites separees par un filet horizontal,
 * puis bandeau de mise en avant optionnel (rempli sur la page anglaise).
 * Le contenu est lu dans les metas de la page (voir inc/metaboxes/resultats.php),
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

				$ditl_post_id       = get_the_ID();
				$ditl_intro_content = (string) get_post_meta( $ditl_post_id, '_ditl_intro_content', true );
				$ditl_sections      = ditl_get_meta_json( $ditl_post_id, '_ditl_sections' );

				// Bandeau de mise en avant optionnel : affiche des que le
				// texte ou l'image est renseigne.
				$ditl_bandeau_image_id   = absint( get_post_meta( $ditl_post_id, '_ditl_bandeau_image_id', true ) );
				$ditl_bandeau_texte      = (string) get_post_meta( $ditl_post_id, '_ditl_bandeau_texte', true );
				$ditl_bandeau_bouton_txt = (string) get_post_meta( $ditl_post_id, '_ditl_bandeau_bouton_texte', true );
				$ditl_bandeau_bouton_url = (string) get_post_meta( $ditl_post_id, '_ditl_bandeau_bouton_url', true );
				$ditl_bandeau_visible    = ( '' !== trim( wp_strip_all_tags( $ditl_bandeau_texte ) ) || 0 !== $ditl_bandeau_image_id );

				// URL relative en meta (portable entre environnements) :
				// prefixee par l'URL du site au rendu (helper partage).
				$ditl_bandeau_href = ditl_href_from_meta_url( $ditl_bandeau_bouton_url );

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

						<div class="ditl-boxed ditl-res-corps">
							<div class="ditl-boxed__inner">

								<?php if ( '' !== $ditl_intro_content ) { ?>
								<div class="ditl-res-intro"><?php echo wp_kses_post( ditl_format_rich_text( $ditl_intro_content ) ); ?></div>
								<?php } ?>

								<?php
								$ditl_premiere_section = true;

								foreach ( $ditl_sections as $ditl_section ) {
									$ditl_section_title   = isset( $ditl_section['title'] ) ? (string) $ditl_section['title'] : '';
									$ditl_section_content = isset( $ditl_section['content'] ) ? (string) $ditl_section['content'] : '';

									if ( '' === $ditl_section_title && '' === $ditl_section_content ) {
										continue;
									}

									// Filet horizontal entre les sections (pas apres la derniere).
									if ( ! $ditl_premiere_section ) {
										?>
									<hr class="ditl-res-separateur" />
										<?php
									}
									$ditl_premiere_section = false;

									?>
								<section class="ditl-res-section">
									<?php if ( '' !== $ditl_section_title ) { ?>
									<h2 class="ditl-res-section__title"><?php echo esc_html( $ditl_section_title ); ?></h2>
									<?php } ?>
									<?php if ( '' !== $ditl_section_content ) { ?>
									<div class="ditl-res-section__content"><?php echo wp_kses_post( ditl_format_rich_text( $ditl_section_content ) ); ?></div>
									<?php } ?>
								</section>
									<?php
								}
								?>

								<?php if ( $ditl_bandeau_visible ) { ?>
								<div
									class="ditl-res-bandeau"
									<?php
									if ( $ditl_bandeau_image_id ) {
										$ditl_bandeau_image_url = (string) wp_get_attachment_image_url( $ditl_bandeau_image_id, 'full' );

										if ( '' !== $ditl_bandeau_image_url ) {
											// Contexte attribut style : esc_url puis retrait des
											// caracteres dangereux pour du CSS (quotes, parentheses).
											$ditl_bandeau_image_css = str_replace( array( '(', ')', '"', "'" ), '', esc_url( $ditl_bandeau_image_url ) );
											echo ' style="background-image:url(' . $ditl_bandeau_image_css . ');"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										}
									}
									?>
								>
									<div class="ditl-res-bandeau__contenu">
										<?php if ( '' !== $ditl_bandeau_texte ) { ?>
										<div class="ditl-res-bandeau__texte"><?php echo wp_kses_post( ditl_format_rich_text( $ditl_bandeau_texte ) ); ?></div>
										<?php } ?>
										<?php if ( '' !== $ditl_bandeau_bouton_txt && '' !== $ditl_bandeau_href ) { ?>
										<div class="ditl-res-bandeau__action">
											<a class="ditl-res-bandeau__bouton" href="<?php echo esc_url( $ditl_bandeau_href ); ?>"><?php echo esc_html( $ditl_bandeau_bouton_txt ); ?></a>
										</div>
										<?php } ?>
									</div>
								</div>
								<?php } ?>

							</div>
						</div>

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
