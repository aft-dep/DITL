/**
 * Menu principal : sous-menus deroulants accessibles au clavier (RGAA 7.1).
 *
 * Astra n'ecoute les boutons .ast-menu-toggle que dans l'en-tete mobile. En
 * desktop, le theme les rend focusables (assets/css/a11y.css) et gere ici
 * l'ouverture / la fermeture : Entree ou Espace sur le bouton, Echap pour
 * refermer et revenir sur le bouton, fermeture quand le focus quitte l'item,
 * etat aria-expanded tenu a jour (y compris quand Astra ouvre le sous-menu
 * par la classe .focus au passage du clavier sur le lien parent).
 *
 * En mobile, Astra pose aria-expanded sur la cible de l'evenement et oublie
 * de le remettre a false quand le menu se referme : l'etat est realigne sur la
 * classe ast-submenu-expanded apres chaque clic, et Echap referme le
 * sous-menu ouvert.
 *
 * Sans dependance. Repli silencieux (comportement Astra inchange) sur les
 * navigateurs sans classList / closest.
 *
 * @package DiTL
 */
( function () {
	'use strict';

	if ( ! document.querySelector || ! ( 'classList' in document.documentElement ) || ! Element.prototype.closest ) {
		return;
	}

	var CLASSE_OUVERT = 'ditl-sous-menu-ouvert';

	function estEchap( e ) {
		return 'Escape' === e.key || 'Esc' === e.key;
	}

	function boutonDe( item ) {
		var enfants = item.children;
		for ( var i = 0; i < enfants.length; i++ ) {
			if ( enfants[ i ].classList.contains( 'ast-menu-toggle' ) ) {
				return enfants[ i ];
			}
		}
		return null;
	}

	function poserEtat( bouton, ouvert ) {
		bouton.setAttribute( 'aria-expanded', ouvert ? 'true' : 'false' );
		// Astra peut avoir pose l'attribut sur l'icone interne (cible du clic).
		var errants = bouton.querySelectorAll( '[aria-expanded]' );
		for ( var i = 0; i < errants.length; i++ ) {
			errants[ i ].removeAttribute( 'aria-expanded' );
		}
	}

	/* ------------------------------------------------------------------
	 * Desktop
	 * ---------------------------------------------------------------- */

	function synchroniserDesktop( item ) {
		var bouton = boutonDe( item );
		if ( bouton ) {
			poserEtat( bouton, item.classList.contains( CLASSE_OUVERT ) || item.classList.contains( 'focus' ) );
		}
	}

	function fermerDesktop( item ) {
		item.classList.remove( CLASSE_OUVERT );
		synchroniserDesktop( item );
	}

	function ouvrirDesktop( item ) {
		var freres = item.parentNode.children;
		for ( var i = 0; i < freres.length; i++ ) {
			if ( freres[ i ] !== item && freres[ i ].classList.contains( CLASSE_OUVERT ) ) {
				fermerDesktop( freres[ i ] );
			}
		}
		item.classList.add( CLASSE_OUVERT );
		synchroniserDesktop( item );
	}

	function initialiserDesktop( item ) {
		var bouton = boutonDe( item );
		if ( ! bouton ) {
			return;
		}

		bouton.addEventListener( 'click', function ( e ) {
			// Sous le point de rupture, l'en-tete desktop est masque : Astra gere.
			if ( document.body.classList.contains( 'ast-header-break-point' ) ) {
				return;
			}
			e.preventDefault();
			if ( item.classList.contains( CLASSE_OUVERT ) ) {
				fermerDesktop( item );
			} else {
				ouvrirDesktop( item );
			}
		} );

		// Apres les handlers focus/blur d'Astra (classe .focus sur le li).
		item.addEventListener( 'focusin', function () {
			window.setTimeout( function () {
				synchroniserDesktop( item );
			}, 0 );
		} );

		item.addEventListener( 'focusout', function ( e ) {
			if ( e.relatedTarget && item.contains( e.relatedTarget ) ) {
				window.setTimeout( function () {
					synchroniserDesktop( item );
				}, 0 );
				return;
			}
			fermerDesktop( item );
		} );

		item.addEventListener( 'keydown', function ( e ) {
			if ( ! estEchap( e ) ) {
				return;
			}
			if ( ! item.classList.contains( CLASSE_OUVERT ) && ! item.classList.contains( 'focus' ) ) {
				return;
			}
			e.preventDefault();
			fermerDesktop( item );
			// Le retour du focus sur le bouton retire la classe .focus d'Astra
			// (blur du lien) : le sous-menu se referme dans tous les cas.
			bouton.focus();
		} );
	}

	var enteteDesktop = document.getElementById( 'ast-desktop-header' );
	if ( enteteDesktop ) {
		var items = enteteDesktop.querySelectorAll( '.main-header-menu > .menu-item-has-children' );
		for ( var i = 0; i < items.length; i++ ) {
			initialiserDesktop( items[ i ] );
		}
	}

	/* ------------------------------------------------------------------
	 * Mobile
	 * ---------------------------------------------------------------- */

	var enteteMobile = document.getElementById( 'ast-mobile-header' );
	if ( enteteMobile ) {
		var synchroniserMobile = function () {
			var boutons = enteteMobile.querySelectorAll( '.menu-item-has-children > .ast-menu-toggle' );
			for ( var j = 0; j < boutons.length; j++ ) {
				poserEtat( boutons[ j ], boutons[ j ].parentNode.classList.contains( 'ast-submenu-expanded' ) );
			}
		};

		// Apres le handler d'Astra sur le bouton (phase de bouillonnement + tick).
		enteteMobile.addEventListener( 'click', function () {
			window.setTimeout( synchroniserMobile, 0 );
		} );

		enteteMobile.addEventListener( 'keydown', function ( e ) {
			if ( ! estEchap( e ) || ! e.target || ! e.target.closest ) {
				return;
			}
			var item = e.target.closest( '.menu-item-has-children.ast-submenu-expanded' );
			if ( ! item || ! enteteMobile.contains( item ) ) {
				return;
			}
			var bouton = boutonDe( item );
			if ( ! bouton ) {
				return;
			}
			e.preventDefault();
			bouton.click();
			bouton.focus();
		} );
	}
} )();
