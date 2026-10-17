<?php
/**
 * Jeu de demonstration de la Reglementation (phase 6, decision D10).
 *
 * OBJET : donner a la recette et a la presentation au client une page
 * "Reglementation" remplie, fidele a la maquette V3 (p. 28 a 33), sans
 * attendre les textes definitifs de l'AFT. Le jeu montre les regles
 * d'affichage du gabarit : onglets, questions depliables, encadres
 * "Plus d'informations", listes, liens, et masquage des questions sans
 * reponse (France : 5 questions saisies, 3 affichees).
 *
 * CE QUE FAIT LE SCRIPT (operation de donnees, aucun fichier du theme
 * modifie) :
 * 1. cree - ou met a jour - la page FR "Réglementation" (slug reglementation,
 *    publiee, langue Polylang FR, modele de page "Reglementation", H1
 *    "Parcours vers l'inclusion : cadre national et ressources clés", lien
 *    de retour "Retourner à la page d'accueil" vers l'accueil FR en URL
 *    relative) ;
 * 2. cree - ou met a jour - 4 pays FR publies, ordre 1 a 4 : France
 *    (5 questions de la maquette, 3 remplies avec le texte EXACT de la
 *    maquette, les 2 dernieres sans reponse donc masquees), Irlande, Espagne
 *    et Italie (une question et un paragraphe "[Démonstration]") ;
 * 3. genere 4 drapeaux de DEMONSTRATION (PNG 128 x 128 par GD, tricolores
 *    simples sans armoiries) et les importe en mediatheque (texte
 *    alternatif vide : image decorative) ;
 * 4. reutilise pour les questions 1 a 3 de la France trois images paysage
 *    DEJA presentes en mediatheque (voir $ditl_demo_illustrations : aucune
 *    image creee pour cela, aucune modifiee) ;
 * 5. purge le cache de page (WP Fastest Cache) s'il y a eu une ecriture.
 * Tout ce qui est cree porte la meta marqueur _ditl_demo_reglementation
 * (valeur : page, pays-<cle>, drapeau-<cle>) et la meta
 * _ditl_demo_reglementation_id (son propre ID). Une COPIE d'un element du
 * jeu (extension Duplicate Page, qui recopie toutes les metas) porte le
 * marqueur mais pas son propre ID : elle n'est ni reprise ni supprimee par
 * le script, seulement signalee (le client a pu y saisir un vrai contenu).
 *
 * GARDE-FOUS :
 * - HOTES AUTORISES SEULEMENT : le local (ditlproject.test) et la preprod
 *   (preprod.ditlproject.eu) ; arret immediat sur tout autre hote,
 *   production comprise (une copie de la base de production servie sous un
 *   autre nom, pendant une repetition de bascule par exemple, est refusee
 *   aussi).
 * - AJOUT AUTOMATIQUE AUX MENUS NEUTRALISE : le menu principal FR est en
 *   "ajout automatique des nouvelles pages" (nav_menu_options, auto_add) ;
 *   le hook du coeur _wp_auto_add_pages_to_menu est retire pendant
 *   l'execution, puis un controle final signale tout element de menu qui
 *   viserait la page.
 * - COLLISION DE SLUG : si une page ou un media NON marque occupe deja le
 *   slug "reglementation" (corbeille comprise), la page n'est pas creee
 *   (les pays le sont quand meme) ; rien n'est jamais ecrit sur un contenu
 *   non marque.
 *
 * CE QUE LE SCRIPT NE TOUCHE PAS : les contenus non marques (pages,
 * medias, menus), les trois images d'illustration reutilisees (ni leur
 * fichier, ni leurs metadonnees), les reglages de Polylang et du theme.
 *
 * Script idempotent : un rejeu met a jour ce qui differe et n'ecrit rien si
 * tout est deja conforme (aucun doublon : chaque element est retrouve par
 * son marqueur).
 *
 * MODE ANNULER : SUPPRIME DEFINITIVEMENT (sans corbeille) les elements du
 * jeu (marqueur ET propre ID) : les pays, la page, les drapeaux PNG
 * (fichiers compris). Les copies marquees sont signalees et conservees. Les
 * images d'illustration reutilisees ne portent pas le marqueur et restent.
 *
 * ============================ RUNBOOK PREPROD ==================================
 * 1. Deployer le theme (inc/reglementation.php, inc/metaboxes/reglementation.php,
 *    page-templates/reglementation.php charges).
 * 2. Simulation :   wp eval-file wp-content/themes/ditl/cli/demo-reglementation.php dry-run
 *    Verifier : hote non production, gabarit present, images d'illustration
 *    trouvees, aucune collision de slug.
 * 3. Application :  wp eval-file wp-content/themes/ditl/cli/demo-reglementation.php
 * 4. Le cache de page est purge par le script (sinon : vider wp-content/cache/all).
 * 5. Recette : /fr/reglementation/ (onglets France, Irlande, Espagne, Italie ;
 *    France = 3 questions, encadres, listes, liens) ; en-tete FR INCHANGE
 *    (aucune entree ajoutee au menu principal) ; menu "Reglementation" du
 *    tableau de bord : 4 pays, colonne "Sections affichees" 3 / 5 pour la France.
 * 6. Un rejeu doit afficher "0 ecriture".
 * 7. Retrait du jeu (avant la saisie des vrais contenus) :
 *    wp eval-file wp-content/themes/ditl/cli/demo-reglementation.php annuler
 * ===============================================================================
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/demo-reglementation.php dry-run
 *   wp eval-file wp-content/themes/ditl/cli/demo-reglementation.php
 *   wp eval-file wp-content/themes/ditl/cli/demo-reglementation.php annuler
 *
 * Le mode simulation accepte "dry-run" ou "--dry-run", l'annulation
 * "annuler" ou "--annuler" (combinables).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Usage : wp eval-file <script> [dry-run] [annuler] - voir le docblock.
	// En acces web direct, reponse muette pour ne rien reveler du fichier.
	http_response_code( 404 );
	exit( 1 );
}

// Bibliotheque commune des scripts CLI du theme.
require_once __DIR__ . '/commun.php';

// ---------------------------------------------------------------------------
// Garde d'environnement : jeu de demonstration reserve au local et a la
// preprod (liste d'hotes AUTORISES : tout autre hote est refuse).
// ---------------------------------------------------------------------------

$ditl_hote = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

if ( ! in_array( $ditl_hote, array( 'ditlproject.test', 'preprod.ditlproject.eu' ), true ) ) {
	WP_CLI::error( sprintf( 'Hote "%s" hors local et preprod : jeu de demonstration refuse. Aucune ecriture.', $ditl_hote ) );
}

// ---------------------------------------------------------------------------
// Lecture des arguments.
// ---------------------------------------------------------------------------

$ditl_modes   = ditl_cli_lire_modes( $args );
$ditl_dry_run = $ditl_modes['dry_run'];
$ditl_annuler = $ditl_modes['annuler'];

if ( $ditl_dry_run ) {
	WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucune ecriture en base ===' );
}

WP_CLI::log( sprintf( 'Hote : %s (autorise).', $ditl_hote ) );

// Compteur d'ecritures effectives (idempotence : rejeu = zero ecriture).
$ditl_ecritures = 0;

// ---------------------------------------------------------------------------
// Reperes fixes du jeu.
// ---------------------------------------------------------------------------

// Meta marqueur posee sur tout ce que le script cree.
$ditl_marqueur = '_ditl_demo_reglementation';

// Langue du jeu.
$ditl_langue = 'fr';

// Page FR.
$ditl_demo_page = array(
	'titre'        => 'Réglementation',
	'slug'         => 'reglementation',
	'h1'           => 'Parcours vers l\'inclusion : cadre national et ressources clés',
	'retour_texte' => 'Retourner à la page d\'accueil',
);

/**
 * Images d'illustration reutilisees (questions 1 a 3 de la France) : images
 * paysage existantes de la mediatheque, choisies le 17/10/2026. L'ID est
 * celui de la base locale (copie de la production du 23/07/2026) ; si l'ID
 * ne correspond pas au fichier attendu sur un autre environnement,
 * l'attachement est recherche par son fichier.
 */
