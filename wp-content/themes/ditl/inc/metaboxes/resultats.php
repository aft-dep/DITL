<?php
/**
 * Metabox du gabarit "Resultats".
 *
 * Contrat de metas (FIGE - le template page-templates/resultats.php s'appuie dessus) :
 * - _ditl_intro_content        (string) : texte d'introduction, HTML riche.
 * - _ditl_sections             (string) : JSON [{title, content}] - meta PARTAGEE avec le
 *                                         gabarit Projet DiTL (declaree par projet-ditl.php).
 *                                         Ici : title = H2 d'activite, content = HTML riche
 *                                         pouvant contenir des sous-titres <h3>.
 * - _ditl_bandeau_image_id     (int)    : image de fond du bandeau de mise en avant.
 * - _ditl_bandeau_texte        (string) : texte du bandeau, HTML riche.
 * - _ditl_bandeau_bouton_texte (string) : libelle du bouton du bandeau.
 * - _ditl_bandeau_bouton_url   (string) : URL du bouton (relative de preference).
 *
 * Le bandeau est optionnel : champs vides = bloc non affiche en front.
 *
 * Les metas de banniere (_ditl_hero_image_id, _ditl_hero_title) sont gerees
 * par la metabox commune inc/metaboxes/banniere.php.
 *
 * La metabox n'est visible que lorsque le modele de page "Resultats"
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
define( 'DITL_TPL_RESULTATS', 'page-templates/resultats.php' );

/**
 * Declare les metas du gabarit (protegees, avec controle d'acces).
 *
 * La meta _ditl_sections n'est pas redeclaree ici : elle est deja
 * enregistree par projet-ditl.php (meme format, meme sanitize).
 */
