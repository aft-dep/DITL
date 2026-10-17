<?php
/**
 * Fiche detail d'un outil (type ditl_ressource, maquettes V3 p. 26-27).
 *
 * Fil d'Ariane, lien de retour (resultats du parcours, catalogue ou page
 * Boite a outils : ditl_boite_url_retour), H1, badges (types de
 * compensation, sous-type), bouton "Imprimer la fiche" (devis ; absent des
 * maquettes, revele par le script : sans JavaScript, l'impression du
 * navigateur reste disponible), illustration, "Apport de la compensation"
 * (contenu), "Points de vigilance", colonne "Informations" (rubriques vides
 * non rendues), carrousel "Explorer mes autres outils"
 * (ditl_boite_autres_fiches, sans defilement automatique).
 *
 * PROVISOIRE (a valider, Access42 laisse le choix) : la colonne
 * "Informations" est placee APRES le corps de la fiche dans le DOM ; elle
 * reste visible au defilement (position collante) au-dessus de 1024 px
 * seulement, et seulement si elle tient dans la fenetre (script).
 *
 * Impression (assets/css/boite-impression.css) : en-tete, menus, pied,
 * fil d'Ariane, boutons et carrousel masques ; adresse de la fiche imprimee
 * en pied (element reserve a l'impression).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header(); ?>

<?php if ( astra_page_layout() === 'left-sidebar' ) { ?>

	<?php get_sidebar(); ?>

<?php } ?>

	<div id="primary" <?php astra_primary_class(); ?>>

		<?php astra_primary_content_top(); ?>

		<main id="main" class="site-main">

			<?php
			while ( have_posts() ) {
				the_post();

				// Contenu protege par mot de passe : seul le formulaire du coeur est
				// rendu (aucune meta, aucune liste ni aucun lien de parcours tant
				// que le mot de passe n'est pas saisi).
				if ( post_password_required() ) {
					?>
				<div class="ditl-boite ditl-boite--protege">
					<h1 class="ditl-boite__titre"><?php echo esc_html( get_the_title() ); ?></h1>
					<?php echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formulaire du coeur, deja echappe. ?>
				</div>
					<?php
					continue;
				}

				$ditl_post_id  = get_the_ID();
				$ditl_contexte = ditl_boite_contexte();
				$ditl_parcours = $ditl_contexte['parcours'];
				$ditl_textes   = $ditl_contexte['textes'];
				$ditl_lang     = $ditl_contexte['langue'];
				$ditl_page_id  = (int) $ditl_contexte['page_id'];
				$ditl_vue      = isset( $ditl_parcours['vue'] ) ? $ditl_parcours['vue'] : 'questionnaire';

				// Retour et fil d'Ariane selon l'origine de la visite.
				if ( 'resultats' === $ditl_vue ) {
					$ditl_retour_libelle = __( 'Retourner à mes outils', 'ditl' );
					$ditl_ariane_liste   = $ditl_textes['resultats_titre'];
				} elseif ( 'catalogue' === $ditl_vue ) {
					$ditl_retour_libelle = __( 'Retourner à tous les outils', 'ditl' );
					$ditl_ariane_liste   = $ditl_textes['catalogue_titre'];
				} else {
					$ditl_retour_libelle = __( 'Retourner à la boîte à outils', 'ditl' );
					$ditl_ariane_liste   = '';
				}

				$ditl_url_retour = ditl_boite_url_retour( $ditl_parcours );
				$ditl_titre_boite = $ditl_page_id > 0 ? get_the_title( $ditl_page_id ) : $ditl_textes['titre'];

				$ditl_types      = array_merge( ditl_boite_front_termes_fiche( $ditl_post_id, 'ditl_type_compensation' ), ditl_boite_front_termes_fiche( $ditl_post_id, 'ditl_sous_type' ) );
				$ditl_image_id   = (int) get_post_thumbnail_id( $ditl_post_id );
				$ditl_image_alt  = trim( (string) get_post_meta( $ditl_post_id, '_ditl_image_alt', true ) );
				$ditl_vigilance  = (string) get_post_meta( $ditl_post_id, '_ditl_points_vigilance', true );
				$ditl_descriptif = (string) get_post_meta( $ditl_post_id, '_ditl_descriptif_technique', true );
				$ditl_apt_texte  = trim( (string) get_post_meta( $ditl_post_id, '_ditl_aptitude_texte', true ) );
				$ditl_fabricants = ditl_get_meta_json( $ditl_post_id, '_ditl_fabricants' );
				$ditl_sources    = ditl_get_meta_json( $ditl_post_id, '_ditl_sources' );
				$ditl_aptitudes  = ditl_boite_front_termes_fiche( $ditl_post_id, 'ditl_aptitude' );
				$ditl_types_comp = ditl_boite_front_termes_fiche( $ditl_post_id, 'ditl_type_compensation' );
				$ditl_envs       = ditl_boite_front_termes_fiche( $ditl_post_id, 'ditl_environnement' );
				$ditl_autres     = ditl_boite_front_autres_fiches();

				// Aptitudes : difficultes (niveau 1) et leurs empechements coches.
				$ditl_difficultes = array();

				foreach ( $ditl_aptitudes as $ditl_terme ) {
					if ( 0 === (int) $ditl_terme->parent ) {
						$ditl_difficultes[ (int) $ditl_terme->term_id ] = array(
							'terme'   => $ditl_terme,
							'enfants' => array(),
						);
					}
				}

				foreach ( $ditl_aptitudes as $ditl_terme ) {
					$ditl_parent_id = (int) $ditl_terme->parent;

					if ( 0 === $ditl_parent_id ) {
						continue;
					}

					if ( ! isset( $ditl_difficultes[ $ditl_parent_id ] ) ) {
						$ditl_parent = get_term( $ditl_parent_id, 'ditl_aptitude' );

						if ( ! $ditl_parent instanceof WP_Term ) {
							continue;
						}

						$ditl_difficultes[ $ditl_parent_id ] = array(
							'terme'   => $ditl_parent,
							'enfants' => array(),
						);
					}

					$ditl_difficultes[ $ditl_parent_id ]['enfants'][] = $ditl_terme->name;
				}

				// Fabricants et sources valides (URL http(s) ou nom seul).
				$ditl_liste_fabricants = array();

				foreach ( array_slice( (array) $ditl_fabricants, 0, 100 ) as $ditl_fabricant ) {
					$ditl_nom = isset( $ditl_fabricant['nom'] ) && is_scalar( $ditl_fabricant['nom'] ) ? trim( (string) $ditl_fabricant['nom'] ) : '';
					$ditl_url = isset( $ditl_fabricant['url'] ) && is_scalar( $ditl_fabricant['url'] ) ? ditl_boite_front_url_externe( (string) $ditl_fabricant['url'] ) : '';

					if ( '' !== $ditl_nom || '' !== $ditl_url ) {
						$ditl_liste_fabricants[] = array(
							'nom' => $ditl_nom,
							'url' => $ditl_url,
						);
					}
				}

				$ditl_liste_sources = array();

				foreach ( array_slice( (array) $ditl_sources, 0, 100 ) as $ditl_source ) {
					$ditl_url = is_scalar( $ditl_source ) ? ditl_boite_front_url_externe( (string) $ditl_source ) : '';

					if ( '' !== $ditl_url ) {
						$ditl_liste_sources[] = $ditl_url;
					}
				}

				// Colonne "Informations" rendue seulement si une rubrique a du contenu.
				$ditl_a_infos = array() !== $ditl_types_comp || array() !== $ditl_difficultes || '' !== $ditl_apt_texte || array() !== $ditl_envs || array() !== $ditl_liste_fabricants || '' !== trim( wp_strip_all_tags( $ditl_descriptif ) ) || array() !== $ditl_liste_sources;

				$ditl_contenu = apply_filters( 'the_content', get_the_content() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- filtre du coeur.

				$ditl_suivi = array(
					'vue'    => 'fiche',
					'langue' => $ditl_lang,
					'fiche'  => ditl_boite_front_id_fiche( $ditl_post_id ),
				);

				astra_entry_before();
				?>
				<article
				<?php
				echo wp_kses_post(
					astra_attr(
						'article-single',
						array(
							'id'    => 'post-' . $ditl_post_id,
							'class' => join( ' ', get_post_class() ),
						)
					)
				);
				?>
				>

					<header class="entry-header ast-no-title ast-header-without-markup"></header> <!-- .entry-header -->

					<div class="entry-content clear" itemprop="text">

						<div class="ditl-boite ditl-boite--fiche" data-ditl-boite-suivi="<?php echo esc_attr( ditl_boite_front_suivi_json( $ditl_suivi ) ); ?>">
							<div class="ditl-boite__conteneur">

								<nav class="ditl-boite-ariane" aria-label="<?php esc_attr_e( 'Fil d\'Ariane', 'ditl' ); ?>">
									<ol class="ditl-boite-ariane__liste">
										<li><a href="<?php echo esc_url( ditl_boite_accueil_langue( $ditl_lang ) ); ?>"><?php esc_html_e( 'Accueil', 'ditl' ); ?></a></li>
										<li><a href="<?php echo esc_url( ditl_boite_url( array() ) ); ?>"><?php echo esc_html( wp_strip_all_tags( $ditl_titre_boite ) ); ?></a></li>
										<?php if ( '' !== $ditl_ariane_liste ) { ?>
										<li><a href="<?php echo esc_url( $ditl_url_retour ); ?>"><?php echo esc_html( wp_strip_all_tags( $ditl_ariane_liste ) ); ?></a></li>
										<?php } ?>
										<li><a href="<?php echo esc_url( get_permalink( $ditl_post_id ) ); ?>" aria-current="page"><?php echo esc_html( get_the_title() ); ?></a></li>
									</ol>
								</nav>

								<p class="ditl-boite-retour">
									<a href="<?php echo esc_url( $ditl_url_retour ); ?>">
										<?php echo ditl_boite_front_icone( 'retour' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
										<?php echo esc_html( $ditl_retour_libelle ); ?>
									</a>
								</p>

								<div class="ditl-fiche<?php echo $ditl_a_infos ? '' : ' ditl-fiche--sans-infos'; ?>">

									<div class="ditl-fiche__entete">
										<h1 class="ditl-fiche__titre"><?php echo esc_html( get_the_title() ); ?></h1>
										<?php if ( array() !== $ditl_types ) { ?>
										<ul class="ditl-fiche__types" aria-label="<?php esc_attr_e( 'Type de compensation', 'ditl' ); ?>">
											<?php foreach ( $ditl_types as $ditl_terme ) { ?>
											<li class="ditl-fiche__type<?php echo 'ditl_sous_type' === $ditl_terme->taxonomy ? ' ditl-fiche__type--sous-type' : ''; ?>"><?php echo esc_html( $ditl_terme->name ); ?></li>
											<?php } ?>
										</ul>
										<?php } ?>
										<p class="ditl-fiche__actions">
											<button type="button" class="ditl-boite-bouton ditl-boite-bouton--petit ditl-fiche__imprimer" data-ditl-imprimer hidden>
												<?php echo ditl_boite_front_icone( 'imprimer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
												<?php esc_html_e( 'Imprimer la fiche', 'ditl' ); ?>
											</button>
										</p>
									</div>

									<div class="ditl-fiche__corps">
										<?php if ( $ditl_image_id > 0 ) { ?>
										<div class="ditl-fiche__illustration">
											<?php
											// Alternative saisie par langue (_ditl_image_alt) ; vide =
											// image decorative (revue Access42).
											echo wp_get_attachment_image(
												$ditl_image_id,
												'large',
												false,
												array(
													'class'   => 'ditl-fiche__img',
													'alt'     => $ditl_image_alt,
													'sizes'   => '(min-width: 1025px) 46rem, 92vw',
													'loading' => false,
												)
											);
											?>
										</div>
										<?php } ?>

										<?php if ( '' !== trim( wp_strip_all_tags( $ditl_contenu ) ) ) { ?>
										<section class="ditl-fiche__section" aria-labelledby="ditl-fiche-apport">
											<h2 class="ditl-fiche__soustitre" id="ditl-fiche-apport">
												<?php echo ditl_boite_front_icone( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
												<?php esc_html_e( 'Apport de la compensation', 'ditl' ); ?>
											</h2>
											<div class="ditl-fiche__texte">
												<?php echo $ditl_contenu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenu de la fiche filtre par the_content (comme le single du coeur). ?>
											</div>
										</section>
										<?php } ?>

										<?php if ( '' !== trim( wp_strip_all_tags( $ditl_vigilance ) ) ) { ?>
										<section class="ditl-fiche__section" aria-labelledby="ditl-fiche-vigilance">
											<h2 class="ditl-fiche__soustitre" id="ditl-fiche-vigilance">
												<?php echo ditl_boite_front_icone( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
												<?php esc_html_e( 'Points de vigilance', 'ditl' ); ?>
											</h2>
											<div class="ditl-fiche__texte">
												<?php echo wp_kses_post( ditl_format_rich_text( $ditl_vigilance ) ); ?>
											</div>
										</section>
										<?php } ?>
									</div>

									<?php if ( $ditl_a_infos ) { ?>
									<aside class="ditl-fiche__infos" aria-labelledby="ditl-fiche-infos-titre" data-ditl-infos>
										<h2 class="ditl-fiche__infos-titre" id="ditl-fiche-infos-titre"><?php esc_html_e( 'Informations', 'ditl' ); ?></h2>

										<?php if ( array() !== $ditl_types_comp ) { ?>
										<div class="ditl-fiche__rubrique">
											<?php echo ditl_boite_front_icone( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
											<div class="ditl-fiche__rubrique-corps">
												<h3 class="ditl-fiche__rubrique-titre"><?php echo esc_html( _n( 'Type de compensation', 'Types de compensation', count( $ditl_types_comp ), 'ditl' ) ); ?></h3>
												<p><?php echo esc_html( implode( ', ', wp_list_pluck( $ditl_types_comp, 'name' ) ) ); ?></p>
											</div>
										</div>
										<?php } ?>

										<?php if ( array() !== $ditl_difficultes || '' !== $ditl_apt_texte ) { ?>
										<div class="ditl-fiche__rubrique">
											<?php echo ditl_boite_front_icone( 'ampoule' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
											<div class="ditl-fiche__rubrique-corps">
												<h3 class="ditl-fiche__rubrique-titre"><?php esc_html_e( 'Aptitude', 'ditl' ); ?></h3>
												<?php if ( array() !== $ditl_difficultes ) { ?>
												<ul class="ditl-fiche__aptitudes">
													<?php foreach ( $ditl_difficultes as $ditl_difficulte ) { ?>
													<li>
														<?php
														if ( array() !== $ditl_difficulte['enfants'] ) {
															echo esc_html(
																sprintf(
																	/* translators: 1: difficulte d'aptitude, 2: empechements separes par des virgules. */
																	__( '%1$s : %2$s', 'ditl' ),
																	$ditl_difficulte['terme']->name,
																	implode( ', ', $ditl_difficulte['enfants'] )
																)
															);
														} else {
															echo esc_html( $ditl_difficulte['terme']->name );
														}
														?>
													</li>
													<?php } ?>
												</ul>
												<?php } ?>
												<?php if ( '' !== $ditl_apt_texte ) { ?>
												<p><?php echo nl2br( esc_html( $ditl_apt_texte ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- texte echappe, seuls les br sont ajoutes. ?></p>
												<?php } ?>
											</div>
										</div>
										<?php } ?>

										<?php if ( array() !== $ditl_envs ) { ?>
										<div class="ditl-fiche__rubrique">
											<?php echo ditl_boite_front_icone( 'lieu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
											<div class="ditl-fiche__rubrique-corps">
												<h3 class="ditl-fiche__rubrique-titre"><?php echo esc_html( _n( 'Environnement', 'Environnements', count( $ditl_envs ), 'ditl' ) ); ?></h3>
												<?php ditl_boite_front_badges_environnements( $ditl_post_id, 'ditl-fiche__environnements' ); ?>
											</div>
										</div>
										<?php } ?>

										<?php if ( array() !== $ditl_liste_fabricants ) { ?>
										<div class="ditl-fiche__rubrique">
											<?php echo ditl_boite_front_icone( 'usine' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
											<div class="ditl-fiche__rubrique-corps">
												<h3 class="ditl-fiche__rubrique-titre"><?php echo esc_html( _n( 'Fabricant', 'Fabricants', count( $ditl_liste_fabricants ), 'ditl' ) ); ?></h3>
												<ul class="ditl-fiche__liens">
													<?php foreach ( $ditl_liste_fabricants as $ditl_fabricant ) { ?>
													<li>
														<?php if ( '' !== $ditl_fabricant['url'] ) { ?>
														<a class="ditl-fiche__lien-externe" href="<?php echo esc_url( $ditl_fabricant['url'] ); ?>" data-ditl-suivi-clic="lien_externe"><?php echo esc_html( '' !== $ditl_fabricant['nom'] ? $ditl_fabricant['nom'] : ditl_boite_front_url_lisible( $ditl_fabricant['url'] ) ); ?></a>
															<?php if ( '' !== $ditl_fabricant['nom'] ) { ?>
														<span class="ditl-fiche__url"><?php echo esc_html( ditl_boite_front_url_lisible( $ditl_fabricant['url'] ) ); ?></span>
															<?php } ?>
														<?php } else { ?>
															<?php echo esc_html( $ditl_fabricant['nom'] ); ?>
														<?php } ?>
													</li>
													<?php } ?>
												</ul>
											</div>
										</div>
										<?php } ?>

										<?php if ( '' !== trim( wp_strip_all_tags( $ditl_descriptif ) ) ) { ?>
										<div class="ditl-fiche__rubrique">
											<?php echo ditl_boite_front_icone( 'engrenage' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
											<div class="ditl-fiche__rubrique-corps">
												<h3 class="ditl-fiche__rubrique-titre"><?php esc_html_e( 'Descriptif technique', 'ditl' ); ?></h3>
												<div class="ditl-fiche__texte"><?php echo wp_kses_post( ditl_format_rich_text( $ditl_descriptif ) ); ?></div>
											</div>
										</div>
										<?php } ?>

										<?php if ( array() !== $ditl_liste_sources ) { ?>
										<div class="ditl-fiche__rubrique">
											<?php echo ditl_boite_front_icone( 'document' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
											<div class="ditl-fiche__rubrique-corps">
												<h3 class="ditl-fiche__rubrique-titre"><?php echo esc_html( _n( 'Source', 'Sources', count( $ditl_liste_sources ), 'ditl' ) ); ?></h3>
												<ul class="ditl-fiche__liens">
													<?php foreach ( $ditl_liste_sources as $ditl_source ) { ?>
													<li><a class="ditl-fiche__lien-externe" href="<?php echo esc_url( $ditl_source ); ?>" data-ditl-suivi-clic="lien_externe"><?php echo esc_html( ditl_boite_front_url_lisible( $ditl_source ) ); ?></a></li>
													<?php } ?>
												</ul>
											</div>
										</div>
										<?php } ?>
									</aside>
									<?php } ?>

									<?php if ( array() !== $ditl_autres ) { ?>
									<section class="ditl-fiche__autres" aria-labelledby="ditl-fiche-autres-titre">
										<h2 class="ditl-fiche__soustitre" id="ditl-fiche-autres-titre">
											<?php echo ditl_boite_front_icone( 'loupe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
											<?php echo esc_html( 'resultats' === $ditl_vue ? __( 'Explorer mes autres outils', 'ditl' ) : __( 'Explorer d\'autres outils', 'ditl' ) ); ?>
										</h2>
										<div
											class="ditl-carousel ditl-boite-carrousel"
											role="region"
											aria-roledescription="<?php esc_attr_e( 'carrousel', 'ditl' ); ?>"
											aria-labelledby="ditl-fiche-autres-titre"
											data-autoplay="off"
										>
											<div class="ditl-carousel__viewport">
												<ul class="ditl-carousel__track">
													<?php
													$ditl_nb_autres = count( $ditl_autres );

													foreach ( array_values( $ditl_autres ) as $ditl_rang => $ditl_autre_id ) {
														?>
													<li
														class="ditl-carousel__slide"
														role="group"
														aria-label="<?php echo esc_attr( sprintf( /* translators: 1: rang de l'outil, 2: nombre d'outils. */ __( 'Outil %1$d sur %2$d', 'ditl' ), $ditl_rang + 1, $ditl_nb_autres ) ); ?>"
													>
														<?php
														get_template_part(
															'template-parts/boite/carte',
															null,
															array(
																'fiche_id' => $ditl_autre_id,
																'parcours' => $ditl_parcours,
																'niveau'   => 3,
																'position' => $ditl_rang + 1,
																'immediat' => false,
																'vue'      => 'fiche',
																'clic'     => 'autres_outils',
															)
														);
														?>
													</li>
														<?php
													}
													?>
												</ul>
											</div>

											<button type="button" class="ditl-carousel__nav ditl-carousel__nav--prev" aria-label="<?php esc_attr_e( 'Outils précédents', 'ditl' ); ?>">
												<?php echo ditl_boite_front_icone( 'gauche' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
											</button>
											<button type="button" class="ditl-carousel__nav ditl-carousel__nav--next" aria-label="<?php esc_attr_e( 'Outils suivants', 'ditl' ); ?>">
												<?php echo ditl_boite_front_icone( 'droite' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
											</button>
										</div>
									</section>
									<?php } ?>

								</div>

								<p class="ditl-boite-impression-seule ditl-fiche__adresse">
									<?php esc_html_e( 'Adresse de la fiche :', 'ditl' ); ?>
									<?php echo esc_html( get_permalink( $ditl_post_id ) ); ?>
								</p>

							</div>
						</div>

					</div><!-- .entry-content .clear -->

				</article><!-- #post-## -->
				<?php
				astra_entry_after();
			}
			?>

		</main><!-- #main -->

		<?php astra_primary_content_bottom(); ?>

	</div><!-- #primary -->

<?php if ( astra_page_layout() === 'right-sidebar' ) { ?>

	<?php get_sidebar(); ?>

<?php } ?>

<?php get_footer(); ?>
