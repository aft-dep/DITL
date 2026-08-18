<?php
/**
 * Configuration du cache WP Fastest Cache (phase 1 de la refonte).
 *
 * Perimetre CONSERVATEUR decide en cadrage : cache de page via mod_rewrite,
 * preload, purge automatique quotidienne, purge a la publication et a la
 * mise a jour d'un contenu, en-tetes Expires sur les statiques (Browser
 * Caching) et compression gzip. PAS de minify HTML, PAS de minify/combine
 * CSS/JS (chantier separe : le HTML servi par le cache doit rester
 * byte-identique au HTML non cache, au commentaire de trace du plugin
 * pres), PAS de lazy load, PAS de CDN, aucune option premium.
 *
 * L'option "Mobile" du plugin reste NON cochee : verification faite dans le
 * code (inc/cache.php, methodes set_cache_file_path et getHtaccess), en
 * version gratuite cette case ne fait qu'EXCLURE les mobiles du cache
 * (aucun fichier servi ni cree pour eux). Le theme Astra etant responsive
 * (meme HTML pour tous), les mobiles profitent du meme cache que le bureau.
 *
 * Exclusions de cache posees (option WpFastestCacheExclude) :
 * - /fr/contact/ et /contact-us/ : le formulaire WPForms embarque un jeton
 *   anti-spam dans le HTML. WPForms 1.9.9.4 accepte cote serveur des jetons
 *   vieux de 5 ans (src/Forms/Token.php, get_valid_tokens) et rafraichit le
 *   jeton en AJAX au-dela de 24 h, mais ces deux garde-fous sont filtrables
 *   et ont deja change d'une version a l'autre (2 jours avant la 1.8.8) :
 *   l'exclusion est le choix robuste, le cout est nul (2 pages).
 * - URLs en .xml : sitemaps Yoast (sitemap_index.xml, *-sitemap.xml).
 *   Le mod_rewrite ne les sert de toute facon pas (regle "trailing slash"),
 *   mais le cache PHP du plugin sait cacher du XML : exclusion explicite.
 * - flux RSS/Atom (segment "feed" de l'URL) : contenu date, cache sans
 *   interet ici. Regle posee en regex "(^|\/)feed(\/|$)" et non en
 *   "contain /feed" : dans exclude_page (inc/cache.php), les regles
 *   "exact" retirent les slashes de l'URL comparee pour les regles
 *   suivantes (variable mutee en boucle), une regle contain avec slash
 *   initial ne matche donc plus /feed/ apres elles. La regex, elle, est
 *   insensible a l'ordre des regles (verifie par test direct).
 * - robots.txt : genere dynamiquement (Yoast + regles iso-coeur du theme).
 * Les pages 404 ne sont jamais cachees par le plugin (controle en dur dans
 * inc/cache.php via is_404), les URLs a chaine de requete non plus (regle
 * QUERY_STRING du bloc mod_rewrite + controle PHP).
 *
 * Utilisateurs connectes : jamais servis par le cache. Cote PHP c'est le
 * comportement en dur du plugin (is_user_logged_in) ; cote mod_rewrite la
 * condition sur le cookie wordpress_logged_in n'est ecrite QUE si l'option
 * wpFastestCacheLoggedInUser est cochee - ce script la coche.
 *
 * Purges automatiques :
 * - quotidienne a 04:00 UTC : evenement cron "wp_fastest_cache_0" (format
 *   du Cache Timeout du plugin, args {"prefix":"all","content":"all",...}).
 *   La purge complete relance d'elle-meme le preload (deleteCache appelle
 *   set_preload qui remet les pointeurs a zero et reprogramme le cron) ;
 * - a la publication d'un contenu : purge complete (option NewPost "all") ;
 * - a la mise a jour d'un contenu : purge complete (option UpdatePost
 *   "all"). Choix assume : une purge partielle laisserait des listes
 *   obsoletes (accueil, /news/, archives) jusqu'a la purge quotidienne.
 *   Le site publie rarement, le preload rechauffe derriere.
 *
 * Preload : accueil + pages + articles, 6 URLs par passage de cron (toutes
 * les 5 minutes) - cadence volontairement douce pour l'hebergement mutualise.
 *
 * ECRITURE DU .HTACCESS : poser les options en base ne suffit pas, c'est
 * l'ecran d'options du plugin qui ecrit les blocs. Ce script reproduit
 * exactement la sauvegarde admin : il pose $_POST comme le formulaire
 * (getHtaccess lit $_POST pour les conditions Mobile et logged-in) puis
 * appelle WpFastestCacheAdmin::modifyHtaccess, qui execute tous les
 * controles du plugin et ecrit les blocs WpFastestCache, GzipWpFastestCache
 * et LBCWpFastestCache EN TETE du fichier, donc avant les blocs AIOS et
 * WordPress (comportement standard du plugin, blocs AIOS intacts).
 * L'ecriture est sautee si les blocs cibles sont deja en place (la ligne
 * "# Modified Time" du plugin est ignoree dans la comparaison).
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod) :
 * un rejeu sans changement n'ecrit rien (ni base, ni .htaccess).
 *
 * ============================ RUNBOOK PREPROD / PROD ============================
 * Le fichier .htaccess n'est PAS versionne : les blocs du plugin doivent etre
 * ecrits SUR CHAQUE ENVIRONNEMENT en rejouant ce script (les blocs contiennent
 * le nom d'hote de l'environnement).
 * 1. Deployer le code (le plugin wp-fastest-cache 1.5.0 est versionne).
 * 2. Simulation :   wp eval-file wp-content/themes/ditl/cli/configurer-cache.php dry-run
 * 3. Application :  wp eval-file wp-content/themes/ditl/cli/configurer-cache.php
 *    (active le plugin si besoin, pose options + exclusions + crons, ecrit le .htaccess)
 * 4. Verifier le .htaccess : blocs "BEGIN WpFastestCache", "BEGIN GzipWpFastestCache",
 *    "BEGIN LBCWpFastestCache" presents AVANT le bloc "BEGIN WordPress".
 *    NB : le bloc WpFastestCache contient les logins des administrateurs en
 *    clair (condition d'exclusion par cookie, comportement natif du plugin,
 *    bloc regenere aux hooks user_register / profile_update). C'est attendu
 *    et accepte : le .htaccess n'est pas versionne et sa lecture HTTP est
 *    interdite (bloc AIOS). Verifier a chaque environnement que le fichier
 *    reste ignore par git (git check-ignore .htaccess).
 * 5. Aucune purge initiale necessaire (cache vide au depart) ; le preload
 *    demarre seul via le cron "wp_fastest_cache_Preload" (5 min). Verifier
 *    l'apparition de fichiers sous wp-content/cache/all/ apres quelques minutes.
 * 6. REJOUER CE SCRIPT apres toute (re)activation du plugin : le hook
 *    d'activation reecrit le bloc .htaccess sans la condition logged-in
 *    ($_POST vide a l'activation), le rejeu la retablit.
 * 7. Le jour du deploiement : verifier aussi le cache HTTP du panel Gandi
 *    (l'acces Gandi est actuellement bloque, point a lever avant la mise en prod).
 * 8. wp-content/cache/ est ignore par git : aucun fichier de cache ne doit
 *    entrer dans le depot (verifier avec : git check-ignore -v wp-content/cache/all).
 * ===============================================================================
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/configurer-cache.php dry-run
 *   wp eval-file wp-content/themes/ditl/cli/configurer-cache.php
 *   wp eval-file wp-content/themes/ditl/cli/configurer-cache.php annuler
 *
 * Le mode simulation accepte "dry-run" ou "--dry-run", l'annulation
 * "annuler" ou "--annuler" (combinables). Le mode annuler desactive le
 * plugin (son hook de desactivation retire les blocs .htaccess et purge le
 * cache), supprime les crons et les options posees, et efface le repertoire
 * wp-content/cache/.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Usage : wp eval-file <script> [annuler] [dry-run] - voir le docblock.
	// En acces web direct, reponse muette pour ne rien reveler du fichier.
	http_response_code( 404 );
	exit( 1 );
}

// ---------------------------------------------------------------------------
// Lecture des arguments : simulation et/ou annulation.
// ---------------------------------------------------------------------------

$ditl_dry_run = false;
$ditl_annuler = false;

foreach ( (array) $args as $ditl_arg ) {
	if ( 'dry-run' === $ditl_arg || '--dry-run' === $ditl_arg ) {
		$ditl_dry_run = true;
	} elseif ( 'annuler' === $ditl_arg || '--annuler' === $ditl_arg ) {
		$ditl_annuler = true;
	} else {
		WP_CLI::warning( sprintf( 'Argument ignore : %s', $ditl_arg ) );
	}
}

if ( $ditl_dry_run ) {
	WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucune ecriture (base, crons, .htaccess) ===' );
}

$ditl_plugin_cache = 'wp-fastest-cache/wpFastestCache.php';

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// Compteur d'ecritures effectives (idempotence : rejeu = zero ecriture).
$ditl_ecritures = 0;

// ---------------------------------------------------------------------------
// Outils locaux.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'ditl_cache_lister_crons_wpfc' ) ) {
	/**
	 * Liste les evenements cron du plugin (hooks wp_fastest_cache,
	 * wp_fastest_cache_<N> et wp_fastest_cache_Preload).
	 *
	 * @return array Tableau de lignes { hook, args, timestamp, schedule }.
	 */
	function ditl_cache_lister_crons_wpfc() {
		$lignes = array();

		foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
			foreach ( (array) $hooks as $hook => $evenements ) {
				if ( ! preg_match( '/^wp_fastest_cache(_\d+|_Preload)?$/', $hook ) ) {
					continue;
				}
				foreach ( (array) $evenements as $evenement ) {
					$lignes[] = array(
						'hook'      => $hook,
						'args'      => isset( $evenement['args'] ) ? (array) $evenement['args'] : array(),
						'timestamp' => (int) $timestamp,
						'schedule'  => isset( $evenement['schedule'] ) ? $evenement['schedule'] : '',
					);
				}
			}
		}

		return $lignes;
	}
}

