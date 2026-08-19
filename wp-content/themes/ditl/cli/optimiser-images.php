<?php
/**
 * Generation d'images WebP et service conditionnel Apache (phase 1).
 *
 * Volet IMAGES du chantier "optimisation du chargement" :
 * - parcourt wp-content/uploads et genere un jumeau .webp a cote de chaque
 *   jpg/jpeg/png (meme nom + suffixe .webp : image.jpg -> image.jpg.webp),
 *   qualite 82, transparence des PNG preservee ; le .webp n'est CONSERVE
 *   que s'il est plus petit que l'original (sinon supprime et compte
 *   "ignore") ;
 * - dossiers de backup EXCLUS (wp-content/uploads/backup et tout dossier
 *   dont le nom commence par "backup") ; seuls jpg/jpeg/png sont traites
 *   (gif, svg, pdf... ignores) ;
 * - pose un bloc .htaccess a marqueurs (# BEGIN DITL WebP / # END DITL WebP),
 *   insere AVANT le bloc WordPress, sans toucher aux blocs existants
 *   (AIOS, WPFC, WordPress) : si le navigateur annonce image/webp dans son
 *   en-tete Accept ET que le jumeau .webp existe, Apache le sert a la place
 *   de l'original avec le bon Content-Type ; l'en-tete "Vary: Accept" est
 *   ajoute sur les jpg/png/webp (indispensable avec le cache navigateur de
 *   120 jours pose par WP Fastest Cache). Le HTML ne change pas : aucune
 *   balise picture, aucun srcset modifie, URLs inchangees.
 *
 * Le mod_expires de WPFC couvre deja image/webp (ExpiresByType image/webp
 * A10368000 dans le bloc LBCWpFastestCache) : aucun ExpiresByType n'est
 * ajoute ici.
 *
 * Outil de conversion : cwebp (binaire) si disponible sur la machine,
 * sinon GD (imagewebp, PHP >= 7.4 avec support WebP). L'outil retenu est
 * affiche dans le rapport.
 *
 * COEUR DE CONVERSION PARTAGE : la mecanique (detection d'outil, qualite,
 * transparence, regle "conserve seulement si plus petit", memoire
 * ditl_webp_ignores) vit dans inc/webp.php, partagee avec les hooks medias
 * du theme : ce script traite le STOCK existant d'uploads, les hooks
 * couvrent le fil de l'eau (nouveaux uploads, regenerations, suppressions).
 * Le mode annuler balaie le disque : il supprime aussi les jumeaux crees
 * par les hooks (meme nommage).
 *
 * Idempotent : au rejeu, les .webp deja presents et a jour (plus recents
 * que leur original) sont ignores, les originaux dont le WebP s'est revele
 * plus lourd sont memorises (option ditl_webp_ignores, non autoload) pour
 * ne pas etre reconvertis, et le bloc .htaccess n'est reecrit que s'il a
 * change : un rejeu sans nouveaute n'ecrit RIEN. Rejouable en preprod et
 * production (le .htaccess n'etant pas versionne, ce script est LE
 * mecanisme de deploiement du bloc WebP).
 *
 * Mode annuler : supprime tous les jumeaux .webp (uniquement les fichiers
 * X.jpg.webp / X.jpeg.webp / X.png.webp dont l'original X existe encore),
 * retire le bloc .htaccess et efface l'option ditl_webp_ignores.
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/optimiser-images.php dry-run
 *   wp eval-file wp-content/themes/ditl/cli/optimiser-images.php
 *   wp eval-file wp-content/themes/ditl/cli/optimiser-images.php annuler
 *
 * Les modes acceptent les formes "dry-run"/"--dry-run" et
 * "annuler"/"--annuler" et sont combinables (annuler en simulation).
 *
 * Apres execution : purger le cache de page (wp-content/cache/all) puis
 * tester une image avec et sans "Accept: image/webp" (Content-Type
 * different, Vary: Accept present).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Usage : wp eval-file <script> [dry-run|annuler] - voir le docblock.
	// En acces web direct, reponse muette pour ne rien reveler du fichier.
	http_response_code( 404 );
	exit( 1 );
}

// Bibliotheque commune des scripts CLI du theme.
require_once __DIR__ . '/commun.php';

// Coeur de conversion WebP partage avec les hooks medias du theme (deja
// charge si le theme DiTL est actif ; require direct pour rester autonome).
require_once __DIR__ . '/../inc/webp.php';

// ---------------------------------------------------------------------------
// Lecture des arguments : modes simulation / annulation.
// ---------------------------------------------------------------------------

$ditl_modes   = ditl_cli_lire_modes( $args );
$ditl_dry_run = $ditl_modes['dry_run'];
$ditl_annuler = $ditl_modes['annuler'];

if ( $ditl_dry_run ) {
	WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucun fichier ecrit, .htaccess non touche ===' );
}

if ( $ditl_annuler ) {
	WP_CLI::log( '=== MODE ANNULER : suppression des .webp generes et du bloc .htaccess ===' );
}

// ---------------------------------------------------------------------------
// Reglages (qualite et extensions : voir inc/webp.php, DITL_WEBP_QUALITE
// et ditl_webp_extensions(), partages avec les hooks medias).
// ---------------------------------------------------------------------------

$ditl_upload_dir = wp_get_upload_dir();
$ditl_uploads    = isset( $ditl_upload_dir['basedir'] ) ? (string) $ditl_upload_dir['basedir'] : '';

if ( '' === $ditl_uploads || ! is_dir( $ditl_uploads ) ) {
	WP_CLI::error( sprintf( 'Dossier uploads introuvable : %s', $ditl_uploads ) );
}

$ditl_htaccess = rtrim( ABSPATH, '/' ) . '/.htaccess';

if ( ! function_exists( 'ditl_webp_chemin_exclu' ) ) {
	/**
	 * Indique si un chemin traverse un dossier de backup (exclu du traitement).
	 *
	 * Exclut wp-content/uploads/backup et, par prudence, tout dossier dont le
	 * nom commence par "backup" (backups, backup-2025...), a n'importe quelle
	 * profondeur.
	 *
	 * @param string $chemin  Chemin absolu du fichier.
	 * @param string $racine  Racine uploads (pour ne tester que le relatif).
	 * @return bool True si le chemin est a exclure.
	 */
	function ditl_webp_chemin_exclu( $chemin, $racine ) {
		$relatif = substr( $chemin, strlen( rtrim( $racine, '/' ) ) );

		return (bool) preg_match( '#/backup[^/]*/#i', $relatif );
	}
}

