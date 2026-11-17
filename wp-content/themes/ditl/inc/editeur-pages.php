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
