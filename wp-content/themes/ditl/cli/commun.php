<?php
/**
 * Bibliotheque commune des scripts WP-CLI du theme DiTL.
 *
 * Regroupe les fonctions partagees par les scripts de ce dossier :
 * - lecture des arguments (IDs + dry-run pour les migrations, modes
 *   dry-run/annuler pour les scripts de configuration) ;
 * - chargement de l'arbre _elementor_data d'un contenu ;
 * - normalisation des URLs internes en URLs relatives ;
 * - verification d'existence d'un attachement reference.
 *
 * Chaque script consommateur reste un point d'entree autonome : il conserve
 * sa propre garde 404 puis charge cette bibliotheque via
 * require_once __DIR__ . '/commun.php'.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Bibliotheque chargee par les scripts de ce dossier - voir leurs docblocks.
	// En acces web direct, reponse muette pour ne rien reveler du fichier.
	http_response_code( 404 );
	exit( 1 );
}

if ( ! function_exists( 'ditl_cli_url_relative' ) ) {
	/**
	 * Rend relative une URL pointant vers le site lui-meme.
	 *
	 * Les URLs des JSON Elementor pointent vers la production
	 * (https://ditlproject.eu/...) : on ne conserve que le chemin pour que
	 * la valeur reste valable en local, preprod et prod. Les URLs externes
	 * sont laissees intactes. La liste des hotes internes du site vit ici,
	 * une seule fois pour tous les scripts.
	 *
	 * @param string $url URL a normaliser.
	 * @return string URL relative si interne, inchangee sinon.
	 */
	function ditl_cli_url_relative( $url ) {
		$parties = wp_parse_url( $url );

		if ( empty( $parties['host'] ) ) {
			return $url;
		}

		$hote_site      = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$hotes_internes = array( 'ditlproject.eu', 'www.ditlproject.eu' );

		if ( '' !== $hote_site ) {
			$hotes_internes[] = $hote_site;
		}

		if ( ! in_array( strtolower( $parties['host'] ), $hotes_internes, true ) ) {
			return $url;
		}

		$relative = isset( $parties['path'] ) && '' !== $parties['path'] ? $parties['path'] : '/';

		if ( isset( $parties['query'] ) ) {
			$relative .= '?' . $parties['query'];
		}

		if ( isset( $parties['fragment'] ) ) {
			$relative .= '#' . $parties['fragment'];
		}

		return $relative;
	}
}

if ( ! function_exists( 'ditl_cli_verifier_attachment' ) ) {
	/**
	 * Verifie qu'un ID d'attachement reference existe encore en mediatheque.
	 *
	 * @param int    $attachment_id ID a verifier (0 ignore).
	 * @param string $contexte      Contexte pour le message de warning.
	 */
	function ditl_cli_verifier_attachment( $attachment_id, $contexte ) {
		if ( $attachment_id <= 0 ) {
			return;
		}

		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			WP_CLI::warning( sprintf( 'Attachment %d introuvable (%s).', $attachment_id, $contexte ) );
		}
	}
}

if ( ! function_exists( 'ditl_cli_lire_ids_et_dry_run' ) ) {
	/**
	 * Lit les arguments d'un script de migration : IDs de contenus + dry-run.
	 *
	 * Le mode simulation accepte "dry-run" ou "--dry-run". Les arguments qui
	 * ne sont pas des IDs valides sont signales et ignores. Sans aucun ID,
	 * le script s'arrete en erreur. En mode simulation, la banniere
	 * "MODE SIMULATION" est affichee ici.
	 *
	 * @param array  $args       Arguments recus par le script (wp eval-file).
	 * @param string $libelle_id Libelle des IDs pour les messages
	 *                           ("de page" ou "d'article").
	 * @return array Tableau { dry_run => bool, ids => int[] }.
	 */
	function ditl_cli_lire_ids_et_dry_run( $args, $libelle_id ) {
		$dry_run = false;
		$ids     = array();

		foreach ( (array) $args as $arg ) {
			if ( 'dry-run' === $arg || '--dry-run' === $arg ) {
				$dry_run = true;
				continue;
			}

			$id = absint( $arg );

			if ( $id > 0 ) {
				$ids[] = $id;
			} else {
				WP_CLI::warning( sprintf( 'Argument ignore (ID %s invalide) : %s', $libelle_id, $arg ) );
			}
		}

		if ( empty( $ids ) ) {
			WP_CLI::error( sprintf( 'Aucun ID %s fourni. Usage : wp eval-file ... <id> [<id>...] [dry-run]', $libelle_id ) );
		}

		if ( $dry_run ) {
			WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucune ecriture en base ===' );
		}

		return array(
			'dry_run' => $dry_run,
			'ids'     => $ids,
		);
	}
}

