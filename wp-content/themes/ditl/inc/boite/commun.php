<?php
/**
 * Boite a outils : definitions partagees (etapes du questionnaire,
 * parametres d'URL, textes de la page, contraste des couleurs).
 *
 * Contrat de la page "Boite a outils" (modele de page DITL_TPL_BOITE) :
 * - _ditl_boite_textes (string) : JSON des textes editables du questionnaire,
 *   toutes les cles sont optionnelles (vide = texte par defaut traduit, voir
 *   ditl_boite_textes_defaut()) :
 *   {
 *     titre            : titre de la colonne du questionnaire ("Boite a outils"),
 *     etapes           : { <cle d'etape> : { court, question, aide, etiquette } },
 *                        court     = libelle de l'etape dans l'etapier ("Metier"),
 *                        question  = titre de la question,
 *                        aide      = texte d'aide (HTML limite, liens permis),
 *                        etiquette = etiquette visible de la liste deroulante,
 *     bouton_solutions : libelle du bouton d'acces aux resultats,
 *     resultats_titre  : titre de la page de resultats ("Vos outils"),
 *     profil_titre     : titre du recapitulatif des reponses ("Votre profil"),
 *     filtres_titre    : titre du panneau de filtres ("Filtrer les outils"),
 *     aucun_resultat   : message affiche sans resultat (HTML limite),
 *     catalogue_titre  : titre du catalogue complet ("Tous les outils"),
 *     catalogue_intro  : introduction du catalogue (HTML limite)
 *   }
 *
 * Parametres d'URL (le parcours vit dans l'URL : partageable, retour arriere
 * naturel, aucun cookie, lisible par la mesure d'audience) :
 * - une cle par etape (metier, tache, difficulte, empechement) = code neutre
 *   du terme choisi, identique dans toutes les langues ;
 * - env[]  : codes des environnements coches dans les filtres ;
 * - vue    : "resultats" ou "catalogue" (absent = questionnaire) ;
 * - etape  : cle d'une etape deja repondue a afficher pour la modifier ;
 * - pg     : numero de page des resultats.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Valeur de _wp_page_template de la page accueillant la boite a outils.
define( 'DITL_TPL_BOITE', 'page-templates/boite-a-outils.php' );

// Noms des parametres d'URL hors etapes (voir l'en-tete).
define( 'DITL_BOITE_PARAM_VUE', 'vue' );
define( 'DITL_BOITE_PARAM_ETAPE', 'etape' );
define( 'DITL_BOITE_PARAM_ENV', 'env' );
define( 'DITL_BOITE_PARAM_PAGE', 'pg' );

/**
 * Etapes du questionnaire, dans l'ordre.
 *
 * Chaque etape lit une taxonomie a un niveau donne ; une etape de niveau 2
 * propose les enfants du terme choisi a l'etape parente.
 *
 * @return array Tableau [cle => {taxonomie, niveau, parent}].
 */
function ditl_boite_etapes() {
	return array(
		'metier'      => array(
			'taxonomie' => 'ditl_metier',
			'niveau'    => 1,
			'parent'    => '',
		),
		'tache'       => array(
			'taxonomie' => 'ditl_metier',
			'niveau'    => 2,
			'parent'    => 'metier',
		),
		'difficulte'  => array(
			'taxonomie' => 'ditl_aptitude',
			'niveau'    => 1,
			'parent'    => '',
		),
		'empechement' => array(
			'taxonomie' => 'ditl_aptitude',
			'niveau'    => 2,
			'parent'    => 'difficulte',
		),
	);
}

/**
 * Textes par defaut de la page (repris des maquettes, traduits par les
 * fichiers de langue du theme).
 *
 * @return array Textes aux cles du contrat _ditl_boite_textes.
 */
