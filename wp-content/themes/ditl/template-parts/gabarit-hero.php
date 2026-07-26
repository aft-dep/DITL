<?php
/**
 * Banniere commune des gabarits DiTL sur mesure.
 *
 * Lit les metas de banniere de la page courante (voir inc/metaboxes/banniere.php) :
 * - _ditl_hero_image_id : image pleine largeur.
 * - _ditl_hero_title    : titre H1.
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
		<?php if ( '' !== $ditl_hero_title ) { ?>
		<h1 class="ditl-hero__title"><?php echo esc_html( $ditl_hero_title ); ?></h1>
		<?php } ?>
	</div>
</section>
