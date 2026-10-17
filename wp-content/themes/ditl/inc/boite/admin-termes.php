<?php
/**
 * Ecrans des referentiels de la boite a outils (les taxonomies du registre
 * ditl_boite_taxonomies() : metiers et taches, difficultes et empechements,
 * environnements, types et sous-types de compensation).
 *
 * - Champs d'ajout et de modification : code neutre, ordre d'affichage,
 *   couleur du badge (environnements, avec apercu et contraste calcule).
 * - Code : controle d'unicite dans la taxonomie et dans chaque langue
 *   concernee (celle du terme et celles de ses traductions, qui partagent
 *   le code) ; un code en doublon n'est pas enregistre et un avertissement
 *   est affiche. Code vide a la creation : repris de la traduction liee,
 *   sinon deduit du libelle (ditl_boite_completer_code_terme, modele.php).
 * - Liste des termes : colonnes Code, Ordre (triable), Couleur.
 * - Niveaux : la liste "Parent" ne propose que le premier niveau (taxonomies
 *   a niveaux) et disparait pour les listes simples ; controle serveur sur
 *   les envois des formulaires de l'admin.
 * - Aide en tete de chaque ecran (description du registre).
 *
 * Ordre d'ecriture et Polylang : les metas neutres (code, ordre, couleur)
 * ne sont PAS synchronisees par Polylang (filtre pll_copy_term_metas de
 * modele.php : copie a la creation d'une traduction seulement). Les champs
 * sont ecrits a la priorite 950 de create_term / edit_term (apres
 * l'affectation de la langue et des traductions par Polylang, priorite
 * 900), avec repli sur les valeurs du groupe de traductions pour un champ
 * vide, puis recopies explicitement sur les traductions
 * (ditl_boite_termes_propager). Un second passage (created_term /
 * edited_term) couvre une extension qui affecterait la langue plus tard
 * (WPML).
 *
 * Compatibilite requise : PHP 7.4 (production actuelle) et PHP 8.x (cible).
 *
 * @package DiTL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accroche les champs, colonnes et aides de chaque taxonomie du registre.
 *
 * Sur admin_init : le registre utilise des chaines traduites, a ne pas
 * appeler avant le chargement des traductions.
 */
function ditl_boite_termes_init() {
	foreach ( ditl_boite_taxonomies() as $taxonomie => $definition ) {
		add_action( "{$taxonomie}_pre_add_form", 'ditl_boite_termes_aide_liste' );
		add_action( "{$taxonomie}_term_edit_form_top", 'ditl_boite_termes_aide_edition', 10, 2 );
		add_action( "{$taxonomie}_add_form_fields", 'ditl_boite_termes_champs_ajout' );
		add_action( "{$taxonomie}_edit_form_fields", 'ditl_boite_termes_champs_edition', 10, 2 );

		add_filter( "manage_edit-{$taxonomie}_columns", $definition['couleur'] ? 'ditl_boite_termes_colonnes_couleur' : 'ditl_boite_termes_colonnes' );
		add_filter( "manage_edit-{$taxonomie}_sortable_columns", 'ditl_boite_termes_colonnes_triables' );
		add_filter( "manage_{$taxonomie}_custom_column", 'ditl_boite_termes_colonne_contenu', 10, 3 );
	}
}
add_action( 'admin_init', 'ditl_boite_termes_init' );

/* -------------------------------------------------------------------------
 * Aide en tete des ecrans.
 * ---------------------------------------------------------------------- */

/**
 * Texte d'aide d'un ecran de taxonomie.
 *
 * @param string $taxonomie Taxonomie de la boite a outils.
 * @return string[] Paragraphes d'aide (texte simple).
 */
function ditl_boite_termes_textes_aide( $taxonomie ) {
	$taxonomies = ditl_boite_taxonomies();
	$textes     = array();

	if ( isset( $taxonomies[ $taxonomie ] ) && '' !== $taxonomies[ $taxonomie ]['aide'] ) {
		$textes[] = $taxonomies[ $taxonomie ]['aide'];
	}

	$textes[] = __( 'Chaque élément est à créer dans toutes les langues du site et à relier à ses traductions : elles partagent le même code, l\'ordre et la couleur.', 'ditl' );

	return $textes;
}

/**
 * Affiche l'aide en tete de la liste des termes (au-dessus du formulaire
 * d'ajout).
 *
 * @param string $taxonomie Taxonomie affichee.
 */
function ditl_boite_termes_aide_liste( $taxonomie ) {
	echo '<div class="ditl-boite-aide-ecran">';

	foreach ( ditl_boite_termes_textes_aide( $taxonomie ) as $texte ) {
		echo '<p>' . esc_html( $texte ) . '</p>';
	}

	echo '</div>';
}

/**
 * Affiche l'aide en tete du formulaire de modification d'un terme.
 *
 * @param WP_Term $terme     Terme modifie.
 * @param string  $taxonomie Taxonomie du terme.
 */
function ditl_boite_termes_aide_edition( $terme, $taxonomie ) {
	ditl_boite_termes_aide_liste( $taxonomie );
}

/* -------------------------------------------------------------------------
 * Valeurs partagees entre traductions.
 * ---------------------------------------------------------------------- */

/**
 * IDs des traductions d'un terme (le terme exclu).
 *
 * @param int $term_id ID du terme.
 * @return int[] Tableau [code de langue => ID].
 */
function ditl_boite_termes_traductions( $term_id ) {
	$traductions = array();

	foreach ( ditl_ml_traductions_terme( $term_id ) as $lang => $traduction_id ) {
		if ( (int) $traduction_id > 0 && (int) $traduction_id !== (int) $term_id ) {
			$traductions[ (string) $lang ] = (int) $traduction_id;
		}
	}

	return $traductions;
}

/**
 * Valeur d'une meta neutre pour un terme : la sienne si elle existe, sinon
 * celle de la premiere traduction qui en a une.
 *
 * @param int    $term_id ID du terme (0 : aucune valeur).
 * @param string $cle     Cle de meta (_ditl_code, _ditl_ordre, _ditl_couleur).
 * @return string|null Valeur brute, ou null si ni le terme ni ses
 *                     traductions n'en ont.
 */
