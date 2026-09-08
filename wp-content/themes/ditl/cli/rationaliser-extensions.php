<?php
/**
 * Rationalisation des extensions (phase 1 de la refonte).
 *
 * Supprime six extensions sans usage et purge les residus de deux
 * extensions fantomes, conformement a l'arbitrage du 08/09/2026 (inventaire
 * des extensions : usage reel, redondances, procedures de suppression).
 * PERIMETRE STRICT : aucune autre extension n'est touchee, aucune
 * configuration n'est modifiee (Complianz, AIOS, Yoast, Site Kit...
 * relevent d'un lot separe). Les mises a jour des extensions conservees
 * se font par "wp plugin update <slug>", hors de ce script.
 *
 * Pour chaque extension, dans l'ordre : desactivation si active,
 * desinstallation ("wp plugin uninstall" : execute uninstall.php ou le
 * hook register_uninstall_hook, puis supprime les fichiers), puis controle
 * et purge de ce que l'extension laisse derriere elle. Ce qui est purge :
 *
 * 1. presto-player (ACTIVE, 0 usage prouve : ni shortcode, ni bloc, ni
 *    video, ni asset en front) : son hook de desinstallation ne purge que
 *    si l'option presto_player_uninstall[uninstall_data] est cochee (elle
 *    ne l'est pas) -> le script supprime lui-meme les 6 tables
 *    wp_presto_player_*, les 13 options presto*, la usermeta
 *    presto-player-optin-notice et le dossier uploads/presto-player-private
 *    (vide).
 * 2. astra-sites (Starter Templates, importeur de demo, inactif) : pas
 *    d'uninstall.php -> options astra-sites*, astra_sites*, _astra_sites_*,
 *    ast-block-templates*, zipwp*, astra-blocks-batch-status-string et
 *    nps-survey-astra-sites ; postmeta d'import _wxr_import_user_slug,
 *    zipwp-images, ast_self_id_* et _uag_* (Spectra, extension absente,
 *    0 bloc wp:uagb/ dans les contenus) ; dossiers uploads/astra-sites,
 *    uploads/ast-block-templates-json, uploads/astra-docs,
 *    uploads/ai-builder (caches JSON et journaux d'import, ~20 Mo, non
 *    versionnes ; aucune reference en base).
 * 3. all-in-one-wp-migration (inactif, 0 trace) : uninstall.php purge
 *    ai1wm_* ; le script controle le reliquat.
 * 4. updraftplus (inactif, aucune planification) : pas d'uninstall.php ->
 *    29 options updraft* (dont 20 autoload), cron
 *    updraftplus_clean_temporary_files. Exclusion explicite des options
 *    updraft_lock_*, updraft_task_*, updraft_interrupt_tasks_queue_*,
 *    updraft_central* et updraftcentral* : bibliotheques Updraft (semaphore,
 *    gestionnaire de taches, lib-central) embarquees dans AIOS (meme
 *    editeur), qui appartiennent a AIOS et restent en place.
 * 5. wpvivid-backuprestore (inactif, dernier backup 01/2025) :
 *    uninstall.php purge la majorite des options (dont deux a nom
 *    generique qui lui appartiennent : clean_task, cron_backup_count) ; le
 *    script controle le reliquat wpvivid*. Les dossiers wp-content/wpvividbackups,
 *    wpvivid_staging et wpvivid_uploads ne sont PAS supprimes par ce
 *    script (voir le runbook : archivage prealable, decision client).
 * 6. post-grid-elementor-addon (inactif depuis le 14/08, 0 widget meme
 *    dans les pages ES/PT/DE non migrees) : option
 *    post_grid_elementor_addon_wpan_time. Le mode annuler de
 *    cli/desactiver-elementor.php ne tente plus de le reactiver.
 * 7. Fantomes (extensions ABSENTES du disque, traces en base) :
 *    BackWPup -> options backwpup_* et bwpup_*, table wp_bwpup_backups,
 *    cron backwpup_check_cleanup ; Imagify -> 153 postmeta _imagify_*,
 *    cron imagify_sync_files.
 *
 * Ce qui est LAISSE volontairement : elementor et ultimate-post-kit
 * (inactifs, differes en phase 2 avec la purge des metas _elementor_*),
 * polylang (remplace par WPML en phase 2), toutes les extensions
 * conservees et leur configuration, les options du theme parent Astra
 * (astra-settings, astra_*, dont les prefixes sont proches de ceux de
 * astra-sites : les motifs LIKE sont echappes et bornes pour ne pas les
 * atteindre), les fichiers hors extension (.user.ini, mu-plugin AIOS,
 * .htaccess).
 *
 * Garde-fous : requetes preparees systematiques ($wpdb->prepare, motifs
 * LIKE passes par $wpdb->esc_like) ; avant tout DROP TABLE et toute purge
 * de residus, verification qu'aucune extension susceptible de reclamer ces
 * donnees n'est presente sur le disque (liste "reclamants" par
 * extension : la purge est sautee avec avertissement sinon) ; noms de
 * tables issus d'une liste fixe et valides par expression reguliere ;
 * suppression de dossiers limitee a la liste fixe ci-dessus, sous
 * wp-content/uploads uniquement, chemin resolu par realpath, jamais de
 * lien symbolique suivi. Aucun mode annuler : la suppression est
 * irreversible par nature, le retour arriere passe par git (fichiers) et
 * une reinstallation depuis wordpress.org (les donnees purgees etaient
 * des valeurs d'usine ou des journaux sans valeur).
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod) : un
 * rejeu sans changement n'ecrit rien et le dit (bilan des ecritures).
 *
 * ============================ RUNBOOK PREPROD / PROD ============================
 * Les FICHIERS des six extensions partent du depot git (suppressions
 * constatees en local apres desinstallation, puis commitees). Leur
 * uninstall.php / hook de desinstallation ne peut s'executer que si les
 * fichiers sont encore sur le disque : ce script doit donc tourner sur
 * chaque environnement AVANT le deploiement du code qui retire les
 * fichiers. Si l'ordre n'a pas pu etre respecte (fichiers deja absents),
 * le script le detecte, saute la desinstallation et purge quand meme tous
 * les residus connus (options, metas, tables, crons, dossiers, y compris
 * les options a nom generique listees par extension) : le nettoyage propre
 * a uninstall.php est alors remplace par les purges du script.
 * 0. EN PRODUCTION, AVANT TOUT : wp-content/wpvividbackups (262 Mo,
 *    janvier 2025) contient une sauvegarde complete d'un AUTRE site du
 *    client. Faire valider par le client sa destruction ou son archivage,
 *    puis le deplacer HORS de l'arborescence web (rsync/scp puis rm), avec
 *    wpvivid_staging et wpvivid_uploads. Ce script ne le fait pas et le
 *    rappelle tant que le dossier est present. L'uninstall.php de WPvivid
 *    peut vider ce dossier si l'option uninstall_clear_folder est cochee :
 *    l'archivage doit preceder l'execution.
 * 1. Simulation :  wp eval-file wp-content/themes/ditl/cli/rationaliser-extensions.php dry-run
 *    Lire le plan : extensions a desinstaller, options/metas/tables/crons/
 *    dossiers qui seraient purges.
 * 2. Execution :   wp eval-file wp-content/themes/ditl/cli/rationaliser-extensions.php
 * 3. Rejeu :       meme commande, attendu "aucune ecriture".
 * 4. Deployer le code (les six dossiers d'extensions n'y sont plus). Si le
 *    deploiement est un "git pull", les suppressions locales coincident avec
 *    celles du commit et ne provoquent pas de conflit ; verifier avec
 *    "git status" que rien d'inattendu ne subsiste.
 * 5. Controles : wp plugin list (15 extensions), SHOW TABLES LIKE 'wp_presto%'
 *    et 'wp_bwpup%' (vides), wp cron event list (plus d'updraft/backwpup/
 *    imagify), rendu des pages (aucune des six extensions ne chargeait
 *    d'asset en front : HTML attendu inchange). Dossiers de sauvegarde
 *    hors uploads eventuellement presents en prod (wp-content/updraft,
 *    wp-content/ai1wm-backups) : le script les signale, a archiver puis
 *    supprimer a la main.
 * ===============================================================================
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/rationaliser-extensions.php dry-run
 *   wp eval-file wp-content/themes/ditl/cli/rationaliser-extensions.php
 *
 * Le mode simulation accepte "dry-run" ou "--dry-run". Le mode "annuler"
 * est refuse explicitement (voir ci-dessus).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Usage : wp eval-file <script> [dry-run] - voir le docblock.
	// En acces web direct, reponse muette pour ne rien reveler du fichier.
	http_response_code( 404 );
	exit( 1 );
}

// Bibliotheque commune des scripts CLI du theme.
require_once __DIR__ . '/commun.php';

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// ---------------------------------------------------------------------------
// Lecture des arguments : simulation uniquement (pas de mode annuler).
// ---------------------------------------------------------------------------

$ditl_modes   = ditl_cli_lire_modes( $args );
$ditl_dry_run = $ditl_modes['dry_run'];

if ( $ditl_modes['annuler'] ) {
	WP_CLI::error( 'Ce script n\'a pas de mode annuler : la suppression d\'extensions est irreversible (retour arriere par git + reinstallation, voir le docblock).' );
}

if ( $ditl_dry_run ) {
	WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucune ecriture (fichiers, base, crons) ===' );
}

// eval-file n'execute pas forcement le script dans la portee globale.
global $wpdb;

// Compteur d'ecritures effectives (idempotence : rejeu = zero ecriture).
$ditl_ecritures = 0;

// ---------------------------------------------------------------------------
// Cibles : une entree par extension (ou fantome), listes FIXES.
// ---------------------------------------------------------------------------

// Cles de chaque entree :
// - fichier            : fichier principal de l'extension ('' pour un fantome).
// - reclamants         : slugs d'extensions dont la presence sur le disque
//                        interdit la purge des residus (l'extension elle-meme
//                        et ses variantes pro, ou l'extension proprietaire des
//                        metas partagees).
// - options            : noms exacts ; options_prefixes : debuts de nom ;
//                        options_exclure_prefixes : debuts de nom ecartes
//                        (options d'une extension conservee au prefixe voisin).
// - postmeta / postmeta_prefixes, usermeta_prefixes : idem sur les metas.
// - tables             : noms sans le prefixe $wpdb->prefix.
// - crons_prefixes     : debuts de nom de hook cron.
// - dossiers           : chemins relatifs a wp-content, sous uploads/ seulement.
$ditl_cibles = array(
	'presto-player'             => array(
		'nom'              => 'Presto Player',
		'fichier'          => 'presto-player/presto-player.php',
		'reclamants'       => array( 'presto-player', 'presto-player-pro' ),
		'options_prefixes' => array( 'presto' ),
		'usermeta_prefixes' => array( 'presto' ),
		'tables'           => array(
			'presto_player_audio_presets',
			'presto_player_email_collection',
			'presto_player_presets',
			'presto_player_videos',
			'presto_player_visits',
			'presto_player_webhooks',
		),
		'crons_prefixes'   => array( 'presto' ),
		'dossiers'         => array( 'uploads/presto-player-private' ),
	),
	'astra-sites'               => array(
		'nom'              => 'Starter Templates (astra-sites)',
		'fichier'          => 'astra-sites/astra-sites.php',
		'reclamants'       => array( 'astra-sites', 'astra-pro-sites', 'ultimate-addons-for-gutenberg', 'wordpress-importer' ),
		'options'          => array( 'astra-blocks-batch-status-string', 'nps-survey-astra-sites' ),
		'options_prefixes' => array( 'astra-sites', 'astra_sites', '_astra_sites_', 'ast-block-templates', 'zipwp' ),
		'postmeta'         => array( '_wxr_import_user_slug', 'zipwp-images' ),
		'postmeta_prefixes' => array( 'ast_self_id_', '_uag_' ),
		'crons_prefixes'   => array( 'astra_sites', 'astra-sites', 'ast-block-templates' ),
		'dossiers'         => array(
			'uploads/astra-sites',
			'uploads/ast-block-templates-json',
			'uploads/astra-docs',
			'uploads/ai-builder',
		),
	),
	'all-in-one-wp-migration'   => array(
		'nom'              => 'All-in-One WP Migration',
		'fichier'          => 'all-in-one-wp-migration/all-in-one-wp-migration.php',
		'reclamants'       => array( 'all-in-one-wp-migration' ),
		'options_prefixes' => array( 'ai1wm_' ),
		'crons_prefixes'   => array( 'ai1wm_' ),
	),
	'updraftplus'               => array(
		'nom'              => 'UpdraftPlus',
		'fichier'          => 'updraftplus/updraftplus.php',
		'reclamants'       => array( 'updraftplus' ),
		'options_prefixes' => array( 'updraft' ),
		// Options des bibliotheques Updraft embarquees dans AIOS (meme
		// editeur) : verrous Updraft_Semaphore (updraft_lock_*, ex.
		// updraft_lock_aios_15_minutes_cron_event), gestionnaire de taches
		// updraft-tasks (updraft_task_*, dont updraft_task_manager_dbversion
		// recree a chaque chargement d'AIOS, updraft_interrupt_tasks_queue_*)
		// et lib-central (updraft_central*, updraftcentral*). Elles
		// appartiennent a AIOS, conservee : jamais purgees.
		'options_exclure_prefixes' => array( 'updraft_lock_', 'updraft_task_', 'updraft_interrupt_tasks_queue_', 'updraft_central', 'updraftcentral' ),
		'crons_prefixes'   => array( 'updraft' ),
	),
	'wpvivid-backuprestore'     => array(
		'nom'              => 'WPvivid Backup',
		'fichier'          => 'wpvivid-backuprestore/wpvivid-backuprestore.php',
		'reclamants'       => array( 'wpvivid-backuprestore', 'wpvivid-backup-pro' ),
		// Deux options a nom generique posees par WPvivid (purgees par son
		// uninstall.php quand il peut tourner, par le script sinon).
		'options'          => array( 'clean_task', 'cron_backup_count' ),
		'options_prefixes' => array( 'wpvivid' ),
		'crons_prefixes'   => array( 'wpvivid' ),
	),
	'post-grid-elementor-addon' => array(
		'nom'              => 'Post Grid Elementor Addon',
		'fichier'          => 'post-grid-elementor-addon/post-grid-elementor-addon.php',
		'reclamants'       => array( 'post-grid-elementor-addon' ),
		'options'          => array( 'post_grid_elementor_addon_wpan_time' ),
	),
	'backwpup'                  => array(
		'nom'              => 'BackWPup (fantome : extension absente)',
		'fichier'          => '',
		'reclamants'       => array( 'backwpup', 'backwpup-pro' ),
		'options_prefixes' => array( 'backwpup_', 'bwpup_' ),
		'tables'           => array( 'bwpup_backups' ),
		'crons_prefixes'   => array( 'backwpup' ),
	),
	'imagify'                   => array(
		'nom'              => 'Imagify (fantome : extension absente)',
		'fichier'          => '',
		'reclamants'       => array( 'imagify' ),
		'postmeta_prefixes' => array( '_imagify_' ),
		'crons_prefixes'   => array( 'imagify' ),
	),
);

// ---------------------------------------------------------------------------
// Outils locaux.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'ditl_ext_reclamants_presents' ) ) {
	/**
	 * Liste les extensions "reclamantes" encore presentes sur le disque.
	 *
	 * @param array  $slugs        Slugs a controler (dossiers de wp-content/plugins).
	 * @param string $slug_exclu   Slug considere comme parti (simulation de la
	 *                             desinstallation en dry-run), '' sinon.
	 * @return array Slugs presents.
	 */
	function ditl_ext_reclamants_presents( $slugs, $slug_exclu = '' ) {
		$presents = array();

		foreach ( (array) $slugs as $slug ) {
			if ( $slug === $slug_exclu ) {
				continue;
			}

			if ( is_dir( WP_PLUGIN_DIR . '/' . $slug ) ) {
				$presents[] = $slug;
			}
		}

		return $presents;
	}
}

