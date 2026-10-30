<?php
/**
 * Optimisation du chargement (phase 1 de la refonte).
 *
 * Degraissage des assets charges sur le front : chaque retrait ci-dessous a
 * ete PROUVE sur les pages temoins (/, /fr/accueil/, /fr/contact/, /news/)
 * avant d'etre code - inventaire des handles, recherche des consommateurs
 * reels dans le HTML rendu, diff avant/apres du <head>.
 *
 * Ce fichier ne retire QUE ce qui est demontre inutile :
 * - emojis WordPress (script de remplacement Twemoji et sa feuille : les
 *   emojis des contenus s'affichent en glyphes natifs ; la regle qui
 *   dimensionne les emojis stockes en image est reprise dans style.css) ;
 * - jquery-migrate (aucun script charge n'utilise d'API jQuery supprimee :
 *   jquery.validate et WPForms supportent jQuery 3 sans migrate. Depuis le
 *   remplacement de la recherche d'en-tete par le composant natif du theme
 *   (inc/recherche.php, 08/09/2026), WPForms est le seul consommateur de
 *   jQuery et il ne reste charge que sur les pages a formulaire) ;
 * - feuille commune des blocs wp-block-library (seules les regles
 *   .has-text-align-* ont un consommateur et sont reprises dans style.css ;
 *   le body n'utilise ni .wp-element-button, ni les autres classes .has-*
 *   de cette feuille, ni variables --wp--preset-- ; les feuilles PAR BLOC reellement consommees
 *   (wp-block-image, wp-block-list, wp-block-paragraph) et global-styles
 *   (marge body, text-decoration des liens) sont CONSERVEES) ;
 * - script Site Kit de suivi des formulaires WPForms sur les pages sans
 *   formulaire (aucun hook natif de Site Kit ne le permet : dequeue cible) ;
 * - flexibility.js d'Astra (polyfill Flexbox pour Internet Explorer, sans
 *   aucun effet dans les autres navigateurs).
 *
 * Complements de chargement :
 * - preconnect vers googletagmanager.com (seule origine tierce restante
 *   apres l'hebergement local des polices) ;
 * - preload de l'image de banniere des gabarits DiTL (image LCP) ;
 * - integration en ligne des petites feuilles bloquantes du <head> par le
 *   mecanisme natif du coeur (meme contenu, meme place dans la cascade).
 *
 * CONSERVES volontairement (decisions documentees dans le rapport du
 * chantier) : global-styles, feuilles par bloc, prefetch speculatif du
 * coeur (bloc speculationrules, benefique), CSS dynamique Astra inline
 * (la generation en fichier appartient a Astra Pro via Astra_Cache,
 * absente du theme gratuit ; gzip le compresse bien).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Desactive les emojis WordPress (front, admin, flux et mails).
 *
 * Le detecteur (JSON de reglages + script module en pied de page, environ
 * 3 Ko) ne remplace plus les emojis par des images Twemoji : les emojis
 * saisis dans les contenus (18 articles) s'affichent en glyphes natifs du
 * systeme (arbitrage natif / Twemoji ouvert, ecart connu avec la prod).
 * Les emojis stockes en dur en image dans un contenu (<img class="emoji">,
 * article "premiere newsletter" dans les 5 langues) restent dimensionnes :
 * la feuille inline du coeur (img.emoji a 1em) est reprise a l'identique
 * dans style.css. Le filtre emoji_svg_url coupe aussi le dns-prefetch
 * vers s.w.org. Retrait classique complet, identique
 * a celui que propose l'option dediee de WP Fastest Cache (non activee :
 * le retrait est porte par le theme pour rester actif quel que soit le
 * plugin de cache).
 */
function ditl_desactiver_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'ditl_desactiver_emojis' );

