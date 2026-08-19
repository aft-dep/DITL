<?php
/**
 * Migration du gabarit "Partenaires" : donnees Elementor -> metas sur mesure.
 *
 * Lit le JSON _elementor_data des pages passees en argument et remplit les
 * metas du gabarit (contrat decrit dans inc/metaboxes/partenaires.php).
 * Les pages FR (2420) et EN (3169) partagent la meme grammaire de widgets,
 * parcourue dans l'ordre du document :
 * - 1er widget image                -> _ditl_hero_image_id (ID d'attachement, jamais l'URL)
 * - heading h1                      -> _ditl_hero_title
 * - heading de niveau "p"           -> _ditl_intro_content (texte d'introduction)
 * - heading h2                      -> nouveau groupe pays dans _ditl_partenaires
 * - widget image dans un groupe     -> ouvre un nouveau partenaire (logo) ; une image qui
 *                                      suit un partenaire COMPLET (bouton pose) sans divider
 *                                      intermediaire est son image complementaire (cas de la
 *                                      photo apres le bouton Escola, page FR uniquement)
 * - heading h3                      -> titre RICHE du partenaire ouvert, conserve tel quel
 *                                      (em/strong/span style, y compris le span font-size
 *                                      des titres EN - verifie compatible wp_kses_post)
 * - text-editor                     -> texte riche du partenaire ouvert
 * - button                          -> bouton du partenaire ouvert (libelle + URL)
 * - divider                         -> separateur entre deux partenaires d'un meme pays :
 *                                      clot le partenaire en cours
 * - spacer                          -> decoratif, ignore (le template regenere les espacements)
 *
 * Les differences FR/EN (H1 dans ou hors du hero, logos URE/Escola distincts,
 * image complementaire et spacer final FR uniquement) sont absorbees par la
 * grammaire ci-dessus : aucune correction de contenu n'est appliquee.
 *
 * Pose aussi _wp_page_template = page-templates/partenaires.php.
 *
 * Les metas Elementor (_elementor_data, _elementor_edit_mode, ...) ne sont
 * PAS modifiees : elles restent en place comme sauvegarde dormante.
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod).
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/migrate-partenaires.php 2420 3169 dry-run
 *   wp eval-file wp-content/themes/ditl/cli/migrate-partenaires.php 2420 3169
 *
 * Le mode simulation accepte "dry-run" ou "--dry-run" en argument.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Usage : wp eval-file <script> <id> [<id>...] [dry-run] - voir le docblock.
	// En acces web direct, reponse muette pour ne rien reveler du fichier.
	http_response_code( 404 );
	exit( 1 );
}

// Bibliotheque commune des scripts CLI du theme.
require_once __DIR__ . '/commun.php';

if ( ! function_exists( 'ditl_migration_partenaires_bouton' ) ) {
	/**
	 * Extrait un bouton Elementor {texte, url} avec URL interne rendue relative.
	 *
	 * Les URLs internes dont la cible ne resout vers aucun contenu local sont
	 * migrees telles quelles mais signalees (decision client a venir, comme
	 * sur les gabarits precedents).
	 *
	 * @param array  $reglages Reglages du widget button.
	 * @param string $contexte Libelle du bouton pour les messages.
	 * @return array Tableau {texte, url}.
	 */
	function ditl_migration_partenaires_bouton( $reglages, $contexte ) {
		$texte = isset( $reglages['text'] ) ? sanitize_text_field( (string) $reglages['text'] ) : '';
		$url   = isset( $reglages['link']['url'] ) ? trim( (string) $reglages['link']['url'] ) : '';

		if ( '' !== $url ) {
			$url = ditl_cli_url_relative( $url );

			// URL interne (devenue relative) : verifie qu'elle mene quelque part.
			if ( 0 === strpos( $url, '/' ) && 0 === url_to_postid( home_url( $url ) ) ) {
				WP_CLI::warning( sprintf(
					'Bouton "%s" (%s) : URL interne "%s" sans contenu correspondant en local. Migree telle quelle, lien probablement bancal en prod (decision client a venir).',
					$texte,
					$contexte,
					$url
				) );
			}
		}

		return array(
			'texte' => $texte,
			'url'   => $url,
		);
	}
}

