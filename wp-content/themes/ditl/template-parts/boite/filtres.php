<?php
/**
 * Boite a outils : panneau "Filtrer les outils" (environnements).
 *
 * Formulaire GET applique au bouton (maquette p. 24) : reponses du parcours
 * en champs caches, cases env[] dans un fieldset (legende reservee aux
 * lecteurs d'ecran, le H2 faisant le titre visuel), compteur de resultats
 * en role="status" (annonce par le titre de page au rechargement).
 * Avec DITL_BOITE_MASQUER_CHOIX_VIDES (PROVISOIRE), un environnement sans
 * fiche parmi les resultats n'est pas propose, sauf s'il est deja coche.
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

$ditl_contexte  = isset( $args['contexte'] ) && is_array( $args['contexte'] ) ? $args['contexte'] : ditl_boite_contexte();
$ditl_parcours  = $ditl_contexte['parcours'];
$ditl_textes    = $ditl_contexte['textes'];
$ditl_catalogue = 'catalogue' === $ditl_parcours['vue'];
$ditl_total     = (int) $ditl_contexte['total'];
$ditl_proposes  = ditl_boite_front_environnements_proposes( $ditl_contexte );

if ( 0 === $ditl_total ) {
	$ditl_compteur = __( 'Aucun résultat', 'ditl' );
} else {
	/* translators: %s : nombre de resultats. */
	$ditl_compteur = sprintf( _n( '%s résultat', '%s résultats', $ditl_total, 'ditl' ), number_format_i18n( $ditl_total ) );
}
?>
<div class="ditl-boite-bloc ditl-boite-filtres">
	<h2 class="ditl-boite-bloc__titre" id="ditl-boite-filtres-titre"><?php echo esc_html( $ditl_textes['filtres_titre'] ); ?></h2>

	<form class="ditl-boite-filtres__formulaire" method="get" action="<?php echo esc_url( ditl_boite_url( array() ) ); ?>" data-ditl-suivi-formulaire="filtres">
		<?php foreach ( ditl_boite_args_parcours( $ditl_parcours, array( DITL_BOITE_PARAM_ENV => array(), DITL_BOITE_PARAM_ETAPE => '', DITL_BOITE_PARAM_PAGE => '' ) ) as $ditl_nom => $ditl_valeur ) { ?>
			<?php if ( is_scalar( $ditl_valeur ) ) { ?>
		<input type="hidden" name="<?php echo esc_attr( $ditl_nom ); ?>" value="<?php echo esc_attr( (string) $ditl_valeur ); ?>">
			<?php } ?>
		<?php } ?>

		<?php if ( array() !== $ditl_proposes ) { ?>
		<fieldset class="ditl-boite-filtres__groupe">
			<legend class="screen-reader-text"><?php esc_html_e( 'Environnements', 'ditl' ); ?></legend>
			<ul class="ditl-boite-filtres__liste">
				<?php foreach ( $ditl_proposes as $ditl_term_id => $ditl_env ) { ?>
					<?php
					$ditl_code = ditl_boite_code_terme( $ditl_env['terme'] );
					$ditl_id   = 'ditl-boite-env-' . sanitize_html_class( $ditl_code );
					$ditl_nb   = (int) $ditl_env['nombre'];
					/* translators: %s : nombre d'outils. */
					$ditl_nb_texte = sprintf( _n( '%s outil', '%s outils', $ditl_nb, 'ditl' ), number_format_i18n( $ditl_nb ) );
					?>
				<li class="ditl-boite-filtre">
					<input type="checkbox" id="<?php echo esc_attr( $ditl_id ); ?>" name="<?php echo esc_attr( DITL_BOITE_PARAM_ENV ); ?>[]" value="<?php echo esc_attr( $ditl_code ); ?>"<?php checked( ! empty( $ditl_env['coche'] ) ); ?>>
					<label for="<?php echo esc_attr( $ditl_id ); ?>"><?php echo esc_html( $ditl_env['terme']->name ); ?><span class="ditl-boite-filtre__nombre"><span aria-hidden="true"> (<?php echo esc_html( number_format_i18n( $ditl_nb ) ); ?>)</span><span class="screen-reader-text">(<?php echo esc_html( $ditl_nb_texte ); ?>)</span></span></label>
				</li>
				<?php } ?>
			</ul>
		</fieldset>
		<?php } ?>

		<div class="ditl-boite-filtres__actions">
			<?php if ( array() !== $ditl_proposes ) { ?>
			<button type="submit" class="ditl-boite-bouton ditl-boite-bouton--petit">
				<?php esc_html_e( 'Appliquer les filtres', 'ditl' ); ?>
				<?php echo ditl_boite_front_icone( 'actualiser' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
			</button>
			<?php } ?>
			<p class="ditl-boite-compteur" role="status"><?php echo esc_html( $ditl_compteur ); ?></p>
		</div>
	</form>
</div>