$ditl_demo_illustrations = array(
	1 => array(
		'id'      => 4445,
		'fichier' => '2025/01/Banner-20-01.jpg',
	),
	2 => array(
		'id'      => 4471,
		'fichier' => '2025/01/Banner-projet-DiTL-20-01.jpg',
	),
	3 => array(
		'id'      => 5183,
		'fichier' => '2025/05/15282319.jpg',
	),
);

// Encadre "Plus d'informations" de la maquette (format d'editeur D3).
$ditl_encadre = static function ( $url ) {
	return '<p class="ditl-regl-encadre">Plus d\'informations : <a href="' . $url . '">' . $url . '</a></p>';
};

// Lien en clair rendu cliquable.
$ditl_lien = static function ( $url ) {
	return '<a href="' . $url . '">' . $url . '</a>';
};

/**
 * Pays du jeu : cle => titre, ordre, drapeau (bandes et sens), sections.
 * Textes de la France : maquette V3 p. 28 et 33 (et son export texte), a
 * l'identique ; les listes a puces et les sauts de ligne suivent le rendu
 * de la p. 33.
 */
$ditl_demo_pays = array(
	'france'  => array(
		'titre'    => 'France',
		'ordre'    => 1,
		'drapeau'  => array(
			'sens'     => 'vertical',
			'couleurs' => array( '#0055A4', '#FFFFFF', '#EF4135' ),
			'parts'    => array( 1, 1, 1 ),
		),
		'sections' => array(
			array(
				'titre'   => 'Comment fonctionne l\'écosystème de l\'inclusion en France ?',
				'contenu' => implode(
					"\n",
					array(
						'<p>En France, le handicap est officiellement reconnu par le régime général de sécurité sociale ou par la Mutualité sociale agricole (MSA) pour les personnes travaillant dans le secteur agricole. Une personne est considérée comme handicapée lorsqu\'un accident ou une maladie, lié ou non au travail, réduit sa capacité de travail ou de gain d\'au moins deux tiers (66 %).</p>',
						'<p>Le principal statut pour les travailleurs handicapés est le RQTH (Reconnaissance de la Qualité de Travailleur Handicapé).<br />Elle est accordée par une commission multidisciplinaire au sein de la Maison départementale des personnes handicapées (MDPH), sur la base d\'une expertise médicale et de directives nationales.</p>',
						$ditl_encadre( 'https://www.handicap-mva.org/reconnaissance-handicap-mdph.html' ),
						'<p>La MDPH fonctionne indépendamment de la Sécurité sociale afin d\'éviter tout conflit d\'intérêts. Le même processus d\'évaluation s\'applique aux enfants.</p>',
					)
				),
			),
			array(
				'titre'   => 'Comment le handicap est-il évalué et à quels soutiens les apprenants en formation professionnelle peuvent-ils avoir accès ?',
				'contenu' => implode(
					"\n",
					array(
						'<p>Le service chargé d\'évaluer l\'aptitude à suivre une formation en transport et logistique est le PECF (Pré-Évaluation des Capacités à la Formation).<br />Il est disponible dans plusieurs régions et est fortement recommandé pour garantir des aménagements adaptés pendant la formation ou l\'emploi.</p>',
						$ditl_encadre( 'https://www.agefiph.fr/services/pre-evaluation-des-capacites-la-formation' ),
						'<p>Pour les personnes qui doivent passer le permis de conduire poids lourds, cette évaluation est incluse dans la procédure.</p>',
						$ditl_encadre( 'https://www.securite-routiere.gouv.fr/permis-et-situation-de-handicap/permis-groupe-lourd-pour-les-personnes-en-situation-de-handicap' ),
						'<p>Les centres de formation ayant besoin de camions spécialisés pour former leurs stagiaires peuvent solliciter l\'aide de l\'AGEFIPH afin d\'accéder aux « handitrucks » :<br /><a href="mailto:contact.handitruck@aftral.com">contact.handitruck@aftral.com</a></p>',
						'<p>Lorsqu’une partie de la formation se déroule en entreprise, les employeurs peuvent bénéficier d’une aide financière pour adapter l’environnement de travail du stagiaire.<br />Les apprentis titulaires du RQTH bénéficient également de subventions plus élevées. Deux organismes principaux gèrent des fonds dédiés aux aménagements du lieu de travail :</p>',
						'<ul>',
						'<li>Agefiph (secteur privé) : ' . $ditl_lien( 'https://www.agefiph.fr/' ) . '</li>',
						'<li>FIPHFP (secteur public) : ' . $ditl_lien( 'https://www.fiphfp.fr/' ) . '</li>',
						'</ul>',
						'<p>Le décret n° 2010-356 autorise des aménagements pour les examens, notamment : des adaptations physiques des locaux, du temps supplémentaire, une assistance humaine; l\'accès à ces aménagements nécessite une demande formelle accompagnée d\'un dossier médical. Les centres de formation peuvent également recevoir des financements de l\'Agefiph pour améliorer l\'accessibilité physique. Chaque centre doit désigner un référent handicap.</p>',
						'<p>Un soutien supplémentaire pour les étudiants en situation de handicap est disponible par l\'intermédiaire de diverses fondations : ' . $ditl_lien( 'https://www.monparcourshandicap.gouv.fr/' ) . '</p>',
					)
				),
			),
			array(
				'titre'   => 'Quelles sont les agences pour l\'emploi ou de placement spécialisées dans l\'inclusion des personnes handicapées ?',
				'contenu' => implode(
					"\n",
					array(
						'<p>Les agences Cap emploi sont spécialisées dans l\'accompagnement des demandeurs d\'emploi en situation de handicap et des employeurs ayant besoin d\'une expertise en matière de handicap : ' . $ditl_lien( 'https://www.capemploi.info/' ) . '</p>',
						'<p>Certaines organisations, telles que les ESAT, emploient exclusivement des travailleurs en situation de handicap et bénéficient de subventions spécifiques.</p>',
						'<p>Parmi les autres parcours d\'emploi inclusif, on peut citer :</p>',
						'<ul>',
						'<li>L\'emploi assisté</li>',
						'<li>Les entreprises adaptées, qui doivent employer au moins 55 % de travailleurs en situation de handicap et offrir des conditions de travail adaptées ainsi qu’un accompagnement professionnel.</li>',
						'</ul>',
						'<p>Des services individualisés supplémentaires, tels que le SAVS (Service d’Accompagnement à la Vie Sociale) ou le coaching professionnel spécialisé, offrent une aide personnalisée.</p>',
					)
				),
			),
			// Questions 4 et 5 : saisies SANS reponse, donc masquees sur le
			// site (demonstration de l'affichage conditionnel, regle D5).
			array(
				'titre'   => 'Quels organismes peuvent aider à adapter les lieux de travail pour les personnes en situation de handicap ? Quelles aides financières sont disponibles ?',
				'contenu' => '',
			),
			array(
				'titre'   => 'Si vous avez besoin de connaître la réglementation…',
				'contenu' => '',
			),
		),
	),
	'irlande' => array(
		'titre'    => 'Irlande',
		'ordre'    => 2,
		'drapeau'  => array(
			'sens'     => 'vertical',
			'couleurs' => array( '#169B62', '#FFFFFF', '#FF883E' ),
			'parts'    => array( 1, 1, 1 ),
		),
		'sections' => array(
			array(
				'titre'   => 'Comment fonctionne l\'écosystème de l\'inclusion en Irlande ?',
				'contenu' => '<p>[Démonstration] Le contenu de ce pays sera fourni par l\'AFT.</p>',
			),
		),
	),
	'espagne' => array(
		'titre'    => 'Espagne',
		'ordre'    => 3,
		'drapeau'  => array(
			'sens'     => 'horizontal',
			'couleurs' => array( '#AA151B', '#F1BF00', '#AA151B' ),
			'parts'    => array( 1, 2, 1 ),
		),
		'sections' => array(
			array(
				'titre'   => 'Comment fonctionne l\'écosystème de l\'inclusion en Espagne ?',
				'contenu' => '<p>[Démonstration] Le contenu de ce pays sera fourni par l\'AFT.</p>',
			),
		),
	),
	'italie'  => array(
		'titre'    => 'Italie',
		'ordre'    => 4,
		'drapeau'  => array(
			'sens'     => 'vertical',
			'couleurs' => array( '#009246', '#FFFFFF', '#CE2B37' ),
			'parts'    => array( 1, 1, 1 ),
		),
		'sections' => array(
			array(
				'titre'   => 'Comment fonctionne l\'écosystème de l\'inclusion en Italie ?',
				'contenu' => '<p>[Démonstration] Le contenu de ce pays sera fourni par l\'AFT.</p>',
			),
		),
	),
);

