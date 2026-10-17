<?php
/**
 * Boite a outils : pagination des resultats et du catalogue.
 *
 * Rendue seulement s'il y a plus d'une page : <nav> nommee, liste de liens
 * (parametre pg, parcours et filtres conserves), page courante en
 * aria-current="page", ellipses decoratives, liens page precedente /
 * suivante aux intitules complets.
 *
 * Arguments : contexte (ditl_boite_contexte()).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ditl_contexte = isset( $args['contexte'] ) && is_array( $args['contexte'] ) ? $args['contexte'] : ditl_boite_contexte();
$ditl_pages    = (int) $ditl_contexte['pages'];
$ditl_courante = (int) $ditl_contexte['page'];
$ditl_parcours = $ditl_contexte['parcours'];

if ( $ditl_pages <= 1 ) {
	return;
}

$ditl_url_page = static function ( $page ) use ( $ditl_parcours ) {
	return ditl_boite_url( ditl_boite_args_parcours( $ditl_parcours, array( DITL_BOITE_PARAM_PAGE => $page > 1 ? $page : '' ) ) );
};
?>
<nav class="ditl-boite-pagination" aria-label="<?php esc_attr_e( 'Pagination des résultats', 'ditl' ); ?>">
	<ul class="ditl-boite-pagination__liste">
		<?php if ( $ditl_courante > 1 ) { ?>
		<li class="ditl-boite-pagination__element ditl-boite-pagination__element--precedente">
			<a href="<?php echo esc_url( $ditl_url_page( $ditl_courante - 1 ) ); ?>">
				<?php echo ditl_boite_front_icone( 'gauche' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Page précédente', 'ditl' ); ?></span>
			</a>
		</li>
		<?php } ?>
		<?php foreach ( ditl_boite_front_pages( $ditl_courante, $ditl_pages ) as $ditl_page ) { ?>
			<?php if ( 0 === $ditl_page ) { ?>
		<li class="ditl-boite-pagination__element ditl-boite-pagination__element--ellipse" aria-hidden="true">&hellip;</li>
			<?php } elseif ( $ditl_page === $ditl_courante ) { ?>
		<li class="ditl-boite-pagination__element">
			<a href="<?php echo esc_url( $ditl_url_page( $ditl_page ) ); ?>" aria-current="page"><span class="screen-reader-text"><?php esc_html_e( 'Page', 'ditl' ); ?></span> <?php echo esc_html( number_format_i18n( $ditl_page ) ); ?></a>
		</li>
			<?php } else { ?>
		<li class="ditl-boite-pagination__element">
			<a href="<?php echo esc_url( $ditl_url_page( $ditl_page ) ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Page', 'ditl' ); ?></span> <?php echo esc_html( number_format_i18n( $ditl_page ) ); ?></a>
		</li>
			<?php } ?>
		<?php } ?>
		<?php if ( $ditl_courante < $ditl_pages ) { ?>
		<li class="ditl-boite-pagination__element ditl-boite-pagination__element--suivante">
			<a href="<?php echo esc_url( $ditl_url_page( $ditl_courante + 1 ) ); ?>">
				<span class="screen-reader-text"><?php esc_html_e( 'Page suivante', 'ditl' ); ?></span>
				<?php echo ditl_boite_front_icone( 'droite' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
			</a>
		</li>
		<?php } ?>
	</ul>
</nav>
