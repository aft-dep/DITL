<?php
/**
 * Reglementation (phase 6) : type de contenu des pays, lecture pour le
 * gabarit, reglages Astra et assets du gabarit, ecran de liste des pays,
 * purge du cache de page.
 *
 * Modele (decisions D1, D2, D5 du lot) :
 * - une publication du type "ditl_reglementation" = un PAYS dans une LANGUE,
 *   les traductions d'un pays etant liees par l'extension multilingue
 *   (declaration dans wpml-config.xml, lue par Polylang et par WPML) ;
 * - AUCUNE URL par pays (type non public : ni page individuelle, ni archive,
 *   ni recherche, ni sitemap) : une PAGE par langue porte le gabarit
 *   "Reglementation" (constante DITL_TPL_REGLEMENTATION) et affiche les pays
 *   publies de sa langue en onglets, dans l'ordre de l'attribut "Ordre" puis
 *   du titre ; lien profond par ancre (#pays-france, #pays-france-q2).
 *
 * Les metas et les metaboxes sont declarees dans
 * inc/metaboxes/reglementation.php (charge avant ce fichier).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enregistre le type de contenu des pays de la Reglementation.
 *
 * Editeur classique (show_in_rest false, pas de Gutenberg) : le contenu
 * d'un pays est porte par ses metas, saisies dans la metabox "Contenu du
 * pays". Capacites alignees sur les pages (capability_type page) : qui peut
 * modifier les pages peut modifier les pays.
 */
function ditl_reglementation_register_post_type() {
	register_post_type(
		DITL_REGL_POST_TYPE,
		array(
			'labels'              => array(
				'name'                  => __( 'Pays', 'ditl' ),
				'singular_name'         => __( 'Pays', 'ditl' ),
				'menu_name'             => __( 'Reglementation', 'ditl' ),
				'name_admin_bar'        => __( 'Pays (reglementation)', 'ditl' ),
				'all_items'             => __( 'Tous les pays', 'ditl' ),
				'add_new'               => __( 'Ajouter un pays', 'ditl' ),
				'add_new_item'          => __( 'Ajouter un pays', 'ditl' ),
				'edit_item'             => __( 'Modifier le pays', 'ditl' ),
				'new_item'              => __( 'Nouveau pays', 'ditl' ),
				'search_items'          => __( 'Rechercher un pays', 'ditl' ),
				'not_found'             => __( 'Aucun pays trouve.', 'ditl' ),
				'not_found_in_trash'    => __( 'Aucun pays dans la corbeille.', 'ditl' ),
				'attributes'            => __( 'Attributs', 'ditl' ),
				'filter_items_list'     => __( 'Filtrer la liste des pays', 'ditl' ),
				'items_list_navigation' => __( 'Navigation de la liste des pays', 'ditl' ),
				'items_list'            => __( 'Liste des pays', 'ditl' ),
				'item_published'        => __( 'Pays publie.', 'ditl' ),
				'item_updated'          => __( 'Pays mis a jour.', 'ditl' ),
			),
			'description'         => __( 'Pays presentes en onglets sur la page au gabarit "Reglementation".', 'ditl' ),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-flag',
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'show_in_rest'        => false,
			'hierarchical'        => false,
			'capability_type'     => 'page',
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'page-attributes' ),
			'delete_with_user'    => false,
		)
	);
}
add_action( 'init', 'ditl_reglementation_register_post_type' );

/* -------------------------------------------------------------------------
 * Lecture pour le gabarit.
 * ---------------------------------------------------------------------- */

/**
 * Filtre les sections affichables d'un pays (regle D5).
 *
 * Une section est affichable si elle a une question ET (un contenu ou une
 * image). Une image dont l'attachement a disparu de la mediatheque compte
 * comme absente.
 *
 * @param mixed $sections Sections decodees de la meta _ditl_regl_sections.
 * @return array Liste ordonnee de sections {titre, image_id, contenu}.
 */
