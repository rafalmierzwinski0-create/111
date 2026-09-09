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
		/*
		 * A plugin screen with no editor on it — the list, the settings — is
		 * the end of whatever a save was carrying. A save that works lands on
		 * the list, so without this the tab it was made from would sit in
		 * storage waiting to open the next sheet somewhere nobody asked for.
		 */
		try {
			window.sessionStorage.removeItem( 'lstabPane' );
		} catch ( error ) {
			// Private windows and blocked storage: not worth a word.
		}

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
		var PANE_KEY = 'lstabPane';
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

			/*
			 * The columns-and-rows tab gets the whole width. The small preview
			 * is no use there — the picker under it shows the entire sheet with
			 * what you have taken out struck through, which is a better preview
			 * than the preview — and squeezing the picker into half the screen
			 * to keep a box nobody is looking at left the two columns wildly
			 * different lengths.
			 */
			var grid = document.querySelector( '.lstab-editor-grid' );

			if ( grid ) {
				grid.classList.toggle( 'is-solo', 'hide' === wanted );
			}
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
		 * clicks to carry on where you were.
		 *
		 * Written when the form is submitted rather than when a tab is
		 * clicked, and read exactly once. Remembering the click meant "Add a
		 * sheet" opened wherever the last person had been working, which is
		 * not where a new sheet starts: a new one starts at the beginning,
		 * the same as opening an existing sheet does.
		 */
		var sourceNow = function () {
			var match = /[?&]source=(\d+)/.exec( window.location.search );

			return match ? match[ 1 ] : 'new';
		};

		var remember = function ( name ) {
			try {
				window.sessionStorage.setItem(
					PANE_KEY,
					JSON.stringify( { pane: name, source: sourceNow(), at: Date.now() } )
				);
			} catch ( error ) {
				// Private windows and blocked storage: not worth a word.
			}
		};

		/**
		 * The pane a save was made from, if this load is that save coming back.
		 *
		 * Taken once and thrown away, so opening the editor again later starts
		 * at the beginning rather than somewhere a previous visit ended up.
		 *
		 * @return {string} Pane name, or an empty string.
		 */
		var remembered = function () {
			var raw;

			try {
				raw = window.sessionStorage.getItem( PANE_KEY );
				window.sessionStorage.removeItem( PANE_KEY );
			} catch ( error ) {
				return '';
			}

			if ( ! raw ) {
				return '';
			}

			var saved;

			try {
				saved = JSON.parse( raw );
			} catch ( error ) {
				return '';
			}

			if ( ! saved || ! saved.pane ) {
				return '';
			}

			// A save is a round trip of seconds. Anything older is a leftover
			// from a flow that went somewhere else, and the tab it names has
			// nothing to do with the screen now being opened.
			if ( ! saved.at || Date.now() - saved.at > 120000 ) {
				return '';
			}

			// Saving a new sheet arrives back with the id it was given, so
			// that one change of address is the same sheet; any other is not.
			var here = sourceNow();

			if ( saved.source !== here && ! ( 'new' === saved.source && 'new' !== here ) ) {
				return '';
			}

			return saved.pane;
		};

		var form = document.getElementById( 'lstab-source-form' );

		if ( form ) {
			form.addEventListener( 'submit', function () {
				var on = nav.querySelector( '[data-lstab-goto].is-on' );

				remember( on ? on.getAttribute( 'data-lstab-goto' ) : '' );
			} );
		}

		// Read first either way, so a load that already knows its pane from the
		// address still clears what a save left behind.
		var saved = remembered();
		var opening = ( window.location.hash || '' ).replace( '#', '' ) || saved;

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
	var layoutInputs = form.querySelectorAll( 'input[data-lstab-layout]' );
	/*
	 * The two pinning settings, which the preview used to ignore completely:
	 * the class is written into the table when the page is built, and nothing
	 * changed it afterwards — so clearing "keep the first column in view" left
	 * the column pinned and the screen said the setting did nothing.
	 */
	var pins = {
		sticky_first: 'lstab-sticky-first',
		sticky_head: 'lstab-sticky-head'
	};
	var pagingToggle = document.getElementById( 'lstab-paging' );
	var pagingRows = document.getElementById( 'lstab-paging-rows' );

	var inFlight = false;

	/**
	 * Whether one of the two pinning settings is ticked.
	 *
	 * @param {string} name The field's name.
	 * @return {boolean} Whether it is on.
	 */
	function pinned( name ) {
		var box = form.querySelector( 'input[name="' + name + '"]' );

		return box ? box.checked : true;
	}

	/**
	 * Which of the three narrow-screen layouts is chosen.
	 *
	 * @return {string} 'table', 'auto' or 'cards'.
	 */
	function selectedLayout() {
		var picked = 'table';

		Array.prototype.forEach.call( layoutInputs, function ( input ) {
			if ( input.checked ) {
				picked = input.value;
			}
		} );

		return picked;
	}

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

	var tabsNote = document.getElementById( 'lstab-tabs-note' );

	/**
	 * Fill the tab picker from a sheet's list of tabs.
	 *
	 * The field is drawn by the page already carrying the tab this source is
	 * set to, so this only ever adds the others. When the list cannot be read
	 * it says so beside the field rather than taking the field away: a sheet
	 * still has the tab it was saved with, and hiding the one control that
	 * could change it is not an answer to Google being slow.
	 *
	 * @param {Array}  tabs        Tabs as the sheet reports them.
	 * @param {string} selectedGid The tab being previewed.
	 * @return {void}
	 */
	function renderTabs( tabs, selectedGid ) {
		var known = tabNameField && tabNameField.value;

		if ( ! tabs || ! tabs.length ) {
			// Nothing to choose from. Keep whatever the source already has.
			tabsWrap.hidden = ! known;

			if ( tabsNote ) {
				tabsNote.textContent = known ? i18n.noTabs || '' : '';
				tabsNote.hidden = ! known;
			}

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

		if ( tabsNote ) {
			tabsNote.hidden = true;
			tabsNote.textContent = '';
		}

		var selected = tabsSelect.options[ tabsSelect.selectedIndex ];
		if ( selected ) {
			tabNameField.value = selected.textContent;
		}
	}

	var sourceIdField = form.querySelector( 'input[name="source_id"]' );
	var rawWrap = document.getElementById( 'lstab-raw-wrap' );
	var rawText = document.getElementById( 'lstab-raw' );
	var rawMeta = document.getElementById( 'lstab-raw-meta' );
	var columnList = document.querySelector( '.lstab-column-list' );
	var columnCard = document.querySelector( '.lstab-columns-card' );

	/**
	 * The column rows as they stand.
	 *
	 * Looked up each time rather than captured once: the list is rebuilt the
	 * moment a preview arrives for a source that has never been saved, and a
	 * list captured at page load would keep answering with the placeholders it
	 * replaced.
	 *
	 * @return {Array} Row elements.
	 */
	function columnRows() {
		return columnList
			? Array.prototype.slice.call( columnList.querySelectorAll( 'tbody tr' ) )
			: [];
	}

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

		columnRows().forEach( function ( row ) {
			var label = row.querySelector( 'input[type="text"]' );
			var state = row.querySelector( 'input[name$="[hidden]"]' );
			var drawer = row.querySelector( 'input[name$="[detail]"]' );

			if ( ! label || label.disabled ) {
				return;
			}

			settings.push( {
				source: label.placeholder || '',
				label: label.value,
				// The add-on writes into these fields as you click, so reading
				// them here keeps the preview honest whether the add-on is
				// there or not, without the free plugin knowing anything about
				// it. Leaving 'detail' out is why the preview used to show a
				// column in the table that the published page put in a drawer.
				visible: ! ( state && '1' === state.value ),
				detail: !! ( drawer && '1' === drawer.value )
			} );
		} );

		return settings;
	}

	/**
	 * Build the column list from the headings a preview brought back.
	 *
	 * Only when the list is still the placeholder one: a saved source has its
	 * own list, carrying choices an add-on wrote into it, and rebuilding that
	 * from a preview would throw them away.
	 *
	 * @param {Array} headers Headings, in sheet order.
	 * @return {void}
	 */
	function buildColumnList( headers ) {
		if ( ! columnList || ! columnCard || ! headers.length ) {
			return;
		}

		var waiting = columnCard.classList.contains( 'is-waiting' );

		if ( ! waiting ) {
			return;
		}

		var body = columnList.querySelector( 'tbody' );

		if ( ! body ) {
			return;
		}

		// Anything already typed into a placeholder is kept: somebody who
		// renamed a column and then pressed Preview should not lose it.
		var typed = columnRows().map( function ( row ) {
			var field = row.querySelector( 'input[type="text"]' );
			return field ? field.value : '';
		} );

		body.innerHTML = '';

		headers.forEach( function ( heading, index ) {
			var row = document.createElement( 'tr' );
			var name = String( heading || '' );

			// The name from the sheet, and the two fields the form submits for
			// every column, exactly as the server renders them for a saved one.
			var sourceCell = document.createElement( 'td' );
			var code = document.createElement( 'code' );
			code.textContent = name || sprintf( i18n.columnNumber || 'Column %1$s', [ index + 1 ] );
			sourceCell.appendChild( code );
			sourceCell.appendChild( hiddenField( 'columns[' + index + '][source]', name ) );

			var labelCell = document.createElement( 'td' );
			var label = document.createElement( 'input' );
			label.type = 'text';
			label.className = 'regular-text';
			label.name = 'columns[' + index + '][label]';
			label.value = typed[ index ] || '';
			label.placeholder = name;
			labelCell.appendChild( label );

			var stateCell = document.createElement( 'td' );
			stateCell.className = 'lstab-column-state';
			stateCell.appendChild( hiddenField( 'columns[' + index + '][hidden]', '0' ) );
			stateCell.appendChild( hiddenField( 'columns[' + index + '][detail]', '0' ) );
			var state = document.createElement( 'span' );
			state.className = 'lstab-state-shown';
			state.textContent = i18n.shown || 'Shown';
			stateCell.appendChild( state );

			row.appendChild( sourceCell );
			row.appendChild( labelCell );
			row.appendChild( stateCell );
			body.appendChild( row );
		} );

		columnCard.classList.remove( 'is-waiting' );

		var note = columnCard.querySelector( '.lstab-columns-waiting' );
		if ( note ) {
			note.remove();
		}
	}

	/**
	 * One hidden field, since the list needs six of them.
	 *
	 * @param {string} name  Field name.
	 * @param {string} value Field value.
	 * @return {HTMLInputElement} The field.
	 */
	function hiddenField( name, value ) {
		var field = document.createElement( 'input' );

		field.type = 'hidden';
		field.name = name;
		field.value = value;

		return field;
	}

	/**
	 * Anything an add-on wants the preview to know about.
	 *
	 * A colour rule being typed exists only in the form until it is saved, so
	 * without this the preview could only ever show the rules as they were the
	 * last time somebody pressed Save — which is exactly the round trip a
	 * preview is for avoiding. An add-on pushes a function here; whatever it
	 * returns is merged into the request.
	 *
	 * @return {Object} Extra fields for the preview request.
	 */
	function extraPreviewData() {
		var extra = {};

		( window.lstabPreviewFields || [] ).forEach( function ( collect ) {
			try {
				var fields = collect();

				Object.keys( fields || {} ).forEach( function ( key ) {
					extra[ key ] = fields[ key ];
				} );
			} catch ( error ) {
				// An add-on that throws must not take the preview with it.
			}
		} );

		return extra;
	}

	/**
	 * Merge those fields into a request payload.
	 *
	 * @param {Object} data The payload.
	 * @return {Object} The payload, with anything an add-on added.
	 */
	function withExtras( data ) {
		var extra = extraPreviewData();

		Object.keys( extra ).forEach( function ( key ) {
			data[ key ] = extra[ key ];
		} );

		return data;
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
			data: withExtras( {
				url: url,
				gid: undefined === gid ? '' : String( gid ),
				firstRowHeader: firstRowHeader ? firstRowHeader.checked : true,
				style: selectedPreset(),
				layout: selectedLayout(),
				sticky: pinned( 'sticky_first' ),
				stickyHead: pinned( 'sticky_head' ),
				columns: columnSettings(),
				sourceId: sourceIdField ? parseInt( sourceIdField.value, 10 ) || 0 : 0
			} )
		} ).then( function ( response ) {
			setBusy( false );

			stage.innerHTML = response.html || '';
			applyAppearance();
			if ( window.lstabInit ) {
				window.lstabInit();
			}

			gidField.value = response.gid;

			// A source that has never been saved has no columns stored, so the
			// list under "Columns and rows" was three disabled placeholders and
			// a note telling you to save first. The preview that just arrived
			// knows the real headings, so the list is built from that instead
			// and the names can be set before anything is stored.
			buildColumnList( response.headers || [] );

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

			// renderTabs() owns that decision: a sheet still has the tab it
			// was saved with, and hiding the one control that could change it
			// is not an answer to a list that did not come back.
			renderTabs( response.tabs, response.gid );

			// Offer a sensible default title once we know the tab name.
			if ( titleField && ! titleField.value && tabNameField.value ) {
				titleField.value = tabNameField.value;
			}
		} ).catch( function ( error ) {
			setBusy( false );
			stage.innerHTML = '';
			setStatus( ( error && error.message ) || i18n.failed, 'error' );

			// Same reasoning: keep the tab the source already has. Only a
			// source that never had one has nothing to show here.
			tabsWrap.hidden = ! ( tabNameField && tabNameField.value );
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

	/**
	 * Squeeze the preview to one of the offered widths.
	 *
	 * Constrains the stage so the author can see the table and the card layout
	 * without resizing the browser; the container query does the rest.
	 *
	 * @param {string} width Width in pixels, or '' for the full column.
	 * @return {void}
	 */
	function setPreviewWidth( width ) {
		stage.style.maxWidth = width ? width + 'px' : '';

		Array.prototype.forEach.call( widthButtons, function ( button ) {
			var active = button.getAttribute( 'data-lstab-width' ) === width;
			button.classList.toggle( 'is-active', active );
			button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		} );
	}

	Array.prototype.forEach.call( widthButtons, function ( widthButton ) {
		widthButton.addEventListener( 'click', function () {
			setPreviewWidth( widthButton.getAttribute( 'data-lstab-width' ) );
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
	Array.prototype.forEach.call( layoutInputs, function ( input ) {
		input.addEventListener( 'change', function () {
			var table = stage.querySelector( '.lstab' );

			/*
			 * The preview is normally as wide as its column, and at that width
			 * two of the three choices draw exactly the same table — so picking
			 * one appeared to do nothing at all, which is a fair reason to
			 * conclude the setting is broken. Each choice is therefore shown at
			 * the width where it is itself: the two that answer "what happens
			 * on a phone" go to phone width, and "always cards" goes back to
			 * the full width, which is the whole of what it claims.
			 */
			setPreviewWidth( 'cards' === input.value ? '' : '390' );

			if ( ! table ) {
				return;
			}

			[ 'table', 'auto', 'cards' ].forEach( function ( value ) {
				table.classList.remove( 'lstab-layout-' + value );
			} );

			if ( 'auto' !== input.value ) {
				table.classList.add( 'lstab-layout-' + input.value );
			}

			// The slider has to re-measure once the layout changes.
			table.dispatchEvent( new CustomEvent( 'lstab:resize' ) );
		} );
	} );

	Object.keys( pins ).forEach( function ( name ) {
		var box = form.querySelector( 'input[name="' + name + '"]' );

		if ( ! box ) {
			return;
		}

		box.addEventListener( 'change', function () {
			var table = stage.querySelector( '.lstab' );

			if ( ! table ) {
				return;
			}

			table.classList.toggle( pins[ name ], box.checked );

			// Pinning changes what has to be measured: a pinned first column
			// takes width from what is left to scroll.
			table.dispatchEvent( new CustomEvent( 'lstab:resize' ) );
		} );
	} );

	/*
	 * The row count is only a question once there are pages to put rows on.
	 * Shown while paging is off it invited a 0 — which is how the whole feature
	 * used to get switched off by somebody who only meant to clear the box.
	 */
	if ( pagingToggle && pagingRows ) {
		pagingToggle.addEventListener( 'change', function () {
			pagingRows.hidden = ! pagingToggle.checked;
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
			data: withExtras( {
				sourceId: id,
				style: selectedPreset(),
				layout: selectedLayout(),
				sticky: pinned( 'sticky_first' ),
				stickyHead: pinned( 'sticky_head' ),
				columns: columnSettings()
			} )
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

	/**
	 * Write the names as they stand straight onto the previewed table.
	 *
	 * Instant, and it needs nothing from the server — a rename changes the
	 * words in the heading row and nothing else. It is also the only thing that
	 * can work before the first save, when there is no stored copy to redraw
	 * from and asking Google again for every keystroke would be absurd.
	 *
	 * @return {void}
	 */
	function renameHeadings() {
		var table = stage.querySelector( '.lstab-table' );

		if ( ! table ) {
			return;
		}

		var heads = table.querySelectorAll( 'thead th' );
		var shown = 0;

		columnRows().forEach( function ( row ) {
			var field = row.querySelector( 'input[type="text"]' );
			var state = row.querySelector( 'input[name$="[hidden]"]' );
			var drawer = row.querySelector( 'input[name$="[detail]"]' );

			if ( ! field || field.disabled ) {
				return;
			}

			/*
			 * Neither a hidden column nor one that lives under the row has a
			 * heading on the table to rename, and counting them would put every
			 * name after them on the wrong column.
			 */
			if ( ( state && '1' === state.value ) || ( drawer && '1' === drawer.value ) ) {
				return;
			}

			var head = heads[ shown ];
			shown++;

			if ( ! head ) {
				return;
			}

			var name = field.value || field.placeholder || '';
			var label = head.querySelector( '.lstab-sort-label' );

			if ( label ) {
				label.textContent = name;
			} else {
				head.textContent = name;
			}
		} );
	}

	/*
	 * A rename shows as it is typed rather than only after a save. Delegated to
	 * the list rather than bound row by row, because the list is rebuilt from
	 * the preview on a source that has never been saved.
	 */
	/*
	 * Published so an add-on can ask for the preview to be drawn again when one
	 * of its own controls changes.
	 */
	window.lstabRedrawPreview = redrawFromStored;

	if ( columnList ) {
		var typing = null;

		columnList.addEventListener( 'input', function ( event ) {
			if ( ! event.target.matches( 'input[type="text"]' ) ) {
				return;
			}

			renameHeadings();

			// The full redraw follows, for the parts of a table a heading is
			// not: the labels a card layout repeats beside every value.
			window.clearTimeout( typing );
			typing = window.setTimeout( redrawFromStored, 600 );
		} );

		columnList.addEventListener( 'change', function () {
			if ( stage.querySelector( '.lstab-table' ) ) {
				redrawFromStored();
			}
		} );
	}

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

