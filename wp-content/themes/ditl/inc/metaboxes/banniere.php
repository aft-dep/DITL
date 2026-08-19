<?php
/**
 * Metabox "Banniere du gabarit", commune aux gabarits sur mesure DiTL.
 *
 * Contrat de metas (FIGE - les templates de page-templates/ s'appuient dessus) :
 * - _ditl_hero_image_id (int)    : image de banniere (ID d'attachement).
 * - _ditl_hero_title    (string) : titre H1 de la banniere.
 *
 * La metabox est affichee des que le modele de page selectionne est l'un
 * des gabarits DiTL du registre ditl_gabarits_templates() (bascule geree
 * en JS, editeur classique et Gutenberg). Chaque gabarit garde par ailleurs
 * sa propre metabox pour ses champs specifiques (ex. projet-ditl.php).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Valeur de _wp_page_template du gabarit "Actualites".
define( 'DITL_TPL_ACTUALITES', 'page-templates/actualites.php' );

/**
 * Registre des gabarits DiTL sur mesure (valeurs de _wp_page_template).
 *
 * Tout gabarit ajoute ici recoit automatiquement la metabox "Banniere du
 * gabarit" et l'enregistrement des deux metas de banniere.
 *
 * @return string[] Liste des modeles de page des gabarits DiTL.
 */
function ditl_gabarits_templates() {
	return array(
		DITL_TPL_PROJET_DITL,
		DITL_TPL_ACTUALITES,
		DITL_TPL_RESULTATS,
		DITL_TPL_ACCUEIL,
		DITL_TPL_PARTENAIRES,
		DITL_TPL_CONTACT,
		DITL_TPL_LIVRABLE,
	);
}

/**
 * Empeche Polylang de propager les gabarits DiTL aux traductions.
 *
 * Le reglage Polylang du site synchronise _wp_page_template entre les
 * langues. Or la migration des gabarits est progressive : quand une page
 * FR/EN recoit un gabarit DiTL, ses traductions ES/PT/DE n'ont pas encore
 * les metas _ditl_* (hors synchronisation, car protegees) - la propagation
 * du seul template rendrait leur contenu vide. Constate en local sur 6
 * pages (05/08/2026), et se reproduirait a chaque rejeu des migrations en
 * preprod/prod : le template d'un gabarit DiTL n'est donc jamais propage.
 * La synchronisation redevient normale pour tout autre modele de page.
 * Garde a retirer en phase 2, quand toutes les langues seront migrees
 * (et Polylang remplace par WPML).
 *
 * @param string[] $metas Cles de metas que Polylang s'apprete a copier.
 * @param bool     $sync  True pour une synchronisation, false pour une copie.
 * @param int      $from  ID du contenu source.
 * @return string[] Cles filtrees.
 */
function ditl_gabarits_bloquer_sync_template( $metas, $sync, $from ) {
	if ( is_array( $metas )
		&& in_array( '_wp_page_template', $metas, true )
		&& in_array( get_page_template_slug( $from ), ditl_gabarits_templates(), true ) ) {
		$metas = array_values( array_diff( $metas, array( '_wp_page_template' ) ) );
	}

	return $metas;
}
add_filter( 'pll_copy_post_metas', 'ditl_gabarits_bloquer_sync_template', 10, 3 );

/**
 * Declare les metas de banniere communes aux gabarits (protegees).
 */
