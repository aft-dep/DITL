/**
 * Boite a outils - amelioration progressive et suivi d'audience.
 *
 * Sans dependance, charge en pied de page sur la page Boite a outils et sur
 * les fiches outils uniquement (inc/boite/front.php). Tout fonctionne sans
 * ce script (formulaires GET rendus cote serveur) ; il ajoute :
 * - le bouton "Imprimer la fiche" (masque tant que le script n'a pas tourne) ;
 * - la colonne "Informations" de la fiche collante au defilement, au-dessus
 *   de 1024 px et seulement si elle tient dans la fenetre ;
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
} )();
