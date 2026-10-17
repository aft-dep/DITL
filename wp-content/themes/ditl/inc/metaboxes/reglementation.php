<?php
/**
 * Metaboxes de la Reglementation (phase 6) : fiche pays et page au gabarit.
 *
 * Modele (decisions D1 a D5 du lot Reglementation) : une
 * publication du type "ditl_reglementation" = un PAYS dans une LANGUE ; une
 * PAGE par langue porte le gabarit "Reglementation" et affiche en onglets
 * les pays publies de sa langue (inc/reglementation.php).
 *
 * Contrat de metas (FIGE - le template page-templates/reglementation.php,
 * le script de demonstration et wpml-config.xml s'appuient dessus) :
 * - _ditl_regl_drapeau_id (int, pays)    : ID d'attachement image du drapeau,
 *                                          0 = aucun. Image decorative (alt
 *                                          vide), commune a toutes les langues.
 * - _ditl_regl_sections   (string, pays) : JSON [{titre, image_id, contenu}]
 *                                          - titre    : la question (texte simple) ;
 *                                          - image_id : image d'illustration
 *                                            decorative, 0 = aucune ;
 *                                          - contenu  : HTML riche (wp_kses_post).
 *                                          Lignes entierement vides ignorees,
 *                                          garde-fou 100 lignes, ordre du DOM =
 *                                          ordre d'affichage.
 * - _ditl_regl_retour     (string, page) : JSON {texte, url} du lien de retour ;
 *                                          url relative si interne (rendue par
 *                                          ditl_href_from_meta_url).
 * Le H1 de la page reste _ditl_hero_title (metabox commune "Banniere du
 * gabarit", registre ditl_gabarits_templates()).
 *
 * Les regles d'affichage (une section n'est affichee que si elle a une
 * question ET un contenu ou une image ; un pays sans section affichable
 * n'apparait pas) sont appliquees a la lecture, pas a l'enregistrement : une
 * question encore sans reponse est conservee en base et apparaitra des que
 * son contenu sera saisi.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Valeur de _wp_page_template declenchant l'affichage de la metabox de page.
define( 'DITL_TPL_REGLEMENTATION', 'page-templates/reglementation.php' );

// Type de publication des fiches pays (enregistre dans inc/reglementation.php).
define( 'DITL_REGL_POST_TYPE', 'ditl_reglementation' );

/**
 * Valide un ID d'attachement image (drapeau, image d'illustration).
 *
 * Seuls un entier ou une chaine de chiffres sont acceptes (un "-5" n'est pas
 * ramene a 5 comme le ferait absint) ; l'ID doit designer un attachement
 * dont le type MIME est image/*. Tout autre cas est ramene a 0.
 *
 * @param mixed $value ID candidat.
 * @return int ID valide, ou 0.
 */
function ditl_reglementation_id_image( $value ) {
	if ( is_int( $value ) ) {
		$attachment_id = $value;
	} elseif ( is_string( $value ) && 1 === preg_match( '/^[0-9]{1,18}$/D', $value ) ) {
		$attachment_id = (int) $value;
	} else {
		return 0;
	}

	if ( $attachment_id <= 0 ) {
		return 0;
	}

	$attachment = get_post( $attachment_id );

	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return 0;
	}

	return 0 === strpos( (string) $attachment->post_mime_type, 'image/' ) ? $attachment_id : 0;
}

/**
 * Indique si un contenu riche est vide pour le visiteur.
 *
 * Vide = aucun texte une fois les balises retirees (les espaces insecables
 * laisses par l'editeur comptent comme vides) et aucune image.
 *
 * @param mixed $html Contenu HTML.
 * @return bool True si rien ne serait affiche.
 */
function ditl_reglementation_contenu_vide( $html ) {
	$html = is_scalar( $html ) ? (string) $html : '';

	if ( false !== stripos( $html, '<img' ) ) {
		return false;
	}

	$texte = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );

	return '' === trim( str_replace( "\xC2\xA0", ' ', $texte ) );
}

/**
 * Nettoie l'ID du drapeau d'un pays (sanitize_callback de la meta).
 *
 * @param mixed $value ID candidat.
 * @return int ID d'attachement image, ou 0.
 */
