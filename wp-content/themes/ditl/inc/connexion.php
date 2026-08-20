<?php
/**
 * Habillage de l'ecran de connexion (wp-login) aux couleurs du site.
 *
 * Purement cosmetique : feuille de style dediee, logo du site et lien
 * d'en-tete. Aucun champ n'est ajoute ni retire, aucun comportement de
 * wp-login n'est modifie (les hooks des extensions de securite restent
 * intacts). Les textes affiches restent ceux du coeur WordPress,
 * localises dans la langue choisie par l'utilisateur.
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Charge les styles de l'ecran de connexion.
 *
 * Trois enqueues, tous limites au contexte wp-login (hook
 * login_enqueue_scripts) donc sans aucun impact sur le front public :
 *
 * 1. La feuille de polices Exo auto-hebergee generee par Astra
 *    (wp-content/astra-local-fonts/, dossier regenere par environnement) :
 *    wp-login ne passe pas par wp_enqueue_scripts, il n'herite donc pas des
 *    polices du theme. Choix du projet : polices auto-hebergees, jamais de
 *    requete vers Google Fonts (meme regle que le front, voir inc/perf.php).
 *    Si le dossier n'a pas encore ete genere sur l'environnement, la pile
 *    de secours systeme de la feuille connexion.css prend le relais.
 * 2. La feuille connexion.css du theme (toutes les vues de wp-login).
 * 3. Le logo du site en style en ligne : l'URL de l'image depend de
 *    l'environnement (local, preprod, prod), elle est donc resolue au
 *    rendu et jamais ecrite en dur dans la feuille. Sans logo declare dans
 *    le customizer, le logo WordPress par defaut reste affiche.
 */
function ditl_connexion_enqueue_styles() {
	$ditl_polices_chemin = WP_CONTENT_DIR . '/astra-local-fonts/astra-local-fonts.css';

	if ( is_readable( $ditl_polices_chemin ) ) {
		// Version = date du fichier : la feuille est regeneree par Astra
		// independamment du theme, DITL_THEME_VERSION serait decorrelee.
		wp_enqueue_style(
			'ditl-connexion-polices',
			content_url( 'astra-local-fonts/astra-local-fonts.css' ),
			array(),
			(string) filemtime( $ditl_polices_chemin )
		);
	}

	// Dependance sur la feuille login du coeur : garantit l'ordre de cascade.
	wp_enqueue_style(
		'ditl-connexion',
		get_stylesheet_directory_uri() . '/assets/css/connexion.css',
		array( 'login' ),
		DITL_THEME_VERSION
	);

	$ditl_logo_id = (int) get_theme_mod( 'custom_logo' );

	if ( $ditl_logo_id > 0 ) {
		// medium_large (768 px) : suffisant pour un affichage a 288 px
		// meme sur ecran Retina, sans charger l'original.
		$ditl_logo_url = wp_get_attachment_image_url( $ditl_logo_id, 'medium_large' );

		if ( $ditl_logo_url ) {
			// Contexte 'db' : pas d'entites HTML (un <style> n'est pas
			// decode par le navigateur, esc_url() classique casserait un
			// eventuel "&"). Les guillemets doubles autour de l'URL sont
			// surs : esc_url() les retire de l'URL elle-meme, impossible
			// d'en sortir, et les parentheses residuelles restent inertes
			// entre guillemets.
			wp_add_inline_style(
				'ditl-connexion',
				sprintf( '.login h1 a{background-image:url("%s");}', esc_url( $ditl_logo_url, null, 'db' ) )
			);
		}
	}
}
add_action( 'login_enqueue_scripts', 'ditl_connexion_enqueue_styles' );

/**
 * Fait pointer le logo de l'ecran de connexion vers l'accueil du site.
 *
 * @return string URL de la page d'accueil.
 */
function ditl_connexion_headerurl() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'ditl_connexion_headerurl' );

/**
 * Remplace le texte du lien du logo ("Powered by WordPress") par le nom
 * du site. Ce texte est masque visuellement mais lu par les lecteurs
 * d'ecran : il doit decrire la destination reelle du lien.
 *
 * @return string Nom du site.
 */
function ditl_connexion_headertext() {
	return get_bloginfo( 'name', 'display' );
}
add_filter( 'login_headertext', 'ditl_connexion_headertext' );
