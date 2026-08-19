<?php
/**
 * Migration des articles construits avec Elementor : donnees Elementor -> contenu classique.
 *
 * Contrairement aux migrations de gabarits (metas + template), ce script
 * convertit les articles en CONTENU WORDPRESS CLASSIQUE : le HTML assemble
 * depuis le JSON _elementor_data devient le post_content, rendu par le
 * template single standard d'Astra via the_content, comme les autres
 * articles du site. Aucune metabox, aucun template dedie.
 *
 * Perimetre : les 10 articles Elementor du site (2 actualites x 5 langues).
 * - "Analyse comparative" (5245 en, 5258 fr, 5263 es, 5268 pt, 5273 de) :
 *   1 ou 2 widgets text-editor (HTML riche avec attributs data-start/data-end
 *   herites d'un copier-coller, conserves tels quels - fidelite au byte).
 * - "Premiere newsletter" (5665 en, 5703 fr, 5709 de, 5715 pt, 5721 es) :
 *   widget image + widget text-editor + 5 widgets button (PDF par langue).
 *   Le text-editor contient une balise <style> heritee d'un copier-coller de
 *   newsletter : migree TELLE QUELLE (deja en prod, CSS de toute facon inerte
 *   car truffe de <br /> litteraux), signalee en warning comme dette client.
 *
 * Grammaire de conversion (ordre du document, parcours recursif) :
 * - widget image       -> <figure class="ditl-art-image"> + wp_get_attachment_image
 *                         de l'ID a la taille reglee par le widget (reglage
 *                         image_size ; absent = "large", defaut Elementor,
 *                         verifie sur le rendu de prod : classe
 *                         "attachment-large size-large"). Jamais d'URL prise
 *                         dans le JSON : si l'attachment n'existe plus,
 *                         warning et aucun bloc. Les URLs (src/srcset) sont
 *                         celles de l'environnement d'execution : le script
 *                         est rejouable en preprod/prod ou il regenerera les
 *                         URLs locales a chaque environnement.
 * - widget text-editor -> HTML conserve tel quel, enveloppe dans
 *                         <div class="ditl-art-texte">. Le reglage align
 *                         "justify" (articles "Analyse comparative") est porte
 *                         par la classe modificatrice ditl-art-texte--justifie
 *                         pour que le CSS du theme reproduise l'alignement.
 *                         SEULE transformation appliquee au HTML : les doubles
 *                         <br /><br /> (idiome du redacteur pour une ligne
 *                         vide) sont convertis en <p>&nbsp;</p>, car wpautop
 *                         les scinde en paragraphes en PERDANT la ligne vide
 *                         (Astra pose margin-bottom:0 sur les p : l'ecart
 *                         visuel serait reel). <p>&nbsp;</p> est l'equivalent
 *                         visuel exact (une ligne), stable a wpautop, et
 *                         l'idiome deja employe ailleurs dans ces contenus.
 * - widget button      -> boutons consecutifs regroupes dans un
 *                         <p class="ditl-art-boutons">, chaque bouton devenant
 *                         <a class="ditl-art-bouton" href="...">texte</a>.
 *                         URLs internes (ditlproject.eu) rendues relatives ;
 *                         pour les fichiers internes (PDF), verification
 *                         file_exists (chemins ".." rejetes), fichier absent
 *                         signale mais URL migree telle quelle.
 * - container, spacer, divider -> purement structurels/decoratifs, ignores.
 *
 * Les blocs sont joints par une ligne vide. Chaque bloc genere tient sur une
 * ligne et commence/finit par une balise de bloc : il est stable au passage
 * de wpautop applique par the_content au rendu (les balises figure, div, p et
 * style figurent toutes dans la liste des blocs proteges de wpautop). Cas
 * limite verifie : le saut de ligne interne a la balise <style> recoit un
 * <br /> de wpautop, immediatement retire par son nettoyage des <br />
 * adjacents aux balises de bloc - le rendu de la zone de contenu est
 * structurellement identique au rendu Elementor (verification empirique
 * avant/apres documentee dans le rapport de migration).
 *
 * BASCULE DE RENDU : la meta _elementor_edit_mode est SUPPRIMEE - c'est elle
 * qui declenche la prise en main du rendu par Elementor ; sans elle, WP rend
 * post_content classiquement. Les metas _elementor_data et
 * _elementor_template_type ne sont PAS touchees (sauvegarde dormante, purge
 * prevue en phase 2). L'ancien post_content (copie de secours generee par
 * Elementor) est sauvegarde dans la meta _ditl_backup_post_content, ecrite
 * UNE SEULE FOIS (jamais ecrasee au rejeu).
 *
 * EFFETS DE BORD ASSUMES de wp_update_post : une revision est creee (seconde
 * sauvegarde de l'ancien contenu) et post_modified / post_modified_gmt sont
 * mis a jour sur chaque article ecrit - signal visible (lastmod des sitemaps,
 * date de mise a jour selon reglage du theme). A rappeler avant tout rejeu en
 * preprod/prod.
 *
 * REVERSIBILITE : pour revenir au rendu Elementor d'un article,
 *   wp post meta update <id> _elementor_edit_mode builder
 *   et restaurer post_content depuis _ditl_backup_post_content si besoin.
 *
 * FILTRES A L'ENREGISTREMENT : wp_update_post applique content_save_pre.
 * Verification faite sur cet environnement : en contexte WP-CLI sans
 * utilisateur, les filtres kses (wp_filter_post_kses) ne sont PAS actifs et
 * le HTML (balise <style> comprise) traverse intact. Garde-fou double, au cas
 * ou le script serait rejoue dans un contexte filtre : simulation de
 * content_save_pre AVANT ecriture (article refuse si le HTML serait altere,
 * relancer alors avec --user=<admin disposant de unfiltered_html>), puis
 * verification au byte pres APRES ecriture (relecture en base).
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod) : au rejeu,
 * le meme post_content est regenere au byte pres et aucune ecriture n'a lieu
 * (ni post_content, ni sauvegarde).
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/migrate-articles.php 5245 5258 5263 5268 5273 5665 5703 5709 5715 5721 dry-run
 *   wp eval-file wp-content/themes/ditl/cli/migrate-articles.php 5245 5258 5263 5268 5273 5665 5703 5709 5715 5721
 *
 * Le mode simulation accepte "dry-run" ou "--dry-run" en argument.
 * Seuls les articles (post_type "post") construits avec Elementor (meta
 * _elementor_edit_mode presente) ou deja migres par ce script (meta
 * _ditl_backup_post_content presente) sont acceptes : un article classique
 * jamais passe par Elementor est refuse avec warning.
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

if ( ! function_exists( 'ditl_migration_articles_bouton' ) ) {
	/**
	 * Extrait un bouton Elementor {texte, url} avec URL interne rendue relative.
	 *
	 * Les URLs internes sont des fichiers d'uploads (PDF des newsletters) :
	 * url_to_postid ne resout jamais ce type de cible, la verification porte
	 * donc sur l'existence du fichier sur le disque local. Un fichier absent
	 * est signale mais l'URL est migree telle quelle (uploads locaux
	 * possiblement incomplets, verifier en prod).
	 *
	 * @param array  $reglages Reglages du widget button.
	 * @param string $contexte Libelle du bouton pour les messages.
	 * @return array Tableau {texte, url}.
	 */
	function ditl_migration_articles_bouton( $reglages, $contexte ) {
		$texte = isset( $reglages['text'] ) ? sanitize_text_field( (string) $reglages['text'] ) : '';
		$url   = isset( $reglages['link']['url'] ) ? trim( (string) $reglages['link']['url'] ) : '';

		if ( '' !== $url ) {
			$url = ditl_cli_url_relative( $url );

			// URL interne (devenue relative) : verifie que le fichier existe.
			// Les chemins contenant ".." sont rejetes (pas de sortie d'ABSPATH)
			// et les URLs protocole-relatif (//hote/...) ne sont pas des
			// chemins locaux.
			if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
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

if ( ! function_exists( 'ditl_migration_articles_stabiliser_wpautop' ) ) {
	/**
	 * Rend le HTML d'un text-editor stable au passage de wpautop.
	 *
	 * wpautop (applique par the_content au rendu classique) remplace toute
	 * paire adjacente <br /><br /> par une coupure de paragraphe : la ligne
	 * vide voulue par le redacteur disparait (les p du theme sont a marge
	 * nulle). La paire est donc convertie en un paragraphe <p>&nbsp;</p>,
	 * equivalent visuel exact d'une ligne vide, que wpautop laisse intact
	 * (son nettoyage des paragraphes vides ne matche pas l'entite &nbsp;).
	 *
	 * Deux cas dans le corpus :
	 * - fin de paragraphe "...<br /><br /></p>" (parfois </span></p>) :
	 *   la paire est deplacee apres la fermeture, en <p>&nbsp;</p> ;
	 * - pleine phrase "...<br /><br />suite..." : le paragraphe est scinde
	 *   (ce que wpautop ferait de toute facon) avec la ligne vide preservee.
	 *   Ce cas suppose la paire directement dans un <p> : signale pour
	 *   verification du rendu.
	 *
	 * @param string $html     HTML du widget text-editor.
	 * @param string $contexte Contexte pour les messages.
	 * @return string HTML stabilise.
	 */
	function ditl_migration_articles_stabiliser_wpautop( $html, $contexte ) {
		// Une sequence de 3 <br /> ou plus n'est pas geree par les deux cas
		// ci-dessous (une ligne vide serait perdue silencieusement) : signalee
		// pour arbitrage manuel. Aucune dans le corpus migre.
		if ( preg_match( '#(?:<br\s*/?>\s*){3,}#', $html ) ) {
			WP_CLI::warning( sprintf(
				'Sequence de 3 <br /> ou plus (%s) : stabilisation wpautop incomplete possible, verifier le rendu de ce passage.',
				$contexte
			) );
		}

		// Cas 1 : paire en fin de paragraphe (fermetures span eventuelles).
		$html = preg_replace(
			'#<br\s*/?>\s*<br\s*/?>\s*((?:</span>)*</p>)#',
			'$1<p>&nbsp;</p>',
			$html,
			-1,
			$nb_fin
		);

		// Cas 2 : paire residuelle en pleine phrase, directement dans un <p>.
		$html = preg_replace(
			'#<br\s*/?>\s*<br\s*/?>#',
			'</p><p>&nbsp;</p><p>',
			$html,
			-1,
			$nb_phrase
		);

		if ( $nb_fin > 0 || $nb_phrase > 0 ) {
			WP_CLI::log( sprintf(
				'  Stabilisation wpautop (%s) : %d double(s) <br /> convertis en <p>&nbsp;</p> (ligne vide preservee au rendu).',
				$contexte,
				$nb_fin + $nb_phrase
			) );
		}

		if ( $nb_phrase > 0 ) {
			WP_CLI::warning( sprintf(
				'Double <br /> en pleine phrase (%s) : paragraphe scinde avec ligne vide preservee - verifier le rendu de ce passage.',
				$contexte
			) );
		}

		return $html;
	}
}

if ( ! function_exists( 'ditl_migration_articles_extraire' ) ) {
	/**
	 * Parcourt recursivement l'arbre Elementor et collecte les blocs de contenu.
	 *
	 * Chaque widget reconnu devient un bloc {type, ...} dans l'ordre du
	 * document ; l'assemblage HTML est fait ensuite (les boutons consecutifs
	 * y sont regroupes).
	 *
	 * @param array $elements Elements Elementor (containers et widgets).
	 * @param array $data     Donnees collectees (passees par reference).
	 */
	function ditl_migration_articles_extraire( $elements, &$data ) {
		foreach ( $elements as $element ) {
			if ( isset( $element['elType'] ) && 'widget' === $element['elType'] ) {
				$type     = isset( $element['widgetType'] ) ? $element['widgetType'] : '';
				$reglages = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

				switch ( $type ) {
					case 'image':
						$image_id = isset( $reglages['image']['id'] ) ? absint( $reglages['image']['id'] ) : 0;
						// Taille reglee par le widget ; absente = "large",
						// valeur par defaut du widget image d'Elementor.
						$taille = isset( $reglages['image_size'] ) && is_scalar( $reglages['image_size'] ) && '' !== (string) $reglages['image_size']
							? (string) $reglages['image_size']
							: 'large';

						if ( 'custom' === $taille ) {
							WP_CLI::warning( sprintf( 'Widget image (attachment %d) : taille "custom" non geree, taille "large" utilisee.', $image_id ) );
							$taille = 'large';
						}

						$data['blocs'][] = array(
							'type'   => 'image',
							'id'     => $image_id,
							'taille' => $taille,
						);
						break;

					case 'text-editor':
						// Le HTML est conserve TEL QUEL (fidelite au byte),
						// y compris les attributs data-start/data-end et la
						// balise <style> herites de copier-coller. Seule
						// exception : les doubles <br />, instables a wpautop
						// (voir ditl_migration_articles_stabiliser_wpautop).
						$html = isset( $reglages['editor'] ) ? (string) $reglages['editor'] : '';

						if ( '' === trim( $html ) ) {
							WP_CLI::warning( 'Widget text-editor vide ignore.' );
							break;
						}

						$html = ditl_migration_articles_stabiliser_wpautop(
							$html,
							sprintf( 'bloc texte n.%d', count( $data['blocs'] ) + 1 )
						);

						if ( false !== stripos( $html, '<style' ) ) {
							WP_CLI::warning( 'Widget text-editor : balise <style> heritee d\'un copier-coller de newsletter, migree TELLE QUELLE (deja en prod ; CSS inerte, truffe de <br /> - dette client a signaler).' );
						}

						$align = isset( $reglages['align'] ) && is_scalar( $reglages['align'] ) ? (string) $reglages['align'] : '';

						if ( '' !== $align && 'justify' !== $align ) {
							WP_CLI::warning( sprintf( 'Widget text-editor : alignement "%s" inattendu, non reporte dans le HTML (seul "justify" est gere).', $align ) );
						}

						$data['blocs'][] = array(
							'type'  => 'texte',
							'html'  => $html,
							'align' => $align,
						);
						break;

					case 'button':
						$bouton = ditl_migration_articles_bouton( $reglages, sprintf( 'bouton n.%d', count( $data['blocs'] ) + 1 ) );

						if ( '' === $bouton['url'] ) {
							WP_CLI::warning( sprintf( 'Bouton "%s" sans URL, migre avec un href vide.', $bouton['texte'] ) );
						}

						$data['blocs'][] = array(
							'type'  => 'bouton',
							'texte' => $bouton['texte'],
							'url'   => $bouton['url'],
						);
						break;

					case 'divider':
					case 'spacer':
						// Purement decoratifs : rien a reporter dans le contenu.
						break;

					default:
						WP_CLI::warning( sprintf( 'Widget "%s" inattendu ignore.', $type ) );
				}
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				ditl_migration_articles_extraire( $element['elements'], $data );
			}
		}
	}
}

if ( ! function_exists( 'ditl_migration_articles_assembler' ) ) {
	/**
	 * Assemble les blocs collectes en HTML de post_content.
	 *
	 * Les boutons consecutifs sont regroupes dans un meme paragraphe
	 * ditl-art-boutons. Chaque bloc tient sur sa propre ligne, les blocs sont
	 * joints par une ligne vide : forme stable au passage de wpautop.
	 *
	 * @param array $blocs    Blocs {type, ...} dans l'ordre du document.
	 * @param int   $post_id  ID de l'article (messages).
	 * @return string HTML du post_content.
	 */
	function ditl_migration_articles_assembler( $blocs, $post_id ) {
		$morceaux = array();
		$boutons  = array();

		foreach ( $blocs as $bloc ) {
			if ( 'bouton' === $bloc['type'] ) {
				$boutons[] = '<a class="ditl-art-bouton" href="' . esc_url( $bloc['url'] ) . '">' . esc_html( $bloc['texte'] ) . '</a>';
				continue;
			}

			if ( array() !== $boutons ) {
				$morceaux[] = '<p class="ditl-art-boutons">' . implode( '', $boutons ) . '</p>';
				$boutons    = array();
			}

			if ( 'image' === $bloc['type'] ) {
				// Jamais d'URL en dur depuis le JSON : le HTML de l'image est
				// regenere par WordPress (src/srcset de l'environnement
				// d'execution - le script est rejouable en preprod/prod).
				$img = $bloc['id'] > 0 ? wp_get_attachment_image( $bloc['id'], $bloc['taille'] ) : '';

				if ( '' === $img ) {
					WP_CLI::warning( sprintf(
						'Article %d : attachment %d introuvable, widget image non migre.',
						$post_id,
						$bloc['id']
					) );
					continue;
				}

				$morceaux[] = '<figure class="ditl-art-image">' . $img . '</figure>';
				continue;
			}

			// Bloc texte : HTML conserve tel quel, alignement justify porte
			// par une classe modificatrice (stylage cote theme).
			$classe = 'ditl-art-texte' . ( 'justify' === $bloc['align'] ? ' ditl-art-texte--justifie' : '' );

			$morceaux[] = '<div class="' . $classe . '">' . $bloc['html'] . '</div>';
		}

		if ( array() !== $boutons ) {
			$morceaux[] = '<p class="ditl-art-boutons">' . implode( '', $boutons ) . '</p>';
		}

		return implode( "\n\n", $morceaux );
	}
}

// ---------------------------------------------------------------------------
// Lecture des arguments : IDs d'articles + mode simulation eventuel.
// ---------------------------------------------------------------------------

$ditl_modes    = ditl_cli_lire_ids_et_dry_run( $args, 'd\'article' );
$ditl_dry_run  = $ditl_modes['dry_run'];
$ditl_post_ids = $ditl_modes['ids'];

// ---------------------------------------------------------------------------
// Traitement article par article.
// ---------------------------------------------------------------------------

foreach ( $ditl_post_ids as $ditl_post_id ) {
	// Garde propre aux articles : uniquement ceux construits avec Elementor
	// (meta _elementor_edit_mode presente, meme vide) ou deja migres par ce
	// script (marqueur _ditl_backup_post_content). Un article classique
	// jamais passe par Elementor n'a rien a faire ici.
	$ditl_elements = ditl_cli_charger_arbre_elementor(
		$ditl_post_id,
		'post',
		static function ( $ditl_post ) {
			if ( ! metadata_exists( 'post', $ditl_post->ID, '_elementor_edit_mode' )
				&& ! metadata_exists( 'post', $ditl_post->ID, '_ditl_backup_post_content' ) ) {
				WP_CLI::warning( sprintf( 'Article %d : jamais construit avec Elementor (pas de meta _elementor_edit_mode) ni migre par ce script : refuse.', $ditl_post->ID ) );
				return false;
			}

			return true;
		}
	);

	if ( null === $ditl_elements ) {
		continue;
	}

	// Relecture de l'article pour la suite du traitement (comparaison du
	// contenu avant ecriture) ; l'objet vient du cache, aucun cout ajoute.
	$ditl_post = get_post( $ditl_post_id );

	// Les deux drapeaux testes par la garde resservent plus bas (sauvegarde
	// initiale, purge du mode d'edition) : recalcules ici plutot qu'exportes
	// de la closure, les metas sont en cache, aucun cout ajoute.
	$ditl_edit_mode_presente = metadata_exists( 'post', $ditl_post_id, '_elementor_edit_mode' );
	$ditl_deja_migre         = metadata_exists( 'post', $ditl_post_id, '_ditl_backup_post_content' );

	$ditl_data = array(
		'blocs' => array(),
	);

	ditl_migration_articles_extraire( $ditl_elements, $ditl_data );

	if ( empty( $ditl_data['blocs'] ) ) {
		WP_CLI::warning( sprintf( 'Article %d : aucun bloc de contenu extrait, article ignore.', $ditl_post_id ) );
		continue;
	}

	$ditl_contenu = ditl_migration_articles_assembler( $ditl_data['blocs'], $ditl_post_id );

	if ( '' === $ditl_contenu ) {
		WP_CLI::warning( sprintf( 'Article %d : contenu assemble vide, article ignore.', $ditl_post_id ) );
		continue;
	}

	// Garde-fou filtres d'enregistrement : simule content_save_pre (kses et
	// autres filtres actifs dans le contexte courant). Verification faite en
	// CLI sans utilisateur sur cet environnement : aucun filtre n'altere le
	// HTML. Si un contexte filtre le HTML (le <style> notamment), l'article
	// est refuse plutot que d'ecrire un contenu degrade.
	$ditl_contenu_filtre = wp_unslash( (string) apply_filters( 'content_save_pre', wp_slash( $ditl_contenu ) ) );

	if ( $ditl_contenu_filtre !== $ditl_contenu ) {
		WP_CLI::warning( sprintf(
			'Article %d : le HTML serait altere par les filtres d\'enregistrement (content_save_pre, kses probable). Article NON migre : relancer avec --user=<admin disposant de unfiltered_html>.',
			$ditl_post_id
		) );
		continue;
	}

	// Recapitulatif lisible des blocs extraits.
	$ditl_nb = array_count_values( wp_list_pluck( $ditl_data['blocs'], 'type' ) );

	WP_CLI::log( sprintf(
		'  Blocs extraits : %d image(s), %d texte(s), %d bouton(s)',
		isset( $ditl_nb['image'] ) ? $ditl_nb['image'] : 0,
		isset( $ditl_nb['texte'] ) ? $ditl_nb['texte'] : 0,
		isset( $ditl_nb['bouton'] ) ? $ditl_nb['bouton'] : 0
	) );

	foreach ( $ditl_data['blocs'] as $ditl_bloc ) {
		if ( 'image' === $ditl_bloc['type'] ) {
			WP_CLI::log( sprintf( '      image  : attachment %d, taille "%s"', $ditl_bloc['id'], $ditl_bloc['taille'] ) );
		} elseif ( 'texte' === $ditl_bloc['type'] ) {
			WP_CLI::log( sprintf( '      texte  : %d car.%s', strlen( $ditl_bloc['html'] ), 'justify' === $ditl_bloc['align'] ? ' (justifie)' : '' ) );
		} else {
			WP_CLI::log( sprintf( '      bouton : "%s" -> %s', $ditl_bloc['texte'], $ditl_bloc['url'] ) );
		}
	}

	WP_CLI::log( sprintf( '  post_content assemble : %d car., md5 %s', strlen( $ditl_contenu ), md5( $ditl_contenu ) ) );

	if ( $ditl_dry_run ) {
		WP_CLI::log( '  [dry-run] Rien n\'a ete ecrit pour cet article.' );
		continue;
	}

	// Sauvegarde du post_content d'origine : UNE SEULE FOIS, jamais ecrasee
	// au rejeu (le parametre unique d'add_post_meta refuse un doublon).
	// La sauvegarde conditionne la reversibilite : echec = article non migre.
	if ( ! $ditl_deja_migre ) {
		if ( ! add_post_meta( $ditl_post_id, '_ditl_backup_post_content', wp_slash( (string) $ditl_post->post_content ), true ) ) {
			WP_CLI::warning( sprintf( 'Article %d : echec de la sauvegarde _ditl_backup_post_content, article NON migre.', $ditl_post_id ) );
			continue;
		}
		WP_CLI::log( '  Sauvegarde : post_content d\'origine copie dans _ditl_backup_post_content.' );
	} else {
		WP_CLI::log( '  Sauvegarde : _ditl_backup_post_content deja presente, conservee telle quelle.' );
	}

	// Ecriture du post_content, uniquement s'il change (idempotence : au
	// rejeu, le meme HTML est regenere et aucune ecriture n'a lieu).
	if ( $ditl_post->post_content === $ditl_contenu ) {
		WP_CLI::log( '  post_content deja conforme, aucune reecriture.' );
	} else {
		$ditl_resultat = wp_update_post(
			array(
				'ID'           => $ditl_post_id,
				'post_content' => wp_slash( $ditl_contenu ),
			),
			true
		);

		if ( is_wp_error( $ditl_resultat ) ) {
			WP_CLI::warning( sprintf( 'Article %d : echec de wp_update_post (%s), article non migre.', $ditl_post_id, $ditl_resultat->get_error_message() ) );
			continue;
		}

		// Verification au byte pres : relecture en base apres ecriture.
		clean_post_cache( $ditl_post_id );
		$ditl_relu = get_post( $ditl_post_id );

		if ( ! $ditl_relu || $ditl_relu->post_content !== $ditl_contenu ) {
			WP_CLI::warning( sprintf(
				'Article %d : le post_content relu en base differe du HTML assemble (filtre a l\'enregistrement ?). A VERIFIER MANUELLEMENT.',
				$ditl_post_id
			) );
			continue;
		}

		WP_CLI::log( '  post_content ecrit et verifie au byte pres.' );
	}

	// Bascule de rendu : sans _elementor_edit_mode, WordPress rend
	// post_content classiquement (template single standard d'Astra).
	// _elementor_data et _elementor_template_type restent en place
	// (sauvegarde dormante, reversibilite documentee dans le docblock).
	if ( $ditl_edit_mode_presente ) {
		delete_post_meta( $ditl_post_id, '_elementor_edit_mode' );
		WP_CLI::log( '  Meta _elementor_edit_mode supprimee : rendu classique actif.' );
	} else {
		WP_CLI::log( '  Meta _elementor_edit_mode deja absente : rendu classique deja actif.' );
	}

	WP_CLI::success( sprintf( 'Article %d migre.', $ditl_post_id ) );
}

WP_CLI::log( '' );
WP_CLI::log( $ditl_dry_run ? 'Simulation terminee.' : 'Migration terminee.' );
