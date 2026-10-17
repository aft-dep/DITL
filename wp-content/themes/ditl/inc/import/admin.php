<?php
/**
 * Import des fiches : ecran d'administration "Boite a outils > Importer".
 *
 * Parcours :
 * 1. formulaire (type, CSV, zip d'images facultatif, option ecraser) ;
 * 2. ANALYSE (admin-post, nonce) : remplace les analyses non lancees de
 *    l'utilisateur, cree un dossier de travail protege, extrait le zip,
 *    lance une SIMULATION complete (aucune ecriture) et redirige vers le
 *    rapport ;
 * 3. rapport : compteurs, messages de fichier et de groupe, lignes en
 *    erreur ou avertissement d'abord, toutes les lignes dans un <details> ;
 * 4. "Lancer l'import" : execution par lots en admin-ajax (nonce lie au
 *    travail, capacite, travail lie a l'utilisateur ; position tenue cote
 *    serveur dans travail.json ; verrou du travail contre deux executions
 *    simultanees et verrou global contre deux imports simultanes ; chaque
 *    lot relit et revalide le fichier puis traite des lignes pendant un
 *    budget de temps) ; barre de progression et zone aria-live ; rapport
 *    final puis suppression du dossier de travail ;
 * 5. "Abandonner" : suppression du dossier de travail, verrou du travail
 *    tenu (refus explicite si un lot est en cours).
 * Telechargements : modele de fichier de fiches, export des referentiels
 * actuels (cellules neutralisees contre l'injection de formules).
 * Nettoyage des travaux de plus de 24 h : a l'ouverture de l'ecran et une
 * fois par jour sur admin_init (transient).
 *
 * Capacite requise : manage_options (administrateurs).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL de l'ecran d'import.
 *
 * @param array $args Parametres de requete a ajouter.
 * @return string URL.
 */
function ditl_import_admin_url( $args = array() ) {
	return add_query_arg(
		array_merge(
			array(
				'post_type' => ditl_import_type_fiche(),
				'page'      => 'ditl-import',
			),
			$args
		),
		admin_url( 'edit.php' )
	);
}

/**
 * Redirige vers l'ecran d'import avec un message et termine la requete.
 *
 * @param string $avis  Code du message (voir ditl_import_admin_avis()).
 * @param string $jeton Jeton de travail eventuel.
 */
function ditl_import_admin_rediriger( $avis, $jeton = '' ) {
	$args = array();

	if ( '' !== $avis ) {
		$args['ditl_avis'] = $avis;
	}

	if ( ditl_import_jeton_valide( $jeton ) ) {
		$args['travail'] = $jeton;
	}

	wp_safe_redirect( ditl_import_admin_url( $args ) );
	exit;
}

/**
 * Messages d'etat affiches en tete d'ecran (codes fixes, jamais de texte
 * venu de l'URL).
 *
 * @return array Tableau [code => { type, texte }].
 */
function ditl_import_admin_avis() {
	return array(
		'abandon'      => array( 'success', __( 'Import abandonné : les fichiers envoyés ont été supprimés.', 'ditl' ) ),
		'lot_en_cours' => array( 'warning', __( 'Abandon impossible pour l\'instant : un lot de cet import est en cours d\'exécution. Attendre la fin du lot puis réessayer.', 'ditl' ) ),
		'taille'       => array( 'error', __( 'Envoi refusé : fichier trop volumineux pour le serveur. Pour les images, utiliser la Médiathèque puis envoyer le CSV seul.', 'ditl' ) ),
		'csv'          => array( 'error', __( 'Aucun fichier CSV reçu.', 'ditl' ) ),
		'extension'    => array( 'error', __( 'Type de fichier refusé : un fichier .csv est attendu (et une archive .zip pour les images).', 'ditl' ) ),
		'type'         => array( 'error', __( 'Choisir le type de fichier (référentiels ou fiches).', 'ditl' ) ),
		'envoi'        => array( 'error', __( 'Envoi du fichier impossible. Réessayer.', 'ditl' ) ),
		'dossier'      => array( 'error', __( 'Dossier de travail impossible à créer (droits du dossier des médias).', 'ditl' ) ),
		'introuvable'  => array( 'warning', __( 'Cet import n\'existe plus (terminé, abandonné ou de plus de 24 heures).', 'ditl' ) ),
	);
}

/**
 * Declare l'ecran dans le menu de la boite a outils.
 */
function ditl_import_admin_menu() {
	if ( ! post_type_exists( ditl_import_type_fiche() ) ) {
		return;
	}

	$hook = add_submenu_page(
		'edit.php?post_type=' . ditl_import_type_fiche(),
		__( 'Importer des fiches outils', 'ditl' ),
		__( 'Importer', 'ditl' ),
		'manage_options',
		'ditl-import',
		'ditl_import_admin_page'
	);

	if ( $hook ) {
		$GLOBALS['ditl_import_admin_hook'] = $hook;
		add_action( 'load-' . $hook, 'ditl_import_admin_chargement' );
	}
}
add_action( 'admin_menu', 'ditl_import_admin_menu' );

/**
 * Au chargement de l'ecran : nettoyage des travaux abandonnes (24 h).
 */
function ditl_import_admin_chargement() {
	ditl_import_nettoyer_travaux();
}