function ditl_reglementation_sections_affichables( $sections ) {
	$affichables = array();

	foreach ( (array) $sections as $section ) {
		if ( ! is_array( $section ) ) {
			continue;
		}

		$titre    = isset( $section['titre'] ) && is_scalar( $section['titre'] ) ? trim( (string) $section['titre'] ) : '';
		$image_id = isset( $section['image_id'] ) ? ditl_reglementation_id_image( $section['image_id'] ) : 0;
		$contenu  = isset( $section['contenu'] ) && is_scalar( $section['contenu'] ) ? (string) $section['contenu'] : '';

		if ( '' === $titre || ( 0 === $image_id && ditl_reglementation_contenu_vide( $contenu ) ) ) {
			continue;
		}

		$affichables[] = array(
			'titre'    => $titre,
			'image_id' => $image_id,
			'contenu'  => $contenu,
		);
	}

	return $affichables;
}

/**
 * Retourne les pays a afficher par le gabarit, dans la langue courante.
 *
 * Requete avec filtres actifs : l'extension multilingue (Polylang
 * aujourd'hui, WPML apres la phase 2) la limite a la langue de la page.
 * Seuls les pays publies sont lus (un brouillon n'apparait pas), dans
 * l'ordre de l'attribut "Ordre" puis du titre, 100 au plus.
 *
 * Regles d'affichage (D5) appliquees ici : un pays n'est retenu que s'il a
 * un titre et au moins une section affichable (voir
 * ditl_reglementation_sections_affichables) ; un drapeau introuvable vaut 0.
 *
 * Ancres : pays = "pays-" + titre normalise (sanitize_title), rendue unique
 * dans la page par un suffixe -2, -3... ; section = ancre du pays + "-q" +
 * rang affiche (1, 2...).
 *
 * Le contenu des sections est retourne BRUT (HTML filtre a l'enregistrement
 * par wp_kses_post) : la mise en forme et l'echappement relevent du template.
 *
 * @return array Liste ordonnee de pays : {id, titre, ancre, drapeau_id,
 *               sections: [{titre, image_id, contenu, ancre}]}.
 */
