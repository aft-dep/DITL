<?php
/**
 * Formulaire de contact francais (RGAA 8.7, lot a11y B4).
 *
 * CONSTAT : la page Contact francaise (16) et la page anglaise (2595)
 * affichent le MEME formulaire WPForms, le formulaire 6 "Contact Form",
 * dont les etiquettes, les textes indicatifs, le bouton d'envoi et les
 * messages sont en anglais. Sur /fr/contact/, c'est un changement de langue
 * non indique (critere 8.7 de l'audit Access42) et une gene d'usage.
 *
 * CHOIX RETENU (decision Aurélien du 08/09/2026) : DUPLIQUER le formulaire 6
 * en un formulaire francais et le rattacher a la seule page FR. Le
 * formulaire 6 reste STRICTEMENT INCHANGE et continue de servir la page EN.
 * L'alternative (un lang="en" pose sur le <form>) aurait rendu la page
 * conforme sans la rendre utilisable en francais.
 *
 * CE QUE FAIT LE SCRIPT (operation de donnees, aucun fichier de theme
 * modifie) :
 * 1. cree - ou met a jour - une publication de type "wpforms" intitulee
 *    "Contact (francais)", copie conforme du formulaire 6 dont seuls les
 *    textes visibles sont traduits ;
 * 2. memorise son identifiant dans l'option ditl_contact_formulaire_fr ;
 * 3. pose cet identifiant dans la cle form_id de la meta
 *    _ditl_contact_formulaire de la page 16 (le titre de colonne, deja en
 *    francais, n'est pas touche).
 *
 * IDENTIFIANTS DE CHAMPS CONSERVES : le formulaire francais reprend les
 * memes identifiants de champs que le formulaire 6 (7 Prenom, 1 Nom,
 * 3 Adresse e-mail, 2 Telephone, 8 Organisme, 10 Pays, 4 Message), ainsi
 * que le compteur field_id. C'est indispensable : la table du theme qui
 * pose les attributs autocomplete et le type="tel" du lot B4
 * (ditl_a11y_wpforms_champs_autocomplete, inc/a11y.php) est indexee par
 * identifiant de champ. Le formulaire est ecrit ici champ par champ, sans
 * passer par la duplication de WPForms, precisement pour garantir cette
 * stabilite.
 *
 * DEPENDANCE THEME (assuree, controlee au demarrage)
 * La table ci-dessus est aussi indexee par identifiant de FORMULAIRE, et le
 * formulaire francais recoit un identifiant different sur chaque
 * environnement (c'est un identifiant de publication). inc/a11y.php relaie
 * donc l'option posee ici vers la table (fonction
 * ditl_a11y_wpforms_champs_autocomplete) : rien a ajouter a la main. Le
 * controle de dependance ci-dessous le verifie et refuse d'ecrire si le
 * relais venait a disparaitre (sauf argument "sans-controle-theme"), car
 * la page FR perdrait alors l'autocomplete, le type="tel" et le maxlength
 * du lot B4. Repli si le relais devait etre retire du theme :
 *
 *     add_filter( 'ditl_a11y_wpforms_champs_autocomplete', function ( $champs ) {
 *         $fr = (int) get_option( 'ditl_contact_formulaire_fr', 0 );
 *         if ( $fr > 0 && isset( $champs[6] ) ) {
 *             $champs[ $fr ] = $champs[6];
 *         }
 *         return $champs;
 *     } );
 *
 * CE QUE LE SCRIPT NE TOUCHE PAS : le formulaire 6, la page 2595 (EN), les
 * destinataires des notifications (la copie reprend a l'identique l'adresse
 * de destination, l'adresse d'expedition et le nom d'expediteur du
 * formulaire 6), les reglages anti-spam et la soumission AJAX.
 *
 * Script idempotent : un rejeu sans changement n'ecrit rien.
 *
 * MODE ANNULER : repose l'identifiant 6 dans la meta de la page 16 (la page
 * FR retrouve le formulaire anglais) et met le formulaire francais A LA
 * CORBEILLE - jamais de suppression definitive, les entrees et l'historique
 * restent recuperables. L'option ditl_contact_formulaire_fr est CONSERVEE
 * volontairement : elle permet a un rejeu du mode normal de sortir le meme
 * formulaire de la corbeille au lieu d'en creer un doublon, et un
 * identifiant de formulaire en corbeille est sans effet cote rendu.
 *
 * ============================ RUNBOOK PREPROD / PROD ============================
 * Le formulaire francais est une DONNEE : il n'existe pas dans le depot et
 * doit etre cree sur chaque environnement en rejouant ce script. Son
 * identifiant differera d'un environnement a l'autre, d'ou l'option.
 * 1. Deployer le code du theme (la ligne de filtre ci-dessus doit y etre).
 * 2. Simulation :   wp eval-file wp-content/themes/ditl/cli/traduire-formulaire-contact.php dry-run
 * 3. Application :  wp eval-file wp-content/themes/ditl/cli/traduire-formulaire-contact.php
 * 4. Purger le cache de page (wp-content/cache/all) : /fr/contact/ est certes
 *    exclue du cache par cli/configurer-cache.php, mais la purge evite toute
 *    ambiguite lors de la recette.
 * 5. Recette : ouvrir /fr/contact/ - etiquettes, bouton "Envoyer" et liste
 *    des pays en francais ; ouvrir /contact-us/ - formulaire anglais
 *    inchange. Verifier dans le code source de la page FR la presence des
 *    attributs autocomplete, du type="tel" et du maxlength="20".
 * 6. Envoyer un message de test depuis /fr/contact/ et verifier la reception
 *    a l'adresse habituelle (les destinataires sont inchanges) ainsi que le
 *    message de confirmation en francais.
 * 7. Retour arriere :
 *    wp eval-file wp-content/themes/ditl/cli/traduire-formulaire-contact.php annuler
 * ===============================================================================
 *
 * Usage :
 *   wp eval-file wp-content/themes/ditl/cli/traduire-formulaire-contact.php dry-run
 *   wp eval-file wp-content/themes/ditl/cli/traduire-formulaire-contact.php
 *   wp eval-file wp-content/themes/ditl/cli/traduire-formulaire-contact.php annuler
 *
 * Le mode simulation accepte "dry-run" ou "--dry-run", l'annulation
 * "annuler" ou "--annuler" (combinables). L'argument "sans-controle-theme"
 * transforme en simple avertissement l'arret provoque par l'absence du
 * filtre d'autocomplete (a n'utiliser que pour preparer les donnees avant
 * le deploiement du theme).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	// Usage : wp eval-file <script> [annuler] [dry-run] - voir le docblock.
	// En acces web direct, reponse muette pour ne rien reveler du fichier.
	http_response_code( 404 );
	exit( 1 );
}

// Bibliotheque commune des scripts CLI du theme.
require_once __DIR__ . '/commun.php';

// ---------------------------------------------------------------------------
// Reperes fixes du chantier.
// ---------------------------------------------------------------------------

// Formulaire anglais d'origine, conserve tel quel pour la page EN.
$ditl_form_en = 6;

// Pages Contact : francaise (a rebrancher) et anglaise (temoin, non touchee).
$ditl_page_fr = 16;
$ditl_page_en = 2595;

// Meta portant l'objet JSON {titre, form_id} du gabarit Contact.
$ditl_meta_formulaire = '_ditl_contact_formulaire';

// Option memorisant l'identifiant du formulaire francais de l'environnement.
$ditl_option_form_fr = 'ditl_contact_formulaire_fr';

// Identite de la publication du formulaire francais.
$ditl_titre_fr = 'Contact (francais)';
$ditl_slug_fr  = 'contact-formulaire-francais';

// Type de publication des formulaires WPForms.
$ditl_type_form = 'wpforms';

// ---------------------------------------------------------------------------
// Lecture des arguments : simulation, annulation, controle du theme.
// ---------------------------------------------------------------------------

// L'argument propre au script est retire avant la lecture commune des
// modes, qui signale tout argument qu'elle ne connait pas.
$ditl_args_modes         = array();
$ditl_controle_theme_dur = true;

foreach ( (array) $args as $ditl_arg ) {
	if ( 'sans-controle-theme' === $ditl_arg || '--sans-controle-theme' === $ditl_arg ) {
		$ditl_controle_theme_dur = false;
		continue;
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
// Traductions posees (textes VISIBLES par le visiteur francais).
// ---------------------------------------------------------------------------

/**
 * Traduction des champs, indexee par identifiant de champ du formulaire 6.
 *
 * "attendu" est l'intitule anglais d'origine : s'il a change en base (le
 * client peut avoir retouche le formulaire), le script le signale plutot
 * que de traduire a l'aveugle un champ devenu autre chose.
 */
