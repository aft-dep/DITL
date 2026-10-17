<?php
/**
 * Import des fiches outils et des referentiels par CSV (phase 4).
 *
 * Point d'entree WP-CLI du moteur inc/import/ (le meme que l'ecran
 * d'administration "Boite a outils > Importer") : tout le fichier est traite
 * en une passe, avec un rapport ligne par ligne puis un recapitulatif.
 *
 * Deux fichiers (format detaille dans inc/import/schema.php) :
 * - referentiels : taxonomie;code;parent;couleur;fr;en;de;es;pt (+ ordre
 *   optionnel). Un terme par langue active, slug "{code}-{lang}", metas
 *   _ditl_code / _ditl_couleur / _ditl_ordre posees dans chaque langue,
 *   marqueur _ditl_import_terme, traductions liees. Jamais de suppression
 *   d'un terme absent du fichier. Un fichier exporte depuis l'ecran
 *   d'administration se reimporte tel quel (neutralisation des formules
 *   retiree a la lecture).
 * - fiches : une ligne = une fiche dans une langue ; group_id relie les
 *   traductions ; colonnes de choix en CODES ; textes longs selon la
 *   convention du 07/09 (retour a la ligne = paragraphe, "- " = puce,
 *   **gras**). Cle de rejeu = colonne id (meta _ditl_import_id).
 *
 * Comportement (fiches) :
 * - simulation (dry-run) : validation complete et actions prevues, AUCUNE
 *   ecriture (ni base, ni mediatheque) ;
 * - rejouable : une ligne identique au dernier import est "inchangee"
 *   (zero ecriture), y compris un rejeu du CSV seul quand les images sont
 *   deja en mediatheque ; une fiche retouchee dans l'administration depuis
 *   le dernier import est IGNOREE, sauf argument "ecraser" ;
 * - statut : brouillon par defaut ; un "brouillon" du fichier ne depublie
 *   jamais une fiche deja publiee ;
 * - images : nom de fichier dans la colonne image, cherche dans le dossier
 *   images=<dossier> puis dans la mediatheque (deja importee, ou deposee
 *   par le client sous ce nom) ; televersee une seule fois (sha1) ;
 * - un seul import a la fois (verrou global partage avec l'ecran
 *   d'administration) : si un import est deja en cours, erreur fatale.
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/importer-fiches.php referentiels <fichier.csv> [dry-run]
 *   wp eval-file wp-content/themes/ditl/cli/importer-fiches.php fiches <fichier.csv> [images=<dossier>] [dry-run] [ecraser]
 *   wp eval-file wp-content/themes/ditl/cli/importer-fiches.php annuler <fiches|referentiels> <dry-run|confirmer>
 * Ajouter --user=<identifiant d'un administrateur> (auteur des fiches).
 * Code de sortie non nul en cas d'erreur fatale (fichier illisible, colonne
 * obligatoire absente, modele de donnees absent, import deja en cours...).
 *
 * Mode annuler (argument "confirmer" obligatoire hors simulation) :
 * - fiches : supprime DEFINITIVEMENT les fiches portant _ditl_import_id et
 *   les medias crees par l'import qui ne servent plus, puis purge le cache
 *   de pages ;
 * - referentiels : supprime les termes CREES OU MIS A JOUR PAR L'IMPORT
 *   (marqueur _ditl_import_terme), toutes langues ; les termes saisis dans
 *   l'administration ne sont pas concernes.
 *
 * ============================ RUNBOOK PREPROD / PROD ============================
 * Prerequis : code du theme deploye avec le modele de la boite a outils
 * (inc/boite/) et ce moteur ; extension multilingue active. Serveur Nginx :
 * voir inc/import/travail.php (regle de refus d'acces au dossier de travail
 * de l'ecran d'administration, ou constante DITL_IMPORT_DOSSIER).
 * 1. Deposer les fichiers HORS de la racine web (sFTP), ex. ~/import/ :
 *    referentiels.csv, fiches.csv, et le dossier d'images ~/import/images/.
 * 2. REFERENTIELS d'abord (les fiches referencent leurs codes) :
 *      wp eval-file wp-content/themes/ditl/cli/importer-fiches.php referentiels ~/import/referentiels.csv dry-run --user=<admin>
 *      wp eval-file wp-content/themes/ditl/cli/importer-fiches.php referentiels ~/import/referentiels.csv --user=<admin>
 * 3. IMAGES : soit le dossier ~/import/images/ (etape 4), soit un depot
 *    prealable par la Mediatheque (glisser-deposer) sous le nom indique dans
 *    la colonne image.
 * 4. FICHES ensuite, simulation puis import :
 *      wp eval-file wp-content/themes/ditl/cli/importer-fiches.php fiches ~/import/fiches.csv images=~/import/images dry-run --user=<admin>
 *      wp eval-file wp-content/themes/ditl/cli/importer-fiches.php fiches ~/import/fiches.csv images=~/import/images --user=<admin>
 *    (le shell developpe "~" seulement en debut de mot : preferer un chemin
 *    absolu pour images=).
 * 5. Lire le recapitulatif : 0 erreur attendu ; avertissements a relire avec
 *    le client (longueurs, langues manquantes, images introuvables...).
 * 6. Un rejeu est sans risque (lignes inchangees = zero ecriture ; fiches
 *    retouchees dans l'admin ignorees sauf "ecraser").
 * 7. JAMAIS "annuler ... confirmer" en production apres le debut de la
 *    saisie ou de la relecture du client : suppression definitive.
 * 8. Supprimer ensuite les fichiers deposes a l'etape 1 (donnees du client).
 * ===============================================================================
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Usage : wp eval-file <script> <fiches|referentiels|annuler> ... - voir le docblock.
	// En acces web direct, reponse muette pour ne rien reveler du fichier.
	http_response_code( 404 );
	exit( 1 );
}

// Bibliotheque commune des scripts CLI du theme.
require_once __DIR__ . '/commun.php';

// Moteur d'import (deja charge si le theme le declare).
require_once dirname( __DIR__ ) . '/inc/import/import.php';

// ---------------------------------------------------------------------------
// Lecture des arguments.
// ---------------------------------------------------------------------------

$ditl_mode      = isset( $args[0] ) ? (string) $args[0] : '';
$ditl_cible     = isset( $args[1] ) ? (string) $args[1] : '';
$ditl_dry_run   = false;
$ditl_ecraser   = false;
$ditl_confirmer = false;
$ditl_images    = '';

foreach ( array_slice( (array) $args, 2 ) as $ditl_arg ) {
	$ditl_arg = (string) $ditl_arg;

	if ( 'dry-run' === $ditl_arg || '--dry-run' === $ditl_arg ) {
		$ditl_dry_run = true;
	} elseif ( 'ecraser' === $ditl_arg || '--ecraser' === $ditl_arg ) {
		$ditl_ecraser = true;
	} elseif ( 'confirmer' === $ditl_arg || '--confirmer' === $ditl_arg ) {
		$ditl_confirmer = true;
	} elseif ( 0 === strpos( $ditl_arg, 'images=' ) ) {
		$ditl_images = substr( $ditl_arg, 7 );
	} else {
		WP_CLI::warning( sprintf( 'Argument ignoré : %s', $ditl_arg ) );
	}
}

if ( ! in_array( $ditl_mode, array( 'fiches', 'referentiels', 'annuler' ), true ) || '' === $ditl_cible ) {
	WP_CLI::error( 'Usage : wp eval-file .../importer-fiches.php <fiches|referentiels> <fichier.csv> [images=<dossier>] [dry-run] [ecraser] | annuler <fiches|referentiels> <dry-run|confirmer>' );
}

$ditl_erreur_modele = ditl_import_verifier_modele();

if ( '' !== $ditl_erreur_modele ) {
	WP_CLI::error( $ditl_erreur_modele );
}

if ( $ditl_dry_run ) {
	WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucune écriture (base, médiathèque) ===' );
}

if ( 0 === get_current_user_id() && ! $ditl_dry_run && 'annuler' !== $ditl_mode ) {
	WP_CLI::warning( 'Aucun utilisateur (--user=<administrateur>) : les fiches créées n\'auront pas d\'auteur.' );
}

$ditl_import_en_cours = 'Un autre import est en cours d\'exécution (écran d\'administration ou ligne de commande) : réessayer plus tard.';

// ---------------------------------------------------------------------------
// Mode annuler.
// ---------------------------------------------------------------------------

if ( 'annuler' === $ditl_mode ) {
	if ( ! in_array( $ditl_cible, array( 'fiches', 'referentiels' ), true ) ) {
		WP_CLI::error( 'Usage : annuler <fiches|referentiels> <dry-run|confirmer>' );
	}

	if ( ! $ditl_dry_run && ! $ditl_confirmer ) {
		WP_CLI::error( 'Suppression définitive : ajouter l\'argument "confirmer" (ou "dry-run" pour simuler). JAMAIS en production après la saisie du client.' );
	}

	if ( 'fiches' === $ditl_cible ) {
		$ditl_purge = ditl_import_purger_fiches( $ditl_dry_run );

		if ( $ditl_purge['occupe'] ) {
			WP_CLI::error( $ditl_import_en_cours );
		}

		WP_CLI::log(
			sprintf(
				'%1$s : %2$d fiche(s) importée(s), %3$d média(s) créé(s) par l\'import%4$s.',
				$ditl_dry_run ? 'À supprimer' : 'Supprimé(s)',
				$ditl_purge['fiches'],
				$ditl_purge['medias'],
				$ditl_purge['medias_gardes'] > 0 ? sprintf( ' (%d média(s) conservé(s) : encore utilisé(s) ailleurs)', $ditl_purge['medias_gardes'] ) : ''
			)
		);
	} else {
		$ditl_purge = ditl_import_purger_referentiels( $ditl_dry_run );

		if ( $ditl_purge['occupe'] ) {
			WP_CLI::error( $ditl_import_en_cours );
		}

		WP_CLI::log( sprintf( '%1$s : %2$d terme(s) des référentiels créé(s) ou mis à jour par l\'import (toutes langues).', $ditl_dry_run ? 'À supprimer' : 'Supprimé(s)', $ditl_purge['termes'] ) );
	}

	WP_CLI::success( $ditl_dry_run ? 'Simulation terminée.' : 'Annulation terminée.' );
	return;
}

// ---------------------------------------------------------------------------
// Import.
// ---------------------------------------------------------------------------

$ditl_chemin = realpath( $ditl_cible );

if ( false === $ditl_chemin || ! is_file( $ditl_chemin ) ) {
	WP_CLI::error( sprintf( 'Fichier introuvable : %s', $ditl_cible ) );
}

if ( '' !== $ditl_images ) {
	if ( 'fiches' !== $ditl_mode ) {
		WP_CLI::warning( 'Argument images= ignoré (import des référentiels).' );
		$ditl_images = '';
	} else {
		$ditl_dossier_images = realpath( $ditl_images );

		if ( false === $ditl_dossier_images || ! is_dir( $ditl_dossier_images ) ) {
			WP_CLI::error( sprintf( 'Dossier d\'images introuvable : %s', $ditl_images ) );
		}

		$ditl_images = $ditl_dossier_images;
	}
}

$ditl_depart = microtime( true );
$ditl_plan   = ditl_import_preparer(
	$ditl_mode,
	$ditl_chemin,
	array(
		'images'  => $ditl_images,
		'ecraser' => $ditl_ecraser,
		'nom'     => wp_basename( $ditl_chemin ),
	)
);

WP_CLI::log( sprintf( 'Fichier : %1$s (%2$s, séparateur « %3$s », %4$s)', $ditl_plan['fichier'], $ditl_mode, "\t" === $ditl_plan['separateur'] ? 'tabulation' : $ditl_plan['separateur'], $ditl_plan['encodage'] ) );

foreach ( $ditl_plan['messages'] as $ditl_message ) {
	WP_CLI::log( sprintf( '[fichier] %1$s : %2$s', $ditl_message['niveau'], $ditl_message['texte'] ) );
}

if ( array() !== $ditl_plan['fatal'] ) {
	foreach ( $ditl_plan['fatal'] as $ditl_fatal ) {
		WP_CLI::log( sprintf( '[fichier] FATAL : %s', $ditl_fatal ) );
	}

	WP_CLI::error( 'Import impossible : aucune ligne traitée.' );
}

foreach ( $ditl_plan['groupes'] as $ditl_groupe => $ditl_messages ) {
	foreach ( $ditl_messages as $ditl_message ) {
		WP_CLI::log( sprintf( '[groupe %1$s] %2$s : %3$s', $ditl_groupe, $ditl_message['niveau'], $ditl_message['texte'] ) );
	}
}

if ( $ditl_ecraser ) {
	WP_CLI::log( 'Option écraser : les fiches modifiées dans l\'administration depuis le dernier import seront remplacées.' );
}

$ditl_execution = ditl_import_executer( $ditl_plan, 0, 0, $ditl_dry_run );

if ( $ditl_execution['occupe'] ) {
	WP_CLI::error( $ditl_import_en_cours );
}

foreach ( $ditl_execution['resultats'] as $ditl_resultat ) {
	WP_CLI::log(
		sprintf(
			'Ligne %1$d [%2$s%3$s] %4$s : %5$s%6$s',
			$ditl_resultat['ligne'],
			'fiches' === $ditl_mode ? 'id ' . $ditl_resultat['id'] : $ditl_resultat['groupe'] . ' ' . $ditl_resultat['id'],
			'' !== $ditl_resultat['lang'] ? ', ' . $ditl_resultat['lang'] : '',
			$ditl_resultat['titre'],
			ditl_import_libelle_action( $ditl_resultat['action'], $ditl_dry_run ),
			$ditl_resultat['objet'] > 0 ? sprintf( ' (%1$s %2$d)', 'fiches' === $ditl_mode ? 'fiche' : 'terme', $ditl_resultat['objet'] ) : ''
		)
	);

	foreach ( $ditl_resultat['messages'] as $ditl_message ) {
		WP_CLI::log( sprintf( '    %1$s : %2$s', $ditl_message['niveau'], $ditl_message['texte'] ) );
	}
}

$ditl_compteurs = ditl_import_compter( $ditl_execution['resultats'] );

WP_CLI::log( '' );
WP_CLI::log(
	sprintf(
		'Récapitulatif : %1$d ligne(s) ; %2$s %3$d ; %4$s %5$d ; inchangée(s) %6$d ; ignorée(s) %7$d ; rejetée(s) %8$d ; échec(s) %9$d ; lignes avec avertissement %10$d.',
		$ditl_compteurs['lignes'],
		$ditl_dry_run ? 'à créer' : 'créée(s)',
		$ditl_compteurs['creation'],
		$ditl_dry_run ? 'à mettre à jour' : 'mise(s) à jour',
		$ditl_compteurs['mise_a_jour'],
		$ditl_compteurs['inchangee'],
		$ditl_compteurs['ignoree'],
		$ditl_compteurs['rejetee'],
		$ditl_compteurs['echec'],
		$ditl_compteurs['avertissements']
	)
);
WP_CLI::log( sprintf( 'Durée : %1$.1f s ; pic mémoire : %2$.1f Mo.', microtime( true ) - $ditl_depart, memory_get_peak_usage( true ) / MB_IN_BYTES ) );

if ( $ditl_compteurs['rejetee'] > 0 || $ditl_compteurs['echec'] > 0 ) {
	WP_CLI::warning( 'Des lignes n\'ont pas été importées : corriger le fichier puis relancer (les lignes déjà importées seront inchangées).' );
}

WP_CLI::success( $ditl_dry_run ? 'Simulation terminée (aucune écriture).' : 'Import terminé.' );
