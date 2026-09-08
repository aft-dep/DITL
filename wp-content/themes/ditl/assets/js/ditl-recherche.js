/**
 * Recherche de l'en-tete : ouverture et fermeture du deroulant (RGAA 7.1, 7.3).
 *
 * Remplace le pilotage jQuery d'Ivory Search par un motif Disclosure complet,
 * en reprenant son comportement a l'identique : fondu de 400 ms a
 * l'ouverture, champ focalise avec le curseur en fin de saisie, croix de
 * fermeture affichee une fois le fondu termine, fermeture au clic hors du
 * composant. S'y ajoute ce qui manquait : Echap ferme et rend le focus au
 * bouton d'ouverture, la croix est un vrai bouton, l'etat est expose par
 * aria-expanded.
 *
 * Le composant est present deux fois dans la page (en-tete desktop et menu
 * hors ecran mobile) : chaque exemplaire est traite independamment.
 *
 * Sans dependance. Repli silencieux (le formulaire reste masque, la recherche
 * reste accessible par la page de resultats) sur les navigateurs sans
 * classList ni closest.
 *
 * @package DiTL
 */
( function () {
	'use strict';

	if ( ! document.querySelector || ! ( 'classList' in document.documentElement ) || ! Element.prototype.closest ) {
		return;
	}

	var CLASSE = 'ditl-recherche';
	var CLASSE_OUVERT = 'ditl-recherche--ouvert';
	var CLASSE_VISIBLE = 'ditl-recherche--visible';
	var CLASSE_COMPLET = 'ditl-recherche--complet';
	var DUREE_FONDU = 400;

	/**
	 * Indique si l'utilisateur demande a limiter les animations.
	 *
	 * @return {boolean} Vrai si le fondu doit etre supprime.
	 */
	function mouvementReduit() {
		return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	}

	/**
	 * Elements interactifs d'un composant.
	 *
	 * @param {Element} composant Conteneur role="search".
	 * @return {Object} Bouton d'ouverture, champ et bouton de fermeture.
	 */
	function pieces( composant ) {
		return {
			bascule: composant.querySelector( '.ditl-recherche__bascule' ),
			champ: composant.querySelector( '.ditl-recherche__champ' ),
			fermer: composant.querySelector( '.ditl-recherche__fermer' )
		};
	}

	/**
	 * Numero de la manoeuvre en cours sur un composant.
	 *
	 * Ouverture et fermeture programment des traitements differes (trame
	 * suivante pour le fondu, fin du fondu pour la croix et pour le retrait
	 * de l'affichage). Deux activations rapprochees les feraient se croiser
	 * et laisseraient le deroulant visible avec aria-expanded="false" : ce
	 * jeton, incremente a chaque manoeuvre, invalide les traitements
	 * differes de la manoeuvre precedente.
	 *
	 * @param {Element} composant Conteneur role="search".
	 * @return {number} Numero de la nouvelle manoeuvre.
	 */
	function nouvelleManoeuvre( composant ) {
		composant.ditlManoeuvre = ( composant.ditlManoeuvre || 0 ) + 1;

		if ( composant.ditlMinuteur ) {
			window.clearTimeout( composant.ditlMinuteur );
			composant.ditlMinuteur = null;
		}

		return composant.ditlManoeuvre;
	}

	/**
	 * Ouvre le deroulant et place le focus dans le champ.
	 *
	 * @param {Element} composant Conteneur role="search".
	 */
	function ouvrir( composant ) {
		var elements = pieces( composant );
		var manoeuvre;

		if ( composant.classList.contains( CLASSE_OUVERT ) ) {
			return;
		}

		manoeuvre = nouvelleManoeuvre( composant );

		composant.classList.add( CLASSE_OUVERT );

		if ( elements.bascule ) {
			elements.bascule.setAttribute( 'aria-expanded', 'true' );
		}

		// Le fondu ne demarre qu'une fois l'affichage applique : la classe
		// d'opacite est posee a la trame suivante.
		if ( mouvementReduit() ) {
			composant.classList.add( CLASSE_VISIBLE );
			composant.classList.add( CLASSE_COMPLET );
		} else {
			window.requestAnimationFrame( function () {
				window.requestAnimationFrame( function () {
					if ( manoeuvre === composant.ditlManoeuvre ) {
						composant.classList.add( CLASSE_VISIBLE );
					}
				} );
			} );
			composant.ditlMinuteur = window.setTimeout( function () {
				composant.ditlMinuteur = null;
				if ( manoeuvre === composant.ditlManoeuvre ) {
					composant.classList.add( CLASSE_COMPLET );
				}
			}, DUREE_FONDU );
		}

		// Focus differe d'une tache : quand l'ouverture vient d'une touche
		// Entree sur le bouton, le navigateur delivre encore keypress et
		// keyup apres le clic. Focaliser le champ tout de suite les lui
		// ferait recevoir, et Entree dans un champ de formulaire declenche
		// l'envoi implicite : la recherche partait a vide.
		window.setTimeout( function () {
			if ( ! elements.champ || manoeuvre !== composant.ditlManoeuvre ) {
				return;
			}
			elements.champ.focus();
			try {
				elements.champ.setSelectionRange( elements.champ.value.length, elements.champ.value.length );
			} catch ( e ) {
				// Certains navigateurs refusent setSelectionRange sur type=search.
			}
		}, 0 );
	}

	/**
	 * Ferme le deroulant.
	 *
	 * @param {Element} composant   Conteneur role="search".
	 * @param {boolean} rendreFocus Vrai pour replacer le focus sur le bouton.
	 */
	function fermer( composant, rendreFocus ) {
		var elements = pieces( composant );
		var manoeuvre;

		if ( ! composant.classList.contains( CLASSE_OUVERT ) ) {
			return;
		}

		manoeuvre = nouvelleManoeuvre( composant );

		composant.classList.remove( CLASSE_COMPLET );
		composant.classList.remove( CLASSE_VISIBLE );

		if ( elements.bascule ) {
			elements.bascule.setAttribute( 'aria-expanded', 'false' );
		}

		if ( rendreFocus && elements.bascule ) {
			elements.bascule.focus();
		}

		if ( mouvementReduit() ) {
			composant.classList.remove( CLASSE_OUVERT );
			return;
		}

		composant.ditlMinuteur = window.setTimeout( function () {
			composant.ditlMinuteur = null;
			if ( manoeuvre === composant.ditlManoeuvre ) {
				composant.classList.remove( CLASSE_OUVERT );
			}
		}, DUREE_FONDU );
	}

	/**
	 * Ferme tous les composants ouverts, sauf celui indique.
	 *
	 * @param {Element} sauf Composant a laisser ouvert (peut etre null).
	 */
	function toutFermer( sauf ) {
		var ouverts = document.querySelectorAll( '.' + CLASSE_OUVERT );
		var i;

		for ( i = 0; i < ouverts.length; i++ ) {
			if ( ouverts[ i ] !== sauf ) {
				fermer( ouverts[ i ], false );
			}
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var cible = e.target;
		var composant;

		if ( ! cible || ! cible.closest ) {
			return;
		}

		if ( cible.closest( '.ditl-recherche__bascule' ) ) {
			composant = cible.closest( '.' + CLASSE );

			// Bouton hors conteneur (markup altere par un tiers) : rien a faire.
			if ( ! composant ) {
				return;
			}

			e.preventDefault();
			toutFermer( composant );
			if ( composant.classList.contains( CLASSE_OUVERT ) ) {
				fermer( composant, true );
			} else {
				ouvrir( composant );
			}
			return;
		}

		if ( cible.closest( '.ditl-recherche__fermer' ) ) {
			composant = cible.closest( '.' + CLASSE );

			if ( ! composant ) {
				return;
			}

			e.preventDefault();
			fermer( composant, true );
			return;
		}

		// Clic hors d'un composant ouvert : fermeture sans deplacer le focus,
		// comme le faisait l'extension remplacee.
		toutFermer( cible.closest( '.' + CLASSE ) );
	} );

	document.addEventListener( 'keydown', function ( e ) {
		var composant;

		if ( 'Escape' !== e.key && 'Esc' !== e.key && 27 !== e.keyCode ) {
			return;
		}

		composant = e.target && e.target.closest ? e.target.closest( '.' + CLASSE_OUVERT ) : null;

		if ( composant ) {
			fermer( composant, true );
			return;
		}

		toutFermer( null );
	} );
}() );