// ---------------------------------------------------------------------------
// Bloc .htaccess gere par ce script.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'ditl_webp_bloc_htaccess' ) ) {
	/**
	 * Contenu du bloc .htaccess de service conditionnel WebP.
	 *
	 * @return string Bloc complet, marqueurs inclus, termine par un saut de ligne.
	 */
	function ditl_webp_bloc_htaccess() {
		$lignes = array(
			'# BEGIN DITL WebP',
			'# Service conditionnel WebP : si le navigateur annonce image/webp et',
			'# qu\'un jumeau .webp existe, il est servi a la place du jpg/png demande',
			'# (URL inchangee). Bloc gere par le script WP-CLI',
			'# wp-content/themes/ditl/cli/optimiser-images.php - ne pas editer a la main.',
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteCond %{HTTP_ACCEPT} image/webp',
			'RewriteCond %{REQUEST_FILENAME} -f',
			'RewriteCond %{REQUEST_FILENAME}.webp -f',
			'RewriteRule ^(wp-content/uploads/.+\\.(?:jpe?g|png))$ $1.webp [NC,T=image/webp,L]',
			'</IfModule>',
			'<IfModule mod_headers.c>',
			'<FilesMatch "(?i)\\.(jpe?g|png|webp)$">',
			'Header merge Vary "Accept"',
			'</FilesMatch>',
			'</IfModule>',
			'# END DITL WebP',
		);

		return implode( "\n", $lignes ) . "\n";
	}
}

