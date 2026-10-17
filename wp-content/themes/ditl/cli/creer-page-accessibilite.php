<?php
/**
 * Pages "Declaration d'accessibilite" FR et EN (lot a11y B6).
 *
 * CONSTAT : l'audit Access42 du 09/06/2026 (RGAA 4.1 = 48,28 %, RAWeb 1.1 =
 * 50 %, non conforme EN 301 549 v3.2.1) inclut dans son echantillon une page
 * "Accessibilite" (P03) qui n'existait pas, et fournit un modele de
 * declaration a completer (2026-06-09_AFT-Dev-DiTL_declaration-accessibilite-
 * RGAA.docm). Les consignes de publication demandent une page HTML
 * accessible (niveaux de titres, pas de paragraphes vides, citation balisee),
 * et la mention "Accessibilite : non conforme" en lien vers cette page
 * (posee par le theme : inc/accessibilite.php).
 *
 * CE QUE FAIT LE SCRIPT (operation de donnees, aucun fichier modifie) :
 * 1. cree - ou met a jour - deux pages classiques (gabarit par defaut, pas
 *    d'Elementor, pas d'ACF, pas de blocs) :
 *    - FR "Declaration d'accessibilite", slug accessibilite -> /fr/accessibilite/
 *    - EN "Accessibility statement", slug accessibility -> /accessibility/
 *    contenu = la declaration Access42 completee (voir les fonctions
 *    ditl_declaration_*), H1 = titre de la page rendu par Astra, puis
 *    H2 / H3 / H4 comme dans le modele ;
 * 2. leur affecte leur langue Polylang et les lie comme traductions ;
 * 3. pose sur chacune la meta marqueur _ditl_declaration_accessibilite
 *    (valeur : la langue) et l'empreinte md5 du contenu ecrit
 *    (_ditl_declaration_empreinte) ;
 * 4. enregistre leurs identifiants dans l'option ditl_page_accessibilite
 *    (tableau langue => ID), lue par le theme pour la mention du pied de page.
 *
 * GARDE-FOUS :
 * - AJOUT AUTOMATIQUE AUX MENUS NEUTRALISE : le menu principal FR est en
 *   "ajout automatique des nouvelles pages" (option nav_menu_options,
 *   auto_add = [55]) ; sans precaution, la page FR publiee entrerait dans
 *   l'en-tete du site. Le hook du coeur _wp_auto_add_pages_to_menu est
 *   retire pendant l'execution, et un controle final signale tout element
 *   de menu qui viserait ces pages.
 * - COLLISION DE SLUG : si une autre page, un article ou un media porte deja
 *   le slug vise, corbeille comprise, le script s'arrete AVANT toute
 *   ecriture (permaliens en /%postname%/ : la page masquerait un article de
 *   meme slug).
 * - FILTRES D'ENREGISTREMENT : content_save_pre est simule avant ecriture
 *   (refus si un filtre alterait le HTML), puis le contenu est relu en base
 *   et compare au byte pres. kses n'est pas couvert par ce controle : WP-CLI
 *   le retire en toute circonstance. LIMITE CONNUE : kses n'autorise pas
 *   hreflang sur les liens ; un enregistrement depuis l'admin par un compte
 *   sans unfiltered_html le retirerait (l'empreinte protege ensuite ce
 *   contenu au rejeu).
 * - MODIFICATION MANUELLE PROTEGEE : si le contenu en base a ete retouche
 *   depuis le dernier passage du script (empreinte differente), il n'est
 *   PAS ecrase ; l'argument "ecraser" force la reecriture.
 *
 * Le contenu de la declaration FAIT FOI ICI : apres un nouvel audit, mettre a
 * jour les fonctions de contenu, rejouer le script, et changer l'etat dans
 * ditl_accessibilite_statut() (inc/accessibilite.php) si le taux change de
 * tranche. Les URLs des pages auditees sont celles de l'audit (production).
 *
 * Script idempotent : un rejeu sans changement n'ecrit rien.
 *
 * MODE ANNULER : met les deux pages A LA CORBEILLE (refuse si la corbeille
 * est desactivee, EMPTY_TRASH_DAYS a 0 : ce serait une suppression
 * definitive). La mention disparait du pied de page (le theme n'affiche que
 * des pages publiees). L'option ditl_page_accessibilite est CONSERVEE
 * volontairement : un rejeu du mode normal sort les memes pages de la
 * corbeille au lieu d'en creer des doublons, tant que la corbeille n'a pas
 * ete videe (30 jours par defaut) ; au-dela, il les recree.
 *
 * ============================ RUNBOOK PREPROD / PROD ============================
 * Les pages sont des DONNEES : elles n'existent pas dans le depot et doivent
 * etre creees sur chaque environnement en rejouant ce script. Leurs
 * identifiants different d'un environnement a l'autre, d'ou l'option.
 * 1. Deployer le code du theme (0.22.0 ou plus, avec inc/accessibilite.php).
 * 2. Simulation :   wp eval-file wp-content/themes/ditl/cli/creer-page-accessibilite.php dry-run
 *    Verifier : "AUCUNE COLLISION", langues fr et en presentes, contenu non
 *    altere par les filtres d'enregistrement.
 * 3. Application :  wp eval-file wp-content/themes/ditl/cli/creer-page-accessibilite.php
 * 4. Purger le cache de page (WP Fastest Cache : wp-content/cache/all) : la
 *    mention apparait dans le pied de TOUTES les pages.
 * 5. Recette : /fr/accessibilite/ et /accessibility/ (200, H1 puis H2/H3/H4,
 *    liens de langue Polylang entre les deux) ; pied de page de l'accueil FR
 *    ("Accessibilite : non conforme" -> /fr/accessibilite/), EN
 *    ("Accessibility: not compliant" -> /accessibility/), ES/PT/DE (mention
 *    traduite -> /accessibility/) ; en-tete FR INCHANGE (aucune entree
 *    ajoutee au menu principal) ; Tab jusqu'a la mention : anneau de focus.
 * 6. Un rejeu doit afficher "0 ecriture".
 * 7. Une fois en production : PREVENIR ACCESS42 de la publication (consigne :
 *    la page doit entrer dans l'echantillon d'audit).
 * 8. Retour arriere :
 *    wp eval-file wp-content/themes/ditl/cli/creer-page-accessibilite.php annuler
 * ===============================================================================
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/creer-page-accessibilite.php dry-run
 *   wp eval-file wp-content/themes/ditl/cli/creer-page-accessibilite.php
 *   wp eval-file wp-content/themes/ditl/cli/creer-page-accessibilite.php annuler
 *   wp eval-file wp-content/themes/ditl/cli/creer-page-accessibilite.php ecraser
 *
 * Le mode simulation accepte "dry-run" ou "--dry-run", l'annulation
 * "annuler" ou "--annuler" (combinables). "ecraser" reecrit un contenu
 * retouche a la main depuis le dernier passage du script.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Usage : wp eval-file <script> [dry-run] [annuler] [ecraser] - voir le docblock.
	// En acces web direct, reponse muette pour ne rien reveler du fichier.
	http_response_code( 404 );
	exit( 1 );
}

// Bibliotheque commune des scripts CLI du theme.
require_once __DIR__ . '/commun.php';

// ---------------------------------------------------------------------------
// Reperes fixes du chantier.
// ---------------------------------------------------------------------------

// Option lue par le theme (inc/accessibilite.php) : langue => ID de page.
$ditl_option_pages = 'ditl_page_accessibilite';

// Meta marqueur (valeur : langue) et empreinte du dernier contenu ecrit.
$ditl_meta_marqueur  = '_ditl_declaration_accessibilite';
$ditl_meta_empreinte = '_ditl_declaration_empreinte';

// ---------------------------------------------------------------------------
// Lecture des arguments : simulation, annulation, ecrasement.
// ---------------------------------------------------------------------------

// L'argument propre au script est retire avant la lecture commune des
// modes. Tout argument inconnu arrete le script (la lecture commune ne
// ferait que le signaler) : une faute de frappe sur "dry-run" lancerait
// sinon l'ecriture reelle.
$ditl_args_modes = array();
$ditl_ecraser    = false;

foreach ( (array) $args as $ditl_arg ) {
	if ( 'ecraser' === $ditl_arg || '--ecraser' === $ditl_arg ) {
		$ditl_ecraser = true;
		continue;
	}

	if ( ! in_array( $ditl_arg, array( 'dry-run', '--dry-run', 'annuler', '--annuler' ), true ) ) {
		WP_CLI::error( sprintf( 'Argument inconnu : %s (attendus : dry-run, annuler, ecraser). Aucune ecriture.', $ditl_arg ) );
	}

	$ditl_args_modes[] = $ditl_arg;
}

$ditl_modes   = ditl_cli_lire_modes( $ditl_args_modes );
$ditl_dry_run = $ditl_modes['dry_run'];
$ditl_annuler = $ditl_modes['annuler'];

if ( $ditl_dry_run ) {
	WP_CLI::log( '=== MODE SIMULATION (dry-run) : aucune ecriture en base ===' );
}

// Compteur d'ecritures effectives (idempotence : rejeu = zero ecriture).
$ditl_ecritures = 0;

// ---------------------------------------------------------------------------
// Contenu de la declaration (modele Access42 complete).
// ---------------------------------------------------------------------------

if ( ! function_exists( 'ditl_declaration_non_conformites' ) ) {
	/**
	 * Criteres non conformes releves par l'audit (liste du modele Access42).
	 *
	 * @return array Tableau critere => ( fr => intitule, en => intitule ).
	 */
	function ditl_declaration_non_conformites() {
		return array(
			'1.1'   => array(
				'fr' => 'Une image porteuse d’information au moins n’a pas d’alternative textuelle.',
				'en' => 'At least one informative image has no text alternative.',
			),
			'1.2'   => array(
				'fr' => 'Une image de décoration au moins n’est pas ignorée des technologies d’assistance.',
				'en' => 'At least one decorative image is not ignored by assistive technologies.',
			),
			'1.3'   => array(
				'fr' => 'L’alternative textuelle d’une image porteuse d’information au moins n’est pas pertinente.',
				'en' => 'The text alternative of at least one informative image is not relevant.',
			),
			'3.2'   => array(
				'fr' => 'Un texte au moins a des contrastes insuffisants.',
				'en' => 'At least one text has insufficient contrast.',
			),
			'3.3'   => array(
				'fr' => 'Un composant d’interface ou un élément graphique porteur d’information au moins a des contrastes insuffisants.',
				'en' => 'At least one user interface component or informative graphic element has insufficient contrast.',
			),
			'4.8'   => array(
				'fr' => 'Un média non temporel au moins, n’a pas d’alternative.',
				'en' => 'At least one non-time-based media has no alternative.',
			),
			'4.12'  => array(
				'fr' => 'Un média non temporel au moins n’est pas contrôlable au clavier et à tout dispositif de pointage.',
				'en' => 'At least one non-time-based media cannot be controlled with the keyboard and any pointing device.',
			),
			'4.13'  => array(
				'fr' => 'Un média temporel ou non temporel au moins n’est pas compatible avec les technologies d’assistance.',
				'en' => 'At least one time-based or non-time-based media is not compatible with assistive technologies.',
			),
			'6.1'   => array(
				'fr' => 'Un lien au moins a un intitulé qui n’est pas pertinent.',
				'en' => 'At least one link has a label that is not explicit.',
			),
			'6.2'   => array(
				'fr' => 'Un lien au moins n’a pas d’intitulé.',
				'en' => 'At least one link has no label.',
			),
			'7.1'   => array(
				'fr' => 'Une fonctionnalité JavaScript au moins n’est pas compatible avec les technologies d’assistance ou fait un usage inapproprié de propriétés ARIA.',
				'en' => 'At least one JavaScript feature is not compatible with assistive technologies or makes inappropriate use of ARIA properties.',
			),
			'7.3'   => array(
				'fr' => 'Une fonctionnalité JavaScript au moins n’est pas contrôlable par le clavier ou tout autre dispositif de pointage.',
				'en' => 'At least one JavaScript feature cannot be controlled with the keyboard or any other pointing device.',
			),
			'8.7'   => array(
				'fr' => 'Un changement de langue au moins n’est pas identifié.',
				'en' => 'At least one change of language is not identified.',
			),
			'8.9'   => array(
				'fr' => 'Une balise au moins est utilisée à des fins de présentation (par exemple des paragraphes vides et/ou des textes non structurés dans des balises de paragraphes).',
				'en' => 'At least one tag is used for presentation purposes (for example empty paragraphs and/or unstructured text in paragraph tags).',
			),
			'9.1'   => array(
				'fr' => 'La hiérarchie des titres d’une page au moins n’est pas pertinente.',
				'en' => 'The heading hierarchy of at least one page is not relevant.',
			),
			'9.2'   => array(
				'fr' => 'La structure du document d’une page au moins n’est pas cohérente.',
				'en' => 'The document structure of at least one page is not consistent.',
			),
			'9.3'   => array(
				'fr' => 'Une liste au moins n’est pas correctement structurée.',
				'en' => 'At least one list is not correctly structured.',
			),
			'10.1'  => array(
				'fr' => 'Un élément HTML de présentation au moins (balise ou attribut) est utilisé.',
				'en' => 'At least one presentational HTML element (tag or attribute) is used.',
			),
			'10.3'  => array(
				'fr' => 'Un contenu au moins ne se présente pas dans un ordre logique dans le code source.',
				'en' => 'At least one piece of content is not presented in a logical order in the source code.',
			),
			'10.6'  => array(
				'fr' => 'Un lien en environnement de texte au moins n’est pas suffisamment visible.',
				'en' => 'At least one link within text is not sufficiently visible.',
			),
			'10.7'  => array(
				'fr' => 'Une indication visuelle de prise de focus au moins n’est pas visible ou suffisamment contrastée.',
				'en' => 'At least one visual focus indicator is not visible or does not have sufficient contrast.',
			),
			'11.1'  => array(
				'fr' => 'Un champ de formulaire au moins n’a pas d’étiquette.',
				'en' => 'At least one form field has no label.',
			),
			'11.9'  => array(
				'fr' => 'L’intitulé d’un bouton de formulaire au moins n’est pas pertinent.',
				'en' => 'The label of at least one form button is not relevant.',
			),
			'11.10' => array(
				'fr' => 'Le contrôle de saisie d’un champ au moins n’est pas pertinent (absence d’identification des champs obligatoires et/ou absence de présentation des formats de données attendus).',
				'en' => 'Input validation of at least one field is not relevant (required fields are not identified and/or expected data formats are not presented).',
			),
			'11.11' => array(
				'fr' => 'Un message d’erreur au moins ne présente pas de suggestions facilitant la correction des erreurs de saisie, ou présente des suggestions non pertinentes.',
				'en' => 'At least one error message does not provide suggestions to help correct input errors, or provides irrelevant suggestions.',
			),
			'11.13' => array(
				'fr' => 'Un champ de formulaire au moins ne permet pas le remplissage automatique.',
				'en' => 'At least one form field does not allow automatic completion.',
			),
			'12.6'  => array(
				'fr' => 'Une zone de regroupement au moins ne peut pas être atteinte.',
				'en' => 'At least one content grouping area cannot be reached.',
			),
			'13.3'  => array(
				'fr' => 'Un document bureautique au moins (doc, odt ou PDF) n’est pas accessible et ne possède pas d’alternative.',
				'en' => 'At least one office document (doc, odt or PDF) is not accessible and has no alternative.',
			),
			'13.8'  => array(
				'fr' => 'Un contenu clignotant ou en mouvement au moins n’est pas contrôlable par l’utilisateur.',
				'en' => 'At least one blinking or moving content cannot be controlled by the user.',
			),
			'13.10' => array(
				'fr' => 'Une fonctionnalité au moins, utilisable au moyen d’un geste complexe, n’a pas d’alternative au moyen d’un geste simple.',
				'en' => 'At least one feature that uses a complex gesture has no alternative using a simple gesture.',
			),
		);
	}
}

