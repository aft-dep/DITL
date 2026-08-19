<?php
/**
 * Migration du gabarit "Contact" : donnees Elementor -> metas sur mesure.
 *
 * Lit le JSON _elementor_data des pages passees en argument et remplit les
 * metas du gabarit (contrat decrit dans inc/metaboxes/contact.php).
 * Les pages FR (16) et EN (2595) ont la MEME ossature : deux containers de
 * premier niveau, le premier etant la banniere, le second la section a deux
 * colonnes. Le decoupage se fait donc par zone (indice du container racine),
 * puis par ordre des widgets dans la zone :
 *
 * Zone 1 (banniere) :
 * - spacer                   -> decoratif, ignore (le template regenere les espacements)
 * - image                    -> _ditl_hero_image_id (ID d'attachement, jamais l'URL)
 * - heading                  -> _ditl_hero_title, quel que soit son niveau (voir plus bas)
 *
 * Zone 2 (section a deux colonnes) :
 * - 1er heading              -> titre de la colonne formulaire
 * - widget wpforms           -> ID du formulaire (le plugin WPForms est conserve)
 * - 2e heading               -> titre de la colonne coordonnees
 * - widget icon-box          -> bloc de coordonnees {icone_id, titre, description}
 *
 * CORRECTION SEMANTIQUE VOLONTAIRE : sur la page anglaise, le titre de la
 * banniere est un heading de niveau h5 - la page n'a donc AUCUN H1 (defaut
 * d'accessibilite RGAA). Le titre est migre comme un titre de page normal :
 * la banniere partagee (template-parts/gabarit-hero.php) le rend en H1, avec
 * un style identique a l'existant. Le script signale le cas.
 *
 * NON MIGRE volontairement : l'image de fond du container de la banniere de
 * la page francaise (attachment 1718, Contact-Banner.jpg) est absente de la
 * mediatheque ET du disque - elle est deja en 404 en production et ne produit
 * aucun rendu. Le script la signale sans rien ecrire.
 *
 * Pose aussi _wp_page_template = page-templates/contact.php.
 *
 * Les metas Elementor (_elementor_data, _elementor_edit_mode, ...) ne sont
 * PAS modifiees : elles restent en place comme sauvegarde dormante.
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod).
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/migrate-contact.php 16 2595 dry-run
 *   wp eval-file wp-content/themes/ditl/cli/migrate-contact.php 16 2595
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

if ( ! function_exists( 'ditl_migration_contact_titre_texte' ) ) {
	/**
	 * Reduit un titre de widget Elementor a du texte simple.
	 *
	 * @param mixed $titre_brut Valeur brute du reglage de titre.
	 * @return string Texte nettoye (balises retirees, entites decodees, trime).
	 */
	function ditl_migration_contact_titre_texte( $titre_brut ) {
		$titre_brut = is_scalar( $titre_brut ) ? (string) $titre_brut : '';

		return sanitize_text_field( html_entity_decode( wp_strip_all_tags( $titre_brut ), ENT_QUOTES, 'UTF-8' ) );
	}
}

if ( ! function_exists( 'ditl_migration_contact_icone_id' ) ) {
	/**
	 * Extrait l'ID d'attachement de l'icone d'un widget icon-box.
	 *
	 * Dans l'existant, les trois icones sont des SVG de la mediatheque
	 * (selected_icon.library = "svg", selected_icon.value = {id, url}). Une
	 * icone issue d'une police d'icones n'a pas d'ID : elle est signalee.
	 *
	 * @param array  $reglages Reglages du widget icon-box.
	 * @param string $contexte Contexte pour les messages.
	 * @return int ID d'attachement de l'icone (0 si absent).
	 */
	function ditl_migration_contact_icone_id( $reglages, $contexte ) {
		$icone = isset( $reglages['selected_icon'] ) && is_array( $reglages['selected_icon'] ) ? $reglages['selected_icon'] : array();

		if ( isset( $icone['value']['id'] ) ) {
			return absint( $icone['value']['id'] );
		}

		$bibliotheque = isset( $icone['library'] ) && is_scalar( $icone['library'] ) ? (string) $icone['library'] : '';

		WP_CLI::warning( sprintf(
			'Icone du bloc %s non issue de la mediatheque (bibliotheque "%s") : aucune icone migree.',
			$contexte,
			$bibliotheque
		) );

		return 0;
	}
}

