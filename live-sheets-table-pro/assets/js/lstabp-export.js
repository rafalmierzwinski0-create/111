/**
 * The print button. Printing is the one thing here a browser will only do when
 * asked by script, so this is the whole of it; the download is a plain link.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-lstabp-print]' );

		if ( button ) {
			window.print();
		}
	} );
}() );