if ( ! function_exists( 'ditl_webp_poser_htaccess' ) ) {
	/**
	 * Pose, met a jour ou retire le bloc DITL WebP dans le .htaccess.
	 *
	 * Le bloc est insere AVANT le bloc WordPress (# BEGIN WordPress) pour
	 * que la reecriture d'image passe avant la regle attrape-tout de
	 * WordPress. Les autres blocs (AIOS, WPFC...) ne sont jamais touches.
	 *
	 * @param string $fichier Chemin du .htaccess.
	 * @param bool   $retirer True pour retirer le bloc (mode annuler).
	 * @param bool   $dry_run True pour simuler sans ecrire.
	 */
	function ditl_webp_poser_htaccess( $fichier, $retirer, $dry_run ) {
		WP_CLI::log( '--- Bloc .htaccess (# BEGIN DITL WebP) ---' );

		if ( ! file_exists( $fichier ) ) {
			WP_CLI::warning( sprintf( '.htaccess introuvable (%s) : bloc non pose, a rejouer sur un environnement ou il existe.', $fichier ) );
			return;
		}

		$contenu = file_get_contents( $fichier );

		if ( false === $contenu ) {
			WP_CLI::warning( sprintf( '.htaccess illisible (%s) : bloc non pose.', $fichier ) );
			return;
		}

		$bloc  = ditl_webp_bloc_htaccess();
		$motif = '/# BEGIN DITL WebP.*?# END DITL WebP\n?/s';

		preg_match( $motif, $contenu, $ditl_existant );
		$present = ! empty( $ditl_existant );

		if ( $retirer ) {
			if ( ! $present ) {
				WP_CLI::log( '  Bloc absent, rien a retirer.' );
				return;
			}

			if ( $dry_run ) {
				WP_CLI::log( '  [dry-run] le bloc serait retire du .htaccess.' );
				return;
			}

			// Retrait du bloc ET de la ligne vide de separation qui le suit
			// (une seule, celle posee a l'insertion : aucun autre bloc touche).
			$nouveau = preg_replace( '/# BEGIN DITL WebP.*?# END DITL WebP\n?\n?/s', '', $contenu, 1 );

			if ( false !== file_put_contents( $fichier, $nouveau ) ) {
				WP_CLI::log( '  Bloc retire du .htaccess.' );
			} else {
				WP_CLI::warning( 'Ecriture du .htaccess impossible : bloc non retire.' );
			}

			return;
		}

		if ( $present && trim( $ditl_existant[0] ) === trim( $bloc ) ) {
			WP_CLI::log( '  Bloc deja en place et a jour, rien a faire.' );
			return;
		}

		if ( $dry_run ) {
			WP_CLI::log( $present ? '  [dry-run] le bloc serait mis a jour.' : '  [dry-run] le bloc serait insere avant le bloc WordPress.' );
			return;
		}

		if ( $present ) {
			// Mise a jour en place. Remplacement par str_replace du bloc
			// capture (JAMAIS preg_replace : le "$1" de la RewriteRule serait
			// interprete comme reference arriere et mutile la regle).
			$nouveau = str_replace( rtrim( $ditl_existant[0], "\n" ), $bloc, $contenu );
		} else {
			$marqueur_wp = '# BEGIN WordPress';
			$position    = strpos( $contenu, $marqueur_wp );

			if ( false !== $position ) {
				$nouveau = substr( $contenu, 0, $position ) . $bloc . "\n" . substr( $contenu, $position );
			} else {
				// Pas de bloc WordPress : ajout en fin de fichier.
				WP_CLI::warning( 'Bloc "# BEGIN WordPress" introuvable : bloc DITL WebP ajoute en fin de .htaccess.' );
				$nouveau = rtrim( $contenu, "\n" ) . "\n\n" . $bloc;
			}
		}

		if ( false !== file_put_contents( $fichier, $nouveau ) ) {
			WP_CLI::log( $present ? '  Bloc mis a jour dans le .htaccess.' : '  Bloc insere avant le bloc WordPress.' );
		} else {
			WP_CLI::warning( 'Ecriture du .htaccess impossible : bloc non pose.' );
		}
	}
}