function ditl_banniere_register_meta() {
	register_post_meta(
		'page',
		'_ditl_hero_image_id',
		array(
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);

	register_post_meta(
		'page',
		'_ditl_hero_title',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', 'ditl_banniere_register_meta' );

/**
 * Ajoute la metabox banniere sur l'ecran d'edition des pages.
 */
function ditl_banniere_add_metabox() {
	add_meta_box(
		'ditl-banniere',
		__( 'Banniere du gabarit', 'ditl' ),
		'ditl_banniere_render_metabox',
		'page',
		'normal',
		'high',
		array( '__block_editor_compatible_meta_box' => true )
	);
}
add_action( 'add_meta_boxes_page', 'ditl_banniere_add_metabox' );

/**
 * Affiche les champs de la metabox banniere.
 *
 * @param WP_Post $post Page en cours d'edition.
 */
function ditl_banniere_render_metabox( $post ) {
	wp_nonce_field( 'ditl_banniere_save_' . $post->ID, 'ditl_banniere_nonce' );

	$hero_image_id = absint( get_post_meta( $post->ID, '_ditl_hero_image_id', true ) );
	$hero_title    = (string) get_post_meta( $post->ID, '_ditl_hero_title', true );
	?>
	<div class="ditl-metabox">
		<p class="description">
			<?php esc_html_e( 'Ces champs alimentent la banniere commune aux gabarits DiTL. Ils ne sont utilises que lorsqu\'un gabarit DiTL est selectionne comme modele de page.', 'ditl' ); ?>
		</p>

		<div class="ditl-field">
			<span class="ditl-field-label"><?php esc_html_e( 'Image de banniere', 'ditl' ); ?></span>
			<?php ditl_metabox_render_media_field( 'ditl_hero_image_id', $hero_image_id, 3 ); ?>
		</div>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-hero-title"><?php esc_html_e( 'Titre de la banniere (H1)', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-hero-title" name="ditl_hero_title" value="<?php echo esc_attr( $hero_title ); ?>" />
		</div>
	</div>
	<?php
}

/**
 * Enregistre les champs de la metabox banniere.
 *
 * @param int $post_id ID de la page enregistree.
 */
function ditl_banniere_save_metabox( $post_id ) {
	if ( ! ditl_metabox_peut_enregistrer( $post_id, 'ditl_banniere_nonce', 'ditl_banniere_save_' . $post_id ) ) {
		return;
	}

	// La metabox est rendue (masquee) sur toutes les pages : on n'ecrit les
	// metas que si un gabarit DiTL est reellement selectionne, sans les
	// effacer quand la page passe temporairement sur un autre modele.
	if ( ! in_array( get_page_template_slug( $post_id ), ditl_gabarits_templates(), true ) ) {
		return;
	}

	// Image de banniere.
	$hero_image_id = isset( $_POST['ditl_hero_image_id'] ) && is_scalar( $_POST['ditl_hero_image_id'] ) ? absint( wp_unslash( $_POST['ditl_hero_image_id'] ) ) : 0;
	update_post_meta( $post_id, '_ditl_hero_image_id', $hero_image_id );

	// Titre de la banniere (texte simple).
	$hero_title = isset( $_POST['ditl_hero_title'] ) ? sanitize_text_field( wp_unslash( $_POST['ditl_hero_title'] ) ) : '';
	update_post_meta( $post_id, '_ditl_hero_title', wp_slash( $hero_title ) );
}
add_action( 'save_post_page', 'ditl_banniere_save_metabox' );

/**
 * Charge les assets admin des metaboxes de gabarits (ecran d'edition de page).
 *
 * Assets communs a toutes les metaboxes de gabarits DiTL : le JS gere
 * l'affichage conditionnel selon le registre transmis via ditlMetabox.
 *
 * @param string $hook_suffix Ecran admin courant.
 */
function ditl_gabarits_admin_assets( $hook_suffix ) {
	if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || 'page' !== $screen->post_type ) {
		return;
	}

	// Selecteur de medias + editeur riche dynamique (wp.editor.initialize).
	wp_enqueue_media();
	wp_enqueue_editor();

	wp_enqueue_style(
		'ditl-admin-metabox',
		get_stylesheet_directory_uri() . '/assets/admin/metabox-gabarits.css',
		array(),
		DITL_THEME_VERSION
	);

	wp_enqueue_script(
		'ditl-admin-metabox',
		get_stylesheet_directory_uri() . '/assets/admin/metabox-gabarits.js',
		array( 'jquery' ),
		DITL_THEME_VERSION,
		true
	);

	wp_localize_script(
		'ditl-admin-metabox',
		'ditlMetabox',
		array(
			// Metaboxes a afficher selon le modele de page selectionne :
			// id de metabox => liste des modeles qui la declenchent.
			'metaboxes' => array(
				'ditl-banniere'    => ditl_gabarits_templates(),
				'ditl-projet-ditl' => array( DITL_TPL_PROJET_DITL ),
				'ditl-resultats'   => array( DITL_TPL_RESULTATS ),
				'ditl-accueil'     => array( DITL_TPL_ACCUEIL ),
				'ditl-partenaires' => array( DITL_TPL_PARTENAIRES ),
				'ditl-contact'     => array( DITL_TPL_CONTACT ),
				'ditl-livrable'    => array( DITL_TPL_LIVRABLE ),
			),
			'i18n'      => array(
				'chooseImage'  => __( 'Choisir une image', 'ditl' ),
				'chooseImages' => __( 'Choisir des images', 'ditl' ),
				'useSelection' => __( 'Utiliser cette selection', 'ditl' ),
				/* translators: %d : numero d'ordre de la section. */
				'sectionLabel' => __( 'Section %d', 'ditl' ),
				// Barre d'outils des lignes repetables (injectee par le JS).
				'rowMoveUp'    => __( 'Monter la ligne', 'ditl' ),
				'rowMoveDown'  => __( 'Descendre la ligne', 'ditl' ),
				'rowRemove'    => __( 'Supprimer', 'ditl' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'ditl_gabarits_admin_assets' );
