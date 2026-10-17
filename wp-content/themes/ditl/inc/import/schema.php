<?php
/**
 * Import des fiches : schema declaratif (mapping colonnes CSV -> modele).
 *
 * Chaque colonne connue declare son nom canonique, ses alias (format de
 * l'echantillon de juillet ET format propose le 07/09, en-tetes francais
 * usuels), son type, son caractere obligatoire dans l'EN-TETE et sa cible
 * dans le modele de la boite a outils :
 * - array( 'post', <champ> )                    : champ de la publication ;
 * - array( 'meta', <cle> )                      : meta du contrat ;
 * - array( 'taxonomie', <taxonomie>, <niveau> ) : termes par code ;
 * - array( 'image' )                            : image a la une ;
 * - array( 'fabricants' )                       : meta _ditl_fabricants.
 * Les colonnes fabricant_N / fabricant_N_url (N de 1 a 100) sont reconnues
 * par motif, en plus de la colonne "fabricants" du format de juillet.
 *
 * Regle de mise a jour : une colonne ABSENTE du fichier ne touche pas le
 * champ correspondant d'une fiche existante (import partiel possible) ; une
 * colonne presente mais vide vide le champ.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Colonnes du fichier des fiches.
 *
 * Types : entier, langue, statut, texte (une ligne), texte_long (texte
 * simple multi-lignes), texte_riche (convention paragraphes / puces / gras),
 * slug, image, codes (liste "|" de codes de referentiel), noms (liste "|"),
 * urls (liste "|").
 *
 * @return array Tableau [nom canonique => definition].
 */
function ditl_import_schema_fiches() {
	$colonnes = array(
		'id'                   => array(
			'alias'       => array( 'identifiant' ),
			'type'        => 'entier',
			'obligatoire' => true,
		),
		'group_id'             => array(
			'alias'       => array( 'groupe', 'group', 'id_groupe', 'groupid' ),
			'type'        => 'entier',
			'obligatoire' => true,
		),
		'lang'                 => array(
			'alias'       => array( 'langue', 'language' ),
			'type'        => 'langue',
			'obligatoire' => true,
		),
		'statut'               => array(
			'alias' => array( 'status' ),
			'type'  => 'statut',
		),
		'title'                => array(
			'alias'       => array( 'titre' ),
			'type'        => 'texte',
			'obligatoire' => true,
			'cible'       => array( 'post', 'post_title' ),
		),
		'slug'                 => array(
			'alias' => array( 'permalien' ),
			'type'  => 'slug',
			'cible' => array( 'post', 'post_name' ),
		),
		'image'                => array(
			'alias' => array( 'illustration', 'fichier_image' ),
			'type'  => 'image',
			'cible' => array( 'image' ),
		),
		'image_alt'            => array(
			'alias' => array( 'alt', 'texte_alternatif', 'alternative' ),
			'type'  => 'texte',
			'cible' => array( 'meta', '_ditl_image_alt' ),
		),
		'type_compensation'    => array(
			'alias' => array( 'type_de_compensation', 'types_compensation', 'types_de_compensation' ),
			'type'  => 'codes',
			'cible' => array( 'taxonomie', 'ditl_type_compensation', 1 ),
		),
		'sous_type'            => array(
			'alias' => array( 'sous_types', 'soustype' ),
			'type'  => 'codes',
			'cible' => array( 'taxonomie', 'ditl_sous_type', 1 ),
		),
		'difficulte'           => array(
			'alias' => array( 'difficultes' ),
			'type'  => 'codes',
			'cible' => array( 'taxonomie', 'ditl_aptitude', 1 ),
		),
		'empechement'          => array(
			'alias' => array( 'empechements' ),
			'type'  => 'codes',
			'cible' => array( 'taxonomie', 'ditl_aptitude', 2 ),
		),
		'aptitude_texte'       => array(
			'alias' => array( 'aptitudes', 'aptitude' ),
			'type'  => 'texte_long',
			'cible' => array( 'meta', '_ditl_aptitude_texte' ),
		),
		'metier'               => array(
			'alias' => array( 'metiers' ),
			'type'  => 'codes',
			'cible' => array( 'taxonomie', 'ditl_metier', 1 ),
		),
		'tache'                => array(
			'alias' => array( 'taches' ),
			'type'  => 'codes',
			'cible' => array( 'taxonomie', 'ditl_metier', 2 ),
		),
		'environnement'        => array(
			'alias' => array( 'environnements' ),
			'type'  => 'codes',
			'cible' => array( 'taxonomie', 'ditl_environnement', 1 ),
		),
		'apport'               => array(
			'alias'   => array( 'apport_de_la_compensation', 'apports' ),
			'type'    => 'texte_riche',
			'cible'   => array( 'post', 'post_content' ),
			'minimum' => 'apport_min',
		),
		'descriptif_technique' => array(
			'alias' => array( 'descriptif', 'description_technique' ),
			'type'  => 'texte_riche',
			'cible' => array( 'meta', '_ditl_descriptif_technique' ),
		),
		'points_vigilance'     => array(
			'alias'   => array( 'points_de_vigilance', 'vigilance' ),
			'type'    => 'texte_riche',
			'cible'   => array( 'meta', '_ditl_points_vigilance' ),
			'minimum' => 'vigilance_min',
		),
		'fabricants'           => array(
			'alias' => array(),
			'type'  => 'noms',
			'cible' => array( 'fabricants' ),
		),
		'sources'              => array(
			'alias' => array( 'source', 'liens' ),
			'type'  => 'urls',
			'cible' => array( 'meta', '_ditl_sources' ),
		),
	);

	/**
	 * Filtre le registre des colonnes du fichier des fiches.
	 *
	 * @param array $colonnes Tableau [nom canonique => definition].
	 */
	$colonnes = apply_filters( 'ditl_import_schema_fiches', $colonnes );

	return is_array( $colonnes ) ? $colonnes : array();
}

