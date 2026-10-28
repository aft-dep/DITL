<?php
/**
 * Configuration des extensions conservees (phase 1 de la refonte, lot
 * configuration du 08/09/2026).
 *
 * Pose en base, exactement comme le ferait l'ecran d'administration de
 * chaque extension (memes classes et fonctions de sauvegarde, donc memes
 * effets de bord), les reglages arbitres dans le rapport
 * rationalisation-extensions.md (section 6.3 / 6.4) :
 *
 * - Complianz 7.5.4 : mesure d'audience declaree GA4 (compile_statistics
 *   google-analytics, au lieu de Matomo qui n'est plus utilise), Google
 *   Consent Mode v2 active (consent-mode yes), plus aucun service tiers
 *   declare (thirdparty_services_on_site vide : les polices sont locales
 *   depuis le 20/08), clarity_id vide, respect du signal Do Not Track
 *   (respect_dnt yes). Ecriture via cmplz_update_option, qui rejoue les hooks
 *   de la sauvegarde admin : creation du service "Google Analytics" dans la
 *   table des services, regeneration des CSS de banniere et incrementation
 *   de banner_version (cmplz_update_all_banners), incrementation de la
 *   politique active (complianz_active_policy_id : les visiteurs seront
 *   re-sollicites, comportement voulu par Complianz quand les services
 *   declares changent), dates de publication des documents, purge du
 *   transient cmplz_blocked_scripts. L'integration Site Kit de Complianz
 *   (integrations/plugins/google-site-kit.php) prend alors le relais : elle
 *   injecte gtag('consent', 'default', analytics_storage denied) AVANT
 *   gtag.js et met a jour le consentement aux evenements de la banniere ;
 *   gtag.js n'est pas bloque (whitelist du tag google_gtagjs-js), c'est le
 *   consent mode qui empeche tout cookie et toute mesure avant acceptation.
 * - Google Site Kit 1.187 : consent mode active (googlesitekit_consent_mode,
 *   l'autre cote du meme mecanisme : gtag('consent', 'default') en tete du
 *   <head> plus le script googlesitekit-consent-mode.js qui relaie le WP
 *   Consent API), suivi des conversions coupe (googlesitekit_conversion_tracking
 *   enabled false : retire le script content-events et le fournisseur WPForms
 *   de toutes les pages), module pagespeed-insights desactive (tableau de
 *   bord admin uniquement), rapports e-mail desactives
 *   (googlesitekit_email_reporting enabled false : le plugin deprogramme lui
 *   meme ses 5 crons googlesitekit_email_reporting_*).
 *   La rustine ditl_retirer_sitekit_wpforms_sans_formulaire (inc/perf.php)
 *   devient sans objet une fois conversion_tracking coupe : elle est laissee
 *   en place (inoffensive, protege un retour en arriere).
 * - WPForms 2.0 : ameliorations RGPD (gdpr : plus de stockage d'IP ni de
 *   user agent), annonces et menu de barre d'admin masques, resumes
 *   hebdomadaires par e-mail desactives (email-summaries-disable) ; le
 *   balisage moderne (modern-markup) N'EST PAS touche (a tester au lot a11y
 *   B4). Ecriture via wpforms_update_settings (hook wpforms_settings_updated
 *   rejoue). Purge des transients orphelins _wpforms_transient_wpforms_*
 *   htaccess_file (cles d'anciennes versions, contenant les chemins absolus
 *   d'anciens environnements) via WPForms\Helpers\Transient::delete.
 *   Form Analytics est coupe par filtre dans inc/extensions.php (pas
 *   d'option).
 * - WP Mail SMTP 4.9 : rapport hebdomadaire par e-mail desactive
 *   (general.summary_report_email_disabled) via WPMailSMTP\Options::set, et
 *   annulation de la tache Action Scheduler comme le fait l'onglet Misc.
 * - Mises a jour automatiques (option auto_update_plugins) : etendues aux
 *   extensions de cache et de confort sans impact front (wp-fastest-cache,
 *   wps-hide-login, duplicate-page) ; google-site-kit y est deja. Wordfence,
 *   seule suite de securite depuis la suppression d'AIOS (22/10/2026), reste
 *   en manuel. Les autres (wpforms-lite, complianz-gdpr, wordpress-seo,
 *   polylang, interactive-geo-maps, wp-mail-smtp) restent en manuel : impact
 *   front, a tester en preprod.
 * - Freemius (interactive-geo-maps ; Ivory Search supprime le 22/10/2026) :
 *   opt-out local de la telemetrie via
 *   FS_Permission_Manager::update_site_tracking(false), la
 *   methode publique du SDK que l'opt-out de l'ecran Compte finit par
 *   appeler ; le cron quotidien fs_data_sync_<slug> est supprime et le SDK ne
 *   le reprogramme pas tant que is_tracking_allowed() est faux. LIMITE : la
 *   partie API de l'opt-out admin (marquage is_disconnected cote Freemius)
 *   passe par des methodes privees et n'est pas rejouee ; pour la completer,
 *   voir le runbook.
 * - .htaccess : bloc "# BEGIN DITL Acces" (distinct du bloc DITL WebP, que
 *   cli/optimiser-images.php reecrit integralement a chaque rejeu : un ajout
 *   dans ce bloc serait perdu) interdisant la lecture HTTP de .user.ini (le
 *   fichier revele le chemin absolu du serveur, constat de l'audit du
 *   08/09). Insere avant le bloc WordPress, mis a jour par str_replace du
 *   bloc capture (jamais preg_replace avec du contenu en remplacement).
 *   Etendu le 27/10/2026 aux protections que posait AIOS avant sa
 *   suppression (audit securite du 27/10/2026) : pas de liste des
 *   dossiers, wp-config.php / debug.log / .ht* interdits ainsi que leurs
 *   copies et les fichiers de sauvegarde (.bak, .sql, .log...), motifs
 *   insensibles a la casse, taille de requete plafonnee a 100 Mo,
 *   affichage en iframe limite au site lui-meme (X-Frame-Options et CSP
 *   frame-ancestors, memes valeurs que la prod).
 *
 * Yoast : le crawl cleanup est porte par cli/configurer-yoast.php (etendu le
 * 08/09), pas par ce script.
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod) : un rejeu
 * sans changement n'ecrit rien (bilan des ecritures en fin de passage).
 *
 * ============================ RUNBOOK PREPROD / PROD ============================
 * 1. Deployer le code (theme + extensions versionnees).
 * 2. Simulation :   wp eval-file wp-content/themes/ditl/cli/configurer-extensions.php dry-run
 * 3. Application :  wp eval-file wp-content/themes/ditl/cli/configurer-extensions.php
 * 4. Purger le cache de page (wp-content/cache/all) puis verifier sur la
 *    page d'accueil (?nocache=1) : gtag('consent', 'default', ...) present
 *    AVANT la balise gtag.js, plus aucun script content-events ni
 *    oncontextmenu, banniere Complianz affichee (politique incrementee).
 * 5. Verifier : curl -I https://<hote>/.user.ini -> 403 ; /wp-config.php,
 *    /wp-content/debug.log et un dossier d'uploads sans index -> 403 ; la
 *    home -> 200 avec X-Frame-Options SAMEORIGIN et CSP frame-ancestors
 *    'self' (verifier avec un parametre qui contourne le cache, ex.
 *    ?nocache=1 : les pages deja en cache Varnish gardent les anciens
 *    en-tetes jusqu'a expiration ou purge) ; une image avec jumeau .webp
 *    servie en image/webp (bloc WebP intact).
 * 6. Complianz : relancer le scan de cookies depuis le tableau de bord
 *    (Complianz > Tableau de bord) pour rafraichir la politique de cookies
 *    generee (Matomo n'y figure plus, Google Analytics y figure).
 * 7. Freemius (optionnel, complete l'opt-out cote API) : Maps > Compte >
 *    "Opt out".
 *    WPForms : l'option "Disable storing user details" par formulaire est
 *    sans objet ici, le reglage global gdpr (pose par ce script) suffit en
 *    version Lite (aucune IP ni user agent stockes, pas de cookie de
 *    session).
 * 8. Rejouer ce script apres toute (re)activation de Complianz, Site Kit,
 *    WPForms ou WP Mail SMTP (leurs hooks d'activation peuvent
 *    reposer des valeurs par defaut) et apres cli/configurer-yoast.php si
 *    Yoast est reactive.
 * 9. Le .htaccess n'est pas versionne : le bloc DITL Acces est a reposer par
 *    ce script sur chaque environnement.
 * 10. PREPROD (base de reference depuis le 22/10/2026, l'AFT peut y avoir
 *    change des reglages d'extensions) : ne poser QUE le bloc .htaccess avec
 *    le mode "htaccess", apres une copie du fichier hors htdocs. Depuis le
 *    dossier du vhost, avec le wp-cli.phar qui s'y trouve (la commande wp
 *    du systeme echoue en console : "failed to set securebits") ; eval-file
 *    cherche le script par rapport au dossier courant :
 *      cd /srv/data/web/vhosts/preprod.ditlproject.eu
 *      wp() { php -d error_reporting=24575 wp-cli.phar --path=htdocs "$@"; }
 *      cp htdocs/.htaccess htaccess-avant-acces.bak
 *      wp eval-file htdocs/wp-content/themes/ditl/cli/configurer-extensions.php htaccess dry-run
 *      wp eval-file htdocs/wp-content/themes/ditl/cli/configurer-extensions.php htaccess
 *    Verifier dans la minute : /wp-config.php et /wp-content/debug.log ->
 *    403, un dossier d'uploads sans index -> 403, la home -> 200 avec
 *    X-Frame-Options SAMEORIGIN et CSP frame-ancestors 'self', une page
 *    e-learning (module affiche). Retour arriere : recopier la sauvegarde.
 * ===============================================================================
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/configurer-extensions.php dry-run
 *   wp eval-file wp-content/themes/ditl/cli/configurer-extensions.php
 *   wp eval-file wp-content/themes/ditl/cli/configurer-extensions.php htaccess [dry-run]
 *
 * Le mode "htaccess" ne traite que le bloc DITL Acces (etape 8) et ne touche
 * a aucun reglage d'extension. Tout argument inconnu est refuse : une faute
 * de frappe sur "htaccess" ne doit pas lancer le mode complet.
 *
 * Le mode simulation accepte "dry-run" ou "--dry-run". Pas de mode annuler :
 * l'argument "annuler" est refuse avec une erreur explicite (les valeurs
 * anterieures sont consignees dans decisions.md du 08/09/2026).
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

// ---------------------------------------------------------------------------
// Lecture des arguments : mode simulation eventuel.
// ---------------------------------------------------------------------------

// Arguments inconnus refuses (et non ignores comme dans ditl_cli_lire_modes) :
// une faute de frappe sur "htaccess" lancerait sinon le mode complet, qui
// reecrit les reglages d'extensions.
foreach ( (array) $args as $ditl_arg ) {
	if ( ! in_array( $ditl_arg, array( 'htaccess', 'dry-run', '--dry-run', 'annuler', '--annuler' ), true ) ) {
		WP_CLI::error( sprintf( 'Argument inconnu : %s (attendus : htaccess, dry-run).', $ditl_arg ) );
	}
}

// Mode "htaccess" : bloc DITL Acces seul, sans aucun reglage d'extension.
$ditl_htaccess_seul = in_array( 'htaccess', (array) $args, true );
$ditl_modes         = ditl_cli_lire_modes( array_diff( (array) $args, array( 'htaccess' ) ) );
$ditl_dry_run       = $ditl_modes['dry_run'];

if ( $ditl_modes['annuler'] ) {
	WP_CLI::error( 'Ce script n\'a pas de mode annuler : argument refuse (valeurs anterieures dans decisions.md du 08/09/2026).' );
}

if ( $ditl_dry_run ) {
	WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucune ecriture (base, crons, .htaccess) ===' );
}

if ( $ditl_htaccess_seul ) {
	WP_CLI::log( '=== MODE htaccess : bloc DITL Acces seul, aucun reglage d\'extension ===' );
}

// Compteur d'ecritures effectives (idempotence : rejeu = zero ecriture).
$ditl_ecritures = 0;

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// Execution au nom du premier administrateur, comme une sauvegarde depuis
// l'admin : sous WP-CLI les gardes principales des extensions passent deja
// (cmplz_admin_logged_in() vaut true en CLI, deactivate_module ne verifie
// aucune capacite), mais certains hooks secondaires testent une capacite
// (cmplz_user_can_manage) et seraient sinon sautes silencieusement.
$ditl_admins = get_users( array(
	'role'    => 'administrator',
	'number'  => 1,
	'orderby' => 'ID',
	'order'   => 'ASC',
	'fields'  => array( 'ID' ),
) );

if ( array() === $ditl_admins ) {
	WP_CLI::error( 'Aucun compte administrateur trouve : impossible de rejouer les sauvegardes admin.' );
}

wp_set_current_user( (int) $ditl_admins[0]->ID );

// ---------------------------------------------------------------------------
// Outils locaux.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'ditl_ext_libelle' ) ) {
	/**
	 * Represente une valeur d'option pour les journaux (compacte, sur une ligne).
	 *
	 * @param mixed $valeur Valeur a afficher.
	 * @return string Representation lisible.
	 */
	function ditl_ext_libelle( $valeur ) {
		if ( is_array( $valeur ) ) {
			return (string) wp_json_encode( $valeur );
		}

		return var_export( $valeur, true );
	}
}

