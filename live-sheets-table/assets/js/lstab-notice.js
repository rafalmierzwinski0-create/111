/**
 * Live Sheets Table — remember that the countdown was put away.
 *
 * WordPress's own dismiss button only hides the notice for the page you are
 * looking at, which for a countdown means it reappears on the next click and
 * becomes something to scroll past rather than read. This tells the site to
 * remember, so it stays away until the last two days.
 */
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
