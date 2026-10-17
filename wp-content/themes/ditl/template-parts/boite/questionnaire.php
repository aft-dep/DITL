<?php
/**
 * Boite a outils : vue questionnaire (maquettes V3 p. 2 a 22).
 *
 * Colonne laterale : H1 de la boite a outils, etapier, "Vos solutions".
 * Zone principale : retour a l'accueil, rappel de la reponse a l'etape
 * precedente, question en H2 PUIS "Question N sur M" (ordre du DOM demande
 * par Access42, l'ordre visuel inverse est obtenu en CSS), aide, formulaire
 * GET (etiquette visible, liste deroulante native, bouton Valider), liens
 * "Question precedente" / "Question suivante".
 *
 * Le formulaire ne transmet que les reponses des etapes PRECEDENTES et la
 * nouvelle reponse : modifier une reponse reinitialise les suivantes (regle
 * de la maquette p. 13). Quand le parcours est complet, le moteur affiche
 * directement les resultats.
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
$ditl_etapes   = ditl_boite_etapes();
$ditl_cle      = $ditl_parcours['etape_affichee'];

if ( ! isset( $ditl_etapes[ $ditl_cle ] ) ) {
	$ditl_cle = (string) key( $ditl_etapes );
}

$ditl_numero    = ditl_boite_numero_etape( $ditl_cle );
$ditl_total     = count( $ditl_etapes );
$ditl_textes_q  = $ditl_textes['etapes'][ $ditl_cle ];
$ditl_choix     = ditl_boite_choix_etape( $ditl_cle, $ditl_parcours );
$ditl_actuelle  = isset( $ditl_parcours['reponses'][ $ditl_cle ] ) ? $ditl_parcours['reponses'][ $ditl_cle ] : null;
$ditl_code_act  = $ditl_actuelle instanceof WP_Term ? ditl_boite_code_terme( $ditl_actuelle ) : '';
$ditl_prec      = ditl_boite_front_etape_voisine( $ditl_parcours, $ditl_cle, -1 );
$ditl_suiv      = ditl_boite_front_etape_voisine( $ditl_parcours, $ditl_cle, 1 );
$ditl_prec_rep  = '' !== $ditl_prec && isset( $ditl_parcours['reponses'][ $ditl_prec ] ) ? $ditl_parcours['reponses'][ $ditl_prec ] : null;
$ditl_lang      = $ditl_contexte['langue'];
$ditl_id_select = 'ditl-boite-choix-' . $ditl_cle;

$ditl_suivi = array(
	'vue'    => 'questionnaire',
	'langue' => $ditl_lang,
	'etape'  => $ditl_cle,
	'numero' => $ditl_numero,
);
?>
<div class="ditl-boite ditl-boite--questionnaire" data-ditl-boite-suivi="<?php echo esc_attr( ditl_boite_front_suivi_json( $ditl_suivi ) ); ?>">
	<div class="ditl-boite__conteneur">

		<div class="ditl-boite__colonne">
			<h1 class="ditl-boite__titre"><?php echo esc_html( $ditl_textes['titre'] ); ?></h1>
			<?php get_template_part( 'template-parts/boite/etapier', null, array( 'contexte' => $ditl_contexte ) ); ?>
		</div>

		<div class="ditl-boite__principal">

			<p class="ditl-boite-retour">
				<a href="<?php echo esc_url( ditl_boite_accueil_langue( $ditl_lang ) ); ?>">
					<?php echo ditl_boite_front_icone( 'retour' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
					<?php esc_html_e( 'Retourner à la page d\'accueil', 'ditl' ); ?>
				</a>
			</p>

			<?php if ( '' !== $ditl_parcours['invalide'] && isset( $ditl_textes['etapes'][ $ditl_parcours['invalide'] ] ) ) { ?>
			<p class="ditl-boite-message">
				<?php
				/* translators: %s : libelle court de la question (ex. Tache). */
				echo esc_html( sprintf( __( 'La réponse transmise pour la question « %s » n\'est pas disponible : choisissez une réponse dans la liste.', 'ditl' ), ditl_boite_front_libelle_etape( $ditl_textes, $ditl_parcours['invalide'] ) ) );
				?>
			</p>
			<?php } ?>

			<?php if ( $ditl_prec_rep instanceof WP_Term ) { ?>
			<div class="ditl-boite-rappel">
				<p class="ditl-boite-rappel__question"><?php echo esc_html( wp_strip_all_tags( $ditl_textes['etapes'][ $ditl_prec ]['question'] ) ); ?></p>
				<p class="ditl-boite-rappel__reponse">
					<span class="ditl-boite-rappel__valeur"><span class="screen-reader-text"><?php esc_html_e( 'Réponse :', 'ditl' ); ?></span> <?php echo esc_html( $ditl_prec_rep->name ); ?></span>
					<a class="ditl-boite-modifier" href="<?php echo esc_url( ditl_boite_front_url_etape( $ditl_parcours, $ditl_prec ) ); ?>" data-ditl-suivi-clic="modifier" data-ditl-suivi-etape="<?php echo esc_attr( $ditl_prec ); ?>">
						<?php echo ditl_boite_front_icone( 'crayon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
						<span class="screen-reader-text">
							<?php
							/* translators: %s : libelle court de la question (ex. Metier). */
							echo esc_html( sprintf( __( 'Modifier la réponse à la question %s', 'ditl' ), ditl_boite_front_libelle_etape( $ditl_textes, $ditl_prec ) ) );
							?>
						</span>
					</a>
				</p>
			</div>
			<?php } ?>

			<div class="ditl-boite-question">
				<div class="ditl-boite-question__entete">
					<h2 class="ditl-boite-question__titre" id="ditl-boite-question-titre"><?php echo esc_html( wp_strip_all_tags( $ditl_textes_q['question'] ) ); ?></h2>
					<p class="ditl-boite-question__numero">
						<?php
						/* translators: 1: numero de la question, 2: nombre total de questions. */
						echo esc_html( sprintf( __( 'Question %1$d sur %2$d', 'ditl' ), $ditl_numero, $ditl_total ) );
						?>
					</p>
				</div>

				<?php if ( '' !== trim( wp_strip_all_tags( (string) $ditl_textes_q['aide'] ) ) ) { ?>
				<div class="ditl-boite-question__aide">
					<?php echo wp_kses( $ditl_textes_q['aide'], ditl_boite_html_autorise() ); ?>
				</div>
				<?php } ?>

				<?php if ( array() !== $ditl_choix ) { ?>
				<form class="ditl-boite-formulaire" method="get" action="<?php echo esc_url( ditl_boite_url( array() ) ); ?>" data-ditl-suivi-formulaire="etape" data-ditl-suivi-etape="<?php echo esc_attr( $ditl_cle ); ?>" data-ditl-suivi-numero="<?php echo esc_attr( (string) $ditl_numero ); ?>">
					<?php
					// Reponses des etapes precedentes uniquement.
					foreach ( array_keys( $ditl_etapes ) as $ditl_cle_prec ) {
						if ( $ditl_cle_prec === $ditl_cle ) {
							break;
						}

						if ( isset( $ditl_parcours['reponses'][ $ditl_cle_prec ] ) && $ditl_parcours['reponses'][ $ditl_cle_prec ] instanceof WP_Term ) {
							?>
					<input type="hidden" name="<?php echo esc_attr( $ditl_cle_prec ); ?>" value="<?php echo esc_attr( ditl_boite_code_terme( $ditl_parcours['reponses'][ $ditl_cle_prec ] ) ); ?>">
							<?php
						}
					}
					?>
					<div class="ditl-boite-champ">
						<label class="ditl-boite-champ__etiquette" for="<?php echo esc_attr( $ditl_id_select ); ?>"><?php echo esc_html( wp_strip_all_tags( $ditl_textes_q['etiquette'] ) ); ?></label>
						<div class="ditl-boite-champ__liste">
							<select id="<?php echo esc_attr( $ditl_id_select ); ?>" name="<?php echo esc_attr( $ditl_cle ); ?>" required data-ditl-suivi-liste="<?php echo esc_attr( $ditl_cle ); ?>">
								<option value=""><?php esc_html_e( 'Choisir dans la liste', 'ditl' ); ?></option>
								<?php foreach ( $ditl_choix as $ditl_terme ) { ?>
									<?php $ditl_code = ditl_boite_code_terme( $ditl_terme ); ?>
								<option value="<?php echo esc_attr( $ditl_code ); ?>"<?php selected( $ditl_code, $ditl_code_act ); ?>><?php echo esc_html( $ditl_terme->name ); ?></option>
								<?php } ?>
							</select>
							<?php echo ditl_boite_front_icone( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
						</div>
					</div>
					<button type="submit" class="ditl-boite-bouton">
						<?php esc_html_e( 'Valider', 'ditl' ); ?> <span class="screen-reader-text">
						<?php
						/* translators: %d : numero de la question. */
						echo esc_html( sprintf( __( 'ma réponse à la question %d', 'ditl' ), $ditl_numero ) );
						?>
						</span>
					</button>
				</form>
				<?php } else { ?>
				<div class="ditl-boite-vide">
					<p><?php esc_html_e( 'Aucun choix n\'est disponible pour cette question pour le moment.', 'ditl' ); ?></p>
					<ul class="ditl-boite-liens">
						<?php if ( '' !== $ditl_prec ) { ?>
						<li><a href="<?php echo esc_url( ditl_boite_front_url_etape( $ditl_parcours, $ditl_prec ) ); ?>"><?php esc_html_e( 'Modifier la réponse précédente', 'ditl' ); ?></a></li>
						<?php } ?>
						<li><a href="<?php echo esc_url( ditl_boite_url( array( DITL_BOITE_PARAM_VUE => 'catalogue' ) ) ); ?>" data-ditl-suivi-clic="voir_tous"><?php esc_html_e( 'Voir tous les outils', 'ditl' ); ?></a></li>
					</ul>
				</div>
				<?php } ?>
			</div>

			<?php if ( '' !== $ditl_prec || ( $ditl_actuelle instanceof WP_Term && '' !== $ditl_suiv ) ) { ?>
			<ul class="ditl-boite-navigation">
				<?php if ( '' !== $ditl_prec ) { ?>
				<li class="ditl-boite-navigation__precedente">
					<a href="<?php echo esc_url( ditl_boite_front_url_etape( $ditl_parcours, $ditl_prec ) ); ?>">
						<?php esc_html_e( 'Question précédente', 'ditl' ); ?>
						<?php echo ditl_boite_front_icone( 'haut' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
					</a>
				</li>
				<?php } ?>
				<?php if ( $ditl_actuelle instanceof WP_Term && '' !== $ditl_suiv ) { ?>
				<li class="ditl-boite-navigation__suivante">
					<a href="<?php echo esc_url( ditl_boite_front_url_etape( $ditl_parcours, $ditl_suiv ) ); ?>">
						<?php esc_html_e( 'Question suivante', 'ditl' ); ?>
						<?php echo ditl_boite_front_icone( 'bas' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
					</a>
				</li>
				<?php } ?>
			</ul>
			<?php } ?>

		</div>

	</div>
</div>
