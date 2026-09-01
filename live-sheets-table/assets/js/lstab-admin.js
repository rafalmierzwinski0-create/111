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
			var visible = row.querySelector( 'input[type="checkbox"]' );

			if ( ! label || ! visible || label.disabled ) {
				return;
			}

			settings.push( {
				source: label.placeholder || '',
				label: label.value,
				visible: visible.checked
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

/**
 * Live Sheets Table — click what you want gone.
 *
 * The picker is a plain HTML table of the stored sheet. Clicking a heading or a
 * row marks it hidden, and the marking is what gets submitted: columns through
 * the checkboxes in the Columns card, rows through hidden fields written here.
 * Without JavaScript the checkboxes still work on their own, so nothing is lost
 * — the picker is a faster way to reach the same settings, not the only way.
 */
( function () {
	'use strict';

	var picker = document.getElementById( 'lstab-picker' );
	if ( ! picker ) {
		return;
	}

	var settings = window.lstabAdmin || {};
	var i18n = settings.i18n || {};
	var fields = document.getElementById( 'lstab-hidden-rows-fields' );
	var chips = document.getElementById( 'lstab-hidden-rows-chips' );
	var empty = document.querySelector( '.lstab-picker-empty' );

	/**
	 * The checkbox in the Columns card that governs one column.
	 *
	 * @param {number} index Column position.
	 * @return {HTMLInputElement|null} Checkbox.
	 */
	function columnBox( index ) {
		return document.querySelector(
			'input[type="checkbox"][name="columns[' + index + '][visible]"]'
		);
	}

	/**
	 * Show a column as kept or dropped, everywhere it appears.
	 *
	 * @param {number}  index  Column position.
	 * @param {boolean} hidden Whether it is dropped.
	 * @return {void}
	 */
	function paintColumn( index, hidden ) {
		var cells = picker.querySelectorAll( '[data-lstab-column="' + index + '"]' );

		Array.prototype.forEach.call( cells, function ( cell ) {
			cell.classList.toggle( 'is-hidden', hidden );

			var header = cell.closest( 'th' );
			if ( header ) {
				header.classList.toggle( 'is-hidden', hidden );
			}
		} );

		var toggle = picker.querySelector( 'button[data-lstab-column="' + index + '"]' );
		if ( toggle ) {
			toggle.setAttribute( 'aria-pressed', hidden ? 'true' : 'false' );
			toggle.closest( 'th' ).classList.toggle( 'is-hidden', hidden );
		}
	}

	/**
	 * Every row key currently marked hidden, in the order they appear.
	 *
	 * @return {Array} Keys.
	 */
	function hiddenKeys() {
		var keys = [];

		Array.prototype.forEach.call(
			picker.querySelectorAll( 'tr.is-hidden[data-lstab-key]' ),
			function ( row ) {
				var key = row.getAttribute( 'data-lstab-key' );
				if ( key && keys.indexOf( key ) === -1 ) {
					keys.push( key );
				}
			}
		);

		return keys;
	}

	/**
	 * Rewrite the submitted fields and the list of what is hidden.
	 *
	 * @return {void}
	 */
	function syncRows() {
		var keys = hiddenKeys();

		fields.innerHTML = '';
		chips.innerHTML = '';

		keys.forEach( function ( key ) {
			var field = document.createElement( 'input' );
			field.type = 'hidden';
			field.name = 'hidden_rows[]';
			field.value = key;
			fields.appendChild( field );

			var chip = document.createElement( 'li' );
			chip.className = 'lstab-chip';

			var label = document.createElement( 'span' );
			label.textContent = key;
			chip.appendChild( label );

			var remove = document.createElement( 'button' );
			remove.type = 'button';
			remove.className = 'lstab-chip-remove';
			remove.setAttribute( 'aria-label', ( i18n.showRowAgain || 'Show this row again' ) + ': ' + key );
			remove.textContent = '×';
			remove.addEventListener( 'click', function () {
				setRow( key, false );
			} );
			chip.appendChild( remove );

			chips.appendChild( chip );
		} );

		if ( empty ) {
			empty.hidden = keys.length > 0;
		}
	}

	/**
	 * Mark every row carrying one key as hidden or shown.
	 *
	 * Two rows can legitimately say the same thing, and hiding one while
	 * leaving its twin would look like the click had failed.
	 *
	 * @param {string}  key    Row key.
	 * @param {boolean} hidden Whether to hide it.
	 * @return {void}
	 */
	function setRow( key, hidden ) {
		var rows = picker.querySelectorAll( 'tr[data-lstab-key="' + ( window.CSS && CSS.escape ? CSS.escape( key ) : key ) + '"]' );

		Array.prototype.forEach.call( rows, function ( row ) {
			row.classList.toggle( 'is-hidden', hidden );

			var toggle = row.querySelector( 'button[data-lstab-row]' );
			if ( toggle ) {
				toggle.setAttribute( 'aria-pressed', hidden ? 'true' : 'false' );
			}
		} );

		syncRows();
	}

	picker.addEventListener( 'click', function ( event ) {
		var toggle = event.target.closest( '.lstab-picker-toggle' );
		if ( ! toggle || toggle.disabled ) {
			return;
		}

		var column = toggle.getAttribute( 'data-lstab-column' );

		if ( null !== column ) {
			var box = columnBox( column );
			var hidden = 'true' !== toggle.getAttribute( 'aria-pressed' );

			if ( box ) {
				box.checked = ! hidden;
			}

			paintColumn( column, hidden );
			return;
		}

		var key = toggle.getAttribute( 'data-lstab-row' );
		if ( key ) {
			setRow( key, 'true' !== toggle.getAttribute( 'aria-pressed' ) );
		}
	} );

	// The Columns card can still be used directly, and the picker follows it.
	Array.prototype.forEach.call(
		document.querySelectorAll( 'input[type="checkbox"][name^="columns["]' ),
		function ( box ) {
			box.addEventListener( 'change', function () {
				var match = box.name.match( /columns\[(\d+)\]/ );
				if ( match ) {
					paintColumn( match[ 1 ], ! box.checked );
				}
			} );
		}
	);

	syncRows();
}() );