/**
 * Colonnes fixes du fichier des referentiels. Les colonnes de libelles
 * (une par langue active : fr, en, de, es, pt) s'y ajoutent.
 *
 * @return array Tableau [nom canonique => definition].
 */
function ditl_import_schema_referentiels() {
	$colonnes = array(
		'taxonomie' => array(
			'alias'       => array( 'liste', 'taxonomy', 'referentiel' ),
			'type'        => 'texte',
			'obligatoire' => true,
		),
		'code'      => array(
			'alias'       => array(),
			'type'        => 'texte',
			'obligatoire' => true,
		),
		'parent'    => array(
			'alias' => array( 'code_parent' ),
			'type'  => 'texte',
		),
		'couleur'   => array(
			'alias' => array( 'color' ),
			'type'  => 'texte',
		),
		'ordre'     => array(
			'alias' => array( 'order', 'rang' ),
			'type'  => 'texte',
		),
	);

	foreach ( ditl_ml_langues() as $langue ) {
		$colonnes[ $langue ] = array(
			'alias'   => array( 'libelle_' . $langue ),
			'type'    => 'texte',
			'libelle' => true,
		);
	}

	return $colonnes;
}

/**
 * Valeurs acceptees dans la colonne "taxonomie" des referentiels.
 *
 * Niveau 1 = terme sans parent, niveau 2 = terme rattache a un parent,
 * 0 = deduit de la colonne parent (nom technique de taxonomie).
 *
 * @return array Tableau [valeur normalisee => array( taxonomie, niveau )].
 */
function ditl_import_listes_referentiels() {
	$listes = array(
		'type_compensation' => array( 'ditl_type_compensation', 1 ),
		'sous_type'         => array( 'ditl_sous_type', 1 ),
		'difficulte'        => array( 'ditl_aptitude', 1 ),
		'empechement'       => array( 'ditl_aptitude', 2 ),
		'metier'            => array( 'ditl_metier', 1 ),
		'tache'             => array( 'ditl_metier', 2 ),
		'environnement'     => array( 'ditl_environnement', 1 ),
	);

	// Pluriels et variantes usuelles.
	$variantes = array(
		'types_compensation'    => 'type_compensation',
		'type_de_compensation'  => 'type_compensation',
		'types_de_compensation' => 'type_compensation',
		'sous_types'            => 'sous_type',
		'difficultes'           => 'difficulte',
		'empechements'          => 'empechement',
		'metiers'               => 'metier',
		'taches'                => 'tache',
		'environnements'        => 'environnement',
	);

	foreach ( $variantes as $variante => $canonique ) {
		$listes[ $variante ] = $listes[ $canonique ];
	}

	// Noms techniques des taxonomies : niveau deduit de la colonne parent.
	foreach ( ditl_import_taxonomies() as $taxonomie ) {
		$listes[ $taxonomie ] = array( $taxonomie, 0 );
	}

	return $listes;
}

/**
 * Nom court d'une liste de referentiel (export) a partir d'une taxonomie et
 * d'un niveau.
 *
 * @param string $taxonomie Taxonomie.
 * @param int    $niveau    1 ou 2.
 * @return string Nom court (ex. "empechement").
 */
function ditl_import_nom_liste( $taxonomie, $niveau ) {
	foreach ( ditl_import_listes_referentiels() as $nom => $cible ) {
		if ( $cible[0] === $taxonomie && (int) $cible[1] === (int) $niveau ) {
			return $nom;
		}
	}

	return $taxonomie;
}

