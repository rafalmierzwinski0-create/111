( function () {
	var korzenie = document.querySelectorAll( '.lst-mz' );
	if ( ! korzenie.length || ! window.IntersectionObserver ) { return; }
	if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) { return; }

	var CELE = '.lst-mz-blok > .lst-mz-etykieta, .lst-mz-blok > .lst-mz-wstep, .lst-mz-blok > .lst-mz-naglowek,'
		+ '.lst-mz-stol > .lst-mz-etykieta, .lst-mz-stol > .lst-mz-opis-stolu,'
		+ '.lst-mz-legenda .lst-mz-pozycja, .lst-mz-para, .lst-mz-pas,'
		+ '.lst-mz-listy .lst-mz-kolumna, .lst-mz-kod, .lst-mz-okno.jest-stolem,'
		+ '.lst-mz-stol .lstab-row';

	var oko = new IntersectionObserver( function ( wpisy ) {
		for ( var i = 0; i < wpisy.length; i++ ) {
			if ( wpisy[ i ].isIntersecting ) {
				wpisy[ i ].target.classList.add( 'jest-tu' );
				oko.unobserve( wpisy[ i ].target );
			}
		}
	}, { rootMargin: '0px 0px -10% 0px', threshold: 0.1 } );

	for ( var k = 0; k < korzenie.length; k++ ) {
		korzenie[ k ].classList.add( 'lst-mz-ruch' );

		var cele = korzenie[ k ].querySelectorAll( CELE );
		for ( var i = 0; i < cele.length; i++ ) {
			var el = cele[ i ];

			// Numer w swojej grupie: po nim idzie odstęp między sąsiadami.
			// Ucinany po szóstym, bo dziesięć rzeczy po kolei to czekanie.
			var bracia = el.parentNode.children;
			var n = 0;
			for ( var j = 0; j < bracia.length && bracia[ j ] !== el; j++ ) {
				if ( bracia[ j ].className && ( ' ' + bracia[ j ].className + ' ' ).indexOf( el.className.split( ' ' )[ 0 ] ) > -1 ) { n++; }
			}
			if ( n > 0 ) { el.style.setProperty( '--mz-kolej', n > 6 ? 6 : n ); }

			oko.observe( el );
		}
	}
} )();
