<?php
/**
 * Ecran d'edition d'une fiche outil (type ditl_ressource, editeur classique)
 * et elements communs aux ecrans d'administration de la boite a outils.
 *
 * Fiche outil :
 * - titre : texte d'invite adapte ("Nom de l'outil") ;
 * - editeur principal (post_content) : intitule "Apport de la compensation"
 *   et courte aide places juste au-dessus ;
 * - metabox "Informations de la fiche" : phrase d'aptitude, descriptif
 *   technique et points de vigilance (editeurs a barre reduite), fabricants
 *   (lignes repetables nom + adresse), sources (une adresse par ligne),
 *   texte alternatif de l'illustration. Contrat des metas : modele.php ;
 * - metaboxes des taxonomies : cases a cocher natives, arbre conserve (les
 *   termes coches ne remontent pas en tete), rappel de la regle de marquage
 *   sous les taxonomies a niveaux, champ "Parent" de l'ajout rapide limite
 *   au premier niveau (masque pour les listes simples).
 *
 * Elements communs (utilises aussi par admin-termes.php et admin-page.php) :
 * - avertissements differes (transient par utilisateur, affiches une fois) ;
 * - chargement de assets/admin/boite-admin.js|css, limite aux ecrans de la
 *   boite a outils.
 *
 * Enregistrement : nonce lie a l'ID de la fiche, garde autosave/revision,
 * capacite edit_post. Une meta n'est ecrite que si son champ est poste (une
 * metabox absente ou masquee par une extension n'efface rien) ; les metas
 * sont nettoyees une seconde fois par leur sanitize declare (modele.php).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Nombre maximal de fabricants par fiche (meme garde-fou que le modele).
define( 'DITL_BOITE_MAX_FABRICANTS', 100 );

// Nombre maximal de sources par fiche (meme garde-fou que le modele).
define( 'DITL_BOITE_MAX_SOURCES', 100 );

/* -------------------------------------------------------------------------
 * Avertissements differes (communs aux ecrans de la boite a outils).
 * ---------------------------------------------------------------------- */

/**
 * Avertissements emis pendant la requete courante.
 *
 * Sert aux requetes AJAX de creation de terme (aucun rechargement de page :
 * le message est renvoye dans la reponse, voir admin-termes.php).
 *
 * @param string|null $message Message a ajouter (null : lecture seule).
 * @return string[] Messages de la requete.
 */
function ditl_boite_admin_avis_requete( $message = null ) {
	static $avis = array();

	if ( is_string( $message ) && '' !== $message ) {
		$avis[] = $message;
	}

	return $avis;
}

/**
 * Indique si la requete courante est un ajout de terme en AJAX depuis
 * l'ecran d'une liste de la boite a outils.
 *
 * @return bool True pour l'action AJAX "add-tag" d'une taxonomie de la boite.
 */
