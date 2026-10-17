/**
 * Modules e-learning (gabarit "E-learning") : hauteur des iframes.
 *
 * Les exports eXeLearning sont servis par le site lui-meme (meme origine) :
 * le script lit la hauteur du module et l'applique a son iframe, qui
 * s'allonge comme le reste de la page. Le visiteur n'a qu'une barre de
 * defilement, celle de la page, y compris sur mobile. La hauteur suit le
 * module en continu (ouverture ou fermeture d'un bloc, reponse a une
 * activite, changement de largeur de la fenetre).
 *
 * Correctif d'integration : le theme "base" d'eXeLearning masque le texte
 * de ses boutons d'icone (ex. "Toggle content") par un decoupage CSS qui
 * laisse la boite du texte deborder du bouton : 16 px de defilement
 * horizontal parasite dans l'iframe. Une regle limitant cette boite a 1 px
 * est ajoutee dans le document du module au chargement (le texte reste lu
 * par les lecteurs d'ecran) ; l'export lui-meme n'est jamais modifie.
 *
 * Garde-fou : un export dont la hauteur grandirait avec celle de l'iframe
 * (element en 100vh dans un autre style eXeLearning) ferait grandir la page
 * sans fin (un agrandissement a chaque image, soit une soixantaine par
 * seconde). Au-dela de 40 agrandissements en 1 seconde, seuil hors d'atteinte
 * du chargement normal des images d'un module (mesures regroupees par
 * image), le suivi s'arrete et l'iframe reprend sa hauteur fixe de repli
 * (elle defile).
 *
 * Sans JavaScript, si le module etait servi par une autre origine, ou si
 * l'iframe affiche un document illisible (lien externe ouvert dedans),
 * l'iframe garde la hauteur fixe de la feuille de style et defile.
 */
