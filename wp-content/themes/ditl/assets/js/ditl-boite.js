/**
 * Boite a outils - amelioration progressive et suivi d'audience.
 *
 * Sans dependance, charge en pied de page sur la page Boite a outils et sur
 * les fiches outils uniquement (inc/boite/front.php). Tout fonctionne sans
 * ce script (formulaires GET rendus cote serveur) ; il ajoute :
 * - le bouton "Imprimer la fiche" (masque tant que le script n'a pas tourne) ;
 * - la colonne "Informations" de la fiche collante au defilement, au-dessus
 *   de 1024 px et seulement si elle tient dans la fenetre ;
 * - sur telephone (resultats et catalogue), les fenetres modales "Votre
 *   profil" et "Filtrer les outils" (dialog natif) ; sans ce script ou sans
 *   dialog, les blocs restent dans la page ;
 * - le suivi des interactions dans Google Analytics 4.
 *
 * Suivi d'audience : evenements envoyes par window.gtag( 'event', ... ) et
 * UNIQUEMENT si gtag existe. Site Kit definit gtag avec un consentement
 * refuse par defaut et Complianz ne charge gtag.js qu'apres l'accord du
 * visiteur : aucun envoi sans consentement. Aucun cookie ni stockage n'est
 * ajoute. Les donnees viennent des attributs data-ditl-suivi* poses par les
 * gabarits : codes neutres, identiques dans toutes les langues (agregation
 * multilingue dans GA4).
 *
 * Plan de marquage (noms et parametres en snake_case, noms <= 40
 * caracteres, valeurs tronquees a 100 caracteres ; parametre "langue" sur
 * tous les evenements) :
 *
 *  Evenement                  | Declencheur                               | Parametres
 *  ---------------------------+-------------------------------------------+-------------------------------------------
 *  ditl_boite_choix           | changement d'une liste du questionnaire   | etape, valeur
 *  ditl_boite_etape           | envoi d'une etape (bouton Valider)        | etape, numero, valeur
 *  ditl_boite_resultats       | affichage des resultats ou du catalogue   | vue, nb_resultats, page, metier, tache,
 *                             |                                           | difficulte, empechement, environnements
 *  ditl_boite_filtres         | envoi des filtres (Appliquer les filtres) | vue, environnements, nb_environnements
 *  ditl_boite_modifier        | lien "Modifier" d'une reponse             | etape, vue
 *  ditl_boite_reinitialiser   | lien "Reinitialiser / Recommencer"        | vue
 *  ditl_boite_voir_tous       | lien "Voir tous les outils"               | vue
 *  ditl_boite_ouverture_fiche | clic sur une carte (resultats, catalogue) | fiche, position, vue
 *  ditl_fiche_impression      | impression de la fiche (bouton ou         | fiche, declencheur (bouton | navigateur)
 *                             | commande du navigateur)                   |
 *  ditl_fiche_lien_externe    | clic sur un lien fabricant ou source      | fiche, domaine
 *  ditl_fiche_autres_outils   | clic sur une carte du carrousel           | fiche (fiche ouverte), position
 *
 * Valeurs : etape = metier | tache | difficulte | empechement ; valeur,
 * metier, tache, difficulte, empechement = code neutre du terme ;
 * environnements = codes separes par des virgules ; vue = questionnaire |
 * resultats | catalogue | fiche ; fiche = identifiant neutre de la fiche
 * (groupe de l'import CSV, sinon "p" + ID de la plus ancienne traduction).
 *
 * @package DiTL
 */