if ( ! function_exists( 'ditl_cli_charger_arbre_elementor' ) ) {
	/**
	 * Charge et decode l'arbre _elementor_data d'un contenu.
	 *
	 * Reproduit le controle d'entree commun aux scripts de migration :
	 * contenu introuvable (ou mauvais type), meta _elementor_data absente ou
	 * vide, JSON illisible - chaque cas emet le warning historique du script
	 * et retourne null (l'appelant passe alors au contenu suivant). Entre le
	 * controle d'existence et la lecture de la meta, la banniere de log
	 * "--- Page/Article N ---" est affichee (titre tronque a 70 caracteres
	 * pour les articles, comme avant factorisation).
	 *
	 * @param int           $post_id   ID du contenu a charger.
	 * @param string        $post_type Type attendu ("page" ou "post").
	 * @param callable|null $garde     Controle supplementaire optionnel,
	 *                                 appele avec le WP_Post apres le controle
	 *                                 d'existence et avant la banniere de log ;
	 *                                 il emet ses propres messages et retourne
	 *                                 false pour ecarter le contenu.
	 * @return array|null Arbre Elementor decode, ou null si le contenu est ecarte.
	 */
	function ditl_cli_charger_arbre_elementor( $post_id, $post_type, $garde = null ) {
		// Libelles des messages selon le type de contenu traite.
		if ( 'post' === $post_type ) {
			$nom    = 'Article';
			$objet  = 'article';
			$ecarte = 'ignore';
		} else {
			$nom    = 'Page';
			$objet  = 'page';
			$ecarte = 'ignoree';
		}

		$post = get_post( $post_id );

		if ( ! $post || $post_type !== $post->post_type ) {
			WP_CLI::warning( sprintf( '%s %d introuvable (ou pas de type "%s") : %s.', $nom, $post_id, $post_type, $ecarte ) );
			return null;
		}

		if ( null !== $garde && true !== call_user_func( $garde, $post ) ) {
			return null;
		}

		$titre = 'post' === $post_type ? mb_substr( $post->post_title, 0, 70 ) : $post->post_title;

		WP_CLI::log( '' );
		WP_CLI::log( sprintf( '--- %s %d : "%s" ---', $nom, $post_id, $titre ) );

		$elementor_raw = (string) get_post_meta( $post_id, '_elementor_data', true );

		if ( '' === $elementor_raw ) {
			WP_CLI::warning( sprintf( '%s %d : meta _elementor_data absente ou vide, %s %s.', $nom, $post_id, $objet, $ecarte ) );
			return null;
		}

		$elements = json_decode( $elementor_raw, true );

		if ( ! is_array( $elements ) ) {
			WP_CLI::warning( sprintf( '%s %d : JSON _elementor_data illisible, %s %s.', $nom, $post_id, $objet, $ecarte ) );
			return null;
		}

		return $elements;
	}
}

if ( ! function_exists( 'ditl_cli_lire_modes' ) ) {
	/**
	 * Lit les arguments d'un script de configuration : dry-run et annuler.
	 *
	 * Les deux modes acceptent les formes avec et sans tirets (dry-run ou
	 * --dry-run, annuler ou --annuler) et sont combinables. Tout autre
	 * argument est signale et ignore. La banniere "MODE SIMULATION" reste
	 * affichee par chaque script (son texte varie selon le script). Un script
	 * sans mode annuler doit refuser explicitement annuler => true.
	 *
	 * CHANGEMENT DE COMPORTEMENT DOCUMENTE (factorisation, lot A) : avant,
	 * securiser-auteurs.php n'acceptait que la forme "annuler" seche ;
	 * "--annuler" y etait traite en argument inconnu et le script executait
	 * le mode NORMAL (bug de coherence avec les autres scripts). Desormais
	 * "--annuler" y declenche bien l'annulation. NB : en pratique WP-CLI
	 * intercepte les arguments "--xxx" avant eval-file ; les formes a tirets
	 * ne l'atteignent que via un $args construit autrement (wp eval + require).
	 *
	 * @param array $args Arguments recus par le script (wp eval-file).
	 * @return array Tableau { dry_run => bool, annuler => bool }.
	 */
	function ditl_cli_lire_modes( $args ) {
		$dry_run = false;
		$annuler = false;

		foreach ( (array) $args as $arg ) {
			if ( 'dry-run' === $arg || '--dry-run' === $arg ) {
				$dry_run = true;
			} elseif ( 'annuler' === $arg || '--annuler' === $arg ) {
				$annuler = true;
			} else {
				WP_CLI::warning( sprintf( 'Argument ignore : %s', $arg ) );
			}
		}

		return array(
			'dry_run' => $dry_run,
			'annuler' => $annuler,
		);
	}
}