function ditl_boite_admin_est_ajout_terme_ajax() {
	if ( ! wp_doing_ajax() ) {
		return false;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- lecture du contexte ; le nonce est verifie par WordPress et par l'enregistrement.
	$action    = isset( $_POST['action'] ) && is_string( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
	$taxonomie = isset( $_POST['taxonomy'] ) && is_string( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	return 'add-tag' === $action && ditl_boite_est_taxonomie( $taxonomie );
}

/**
 * Enregistre un avertissement a afficher a l'utilisateur courant.
 *
 * Hors AJAX, le message est conserve quelques minutes (transient propre a
 * l'utilisateur) et affiche une seule fois au prochain ecran d'admin, apres
 * la redirection qui suit un enregistrement.
 *
 * @param string $message Message en texte simple (echappe a l'affichage).
 */
function ditl_boite_admin_avertir( $message ) {
	$message = is_string( $message ) ? trim( $message ) : '';

	if ( '' === $message ) {
		return;
	}

	ditl_boite_admin_avis_requete( $message );

	if ( ditl_boite_admin_est_ajout_terme_ajax() ) {
		return;
	}

	$utilisateur = get_current_user_id();

	if ( $utilisateur <= 0 ) {
		return;
	}

	$cle  = 'ditl_boite_avis_' . $utilisateur;
	$avis = get_transient( $cle );
	$avis = is_array( $avis ) ? $avis : array();

	$avis[] = $message;

	// Garde-fou : quelques messages au plus, sans doublon.
	set_transient( $cle, array_slice( array_values( array_unique( $avis ) ), -10 ), 5 * MINUTE_IN_SECONDS );
}

/**
 * Affiche (une fois) les avertissements en attente de l'utilisateur courant.
 */
function ditl_boite_admin_afficher_avis() {
	$utilisateur = get_current_user_id();

	if ( $utilisateur <= 0 ) {
		return;
	}

	$cle  = 'ditl_boite_avis_' . $utilisateur;
	$avis = get_transient( $cle );

	if ( false === $avis ) {
		return;
	}

	delete_transient( $cle );

	foreach ( (array) $avis as $message ) {
		if ( is_string( $message ) && '' !== $message ) {
			echo '<div class="notice notice-warning is-dismissible ditl-boite-avis"><p>' . esc_html( $message ) . '</p></div>';
		}
	}
}
add_action( 'admin_notices', 'ditl_boite_admin_afficher_avis' );

/* -------------------------------------------------------------------------
 * Feuille de style et script d'administration (communs).
 * ---------------------------------------------------------------------- */

/**
 * Ecran d'administration courant, du point de vue de la boite a outils.
 *
 * @return string "fiche", "termes", "page" ou "" (autre ecran).
 */
function ditl_boite_admin_ecran() {
	$ecran = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $ecran ) {
		return '';
	}

	if ( 'post' === $ecran->base && DITL_RESSOURCE_POST_TYPE === $ecran->post_type ) {
		return 'fiche';
	}

	if ( ( 'edit-tags' === $ecran->base || 'term' === $ecran->base ) && ditl_boite_est_taxonomie( $ecran->taxonomy ) ) {
		return 'termes';
	}

	if ( 'post' === $ecran->base && 'page' === $ecran->post_type ) {
		return 'page';
	}

	return '';
}

/**
 * Charge la feuille de style et le script d'administration de la boite a
 * outils, avec leurs reglages (objet JS ditlBoiteAdmin).
 *
 * @param string $ecran   Ecran courant (voir ditl_boite_admin_ecran()).
 * @param array  $reglages Reglages supplementaires transmis au script.
 */
function ditl_boite_admin_charger_assets( $ecran, $reglages = array() ) {
	global $wp_locale;

	$niveaux = array();

	foreach ( ditl_boite_taxonomies() as $taxonomie => $definition ) {
		if ( (int) $definition['niveaux'] > 1 ) {
			$niveaux[] = $taxonomie;
		}
	}

	wp_enqueue_style(
		'ditl-boite-admin',
		get_stylesheet_directory_uri() . '/assets/admin/boite-admin.css',
		array(),
		DITL_THEME_VERSION
	);

	wp_enqueue_script(
		'ditl-boite-admin',
		get_stylesheet_directory_uri() . '/assets/admin/boite-admin.js',
		array( 'jquery', 'wp-a11y' ),
		DITL_THEME_VERSION,
		true
	);

	wp_localize_script(
		'ditl-boite-admin',
		'ditlBoiteAdmin',
		array_merge(
			array(
				'ecran'             => $ecran,
				'taxonomies'        => array_keys( ditl_boite_taxonomies() ),
				'taxonomiesNiveaux' => $niveaux,
				'maxFabricants'     => DITL_BOITE_MAX_FABRICANTS,
				// Calcul du contraste des badges : miroir JS de commun.php
				// (ditl_boite_couleur_texte), le PHP fait foi a l'affichage.
				'couleurCharte'     => '#1e448f',
				'seuilContraste'    => 4.5,
				'separateurDecimal' => isset( $wp_locale->number_format['decimal_point'] ) ? (string) $wp_locale->number_format['decimal_point'] : ',',
				'pageTextes'        => null,
				'i18n'              => array(
					/* translators: %d : numero d'ordre du fabricant. */
					'fabricant'        => __( 'Fabricant %d', 'ditl' ),
					/* translators: %d : numero d'ordre du fabricant. */
					'monter'           => __( 'Monter le fabricant %d', 'ditl' ),
					/* translators: %d : numero d'ordre du fabricant. */
					'descendre'        => __( 'Descendre le fabricant %d', 'ditl' ),
					/* translators: %d : numero d'ordre du fabricant. */
					'supprimer'        => __( 'Supprimer le fabricant %d', 'ditl' ),
					'fabricantAjoute'  => __( 'Ligne de fabricant ajoutée.', 'ditl' ),
					'fabricantRetire'  => __( 'Fabricant retiré.', 'ditl' ),
					'fabricantDeplace' => __( 'Fabricant déplacé.', 'ditl' ),
					'limiteFabricants' => __( 'Nombre maximal de fabricants atteint.', 'ditl' ),
					/* translators: 1 : ratio de contraste (ex. 8,59), 2 : couleur du texte (ex. texte noir). */
					'contraste'        => __( 'Contraste du texte : %1$s:1 (%2$s, choisi automatiquement).', 'ditl' ),
					'texteCharte'      => __( 'texte bleu de la charte', 'ditl' ),
					'texteBlanc'       => __( 'texte blanc', 'ditl' ),
					'texteNoir'        => __( 'texte noir', 'ditl' ),
					'sansCouleur'      => __( 'Aucune couleur : le badge garde le style par défaut.', 'ditl' ),
					'couleurInvalide'  => __( 'Couleur non valide : saisir un code hexadécimal, ex. #00b0cf.', 'ditl' ),
					'exemple'          => __( 'Exemple', 'ditl' ),
					// Message standard de WordPress apres l'ajout d'un terme
					// (permet de reperer un message remplace par un
					// avertissement, voir admin-termes.php).
					'termeAjoute'      => __( 'Item added.' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- chaine du coeur.
				),
			),
			$reglages
		)
	);
}

/**
 * Charge les assets sur l'ecran d'edition des fiches et sur les ecrans des
 * listes de la boite a outils (les pages sont traitees par admin-page.php).
 */
function ditl_boite_admin_assets() {
	$ecran = ditl_boite_admin_ecran();

	if ( 'fiche' === $ecran || 'termes' === $ecran ) {
		ditl_boite_admin_charger_assets( $ecran );
	}
}
add_action( 'admin_enqueue_scripts', 'ditl_boite_admin_assets', 20 );

/* -------------------------------------------------------------------------
 * Fiche outil : titre et editeur principal.
 * ---------------------------------------------------------------------- */

/**
 * Texte d'invite du champ titre d'une fiche.
 *
 * @param string  $texte Texte d'invite par defaut.
 * @param WP_Post $post  Publication en cours d'edition.
 * @return string Texte d'invite.
 */
function ditl_boite_fiche_invite_titre( $texte, $post ) {
	if ( $post instanceof WP_Post && DITL_RESSOURCE_POST_TYPE === $post->post_type ) {
		return __( 'Nom de l\'outil (ex. Rallonges de pédales)', 'ditl' );
	}

	return $texte;
}
add_filter( 'enter_title_here', 'ditl_boite_fiche_invite_titre', 10, 2 );

/**
 * Intitule et aide de l'editeur principal (post_content = "Apport de la
 * compensation"), places entre le titre et l'editeur.
 *
 * @param WP_Post $post Publication en cours d'edition.
 */
function ditl_boite_fiche_intitule_editeur( $post ) {
	if ( ! $post instanceof WP_Post || DITL_RESSOURCE_POST_TYPE !== $post->post_type ) {
		return;
	}
	?>
	<div class="ditl-boite-intitule-editeur">
		<p class="ditl-boite-intitule"><label for="content"><?php esc_html_e( 'Apport de la compensation', 'ditl' ); ?></label></p>
		<p class="description"><?php esc_html_e( 'Texte principal de la fiche : ce que l\'outil apporte à la personne et dans quelle situation il l\'aide. Les autres rubriques se remplissent dans le bloc « Informations de la fiche », sous l\'éditeur.', 'ditl' ); ?></p>
	</div>
	<?php
}
add_action( 'edit_form_after_title', 'ditl_boite_fiche_intitule_editeur' );

/* -------------------------------------------------------------------------
 * Fiche outil : taxonomies.
 * ---------------------------------------------------------------------- */

/**
 * Conserve l'arbre des cases a cocher (metier > tache, difficulte >
 * empechement) : par defaut, WordPress remonte les termes coches en tete de
 * liste, ce qui separe une tache de son metier.
 *
 * @param array $args    Arguments de wp_terms_checklist.
 * @param int   $post_id ID de la publication.
 * @return array Arguments modifies.
 */
function ditl_boite_fiche_arbre_cases( $args, $post_id ) {
	if ( is_array( $args ) && isset( $args['taxonomy'] ) && ditl_boite_est_taxonomie( $args['taxonomy'] ) ) {
		$args['checked_ontop'] = false;
	}

	return $args;
}
add_filter( 'wp_terms_checklist_args', 'ditl_boite_fiche_arbre_cases', 10, 2 );

/**
 * Limite le champ "Parent" de l'ajout rapide ("+ Ajouter", metabox de
 * taxonomie de la fiche) aux termes de premier niveau.
 *
 * Les reponses AJAX (ajout d'un terme, changement de langue par Polylang)
 * reconstruisent cette liste sans ce filtre : le script d'administration
 * retire alors les niveaux inferieurs (boite-admin.js).
 *
 * @param array $args Arguments de wp_dropdown_categories.
 * @return array Arguments modifies.
 */
function ditl_boite_fiche_parent_ajout_rapide( $args ) {
	$taxonomies = ditl_boite_taxonomies();

	if ( is_array( $args ) && isset( $args['taxonomy'] ) && is_string( $args['taxonomy'] ) && isset( $taxonomies[ $args['taxonomy'] ] ) ) {
		$args['depth'] = 1;
	}

	return $args;
}
add_filter( 'post_edit_category_parent_dropdown_args', 'ditl_boite_fiche_parent_ajout_rapide' );

/**
 * Rappel de la regle de marquage d'une taxonomie a niveaux.
 *
 * @param string $taxonomie Taxonomie de la boite a outils.
 * @return string Texte d'aide (vide pour une liste simple).
 */
function ditl_boite_fiche_aide_marquage( $taxonomie ) {
	if ( 'ditl_metier' === $taxonomie ) {
		return __( 'Cochez la tâche la plus précise. Un métier coché seul, sans tâche, vaut pour toutes ses tâches.', 'ditl' );
	}

	if ( 'ditl_aptitude' === $taxonomie ) {
		return __( 'Cochez l\'empêchement le plus précis. Une difficulté cochée seule, sans empêchement, vaut pour tous ses empêchements.', 'ditl' );
	}

	$taxonomies = ditl_boite_taxonomies();

	if ( isset( $taxonomies[ $taxonomie ] ) && (int) $taxonomies[ $taxonomie ]['niveaux'] > 1 ) {
		return __( 'Cochez l\'élément le plus précis. Un élément de premier niveau coché seul vaut pour tous ses sous-éléments.', 'ditl' );
	}

	return '';
}

/**
 * Remplace l'affichage des metaboxes de taxonomie de la fiche par une
 * enveloppe : rappel de la regle de marquage, classe de mise en forme, puis
 * metabox native inchangee (post_categories_meta_box).
 *
 * Meme identifiant que la metabox native ({taxonomie}div) : les scripts du
 * coeur et de Polylang continuent de la reconnaitre, l'ordre choisi par
 * l'utilisateur est conserve.
 *
 * @param WP_Post $post Fiche en cours d'edition.
 */
function ditl_boite_fiche_metaboxes_taxonomies( $post ) {
	foreach ( array_keys( ditl_boite_taxonomies() ) as $taxonomie ) {
		$objet = get_taxonomy( $taxonomie );

		if ( ! $objet || ! $objet->show_ui || 'post_categories_meta_box' !== $objet->meta_box_cb ) {
			continue;
		}

		// Priorite "default" : une metabox "core" deja declaree ne peut pas
		// etre redeclaree avec la priorite "core" (voir add_meta_box).
		add_meta_box(
			$taxonomie . 'div',
			$objet->labels->name,
			'ditl_boite_fiche_metabox_taxonomie',
			null,
			'side',
			'default',
			array(
				'taxonomy'               => $taxonomie,
				'__back_compat_meta_box' => true,
			)
		);
	}
}
add_action( 'add_meta_boxes_' . DITL_RESSOURCE_POST_TYPE, 'ditl_boite_fiche_metaboxes_taxonomies' );

/**
 * Affiche une metabox de taxonomie de la fiche (enveloppe de la metabox
 * native).
 *
 * @param WP_Post $post Fiche en cours d'edition.
 * @param array   $box  Definition de la metabox (args.taxonomy).
 */
function ditl_boite_fiche_metabox_taxonomie( $post, $box ) {
	$taxonomie  = isset( $box['args']['taxonomy'] ) && is_string( $box['args']['taxonomy'] ) ? $box['args']['taxonomy'] : '';
	$taxonomies = ditl_boite_taxonomies();

	if ( ! isset( $taxonomies[ $taxonomie ] ) ) {
		post_categories_meta_box( $post, $box );

		return;
	}

	$a_niveaux = (int) $taxonomies[ $taxonomie ]['niveaux'] > 1;
	$aide      = ditl_boite_fiche_aide_marquage( $taxonomie );

	echo '<div class="ditl-boite-taxo ' . ( $a_niveaux ? 'ditl-boite-taxo-niveaux' : 'ditl-boite-taxo-plate' ) . '">';

	if ( '' !== $aide ) {
		echo '<p class="description ditl-boite-taxo-aide">' . esc_html( $aide ) . '</p>';
	}

	post_categories_meta_box( $post, $box );

	echo '</div>';
}

/* -------------------------------------------------------------------------
 * Fiche outil : metabox "Informations de la fiche".
 * ---------------------------------------------------------------------- */

/**
 * Ajoute la metabox des informations de la fiche.
 */
function ditl_boite_fiche_ajouter_metabox() {
	add_meta_box(
		'ditl-boite-fiche',
		__( 'Informations de la fiche', 'ditl' ),
		'ditl_boite_fiche_afficher_metabox',
		DITL_RESSOURCE_POST_TYPE,
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_' . DITL_RESSOURCE_POST_TYPE, 'ditl_boite_fiche_ajouter_metabox' );

/**
 * Reglages des editeurs a barre reduite de la metabox.
 *
 * Gras, italique, listes, lien : rien d'autre (pas de medias, pas de
 * titres). Les balises acceptees par l'editeur suivent la meme liste, ce
 * qui nettoie aussi un texte colle depuis un traitement de texte.
 *
 * @param string $name Attribut name de la zone de texte.
 * @return array Reglages pour wp_editor().
 */
function ditl_boite_fiche_reglages_editeur( $name ) {
	return array(
		'textarea_name'    => $name,
		'textarea_rows'    => 6,
		'media_buttons'    => false,
		'drag_drop_upload' => false,
		'teeny'            => true,
		'editor_class'     => 'ditl-boite-editeur',
		'tinymce'          => array(
			'toolbar1'       => 'bold,italic,bullist,numlist,link,unlink,undo,redo',
			'toolbar2'       => '',
			'valid_elements' => 'p,br,strong/b,em/i,ul,ol,li,a[href|target|rel|hreflang|lang]',
		),
		'quicktags'        => array( 'buttons' => 'strong,em,ul,ol,li,link' ),
	);
}

/**
 * Affiche une ligne de fabricant (ligne existante, ligne vide ou modele JS).
 *
 * @param array $fabricant Donnees {nom, url} de la ligne.
 */
function ditl_boite_fiche_ligne_fabricant( $fabricant = array() ) {
	$nom = isset( $fabricant['nom'] ) && is_scalar( $fabricant['nom'] ) ? (string) $fabricant['nom'] : '';
	$url = isset( $fabricant['url'] ) && is_scalar( $fabricant['url'] ) ? (string) $fabricant['url'] : '';
	?>
	<li class="ditl-boite-ligne">
		<span class="ditl-boite-ligne-numero" aria-hidden="true"></span>
		<label class="ditl-boite-ligne-champ">
			<span class="ditl-boite-ligne-libelle"><?php esc_html_e( 'Nom du fabricant', 'ditl' ); ?></span>
			<input type="text" class="widefat" name="ditl_fabricants_nom[]" value="<?php echo esc_attr( $nom ); ?>" autocomplete="off" />
		</label>
		<label class="ditl-boite-ligne-champ">
			<span class="ditl-boite-ligne-libelle"><?php esc_html_e( 'Site web (adresse)', 'ditl' ); ?></span>
			<input type="text" class="widefat code" name="ditl_fabricants_url[]" value="<?php echo esc_attr( $url ); ?>" inputmode="url" placeholder="https://" autocomplete="off" spellcheck="false" />
		</label>
		<span class="ditl-boite-ligne-actions">
			<button type="button" class="button ditl-boite-monter"><span aria-hidden="true">&uarr;</span><span class="screen-reader-text"><?php esc_html_e( 'Monter', 'ditl' ); ?></span></button>
			<button type="button" class="button ditl-boite-descendre"><span aria-hidden="true">&darr;</span><span class="screen-reader-text"><?php esc_html_e( 'Descendre', 'ditl' ); ?></span></button>
			<button type="button" class="button ditl-boite-supprimer"><?php esc_html_e( 'Supprimer', 'ditl' ); ?></button>
		</span>
	</li>
	<?php
}

/**
 * Affiche la metabox des informations de la fiche.
 *
 * @param WP_Post $post Fiche en cours d'edition.
 */
function ditl_boite_fiche_afficher_metabox( $post ) {
	wp_nonce_field( 'ditl_boite_fiche_' . $post->ID, 'ditl_boite_fiche_nonce' );

	$aptitude    = (string) get_post_meta( $post->ID, '_ditl_aptitude_texte', true );
	$descriptif  = (string) get_post_meta( $post->ID, '_ditl_descriptif_technique', true );
	$vigilance   = (string) get_post_meta( $post->ID, '_ditl_points_vigilance', true );
	$image_alt   = (string) get_post_meta( $post->ID, '_ditl_image_alt', true );
	$fabricants  = ditl_get_meta_json( $post->ID, '_ditl_fabricants' );
	$sources     = array_filter( ditl_get_meta_json( $post->ID, '_ditl_sources' ), 'is_string' );

	// Au moins une ligne de fabricant, vide si besoin (utilisable sans JS).
	if ( array() === $fabricants ) {
		$fabricants = array( array() );
	}
	?>
	<div class="ditl-boite-metabox">
		<div class="ditl-boite-champ">
			<label class="ditl-boite-libelle" for="ditl-aptitude-texte"><?php esc_html_e( 'Aptitude concernée (phrase)', 'ditl' ); ?></label>
			<textarea class="widefat" id="ditl-aptitude-texte" name="ditl_aptitude_texte" rows="2" aria-describedby="ditl-aptitude-texte-aide"><?php echo esc_textarea( $aptitude ); ?></textarea>
			<p class="description" id="ditl-aptitude-texte-aide"><?php esc_html_e( 'Phrase affichée dans le bloc « Informations » de la fiche, ex. « Compréhension écrite (lecture) mais également visuelle ». Texte simple.', 'ditl' ); ?></p>
		</div>

		<div class="ditl-boite-champ">
			<label class="ditl-boite-libelle" for="ditl_descriptif_technique"><?php esc_html_e( 'Descriptif technique', 'ditl' ); ?></label>
			<?php wp_editor( $descriptif, 'ditl_descriptif_technique', ditl_boite_fiche_reglages_editeur( 'ditl_descriptif_technique' ) ); ?>
			<p class="description" id="ditl-descriptif-technique-aide"><?php esc_html_e( 'Fonctionnement et caractéristiques de l\'outil, affichés dans le bloc « Informations ». Mise en forme : gras, italique, listes, liens.', 'ditl' ); ?></p>
		</div>

		<div class="ditl-boite-champ">
			<label class="ditl-boite-libelle" for="ditl_points_vigilance"><?php esc_html_e( 'Points de vigilance', 'ditl' ); ?></label>
			<?php wp_editor( $vigilance, 'ditl_points_vigilance', ditl_boite_fiche_reglages_editeur( 'ditl_points_vigilance' ) ); ?>
			<p class="description" id="ditl-points-vigilance-aide"><?php esc_html_e( 'Précautions, limites et conditions de mise en place de l\'outil. Mise en forme : gras, italique, listes, liens.', 'ditl' ); ?></p>
		</div>

		<fieldset class="ditl-boite-champ ditl-boite-fabricants" aria-describedby="ditl-fabricants-aide">
			<legend class="ditl-boite-libelle"><?php esc_html_e( 'Fabricants', 'ditl' ); ?></legend>
			<p class="description" id="ditl-fabricants-aide"><?php esc_html_e( 'Nom et site web de chaque fabricant ou distributeur, dans l\'ordre d\'affichage. Une ligne laissée vide est ignorée.', 'ditl' ); ?></p>
			<ol class="ditl-boite-lignes">
				<?php foreach ( array_slice( $fabricants, 0, DITL_BOITE_MAX_FABRICANTS ) as $fabricant ) : ?>
					<?php ditl_boite_fiche_ligne_fabricant( is_array( $fabricant ) ? $fabricant : array() ); ?>
				<?php endforeach; ?>
			</ol>
			<button type="button" class="button ditl-boite-ajouter"><?php esc_html_e( 'Ajouter un fabricant', 'ditl' ); ?></button>
			<script type="text/html" class="ditl-boite-modele-ligne">
				<?php ditl_boite_fiche_ligne_fabricant(); ?>
			</script>
		</fieldset>

		<div class="ditl-boite-champ">
			<label class="ditl-boite-libelle" for="ditl-sources"><?php esc_html_e( 'Sources', 'ditl' ); ?></label>
			<textarea class="widefat code" id="ditl-sources" name="ditl_sources" rows="4" spellcheck="false" aria-describedby="ditl-sources-aide"><?php echo esc_textarea( implode( "\n", $sources ) ); ?></textarea>
			<p class="description" id="ditl-sources-aide"><?php esc_html_e( 'Une adresse web par ligne (https://...). Les lignes vides sont ignorées ; une adresse non valide est signalée et n\'est pas enregistrée.', 'ditl' ); ?></p>
		</div>

		<div class="ditl-boite-champ">
			<label class="ditl-boite-libelle" for="ditl-image-alt"><?php esc_html_e( 'Texte alternatif de l\'illustration', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-image-alt" name="ditl_image_alt" value="<?php echo esc_attr( $image_alt ); ?>" aria-describedby="ditl-image-alt-aide" />
			<p class="description" id="ditl-image-alt-aide"><?php esc_html_e( 'Décrit l\'illustration (bloc « Illustration de la fiche ») pour les personnes qui ne la voient pas. Laisser vide si l\'image est purement décorative ; à traduire dans chaque langue.', 'ditl' ); ?></p>
		</div>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Fiche outil : enregistrement.
 * ---------------------------------------------------------------------- */

/**
 * Verifie qu'un enregistrement de la metabox de fiche est legitime.
 *
 * Equivalent de ditl_metabox_peut_enregistrer (helpers.php) pour le type
 * fiche outil : capacite edit_post (et non edit_page).
 *
 * @param int $post_id ID de la fiche enregistree.
 * @return bool True si l'ecriture des metas est autorisee.
 */
function ditl_boite_fiche_peut_enregistrer( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return false;
	}

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return false;
	}

	if ( ! isset( $_POST['ditl_boite_fiche_nonce'] ) || ! is_string( $_POST['ditl_boite_fiche_nonce'] ) ) {
		return false;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['ditl_boite_fiche_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'ditl_boite_fiche_' . $post_id ) ) {
		return false;
	}

	return current_user_can( 'edit_post', $post_id );
}

/**
 * Normalise une adresse saisie : espaces retires, "https://" ajoute a une
 * adresse sans protocole (ex. "www.exemple.fr"), puis filtrage http/https.
 *
 * @param string $saisie Adresse saisie.
 * @return string Adresse nettoyee (vide si non valide).
 */
function ditl_boite_fiche_normaliser_url( $saisie ) {
	$saisie = trim( (string) $saisie );

	if ( '' === $saisie ) {
		return '';
	}

	// Sans protocole ni chemin relatif : on suppose une adresse web.
	if ( ! preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $saisie ) && '/' !== $saisie[0] ) {
		$saisie = 'https://' . ltrim( $saisie, '/' );
	}

	return esc_url_raw( $saisie, array( 'http', 'https' ) );
}

/**
 * Lit les fabricants postes (tableaux paralleles nom[] / url[]).
 *
 * Le nonce est verifie en amont (ditl_boite_fiche_peut_enregistrer).
 *
 * @param string[] $avertissements Adresses refusees (completees par reference).
 * @return array Fabricants {nom, url} (lignes vides ignorees).
 */
function ditl_boite_fiche_lire_fabricants( &$avertissements ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verifie par l'appelant.
	$noms = isset( $_POST['ditl_fabricants_nom'] ) && is_array( $_POST['ditl_fabricants_nom'] ) ? array_values( wp_unslash( $_POST['ditl_fabricants_nom'] ) ) : array();
	$urls = isset( $_POST['ditl_fabricants_url'] ) && is_array( $_POST['ditl_fabricants_url'] ) ? array_values( wp_unslash( $_POST['ditl_fabricants_url'] ) ) : array();
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	// Garde-fou contre un POST anormalement gonfle.
	$total      = min( max( count( $noms ), count( $urls ) ), DITL_BOITE_MAX_FABRICANTS );
	$fabricants = array();

	for ( $i = 0; $i < $total; $i++ ) {
		$nom    = isset( $noms[ $i ] ) && is_string( $noms[ $i ] ) ? sanitize_text_field( $noms[ $i ] ) : '';
		// Pas de sanitize_text_field sur une adresse : il retire les caracteres
		// encodes (%E9...) et abimerait l'URL a chaque enregistrement ; le
		// filtrage est fait par esc_url_raw (ditl_boite_fiche_normaliser_url).
		$saisie = isset( $urls[ $i ] ) && is_string( $urls[ $i ] ) ? trim( wp_strip_all_tags( $urls[ $i ] ) ) : '';
		$url    = ditl_boite_fiche_normaliser_url( $saisie );

		if ( '' !== $saisie && '' === $url ) {
			$avertissements[] = $saisie;
		}

		if ( '' === $nom && '' === $url ) {
			continue;
		}

		$fabricants[] = array(
			'nom' => $nom,
			'url' => $url,
		);
	}

	return $fabricants;
}

/**
 * Lit les sources postees (une adresse par ligne).
 *
 * Le nonce est verifie en amont (ditl_boite_fiche_peut_enregistrer).
 *
 * @param string[] $avertissements Adresses refusees (completees par reference).
 * @return string[] Adresses nettoyees, sans doublon.
 */
function ditl_boite_fiche_lire_sources( &$avertissements ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verifie par l'appelant.
	$texte   = isset( $_POST['ditl_sources'] ) && is_string( $_POST['ditl_sources'] ) ? wp_unslash( $_POST['ditl_sources'] ) : '';
	$lignes  = preg_split( '/\R/u', (string) $texte );
	$lignes  = is_array( $lignes ) ? $lignes : array();
	$sources = array();

	foreach ( $lignes as $ligne ) {
		// Meme remarque que pour les fabricants : esc_url_raw filtre l'adresse.
		$saisie = trim( wp_strip_all_tags( $ligne ) );

		if ( '' === $saisie ) {
			continue;
		}

		$url = ditl_boite_fiche_normaliser_url( $saisie );

		if ( '' === $url ) {
			$avertissements[] = $saisie;
			continue;
		}

		$sources[] = $url;

		// Garde-fou : meme limite que le modele.
		if ( count( $sources ) >= DITL_BOITE_MAX_SOURCES ) {
			break;
		}
	}

	return array_values( array_unique( $sources ) );
}

/**
 * Enregistre les champs de la metabox de la fiche.
 *
 * @param int $post_id ID de la fiche enregistree.
 */
function ditl_boite_fiche_enregistrer( $post_id ) {
	if ( ! ditl_boite_fiche_peut_enregistrer( $post_id ) ) {
		return;
	}

	$refusees = array();

	// Phrase d'aptitude (texte simple multi-lignes).
	if ( isset( $_POST['ditl_aptitude_texte'] ) && is_string( $_POST['ditl_aptitude_texte'] ) ) {
		$aptitude = sanitize_textarea_field( wp_unslash( $_POST['ditl_aptitude_texte'] ) );
		update_post_meta( $post_id, '_ditl_aptitude_texte', wp_slash( $aptitude ) );
	}

	// Textes riches : paragraphes rendus explicites (wpautop, l'editeur les
	// retire en mode visuel), comme les fiches importees ; HTML filtre par
	// wp_kses_post (ici puis par le sanitize de la meta).
	$riches = array(
		'ditl_descriptif_technique' => '_ditl_descriptif_technique',
		'ditl_points_vigilance'     => '_ditl_points_vigilance',
	);

	foreach ( $riches as $champ => $cle ) {
		if ( isset( $_POST[ $champ ] ) && is_string( $_POST[ $champ ] ) ) {
			$html = trim( wp_unslash( $_POST[ $champ ] ) );
			$html = '' !== trim( wp_strip_all_tags( $html ) ) ? wp_kses_post( wpautop( $html ) ) : '';
			update_post_meta( $post_id, $cle, wp_slash( $html ) );
		}
	}

	// Fabricants (lignes repetables, deux tableaux paralleles).
	if ( ( isset( $_POST['ditl_fabricants_nom'] ) && is_array( $_POST['ditl_fabricants_nom'] ) ) || ( isset( $_POST['ditl_fabricants_url'] ) && is_array( $_POST['ditl_fabricants_url'] ) ) ) {
		$fabricants = ditl_boite_fiche_lire_fabricants( $refusees );
		update_post_meta( $post_id, '_ditl_fabricants', wp_slash( (string) wp_json_encode( $fabricants, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	}

	// Sources (une adresse par ligne).
	if ( isset( $_POST['ditl_sources'] ) && is_string( $_POST['ditl_sources'] ) ) {
		$sources = ditl_boite_fiche_lire_sources( $refusees );
		update_post_meta( $post_id, '_ditl_sources', wp_slash( (string) wp_json_encode( $sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	}

	// Texte alternatif de l'illustration (texte simple).
	if ( isset( $_POST['ditl_image_alt'] ) && is_string( $_POST['ditl_image_alt'] ) ) {
		$image_alt = sanitize_text_field( wp_unslash( $_POST['ditl_image_alt'] ) );
		update_post_meta( $post_id, '_ditl_image_alt', wp_slash( $image_alt ) );
	}

	if ( array() !== $refusees ) {
		ditl_boite_admin_avertir(
			sprintf(
				/* translators: %s : liste des adresses refusees. */
				__( 'Adresse(s) non valide(s), non enregistrée(s) : %s. Une adresse web commence par https:// ou http://.', 'ditl' ),
				implode( ' ; ', array_slice( array_unique( $refusees ), 0, 10 ) )
			)
		);
	}
}
add_action( 'save_post_' . DITL_RESSOURCE_POST_TYPE, 'ditl_boite_fiche_enregistrer' );
