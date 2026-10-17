<?php
/**
 * Boite a outils : chargement front et aides de rendu des gabarits.
 *
 * - Traductions de l'interface : fichiers languages/<locale>.mo du theme
 *   (source francaise, textdomain 'ditl' partage avec tout le theme).
 * - Gabarits : page-templates/boite-a-outils.php (modele DITL_TPL_BOITE :
 *   questionnaire, resultats, catalogue) et single-ditl_ressource.php (fiche
 *   detail), morceaux communs dans template-parts/boite/.
 * - Feuilles et scripts charges UNIQUEMENT sur ces deux vues :
 *   assets/css/boite.css, assets/css/boite-impression.css (media print),
 *   assets/js/ditl-boite.js (pied de page : bouton imprimer, colonne
 *   "Informations" collante, suivi d'audience), assets/js/ditl-carousel.js
 *   sur une fiche qui propose d'autres outils.
 * - Mise en page Astra : sans barre laterale, pleine largeur (le gabarit
 *   gere ses conteneurs), sans titre, image ni navigation d'article d'Astra.
 * - Selecteur de langue : sur ces deux vues, le parcours (codes neutres,
 *   identiques dans toutes les langues) est conserve en changeant de langue.
 *
 * Suivi d'audience (Google Analytics 4) : le plan de marquage est decrit en
 * tete de assets/js/ditl-boite.js. Les gabarits fournissent les donnees en
 * attributs data-ditl-suivi* (codes neutres) ; aucun envoi sans gtag, donc
 * sans consentement (Complianz ne charge gtag.js qu'apres accord).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Traductions, feuilles et scripts, mise en page.
 * ---------------------------------------------------------------------- */

/**
 * Charge les traductions du theme (languages/<locale>.mo).
 *
 * Seules les chaines de la boite a outils sont traduites a ce jour : le
 * reste du theme garde ses libelles en dur (voir ditl_page_est_francaise).
 * Aucun fichier fr_FR : le francais est la langue source.
 */
