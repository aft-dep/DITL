<?php
/**
 * Import des fiches : lecture robuste d'un fichier CSV.
 *
 * Tolere ce que produisent Excel, LibreOffice et les editeurs de texte :
 * BOM UTF-8, fichier Windows-1252 (Excel "CSV separateur point-virgule"
 * non UTF-8, converti avec avertissement), separateur ; , ou tabulation
 * detecte sur la premiere ligne non vide, guillemets doubles, retours a la
 * ligne dans les cellules, fins de ligne CRLF / LF / CR. Le numero de ligne
 * PHYSIQUE du fichier est conserve pour chaque enregistrement (messages
 * d'erreur exploitables dans un tableur ou un editeur).
 *
 * Bornes memoire : taille du fichier, nombre de lignes, nombre de cellules
 * estime AVANT l'analyse (separateurs + retours a la ligne), cellules
 * toujours tronquees au nombre de colonnes de l'en-tete.
 *
 * Fichier mixte (UTF-8 invalide contenant aussi de l'UTF-8 valide, ex.
 * copier-coller entre deux encodages) : erreur fatale, car une conversion
 * Windows-1252 abimerait tous les accents deja corrects.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lit un fichier CSV d'import.
 *
 * @param string $chemin Chemin absolu du fichier.
 * @param array  $regles Regles de l'import (taille_max, lignes_max, cellules_max).
 * @return array Tableau {
 *     fatal      : string[] erreurs bloquantes,
 *     messages   : array[]  messages de fichier { niveau, texte },
 *     separateur : string   separateur detecte,
 *     encodage   : string   encodage d'origine,
 *     entetes    : string[] en-tetes normalises (index = colonne),
 *     bruts      : string[] en-tetes tels que saisis,
 *     lignes     : array[]  enregistrements { ligne, cellules, erreur }.
 * }
 */
