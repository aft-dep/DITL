<?php
/**
 * Integration des modules e-learning (phase 5 du devis, option iframe).
 *
 * Les modules sont des exports HTML autonomes produits par l'AFT avec
 * eXeLearning (pas de Moodle, confirme par le client le 23/07/2026). Chaque
 * export est depose tel quel, sans aucune modification, dans un sous-dossier
 * du dossier des modules :
 *
 *   wp-content/elearning/<dossier>/index.html
 *
 * puis affiche dans une iframe par le gabarit "E-learning"
 * (page-templates/elearning.php), sous le titre et le contenu de la page.
 * Le choix des modules de chaque page se fait dans la metabox du gabarit
 * (inc/metaboxes/elearning.php) : chaque page de langue choisit l'export de
 * sa langue.
 *
 * Choix techniques :
 * - Meme origine que le site : l'iframe ajuste sa hauteur a celle du module
 *   (assets/js/ditl-elearning.js), sans double barre de defilement et sans
 *   rien modifier dans les exports (un nouvel export remplace l'ancien tel
 *   quel). Sans JavaScript, l'iframe garde une hauteur fixe et defile.
 * - Dossier hors mediatheque (pas de traitement WebP ni de vignettes sur les
 *   images des modules), hors depot git comme uploads/ (contenu du client,
 *   depose par environnement). Son .htaccess (aucun script serveur execute,
 *   pages des modules non indexees hors de la page qui les integre, cache
 *   court des ressources) est pose par cli/configurer-elearning.php.
 * - Nom de dossier en minuscules, chiffres, tirets et soulignes uniquement
 *   (ex. module-2-fr) : aucun chemin ne peut sortir du dossier des modules,
 *   et l'URL reste identique entre macOS (insensible a la casse) et le
 *   serveur Linux.
 *
 * Les constantes DITL_ELEARNING_DIR et DITL_ELEARNING_URL peuvent etre
 * definies dans wp-config.php pour deplacer le dossier des modules.
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DITL_ELEARNING_DIR' ) ) {
	define( 'DITL_ELEARNING_DIR', WP_CONTENT_DIR . '/elearning' );
}

if ( ! defined( 'DITL_ELEARNING_URL' ) ) {
	define( 'DITL_ELEARNING_URL', content_url( 'elearning' ) );
}

/**
 * Indique si un nom de dossier de module est syntaxiquement valide.
 *
 * Minuscules, chiffres, tirets et soulignes, 64 caracteres au plus, premier
 * caractere alphanumerique : ni separateur de chemin, ni "..", ni caractere
 * a encoder dans une URL.
 *
 * @param mixed $dossier Nom candidat.
 * @return bool True si le nom est valide.
 */
function ditl_elearning_dossier_valide( $dossier ) {
	return is_string( $dossier ) && 1 === preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $dossier );
}

/**
 * Chemin du fichier d'entree d'un module, s'il existe.
 *
 * Le chemin resolu doit rester sous le dossier des modules (garde contre un
 * lien symbolique qui pointerait ailleurs sur le serveur).
 *
 * @param mixed $dossier Nom du dossier du module.
 * @return string Chemin absolu de index.html, ou chaine vide si le module est
 *                absent ou invalide.
 */
function ditl_elearning_fichier_module( $dossier ) {
	if ( ! ditl_elearning_dossier_valide( $dossier ) ) {
		return '';
	}

	$racine  = realpath( DITL_ELEARNING_DIR );
	$fichier = realpath( DITL_ELEARNING_DIR . '/' . $dossier . '/index.html' );

	if ( false === $racine || false === $fichier || ! is_file( $fichier ) ) {
		return '';
	}

	if ( 0 !== strpos( $fichier, $racine . DIRECTORY_SEPARATOR ) ) {
		return '';
	}

	return $fichier;
}

/**
 * URL publique du fichier d'entree d'un module.
 *
 * @param string $dossier Nom de dossier valide.
 * @return string URL de index.html (a echapper via esc_url).
 */
function ditl_elearning_url_module( $dossier ) {
	return trailingslashit( DITL_ELEARNING_URL ) . rawurlencode( $dossier ) . '/index.html';
}

/**
 * Lit le titre et la langue declares par un export.
 *
 * Seul le debut du fichier est lu (le <head> d'un export eXeLearning tient
 * dans les premiers kilo-octets). Resultat memorise pour la requete.
 *
 * @param string $dossier Nom de dossier valide.
 * @return array { titre => string, langue => string } (chaines vides si
 *               l'information est absente ou le module introuvable).
 */