if ( ! function_exists( 'ditl_cache_supprimer_repertoire' ) ) {
	/**
	 * Supprime recursivement un repertoire, uniquement s'il est situe sous
	 * wp-content/cache (garde-fou contre toute suppression hors perimetre).
	 *
	 * @param string $repertoire Chemin absolu du repertoire a supprimer.
	 * @return bool Vrai si le repertoire n'existe plus a la sortie.
	 */
	function ditl_cache_supprimer_repertoire( $repertoire ) {
		$racine = realpath( WP_CONTENT_DIR . '/cache' );
		$reel   = realpath( $repertoire );

		if ( false === $reel || false === $racine || 0 !== strpos( $reel, $racine ) ) {
			return ! is_dir( $repertoire );
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
// MODE ANNULER : retour a l'etat anterieur au chantier cache.
// ---------------------------------------------------------------------------

if ( $ditl_annuler ) {
	WP_CLI::log( '--- Annulation de la configuration du cache ---' );

	// 1. Desactivation du plugin : son hook de desactivation retire les blocs
	// WpFastestCache / Gzip / LBC / WEBP du .htaccess et purge le cache.
	if ( is_plugin_active( $ditl_plugin_cache ) ) {
		if ( $ditl_dry_run ) {
			WP_CLI::log( '  [dry-run] wp-fastest-cache : serait desactive (blocs .htaccess retires, cache purge).' );
		} else {
			deactivate_plugins( $ditl_plugin_cache );
			WP_CLI::log( '  wp-fastest-cache : desactive (blocs .htaccess retires, cache purge par le hook du plugin).' );
		}
	} else {
		WP_CLI::log( '  wp-fastest-cache : deja inactif, rien a faire.' );

		// Cas limite : des blocs du plugin peuvent subsister si une
		// desactivation anterieure n'a pas pu ecrire le .htaccess
		// (fichier non inscriptible a ce moment-la).
		$ditl_chemin_ht_annuler = ABSPATH . '.htaccess';

		if ( file_exists( $ditl_chemin_ht_annuler ) && false !== strpos( (string) file_get_contents( $ditl_chemin_ht_annuler ), '# BEGIN WpFastestCache' ) ) {
			WP_CLI::warning( 'Des blocs WpFastestCache subsistent dans le .htaccess alors que le plugin est inactif : les retirer manuellement.' );
		}
	}

	// 2. Crons du plugin (purge quotidienne, preload, eventuel hook historique).
	$ditl_crons = ditl_cache_lister_crons_wpfc();

	if ( array() === $ditl_crons ) {
		WP_CLI::log( '  Crons wp_fastest_cache* : aucun, rien a faire.' );
	} else {
		foreach ( $ditl_crons as $ditl_cron ) {
			if ( $ditl_dry_run ) {
				WP_CLI::log( sprintf( '  [dry-run] Cron %s : serait supprime.', $ditl_cron['hook'] ) );
			} else {
				wp_clear_scheduled_hook( $ditl_cron['hook'], $ditl_cron['args'] );
				WP_CLI::log( sprintf( '  Cron %s : supprime.', $ditl_cron['hook'] ) );
			}
		}
	}

	// 3. Options posees par ce script (etat anterieur : aucune des trois
	// n'existait, seule l'option residuelle wpfc-group - vide - est laissee
	// telle quelle, comme trouvee a la reprise du site).
	foreach ( array( 'WpFastestCache', 'WpFastestCacheExclude', 'WpFastestCachePreLoad' ) as $ditl_option ) {
		if ( false === get_option( $ditl_option ) ) {
			WP_CLI::log( sprintf( '  Option %s : absente, rien a faire.', $ditl_option ) );
		} elseif ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] Option %s : serait supprimee.', $ditl_option ) );
		} else {
			delete_option( $ditl_option );
			WP_CLI::log( sprintf( '  Option %s : supprimee.', $ditl_option ) );
		}
	}

	// 4. Residus disque : wp-content/cache (le hook de desactivation deplace
	// le cache dans cache/tmpWpfc avant suppression differee ; on efface tout).
	if ( is_dir( WP_CONTENT_DIR . '/cache' ) ) {
		if ( $ditl_dry_run ) {
			WP_CLI::log( '  [dry-run] wp-content/cache/ : serait supprime.' );
		} elseif ( ditl_cache_supprimer_repertoire( WP_CONTENT_DIR . '/cache' ) ) {
			WP_CLI::log( '  wp-content/cache/ : supprime.' );
		} else {
			WP_CLI::warning( 'wp-content/cache/ : suppression incomplete, verifier les permissions.' );
		}
	} else {
		WP_CLI::log( '  wp-content/cache/ : absent, rien a faire.' );
	}

	WP_CLI::log( '' );
	WP_CLI::log( $ditl_dry_run ? 'Simulation d\'annulation terminee.' : 'Annulation terminee.' );
	return;
}

// ---------------------------------------------------------------------------
// Cibles de configuration.
// ---------------------------------------------------------------------------

// Champs poses tels que l'ecran d'options du plugin les posterait : l'option
// WpFastestCache stocke le json_encode du formulaire (cases cochees = "on").
// Les cases NON cochees (mobile, minify, combine, lazy load, CDN...) sont
// simplement absentes du tableau - voir le perimetre dans le docblock.
$ditl_options_cache = array(
	'wpFastestCacheStatus'          => 'on',    // Cache System (mod_rewrite).
	'wpFastestCacheLanguage'        => 'eng',   // Langue de l'ecran d'options (valeur par defaut du plugin).
	'wpFastestCacheNewPost'         => 'on',    // Purge a la publication...
	'wpFastestCacheNewPost_type'    => 'all',   // ... purge complete.
	'wpFastestCacheUpdatePost'      => 'on',    // Purge a la mise a jour...
	'wpFastestCacheUpdatePost_type' => 'all',   // ... purge complete (listes a jour).
	'wpFastestCacheLoggedInUser'    => 'on',    // Pas de cache pour les connectes (condition mod_rewrite).
	'wpFastestCacheGzip'            => 'on',    // Bloc mod_deflate.
	'wpFastestCacheLBC'             => 'on',    // Browser Caching : bloc mod_expires sur les statiques.
	'wpFastestCachePreload'         => 'on',    // Preload...
	'wpFastestCachePreload_homepage' => 'on',   // ... accueil,
	'wpFastestCachePreload_post'    => 'on',    // ... articles,
	'wpFastestCachePreload_page'    => 'on',    // ... pages,
	'wpFastestCachePreload_number'  => '6',     // ... 6 URLs par passage (5 min).
);

// Regles d'exclusion (format de l'onglet Exclude du plugin) - justification
// detaillee dans le docblock.
$ditl_exclusions_cache = array(
	array( 'prefix' => 'exact',   'content' => 'fr/contact',  'type' => 'page' ),
	array( 'prefix' => 'exact',   'content' => 'contact-us',  'type' => 'page' ),
	array( 'prefix' => 'contain', 'content' => '.xml',        'type' => 'page' ),
	array( 'prefix' => 'regex',   'content' => '(^|\/)feed(\/|$)', 'type' => 'page' ),
	array( 'prefix' => 'exact',   'content' => 'robots.txt',  'type' => 'page' ),
);

// Purge quotidienne : format exact du Cache Timeout du plugin (une regle
// "All" a heure fixe). L'heure est en UTC (WordPress force le fuseau PHP).
$ditl_purge_heure   = '04';
$ditl_purge_minute  = '00';
$ditl_purge_hook    = 'wp_fastest_cache_0';
$ditl_purge_args    = array( json_encode( array(
	'prefix'  => 'all',
	'content' => 'all',
	'hour'    => $ditl_purge_heure,
	'minute'  => $ditl_purge_minute,
) ) );

// ---------------------------------------------------------------------------
// Etape 1 : activation du plugin.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Extension wp-fastest-cache ---' );

if ( is_plugin_active( $ditl_plugin_cache ) ) {
	WP_CLI::log( '  Deja active, rien a faire.' );
} elseif ( $ditl_dry_run ) {
	WP_CLI::log( '  [dry-run] Serait activee.' );
} else {
	$ditl_resultat_activation = activate_plugin( $ditl_plugin_cache );

	if ( is_wp_error( $ditl_resultat_activation ) ) {
		WP_CLI::error( sprintf( 'Activation impossible : %s', $ditl_resultat_activation->get_error_message() ) );
	}

	WP_CLI::log( '  Activee.' );
	$ditl_ecritures++;
}

// Chargement des classes du plugin si necessaire (cas du dry-run avec plugin
// inactif : le fichier principal n'a pas ete charge par WordPress).
if ( ! class_exists( 'WpFastestCache' ) ) {
	include_once WP_PLUGIN_DIR . '/wp-fastest-cache/wpFastestCache.php';
}
if ( ! class_exists( 'WpFastestCacheAdmin' ) ) {
	include_once WP_PLUGIN_DIR . '/wp-fastest-cache/inc/admin.php';
}

if ( ! class_exists( 'WpFastestCacheAdmin' ) ) {
	WP_CLI::error( 'Classes du plugin wp-fastest-cache introuvables : verifier la presence du plugin sur le disque.' );
}

// Le plugin verifie manage_options dans set_preload : execution au nom d'un
// administrateur (le premier par ID), comme une sauvegarde depuis l'admin.
$ditl_admins = get_users( array(
	'role'    => 'administrator',
	'number'  => 1,
	'orderby' => 'ID',
	'order'   => 'ASC',
	'fields'  => array( 'ID' ),
) );

if ( array() === $ditl_admins ) {
	WP_CLI::error( 'Aucun compte administrateur trouve : impossible de configurer le preload.' );
}

wp_set_current_user( (int) $ditl_admins[0]->ID );

// Etat initial des superglobales posees plus bas pour reproduire la
// sauvegarde admin du plugin : restaurees en fin de script.
$ditl_post_initial      = $_POST;
$ditl_http_host_initial = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : null;

// ---------------------------------------------------------------------------
// Etape 2 : regles d'exclusion (a poser AVANT l'ecriture du .htaccess,
// qui les integre au bloc mod_rewrite via excludeRules).
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Regles d\'exclusion (option WpFastestCacheExclude) ---' );