function ditl_boite_front_traductions() {
	load_child_theme_textdomain( 'ditl', get_stylesheet_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'ditl_boite_front_traductions' );

/**
 * Vue de la boite a outils affichee par la requete en cours.
 *
 * @return string "boite" (page Boite a outils), "fiche" (fiche detail) ou ''.
 */
function ditl_boite_front_vue() {
	if ( ! did_action( 'wp' ) ) {
		return '';
	}

	if ( ditl_boite_est_page_boite() ) {
		return 'boite';
	}

	return is_singular( DITL_RESSOURCE_POST_TYPE ) ? 'fiche' : '';
}

/**
 * Charge les feuilles et scripts de la boite a outils sur ses seules vues.
 *
 * Priorite 12 : apres ditl-style (10) et la feuille d'accessibilite (11),
 * dont boite.css ajuste l'anneau de focus sur certains composants.
 */
function ditl_boite_front_enqueue() {
	$vue = ditl_boite_front_vue();

	if ( '' === $vue ) {
		return;
	}

	$base = get_stylesheet_directory_uri() . '/assets';

	wp_enqueue_style( 'ditl-boite', $base . '/css/boite.css', array( 'ditl-style' ), DITL_THEME_VERSION );
	wp_enqueue_style( 'ditl-boite-impression', $base . '/css/boite-impression.css', array( 'ditl-boite' ), DITL_THEME_VERSION, 'print' );

	// Sans dependance, en pied de page : amelioration progressive (tout
	// fonctionne sans JavaScript, formulaires GET rendus cote serveur).
	wp_enqueue_script( 'ditl-boite', $base . '/js/ditl-boite.js', array(), DITL_THEME_VERSION, true );

	if ( 'fiche' === $vue && ! post_password_required( get_queried_object_id() ) && array() !== ditl_boite_front_autres_fiches() ) {
		wp_enqueue_script( 'ditl-carousel', $base . '/js/ditl-carousel.js', array(), DITL_THEME_VERSION, true );
	}
}
add_action( 'wp_enqueue_scripts', 'ditl_boite_front_enqueue', 12 );

/**
 * Pas de barre laterale sur les vues de la boite a outils (filtre Astra,
 * meme principe que le gabarit Reglementation : aucun reglage par page).
 *
 * @param string $layout Mise en page laterale calculee par Astra.
 * @return string "no-sidebar" sur les vues de la boite a outils.
 */
function ditl_boite_front_astra_sans_barre_laterale( $layout ) {
	return '' !== ditl_boite_front_vue() ? 'no-sidebar' : $layout;
}
add_filter( 'astra_page_layout', 'ditl_boite_front_astra_sans_barre_laterale', 20 );

/**
 * Pleine largeur sans conteneur Astra ("page-builder") : les gabarits de la
 * boite a outils gerent leur largeur et leurs fonds.
 *
 * @param string $layout Mise en page du contenu calculee par Astra.
 * @return string "page-builder" sur les vues de la boite a outils.
 */
function ditl_boite_front_astra_pleine_largeur( $layout ) {
	return '' !== ditl_boite_front_vue() ? 'page-builder' : $layout;
}
add_filter( 'astra_get_content_layout', 'ditl_boite_front_astra_pleine_largeur', 20 );

/**
 * Titre, image mise en avant et navigation precedent / suivant d'Astra
 * desactives : le H1 et l'illustration sont rendus par les gabarits, et
 * l'ordre de publication des fiches n'a pas de sens pour le visiteur.
 *
 * Accroche sur astra_the_title_enabled, astra_the_post_title_enabled,
 * astra_featured_image_enabled et astra_single_post_navigation_enabled
 * (ce dernier est aussi lu par la navigation de inc/a11y.php).
 *
 * @param bool $actif Affichage calcule par Astra.
 * @return bool False sur les vues de la boite a outils.
 */
function ditl_boite_front_astra_sans_elements( $actif ) {
	return '' !== ditl_boite_front_vue() ? false : $actif;
}
add_filter( 'astra_the_title_enabled', 'ditl_boite_front_astra_sans_elements', 20 );
add_filter( 'astra_the_post_title_enabled', 'ditl_boite_front_astra_sans_elements', 20 );
add_filter( 'astra_featured_image_enabled', 'ditl_boite_front_astra_sans_elements', 20 );
add_filter( 'astra_single_post_navigation_enabled', 'ditl_boite_front_astra_sans_elements', 20 );

/**
 * Chaine de requete du parcours de la vue courante, a reporter sur les
 * traductions (reponses, environnements, vue, etape, page ; l'etape n'a pas
 * de sens sur une fiche, comme dans ditl_boite_url_fiche).
 *
 * @return string Chaine sans "?" ('' hors vue de la boite a outils ou sans
 *                parametre du parcours).
 */
function ditl_boite_front_chaine_parcours() {
	if ( '' === ditl_boite_front_vue() || ! ditl_boite_parametres_presents() ) {
		return '';
	}

	$contexte = ditl_boite_contexte();

	if ( empty( $contexte['parcours'] ) ) {
		return '';
	}

	$args = ditl_boite_args_parcours( $contexte['parcours'] );

	if ( 'fiche' === $contexte['type'] ) {
		unset( $args[ DITL_BOITE_PARAM_ETAPE ] );
	}

	return ditl_boite_chaine_requete( $args );
}

/**
 * Selecteur de langue : conserve le parcours (reponses, environnements,
 * vue, etape, page) vers la traduction de la page Boite a outils ou de la
 * fiche. Les codes etant identiques dans toutes les langues, le moteur de la
 * langue cible ecarte seul ce qui n'y existe pas.
 *
 * Polylang : filtre pll_translation_url (URL de la traduction, null si la
 * page n'est pas traduite : rien n'est ajoute a l'accueil de repli).
 * Equivalent WPML a brancher en phase 2 (filtre icl_ls_languages).
 *
 * @param string|null $url  URL de la traduction.
 * @param string      $lang Code de la langue cible.
 * @return string|null URL completee.
 */
function ditl_boite_front_url_traduction( $url, $lang ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return $url;
	}

	$chaine = ditl_boite_front_chaine_parcours();

	if ( '' === $chaine ) {
		return $url;
	}

	$traductions = ditl_ml_traductions_post( get_queried_object_id() );

	if ( ! isset( $traductions[ $lang ] ) ) {
		return $url;
	}

	return ditl_boite_ajouter_requete( $url, $chaine );
}
add_filter( 'pll_translation_url', 'ditl_boite_front_url_traduction', 10, 2 );

