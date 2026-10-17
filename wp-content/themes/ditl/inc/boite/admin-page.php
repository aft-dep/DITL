<?php
/**
 * Metabox "Textes de la boite a outils" des pages.
 *
 * Contrat de la meta (FIGE, voir commun.php) : _ditl_boite_textes, JSON des
 * textes editables du questionnaire ; toute cle vide est omise et remplacee
 * a l'affichage par le texte par defaut traduit (ditl_boite_textes_defaut).
 *
 * - Visible uniquement quand le modele de page DITL_TPL_BOITE est
 *   selectionne : la metabox s'inscrit dans le registre du script commun
 *   des gabarits (ditlMetabox.metaboxes, assets/admin/metabox-gabarits.js)
 *   par un script place juste avant lui, sans toucher a ce script, a
 *   banniere.php ni au registre ditl_gabarits_templates() (qui declencherait
 *   aussi la banniere, les feuilles communes et les retraits de scripts des
 *   gabarits). Si le script commun n'est pas charge, boite-admin.js prend
 *   le relais (bascule autonome).
 * - Chaque champ montre le texte par defaut en texte d'exemple, dans la
 *   langue de la page quand sa traduction est disponible.
 * - Textes riches (aide des etapes, message sans resultat, introduction du
 *   catalogue) : texte simple, une ligne vide = un nouveau paragraphe (les
 *   paragraphes sont poses a l'enregistrement) ; gras, italique, listes et
 *   liens en HTML (liste blanche ditl_boite_html_autorise).
 *
 * Enregistrement : nonce lie a l'ID de page, capacite edit_page, garde
 * autosave/revision (ditl_metabox_peut_enregistrer), ecriture seulement si
 * le modele est selectionne et si les champs sont postes.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Identifiant HTML de la metabox (registre de bascule du script commun).
define( 'DITL_BOITE_METABOX_TEXTES', 'ditl-boite-textes' );

/**
 * Cles simples et riches du contrat _ditl_boite_textes (hors etapes).
 *
 * @return array Tableau {simples: string[], riches: string[]}.
 */
function ditl_boite_page_cles() {
	return array(
		'simples' => array( 'titre', 'bouton_solutions', 'resultats_titre', 'profil_titre', 'filtres_titre', 'catalogue_titre' ),
		'riches'  => array( 'aucun_resultat', 'catalogue_intro' ),
	);
}

/**
 * Ajoute la metabox sur l'ecran d'edition des pages.
 */
function ditl_boite_page_ajouter_metabox() {
	add_meta_box(
		DITL_BOITE_METABOX_TEXTES,
		__( 'Textes de la boîte à outils', 'ditl' ),
		'ditl_boite_page_afficher_metabox',
		'page',
		'normal',
		'high',
		array( '__block_editor_compatible_meta_box' => true )
	);
}
add_action( 'add_meta_boxes_page', 'ditl_boite_page_ajouter_metabox' );

/**
 * Locale correspondant a la langue d'une page (ex. "en_GB" pour "en"),
 * parmi les langues installees.
 *
 * @param int $post_id ID de la page.
 * @return string Locale (vide si indeterminee).
 */
function ditl_boite_page_locale( $post_id ) {
	$lang = ditl_ml_langue_post( $post_id );

	if ( '' === $lang ) {
		return '';
	}

	foreach ( array_merge( get_available_languages(), array( 'en_US' ) ) as $locale ) {
		if ( $locale === $lang || 0 === strpos( $locale, $lang . '_' ) ) {
			return $locale;
		}
	}

	return '';
}

/**
 * Textes par defaut dans la langue de la page (repli : langue de l'admin).
 *
 * @param int $post_id ID de la page.
 * @return array Textes par defaut (cles du contrat).
 */
function ditl_boite_page_textes_defaut( $post_id ) {
	$locale  = ditl_boite_page_locale( $post_id );
	$bascule = '' !== $locale && switch_to_locale( $locale );
	$defauts = ditl_boite_textes_defaut();

	if ( $bascule ) {
		restore_previous_locale();
	}

	return $defauts;
}

/**
 * Prepare un texte riche enregistre pour la zone de texte : un texte fait
 * seulement de paragraphes est rendu en texte simple (lignes vides entre
 * paragraphes), tout autre HTML est montre tel quel.
 *
 * @param string $html HTML enregistre.
 * @return string Valeur de la zone de texte.
 */