if ( ! function_exists( 'ditl_ext_options_ciblees' ) ) {
	/**
	 * Liste les options correspondant a des noms exacts et a des prefixes.
	 *
	 * @param array $noms       Noms exacts.
	 * @param array $prefixes   Debuts de nom (echappes pour LIKE).
	 * @param array $exclusions Debuts de nom a ecarter du resultat (options
	 *                          d'une extension conservee au prefixe voisin).
	 * @return string[] Noms d'options presents en base, tries.
	 */
	function ditl_ext_options_ciblees( $noms, $prefixes, $exclusions = array() ) {
		global $wpdb;

		$trouvees = array();

		foreach ( (array) $noms as $nom ) {
			$trouvees = array_merge( $trouvees, (array) $wpdb->get_col( $wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name = %s",
				$nom
			) ) );
		}

		foreach ( (array) $prefixes as $prefixe ) {
			$trouvees = array_merge( $trouvees, (array) $wpdb->get_col( $wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefixe ) . '%'
			) ) );
		}

		foreach ( (array) $exclusions as $exclusion ) {
			$trouvees = array_filter( $trouvees, static function ( $nom ) use ( $exclusion ) {
				return 0 !== strpos( (string) $nom, (string) $exclusion );
			} );
		}

		$trouvees = array_values( array_unique( $trouvees ) );
		sort( $trouvees );

		return $trouvees;
	}
}

