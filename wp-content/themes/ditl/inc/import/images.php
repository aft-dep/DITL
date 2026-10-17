<?php
/**
 * Import des fiches : images (resolution, televersement, archive zip).
 *
 * Ordre de recherche de l'image d'une ligne (colonne "image" = nom de
 * fichier, sans chemin) :
 * 1. dossier d'images du travail (zip envoye dans l'admin, ou
 *    images=<dossier> en WP-CLI) : le fichier est televerse une seule fois,
 *    deduplique par empreinte sha1 (meta _ditl_import_image_sha1) ;
 * 2. mediatheque : media deja importe sous ce nom (meta _ditl_import_image),
 *    puis nom du fichier televerse (_wp_attached_file, variante -scaled
 *    comprise). Le client peut donc deposer ses images par la mediatheque
 *    native puis importer le CSV seul ;
 * 3. sinon : avertissement, l'illustration de la fiche n'est pas modifiee.
 *
 * Securite : un nom contenant un separateur de chemin ou ".." est refuse ;
 * extensions jpg, jpeg, png, webp, gif seulement ; contenu verifie
 * (getimagesize) ; plafonds de poids et de pixels par image ; le
 * televersement passe par media_handle_sideload sur une COPIE (le fichier
 * source n'est jamais deplace), metas de l'import posees a la creation du
 * media. En simulation, rien n'est televerse.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extensions d'images acceptees.
 *
 * @return string[] Extensions en minuscules.
 */
function ditl_import_extensions_images() {
	return array( 'jpg', 'jpeg', 'png', 'webp', 'gif' );
}

/**
 * Cle de comparaison d'un nom de fichier (insensible a la casse, nettoyee
 * comme le fait WordPress au televersement).
 *
 * @param string $nom Nom de fichier.
 * @return string Cle.
 */
function ditl_import_cle_image( $nom ) {
	return strtolower( sanitize_file_name( (string) $nom ) );
}

/**
 * Controle le nom de fichier saisi dans la colonne image.
 *
 * @param string $nom Nom saisi.
 * @return string Message d'avertissement, chaine vide si le nom est valide.
 */
function ditl_import_controler_nom_image( $nom ) {
	if ( preg_match( '#[/\\\\]#', $nom ) || false !== strpos( $nom, '..' ) || false !== strpos( $nom, "\0" ) ) {
		return 'nom refusé (chemin interdit : indiquer seulement le nom du fichier)';
	}

	if ( '.' === substr( $nom, 0, 1 ) ) {
		return 'nom refusé (fichier caché)';
	}

	$extension = strtolower( (string) pathinfo( $nom, PATHINFO_EXTENSION ) );

	if ( ! in_array( $extension, ditl_import_extensions_images(), true ) ) {
		return sprintf( 'extension non acceptée (%s)', implode( ', ', ditl_import_extensions_images() ) );
	}

	return '';
}

/**
 * Index des images d'un dossier (non recursif) : cle de nom => chemin.
 *
 * @param string $dossier Dossier d'images (chemin absolu), vide = aucun.
 * @return array Tableau [cle => chemin absolu].
 */
function ditl_import_index_images( $dossier ) {
	$dossier = (string) $dossier;

	if ( '' === $dossier ) {
		return array();
	}

	$index = ditl_import_cache_lire( 'index_images', $dossier );

	if ( is_array( $index ) ) {
		return $index;
	}

	$index = array();
	$reel  = realpath( $dossier );

	if ( false !== $reel && is_dir( $reel ) ) {
		$fichiers = scandir( $reel );

		foreach ( is_array( $fichiers ) ? $fichiers : array() as $fichier ) {
			$chemin = $reel . DIRECTORY_SEPARATOR . $fichier;

			if ( '.' === substr( $fichier, 0, 1 ) || is_link( $chemin ) || ! is_file( $chemin ) || '' !== ditl_import_controler_nom_image( $fichier ) ) {
				continue;
			}

			$cle = ditl_import_cle_image( $fichier );

			if ( ! isset( $index[ $cle ] ) ) {
				$index[ $cle ] = $chemin;
			}
		}
	}

	ditl_import_cache_ecrire( 'index_images', $dossier, $index );

	return $index;
}

