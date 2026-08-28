/**
 * Live Sheets Table — front-end enhancement.
 *
 * The table is already complete in the HTML the server sent. This only adds
 * search and sorting on top; if it never runs, the table still works.
 */
( function () {
	'use strict';

	var COLLATOR = typeof Intl !== 'undefined' && Intl.Collator
		? new Intl.Collator( undefined, { numeric: true, sensitivity: 'base' } )
		: null;

	/**
	 * Try to read a cell as a number, tolerating thousands separators,
	 * currency symbols, percentages and comma decimals.
	 *
	 * @param {string} value Raw cell text.
	 * @return {number|null} Parsed number or null.
	 */
	function toNumber( value ) {
		var cleaned = value
			.replace( /\s| /g, '' )
			.replace( /[^0-9,.\-+eE]/g, '' );

		if ( ! cleaned || ! /[0-9]/.test( cleaned ) ) {
			return null;
		}

		// "1.234,56" (European) vs "1,234.56" (US): the last separator wins.
		var lastComma = cleaned.lastIndexOf( ',' );
		var lastDot = cleaned.lastIndexOf( '.' );

		if ( lastComma > -1 && lastDot > -1 ) {
			cleaned = lastComma > lastDot
				? cleaned.replace( /\./g, '' ).replace( ',', '.' )
				: cleaned.replace( /,/g, '' );
		} else if ( lastComma > -1 ) {
			cleaned = cleaned.split( ',' ).length > 2
				? cleaned.replace( /,/g, '' )
				: cleaned.replace( ',', '.' );
		}

		var parsed = parseFloat( cleaned );

		return isNaN( parsed ) ? null : parsed;
	}

	/**
	 * Cell text of a row, cached on the element.
	 *
	 * @param {HTMLElement} row   Table row.
	 * @param {number}      index Column index.
	 * @return {string} Cell text.
	 */
	function cellText( row, index ) {
		var cell = row.children[ index ];
		if ( ! cell ) {
			return '';
		}
		var value = cell.querySelector( '.lstab-cell-value' );
		return ( value ? value.textContent : cell.textContent ).trim();
	}

	/**
	 * Wire up one table.
	 *
	 * @param {HTMLElement} root Wrapper element.
	 */
	function initTable( root ) {
		if ( root.dataset.lstabReady ) {
			return;
		}
		root.dataset.lstabReady = '1';

		var table = root.querySelector( '.lstab-table' );
		if ( ! table ) {
			return;
		}

		var body = table.tBodies[ 0 ];
		if ( ! body ) {
			return;
		}

		var rows = Array.prototype.slice.call( body.rows );
		var input = root.querySelector( '.lstab-search-input' );
		var counter = root.querySelector( '.lstab-count' );
		var empty = root.querySelector( '.lstab-no-results' );

		// Remember the server order so a third click can restore it.
		rows.forEach( function ( row, index ) {
			row.dataset.lstabOrder = String( index );
		} );

		function updateCount( visible ) {
			if ( ! counter ) {
				return;
			}
			var template = counter.getAttribute( 'data-lstab-count-template' ) || '%1$s of %2$s rows';
			counter.textContent = template
				.replace( '%1$s', String( visible ) )
				.replace( '%2$s', String( rows.length ) );
		}

		function filter() {
			var term = input ? input.value.trim().toLowerCase() : '';
			var visible = 0;

			rows.forEach( function ( row ) {
				var match = ! term || row.textContent.toLowerCase().indexOf( term ) !== -1;
				row.hidden = ! match;
				if ( match ) {
					visible++;
				}
			} );

			if ( empty ) {
				empty.hidden = visible !== 0;
			}

			updateCount( visible );
		}

		if ( input ) {
			var timer = null;
			input.addEventListener( 'input', function () {
				window.clearTimeout( timer );
				timer = window.setTimeout( filter, 120 );
			} );
			updateCount( rows.length );
		}

		var headers = table.tHead ? Array.prototype.slice.call( table.tHead.rows[ 0 ].cells ) : [];

		headers.forEach( function ( header, index ) {
			var button = header.querySelector( '.lstab-sort' );
			if ( ! button ) {
				return;
			}

			button.addEventListener( 'click', function () {
				var current = header.getAttribute( 'aria-sort' );
				var next = 'ascending';

				if ( 'ascending' === current ) {
					next = 'descending';
				} else if ( 'descending' === current ) {
					next = 'none';
				}

				headers.forEach( function ( other ) {
					other.removeAttribute( 'aria-sort' );
				} );

				var sorted = rows.slice();

				if ( 'none' === next ) {
					sorted.sort( function ( a, b ) {
						return Number( a.dataset.lstabOrder ) - Number( b.dataset.lstabOrder );
					} );
				} else {
					header.setAttribute( 'aria-sort', next );
					var direction = 'ascending' === next ? 1 : -1;

					sorted.sort( function ( a, b ) {
						var left = cellText( a, index );
						var right = cellText( b, index );

						// Blanks always sink to the bottom, whichever way we sort.
						if ( '' === left && '' === right ) {
							return 0;
						}
						if ( '' === left ) {
							return 1;
						}
						if ( '' === right ) {
							return -1;
						}

						var leftNumber = toNumber( left );
						var rightNumber = toNumber( right );

						if ( null !== leftNumber && null !== rightNumber ) {
							return ( leftNumber - rightNumber ) * direction;
						}

						var comparison = COLLATOR
							? COLLATOR.compare( left, right )
							: left.localeCompare( right );

						return comparison * direction;
					} );
				}

				var fragment = document.createDocumentFragment();
				sorted.forEach( function ( row ) {
					fragment.appendChild( row );
				} );
				body.appendChild( fragment );
			} );
		} );
	}

	/**
	 * Initialise every table on the page.
	 */
	function init() {
		var tables = document.querySelectorAll( '.lstab' );
		Array.prototype.forEach.call( tables, initTable );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Block editor previews and AJAX-loaded content can add tables later.
	window.lstabInit = init;
}() );