function ditl_reglementation_pays_affichables() {
	$requete = new WP_Query(
		array(
			'post_type'        => DITL_REGL_POST_TYPE,
			'post_status'      => 'publish',
			'posts_per_page'   => 100,
			'orderby'          => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);

	// Lecture des metas (cache deja amorce par la requete), puis amorcage en
	// une requete des attachements references (drapeaux, images) : leur
	// validation et leur rendu ne coutent plus de requete par image.
	$lus        = array();
	$ids_images = array();

	foreach ( $requete->posts as $pays ) {
		$sections = ditl_get_meta_json( $pays->ID, '_ditl_regl_sections' );
		$drapeau  = get_post_meta( $pays->ID, '_ditl_regl_drapeau_id', true );

		$ids_images[] = absint( $drapeau );

		foreach ( $sections as $section ) {
			if ( is_array( $section ) && isset( $section['image_id'] ) && is_scalar( $section['image_id'] ) ) {
				$ids_images[] = absint( $section['image_id'] );
			}
		}

		$lus[] = array(
			'post'     => $pays,
			'sections' => $sections,
			'drapeau'  => $drapeau,
		);
	}

	$ids_images = array_values( array_filter( array_unique( $ids_images ) ) );

	if ( array() !== $ids_images && function_exists( '_prime_post_caches' ) ) {
		_prime_post_caches( $ids_images, false, true );
	}

	$resultat = array();
	$ancres   = array();

	foreach ( $lus as $lu ) {
		$titre       = trim( (string) $lu['post']->post_title );
		$affichables = ditl_reglementation_sections_affichables( $lu['sections'] );

		if ( '' === $titre || array() === $affichables ) {
			continue;
		}

		// Ancre du pays, unique dans la page (titre sans caractere
		// exploitable : repli sur l'ID de la publication).
		$base = sanitize_title( $titre );

		if ( '' === $base ) {
			$base = (string) $lu['post']->ID;
		}

		$ancre   = 'pays-' . $base;
		$suffixe = 2;

		while ( isset( $ancres[ $ancre ] ) ) {
			$ancre = 'pays-' . $base . '-' . $suffixe;
			$suffixe++;
		}

		$ancres[ $ancre ] = true;

		$sections = array();

		foreach ( $affichables as $rang => $section ) {
			// Unique elle aussi : un pays intitule "France Q1" range avant
			// "France" produirait sinon deux fois pays-france-q1.
			$base_section  = $ancre . '-q' . ( $rang + 1 );
			$ancre_section = $base_section;
			$suffixe       = 2;

			while ( isset( $ancres[ $ancre_section ] ) ) {
				$ancre_section = $base_section . '-' . $suffixe;
				$suffixe++;
			}

			$section['ancre'] = $ancre_section;

			$ancres[ $ancre_section ] = true;
			$sections[]               = $section;
		}

		$resultat[] = array(
			'id'         => (int) $lu['post']->ID,
			'titre'      => $titre,
			'ancre'      => $ancre,
			'drapeau_id' => ditl_reglementation_id_image( $lu['drapeau'] ),
			'sections'   => $sections,
		);
	}

	return $resultat;
}

/**
 * Pose les espaces insecables de la typographie francaise dans un titre en
 * texte simple (H1 de la page, questions).
 *
 * Remplace l'espace ordinaire place avant : ; ! ? et le guillemet fermant,
 * ou apres le guillemet ouvrant, par une espace insecable : aucun signe isole
 * ne commence une ligne. Sans effet sur un texte sans espace devant ces
 * signes (anglais). Chaine de remplacement fixe (aucune reference arriere).
 *
 * @param mixed $texte Texte simple (non echappe).
 * @return string Texte avec espaces insecables, a echapper au rendu.
 */
function ditl_reglementation_insecables( $texte ) {
	$texte    = is_scalar( $texte ) ? (string) $texte : '';
	$resultat = preg_replace( '/ (?=[:;!?\x{00BB}])|(?<=\x{00AB}) /u', "\xC2\xA0", $texte );

	// Echec PCRE (UTF-8 invalide) : texte conserve tel quel.
	return null === $resultat ? $texte : $resultat;
}

/**
 * Balise <img> d'une image de la mediatheque (drapeau, illustration), avec
 * alt vide force : ces images sont decoratives, le texte adjacent porte
 * l'information.
 *
 * Les SVG n'ont pas de dimensions en metadonnees : wp_get_attachment_image
 * leur poserait width="1" height="1" (voir ditl_metabox_media_preview). Ils
 * recoivent un <img> simple, dimensionne par $dimensions_svg ou par la feuille
 * de style ; les attributs propres aux images matricielles (srcset, sizes),
 * les gestionnaires d'evenement (on...) et les valeurs false (attribut omis)
 * n'y sont pas reportes. Les autres images passent par
 * wp_get_attachment_image (srcset, sizes, WebP du site).
 *
 * @param int          $attachment_id  ID d'attachement image (deja valide).
 * @param string|int[] $taille         Taille WordPress des images matricielles.
 * @param array        $attributs      Attributs de la balise (class, sizes,
 *                                     loading, decoding...).
 * @param int[]        $dimensions_svg Largeur et hauteur posees sur un SVG
 *                                     (vide : aucune).
 * @return string HTML echappe ; chaine vide si l'image est introuvable.
 */
function ditl_reglementation_image( $attachment_id, $taille, $attributs = array(), $dimensions_svg = array() ) {
	$attachment_id = (int) $attachment_id;

	if ( $attachment_id <= 0 ) {
		return '';
	}

	$attributs        = (array) $attributs;
	$attributs['alt'] = '';

	if ( 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
		return (string) wp_get_attachment_image( $attachment_id, $taille, false, $attributs );
	}

	$url = (string) wp_get_attachment_url( $attachment_id );

	if ( '' === $url ) {
		return '';
	}

	$html = '<img src="' . esc_url( $url ) . '"';

	if ( 2 === count( $dimensions_svg ) ) {
		$html .= ' width="' . absint( reset( $dimensions_svg ) ) . '" height="' . absint( end( $dimensions_svg ) ) . '"';
	}

	foreach ( $attributs as $nom => $valeur ) {
		// Noms d'attributs simples uniquement, sans gestionnaire d'evenement.
		if ( false === $valeur || null === $valeur || in_array( $nom, array( 'src', 'srcset', 'sizes', 'width', 'height' ), true ) || ! preg_match( '/^[a-z][a-z0-9-]*$/', (string) $nom ) || 0 === strpos( (string) $nom, 'on' ) ) {
			continue;
		}

		$html .= ' ' . $nom . '="' . esc_attr( (string) $valeur ) . '"';
	}

	return $html . ' />';
}

/* -------------------------------------------------------------------------
 * Gabarit : assets et reglages Astra.
 * ---------------------------------------------------------------------- */

/**
 * Charge les assets publics du gabarit "Reglementation".
 *
 * La feuille commune des gabarits (ditl-gabarits-communs) est chargee par
 * functions.php pour tout le registre ditl_gabarits_templates(), dont ce
 * gabarit fait partie. Le script (onglets et zones depliables) est seulement
 * DECLARE ici : le gabarit le met en file d'attente s'il a au moins un pays a
 * afficher (sans pays, la page se limite au H1 et n'a rien a animer). Les
 * pays ne sont ainsi lus qu'une fois par affichage ; le script, place en pied
 * de page, est imprime par wp_footer.
 */
function ditl_reglementation_enqueue_assets() {
	if ( ! is_page_template( DITL_TPL_REGLEMENTATION ) ) {
		return;
	}

	wp_enqueue_style(
		'ditl-gabarit-reglementation',
		get_stylesheet_directory_uri() . '/assets/css/gabarit-reglementation.css',
		array( 'ditl-gabarits-communs' ),
		DITL_THEME_VERSION
	);

	wp_register_script(
		'ditl-reglementation',
		get_stylesheet_directory_uri() . '/assets/js/ditl-reglementation.js',
		array(),
		DITL_THEME_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'ditl_reglementation_enqueue_assets' );

/**
 * Pas de barre laterale sur le gabarit "Reglementation".
 *
 * Les pages des autres gabarits portent les metas de mise en page d'Astra
 * heritees d'Elementor (site-sidebar-layout = no-sidebar,
 * ast-site-content-layout = full-width-container, site-post-title et
 * ast-featured-img = disabled). Une page Reglementation creee dans une
 * nouvelle langue n'en a aucune : ces filtres d'Astra (4.12, ceux qu'utilise
 * sa propre compatibilite Elementor) imposent le meme resultat sans reglage
 * manuel. La meta ast-title-bar-display ne concerne qu'Astra Pro (absent).
 *
 * @param string $layout Mise en page laterale calculee par Astra.
 * @return string "no-sidebar" sur le gabarit, inchangee ailleurs.
 */
function ditl_reglementation_astra_sans_barre_laterale( $layout ) {
	return is_page_template( DITL_TPL_REGLEMENTATION ) ? 'no-sidebar' : $layout;
}
add_filter( 'astra_page_layout', 'ditl_reglementation_astra_sans_barre_laterale', 20 );

/**
 * Mise en page pleine largeur sans conteneur (equivalent de la meta
 * ast-site-content-layout = full-width-container, rendue "page-builder"
 * par Astra) sur le gabarit "Reglementation".
 *
 * @param string $layout Mise en page du contenu calculee par Astra.
 * @return string "page-builder" sur le gabarit, inchangee ailleurs.
 */
function ditl_reglementation_astra_pleine_largeur( $layout ) {
	return is_page_template( DITL_TPL_REGLEMENTATION ) ? 'page-builder' : $layout;
}
add_filter( 'astra_get_content_layout', 'ditl_reglementation_astra_pleine_largeur', 20 );

/**
 * Masque le titre et l'image mise en avant d'Astra sur le gabarit
 * "Reglementation" (equivalent des metas site-post-title et
 * ast-featured-img = disabled) : le H1 est rendu par le gabarit.
 *
 * Accroche sur astra_the_title_enabled, astra_the_post_title_enabled et
 * astra_featured_image_enabled.
 *
 * @param bool $actif Affichage calcule par Astra.
 * @return bool False sur le gabarit, inchange ailleurs.
 */
function ditl_reglementation_astra_sans_titre( $actif ) {
	return is_page_template( DITL_TPL_REGLEMENTATION ) ? false : $actif;
}
add_filter( 'astra_the_title_enabled', 'ditl_reglementation_astra_sans_titre', 20 );
add_filter( 'astra_the_post_title_enabled', 'ditl_reglementation_astra_sans_titre', 20 );
add_filter( 'astra_featured_image_enabled', 'ditl_reglementation_astra_sans_titre', 20 );

/**
 * Retire la classe ast-normal-title-enabled du body sur le gabarit.
 *
 * Astra (inc/metabox/class-astra-meta-box-operations.php) l'ajoute en lisant
 * directement la meta site-post-title, sans filtre : les pages des autres
 * gabarits (meta a "disabled") ne la portent pas, la page Reglementation ne
 * doit pas la porter non plus.
 *
 * @param string[] $classes Classes du body.
 * @return string[] Classes filtrees.
 */
function ditl_reglementation_astra_classes_body( $classes ) {
	if ( is_page_template( DITL_TPL_REGLEMENTATION ) ) {
		$classes = array_values( array_diff( (array) $classes, array( 'ast-normal-title-enabled' ) ) );
	}

	return $classes;
}
add_filter( 'body_class', 'ditl_reglementation_astra_classes_body', 20 );

/* -------------------------------------------------------------------------
 * Ecran de liste et d'edition des pays (administration).
 * ---------------------------------------------------------------------- */

/**
 * Invite du champ titre de la fiche pays.
 *
 * @param string  $texte Invite par defaut.
 * @param WP_Post $post  Publication en cours d'edition.
 * @return string Invite adaptee aux pays.
 */
function ditl_reglementation_invite_titre( $texte, $post ) {
	if ( $post instanceof WP_Post && DITL_REGL_POST_TYPE === $post->post_type ) {
		return __( 'Nom du pays (affiche dans l\'onglet)', 'ditl' );
	}

	return $texte;
}
add_filter( 'enter_title_here', 'ditl_reglementation_invite_titre', 10, 2 );

/**
 * Messages d'enregistrement de la fiche pays.
 *
 * Sans eux, WordPress afficherait les messages des articles ("Article mis a
 * jour"). Le type n'etant pas public, aucun lien "Voir" n'est propose.
 *
 * @param array $messages Messages par type de publication.
 * @return array Messages completes.
 */
function ditl_reglementation_messages( $messages ) {
	$messages[ DITL_REGL_POST_TYPE ] = array(
		0  => '',
		1  => __( 'Pays mis a jour.', 'ditl' ),
		2  => __( 'Champ mis a jour.', 'ditl' ),
		3  => __( 'Champ supprime.', 'ditl' ),
		4  => __( 'Pays mis a jour.', 'ditl' ),
		5  => __( 'Version du pays restauree.', 'ditl' ),
		6  => __( 'Pays publie.', 'ditl' ),
		7  => __( 'Pays enregistre.', 'ditl' ),
		8  => __( 'Pays envoye pour relecture.', 'ditl' ),
		9  => __( 'Publication du pays planifiee.', 'ditl' ),
		10 => __( 'Brouillon du pays mis a jour.', 'ditl' ),
	);

	return $messages;
}
add_filter( 'post_updated_messages', 'ditl_reglementation_messages' );

/**
 * Messages des actions groupees sur la liste des pays.
 *
 * @param array $messages Messages par type de publication.
 * @param int[] $nombres  Nombre d'elements traites par action.
 * @return array Messages completes.
 */
function ditl_reglementation_messages_groupes( $messages, $nombres ) {
	$messages[ DITL_REGL_POST_TYPE ] = array(
		/* translators: %s : nombre de pays. */
		'updated'   => _n( '%s pays mis a jour.', '%s pays mis a jour.', $nombres['updated'], 'ditl' ),
		/* translators: %s : nombre de pays. */
		'locked'    => _n( '%s pays non mis a jour, en cours de modification par un autre utilisateur.', '%s pays non mis a jour, en cours de modification par un autre utilisateur.', $nombres['locked'], 'ditl' ),
		/* translators: %s : nombre de pays. */
		'deleted'   => _n( '%s pays supprime definitivement.', '%s pays supprimes definitivement.', $nombres['deleted'], 'ditl' ),
		/* translators: %s : nombre de pays. */
		'trashed'   => _n( '%s pays deplace dans la corbeille.', '%s pays deplaces dans la corbeille.', $nombres['trashed'], 'ditl' ),
		/* translators: %s : nombre de pays. */
		'untrashed' => _n( '%s pays restaure depuis la corbeille.', '%s pays restaures depuis la corbeille.', $nombres['untrashed'], 'ditl' ),
	);

	return $messages;
}
add_filter( 'bulk_post_updated_messages', 'ditl_reglementation_messages_groupes', 10, 2 );

/**
 * Colonnes de la liste des pays : drapeau, ordre, sections affichees.
 *
 * @param string[] $colonnes Colonnes par defaut.
 * @return string[] Colonnes completees (drapeau avant le titre, ordre et
 *                  sections juste apres).
 */
function ditl_reglementation_colonnes( $colonnes ) {
	$nouvelles = array();

	foreach ( (array) $colonnes as $cle => $libelle ) {
		if ( 'title' === $cle ) {
			$nouvelles['ditl_drapeau'] = __( 'Drapeau', 'ditl' );
		}

		$nouvelles[ $cle ] = $libelle;

		if ( 'title' === $cle ) {
			$nouvelles['ditl_ordre']    = __( 'Ordre', 'ditl' );
			$nouvelles['ditl_sections'] = __( 'Sections affichees', 'ditl' );
		}
	}

	return $nouvelles;
}
add_filter( 'manage_' . DITL_REGL_POST_TYPE . '_posts_columns', 'ditl_reglementation_colonnes' );

/**
 * Contenu des colonnes de la liste des pays.
 *
 * "Sections affichees" = sections qui apparaissent sur le site (regle D5)
 * sur le nombre de sections enregistrees ; a 0, le pays est masque.
 *
 * @param string $colonne Cle de la colonne.
 * @param int    $post_id ID du pays.
 */
function ditl_reglementation_colonne_contenu( $colonne, $post_id ) {
	if ( 'ditl_drapeau' === $colonne ) {
		$drapeau_id = ditl_reglementation_id_image( get_post_meta( $post_id, '_ditl_regl_drapeau_id', true ) );

		if ( $drapeau_id <= 0 ) {
			echo '<span aria-hidden="true">-</span><span class="screen-reader-text">' . esc_html__( 'Aucun drapeau', 'ditl' ) . '</span>';
		} else {
			echo ditl_reglementation_image( $drapeau_id, array( 32, 32 ), array(), array( 32, 32 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML echappe par ditl_reglementation_image().
		}

		return;
	}

	if ( 'ditl_ordre' === $colonne ) {
		echo esc_html( (string) (int) get_post_field( 'menu_order', $post_id ) );
		return;
	}

	if ( 'ditl_sections' === $colonne ) {
		$sections    = ditl_get_meta_json( $post_id, '_ditl_regl_sections' );
		$affichables = count( ditl_reglementation_sections_affichables( $sections ) );

		echo esc_html( sprintf( '%1$d / %2$d', $affichables, count( $sections ) ) );

		if ( 0 === $affichables ) {
			echo ' <em>' . esc_html__( '(pays non affiche)', 'ditl' ) . '</em>';
		}
	}
}
add_action( 'manage_' . DITL_REGL_POST_TYPE . '_posts_custom_column', 'ditl_reglementation_colonne_contenu', 10, 2 );

/**
 * Rend la colonne "Ordre" triable.
 *
 * @param array $colonnes Colonnes triables.
 * @return array Colonnes triables completees.
 */
function ditl_reglementation_colonnes_triables( $colonnes ) {
	$colonnes['ditl_ordre'] = 'menu_order';

	return $colonnes;
}
add_filter( 'manage_edit-' . DITL_REGL_POST_TYPE . '_sortable_columns', 'ditl_reglementation_colonnes_triables' );

/**
 * Tri par defaut de la liste des pays : ordre des onglets (menu_order) puis
 * titre, comme sur le site. Un tri choisi en cliquant sur une colonne reste
 * prioritaire.
 *
 * @param WP_Query $query Requete en preparation.
 */
function ditl_reglementation_tri_liste( $query ) {
	global $pagenow;

	if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
		return;
	}

	if ( DITL_REGL_POST_TYPE !== $query->get( 'post_type' ) || '' !== (string) $query->get( 'orderby' ) ) {
		return;
	}

	$query->set(
		'orderby',
		array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		)
	);
}
add_action( 'pre_get_posts', 'ditl_reglementation_tri_liste' );

/**
 * Largeur des colonnes ajoutees a la liste des pays.
 *
 * @param string $hook_suffix Ecran admin courant.
 */
function ditl_reglementation_styles_liste( $hook_suffix ) {
	if ( 'edit.php' !== $hook_suffix ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || DITL_REGL_POST_TYPE !== $screen->post_type ) {
		return;
	}

	wp_register_style( 'ditl-admin-reglementation-liste', false, array(), DITL_THEME_VERSION );
	wp_enqueue_style( 'ditl-admin-reglementation-liste' );
	wp_add_inline_style( 'ditl-admin-reglementation-liste', '.column-ditl_drapeau,.column-ditl_ordre{width:64px}.column-ditl_sections{width:160px}.column-ditl_drapeau img{display:block;width:32px;height:32px;object-fit:cover;border-radius:50%}' );
}
add_action( 'admin_enqueue_scripts', 'ditl_reglementation_styles_liste' );

/* -------------------------------------------------------------------------
 * Cache de page (WP Fastest Cache).
 * ---------------------------------------------------------------------- */

/**
 * Purge complete du cache de page via l'API publique de WP Fastest Cache.
 *
 * Sans l'extension (ou API absente), rien n'est fait.
 */
function ditl_reglementation_purger_cache() {
	if ( function_exists( 'wpfc_clear_all_cache' ) ) {
		wpfc_clear_all_cache();
	}
}

/**
 * Purge le cache de page quand un pays PUBLIE part a la corbeille.
 *
 * Avec la configuration du site (cli/configurer-cache.php : purge complete
 * a la publication, a la mise a jour et a la depublication), WP Fastest
 * Cache ne purge, sur le passage publie -> corbeille, que l'URL de la
 * publication elle-meme (singleDeleteCache). Or un pays n'a pas d'URL : la
 * page Reglementation resterait servie en cache avec le pays supprime
 * jusqu'a la purge quotidienne. La purge complete est donc declenchee ici.
 *
 * @param int    $post_id         ID de la publication mise a la corbeille.
 * @param string $previous_status Statut avant la corbeille.
 */
function ditl_reglementation_purger_cache_corbeille( $post_id, $previous_status = '' ) {
	if ( DITL_REGL_POST_TYPE === get_post_type( $post_id ) && 'publish' === $previous_status ) {
		ditl_reglementation_purger_cache();
	}
}
add_action( 'trashed_post', 'ditl_reglementation_purger_cache_corbeille', 10, 2 );

/**
 * Purge le cache de page quand un pays PUBLIE est supprime definitivement
 * sans passer par la corbeille (aucune transition de statut : WP Fastest
 * Cache ne purge rien). Un pays deja en corbeille a purge le cache a son
 * arrivee dans la corbeille.
 *
 * @param int     $post_id ID de la publication supprimee.
 * @param WP_Post $post    Publication supprimee (statut avant suppression).
 */
function ditl_reglementation_purger_cache_suppression( $post_id, $post ) {
	if ( $post instanceof WP_Post && DITL_REGL_POST_TYPE === $post->post_type && 'publish' === $post->post_status ) {
		ditl_reglementation_purger_cache();
	}
}
add_action( 'after_delete_post', 'ditl_reglementation_purger_cache_suppression', 10, 2 );