if ( ! function_exists( 'ditl_ext_metas_ciblees' ) ) {
	/**
	 * Liste les cles de metas (avec leur nombre de lignes) correspondant a
	 * des noms exacts et a des prefixes, dans wp_postmeta ou wp_usermeta.
	 *
	 * @param string $type     'post' ou 'user'.
	 * @param array  $noms     Cles exactes.
	 * @param array  $prefixes Debuts de cle (echappes pour LIKE).
	 * @return array Tableau cle => nombre de lignes.
	 */
	function ditl_ext_metas_ciblees( $type, $noms, $prefixes ) {
		global $wpdb;

		$table    = 'user' === $type ? $wpdb->usermeta : $wpdb->postmeta;
		$trouvees = array();

		foreach ( (array) $noms as $nom ) {
			$lignes = $wpdb->get_results( $wpdb->prepare(
				"SELECT meta_key, COUNT(*) AS total FROM {$table} WHERE meta_key = %s GROUP BY meta_key",
				$nom
			) );
			foreach ( (array) $lignes as $ligne ) {
				$trouvees[ $ligne->meta_key ] = (int) $ligne->total;
			}
		}

		foreach ( (array) $prefixes as $prefixe ) {
			$lignes = $wpdb->get_results( $wpdb->prepare(
				"SELECT meta_key, COUNT(*) AS total FROM {$table} WHERE meta_key LIKE %s GROUP BY meta_key",
				$wpdb->esc_like( $prefixe ) . '%'
			) );
			foreach ( (array) $lignes as $ligne ) {
				$trouvees[ $ligne->meta_key ] = (int) $ligne->total;
			}
		}

		ksort( $trouvees );

		return $trouvees;
	}
}

