<?php
/**
 * Moteur de la boite a outils : lecture du parcours, choix proposes a chaque
 * etape, correspondance des fiches, resultats, URL.
 *
 * API (contrat de la section 3.3 de la specification, noms et signatures
 * figes : les gabarits sont codes contre elle) :
 * - ditl_boite_page_id( $lang = null )
 * - ditl_boite_index( $lang = null )
 * - ditl_boite_fiche_couvre( array $term_ids_fiche, WP_Term $terme )
 * - ditl_boite_lire_parcours( $source = null )
 * - ditl_boite_choix_etape( $cle, array $parcours )
 * - ditl_boite_fiches_correspondantes( array $parcours )
 * - ditl_boite_filtrer_environnements( array $ids, array $parcours )
 * - ditl_boite_environnements_disponibles( array $ids )
 * - ditl_boite_requete_fiches( array $ids, $page, $par_page = DITL_BOITE_PAR_PAGE )
 * - ditl_boite_contexte()
 * - ditl_boite_args_parcours( array $parcours, array $remplacements = array() )
 * - ditl_boite_url( array $args )
 * - ditl_boite_url_fiche( $fiche_id, array $parcours )
 * - ditl_boite_url_retour( array $parcours )
 * - ditl_boite_autres_fiches( $fiche_id, array $parcours, $nombre = 12 )
 * Aides publiques complementaires : ditl_boite_langue(), ditl_boite_termes(),
 * ditl_boite_est_page_boite(), ditl_boite_parametres_presents(),
 * ditl_boite_numero_etape(), ditl_boite_vider_memo().
 *
 * Structure du parcours (retour de ditl_boite_lire_parcours) :
 *   langue         : code de la langue du parcours ;
 *   reponses       : [cle d'etape => WP_Term], validees dans l'ordre des
 *                    etapes ; la premiere reponse absente ou invalide coupe
 *                    toutes les suivantes ;
 *   sautees        : [cle d'etape] etapes de niveau 2 sans aucun choix ;
 *   etape_courante : premiere etape sans reponse ('' si parcours complet) ;
 *   etape_affichee : etape demandee par ?etape= si elle est deja repondue ou
 *                    courante, sinon l'etape courante ;
 *   complet        : true si toutes les etapes sont repondues ou sautees ;
 *   vue            : 'catalogue' si ?vue=catalogue ; sinon 'resultats' si le
 *                    parcours est complet et qu'aucune etape valide n'est
 *                    demandee par ?etape= ; sinon 'questionnaire'
 *                    (?vue=resultats est accepte mais sans effet) ;
 *   environnements : [code => WP_Term] environnements coches (ordre des
 *                    listes) ;
 *   page           : numero de page des resultats (1 a DITL_BOITE_PAGE_MAX) ;
 *   invalide       : cle de l'etape dont la valeur transmise a ete rejetee
 *                    (code inconnu, mauvais niveau, choix non propose), ''
 *                    sinon (permet un message d'erreur cote gabarit).
 *
 * Regle de correspondance (PROVISOIRE, question A1-3 du 07/09 au client) :
 * une fiche correspond au parcours si elle couvre, dans chaque taxonomie a
 * niveaux, le terme repondu le plus profond. Une fiche "couvre" un terme si
 * elle est cochee sur ce terme, sur un de ses descendants, ou sur un de ses
 * ancetres SANS aucun descendant de cet ancetre (regle de branche, voir
 * modele.php).
 *
 * Performances (cible : 300 fiches x 5 langues) : par langue, une requete
 * d'identifiants de fiches, un appel groupe wp_get_object_terms et une
 * lecture groupee des referentiels ; tout le reste (couverture, choix,
 * correspondance, environnements) est calcule en memoire et memorise pour
 * la duree de la requete. Aucune requete par fiche ni par choix. Aucune
 * requete au chargement des autres pages du site : rien n'est calcule tant
 * qu'un gabarit de la boite a outils (ou seo.php, sur la seule page Boite a
 * outils) n'appelle pas l'API.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// PROVISOIRE (decision client attendue) : seuls les choix menant a au moins
// une fiche publiee sont proposes, ce qui evite les impasses a 0 resultat.
// Passer a false pour proposer tous les termes des referentiels.
define( 'DITL_BOITE_MASQUER_CHOIX_VIDES', true );

// PROVISOIRE (decision client attendue) : nombre de fiches par page de
// resultats.
define( 'DITL_BOITE_PAR_PAGE', 12 );

// Garde-fou sur le numero de page lu dans l'URL (300 fiches = 25 pages).
define( 'DITL_BOITE_PAGE_MAX', 500 );

// Garde-fou sur le nombre d'environnements lus dans l'URL.
define( 'DITL_BOITE_ENV_MAX', 100 );

/**
 * Memoire de la requete en cours (resultats intermediaires du moteur).
 *
 * @param string|null   $groupe Groupe de valeurs (null = tout vider).
 * @param string        $cle    Cle dans le groupe.
 * @param callable|null $calcul Calcul de la valeur si elle est absente.
 * @return mixed Valeur memorisee.
 */
function ditl_boite_memo( $groupe, $cle = '', $calcul = null ) {
	static $memo = array();

	if ( null === $groupe ) {
		$memo = array();

		return null;
	}

	if ( ! isset( $memo[ $groupe ] ) || ! array_key_exists( $cle, $memo[ $groupe ] ) ) {
		$valeur                  = null === $calcul ? null : call_user_func( $calcul );
		$memo[ $groupe ][ $cle ] = $valeur;
	}

	return $memo[ $groupe ][ $cle ];
}

/**
 * Vide la memoire du moteur.
 *
 * Branche sur les invalidations de cache du coeur : une fiche, un terme ou
 * une affectation modifies dans la meme requete (import, script WP-CLI)
 * sont pris en compte au calcul suivant.
 */
function ditl_boite_vider_memo() {
	ditl_boite_memo( null );
}
add_action( 'clean_post_cache', 'ditl_boite_vider_memo' );
add_action( 'clean_term_cache', 'ditl_boite_vider_memo' );
add_action( 'clean_object_term_cache', 'ditl_boite_vider_memo' );

/**
 * Langue de travail du moteur.
 *
 * @param string|null $lang Code de langue (null = langue courante).
 * @return string Langue demandee si elle existe, sinon la langue courante,
 *                sinon la langue par defaut (jamais une langue inconnue :
 *                une langue vide ou inconnue leverait le filtre de langue
 *                des requetes et melangerait toutes les langues).
 */
