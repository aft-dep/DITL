<?php
/**
 * Migration du gabarit "Accueil" : donnees Elementor -> metas sur mesure.
 *
 * Lit le JSON _elementor_data des pages passees en argument et remplit les
 * metas du gabarit (contrat decrit dans inc/metaboxes/accueil.php). Les pages
 * FR (3075) et EN (3035) partagent la meme structure : les blocs sont reperes
 * par la position de leur titre H2 dans l'ordre du document.
 * - 1er widget image             -> _ditl_hero_image_id (ID d'attachement, jamais l'URL)
 * - heading h1                   -> _ditl_hero_title
 * - h2 n.1 + bouton              -> _ditl_accueil_hero {sous_titre, bouton_texte, bouton_url}
 * - h2 n.2 + texte/bouton/image  -> _ditl_accueil_presentation
 * - h2 n.3 + intro + paires
 *   {image, texte}               -> _ditl_accueil_livrables (items = vignettes)
 * - h2 n.4                       -> _ditl_accueil_actualites (le widget upk-buzz-list qui
 *                                   suit est dynamique : rien a migrer)
 * - h2 n.5 + texte/bouton/logos  -> _ditl_accueil_partenaires ; les logos viennent soit de
 *                                   widgets image individuels (FR, carrousel=false), soit
 *                                   d'un widget image-carousel (EN, carrousel=true)
 *
 * Cas particuliers traites (signales par des warnings) :
 * - INTRO DU BLOC LIVRABLES : le widget text-editor d'origine contenait du
 *   markup Elementor rendu, colle tel quel par le client (wrappers
 *   div.e-con / e-con-inner / elementor-widget autour du contenu utile,
 *   puis un conteneur vide servant d'espaceur vertical). Ce markup dependait
 *   de frontend.min.css : l'intro est normalisee au contenu utile seul
 *   (concatenation des div.elementor-widget-container), la mise en boite et
 *   l'espacement equivalents sont repris par gabarit-accueil.css
 *   (.ditl-accueil-livr__intro--normalisee).
 * - CORRECTION VOLONTAIRE : dans le carrousel EN, l'attachment 4993 (logo
 *   INSTITUT ESCOLA DEL TREBALL, supprime de la mediatheque, fichier 404 en
 *   prod) est remplace par 5803 (logo-escola-del-treball.png), deja utilise
 *   par la page FR. Seul ecart de contenu de la migration, a signaler au client.
 * - Les URLs internes de boutons sont stockees RELATIVES ; celles dont la
 *   cible ne resout vers aucun contenu local (ex. /en/ditl-project/, prefixe
 *   /en/ inhabituel herite de la prod) sont migrees telles quelles et
 *   signalees (decision client a venir).
 * - Chaque attachment reference est verifie (get_post) ; warning si absent.
 *
 * Pose aussi _wp_page_template = page-templates/accueil.php.
 *
 * Les metas Elementor (_elementor_data, _elementor_edit_mode, ...) ne sont
 * PAS modifiees : elles restent en place comme sauvegarde dormante.
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod).
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/migrate-accueil.php 3075 3035 dry-run
 *   wp eval-file wp-content/themes/ditl/cli/migrate-accueil.php 3075 3035
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

if ( ! function_exists( 'ditl_migration_accueil_bouton' ) ) {
	/**
	 * Extrait un bouton Elementor {texte, url} avec URL interne rendue relative.
	 *
	 * Les URLs internes dont la cible ne resout vers aucun contenu local sont
	 * migrees telles quelles mais signalees : le prefixe /en/ n'existe pas
	 * dans la structure Polylang actuelle (EN = langue par defaut sans
	 * prefixe), ces liens sont probablement bancals en prod aussi
	 * (decision client a venir).
	 *
	 * @param array  $reglages Reglages du widget button.
	 * @param string $contexte Libelle du bouton pour les messages.
	 * @return array Tableau {texte, url}.
	 */
	function ditl_migration_accueil_bouton( $reglages, $contexte ) {
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

if ( ! function_exists( 'ditl_migration_accueil_normaliser_intro' ) ) {
	/**
	 * Retire du HTML de l'intro du bloc Livrables les wrappers Elementor
	 * que le widget d'origine embarquait (markup rendu colle dans le widget
	 * texte par le client : conteneurs div.e-con / e-con-inner /
	 * elementor-widget autour du contenu utile, puis un conteneur vide
	 * servant d'espaceur vertical, une espace insecable sur la page FR).
	 *
	 * Le contenu utile est la concatenation du contenu des
	 * div.elementor-widget-container ; les wrappers et l'espaceur sont
	 * abandonnes : la mise en boite et l'espacement equivalents sont
	 * assures par gabarit-accueil.css (.ditl-accueil-livr__intro--normalisee).
	 *
	 * Sans wrapper detecte, le HTML ressort inchange : une intro deja
	 * normalisee est stable au rejeu. Si du texte inattendu vit hors des
	 * wrappers, l'intro est conservee telle quelle (warning), aucune perte
	 * de contenu possible.
	 *
	 * @param string $html HTML de l'intro issu du widget text-editor.
	 * @return string HTML normalise (contenu utile seul).
	 */
	function ditl_migration_accueil_normaliser_intro( $html ) {
		if ( false === strpos( $html, 'elementor-widget-container' ) ) {
			return $html;
		}

		// Tokenise les balises div pour extraire le contenu de chaque
		// div.elementor-widget-container en suivant la profondeur
		// d'imbrication (les autres balises n'ont pas besoin d'etre suivies).
		if ( ! preg_match_all( '#<div\b[^>]*>|</div\s*>#i', $html, $ditl_balises, PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}

		$contenus   = array();
		$plages     = array(); // Plages extraites [debut, fin], ordonnees.
		$debut      = null; // Offset du contenu du widget-container courant.
		$profondeur = 0;

		foreach ( $ditl_balises[0] as $ditl_balise ) {
			list( $texte_balise, $offset ) = $ditl_balise;

			if ( '/' === $texte_balise[1] ) {
				// Balise fermante.
				if ( null !== $debut ) {
					$profondeur--;

					if ( 0 === $profondeur ) {
						$contenus[] = substr( $html, $debut, $offset - $debut );
						$plages[]   = array( $debut, $offset );
						$debut      = null;
					}
				}
			} elseif ( null !== $debut ) {
				$profondeur++;
			} elseif ( false !== stripos( $texte_balise, 'elementor-widget-container' ) ) {
				$debut      = $offset + strlen( $texte_balise );
				$profondeur = 1;
			}
		}

		if ( array() === $contenus || null !== $debut ) {
			WP_CLI::warning( 'Intro du bloc Livrables : wrappers Elementor mal formes, intro conservee telle quelle.' );
			return $html;
		}

		// Controle de non-perte : reconstruit par offsets ce qui vit HORS
		// des plages extraites (un texte hors wrappers qui dupliquerait un
		// contenu extrait ne peut donc pas masquer une perte). Il ne doit
		// rester que des balises de wrappers et du blanc (dont l'espace
		// insecable de l'espaceur, reprise par le CSS du gabarit).
		$restant = '';
		$curseur = 0;

		foreach ( $plages as $ditl_plage ) {
			$restant .= substr( $html, $curseur, $ditl_plage[0] - $curseur );
			$curseur  = $ditl_plage[1];
		}

		$restant .= substr( $html, $curseur );

		$restant = wp_strip_all_tags( $restant );
		$restant = str_replace( array( "\xC2\xA0", '&nbsp;' ), ' ', $restant );

		if ( '' !== trim( $restant ) ) {
			WP_CLI::warning( sprintf( 'Intro du bloc Livrables : contenu inattendu hors des wrappers Elementor ("%s..."), intro conservee telle quelle.', mb_substr( trim( $restant ), 0, 60 ) ) );
			return $html;
		}

		$normalise = trim( implode( "\n", array_map( 'trim', $contenus ) ) );

		WP_CLI::log( sprintf( '  (intro du bloc Livrables : wrappers Elementor retires, %d -> %d caracteres ; boite et espaceur repris par gabarit-accueil.css)', mb_strlen( $html ), mb_strlen( $normalise ) ) );

		return $normalise;
	}
}

if ( ! function_exists( 'ditl_migration_accueil_extraire' ) ) {
	/**
	 * Parcourt recursivement l'arbre Elementor et collecte les donnees du gabarit.
	 *
	 * Le bloc courant est repere par le nombre de titres H2 deja rencontres
	 * (1 = hero, 2 = presentation, 3 = livrables, 4 = actualites,
	 * 5 = partenaires) : FR et EN partagent exactement cette structure.
	 *
	 * @param array $elements Elements Elementor (containers et widgets).
	 * @param array $data     Donnees collectees (passees par reference).
	 */
	function ditl_migration_accueil_extraire( $elements, &$data ) {
		foreach ( $elements as $element ) {
			if ( isset( $element['elType'] ) && 'widget' === $element['elType'] ) {
				$type     = isset( $element['widgetType'] ) ? $element['widgetType'] : '';
				$reglages = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
				$bloc     = $data['h2_count'];

				switch ( $type ) {
					case 'heading':
						// Les titres Elementor peuvent contenir des entites HTML et des
						// espaces parasites (le sous-titre EN a un saut de ligne final) :
						// decodage + sanitize_text_field pour du texte brut net.
						$titre = isset( $reglages['title'] ) ? (string) $reglages['title'] : '';
						$titre = sanitize_text_field( html_entity_decode( wp_strip_all_tags( $titre ), ENT_QUOTES, 'UTF-8' ) );
						// h2 est la valeur par defaut d'Elementor quand header_size est absent.
						$niveau = isset( $reglages['header_size'] ) && '' !== $reglages['header_size'] ? $reglages['header_size'] : 'h2';

						if ( 'h1' === $niveau && '' === $data['hero_title'] ) {
							$data['hero_title'] = $titre;
						} elseif ( 'h2' === $niveau ) {
							$data['h2_count']++;

							switch ( $data['h2_count'] ) {
								case 1:
									$data['hero']['sous_titre'] = $titre;
									break;
								case 2:
									$data['presentation']['titre'] = $titre;
									break;
								case 3:
									$data['livrables']['titre'] = $titre;
									break;
								case 4:
									$data['actualites']['titre'] = $titre;
									break;
								case 5:
									$data['partenaires']['titre'] = $titre;
									break;
								default:
									WP_CLI::warning( sprintf( 'Heading h2 inattendu (bloc n.%d) ignore : "%s"', $data['h2_count'], $titre ) );
							}
						} else {
							WP_CLI::warning( sprintf( 'Heading %s inattendu ignore : "%s"', $niveau, $titre ) );
						}
						break;

					case 'image':
						$image_id = isset( $reglages['image']['id'] ) ? absint( $reglages['image']['id'] ) : 0;

						if ( 0 === $bloc && 0 === $data['hero_image_id'] ) {
							// Avant tout H2 : image de la banniere commune.
							$data['hero_image_id'] = $image_id;
						} elseif ( 2 === $bloc && 0 === $data['presentation']['image_id'] ) {
							$data['presentation']['image_id'] = $image_id;
						} elseif ( 3 === $bloc ) {
							// Chaque image du bloc Livrables ouvre une vignette,
							// completee par le text-editor qui la suit.
							$data['livrables']['items'][] = array(
								'image_id' => $image_id,
								'texte'    => '',
							);
						} elseif ( 5 === $bloc ) {
							// Logos partenaires en widgets individuels (page FR) :
							// affichage en grille statique.
							$data['partenaires']['logo_ids'][] = $image_id;
						} else {
							WP_CLI::warning( sprintf( 'Widget image orphelin (bloc n.%d, attachment %d) ignore.', $bloc, $image_id ) );
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

						if ( 2 === $bloc && '' === $data['presentation']['texte'] ) {
							$data['presentation']['texte'] = $html;
						} elseif ( 3 === $bloc ) {
							$nb_items = count( $data['livrables']['items'] );

							if ( 0 === $nb_items && '' === $data['livrables']['intro'] ) {
								// Avant la premiere vignette : introduction du bloc,
								// normalisee (wrappers Elementor colles retires).
								$data['livrables']['intro'] = ditl_migration_accueil_normaliser_intro( $html );
							} elseif ( $nb_items > 0 && '' === $data['livrables']['items'][ $nb_items - 1 ]['texte'] ) {
								$data['livrables']['items'][ $nb_items - 1 ]['texte'] = $html;
							} else {
								WP_CLI::warning( sprintf( 'Widget text-editor orphelin (bloc Livrables) ignore : "%s..."', $extrait ) );
							}
						} elseif ( 5 === $bloc && '' === $data['partenaires']['texte'] ) {
							$data['partenaires']['texte'] = $html;
						} else {
							WP_CLI::warning( sprintf( 'Widget text-editor orphelin (bloc n.%d) ignore : "%s..."', $bloc, $extrait ) );
						}
						break;

					case 'button':
						if ( 1 === $bloc && '' === $data['hero']['bouton_texte'] ) {
							$bouton                       = ditl_migration_accueil_bouton( $reglages, 'banniere' );
							$data['hero']['bouton_texte'] = $bouton['texte'];
							$data['hero']['bouton_url']   = $bouton['url'];
						} elseif ( 2 === $bloc && '' === $data['presentation']['bouton_texte'] ) {
							$bouton                               = ditl_migration_accueil_bouton( $reglages, 'bloc Presentation' );
							$data['presentation']['bouton_texte'] = $bouton['texte'];
							$data['presentation']['bouton_url']   = $bouton['url'];
						} elseif ( 5 === $bloc && '' === $data['partenaires']['bouton_texte'] ) {
							$bouton                              = ditl_migration_accueil_bouton( $reglages, 'bloc Partenaires' );
							$data['partenaires']['bouton_texte'] = $bouton['texte'];
							$data['partenaires']['bouton_url']   = $bouton['url'];
						} else {
							WP_CLI::warning( sprintf( 'Widget button orphelin (bloc n.%d) ignore.', $bloc ) );
						}
						break;

					case 'image-carousel':
						// Logos partenaires en carrousel (page EN).
						if ( 5 === $bloc && isset( $reglages['carousel'] ) && is_array( $reglages['carousel'] ) ) {
							foreach ( $reglages['carousel'] as $diapo ) {
								if ( isset( $diapo['id'] ) && absint( $diapo['id'] ) > 0 ) {
									$data['partenaires']['logo_ids'][] = absint( $diapo['id'] );
								}
							}

							$data['partenaires']['carrousel'] = true;
						} else {
							WP_CLI::warning( sprintf( 'Widget image-carousel orphelin (bloc n.%d) ignore.', $bloc ) );
						}
						break;

					case 'upk-buzz-list':
						if ( 4 === $bloc ) {
							// Liste des derniers articles : contenu dynamique, le
							// template la regenere, rien a migrer.
							WP_CLI::log( '  (widget upk-buzz-list : liste d\'articles dynamique, rien a migrer)' );
						} else {
							WP_CLI::warning( sprintf( 'Widget upk-buzz-list orphelin (bloc n.%d) ignore.', $bloc ) );
						}
						break;

					case 'spacer':
					case 'divider':
						// Purement decoratifs : le template regenere les espacements.
						break;

					default:
						WP_CLI::warning( sprintf( 'Widget "%s" orphelin (bloc n.%d) ignore.', $type, $bloc ) );
				}
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				ditl_migration_accueil_extraire( $element['elements'], $data );
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
		'h2_count'      => 0,
		'hero'          => array(
			'sous_titre'   => '',
			'bouton_texte' => '',
			'bouton_url'   => '',
		),
		'presentation'  => array(
			'titre'        => '',
			'texte'        => '',
			'bouton_texte' => '',
			'bouton_url'   => '',
			'image_id'     => 0,
		),
		'livrables'     => array(
			'titre' => '',
			'intro' => '',
			'items' => array(),
		),
		'actualites'    => array(
			'titre' => '',
		),
		'partenaires'   => array(
			'titre'        => '',
			'texte'        => '',
			'bouton_texte' => '',
			'bouton_url'   => '',
			'logo_ids'     => array(),
			'carrousel'    => false,
		),
	);

	ditl_migration_accueil_extraire( $ditl_elements, $ditl_data );

	if ( 5 !== $ditl_data['h2_count'] ) {
		WP_CLI::warning( sprintf( 'Page %d : %d titre(s) H2 trouve(s) au lieu des 5 attendus, verifier le decoupage des blocs.', $ditl_page_id, $ditl_data['h2_count'] ) );
	}

	// CORRECTION VOLONTAIRE (seul ecart de contenu de la migration) : le logo
	// INSTITUT ESCOLA DEL TREBALL du carrousel EN reference l'attachment 4993,
	// supprime de la mediatheque (fichier 404 en prod, logo casse sur la home
	// EN en ligne). La page FR utilise deja son remplacant 5803
	// (logo-escola-del-treball.png, juillet 2026).
	foreach ( $ditl_data['partenaires']['logo_ids'] as $ditl_i => $ditl_logo_id ) {
		if ( 4993 === $ditl_logo_id ) {
			$ditl_data['partenaires']['logo_ids'][ $ditl_i ] = 5803;
			WP_CLI::warning( sprintf(
				'Page %d : CORRECTION VOLONTAIRE - logo partenaire 4993 (Logo-INSTITUT-ESCOLA-DEL-TREBALL, attachment supprime, fichier 404 en prod) remplace par 5803 (logo-escola-del-treball.png, deja utilise sur la page FR). A signaler au client.',
				$ditl_page_id
			) );
		}
	}

	// Verification d'existence de tous les attachments references.
	ditl_cli_verifier_attachment( $ditl_data['hero_image_id'], 'image de banniere' );
	ditl_cli_verifier_attachment( $ditl_data['presentation']['image_id'], 'image du bloc Presentation' );

	foreach ( $ditl_data['livrables']['items'] as $ditl_i => $ditl_item ) {
		ditl_cli_verifier_attachment( $ditl_item['image_id'], sprintf( 'vignette Livrables n.%d', $ditl_i + 1 ) );
	}

	foreach ( $ditl_data['partenaires']['logo_ids'] as $ditl_logo_id ) {
		ditl_cli_verifier_attachment( $ditl_logo_id, 'logo partenaire' );
	}

	// Recapitulatif lisible des valeurs extraites.
	WP_CLI::log( sprintf( '  _ditl_hero_image_id         : %d', $ditl_data['hero_image_id'] ) );
	WP_CLI::log( sprintf( '  _ditl_hero_title            : %s', $ditl_data['hero_title'] ) );
	WP_CLI::log( sprintf( '  _ditl_accueil_hero          : sous_titre "%s" | bouton "%s" -> %s', $ditl_data['hero']['sous_titre'], $ditl_data['hero']['bouton_texte'], $ditl_data['hero']['bouton_url'] ) );
	WP_CLI::log( sprintf( '  _ditl_accueil_presentation  : titre "%s" | texte %d caracteres | bouton "%s" -> %s | image %d', $ditl_data['presentation']['titre'], strlen( $ditl_data['presentation']['texte'] ), $ditl_data['presentation']['bouton_texte'], $ditl_data['presentation']['bouton_url'], $ditl_data['presentation']['image_id'] ) );
	WP_CLI::log( sprintf( '  _ditl_accueil_livrables     : titre "%s" | intro %d caracteres | %d vignette(s)', $ditl_data['livrables']['titre'], strlen( $ditl_data['livrables']['intro'] ), count( $ditl_data['livrables']['items'] ) ) );

	foreach ( $ditl_data['livrables']['items'] as $ditl_i => $ditl_item ) {
		WP_CLI::log( sprintf( '    %d. image %d, texte %d caracteres', $ditl_i + 1, $ditl_item['image_id'], strlen( $ditl_item['texte'] ) ) );
	}

	WP_CLI::log( sprintf( '  _ditl_accueil_actualites    : titre "%s"', $ditl_data['actualites']['titre'] ) );
	WP_CLI::log( sprintf(
		'  _ditl_accueil_partenaires   : titre "%s" | texte %d caracteres | bouton "%s" -> %s | %d logo(s) [%s] | carrousel=%s',
		$ditl_data['partenaires']['titre'],
		strlen( $ditl_data['partenaires']['texte'] ),
		$ditl_data['partenaires']['bouton_texte'],
		$ditl_data['partenaires']['bouton_url'],
		count( $ditl_data['partenaires']['logo_ids'] ),
		implode( ',', $ditl_data['partenaires']['logo_ids'] ),
		$ditl_data['partenaires']['carrousel'] ? 'true' : 'false'
	) );
	WP_CLI::log( sprintf( '  _wp_page_template           : %s', 'page-templates/accueil.php' ) );

	if ( $ditl_dry_run ) {
		WP_CLI::log( '  [dry-run] Rien n\'a ete ecrit pour cette page.' );
		continue;
	}

	// Ecriture des metas. update_post_meta est idempotent ; wp_slash compense
	// le wp_unslash applique en interne (le JSON contient des antislashs).
	// Les sanitize_callback declares via register_post_meta s'appliquent ici aussi.
	$ditl_options_json = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

	update_post_meta( $ditl_page_id, '_ditl_hero_image_id', $ditl_data['hero_image_id'] );
	update_post_meta( $ditl_page_id, '_ditl_hero_title', wp_slash( $ditl_data['hero_title'] ) );
	update_post_meta( $ditl_page_id, '_ditl_accueil_hero', wp_slash( (string) wp_json_encode( $ditl_data['hero'], $ditl_options_json ) ) );
	update_post_meta( $ditl_page_id, '_ditl_accueil_presentation', wp_slash( (string) wp_json_encode( $ditl_data['presentation'], $ditl_options_json ) ) );
	update_post_meta( $ditl_page_id, '_ditl_accueil_livrables', wp_slash( (string) wp_json_encode( $ditl_data['livrables'], $ditl_options_json ) ) );
	update_post_meta( $ditl_page_id, '_ditl_accueil_actualites', wp_slash( (string) wp_json_encode( $ditl_data['actualites'], $ditl_options_json ) ) );
	update_post_meta( $ditl_page_id, '_ditl_accueil_partenaires', wp_slash( (string) wp_json_encode( $ditl_data['partenaires'], $ditl_options_json ) ) );
	update_post_meta( $ditl_page_id, '_wp_page_template', 'page-templates/accueil.php' );

	WP_CLI::success( sprintf( 'Page %d migree.', $ditl_page_id ) );
}

WP_CLI::log( '' );
WP_CLI::log( $ditl_dry_run ? 'Simulation terminee.' : 'Migration terminee.' );