if ( ! function_exists( 'ditl_ext_bloc_htaccess_acces' ) ) {
	/**
	 * Contenu du bloc .htaccess de protection des acces : fichiers de
	 * configuration et de documentation interdits en HTTP, plus les
	 * protections reprises d'AIOS le 27/10/2026 (directives acceptees par
	 * Gandi : le bloc AIOS les a posees sur la preprod du 21 au 22/10 sans
	 * aucune erreur serveur).
	 *
	 * Les deux formes de directive (Apache 2.4 mod_authz_core et 2.2) sont
	 * posees, comme dans les autres blocs de securite du meme fichier.
	 * Header set : couvre aussi les pages servies en statique par WP Fastest
	 * Cache, que PHP ne voit pas. Les en-tetes envoyes par PHP (admin,
	 * connexion) ne sont pas remplaces : doublon de meme valeur, sans effet
	 * sur les navigateurs. Header always set deplacerait seulement le doublon
	 * sur les modules e-learning (Header set dans leur propre bloc).
	 *
	 * @return string Bloc complet, marqueurs inclus, termine par un saut de ligne.
	 */
	function ditl_ext_bloc_htaccess_acces() {
		$lignes = array(
			'# BEGIN DITL Acces',
			'# Interdiction de lecture HTTP du fichier .user.ini (configuration PHP',
			'# d\'un pare-feu applicatif : il revele le chemin absolu). Bloc gere',
			'# par le script WP-CLI wp-content/themes/ditl/cli/configurer-extensions.php',
			'# - ne pas editer a la main.',
			'<Files ".user.ini">',
			'<IfModule mod_authz_core.c>',
			'Require all denied',
			'</IfModule>',
			'<IfModule !mod_authz_core.c>',
			'Order deny,allow',
			'Deny from all',
			'</IfModule>',
			'</Files>',
			'# Fichiers de documentation des extensions (divulgation de versions).',
			'<FilesMatch "(?i)^(readme|license|changelog)\.(txt|html|md)$">',
			'<IfModule mod_authz_core.c>',
			'Require all denied',
			'</IfModule>',
			'<IfModule !mod_authz_core.c>',
			'Order deny,allow',
			'Deny from all',
			'</IfModule>',
			'</FilesMatch>',
			'# Pas de liste des fichiers des dossiers sans index (repris d\'AIOS).',
			'Options -Indexes',
			'# Configuration WordPress, journal de debogage et fichiers .ht*, avec',
			'# leurs copies (wp-config.php.bak, .wp-config.php.swp, debug.log.1...),',
			'# sans tenir compte de la casse (repris d\'AIOS et etendu ; .ht* deja',
			'# refuse par Apache, redondance voulue).',
			'<FilesMatch "(?i)^(\.?wp-config|debug\.log|\.ht)">',
			'<IfModule mod_authz_core.c>',
			'Require all denied',
			'</IfModule>',
			'<IfModule !mod_authz_core.c>',
			'Order deny,allow',
			'Deny from all',
			'</IfModule>',
			'</FilesMatch>',
			'# Copies de sauvegarde, d\'editeur et exports de base, ou qu\'ils soient.',
			'<FilesMatch "(?i)(\.(bak|old|orig|save|swp|swo|sql|log)|~)$">',
			'<IfModule mod_authz_core.c>',
			'Require all denied',
			'</IfModule>',
			'<IfModule !mod_authz_core.c>',
			'Order deny,allow',
			'Deny from all',
			'</IfModule>',
			'</FilesMatch>',
			'# Taille maximale d\'une requete : 100 Mo (valeur AIOS ; illimitee',
			'# par defaut sur l\'Apache de Gandi).',
			'LimitRequestBody 104857600',
			'# Pages affichables en iframe par le site lui-meme uniquement',
			'# (memes valeurs que la prod sous AIOS).',
			'<IfModule mod_headers.c>',
			'Header set X-Frame-Options "SAMEORIGIN"',
			'Header set Content-Security-Policy "frame-ancestors \'self\'"',
			'</IfModule>',
			'# END DITL Acces',
		);

		return implode( "\n", $lignes ) . "\n";
	}
}