if ( ! function_exists( 'ditl_migration_partenaires_extraire' ) ) {
	/**
	 * Parcourt recursivement l'arbre Elementor et collecte les donnees du gabarit.
	 *
	 * L'etat de la collecte vit dans $data :
	 * - groupes            : groupes {pays, partenaires} en construction ;
	 * - nouveau_partenaire : true quand la prochaine image ouvre un nouveau
	 *   partenaire (apres un titre de pays ou un divider).
	 *
	 * @param array $elements Elements Elementor (containers et widgets).
	 * @param array $data     Donnees collectees (passees par reference).
	 */
	function ditl_migration_partenaires_extraire( $elements, &$data ) {
		foreach ( $elements as $element ) {
			if ( isset( $element['elType'] ) && 'widget' === $element['elType'] ) {
				$type     = isset( $element['widgetType'] ) ? $element['widgetType'] : '';
				$reglages = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

				// Reperes sur le groupe et le partenaire en cours.
				$idx_groupe     = count( $data['groupes'] ) - 1;
				$idx_partenaire = $idx_groupe >= 0 ? count( $data['groupes'][ $idx_groupe ]['partenaires'] ) - 1 : -1;

				switch ( $type ) {
					case 'heading':
						// Titre brut du widget (peut contenir du HTML sur les H3).
						$titre_brut = isset( $reglages['title'] ) ? (string) $reglages['title'] : '';
						// Version texte simple (entites decodees, balises retirees).
						$titre_texte = sanitize_text_field( html_entity_decode( wp_strip_all_tags( $titre_brut ), ENT_QUOTES, 'UTF-8' ) );
						// h2 est la valeur par defaut d'Elementor quand header_size est absent.
						$niveau = isset( $reglages['header_size'] ) && '' !== $reglages['header_size'] ? $reglages['header_size'] : 'h2';

						if ( 'h1' === $niveau && '' === $data['hero_title'] ) {
							$data['hero_title'] = $titre_texte;
						} elseif ( 'p' === $niveau && '' === $data['intro'] && $idx_groupe < 0 ) {
							// Texte d'introduction (avant le premier groupe pays).
							$data['intro'] = trim( $titre_brut );
						} elseif ( 'h2' === $niveau ) {
							// Nouveau groupe pays.
							$data['groupes'][] = array(
								'pays'        => $titre_texte,
								'partenaires' => array(),
							);
							$data['nouveau_partenaire'] = true;
						} elseif ( 'h3' === $niveau ) {
							// Titre riche du partenaire ouvert, conserve TEL QUEL
							// (em/strong/span style) : la fidelite du rendu prime,
							// wp_kses_post s'applique au sanitize de la meta.
							if ( $idx_partenaire >= 0 && '' === $data['groupes'][ $idx_groupe ]['partenaires'][ $idx_partenaire ]['titre'] ) {
								$data['groupes'][ $idx_groupe ]['partenaires'][ $idx_partenaire ]['titre'] = trim( $titre_brut );
							} else {
								WP_CLI::warning( sprintf( 'Heading h3 orphelin (aucun partenaire ouvert) ignore : "%s"', $titre_texte ) );
							}
						} else {
							WP_CLI::warning( sprintf( 'Heading %s inattendu ignore : "%s"', $niveau, $titre_texte ) );
						}
						break;

					case 'image':
						$image_id = isset( $reglages['image']['id'] ) ? absint( $reglages['image']['id'] ) : 0;

						if ( 0 === $data['hero_image_id'] && $idx_groupe < 0 ) {
							// Avant tout groupe : image de la banniere commune.
							$data['hero_image_id'] = $image_id;
						} elseif ( $idx_groupe < 0 ) {
							WP_CLI::warning( sprintf( 'Widget image orphelin (avant le premier pays, attachment %d) ignore.', $image_id ) );
						} elseif ( $data['nouveau_partenaire'] || $idx_partenaire < 0 ) {
							// Ouvre un nouveau partenaire dans le groupe en cours.
							$data['groupes'][ $idx_groupe ]['partenaires'][] = array(
								'logo_id'        => $image_id,
								'titre'          => '',
								'texte'          => '',
								'bouton_texte'   => '',
								'bouton_url'     => '',
								'image_extra_id' => 0,
							);
							$data['nouveau_partenaire'] = false;
						} elseif ( '' !== $data['groupes'][ $idx_groupe ]['partenaires'][ $idx_partenaire ]['bouton_texte']
							&& 0 === $data['groupes'][ $idx_groupe ]['partenaires'][ $idx_partenaire ]['image_extra_id'] ) {
							// Image qui suit un partenaire complet sans divider :
							// image complementaire (photo apres le bouton, FR).
							$data['groupes'][ $idx_groupe ]['partenaires'][ $idx_partenaire ]['image_extra_id'] = $image_id;
						} else {
							WP_CLI::warning( sprintf( 'Widget image inattendu (partenaire en cours incomplet, attachment %d) ignore.', $image_id ) );
						}
						break;

					case 'text-editor':
						// Le HTML est conserve tel quel ; wp_kses_post est applique
						// a l'enregistrement par le sanitize_callback de la meta.
						$html = isset( $reglages['editor'] ) ? trim( (string) $reglages['editor'] ) : '';

						if ( '' === $html ) {
							break;
						}

						$extrait = mb_substr( wp_strip_all_tags( $html ), 0, 60 );

						if ( $idx_partenaire >= 0 && '' === $data['groupes'][ $idx_groupe ]['partenaires'][ $idx_partenaire ]['texte'] ) {
							$data['groupes'][ $idx_groupe ]['partenaires'][ $idx_partenaire ]['texte'] = $html;
						} else {
							WP_CLI::warning( sprintf( 'Widget text-editor orphelin ignore : "%s..."', $extrait ) );
						}
						break;

					case 'button':
						if ( $idx_partenaire >= 0 && '' === $data['groupes'][ $idx_groupe ]['partenaires'][ $idx_partenaire ]['bouton_texte'] ) {
							$contexte = sprintf(
								'partenaire n.%d, groupe "%s"',
								$idx_partenaire + 1,
								$data['groupes'][ $idx_groupe ]['pays']
							);
							$bouton   = ditl_migration_partenaires_bouton( $reglages, $contexte );

							$data['groupes'][ $idx_groupe ]['partenaires'][ $idx_partenaire ]['bouton_texte'] = $bouton['texte'];
							$data['groupes'][ $idx_groupe ]['partenaires'][ $idx_partenaire ]['bouton_url']   = $bouton['url'];
						} else {
							WP_CLI::warning( 'Widget button orphelin (aucun partenaire ouvert) ignore.' );
						}
						break;

					case 'divider':
						// Separateur entre deux partenaires d'un meme pays :
						// la prochaine image ouvre un nouveau partenaire.
						if ( $idx_groupe >= 0 ) {
							$data['nouveau_partenaire'] = true;
						}
						break;

					case 'spacer':
						// Purement decoratif : le template regenere les espacements.
						break;

					default:
						WP_CLI::warning( sprintf( 'Widget "%s" inattendu ignore.', $type ) );
				}
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				ditl_migration_partenaires_extraire( $element['elements'], $data );
			}
		}
	}
}