if ( ! function_exists( 'ditl_declaration_pages_auditees' ) ) {
	/**
	 * Pages de l'echantillon verifiees par l'audit (liste visible du modele).
	 *
	 * Toutes en francais, URLs de production telles qu'auditees. En anglais,
	 * les titres propres des contenus restent en francais (attribut lang).
	 * P03 (Accessibilite) n'existait pas au moment de l'audit : absente.
	 *
	 * @return array Liste de ( fr, en, en_lang_fr, url ).
	 */
	function ditl_declaration_pages_auditees() {
		return array(
			array( 'Page d’accueil', 'Home page', false, 'https://ditlproject.eu/fr/accueil/' ),
			array( 'Mentions légales', 'Legal notice', false, 'https://ditlproject.eu/fr/mentions-legales/' ),
			array( 'Contact', 'Contact', false, 'https://ditlproject.eu/fr/contact/' ),
			array( 'Recherche', 'Search', false, 'https://ditlproject.eu/fr/?s=' ),
			array( 'Projet DITL', 'DiTL project', false, 'https://ditlproject.eu/fr/le-projet-ditl/' ),
			array( 'Partenaires', 'Partners', false, 'https://ditlproject.eu/fr/partenaires/' ),
			array( 'Résultats', 'Results', false, 'https://ditlproject.eu/fr/resultat/' ),
			array( 'Règlementations et Dispositions Inclusives dans le Transport et la Logistique', 'Règlementations et Dispositions Inclusives dans le Transport et la Logistique', true, 'https://ditlproject.eu/fr/livrable-1/' ),
			array( 'Actualités', 'News', false, 'https://ditlproject.eu/fr/actualite/' ),
			array( 'Publication de la première newsletter du projet DiTL', 'Publication de la première newsletter du projet DiTL', true, 'https://ditlproject.eu/fr/publication-de-la-premiere-newsletter-du-projet-ditl/' ),
			array( 'Promouvoir l’inclusion dans le secteur de la logistique et du transport', 'Promouvoir l’inclusion dans le secteur de la logistique et du transport', true, 'https://ditlproject.eu/fr/promouvoir-linclusion-dans-le-secteur-de-la-logistique-et-du-transport/' ),
		);
	}
}