if ( ! function_exists( 'ditl_ext_poser_htaccess_acces' ) ) {
	/**
	 * Pose ou met a jour le bloc DITL Acces dans le .htaccess.
	 *
	 * Insere AVANT le bloc WordPress (donc apres les blocs WP Fastest Cache
	 * et DITL WebP existants). Les autres blocs ne sont jamais touches. Mise a jour par
	 * str_replace du bloc capture : jamais de preg_replace avec du contenu en
	 * chaine de remplacement (lecon du 20/08, references arrieres).
	 *
	 * @param string $fichier Chemin du .htaccess.
	 * @param bool   $dry_run True pour simuler sans ecrire.
	 * @return int Nombre d'ecritures effectuees (0 ou 1).
	 */
	function ditl_ext_poser_htaccess_acces( $fichier, $dry_run ) {
		WP_CLI::log( '--- Bloc .htaccess (# BEGIN DITL Acces : fichiers sensibles, liste des dossiers, iframes) ---' );

		if ( ! file_exists( $fichier ) ) {
			WP_CLI::warning( sprintf( '.htaccess introuvable (%s) : bloc non pose, a rejouer sur un environnement ou il existe.', $fichier ) );
			return 0;
		}

		$contenu = file_get_contents( $fichier );

		if ( false === $contenu ) {
			WP_CLI::warning( sprintf( '.htaccess illisible (%s) : bloc non pose.', $fichier ) );
			return 0;
		}

		$bloc = ditl_ext_bloc_htaccess_acces();

		if ( substr_count( $contenu, '# BEGIN DITL Acces' ) !== substr_count( $contenu, '# END DITL Acces' ) ) {
			WP_CLI::warning( 'Marqueurs "DITL Acces" desequilibres dans le .htaccess (edition manuelle ?) : bloc non touche, a reparer a la main.' );
			return 0;
		}

		preg_match( '/# BEGIN DITL Acces.*?# END DITL Acces\n?/s', $contenu, $existant );
		$present = ! empty( $existant );

		if ( $present && trim( $existant[0] ) === trim( $bloc ) ) {
			WP_CLI::log( '  Bloc deja en place et a jour, rien a faire.' );
			return 0;
		}

		if ( $dry_run ) {
			WP_CLI::log( $present ? '  [dry-run] le bloc serait mis a jour.' : '  [dry-run] le bloc serait insere avant le bloc WordPress.' );
			return 0;
		}

		if ( $present ) {
			$nouveau = str_replace( rtrim( $existant[0], "\n" ), rtrim( $bloc, "\n" ), $contenu );
		} else {
			$position = strpos( $contenu, '# BEGIN WordPress' );

			if ( false !== $position ) {
				$nouveau = substr( $contenu, 0, $position ) . $bloc . "\n" . substr( $contenu, $position );
			} else {
				WP_CLI::warning( 'Bloc "# BEGIN WordPress" introuvable : bloc DITL Acces ajoute en fin de .htaccess.' );
				$nouveau = rtrim( $contenu, "\n" ) . "\n\n" . $bloc;
			}
		}

		if ( ! is_writable( $fichier ) || false === @file_put_contents( $fichier, $nouveau ) ) {
			WP_CLI::warning( 'Ecriture du .htaccess impossible (fichier non inscriptible) : bloc non pose.' );
			return 0;
		}

		WP_CLI::log( $present ? '  Bloc mis a jour dans le .htaccess.' : '  Bloc insere avant le bloc WordPress.' );
		return 1;
	}
}

