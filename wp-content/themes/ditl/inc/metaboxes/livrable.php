<?php
/**
 * Metabox du gabarit "Livrable".
 *
 * Contrat de metas (FIGE - le template page-templates/livrable.php s'appuie dessus) :
 * - _ditl_livrable_carte (string) : {map_id, alternative}
 *                                   map_id      = ID de la carte interactive affichee
 *                                     (publication du type "igmap" du plugin
 *                                     Interactive Geo Maps, conserve au perimetre ;
 *                                     0 = aucune carte). Le template rend la carte
 *                                     via le shortcode du plugin : l'ID n'est
 *                                     jamais code en dur.
 *                                   alternative = alternative textuelle accessible
 *                                     de la carte (texte simple multi-lignes), lue
 *                                     par les lecteurs d'ecran a la place de la
 *                                     carte (pays et villes du projet, soit
 *                                     l'information portee par les tooltips).
 * - _ditl_livrables      (string) : JSON [{titre, contenu, bouton_texte, bouton_url}]
 *                                   Sections de livrables :
 *                                   - titre        : titre H2 de la section, HTML riche
 *                                     leger (certains titres de l'existant portent un
 *                                     span avec style inline, conserve tel quel) ;
 *                                   - contenu      : contenu de la section, HTML riche ;
 *                                   - bouton_texte : libelle du bouton de telechargement ;
 *                                   - bouton_url   : URL du bouton (PDF interne en
 *                                     general, stockee relative).
 *
 * Le titre de section est saisi en zone de texte SIMPLE, sans editeur
 * TinyMCE : wpautop enroberait les spans de <p> supplementaires (meme parade
 * que le titre H3 du gabarit Partenaires). Le HTML reste autorise, filtre
 * par wp_kses_post.
 *
 * Ce gabarit n'a pas d'image de banniere dans l'existant : seule la meta
 * _ditl_hero_title est utilisee (_ditl_hero_image_id reste a 0), toutes deux
 * gerees par la metabox commune inc/metaboxes/banniere.php.
 *
 * La metabox n'est visible que lorsque le modele de page "Livrable"
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
define( 'DITL_TPL_LIVRABLE', 'page-templates/livrable.php' );

// Type de publication des cartes Interactive Geo Maps (plugin conserve au perimetre).
define( 'DITL_LIVRABLE_CARTE_POST_TYPE', 'igmap' );

/**
 * Valide un ID de carte Interactive Geo Maps.
 *
 * Un ID qui ne correspond pas a une publication du type "igmap" est
 * ramene a 0 : le template n'affiche alors aucune carte, plutot que
 * d'appeler le shortcode du plugin avec un ID fantaisiste. Le controle ne
 * depend pas de l'activation du plugin (le type est lu en base).
 *
 * @param mixed $value ID candidat.
 * @return int ID valide, ou 0.
 */
function ditl_livrable_map_id_valide( $value ) {
	$map_id = is_scalar( $value ) ? absint( $value ) : 0;

	if ( $map_id <= 0 ) {
		return 0;
	}

	return DITL_LIVRABLE_CARTE_POST_TYPE === get_post_type( $map_id ) ? $map_id : 0;
}

/**
 * Nettoie l'objet JSON {map_id, alternative} de la carte interactive.
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (objet aux cles garanties).
 */
