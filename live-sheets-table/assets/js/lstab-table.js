/**
 * Live Sheets Table — front-end enhancement.
 *
 * The table is already complete in the HTML the server sent. This only adds
 * search and sorting on top; if it never runs, the table still works.
 */
( function () {
	'use strict';

	/*
	 * Page buttons, column sorting and the Pro filters are all ordinary links,
	 * and every one of them ends in a fragment (#lstab-table-7) so it lands on
	 * the table rather than the top of the page.
	 *
	 * A great many themes — Divi among them — bind a "smooth scrolling"
	 * handler to every link whose address contains a fragment, compare the
	 * fragment alone, decide the link points at this page, and cancel the
	 * navigation. All three controls then scroll an inch and do nothing. It is
	 * the worst kind of fault to look at: the markup is correct, the console is
	 * silent, the address never changes, and the plugin looks broken rather
	 * than hijacked.
	 *
	 * Listening in the capture phase puts this ahead of those handlers, which
	 * are delegated on the document and therefore run while the click bubbles
	 * back up. The click stops travelling before any of them sees it. Nothing
	 * is prevented here, so the browser is left to follow the link the ordinary
	 * way — which is all these links ever needed.
	 */
	var NAV_LINKS = 'a.lstab-page-link, a.lstab-sort, a.lstabp-facet-value, a.lstabp-facets-clear';

	document.addEventListener(
		'click',
		function ( event ) {
			// A middle click, or one with a modifier held, belongs to the
			// browser: opening one of these in a new tab is a reasonable thing
			// to want, and the theme cannot break that anyway.
			if ( event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}

			var target = event.target;

			if ( ! target || ! target.closest ) {
				return;
			}

			var link = target.closest( NAV_LINKS );

			if ( ! link || ! link.href ) {
				return;
			}

			event.stopPropagation();

			// Fetching the new page and swapping the table in beats reloading
			// the whole page: the reader keeps their place on the page, the
			// theme's header and images are not fetched again, and a slow
			// server no longer means a white screen between page two and
			// page three. If any part of that is unavailable, the link is
			// left alone and the browser follows it the ordinary way.
			if ( swapInPlace( link.href, link ) ) {
				event.preventDefault();
			}
		},
		true
	);

	/* ------------------------------------------- turning pages without a reload */

	/**
	 * Every table wrapper on the page, in the order they appear.
	 *
	 * The wrappers are matched between the current page and the fetched one by
	 * their position rather than by an id: a table's id is generated per render
	 * and differs between the two documents, while the order they stand in does
	 * not.
	 *
	 * @param {Document} doc Document to look in.
	 * @return {Array} Wrapper elements.
	 */
	function containers( doc ) {
		return Array.prototype.slice.call( doc.querySelectorAll( '.lstab-container' ) );
	}

	var loading = false;

	/*
	 * Whether a page has been turned in place yet. The back button is judged
	 * by this rather than by what the history entry carries, because the
	 * entry the reader started on belongs to the browser and holds no state
	 * of ours. Marking it with replaceState first looks like the tidy answer
	 * and is a trap: in Chrome a replaceState turns the next backward step
	 * into a full document load, which is the very thing this avoids.
	 */
	var turned = false;

	/**
	 * Fetch a page and put its tables in place of the ones on screen.
	 *
	 * @param {string}      url  Address to fetch.
	 * @param {HTMLElement} from The link that was clicked, if any.
	 * @param {boolean}     push Whether to add an entry to the browser history.
	 * @return {boolean} True if it took the job on.
	 */
	function swapInPlace( url, from, push ) {
		if ( loading || ! window.fetch || ! window.DOMParser || ! window.history || ! window.history.pushState ) {
			return false;
		}

		var here = containers( document );
		var box = from && from.closest ? from.closest( '.lstab-container' ) : null;
		/*
		 * Which wrapper to keep an eye on. The back button arrives with no link
		 * to go by, and watching none of them was a quiet fault: the element
		 * measured afterwards had already been replaced, so it measured zero,
		 * and the page jumped by the table's own offset on every step back.
		 */
		var at = box ? here.indexOf( box ) : 0;

		if ( from && at < 0 ) {
			return false;
		}

		var watched = box || here[ 0 ];

		if ( ! watched ) {
			return false;
		}

		loading = true;
		watched.setAttribute( 'aria-busy', 'true' );
		watched.classList.add( 'lstab-is-loading' );

		// Where the table sat on screen, so the page can be nudged afterwards
		// to leave it exactly there: a shorter page of rows would otherwise
		// pull everything up and move the ground under the reader.
		var wasAt = watched.getBoundingClientRect().top;

		window.fetch( url, { credentials: 'same-origin' } )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( String( response.status ) );
				}

				return response.text();
			} )
			.then( function ( html ) {
				var fetched = containers( new DOMParser().parseFromString( html, 'text/html' ) );

				if ( ! fetched.length || fetched.length !== here.length ) {
					throw new Error( 'shape changed' );
				}

				here.forEach( function ( old, index ) {
					var fresh = document.importNode( fetched[ index ], true );
					old.parentNode.replaceChild( fresh, old );

					if ( index === at ) {
						watched = fresh;
					}
				} );

				init();

				if ( false !== push ) {
					window.history.pushState( { lstab: true }, '', url );
				}

				// From here on the back button has pages of ours to return to.
				turned = true;

				window.scrollBy( 0, watched.getBoundingClientRect().top - wasAt );

				// Keyboard and screen reader users were standing on a button
				// that no longer exists. Put them on the table itself rather
				// than back at the top of the document.
				var landing = watched.querySelector( '.lstab' );

				if ( landing && from ) {
					landing.setAttribute( 'tabindex', '-1' );
					landing.focus( { preventScroll: true } );
				}
			} )
			.catch( function () {
				// Anything unexpected — an error page, a login wall, a theme
				// that renders tables differently for a fetch — and we hand the
				// job back to the browser rather than leave a dead button.
				window.location.href = url;
			} )
			.then( function () {
				loading = false;

				if ( watched && watched.isConnected ) {
					watched.removeAttribute( 'aria-busy' );
					watched.classList.remove( 'lstab-is-loading' );
				}
			} );

		return true;
	}

	// The back button has to work as well as the page buttons, or turning
	// pages without a reload trades one annoyance for a worse one.
	window.addEventListener( 'popstate', function () {
		if ( turned ) {
			swapInPlace( window.location.href, null, false );
		}
	} );

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
	 * Everything a row says, without the column names the cards show.
	 *
	 * @param {HTMLElement} row Table row.
	 * @return {string} The row's values, separated by spaces.
	 */
	function rowText( row ) {
		var values = row.querySelectorAll( '.lstab-cell-value' );

		if ( ! values.length ) {
			return row.textContent;
		}

		return Array.prototype.map.call( values, function ( value ) {
			return value.textContent;
		} ).join( ' ' );
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
	 * Follow a page that went dark without saying so.
	 *
	 * The stylesheet reads the `color-scheme` a theme declares, which is the
	 * right question and the one a theme with a dark mode usually answers. Some
	 * do not: they paint themselves dark in their own media query and never
	 * mention it, and against those the table would sit as a white card on a
	 * black page.
	 *
	 * So the page is measured. If what is actually painted behind the table is
	 * dark while the table is light, the table is told to consider itself dark
	 * — one property, which is all `light-dark()` needs to change its mind
	 * about every colour at once.
	 *
	 * Only ever in that direction. A light page is left alone, which is the
	 * common case and the one where a change of mind after the page is drawn
	 * would be visible as a flicker.
	 *
	 * @param {HTMLElement} root Wrapper element.
	 * @return {void}
	 */
	function followPage( root ) {
		/**
		 * How much light a colour puts out, roughly enough to sort dark from
		 * light.
		 *
		 * @param {string} colour Any computed colour.
		 * @return {number|null} 0 to 1, or null if it is see-through.
		 */
		function brightness( colour ) {
			var parts = ( String( colour ).match( /[\d.]+/g ) || [] ).map( Number );

			if ( parts.length < 3 || ( parts.length > 3 && parts[ 3 ] < 0.5 ) ) {
				return null;
			}

			return ( 0.2126 * parts[ 0 ] + 0.7152 * parts[ 1 ] + 0.0722 * parts[ 2 ] ) / 255;
		}

		var behind = null;

		for ( var el = root.parentElement; el && behind === null; el = el.parentElement ) {
			behind = brightness( window.getComputedStyle( el ).backgroundColor );
		}

		var frame = root.querySelector( '.lstab-scroll' );
		var mine = frame ? brightness( window.getComputedStyle( frame ).backgroundColor ) : null;

		if ( null !== behind && behind < 0.35 && null !== mine && mine > 0.5 ) {
			root.style.colorScheme = 'dark';
		}
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

		/**
		 * Dress the bar when it is lying on the rows, undress it when it is not.
		 *
		 * While any part of the table is still below the top of the bar, the
		 * bar is on top of the data and has to look like a control rather than
		 * a rule drawn across it. Once the table ends above the bar, it is
		 * sitting on the page and gives the paper back.
		 *
		 * @return {void}
		 */
		function dressBar() {
			// A pixel of slack: sub-pixel layout must not read as overlap.
			var onTheRows = scroller.getBoundingClientRect().bottom - bar.getBoundingClientRect().top > 1;

			bar.classList.toggle( 'is-floating', ! bar.hidden && onTheRows );
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
		 * Measured from the table rather than from the frame around it. Once
		 * the frame stops being a scroll container — which is what lets the
		 * headings pin to the screen — it reports no scrollable overflow at
		 * all, however far the table sticks out of it, and the slider would
		 * never come back for a window that had since been made narrower.
		 *
		 * @return {number} Scrollable overflow in pixels.
		 */
		function overflow() {
			var table = scroller.querySelector( '.lstab-table' );

			if ( ! table ) {
				return 0;
			}

			return Math.max( table.scrollWidth, table.offsetWidth ) - scroller.clientWidth;
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
				dressBar();
				root.classList.remove( 'lstab-has-slider' );
				root.classList.remove( 'lstab-is-scrolled' );
				// Measured, not assumed: only now can the frame stop being a
				// scroll container, which is what lets the headings pin to the
				// screen rather than to the frame.
				root.classList.add( 'lstab-fits' );
				return;
			}

			bar.hidden = false;

			/*
			 * Without this the dressing waited for the first scroll: a table
			 * already lying under the bar when the page opened showed a bare
			 * line across its rows until somebody moved the page — which is
			 * the one fault this measurement exists to prevent.
			 */
			dressBar();

			root.classList.remove( 'lstab-fits' );
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

		/*
		 * A sticky element cannot tell CSS whether it is currently stuck, and
		 * the two states want to look different: floating over rows it needs
		 * its own paper and edge, settled under the table it should disappear
		 * into the page — so ask the rows. While any part of the table is still
		 * below the top of the bar, the bar is lying on the data and has to
		 * look like a control rather than a rule drawn across it. Once the
		 * table ends above the bar, it is sitting on the page and gives the
		 * paper back.
		 *
		 * This used to compare a marker line against the bottom of the window,
		 * which assumed the page itself was the thing scrolling. True on a
		 * published page; false in the editor's preview, where the table sits
		 * in a box that scrolls inside a window it never reaches the bottom
		 * of. There the bar rode over the rows undressed — the one state it
		 * exists to avoid. Overlap means the same thing in either place, and
		 * needs no marker.
		 *
		 * Measured on scroll rather than watched with an IntersectionObserver:
		 * an observer reports threshold crossings, and a jump straight to the
		 * foot of the page can move the boundary from one side to the other
		 * without ever crossing, leaving the bar dressed for a float it was
		 * no longer doing.
		 */
		var pending = false;

		var queueFloat = function () {
			if ( pending ) {
				return;
			}

			pending = true;
			window.requestAnimationFrame( function () {
				pending = false;
				dressBar();
			} );
		};

		/*
		 * Capture, so a scroll inside any box between the table and the window
		 * counts too — the editor's preview is exactly that box, and its
		 * scrolling never reaches the window to be heard.
		 */
		window.addEventListener( 'scroll', queueFloat, { passive: true, capture: true } );
		window.addEventListener( 'resize', queueFloat, { passive: true } );
		dressBar();

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

		followPage( root );

		/*
		 * The slider first, and outside the check below, because it is about
		 * width and nothing else. It used to be set up after that check, so a
		 * paged table too wide for its column was clipped with no slider, no
		 * cards and no way to reach the columns past the edge — which is the
		 * one thing the layout is not allowed to do.
		 */
		initSlider( root );

		/*
		 * A paged table holds one page of the sheet. Searching or sorting it
		 * here would work on that page and present the result as the whole
		 * table, so both are done on the server and the controls are ordinary
		 * links and a form.
		 */
		if ( root.classList.contains( 'lstab-paged' ) ) {
			return;
		}

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

		/**
		 * Take the marking off, leaving the text as it was.
		 *
		 * @param {Element} scope Row or drawer.
		 * @return {void}
		 */
		function unmark( scope ) {
			var marks = scope.querySelectorAll( 'mark.lstab-hit' );

			Array.prototype.forEach.call( marks, function ( hit ) {
				hit.parentNode.replaceChild( document.createTextNode( hit.textContent ), hit );
			} );

			// Otherwise the text either side of a removed mark stays split into
			// separate nodes, and the next search cannot find a term that
			// happens to span the join.
			if ( marks.length ) {
				scope.normalize();
			}
		}

		/**
		 * Mark every occurrence of the search term inside one row.
		 *
		 * Only the text is touched — never the markup around it — so a cell
		 * holding a link keeps its link and gets the match marked inside it.
		 * The labels a card layout repeats beside every value are left alone:
		 * they are the column's name, not the row's answer.
		 *
		 * @param {Element} scope Row or drawer.
		 * @param {string}  term  Lower-cased search term.
		 * @return {void}
		 */
		function mark( scope, term ) {
			var walker = document.createTreeWalker( scope, NodeFilter.SHOW_TEXT, null );
			var targets = [];
			var node;

			while ( ( node = walker.nextNode() ) ) {
				if ( ! node.nodeValue || node.nodeValue.toLowerCase().indexOf( term ) === -1 ) {
					continue;
				}

				if ( node.parentNode && node.parentNode.closest( '.lstab-cell-label, .lstab-detail-key, .screen-reader-text' ) ) {
					continue;
				}

				targets.push( node );
			}

			targets.forEach( function ( text ) {
				var value = text.nodeValue;
				var lower = value.toLowerCase();
				var piece = document.createDocumentFragment();
				var at = 0;
				var found = lower.indexOf( term );

				while ( found !== -1 ) {
					if ( found > at ) {
						piece.appendChild( document.createTextNode( value.slice( at, found ) ) );
					}

					var hit = document.createElement( 'mark' );
					hit.className = 'lstab-hit';
					hit.textContent = value.slice( found, found + term.length );
					piece.appendChild( hit );

					at = found + term.length;
					found = lower.indexOf( term, at );
				}

				if ( at < value.length ) {
					piece.appendChild( document.createTextNode( value.slice( at ) ) );
				}

				text.parentNode.replaceChild( piece, text );
			} );
		}

		function filter() {
			var term = input ? input.value.trim().toLowerCase() : '';
			var visible = 0;

			rows.forEach( function ( row ) {
				var detail = detailOf( row );

				// What is in the drawer is part of the row, so searching finds
				// it. A search that missed the text it can see on screen —
				// because the row happened to be open — would read as broken.
				//
				// Built from the values rather than from the row's whole text:
				// every cell also carries the name of its column, for the card
				// layout to show, and that name is in the row whichever layout
				// is on. Reading the row wholesale made a search for "status"
				// or "price" match every single row — the table stayed as it
				// was, nothing was marked, and the counter said everything
				// still matched. It read as a broken search box.
				var haystack = rowText( row ) + ( detail ? ' ' + detail.textContent : '' );
				var match = ! term || haystack.toLowerCase().indexOf( term ) !== -1;

				/*
				 * Cleared first, always: the previous term's marks are wrong
				 * the moment a letter is added, and a row that no longer
				 * matches must not keep the marking that says it did.
				 */
				unmark( row );

				if ( detail ) {
					unmark( detail );
				}

				if ( match && term ) {
					mark( row, term );

					if ( detail ) {
						mark( detail, term );
					}
				}

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