/**
 * Liens hreflang du <head> : Polylang les construit avec les memes URL de
 * traduction que le selecteur ; le parcours en est retire pour qu'ils
 * restent les URL propres (coherentes avec le canonical de Yoast, sans
 * parametre), exactement comme avant l'ajout du parcours au selecteur.
 *
 * @param array $hreflangs URL par code de langue.
 * @return array URL sans le parcours.
 */
function ditl_boite_front_hreflang_sans_parcours( $hreflangs ) {
	$chaine = is_array( $hreflangs ) ? ditl_boite_front_chaine_parcours() : '';

	if ( '' === $chaine ) {
		return $hreflangs;
	}

	foreach ( $hreflangs as $lang => $url ) {
		if ( ! is_string( $url ) ) {
			continue;
		}

		foreach ( array( '?', '&' ) as $separateur ) {
			$suffixe = $separateur . $chaine;

			if ( strlen( $url ) > strlen( $suffixe ) && substr( $url, -strlen( $suffixe ) ) === $suffixe ) {
				$hreflangs[ $lang ] = substr( $url, 0, -strlen( $suffixe ) );
				break;
			}
		}
	}

	return $hreflangs;
}
add_filter( 'pll_rel_hreflang_attributes', 'ditl_boite_front_hreflang_sans_parcours' );

/* -------------------------------------------------------------------------
 * Aides de rendu partagees par les gabarits.
 * ---------------------------------------------------------------------- */

/**
 * Icone SVG decorative (masquee aux technologies d'assistance).
 *
 * @param string $nom Nom de l'icone.
 * @return string Balisage SVG statique ('' si icone inconnue).
 */
function ditl_boite_front_icone( $nom ) {
	$traces = array(
		'retour'     => '<path d="M20 12H5.5M11.5 5.5 5 12l6.5 6.5"/>',
		'crayon'     => '<path d="M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4"/>',
		'coche'      => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
		'haut'       => '<path d="M12 19.5V5M5.5 11.5 12 5l6.5 6.5"/>',
		'bas'        => '<path d="M12 4.5V19M5.5 12.5 12 19l6.5-6.5"/>',
		'gauche'     => '<path d="M20 12H5.5M12.5 4.5 5 12l7.5 7.5"/>',
		'droite'     => '<path d="M4 12h14.5M12 4.5l7.5 7.5-7.5 7.5"/>',
		'actualiser' => '<path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3M19.5 4.5v4.5H15"/>',
		'externe'    => '<path d="M7 17 17 7M9 7h8v8"/>',
		'imprimer'   => '<path d="M7 9V4h10v5M7 17H5a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-2M7 14h10v6H7z"/>',
		'plus'       => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
		'info'       => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
		'chevron'    => '<path d="M6 9.5l6 6 6-6"/>',
		'loupe'      => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 20 20"/>',
		'outil'      => '<path d="M14.5 4.5a4 4 0 0 0-4.9 5.2L4 15.3V20h4.7l5.6-5.6a4 4 0 0 0 5.2-4.9l-2.6 2.6-2.8-.6-.6-2.8z"/>',
		'ampoule'    => '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9V15h7v-1.1A6 6 0 0 0 12 3z"/>',
		'engrenage'  => '<circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/>',
		'lieu'       => '<path d="M12 21s6.5-6.1 6.5-11A6.5 6.5 0 0 0 5.5 10c0 4.9 6.5 11 6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
		'usine'      => '<path d="M3.5 20.5V10l5 3V10l5 3V6.5h4l1 14H3.5z"/>',
		'document'   => '<path d="M6.5 3.5h7l4 4v13h-11zM13.5 3.5v4h4M9 12h6M9 15.5h6"/>',
		'lien'       => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
	);

	if ( ! isset( $traces[ $nom ] ) ) {
		return '';
	}

	return '<svg class="ditl-boite-icone ditl-boite-icone--' . $nom . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $traces[ $nom ] . '</svg>';
}