/**
 * Empreinte sha1 d'un fichier (memorisee pour la requete).
 *
 * @param string $chemin Chemin absolu.
 * @return string Empreinte, chaine vide si illisible.
 */
function ditl_import_sha1_fichier( $chemin ) {
	$cle    = $chemin . '|' . (string) @filemtime( $chemin ) . '|' . (string) @filesize( $chemin ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- fichier absent gere par sha1_file.
	$valeur = ditl_import_cache_lire( 'sha1', $cle );

	if ( is_string( $valeur ) ) {
		return $valeur;
	}

	$valeur = is_readable( $chemin ) ? (string) sha1_file( $chemin ) : '';
	ditl_import_cache_ecrire( 'sha1', $cle, $valeur );

	return $valeur;
}

/**
 * Resout l'image d'une ligne (sans aucune ecriture).
 *
 * @param string $nom     Nom de fichier saisi (deja controle).
 * @param string $dossier Dossier d'images du travail (vide = aucun).
 * @param array  $regles  Regles de l'import.
 * @return array Tableau { source : dossier|mediatheque|"", chemin, sha1,
 *               attachment_id, message }.
 */
function ditl_import_resoudre_image( $nom, $dossier, $regles ) {
	$resultat = array(
		'source'        => '',
		'chemin'        => '',
		'sha1'          => '',
		'attachment_id' => 0,
		'message'       => '',
	);

	$index = ditl_import_index_images( $dossier );
	$cle   = ditl_import_cle_image( $nom );

	if ( isset( $index[ $cle ] ) ) {
		$chemin = $index[ $cle ];
		$taille = (int) filesize( $chemin );

		if ( $taille > $regles['image_taille_max'] ) {
			$resultat['message'] = sprintf( 'image « %1$s » trop lourde (%2$s, maximum %3$s) : illustration non modifiée', $nom, size_format( $taille ), size_format( $regles['image_taille_max'] ) );
			return $resultat;
		}

		$refus = ditl_import_controler_image( $chemin, $regles );

		if ( '' !== $refus ) {
			$resultat['message'] = sprintf( 'fichier « %1$s » : %2$s : illustration non modifiée', $nom, $refus );
			return $resultat;
		}

		$resultat['source'] = 'dossier';
		$resultat['chemin'] = $chemin;
		$resultat['sha1']   = ditl_import_sha1_fichier( $chemin );

		return $resultat;
	}

	$attachment_id = ditl_import_media_par_nom( $nom );

	if ( $attachment_id > 0 ) {
		$resultat['source']        = 'mediatheque';
		$resultat['attachment_id'] = $attachment_id;

		return $resultat;
	}

	$resultat['message'] = sprintf( 'image « %s » introuvable (ni dans les images envoyées, ni dans la médiathèque) : illustration non modifiée', $nom );

	return $resultat;
}

/**
 * Controle qu'un fichier est une image lisible et de dimensions raisonnables
 * (plafond de pixels : protege la memoire lors du calcul des vignettes).
 *
 * @param string $chemin Chemin absolu.
 * @param array  $regles Regles de l'import (pixels_max).
 * @return string Motif du refus, chaine vide si l'image est acceptee.
 */
function ditl_import_controler_image( $chemin, $regles ) {
	$infos = @getimagesize( $chemin ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- fichier non image : false attendu.

	if ( ! is_array( $infos ) || empty( $infos[0] ) || empty( $infos[1] ) ) {
		return 'contenu illisible ou qui n\'est pas une image';
	}

	if ( (float) $infos[0] * (float) $infos[1] > (float) $regles['pixels_max'] ) {
		return sprintf( 'image trop grande (%1$d x %2$d pixels, %3$s millions de pixels maximum)', (int) $infos[0], (int) $infos[1], number_format_i18n( $regles['pixels_max'] / 1000000 ) );
	}

	return '';
}

/**
 * Verifie qu'un fichier est une image acceptee.
 *
 * @param string     $chemin Chemin absolu.
 * @param array|null $regles Regles de l'import (null = regles courantes).
 * @return bool True si l'image est acceptee.
 */
function ditl_import_est_image( $chemin, $regles = null ) {
	return '' === ditl_import_controler_image( $chemin, null === $regles ? ditl_import_regles() : $regles );
}

/**
 * Empreinte sha1 d'un media cree par l'import (meta _ditl_import_image_sha1).
 *
 * @param int $attachment_id ID du media.
 * @return string Empreinte, chaine vide si le media n'en a pas.
 */
function ditl_import_sha1_media( $attachment_id ) {
	return (string) get_post_meta( (int) $attachment_id, '_ditl_import_image_sha1', true );
}

/**
 * Cherche un media de la mediatheque par nom de fichier.
 *
 * Ordre : media deja cree par l'import sous ce nom, puis fichier televerse
 * portant ce nom (ou sa variante -scaled). Le plus ancien gagne (stable
 * d'un import a l'autre, et original avant ses traductions Polylang).
 *
 * @param string $nom Nom de fichier saisi.
 * @return int ID du media, 0 si aucun.
 */
function ditl_import_media_par_nom( $nom ) {
	global $wpdb;

	$cle    = ditl_import_cle_image( $nom );
	$trouve = ditl_import_cache_lire( 'media_nom', $cle );

	if ( null !== $trouve ) {
		return (int) $trouve;
	}

	$id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- recherche ponctuelle, mise en cache pour la requete.
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
			WHERE p.post_type = 'attachment' AND m.meta_key = '_ditl_import_image' AND m.meta_value = %s
			ORDER BY p.ID ASC LIMIT 1",
			$nom
		)
	);

	if ( $id <= 0 ) {
		$candidats = array();

		foreach ( array_unique( array( $nom, sanitize_file_name( $nom ) ) ) as $variante ) {
			$candidats[] = $variante;
			$base        = pathinfo( $variante, PATHINFO_FILENAME );
			$extension   = pathinfo( $variante, PATHINFO_EXTENSION );

			if ( '' !== $extension ) {
				$candidats[] = $base . '-scaled.' . $extension;
			}
		}

		$candidats = array_values( array_unique( $candidats ) );
		$clauses   = array();
		$valeurs   = array();

		foreach ( $candidats as $candidat ) {
			$clauses[] = '(m.meta_value = %s OR m.meta_value LIKE %s)';
			$valeurs[] = $candidat;
			$valeurs[] = '%/' . $wpdb->esc_like( $candidat );
		}

		$lignes = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- recherche ponctuelle, mise en cache pour la requete.
			$wpdb->prepare(
				"SELECT p.ID, m.meta_value FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				WHERE p.post_type = 'attachment' AND m.meta_key = '_wp_attached_file' AND (" . implode( ' OR ', $clauses ) . ')
				ORDER BY p.ID ASC',
				$valeurs
			)
		);

		$cles_candidats = array_map( 'strtolower', $candidats );

		foreach ( (array) $lignes as $ligne ) {
			if ( in_array( strtolower( wp_basename( (string) $ligne->meta_value ) ), $cles_candidats, true ) ) {
				$id = (int) $ligne->ID;
				break;
			}
		}
	}

	ditl_import_cache_ecrire( 'media_nom', $cle, max( 0, $id ) );

	return max( 0, $id );
}

