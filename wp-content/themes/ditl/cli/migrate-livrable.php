<?php
/**
 * Migration du gabarit "Livrable" : donnees Elementor -> metas sur mesure.
 *
 * Lit le JSON _elementor_data des pages passees en argument et remplit les
 * metas du gabarit (contrat decrit dans inc/metaboxes/livrable.php).
 * Les pages FR (1852) et EN (4108) partagent la meme grammaire de widgets,
 * parcourue dans l'ordre du document :
 * - heading h1      -> _ditl_hero_title (texte simple, entites decodees) ;
 *                      ce gabarit n'a pas d'image de banniere (_ditl_hero_image_id
 *                      reste a 0, valeur par defaut de la meta). Sans h1 (pages
 *                      ES/PT/DE), le 1er heading place AVANT la carte en tient
 *                      lieu, en texte nettoye (balises retirees, entites
 *                      decodees : le titre PT contient du HTML colle) ; le
 *                      gabarit le rend en H1 (correction d'accessibilite
 *                      volontaire signalee par le script, style d'origine
 *                      repris par le CSS de la page)
 * - widget igmap    -> map_id de _ditl_livrable_carte (le plugin Interactive
 *                      Geo Maps est conserve)
 * - heading h2      -> OUVRE une nouvelle section de _ditl_livrables (titre
 *                      brut conserve tel quel : certains h2 portent un span
 *                      avec style inline, fidelite au rendu existant)
 * - text-editor     -> concatene au contenu de la section en cours ; les
 *                      blocs <style> colles dans le texte (page PT 4115,
 *                      section 3) sont retires avec leur contenu : invisibles
 *                      dans l'original, ils s'afficheraient en clair une fois
 *                      la balise retiree par wp_kses_post (signale par le script)
 * - heading h3      -> concatene <h3>titre brut</h3> au contenu de la section
 *                      en cours (la page EN separe ses h3 "Report Highlights" /
 *                      "Objectives" en widgets distincts la ou la page FR les
 *                      embarque dans un seul text-editor : la concatenation
 *                      ramene les deux langues au meme format, un seul champ
 *                      de contenu riche par section)
 * - button          -> bouton de telechargement de la section en cours
 * - divider, spacer -> decoratifs, ignores (le template regenere separateurs
 *                      et espacements)
 *
 * L'alternative textuelle de la carte (accessibilite : information des
 * tooltips, pays et villes du projet) est pre-remplie depuis la meta
 * "map_info" du post igmap reference (regions et marqueurs). Phrases dans la
 * langue Polylang de la page migree (FR, EN, ES, PT, DE ; anglais en repli),
 * les noms de pays et de villes venant de la carte de cette langue.
 *
 * Les URLs des boutons sont des PDF internes : rendues relatives comme sur
 * les gabarits precedents. Un fichier introuvable sur le disque local est
 * signale mais migre tel quel (les uploads locaux peuvent etre incomplets,
 * verifier en prod) ; url_to_postid ne s'applique pas ici, un fichier
 * d'uploads ne resout jamais vers un contenu.
 *
 * Pose aussi _wp_page_template = page-templates/livrable.php.
 *
 * Les metas Elementor (_elementor_data, _elementor_edit_mode, ...) ne sont
 * PAS modifiees : elles restent en place comme sauvegarde dormante.
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod). Sous
 * WordPress 7.1.3 et suivants, un rejeu sur la page EN 4108 deja migree
 * reecrit toutefois _ditl_livrables (word-spacing: 0px desormais conserve
 * dans les styles en ligne, sans effet visuel) : ne pas rejouer le script
 * sur une page deja migree.
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/migrate-livrable.php 1852 4108 dry-run
 *   wp eval-file wp-content/themes/ditl/cli/migrate-livrable.php 1852 4108
 *
 *   wp eval-file wp-content/themes/ditl/cli/migrate-livrable.php 4113 4115 4111 dry-run
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

if ( ! function_exists( 'ditl_migration_livrable_bouton' ) ) {
	/**
	 * Extrait un bouton Elementor {texte, url} avec URL interne rendue relative.
	 *
	 * Les URLs internes sont des fichiers d'uploads (PDF des livrables) :
	 * url_to_postid ne resout jamais ce type de cible, la verification porte
	 * donc sur l'existence du fichier sur le disque local. Un fichier absent
	 * est signale mais l'URL est migree telle quelle (uploads locaux
	 * possiblement incomplets, verifier en prod).
	 *
	 * @param array  $reglages Reglages du widget button.
	 * @param string $contexte Libelle du bouton pour les messages.
	 * @return array Tableau {texte, url}.
	 */
	function ditl_migration_livrable_bouton( $reglages, $contexte ) {
		$texte = isset( $reglages['text'] ) ? sanitize_text_field( (string) $reglages['text'] ) : '';
		$url   = isset( $reglages['link']['url'] ) ? trim( (string) $reglages['link']['url'] ) : '';

		if ( '' !== $url ) {
			$url = ditl_cli_url_relative( $url );

			// URL interne (devenue relative) : verifie que le fichier existe.
			// Les chemins contenant ".." sont rejetes (pas de sortie d'ABSPATH).
			if ( 0 === strpos( $url, '/' ) ) {
				$chemin  = rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) );
				$fichier = untrailingslashit( ABSPATH ) . $chemin;

				if ( '' === $chemin || false !== strpos( $chemin, '..' ) || ! file_exists( $fichier ) ) {
					WP_CLI::warning( sprintf(
						'Bouton "%s" (%s) : fichier "%s" absent en local, verifier en prod. URL migree telle quelle.',
						$texte,
						$contexte,
						$url
					) );
				}
			}
		}

		return array(
			'texte' => $texte,
			'url'   => $url,
		);
	}
}