function ditl_boite_langue( $lang = null ) {
	if ( null !== $lang && ditl_ml_langue_valide( $lang ) ) {
		return $lang;
	}

	$courante = ditl_ml_langue_courante();

	return ditl_ml_langue_valide( $courante ) ? $courante : ditl_ml_langue_defaut();
}

/**
 * Ajoute aux arguments d'une requete le filtre de langue de l'extension.
 *
 * Polylang : argument "lang". WPML : filtres actifs (la langue est basculee
 * par ditl_ml_avec_langue autour de la requete).
 *
 * @param array  $args Arguments de get_posts / get_terms.
 * @param string $lang Code de langue.
 * @return array Arguments completes.
 */
function ditl_boite_args_langue( array $args, $lang ) {
	$extension = ditl_ml_extension();

	if ( 'polylang' === $extension ) {
		$args['lang'] = $lang;
	} elseif ( 'wpml' === $extension ) {
		$args['suppress_filters'] = false;
	}

	return $args;
}

/**
 * Accueil du site dans une langue (repli si la page Boite a outils manque).
 *
 * A deplacer dans l'adaptateur multilingue (multilingue.php) a la prochaine
 * evolution de celui-ci.
 *
 * @param string $lang Code de langue.
 * @return string URL.
 */
function ditl_boite_accueil_langue( $lang ) {
	$extension = ditl_ml_extension();

	if ( 'polylang' === $extension && function_exists( 'pll_home_url' ) ) {
		return (string) pll_home_url( $lang );
	}

	if ( 'wpml' === $extension ) {
		return (string) apply_filters( 'wpml_permalink', home_url( '/' ), $lang );
	}

	return home_url( '/' );
}

/**
 * Indique si la requete en cours affiche la page Boite a outils.
 *
 * Aucune requete en base : le modele de page est lu dans les metas de la
 * page demandee, deja chargees par la requete principale.
 *
 * @return bool True sur une page portant le modele DITL_TPL_BOITE.
 */
function ditl_boite_est_page_boite() {
	if ( ! is_page() ) {
		return false;
	}

	return DITL_TPL_BOITE === get_page_template_slug( get_queried_object_id() );
}

/**
 * Indique si une source de parametres contient au moins un parametre du
 * parcours (etapes, environnements, vue, etape, page), meme vide.
 *
 * @param array|null $source Parametres (null = $_GET).
 * @return bool True si un parametre du parcours est present.
 */
function ditl_boite_parametres_presents( $source = null ) {
	if ( null === $source ) {
		$source = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule d'une navigation publique.
	}

	if ( ! is_array( $source ) ) {
		return false;
	}

	$cles = array_merge( array_keys( ditl_boite_etapes() ), array( DITL_BOITE_PARAM_ENV, DITL_BOITE_PARAM_VUE, DITL_BOITE_PARAM_ETAPE, DITL_BOITE_PARAM_PAGE ) );

	foreach ( $cles as $cle ) {
		if ( array_key_exists( $cle, $source ) ) {
			return true;
		}
	}

	return false;
}

/**
 * ID de la page Boite a outils dans une langue.
 *
 * @param string|null $lang Code de langue (null = langue courante).
 * @return int ID de la page publiee (ou privee si l'utilisateur peut la
 *             lire) portant le modele DITL_TPL_BOITE, 0 si aucune.
 */
function ditl_boite_page_id( $lang = null ) {
	$lang = ditl_boite_langue( $lang );

	// Sur la page elle-meme : aucune requete.
	if ( did_action( 'wp' ) && ditl_boite_est_page_boite() ) {
		$courante = (int) get_queried_object_id();

		if ( ditl_ml_langue_post( $courante ) === $lang ) {
			return $courante;
		}
	}

	return (int) ditl_boite_memo(
		'page_id',
		$lang,
		static function () use ( $lang ) {
			$args = ditl_boite_args_langue(
				array(
					'post_type'              => 'page',
					'post_status'            => array( 'publish', 'private' ),
					'posts_per_page'         => 10,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_key'               => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- une requete memorisee, pages seulement.
					'meta_value'             => DITL_TPL_BOITE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'suppress_filters'       => true,
				),
				$lang
			);

			$pages = ditl_ml_avec_langue(
				$lang,
				static function () use ( $args ) {
					return get_posts( $args );
				}
			);

			$privee = 0;

			foreach ( (array) $pages as $page ) {
				if ( ! $page instanceof WP_Post ) {
					continue;
				}

				if ( 'publish' === $page->post_status ) {
					return (int) $page->ID;
				}

				if ( 0 === $privee && current_user_can( 'read_post', $page->ID ) ) {
					$privee = (int) $page->ID;
				}
			}

			return $privee;
		}
	);
}

/**
 * Trie des termes : rang (_ditl_ordre) puis libelle, sans tenir compte des
 * accents ni de la casse (ordre naturel : "Tache 2" avant "Tache 10").
 *
 * @param WP_Term[] $termes Termes a trier.
 * @return WP_Term[] Termes tries (liste).
 */
function ditl_boite_trier_termes( array $termes ) {
	$cles = array();

	foreach ( $termes as $terme ) {
		$cles[ $terme->term_id ] = array(
			ditl_boite_sanitize_ordre( get_term_meta( $terme->term_id, '_ditl_ordre', true ) ),
			remove_accents( wp_specialchars_decode( $terme->name, ENT_QUOTES ) ),
		);
	}

	usort(
		$termes,
		static function ( $a, $b ) use ( $cles ) {
			$ka = $cles[ $a->term_id ];
			$kb = $cles[ $b->term_id ];

			if ( $ka[0] !== $kb[0] ) {
				return $ka[0] < $kb[0] ? -1 : 1;
			}

			$libelle = strnatcasecmp( $ka[1], $kb[1] );

			if ( 0 !== $libelle ) {
				return $libelle;
			}

			// Departage stable (usort n'est pas stable en PHP 7.4).
			return (int) $a->term_id - (int) $b->term_id;
		}
	);

	return array_values( $termes );
}

/**
 * Referentiels de la boite a outils dans une langue (une seule requete pour
 * toutes les taxonomies, metas des termes chargees au passage).
 *
 * @param string|null $lang Code de langue (null = langue courante).
 * @return array Tableau [taxonomie => WP_Term[]] tries (rang puis libelle).
 */