/**
 * Cherche un media cree par l'import a partir de l'empreinte du fichier.
 *
 * Le fichier du media doit toujours exister sur le disque (sinon un nouvel
 * envoi est necessaire, ex. uploads non recopies d'un environnement a
 * l'autre).
 *
 * @param string $sha1 Empreinte sha1.
 * @return int ID du media, 0 si aucun.
 */
function ditl_import_media_par_sha1( $sha1 ) {
	global $wpdb;

	if ( '' === $sha1 ) {
		return 0;
	}

	$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- recherche ponctuelle.
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
			WHERE p.post_type = 'attachment' AND m.meta_key = '_ditl_import_image_sha1' AND m.meta_value = %s
			ORDER BY p.ID ASC",
			$sha1
		)
	);

	foreach ( (array) $ids as $id ) {
		$fichier = get_attached_file( (int) $id );

		if ( is_string( $fichier ) && '' !== $fichier && is_file( $fichier ) ) {
			return (int) $id;
		}
	}

	return 0;
}

/**
 * Televerse une image du dossier de travail dans la mediatheque.
 *
 * @param string $chemin Fichier source (jamais deplace : copie temporaire).
 * @param string $nom    Nom saisi dans le CSV (nom du media).
 * @param string $sha1   Empreinte du fichier.
 * @return int|WP_Error ID du media cree, ou erreur.
 */
