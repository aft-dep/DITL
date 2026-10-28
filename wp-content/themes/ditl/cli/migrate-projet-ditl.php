<?php
/**
 * Migration du gabarit "Projet DiTL" : donnees Elementor -> metas sur mesure.
 *
 * Lit le JSON _elementor_data des pages passees en argument et remplit les
 * metas du gabarit (contrat decrit dans inc/metaboxes/projet-ditl.php) :
 * - 1er widget image          -> _ditl_hero_image_id (ID d'attachement, jamais l'URL)
 * - 1er heading de la banniere (1er container racine), quel que soit son
 *   niveau, sinon 1er heading h1 de la page -> _ditl_hero_title
 * - heading h2 (defaut)       -> _ditl_intro_title
 * - chaque heading h3         -> nouvelle section {title, content}
 * - text-editor qui suivent   -> concatenes dans le contenu de la section courante ;
 *                                sans h3 prealable, ils ouvrent une section SANS titre
 * - widget image-carousel     -> _ditl_carousel_ids (tableau vide si absent)
 * Pose aussi _wp_page_template = page-templates/projet-ditl.php.
 *
 * Pages ES/PT/DE et doublon EN 3004 (/ditl-project-2/) : leur structure
 * Elementor differe des pages FR/EN. Le titre de la banniere y est un h5
 * (page sans H1 : migre comme titre de page, la banniere partagee le rend
 * en H1 avec le meme style, correction d'accessibilite volontaire signalee
 * par le script, comme sur /contact-us/), et tout le contenu tient dans UN
 * text-editor (intertitres h4 ou paragraphes en gras dans le HTML), migre
 * en une section sans titre. Resultat inchange sur les pages FR 1924 et
 * EN 3167 (elles ont leur h1 dans la banniere et un h3 avant chaque texte).
 *
 * Les metas Elementor (_elementor_data, _elementor_edit_mode, ...) ne sont
 * PAS modifiees : elles restent en place comme sauvegarde dormante.
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod).
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/migrate-projet-ditl.php 1924 3167 dry-run
 *   wp eval-file wp-content/themes/ditl/cli/migrate-projet-ditl.php 1924 3167
 *   wp eval-file wp-content/themes/ditl/cli/migrate-projet-ditl.php 3132 3104 2593 3004 dry-run
 *
 * Le mode simulation accepte "dry-run" ou "--dry-run" en argument. Tout
 * autre argument qui n'est pas un ID est REFUSE (arret, rien n'est ecrit).
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

if ( ! function_exists( 'ditl_migration_projet_ditl_extraire' ) ) {
	/**
	 * Parcourt recursivement l'arbre Elementor et collecte les donnees du gabarit.
	 *
	 * @param array  $elements Elements Elementor (containers et widgets).
	 * @param array  $data     Donnees collectees (passees par reference).
	 * @param string $zone     "hero" dans le 1er container racine (banniere),
	 *                         "contenu" ailleurs.
	 */
	function ditl_migration_projet_ditl_extraire( $elements, &$data, $zone = 'contenu' ) {
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

						if ( '' === $data['hero_title'] && ( 'hero' === $zone || 'h1' === $niveau ) ) {
							// Titre de la banniere : h1 sur les pages FR/EN, h5 sur
							// les traductions (rendu en H1 par la banniere partagee).
							$data['hero_title']        = $titre;
							$data['hero_title_niveau'] = $niveau;
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

						// Sans h3 prealable (pages ES/PT/DE, 3004), le texte ouvre
						// une section sans titre au lieu d'etre perdu.
						if ( '' !== $html && empty( $data['sections'] ) ) {
							$data['sections'][] = array(
								'title'   => '',
								'content' => '',
							);
						}

						if ( '' !== $html ) {
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
				ditl_migration_projet_ditl_extraire( $element['elements'], $data, $zone );
			}
		}
	}
}

// ---------------------------------------------------------------------------
// Lecture des arguments : IDs de pages + mode simulation eventuel.
// ---------------------------------------------------------------------------

$ditl_modes    = ditl_cli_lire_ids_et_dry_run( $args, 'de page', true );
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
		'hero_image_id'     => 0,
		'hero_title'        => '',
		'hero_title_niveau' => '',
		'intro_title'       => '',
		'sections'          => array(),
		'carousel_ids'      => array(),
	);

	// Le premier container racine est la banniere, les suivants le contenu.
	foreach ( array_values( $ditl_elements ) as $ditl_index => $ditl_racine ) {
		ditl_migration_projet_ditl_extraire( array( $ditl_racine ), $ditl_data, 0 === $ditl_index ? 'hero' : 'contenu' );
	}

	ditl_cli_verifier_attachment( $ditl_data['hero_image_id'], 'image de banniere' );

	if ( '' === $ditl_data['hero_title'] ) {
		WP_CLI::warning( sprintf( 'Page %d : aucun titre trouve pour la banniere.', $ditl_page_id ) );
	} elseif ( 'h1' !== $ditl_data['hero_title_niveau'] ) {
		WP_CLI::warning( sprintf(
			'Page %d : le titre de banniere "%s" est un %s dans Elementor (page sans H1). Migre comme titre de banniere : la banniere partagee le rendra en H1 (correction d\'accessibilite volontaire, style inchange).',
			$ditl_page_id,
			$ditl_data['hero_title'],
			strtoupper( $ditl_data['hero_title_niveau'] )
		) );
	}

	$ditl_sections_json = (string) wp_json_encode( $ditl_data['sections'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$ditl_carousel_json = (string) wp_json_encode( $ditl_data['carousel_ids'] );

	// Recapitulatif lisible des valeurs extraites.
	WP_CLI::log( sprintf( '  _ditl_hero_image_id : %d', $ditl_data['hero_image_id'] ) );
	WP_CLI::log( sprintf( '  _ditl_hero_title    : %s%s', $ditl_data['hero_title'], '' !== $ditl_data['hero_title_niveau'] ? ' (' . strtoupper( $ditl_data['hero_title_niveau'] ) . ' dans Elementor)' : '' ) );
	WP_CLI::log( sprintf( '  _ditl_intro_title   : %s', $ditl_data['intro_title'] ) );
	WP_CLI::log( sprintf( '  _ditl_sections      : %d section(s)', count( $ditl_data['sections'] ) ) );

	foreach ( $ditl_data['sections'] as $ditl_i => $ditl_section ) {
		WP_CLI::log( sprintf(
			'    %d. %s (%d caracteres de contenu)',
			$ditl_i + 1,
			'' !== $ditl_section['title'] ? '"' . $ditl_section['title'] . '"' : '(sans titre)',
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
