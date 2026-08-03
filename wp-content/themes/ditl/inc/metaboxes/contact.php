<?php
/**
 * Metabox du gabarit "Contact".
 *
 * Contrat de metas (FIGE - le template page-templates/contact.php s'appuie dessus).
 * Les deux metas sont des objets JSON structures, une par colonne de la section :
 * - _ditl_contact_formulaire  (string) : {titre, form_id}
 *                                        titre   = H2 de la colonne de gauche ;
 *                                        form_id = ID du formulaire WPForms affiche
 *                                        (0 = aucun formulaire). Le template rend le
 *                                        formulaire via le shortcode du plugin : l'ID
 *                                        n'est jamais code en dur.
 * - _ditl_contact_coordonnees (string) : {titre, blocs: [{icone_id, titre, description}]}
 *                                        titre       = H2 de la colonne de droite ;
 *                                        icone_id    = icone du bloc (ID d'attachement,
 *                                          SVG de la mediatheque dans l'existant) ;
 *                                        titre       = intitule du bloc (H3), texte simple ;
 *                                        description = contenu du bloc, HTML riche
 *                                          (liens tel:/mailto: et <br> conserves).
 *
 * Les descriptions des blocs de coordonnees sont saisies en zone de texte SIMPLE,
 * sans editeur TinyMCE : l'existant melange sauts de ligne et <br> dans un seul
 * paragraphe, et wpautop enrobirait le tout de <p> supplementaires (meme parade
 * que le titre H3 du gabarit Partenaires). Le HTML reste autorise, filtre par
 * wp_kses_post - qui accepte le protocole tel: et preserve les <br>.
 *
 * Les metas de banniere (_ditl_hero_image_id, _ditl_hero_title) sont gerees
 * par la metabox commune inc/metaboxes/banniere.php.
 *
 * La metabox n'est visible que lorsque le modele de page "Contact"
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
define( 'DITL_TPL_CONTACT', 'page-templates/contact.php' );

// Type de publication des formulaires WPForms (plugin conserve au perimetre).
define( 'DITL_CONTACT_FORM_POST_TYPE', 'wpforms' );

/**
 * Valide un ID de formulaire WPForms.
 *
 * Un ID qui ne correspond pas a une publication du type "wpforms" est
 * ramene a 0 : le template n'affiche alors aucun formulaire, plutot que
 * d'appeler le shortcode du plugin avec un ID fantaisiste. Le controle ne
 * depend pas de l'activation du plugin (le type est lu en base).
 *
 * @param mixed $value ID candidat.
 * @return int ID valide, ou 0.
 */
function ditl_contact_form_id_valide( $value ) {
	$form_id = is_scalar( $value ) ? absint( $value ) : 0;

	if ( $form_id <= 0 ) {
		return 0;
	}

	return DITL_CONTACT_FORM_POST_TYPE === get_post_type( $form_id ) ? $form_id : 0;
}

/**
 * Nettoie l'objet JSON {titre, form_id} de la colonne formulaire.
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (objet aux cles garanties).
 */
