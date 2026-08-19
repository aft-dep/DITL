<?php
/**
 * Metabox du gabarit "Partenaires".
 *
 * Contrat de metas (FIGE - le template page-templates/partenaires.php s'appuie dessus) :
 * - _ditl_intro_content (string) : texte d'introduction, HTML riche - meta PARTAGEE avec le
 *                                  gabarit Resultats (declaree par resultats.php).
 * - _ditl_partenaires   (string) : JSON [{pays, partenaires: [{logo_id, titre, texte,
 *                                  bouton_texte, bouton_url, image_extra_id}]}]
 *                                  Groupes de partenaires par pays :
 *                                  - pays           : nom du pays (H2), texte simple ;
 *                                  - logo_id        : logo du partenaire (ID d'attachement) ;
 *                                  - titre          : titre H3 du partenaire, HTML riche
 *                                    (em/strong/span autorises par wp_kses_post, y compris
 *                                    l'attribut style herite de l'existant) ;
 *                                  - texte          : presentation du partenaire, HTML riche ;
 *                                  - bouton_texte   : libelle du bouton "Site web" ;
 *                                  - bouton_url     : URL du bouton (externe en general) ;
 *                                  - image_extra_id : image optionnelle affichee apres le
 *                                    bouton (ID d'attachement, 0 si absente).
 *
 * Dans la metabox, les partenaires sont saisis en lignes repetables a plat,
 * chacune portant son pays : a l'enregistrement, les lignes consecutives de
 * meme pays sont regroupees (un pays vide rattache la ligne au groupe en
 * cours). Le rendu de la metabox aplatit les groupes dans l'autre sens :
 * l'aller-retour est stable.
 *
 * Les metas de banniere (_ditl_hero_image_id, _ditl_hero_title) sont gerees
 * par la metabox commune inc/metaboxes/banniere.php.
 *
 * La metabox n'est visible que lorsque le modele de page "Partenaires"
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
define( 'DITL_TPL_PARTENAIRES', 'page-templates/partenaires.php' );

/**
 * Nettoie un partenaire {logo_id, titre, texte, bouton_texte, bouton_url, image_extra_id}.
 *
 * @param array $partenaire Donnees brutes du partenaire.
 * @return array Partenaire aux cles garanties et valeurs nettoyees.
 */
function ditl_partenaires_sanitize_partenaire( $partenaire ) {
	if ( ! is_array( $partenaire ) ) {
		$partenaire = array();
	}

	// Rejet des valeurs non scalaires (JSON inattendu) avant tout cast.
	foreach ( array( 'titre', 'texte', 'bouton_texte', 'bouton_url' ) as $ditl_cle_texte ) {
		if ( isset( $partenaire[ $ditl_cle_texte ] ) && ! is_scalar( $partenaire[ $ditl_cle_texte ] ) ) {
			$partenaire[ $ditl_cle_texte ] = '';
		}
	}
	foreach ( array( 'logo_id', 'image_extra_id' ) as $ditl_cle_id ) {
		if ( isset( $partenaire[ $ditl_cle_id ] ) && ! is_scalar( $partenaire[ $ditl_cle_id ] ) ) {
			$partenaire[ $ditl_cle_id ] = 0;
		}
	}

	return array(
		'logo_id'        => isset( $partenaire['logo_id'] ) ? absint( $partenaire['logo_id'] ) : 0,
		'titre'          => isset( $partenaire['titre'] ) ? wp_kses_post( (string) $partenaire['titre'] ) : '',
		'texte'          => isset( $partenaire['texte'] ) ? wp_kses_post( (string) $partenaire['texte'] ) : '',
		'bouton_texte'   => isset( $partenaire['bouton_texte'] ) ? sanitize_text_field( (string) $partenaire['bouton_texte'] ) : '',
		'bouton_url'     => isset( $partenaire['bouton_url'] ) ? esc_url_raw( trim( (string) $partenaire['bouton_url'] ) ) : '',
		'image_extra_id' => isset( $partenaire['image_extra_id'] ) ? absint( $partenaire['image_extra_id'] ) : 0,
	);
}