if ( ! function_exists( 'ditl_migration_contact_signaler_tel' ) ) {
	/**
	 * Signale les liens tel: malformes d'une description de bloc.
	 *
	 * La version francaise contient des href de la forme "tel:+33 6 00000000"
	 * (espaces dans le numero, non conformes a la RFC 3966). Ils sont migres
	 * TEL QUELS - fidelite a l'existant - mais remontes comme dette client.
	 *
	 * @param string $description Description HTML du bloc.
	 * @param string $contexte    Contexte pour les messages.
	 */
	function ditl_migration_contact_signaler_tel( $description, $contexte ) {
		if ( ! preg_match_all( '/href="(tel:[^"]*)"/i', $description, $correspondances ) ) {
			return;
		}

		foreach ( $correspondances[1] as $lien ) {
			if ( preg_match( '/\s/', $lien ) ) {
				WP_CLI::warning( sprintf(
					'Lien telephone malforme migre tel quel dans le bloc %s : "%s" (espaces dans le numero, dette client).',
					$contexte,
					$lien
				) );
			}
		}
	}
}

if ( ! function_exists( 'ditl_migration_contact_extraire' ) ) {
	/**
	 * Parcourt recursivement une zone de l'arbre Elementor et collecte ses donnees.
	 *
	 * @param array  $elements Elements Elementor (containers et widgets).
	 * @param array  $data     Donnees collectees (passees par reference).
	 * @param string $zone     "hero" (banniere) ou "contenu" (section a deux colonnes).
	 */
	function ditl_migration_contact_extraire( $elements, &$data, $zone ) {
		foreach ( $elements as $element ) {
			if ( isset( $element['elType'] ) && 'widget' === $element['elType'] ) {
				$type     = isset( $element['widgetType'] ) ? $element['widgetType'] : '';
				$reglages = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

				switch ( $type ) {
					case 'spacer':
						// Purement decoratif : le template regenere les espacements.
						break;

					case 'image':
						$image_id = isset( $reglages['image']['id'] ) ? absint( $reglages['image']['id'] ) : 0;

						if ( 'hero' === $zone && 0 === $data['hero_image_id'] ) {
							$data['hero_image_id'] = $image_id;
						} else {
							WP_CLI::warning( sprintf( 'Widget image inattendu (zone "%s", attachment %d) ignore.', $zone, $image_id ) );
						}
						break;

					case 'heading':
						$titre = ditl_migration_contact_titre_texte( isset( $reglages['title'] ) ? $reglages['title'] : '' );
						// h2 est la valeur par defaut d'Elementor quand header_size est absent.
						$niveau = isset( $reglages['header_size'] ) && '' !== $reglages['header_size'] ? (string) $reglages['header_size'] : 'h2';

						if ( 'hero' === $zone ) {
							if ( '' === $data['hero_title'] ) {
								$data['hero_title']        = $titre;
								$data['hero_title_niveau'] = $niveau;
							} else {
								WP_CLI::warning( sprintf( 'Heading %s surnumeraire dans la banniere ignore : "%s"', $niveau, $titre ) );
							}
						} elseif ( '' === $data['form_titre'] ) {
							$data['form_titre'] = $titre;
						} elseif ( '' === $data['coord_titre'] ) {
							$data['coord_titre'] = $titre;
						} else {
							WP_CLI::warning( sprintf( 'Heading %s surnumeraire dans le contenu ignore : "%s"', $niveau, $titre ) );
						}
						break;

					case 'wpforms':
						$form_id = isset( $reglages['form_id'] ) && is_scalar( $reglages['form_id'] ) ? absint( $reglages['form_id'] ) : 0;

						if ( 0 === $data['form_id'] ) {
							$data['form_id'] = $form_id;
						} else {
							WP_CLI::warning( sprintf( 'Widget wpforms surnumeraire ignore (formulaire %d).', $form_id ) );
						}
						break;

					case 'icon-box':
						$titre    = ditl_migration_contact_titre_texte( isset( $reglages['title_text'] ) ? $reglages['title_text'] : '' );
						$contexte = '' !== $titre ? sprintf( '"%s"', $titre ) : sprintf( 'n.%d', count( $data['coord_blocs'] ) + 1 );

						// La description est conservee TELLE QUELLE (liens tel:,
						// <br>) ; la liste blanche kses dediee (voir
						// ditl_contact_filtrer_description) est appliquee a
						// l'enregistrement par le sanitize_callback de la meta.
						$description = isset( $reglages['description_text'] ) && is_scalar( $reglages['description_text'] ) ? trim( (string) $reglages['description_text'] ) : '';

						ditl_migration_contact_signaler_tel( $description, $contexte );

						$data['coord_blocs'][] = array(
							'icone_id'    => ditl_migration_contact_icone_id( $reglages, $contexte ),
							'titre'       => $titre,
							'description' => $description,
						);
						break;

					default:
						WP_CLI::warning( sprintf( 'Widget "%s" inattendu ignore (zone "%s").', $type, $zone ) );
				}
			} elseif ( 'hero' === $zone && isset( $element['settings']['background_image']['id'] ) ) {
				// Fond de container : jamais migre (le gabarit gere ses fonds en
				// CSS). Signale pour tracer le cas de la page francaise, dont le
				// fond reference un attachment supprime, deja 404 en production.
				$fond_id = absint( $element['settings']['background_image']['id'] );

				WP_CLI::log( sprintf(
					'  Info : image de fond du container de banniere (attachment %d) non migree%s.',
					$fond_id,
					get_post( $fond_id ) ? '' : ' - attachment absent de la mediatheque, deja sans rendu'
				) );
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				ditl_migration_contact_extraire( $element['elements'], $data, $zone );
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
		'hero_image_id'     => 0,
		'hero_title'        => '',
		'hero_title_niveau' => '',
		'form_titre'        => '',
		'form_id'           => 0,
		'coord_titre'       => '',
		'coord_blocs'       => array(),
	);

	// Le premier container racine est la banniere, les suivants le contenu.
	$ditl_racines = array_values( $ditl_elements );

	if ( count( $ditl_racines ) > 2 ) {
		WP_CLI::warning( sprintf(
			'Page %d : %d containers racine au lieu de 2, verifier le decoupage banniere / contenu.',
			$ditl_page_id,
			count( $ditl_racines )
		) );
	}

	foreach ( $ditl_racines as $ditl_index => $ditl_racine ) {
		$ditl_zone = 0 === $ditl_index ? 'hero' : 'contenu';

		ditl_migration_contact_extraire( array( $ditl_racine ), $ditl_data, $ditl_zone );
	}

	// Controles de coherence.
	ditl_cli_verifier_attachment( $ditl_data['hero_image_id'], 'image de banniere' );

	if ( '' === $ditl_data['hero_title'] ) {
		WP_CLI::warning( sprintf( 'Page %d : aucun titre trouve pour la banniere.', $ditl_page_id ) );
	} elseif ( 'h1' !== $ditl_data['hero_title_niveau'] ) {
		WP_CLI::warning( sprintf(
			'Page %d : le titre de banniere "%s" est un %s dans Elementor (page sans H1). Migre comme titre de page : la banniere partagee le rendra en H1 (correction d\'accessibilite volontaire, style inchange).',
			$ditl_page_id,
			$ditl_data['hero_title'],
			strtoupper( $ditl_data['hero_title_niveau'] )
		) );
	}

	if ( '' === $ditl_data['form_titre'] ) {
		WP_CLI::warning( sprintf( 'Page %d : aucun titre trouve pour la colonne formulaire.', $ditl_page_id ) );
	}

	if ( '' === $ditl_data['coord_titre'] ) {
		WP_CLI::warning( sprintf( 'Page %d : aucun titre trouve pour la colonne coordonnees.', $ditl_page_id ) );
	}

	if ( 0 === $ditl_data['form_id'] ) {
		WP_CLI::warning( sprintf( 'Page %d : aucun formulaire WPForms trouve dans le contenu.', $ditl_page_id ) );
	} elseif ( 'wpforms' !== get_post_type( $ditl_data['form_id'] ) ) {
		WP_CLI::warning( sprintf(
			'Page %d : le formulaire %d reference par Elementor n\'existe pas (ou n\'est pas un formulaire WPForms) : la meta sera remise a 0.',
			$ditl_page_id,
			$ditl_data['form_id']
		) );
	}

	if ( empty( $ditl_data['coord_blocs'] ) ) {
		WP_CLI::warning( sprintf( 'Page %d : aucun bloc de coordonnees trouve.', $ditl_page_id ) );
	}

	foreach ( $ditl_data['coord_blocs'] as $ditl_i => $ditl_bloc ) {
		$ditl_contexte = sprintf( 'bloc de coordonnees n.%d', $ditl_i + 1 );

		foreach ( array( 'titre', 'description' ) as $ditl_champ ) {
			if ( '' === $ditl_bloc[ $ditl_champ ] ) {
				WP_CLI::warning( sprintf( '%s : champ "%s" vide, verifier le decoupage.', ucfirst( $ditl_contexte ), $ditl_champ ) );
			}
		}

		ditl_cli_verifier_attachment( $ditl_bloc['icone_id'], sprintf( 'icone du %s', $ditl_contexte ) );
	}

	// Recapitulatif lisible des valeurs extraites.
	WP_CLI::log( sprintf( '  _ditl_hero_image_id       : %d', $ditl_data['hero_image_id'] ) );
	WP_CLI::log( sprintf( '  _ditl_hero_title          : %s (%s dans Elementor)', $ditl_data['hero_title'], strtoupper( $ditl_data['hero_title_niveau'] ) ) );
	WP_CLI::log( sprintf( '  _ditl_contact_formulaire  : titre "%s" | formulaire %d', $ditl_data['form_titre'], $ditl_data['form_id'] ) );
	WP_CLI::log( sprintf( '  _ditl_contact_coordonnees : titre "%s" | %d bloc(s)', $ditl_data['coord_titre'], count( $ditl_data['coord_blocs'] ) ) );

	foreach ( $ditl_data['coord_blocs'] as $ditl_bloc ) {
		WP_CLI::log( sprintf(
			'      icone %d | titre "%s" | description %d car.',
			$ditl_bloc['icone_id'],
			$ditl_bloc['titre'],
			strlen( $ditl_bloc['description'] )
		) );
	}

	WP_CLI::log( sprintf( '  _wp_page_template         : %s', 'page-templates/contact.php' ) );

	if ( $ditl_dry_run ) {
		WP_CLI::log( '  [dry-run] Rien n\'a ete ecrit pour cette page.' );
		continue;
	}

	$ditl_formulaire = array(
		'titre'   => $ditl_data['form_titre'],
		'form_id' => $ditl_data['form_id'],
	);

	$ditl_coordonnees = array(
		'titre' => $ditl_data['coord_titre'],
		'blocs' => $ditl_data['coord_blocs'],
	);

	// Ecriture des metas. update_post_meta est idempotent ; wp_slash compense
	// le wp_unslash applique en interne (le JSON contient des antislashs).
	// Les sanitize_callback declares via register_post_meta s'appliquent ici aussi.
	update_post_meta( $ditl_page_id, '_ditl_hero_image_id', $ditl_data['hero_image_id'] );
	update_post_meta( $ditl_page_id, '_ditl_hero_title', wp_slash( $ditl_data['hero_title'] ) );
	update_post_meta( $ditl_page_id, '_ditl_contact_formulaire', wp_slash( (string) wp_json_encode( $ditl_formulaire, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	update_post_meta( $ditl_page_id, '_ditl_contact_coordonnees', wp_slash( (string) wp_json_encode( $ditl_coordonnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	update_post_meta( $ditl_page_id, '_wp_page_template', 'page-templates/contact.php' );

	WP_CLI::success( sprintf( 'Page %d migree.', $ditl_page_id ) );
}

WP_CLI::log( '' );
WP_CLI::log( $ditl_dry_run ? 'Simulation terminee.' : 'Migration terminee.' );
