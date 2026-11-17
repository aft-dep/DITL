<?php
/**
 * Ecran d'edition des pages a gabarit sur mesure.
 *
 * Les gabarits du theme (Accueil, Contact, Projet DiTL, Partenaires,
 * Actualites, Resultats, Livrable, Boite a outils, Reglementation)
 * n'affichent pas le contenu de l'editeur : tout vient des champs sur mesure
 * (metaboxes). L'editeur de blocs restait pourtant affiche, avec les anciens
 * blocs herites d'Elementor, et chaque modification y etait sans effet sur le
 * site. Sur ces pages : ecran classique sans zone de contenu (titre, champs
 * sur mesure, Yoast, langue, attributs de page) et un message en tete qui
 * renvoie vers les champs.
 *
 * Non concernes : les pages sans gabarit (mentions legales, politique de
 * confidentialite, declaration d'accessibilite...) et le gabarit E-learning,
 * qui affichent bien le contenu de l'editeur. Le contenu deja enregistre
 * n'est ni modifie ni supprime.
 *
 * Sur tous les ecrans d'edition de contenu (pages, articles, fiches outils,
 * pays de la Reglementation...), et pour tous les utilisateurs, la
 * disposition des blocs est imposee a chaque chargement : blocs Commentaires,
 * Discussion et Champs personnalises retires ; Identifiant et Auteur masques ;
 * Yoast SEO et Revisions tout en bas, fermes ; blocs du theme (banniere, champs des gabarits, fiches)
 * ouverts et jamais masques. Les choix individuels (glisser-deposer, blocs
 * replies, options de l'ecran) ne sont plus pris en compte, et le
 * deplacement des blocs est desactive.
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gabarits du theme qui n'affichent pas le contenu de l'editeur.
 *
 * @return string[] Chemins des gabarits (meta _wp_page_template).
 */
function ditl_gabarits_sans_contenu() {
	return array(
		'page-templates/accueil.php',
		'page-templates/actualites.php',
		'page-templates/boite-a-outils.php',
		'page-templates/contact.php',
		'page-templates/livrable.php',
		'page-templates/partenaires.php',
		'page-templates/projet-ditl.php',
		'page-templates/reglementation.php',
		'page-templates/resultats.php',
	);
}

/**
 * La page utilise-t-elle un gabarit qui n'affiche pas le contenu ?
 *
 * @param WP_Post|int|null $post Page.
 * @return bool
 */
function ditl_page_sans_contenu( $post ) {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return false;
	}

	return in_array( get_page_template_slug( $post ), ditl_gabarits_sans_contenu(), true );
}

/**
 * Pas d'editeur de blocs sur ces pages.
 *
 * @param bool    $utiliser Valeur calculee par WordPress.
 * @param WP_Post $post     Publication editee.
 * @return bool
 */
function ditl_editeur_blocs_pages( $utiliser, $post ) {
	return ditl_page_sans_contenu( $post ) ? false : $utiliser;
}
add_filter( 'use_block_editor_for_post', 'ditl_editeur_blocs_pages', 10, 2 );

/**
 * Ecran d'edition d'une de ces pages : zone de contenu retiree (le support
 * "editor" n'est retire que pour la requete en cours).
 */
function ditl_retirer_zone_contenu() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture de l'ID de l'ecran d'edition, aucune action.
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

	if ( $post_id > 0 && ditl_page_sans_contenu( $post_id ) ) {
		remove_post_type_support( 'page', 'editor' );
	}
}
add_action( 'load-post.php', 'ditl_retirer_zone_contenu' );

/**
 * Message sous le titre : ou modifier le contenu de la page.
 *
 * @param WP_Post $post Publication editee.
 */
function ditl_message_gabarit_sans_contenu( $post ) {
	if ( ! ditl_page_sans_contenu( $post ) ) {
		return;
	}

	$gabarits = wp_get_theme()->get_page_templates( $post, 'page' );
	$slug     = get_page_template_slug( $post );
	$nom      = isset( $gabarits[ $slug ] ) ? $gabarits[ $slug ] : $slug;
	?>
	<div class="notice notice-info inline ditl-message-gabarit">
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s : nom du gabarit de la page. */
					__( 'Cette page utilise le gabarit « %s » : son contenu se modifie dans les champs ci-dessous (bannière et sections propres au gabarit), puis avec le bouton « Mettre à jour ». Ce gabarit n\'a pas de zone de texte libre.', 'ditl' ),
					$nom
				)
			);
			?>
		</p>
	</div>
	<?php
}
add_action( 'edit_form_after_title', 'ditl_message_gabarit_sans_contenu' );

/* -------------------------------------------------------------------------
 * Disposition imposee des blocs (tous les ecrans d'edition de contenu).
 * ---------------------------------------------------------------------- */

/**
 * Blocs retires des ecrans d'edition.
 *
 * @return string[] Identifiants de metabox.
 */
function ditl_edition_blocs_retires() {
	return array( 'commentsdiv', 'commentstatusdiv', 'postcustom' );
}

/**
 * Blocs toujours masques sans etre retires : Identifiant (modifiable sous le
 * titre) et Auteur. WordPress les masque deja par defaut, sauf pour un
 * utilisateur qui a modifie ses options d'ecran.
 *
 * @return string[] Identifiants de metabox.
 */
function ditl_edition_blocs_caches() {
	return array( 'slugdiv', 'authordiv' );
}

/**
 * Blocs ranges tout en bas, dans cet ordre, et toujours fermes.
 *
 * @return string[] Identifiants de metabox.
 */
function ditl_edition_blocs_en_bas() {
	return array( 'wpseo_meta', 'revisionsdiv' );
}

