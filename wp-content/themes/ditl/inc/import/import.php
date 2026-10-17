<?php
/**
 * Import des fiches outils par CSV (phase 4) : point d'entree.
 *
 * Le moteur consomme le modele de donnees de la boite a outils (phase 3,
 * inc/boite/) : type de publication DITL_RESSOURCE_POST_TYPE, taxonomies a
 * niveaux, metas du contrat, fonction ditl_boite_terme_par_code() et
 * adaptateur multilingue ditl_ml_*. Il ne declare ni type de contenu, ni
 * taxonomie.
 *
 * Organisation :
 * - csv.php        : lecture robuste d'un fichier CSV (BOM, separateur,
 *                    encodage, cellules multi-lignes, limites) ;
 * - schema.php     : registre des colonnes (mapping colonnes -> modele),
 *                    alias, schema des referentiels ;
 * - texte.php      : conversions (texte riche, codes, URL, statut,
 *                    neutralisation des formules de tableur) ;
 * - images.php     : resolution des images (dossier, mediatheque),
 *                    televersement, extraction sure d'une archive zip ;
 * - validation.php : preparation d'un import (lecture + validation
 *                    complete, sans aucune ecriture) ;
 * - ecriture.php   : execution (simulation ou ecriture, par lots),
 *                    empreintes, liaison des traductions, purge ;
 * - travail.php    : dossier de travail, verrous, execution d'un lot ;
 * - admin.php      : ecran d'administration (charge en admin seulement).
 *
 * Point d'entree WP-CLI : cli/importer-fiches.php (meme moteur).
 *
 * Metas techniques posees par l'import (jamais copiees vers une traduction,
 * declarees "ignore" dans wpml-config.xml) :
 * - fiches : _ditl_import_id, _ditl_import_groupe, _ditl_import_langue,
 *   _ditl_import_empreinte, _ditl_import_etat, _ditl_import_date ;
 * - medias : _ditl_import_image, _ditl_import_image_sha1 ;
 * - termes : _ditl_import_terme (terme cree ou mis a jour par l'import,
 *   seul concerne par le mode annuler des referentiels).
 * Hors ecriture par l'import, les metas _ditl_import_* d'une fiche sont
 * protegees (ex. extension Duplicate Page qui recopie toutes les metas : une
 * copie d'une fiche ne doit pas reprendre son id d'import).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Version du format des empreintes : l'incrementer force la reecriture de
// toutes les fiches au prochain import (changement de conversion du texte).
define( 'DITL_IMPORT_VERSION', '1' );

/**
 * Temoin de chargement du moteur d'import.
 *
 * @return bool Toujours true.
 */
function ditl_import_charge() {
	return true;
}

/**
 * Type de publication des fiches outils (constante du modele).
 *
 * @return string Nom du type de publication.
 */
function ditl_import_type_fiche() {
	return defined( 'DITL_RESSOURCE_POST_TYPE' ) ? (string) DITL_RESSOURCE_POST_TYPE : 'ditl_ressource';
}

/**
 * Regles et seuils de l'import (filtrables sans toucher au code).
 *
 * Les minimums de longueur et le nombre de mots du titre viennent du devis
 * et restent des AVERTISSEMENTS (a trancher par le client).
 *
 * @return array Regles.
 */
function ditl_import_regles() {
	$defaut = array(
		'apport_min'       => 700,
		'vigilance_min'    => 600,
		'titre_mots_min'   => 2,
		'titre_mots_max'   => 5,
		'taille_max'       => 20 * MB_IN_BYTES,
		'lignes_max'       => 5000,
		'cellules_max'     => 500000,
		'image_taille_max' => 15 * MB_IN_BYTES,
		'pixels_max'       => 50000000,
		'zip_entrees_max'  => 2000,
		'zip_total_max'    => 500 * MB_IN_BYTES,
		'fabricants_max'   => 100,
		'budget'           => 15,
	);

	$regles = apply_filters( 'ditl_import_regles', $defaut );

	if ( ! is_array( $regles ) ) {
		return $defaut;
	}

	// Valeurs numeriques uniquement, valeur par defaut sinon.
	foreach ( $defaut as $cle => $valeur ) {
		$regles[ $cle ] = isset( $regles[ $cle ] ) && is_numeric( $regles[ $cle ] ) ? (int) $regles[ $cle ] : $valeur;
	}

	return $regles;
}

/**
 * Verifie la presence du modele de donnees de la boite a outils.
 *
 * @return string Message d'erreur fatale, chaine vide si le modele est la.
 */
