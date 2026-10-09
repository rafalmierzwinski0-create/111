( function () {
	'use strict';

	var NAV_LINKS = 'a.lstab-page-link, a.lstab-sort, a.lstab-search-clear, a.lstabp-facet-value, a.lstabp-facets-clear';

	document.addEventListener(
		'click',
		function ( event ) {
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

			if ( swapInPlace( link.href, link ) ) {
				event.preventDefault();
			}
		},
		true
	);

	document.addEventListener(
		'submit',
		function ( event ) {
			var form = event.target;

			if ( ! form || ! form.matches || ! form.matches( 'form.lstab-search-form' ) ) {
				return;
			}

			if ( ! window.URLSearchParams || ! window.FormData ) {
				return;
			}

			var query = new window.URLSearchParams( new window.FormData( form ) ).toString();
			var url = form.action.split( '#' )[ 0 ].split( '?' )[ 0 ] + ( query ? '?' + query : '' );

			if ( swapInPlace( url, form ) ) {
				event.preventDefault();
			}
		},
		true
	);

	function containers( doc ) {
		return Array.prototype.slice.call( doc.querySelectorAll( '.lstab-container' ) );
	}

	var loading = false;

	var turned = false;

	function swapInPlace( url, from, push ) {
		if ( loading || ! window.fetch || ! window.DOMParser || ! window.history || ! window.history.pushState ) {
			return false;
		}

		var here = containers( document );
		var box = from && from.closest ? from.closest( '.lstab-container' ) : null;
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
					if ( box && index !== at ) {
						return;
					}

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

				turned = true;

				window.scrollBy( 0, watched.getBoundingClientRect().top - wasAt );

				var landing = watched.querySelector( '.lstab' );

				var typing = from && from.matches && from.matches( 'form' ) ? watched.querySelector( '.lstab-search-input' ) : null;

				if ( typing ) {
					typing.focus( { preventScroll: true } );
					typing.setSelectionRange( typing.value.length, typing.value.length );
				} else if ( landing && from ) {
					landing.setAttribute( 'tabindex', '-1' );
					landing.focus( { preventScroll: true } );
				}
			} )
			.catch( function () {
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

	window.addEventListener( 'popstate', function () {
		if ( turned ) {
			swapInPlace( window.location.href, null, false );
		}
	} );

	var COLLATOR = typeof Intl !== 'undefined' && Intl.Collator
		? new Intl.Collator( undefined, { numeric: true, sensitivity: 'base' } )
		: null;

	function toNumber( value ) {
		var cleaned = value
			.replace( /\s| /g, '' )
			.replace( /[^0-9,.\-+eE]/g, '' );

		if ( ! cleaned || ! /[0-9]/.test( cleaned ) ) {
			return null;
		}

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

	function toMoment( value ) {
		var text = String( value ).trim();

		if ( ! text ) {
			return null;
		}

		var date = text.match( /^(\d{1,2})\.(\d{1,2})\.(\d{4}|\d{2})(?:[\s,]+(\d{1,2}):([0-5]\d)(?::([0-5]\d))?)?$/ );

		if ( date ) {
			var day = parseInt( date[ 1 ], 10 );
			var month = parseInt( date[ 2 ], 10 );

			if ( day < 1 || day > 31 || month < 1 || month > 12 ) {
				return null;
			}

			var inside = 0;

			if ( date[ 4 ] ) {
				var dateHour = parseInt( date[ 4 ], 10 );

				if ( dateHour > 23 ) {
					return null;
				}

				inside = dateHour * 60 + parseInt( date[ 5 ], 10 ) + ( date[ 6 ] ? parseInt( date[ 6 ], 10 ) / 60 : 0 );
			}

			var year = parseInt( date[ 3 ], 10 );

			if ( 2 === date[ 3 ].length ) {
				year += year < 30 ? 2000 : 1900;
			}

			return {
				kind: 'date',
				value: year * 10000 + month * 100 + day + inside / 1440
			};
		}

		var clock = text.match( /^(\d{1,2}):([0-5]\d)(?::([0-5]\d))?$/ );

		if ( clock ) {
			var hour = parseInt( clock[ 1 ], 10 );

			if ( hour > 23 ) {
				return null;
			}

			return {
				kind: 'clock',
				value: hour * 60 + parseInt( clock[ 2 ], 10 ) + ( clock[ 3 ] ? parseInt( clock[ 3 ], 10 ) / 60 : 0 )
			};
		}

		var half = text.match( /^(\d{1,2})(?:[:.]([0-5]\d))?(?::([0-5]\d))?\s*([ap])\.?\s?m\.?$/i );

		if ( half ) {
			var twelve = parseInt( half[ 1 ], 10 );

			if ( twelve < 1 || twelve > 12 ) {
				return null;
			}

			twelve = ( twelve % 12 ) + ( 'p' === half[ 4 ].toLowerCase() ? 12 : 0 );

			return {
				kind: 'clock',
				value: twelve * 60 + ( half[ 2 ] ? parseInt( half[ 2 ], 10 ) : 0 )
					+ ( half[ 3 ] ? parseInt( half[ 3 ], 10 ) / 60 : 0 )
			};
		}

		return null;
	}

	function rowText( row ) {
		var values = row.querySelectorAll( '.lstab-cell-value' );

		if ( ! values.length ) {
			return row.textContent;
		}

		return Array.prototype.map.call( values, function ( value ) {
			return value.textContent;
		} ).join( ' ' );
	}

	function cellText( row, index ) {
		var cell = row.children[ index ];
		if ( ! cell ) {
			return '';
		}
		var value = cell.querySelector( '.lstab-cell-value' );
		return ( value ? value.textContent : cell.textContent ).trim();
	}

	function cellRank( row, index ) {
		var cell = row.children[ index ];
		var rank = cell ? cell.getAttribute( 'data-lstab-rank' ) : null;

		if ( null === rank || '' === rank || isNaN( Number( rank ) ) ) {
			return null;
		}

		return Number( rank );
	}

	function followPage( root ) {
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

	function initSlider( root ) {
		var scroller = root.querySelector( '.lstab-scroll' );
		var bar = root.querySelector( '.lstab-scrollbar' );

		if ( ! scroller || ! bar ) {
			return;
		}

		function dressBar() {
			var frame = scroller.getBoundingClientRect();
			var view = window.innerHeight || document.documentElement.clientHeight || 0;
			var resting = frame.top > view - Math.min( 140, frame.height );

			bar.classList.toggle( 'is-resting', resting );

			var onTheRows = ! resting && frame.bottom - bar.getBoundingClientRect().top > 1;

			bar.classList.toggle( 'is-floating', ! bar.hidden && onTheRows );
		}

		var track = bar.querySelector( '.lstab-scrollbar-track' );
		var thumb = bar.querySelector( '.lstab-scrollbar-thumb' );

		if ( ! track || ! thumb ) {
			return;
		}

		var dragging = false;
		var grabOffset = 0;
		var rtl = 'rtl' === window.getComputedStyle( scroller ).direction;
		var sign = rtl ? -1 : 1;

		function overflow() {
			var table = scroller.querySelector( '.lstab-table' );

			if ( ! table ) {
				return 0;
			}

			return Math.max( table.scrollWidth, table.offsetWidth ) - scroller.clientWidth;
		}

		function applyEvenColumns() {
			var table = scroller.querySelector( '.lstab-table' );
			var heads = table ? Array.prototype.slice.call( table.querySelectorAll( 'thead th' ) ) : [];

			heads.forEach( function ( th ) {
				th.style.width = '';
			} );
			root.classList.remove( 'lstab-even' );

			if ( heads.length < 2 || overflow() > 2 || 'table' !== window.getComputedStyle( table ).display ) {
				return;
			}

			table.style.width = 'auto';

			var natural = heads.map( function ( th ) {
				return th.getBoundingClientRect().width;
			} );

			table.style.width = '';

			var whole = table.getBoundingClientRect().width;
			var used = natural.reduce( function ( sum, width ) {
				return sum + width;
			}, 0 );
			var share = ( whole - used ) / heads.length;

			if ( share < 1 || ! whole ) {
				return;
			}

			heads.forEach( function ( th, index ) {
				th.style.width = ( ( natural[ index ] + share ) / whole * 100 ).toFixed( 3 ) + '%';
			} );
			root.classList.add( 'lstab-even' );

			if ( overflow() > 2 ) {
				heads.forEach( function ( th ) {
					th.style.width = '';
				} );
				root.classList.remove( 'lstab-even' );
			}
		}

		function sync() {
			var scrollable = overflow();

			if ( scrollable <= 2 ) {
				bar.hidden = true;
				dressBar();
				root.classList.remove( 'lstab-has-slider' );
				root.classList.remove( 'lstab-is-scrolled' );
				root.classList.add( 'lstab-fits' );
				return;
			}

			bar.hidden = false;

			dressBar();

			root.classList.remove( 'lstab-fits' );
			root.classList.add( 'lstab-has-slider' );

			root.classList.toggle( 'lstab-is-scrolled', Math.abs( scroller.scrollLeft ) > 0 );

			var trackWidth = track.clientWidth;
			var ratio = scroller.clientWidth / scroller.scrollWidth;
			var thumbWidth = Math.max( 32, Math.round( trackWidth * ratio ) );
			var travel = trackWidth - thumbWidth;
			var progress = scrollable > 0 ? Math.min( 1, Math.abs( scroller.scrollLeft ) / scrollable ) : 0;

			thumb.style.width = thumbWidth + 'px';
			thumb.style.transform = 'translateX(' + Math.round( travel * ( rtl ? 1 - progress : progress ) ) + 'px)';
			thumb.setAttribute( 'aria-valuenow', String( Math.round( progress * 100 ) ) );
		}

		function scrollToProgress( progress ) {
			progress = Math.min( 1, Math.max( 0, progress ) );
			scroller.scrollLeft = sign * overflow() * progress;
		}

		function scrollFromPointer( clientX ) {
			var rect = track.getBoundingClientRect();
			var thumbWidth = thumb.offsetWidth;
			var travel = rect.width - thumbWidth;

			if ( travel <= 0 ) {
				return;
			}

			var along = ( clientX - rect.left - grabOffset ) / travel;

			scrollToProgress( rtl ? 1 - along : along );
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
					scroller.scrollLeft -= sign * scroller.clientWidth * 0.9;
					break;
				case 'PageDown':
					scroller.scrollLeft += sign * scroller.clientWidth * 0.9;
					break;
				case 'Home':
					scroller.scrollLeft = 0;
					break;
				case 'End':
					scroller.scrollLeft = sign * overflow();
					break;
				default:
					handled = false;
			}

			if ( handled ) {
				event.preventDefault();
			}
		} );

		function measure() {
			applyEvenColumns();
			sync();
		}

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

		window.addEventListener( 'scroll', queueFloat, { passive: true, capture: true } );
		window.addEventListener( 'resize', queueFloat, { passive: true } );
		dressBar();

		scroller.addEventListener( 'scroll', sync, { passive: true } );

		if ( window.ResizeObserver ) {
			var observer = new window.ResizeObserver( measure );
			observer.observe( scroller );
			observer.observe( root );
		} else {
			window.addEventListener( 'resize', measure );
		}

		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( measure ).catch( function () {} );
		}

		root.addEventListener( 'lstab:resize', measure );

		measure();
	}

	function initTable( root ) {
		if ( root.dataset.lstabReady ) {
			return;
		}
		root.dataset.lstabReady = '1';

		followPage( root );

		initSlider( root );

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

		var rows = Array.prototype.slice.call( body.rows ).filter( function ( row ) {
			return ! row.classList.contains( 'lstab-detail' );
		} );

		function detailOf( row ) {
			var next = row.nextElementSibling;

			return next && next.classList.contains( 'lstab-detail' ) ? next : null;
		}

		var input = root.querySelector( '.lstab-search-input' );
		var counter = root.querySelector( '.lstab-count' );
		var empty = root.querySelector( '.lstab-no-results' );

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

		function unmark( scope ) {
			var marks = scope.querySelectorAll( 'mark.lstab-hit' );

			Array.prototype.forEach.call( marks, function ( hit ) {
				hit.parentNode.replaceChild( document.createTextNode( hit.textContent ), hit );
			} );

			if ( marks.length ) {
				scope.normalize();
			}
		}

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

				var haystack = rowText( row ) + ( detail ? ' ' + detail.textContent : '' );
				var match = ! term || haystack.toLowerCase().indexOf( term ) !== -1;

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

		function openState( row ) {
			var button = row.querySelector( '.lstab-open' );

			return button ? button.getAttribute( 'aria-expanded' ) : 'false';
		}

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

						if ( '' === left && '' === right ) {
							return 0;
						}
						if ( '' === left ) {
							return 1;
						}
						if ( '' === right ) {
							return -1;
						}

						var leftPlace = cellRank( a, index );
						var rightPlace = cellRank( b, index );

						if ( null !== leftPlace || null !== rightPlace ) {
							if ( null === leftPlace || null === rightPlace ) {
								return ( null === leftPlace ? 1 : -1 ) * direction;
							}

							if ( leftPlace !== rightPlace ) {
								return ( leftPlace - rightPlace ) * direction;
							}
						}

						var leftMoment = toMoment( left );
						var rightMoment = toMoment( right );

						if ( leftMoment || rightMoment ) {
							var leftRank = leftMoment ? ( 'date' === leftMoment.kind ? 0 : 1 ) : 2;
							var rightRank = rightMoment ? ( 'date' === rightMoment.kind ? 0 : 1 ) : 2;

							if ( leftRank !== rightRank ) {
								return ( leftRank - rightRank ) * direction;
							}

							if ( leftMoment && rightMoment ) {
								return ( leftMoment.value - rightMoment.value ) * direction;
							}
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

					if ( detail ) {
						fragment.appendChild( detail );
					}
				} );
				body.appendChild( fragment );
			} );
		} );
	}

	function init() {
		var tables = document.querySelectorAll( '.lstab' );
		Array.prototype.forEach.call( tables, initTable );
	}

	var asked = false;

	function inUse( box ) {
		var search = box.querySelector( '.lstab-search-input' );
		var scroller = box.querySelector( '.lstab-scroll' );

		return !! ( ( search && search.value ) ||
			( scroller && Math.abs( scroller.scrollLeft ) > 2 ) ||
			box.querySelector( 'th[aria-sort="ascending"], th[aria-sort="descending"], .lstab-open[aria-expanded="true"], .lstabp-facet.is-on' ) ||
			( document.activeElement && box.contains( document.activeElement ) ) );
	}

	function swapQuietly( copies ) {
		var ids = Object.keys( copies );

		if ( ! ids.length || ! window.DOMParser ) {
			return;
		}

		var url = new window.URL( window.location.href );
		url.hash = '';
		url.searchParams.set( 'lstab-copy', ids.map( function ( id ) {
			return copies[ id ];
		} ).join( '' ).slice( 0, 24 ) );

		window.fetch( url.toString(), { credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.ok ? response.text() : '';
			} )
			.then( function ( html ) {
				if ( ! html ) {
					return;
				}

				var fetched = containers( new DOMParser().parseFromString( html, 'text/html' ) );
				var swapped = false;

				containers( document ).forEach( function ( box, index ) {
					var root = box.querySelector( '.lstab[data-lstab-copy]' );
					var twin = fetched[ index ] ? fetched[ index ].querySelector( '.lstab[data-lstab-copy]' ) : null;

					if ( ! root || ! twin || inUse( box ) ) {
						return;
					}

					var id = root.getAttribute( 'data-lstab-id' );

					if ( ! copies[ id ] || twin.getAttribute( 'data-lstab-id' ) !== id ||
						twin.getAttribute( 'data-lstab-copy' ) === root.getAttribute( 'data-lstab-copy' ) ) {
						return;
					}

					var before = box.getBoundingClientRect();
					var fresh = document.importNode( fetched[ index ], true );

					box.parentNode.replaceChild( fresh, box );

					if ( before.bottom <= 0 ) {
						window.scrollBy( 0, fresh.getBoundingClientRect().height - before.height );
					}

					swapped = true;
				} );

				if ( swapped ) {
					init();
				}
			} )
			.catch( function () {} );
	}

	function span( diff, words ) {
		var steps = [ [ 60, 1 ], [ 3600, 60 ], [ 86400, 3600 ], [ 604800, 86400 ], [ 2592000, 604800 ], [ 31536000, 2592000 ], [ Infinity, 31536000 ] ];
		var unit = 0;

		while ( diff >= steps[ unit ][ 0 ] ) {
			unit++;
		}

		var count = Math.max( 1, 0 === unit ? Math.floor( diff ) : Math.round( diff / steps[ unit ][ 1 ] ) );
		var form = words[ unit ] ? words[ unit ][ 1 === count ? 0 : 1 ] : '%s';

		return form.replace( '%s', String( count ) );
	}

	function sayWhen( root, now, since ) {
		var line = root.querySelector( '.lstab-meta[data-lstab-said]' );

		if ( ! line || ! since || now < since ) {
			return;
		}

		try {
			var said = JSON.parse( line.getAttribute( 'data-lstab-said' ) );
			line.textContent = said.t.replace( '%s', span( now - since, said.u ) );
		} catch ( e ) {}
	}

	function askIfDue( now, due, mine ) {
		var overdue = [];
		var newer = {};

		mine.forEach( function ( root ) {
			var id = root.getAttribute( 'data-lstab-id' );
			var known = due && due[ id ];
			var next = known ? Number( known.n ) : Number( root.getAttribute( 'data-lstab-next' ) );

			if ( known && known.c && known.c !== root.getAttribute( 'data-lstab-copy' ) ) {
				newer[ id ] = known.c;
				return;
			}

			if ( known ) {
				sayWhen( root, now, Number( known.f ) );
			}

			if ( next && now >= next && overdue.indexOf( id ) < 0 ) {
				overdue.push( id );
			}
		} );

		swapQuietly( newer );

		if ( ! overdue.length ) {
			return;
		}

		var body = new window.FormData();
		body.append( 'action', 'lstab_keep_current' );
		body.append( 'tables', overdue.join( ',' ) );

		window.fetch( mine[ 0 ].getAttribute( 'data-lstab-ask' ), { method: 'POST', body: body, credentials: 'same-origin', keepalive: true } )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( answer ) {
				if ( ! answer ) {
					return;
				}

				if ( answer.checked ) {
					mine.forEach( function ( root ) {
						var at = answer.checked[ root.getAttribute( 'data-lstab-id' ) ];

						if ( at ) {
							sayWhen( root, Number( answer.now ), Number( at ) );
						}
					} );
				}

				if ( answer.changed ) {
					swapQuietly( answer.changed );
				}
			} )
			.catch( function () {} );
	}

	function keepCurrent() {
		if ( asked || ! window.fetch || ! window.FormData || ! window.URL ) {
			return;
		}

		var mine = Array.prototype.slice.call( document.querySelectorAll( '.lstab[data-lstab-ask]' ) );

		if ( ! mine.length ) {
			return;
		}

		asked = true;

		var file = mine[ 0 ].getAttribute( 'data-lstab-due' );
		var clientNow = Date.now() / 1000;

		if ( ! file ) {
			askIfDue( clientNow, null, mine );
			return;
		}

		window.fetch( file + '?t=' + Math.floor( Date.now() / 30000 ), { credentials: 'same-origin' } )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( String( response.status ) );
				}

				var stamped = Date.parse( response.headers.get( 'Date' ) || '' );

				return response.json().then( function ( json ) {
					askIfDue( isNaN( stamped ) ? clientNow : stamped / 1000, json && json.t ? json.t : null, mine );
				} );
			} )
			.catch( function () {
				askIfDue( clientNow, null, mine );
			} );
	}

	function start() {
		init();
		keepCurrent();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}

	window.lstabInit = init;
}() );
