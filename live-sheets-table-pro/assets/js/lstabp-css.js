( function () {
	'use strict';

	var field = document.getElementById( 'lstabp-custom-css' );
	var preview = document.getElementById( 'lstab-preview' );
	var stage = document.getElementById( 'lstab-preview-stage' ) || preview;

	if ( ! field || ! stage || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	var sheet = document.createElement( 'style' );
	var timer = null;
	var pending = null;

	sheet.className = 'lstab-live-css';
	stage.parentNode.insertBefore( sheet, stage.nextSibling );

	function refresh() {
		var css = field.value;

		var stored = stage.querySelector( 'style.lstab-custom-css' );
		if ( stored ) {
			stored.parentNode.removeChild( stored );
		}

		if ( ! css.trim() ) {
			sheet.textContent = '';
			return;
		}

		if ( pending === css ) {
			return;
		}
		pending = css;

		window.wp.apiFetch( {
			path: '/live-sheets-table-pro/v1/scoped-css',
			method: 'POST',
			data: {
				css: css,
				selector: '[data-lstab-preview="stage"]'
			}
		} ).then(
			function ( response ) {
				if ( pending === css ) {
					sheet.textContent = response.css || '';
				}
			},
			function () {
			}
		);
	}

	field.addEventListener( 'input', function () {
		window.clearTimeout( timer );
		timer = window.setTimeout( refresh, 400 );
	} );

	field.addEventListener( 'change', refresh );

	document.addEventListener( 'lstab:preview-drawn', function () {
		pending = null;
		refresh();
	} );
}() );
