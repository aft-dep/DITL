<?php
/**
 * Migration du gabarit "Projet DiTL" : donnees Elementor -> metas sur mesure.
 *
 * Lit le JSON _elementor_data des pages passees en argument et remplit les
 * metas du gabarit (contrat decrit dans inc/metaboxes/projet-ditl.php) :
 * - 1er widget image          -> _ditl_hero_image_id (ID d'attachement, jamais l'URL)
 * - heading h1                -> _ditl_hero_title
 * - heading h2 (defaut)       -> _ditl_intro_title
 * - chaque heading h3         -> nouvelle section {title, content}
 * - text-editor qui suivent   -> concatenes dans le contenu de la section courante
 * - widget image-carousel     -> _ditl_carousel_ids (tableau vide si absent)
 * Pose aussi _wp_page_template = page-templates/projet-ditl.php.
 *
 * Les metas Elementor (_elementor_data, _elementor_edit_mode, ...) ne sont
 * PAS modifiees : elles restent en place comme sauvegarde dormante.
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod).
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/migrate-projet-ditl.php 1924 3167 dry-run
 *   wp eval-file wp-content/themes/ditl/cli/migrate-projet-ditl.php 1924 3167
 *
 * Le mode simulation accepte "dry-run" ou "--dry-run" en argument.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "Ce script doit etre lance via WP-CLI : wp eval-file ... <id> [<id>...] [dry-run]\n";
	exit( 1 );
}

if ( ! function_exists( 'ditl_migration_projet_ditl_extraire' ) ) {
	/**
	 * Parcourt recursivement l'arbre Elementor et collecte les donnees du gabarit.
	 *
	 * @param array $elements Elements Elementor (containers et widgets).
	 * @param array $data     Donnees collectees (passees par reference).
	 */
	function ditl_migration_projet_ditl_extraire( $elements, &$data ) {
		foreach ( $elements as $element ) {
			if ( isset( $element['elType'] ) && 'widget' === $element['elType'] ) {
				$type       = isset( $element['widgetType'] ) ? $element['widgetType'] : '';
				$reglages   = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

				switch ( $type ) {
					case 'image':
						// Premiere image rencontree = banniere. On garde l'ID
						// d'attachement, jamais l'URL (les URLs pointent vers la prod).
						if ( 0 === $data['hero_image_id'] && isset( $reglages['image']['id'] ) ) {
							$data['hero_image_id'] = absint( $reglages['image']['id'] );
						}
						break;

					case 'heading':
						$titre = isset( $reglages['title'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $reglages['title'] ) ) : '';
						// h2 est la valeur par defaut d'Elementor quand header_size est absent.
						$niveau = isset( $reglages['header_size'] ) && '' !== $reglages['header_size'] ? $reglages['header_size'] : 'h2';

						if ( 'h1' === $niveau && '' === $data['hero_title'] ) {
							$data['hero_title'] = $titre;
						} elseif ( 'h2' === $niveau && '' === $data['intro_title'] ) {
							$data['intro_title'] = $titre;
						} elseif ( 'h3' === $niveau ) {
							$data['sections'][] = array(
								'title'   => $titre,
								'content' => '',
							);
						}
						break;

					case 'text-editor':
						// Le HTML est conserve tel quel ; wp_kses_post est applique
						// a l'enregistrement par le sanitize_callback de la meta.
						$html = isset( $reglages['editor'] ) ? trim( (string) $reglages['editor'] ) : '';

						if ( '' !== $html && ! empty( $data['sections'] ) ) {
							$derniere = count( $data['sections'] ) - 1;

							if ( '' !== $data['sections'][ $derniere ]['content'] ) {
								$data['sections'][ $derniere ]['content'] .= "\n";
							}

							$data['sections'][ $derniere ]['content'] .= $html;
						}
						break;

					case 'image-carousel':
						if ( empty( $data['carousel_ids'] ) && isset( $reglages['carousel'] ) && is_array( $reglages['carousel'] ) ) {
							foreach ( $reglages['carousel'] as $diapo ) {
								if ( isset( $diapo['id'] ) && absint( $diapo['id'] ) > 0 ) {
									$data['carousel_ids'][] = absint( $diapo['id'] );
								}
							}
						}
						break;
				}
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				ditl_migration_projet_ditl_extraire( $element['elements'], $data );
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
		'hero_image_id' => 0,
		'hero_title'    => '',
		'intro_title'   => '',
		'sections'      => array(),
		'carousel_ids'  => array(),
	);

	ditl_migration_projet_ditl_extraire( $ditl_elements, $ditl_data );

	$ditl_sections_json = (string) wp_json_encode( $ditl_data['sections'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$ditl_carousel_json = (string) wp_json_encode( $ditl_data['carousel_ids'] );

	// Recapitulatif lisible des valeurs extraites.
	WP_CLI::log( sprintf( '  _ditl_hero_image_id : %d', $ditl_data['hero_image_id'] ) );
	WP_CLI::log( sprintf( '  _ditl_hero_title    : %s', $ditl_data['hero_title'] ) );
	WP_CLI::log( sprintf( '  _ditl_intro_title   : %s', $ditl_data['intro_title'] ) );
	WP_CLI::log( sprintf( '  _ditl_sections      : %d section(s)', count( $ditl_data['sections'] ) ) );

	foreach ( $ditl_data['sections'] as $ditl_i => $ditl_section ) {
		WP_CLI::log( sprintf(
			'    %d. "%s" (%d caracteres de contenu)',
			$ditl_i + 1,
			$ditl_section['title'],
			strlen( $ditl_section['content'] )
		) );
	}

	WP_CLI::log( sprintf( '  _ditl_carousel_ids  : %s', $ditl_carousel_json ) );
	WP_CLI::log( sprintf( '  _wp_page_template   : %s', 'page-templates/projet-ditl.php' ) );

	if ( $ditl_dry_run ) {
		WP_CLI::log( '  [dry-run] Rien n\'a ete ecrit pour cette page.' );
		continue;
	}

	// Ecriture des metas. update_post_meta est idempotent ; wp_slash compense
	// le wp_unslash applique en interne (le JSON contient des antislashs).
	// Les sanitize_callback declares via register_post_meta s'appliquent ici aussi.
	update_post_meta( $ditl_page_id, '_ditl_hero_image_id', $ditl_data['hero_image_id'] );
	update_post_meta( $ditl_page_id, '_ditl_hero_title', wp_slash( $ditl_data['hero_title'] ) );
	update_post_meta( $ditl_page_id, '_ditl_intro_title', wp_slash( $ditl_data['intro_title'] ) );
	update_post_meta( $ditl_page_id, '_ditl_sections', wp_slash( $ditl_sections_json ) );
	update_post_meta( $ditl_page_id, '_ditl_carousel_ids', wp_slash( $ditl_carousel_json ) );
	update_post_meta( $ditl_page_id, '_wp_page_template', 'page-templates/projet-ditl.php' );

	WP_CLI::success( sprintf( 'Page %d migree.', $ditl_page_id ) );
}

WP_CLI::log( '' );
WP_CLI::log( $ditl_dry_run ? 'Simulation terminee.' : 'Migration terminee.' );