// ---------------------------------------------------------------------------
// Mode "htaccess" : etape 8 seule, puis fin (runbook, point 10).
// ---------------------------------------------------------------------------

if ( $ditl_htaccess_seul ) {
	$ditl_ecritures = ditl_ext_poser_htaccess_acces( rtrim( ABSPATH, '/' ) . '/.htaccess', $ditl_dry_run );

	WP_CLI::log( '' );
	WP_CLI::log( $ditl_dry_run ? 'Simulation terminee (bloc .htaccess seul).' : sprintf( 'Bloc .htaccess traite (%d ecriture(s)), aucun reglage d\'extension touche.', $ditl_ecritures ) );
	return;
}

// ---------------------------------------------------------------------------
// Etape 1 : Complianz.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Complianz (option cmplz_options) ---' );

if ( ! class_exists( 'COMPLIANZ' ) || ! function_exists( 'cmplz_update_option' ) || ! function_exists( 'cmplz_get_option' ) ) {
	WP_CLI::warning( 'Complianz inactif : reglages non poses (rejouer ce script apres activation).' );
} else {
	// Cle => valeur cible, au format de l'ecran (radio => chaine, multicheckbox
	// => tableau, text => chaine).
	$ditl_cmplz_cibles = array(
		'compile_statistics'          => 'google-analytics',
		'consent-mode'                => 'yes',
		'thirdparty_services_on_site' => array(),
		'clarity_id'                  => '',
		'respect_dnt'                 => 'yes',
	);

	$ditl_cmplz_ids     = array_column( COMPLIANZ::$config->fields, 'id' );
	$ditl_cmplz_ecrites = 0;

	foreach ( $ditl_cmplz_cibles as $ditl_cle => $ditl_cible ) {
		if ( ! in_array( $ditl_cle, $ditl_cmplz_ids, true ) ) {
			WP_CLI::warning( sprintf( 'Champ Complianz "%s" inconnu de cette version : ignore (verifier a la montee de version).', $ditl_cle ) );
			continue;
		}

		$ditl_actuelle = cmplz_get_option( $ditl_cle );

		// Une multicheckbox vide est stockee tantot "" (defaut), tantot [] :
		// normalisation pour la comparaison.
		if ( is_array( $ditl_cible ) ) {
			$ditl_actuelle = is_array( $ditl_actuelle ) ? array_values( $ditl_actuelle ) : ( empty( $ditl_actuelle ) ? array() : array( $ditl_actuelle ) );
		}

		if ( $ditl_actuelle === $ditl_cible ) {
			WP_CLI::log( sprintf( '  %s : deja a %s, rien a faire.', $ditl_cle, ditl_ext_libelle( $ditl_cible ) ) );
			continue;
		}

		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] %s : %s serait remplacee par %s.', $ditl_cle, ditl_ext_libelle( $ditl_actuelle ), ditl_ext_libelle( $ditl_cible ) ) );
			continue;
		}

		// Sauvegarde admin a l'identique : sanitize du champ, filtre
		// cmplz_before_save_options (bannieres, politique, service GA) et
		// action cmplz_after_save_field.
		cmplz_update_option( $ditl_cle, $ditl_cible );

		$ditl_relue = cmplz_get_option( $ditl_cle );

		if ( is_array( $ditl_cible ) ) {
			$ditl_relue = is_array( $ditl_relue ) ? array_values( $ditl_relue ) : ( empty( $ditl_relue ) ? array() : array( $ditl_relue ) );
		}

		if ( $ditl_relue === $ditl_cible ) {
			WP_CLI::log( sprintf( '  %s : %s -> %s.', $ditl_cle, ditl_ext_libelle( $ditl_actuelle ), ditl_ext_libelle( $ditl_cible ) ) );
			$ditl_ecritures++;
			$ditl_cmplz_ecrites++;
		} else {
			WP_CLI::warning( sprintf( 'Champ Complianz "%s" : valeur relue %s au lieu de %s (ecriture refusee par le plugin ?).', $ditl_cle, ditl_ext_libelle( $ditl_relue ), ditl_ext_libelle( $ditl_cible ) ) );
		}
	}

	if ( $ditl_cmplz_ecrites > 0 && function_exists( 'cmplz_delete_transient' ) ) {
		// Fin de la sauvegarde REST (settings.php, cmplz_rest_api_fields_set) :
		// la liste des scripts bloques est recalculee au prochain rendu.
		cmplz_delete_transient( 'cmplz_blocked_scripts' );
		WP_CLI::log( '  Transient cmplz_blocked_scripts purge ; bannieres regenerees et politique incrementee par les hooks du plugin.' );
	}

	if ( defined( 'WP_CONSENT_API_VERSION' ) ) {
		WP_CLI::log( '  WP Consent API present : l\'integration Site Kit de Complianz s\'efface derriere lui (comportement du plugin).' );
	}
}

