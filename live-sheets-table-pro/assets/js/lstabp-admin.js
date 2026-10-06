/**
 * Keeps the colour swatch beside each rule showing the look currently chosen,
 * rather than the one that was saved. Without this the picker contradicts
 * itself the moment anything is changed.
 *
 * Nothing here is required for the rules to work: they are applied on the
 * server, and this only makes the form honest while it is being filled in.
 *
 * The one piece of real work is choosing the text colour for a colour the
 * server has never seen — the same reasoning as LSTABP_Rules::ink(), kept in
 * step with it, so that the swatch shown while picking is the swatch the page
 * will get.
 */
( function () {
	'use strict';

	var settings = window.lstabpRules || {};
	var styles = settings.styles || {};

	/**
	 * How much light a colour puts out, by the sRGB definition.
	 *
	 * @param {number[]} rgb Three channels, 0-255.
	 * @return {number} 0 to 1.
	 */
	function luminance( rgb ) {
		var weights = [ 0.2126, 0.7152, 0.0722 ];

		return rgb.reduce( function ( total, channel, index ) {
			var value = channel / 255;

			value = value <= 0.03928 ? value / 12.92 : Math.pow( ( value + 0.055 ) / 1.055, 2.4 );

			return total + weights[ index ] * value;
		}, 0 );
	}

	/**
	 * How far apart two colours are, as the accessibility guidelines count it.
	 *
	 * @param {number[]} one First colour.
	 * @param {number[]} two Second colour.
	 * @return {number} 1 to 21.
	 */
	function contrast( one, two ) {
		var first = luminance( one );
		var second = luminance( two );

		return ( Math.max( first, second ) + 0.05 ) / ( Math.min( first, second ) + 0.05 );
	}

	/**
	 * A hex colour as three channels.
	 *
	 * @param {string} hex '#rrggbb'.
	 * @return {number[]|null} Channels, or null if it is not a colour.
	 */
	function channels( hex ) {
		var match = /^#([0-9a-f]{6})$/i.exec( String( hex ).trim() );

		if ( ! match ) {
			return null;
		}

		return [ 0, 2, 4 ].map( function ( at ) {
			return parseInt( match[ 1 ].substr( at, 2 ), 16 );
		} );
	}

	/**
	 * The same colour taken down to nearly ink, keeping its hue.
	 *
	 * @param {number[]} rgb   Channels.
	 * @param {number}   light How dark to take it, 0 to 1.
	 * @return {number[]} Channels.
	 */
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

		// A grey with a trace of hue is worse than none: amplifying the little
		// blue in #f1f2f4 is all it takes to turn the text navy.
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

	/**
	 * Hue, saturation and lightness back to channels.
	 *
	 * @param {number} hue        0-1.
	 * @param {number} saturation 0-1.
	 * @param {number} light      0-1.
	 * @return {number[]} Channels.
	 */
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

	/**
	 * Text that can be read on a given background.
	 *
	 * @param {string} hex Background colour.
	 * @return {string} Text colour.
	 */
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

		/*
		 * In the order they would be chosen by hand: the colour's own hue, the
		 * same hue deeper, the admin's ink, white, and pure black last. The
		 * first that clears the readability bar wins; if none does — a mid-tone
		 * olive is the classic — the best of the five stands.
		 */
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

	/**
	 * The three properties a badge is made of.
	 *
	 * Written once because two screens draw the same badge: the swatch beside
	 * a colour rule, and the chip that offers a whole column of them.
	 *
	 * The three shares here are the three in LSTABP_Rules::css_for(). They have
	 * to be, or the preview shows one badge and the page another — and the
	 * share on the ink is not a matter of taste: it is what keeps the word
	 * readable when somebody picks white.
	 *
	 * @param {string} hex The badge's colour.
	 * @return {string} Declarations.
	 */
	function pillCss( hex ) {
		return '--lstabp-pill-line:' + hex + ';'
			+ '--lstabp-pill-fill:color-mix(in srgb,' + hex + ' 18%,transparent);'
			+ '--lstabp-pill-ink:color-mix(in srgb,' + hex + ' 35%,currentColor);';
	}

	/**
	 * The CSS one chosen look is made of.
	 *
	 * @param {string} style A hex colour, an effect's name, or 'custom'.
	 * @param {Element} line The rule the choice belongs to.
	 * @return {string} Declarations.
	 */
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

		/*
		 * The same colour worn two ways. Read from the rule's own control, so
		 * the swatch shows what the rule will actually do rather than what the
		 * palette looks like — the whole point of a swatch is that nobody has
		 * to save and go and look.
		 */
		var where = line ? line.querySelector( 'select[name*="[scope]"]' ) : null;

		if ( where && 'text' === where.value ) {
			return 'color:' + style + ';';
		}

		if ( where && 'dot' === where.value ) {
			return '--lstabp-dot:' + style + ';';
		}

		if ( where && 'pill' === where.value ) {
			// The same three properties the server writes; the shape itself
			// comes from the class the swatch is given below.
			return pillCss( style );
		}

		return 'background-color:' + style + ';color:' + ink( style ) + ';';
	}

	/**
	 * Show one rule's swatch in the colour that rule now has.
	 *
	 * @param {Element} field Any control inside the rule.
	 * @return {void}
	 */
	function paint( field ) {
		var line = field.closest( '.lstabp-rule' );
		var swatch = line ? line.querySelector( '.lstabp-swatch' ) : null;
		var chosen = line ? line.querySelector( '.lstabp-style-input:checked' ) : null;

		if ( ! swatch ) {
			return;
		}

		// Written as a whole rather than tweaked property by property, so a
		// look that sets no background clears the previous one's.
		swatch.setAttribute( 'style', chosen ? cssFor( chosen.value, line ) : '' );

		// The pill is a shape as well as a colour, and a shape is a class.
		var where = line ? line.querySelector( 'select[name*="[scope]"]' ) : null;

		swatch.classList.toggle( 'lstabp-pill-face', !! ( where && 'pill' === where.value ) );
		swatch.classList.toggle( 'lstabp-dot-face', !! ( where && 'dot' === where.value ) );

		/*
		 * The wheel wears the colour it stands for once one has been picked,
		 * so the row of chips shows which is the custom one at a glance. It
		 * goes back to the wheel when a palette colour is chosen instead.
		 */
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

	/**
	 * The rules exactly as they stand in the form.
	 *
	 * @return {Array} One entry per filled-in rule.
	 */
	function currentRules() {
		var rules = [];

		Array.prototype.forEach.call( document.querySelectorAll( '.lstabp-rule' ), function ( line ) {
			var column = line.querySelector( '.lstabp-rule-column' );

			// A line with no column chosen is an empty form row, not a rule.
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
				// Sent whatever is chosen, so the server can resolve "custom"
				// exactly as it does on a save.
				custom: field( '.lstabp-own-colour' ),
				scope: field( 'select[name*="[scope]"]' )
			} );
		} );

		return rules;
	}

	/**
	 * Show the fields the chosen look actually uses.
	 *
	 * The choice is kept on the row rather than in the script, so the stylesheet
	 * does the showing and hiding and the page looks right before this ever runs.
	 *
	 * @param {Element} pick The look chooser that changed.
	 * @return {void}
	 */
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

		/*
		 * The closed line has to say the truth. It shows what the column looks
		 * like now, and "now" changed a moment ago — a line still showing the
		 * old look is worse than a line showing nothing, because it is read
		 * without being opened.
		 */
		var summary = row.querySelector( '.lstabp-look-now' );

		if ( summary && chosen ) {
			var face = chosen.querySelector( '.lstabp-look-face' );
			var word = chosen.querySelector( '.lstabp-look-word' );
			var shown = summary.querySelector( '.lstabp-look-face' );
			var named = summary.querySelector( '.lstabp-look-now-name' );

			/*
			 * An ordinary column has nothing to draw, so the closed line
			 * carries the word alone — and a line that has just been set back
			 * to ordinary has to lose the picture it was carrying.
			 */
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

	/**
	 * Repaint one column's chips in the colours it is now wearing.
	 *
	 * The chips are the whole point of the card: they say what a look does by
	 * doing it. A chip drawn once at page load and left there would stop being
	 * true the moment somebody moved the colour picker beside it — which is
	 * the one moment they are looking at it.
	 *
	 * @param {Element} row One column's row.
	 * @return {void}
	 */
	function paintLook( row ) {
		var value = function ( selector, fallback ) {
			var field = row.querySelector( selector );

			return field && field.value ? field.value : fallback;
		};

		var tint = value( '.lstabp-look-tint', '#c7e0f4' );
		// Not "ink": that is the function above, which works out a readable
		// text colour for a given background, and the whole-column chip needs
		// it right here.
		var chosenInk = value( '.lstabp-look-ink', '#06100f' );
		var says = value( '.lstabp-look-label', '' ).trim();

		Array.prototype.forEach.call( row.querySelectorAll( '.lstabp-look-opt' ), function ( option ) {
			var radio = option.querySelector( '.lstabp-look-pick' );
			var faces = [ option.querySelector( '.lstabp-look-face' ) ];

			if ( ! radio || ! faces[ 0 ] ) {
				return;
			}

			// The same chip is drawn twice for whichever look is chosen: once
			// among the choices, once in the closed line above them.
			if ( radio.checked ) {
				var alsoShown = row.querySelector( '.lstabp-look-now .lstabp-look-face' );

				if ( alsoShown ) {
					faces.push( alsoShown );
				}
			}

			faces.forEach( function ( face ) {

				// The same three shapes the server draws, from the same colours.
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
						// What the server drew is the fallback, kept the first
						// time through: it is already translated, and this script
						// has no dictionary of its own.
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

	/**
	 * The column looks exactly as they stand in the form.
	 *
	 * The heading is read from the field's own name rather than from the label
	 * beside it: a heading may hold anything a spreadsheet allows, and the name
	 * is what the save will read too.
	 *
	 * @return {Object} Looks keyed by heading.
	 */
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

	/**
	 * The columns ticked for a filter, as they stand in the form.
	 *
	 * @return {Array} Headings.
	 */
	function currentFacets() {
		return Array.prototype.map.call(
			document.querySelectorAll( '.lstabp-facets-card input[name="lstabp_facets[]"]:checked' ),
			function ( box ) {
				return box.value;
			}
		);
	}

	/**
	 * Show a long list ten at a time, with a button for the next ten.
	 *
	 * A sheet is allowed fifty columns, and a card that draws one row per
	 * column pushed everything after it off the bottom of the screen. Folding
	 * is done here rather than in the stylesheet on purpose: with JavaScript
	 * off the whole list is on the page, which is the only state in which
	 * every column can still be reached.
	 *
	 * What is already chosen is never folded away. Ten rows that hide the one
	 * setting somebody came back to change would be worse than the long list.
	 *
	 * @param {Element}  list  The list to fold.
	 * @param {Function} isOn  Says whether one item is already chosen.
	 * @param {Object}   words The button's wording, from the server.
	 * @return {void}
	 */
	function foldList( list, isOn, words ) {
		var step = Number( list.dataset.lstabpFold || 0 );
		var items = Array.prototype.filter.call( list.children, function ( item ) {
			return 'LI' === item.tagName;
		} );

		/*
		 * No wording, no folding. A button with nothing written on it is a
		 * button nobody can see, and it would be hiding rows behind itself —
		 * so a page that never got the strings keeps the whole list instead.
		 */
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

		/**
		 * Hide everything past the budget, and say how much is left.
		 *
		 * @return {void}
		 */
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
			// The first row that was hidden a moment ago, so the eye lands
			// where the button was pointing rather than back at the top.
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

		/*
		 * Ticking the last visible column would otherwise leave the count in
		 * the button stale, and a column set and then folded away would
		 * vanish mid-edit.
		 */
		list.addEventListener( 'change', apply );

		apply();
	}

	function init() {
		/*
		 * A rule being typed exists only in this form until it is saved, so it
		 * is handed to the preview with every request it makes. Without this
		 * the only way to see a colour rule was to save and look at the page.
		 */
		window.lstabPreviewFields = window.lstabPreviewFields || [];
		window.lstabPreviewFields.push( function () {
			return {
				rules: currentRules(),
				looks: currentLooks(),
				facets: currentFacets()
			};
		} );

		var redrawing = null;

		/**
		 * Ask the editor to draw the preview again, once the typing stops.
		 *
		 * @return {void}
		 */
		function redraw() {
			window.clearTimeout( redrawing );
			redrawing = window.setTimeout( function () {
				if ( window.lstabRedrawPreview ) {
					window.lstabRedrawPreview();
				}
			}, 500 );
		}

		/*
		 * The column looks are a card of their own, so they need a listener of
		 * their own: the one below is bound to the rules card and would never
		 * hear a word said on this one.
		 */
		var looks = document.querySelector( '.lstabp-looks-card' );

		if ( looks ) {
			looks.addEventListener( 'change', function ( event ) {
				if ( event.target.classList.contains( 'lstabp-look-pick' ) ) {
					lookChanged( event.target );
				}
			} );

			/*
			 * A colour moving repaints the chips beside it, so the chip that
			 * says "a pill" is wearing the colour it is about to give the
			 * column. Bound to input as well as change: a colour picker fires
			 * input while it is being dragged and change only when it closes.
			 */
			var repaint = function ( event ) {
				var row = event.target.closest ? event.target.closest( '.lstabp-look' ) : null;

				if ( row ) {
					paintLook( row );
				}
			};

			looks.addEventListener( 'input', repaint );
			looks.addEventListener( 'change', repaint );

			// Typing a button's words redraws as they are typed; the rest of
			// the card only ever changes on a choice being made.
			looks.addEventListener( 'input', redraw );
			looks.addEventListener( 'change', redraw );

			var lookList = looks.querySelector( '.lstabp-looks' );

			if ( lookList ) {
				foldList(
					lookList,
					function ( item ) {
						/*
						 * Read off the control rather than off the "is-on"
						 * class: the class is set by another listener on the
						 * same event, and which of the two runs first is not
						 * something to depend on.
						 */
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

		/*
		 * Delegated, all of it: the "Add a rule" button puts lines on the page
		 * after this runs, and a listener bound to the controls that happened
		 * to exist at load would leave every added line inert.
		 */
		if ( card ) {
			card.addEventListener( 'change', function ( event ) {
				var target = event.target;

				if ( target.classList.contains( 'lstabp-style-input' ) ) {
					paint( target );
				}

				// Changing where the colour goes changes what the colour looks
				// like, so the swatch has to be redrawn for that too.
				if ( 'SELECT' === target.tagName && -1 !== target.name.indexOf( '[scope]' ) ) {
					paint( target );
				}


				// A line being filled in is no longer one of the blank ones
				// waiting at the bottom.
				if ( target.classList.contains( 'lstabp-rule-value' ) ) {
					var line = target.closest( '.lstabp-rule' );

					if ( line && target.value.trim() ) {
						line.classList.remove( 'is-new' );
					}
				}
			} );

			/*
			 * The bin. It takes the line off the page and leaves the saving to
			 * the form's own button, so a rule dropped by accident comes back
			 * by leaving the screen without saving — which is what somebody
			 * who has just deleted the wrong thing reaches for.
			 *
			 * Nothing is renumbered. The store reads the lines in the order
			 * they arrive and numbers them itself, so a gap in the middle of
			 * the field names is not a gap in the saved rules.
			 */
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

				// Somewhere to be after the line under the pointer disappears,
				// or the focus falls back to the document and a keyboard is
				// left at the top of the page.
				var land = neighbour ? neighbour.querySelector( '.lstabp-rule-column' ) : null;

				( land || bin.ownerDocument.getElementById( 'lstabp-add-rule' ) || document.body ).focus();

				if ( window.lstabpRulesRoom ) {
					window.lstabpRulesRoom();
				}

				redraw();
			} );

			/*
			 * Reaching for the picker is itself the choice: nobody sets a
			 * colour of their own and then expects the rule to stay red
			 * because the circle beside it was never clicked. The picker lies
			 * on top of the wheel, so a click on it is a click on the wheel,
			 * and the choice is made whether or not the dialogue that opens is
			 * then cancelled.
			 */
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

		/*
		 * "Add a rule". Three rules used to mean filling the two blank lines,
		 * saving, and coming back for two more; the number of rules somebody
		 * wants is not something a screen can guess.
		 */
		var addButton = document.getElementById( 'lstabp-add-rule' );
		var template = document.getElementById( 'lstabp-rule-template' );
		var list = document.querySelector( '.lstabp-rules' );

		if ( addButton && template && list ) {
			var maxRules = Number( settings.maxRules || 0 );

			/**
			 * Hide the button once the store would refuse the next rule.
			 *
			 * Silently dropping a twenty-first rule at save time is how
			 * somebody loses an afternoon's work without being told.
			 *
			 * @return {void}
			 */
			var checkRoom = function () {
				if ( ! maxRules ) {
					return;
				}

				addButton.hidden = list.querySelectorAll( '.lstabp-rule' ).length >= maxRules;
			};

			checkRoom();

			// Reachable from the bin above, which is bound before this runs.
			window.lstabpRulesRoom = checkRoom;

			addButton.addEventListener( 'click', function () {
				// One past the highest number on the page, so a line added
				// after one was removed cannot land on a name already taken.
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