$ditl_traduction_champs = array(
	7  => array(
		'attendu'     => 'Name',
		'label'       => 'Prénom',
		'placeholder' => null,
	),
	1  => array(
		'attendu'     => 'Last name',
		'label'       => 'Nom',
		'placeholder' => 'Votre nom',
	),
	3  => array(
		'attendu'     => 'Email',
		'label'       => 'Adresse e-mail',
		'placeholder' => 'Adresse e-mail',
	),
	2  => array(
		'attendu'     => 'Phone',
		'label'       => 'Téléphone',
		'placeholder' => 'Numéro de téléphone',
	),
	8  => array(
		'attendu'     => 'Company / Organisation',
		'label'       => 'Organisme',
		'placeholder' => null,
	),
	10 => array(
		'attendu'     => 'Country',
		'label'       => 'Pays',
		'placeholder' => null,
	),
	4  => array(
		'attendu'     => 'Message',
		'label'       => 'Message',
		'placeholder' => 'Message',
	),
);

/**
 * Traduction des pays de la liste deroulante, indexee par intitule anglais.
 *
 * Les intitules deja francais du formulaire 6 (Chypre, Danemark, Republique
 * tcheque) sont repris a l'identique. Les identifiants de choix ne changent
 * pas ; seul l'ORDRE d'affichage est recalcule (alphabetique francais),
 * sans quoi "Allemagne" resterait a la place de "Germany", entre France et
 * Grece.
 */
