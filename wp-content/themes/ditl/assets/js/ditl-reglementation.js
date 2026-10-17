/**
 * Reglementation : onglets des pays et questions depliables.
 *
 * Amelioration progressive du HTML de page-templates/reglementation.php,
 * qui fonctionne sans script (sommaire de liens d'ancre, tout est visible) :
 * - sommaire des pays -> motif ARIA Tabs (APG) : ul role=tablist etiquete
 *   par le H1, li role=presentation, liens role=tab (aria-selected,
 *   aria-controls, tabindex itinerant 0 / -1), pays role=tabpanel
 *   (aria-labelledby = onglet, hidden si inactif, tabindex=0 seulement s'il
 *   ne contient aucun element focusable). Activation automatique : fleches
 *   gauche / droite (circulaires), Debut / Fin, clic ; l'ancre de l'adresse
 *   suit l'onglet (history.replaceState, sans saut de page) ;
 * - questions -> motif Disclosure : bouton aria-expanded + aria-controls
 *   dans le h3, reponse masquee (hidden). Ouverture a l'activation (clic,
 *   Entree, Espace : comportement natif du bouton), jamais au focus ;
 *   plusieurs reponses peuvent etre ouvertes en meme temps.
 *
 * Recherche dans la page (Ctrl+F) : les navigateurs qui le permettent
 * masquent pays inactifs et reponses repliees par hidden="until-found" ; le
 * texte y reste trouvable, et l'evenement beforematch active le pays ou
 * deplie la question trouvee (aria-selected / aria-expanded a jour). Ailleurs,
 * hidden simple (contenu replie hors recherche, comme sans cette option).
 *
 * Etat initial : premier pays actif, toutes les reponses repliees. Lien
 * profond : #pays-x active l'onglet du pays ; #pays-x-qN active le pays,
 * ouvre la reponse N, fait defiler jusqu'a sa question et y place le focus.
 * Un lien interne vers un pays ou une question (evenement hashchange) a le
 * meme effet, focus compris.
 *
 * Aucun texte d'interface n'est ecrit par ce script : le tablist est
 * etiquete par le H1 de la page, les boutons reprennent le texte des
 * questions (contenus traduits par langue dans l'administration).
 * Aucune animation pilotee par le script (la rotation du chevron est en
 * CSS, desactivee si l'utilisateur prefere reduire les animations).
 *
 * @package DiTL
 */

