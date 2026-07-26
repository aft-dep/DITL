/**
 * Carrousel des actualites - implementation maison sans dependance.
 *
 * Reprend le comportement du Swiper d'origine : boucle infinie, defilement
 * automatique toutes les 5 s, transition de 500 ms, 3/2/1 cartes selon la
 * largeur de fenetre (>= 1023 px / >= 767 px / en deca), glisser au doigt
 * ou a la souris (Pointer Events, sans librairie).
 *
 * Accessibilite : vrais boutons avec libelles, navigation clavier sur la
 * region (fleches gauche / droite), bouton pause / lecture (RGAA 13.8),
 * pause au survol et au focus, pas d'autoplay si l'utilisateur prefere
 * reduire les animations. Seules les cartes physiquement affichees sont
 * exposees et tabulables ; si le focus atteint malgre tout une carte hors
 * champ, le carrousel se recale dessus sans animation.
 *
 * Quand toutes les cartes tiennent dans la fenetre, le carrousel passe en
 * mode statique : pas de boucle, pas d'autoplay, commandes masquees.
 *
 * @package DiTL
 */

( function () {
	'use strict';

	var SPEED      = 500;
	var DELAY      = 5000;
	var CLONES     = 3;
	var DRAG_START = 10;
	var SWIPE_MIN  = 40;

	function toArray( list ) {
		return Array.prototype.slice.call( list );
	}

	// Ecoute un media query avec repli pour les anciens navigateurs.
	function onMediaChange( mq, fn ) {
		if ( mq.addEventListener ) {
			mq.addEventListener( 'change', fn );
		} else if ( mq.addListener ) {
			mq.addListener( fn );
		}
	}

	function initCarousel( root ) {
		// Garde contre une double initialisation du meme carrousel.
		if ( root.dataset.ditlInit ) {
			return;
		}

		root.dataset.ditlInit = '1';

		var viewport = root.querySelector( '.ditl-carousel__viewport' );
		var track    = root.querySelector( '.ditl-carousel__track' );
		var prevBtn  = root.querySelector( '.ditl-carousel__nav--prev' );
		var nextBtn  = root.querySelector( '.ditl-carousel__nav--next' );
		var pauseBtn = root.querySelector( '.ditl-carousel__pause' );

		if ( ! viewport || ! track ) {
			return;
		}

		// Le script pilote le defilement : fin du repli overflow-x du CSS.
		viewport.style.overflow = 'hidden';

		var slides = toArray( track.children );
		var total  = slides.length;

		if ( total < 2 ) {
			// Rien a faire defiler : masque fleches et bouton pause.
			root.classList.add( 'ditl-carousel--static' );
			return;
		}

		var mqReduce  = window.matchMedia( '(prefers-reduced-motion: reduce)' );
		var mqDesktop = window.matchMedia( '(min-width: 1023px)' );
		var mqTablet  = window.matchMedia( '(min-width: 767px)' );

		var index         = 0;
		var step          = 0;
		var moving        = false;
		var staticMode    = null;
		var timer         = null;
		var settleTimer   = null;
		var pausedByUser  = false;
		var hovered       = false;
		var focused       = false;
		var dragPointer   = null;
		var dragStartX    = 0;
		var dragDelta     = 0;
		var dragging      = false;
		var suppressClick = false;

		function perView() {
			if ( mqDesktop.matches ) {
				return 3;
			}

			return mqTablet.matches ? 2 : 1;
		}

		// Neutralise un clone pour les technologies d'assistance.
		function markClone( slide ) {
			slide.classList.add( 'ditl-carousel__slide--clone' );
			slide.setAttribute( 'aria-hidden', 'true' );
			slide.removeAttribute( 'role' );
			slide.removeAttribute( 'aria-roledescription' );
			slide.removeAttribute( 'aria-label' );
			toArray( slide.querySelectorAll( 'a' ) ).forEach( function ( link ) {
				link.setAttribute( 'tabindex', '-1' );
			} );
		}

		// Clones de tete et de queue pour la boucle infinie.
		var i, clone;

		for ( i = 0; i < CLONES; i++ ) {
			clone = slides[ i % total ].cloneNode( true );
			markClone( clone );
			track.appendChild( clone );
		}

		for ( i = 0; i < CLONES; i++ ) {
			clone = slides[ ( total - 1 - ( i % total ) + total ) % total ].cloneNode( true );
			markClone( clone );
			track.insertBefore( clone, track.firstChild );
		}

		function measure() {
			var first = track.children[ 0 ];
			var style = window.getComputedStyle( first );

			step = first.getBoundingClientRect().width + ( parseFloat( style.marginRight ) || 0 );
		}

		function render( animate ) {
			var duration = ( animate && ! mqReduce.matches ) ? SPEED : 0;
			var offset   = staticMode ? 0 : -( index + CLONES ) * step;

			track.style.transitionDuration = duration + 'ms';
			track.style.transform = 'translate3d(' + offset + 'px, 0, 0)';
		}

		// Une diapositive reelle est-elle physiquement dans la fenetre ?
		// Les positions servies par des clones ne comptent pas : la carte
		// reelle correspondante est hors champ et doit rester neutralisee.
		function isShown( position ) {
			return staticMode || ( position >= index && position < index + perView() );
		}

		// Seules les cartes physiquement affichees restent exposees et
		// tabulables (les clones sont neutralises a la creation).
		function updateAria() {
			slides.forEach( function ( slide, position ) {
				var shown = isShown( position );

				slide.setAttribute( 'aria-hidden', shown ? 'false' : 'true' );
				toArray( slide.querySelectorAll( 'a' ) ).forEach( function ( link ) {
					if ( shown ) {
						link.removeAttribute( 'tabindex' );
					} else {
						link.setAttribute( 'tabindex', '-1' );
					}
				} );
			} );
		}

		// Bascule carrousel / statique : quand toutes les cartes tiennent
		// dans la fenetre, plus de boucle ni de commandes (clones et
		// boutons masques par la classe, autoplay coupe par syncAutoplay).
		function syncMode() {
			var isStatic = total <= perView();

			if ( isStatic === staticMode ) {
				return;
			}

			staticMode = isStatic;
			root.classList.toggle( 'ditl-carousel--static', staticMode );

			if ( staticMode ) {
				index  = 0;
				moving = false;
				window.clearTimeout( settleTimer );
				root.removeAttribute( 'tabindex' );
			} else {
				// La region reste operable au clavier meme quand les
				// fleches sont masquees (fenetres etroites).
				root.setAttribute( 'tabindex', '0' );
			}
		}

		// Fin de deplacement : repositionne sans transition dans la plage reelle.
		function settle() {
			moving = false;
			window.clearTimeout( settleTimer );

			if ( index >= total ) {
				index -= total;
				render( false );
			} else if ( index < 0 ) {
				index += total;
				render( false );
			}

			updateAria();
		}

		function goTo( target ) {
			if ( moving || staticMode || null !== dragPointer ) {
				return;
			}

			moving = true;
			index  = target;
			render( true );

			// Filet de securite si transitionend ne remonte pas.
			window.clearTimeout( settleTimer );
			settleTimer = window.setTimeout( settle, mqReduce.matches ? 50 : SPEED + 100 );
		}

		function next() {
			goTo( index + 1 );
		}

		function prev() {
			goTo( index - 1 );
		}

		track.addEventListener( 'transitionend', function ( event ) {
			if ( event.target === track && 'transform' === event.propertyName ) {
				settle();
			}
		} );

		/* -- Defilement automatique ---------------------------------------- */

		function autoplayAllowed() {
			return ! staticMode && ! dragging && ! mqReduce.matches && ! pausedByUser && ! hovered && ! focused && ! document.hidden;
		}

		function syncAutoplay() {
			if ( timer ) {
				window.clearInterval( timer );
				timer = null;
			}

			if ( autoplayAllowed() ) {
				timer = window.setInterval( next, DELAY );
			}
		}

		function syncPauseButton() {
			if ( ! pauseBtn ) {
				return;
			}

			// Sans animation automatique, le bouton n'a pas d'objet.
			if ( mqReduce.matches ) {
				pauseBtn.setAttribute( 'hidden', '' );
				return;
			}

			pauseBtn.removeAttribute( 'hidden' );
			root.classList.toggle( 'is-paused', pausedByUser );
			pauseBtn.setAttribute( 'aria-pressed', pausedByUser ? 'true' : 'false' );

			var label = pausedByUser ? root.getAttribute( 'data-label-resume' ) : root.getAttribute( 'data-label-pause' );

			if ( label ) {
				pauseBtn.setAttribute( 'aria-label', label );
			}
		}

		/* -- Glisser au doigt ou a la souris --------------------------------- */

		// Suit le pointeur en bornant le debord a la plage des clones.
		function dragTo( delta ) {
			var max     = CLONES * step;
			var bounded = Math.max( -max, Math.min( max, delta ) );

			track.style.transitionDuration = '0ms';
			track.style.transform = 'translate3d(' + ( -( index + CLONES ) * step + bounded ) + 'px, 0, 0)';

			return bounded;
		}

		function endDrag( event, cancelled ) {
			if ( null === dragPointer || event.pointerId !== dragPointer ) {
				return;
			}

			dragPointer = null;

			if ( ! dragging ) {
				return;
			}

			dragging = false;

			// Le clic qui suit un glisser ne doit pas ouvrir de lien.
			suppressClick = ! cancelled;

			var moved = 0;
			var bounded;

			if ( ! cancelled && step > 0 ) {
				bounded = Math.max( -CLONES * step, Math.min( CLONES * step, dragDelta ) );
				moved   = Math.round( -bounded / step );

				if ( 0 === moved && Math.abs( bounded ) >= SWIPE_MIN ) {
					moved = bounded < 0 ? 1 : -1;
				}
			}

			// Anime depuis la position du doigt vers la carte retenue
			// (retour a la position de depart si le geste est trop court).
			goTo( index + moved );
			syncAutoplay();
		}

		if ( window.PointerEvent ) {
			// Le glissement vertical reste au navigateur (defilement de page).
			viewport.style.touchAction = 'pan-y';

			viewport.addEventListener( 'pointerdown', function ( event ) {
				suppressClick = false;

				if ( staticMode || moving || null !== dragPointer || ! event.isPrimary ) {
					return;
				}

				if ( 'mouse' === event.pointerType && 0 !== event.button ) {
					return;
				}

				dragPointer = event.pointerId;
				dragStartX  = event.clientX;
				dragDelta   = 0;
			} );

			viewport.addEventListener( 'pointermove', function ( event ) {
				if ( null === dragPointer || event.pointerId !== dragPointer ) {
					return;
				}

				dragDelta = event.clientX - dragStartX;

				if ( ! dragging ) {
					// Seuil avant de considerer le geste comme un glisser.
					if ( Math.abs( dragDelta ) < DRAG_START ) {
						return;
					}

					dragging = true;

					if ( viewport.setPointerCapture ) {
						try {
							viewport.setPointerCapture( event.pointerId );
						} catch ( e ) {
							// Pointeur deja libere : le suivi continue sans capture.
						}
					}

					syncAutoplay();
				}

				dragTo( dragDelta );
			} );

			viewport.addEventListener( 'pointerup', function ( event ) {
				endDrag( event, false );
			} );

			viewport.addEventListener( 'pointercancel', function ( event ) {
				endDrag( event, true );
			} );

			// Pas de glisser-deposer natif ni de selection pendant le geste.
			viewport.addEventListener( 'dragstart', function ( event ) {
				if ( null !== dragPointer ) {
					event.preventDefault();
				}
			} );

			viewport.addEventListener( 'selectstart', function ( event ) {
				if ( null !== dragPointer ) {
					event.preventDefault();
				}
			} );

			viewport.addEventListener( 'click', function ( event ) {
				if ( suppressClick ) {
					suppressClick = false;
					event.preventDefault();
					event.stopPropagation();
				}
			}, true );
		}

		/* -- Interactions ---------------------------------------------------- */

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', prev );
		}

		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', next );
		}

		if ( pauseBtn ) {
			pauseBtn.addEventListener( 'click', function () {
				pausedByUser = ! pausedByUser;
				syncPauseButton();
				syncAutoplay();
			} );
		}

		root.addEventListener( 'mouseenter', function () {
			hovered = true;
			syncAutoplay();
		} );

		root.addEventListener( 'mouseleave', function () {
			hovered = false;
			syncAutoplay();
		} );

		// Filet de securite : si le focus atteint une carte hors champ
		// (recherche dans la page, focus programmatique), recale le
		// carrousel dessus sans animation plutot que de laisser un focus
		// invisible (WCAG 2.4.7).
		function realignOnFocus( target ) {
			if ( viewport.scrollLeft ) {
				// Certains navigateurs defilent la fenetre malgre overflow hidden.
				viewport.scrollLeft = 0;
			}

			if ( staticMode ) {
				return;
			}

			var node = target;

			while ( node && node !== root && ! ( node.classList && node.classList.contains( 'ditl-carousel__slide' ) ) ) {
				node = node.parentNode;
			}

			var position = slides.indexOf( node );

			if ( -1 === position || isShown( position ) ) {
				return;
			}

			window.clearTimeout( settleTimer );
			moving = false;
			index  = Math.max( 0, Math.min( position, total - perView() ) );
			render( false );
			updateAria();
		}

		root.addEventListener( 'focusin', function ( event ) {
			focused = true;
			realignOnFocus( event.target );
			syncAutoplay();
		} );

		root.addEventListener( 'focusout', function ( event ) {
			focused = !! ( event.relatedTarget && root.contains( event.relatedTarget ) );
			syncAutoplay();
		} );

		root.addEventListener( 'keydown', function ( event ) {
			if ( staticMode ) {
				return;
			}

			if ( 'ArrowLeft' === event.key ) {
				event.preventDefault();
				prev();
			} else if ( 'ArrowRight' === event.key ) {
				event.preventDefault();
				next();
			}
		} );

		document.addEventListener( 'visibilitychange', syncAutoplay );

		onMediaChange( mqReduce, function () {
			render( false );
			syncPauseButton();
			syncAutoplay();
		} );

		var resizePending = false;

		window.addEventListener( 'resize', function () {
			if ( resizePending ) {
				return;
			}

			resizePending = true;
			window.requestAnimationFrame( function () {
				resizePending = false;
				syncMode();
				measure();
				render( false );
				updateAria();
				syncAutoplay();
			} );
		} );

		/* -- Etat initial ------------------------------------------------------ */

		syncMode();
		measure();
		render( false );
		updateAria();
		syncPauseButton();
		syncAutoplay();
	}

	function init() {
		toArray( document.querySelectorAll( '.ditl-carousel' ) ).forEach( initCarousel );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