$ditl_traduction_pays = array(
	'Austria'            => 'Autriche',
	'Belgium'            => 'Belgique',
	'Bulgaria'           => 'Bulgarie',
	'Chypre'             => 'Chypre',
	'Croatia'            => 'Croatie',
	'Danemark'           => 'Danemark',
	'Estonia'            => 'Estonie',
	'Finland'            => 'Finlande',
	'France'             => 'France',
	'Germany'            => 'Allemagne',
	'Greece'             => 'Grèce',
	'Hungary'            => 'Hongrie',
	'Ireland'            => 'Irlande',
	'Italy'              => 'Italie',
	'Latvia'             => 'Lettonie',
	'Lithuania'          => 'Lituanie',
	'Luxembourg'         => 'Luxembourg',
	'Malta'              => 'Malte',
	'Netherlands'        => 'Pays-Bas',
	'Poland'             => 'Pologne',
	'Portugal'           => 'Portugal',
	'République tchèque' => 'République tchèque',
	'Romania'            => 'Roumanie',
	'Slovakia'           => 'Slovaquie',
	'Slovenia'           => 'Slovénie',
	'Spain'              => 'Espagne',
	'Switzerland'        => 'Suisse',
	'United Kingdom'     => 'Royaume-Uni',
);

/**
 * Traduction des reglages du formulaire (bouton, confirmation, notification).
 *
 * Les cles absentes de ce tableau sont recopiees telles quelles : c'est le
 * cas des destinataires, du nom et de l'adresse d'expedition, du corps de
 * notification ({all_fields}, une balise dynamique) et de tout l'anti-spam.
 */
$ditl_traduction_reglages = array(
	'submit_text'            => 'Envoyer',
	'submit_text_processing' => 'Envoi en cours...',
	'notification_subject'   => 'Nouveau message du formulaire de contact',
	'confirmation_message'   => '<p>Merci de nous avoir contactés ! Nous reviendrons vers vous très prochainement.</p>',
);

// ---------------------------------------------------------------------------
// Outils locaux.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'ditl_contact_fr_lire_meta' ) ) {
	/**
	 * Lit la meta JSON {titre, form_id} d'une page Contact.
	 *
	 * La lecture ne depend d'aucune fonction du theme : le script doit
	 * rester jouable meme si le theme n'est pas encore actif sur
	 * l'environnement.
	 *
	 * @param int    $page_id  ID de la page.
	 * @param string $meta_cle Cle de la meta.
	 * @return array Tableau { titre => string, form_id => int }.
	 */
	function ditl_contact_fr_lire_meta( $page_id, $meta_cle ) {
		$brut = get_post_meta( $page_id, $meta_cle, true );
		$data = is_scalar( $brut ) ? json_decode( (string) $brut, true ) : null;

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		return array(
			'titre'   => isset( $data['titre'] ) && is_scalar( $data['titre'] ) ? (string) $data['titre'] : '',
			'form_id' => isset( $data['form_id'] ) && is_scalar( $data['form_id'] ) ? absint( $data['form_id'] ) : 0,
		);
	}
}