if ( ! function_exists( 'ditl_ext_table_existe' ) ) {
	/**
	 * Verifie l'existence d'une table (nom complet, prefixe inclus).
	 *
	 * @param string $table Nom complet de la table.
	 * @return bool
	 */
	function ditl_ext_table_existe( $table ) {
		global $wpdb;

		return (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === (string) $table;
	}
}

if ( ! function_exists( 'ditl_ext_crons_cibles' ) ) {
	/**
	 * Liste les hooks cron dont le nom commence par l'un des prefixes.
	 *
	 * @param array $prefixes Debuts de nom de hook.
	 * @return string[] Hooks presents, tries.
	 */
	function ditl_ext_crons_cibles( $prefixes ) {
		$hooks = array();

		foreach ( (array) _get_cron_array() as $evenements ) {
			foreach ( array_keys( (array) $evenements ) as $hook ) {
				foreach ( (array) $prefixes as $prefixe ) {
					if ( 0 === strpos( (string) $hook, $prefixe ) ) {
						$hooks[] = (string) $hook;
						break;
					}
				}
			}
		}

		$hooks = array_values( array_unique( $hooks ) );
		sort( $hooks );

		return $hooks;
	}
}

if ( ! function_exists( 'ditl_ext_supprimer_dossier_uploads' ) ) {
	/**
	 * Supprime recursivement un dossier, uniquement s'il est situe sous
	 * wp-content/uploads et n'est pas un lien symbolique (garde-fou contre
	 * toute suppression hors perimetre). Les liens symboliques rencontres
	 * a l'interieur sont supprimes comme liens, jamais suivis.
	 *
	 * @param string $dossier Chemin absolu du dossier a supprimer.
	 * @return bool Vrai si le dossier n'existe plus a la sortie.
	 */
	function ditl_ext_supprimer_dossier_uploads( $dossier ) {
		$racine = realpath( WP_CONTENT_DIR . '/uploads' );
		$reel   = realpath( $dossier );

		if ( false === $reel || false === $racine || is_link( $dossier ) || 0 !== strpos( $reel, $racine . DIRECTORY_SEPARATOR ) ) {
			return ! is_dir( $dossier );
		}

		$elements = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $reel, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $elements as $element ) {
			if ( $element->isDir() && ! $element->isLink() ) {
				rmdir( $element->getPathname() );
			} else {
				unlink( $element->getPathname() );
			}
		}

		return rmdir( $reel );
	}
}

