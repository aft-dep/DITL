/**
 * Carte interactive du gabarit Livrable - neutralisation pour les
 * technologies d'assistance.
 *
 * La carte (plugin Interactive Geo Maps, rendu amCharts) est purement
 * illustrative : aucune zone n'a d'action, et son contenu est decrit par
 * l'alternative textuelle qui la precede dans le gabarit. Le conteneur
 * .ditl-liv-carte porte aria-hidden="true" cote serveur ; ce script retire
 * en plus du parcours clavier les elements que le graphe rend focalisables
 * (tabindex="0" sur le groupe SVG, focusable="true") pour qu'un utilisateur
 * clavier ne s'arrete pas sur un composant vide de sens et sans etiquette
 * francaise (RGAA 4.12 / 4.13 / 7.1). Aucun pixel ne change.
 *
 * Le graphe se dessine apres le chargement, et peut se redessiner
 * (redimensionnement) : un MutationObserver reapplique la neutralisation.
 *
 * @package DiTL
 */

( function () {
	'use strict';

	function toArray( list ) {
		return Array.prototype.slice.call( list );
	}

	function neutraliser( conteneur ) {
		toArray( conteneur.querySelectorAll( '[tabindex]' ) ).forEach( function ( el ) {
			if ( '-1' !== el.getAttribute( 'tabindex' ) ) {
				el.setAttribute( 'tabindex', '-1' );
			}
		} );

		toArray( conteneur.querySelectorAll( '[focusable="true"]' ) ).forEach( function ( el ) {
			el.setAttribute( 'focusable', 'false' );
		} );

		toArray( conteneur.querySelectorAll( 'a, button, input, select, textarea, iframe' ) ).forEach( function ( el ) {
			if ( '-1' !== el.getAttribute( 'tabindex' ) ) {
				el.setAttribute( 'tabindex', '-1' );
			}
		} );
	}

	function init() {
		toArray( document.querySelectorAll( '.ditl-liv-carte' ) ).forEach( function ( conteneur ) {
			neutraliser( conteneur );

			if ( ! window.MutationObserver ) {
				return;
			}

			var enCours = false;

			new MutationObserver( function () {
				// Nos propres ecritures d'attributs declenchent l'observateur :
				// une seule passe par lot de mutations, sans rebouclage.
				if ( enCours ) {
					return;
				}

				enCours = true;
				window.requestAnimationFrame( function () {
					neutraliser( conteneur );
					enCours = false;
				} );
			} ).observe( conteneur, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'tabindex', 'focusable' ] } );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
