<?php
/**
 * Banniere commune des gabarits DiTL sur mesure.
 *
 * Lit les metas de banniere de la page courante (voir inc/metaboxes/banniere.php) :
 * - _ditl_hero_image_id : image pleine largeur.
 * - _ditl_hero_title    : titre H1.
 *
 * Arguments optionnels (get_template_part(..., null, $args), utilises par les
 * gabarits Accueil et Partenaires ; les autres gabarits n'en passent pas,
 * rien ne change) :
 * - sous_titre     : sous-titre H2 affiche sous le H1.
 * - bouton_texte   : libelle du bouton d'appel a l'action.
 * - bouton_url     : URL du bouton (relative en meta, prefixee par l'URL du
 *                    site au rendu, meme regle que les autres gabarits).
 * - afficher_titre : false pour ne pas rendre le H1 dans la banniere (le
 *                    gabarit l'affiche alors lui-meme plus bas, ex. page
 *                    anglaise des Partenaires) ; true par defaut.
 *
 * Styles associes : assets/css/gabarits-communs.css (charge pour tous les
 * gabarits du registre ditl_gabarits_templates()).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ditl_hero_id    = absint( get_post_meta( get_the_ID(), '_ditl_hero_image_id', true ) );
$ditl_hero_title = (string) get_post_meta( get_the_ID(), '_ditl_hero_title', true );

// Complements optionnels passes en arguments (gabarit Accueil).
$ditl_hero_args = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'sous_titre'     => '',
		'bouton_texte'   => '',
		'bouton_url'     => '',
		'afficher_titre' => true,
	)
);

// URL relative en meta (portable entre environnements) :
// prefixee par l'URL du site au rendu (helper partage).
$ditl_hero_href = ditl_href_from_meta_url( $ditl_hero_args['bouton_url'] );
?>
<section class="ditl-hero">
	<div class="ditl-hero__col">
		<div class="ditl-hero__media">
			<div class="ditl-hero__spacer" aria-hidden="true"></div>
			<?php
			if ( $ditl_hero_id ) {
				echo wp_get_attachment_image(
					$ditl_hero_id,
					'full',
					false,
					array( 'class' => 'ditl-hero__image' )
				);
			}
			?>
		</div>
		<?php if ( $ditl_hero_args['afficher_titre'] && '' !== $ditl_hero_title ) { ?>
		<h1 class="ditl-hero__title"><?php echo esc_html( $ditl_hero_title ); ?></h1>
		<?php } ?>
		<?php if ( '' !== (string) $ditl_hero_args['sous_titre'] ) { ?>
		<h2 class="ditl-hero__sous-titre"><?php echo esc_html( $ditl_hero_args['sous_titre'] ); ?></h2>
		<?php } ?>
		<?php if ( '' !== (string) $ditl_hero_args['bouton_texte'] && '' !== $ditl_hero_href ) { ?>
		<div class="ditl-hero__action">
			<a class="ditl-bouton ditl-hero__bouton" href="<?php echo esc_url( $ditl_hero_href ); ?>"><?php echo esc_html( $ditl_hero_args['bouton_texte'] ); ?></a>
		</div>
		<?php } ?>
	</div>
</section>
