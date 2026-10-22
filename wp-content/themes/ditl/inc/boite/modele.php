<?php
/**
 * Modele de donnees de la boite a outils : type de publication "fiche
 * outil" (ditl_ressource), taxonomies a niveaux et metas.
 *
 * Contrat (FIGE - le moteur, les gabarits et l'import des fiches s'appuient
 * dessus ; toute evolution se fait en ajout) :
 *
 * Type de publication ditl_ressource (public : la fiche detail est une page
 * du site ; editeur classique, show_in_rest false) :
 * - post_title   : titre de la fiche ;
 * - post_content : "Apport de la compensation" (HTML riche) ;
 * - image a la une : illustration, identique dans toutes les langues ;
 * - _ditl_image_alt            (string) texte alternatif de l'illustration
 *                              dans la langue de la fiche (vide = image
 *                              decorative, recommandation Access42) ;
 * - _ditl_aptitude_texte       (string) phrase libre affichee dans le bloc
 *                              "Informations" (texte simple) ;
 * - _ditl_descriptif_technique (string) HTML riche (wp_kses_post) ;
 * - _ditl_points_vigilance     (string) HTML riche (wp_kses_post) ;
 * - _ditl_fabricants           (string) JSON [{nom, url}] (100 maximum) ;
 * - _ditl_sources              (string) JSON [url, ...] (100 maximum).
 * Les metas techniques de l'import des fiches (phase 4) sont prefixees
 * _ditl_import_ et appartiennent a l'import.
 *
 * Taxonomies (hierarchiques pour l'interface a cases a cocher, non publiques :
 * aucune page d'archive, elles n'existent que dans la boite a outils) :
 * - ditl_metier            : niveau 1 = metier, niveau 2 = tache ;
 * - ditl_aptitude          : niveau 1 = difficulte, niveau 2 = empechement ;
 * - ditl_environnement     : liste simple, couleur par terme ;
 * - ditl_type_compensation : liste simple ;
 * - ditl_sous_type         : liste simple.
 * Metas de termes :
 * - _ditl_code    : code neutre, IDENTIQUE dans toutes les langues (cle des
 *                   URL du questionnaire, du suivi d'audience et de l'import),
 *                   unique dans toute la taxonomie ([a-z0-9_-]) ;
 * - _ditl_couleur : couleur hexadecimale #rrggbb (environnements) ;
 * - _ditl_ordre   : rang d'affichage dans les listes (entier, 0 par defaut).
 *
 * Regle de marquage d'une fiche (taxonomies a niveaux) : on coche le terme le
 * plus precis. Une fiche cochee sur un terme de niveau 1 SANS aucun de ses
 * enfants couvre toute la branche (ex. difficulte "Maladie invalidante" sans
 * empechement precis : la fiche vaut pour tous les empechements de cette
 * difficulte) ; cochee sur un parent ET un enfant, seul l'enfant compte.
 * La regle est appliquee par le moteur (inc/boite/moteur.php), les termes
 * coches ne sont jamais modifies automatiquement.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DITL_RESSOURCE_POST_TYPE', 'ditl_ressource' );

// Base d'URL des fiches (/fr/outils/lunettes-intelligentes/). PROVISOIRE :
// l'URL definitive est a faire valider (SEO) ; WPML permettra de la traduire.
define( 'DITL_RESSOURCE_SLUG', 'outils' );

// A incrementer a chaque changement des regles de reecriture de la boite a
// outils : declenche une seule regeneration des regles (voir plus bas).
define( 'DITL_BOITE_REECRITURE_VERSION', '1' );

/**
 * Registre des taxonomies de la boite a outils.
 *
 * @return array Tableau [taxonomie => {niveaux, couleur, libelles}].
 */
