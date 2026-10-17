<?php
/**
 * Import des fiches : conversions de valeurs (texte riche, codes, URL,
 * statut, titres).
 *
 * Texte riche (convention proposee au client le 07/09, sans HTML a taper) :
 * - un retour a la ligne = un nouveau paragraphe ;
 * - une ligne commencant par "- " (ou par une puce collee depuis Word :
 *   puce ronde, tiret moyen ou long, asterisque, suivis d'un espace ou d'une
 *   tabulation) = un element de liste ;
 * - **texte** = gras.
 * Tout le reste est ECHAPPE avant l'application des motifs : du HTML saisi
 * s'affiche tel quel (avertissement), il n'est jamais interprete. La sortie
 * est filtree par wp_kses (p, ul, li, strong seulement).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Caracteres de puce acceptes en debut de ligne (contenu d'une classe PCRE,
// motif /u) : tiret, asterisque, puce ronde, puce triangulaire, puce trait,
// tiret moyen, tiret long, point median, carre, disque, cercle, puce Symbol
// de Word (zone privee U+F0B7). Partage par la conversion et le comptage.
define( 'DITL_IMPORT_PUCES', '\-\*\x{2022}\x{2023}\x{2043}\x{2013}\x{2014}\x{00B7}\x{25AA}\x{25CF}\x{25E6}\x{F0B7}' );

/**
 * Convertit un texte saisi selon la convention en HTML limite.
 *
 * @param string $texte Texte de la cellule.
 * @return string HTML (p, ul, li, strong), chaine vide si texte vide.
 */
function ditl_import_texte_riche( $texte ) {
	$lignes = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", (string) $texte ) );
	$blocs  = array();
	$liste  = array();

	$motif_puce = '/^[' . DITL_IMPORT_PUCES . '][ \t\x{00A0}]+(.+)$/u';

	foreach ( $lignes as $ligne ) {
		$ligne = ditl_import_rogner( $ligne );

		if ( '' === $ligne ) {
			if ( array() !== $liste ) {
				$blocs[] = "<ul>\n" . implode( "\n", $liste ) . "\n</ul>";
				$liste   = array();
			}
			continue;
		}

		if ( preg_match( $motif_puce, $ligne, $correspondance ) ) {
			$element = ditl_import_rogner( $correspondance[1] );

			if ( '' !== $element ) {
				$liste[] = '<li>' . ditl_import_mise_en_forme( $element ) . '</li>';
				continue;
			}
		}

		if ( array() !== $liste ) {
			$blocs[] = "<ul>\n" . implode( "\n", $liste ) . "\n</ul>";
			$liste   = array();
		}

		$blocs[] = '<p>' . ditl_import_mise_en_forme( $ligne ) . '</p>';
	}

	if ( array() !== $liste ) {
		$blocs[] = "<ul>\n" . implode( "\n", $liste ) . "\n</ul>";
	}

	return wp_kses(
		implode( "\n", $blocs ),
		array(
			'p'      => array(),
			'ul'     => array(),
			'li'     => array(),
			'strong' => array(),
		)
	);
}

/**
 * Echappe une ligne de texte puis applique le gras (**texte**).
 *
 * L'echappement precede le motif : le contenu capture est deja inoffensif.
 * preg_replace_callback (et non preg_replace) : le texte saisi ne passe
 * jamais dans une chaine de remplacement (references arriere).
 *
 * @param string $texte Ligne de texte brute.
 * @return string Ligne echappee avec balises strong.
 */
