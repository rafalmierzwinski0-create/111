/**
 * Keeps the colour swatch beside each rule showing the look currently chosen,
 * rather than the one that was saved. Without this the picker contradicts
 * itself the moment anything is changed.
 *
 * Nothing here is required for the rules to work: they are applied on the
 * server, and this only makes the form honest while it is being filled in.
 */
( function () {
	'use strict';

	var settings = window.lstabpRules || {};
	var styles = settings.styles || {};

	/**
	 * The swatch belonging to one rule.
	 *
	 * @param {Element} field Any control inside the rule.
	 * @return {Element|null} Its swatch.
	 */
	function swatchFor( field ) {
		var line = field.closest( '.lstabp-rule' );

		return line ? line.querySelector( '.lstabp-swatch' ) : null;
	}

	function paint( select ) {
		var swatch = swatchFor( select );

		if ( ! swatch ) {
			return;
		}

		// Written as a whole rather than tweaked property by property, so a
		// look that sets no background clears the previous one's.
		swatch.setAttribute( 'style', styles[ select.value ] || '' );
	}

	/**
	 * Show the swatch saying what the rule is about, rather than "Abc".
	 *
	 * Seeing your own word — "Brak", "W magazynie" — in the colour you picked
	 * is the difference between choosing a colour and reading a colour's name.
	 *
	 * @param {Element} field The value field.
	 * @return {void}
	 */
	function label( field ) {
		var swatch = swatchFor( field );

		if ( ! swatch ) {
			return;
		}

		swatch.textContent = field.value.trim() || ( settings.i18n && settings.i18n.sample ) || 'Abc';
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

			rules.push( {
				column: column.value,
				operator: field( 'select[name*="[operator]"]' ),
				value: field( '.lstabp-rule-value' ),
				style: field( '.lstabp-style-select' ),
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
			document.querySelectorAll( '.lstabp-style-select' ),
			function ( select ) {
				select.addEventListener( 'change', function () {
					paint( select );
				} );
			}
		);

		Array.prototype.forEach.call(
			document.querySelectorAll( '.lstabp-rule-value' ),
			function ( field ) {
				field.addEventListener( 'input', function () {
					label( field );
				} );

				// A line being filled in is no longer one of the blank ones
				// waiting at the bottom.
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
