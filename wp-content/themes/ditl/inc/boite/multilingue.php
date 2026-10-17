<?php
/**
 * Adaptateur multilingue de la boite a outils.
 *
 * Le site tourne aujourd'hui sous Polylang ; la migration vers WPML est
 * prevue en phase 2. Tout le code de la boite a outils (modele, moteur,
 * import des fiches) passe par ces fonctions plutot que par l'API d'une
 * extension : le jour de la migration, seule cette couche change.
 *
 * Branche Polylang : utilisee et verifiee en local.
 * Branche WPML : ecrite d'apres l'API publique documentee (hooks wpml_*),
 * A RECETTER en phase 2 une fois l'extension installee.
 * Sans extension multilingue : une seule langue, deduite de la locale.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extension multilingue active.
 *
 * @return string "polylang", "wpml" ou "" (aucune).
 */
function ditl_ml_extension() {
	if ( function_exists( 'pll_languages_list' ) ) {
		return 'polylang';
	}

	if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
		return 'wpml';
	}

	return '';
}

/**
 * Codes des langues du site (ex. array( 'fr', 'en', 'pt', 'es', 'de' )).
 *
 * @return string[] Codes de langue a deux lettres.
 */
function ditl_ml_langues() {
	$extension = ditl_ml_extension();

	if ( 'polylang' === $extension ) {
		$langues = pll_languages_list( array( 'fields' => 'slug' ) );

		return is_array( $langues ) ? array_values( array_map( 'strval', $langues ) ) : array();
	}

	if ( 'wpml' === $extension ) {
		$langues = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );

		return is_array( $langues ) ? array_values( array_map( 'strval', array_keys( $langues ) ) ) : array();
	}

	return array( substr( (string) get_locale(), 0, 2 ) );
}

/**
 * Langue de la page en cours de rendu.
 *
 * @return string Code de langue (chaine vide si indeterminee).
 */
function ditl_ml_langue_courante() {
	$extension = ditl_ml_extension();

	if ( 'polylang' === $extension ) {
		return (string) pll_current_language( 'slug' );
	}

	if ( 'wpml' === $extension ) {
		return (string) apply_filters( 'wpml_current_language', null );
	}

	return substr( (string) get_locale(), 0, 2 );
}

/**
 * Langue par defaut du site.
 *
 * @return string Code de langue.
 */
function ditl_ml_langue_defaut() {
	$extension = ditl_ml_extension();

	if ( 'polylang' === $extension ) {
		return (string) pll_default_language( 'slug' );
	}

	if ( 'wpml' === $extension ) {
		return (string) apply_filters( 'wpml_default_language', null );
	}

	return substr( (string) get_locale(), 0, 2 );
}

/**
 * Indique si un code de langue fait partie des langues du site.
 *
 * @param mixed $lang Code candidat.
 * @return bool True si la langue existe.
 */
function ditl_ml_langue_valide( $lang ) {
	return is_string( $lang ) && '' !== $lang && in_array( $lang, ditl_ml_langues(), true );
}

/**
 * Execute un traitement dans le contexte d'une langue donnee.
 *
 * Sous WPML, les requetes (get_terms, WP_Query) sont filtrees par la langue
 * courante : on bascule le temps du traitement. Sous Polylang, les fonctions
 * appelantes passent l'argument "lang" aux requetes : la bascule est sans
 * objet.
 *
 * @param string   $lang       Code de langue.
 * @param callable $traitement Traitement a executer.
 * @return mixed Valeur retournee par le traitement.
 */
function ditl_ml_avec_langue( $lang, $traitement ) {
	if ( 'wpml' !== ditl_ml_extension() ) {
		return call_user_func( $traitement );
	}

	$precedente = apply_filters( 'wpml_current_language', null );
	do_action( 'wpml_switch_language', $lang );

	try {
		return call_user_func( $traitement );
	} finally {
		do_action( 'wpml_switch_language', $precedente );
	}
}

/**
 * Langue d'une publication.
 *
 * @param int $post_id ID de la publication.
 * @return string Code de langue (chaine vide si aucune).
 */
function ditl_ml_langue_post( $post_id ) {
	$post_id   = absint( $post_id );
	$extension = ditl_ml_extension();

	if ( 'polylang' === $extension ) {
		return (string) pll_get_post_language( $post_id, 'slug' );
	}

	if ( 'wpml' === $extension ) {
		$details = apply_filters( 'wpml_post_language_details', null, $post_id );

		return is_array( $details ) && isset( $details['language_code'] ) ? (string) $details['language_code'] : '';
	}

	return ditl_ml_langue_defaut();
}

