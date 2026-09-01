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
	var stage = document.getElementById( 'lstab-preview-stage' ) || preview;
	var widthButtons = document.querySelectorAll( '.lstab-width-button' );
	var tabsWrap = document.getElementById( 'lstab-tabs-wrap' );
	var tabsSelect = document.getElementById( 'lstab-tabs' );
	var gidField = document.getElementById( 'lstab-gid' );
	var tabNameField = document.getElementById( 'lstab-tab-name' );
	var titleField = document.getElementById( 'lstab-title' );
	var firstRowHeader = document.getElementById( 'lstab-first-row-header' );
	var presetInputs = form.querySelectorAll( 'input[name="style_preset"]' );
	var layoutSelect = document.getElementById( 'lstab-layout' );

	var inFlight = false;

	function sprintf( template, values ) {
		return String( template ).replace( /%(\d)\$s/g, function ( match, index ) {
			var value = values[ Number( index ) - 1 ];
			return undefined === value ? match : String( value );
		} );
	}

	/**
	 * The preset the author currently has selected.
	 *
	 * @return {string} Preset slug, or '' when nothing is checked.
	 */
	function selectedPreset() {
		for ( var i = 0; i < presetInputs.length; i++ ) {
			if ( presetInputs[ i ].checked ) {
				return presetInputs[ i ].value;
			}
		}
		return '';
	}

	/**
	 * Restyle the preview that is already on screen.
	 *
	 * A preset is presentation only, so swapping the class beats refetching the
	 * sheet from Google just to render the same rows in a different skin.
	 *
	 * @param {string} preset Preset slug.
	 */
	function applyPreset( preset ) {
		var table = stage.querySelector( '.lstab' );
		if ( ! table || ! preset ) {
			return;
		}


		( settings.presets || [] ).forEach( function ( slug ) {
			table.classList.remove( 'lstab-style-' + slug );
		} );
		table.classList.add( 'lstab-style-' + preset );
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

	var sourceIdField = form.querySelector( 'input[name="source_id"]' );
	var rawWrap = document.getElementById( 'lstab-raw-wrap' );
	var rawText = document.getElementById( 'lstab-raw' );
	var rawMeta = document.getElementById( 'lstab-raw-meta' );
	var columnRows = document.querySelectorAll( '.lstab-column-list tbody tr' );

	/**
	 * Read the column settings out of the form.
	 *
	 * Position is the key, so the array order is the column order. Before the
	 * first sync the rows are placeholders with their controls disabled, and
	 * an empty list leaves the preview showing every column.
	 *
	 * @return {Array} One entry per column.
	 */
	function columnSettings() {
		var settings = [];

		Array.prototype.forEach.call( columnRows, function ( row ) {
			var label = row.querySelector( 'input[type="text"]' );
			var state = row.querySelector( 'input[name$="[hidden]"]' );

			if ( ! label || label.disabled ) {
				return;
			}

			settings.push( {
				source: label.placeholder || '',
				label: label.value,
				// The add-on writes into this field as you click, so reading it
				// here keeps the preview honest whether the add-on is there or
				// not, without the free plugin knowing anything about it.
				visible: ! ( state && '1' === state.value )
			} );
		} );

		return settings;
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
				firstRowHeader: firstRowHeader ? firstRowHeader.checked : true,
				style: selectedPreset(),
				layout: layoutSelect ? layoutSelect.value : 'table',
				columns: columnSettings(),
				sourceId: sourceIdField ? parseInt( sourceIdField.value, 10 ) || 0 : 0
			}
		} ).then( function ( response ) {
			setBusy( false );

			stage.innerHTML = response.html || '';
			applyAppearance();
			if ( window.lstabInit ) {
				window.lstabInit();
			}

			gidField.value = response.gid;

			// Only offered once there is something to show. A row that came
			// back with the wrong number of cells is named here too, since
			// this is where you would go looking for it.
			if ( rawWrap && rawText ) {
				rawWrap.hidden = ! response.raw;
				rawText.value = response.raw || '';

				if ( rawMeta ) {
					var parts = [];
					if ( response.rawBytes ) {
						parts.push( sprintf( i18n.rawBytes, [ response.rawBytes ] ) );
					}
					if ( response.ragged && response.ragged.rows ) {
						var numbers = response.ragged.rows.map( function ( entry ) {
							return entry.row;
						} ).join( ', ' );
						parts.push( sprintf( i18n.rawRagged, [ numbers ] ) );
					}
					rawMeta.textContent = parts.join( ' ' );
				}
			}

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
			stage.innerHTML = '';
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

	// ---------------------------------------------------------- appearance

	var swatches = document.querySelectorAll( '.lstab-swatch' );
	var metricInputs = document.querySelectorAll( '.lstab-metric-input' );
	var resetAppearance = document.getElementById( 'lstab-reset-appearance' );

	/**
	 * Push every override onto the previewed table.
	 *
	 * Overrides are CSS custom properties, so applying them is a property set
	 * on the element — no restyle round trip and no regenerated markup.
	 */
	function applyAppearance() {
		var table = stage.querySelector( '.lstab' );
		if ( ! table ) {
			return;
		}

		Array.prototype.forEach.call( swatches, function ( swatch ) {
			var property = swatch.getAttribute( 'data-lstab-var' );
			var value = swatch.querySelector( '.lstab-color-value' ).value;

			if ( value ) {
				table.style.setProperty( property, value );
			} else {
				table.style.removeProperty( property );
			}
		} );

		// Metrics map one choice onto several properties, so the server is the
		// single source of truth for that mapping; mirror it via a data blob.
		Array.prototype.forEach.call( metricInputs, function ( input ) {
			var token = input.getAttribute( 'data-lstab-token' );
			var map = ( settings.metrics || {} )[ token ] || {};
			var chosen = map[ input.value ] || {};

			Object.keys( map ).forEach( function ( choice ) {
				Object.keys( map[ choice ] || {} ).forEach( function ( property ) {
					table.style.removeProperty( property );
				} );
			} );

			Object.keys( chosen ).forEach( function ( property ) {
				table.style.setProperty( property, chosen[ property ] );
			} );
		} );
	}

	Array.prototype.forEach.call( swatches, function ( swatch ) {
		var picker = swatch.querySelector( '.lstab-color-input' );
		var hidden = swatch.querySelector( '.lstab-color-value' );
		var clear = swatch.querySelector( '.lstab-color-clear' );

		picker.addEventListener( 'input', function () {
			hidden.value = picker.value;
			picker.removeAttribute( 'data-lstab-unset' );
			clear.disabled = false;
			applyAppearance();
		} );

		clear.addEventListener( 'click', function () {
			hidden.value = '';
			picker.setAttribute( 'data-lstab-unset', '1' );
			clear.disabled = true;
			applyAppearance();
		} );
	} );

	Array.prototype.forEach.call( metricInputs, function ( input ) {
		input.addEventListener( 'change', applyAppearance );
	} );

	if ( resetAppearance ) {
		resetAppearance.addEventListener( 'click', function () {
			Array.prototype.forEach.call( swatches, function ( swatch ) {
				swatch.querySelector( '.lstab-color-value' ).value = '';
				swatch.querySelector( '.lstab-color-input' ).setAttribute( 'data-lstab-unset', '1' );
				swatch.querySelector( '.lstab-color-clear' ).disabled = true;
			} );
			Array.prototype.forEach.call( metricInputs, function ( input ) {
				input.value = 'normal';
			} );

			var table = stage.querySelector( '.lstab' );
			if ( table ) {
				table.removeAttribute( 'style' );
			}
		} );
	}

	// Constrain the stage so the author can see the table and the card layout
	// without resizing the browser. The container query does the rest.
	Array.prototype.forEach.call( widthButtons, function ( widthButton ) {
		widthButton.addEventListener( 'click', function () {
			var width = widthButton.getAttribute( 'data-lstab-width' );

			stage.style.maxWidth = width ? width + 'px' : '';

			Array.prototype.forEach.call( widthButtons, function ( other ) {
				var active = other === widthButton;
				other.classList.toggle( 'is-active', active );
				other.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
			} );
		} );
	} );

	Array.prototype.forEach.call( presetInputs, function ( input ) {
		input.addEventListener( 'change', function () {
			applyPreset( input.value );
		} );
	} );

	// Layout is a class too, so swap it in place rather than refetching.
	if ( layoutSelect ) {
		layoutSelect.addEventListener( 'change', function () {
			var table = stage.querySelector( '.lstab' );
			if ( ! table ) {
				return;
			}

			[ 'table', 'auto', 'cards' ].forEach( function ( value ) {
				table.classList.remove( 'lstab-layout-' + value );
			} );

			if ( 'auto' !== layoutSelect.value ) {
				table.classList.add( 'lstab-layout-' + layoutSelect.value );
			}

			// The slider has to re-measure once the layout changes.
			table.dispatchEvent( new CustomEvent( 'lstab:resize' ) );
		} );
	}

	// Hiding or renaming changes the markup itself, so the preview is rebuilt
	// rather than restyled. A text field only fires this on blur, so a rename
	// costs one round trip, not one per keystroke.
	Array.prototype.forEach.call( columnRows, function ( row ) {
		row.addEventListener( 'change', function () {
			if ( stage.querySelector( '.lstab-table' ) ) {
				loadPreview( gidField.value );
			}
		} );
	} );

	if ( firstRowHeader ) {
		firstRowHeader.addEventListener( 'change', function () {
			if ( stage.querySelector( '.lstab-table' ) ) {
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

