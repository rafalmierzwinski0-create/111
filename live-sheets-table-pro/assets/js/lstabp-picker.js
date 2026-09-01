/**
 * Live Sheets Table Pro — click what you want gone.
 *
 * The picker is a plain HTML table of the stored sheet. Clicking a heading or a
 * row marks it hidden, and the marking is what gets submitted: columns through
 * the free plugin's own checkboxes, rows through hidden fields written here.
 * The checkboxes keep working on their own, so a page that never runs this
 * script is less convenient rather than broken.
 */
( function () {
	'use strict';

	var picker = document.getElementById( 'lstabp-picker' );
	if ( ! picker ) {
		return;
	}

	var settings = window.lstabpPicker || {};
	var i18n = settings.i18n || {};
	var fields = document.getElementById( 'lstabp-hidden-rows-fields' );
	var chips = document.getElementById( 'lstabp-hidden-rows-chips' );
	var empty = document.querySelector( '.lstabp-picker-empty' );

	/**
	 * Keys that were already hidden when the page loaded but match no row now.
	 *
	 * The sheet has moved on: the row was renamed or taken out. The choice is
	 * kept rather than dropped — the row may be back tomorrow — but it is shown
	 * as what it is, so nobody wonders why nothing looks hidden.
	 *
	 * @return {Array} Keys.
	 */
	function orphanedKeys() {
		var keys = [];

		Array.prototype.forEach.call(
			fields.querySelectorAll( 'input[data-lstabp-present="0"]' ),
			function ( field ) {
				if ( keys.indexOf( field.value ) === -1 ) {
					keys.push( field.value );
				}
			}
		);

		return keys;
	}

	var orphans = orphanedKeys();

	/**
	 * The free plugin's checkbox governing one column.
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
		Array.prototype.forEach.call(
			picker.querySelectorAll( '[data-lstabp-column="' + index + '"]' ),
			function ( cell ) {
				cell.classList.toggle( 'is-hidden', hidden );

				var header = cell.closest( 'th' );
				if ( header ) {
					header.classList.toggle( 'is-hidden', hidden );
				}
			}
		);

		var toggle = picker.querySelector( 'button[data-lstabp-column="' + index + '"]' );
		if ( toggle ) {
			toggle.setAttribute( 'aria-pressed', hidden ? 'true' : 'false' );
		}
	}

	/**
	 * Every hidden row key: the ones clicked, plus the ones the sheet has lost.
	 *
	 * @return {Array} Keys.
	 */
	function hiddenKeys() {
		var keys = orphans.slice();

		Array.prototype.forEach.call(
			picker.querySelectorAll( 'tr.is-hidden[data-lstabp-key]' ),
			function ( row ) {
				var key = row.getAttribute( 'data-lstabp-key' );
				if ( key && keys.indexOf( key ) === -1 ) {
					keys.push( key );
				}
			}
		);

		return keys;
	}

	/**
	 * How many rows of the sheet answer to one key.
	 *
	 * @param {string} key Row key.
	 * @return {number} Count.
	 */
	function sharedBy( key ) {
		var row = picker.querySelector( 'tr[data-lstabp-key="' + cssEscape( key ) + '"]' );

		return row ? parseInt( row.getAttribute( 'data-lstabp-shared' ), 10 ) || 1 : 0;
	}

	/**
	 * Make a value safe to put inside an attribute selector.
	 *
	 * @param {string} value Value.
	 * @return {string} Escaped value.
	 */
	function cssEscape( value ) {
		if ( window.CSS && CSS.escape ) {
			return CSS.escape( value );
		}

		return String( value ).replace( /["\\]/g, '\\$&' );
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

			var shared = sharedBy( key );
			var chip = document.createElement( 'li' );
			chip.className = 'lstabp-chip' + ( 0 === shared ? ' is-orphan' : '' );

			var label = document.createElement( 'span' );
			label.textContent = key;
			chip.appendChild( label );

			if ( shared > 1 ) {
				var many = document.createElement( 'em' );
				many.className = 'lstabp-chip-note';
				many.textContent = ( i18n.alsoMatches || '%d rows' ).replace( '%d', shared );
				chip.appendChild( many );
			} else if ( 0 === shared ) {
				var gone = document.createElement( 'em' );
				gone.className = 'lstabp-chip-note';
				gone.textContent = i18n.notInSheet || 'not in the sheet now';
				chip.appendChild( gone );
			}

			var remove = document.createElement( 'button' );
			remove.type = 'button';
			remove.className = 'lstabp-chip-remove';
			remove.setAttribute( 'aria-label', ( i18n.showRowAgain || 'Show this row again' ) + ': ' + key );
			remove.textContent = '×';
			remove.addEventListener( 'click', function () {
				orphans = orphans.filter( function ( other ) {
					return other !== key;
				} );
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
		Array.prototype.forEach.call(
			picker.querySelectorAll( 'tr[data-lstabp-key="' + cssEscape( key ) + '"]' ),
			function ( row ) {
				row.classList.toggle( 'is-hidden', hidden );

				var toggle = row.querySelector( 'button[data-lstabp-row]' );
				if ( toggle ) {
					toggle.setAttribute( 'aria-pressed', hidden ? 'true' : 'false' );
				}
			}
		);

		syncRows();
	}

	picker.addEventListener( 'click', function ( event ) {
		var toggle = event.target.closest( '.lstabp-picker-toggle' );
		if ( ! toggle || toggle.disabled ) {
			return;
		}

		var column = toggle.getAttribute( 'data-lstabp-column' );

		if ( null !== column ) {
			var box = columnBox( column );
			var hidden = 'true' !== toggle.getAttribute( 'aria-pressed' );

			if ( box ) {
				box.checked = ! hidden;
			}

			paintColumn( column, hidden );
			return;
		}

		var key = toggle.getAttribute( 'data-lstabp-row' );
		if ( key ) {
			setRow( key, 'true' !== toggle.getAttribute( 'aria-pressed' ) );
		}
	} );

	// The checkbox list can still be used directly, and the picker follows it.
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
