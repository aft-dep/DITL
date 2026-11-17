<?php
/**
 * Boite a outils : panneau "Filtrer les outils" (environnements, metiers).
 *
 * Formulaire GET applique au bouton (maquette p. 24) : reponses du parcours
 * en champs caches, un fieldset par groupe de filtres (ditl_boite_filtres :
 * cases env[], puis poste[]), compteur de resultats en role="status"
 * (annonce par le titre de page au rechargement). Un seul groupe : legende
 * reservee aux lecteurs d'ecran, le H2 faisant le titre visuel (rendu
 * d'origine) ; deux groupes : legendes visibles ("Environnements",
 * "Metiers"). Metiers du catalogue : un fieldset par famille dans celui des
 * metiers (legende = famille).
 * Avec DITL_BOITE_MASQUER_CHOIX_VIDES (PROVISOIRE), un choix sans fiche
 * parmi les resultats n'est pas propose, sauf s'il est deja coche.
 * Sur telephone, le bloc s'affiche dans une fenetre modale (attribut
 * data-ditl-fenetre-bloc, voir ditl_boite_front_fenetre) ; le titre (ID
 * fixe) nomme alors la fenetre.
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
$ditl_groupes   = ditl_boite_front_groupes_filtres( $ditl_contexte );
$ditl_plusieurs = count( $ditl_groupes ) > 1;

if ( 0 === $ditl_total ) {
	$ditl_compteur = __( 'Aucun résultat', 'ditl' );
} else {
	/* translators: %s : nombre de resultats. */
	$ditl_compteur = sprintf( _n( '%s résultat', '%s résultats', $ditl_total, 'ditl' ), number_format_i18n( $ditl_total ) );
}
?>
<div class="ditl-boite-bloc ditl-boite-filtres" data-ditl-fenetre-bloc="filtres">
	<h2 class="ditl-boite-bloc__titre" id="ditl-boite-filtres-titre"><?php echo esc_html( $ditl_textes['filtres_titre'] ); ?></h2>

	<form class="ditl-boite-filtres__formulaire" method="get" action="<?php echo esc_url( ditl_boite_url( array() ) ); ?>" data-ditl-suivi-formulaire="filtres">
		<?php foreach ( ditl_boite_args_parcours( $ditl_parcours, ditl_boite_front_sans_filtres() + array( DITL_BOITE_PARAM_ETAPE => '', DITL_BOITE_PARAM_PAGE => '' ) ) as $ditl_nom => $ditl_valeur ) { ?>
			<?php if ( is_scalar( $ditl_valeur ) ) { ?>
		<input type="hidden" name="<?php echo esc_attr( $ditl_nom ); ?>" value="<?php echo esc_attr( (string) $ditl_valeur ); ?>">
			<?php } ?>
		<?php } ?>

		<?php foreach ( $ditl_groupes as $ditl_param => $ditl_groupe ) { ?>
		<fieldset class="ditl-boite-filtres__groupe">
			<legend class="<?php echo esc_attr( $ditl_plusieurs ? 'ditl-boite-filtres__legende' : 'screen-reader-text' ); ?>"><?php echo esc_html( $ditl_groupe['libelle'] ); ?></legend>
			<?php foreach ( $ditl_groupe['sous_groupes'] as $ditl_sous_groupe ) { ?>
				<?php if ( '' !== $ditl_sous_groupe['libelle'] ) { ?>
			<fieldset class="ditl-boite-filtres__sous-groupe">
				<legend class="ditl-boite-filtres__sous-legende"><?php echo esc_html( $ditl_sous_groupe['libelle'] ); ?></legend>
				<?php } ?>
			<ul class="ditl-boite-filtres__liste">
				<?php foreach ( $ditl_sous_groupe['choix'] as $ditl_term_id => $ditl_choix ) { ?>
					<?php
					$ditl_code = ditl_boite_code_terme( $ditl_choix['terme'] );
					$ditl_id   = 'ditl-boite-' . $ditl_param . '-' . sanitize_html_class( $ditl_code );
					$ditl_nb   = (int) $ditl_choix['nombre'];
					/* translators: %s : nombre d'outils. */
					$ditl_nb_texte = sprintf( _n( '%s outil', '%s outils', $ditl_nb, 'ditl' ), number_format_i18n( $ditl_nb ) );
					?>
				<li class="ditl-boite-filtre">
					<input type="checkbox" id="<?php echo esc_attr( $ditl_id ); ?>" name="<?php echo esc_attr( $ditl_param ); ?>[]" value="<?php echo esc_attr( $ditl_code ); ?>"<?php checked( ! empty( $ditl_choix['coche'] ) ); ?>>
					<label for="<?php echo esc_attr( $ditl_id ); ?>"><?php echo esc_html( $ditl_choix['terme']->name ); ?><span class="ditl-boite-filtre__nombre"><span aria-hidden="true"> (<?php echo esc_html( number_format_i18n( $ditl_nb ) ); ?>)</span><span class="screen-reader-text">(<?php echo esc_html( $ditl_nb_texte ); ?>)</span></span></label>
				</li>
				<?php } ?>
			</ul>
				<?php if ( '' !== $ditl_sous_groupe['libelle'] ) { ?>
			</fieldset>
				<?php } ?>
			<?php } ?>
		</fieldset>
		<?php } ?>

		<div class="ditl-boite-filtres__actions">
			<?php if ( array() !== $ditl_groupes ) { ?>
			<button type="submit" class="ditl-boite-bouton ditl-boite-bouton--petit">
				<?php esc_html_e( 'Appliquer les filtres', 'ditl' ); ?>
				<?php echo ditl_boite_front_icone( 'actualiser' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
			</button>
			<?php } ?>
			<p class="ditl-boite-compteur" role="status"><?php echo esc_html( $ditl_compteur ); ?></p>
		</div>
	</form>
</div>
