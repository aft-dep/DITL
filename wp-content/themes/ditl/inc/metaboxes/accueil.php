<?php
/**
 * Metabox du gabarit "Accueil".
 *
 * Contrat de metas (FIGE - le template page-templates/accueil.php s'appuie dessus).
 * Toutes les metas sont des objets JSON structures :
 * - _ditl_accueil_hero         (string) : {sous_titre, bouton_texte, bouton_url}
 *                                         Complements de la banniere commune : sous-titre H2
 *                                         affiche sous le H1 et bouton d'appel a l'action.
 * - _ditl_accueil_presentation (string) : {titre, texte, bouton_texte, bouton_url, image_id}
 *                                         titre = H2, texte = HTML riche, image_id = illustration.
 * - _ditl_accueil_livrables    (string) : {titre, intro, items: [{image_id, texte}]}
 *                                         titre = H2, intro = HTML riche, items = vignettes
 *                                         repetables (icone + texte HTML riche).
 * - _ditl_accueil_actualites   (string) : {titre}
 *                                         titre = H2. La liste d'articles est dynamique
 *                                         (derniers posts), aucune donnee stockee ici.
 * - _ditl_accueil_partenaires  (string) : {titre, texte, bouton_texte, bouton_url,
 *                                          logo_ids: [int], carrousel: bool}
 *                                         logo_ids = galerie de logos, carrousel = true pour
 *                                         un affichage en carrousel, false pour une grille.
 *
 * Les URLs de boutons sont stockees relatives de preference (valables sur
 * tous les environnements) ; les URLs externes restent absolues.
 *
 * Les metas de banniere (_ditl_hero_image_id, _ditl_hero_title) sont gerees
 * par la metabox commune inc/metaboxes/banniere.php.
 *
 * La metabox n'est visible que lorsque le modele de page "Accueil"
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
define( 'DITL_TPL_ACCUEIL', 'page-templates/accueil.php' );

/**
 * Nettoie l'objet JSON {sous_titre, bouton_texte, bouton_url} du hero.
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (objet aux cles garanties).
 */
