<?php
/**
 * Migration du gabarit "Resultats" : donnees Elementor -> metas sur mesure.
 *
 * Lit le JSON _elementor_data des pages passees en argument et remplit les
 * metas du gabarit (contrat decrit dans inc/metaboxes/resultats.php) :
 * - 1er widget image                  -> _ditl_hero_image_id (ID d'attachement, jamais l'URL)
 * - heading h1                        -> _ditl_hero_title
 * - text-editors avant le premier h2  -> _ditl_intro_content (concatenes, vides ignores)
 * - chaque heading h2 (defaut)        -> nouvelle section {title, content}
 * - text-editors et headings h3       -> contenu de la section courante, dans l'ordre du
 *                                        document (les h3 serialises en <h3>texte</h3>)
 * - widget divider                    -> fin de la section courante (rien n'est stocke,
 *                                        le template regenere les separateurs)
 * - widget upk-banner (1er rencontre) -> _ditl_bandeau_image_id, _ditl_bandeau_texte,
 *                                        _ditl_bandeau_bouton_texte, _ditl_bandeau_bouton_url
 *                                        (URL interne stockee RELATIVE pour survivre aux
 *                                        changements d'environnement)
 * Les spacers et les text-editors vides sont ignores.
 * Pose aussi _wp_page_template = page-templates/resultats.php.
 *
 * L'arbre Elementor est parcouru recursivement dans l'ordre du document :
 * selon la langue, les widgets vivent dans des conteneurs imbriques
 * differemment (FR a plat, EN tres imbrique).
 *
 * Les metas Elementor (_elementor_data, _elementor_edit_mode, ...) ne sont
 * PAS modifiees : elles restent en place comme sauvegarde dormante.
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod).
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/migrate-resultats.php 3538 3171 dry-run
 *   wp eval-file wp-content/themes/ditl/cli/migrate-resultats.php 3538 3171
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

if ( ! function_exists( 'ditl_migration_resultats_url_relative' ) ) {
	/**
	 * Rend relative une URL pointant vers le site lui-meme.
	 *
	 * Les URLs des JSON Elementor pointent vers la production
	 * (https://ditlproject.eu/...) : on ne conserve que le chemin pour que
	 * la valeur reste valable en local, preprod et prod. Les URLs externes
	 * sont laissees intactes.
	 *
	 * @param string $url URL a normaliser.
	 * @return string URL relative si interne, inchangee sinon.
	 */
	function ditl_migration_resultats_url_relative( $url ) {
		$parties = wp_parse_url( $url );

		if ( empty( $parties['host'] ) ) {
			return $url;
		}

		$hote_site   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$hotes_internes = array( 'ditlproject.eu', 'www.ditlproject.eu' );

		if ( '' !== $hote_site ) {
			$hotes_internes[] = $hote_site;
		}

		if ( ! in_array( strtolower( $parties['host'] ), $hotes_internes, true ) ) {
			return $url;
		}

		$relative = isset( $parties['path'] ) && '' !== $parties['path'] ? $parties['path'] : '/';

		if ( isset( $parties['query'] ) ) {
			$relative .= '?' . $parties['query'];
		}

		if ( isset( $parties['fragment'] ) ) {
			$relative .= '#' . $parties['fragment'];
		}

		return $relative;
	}
}