function ditl_boite_page_html_vers_texte( $html ) {
	$html = trim( (string) $html );

	if ( '' === $html || preg_match( '#<(?!/?p>|br\s*/?>)[a-z/!]#i', $html ) ) {
		return $html;
	}

	$texte = preg_replace( '#<br\s*/?>\s*#i', "\n", $html );
	$texte = preg_replace( '#</p>\s*<p>#i', "\n\n", (string) $texte );
	$texte = preg_replace( '#</?p>#i', '', (string) $texte );

	return trim( html_entity_decode( (string) $texte, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
}

/**
 * Nettoie un texte riche poste : paragraphes poses (wpautop), balises
 * limitees a la liste blanche de la boite a outils.
 *
 * @param mixed $valeur Valeur postee (sans barres obliques).
 * @return string HTML nettoye (vide si aucun texte).
 */
function ditl_boite_page_texte_riche( $valeur ) {
	if ( ! is_string( $valeur ) || '' === trim( wp_strip_all_tags( $valeur ) ) ) {
		return '';
	}

	return trim( wp_kses( wpautop( trim( $valeur ) ), ditl_boite_html_autorise() ) );
}

/**
 * Affiche un champ de la metabox.
 *
 * @param array $args {
 *     Options du champ.
 *
 *     @type string $id     Identifiant HTML.
 *     @type string $name   Attribut name.
 *     @type string $label  Libelle.
 *     @type string $valeur Valeur saisie (vide : texte par defaut).
 *     @type string $defaut Texte par defaut (texte d'exemple).
 *     @type bool   $riche  True pour une zone de texte riche.
 * }
 */
function ditl_boite_page_champ( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'id'     => '',
			'name'   => '',
			'label'  => '',
			'valeur' => '',
			'defaut' => '',
			'riche'  => false,
		)
	);

	$exemple = trim( wp_strip_all_tags( (string) $args['defaut'] ) );
	?>
	<div class="ditl-field ditl-boite-texte">
		<label class="ditl-field-label" for="<?php echo esc_attr( $args['id'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
		<?php if ( $args['riche'] ) : ?>
			<textarea class="widefat" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>" rows="3" placeholder="<?php echo esc_attr( $exemple ); ?>" aria-describedby="ditl-boite-textes-riches"><?php echo esc_textarea( ditl_boite_page_html_vers_texte( $args['valeur'] ) ); ?></textarea>
		<?php else : ?>
			<input type="text" class="widefat" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>" value="<?php echo esc_attr( $args['valeur'] ); ?>" placeholder="<?php echo esc_attr( $exemple ); ?>" />
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Valeur saisie d'un texte (chaine vide si absente).
 *
 * @param array  $saisis Textes enregistres.
 * @param string $cle    Cle du texte.
 * @return string Valeur.
 */
function ditl_boite_page_valeur( $saisis, $cle ) {
	return isset( $saisis[ $cle ] ) && is_string( $saisis[ $cle ] ) ? $saisis[ $cle ] : '';
}

/**
 * Affiche les champs de la metabox.
 *
 * @param WP_Post $post Page en cours d'edition.
 */
function ditl_boite_page_afficher_metabox( $post ) {
	wp_nonce_field( 'ditl_boite_textes_save_' . $post->ID, 'ditl_boite_textes_nonce' );

	$saisis  = ditl_get_meta_json( $post->ID, '_ditl_boite_textes' );
	$defauts = ditl_boite_page_textes_defaut( $post->ID );
	$etapes  = isset( $saisis['etapes'] ) && is_array( $saisis['etapes'] ) ? $saisis['etapes'] : array();

	$libelles = array(
		'titre'            => __( 'Titre de la boîte à outils', 'ditl' ),
		'bouton_solutions' => __( 'Bouton d\'accès aux résultats', 'ditl' ),
		'resultats_titre'  => __( 'Titre de la page de résultats', 'ditl' ),
		'profil_titre'     => __( 'Titre du récapitulatif des réponses', 'ditl' ),
		'filtres_titre'    => __( 'Titre du panneau de filtres', 'ditl' ),
		'aucun_resultat'   => __( 'Message affiché quand aucun outil ne correspond', 'ditl' ),
		'catalogue_titre'  => __( 'Titre du catalogue de tous les outils', 'ditl' ),
		'catalogue_intro'  => __( 'Introduction du catalogue', 'ditl' ),
	);

	$libelles_etape = array(
		'court'     => __( 'Nom de l\'étape (dans la liste des étapes)', 'ditl' ),
		'question'  => __( 'Question', 'ditl' ),
		'aide'      => __( 'Texte d\'aide', 'ditl' ),
		'etiquette' => __( 'Étiquette de la liste de choix', 'ditl' ),
	);
	?>
	<div class="ditl-metabox ditl-boite-metabox-textes">
		<p class="description">
			<?php esc_html_e( 'Textes du questionnaire et des résultats de la boîte à outils, pour cette page uniquement (une page par langue). Un champ laissé vide affiche le texte par défaut, visible en grisé dans le champ et traduit automatiquement dans la langue de la page.', 'ditl' ); ?>
		</p>
		<p class="description" id="ditl-boite-textes-riches">
			<?php esc_html_e( 'Zones de texte sur plusieurs lignes : une ligne vide sépare deux paragraphes. Gras, italique, listes et liens possibles en HTML (<strong>, <em>, <ul> et <li>, <a href="...">).', 'ditl' ); ?>
		</p>

		<?php
		ditl_boite_page_champ(
			array(
				'id'     => 'ditl-boite-textes-titre',
				'name'   => 'ditl_boite_textes[titre]',
				'label'  => $libelles['titre'],
				'valeur' => ditl_boite_page_valeur( $saisis, 'titre' ),
				'defaut' => $defauts['titre'],
			)
		);
		?>

		<?php $ditl_numero = 0; ?>
		<?php foreach ( ditl_boite_etapes() as $ditl_cle_etape => $ditl_etape ) : ?>
			<?php
			$ditl_numero++;
			$ditl_saisis_etape  = isset( $etapes[ $ditl_cle_etape ] ) && is_array( $etapes[ $ditl_cle_etape ] ) ? $etapes[ $ditl_cle_etape ] : array();
			$ditl_defauts_etape = isset( $defauts['etapes'][ $ditl_cle_etape ] ) ? $defauts['etapes'][ $ditl_cle_etape ] : array();
			?>
			<fieldset class="ditl-boite-etape">
				<legend>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1 : numero de l'etape, 2 : nom de l'etape (ex. Metier). */
							__( 'Étape %1$d : %2$s', 'ditl' ),
							$ditl_numero,
							isset( $ditl_defauts_etape['court'] ) ? $ditl_defauts_etape['court'] : $ditl_cle_etape
						)
					);
					?>
				</legend>
				<?php foreach ( $libelles_etape as $ditl_cle => $ditl_libelle ) : ?>
					<?php
					ditl_boite_page_champ(
						array(
							'id'     => 'ditl-boite-textes-' . $ditl_cle_etape . '-' . $ditl_cle,
							'name'   => 'ditl_boite_textes[etapes][' . $ditl_cle_etape . '][' . $ditl_cle . ']',
							'label'  => $ditl_libelle,
							'valeur' => ditl_boite_page_valeur( $ditl_saisis_etape, $ditl_cle ),
							'defaut' => isset( $ditl_defauts_etape[ $ditl_cle ] ) ? $ditl_defauts_etape[ $ditl_cle ] : '',
							'riche'  => 'aide' === $ditl_cle,
						)
					);
					?>
				<?php endforeach; ?>
			</fieldset>
		<?php endforeach; ?>

		<fieldset class="ditl-boite-etape">
			<legend><?php esc_html_e( 'Résultats et catalogue', 'ditl' ); ?></legend>
			<?php
			$ditl_cles = ditl_boite_page_cles();

			foreach ( array( 'bouton_solutions', 'resultats_titre', 'profil_titre', 'filtres_titre', 'aucun_resultat', 'catalogue_titre', 'catalogue_intro' ) as $ditl_cle ) {
				ditl_boite_page_champ(
					array(
						'id'     => 'ditl-boite-textes-' . str_replace( '_', '-', $ditl_cle ),
						'name'   => 'ditl_boite_textes[' . $ditl_cle . ']',
						'label'  => $libelles[ $ditl_cle ],
						'valeur' => ditl_boite_page_valeur( $saisis, $ditl_cle ),
						'defaut' => isset( $defauts[ $ditl_cle ] ) ? $defauts[ $ditl_cle ] : '',
						'riche'  => in_array( $ditl_cle, $ditl_cles['riches'], true ),
					)
				);
			}
			?>
		</fieldset>
	</div>
	<?php
}

