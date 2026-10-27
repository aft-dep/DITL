<?php
/**
 * Import des fiches : execution (simulation ou ecriture), par lots.
 *
 * Fiches (cle de rejeu = colonne id, meta _ditl_import_id) :
 * - ligne identique au dernier import (empreinte de la ligne source) et
 *   fiche non retouchee : "inchangee", AUCUNE ecriture ;
 * - fiche retouchee dans l'administration depuis le dernier import
 *   (empreinte de l'etat relu en base apres ecriture, comparee a l'etat
 *   actuel) : ignoree, sauf option "ecraser" ;
 * - fiche en corbeille : ignoree ;
 * - sinon creation ou mise a jour ; jamais de suppression d'une fiche
 *   absente du fichier.
 * Statut : brouillon par defaut a la creation. En mise a jour, le statut ne
 * change que pour PUBLIER : un "brouillon" du fichier ne depublie jamais une
 * fiche mise en ligne dans l'administration (decision de relecture du
 * client), et le statut n'entre pas dans l'empreinte d'etat (publier une
 * fiche n'est pas une modification de son contenu).
 * Apres chaque ligne, tout le groupe (group_id) est relie comme traductions
 * via l'adaptateur ditl_ml_* (liens existants vers d'autres publications
 * conserves pour les langues absentes du groupe).
 *
 * Reprise sure apres une interruption (delai depasse, erreur fatale) :
 * id / groupe / langue poses a la creation de la fiche (meta_input), etat
 * supprime avant une mise a jour, empreinte de la ligne ecrite EN DERNIER :
 * une ligne interrompue est toujours reecrite au lot suivant.
 *
 * Pendant une ecriture : verrou global (un seul import a la fois, admin et
 * CLI confondus) ; comptage des termes differe (sauf sous Polylang : ses
 * groupes de traduction sont des termes dont le compteur doit rester exact,
 * un compteur differe a 0 lui fait supprimer le groupe et casse la liaison
 * des traductions) ; synchronisation Polylang des taxonomies de la boite et
 * de l'image a la une suspendue (chaque ligne fait foi pour sa langue) ;
 * purge complete de WP Fastest Cache a chaque publication suspendue,
 * remplacee par UNE purge en fin de lot si une fiche publiee a ete ecrite.
 *
 * Referentiels : un terme par langue active (slug "{code}-{lang}"), metas
 * _ditl_code / _ditl_couleur / _ditl_ordre posees dans CHAQUE langue (elles
 * ne sont pas synchronisees entre traductions), marqueur _ditl_import_terme
 * sur tout terme cree ou mis a jour, parent = terme du code parent dans la
 * meme langue, traductions liees ; jamais de suppression.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Execute un plan a partir d'une position, dans un budget de temps.
 *
 * Hors simulation, le verrou global d'ecriture est pris pour toute
 * l'execution : s'il est deja tenu (autre import en cours), rien n'est fait
 * et le resultat porte occupe = true (position inchangee).
 *
 * @param array $plan       Plan (ditl_import_preparer).
 * @param int   $debut      Position de depart dans l'ordre de traitement.
 * @param int   $budget     Budget en secondes (0 = sans limite). Au moins
 *                          une ligne est toujours traitee.
 * @param bool  $simulation True : aucune ecriture, actions prevues.
 * @return array Tableau { position, total, termine, occupe, resultats }.
 */
function ditl_import_executer( $plan, $debut = 0, $budget = 0, $simulation = true ) {
	$total    = count( $plan['ordre'] );
	$debut    = max( 0, min( (int) $debut, $total ) );
	$resultat = array(
		'position'  => $debut,
		'total'     => $total,
		'termine'   => false,
		'occupe'    => false,
		'resultats' => array(),
	);

	if ( array() !== $plan['fatal'] ) {
		$resultat['termine'] = true;
		return $resultat;
	}

	if ( ! $simulation && ! ditl_import_verrouiller_ecriture() ) {
		$resultat['occupe'] = true;
		return $resultat;
	}

	$depart = microtime( true );

	if ( ! $simulation ) {
		ditl_import_debut_ecriture();
	}

	try {
		for ( $position = $debut; $position < $total; $position++ ) {
			if ( $budget > 0 && $position > $debut && ( microtime( true ) - $depart ) >= $budget ) {
				break;
			}

			$ligne = $plan['lignes'][ $plan['ordre'][ $position ] ];

			$resultat['resultats'][] = 'fiches' === $plan['type']
				? ditl_import_traiter_fiche( $ligne, $plan, $simulation )
				: ditl_import_traiter_referentiel( $ligne, $simulation );

			$resultat['position'] = $position + 1;
		}
	} finally {
		if ( ! $simulation ) {
			ditl_import_fin_ecriture();
			ditl_import_liberer_ecriture();
		}
	}

	$resultat['termine'] = $resultat['position'] >= $total;

	return $resultat;
}

/**
 * Resultat de base d'une ligne (repris dans les rapports).
 *
 * @param array $ligne Ligne du plan.
 * @return array Resultat { ligne, id, groupe, lang, titre, action, objet, messages }.
 */
function ditl_import_resultat_ligne( $ligne ) {
	return array(
		'ligne'    => (int) $ligne['ligne'],
		'id'       => (string) $ligne['id'],
		'groupe'   => isset( $ligne['groupe'] ) ? (string) $ligne['groupe'] : ( isset( $ligne['liste'] ) ? (string) $ligne['liste'] : '' ),
		'lang'     => (string) $ligne['lang'],
		'titre'    => (string) $ligne['titre'],
		'action'   => '',
		'objet'    => 0,
		'messages' => ditl_import_trier_messages( $ligne['messages'] ),
	);
}

/**
 * Trie des messages : erreurs, puis avertissements, puis informations
 * (ordre d'origine conserve a niveau egal).
 *
 * @param array $messages Messages { niveau, texte }.
 * @return array Messages tries.
 */
function ditl_import_trier_messages( $messages ) {
	$poids = array(
		'erreur'        => 0,
		'avertissement' => 1,
		'info'          => 2,
	);
	$cles  = array();
	$rangs = array();
	$liste = array_values( (array) $messages );

	foreach ( $liste as $rang => $message ) {
		$cles[]  = isset( $message['niveau'], $poids[ $message['niveau'] ] ) ? $poids[ $message['niveau'] ] : 3;
		$rangs[] = $rang;
	}

	array_multisort( $cles, SORT_ASC, $rangs, SORT_ASC, $liste );

	return $liste;
}

/**
 * Prepare l'environnement d'ecriture d'un lot.
 */
