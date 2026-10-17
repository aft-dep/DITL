<?php
/**
 * Boite a outils (phase 3) : point d'entree.
 *
 * Moteur de recherche filtre des fiches outils : questionnaire en etapes
 * (metier, tache, difficulte, empechement), resultats filtrables par
 * environnement, catalogue complet, fiche detail imprimable, suivi des
 * interactions dans Google Analytics.
 *
 * Organisation :
 * - multilingue.php  : adaptateur Polylang / WPML (seul point de contact
 *   avec l'extension multilingue) ;
 * - modele.php       : type de publication, taxonomies a niveaux, metas ;
 * - commun.php       : etapes du questionnaire, parametres d'URL, textes de
 *   la page, contraste des couleurs ;
 * - moteur.php       : lecture du parcours, choix proposes, correspondance
 *   des fiches, resultats, URL ;
 * - seo.php          : titre de page et indexation des vues du parcours ;
 * - admin-fiche.php  : metabox de la fiche outil ;
 * - admin-termes.php : champs des referentiels (code, ordre, couleur) ;
 * - admin-page.php   : metabox des textes de la page Boite a outils ;
 * - front.php        : chargement des feuilles et scripts, gabarits.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Temoin de chargement de la boite a outils.
 *
 * @return bool Toujours true.
 */
function ditl_boite_charge() {
	return true;
}

require_once __DIR__ . '/multilingue.php';
require_once __DIR__ . '/modele.php';
require_once __DIR__ . '/commun.php';
require_once __DIR__ . '/moteur.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/front.php';

if ( is_admin() ) {
	require_once __DIR__ . '/admin-fiche.php';
	require_once __DIR__ . '/admin-termes.php';
	require_once __DIR__ . '/admin-page.php';
}