function ditl_boite_taxonomies() {
	return array(
		'ditl_metier'            => array(
			'niveaux'  => 2,
			'couleur'  => false,
			'pluriel'  => __( 'Métiers et tâches', 'ditl' ),
			'singulier' => __( 'Métier ou tâche', 'ditl' ),
			'aide'     => __( 'Premier niveau : les métiers. Second niveau : les tâches de chaque métier (choisir le métier comme parent).', 'ditl' ),
		),
		'ditl_aptitude'          => array(
			'niveaux'  => 2,
			'couleur'  => false,
			'pluriel'  => __( 'Difficultés et empêchements', 'ditl' ),
			'singulier' => __( 'Difficulté ou empêchement', 'ditl' ),
			'aide'     => __( 'Premier niveau : les difficultés d\'aptitude. Second niveau : les empêchements de chaque difficulté (choisir la difficulté comme parent).', 'ditl' ),
		),
		'ditl_environnement'     => array(
			'niveaux'  => 1,
			'couleur'  => true,
			'pluriel'  => __( 'Environnements', 'ditl' ),
			'singulier' => __( 'Environnement', 'ditl' ),
			'aide'     => __( 'Environnements de travail, utilisés comme filtre des résultats.', 'ditl' ),
		),
		'ditl_type_compensation' => array(
			'niveaux'  => 1,
			'couleur'  => false,
			'pluriel'  => __( 'Types de compensation', 'ditl' ),
			'singulier' => __( 'Type de compensation', 'ditl' ),
			'aide'     => '',
		),
		'ditl_sous_type'         => array(
			'niveaux'  => 1,
			'couleur'  => false,
			'pluriel'  => __( 'Sous-types', 'ditl' ),
			'singulier' => __( 'Sous-type', 'ditl' ),
			'aide'     => '',
		),
	);
}

/**
 * Indique si une taxonomie appartient a la boite a outils.
 *
 * @param mixed $taxonomie Nom de taxonomie.
 * @return bool True si c'est une taxonomie de la boite a outils.
 */
function ditl_boite_est_taxonomie( $taxonomie ) {
	return is_string( $taxonomie ) && array_key_exists( $taxonomie, ditl_boite_taxonomies() );
}

/**
 * Declare le type de publication et les taxonomies.
 */
function ditl_boite_enregistrer_modele() {
	register_post_type(
		DITL_RESSOURCE_POST_TYPE,
		array(
			'labels'              => array(
				'name'                  => __( 'Fiches outils', 'ditl' ),
				'singular_name'         => __( 'Fiche outil', 'ditl' ),
				'menu_name'             => __( 'Boîte à outils', 'ditl' ),
				'all_items'             => __( 'Toutes les fiches', 'ditl' ),
				'add_new'               => __( 'Ajouter une fiche', 'ditl' ),
				'add_new_item'          => __( 'Ajouter une fiche outil', 'ditl' ),
				'edit_item'             => __( 'Modifier la fiche outil', 'ditl' ),
				'new_item'              => __( 'Nouvelle fiche outil', 'ditl' ),
				'view_item'             => __( 'Voir la fiche', 'ditl' ),
				'view_items'            => __( 'Voir les fiches', 'ditl' ),
				'search_items'          => __( 'Rechercher une fiche', 'ditl' ),
				'not_found'             => __( 'Aucune fiche trouvée.', 'ditl' ),
				'not_found_in_trash'    => __( 'Aucune fiche dans la corbeille.', 'ditl' ),
				'featured_image'        => __( 'Illustration de la fiche', 'ditl' ),
				'set_featured_image'    => __( 'Choisir l\'illustration', 'ditl' ),
				'remove_featured_image' => __( 'Retirer l\'illustration', 'ditl' ),
				'use_featured_image'    => __( 'Utiliser comme illustration', 'ditl' ),
				'item_published'        => __( 'Fiche publiée.', 'ditl' ),
				'item_updated'          => __( 'Fiche mise à jour.', 'ditl' ),
			),
			'public'              => true,
			'exclude_from_search' => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-portfolio',
			'supports'            => array( 'title', 'editor', 'thumbnail', 'revisions' ),
			'rewrite'             => array(
				'slug'       => DITL_RESSOURCE_SLUG,
				'with_front' => false,
			),
		)
	);

	foreach ( ditl_boite_taxonomies() as $taxonomie => $definition ) {
		register_taxonomy(
			$taxonomie,
			DITL_RESSOURCE_POST_TYPE,
			array(
				'labels'             => array(
					'name'              => $definition['pluriel'],
					'singular_name'     => $definition['singulier'],
					'menu_name'         => $definition['pluriel'],
					/* translators: %s : nom de la liste (ex. Environnements). */
					'all_items'         => sprintf( __( 'Tous : %s', 'ditl' ), $definition['pluriel'] ),
					'edit_item'         => __( 'Modifier', 'ditl' ),
					'update_item'       => __( 'Mettre à jour', 'ditl' ),
					'add_new_item'      => __( 'Ajouter', 'ditl' ),
					'new_item_name'     => __( 'Libellé', 'ditl' ),
					'parent_item'       => __( 'Parent (niveau 1)', 'ditl' ),
					'parent_item_colon' => __( 'Parent (niveau 1) :', 'ditl' ),
					'search_items'      => __( 'Rechercher', 'ditl' ),
					'not_found'         => __( 'Aucun élément.', 'ditl' ),
					'back_to_items'     => __( 'Retour à la liste', 'ditl' ),
				),
				'description'        => $definition['aide'],
				'hierarchical'       => true,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => false,
				'show_tagcloud'      => false,
				'show_in_quick_edit' => true,
				'show_admin_column'  => in_array( $taxonomie, array( 'ditl_environnement', 'ditl_type_compensation' ), true ),
				'show_in_rest'       => false,
				'rewrite'            => false,
				'query_var'          => false,
			)
		);
	}
}
add_action( 'init', 'ditl_boite_enregistrer_modele' );