if ( ! function_exists( 'ditl_contact_fr_encoder_meta' ) ) {
	/**
	 * Encode la meta {titre, form_id} exactement comme la metabox du theme
	 * (memes cles, meme ordre, memes options d'encodage).
	 *
	 * @param string $titre   Titre de la colonne formulaire.
	 * @param int    $form_id ID du formulaire a afficher.
	 * @return string JSON pret a etre stocke.
	 */
	function ditl_contact_fr_encoder_meta( $titre, $form_id ) {
		return (string) wp_json_encode(
			array(
				'titre'   => (string) $titre,
				'form_id' => (int) $form_id,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}
}

if ( ! function_exists( 'ditl_contact_fr_traduire' ) ) {
	/**
	 * Construit le JSON du formulaire francais a partir du formulaire source.
	 *
	 * Le tableau retourne est une copie integrale du formulaire source dont
	 * seuls les textes visibles sont remplaces : structure, identifiants de
	 * champs, compteur field_id, reglages anti-spam et destinataires des
	 * notifications restent ceux de la source.
	 *
	 * @param array  $source     Donnees decodees du formulaire source.
	 * @param array  $champs     Traduction des champs, par identifiant.
	 * @param array  $pays       Traduction des pays, par intitule anglais.
	 * @param array  $reglages   Traduction des reglages.
	 * @param string $titre      Titre du formulaire francais.
	 * @param array  $anomalies  Anomalies rencontrees (passe par reference).
	 * @return array Donnees du formulaire francais (cle "id" non renseignee).
	 */
	function ditl_contact_fr_traduire( $source, $champs, $pays, $reglages, $titre, &$anomalies ) {
		$forme = $source;

		// --- Champs -----------------------------------------------------
		$forme['fields'] = array();

		foreach ( (array) $source['fields'] as $cle => $champ ) {
			$id = isset( $champ['id'] ) ? (int) $champ['id'] : 0;

			if ( ! isset( $champs[ $id ] ) ) {
				$anomalies[] = sprintf( 'Champ %d ("%s") absent de la table de traduction : recopie en anglais.', $id, isset( $champ['label'] ) ? (string) $champ['label'] : '' );
				$forme['fields'][ $cle ] = $champ;
				continue;
			}

			$regle   = $champs[ $id ];
			$origine = isset( $champ['label'] ) ? (string) $champ['label'] : '';

			if ( $origine !== $regle['attendu'] ) {
				$anomalies[] = sprintf( 'Champ %d : intitule source "%s" au lieu de "%s" attendu (le formulaire anglais a change ?).', $id, $origine, $regle['attendu'] );
			}

			$champ['label'] = $regle['label'];

			if ( null !== $regle['placeholder'] && isset( $champ['placeholder'] ) ) {
				$champ['placeholder'] = $regle['placeholder'];
			}

			// Liste deroulante des pays : intitules traduits, identifiants
			// de choix conserves, ordre recalcule en alphabetique francais.
			if ( isset( $champ['choices'] ) && is_array( $champ['choices'] ) ) {
				$traduits = array();

				foreach ( $champ['choices'] as $choix_cle => $choix ) {
					$intitule = isset( $choix['label'] ) ? (string) $choix['label'] : '';

					if ( isset( $pays[ $intitule ] ) ) {
						$choix['label'] = $pays[ $intitule ];
					} elseif ( '' !== $intitule ) {
						$anomalies[] = sprintf( 'Pays "%s" absent de la table de traduction : recopie tel quel.', $intitule );
					}

					$traduits[ $choix_cle ] = $choix;
				}

				uasort(
					$traduits,
					/**
					 * Tri alphabetique francais des intitules de pays.
					 *
					 * @param array $a Premier choix.
					 * @param array $b Second choix.
					 * @return int
					 */
					function ( $a, $b ) {
						$ga = remove_accents( isset( $a['label'] ) ? (string) $a['label'] : '' );
						$gb = remove_accents( isset( $b['label'] ) ? (string) $b['label'] : '' );

						return strcasecmp( $ga, $gb );
					}
				);

				$champ['choices'] = $traduits;
			}

			$forme['fields'][ $cle ] = $champ;
		}

		// --- Reglages ---------------------------------------------------
		if ( ! isset( $forme['settings'] ) || ! is_array( $forme['settings'] ) ) {
			$forme['settings'] = array();
		}

		$forme['settings']['form_title']             = $titre;
		$forme['settings']['submit_text']            = $reglages['submit_text'];
		$forme['settings']['submit_text_processing'] = $reglages['submit_text_processing'];

		// Notifications : seul l'objet est traduit. Adresse de destination,
		// adresse et nom d'expedition, corps ({all_fields}) sont recopies.
		if ( isset( $forme['settings']['notifications'] ) && is_array( $forme['settings']['notifications'] ) ) {
			foreach ( $forme['settings']['notifications'] as $cle => $notification ) {
				if ( ! is_array( $notification ) ) {
					continue;
				}

				$notification['subject'] = $reglages['notification_subject'];

				$forme['settings']['notifications'][ $cle ] = $notification;
			}
		}

		// Confirmations : seules celles de type "message" ont un texte a
		// traduire (une confirmation de type page ou redirection n'en a pas).
		if ( isset( $forme['settings']['confirmations'] ) && is_array( $forme['settings']['confirmations'] ) ) {
			foreach ( $forme['settings']['confirmations'] as $cle => $confirmation ) {
				if ( ! is_array( $confirmation ) ) {
					continue;
				}

				if ( isset( $confirmation['type'] ) && 'message' !== $confirmation['type'] ) {
					$anomalies[] = sprintf( 'Confirmation %s de type "%s" : aucun message a traduire.', (string) $cle, (string) $confirmation['type'] );
				} else {
					$confirmation['message'] = $reglages['confirmation_message'];
				}

				$forme['settings']['confirmations'][ $cle ] = $confirmation;
			}
		}

		return $forme;
	}
}

if ( ! function_exists( 'ditl_contact_fr_encoder_formulaire' ) ) {
	/**
	 * Encode les donnees d'un formulaire comme le fait WPForms lui-meme
	 * (wp_json_encode aux options par defaut : unicode echappe).
	 *
	 * @param array $forme Donnees du formulaire.
	 * @return string JSON pret a etre stocke en post_content.
	 */
	function ditl_contact_fr_encoder_formulaire( $forme ) {
		return (string) wp_json_encode( $forme );
	}
}

// ---------------------------------------------------------------------------
// Reperage du formulaire francais existant (option, puis slug).
// ---------------------------------------------------------------------------

$ditl_form_fr = 0;
$ditl_memo    = absint( get_option( $ditl_option_form_fr, 0 ) );

if ( $ditl_memo > 0 && $ditl_type_form === get_post_type( $ditl_memo ) ) {
	$ditl_form_fr = $ditl_memo;
} else {
	if ( $ditl_memo > 0 ) {
		WP_CLI::warning( sprintf( 'Option %s : le formulaire %d n\'existe plus, recherche par identifiant de publication.', $ditl_option_form_fr, $ditl_memo ) );
	}

	// Repli : une publication du bon type portant le slug attendu. Le statut
	// "trash" est demande explicitement ("any" l'exclut) et le slug d'une
	// publication mise a la corbeille recoit le suffixe __trashed du coeur :
	// les deux formes sont cherchees, sinon une annulation suivie d'un rejeu
	// creerait un doublon si l'option avait ete effacee entre-temps.
	$ditl_trouves = get_posts(
		array(
			'post_type'        => $ditl_type_form,
			'post_name__in'    => array( $ditl_slug_fr, $ditl_slug_fr . '__trashed' ),
			'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'suppress_filters' => false,
		)
	);

	if ( ! empty( $ditl_trouves ) ) {
		$ditl_form_fr = (int) $ditl_trouves[0];
	}
}

// ---------------------------------------------------------------------------
// MODE ANNULER : la page FR revient au formulaire anglais.
// ---------------------------------------------------------------------------

if ( $ditl_annuler ) {
	WP_CLI::log( '--- Annulation : retour du formulaire anglais sur la page FR ---' );

	$ditl_meta_actuelle = ditl_contact_fr_lire_meta( $ditl_page_fr, $ditl_meta_formulaire );

	if ( $ditl_form_en === $ditl_meta_actuelle['form_id'] ) {
		WP_CLI::log( sprintf( '  Page %d : deja rattachee au formulaire %d, rien a faire.', $ditl_page_fr, $ditl_form_en ) );
	} elseif ( $ditl_form_fr !== $ditl_meta_actuelle['form_id'] ) {
		// Un tiers a rattache un autre formulaire depuis : ne rien ecraser.
		WP_CLI::warning( sprintf( 'Page %d : rattachee au formulaire %d, ni l\'anglais ni le francais de ce script. Meta laissee telle quelle.', $ditl_page_fr, $ditl_meta_actuelle['form_id'] ) );
	} elseif ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] Page %d : form_id %d serait remis a %d.', $ditl_page_fr, $ditl_meta_actuelle['form_id'], $ditl_form_en ) );
	} else {
		update_post_meta( $ditl_page_fr, $ditl_meta_formulaire, wp_slash( ditl_contact_fr_encoder_meta( $ditl_meta_actuelle['titre'], $ditl_form_en ) ) );
		WP_CLI::log( sprintf( '  Page %d : form_id remis a %d.', $ditl_page_fr, $ditl_form_en ) );
		$ditl_ecritures++;
	}

	// Le formulaire francais part a la CORBEILLE, jamais a la poubelle
	// definitive : rien n'est perdu et un rejeu du mode normal le restaure.
	if ( $ditl_form_fr <= 0 ) {
		WP_CLI::log( '  Formulaire francais : aucun en base, rien a faire.' );
	} elseif ( 'trash' === get_post_status( $ditl_form_fr ) ) {
		WP_CLI::log( sprintf( '  Formulaire francais %d : deja en corbeille, rien a faire.', $ditl_form_fr ) );
	} elseif ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] Formulaire francais %d : serait mis en corbeille.', $ditl_form_fr ) );
	} else {
		wp_trash_post( $ditl_form_fr );
		WP_CLI::log( sprintf( '  Formulaire francais %d : mis en corbeille (recuperable).', $ditl_form_fr ) );
		$ditl_ecritures++;
	}

	WP_CLI::log( sprintf( '  Option %s : conservee volontairement (evite un doublon au prochain rejeu).', $ditl_option_form_fr ) );
	WP_CLI::log( '' );

	if ( $ditl_dry_run ) {
		WP_CLI::log( 'Simulation terminee.' );
	} else {
		WP_CLI::log( sprintf( 'Annulation terminee (%d ecriture(s) lors de ce passage).', $ditl_ecritures ) );
		WP_CLI::log( 'Purger le cache de page avant la recette (wp-content/cache/all).' );
	}

	return;
}