// ---------------------------------------------------------------------------
// Etape 2 : Google Site Kit.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Google Site Kit ---' );

if ( ! class_exists( '\Google\Site_Kit\Plugin' ) ) {
	WP_CLI::warning( 'Site Kit inactif : reglages non poses (rejouer ce script apres activation).' );
} else {
	$ditl_sk_context = \Google\Site_Kit\Plugin::instance()->context();
	$ditl_sk_options = new \Google\Site_Kit\Core\Storage\Options( $ditl_sk_context );

	// Reglages "objet" du plugin : classe de reglage => valeurs cibles. Le
	// tableau complet est reecrit (valeur actuelle + cibles) : Setting::set
	// remplace l'option, sa sanitize_callback fusionne de son cote.
	$ditl_sk_reglages = array(
		'googlesitekit_conversion_tracking' => array(
			'classe' => '\Google\Site_Kit\Core\Conversion_Tracking\Conversion_Tracking_Settings',
			'cibles' => array( 'enabled' => false ),
			'defaut' => array( 'enabled' => false ),
		),
		'googlesitekit_consent_mode'        => array(
			'classe' => '\Google\Site_Kit\Core\Consent_Mode\Consent_Mode_Settings',
			'cibles' => array( 'enabled' => true ),
			'defaut' => array( 'enabled' => false ),
		),
		'googlesitekit_email_reporting'     => array(
			'classe' => '\Google\Site_Kit\Core\Email_Reporting\Email_Reporting_Settings',
			'cibles' => array( 'enabled' => false ),
			'defaut' => array( 'enabled' => true ),
		),
	);

	foreach ( $ditl_sk_reglages as $ditl_option => $ditl_reglage ) {
		if ( ! class_exists( $ditl_reglage['classe'] ) ) {
			WP_CLI::warning( sprintf( 'Classe %s absente de cette version de Site Kit : option %s non posee.', $ditl_reglage['classe'], $ditl_option ) );
			continue;
		}

		$ditl_setting  = new $ditl_reglage['classe']( $ditl_sk_options );
		$ditl_actuelle = $ditl_setting->get();

		if ( ! is_array( $ditl_actuelle ) ) {
			$ditl_actuelle = $ditl_reglage['defaut'];
		}

		$ditl_conforme = true;

		foreach ( $ditl_reglage['cibles'] as $ditl_cle => $ditl_valeur ) {
			if ( ! array_key_exists( $ditl_cle, $ditl_actuelle ) || $ditl_actuelle[ $ditl_cle ] !== $ditl_valeur ) {
				$ditl_conforme = false;
			}
		}

		if ( $ditl_conforme ) {
			WP_CLI::log( sprintf( '  %s : deja a %s, rien a faire.', $ditl_option, ditl_ext_libelle( $ditl_reglage['cibles'] ) ) );
			continue;
		}

		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] %s : %s serait remplacee par %s.', $ditl_option, ditl_ext_libelle( array_intersect_key( $ditl_actuelle, $ditl_reglage['cibles'] ) ), ditl_ext_libelle( $ditl_reglage['cibles'] ) ) );
			continue;
		}

		$ditl_setting->set( array_merge( $ditl_actuelle, $ditl_reglage['cibles'] ) );
		$ditl_relue = $ditl_setting->get();

		if ( is_array( $ditl_relue ) && array_intersect_key( $ditl_relue, $ditl_reglage['cibles'] ) === $ditl_reglage['cibles'] ) {
			WP_CLI::log( sprintf( '  %s : %s -> %s.', $ditl_option, ditl_ext_libelle( array_intersect_key( $ditl_actuelle, $ditl_reglage['cibles'] ) ), ditl_ext_libelle( $ditl_reglage['cibles'] ) ) );
			$ditl_ecritures++;
		} else {
			WP_CLI::warning( sprintf( 'Option %s : valeur relue %s, ecriture non prise en compte.', $ditl_option, ditl_ext_libelle( $ditl_relue ) ) );
		}
	}

	// Module PageSpeed Insights : desactivation par la classe des modules
	// (retire le slug de googlesitekit_active_modules, supprime les reglages
	// du module, le retire du partage de tableau de bord).
	$ditl_sk_actifs = get_option( 'googlesitekit_active_modules', array() );

	if ( ! is_array( $ditl_sk_actifs ) || ! in_array( 'pagespeed-insights', $ditl_sk_actifs, true ) ) {
		WP_CLI::log( '  Module pagespeed-insights : deja inactif, rien a faire.' );
	} elseif ( $ditl_dry_run ) {
		WP_CLI::log( '  [dry-run] Module pagespeed-insights : serait desactive.' );
	} elseif ( ! class_exists( '\Google\Site_Kit\Core\Modules\Modules' ) ) {
		WP_CLI::warning( 'Classe Modules absente : module pagespeed-insights non desactive.' );
	} else {
		$ditl_sk_modules = new \Google\Site_Kit\Core\Modules\Modules( $ditl_sk_context, $ditl_sk_options );

		if ( $ditl_sk_modules->deactivate_module( 'pagespeed-insights' ) ) {
			WP_CLI::log( '  Module pagespeed-insights : desactive.' );
			$ditl_ecritures++;
		} else {
			WP_CLI::warning( 'Module pagespeed-insights : desactivation refusee par Site Kit.' );
		}
	}

	// Crons des rapports e-mail : deprogrammes par le plugin au changement
	// d'option (Email_Reporting::register, on_change) ; controle et repli.
	$ditl_sk_hooks_mail = array(
		'googlesitekit_email_reporting_initiator',
		'googlesitekit_email_reporting_worker',
		'googlesitekit_email_reporting_fallback',
		'googlesitekit_email_reporting_monitor',
		'googlesitekit_email_reporting_cleanup',
	);
	$ditl_sk_restants   = array();

	foreach ( $ditl_sk_hooks_mail as $ditl_hook ) {
		foreach ( (array) _get_cron_array() as $ditl_evenements ) {
			if ( isset( $ditl_evenements[ $ditl_hook ] ) ) {
				$ditl_sk_restants[ $ditl_hook ] = true;
			}
		}
	}

	if ( array() === $ditl_sk_restants ) {
		WP_CLI::log( '  Crons googlesitekit_email_reporting_* : aucun.' );
	} elseif ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] Crons %s : seraient deprogrammes par le plugin a la desactivation des rapports.', implode( ', ', array_keys( $ditl_sk_restants ) ) ) );
	} else {
		foreach ( array_keys( $ditl_sk_restants ) as $ditl_hook ) {
			wp_unschedule_hook( $ditl_hook );
			WP_CLI::log( sprintf( '  Cron %s : deprogramme (repli, le hook du plugin ne l\'avait pas fait).', $ditl_hook ) );
			$ditl_ecritures++;
		}
	}

	WP_CLI::log( '  Rappel : la rustine ditl_retirer_sitekit_wpforms_sans_formulaire (inc/perf.php) est sans objet avec conversion_tracking coupe, laissee en place.' );
}