if ( ! function_exists( 'ditl_migration_livrable_alternative' ) ) {
	/**
	 * Construit l'alternative textuelle de la carte depuis sa meta "map_info".
	 *
	 * Reprend l'information portee par les tooltips de la carte : noms des
	 * regions (pays partenaires) et contenus des marqueurs (villes). Texte
	 * simple, dans la langue de la page migree (anglais en repli). Phrases
	 * ES/PT/DE a faire relire par un locuteur natif.
	 *
	 * @param int    $map_id ID du post igmap reference.
	 * @param string $langue Code de langue Polylang de la page (fr, en, es, pt, de).
	 * @return string Alternative textuelle ('' si map_info illisible ou vide).
	 */
	function ditl_migration_livrable_alternative( $map_id, $langue ) {
		$libelles = array(
			'fr' => array( 'Carte de l\'Europe mettant en évidence les pays partenaires du projet DiTL : %s.', 'Villes partenaires signalées : %s.' ),
			'en' => array( 'Map of Europe highlighting the partner countries of the DiTL project: %s.', 'Partner cities shown: %s.' ),
			'es' => array( 'Mapa de Europa que destaca los países socios del proyecto DiTL: %s.', 'Ciudades socias señaladas: %s.' ),
			'pt' => array( 'Mapa da Europa que destaca os países parceiros do projeto DiTL: %s.', 'Cidades parceiras assinaladas: %s.' ),
			'de' => array( 'Europakarte mit den Partnerländern des DiTL-Projekts: %s.', 'Markierte Partnerstädte: %s.' ),
		);
		$phrase = isset( $libelles[ $langue ] ) ? $libelles[ $langue ] : $libelles['en'];

		$info = get_post_meta( $map_id, 'map_info', true );

		if ( ! is_array( $info ) ) {
			return '';
		}

		$pays = array();

		if ( isset( $info['regions'] ) && is_array( $info['regions'] ) ) {
			foreach ( $info['regions'] as $region ) {
				if ( isset( $region['name'] ) && is_scalar( $region['name'] ) ) {
					$nom = sanitize_text_field( wp_strip_all_tags( (string) $region['name'] ) );

					if ( '' !== $nom ) {
						$pays[] = $nom;
					}
				}
			}
		}

		$villes = array();

		if ( isset( $info['roundMarkers'] ) && is_array( $info['roundMarkers'] ) ) {
			foreach ( $info['roundMarkers'] as $marqueur ) {
				if ( isset( $marqueur['tooltipContent'] ) && is_scalar( $marqueur['tooltipContent'] ) ) {
					$nom = sanitize_text_field( wp_strip_all_tags( (string) $marqueur['tooltipContent'] ) );

					if ( '' !== $nom ) {
						$villes[] = $nom;
					}
				}
			}
		}

		if ( array() === $pays && array() === $villes ) {
			return '';
		}

		$phrases = array();

		if ( array() !== $pays ) {
			$phrases[] = sprintf( $phrase[0], implode( ', ', $pays ) );
		}

		if ( array() !== $villes ) {
			$phrases[] = sprintf( $phrase[1], implode( ', ', $villes ) );
		}

		return implode( ' ', $phrases );
	}
}

