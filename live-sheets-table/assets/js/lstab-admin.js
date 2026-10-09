( function () {
	'use strict';

	var settings = window.lstabAdmin || {};
	var i18n = settings.i18n || {};

	var form = document.getElementById( 'lstab-source-form' );
	if ( ! form ) {
		try {
			window.sessionStorage.removeItem( 'lstabPane' );
		} catch ( error ) {
		}

		return;
	}

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

			Array.prototype.forEach.call(
				document.querySelectorAll( '[data-lstab-panes]' ),
				function ( block ) {
					var list = block.getAttribute( 'data-lstab-panes' ).split( /\s+/ );
					block.hidden = list.indexOf( wanted ) === -1;
				}
			);

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
			}
		};

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

			if ( ! saved.at || Date.now() - saved.at > 120000 ) {
				return '';
			}

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

	var refreshLiveCss = function () {};
	var widthButtons = document.querySelectorAll( '.lstab-width-button' );
	var tabsWrap = document.getElementById( 'lstab-tabs-wrap' );
	var tabsSelect = document.getElementById( 'lstab-tabs' );
	var gidField = document.getElementById( 'lstab-gid' );
	var tabNameField = document.getElementById( 'lstab-tab-name' );
	var titleField = document.getElementById( 'lstab-title' );
	var firstRowHeader = document.getElementById( 'lstab-first-row-header' );
	var presetInputs = form.querySelectorAll( 'input[name="style_preset"]' );
	var layoutInputs = form.querySelectorAll( 'input[data-lstab-layout]' );
	var pins = {
		sticky_first: 'lstab-sticky-first',
		sticky_head: 'lstab-sticky-head'
	};
	var pagingToggle = document.getElementById( 'lstab-paging' );
	var pagingRows = document.getElementById( 'lstab-paging-rows' );

	var inFlight = false;

	function pinned( name ) {
		var box = form.querySelector( 'input[name="' + name + '"]' );

		return box ? box.checked : true;
	}

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

	function selectedPreset() {
		for ( var i = 0; i < presetInputs.length; i++ ) {
			if ( presetInputs[ i ].checked ) {
				return presetInputs[ i ].value;
			}
		}
		return '';
	}

	function applyPreset( preset ) {
		if ( ! preset ) {
			return;
		}

		Array.prototype.forEach.call(
			document.querySelectorAll( '.lstab-appearance, .lstab-preview-pane' ),
			function ( panel ) {
				panel.dataset.lstabStyle = preset;
			}
		);

		var table = stage.querySelector( '.lstab' );

		if ( ! table ) {
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

	function renderTabs( tabs, selectedGid ) {
		var known = tabNameField && tabNameField.value;

		if ( ! tabs || ! tabs.length ) {
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

	function columnRows() {
		return columnList
			? Array.prototype.slice.call( columnList.querySelectorAll( 'tbody tr' ) )
			: [];
	}

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
				visible: ! ( state && '1' === state.value ),
				detail: !! ( drawer && '1' === drawer.value )
			} );
		} );

		return settings;
	}

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

		var typed = columnRows().map( function ( row ) {
			var field = row.querySelector( 'input[type="text"]' );
			return field ? field.value : '';
		} );

		body.innerHTML = '';

		headers.forEach( function ( heading, index ) {
			var row = document.createElement( 'tr' );
			var name = String( heading || '' );

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

	function hiddenField( name, value ) {
		var field = document.createElement( 'input' );

		field.type = 'hidden';
		field.name = name;
		field.value = value;

		return field;
	}

	function extraPreviewData() {
		var extra = {};

		( window.lstabPreviewFields || [] ).forEach( function ( collect ) {
			try {
				var fields = collect();

				Object.keys( fields || {} ).forEach( function ( key ) {
					extra[ key ] = fields[ key ];
				} );
			} catch ( error ) {
			}
		} );

		return extra;
	}

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
			followPresetWhereUnset();
			refreshLiveCss();
			if ( window.lstabInit ) {
				window.lstabInit();
			}

			gidField.value = response.gid;

			buildColumnList( response.headers || [] );

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

			if ( titleField && ! titleField.value && tabNameField.value ) {
				titleField.value = tabNameField.value;
			}
		} ).catch( function ( error ) {
			setBusy( false );
			stage.innerHTML = '';
			setStatus( ( error && error.message ) || i18n.failed, 'error' );

			tabsWrap.hidden = ! ( tabNameField && tabNameField.value );
		} );
	}

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

		function refresh() {
			var css = field.value;

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
					if ( pending === css ) {
						sheet.textContent = response.css || '';
					}
				},
				function () {
				}
			);
		}

		field.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( refresh, 400 );
		} );

		field.addEventListener( 'change', refresh );

		refreshLiveCss = function () {
			pending = null;
			refresh();
		};
	}() );

	var swatches = document.querySelectorAll( '.lstab-swatch' );
	var metricInputs = document.querySelectorAll( '.lstab-metric-input' );
	var sizeInputs = document.querySelectorAll( '.lstab-size-input' );

	function sizeOf( input ) {
		var raw = parseInt( input.value, 10 );

		if ( isNaN( raw ) ) {
			return '';
		}

		var min = parseInt( input.getAttribute( 'min' ), 10 ) || 0;
		var max = parseInt( input.getAttribute( 'max' ), 10 ) || raw;

		return String( Math.max( min, Math.min( max, raw ) ) );
	}
	var resetAppearance = document.getElementById( 'lstab-reset-appearance' );

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

		Array.prototype.forEach.call( sizeInputs, function ( input ) {
			var property = input.getAttribute( 'data-lstab-var' );
			var size = sizeOf( input );

			if ( size ) {
				table.style.setProperty( property, size + 'px' );
			} else {
				table.style.removeProperty( property );
			}
		} );
	}

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

	function describe( swatch ) {
		var value = swatch.querySelector( '.lstab-color-value' ).value;
		var hex = swatch.querySelector( '.lstab-swatch-hex' );

		swatch.classList.toggle( 'is-set', !! value );

		if ( hex ) {
			hex.textContent = value ? value.toUpperCase() : hex.getAttribute( 'data-lstab-style-word' );
		}
	}

	function followPreset( swatch ) {
		var picker = swatch.querySelector( '.lstab-color-input' );
		var table = stage.querySelector( '.lstab' );
		var resolved = table
			? window.getComputedStyle( table ).getPropertyValue( swatch.getAttribute( 'data-lstab-var' ) )
			: '';

		picker.value = toHex( resolved ) || '#ffffff';
		describe( swatch );
	}

	function followPresetWhereUnset() {
		Array.prototype.forEach.call( swatches, function ( swatch ) {
			if ( ! swatch.querySelector( '.lstab-color-value' ).value ) {
				followPreset( swatch );
			}
		} );
	}

	function clearSwatch( swatch ) {
		swatch.querySelector( '.lstab-color-value' ).value = '';
		swatch.querySelector( '.lstab-color-input' ).setAttribute( 'data-lstab-unset', '1' );
		swatch.querySelector( '.lstab-color-clear' ).disabled = true;
		describe( swatch );
	}

	Array.prototype.forEach.call( swatches, function ( swatch ) {
		var picker = swatch.querySelector( '.lstab-color-input' );
		var hidden = swatch.querySelector( '.lstab-color-value' );
		var clear = swatch.querySelector( '.lstab-color-clear' );

		picker.addEventListener( 'input', function () {
			hidden.value = picker.value;
			picker.removeAttribute( 'data-lstab-unset' );
			clear.disabled = false;
			describe( swatch );
			applyAppearance();
		} );

		clear.addEventListener( 'click', function () {
			clearSwatch( swatch );
			applyAppearance();
			followPreset( swatch );
		} );
	} );

	followPresetWhereUnset();

	Array.prototype.forEach.call( metricInputs, function ( input ) {
		input.addEventListener( 'change', applyAppearance );
	} );

	Array.prototype.forEach.call( sizeInputs, function ( input ) {
		input.addEventListener( 'input', applyAppearance );
		input.addEventListener( 'change', function () {
			input.value = sizeOf( input );
			applyAppearance();
		} );
	} );

	if ( resetAppearance ) {
		resetAppearance.addEventListener( 'click', function () {
			Array.prototype.forEach.call( swatches, clearSwatch );
			Array.prototype.forEach.call( metricInputs, function ( input ) {
				input.value = 'normal';
			} );
			Array.prototype.forEach.call( sizeInputs, function ( input ) {
				input.value = '';
			} );

			var table = stage.querySelector( '.lstab' );
			if ( table ) {
				table.removeAttribute( 'style' );
			}

			Array.prototype.forEach.call( swatches, followPreset );
		} );
	}

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

			followPresetWhereUnset();
		} );
	} );

	Array.prototype.forEach.call( layoutInputs, function ( input ) {
		input.addEventListener( 'change', function () {
			var table = stage.querySelector( '.lstab' );

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

			table.dispatchEvent( new CustomEvent( 'lstab:resize' ) );
		} );
	} );

	if ( pagingToggle && pagingRows ) {
		pagingToggle.addEventListener( 'change', function () {
			pagingRows.hidden = ! pagingToggle.checked;
		} );
	}

	var pagingTouched = document.getElementById( 'lstab-paging-touched' );

	if ( pagingTouched ) {
		[ pagingToggle, document.getElementById( 'lstab-per-page' ) ].forEach( function ( control ) {
			if ( ! control ) {
				return;
			}

			[ 'change', 'input' ].forEach( function ( event ) {
				control.addEventListener( event, function () {
					pagingTouched.value = '1';
				} );
			} );
		} );
	}

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
				followPresetWhereUnset();
				refreshLiveCss();
				if ( window.lstabInit ) {
					window.lstabInit();
				}
			},
			function () {
			}
		);
	}

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

	window.lstabRedrawPreview = redrawFromStored;

	if ( columnList ) {
		var typing = null;

		columnList.addEventListener( 'input', function ( event ) {
			if ( ! event.target.matches( 'input[type="text"]' ) ) {
				return;
			}

			renameHeadings();

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

		if ( urlInput.value ) {
			loadPreview( gidField ? gidField.value : '' );
		}
	}
}() );

