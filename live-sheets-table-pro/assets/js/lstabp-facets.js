( function () {
	'use strict';

	function fold( text ) {
		return String( text ).trim().toLowerCase();
	}

	function init( box ) {
		var menu = box.closest( '.lstabp-facet-menu' );

		if ( ! menu ) {
			return;
		}

		var values = menu.querySelectorAll( '.lstabp-facet-value' );
		var empty = menu.querySelector( '.lstabp-facet-none' );

		box.hidden = false;

		box.addEventListener( 'input', function () {
			var term = fold( box.value );
			var shown = 0;

			Array.prototype.forEach.call( values, function ( value ) {
				var text = value.querySelector( '.lstabp-facet-text' );
				var match = ! term || fold( text ? text.textContent : '' ).indexOf( term ) !== -1;

				match = match || value.classList.contains( 'is-picked' );

				value.hidden = ! match;

				if ( match ) {
					shown++;
				}
			} );

			if ( empty ) {
				empty.hidden = shown !== 0;
			}
		} );

		box.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				box.value = '';
				box.dispatchEvent( new Event( 'input' ) );
				event.stopPropagation();
			}
		} );
	}

	function start() {
		Array.prototype.forEach.call( document.querySelectorAll( '.lstabp-facet-find' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