$ditl_exclusions_actuelles = json_decode( (string) get_option( 'WpFastestCacheExclude' ), true );

if ( $ditl_exclusions_actuelles == $ditl_exclusions_cache ) {
	WP_CLI::log( '  Deja conformes, rien a faire.' );
} else {
	foreach ( $ditl_exclusions_cache as $ditl_regle ) {
		WP_CLI::log( sprintf( '  %s%s : %s (%s)', $ditl_dry_run ? '[dry-run] ' : '', $ditl_regle['prefix'], $ditl_regle['content'], $ditl_regle['type'] ) );
	}

	if ( ! $ditl_dry_run ) {
		if ( false === get_option( 'WpFastestCacheExclude' ) ) {
			add_option( 'WpFastestCacheExclude', json_encode( $ditl_exclusions_cache ), null, 'yes' );
		} else {
			update_option( 'WpFastestCacheExclude', json_encode( $ditl_exclusions_cache ) );
		}
		WP_CLI::log( '  Regles posees.' );
		$ditl_ecritures++;
	}
}

// ---------------------------------------------------------------------------
// Etape 3 : options principales (option WpFastestCache).
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Options principales (option WpFastestCache) ---' );

$ditl_options_actuelles = json_decode( (string) get_option( 'WpFastestCache' ), true );