// ---------------------------------------------------------------------------
// Lecture des arguments : IDs de pages + mode simulation eventuel.
// ---------------------------------------------------------------------------

$ditl_modes    = ditl_cli_lire_ids_et_dry_run( $args, 'de page' );
$ditl_dry_run  = $ditl_modes['dry_run'];
$ditl_page_ids = $ditl_modes['ids'];

// ---------------------------------------------------------------------------
// Traitement page par page.
// ---------------------------------------------------------------------------

foreach ( $ditl_page_ids as $ditl_page_id ) {
	$ditl_elements = ditl_cli_charger_arbre_elementor( $ditl_page_id, 'page' );

	if ( null === $ditl_elements ) {
		continue;
	}

	$ditl_data = array(
		'hero_image_id'      => 0,
		'hero_title'         => '',
		'intro'              => '',
		'groupes'            => array(),
		'nouveau_partenaire' => false,
	);

	ditl_migration_partenaires_extraire( $ditl_elements, $ditl_data );

	// Controles de coherence : la page de prod compte 5 pays et 8 partenaires.
	$ditl_nb_partenaires = 0;

	foreach ( $ditl_data['groupes'] as $ditl_groupe ) {
		$ditl_nb_partenaires += count( $ditl_groupe['partenaires'] );

		foreach ( $ditl_groupe['partenaires'] as $ditl_i => $ditl_partenaire ) {
			foreach ( array( 'titre', 'texte', 'bouton_texte' ) as $ditl_champ ) {
				if ( '' === $ditl_partenaire[ $ditl_champ ] ) {
					WP_CLI::warning( sprintf(
						'Partenaire n.%d du groupe "%s" : champ "%s" vide, verifier le decoupage.',
						$ditl_i + 1,
						$ditl_groupe['pays'],
						$ditl_champ
					) );
				}
			}

			ditl_cli_verifier_attachment( $ditl_partenaire['logo_id'], sprintf( 'logo du partenaire n.%d, groupe "%s"', $ditl_i + 1, $ditl_groupe['pays'] ) );
			ditl_cli_verifier_attachment( $ditl_partenaire['image_extra_id'], sprintf( 'image complementaire du partenaire n.%d, groupe "%s"', $ditl_i + 1, $ditl_groupe['pays'] ) );
		}
	}

	ditl_cli_verifier_attachment( $ditl_data['hero_image_id'], 'image de banniere' );

	if ( '' === $ditl_data['hero_title'] ) {
		WP_CLI::warning( sprintf( 'Page %d : aucun titre H1 trouve pour la banniere.', $ditl_page_id ) );
	}

	if ( '' === $ditl_data['intro'] ) {
		WP_CLI::warning( sprintf( 'Page %d : aucun texte d\'introduction trouve.', $ditl_page_id ) );
	}

	// Recapitulatif lisible des valeurs extraites.
	WP_CLI::log( sprintf( '  _ditl_hero_image_id : %d', $ditl_data['hero_image_id'] ) );
	WP_CLI::log( sprintf( '  _ditl_hero_title    : %s', $ditl_data['hero_title'] ) );
	WP_CLI::log( sprintf( '  _ditl_intro_content : %d caracteres', strlen( $ditl_data['intro'] ) ) );
	WP_CLI::log( sprintf( '  _ditl_partenaires   : %d groupe(s), %d partenaire(s)', count( $ditl_data['groupes'] ), $ditl_nb_partenaires ) );

	foreach ( $ditl_data['groupes'] as $ditl_groupe ) {
		WP_CLI::log( sprintf( '    [%s]', $ditl_groupe['pays'] ) );

		foreach ( $ditl_groupe['partenaires'] as $ditl_partenaire ) {
			WP_CLI::log( sprintf(
				'      logo %d | titre %d car. | texte %d car. | bouton "%s" -> %s%s',
				$ditl_partenaire['logo_id'],
				strlen( $ditl_partenaire['titre'] ),
				strlen( $ditl_partenaire['texte'] ),
				$ditl_partenaire['bouton_texte'],
				$ditl_partenaire['bouton_url'],
				$ditl_partenaire['image_extra_id'] ? sprintf( ' | image extra %d', $ditl_partenaire['image_extra_id'] ) : ''
			) );
		}
	}

	WP_CLI::log( sprintf( '  _wp_page_template   : %s', 'page-templates/partenaires.php' ) );

	if ( $ditl_dry_run ) {
		WP_CLI::log( '  [dry-run] Rien n\'a ete ecrit pour cette page.' );
		continue;
	}

	// Ecriture des metas. update_post_meta est idempotent ; wp_slash compense
	// le wp_unslash applique en interne (le JSON contient des antislashs).
	// Les sanitize_callback declares via register_post_meta s'appliquent ici aussi.
	update_post_meta( $ditl_page_id, '_ditl_hero_image_id', $ditl_data['hero_image_id'] );
	update_post_meta( $ditl_page_id, '_ditl_hero_title', wp_slash( $ditl_data['hero_title'] ) );
	update_post_meta( $ditl_page_id, '_ditl_intro_content', wp_slash( $ditl_data['intro'] ) );
	update_post_meta( $ditl_page_id, '_ditl_partenaires', wp_slash( (string) wp_json_encode( $ditl_data['groupes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	update_post_meta( $ditl_page_id, '_wp_page_template', 'page-templates/partenaires.php' );

	WP_CLI::success( sprintf( 'Page %d migree.', $ditl_page_id ) );
}

WP_CLI::log( '' );
WP_CLI::log( $ditl_dry_run ? 'Simulation terminee.' : 'Migration terminee.' );
