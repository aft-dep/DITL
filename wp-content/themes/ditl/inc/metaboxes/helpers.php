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
	$sections = json_decode( (string) $value, true );

	if ( ! is_array( $sections ) ) {
		return '[]';
	}

	$propres = array();

	foreach ( $sections as $section ) {
		if ( ! is_array( $section ) ) {
			continue;
		}

		$title   = isset( $section['title'] ) ? sanitize_text_field( $section['title'] ) : '';
		$content = isset( $section['content'] ) ? wp_kses_post( $section['content'] ) : '';

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
	$ids = json_decode( (string) $value, true );

	if ( ! is_array( $ids ) ) {
		return '[]';
	}

	$propres = array();

	foreach ( $ids as $id ) {
		$id = absint( $id );

		if ( $id > 0 ) {
			$propres[] = $id;
		}
	}

	return (string) wp_json_encode( array_values( $propres ) );
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
