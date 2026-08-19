<?php
/**
 * Fonctions communes aux metaboxes des gabarits sur mesure.
 *
 * Utilisees par le gabarit "Projet DiTL" et reutilisables pour les
 * futurs gabarits de la refonte (un fichier par gabarit dans ce dossier).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifie qu'un enregistrement de metabox est legitime.
 *
 * Regroupe les controles obligatoires avant toute ecriture de meta :
 * autosave, revision, nonce et capacite de l'utilisateur.
 *
 * @param int    $post_id      ID de la page en cours d'enregistrement.
 * @param string $nonce_name   Nom du champ nonce poste.
 * @param string $nonce_action Action du nonce.
 * @return bool True si l'ecriture des metas est autorisee.
 */
function ditl_metabox_peut_enregistrer( $post_id, $nonce_name, $nonce_action ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return false;
	}

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return false;
	}

	if ( ! isset( $_POST[ $nonce_name ] ) ) {
		return false;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) );

	if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
		return false;
	}

	if ( ! current_user_can( 'edit_page', $post_id ) ) {
		return false;
	}

	return true;
}

/**
 * Callback d'autorisation des metas protegees des gabarits.
 *
 * @param bool   $allowed  Autorisation courante.
 * @param string $meta_key Cle de la meta.
 * @param int    $post_id  ID de la page.
 * @param int    $user_id  ID de l'utilisateur.
 * @return bool True si l'utilisateur peut editer la page.
 */
function ditl_meta_auth_callback( $allowed, $meta_key, $post_id, $user_id ) {
	return user_can( $user_id, 'edit_page', $post_id );
}

/**
 * Nettoie une liste de sections {title, content} encodee en JSON.
 *
 * Titre : texte simple. Contenu : HTML limite aux balises autorisees
 * dans un contenu de publication (wp_kses_post).
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (tableau vide si invalide).
 */
function ditl_sanitize_sections_json( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '[]';
	}

	$sections = json_decode( (string) $value, true );

	if ( ! is_array( $sections ) ) {
		return '[]';
	}

	$propres = array();

	foreach ( $sections as $section ) {
		if ( ! is_array( $section ) ) {
			continue;
		}

		// Rejet des valeurs non scalaires (JSON inattendu) avant tout cast.
		$title   = isset( $section['title'] ) && is_scalar( $section['title'] ) ? sanitize_text_field( (string) $section['title'] ) : '';
		$content = isset( $section['content'] ) && is_scalar( $section['content'] ) ? wp_kses_post( (string) $section['content'] ) : '';

		if ( '' === $title && '' === trim( wp_strip_all_tags( $content ) ) ) {
			continue;
		}

		$propres[] = array(
			'title'   => $title,
			'content' => $content,
		);
	}

	return (string) wp_json_encode( $propres, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Nettoie une liste d'IDs d'attachements encodee en JSON.
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON d'entiers positifs (tableau vide si invalide).
 */
function ditl_sanitize_ids_json( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '[]';
	}

	$ids = json_decode( (string) $value, true );

	if ( ! is_array( $ids ) ) {
		return '[]';
	}

	// Meme garde-fou que les listes repetables.
	$ids = array_slice( $ids, 0, 100 );

	$propres = array();

	foreach ( $ids as $id ) {
		// Rejet des valeurs non scalaires (JSON inattendu) avant tout cast.
		$id = is_scalar( $id ) ? absint( $id ) : 0;

		if ( $id > 0 ) {
			$propres[] = $id;
		}
	}

	return (string) wp_json_encode( array_values( $propres ) );
}

