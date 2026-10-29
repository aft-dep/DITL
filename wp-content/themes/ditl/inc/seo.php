<?php
/**
 * Ajustements SEO du theme (iso-SEO avec l'existant apres activation de Yoast).
 *
 * Yoast SEO n'est introduit que pour AJOUTER (sitemap XML plus riche, balises
 * Open Graph / Twitter, donnees structurees JSON-LD, saisie future des meta
 * descriptions) : rien de ce que le coeur de WordPress et Polylang emettaient
 * deja dans le <head> (titres, canonical, hreflang, meta robots) ne doit
 * changer. Ce fichier neutralise les seuls ecarts que la configuration par
 * options ne couvre pas.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ramene la meta robots de Yoast a la valeur emise par le coeur de WordPress.
 *
 * Sans Yoast, le coeur emet "max-image-preview:large". Yoast y ajoute par
 * defaut "index, follow, max-snippet:-1, max-video-preview:-1" : directives
 * strictement equivalentes pour les moteurs (ce sont leurs valeurs par
 * defaut en l'absence de directive), mais l'exigence d'iso-SEO de la refonte
 * est une identite au byte pres. Les directives redondantes sont donc
 * retirees UNIQUEMENT quand la page reste indexable : si une restriction
 * (noindex, nofollow, max-snippet personnalise...) est posee un jour via
 * Yoast, elle passe intacte.
 *
 * Sans Yoast actif, le tableau du coeur ne contient aucune de ces cles :
 * le filtre est alors sans effet (ordre d'activation indifferent).
 *
 * @param array $robots Directives robots (cle => valeur ou true).
 * @return array Directives filtrees.
 */
function ditl_seo_robots_iso( $robots ) {
	if ( ! is_array( $robots ) || ! empty( $robots['noindex'] ) || ! empty( $robots['nofollow'] ) ) {
		return $robots;
	}

	// "index" et "follow" sont le comportement par defaut des moteurs :
	// leur absence est equivalente (c'est ce que le coeur emettait).
	unset( $robots['index'], $robots['follow'] );

	// -1 = illimite = valeur par defaut des moteurs : redondant aussi.
	if ( isset( $robots['max-snippet'] ) && '-1' === (string) $robots['max-snippet'] ) {
		unset( $robots['max-snippet'] );
	}

	if ( isset( $robots['max-video-preview'] ) && '-1' === (string) $robots['max-video-preview'] ) {
		unset( $robots['max-video-preview'] );
	}

	return $robots;
}
// Yoast injecte ses directives a PHP_INT_MAX - 10 : la normalisation doit
// passer apres lui.
add_filter( 'wp_robots', 'ditl_seo_robots_iso', PHP_INT_MAX );

/**
 * Restaure dans le robots.txt virtuel les regles par defaut de WordPress
 * que Yoast retire.
 *
 * Sans Yoast, WordPress genere :
 *   User-agent: *
 *   Disallow: /wp-admin/
 *   Allow: /wp-admin/admin-ajax.php
 * Yoast supprime ce bloc et le remplace par un "Disallow:" vide (sa
 * philosophie : ne rien bloquer). Iso-SEO oblige, les memes regles sont
 * reinjectees dans le bloc Yoast via l'action prevue a cet effet. La ligne
 * "Sitemap:" pointe desormais vers sitemap_index.xml (Yoast), l'ancienne
 * URL /wp-sitemap.xml etant redirigee en 301 par Yoast lui-meme.
 *
 * Sans Yoast actif, l'action n'est jamais declenchee : le robots.txt du
 * coeur reste intact (ordre d'activation indifferent).
 *
 * @param Yoast\WP\SEO\Helpers\Robots_Txt_Helper $robots_txt_helper Collecteur de regles de Yoast.
 */
function ditl_seo_robots_txt_regles_wp( $robots_txt_helper ) {
	if ( ! is_object( $robots_txt_helper )
		|| ! method_exists( $robots_txt_helper, 'add_disallow' )
		|| ! method_exists( $robots_txt_helper, 'add_allow' ) ) {
		return;
	}

	$robots_txt_helper->add_disallow( '*', '/wp-admin/' );
	$robots_txt_helper->add_allow( '*', '/wp-admin/admin-ajax.php' );
}
add_action( 'Yoast\WP\SEO\register_robots_rules', 'ditl_seo_robots_txt_regles_wp' );

