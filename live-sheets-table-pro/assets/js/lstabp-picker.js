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

	/*
	 * Paging. A forty-column sheet does not fit on any screen, and two hundred
	 * rows of clickable line numbers is a wall rather than a control. Both are
	 * paged here rather than in PHP so that every row and every column stays in
	 * the form: what is hidden must be submitted whether or not you happened to
	 * be looking at that page when you saved.
	 */
	( function paging() {
		var ROWS = 25;
		var COLS = 12;

		var bar = document.getElementById( 'lstabp-picker-paging' );

		if ( ! bar ) {
			return;
		}

		var bodyRows = picker.querySelectorAll( 'tbody tr' );
		var headings = picker.querySelectorAll( 'thead th.lstabp-picker-col' );
		var at = { rows: 0, cols: 0 };

		var pages = function ( total, per ) {
			return Math.max( 1, Math.ceil( total / per ) );
		};

		var rowPages = pages( bodyRows.length, ROWS );
		var colPages = pages( headings.length, COLS );

		if ( rowPages < 2 && colPages < 2 ) {
			return;
		}

		var label = function ( which, page, total, count ) {
			var text = ( 'rows' === which ? i18n.rowsPage : i18n.colsPage ) || '%1$s / %2$s';

			return text.replace( '%1$s', page + 1 ).replace( '%2$s', total ).replace( '%3$s', count );
		};

		var paint = function () {
			Array.prototype.forEach.call( bodyRows, function ( row, index ) {
				row.hidden = rowPages > 1 && Math.floor( index / ROWS ) !== at.rows;
			} );

			Array.prototype.forEach.call( headings, function ( head, index ) {
				var off = colPages > 1 && Math.floor( index / COLS ) !== at.cols;

				head.hidden = off;

				// Every cell in that column goes with its heading, or the rows
				// below would silently shift one place to the left.
				Array.prototype.forEach.call(
					picker.querySelectorAll( 'td[data-lstabp-column="' + index + '"]' ),
					function ( cell ) {
						cell.hidden = off;
					}
				);
			} );

			var groups = bar.querySelectorAll( '[data-lstabp-page-for]' );

			Array.prototype.forEach.call( groups, function ( group ) {
				var which = group.getAttribute( 'data-lstabp-page-for' );
				var total = 'rows' === which ? rowPages : colPages;
				var count = 'rows' === which ? bodyRows.length : headings.length;

				group.hidden = total < 2;
				group.querySelector( '[data-lstabp-page-label]' ).textContent =
					label( which, at[ which ], total, count );
			} );

			bar.hidden = false;
		};

		Array.prototype.forEach.call( bar.querySelectorAll( '[data-lstabp-step]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				var parts = button.getAttribute( 'data-lstabp-step' ).split( ':' );
				var which = parts[ 0 ];
				var total = 'rows' === which ? rowPages : colPages;

				at[ which ] = ( at[ which ] + Number( parts[ 1 ] ) + total ) % total;
				paint();
			} );
		} );

		paint();
	}() );
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
	 * The field carrying whether a column belongs under the row.
	 *
	 * @param {number} index Column position.
	 * @return {HTMLInputElement|null} Field.
	 */
	function detailField( index ) {
		return document.querySelector(
			'input[type="hidden"][name="columns[' + index + '][detail]"]'
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

		var detail = detailField( index );
		var inDrawer = detail && '1' === detail.value;

		if ( hidden ) {
			label.className = 'lstab-state-hidden';
			label.textContent = i18n.hidden || 'Hidden';
		} else if ( inDrawer ) {
			label.className = 'lstab-state-detail';
			label.textContent = i18n.inDetails || 'In the details';
		} else {
			label.className = 'lstab-state-shown';
			label.textContent = i18n.shown || 'Shown';
		}
	}

	/**
	 * Show a column as moved under the row, everywhere it appears.
	 *
	 * @param {number}  index  Column position.
	 * @param {boolean} detail Whether it belongs in the drawer.
	 * @return {void}
	 */
	function paintDetail( index, detail ) {
		Array.prototype.forEach.call(
			picker.querySelectorAll( '[data-lstabp-column="' + index + '"]' ),
			function ( cell ) {
				cell.classList.toggle( 'is-detail', detail );

				var header = cell.closest( 'th' );
				if ( header ) {
					header.classList.toggle( 'is-detail', detail );
				}
			}
		);

		var button = picker.querySelector( 'button[data-lstabp-detail="' + index + '"]' );
		if ( button ) {
			button.setAttribute( 'aria-pressed', detail ? 'true' : 'false' );
			button.closest( 'th' ).classList.toggle( 'is-detail', detail );
		}
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
		var chevron = event.target.closest( '.lstabp-picker-detail' );

		if ( chevron && ! chevron.disabled ) {
			var detailIndex = chevron.getAttribute( 'data-lstabp-detail' );
			var field = detailField( detailIndex );
			var wanted = 'true' !== chevron.getAttribute( 'aria-pressed' );

			if ( field ) {
				field.value = wanted ? '1' : '0';
			}

			paintDetail( detailIndex, wanted );
			paintState( detailIndex, false );
			return;
		}

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

			/*
			 * Taking a column out of the table takes it out of the drawer too.
			 * "Hidden but also in the details" is not a state anybody means,
			 * and leaving the arrow lit under a struck-through heading reads as
			 * a bug.
			 */
			if ( hidden ) {
				var detailToo = detailField( column );

				if ( detailToo ) {
					detailToo.value = '0';
				}

				paintDetail( column, false );
			}

			var chevronButton = picker.querySelector( 'button[data-lstabp-detail="' + column + '"]' );
			if ( chevronButton ) {
				chevronButton.disabled = hidden;
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