/**
 * Libelle court d'une etape (etapier, recapitulatif, intitules).
 *
 * @param array  $textes Textes de la page (ditl_boite_textes).
 * @param string $cle    Cle de l'etape.
 * @return string Libelle (texte simple).
 */
function ditl_boite_front_libelle_etape( array $textes, $cle ) {
	return isset( $textes['etapes'][ $cle ]['court'] ) ? wp_strip_all_tags( (string) $textes['etapes'][ $cle ]['court'] ) : '';
}

/**
 * Etape voisine (precedente ou suivante), etapes sautees exclues.
 *
 * @param array  $parcours Parcours.
 * @param string $cle      Etape de depart.
 * @param int    $sens     -1 (precedente) ou 1 (suivante).
 * @return string Cle de l'etape voisine ('' si aucune).
 */
function ditl_boite_front_etape_voisine( array $parcours, $cle, $sens ) {
	$cles    = array_keys( ditl_boite_etapes() );
	$rang    = array_search( $cle, $cles, true );
	$sautees = isset( $parcours['sautees'] ) ? (array) $parcours['sautees'] : array();

	if ( false === $rang ) {
		return '';
	}

	for ( $i = $rang + ( $sens < 0 ? -1 : 1 ); $i >= 0 && $i < count( $cles ); $i += ( $sens < 0 ? -1 : 1 ) ) {
		if ( ! in_array( $cles[ $i ], $sautees, true ) ) {
			return $cles[ $i ];
		}
	}

	return '';
}

/**
 * URL d'affichage d'une etape du questionnaire (modification d'une reponse).
 *
 * Reponses deja donnees conservees (les suivantes seront ecartees par le
 * moteur si elles ne sont plus coherentes une fois la reponse changee) ;
 * l'etape courante s'affiche sans parametre etape.
 *
 * @param array  $parcours Parcours.
 * @param string $cle      Cle de l'etape.
 * @return string URL (non echappee).
 */
function ditl_boite_front_url_etape( array $parcours, $cle ) {
	$etape = isset( $parcours['etape_courante'] ) && $cle === $parcours['etape_courante'] ? '' : $cle;

	return ditl_boite_url(
		ditl_boite_args_parcours(
			$parcours,
			array(
				DITL_BOITE_PARAM_ETAPE => $etape,
				DITL_BOITE_PARAM_VUE   => '',
				DITL_BOITE_PARAM_PAGE  => '',
			)
		)
	);
}

/**
 * Codes du parcours pour le suivi d'audience (codes neutres).
 *
 * @param array $parcours Parcours.
 * @return array Tableau [metier, tache, difficulte, empechement,
 *               environnements] (chaines, '' si absent).
 */
function ditl_boite_front_suivi_parcours( array $parcours ) {
	$donnees = array();

	foreach ( array_keys( ditl_boite_etapes() ) as $cle ) {
		$donnees[ $cle ] = isset( $parcours['reponses'][ $cle ] ) && $parcours['reponses'][ $cle ] instanceof WP_Term ? ditl_boite_code_terme( $parcours['reponses'][ $cle ] ) : '';
	}

	$donnees['environnements'] = isset( $parcours['environnements'] ) && is_array( $parcours['environnements'] ) ? implode( ',', array_map( 'strval', array_keys( $parcours['environnements'] ) ) ) : '';

	return $donnees;
}

/**
 * Encode des donnees de suivi pour un attribut data-* (valeurs scalaires,
 * tronquees a 100 caracteres comme l'exige Google Analytics 4).
 *
 * @param array $donnees Donnees [parametre => valeur].
 * @return string JSON (a echapper avec esc_attr).
 */