( function () {
	'use strict';

	var MAX_VALEUR = 100;

	// Donnees de la vue (attribut JSON de la racine du gabarit).
	function donneesVue() {
		var racine = document.querySelector( '[data-ditl-boite-suivi]' );
		var brut   = racine ? racine.getAttribute( 'data-ditl-boite-suivi' ) : '';

		if ( ! brut ) {
			return {};
		}

		try {
			var donnees = JSON.parse( brut );

			return donnees && 'object' === typeof donnees ? donnees : {};
		} catch ( e ) {
			return {};
		}
	}

	var vue = donneesVue();

	function tronquer( valeur ) {
		if ( 'number' === typeof valeur ) {
			return valeur;
		}

		valeur = null === valeur || undefined === valeur ? '' : String( valeur );

		return valeur.length > MAX_VALEUR ? valeur.slice( 0, MAX_VALEUR ) : valeur;
	}

	// Envoie un evenement GA4 si gtag est disponible (jamais d'erreur).
	function envoyer( nom, parametres ) {
		if ( 'function' !== typeof window.gtag ) {
			return;
		}

		var donnees = { langue: tronquer( vue.langue || document.documentElement.lang || '' ) };
		var cle;

		for ( cle in parametres ) {
			if ( Object.prototype.hasOwnProperty.call( parametres, cle ) ) {
				donnees[ cle ] = tronquer( parametres[ cle ] );
			}
		}

		try {
			window.gtag( 'event', nom, donnees );
		} catch ( e ) {
			// Le suivi ne doit jamais casser la navigation.
		}
	}

	function attribut( element, nom ) {
		return element ? ( element.getAttribute( nom ) || '' ) : '';
	}

	/* -- Affichage des resultats ou du catalogue ------------------------------ */

	if ( 'resultats' === vue.vue || 'catalogue' === vue.vue ) {
		envoyer( 'ditl_boite_resultats', {
			vue: vue.vue,
			nb_resultats: 'number' === typeof vue.nb_resultats ? vue.nb_resultats : 0,
			page: 'number' === typeof vue.page ? vue.page : 1,
			metier: vue.metier || '',
			tache: vue.tache || '',
			difficulte: vue.difficulte || '',
			empechement: vue.empechement || '',
			environnements: vue.environnements || ''
		} );
	}

	/* -- Listes et formulaires -------------------------------------------------- */

	document.addEventListener( 'change', function ( event ) {
		var cible = event.target;

		if ( cible && cible.matches && cible.matches( 'select[data-ditl-suivi-liste]' ) && '' !== cible.value ) {
			// Parcours des options au clavier (liste fermee, Windows) : un
			// "change" par option ; seul le dernier choix stable est envoye.
			window.clearTimeout( cible.ditlSuiviChoix );
			cible.ditlSuiviChoix = window.setTimeout( function () {
				envoyer( 'ditl_boite_choix', {
					etape: attribut( cible, 'data-ditl-suivi-liste' ),
					valeur: cible.value
				} );
			}, 800 );
		}
	} );

	document.addEventListener( 'submit', function ( event ) {
		var formulaire = event.target;
		var type       = attribut( formulaire, 'data-ditl-suivi-formulaire' );
		var liste, coches, codes, i;

		if ( 'etape' === type ) {
			liste = formulaire.querySelector( 'select[data-ditl-suivi-liste]' );

			envoyer( 'ditl_boite_etape', {
				etape: attribut( formulaire, 'data-ditl-suivi-etape' ),
				numero: parseInt( attribut( formulaire, 'data-ditl-suivi-numero' ), 10 ) || 0,
				valeur: liste ? liste.value : ''
			} );
		} else if ( 'filtres' === type ) {
			coches = formulaire.querySelectorAll( 'input[type="checkbox"]:checked' );
			codes  = [];

			for ( i = 0; i < coches.length; i++ ) {
				codes.push( coches[ i ].value );
			}

			envoyer( 'ditl_boite_filtres', {
				vue: vue.vue || '',
				environnements: codes.join( ',' ),
				nb_environnements: codes.length
			} );
		}
	} );

	/* -- Liens suivis ------------------------------------------------------------ */

	document.addEventListener( 'click', function ( event ) {
		var lien = event.target && event.target.closest ? event.target.closest( '[data-ditl-suivi-clic]' ) : null;
		var type = attribut( lien, 'data-ditl-suivi-clic' );
		var domaine;

		if ( ! type ) {
			return;
		}

		switch ( type ) {
			case 'modifier':
				envoyer( 'ditl_boite_modifier', { etape: attribut( lien, 'data-ditl-suivi-etape' ), vue: vue.vue || '' } );
				break;

			case 'reinitialiser':
				envoyer( 'ditl_boite_reinitialiser', { vue: vue.vue || '' } );
				break;

			case 'voir_tous':
				envoyer( 'ditl_boite_voir_tous', { vue: vue.vue || '' } );
				break;

			case 'ouverture_fiche':
				envoyer( 'ditl_boite_ouverture_fiche', {
					fiche: attribut( lien, 'data-ditl-suivi-fiche' ),
					position: parseInt( attribut( lien, 'data-ditl-suivi-position' ), 10 ) || 0,
					vue: attribut( lien, 'data-ditl-suivi-vue' ) || vue.vue || ''
				} );
				break;

			case 'autres_outils':
				envoyer( 'ditl_fiche_autres_outils', {
					fiche: attribut( lien, 'data-ditl-suivi-fiche' ),
					position: parseInt( attribut( lien, 'data-ditl-suivi-position' ), 10 ) || 0
				} );
				break;

			case 'lien_externe':
				domaine = lien.hostname ? lien.hostname.replace( /^www\./i, '' ) : '';
				envoyer( 'ditl_fiche_lien_externe', { fiche: vue.fiche || '', domaine: domaine } );
				break;
		}
	} );

	/* -- Fiche : impression ------------------------------------------------------ */

	var parBouton     = false;
	var dernierEnvoi  = 0;
	var boutonImprimer = document.querySelector( '[data-ditl-imprimer]' );

	if ( 'fiche' === vue.vue ) {
		// Toute impression de la fiche (bouton ou commande du navigateur) est
		// comptee une fois, meme si le navigateur emet l'evenement en double.
		window.addEventListener( 'beforeprint', function () {
			var maintenant = Date.now();

			if ( maintenant - dernierEnvoi > 2000 ) {
				dernierEnvoi = maintenant;
				envoyer( 'ditl_fiche_impression', { fiche: vue.fiche || '', declencheur: parBouton ? 'bouton' : 'navigateur' } );
			}

			parBouton = false;
		} );
	}

	if ( boutonImprimer && 'function' === typeof window.print ) {
		boutonImprimer.removeAttribute( 'hidden' );
		boutonImprimer.addEventListener( 'click', function () {
			parBouton = true;
			window.print();
		} );
	}

	/* -- Fiche : colonne "Informations" collante ---------------------------------- */

	var infos = document.querySelector( '[data-ditl-infos]' );

	if ( infos && window.matchMedia ) {
		var mqLarge   = window.matchMedia( '(min-width: 1025px)' );
		var enAttente = false;

		var ajuster = function () {
			enAttente = false;

			// Marge haute de la position collante : 2rem (boite.css).
			var marge   = 2 * ( parseFloat( window.getComputedStyle( document.documentElement ).fontSize ) || 16 );
			var tient   = infos.offsetHeight + 2 * marge <= window.innerHeight;
			var collant = mqLarge.matches && tient;

			infos.classList.toggle( 'ditl-fiche__infos--collante', collant );
		};

		var planifier = function () {
			if ( ! enAttente ) {
				enAttente = true;
				window.requestAnimationFrame( ajuster );
			}
		};

		window.addEventListener( 'resize', planifier );
		window.addEventListener( 'load', planifier );
		ajuster();
	}

	/* -- Resultats et catalogue : fenetres "Votre profil" et "Filtrer les outils" -- */

	/*
	 * Telephone uniquement (maquettes mobiles du 17/11/2026). Chaque bouton
	 * [data-ditl-fenetre-ouvrir] ouvre le dialog de meme nom (gabarit :
	 * ditl_boite_front_fenetre) et y DEPLACE le bloc de la colonne
	 * [data-ditl-fenetre-bloc] : un seul formulaire, cases et suivi
	 * d'audience inchanges. showModal assure le piegeage du focus, la touche
	 * Echap et l'inertie du reste de la page. A l'ouverture, le titre du bloc
	 * passe en H1 (hierarchie propre a la fenetre, revue Access42) et recoit
	 * le focus ; a la fermeture (bouton Fermer, Echap, clic sur le fond), le
	 * bloc reprend sa place, son titre redevient H2 et le focus revient sur
	 * le bouton. Passage au-dela du seuil (rotation, redimensionnement) avec
	 * une fenetre ouverte : elle se ferme et le bloc revient dans la colonne,
	 * focus conserve sur l'element actif.
	 */
	var TELEPHONE = 'screen and (max-width: 767.98px)'; // Seuil de la section "Telephone" de boite.css.

	function changerBalise( element, balise ) {
		var nouveau = document.createElement( balise );
		var i;

		for ( i = 0; i < element.attributes.length; i++ ) {
			nouveau.setAttribute( element.attributes[ i ].name, element.attributes[ i ].value );
		}

		while ( element.firstChild ) {
			nouveau.appendChild( element.firstChild );
		}

		element.parentNode.replaceChild( nouveau, element );

		return nouveau;
	}

	function creerFenetre( declencheur, dialogue, contenu, bloc, requete ) {
		var fenetre       = { declencheur: declencheur, bloc: bloc };
		var marqueur      = null;
		var titre         = null;
		var focusAvant    = null;
		var appuiSurFond  = false;

		function horsDuCadre( event ) {
			var r = dialogue.getBoundingClientRect();

			return event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom;
		}

		fenetre.ouvrir = function () {
			if ( dialogue.open || ! requete.matches ) {
				return;
			}

			marqueur = document.createComment( ' ditl-fenetre ' );
			bloc.parentNode.insertBefore( marqueur, bloc );
			contenu.appendChild( bloc );

			titre = bloc.querySelector( '.ditl-boite-bloc__titre' );

			if ( titre ) {
				if ( 'H2' === titre.tagName ) {
					titre = changerBalise( titre, 'h1' );
				}

				titre.setAttribute( 'tabindex', '-1' );
			}

			document.documentElement.classList.add( 'ditl-boite-fenetre-ouverte' );
			dialogue.showModal();

			if ( titre ) {
				titre.focus();
			}
		};

		fenetre.fermer = function () {
			if ( dialogue.open ) {
				focusAvant = document.activeElement;
				dialogue.close();
			}
		};

		// Remise en place (evenement close : bouton, Echap ou fermeture forcee).
		dialogue.addEventListener( 'close', function () {
			var avant = focusAvant;
			var cible = avant && bloc.contains( avant ) && avant !== titre ? avant : null;

			focusAvant = null;

			if ( ! marqueur ) {
				return;
			}

			if ( titre ) {
				titre.removeAttribute( 'tabindex' );

				if ( 'H1' === titre.tagName ) {
					changerBalise( titre, 'h2' );
				}

				titre = null;
			}

			marqueur.parentNode.insertBefore( bloc, marqueur );
			marqueur.parentNode.removeChild( marqueur );
			marqueur = null;
			document.documentElement.classList.remove( 'ditl-boite-fenetre-ouverte' );

			if ( requete.matches ) {
				declencheur.focus();
			} else if ( avant ) {
				// Fermeture forcee par le passage au bureau : le focus etait sur
				// "Fermer" ou sur le titre (hors du bloc remis en place) -> premier
				// element focalisable du bloc, pour ne pas le perdre sur body.
				cible = cible || bloc.querySelector( 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled])' );

				if ( cible ) {
					cible.focus();
				}
			}
		} );

		declencheur.addEventListener( 'click', fenetre.ouvrir );

		dialogue.querySelector( '[data-ditl-fenetre-fermer]' ).addEventListener( 'click', fenetre.fermer );

		// Tab dans la fenetre : la navigation native du dialog suffit. Tant que
		// le bandeau de cookies attend une reponse, son piege a focus (ecouteur
		// sur document) renverrait Tab vers ce bandeau, inerte ici : le focus
		// resterait bloque. La touche ne remonte donc pas au-dela du dialog.
		dialogue.addEventListener( 'keydown', function ( event ) {
			if ( 'Tab' === event.key ) {
				event.stopPropagation();
			}
		} );

		// Clic sur le fond grise : fermeture (appui ET relachement hors du cadre).
		dialogue.addEventListener( 'pointerdown', function ( event ) {
			appuiSurFond = event.target === dialogue && horsDuCadre( event );
		} );

		dialogue.addEventListener( 'click', function ( event ) {
			if ( appuiSurFond && event.target === dialogue && horsDuCadre( event ) ) {
				fenetre.fermer();
			}

			appuiSurFond = false;
		} );

		return fenetre;
	}

	( function () {
		var liste = document.querySelector( '.ditl-boite--liste' );
		var fenetres = [];
		var requete, declencheurs, i, nom, dialogue, contenu, bloc;

		if ( ! liste || ! window.matchMedia || 'function' !== typeof window.HTMLDialogElement || 'function' !== typeof window.HTMLDialogElement.prototype.showModal ) {
			return;
		}

		requete      = window.matchMedia( TELEPHONE );
		declencheurs = liste.querySelectorAll( '[data-ditl-fenetre-ouvrir]' );

		for ( i = 0; i < declencheurs.length; i++ ) {
			nom      = declencheurs[ i ].getAttribute( 'data-ditl-fenetre-ouvrir' );
			dialogue = liste.querySelector( 'dialog[data-ditl-fenetre="' + nom + '"]' );
			contenu  = dialogue ? dialogue.querySelector( '[data-ditl-fenetre-contenu]' ) : null;
			bloc     = liste.querySelector( '[data-ditl-fenetre-bloc="' + nom + '"]' );

			if ( contenu && bloc && dialogue.querySelector( '[data-ditl-fenetre-fermer]' ) ) {
				fenetres.push( creerFenetre( declencheurs[ i ], dialogue, contenu, bloc, requete ) );
				declencheurs[ i ].removeAttribute( 'hidden' );
				dialogue.removeAttribute( 'hidden' );
			}
		}

		if ( 0 === fenetres.length ) {
			return;
		}

		// La colonne n'est retiree de l'affichage (boite.css) qu'une fois les
		// fenetres pretes.
		liste.classList.add( 'ditl-boite--fenetres' );

		function surChangement() {
			var j, actif = document.activeElement;

			for ( j = 0; j < fenetres.length; j++ ) {
				if ( ! requete.matches ) {
					fenetres[ j ].fermer();
				} else if ( actif && fenetres[ j ].bloc.contains( actif ) ) {
					// Passage au telephone, focus dans la colonne qui disparait.
					fenetres[ j ].declencheur.focus();
				}
			}
		}

		if ( requete.addEventListener ) {
			requete.addEventListener( 'change', surChangement );
		} else if ( requete.addListener ) {
			requete.addListener( surChangement );
		}
	}() );
} )();
