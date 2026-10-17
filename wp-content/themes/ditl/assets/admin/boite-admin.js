/**
 * Boite a outils - ecrans d'administration (fiche outil, listes de
 * reference, textes de la page Boite a outils).
 *
 * - Fiche : lignes repetables des fabricants (ajout, suppression, ordre),
 *   editeurs riches preserves quand la metabox est deplacee.
 * - Listes a niveaux : champ "Parent" limite au premier niveau, y compris
 *   apres les reponses AJAX qui le reconstruisent (ajout rapide, changement
 *   de langue par Polylang, ajout depuis la liste).
 * - Environnements : couleur du badge (saisie hexadecimale + palette),
 *   apercu avec la couleur de texte calculee comme cote PHP
 *   (ditl_boite_couleur_texte, commun.php : le PHP fait foi).
 * - Ajout AJAX d'un terme : affichage des avertissements (code en doublon).
 * - Page : bascule autonome de la metabox des textes, seulement si le
 *   script commun des gabarits n'est pas charge.
 *
 * Reglages fournis par PHP via ditlBoiteAdmin (inc/boite/admin-fiche.php).
 *
 * @package DiTL
 */

( function( $ ) {
	'use strict';

	var reglages = window.ditlBoiteAdmin || {};
	var i18n     = reglages.i18n || {};

	/**
	 * Annonce un message aux technologies d'assistance.
	 */
	function annoncer( message ) {
		if ( message && window.wp && wp.a11y && wp.a11y.speak ) {
			wp.a11y.speak( message );
		}
	}

	/**
	 * Remplace %d dans un modele de texte.
	 */
	function formater( modele, valeur ) {
		return String( modele || '' ).replace( '%d', valeur );
	}

	/* -------------------------------------------------------------------------
	 * Fiche : fabricants.
	 * ---------------------------------------------------------------------- */

	function initFabricants() {
		$( '.ditl-boite-fabricants' ).each( function() {
			var $champ  = $( this );
			var $liste  = $champ.find( '.ditl-boite-lignes' );
			var modele  = $.trim( $champ.find( '.ditl-boite-modele-ligne' ).html() || '' );
			var maximum = parseInt( reglages.maxFabricants, 10 ) || 100;

			$champ.addClass( 'ditl-boite-js' );

			function lignes() {
				return $liste.children( '.ditl-boite-ligne' );
			}

			function renumeroter() {
				var $lignes = lignes();

				$lignes.each( function( index ) {
					var $ligne = $( this );
					var numero = index + 1;

					$ligne.find( '.ditl-boite-ligne-numero' ).text( formater( i18n.fabricant, numero ) );
					$ligne.find( '.ditl-boite-monter' ).prop( 'disabled', 0 === index )
						.find( '.screen-reader-text' ).text( formater( i18n.monter, numero ) );
					$ligne.find( '.ditl-boite-descendre' ).prop( 'disabled', index === $lignes.length - 1 )
						.find( '.screen-reader-text' ).text( formater( i18n.descendre, numero ) );
					$ligne.find( '.ditl-boite-supprimer' ).attr( 'aria-label', formater( i18n.supprimer, numero ) );
				} );

				$champ.find( '.ditl-boite-ajouter' ).prop( 'disabled', $lignes.length >= maximum );
			}

			$champ.on( 'click', '.ditl-boite-ajouter', function( e ) {
				e.preventDefault();

				if ( lignes().length >= maximum ) {
					annoncer( i18n.limiteFabricants );
					return;
				}

				var $ligne = $( modele ).filter( '.ditl-boite-ligne' );

				$liste.append( $ligne );
				renumeroter();
				$ligne.find( 'input' ).first().trigger( 'focus' );
				annoncer( i18n.fabricantAjoute );
			} );

			$champ.on( 'click', '.ditl-boite-supprimer', function( e ) {
				e.preventDefault();

				var $ligne = $( this ).closest( '.ditl-boite-ligne' );
				var $cible = $ligne.next( '.ditl-boite-ligne' );

				// Derniere ligne : videe plutot que retiree (le champ reste
				// utilisable et l'enregistrement efface bien la liste).
				if ( 1 === lignes().length ) {
					$ligne.find( 'input' ).val( '' );
					$ligne.find( 'input' ).first().trigger( 'focus' );
					annoncer( i18n.fabricantRetire );
					return;
				}

				if ( ! $cible.length ) {
					$cible = $ligne.prev( '.ditl-boite-ligne' );
				}

				$ligne.remove();
				renumeroter();
				$cible.find( 'input' ).first().trigger( 'focus' );
				annoncer( i18n.fabricantRetire );
			} );

			$champ.on( 'click', '.ditl-boite-monter, .ditl-boite-descendre', function( e ) {
				e.preventDefault();

				var $bouton = $( this );
				var monter  = $bouton.hasClass( 'ditl-boite-monter' );
				var $ligne  = $bouton.closest( '.ditl-boite-ligne' );
				var $voisin = monter ? $ligne.prev( '.ditl-boite-ligne' ) : $ligne.next( '.ditl-boite-ligne' );

				if ( ! $voisin.length ) {
					return;
				}

				if ( monter ) {
					$ligne.insertBefore( $voisin );
				} else {
					$ligne.insertAfter( $voisin );
				}

				renumeroter();

				// Le bouton devient inactif en premiere ou derniere position :
				// le focus passe alors sur le bouton inverse.
				if ( $bouton.prop( 'disabled' ) ) {
					$ligne.find( monter ? '.ditl-boite-descendre' : '.ditl-boite-monter' ).trigger( 'focus' );
				} else {
					$bouton.trigger( 'focus' );
				}

				annoncer( i18n.fabricantDeplace );
			} );

			renumeroter();
		} );
	}

	/* -------------------------------------------------------------------------
	 * Fiche : editeurs riches de la metabox preserves lors d'un deplacement
	 * (un iframe TinyMCE deplace dans la page perd son contenu).
	 * ---------------------------------------------------------------------- */

	function initDeplacementEditeurs() {
		var boite = document.getElementById( 'ditl-boite-fiche' );

		if ( ! boite || ! window.tinymce ) {
			return;
		}

		function detacher() {
			$( boite ).find( 'textarea.wp-editor-area' ).each( function() {
				var editeur = window.tinymce.get( this.id );

				if ( editeur && ! editeur.isHidden() ) {
					editeur.save();
					editeur.remove();
					$( this ).data( 'ditlDetache', true );
				}
			} );
		}

		function rattacher() {
			$( boite ).find( 'textarea.wp-editor-area' ).each( function() {
				var reglagesEditeur = window.tinyMCEPreInit && window.tinyMCEPreInit.mceInit ? window.tinyMCEPreInit.mceInit[ this.id ] : null;

				if ( $( this ).data( 'ditlDetache' ) && reglagesEditeur ) {
					$( this ).removeData( 'ditlDetache' );
					window.tinymce.init( reglagesEditeur );
				}
			} );
		}

		// Glisser-deposer de la metabox.
		$( '.meta-box-sortables' )
			.on( 'sortstart', function( e, ui ) {
				if ( ui && ui.item && ui.item.is( boite ) ) {
					detacher();
				}
			} )
			.on( 'sortstop', function( e, ui ) {
				if ( ui && ui.item && ui.item.is( boite ) ) {
					rattacher();
				}
			} );

		// Fleches "monter / descendre" de l'en-tete : ecoute en phase de
		// capture, avant le deplacement fait par postbox.js.
		boite.addEventListener( 'click', function( e ) {
			if ( $( e.target ).closest( '.handle-order-higher, .handle-order-lower' ).length ) {
				detacher();
				window.setTimeout( rattacher, 0 );
			}
		}, true );
	}

	/* -------------------------------------------------------------------------
	 * Listes a niveaux : champ "Parent" limite au premier niveau.
	 * ---------------------------------------------------------------------- */

	/**
	 * Retire d'une liste "Parent" les termes de second niveau et plus
	 * (classe level-N de wp_dropdown_categories, ou libelle indente ajoute
	 * par le script de la liste des termes).
	 */
	function elaguerListe( $select ) {
		$select.find( 'option' ).filter( function() {
			return /(^|\s)level-[1-9]/.test( this.className ) || /^\s/.test( $( this ).text() );
		} ).remove();
	}

	function elaguerParents() {
		$.each( reglages.taxonomiesNiveaux || [], function( index, taxonomie ) {
			elaguerListe( $( '#new' + taxonomie + '_parent' ) );
		} );

		if ( 'termes' === reglages.ecran && $( 'body' ).hasClass( 'ditl-boite-taxo-niveaux' ) ) {
			elaguerListe( $( 'select#parent' ) );
		}
	}

	/* -------------------------------------------------------------------------
	 * Couleur des badges (environnements).
	 * ---------------------------------------------------------------------- */

	/**
	 * Normalise une couleur saisie : #rrggbb en minuscules, ou null.
	 */
	function normaliserCouleur( valeur ) {
		var couleur = $.trim( String( valeur || '' ) ).toLowerCase();

		if ( /^[0-9a-f]{3}([0-9a-f]{3})?$/.test( couleur ) ) {
			couleur = '#' + couleur;
		}

		if ( ! /^#([0-9a-f]{3}){1,2}$/.test( couleur ) ) {
			return null;
		}

		if ( 4 === couleur.length ) {
			couleur = '#' + couleur.charAt( 1 ) + couleur.charAt( 1 ) + couleur.charAt( 2 ) + couleur.charAt( 2 ) + couleur.charAt( 3 ) + couleur.charAt( 3 );
		}

		return couleur;
	}

	/**
	 * Luminance relative WCAG 2.x (miroir de ditl_boite_luminance).
	 */
	function luminance( hex ) {
		var valeur = String( hex ).replace( '#', '' );

		if ( 6 !== valeur.length ) {
			return 1;
		}

		var composantes = [ 0, 2, 4 ].map( function( position ) {
			var canal = parseInt( valeur.substr( position, 2 ), 16 ) / 255;

			return canal <= 0.03928 ? canal / 12.92 : Math.pow( ( canal + 0.055 ) / 1.055, 2.4 );
		} );

		return 0.2126 * composantes[ 0 ] + 0.7152 * composantes[ 1 ] + 0.0722 * composantes[ 2 ];
	}

	function ratioContraste( premiere, seconde ) {
		var l1 = luminance( premiere );
		var l2 = luminance( seconde );

		return ( Math.max( l1, l2 ) + 0.05 ) / ( Math.min( l1, l2 ) + 0.05 );
	}

	/**
	 * Couleur de texte lisible (miroir de ditl_boite_couleur_texte).
	 */
	function couleurTexte( fond ) {
		var charte = reglages.couleurCharte || '#1e448f';
		var seuil  = parseFloat( reglages.seuilContraste ) || 4.5;

		if ( ratioContraste( fond, charte ) >= seuil ) {
			return charte;
		}

		return ratioContraste( fond, '#ffffff' ) >= ratioContraste( fond, '#000000' ) ? '#ffffff' : '#000000';
	}

	function initCouleurs() {
		$( '.ditl-boite-couleur' ).each( function() {
			var $bloc      = $( this );
			var $hex       = $bloc.find( '.ditl-boite-couleur-hex' );
			var $palette   = $bloc.find( '.ditl-boite-couleur-palette' );
			var $apercu    = $bloc.nextAll( '.ditl-boite-apercu' ).first();
			var $badge     = $apercu.find( '.ditl-boite-badge' );
			var $contraste = $apercu.find( '.ditl-boite-contraste' );
			var dernier    = null;

			$palette.prop( 'hidden', false );

			function mettreAJour() {
				var saisie  = $.trim( $hex.val() );
				var couleur = normaliserCouleur( saisie );
				var message;

				if ( '' === saisie ) {
					$badge.addClass( 'ditl-boite-badge-neutre' ).css( { backgroundColor: '', color: '' } );
					message = i18n.sansCouleur;
				} else if ( null === couleur ) {
					$badge.addClass( 'ditl-boite-badge-neutre' ).css( { backgroundColor: '', color: '' } );
					message = i18n.couleurInvalide;
				} else {
					var texte = couleurTexte( couleur );
					var nom   = texte === ( reglages.couleurCharte || '#1e448f' ) ? i18n.texteCharte : ( '#ffffff' === texte ? i18n.texteBlanc : i18n.texteNoir );
					var ratio = ratioContraste( couleur, texte ).toFixed( 2 ).replace( '.', reglages.separateurDecimal || ',' );

					$badge.removeClass( 'ditl-boite-badge-neutre' ).css( { backgroundColor: couleur, color: texte } );
					$palette.val( couleur );
					message = String( i18n.contraste || '' ).replace( '%1$s', ratio ).replace( '%2$s', nom );
				}

				// Region aria-live : mise a jour seulement si le texte change.
				if ( message !== dernier ) {
					dernier = message;
					$contraste.text( message );
				}
			}

			$hex.on( 'input', mettreAJour );

			// A la sortie du champ : forme normalisee (# ajoute, minuscules).
			$hex.on( 'change', function() {
				var couleur = normaliserCouleur( $hex.val() );

				if ( null !== couleur ) {
					$hex.val( couleur );
				}

				mettreAJour();
			} );

			$palette.on( 'input change', function() {
				$hex.val( $palette.val() );
				mettreAJour();
			} );

			// Formulaire d'ajout : l'apercu reprend le libelle saisi.
			if ( $bloc.closest( '#addtag' ).length ) {
				$( '#tag-name' ).on( 'input', function() {
					$badge.text( $.trim( $( this ).val() ) || i18n.exemple || '' );
				} );
			}

			mettreAJour();
		} );
	}

	/* -------------------------------------------------------------------------
	 * Ajout AJAX d'un terme : avertissements renvoyes par le serveur.
	 * ---------------------------------------------------------------------- */

	function initAjoutTerme() {
		if ( 'termes' !== reglages.ecran ) {
			return;
		}

		$( document ).ajaxSuccess( function( event, xhr, options ) {
			if ( ! options || 'string' !== typeof options.data || -1 === options.data.indexOf( 'action=add-tag' ) ) {
				return;
			}

			var xml = xhr.responseXML;

			if ( ! xml ) {
				try {
					xml = $.parseXML( xhr.responseText );
				} catch ( erreur ) {
					return;
				}
			}

			var $xml = $( xml );

			if ( ! $xml.find( 'wp_error' ).length ) {
				var message = $.trim( $xml.find( 'supplemental notice' ).first().text() );

				if ( message && message !== i18n.termeAjoute ) {
					// Le coeur affiche deja le message (echappe cote serveur)
					// dans #ajax-response : il passe simplement en avertissement.
					var $avis = $( '#ajax-response .notice' ).last();

					if ( $avis.length ) {
						$avis.removeClass( 'notice-success' ).addClass( 'notice-warning ditl-boite-avis' );
						annoncer( $avis.text() );
					}
				}
			}

			// Champs vides par le script de la liste : apercu recalcule.
			$( '.ditl-boite-couleur-hex' ).trigger( 'input' );
			$( '#tag-name' ).trigger( 'input' );
		} );
	}

	/* -------------------------------------------------------------------------
	 * Page : bascule autonome de la metabox des textes (repli).
	 * ---------------------------------------------------------------------- */

	function initBasculePage() {
		var config = reglages.pageTextes;

		if ( ! config || ! config.metabox ) {
			return;
		}

		var $boite = $( '#' + config.metabox );

		if ( ! $boite.length ) {
			return;
		}

		function modeleCourant() {
			var $select = $( '#page_template' );

			if ( $select.length ) {
				return $select.val();
			}

			if ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) {
				return wp.data.select( 'core/editor' ).getEditedPostAttribute( 'template' );
			}

			return '';
		}

		function basculer() {
			$boite.toggle( -1 !== $.inArray( modeleCourant(), config.modeles || [] ) );
		}

		$( '#page_template' ).on( 'change', basculer );

		if ( ! $( '#page_template' ).length && window.wp && wp.data && wp.data.subscribe ) {
			var precedent = null;

			wp.data.subscribe( function() {
				var editeur = wp.data.select( 'core/editor' );

				if ( ! editeur ) {
					return;
				}

				var modele = editeur.getEditedPostAttribute( 'template' );

				if ( modele !== precedent ) {
					precedent = modele;
					basculer();
				}
			} );
		}

		basculer();
	}

	/* -------------------------------------------------------------------------
	 * Initialisation.
	 * ---------------------------------------------------------------------- */

	$( function() {
		initFabricants();
		initDeplacementEditeurs();
		initCouleurs();
		initAjoutTerme();
		initBasculePage();
		elaguerParents();

		// Listes "Parent" reconstruites par les reponses AJAX.
		$( document ).ajaxComplete( elaguerParents );
	} );

} )( jQuery );
