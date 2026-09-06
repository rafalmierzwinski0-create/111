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

		var card = document.querySelector( '.lstabp-rules-card' );

		if ( card ) {
			card.addEventListener( 'input', redraw );
			card.addEventListener( 'change', redraw );
		}

		Array.prototype.forEach.call(
			document.querySelectorAll( '.lstabp-style-input' ),
			function ( input ) {
				input.addEventListener( 'change', function () {
					paint( input );
				} );
			}
		);

		/*
		 * Reaching for the picker is itself the choice: nobody sets a colour of
		 * their own and then expects the rule to stay red because the circle
		 * beside it was never clicked.
		 */
		Array.prototype.forEach.call(
			document.querySelectorAll( '.lstabp-own-colour' ),
			function ( picker ) {
				var choose = function () {
					var line = picker.closest( '.lstabp-rule' );
					var own = line ? line.querySelector( '.lstabp-style-input[value="custom"]' ) : null;

					if ( own ) {
						own.checked = true;
					}

					paint( picker );
				};

				picker.addEventListener( 'input', choose );
				picker.addEventListener( 'change', choose );
			}
		);

		// A line being filled in is no longer one of the blank ones waiting at
		// the bottom.
		Array.prototype.forEach.call(
			document.querySelectorAll( '.lstabp-rule-value' ),
			function ( field ) {
				field.addEventListener( 'change', function () {
					var line = field.closest( '.lstabp-rule' );

					if ( line && field.value.trim() ) {
						line.classList.remove( 'is-new' );
					}
				} );
			}
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
