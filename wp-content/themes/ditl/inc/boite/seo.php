<?php
/**
 * Titre et indexation des vues de la boite a outils.
 *
 * Page Boite a outils SANS parametre du parcours : rien ne change (titre,
 * robots et canonical habituels : c'est la seule URL a indexer).
 *
 * Page Boite a outils AVEC au moins un parametre du parcours (etapes, env,
 * vue, etape, pg), meme vide :
 * - meta robots "noindex, follow" : les combinaisons de reponses et de
 *   filtres ne doivent pas encombrer l'index, les liens vers les fiches
 *   restent suivis. Le canonical emis par Yoast est deja l'URL propre de la
 *   page (Yoast ignore la chaine de requete), rien a ajouter ;
 * - <title> annoncant l'etat (recommandation Access42 : chaque rechargement
 *   doit dire ou l'on en est) :
 *     questionnaire : "Question 2 sur 4 : Tache - <titre de la page> - <site>"
 *     resultats     : "Vos outils : 3 resultats - ..." (", page 2" si pg > 1)
 *     catalogue     : "Tous les outils : 42 resultats - ..."
 *   Yoast etant actif, le titre passe par le filtre wpseo_title (Yoast court-
 *   circuite document_title_parts via pre_get_document_title) ; sans Yoast,
 *   document_title_parts prend le relais. Les deux ne s'appliquent jamais
 *   ensemble.
 *
 * Fiche detail : rien a changer. Les liens des resultats portent le
 * parcours en parametres (retour aux resultats), mais le canonical de Yoast
 * reste le permalien sans parametre (verifie le 17/10).
 *
 * Aucune requete en base hors de la page Boite a outils : le test du modele
 * de page lit les metas deja chargees de la page demandee.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indique si la requete affiche une vue du parcours (page Boite a outils
 * avec au moins un parametre du parcours).
 *
 * @return bool True pour une vue a ne pas indexer.
 */
function ditl_boite_seo_vue_parcours() {
	return ditl_boite_est_page_boite() && ditl_boite_parametres_presents();
}

/**
 * Meta robots "noindex, follow" sur les vues du parcours.
 *
 * Branche apres Yoast (PHP_INT_MAX - 10) et avant la normalisation iso-SEO
 * du theme (ditl_seo_robots_iso, PHP_INT_MAX), qui laisse passer toute
 * directive noindex. Les directives d'apercu (max-*) n'ont pas de sens sur
 * une page non indexee : retirees, comme le fait Yoast.
 *
 * @param array $robots Directives robots (cle => valeur ou true).
 * @return array Directives filtrees.
 */
function ditl_boite_seo_robots( $robots ) {
	if ( ! is_array( $robots ) || ! ditl_boite_seo_vue_parcours() ) {
		return $robots;
	}

	unset( $robots['index'], $robots['noindex'], $robots['follow'], $robots['max-snippet'], $robots['max-image-preview'], $robots['max-video-preview'] );

	$directives = array( 'noindex' => true );

	if ( empty( $robots['nofollow'] ) ) {
		$directives['follow'] = true;
	}

	return $directives + $robots;
}
add_filter( 'wp_robots', 'ditl_boite_seo_robots', PHP_INT_MAX - 5 );

/**
 * Libelle de l'etat de la vue courante, sans le titre de la page ni le nom
 * du site (reutilisable par le gabarit, par exemple pour une region
 * annoncee).
 *
 * @return string Libelle ('' hors vue du parcours).
 */