/**
 * Nettoyage quotidien des travaux abandonnes, meme si l'ecran d'import
 * n'est plus ouvert (une fois par jour au plus, sur une page d'admin).
 */
function ditl_import_admin_nettoyage_quotidien() {
	if ( ! current_user_can( 'manage_options' ) || false !== get_transient( 'ditl_import_nettoyage' ) ) {
		return;
	}

	set_transient( 'ditl_import_nettoyage', 1, DAY_IN_SECONDS );
	ditl_import_nettoyer_travaux();
}
add_action( 'admin_init', 'ditl_import_admin_nettoyage_quotidien' );

/**
 * Charge le CSS et le JS de l'ecran (uniquement sur cet ecran).
 *
 * @param string $hook Ecran courant.
 */
function ditl_import_admin_assets( $hook ) {
	if ( empty( $GLOBALS['ditl_import_admin_hook'] ) || $hook !== $GLOBALS['ditl_import_admin_hook'] ) {
		return;
	}

	$version = defined( 'DITL_THEME_VERSION' ) ? DITL_THEME_VERSION : DITL_IMPORT_VERSION;

	wp_enqueue_style(
		'ditl-admin-import',
		get_stylesheet_directory_uri() . '/assets/admin/import.css',
		array(),
		$version
	);

	wp_enqueue_script(
		'ditl-admin-import',
		get_stylesheet_directory_uri() . '/assets/admin/import.js',
		array(),
		$version,
		true
	);

	wp_localize_script(
		'ditl-admin-import',
		'ditlImport',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'urlEcran' => ditl_import_admin_url(),
			'i18n'     => array(
				/* translators: 1: lignes traitees, 2: total. */
				'progression'     => __( 'Lignes traitées : %1$s sur %2$s.', 'ditl' ),
				'demarrage'       => __( 'Import en cours, ne pas fermer cette page.', 'ditl' ),
				'occupe'          => __( 'Un lot est déjà en cours d\'exécution, nouvelle tentative dans quelques secondes.', 'ditl' ),
				'erreur'          => __( 'L\'import s\'est interrompu. Les lignes déjà traitées sont enregistrées : relancer pour reprendre.', 'ditl' ),
				'erreurReseau'    => __( 'Erreur de communication avec le serveur. Relancer pour reprendre (les lignes déjà traitées ne sont pas refaites).', 'ditl' ),
				'session'         => __( 'Session expirée : reconnectez-vous puis relancez l\'import (il reprendra là où il s\'est arrêté).', 'ditl' ),
				'reprendre'       => __( 'Reprendre l\'import', 'ditl' ),
				'quitter'         => __( 'L\'import est en cours : quitter la page l\'interrompt.', 'ditl' ),
				'titreFinal'      => __( 'Rapport final de l\'import', 'ditl' ),
				'termine'         => __( 'Import terminé. Le dossier de travail a été supprimé.', 'ditl' ),
				'nouvelImport'    => __( 'Nouvel import', 'ditl' ),
				'compteurs'       => array(
					'lignes'         => __( 'Lignes', 'ditl' ),
					'creation'       => __( 'Créées', 'ditl' ),
					'mise_a_jour'    => __( 'Mises à jour', 'ditl' ),
					'inchangee'      => __( 'Inchangées', 'ditl' ),
					'ignoree'        => __( 'Ignorées', 'ditl' ),
					'rejetee'        => __( 'Rejetées (erreur)', 'ditl' ),
					'echec'          => __( 'Échecs', 'ditl' ),
					'avertissements' => __( 'Lignes avec avertissement', 'ditl' ),
				),
				'messagesFichier' => __( 'Messages sur le fichier', 'ditl' ),
				'messagesGroupes' => __( 'Contrôles par fiche (group_id)', 'ditl' ),
				'groupe'          => __( 'Groupe', 'ditl' ),
				'caption'         => __( 'Lignes avec erreur, avertissement ou information', 'ditl' ),
				'aucuneLigne'     => __( 'Aucune ligne avec erreur ou avertissement.', 'ditl' ),
				'colonnes'        => array(
					'ligne'    => __( 'Ligne', 'ditl' ),
					'id'       => __( 'Id ou code', 'ditl' ),
					'langue'   => __( 'Langue', 'ditl' ),
					'titre'    => __( 'Titre ou libellé', 'ditl' ),
					'action'   => __( 'Résultat', 'ditl' ),
					'messages' => __( 'Messages', 'ditl' ),
				),
				'niveaux'         => array(
					'erreur'        => __( 'Erreur', 'ditl' ),
					'avertissement' => __( 'Avertissement', 'ditl' ),
					'info'          => __( 'Information', 'ditl' ),
				),
				'actions'         => array(
					'creation'    => __( 'créée', 'ditl' ),
					'mise_a_jour' => __( 'mise à jour', 'ditl' ),
					'inchangee'   => __( 'inchangée', 'ditl' ),
					'ignoree'     => __( 'ignorée', 'ditl' ),
					'rejetee'     => __( 'rejetée (erreur)', 'ditl' ),
					'echec'       => __( 'échec', 'ditl' ),
				),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'ditl_import_admin_assets' );

/**
 * Libelles des niveaux de message.
 *
 * @return array Tableau [niveau => libelle].
 */
function ditl_import_admin_niveaux() {
	return array(
		'fatal'         => __( 'Erreur bloquante', 'ditl' ),
		'erreur'        => __( 'Erreur', 'ditl' ),
		'avertissement' => __( 'Avertissement', 'ditl' ),
		'info'          => __( 'Information', 'ditl' ),
	);
}

/**
 * Affiche une liste de messages { niveau, texte }.
 *
 * @param array $messages Messages.
 */
function ditl_import_admin_messages( $messages ) {
	$niveaux = ditl_import_admin_niveaux();

	echo '<ul class="ditl-import-messages">';

	foreach ( (array) $messages as $message ) {
		$niveau = isset( $message['niveau'] ) && isset( $niveaux[ $message['niveau'] ] ) ? $message['niveau'] : 'info';

		printf(
			'<li class="ditl-import-niveau-%1$s"><span class="ditl-import-niveau">%2$s :</span> %3$s</li>',
			esc_attr( $niveau ),
			esc_html( $niveaux[ $niveau ] ),
			esc_html( isset( $message['texte'] ) ? (string) $message['texte'] : '' )
		);
	}

	echo '</ul>';
}

/**
 * Affiche un tableau de resultats de lignes.
 *
 * @param array  $lignes  Resultats de lignes.
 * @param string $type    fiches ou referentiels.
 * @param string $caption Legende du tableau.
 * @param bool   $prevue  True pour un rapport de simulation.
 */
function ditl_import_admin_tableau( $lignes, $type, $caption, $prevue ) {
	$fiches = 'fiches' === $type;
	?>
	<table class="widefat striped ditl-import-tableau">
		<caption><?php echo esc_html( $caption ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Ligne', 'ditl' ); ?></th>
				<th scope="col"><?php echo $fiches ? esc_html__( 'Id', 'ditl' ) : esc_html__( 'Liste', 'ditl' ); ?></th>
				<th scope="col"><?php echo $fiches ? esc_html__( 'Groupe', 'ditl' ) : esc_html__( 'Code', 'ditl' ); ?></th>
				<?php if ( $fiches ) : ?>
					<th scope="col"><?php esc_html_e( 'Langue', 'ditl' ); ?></th>
				<?php endif; ?>
				<th scope="col"><?php echo $fiches ? esc_html__( 'Titre', 'ditl' ) : esc_html__( 'Libellé', 'ditl' ); ?></th>
				<th scope="col"><?php echo $prevue ? esc_html__( 'Action prévue', 'ditl' ) : esc_html__( 'Résultat', 'ditl' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Messages', 'ditl' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $lignes as $ligne ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( (string) $ligne['ligne'] ); ?></th>
					<td><?php echo esc_html( $fiches ? $ligne['id'] : $ligne['groupe'] ); ?></td>
					<td><?php echo esc_html( $fiches ? $ligne['groupe'] : $ligne['id'] ); ?></td>
					<?php if ( $fiches ) : ?>
						<td><?php echo esc_html( $ligne['lang'] ); ?></td>
					<?php endif; ?>
					<td><?php echo esc_html( $ligne['titre'] ); ?></td>
					<td><?php echo esc_html( ditl_import_libelle_action( $ligne['action'], $prevue ) ); ?></td>
					<td>
						<?php
						if ( array() !== $ligne['messages'] ) {
							ditl_import_admin_messages( $ligne['messages'] );
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Ecran d'import.
 */
function ditl_import_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'ditl' ), 403 );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- lecture seule (affichage).
	$jeton = isset( $_GET['travail'] ) ? sanitize_key( wp_unslash( $_GET['travail'] ) ) : '';
	$avis  = isset( $_GET['ditl_avis'] ) ? sanitize_key( wp_unslash( $_GET['ditl_avis'] ) ) : '';
	// phpcs:enable

	$avis_connus = ditl_import_admin_avis();
	$travail     = ditl_import_jeton_valide( $jeton ) ? ditl_import_lire_travail( $jeton ) : null;

	if ( null !== $travail && (int) $travail['utilisateur'] !== get_current_user_id() ) {
		$travail = null;
	}

	if ( '' !== $jeton && null === $travail ) {
		$avis = 'introuvable';
	}
	?>
	<div class="wrap ditl-import">
		<h1><?php esc_html_e( 'Importer des fiches outils', 'ditl' ); ?></h1>

		<?php if ( isset( $avis_connus[ $avis ] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $avis_connus[ $avis ][0] ); ?>"><p><?php echo esc_html( $avis_connus[ $avis ][1] ); ?></p></div>
		<?php endif; ?>

		<?php
		if ( null !== $travail ) {
			ditl_import_admin_rapport( $jeton, $travail );
		} else {
			ditl_import_admin_accueil();
		}
		?>
	</div>
	<?php
}

/**
 * Accueil : aide, telechargements, formulaire, imports en attente.
 */
function ditl_import_admin_accueil() {
	$regles   = ditl_import_regles();
	$max      = wp_max_upload_size();
	$modele   = wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'ditl_import_telecharger',
				'quoi'   => 'modele',
			),
			admin_url( 'admin-post.php' )
		),
		'ditl_import_telecharger_modele'
	);
	$export   = wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'ditl_import_telecharger',
				'quoi'   => 'referentiels',
			),
			admin_url( 'admin-post.php' )
		),
		'ditl_import_telecharger_referentiels'
	);
	$attentes = ditl_import_admin_travaux_utilisateur();
	?>
	<?php if ( array() !== $attentes ) : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'Imports analysés non terminés :', 'ditl' ); ?></p>
			<ul>
				<?php foreach ( $attentes as $attente ) : ?>
					<li>
						<a href="<?php echo esc_url( ditl_import_admin_url( array( 'travail' => $attente['jeton'] ) ) ); ?>">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: nom du fichier, 2: type, 3: date. */
									__( '%1$s (%2$s, %3$s)', 'ditl' ),
									$attente['fichier'],
									$attente['type'],
									wp_date( 'd/m/Y H:i', (int) $attente['date'] )
								)
							);
							?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Mode d\'emploi', 'ditl' ); ?></h2>
	<ol class="ditl-import-aide">
		<li><?php esc_html_e( 'Importer d\'abord les RÉFÉRENTIELS (listes de choix : types, sous-types, difficultés, empêchements, métiers, tâches, environnements, avec leurs libellés dans chaque langue). Les fiches y font référence par leurs codes.', 'ditl' ); ?></li>
		<li><?php esc_html_e( 'Préparer les IMAGES : soit une archive .zip envoyée avec le fichier de fiches, soit (archive trop lourde) un dépôt préalable par la Médiathèque, sous le nom de fichier indiqué dans la colonne image.', 'ditl' ); ?></li>
		<li><?php esc_html_e( 'Importer enfin les FICHES. Chaque envoi commence par une analyse complète sans aucune écriture : l\'import ne se lance qu\'après lecture du rapport.', 'ditl' ); ?></li>
	</ol>
	<p><?php esc_html_e( 'Format : CSV UTF-8, séparateur point-virgule (Excel : « CSV UTF-8 »). Une ligne = une fiche dans une langue ; group_id identique pour les traductions d\'une même fiche ; id unique sur l\'ensemble des fichiers, à ne jamais renuméroter. Plusieurs valeurs dans une cellule : séparées par |. Textes longs : un retour à la ligne = un paragraphe, une ligne commençant par « - » = une puce, **texte** = gras. Fabricants et sources sont communs à toutes les langues d\'une fiche.', 'ditl' ); ?></p>
	<p><?php esc_html_e( 'Un nouvel import du même fichier est sans risque : les lignes identiques ne sont pas réécrites, les fiches modifiées dans l\'administration depuis le dernier import sont ignorées (sauf option ci-dessous), aucune fiche n\'est jamais supprimée. Les nouvelles fiches sont créées en brouillon.', 'ditl' ); ?></p>
	<p>
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: taille maximale d'un envoi, 2: taille maximale d'une image. */
				__( 'Taille maximale d\'un envoi sur ce serveur : %1$s (CSV et archive ensemble). Image : %2$s maximum.', 'ditl' ),
				size_format( $max ),
				size_format( $regles['image_taille_max'] )
			)
		);
		?>
	</p>
	<p class="ditl-import-telechargements">
		<a class="button" href="<?php echo esc_url( $modele ); ?>"><?php esc_html_e( 'Télécharger le modèle de fichier de fiches (CSV)', 'ditl' ); ?></a>
		<a class="button" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Exporter les référentiels actuels (CSV)', 'ditl' ); ?></a>
	</p>

	<h2><?php esc_html_e( 'Analyser un fichier', 'ditl' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ditl-import-formulaire">
		<input type="hidden" name="action" value="ditl_import_analyser">
		<?php wp_nonce_field( 'ditl_import_analyser', 'ditl_import_nonce' ); ?>

		<fieldset>
			<legend><?php esc_html_e( 'Type de fichier', 'ditl' ); ?></legend>
			<p>
				<input type="radio" id="ditl-import-type-referentiels" name="ditl_import_type" value="referentiels" required>
				<label for="ditl-import-type-referentiels"><?php esc_html_e( 'Référentiels (listes de choix)', 'ditl' ); ?></label>
			</p>
			<p>
				<input type="radio" id="ditl-import-type-fiches" name="ditl_import_type" value="fiches">
				<label for="ditl-import-type-fiches"><?php esc_html_e( 'Fiches outils', 'ditl' ); ?></label>
			</p>
		</fieldset>

		<p>
			<label for="ditl-import-csv"><?php esc_html_e( 'Fichier CSV (obligatoire)', 'ditl' ); ?></label><br>
			<input type="file" id="ditl-import-csv" name="ditl_import_csv" accept=".csv,text/csv" required>
		</p>

		<p>
			<label for="ditl-import-zip"><?php esc_html_e( 'Images : archive .zip (facultatif, fiches seulement)', 'ditl' ); ?></label><br>
			<input type="file" id="ditl-import-zip" name="ditl_import_zip" accept=".zip,application/zip" aria-describedby="ditl-import-zip-aide">
			<span id="ditl-import-zip-aide" class="description"><?php esc_html_e( 'Images jpg, png, webp ou gif, nommées comme dans la colonne image.', 'ditl' ); ?></span>
		</p>

		<p>
			<input type="checkbox" id="ditl-import-ecraser" name="ditl_import_ecraser" value="1">
			<label for="ditl-import-ecraser"><?php esc_html_e( 'Écraser aussi les fiches modifiées dans l\'administration depuis le dernier import', 'ditl' ); ?></label>
		</p>

		<?php submit_button( __( 'Analyser le fichier (aucune écriture)', 'ditl' ), 'primary', 'submit', false ); ?>
	</form>
	<?php
}