if ( $ditl_options_actuelles == $ditl_options_cache ) {
	WP_CLI::log( '  Deja conformes, rien a faire.' );
} else {
	foreach ( $ditl_options_cache as $ditl_cle => $ditl_valeur ) {
		WP_CLI::log( sprintf( '  %s%s = %s', $ditl_dry_run ? '[dry-run] ' : '', $ditl_cle, $ditl_valeur ) );
	}

	if ( ! $ditl_dry_run ) {
		if ( false === get_option( 'WpFastestCache' ) ) {
			add_option( 'WpFastestCache', json_encode( $ditl_options_cache ), null, 'yes' );
		} else {
			update_option( 'WpFastestCache', json_encode( $ditl_options_cache ) );
		}
		WP_CLI::log( '  Options posees.' );
		$ditl_ecritures++;
	}
}

// Le plugin lit ses options depuis cette globale, figee au chargement :
// rafraichissement pour que la suite du script (htaccess, preload) voie
// la configuration cible.
$GLOBALS['wp_fastest_cache_options'] = json_decode( json_encode( $ditl_options_cache ) );

// ---------------------------------------------------------------------------
// Etape 4 : purge automatique quotidienne (cron du Cache Timeout).
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Purge quotidienne (cron ' . $ditl_purge_hook . ', ' . $ditl_purge_heure . ':' . $ditl_purge_minute . ' UTC) ---' );