/**
 * Retire jquery-migrate des dependances de jQuery sur le front.
 *
 * jQuery Migrate 3.4.1 (7,7 Ko) ne retablit que des APIs supprimees de
 * jQuery 3.x. Inventaire des scripts charges sur le front : depuis le
 * remplacement de la recherche du header (Ivory Search, 08/09/2026,
 * extension supprimee le 22/10/2026), jQuery n'est plus charge que sur le
 * gabarit Contact, pour jquery.validate 1.21 et WPForms 1.9.9, tous deux
 * compatibles jQuery 3 sans migrate.
 *
 * L'admin n'est pas touche (les extensions y chargent leurs propres
 * scripts, hors perimetre).
 *
 * @param WP_Scripts $scripts Registre des scripts en cours d'initialisation.
 */
function ditl_retirer_jquery_migrate( $scripts ) {
	if ( is_admin() ) {
		return;
	}

	if ( isset( $scripts->registered['jquery'] ) && $scripts->registered['jquery'] instanceof _WP_Dependency ) {
		$scripts->registered['jquery']->deps = array_values(
			array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) )
		);
	}
}
add_action( 'wp_default_scripts', 'ditl_retirer_jquery_migrate' );

/**
 * Retire la feuille commune des blocs (wp-block-library).
 *
 * Astra charge les assets de blocs SEPAREMENT (une petite feuille inline
 * par bloc reellement present : wp-block-image, wp-block-list,
 * wp-block-paragraph - toutes trois consommees par les contenus migres et
 * les widgets de pied de page, donc conservees). La feuille commune
 * wp-block-library (3,6 Ko inline par page) ne contient, a une exception
 * pres, que des regles sans consommateur dans le HTML rendu :
 * .wp-element-button, classes .has-* (couleurs et degrades de la palette),
 * variables d'admin et tailles --wp--preset--font-size-* (utilisees nulle
 * part, ni dans le body, ni dans les CSS du theme). global-styles est CONSERVEE : ses
 * regles hors presets s'appliquent reellement (marge du body,
 * text-decoration des liens, layouts). classic-theme-styles n'est pas
 * enqueue sur ce site (rien a retirer). Exception : les trois regles
 * .has-text-align-* (paragraphe "In progress" centre des pages Livrable 2,
 * 3 et 4) sont reprises a l'identique dans style.css plutot que de
 * recharger toute la feuille.
 */
function ditl_retirer_css_blocs_sans_consommateur() {
	wp_dequeue_style( 'wp-block-library' );
}
add_action( 'wp_enqueue_scripts', 'ditl_retirer_css_blocs_sans_consommateur', 100 );

/**
 * Retire le script Site Kit de suivi WPForms sur les pages sans formulaire.
 *
 * Site Kit enqueue googlesitekit-events-provider-wpforms sur TOUTES les
 * pages des que WPForms est actif (Conversion_Tracking::maybe_enqueue_scripts,
 * priorite 30), alors que le script ne fait rien sans formulaire dans la
 * page. Aucun filtre natif de Site Kit ne permet de le conditionner :
 * dequeue cible, avec double garde pour ne jamais perdre le suivi des
 * conversions (evenement submit_lead_form) :
 * - le gabarit Contact (seules pages a formulaire aujourd'hui) le garde
 *   toujours ;
 * - toute page ou WPForms enqueue ses propres assets le garde aussi
 *   (protege un futur formulaire insere hors gabarit Contact).
 *
 * LIMITE CONNUE : la garde wp_script_is ne voit pas un formulaire dont
 * WPForms n'enqueue les assets qu'au rendu (apres priorite 60), par exemple
 * en widget. Si un formulaire est un jour ajoute hors gabarit et hors
 * contenu de page, verifier que son suivi Site Kit remonte toujours.
 */
function ditl_retirer_sitekit_wpforms_sans_formulaire() {
	if ( defined( 'DITL_TPL_CONTACT' ) && is_page_template( DITL_TPL_CONTACT ) ) {
		return;
	}

	if ( wp_script_is( 'wpforms', 'enqueued' ) ) {
		return;
	}

	wp_dequeue_script( 'googlesitekit-events-provider-wpforms' );
}
add_action( 'wp_enqueue_scripts', 'ditl_retirer_sitekit_wpforms_sans_formulaire', 60 );