// ---------------------------------------------------------------------------
// Controle de dependance : le theme doit savoir etendre sa table a11y.
// ---------------------------------------------------------------------------

// Le theme est interroge avec un identifiant temoin : si sa table relaie
// bien l'option, elle contient une entree pour cet identifiant. Le temoin
// est injecte EN MEMOIRE par le filtre pre_option_* du coeur - aucune
// ecriture en base, y compris hors mode simulation.
$ditl_temoin = 999999;

$ditl_filtre_temoin =
	/**
	 * Sert la valeur temoin a la place de l'option, sans y toucher.
	 *
	 * @return int Identifiant temoin.
	 */
	function () use ( $ditl_temoin ) {
		return $ditl_temoin;
	};

add_filter( 'pre_option_' . $ditl_option_form_fr, $ditl_filtre_temoin );

$ditl_table_a11y = function_exists( 'ditl_a11y_wpforms_champs_autocomplete' ) ? ditl_a11y_wpforms_champs_autocomplete() : array();

remove_filter( 'pre_option_' . $ditl_option_form_fr, $ditl_filtre_temoin );

if ( isset( $ditl_table_a11y[ $ditl_temoin ] ) && is_array( $ditl_table_a11y[ $ditl_temoin ] ) ) {
	WP_CLI::log( sprintf( '  Theme : la table autocomplete suit bien l\'option %s.', $ditl_option_form_fr ) );
} else {
	$ditl_message = sprintf(
		'Le theme ne relaie pas l\'option %s dans ditl_a11y_wpforms_champs_autocomplete() : le formulaire francais perdrait l\'autocomplete, le type="tel" et le maxlength du lot B4. Ajouter le filtre indique dans le docblock de ce script.',
		$ditl_option_form_fr
	);

	if ( $ditl_controle_theme_dur ) {
		WP_CLI::error( $ditl_message );
	}

	WP_CLI::warning( $ditl_message . ' (controle desactive par "sans-controle-theme").' );
}