function ditl_boite_termes_valeur_groupe( $term_id, $cle ) {
	$term_id = absint( $term_id );

	if ( $term_id <= 0 ) {
		return null;
	}

	foreach ( array_merge( array( $term_id ), array_values( ditl_boite_termes_traductions( $term_id ) ) ) as $id ) {
		if ( metadata_exists( 'term', $id, $cle ) ) {
			$valeur = get_term_meta( $id, $cle, true );

			if ( is_scalar( $valeur ) && ( '_ditl_ordre' === $cle || '' !== (string) $valeur ) ) {
				return (string) $valeur;
			}
		}
	}

	return null;
}

/**
 * Code porte par les traductions d'un terme (le terme exclu).
 *
 * @param int $term_id ID du terme.
 * @return string Code (vide si aucune traduction n'en a).
 */
function ditl_boite_termes_code_traductions( $term_id ) {
	foreach ( ditl_boite_termes_traductions( $term_id ) as $traduction_id ) {
		$code = ditl_boite_code_terme( $traduction_id );

		if ( '' !== $code ) {
			return $code;
		}
	}

	return '';
}

/**
 * Termes qui utilisent deja un code, dans la taxonomie et dans les langues
 * du terme et de ses traductions (le terme et ses traductions exclus).
 *
 * @param string $taxonomie Taxonomie de la boite a outils.
 * @param string $code      Code neutre (deja nettoye).
 * @param int    $term_id   ID du terme a qui le code est destine.
 * @return WP_Term[] Termes en conflit (vide : code disponible).
 */
function ditl_boite_termes_doublons_code( $taxonomie, $code, $term_id ) {
	$code = ditl_boite_sanitize_code( $code );

	if ( '' === $code || ! ditl_boite_est_taxonomie( $taxonomie ) ) {
		return array();
	}

	$traductions = ditl_boite_termes_traductions( $term_id );
	$exclus      = array_merge( array( (int) $term_id ), array_values( $traductions ) );
	$langues     = array_keys( $traductions );
	$langue      = ditl_ml_langue_terme( $term_id );

	if ( '' !== $langue ) {
		$langues[] = $langue;
	}

	$langues = array_values( array_unique( array_filter( array_map( 'strval', $langues ) ) ) );

	// Langue inconnue : controle sur toutes les langues (plus strict).
	if ( array() === $langues ) {
		$langues = array( '' );
	}

	$extension = ditl_ml_extension();
	$conflits  = array();

	foreach ( $langues as $lang ) {
		$args = array(
			'taxonomy'   => $taxonomie,
			'hide_empty' => false,
			'number'     => 20,
			'exclude'    => $exclus,
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- referentiel de quelques dizaines de termes, ecran d'admin.
				array(
					'key'   => '_ditl_code',
					'value' => $code,
				),
			),
		);

		// Polylang : "lang" vide = toutes les langues.
		if ( 'polylang' === $extension ) {
			$args['lang'] = $lang;
		}

		$termes = '' === $lang ? get_terms( $args ) : ditl_ml_avec_langue(
			$lang,
			static function () use ( $args ) {
				return get_terms( $args );
			}
		);

		foreach ( is_array( $termes ) ? $termes : array() as $terme ) {
			if ( ! $terme instanceof WP_Term ) {
				continue;
			}

			// Double controle de la langue (requete non filtree par une
			// extension inconnue).
			if ( '' !== $lang && '' !== $extension && ditl_ml_langue_terme( $terme->term_id ) !== $lang ) {
				continue;
			}

			$conflits[ $terme->term_id ] = $terme;
		}
	}

	return array_values( $conflits );
}

/**
 * Message d'avertissement pour un code deja utilise.
 *
 * @param string    $code     Code refuse.
 * @param WP_Term[] $conflits Termes qui l'utilisent.
 * @param string    $suite    Phrase de conclusion.
 * @return string Message en texte simple.
 */
function ditl_boite_termes_message_doublon( $code, $conflits, $suite ) {
	$noms = array();

	foreach ( array_slice( $conflits, 0, 3 ) as $terme ) {
		$lang   = ditl_ml_langue_terme( $terme->term_id );
		$noms[] = '« ' . wp_specialchars_decode( $terme->name, ENT_QUOTES ) . ' »' . ( '' !== $lang ? ' (' . strtoupper( $lang ) . ')' : '' );
	}

	return sprintf(
		/* translators: 1 : code, 2 : liste des elements qui l'utilisent, 3 : conclusion. */
		__( 'Le code « %1$s » est déjà utilisé par %2$s dans cette liste. %3$s', 'ditl' ),
		$code,
		implode( ', ', $noms ),
		$suite
	);
}

/* -------------------------------------------------------------------------
 * Champs des formulaires.
 * ---------------------------------------------------------------------- */

/**
 * Texte du contraste d'un badge.
 *
 * @param string $couleur Couleur de fond #rrggbb (vide : aucune).
 * @return string Texte simple.
 */
function ditl_boite_termes_texte_contraste( $couleur ) {
	if ( '' === $couleur ) {
		return __( 'Aucune couleur : le badge garde le style par défaut.', 'ditl' );
	}

	$texte = ditl_boite_couleur_texte( $couleur );
	$noms  = array(
		'#1e448f' => __( 'texte bleu de la charte', 'ditl' ),
		'#ffffff' => __( 'texte blanc', 'ditl' ),
		'#000000' => __( 'texte noir', 'ditl' ),
	);

	return sprintf(
		/* translators: 1 : ratio de contraste (ex. 8,59), 2 : couleur du texte (ex. texte noir). */
		__( 'Contraste du texte : %1$s:1 (%2$s, choisi automatiquement).', 'ditl' ),
		number_format_i18n( ditl_boite_ratio_contraste( $couleur, $texte ), 2 ),
		isset( $noms[ $texte ] ) ? $noms[ $texte ] : $texte
	);
}

/**
 * Affiche le champ couleur (saisie hexadecimale, palette, apercu du badge).
 *
 * @param string $couleur Couleur actuelle (vide : aucune).
 * @param string $libelle Texte de l'apercu du badge.
 */