/**
 * Indique si un partenaire nettoye est entierement vide.
 *
 * @param array $partenaire Partenaire aux cles garanties.
 * @return bool True si aucune donnee utile.
 */
function ditl_partenaires_partenaire_vide( $partenaire ) {
	return 0 === $partenaire['logo_id']
		&& 0 === $partenaire['image_extra_id']
		&& '' === trim( wp_strip_all_tags( $partenaire['titre'] ) )
		&& '' === trim( wp_strip_all_tags( $partenaire['texte'] ) )
		&& '' === $partenaire['bouton_texte']
		&& '' === $partenaire['bouton_url'];
}

/**
 * Nettoie la liste de groupes pays/partenaires encodee en JSON.
 *
 * Les groupes sans partenaire et les partenaires vides sont ignores.
 * Garde-fous : 100 groupes et 100 partenaires au total maximum.
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (tableau vide si invalide).
 */
function ditl_partenaires_sanitize_json( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '[]';
	}

	$groupes = json_decode( (string) $value, true );

	if ( ! is_array( $groupes ) ) {
		return '[]';
	}

	// Meme garde-fou que les autres listes repetables.
	$groupes          = array_slice( $groupes, 0, 100 );
	$propres          = array();
	$total_partenaires = 0;

	foreach ( $groupes as $groupe ) {
		if ( ! is_array( $groupe ) ) {
			continue;
		}

		$pays        = isset( $groupe['pays'] ) ? sanitize_text_field( (string) $groupe['pays'] ) : '';
		$partenaires = isset( $groupe['partenaires'] ) && is_array( $groupe['partenaires'] ) ? $groupe['partenaires'] : array();
		$retenus     = array();

		foreach ( $partenaires as $partenaire ) {
			if ( $total_partenaires >= 100 ) {
				break;
			}

			$partenaire = ditl_partenaires_sanitize_partenaire( $partenaire );

			if ( ditl_partenaires_partenaire_vide( $partenaire ) ) {
				continue;
			}

			$retenus[] = $partenaire;
			$total_partenaires++;
		}

		if ( array() === $retenus ) {
			continue;
		}

		$propres[] = array(
			'pays'        => $pays,
			'partenaires' => $retenus,
		);
	}

	return (string) wp_json_encode( $propres, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Declare la meta du gabarit (protegee, avec controle d'acces).
 *
 * La meta _ditl_intro_content n'est pas redeclaree ici : elle est deja
 * enregistree par resultats.php (meme format, meme sanitize).
 */
function ditl_partenaires_register_meta() {
	register_post_meta(
		'page',
		'_ditl_partenaires',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '[]',
			'sanitize_callback' => 'ditl_partenaires_sanitize_json',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', 'ditl_partenaires_register_meta' );

/**
 * Ajoute la metabox sur l'ecran d'edition des pages.
 */
function ditl_partenaires_add_metabox() {
	add_meta_box(
		'ditl-partenaires',
		__( 'Contenu du gabarit Partenaires', 'ditl' ),
		'ditl_partenaires_render_metabox',
		'page',
		'normal',
		'high',
		array( '__block_editor_compatible_meta_box' => true )
	);
}
add_action( 'add_meta_boxes_page', 'ditl_partenaires_add_metabox' );

/**
 * Affiche une ligne de partenaire (ligne existante ou modele JS).
 *
 * Reutilise le markup des lignes repetables (.ditl-section) : ajout,
 * suppression, tri, barre d'outils et editeurs riches sont geres par le JS
 * commun (assets/admin/metabox-gabarits.js), le selecteur de media par la
 * mecanique .ditl-media-field partagee.
 *
 * @param string|int $index      Index de la ligne (ou "%index%" pour le modele).
 * @param string     $pays       Pays du groupe auquel la ligne appartient.
 * @param array      $partenaire Donnees du partenaire (cles du contrat de meta).
 */
function ditl_partenaires_render_partenaire_row( $index, $pays = '', $partenaire = array() ) {
	$logo_id        = isset( $partenaire['logo_id'] ) ? absint( $partenaire['logo_id'] ) : 0;
	$titre          = isset( $partenaire['titre'] ) ? (string) $partenaire['titre'] : '';
	$texte          = isset( $partenaire['texte'] ) ? (string) $partenaire['texte'] : '';
	$bouton_texte   = isset( $partenaire['bouton_texte'] ) ? (string) $partenaire['bouton_texte'] : '';
	$bouton_url     = isset( $partenaire['bouton_url'] ) ? (string) $partenaire['bouton_url'] : '';
	$image_extra_id = isset( $partenaire['image_extra_id'] ) ? absint( $partenaire['image_extra_id'] ) : 0;
	?>
	<div class="ditl-section">
		<label>
			<span class="ditl-field-label"><?php esc_html_e( 'Pays (titre H2 du groupe)', 'ditl' ); ?></span>
			<input type="text" class="widefat" name="ditl_partenaires_pays[]" value="<?php echo esc_attr( $pays ); ?>" />
		</label>
		<p class="description"><?php esc_html_e( 'Les partenaires consecutifs de meme pays sont regroupes sous un seul titre. Laisser vide pour rattacher ce partenaire au meme pays que le precedent.', 'ditl' ); ?></p>

		<span class="ditl-field-label"><?php esc_html_e( 'Logo du partenaire', 'ditl' ); ?></span>
		<?php ditl_metabox_render_media_field( 'ditl_partenaires_logo_id[]', $logo_id ); ?>

		<label>
			<span class="ditl-field-label"><?php esc_html_e( 'Titre du partenaire (H3)', 'ditl' ); ?></span>
			<textarea class="widefat" name="ditl_partenaires_titre[]" rows="2"><?php echo esc_textarea( $titre ); ?></textarea>
		</label>
		<p class="description"><?php esc_html_e( 'Mise en forme HTML conservee (memes balises que l\'editeur de texte : em, strong, span, liens...). Privilegier une mise en forme legere pour rester fidele au design.', 'ditl' ); ?></p>

		<span class="ditl-field-label"><?php esc_html_e( 'Presentation du partenaire', 'ditl' ); ?></span>
		<textarea class="ditl-section-editor" id="<?php echo esc_attr( 'ditl-partenaires-texte-' . $index ); ?>" name="ditl_partenaires_texte[]" rows="8"><?php echo esc_textarea( $texte ); ?></textarea>

		<label>
			<span class="ditl-field-label"><?php esc_html_e( 'Libelle du bouton', 'ditl' ); ?></span>
			<input type="text" class="widefat" name="ditl_partenaires_bouton_texte[]" value="<?php echo esc_attr( $bouton_texte ); ?>" />
		</label>

		<label>
			<span class="ditl-field-label"><?php esc_html_e( 'URL du bouton', 'ditl' ); ?></span>
			<input type="text" class="widefat" name="ditl_partenaires_bouton_url[]" value="<?php echo esc_attr( $bouton_url ); ?>" />
		</label>
		<p class="description"><?php esc_html_e( 'Site du partenaire (URL externe complete). Pour un lien interne, preferer une URL relative (ex. /partenaires/).', 'ditl' ); ?></p>

		<span class="ditl-field-label"><?php esc_html_e( 'Image complementaire (optionnelle, affichee apres le bouton)', 'ditl' ); ?></span>
		<?php ditl_metabox_render_media_field( 'ditl_partenaires_image_extra_id[]', $image_extra_id ); ?>
	</div>
	<?php
}

/**
 * Affiche les champs de la metabox.
 *
 * @param WP_Post $post Page en cours d'edition.
 */
function ditl_partenaires_render_metabox( $post ) {
	wp_nonce_field( 'ditl_partenaires_save_' . $post->ID, 'ditl_partenaires_nonce' );

	$intro_content = (string) get_post_meta( $post->ID, '_ditl_intro_content', true );
	$groupes       = ditl_get_meta_json( $post->ID, '_ditl_partenaires' );

	// Aplatit les groupes en lignes {pays, partenaire} pour la saisie.
	$lignes = array();

	foreach ( $groupes as $groupe ) {
		if ( ! is_array( $groupe ) ) {
			continue;
		}

		$pays        = isset( $groupe['pays'] ) ? (string) $groupe['pays'] : '';
		$partenaires = isset( $groupe['partenaires'] ) && is_array( $groupe['partenaires'] ) ? $groupe['partenaires'] : array();

		foreach ( $partenaires as $partenaire ) {
			if ( ! is_array( $partenaire ) ) {
				continue;
			}

			$lignes[] = array(
				'pays'       => $pays,
				'partenaire' => $partenaire,
			);
		}
	}
	?>
	<div class="ditl-metabox">
		<p class="description">
			<?php esc_html_e( 'Ces champs alimentent le gabarit "Partenaires". Ils ne sont utilises que lorsque ce modele de page est selectionne. La banniere se regle dans la metabox "Banniere du gabarit".', 'ditl' ); ?>
		</p>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-partenaires-intro"><?php esc_html_e( 'Texte d\'introduction', 'ditl' ); ?></label>
			<textarea class="ditl-richtext-editor" id="ditl-partenaires-intro" name="ditl_partenaires_intro" rows="4"><?php echo esc_textarea( $intro_content ); ?></textarea>
		</div>

		<div class="ditl-field">
			<span class="ditl-field-label"><?php esc_html_e( 'Partenaires par pays', 'ditl' ); ?></span>
			<div class="ditl-sections-field" data-row-label="<?php echo esc_attr( /* translators: %d : numero d'ordre du partenaire. */ __( 'Partenaire %d', 'ditl' ) ); ?>">
				<div class="ditl-sections">
					<?php foreach ( $lignes as $index => $ligne ) : ?>
						<?php ditl_partenaires_render_partenaire_row( $index, $ligne['pays'], $ligne['partenaire'] ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button button-secondary ditl-section-add"><?php esc_html_e( 'Ajouter un partenaire', 'ditl' ); ?></button>
				<script type="text/html" class="ditl-section-template">
					<?php ditl_partenaires_render_partenaire_row( '%index%' ); ?>
				</script>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Lit les lignes de partenaires postees et les regroupe par pays.
 *
 * Les lignes consecutives de meme pays forment un groupe ; une ligne au pays
 * vide est rattachee au groupe en cours. Le nonce est verifie en amont
 * (ditl_metabox_peut_enregistrer).
 *
 * @return array Groupes {pays, partenaires} nettoyes (lignes vides ignorees).
 */
function ditl_partenaires_lire_groupes_post() {
	// Tableaux paralleles, l'ordre du DOM fait foi.
	$pays_list    = isset( $_POST['ditl_partenaires_pays'] ) ? array_values( (array) wp_unslash( $_POST['ditl_partenaires_pays'] ) ) : array();
	$logo_ids     = isset( $_POST['ditl_partenaires_logo_id'] ) ? array_values( (array) wp_unslash( $_POST['ditl_partenaires_logo_id'] ) ) : array();
	$titres       = isset( $_POST['ditl_partenaires_titre'] ) ? array_values( (array) wp_unslash( $_POST['ditl_partenaires_titre'] ) ) : array();
	$textes       = isset( $_POST['ditl_partenaires_texte'] ) ? array_values( (array) wp_unslash( $_POST['ditl_partenaires_texte'] ) ) : array();
	$boutons_txt  = isset( $_POST['ditl_partenaires_bouton_texte'] ) ? array_values( (array) wp_unslash( $_POST['ditl_partenaires_bouton_texte'] ) ) : array();
	$boutons_url  = isset( $_POST['ditl_partenaires_bouton_url'] ) ? array_values( (array) wp_unslash( $_POST['ditl_partenaires_bouton_url'] ) ) : array();
	$images_extra = isset( $_POST['ditl_partenaires_image_extra_id'] ) ? array_values( (array) wp_unslash( $_POST['ditl_partenaires_image_extra_id'] ) ) : array();

	// Garde-fou contre un POST anormalement gonfle.
	$total = min( max( count( $pays_list ), count( $logo_ids ), count( $titres ), count( $textes ) ), 100 );

	$groupes = array();

	for ( $i = 0; $i < $total; $i++ ) {
		$partenaire = ditl_partenaires_sanitize_partenaire(
			array(
				'logo_id'        => isset( $logo_ids[ $i ] ) && is_scalar( $logo_ids[ $i ] ) ? $logo_ids[ $i ] : 0,
				'titre'          => isset( $titres[ $i ] ) && is_string( $titres[ $i ] ) ? $titres[ $i ] : '',
				'texte'          => isset( $textes[ $i ] ) && is_string( $textes[ $i ] ) ? $textes[ $i ] : '',
				'bouton_texte'   => isset( $boutons_txt[ $i ] ) && is_string( $boutons_txt[ $i ] ) ? $boutons_txt[ $i ] : '',
				'bouton_url'     => isset( $boutons_url[ $i ] ) && is_string( $boutons_url[ $i ] ) ? $boutons_url[ $i ] : '',
				'image_extra_id' => isset( $images_extra[ $i ] ) && is_scalar( $images_extra[ $i ] ) ? $images_extra[ $i ] : 0,
			)
		);

		// Les lignes entierement vides sont ignorees.
		if ( ditl_partenaires_partenaire_vide( $partenaire ) ) {
			continue;
		}

		$pays    = isset( $pays_list[ $i ] ) && is_string( $pays_list[ $i ] ) ? sanitize_text_field( $pays_list[ $i ] ) : '';
		$dernier = count( $groupes ) - 1;

		if ( $dernier < 0 || ( '' !== $pays && $groupes[ $dernier ]['pays'] !== $pays ) ) {
			$groupes[] = array(
				'pays'        => $pays,
				'partenaires' => array(),
			);
			$dernier++;
		}

		$groupes[ $dernier ]['partenaires'][] = $partenaire;
	}

	return $groupes;
}

/**
 * Enregistre les champs de la metabox.
 *
 * @param int $post_id ID de la page enregistree.
 */
function ditl_partenaires_save_metabox( $post_id ) {
	if ( ! ditl_metabox_peut_enregistrer( $post_id, 'ditl_partenaires_nonce', 'ditl_partenaires_save_' . $post_id ) ) {
		return;
	}

	// La metabox est rendue (masquee) sur toutes les pages : on n'ecrit les
	// metas que si le gabarit est reellement selectionne, sans les effacer
	// quand la page passe temporairement sur un autre modele.
	if ( DITL_TPL_PARTENAIRES !== get_page_template_slug( $post_id ) ) {
		return;
	}

	// Texte d'introduction (HTML riche).
	$intro_content = isset( $_POST['ditl_partenaires_intro'] ) && is_string( $_POST['ditl_partenaires_intro'] ) ? wp_kses_post( wp_unslash( $_POST['ditl_partenaires_intro'] ) ) : '';
	update_post_meta( $post_id, '_ditl_intro_content', wp_slash( $intro_content ) );

	// Groupes pays/partenaires (lignes repetables regroupees).
	$groupes      = ditl_partenaires_lire_groupes_post();
	$groupes_json = (string) wp_json_encode( $groupes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	update_post_meta( $post_id, '_ditl_partenaires', wp_slash( $groupes_json ) );
}
add_action( 'save_post_page', 'ditl_partenaires_save_metabox' );