function ditl_import_verifier_modele() {
	// Toutes les fonctions du modele appelees par l'import.
	$fonctions = array(
		'ditl_boite_terme_par_code',
		'ditl_boite_code_terme',
		'ditl_boite_sanitize_code',
		'ditl_boite_sanitize_couleur',
		'ditl_boite_sanitize_ordre',
		'ditl_boite_taxonomies',
		'ditl_ml_extension',
		'ditl_ml_langues',
		'ditl_ml_langue_defaut',
		'ditl_ml_langue_valide',
		'ditl_ml_definir_langue_post',
		'ditl_ml_langue_post',
		'ditl_ml_traductions_post',
		'ditl_ml_lier_traductions_posts',
		'ditl_ml_definir_langue_terme',
		'ditl_ml_langue_terme',
		'ditl_ml_traductions_terme',
		'ditl_ml_lier_traductions_termes',
	);

	foreach ( $fonctions as $fonction ) {
		if ( ! function_exists( $fonction ) ) {
			return sprintf( 'Modèle de données de la boîte à outils absent (fonction %s introuvable).', $fonction );
		}
	}

	if ( ! defined( 'DITL_RESSOURCE_POST_TYPE' ) || ! post_type_exists( ditl_import_type_fiche() ) ) {
		return 'Modèle de données de la boîte à outils absent (type de publication des fiches non déclaré).';
	}

	foreach ( ditl_import_taxonomies() as $taxonomie ) {
		if ( ! taxonomy_exists( $taxonomie ) ) {
			return sprintf( 'Modèle de données de la boîte à outils absent (taxonomie %s non déclarée).', $taxonomie );
		}
	}

	if ( array() === ditl_ml_langues() ) {
		return 'Aucune langue active sur le site.';
	}

	return '';
}

/**
 * Taxonomies de la boite a outils alimentees par l'import.
 *
 * @return string[] Noms de taxonomies.
 */
function ditl_import_taxonomies() {
	return array( 'ditl_type_compensation', 'ditl_sous_type', 'ditl_aptitude', 'ditl_metier', 'ditl_environnement' );
}

/**
 * Protege les metas techniques _ditl_import_* d'une fiche : hors ecriture
 * par l'import, toute creation ou mise a jour est refusee (ex. extension
 * Duplicate Page, qui recopie toutes les metas sur la copie d'une fiche :
 * la copie reprendrait l'id d'import et ferait rejeter la ligne).
 *
 * @param mixed  $verification Valeur courante du court-circuit (null).
 * @param int    $object_id    ID de la publication.
 * @param string $meta_key     Cle de la meta.
 * @return mixed false pour refuser l'ecriture, sinon $verification.
 */
function ditl_import_garde_metas( $verification, $object_id, $meta_key ) {
	if ( null !== $verification || ! is_string( $meta_key ) || 0 !== strpos( $meta_key, '_ditl_import_' ) || ! empty( $GLOBALS['ditl_import_ecriture'] ) ) {
		return $verification;
	}

	return ditl_import_type_fiche() === get_post_type( (int) $object_id ) ? false : $verification;
}
add_filter( 'add_post_metadata', 'ditl_import_garde_metas', 10, 3 );
add_filter( 'update_post_metadata', 'ditl_import_garde_metas', 10, 3 );

/**
 * Cache memoire d'une requete d'import (resolutions de codes, d'images...).
 *
 * @param string $groupe Groupe de cache.
 * @param string $cle    Cle dans le groupe.
 * @param mixed  $defaut Valeur si absente.
 * @return mixed Valeur en cache ou $defaut.
 */
function ditl_import_cache_lire( $groupe, $cle, $defaut = null ) {
	return isset( $GLOBALS['ditl_import_cache'][ $groupe ] ) && array_key_exists( $cle, $GLOBALS['ditl_import_cache'][ $groupe ] )
		? $GLOBALS['ditl_import_cache'][ $groupe ][ $cle ]
		: $defaut;
}

/**
 * Ecrit une valeur dans le cache memoire de l'import.
 *
 * @param string $groupe Groupe de cache.
 * @param string $cle    Cle dans le groupe.
 * @param mixed  $valeur Valeur.
 */
function ditl_import_cache_ecrire( $groupe, $cle, $valeur ) {
	$GLOBALS['ditl_import_cache'][ $groupe ][ $cle ] = $valeur;
}

/**
 * Vide le cache memoire de l'import (debut de preparation, tests).
 */
function ditl_import_cache_vider() {
	$GLOBALS['ditl_import_cache'] = array();
}

require_once __DIR__ . '/csv.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/texte.php';
require_once __DIR__ . '/images.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/ecriture.php';
require_once __DIR__ . '/travail.php';

if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
}