function ditl_import_debut_ecriture() {
	$GLOBALS['ditl_import_ecriture']     = true;
	$GLOBALS['ditl_import_publications'] = 0;
	$GLOBALS['ditl_import_differe']      = 'polylang' !== ditl_ml_extension();

	if ( $GLOBALS['ditl_import_differe'] ) {
		wp_defer_term_counting( true );
	}

	add_filter( 'pll_copy_taxonomies', 'ditl_import_filtre_synchro_taxonomies', 99, 2 );
	add_filter( 'pll_copy_post_metas', 'ditl_import_filtre_synchro_metas', 99, 3 );

	ditl_import_detacher_purge_cache();
}

/**
 * Retablit l'environnement apres un lot (et purge le cache une fois si une
 * fiche publiee a ete ecrite).
 */
function ditl_import_fin_ecriture() {
	if ( ! empty( $GLOBALS['ditl_import_differe'] ) ) {
		wp_defer_term_counting( false );
	}

	$GLOBALS['ditl_import_differe']  = false;
	$GLOBALS['ditl_import_ecriture'] = false;

	remove_filter( 'pll_copy_taxonomies', 'ditl_import_filtre_synchro_taxonomies', 99 );
	remove_filter( 'pll_copy_post_metas', 'ditl_import_filtre_synchro_metas', 99 );

	ditl_import_rattacher_purge_cache();

	if ( ! empty( $GLOBALS['ditl_import_publications'] ) ) {
		ditl_import_purger_cache();
	}

	$GLOBALS['ditl_import_publications'] = 0;
}

/**
 * Purge complete du cache de pages (WP Fastest Cache), si l'extension est
 * active.
 */
function ditl_import_purger_cache() {
	if ( has_action( 'wpfc_clear_all_cache' ) ) {
		do_action( 'wpfc_clear_all_cache' );
	}
}

/**
 * Pendant un import : pas de synchronisation Polylang des taxonomies de la
 * boite entre traductions (chaque ligne fait foi pour sa langue).
 *
 * @param string[] $taxonomies Taxonomies a copier.
 * @param bool     $sync       True pour une synchronisation (et non une copie).
 * @return string[] Taxonomies filtrees.
 */
function ditl_import_filtre_synchro_taxonomies( $taxonomies, $sync = false ) {
	if ( ! $sync ) {
		return $taxonomies;
	}

	return array_values( array_diff( (array) $taxonomies, ditl_import_taxonomies() ) );
}

/**
 * Pendant un import : pas de synchronisation Polylang de l'image a la une
 * entre fiches traduites.
 *
 * @param string[] $metas Metas a copier.
 * @param bool     $sync  True pour une synchronisation.
 * @param int      $from  Publication source.
 * @return string[] Metas filtrees.
 */
function ditl_import_filtre_synchro_metas( $metas, $sync = false, $from = 0 ) {
	if ( ! $sync || ditl_import_type_fiche() !== get_post_type( (int) $from ) ) {
		return $metas;
	}

	return array_values( array_diff( (array) $metas, array( '_thumbnail_id' ) ) );
}

/**
 * Detache la purge de WP Fastest Cache declenchee a chaque publication ou
 * mise a jour d'un contenu publie (purge COMPLETE du cache a chaque fiche).
 */
function ditl_import_detacher_purge_cache() {
	global $wp_filter;

	$GLOBALS['ditl_import_wpfc'] = array();

	if ( ! class_exists( 'WpFastestCache' ) || empty( $wp_filter['transition_post_status'] ) || ! is_object( $wp_filter['transition_post_status'] ) ) {
		return;
	}

	$rappels = $wp_filter['transition_post_status']->callbacks;

	foreach ( $rappels as $priorite => $liste ) {
		foreach ( $liste as $rappel ) {
			$fonction = isset( $rappel['function'] ) ? $rappel['function'] : null;

			if ( is_array( $fonction ) && isset( $fonction[0], $fonction[1] ) && $fonction[0] instanceof WpFastestCache && 'on_all_status_transitions' === $fonction[1] ) {
				remove_action( 'transition_post_status', $fonction, $priorite );
				$GLOBALS['ditl_import_wpfc'][] = array( $fonction, $priorite, isset( $rappel['accepted_args'] ) ? (int) $rappel['accepted_args'] : 3 );
			}
		}
	}
}

/**
 * Rattache la purge de WP Fastest Cache detachee pendant le lot.
 */
function ditl_import_rattacher_purge_cache() {
	foreach ( isset( $GLOBALS['ditl_import_wpfc'] ) ? (array) $GLOBALS['ditl_import_wpfc'] : array() as $rappel ) {
		add_action( 'transition_post_status', $rappel[0], $rappel[1], $rappel[2] );
	}

	$GLOBALS['ditl_import_wpfc'] = array();
}

/**
 * Empreinte d'une ligne source (valeurs normalisees, codes et non IDs).
 *
 * L'image est identifiee par l'empreinte sha1 de son fichier, qu'elle vienne
 * du dossier d'images ou d'un media deja importe : un rejeu du CSV seul
 * (images deja en mediatheque) donne la meme empreinte.
 *
 * @param array $ligne Ligne du plan.
 * @return string Empreinte md5.
 */
function ditl_import_empreinte_ligne( $ligne ) {
	$image = null;

	if ( null !== $ligne['image'] ) {
		if ( '' === $ligne['image']['nom'] ) {
			$image = '';
		} elseif ( 'dossier' === $ligne['image']['source'] ) {
			$image = 'sha1:' . $ligne['image']['sha1'];
		} else {
			$sha1  = ditl_import_sha1_media( (int) $ligne['image']['attachment_id'] );
			$image = '' !== $sha1 ? 'sha1:' . $sha1 : 'media:' . (int) $ligne['image']['attachment_id'];
		}
	}

	$codes = $ligne['codes'];
	ksort( $codes );

	return md5(
		(string) wp_json_encode(
			array(
				DITL_IMPORT_VERSION,
				$ligne['groupe'],
				$ligne['lang'],
				$ligne['valeurs'],
				$codes,
				$image,
			)
		)
	);
}

/**
 * Termes d'une publication dans une taxonomie (lecture directe, sans
 * filtre de langue).
 *
 * @param int    $post_id   ID de la publication.
 * @param string $taxonomie Taxonomie.
 * @return int[] IDs de termes tries.
 */
function ditl_import_termes_post( $post_id, $taxonomie ) {
	global $wpdb;

	$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- lecture sans filtre de langue.
		$wpdb->prepare(
			"SELECT tt.term_id FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			WHERE tr.object_id = %d AND tt.taxonomy = %s
			ORDER BY tt.term_id ASC",
			$post_id,
			$taxonomie
		)
	);

	return array_map( 'intval', (array) $ids );
}