( function () {
	'use strict';

	var CLASSE_AJUSTE = 'ditl-elearning__cadre--ajuste';
	var ID_CORRECTIF  = 'ditl-elearning-correctif';
	var CORRECTIF     = '.box-toggle span,.nav-buttons .nav-button span,#exe-client-search-reset span,#packageLicense .license-label{width:1px!important;height:1px!important;overflow:hidden!important;white-space:nowrap!important}';
	var AGRANDISSEMENTS_MAX = 40;
	var FENETRE_MS          = 1000;

	/**
	 * Document du module, ou null s'il est inaccessible (autre origine) ou
	 * pas encore charge.
	 *
	 * @param {HTMLIFrameElement} cadre Iframe du module.
	 * @return {Document|null} Document du module.
	 */
	function documentModule( cadre ) {
		var doc;

		try {
			doc = cadre.contentDocument;
		} catch ( e ) {
			return null;
		}

		if ( ! doc || ! doc.documentElement || ! doc.body || 'about:blank' === doc.location.href ) {
			return null;
		}

		return doc;
	}

	/**
	 * Rend a l'iframe sa hauteur fixe de repli (feuille de style).
	 *
	 * @param {HTMLIFrameElement} cadre Iframe du module.
	 */
	function hauteurDeRepli( cadre ) {
		cadre.style.height = '';
		cadre.classList.remove( CLASSE_AJUSTE );
	}

	/**
	 * Ajoute le correctif d'integration dans le document du module.
	 *
	 * @param {Document} doc Document du module.
	 */
	function poserCorrectif( doc ) {
		var style;

		if ( doc.getElementById( ID_CORRECTIF ) ) {
			return;
		}

		style             = doc.createElement( 'style' );
		style.id          = ID_CORRECTIF;
		style.textContent = CORRECTIF;
		( doc.head || doc.documentElement ).appendChild( style );
	}

	/**
	 * Raccorde une iframe au document charge : correctif puis suivi de la
	 * hauteur. Un meme document n'est raccorde qu'une fois.
	 *
	 * @param {HTMLIFrameElement} cadre Iframe du module.
	 */
	function raccorder( cadre ) {
		var doc = documentModule( cadre );
		var win;
		var observateur = null;
		var prevu       = false;
		var arrete      = false;
		var agrandissements = [];

		if ( ! doc ) {
			hauteurDeRepli( cadre );
			return;
		}

		if ( cadre.ditlDocument === doc ) {
			return;
		}

		cadre.ditlDocument = doc;
		win                = doc.defaultView;

		poserCorrectif( doc );

		// Arret du suivi (garde-fou) : hauteur de repli, plus aucune mesure.
		function arreter() {
			arrete = true;

			if ( observateur ) {
				observateur.disconnect();
			}

			hauteurDeRepli( cadre );
		}

		// Hauteur de la boite de l'element html (et non scrollHeight, qui ne
		// descend jamais sous la hauteur de l'iframe : le cadre ne pourrait
		// plus raccourcir quand un bloc se referme).
		function ajuster() {
			var hauteur;
			var maintenant;

			prevu = false;

			if ( arrete || cadre.ditlDocument !== doc || ! doc.documentElement ) {
				return;
			}

			hauteur = Math.ceil( doc.documentElement.getBoundingClientRect().height );

			if ( hauteur <= 0 || Math.abs( hauteur - cadre.offsetHeight ) <= 1 ) {
				cadre.classList.add( CLASSE_AJUSTE );
				return;
			}

			if ( hauteur > cadre.offsetHeight ) {
				maintenant      = Date.now();
				agrandissements = agrandissements.filter( function ( instant ) {
					return maintenant - instant < FENETRE_MS;
				} );
				agrandissements.push( maintenant );

				if ( agrandissements.length > AGRANDISSEMENTS_MAX ) {
					arreter();
					return;
				}
			}

			cadre.style.height = hauteur + 'px';
			cadre.classList.add( CLASSE_AJUSTE );
		}

		// Regroupe les mesures demandees dans une meme image.
		function planifier() {
			if ( ! prevu && ! arrete ) {
				prevu = true;
				window.requestAnimationFrame( ajuster );
			}
		}

		ajuster();

		if ( win && 'function' === typeof win.ResizeObserver ) {
			observateur = new win.ResizeObserver( planifier );
			observateur.observe( doc.documentElement );
		}
	}

	/**
	 * Initialise une iframe de module (une seule fois).
	 *
	 * @param {HTMLIFrameElement} cadre Iframe du module.
	 */
	function initialiser( cadre ) {
		var chargements = 0;

		if ( cadre.getAttribute( 'data-ditl-elearning' ) ) {
			return;
		}

		cadre.setAttribute( 'data-ditl-elearning', '1' );

		// Chaque chargement (premier affichage ou page suivante d'un export
		// en plusieurs pages) recree le document du module.
		cadre.addEventListener( 'load', function () {
			++chargements;
			raccorder( cadre );

			// Page suivante d'un export en plusieurs pages : l'iframe ne
			// defile pas, c'est la page qui ramene le haut du module a
			// l'ecran (sinon le visiteur resterait en bas de la nouvelle page).
			if ( chargements > 1 && cadre.getBoundingClientRect().top < 0 ) {
				cadre.scrollIntoView();
			}
		} );

		// Module deja charge avant l'execution du script.
		if ( documentModule( cadre ) && 'complete' === cadre.contentDocument.readyState ) {
			++chargements;
			raccorder( cadre );
		}
	}

	Array.prototype.forEach.call( document.querySelectorAll( '.ditl-elearning__cadre' ), initialiser );

	// Navigateurs sans ResizeObserver (anterieurs a 2020) : remesure au
	// redimensionnement de la fenetre seulement.
	if ( 'function' !== typeof window.ResizeObserver ) {
		window.addEventListener( 'resize', function () {
			Array.prototype.forEach.call( document.querySelectorAll( '.ditl-elearning__cadre' ), function ( cadre ) {
				cadre.ditlDocument = null;
				raccorder( cadre );
			} );
		} );
	}
}() );
