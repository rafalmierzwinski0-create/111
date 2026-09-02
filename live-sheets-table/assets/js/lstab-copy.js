/**
 * Live Sheets Table — copying a shortcode.
 *
 * The most repeated action in the plugin, and until now the most annoying: the
 * shortcode was a piece of text you had to select by hand without overshooting
 * either bracket. One click, and the button says so.
 */
( function () {
	'use strict';

	var i18n = ( window.lstabCopy || {} ).i18n || {};

	/**
	 * Put text on the clipboard, however this browser allows it.
	 *
	 * The modern API needs a secure context, which a WordPress dashboard on
	 * plain http is not — and plenty of small sites still are. The old
	 * execCommand path is the fallback, so the button works there too.
	 *
	 * @param {string} text What to copy.
	 * @return {Promise} Resolves when the text is on the clipboard.
	 */
	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}

		return new Promise( function ( resolve, reject ) {
			var field = document.createElement( 'textarea' );

			field.value = text;
			field.setAttribute( 'readonly', 'readonly' );
			field.style.position = 'fixed';
			field.style.top = '-1000px';
			document.body.appendChild( field );
			field.select();

			try {
				if ( document.execCommand( 'copy' ) ) {
					resolve();
				} else {
					reject();
				}
			} catch ( error ) {
				reject();
			}

			document.body.removeChild( field );
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '.lstab-copy' ) : null;

		if ( ! button ) {
			return;
		}

		var label = button.querySelector( '.lstab-copy-label' );
		var text = button.getAttribute( 'data-lstab-copy' ) || '';

		if ( ! text || ! label ) {
			return;
		}

		copyText( text ).then(
			function () {
				var original = label.textContent;

				label.textContent = i18n.copied || 'Copied';
				button.classList.add( 'is-done' );

				window.setTimeout( function () {
					label.textContent = original;
					button.classList.remove( 'is-done' );
				}, 1800 );
			},
			function () {
				/*
				 * Copying was refused. Selecting the shortcode leaves the
				 * person one keystroke from having it, which is better than a
				 * button that appears to do nothing.
				 */
				var code = button.parentNode.querySelector( '.lstab-shortcode' );

				if ( code && window.getSelection && document.createRange ) {
					var range = document.createRange();
					range.selectNodeContents( code );
					window.getSelection().removeAllRanges();
					window.getSelection().addRange( range );
				}

				label.textContent = i18n.failed || 'Press Ctrl+C';

				window.setTimeout( function () {
					label.textContent = i18n.copy || 'Copy';
				}, 2600 );
			}
		);
	} );
}() );