/**
 * Imports en attente de l'utilisateur courant.
 *
 * @return array Liste de { jeton, fichier, type, date }.
 */
function ditl_import_admin_travaux_utilisateur() {
	$racine  = ditl_import_racine_travaux();
	$travaux = array();
	$entrees = is_dir( $racine ) ? scandir( $racine ) : array();

	foreach ( is_array( $entrees ) ? $entrees : array() as $entree ) {
		if ( ! ditl_import_jeton_valide( $entree ) ) {
			continue;
		}

		$travail = ditl_import_lire_travail( $entree );

		if ( is_array( $travail ) && (int) $travail['utilisateur'] === get_current_user_id() && in_array( $travail['statut'], array( 'analyse', 'en_cours' ), true ) ) {
			$travaux[] = array(
				'jeton'   => $entree,
				'fichier' => (string) $travail['fichier'],
				'type'    => (string) $travail['type'],
				'date'    => (int) $travail['date'],
			);
		}
	}

	return $travaux;
}

/**
 * Rapport d'analyse d'un travail et actions (lancer, abandonner).
 *
 * @param string $jeton   Jeton.
 * @param array  $travail Travail.
 */
function ditl_import_admin_rapport( $jeton, $travail ) {
	$rapport = ditl_import_lire_json( $jeton, 'rapport' );

	if ( null === $rapport ) {
		$rapport = array();
	}

	$rapport = array_merge(
		array(
			'fatal'      => array(),
			'messages'   => array(),
			'groupes'    => array(),
			'lignes'     => array(),
			'compteurs'  => array(),
			'separateur' => '',
			'encodage'   => '',
		),
		$rapport
	);

	$type       = 'referentiels' === $travail['type'] ? 'referentiels' : 'fiches';
	$fatal      = array() !== $rapport['fatal'];
	$compteurs  = array_merge( ditl_import_compter( array() ), (array) $rapport['compteurs'] );
	$a_signaler = array();

	foreach ( $rapport['lignes'] as $ligne ) {
		if ( array() !== $ligne['messages'] ) {
			$a_signaler[] = $ligne;
		}
	}

	// Lignes en erreur d'abord, puis avertissements.
	usort(
		$a_signaler,
		static function ( $a, $b ) {
			$poids_a = 'rejetee' === $a['action'] ? 0 : 1;
			$poids_b = 'rejetee' === $b['action'] ? 0 : 1;

			return array( $poids_a, $a['ligne'] ) <=> array( $poids_b, $b['ligne'] );
		}
	);

	$en_cours = 'en_cours' === $travail['statut'];
	?>
	<h2 id="ditl-import-rapport" tabindex="-1"><?php esc_html_e( 'Rapport d\'analyse (simulation, aucune écriture)', 'ditl' ); ?></h2>

	<ul class="ditl-import-resume">
		<li><?php echo esc_html( sprintf( /* translators: %s : nom du fichier. */ __( 'Fichier : %s', 'ditl' ), (string) $travail['fichier'] ) ); ?></li>
		<li><?php echo esc_html( sprintf( /* translators: %s : type d'import. */ __( 'Type : %s', 'ditl' ), 'fiches' === $type ? __( 'fiches outils', 'ditl' ) : __( 'référentiels', 'ditl' ) ) ); ?></li>
		<?php if ( '' !== $rapport['encodage'] ) : ?>
			<li><?php echo esc_html( sprintf( /* translators: 1: separateur, 2: encodage. */ __( 'Séparateur : %1$s ; encodage : %2$s', 'ditl' ), "\t" === $rapport['separateur'] ? __( 'tabulation', 'ditl' ) : $rapport['separateur'], $rapport['encodage'] ) ); ?></li>
		<?php endif; ?>
		<li><?php echo ! empty( $travail['options']['ecraser'] ) ? esc_html__( 'Option : les fiches modifiées dans l\'administration seront écrasées.', 'ditl' ) : esc_html__( 'Les fiches modifiées dans l\'administration depuis le dernier import seront ignorées.', 'ditl' ); ?></li>
		<?php if ( $en_cours ) : ?>
			<li><strong><?php echo esc_html( sprintf( /* translators: 1: position, 2: total. */ __( 'Import interrompu : %1$d ligne(s) traitée(s) sur %2$d.', 'ditl' ), (int) $travail['position'], (int) $travail['total'] ) ); ?></strong></li>
		<?php endif; ?>
	</ul>

	<?php if ( $fatal ) : ?>
		<div class="notice notice-error inline">
			<p><strong><?php esc_html_e( 'Import impossible :', 'ditl' ); ?></strong></p>
			<?php ditl_import_admin_messages( array_map( 'ditl_import_admin_message_fatal', $rapport['fatal'] ) ); ?>
		</div>
	<?php else : ?>
		<h3><?php esc_html_e( 'Actions prévues', 'ditl' ); ?></h3>
		<ul class="ditl-import-compteurs">
			<li><?php echo esc_html( sprintf( /* translators: %d : nombre. */ __( 'Lignes : %d', 'ditl' ), $compteurs['lignes'] ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: %d : nombre. */ __( 'À créer : %d', 'ditl' ), $compteurs['creation'] ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: %d : nombre. */ __( 'À mettre à jour : %d', 'ditl' ), $compteurs['mise_a_jour'] ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: %d : nombre. */ __( 'Inchangées : %d', 'ditl' ), $compteurs['inchangee'] ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: %d : nombre. */ __( 'Ignorées : %d', 'ditl' ), $compteurs['ignoree'] ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: %d : nombre. */ __( 'Rejetées (erreur) : %d', 'ditl' ), $compteurs['rejetee'] ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: %d : nombre. */ __( 'Lignes avec avertissement : %d', 'ditl' ), $compteurs['avertissements'] ) ); ?></li>
		</ul>
	<?php endif; ?>

	<?php if ( array() !== $rapport['messages'] ) : ?>
		<h3><?php esc_html_e( 'Messages sur le fichier', 'ditl' ); ?></h3>
		<?php ditl_import_admin_messages( $rapport['messages'] ); ?>
	<?php endif; ?>

	<?php if ( array() !== $rapport['groupes'] ) : ?>
		<h3><?php esc_html_e( 'Contrôles par fiche (group_id)', 'ditl' ); ?></h3>
		<ul class="ditl-import-groupes">
			<?php foreach ( $rapport['groupes'] as $groupe => $messages ) : ?>
				<li>
					<?php echo esc_html( sprintf( /* translators: %s : group_id. */ __( 'Groupe %s', 'ditl' ), (string) $groupe ) ); ?>
					<?php ditl_import_admin_messages( $messages ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( ! $fatal ) : ?>
		<?php if ( array() !== $a_signaler ) : ?>
			<?php
			ditl_import_admin_tableau(
				$a_signaler,
				$type,
				/* translators: %d : nombre de lignes. */
				sprintf( __( 'Lignes avec erreur, avertissement ou information (%d)', 'ditl' ), count( $a_signaler ) ),
				true
			);
			?>
		<?php else : ?>
			<p><?php esc_html_e( 'Aucune ligne avec erreur ou avertissement.', 'ditl' ); ?></p>
		<?php endif; ?>

		<details class="ditl-import-toutes">
			<summary><?php echo esc_html( sprintf( /* translators: %d : nombre de lignes. */ __( 'Toutes les lignes (%d)', 'ditl' ), count( $rapport['lignes'] ) ) ); ?></summary>
			<?php ditl_import_admin_tableau( $rapport['lignes'], $type, __( 'Toutes les lignes du fichier', 'ditl' ), true ); ?>
		</details>
	<?php endif; ?>

	<div class="ditl-import-actions">
		<noscript>
			<p class="ditl-import-noscript">
				<?php esc_html_e( 'L\'exécution de l\'import par lots demande JavaScript. À défaut, faire lancer l\'import en ligne de commande (WP-CLI) : wp eval-file wp-content/themes/ditl/cli/importer-fiches.php.', 'ditl' ); ?>
			</p>
		</noscript>

		<button type="button" class="button button-primary" id="ditl-import-lancer"
			data-jeton="<?php echo esc_attr( $jeton ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'ditl_import_lot_' . $jeton ) ); ?>"
			<?php disabled( $fatal ); ?>>
			<?php echo $en_cours ? esc_html__( 'Reprendre l\'import', 'ditl' ) : esc_html__( 'Lancer l\'import', 'ditl' ); ?>
		</button>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ditl-import-abandon">
			<input type="hidden" name="action" value="ditl_import_abandonner">
			<input type="hidden" name="travail" value="<?php echo esc_attr( $jeton ); ?>">
			<?php wp_nonce_field( 'ditl_import_abandonner_' . $jeton, 'ditl_import_nonce' ); ?>
			<button type="submit" class="button" id="ditl-import-abandon"><?php esc_html_e( 'Abandonner (supprimer les fichiers envoyés)', 'ditl' ); ?></button>
		</form>
	</div>

	<div id="ditl-import-progression" class="ditl-import-progression" hidden>
		<label for="ditl-import-barre"><?php esc_html_e( 'Progression de l\'import', 'ditl' ); ?></label>
		<progress id="ditl-import-barre" max="<?php echo esc_attr( (string) max( 1, (int) $travail['total'] ) ); ?>" value="<?php echo esc_attr( (string) (int) $travail['position'] ); ?>"></progress>
	</div>
	<p id="ditl-import-statut" class="ditl-import-statut" role="status" aria-live="polite"></p>
	<div id="ditl-import-final" class="ditl-import-final" hidden></div>
	<?php
}

/**
 * Message fatal au format { niveau, texte }.
 *
 * @param string $texte Texte.
 * @return array Message.
 */
function ditl_import_admin_message_fatal( $texte ) {
	return array(
		'niveau' => 'fatal',
		'texte'  => (string) $texte,
	);
}

/**
 * Controle un fichier envoye.
 *
 * @param string   $champ      Nom du champ de formulaire.
 * @param string[] $extensions Extensions acceptees.
 * @return array|string|null Fichier { name, tmp_name } valide, null si aucun
 *                           fichier, ou code d'erreur (avis).
 */
function ditl_import_admin_fichier_envoye( $champ, $extensions ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verifie par l'appelant.
	if ( ! isset( $_FILES[ $champ ] ) || ! is_array( $_FILES[ $champ ] ) ) {
		return null;
	}

	$fichier = $_FILES[ $champ ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- controle champ par champ ci-dessous.
	$erreur  = isset( $fichier['error'] ) && is_scalar( $fichier['error'] ) ? (int) $fichier['error'] : UPLOAD_ERR_NO_FILE;

	if ( UPLOAD_ERR_NO_FILE === $erreur ) {
		return null;
	}

	if ( UPLOAD_ERR_INI_SIZE === $erreur || UPLOAD_ERR_FORM_SIZE === $erreur ) {
		return 'taille';
	}

	if ( UPLOAD_ERR_OK !== $erreur || ! isset( $fichier['tmp_name'], $fichier['name'] ) || ! is_scalar( $fichier['tmp_name'] ) || ! is_scalar( $fichier['name'] ) || ! is_uploaded_file( (string) $fichier['tmp_name'] ) ) {
		return 'envoi';
	}

	$nom       = sanitize_file_name( (string) $fichier['name'] );
	$extension = strtolower( (string) pathinfo( $nom, PATHINFO_EXTENSION ) );

	if ( ! in_array( $extension, $extensions, true ) ) {
		return 'extension';
	}

	return array(
		'name'     => $nom,
		'tmp_name' => (string) $fichier['tmp_name'],
	);
}

/**
 * Analyse (admin-post) : cree le travail, extrait les images, simule.
 */
function ditl_import_admin_analyser() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'ditl' ), 403 );
	}

	// Envoi plus gros que post_max_size : PHP vide $_POST et $_FILES.
	if ( empty( $_POST ) && isset( $_SERVER['CONTENT_LENGTH'] ) && (int) $_SERVER['CONTENT_LENGTH'] > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- requete tronquee par PHP, aucun champ lu.
		ditl_import_admin_rediriger( 'taille' );
	}

	check_admin_referer( 'ditl_import_analyser', 'ditl_import_nonce' );

	$type = isset( $_POST['ditl_import_type'] ) ? sanitize_key( wp_unslash( $_POST['ditl_import_type'] ) ) : '';

	if ( ! in_array( $type, array( 'fiches', 'referentiels' ), true ) ) {
		ditl_import_admin_rediriger( 'type' );
	}

	$csv = ditl_import_admin_fichier_envoye( 'ditl_import_csv', array( 'csv', 'txt', 'tsv' ) );

	if ( null === $csv ) {
		ditl_import_admin_rediriger( 'csv' );
	}

	if ( is_string( $csv ) ) {
		ditl_import_admin_rediriger( $csv );
	}

	$zip = 'fiches' === $type ? ditl_import_admin_fichier_envoye( 'ditl_import_zip', array( 'zip' ) ) : null;

	if ( is_string( $zip ) ) {
		ditl_import_admin_rediriger( $zip );
	}

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- peut etre interdit par l'hebergeur.
	}

	ditl_import_nettoyer_travaux();

	// Une nouvelle analyse remplace les analyses non lancees de l'utilisateur.
	ditl_import_supprimer_analyses( get_current_user_id() );

	$jeton = ditl_import_creer_travail(
		array(
			'type'    => $type,
			'fichier' => $csv['name'],
			'options' => array( 'ecraser' => ! empty( $_POST['ditl_import_ecraser'] ) ),
			'statut'  => 'analyse',
		)
	);

	if ( is_wp_error( $jeton ) ) {
		ditl_import_admin_rediriger( 'dossier' );
	}

	$dossier = ditl_import_dossier_travail( $jeton );

	if ( ! move_uploaded_file( $csv['tmp_name'], $dossier . '/source.csv' ) ) {
		ditl_import_supprimer_travail( $jeton );
		ditl_import_admin_rediriger( 'envoi' );
	}

	$travail        = ditl_import_lire_travail( $jeton );
	$travail['zip'] = array(
		'fatal'    => array(),
		'messages' => array(),
	);

	if ( is_array( $zip ) ) {
		$archive = $dossier . '/images.zip';

		if ( move_uploaded_file( $zip['tmp_name'], $archive ) ) {
			$extraction     = ditl_import_extraire_zip( $archive, $dossier . '/images', ditl_import_regles() );
			$travail['zip'] = array(
				'fatal'    => $extraction['fatal'],
				'messages' => $extraction['messages'],
			);
			wp_delete_file( $archive );
		} else {
			$travail['zip']['fatal'][] = 'Archive d\'images non reçue.';
		}
	}

	$travail['sha1'] = (string) sha1_file( $dossier . '/source.csv' );

	$plan       = ditl_import_plan_travail( $jeton, $travail );
	$simulation = ditl_import_executer( $plan, 0, 0, true );

	ditl_import_ecrire_json(
		$jeton,
		'rapport',
		array(
			'fatal'      => $plan['fatal'],
			'messages'   => $plan['messages'],
			'groupes'    => $plan['groupes'],
			'separateur' => $plan['separateur'],
			'encodage'   => $plan['encodage'],
			'compteurs'  => ditl_import_compter( $simulation['resultats'] ),
			'lignes'     => $simulation['resultats'],
		)
	);

	$travail['total']     = count( $plan['ordre'] );
	$travail['position']  = 0;
	$travail['fatal']     = array() !== $plan['fatal'];
	$travail['compteurs'] = array();
	$travail['statut']    = 'analyse';
	ditl_import_ecrire_travail( $jeton, $travail );

	ditl_import_admin_rediriger( '', $jeton );
}
add_action( 'admin_post_ditl_import_analyser', 'ditl_import_admin_analyser' );