// ---------------------------------------------------------------------------
// Outils locaux.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'ditl_demo_regl_est_original' ) ) {
	/**
	 * Indique si un element marque a ete cree par le script lui-meme.
	 *
	 * Le script pose, a cote du marqueur, une meta "<marqueur>_id" egale a
	 * l'ID de l'element. Une copie faite dans l'administration (extension
	 * Duplicate Page, qui recopie toutes les metas) porte le marqueur mais
	 * l'ID de l'original : elle n'appartient pas au jeu (le client a pu y
	 * saisir un vrai contenu).
	 *
	 * @param int    $id       ID de l'element marque.
	 * @param string $marqueur Cle de la meta marqueur.
	 * @return bool True si l'element est un original du jeu.
	 */
	function ditl_demo_regl_est_original( $id, $marqueur ) {
		return (int) $id > 0 && (int) get_post_meta( (int) $id, $marqueur . '_id', true ) === (int) $id;
	}
}

if ( ! function_exists( 'ditl_demo_regl_signaler_copies' ) ) {
	/**
	 * Signale les copies marquees ignorees par le script.
	 *
	 * @param int[] $ids IDs des copies.
	 */
	function ditl_demo_regl_signaler_copies( $ids ) {
		if ( ! empty( $ids ) ) {
			WP_CLI::warning( sprintf( 'Copies d\'elements du jeu (marqueur sans propre ID, Duplicate Page probable) ignorees et conservees : %s.', implode( ', ', $ids ) ) );
		}
	}
}