/**
 * Regenere une seule fois les regles de reecriture apres l'ajout du type de
 * publication (ou un changement de sa base d'URL).
 *
 * Regeneration "douce" (sans reecriture du .htaccess, gere par ailleurs par
 * WordPress et WP Fastest Cache). Rejouee automatiquement sur chaque
 * environnement au premier chargement qui suit le deploiement.
 */
function ditl_boite_regenerer_reecriture() {
	if ( DITL_BOITE_REECRITURE_VERSION === get_option( 'ditl_boite_reecriture_version' ) ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'ditl_boite_reecriture_version', DITL_BOITE_REECRITURE_VERSION, true );
}
add_action( 'init', 'ditl_boite_regenerer_reecriture', 99 );

/**
 * Rend le type de publication traduisible sous Polylang (sans reglage en base).
 *
 * @param array $types Types de publication traduisibles.
 * @return array Types completes.
 */
function ditl_boite_polylang_types( $types ) {
	$types[ DITL_RESSOURCE_POST_TYPE ] = DITL_RESSOURCE_POST_TYPE;

	return $types;
}
add_filter( 'pll_get_post_types', 'ditl_boite_polylang_types' );

/**
 * Rend les taxonomies traduisibles sous Polylang (sans reglage en base).
 *
 * @param array $taxonomies Taxonomies traduisibles.
 * @return array Taxonomies completees.
 */
function ditl_boite_polylang_taxonomies( $taxonomies ) {
	foreach ( array_keys( ditl_boite_taxonomies() ) as $taxonomie ) {
		$taxonomies[ $taxonomie ] = $taxonomie;
	}

	return $taxonomies;
}
add_filter( 'pll_get_taxonomies', 'ditl_boite_polylang_taxonomies' );

/**
 * Copie les metas neutres d'un terme vers une nouvelle traduction (Polylang),
 * sans synchronisation ulterieure.
 *
 * Le code, la couleur et le rang sont identiques dans toutes les langues :
 * Polylang les recopie a la creation d'une traduction depuis l'admin. La
 * synchronisation est volontairement coupee : a chaque enregistrement d'un
 * terme, elle effacerait chez ses traductions toute meta absente du terme
 * enregistre (un terme sans code viderait le code de tout son groupe).
 * L'alignement entre langues est assure par l'admin de la boite
 * (ditl_boite_termes_propager) et par l'import. Equivalent WPML :
 * wpml-config.xml (action "copy-once", lue aussi par Polylang). Priorite 30 :
 * apres le module wpml-config de Polylang (20).
 *
 * @param array $metas Cles de metas a copier.
 * @param bool  $sync  True pour une synchronisation, false pour une copie.
 * @return array Cles filtrees.
 */