function ditl_boite_termes( $lang = null ) {
	$lang = ditl_boite_langue( $lang );

	return ditl_boite_memo(
		'termes',
		$lang,
		static function () use ( $lang ) {
			$taxonomies = array_keys( ditl_boite_taxonomies() );
			$args       = ditl_boite_args_langue(
				array(
					'taxonomy'               => $taxonomies,
					'hide_empty'             => false,
					'update_term_meta_cache' => true,
				),
				$lang
			);

			$termes = ditl_ml_avec_langue(
				$lang,
				static function () use ( $args ) {
					return get_terms( $args );
				}
			);

			$par_taxonomie = array_fill_keys( $taxonomies, array() );

			foreach ( is_array( $termes ) ? $termes : array() as $terme ) {
				if ( $terme instanceof WP_Term && isset( $par_taxonomie[ $terme->taxonomy ] ) ) {
					$par_taxonomie[ $terme->taxonomy ][] = $terme;
				}
			}

			foreach ( $par_taxonomie as $taxonomie => $liste ) {
				$par_taxonomie[ $taxonomie ] = ditl_boite_trier_termes( $liste );
			}

			return $par_taxonomie;
		}
	);
}

/**
 * Termes d'une taxonomie indexes par code (premier terme du tri en cas de
 * code en double).
 *
 * @param string $taxonomie Taxonomie de la boite a outils.
 * @param string $lang      Code de langue.
 * @return WP_Term[] Tableau [code => WP_Term] dans l'ordre des listes.
 */
function ditl_boite_termes_par_code( $taxonomie, $lang ) {
	$termes   = ditl_boite_termes( $lang );
	$par_code = array();

	foreach ( isset( $termes[ $taxonomie ] ) ? $termes[ $taxonomie ] : array() as $terme ) {
		$code = ditl_boite_code_terme( $terme );

		if ( '' !== $code && ! isset( $par_code[ $code ] ) ) {
			$par_code[ $code ] = $terme;
		}
	}

	return $par_code;
}

/**
 * Index des fiches publiees d'une langue et de leurs termes.
 *
 * Une requete d'identifiants (triee par titre) et un appel groupe
 * wp_get_object_terms pour toutes les fiches ; les referentiels de la langue
 * sont charges au passage (les calculs d'ancetres se font alors sans
 * requete).
 *
 * @param string|null $lang Code de langue (null = langue courante).
 * @return array Tableau [post_id => [taxonomie => int[] term_ids]], fiches
 *               dans l'ordre alphabetique des titres, toutes les taxonomies
 *               presentes (tableau vide si aucun terme).
 */
function ditl_boite_index( $lang = null ) {
	$lang = ditl_boite_langue( $lang );

	return ditl_boite_memo(
		'index',
		$lang,
		static function () use ( $lang ) {
			ditl_boite_termes( $lang );

			$taxonomies = array_keys( ditl_boite_taxonomies() );
			$args       = ditl_boite_args_langue(
				array(
					'post_type'              => DITL_RESSOURCE_POST_TYPE,
					'post_status'            => 'publish',
					// Fiche protegee par mot de passe : jamais listee (titre,
					// image et lien resteraient visibles dans les resultats).
					'has_password'           => false,
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'orderby'                => array(
						'title' => 'ASC',
						'ID'    => 'ASC',
					),
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'suppress_filters'       => true,
				),
				$lang
			);

			$ids = ditl_ml_avec_langue(
				$lang,
				static function () use ( $args ) {
					return get_posts( $args );
				}
			);

			$index = array();

			foreach ( (array) $ids as $id ) {
				$index[ (int) $id ] = array_fill_keys( $taxonomies, array() );
			}

			if ( array() === $index ) {
				return $index;
			}

			$termes = ditl_ml_avec_langue(
				$lang,
				static function () use ( $index, $taxonomies ) {
					return wp_get_object_terms(
						array_keys( $index ),
						$taxonomies,
						array(
							'fields'                 => 'all_with_object_id',
							'orderby'                => 'none',
							'update_term_meta_cache' => true,
						)
					);
				}
			);

			foreach ( is_array( $termes ) ? $termes : array() as $terme ) {
				if ( ! $terme instanceof WP_Term || ! isset( $terme->object_id ) ) {
					continue;
				}

				$post_id = (int) $terme->object_id;

				if ( isset( $index[ $post_id ][ $terme->taxonomy ] ) ) {
					$index[ $post_id ][ $terme->taxonomy ][] = (int) $terme->term_id;
				}
			}

			return $index;
		}
	);
}

/**
 * Ancetres d'un terme (du parent a la racine).
 *
 * @param int    $term_id   ID du terme.
 * @param string $taxonomie Taxonomie.
 * @return int[] IDs des ancetres.
 */
function ditl_boite_ancetres( $term_id, $taxonomie ) {
	$term_id = (int) $term_id;

	return ditl_boite_memo(
		'ancetres',
		$taxonomie . '|' . $term_id,
		static function () use ( $term_id, $taxonomie ) {
			return array_map( 'intval', get_ancestors( $term_id, $taxonomie, 'taxonomy' ) );
		}
	);
}

/**
 * Descendants d'un terme (tous niveaux).
 *
 * @param int    $term_id   ID du terme.
 * @param string $taxonomie Taxonomie.
 * @return int[] IDs des descendants.
 */
function ditl_boite_descendants( $term_id, $taxonomie ) {
	$term_id = (int) $term_id;

	return ditl_boite_memo(
		'descendants',
		$taxonomie . '|' . $term_id,
		static function () use ( $term_id, $taxonomie ) {
			$enfants = get_term_children( $term_id, $taxonomie );

			return is_array( $enfants ) ? array_map( 'intval', $enfants ) : array();
		}
	);
}

/**
 * Ensemble des termes couverts par une fiche dans une taxonomie (regle de
 * branche) : chaque terme coche, ses ancetres, et ses descendants s'il n'a
 * lui-meme aucun descendant coche.
 *
 * @param int[]  $term_ids_fiche Termes coches sur la fiche.
 * @param string $taxonomie      Taxonomie.
 * @return true[] Tableau [term_id => true].
 */
function ditl_boite_termes_couverts( array $term_ids_fiche, $taxonomie ) {
	$coches = array();

	foreach ( $term_ids_fiche as $id ) {
		if ( is_scalar( $id ) && (int) $id > 0 ) {
			$coches[ (int) $id ] = true;
		}
	}

	$couverts = $coches;

	foreach ( array_keys( $coches ) as $id ) {
		foreach ( ditl_boite_ancetres( $id, $taxonomie ) as $ancetre ) {
			$couverts[ $ancetre ] = true;
		}

		$descendants = ditl_boite_descendants( $id, $taxonomie );
		$precise     = false;

		foreach ( $descendants as $descendant ) {
			if ( isset( $coches[ $descendant ] ) ) {
				$precise = true;
				break;
			}
		}

		// Coche seul (sans enfant coche) : vaut pour toute sa branche.
		if ( ! $precise ) {
			foreach ( $descendants as $descendant ) {
				$couverts[ $descendant ] = true;
			}
		}
	}

	return $couverts;
}