if ( ! function_exists( 'ditl_migration_resultats_extraire' ) ) {
	/**
	 * Parcourt recursivement l'arbre Elementor et collecte les donnees du gabarit.
	 *
	 * @param array $elements Elements Elementor (containers et widgets).
	 * @param array $data     Donnees collectees (passees par reference).
	 */
	function ditl_migration_resultats_extraire( $elements, &$data ) {
		foreach ( $elements as $element ) {
			if ( isset( $element['elType'] ) && 'widget' === $element['elType'] ) {
				$type     = isset( $element['widgetType'] ) ? $element['widgetType'] : '';
				$reglages = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

				switch ( $type ) {
					case 'image':
						// Premiere image rencontree = banniere. On garde l'ID
						// d'attachement, jamais l'URL (les URLs pointent vers la prod).
						if ( 0 === $data['hero_image_id'] && isset( $reglages['image']['id'] ) ) {
							$data['hero_image_id'] = absint( $reglages['image']['id'] );
						}
						break;

					case 'heading':
						// Les titres Elementor peuvent contenir des entites HTML
						// (&nbsp;...) : on les decode pour stocker du texte brut.
						$titre = isset( $reglages['title'] ) ? (string) $reglages['title'] : '';
						$titre = sanitize_text_field( html_entity_decode( wp_strip_all_tags( $titre ), ENT_QUOTES, 'UTF-8' ) );
						// h2 est la valeur par defaut d'Elementor quand header_size est absent.
						$niveau = isset( $reglages['header_size'] ) && '' !== $reglages['header_size'] ? $reglages['header_size'] : 'h2';

						if ( 'h1' === $niveau && '' === $data['hero_title'] ) {
							$data['hero_title'] = $titre;
						} elseif ( 'h2' === $niveau ) {
							// Chaque h2 ouvre une nouvelle section.
							$data['sections'][]      = array(
								'title'   => $titre,
								'content' => '',
							);
							$data['section_ouverte'] = true;
						} elseif ( 'h3' === $niveau && $data['section_ouverte'] && ! empty( $data['sections'] ) ) {
							// Sous-titre serialise dans le HTML de la section,
							// a sa position dans le flux.
							$derniere = count( $data['sections'] ) - 1;

							if ( '' !== $data['sections'][ $derniere ]['content'] ) {
								$data['sections'][ $derniere ]['content'] .= "\n";
							}

							$data['sections'][ $derniere ]['content'] .= '<h3>' . esc_html( $titre ) . '</h3>';
						} elseif ( 'h3' === $niveau ) {
							// h3 hors section : signale plutot que perdu en silence.
							WP_CLI::warning( sprintf( 'Heading h3 ignore (hors section) : "%s"', $titre ) );
						}
						break;

					case 'text-editor':
						// Le HTML est conserve tel quel ; wp_kses_post est applique
						// a l'enregistrement par le sanitize_callback de la meta.
						$html = isset( $reglages['editor'] ) ? trim( (string) $reglages['editor'] ) : '';

						if ( '' === $html ) {
							break;
						}

						if ( empty( $data['sections'] ) ) {
							// Avant le premier h2 : texte d'introduction.
							$data['intro_parts'][] = $html;
						} elseif ( $data['section_ouverte'] ) {
							$derniere = count( $data['sections'] ) - 1;

							if ( '' !== $data['sections'][ $derniere ]['content'] ) {
								$data['sections'][ $derniere ]['content'] .= "\n";
							}

							$data['sections'][ $derniere ]['content'] .= $html;
						} else {
							// Texte apres un divider sans nouveau h2 : rien ne le
							// rattache, on previent plutot que de le perdre en silence.
							WP_CLI::warning( sprintf( 'Widget text-editor ignore (hors section) : "%s..."', mb_substr( wp_strip_all_tags( $html ), 0, 60 ) ) );
						}
						break;

					case 'divider':
						// Fin de la section courante : rien n'est stocke, le
						// template regenere les separateurs entre sections.
						$data['section_ouverte'] = false;
						break;

					case 'upk-banner':
						// Bandeau de mise en avant (present sur la page EN seulement).
						if ( 0 === $data['bandeau_image_id'] && '' === $data['bandeau_texte'] ) {
							if ( isset( $reglages['image']['id'] ) ) {
								$data['bandeau_image_id'] = absint( $reglages['image']['id'] );
							}

							$data['bandeau_texte']        = isset( $reglages['description_text'] ) ? trim( (string) $reglages['description_text'] ) : '';
							$data['bandeau_bouton_texte'] = isset( $reglages['readmore_text'] ) ? sanitize_text_field( (string) $reglages['readmore_text'] ) : '';

							$bandeau_url = isset( $reglages['readmore_link']['url'] ) ? trim( (string) $reglages['readmore_link']['url'] ) : '';

							if ( '' !== $bandeau_url ) {
								$bandeau_url = ditl_migration_resultats_url_relative( $bandeau_url );
							}

							$data['bandeau_bouton_url'] = $bandeau_url;
						}
						break;
				}
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				ditl_migration_resultats_extraire( $element['elements'], $data );
			}
		}
	}
}

// ---------------------------------------------------------------------------
// Lecture des arguments : IDs de pages + mode simulation eventuel.
// ---------------------------------------------------------------------------

$ditl_dry_run  = false;
$ditl_page_ids = array();

foreach ( (array) $args as $ditl_arg ) {
	if ( 'dry-run' === $ditl_arg || '--dry-run' === $ditl_arg ) {
		$ditl_dry_run = true;
		continue;
	}

	$ditl_id = absint( $ditl_arg );

	if ( $ditl_id > 0 ) {
		$ditl_page_ids[] = $ditl_id;
	} else {
		WP_CLI::warning( sprintf( 'Argument ignore (ID de page invalide) : %s', $ditl_arg ) );
	}
}

if ( empty( $ditl_page_ids ) ) {
	WP_CLI::error( 'Aucun ID de page fourni. Usage : wp eval-file ... <id> [<id>...] [dry-run]' );
}

if ( $ditl_dry_run ) {
	WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucune ecriture en base ===' );
}

// ---------------------------------------------------------------------------
// Traitement page par page.
// ---------------------------------------------------------------------------

