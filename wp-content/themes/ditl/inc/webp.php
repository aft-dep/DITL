<?php
/**
 * Jumeaux WebP des images : coeur de conversion partage + hooks medias.
 *
 * Mecanique unique, partagee entre :
 * - le script WP-CLI cli/optimiser-images.php (traitement du stock existant
 *   d'uploads + pose du bloc .htaccess de service conditionnel) ;
 * - les hooks de ce fichier, qui couvrent le fil de l'eau : tout nouvel
 *   upload (original + toutes les tailles intermediaires) recoit ses
 *   jumeaux .webp, et toute suppression physique d'une image (suppression
 *   d'attachement, regeneration de vignettes, editeur d'images) emporte
 *   son jumeau - aucun orphelin.
 *
 * Regles communes (identiques au lot initial) :
 * - outil : binaire cwebp si disponible, sinon GD (imagewebp) ; si aucun
 *   outil, les hooks s'effacent silencieusement (un upload ne doit JAMAIS
 *   echouer a cause du WebP), le script CLI, lui, s'arrete en erreur ;
 * - qualite 82, transparence des PNG preservee, EXIF retire (cwebp) ;
 * - jumeau nomme "fichier.ext.webp", conserve UNIQUEMENT s'il est plus
 *   petit que l'original ; sinon supprime et memorise dans l'option
 *   ditl_webp_ignores (non autoload, chemins relatifs a uploads) pour ne
 *   pas etre reconverti tant que le fichier ne change pas ;
 * - seuls jpg / jpeg / png sont traites.
 *
 * Cout front : nul. Ce fichier ne definit que des fonctions et n'accroche
 * que des hooks de la chaine des medias (wp_generate_attachment_metadata,
 * wp_delete_file), qui ne se declenchent jamais lors du rendu public
 * (uniquement admin, async-upload, REST, WP-CLI, cron).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Qualite WebP appliquee a toutes les conversions (photos : bon compromis
 * poids / fidelite visuelle).
 */
define( 'DITL_WEBP_QUALITE', 82 );

/**
 * Extensions d'images traitees (minuscules). Tout le reste est ignore.
 *
 * @return string[] Extensions traitees.
 */
function ditl_webp_extensions() {
	return array( 'jpg', 'jpeg', 'png' );
}

/**
 * Detecte une fois l'outil de conversion disponible.
 *
 * Prefere le binaire cwebp (meilleure compression, EXIF retire), se replie
 * sur GD (imagewebp). Resultat mis en cache pour la duree de la requete.
 *
 * @return array Tableau { outil => "cwebp"|"gd"|"", cwebp => chemin du binaire }.
 */
function ditl_webp_outil() {
	static $ditl_detection = null;

	if ( null !== $ditl_detection ) {
		return $ditl_detection;
	}

	$ditl_detection = array(
		'outil' => '',
		'cwebp' => '',
	);

	if ( function_exists( 'exec' ) ) {
		$ditl_sortie = array();
		$ditl_code   = 1;
		@exec( 'command -v cwebp 2>/dev/null', $ditl_sortie, $ditl_code );

		if ( 0 === $ditl_code && ! empty( $ditl_sortie[0] ) ) {
			$ditl_detection['outil'] = 'cwebp';
			$ditl_detection['cwebp'] = trim( $ditl_sortie[0] );
		}
	}

	if ( '' === $ditl_detection['outil'] && function_exists( 'imagewebp' ) ) {
		$ditl_detection['outil'] = 'gd';
	}

	return $ditl_detection;
}

/**
 * Chemin d'un fichier relatif au dossier uploads (cle de l'option
 * ditl_webp_ignores, portable entre environnements).
 *
 * @param string $chemin Chemin absolu du fichier.
 * @return string Chemin relatif a uploads (ou absolu s'il n'en fait pas partie).
 */
function ditl_webp_chemin_relatif( $chemin ) {
	$ditl_uploads = wp_get_upload_dir();
	$ditl_racine  = isset( $ditl_uploads['basedir'] ) ? rtrim( (string) $ditl_uploads['basedir'], '/' ) : '';

	if ( '' !== $ditl_racine && 0 === strpos( $chemin, $ditl_racine ) ) {
		return substr( $chemin, strlen( $ditl_racine ) );
	}

	return $chemin;
}

/**
 * Conversion brute d'une image en WebP (sans regle de conservation).
 *
 * @param string $source Chemin de l'image source.
 * @param string $cible  Chemin du .webp a produire.
 * @return bool True si le fichier cible a ete produit.
 */