/**
 * Normalise un HTML pour la comparaison d'etat : les reformatages de
 * l'editeur classique (paragraphes retires, retours a la ligne,
 * indentation) ne comptent pas comme une modification.
 *
 * @param string $html HTML.
 * @return string HTML normalise.
 */
function ditl_import_normaliser_html( $html ) {
	$html = wpautop( (string) $html );
	$html = (string) preg_replace( '/\s+/u', ' ', $html );
	$html = (string) preg_replace( '/>\s+</', '><', $html );
	$html = str_replace( array( '<br />', '<br/>' ), '<br>', $html );

	return trim( $html );
}

/**
 * Empreinte de l'etat actuel d'une fiche, sur une liste de champs.
 *
 * @param int      $post_id ID de la fiche.
 * @param string[] $champs  Champs geres (post_title, post_content, lang,
 *                          _thumbnail_id, tax:<taxonomie>, metas _ditl_*).
 * @return string Empreinte md5 (chaine vide si fiche introuvable).
 */
function ditl_import_calculer_etat( $post_id, $champs ) {
	$post = get_post( $post_id );

	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$etat = array();

	foreach ( (array) $champs as $champ ) {
		$champ = is_scalar( $champ ) ? (string) $champ : '';

		if ( 'post_title' === $champ ) {
			$etat[ $champ ] = (string) $post->post_title;
		} elseif ( 'post_content' === $champ ) {
			$etat[ $champ ] = ditl_import_normaliser_html( $post->post_content );
		} elseif ( 'lang' === $champ ) {
			$etat[ $champ ] = ditl_ml_langue_post( $post_id );
		} elseif ( '_thumbnail_id' === $champ ) {
			$etat[ $champ ] = (int) get_post_thumbnail_id( $post_id );
		} elseif ( 0 === strpos( $champ, 'tax:' ) ) {
			$etat[ $champ ] = ditl_import_termes_post( $post_id, substr( $champ, 4 ) );
		} elseif ( in_array( $champ, array( '_ditl_descriptif_technique', '_ditl_points_vigilance' ), true ) ) {
			$etat[ $champ ] = ditl_import_normaliser_html( (string) get_post_meta( $post_id, $champ, true ) );
		} elseif ( 0 === strpos( $champ, '_ditl_' ) ) {
			$etat[ $champ ] = (string) get_post_meta( $post_id, $champ, true );
		}
	}

	return md5( (string) wp_json_encode( $etat ) );
}

/**
 * Champs suivis par l'etat enregistre d'une fiche.
 *
 * @param int $post_id ID de la fiche.
 * @return string[] Champs (vide si aucun etat valide).
 */
function ditl_import_champs_suivis( $post_id ) {
	$stocke = json_decode( (string) get_post_meta( $post_id, '_ditl_import_etat', true ), true );

	if ( ! is_array( $stocke ) || empty( $stocke['champs'] ) || ! is_array( $stocke['champs'] ) ) {
		return array();
	}

	return array_values( array_filter( $stocke['champs'], 'is_string' ) );
}

/**
 * Indique si une fiche a ete modifiee depuis le dernier import (sur les
 * champs suivis par l'import).
 *
 * @param int $post_id ID de la fiche.
 * @return bool True si modifiee.
 */
function ditl_import_fiche_modifiee( $post_id ) {
	$stocke = json_decode( (string) get_post_meta( $post_id, '_ditl_import_etat', true ), true );

	if ( ! is_array( $stocke ) || empty( $stocke['champs'] ) || ! is_array( $stocke['champs'] ) || ! isset( $stocke['empreinte'] ) || ! is_string( $stocke['empreinte'] ) ) {
		// Etat absent (import interrompu) : la fiche est reprise.
		return false;
	}

	return ditl_import_calculer_etat( $post_id, $stocke['champs'] ) !== $stocke['empreinte'];
}

/**
 * Traite une ligne du fichier des fiches.
 *
 * @param array $ligne      Ligne du plan.
 * @param array $plan       Plan (options).
 * @param bool  $simulation True : aucune ecriture.
 * @return array Resultat de la ligne.
 */
function ditl_import_traiter_fiche( $ligne, $plan, $simulation ) {
	$resultat = ditl_import_resultat_ligne( $ligne );

	if ( $ligne['erreur'] ) {
		$resultat['action'] = 'rejetee';
		return $resultat;
	}

	$empreinte = ditl_import_empreinte_ligne( $ligne );
	$existant  = $ligne['existant'];
	$post_id   = 0;

	if ( null !== $existant ) {
		$post_id           = (int) $existant['ID'];
		$resultat['objet'] = $post_id;

		if ( 'trash' === $existant['statut'] ) {
			ditl_import_message( $resultat, 'avertissement', sprintf( 'fiche %d dans la corbeille : ignorée (la restaurer, ou la supprimer définitivement pour la recréer).', $post_id ) );
			$resultat['action'] = 'ignoree';
			return $resultat;
		}

		$modifiee = ditl_import_fiche_modifiee( $post_id );

		if ( $modifiee && empty( $plan['options']['ecraser'] ) ) {
			ditl_import_message( $resultat, 'avertissement', sprintf( 'fiche %d modifiée dans l\'administration depuis le dernier import : ignorée (option « écraser » pour la remplacer).', $post_id ) );
			$resultat['action'] = 'ignoree';
			return $resultat;
		}

		if ( ! $modifiee && get_post_meta( $post_id, '_ditl_import_empreinte', true ) === $empreinte ) {
			$resultat['action'] = 'inchangee';

			if ( ! $simulation ) {
				ditl_import_lier_groupe( $ligne['groupe'], $resultat );

				// Etat absent alors que l'empreinte est a jour : il est recree.
				if ( '' === (string) get_post_meta( $post_id, '_ditl_import_etat', true ) ) {
					ditl_import_enregistrer_etat( $post_id, $ligne['champs'] );
				}
			}

			return $resultat;
		}

		if ( $modifiee ) {
			ditl_import_message( $resultat, 'avertissement', sprintf( 'fiche %d modifiée dans l\'administration : remplacée (option « écraser »).', $post_id ) );
		}

		$resultat['action'] = 'mise_a_jour';
	} else {
		$resultat['action'] = 'creation';
	}

	if ( $simulation ) {
		if ( null !== $ligne['image'] && '' !== $ligne['image']['nom'] && 'dossier' === $ligne['image']['source'] && 0 === ditl_import_media_par_sha1( $ligne['image']['sha1'] ) ) {
			ditl_import_message( $resultat, 'info', sprintf( 'image « %s » : sera ajoutée à la médiathèque.', ditl_import_extrait( $ligne['image']['nom'], 60 ) ) );
		}

		return $resultat;
	}

	return ditl_import_ecrire_fiche( $ligne, $resultat, $post_id, $empreinte );
}

