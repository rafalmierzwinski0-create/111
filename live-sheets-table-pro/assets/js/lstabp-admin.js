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

	function paint( select ) {
		var row = select.closest( 'td' );
		var swatch = row ? row.querySelector( '.lstabp-swatch' ) : null;

		if ( ! swatch ) {
			return;
		}

		// Written as a whole rather than tweaked property by property, so a
		// look that sets no background clears the previous one's.
		swatch.setAttribute( 'style', styles[ select.value ] || '' );
	}

	function init() {
		var selects = document.querySelectorAll( '.lstabp-style-select' );

		Array.prototype.forEach.call( selects, function ( select ) {
			select.addEventListener( 'change', function () {
				paint( select );
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
