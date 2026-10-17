<?php
/**
 * Metabox du gabarit "E-learning".
 *
 * Contrat de metas (FIGE - inc/elearning.php s'appuie dessus) :
 * - _ditl_elearning_modules (string) : JSON [{dossier, titre, description}]
 *                                      Modules affiches sous le contenu de la
 *                                      page, dans l'ordre :
 *                                      - dossier     : nom du sous-dossier de
 *                                        l'export dans le dossier des modules
 *                                        (voir inc/elearning.php) ;
 *                                      - titre       : titre H2 visible au-dessus
 *                                        du module (texte simple, facultatif) ;
 *                                      - description : texte d'introduction du
 *                                        module, HTML riche (facultatif).
 *
 * Le titre et le contenu de la page restent ceux de l'editeur de WordPress
 * (le gabarit reprend le modele de page d'Astra) : la metabox ne gere que
 * les modules. Elle n'est visible que lorsque le modele de page
 * "E-learning" est selectionne (bascule geree en JS, editeur classique et
 * Gutenberg).
 *
 * Un dossier enregistre mais absent de l'environnement (export pas encore
 * depose) est conserve a l'enregistrement et signale dans la liste : il
 * s'affichera des que l'export sera depose.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Valeur de _wp_page_template declenchant l'affichage de la metabox.
define( 'DITL_TPL_ELEARNING', 'page-templates/elearning.php' );

/**
 * Nettoie un module {dossier, titre, description}.
 *
 * @param array $module Donnees brutes du module.
 * @return array Module aux cles garanties et valeurs nettoyees.
 */
function ditl_elearning_sanitize_module( $module ) {
	if ( ! is_array( $module ) ) {
		$module = array();
	}

	// Rejet des valeurs non scalaires (JSON inattendu) avant tout cast.
	foreach ( array( 'dossier', 'titre', 'description' ) as $ditl_cle ) {
		if ( isset( $module[ $ditl_cle ] ) && ! is_scalar( $module[ $ditl_cle ] ) ) {
			$module[ $ditl_cle ] = '';
		}
	}

	$dossier = isset( $module['dossier'] ) ? trim( (string) $module['dossier'] ) : '';

	return array(
		'dossier'     => ditl_elearning_dossier_valide( $dossier ) ? $dossier : '',
		'titre'       => isset( $module['titre'] ) ? sanitize_text_field( (string) $module['titre'] ) : '',
		'description' => isset( $module['description'] ) ? wp_kses_post( (string) $module['description'] ) : '',
	);
}

/**
 * Nettoie la liste de modules encodee en JSON.
 *
 * Un module sans dossier valide est ignore. Garde-fou : 100 modules maximum.
 *
 * @param mixed $value Chaine JSON a nettoyer.
 * @return string Chaine JSON nettoyee (tableau vide si invalide).
 */