/**
 * Retire flexibility.js, polyfill Flexbox d'Internet Explorer charge par Astra.
 *
 * Astra 4.12 enqueue astra-flexibility dans le <head> (script synchrone de
 * 15,5 Ko, 4,3 Ko gzip, suivi de l'appel en ligne
 * flexibility(document.documentElement)) sur toutes les pages et pour tous
 * les navigateurs : la condition "IE" qui le reservait autrefois a Internet
 * Explorer a disparu avec la fin des commentaires conditionnels
 * (WordPress 6.9). C'etait la seule ressource bloquante de type script.
 *
 * Preuve de l'absence d'effet hors Internet Explorer (source
 * assets/js/unminified/flexibility.js d'Astra) : le polyfill ne traite qu'un
 * element dont l'attribut data-style declare display:flex ou dont
 * currentStyle["-js-display"] vaut flex. currentStyle n'existe que dans
 * Internet Explorer et data-style n'est pose que sur un element deja
 * detecte : dans Chrome, Firefox, Safari et Edge, aucun element n'est
 * detecte et rien n'est ecrit (l'appel a lieu en outre dans le <head>,
 * avant le body). Aucun script ne declare la dependance astra-flexibility
 * ni n'appelle flexibility (recherche dans wp-content/themes,
 * wp-content/plugins et wp-includes, 29/10/2026). Internet Explorer n'est
 * plus un navigateur cible (fin de support par Microsoft en 2022, par
 * WordPress depuis la 5.8) et le site ne s'y affiche deja plus correctement
 * (variables CSS d'Astra et du theme, inconnues d'Internet Explorer).
 *
 * Le dequeue retire aussi le script en ligne attache au handle.
 */
function ditl_retirer_flexibility_ie() {
	wp_dequeue_script( 'astra-flexibility' );
}
add_action( 'wp_enqueue_scripts', 'ditl_retirer_flexibility_ie', 100 );

/**
 * Integre en ligne les petites feuilles bloquantes du <head>.
 *
 * Chaque feuille listee ci-dessous etait une requete bloquante de quelques
 * Ko. Son contenu est desormais imprime dans une balise <style> A LA MEME
 * PLACE que l'ancienne balise <link>, par le mecanisme natif du coeur qui
 * integre deja les feuilles des blocs (wp_maybe_inline_styles, sur wp_head
 * et wp_footer) : il suffit de declarer le chemin du fichier (donnee
 * "path"). Le coeur lit le fichier, rend ses url() relatives a la racine
 * du site (_wp_normalize_relative_css_links) et imprime
 * <style id="<handle>-inline-css">, dans un budget de 40 Ko par passage
 * (wp_head, puis wp_footer ; filtre styles_inline_size_limit, plus petites
 * feuilles d'abord) :
 * au-dela, la feuille reste en <link>.
 *
 * Rendu identique : meme contenu, meme position dans la cascade, memes
 * fichiers de polices. Les fichiers sources restent la reference (aucune
 * copie a maintenir, aucun outil de construction).
 *
 * Feuilles concernees (tailles au 29/10/2026) :
 * - theme : style.css (1,7 Ko, toutes les pages), gabarits-communs.css
 *   (3,2 Ko, gabarits DiTL), police-roboto.css et police-jost.css (1,5 et
 *   1,4 Ko, gabarits Contact et Livrable) ;
 * - Astra : menu-animation.min.css (3,5 Ko, animation "slide-up" des
 *   sous-menus du menu principal) et astra-local-fonts.css (5,1 Ko,
 *   @font-face d'Exo generees par Astra dans wp-content/astra-local-fonts/,
 *   URLs absolues) ;
 * - Complianz : cookieblocker.min.css (2,8 Ko, contenus bloques avant
 *   consentement).
 * Les feuilles plus lourdes (CSS principale d'Astra, feuilles des
 * gabarits, a11y.css, recherche.css, banniere Complianz, WPForms) restent
 * en <link> : le navigateur les garde en cache d'une page a l'autre.
 *
 * Gardes (robustesse aux mises a jour d'Astra et de Complianz) : handle
 * absent ou renomme = rien ne change ; fichier deduit de l'URL enregistree
 * (aucun chemin d'extension code en dur), situe sous wp-content, lisible,
 * en .css et de 6 Ko au plus (une feuille devenue lourde repasse en
 * <link>) ; media "all" seulement (la balise <style> du coeur ne porte pas
 * d'attribut media) ; ni feuille alternative, ni conditionnelle, ni
 * variante RTL ; front seulement, hors apercu du personnalisateur (Astra y
 * manipule la feuille des polices).
 *
 * Cache de page : le CSS integre fait partie du HTML mis en cache par WP
 * Fastest Cache, que le deploiement vide (.github/scripts/deployer.sh).
 * Apres une mise a jour d'Astra ou de Complianz faite depuis
 * l'administration, les pages en cache gardent l'ancien CSS integre
 * jusqu'a la prochaine purge du cache (un <link> servait directement le
 * nouveau fichier) : purger le cache apres une telle mise a jour.
 */