/**
 * Abandon (admin-post) : suppression du dossier de travail, verrou du
 * travail tenu pendant la suppression.
 */
function ditl_import_admin_abandonner() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'ditl' ), 403 );
	}

	$jeton = isset( $_POST['travail'] ) ? sanitize_key( wp_unslash( $_POST['travail'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verifie juste apres (il depend du jeton).

	if ( ! ditl_import_jeton_valide( $jeton ) ) {
		ditl_import_admin_rediriger( 'introuvable' );
	}

	check_admin_referer( 'ditl_import_abandonner_' . $jeton, 'ditl_import_nonce' );

	$travail = ditl_import_lire_travail( $jeton );

	if ( null === $travail || (int) $travail['utilisateur'] !== get_current_user_id() ) {
		ditl_import_admin_rediriger( 'introuvable' );
	}

	$verrou = ditl_import_verrouiller( $jeton );

	if ( null === $verrou ) {
		// Un lot est en cours : on ne supprime pas sous ses pieds.
		ditl_import_admin_rediriger( 'lot_en_cours', $jeton );
	}

	ditl_import_supprimer_travail( $jeton );
	ditl_import_deverrouiller( $verrou );
	ditl_import_admin_rediriger( 'abandon' );
}
add_action( 'admin_post_ditl_import_abandonner', 'ditl_import_admin_abandonner' );

/**
 * Execution d'un lot (admin-ajax).
 */
function ditl_import_admin_lot() {
	$jeton = isset( $_POST['jeton'] ) ? sanitize_key( wp_unslash( $_POST['jeton'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verifie juste apres (il depend du jeton).

	if ( ! ditl_import_jeton_valide( $jeton ) ) {
		wp_send_json_error( array( 'message' => __( 'Import introuvable.', 'ditl' ) ), 400 );
	}

	check_ajax_referer( 'ditl_import_lot_' . $jeton, 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'ditl' ) ), 403 );
	}

	$travail = ditl_import_lire_travail( $jeton );

	if ( null === $travail || (int) $travail['utilisateur'] !== get_current_user_id() ) {
		wp_send_json_error( array( 'message' => __( 'Import introuvable (terminé, abandonné ou de plus de 24 heures).', 'ditl' ) ), 404 );
	}

	$verrou = ditl_import_verrouiller( $jeton );

	if ( null === $verrou ) {
		wp_send_json_error(
			array(
				'message' => __( 'Un lot est déjà en cours d\'exécution.', 'ditl' ),
				'occupe'  => true,
			),
			409
		);
	}

	$reponse = ditl_import_lot_travail( $jeton );

	ditl_import_deverrouiller( $verrou );

	if ( ! empty( $reponse['termine'] ) ) {
		ditl_import_supprimer_travail( $jeton );
	}

	if ( isset( $reponse['erreur'] ) ) {
		wp_send_json_error(
			array(
				'message' => $reponse['erreur'],
				'code'    => isset( $reponse['code'] ) ? $reponse['code'] : 'erreur',
			),
			409
		);
	}

	wp_send_json_success( $reponse );
}
add_action( 'wp_ajax_ditl_import_lot', 'ditl_import_admin_lot' );

/**
 * Telechargements (admin-post) : modele de fiches, export des referentiels.
 */
function ditl_import_admin_telecharger() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'ditl' ), 403 );
	}

	$quoi = isset( $_GET['quoi'] ) ? sanitize_key( wp_unslash( $_GET['quoi'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verifie juste apres (il depend de la valeur).

	if ( ! in_array( $quoi, array( 'modele', 'referentiels' ), true ) ) {
		wp_die( esc_html__( 'Téléchargement inconnu.', 'ditl' ), 400 );
	}

	check_admin_referer( 'ditl_import_telecharger_' . $quoi );

	if ( 'modele' === $quoi ) {
		$entetes = ditl_import_entetes_modele_fiches();
		$lignes  = array();
		$nom     = 'modele-fiches-outils.csv';
	} else {
		$export  = ditl_import_exporter_referentiels();
		$entetes = $export['entetes'];
		$lignes  = $export['lignes'];
		$nom     = 'referentiels-' . gmdate( 'Y-m-d' ) . '.csv';
	}

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $nom . '"' );
	header( 'X-Content-Type-Options: nosniff' );

	$flux = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- sortie HTTP.

	if ( false !== $flux ) {
		ditl_import_ecrire_csv( $flux, $entetes, $lignes );
		fclose( $flux ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- sortie HTTP.
	}

	exit;
}
add_action( 'admin_post_ditl_import_telecharger', 'ditl_import_admin_telecharger' );