/**
 * Indique si une fiche couvre un terme (regle de branche, section 2 de la
 * specification) : fiche cochee sur le terme, ou sur un de ses descendants,
 * ou sur un de ses ancetres sans aucun descendant de cet ancetre.
 *
 * @param int[]   $term_ids_fiche Termes coches sur la fiche (toutes
 *                                taxonomies admises).
 * @param WP_Term $terme          Terme recherche.
 * @return bool True si la fiche couvre le terme.
 */
function ditl_boite_fiche_couvre( array $term_ids_fiche, WP_Term $terme ) {
	$couverts = ditl_boite_termes_couverts( $term_ids_fiche, $terme->taxonomy );

	return isset( $couverts[ (int) $terme->term_id ] );
}

/**
 * Termes couverts par une fiche de l'index (memorise par fiche).
 *
 * @param int    $post_id   ID de la fiche.
 * @param string $taxonomie Taxonomie.
 * @param string $lang      Langue de l'index.
 * @return true[] Tableau [term_id => true].
 */
function ditl_boite_couverts_fiche( $post_id, $taxonomie, $lang ) {
	$post_id = (int) $post_id;

	return ditl_boite_memo(
		'couverts',
		$lang . '|' . $taxonomie . '|' . $post_id,
		static function () use ( $post_id, $taxonomie, $lang ) {
			$index = ditl_boite_index( $lang );

			return isset( $index[ $post_id ][ $taxonomie ] ) ? ditl_boite_termes_couverts( $index[ $post_id ][ $taxonomie ], $taxonomie ) : array();
		}
	);
}

/**
 * Fiches de la langue qui couvrent, dans chaque taxonomie a niveaux, le
 * terme repondu le plus profond.
 *
 * @param WP_Term[] $reponses Reponses [cle d'etape => WP_Term].
 * @param string    $lang     Code de langue.
 * @return int[] IDs des fiches (ordre alphabetique des titres).
 */
function ditl_boite_fiches_pour_reponses( array $reponses, $lang ) {
	$profonds = array();

	foreach ( ditl_boite_etapes() as $cle => $etape ) {
		if ( isset( $reponses[ $cle ] ) && $reponses[ $cle ] instanceof WP_Term ) {
			// Les etapes sont ordonnees par niveau : la derniere repondue
			// d'une taxonomie est la plus profonde.
			$profonds[ $etape['taxonomie'] ] = (int) $reponses[ $cle ]->term_id;
		}
	}

	ksort( $profonds );
	$signature = $lang . '|' . wp_json_encode( $profonds );

	return ditl_boite_memo(
		'correspondances',
		$signature,
		static function () use ( $profonds, $lang ) {
			$ids = array();

			foreach ( array_keys( ditl_boite_index( $lang ) ) as $post_id ) {
				foreach ( $profonds as $taxonomie => $term_id ) {
					$couverts = ditl_boite_couverts_fiche( $post_id, $taxonomie, $lang );

					if ( ! isset( $couverts[ $term_id ] ) ) {
						continue 2;
					}
				}

				$ids[] = (int) $post_id;
			}

			return $ids;
		}
	);
}

/**
 * Lit un code dans une source de parametres.
 *
 * @param array  $source Parametres.
 * @param string $cle    Nom du parametre.
 * @return string Code nettoye ('' si absent ou non scalaire).
 */
function ditl_boite_lire_code( array $source, $cle ) {
	if ( ! isset( $source[ $cle ] ) || ! is_scalar( $source[ $cle ] ) ) {
		return '';
	}

	return ditl_boite_sanitize_code( wp_unslash( (string) $source[ $cle ] ) );
}

/**
 * Lit une liste de codes (tableau env[] ou chaine "a,b").
 *
 * @param mixed $valeur Valeur brute.
 * @return string[] Codes nettoyes, uniques, DITL_BOITE_ENV_MAX au plus.
 */
function ditl_boite_lire_codes( $valeur ) {
	if ( is_scalar( $valeur ) ) {
		$valeur = explode( ',', (string) $valeur );
	}

	if ( ! is_array( $valeur ) ) {
		return array();
	}

	$codes = array();

	foreach ( array_slice( $valeur, 0, DITL_BOITE_ENV_MAX ) as $brut ) {
		$code = is_scalar( $brut ) ? ditl_boite_sanitize_code( wp_unslash( (string) $brut ) ) : '';

		if ( '' !== $code ) {
			$codes[ $code ] = true;
		}
	}

	return array_keys( $codes );
}

/**
 * Lit et valide le parcours porte par l'URL.
 *
 * Les reponses sont validees dans l'ordre des etapes : code connu dans la
 * langue courante, niveau correct (niveau 1 = terme racine ; niveau 2 =
 * enfant direct de la reponse parente) et present parmi les choix proposes
 * pour les reponses precedentes. La premiere reponse absente ou invalide
 * coupe toutes les suivantes. Une etape de niveau 2 sans aucun choix est
 * sautee (sa valeur eventuelle est ignoree, sans couper la suite).
 *
 * @param array|null $source Parametres (null = $_GET).
 * @return array Parcours (structure decrite en tete de fichier).
 */
