/**
 * Metaboxes des gabarits DiTL - ecran d'edition de page.
 *
 * - Affichage conditionnel des metaboxes selon le modele de page choisi
 *   (editeur classique et Gutenberg). Le registre id de metabox => modeles
 *   declencheurs est fourni par PHP via ditlMetabox.metaboxes.
 * - Selecteur de media simple (image de banniere).
 * - Galerie multiple (carrousel).
 * - Sections repetables avec editeur riche (wp.editor.initialize), plusieurs
 *   instances possibles (wrapper .ditl-sections-field, une par gabarit).
 * - Editeurs riches autonomes (textarea.ditl-richtext-editor).
 *
 * @package DiTL
 */

( function( $ ) {
	'use strict';

	var settings  = window.ditlMetabox || {};
	var i18n      = settings.i18n || {};
	var metaboxes = settings.metaboxes || {};

	/* -------------------------------------------------------------------------
	 * Affichage conditionnel selon le modele de page.
	 * ---------------------------------------------------------------------- */

	/**
	 * Retourne le modele de page courant, quel que soit l'editeur.
	 */
	function ditlCurrentTemplate() {
		var $select = $( '#page_template' );

		// Editeur classique : select "Modele de page".
		if ( $select.length ) {
			return $select.val();
		}

		// Gutenberg : attribut "template" du post en cours d'edition.
		if ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) {
			return wp.data.select( 'core/editor' ).getEditedPostAttribute( 'template' );
		}

		return '';
	}

	/**
	 * Montre ou masque chaque metabox du registre selon le modele selectionne.
	 */
	function ditlToggleMetaboxes() {
		var template = ditlCurrentTemplate();

		$.each( metaboxes, function( metaboxId, templates ) {
			var visible = $.inArray( template, templates || [] ) !== -1;

			$( '#' + metaboxId ).toggle( visible );
		} );
	}

	/**
	 * Surveille les changements de modele de page.
	 */
	function ditlWatchTemplate() {
		var $select = $( '#page_template' );

		if ( $select.length ) {
			$select.on( 'change', ditlToggleMetaboxes );
		} else if ( window.wp && wp.data && wp.data.subscribe ) {
			var previous = null;

			wp.data.subscribe( function() {
				var editor = wp.data.select( 'core/editor' );

				if ( ! editor ) {
					return;
				}

				var template = editor.getEditedPostAttribute( 'template' );

				if ( template !== previous ) {
					previous = template;
					ditlToggleMetaboxes();
				}
			} );
		}

		ditlToggleMetaboxes();
	}

	/* -------------------------------------------------------------------------
	 * Selecteur de media simple (image de banniere).
	 * ---------------------------------------------------------------------- */

	function ditlInitMediaField( $metabox ) {
		var frame = null;

		$metabox.on( 'click', '.ditl-media-choose', function( e ) {
			e.preventDefault();

			var $field = $( this ).closest( '.ditl-media-field' );

			if ( ! frame ) {
				frame = wp.media( {
					title: i18n.chooseImage || 'Choisir une image',
					library: { type: 'image' },
					multiple: false,
					button: { text: i18n.useSelection || 'Utiliser cette selection' }
				} );

				frame.on( 'select', function() {
					var attachment = frame.state().get( 'selection' ).first();

					if ( ! attachment ) {
						return;
					}

					var data = attachment.toJSON();
					var url  = data.sizes && data.sizes.medium ? data.sizes.medium.url : data.url;

					$field.find( '.ditl-media-value' ).val( data.id );
					$field.find( '.ditl-media-preview' ).html(
						$( '<img/>', { src: url, alt: '' } )
					);
					$field.find( '.ditl-media-remove' ).show();
				} );
			}

			frame.open();
		} );

		$metabox.on( 'click', '.ditl-media-remove', function( e ) {
			e.preventDefault();

			var $field = $( this ).closest( '.ditl-media-field' );

			$field.find( '.ditl-media-value' ).val( '' );
			$field.find( '.ditl-media-preview' ).empty();
			$( this ).hide();
		} );
	}

	/* -------------------------------------------------------------------------
	 * Galerie multiple (carrousel).
	 * ---------------------------------------------------------------------- */

	/**
	 * Recopie l'ordre des vignettes dans le champ cache (JSON d'IDs).
	 */
	function ditlSyncGalleryValue( $field ) {
		var ids = [];

		$field.find( '.ditl-gallery-preview li' ).each( function() {
			var id = parseInt( $( this ).attr( 'data-id' ), 10 );

			if ( id > 0 ) {
				ids.push( id );
			}
		} );

		$field.find( '.ditl-gallery-value' ).val( JSON.stringify( ids ) );
	}

	function ditlInitGalleryField( $metabox ) {
		var frame = null;

		$metabox.on( 'click', '.ditl-gallery-choose', function( e ) {
			e.preventDefault();

			var $field = $( this ).closest( '.ditl-gallery-field' );

			if ( ! frame ) {
				frame = wp.media( {
					title: i18n.chooseImages || 'Choisir des images',
					library: { type: 'image' },
					multiple: 'add',
					button: { text: i18n.useSelection || 'Utiliser cette selection' }
				} );

				// Preselectionne les images deja retenues.
				frame.on( 'open', function() {
					var selection = frame.state().get( 'selection' );
					var ids       = [];

					try {
						ids = JSON.parse( $field.find( '.ditl-gallery-value' ).val() || '[]' );
					} catch ( err ) {
						ids = [];
					}

					selection.reset();

					ids.forEach( function( id ) {
						var attachment = wp.media.attachment( id );
						attachment.fetch();
						selection.add( attachment );
					} );
				} );

				frame.on( 'select', function() {
					var $preview = $field.find( '.ditl-gallery-preview' );

					$preview.empty();

					frame.state().get( 'selection' ).each( function( attachment ) {
						var data = attachment.toJSON();
						var url  = data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : data.url;

						$preview.append(
							$( '<li/>', { 'data-id': data.id } )
								.append( $( '<img/>', { src: url, alt: '' } ) )
								.append( $( '<button/>', {
									type: 'button',
									'class': 'button-link ditl-gallery-item-remove',
									html: '&times;'
								} ) )
						);
					} );

					ditlSyncGalleryValue( $field );
				} );
			}

			frame.open();
		} );

		$metabox.on( 'click', '.ditl-gallery-item-remove', function( e ) {
			e.preventDefault();

			var $field = $( this ).closest( '.ditl-gallery-field' );

			$( this ).closest( 'li' ).remove();
			ditlSyncGalleryValue( $field );
		} );
	}

	/* -------------------------------------------------------------------------
	 * Sections repetables avec editeur riche.
	 * ---------------------------------------------------------------------- */

	var ditlSectionIndex = 0;

	/**
	 * Initialise l'editeur riche d'une zone de texte de section.
	 */
	function ditlInitEditor( editorId ) {
		if ( ! window.wp || ! wp.editor || ! wp.editor.initialize ) {
			return;
		}

		wp.editor.initialize( editorId, {
			tinymce: {
				wpautop: true,
				toolbar1: 'formatselect,bold,italic,bullist,numlist,link,unlink,removeformat,undo,redo',
				setup: function( editor ) {
					// Garde la zone de texte synchronisee (indispensable
					// pour l'enregistrement des metaboxes sous Gutenberg).
					editor.on( 'change keyup blur', function() {
						editor.save();
					} );
				}
			},
			quicktags: true,
			mediaButtons: false
		} );
	}

	/**
	 * Retire proprement l'editeur riche d'une ligne de section.
	 */
	function ditlRemoveRowEditor( $row ) {
		var editorId = $row.find( '.ditl-section-editor' ).attr( 'id' );

		if ( editorId && window.wp && wp.editor && wp.editor.remove ) {
			if ( window.tinymce && tinymce.get( editorId ) ) {
				tinymce.get( editorId ).save();
			}

			wp.editor.remove( editorId );
		}
	}

	/**
	 * Renumerote les libelles "Section N" d'un champ de sections.
	 */
	function ditlRenumberSections( $field ) {
		$field.find( '.ditl-section' ).each( function( index ) {
			$( this ).find( '.ditl-section-numero' ).text( ditlMetabox.i18n.sectionLabel.replace( '%d', index + 1 ) );
		} );
	}

	function ditlInitSections( $metabox ) {
		var $fields = $metabox.find( '.ditl-sections-field' );

		if ( ! $fields.length ) {
			return;
		}

		// Editeurs des lignes deja presentes, champ par champ.
		$fields.each( function() {
			var $field = $( this );

			$field.find( '.ditl-section-editor' ).each( function() {
				ditlInitEditor( $( this ).attr( 'id' ) );
				ditlSectionIndex++;
			} );

			ditlRenumberSections( $field );
		} );

		// Ajout d'une section (le modele HTML vit dans le champ clique :
		// chaque gabarit a ses propres noms de champs).
		$metabox.on( 'click', '.ditl-section-add', function( e ) {
			e.preventDefault();

			var $field = $( this ).closest( '.ditl-sections-field' );
			var html   = $field.find( '.ditl-section-template' ).html().replace( /%index%/g, 'new-' + ditlSectionIndex );

			ditlSectionIndex++;

			var $row = $( html );

			$field.find( '.ditl-sections' ).append( $row );
			ditlInitEditor( $row.find( '.ditl-section-editor' ).attr( 'id' ) );
			ditlRenumberSections( $field );
		} );

		// Suppression d'une section.
		$metabox.on( 'click', '.ditl-section-remove', function( e ) {
			e.preventDefault();

			var $row   = $( this ).closest( '.ditl-section' );
			var $field = $row.closest( '.ditl-sections-field' );

			ditlRemoveRowEditor( $row );
			$row.remove();
			ditlRenumberSections( $field );
		} );

		// Deplacement d'une section (l'editeur est retire avant le
		// deplacement du DOM puis reinitialise, sinon TinyMCE casse).
		$metabox.on( 'click', '.ditl-section-up, .ditl-section-down', function( e ) {
			e.preventDefault();

			var $row     = $( this ).closest( '.ditl-section' );
			var $field   = $row.closest( '.ditl-sections-field' );
			var up       = $( this ).hasClass( 'ditl-section-up' );
			var $target  = up ? $row.prev( '.ditl-section' ) : $row.next( '.ditl-section' );
			var editorId = $row.find( '.ditl-section-editor' ).attr( 'id' );

			if ( ! $target.length ) {
				return;
			}

			ditlRemoveRowEditor( $row );

			if ( up ) {
				$row.insertBefore( $target );
			} else {
				$row.insertAfter( $target );
			}

			ditlInitEditor( editorId );
			ditlRenumberSections( $field );
		} );
	}

	/* -------------------------------------------------------------------------
	 * Editeurs riches autonomes (champs simples hors sections).
	 * ---------------------------------------------------------------------- */

	function ditlInitRichTextareas( $metabox ) {
		$metabox.find( '.ditl-richtext-editor' ).each( function() {
			ditlInitEditor( $( this ).attr( 'id' ) );
		} );
	}

	/* -------------------------------------------------------------------------
	 * Initialisation.
	 * ---------------------------------------------------------------------- */

	$( function() {
		var initialisee = false;

		// Chaque metabox du registre initialise les champs qu'elle contient
		// (les bindings delegues restent inertes si le champ est absent).
		$.each( metaboxes, function( metaboxId ) {
			var $metabox = $( '#' + metaboxId );

			if ( ! $metabox.length ) {
				return;
			}

			initialisee = true;

			ditlInitMediaField( $metabox );
			ditlInitGalleryField( $metabox );
			ditlInitSections( $metabox );
			ditlInitRichTextareas( $metabox );
		} );

		if ( initialisee ) {
			ditlWatchTemplate();
		}
	} );

} )( jQuery );
