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
	 * Give a scrollable table a visible, draggable slider.
	 *
	 * Native horizontal scrollbars are overlay-only on macOS, iOS and Android:
	 * they fade out, so a table that scrolls just looks cut off. This one is
	 * always visible while there is something to scroll, and disappears when
	 * the table fits.
	 *
	 * @param {HTMLElement} root Wrapper element.
	 */
	function initSlider( root ) {
		var scroller = root.querySelector( '.lstab-scroll' );
		var bar = root.querySelector( '.lstab-scrollbar' );

		if ( ! scroller || ! bar ) {
			return;
		}

		var track = bar.querySelector( '.lstab-scrollbar-track' );
		var thumb = bar.querySelector( '.lstab-scrollbar-thumb' );

		if ( ! track || ! thumb ) {
			return;
		}

		var dragging = false;
		var grabOffset = 0;

		/**
		 * Distance the content can travel.
		 *
		 * @return {number} Scrollable overflow in pixels.
		 */
		function overflow() {
			return scroller.scrollWidth - scroller.clientWidth;
		}

		/**
		 * Ask every column for an equal share of the width, or stop asking.
		 *
		 * Left alone, auto table layout hands the surplus out in proportion to
		 * how wide each column's content already is, so a table of short values
		 * ends up with one sprawling column beside several pinched ones. An
		 * equal share evens that out, and a column whose content genuinely
		 * needs more still takes it from the rest.
		 *
		 * It is only safe once the table fits. On a table that has to scroll,
		 * the browser satisfies the percentages by inflating the table rather
		 * than by clipping it — in testing an 811px table became 1826px.
		 *
		 * @param {number} scrollable Overflow measured at natural widths.
		 * @return {number} Overflow once the decision has been applied.
		 */
		function applyEvenColumns( scrollable ) {
			var table = scroller.querySelector( '.lstab-table' );
			var count = table ? table.querySelectorAll( 'thead th' ).length : 0;

			// In the stacked card layout the cells are grids rather than table
			// columns, and a width would only distort them.
			if ( scrollable > 2 || count < 2 || 'table' !== window.getComputedStyle( table ).display ) {
				root.classList.remove( 'lstab-even' );
				return scrollable;
			}

			root.style.setProperty( '--lstab-col-basis', 'calc(100% / ' + count + ')' );
			root.classList.add( 'lstab-even' );

			// Lifting the per-cell width cap widens what the columns ask for,
			// which on a table that only just fitted is enough to push it into
			// scrolling. Equal shares must never be the reason a table starts
			// to scroll, so a table that no longer fits gets its own widths
			// back.
			scrollable = overflow();

			if ( scrollable > 2 ) {
				root.classList.remove( 'lstab-even' );
				scrollable = overflow();
			}

			return scrollable;
		}

		/**
		 * Size and place the thumb from the current scroll position.
		 */
		function sync() {
			var scrollable = overflow();

			// After the measurement above, so column sizing can never colour
			// the reading that decides whether to apply it.
			scrollable = applyEvenColumns( scrollable );

			// Sub-pixel layout leaves a stray pixel or two on tables that
			// actually fit; treat that as "no overflow" rather than showing a
			// slider that cannot move.
			if ( scrollable <= 2 ) {
				bar.hidden = true;
				root.classList.remove( 'lstab-has-slider' );
				root.classList.remove( 'lstab-is-scrolled' );
				return;
			}

			bar.hidden = false;
			root.classList.add( 'lstab-has-slider' );

			// The pinned column's divider is only meaningful once something is
			// actually hidden behind it.
			root.classList.toggle( 'lstab-is-scrolled', scroller.scrollLeft > 0 );

			var trackWidth = track.clientWidth;
			var ratio = scroller.clientWidth / scroller.scrollWidth;
			var thumbWidth = Math.max( 32, Math.round( trackWidth * ratio ) );
			var travel = trackWidth - thumbWidth;
			var progress = scrollable > 0 ? scroller.scrollLeft / scrollable : 0;

			thumb.style.width = thumbWidth + 'px';
			thumb.style.transform = 'translateX(' + Math.round( travel * progress ) + 'px)';
			thumb.setAttribute( 'aria-valuenow', String( Math.round( progress * 100 ) ) );
		}

		/**
		 * Scroll the table to a fraction of its travel.
		 *
		 * @param {number} progress Value between 0 and 1.
		 */
		function scrollToProgress( progress ) {
			progress = Math.min( 1, Math.max( 0, progress ) );
			scroller.scrollLeft = overflow() * progress;
		}

		/**
		 * Map a pointer position on the track to a scroll position.
		 *
		 * @param {number} clientX Pointer X in viewport coordinates.
		 */
		function scrollFromPointer( clientX ) {
			var rect = track.getBoundingClientRect();
			var thumbWidth = thumb.offsetWidth;
			var travel = rect.width - thumbWidth;

			if ( travel <= 0 ) {
				return;
			}

			scrollToProgress( ( clientX - rect.left - grabOffset ) / travel );
		}

		thumb.addEventListener( 'pointerdown', function ( event ) {
			dragging = true;
			grabOffset = event.clientX - thumb.getBoundingClientRect().left;
			thumb.classList.add( 'is-dragging' );
			thumb.setPointerCapture( event.pointerId );
			event.preventDefault();
		} );

		thumb.addEventListener( 'pointermove', function ( event ) {
			if ( dragging ) {
				scrollFromPointer( event.clientX );
			}
		} );

		function endDrag( event ) {
			if ( ! dragging ) {
				return;
			}
			dragging = false;
			thumb.classList.remove( 'is-dragging' );
			if ( thumb.hasPointerCapture && thumb.hasPointerCapture( event.pointerId ) ) {
				thumb.releasePointerCapture( event.pointerId );
			}
		}

		thumb.addEventListener( 'pointerup', endDrag );
		thumb.addEventListener( 'pointercancel', endDrag );

		// Clicking the track jumps a screenful towards the click.
		track.addEventListener( 'pointerdown', function ( event ) {
			if ( event.target === thumb ) {
				return;
			}
			var rect = track.getBoundingClientRect();
			var direction = event.clientX < thumb.getBoundingClientRect().left ? -1 : 1;
			scroller.scrollLeft += direction * scroller.clientWidth * 0.8;
		} );

		thumb.addEventListener( 'keydown', function ( event ) {
			var step = scroller.clientWidth * 0.25;
			var handled = true;

			switch ( event.key ) {
				case 'ArrowLeft':
					scroller.scrollLeft -= step;
					break;
				case 'ArrowRight':
					scroller.scrollLeft += step;
					break;
				case 'PageUp':
					scroller.scrollLeft -= scroller.clientWidth * 0.9;
					break;
				case 'PageDown':
					scroller.scrollLeft += scroller.clientWidth * 0.9;
					break;
				case 'Home':
					scroller.scrollLeft = 0;
					break;
				case 'End':
					scroller.scrollLeft = overflow();
					break;
				default:
					handled = false;
			}

			if ( handled ) {
				event.preventDefault();
			}
		} );

		/**
		 * Re-measure from scratch, dropping any column sizing already applied
		 * so the table is read at its natural widths.
		 */
		function measure() {
			root.classList.remove( 'lstab-even' );
			sync();
		}

		// Scrolling cannot change what fits, so it only moves the thumb.
		scroller.addEventListener( 'scroll', sync, { passive: true } );

		if ( window.ResizeObserver ) {
			var observer = new window.ResizeObserver( measure );
			observer.observe( scroller );
			observer.observe( root );
		} else {
			window.addEventListener( 'resize', measure );
		}

		// Column widths settle after fonts load, which changes the overflow.
		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( measure ).catch( function () {} );
		}

		root.addEventListener( 'lstab:resize', measure );

		measure();
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

		/*
		 * A paged table holds one page of the sheet. Searching or sorting it
		 * here would work on that page and present the result as the whole
		 * table, so both are done on the server and the controls are ordinary
		 * links and a form. The slider still applies: it is about width.
		 */
		if ( root.classList.contains( 'lstab-paged' ) ) {
			return;
		}

		initSlider( root );

		var table = root.querySelector( '.lstab-table' );
		if ( ! table ) {
			return;
		}

		var body = table.tBodies[ 0 ];
		if ( ! body ) {
			return;
		}

		/*
		 * A drawer is a row of its own in the markup — the only way to put a
		 * full-width panel inside a table — but it is not a row of the table as
		 * anybody reading it means the word. It is never counted, never sorted
		 * on its own, and never searched apart from the row it belongs to.
		 */
		var rows = Array.prototype.slice.call( body.rows ).filter( function ( row ) {
			return ! row.classList.contains( 'lstab-detail' );
		} );

		/**
		 * The drawer belonging to one row, if it has one.
		 *
		 * @param {Element} row A data row.
		 * @return {Element|null} Its drawer.
		 */
		function detailOf( row ) {
			var next = row.nextElementSibling;

			return next && next.classList.contains( 'lstab-detail' ) ? next : null;
		}

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
				var detail = detailOf( row );

				// What is in the drawer is part of the row, so searching finds
				// it. A search that missed the text it can see on screen —
				// because the row happened to be open — would read as broken.
				var haystack = row.textContent + ( detail ? ' ' + detail.textContent : '' );
				var match = ! term || haystack.toLowerCase().indexOf( term ) !== -1;

				row.hidden = ! match;

				if ( detail ) {
					// Hidden with its row; otherwise it follows whether the row
					// is open, which the button is the only thing to decide.
					detail.hidden = ! match || 'true' !== openState( row );
				}

				if ( match ) {
					visible++;
				}
			} );

			if ( empty ) {
				empty.hidden = visible !== 0;
			}

			updateCount( visible );

			// Hiding rows can change the widest column, and so the overflow.
			root.dispatchEvent( new CustomEvent( 'lstab:resize' ) );
		}

		if ( input ) {
			var timer = null;
			input.addEventListener( 'input', function () {
				window.clearTimeout( timer );
				timer = window.setTimeout( filter, 120 );
			} );
			updateCount( rows.length );
		}

		/**
		 * Whether one row's drawer is open, as the button reports it.
		 *
		 * @param {Element} row A data row.
		 * @return {string} 'true' or 'false'.
		 */
		function openState( row ) {
			var button = row.querySelector( '.lstab-open' );

			return button ? button.getAttribute( 'aria-expanded' ) : 'false';
		}

		/*
		 * Opening and closing a drawer. Delegated to the table, so a redraw of
		 * the rows — a sort, a search, a preview rebuilt in the editor — does
		 * not leave dead buttons behind.
		 */
		body.addEventListener( 'click', function ( event ) {
			var button = event.target.closest ? event.target.closest( '.lstab-open' ) : null;

			if ( ! button || ! body.contains( button ) ) {
				return;
			}

			var row = button.closest( 'tr' );
			var detail = row ? detailOf( row ) : null;

			if ( ! detail ) {
				return;
			}

			var open = 'true' !== button.getAttribute( 'aria-expanded' );

			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			row.classList.toggle( 'is-open', open );
			detail.hidden = ! open;

			// An open drawer is taller than the row was, and the slider under a
			// wide table measures what it can see.
			root.dispatchEvent( new CustomEvent( 'lstab:resize' ) );
		} );

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
					var detail = detailOf( row );

					fragment.appendChild( row );

					// Moved with it, or sorting would leave every drawer under
					// somebody else's row.
					if ( detail ) {
						fragment.appendChild( detail );
					}
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