/**
 * Affecte une langue a une publication.
 *
 * @param int    $post_id ID de la publication.
 * @param string $lang    Code de langue.
 * @return bool True si la langue a pu etre affectee.
 */
function ditl_ml_definir_langue_post( $post_id, $lang ) {
	$post_id = absint( $post_id );

	if ( $post_id <= 0 || ! ditl_ml_langue_valide( $lang ) ) {
		return false;
	}

	$extension = ditl_ml_extension();

	if ( 'polylang' === $extension ) {
		pll_set_post_language( $post_id, $lang );

		return true;
	}

	if ( 'wpml' === $extension ) {
		do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'    => $post_id,
				'element_type'  => apply_filters( 'wpml_element_type', get_post_type( $post_id ) ),
				'trid'          => false,
				'language_code' => $lang,
			)
		);

		return true;
	}

	return false;
}

/**
 * Traductions d'une publication.
 *
 * @param int $post_id ID de la publication.
 * @return int[] Tableau [code de langue => ID], publication comprise.
 */
function ditl_ml_traductions_post( $post_id ) {
	$post_id   = absint( $post_id );
	$extension = ditl_ml_extension();

	if ( 'polylang' === $extension ) {
		return array_map( 'absint', (array) pll_get_post_translations( $post_id ) );
	}

	if ( 'wpml' === $extension ) {
		$type         = apply_filters( 'wpml_element_type', get_post_type( $post_id ) );
		$trid         = apply_filters( 'wpml_element_trid', null, $post_id, $type );
		$traductions  = $trid ? apply_filters( 'wpml_get_element_translations', null, $trid, $type ) : array();
		$correspondance = array();

		foreach ( (array) $traductions as $code => $traduction ) {
			if ( is_object( $traduction ) && ! empty( $traduction->element_id ) ) {
				$correspondance[ (string) $code ] = absint( $traduction->element_id );
			}
		}

		return $correspondance;
	}

	return array( ditl_ml_langue_defaut() => $post_id );
}

/**
 * Relie des publications comme traductions les unes des autres.
 *
 * Chaque publication doit deja avoir sa langue (ditl_ml_definir_langue_post).
 *
 * @param int[] $correspondance Tableau [code de langue => ID de publication].
 * @return bool True si le lien a pu etre enregistre.
 */
function ditl_ml_lier_traductions_posts( $correspondance ) {
	$correspondance = ditl_ml_nettoyer_correspondance( $correspondance );

	if ( count( $correspondance ) < 2 ) {
		return false;
	}

	$extension = ditl_ml_extension();

	if ( 'polylang' === $extension ) {
		pll_save_post_translations( $correspondance );

		return true;
	}

	if ( 'wpml' === $extension ) {
		return ditl_ml_wpml_lier( $correspondance, 'post' );
	}

	return false;
}

/**
 * Langue d'un terme.
 *
 * @param int $term_id ID du terme.
 * @return string Code de langue (chaine vide si aucune).
 */
function ditl_ml_langue_terme( $term_id ) {
	$term_id   = absint( $term_id );
	$extension = ditl_ml_extension();

	if ( 'polylang' === $extension ) {
		return (string) pll_get_term_language( $term_id, 'slug' );
	}

	if ( 'wpml' === $extension ) {
		$terme = get_term( $term_id );

		if ( ! $terme instanceof WP_Term ) {
			return '';
		}

		$details = apply_filters(
			'wpml_element_language_details',
			null,
			array(
				'element_id'   => (int) $terme->term_taxonomy_id,
				'element_type' => $terme->taxonomy,
			)
		);

		return is_object( $details ) && isset( $details->language_code ) ? (string) $details->language_code : '';
	}

	return ditl_ml_langue_defaut();
}

/**
 * Affecte une langue a un terme.
 *
 * @param int    $term_id ID du terme.
 * @param string $lang    Code de langue.
 * @return bool True si la langue a pu etre affectee.
 */
function ditl_ml_definir_langue_terme( $term_id, $lang ) {
	$term_id = absint( $term_id );

	if ( $term_id <= 0 || ! ditl_ml_langue_valide( $lang ) ) {
		return false;
	}

	$extension = ditl_ml_extension();

	if ( 'polylang' === $extension ) {
		pll_set_term_language( $term_id, $lang );

		return true;
	}

	if ( 'wpml' === $extension ) {
		$terme = get_term( $term_id );

		if ( ! $terme instanceof WP_Term ) {
			return false;
		}

		// WPML identifie un terme par son term_taxonomy_id.
		do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'    => (int) $terme->term_taxonomy_id,
				'element_type'  => apply_filters( 'wpml_element_type', $terme->taxonomy ),
				'trid'          => false,
				'language_code' => $lang,
			)
		);

		return true;
	}

	return false;
}