function ditl_boite_termes_champ_couleur( $couleur, $libelle ) {
	$couleur = ditl_boite_sanitize_couleur( $couleur );
	$style   = '' !== $couleur ? 'background-color:' . $couleur . ';color:' . ditl_boite_couleur_texte( $couleur ) . ';' : '';
	?>
	<span class="ditl-boite-couleur">
		<input type="text" id="ditl-couleur" name="ditl_couleur" class="ditl-boite-couleur-hex code" value="<?php echo esc_attr( $couleur ); ?>" maxlength="7" placeholder="#00b0cf" autocomplete="off" spellcheck="false" aria-describedby="ditl-couleur-description ditl-couleur-contraste" />
		<input type="color" class="ditl-boite-couleur-palette" value="<?php echo esc_attr( '' !== $couleur ? $couleur : '#ffffff' ); ?>" aria-label="<?php esc_attr_e( 'Choisir la couleur dans la palette', 'ditl' ); ?>" hidden />
	</span>
	<span class="ditl-boite-apercu">
		<span class="ditl-boite-badge<?php echo '' === $couleur ? ' ditl-boite-badge-neutre' : ''; ?>" style="<?php echo esc_attr( $style ); ?>"><?php echo esc_html( $libelle ); ?></span>
		<span class="ditl-boite-contraste" id="ditl-couleur-contraste" aria-live="polite"><?php echo esc_html( ditl_boite_termes_texte_contraste( $couleur ) ); ?></span>
	</span>
	<?php
}

/**
 * Aides des champs (partagees entre l'ajout et la modification).
 *
 * @param string $champ    "code", "ordre" ou "couleur".
 * @param bool   $creation True pour le formulaire d'ajout.
 * @return string Texte simple.
 */
function ditl_boite_termes_aide_champ( $champ, $creation ) {
	if ( 'code' === $champ ) {
		$aide = __( 'Identifiant neutre, identique dans toutes les langues, utilisé par l\'import et les adresses du questionnaire. Minuscules, chiffres, « _ » et « - ».', 'ditl' );

		return $aide . ' ' . ( $creation
			? __( 'Laisser vide pour le remplir automatiquement (code de la traduction liée, sinon déduit du libellé).', 'ditl' )
			: __( 'À ne pas modifier une fois le site en ligne : les adresses déjà partagées et les statistiques en dépendent. Le changement s\'applique aussi aux traductions.', 'ditl' ) );
	}

	if ( 'ordre' === $champ ) {
		return __( 'Rang dans les listes du questionnaire et des filtres : les plus petits nombres en premier, à égalité par ordre alphabétique. 0 par défaut.', 'ditl' );
	}

	return __( 'Code hexadécimal, ex. #00b0cf. Laisser vide : badge sans couleur. La couleur du texte (bleu de la charte, noir ou blanc) est choisie automatiquement pour rester lisible (contraste d\'au moins 4,5:1).', 'ditl' );
}

/**
 * Affiche les champs du formulaire d'ajout d'un terme.
 *
 * @param string $taxonomie Taxonomie affichee.
 */
function ditl_boite_termes_champs_ajout( $taxonomie ) {
	$taxonomies = ditl_boite_taxonomies();

	if ( ! isset( $taxonomies[ $taxonomie ] ) ) {
		return;
	}

	// Pas de champ "referer" en double : le formulaire en porte deja un.
	// Nonce lie au terme (0 a la creation), comme les autres metaboxes du theme.
	wp_nonce_field( 'ditl_boite_terme_0', 'ditl_boite_terme_nonce', false );
	?>
	<div class="form-field term-ditl-code-wrap">
		<label for="ditl-code"><?php esc_html_e( 'Code', 'ditl' ); ?></label>
		<input type="text" id="ditl-code" name="ditl_code" class="code" value="" maxlength="100" placeholder="<?php esc_attr_e( 'automatique', 'ditl' ); ?>" autocomplete="off" spellcheck="false" aria-describedby="ditl-code-description" />
		<p class="description" id="ditl-code-description"><?php echo esc_html( ditl_boite_termes_aide_champ( 'code', true ) ); ?></p>
	</div>
	<div class="form-field term-ditl-ordre-wrap">
		<label for="ditl-ordre"><?php esc_html_e( 'Ordre d\'affichage', 'ditl' ); ?></label>
		<input type="number" id="ditl-ordre" name="ditl_ordre" class="small-text" value="" step="1" min="-9999" max="9999" placeholder="0" aria-describedby="ditl-ordre-description" />
		<p class="description" id="ditl-ordre-description"><?php echo esc_html( ditl_boite_termes_aide_champ( 'ordre', true ) ); ?></p>
	</div>
	<?php if ( $taxonomies[ $taxonomie ]['couleur'] ) : ?>
		<div class="form-field term-ditl-couleur-wrap">
			<label for="ditl-couleur"><?php esc_html_e( 'Couleur du badge', 'ditl' ); ?></label>
			<?php ditl_boite_termes_champ_couleur( '', __( 'Exemple', 'ditl' ) ); ?>
			<p class="description" id="ditl-couleur-description"><?php echo esc_html( ditl_boite_termes_aide_champ( 'couleur', true ) ); ?></p>
		</div>
	<?php endif; ?>
	<?php
}

/**
 * Affiche les champs du formulaire de modification d'un terme.
 *
 * Un terme a qui il manque une valeur affiche celle de ses traductions :
 * l'enregistrement la lui donne (voir l'en-tete, synchronisation Polylang).
 *
 * @param WP_Term $terme     Terme modifie.
 * @param string  $taxonomie Taxonomie du terme.
 */