// ---------------------------------------------------------------------------
// Etape 3 : All In One WP Security (retiree).
// ---------------------------------------------------------------------------

// Extension supprimee le 22/10/2026 (Wordfence reste la seule suite de
// securite) : etape sans objet, numerotation des suivantes conservee.

// ---------------------------------------------------------------------------
// Etape 4 : WPForms.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- WPForms (option wpforms_settings) ---' );

if ( ! function_exists( 'wpforms_update_settings' ) ) {
	WP_CLI::warning( 'WPForms inactif : reglages non poses (rejouer ce script apres activation).' );
} else {
	$ditl_wpf_cibles = array(
		'gdpr'                    => true,
		'hide-announcements'      => true,
		'hide-admin-bar'          => true,
		'email-summaries-disable' => true,
	);

	$ditl_wpf_actuels = (array) get_option( 'wpforms_settings', array() );
	$ditl_wpf_ecarts  = array();

	foreach ( $ditl_wpf_cibles as $ditl_cle => $ditl_cible ) {
		if ( array_key_exists( $ditl_cle, $ditl_wpf_actuels ) && $ditl_wpf_actuels[ $ditl_cle ] === $ditl_cible ) {
			WP_CLI::log( sprintf( '  %s : deja a %s, rien a faire.', $ditl_cle, ditl_ext_libelle( $ditl_cible ) ) );
			continue;
		}

		$ditl_wpf_ecarts[ $ditl_cle ] = array_key_exists( $ditl_cle, $ditl_wpf_actuels ) ? $ditl_wpf_actuels[ $ditl_cle ] : null;

		WP_CLI::log( sprintf( '  %s%s : %s -> %s.', $ditl_dry_run ? '[dry-run] ' : '', $ditl_cle, ditl_ext_libelle( $ditl_wpf_ecarts[ $ditl_cle ] ), ditl_ext_libelle( $ditl_cible ) ) );
	}

	if ( array() !== $ditl_wpf_ecarts && ! $ditl_dry_run ) {
		// Sauvegarde admin a l'identique : filtre wpforms_update_settings et
		// action wpforms_settings_updated (annule la tache Action Scheduler
		// des blocs d'information des resumes). modern-markup et
		// modern-markup-is-set sont conserves tels quels.
		if ( wpforms_update_settings( array_merge( $ditl_wpf_actuels, $ditl_wpf_cibles ) ) ) {
			WP_CLI::log( '  Option wpforms_settings enregistree.' );
			$ditl_ecritures++;
		} else {
			WP_CLI::warning( 'Option wpforms_settings : enregistrement refuse.' );
		}
	}

	if ( isset( $ditl_wpf_actuels['modern-markup'] ) ) {
		WP_CLI::log( sprintf( '  modern-markup : %s, volontairement inchange (lot a11y B4).', ditl_ext_libelle( $ditl_wpf_actuels['modern-markup'] ) ) );
	}

	// Transients orphelins : cles d'anciennes versions de WPForms
	// (wpforms_htaccess_file et wpforms_<chemin absolu>/.htaccess_file), en
	// autoload, contenant les chemins d'anciens environnements. La 2.0 utilise
	// upload_htaccess_file, cache_htaccess_file et tmp_htaccess_file.
	global $wpdb;

	$ditl_wpf_prefixe = '_wpforms_transient_';
	$ditl_wpf_noms    = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name",
			$wpdb->esc_like( $ditl_wpf_prefixe . 'wpforms_' ) . '%'
		)
	);
	$ditl_wpf_orphelins = array();

	foreach ( (array) $ditl_wpf_noms as $ditl_nom ) {
		$ditl_cle_transient = substr( $ditl_nom, strlen( $ditl_wpf_prefixe ) );

		if ( preg_match( '#^wpforms_(/.+/)?\.?htaccess_file$#', $ditl_cle_transient ) ) {
			$ditl_wpf_orphelins[] = $ditl_cle_transient;
		}
	}

	if ( array() === $ditl_wpf_orphelins ) {
		WP_CLI::log( '  Transients orphelins _wpforms_transient_wpforms_*htaccess_file : aucun.' );
	} else {
		foreach ( $ditl_wpf_orphelins as $ditl_cle_transient ) {
			if ( $ditl_dry_run ) {
				WP_CLI::log( sprintf( '  [dry-run] Transient %s%s : serait supprime.', $ditl_wpf_prefixe, $ditl_cle_transient ) );
				continue;
			}

			if ( class_exists( '\WPForms\Helpers\Transient' ) ) {
				\WPForms\Helpers\Transient::delete( $ditl_cle_transient );
			} else {
				delete_option( $ditl_wpf_prefixe . $ditl_cle_transient );
				delete_option( '_wpforms_transient_timeout_' . $ditl_cle_transient );
			}

			WP_CLI::log( sprintf( '  Transient %s%s : supprime.', $ditl_wpf_prefixe, $ditl_cle_transient ) );
			$ditl_ecritures++;
		}
	}

	WP_CLI::log( '  Form Analytics : coupe par le filtre wpforms_analytics_should_track_user (inc/extensions.php ; wpforms_analytics_is_enabled est evalue avant le chargement du theme).' );
}

