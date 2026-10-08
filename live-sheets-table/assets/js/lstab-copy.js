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

	function clean( value ) {
		return String( value ).replace( /["\[\]]/g, '' ).replace( /\s+/g, ' ' ).trim();
	}

	function build( builder ) {
		var card = builder.closest( '.lstab-usage' );
		var code = card ? card.querySelector( '.lstab-shortcode' ) : null;
		var button = card ? card.querySelector( '.lstab-copy' ) : null;
		var text = '[sheet_table id="' + builder.getAttribute( 'data-lstab-shortcode-builder' ) + '"';

		builder.querySelectorAll( '[data-lstab-att]' ).forEach( function ( field ) {
			var att = field.getAttribute( 'data-lstab-att' );

			if ( 'checkbox' === field.type ) {
				if ( ! field.checked ) {
					text += ' ' + att + '="no"';
				}
				return;
			}

			var value = clean( field.value );

			if ( value ) {
				text += ' ' + att + '="' + value + '"';
			}
		} );

		text += ']';

		if ( code ) {
			code.textContent = text;
		}
		if ( button ) {
			button.setAttribute( 'data-lstab-copy', text );
		}
	}

	function onChange( event ) {
		var builder = event.target.closest ? event.target.closest( '[data-lstab-shortcode-builder]' ) : null;

		if ( builder ) {
			build( builder );
		}
	}

	document.addEventListener( 'input', onChange );
	document.addEventListener( 'change', onChange );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key && event.target.closest && event.target.closest( '[data-lstab-shortcode-builder]' ) ) {
			event.preventDefault();
		}
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
