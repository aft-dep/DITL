<?php
/**
 * Template Name: Gabarit Accueil
 *
 * Gabarit sur mesure remplacant le rendu Elementor des pages d'accueil.
 * Banniere commune etendue (sous-titre H2 et bouton passes en arguments a
 * template-parts/gabarit-hero.php), bloc de presentation en deux colonnes,
 * bloc des livrables (vignettes), liste dynamique des 6 derniers articles
 * de la langue courante (rendu identique au widget UPK buzz-list d'origine)
 * puis bloc partenaires : grille de logos ou carrousel selon le reglage de
 * la page (assets/js/ditl-carousel.js, sans dependance).
 * Le contenu est lu dans les metas de la page (voir inc/metaboxes/accueil.php).
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

				$ditl_hero         = ditl_get_meta_json( $ditl_post_id, '_ditl_accueil_hero' );
				$ditl_presentation = ditl_get_meta_json( $ditl_post_id, '_ditl_accueil_presentation' );
				$ditl_livrables    = ditl_get_meta_json( $ditl_post_id, '_ditl_accueil_livrables' );
				$ditl_actualites   = ditl_get_meta_json( $ditl_post_id, '_ditl_accueil_actualites' );
				$ditl_partenaires  = ditl_get_meta_json( $ditl_post_id, '_ditl_accueil_partenaires' );

				// Bloc Presentation.
				$ditl_pres_titre      = isset( $ditl_presentation['titre'] ) ? (string) $ditl_presentation['titre'] : '';
				$ditl_pres_texte      = isset( $ditl_presentation['texte'] ) ? (string) $ditl_presentation['texte'] : '';
				$ditl_pres_bouton_txt = isset( $ditl_presentation['bouton_texte'] ) ? (string) $ditl_presentation['bouton_texte'] : '';
				$ditl_pres_bouton_url = isset( $ditl_presentation['bouton_url'] ) ? (string) $ditl_presentation['bouton_url'] : '';
				$ditl_pres_image_id   = isset( $ditl_presentation['image_id'] ) ? absint( $ditl_presentation['image_id'] ) : 0;

				// URL relative en meta (portable entre environnements) :
				// prefixee par l'URL du site au rendu (helper partage).
				$ditl_pres_href = ditl_href_from_meta_url( $ditl_pres_bouton_url );

				$ditl_pres_visible = ( '' !== $ditl_pres_titre || '' !== trim( wp_strip_all_tags( $ditl_pres_texte ) ) || 0 !== $ditl_pres_image_id );

				// Bloc Livrables.
				$ditl_livr_titre = isset( $ditl_livrables['titre'] ) ? (string) $ditl_livrables['titre'] : '';
				$ditl_livr_intro = isset( $ditl_livrables['intro'] ) ? (string) $ditl_livrables['intro'] : '';
				$ditl_livr_items = isset( $ditl_livrables['items'] ) && is_array( $ditl_livrables['items'] ) ? $ditl_livrables['items'] : array();

				$ditl_livr_visible = ( '' !== $ditl_livr_titre || '' !== trim( wp_strip_all_tags( $ditl_livr_intro ) ) || array() !== $ditl_livr_items );

				// Transition Elementor : tant que l'intro stockee contient
				// encore les wrappers Elementor historiques (avant rejeu de
				// cli/migrate-accueil.php), sa mise en boite reste portee par
				// les regles .e-con du gabarit ; une fois l'intro normalisee
				// (contenu utile seul), le modificateur ci-dessous porte la
				// mise en boite equivalente. Le marqueur detecte est le meme
				// que la garde d'entree de la normalisation cote migration
				// (la classe precise des wrappers, qu'un texte saisi par un
				// editeur ne peut pas contenir par accident). A simplifier en
				// phase 2 avec la purge des metas Elementor.
				$ditl_livr_intro_normalisee = ( false === strpos( $ditl_livr_intro, 'elementor-widget-container' ) );

				// Bloc Actualites (liste dynamique, seul le titre est stocke).
				$ditl_actus_titre = isset( $ditl_actualites['titre'] ) ? (string) $ditl_actualites['titre'] : '';

				// Bloc Partenaires.
				$ditl_part_titre      = isset( $ditl_partenaires['titre'] ) ? (string) $ditl_partenaires['titre'] : '';
				$ditl_part_texte      = isset( $ditl_partenaires['texte'] ) ? (string) $ditl_partenaires['texte'] : '';
				$ditl_part_bouton_txt = isset( $ditl_partenaires['bouton_texte'] ) ? (string) $ditl_partenaires['bouton_texte'] : '';
				$ditl_part_bouton_url = isset( $ditl_partenaires['bouton_url'] ) ? (string) $ditl_partenaires['bouton_url'] : '';
				$ditl_part_carrousel  = ! empty( $ditl_partenaires['carrousel'] );
				$ditl_part_logo_ids   = array();

				if ( isset( $ditl_partenaires['logo_ids'] ) && is_array( $ditl_partenaires['logo_ids'] ) ) {
					foreach ( $ditl_partenaires['logo_ids'] as $ditl_logo_id ) {
						$ditl_logo_id = absint( $ditl_logo_id );

						if ( $ditl_logo_id > 0 ) {
							$ditl_part_logo_ids[] = $ditl_logo_id;
						}
					}
				}

				$ditl_part_href = ditl_href_from_meta_url( $ditl_part_bouton_url );

				$ditl_part_visible = ( '' !== $ditl_part_titre || '' !== trim( wp_strip_all_tags( $ditl_part_texte ) ) || array() !== $ditl_part_logo_ids );

				// Libelles selon la langue de la page (site multilingue sans
				// fichiers de traduction du theme : francais, sinon anglais).
				$ditl_fr     = ditl_page_est_francaise();
				$ditl_labels = array(
					'region' => $ditl_fr ? 'Logos des partenaires' : 'Partner logos',
					'role'   => $ditl_fr ? 'carrousel' : 'carousel',
					'slide'  => $ditl_fr ? 'Diapositive %1$d sur %2$d' : 'Slide %1$d of %2$d',
					'prev'   => $ditl_fr ? 'Logo précédent' : 'Previous logo',
					'next'   => $ditl_fr ? 'Logo suivant' : 'Next logo',
					'pause'  => $ditl_fr ? 'Mettre en pause le défilement automatique' : 'Pause automatic sliding',
					'resume' => $ditl_fr ? 'Reprendre le défilement automatique' : 'Resume automatic sliding',
				);

				// Les 6 derniers articles publies (requete partagee avec le
				// gabarit Actualites, voir functions.php).
				$ditl_actus = ditl_query_dernieres_actus();

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

						<?php
						get_template_part(
							'template-parts/gabarit-hero',
							null,
							array(
								'sous_titre'   => isset( $ditl_hero['sous_titre'] ) ? (string) $ditl_hero['sous_titre'] : '',
								'bouton_texte' => isset( $ditl_hero['bouton_texte'] ) ? (string) $ditl_hero['bouton_texte'] : '',
								'bouton_url'   => isset( $ditl_hero['bouton_url'] ) ? (string) $ditl_hero['bouton_url'] : '',
							)
						);
						?>

						<?php if ( $ditl_pres_visible ) { ?>
						<section class="ditl-accueil-pres">
							<div class="ditl-accueil-pres__inner">
								<div class="ditl-accueil-pres__texte-col">
									<?php if ( '' !== $ditl_pres_titre ) { ?>
									<h2 class="ditl-accueil-pres__titre"><?php echo esc_html( $ditl_pres_titre ); ?></h2>
									<?php } ?>
									<?php if ( '' !== $ditl_pres_texte ) { ?>
									<div class="ditl-accueil-pres__texte"><?php echo wp_kses_post( ditl_format_rich_text( $ditl_pres_texte ) ); ?></div>
									<?php } ?>
									<?php if ( '' !== $ditl_pres_bouton_txt && '' !== $ditl_pres_href ) { ?>
									<div class="ditl-accueil-pres__action">
										<a class="ditl-bouton ditl-accueil-pres__bouton" href="<?php echo esc_url( $ditl_pres_href ); ?>"><?php echo esc_html( $ditl_pres_bouton_txt ); ?></a>
									</div>
									<?php } ?>
								</div>
								<div class="ditl-accueil-pres__image-col">
									<?php
									if ( $ditl_pres_image_id ) {
										echo wp_get_attachment_image(
											$ditl_pres_image_id,
											'large',
											false,
											array( 'class' => 'ditl-accueil-pres__image' )
										);
									}
									?>
								</div>
							</div>
						</section>
						<?php } ?>

						<?php if ( $ditl_livr_visible ) { ?>
						<section class="ditl-accueil-livr">
							<div class="ditl-accueil-livr__inner">
								<?php if ( '' !== $ditl_livr_titre ) { ?>
								<h2 class="ditl-accueil-livr__titre"><?php echo esc_html( $ditl_livr_titre ); ?></h2>
								<?php } ?>
								<?php if ( '' !== $ditl_livr_intro ) { ?>
								<div class="ditl-accueil-livr__intro<?php echo $ditl_livr_intro_normalisee ? ' ditl-accueil-livr__intro--normalisee' : ''; ?>"><?php echo wp_kses_post( ditl_format_rich_text( $ditl_livr_intro ) ); ?></div>
								<?php } ?>
								<?php if ( array() !== $ditl_livr_items ) { ?>
								<div class="ditl-accueil-livr__grille">
									<?php
									foreach ( $ditl_livr_items as $ditl_item ) {
										$ditl_item_image_id = isset( $ditl_item['image_id'] ) ? absint( $ditl_item['image_id'] ) : 0;
										$ditl_item_texte    = isset( $ditl_item['texte'] ) ? (string) $ditl_item['texte'] : '';
										?>
									<div class="ditl-accueil-livr__cell">
										<div class="ditl-accueil-livr__vignette">
											<div class="ditl-accueil-livr__icone">
												<?php
												if ( $ditl_item_image_id ) {
													echo wp_get_attachment_image( $ditl_item_image_id, 'full' );
												}
												?>
											</div>
											<div class="ditl-accueil-livr__texte"><?php echo wp_kses_post( ditl_format_rich_text( $ditl_item_texte ) ); ?></div>
										</div>
									</div>
										<?php
									}
									?>
								</div>
								<?php } ?>
							</div>
						</section>
						<?php } ?>

						<?php if ( '' !== $ditl_actus_titre ) { ?>
						<div class="ditl-boxed ditl-accueil-actus-titre">
							<div class="ditl-boxed__inner">
								<h2 class="ditl-accueil-actus__titre"><?php echo esc_html( $ditl_actus_titre ); ?></h2>
							</div>
						</div>
						<?php } ?>

						<?php if ( $ditl_actus->have_posts() ) { ?>
						<div class="ditl-boxed ditl-accueil-actus">
							<div class="ditl-boxed__inner">
								<div class="ditl-actus-liste">
									<?php
									while ( $ditl_actus->have_posts() ) {
										$ditl_actus->the_post();

										$ditl_author_id  = (int) get_the_author_meta( 'ID' );
										$ditl_categories = get_the_category();
										?>
									<article class="ditl-actu-item">
										<div class="ditl-actu-item__img-wrap">
											<?php
											if ( has_post_thumbnail() ) {
												// Comme l'original : taille "medium", alt = titre de l'article.
												the_post_thumbnail(
													'medium',
													array(
														'class' => 'ditl-actu-item__img',
														'alt'   => the_title_attribute( array( 'echo' => false ) ),
													)
												);
											}
											?>
										</div>
										<div class="ditl-actu-item__contenu">
											<div class="ditl-actu-item__num" aria-hidden="true"></div>
											<div class="ditl-actu-item__inner">
												<?php if ( ! empty( $ditl_categories ) ) { ?>
												<div class="ditl-actu-item__categorie">
													<a href="<?php echo esc_url( get_category_link( $ditl_categories[0] ) ); ?>"><?php echo esc_html( $ditl_categories[0]->name ); ?></a>
												</div>
												<?php } ?>
												<h3 class="ditl-actu-item__titre">
													<a class="ditl-souligne-anime" href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( get_the_title() ); ?></a>
												</h3>
												<div class="ditl-actu-item__meta">
													<div class="ditl-actu-item__auteur">
														<span class="ditl-actu-item__par">by</span>
														<a href="<?php echo esc_url( get_author_posts_url( $ditl_author_id ) ); ?>"><?php echo esc_html( get_the_author() ); ?></a>
													</div>
													<div class="ditl-actu-item__date-wrap" data-separator="//">
														<div class="ditl-actu-item__date"><?php echo esc_html( get_the_date( 'F j, Y' ) ); ?></div>
													</div>
												</div>
											</div>
										</div>
									</article>
										<?php
									}

									wp_reset_postdata();
									?>
								</div>
							</div>
						</div>
						<?php } ?>

						<?php if ( $ditl_part_visible ) { ?>
						<section class="ditl-accueil-part">
							<div class="ditl-accueil-part__inner">
								<?php if ( '' !== $ditl_part_titre ) { ?>
								<h2 class="ditl-accueil-part__titre"><?php echo esc_html( $ditl_part_titre ); ?></h2>
								<?php } ?>
								<?php if ( '' !== $ditl_part_texte ) { ?>
								<div class="ditl-accueil-part__texte"><?php echo wp_kses_post( ditl_format_rich_text( $ditl_part_texte ) ); ?></div>
								<?php } ?>
								<?php if ( '' !== $ditl_part_bouton_txt && '' !== $ditl_part_href ) { ?>
								<div class="ditl-accueil-part__action">
									<a class="ditl-bouton ditl-accueil-part__bouton" href="<?php echo esc_url( $ditl_part_href ); ?>"><?php echo esc_html( $ditl_part_bouton_txt ); ?></a>
								</div>
								<?php } ?>

								<?php if ( array() !== $ditl_part_logo_ids && $ditl_part_carrousel ) { ?>
								<div class="ditl-accueil-part__carrousel">
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

												// Comme le widget d'origine : src simple, sans srcset
												// (rendu strictement identique des logos).
												add_filter( 'wp_calculate_image_srcset', '__return_false' );

												foreach ( $ditl_part_logo_ids as $ditl_logo_id ) {
													$ditl_position++;
													?>
												<li
													class="ditl-carousel__slide"
													role="group"
													aria-label="<?php echo esc_attr( sprintf( $ditl_labels['slide'], $ditl_position, count( $ditl_part_logo_ids ) ) ); ?>"
												>
													<?php echo wp_get_attachment_image( $ditl_logo_id, 'large', false, array( 'class' => 'ditl-accueil-part__logo' ) ); ?>
												</li>
													<?php
												}

												remove_filter( 'wp_calculate_image_srcset', '__return_false' );
												?>
											</ul>
										</div>

										<button type="button" class="ditl-carousel__nav ditl-carousel__nav--prev" aria-label="<?php echo esc_attr( $ditl_labels['prev'] ); ?>">
											<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true" focusable="false"><path d="M15 4.5 7.5 12l7.5 7.5"></path></svg>
										</button>
										<button type="button" class="ditl-carousel__nav ditl-carousel__nav--next" aria-label="<?php echo esc_attr( $ditl_labels['next'] ); ?>">
											<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true" focusable="false"><path d="M9 4.5 16.5 12 9 19.5"></path></svg>
										</button>

										<button type="button" class="ditl-carousel__pause" aria-pressed="false" aria-label="<?php echo esc_attr( $ditl_labels['pause'] ); ?>" hidden>
											<svg class="ditl-carousel__icon-pause" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true" focusable="false"><rect x="1.5" y="1" width="3" height="10" rx="0.5"></rect><rect x="7.5" y="1" width="3" height="10" rx="0.5"></rect></svg>
											<svg class="ditl-carousel__icon-play" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true" focusable="false"><path d="M2.5 1.2v9.6L10.5 6z"></path></svg>
										</button>
									</div>
								</div>
								<?php } elseif ( array() !== $ditl_part_logo_ids ) { ?>
								<ul class="ditl-accueil-part__grille">
									<?php foreach ( $ditl_part_logo_ids as $ditl_logo_id ) { ?>
									<li class="ditl-accueil-part__logo-item">
										<?php echo wp_get_attachment_image( $ditl_logo_id, 'large', false, array( 'class' => 'ditl-accueil-part__logo' ) ); ?>
									</li>
									<?php } ?>
								</ul>
								<?php } ?>
							</div>
						</section>
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