$ditl_purge_prochaine = wp_next_scheduled( $ditl_purge_hook, $ditl_purge_args );

if ( false !== $ditl_purge_prochaine ) {
	WP_CLI::log( sprintf( '  Deja programmee (prochaine execution : %s UTC), rien a faire.', gmdate( 'd/m/Y H:i:s', $ditl_purge_prochaine ) ) );
} else {
	// Nettoyage d'eventuels crons de purge divergents (autre heure, autre
	// contenu, ancien hook global) avant de programmer la regle cible.
	foreach ( ditl_cache_lister_crons_wpfc() as $ditl_cron ) {
		if ( 'wp_fastest_cache_Preload' === $ditl_cron['hook'] ) {
			continue;
		}
		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] Cron divergent %s : serait supprime.', $ditl_cron['hook'] ) );
		} else {
			wp_clear_scheduled_hook( $ditl_cron['hook'], $ditl_cron['args'] );
			WP_CLI::log( sprintf( '  Cron divergent %s : supprime.', $ditl_cron['hook'] ) );
		}
	}

	// Premier declenchement : aujourd'hui a l'heure cible si elle est a
	// venir, sinon demain (meme calcul que l'ecran Cache Timeout du plugin).
	$ditl_purge_premier = mktime( (int) $ditl_purge_heure, (int) $ditl_purge_minute, 0, (int) date( 'n' ), (int) date( 'j' ), (int) date( 'Y' ) );

	if ( $ditl_purge_premier <= time() ) {
		$ditl_purge_premier += DAY_IN_SECONDS;
	}

	if ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] Purge quotidienne : serait programmee (premiere execution : %s UTC).', gmdate( 'd/m/Y H:i:s', $ditl_purge_premier ) ) );
	} else {
		$ditl_purge_programmee = wp_schedule_event( $ditl_purge_premier, 'daily', $ditl_purge_hook, $ditl_purge_args );

		if ( true !== $ditl_purge_programmee ) {
			WP_CLI::warning( 'La purge quotidienne n\'a pas pu etre programmee (echec de wp_schedule_event) : la poser manuellement dans l\'ecran Cache Timeout du plugin.' );
		} else {
			WP_CLI::log( sprintf( '  Purge quotidienne programmee (premiere execution : %s UTC).', gmdate( 'd/m/Y H:i:s', $ditl_purge_premier ) ) );
			$ditl_ecritures++;
		}
	}
}