// ---------------------------------------------------------------------------
// Etape 5 : WP Mail SMTP.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- WP Mail SMTP (option wp_mail_smtp, groupe general) ---' );

if ( ! class_exists( '\WPMailSMTP\Options' ) ) {
	WP_CLI::warning( 'WP Mail SMTP inactif : reglage non pose (rejouer ce script apres activation).' );
} else {
	$ditl_smtp_options = \WPMailSMTP\Options::init();
	$ditl_smtp_actuel  = $ditl_smtp_options->get( 'general', 'summary_report_email_disabled' );

	if ( true === $ditl_smtp_actuel ) {
		WP_CLI::log( '  summary_report_email_disabled : deja a true, rien a faire.' );
	} elseif ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] summary_report_email_disabled : %s serait remplacee par true (tache wp_mail_smtp_summary_report_email annulee).', ditl_ext_libelle( $ditl_smtp_actuel ) ) );
	} else {
		// Onglet Misc a l'identique : annulation de la tache Action Scheduler
		// du rapport, puis fusion du reglage dans l'option (sanitize du plugin).
		if ( class_exists( '\WPMailSMTP\Tasks\Reports\SummaryEmailTask' ) ) {
			( new \WPMailSMTP\Tasks\Reports\SummaryEmailTask() )->cancel();
		}

		$ditl_smtp_options->set( array( 'general' => array( 'summary_report_email_disabled' => true ) ), false, false );

		if ( true === \WPMailSMTP\Options::init()->get( 'general', 'summary_report_email_disabled' ) ) {
			WP_CLI::log( sprintf( '  summary_report_email_disabled : %s -> true (tache wp_mail_smtp_summary_report_email annulee).', ditl_ext_libelle( $ditl_smtp_actuel ) ) );
			$ditl_ecritures++;
		} else {
			WP_CLI::warning( 'summary_report_email_disabled : valeur relue differente de true, ecriture non prise en compte.' );
		}
	}
}

// ---------------------------------------------------------------------------
// Etape 6 : mises a jour automatiques (option auto_update_plugins).
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Mises a jour automatiques (option auto_update_plugins) ---' );

$ditl_auto_cibles = array(
	// Wordfence (seule suite de securite) reste en manuel : son pare-feu
	// applicatif doit etre controle apres chaque mise a jour.
	'wp-fastest-cache/wpFastestCache.php',
	'wps-hide-login/wps-hide-login.php',
	'duplicate-page/duplicatepage.php',
);

