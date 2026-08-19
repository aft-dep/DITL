<?php
/**
 * Metabox du gabarit "Projet DiTL" (pilote de la refonte sans Elementor).
 *
 * Contrat de metas (FIGE - le template page-templates/projet-ditl.php s'appuie dessus) :
 * - _ditl_intro_title   (string) : titre H2 d'introduction.
 * - _ditl_sections      (string) : JSON [{title, content}] - titre H3 texte simple, contenu HTML riche.
 *                                  Meta PARTAGEE avec le gabarit Resultats (resultats.php), qui
 *                                  l'exploite avec un titre de niveau H2 (une seule des deux
 *                                  metaboxes ecrit, selon le modele de page selectionne).
 * - _ditl_carousel_ids  (string) : JSON [int] - IDs d'attachements de la galerie (peut etre vide).
 *
 * Les metas de banniere (_ditl_hero_image_id, _ditl_hero_title) sont gerees
 * par la metabox commune inc/metaboxes/banniere.php.
 *
 * La metabox n'est visible que lorsque le modele de page "Projet DiTL"
 * est selectionne (bascule geree en JS, editeur classique et Gutenberg).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Valeur de _wp_page_template declenchant l'affichage de la metabox.
define( 'DITL_TPL_PROJET_DITL', 'page-templates/projet-ditl.php' );

/**
 * Declare les metas du gabarit (protegees, avec controle d'acces).
 */
function ditl_projet_ditl_register_meta() {
	register_post_meta(
		'page',
		'_ditl_intro_title',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);

	register_post_meta(
		'page',
		'_ditl_sections',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '[]',
			'sanitize_callback' => 'ditl_sanitize_sections_json',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);

	register_post_meta(
		'page',
		'_ditl_carousel_ids',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '[]',
			'sanitize_callback' => 'ditl_sanitize_ids_json',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', 'ditl_projet_ditl_register_meta' );

/**
 * Ajoute la metabox sur l'ecran d'edition des pages.
 */
function ditl_projet_ditl_add_metabox() {
	add_meta_box(
		'ditl-projet-ditl',
		__( 'Contenu du gabarit Projet DiTL', 'ditl' ),
		'ditl_projet_ditl_render_metabox',
		'page',
		'normal',
		'high',
		array( '__block_editor_compatible_meta_box' => true )
	);
}
add_action( 'add_meta_boxes_page', 'ditl_projet_ditl_add_metabox' );

/**
 * Affiche les champs de la metabox.
 *
 * @param WP_Post $post Page en cours d'edition.
 */
function ditl_projet_ditl_render_metabox( $post ) {
	wp_nonce_field( 'ditl_projet_ditl_save_' . $post->ID, 'ditl_projet_ditl_nonce' );

	$intro_title  = (string) get_post_meta( $post->ID, '_ditl_intro_title', true );
	$sections     = ditl_get_meta_json( $post->ID, '_ditl_sections' );
	$carousel_ids = ditl_get_meta_json( $post->ID, '_ditl_carousel_ids' );
	?>
	<div class="ditl-metabox">
		<p class="description">
			<?php esc_html_e( 'Ces champs alimentent le gabarit "Projet DiTL". Ils ne sont utilises que lorsque ce modele de page est selectionne. La banniere se regle dans la metabox "Banniere du gabarit".', 'ditl' ); ?>
		</p>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-intro-title"><?php esc_html_e( 'Titre d\'introduction (H2)', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-intro-title" name="ditl_intro_title" value="<?php echo esc_attr( $intro_title ); ?>" />
		</div>

		<div class="ditl-field">
			<span class="ditl-field-label"><?php esc_html_e( 'Sections de contenu', 'ditl' ); ?></span>
			<?php
			// Champ partage entre gabarits (markup et JS communs, helpers.php).
			ditl_metabox_render_sections(
				$post->ID,
				array(
					'meta_key'      => '_ditl_sections',
					'prefix'        => 'ditl_sections',
					'title_label'   => __( 'Titre de la section (H3)', 'ditl' ),
					'content_label' => __( 'Contenu de la section', 'ditl' ),
				)
			);
			?>
		</div>

		<div class="ditl-field">
			<span class="ditl-field-label"><?php esc_html_e( 'Galerie d\'images (carrousel)', 'ditl' ); ?></span>
			<?php ditl_metabox_render_gallery_field( 'ditl_carousel_ids', $carousel_ids, 3 ); ?>
			<p class="description"><?php esc_html_e( 'Galerie optionnelle affichee en bas de page. Laisser vide pour ne pas afficher de carrousel.', 'ditl' ); ?></p>
		</div>
	</div>
	<?php
}

/**
 * Enregistre les champs de la metabox.
 *
 * @param int $post_id ID de la page enregistree.
 */
function ditl_projet_ditl_save_metabox( $post_id ) {
	if ( ! ditl_metabox_peut_enregistrer( $post_id, 'ditl_projet_ditl_nonce', 'ditl_projet_ditl_save_' . $post_id ) ) {
		return;
	}

	// La metabox est rendue (masquee) sur toutes les pages : on n'ecrit les
	// metas que si le gabarit est reellement selectionne, sans les effacer
	// quand la page passe temporairement sur un autre modele.
	if ( DITL_TPL_PROJET_DITL !== get_page_template_slug( $post_id ) ) {
		return;
	}

	// Titre d'introduction (texte simple).
	$intro_title = isset( $_POST['ditl_intro_title'] ) ? sanitize_text_field( wp_unslash( $_POST['ditl_intro_title'] ) ) : '';
	update_post_meta( $post_id, '_ditl_intro_title', wp_slash( $intro_title ) );

	// Sections repetables (lecture partagee, helpers.php).
	$sections      = ditl_metabox_lire_sections_post( 'ditl_sections' );
	$sections_json = (string) wp_json_encode( $sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	update_post_meta( $post_id, '_ditl_sections', wp_slash( $sections_json ) );

	// Galerie : liste d'IDs transmise en JSON par le selecteur de medias.
	$carousel_raw = isset( $_POST['ditl_carousel_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ditl_carousel_ids'] ) ) : '[]';
	update_post_meta( $post_id, '_ditl_carousel_ids', wp_slash( ditl_sanitize_ids_json( $carousel_raw ) ) );
}
add_action( 'save_post_page', 'ditl_projet_ditl_save_metabox' );