// ---------------------------------------------------------------------------
// Etape 5 : preload (option WpFastestCachePreLoad + cron toutes les 5 min).
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Preload (option WpFastestCachePreLoad) ---' );

$ditl_preload_actuel   = json_decode( (string) get_option( 'WpFastestCachePreLoad' ), true );
$ditl_preload_conforme = false;

if ( is_array( $ditl_preload_actuel ) ) {
	// Conformite : memes types de contenus configures et meme cadence. Les
	// valeurs des pointeurs (progression du preload) varient en permanence
	// et ne sont pas comparees.
	$ditl_cles_actuelles = array_keys( $ditl_preload_actuel );
	sort( $ditl_cles_actuelles );

	$ditl_preload_conforme = ( array( 'homepage', 'number', 'page', 'post' ) === $ditl_cles_actuelles )
		&& isset( $ditl_preload_actuel['number'] )
		&& (string) $ditl_preload_actuel['number'] === $ditl_options_cache['wpFastestCachePreload_number'];
}

if ( $ditl_preload_conforme ) {
	WP_CLI::log( '  Deja conforme (accueil + pages + articles, 6 URLs / 5 min), rien a faire.' );

	if ( false === wp_next_scheduled( 'wp_fastest_cache_Preload' ) ) {
		WP_CLI::log( '  Cron wp_fastest_cache_Preload non programme : normal apres un preload complet, la prochaine purge le relancera.' );
	}
} elseif ( $ditl_dry_run ) {
	WP_CLI::log( '  [dry-run] Preload : accueil + pages + articles, 6 URLs par passage de cron (5 min) serait configure.' );
} else {
	// Reproduction de la sauvegarde admin : PreloadWPFC::set_preload lit
	// $_POST (les champs wpFastestCachePreload_* des cibles ci-dessus) et
	// programme le cron. $_POST est restaure en fin de script.
	$_POST = array_merge( $_POST, $ditl_options_cache );

	include_once WP_PLUGIN_DIR . '/wp-fastest-cache/inc/preload.php';
	PreloadWPFC::set_preload( 'wp_fastest_cache' );

	if ( false === get_option( 'WpFastestCachePreLoad' ) ) {
		WP_CLI::warning( 'Le preload n\'a pas pu etre configure (option WpFastestCachePreLoad absente apres set_preload).' );
	} else {
		WP_CLI::log( '  Preload configure : accueil + pages + articles, 6 URLs par passage de cron (5 min).' );
		WP_CLI::log( sprintf( '  Cron wp_fastest_cache_Preload : prochaine execution %s UTC.', gmdate( 'd/m/Y H:i:s', (int) wp_next_scheduled( 'wp_fastest_cache_Preload' ) ) ) );
		$ditl_ecritures++;
	}
}

