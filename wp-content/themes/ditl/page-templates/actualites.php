<?php
/**
 * Template Name: Gabarit Actualites
 *
 * Gabarit sur mesure remplacant le rendu Elementor des pages "Actualites" /
 * "News". Banniere commune (metas, voir inc/metaboxes/banniere.php) puis
 * carrousel des 6 derniers articles publies dans la langue courante
 * (Polylang filtre la requete), rendu identique au widget UPK d'origine.
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

				$ditl_post_id = get_the_ID();

				// Libelles selon la langue de la page (site multilingue sans
				// fichiers de traduction du theme : francais, sinon anglais).
				$ditl_fr     = ( 0 === strpos( (string) get_locale(), 'fr' ) );
				$ditl_labels = array(
					'region' => $ditl_fr ? 'Dernières actualités' : 'Latest news',
					'role'   => $ditl_fr ? 'carrousel' : 'carousel',
					'slide'  => $ditl_fr ? 'Diapositive %1$d sur %2$d' : 'Slide %1$d of %2$d',
					'prev'   => $ditl_fr ? 'Article précédent' : 'Previous post',
					'next'   => $ditl_fr ? 'Article suivant' : 'Next post',
					'pause'  => $ditl_fr ? 'Mettre en pause le défilement automatique' : 'Pause automatic sliding',
					'resume' => $ditl_fr ? 'Reprendre le défilement automatique' : 'Resume automatic sliding',
					'read'   => $ditl_fr ? 'Lire l\'article : %s' : 'Read the post: %s',
				);

				// Les 6 derniers articles publies ; Polylang limite la requete
				// a la langue courante de la page.
				$ditl_actus = new WP_Query(
					array(
						'post_type'           => 'post',
						'post_status'         => 'publish',
						'posts_per_page'      => 6,
						'ignore_sticky_posts' => true,
						'no_found_rows'       => true,
					)
				);

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

						<?php if ( $ditl_actus->have_posts() ) { ?>
						<div class="ditl-boxed ditl-actus">
							<div class="ditl-boxed__inner">
								<?php // H2 reserve aux lecteurs d'ecran : evite le saut de titres H1 -> H3 des cartes (RGAA 9.1), sans effet visuel (la page d'origine n'a pas de titre de section). ?>
								<h2 class="screen-reader-text"><?php echo esc_html( $ditl_labels['region'] ); ?></h2>
								<div
									class="ditl-carousel"
									role="region"
									aria-roledescription="<?php echo esc_attr( $ditl_labels['role'] ); ?>"
									aria-label="<?php echo esc_attr( $ditl_labels['region'] ); ?>"
									data-label-pause="<?php echo esc_attr( $ditl_labels['pause'] ); ?>"
									data-label-resume="<?php echo esc_attr( $ditl_labels['resume'] ); ?>"
								>
									<div class="ditl-carousel__viewport">
										<ul class="ditl-carousel__track">
											<?php
											$ditl_position = 0;

											while ( $ditl_actus->have_posts() ) {
												$ditl_actus->the_post();

												$ditl_position++;
												$ditl_author_id  = (int) get_the_author_meta( 'ID' );
												$ditl_categories = get_the_category();
												?>
											<li
												class="ditl-carousel__slide"
												role="group"
												aria-label="<?php echo esc_attr( sprintf( $ditl_labels['slide'], $ditl_position, (int) $ditl_actus->post_count ) ); ?>"
											>
												<article class="ditl-actu-card">
													<div class="ditl-actu-card__image">
														<?php
														if ( has_post_thumbnail() ) {
															// Comme l'original : taille "medium", alt = titre de l'article.
															the_post_thumbnail(
																'medium',
																array(
																	'class' => 'ditl-actu-card__img',
																	'alt'   => the_title_attribute( array( 'echo' => false ) ),
																)
															);
														}
														?>
														<div class="ditl-actu-card__meta">
															<div class="ditl-actu-card__avatar">
																<?php echo get_avatar( $ditl_author_id, 48 ); ?>
															</div>
															<div class="ditl-actu-card__byline">
																<div class="ditl-actu-card__author">
																	<a href="<?php echo esc_url( get_author_posts_url( $ditl_author_id ) ); ?>"><?php echo esc_html( get_the_author() ); ?></a>
																</div>
																<div class="ditl-actu-card__date"><?php echo esc_html( get_the_date( 'F j, Y' ) ); ?></div>
															</div>
														</div>
													</div>
													<div class="ditl-actu-card__overlay">
														<div class="ditl-actu-card__content">
															<?php if ( ! empty( $ditl_categories ) ) { ?>
															<div class="ditl-actu-card__category">
																<a href="<?php echo esc_url( get_category_link( $ditl_categories[0] ) ); ?>"><?php echo esc_html( $ditl_categories[0]->name ); ?></a>
															</div>
															<?php } ?>
															<h3 class="ditl-actu-card__title">
																<a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( get_the_title() ); ?></a>
															</h3>
														</div>
														<div class="ditl-actu-card__more-wrap">
															<a
																href="<?php echo esc_url( get_permalink() ); ?>"
																class="ditl-actu-card__more"
																aria-label="<?php echo esc_attr( sprintf( $ditl_labels['read'], get_the_title() ) ); ?>"
															>
																<span class="ditl-actu-card__more-icon"><span></span></span>
															</a>
														</div>
													</div>
												</article>
											</li>
												<?php
											}

											wp_reset_postdata();
											?>
										</ul>
									</div>

									<button type="button" class="ditl-carousel__nav ditl-carousel__nav--prev" aria-label="<?php echo esc_attr( $ditl_labels['prev'] ); ?>">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 12H5.5M12.5 4.5 5 12l7.5 7.5"></path></svg>
									</button>
									<button type="button" class="ditl-carousel__nav ditl-carousel__nav--next" aria-label="<?php echo esc_attr( $ditl_labels['next'] ); ?>">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M4 12h14.5M12 4.5l7.5 7.5-7.5 7.5"></path></svg>
									</button>

									<button type="button" class="ditl-carousel__pause" aria-pressed="false" aria-label="<?php echo esc_attr( $ditl_labels['pause'] ); ?>" hidden>
										<svg class="ditl-carousel__icon-pause" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true" focusable="false"><rect x="1.5" y="1" width="3" height="10" rx="0.5"></rect><rect x="7.5" y="1" width="3" height="10" rx="0.5"></rect></svg>
										<svg class="ditl-carousel__icon-play" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true" focusable="false"><path d="M2.5 1.2v9.6L10.5 6z"></path></svg>
									</button>
								</div>
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