if ( ! function_exists( 'ditl_declaration_lien' ) ) {
	/**
	 * Lien HTML echappe.
	 *
	 * @param string $url      Adresse.
	 * @param string $texte    Intitule (texte brut).
	 * @param string $hreflang Langue de la cible si differente de la page.
	 * @return string
	 */
	function ditl_declaration_lien( $url, $texte, $hreflang = '' ) {
		return sprintf(
			'<a href="%1$s"%2$s>%3$s</a>',
			esc_url( $url ),
			'' !== $hreflang ? ' hreflang="' . esc_attr( $hreflang ) . '"' : '',
			esc_html( $texte )
		);
	}
}

if ( ! function_exists( 'ditl_declaration_contenu' ) ) {
	/**
	 * HTML de la declaration pour une langue.
	 *
	 * Forme stable au passage de wpautop (applique par the_content) : un
	 * element de bloc par ligne, aucune ligne vide, aucun saut de ligne a
	 * l'interieur d'un element. Textes du modele Access42 ; seuls les champs
	 * surlignes du modele ont ete completes.
	 *
	 * @param string $langue "fr" ou "en".
	 * @return string
	 */
	function ditl_declaration_contenu( $langue ) {
		$fr        = 'fr' === $langue;
		$rgaa      = 'https://www.numerique.gouv.fr/publications/rgaa-accessibilite/';
		$etsi      = 'https://www.etsi.org/deliver/etsi_en/301500_301599/301549/03.02.01_60/en_301549v030201p.pdf';
		$raweb     = 'https://accessibilite.public.lu/fr/raweb1.1/index.html';
		$defenseur = 'https://formulaire.defenseurdesdroits.fr/formulaire_saisine/';
		$accueil   = 'https://ditlproject.eu/fr/accueil/';
		$l         = array();

		if ( $fr ) {
			$l[] = '<p>AFT-Dev s’engage à rendre son site internet accessible conformément à l’article 47 de la loi n°&nbsp;2005-102 du 11&nbsp;février 2005 et à l’article 16 de la loi n°&nbsp;2023-171 du 9&nbsp;mars 2023.</p>';
			$l[] = '<p>À cette fin, le site fait l’objet d’une refonte technique qui intègre des corrections fondées sur l’audit décrit ci-dessous. La présente déclaration décrit l’état du site constaté lors de cet audit&nbsp;; elle sera mise à jour à l’issue de ces travaux.</p>';
			$l[] = '<p>La présente déclaration d’accessibilité s’applique au site DiTL.</p>';
			$l[] = '<h2>État de conformité</h2>';
			$l[] = '<p>Le site DiTL (' . ditl_declaration_lien( $accueil, $accueil ) . ') est non conforme avec le ' . ditl_declaration_lien( $rgaa, 'RGAA version 4.1' ) . ', en raison des non-conformités énumérées dans la section «&nbsp;Résultats des tests&nbsp;».</p>';
			$l[] = '<p>Le site DiTL est non conforme avec la ' . ditl_declaration_lien( $etsi, 'norme européenne 301 549', 'en' ) . ' (v3.2.1).</p>';
			$l[] = '<p>La méthodologie d’audit se base sur le ' . ditl_declaration_lien( $raweb, 'Référentiel d’Évaluation de l’Accessibilité Web (RAWeb 1.1)' ) . ', seule méthode opérationnelle publiée à ce jour pour vérifier l’ensemble des critères de la norme européenne.</p>';
			$l[] = '<h2>Résultats des tests</h2>';
			$l[] = '<p>L’audit de conformité réalisé par la société Access42 révèle que le site est conforme à 48,28&nbsp;% au RGAA version 4.1.</p>';
			$l[] = '<h3>Contenus inaccessibles</h3>';
			$l[] = '<p>Les contenus listés ci-dessous ne sont pas accessibles pour les raisons suivantes.</p>';
			$l[] = '<h4>Non-conformités</h4>';
		} else {
			$l[] = '<p>AFT-Dev is committed to making its website accessible in accordance with Article 47 of French Law No.&nbsp;2005-102 of 11&nbsp;February 2005 and Article 16 of French Law No.&nbsp;2023-171 of 9&nbsp;March 2023.</p>';
			$l[] = '<p>To this end, the website is undergoing a technical overhaul that includes corrections based on the audit described below. This statement describes the state of the website as found during that audit; it will be updated once this work is complete.</p>';
			$l[] = '<p>This accessibility statement applies to the DiTL website.</p>';
			$l[] = '<h2>Compliance status</h2>';
			$l[] = '<p>The DiTL website (' . ditl_declaration_lien( $accueil, $accueil ) . ') is not compliant with the French accessibility framework ' . ditl_declaration_lien( $rgaa, 'RGAA version 4.1', 'fr' ) . ', due to the non-compliances listed in the “Test results” section.</p>';
			$l[] = '<p>The DiTL website is not compliant with the ' . ditl_declaration_lien( $etsi, 'European standard EN 301 549' ) . ' (v3.2.1).</p>';
			$l[] = '<p>The audit methodology is based on the web accessibility assessment framework ' . ditl_declaration_lien( $raweb, 'RAWeb 1.1', 'fr' ) . ', the only operational method published to date for checking all the criteria of the European standard.</p>';
			$l[] = '<h2>Test results</h2>';
			$l[] = '<p>The compliance audit carried out by Access42 shows that the website is 48.28% compliant with RGAA version 4.1.</p>';
			$l[] = '<h3>Non-accessible content</h3>';
			$l[] = '<p>The content listed below is not accessible for the following reasons.</p>';
			$l[] = '<h4>Non-compliances</h4>';
		}

		$l[] = '<ul>';
		foreach ( ditl_declaration_non_conformites() as $critere => $intitules ) {
			$l[] = '<li>' . esc_html( $critere ) . ' - ' . esc_html( $intitules[ $fr ? 'fr' : 'en' ] ) . '</li>';
		}
		$l[] = '</ul>';

		if ( $fr ) {
			$l[] = '<h2>Établissement de cette déclaration d’accessibilité</h2>';
			$l[] = '<p>Cette déclaration a été établie le 8&nbsp;juin 2026.</p>';
			$l[] = '<h3>Technologies utilisées pour la réalisation du site</h3>';
			$l[] = '<ul>';
			$l[] = '<li>HTML5</li>';
			$l[] = '<li>CSS</li>';
			$l[] = '<li>JavaScript</li>';
			$l[] = '<li>Outil de gestion de contenus&nbsp;: WordPress</li>';
			$l[] = '</ul>';
			$l[] = '<h3>Agents utilisateurs, technologies d’assistance et outils utilisés pour vérifier l’accessibilité</h3>';
			$l[] = '<p>Les tests des pages web ont été effectués avec les combinaisons de navigateurs web et lecteurs d’écran suivants&nbsp;:</p>';
			$l[] = '<ul>';
			$l[] = '<li>Firefox 151.0.2 et NVDA</li>';
			$l[] = '<li>Firefox 151.0.2 et JAWS</li>';
			$l[] = '<li>Safari 26.5 et VoiceOver (macOS 26.5)</li>';
			$l[] = '<li>Safari et VoiceOver (iOS 18.3)</li>';
			$l[] = '<li>Chrome et TalkBack (Android 14)</li>';
			$l[] = '</ul>';
			$l[] = '<p>La vérification de l’accessibilité est le résultat de tests manuels, assistés par des outils (feuilles CSS dédiées, extensions HeadingsMaps et WebDeveloper Toolbar, Color Contrast Analyser).</p>';
			$l[] = '<h3>Pages du site ayant fait l’objet de la vérification de conformité</h3>';
		} else {
			$l[] = '<h2>Preparation of this accessibility statement</h2>';
			$l[] = '<p>This statement was prepared on 8&nbsp;June 2026.</p>';
			$l[] = '<h3>Technologies used to build the website</h3>';
			$l[] = '<ul>';
			$l[] = '<li>HTML5</li>';
			$l[] = '<li>CSS</li>';
			$l[] = '<li>JavaScript</li>';
			$l[] = '<li>Content management system: WordPress</li>';
			$l[] = '</ul>';
			$l[] = '<h3>User agents, assistive technologies and tools used to check accessibility</h3>';
			$l[] = '<p>The web pages were tested with the following combinations of web browsers and screen readers:</p>';
			$l[] = '<ul>';
			$l[] = '<li>Firefox 151.0.2 and NVDA</li>';
			$l[] = '<li>Firefox 151.0.2 and JAWS</li>';
			$l[] = '<li>Safari 26.5 and VoiceOver (macOS 26.5)</li>';
			$l[] = '<li>Safari and VoiceOver (iOS 18.3)</li>';
			$l[] = '<li>Chrome and TalkBack (Android 14)</li>';
			$l[] = '</ul>';
			$l[] = '<p>The accessibility check is the result of manual tests, assisted by tools (dedicated CSS style sheets, HeadingsMaps and WebDeveloper Toolbar extensions, Color Contrast Analyser).</p>';
			$l[] = '<h3>Pages of the website checked for compliance</h3>';
			$l[] = '<p>The audit was carried out on the French version of the website.</p>';
		}

		$l[] = '<ul>';
		foreach ( ditl_declaration_pages_auditees() as $page ) {
			$titre = esc_html( $fr ? $page[0] : $page[1] );
			if ( ! $fr && $page[2] ) {
				$titre = '<span lang="fr">' . $titre . '</span>';
			}
			$l[] = '<li>' . $titre . ' (' . ditl_declaration_lien( $page[3], $page[3] ) . ')</li>';
		}
		$l[] = '</ul>';

		if ( $fr ) {
			$l[] = '<h2>Retour d’information et contact</h2>';
			$l[] = '<p>Il est important de rappeler qu’en vertu de l’article 11 de la loi de février 2005&nbsp;:</p>';
			$l[] = '<blockquote><p>«&nbsp;la personne handicapée a droit à la compensation des conséquences de son handicap, quels que soient l’origine et la nature de sa déficience, son âge ou son mode de vie.&nbsp;»</p></blockquote>';
			$l[] = '<p>AFT s’engage à prendre les moyens nécessaires afin de donner accès, dans un délai raisonnable, aux informations et fonctionnalités recherchées par la personne handicapée, que le contenu fasse l’objet d’une dérogation ou non.</p>';
			$l[] = '<p>AFT invite les personnes qui rencontreraient des difficultés à la contacter afin qu’une assistance puisse être apportée (alternative accessible, information et contenu donnés sous une autre forme)&nbsp;:</p>';
			$l[] = '<ul>';
			$l[] = '<li>par le formulaire de la page ' . ditl_declaration_lien( '/fr/contact/', 'Contact' ) . ' du site&nbsp;;</li>';
			$l[] = '<li>par courriel&nbsp;: ' . ditl_declaration_lien( 'mailto:ditlprojectaft@gmail.com', 'ditlprojectaft@gmail.com' ) . '&nbsp;;</li>';
			$l[] = '<li>par courrier&nbsp;: AFT, siège social, 82 rue Cardinet, 75017 Paris.</li>';
			$l[] = '</ul>';
			$l[] = '<h2>Voies de recours</h2>';
			$l[] = '<p>Si vous constatez un défaut d’accessibilité vous empêchant d’accéder à un contenu ou une fonctionnalité du site, que vous nous le signalez et que vous ne parvenez pas à obtenir une réponse de notre part, vous êtes en droit de faire parvenir vos doléances ou une demande de saisine au Défenseur des droits.</p>';
			$l[] = '<p>Plusieurs moyens sont à votre disposition&nbsp;:</p>';
			$l[] = '<ul>';
			$l[] = '<li>un ' . ditl_declaration_lien( $defenseur, 'formulaire de contact' ) . '&nbsp;;</li>';
			$l[] = '<li>un numéro de téléphone&nbsp;: 09&nbsp;69&nbsp;39&nbsp;00&nbsp;00&nbsp;;</li>';
			$l[] = '<li>une adresse postale (courrier gratuit, sans affranchissement)&nbsp;: Le Défenseur des droits - Libre réponse 71120 - 75342 Paris CEDEX 07.</li>';
			$l[] = '</ul>';
		} else {
			$l[] = '<h2>Feedback and contact</h2>';
			$l[] = '<p>It is important to remember that, under Article 11 of the French Law of February 2005 (unofficial translation):</p>';
			$l[] = '<blockquote><p>“disabled people are entitled to compensation for the consequences of their disability, whatever the origin and nature of their impairment, their age or their way of life.”</p></blockquote>';
			$l[] = '<p>AFT is committed to taking the necessary measures to give disabled people access, within a reasonable time, to the information and features they are looking for, whether or not the content is subject to an exemption.</p>';
			$l[] = '<p>AFT invites anyone experiencing difficulties to contact it so that assistance can be provided (accessible alternative, information and content provided in another form):</p>';
			$l[] = '<ul>';
			$l[] = '<li>through the form on the website’s ' . ditl_declaration_lien( '/contact-us/', 'Contact us' ) . ' page;</li>';
			$l[] = '<li>by email: ' . ditl_declaration_lien( 'mailto:ditlprojectaft@gmail.com', 'ditlprojectaft@gmail.com' ) . ';</li>';
			$l[] = '<li>by post: AFT, head office, 82 rue Cardinet, 75017 Paris, France.</li>';
			$l[] = '</ul>';
			$l[] = '<h2>Remedies</h2>';
			$l[] = '<p>If you notice an accessibility defect preventing you from accessing content or a feature of the website, you report it to us and you do not obtain a response from us, you are entitled to send your complaints or a referral request to the <span lang="fr">Défenseur des droits</span> (French Defender of Rights).</p>';
			$l[] = '<p>Several means are available to you:</p>';
			$l[] = '<ul>';
			$l[] = '<li>a ' . ditl_declaration_lien( $defenseur, 'contact form', 'fr' ) . ';</li>';
			$l[] = '<li>a telephone number: 09&nbsp;69&nbsp;39&nbsp;00&nbsp;00;</li>';
			$l[] = '<li>a postal address (free of charge, no stamp needed): <span lang="fr">Le Défenseur des droits - Libre réponse 71120 - 75342 Paris CEDEX 07</span>, France.</li>';
			$l[] = '</ul>';
		}

		return implode( "\n", $l );
	}
}