// ---------------------------------------------------------------------------
// Lecture du formulaire anglais source.
// ---------------------------------------------------------------------------

$ditl_post_en = get_post( $ditl_form_en );

if ( ! $ditl_post_en || $ditl_type_form !== $ditl_post_en->post_type ) {
	WP_CLI::error( sprintf( 'Formulaire source %d introuvable (ou pas de type "%s").', $ditl_form_en, $ditl_type_form ) );
}

$ditl_source = json_decode( (string) $ditl_post_en->post_content, true );

if ( ! is_array( $ditl_source ) || ! isset( $ditl_source['fields'] ) || ! is_array( $ditl_source['fields'] ) ) {
	WP_CLI::error( sprintf( 'Formulaire source %d : JSON illisible ou sans champ.', $ditl_form_en ) );
}

// ---------------------------------------------------------------------------
// Construction du formulaire francais.
// ---------------------------------------------------------------------------

$ditl_anomalies = array();
$ditl_forme_fr  = ditl_contact_fr_traduire(
	$ditl_source,
	$ditl_traduction_champs,
	$ditl_traduction_pays,
	$ditl_traduction_reglages,
	$ditl_titre_fr,
	$ditl_anomalies
);

foreach ( $ditl_anomalies as $ditl_anomalie ) {
	WP_CLI::warning( $ditl_anomalie );
}

