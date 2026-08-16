<?php
/**
 * Securisation des identifiants d'auteurs - phase 1 de la refonte.
 *
 * Constat (audit securite du 16/08) : plusieurs comptes ont un
 * user_nicename identique a leur user_login. Le nicename est PUBLIC : il
 * apparait dans les URLs d'archives d'auteur (/author/<nicename>/), dans le
 * sitemap des auteurs et dans la redirection d'enumeration ?author=N. Le
 * site publie donc des identifiants de connexion reels.
 *
 * Ce script remplace le nicename par un slug derive du nom d'affichage
 * (display_name) pour chaque compte dont le nicename est egal au login
 * (comparaison insensible a la casse). Les comptes sans nom d'affichage
 * exploitable (identique au login ou vide) sont signales et laisses
 * intacts : a traiter manuellement apres arbitrage.
 *
 * ISO-SEO : pour ne casser aucune URL d'auteur deja indexee, chaque
 * changement est enregistre dans l'option ditl_redirections_auteurs ;
 * le theme (inc/seo.php) emet une redirection 301 de l'ancienne URL vers
 * la nouvelle. Le sitemap des auteurs de Yoast est reconstruit ensuite
 * (voir rappel en fin d'execution).
 *
 * Reversibilite : les anciens nicenames sont sauvegardes dans l'option
 * ditl_sauvegarde_nicenames ; le mode "annuler" les restaure et purge les
 * deux options.
 *
 * Script idempotent, rejouable sans degat (local, preprod, prod).
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/securiser-auteurs.php dry-run
 *   wp eval-file wp-content/themes/ditl/cli/securiser-auteurs.php
 *   wp eval-file wp-content/themes/ditl/cli/securiser-auteurs.php annuler
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

// ---------------------------------------------------------------------------
// Lecture des arguments : simulation ou annulation.
// ---------------------------------------------------------------------------

$ditl_dry_run = false;
$ditl_annuler = false;

foreach ( (array) $args as $ditl_arg ) {
	if ( 'dry-run' === $ditl_arg || '--dry-run' === $ditl_arg ) {
		$ditl_dry_run = true;
	} elseif ( 'annuler' === $ditl_arg ) {
		$ditl_annuler = true;
	} else {
		WP_CLI::warning( sprintf( 'Argument ignore : %s', $ditl_arg ) );
	}
}

if ( $ditl_dry_run ) {
	WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucune ecriture en base ===' );
}

// ---------------------------------------------------------------------------
// Mode annuler : restaurer les nicenames sauvegardes puis purger les options.
// ---------------------------------------------------------------------------

if ( $ditl_annuler ) {
	$ditl_sauvegarde = get_option( 'ditl_sauvegarde_nicenames', array() );

	if ( ! is_array( $ditl_sauvegarde ) || array() === $ditl_sauvegarde ) {
		WP_CLI::log( 'Aucune sauvegarde de nicenames : rien a annuler.' );
		WP_CLI::halt( 0 );
	}

	foreach ( $ditl_sauvegarde as $ditl_id => $ditl_ancien ) {
		$ditl_id     = (int) $ditl_id;
		$ditl_ancien = (string) $ditl_ancien;

		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] utilisateur %d : nicename restaure a "%s".', $ditl_id, $ditl_ancien ) );
			continue;
		}

		$ditl_resultat = wp_update_user(
			array(
				'ID'            => $ditl_id,
				'user_nicename' => $ditl_ancien,
			)
		);

		if ( is_wp_error( $ditl_resultat ) ) {
			WP_CLI::warning( sprintf( 'Utilisateur %d : restauration refusee (%s).', $ditl_id, $ditl_resultat->get_error_message() ) );
		} else {
			// wp_update_user peut suffixer en cas de collision : relire la
			// valeur reellement posee pour ne pas logger une illusion.
			$ditl_relu = get_userdata( $ditl_id );
			$ditl_pose = $ditl_relu instanceof WP_User ? (string) $ditl_relu->user_nicename : $ditl_ancien;

			if ( $ditl_pose !== $ditl_ancien ) {
				WP_CLI::warning( sprintf( 'Utilisateur %d : nicename restaure a "%s" au lieu de "%s" (collision, slug suffixe).', $ditl_id, $ditl_pose, $ditl_ancien ) );
			} else {
				WP_CLI::log( sprintf( '  utilisateur %d : nicename restaure a "%s".', $ditl_id, $ditl_pose ) );
			}
		}
	}

	if ( ! $ditl_dry_run ) {
		delete_option( 'ditl_sauvegarde_nicenames' );
		delete_option( 'ditl_redirections_auteurs' );
		WP_CLI::log( 'Options ditl_sauvegarde_nicenames et ditl_redirections_auteurs purgees.' );
		WP_CLI::log( 'Rappel : reconstruire les indexables Yoast ("wp yoast index --reindex") pour rafraichir le sitemap des auteurs.' );
	}

	WP_CLI::log( $ditl_dry_run ? 'Simulation d\'annulation terminee.' : 'Annulation terminee.' );
	WP_CLI::halt( 0 );
}

// ---------------------------------------------------------------------------
// Passe principale : nicename derive du nom d'affichage quand nicename = login.
// ---------------------------------------------------------------------------

$ditl_sauvegarde   = get_option( 'ditl_sauvegarde_nicenames', array() );
$ditl_redirections = get_option( 'ditl_redirections_auteurs', array() );
$ditl_sauvegarde   = is_array( $ditl_sauvegarde ) ? $ditl_sauvegarde : array();
$ditl_redirections = is_array( $ditl_redirections ) ? $ditl_redirections : array();
$ditl_ecritures    = 0;

WP_CLI::log( '--- Nicenames publics egaux au login (comparaison insensible a la casse) ---' );

foreach ( get_users( array( 'fields' => 'all' ) ) as $ditl_utilisateur ) {
	$ditl_login    = (string) $ditl_utilisateur->user_login;
	$ditl_nicename = (string) $ditl_utilisateur->user_nicename;

	if ( strtolower( $ditl_nicename ) !== strtolower( $ditl_login ) ) {
		continue;
	}

	$ditl_nouveau = sanitize_title( (string) $ditl_utilisateur->display_name );

	// Nom d'affichage inexploitable (vide, ou retombant sur le login) :
	// on ne corrige pas a l'aveugle, arbitrage manuel requis.
	if ( '' === $ditl_nouveau || strtolower( $ditl_nouveau ) === strtolower( $ditl_login ) ) {
		WP_CLI::warning( sprintf(
			'Utilisateur %d ("%s") : nom d\'affichage inexploitable pour deriver un slug, nicename laisse intact - definir un nom d\'affichage puis rejouer.',
			(int) $ditl_utilisateur->ID,
			$ditl_utilisateur->display_name
		) );
		continue;
	}

	if ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] utilisateur %d : "%s" deviendrait "%s" (+ redirection 301).', (int) $ditl_utilisateur->ID, $ditl_nicename, $ditl_nouveau ) );
		continue;
	}

	$ditl_resultat = wp_update_user(
		array(
			'ID'            => (int) $ditl_utilisateur->ID,
			'user_nicename' => $ditl_nouveau,
		)
	);

	if ( is_wp_error( $ditl_resultat ) ) {
		WP_CLI::warning( sprintf( 'Utilisateur %d : mise a jour refusee (%s).', (int) $ditl_utilisateur->ID, $ditl_resultat->get_error_message() ) );
		continue;
	}

	// wp_update_user peut suffixer le slug en cas de collision : relire la
	// valeur reellement posee.
	$ditl_relu = get_userdata( (int) $ditl_utilisateur->ID );
	$ditl_pose = $ditl_relu instanceof WP_User ? (string) $ditl_relu->user_nicename : $ditl_nouveau;

	if ( ! isset( $ditl_sauvegarde[ (int) $ditl_utilisateur->ID ] ) ) {
		$ditl_sauvegarde[ (int) $ditl_utilisateur->ID ] = $ditl_nicename;
	}
	$ditl_redirections[ $ditl_nicename ] = $ditl_pose;
	$ditl_ecritures++;

	WP_CLI::log( sprintf( '  utilisateur %d : "%s" -> "%s" (redirection 301 enregistree).', (int) $ditl_utilisateur->ID, $ditl_nicename, $ditl_pose ) );
}

if ( $ditl_ecritures > 0 ) {
	update_option( 'ditl_sauvegarde_nicenames', $ditl_sauvegarde, false );
	update_option( 'ditl_redirections_auteurs', $ditl_redirections, false );
	WP_CLI::log( sprintf( '%d nicename(s) corrige(s), sauvegarde et redirections enregistrees.', $ditl_ecritures ) );
} else {
	WP_CLI::log( 'Aucune ecriture lors de ce passage.' );
}

// ---------------------------------------------------------------------------
// Rappels (aucune modification ici).
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Rappels ---' );
WP_CLI::log( '  Les redirections 301 des anciennes URLs d\'auteur sont servies par le theme (inc/seo.php, option ditl_redirections_auteurs).' );
WP_CLI::log( '  Reconstruire les indexables Yoast apres correction : wp yoast index --reindex (sinon le sitemap des auteurs peut servir les anciennes URLs).' );
WP_CLI::log( '  Les comptes signales "nom d\'affichage inexploitable" restent a corriger manuellement (definir un vrai nom d\'affichage en admin, puis rejouer ce script).' );

WP_CLI::log( '' );
WP_CLI::log( $ditl_dry_run ? 'Simulation terminee.' : 'Correction terminee.' );