/**
 * Pages a creer, par langue Polylang.
 */
$ditl_pages = array(
	'fr' => array(
		'titre' => 'Déclaration d’accessibilité',
		'slug'  => 'accessibilite',
	),
	'en' => array(
		'titre' => 'Accessibility statement',
		'slug'  => 'accessibility',
	),
);

foreach ( $ditl_pages as $ditl_langue => $ditl_def ) {
	$ditl_pages[ $ditl_langue ]['contenu'] = ditl_declaration_contenu( $ditl_langue );
}

// ---------------------------------------------------------------------------
// Outils locaux.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'ditl_declaration_trouver' ) ) {
	/**
	 * Retrouve la page de declaration d'une langue (option, puis meta marqueur).
	 *
	 * Corbeille comprise : sinon une annulation suivie d'un rejeu creerait
	 * un doublon si l'option avait ete effacee entre-temps. La recherche de
	 * secours ne retient que les pages au slug vise : l'extension Duplicate
	 * Page copie toutes les metas, une copie de la declaration porte donc
	 * aussi le marqueur et ne doit jamais etre prise pour elle.
	 *
	 * @param string $langue    Langue.
	 * @param string $slug      Slug de la page de declaration de cette langue.
	 * @param array  $option    Valeur de l'option ditl_page_accessibilite.
	 * @param string $marqueur  Cle de la meta marqueur.
	 * @return int ID de la page, 0 si aucune.
	 */
	function ditl_declaration_trouver( $langue, $slug, $option, $marqueur ) {
		$memo = isset( $option[ $langue ] ) && is_scalar( $option[ $langue ] ) ? absint( $option[ $langue ] ) : 0;

		if ( $memo > 0 && 'page' === get_post_type( $memo ) && $langue === get_post_meta( $memo, $marqueur, true ) ) {
			return $memo;
		}

		if ( $memo > 0 ) {
			WP_CLI::warning( sprintf( 'Option : la page %d (%s) n\'est plus une page de declaration, recherche par meta marqueur.', $memo, $langue ) );
		}

		$trouves = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'meta_key'         => $marqueur, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $langue, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'post_name__in'    => array( $slug, $slug . '__trashed' ),
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'posts_per_page'   => 5,
				'suppress_filters' => true,
				'lang'             => '',
			)
		);

		if ( count( $trouves ) > 1 ) {
			WP_CLI::error( sprintf( 'Plusieurs pages de declaration %s au slug %s en base (%s) : a departager a la main. Aucune ecriture.', $langue, $slug, implode( ', ', $trouves ) ) );
		}

		return empty( $trouves ) ? 0 : (int) $trouves[0];
	}
}