WP_CLI::log( '--- Formulaire francais ---' );

foreach ( $ditl_forme_fr['fields'] as $ditl_champ ) {
	WP_CLI::log(
		sprintf(
			'  Champ %s (%s) : "%s"',
			isset( $ditl_champ['id'] ) ? (string) $ditl_champ['id'] : '?',
			isset( $ditl_champ['type'] ) ? (string) $ditl_champ['type'] : '?',
			isset( $ditl_champ['label'] ) ? (string) $ditl_champ['label'] : ''
		)
	);
}

WP_CLI::log( sprintf( '  Bouton d\'envoi : "%s"', $ditl_forme_fr['settings']['submit_text'] ) );
WP_CLI::log( '  Notifications : objet traduit, destinataires inchanges.' );

// ---------------------------------------------------------------------------
// Creation ou mise a jour de la publication du formulaire.
// ---------------------------------------------------------------------------

if ( $ditl_form_fr > 0 ) {
	// Sortie de corbeille si une annulation precedente l'y avait mis.
	if ( 'trash' === get_post_status( $ditl_form_fr ) ) {
		if ( $ditl_dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] Formulaire %d : serait sorti de la corbeille.', $ditl_form_fr ) );
		} else {
			wp_untrash_post( $ditl_form_fr );
			wp_update_post(
				array(
					'ID'          => $ditl_form_fr,
					'post_status' => 'publish',
				)
			);
			WP_CLI::log( sprintf( '  Formulaire %d : sorti de la corbeille.', $ditl_form_fr ) );
			$ditl_ecritures++;
		}
	}

	// Le JSON stocke porte l'identifiant reel de la publication.
	$ditl_forme_fr['id'] = (string) $ditl_form_fr;

	$ditl_actuel = json_decode( (string) get_post_field( 'post_content', $ditl_form_fr ), true );

	// Comparaison souple (l'ordre des cles ne compte pas) : un rejeu sans
	// changement de traduction ne doit produire aucune ecriture.
	if ( is_array( $ditl_actuel ) && $ditl_actuel == $ditl_forme_fr ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- comparaison de contenu, insensible a l'ordre des cles et au type scalaire.
		WP_CLI::log( sprintf( '  Formulaire %d : contenu deja a jour, rien a faire.', $ditl_form_fr ) );
	} elseif ( $ditl_dry_run ) {
		WP_CLI::log( sprintf( '  [dry-run] Formulaire %d : contenu serait mis a jour.', $ditl_form_fr ) );
	} else {
		wp_update_post(
			array(
				'ID'           => $ditl_form_fr,
				'post_title'   => $ditl_titre_fr,
				'post_content' => wp_slash( ditl_contact_fr_encoder_formulaire( $ditl_forme_fr ) ),
			)
		);
		WP_CLI::log( sprintf( '  Formulaire %d : contenu mis a jour.', $ditl_form_fr ) );
		$ditl_ecritures++;
	}
} elseif ( $ditl_dry_run ) {
	WP_CLI::log( sprintf( '  [dry-run] Formulaire "%s" : serait cree (publication de type %s).', $ditl_titre_fr, $ditl_type_form ) );
} else {
	// Creation en deux temps : l'identifiant de la publication doit etre
	// recopie dans le JSON (WPForms procede de la meme facon).
	$ditl_form_fr = wp_insert_post(
		array(
			'post_type'      => $ditl_type_form,
			'post_status'    => 'publish',
			'post_title'     => $ditl_titre_fr,
			'post_name'      => $ditl_slug_fr,
			'post_author'    => (int) $ditl_post_en->post_author,
			'post_excerpt'   => (string) $ditl_post_en->post_excerpt,
			'post_content'   => wp_slash( ditl_contact_fr_encoder_formulaire( $ditl_forme_fr ) ),
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		),
		true
	);

	if ( is_wp_error( $ditl_form_fr ) ) {
		WP_CLI::error( sprintf( 'Creation du formulaire francais impossible : %s', $ditl_form_fr->get_error_message() ) );
	}

	$ditl_form_fr        = (int) $ditl_form_fr;
	$ditl_forme_fr['id'] = (string) $ditl_form_fr;

	wp_update_post(
		array(
			'ID'           => $ditl_form_fr,
			'post_content' => wp_slash( ditl_contact_fr_encoder_formulaire( $ditl_forme_fr ) ),
		)
	);

	WP_CLI::log( sprintf( '  Formulaire %d : cree ("%s").', $ditl_form_fr, $ditl_titre_fr ) );
	$ditl_ecritures++;
}