/**
 * Associe les en-tetes normalises d'un fichier aux colonnes d'un schema.
 *
 * @param string[] $entetes En-tetes normalises (index = colonne).
 * @param array    $schema  Schema (ditl_import_schema_fiches ou _referentiels).
 * @param bool     $fiches  True pour reconnaitre les colonnes fabricant_N.
 * @param int      $max_fab Plafond de N pour fabricant_N.
 * @return array Tableau {
 *     presentes  : array [nom canonique => index de colonne],
 *     fabricants : array [N => { nom => index|null, url => index|null }],
 *     inconnues  : string[] en-tetes non reconnus,
 *     doublons   : string[] colonnes presentes plusieurs fois,
 *     manquantes : string[] colonnes obligatoires absentes.
 * }
 */
function ditl_import_mapper_entetes( $entetes, $schema, $fiches, $max_fab = 100 ) {
	$correspondance = array();

	foreach ( $schema as $nom => $definition ) {
		$correspondance[ $nom ] = $nom;

		foreach ( isset( $definition['alias'] ) ? (array) $definition['alias'] : array() as $alias ) {
			$correspondance[ (string) $alias ] = $nom;
		}
	}

	$resultat = array(
		'presentes'  => array(),
		'fabricants' => array(),
		'inconnues'  => array(),
		'doublons'   => array(),
		'manquantes' => array(),
	);

	foreach ( $entetes as $index => $entete ) {
		if ( '' === $entete ) {
			continue;
		}

		if ( isset( $correspondance[ $entete ] ) ) {
			$nom = $correspondance[ $entete ];

			if ( isset( $resultat['presentes'][ $nom ] ) ) {
				$resultat['doublons'][] = $nom;
			} else {
				$resultat['presentes'][ $nom ] = $index;
			}
			continue;
		}

		if ( $fiches && preg_match( '/^fabricant_?(\d{1,3})(_url|_lien|_site)?$/', $entete, $m ) ) {
			$numero = (int) $m[1];

			if ( $numero >= 1 && $numero <= $max_fab ) {
				$partie = empty( $m[2] ) ? 'nom' : 'url';

				if ( isset( $resultat['fabricants'][ $numero ][ $partie ] ) ) {
					$resultat['doublons'][] = $entete;
				} else {
					$resultat['fabricants'][ $numero ][ $partie ] = $index;
				}
				continue;
			}
		}

		$resultat['inconnues'][] = $entete;
	}

	ksort( $resultat['fabricants'] );

	foreach ( $schema as $nom => $definition ) {
		if ( ! empty( $definition['obligatoire'] ) && ! isset( $resultat['presentes'][ $nom ] ) ) {
			$resultat['manquantes'][] = $nom;
		}
	}

	$resultat['doublons'] = array_values( array_unique( $resultat['doublons'] ) );

	return $resultat;
}

/**
 * Metas COMMUNES a toutes les langues d'une fiche (contrat du modele :
 * action "copy" de wpml-config.xml, appliquee aussi par Polylang qui
 * synchronise ces metas entre traductions).
 *
 * L'import les traite au niveau du groupe : la valeur de la ligne de la
 * langue source (langue par defaut du site, sinon fr, sinon la premiere
 * ligne valide) s'applique a toutes les lignes du groupe.
 *
 * @return string[] Cles de metas.
 */
function ditl_import_metas_communes() {
	$metas = apply_filters( 'ditl_import_metas_communes', array( '_ditl_fabricants', '_ditl_sources' ) );

	return is_array( $metas ) ? array_values( array_filter( $metas, 'is_string' ) ) : array();
}

/**
 * En-tetes canoniques du modele de fichier de fiches (telechargement).
 *
 * @param int $fabricants Nombre de paires fabricant_N / fabricant_N_url.
 * @return string[] En-tetes dans l'ordre du modele du 07/09.
 */
function ditl_import_entetes_modele_fiches( $fabricants = 6 ) {
	$entetes = array(
		'id',
		'group_id',
		'lang',
		'statut',
		'title',
		'slug',
		'image',
		'image_alt',
		'type_compensation',
		'sous_type',
		'difficulte',
		'empechement',
		'aptitude_texte',
		'metier',
		'tache',
		'environnement',
		'apport',
		'descriptif_technique',
		'points_vigilance',
	);

	for ( $numero = 1; $numero <= $fabricants; $numero++ ) {
		$entetes[] = 'fabricant_' . $numero;
		$entetes[] = 'fabricant_' . $numero . '_url';
	}

	$entetes[] = 'sources';

	return $entetes;
}