if ( ! function_exists( 'ditl_declaration_collisions' ) ) {
	/**
	 * Contenus qui occupent deja le slug vise (hors page de declaration).
	 *
	 * Pages de premier niveau et medias (le coeur controle l'unicite d'un
	 * slug de page contre les deux), tous statuts, corbeille comprise (le
	 * slug d'un contenu en corbeille est suffixe de __trashed et repris a la
	 * restauration).
	 *
	 * @param string $slug    Slug vise.
	 * @param int    $exclure ID de la page de declaration (0 si aucune).
	 * @return array Lignes { ID, post_type, post_status, post_name }.
	 */
	function ditl_declaration_collisions( $slug, $exclure ) {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ID, post_type, post_status, post_name FROM {$wpdb->posts}
				WHERE post_name IN ( %s, %s ) AND post_type IN ( 'page', 'attachment', 'post' ) AND post_parent = 0 AND ID != %d",
				$slug,
				$slug . '__trashed',
				$exclure
			),
			ARRAY_A
		);
	}
}

if ( ! function_exists( 'ditl_declaration_menus_visant' ) ) {
	/**
	 * Elements de menu qui visent l'une des pages donnees.
	 *
	 * @param int[] $ids IDs de pages.
	 * @return array Lignes { item, menu_object_id }.
	 */
	function ditl_declaration_menus_visant( $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', (array) $ids ) );
		if ( empty( $ids ) ) {
			return array();
		}

		$place = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.ID AS item, m.meta_value AS menu_object_id FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_menu_item_object_id'
				INNER JOIN {$wpdb->postmeta} o ON o.post_id = p.ID AND o.meta_key = '_menu_item_object' AND o.meta_value = 'page'
				WHERE p.post_type = 'nav_menu_item' AND m.meta_value IN ( $place )", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				$ids
			),
			ARRAY_A
		);
	}
}