if ( ! function_exists( 'ditl_demo_regl_trouver' ) ) {
	/**
	 * Retrouve l'element du jeu portant une valeur de marqueur.
	 *
	 * Toutes langues, tous statuts (corbeille comprise), types page, pays et
	 * media. Seuls les originaux du jeu sont retenus (voir
	 * ditl_demo_regl_est_original) ; les copies sont signalees.
	 *
	 * @param string $marqueur Cle de la meta marqueur.
	 * @param string $valeur   Valeur cherchee (page, pays-france...).
	 * @return int ID de l'element (le plus ancien si doublon), 0 si absent.
	 */
	function ditl_demo_regl_trouver( $marqueur, $valeur ) {
		$marques = get_posts(
			array(
				'post_type'        => array( 'page', DITL_REGL_POST_TYPE, 'attachment' ),
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'inherit' ),
				'meta_key'         => $marqueur, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $valeur, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'posts_per_page'   => 20,
				'suppress_filters' => true,
				'lang'             => '',
			)
		);

		$trouves = array();
		$copies  = array();

		foreach ( $marques as $id ) {
			if ( ditl_demo_regl_est_original( $id, $marqueur ) ) {
				$trouves[] = (int) $id;
			} else {
				$copies[] = (int) $id;
			}
		}

		ditl_demo_regl_signaler_copies( $copies );

		if ( count( $trouves ) > 1 ) {
			WP_CLI::warning( sprintf( 'Plusieurs elements marques "%s" (%s) : le plus ancien est retenu.', $valeur, implode( ', ', $trouves ) ) );
		}

		return empty( $trouves ) ? 0 : (int) $trouves[0];
	}
}

if ( ! function_exists( 'ditl_demo_regl_tous' ) ) {
	/**
	 * Liste les originaux du jeu (mode annuler) ; les copies marquees sont
	 * signalees et exclues (jamais supprimees).
	 *
	 * @param string $marqueur Cle de la meta marqueur.
	 * @return WP_Post[] Originaux du jeu, pays d'abord, puis page, puis medias.
	 */
	function ditl_demo_regl_tous( $marqueur ) {
		$marques = get_posts(
			array(
				'post_type'        => array( 'page', DITL_REGL_POST_TYPE, 'attachment' ),
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'inherit', 'auto-draft' ),
				'meta_key'         => $marqueur, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'posts_per_page'   => 100,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
				'lang'             => '',
			)
		);

		$elements = array();
		$copies   = array();

		foreach ( $marques as $element ) {
			if ( ditl_demo_regl_est_original( $element->ID, $marqueur ) ) {
				$elements[] = $element;
			} else {
				$copies[] = (int) $element->ID;
			}
		}

		ditl_demo_regl_signaler_copies( $copies );

		$rang = array(
			DITL_REGL_POST_TYPE => 0,
			'page'              => 1,
			'attachment'        => 2,
		);

		usort(
			$elements,
			/**
			 * Ordre de suppression : pays, page, medias.
			 *
			 * @param WP_Post $a Premier element.
			 * @param WP_Post $b Second element.
			 * @return int
			 */
			static function ( $a, $b ) use ( $rang ) {
				return $rang[ $a->post_type ] - $rang[ $b->post_type ];
			}
		);

		return $elements;
	}
}

if ( ! function_exists( 'ditl_demo_regl_collisions' ) ) {
	/**
	 * Contenus NON marques qui occupent deja le slug vise.
	 *
	 * Pages de premier niveau et medias (le coeur controle l'unicite d'un
	 * slug de page contre les deux), tous statuts, corbeille comprise.
	 *
	 * @param string $slug    Slug vise.
	 * @param int    $exclure ID de la page du jeu (0 si aucune).
	 * @return array Lignes { ID, post_type, post_status, post_name }.
	 */
	function ditl_demo_regl_collisions( $slug, $exclure ) {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ID, post_type, post_status, post_name FROM {$wpdb->posts}
				WHERE post_name IN ( %s, %s ) AND post_type IN ( 'page', 'attachment' ) AND post_parent = 0 AND ID != %d",
				$slug,
				$slug . '__trashed',
				$exclure
			),
			ARRAY_A
		);
	}
}

if ( ! function_exists( 'ditl_demo_regl_menus_visant' ) ) {
	/**
	 * Elements de menu qui visent une page.
	 *
	 * @param int $page_id ID de la page.
	 * @return int[] IDs des elements de menu.
	 */
	function ditl_demo_regl_menus_visant( $page_id ) {
		global $wpdb;

		return array_map(
			'intval',
			$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_menu_item_object_id'
					INNER JOIN {$wpdb->postmeta} o ON o.post_id = p.ID AND o.meta_key = '_menu_item_object' AND o.meta_value = 'page'
					WHERE p.post_type = 'nav_menu_item' AND m.meta_value = %s",
					(string) $page_id
				)
			)
		);
	}
}

if ( ! function_exists( 'ditl_demo_regl_illustration' ) ) {
	/**
	 * Resout une image d'illustration reutilisee : l'ID attendu s'il
	 * designe bien le fichier attendu, sinon l'attachement portant ce
	 * fichier, sinon 0 (avec avertissement).
	 *
	 * @param array $image { id, fichier }.
	 * @return int ID d'attachement image, ou 0.
	 */
	function ditl_demo_regl_illustration( $image ) {
		global $wpdb;

		$id = (int) $image['id'];

		ditl_cli_verifier_attachment( $id, 'image d\'illustration ' . $image['fichier'] );

		if ( $id > 0 && $image['fichier'] === get_post_meta( $id, '_wp_attached_file', true ) && ditl_reglementation_id_image( $id ) === $id ) {
			return $id;
		}

		$trouve = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s ORDER BY post_id ASC LIMIT 1",
				$image['fichier']
			)
		);

		if ( $trouve > 0 && ditl_reglementation_id_image( $trouve ) === $trouve ) {
			WP_CLI::log( sprintf( '  Illustration %s : ID %d (au lieu de %d).', $image['fichier'], $trouve, $id ) );
			return $trouve;
		}

		WP_CLI::warning( sprintf( 'Illustration %s introuvable : la question sera affichee sans image.', $image['fichier'] ) );

		return 0;
	}
}

