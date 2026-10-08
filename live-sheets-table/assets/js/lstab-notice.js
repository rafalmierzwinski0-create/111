( function () {
	'use strict';

	var notice = document.querySelector( '.lstab-grace-notice' );
	if ( ! notice ) {
		return;
	}

	var settings = window.lstabNotice || {};

	notice.addEventListener( 'click', function ( event ) {
		if ( ! event.target.classList.contains( 'notice-dismiss' ) ) {
			return;
		}

		var body = new URLSearchParams();
		body.append( 'action', 'lstab_dismiss_grace' );
		body.append( '_wpnonce', notice.getAttribute( 'data-lstab-dismiss' ) || '' );

		window.fetch( settings.ajaxUrl || window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} );
	} );
}() );