function ditl_boite_lire_parcours( $source = null ) {
	if ( null === $source ) {
		$source = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule d'une navigation publique, chaque valeur est validee.
	}

	$source = is_array( $source ) ? $source : array();
	$lang   = ditl_boite_langue();

	$parcours = array(
		'langue'         => $lang,
		'reponses'       => array(),
		'sautees'        => array(),
		'etape_courante' => '',
		'etape_affichee' => '',
		'complet'        => false,
		'vue'            => 'questionnaire',
		'environnements' => array(),
		'page'           => 1,
		'invalide'       => '',
	);

	foreach ( ditl_boite_etapes() as $cle => $etape ) {
		$choix = ditl_boite_choix_etape( $cle, $parcours );

		if ( 2 === (int) $etape['niveau'] && array() === $choix ) {
			$parcours['sautees'][] = $cle;
			continue;
		}

		$code  = ditl_boite_lire_code( $source, $cle );
		$codes = '' !== $code ? ditl_boite_termes_par_code( $etape['taxonomie'], $lang ) : array();
		$terme = isset( $codes[ $code ] ) ? $codes[ $code ] : null;

		if ( $terme instanceof WP_Term ) {
			$parent_attendu = 1 === (int) $etape['niveau'] ? 0 : (int) $parcours['reponses'][ $etape['parent'] ]->term_id;
			$propose        = false;

			foreach ( $choix as $possible ) {
				if ( (int) $possible->term_id === (int) $terme->term_id ) {
					$propose = true;
					break;
				}
			}

			if ( $propose && (int) $terme->parent === $parent_attendu ) {
				$parcours['reponses'][ $cle ] = $terme;
				continue;
			}
		}

		if ( '' !== $code ) {
			$parcours['invalide'] = $cle;
		}

		// Premiere etape sans reponse valide : les suivantes sont ignorees.
		$parcours['etape_courante'] = $cle;
		break;
	}

	$parcours['complet'] = '' === $parcours['etape_courante'];

	// Etape demandee : deja repondue ou courante, sinon l'etape courante.
	$demandee        = ditl_boite_lire_code( $source, DITL_BOITE_PARAM_ETAPE );
	$demandee_valide = '' !== $demandee && ( isset( $parcours['reponses'][ $demandee ] ) || $demandee === $parcours['etape_courante'] );

	$parcours['etape_affichee'] = $demandee_valide ? $demandee : $parcours['etape_courante'];

	if ( 'catalogue' === ditl_boite_lire_code( $source, DITL_BOITE_PARAM_VUE ) ) {
		$parcours['vue'] = 'catalogue';
	} elseif ( $parcours['complet'] && ! $demandee_valide ) {
		$parcours['vue'] = 'resultats';
	}

	// Environnements : codes valides de la langue, dans l'ordre des listes.
	$demandes = array_flip( ditl_boite_lire_codes( isset( $source[ DITL_BOITE_PARAM_ENV ] ) ? $source[ DITL_BOITE_PARAM_ENV ] : array() ) );

	if ( array() !== $demandes ) {
		foreach ( ditl_boite_termes_par_code( 'ditl_environnement', $lang ) as $code => $terme ) {
			if ( isset( $demandes[ $code ] ) ) {
				$parcours['environnements'][ $code ] = $terme;
			}
		}
	}

	$page = isset( $source[ DITL_BOITE_PARAM_PAGE ] ) && is_scalar( $source[ DITL_BOITE_PARAM_PAGE ] ) ? absint( wp_unslash( (string) $source[ DITL_BOITE_PARAM_PAGE ] ) ) : 1;

	$parcours['page'] = max( 1, min( DITL_BOITE_PAGE_MAX, $page ) );

	return $parcours;
}

/**
 * Indique si une fiche est marquee dans toutes les taxonomies du
 * questionnaire (au moins un terme dans chacune), seule condition pour
 * qu'elle puisse correspondre a un parcours complet.
 *
 * @param int    $post_id ID de la fiche.
 * @param string $lang    Langue de l'index.
 * @return bool True si la fiche peut sortir en resultat du questionnaire.
 */