// ---------------------------------------------------------------------------
// Controles prealables (aucune ecriture).
// ---------------------------------------------------------------------------

$ditl_option = get_option( $ditl_option_pages, array() );
if ( ! is_array( $ditl_option ) ) {
	$ditl_option = array();
}

$ditl_ids = array();
foreach ( $ditl_pages as $ditl_langue => $ditl_def ) {
	$ditl_ids[ $ditl_langue ] = ditl_declaration_trouver( $ditl_langue, $ditl_def['slug'], $ditl_option, $ditl_meta_marqueur );
}

// ---------------------------------------------------------------------------
// MODE ANNULER : les deux pages a la corbeille.
// ---------------------------------------------------------------------------

if ( $ditl_annuler ) {
	WP_CLI::log( '--- Annulation : pages de declaration a la corbeille ---' );

	// Corbeille desactivee : wp_trash_post() supprimerait definitivement.
	if ( ! EMPTY_TRASH_DAYS ) {
		WP_CLI::error( 'Corbeille desactivee (EMPTY_TRASH_DAYS a 0) : l\'annulation supprimerait definitivement les pages. Aucune ecriture.' );
	}

	foreach ( $ditl_ids as $ditl_langue => $ditl_id ) {
		if ( $ditl_id <= 0 ) {
			WP_CLI::log( sprintf( '  %s : aucune page en base, rien a faire.', $ditl_langue ) );
		} elseif ( 'trash' === get_post_status( $ditl_id ) ) {
			WP_CLI::log( sprintf( '  %s : page %d deja en corbeille, rien a faire.', $ditl_langue, $ditl_id ) );
		} elseif ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] %s : page %d serait mise en corbeille.', $ditl_langue, $ditl_id ) );
		} elseif ( wp_trash_post( $ditl_id ) ) {
			WP_CLI::log( sprintf( '  %s : page %d mise en corbeille.', $ditl_langue, $ditl_id ) );
			$ditl_ecritures++;
		} else {
			WP_CLI::warning( sprintf( '%s : echec de la mise en corbeille de la page %d.', $ditl_langue, $ditl_id ) );
		}
	}

	WP_CLI::log( sprintf( '  Option %s conservee (rejeu = restauration des memes pages tant que la corbeille n\'est pas videe).', $ditl_option_pages ) );
	WP_CLI::success( sprintf( 'Annulation terminee : %d ecriture(s). Purger le cache de page (wp-content/cache/all).', $ditl_ecritures ) );
	return;
}

// --- Polylang : langues presentes et API disponible --------------------------

$ditl_api_polylang = function_exists( 'pll_set_post_language' ) && function_exists( 'pll_save_post_translations' )
	&& function_exists( 'pll_get_post_language' ) && function_exists( 'pll_get_post_translations' ) && function_exists( 'pll_languages_list' );

if ( ! $ditl_api_polylang ) {
	WP_CLI::error( 'API Polylang indisponible (extension inactive ?) : langues et traductions impossibles a poser. Aucune ecriture.' );
}

$ditl_langues_site = (array) pll_languages_list( array( 'fields' => 'slug' ) );
foreach ( array_keys( $ditl_pages ) as $ditl_langue ) {
	if ( ! in_array( $ditl_langue, $ditl_langues_site, true ) ) {
		WP_CLI::error( sprintf( 'Langue Polylang "%s" absente du site (%s). Aucune ecriture.', $ditl_langue, implode( ', ', $ditl_langues_site ) ) );
	}
}
WP_CLI::log( sprintf( 'Langues Polylang du site : %s.', implode( ', ', $ditl_langues_site ) ) );

// --- Theme : la mention du pied de page lit l'option posee ici --------------

if ( function_exists( 'ditl_accessibilite_mention_pied' ) ) {
	WP_CLI::log( 'Theme : mention du pied de page presente (inc/accessibilite.php).' );
} else {
	WP_CLI::warning( 'Theme : inc/accessibilite.php absent ou inactif. Les pages seront creees, mais la mention du pied de page n\'apparaitra qu\'apres deploiement du theme 0.22.0.' );
}

// --- Collisions de slug -----------------------------------------------------

$ditl_bloquant = false;
foreach ( $ditl_pages as $ditl_langue => $ditl_def ) {
	$ditl_collisions = ditl_declaration_collisions( $ditl_def['slug'], $ditl_ids[ $ditl_langue ] );

	foreach ( $ditl_collisions as $ditl_c ) {
		WP_CLI::warning( sprintf( 'Collision %s : le slug "%s" est deja pris par %s %d (statut %s, slug %s).', $ditl_langue, $ditl_def['slug'], $ditl_c['post_type'], $ditl_c['ID'], $ditl_c['post_status'], $ditl_c['post_name'] ) );
		$ditl_bloquant = true;
	}
}