function ditl_accueil_sanitize_hero_json( $value ) {
	$data = json_decode( (string) $value, true );

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$propre = array(
		'sous_titre'   => isset( $data['sous_titre'] ) ? sanitize_text_field( (string) $data['sous_titre'] ) : '',
		'bouton_texte' => isset( $data['bouton_texte'] ) ? sanitize_text_field( (string) $data['bouton_texte'] ) : '',
		'bouton_url'   => isset( $data['bouton_url'] ) ? esc_url_raw( trim( (string) $data['bouton_url'] ) ) : '',
	);

	return (string) wp_json_encode( $propre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Nettoie l'objet JSON du bloc Presentation.
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (objet aux cles garanties).
 */
function ditl_accueil_sanitize_presentation_json( $value ) {
	$data = json_decode( (string) $value, true );

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$propre = array(
		'titre'        => isset( $data['titre'] ) ? sanitize_text_field( (string) $data['titre'] ) : '',
		'texte'        => isset( $data['texte'] ) ? wp_kses_post( (string) $data['texte'] ) : '',
		'bouton_texte' => isset( $data['bouton_texte'] ) ? sanitize_text_field( (string) $data['bouton_texte'] ) : '',
		'bouton_url'   => isset( $data['bouton_url'] ) ? esc_url_raw( trim( (string) $data['bouton_url'] ) ) : '',
		'image_id'     => isset( $data['image_id'] ) ? absint( $data['image_id'] ) : 0,
	);

	return (string) wp_json_encode( $propre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Nettoie l'objet JSON du bloc Livrables (vignettes repetables incluses).
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (objet aux cles garanties).
 */
function ditl_accueil_sanitize_livrables_json( $value ) {
	$data = json_decode( (string) $value, true );

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$items  = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
	$propres = array();

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$image_id = isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0;
		$texte    = isset( $item['texte'] ) ? wp_kses_post( (string) $item['texte'] ) : '';

		// Les vignettes entierement vides sont ignorees.
		if ( 0 === $image_id && '' === trim( wp_strip_all_tags( $texte ) ) ) {
			continue;
		}

		$propres[] = array(
			'image_id' => $image_id,
			'texte'    => $texte,
		);

		// Garde-fou contre une liste anormalement gonflee.
		if ( count( $propres ) >= 100 ) {
			break;
		}
	}

	$propre = array(
		'titre' => isset( $data['titre'] ) ? sanitize_text_field( (string) $data['titre'] ) : '',
		'intro' => isset( $data['intro'] ) ? wp_kses_post( (string) $data['intro'] ) : '',
		'items' => $propres,
	);

	return (string) wp_json_encode( $propre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Nettoie l'objet JSON {titre} du bloc Actualites.
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (objet aux cles garanties).
 */
function ditl_accueil_sanitize_actualites_json( $value ) {
	$data = json_decode( (string) $value, true );

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$propre = array(
		'titre' => isset( $data['titre'] ) ? sanitize_text_field( (string) $data['titre'] ) : '',
	);

	return (string) wp_json_encode( $propre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Nettoie l'objet JSON du bloc Partenaires (galerie de logos incluse).
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (objet aux cles garanties).
 */
function ditl_accueil_sanitize_partenaires_json( $value ) {
	$data = json_decode( (string) $value, true );

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$logo_ids = array();

	if ( isset( $data['logo_ids'] ) && is_array( $data['logo_ids'] ) ) {
		foreach ( $data['logo_ids'] as $logo_id ) {
			$logo_id = absint( $logo_id );

			if ( $logo_id > 0 ) {
				$logo_ids[] = $logo_id;
			}
		}
	}

	$propre = array(
		'titre'        => isset( $data['titre'] ) ? sanitize_text_field( (string) $data['titre'] ) : '',
		'texte'        => isset( $data['texte'] ) ? wp_kses_post( (string) $data['texte'] ) : '',
		'bouton_texte' => isset( $data['bouton_texte'] ) ? sanitize_text_field( (string) $data['bouton_texte'] ) : '',
		'bouton_url'   => isset( $data['bouton_url'] ) ? esc_url_raw( trim( (string) $data['bouton_url'] ) ) : '',
		'logo_ids'     => array_values( $logo_ids ),
		'carrousel'    => ! empty( $data['carrousel'] ),
	);

	return (string) wp_json_encode( $propre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Declare les metas du gabarit (protegees, avec controle d'acces).
 */
function ditl_accueil_register_meta() {
	$metas = array(
		'_ditl_accueil_hero'         => 'ditl_accueil_sanitize_hero_json',
		'_ditl_accueil_presentation' => 'ditl_accueil_sanitize_presentation_json',
		'_ditl_accueil_livrables'    => 'ditl_accueil_sanitize_livrables_json',
		'_ditl_accueil_actualites'   => 'ditl_accueil_sanitize_actualites_json',
		'_ditl_accueil_partenaires'  => 'ditl_accueil_sanitize_partenaires_json',
	);

	foreach ( $metas as $meta_key => $sanitize_callback ) {
		register_post_meta(
			'page',
			$meta_key,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '{}',
				'sanitize_callback' => $sanitize_callback,
				'auth_callback'     => 'ditl_meta_auth_callback',
				'show_in_rest'      => false,
			)
		);
	}
}
add_action( 'init', 'ditl_accueil_register_meta' );

/**
 * Ajoute la metabox sur l'ecran d'edition des pages.
 */
function ditl_accueil_add_metabox() {
	add_meta_box(
		'ditl-accueil',
		__( 'Contenu du gabarit Accueil', 'ditl' ),
		'ditl_accueil_render_metabox',
		'page',
		'normal',
		'high',
		array( '__block_editor_compatible_meta_box' => true )
	);
}
add_action( 'add_meta_boxes_page', 'ditl_accueil_add_metabox' );

/**
 * Affiche une ligne de vignette Livrables (ligne existante ou modele JS).
 *
 * Reutilise le markup des sections repetables (.ditl-section) : ajout,
 * suppression, tri, barre d'outils et editeurs riches sont geres par le JS
 * commun (assets/admin/metabox-gabarits.js), le selecteur de media par la
 * mecanique .ditl-media-field partagee.
 *
 * @param string|int $index Index de la ligne (ou "%index%" pour le modele).
 * @param array      $item  Donnees {image_id, texte} de la ligne.
 */
function ditl_accueil_render_livrable_row( $index, $item = array() ) {
	$image_id = isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0;
	$texte    = isset( $item['texte'] ) ? (string) $item['texte'] : '';
	?>
	<div class="ditl-section">
		<span class="ditl-field-label"><?php esc_html_e( 'Icone de la vignette', 'ditl' ); ?></span>
		<div class="ditl-media-field">
			<input type="hidden" name="ditl_accueil_livrable_image_id[]" class="ditl-media-value" value="<?php echo esc_attr( $image_id ? $image_id : '' ); ?>" />
			<div class="ditl-media-preview">
				<?php
				if ( $image_id ) {
					echo wp_get_attachment_image( $image_id, 'medium' );
				}
				?>
			</div>
			<button type="button" class="button ditl-media-choose"><?php esc_html_e( 'Choisir une image', 'ditl' ); ?></button>
			<button type="button" class="button ditl-media-remove"<?php echo $image_id ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Retirer l\'image', 'ditl' ); ?></button>
		</div>
		<span class="ditl-field-label"><?php esc_html_e( 'Texte de la vignette', 'ditl' ); ?></span>
		<textarea class="ditl-section-editor" id="<?php echo esc_attr( 'ditl-accueil-livrable-texte-' . $index ); ?>" name="ditl_accueil_livrable_texte[]" rows="6"><?php echo esc_textarea( $texte ); ?></textarea>
	</div>
	<?php
}

/**
 * Affiche les champs de la metabox.
 *
 * @param WP_Post $post Page en cours d'edition.
 */
function ditl_accueil_render_metabox( $post ) {
	wp_nonce_field( 'ditl_accueil_save_' . $post->ID, 'ditl_accueil_nonce' );

	$hero         = ditl_get_meta_json( $post->ID, '_ditl_accueil_hero' );
	$presentation = ditl_get_meta_json( $post->ID, '_ditl_accueil_presentation' );
	$livrables    = ditl_get_meta_json( $post->ID, '_ditl_accueil_livrables' );
	$actualites   = ditl_get_meta_json( $post->ID, '_ditl_accueil_actualites' );
	$partenaires  = ditl_get_meta_json( $post->ID, '_ditl_accueil_partenaires' );

	$pres_image_id = isset( $presentation['image_id'] ) ? absint( $presentation['image_id'] ) : 0;
	$livr_items    = isset( $livrables['items'] ) && is_array( $livrables['items'] ) ? $livrables['items'] : array();
	$logo_ids      = array();

	if ( isset( $partenaires['logo_ids'] ) && is_array( $partenaires['logo_ids'] ) ) {
		foreach ( $partenaires['logo_ids'] as $logo_id ) {
			$logo_id = absint( $logo_id );

			if ( $logo_id > 0 ) {
				$logo_ids[] = $logo_id;
			}
		}
	}
	?>
	<div class="ditl-metabox">
		<p class="description">
			<?php esc_html_e( 'Ces champs alimentent le gabarit "Accueil". Ils ne sont utilises que lorsque ce modele de page est selectionne. L\'image et le titre H1 de la banniere se reglent dans la metabox "Banniere du gabarit".', 'ditl' ); ?>
		</p>

		<h3><?php esc_html_e( 'Banniere (complements)', 'ditl' ); ?></h3>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-accueil-hero-sous-titre"><?php esc_html_e( 'Sous-titre de la banniere (H2)', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-accueil-hero-sous-titre" name="ditl_accueil_hero_sous_titre" value="<?php echo esc_attr( isset( $hero['sous_titre'] ) ? $hero['sous_titre'] : '' ); ?>" />
		</div>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-accueil-hero-bouton-texte"><?php esc_html_e( 'Libelle du bouton de la banniere', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-accueil-hero-bouton-texte" name="ditl_accueil_hero_bouton_texte" value="<?php echo esc_attr( isset( $hero['bouton_texte'] ) ? $hero['bouton_texte'] : '' ); ?>" />
		</div>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-accueil-hero-bouton-url"><?php esc_html_e( 'URL du bouton de la banniere', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-accueil-hero-bouton-url" name="ditl_accueil_hero_bouton_url" value="<?php echo esc_attr( isset( $hero['bouton_url'] ) ? $hero['bouton_url'] : '' ); ?>" />
			<p class="description"><?php esc_html_e( 'Preferer une URL relative (ex. /ditl-project-3/) pour rester valable sur tous les environnements.', 'ditl' ); ?></p>
		</div>

		<h3><?php esc_html_e( 'Bloc Presentation', 'ditl' ); ?></h3>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-accueil-pres-titre"><?php esc_html_e( 'Titre du bloc (H2)', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-accueil-pres-titre" name="ditl_accueil_pres_titre" value="<?php echo esc_attr( isset( $presentation['titre'] ) ? $presentation['titre'] : '' ); ?>" />
		</div>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-accueil-pres-texte"><?php esc_html_e( 'Texte de presentation', 'ditl' ); ?></label>
			<textarea class="ditl-richtext-editor" id="ditl-accueil-pres-texte" name="ditl_accueil_pres_texte" rows="6"><?php echo esc_textarea( isset( $presentation['texte'] ) ? (string) $presentation['texte'] : '' ); ?></textarea>
		</div>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-accueil-pres-bouton-texte"><?php esc_html_e( 'Libelle du bouton', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-accueil-pres-bouton-texte" name="ditl_accueil_pres_bouton_texte" value="<?php echo esc_attr( isset( $presentation['bouton_texte'] ) ? $presentation['bouton_texte'] : '' ); ?>" />
		</div>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-accueil-pres-bouton-url"><?php esc_html_e( 'URL du bouton', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-accueil-pres-bouton-url" name="ditl_accueil_pres_bouton_url" value="<?php echo esc_attr( isset( $presentation['bouton_url'] ) ? $presentation['bouton_url'] : '' ); ?>" />
			<p class="description"><?php esc_html_e( 'Preferer une URL relative (ex. /ditl-project-3/) pour rester valable sur tous les environnements.', 'ditl' ); ?></p>
		</div>

		<div class="ditl-field">
			<span class="ditl-field-label"><?php esc_html_e( 'Image d\'illustration', 'ditl' ); ?></span>
			<div class="ditl-media-field">
				<input type="hidden" name="ditl_accueil_pres_image_id" class="ditl-media-value" value="<?php echo esc_attr( $pres_image_id ? $pres_image_id : '' ); ?>" />
				<div class="ditl-media-preview">
					<?php
					if ( $pres_image_id ) {
						echo wp_get_attachment_image( $pres_image_id, 'medium' );
					}
					?>
				</div>
				<button type="button" class="button ditl-media-choose"><?php esc_html_e( 'Choisir une image', 'ditl' ); ?></button>
				<button type="button" class="button ditl-media-remove"<?php echo $pres_image_id ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Retirer l\'image', 'ditl' ); ?></button>
			</div>
		</div>

		<h3><?php esc_html_e( 'Bloc Livrables', 'ditl' ); ?></h3>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-accueil-livr-titre"><?php esc_html_e( 'Titre du bloc (H2)', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-accueil-livr-titre" name="ditl_accueil_livr_titre" value="<?php echo esc_attr( isset( $livrables['titre'] ) ? $livrables['titre'] : '' ); ?>" />
		</div>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-accueil-livr-intro"><?php esc_html_e( 'Texte d\'introduction du bloc', 'ditl' ); ?></label>
			<textarea class="ditl-richtext-editor" id="ditl-accueil-livr-intro" name="ditl_accueil_livr_intro" rows="6"><?php echo esc_textarea( isset( $livrables['intro'] ) ? (string) $livrables['intro'] : '' ); ?></textarea>
		</div>

		<div class="ditl-field">
			<span class="ditl-field-label"><?php esc_html_e( 'Vignettes des livrables', 'ditl' ); ?></span>
			<div class="ditl-sections-field" data-row-label="<?php echo esc_attr( /* translators: %d : numero d'ordre de la vignette. */ __( 'Vignette %d', 'ditl' ) ); ?>">
				<div class="ditl-sections">
					<?php foreach ( $livr_items as $index => $item ) : ?>
						<?php ditl_accueil_render_livrable_row( $index, $item ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button button-secondary ditl-section-add"><?php esc_html_e( 'Ajouter une vignette', 'ditl' ); ?></button>
				<script type="text/html" class="ditl-section-template">
					<?php ditl_accueil_render_livrable_row( '%index%' ); ?>
				</script>
			</div>
		</div>

		<h3><?php esc_html_e( 'Bloc Actualites', 'ditl' ); ?></h3>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-accueil-actu-titre"><?php esc_html_e( 'Titre du bloc (H2)', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-accueil-actu-titre" name="ditl_accueil_actu_titre" value="<?php echo esc_attr( isset( $actualites['titre'] ) ? $actualites['titre'] : '' ); ?>" />
			<p class="description"><?php esc_html_e( 'La liste des derniers articles est generee automatiquement, seul le titre du bloc se regle ici.', 'ditl' ); ?></p>
		</div>

		<h3><?php esc_html_e( 'Bloc Partenaires', 'ditl' ); ?></h3>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-accueil-part-titre"><?php esc_html_e( 'Titre du bloc (H2)', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-accueil-part-titre" name="ditl_accueil_part_titre" value="<?php echo esc_attr( isset( $partenaires['titre'] ) ? $partenaires['titre'] : '' ); ?>" />
		</div>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-accueil-part-texte"><?php esc_html_e( 'Texte du bloc', 'ditl' ); ?></label>
			<textarea class="ditl-richtext-editor" id="ditl-accueil-part-texte" name="ditl_accueil_part_texte" rows="4"><?php echo esc_textarea( isset( $partenaires['texte'] ) ? (string) $partenaires['texte'] : '' ); ?></textarea>
		</div>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-accueil-part-bouton-texte"><?php esc_html_e( 'Libelle du bouton', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-accueil-part-bouton-texte" name="ditl_accueil_part_bouton_texte" value="<?php echo esc_attr( isset( $partenaires['bouton_texte'] ) ? $partenaires['bouton_texte'] : '' ); ?>" />
		</div>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-accueil-part-bouton-url"><?php esc_html_e( 'URL du bouton', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-accueil-part-bouton-url" name="ditl_accueil_part_bouton_url" value="<?php echo esc_attr( isset( $partenaires['bouton_url'] ) ? $partenaires['bouton_url'] : '' ); ?>" />
			<p class="description"><?php esc_html_e( 'Preferer une URL relative (ex. /partenaires/) pour rester valable sur tous les environnements.', 'ditl' ); ?></p>
		</div>

		<div class="ditl-field">
			<span class="ditl-field-label"><?php esc_html_e( 'Logos des partenaires', 'ditl' ); ?></span>
			<div class="ditl-gallery-field">
				<input type="hidden" name="ditl_accueil_part_logo_ids" class="ditl-gallery-value" value="<?php echo esc_attr( (string) wp_json_encode( $logo_ids ) ); ?>" />
				<ul class="ditl-gallery-preview">
					<?php foreach ( $logo_ids as $logo_id ) : ?>
						<li data-id="<?php echo esc_attr( $logo_id ); ?>">
							<?php echo wp_get_attachment_image( $logo_id, 'thumbnail' ); ?>
							<button type="button" class="button-link ditl-gallery-item-remove" title="<?php esc_attr_e( 'Retirer cette image', 'ditl' ); ?>">&times;</button>
						</li>
					<?php endforeach; ?>
				</ul>
				<button type="button" class="button ditl-gallery-choose"><?php esc_html_e( 'Choisir des images', 'ditl' ); ?></button>
			</div>
		</div>

		<div class="ditl-field">
			<label>
				<input type="checkbox" name="ditl_accueil_part_carrousel" value="1"<?php checked( ! empty( $partenaires['carrousel'] ) ); ?> />
				<?php esc_html_e( 'Afficher les logos en carrousel (decoche : grille statique)', 'ditl' ); ?>
			</label>
		</div>
	</div>
	<?php
}

/**
 * Lit les vignettes Livrables postees par la metabox.
 *
 * Le nonce est verifie en amont (ditl_metabox_peut_enregistrer).
 *
 * @return array Vignettes {image_id, texte} nettoyees (lignes vides ignorees).
 */
function ditl_accueil_lire_livrables_post() {
	// Deux tableaux paralleles, l'ordre du DOM fait foi.
	$image_ids = isset( $_POST['ditl_accueil_livrable_image_id'] ) ? array_values( (array) wp_unslash( $_POST['ditl_accueil_livrable_image_id'] ) ) : array();
	$textes    = isset( $_POST['ditl_accueil_livrable_texte'] ) ? array_values( (array) wp_unslash( $_POST['ditl_accueil_livrable_texte'] ) ) : array();

	// Garde-fou contre un POST anormalement gonfle.
	$total = min( max( count( $image_ids ), count( $textes ) ), 100 );
	$items = array();

	for ( $i = 0; $i < $total; $i++ ) {
		$image_id = isset( $image_ids[ $i ] ) && is_scalar( $image_ids[ $i ] ) ? absint( $image_ids[ $i ] ) : 0;
		$texte    = isset( $textes[ $i ] ) && is_string( $textes[ $i ] ) ? wp_kses_post( $textes[ $i ] ) : '';

		// Les lignes entierement vides sont ignorees.
		if ( 0 === $image_id && '' === trim( wp_strip_all_tags( $texte ) ) ) {
			continue;
		}

		$items[] = array(
			'image_id' => $image_id,
			'texte'    => $texte,
		);
	}

	return $items;
}

/**
 * Lit un champ texte simple poste par la metabox.
 *
 * @param string $name Nom du champ poste.
 * @return string Valeur nettoyee.
 */
function ditl_accueil_lire_texte_post( $name ) {
	return isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
}

/**
 * Lit un champ HTML riche poste par la metabox.
 *
 * @param string $name Nom du champ poste.
 * @return string HTML nettoye (wp_kses_post).
 */
function ditl_accueil_lire_html_post( $name ) {
	return isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] ) ? wp_kses_post( wp_unslash( $_POST[ $name ] ) ) : '';
}

/**
 * Lit un champ URL poste par la metabox.
 *
 * @param string $name Nom du champ poste.
 * @return string URL nettoyee (relative acceptee).
 */
function ditl_accueil_lire_url_post( $name ) {
	return isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] ) ? esc_url_raw( trim( wp_unslash( $_POST[ $name ] ) ) ) : '';
}

/**
 * Enregistre les champs de la metabox.
 *
 * @param int $post_id ID de la page enregistree.
 */
function ditl_accueil_save_metabox( $post_id ) {
	if ( ! ditl_metabox_peut_enregistrer( $post_id, 'ditl_accueil_nonce', 'ditl_accueil_save_' . $post_id ) ) {
		return;
	}

	// La metabox est rendue (masquee) sur toutes les pages : on n'ecrit les
	// metas que si le gabarit est reellement selectionne, sans les effacer
	// quand la page passe temporairement sur un autre modele.
	if ( DITL_TPL_ACCUEIL !== get_page_template_slug( $post_id ) ) {
		return;
	}

	// Complements de la banniere (sous-titre H2 + bouton).
	$hero = array(
		'sous_titre'   => ditl_accueil_lire_texte_post( 'ditl_accueil_hero_sous_titre' ),
		'bouton_texte' => ditl_accueil_lire_texte_post( 'ditl_accueil_hero_bouton_texte' ),
		'bouton_url'   => ditl_accueil_lire_url_post( 'ditl_accueil_hero_bouton_url' ),
	);
	update_post_meta( $post_id, '_ditl_accueil_hero', wp_slash( (string) wp_json_encode( $hero, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );

	// Bloc Presentation.
	$presentation = array(
		'titre'        => ditl_accueil_lire_texte_post( 'ditl_accueil_pres_titre' ),
		'texte'        => ditl_accueil_lire_html_post( 'ditl_accueil_pres_texte' ),
		'bouton_texte' => ditl_accueil_lire_texte_post( 'ditl_accueil_pres_bouton_texte' ),
		'bouton_url'   => ditl_accueil_lire_url_post( 'ditl_accueil_pres_bouton_url' ),
		'image_id'     => isset( $_POST['ditl_accueil_pres_image_id'] ) ? absint( wp_unslash( $_POST['ditl_accueil_pres_image_id'] ) ) : 0,
	);
	update_post_meta( $post_id, '_ditl_accueil_presentation', wp_slash( (string) wp_json_encode( $presentation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );

	// Bloc Livrables (vignettes repetables).
	$livrables = array(
		'titre' => ditl_accueil_lire_texte_post( 'ditl_accueil_livr_titre' ),
		'intro' => ditl_accueil_lire_html_post( 'ditl_accueil_livr_intro' ),
		'items' => ditl_accueil_lire_livrables_post(),
	);
	update_post_meta( $post_id, '_ditl_accueil_livrables', wp_slash( (string) wp_json_encode( $livrables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );

	// Bloc Actualites (le contenu est dynamique, seul le titre est stocke).
	$actualites = array(
		'titre' => ditl_accueil_lire_texte_post( 'ditl_accueil_actu_titre' ),
	);
	update_post_meta( $post_id, '_ditl_accueil_actualites', wp_slash( (string) wp_json_encode( $actualites, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );

	// Bloc Partenaires (galerie transmise en JSON par le selecteur de medias).
	$logo_ids_raw = isset( $_POST['ditl_accueil_part_logo_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ditl_accueil_part_logo_ids'] ) ) : '[]';
	$logo_ids     = json_decode( ditl_sanitize_ids_json( $logo_ids_raw ), true );

	$partenaires = array(
		'titre'        => ditl_accueil_lire_texte_post( 'ditl_accueil_part_titre' ),
		'texte'        => ditl_accueil_lire_html_post( 'ditl_accueil_part_texte' ),
		'bouton_texte' => ditl_accueil_lire_texte_post( 'ditl_accueil_part_bouton_texte' ),
		'bouton_url'   => ditl_accueil_lire_url_post( 'ditl_accueil_part_bouton_url' ),
		'logo_ids'     => is_array( $logo_ids ) ? $logo_ids : array(),
		'carrousel'    => isset( $_POST['ditl_accueil_part_carrousel'] ),
	);
	update_post_meta( $post_id, '_ditl_accueil_partenaires', wp_slash( (string) wp_json_encode( $partenaires, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
}
add_action( 'save_post_page', 'ditl_accueil_save_metabox' );