function ditl_reglementation_sanitize_drapeau( $value ) {
	return ditl_reglementation_id_image( $value );
}

/**
 * Nettoie une section {titre, image_id, contenu}.
 *
 * @param mixed $section Donnees brutes de la section.
 * @return array Section aux cles garanties et valeurs nettoyees.
 */
function ditl_reglementation_sanitize_section( $section ) {
	if ( ! is_array( $section ) ) {
		$section = array();
	}

	// Rejet des valeurs non scalaires (JSON inattendu) avant tout cast.
	foreach ( array( 'titre', 'contenu' ) as $ditl_cle ) {
		if ( isset( $section[ $ditl_cle ] ) && ! is_scalar( $section[ $ditl_cle ] ) ) {
			$section[ $ditl_cle ] = '';
		}
	}

	return array(
		'titre'    => isset( $section['titre'] ) ? sanitize_text_field( (string) $section['titre'] ) : '',
		'image_id' => isset( $section['image_id'] ) ? ditl_reglementation_id_image( $section['image_id'] ) : 0,
		'contenu'  => isset( $section['contenu'] ) ? wp_kses_post( (string) $section['contenu'] ) : '',
	);
}

/**
 * Indique si une section nettoyee est entierement vide.
 *
 * @param array $section Section aux cles garanties.
 * @return bool True si ni question, ni image, ni contenu.
 */
function ditl_reglementation_section_vide( $section ) {
	return '' === $section['titre']
		&& 0 === $section['image_id']
		&& ditl_reglementation_contenu_vide( $section['contenu'] );
}

/**
 * Nettoie la liste des sections d'un pays encodee en JSON.
 *
 * Les lignes entierement vides sont ignorees. Garde-fou : 100 lignes.
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (tableau vide si invalide).
 */
function ditl_reglementation_sanitize_sections_json( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '[]';
	}

	$sections = json_decode( (string) $value, true );

	if ( ! is_array( $sections ) ) {
		return '[]';
	}

	// Meme garde-fou que les autres listes repetables des gabarits.
	$sections = array_slice( $sections, 0, 100 );
	$propres  = array();

	foreach ( $sections as $section ) {
		$section = ditl_reglementation_sanitize_section( $section );

		if ( ditl_reglementation_section_vide( $section ) ) {
			continue;
		}

		$propres[] = $section;
	}

	$json = wp_json_encode( $propres, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	return false === $json ? '[]' : $json;
}

/**
 * Nettoie l'URL du lien de retour.
 *
 * Protocoles admis : http et https, ou URL relative (/chemin, #ancre,
 * ?requete). Une URL relative au protocole ("//hote/...") est refusee, de
 * meme qu'une URL qui porte un protocole sans hote ("http:exemple.org",
 * resolue hors du site par le navigateur). Une
 * URL absolue pointant vers le site lui-meme (hote courant ou production)
 * est enregistree RELATIVE, comme les autres URLs internes des gabarits :
 * la valeur reste juste en local, en preprod et en prod.
 *
 * @param mixed $url URL saisie.
 * @return string URL nettoyee (chaine vide si refusee).
 */
function ditl_reglementation_url_retour( $url ) {
	$url = is_scalar( $url ) ? trim( (string) $url ) : '';

	if ( '' === $url || 0 === strpos( $url, '//' ) ) {
		return '';
	}

	$url = esc_url_raw( $url, array( 'http', 'https' ) );

	if ( '' === $url || 0 === strpos( $url, '//' ) ) {
		return '';
	}

	$parties = wp_parse_url( $url );

	if ( ! is_array( $parties ) ) {
		return '';
	}

	if ( empty( $parties['host'] ) ) {
		return isset( $parties['scheme'] ) ? '' : $url;
	}

	// Meme liste d'hotes internes que ditl_cli_url_relative() (cli/commun.php,
	// reservee aux scripts CLI) : a tenir alignees.
	$hotes_internes = array( 'ditlproject.eu', 'www.ditlproject.eu' );
	$hote_site      = (string) wp_parse_url( home_url(), PHP_URL_HOST );

	if ( '' !== $hote_site ) {
		$hotes_internes[] = strtolower( $hote_site );
	}

	if ( ! in_array( strtolower( $parties['host'] ), $hotes_internes, true ) ) {
		return $url;
	}

	$relative = isset( $parties['path'] ) && '' !== $parties['path'] ? $parties['path'] : '/';

	if ( isset( $parties['query'] ) ) {
		$relative .= '?' . $parties['query'];
	}

	if ( isset( $parties['fragment'] ) ) {
		$relative .= '#' . $parties['fragment'];
	}

	return $relative;
}