/**
 * Ecrit une fiche (creation ou mise a jour).
 *
 * Ordre des ecritures (reprise sure) : etat supprime (mise a jour), fiche,
 * langue, metas, termes, image, metas techniques, traductions, etat, et
 * l'empreinte de la ligne EN DERNIER.
 *
 * @param array  $ligne     Ligne du plan.
 * @param array  $resultat  Resultat en cours.
 * @param int    $post_id   ID de la fiche existante (0 = creation).
 * @param string $empreinte Empreinte de la ligne source.
 * @return array Resultat de la ligne.
 */
function ditl_import_ecrire_fiche( $ligne, $resultat, $post_id, $empreinte ) {
	// Champs deja suivis : conserves dans le nouvel etat (union).
	$suivis = 0 === $post_id ? array() : ditl_import_champs_suivis( $post_id );

	// Image : null = inchangee, 0 = retiree, sinon ID du media.
	$attachment_id = ditl_import_media_fiche( $ligne['image'], $resultat );

	$post_id = ditl_import_enregistrer_post( $ligne, $resultat, $post_id );

	if ( 0 === $post_id ) {
		return $resultat;
	}

	$resultat['objet'] = $post_id;

	// Lu AVANT l'ecriture des metas (comparaison avec les valeurs en base).
	$traductions_propres = ditl_import_traductions_a_rafraichir( $post_id, $ligne['valeurs']['metas'] );

	if ( ditl_ml_langue_post( $post_id ) !== $ligne['lang'] ) {
		ditl_ml_definir_langue_post( $post_id, $ligne['lang'] );
	}

	ditl_import_poser_metas( $post_id, $ligne['valeurs']['metas'] );
	ditl_import_poser_termes( $post_id, $ligne['termes'], $resultat );
	ditl_import_poser_image( $post_id, $attachment_id );
	ditl_import_poser_metas_techniques( $post_id, $ligne );

	ditl_import_lier_groupe( $ligne['groupe'], $resultat );

	ditl_import_enregistrer_etat( $post_id, array_merge( $suivis, $ligne['champs'] ) );

	foreach ( $traductions_propres as $traduction ) {
		$champs_traduction = ditl_import_champs_suivis( $traduction );

		if ( array() !== $champs_traduction ) {
			ditl_import_enregistrer_etat( $traduction, $champs_traduction );
		}
	}

	// En dernier : la ligne n'est "a jour" qu'une fois tout ecrit.
	update_post_meta( $post_id, '_ditl_import_empreinte', $empreinte );

	return $resultat;
}

/**
 * Media a poser en image a la une d'une fiche. Une image du dossier absente
 * de la mediatheque (sha1) y est televersee.
 *
 * @param array|null $image    Image de la ligne (null = colonne absente ou
 *                             image refusee a l'analyse).
 * @param array      $resultat Resultat de ligne (par reference, messages).
 * @return int|null null = illustration inchangee, 0 = retiree, sinon ID du media.
 */
function ditl_import_media_fiche( $image, &$resultat ) {
	if ( null === $image ) {
		return null;
	}

	if ( '' === $image['nom'] ) {
		return 0;
	}

	if ( 'mediatheque' === $image['source'] ) {
		return (int) $image['attachment_id'];
	}

	if ( 'dossier' !== $image['source'] ) {
		return null;
	}

	$attachment_id = ditl_import_media_par_sha1( $image['sha1'] );

	if ( 0 !== $attachment_id ) {
		return $attachment_id;
	}

	$televerse = ditl_import_televerser_image( $image['chemin'], $image['nom'], $image['sha1'] );

	if ( is_wp_error( $televerse ) ) {
		ditl_import_message( $resultat, 'avertissement', sprintf( 'image « %1$s » : téléversement impossible (%2$s) : illustration non modifiée.', ditl_import_extrait( $image['nom'], 60 ), $televerse->get_error_message() ) );
		return null;
	}

	$attachment_id = (int) $televerse;
	ditl_import_message( $resultat, 'info', sprintf( 'image « %1$s » ajoutée à la médiathèque (média %2$d).', ditl_import_extrait( $image['nom'], 60 ), $attachment_id ) );

	return $attachment_id;
}

/**
 * Cree ou met a jour la publication d'une fiche (titre, contenu, slug,
 * statut) et compte les fiches publiees ecrites (une purge du cache en fin
 * de lot).
 *
 * Creation : brouillon sauf statut "publie", cle de rejeu posee des la
 * creation. Mise a jour : le statut ne change que pour publier, etat
 * supprime avant d'ecrire.
 *
 * @param array $ligne    Ligne du plan.
 * @param array $resultat Resultat de ligne (par reference : message et
 *                        action "echec" si l'enregistrement echoue).
 * @param int   $post_id  ID de la fiche existante (0 = creation).
 * @return int ID de la fiche, 0 si l'enregistrement a echoue.
 */
function ditl_import_enregistrer_post( $ligne, &$resultat, $post_id ) {
	$valeurs = $ligne['valeurs'];
	$postarr = array(
		'post_type'  => ditl_import_type_fiche(),
		'post_title' => $valeurs['post_title'],
	);

	if ( isset( $valeurs['post_content'] ) ) {
		$postarr['post_content'] = $valeurs['post_content'];
	}

	if ( isset( $valeurs['post_name'] ) && '' !== $valeurs['post_name'] ) {
		$postarr['post_name'] = $valeurs['post_name'];
	}

	$statut = isset( $valeurs['statut'] ) ? $valeurs['statut'] : '';

	if ( 0 === $post_id ) {
		$postarr['post_status'] = 'publish' === $statut ? 'publish' : 'draft';
		// Cle de rejeu posee des la creation : une interruption ne cree pas de doublon.
		$postarr['meta_input'] = array(
			'_ditl_import_id'     => $ligne['id'],
			'_ditl_import_groupe' => $ligne['groupe'],
			'_ditl_import_langue' => $ligne['lang'],
		);
		$enregistre            = wp_insert_post( wp_slash( $postarr ), true );
		$statut_final          = $postarr['post_status'];
	} else {
		$actuel        = get_post( $post_id );
		$statut_actuel = $actuel instanceof WP_Post ? $actuel->post_status : '';
		$statut_final  = $statut_actuel;

		if ( 'publish' === $statut && 'publish' !== $statut_actuel ) {
			$postarr['post_status'] = 'publish';
			$statut_final           = 'publish';
		} elseif ( 'draft' === $statut && 'draft' !== $statut_actuel ) {
			ditl_import_message( $resultat, 'info', sprintf( 'statut « %s » conservé (un brouillon du fichier ne dépublie pas une fiche).', $statut_actuel ) );
		}

		// Etat supprime avant d'ecrire : une interruption laisse la fiche a reprendre.
		delete_post_meta( $post_id, '_ditl_import_etat' );

		$postarr['ID'] = $post_id;
		$enregistre    = wp_update_post( wp_slash( $postarr ), true );
	}

	if ( is_wp_error( $enregistre ) || ! $enregistre ) {
		ditl_import_message( $resultat, 'erreur', sprintf( 'enregistrement impossible : %s', is_wp_error( $enregistre ) ? $enregistre->get_error_message() : 'erreur inconnue' ) );
		$resultat['action'] = 'echec';
		return 0;
	}

	if ( 'publish' === $statut_final ) {
		++$GLOBALS['ditl_import_publications'];
	}

	return (int) $enregistre;
}