function ditl_boite_titre_etat() {
	if ( ! ditl_boite_seo_vue_parcours() ) {
		return '';
	}

	$contexte = ditl_boite_contexte();

	if ( 'boite' !== $contexte['type'] ) {
		return '';
	}

	$parcours = $contexte['parcours'];
	$textes   = $contexte['textes'];

	if ( 'questionnaire' === $parcours['vue'] ) {
		$cle = $parcours['etape_affichee'];

		if ( '' === $cle || ! isset( $textes['etapes'][ $cle ]['court'] ) ) {
			return '';
		}

		return sprintf(
			/* translators: 1: numero de la question, 2: nombre total de questions, 3: libelle court de l'etape (ex. Metier). */
			__( 'Question %1$d sur %2$d : %3$s', 'ditl' ),
			ditl_boite_numero_etape( $cle ),
			count( ditl_boite_etapes() ),
			wp_strip_all_tags( $textes['etapes'][ $cle ]['court'] )
		);
	}

	$total = (int) $contexte['total'];

	if ( 0 === $total ) {
		$nombre = __( 'aucun résultat', 'ditl' );
	} else {
		/* translators: %s : nombre de resultats. */
		$nombre = sprintf( _n( '%s résultat', '%s résultats', $total, 'ditl' ), number_format_i18n( $total ) );
	}

	$titre = 'catalogue' === $parcours['vue'] ? $textes['catalogue_titre'] : $textes['resultats_titre'];

	$etat = sprintf(
		/* translators: 1: titre de la liste (ex. Vos outils), 2: nombre de resultats (ex. 3 resultats). */
		__( '%1$s : %2$s', 'ditl' ),
		wp_strip_all_tags( $titre ),
		$nombre
	);

	if ( (int) $contexte['page'] > 1 ) {
		$etat = sprintf(
			/* translators: 1: titre et nombre de resultats, 2: numero de page. */
			__( '%1$s, page %2$s', 'ditl' ),
			$etat,
			number_format_i18n( (int) $contexte['page'] )
		);
	}

	return $etat;
}

/**
 * Separateur du titre de page.
 *
 * Avec Yoast, son separateur sous la forme qu'il insere lui-meme dans le
 * titre (entite HTML) : le titre emis reste homogene au caractere pres.
 *
 * @param bool $yoast True pour le titre construit par Yoast.
 * @return string Separateur.
 */
function ditl_boite_seo_separateur( $yoast = false ) {
	if ( $yoast && function_exists( 'YoastSEO' ) ) {
		try {
			$options = YoastSEO()->helpers->options;

			if ( is_object( $options ) && method_exists( $options, 'get_title_separator' ) ) {
				$separateur = $options->get_title_separator();

				if ( is_string( $separateur ) && '' !== trim( $separateur ) ) {
					return trim( $separateur );
				}
			}
		} catch ( Throwable $erreur ) {
			// API de Yoast modifiee : repli sur le separateur du coeur.
			unset( $erreur );
		}
	}

	$separateur = apply_filters( 'document_title_separator', '-' );

	return is_string( $separateur ) && '' !== trim( $separateur ) ? trim( $separateur ) : '-';
}

/**
 * Titre Yoast : etat de la vue prefixe au titre habituel.
 *
 * @param string $titre Titre calcule par Yoast.
 * @return string Titre complete.
 */
function ditl_boite_seo_titre_yoast( $titre ) {
	$etat = ditl_boite_titre_etat();

	if ( '' === $etat || ! is_string( $titre ) ) {
		return $titre;
	}

	return $etat . ' ' . ditl_boite_seo_separateur( true ) . ' ' . $titre;
}
add_filter( 'wpseo_title', 'ditl_boite_seo_titre_yoast' );

/**
 * Titre du coeur (sans Yoast) : etat de la vue prefixe au titre de la page.
 *
 * @param array $parties Parties du titre (title, page, tagline, site).
 * @return array Parties completees.
 */
function ditl_boite_seo_titre_coeur( $parties ) {
	$etat = ditl_boite_titre_etat();

	if ( '' === $etat || ! is_array( $parties ) ) {
		return $parties;
	}

	$parties['title'] = isset( $parties['title'] ) && '' !== $parties['title'] ? $etat . ' ' . ditl_boite_seo_separateur() . ' ' . $parties['title'] : $etat;

	return $parties;
}
add_filter( 'document_title_parts', 'ditl_boite_seo_titre_coeur' );
