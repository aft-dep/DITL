<?php
/**
 * Page d'erreur 404 (maquettes finales du 10/11/2026, p. 33).
 *
 * Remplace le 404.php d'Astra (titre, sous-titre et formulaire de
 * recherche) dans la meme structure (en-tete, #primary, pied de page) :
 * "Code d'erreur : 404", titre H1 "Oups...", sous-titre, bouton vers
 * l'accueil de la langue courante, illustration decorative a droite.
 * Mise en page et feuille : inc/erreur-404.php, assets/css/erreur-404.css.
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

			<div class="ditl-404">
				<div class="ditl-404__conteneur">

					<p class="ditl-404__code"><?php esc_html_e( 'Code d\'erreur : 404', 'ditl' ); ?></p>

					<div class="ditl-404__corps">

						<div class="ditl-404__texte">
							<h1 class="ditl-404__titre"><?php esc_html_e( 'Oups...', 'ditl' ); ?></h1>
							<p class="ditl-404__soustitre"><?php esc_html_e( 'La page que vous cherchez est introuvable.', 'ditl' ); ?></p>
							<p class="ditl-404__action">
								<a class="ditl-404__bouton" href="<?php echo esc_url( ditl_404_url_accueil() ); ?>"><?php esc_html_e( 'Revenir à la page d\'accueil', 'ditl' ); ?></a>
							</p>
						</div>

						<div class="ditl-404__illustration">
							<?php get_template_part( 'template-parts/erreur-404-illustration' ); ?>
						</div>

					</div>

				</div>
			</div>

		</main><!-- #main -->

		<?php astra_primary_content_bottom(); ?>

	</div><!-- #primary -->

<?php if ( astra_page_layout() === 'right-sidebar' ) { ?>

	<?php get_sidebar(); ?>

<?php } ?>

<?php get_footer(); ?>