/**
 * Retourne l'apercu d'un media pour les metaboxes (ecran d'edition).
 *
 * wp_get_attachment_image pose width="1" height="1" sur les SVG de la
 * mediatheque (icones du gabarit Contact), qui n'ont pas de metadonnees
 * d'image : l'apercu serait invisible. Pour ce seul type de fichier, un <img>
 * simple est rendu, dimensionne par le fichier lui-meme et plafonne par la
 * feuille de style admin. Tout autre type conserve exactement la sortie de
 * wp_get_attachment_image (les PDF, notamment, ont une vignette generee mais
 * pas de dimensions racine : les tester sur les metadonnees les casserait).
 *
 * Tout attribut ajoute au markup de repli doit passer par esc_attr().
 *
 * @param mixed $attachment_id ID de l'attachement (0 ou invalide : chaine vide).
 * @return string Markup de l'apercu (deja echappe).
 */
function ditl_metabox_media_preview( $attachment_id ) {
	$attachment_id = absint( $attachment_id );

	if ( $attachment_id <= 0 ) {
		return '';
	}

	if ( 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
		return wp_get_attachment_image( $attachment_id, 'medium' );
	}

	$url = wp_get_attachment_url( $attachment_id );

	if ( ! $url ) {
		return '';
	}

	return '<img src="' . esc_url( $url ) . '" alt="" />';
}

/**
 * Lit une meta stockee en JSON et la retourne sous forme de tableau.
 *
 * @param int    $post_id  ID de la page.
 * @param string $meta_key Cle de la meta.
 * @return array Tableau decode (vide si meta absente ou invalide).
 */
function ditl_get_meta_json( $post_id, $meta_key ) {
	$decoded = json_decode( (string) get_post_meta( $post_id, $meta_key, true ), true );

	return is_array( $decoded ) ? $decoded : array();
}

/**
 * Affiche une ligne de section repetable (markup partage entre gabarits).
 *
 * Utilisee pour les lignes existantes et pour le modele JS (avec l'index
 * litteral "%index%", remplace cote JS a l'ajout d'une section).
 *
 * La barre d'outils de la ligne (numero, monter/descendre, supprimer) n'est
 * pas rendue ici : elle est injectee par le JS commun (metabox-gabarits.js),
 * comme pour toutes les lignes repetables des gabarits.
 *
 * @param array        $args    Options du champ (voir ditl_metabox_render_sections).
 * @param string|int   $index   Index de la ligne (ou "%index%" pour le modele).
 * @param array        $section Donnees {title, content} de la ligne.
 */
function ditl_metabox_render_section_row( $args, $index, $section = array() ) {
	$title   = isset( $section['title'] ) ? (string) $section['title'] : '';
	$content = isset( $section['content'] ) ? (string) $section['content'] : '';
	?>
	<div class="ditl-section">
		<label>
			<span class="ditl-field-label"><?php echo esc_html( $args['title_label'] ); ?></span>
			<input type="text" class="widefat" name="<?php echo esc_attr( $args['prefix'] ); ?>_title[]" value="<?php echo esc_attr( $title ); ?>" />
		</label>
		<span class="ditl-field-label"><?php echo esc_html( $args['content_label'] ); ?></span>
		<textarea class="ditl-section-editor" id="<?php echo esc_attr( $args['prefix'] . '-content-' . $index ); ?>" name="<?php echo esc_attr( $args['prefix'] ); ?>_content[]" rows="8"><?php echo esc_textarea( $content ); ?></textarea>
	</div>
	<?php
}

/**
 * Affiche le champ "sections repetables" partage entre gabarits.
 *
 * Chaque gabarit fournit son propre prefixe de champs : les metaboxes de
 * tous les gabarits etant rendues (masquees) sur le meme ecran, des noms
 * distincts evitent tout melange de donnees a l'enregistrement.
 *
 * @param int   $post_id ID de la page en cours d'edition.
 * @param array $args {
 *     Options du champ.
 *
 *     @type string $meta_key      Meta JSON [{title, content}] a editer.
 *     @type string $prefix        Prefixe des noms de champs postes.
 *     @type string $title_label   Libelle du champ titre.
 *     @type string $content_label Libelle du champ contenu.
 * }
 */