function ditl_boite_textes_defaut() {
	return array(
		'titre'            => __( 'Boîte à outils', 'ditl' ),
		'etapes'           => array(
			'metier'      => array(
				'court'     => __( 'Métier', 'ditl' ),
				'question'  => __( 'Quel est le métier concerné par la situation ?', 'ditl' ),
				'aide'      => '<p>' . __( 'Vous avez repéré une situation de travail ou de formation qui pourrait nécessiter un aménagement. Sélectionnez le métier concerné puis laissez-vous guider par les questions d\'exploration pour découvrir des adaptations possibles à mettre en œuvre.', 'ditl' ) . '</p>',
				'etiquette' => __( 'Sélectionnez votre métier', 'ditl' ),
			),
			'tache'       => array(
				'court'     => __( 'Tâche', 'ditl' ),
				'question'  => __( 'Quelle tâche est concernée dans cette situation ?', 'ditl' ),
				'aide'      => '<p>' . __( 'Le métier que vous avez choisi est composé de différentes tâches à réaliser. Dans cette étape, identifiez la tâche qui pose problème dans la situation concernée puis sélectionnez-la dans la liste proposée. Si plusieurs tâches sont concernées, il sera nécessaire de réaliser plusieurs recherches.', 'ditl' ) . '</p>',
				'etiquette' => __( 'Sélectionnez votre tâche', 'ditl' ),
			),
			'difficulte'  => array(
				'court'     => __( 'Difficulté', 'ditl' ),
				'question'  => __( 'Quelle difficulté d\'aptitude impacte la réalisation de cette tâche ?', 'ditl' ),
				'aide'      => '<p>' . __( 'Une difficulté d\'aptitude correspond à une limitation de la capacité à accomplir une tâche. Il est possible que plusieurs aptitudes viennent impacter une même tâche : il sera alors nécessaire de réaliser plusieurs recherches (une par aptitude concernée).', 'ditl' ) . '</p>',
				'etiquette' => __( 'Sélectionnez votre difficulté d\'aptitude', 'ditl' ),
			),
			'empechement' => array(
				'court'     => __( 'Empêchement', 'ditl' ),
				'question'  => __( 'Comment ou où cela se manifeste-t-il ?', 'ditl' ),
				'aide'      => '<p>' . __( 'Cette liste permet de préciser l\'aptitude concernée par la difficulté dans la réalisation de la tâche.', 'ditl' ) . '</p>',
				'etiquette' => __( 'Sélectionnez votre empêchement', 'ditl' ),
			),
		),
		'bouton_solutions' => __( 'Vos solutions', 'ditl' ),
		'resultats_titre'  => __( 'Vos outils', 'ditl' ),
		'profil_titre'     => __( 'Votre profil', 'ditl' ),
		'filtres_titre'    => __( 'Filtrer les outils', 'ditl' ),
		'aucun_resultat'   => '<p>' . __( 'Aucun outil ne correspond à cette situation pour le moment. Vous pouvez modifier une de vos réponses ou consulter tous les outils.', 'ditl' ) . '</p>',
		'catalogue_titre'  => __( 'Tous les outils', 'ditl' ),
		'catalogue_intro'  => '',
	);
}

/**
 * Balises autorisees dans les textes riches de la page (aide, messages).
 *
 * @return array Liste blanche au format wp_kses.
 */
function ditl_boite_html_autorise() {
	return array(
		'p'      => array(),
		'br'     => array(),
		'strong' => array(),
		'em'     => array(),
		'ul'     => array(),
		'ol'     => array(),
		'li'     => array(),
		'a'      => array(
			'href'     => true,
			'hreflang' => true,
			'lang'     => true,
		),
		'span'   => array(
			'lang' => true,
		),
	);
}

/**
 * Nettoie le JSON des textes de la page (contrat _ditl_boite_textes).
 *
 * Seules les cles du contrat sont conservees ; les valeurs vides sont
 * omises (le texte par defaut s'applique alors).
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (objet, eventuellement vide).
 */
