<?php
/**
 * Migration du gabarit "Actualites" : donnees Elementor -> metas sur mesure.
 *
 * Lit le JSON _elementor_data des pages passees en argument et remplit les
 * metas de banniere (contrat decrit dans inc/metaboxes/banniere.php) :
 * - 1er widget image -> _ditl_hero_image_id (ID d'attachement, jamais l'URL)
 * - heading h1       -> _ditl_hero_title
 * Pose aussi _wp_page_template = page-templates/actualites.php.
 *
 * Le widget upk-alex-carousel (carrousel d'articles) n'a aucune donnee a
 * migrer : reglages par defaut, contenu 100% dynamique (requete cote
 * template). L'arbre Elementor est parcouru recursivement : selon la langue,
 * l'image et le h1 vivent dans des conteneurs imbriques differemment.
 *
 * Les metas Elementor (_elementor_data, _elementor_edit_mode, ...) ne sont
 * PAS modifiees : elles restent en place comme sauvegarde dormante.
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod).
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/migrate-actualites.php 1927 2589 dry-run
 *   wp eval-file wp-content/themes/ditl/cli/migrate-actualites.php 1927 2589
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

if ( ! function_exists( 'ditl_migration_actualites_extraire' ) ) {
	/**
	 * Parcourt recursivement l'arbre Elementor et collecte les donnees du gabarit.
	 *
	 * @param array $elements Elements Elementor (containers et widgets).
	 * @param array $data     Donnees collectees (passees par reference).
	 */
	function ditl_migration_actualites_extraire( $elements, &$data ) {
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
						// h2 est la valeur par defaut d'Elementor quand header_size est absent.
						$niveau = isset( $reglages['header_size'] ) && '' !== $reglages['header_size'] ? $reglages['header_size'] : 'h2';

						if ( 'h1' === $niveau && '' === $data['hero_title'] ) {
							$data['hero_title'] = isset( $reglages['title'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $reglages['title'] ) ) : '';
						}
						break;
				}
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				ditl_migration_actualites_extraire( $element['elements'], $data );
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
		'hero_image_id' => 0,
		'hero_title'    => '',
	);

	ditl_migration_actualites_extraire( $ditl_elements, $ditl_data );

	// Recapitulatif lisible des valeurs extraites.
	WP_CLI::log( sprintf( '  _ditl_hero_image_id : %d', $ditl_data['hero_image_id'] ) );
	WP_CLI::log( sprintf( '  _ditl_hero_title    : %s', $ditl_data['hero_title'] ) );
	WP_CLI::log( sprintf( '  _wp_page_template   : %s', 'page-templates/actualites.php' ) );

	if ( $ditl_dry_run ) {
		WP_CLI::log( '  [dry-run] Rien n\'a ete ecrit pour cette page.' );
		continue;
	}

	// Ecriture des metas. update_post_meta est idempotent ; wp_slash compense
	// le wp_unslash applique en interne. Les sanitize_callback declares via
	// register_post_meta s'appliquent ici aussi.
	update_post_meta( $ditl_page_id, '_ditl_hero_image_id', $ditl_data['hero_image_id'] );
	update_post_meta( $ditl_page_id, '_ditl_hero_title', wp_slash( $ditl_data['hero_title'] ) );
	update_post_meta( $ditl_page_id, '_wp_page_template', 'page-templates/actualites.php' );

	WP_CLI::success( sprintf( 'Page %d migree.', $ditl_page_id ) );
}

WP_CLI::log( '' );
WP_CLI::log( $ditl_dry_run ? 'Simulation terminee.' : 'Migration terminee.' );