if ( ! function_exists( 'ditl_demo_regl_png_drapeau' ) ) {
	/**
	 * Genere le PNG 128 x 128 d'un drapeau tricolore de demonstration.
	 *
	 * @param array  $drapeau { sens (vertical|horizontal), couleurs (hex), parts }.
	 * @param string $fichier Chemin du fichier PNG a ecrire.
	 * @return bool True si le fichier a ete ecrit.
	 */
	function ditl_demo_regl_png_drapeau( $drapeau, $fichier ) {
		$taille = 128;
		$image  = imagecreatetruecolor( $taille, $taille );

		if ( false === $image ) {
			return false;
		}

		$total = array_sum( $drapeau['parts'] );
		$debut = 0;
		$cumul = 0;

		foreach ( $drapeau['couleurs'] as $rang => $hex ) {
			$cumul += $drapeau['parts'][ $rang ];
			$fin    = (int) round( $cumul * $taille / $total ) - 1;
			$rgb    = sscanf( $hex, '#%02x%02x%02x' );
			$teinte = imagecolorallocate( $image, $rgb[0], $rgb[1], $rgb[2] );

			if ( 'horizontal' === $drapeau['sens'] ) {
				imagefilledrectangle( $image, 0, $debut, $taille - 1, $fin, $teinte );
			} else {
				imagefilledrectangle( $image, $debut, 0, $fin, $taille - 1, $teinte );
			}

			$debut = $fin + 1;
		}

		// imagedestroy() est sans effet depuis PHP 8.0 (et deprecie en 8.5) :
		// l'image est liberee avec la variable.
		return imagepng( $image, $fichier, 9 );
	}
}

// ---------------------------------------------------------------------------
// Dependances du theme (aucune ecriture).
// ---------------------------------------------------------------------------

if ( ! defined( 'DITL_REGL_POST_TYPE' ) || ! defined( 'DITL_TPL_REGLEMENTATION' ) || ! post_type_exists( DITL_REGL_POST_TYPE ) || ! function_exists( 'ditl_reglementation_sanitize_sections_json' ) ) {
	WP_CLI::error( 'Theme : module Reglementation absent ou inactif (inc/reglementation.php, inc/metaboxes/reglementation.php). Deployer le theme avant de jouer ce script. Aucune ecriture.' );
}

// ---------------------------------------------------------------------------
// MODE ANNULER : suppression definitive de tout ce qui est marque.
// ---------------------------------------------------------------------------

if ( $ditl_annuler ) {
	WP_CLI::log( '--- Annulation : suppression definitive des elements marques ---' );

	$ditl_elements = ditl_demo_regl_tous( $ditl_marqueur );

	if ( empty( $ditl_elements ) ) {
		WP_CLI::log( '  Aucun element marque en base, rien a faire.' );
	}

	foreach ( $ditl_elements as $ditl_element ) {
		$ditl_libelle = sprintf( '%s %d "%s" (%s)', $ditl_element->post_type, $ditl_element->ID, $ditl_element->post_title, get_post_meta( $ditl_element->ID, $ditl_marqueur, true ) );

		if ( $ditl_dry_run ) {
			WP_CLI::log( '  [dry-run] Serait supprime definitivement : ' . $ditl_libelle . '.' );
			continue;
		}

		// wp_delete_attachment supprime aussi les fichiers (original, tailles
		// et jumeaux WebP via inc/webp.php).
		$ditl_ok = 'attachment' === $ditl_element->post_type ? wp_delete_attachment( $ditl_element->ID, true ) : wp_delete_post( $ditl_element->ID, true );

		if ( $ditl_ok ) {
			WP_CLI::log( '  Supprime : ' . $ditl_libelle . '.' );
			$ditl_ecritures++;
		} else {
			WP_CLI::warning( 'Echec de la suppression : ' . $ditl_libelle . '.' );
		}
	}

	WP_CLI::log( '  Images d\'illustration reutilisees : non marquees, conservees.' );

	if ( $ditl_dry_run ) {
		WP_CLI::success( 'Simulation terminee : aucune ecriture.' );
		return;
	}

	if ( $ditl_ecritures > 0 && function_exists( 'wpfc_clear_all_cache' ) ) {
		wpfc_clear_all_cache();
		WP_CLI::log( '  Cache de page purge (WP Fastest Cache).' );
	}

	WP_CLI::success( sprintf( 'Annulation terminee : %d suppression(s).', $ditl_ecritures ) );
	return;
}

// ---------------------------------------------------------------------------
// Controles prealables (aucune ecriture).
// ---------------------------------------------------------------------------

$ditl_polylang = function_exists( 'pll_set_post_language' ) && function_exists( 'pll_get_post_language' ) && function_exists( 'pll_languages_list' );

if ( $ditl_polylang ) {
	if ( ! in_array( $ditl_langue, (array) pll_languages_list( array( 'fields' => 'slug' ) ), true ) ) {
		WP_CLI::error( sprintf( 'Langue Polylang "%s" absente du site. Aucune ecriture.', $ditl_langue ) );
	}

	WP_CLI::log( sprintf( 'Polylang : langue "%s" presente.', $ditl_langue ) );
} else {
	WP_CLI::warning( 'Polylang inactif : aucune langue ne sera posee (la page et les pays seront visibles quelle que soit la langue).' );
}

if ( file_exists( get_stylesheet_directory() . '/' . DITL_TPL_REGLEMENTATION ) ) {
	WP_CLI::log( sprintf( 'Gabarit : %s present.', DITL_TPL_REGLEMENTATION ) );
} else {
	WP_CLI::warning( sprintf( 'Gabarit %s absent du theme : le modele est pose sur la page, qui rendra le modele par defaut jusqu\'a son deploiement.', DITL_TPL_REGLEMENTATION ) );
}

if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagepng' ) ) {
	WP_CLI::warning( 'Extension GD absente : les drapeaux de demonstration ne seront pas generes (pays sans drapeau).' );
}

// Accueil FR (lien de retour), en URL relative portable.
$ditl_accueil = function_exists( 'pll_home_url' ) ? (string) pll_home_url( $ditl_langue ) : home_url( '/' );
$ditl_accueil = ditl_cli_url_relative( $ditl_accueil );
WP_CLI::log( sprintf( 'Lien de retour : "%s" -> %s', $ditl_demo_page['retour_texte'], $ditl_accueil ) );