// ---------------------------------------------------------------------------
// Traitement extension par extension.
// ---------------------------------------------------------------------------

foreach ( $ditl_cibles as $ditl_slug => $ditl_cible ) {
	WP_CLI::log( '' );
	WP_CLI::log( sprintf( '--- %s ---', $ditl_cible['nom'] ) );

	$ditl_fichier          = isset( $ditl_cible['fichier'] ) ? (string) $ditl_cible['fichier'] : '';
	$ditl_slug_simule_parti = '';

	// 1. Desactivation puis desinstallation (extensions presentes seulement).
	if ( '' !== $ditl_fichier ) {
		if ( ! is_dir( WP_PLUGIN_DIR . '/' . $ditl_slug ) ) {
			WP_CLI::log( '  Fichiers deja absents du disque : desinstallation sautee, controle des residus seulement.' );

			// Une extension retiree du disque mais toujours listee dans
			// active_plugins provoquerait une erreur a chaque chargement :
			// on la retire de la liste des actives.
			if ( is_plugin_active( $ditl_fichier ) ) {
				if ( $ditl_dry_run ) {
					WP_CLI::log( '  [dry-run] Encore listee dans active_plugins : serait retiree de la liste.' );
				} else {
					deactivate_plugins( $ditl_fichier, true );
					WP_CLI::log( '  Retiree de active_plugins (fichiers absents).' );
					$ditl_ecritures++;
				}
			}
		} else {
			if ( is_plugin_active( $ditl_fichier ) ) {
				if ( $ditl_dry_run ) {
					WP_CLI::log( '  [dry-run] Active : serait desactivee.' );
				} else {
					deactivate_plugins( $ditl_fichier );
					WP_CLI::log( '  DESACTIVEE.' );
					$ditl_ecritures++;
				}
			} else {
				WP_CLI::log( '  Deja inactive.' );
			}

			if ( $ditl_dry_run ) {
				WP_CLI::log( '  [dry-run] Serait desinstallee (uninstall.php ou hook, puis suppression des fichiers).' );
				$ditl_slug_simule_parti = $ditl_slug;
			} else {
				// Desinstallation dans un processus WP-CLI separe : l'extension
				// y est inactive des le depart (hooks de desinstallation
				// executes dans un contexte propre), exactement comme la
				// commande "wp plugin uninstall" du runbook.
				$ditl_resultat = WP_CLI::runcommand( 'plugin uninstall ' . $ditl_slug, array(
					'launch'     => true,
					'return'     => 'all',
					'exit_error' => false,
				) );

				foreach ( preg_split( '/\r?\n/', trim( (string) $ditl_resultat->stdout . "\n" . (string) $ditl_resultat->stderr ) ) as $ditl_ligne ) {
					if ( '' !== trim( $ditl_ligne ) ) {
						WP_CLI::log( '    ' . trim( $ditl_ligne ) );
					}
				}

				if ( 0 !== (int) $ditl_resultat->return_code || is_dir( WP_PLUGIN_DIR . '/' . $ditl_slug ) ) {
					WP_CLI::warning( sprintf( '%s : desinstallation incomplete (code %d), fichiers encore presents. Purge des residus SAUTEE pour cette extension.', $ditl_slug, (int) $ditl_resultat->return_code ) );
					continue;
				}

				WP_CLI::log( '  DESINSTALLEE (fichiers supprimes ; en local, git constate les suppressions).' );
				$ditl_ecritures++;
			}
		}
	} else {
		WP_CLI::log( '  Extension absente du disque (fantome) : purge des residus seulement.' );
	}

	// 2. Garde-fou : aucune extension reclamante ne doit etre presente.
	$ditl_presents = ditl_ext_reclamants_presents( isset( $ditl_cible['reclamants'] ) ? $ditl_cible['reclamants'] : array(), $ditl_slug_simule_parti );

	if ( array() !== $ditl_presents ) {
		WP_CLI::warning( sprintf( 'Extension(s) presente(s) susceptible(s) de reclamer ces donnees : %s. Purge des residus SAUTEE.', implode( ', ', $ditl_presents ) ) );
		continue;
	}

	// 3. Options (noms exacts et prefixes).
	$ditl_options = ditl_ext_options_ciblees(
		isset( $ditl_cible['options'] ) ? $ditl_cible['options'] : array(),
		isset( $ditl_cible['options_prefixes'] ) ? $ditl_cible['options_prefixes'] : array(),
		isset( $ditl_cible['options_exclure_prefixes'] ) ? $ditl_cible['options_exclure_prefixes'] : array()
	);

	if ( array() === $ditl_options ) {
		WP_CLI::log( '  Options : aucune, rien a faire.' );
	} elseif ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] Options : %d seraient purgees (%s).', count( $ditl_options ), implode( ', ', $ditl_options ) ) );
	} else {
		$ditl_purgees = 0;
		foreach ( $ditl_options as $ditl_option ) {
			if ( delete_option( $ditl_option ) ) {
				$ditl_purgees++;
			}
		}
		WP_CLI::log( sprintf( '  Options : %d PURGEES sur %d (%s).', $ditl_purgees, count( $ditl_options ), implode( ', ', $ditl_options ) ) );
		$ditl_ecritures += $ditl_purgees;
	}

	// 4. Metas (postmeta puis usermeta).
	foreach ( array( 'post' => 'postmeta', 'user' => 'usermeta' ) as $ditl_type_meta => $ditl_libelle_meta ) {
		$ditl_metas = ditl_ext_metas_ciblees(
			$ditl_type_meta,
			isset( $ditl_cible[ $ditl_libelle_meta ] ) ? $ditl_cible[ $ditl_libelle_meta ] : array(),
			isset( $ditl_cible[ $ditl_libelle_meta . '_prefixes' ] ) ? $ditl_cible[ $ditl_libelle_meta . '_prefixes' ] : array()
		);

		if ( array() === $ditl_metas ) {
			if ( isset( $ditl_cible[ $ditl_libelle_meta ] ) || isset( $ditl_cible[ $ditl_libelle_meta . '_prefixes' ] ) ) {
				WP_CLI::log( sprintf( '  %s : aucune, rien a faire.', ucfirst( $ditl_libelle_meta ) ) );
			}
			continue;
		}

		$ditl_total_lignes = array_sum( $ditl_metas );
		$ditl_detail       = array();

		foreach ( $ditl_metas as $ditl_cle => $ditl_nombre ) {
			$ditl_detail[] = sprintf( '%s x%d', $ditl_cle, $ditl_nombre );
		}

		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] %s : %d ligne(s) seraient purgees (%s).', ucfirst( $ditl_libelle_meta ), $ditl_total_lignes, implode( ', ', $ditl_detail ) ) );
		} else {
			// Suppression par cle via l'API metas (caches invalides), la
			// cle provenant de la lecture prealable en base.
			foreach ( array_keys( $ditl_metas ) as $ditl_cle ) {
				delete_metadata( $ditl_type_meta, 0, $ditl_cle, '', true );
			}
			WP_CLI::log( sprintf( '  %s : %d ligne(s) PURGEES (%s).', ucfirst( $ditl_libelle_meta ), $ditl_total_lignes, implode( ', ', $ditl_detail ) ) );
			$ditl_ecritures += $ditl_total_lignes;
		}
	}

	// 5. Tables dediees.
	foreach ( isset( $ditl_cible['tables'] ) ? (array) $ditl_cible['tables'] : array() as $ditl_table_courte ) {
		$ditl_table = $wpdb->prefix . $ditl_table_courte;

		// Nom issu de la liste fixe ci-dessus ; controle de forme avant de
		// l'inserer dans un DROP TABLE (les identifiants ne se preparent pas).
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $ditl_table ) ) {
			WP_CLI::warning( sprintf( 'Nom de table refuse : %s', $ditl_table ) );
			continue;
		}

		if ( ! ditl_ext_table_existe( $ditl_table ) ) {
			WP_CLI::log( sprintf( '  Table %s : deja absente.', $ditl_table ) );
		} elseif ( $ditl_dry_run ) {
			$ditl_lignes_table = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$ditl_table}`" );
			WP_CLI::log( sprintf( '  [dry-run] Table %s : serait supprimee (%d ligne(s)).', $ditl_table, $ditl_lignes_table ) );
		} else {
			$wpdb->query( "DROP TABLE `{$ditl_table}`" );

			if ( ditl_ext_table_existe( $ditl_table ) ) {
				WP_CLI::warning( sprintf( 'Table %s : suppression echouee (%s).', $ditl_table, $wpdb->last_error ) );
			} else {
				WP_CLI::log( sprintf( '  Table %s : SUPPRIMEE.', $ditl_table ) );
				$ditl_ecritures++;
			}
		}
	}

	// 6. Evenements cron orphelins.
	if ( isset( $ditl_cible['crons_prefixes'] ) ) {
		$ditl_hooks = ditl_ext_crons_cibles( $ditl_cible['crons_prefixes'] );

		if ( array() === $ditl_hooks ) {
			WP_CLI::log( '  Crons : aucun, rien a faire.' );
		} else {
			foreach ( $ditl_hooks as $ditl_hook ) {
				if ( $ditl_dry_run ) {
					WP_CLI::log( sprintf( '  [dry-run] Cron %s : serait supprime.', $ditl_hook ) );
				} else {
					// Toutes les occurrences du hook, quels que soient les args.
					wp_unschedule_hook( $ditl_hook );
					WP_CLI::log( sprintf( '  Cron %s : SUPPRIME.', $ditl_hook ) );
					$ditl_ecritures++;
				}
			}
		}
	}

	// 7. Dossiers (liste fixe, sous wp-content/uploads uniquement).
	foreach ( isset( $ditl_cible['dossiers'] ) ? (array) $ditl_cible['dossiers'] : array() as $ditl_dossier_relatif ) {
		$ditl_dossier = WP_CONTENT_DIR . '/' . $ditl_dossier_relatif;

		if ( 0 !== strpos( $ditl_dossier_relatif, 'uploads/' ) ) {
			WP_CLI::warning( sprintf( 'Dossier refuse (hors uploads/) : %s', $ditl_dossier_relatif ) );
			continue;
		}

		if ( ! is_dir( $ditl_dossier ) && ! is_link( $ditl_dossier ) ) {
			WP_CLI::log( sprintf( '  Dossier wp-content/%s : deja absent.', $ditl_dossier_relatif ) );
		} elseif ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] Dossier wp-content/%s : serait supprime.', $ditl_dossier_relatif ) );
		} elseif ( ditl_ext_supprimer_dossier_uploads( $ditl_dossier ) ) {
			WP_CLI::log( sprintf( '  Dossier wp-content/%s : SUPPRIME.', $ditl_dossier_relatif ) );
			$ditl_ecritures++;
		} else {
			WP_CLI::warning( sprintf( 'Dossier wp-content/%s : suppression refusee ou incomplete (hors uploads, lien symbolique ou permissions).', $ditl_dossier_relatif ) );
		}
	}
}

// ---------------------------------------------------------------------------
// Rappels (aucune modification ici).
// ---------------------------------------------------------------------------

WP_CLI::log( '' );
WP_CLI::log( '--- Rappels ---' );

foreach ( array( 'wpvividbackups', 'wpvivid_staging', 'wpvivid_uploads' ) as $ditl_dossier_wpvivid ) {
	if ( is_dir( WP_CONTENT_DIR . '/' . $ditl_dossier_wpvivid ) ) {
		WP_CLI::warning( sprintf( 'wp-content/%s encore present : a archiver HORS de l\'arborescence web puis a supprimer a la main (decision client pour wpvividbackups, voir le runbook). Ce script ne le supprime pas.', $ditl_dossier_wpvivid ) );
	}
}

foreach ( array( 'updraft', 'ai1wm-backups' ) as $ditl_dossier_sauvegarde ) {
	if ( is_dir( WP_CONTENT_DIR . '/' . $ditl_dossier_sauvegarde ) ) {
		WP_CLI::warning( sprintf( 'wp-content/%s present (repertoire de sauvegarde d\'une extension supprimee) : a archiver puis a supprimer a la main. Ce script ne le supprime pas.', $ditl_dossier_sauvegarde ) );
	}
}

WP_CLI::log( '  Les fichiers des extensions desinstallees sont retires du versionnement par le commit qui suit (git constate les suppressions).' );
WP_CLI::log( '  elementor et ultimate-post-kit restent en place (inactifs, phase 2) ; polylang reste (WPML en phase 2).' );
WP_CLI::log( '  Aucune des extensions supprimees ne chargeait d\'asset en front : le HTML des pages est attendu inchange.' );

WP_CLI::log( '' );

if ( $ditl_dry_run ) {
	WP_CLI::log( 'Simulation terminee.' );
} elseif ( 0 === $ditl_ecritures ) {
	WP_CLI::log( 'Rationalisation terminee : aucune ecriture, etat deja conforme.' );
} else {
	WP_CLI::log( sprintf( 'Rationalisation terminee (%d ecriture(s) lors de ce passage).', $ditl_ecritures ) );
}
