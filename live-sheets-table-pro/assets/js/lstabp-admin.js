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
			return '--lstabp-pill-line:' + style + ';'
				+ '--lstabp-pill-fill:color-mix(in srgb,' + style + ' 18%,transparent);'
				+ '--lstabp-pill-ink:color-mix(in srgb,' + style + ' 55%,currentColor);';
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
	}

	function init() {
		/*
		 * A rule being typed exists only in this form until it is saved, so it
		 * is handed to the preview with every request it makes. Without this
		 * the only way to see a colour rule was to save and look at the page.
		 */
		window.lstabPreviewFields = window.lstabPreviewFields || [];
		window.lstabPreviewFields.push( function () {
			return { rules: currentRules() };
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