$ditl_auto_actuels = (array) get_option( 'auto_update_plugins', array() );
$ditl_auto_nouveau = $ditl_auto_actuels;

foreach ( $ditl_auto_cibles as $ditl_fichier_plugin ) {
	if ( ! file_exists( WP_PLUGIN_DIR . '/' . $ditl_fichier_plugin ) ) {
		WP_CLI::log( sprintf( '  %s : extension absente de cet environnement, ignoree.', $ditl_fichier_plugin ) );
		continue;
	}

	if ( in_array( $ditl_fichier_plugin, $ditl_auto_nouveau, true ) ) {
		WP_CLI::log( sprintf( '  %s : deja en auto-update, rien a faire.', $ditl_fichier_plugin ) );
		continue;
	}

	$ditl_auto_nouveau[] = $ditl_fichier_plugin;
	WP_CLI::log( sprintf( '  %s%s : auto-update active.', $ditl_dry_run ? '[dry-run] ' : '', $ditl_fichier_plugin ) );
}

$ditl_auto_nouveau = array_values( array_unique( $ditl_auto_nouveau ) );

if ( $ditl_auto_nouveau !== array_values( $ditl_auto_actuels ) && ! $ditl_dry_run ) {
	// Meme ecriture que l'ecran des extensions du coeur (wp-admin/plugins.php).
	update_option( 'auto_update_plugins', $ditl_auto_nouveau );
	WP_CLI::log( '  Option auto_update_plugins enregistree.' );
	$ditl_ecritures++;
}

// ---------------------------------------------------------------------------
// Etape 7 : Freemius (telemetrie d'Interactive Geo Maps).
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Freemius : opt-out de la telemetrie ---' );

// Identifiants Freemius des modules (fs_dynamic_init de chaque extension).
$ditl_fs_modules = array(
	'interactive-geo-maps' => 5114,
);

if ( ! function_exists( 'freemius' ) || ! class_exists( 'FS_Permission_Manager' ) ) {
	WP_CLI::log( '  SDK Freemius absent (extensions inactives) : rien a faire.' );
} else {
	foreach ( $ditl_fs_modules as $ditl_slug => $ditl_id_module ) {
		if ( ! is_plugin_active( $ditl_slug . '/' . $ditl_slug . '.php' ) ) {
			WP_CLI::log( sprintf( '  %s : extension inactive, ignoree.', $ditl_slug ) );
			continue;
		}

		try {
			$ditl_fs = freemius( $ditl_id_module );
		} catch ( Exception $e ) {
			WP_CLI::warning( sprintf( '%s : instance Freemius introuvable (%s).', $ditl_slug, $e->getMessage() ) );
			continue;
		}

		if ( ! is_object( $ditl_fs ) || ! $ditl_fs->is_registered( true ) ) {
			WP_CLI::log( sprintf( '  %s : jamais connecte a Freemius, rien a faire.', $ditl_slug ) );
			continue;
		}

		$ditl_fs_cron = 'fs_data_sync_' . $ditl_slug;

		if ( $ditl_fs->is_tracking_prohibited() ) {
			WP_CLI::log( sprintf( '  %s : telemetrie deja coupee.', $ditl_slug ) );
		} elseif ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] %s : la permission "site" serait retiree (opt-out local).', $ditl_slug ) );
		} else {
			FS_Permission_Manager::instance( $ditl_fs )->update_site_tracking( false );

			if ( $ditl_fs->is_tracking_prohibited() ) {
				WP_CLI::log( sprintf( '  %s : telemetrie coupee (permission "site" retiree).', $ditl_slug ) );
				$ditl_ecritures++;
			} else {
				WP_CLI::warning( sprintf( '%s : la telemetrie reste autorisee apres update_site_tracking(false).', $ditl_slug ) );
			}
		}

		if ( false === wp_next_scheduled( $ditl_fs_cron ) ) {
			WP_CLI::log( sprintf( '  Cron %s : absent.', $ditl_fs_cron ) );
		} elseif ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] Cron %s : serait supprime.', $ditl_fs_cron ) );
		} else {
			wp_clear_scheduled_hook( $ditl_fs_cron );
			WP_CLI::log( sprintf( '  Cron %s : supprime (non reprogramme par le SDK tant que la telemetrie est coupee).', $ditl_fs_cron ) );
			$ditl_ecritures++;
		}
	}
}

// ---------------------------------------------------------------------------
// Etape 8 : bloc .htaccess (DITL Acces).
// ---------------------------------------------------------------------------

$ditl_ecritures += ditl_ext_poser_htaccess_acces( rtrim( ABSPATH, '/' ) . '/.htaccess', $ditl_dry_run );

// ---------------------------------------------------------------------------
// Rappels (aucune modification ici).
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Rappels ---' );
WP_CLI::log( '  Purger le cache de page (wp-content/cache/all) avant toute verification du front.' );
WP_CLI::log( '  Complianz : relancer le scan de cookies depuis le tableau de bord pour rafraichir la politique generee.' );
WP_CLI::log( '  Freemius : l\'opt-out cote API (Compte > Opt out) reste une manipulation admin optionnelle, voir le runbook.' );
WP_CLI::log( '  Le crawl cleanup Yoast est porte par cli/configurer-yoast.php : le rejouer si Yoast est reactive.' );
WP_CLI::log( '  Le .htaccess n\'est pas versionne : rejouer ce script sur chaque environnement.' );

WP_CLI::log( '' );

if ( $ditl_dry_run ) {
	WP_CLI::log( 'Simulation terminee.' );
} else {
	WP_CLI::log( sprintf( 'Configuration terminee (%d ecriture(s) lors de ce passage).', $ditl_ecritures ) );
}