function ditl_elearning_infos_module( $dossier ) {
	static $cache = array();

	if ( isset( $cache[ $dossier ] ) ) {
		return $cache[ $dossier ];
	}

	$infos   = array(
		'titre'  => '',
		'langue' => '',
	);
	$fichier = ditl_elearning_fichier_module( $dossier );
	$debut   = '' !== $fichier && is_readable( $fichier ) ? file_get_contents( $fichier, false, null, 0, 16384 ) : false;

	if ( is_string( $debut ) ) {
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $debut, $titre ) ) {
			$infos['titre'] = mb_substr( trim( wp_strip_all_tags( html_entity_decode( $titre[1], ENT_QUOTES, 'UTF-8' ) ) ), 0, 150 );
		}

		if ( preg_match( '/<html\b[^>]*\slang=["\']([a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})*)["\']/i', $debut, $langue ) ) {
			$infos['langue'] = strtolower( $langue[1] );
		}
	}

	$cache[ $dossier ] = $infos;

	return $infos;
}

/**
 * Liste les modules deposes dans le dossier des modules.
 *
 * Un sous-dossier n'est retenu que si son nom est valide et qu'il contient
 * un index.html. Les dossiers au nom non conforme sont signales a part
 * (pour l'aide de la metabox) plutot qu'ignores en silence.
 *
 * @return array { modules => [ dossier => { titre, langue } ], ignores => string[] }
 */
function ditl_elearning_modules_disponibles() {
	$resultat = array(
		'modules' => array(),
		'ignores' => array(),
	);

	if ( ! is_dir( DITL_ELEARNING_DIR ) ) {
		return $resultat;
	}

	$entrees = scandir( DITL_ELEARNING_DIR );

	if ( false === $entrees ) {
		return $resultat;
	}

	foreach ( $entrees as $entree ) {
		if ( '.' === $entree[0] || ! is_dir( DITL_ELEARNING_DIR . '/' . $entree ) ) {
			continue;
		}

		if ( ! ditl_elearning_dossier_valide( $entree ) ) {
			$resultat['ignores'][] = $entree;
			continue;
		}

		if ( '' !== ditl_elearning_fichier_module( $entree ) ) {
			$resultat['modules'][ $entree ] = ditl_elearning_infos_module( $entree );
		}
	}

	return $resultat;
}

/**
 * Libelle d'interface du gabarit dans la langue de la page.
 *
 * Le theme n'a pas encore de fichiers de traduction (revus en phase 2 avec
 * WPML, voir ditl_page_est_francaise) : les libelles visibles du gabarit
 * sont fournis ici dans les 5 langues du site, l'anglais servant de repli.
 *
 * @param string $cle Cle du libelle.
 * @return string Libelle (a echapper au rendu).
 */
function ditl_elearning_libelle( $cle ) {
	$libelles = array(
		'pleine_page' => array(
			'fr' => 'Afficher le module en pleine page',
			'en' => 'Open the module in full-page view',
			'de' => 'Modul ganzseitig anzeigen',
			'es' => 'Ver el módulo a página completa',
			'pt' => 'Ver o módulo em página inteira',
		),
	);

	if ( ! isset( $libelles[ $cle ] ) ) {
		return '';
	}

	$langue = substr( (string) get_locale(), 0, 2 );

	return isset( $libelles[ $cle ][ $langue ] ) ? $libelles[ $cle ][ $langue ] : $libelles[ $cle ]['en'];
}

/**
 * Modules de la page courante prets a etre affiches.
 *
 * Les modules dont le dossier est absent de l'environnement (export pas
 * encore depose, par exemple) sont ecartes : rien n'est affiche plutot
 * qu'une iframe vers une page introuvable.
 *
 * @param int $post_id ID de la page.
 * @return array Liste de { dossier, titre, description, url, titre_cadre, langue }.
 */
function ditl_elearning_modules_page( $post_id ) {
	$modules = array();

	foreach ( ditl_get_meta_json( $post_id, '_ditl_elearning_modules' ) as $module ) {
		if ( ! is_array( $module ) ) {
			continue;
		}

		$dossier = isset( $module['dossier'] ) ? $module['dossier'] : '';

		if ( '' === ditl_elearning_fichier_module( $dossier ) ) {
			continue;
		}

		$infos = ditl_elearning_infos_module( $dossier );
		$titre = isset( $module['titre'] ) ? (string) $module['titre'] : '';

		// Nom accessible de l'iframe (RGAA 2.1 / 2.2) : titre saisi, sinon
		// titre declare par l'export, sinon nom du dossier.
		$titre_cadre = '' !== $titre ? $titre : ( '' !== $infos['titre'] ? $infos['titre'] : $dossier );

		$modules[] = array(
			'dossier'     => $dossier,
			'titre'       => $titre,
			'description' => isset( $module['description'] ) ? (string) $module['description'] : '',
			'url'         => ditl_elearning_url_module( $dossier ),
			'titre_cadre' => $titre_cadre,
			'langue'      => $infos['langue'],
		);
	}

	return $modules;
}

/**
 * Affiche les modules sous le contenu de la page (gabarit "E-learning").
 *
 * Accroche sur astra_entry_content_after : le gabarit reprend le modele de
 * page d'Astra (titre et contenu inchanges) et les modules s'ajoutent a la
 * fin de .entry-content. Une page sans module affichable rend exactement
 * comme avant.
 */