foreach ( $ditl_page_ids as $ditl_page_id ) {
	$ditl_page = get_post( $ditl_page_id );

	if ( ! $ditl_page || 'page' !== $ditl_page->post_type ) {
		WP_CLI::warning( sprintf( 'Page %d introuvable (ou pas de type "page") : ignoree.', $ditl_page_id ) );
		continue;
	}

	WP_CLI::log( '' );
	WP_CLI::log( sprintf( '--- Page %d : "%s" ---', $ditl_page_id, $ditl_page->post_title ) );

	$ditl_elementor_raw = (string) get_post_meta( $ditl_page_id, '_elementor_data', true );

	if ( '' === $ditl_elementor_raw ) {
		WP_CLI::warning( sprintf( 'Page %d : meta _elementor_data absente ou vide, page ignoree.', $ditl_page_id ) );
		continue;
	}

	$ditl_elements = json_decode( $ditl_elementor_raw, true );

	if ( ! is_array( $ditl_elements ) ) {
		WP_CLI::warning( sprintf( 'Page %d : JSON _elementor_data illisible, page ignoree.', $ditl_page_id ) );
		continue;
	}

	$ditl_data = array(
		'hero_image_id'        => 0,
		'hero_title'           => '',
		'intro_parts'          => array(),
		'sections'             => array(),
		'section_ouverte'      => false,
		'bandeau_image_id'     => 0,
		'bandeau_texte'        => '',
		'bandeau_bouton_texte' => '',
		'bandeau_bouton_url'   => '',
	);

	ditl_migration_resultats_extraire( $ditl_elements, $ditl_data );

	$ditl_intro_content = implode( "\n", $ditl_data['intro_parts'] );
	$ditl_sections_json = (string) wp_json_encode( $ditl_data['sections'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	// Recapitulatif lisible des valeurs extraites.
	WP_CLI::log( sprintf( '  _ditl_hero_image_id        : %d', $ditl_data['hero_image_id'] ) );
	WP_CLI::log( sprintf( '  _ditl_hero_title           : %s', $ditl_data['hero_title'] ) );
	WP_CLI::log( sprintf( '  _ditl_intro_content        : %d caracteres', strlen( $ditl_intro_content ) ) );
	WP_CLI::log( sprintf( '  _ditl_sections             : %d section(s)', count( $ditl_data['sections'] ) ) );

	foreach ( $ditl_data['sections'] as $ditl_i => $ditl_section ) {
		WP_CLI::log( sprintf(
			'    %d. "%s" (%d caracteres de contenu)',
			$ditl_i + 1,
			$ditl_section['title'],
			strlen( $ditl_section['content'] )
		) );
	}

	WP_CLI::log( sprintf( '  _ditl_bandeau_image_id     : %d', $ditl_data['bandeau_image_id'] ) );
	WP_CLI::log( sprintf( '  _ditl_bandeau_texte        : %d caracteres', strlen( $ditl_data['bandeau_texte'] ) ) );
	WP_CLI::log( sprintf( '  _ditl_bandeau_bouton_texte : %s', $ditl_data['bandeau_bouton_texte'] ) );
	WP_CLI::log( sprintf( '  _ditl_bandeau_bouton_url   : %s', $ditl_data['bandeau_bouton_url'] ) );
	WP_CLI::log( sprintf( '  _wp_page_template          : %s', 'page-templates/resultats.php' ) );

	if ( $ditl_dry_run ) {
		WP_CLI::log( '  [dry-run] Rien n\'a ete ecrit pour cette page.' );
		continue;
	}

	// Ecriture des metas. update_post_meta est idempotent ; wp_slash compense
	// le wp_unslash applique en interne (le JSON contient des antislashs).
	// Les sanitize_callback declares via register_post_meta s'appliquent ici aussi.
	update_post_meta( $ditl_page_id, '_ditl_hero_image_id', $ditl_data['hero_image_id'] );
	update_post_meta( $ditl_page_id, '_ditl_hero_title', wp_slash( $ditl_data['hero_title'] ) );
	update_post_meta( $ditl_page_id, '_ditl_intro_content', wp_slash( $ditl_intro_content ) );
	update_post_meta( $ditl_page_id, '_ditl_sections', wp_slash( $ditl_sections_json ) );
	update_post_meta( $ditl_page_id, '_ditl_bandeau_image_id', $ditl_data['bandeau_image_id'] );
	update_post_meta( $ditl_page_id, '_ditl_bandeau_texte', wp_slash( $ditl_data['bandeau_texte'] ) );
	update_post_meta( $ditl_page_id, '_ditl_bandeau_bouton_texte', wp_slash( $ditl_data['bandeau_bouton_texte'] ) );
	update_post_meta( $ditl_page_id, '_ditl_bandeau_bouton_url', wp_slash( $ditl_data['bandeau_bouton_url'] ) );
	update_post_meta( $ditl_page_id, '_wp_page_template', 'page-templates/resultats.php' );

	WP_CLI::success( sprintf( 'Page %d migree.', $ditl_page_id ) );
}

WP_CLI::log( '' );
WP_CLI::log( $ditl_dry_run ? 'Simulation terminee.' : 'Migration terminee.' );
