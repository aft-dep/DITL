<?php
/**
 * Boite a outils : etapier du questionnaire et bouton "Vos solutions".
 *
 * Accessibilite (revue Access42) : liste ordonnee nommee, etape affichee en
 * aria-current="step", etat de chaque etape annonce par un texte reserve aux
 * lecteurs d'ecran (terminee, a venir, sans objet), navigation au Tab (pas
 * de motif a fleches). Une etape terminee affiche sa reponse et un lien
 * "Modifier" (icone crayon, nom accessible complet). "Vos solutions" est un
 * lien vers les resultats si le parcours est complet, sinon un element non
 * interactif d'aspect desactive, explique aux lecteurs d'ecran.
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
$ditl_affichee = 'questionnaire' === $ditl_parcours['vue'] ? $ditl_parcours['etape_affichee'] : '';
$ditl_sautees  = (array) $ditl_parcours['sautees'];
?>
<ol class="ditl-boite-etapier" aria-label="<?php esc_attr_e( 'Étapes du questionnaire', 'ditl' ); ?>">
	<?php
	foreach ( array_keys( ditl_boite_etapes() ) as $ditl_cle ) {
		$ditl_numero  = ditl_boite_numero_etape( $ditl_cle );
		$ditl_libelle = ditl_boite_front_libelle_etape( $ditl_textes, $ditl_cle );
		$ditl_reponse = isset( $ditl_parcours['reponses'][ $ditl_cle ] ) ? $ditl_parcours['reponses'][ $ditl_cle ] : null;

		if ( $ditl_cle === $ditl_affichee ) {
			$ditl_etat = 'courante';
			/* translators: %d : numero de la question. */
			$ditl_annonce = sprintf( __( 'Question %d :', 'ditl' ), $ditl_numero );
		} elseif ( in_array( $ditl_cle, $ditl_sautees, true ) ) {
			$ditl_etat = 'sautee';
			/* translators: %d : numero de la question. */
			$ditl_annonce = sprintf( __( 'Question %d, sans objet :', 'ditl' ), $ditl_numero );
		} elseif ( $ditl_reponse instanceof WP_Term ) {
			$ditl_etat = 'terminee';
			/* translators: %d : numero de la question. */
			$ditl_annonce = sprintf( __( 'Question %d, terminée :', 'ditl' ), $ditl_numero );
		} else {
			$ditl_etat = 'avenir';
			/* translators: %d : numero de la question. */
			$ditl_annonce = sprintf( __( 'Question %d, à venir :', 'ditl' ), $ditl_numero );
		}
		?>
	<li class="ditl-boite-etape ditl-boite-etape--<?php echo esc_attr( $ditl_etat ); ?>"<?php echo 'courante' === $ditl_etat ? ' aria-current="step"' : ''; ?>>
		<span class="ditl-boite-etape__marque" aria-hidden="true">
			<?php
			if ( 'terminee' === $ditl_etat ) {
				echo ditl_boite_front_icone( 'coche' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique.
			} else {
				echo esc_html( number_format_i18n( $ditl_numero ) );
			}
			?>
		</span>
		<span class="ditl-boite-etape__texte">
			<span class="ditl-boite-etape__entete">
				<span class="ditl-boite-etape__libelle"><span class="screen-reader-text"><?php echo esc_html( $ditl_annonce ); ?></span> <?php echo esc_html( $ditl_libelle ); ?></span>
				<?php if ( 'terminee' === $ditl_etat ) { ?>
				<a class="ditl-boite-modifier" href="<?php echo esc_url( ditl_boite_front_url_etape( $ditl_parcours, $ditl_cle ) ); ?>" data-ditl-suivi-clic="modifier" data-ditl-suivi-etape="<?php echo esc_attr( $ditl_cle ); ?>">
					<?php echo ditl_boite_front_icone( 'crayon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
					<span class="screen-reader-text">
						<?php
						/* translators: %s : libelle court de la question (ex. Metier). */
						echo esc_html( sprintf( __( 'Modifier la réponse à la question %s', 'ditl' ), $ditl_libelle ) );
						?>
					</span>
				</a>
				<?php } ?>
			</span>
			<?php if ( 'terminee' === $ditl_etat ) { ?>
			<span class="ditl-boite-etape__reponse"><span class="screen-reader-text"><?php esc_html_e( 'Réponse :', 'ditl' ); ?></span> <?php echo esc_html( $ditl_reponse->name ); ?></span>
			<?php } ?>
		</span>
	</li>
		<?php
	}
	?>
</ol>

<?php if ( $ditl_parcours['complet'] ) { ?>
<a class="ditl-boite-solutions" href="<?php echo esc_url( ditl_boite_url( ditl_boite_args_parcours( $ditl_parcours, array( DITL_BOITE_PARAM_ETAPE => '', DITL_BOITE_PARAM_VUE => '', DITL_BOITE_PARAM_PAGE => '' ) ) ) ); ?>"><?php echo esc_html( $ditl_textes['bouton_solutions'] ); ?></a>
<?php } else { ?>
<p class="ditl-boite-solutions ditl-boite-solutions--inactif"><?php echo esc_html( $ditl_textes['bouton_solutions'] ); ?> <span class="screen-reader-text"><?php esc_html_e( '(disponible une fois toutes les questions répondues)', 'ditl' ); ?></span></p>
<?php } ?>
