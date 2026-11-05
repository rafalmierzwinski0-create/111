( function () {
	'use strict';

	var i18n = ( window.lstabCopy || {} ).i18n || {};

	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return Promise.race( [
				navigator.clipboard.writeText( text ),
				new Promise( function ( resolve, reject ) {
					window.setTimeout( reject, 1500 );
				} ),
			] );
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
		var choice = event.target.closest ? event.target.closest( '[data-lstab-set]' ) : null;
		var builder = choice ? choice.closest( '[data-lstab-shortcode-builder]' ) : null;
		var group = choice ? choice.closest( '[data-lstab-att]' ) : null;

		if ( ! builder || ! group ) {
			return;
		}

		var field = group.querySelector( 'input[type="hidden"]' );

		group.setAttribute( 'data-lstab-value', choice.getAttribute( 'data-lstab-set' ) );
		group.querySelectorAll( '[data-lstab-set]' ).forEach( function ( option ) {
			option.setAttribute( 'aria-pressed', option === choice ? 'true' : 'false' );
		} );

		if ( field ) {
			field.value = 'hide' === choice.getAttribute( 'data-lstab-set' ) ? '0' : '1';
			field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}

		if ( window.lstabRedrawPreview ) {
			window.lstabRedrawPreview();
		}
	} );

	window.lstabPreviewFields = window.lstabPreviewFields || [];
	window.lstabPreviewFields.push( function () {
		var shown = {};

		document.querySelectorAll( '[data-lstab-shortcode-builder] [data-lstab-att]' ).forEach( function ( group ) {
			var att = group.getAttribute( 'data-lstab-att' );

			if ( 'search' === att || 'sort' === att || 'meta' === att ) {
				shown[ att ] = 'hide' !== group.getAttribute( 'data-lstab-value' );
			}
		} );

		return shown;
	} );

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