/**
 * Traductions d'un terme.
 *
 * @param int $term_id ID du terme.
 * @return int[] Tableau [code de langue => ID de terme], terme compris.
 */
function ditl_ml_traductions_terme( $term_id ) {
	$term_id   = absint( $term_id );
	$extension = ditl_ml_extension();

	if ( 'polylang' === $extension ) {
		return array_map( 'absint', (array) pll_get_term_translations( $term_id ) );
	}

	if ( 'wpml' === $extension ) {
		$terme = get_term( $term_id );

		if ( ! $terme instanceof WP_Term ) {
			return array();
		}

		$type           = apply_filters( 'wpml_element_type', $terme->taxonomy );
		$trid           = apply_filters( 'wpml_element_trid', null, (int) $terme->term_taxonomy_id, $type );
		$traductions    = $trid ? apply_filters( 'wpml_get_element_translations', null, $trid, $type ) : array();
		$correspondance = array();

		foreach ( (array) $traductions as $code => $traduction ) {
			if ( is_object( $traduction ) && ! empty( $traduction->term_id ) ) {
				$correspondance[ (string) $code ] = absint( $traduction->term_id );
			}
		}

		return $correspondance;
	}

	return array( ditl_ml_langue_defaut() => $term_id );
}

/**
 * Relie des termes comme traductions les uns des autres.
 *
 * Chaque terme doit deja avoir sa langue (ditl_ml_definir_langue_terme).
 *
 * @param int[] $correspondance Tableau [code de langue => ID de terme].
 * @return bool True si le lien a pu etre enregistre.
 */
function ditl_ml_lier_traductions_termes( $correspondance ) {
	$correspondance = ditl_ml_nettoyer_correspondance( $correspondance );

	if ( count( $correspondance ) < 2 ) {
		return false;
	}

	$extension = ditl_ml_extension();

	if ( 'polylang' === $extension ) {
		pll_save_term_translations( $correspondance );

		return true;
	}

	if ( 'wpml' === $extension ) {
		return ditl_ml_wpml_lier( $correspondance, 'term' );
	}

	return false;
}

/**
 * Filtre un tableau [code de langue => ID] : langues connues, IDs positifs.
 *
 * @param mixed $correspondance Tableau brut.
 * @return int[] Tableau nettoye.
 */
function ditl_ml_nettoyer_correspondance( $correspondance ) {
	$propre = array();

	foreach ( (array) $correspondance as $lang => $id ) {
		$id = is_scalar( $id ) ? absint( $id ) : 0;

		if ( $id > 0 && ditl_ml_langue_valide( $lang ) ) {
			$propre[ $lang ] = $id;
		}
	}

	return $propre;
}

/**
 * Branche WPML de la liaison des traductions (publications ou termes).
 *
 * La langue par defaut sert d'original si elle est presente, sinon la
 * premiere langue fournie. Les autres elements rejoignent son groupe de
 * traduction (trid). A RECETTER en phase 2.
 *
 * @param int[]  $correspondance Tableau [code de langue => ID].
 * @param string $nature         "post" ou "term".
 * @return bool True si la liaison a ete demandee.
 */
function ditl_ml_wpml_lier( $correspondance, $nature ) {
	$defaut        = ditl_ml_langue_defaut();
	$lang_original = isset( $correspondance[ $defaut ] ) ? $defaut : (string) array_key_first( $correspondance );
	$elements      = array();

	foreach ( $correspondance as $lang => $id ) {
		if ( 'term' === $nature ) {
			$terme = get_term( $id );

			if ( ! $terme instanceof WP_Term ) {
				continue;
			}

			$elements[ $lang ] = array(
				'id'   => (int) $terme->term_taxonomy_id,
				'type' => apply_filters( 'wpml_element_type', $terme->taxonomy ),
			);
		} else {
			$elements[ $lang ] = array(
				'id'   => (int) $id,
				'type' => apply_filters( 'wpml_element_type', get_post_type( $id ) ),
			);
		}
	}

	if ( ! isset( $elements[ $lang_original ] ) ) {
		return false;
	}

	$original = $elements[ $lang_original ];
	$trid     = apply_filters( 'wpml_element_trid', null, $original['id'], $original['type'] );

	if ( ! $trid ) {
		return false;
	}

	foreach ( $elements as $lang => $element ) {
		if ( $lang === $lang_original ) {
			continue;
		}

		do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'           => $element['id'],
				'element_type'         => $element['type'],
				'trid'                 => $trid,
				'language_code'        => $lang,
				'source_language_code' => $lang_original,
			)
		);
	}

	return true;
}