( function () {
	'use strict';

	var FOCUSABLES = 'a[href], area[href], button, input, select, textarea, iframe, audio[controls], video[controls], [contenteditable], [tabindex]';

	// Valeur de l'attribut hidden : "until-found" si le navigateur gere la
	// recherche dans les contenus masques (evenement beforematch).
	var MASQUE = 'onbeforematch' in document.documentElement ? 'until-found' : '';

	function toArray( list ) {
		return Array.prototype.slice.call( list );
	}

	function hasClass( el, name ) {
		return ( ' ' + el.className + ' ' ).indexOf( ' ' + name + ' ' ) !== -1;
	}

	// Premier ancetre (ou l'element lui-meme) portant la classe, sans
	// depasser la racine. Repli maison de Element.closest.
	function ancetre( el, name, racine ) {
		while ( el && el !== racine && el.nodeType === 1 ) {
			if ( hasClass( el, name ) ) {
				return el;
			}

			el = el.parentNode;
		}

		return null;
	}

	// Element designe par une ancre d'adresse (#...), brute puis decodee.
	function cibleDeAncre( hash ) {
		var id = hash ? hash.replace( /^#/, '' ) : '';
		var el;

		if ( '' === id ) {
			return null;
		}

		el = document.getElementById( id );

		if ( ! el ) {
			try {
				el = document.getElementById( decodeURIComponent( id ) );
			} catch ( e ) {
				el = null;
			}
		}

		return el;
	}

	// Vrai si l'element est masque par un ancetre hidden (jusqu'a la limite).
	function estMasque( el, limite ) {
		while ( el && el !== limite ) {
			if ( el.hasAttribute && el.hasAttribute( 'hidden' ) ) {
				return true;
			}

			el = el.parentNode;
		}

		return false;
	}

	function initReglementation( racine ) {
		// Garde contre une double initialisation.
		if ( racine.getAttribute( 'data-ditl-init' ) ) {
			return;
		}

		racine.setAttribute( 'data-ditl-init', '1' );

		var titre     = document.getElementById( 'ditl-regl-titre' );
		var sommaire  = racine.querySelector( '.ditl-regl__onglets' );
		var onglets   = [];
		var panneaux  = [];
		var questions = [];

		/* ------------------------------------------------------------------
		 * Questions depliables (motif Disclosure)
		 * --------------------------------------------------------------- */

		function ouvrir( question, ouverte ) {
			question.bouton.setAttribute( 'aria-expanded', ouverte ? 'true' : 'false' );

			if ( ouverte ) {
				question.reponse.removeAttribute( 'hidden' );
			} else {
				question.reponse.setAttribute( 'hidden', MASQUE );
			}
		}

		toArray( racine.querySelectorAll( '.ditl-regl__section' ) ).forEach( function ( section ) {
			var h3      = section.querySelector( '.ditl-regl__question' );
			var reponse = section.querySelector( '.ditl-regl__reponse' );

			if ( ! h3 || ! reponse || ! reponse.id ) {
				return;
			}

			var bouton  = document.createElement( 'button' );
			var texte   = document.createElement( 'span' );
			var chevron = document.createElement( 'span' );

			bouton.type      = 'button';
			bouton.className = 'ditl-regl__declencheur';
			bouton.setAttribute( 'aria-controls', reponse.id );

			texte.className = 'ditl-regl__question-texte';

			while ( h3.firstChild ) {
				texte.appendChild( h3.firstChild );
			}

			chevron.className = 'ditl-regl__chevron';
			chevron.setAttribute( 'aria-hidden', 'true' );

			bouton.appendChild( texte );
			bouton.appendChild( chevron );
			h3.appendChild( bouton );

			var question = {
				bouton: bouton,
				reponse: reponse
			};

			ouvrir( question, false );

			bouton.addEventListener( 'click', function () {
				ouvrir( question, 'true' !== bouton.getAttribute( 'aria-expanded' ) );
			} );

			// Texte trouve par la recherche dans la page : la question
			// s'ouvre (le navigateur retire lui-meme l'attribut hidden).
			reponse.addEventListener( 'beforematch', function () {
				ouvrir( question, true );
			} );

			questions.push( question );
		} );

		/* ------------------------------------------------------------------
		 * Onglets des pays (motif Tabs, activation automatique)
		 * --------------------------------------------------------------- */

		if ( sommaire && titre ) {
			toArray( sommaire.querySelectorAll( 'a.ditl-regl__onglet' ) ).forEach( function ( lien ) {
				var panneau = cibleDeAncre( lien.getAttribute( 'href' ) );

				if ( ! panneau || ! lien.id || ! hasClass( panneau, 'ditl-regl__pays' ) || ! racine.contains( panneau ) ) {
					return;
				}

				onglets.push( lien );
				panneaux.push( panneau );
			} );
		}

		// Moins de deux onglets valides : pas d'onglets (contenu direct).
		if ( onglets.length < 2 ) {
			onglets  = [];
			panneaux = [];
		}

		function activer( index, majAncre ) {
			onglets.forEach( function ( onglet, i ) {
				var actif = i === index;

				onglet.setAttribute( 'aria-selected', actif ? 'true' : 'false' );
				onglet.setAttribute( 'tabindex', actif ? '0' : '-1' );

				if ( actif ) {
					panneaux[ i ].removeAttribute( 'hidden' );
				} else {
					panneaux[ i ].setAttribute( 'hidden', MASQUE );
				}
			} );

			if ( majAncre && window.history && window.history.replaceState ) {
				try {
					window.history.replaceState( window.history.state, '', '#' + panneaux[ index ].id );
				} catch ( e ) {
					// Adresse non modifiable (document hors ligne, bac a sable) : sans effet.
				}
			}
		}

		function activerEtFocus( index ) {
			activer( index, true );
			onglets[ index ].focus();
		}

		if ( onglets.length > 1 ) {
			sommaire.setAttribute( 'role', 'tablist' );
			sommaire.setAttribute( 'aria-labelledby', titre.id );

			onglets.forEach( function ( onglet, i ) {
				var panneau = panneaux[ i ];
				var focusables;

				onglet.parentNode.setAttribute( 'role', 'presentation' );
				onglet.setAttribute( 'role', 'tab' );
				onglet.setAttribute( 'aria-controls', panneau.id );

				panneau.setAttribute( 'role', 'tabpanel' );
				panneau.setAttribute( 'aria-labelledby', onglet.id );

				// Panneau atteignable au clavier seulement s'il ne contient
				// aucun element focusable visible (cas sans question).
				focusables = toArray( panneau.querySelectorAll( FOCUSABLES ) ).filter( function ( el ) {
					return '-1' !== el.getAttribute( 'tabindex' ) && ! estMasque( el, panneau );
				} );

				if ( 0 === focusables.length ) {
					panneau.setAttribute( 'tabindex', '0' );
				}

				onglet.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					activer( i, true );
				} );

				// Texte trouve par la recherche dans un pays inactif : son
				// onglet devient l'onglet actif (adresse inchangee).
				panneau.addEventListener( 'beforematch', function () {
					activer( i, false );
				} );

				onglet.addEventListener( 'keydown', function ( event ) {
					var cible = -1;
					var key   = event.key || '';
					var code  = event.keyCode;

					if ( event.altKey || event.ctrlKey || event.metaKey ) {
						return;
					}

					if ( 'ArrowRight' === key || 'Right' === key || 39 === code ) {
						cible = ( i + 1 ) % onglets.length;
					} else if ( 'ArrowLeft' === key || 'Left' === key || 37 === code ) {
						cible = ( i - 1 + onglets.length ) % onglets.length;
					} else if ( 'Home' === key || 36 === code ) {
						cible = 0;
					} else if ( 'End' === key || 35 === code ) {
						cible = onglets.length - 1;
					} else if ( ' ' === key || 'Spacebar' === key || 32 === code ) {
						// Espace sur un lien-onglet : active sans faire defiler la page.
						event.preventDefault();
						activer( i, true );
						return;
					}

					if ( cible < 0 ) {
						return;
					}

					event.preventDefault();
					activerEtFocus( cible );
				} );
			} );

			racine.className += ' ditl-regl--onglets';
		}

		racine.className += ' ditl-regl--js';

		/* ------------------------------------------------------------------
		 * Ancre de l'adresse : pays, question ou element interne
		 * --------------------------------------------------------------- */

		// Applique l'ancre courante. Retourne vrai si elle designait un
		// element de la page Reglementation.
		function appliquerAncre( avecFocus ) {
			var cible = cibleDeAncre( window.location.hash );
			var panneau;
			var index;
			var reponse;
			var question = null;

			if ( ! cible || ! racine.contains( cible ) ) {
				return false;
			}

			panneau = ancetre( cible, 'ditl-regl__pays', racine );
			index   = panneau ? panneaux.indexOf( panneau ) : -1;

			if ( index > -1 ) {
				activer( index, false );
			}

			reponse = ancetre( cible, 'ditl-regl__reponse', racine );

			if ( reponse ) {
				questions.forEach( function ( q ) {
					if ( q.reponse === reponse ) {
						question = q;
					}
				} );
			}

			if ( question ) {
				ouvrir( question, true );

				if ( cible === reponse ) {
					// Question visee : sa ligne en haut de l'ecran, focus sur
					// son bouton.
					question.bouton.scrollIntoView( true );
					question.bouton.focus();
				} else {
					// Element interne a une reponse (ancre dans le contenu).
					cible.scrollIntoView( true );
				}

				return true;
			}

			if ( cible === panneau && index > -1 ) {
				// Pays vise : barre d'onglets en haut de l'ecran.
				sommaire.scrollIntoView( true );

				if ( avecFocus ) {
					onglets[ index ].focus();
				}

				return true;
			}

			if ( avecFocus && cible !== panneau ) {
				cible.scrollIntoView( true );
			}

			return true;
		}

		if ( onglets.length > 1 ) {
			activer( 0, false );
		}

		if ( appliquerAncre( false ) ) {
			// Certains navigateurs defilent vers l'ancre apres l'execution du
			// script (fin du chargement) : si la position a change d'ici la
			// fin du chargement, la position voulue est reappliquee une fois.
			var positionAttendue = window.pageYOffset;

			window.addEventListener( 'load', function () {
				if ( Math.abs( window.pageYOffset - positionAttendue ) > 2 ) {
					appliquerAncre( false );
				}
			} );
		}

		window.addEventListener( 'hashchange', function () {
			appliquerAncre( true );
		} );
	}

	function init() {
		toArray( document.querySelectorAll( '.ditl-regl' ) ).forEach( initReglementation );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
