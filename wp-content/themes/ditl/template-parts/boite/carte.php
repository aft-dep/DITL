<?php
/**
 * Boite a outils : carte d'une fiche (resultats, catalogue, carrousel
 * "Explorer mes autres outils" de la fiche detail).
 *
 * Ordre du DOM : titre (contenant LE lien vers la fiche), badges
 * d'environnement, image decorative, "En savoir plus" (decoratif). L'ordre
 * visuel de la maquette (badges, image, titre) est obtenu en CSS (flex et
 * order, comme les cartes d'actualites du lot B1). Le lien est etendu a
 * toute la carte par un pseudo-element : une seule tabulation par carte.
 * Le conteneur de liste (li) est fourni par l'appelant.
 *
 * Arguments : fiche_id (int), parcours (contexte conserve dans le lien),
 * niveau (2 ou 3 : niveau du titre), position (rang affiche, suivi
 * d'audience), immediat (true : image sans chargement differe, premiere
 * rangee), vue (resultats | catalogue | fiche), clic (ouverture_fiche |
 * autres_outils : evenement de suivi).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ditl_fiche_id = isset( $args['fiche_id'] ) ? absint( $args['fiche_id'] ) : 0;

if ( $ditl_fiche_id <= 0 ) {
	return;
}

$ditl_parcours = isset( $args['parcours'] ) && is_array( $args['parcours'] ) ? $args['parcours'] : array();
$ditl_niveau   = isset( $args['niveau'] ) && 3 === (int) $args['niveau'] ? 3 : 2;
$ditl_position = isset( $args['position'] ) ? max( 1, (int) $args['position'] ) : 1;
$ditl_immediat = ! empty( $args['immediat'] );
$ditl_vue      = isset( $args['vue'] ) && is_string( $args['vue'] ) ? $args['vue'] : 'resultats';
$ditl_clic     = isset( $args['clic'] ) && 'autres_outils' === $args['clic'] ? 'autres_outils' : 'ouverture_fiche';
$ditl_url      = ditl_boite_url_fiche( $ditl_fiche_id, $ditl_parcours );
$ditl_image_id = (int) get_post_thumbnail_id( $ditl_fiche_id );
?>
<div class="ditl-boite-carte">
	<h<?php echo (int) $ditl_niveau; ?> class="ditl-boite-carte__titre">
		<a class="ditl-boite-carte__lien" href="<?php echo esc_url( $ditl_url ); ?>" data-ditl-suivi-clic="<?php echo esc_attr( $ditl_clic ); ?>" data-ditl-suivi-fiche="<?php echo esc_attr( ditl_boite_front_id_fiche( $ditl_fiche_id ) ); ?>" data-ditl-suivi-position="<?php echo esc_attr( (string) $ditl_position ); ?>" data-ditl-suivi-vue="<?php echo esc_attr( $ditl_vue ); ?>"><?php echo esc_html( get_the_title( $ditl_fiche_id ) ); ?></a>
	</h<?php echo (int) $ditl_niveau; ?>>

	<?php ditl_boite_front_badges_environnements( $ditl_fiche_id, 'ditl-boite-carte__badges' ); ?>

	<div class="ditl-boite-carte__image<?php echo $ditl_image_id > 0 ? '' : ' ditl-boite-carte__image--vide'; ?>">
		<?php
		if ( $ditl_image_id > 0 ) {
			// Vignette decorative (alt vide) : le titre de la carte porte
			// l'information (RGAA 1.2, revue Access42).
			echo wp_get_attachment_image(
				$ditl_image_id,
				'medium_large',
				false,
				array(
					'class'   => 'ditl-boite-carte__img',
					'alt'     => '',
					'sizes'   => '(min-width: 1025px) 18rem, (min-width: 600px) 45vw, 92vw',
					'loading' => $ditl_immediat ? false : 'lazy',
				)
			);
		} else {
			echo ditl_boite_front_icone( 'outil' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique.
		}
		?>
	</div>

	<span class="ditl-boite-carte__plus" aria-hidden="true"><?php esc_html_e( 'En savoir plus', 'ditl' ); ?><?php echo ditl_boite_front_icone( 'externe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?></span>
</div>