function ditl_elearning_afficher_modules() {
	// Page protegee par mot de passe : rien tant que le mot de passe n'est pas
	// saisi (Astra declenche ce crochet meme sous le formulaire).
	if ( ! is_page_template( DITL_TPL_ELEARNING ) || ! in_the_loop() || post_password_required() ) {
		return;
	}

	$ditl_modules = ditl_elearning_modules_page( get_the_ID() );

	if ( array() === $ditl_modules ) {
		return;
	}

	$ditl_rang        = 0;
	$ditl_langue_page = strtolower( substr( (string) get_locale(), 0, 2 ) );

	foreach ( $ditl_modules as $ditl_module ) {
		++$ditl_rang;

		$ditl_id_titre = 'ditl-elearning-titre-' . $ditl_rang;
		$ditl_a_titre  = '' !== $ditl_module['titre'];

		// Un module titre forme une region nommee par son titre H2 ; sans
		// titre, un simple conteneur (l'iframe porte deja le nom du module).
		$ditl_balise = $ditl_a_titre ? 'section' : 'div';

		// Langue du module annoncee sur le lien quand elle differe de celle
		// de la page (export pas encore traduit, par exemple).
		$ditl_hreflang = '' !== $ditl_module['langue'] && 0 !== strpos( $ditl_module['langue'], $ditl_langue_page ) ? $ditl_module['langue'] : '';

		// Sans titre saisi, le nom de l'iframe vient de l'export : il est
		// dans la langue du module (RGAA 8.7), signalee le cas echeant.
		$ditl_lang_nom = ! $ditl_a_titre && '' !== $ditl_hreflang && $ditl_module['titre_cadre'] !== $ditl_module['dossier'] ? $ditl_hreflang : '';
		?>
		<<?php echo $ditl_balise; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- valeur fixe (section ou div). ?> class="ditl-elearning"<?php echo $ditl_a_titre ? ' aria-labelledby="' . esc_attr( $ditl_id_titre ) . '"' : ''; ?>>
			<?php if ( $ditl_a_titre ) { ?>
			<h2 class="ditl-elearning__titre" id="<?php echo esc_attr( $ditl_id_titre ); ?>"><?php echo esc_html( $ditl_module['titre'] ); ?></h2>
			<?php } ?>
			<?php if ( '' !== trim( wp_strip_all_tags( $ditl_module['description'] ) ) ) { ?>
			<div class="ditl-elearning__description"><?php echo wp_kses_post( ditl_format_rich_text( $ditl_module['description'] ) ); ?></div>
			<?php } ?>
			<?php // Lien avant l'iframe : un module fait souvent plusieurs milliers de pixels de haut. ?>
			<p class="ditl-elearning__pleine-page">
				<a href="<?php echo esc_url( $ditl_module['url'] ); ?>"<?php echo '' !== $ditl_hreflang ? ' hreflang="' . esc_attr( $ditl_hreflang ) . '"' : ''; ?>><?php echo esc_html( ditl_elearning_libelle( 'pleine_page' ) ); ?><?php if ( ! $ditl_a_titre ) { ?><span class="screen-reader-text"> (<?php echo '' !== $ditl_lang_nom ? '<span lang="' . esc_attr( $ditl_lang_nom ) . '">' . esc_html( $ditl_module['titre_cadre'] ) . '</span>' : esc_html( $ditl_module['titre_cadre'] ); ?>)</span><?php } ?></a>
			</p>
			<?php // Plein ecran autorise : certaines activites d'eXeLearning (ex. tri) proposent un bouton plein ecran. ?>
			<iframe class="ditl-elearning__cadre" src="<?php echo esc_url( $ditl_module['url'] ); ?>" title="<?php echo esc_attr( $ditl_module['titre_cadre'] ); ?>"<?php echo '' !== $ditl_lang_nom ? ' lang="' . esc_attr( $ditl_lang_nom ) . '"' : ''; ?> loading="lazy" allowfullscreen></iframe>
		</<?php echo $ditl_balise; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- valeur fixe (section ou div). ?>>
		<?php
	}
}
add_action( 'astra_entry_content_after', 'ditl_elearning_afficher_modules' );

/**
 * Charge les assets du gabarit "E-learning" quand la page affiche un module.
 */
function ditl_elearning_enqueue_assets() {
	if ( ! is_page_template( DITL_TPL_ELEARNING ) || post_password_required( get_queried_object_id() ) || array() === ditl_elearning_modules_page( get_queried_object_id() ) ) {
		return;
	}

	wp_enqueue_style(
		'ditl-gabarit-elearning',
		get_stylesheet_directory_uri() . '/assets/css/gabarit-elearning.css',
		array( 'ditl-style' ),
		DITL_THEME_VERSION
	);

	// Ajustement de la hauteur des iframes, sans dependance, en pied de page.
	wp_enqueue_script(
		'ditl-elearning',
		get_stylesheet_directory_uri() . '/assets/js/ditl-elearning.js',
		array(),
		DITL_THEME_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'ditl_elearning_enqueue_assets' );