// ---------------------------------------------------------------------------
// Etape 6 : ecriture des blocs .htaccess (mod_rewrite + gzip + expires).
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Blocs .htaccess ---' );

// getHtaccess (via insertRewriteRule) et prefixRedirect lisent $_POST et
// $_SERVER["HTTP_HOST"] : pose a l'identique d'une sauvegarde admin.
$_POST = array_merge( $_POST, $ditl_options_cache );

if ( empty( $_SERVER['HTTP_HOST'] ) ) {
	$_SERVER['HTTP_HOST'] = (string) parse_url( home_url(), PHP_URL_HOST );
}

$ditl_admin_wpfc     = new WpFastestCacheAdmin();
$ditl_chemin_ht      = ABSPATH . '.htaccess';
$ditl_htaccess_avant = file_exists( $ditl_chemin_ht ) ? (string) file_get_contents( $ditl_chemin_ht ) : '';

// Simulation de ce que modifyHtaccess ecrirait (memes methodes du plugin),
// pour ne reecrire le fichier que si les blocs cibles different.
$ditl_htaccess_cible = $ditl_admin_wpfc->insertWebp( $ditl_htaccess_avant );
$ditl_htaccess_cible = $ditl_admin_wpfc->insertLBCRule( $ditl_htaccess_cible, $ditl_options_cache );
$ditl_htaccess_cible = $ditl_admin_wpfc->insertGzipRule( $ditl_htaccess_cible, $ditl_options_cache );
$ditl_htaccess_cible = $ditl_admin_wpfc->insertRewriteRule( $ditl_htaccess_cible, $ditl_options_cache );
$ditl_htaccess_cible = $ditl_admin_wpfc->to_move_gtranslate_rules( $ditl_htaccess_cible );