// ---------------------------------------------------------------------------
// Memorisation de l'identifiant (l'environnement suivant en aura un autre).
// ---------------------------------------------------------------------------

if ( $ditl_dry_run && $ditl_form_fr <= 0 ) {
	WP_CLI::log( sprintf( '  [dry-run] Option %s : serait posee sur le formulaire cree.', $ditl_option_form_fr ) );
} elseif ( absint( get_option( $ditl_option_form_fr, 0 ) ) === $ditl_form_fr ) {
	WP_CLI::log( sprintf( '  Option %s : deja a %d, rien a faire.', $ditl_option_form_fr, $ditl_form_fr ) );
} elseif ( $ditl_dry_run ) {
	WP_CLI::log( sprintf( '  [dry-run] Option %s : serait posee a %d.', $ditl_option_form_fr, $ditl_form_fr ) );
} else {
	update_option( $ditl_option_form_fr, $ditl_form_fr, false );
	WP_CLI::log( sprintf( '  Option %s : posee a %d.', $ditl_option_form_fr, $ditl_form_fr ) );
	$ditl_ecritures++;
}

// ---------------------------------------------------------------------------
// Rattachement du formulaire a la page Contact francaise.
// ---------------------------------------------------------------------------

WP_CLI::log( '--- Pages Contact ---' );

$ditl_meta_fr = ditl_contact_fr_lire_meta( $ditl_page_fr, $ditl_meta_formulaire );

if ( $ditl_dry_run && $ditl_form_fr <= 0 ) {
	WP_CLI::log( sprintf( '  [dry-run] Page %d : form_id %d serait remplace par celui du formulaire cree.', $ditl_page_fr, $ditl_meta_fr['form_id'] ) );
} elseif ( $ditl_meta_fr['form_id'] === $ditl_form_fr ) {
	WP_CLI::log( sprintf( '  Page %d (FR) : deja rattachee au formulaire %d, rien a faire.', $ditl_page_fr, $ditl_form_fr ) );
} elseif ( $ditl_dry_run ) {
	WP_CLI::log( sprintf( '  [dry-run] Page %d (FR) : form_id passerait de %d a %d.', $ditl_page_fr, $ditl_meta_fr['form_id'], $ditl_form_fr ) );
} else {
	update_post_meta( $ditl_page_fr, $ditl_meta_formulaire, wp_slash( ditl_contact_fr_encoder_meta( $ditl_meta_fr['titre'], $ditl_form_fr ) ) );
	WP_CLI::log( sprintf( '  Page %d (FR) : form_id passe de %d a %d.', $ditl_page_fr, $ditl_meta_fr['form_id'], $ditl_form_fr ) );
	$ditl_ecritures++;
}

// Page anglaise : simple controle, elle ne doit surtout pas bouger. La meta
// _ditl_contact_formulaire est protegee (prefixe _) et n'est donc pas
// synchronisee entre traductions par Polylang ; le controle le verifie.
$ditl_meta_en = ditl_contact_fr_lire_meta( $ditl_page_en, $ditl_meta_formulaire );

if ( $ditl_form_en === $ditl_meta_en['form_id'] ) {
	WP_CLI::log( sprintf( '  Page %d (EN) : toujours rattachee au formulaire %d, inchangee.', $ditl_page_en, $ditl_form_en ) );
} else {
	WP_CLI::warning( sprintf( 'Page %d (EN) : rattachee au formulaire %d au lieu de %d attendu, a verifier.', $ditl_page_en, $ditl_meta_en['form_id'], $ditl_form_en ) );
}

// ---------------------------------------------------------------------------
// Bilan.
// ---------------------------------------------------------------------------

WP_CLI::log( '' );

if ( $ditl_dry_run ) {
	WP_CLI::log( 'Simulation terminee.' );
} else {
	WP_CLI::log( sprintf( 'Traduction terminee (%d ecriture(s) lors de ce passage).', $ditl_ecritures ) );
	WP_CLI::log( 'Purger le cache de page avant la recette (wp-content/cache/all).' );
	WP_CLI::log( 'Le formulaire anglais 6 est inchange et reste celui de la page EN.' );
}
