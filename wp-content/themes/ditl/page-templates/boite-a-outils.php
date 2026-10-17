<?php
/**
 * Template Name: Gabarit Boite a outils
 *
 * Page "Boite a outils" (modele DITL_TPL_BOITE, voir inc/boite/commun.php).
 * Le parcours vit dans l'URL (formulaires GET, rendu cote serveur, aucun
 * JavaScript requis) ; trois vues selon ditl_boite_contexte() :
 * - questionnaire : etapier + question de l'etape affichee
 *   (template-parts/boite/questionnaire.php) ;
 * - resultats : profil, filtres par environnement, cartes des fiches
 *   (template-parts/boite/liste.php) ;
 * - catalogue : toutes les fiches, filtres par environnement, sans profil
 *   (PROVISOIRE, ecran non maquette ; meme morceau que les resultats).
 * Textes editables par page (meta _ditl_boite_textes, repli sur les textes
 * par defaut traduits) ; <title> et indexation geres par inc/boite/seo.php.
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

				// Contenu protege par mot de passe : seul le formulaire du coeur est
				// rendu (aucune meta, aucune liste ni aucun lien de parcours tant
				// que le mot de passe n'est pas saisi).
				if ( post_password_required() ) {
					?>
				<div class="ditl-boite ditl-boite--protege">
					<h1 class="ditl-boite__titre"><?php echo esc_html( get_the_title() ); ?></h1>
					<?php echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formulaire du coeur, deja echappe. ?>
				</div>
					<?php
					continue;
				}

				$ditl_post_id  = get_the_ID();
				$ditl_contexte = ditl_boite_contexte();
				$ditl_vue      = isset( $ditl_contexte['parcours']['vue'] ) ? $ditl_contexte['parcours']['vue'] : 'questionnaire';

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
							'template-parts/boite/' . ( 'questionnaire' === $ditl_vue ? 'questionnaire' : 'liste' ),
							null,
							array( 'contexte' => $ditl_contexte )
						);
						?>

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