function ditl_metabox_render_sections( $post_id, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'meta_key'      => '_ditl_sections',
			'prefix'        => 'ditl_sections',
			'title_label'   => __( 'Titre de la section', 'ditl' ),
			'content_label' => __( 'Contenu de la section', 'ditl' ),
		)
	);

	$sections = ditl_get_meta_json( $post_id, $args['meta_key'] );
	?>
	<div class="ditl-sections-field">
		<div class="ditl-sections">
			<?php foreach ( $sections as $index => $section ) : ?>
				<?php ditl_metabox_render_section_row( $args, $index, $section ); ?>
			<?php endforeach; ?>
		</div>
		<button type="button" class="button button-secondary ditl-section-add"><?php esc_html_e( 'Ajouter une section', 'ditl' ); ?></button>
		<script type="text/html" class="ditl-section-template">
			<?php ditl_metabox_render_section_row( $args, '%index%' ); ?>
		</script>
	</div>
	<?php
}

/**
 * Lit les sections repetables postees par une metabox de gabarit.
 *
 * Le nonce est verifie en amont par la metabox appelante
 * (ditl_metabox_peut_enregistrer).
 *
 * @param string $prefix Prefixe des noms de champs postes.
 * @return array Sections {title, content} nettoyees (lignes vides ignorees).
 */
function ditl_metabox_lire_sections_post( $prefix ) {
	// Deux tableaux paralleles, l'ordre du DOM fait foi.
	$titles   = isset( $_POST[ $prefix . '_title' ] ) ? array_values( (array) wp_unslash( $_POST[ $prefix . '_title' ] ) ) : array();
	$contents = isset( $_POST[ $prefix . '_content' ] ) ? array_values( (array) wp_unslash( $_POST[ $prefix . '_content' ] ) ) : array();

	// Garde-fou contre un POST anormalement gonfle.
	$total = min( max( count( $titles ), count( $contents ) ), 100 );
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

	return $sections;
}

/**
 * Affiche le champ media partage des metaboxes (valeur cachee, apercu,
 * boutons choisir/retirer).
 *
 * Markup attendu par le JS commun (assets/admin/metabox-gabarits.js) :
 * .ditl-media-field, .ditl-media-value, .ditl-media-preview. Couvre un
 * champ simple ("xxx") comme une ligne repetable ("xxx[]") : le name est
 * repris tel quel.
 *
 * Le HTML des metaboxes est indente en tabulations et les champs media ne
 * vivent pas tous a la meme profondeur selon les gabarits : la profondeur
 * est parametrable pour garder une sortie strictement identique aux blocs
 * remplaces (l'appelant place le bloc, cette fonction indente l'interieur).
 *
 * @param string $name          Attribut name du champ cache.
 * @param int    $attachment_id ID de l'attachement (0 si aucun).
 * @param int    $profondeur    Tabulations de la ligne d'appel (defaut 2).
 */
