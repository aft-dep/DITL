<?php
/**
 * Template Name: Gabarit E-learning
 *
 * Gabarit des pages "Parcours e-learning" (livrable 3, une page par langue).
 * Il reprend tel quel le modele de page d'Astra (titre, contenu de
 * l'editeur, mise en page) : seuls les modules e-learning choisis dans la
 * metabox du gabarit (inc/metaboxes/elearning.php) s'ajoutent sous le
 * contenu, rendus par ditl_elearning_afficher_modules() (inc/elearning.php)
 * sur le crochet astra_entry_content_after. Une page sans module rend donc
 * exactement comme une page standard.
 *
 * Le modele d'Astra est inclus plutot que recopie : sa structure suit
 * automatiquement les mises a jour du theme parent.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require get_template_directory() . '/page.php';