if ( ! function_exists( 'ditl_migration_livrable_extraire' ) ) {
	/**
	 * Parcourt recursivement l'arbre Elementor et collecte les donnees du gabarit.
	 *
	 * L'etat de la collecte vit dans $data : chaque heading h2 ouvre une
	 * nouvelle section, les widgets suivants (text-editor, h3, button)
	 * alimentent la derniere section ouverte.
	 *
	 * @param array $elements Elements Elementor (containers et widgets).
	 * @param array $data     Donnees collectees (passees par reference).
	 */
	function ditl_migration_livrable_extraire( $elements, &$data ) {
		foreach ( $elements as $element ) {
			if ( isset( $element['elType'] ) && 'widget' === $element['elType'] ) {
				$type     = isset( $element['widgetType'] ) ? $element['widgetType'] : '';
				$reglages = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

				// Repere sur la section en cours.
				$idx = count( $data['sections'] ) - 1;

				switch ( $type ) {
					case 'heading':
						// Titre brut du widget (peut contenir du HTML sur les h2).
						$titre_brut = isset( $reglages['title'] ) ? (string) $reglages['title'] : '';
						// Version texte simple (entites decodees, balises retirees).
						$titre_texte = sanitize_text_field( html_entity_decode( wp_strip_all_tags( $titre_brut ), ENT_QUOTES, 'UTF-8' ) );
						// h2 est la valeur par defaut d'Elementor quand header_size est absent.
						$niveau = isset( $reglages['header_size'] ) && '' !== $reglages['header_size'] ? (string) $reglages['header_size'] : 'h2';

						if ( 'h1' === $niveau ) {
							if ( '' === $data['hero_title'] ) {
								$data['hero_title']        = $titre_texte;
								$data['hero_title_niveau'] = 'h1';
							} elseif ( 'h1' !== $data['hero_title_niveau'] ) {
								// Un heading place avant le h1 avait ete pris pour titre :
								// le h1 reste prioritaire.
								WP_CLI::warning( sprintf( 'Heading %s place avant le h1 ignore comme titre : "%s"', $data['hero_title_niveau'], $data['hero_title'] ) );
								$data['hero_title']        = $titre_texte;
								$data['hero_title_niveau'] = 'h1';
							} else {
								WP_CLI::warning( sprintf( 'Heading h1 surnumeraire ignore : "%s"', $titre_texte ) );
							}
						} elseif ( '' === $data['hero_title'] && 0 === $data['map_id'] && empty( $data['sections'] ) ) {
							// Pas de h1 : le 1er heading avant la carte est le titre
							// de la page (pages ES/PT/DE, ou il est en h2).
							$data['hero_title']        = $titre_texte;
							$data['hero_title_niveau'] = $niveau;
						} elseif ( 'h2' === $niveau ) {
							// Nouvelle section de livrable (titre brut conserve tel quel).
							$data['sections'][] = array(
								'titre'        => trim( $titre_brut ),
								'contenu'      => '',
								'bouton_texte' => '',
								'bouton_url'   => '',
							);
						} elseif ( 'h3' === $niveau ) {
							// Sous-titre de section (page EN) : concatene au contenu
							// pour ramener les deux langues au meme format.
							if ( $idx >= 0 ) {
								$data['sections'][ $idx ]['contenu'] .= ( '' !== $data['sections'][ $idx ]['contenu'] ? "\n" : '' ) . '<h3>' . trim( $titre_brut ) . '</h3>';
							} else {
								WP_CLI::warning( sprintf( 'Heading h3 orphelin (aucune section ouverte) ignore : "%s"', $titre_texte ) );
							}
						} else {
							WP_CLI::warning( sprintf( 'Heading %s inattendu ignore : "%s"', $niveau, $titre_texte ) );
						}
						break;

					case 'igmap':
						$map_id = isset( $reglages['mapID'] ) && is_scalar( $reglages['mapID'] ) ? absint( $reglages['mapID'] ) : 0;

						if ( 0 === $data['map_id'] ) {
							$data['map_id'] = $map_id;
						} else {
							WP_CLI::warning( sprintf( 'Widget igmap surnumeraire ignore (carte %d).', $map_id ) );
						}
						break;

					case 'text-editor':
						// Le HTML est conserve tel quel ; wp_kses_post est applique
						// a l'enregistrement par le sanitize_callback de la meta.
						$html = isset( $reglages['editor'] ) ? trim( (string) $reglages['editor'] ) : '';

						// Bloc <style> colle (invisible dans l'original) : retire
						// avec son contenu, chaine de remplacement fixe.
						$sans_style = preg_replace( '#<style\b[^>]*>.*?</style\s*>#is', '', $html );

						if ( null !== $sans_style && $sans_style !== $html ) {
							WP_CLI::warning( 'Bloc <style> colle dans un texte retire (invisible dans l\'original, il s\'afficherait en clair).' );
							$html = trim( $sans_style );
						}

						if ( null === $sans_style || false !== stripos( $html, '<style' ) ) {
							WP_CLI::warning( 'Balise <style> non retiree dans un texte (non fermee ?) : verifier le rendu de la section.' );
						}

						if ( '' === $html ) {
							break;
						}

						if ( $idx >= 0 ) {
							$data['sections'][ $idx ]['contenu'] .= ( '' !== $data['sections'][ $idx ]['contenu'] ? "\n" : '' ) . $html;
						} else {
							WP_CLI::warning( sprintf( 'Widget text-editor orphelin (aucune section ouverte) ignore : "%s..."', mb_substr( wp_strip_all_tags( $html ), 0, 60 ) ) );
						}
						break;

					case 'button':
						if ( $idx < 0 ) {
							WP_CLI::warning( 'Widget button orphelin (aucune section ouverte) ignore.' );
						} elseif ( '' !== $data['sections'][ $idx ]['bouton_texte'] || '' !== $data['sections'][ $idx ]['bouton_url'] ) {
							WP_CLI::warning( sprintf( 'Widget button surnumeraire ignore (section n.%d, bouton deja pose).', $idx + 1 ) );
						} else {
							$bouton = ditl_migration_livrable_bouton( $reglages, sprintf( 'section n.%d', $idx + 1 ) );

							$data['sections'][ $idx ]['bouton_texte'] = $bouton['texte'];
							$data['sections'][ $idx ]['bouton_url']   = $bouton['url'];
						}
						break;

					case 'divider':
					case 'spacer':
						// Purement decoratifs : le template regenere separateurs
						// et espacements.
						break;

					default:
						WP_CLI::warning( sprintf( 'Widget "%s" inattendu ignore.', $type ) );
				}
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				ditl_migration_livrable_extraire( $element['elements'], $data );
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
		'hero_title'        => '',
		'hero_title_niveau' => '',
		'map_id'            => 0,
		'sections'          => array(),
	);

	ditl_migration_livrable_extraire( $ditl_elements, $ditl_data );

	// Controles de coherence : les pages de prod comptent 4 sections chacune.
	if ( '' === $ditl_data['hero_title'] ) {
		WP_CLI::warning( sprintf( 'Page %d : aucun titre H1 trouve pour la banniere.', $ditl_page_id ) );
	} elseif ( 'h1' !== $ditl_data['hero_title_niveau'] ) {
		WP_CLI::warning( sprintf(
			'Page %d : le titre de page "%s" est un %s dans Elementor (page sans H1). Migre comme titre de page : le gabarit le rendra en H1 (correction d\'accessibilite volontaire, style d\'origine repris par le CSS de la page).',
			$ditl_page_id,
			$ditl_data['hero_title'],
			strtoupper( $ditl_data['hero_title_niveau'] )
		) );
	}

	if ( 0 === $ditl_data['map_id'] ) {
		WP_CLI::warning( sprintf( 'Page %d : aucun widget igmap trouve, aucune carte migree.', $ditl_page_id ) );
	} elseif ( 'igmap' !== get_post_type( $ditl_data['map_id'] ) ) {
		WP_CLI::warning( sprintf(
			'Page %d : la carte %d referencee par Elementor n\'existe pas (ou n\'est pas une carte igmap) : la meta sera remise a 0.',
			$ditl_page_id,
			$ditl_data['map_id']
		) );
	}

	if ( empty( $ditl_data['sections'] ) ) {
		WP_CLI::warning( sprintf( 'Page %d : aucune section de livrable trouvee.', $ditl_page_id ) );
	}

	foreach ( $ditl_data['sections'] as $ditl_i => $ditl_section ) {
		foreach ( array( 'titre', 'contenu', 'bouton_texte', 'bouton_url' ) as $ditl_champ ) {
			if ( '' === $ditl_section[ $ditl_champ ] ) {
				WP_CLI::warning( sprintf(
					'Section n.%d : champ "%s" vide, verifier le decoupage.',
					$ditl_i + 1,
					$ditl_champ
				) );
			}
		}
	}

	// Alternative textuelle de la carte : pre-remplie depuis map_info, phrases
	// dans la langue Polylang de la page (anglais en repli).
	$ditl_langue      = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $ditl_page_id ) : '';
	$ditl_alternative = '';

	if ( $ditl_data['map_id'] > 0 && 'igmap' === get_post_type( $ditl_data['map_id'] ) ) {
		$ditl_alternative = ditl_migration_livrable_alternative( $ditl_data['map_id'], $ditl_langue );

		if ( '' === $ditl_alternative ) {
			WP_CLI::warning( sprintf(
				'Page %d : meta map_info de la carte %d illisible ou vide, alternative textuelle laissee vide (a saisir en admin).',
				$ditl_page_id,
				$ditl_data['map_id']
			) );
		}
	}

	// Recapitulatif lisible des valeurs extraites.
	WP_CLI::log( sprintf(
		'  _ditl_hero_title     : %s%s',
		$ditl_data['hero_title'],
		'' !== $ditl_data['hero_title_niveau'] ? ' (' . strtoupper( $ditl_data['hero_title_niveau'] ) . ' dans Elementor)' : ''
	) );
	WP_CLI::log( sprintf( '  _ditl_livrable_carte : carte %d | alternative %d caracteres', $ditl_data['map_id'], mb_strlen( $ditl_alternative ) ) );
	WP_CLI::log( sprintf( '  _ditl_livrables      : %d section(s)', count( $ditl_data['sections'] ) ) );

	foreach ( $ditl_data['sections'] as $ditl_section ) {
		WP_CLI::log( sprintf(
			'      titre "%s" | contenu %d car. | bouton "%s" -> %s',
			mb_substr( sanitize_text_field( html_entity_decode( wp_strip_all_tags( $ditl_section['titre'] ), ENT_QUOTES, 'UTF-8' ) ), 0, 60 ),
			mb_strlen( $ditl_section['contenu'] ),
			$ditl_section['bouton_texte'],
			$ditl_section['bouton_url']
		) );
	}

	WP_CLI::log( sprintf( '  _wp_page_template    : %s', 'page-templates/livrable.php' ) );

	if ( $ditl_dry_run ) {
		WP_CLI::log( '  [dry-run] Rien n\'a ete ecrit pour cette page.' );
		continue;
	}

	$ditl_carte = array(
		'map_id'      => $ditl_data['map_id'],
		'alternative' => $ditl_alternative,
	);

	// Ecriture des metas. update_post_meta est idempotent ; wp_slash compense
	// le wp_unslash applique en interne (le JSON contient des antislashs).
	// Les sanitize_callback declares via register_post_meta s'appliquent ici aussi.
	update_post_meta( $ditl_page_id, '_ditl_hero_title', wp_slash( $ditl_data['hero_title'] ) );
	update_post_meta( $ditl_page_id, '_ditl_livrable_carte', wp_slash( (string) wp_json_encode( $ditl_carte, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	update_post_meta( $ditl_page_id, '_ditl_livrables', wp_slash( (string) wp_json_encode( $ditl_data['sections'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	update_post_meta( $ditl_page_id, '_wp_page_template', 'page-templates/livrable.php' );

	WP_CLI::success( sprintf( 'Page %d migree.', $ditl_page_id ) );
}

WP_CLI::log( '' );
WP_CLI::log( $ditl_dry_run ? 'Simulation terminee.' : 'Migration terminee.' );
