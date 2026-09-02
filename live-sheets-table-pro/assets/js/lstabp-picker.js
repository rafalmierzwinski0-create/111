/**
 * Live Sheets Table Pro — click what you want gone.
 *
 * The picker is a plain HTML table of the stored sheet. Clicking a heading or a
 * line number marks it, and the marking is what gets submitted: columns through
 * the free plugin's own field, rows through hidden fields written here. The
 * fields keep working with no JavaScript at all, so a page where this never
 * runs is less convenient rather than broken.
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
	 * Choices that were stored but no longer match what is on their line.
	 *
	 * The sheet has moved: a line was inserted above, or the row was replaced.
	 * The choice is kept rather than dropped — the sheet may be put back, and
	 * silently forgetting what someone decided is worse than carrying a choice
	 * that does nothing today — but it is shown for what it is.
	 *
	 * @return {Array} Entries.
	 */
	function strandedEntries() {
		var found = [];

		Array.prototype.forEach.call(
			fields.querySelectorAll( 'input[data-lstabp-present="0"]' ),
			function ( field ) {
				found.push( {
					index: field.value || '0',
					name: field.getAttribute( 'data-lstabp-name' ) || '',
					sig: field.getAttribute( 'data-lstabp-sig' ) || '',
					label: field.getAttribute( 'data-lstabp-label' ) || '',
					line: field.getAttribute( 'data-lstabp-line' ) || '',
					stranded: true
				} );
			}
		);

		return found;
	}

	var stranded = strandedEntries();

	/**
	 * The field in which the free plugin carries one column's state.
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
	 * @param {boolean} hidden Whether it is taken out.
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
	 * Show a column as kept or taken out, everywhere it appears.
	 *
	 * @param {number}  index  Column position.
	 * @param {boolean} hidden Whether it is taken out.
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
	 * Every choice about a row: the ones marked here, plus the stranded ones.
	 *
	 * @return {Array} Entries.
	 */
	function chosenRows() {
		var entries = stranded.slice();

		Array.prototype.forEach.call(
			picker.querySelectorAll( 'tr.is-hidden[data-lstabp-index]' ),
			function ( row ) {
				entries.push( {
					index: row.getAttribute( 'data-lstabp-index' ) || '0',
					name: row.getAttribute( 'data-lstabp-name' ) || '',
					sig: row.getAttribute( 'data-lstabp-sig' ) || '',
					label: row.getAttribute( 'data-lstabp-label' ) || '',
					line: row.getAttribute( 'data-lstabp-line' ) || '',
					stranded: false
				} );
			}
		);

		return entries;
	}

	/**
	 * Rewrite the submitted fields and the list of what has been taken out.
	 *
	 * @return {void}
	 */
	function syncRows() {
		var entries = chosenRows();

		fields.innerHTML = '';
		chips.innerHTML = '';

		entries.forEach( function ( entry, position ) {
			[ 'index', 'name', 'sig', 'label' ].forEach( function ( part ) {
				var field = document.createElement( 'input' );
				field.type = 'hidden';
				field.name = 'hidden_rows[' + position + '][' + part + ']';
				field.value = entry[ part ];
				fields.appendChild( field );
			} );

			var chip = document.createElement( 'li' );
			chip.className = 'lstabp-chip' + ( entry.stranded ? ' is-orphan' : '' );

			var line = document.createElement( 'strong' );
			line.className = 'lstabp-chip-line';
			line.textContent = entry.line;
			chip.appendChild( line );

			var label = document.createElement( 'span' );
			label.textContent = entry.label || entry.name;
			chip.appendChild( label );

			if ( entry.stranded ) {
				var note = document.createElement( 'em' );
				note.className = 'lstabp-chip-note';
				note.textContent = i18n.notThereNow || 'not on that line now';
				chip.appendChild( note );
			}

			var remove = document.createElement( 'button' );
			remove.type = 'button';
			remove.className = 'lstabp-chip-remove';
			remove.setAttribute( 'aria-label', ( i18n.showRowAgain || 'Show this row again' ) + ': ' + ( entry.label || entry.name ) );
			remove.textContent = '×';
			remove.addEventListener( 'click', function () {
				stranded = stranded.filter( function ( other ) {
					return other.index !== entry.index;
				} );
				setRow( entry.index, false );
			} );
			chip.appendChild( remove );

			chips.appendChild( chip );
		} );

		if ( empty ) {
			empty.hidden = entries.length > 0;
		}
	}

	/**
	 * Mark the row on one line as taken out or put back.
	 *
	 * @param {string}  index  Position among the stored rows.
	 * @param {boolean} hidden Whether to take it out.
	 * @return {void}
	 */
	function setRow( index, hidden ) {
		var row = picker.querySelector( 'tr[data-lstabp-index="' + index + '"]' );

		if ( row ) {
			row.classList.toggle( 'is-hidden', hidden );

			var toggle = row.querySelector( 'button[data-lstabp-row]' );
			if ( toggle ) {
				toggle.setAttribute( 'aria-pressed', hidden ? 'true' : 'false' );
			}
		}

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

		var index = toggle.getAttribute( 'data-lstabp-row' );
		if ( null !== index ) {
			setRow( index, 'true' !== toggle.getAttribute( 'aria-pressed' ) );
		}
	} );

	syncRows();
}() );
