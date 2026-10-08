( function () {
	'use strict';

	var settings = window.lstabpRules || {};
	var styles = settings.styles || {};

	function luminance( rgb ) {
		var weights = [ 0.2126, 0.7152, 0.0722 ];

		return rgb.reduce( function ( total, channel, index ) {
			var value = channel / 255;

			value = value <= 0.03928 ? value / 12.92 : Math.pow( ( value + 0.055 ) / 1.055, 2.4 );

			return total + weights[ index ] * value;
		}, 0 );
	}

	function contrast( one, two ) {
		var first = luminance( one );
		var second = luminance( two );

		return ( Math.max( first, second ) + 0.05 ) / ( Math.min( first, second ) + 0.05 );
	}

	function channels( hex ) {
		var match = /^#([0-9a-f]{6})$/i.exec( String( hex ).trim() );

		if ( ! match ) {
			return null;
		}

		return [ 0, 2, 4 ].map( function ( at ) {
			return parseInt( match[ 1 ].substr( at, 2 ), 16 );
		} );
	}

	function deepen( rgb, light ) {
		var red = rgb[ 0 ] / 255;
		var green = rgb[ 1 ] / 255;
		var blue = rgb[ 2 ] / 255;
		var max = Math.max( red, green, blue );
		var min = Math.min( red, green, blue );
		var own = ( max + min ) / 2;
		var span = max - min;
		var saturation = 0;
		var hue;

		if ( span > 0 ) {
			saturation = own > 0.5 ? span / ( 2 - max - min ) : span / ( max + min );
		}

		if ( saturation < 0.2 ) {
			return light > 0.2 ? [ 63, 66, 73 ] : [ 29, 35, 39 ];
		}

		if ( max === red ) {
			hue = ( green - blue ) / span + ( green < blue ? 6 : 0 );
		} else if ( max === green ) {
			hue = ( blue - red ) / span + 2;
		} else {
			hue = ( red - green ) / span + 4;
		}

		return fromHsl( hue / 6, Math.min( 0.75, Math.max( 0.42, saturation * 1.8 ) ), light );
	}

	function fromHsl( hue, saturation, light ) {
		var high = light < 0.5 ? light * ( 1 + saturation ) : light + saturation - light * saturation;
		var low = 2 * light - high;

		return [ hue + 1 / 3, hue, hue - 1 / 3 ].map( function ( shift ) {
			var value;

			shift = ( shift + 1 ) % 1;

			if ( shift < 1 / 6 ) {
				value = low + ( high - low ) * 6 * shift;
			} else if ( shift < 1 / 2 ) {
				value = high;
			} else if ( shift < 2 / 3 ) {
				value = low + ( high - low ) * ( 2 / 3 - shift ) * 6;
			} else {
				value = low;
			}

			return Math.round( value * 255 );
		} );
	}

	function ink( hex ) {
		var rgb = channels( hex );
		var best = [ 29, 35, 39 ];
		var bestRatio = 0;
		var candidates;
		var ratio;
		var at;

		if ( ! rgb ) {
			return '#1d2327';
		}

		candidates = [ deepen( rgb, 0.26 ), deepen( rgb, 0.15 ), [ 29, 35, 39 ], [ 255, 255, 255 ], [ 0, 0, 0 ] ];

		for ( at = 0; at < candidates.length; at++ ) {
			ratio = contrast( rgb, candidates[ at ] );

			if ( ratio >= 4.5 ) {
				best = candidates[ at ];
				break;
			}

			if ( ratio > bestRatio ) {
				bestRatio = ratio;
				best = candidates[ at ];
			}
		}

		return '#' + best.map( function ( channel ) {
			return ( '0' + Math.max( 0, Math.min( 255, channel ) ).toString( 16 ) ).slice( -2 );
		} ).join( '' );
	}

	function pillCss( hex ) {
		return '--lstabp-pill-line:' + hex + ';'
			+ '--lstabp-pill-fill:color-mix(in srgb,' + hex + ' 18%,transparent);'
			+ '--lstabp-pill-ink:color-mix(in srgb,' + hex + ' 35%,currentColor);';
	}

	function cssFor( style, line ) {
		var picker;

		if ( 'custom' === style ) {
			picker = line.querySelector( '.lstabp-own-colour' );
			style = picker ? picker.value : '';
		}

		if ( styles[ style ] ) {
			return styles[ style ];
		}

		if ( ! channels( style ) ) {
			return '';
		}

		var where = line ? line.querySelector( 'select[name*="[scope]"]' ) : null;

		if ( where && 'text' === where.value ) {
			return 'color:' + style + ';';
		}

		if ( where && 'dot' === where.value ) {
			return '--lstabp-dot:' + style + ';';
		}

		if ( where && 'pill' === where.value ) {
			return pillCss( style );
		}

		return 'background-color:' + style + ';color:' + ink( style ) + ';';
	}

	function paint( field ) {
		var line = field.closest( '.lstabp-rule' );
		var swatch = line ? line.querySelector( '.lstabp-swatch' ) : null;
		var chosen = line ? line.querySelector( '.lstabp-style-input:checked' ) : null;

		if ( ! swatch ) {
			return;
		}

		swatch.setAttribute( 'style', chosen ? cssFor( chosen.value, line ) : '' );

		var where = line ? line.querySelector( 'select[name*="[scope]"]' ) : null;

		swatch.classList.toggle( 'lstabp-pill-face', !! ( where && 'pill' === where.value ) );
		swatch.classList.toggle( 'lstabp-dot-face', !! ( where && 'dot' === where.value ) );

		var wheel = line ? line.querySelector( '.lstabp-paint-own' ) : null;
		var own = line ? line.querySelector( '.lstabp-own-colour' ) : null;
		var wrap = line ? line.querySelector( '.lstabp-paint-own-wrap' ) : null;

		if ( wheel && own && wrap ) {
			var mine = chosen && 'custom' === chosen.value;

			wrap.classList.toggle( 'has-colour', !! mine );
			wheel.style.backgroundImage = mine ? 'none' : '';
			wheel.style.backgroundColor = mine ? own.value : '';
		}
	}

	function currentRules() {
		var rules = [];

		Array.prototype.forEach.call( document.querySelectorAll( '.lstabp-rule' ), function ( line ) {
			var column = line.querySelector( '.lstabp-rule-column' );

			if ( ! column || ! column.value ) {
				return;
			}

			var field = function ( selector ) {
				var control = line.querySelector( selector );

				return control ? control.value : '';
			};

			var chosen = line.querySelector( '.lstabp-style-input:checked' );

			rules.push( {
				column: column.value,
				operator: field( 'select[name*="[operator]"]' ),
				value: field( '.lstabp-rule-value' ),
				style: chosen ? chosen.value : '',
				custom: field( '.lstabp-own-colour' ),
				scope: field( 'select[name*="[scope]"]' )
			} );
		} );

		return rules;
	}

	function lookChanged( pick ) {
		var row = pick.closest( '.lstabp-look' );

		if ( ! row ) {
			return;
		}

		row.dataset.lstabpLook = pick.value;
		row.classList.toggle( 'is-on', '' !== pick.value );

		var chosen = null;

		Array.prototype.forEach.call( row.querySelectorAll( '.lstabp-look-opt' ), function ( option ) {
			var radio = option.querySelector( '.lstabp-look-pick' );

			option.classList.toggle( 'is-picked', !! radio && radio.checked );

			if ( radio && radio.checked ) {
				chosen = option;
			}
		} );

		var summary = row.querySelector( '.lstabp-look-now' );

		if ( summary && chosen ) {
			var face = chosen.querySelector( '.lstabp-look-face' );
			var word = chosen.querySelector( '.lstabp-look-word' );
			var shown = summary.querySelector( '.lstabp-look-face' );
			var named = summary.querySelector( '.lstabp-look-now-name' );

			if ( '' === pick.value ) {
				if ( shown ) {
					shown.remove();
				}
			} else if ( face && shown ) {
				shown.replaceWith( face.cloneNode( true ) );
			} else if ( face && named ) {
				summary.insertBefore( face.cloneNode( true ), named );
			}

			if ( word && named ) {
				named.textContent = word.textContent;
			}
		}
	}

	function paintLook( row ) {
		var value = function ( selector, fallback ) {
			var field = row.querySelector( selector );

			return field && field.value ? field.value : fallback;
		};

		var tint = value( '.lstabp-look-tint', '#c7e0f4' );
		var chosenInk = value( '.lstabp-look-ink', '#06100f' );
		var says = value( '.lstabp-look-label', '' ).trim();

		Array.prototype.forEach.call( row.querySelectorAll( '.lstabp-look-opt' ), function ( option ) {
			var radio = option.querySelector( '.lstabp-look-pick' );
			var faces = [ option.querySelector( '.lstabp-look-face' ) ];

			if ( ! radio || ! faces[ 0 ] ) {
				return;
			}

			if ( radio.checked ) {
				var alsoShown = row.querySelector( '.lstabp-look-now .lstabp-look-face' );

				if ( alsoShown ) {
					faces.push( alsoShown );
				}
			}

			faces.forEach( function ( face ) {
				if ( 'bar' === radio.value ) {
					face.style.cssText = '--lstabp-bar:64%;--lstabp-bar-colour:' + tint + ';';
				} else if ( 'pill' === radio.value ) {
					var badge = face.querySelector( '.lstabp-pill-face' );

					if ( badge ) {
						badge.style.cssText = pillCss( tint );
					}
				} else if ( 'tint' === radio.value ) {
					face.style.cssText = 'background-color:' + tint + ';color:' + ink( tint ) + ';';
				} else if ( 'button' === radio.value ) {
					var cta = face.querySelector( '.lstabp-cta-link' );

					if ( cta ) {
						if ( undefined === cta.dataset.lstabpSays ) {
							cta.dataset.lstabpSays = cta.textContent.trim();
						}

						cta.style.cssText = '--lstabp-cta-bg:' + tint + ';--lstabp-cta-ink:' + chosenInk + ';';
						cta.textContent = says || cta.dataset.lstabpSays;
					}
				}
			} );
		} );
	}

	function currentLooks() {
		var looks = {};

		Array.prototype.forEach.call( document.querySelectorAll( '.lstabp-look' ), function ( row ) {
			var pick = row.querySelector( '.lstabp-look-pick:checked' );

			if ( ! pick || ! pick.value ) {
				return;
			}

			var named = pick.name.match( /^lstabp_looks\[(.*)\]\[look\]$/ );

			if ( ! named ) {
				return;
			}

			var field = function ( selector ) {
				var control = row.querySelector( selector );

				return control ? control.value : '';
			};

			looks[ named[ 1 ] ] = {
				look: pick.value,
				tint: field( 'input[name$="[tint]"]' ),
				ink: field( 'input[name$="[ink]"]' ),
				label: field( '.lstabp-look-label' )
			};
		} );

		return looks;
	}

	function currentFacets() {
		return Array.prototype.map.call(
			document.querySelectorAll( '.lstabp-facets-card input[name="lstabp_facets[]"]:checked' ),
			function ( box ) {
				return box.value;
			}
		);
	}

	function foldList( list, isOn, words ) {
		var step = Number( list.dataset.lstabpFold || 0 );
		var items = Array.prototype.filter.call( list.children, function ( item ) {
			return 'LI' === item.tagName;
		} );

		if ( ! step || items.length <= step || ! words.more || ! words.all ) {
			return;
		}

		var shown = step;
		var more = document.createElement( 'p' );
		more.className = 'lstabp-fold-more';

		var next = document.createElement( 'button' );
		next.type = 'button';
		next.className = 'lstab-mini';

		var all = document.createElement( 'button' );
		all.type = 'button';
		all.className = 'lstabp-fold-all';
		all.textContent = words.all;

		more.appendChild( next );
		more.appendChild( all );
		list.parentNode.insertBefore( more, list.nextSibling );

		function apply() {
			var left = shown;
			var hidden = 0;

			items.forEach( function ( item ) {
				var chosen = isOn( item );

				if ( chosen || left > 0 ) {
					item.hidden = false;

					if ( left > 0 ) {
						left--;
					}

					return;
				}

				item.hidden = true;
				hidden++;
			} );

			more.hidden = 0 === hidden;
			next.textContent = words.more.replace( '%1$s', String( Math.min( step, hidden ) ) ).replace( '%2$s', String( hidden ) );
		}

		next.addEventListener( 'click', function () {
			shown += step;
			apply();
			var landed = items.filter( function ( item ) {
				return ! item.hidden;
			} )[ shown - step ];

			if ( landed ) {
				var focusable = landed.querySelector( 'input, select, button' );

				if ( focusable ) {
					focusable.focus( { preventScroll: true } );
				}
			}
		} );

		all.addEventListener( 'click', function () {
			shown = items.length;
			apply();
		} );

		list.addEventListener( 'change', apply );

		apply();
	}

	function init() {
		window.lstabPreviewFields = window.lstabPreviewFields || [];
		window.lstabPreviewFields.push( function () {
			return {
				rules: currentRules(),
				looks: currentLooks(),
				facets: currentFacets()
			};
		} );

		var redrawing = null;

		function redraw() {
			window.clearTimeout( redrawing );
			redrawing = window.setTimeout( function () {
				if ( window.lstabRedrawPreview ) {
					window.lstabRedrawPreview();
				}
			}, 500 );
		}

		var looks = document.querySelector( '.lstabp-looks-card' );

		if ( looks ) {
			looks.addEventListener( 'change', function ( event ) {
				if ( event.target.classList.contains( 'lstabp-look-pick' ) ) {
					lookChanged( event.target );
				}
			} );

			var repaint = function ( event ) {
				var row = event.target.closest ? event.target.closest( '.lstabp-look' ) : null;

				if ( row ) {
					paintLook( row );
				}
			};

			looks.addEventListener( 'input', repaint );
			looks.addEventListener( 'change', repaint );

			looks.addEventListener( 'input', redraw );
			looks.addEventListener( 'change', redraw );

			var resetLooks = looks.querySelector( '.lstabp-looks-reset' );

			var anyLook = function () {
				return Array.prototype.some.call( looks.querySelectorAll( '.lstabp-look-pick:checked' ), function ( radio ) {
					return '' !== radio.value;
				} );
			};

			if ( resetLooks ) {
				resetLooks.addEventListener( 'click', function ( event ) {
					event.preventDefault();

					var tint = resetLooks.getAttribute( 'data-lstabp-default-tint' ) || '#c7e0f4';

					Array.prototype.forEach.call( looks.querySelectorAll( '.lstabp-look' ), function ( row ) {
						var plain = row.querySelector( '.lstabp-look-pick[value=""]' );
						var set = function ( selector, value ) {
							var field = row.querySelector( selector );

							if ( field ) {
								field.value = value;
							}
						};

						set( '.lstabp-look-tint', tint );
						set( '.lstabp-look-ink', '#06100f' );
						set( '.lstabp-look-label', '' );

						if ( plain ) {
							plain.checked = true;
							lookChanged( plain );
						}

						paintLook( row );

						var box = row.querySelector( 'details' );

						if ( box ) {
							box.open = false;
						}
					} );

					resetLooks.disabled = true;
					redraw();
				} );

				looks.addEventListener( 'change', function ( event ) {
					if ( event.target.classList.contains( 'lstabp-look-pick' ) ) {
						resetLooks.disabled = ! anyLook();
					}
				} );
			}

			var lookList = looks.querySelector( '.lstabp-looks' );

			if ( lookList ) {
				foldList(
					lookList,
					function ( item ) {
						var picked = item.querySelector( '.lstabp-look-pick:checked' );

						return !! picked && '' !== picked.value;
					},
					{ more: settings.foldMore || '', all: settings.foldAll || '' }
				);
			}
		}

		var facets = document.querySelector( '.lstabp-facets-card' );

		if ( facets ) {
			facets.addEventListener( 'change', redraw );

			var facetList = facets.querySelector( '.lstabp-facet-picks' );

			if ( facetList ) {
				foldList(
					facetList,
					function ( item ) {
						var box = item.querySelector( 'input[type="checkbox"]' );

						return !! box && box.checked;
					},
					{ more: settings.foldMore || '', all: settings.foldAll || '' }
				);
			}
		}

		var card = document.querySelector( '.lstabp-rules-card' );

		if ( card ) {
			card.addEventListener( 'input', redraw );
			card.addEventListener( 'change', redraw );
		}

		if ( card ) {
			card.addEventListener( 'change', function ( event ) {
				var target = event.target;

				if ( target.classList.contains( 'lstabp-style-input' ) ) {
					paint( target );
				}

				if ( 'SELECT' === target.tagName && -1 !== target.name.indexOf( '[scope]' ) ) {
					paint( target );
				}

				if ( target.classList.contains( 'lstabp-rule-value' ) ) {
					var line = target.closest( '.lstabp-rule' );

					if ( line && target.value.trim() ) {
						line.classList.remove( 'is-new' );
					}
				}
			} );

			card.addEventListener( 'click', function ( event ) {
				var bin = event.target.closest ? event.target.closest( '.lstabp-rule-drop' ) : null;

				if ( ! bin ) {
					return;
				}

				var line = bin.closest( '.lstabp-rule' );

				if ( ! line ) {
					return;
				}

				var neighbour = line.nextElementSibling || line.previousElementSibling;

				line.remove();

				var land = neighbour ? neighbour.querySelector( '.lstabp-rule-column' ) : null;

				( land || bin.ownerDocument.getElementById( 'lstabp-add-rule' ) || document.body ).focus();

				if ( window.lstabpRulesRoom ) {
					window.lstabpRulesRoom();
				}

				redraw();
			} );

			var chooseOwn = function ( event ) {
				var picker = event.target;

				if ( ! picker.classList || ! picker.classList.contains( 'lstabp-own-colour' ) ) {
					return;
				}

				var line = picker.closest( '.lstabp-rule' );
				var own = line ? line.querySelector( '.lstabp-style-input[value="custom"]' ) : null;

				if ( own ) {
					own.checked = true;
				}

				paint( picker );
			};

			card.addEventListener( 'click', chooseOwn );
			card.addEventListener( 'input', chooseOwn );
			card.addEventListener( 'change', chooseOwn );
		}

		var addButton = document.getElementById( 'lstabp-add-rule' );
		var template = document.getElementById( 'lstabp-rule-template' );
		var list = document.querySelector( '.lstabp-rules' );

		if ( addButton && template && list ) {
			var maxRules = Number( settings.maxRules || 0 );

			var checkRoom = function () {
				if ( ! maxRules ) {
					return;
				}

				addButton.hidden = list.querySelectorAll( '.lstabp-rule' ).length >= maxRules;
			};

			checkRoom();

			window.lstabpRulesRoom = checkRoom;

			addButton.addEventListener( 'click', function () {
				var used = Array.prototype.map.call(
					list.querySelectorAll( '[name^="lstabp_rules["]' ),
					function ( field ) {
						var found = /lstabp_rules\[(\d+)\]/.exec( field.getAttribute( 'name' ) || '' );

						return found ? Number( found[ 1 ] ) : -1;
					}
				);
				var next = used.length ? Math.max.apply( null, used ) + 1 : 0;

				var markup = template.innerHTML.split( 'lstabp-new' ).join( String( next ) );
				var holder = document.createElement( 'div' );
				holder.innerHTML = markup;

				var line = holder.querySelector( '.lstabp-rule' );

				if ( ! line ) {
					return;
				}

				list.appendChild( line );

				var column = line.querySelector( '.lstabp-rule-column' );

				if ( column ) {
					column.focus();
				}

				checkRoom();
			} );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