function ditl_import_lire_csv( $chemin, $regles ) {
	$resultat = array(
		'fatal'      => array(),
		'messages'   => array(),
		'separateur' => ';',
		'encodage'   => 'UTF-8',
		'entetes'    => array(),
		'bruts'      => array(),
		'lignes'     => array(),
	);

	if ( ! is_string( $chemin ) || '' === $chemin || ! is_file( $chemin ) || ! is_readable( $chemin ) ) {
		$resultat['fatal'][] = 'Fichier introuvable ou illisible.';
		return $resultat;
	}

	$taille = filesize( $chemin );

	if ( false === $taille || $taille > $regles['taille_max'] ) {
		$resultat['fatal'][] = sprintf( 'Fichier trop volumineux (%s maximum).', size_format( $regles['taille_max'] ) );
		return $resultat;
	}

	if ( 0 === $taille ) {
		$resultat['fatal'][] = 'Fichier vide.';
		return $resultat;
	}

	$contenu = file_get_contents( $chemin ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichier local.

	if ( false === $contenu ) {
		$resultat['fatal'][] = 'Fichier illisible.';
		return $resultat;
	}

	// UTF-16 (export "Texte Unicode" d'Excel) : non pris en charge.
	if ( 0 === strncmp( $contenu, "\xFF\xFE", 2 ) || 0 === strncmp( $contenu, "\xFE\xFF", 2 ) ) {
		$resultat['fatal'][] = 'Fichier en UTF-16 non pris en charge : l\'enregistrer au format « CSV UTF-8 ».';
		return $resultat;
	}

	// BOM UTF-8.
	if ( 0 === strncmp( $contenu, "\xEF\xBB\xBF", 3 ) ) {
		$contenu = (string) substr( $contenu, 3 );
	}

	if ( false !== strpos( $contenu, "\0" ) ) {
		$resultat['fatal'][] = 'Fichier binaire (ce n\'est pas un CSV) : l\'enregistrer au format « CSV UTF-8 ».';
		return $resultat;
	}

	if ( ! ditl_import_est_utf8( $contenu ) ) {
		if ( ditl_import_contient_utf8( $contenu ) ) {
			$resultat['fatal'][] = 'Encodage mixte : le fichier mélange de l\'UTF-8 et un autre encodage (copier-coller entre fichiers ?). L\'ouvrir dans le tableur et l\'enregistrer à nouveau au format « CSV UTF-8 ».';
			return $resultat;
		}

		$converti = ditl_import_depuis_windows1252( $contenu );

		if ( null === $converti ) {
			$resultat['fatal'][] = 'Encodage non reconnu : enregistrer le fichier au format « CSV UTF-8 ».';
			return $resultat;
		}

		$contenu                = $converti;
		$resultat['encodage']   = 'Windows-1252';
		$resultat['messages'][] = array(
			'niveau' => 'avertissement',
			'texte'  => 'Fichier non UTF-8 : converti depuis Windows-1252 (Excel « CSV séparateur point-virgule »). Vérifier les accents ; préférer le format « CSV UTF-8 ».',
		);
	}

	// Fins de ligne unifiees (y compris dans les cellules).
	$contenu = str_replace( array( "\r\n", "\r" ), "\n", $contenu );

	$separateur             = ditl_import_detecter_separateur( ditl_import_premiere_ligne( $contenu ) );
	$resultat['separateur'] = $separateur;

	// Borne memoire avant l'analyse : estimation du nombre de cellules.
	$cellules_estimees = substr_count( $contenu, $separateur ) + substr_count( $contenu, "\n" ) + 1;

	if ( $cellules_estimees > $regles['cellules_max'] ) {
		$resultat['fatal'][] = sprintf( 'Fichier trop grand : environ %1$d cellules (%2$d maximum). Le découper en plusieurs fichiers.', $cellules_estimees, $regles['cellules_max'] );
		return $resultat;
	}

	$longueur_totale = strlen( $contenu );
	$flux            = fopen( 'php://temp', 'w+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- flux memoire.

	if ( false === $flux ) {
		$resultat['fatal'][] = 'Lecture impossible (flux temporaire).';
		return $resultat;
	}

	fwrite( $flux, $contenu ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- flux memoire.
	rewind( $flux );

	$ligne_physique = 1;
	$position       = 0;
	$entetes        = null;
	$attendu        = 0;

	while ( true ) {
		$debut_ligne = $ligne_physique;
		// Echappement "" (RFC 4180) : aucun caractere d'echappement special.
		$cellules = fgetcsv( $flux, 0, $separateur, '"', '' );

		if ( false === $cellules || null === $cellules ) {
			break;
		}

		$nouvelle_position = (int) ftell( $flux );
		$longueur          = $nouvelle_position - $position;

		if ( $longueur > 0 ) {
			$brut            = (string) substr( $contenu, $position, $longueur );
			$ligne_physique += substr_count( $brut, "\n" );

			// Guillemet ouvert jamais ferme : l'enregistrement avale la fin du fichier.
			if ( $nouvelle_position >= $longueur_totale && false !== strpos( rtrim( $brut, "\n" ), "\n" ) && 1 === substr_count( $brut, '"' ) % 2 ) {
				$resultat['fatal'][] = sprintf( 'Ligne %d : guillemet ouvert et jamais fermé, la suite du fichier est illisible.', $debut_ligne );
				break;
			}
		}

		$position = $nouvelle_position;

		if ( null === $entetes ) {
			$cellules = array_map( 'ditl_import_cellule', $cellules );

			if ( ditl_import_ligne_vide( $cellules ) ) {
				continue;
			}

			// En-tetes vides de fin (separateurs en trop) retires.
			while ( array() !== $cellules && '' === end( $cellules ) ) {
				array_pop( $cellules );
			}

			$entetes             = array_map( 'ditl_import_normaliser_entete', $cellules );
			$attendu             = count( $entetes );
			$resultat['entetes'] = $entetes;
			$resultat['bruts']   = $cellules;
			continue;
		}

		$constate = count( $cellules );

		// Toujours tronque au nombre de colonnes attendu (borne memoire).
		$surplus  = $constate > $attendu ? array_slice( $cellules, $attendu ) : array();
		$cellules = array_map( 'ditl_import_cellule', array_slice( $cellules, 0, $attendu ) );

		if ( ditl_import_ligne_vide( $cellules ) && ditl_import_ligne_vide( $surplus ) ) {
			continue;
		}

		if ( count( $resultat['lignes'] ) >= $regles['lignes_max'] ) {
			$resultat['fatal'][] = sprintf( 'Trop de lignes (%d maximum) : découper le fichier.', $regles['lignes_max'] );
			break;
		}

		$erreur = '';

		if ( $constate > $attendu && ! ditl_import_ligne_vide( $surplus ) ) {
			$erreur = sprintf( 'Nombre de colonnes incohérent : %1$d au lieu de %2$d (séparateur ou guillemet en trop ?).', $constate, $attendu );
		} elseif ( $constate < $attendu ) {
			$erreur = sprintf( 'Nombre de colonnes incohérent : %1$d au lieu de %2$d (séparateur manquant ?).', $constate, $attendu );
		}

		$resultat['lignes'][] = array(
			'ligne'    => $debut_ligne,
			'cellules' => $cellules,
			'erreur'   => $erreur,
		);
	}

	fclose( $flux ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- flux memoire.

	if ( null === $entetes && array() === $resultat['fatal'] ) {
		$resultat['fatal'][] = 'Fichier sans ligne d\'en-tête.';
	}

	return $resultat;
}

/**
 * Premiere ligne non vide d'un contenu (fins de ligne deja unifiees).
 *
 * @param string $contenu Contenu du fichier.
 * @return string Ligne (chaine vide si aucune).
 */
function ditl_import_premiere_ligne( $contenu ) {
	$debut  = 0;
	$taille = strlen( $contenu );

	while ( $debut < $taille ) {
		$fin   = strpos( $contenu, "\n", $debut );
		$fin   = false === $fin ? $taille : $fin;
		$ligne = (string) substr( $contenu, $debut, $fin - $debut );

		if ( '' !== trim( $ligne, " \t;," ) ) {
			return $ligne;
		}

		$debut = $fin + 1;
	}

	return '';
}

/**
 * Indique si une chaine est de l'UTF-8 valide.
 *
 * @param string $texte Chaine a tester.
 * @return bool True si UTF-8 valide.
 */
function ditl_import_est_utf8( $texte ) {
	if ( function_exists( 'mb_check_encoding' ) ) {
		return mb_check_encoding( $texte, 'UTF-8' );
	}

	return 1 === preg_match( '//u', $texte );
}

/**
 * Indique si une chaine (non UTF-8 valide) contient des sequences UTF-8
 * multi-octets bien formees, signe d'un fichier a encodage mixte.
 *
 * @param string $texte Chaine a tester.
 * @return bool True si au moins une sequence UTF-8 multi-octets est trouvee.
 */
function ditl_import_contient_utf8( $texte ) {
	return 1 === preg_match( '/[\xC2-\xDF][\x80-\xBF]|[\xE0-\xEF][\x80-\xBF]{2}|[\xF0-\xF4][\x80-\xBF]{3}/', $texte );
}

/**
 * Convertit une chaine Windows-1252 en UTF-8.
 *
 * @param string $texte Chaine en Windows-1252.
 * @return string|null Chaine UTF-8, ou null si aucune conversion possible.
 */
function ditl_import_depuis_windows1252( $texte ) {
	$converti = false;

	if ( function_exists( 'mb_convert_encoding' ) ) {
		$converti = mb_convert_encoding( $texte, 'UTF-8', 'Windows-1252' );
	} elseif ( function_exists( 'iconv' ) ) {
		$converti = iconv( 'Windows-1252', 'UTF-8//TRANSLIT', $texte );
	}

	return is_string( $converti ) && ditl_import_est_utf8( $converti ) ? $converti : null;
}

/**
 * Detecte le separateur sur une ligne d'en-tete (hors texte entre guillemets).
 *
 * A egalite, ordre de preference : point-virgule, virgule, tabulation.
 *
 * @param string $ligne Ligne d'en-tete brute.
 * @return string Separateur.
 */
function ditl_import_detecter_separateur( $ligne ) {
	$sans_guillemets = (string) preg_replace( '/"(?:[^"]|"")*"/', '', (string) $ligne );
	$retenu          = ';';
	$maximum         = 0;

	foreach ( array( ';', ',', "\t" ) as $candidat ) {
		$nombre = substr_count( $sans_guillemets, $candidat );

		if ( $nombre > $maximum ) {
			$maximum = $nombre;
			$retenu  = $candidat;
		}
	}

	return $retenu;
}

/**
 * Normalise une cellule : chaine, caracteres de controle et de mise en
 * forme bidirectionnelle retires (tabulation et retours a la ligne gardes),
 * espaces de bord retires.
 *
 * @param mixed $cellule Cellule lue.
 * @return string Cellule nettoyee.
 */
function ditl_import_cellule( $cellule ) {
	if ( ! is_scalar( $cellule ) ) {
		return '';
	}

	$texte  = (string) $cellule;
	$propre = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\x{202A}|\x{202B}|\x{202C}|\x{202D}|\x{202E}|\x{2066}|\x{2067}|\x{2068}|\x{2069}/u', '', $texte );

	if ( ! is_string( $propre ) ) {
		$propre = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $texte );
	}

	return trim( $propre );
}

/**
 * Indique si un enregistrement ne contient que des cellules vides.
 *
 * @param array $cellules Cellules.
 * @return bool True si toutes les cellules sont vides.
 */
function ditl_import_ligne_vide( $cellules ) {
	foreach ( (array) $cellules as $cellule ) {
		if ( '' !== ditl_import_cellule( $cellule ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Normalise un en-tete de colonne : minuscules, accents retires, espaces et
 * tirets remplaces par un souligne (ex. "Points de vigilance" ->
 * "points_de_vigilance", "Fabricant 1 URL" -> "fabricant_1_url").
 *
 * @param string $entete En-tete brut.
 * @return string En-tete normalise.
 */
function ditl_import_normaliser_entete( $entete ) {
	$texte = strtolower( remove_accents( trim( (string) $entete ) ) );
	$texte = (string) preg_replace( '/[\s\-]+/', '_', $texte );
	$texte = (string) preg_replace( '/[^a-z0-9_]/', '', $texte );
	$texte = (string) preg_replace( '/_+/', '_', $texte );

	return trim( $texte, '_' );
}
