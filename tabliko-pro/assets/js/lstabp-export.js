( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-lstabp-print]' );

		if ( button ) {
			window.print();
		}
	} );
}() );