/**
 * Traductions dont l'etat sera rafraichi apres l'ecriture d'une fiche.
 *
 * Metas communes modifiees : l'extension multilingue les recopie sur les
 * traductions. L'etat des traductions non retouchees sera rafraichi (c'est
 * l'import qui les change, pas l'administration). A appeler AVANT
 * l'ecriture des metas.
 *
 * @param int   $post_id ID de la fiche.
 * @param array $metas   Metas de la ligne [cle => valeur].
 * @return int[] IDs des traductions a rafraichir.
 */
function ditl_import_traductions_a_rafraichir( $post_id, $metas ) {
	$traductions_propres = array();

	foreach ( ditl_import_metas_communes() as $cle ) {
		if ( isset( $metas[ $cle ] ) && (string) get_post_meta( $post_id, $cle, true ) !== $metas[ $cle ] ) {
			foreach ( ditl_ml_traductions_post( $post_id ) as $traduction ) {
				$traduction = (int) $traduction;

				if ( $traduction !== $post_id && '' !== (string) get_post_meta( $traduction, '_ditl_import_etat', true ) && ! ditl_import_fiche_modifiee( $traduction ) ) {
					$traductions_propres[ $traduction ] = true;
				}
			}
			break;
		}
	}

	return array_keys( $traductions_propres );
}

/**
 * Ecrit les metas de contenu d'une fiche (seulement celles qui changent).
 *
 * @param int   $post_id ID de la fiche.
 * @param array $metas   Metas de la ligne [cle => valeur].
 */
function ditl_import_poser_metas( $post_id, $metas ) {
	foreach ( $metas as $cle => $valeur ) {
		if ( (string) get_post_meta( $post_id, $cle, true ) !== $valeur ) {
			update_post_meta( $post_id, $cle, wp_slash( $valeur ) );
		}
	}
}

/**
 * Pose les termes d'une fiche, taxonomie par taxonomie (seulement si la
 * liste change ; les termes d'une taxonomie absente du fichier ne sont pas
 * touches).
 *
 * @param int   $post_id  ID de la fiche.
 * @param array $termes   Termes de la ligne [taxonomie => IDs].
 * @param array $resultat Resultat de ligne (par reference, messages).
 */
function ditl_import_poser_termes( $post_id, $termes, &$resultat ) {
	foreach ( $termes as $taxonomie => $ids ) {
		$ids = array_map( 'intval', $ids );
		sort( $ids );

		if ( ditl_import_termes_post( $post_id, $taxonomie ) !== $ids ) {
			$pose = wp_set_object_terms( $post_id, $ids, $taxonomie, false );

			if ( is_wp_error( $pose ) ) {
				ditl_import_message( $resultat, 'avertissement', sprintf( '%1$s : termes non enregistrés (%2$s).', $taxonomie, $pose->get_error_message() ) );
			}
		}
	}
}

/**
 * Pose, retire ou laisse l'image a la une d'une fiche.
 *
 * @param int      $post_id       ID de la fiche.
 * @param int|null $attachment_id null = inchangee, 0 = retiree, sinon ID du media.
 */
function ditl_import_poser_image( $post_id, $attachment_id ) {
	if ( 0 === $attachment_id ) {
		if ( has_post_thumbnail( $post_id ) ) {
			delete_post_thumbnail( $post_id );
		}
	} elseif ( null !== $attachment_id && (int) get_post_thumbnail_id( $post_id ) !== $attachment_id ) {
		set_post_thumbnail( $post_id, $attachment_id );
	}
}

/**
 * Ecrit les metas techniques d'une fiche (id, groupe, langue si elles
 * changent ; date du dernier import).
 *
 * @param int   $post_id ID de la fiche.
 * @param array $ligne   Ligne du plan.
 */
function ditl_import_poser_metas_techniques( $post_id, $ligne ) {
	$techniques = array(
		'_ditl_import_id'     => $ligne['id'],
		'_ditl_import_groupe' => $ligne['groupe'],
		'_ditl_import_langue' => $ligne['lang'],
	);

	foreach ( $techniques as $cle => $valeur ) {
		if ( (string) get_post_meta( $post_id, $cle, true ) !== $valeur ) {
			update_post_meta( $post_id, $cle, $valeur );
		}
	}

	update_post_meta( $post_id, '_ditl_import_date', current_time( 'mysql' ) );
}

/**
 * Enregistre l'empreinte de l'etat d'une fiche, relu en base apres
 * ecriture (sert a detecter une modification faite dans l'administration).
 *
 * @param int      $post_id ID de la fiche.
 * @param string[] $champs  Champs suivis (doublons retires, ordre trie).
 */
function ditl_import_enregistrer_etat( $post_id, $champs ) {
	$champs = array_values( array_unique( array_filter( (array) $champs, 'is_string' ) ) );
	sort( $champs );

	clean_post_cache( $post_id );
	update_post_meta(
		$post_id,
		'_ditl_import_etat',
		wp_slash(
			(string) wp_json_encode(
				array(
					'champs'    => $champs,
					'empreinte' => ditl_import_calculer_etat( $post_id, $champs ),
				)
			)
		)
	);
}

/**
 * Ordonne une correspondance [langue => ID] : langue par defaut du site,
 * puis francais, puis les autres (langue source WPML d'un groupe).
 *
 * @param int[] $correspondance Correspondance.
 * @return int[] Correspondance ordonnee.
 */