function ditl_boite_termes_champs_edition( $terme, $taxonomie ) {
	$taxonomies = ditl_boite_taxonomies();

	if ( ! $terme instanceof WP_Term || ! isset( $taxonomies[ $taxonomie ] ) ) {
		return;
	}

	$code    = ditl_boite_termes_valeur_groupe( $terme->term_id, '_ditl_code' );
	$ordre   = ditl_boite_termes_valeur_groupe( $terme->term_id, '_ditl_ordre' );
	$couleur = ditl_boite_termes_valeur_groupe( $terme->term_id, '_ditl_couleur' );

	?>
	<tr class="form-field term-ditl-code-wrap">
		<th scope="row"><label for="ditl-code"><?php esc_html_e( 'Code', 'ditl' ); ?></label></th>
		<td>
			<?php wp_nonce_field( 'ditl_boite_terme_' . (int) $terme->term_id, 'ditl_boite_terme_nonce', false ); ?>
			<input type="text" id="ditl-code" name="ditl_code" class="code" value="<?php echo esc_attr( null !== $code ? ditl_boite_sanitize_code( $code ) : '' ); ?>" maxlength="100" autocomplete="off" spellcheck="false" aria-describedby="ditl-code-description" />
			<p class="description" id="ditl-code-description"><?php echo esc_html( ditl_boite_termes_aide_champ( 'code', false ) ); ?></p>
		</td>
	</tr>
	<tr class="form-field term-ditl-ordre-wrap">
		<th scope="row"><label for="ditl-ordre"><?php esc_html_e( 'Ordre d\'affichage', 'ditl' ); ?></label></th>
		<td>
			<input type="number" id="ditl-ordre" name="ditl_ordre" class="small-text" value="<?php echo esc_attr( (string) ditl_boite_sanitize_ordre( null !== $ordre ? $ordre : 0 ) ); ?>" step="1" min="-9999" max="9999" aria-describedby="ditl-ordre-description" />
			<p class="description" id="ditl-ordre-description"><?php echo esc_html( ditl_boite_termes_aide_champ( 'ordre', false ) ); ?></p>
		</td>
	</tr>
	<?php if ( $taxonomies[ $taxonomie ]['couleur'] ) : ?>
		<tr class="form-field term-ditl-couleur-wrap">
			<th scope="row"><label for="ditl-couleur"><?php esc_html_e( 'Couleur du badge', 'ditl' ); ?></label></th>
			<td>
				<?php ditl_boite_termes_champ_couleur( null !== $couleur ? $couleur : '', $terme->name ); ?>
				<p class="description" id="ditl-couleur-description"><?php echo esc_html( ditl_boite_termes_aide_champ( 'couleur', false ) ); ?></p>
			</td>
		</tr>
	<?php endif; ?>
	<?php
}

/* -------------------------------------------------------------------------
 * Enregistrement.
 * ---------------------------------------------------------------------- */

/**
 * Indique si le nonce des champs de terme est poste et valide.
 *
 * Le nonce est lie au terme modifie (tag_ID poste par le formulaire de
 * modification), ou a 0 pour le formulaire d'ajout.
 *
 * @return bool True si la requete vient d'un formulaire de terme de la boite.
 */
function ditl_boite_termes_nonce_valide() {
	if ( ! isset( $_POST['ditl_boite_terme_nonce'] ) || ! is_string( $_POST['ditl_boite_terme_nonce'] ) ) {
		return false;
	}

	$term_id = isset( $_POST['tag_ID'] ) && is_scalar( $_POST['tag_ID'] ) ? absint( wp_unslash( $_POST['tag_ID'] ) ) : 0;

	return false !== wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ditl_boite_terme_nonce'] ) ), 'ditl_boite_terme_' . $term_id );
}

/**
 * Lit un champ poste du formulaire de terme.
 *
 * Le nonce est verifie en amont (ditl_boite_termes_nonce_valide).
 *
 * @param string $champ Nom du champ.
 * @return string|null Valeur (sans barres obliques), ou null si absent ou
 *                     non scalaire.
 */
