<?php
/**
 * Boite a outils : vues resultats ("Vos outils", maquettes finales du
 * 10/11/2026 p. 23 a 25) et catalogue ("Tous les outils", PROVISOIRE :
 * ecran non maquette, meme vue sans profil).
 *
 * Ordre du DOM : en-tete (fil d'Ariane "Accueil de la boite a outils >
 * Vos outils" ou "> Tous les outils", H1, lien vers l'autre vue),
 * colonne laterale (profil, filtres, compteur), puis les cartes. En deux
 * colonnes, la grille CSS place l'en-tete au-dessus des cartes : l'ordre de
 * lecture reste en-tete, filtres, resultats, a l'ecran comme au clavier.
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
$ditl_lang      = $ditl_contexte['langue'];
$ditl_requete   = $ditl_contexte['requete'];
$ditl_total     = (int) $ditl_contexte['total'];
$ditl_filtres   = array() !== ditl_boite_front_environnements_proposes( $ditl_contexte );
$ditl_colonne   = ! $ditl_catalogue || $ditl_filtres;

$ditl_suivi = array_merge(
	array(
		'vue'          => $ditl_catalogue ? 'catalogue' : 'resultats',
		'langue'       => $ditl_lang,
		'nb_resultats' => $ditl_total,
		'page'         => (int) $ditl_contexte['page'],
	),
	ditl_boite_front_suivi_parcours( $ditl_parcours )
);
?>
<div class="ditl-boite ditl-boite--liste ditl-boite--<?php echo esc_attr( $ditl_catalogue ? 'catalogue' : 'resultats' ); ?><?php echo $ditl_colonne ? '' : ' ditl-boite--sans-colonne'; ?>" data-ditl-boite-suivi="<?php echo esc_attr( ditl_boite_front_suivi_json( $ditl_suivi ) ); ?>">
	<div class="ditl-boite__conteneur">

		<div class="ditl-boite__entete">
			<?php
			ditl_boite_front_fil_ariane(
				array(
					array(
						'libelle' => __( 'Accueil de la boîte à outils', 'ditl' ),
						'url'     => ditl_boite_front_url_accueil_boite(),
					),
					ditl_boite_front_ariane_liste( $ditl_parcours, $ditl_textes ),
				)
			);
			?>

			<h1 class="ditl-boite__titre-liste"><?php echo esc_html( $ditl_catalogue ? $ditl_textes['catalogue_titre'] : $ditl_textes['resultats_titre'] ); ?></h1>

			<?php if ( $ditl_catalogue && '' !== trim( wp_strip_all_tags( (string) $ditl_textes['catalogue_intro'] ) ) ) { ?>
			<div class="ditl-boite__intro"><?php echo wp_kses( $ditl_textes['catalogue_intro'], ditl_boite_html_autorise() ); ?></div>
			<?php } ?>

			<p class="ditl-boite__autre-vue">
				<?php if ( $ditl_catalogue ) { ?>
				<a class="ditl-boite-bouton ditl-boite-bouton--petit" href="<?php echo esc_url( ditl_boite_url( array() ) ); ?>"><?php esc_html_e( 'Faire le questionnaire', 'ditl' ); ?></a>
				<?php } else { ?>
				<a class="ditl-boite-bouton ditl-boite-bouton--petit" href="<?php echo esc_url( ditl_boite_url( array( DITL_BOITE_PARAM_VUE => 'catalogue' ) ) ); ?>" data-ditl-suivi-clic="voir_tous"><?php esc_html_e( 'Voir tous les outils', 'ditl' ); ?></a>
				<?php } ?>
			</p>
		</div>

		<?php if ( $ditl_colonne ) { ?>
		<div class="ditl-boite__colonne">
			<?php if ( ! $ditl_catalogue ) { ?>
				<?php get_template_part( 'template-parts/boite/profil', null, array( 'contexte' => $ditl_contexte ) ); ?>
			<?php } ?>
			<?php if ( $ditl_filtres ) { ?>
				<?php get_template_part( 'template-parts/boite/filtres', null, array( 'contexte' => $ditl_contexte ) ); ?>
			<?php } ?>
		</div>
		<?php } ?>

		<div class="ditl-boite__principal">
			<?php
			if ( $ditl_requete instanceof WP_Query && $ditl_requete->have_posts() ) {
				// Vignettes chargees en une fois pour toute la page de resultats.
				update_post_thumbnail_cache( $ditl_requete );
				?>
			<?php // Compteur visible au-dessus des cartes (maquettes finales), qui sert aussi de titre (H2) a la section des cartes (H3). ?>
			<h2 class="ditl-boite-cartes__compteur"><?php echo esc_html( sprintf( _n( '%s résultat', '%s résultats', $ditl_total, 'ditl' ), number_format_i18n( $ditl_total ) ) ); ?></h2>
			<ul class="ditl-boite-cartes">
				<?php
				$ditl_rang = 0;

				while ( $ditl_requete->have_posts() ) {
					$ditl_requete->the_post();
					++$ditl_rang;
					?>
				<li class="ditl-boite-cartes__element">
					<?php
					get_template_part(
						'template-parts/boite/carte',
						null,
						array(
							'fiche_id' => get_the_ID(),
							'parcours' => $ditl_parcours,
							'niveau'   => 3,
							'position' => ( (int) $ditl_contexte['page'] - 1 ) * (int) $ditl_contexte['par_page'] + $ditl_rang,
							'immediat' => $ditl_rang <= 4,
							'vue'      => $ditl_catalogue ? 'catalogue' : 'resultats',
							'clic'     => 'ouverture_fiche',
						)
					);
					?>
				</li>
					<?php
				}

				wp_reset_postdata();
				?>
			</ul>
				<?php get_template_part( 'template-parts/boite/pagination', null, array( 'contexte' => $ditl_contexte ) ); ?>
			<?php } else { ?>
			<div class="ditl-boite-vide">
				<?php if ( ! $ditl_catalogue ) { ?>
					<?php echo wp_kses( $ditl_textes['aucun_resultat'], ditl_boite_html_autorise() ); ?>
				<?php } elseif ( array() !== $ditl_parcours['environnements'] ) { ?>
				<p><?php esc_html_e( 'Aucun outil ne correspond à ces filtres.', 'ditl' ); ?></p>
				<?php } else { ?>
				<p><?php esc_html_e( 'Aucun outil n\'est disponible pour le moment.', 'ditl' ); ?></p>
				<?php } ?>
				<?php if ( ! $ditl_catalogue || array() !== $ditl_parcours['environnements'] ) { ?>
				<ul class="ditl-boite-liens">
					<?php if ( array() !== $ditl_parcours['environnements'] ) { ?>
					<li><a href="<?php echo esc_url( ditl_boite_url( ditl_boite_args_parcours( $ditl_parcours, array( DITL_BOITE_PARAM_ENV => array(), DITL_BOITE_PARAM_PAGE => '' ) ) ) ); ?>"><?php esc_html_e( 'Retirer les filtres', 'ditl' ); ?></a></li>
					<?php } ?>
					<?php if ( ! $ditl_catalogue ) { ?>
					<li><a href="<?php echo esc_url( ditl_boite_url( array( DITL_BOITE_PARAM_VUE => 'catalogue' ) ) ); ?>" data-ditl-suivi-clic="voir_tous"><?php esc_html_e( 'Voir tous les outils', 'ditl' ); ?></a></li>
					<li><a href="<?php echo esc_url( ditl_boite_url( array() ) ); ?>" data-ditl-suivi-clic="reinitialiser"><?php esc_html_e( 'Recommencer le questionnaire', 'ditl' ); ?></a></li>
					<?php } ?>
				</ul>
				<?php } ?>
			</div>
			<?php } ?>
		</div>

	</div>
</div>