/**
 * Redirige en 301 les anciennes URLs d'archives d'auteur vers les nouvelles.
 *
 * Le script cli/securiser-auteurs.php remplace les user_nicename qui
 * exposaient des identifiants de connexion reels (constat de l'audit
 * securite du 16/08) et consigne chaque changement dans l'option
 * ditl_redirections_auteurs (ancien slug => nouveau slug). Iso-SEO oblige,
 * les URLs d'auteur deja indexees ne doivent pas casser : quand une requete
 * d'archive d'auteur aboutit a un 404 sur un ancien slug connu, elle est
 * redirigee en 301 vers l'archive du nouveau slug.
 *
 * Compromis assume : les cles de la table sont les anciens nicenames, qui
 * etaient egaux aux logins - mais ces slugs etaient deja publics (URLs
 * indexees, sitemap). La 301 confirme seulement une correspondance deja
 * connue des moteurs ; elle n'expose rien de nouveau et les nouvelles URLs
 * ne derivent plus du login. La cible est validee comme utilisateur
 * existant avant toute redirection : aucune redirection arbitraire
 * possible. La pagination d'archive (/page/2/) n'est pas reportee : la
 * redirection renvoie a la premiere page de la nouvelle archive (assume).
 */
function ditl_seo_redirection_anciens_auteurs() {
	if ( ! is_404() ) {
		return;
	}

	$ditl_ancien = (string) get_query_var( 'author_name' );

	if ( '' === $ditl_ancien ) {
		return;
	}

	$ditl_redirections = get_option( 'ditl_redirections_auteurs', array() );

	if ( ! is_array( $ditl_redirections ) || empty( $ditl_redirections[ $ditl_ancien ] ) ) {
		return;
	}

	$ditl_utilisateur = get_user_by( 'slug', (string) $ditl_redirections[ $ditl_ancien ] );

	if ( ! $ditl_utilisateur instanceof WP_User ) {
		return;
	}

	wp_safe_redirect( get_author_posts_url( $ditl_utilisateur->ID ), 301 );
	exit;
}
add_action( 'template_redirect', 'ditl_seo_redirection_anciens_auteurs' );

/*
 * ---------------------------------------------------------------------------
 * Titres de page (<title>) identiques a ceux du coeur.
 * ---------------------------------------------------------------------------
 *
 * Yoast court-circuite wp_get_document_title() (filtre
 * pre_get_document_title) et construit le titre a partir de ses gabarits
 * d'options. Trois ecarts avec le coeur en decoulent (releves le 28/10/2026,
 * corriges ici par le code plutot que par des options en base, pour suivre
 * le deploiement) :
 * 1. recherche et page 404 : les gabarits title-search-wpseo et
 *    title-404-wpseo sont des textes fixes en anglais, communs a toutes les
 *    langues ; le coeur utilise ses chaines traduites dans la langue de la
 *    page ("Resultats de recherche pour", "Page non trouvee"...). Ces deux
 *    titres sont donc reconstruits comme le coeur le fait (les deux options
 *    Yoast restent en base mais ne s'appliquent plus a ces vues) ;
 * 2. paginations : la variable %%page%% de Yoast vaut "Page 2 of 3" (chaine
 *    de Yoast) sur les archives et reste vide sur une page dont le gabarit
 *    pagine sa propre liste (/news/page/2/) ; le coeur ajoute "Page 2"
 *    (chaine du coeur traduite) dans les deux cas ;
 * 3. typographie : le coeur passe le titre dans wptexturize (apostrophes et
 *    guillemets typographiques), Yoast non.
 *
 * Les chaines __( 'Page %s' ), __( 'Page not found' ) et
 * __( 'Search Results for &#8220;%s&#8221;' ) sont VOLONTAIREMENT appelees
 * sans domaine de traduction : ce sont celles du coeur, avec ses
 * traductions, exactement comme dans wp_get_document_title().
 *
 * Sans Yoast actif, aucun de ces filtres n'est declenche : le titre du coeur
 * s'applique tel quel (ordre d'activation indifferent).
 */

/**
 * Numero de page que le coeur ajoute au titre, ou 0.
 *
 * Meme condition que wp_get_document_title() : pagination d'une archive ou
 * d'une page qui pagine sa propre liste (variable paged, ex. /news/page/2/)
 * ou d'une publication decoupee en pages (variable page), jamais sur une
 * page 404.
 *
 * @return int Numero de page (2 ou plus), 0 sinon.
 */
function ditl_seo_titre_numero_page() {
	global $page, $paged;

	if ( is_404() ) {
		return 0;
	}

	$ditl_numero = max( (int) $paged, (int) $page );

	return $ditl_numero >= 2 ? $ditl_numero : 0;
}

/**
 * Libelle de pagination du coeur ("Page 2", "Pagina 2", "Seite 2"...), ou ''.
 *
 * @return string Libelle traduit dans la langue de la page.
 */
function ditl_seo_titre_libelle_page() {
	$ditl_numero = ditl_seo_titre_numero_page();

	if ( 0 === $ditl_numero ) {
		return '';
	}

	/* translators: %s: Page number. */
	return sprintf( __( 'Page %s' ), $ditl_numero );
}

