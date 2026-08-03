<?php
/**
 * Template Name: Gabarit Contact
 *
 * Gabarit sur mesure remplacant le rendu Elementor des pages "Contact" /
 * "Contact us". Banniere commune (metas, voir inc/metaboxes/banniere.php),
 * puis une section a deux colonnes :
 * - a gauche, un titre H2 et le formulaire de contact, rendu par le
 *   shortcode du plugin WPForms (conserve au perimetre : le formulaire
 *   lui-meme n'est pas touche, seul son emplacement est gere ici) ;
 * - a droite, un titre H2 et les blocs de coordonnees (icone, intitule H3,
 *   contenu HTML riche avec liens tel: / mailto:).
 *
 * Le contenu est lu dans les metas de la page (voir inc/metaboxes/contact.php),
 * le rendu reproduit a l'identique la mise en page d'origine (iso-design).
 *
 * Le titre de la banniere est rendu en H1 sur toutes les langues. La page
 * anglaise d'origine utilisait un H5 (elle n'avait donc aucun H1) : c'est une
 * correction d'accessibilite volontaire, sans effet visuel (la variante de
 * style est reproduite dans assets/css/gabarit-contact.css).
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
				$ditl_formulaire = ditl_get_meta_json( $ditl_post_id, '_ditl_contact_formulaire' );
				$ditl_coord      = ditl_get_meta_json( $ditl_post_id, '_ditl_contact_coordonnees' );

				$ditl_form_titre = isset( $ditl_formulaire['titre'] ) ? (string) $ditl_formulaire['titre'] : '';
				$ditl_form_id    = isset( $ditl_formulaire['form_id'] ) ? absint( $ditl_formulaire['form_id'] ) : 0;

				$ditl_coord_titre = isset( $ditl_coord['titre'] ) ? (string) $ditl_coord['titre'] : '';
				$ditl_coord_blocs = isset( $ditl_coord['blocs'] ) && is_array( $ditl_coord['blocs'] ) ? $ditl_coord['blocs'] : array();

				// Le formulaire n'est rendu que si le shortcode du plugin est
				// disponible : plugin desactive, on n'affiche rien plutot que
				// le shortcode en clair.
				$ditl_form_html = '';

				if ( $ditl_form_id > 0 && shortcode_exists( 'wpforms' ) ) {
					$ditl_form_html = do_shortcode( '[wpforms id="' . $ditl_form_id . '"]' );
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

						<?php get_template_part( 'template-parts/gabarit-hero' ); ?>

						<section class="ditl-contact-section">
							<div class="ditl-contact-section__inner">

								<div class="ditl-contact-col ditl-contact-col--formulaire">
									<?php if ( '' !== $ditl_form_titre ) { ?>
									<h2 class="ditl-contact-titre"><?php echo esc_html( $ditl_form_titre ); ?></h2>
									<?php } ?>
									<?php if ( '' !== $ditl_form_html ) { ?>
									<div class="ditl-contact-formulaire">
										<?php echo $ditl_form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formulaire genere par le shortcode du plugin WPForms. ?>
									</div>
									<?php } ?>
								</div>

								<div class="ditl-contact-col ditl-contact-col--coordonnees">
									<?php if ( '' !== $ditl_coord_titre ) { ?>
									<h2 class="ditl-contact-titre"><?php echo esc_html( $ditl_coord_titre ); ?></h2>
									<?php } ?>

									<?php
									// Rang du bloc (le style du premier intitule differe,
									// voir assets/css/gabarit-contact.css).
									$ditl_rang = 0;

									foreach ( $ditl_coord_blocs as $ditl_bloc ) {
										++$ditl_rang;

										$ditl_icone_id    = isset( $ditl_bloc['icone_id'] ) ? absint( $ditl_bloc['icone_id'] ) : 0;
										$ditl_bloc_titre  = isset( $ditl_bloc['titre'] ) ? (string) $ditl_bloc['titre'] : '';
										$ditl_description = isset( $ditl_bloc['description'] ) ? (string) $ditl_bloc['description'] : '';

										// Icone decorative : l'intitule du bloc porte
										// l'information juste a cote.
										$ditl_icone_url = $ditl_icone_id ? wp_get_attachment_image_url( $ditl_icone_id, 'full' ) : '';
										?>
									<div class="ditl-contact-bloc ditl-contact-bloc--<?php echo esc_attr( (string) $ditl_rang ); ?>">
										<?php if ( '' !== (string) $ditl_icone_url ) { ?>
										<span class="ditl-contact-bloc__icone">
											<img
												class="ditl-contact-bloc__icone-image"
												src="<?php echo esc_url( $ditl_icone_url ); ?>"
												width="42"
												height="42"
												alt=""
												aria-hidden="true"
											/>
										</span>
										<?php } ?>
										<div class="ditl-contact-bloc__contenu">
											<?php if ( '' !== $ditl_bloc_titre ) { ?>
											<h3 class="ditl-contact-bloc__titre"><?php echo esc_html( $ditl_bloc_titre ); ?></h3>
											<?php } ?>
											<?php if ( '' !== trim( wp_strip_all_tags( $ditl_description ) ) ) { ?>
											<?php // Pas de ditl_format_rich_text ici : le widget icon-box d'Elementor imprime la description brute (ni shortcodes ni wptexturize), l'appliquer changerait la typographie du rendu. ?>
											<p class="ditl-contact-bloc__texte"><?php echo ditl_contact_filtrer_description( $ditl_description ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filtre par liste blanche kses dediee. ?></p>
											<?php } ?>
										</div>
									</div>
										<?php
									}
									?>
								</div>

							</div>
						</section>

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