// Images d'illustration reutilisees.
$ditl_images = array();

foreach ( $ditl_demo_illustrations as $ditl_rang => $ditl_image ) {
	$ditl_images[ $ditl_rang ] = ditl_demo_regl_illustration( $ditl_image );
	WP_CLI::log( sprintf( 'Illustration question %d : ID %d (%s).', $ditl_rang, $ditl_images[ $ditl_rang ], $ditl_image['fichier'] ) );
}

// Page : retrouvee par son marqueur, slug libre sinon.
$ditl_page_id   = ditl_demo_regl_trouver( $ditl_marqueur, 'page' );
$ditl_page_bloc = false;

foreach ( ditl_demo_regl_collisions( $ditl_demo_page['slug'], $ditl_page_id ) as $ditl_c ) {
	WP_CLI::warning( sprintf( 'Collision : le slug "%s" est deja pris par %s %d (statut %s, slug %s), contenu non marque.', $ditl_demo_page['slug'], $ditl_c['post_type'], $ditl_c['ID'], $ditl_c['post_status'], $ditl_c['post_name'] ) );
	$ditl_page_bloc = true;
}

if ( $ditl_page_bloc ) {
	WP_CLI::warning( 'Page de demonstration NON creee (slug occupe) ; les pays sont traites quand meme.' );
} else {
	WP_CLI::log( 'Slug "reglementation" : aucune collision (pages et medias, corbeille comprise).' );
}

// Ajout automatique aux menus "auto_add" (menu principal FR) neutralise :
// la publication de la page ne doit rien changer a l'en-tete.
remove_action( 'transition_post_status', '_wp_auto_add_pages_to_menu', 10 );

// Auteur : celui de la page d'accueil (comme les autres scripts de pages).
$ditl_auteur = (int) get_post_field( 'post_author', (int) get_option( 'page_on_front' ) );

/**
 * Pose la langue du jeu sur un element (Polylang), si elle differe.
 *
 * @param int    $id      ID de l'element.
 * @param string $langue  Code de langue.
 * @param bool   $dry_run Mode simulation.
 * @return int Nombre d'ecritures (0 ou 1).
 */
$ditl_poser_langue = static function ( $id, $langue, $dry_run ) use ( $ditl_polylang ) {
	if ( ! $ditl_polylang || $id <= 0 ) {
		return 0;
	}

	if ( $langue === pll_get_post_language( $id ) ) {
		return 0;
	}

	if ( $dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] Langue "%s" serait posee sur %d.', $langue, $id ) );
		return 0;
	}

	pll_set_post_language( $id, $langue );
	WP_CLI::log( sprintf( '  Langue "%s" posee sur %d.', $langue, $id ) );

	return 1;
};

/**
 * Met a jour une meta si sa valeur (nettoyee par son sanitize_callback)
 * differe de la valeur stockee.
 *
 * @param int    $id      ID de l'element.
 * @param string $cle     Cle de la meta.
 * @param string $valeur  Valeur attendue, deja nettoyee.
 * @param bool   $dry_run Mode simulation.
 * @return int Nombre d'ecritures (0 ou 1).
 */
$ditl_poser_meta = static function ( $id, $cle, $valeur, $dry_run ) {
	if ( $id > 0 && metadata_exists( 'post', $id, $cle ) && (string) get_post_meta( $id, $cle, true ) === (string) $valeur ) {
		return 0;
	}

	if ( $dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] Meta %s serait ecrite.', $cle ) );
		return 0;
	}

	update_post_meta( $id, $cle, wp_slash( $valeur ) );
	WP_CLI::log( sprintf( '  Meta %s ecrite.', $cle ) );

	return 1;
};

// ---------------------------------------------------------------------------
// Drapeaux de demonstration (PNG generes, importes en mediatheque).
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '--- Drapeaux de demonstration ---' );

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$ditl_drapeaux = array();

foreach ( $ditl_demo_pays as $ditl_cle => $ditl_pays ) {
	$ditl_valeur = 'drapeau-' . $ditl_cle;
	$ditl_titre  = sprintf( 'Drapeau %s (demonstration)', $ditl_pays['titre'] );
	$ditl_id     = ditl_demo_regl_trouver( $ditl_marqueur, $ditl_valeur );

	if ( $ditl_id > 0 && 'attachment' === get_post_type( $ditl_id ) && file_exists( (string) get_attached_file( $ditl_id ) ) ) {
		WP_CLI::log( sprintf( '  %s : media %d deja present.', $ditl_titre, $ditl_id ) );
		$ditl_ecritures += $ditl_poser_langue( $ditl_id, $ditl_langue, $ditl_dry_run );

		$ditl_drapeaux[ $ditl_cle ] = $ditl_id;
		continue;
	}

	if ( $ditl_id > 0 ) {
		// Element marque sans fichier : remplace par un media neuf.
		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] %s : media %d sans fichier, serait remplace.', $ditl_titre, $ditl_id ) );
		} else {
			wp_delete_attachment( $ditl_id, true );
			WP_CLI::log( sprintf( '  %s : media %d sans fichier supprime, recree ci-dessous.', $ditl_titre, $ditl_id ) );
			$ditl_ecritures++;
		}
	}

	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		$ditl_drapeaux[ $ditl_cle ] = 0;
		continue;
	}

	if ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] %s : PNG 128 x 128 serait genere et importe.', $ditl_titre ) );
		$ditl_drapeaux[ $ditl_cle ] = 0;
		continue;
	}

	$ditl_tmp = wp_tempnam( 'ditl-demo-drapeau-' . $ditl_cle . '.png' );

	if ( ! ditl_demo_regl_png_drapeau( $ditl_pays['drapeau'], $ditl_tmp ) ) {
		WP_CLI::warning( sprintf( '%s : generation du PNG impossible, pays sans drapeau.', $ditl_titre ) );
		wp_delete_file( $ditl_tmp );
		$ditl_drapeaux[ $ditl_cle ] = 0;
		continue;
	}

	$ditl_id = media_handle_sideload(
		array(
			'name'     => 'ditl-demo-drapeau-' . $ditl_cle . '.png',
			'tmp_name' => $ditl_tmp,
		),
		0,
		$ditl_titre
	);

	if ( is_wp_error( $ditl_id ) ) {
		WP_CLI::warning( sprintf( '%s : import impossible (%s), pays sans drapeau.', $ditl_titre, $ditl_id->get_error_message() ) );
		wp_delete_file( $ditl_tmp );
		$ditl_drapeaux[ $ditl_cle ] = 0;
		continue;
	}

	$ditl_id = (int) $ditl_id;

	update_post_meta( $ditl_id, $ditl_marqueur, $ditl_valeur );
	update_post_meta( $ditl_id, $ditl_marqueur . '_id', $ditl_id );
	delete_post_meta( $ditl_id, '_wp_attachment_image_alt' );
	$ditl_ecritures++;
	WP_CLI::log( sprintf( '  %s : media %d cree (texte alternatif vide, image decorative).', $ditl_titre, $ditl_id ) );

	$ditl_ecritures += $ditl_poser_langue( $ditl_id, $ditl_langue, false );

	$ditl_drapeaux[ $ditl_cle ] = $ditl_id;
}

