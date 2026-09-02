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
		var found = [];

		Array.prototype.forEach.call(
			fields.querySelectorAll( 'input[data-lstabp-present="0"]' ),
			function ( field ) {
				found.push( {
					name: field.getAttribute( 'data-lstabp-name' ) || '',
					sig: field.value || '',
					label: field.getAttribute( 'data-lstabp-label' ) || field.getAttribute( 'data-lstabp-name' ) || ''
				} );
			}
		);

		return found;
	}

	var orphans = orphanedKeys();

	/**
	 * The field in which the free plugin carries one column's state.
	 *
	 * The free plugin shows that state and submits it back unchanged; changing
	 * it is what this add-on is for, so the picker writes into the same field
	 * rather than inventing a second place for the same fact.
	 *
	 * @param {number} index Column position.
	 * @return {HTMLInputElement|null} Field.
	 */
	function columnField( index ) {
		return document.querySelector(
			'input[type="hidden"][name="columns[' + index + '][hidden]"]'
		);
	}

	/**
	 * Keep the free plugin's own wording in step with the picker.
	 *
	 * @param {number}  index  Column position.
	 * @param {boolean} hidden Whether it is dropped.
	 * @return {void}
	 */
	function paintState( index, hidden ) {
		var field = columnField( index );
		if ( ! field ) {
			return;
		}

		var cell = field.closest( '.lstab-column-state' );
		if ( ! cell ) {
			return;
		}

		var label = cell.querySelector( 'span' );
		if ( ! label ) {
			return;
		}

		label.className = hidden ? 'lstab-state-hidden' : 'lstab-state-shown';
		label.textContent = hidden ? ( i18n.hidden || 'Hidden' ) : ( i18n.shown || 'Shown' );
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
		var entries = orphans.slice();
		var seen = {};

		entries.forEach( function ( entry ) {
			seen[ entry.sig ] = true;
		} );

		Array.prototype.forEach.call(
			picker.querySelectorAll( 'tr.is-hidden[data-lstabp-sig]' ),
			function ( row ) {
				var sig = row.getAttribute( 'data-lstabp-sig' );

				if ( sig && ! seen[ sig ] ) {
					seen[ sig ] = true;
					entries.push( {
						name: row.getAttribute( 'data-lstabp-key' ) || '',
						sig: sig,
						// What the row says, for the list below the table: a
						// name alone can be a date, a code, or the number 3.
						label: row.getAttribute( 'data-lstabp-label' ) || row.getAttribute( 'data-lstabp-key' ) || ''
					} );
				}
			}
		);

		return entries;
	}

	/**
	 * How many rows of the sheet answer to one key.
	 *
	 * @param {string} key Row key.
	 * @return {number} Count.
	 */
	function sharedBy( sig ) {
		var row = picker.querySelector( 'tr[data-lstabp-sig="' + cssEscape( sig ) + '"]' );

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

		keys.forEach( function ( entry, position ) {
			// Only what the server needs to find the row again; the label is
			// for reading, and is rebuilt from the sheet each time.
			[ 'name', 'sig' ].forEach( function ( part ) {
				var field = document.createElement( 'input' );
				field.type = 'hidden';
				field.name = 'hidden_rows[' + position + '][' + part + ']';
				field.value = entry[ part ];
				fields.appendChild( field );
			} );

			var key = entry.label || entry.name;
			var shared = sharedBy( entry.sig );
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
					return other.sig !== entry.sig;
				} );
				setRow( entry.sig, false );
			} );
			chip.appendChild( remove );

			chips.appendChild( chip );
		} );

		if ( empty ) {
			empty.hidden = keys.length > 0;
		}
	}

	/**
	 * Mark the row with this signature as hidden or shown.
	 *
	 * Rows that differ anywhere have different signatures, so ten products all
	 * called "Kask" are ten separate choices. Rows identical in every cell share
	 * one, which is right: they are indistinguishable to a reader too.
	 *
	 * @param {string}  sig    Row signature.
	 * @param {boolean} hidden Whether to hide it.
	 * @return {void}
	 */
	function setRow( sig, hidden ) {
		Array.prototype.forEach.call(
			picker.querySelectorAll( 'tr[data-lstabp-sig="' + cssEscape( sig ) + '"]' ),
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
			var field = columnField( column );
			var hidden = 'true' !== toggle.getAttribute( 'aria-pressed' );

			if ( field ) {
				field.value = hidden ? '1' : '0';
			}

			paintColumn( column, hidden );
			paintState( column, hidden );
			return;
		}

		var key = toggle.getAttribute( 'data-lstabp-row' );
		if ( key ) {
			setRow( key, 'true' !== toggle.getAttribute( 'aria-pressed' ) );
		}
	} );

	syncRows();
}() );