/**
 * Nettoie l'objet JSON {texte, url} du lien de retour de la page.
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (objet aux cles garanties).
 */
function ditl_reglementation_sanitize_retour_json( $value ) {
	$data = is_scalar( $value ) ? json_decode( (string) $value, true ) : null;

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$propre = array(
		'texte' => isset( $data['texte'] ) && is_scalar( $data['texte'] ) ? sanitize_text_field( (string) $data['texte'] ) : '',
		'url'   => isset( $data['url'] ) ? ditl_reglementation_url_retour( $data['url'] ) : '',
	);

	$json = wp_json_encode( $propre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	return false === $json ? '{"texte":"","url":""}' : $json;
}

/**
 * Declare les metas de la Reglementation (protegees, avec controle d'acces).
 *
 * Le type de contenu des pays a capability_type "page" : la capacite
 * edit_page du callback commun ditl_meta_auth_callback() s'applique donc
 * aussi aux fiches pays (map_meta_cap resout la capacite selon le type de
 * la publication).
 */
function ditl_reglementation_register_meta() {
	register_post_meta(
		DITL_REGL_POST_TYPE,
		'_ditl_regl_drapeau_id',
		array(
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'sanitize_callback' => 'ditl_reglementation_sanitize_drapeau',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);

	register_post_meta(
		DITL_REGL_POST_TYPE,
		'_ditl_regl_sections',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '[]',
			'sanitize_callback' => 'ditl_reglementation_sanitize_sections_json',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);

	register_post_meta(
		'page',
		'_ditl_regl_retour',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '{}',
			'sanitize_callback' => 'ditl_reglementation_sanitize_retour_json',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', 'ditl_reglementation_register_meta' );

/* -------------------------------------------------------------------------
 * Fiche pays (ecran d'edition du type ditl_reglementation).
 * ---------------------------------------------------------------------- */

/**
 * Ajoute la metabox de la fiche pays.
 */
function ditl_reglementation_add_metabox_pays() {
	add_meta_box(
		'ditl-reglementation-pays',
		__( 'Contenu du pays', 'ditl' ),
		'ditl_reglementation_render_metabox_pays',
		DITL_REGL_POST_TYPE,
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_' . DITL_REGL_POST_TYPE, 'ditl_reglementation_add_metabox_pays' );

/**
 * Affiche une section du pays (ligne existante ou modele JS).
 *
 * Reutilise le markup des lignes repetables (.ditl-section) : ajout,
 * suppression, tri, barre d'outils, choix d'image et editeur riche sont
 * geres par le JS commun (assets/admin/metabox-gabarits.js). L'attribut
 * data-ditl-formats="reglementation" de l'editeur ajoute le format
 * "Encadre d'information" et limite les titres au niveau 4.
 *
 * Chaque ligne poste exactement trois valeurs (question, image, contenu),
 * meme vides : le champ image cache vaut '' sans image. Les trois tableaux
 * paralleles restent ainsi alignes ligne a ligne.
 *
 * @param string|int $index   Index de la ligne (ou "%index%" pour le modele).
 * @param array      $section Donnees de la section (cles du contrat de meta).
 */
function ditl_reglementation_render_section_row( $index, $section = array() ) {
	$titre    = isset( $section['titre'] ) && is_scalar( $section['titre'] ) ? (string) $section['titre'] : '';
	$image_id = isset( $section['image_id'] ) ? ditl_reglementation_id_image( $section['image_id'] ) : 0;
	$contenu  = isset( $section['contenu'] ) && is_scalar( $section['contenu'] ) ? (string) $section['contenu'] : '';
	?>
	<div class="ditl-section">
		<label>
			<span class="ditl-field-label"><?php esc_html_e( 'Question (titre de la section)', 'ditl' ); ?></span>
			<input type="text" class="widefat" name="ditl_regl_section_titre[]" value="<?php echo esc_attr( $titre ); ?>" />
		</label>

		<span class="ditl-field-label"><?php esc_html_e( 'Image d\'illustration (facultative)', 'ditl' ); ?></span>
		<?php ditl_metabox_render_media_field( 'ditl_regl_section_image[]', $image_id, 2 ); ?>
		<p class="description"><?php esc_html_e( 'Image decorative, affichee en bandeau en tete de la reponse : elle ne doit porter aucune information absente du texte.', 'ditl' ); ?></p>

		<span class="ditl-field-label"><?php esc_html_e( 'Contenu de la section', 'ditl' ); ?></span>
		<textarea class="ditl-section-editor" id="<?php echo esc_attr( 'ditl-regl-contenu-' . $index ); ?>" name="ditl_regl_section_contenu[]" rows="10" data-ditl-formats="reglementation"><?php echo esc_textarea( $contenu ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Encadre "Plus d\'informations" : ecrire le paragraphe (texte et lien), puis le selectionner et choisir "Encadre d\'information" dans le menu "Formats" de la barre d\'outils. L\'icone (i) est ajoutee automatiquement sur le site.', 'ditl' ); ?></p>
	</div>
	<?php
}

/**
 * Affiche les champs de la fiche pays.
 *
 * @param WP_Post $post Pays en cours d'edition.
 */
function ditl_reglementation_render_metabox_pays( $post ) {
	wp_nonce_field( 'ditl_regl_pays_save_' . $post->ID, 'ditl_regl_pays_nonce' );

	$drapeau_id = ditl_reglementation_id_image( get_post_meta( $post->ID, '_ditl_regl_drapeau_id', true ) );
	$sections   = ditl_get_meta_json( $post->ID, '_ditl_regl_sections' );
	?>
	<div class="ditl-metabox">
		<p class="description">
			<?php esc_html_e( 'Une fiche = un pays dans une langue. Le titre saisi en haut de l\'ecran est le nom du pays affiche dans son onglet sur la page "Reglementation". Seuls les pays publies apparaissent ; un brouillon reste invisible.', 'ditl' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Ordre des onglets : champ "Ordre" de l\'encadre "Attributs" (1 pour le premier pays, 2 pour le suivant...). Traduction : creer la traduction depuis l\'encadre des langues ; chaque langue a sa propre fiche, le drapeau et l\'ordre sont repris automatiquement.', 'ditl' ); ?>
		</p>

		<h3><?php esc_html_e( 'Drapeau', 'ditl' ); ?></h3>

		<div class="ditl-field">
			<span class="ditl-field-label"><?php esc_html_e( 'Drapeau du pays', 'ditl' ); ?></span>
			<?php ditl_metabox_render_media_field( 'ditl_regl_drapeau_id', $drapeau_id, 3 ); ?>
			<p class="description"><?php esc_html_e( 'Affiche a cote du nom du pays dans l\'onglet. Image decorative (le nom du pays suffit) : aucun texte alternatif n\'est necessaire. Image carree conseillee, 128 x 128 pixels au minimum.', 'ditl' ); ?></p>
		</div>

		<h3><?php esc_html_e( 'Questions', 'ditl' ); ?></h3>

		<p class="description">
			<?php esc_html_e( 'Chaque section est une question que le visiteur deplie pour lire la reponse. Une section n\'est affichee sur le site que si sa question est renseignee ET qu\'elle a un contenu ou une image : une question sans reponse peut donc etre preparee a l\'avance sans apparaitre. Un pays sans aucune section affichable n\'apparait pas dans les onglets. L\'ordre ci-dessous est l\'ordre d\'affichage (fleches pour deplacer une section).', 'ditl' ); ?>
		</p>

		<div class="ditl-field">
			<div class="ditl-sections-field" data-row-label="<?php echo esc_attr( /* translators: %d : numero d'ordre de la question. */ __( 'Question %d', 'ditl' ) ); ?>">
				<div class="ditl-sections">
					<?php foreach ( $sections as $index => $section ) : ?>
						<?php ditl_reglementation_render_section_row( $index, $section ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button button-secondary ditl-section-add"><?php esc_html_e( 'Ajouter une question', 'ditl' ); ?></button>
				<script type="text/html" class="ditl-section-template">
					<?php ditl_reglementation_render_section_row( '%index%' ); ?>
				</script>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Lit les sections postees par la fiche pays.
 *
 * Le nonce est verifie en amont (ditl_metabox_peut_enregistrer).
 *
 * @return array Sections {titre, image_id, contenu} nettoyees (lignes vides
 *               ignorees).
 */
function ditl_reglementation_lire_sections_post() {
	// Tableaux paralleles, l'ordre du DOM fait foi (une valeur par ligne et
	// par champ, y compris le champ image cache vide).
	$titres   = isset( $_POST['ditl_regl_section_titre'] ) ? array_values( (array) wp_unslash( $_POST['ditl_regl_section_titre'] ) ) : array();
	$images   = isset( $_POST['ditl_regl_section_image'] ) ? array_values( (array) wp_unslash( $_POST['ditl_regl_section_image'] ) ) : array();
	$contenus = isset( $_POST['ditl_regl_section_contenu'] ) ? array_values( (array) wp_unslash( $_POST['ditl_regl_section_contenu'] ) ) : array();

	// Garde-fou contre un POST anormalement gonfle.
	$total    = min( max( count( $titres ), count( $images ), count( $contenus ) ), 100 );
	$sections = array();

	for ( $i = 0; $i < $total; $i++ ) {
		$section = ditl_reglementation_sanitize_section(
			array(
				'titre'    => isset( $titres[ $i ] ) && is_string( $titres[ $i ] ) ? $titres[ $i ] : '',
				'image_id' => isset( $images[ $i ] ) && is_string( $images[ $i ] ) ? $images[ $i ] : 0,
				'contenu'  => isset( $contenus[ $i ] ) && is_string( $contenus[ $i ] ) ? $contenus[ $i ] : '',
			)
		);

		// Les lignes entierement vides sont ignorees.
		if ( ditl_reglementation_section_vide( $section ) ) {
			continue;
		}

		$sections[] = $section;
	}

	return $sections;
}

/**
 * Enregistre les champs de la fiche pays.
 *
 * @param int $post_id ID du pays enregistre.
 */
function ditl_reglementation_save_metabox_pays( $post_id ) {
	if ( ! ditl_metabox_peut_enregistrer( $post_id, 'ditl_regl_pays_nonce', 'ditl_regl_pays_save_' . $post_id ) ) {
		return;
	}

	// Drapeau (image de la mediatheque, 0 si aucun).
	$drapeau_id = isset( $_POST['ditl_regl_drapeau_id'] ) && is_string( $_POST['ditl_regl_drapeau_id'] ) ? ditl_reglementation_id_image( wp_unslash( $_POST['ditl_regl_drapeau_id'] ) ) : 0;
	update_post_meta( $post_id, '_ditl_regl_drapeau_id', $drapeau_id );

	// Sections (lignes repetables).
	$sections = ditl_reglementation_lire_sections_post();
	update_post_meta( $post_id, '_ditl_regl_sections', wp_slash( (string) wp_json_encode( $sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
}
add_action( 'save_post_' . DITL_REGL_POST_TYPE, 'ditl_reglementation_save_metabox_pays' );

/* -------------------------------------------------------------------------
 * Page au gabarit "Reglementation" (ecran d'edition de page).
 * ---------------------------------------------------------------------- */

/**
 * Ajoute la metabox du gabarit sur l'ecran d'edition des pages.
 */
function ditl_reglementation_add_metabox_page() {
	add_meta_box(
		'ditl-reglementation-page',
		__( 'Contenu du gabarit Reglementation', 'ditl' ),
		'ditl_reglementation_render_metabox_page',
		'page',
		'normal',
		'high',
		array( '__block_editor_compatible_meta_box' => true )
	);
}
add_action( 'add_meta_boxes_page', 'ditl_reglementation_add_metabox_page' );

/**
 * Affiche les champs de la metabox de page.
 *
 * @param WP_Post $post Page en cours d'edition.
 */
function ditl_reglementation_render_metabox_page( $post ) {
	wp_nonce_field( 'ditl_regl_page_save_' . $post->ID, 'ditl_regl_page_nonce' );

	$retour = ditl_get_meta_json( $post->ID, '_ditl_regl_retour' );
	$texte  = isset( $retour['texte'] ) && is_scalar( $retour['texte'] ) ? (string) $retour['texte'] : '';
	$url    = isset( $retour['url'] ) && is_scalar( $retour['url'] ) ? (string) $retour['url'] : '';
	?>
	<div class="ditl-metabox">
		<p class="description">
			<?php esc_html_e( 'Ces champs alimentent le gabarit "Reglementation". Ils ne sont utilises que lorsque ce modele de page est selectionne. Le titre H1 de la page se regle dans la metabox "Banniere du gabarit" (champ "Titre de la banniere (H1)" ; s\'il est vide, le titre de la page est utilise). Ce gabarit n\'a pas d\'image de banniere : laisser le champ image vide.', 'ditl' ); ?>
		</p>
		<p class="description">
			<?php
			printf(
				/* translators: %s : lien vers la liste des pays. */
				esc_html__( 'Les pays affiches en onglets se gerent dans le menu %s du tableau de bord : la page affiche automatiquement les pays publies dans sa langue, dans l\'ordre choisi sur chaque fiche.', 'ditl' ),
				'<a href="' . esc_url( admin_url( 'edit.php?post_type=' . DITL_REGL_POST_TYPE ) ) . '">' . esc_html__( 'Reglementation', 'ditl' ) . '</a>'
			);
			?>
		</p>

		<h3><?php esc_html_e( 'Lien de retour', 'ditl' ); ?></h3>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-regl-retour-texte"><?php esc_html_e( 'Texte du lien', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-regl-retour-texte" name="ditl_regl_retour_texte" value="<?php echo esc_attr( $texte ); ?>" />
		</div>

		<div class="ditl-field">
			<label class="ditl-field-label" for="ditl-regl-retour-url"><?php esc_html_e( 'URL du lien', 'ditl' ); ?></label>
			<input type="text" class="widefat" id="ditl-regl-retour-url" name="ditl_regl_retour_url" value="<?php echo esc_attr( $url ); ?>" />
			<p class="description"><?php esc_html_e( 'Lien affiche au-dessus du titre (ex. "Retourner a la page d\'accueil", vers l\'accueil de la langue de la page). Il n\'apparait que si le texte et l\'URL sont renseignes. Une adresse du site est enregistree en relatif (ex. /fr/accueil/).', 'ditl' ); ?></p>
		</div>
	</div>
	<?php
}

/**
 * Enregistre les champs de la metabox de page.
 *
 * @param int $post_id ID de la page enregistree.
 */
function ditl_reglementation_save_metabox_page( $post_id ) {
	if ( ! ditl_metabox_peut_enregistrer( $post_id, 'ditl_regl_page_nonce', 'ditl_regl_page_save_' . $post_id ) ) {
		return;
	}

	// La metabox est rendue (masquee) sur toutes les pages : on n'ecrit la
	// meta que si le gabarit est reellement selectionne, sans l'effacer
	// quand la page passe temporairement sur un autre modele.
	if ( DITL_TPL_REGLEMENTATION !== get_page_template_slug( $post_id ) ) {
		return;
	}

	$retour = ditl_reglementation_sanitize_retour_json(
		wp_json_encode(
			array(
				'texte' => isset( $_POST['ditl_regl_retour_texte'] ) && is_string( $_POST['ditl_regl_retour_texte'] ) ? wp_unslash( $_POST['ditl_regl_retour_texte'] ) : '',
				'url'   => isset( $_POST['ditl_regl_retour_url'] ) && is_string( $_POST['ditl_regl_retour_url'] ) ? wp_unslash( $_POST['ditl_regl_retour_url'] ) : '',
			)
		)
	);
	update_post_meta( $post_id, '_ditl_regl_retour', wp_slash( $retour ) );
}
add_action( 'save_post_page', 'ditl_reglementation_save_metabox_page' );