function ditl_contact_sanitize_formulaire_json( $value ) {
	$data = is_scalar( $value ) ? json_decode( (string) $value, true ) : null;

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$propre = array(
		'titre'   => isset( $data['titre'] ) && is_scalar( $data['titre'] ) ? sanitize_text_field( (string) $data['titre'] ) : '',
		'form_id' => isset( $data['form_id'] ) ? ditl_contact_form_id_valide( $data['form_id'] ) : 0,
	);

	return (string) wp_json_encode( $propre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Filtre la description d'un bloc de coordonnees.
 *
 * Liste blanche volontairement plus etroite que wp_kses_post : les
 * coordonnees n'ont besoin que de liens et de sauts de ligne. Ecarte du meme
 * coup l'attribut style (recouvrement possible de la page), les balises de
 * bloc (qui casseraient le <p> du rendu) et target.
 *
 * Le protocole tel: est conserve, il fait partie de wp_allowed_protocols().
 *
 * @param string $description Description brute.
 * @return string Description filtree.
 */
function ditl_contact_filtrer_description( $description ) {
	return wp_kses(
		(string) $description,
		array(
			'a'      => array(
				'href'  => true,
				'title' => true,
			),
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
		)
	);
}

/**
 * Nettoie un bloc de coordonnees {icone_id, titre, description}.
 *
 * @param mixed $bloc Donnees brutes du bloc.
 * @return array Bloc aux cles garanties et valeurs nettoyees.
 */
function ditl_contact_sanitize_bloc( $bloc ) {
	if ( ! is_array( $bloc ) ) {
		$bloc = array();
	}

	// Rejet des valeurs non scalaires (JSON inattendu) avant tout cast.
	foreach ( array( 'icone_id', 'titre', 'description' ) as $ditl_cle ) {
		if ( isset( $bloc[ $ditl_cle ] ) && ! is_scalar( $bloc[ $ditl_cle ] ) ) {
			unset( $bloc[ $ditl_cle ] );
		}
	}

	$icone_id = isset( $bloc['icone_id'] ) ? absint( $bloc['icone_id'] ) : 0;

	// Meme garde que sur form_id : un ID qui n'est pas un media est ecarte.
	if ( $icone_id > 0 && 'attachment' !== get_post_type( $icone_id ) ) {
		$icone_id = 0;
	}

	return array(
		'icone_id'    => $icone_id,
		'titre'       => isset( $bloc['titre'] ) ? sanitize_text_field( (string) $bloc['titre'] ) : '',
		'description' => isset( $bloc['description'] ) ? ditl_contact_filtrer_description( (string) $bloc['description'] ) : '',
	);
}

/**
 * Indique si un bloc de coordonnees nettoye est entierement vide.
 *
 * @param array $bloc Bloc aux cles garanties.
 * @return bool True si aucune donnee utile.
 */
function ditl_contact_bloc_vide( $bloc ) {
	return 0 === $bloc['icone_id']
		&& '' === $bloc['titre']
		&& '' === trim( wp_strip_all_tags( $bloc['description'] ) );
}

/**
 * Nettoie l'objet JSON de la colonne coordonnees (blocs repetables inclus).
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (objet aux cles garanties).
 */
function ditl_contact_sanitize_coordonnees_json( $value ) {
	$data = is_scalar( $value ) ? json_decode( (string) $value, true ) : null;

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$blocs = isset( $data['blocs'] ) && is_array( $data['blocs'] ) ? $data['blocs'] : array();

	// Meme garde-fou que les autres listes repetables des gabarits.
	$blocs   = array_slice( $blocs, 0, 100 );
	$propres = array();

	foreach ( $blocs as $bloc ) {
		$bloc = ditl_contact_sanitize_bloc( $bloc );

		if ( ditl_contact_bloc_vide( $bloc ) ) {
			continue;
		}

		$propres[] = $bloc;
	}

	$propre = array(
		'titre' => isset( $data['titre'] ) && is_scalar( $data['titre'] ) ? sanitize_text_field( (string) $data['titre'] ) : '',
		'blocs' => $propres,
	);

	return (string) wp_json_encode( $propre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Declare les metas du gabarit (protegees, avec controle d'acces).
 */
function ditl_contact_register_meta() {
	$metas = array(
		'_ditl_contact_formulaire'  => 'ditl_contact_sanitize_formulaire_json',
		'_ditl_contact_coordonnees' => 'ditl_contact_sanitize_coordonnees_json',
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
add_action( 'init', 'ditl_contact_register_meta' );

/**
 * Ajoute la metabox sur l'ecran d'edition des pages.
 */
function ditl_contact_add_metabox() {
	add_meta_box(
		'ditl-contact',
		__( 'Contenu du gabarit Contact', 'ditl' ),
		'ditl_contact_render_metabox',
		'page',
		'normal',
		'high',
		array( '__block_editor_compatible_meta_box' => true )
	);
}
add_action( 'add_meta_boxes_page', 'ditl_contact_add_metabox' );

/**
 * Retourne la liste des formulaires WPForms proposables dans la metabox.
 *
 * Les formulaires publies sont listes ; l'ID deja enregistre est ajoute a la
 * liste s'il n'y figure pas (formulaire en brouillon par exemple), afin qu'un
 * enregistrement ne le perde pas silencieusement.
 *
 * @param int $form_id ID actuellement enregistre (0 si aucun).
 * @return array Liste [ID => libelle].
 */
function ditl_contact_liste_formulaires( $form_id ) {
	$formulaires = array();

	$posts = get_posts(
		array(
			'post_type'        => DITL_CONTACT_FORM_POST_TYPE,
			'post_status'      => 'publish',
			'numberposts'      => 100,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
			// Toutes langues : un meme formulaire sert les pages de chaque langue.
			'lang'             => '',
		)
	);

	foreach ( $posts as $ditl_formulaire_post ) {
		$titre = '' !== $ditl_formulaire_post->post_title ? $ditl_formulaire_post->post_title : __( '(sans titre)', 'ditl' );

		/* translators: 1 : titre du formulaire, 2 : ID du formulaire. */
		$formulaires[ $ditl_formulaire_post->ID ] = sprintf( __( '%1$s (ID %2$d)', 'ditl' ), $titre, $ditl_formulaire_post->ID );
	}

	$form_id = absint( $form_id );

	if ( $form_id > 0 && ! isset( $formulaires[ $form_id ] ) ) {
		/* translators: %d : ID du formulaire. */
		$formulaires[ $form_id ] = sprintf( __( 'ID %d (formulaire non publie ou introuvable)', 'ditl' ), $form_id );
	}

	return $formulaires;
}

/**
 * Affiche un bloc de coordonnees (ligne existante ou modele JS).
 *
 * Reutilise le markup des lignes repetables (.ditl-section) : ajout,
 * suppression, tri et barre d'outils sont geres par le JS commun
 * (assets/admin/metabox-gabarits.js), le selecteur de media par la
 * mecanique .ditl-media-field partagee.
 *
 * La description est une zone de texte simple (pas de classe
 * ditl-section-editor) : voir l'en-tete du fichier.
 *
 * @param string|int $index Index de la ligne (ou "%index%" pour le modele).
 * @param array      $bloc  Donnees du bloc (cles du contrat de meta).
 */
function ditl_contact_render_bloc_row( $index, $bloc = array() ) {
	$icone_id    = isset( $bloc['icone_id'] ) ? absint( $bloc['icone_id'] ) : 0;
	$titre       = isset( $bloc['titre'] ) ? (string) $bloc['titre'] : '';
	$description = isset( $bloc['description'] ) ? (string) $bloc['description'] : '';
	?>
	<div class="ditl-section">
		<span class="ditl-field-label"><?php esc_html_e( 'Icone du bloc', 'ditl' ); ?></span>
		<div class="ditl-media-field">
			<input type="hidden" name="ditl_contact_coord_icone_id[]" class="ditl-media-value" value="<?php echo esc_attr( $icone_id ? $icone_id : '' ); ?>" />
			<div class="ditl-media-preview">
				<?php echo ditl_metabox_media_preview( $icone_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup deja echappe. ?>
			</div>
			<button type="button" class="button ditl-media-choose"><?php esc_html_e( 'Choisir une image', 'ditl' ); ?></button>
			<button type="button" class="button ditl-media-remove"<?php echo $icone_id ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Retirer l\'image', 'ditl' ); ?></button>
		</div>

		<label>
			<span class="ditl-field-label"><?php esc_html_e( 'Intitule du bloc (H3)', 'ditl' ); ?></span>
			<input type="text" class="widefat" id="<?php echo esc_attr( 'ditl-contact-coord-titre-' . $index ); ?>" name="ditl_contact_coord_bloc_titre[]" value="<?php echo esc_attr( $titre ); ?>" />
		</label>

		<label>
			<span class="ditl-field-label"><?php esc_html_e( 'Contenu du bloc', 'ditl' ); ?></span>
			<textarea class="widefat" id="<?php echo esc_attr( 'ditl-contact-coord-description-' . $index ); ?>" name="ditl_contact_coord_description[]" rows="5"><?php echo esc_textarea( $description ); ?></textarea>
		</label>
		<p class="description"><?php esc_html_e( 'Balises autorisees : <br> pour un retour a la ligne, <a href="tel:...">, <a href="mailto:...">, <strong> et <em>. Le texte n\'est pas mis en forme automatiquement. Attention : la couleur de l\'intitule du premier bloc differe de celle des suivants (reprise du site existant), elle suit donc l\'ordre des blocs.', 'ditl' ); ?></p>
	</div>
	<?php
}

/**
 * Affiche les champs de la metabox.
 *
 * @param WP_Post $post Page en cours d'edition.
 */
function ditl_contact_render_metabox( $post ) {
	wp_nonce_field( 'ditl_contact_save_' . $post->ID, 'ditl_contact_nonce' );

	$formulaire  = ditl_get_meta_json( $post->ID, '_ditl_contact_formulaire' );
	$coordonnees = ditl_get_meta_json( $post->ID, '_ditl_contact_coordonnees' );

	$form_titre  = isset( $formulaire['titre'] ) ? (string) $formulaire['titre'] : '';
	$form_id     = isset( $formulaire['form_id'] ) ? absint( $formulaire['form_id'] ) : 0;
	$coord_titre = isset( $coordonnees['titre'] ) ? (string) $coordonnees['titre'] : '';
	$blocs       = isset( $coordonnees['blocs'] ) && is_array( $coordonnees['blocs'] ) ? $coordonnees['blocs'] : array();

	$formulaires = ditl_contact_liste_formulaires( $form_id );
	?>
	<div class="ditl-metabox">
		<p class="description">
			<?php esc_html_e( 'Ces champs alimentent le gabarit "Contact". Ils ne sont utilises que lorsque ce modele de page est selectionne. L\'image et le titre H1 de la banniere se reglent dans la metabox "Banniere du gabarit".', 'ditl' ); ?>
		</p>

		<h3><?php esc_html_e( 'Colonne formulaire', 'ditl' ); ?></h3>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-contact-form-titre"><?php esc_html_e( 'Titre de la colonne (H2)', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-contact-form-titre" name="ditl_contact_form_titre" value="<?php echo esc_attr( $form_titre ); ?>" />
		</div>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-contact-form-id"><?php esc_html_e( 'Formulaire affiche', 'ditl' ); ?></label>
			<select class="widefat" id="ditl-contact-form-id" name="ditl_contact_form_id">
				<option value="0"><?php esc_html_e( '- Aucun formulaire -', 'ditl' ); ?></option>
				<?php foreach ( $formulaires as $ditl_form_id => $ditl_form_label ) : ?>
					<option value="<?php echo esc_attr( $ditl_form_id ); ?>"<?php selected( $form_id, $ditl_form_id ); ?>><?php echo esc_html( $ditl_form_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( empty( $formulaires ) ) : ?>
				<p class="description"><?php esc_html_e( 'Aucun formulaire WPForms publie n\'a ete trouve sur ce site.', 'ditl' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Formulaire gere dans WPForms ; seul son choix se fait ici.', 'ditl' ); ?></p>
			<?php endif; ?>
		</div>

		<h3><?php esc_html_e( 'Colonne coordonnees', 'ditl' ); ?></h3>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-contact-coord-titre"><?php esc_html_e( 'Titre de la colonne (H2)', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-contact-coord-titre" name="ditl_contact_coord_titre" value="<?php echo esc_attr( $coord_titre ); ?>" />
		</div>

		<div class="ditl-field">
			<span class="ditl-field-label"><?php esc_html_e( 'Blocs de coordonnees', 'ditl' ); ?></span>
			<div class="ditl-sections-field" data-row-label="<?php echo esc_attr( /* translators: %d : numero d'ordre du bloc. */ __( 'Bloc %d', 'ditl' ) ); ?>">
				<div class="ditl-sections">
					<?php foreach ( $blocs as $index => $bloc ) : ?>
						<?php ditl_contact_render_bloc_row( $index, $bloc ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button button-secondary ditl-section-add"><?php esc_html_e( 'Ajouter un bloc', 'ditl' ); ?></button>
				<script type="text/html" class="ditl-section-template">
					<?php ditl_contact_render_bloc_row( '%index%' ); ?>
				</script>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Lit les blocs de coordonnees postes par la metabox.
 *
 * Le nonce est verifie en amont (ditl_metabox_peut_enregistrer).
 *
 * @return array Blocs {icone_id, titre, description} nettoyes (lignes vides ignorees).
 */
function ditl_contact_lire_blocs_post() {
	// Tableaux paralleles, l'ordre du DOM fait foi.
	$icone_ids    = isset( $_POST['ditl_contact_coord_icone_id'] ) ? array_values( (array) wp_unslash( $_POST['ditl_contact_coord_icone_id'] ) ) : array();
	$titres       = isset( $_POST['ditl_contact_coord_bloc_titre'] ) ? array_values( (array) wp_unslash( $_POST['ditl_contact_coord_bloc_titre'] ) ) : array();
	$descriptions = isset( $_POST['ditl_contact_coord_description'] ) ? array_values( (array) wp_unslash( $_POST['ditl_contact_coord_description'] ) ) : array();

	// Garde-fou contre un POST anormalement gonfle.
	$total = min( max( count( $icone_ids ), count( $titres ), count( $descriptions ) ), 100 );
	$blocs = array();

	for ( $i = 0; $i < $total; $i++ ) {
		$bloc = ditl_contact_sanitize_bloc(
			array(
				'icone_id'    => isset( $icone_ids[ $i ] ) ? $icone_ids[ $i ] : 0,
				'titre'       => isset( $titres[ $i ] ) ? $titres[ $i ] : '',
				'description' => isset( $descriptions[ $i ] ) ? $descriptions[ $i ] : '',
			)
		);

		// Les lignes entierement vides sont ignorees.
		if ( ditl_contact_bloc_vide( $bloc ) ) {
			continue;
		}

		$blocs[] = $bloc;
	}

	return $blocs;
}

/**
 * Enregistre les champs de la metabox.
 *
 * @param int $post_id ID de la page enregistree.
 */
function ditl_contact_save_metabox( $post_id ) {
	if ( ! ditl_metabox_peut_enregistrer( $post_id, 'ditl_contact_nonce', 'ditl_contact_save_' . $post_id ) ) {
		return;
	}

	// La metabox est rendue (masquee) sur toutes les pages : on n'ecrit les
	// metas que si le gabarit est reellement selectionne, sans les effacer
	// quand la page passe temporairement sur un autre modele.
	if ( DITL_TPL_CONTACT !== get_page_template_slug( $post_id ) ) {
		return;
	}

	// Colonne formulaire (titre + formulaire WPForms choisi).
	$formulaire = array(
		'titre'   => isset( $_POST['ditl_contact_form_titre'] ) && is_string( $_POST['ditl_contact_form_titre'] ) ? sanitize_text_field( wp_unslash( $_POST['ditl_contact_form_titre'] ) ) : '',
		'form_id' => isset( $_POST['ditl_contact_form_id'] ) ? ditl_contact_form_id_valide( wp_unslash( $_POST['ditl_contact_form_id'] ) ) : 0,
	);
	update_post_meta( $post_id, '_ditl_contact_formulaire', wp_slash( (string) wp_json_encode( $formulaire, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );

	// Colonne coordonnees (titre + blocs repetables).
	$coordonnees = array(
		'titre' => isset( $_POST['ditl_contact_coord_titre'] ) && is_string( $_POST['ditl_contact_coord_titre'] ) ? sanitize_text_field( wp_unslash( $_POST['ditl_contact_coord_titre'] ) ) : '',
		'blocs' => ditl_contact_lire_blocs_post(),
	);
	update_post_meta( $post_id, '_ditl_contact_coordonnees', wp_slash( (string) wp_json_encode( $coordonnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
}
add_action( 'save_post_page', 'ditl_contact_save_metabox' );