function ditl_webp_convertir_fichier( $source, $cible ) {
	$ditl_outil = ditl_webp_outil();

	if ( 'cwebp' === $ditl_outil['outil'] ) {
		// -metadata none : pas d'EXIF dans le fichier servi ;
		// -quiet : les rapports sont portes par les appelants.
		// La transparence PNG est preservee par defaut par cwebp.
		$ditl_commande = sprintf(
			'%s -q %d -metadata none -quiet %s -o %s 2>/dev/null',
			escapeshellcmd( $ditl_outil['cwebp'] ),
			DITL_WEBP_QUALITE,
			escapeshellarg( $source ),
			escapeshellarg( $cible )
		);

		$ditl_sortie = array();
		$ditl_code   = 1;
		exec( $ditl_commande, $ditl_sortie, $ditl_code );

		return 0 === $ditl_code && file_exists( $cible );
	}

	if ( 'gd' !== $ditl_outil['outil'] ) {
		return false;
	}

	// GD : chargement selon le type reel du fichier.
	$ditl_type = wp_check_filetype( $source );
	$ditl_mime = isset( $ditl_type['type'] ) ? $ditl_type['type'] : '';

	if ( 'image/png' === $ditl_mime ) {
		$ditl_image = @imagecreatefrompng( $source );

		if ( ! $ditl_image ) {
			return false;
		}

		// Preservation de la transparence des PNG (palette comprise).
		imagepalettetotruecolor( $ditl_image );
		imagealphablending( $ditl_image, false );
		imagesavealpha( $ditl_image, true );
	} else {
		$ditl_image = @imagecreatefromjpeg( $source );

		if ( ! $ditl_image ) {
			return false;
		}
	}

	$ditl_resultat = @imagewebp( $ditl_image, $cible, DITL_WEBP_QUALITE );
	imagedestroy( $ditl_image );

	return $ditl_resultat && file_exists( $cible );
}

/**
 * Genere (si utile) le jumeau .webp d'une image, regles completes.
 *
 * Sequence : extension traitee -> jumeau deja a jour -> memoire des
 * refuses -> conversion -> conservation seulement si plus petit (sinon
 * suppression + memorisation dans ditl_webp_ignores).
 *
 * @param string $chemin  Chemin absolu de l'image source.
 * @param bool   $simuler True pour ne rien ecrire (mode dry-run du CLI) :
 *                        retourne "converti" pour tout candidat effectif.
 * @return string Statut : "converti", "a_jour", "memoire" (refus memorise),
 *                "ignore" (plus lourd, jumeau supprime), "echec",
 *                "inutilisable" (extension ou fichier hors perimetre).
 */
function ditl_webp_generer_jumeau( $chemin, $simuler = false ) {
	$ditl_extension = strtolower( (string) pathinfo( $chemin, PATHINFO_EXTENSION ) );

	if ( ! in_array( $ditl_extension, ditl_webp_extensions(), true ) || ! is_file( $chemin ) ) {
		return 'inutilisable';
	}

	$ditl_cible = $chemin . '.webp';

	if ( file_exists( $ditl_cible ) && filemtime( $ditl_cible ) >= filemtime( $chemin ) ) {
		return 'a_jour';
	}

	// Memoire des originaux dont le WebP s'est revele plus lourd : ignores
	// tant que leur mtime n'a pas change (rejeu sans aucune ecriture).
	$ditl_relatif = ditl_webp_chemin_relatif( $chemin );
	$ditl_memoire = get_option( 'ditl_webp_ignores', array() );
	$ditl_memoire = is_array( $ditl_memoire ) ? $ditl_memoire : array();

	if ( isset( $ditl_memoire[ $ditl_relatif ] ) && (int) $ditl_memoire[ $ditl_relatif ] === (int) filemtime( $chemin ) ) {
		return 'memoire';
	}

	if ( $simuler ) {
		return 'converti';
	}

	if ( ! ditl_webp_convertir_fichier( $chemin, $ditl_cible ) ) {
		if ( file_exists( $ditl_cible ) ) {
			@unlink( $ditl_cible );
		}

		return 'echec';
	}

	if ( (int) filesize( $ditl_cible ) >= (int) filesize( $chemin ) ) {
		// Plus lourd que l'original : aucun interet, on le retire et on le
		// memorise pour ne pas le reconvertir au rejeu.
		@unlink( $ditl_cible );
		$ditl_memoire[ $ditl_relatif ] = (int) filemtime( $chemin );
		update_option( 'ditl_webp_ignores', $ditl_memoire, false );

		return 'ignore';
	}

	return 'converti';
}