if ( $ditl_bloquant ) {
	WP_CLI::error( 'Slug deja occupe : choisir un autre slug ou liberer celui-ci, puis rejouer. Aucune ecriture.' );
}
WP_CLI::log( 'Slugs : AUCUNE COLLISION (pages, articles et medias, corbeille comprise).' );

// --- Filtres d'enregistrement ----------------------------------------------

foreach ( $ditl_pages as $ditl_langue => $ditl_def ) {
	$ditl_filtre = wp_unslash( (string) apply_filters( 'content_save_pre', wp_slash( $ditl_def['contenu'] ) ) );

	if ( $ditl_filtre !== $ditl_def['contenu'] ) {
		WP_CLI::error( sprintf( 'Contenu %s altere par un filtre d\'enregistrement (content_save_pre) : identifier le filtre en cause avant de rejouer. Aucune ecriture.', $ditl_langue ) );
	}

	WP_CLI::log( sprintf( 'Contenu %s : %d car., md5 %s, non altere par content_save_pre.', $ditl_langue, strlen( $ditl_def['contenu'] ), md5( $ditl_def['contenu'] ) ) );
}

// ---------------------------------------------------------------------------
// Creation ou mise a jour des pages.
// ---------------------------------------------------------------------------

// Ajout automatique aux menus "auto_add" (menu principal FR) neutralise :
// la publication d'une page ne doit rien changer a l'en-tete.
remove_action( 'transition_post_status', '_wp_auto_add_pages_to_menu', 10 );

// Auteur : celui de la page d'accueil (les pages publiees du site sont
// toutes du meme compte). 0 si aucune page d'accueil statique.
$ditl_auteur = (int) get_post_field( 'post_author', (int) get_option( 'page_on_front' ) );

foreach ( $ditl_pages as $ditl_langue => $ditl_def ) {
	$ditl_id      = $ditl_ids[ $ditl_langue ];
	$ditl_protege = false;

	WP_CLI::log( '' );
	WP_CLI::log( sprintf( '--- Page %s : "%s" (slug %s) ---', $ditl_langue, $ditl_def['titre'], $ditl_def['slug'] ) );

	// --- Creation -----------------------------------------------------------
	if ( $ditl_id <= 0 ) {
		if ( $ditl_dry_run ) {
			WP_CLI::log( '  [dry-run] Page absente : serait creee (publiee, gabarit par defaut, commentaires fermes), puis marquee, mise en langue et liee.' );
			continue;
		}

		$ditl_resultat = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => wp_slash( $ditl_def['titre'] ),
				'post_name'      => $ditl_def['slug'],
				'post_content'   => wp_slash( $ditl_def['contenu'] ),
				'post_author'    => $ditl_auteur,
				'post_parent'    => 0,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'page_template'  => 'default',
			),
			true
		);

		if ( is_wp_error( $ditl_resultat ) ) {
			WP_CLI::error( sprintf( 'Echec de la creation de la page %s : %s', $ditl_langue, $ditl_resultat->get_error_message() ) );
		}

		$ditl_id                  = (int) $ditl_resultat;
		$ditl_ids[ $ditl_langue ] = $ditl_id;
		$ditl_ecritures++;
		WP_CLI::log( sprintf( '  Page creee : ID %d.', $ditl_id ) );
	} else {
		// --- Mise a jour d'une page existante -------------------------------
		WP_CLI::log( sprintf( '  Page existante : ID %d (statut %s).', $ditl_id, get_post_status( $ditl_id ) ) );

		// Sortie de corbeille (le coeur restaure le slug d'origine et repasse
		// la page en brouillon ; la publication suit ci-dessous).
		if ( 'trash' === get_post_status( $ditl_id ) ) {
			if ( $ditl_dry_run ) {
				WP_CLI::log( '  [dry-run] Serait sortie de la corbeille puis republiee.' );
			} else {
				if ( ! wp_untrash_post( $ditl_id ) ) {
					WP_CLI::error( sprintf( 'Echec de la sortie de corbeille de la page %d.', $ditl_id ) );
				}
				$ditl_ecritures++;
				WP_CLI::log( '  Sortie de la corbeille.' );
			}
		}

		$ditl_post = get_post( $ditl_id );
		$ditl_maj  = array();

		if ( $ditl_post->post_title !== $ditl_def['titre'] ) {
			$ditl_maj['post_title'] = wp_slash( $ditl_def['titre'] );
		}
		if ( $ditl_post->post_name !== $ditl_def['slug'] && ! ( $ditl_dry_run && 'trash' === $ditl_post->post_status ) ) {
			$ditl_maj['post_name'] = $ditl_def['slug'];
		}
		if ( 'publish' !== $ditl_post->post_status ) {
			$ditl_maj['post_status'] = 'publish';
		}
		if ( 0 !== (int) $ditl_post->post_parent ) {
			$ditl_maj['post_parent'] = 0;
		}
		if ( 'closed' !== $ditl_post->comment_status ) {
			$ditl_maj['comment_status'] = 'closed';
		}
		if ( 'closed' !== $ditl_post->ping_status ) {
			$ditl_maj['ping_status'] = 'closed';
		}

		// Contenu : protege s'il a ete retouche a la main depuis le dernier
		// passage du script (empreinte differente du contenu en base).
		if ( $ditl_post->post_content !== $ditl_def['contenu'] ) {
			$ditl_empreinte = (string) get_post_meta( $ditl_id, $ditl_meta_empreinte, true );

			if ( '' !== $ditl_empreinte && md5( $ditl_post->post_content ) !== $ditl_empreinte && ! $ditl_ecraser ) {
				$ditl_protege = true;
				WP_CLI::warning( sprintf( 'Page %d : contenu modifie depuis le dernier passage du script, NON ecrase (argument "ecraser" pour forcer, apres avoir reporte les modifications dans ce script).', $ditl_id ) );
			} else {
				$ditl_maj['post_content'] = wp_slash( $ditl_def['contenu'] );
			}
		}

		if ( empty( $ditl_maj ) ) {
			WP_CLI::log( '  Titre, slug, statut, contenu : deja conformes.' );
		} elseif ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] Mise a jour : %s.', implode( ', ', array_keys( $ditl_maj ) ) ) );
		} else {
			$ditl_maj['ID'] = $ditl_id;
			$ditl_resultat  = wp_update_post( $ditl_maj, true );

			if ( is_wp_error( $ditl_resultat ) ) {
				WP_CLI::error( sprintf( 'Echec de la mise a jour de la page %d : %s', $ditl_id, $ditl_resultat->get_error_message() ) );
			}

			unset( $ditl_maj['ID'] );
			$ditl_ecritures++;
			WP_CLI::log( sprintf( '  Mise a jour : %s.', implode( ', ', array_keys( $ditl_maj ) ) ) );
		}

		// Gabarit par defaut (meta absente ou "default").
		$ditl_gabarit = (string) get_post_meta( $ditl_id, '_wp_page_template', true );
		if ( '' !== $ditl_gabarit && 'default' !== $ditl_gabarit ) {
			if ( $ditl_dry_run ) {
				WP_CLI::log( sprintf( '  [dry-run] Gabarit "%s" serait remis a "default".', $ditl_gabarit ) );
			} else {
				update_post_meta( $ditl_id, '_wp_page_template', 'default' );
				$ditl_ecritures++;
				WP_CLI::log( sprintf( '  Gabarit "%s" remis a "default".', $ditl_gabarit ) );
			}
		}
	}

	// --- Verification apres ecriture ----------------------------------------
	if ( ! $ditl_dry_run ) {
		clean_post_cache( $ditl_id );
		$ditl_relu = get_post( $ditl_id );

		if ( ! $ditl_relu ) {
			WP_CLI::error( sprintf( 'Page %d introuvable apres ecriture.', $ditl_id ) );
		}
		if ( ! $ditl_protege && $ditl_relu->post_content !== $ditl_def['contenu'] ) {
			WP_CLI::warning( sprintf( 'Page %d : le contenu relu en base differe du contenu prevu (filtre a l\'enregistrement ?). A VERIFIER.', $ditl_id ) );
		}
		if ( $ditl_relu->post_name !== $ditl_def['slug'] ) {
			WP_CLI::warning( sprintf( 'Page %d : slug obtenu "%s" au lieu de "%s". A VERIFIER.', $ditl_id, $ditl_relu->post_name, $ditl_def['slug'] ) );
		}
	}

	// --- Marqueur, empreinte, langue (simulation : constat seulement) -------
	if ( $ditl_langue !== get_post_meta( $ditl_id, $ditl_meta_marqueur, true ) ) {
		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] Meta %s serait posee a %s.', $ditl_meta_marqueur, $ditl_langue ) );
		} else {
			update_post_meta( $ditl_id, $ditl_meta_marqueur, $ditl_langue );
			$ditl_ecritures++;
			WP_CLI::log( sprintf( '  Meta %s = %s.', $ditl_meta_marqueur, $ditl_langue ) );
		}
	}

	$ditl_md5 = md5( $ditl_def['contenu'] );
	if ( ! $ditl_protege && $ditl_md5 !== get_post_meta( $ditl_id, $ditl_meta_empreinte, true ) ) {
		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] Meta %s serait posee a %s.', $ditl_meta_empreinte, $ditl_md5 ) );
		} else {
			update_post_meta( $ditl_id, $ditl_meta_empreinte, $ditl_md5 );
			$ditl_ecritures++;
			WP_CLI::log( sprintf( '  Meta %s = %s.', $ditl_meta_empreinte, $ditl_md5 ) );
		}
	}

	$ditl_langue_actuelle = pll_get_post_language( $ditl_id, 'slug' );
	if ( $ditl_langue_actuelle !== $ditl_langue ) {
		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] Langue Polylang : %s -> %s.', $ditl_langue_actuelle ? $ditl_langue_actuelle : '(aucune)', $ditl_langue ) );
		} else {
			pll_set_post_language( $ditl_id, $ditl_langue );
			$ditl_ecritures++;
			WP_CLI::log( sprintf( '  Langue Polylang : %s -> %s.', $ditl_langue_actuelle ? $ditl_langue_actuelle : '(aucune)', $ditl_langue ) );
		}
	} else {
		WP_CLI::log( sprintf( '  Langue Polylang : %s, conforme.', $ditl_langue ) );
	}
}

