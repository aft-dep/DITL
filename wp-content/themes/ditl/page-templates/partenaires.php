<?php
/**
 * Template Name: Gabarit Partenaires
 *
 * Gabarit sur mesure remplacant le rendu Elementor des pages "Partenaires" /
 * "Partners". Banniere commune (metas, voir inc/metaboxes/banniere.php),
 * introduction, puis groupes de partenaires par pays : titre H2 du pays,
 * et pour chaque partenaire logo centre, titre H3 (HTML riche), texte de
 * presentation, bouton vers le site du partenaire et image complementaire
 * optionnelle. Un filet horizontal separe deux partenaires d'un meme pays
 * (jamais deux pays). Le contenu est lu dans les metas de la page (voir
 * inc/metaboxes/partenaires.php), le rendu reproduit a l'identique la mise
 * en page d'origine (iso-design).
 *
 * Variante structurelle heritee d'Elementor (a generaliser lors de la
 * migration des autres langues, phase 2) :
 * - pages francaises : H1 dans la banniere, espace final avant le footer,
 *   logo du dernier partenaire en taille reelle ;
 * - autres langues (reference : page anglaise) : banniere haute sans titre,
 *   H1 affiche au-dessus de l'introduction.
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
				$ditl_groupes       = ditl_get_meta_json( $ditl_post_id, '_ditl_partenaires' );
				$ditl_hero_title    = (string) get_post_meta( $ditl_post_id, '_ditl_hero_title', true );

				// Variante de structure selon la langue de la page (voir en-tete).
				$ditl_variante_fr = ditl_page_est_francaise();

				// Les groupes sans partenaire ne rendent rien : on les ecarte
				// avant de compter (la detection du dernier partenaire, qui
				// pilote la taille du logo, en depend).
				$ditl_groupes = array_values(
					array_filter(
						$ditl_groupes,
						function ( $ditl_groupe ) {
							return is_array( $ditl_groupe )
								&& isset( $ditl_groupe['partenaires'] )
								&& is_array( $ditl_groupe['partenaires'] )
								&& array() !== $ditl_groupe['partenaires'];
						}
					)
				);

				$ditl_nb_groupes = count( $ditl_groupes );

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
						// Variante francaise : H1 dans la banniere ; sinon la
						// banniere reste muette et le H1 est rendu ci-dessous.
						get_template_part(
							'template-parts/gabarit-hero',
							null,
							array( 'afficher_titre' => $ditl_variante_fr )
						);
						?>

						<?php if ( ( ! $ditl_variante_fr && '' !== $ditl_hero_title ) || '' !== $ditl_intro_content ) { ?>
						<div class="ditl-boxed ditl-part-entete">
							<div class="ditl-boxed__inner">
								<?php if ( ! $ditl_variante_fr && '' !== $ditl_hero_title ) { ?>
								<h1 class="ditl-hero__title"><?php echo esc_html( $ditl_hero_title ); ?></h1>
								<?php } ?>
								<?php if ( '' !== $ditl_intro_content ) { ?>
								<div class="ditl-part-intro"><?php echo wp_kses_post( ditl_format_rich_text( $ditl_intro_content ) ); ?></div>
								<?php } ?>
							</div>
						</div>
						<?php } ?>

						<?php
						foreach ( $ditl_groupes as $ditl_index_groupe => $ditl_groupe ) {
							$ditl_pays        = isset( $ditl_groupe['pays'] ) ? (string) $ditl_groupe['pays'] : '';
							$ditl_partenaires = isset( $ditl_groupe['partenaires'] ) && is_array( $ditl_groupe['partenaires'] ) ? $ditl_groupe['partenaires'] : array();

							if ( array() === $ditl_partenaires ) {
								continue;
							}

							$ditl_nb_partenaires = count( $ditl_partenaires );

							if ( '' !== $ditl_pays ) {
								?>
						<div class="ditl-boxed ditl-part-pays ditl-part-pays--<?php echo esc_attr( (string) ( $ditl_index_groupe + 1 ) ); ?>">
							<div class="ditl-boxed__inner">
								<h2 class="ditl-part-pays__titre"><?php echo esc_html( $ditl_pays ); ?></h2>
							</div>
						</div>
								<?php
							}

							foreach ( $ditl_partenaires as $ditl_index_part => $ditl_partenaire ) {
								$ditl_logo_id      = isset( $ditl_partenaire['logo_id'] ) ? absint( $ditl_partenaire['logo_id'] ) : 0;
								$ditl_titre        = isset( $ditl_partenaire['titre'] ) ? (string) $ditl_partenaire['titre'] : '';
								$ditl_texte        = isset( $ditl_partenaire['texte'] ) ? (string) $ditl_partenaire['texte'] : '';
								$ditl_bouton_texte = isset( $ditl_partenaire['bouton_texte'] ) ? (string) $ditl_partenaire['bouton_texte'] : '';
								$ditl_bouton_url   = isset( $ditl_partenaire['bouton_url'] ) ? (string) $ditl_partenaire['bouton_url'] : '';
								$ditl_extra_id     = isset( $ditl_partenaire['image_extra_id'] ) ? absint( $ditl_partenaire['image_extra_id'] ) : 0;

								// URL relative en meta (portable entre environnements) :
								// prefixee par l'URL du site au rendu (helper partage).
								$ditl_bouton_href = ditl_href_from_meta_url( $ditl_bouton_url );

								// Filet horizontal entre deux partenaires d'un meme
								// pays (l'original n'en mettait pas entre les pays).
								if ( $ditl_index_part > 0 ) {
									?>
						<div class="ditl-boxed ditl-part-filet">
							<div class="ditl-boxed__inner">
								<hr class="ditl-part-separateur" aria-hidden="true" />
							</div>
						</div>
									<?php
								}

								// Variante de la page francaise : le logo du dernier
								// partenaire (Institut Escola del Treball) etait insere
								// en taille reelle par Elementor, les autres en "medium".
								$ditl_logo_taille = 'medium';

								if ( $ditl_variante_fr
									&& $ditl_index_groupe === $ditl_nb_groupes - 1
									&& $ditl_index_part === $ditl_nb_partenaires - 1 ) {
									$ditl_logo_taille = 'full';
								}

								$ditl_logo_html = $ditl_logo_id ? wp_get_attachment_image( $ditl_logo_id, $ditl_logo_taille ) : '';

								// Image complementaire optionnelle : rien n'est rendu si
								// l'attachement a ete supprime de la mediatheque.
								$ditl_extra_html = $ditl_extra_id ? wp_get_attachment_image( $ditl_extra_id, 'full' ) : '';

								// Le libelle des boutons ("Site web") est identique d'un
								// partenaire a l'autre : le nom du partenaire est ajoute
								// au nom accessible du lien, sans changer le rendu.
								$ditl_titre_texte = trim( wp_strip_all_tags( $ditl_titre ) );
								?>
						<section class="ditl-boxed ditl-part-partenaire">
							<div class="ditl-boxed__inner">
								<?php if ( '' !== $ditl_logo_html ) { ?>
								<div class="ditl-part-logo"><?php echo $ditl_logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML genere par wp_get_attachment_image(). ?></div>
								<?php } ?>
								<?php if ( '' !== trim( wp_strip_all_tags( $ditl_titre ) ) ) { ?>
								<h3 class="ditl-part-nom"><?php echo wp_kses_post( $ditl_titre ); ?></h3>
								<?php } ?>
								<?php if ( '' !== $ditl_texte ) { ?>
								<div class="ditl-part-texte"><?php echo wp_kses_post( ditl_format_rich_text( $ditl_texte ) ); ?></div>
								<?php } ?>
								<?php if ( '' !== $ditl_bouton_texte && '' !== $ditl_bouton_href ) { ?>
								<div class="ditl-part-action">
									<a
										class="ditl-part-bouton"
										href="<?php echo esc_url( $ditl_bouton_href ); ?>"
										<?php if ( '' !== $ditl_titre_texte ) { ?>
										aria-label="<?php echo esc_attr( $ditl_bouton_texte . ' - ' . $ditl_titre_texte ); ?>"
										<?php } ?>
									><?php echo esc_html( $ditl_bouton_texte ); ?></a>
								</div>
								<?php } ?>
								<?php if ( '' !== $ditl_extra_html ) { ?>
								<div class="ditl-part-image-extra"><?php echo $ditl_extra_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML genere par wp_get_attachment_image(). ?></div>
								<?php } ?>
							</div>
						</section>
								<?php
							}
						}
						?>

						<?php if ( $ditl_variante_fr ) { ?>
						<div class="ditl-boxed ditl-part-fin">
							<div class="ditl-boxed__inner">
								<div class="ditl-part-espaceur" aria-hidden="true"></div>
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
