<?php
/**
 * Boite a outils : recapitulatif "Votre profil" des resultats.
 *
 * Liste des reponses (intitule de la question annonce aux lecteurs
 * d'ecran), lien "Modifier" par reponse (icone crayon, nom accessible
 * complet), lien "Reinitialiser le questionnaire" (page sans parametre).
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
$ditl_parcours = $ditl_contexte['parcours'];
$ditl_textes   = $ditl_contexte['textes'];
?>
<div class="ditl-boite-bloc ditl-boite-profil">
	<h2 class="ditl-boite-bloc__titre"><?php echo esc_html( $ditl_textes['profil_titre'] ); ?></h2>
	<ul class="ditl-boite-profil__liste">
		<?php
		foreach ( array_keys( ditl_boite_etapes() ) as $ditl_cle ) {
			if ( ! isset( $ditl_parcours['reponses'][ $ditl_cle ] ) || ! $ditl_parcours['reponses'][ $ditl_cle ] instanceof WP_Term ) {
				continue;
			}

			$ditl_libelle = ditl_boite_front_libelle_etape( $ditl_textes, $ditl_cle );
			?>
		<li class="ditl-boite-profil__reponse">
			<span class="ditl-boite-profil__valeur">
				<?php
				/* translators: %s : libelle court de la question (ex. Metier). */
				$ditl_intitule = sprintf( __( '%s :', 'ditl' ), $ditl_libelle );
				?>
				<span class="screen-reader-text"><?php echo esc_html( $ditl_intitule ); ?></span> <?php echo esc_html( $ditl_parcours['reponses'][ $ditl_cle ]->name ); ?>
			</span>
			<a class="ditl-boite-modifier" href="<?php echo esc_url( ditl_boite_front_url_etape( $ditl_parcours, $ditl_cle ) ); ?>" data-ditl-suivi-clic="modifier" data-ditl-suivi-etape="<?php echo esc_attr( $ditl_cle ); ?>">
				<?php echo ditl_boite_front_icone( 'crayon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
				<span class="screen-reader-text">
					<?php
					/* translators: %s : libelle court de la question (ex. Metier). */
					echo esc_html( sprintf( __( 'Modifier la réponse à la question %s', 'ditl' ), $ditl_libelle ) );
					?>
				</span>
			</a>
		</li>
			<?php
		}
		?>
	</ul>
	<p class="ditl-boite-profil__reinitialiser">
		<a class="ditl-boite-bouton ditl-boite-bouton--petit" href="<?php echo esc_url( ditl_boite_url( array() ) ); ?>" data-ditl-suivi-clic="reinitialiser">
			<?php esc_html_e( 'Réinitialiser le questionnaire', 'ditl' ); ?>
			<?php echo ditl_boite_front_icone( 'actualiser' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
		</a>
	</p>
</div>