function ditl_import_ordonner_langues( $correspondance ) {
	$ordonne = array();

	foreach ( array( ditl_ml_langue_defaut(), 'fr' ) as $prioritaire ) {
		if ( isset( $correspondance[ $prioritaire ] ) && ! isset( $ordonne[ $prioritaire ] ) ) {
			$ordonne[ $prioritaire ] = (int) $correspondance[ $prioritaire ];
		}
	}

	foreach ( $correspondance as $lang => $id ) {
		if ( ! isset( $ordonne[ $lang ] ) ) {
			$ordonne[ $lang ] = (int) $id;
		}
	}

	return $ordonne;
}

/**
 * Compare deux correspondances [langue => ID] sans tenir compte de l'ordre.
 *
 * @param array $a Correspondance.
 * @param array $b Correspondance.
 * @return bool True si identiques.
 */
function ditl_import_memes_traductions( $a, $b ) {
	$a = array_map( 'intval', (array) $a );
	$b = array_map( 'intval', (array) $b );
	ksort( $a );
	ksort( $b );

	return $a === $b;
}

/**
 * Relie comme traductions toutes les fiches importees d'un groupe.
 *
 * N'ecrit que si les liens changent. Les liens existants vers d'autres
 * publications sont conserves pour les langues absentes du groupe.
 *
 * @param string $groupe   group_id.
 * @param array  $resultat Resultat de ligne (par reference, messages).
 */
function ditl_import_lier_groupe( $groupe, &$resultat ) {
	global $wpdb;

	$lignes = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- membres du groupe.
		$wpdb->prepare(
			"SELECT p.ID, ml.meta_value AS langue FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} mg ON mg.post_id = p.ID AND mg.meta_key = '_ditl_import_groupe' AND mg.meta_value = %s
			LEFT JOIN {$wpdb->postmeta} ml ON ml.post_id = p.ID AND ml.meta_key = '_ditl_import_langue'
			WHERE p.post_type = %s AND p.post_status NOT IN ('trash', 'auto-draft')
			ORDER BY p.ID ASC",
			(string) $groupe,
			ditl_import_type_fiche()
		)
	);

	$membres = array();

	foreach ( (array) $lignes as $ligne ) {
		$id   = (int) $ligne->ID;
		$lang = ditl_ml_langue_post( $id );
		$lang = '' !== $lang ? $lang : (string) $ligne->langue;

		if ( '' === $lang || isset( $membres[ $lang ] ) ) {
			continue;
		}

		$membres[ $lang ] = $id;
	}

	if ( ! ditl_import_lier_traductions( 'post', $membres ) ) {
		ditl_import_message( $resultat, 'avertissement', sprintf( 'groupe %s : traductions non reliées (extension multilingue absente ?).', $groupe ) );
	}
}

/**
 * Relie comme traductions des fiches (groupe group_id) ou des termes (ligne
 * d'un referentiel) : meme logique pour les deux.
 *
 * N'ecrit que si les liens changent, en une seule liaison. Les liens
 * existants vers d'autres objets sont conserves pour les langues absentes
 * des membres. Langue source (WPML) : ditl_import_ordonner_langues().
 *
 * @param string $nature  "post" (fiches) ou "terme" (referentiels).
 * @param int[]  $membres Correspondance [langue => ID] des objets a relier.
 * @return bool False si la liaison a echoue (extension multilingue absente ?),
 *              true sinon, y compris sans ecriture (moins de deux membres,
 *              liens deja en place).
 */
function ditl_import_lier_traductions( $nature, $membres ) {
	if ( count( $membres ) < 2 ) {
		return true;
	}

	$lire  = 'terme' === $nature ? 'ditl_ml_traductions_terme' : 'ditl_ml_traductions_post';
	$cible = array();

	foreach ( $membres as $id ) {
		foreach ( $lire( $id ) as $lang => $traduction ) {
			if ( ! isset( $cible[ $lang ] ) && (int) $traduction > 0 ) {
				$cible[ $lang ] = (int) $traduction;
			}
		}
	}

	foreach ( $membres as $lang => $id ) {
		$cible[ $lang ] = $id;
	}

	$a_jour = true;

	foreach ( $cible as $id ) {
		if ( ! ditl_import_memes_traductions( $lire( $id ), $cible ) ) {
			$a_jour = false;
			break;
		}
	}

	if ( $a_jour ) {
		return true;
	}

	$correspondance = ditl_import_ordonner_langues( $cible );

	return 'terme' === $nature ? (bool) ditl_ml_lier_traductions_termes( $correspondance ) : (bool) ditl_ml_lier_traductions_posts( $correspondance );
}

/**
 * Traite une ligne du fichier des referentiels (toutes les langues).
 *
 * @param array $ligne      Ligne du plan.
 * @param bool  $simulation True : aucune ecriture.
 * @return array Resultat de la ligne.
 */