/**
 * Enregistre les textes de la boite a outils.
 *
 * @param int $post_id ID de la page enregistree.
 */
function ditl_boite_page_enregistrer( $post_id ) {
	if ( ! ditl_metabox_peut_enregistrer( $post_id, 'ditl_boite_textes_nonce', 'ditl_boite_textes_save_' . $post_id ) ) {
		return;
	}

	// La metabox est rendue (masquee) sur toutes les pages : on n'ecrit que
	// si le modele Boite a outils est reellement selectionne, sans effacer
	// les textes quand la page passe temporairement sur un autre modele.
	if ( DITL_TPL_BOITE !== get_page_template_slug( $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['ditl_boite_textes'] ) || ! is_array( $_POST['ditl_boite_textes'] ) ) {
		return;
	}

	$poste  = wp_unslash( $_POST['ditl_boite_textes'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- chaque valeur est nettoyee ci-dessous.
	$cles   = ditl_boite_page_cles();
	$textes = array();

	foreach ( $cles['simples'] as $cle ) {
		$textes[ $cle ] = isset( $poste[ $cle ] ) && is_string( $poste[ $cle ] ) ? sanitize_text_field( $poste[ $cle ] ) : '';
	}

	foreach ( $cles['riches'] as $cle ) {
		$textes[ $cle ] = ditl_boite_page_texte_riche( isset( $poste[ $cle ] ) ? $poste[ $cle ] : '' );
	}

	$etapes = isset( $poste['etapes'] ) && is_array( $poste['etapes'] ) ? $poste['etapes'] : array();

	foreach ( array_keys( ditl_boite_etapes() ) as $cle_etape ) {
		$etape = isset( $etapes[ $cle_etape ] ) && is_array( $etapes[ $cle_etape ] ) ? $etapes[ $cle_etape ] : array();

		foreach ( array( 'court', 'question', 'etiquette' ) as $cle ) {
			$textes['etapes'][ $cle_etape ][ $cle ] = isset( $etape[ $cle ] ) && is_string( $etape[ $cle ] ) ? sanitize_text_field( $etape[ $cle ] ) : '';
		}

		$textes['etapes'][ $cle_etape ]['aide'] = ditl_boite_page_texte_riche( isset( $etape['aide'] ) ? $etape['aide'] : '' );
	}

	// Les valeurs vides sont omises par le sanitize de la meta (commun.php).
	update_post_meta( $post_id, '_ditl_boite_textes', wp_slash( (string) wp_json_encode( $textes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
}
add_action( 'save_post_page', 'ditl_boite_page_enregistrer' );

/**
 * Branche la metabox sur la bascule par modele de page et charge les assets
 * de la boite a outils sur l'ecran d'edition des pages.
 *
 * - Script commun des gabarits charge (cas normal) : un court script place
 *   AVANT lui ajoute la metabox a son registre (ditlMetabox.metaboxes, lu au
 *   demarrage du script) ; feuille de la boite chargee seulement si la page
 *   porte deja le modele.
 * - Script commun absent : boite-admin.js assure seul la bascule.
 */
function ditl_boite_page_admin_assets() {
	if ( 'page' !== ditl_boite_admin_ecran() ) {
		return;
	}

	$modeles = array( DITL_TPL_BOITE );
	$repli   = ! wp_script_is( 'ditl-admin-metabox', 'enqueued' );

	if ( ! $repli ) {
		wp_add_inline_script(
			'ditl-admin-metabox',
			sprintf(
				'window.ditlMetabox = window.ditlMetabox || {}; window.ditlMetabox.metaboxes = window.ditlMetabox.metaboxes || {}; window.ditlMetabox.metaboxes[%1$s] = %2$s;',
				wp_json_encode( DITL_BOITE_METABOX_TEXTES ),
				wp_json_encode( $modeles )
			),
			'before'
		);
	}

	$post        = get_post();
	$avec_modele = $post instanceof WP_Post && DITL_TPL_BOITE === get_page_template_slug( $post );

	if ( $repli || $avec_modele ) {
		ditl_boite_admin_charger_assets(
			'page',
			array(
				'pageTextes' => $repli ? array(
					'metabox' => DITL_BOITE_METABOX_TEXTES,
					'modeles' => $modeles,
				) : null,
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'ditl_boite_page_admin_assets', 20 );