// ---------------------------------------------------------------------------
// Outil de conversion : detection partagee (inc/webp.php).
// ---------------------------------------------------------------------------

if ( ! $ditl_annuler ) {
	$ditl_outil = ditl_webp_outil();

	if ( '' === $ditl_outil['outil'] ) {
		WP_CLI::error( 'Aucun outil WebP disponible : ni le binaire cwebp, ni GD avec support WebP (imagewebp).' );
	}

	WP_CLI::log( sprintf( 'Outil de conversion : %s%s.', $ditl_outil['outil'], 'cwebp' === $ditl_outil['outil'] ? ' (' . $ditl_outil['cwebp'] . ')' : '' ) );
}

// ---------------------------------------------------------------------------
// Parcours des uploads.
// ---------------------------------------------------------------------------

$ditl_convertis      = 0;
$ditl_a_jour         = 0;
$ditl_ignores        = 0;
$ditl_echecs         = 0;
$ditl_supprimes      = 0;
$ditl_octets_sources = 0;
$ditl_octets_webp    = 0;

$ditl_iterateur = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $ditl_uploads, FilesystemIterator::SKIP_DOTS )
);

foreach ( $ditl_iterateur as $ditl_fichier ) {
	if ( ! $ditl_fichier->isFile() ) {
		continue;
	}

	$ditl_chemin = $ditl_fichier->getPathname();

	if ( ditl_webp_chemin_exclu( $ditl_chemin, $ditl_uploads ) ) {
		continue;
	}

	$ditl_extension = strtolower( $ditl_fichier->getExtension() );

	// Mode annuler : suppression des jumeaux .webp dont l'original existe.
	if ( $ditl_annuler ) {
		if ( 'webp' !== $ditl_extension ) {
			continue;
		}

		$ditl_original = substr( $ditl_chemin, 0, -5 );
		$ditl_ext_orig = strtolower( (string) pathinfo( $ditl_original, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ditl_ext_orig, ditl_webp_extensions(), true ) || ! file_exists( $ditl_original ) ) {
			// .webp natif (televerse tel quel) ou orphelin : non touche.
			continue;
		}

		if ( $ditl_dry_run ) {
			$ditl_supprimes++;
			continue;
		}

		if ( @unlink( $ditl_chemin ) ) {
			$ditl_supprimes++;
		} else {
			WP_CLI::warning( sprintf( 'Suppression impossible : %s', $ditl_chemin ) );
			$ditl_echecs++;
		}

		continue;
	}

	// Mode normal : generation des jumeaux manquants ou perimes, par le
	// coeur de conversion partage (inc/webp.php) - regles identiques a
	// celles des hooks medias.
	$ditl_statut = ditl_webp_generer_jumeau( $ditl_chemin, $ditl_dry_run );

	switch ( $ditl_statut ) {
		case 'converti':
			$ditl_convertis++;

			if ( ! $ditl_dry_run ) {
				$ditl_octets_sources += (int) filesize( $ditl_chemin );
				$ditl_octets_webp    += (int) filesize( $ditl_chemin . '.webp' );
			}
			break;

		case 'a_jour':
			$ditl_a_jour++;
			$ditl_octets_sources += (int) filesize( $ditl_chemin );
			$ditl_octets_webp    += (int) filesize( $ditl_chemin . '.webp' );
			break;

		case 'memoire':
		case 'ignore':
			// Refus memorise (option ditl_webp_ignores) ou WebP plus lourd
			// que l'original, supprime et memorise a l'instant.
			$ditl_ignores++;
			break;

		case 'echec':
			WP_CLI::warning( sprintf( 'Conversion echouee : %s', $ditl_chemin ) );
			$ditl_echecs++;
			break;

		default:
			// "inutilisable" : extension hors perimetre, deja filtree plus haut.
			break;
	}
}