function ditl_metabox_render_media_field( $name, $attachment_id, $profondeur = 2 ) {
	$t0 = str_repeat( "\t", $profondeur );
	$t1 = $t0 . "\t";
	$t2 = $t1 . "\t";

	echo '<div class="ditl-media-field">' . "\n";
	echo $t1 . '<input type="hidden" name="' . esc_attr( $name ) . '" class="ditl-media-value" value="' . esc_attr( $attachment_id ? $attachment_id : '' ) . '" />' . "\n";
	echo $t1 . '<div class="ditl-media-preview">' . "\n";
	echo $t2;
	echo ditl_metabox_media_preview( $attachment_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup deja echappe.
	echo $t1 . '</div>' . "\n";
	echo $t1 . '<button type="button" class="button ditl-media-choose">' . esc_html__( 'Choisir une image', 'ditl' ) . '</button>' . "\n";
	echo $t1 . '<button type="button" class="button ditl-media-remove"' . ( $attachment_id ? '' : ' style="display:none"' ) . '>' . esc_html__( 'Retirer l\'image', 'ditl' ) . '</button>' . "\n";
	echo $t0 . '</div>' . "\n";
}

/**
 * Affiche le champ galerie partage des metaboxes (valeur JSON cachee,
 * vignettes triables, bouton de choix).
 *
 * Markup attendu par le JS commun (assets/admin/metabox-gabarits.js) :
 * .ditl-gallery-field, .ditl-gallery-value, .ditl-gallery-preview,
 * .ditl-gallery-item-remove, .ditl-gallery-choose.
 *
 * Meme principe d'indentation parametrable que le champ media (voir
 * ditl_metabox_render_media_field) : sortie strictement identique aux
 * blocs remplaces, y compris les tabulations emises par la boucle.
 *
 * @param string $name       Attribut name du champ cache.
 * @param array  $ids        IDs d'attachements de la galerie.
 * @param int    $profondeur Tabulations de la ligne d'appel (defaut 2).
 */
function ditl_metabox_render_gallery_field( $name, $ids, $profondeur = 2 ) {
	$t0 = str_repeat( "\t", $profondeur );
	$t1 = $t0 . "\t";
	$t2 = $t1 . "\t";
	$t3 = $t2 . "\t";
	$t4 = $t3 . "\t";

	echo '<div class="ditl-gallery-field">' . "\n";
	echo $t1 . '<input type="hidden" name="' . esc_attr( $name ) . '" class="ditl-gallery-value" value="' . esc_attr( (string) wp_json_encode( $ids ) ) . '" />' . "\n";
	echo $t1 . '<ul class="ditl-gallery-preview">' . "\n";
	echo $t2;

	foreach ( $ids as $attachment_id ) {
		echo $t3 . '<li data-id="' . esc_attr( $attachment_id ) . '">' . "\n";
		echo $t4;
		echo wp_get_attachment_image( $attachment_id, 'thumbnail' );
		echo $t4 . '<button type="button" class="button-link ditl-gallery-item-remove" title="' . esc_attr__( 'Retirer cette image', 'ditl' ) . '">&times;</button>' . "\n";
		echo $t3 . '</li>' . "\n";
		echo $t2;
	}

	echo $t1 . '</ul>' . "\n";
	echo $t1 . '<button type="button" class="button ditl-gallery-choose">' . esc_html__( 'Choisir des images', 'ditl' ) . '</button>' . "\n";
	echo $t0 . '</div>' . "\n";
}

/**
 * Retourne la liste des publications proposables dans un selecteur de metabox.
 *
 * Les publications publiees du type demande sont listees, toutes langues
 * confondues ; l'ID deja enregistre est ajoute a la liste s'il n'y figure
 * pas (publication en brouillon par exemple), afin qu'un enregistrement ne
 * le perde pas silencieusement.
 *
 * @param string $post_type            Type de publication a lister.
 * @param int    $id_actuel            ID actuellement enregistre (0 si aucun).
 * @param string $libelle_introuvable  Libelle sprintf (%d = ID) de l'entree
 *                                     ajoutee quand l'ID enregistre est
 *                                     absent de la liste.
 * @return array Liste [ID => libelle].
 */
function ditl_metabox_liste_publications( $post_type, $id_actuel, $libelle_introuvable ) {
	$liste = array();

	$posts = get_posts(
		array(
			'post_type'        => $post_type,
			'post_status'      => 'publish',
			'numberposts'      => 100,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
			// Toutes langues : le choix appartient a chaque page appelante.
			'lang'             => '',
		)
	);

	foreach ( $posts as $ditl_publication ) {
		$titre = '' !== $ditl_publication->post_title ? $ditl_publication->post_title : __( '(sans titre)', 'ditl' );

		/* translators: 1 : titre de la publication, 2 : ID de la publication. */
		$liste[ $ditl_publication->ID ] = sprintf( __( '%1$s (ID %2$d)', 'ditl' ), $titre, $ditl_publication->ID );
	}

	$id_actuel = absint( $id_actuel );

	if ( $id_actuel > 0 && ! isset( $liste[ $id_actuel ] ) ) {
		$liste[ $id_actuel ] = sprintf( $libelle_introuvable, $id_actuel );
	}

	return $liste;
}
