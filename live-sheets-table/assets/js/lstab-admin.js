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

	/*
	 * Panes. One form still, so the save button saves everything wherever you
	 * were standing — these only decide what is on screen.
	 *
	 * The chosen pane goes in the address bar so that reloading, or coming
	 * back from a save, lands you where you left off rather than throwing you
	 * to the front of the form.
	 */
	( function panes() {
		var nav = document.getElementById( 'lstab-panes' );

		if ( ! nav ) {
			return;
		}

		var tabs = nav.querySelectorAll( '[data-lstab-goto]' );

		var show = function ( wanted ) {
			Array.prototype.forEach.call( tabs, function ( tab ) {
				var on = tab.getAttribute( 'data-lstab-goto' ) === wanted;
				tab.classList.toggle( 'is-on', on );
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );

			Array.prototype.forEach.call(
				document.querySelectorAll( '[data-lstab-pane]' ),
				function ( pane ) {
					pane.hidden = pane.getAttribute( 'data-lstab-pane' ) !== wanted;
				}
			);

			// Blocks that belong to more than one pane — the preview, which is
			// worth seeing while editing both the sheet and its appearance.
			Array.prototype.forEach.call(
				document.querySelectorAll( '[data-lstab-panes]' ),
				function ( block ) {
					var list = block.getAttribute( 'data-lstab-panes' ).split( /\s+/ );
					block.hidden = list.indexOf( wanted ) === -1;
				}
			);
		};

		Array.prototype.forEach.call( tabs, function ( tab ) {
			tab.addEventListener( 'click', function () {
				var wanted = tab.getAttribute( 'data-lstab-goto' );

				show( wanted );

				if ( window.history && window.history.replaceState ) {
					window.history.replaceState( null, '', '#' + wanted );
				}
			} );
		} );

		/*
		 * Saving reloads the page, and a hash never reaches the server, so
		 * without this every save threw you back to the first pane — three
		 * clicks to carry on where you were. The browser remembers instead.
		 */
		var remember = function ( name ) {
			try {
				window.sessionStorage.setItem( 'lstabPane', name );
			} catch ( error ) {
				// Private windows and blocked storage: not worth a word.
			}
		};

		var remembered = function () {
			try {
				return window.sessionStorage.getItem( 'lstabPane' ) || '';
			} catch ( error ) {
				return '';
			}
		};

		Array.prototype.forEach.call( tabs, function ( tab ) {
			tab.addEventListener( 'click', function () {
				remember( tab.getAttribute( 'data-lstab-goto' ) );
			} );
		} );

		var opening = ( window.location.hash || '' ).replace( '#', '' ) || remembered();

		if ( opening && document.querySelector( '[data-lstab-pane="' + opening + '"]' ) ) {
			show( opening );
		}
	}() );

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

	/*
	 * The bundled example has no Google card, so it has no preview button and
	 * no tab picker either. Reaching for them regardless threw, and a throw
	 * here stopped the rest of this file from running at all — which is why the
	 * example's colours and style did nothing: not one of those controls had
	 * been wired up by the time the error landed.
	 */
	if ( button ) {
		button.addEventListener( 'click', function () {
			if ( ! inFlight ) {
				loadPreview();
			}
		} );
	}

	if ( tabsSelect ) {
		tabsSelect.addEventListener( 'change', function () {
			var selected = tabsSelect.options[ tabsSelect.selectedIndex ];
			if ( selected && tabNameField ) {
				tabNameField.value = selected.textContent;
			}
			loadPreview( tabsSelect.value );
		} );
	}

	// ------------------------------------------------------------ own CSS

	/*
	 * Rules typed into the CSS field, shown on the preview as they are typed.
	 * The rewriting that confines them to one table is done on the server, so
	 * the preview is styled by exactly the code the published page will use.
	 */
	( function () {
		var field = document.getElementById( 'lstab-custom-css' );

		if ( ! field || ! stage ) {
			return;
		}

		var sheet = document.createElement( 'style' );
		var timer = null;
		var pending = null;

		sheet.className = 'lstab-live-css';
		stage.parentNode.insertBefore( sheet, stage.nextSibling );

		/**
		 * Ask the server for the scoped form and put it on the page.
		 *
		 * @return {void}
		 */
		function refresh() {
			var css = field.value;

			// The stored rules arrived with the server-rendered preview and are
			// confined to the saved table's own selector. Once this is driving
			// the preview they would be a second, stale answer.
			var stored = stage.querySelector( 'style.lstab-custom-css' );
			if ( stored ) {
				stored.parentNode.removeChild( stored );
			}

			if ( ! css.trim() ) {
				sheet.textContent = '';
				return;
			}

			if ( pending === css ) {
				return;
			}
			pending = css;

			window.wp.apiFetch( {
				path: '/live-sheets-table/v1/scoped-css',
				method: 'POST',
				data: {
					css: css,
					selector: '[data-lstab-preview="stage"]'
				}
			} ).then(
				function ( response ) {
					// A slow answer to an older keystroke must not overwrite a
					// newer one.
					if ( pending === css ) {
						sheet.textContent = response.css || '';
					}
				},
				function () {
					// Nothing to say: the field is still there, the preview is
					// simply one edit behind until the next keystroke.
				}
			);
		}

		field.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( refresh, 400 );
		} );

		field.addEventListener( 'change', refresh );
	}() );

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

	/**
	 * Turn any CSS colour into the #rrggbb a colour input will accept.
	 *
	 * A preset writes its colours however it likes — a name, three digits,
	 * rgb() — but <input type="color"> takes one form only and silently keeps
	 * its old value when handed anything else. Letting the browser parse it
	 * through a throwaway element is the only way to cover every spelling.
	 *
	 * @param {string} value A CSS colour, or nothing.
	 * @return {string} A #rrggbb string, or '' when it could not be read.
	 */
	function toHex( value ) {
		var text = ( value || '' ).trim();

		if ( ! text ) {
			return '';
		}

		if ( /^#[0-9a-f]{6}$/i.test( text ) ) {
			return text.toLowerCase();
		}

		var probe = document.createElement( 'span' );

		probe.style.color = text;

		if ( ! probe.style.color ) {
			return '';
		}

		probe.style.display = 'none';
		document.body.appendChild( probe );

		var parts = ( window.getComputedStyle( probe ).color || '' ).match( /[0-9.]+/g );

		document.body.removeChild( probe );

		if ( ! parts || parts.length < 3 ) {
			return '';
		}

		return '#' + parts.slice( 0, 3 ).map( function ( part ) {
			return ( '0' + parseInt( part, 10 ).toString( 16 ) ).slice( -2 );
		} ).join( '' );
	}

	/**
	 * Show, in the swatch itself, the colour the preset is now supplying.
	 *
	 * Clearing an override used to leave the old colour sitting in the picker,
	 * which read as "still set" even though nothing was. Reading the value back
	 * off the previewed table means the swatch shows what the table is actually
	 * using — so a reset looks like a reset.
	 *
	 * Call it only after the override has been taken off the table, or it reads
	 * back the very value being cleared.
	 *
	 * @param {Element} swatch The swatch to update.
	 * @return {void}
	 */
	function followPreset( swatch ) {
		var picker = swatch.querySelector( '.lstab-color-input' );
		var table = stage.querySelector( '.lstab' );
		var resolved = table
			? window.getComputedStyle( table ).getPropertyValue( swatch.getAttribute( 'data-lstab-var' ) )
			: '';

		picker.value = toHex( resolved ) || '#ffffff';
	}

	/**
	 * Put one swatch back to following the preset.
	 *
	 * @param {Element} swatch The swatch to clear.
	 * @return {void}
	 */
	function clearSwatch( swatch ) {
		swatch.querySelector( '.lstab-color-value' ).value = '';
		swatch.querySelector( '.lstab-color-input' ).setAttribute( 'data-lstab-unset', '1' );
		swatch.querySelector( '.lstab-color-clear' ).disabled = true;
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
			clearSwatch( swatch );
			applyAppearance();
			followPreset( swatch );
		} );
	} );

	Array.prototype.forEach.call( metricInputs, function ( input ) {
		input.addEventListener( 'change', applyAppearance );
	} );

	if ( resetAppearance ) {
		resetAppearance.addEventListener( 'click', function () {
			Array.prototype.forEach.call( swatches, clearSwatch );
			Array.prototype.forEach.call( metricInputs, function ( input ) {
				input.value = 'normal';
			} );

			var table = stage.querySelector( '.lstab' );
			if ( table ) {
				table.removeAttribute( 'style' );
			}

			// Only now that the overrides are off the table can each swatch be
			// shown the colour the preset supplies in their place.
			Array.prototype.forEach.call( swatches, followPreset );
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

			// A swatch with no override of its own is showing the old preset's
			// colour until it is told otherwise.
			Array.prototype.forEach.call( swatches, function ( swatch ) {
				if ( ! swatch.querySelector( '.lstab-color-value' ).value ) {
					followPreset( swatch );
				}
			} );
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
	/**
	 * Redraw the preview from the copy already stored.
	 *
	 * Renaming a column or hiding one changes the markup, not just its styling,
	 * so the table has to be built again — but not by asking Google, which is
	 * slow, is a request nobody asked for, and is impossible for the bundled
	 * example. The server has the rows already; this hands it the settings as
	 * they stand in the form, unsaved.
	 *
	 * @return {void}
	 */
	function redrawFromStored() {
		var id = sourceIdField ? parseInt( sourceIdField.value, 10 ) || 0 : 0;

		if ( ! id ) {
			return;
		}

		window.wp.apiFetch( {
			path: '/live-sheets-table/v1/redraw',
			method: 'POST',
			data: {
				sourceId: id,
				style: selectedPreset(),
				layout: layoutSelect ? layoutSelect.value : 'table',
				columns: columnSettings()
			}
		} ).then(
			function ( response ) {
				stage.innerHTML = response.html || '';
				applyAppearance();
				if ( window.lstabInit ) {
					window.lstabInit();
				}
			},
			function () {
				// Nothing to say: the preview simply stays as it was until the
				// next change, or until the save that makes it certain.
			}
		);
	}

	/*
	 * A rename shows as it is typed rather than only after a save. "change"
	 * fires on blur for a text field, so this is one redraw per rename, not one
	 * per keystroke.
	 */
	Array.prototype.forEach.call( columnRows, function ( row ) {
		row.addEventListener( 'change', function () {
			if ( stage.querySelector( '.lstab-table' ) ) {
				redrawFromStored();
			}
		} );

		// And as it is typed, for the field somebody is actually looking at.
		var label = row.querySelector( 'input[type="text"]' );

		if ( label ) {
			var typing = null;

			label.addEventListener( 'input', function () {
				window.clearTimeout( typing );
				typing = window.setTimeout( redrawFromStored, 450 );
			} );
		}
	} );

	if ( firstRowHeader ) {
		firstRowHeader.addEventListener( 'change', function () {
			if ( ! stage.querySelector( '.lstab-table' ) ) {
				return;
			}

			// Which line is the heading is a question about the sheet, not
			// about the stored table, so this one does go to Google — where
			// there is a sheet to go to.
			if ( urlInput && urlInput.value ) {
				loadPreview( gidField ? gidField.value : '' );
			} else {
				redrawFromStored();
			}
		} );
	}

	if ( urlInput ) {
		urlInput.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				loadPreview();
			}
		} );

		// Editing a source that has a sheet behind it: fetch once on opening,
		// which is also what fills the tab picker.
		if ( urlInput.value ) {
			loadPreview( gidField ? gidField.value : '' );
		}
	}
}() );

