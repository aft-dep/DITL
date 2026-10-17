/**
 * Import des fiches outils - execution par lots de l'ecran d'administration.
 *
 * - Bouton "Lancer l'import" : enchaine les requetes admin-ajax
 *   (action ditl_import_lot) jusqu'a la fin ; la position est tenue par le
 *   serveur, une interruption se reprend la ou elle s'est arretee.
 * - Barre <progress> et zone de statut aria-live (role status).
 * - Rapport final construit dans le DOM, texte insere uniquement par
 *   textContent (jamais innerHTML avec des donnees), focus deplace sur son
 *   titre.
 * - Sans JavaScript : message <noscript> renvoyant vers WP-CLI.
 *
 * @package DiTL
 */

( function() {
	'use strict';

	var config = window.ditlImport || {};
	var i18n   = config.i18n || {};

	var bouton      = document.getElementById( 'ditl-import-lancer' );
	var progression = document.getElementById( 'ditl-import-progression' );
	var barre       = document.getElementById( 'ditl-import-barre' );
	var statut      = document.getElementById( 'ditl-import-statut' );
	var zoneFinale  = document.getElementById( 'ditl-import-final' );
	var abandon     = document.getElementById( 'ditl-import-abandon' );
	var rapport     = document.getElementById( 'ditl-import-rapport' );

	var enCours    = false;
	var tentatives = 0;

	// Ecran de rapport charge : focus sur son titre.
	if ( rapport ) {
		rapport.focus();
	}

	if ( ! bouton || ! config.ajaxUrl ) {
		return;
	}

	/**
	 * Remplace %1$s, %2$s... dans un texte traduit.
	 */
	function formater( texte ) {
		var valeurs = Array.prototype.slice.call( arguments, 1 );

		return String( texte || '' ).replace( /%(\d)\$s/g, function( tout, rang ) {
			var index = parseInt( rang, 10 ) - 1;

			return index < valeurs.length ? String( valeurs[ index ] ) : tout;
		} );
	}

	/**
	 * Cree un element avec un texte (textContent uniquement).
	 */
	function element( balise, texte, classe ) {
		var noeud = document.createElement( balise );

		if ( undefined !== texte && null !== texte ) {
			noeud.textContent = String( texte );
		}

		if ( classe ) {
			noeud.className = classe;
		}

		return noeud;
	}

	function annoncer( texte ) {
		statut.textContent = texte;
	}

	function avantDeQuitter( evenement ) {
		evenement.preventDefault();
		evenement.returnValue = i18n.quitter || '';

		return i18n.quitter || '';
	}

	function terminer() {
		enCours = false;
		window.removeEventListener( 'beforeunload', avantDeQuitter );
	}

	function echec( message ) {
		terminer();
		annoncer( message || i18n.erreur || '' );
		bouton.disabled    = false;
		bouton.textContent = i18n.reprendre || bouton.textContent;

		if ( abandon ) {
			abandon.disabled = false;
		}

		// Focus rendu au bouton : reprise immediate au clavier.
		bouton.focus();
	}

	/**
	 * Liste de messages { niveau, texte }.
	 */
	function listeMessages( messages ) {
		var liste   = element( 'ul', null, 'ditl-import-messages' );
		var niveaux = i18n.niveaux || {};

		( messages || [] ).forEach( function( message ) {
			var niveau = message && message.niveau ? String( message.niveau ) : 'info';
			var item   = element( 'li', null, 'ditl-import-niveau-' + niveau.replace( /[^a-z]/g, '' ) );

			item.appendChild( element( 'span', ( niveaux[ niveau ] || niveau ) + ' :', 'ditl-import-niveau' ) );
			item.appendChild( document.createTextNode( ' ' + String( message && message.texte ? message.texte : '' ) ) );
			liste.appendChild( item );
		} );

		return liste;
	}

	/**
	 * Tableau des lignes a signaler.
	 */
	function tableau( lignes ) {
		var colonnes  = i18n.colonnes || {};
		var actions   = i18n.actions || {};
		var table     = element( 'table', null, 'widefat striped ditl-import-tableau' );
		var corps     = document.createElement( 'tbody' );
		var tete      = document.createElement( 'thead' );
		var ligneTete = document.createElement( 'tr' );

		table.appendChild( element( 'caption', ( i18n.caption || '' ) + ' (' + lignes.length + ')' ) );

		[ 'ligne', 'id', 'langue', 'titre', 'action', 'messages' ].forEach( function( cle ) {
			var th = element( 'th', colonnes[ cle ] || cle );

			th.setAttribute( 'scope', 'col' );
			ligneTete.appendChild( th );
		} );

		tete.appendChild( ligneTete );
		table.appendChild( tete );

		lignes.forEach( function( ligne ) {
			var tr      = document.createElement( 'tr' );
			var entete  = element( 'th', ligne.ligne );
			var cellule = document.createElement( 'td' );

			entete.setAttribute( 'scope', 'row' );
			tr.appendChild( entete );
			tr.appendChild( element( 'td', ligne.id ) );
			tr.appendChild( element( 'td', ligne.lang ) );
			tr.appendChild( element( 'td', ligne.titre ) );
			tr.appendChild( element( 'td', actions[ ligne.action ] || ligne.action ) );
			cellule.appendChild( listeMessages( ligne.messages ) );
			tr.appendChild( cellule );
			corps.appendChild( tr );
		} );

		table.appendChild( corps );

		return table;
	}

	/**
	 * Rapport final (compteurs, messages, lignes a signaler).
	 */
	function afficherRapport( donnees ) {
		var final     = donnees.rapport || {};
		var compteurs = donnees.compteurs || {};
		var libelles  = i18n.compteurs || {};
		var titre     = element( 'h2', i18n.titreFinal || '' );
		var resume    = element( 'ul', null, 'ditl-import-compteurs' );
		var groupes;
		var lien;

		while ( zoneFinale.firstChild ) {
			zoneFinale.removeChild( zoneFinale.firstChild );
		}

		titre.setAttribute( 'tabindex', '-1' );
		zoneFinale.appendChild( titre );
		zoneFinale.appendChild( element( 'p', i18n.termine || '' ) );

		Object.keys( libelles ).forEach( function( cle ) {
			resume.appendChild( element( 'li', libelles[ cle ] + ' : ' + ( compteurs[ cle ] || 0 ) ) );
		} );

		zoneFinale.appendChild( resume );

		if ( final.messages && final.messages.length ) {
			zoneFinale.appendChild( element( 'h3', i18n.messagesFichier || '' ) );
			zoneFinale.appendChild( listeMessages( final.messages ) );
		}

		if ( final.groupes && Object.keys( final.groupes ).length ) {
			zoneFinale.appendChild( element( 'h3', i18n.messagesGroupes || '' ) );
			groupes = element( 'ul', null, 'ditl-import-groupes' );

			Object.keys( final.groupes ).forEach( function( groupe ) {
				var item = element( 'li', ( i18n.groupe || '' ) + ' ' + groupe );

				item.appendChild( listeMessages( final.groupes[ groupe ] ) );
				groupes.appendChild( item );
			} );

			zoneFinale.appendChild( groupes );
		}

		if ( final.lignes && final.lignes.length ) {
			zoneFinale.appendChild( tableau( final.lignes ) );
		} else {
			zoneFinale.appendChild( element( 'p', i18n.aucuneLigne || '' ) );
		}

		if ( config.urlEcran ) {
			lien      = element( 'a', i18n.nouvelImport || '' );
			lien.href = String( config.urlEcran );
			zoneFinale.appendChild( element( 'p' ) ).appendChild( lien );
		}

		zoneFinale.hidden = false;
		titre.focus();
	}

	function traiter( reponse ) {
		var donnees = reponse.json && reponse.json.data ? reponse.json.data : {};

		if ( ! reponse.json || true !== reponse.json.success ) {
			// Session expiree : admin-ajax repond "0" (HTTP 400) sans connexion,
			// "-1" (HTTP 403) si le jeton de securite n'est plus valable.
			if ( 403 === reponse.http || -1 === reponse.json || ( 400 === reponse.http && 0 === reponse.json ) ) {
				echec( i18n.session );
				return;
			}

			if ( donnees.occupe && tentatives < 10 ) {
				tentatives++;
				annoncer( i18n.occupe || '' );
				window.setTimeout( lot, 3000 );
				return;
			}

			echec( donnees.message || i18n.erreur );
			return;
		}

		tentatives  = 0;
		barre.max   = Math.max( 1, parseInt( donnees.total, 10 ) || 1 );
		barre.value = parseInt( donnees.position, 10 ) || 0;
		annoncer( formater( i18n.progression, donnees.position, donnees.total ) );

		if ( donnees.termine ) {
			terminer();
			bouton.hidden = true;

			if ( abandon ) {
				abandon.hidden = true;
			}

			afficherRapport( donnees );
			return;
		}

		lot();
	}

	function lot() {
		var corps = new FormData();

		corps.append( 'action', 'ditl_import_lot' );
		corps.append( 'jeton', bouton.getAttribute( 'data-jeton' ) || '' );
		corps.append( 'nonce', bouton.getAttribute( 'data-nonce' ) || '' );

		window.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: corps
		} ).then( function( reponse ) {
			return reponse.text().then( function( texte ) {
				var json = null;

				try {
					json = JSON.parse( texte );
				} catch ( erreur ) {
					json = null;
				}

				return { http: reponse.status, json: json };
			} );
		} ).then( traiter ).catch( function() {
			echec( i18n.erreurReseau );
		} );
	}

	bouton.addEventListener( 'click', function() {
		if ( enCours ) {
			return;
		}

		enCours         = true;
		tentatives      = 0;
		bouton.disabled = true;

		if ( abandon ) {
			abandon.disabled = true;
		}

		progression.hidden = false;
		annoncer( i18n.demarrage || '' );
		window.addEventListener( 'beforeunload', avantDeQuitter );
		lot();
	} );
}() );