function ditl_boite_polylang_metas_termes( $metas, $sync = false ) {
	$cles = array( '_ditl_code', '_ditl_couleur', '_ditl_ordre' );

	if ( $sync ) {
		return array_values( array_diff( (array) $metas, $cles ) );
	}

	return array_values( array_unique( array_merge( (array) $metas, $cles ) ) );
}
add_filter( 'pll_copy_term_metas', 'ditl_boite_polylang_metas_termes', 30, 2 );

/**
 * Protege les fabricants et les sources d'une fiche lors de la
 * synchronisation Polylang entre traductions.
 *
 * Ces deux metas sont identiques dans toutes les langues (action "copy" de
 * wpml-config.xml, que Polylang lit aussi) : Polylang les synchronise a
 * chaque enregistrement. Mais une fiche qui n'a pas encore la meta en base
 * (fiche creee sans fabricant ni source) la ferait supprimer chez toutes ses
 * traductions. La cle est donc retiree de la synchronisation quand la fiche
 * enregistree ne la porte pas ; la synchronisation reste normale sinon.
 * Priorite 30 : apres le module wpml-config de Polylang (20).
 *
 * @param array $metas Cles de metas que Polylang s'apprete a copier.
 * @param bool  $sync  True pour une synchronisation, false pour une copie.
 * @param int   $from  ID de la publication source.
 * @return array Cles filtrees.
 */
function ditl_boite_polylang_metas_fiches( $metas, $sync = false, $from = 0 ) {
	if ( ! $sync || DITL_RESSOURCE_POST_TYPE !== get_post_type( $from ) ) {
		return $metas;
	}

	$absentes = array();

	foreach ( array( '_ditl_fabricants', '_ditl_sources' ) as $cle ) {
		if ( ! metadata_exists( 'post', (int) $from, $cle ) ) {
			$absentes[] = $cle;
		}
	}

	return array() === $absentes ? $metas : array_values( array_diff( (array) $metas, $absentes ) );
}
add_filter( 'pll_copy_post_metas', 'ditl_boite_polylang_metas_fiches', 30, 3 );

/**
 * Nettoie un code neutre de terme.
 *
 * @param mixed $value Code candidat.
 * @return string Code en minuscules [a-z0-9_-], 100 caracteres maximum.
 */
function ditl_boite_sanitize_code( $value ) {
	return is_scalar( $value ) ? substr( sanitize_key( (string) $value ), 0, 100 ) : '';
}

/**
 * Nettoie une couleur hexadecimale.
 *
 * @param mixed $value Couleur candidate.
 * @return string Couleur #rrggbb en minuscules, ou chaine vide.
 */
function ditl_boite_sanitize_couleur( $value ) {
	$couleur = is_scalar( $value ) ? sanitize_hex_color( trim( (string) $value ) ) : '';

	if ( ! is_string( $couleur ) || '' === $couleur ) {
		return '';
	}

	// Forme courte #abc developpee en #aabbcc (comparaisons et contrastes).
	if ( 4 === strlen( $couleur ) ) {
		$couleur = '#' . $couleur[1] . $couleur[1] . $couleur[2] . $couleur[2] . $couleur[3] . $couleur[3];
	}

	return strtolower( $couleur );
}

/**
 * Nettoie un rang d'affichage.
 *
 * @param mixed $value Rang candidat.
 * @return int Entier entre -9999 et 9999.
 */
function ditl_boite_sanitize_ordre( $value ) {
	$ordre = is_scalar( $value ) && is_numeric( $value ) ? (int) $value : 0;

	return max( -9999, min( 9999, $ordre ) );
}

/**
 * Nettoie la liste des fabricants [{nom, url}] encodee en JSON.
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (tableau vide si invalide).
 */