// ---------------------------------------------------------------------------
// Pays.
// ---------------------------------------------------------------------------

$ditl_ids_pays = array();

foreach ( $ditl_demo_pays as $ditl_cle => $ditl_pays ) {
	WP_CLI::log( '' );
	WP_CLI::log( sprintf( '--- Pays "%s" (ordre %d) ---', $ditl_pays['titre'], $ditl_pays['ordre'] ) );

	// Sections au format du contrat, nettoyees comme a l'enregistrement.
	$ditl_sections = array();

	foreach ( $ditl_pays['sections'] as $ditl_rang => $ditl_section ) {
		$ditl_image_rang = $ditl_rang + 1;

		$ditl_sections[] = array(
			'titre'    => $ditl_section['titre'],
			'image_id' => 'france' === $ditl_cle && isset( $ditl_images[ $ditl_image_rang ] ) ? $ditl_images[ $ditl_image_rang ] : 0,
			'contenu'  => $ditl_section['contenu'],
		);
	}

	$ditl_json = ditl_reglementation_sanitize_sections_json( wp_json_encode( $ditl_sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

	if ( count( json_decode( $ditl_json, true ) ) !== count( $ditl_sections ) ) {
		WP_CLI::warning( 'Sections : une ligne a ete ecartee par le nettoyage, a verifier.' );
	}

	$ditl_valeur = 'pays-' . $ditl_cle;
	$ditl_id     = ditl_demo_regl_trouver( $ditl_marqueur, $ditl_valeur );

	if ( $ditl_id <= 0 ) {
		if ( $ditl_dry_run ) {
			WP_CLI::log( '  [dry-run] Pays absent : serait cree (publie), puis marque, mis en langue et rempli.' );
			continue;
		}

		$ditl_id = wp_insert_post(
			array(
				'post_type'      => DITL_REGL_POST_TYPE,
				'post_status'    => 'publish',
				'post_title'     => wp_slash( $ditl_pays['titre'] ),
				'menu_order'     => $ditl_pays['ordre'],
				'post_author'    => $ditl_auteur,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( is_wp_error( $ditl_id ) ) {
			WP_CLI::error( sprintf( 'Echec de la creation du pays %s : %s', $ditl_pays['titre'], $ditl_id->get_error_message() ) );
		}

		$ditl_id = (int) $ditl_id;
		update_post_meta( $ditl_id, $ditl_marqueur, $ditl_valeur );
		update_post_meta( $ditl_id, $ditl_marqueur . '_id', $ditl_id );
		$ditl_ecritures++;
		WP_CLI::log( sprintf( '  Pays cree : ID %d.', $ditl_id ) );
	} else {
		$ditl_post = get_post( $ditl_id );
		$ditl_maj  = array();

		if ( 'trash' === $ditl_post->post_status ) {
			if ( ! $ditl_dry_run ) {
				wp_untrash_post( $ditl_id );
			}
			WP_CLI::log( sprintf( '  %sPays %d sorti de la corbeille.', $ditl_dry_run ? '[dry-run] ' : '', $ditl_id ) );
			$ditl_ecritures += $ditl_dry_run ? 0 : 1;
		}

		if ( 'publish' !== $ditl_post->post_status ) {
			$ditl_maj['post_status'] = 'publish';
		}

		if ( $ditl_pays['titre'] !== $ditl_post->post_title ) {
			$ditl_maj['post_title'] = wp_slash( $ditl_pays['titre'] );
		}

		if ( (int) $ditl_pays['ordre'] !== (int) $ditl_post->menu_order ) {
			$ditl_maj['menu_order'] = $ditl_pays['ordre'];
		}

		if ( empty( $ditl_maj ) ) {
			WP_CLI::log( sprintf( '  Pays existant : ID %d, titre, ordre et statut conformes.', $ditl_id ) );
		} elseif ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] Pays %d : %s seraient mis a jour.', $ditl_id, implode( ', ', array_keys( $ditl_maj ) ) ) );
		} else {
			$ditl_maj['ID'] = $ditl_id;
			wp_update_post( $ditl_maj );
			WP_CLI::log( sprintf( '  Pays %d : %s mis a jour.', $ditl_id, implode( ', ', array_diff( array_keys( $ditl_maj ), array( 'ID' ) ) ) ) );
			$ditl_ecritures++;
		}
	}

	$ditl_ids_pays[ $ditl_cle ] = $ditl_id;

	$ditl_ecritures += $ditl_poser_langue( $ditl_id, $ditl_langue, $ditl_dry_run );
	$ditl_ecritures += $ditl_poser_meta( $ditl_id, '_ditl_regl_drapeau_id', (string) ( isset( $ditl_drapeaux[ $ditl_cle ] ) ? $ditl_drapeaux[ $ditl_cle ] : 0 ), $ditl_dry_run );
	$ditl_ecritures += $ditl_poser_meta( $ditl_id, '_ditl_regl_sections', $ditl_json, $ditl_dry_run );

	$ditl_affichables = ditl_reglementation_sections_affichables( json_decode( $ditl_json, true ) );
	WP_CLI::log( sprintf( '  Sections : %d saisie(s), %d affichee(s).', count( $ditl_sections ), count( $ditl_affichables ) ) );
}

// ---------------------------------------------------------------------------
// Page FR.
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( sprintf( '--- Page "%s" (slug %s) ---', $ditl_demo_page['titre'], $ditl_demo_page['slug'] ) );

$ditl_retour = ditl_reglementation_sanitize_retour_json(
	wp_json_encode(
		array(
			'texte' => $ditl_demo_page['retour_texte'],
			'url'   => $ditl_accueil,
		)
	)
);
$ditl_h1     = sanitize_text_field( $ditl_demo_page['h1'] );

if ( $ditl_page_bloc ) {
	WP_CLI::log( '  Ignoree (slug occupe, voir l\'avertissement ci-dessus).' );
} else {
	if ( $ditl_page_id <= 0 ) {
		if ( $ditl_dry_run ) {
			WP_CLI::log( '  [dry-run] Page absente : serait creee (publiee, gabarit Reglementation), puis marquee, mise en langue et remplie.' );
		} else {
			$ditl_page_id = wp_insert_post(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => wp_slash( $ditl_demo_page['titre'] ),
					'post_name'      => $ditl_demo_page['slug'],
					'post_content'   => '',
					'post_author'    => $ditl_auteur,
					'post_parent'    => 0,
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				),
				true
			);

			if ( is_wp_error( $ditl_page_id ) ) {
				WP_CLI::error( sprintf( 'Echec de la creation de la page : %s', $ditl_page_id->get_error_message() ) );
			}

			$ditl_page_id = (int) $ditl_page_id;
			update_post_meta( $ditl_page_id, $ditl_marqueur, 'page' );
			update_post_meta( $ditl_page_id, $ditl_marqueur . '_id', $ditl_page_id );
			$ditl_ecritures++;
			WP_CLI::log( sprintf( '  Page creee : ID %d.', $ditl_page_id ) );
		}
	} else {
		$ditl_post = get_post( $ditl_page_id );
		$ditl_maj  = array();

		if ( 'trash' === $ditl_post->post_status ) {
			if ( ! $ditl_dry_run ) {
				wp_untrash_post( $ditl_page_id );
			}
			WP_CLI::log( sprintf( '  %sPage %d sortie de la corbeille.', $ditl_dry_run ? '[dry-run] ' : '', $ditl_page_id ) );
			$ditl_ecritures += $ditl_dry_run ? 0 : 1;
		}

		if ( 'publish' !== $ditl_post->post_status ) {
			$ditl_maj['post_status'] = 'publish';
		}

		if ( $ditl_demo_page['titre'] !== $ditl_post->post_title ) {
			$ditl_maj['post_title'] = wp_slash( $ditl_demo_page['titre'] );
		}

		if ( $ditl_demo_page['slug'] !== $ditl_post->post_name ) {
			$ditl_maj['post_name'] = $ditl_demo_page['slug'];
		}

		if ( empty( $ditl_maj ) ) {
			WP_CLI::log( sprintf( '  Page existante : ID %d, titre, slug et statut conformes.', $ditl_page_id ) );
		} elseif ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] Page %d : %s seraient mis a jour.', $ditl_page_id, implode( ', ', array_keys( $ditl_maj ) ) ) );
		} else {
			$ditl_maj['ID'] = $ditl_page_id;
			wp_update_post( $ditl_maj );
			WP_CLI::log( sprintf( '  Page %d : %s mis a jour.', $ditl_page_id, implode( ', ', array_diff( array_keys( $ditl_maj ), array( 'ID' ) ) ) ) );
			$ditl_ecritures++;
		}
	}

	if ( $ditl_page_id > 0 ) {
		$ditl_ecritures += $ditl_poser_langue( $ditl_page_id, $ditl_langue, $ditl_dry_run );

		// Modele de page pose directement en meta : wp_insert_post refuse un
		// modele dont le fichier n'est pas encore deploye.
		$ditl_ecritures += $ditl_poser_meta( $ditl_page_id, '_wp_page_template', DITL_TPL_REGLEMENTATION, $ditl_dry_run );
		$ditl_ecritures += $ditl_poser_meta( $ditl_page_id, '_ditl_hero_title', $ditl_h1, $ditl_dry_run );
		$ditl_ecritures += $ditl_poser_meta( $ditl_page_id, '_ditl_regl_retour', $ditl_retour, $ditl_dry_run );

		$ditl_menus = ditl_demo_regl_menus_visant( $ditl_page_id );

		if ( empty( $ditl_menus ) ) {
			WP_CLI::log( '  Menus : aucun element de menu ne vise la page (en-tete inchange).' );
		} else {
			WP_CLI::warning( sprintf( 'Elements de menu visant la page %d : %s (a retirer si l\'en-tete ne doit pas changer).', $ditl_page_id, implode( ', ', $ditl_menus ) ) );
		}

		WP_CLI::log( sprintf( '  URL : %s', get_permalink( $ditl_page_id ) ) );
	}
}