/**
 * Ecran d'edition d'un contenu (classique ou editeur de blocs) ?
 *
 * @param WP_Screen|null $ecran Ecran courant.
 * @return bool
 */
function ditl_edition_est_ecran_contenu( $ecran ) {
	return $ecran instanceof WP_Screen && 'post' === $ecran->base;
}

/**
 * Branche les reglages imposes sur l'ecran courant : ordre enregistre par
 * l'utilisateur ignore (ordre de declaration, voir
 * ditl_edition_ranger_blocs), blocs fermes imposes.
 *
 * @param WP_Screen $ecran Ecran courant.
 */
function ditl_edition_imposer_disposition( $ecran ) {
	if ( ! ditl_edition_est_ecran_contenu( $ecran ) ) {
		return;
	}

	add_filter( 'get_user_option_meta-box-order_' . $ecran->id, '__return_false' );
	add_filter( 'get_user_option_closedpostboxes_' . $ecran->id, 'ditl_edition_blocs_en_bas' );
}
add_action( 'current_screen', 'ditl_edition_imposer_disposition' );

/**
 * Une fois tous les blocs declares : retire les blocs inutiles et place
 * Yoast SEO puis Revisions en dernier (contexte "advanced", affiche apres
 * "normal", priorite basse). Appele pour chaque contexte, sans effet apres
 * le premier passage.
 *
 * @param string $post_type Type de contenu.
 */
function ditl_edition_ranger_blocs( $post_type ) {
	global $wp_meta_boxes;

	$ecran = get_current_screen();

	if ( ! ditl_edition_est_ecran_contenu( $ecran ) || ! isset( $wp_meta_boxes[ $ecran->id ] ) || ! is_array( $wp_meta_boxes[ $ecran->id ] ) ) {
		return;
	}

	foreach ( ditl_edition_blocs_retires() as $id ) {
		foreach ( array( 'normal', 'advanced', 'side' ) as $contexte ) {
			remove_meta_box( $id, $ecran->id, $contexte );
		}
	}

	$deplaces = array();

	foreach ( ditl_edition_blocs_en_bas() as $id ) {
		foreach ( $wp_meta_boxes[ $ecran->id ] as $contexte => $priorites ) {
			foreach ( (array) $priorites as $priorite => $blocs ) {
				if ( isset( $blocs[ $id ] ) && is_array( $blocs[ $id ] ) ) {
					$deplaces[ $id ] = $blocs[ $id ];
					unset( $wp_meta_boxes[ $ecran->id ][ $contexte ][ $priorite ][ $id ] );
				}
			}
		}
	}

	foreach ( ditl_edition_blocs_en_bas() as $id ) {
		if ( isset( $deplaces[ $id ] ) ) {
			$wp_meta_boxes[ $ecran->id ]['advanced']['low'][ $id ] = $deplaces[ $id ];
		}
	}
}
add_action( 'do_meta_boxes', 'ditl_edition_ranger_blocs', PHP_INT_MAX );

/**
 * Blocs masques imposes : les blocs du theme, Yoast SEO et Revisions sont
 * toujours affiches ; les blocs retires, Identifiant et Auteur toujours
 * masques.
 *
 * @param string[]  $masques Blocs masques (choix de l'utilisateur ou defaut).
 * @param WP_Screen $ecran   Ecran courant.
 * @return string[]
 */
function ditl_edition_blocs_masques( $masques, $ecran ) {
	if ( ! ditl_edition_est_ecran_contenu( $ecran ) ) {
		return $masques;
	}

	$masques = array_filter(
		(array) $masques,
		static function ( $id ) {
			return 0 !== strpos( (string) $id, 'ditl' ) && ! in_array( $id, ditl_edition_blocs_en_bas(), true );
		}
	);

	return array_values( array_unique( array_merge( $masques, ditl_edition_blocs_retires(), ditl_edition_blocs_caches() ) ) );
}
add_filter( 'hidden_meta_boxes', 'ditl_edition_blocs_masques', 10, 2 );

/**
 * Ecran classique : deplacement des blocs desactive (fleches de tri
 * masquees, glisser-deposer coupe) pour garder la disposition imposee.
 */
function ditl_edition_figer_blocs() {
	if ( ! ditl_edition_est_ecran_contenu( get_current_screen() ) ) {
		return;
	}
	?>
	<style>
		.postbox .handle-order-higher,
		.postbox .handle-order-lower { display: none; }
		.js .postbox .hndle,
		.js .postbox .postbox-header .hndle { cursor: default; }
	</style>
	<script>
		( function ( $ ) {
			if ( ! $ || ! $.fn || ! $.fn.sortable ) {
				return;
			}
			$( window ).on( 'load', function () {
				$( '.meta-box-sortables' ).each( function () {
					if ( $( this ).sortable( 'instance' ) ) {
						$( this ).sortable( 'option', 'disabled', true );
					}
				} );
			} );
		}( window.jQuery ) );
	</script>
	<?php
}
add_action( 'admin_print_footer_scripts', 'ditl_edition_figer_blocs' );

/**
 * Editeur de blocs (pages simples, articles...) : panneau "Discussion" de
 * la barre laterale retire.
 */
function ditl_edition_panneau_discussion() {
	wp_add_inline_script(
		'wp-edit-post',
		"wp.domReady( function () { var editeur = wp.data && wp.data.dispatch( 'core/editor' ); if ( editeur && editeur.removeEditorPanel ) { editeur.removeEditorPanel( 'discussion-panel' ); } } );"
	);
}
add_action( 'enqueue_block_editor_assets', 'ditl_edition_panneau_discussion' );