function ditl_livrable_sanitize_carte_json( $value ) {
	$data = is_scalar( $value ) ? json_decode( (string) $value, true ) : null;

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$propre = array(
		'map_id'      => isset( $data['map_id'] ) ? ditl_livrable_map_id_valide( $data['map_id'] ) : 0,
		'alternative' => isset( $data['alternative'] ) && is_scalar( $data['alternative'] ) ? sanitize_textarea_field( (string) $data['alternative'] ) : '',
	);

	return (string) wp_json_encode( $propre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Nettoie une section de livrable {titre, contenu, bouton_texte, bouton_url}.
 *
 * @param array $livrable Donnees brutes de la section.
 * @return array Section aux cles garanties et valeurs nettoyees.
 */
function ditl_livrable_sanitize_livrable( $livrable ) {
	if ( ! is_array( $livrable ) ) {
		$livrable = array();
	}

	// Rejet des valeurs non scalaires (JSON inattendu) avant tout cast.
	foreach ( array( 'titre', 'contenu', 'bouton_texte', 'bouton_url' ) as $ditl_cle ) {
		if ( isset( $livrable[ $ditl_cle ] ) && ! is_scalar( $livrable[ $ditl_cle ] ) ) {
			$livrable[ $ditl_cle ] = '';
		}
	}

	return array(
		'titre'        => isset( $livrable['titre'] ) ? wp_kses_post( (string) $livrable['titre'] ) : '',
		'contenu'      => isset( $livrable['contenu'] ) ? wp_kses_post( (string) $livrable['contenu'] ) : '',
		'bouton_texte' => isset( $livrable['bouton_texte'] ) ? sanitize_text_field( (string) $livrable['bouton_texte'] ) : '',
		'bouton_url'   => isset( $livrable['bouton_url'] ) ? esc_url_raw( trim( (string) $livrable['bouton_url'] ) ) : '',
	);
}

/**
 * Indique si une section de livrable nettoyee est entierement vide.
 *
 * @param array $livrable Section aux cles garanties.
 * @return bool True si aucune donnee utile.
 */
function ditl_livrable_livrable_vide( $livrable ) {
	return '' === trim( wp_strip_all_tags( $livrable['titre'] ) )
		&& '' === trim( wp_strip_all_tags( $livrable['contenu'] ) )
		&& '' === $livrable['bouton_texte']
		&& '' === $livrable['bouton_url'];
}

/**
 * Nettoie la liste de sections de livrables encodee en JSON.
 *
 * Les sections vides sont ignorees. Garde-fou : 100 sections maximum.
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (tableau vide si invalide).
 */
function ditl_livrable_sanitize_livrables_json( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '[]';
	}

	$livrables = json_decode( (string) $value, true );

	if ( ! is_array( $livrables ) ) {
		return '[]';
	}

	// Meme garde-fou que les autres listes repetables des gabarits.
	$livrables = array_slice( $livrables, 0, 100 );
	$propres   = array();

	foreach ( $livrables as $livrable ) {
		$livrable = ditl_livrable_sanitize_livrable( $livrable );

		if ( ditl_livrable_livrable_vide( $livrable ) ) {
			continue;
		}

		$propres[] = $livrable;
	}

	return (string) wp_json_encode( $propres, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Declare les metas du gabarit (protegees, avec controle d'acces).
 */
function ditl_livrable_register_meta() {
	register_post_meta(
		'page',
		'_ditl_livrable_carte',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '{}',
			'sanitize_callback' => 'ditl_livrable_sanitize_carte_json',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);

	register_post_meta(
		'page',
		'_ditl_livrables',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '[]',
			'sanitize_callback' => 'ditl_livrable_sanitize_livrables_json',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', 'ditl_livrable_register_meta' );

/**
 * Ajoute la metabox sur l'ecran d'edition des pages.
 */
function ditl_livrable_add_metabox() {
	add_meta_box(
		'ditl-livrable',
		__( 'Contenu du gabarit Livrable', 'ditl' ),
		'ditl_livrable_render_metabox',
		'page',
		'normal',
		'high',
		array( '__block_editor_compatible_meta_box' => true )
	);
}
add_action( 'add_meta_boxes_page', 'ditl_livrable_add_metabox' );

/**
 * Affiche une section de livrable (ligne existante ou modele JS).
 *
 * Reutilise le markup des lignes repetables (.ditl-section) : ajout,
 * suppression, tri, barre d'outils et editeur riche sont geres par le JS
 * commun (assets/admin/metabox-gabarits.js).
 *
 * Le titre est une zone de texte simple (pas de classe ditl-section-editor) :
 * voir l'en-tete du fichier.
 *
 * @param string|int $index    Index de la ligne (ou "%index%" pour le modele).
 * @param array      $livrable Donnees de la section (cles du contrat de meta).
 */
function ditl_livrable_render_livrable_row( $index, $livrable = array() ) {
	$titre        = isset( $livrable['titre'] ) ? (string) $livrable['titre'] : '';
	$contenu      = isset( $livrable['contenu'] ) ? (string) $livrable['contenu'] : '';
	$bouton_texte = isset( $livrable['bouton_texte'] ) ? (string) $livrable['bouton_texte'] : '';
	$bouton_url   = isset( $livrable['bouton_url'] ) ? (string) $livrable['bouton_url'] : '';
	?>
	<div class="ditl-section">
		<label>
			<span class="ditl-field-label"><?php esc_html_e( 'Titre de la section (H2)', 'ditl' ); ?></span>
			<textarea class="widefat" name="ditl_livrable_titre[]" rows="2"><?php echo esc_textarea( $titre ); ?></textarea>
		</label>
		<p class="description"><?php esc_html_e( 'Mise en forme HTML conservee (em, strong, span...). Privilegier une mise en forme legere pour rester fidele au design.', 'ditl' ); ?></p>

		<span class="ditl-field-label"><?php esc_html_e( 'Contenu de la section', 'ditl' ); ?></span>
		<textarea class="ditl-section-editor" id="<?php echo esc_attr( 'ditl-livrable-contenu-' . $index ); ?>" name="ditl_livrable_contenu[]" rows="8"><?php echo esc_textarea( $contenu ); ?></textarea>

		<label>
			<span class="ditl-field-label"><?php esc_html_e( 'Libelle du bouton de telechargement', 'ditl' ); ?></span>
			<input type="text" class="widefat" name="ditl_livrable_bouton_texte[]" value="<?php echo esc_attr( $bouton_texte ); ?>" />
		</label>

		<label>
			<span class="ditl-field-label"><?php esc_html_e( 'URL du bouton', 'ditl' ); ?></span>
			<input type="text" class="widefat" name="ditl_livrable_bouton_url[]" value="<?php echo esc_attr( $bouton_url ); ?>" />
		</label>
		<p class="description"><?php esc_html_e( 'Fichier PDF du livrable en general. Pour un fichier du site, preferer une URL relative (ex. /wp-content/uploads/...).', 'ditl' ); ?></p>
	</div>
	<?php
}

/**
 * Affiche les champs de la metabox.
 *
 * @param WP_Post $post Page en cours d'edition.
 */
function ditl_livrable_render_metabox( $post ) {
	wp_nonce_field( 'ditl_livrable_save_' . $post->ID, 'ditl_livrable_nonce' );

	$carte     = ditl_get_meta_json( $post->ID, '_ditl_livrable_carte' );
	$livrables = ditl_get_meta_json( $post->ID, '_ditl_livrables' );

	$map_id      = isset( $carte['map_id'] ) ? absint( $carte['map_id'] ) : 0;
	$alternative = isset( $carte['alternative'] ) ? (string) $carte['alternative'] : '';

	// Cartes Interactive Geo Maps publiees, toutes langues (chaque page
	// choisit la carte de sa langue) ; helper partage avec le gabarit Contact.
	$cartes = ditl_metabox_liste_publications(
		DITL_LIVRABLE_CARTE_POST_TYPE,
		$map_id,
		/* translators: %d : ID de la carte. */
		__( 'ID %d (carte non publiee ou introuvable)', 'ditl' )
	);
	?>
	<div class="ditl-metabox">
		<p class="description">
			<?php esc_html_e( 'Ces champs alimentent le gabarit "Livrable". Ils ne sont utilises que lorsque ce modele de page est selectionne. Le titre H1 de la page se regle dans la metabox "Banniere du gabarit" (ce gabarit n\'a pas d\'image de banniere : laisser le champ image vide).', 'ditl' ); ?>
		</p>

		<h3><?php esc_html_e( 'Carte interactive', 'ditl' ); ?></h3>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-livrable-map-id"><?php esc_html_e( 'Carte affichee', 'ditl' ); ?></label>
			<select class="widefat" id="ditl-livrable-map-id" name="ditl_livrable_map_id">
				<option value="0"><?php esc_html_e( '- Aucune carte -', 'ditl' ); ?></option>
				<?php foreach ( $cartes as $ditl_carte_id => $ditl_carte_label ) : ?>
					<option value="<?php echo esc_attr( $ditl_carte_id ); ?>"<?php selected( $map_id, $ditl_carte_id ); ?>><?php echo esc_html( $ditl_carte_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( empty( $cartes ) ) : ?>
				<p class="description"><?php esc_html_e( 'Aucune carte Interactive Geo Maps publiee n\'a ete trouvee sur ce site.', 'ditl' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Carte geree dans Interactive Geo Maps ; seul son choix se fait ici. Choisir la carte de la langue de la page.', 'ditl' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-livrable-carte-alt"><?php esc_html_e( 'Alternative textuelle de la carte', 'ditl' ); ?></label>
			<textarea class="widefat" id="ditl-livrable-carte-alt" name="ditl_livrable_carte_alt" rows="4"><?php echo esc_textarea( $alternative ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Texte lu par les lecteurs d\'ecran a la place de la carte : decrire l\'information qu\'elle porte (pays partenaires du projet et villes signalees), dans la langue de la page. Texte simple, sans HTML.', 'ditl' ); ?></p>
		</div>

		<h3><?php esc_html_e( 'Sections de livrables', 'ditl' ); ?></h3>

		<div class="ditl-field">
			<div class="ditl-sections-field" data-row-label="<?php echo esc_attr( /* translators: %d : numero d'ordre du livrable. */ __( 'Livrable %d', 'ditl' ) ); ?>">
				<div class="ditl-sections">
					<?php foreach ( $livrables as $index => $livrable ) : ?>
						<?php ditl_livrable_render_livrable_row( $index, $livrable ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button button-secondary ditl-section-add"><?php esc_html_e( 'Ajouter un livrable', 'ditl' ); ?></button>
				<script type="text/html" class="ditl-section-template">
					<?php ditl_livrable_render_livrable_row( '%index%' ); ?>
				</script>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Lit les sections de livrables postees par la metabox.
 *
 * Le nonce est verifie en amont (ditl_metabox_peut_enregistrer).
 *
 * @return array Sections {titre, contenu, bouton_texte, bouton_url} nettoyees
 *               (lignes vides ignorees).
 */
function ditl_livrable_lire_livrables_post() {
	// Tableaux paralleles, l'ordre du DOM fait foi.
	$titres       = isset( $_POST['ditl_livrable_titre'] ) ? array_values( (array) wp_unslash( $_POST['ditl_livrable_titre'] ) ) : array();
	$contenus     = isset( $_POST['ditl_livrable_contenu'] ) ? array_values( (array) wp_unslash( $_POST['ditl_livrable_contenu'] ) ) : array();
	$boutons_txt  = isset( $_POST['ditl_livrable_bouton_texte'] ) ? array_values( (array) wp_unslash( $_POST['ditl_livrable_bouton_texte'] ) ) : array();
	$boutons_url  = isset( $_POST['ditl_livrable_bouton_url'] ) ? array_values( (array) wp_unslash( $_POST['ditl_livrable_bouton_url'] ) ) : array();

	// Garde-fou contre un POST anormalement gonfle.
	$total     = min( max( count( $titres ), count( $contenus ), count( $boutons_txt ), count( $boutons_url ) ), 100 );
	$livrables = array();

	for ( $i = 0; $i < $total; $i++ ) {
		$livrable = ditl_livrable_sanitize_livrable(
			array(
				'titre'        => isset( $titres[ $i ] ) && is_string( $titres[ $i ] ) ? $titres[ $i ] : '',
				'contenu'      => isset( $contenus[ $i ] ) && is_string( $contenus[ $i ] ) ? $contenus[ $i ] : '',
				'bouton_texte' => isset( $boutons_txt[ $i ] ) && is_string( $boutons_txt[ $i ] ) ? $boutons_txt[ $i ] : '',
				'bouton_url'   => isset( $boutons_url[ $i ] ) && is_string( $boutons_url[ $i ] ) ? $boutons_url[ $i ] : '',
			)
		);

		// Les lignes entierement vides sont ignorees.
		if ( ditl_livrable_livrable_vide( $livrable ) ) {
			continue;
		}

		$livrables[] = $livrable;
	}

	return $livrables;
}

/**
 * Enregistre les champs de la metabox.
 *
 * @param int $post_id ID de la page enregistree.
 */
function ditl_livrable_save_metabox( $post_id ) {
	if ( ! ditl_metabox_peut_enregistrer( $post_id, 'ditl_livrable_nonce', 'ditl_livrable_save_' . $post_id ) ) {
		return;
	}

	// La metabox est rendue (masquee) sur toutes les pages : on n'ecrit les
	// metas que si le gabarit est reellement selectionne, sans les effacer
	// quand la page passe temporairement sur un autre modele.
	if ( DITL_TPL_LIVRABLE !== get_page_template_slug( $post_id ) ) {
		return;
	}

	// Carte interactive (choix de la carte + alternative textuelle).
	$carte = array(
		'map_id'      => isset( $_POST['ditl_livrable_map_id'] ) ? ditl_livrable_map_id_valide( wp_unslash( $_POST['ditl_livrable_map_id'] ) ) : 0,
		'alternative' => isset( $_POST['ditl_livrable_carte_alt'] ) && is_string( $_POST['ditl_livrable_carte_alt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ditl_livrable_carte_alt'] ) ) : '',
	);
	update_post_meta( $post_id, '_ditl_livrable_carte', wp_slash( (string) wp_json_encode( $carte, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );

	// Sections de livrables (lignes repetables).
	$livrables = ditl_livrable_lire_livrables_post();
	update_post_meta( $post_id, '_ditl_livrables', wp_slash( (string) wp_json_encode( $livrables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
}
add_action( 'save_post_page', 'ditl_livrable_save_metabox' );