function ditl_boite_termes_champ_poste( $champ ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verifie par l'appelant.
	if ( ! isset( $_POST[ $champ ] ) || ! is_scalar( $_POST[ $champ ] ) ) {
		return null;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verifie par l'appelant.
	return trim( (string) wp_unslash( $_POST[ $champ ] ) );
}

/**
 * Enregistre le code d'un terme.
 *
 * @param int    $term_id   ID du terme.
 * @param string $taxonomie Taxonomie du terme.
 * @param bool   $creation  True a la creation du terme.
 */
function ditl_boite_termes_enregistrer_code( $term_id, $taxonomie, $creation ) {
	$saisie = ditl_boite_termes_champ_poste( 'ditl_code' );

	if ( null === $saisie ) {
		return;
	}

	$code_saisi   = ditl_boite_sanitize_code( $saisie );
	$code_actuel  = ditl_boite_code_terme( $term_id );
	$code_traduit = ditl_boite_termes_code_traductions( $term_id );

	if ( '' !== $saisie && $code_saisi !== $saisie ) {
		ditl_boite_admin_avertir(
			sprintf(
				/* translators: 1 : code saisi, 2 : code retenu. */
				__( 'Le code « %1$s » a été normalisé en « %2$s » (minuscules, chiffres, « _ » et « - » uniquement).', 'ditl' ),
				$saisie,
				$code_saisi
			)
		);
	}

	// Traduction d'un element qui a deja un code : le code du groupe
	// s'impose (meme code dans toutes les langues).
	if ( '' !== $code_traduit && ( $creation || '' === $code_actuel ) ) {
		if ( '' !== $code_saisi && $code_saisi !== $code_traduit ) {
			ditl_boite_admin_avertir(
				sprintf(
					/* translators: 1 : code saisi, 2 : code des traductions. */
					__( 'Le code « %1$s » n\'a pas été retenu : une traduction porte le même code que l\'élément d\'origine (« %2$s »). Pour changer le code de tout le groupe, modifiez-le une fois l\'élément créé.', 'ditl' ),
					$code_saisi,
					$code_traduit
				)
			);
		}

		$conflits = ditl_boite_termes_doublons_code( $taxonomie, $code_traduit, $term_id );

		if ( array() !== $conflits ) {
			ditl_boite_admin_avertir( ditl_boite_termes_message_doublon( $code_traduit, $conflits, __( 'Il a tout de même été repris des traductions : corrigez le code de l\'un des deux éléments.', 'ditl' ) ) );
		}

		if ( $code_traduit !== $code_actuel ) {
			update_term_meta( $term_id, '_ditl_code', $code_traduit );
		}

		return;
	}

	if ( '' === $code_saisi ) {
		// Creation : rempli ensuite par ditl_boite_completer_code_terme.
		// Modification : un code ne s'efface pas (il identifie l'element
		// dans les adresses, l'import et les statistiques).
		if ( ! $creation && '' !== $code_actuel ) {
			ditl_boite_admin_avertir(
				sprintf(
					/* translators: %s : code conserve. */
					__( 'Le code ne peut pas être vidé : le code « %s » a été conservé.', 'ditl' ),
					$code_actuel
				)
			);
		}

		return;
	}

	if ( $code_saisi === $code_actuel ) {
		return;
	}

	$conflits = ditl_boite_termes_doublons_code( $taxonomie, $code_saisi, $term_id );

	if ( array() !== $conflits ) {
		ditl_boite_admin_avertir(
			ditl_boite_termes_message_doublon(
				$code_saisi,
				$conflits,
				$creation
					? __( 'Il n\'a pas été enregistré : un code automatique a été attribué, à vérifier dans la colonne Code.', 'ditl' )
					: __( 'Il n\'a pas été enregistré : l\'ancien code est conservé.', 'ditl' )
			)
		);

		return;
	}

	update_term_meta( $term_id, '_ditl_code', $code_saisi );
}

/**
 * Enregistre l'ordre et la couleur d'un terme.
 *
 * Champ vide : valeur du groupe de traductions si elle existe (la valeur
 * est ensuite recopiee sur tout le groupe par ditl_boite_termes_propager),
 * sinon 0 pour l'ordre et aucune couleur.
 *
 * @param int    $term_id   ID du terme.
 * @param string $taxonomie Taxonomie du terme.
 * @param bool   $creation  True a la creation du terme.
 */
function ditl_boite_termes_enregistrer_ordre_couleur( $term_id, $taxonomie, $creation ) {
	$taxonomies = ditl_boite_taxonomies();
	$ordre      = ditl_boite_termes_champ_poste( 'ditl_ordre' );

	if ( null !== $ordre ) {
		if ( '' === $ordre ) {
			$groupe = ditl_boite_termes_valeur_groupe( $term_id, '_ditl_ordre' );
			$ordre  = null !== $groupe ? $groupe : '0';
		} elseif ( ! is_numeric( $ordre ) ) {
			ditl_boite_admin_avertir(
				sprintf(
					/* translators: %s : valeur saisie. */
					__( 'Ordre d\'affichage non valide (« %s ») : 0 a été enregistré.', 'ditl' ),
					$ordre
				)
			);
		}

		update_term_meta( $term_id, '_ditl_ordre', ditl_boite_sanitize_ordre( $ordre ) );
	}

	if ( empty( $taxonomies[ $taxonomie ]['couleur'] ) ) {
		return;
	}

	$saisie = ditl_boite_termes_champ_poste( 'ditl_couleur' );

	if ( null === $saisie ) {
		return;
	}

	// Code saisi sans diese (ex. "00b0cf") : complete.
	if ( preg_match( '/^[0-9a-f]{3}([0-9a-f]{3})?$/i', $saisie ) ) {
		$saisie = '#' . $saisie;
	}

	$couleur = ditl_boite_sanitize_couleur( $saisie );

	if ( '' !== $saisie && '' === $couleur ) {
		ditl_boite_admin_avertir(
			sprintf(
				/* translators: %s : valeur saisie. */
				__( 'Couleur non valide (« %s ») : saisir un code hexadécimal, ex. #00b0cf. La couleur n\'a pas été modifiée.', 'ditl' ),
				$saisie
			)
		);

		$saisie = '';

		if ( ! $creation ) {
			return;
		}
	}

	if ( '' === $saisie && $creation ) {
		$groupe = ditl_boite_termes_valeur_groupe( $term_id, '_ditl_couleur' );

		if ( null === $groupe ) {
			return;
		}

		$couleur = ditl_boite_sanitize_couleur( $groupe );
	}

	update_term_meta( $term_id, '_ditl_couleur', $couleur );
}

/**
 * Indique si la requete est le formulaire de ce terme : taxonomie postee
 * identique et, pour une modification, ID du terme poste (tag_ID). Ecarte
 * tout autre terme enregistre pendant la meme requete.
 *
 * Le nonce est verifie en amont (ditl_boite_termes_nonce_valide).
 *
 * @param int    $term_id   ID du terme enregistre.
 * @param string $taxonomie Taxonomie du terme.
 * @param bool   $creation  True a la creation du terme.
 * @return bool True si le terme est celui du formulaire.
 */
function ditl_boite_termes_est_terme_du_formulaire( $term_id, $taxonomie, $creation ) {
	if ( ditl_boite_termes_champ_poste( 'taxonomy' ) !== $taxonomie ) {
		return false;
	}

	return $creation || (int) ditl_boite_termes_champ_poste( 'tag_ID' ) === (int) $term_id;
}

/**
 * Enregistre les champs d'un terme (code, ordre, couleur).
 *
 * Deux passages par operation (voir l'en-tete) : le premier (create_term /
 * edit_term, priorite 950) enregistre des que la langue du terme est
 * connue ; le second (created_term / edited_term) ne sert que si le premier
 * a du attendre.
 *
 * @param int    $term_id   ID du terme.
 * @param string $taxonomie Taxonomie du terme.
 * @param bool   $creation  True a la creation du terme.
 */
function ditl_boite_termes_enregistrer( $term_id, $taxonomie, $creation ) {
	static $faits = array();

	$term_id         = absint( $term_id );
	$cle             = $term_id . ( $creation ? ':creation' : ':modification' );
	$premier_passage = doing_action( 'create_term' ) || doing_action( 'edit_term' );

	// Second passage d'une operation deja traitee au premier.
	if ( ! $premier_passage && isset( $faits[ $cle ] ) ) {
		unset( $faits[ $cle ] );

		return;
	}

	if ( $term_id <= 0 || ! ditl_boite_est_taxonomie( $taxonomie ) ) {
		return;
	}

	if ( ! ditl_boite_termes_nonce_valide() || ! ditl_boite_termes_est_terme_du_formulaire( $term_id, $taxonomie, $creation ) || ! current_user_can( 'edit_term', $term_id ) ) {
		return;
	}

	// Premier passage (avant la synchronisation Polylang) : attendre le
	// second si l'extension multilingue n'a pas encore affecte la langue.
	if ( $premier_passage ) {
		if ( '' !== ditl_ml_extension() && '' === ditl_ml_langue_terme( $term_id ) ) {
			return;
		}

		$faits[ $cle ] = true;
	}

	ditl_boite_termes_enregistrer_code( $term_id, $taxonomie, $creation );
	ditl_boite_termes_enregistrer_ordre_couleur( $term_id, $taxonomie, $creation );
	ditl_boite_termes_propager( $term_id, $taxonomie );
}

/**
 * Recopie le code, l'ordre et la couleur d'un terme sur ses traductions.
 *
 * Ces valeurs sont identiques dans toutes les langues. La synchronisation
 * automatique de Polylang est coupee (voir ditl_boite_polylang_metas_termes
 * dans modele.php) : la recopie se fait ici, depuis les formulaires de la
 * boite uniquement, valeur par valeur. Un code vide n'est jamais recopie ;
 * une couleur videe volontairement l'est (retrait dans toutes les langues).
 * Le code enregistre a deja ete controle dans les langues des traductions
 * (ditl_boite_termes_doublons_code).
 *
 * @param int    $term_id   ID du terme enregistre.
 * @param string $taxonomie Taxonomie du terme.
 */
function ditl_boite_termes_propager( $term_id, $taxonomie ) {
	$taxonomies = ditl_boite_taxonomies();
	$cles       = array( '_ditl_code', '_ditl_ordre' );

	if ( ! empty( $taxonomies[ $taxonomie ]['couleur'] ) ) {
		$cles[] = '_ditl_couleur';
	}

	foreach ( ditl_boite_termes_traductions( $term_id ) as $traduction_id ) {
		foreach ( $cles as $cle ) {
			if ( ! metadata_exists( 'term', $term_id, $cle ) ) {
				continue;
			}

			$valeur = (string) get_term_meta( $term_id, $cle, true );

			if ( '_ditl_code' === $cle && '' === $valeur ) {
				continue;
			}

			if ( ! metadata_exists( 'term', $traduction_id, $cle ) || (string) get_term_meta( $traduction_id, $cle, true ) !== $valeur ) {
				update_term_meta( $traduction_id, $cle, $valeur );
			}
		}
	}
}

/**
 * Creation d'un terme (create_term 950, puis created_term 20).
 *
 * @param int    $term_id   ID du terme.
 * @param int    $tt_id     ID term_taxonomy.
 * @param string $taxonomie Taxonomie.
 */
function ditl_boite_termes_creation( $term_id, $tt_id, $taxonomie ) {
	ditl_boite_termes_enregistrer( $term_id, $taxonomie, true );
}
add_action( 'create_term', 'ditl_boite_termes_creation', 950, 3 );
add_action( 'created_term', 'ditl_boite_termes_creation', 20, 3 );

/**
 * Modification d'un terme (edit_term 950, puis edited_term 20).
 *
 * @param int    $term_id   ID du terme.
 * @param int    $tt_id     ID term_taxonomy.
 * @param string $taxonomie Taxonomie.
 */
function ditl_boite_termes_modification( $term_id, $tt_id, $taxonomie ) {
	ditl_boite_termes_enregistrer( $term_id, $taxonomie, false );
}
add_action( 'edit_term', 'ditl_boite_termes_modification', 950, 3 );
add_action( 'edited_term', 'ditl_boite_termes_modification', 20, 3 );

/**
 * Verifie, apres le remplissage automatique, que le code d'un terme cree
 * depuis les formulaires de la boite n'est pas deja utilise (code deduit du
 * libelle).
 *
 * @param int    $term_id   ID du terme.
 * @param int    $tt_id     ID term_taxonomy.
 * @param string $taxonomie Taxonomie.
 * @param bool   $maj       True pour une mise a jour.
 */
function ditl_boite_termes_controle_code_final( $term_id, $tt_id, $taxonomie, $maj = false ) {
	if ( $maj || ! ditl_boite_est_taxonomie( $taxonomie ) || ! ditl_boite_termes_nonce_valide() || ! ditl_boite_termes_est_terme_du_formulaire( $term_id, $taxonomie, true ) ) {
		return;
	}

	$code = ditl_boite_code_terme( $term_id );

	if ( '' === $code || '' !== ditl_boite_termes_code_traductions( $term_id ) ) {
		return;
	}

	$conflits = ditl_boite_termes_doublons_code( $taxonomie, $code, $term_id );

	if ( array() !== $conflits ) {
		ditl_boite_admin_avertir( ditl_boite_termes_message_doublon( $code, $conflits, __( 'Ce code a été déduit du libellé : modifiez-le sur l\'un des deux éléments.', 'ditl' ) ) );
	}
}
add_action( 'saved_term', 'ditl_boite_termes_controle_code_final', 1000, 4 );

/**
 * Remplace le message de succes de l'ajout AJAX d'un terme par les
 * avertissements de la requete (le formulaire d'ajout ne recharge pas la
 * page : le script d'administration affiche ce message).
 *
 * @param array $messages Messages des ecrans de termes.
 * @return array Messages modifies.
 */
function ditl_boite_termes_messages_ajout( $messages ) {
	$avis = ditl_boite_admin_avis_requete();

	if ( array() === $avis || ! ditl_boite_admin_est_ajout_terme_ajax() || ! is_array( $messages ) ) {
		return $messages;
	}

	// Echappe : le coeur renvoie ce message en CDATA et l'insere tel quel
	// dans la page par .html() (wp-ajax-response.js) ; il peut contenir le
	// libelle d'un autre terme.
	$texte = esc_html( __( 'Élément ajouté, avec un avertissement :', 'ditl' ) . ' ' . implode( ' ', $avis ) );

	foreach ( array_keys( ditl_boite_taxonomies() ) as $taxonomie ) {
		$messages[ $taxonomie ]    = isset( $messages['_item'] ) && is_array( $messages['_item'] ) ? $messages['_item'] : array();
		$messages[ $taxonomie ][1] = $texte;
	}

	return $messages;
}
add_filter( 'term_updated_messages', 'ditl_boite_termes_messages_ajout' );

/* -------------------------------------------------------------------------
 * Niveaux (parent).
 * ---------------------------------------------------------------------- */

/**
 * Indique si la requete vient d'une interface de la boite qui choisit un
 * parent : formulaires de terme (nonce de la boite) ou ajout rapide depuis
 * la fiche. Les autres ecritures (import, WP-CLI) ne sont pas controlees ici.
 *
 * @param string $taxonomie Taxonomie concernee.
 * @return bool True si le controle du parent s'applique.
 */
function ditl_boite_termes_controle_parent_actif( $taxonomie ) {
	if ( ditl_boite_termes_nonce_valide() ) {
		return true;
	}

	if ( ! wp_doing_ajax() ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- lecture du contexte ; le nonce de l'ajout rapide est verifie par WordPress.
	$action = isset( $_POST['action'] ) && is_string( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

	return 'add-' . $taxonomie === $action;
}

/**
 * Explique pourquoi un parent n'est pas acceptable.
 *
 * @param string $taxonomie Taxonomie de la boite a outils.
 * @param int    $parent_id ID du parent demande (> 0).
 * @param int    $term_id   ID du terme rattache (0 a la creation).
 * @return string Message (vide si le parent est acceptable).
 */
function ditl_boite_termes_erreur_parent( $taxonomie, $parent_id, $term_id ) {
	$taxonomies = ditl_boite_taxonomies();

	if ( ! isset( $taxonomies[ $taxonomie ] ) || $parent_id <= 0 ) {
		return '';
	}

	if ( (int) $taxonomies[ $taxonomie ]['niveaux'] < 2 ) {
		return __( 'Cette liste n\'a qu\'un niveau : un élément ne peut pas avoir de parent.', 'ditl' );
	}

	$parent = get_term( $parent_id, $taxonomie );

	if ( $parent instanceof WP_Term && (int) $parent->parent > 0 ) {
		return __( 'Le parent choisi est déjà un élément de second niveau : choisissez un élément de premier niveau.', 'ditl' );
	}

	if ( $term_id > 0 ) {
		$enfants = get_terms(
			array(
				'taxonomy'   => $taxonomie,
				'parent'     => $term_id,
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
				'lang'       => '',
			)
		);

		if ( is_array( $enfants ) && array() !== $enfants ) {
			return __( 'Cet élément a des sous-éléments : il ne peut pas être rattaché à un parent.', 'ditl' );
		}
	}

	return '';
}

/**
 * Refuse la creation d'un terme sous un parent qui creerait un troisieme
 * niveau (ou un niveau dans une liste simple).
 *
 * @param string|WP_Error $terme     Libelle du terme (ou erreur).
 * @param string          $taxonomie Taxonomie.
 * @param array|string    $args      Arguments de wp_insert_term.
 * @return string|WP_Error Libelle inchange, ou erreur.
 */
function ditl_boite_termes_controle_insertion( $terme, $taxonomie, $args = array() ) {
	if ( is_wp_error( $terme ) || ! ditl_boite_est_taxonomie( $taxonomie ) || ! ditl_boite_termes_controle_parent_actif( $taxonomie ) ) {
		return $terme;
	}

	$args      = wp_parse_args( $args );
	$parent_id = isset( $args['parent'] ) && is_scalar( $args['parent'] ) ? (int) $args['parent'] : 0;
	$erreur    = ditl_boite_termes_erreur_parent( $taxonomie, $parent_id, 0 );

	return '' === $erreur ? $terme : new WP_Error( 'ditl_boite_parent', $erreur );
}
add_filter( 'pre_insert_term', 'ditl_boite_termes_controle_insertion', 10, 3 );

/**
 * Refuse un changement de parent qui creerait un troisieme niveau (ou un
 * niveau dans une liste simple) : le parent precedent est conserve.
 *
 * @param int    $parent_id ID du parent demande.
 * @param int    $term_id   ID du terme modifie.
 * @param string $taxonomie Taxonomie.
 * @return int ID du parent retenu.
 */
function ditl_boite_termes_controle_parent( $parent_id, $term_id, $taxonomie ) {
	$parent_id = (int) $parent_id;

	if ( $parent_id <= 0 || ! ditl_boite_est_taxonomie( $taxonomie ) || ! ditl_boite_termes_controle_parent_actif( $taxonomie ) ) {
		return $parent_id;
	}

	$actuel = get_term( $term_id, $taxonomie );
	$ancien = $actuel instanceof WP_Term ? (int) $actuel->parent : 0;

	if ( $parent_id === $ancien ) {
		return $parent_id;
	}

	$erreur = ditl_boite_termes_erreur_parent( $taxonomie, $parent_id, (int) $term_id );

	if ( '' === $erreur ) {
		return $parent_id;
	}

	ditl_boite_admin_avertir( $erreur . ' ' . __( 'Le parent n\'a pas été modifié.', 'ditl' ) );

	return $ancien;
}
add_filter( 'wp_update_term_parent', 'ditl_boite_termes_controle_parent', 20, 3 );

/**
 * Limite la liste "Parent" des formulaires de terme au premier niveau
 * (taxonomies a niveaux).
 *
 * @param array  $args      Arguments de wp_dropdown_categories.
 * @param string $taxonomie Taxonomie.
 * @return array Arguments modifies.
 */
function ditl_boite_termes_liste_parent( $args, $taxonomie ) {
	$taxonomies = ditl_boite_taxonomies();

	if ( is_array( $args ) && isset( $taxonomies[ $taxonomie ] ) ) {
		$args['depth'] = 1;
	}

	return $args;
}
add_filter( 'taxonomy_parent_dropdown_args', 'ditl_boite_termes_liste_parent', 10, 2 );

/**
 * Classe d'ecran pour les listes simples (masque le champ "Parent", voir
 * boite-admin.css).
 *
 * @param string $classes Classes du body de l'admin.
 * @return string Classes completees.
 */
function ditl_boite_termes_classe_ecran( $classes ) {
	$ecran      = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$taxonomies = ditl_boite_taxonomies();

	if ( $ecran && in_array( $ecran->base, array( 'edit-tags', 'term' ), true ) && isset( $taxonomies[ $ecran->taxonomy ] ) ) {
		$classes .= (int) $taxonomies[ $ecran->taxonomy ]['niveaux'] > 1 ? ' ditl-boite-taxo-niveaux' : ' ditl-boite-taxo-plate';
	}

	return $classes;
}
add_filter( 'admin_body_class', 'ditl_boite_termes_classe_ecran' );

/* -------------------------------------------------------------------------
 * Colonnes de la liste des termes.
 * ---------------------------------------------------------------------- */

/**
 * Insere des colonnes juste apres la colonne du libelle.
 *
 * @param array $colonnes Colonnes existantes.
 * @param array $ajouts   Colonnes a inserer [cle => libelle].
 * @return array Colonnes completees.
 */
function ditl_boite_termes_inserer_colonnes( $colonnes, $ajouts ) {
	$colonnes = is_array( $colonnes ) ? $colonnes : array();

	// La description native n'est pas utilisee par la boite a outils.
	unset( $colonnes['description'] );

	if ( ! isset( $colonnes['name'] ) ) {
		return array_merge( $colonnes, $ajouts );
	}

	$resultat = array();

	foreach ( $colonnes as $cle => $libelle ) {
		$resultat[ $cle ] = $libelle;

		if ( 'name' === $cle ) {
			$resultat = array_merge( $resultat, $ajouts );
		}
	}

	return $resultat;
}

/**
 * Colonnes Code et Ordre.
 *
 * @param array $colonnes Colonnes existantes.
 * @return array Colonnes completees.
 */
function ditl_boite_termes_colonnes( $colonnes ) {
	return ditl_boite_termes_inserer_colonnes(
		$colonnes,
		array(
			'ditl_code'  => __( 'Code', 'ditl' ),
			'ditl_ordre' => __( 'Ordre', 'ditl' ),
		)
	);
}

/**
 * Colonnes Code, Ordre et Couleur (environnements).
 *
 * @param array $colonnes Colonnes existantes.
 * @return array Colonnes completees.
 */
function ditl_boite_termes_colonnes_couleur( $colonnes ) {
	return ditl_boite_termes_inserer_colonnes(
		$colonnes,
		array(
			'ditl_code'    => __( 'Code', 'ditl' ),
			'ditl_ordre'   => __( 'Ordre', 'ditl' ),
			'ditl_couleur' => __( 'Couleur', 'ditl' ),
		)
	);
}

/**
 * Colonne Ordre triable.
 *
 * @param array $colonnes Colonnes triables.
 * @return array Colonnes completees.
 */
function ditl_boite_termes_colonnes_triables( $colonnes ) {
	$colonnes                = is_array( $colonnes ) ? $colonnes : array();
	$colonnes['ditl_ordre'] = 'ditl_ordre';

	return $colonnes;
}

/**
 * Contenu des colonnes de la boite a outils.
 *
 * @param string $contenu Contenu par defaut.
 * @param string $colonne Cle de la colonne.
 * @param int    $term_id ID du terme.
 * @return string HTML de la cellule (echappe).
 */
function ditl_boite_termes_colonne_contenu( $contenu, $colonne, $term_id ) {
	$vide = '<span aria-hidden="true">-</span><span class="screen-reader-text">%s</span>';

	if ( 'ditl_code' === $colonne ) {
		$code = ditl_boite_code_terme( $term_id );

		return '' !== $code ? '<code>' . esc_html( $code ) . '</code>' : sprintf( $vide, esc_html__( 'Aucun code', 'ditl' ) );
	}

	if ( 'ditl_ordre' === $colonne ) {
		return esc_html( (string) ditl_boite_sanitize_ordre( get_term_meta( $term_id, '_ditl_ordre', true ) ) );
	}

	if ( 'ditl_couleur' === $colonne ) {
		$couleur = ditl_boite_sanitize_couleur( get_term_meta( $term_id, '_ditl_couleur', true ) );

		if ( '' === $couleur ) {
			return sprintf( $vide, esc_html__( 'Aucune couleur', 'ditl' ) );
		}

		return '<span class="ditl-boite-pastille" style="' . esc_attr( 'background-color:' . $couleur ) . '" aria-hidden="true"></span> <code>' . esc_html( $couleur ) . '</code>';
	}

	return $contenu;
}

/**
 * Tri de la liste des termes par la colonne Ordre (rang 0 pour un terme
 * sans ordre, puis ordre alphabetique).
 *
 * Jointure explicite sur la seule meta _ditl_ordre (une ligne au plus par
 * terme) : un meta_query "EXISTS" joindrait toutes les metas du terme et
 * trierait sur une valeur quelconque.
 *
 * @param array    $clauses    Clauses SQL de la requete de termes.
 * @param string[] $taxonomies Taxonomies interrogees.
 * @param array    $args       Arguments de get_terms.
 * @return array Clauses modifiees.
 */
function ditl_boite_termes_tri_ordre( $clauses, $taxonomies, $args ) {
	global $wpdb;

	if ( ! is_array( $clauses ) || ! is_array( $args ) || ! isset( $args['orderby'] ) || 'ditl_ordre' !== $args['orderby'] ) {
		return $clauses;
	}

	$taxonomies = array_values( (array) $taxonomies );

	if ( 1 !== count( $taxonomies ) || ! ditl_boite_est_taxonomie( $taxonomies[0] ) ) {
		return $clauses;
	}

	$sens = isset( $clauses['order'] ) && 'DESC' === strtoupper( trim( (string) $clauses['order'] ) ) ? 'DESC' : 'ASC';

	$clauses['join']    .= $wpdb->prepare( " LEFT JOIN {$wpdb->termmeta} AS ditl_tri ON ( t.term_id = ditl_tri.term_id AND ditl_tri.meta_key = %s )", '_ditl_ordre' );
	$clauses['orderby']  = 'ORDER BY CAST( COALESCE( ditl_tri.meta_value, 0 ) AS SIGNED ) ' . $sens . ', t.name';

	return $clauses;
}
add_filter( 'terms_clauses', 'ditl_boite_termes_tri_ordre', 10, 3 );