function ditl_boite_fiche_questionnaire( $post_id, $lang ) {
	$index = ditl_boite_index( $lang );

	foreach ( ditl_boite_etapes() as $etape ) {
		if ( empty( $index[ (int) $post_id ][ $etape['taxonomie'] ] ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Choix proposes a une etape.
 *
 * Niveau 1 : termes racine de la taxonomie ; niveau 2 : enfants directs de
 * la reponse a l'etape parente (aucun choix si elle manque). Si
 * DITL_BOITE_MASQUER_CHOIX_VIDES : seuls les termes couverts par au moins
 * une fiche correspondant aux reponses des etapes PRECEDENTES.
 *
 * @param string $cle      Cle de l'etape.
 * @param array  $parcours Parcours (ditl_boite_lire_parcours ou partiel).
 * @return WP_Term[] Choix tries (rang puis libelle).
 */
function ditl_boite_choix_etape( $cle, array $parcours ) {
	$etapes = ditl_boite_etapes();

	if ( ! is_string( $cle ) || ! isset( $etapes[ $cle ] ) ) {
		return array();
	}

	$etape    = $etapes[ $cle ];
	$lang     = ditl_boite_langue( isset( $parcours['langue'] ) ? $parcours['langue'] : null );
	$reponses = isset( $parcours['reponses'] ) && is_array( $parcours['reponses'] ) ? $parcours['reponses'] : array();

	// Reponses des etapes precedentes uniquement.
	$precedentes = array();

	foreach ( array_keys( $etapes ) as $cle_etape ) {
		if ( $cle_etape === $cle ) {
			break;
		}

		if ( isset( $reponses[ $cle_etape ] ) && $reponses[ $cle_etape ] instanceof WP_Term ) {
			$precedentes[ $cle_etape ] = $reponses[ $cle_etape ];
		}
	}

	$parent = 0;

	if ( 2 === (int) $etape['niveau'] ) {
		if ( ! isset( $precedentes[ $etape['parent'] ] ) ) {
			return array();
		}

		$parent = (int) $precedentes[ $etape['parent'] ]->term_id;
	}

	$signature = array();

	foreach ( $precedentes as $cle_etape => $terme ) {
		$signature[ $cle_etape ] = (int) $terme->term_id;
	}

	return ditl_boite_memo(
		'choix',
		$lang . '|' . $cle . '|' . wp_json_encode( $signature ),
		static function () use ( $etape, $lang, $parent, $precedentes ) {
			$termes = ditl_boite_termes( $lang );
			$choix  = array();

			foreach ( isset( $termes[ $etape['taxonomie'] ] ) ? $termes[ $etape['taxonomie'] ] : array() as $terme ) {
				if ( (int) $terme->parent === $parent ) {
					$choix[] = $terme;
				}
			}

			if ( ! DITL_BOITE_MASQUER_CHOIX_VIDES || array() === $choix ) {
				return $choix;
			}

			// Union des termes couverts par les fiches encore possibles.
			$couverts = array();

			foreach ( ditl_boite_fiches_pour_reponses( $precedentes, $lang ) as $post_id ) {
				// Une fiche sans terme dans l'une des taxonomies du
				// questionnaire ne peut jamais sortir en resultat : elle ne
				// doit pas rendre un choix proposable (sinon impasse a une
				// etape suivante sans aucun choix).
				if ( ditl_boite_fiche_questionnaire( $post_id, $lang ) ) {
					$couverts += ditl_boite_couverts_fiche( $post_id, $etape['taxonomie'], $lang );
				}
			}

			$visibles = array();

			foreach ( $choix as $terme ) {
				if ( isset( $couverts[ (int) $terme->term_id ] ) ) {
					$visibles[] = $terme;
				}
			}

			return $visibles;
		}
	);
}

/**
 * Fiches correspondant au parcours.
 *
 * @param array $parcours Parcours.
 * @return int[] IDs des fiches publiees de la langue du parcours, ordre
 *               alphabetique des titres (catalogue : toutes les fiches).
 */
function ditl_boite_fiches_correspondantes( array $parcours ) {
	$lang = ditl_boite_langue( isset( $parcours['langue'] ) ? $parcours['langue'] : null );

	if ( isset( $parcours['vue'] ) && 'catalogue' === $parcours['vue'] ) {
		return array_keys( ditl_boite_index( $lang ) );
	}

	$reponses = isset( $parcours['reponses'] ) && is_array( $parcours['reponses'] ) ? $parcours['reponses'] : array();

	return ditl_boite_fiches_pour_reponses( $reponses, $lang );
}

/**
 * Filtre des fiches par environnement (OU entre les environnements coches).
 *
 * @param int[] $ids      IDs des fiches.
 * @param array $parcours Parcours (environnements coches).
 * @return int[] IDs conserves, dans l'ordre recu.
 */
function ditl_boite_filtrer_environnements( array $ids, array $parcours ) {
	$environnements = isset( $parcours['environnements'] ) && is_array( $parcours['environnements'] ) ? $parcours['environnements'] : array();

	if ( array() === $environnements ) {
		return array_values( array_map( 'intval', $ids ) );
	}

	$voulus = array();

	foreach ( $environnements as $terme ) {
		if ( $terme instanceof WP_Term ) {
			$voulus[ (int) $terme->term_id ] = true;
		}
	}

	$index    = ditl_boite_index( ditl_boite_langue( isset( $parcours['langue'] ) ? $parcours['langue'] : null ) );
	$filtrees = array();

	foreach ( $ids as $id ) {
		$id = (int) $id;

		if ( ! isset( $index[ $id ]['ditl_environnement'] ) ) {
			continue;
		}

		foreach ( $index[ $id ]['ditl_environnement'] as $term_id ) {
			if ( isset( $voulus[ $term_id ] ) ) {
				$filtrees[] = $id;
				break;
			}
		}
	}

	return $filtrees;
}

/**
 * Environnements de la langue courante avec le nombre de fiches concernees.
 *
 * @param int[] $ids IDs des fiches a compter (en general les fiches
 *                   correspondantes, avant filtre).
 * @return array Tableau [term_id => array( 'terme' => WP_Term, 'nombre' =>
 *               int )], tous les environnements de la langue, dans l'ordre
 *               des listes (rang puis libelle).
 */
function ditl_boite_environnements_disponibles( array $ids ) {
	$lang        = ditl_boite_langue();
	$index       = ditl_boite_index( $lang );
	$termes      = ditl_boite_termes( $lang );
	$disponibles = array();

	foreach ( $termes['ditl_environnement'] as $terme ) {
		$disponibles[ (int) $terme->term_id ] = array(
			'terme'  => $terme,
			'nombre' => 0,
		);
	}

	foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
		if ( ! isset( $index[ $id ]['ditl_environnement'] ) ) {
			continue;
		}

		foreach ( $index[ $id ]['ditl_environnement'] as $term_id ) {
			if ( isset( $disponibles[ $term_id ] ) ) {
				$disponibles[ $term_id ]['nombre']++;
			}
		}
	}

	return $disponibles;
}

/**
 * Requete paginee des fiches a afficher (tri alphabetique des titres).
 *
 * Les IDs sont deja filtres par langue : le filtre de langue de Polylang
 * est leve ("lang" vide). Termes et metas des fiches de la page sont
 * charges en deux requetes groupees (badges, illustrations).
 *
 * @param int[] $ids      IDs des fiches (vide = 0 resultat).
 * @param int   $page     Numero de page (1 au minimum).
 * @param int   $par_page Fiches par page.
 * @return WP_Query Requete executee.
 */
function ditl_boite_requete_fiches( array $ids, $page, $par_page = DITL_BOITE_PAR_PAGE ) {
	$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

	$args = array(
		'post_type'           => DITL_RESSOURCE_POST_TYPE,
		'post_status'         => 'publish',
		'post__in'            => array() === $ids ? array( 0 ) : $ids,
		'orderby'             => array(
			'title' => 'ASC',
			'ID'    => 'ASC',
		),
		'posts_per_page'      => max( 1, min( 100, (int) $par_page ) ),
		'paged'               => max( 1, (int) $page ),
		'ignore_sticky_posts' => true,
	);

	if ( 'polylang' === ditl_ml_extension() ) {
		$args['lang'] = '';
	}

	return new WP_Query( $args );
}

/**
 * Contexte vide (requete hors boite a outils).
 *
 * @return array Contexte aux cles de ditl_boite_contexte().
 */
function ditl_boite_contexte_vide() {
	return array(
		'type'           => '',
		'langue'         => '',
		'page_id'        => 0,
		'textes'         => array(),
		'parcours'       => array(),
		'ids'            => array(),
		'ids_filtres'    => array(),
		'total'          => 0,
		'page'           => 1,
		'pages'          => 0,
		'par_page'       => DITL_BOITE_PAR_PAGE,
		'environnements' => array(),
		'requete'        => null,
	);
}

/**
 * Calcule le contexte d'une vue de la boite a outils.
 *
 * @param string     $type   "boite" (page Boite a outils) ou "fiche".
 * @param array|null $source Parametres (null = $_GET).
 * @return array Contexte (voir ditl_boite_contexte()).
 */
function ditl_boite_calculer_contexte( $type, $source = null ) {
	$contexte = ditl_boite_contexte_vide();
	$parcours = ditl_boite_lire_parcours( $source );

	$contexte['type']     = 'fiche' === $type ? 'fiche' : 'boite';
	$contexte['langue']   = $parcours['langue'];
	$contexte['page_id']  = ditl_boite_page_id( $parcours['langue'] );
	$contexte['textes']   = ditl_boite_textes( $contexte['page_id'] );
	$contexte['parcours'] = $parcours;
	$contexte['page']     = $parcours['page'];

	if ( 'questionnaire' === $parcours['vue'] ) {
		return $contexte;
	}

	$contexte['ids']         = ditl_boite_fiches_correspondantes( $parcours );
	$contexte['ids_filtres'] = ditl_boite_filtrer_environnements( $contexte['ids'], $parcours );
	$contexte['total']       = count( $contexte['ids_filtres'] );
	$contexte['pages']       = (int) ceil( $contexte['total'] / $contexte['par_page'] );

	// Page au-dela de la derniere (lien perime) : derniere page.
	$contexte['page'] = max( 1, min( $parcours['page'], $contexte['pages'] ) );

	if ( 'boite' === $contexte['type'] ) {
		$contexte['environnements'] = ditl_boite_environnements_disponibles( $contexte['ids'] );
		$contexte['requete']        = ditl_boite_requete_fiches( $contexte['ids_filtres'], $contexte['page'], $contexte['par_page'] );
	}

	return $contexte;
}

/**
 * Contexte de la vue courante, calcule une fois par requete et partage
 * entre le <title> (seo.php) et les gabarits.
 *
 * Cles : type ("boite", "fiche" ou "" hors boite a outils), langue,
 * page_id (page Boite a outils de la langue), textes (ditl_boite_textes),
 * parcours, ids (fiches correspondantes), ids_filtres (apres filtre des
 * environnements), total, page (bornee a la derniere page), pages,
 * par_page, environnements (ditl_boite_environnements_disponibles, page
 * Boite a outils seulement), requete (WP_Query paginee, page Boite a outils
 * en vue resultats ou catalogue, null sinon). En vue questionnaire, ids,
 * ids_filtres et total restent vides.
 *
 * @return array Contexte.
 */
function ditl_boite_contexte() {
	// Avant l'analyse de la requete principale, la vue est inconnue : rien
	// n'est memorise.
	if ( ! did_action( 'wp' ) ) {
		return ditl_boite_contexte_vide();
	}

	return ditl_boite_memo(
		'contexte',
		'courant',
		static function () {
			if ( ditl_boite_est_page_boite() ) {
				return ditl_boite_calculer_contexte( 'boite' );
			}

			if ( is_singular( DITL_RESSOURCE_POST_TYPE ) ) {
				return ditl_boite_calculer_contexte( 'fiche' );
			}

			return ditl_boite_contexte_vide();
		}
	);
}

/**
 * Rang d'une etape dans le questionnaire (1 a N).
 *
 * PROVISOIRE : la numerotation suit l'etapier complet (une etape sautee
 * garde son numero), pour que le <title> et l'etapier visible annoncent la
 * meme position.
 *
 * @param string $cle Cle de l'etape.
 * @return int Rang (0 si etape inconnue).
 */
function ditl_boite_numero_etape( $cle ) {
	$rang = array_search( $cle, array_keys( ditl_boite_etapes() ), true );

	return false === $rang ? 0 : (int) $rang + 1;
}

/**
 * Normalise des arguments d'URL du parcours : ordre fixe (etapes dans
 * l'ordre, env, vue, etape, pg), valeurs nettoyees, valeurs vides omises.
 *
 * @param array $args Arguments bruts.
 * @return array Arguments normalises (env = liste de codes).
 */
function ditl_boite_normaliser_args( array $args ) {
	$etapes = ditl_boite_etapes();
	$propre = array();

	foreach ( array_keys( $etapes ) as $cle ) {
		if ( ! isset( $args[ $cle ] ) ) {
			continue;
		}

		$code = $args[ $cle ] instanceof WP_Term ? ditl_boite_code_terme( $args[ $cle ] ) : ditl_boite_lire_code( $args, $cle );

		if ( '' !== $code ) {
			$propre[ $cle ] = $code;
		}
	}

	if ( isset( $args[ DITL_BOITE_PARAM_ENV ] ) ) {
		$env = ditl_boite_lire_codes( $args[ DITL_BOITE_PARAM_ENV ] );

		if ( array() !== $env ) {
			$propre[ DITL_BOITE_PARAM_ENV ] = $env;
		}
	}

	// Seul le catalogue s'ecrit dans l'URL : les resultats se deduisent d'un
	// parcours complet.
	if ( 'catalogue' === ditl_boite_lire_code( $args, DITL_BOITE_PARAM_VUE ) ) {
		$propre[ DITL_BOITE_PARAM_VUE ] = 'catalogue';
	}

	$etape = ditl_boite_lire_code( $args, DITL_BOITE_PARAM_ETAPE );

	if ( isset( $etapes[ $etape ] ) ) {
		$propre[ DITL_BOITE_PARAM_ETAPE ] = $etape;
	}

	$page = isset( $args[ DITL_BOITE_PARAM_PAGE ] ) && is_scalar( $args[ DITL_BOITE_PARAM_PAGE ] ) ? absint( $args[ DITL_BOITE_PARAM_PAGE ] ) : 0;

	if ( $page > 1 ) {
		$propre[ DITL_BOITE_PARAM_PAGE ] = min( DITL_BOITE_PAGE_MAX, $page );
	}

	return $propre;
}

/**
 * Arguments d'URL decrivant un parcours.
 *
 * Etapes repondues (codes), environnements coches, vue "catalogue", etape
 * affichee si elle differe de l'etape courante (modification d'une
 * reponse), page si > 1 en vue resultats ou catalogue. Les remplacements
 * ecrasent les valeurs (cles : etapes, env, vue, etape, pg ; '' ou array()
 * retire la valeur ; autres cles ignorees). Remplacer une reponse ne retire
 * pas les suivantes : la validation du parcours les ecartera si elles ne
 * sont plus coherentes.
 *
 * @param array $parcours      Parcours.
 * @param array $remplacements Valeurs a remplacer.
 * @return array Arguments normalises (ordre fixe, valeurs vides omises).
 */
function ditl_boite_args_parcours( array $parcours, array $remplacements = array() ) {
	$args = array();

	foreach ( array_keys( ditl_boite_etapes() ) as $cle ) {
		$args[ $cle ] = isset( $parcours['reponses'][ $cle ] ) && $parcours['reponses'][ $cle ] instanceof WP_Term ? ditl_boite_code_terme( $parcours['reponses'][ $cle ] ) : '';
	}

	$args[ DITL_BOITE_PARAM_ENV ] = isset( $parcours['environnements'] ) && is_array( $parcours['environnements'] ) ? array_map( 'strval', array_keys( $parcours['environnements'] ) ) : array();

	$vue = isset( $parcours['vue'] ) ? $parcours['vue'] : 'questionnaire';

	$args[ DITL_BOITE_PARAM_VUE ] = 'catalogue' === $vue ? 'catalogue' : '';

	$affichee = isset( $parcours['etape_affichee'] ) ? (string) $parcours['etape_affichee'] : '';
	$courante = isset( $parcours['etape_courante'] ) ? (string) $parcours['etape_courante'] : '';

	$args[ DITL_BOITE_PARAM_ETAPE ] = '' !== $affichee && $affichee !== $courante ? $affichee : '';

	$page = isset( $parcours['page'] ) ? (int) $parcours['page'] : 1;

	$args[ DITL_BOITE_PARAM_PAGE ] = 'questionnaire' !== $vue && $page > 1 ? $page : '';

	foreach ( $remplacements as $cle => $valeur ) {
		if ( array_key_exists( $cle, $args ) ) {
			$args[ $cle ] = null === $valeur ? '' : $valeur;
		}
	}

	return ditl_boite_normaliser_args( $args );
}

/**
 * Chaine de requete des arguments du parcours (ordre fixe, env encode comme
 * le fait un formulaire GET : env%5B%5D=a&env%5B%5D=b).
 *
 * @param array $args Arguments (normalises ici).
 * @return string Chaine de requete sans "?" ('' si aucun argument).
 */
function ditl_boite_chaine_requete( array $args ) {
	$parties = array();

	foreach ( ditl_boite_normaliser_args( $args ) as $cle => $valeur ) {
		if ( is_array( $valeur ) ) {
			foreach ( $valeur as $code ) {
				$parties[] = rawurlencode( $cle ) . '%5B%5D=' . rawurlencode( $code );
			}
		} else {
			$parties[] = rawurlencode( $cle ) . '=' . rawurlencode( (string) $valeur );
		}
	}

	return implode( '&', $parties );
}

/**
 * Ajoute une chaine de requete a une URL.
 *
 * @param string $url    URL de base.
 * @param string $chaine Chaine de requete sans "?".
 * @return string URL complete (non echappee : esc_url a l'affichage).
 */
function ditl_boite_ajouter_requete( $url, $chaine ) {
	if ( '' === $chaine ) {
		return $url;
	}

	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $chaine;
}

/**
 * URL de la page Boite a outils de la langue courante avec des arguments.
 *
 * Repli sur l'accueil de la langue si la page n'existe pas.
 *
 * @param array $args Arguments (ditl_boite_args_parcours ou bruts).
 * @return string URL (non echappee : esc_url a l'affichage).
 */
function ditl_boite_url( array $args ) {
	$lang    = ditl_boite_langue();
	$page_id = ditl_boite_page_id( $lang );
	$base    = $page_id > 0 ? get_permalink( $page_id ) : '';

	if ( ! is_string( $base ) || '' === $base ) {
		$base = ditl_boite_accueil_langue( $lang );
	}

	return ditl_boite_ajouter_requete( $base, ditl_boite_chaine_requete( $args ) );
}

/**
 * URL d'une fiche portant le contexte du parcours (reponses, environnements,
 * vue catalogue, page) : la fiche sait ainsi revenir aux resultats.
 *
 * @param int   $fiche_id ID de la fiche.
 * @param array $parcours Parcours.
 * @return string URL (non echappee), '' si la fiche n'existe pas.
 */
function ditl_boite_url_fiche( $fiche_id, array $parcours ) {
	$lien = get_permalink( absint( $fiche_id ) );

	if ( ! is_string( $lien ) || '' === $lien ) {
		return '';
	}

	return ditl_boite_ajouter_requete( $lien, ditl_boite_chaine_requete( ditl_boite_args_parcours( $parcours, array( DITL_BOITE_PARAM_ETAPE => '' ) ) ) );
}

/**
 * URL de retour depuis une fiche : resultats si le parcours est complet,
 * catalogue si la fiche a ete ouverte depuis le catalogue, sinon page Boite
 * a outils (reponses deja donnees conservees).
 *
 * @param array $parcours Parcours.
 * @return string URL (non echappee).
 */
function ditl_boite_url_retour( array $parcours ) {
	return ditl_boite_url( ditl_boite_args_parcours( $parcours, array( DITL_BOITE_PARAM_ETAPE => '' ) ) );
}

/**
 * Termes d'une fiche de l'index completes de leurs ancetres (proximite).
 *
 * @param array $termes_fiche Tableau [taxonomie => int[]] de l'index.
 * @return true[] Tableau [term_id => true].
 */
function ditl_boite_termes_etendus( array $termes_fiche ) {
	$etendus = array();

	foreach ( $termes_fiche as $taxonomie => $term_ids ) {
		foreach ( (array) $term_ids as $term_id ) {
			$etendus[ (int) $term_id ] = true;

			foreach ( ditl_boite_ancetres( $term_id, $taxonomie ) as $ancetre ) {
				$etendus[ $ancetre ] = true;
			}
		}
	}

	return $etendus;
}

/**
 * Autres fiches a proposer sur une fiche.
 *
 * Parcours en vue resultats : les resultats du parcours (filtre des
 * environnements compris), sans la fiche. Sinon (ou si la fiche etait le
 * seul resultat) : fiches proches, classees par nombre de termes communs
 * decroissant (termes coches et leurs ancetres, toutes taxonomies) puis par
 * titre ; une fiche sans aucun terme commun n'est pas proposee.
 *
 * @param int   $fiche_id ID de la fiche affichee.
 * @param array $parcours Parcours.
 * @param int   $nombre   Nombre maximum de fiches (1 a 100).
 * @return int[] IDs des fiches.
 */
function ditl_boite_autres_fiches( $fiche_id, array $parcours, $nombre = 12 ) {
	$fiche_id = absint( $fiche_id );
	$nombre   = max( 1, min( 100, (int) $nombre ) );

	if ( isset( $parcours['vue'] ) && 'resultats' === $parcours['vue'] ) {
		$resultats = array_values( array_diff( ditl_boite_filtrer_environnements( ditl_boite_fiches_correspondantes( $parcours ), $parcours ), array( $fiche_id ) ) );

		if ( array() !== $resultats ) {
			return array_slice( $resultats, 0, $nombre );
		}
	}

	$lang  = ditl_boite_langue( ditl_ml_langue_post( $fiche_id ) );
	$index = ditl_boite_index( $lang );

	if ( ! isset( $index[ $fiche_id ] ) ) {
		return array();
	}

	$reference = ditl_boite_termes_etendus( $index[ $fiche_id ] );
	$proches   = array();
	$rang      = 0;

	foreach ( $index as $id => $termes ) {
		++$rang;

		if ( $id === $fiche_id ) {
			continue;
		}

		$communs = count( array_intersect_key( $reference, ditl_boite_termes_etendus( $termes ) ) );

		if ( $communs > 0 ) {
			$proches[] = array( $id, $communs, $rang );
		}
	}

	usort(
		$proches,
		static function ( $a, $b ) {
			if ( $a[1] !== $b[1] ) {
				return $b[1] - $a[1];
			}

			// Rang dans l'index = ordre alphabetique des titres.
			return $a[2] - $b[2];
		}
	);

	$ids = array();

	foreach ( array_slice( $proches, 0, $nombre ) as $proche ) {
		$ids[] = (int) $proche[0];
	}

	return $ids;
}