// ---------------------------------------------------------------------------
// Traductions, option du theme, controle des menus.
// ---------------------------------------------------------------------------

WP_CLI::log( '' );

if ( in_array( 0, $ditl_ids, true ) ) {
	// Simulation sur un environnement ou une page reste a creer.
	WP_CLI::log( '[dry-run] Traductions Polylang et option seraient posees apres creation des pages.' );
	WP_CLI::success( 'Simulation terminee : aucune ecriture.' );
	return;
}

// Seuls les liens fr et en relevent du script : une traduction rattachee a
// la main dans une autre langue (es, pt, de) est conservee.
$ditl_attendu = $ditl_ids;
ksort( $ditl_attendu );
$ditl_traductions = array_map( 'intval', (array) pll_get_post_translations( $ditl_ids['fr'] ) );
$ditl_actuel      = array_intersect_key( $ditl_traductions, $ditl_ids );
ksort( $ditl_actuel );
$ditl_groupe = array_merge( $ditl_traductions, $ditl_ids );

if ( $ditl_actuel === $ditl_attendu ) {
	WP_CLI::log( sprintf( 'Traductions Polylang : %s, conformes.', wp_json_encode( $ditl_ids ) ) );
} elseif ( $ditl_dry_run ) {
	WP_CLI::log( sprintf( '[dry-run] Traductions Polylang seraient liees : %s (actuel %s).', wp_json_encode( $ditl_groupe ), wp_json_encode( $ditl_traductions ) ) );
} else {
	pll_save_post_translations( $ditl_groupe );
	$ditl_ecritures++;
	WP_CLI::log( sprintf( 'Traductions Polylang liees : %s.', wp_json_encode( $ditl_groupe ) ) );
}

if ( $ditl_option === $ditl_ids ) {
	WP_CLI::log( sprintf( 'Option %s : %s, conforme.', $ditl_option_pages, wp_json_encode( $ditl_ids ) ) );
} elseif ( $ditl_dry_run ) {
	WP_CLI::log( sprintf( '[dry-run] Option %s serait posee a %s.', $ditl_option_pages, wp_json_encode( $ditl_ids ) ) );
} else {
	update_option( $ditl_option_pages, $ditl_ids, true );
	$ditl_ecritures++;
	WP_CLI::log( sprintf( 'Option %s = %s.', $ditl_option_pages, wp_json_encode( $ditl_ids ) ) );
}

$ditl_menus = ditl_declaration_menus_visant( array_values( $ditl_ids ) );
if ( empty( $ditl_menus ) ) {
	WP_CLI::log( 'Menus : aucun element de menu ne vise les pages de declaration (en-tete inchange).' );
} else {
	foreach ( $ditl_menus as $ditl_m ) {
		WP_CLI::warning( sprintf( 'Element de menu %d vise la page %d : a retirer si l\'en-tete ne doit pas changer.', $ditl_m['item'], $ditl_m['menu_object_id'] ) );
	}
}

foreach ( $ditl_ids as $ditl_langue => $ditl_id ) {
	WP_CLI::log( sprintf( 'URL %s : %s', $ditl_langue, get_permalink( $ditl_id ) ) );
}

if ( $ditl_dry_run ) {
	WP_CLI::success( 'Simulation terminee : aucune ecriture.' );
	return;
}

WP_CLI::success( sprintf( 'Termine : %d ecriture(s). Purger le cache de page (wp-content/cache/all) pour afficher la mention du pied de page.', $ditl_ecritures ) );