// Ajout automatique aux menus retabli (au cas ou le script serait enchaine
// avec d'autres traitements dans le meme processus).
add_action( 'transition_post_status', '_wp_auto_add_pages_to_menu', 10, 3 );

// ---------------------------------------------------------------------------
// Bilan.
// ---------------------------------------------------------------------------

WP_CLI::log( '' );

foreach ( $ditl_ids_pays as $ditl_cle => $ditl_id ) {
	WP_CLI::log( sprintf( 'Pays %s : ID %d, drapeau %d.', $ditl_cle, $ditl_id, isset( $ditl_drapeaux[ $ditl_cle ] ) ? $ditl_drapeaux[ $ditl_cle ] : 0 ) );
}

if ( $ditl_dry_run ) {
	WP_CLI::success( 'Simulation terminee : aucune ecriture.' );
	return;
}

if ( $ditl_ecritures > 0 ) {
	if ( function_exists( 'wpfc_clear_all_cache' ) ) {
		wpfc_clear_all_cache();
		WP_CLI::log( 'Cache de page purge (WP Fastest Cache).' );
	} else {
		WP_CLI::log( 'WP Fastest Cache inactif : purger le cache de page a la main si besoin.' );
	}
}

WP_CLI::success( sprintf( 'Termine : %d ecriture(s).', $ditl_ecritures ) );