// La ligne "# Modified Time" change a chaque generation : ignoree.
$ditl_normaliser_ht = static function ( $contenu ) {
	return preg_replace( '/^# Modified Time:.*$/m', '', (string) $contenu );
};

if ( $ditl_normaliser_ht( $ditl_htaccess_avant ) === $ditl_normaliser_ht( $ditl_htaccess_cible ) ) {
	WP_CLI::log( '  Blocs deja en place et conformes, rien a faire.' );
} elseif ( $ditl_dry_run ) {
	WP_CLI::log( '  [dry-run] Les blocs suivants seraient ecrits en tete du .htaccess :' );
	foreach ( array( 'WpFastestCache', 'GzipWpFastestCache', 'LBCWpFastestCache' ) as $ditl_bloc ) {
		if ( preg_match( '/# BEGIN ' . $ditl_bloc . '\n.*?# END ' . $ditl_bloc . '/s', $ditl_htaccess_cible, $ditl_correspondance ) ) {
			WP_CLI::log( $ditl_correspondance[0] );
			WP_CLI::log( '' );
		}
	}
} else {
	// Ecriture reelle par le plugin lui-meme : modifyHtaccess rejoue tous
	// ses controles (permaliens, https, plugins incompatibles, droits en
	// ecriture) et n'ecrit qu'en leur absence d'erreur.
	$ditl_resultat_ht = $ditl_admin_wpfc->modifyHtaccess( $ditl_options_cache );

	if ( isset( $ditl_resultat_ht[1] ) && 'error' === $ditl_resultat_ht[1] ) {
		WP_CLI::error( sprintf( 'Ecriture du .htaccess refusee par le plugin : %s', trim( strip_tags( (string) $ditl_resultat_ht[0] ) ) ) );
	}

	$ditl_htaccess_apres = (string) file_get_contents( $ditl_chemin_ht );

	foreach ( array( 'WpFastestCache', 'GzipWpFastestCache', 'LBCWpFastestCache' ) as $ditl_bloc ) {
		if ( preg_match( '/# BEGIN ' . $ditl_bloc . '\b/', $ditl_htaccess_apres ) ) {
			WP_CLI::log( sprintf( '  Bloc %s : ecrit.', $ditl_bloc ) );
		} else {
			WP_CLI::warning( sprintf( 'Bloc %s absent du .htaccess apres ecriture : a verifier manuellement.', $ditl_bloc ) );
		}
	}

	$ditl_ecritures++;
}

// Repertoires de cache : le plugin les cree a la sauvegarde admin, meme
// controle ici (creation wp-content/cache/ et cache/all/ si absents).
if ( ! $ditl_dry_run ) {
	$ditl_controle_cache = $ditl_admin_wpfc->checkCachePathWriteable();

	if ( true !== $ditl_controle_cache ) {
		WP_CLI::warning( sprintf( 'Repertoire de cache non inscriptible : %s', trim( strip_tags( (string) $ditl_controle_cache[0] ) ) ) );
	} else {
		WP_CLI::log( '  Repertoires wp-content/cache/ et cache/all/ : prets.' );
	}
}

// ---------------------------------------------------------------------------
// Rappels (aucune modification ici).
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Rappels ---' );
WP_CLI::log( '  Rejouer ce script apres toute (re)activation du plugin : le hook d\'activation reecrit le bloc .htaccess sans la condition logged-in.' );
WP_CLI::log( '  Le .htaccess n\'est pas versionne : rejouer ce script sur chaque environnement (voir le runbook du docblock).' );
WP_CLI::log( '  Perimetre conservateur : minify/combine, lazy load, CDN et option Mobile restent volontairement desactives.' );
WP_CLI::log( '  Le preload tourne via WP-Cron : il n\'avance que si le site recoit des visites (ou via "wp cron event run wp_fastest_cache_Preload").' );

// Restauration des superglobales posees pour reproduire la sauvegarde admin.
$_POST = $ditl_post_initial;

if ( null === $ditl_http_host_initial ) {
	unset( $_SERVER['HTTP_HOST'] );
} else {
	$_SERVER['HTTP_HOST'] = $ditl_http_host_initial;
}

WP_CLI::log( '' );

if ( $ditl_dry_run ) {
	WP_CLI::log( 'Simulation terminee.' );
} else {
	WP_CLI::log( sprintf( 'Configuration terminee (%d ecriture(s) lors de ce passage).', $ditl_ecritures ) );
}