function ditl_import_traiter_referentiel( $ligne, $simulation ) {
	$resultat = ditl_import_resultat_ligne( $ligne );

	if ( $ligne['erreur'] ) {
		$resultat['action'] = 'rejetee';
		return $resultat;
	}

	$taxonomie    = $ligne['taxonomie'];
	$definitions  = ditl_boite_taxonomies();
	$a_couleur    = ! empty( $definitions[ $taxonomie ]['couleur'] );
	$creations    = 0;
	$mises_a_jour = 0;
	$termes       = array();

	foreach ( ditl_ml_langues() as $lang ) {
		$libelle   = $ligne['libelles'][ $lang ];
		$terme     = ditl_boite_terme_par_code( $taxonomie, $ligne['code'], $lang );
		$parent_id = 0;

		if ( '' !== $ligne['parent'] ) {
			$parent = ditl_boite_terme_par_code( $taxonomie, $ligne['parent'], $lang );

			if ( $parent instanceof WP_Term ) {
				$parent_id = (int) $parent->term_id;
			} elseif ( ! $simulation ) {
				ditl_import_message( $resultat, 'erreur', sprintf( 'langue %1$s : parent « %2$s » introuvable.', $lang, $ligne['parent'] ) );
				continue;
			}
		}

		// Metas posees dans CHAQUE langue (pas de synchronisation entre traductions).
		$metas = array(
			'_ditl_code'         => $ligne['code'],
			'_ditl_ordre'        => (int) $ligne['ordre'],
			'_ditl_import_terme' => '1',
		);

		if ( $a_couleur && null !== $ligne['couleur'] ) {
			$metas['_ditl_couleur'] = $ligne['couleur'];
		}

		if ( ! $terme instanceof WP_Term ) {
			++$creations;

			if ( $simulation ) {
				continue;
			}

			$cree = wp_insert_term(
				wp_slash( $libelle ),
				$taxonomie,
				array(
					'slug'   => $ligne['code'] . '-' . $lang,
					'parent' => $parent_id,
				)
			);

			if ( is_wp_error( $cree ) ) {
				ditl_import_message( $resultat, 'erreur', sprintf( 'langue %1$s : création impossible (%2$s).', $lang, $cree->get_error_message() ) );
				continue;
			}

			$term_id = (int) $cree['term_id'];
			ditl_ml_definir_langue_terme( $term_id, $lang );

			// Code toujours pose apres la creation (le modele en deduit un du slug).
			foreach ( $metas as $cle => $valeur ) {
				update_term_meta( $term_id, $cle, $valeur );
			}

			$termes[ $lang ] = $term_id;
			continue;
		}

		$term_id         = (int) $terme->term_id;
		$termes[ $lang ] = $term_id;
		$modifications   = array();

		if ( wp_specialchars_decode( $terme->name, ENT_QUOTES ) !== $libelle ) {
			$modifications['name'] = $libelle;
		}

		if ( (int) $terme->parent !== $parent_id ) {
			$modifications['parent'] = $parent_id;
		}

		$metas_modifiees = array();

		foreach ( $metas as $cle => $valeur ) {
			$actuelle = get_term_meta( $term_id, $cle, true );

			if ( (string) $actuelle !== (string) $valeur ) {
				$metas_modifiees[ $cle ] = $valeur;
			}
		}

		if ( array() === $modifications && array() === $metas_modifiees ) {
			continue;
		}

		++$mises_a_jour;

		if ( $simulation ) {
			continue;
		}

		if ( array() !== $modifications ) {
			$modifie = wp_update_term( $term_id, $taxonomie, wp_slash( $modifications ) );

			if ( is_wp_error( $modifie ) ) {
				ditl_import_message( $resultat, 'erreur', sprintf( 'langue %1$s : mise à jour impossible (%2$s).', $lang, $modifie->get_error_message() ) );
			}
		}

		foreach ( $metas_modifiees as $cle => $valeur ) {
			update_term_meta( $term_id, $cle, $valeur );
		}
	}

	// Traductions de la ligne (ecrites seulement si les liens changent).
	if ( ! $simulation ) {
		ditl_import_lier_traductions( 'terme', $termes );
	}

	if ( $creations > 0 ) {
		$resultat['action'] = 'creation';
	} elseif ( $mises_a_jour > 0 ) {
		$resultat['action'] = 'mise_a_jour';
	} else {
		$resultat['action'] = 'inchangee';
	}

	foreach ( $resultat['messages'] as $message ) {
		if ( 'erreur' === $message['niveau'] ) {
			$resultat['action'] = 'echec';
			break;
		}
	}

	return $resultat;
}

/**
 * Compteurs d'un ensemble de resultats de lignes.
 *
 * @param array $resultats Resultats de lignes.
 * @return array Compteurs { lignes, creation, mise_a_jour, inchangee,
 *               ignoree, rejetee, echec, avertissements }.
 */
function ditl_import_compter( $resultats ) {
	$compteurs = array(
		'lignes'         => 0,
		'creation'       => 0,
		'mise_a_jour'    => 0,
		'inchangee'      => 0,
		'ignoree'        => 0,
		'rejetee'        => 0,
		'echec'          => 0,
		'avertissements' => 0,
	);

	foreach ( (array) $resultats as $resultat ) {
		++$compteurs['lignes'];

		if ( isset( $compteurs[ $resultat['action'] ] ) && 'lignes' !== $resultat['action'] ) {
			++$compteurs[ $resultat['action'] ];
		}

		foreach ( $resultat['messages'] as $message ) {
			if ( 'avertissement' === $message['niveau'] ) {
				++$compteurs['avertissements'];
				break;
			}
		}
	}

	return $compteurs;
}

/**
 * Ajoute des compteurs a d'autres (cumul des lots).
 *
 * @param array $total     Compteurs cumules.
 * @param array $compteurs Compteurs a ajouter.
 * @return array Compteurs cumules.
 */
function ditl_import_cumuler( $total, $compteurs ) {
	foreach ( $compteurs as $cle => $valeur ) {
		$total[ $cle ] = ( isset( $total[ $cle ] ) ? (int) $total[ $cle ] : 0 ) + (int) $valeur;
	}

	return $total;
}

/**
 * Libelle d'une action, pour les rapports.
 *
 * @param string $action Action.
 * @param bool   $prevue True pour une simulation (futur).
 * @return string Libelle.
 */
function ditl_import_libelle_action( $action, $prevue = false ) {
	$libelles = array(
		'creation'    => $prevue ? 'sera créée' : 'créée',
		'mise_a_jour' => $prevue ? 'sera mise à jour' : 'mise à jour',
		'inchangee'   => 'inchangée',
		'ignoree'     => $prevue ? 'sera ignorée' : 'ignorée',
		'rejetee'     => 'rejetée (erreur)',
		'echec'       => 'échec',
	);

	return isset( $libelles[ $action ] ) ? $libelles[ $action ] : (string) $action;
}

/**
 * Supprime les fiches importees (definitivement) et les medias crees par
 * l'import qui ne servent plus, puis purge le cache de pages.
 *
 * NE JAMAIS LANCER EN PRODUCTION apres la saisie du client (perte des
 * relectures et retouches faites dans l'administration).
 *
 * @param bool $simulation True : comptage seulement.
 * @return array Tableau { fiches, medias, medias_gardes, occupe }.
 */