function ditl_integrer_petites_feuilles() {
	if ( is_admin() || is_customize_preview() ) {
		return;
	}

	$ditl_handles = array(
		'ditl-style',
		'ditl-gabarits-communs',
		'ditl-police-roboto',
		'ditl-police-jost',
		'astra-menu-animation',
		'astra-google-fonts',
		'cmplz-general',
	);

	$ditl_styles = wp_styles();

	foreach ( $ditl_handles as $ditl_handle ) {
		$ditl_chemin = ditl_chemin_feuille_integrable( $ditl_styles, $ditl_handle );

		if ( '' !== $ditl_chemin ) {
			$ditl_styles->add_data( $ditl_handle, 'path', $ditl_chemin );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'ditl_integrer_petites_feuilles', 100 );

/**
 * Chemin du fichier d'une feuille enfilee, si elle peut etre integree.
 *
 * @param WP_Styles $styles Registre des feuilles.
 * @param string    $handle Handle de la feuille.
 * @return string Chemin absolu du fichier, ou chaine vide si la feuille
 *                doit rester en <link>.
 */
function ditl_chemin_feuille_integrable( $styles, $handle ) {
	if ( ! wp_style_is( $handle, 'enqueued' ) || ! isset( $styles->registered[ $handle ] ) ) {
		return '';
	}

	$ditl_feuille = $styles->registered[ $handle ];

	if ( ! is_string( $ditl_feuille->src ) || '' === $ditl_feuille->src || $styles->get_data( $handle, 'path' ) ) {
		return '';
	}

	$ditl_media = $ditl_feuille->args ? $ditl_feuille->args : 'all';

	if ( 'all' !== $ditl_media || $styles->get_data( $handle, 'alt' ) || $styles->get_data( $handle, 'conditional' ) ) {
		return '';
	}

	if ( is_rtl() && $styles->get_data( $handle, 'rtl' ) ) {
		return '';
	}

	// URL de la feuille sans protocole ni parametres, comparee a celle de
	// wp-content : seules les feuilles servies depuis wp-content sont visees.
	$ditl_url_contenu = preg_replace( '#^https?:#i', '', trailingslashit( content_url() ) );
	$ditl_url         = preg_replace( '#^https?:#i', '', preg_replace( '/[?#].*$/', '', $ditl_feuille->src ) );

	if ( 0 !== strpos( $ditl_url, $ditl_url_contenu ) ) {
		return '';
	}

	$ditl_racine = realpath( WP_CONTENT_DIR );
	$ditl_chemin = realpath( WP_CONTENT_DIR . '/' . rawurldecode( substr( $ditl_url, strlen( $ditl_url_contenu ) ) ) );

	if ( false === $ditl_racine || false === $ditl_chemin || 0 !== strpos( wp_normalize_path( $ditl_chemin ), trailingslashit( wp_normalize_path( $ditl_racine ) ) ) ) {
		return '';
	}

	if ( '.css' !== strtolower( substr( $ditl_chemin, -4 ) ) || ! is_readable( $ditl_chemin ) ) {
		return '';
	}

	$ditl_taille = filesize( $ditl_chemin );

	if ( false === $ditl_taille || $ditl_taille <= 0 || $ditl_taille > 6 * KB_IN_BYTES ) {
		return '';
	}

	return $ditl_chemin;
}

/**
 * Ajoute un preconnect vers googletagmanager.com.
 *
 * Apres l'hebergement local des polices (Exo via Astra, Roboto et Jost via
 * le theme), gtag.js est la seule ressource tierce restante : le preconnect
 * (DNS + TCP + TLS anticipes) complete le dns-prefetch que Site Kit emet
 * deja, pour les navigateurs qui le supportent.
 *
 * @param array  $urls     URLs des indications de ressources.
 * @param string $relation Type d'indication (dns-prefetch, preconnect...).
 * @return array URLs completees.
 */
function ditl_preconnect_gtag( $urls, $relation ) {
	if ( 'preconnect' === $relation && ! is_admin() ) {
		$urls[] = 'https://www.googletagmanager.com';
	}

	return $urls;
}
add_filter( 'wp_resource_hints', 'ditl_preconnect_gtag', 10, 2 );

/**
 * Precharge l'image de banniere des gabarits DiTL (image LCP).
 *
 * Sur les pages migrees, la banniere (template-parts/gabarit-hero.php) est
 * l'element LCP : un preload dans le <head> lance son telechargement avant
 * que le parseur n'atteigne le body. Le srcset et le sizes du preload
 * reprennent exactement ceux de la balise img rendue par
 * wp_get_attachment_image (meme taille "full") : le navigateur reutilise
 * la ressource prechargee, aucun double telechargement.
 *
 * Priorite 2 : avant l'impression des feuilles de style (wp_head prio 8),
 * pour que le preload parte le plus tot possible.
 */
function ditl_preload_banniere_gabarit() {
	if ( ! function_exists( 'ditl_gabarits_templates' ) || ! is_page_template( ditl_gabarits_templates() ) ) {
		return;
	}

	// Le gabarit Reglementation n'affiche pas d'image de banniere (H1 seul) :
	// une image saisie par erreur serait prechargee sans etre affichee.
	if ( defined( 'DITL_TPL_REGLEMENTATION' ) && is_page_template( DITL_TPL_REGLEMENTATION ) ) {
		return;
	}

	$ditl_hero_id = absint( get_post_meta( get_queried_object_id(), '_ditl_hero_image_id', true ) );

	if ( $ditl_hero_id <= 0 ) {
		return;
	}

	$ditl_src = wp_get_attachment_image_url( $ditl_hero_id, 'full' );

	if ( ! $ditl_src ) {
		return;
	}

	$ditl_srcset = wp_get_attachment_image_srcset( $ditl_hero_id, 'full' );
	$ditl_sizes  = $ditl_srcset ? wp_get_attachment_image_sizes( $ditl_hero_id, 'full' ) : false;

	$ditl_preload = '<link rel="preload" as="image" href="' . esc_url( $ditl_src ) . '"';

	if ( $ditl_srcset && $ditl_sizes ) {
		$ditl_preload .= ' imagesrcset="' . esc_attr( $ditl_srcset ) . '" imagesizes="' . esc_attr( $ditl_sizes ) . '"';
	}

	echo $ditl_preload . ">\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Balise construite ci-dessus, attributs echappes individuellement.
}
add_action( 'wp_head', 'ditl_preload_banniere_gabarit', 2 );