function ditl_import_mise_en_forme( $texte ) {
	$echappe = htmlspecialchars( (string) $texte, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	$gras    = preg_replace_callback(
		'/\*\*(?=\S)(.+?)(?<=\S)\*\*/u',
		static function ( $m ) {
			return '<strong>' . $m[1] . '</strong>';
		},
		$echappe
	);

	return is_string( $gras ) ? $gras : $echappe;
}

/**
 * Retire les espaces de bord, espaces insecables compris.
 *
 * @param string $texte Texte.
 * @return string Texte rogne.
 */
function ditl_import_rogner( $texte ) {
	$rogne = preg_replace( '/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', (string) $texte );

	return is_string( $rogne ) ? $rogne : trim( (string) $texte );
}

/**
 * Indique si un texte contient des balises HTML.
 *
 * @param string $texte Texte saisi.
 * @return bool True si une balise (ou un commentaire HTML) est detectee.
 */
function ditl_import_contient_html( $texte ) {
	return 1 === preg_match( '/<\s*\/?\s*[a-z!?][^>]*>/i', (string) $texte );
}

/**
 * Longueur d'un texte saisi, hors marques de mise en forme.
 *
 * @param string $texte Texte saisi.
 * @return int Nombre de caracteres.
 */
function ditl_import_longueur( $texte ) {
	$sans_marques = str_replace( '**', '', (string) $texte );
	$sans_marques = (string) preg_replace( '/^[ \t]*[' . DITL_IMPORT_PUCES . '][ \t\x{00A0}]+/mu', '', $sans_marques );

	return (int) mb_strlen( ditl_import_rogner( $sans_marques ), 'UTF-8' );
}

/**
 * Nombre de mots d'un titre.
 *
 * @param string $titre Titre.
 * @return int Nombre de mots (un mot compose compte pour un).
 */
function ditl_import_compter_mots( $titre ) {
	$mots = preg_split( '/[\s\x{00A0}]+/u', ditl_import_rogner( $titre ), -1, PREG_SPLIT_NO_EMPTY );

	return is_array( $mots ) ? count( $mots ) : 0;
}

/**
 * Normalise un code de referentiel saisi.
 *
 * Casse, accents et espaces sont toleres ("Technique" vaut "technique",
 * "Membres inferieurs" vaut "membres_inferieurs") ; le resultat passe par
 * le nettoyage du modele ([a-z0-9_-]).
 *
 * @param string $brut Code saisi.
 * @return string Code normalise (chaine vide si inexploitable).
 */
function ditl_import_normaliser_code( $brut ) {
	$code = strtolower( remove_accents( ditl_import_rogner( $brut ) ) );
	$code = (string) preg_replace( '/[\s]+/', '_', $code );

	return ditl_boite_sanitize_code( $code );
}

/**
 * Decoupe une cellule multi-valeurs (separateur "|").
 *
 * @param string $cellule Cellule.
 * @return string[] Valeurs non vides, sans doublon, ordre conserve.
 */
function ditl_import_decouper( $cellule ) {
	$valeurs = array();

	foreach ( explode( '|', (string) $cellule ) as $valeur ) {
		$valeur = ditl_import_rogner( $valeur );

		if ( '' !== $valeur && ! in_array( $valeur, $valeurs, true ) ) {
			$valeurs[] = $valeur;
		}
	}

	return $valeurs;
}

/**
 * Valide une URL http(s).
 *
 * @param string $brut URL saisie.
 * @return string URL nettoyee, chaine vide si invalide.
 */
function ditl_import_url( $brut ) {
	$url = ditl_import_rogner( $brut );

	if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
		return '';
	}

	$hote = wp_parse_url( $url, PHP_URL_HOST );

	if ( ! is_string( $hote ) || '' === $hote || false === strpos( $hote, '.' ) || preg_match( '/\s/', $url ) ) {
		return '';
	}

	return (string) esc_url_raw( $url, array( 'http', 'https' ) );
}

/**
 * Interprete la colonne statut.
 *
 * @param string $brut Valeur saisie.
 * @return string|null "draft", "publish", "" (vide), ou null si inconnue.
 */
function ditl_import_statut( $brut ) {
	$valeur = strtolower( remove_accents( ditl_import_rogner( $brut ) ) );

	if ( '' === $valeur ) {
		return '';
	}

	if ( in_array( $valeur, array( 'brouillon', 'draft' ), true ) ) {
		return 'draft';
	}

	if ( in_array( $valeur, array( 'publie', 'publiee', 'publier', 'publish', 'published', 'en ligne' ), true ) ) {
		return 'publish';
	}

	return null;
}

/**
 * Tronque un texte pour l'affichage dans un message.
 *
 * @param string $texte  Texte.
 * @param int    $limite Nombre de caracteres maximum.
 * @return string Texte tronque.
 */
function ditl_import_extrait( $texte, $limite = 80 ) {
	$texte = ditl_import_rogner( str_replace( array( "\r", "\n", "\t" ), ' ', (string) $texte ) );

	return mb_strlen( $texte, 'UTF-8' ) > $limite ? mb_substr( $texte, 0, $limite, 'UTF-8' ) . '...' : $texte;
}

/**
 * Neutralise une cellule exportee contre l'injection de formules (tableur).
 *
 * Une apostrophe est inseree devant tout declencheur (= + - @) place en
 * debut de cellule ou apres une virgule, un point-virgule ou une
 * tabulation (formules en cascade de certains tableurs), et devant une
 * cellule commencant par une tabulation ou un retour chariot.
 * ditl_import_deneutraliser_cellule() fait l'operation inverse exacte.
 *
 * @param string $valeur Valeur.
 * @return string Valeur neutralisee.
 */
function ditl_import_neutraliser_cellule( $valeur ) {
	$valeur = (string) $valeur;
	$neutre = preg_replace_callback(
		'/(^|[,;\t])([=+\-@])/u',
		static function ( $m ) {
			return $m[1] . "'" . $m[2];
		},
		$valeur
	);
	$neutre = is_string( $neutre ) ? $neutre : "'" . $valeur;

	if ( '' !== $neutre && ( "\t" === $neutre[0] || "\r" === $neutre[0] ) ) {
		$neutre = "'" . $neutre;
	}

	return $neutre;
}

/**
 * Retire la neutralisation posee par ditl_import_neutraliser_cellule()
 * (aller-retour export -> import neutre, y compris un ordre negatif).
 *
 * @param string $valeur Valeur lue dans un fichier exporte.
 * @return string Valeur d'origine.
 */
function ditl_import_deneutraliser_cellule( $valeur ) {
	$valeur = (string) $valeur;

	if ( 0 === strpos( $valeur, "'\t" ) || 0 === strpos( $valeur, "'\r" ) ) {
		$valeur = (string) substr( $valeur, 1 );
	}

	$origine = preg_replace_callback(
		"/(^|[,;\t])'([=+\-@])/u",
		static function ( $m ) {
			return $m[1] . $m[2];
		},
		$valeur
	);

	return is_string( $origine ) ? $origine : $valeur;
}