/**
 * Genere les jumeaux .webp d'un attachement fraichement (re)traite.
 *
 * Accroche en FILTRE tardif sur wp_generate_attachment_metadata : couvre
 * l'upload initial, la regeneration de vignettes et l'editeur d'images.
 * Convertit le fichier principal, l'original pre-redimensionnement
 * (cle original_image des images "-scaled") et toutes les tailles
 * intermediaires (ce sont elles que servent les srcset).
 *
 * Toujours silencieux : un echec de conversion (outil absent, image
 * illisible...) ne doit JAMAIS bloquer un upload. Les metadonnees sont
 * retournees strictement inchangees.
 *
 * @param array $metadata      Metadonnees generees par WordPress.
 * @param int   $attachment_id ID de l'attachement (non utilise, signature du filtre).
 * @return array Metadonnees inchangees.
 */
function ditl_webp_hook_generation( $metadata, $attachment_id ) {
	if ( ! is_array( $metadata ) || empty( $metadata['file'] ) ) {
		return $metadata;
	}

	$ditl_uploads = wp_get_upload_dir();
	$ditl_racine  = isset( $ditl_uploads['basedir'] ) ? rtrim( (string) $ditl_uploads['basedir'], '/' ) : '';

	if ( '' === $ditl_racine ) {
		return $metadata;
	}

	$ditl_fichier = $ditl_racine . '/' . ltrim( (string) $metadata['file'], '/' );
	$ditl_dossier = dirname( $ditl_fichier );

	$ditl_cibles = array( $ditl_fichier );

	// Original pre-redimensionnement des images "-scaled" (grands uploads).
	if ( ! empty( $metadata['original_image'] ) ) {
		$ditl_cibles[] = $ditl_dossier . '/' . (string) $metadata['original_image'];
	}

	// Toutes les tailles intermediaires (servies par les srcset).
	if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
		foreach ( $metadata['sizes'] as $ditl_taille ) {
			if ( ! empty( $ditl_taille['file'] ) ) {
				$ditl_cibles[] = $ditl_dossier . '/' . (string) $ditl_taille['file'];
			}
		}
	}

	foreach ( array_unique( $ditl_cibles ) as $ditl_cible ) {
		ditl_webp_generer_jumeau( $ditl_cible );
	}

	return $metadata;
}
// Priorite tardive : apres les autres manipulations de metadonnees
// (regeneration, optimiseurs eventuels), sur les fichiers definitifs.
add_filter( 'wp_generate_attachment_metadata', 'ditl_webp_hook_generation', 9999, 2 );

/**
 * Supprime le jumeau .webp de toute image physiquement effacee.
 *
 * Accroche sur le filtre wp_delete_file, par lequel WordPress passe pour
 * CHAQUE suppression physique de fichier media : suppression d'un
 * attachement (original + toutes les tailles), remplacement des anciennes
 * tailles lors d'une regeneration de vignettes, fichiers intermediaires de
 * l'editeur d'images. Couvre donc structurellement tous les cas d'orphelins,
 * la ou un hook delete_attachment ne verrait que la suppression complete.
 *
 * L'entree correspondante de la memoire ditl_webp_ignores est purgee au
 * passage (sinon un futur fichier homonyme au meme mtime serait ignore).
 *
 * @param string $fichier Chemin du fichier que WordPress va supprimer.
 * @return string Chemin inchange (filtre passe-plat).
 */
function ditl_webp_hook_suppression( $fichier ) {
	if ( ! is_string( $fichier ) || '' === $fichier ) {
		return $fichier;
	}

	$ditl_extension = strtolower( (string) pathinfo( $fichier, PATHINFO_EXTENSION ) );

	if ( ! in_array( $ditl_extension, ditl_webp_extensions(), true ) ) {
		return $fichier;
	}

	if ( file_exists( $fichier . '.webp' ) ) {
		@unlink( $fichier . '.webp' );
	}

	$ditl_relatif = ditl_webp_chemin_relatif( $fichier );
	$ditl_memoire = get_option( 'ditl_webp_ignores', array() );

	if ( is_array( $ditl_memoire ) && isset( $ditl_memoire[ $ditl_relatif ] ) ) {
		unset( $ditl_memoire[ $ditl_relatif ] );
		update_option( 'ditl_webp_ignores', $ditl_memoire, false );
	}

	return $fichier;
}
add_filter( 'wp_delete_file', 'ditl_webp_hook_suppression' );