function ditl_boite_sanitize_textes_json( $value ) {
	$data   = is_scalar( $value ) ? json_decode( (string) $value, true ) : null;
	$data   = is_array( $data ) ? $data : array();
	$propre = array();

	$simples = array( 'titre', 'bouton_solutions', 'resultats_titre', 'profil_titre', 'filtres_titre', 'catalogue_titre' );
	$riches  = array( 'aucun_resultat', 'catalogue_intro' );

	foreach ( $simples as $cle ) {
		$texte = isset( $data[ $cle ] ) && is_scalar( $data[ $cle ] ) ? sanitize_text_field( (string) $data[ $cle ] ) : '';

		if ( '' !== $texte ) {
			$propre[ $cle ] = $texte;
		}
	}

	foreach ( $riches as $cle ) {
		$texte = isset( $data[ $cle ] ) && is_scalar( $data[ $cle ] ) ? trim( wp_kses( (string) $data[ $cle ], ditl_boite_html_autorise() ) ) : '';

		if ( '' !== trim( wp_strip_all_tags( $texte ) ) ) {
			$propre[ $cle ] = $texte;
		}
	}

	$etapes = isset( $data['etapes'] ) && is_array( $data['etapes'] ) ? $data['etapes'] : array();

	foreach ( array_keys( ditl_boite_etapes() ) as $cle_etape ) {
		$etape = isset( $etapes[ $cle_etape ] ) && is_array( $etapes[ $cle_etape ] ) ? $etapes[ $cle_etape ] : array();
		$textes_etape = array();

		foreach ( array( 'court', 'question', 'etiquette' ) as $cle ) {
			$texte = isset( $etape[ $cle ] ) && is_scalar( $etape[ $cle ] ) ? sanitize_text_field( (string) $etape[ $cle ] ) : '';

			if ( '' !== $texte ) {
				$textes_etape[ $cle ] = $texte;
			}
		}

		$aide = isset( $etape['aide'] ) && is_scalar( $etape['aide'] ) ? trim( wp_kses( (string) $etape['aide'], ditl_boite_html_autorise() ) ) : '';

		if ( '' !== trim( wp_strip_all_tags( $aide ) ) ) {
			$textes_etape['aide'] = $aide;
		}

		if ( array() !== $textes_etape ) {
			$propre['etapes'][ $cle_etape ] = $textes_etape;
		}
	}

	return array() === $propre ? '{}' : (string) wp_json_encode( $propre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Declare la meta des textes de la page (protegee, nettoyee a l'ecriture).
 */
function ditl_boite_enregistrer_meta_page() {
	register_post_meta(
		'page',
		'_ditl_boite_textes',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '{}',
			'sanitize_callback' => 'ditl_boite_sanitize_textes_json',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', 'ditl_boite_enregistrer_meta_page' );

/**
 * Textes de la page, valeurs saisies completees par les textes par defaut.
 *
 * @param int $page_id ID de la page Boite a outils (0 = textes par defaut).
 * @return array Textes complets aux cles du contrat.
 */
function ditl_boite_textes( $page_id ) {
	$textes = ditl_boite_textes_defaut();
	$saisis = $page_id > 0 ? ditl_get_meta_json( (int) $page_id, '_ditl_boite_textes' ) : array();

	foreach ( $textes as $cle => $defaut ) {
		if ( 'etapes' === $cle ) {
			continue;
		}

		if ( isset( $saisis[ $cle ] ) && is_string( $saisis[ $cle ] ) && '' !== $saisis[ $cle ] ) {
			$textes[ $cle ] = $saisis[ $cle ];
		}
	}

	foreach ( $textes['etapes'] as $cle_etape => $defauts_etape ) {
		foreach ( $defauts_etape as $cle => $defaut ) {
			if ( isset( $saisis['etapes'][ $cle_etape ][ $cle ] ) && is_string( $saisis['etapes'][ $cle_etape ][ $cle ] ) && '' !== $saisis['etapes'][ $cle_etape ][ $cle ] ) {
				$textes['etapes'][ $cle_etape ][ $cle ] = $saisis['etapes'][ $cle_etape ][ $cle ];
			}
		}
	}

	return $textes;
}

/**
 * Luminance relative d'une couleur (WCAG 2.x).
 *
 * @param string $hex Couleur #rrggbb.
 * @return float Luminance entre 0 et 1.
 */
function ditl_boite_luminance( $hex ) {
	$hex = ltrim( (string) $hex, '#' );

	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return 1.0;
	}

	$composantes = array();

	foreach ( array( 0, 2, 4 ) as $position ) {
		$canal         = hexdec( substr( $hex, $position, 2 ) ) / 255;
		$composantes[] = $canal <= 0.03928 ? $canal / 12.92 : pow( ( $canal + 0.055 ) / 1.055, 2.4 );
	}

	return 0.2126 * $composantes[0] + 0.7152 * $composantes[1] + 0.0722 * $composantes[2];
}

/**
 * Ratio de contraste entre deux couleurs (WCAG 2.x).
 *
 * @param string $premiere Couleur #rrggbb.
 * @param string $seconde  Couleur #rrggbb.
 * @return float Ratio entre 1 et 21.
 */
function ditl_boite_ratio_contraste( $premiere, $seconde ) {
	$l1 = ditl_boite_luminance( $premiere );
	$l2 = ditl_boite_luminance( $seconde );

	return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
}

/**
 * Couleur de texte lisible sur un fond donne (badges des environnements).
 *
 * Le bleu fonce de la charte est retenu s'il atteint 4,5:1 sur le fond
 * (RGAA 3.2, texte de petite taille), sinon le blanc ou le noir, celui des
 * deux qui contraste le plus. Quelle que soit la couleur choisie par le
 * client pour un environnement, le texte du badge reste lisible (point
 * releve par Access42 sur les badges cyan et violet des maquettes).
 *
 * @param string $fond Couleur de fond #rrggbb.
 * @return string Couleur de texte #rrggbb.
 */
function ditl_boite_couleur_texte( $fond ) {
	$fond = ditl_boite_sanitize_couleur( $fond );

	if ( '' === $fond ) {
		return '#1e448f';
	}

	if ( ditl_boite_ratio_contraste( $fond, '#1e448f' ) >= 4.5 ) {
		return '#1e448f';
	}

	return ditl_boite_ratio_contraste( $fond, '#ffffff' ) >= ditl_boite_ratio_contraste( $fond, '#000000' ) ? '#ffffff' : '#000000';
}