/**
 * Separateur des titres, sous la forme que Yoast insere lui-meme (entite
 * HTML, &#8211; avec le reglage sc-ndash pose par cli/configurer-yoast.php).
 *
 * Lecture partagee avec le titre d'etat de la boite a outils
 * (ditl_boite_seo_separateur(), inc/boite/seo.php, toujours charge).
 * Repli : tiret simple du coeur, que wptexturize transforme en &#8211;
 * comme dans les titres du coeur.
 *
 * @return string Separateur.
 */
function ditl_seo_titre_separateur() {
	if ( function_exists( 'ditl_boite_seo_separateur' ) ) {
		return ditl_boite_seo_separateur( true );
	}

	return '-';
}

/**
 * Variable %%page%% de Yoast au format du coeur.
 *
 * Remplace "- Page 2 of 3" (chaine de Yoast, absente sur /news/page/2/) par
 * "- Page 2" (chaine du coeur traduite), dans tous les titres construits
 * par Yoast a partir de ses gabarits : <title>, og:title et nom de la page
 * dans le JSON-LD restent ainsi coherents entre eux. Sans numero de page
 * chez le coeur, la variable est retiree (Yoast la retire aussi).
 *
 * @param array $replacements Remplacements de Yoast (variable => valeur).
 * @return array Remplacements ajustes.
 */
function ditl_seo_titre_variable_page( $replacements ) {
	if ( ! is_array( $replacements ) || is_admin() ) {
		return $replacements;
	}

	$ditl_libelle = ditl_seo_titre_libelle_page();

	if ( '' === $ditl_libelle ) {
		unset( $replacements['%%page%%'] );

		return $replacements;
	}

	$replacements['%%page%%'] = ditl_seo_titre_separateur() . ' ' . $ditl_libelle;

	return $replacements;
}
add_filter( 'wpseo_replacements', 'ditl_seo_titre_variable_page' );

/**
 * Titre du coeur pour la recherche et la page 404, ou ''.
 *
 * Reprend wp_get_document_title() : libelle traduit, numero de page
 * eventuel (recherche paginee), nom du site, separes par le separateur des
 * titres. La typographie (wptexturize) est appliquee ensuite par
 * ditl_seo_titre_iso(), comme le fait le filtre document_title du coeur.
 *
 * @return string Titre brut, '' hors recherche et 404.
 */
function ditl_seo_titre_coeur_recherche_404() {
	if ( is_404() ) {
		$ditl_parties = array( __( 'Page not found' ) );
	} elseif ( is_search() ) {
		/* translators: %s: Search query. */
		$ditl_parties = array( sprintf( __( 'Search Results for &#8220;%s&#8221;' ), get_search_query() ) );

		$ditl_parties[] = ditl_seo_titre_libelle_page();
	} else {
		return '';
	}

	$ditl_parties[] = get_bloginfo( 'name', 'display' );

	return implode( ' ' . ditl_seo_titre_separateur() . ' ', array_filter( $ditl_parties ) );
}

/**
 * Titre emis par Yoast ramene au titre du coeur.
 *
 * Recherche et 404 : titre du coeur (libelles traduits). Toutes vues :
 * memes traitements que le filtre document_title du coeur (wptexturize,
 * convert_chars, capital_P_dangit ; l'echappement esc_html est fait par
 * Yoast a l'affichage), puis wp_strip_all_tags, que Yoast applique au
 * <title> et a og:title mais pas a twitter:title : les trois balises
 * sortent ainsi identiques (octet NUL d'une recherche compris). Branche apres les autres filtres de titre du theme
 * (titre d'etat de la boite a outils, priorite 10), comme document_title
 * passe apres document_title_parts dans le coeur.
 *
 * Applique aussi a og:title et twitter:title, que Yoast derive du meme
 * gabarit : le titre partage reste identique au titre de l'onglet.
 *
 * @param string $titre Titre calcule par Yoast.
 * @return string Titre ajuste.
 */
function ditl_seo_titre_iso( $titre ) {
	// Titre vide : Yoast n'emet pas la balise, rien a ajouter.
	if ( ! is_string( $titre ) || '' === $titre || is_admin() ) {
		return $titre;
	}

	$ditl_coeur = ditl_seo_titre_coeur_recherche_404();

	if ( '' !== $ditl_coeur ) {
		$titre = $ditl_coeur;
	}

	return wp_strip_all_tags( capital_P_dangit( convert_chars( wptexturize( $titre ) ) ) );
}
add_filter( 'wpseo_title', 'ditl_seo_titre_iso', 20 );
add_filter( 'wpseo_opengraph_title', 'ditl_seo_titre_iso', 20 );
add_filter( 'wpseo_twitter_title', 'ditl_seo_titre_iso', 20 );