function ditl_import_purger_fiches( $simulation ) {
	global $wpdb;

	$resultat = array(
		'fiches'        => 0,
		'medias'        => 0,
		'medias_gardes' => 0,
		'occupe'        => false,
	);

	if ( ! $simulation && ! ditl_import_verrouiller_ecriture() ) {
		$resultat['occupe'] = true;
		return $resultat;
	}

	try {
		$fiches = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- purge ponctuelle.
			$wpdb->prepare(
				"SELECT DISTINCT p.ID, p.post_status FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_ditl_import_id'
				WHERE p.post_type = %s",
				ditl_import_type_fiche()
			)
		);

		$ids = array_map( 'intval', wp_list_pluck( (array) $fiches, 'ID' ) );

		$medias = array_map(
			'intval',
			(array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- purge ponctuelle.
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_ditl_import_image_sha1'
				WHERE p.post_type = 'attachment'"
			)
		);

		$resultat['fiches'] = count( $ids );

		if ( ! $simulation ) {
			ditl_import_debut_ecriture();

			try {
				foreach ( $ids as $id ) {
					wp_delete_post( $id, true );
				}

				// Fiches supprimees : une purge du cache en fin de traitement.
				$GLOBALS['ditl_import_publications'] = count( $ids );
			} finally {
				ditl_import_fin_ecriture();
			}
		}

		foreach ( $medias as $media_id ) {
			// Le media et ses eventuelles traductions (Polylang, gestion des medias).
			$famille = array_values( array_unique( array_merge( array( $media_id ), array_map( 'intval', ditl_ml_traductions_post( $media_id ) ) ) ) );
			$marques = implode( ', ', array_fill( 0, count( $famille ), '%d' ) );
			$exclus  = $simulation ? $ids : array();
			$usages  = (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- purge ponctuelle.
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value IN ($marques)", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- marques %d generees.
					$famille
				)
			);
			$usages  = array_diff( array_map( 'intval', $usages ), $exclus );

			if ( array() !== $usages ) {
				++$resultat['medias_gardes'];
				continue;
			}

			++$resultat['medias'];

			if ( ! $simulation ) {
				foreach ( array_reverse( $famille ) as $id ) {
					if ( get_post( $id ) ) {
						wp_delete_attachment( $id, true );
					}
				}
			}
		}
	} finally {
		if ( ! $simulation ) {
			ditl_import_liberer_ecriture();
		}
	}

	return $resultat;
}

/**
 * Supprime les termes des referentiels CREES OU MIS A JOUR PAR L'IMPORT
 * (marqueur _ditl_import_terme), toutes langues. Les termes saisis dans
 * l'administration ne sont jamais concernes.
 *
 * NE JAMAIS LANCER EN PRODUCTION apres la saisie du client.
 *
 * @param bool $simulation True : comptage seulement.
 * @return array Tableau { termes : nombre supprime ou a supprimer, occupe }.
 */
function ditl_import_purger_referentiels( $simulation ) {
	global $wpdb;

	$resultat = array(
		'termes' => 0,
		'occupe' => false,
	);

	if ( ! $simulation && ! ditl_import_verrouiller_ecriture() ) {
		$resultat['occupe'] = true;
		return $resultat;
	}

	try {
		$taxonomies = ditl_import_taxonomies();
		$marques    = implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) );
		$termes     = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- purge ponctuelle.
			$wpdb->prepare(
				"SELECT DISTINCT tt.term_id, tt.taxonomy, tt.parent FROM {$wpdb->term_taxonomy} tt
				INNER JOIN {$wpdb->termmeta} tm ON tm.term_id = tt.term_id AND tm.meta_key = '_ditl_import_terme'
				WHERE tt.taxonomy IN ($marques)
				ORDER BY tt.parent DESC, tt.term_id ASC", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- marques %s generees.
				$taxonomies
			)
		);

		$resultat['termes'] = count( $termes );

		if ( ! $simulation ) {
			foreach ( $termes as $terme ) {
				wp_delete_term( (int) $terme->term_id, (string) $terme->taxonomy );
			}
		}
	} finally {
		if ( ! $simulation ) {
			ditl_import_liberer_ecriture();
		}
	}

	return $resultat;
}

/**
 * Referentiels actuels, au format du fichier des referentiels (export).
 *
 * Un code = une ligne ; libelles lus dans chaque langue ; ordre de sortie :
 * listes du registre, parents avant enfants, rang puis code.
 *
 * @return array Tableau { entetes : string[], lignes : string[][] }.
 */
function ditl_import_exporter_referentiels() {
	$langues = ditl_ml_langues();
	$entetes = array_merge( array( 'taxonomie', 'code', 'parent', 'couleur', 'ordre' ), $langues );
	$lignes  = array();

	foreach ( ditl_import_taxonomies() as $taxonomie ) {
		$codes = array();

		foreach ( $langues as $lang ) {
			$termes = get_terms(
				array(
					'taxonomy'   => $taxonomie,
					'hide_empty' => false,
					'lang'       => $lang,
				)
			);

			foreach ( is_array( $termes ) ? $termes : array() as $terme ) {
				$code = ditl_boite_code_terme( $terme );

				if ( '' === $code || ditl_ml_langue_terme( $terme->term_id ) !== $lang ) {
					continue;
				}

				if ( ! isset( $codes[ $code ] ) ) {
					$parent = (int) $terme->parent > 0 ? ditl_boite_code_terme( (int) $terme->parent ) : '';

					$codes[ $code ] = array(
						'niveau'   => '' === $parent ? 1 : 2,
						'parent'   => $parent,
						'couleur'  => (string) get_term_meta( $terme->term_id, '_ditl_couleur', true ),
						'ordre'    => (int) get_term_meta( $terme->term_id, '_ditl_ordre', true ),
						'libelles' => array(),
					);
				}

				$codes[ $code ]['libelles'][ $lang ] = wp_specialchars_decode( $terme->name, ENT_QUOTES );
			}
		}

		uksort(
			$codes,
			static function ( $a, $b ) use ( $codes ) {
				$cle_a = array( $codes[ $a ]['niveau'], $codes[ $a ]['ordre'], $a );
				$cle_b = array( $codes[ $b ]['niveau'], $codes[ $b ]['ordre'], $b );

				return $cle_a <=> $cle_b;
			}
		);

		foreach ( $codes as $code => $infos ) {
			$ligne = array( ditl_import_nom_liste( $taxonomie, $infos['niveau'] ), $code, $infos['parent'], $infos['couleur'], (string) $infos['ordre'] );

			foreach ( $langues as $lang ) {
				$ligne[] = isset( $infos['libelles'][ $lang ] ) ? $infos['libelles'][ $lang ] : '';
			}

			$lignes[] = $ligne;
		}
	}

	return array(
		'entetes' => $entetes,
		'lignes'  => $lignes,
	);
}

/**
 * Ecrit un CSV (UTF-8 avec BOM, point-virgule) dans un flux, cellules
 * neutralisees contre l'injection de formules.
 *
 * @param resource $flux    Flux ouvert en ecriture.
 * @param string[] $entetes En-tetes.
 * @param array    $lignes  Lignes (tableaux de cellules).
 */
function ditl_import_ecrire_csv( $flux, $entetes, $lignes ) {
	fwrite( $flux, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- flux de sortie.
	fputcsv( $flux, array_map( 'ditl_import_neutraliser_cellule', $entetes ), ';', '"', '' );

	foreach ( $lignes as $ligne ) {
		fputcsv( $flux, array_map( 'ditl_import_neutraliser_cellule', (array) $ligne ), ';', '"', '' );
	}
}