function ditl_resultats_register_meta() {
	register_post_meta(
		'page',
		'_ditl_intro_content',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'sanitize_callback' => 'wp_kses_post',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);

	register_post_meta(
		'page',
		'_ditl_bandeau_image_id',
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
		'_ditl_bandeau_texte',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'sanitize_callback' => 'wp_kses_post',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);

	register_post_meta(
		'page',
		'_ditl_bandeau_bouton_texte',
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
		'_ditl_bandeau_bouton_url',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', 'ditl_resultats_register_meta' );

/**
 * Ajoute la metabox sur l'ecran d'edition des pages.
 */
function ditl_resultats_add_metabox() {
	add_meta_box(
		'ditl-resultats',
		__( 'Contenu du gabarit Resultats', 'ditl' ),
		'ditl_resultats_render_metabox',
		'page',
		'normal',
		'high',
		array( '__block_editor_compatible_meta_box' => true )
	);
}
add_action( 'add_meta_boxes_page', 'ditl_resultats_add_metabox' );

/**
 * Affiche les champs de la metabox.
 *
 * @param WP_Post $post Page en cours d'edition.
 */
function ditl_resultats_render_metabox( $post ) {
	wp_nonce_field( 'ditl_resultats_save_' . $post->ID, 'ditl_resultats_nonce' );

	$intro_content       = (string) get_post_meta( $post->ID, '_ditl_intro_content', true );
	$bandeau_image_id    = absint( get_post_meta( $post->ID, '_ditl_bandeau_image_id', true ) );
	$bandeau_texte       = (string) get_post_meta( $post->ID, '_ditl_bandeau_texte', true );
	$bandeau_bouton_txt  = (string) get_post_meta( $post->ID, '_ditl_bandeau_bouton_texte', true );
	$bandeau_bouton_url  = (string) get_post_meta( $post->ID, '_ditl_bandeau_bouton_url', true );
	?>
	<div class="ditl-metabox">
		<p class="description">
			<?php esc_html_e( 'Ces champs alimentent le gabarit "Resultats". Ils ne sont utilises que lorsque ce modele de page est selectionne. La banniere se regle dans la metabox "Banniere du gabarit".', 'ditl' ); ?>
		</p>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-resultats-intro"><?php esc_html_e( 'Texte d\'introduction', 'ditl' ); ?></label>
			<textarea class="ditl-richtext-editor" id="ditl-resultats-intro" name="ditl_resultats_intro" rows="6"><?php echo esc_textarea( $intro_content ); ?></textarea>
		</div>

		<div class="ditl-field">
			<span class="ditl-field-label"><?php esc_html_e( 'Sections de contenu', 'ditl' ); ?></span>
			<?php
			// Champ partage entre gabarits (markup et JS communs, helpers.php).
			// Meme meta _ditl_sections que le gabarit Projet, mais prefixe de
			// champs distinct : les deux metaboxes coexistent sur l'ecran.
			ditl_metabox_render_sections(
				$post->ID,
				array(
					'meta_key'      => '_ditl_sections',
					'prefix'        => 'ditl_resultats_sections',
					'title_label'   => __( 'Titre de la section (H2)', 'ditl' ),
					'content_label' => __( 'Contenu de la section', 'ditl' ),
				)
			);
			?>
			<p class="description"><?php esc_html_e( 'Le contenu d\'une section peut inclure des sous-titres : utiliser le format "Titre 3" dans l\'editeur.', 'ditl' ); ?></p>
		</div>

		<div class="ditl-field">
			<span class="ditl-field-label"><?php esc_html_e( 'Bandeau de mise en avant', 'ditl' ); ?></span>
			<p class="description"><?php esc_html_e( 'Bloc optionnel affiche en bas de page. Laisser tous les champs vides pour ne pas l\'afficher.', 'ditl' ); ?></p>

			<div class="ditl-field">
				<span class="ditl-field-label"><?php esc_html_e( 'Image de fond du bandeau', 'ditl' ); ?></span>
				<?php ditl_metabox_render_media_field( 'ditl_resultats_bandeau_image_id', $bandeau_image_id, 4 ); ?>
			</div>

			<div class="ditl-field">
				<label class="ditl-field-label" for="ditl-resultats-bandeau-texte"><?php esc_html_e( 'Texte du bandeau', 'ditl' ); ?></label>
				<textarea class="ditl-richtext-editor" id="ditl-resultats-bandeau-texte" name="ditl_resultats_bandeau_texte" rows="5"><?php echo esc_textarea( $bandeau_texte ); ?></textarea>
			</div>

			<div class="ditl-field">
				<label class="ditl-field-label" for="ditl-resultats-bandeau-bouton-texte"><?php esc_html_e( 'Libelle du bouton', 'ditl' ); ?></label>
				<input type="text" class="widefat" id="ditl-resultats-bandeau-bouton-texte" name="ditl_resultats_bandeau_bouton_texte" value="<?php echo esc_attr( $bandeau_bouton_txt ); ?>" />
			</div>

			<div class="ditl-field">
				<label class="ditl-field-label" for="ditl-resultats-bandeau-bouton-url"><?php esc_html_e( 'URL du bouton', 'ditl' ); ?></label>
				<input type="text" class="widefat" id="ditl-resultats-bandeau-bouton-url" name="ditl_resultats_bandeau_bouton_url" value="<?php echo esc_attr( $bandeau_bouton_url ); ?>" />
				<p class="description"><?php esc_html_e( 'Preferer une URL relative (ex. /ditl-project-3/) pour rester valable sur tous les environnements.', 'ditl' ); ?></p>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Enregistre les champs de la metabox.
 *
 * @param int $post_id ID de la page enregistree.
 */
function ditl_resultats_save_metabox( $post_id ) {
	if ( ! ditl_metabox_peut_enregistrer( $post_id, 'ditl_resultats_nonce', 'ditl_resultats_save_' . $post_id ) ) {
		return;
	}

	// La metabox est rendue (masquee) sur toutes les pages : on n'ecrit les
	// metas que si le gabarit est reellement selectionne, sans les effacer
	// quand la page passe temporairement sur un autre modele.
	if ( DITL_TPL_RESULTATS !== get_page_template_slug( $post_id ) ) {
		return;
	}

	// Texte d'introduction (HTML riche).
	$intro_content = isset( $_POST['ditl_resultats_intro'] ) && is_string( $_POST['ditl_resultats_intro'] ) ? wp_kses_post( wp_unslash( $_POST['ditl_resultats_intro'] ) ) : '';
	update_post_meta( $post_id, '_ditl_intro_content', wp_slash( $intro_content ) );

	// Sections repetables (lecture partagee, helpers.php).
	$sections      = ditl_metabox_lire_sections_post( 'ditl_resultats_sections' );
	$sections_json = (string) wp_json_encode( $sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	update_post_meta( $post_id, '_ditl_sections', wp_slash( $sections_json ) );

	// Bandeau de mise en avant (optionnel).
	$bandeau_image_id = isset( $_POST['ditl_resultats_bandeau_image_id'] ) && is_scalar( $_POST['ditl_resultats_bandeau_image_id'] ) ? absint( wp_unslash( $_POST['ditl_resultats_bandeau_image_id'] ) ) : 0;
	update_post_meta( $post_id, '_ditl_bandeau_image_id', $bandeau_image_id );

	$bandeau_texte = isset( $_POST['ditl_resultats_bandeau_texte'] ) && is_string( $_POST['ditl_resultats_bandeau_texte'] ) ? wp_kses_post( wp_unslash( $_POST['ditl_resultats_bandeau_texte'] ) ) : '';
	update_post_meta( $post_id, '_ditl_bandeau_texte', wp_slash( $bandeau_texte ) );

	$bandeau_bouton_texte = isset( $_POST['ditl_resultats_bandeau_bouton_texte'] ) && is_string( $_POST['ditl_resultats_bandeau_bouton_texte'] ) ? sanitize_text_field( wp_unslash( $_POST['ditl_resultats_bandeau_bouton_texte'] ) ) : '';
	update_post_meta( $post_id, '_ditl_bandeau_bouton_texte', wp_slash( $bandeau_bouton_texte ) );

	$bandeau_bouton_url = isset( $_POST['ditl_resultats_bandeau_bouton_url'] ) && is_string( $_POST['ditl_resultats_bandeau_bouton_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['ditl_resultats_bandeau_bouton_url'] ) ) ) : '';
	update_post_meta( $post_id, '_ditl_bandeau_bouton_url', wp_slash( $bandeau_bouton_url ) );
}
add_action( 'save_post_page', 'ditl_resultats_save_metabox' );