function ditl_boite_front_suivi_json( array $donnees ) {
	$propres = array();

	foreach ( $donnees as $cle => $valeur ) {
		if ( is_int( $valeur ) ) {
			$propres[ $cle ] = $valeur;
		} elseif ( is_scalar( $valeur ) ) {
			$propres[ $cle ] = function_exists( 'mb_substr' ) ? mb_substr( (string) $valeur, 0, 100 ) : substr( (string) $valeur, 0, 100 );
		}
	}

	return (string) wp_json_encode( $propres, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Identifiant neutre d'une fiche pour le suivi d'audience, identique dans
 * toutes les langues : groupe de l'import CSV (colonne group_id, phase 4)
 * s'il existe, sinon "p" suivi de l'ID de la plus ancienne traduction.
 *
 * @param int $post_id ID de la fiche.
 * @return string Identifiant.
 */
function ditl_boite_front_id_fiche( $post_id ) {
	$post_id = absint( $post_id );
	$groupe  = get_post_meta( $post_id, '_ditl_import_groupe', true );

	if ( is_scalar( $groupe ) && '' !== (string) $groupe ) {
		return substr( sanitize_key( (string) $groupe ), 0, 40 );
	}

	$traductions = array_filter( array_map( 'absint', ditl_ml_traductions_post( $post_id ) ) );

	return 'p' . ( array() !== $traductions ? min( $traductions ) : $post_id );
}

/**
 * Termes d'une fiche dans une taxonomie, dans l'ordre des listes.
 *
 * @param int    $post_id   ID de la fiche.
 * @param string $taxonomie Taxonomie de la boite a outils.
 * @return WP_Term[] Termes tries (rang puis libelle).
 */
function ditl_boite_front_termes_fiche( $post_id, $taxonomie ) {
	$termes = get_the_terms( absint( $post_id ), $taxonomie );

	if ( ! is_array( $termes ) || array() === $termes ) {
		return array();
	}

	return ditl_boite_trier_termes( array_slice( $termes, 0, 100 ) );
}

/**
 * Attribut style d'un badge d'environnement : couleur de fond du terme et
 * couleur de texte lisible calculee (ditl_boite_couleur_texte).
 *
 * @param WP_Term $terme Environnement.
 * @return string Declarations CSS ('' sans couleur : couleurs par defaut).
 */
function ditl_boite_front_style_badge( WP_Term $terme ) {
	$fond = ditl_boite_sanitize_couleur( get_term_meta( $terme->term_id, '_ditl_couleur', true ) );

	if ( '' === $fond ) {
		return '';
	}

	return '--ditl-boite-badge-fond:' . $fond . ';--ditl-boite-badge-texte:' . ditl_boite_couleur_texte( $fond ) . ';';
}

/**
 * Affiche les badges d'environnement d'une fiche (liste).
 *
 * @param int    $post_id ID de la fiche.
 * @param string $classe  Classe complementaire de la liste.
 */
function ditl_boite_front_badges_environnements( $post_id, $classe = '' ) {
	$environnements = ditl_boite_front_termes_fiche( $post_id, 'ditl_environnement' );

	if ( array() === $environnements ) {
		return;
	}
	?>
	<ul class="ditl-boite-badges<?php echo '' !== $classe ? ' ' . esc_attr( $classe ) : ''; ?>" aria-label="<?php echo esc_attr( _n( 'Environnement', 'Environnements', count( $environnements ), 'ditl' ) ); ?>">
		<?php foreach ( $environnements as $terme ) { ?>
			<?php $style = ditl_boite_front_style_badge( $terme ); ?>
		<li class="ditl-boite-badge"<?php echo '' !== $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>><?php echo esc_html( $terme->name ); ?></li>
		<?php } ?>
	</ul>
	<?php
}

/**
 * URL lisible pour un affichage en clair (sans protocole, sans "www." ni
 * barre finale).
 *
 * @param string $url URL.
 * @return string URL simplifiee.
 */
function ditl_boite_front_url_lisible( $url ) {
	// Decodage des %xx pour la lecture, sauf s'il produit de l'UTF-8 invalide
	// (adresses encodees en Latin-1, ex. %E9 : l'echappement viderait le texte).
	$decode  = rawurldecode( (string) $url );
	$decode  = seems_utf8( $decode ) ? $decode : (string) $url;
	$lisible = preg_replace( '#^https?://(www\.)?#i', '', $decode );
	$lisible = is_string( $lisible ) ? untrailingslashit( $lisible ) : (string) $url;

	return '' !== $lisible ? $lisible : (string) $url;
}

/**
 * Autres fiches proposees sur la fiche affichee (memorise pour la requete :
 * appele au chargement des scripts puis par le gabarit).
 *
 * @return int[] IDs des fiches (12 au plus).
 */
function ditl_boite_front_autres_fiches() {
	static $memo = null;

	if ( null !== $memo ) {
		return $memo;
	}

	$contexte = ditl_boite_contexte();

	if ( 'fiche' !== $contexte['type'] ) {
		return array();
	}

	$memo = ditl_boite_autres_fiches( get_queried_object_id(), $contexte['parcours'], 12 );

	// Chargement groupe des fiches, de leurs metas et termes puis de leurs
	// vignettes : quelques requetes au lieu de 6 a 7 par carte.
	if ( array() !== $memo ) {
		_prime_post_caches( $memo, true, true );

		$vignettes = array();

		foreach ( $memo as $fiche_id ) {
			$vignette = (int) get_post_thumbnail_id( $fiche_id );

			if ( $vignette > 0 ) {
				$vignettes[] = $vignette;
			}
		}

		if ( array() !== $vignettes ) {
			_prime_post_caches( $vignettes, false, true );
		}
	}

	return $memo;
}

/**
 * Environnements proposes dans le panneau de filtres de la vue courante.
 *
 * Avec DITL_BOITE_MASQUER_CHOIX_VIDES (PROVISOIRE), un environnement sans
 * fiche parmi les fiches correspondantes n'est pas propose, sauf s'il est
 * deja coche (le visiteur doit pouvoir le decocher).
 *
 * @param array $contexte Contexte (ditl_boite_contexte()).
 * @return array Tableau [term_id => {terme, nombre, coche}] dans l'ordre des
 *               listes.
 */
function ditl_boite_front_environnements_proposes( array $contexte ) {
	$coches = array();

	foreach ( isset( $contexte['parcours']['environnements'] ) ? (array) $contexte['parcours']['environnements'] : array() as $terme ) {
		if ( $terme instanceof WP_Term ) {
			$coches[ (int) $terme->term_id ] = true;
		}
	}

	$proposes = array();

	foreach ( isset( $contexte['environnements'] ) ? (array) $contexte['environnements'] : array() as $term_id => $env ) {
		$coche = isset( $coches[ (int) $term_id ] );

		if ( DITL_BOITE_MASQUER_CHOIX_VIDES && 0 === (int) $env['nombre'] && ! $coche ) {
			continue;
		}

		$env['coche']           = $coche;
		$proposes[ $term_id ] = $env;
	}

	return $proposes;
}

/**
 * Numeros de page a afficher dans la pagination : premiere, derniere, page
 * courante et ses deux voisines de chaque cote ; 0 marque une ellipse.
 *
 * @param int $courante Page courante.
 * @param int $total    Nombre de pages.
 * @return int[] Numeros (0 = ellipse).
 */
function ditl_boite_front_pages( $courante, $total ) {
	$courante = max( 1, (int) $courante );
	$total    = max( 1, (int) $total );
	$pages    = array();
	$derniere = 0;

	for ( $page = 1; $page <= $total; $page++ ) {
		if ( 1 === $page || $total === $page || abs( $page - $courante ) <= 2 ) {
			// Une seule page sautee : elle est affichee plutot qu'une ellipse.
			if ( $derniere > 0 && 2 === $page - $derniere ) {
				$pages[] = $page - 1;
			} elseif ( $derniere > 0 && $page - $derniere > 2 ) {
				$pages[] = 0;
			}

			$pages[]  = $page;
			$derniere = $page;
		}
	}

	return $pages;
}

/**
 * Vide le cache de page des pages Boite a outils (toutes langues) quand une
 * fiche quitte ou retrouve la publication par la corbeille, ou quand un
 * referentiel change.
 *
 * Seule la page sans parametre est mise en cache par WP Fastest Cache (les
 * vues du parcours portent des parametres d'URL) : elle affiche les choix de
 * la premiere question, calcules d'apres les fiches publiees. L'extension la
 * purge deja a la publication, a la mise a jour et a la depublication d'une
 * fiche, mais pas a la mise a la corbeille ni a la modification d'un terme
 * (constate le 17/10/2026). Passe par l'action publique de l'extension, sans
 * toucher a sa configuration ; sans effet si elle est absente.
 */
function ditl_boite_front_purger_cache() {
	static $programmee = false;

	// Une seule purge par requete, en fin de traitement (un import touche des
	// centaines de termes et de fiches en une fois).
	if ( $programmee || ! has_action( 'wpfc_clear_post_cache_by_id' ) ) {
		return;
	}

	$programmee = true;
	add_action( 'shutdown', 'ditl_boite_front_executer_purge' );
}

/**
 * Execute la purge programmee par ditl_boite_front_purger_cache().
 */
function ditl_boite_front_executer_purge() {
	foreach ( ditl_ml_langues() as $lang ) {
		$page_id = ditl_boite_page_id( $lang );

		if ( $page_id > 0 ) {
			do_action( 'wpfc_clear_post_cache_by_id', false, $page_id );
		}
	}
}

/**
 * Purge a la mise a la corbeille, a la restauration ou a la suppression
 * d'une fiche.
 *
 * @param int $post_id ID de la publication.
 */
function ditl_boite_front_purger_cache_fiche( $post_id ) {
	if ( DITL_RESSOURCE_POST_TYPE === get_post_type( $post_id ) ) {
		ditl_boite_front_purger_cache();
	}
}
add_action( 'trashed_post', 'ditl_boite_front_purger_cache_fiche' );
add_action( 'untrashed_post', 'ditl_boite_front_purger_cache_fiche' );
add_action( 'deleted_post', 'ditl_boite_front_purger_cache_fiche' );

/**
 * Purge a la creation, la modification ou la suppression d'un terme des
 * referentiels.
 *
 * @param int    $term_id   ID du terme.
 * @param int    $tt_id     ID term_taxonomy.
 * @param string $taxonomie Taxonomie.
 */
function ditl_boite_front_purger_cache_terme( $term_id, $tt_id, $taxonomie ) {
	if ( ditl_boite_est_taxonomie( $taxonomie ) ) {
		ditl_boite_front_purger_cache();
	}
}
add_action( 'created_term', 'ditl_boite_front_purger_cache_terme', 10, 3 );
add_action( 'edited_term', 'ditl_boite_front_purger_cache_terme', 10, 3 );
add_action( 'delete_term', 'ditl_boite_front_purger_cache_terme', 10, 3 );

/**
 * Adresse externe affichable (fabricants, sources) : http ou https avec un
 * hote. Les adresses relatives ou sans protocole ("//hote/x"), acceptees a
 * l'enregistrement par esc_url_raw, ne sont pas rendues en lien.
 *
 * @param string $url Adresse stockee.
 * @return string Adresse echappee pour un attribut href, ou chaine vide.
 */
function ditl_boite_front_url_externe( $url ) {
	$url    = trim( (string) $url );
	$schema = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
	$hote   = (string) wp_parse_url( $url, PHP_URL_HOST );

	if ( ! in_array( $schema, array( 'http', 'https' ), true ) || '' === $hote ) {
		return '';
	}

	return esc_url( $url, array( 'http', 'https' ) );
}
