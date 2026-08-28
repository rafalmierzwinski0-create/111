/**
 * Live Sheets Table — add/edit screen.
 *
 * Fetches a parsed preview of the pasted sheet before anything is saved, so a
 * wrong tab or a misread header row is visible here rather than on a live page.
 */
( function () {
	'use strict';

	var settings = window.lstabAdmin || {};
	var i18n = settings.i18n || {};

	var form = document.getElementById( 'lstab-source-form' );
	if ( ! form ) {
		return;
	}

	var urlInput = document.getElementById( 'lstab-sheet-url' );
	var button = document.getElementById( 'lstab-preview-button' );
	var spinner = document.getElementById( 'lstab-spinner' );
	var status = document.getElementById( 'lstab-preview-status' );
	var preview = document.getElementById( 'lstab-preview' );
	var tabsWrap = document.getElementById( 'lstab-tabs-wrap' );
	var tabsSelect = document.getElementById( 'lstab-tabs' );
	var gidField = document.getElementById( 'lstab-gid' );
	var tabNameField = document.getElementById( 'lstab-tab-name' );
	var titleField = document.getElementById( 'lstab-title' );
	var firstRowHeader = document.getElementById( 'lstab-first-row-header' );

	var inFlight = false;

	function sprintf( template, values ) {
		return String( template ).replace( /%(\d)\$s/g, function ( match, index ) {
			var value = values[ Number( index ) - 1 ];
			return undefined === value ? match : String( value );
		} );
	}

	function setStatus( message, state ) {
		status.textContent = message || '';
		status.className = 'lstab-preview-status' + ( state ? ' is-' + state : '' );
	}

	function setBusy( busy ) {
		inFlight = busy;
		button.disabled = busy;
		spinner.classList.toggle( 'is-active', busy );
	}

	function renderTabs( tabs, selectedGid ) {
		if ( ! tabs || ! tabs.length ) {
			tabsWrap.hidden = true;
			return;
		}

		tabsSelect.innerHTML = '';

		tabs.forEach( function ( tab ) {
			var option = document.createElement( 'option' );
			option.value = tab.gid;
			option.textContent = tab.name;
			if ( String( tab.gid ) === String( selectedGid ) ) {
				option.selected = true;
			}
			tabsSelect.appendChild( option );
		} );

		tabsWrap.hidden = false;

		var selected = tabsSelect.options[ tabsSelect.selectedIndex ];
		if ( selected ) {
			tabNameField.value = selected.textContent;
		}
	}

	function loadPreview( gid ) {
		var url = ( urlInput.value || '' ).trim();

		if ( ! url ) {
			setStatus( i18n.emptyUrl, 'error' );
			return;
		}

		setBusy( true );
		setStatus( i18n.loading );

		window.wp.apiFetch( {
			path: '/live-sheets-table/v1/preview',
			method: 'POST',
			data: {
				url: url,
				gid: undefined === gid ? '' : String( gid ),
				firstRowHeader: firstRowHeader ? firstRowHeader.checked : true
			}
		} ).then( function ( response ) {
			setBusy( false );

			preview.innerHTML = response.html || '';
			if ( window.lstabInit ) {
				window.lstabInit();
			}

			gidField.value = response.gid;

			var message = sprintf( i18n.rowsFound, [ response.rowCount, response.colCount ] );
			if ( response.truncated ) {
				message += ' ' + i18n.truncated;
			}
			setStatus( message, 'ok' );

			renderTabs( response.tabs, response.gid );

			if ( ! response.tabs || ! response.tabs.length ) {
				tabsWrap.hidden = true;
			}

			// Offer a sensible default title once we know the tab name.
			if ( titleField && ! titleField.value && tabNameField.value ) {
				titleField.value = tabNameField.value;
			}
		} ).catch( function ( error ) {
			setBusy( false );
			preview.innerHTML = '';
			setStatus( ( error && error.message ) || i18n.failed, 'error' );
			tabsWrap.hidden = true;
		} );
	}

	button.addEventListener( 'click', function () {
		if ( ! inFlight ) {
			loadPreview();
		}
	} );

	tabsSelect.addEventListener( 'change', function () {
		var selected = tabsSelect.options[ tabsSelect.selectedIndex ];
		if ( selected ) {
			tabNameField.value = selected.textContent;
		}
		loadPreview( tabsSelect.value );
	} );

	if ( firstRowHeader ) {
		firstRowHeader.addEventListener( 'change', function () {
			if ( preview.querySelector( '.lstab-table' ) ) {
				loadPreview( gidField.value );
			}
		} );
	}

	urlInput.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key ) {
			event.preventDefault();
			loadPreview();
		}
	} );

	// Editing an existing source: show what is stored right away.
	if ( urlInput.value ) {
		loadPreview( gidField.value );
	}
}() );
