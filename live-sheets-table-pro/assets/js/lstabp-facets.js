/**
 * Narrowing a long filter menu by typing.
 *
 * A column of forty towns makes a perfectly good filter and an unreadable
 * list. This adds nothing a visitor cannot do without it — every value is
 * still a link, and the filtering itself happens on the server — it only
 * makes a long menu findable.
 *
 * The box is printed hidden and shown here, because a search box that cannot
 * search is worse than a long list.
 */
( function () {
	'use strict';

	/**
	 * Case-insensitive form of a value, matching the server's own folding.
	 *
	 * @param {string} text Text.
	 * @return {string} Folded text.
	 */
	function fold( text ) {
		return String( text ).trim().toLowerCase();
	}

	/**
	 * Wire up one menu's find box.
	 *
	 * @param {Element} box The search input.
	 * @return {void}
	 */
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

				// A value already chosen stays on screen whatever is typed:
				// hiding it would look like the filter had been cleared.
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

		/*
		 * Typing in the box must not close the menu it is inside: a details
		 * element treats a press of Escape, and some keys inside a summary, as
		 * a request to fold itself away.
		 */
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
