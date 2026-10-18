<?php
/**
 * Template Name: Gabarit Reglementation
 *
 * Gabarit de la page "Reglementation" (phase 6) : presentation des
 * ecosystemes nationaux, un onglet par pays, une zone depliable par
 * question. Une page par langue porte ce gabarit ; les pays sont des
 * publications du type "ditl_reglementation" de la langue de la page
 * (voir inc/reglementation.php et inc/metaboxes/reglementation.php).
 *
 * Contenu rendu, dans l'ordre :
 * - le lien de retour (meta _ditl_regl_retour), seulement si son texte ET
 *   son URL sont renseignes ;
 * - le H1 (meta _ditl_hero_title de la metabox commune, repli sur le titre
 *   de la page : jamais de page sans H1) ;
 * - les pays affichables (ditl_reglementation_pays_affichables, regles
 *   d'affichage deja appliquees) : aucun pays = rien apres le H1 ; un seul
 *   pays = son titre et ses questions, sans onglet ; deux pays ou plus = un
 *   sommaire de liens vers les pays, transforme en onglets par le script.
 *
 * Amelioration progressive : le HTML serveur fonctionne sans JavaScript
 * (sommaire de liens d'ancre, tous les pays et toutes les reponses
 * visibles, titres h2 des pays visibles). assets/js/ditl-reglementation.js
 * pose ensuite les motifs ARIA Tabs (sommaire -> onglets, pays ->
 * panneaux) et Disclosure (question -> bouton aria-expanded, reponse
 * repliee) : aucun role ni attribut ARIA n'est ecrit ici, pour ne rien
 * annoncer qui ne fonctionnerait pas sans script.
 *
 * Images : drapeaux et illustrations sont decoratifs (alt vide force), le
 * texte adjacent porte l'information. Contenu riche des reponses : wpautop
 * (filet pour un contenu enregistre sans balises <p>, par l'editeur avant le
 * theme 0.26.0 ou par un import) puis ditl_format_rich_text, filtre par
 * wp_kses_post au rendu.
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

				// H1 : titre saisi dans la metabox commune, repli sur le titre
				// de la page.
				$ditl_titre_page = trim( (string) get_post_meta( $ditl_post_id, '_ditl_hero_title', true ) );

				if ( '' === $ditl_titre_page ) {
					$ditl_titre_page = trim( wp_strip_all_tags( get_the_title() ) );
				}

				// Lien de retour : rendu seulement si texte ET URL (URL interne
				// relative en meta, prefixee par l'URL du site au rendu).
				$ditl_retour       = ditl_get_meta_json( $ditl_post_id, '_ditl_regl_retour' );
				$ditl_retour_texte = isset( $ditl_retour['texte'] ) && is_scalar( $ditl_retour['texte'] ) ? trim( (string) $ditl_retour['texte'] ) : '';
				$ditl_retour_url   = isset( $ditl_retour['url'] ) && is_string( $ditl_retour['url'] ) ? esc_url( ditl_href_from_meta_url( trim( $ditl_retour['url'] ) ) ) : '';

				// Pays lus une seule fois par affichage ; le script (onglets,
				// questions depliables), declare par inc/reglementation.php,
				// n'est mis en file d'attente que s'il y a un pays a afficher :
				// il est imprime en pied de page par wp_footer.
				$ditl_pays         = ditl_reglementation_pays_affichables();
				$ditl_avec_onglets = count( $ditl_pays ) > 1;

				if ( array() !== $ditl_pays ) {
					wp_enqueue_script( 'ditl-reglementation' );
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

						<div class="ditl-regl">
							<div class="ditl-regl__inner">

								<?php if ( '' !== $ditl_retour_texte && '' !== $ditl_retour_url ) { ?>
								<div class="ditl-regl__retour">
									<a class="ditl-regl__retour-lien" href="<?php echo $ditl_retour_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- echappee par esc_url() ci-dessus. ?>">
										<svg class="ditl-regl__retour-fleche" width="24" height="16" viewBox="0 0 24 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M23 8H1.5M8.5 1 1.5 8l7 7"></path></svg>
										<span class="ditl-regl__retour-texte"><?php echo esc_html( $ditl_retour_texte ); ?></span>
									</a>
								</div>
								<?php } ?>

								<h1 class="ditl-regl__titre" id="ditl-regl-titre"><?php echo esc_html( ditl_reglementation_insecables( $ditl_titre_page ) ); ?></h1>

								<?php if ( $ditl_avec_onglets ) { ?>
								<ul class="ditl-regl__onglets">
									<?php
									foreach ( $ditl_pays as $ditl_un_pays ) {
										// Drapeau decoratif, visible des le chargement (pas de
										// chargement differe).
										$ditl_drapeau = ditl_reglementation_image(
											$ditl_un_pays['drapeau_id'],
											'thumbnail',
											array(
												'class'    => 'ditl-regl__drapeau',
												'loading'  => false,
												'decoding' => 'async',
											),
											array( 25, 25 )
										);
										?>
									<li class="ditl-regl__onglet-item">
										<a class="ditl-regl__onglet" id="<?php echo esc_attr( 'ditl-regl-onglet-' . $ditl_un_pays['ancre'] ); ?>" href="<?php echo esc_attr( '#' . $ditl_un_pays['ancre'] ); ?>">
											<?php echo $ditl_drapeau; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML echappe par ditl_reglementation_image(). ?>
											<span class="ditl-regl__onglet-nom"><?php echo esc_html( $ditl_un_pays['titre'] ); ?></span>
										</a>
									</li>
										<?php
									}
									?>
								</ul>
								<?php } ?>

								<?php foreach ( $ditl_pays as $ditl_un_pays ) { ?>
								<section class="ditl-regl__pays" id="<?php echo esc_attr( $ditl_un_pays['ancre'] ); ?>">

									<h2 class="ditl-regl__pays-titre"><?php echo esc_html( $ditl_un_pays['titre'] ); ?></h2>

									<?php
									foreach ( $ditl_un_pays['sections'] as $ditl_section ) {
										$ditl_contenu = (string) $ditl_section['contenu'];

										// Illustration decorative. Largeur d'affichage : 1300 px
										// au plus (conteneur), pleine largeur moins les
										// gouttieres en deca.
										$ditl_image = ditl_reglementation_image(
											$ditl_section['image_id'],
											'1536x1536',
											array(
												'class'    => 'ditl-regl__illustration',
												'sizes'    => '(max-width: 1024px) calc(100vw - 40px), (max-width: 1380px) calc(100vw - 80px), 1300px',
												'loading'  => 'lazy',
												'decoding' => 'async',
											)
										);

										// Un contenu enregistre sans balises <p> (editeur avant
										// le theme 0.26.0, import) retrouve ses paragraphes ;
										// sans effet sur un contenu deja balise.
										$ditl_texte = ditl_reglementation_contenu_vide( $ditl_contenu ) ? '' : wp_kses_post( ditl_format_rich_text( wpautop( $ditl_contenu ) ) );
										?>
									<div class="ditl-regl__section">
										<h3 class="ditl-regl__question"><?php echo esc_html( ditl_reglementation_insecables( $ditl_section['titre'] ) ); ?></h3>
										<div class="ditl-regl__reponse" id="<?php echo esc_attr( $ditl_section['ancre'] ); ?>">
											<?php echo $ditl_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML echappe par ditl_reglementation_image(). ?>
											<?php if ( '' !== $ditl_texte ) { ?>
											<div class="ditl-regl__texte"><?php echo $ditl_texte; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filtre par wp_kses_post() ci-dessus. ?></div>
											<?php } ?>
										</div>
									</div>
										<?php
									}
									?>

								</section>
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
