<?php
/**
 * Metabox du gabarit "Projet DiTL" (pilote de la refonte sans Elementor).
 *
 * Contrat de metas (FIGE - le template page-templates/projet-ditl.php s'appuie dessus) :
 * - _ditl_intro_title   (string) : titre H2 d'introduction.
 * - _ditl_sections      (string) : JSON [{title, content}] - titre H3 texte simple, contenu HTML riche.
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
			<div class="ditl-sections" id="ditl-sections">
				<?php foreach ( $sections as $index => $section ) : ?>
					<div class="ditl-section">
						<div class="ditl-section-toolbar">
							<?php /* translators: %d : numero d'ordre de la section. */ ?>
						<span class="ditl-section-numero"><?php echo esc_html( sprintf( __( 'Section %d', 'ditl' ), $index + 1 ) ); ?></span>
							<button type="button" class="button ditl-section-up" title="<?php esc_attr_e( 'Monter la section', 'ditl' ); ?>">&uarr;</button>
							<button type="button" class="button ditl-section-down" title="<?php esc_attr_e( 'Descendre la section', 'ditl' ); ?>">&darr;</button>
							<button type="button" class="button ditl-section-remove"><?php esc_html_e( 'Supprimer', 'ditl' ); ?></button>
						</div>
						<label>
							<span class="ditl-field-label"><?php esc_html_e( 'Titre de la section (H3)', 'ditl' ); ?></span>
							<input type="text" class="widefat" name="ditl_sections_title[]" value="<?php echo esc_attr( isset( $section['title'] ) ? $section['title'] : '' ); ?>" />
						</label>
						<span class="ditl-field-label"><?php esc_html_e( 'Contenu de la section', 'ditl' ); ?></span>
						<textarea class="ditl-section-editor" id="ditl-section-content-<?php echo esc_attr( $index ); ?>" name="ditl_sections_content[]" rows="8"><?php echo esc_textarea( isset( $section['content'] ) ? $section['content'] : '' ); ?></textarea>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-secondary" id="ditl-section-add"><?php esc_html_e( 'Ajouter une section', 'ditl' ); ?></button>
		</div>

		<div class="ditl-field">
			<span class="ditl-field-label"><?php esc_html_e( 'Galerie d\'images (carrousel)', 'ditl' ); ?></span>
			<div class="ditl-gallery-field">
				<input type="hidden" name="ditl_carousel_ids" class="ditl-gallery-value" value="<?php echo esc_attr( (string) wp_json_encode( $carousel_ids ) ); ?>" />
				<ul class="ditl-gallery-preview">
					<?php foreach ( $carousel_ids as $attachment_id ) : ?>
						<li data-id="<?php echo esc_attr( $attachment_id ); ?>">
							<?php echo wp_get_attachment_image( $attachment_id, 'thumbnail' ); ?>
							<button type="button" class="button-link ditl-gallery-item-remove" title="<?php esc_attr_e( 'Retirer cette image', 'ditl' ); ?>">&times;</button>
						</li>
					<?php endforeach; ?>
				</ul>
				<button type="button" class="button ditl-gallery-choose"><?php esc_html_e( 'Choisir des images', 'ditl' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Galerie optionnelle affichee en bas de page. Laisser vide pour ne pas afficher de carrousel.', 'ditl' ); ?></p>
		</div>
	</div>

	<script type="text/html" id="tmpl-ditl-section">
		<div class="ditl-section">
			<div class="ditl-section-toolbar">
				<span class="ditl-section-numero"><?php esc_html_e( 'Section', 'ditl' ); ?></span>
				<button type="button" class="button ditl-section-up" title="<?php esc_attr_e( 'Monter la section', 'ditl' ); ?>">&uarr;</button>
				<button type="button" class="button ditl-section-down" title="<?php esc_attr_e( 'Descendre la section', 'ditl' ); ?>">&darr;</button>
				<button type="button" class="button ditl-section-remove"><?php esc_html_e( 'Supprimer', 'ditl' ); ?></button>
			</div>
			<label>
				<span class="ditl-field-label"><?php esc_html_e( 'Titre de la section (H3)', 'ditl' ); ?></span>
				<input type="text" class="widefat" name="ditl_sections_title[]" value="" />
			</label>
			<span class="ditl-field-label"><?php esc_html_e( 'Contenu de la section', 'ditl' ); ?></span>
			<textarea class="ditl-section-editor" id="ditl-section-content-%index%" name="ditl_sections_content[]" rows="8"></textarea>
		</div>
	</script>
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

	// Sections : deux tableaux paralleles, l'ordre du DOM fait foi.
	$titles   = isset( $_POST['ditl_sections_title'] ) ? array_values( (array) wp_unslash( $_POST['ditl_sections_title'] ) ) : array();
	$contents = isset( $_POST['ditl_sections_content'] ) ? array_values( (array) wp_unslash( $_POST['ditl_sections_content'] ) ) : array();
	$total    = max( count( $titles ), count( $contents ) );
	$sections = array();

	for ( $i = 0; $i < $total; $i++ ) {
		$title   = isset( $titles[ $i ] ) && is_string( $titles[ $i ] ) ? sanitize_text_field( $titles[ $i ] ) : '';
		$content = isset( $contents[ $i ] ) && is_string( $contents[ $i ] ) ? wp_kses_post( $contents[ $i ] ) : '';

		// Les lignes entierement vides sont ignorees.
		if ( '' === $title && '' === trim( wp_strip_all_tags( $content ) ) ) {
			continue;
		}

		$sections[] = array(
			'title'   => $title,
			'content' => $content,
		);
	}

	$sections_json = (string) wp_json_encode( $sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	update_post_meta( $post_id, '_ditl_sections', wp_slash( $sections_json ) );

	// Galerie : liste d'IDs transmise en JSON par le selecteur de medias.
	$carousel_raw = isset( $_POST['ditl_carousel_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ditl_carousel_ids'] ) ) : '[]';
	update_post_meta( $post_id, '_ditl_carousel_ids', wp_slash( ditl_sanitize_ids_json( $carousel_raw ) ) );
}
add_action( 'save_post_page', 'ditl_projet_ditl_save_metabox' );