function ditl_elearning_sanitize_modules_json( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '[]';
	}

	$modules = json_decode( (string) $value, true );

	if ( ! is_array( $modules ) ) {
		return '[]';
	}

	// Meme garde-fou que les autres listes repetables des gabarits.
	$modules = array_slice( $modules, 0, 100 );
	$propres = array();

	foreach ( $modules as $module ) {
		$module = ditl_elearning_sanitize_module( $module );

		if ( '' === $module['dossier'] ) {
			continue;
		}

		$propres[] = $module;
	}

	return (string) wp_json_encode( $propres, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Declare la meta du gabarit (protegee, avec controle d'acces).
 */
function ditl_elearning_register_meta() {
	register_post_meta(
		'page',
		'_ditl_elearning_modules',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '[]',
			'sanitize_callback' => 'ditl_elearning_sanitize_modules_json',
			'auth_callback'     => 'ditl_meta_auth_callback',
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', 'ditl_elearning_register_meta' );

/**
 * Ajoute la metabox sur l'ecran d'edition des pages.
 */
function ditl_elearning_add_metabox() {
	add_meta_box(
		'ditl-elearning',
		__( 'Modules e-learning', 'ditl' ),
		'ditl_elearning_render_metabox',
		'page',
		'normal',
		'high',
		array( '__block_editor_compatible_meta_box' => true )
	);
}
add_action( 'add_meta_boxes_page', 'ditl_elearning_add_metabox' );

/**
 * Libelle d'un module dans la liste de choix.
 *
 * @param string $dossier Nom du dossier.
 * @param array  $infos   { titre, langue } lus dans l'export.
 * @return string Libelle (a echapper au rendu).
 */
function ditl_elearning_libelle_choix( $dossier, $infos ) {
	$titre  = '' !== $infos['titre'] ? $infos['titre'] : __( '(sans titre)', 'ditl' );
	$langue = '' !== $infos['langue'] ? $infos['langue'] : '?';

	/* translators: 1 : titre declare par l'export, 2 : code de langue de l'export, 3 : nom du dossier. */
	return sprintf( __( '%1$s - langue %2$s (dossier %3$s)', 'ditl' ), $titre, $langue, $dossier );
}

/**
 * Affiche une ligne de module (ligne existante ou modele JS).
 *
 * Reutilise le markup des lignes repetables (.ditl-section) : ajout,
 * suppression, tri, barre d'outils et editeur riche sont geres par le JS
 * commun (assets/admin/metabox-gabarits.js).
 *
 * @param string|int $index   Index de la ligne (ou "%index%" pour le modele).
 * @param array      $choix   Liste [dossier => libelle] des modules proposes.
 * @param array      $module  Donnees du module (cles du contrat de meta).
 */
function ditl_elearning_render_module_row( $index, $choix, $module = array() ) {
	$dossier     = isset( $module['dossier'] ) ? (string) $module['dossier'] : '';
	$titre       = isset( $module['titre'] ) ? (string) $module['titre'] : '';
	$description = isset( $module['description'] ) ? (string) $module['description'] : '';
	?>
	<div class="ditl-section">
		<label>
			<span class="ditl-field-label"><?php esc_html_e( 'Module affiche', 'ditl' ); ?></span>
			<select class="widefat" name="ditl_elearning_dossier[]">
				<option value=""><?php esc_html_e( '- Choisir un module -', 'ditl' ); ?></option>
				<?php foreach ( $choix as $ditl_dossier => $ditl_libelle ) : ?>
					<option value="<?php echo esc_attr( $ditl_dossier ); ?>"<?php selected( $dossier, (string) $ditl_dossier ); ?>><?php echo esc_html( $ditl_libelle ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>

		<label>
			<span class="ditl-field-label"><?php esc_html_e( 'Titre du module (H2, facultatif)', 'ditl' ); ?></span>
			<input type="text" class="widefat" name="ditl_elearning_titre[]" value="<?php echo esc_attr( $titre ); ?>" />
		</label>
		<p class="description"><?php esc_html_e( 'Laisser vide pour un module seul sur la page : le titre de la page suffit.', 'ditl' ); ?></p>

		<span class="ditl-field-label"><?php esc_html_e( 'Introduction du module (facultatif)', 'ditl' ); ?></span>
		<textarea class="ditl-section-editor" id="<?php echo esc_attr( 'ditl-elearning-description-' . $index ); ?>" name="ditl_elearning_description[]" rows="6"><?php echo esc_textarea( $description ); ?></textarea>
	</div>
	<?php
}

/**
 * Affiche les champs de la metabox.
 *
 * @param WP_Post $post Page en cours d'edition.
 */
function ditl_elearning_render_metabox( $post ) {
	wp_nonce_field( 'ditl_elearning_save_' . $post->ID, 'ditl_elearning_nonce' );

	$modules     = ditl_get_meta_json( $post->ID, '_ditl_elearning_modules' );
	$disponibles = ditl_elearning_modules_disponibles();
	$choix       = array();

	foreach ( $disponibles['modules'] as $ditl_dossier => $ditl_infos ) {
		$choix[ $ditl_dossier ] = ditl_elearning_libelle_choix( $ditl_dossier, $ditl_infos );
	}

	// Un dossier enregistre mais absent de cet environnement reste dans la
	// liste : un enregistrement de la page ne le perd pas silencieusement.
	foreach ( $modules as $ditl_module ) {
		$ditl_dossier = isset( $ditl_module['dossier'] ) ? (string) $ditl_module['dossier'] : '';

		if ( ditl_elearning_dossier_valide( $ditl_dossier ) && ! isset( $choix[ $ditl_dossier ] ) ) {
			/* translators: %s : nom du dossier du module. */
			$choix[ $ditl_dossier ] = sprintf( __( 'Dossier %s (introuvable sur ce site : module non affiche)', 'ditl' ), $ditl_dossier );
		}
	}
	?>
	<div class="ditl-metabox">
		<p class="description">
			<?php esc_html_e( 'Les modules choisis s\'affichent sous le contenu de la page, dans l\'ordre de la liste. Le titre et le texte de la page se modifient dans l\'editeur habituel. Choisir le module de la langue de la page.', 'ditl' ); ?>
		</p>

		<?php if ( array() === $disponibles['modules'] ) : ?>
			<p class="description"><?php esc_html_e( 'Aucun module n\'a ete trouve sur ce site : les exports eXeLearning doivent d\'abord etre deposes dans le dossier des modules.', 'ditl' ); ?></p>
		<?php endif; ?>

		<?php if ( array() !== $disponibles['ignores'] ) : ?>
			<p class="description">
				<?php
				/* translators: %s : liste des dossiers ignores. */
				echo esc_html( sprintf( __( 'Dossiers ignores (nom autorise : minuscules, chiffres, tirets et soulignes) : %s', 'ditl' ), implode( ', ', $disponibles['ignores'] ) ) );
				?>
			</p>
		<?php endif; ?>

		<div class="ditl-field">
			<div class="ditl-sections-field" data-row-label="<?php echo esc_attr( /* translators: %d : numero d'ordre du module. */ __( 'Module %d', 'ditl' ) ); ?>">
				<div class="ditl-sections">
					<?php foreach ( $modules as $index => $module ) : ?>
						<?php ditl_elearning_render_module_row( $index, $choix, $module ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button button-secondary ditl-section-add"><?php esc_html_e( 'Ajouter un module', 'ditl' ); ?></button>
				<script type="text/html" class="ditl-section-template">
					<?php ditl_elearning_render_module_row( '%index%', $choix ); ?>
				</script>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Lit les modules postes par la metabox.
 *
 * Le nonce est verifie en amont (ditl_metabox_peut_enregistrer).
 *
 * @return array Modules {dossier, titre, description} nettoyes (lignes sans
 *               dossier valide ignorees).
 */
function ditl_elearning_lire_modules_post() {
	// Tableaux paralleles, l'ordre du DOM fait foi.
	$dossiers     = isset( $_POST['ditl_elearning_dossier'] ) ? array_values( (array) wp_unslash( $_POST['ditl_elearning_dossier'] ) ) : array();
	$titres       = isset( $_POST['ditl_elearning_titre'] ) ? array_values( (array) wp_unslash( $_POST['ditl_elearning_titre'] ) ) : array();
	$descriptions = isset( $_POST['ditl_elearning_description'] ) ? array_values( (array) wp_unslash( $_POST['ditl_elearning_description'] ) ) : array();

	// Garde-fou contre un POST anormalement gonfle.
	$total   = min( max( count( $dossiers ), count( $titres ), count( $descriptions ) ), 100 );
	$modules = array();

	for ( $i = 0; $i < $total; $i++ ) {
		$module = ditl_elearning_sanitize_module(
			array(
				'dossier'     => isset( $dossiers[ $i ] ) && is_string( $dossiers[ $i ] ) ? $dossiers[ $i ] : '',
				'titre'       => isset( $titres[ $i ] ) && is_string( $titres[ $i ] ) ? $titres[ $i ] : '',
				'description' => isset( $descriptions[ $i ] ) && is_string( $descriptions[ $i ] ) ? $descriptions[ $i ] : '',
			)
		);

		// Une ligne sans module choisi est ignoree.
		if ( '' === $module['dossier'] ) {
			continue;
		}

		$modules[] = $module;
	}

	return $modules;
}

/**
 * Enregistre les champs de la metabox.
 *
 * @param int $post_id ID de la page enregistree.
 */
function ditl_elearning_save_metabox( $post_id ) {
	if ( ! ditl_metabox_peut_enregistrer( $post_id, 'ditl_elearning_nonce', 'ditl_elearning_save_' . $post_id ) ) {
		return;
	}

	// La metabox est rendue (masquee) sur toutes les pages : on n'ecrit la
	// meta que si le gabarit est reellement selectionne, sans l'effacer
	// quand la page passe temporairement sur un autre modele.
	if ( DITL_TPL_ELEARNING !== get_page_template_slug( $post_id ) ) {
		return;
	}

	$modules = ditl_elearning_lire_modules_post();
	update_post_meta( $post_id, '_ditl_elearning_modules', wp_slash( (string) wp_json_encode( $modules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
}
add_action( 'save_post_page', 'ditl_elearning_save_metabox' );