function ditl_boite_sanitize_fabricants_json( $value ) {
	$fabricants = is_scalar( $value ) ? json_decode( (string) $value, true ) : null;

	if ( ! is_array( $fabricants ) ) {
		return '[]';
	}

	$propres = array();

	foreach ( array_slice( $fabricants, 0, 100 ) as $fabricant ) {
		if ( ! is_array( $fabricant ) ) {
			continue;
		}

		// Rejet des valeurs non scalaires (JSON inattendu) avant tout cast.
		$nom = isset( $fabricant['nom'] ) && is_scalar( $fabricant['nom'] ) ? sanitize_text_field( (string) $fabricant['nom'] ) : '';
		$url = isset( $fabricant['url'] ) && is_scalar( $fabricant['url'] ) ? esc_url_raw( trim( (string) $fabricant['url'] ), array( 'http', 'https' ) ) : '';

		if ( '' === $nom && '' === $url ) {
			continue;
		}

		$propres[] = array(
			'nom' => $nom,
			'url' => $url,
		);
	}

	return (string) wp_json_encode( $propres, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Nettoie la liste des sources [url, ...] encodee en JSON.
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (tableau vide si invalide).
 */
function ditl_boite_sanitize_sources_json( $value ) {
	$sources = is_scalar( $value ) ? json_decode( (string) $value, true ) : null;

	if ( ! is_array( $sources ) ) {
		return '[]';
	}

	$propres = array();

	foreach ( array_slice( $sources, 0, 100 ) as $source ) {
		$url = is_scalar( $source ) ? esc_url_raw( trim( (string) $source ), array( 'http', 'https' ) ) : '';

		if ( '' !== $url ) {
			$propres[] = $url;
		}
	}

	return (string) wp_json_encode( array_values( array_unique( $propres ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Callback d'autorisation des metas de fiche.
 *
 * @param bool   $allowed  Autorisation courante.
 * @param string $meta_key Cle de la meta.
 * @param int    $post_id  ID de la fiche.
 * @param int    $user_id  ID de l'utilisateur.
 * @return bool True si l'utilisateur peut modifier la fiche.
 */
function ditl_boite_meta_auth_callback( $allowed, $meta_key, $post_id, $user_id ) {
	return user_can( $user_id, 'edit_post', $post_id );
}

/**
 * Callback d'autorisation des metas de terme.
 *
 * @param bool   $allowed  Autorisation courante.
 * @param string $meta_key Cle de la meta.
 * @param int    $term_id  ID du terme.
 * @param int    $user_id  ID de l'utilisateur.
 * @return bool True si l'utilisateur peut modifier le terme.
 */
function ditl_boite_meta_terme_auth_callback( $allowed, $meta_key, $term_id, $user_id ) {
	return user_can( $user_id, 'edit_term', $term_id );
}

/**
 * Declare les metas des fiches et des termes (protegees, nettoyees a toute
 * ecriture, y compris par update_post_meta / update_term_meta).
 */
function ditl_boite_enregistrer_metas() {
	$metas_fiche = array(
		'_ditl_image_alt'            => 'sanitize_text_field',
		'_ditl_aptitude_texte'       => 'sanitize_textarea_field',
		'_ditl_descriptif_technique' => 'wp_kses_post',
		'_ditl_points_vigilance'     => 'wp_kses_post',
		'_ditl_fabricants'           => 'ditl_boite_sanitize_fabricants_json',
		'_ditl_sources'              => 'ditl_boite_sanitize_sources_json',
	);

	foreach ( $metas_fiche as $cle => $nettoyage ) {
		register_post_meta(
			DITL_RESSOURCE_POST_TYPE,
			$cle,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => in_array( $cle, array( '_ditl_fabricants', '_ditl_sources' ), true ) ? '[]' : '',
				'sanitize_callback' => $nettoyage,
				'auth_callback'     => 'ditl_boite_meta_auth_callback',
				'show_in_rest'      => false,
			)
		);
	}

	foreach ( ditl_boite_taxonomies() as $taxonomie => $definition ) {
		register_term_meta(
			$taxonomie,
			'_ditl_code',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'ditl_boite_sanitize_code',
				'auth_callback'     => 'ditl_boite_meta_terme_auth_callback',
				'show_in_rest'      => false,
			)
		);

		register_term_meta(
			$taxonomie,
			'_ditl_ordre',
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'ditl_boite_sanitize_ordre',
				'auth_callback'     => 'ditl_boite_meta_terme_auth_callback',
				'show_in_rest'      => false,
			)
		);

		if ( $definition['couleur'] ) {
			register_term_meta(
				$taxonomie,
				'_ditl_couleur',
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => 'ditl_boite_sanitize_couleur',
					'auth_callback'     => 'ditl_boite_meta_terme_auth_callback',
					'show_in_rest'      => false,
				)
			);
		}
	}
}
add_action( 'init', 'ditl_boite_enregistrer_metas' );

/**
 * Code neutre d'un terme.
 *
 * @param WP_Term|int $terme Terme ou ID de terme.
 * @return string Code (chaine vide si absent).
 */
function ditl_boite_code_terme( $terme ) {
	$term_id = $terme instanceof WP_Term ? (int) $terme->term_id : absint( $terme );

	return $term_id > 0 ? (string) get_term_meta( $term_id, '_ditl_code', true ) : '';
}

/**
 * Retrouve un terme par son code neutre, dans une langue.
 *
 * @param string      $taxonomie Taxonomie de la boite a outils.
 * @param string      $code      Code neutre.
 * @param string|null $lang      Code de langue (null = langue courante).
 * @return WP_Term|null Terme trouve, ou null.
 */
function ditl_boite_terme_par_code( $taxonomie, $code, $lang = null ) {
	$code = ditl_boite_sanitize_code( $code );

	if ( '' === $code || ! ditl_boite_est_taxonomie( $taxonomie ) ) {
		return null;
	}

	$lang = null === $lang ? ditl_ml_langue_courante() : (string) $lang;
	$args = array(
		'taxonomy'   => $taxonomie,
		'hide_empty' => false,
		'number'     => 1,
		'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- referentiel de quelques dizaines de termes.
			array(
				'key'   => '_ditl_code',
				'value' => $code,
			),
		),
	);

	if ( 'polylang' === ditl_ml_extension() && '' !== $lang ) {
		$args['lang'] = $lang;
	}

	$termes = ditl_ml_avec_langue(
		$lang,
		static function () use ( $args ) {
			return get_terms( $args );
		}
	);

	return is_array( $termes ) && isset( $termes[0] ) && $termes[0] instanceof WP_Term ? $termes[0] : null;
}

/**
 * Attribue un code aux termes qui n'en ont pas (creation depuis l'admin).
 *
 * Le code d'une traduction reprend celui de l'original (Polylang le recopie
 * deja via pll_copy_term_metas ; ce filet couvre l'ordre d'execution des
 * hooks). A defaut, le code est deduit du slug (tirets remplaces par des
 * soulignes, convention des referentiels). Execute en fin de chaine, apres
 * l'enregistrement des liens de traduction.
 *
 * @param int    $term_id   ID du terme.
 * @param int    $tt_id     ID term_taxonomy.
 * @param string $taxonomie Taxonomie.
 */
function ditl_boite_completer_code_terme( $term_id, $tt_id, $taxonomie ) {
	if ( ! ditl_boite_est_taxonomie( $taxonomie ) || '' !== ditl_boite_code_terme( $term_id ) ) {
		return;
	}

	$code = '';

	foreach ( ditl_ml_traductions_terme( $term_id ) as $traduction_id ) {
		if ( (int) $traduction_id !== (int) $term_id ) {
			$code = ditl_boite_code_terme( $traduction_id );

			if ( '' !== $code ) {
				break;
			}
		}
	}

	if ( '' === $code ) {
		$terme = get_term( $term_id, $taxonomie );
		$code  = $terme instanceof WP_Term ? ditl_boite_sanitize_code( str_replace( '-', '_', $terme->slug ) ) : '';
	}

	if ( '' !== $code ) {
		update_term_meta( $term_id, '_ditl_code', $code );
	}
}
add_action( 'saved_term', 'ditl_boite_completer_code_terme', 999, 3 );