// ---------------------------------------------------------------------------
// Memoire des ignores (option non autoload, partagee avec les hooks
// medias) : alimentee au fil de l'eau par ditl_webp_generer_jumeau(),
// effacee entierement en mode annuler.
// ---------------------------------------------------------------------------

if ( $ditl_annuler && false !== get_option( 'ditl_webp_ignores', false ) ) {
	if ( $ditl_dry_run ) {
		WP_CLI::log( '[dry-run] l\'option ditl_webp_ignores serait effacee.' );
	} else {
		delete_option( 'ditl_webp_ignores' );
		WP_CLI::log( 'Option ditl_webp_ignores effacee.' );
	}
}

// ---------------------------------------------------------------------------
// Bloc .htaccess.
// ---------------------------------------------------------------------------

ditl_webp_poser_htaccess( $ditl_htaccess, $ditl_annuler, $ditl_dry_run );

// ---------------------------------------------------------------------------
// Rapport.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Rapport ---' );

if ( $ditl_annuler ) {
	WP_CLI::log( sprintf( '  Jumeaux .webp %s : %d.', $ditl_dry_run ? 'qui seraient supprimes' : 'supprimes', $ditl_supprimes ) );
} else {
	WP_CLI::log( sprintf( '  Convertis %s: %d.', $ditl_dry_run ? '(simulation) ' : '', $ditl_convertis ) );
	WP_CLI::log( sprintf( '  Deja a jour (ignores au rejeu) : %d.', $ditl_a_jour ) );
	WP_CLI::log( sprintf( '  Ignores (WebP plus lourd que l\'original, supprime) : %d.', $ditl_ignores ) );

	if ( ! $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  Octets des originaux couverts par un .webp : %s.', size_format( $ditl_octets_sources, 2 ) ) );
		WP_CLI::log( sprintf( '  Octets des .webp servis a leur place       : %s (-%d %%).', size_format( $ditl_octets_webp, 2 ), $ditl_octets_sources > 0 ? round( 100 - ( 100 * $ditl_octets_webp / $ditl_octets_sources ) ) : 0 ) );
		WP_CLI::log( sprintf( '  Poids disque total ajoute par les .webp    : %s.', size_format( $ditl_octets_webp, 2 ) ) );
		WP_CLI::log( '  NB : wp-content/uploads/ est ignore par git, les .webp ne sont pas versionnes ; ce script est a rejouer en preprod/prod.' );
	}
}

if ( $ditl_echecs > 0 ) {
	WP_CLI::warning( sprintf( '%d echec(s) : voir les messages ci-dessus.', $ditl_echecs ) );
}

WP_CLI::log( '--- Rappels ---' );
WP_CLI::log( '  Purger le cache de page (wp-content/cache/all) apres execution.' );
WP_CLI::log( '  Tester : curl -sI -H "Accept: image/webp" <url d\'un jpg> -> Content-Type: image/webp + Vary: Accept ; sans l\'en-tete -> image/jpeg.' );
WP_CLI::log( '  Le bloc .htaccess n\'est pas versionne : rejouer ce script UNE FOIS par environnement (stock existant + bloc). Les nouveaux uploads sont couverts au fil de l\'eau par les hooks de inc/webp.php.' );

WP_CLI::log( '' );
WP_CLI::log( $ditl_dry_run ? 'Simulation terminee.' : ( $ditl_annuler ? 'Annulation terminee.' : 'Generation terminee.' ) );