function ditl_import_televerser_image( $chemin, $nom, $sha1 ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$copie = wp_tempnam( $nom );

	if ( ! $copie || ! @copy( $chemin, $copie ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- echec gere ci-dessous.
		if ( $copie ) {
			wp_delete_file( $copie );
		}

		return new WP_Error( 'ditl_import_copie', 'copie temporaire impossible' );
	}

	$fichier = array(
		'name'     => sanitize_file_name( $nom ),
		'tmp_name' => $copie,
	);

	// Metas posees a la creation du media (reprise sure apres interruption).
	$attachment_id = media_handle_sideload(
		$fichier,
		0,
		null,
		array(
			'meta_input' => array(
				'_ditl_import_image'      => wp_slash( $nom ),
				'_ditl_import_image_sha1' => $sha1,
			),
		)
	);

	if ( is_wp_error( $attachment_id ) ) {
		if ( is_file( $copie ) ) {
			wp_delete_file( $copie );
		}

		return $attachment_id;
	}

	ditl_import_cache_ecrire( 'media_nom', ditl_import_cle_image( $nom ), (int) $attachment_id );

	return (int) $attachment_id;
}

/**
 * Extrait les images d'une archive zip, sans jamais ecrire hors du dossier
 * de destination.
 *
 * Entree par entree (getStream) : seul le nom de base est conserve, nettoye
 * par sanitize_file_name ; dossiers __MACOSX et fichiers caches (._*)
 * ignores ; extensions d'images seules ; contenu verifie ; plafonds
 * d'entrees, de poids par fichier et de poids total ; un nom deja extrait
 * garde le premier fichier (avertissement).
 *
 * @param string $archive     Chemin de l'archive.
 * @param string $destination Dossier de destination (existant).
 * @param array  $regles      Regles de l'import.
 * @return array Tableau { fatal : string[], messages : array[], extraites : int }.
 */
function ditl_import_extraire_zip( $archive, $destination, $regles ) {
	$resultat = array(
		'fatal'     => array(),
		'messages'  => array(),
		'extraites' => 0,
	);

	if ( ! class_exists( 'ZipArchive' ) ) {
		$resultat['fatal'][] = 'Extension PHP zip absente sur le serveur : déposer les images par la médiathèque.';
		return $resultat;
	}

	$destination = realpath( $destination );

	if ( false === $destination || ! is_dir( $destination ) ) {
		$resultat['fatal'][] = 'Dossier de destination des images introuvable.';
		return $resultat;
	}

	$zip = new ZipArchive();

	if ( true !== $zip->open( $archive ) ) {
		$resultat['fatal'][] = 'Archive zip illisible.';
		return $resultat;
	}

	if ( $zip->numFiles > $regles['zip_entrees_max'] ) {
		$resultat['fatal'][] = sprintf( 'Archive zip trop volumineuse (%1$d entrées, maximum %2$d).', $zip->numFiles, $regles['zip_entrees_max'] );
		$zip->close();
		return $resultat;
	}

	$total  = 0;
	$noms   = array();
	$ignore = 0;

	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$infos = $zip->statIndex( $i );

		if ( ! is_array( $infos ) || ! isset( $infos['name'] ) ) {
			continue;
		}

		$entree   = (string) $infos['name'];
		$normal   = str_replace( '\\', '/', $entree );
		$segments = explode( '/', $normal );
		$base     = (string) end( $segments );
		$affiche  = ditl_import_extrait( $entree, 120 );

		// Dossier, ressources macOS, fichiers caches : ignores sans message.
		if ( '' === $base || in_array( '__MACOSX', $segments, true ) || '.' === substr( $base, 0, 1 ) ) {
			continue;
		}

		$extension = strtolower( (string) pathinfo( $base, PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, ditl_import_extensions_images(), true ) ) {
			++$ignore;
			$resultat['messages'][] = array(
				'niveau' => 'avertissement',
				'texte'  => sprintf( 'Archive : « %s » ignoré (pas une image jpg, jpeg, png, webp ou gif).', $affiche ),
			);
			continue;
		}

		$nom = sanitize_file_name( $base );
		$cle = strtolower( $nom );

		if ( '' === $nom || '' !== ditl_import_controler_nom_image( $nom ) ) {
			++$ignore;
			continue;
		}

		if ( isset( $noms[ $cle ] ) ) {
			$resultat['messages'][] = array(
				'niveau' => 'avertissement',
				'texte'  => sprintf( 'Archive : « %s » ignoré (même nom qu\'une image déjà extraite : la première est gardée).', $affiche ),
			);
			continue;
		}

		$declaree = isset( $infos['size'] ) ? (int) $infos['size'] : 0;

		if ( $declaree > $regles['image_taille_max'] ) {
			$resultat['messages'][] = array(
				'niveau' => 'avertissement',
				'texte'  => sprintf( 'Archive : « %1$s » ignoré (plus de %2$s).', $affiche, size_format( $regles['image_taille_max'] ) ),
			);
			continue;
		}

		if ( $total + $declaree > $regles['zip_total_max'] ) {
			$resultat['fatal'][] = sprintf( 'Archive zip trop volumineuse une fois décompressée (maximum %s).', size_format( $regles['zip_total_max'] ) );
			break;
		}

		$cible = $destination . DIRECTORY_SEPARATOR . $nom;

		// Garde-fou : la cible reste un enfant direct de la destination.
		if ( dirname( $cible ) !== $destination || file_exists( $cible ) ) {
			continue;
		}

		$flux = $zip->getStream( $entree );

		if ( false === $flux ) {
			$resultat['messages'][] = array(
				'niveau' => 'avertissement',
				'texte'  => sprintf( 'Archive : « %s » illisible (fichier chiffré ou endommagé).', $affiche ),
			);
			continue;
		}

		$sortie = @fopen( $cible, 'xb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- creation exclusive.

		if ( false === $sortie ) {
			fclose( $flux ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- flux zip.
			continue;
		}

		// Taille reelle controlee pendant la copie (la taille declaree peut mentir).
		$copie = stream_copy_to_stream( $flux, $sortie, $regles['image_taille_max'] + 1 );
		fclose( $sortie ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- fichier extrait.
		fclose( $flux ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- flux zip.

		if ( false === $copie || $copie > $regles['image_taille_max'] ) {
			wp_delete_file( $cible );
			$resultat['messages'][] = array(
				'niveau' => 'avertissement',
				'texte'  => sprintf( 'Archive : « %1$s » ignoré (plus de %2$s).', $affiche, size_format( $regles['image_taille_max'] ) ),
			);
			continue;
		}

		$total += (int) $copie;

		if ( $total > $regles['zip_total_max'] ) {
			wp_delete_file( $cible );
			$resultat['fatal'][] = sprintf( 'Archive zip trop volumineuse une fois décompressée (maximum %s).', size_format( $regles['zip_total_max'] ) );
			break;
		}

		$refus = ditl_import_controler_image( $cible, $regles );

		if ( '' !== $refus ) {
			wp_delete_file( $cible );
			$resultat['messages'][] = array(
				'niveau' => 'avertissement',
				'texte'  => sprintf( 'Archive : « %1$s » ignoré (%2$s).', $affiche, $refus ),
			);
			continue;
		}

		$noms[ $cle ] = true;
		++$resultat['extraites'];
	}

	$zip->close();

	$resultat['messages'][] = array(
		'niveau' => 'info',
		'texte'  => sprintf( 'Archive : %1$d image(s) extraite(s)%2$s.', $resultat['extraites'], $ignore > 0 ? sprintf( ', %d fichier(s) ignoré(s)', $ignore ) : '' ),
	);

	return $resultat;
}
