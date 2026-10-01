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
	}, { rootMargin: '0px 0px -6% 0px', threshold: 0.06 } );

	for ( var k = 0; k < korzenie.length; k++ ) {
		korzenie[ k ].classList.add( 'lst-mz-ruch' );

		var cele = korzenie[ k ].querySelectorAll( CELE );
		for ( var i = 0; i < cele.length; i++ ) {
			var el = cele[ i ];

			/*
			 * Numer W SWOIM WIERSZU, nie w całej siatce.
			 *
			 * Przedtem szedł numer po kolei przez wszystkie dziewięć kafelków,
			 * więc ostatni czekał 360 ms. Siedział wtedy przygaszony na środku
			 * ekranu, po czym skakał do pełni: dokładnie ten przeskok na dole,
			 * który widać na filmie. Teraz każdy wiersz zaczyna od nowa, więc
			 * żaden kafelek nie czeka dłużej niż dwa odstępy.
			 *
			 * Wiersz poznajemy po tym, że sąsiad stoi na tej samej wysokości.
			 * Działa tak samo przy trzech kolumnach, przy dwóch i przy jednej,
			 * bo przy jednej każdy kafelek jest sam w swoim wierszu i odstępu
			 * nie dostaje wcale.
			 */
			var bracia = el.parentNode.children;
			var wTabeli = 'TR' === el.tagName;
			var moja = el.offsetTop;
			var n = 0;
			for ( var j = 0; j < bracia.length && bracia[ j ] !== el; j++ ) {
				// Wiersze tabeli stoją jeden pod drugim i mają schodzić po kolei,
				// więc tam liczą się wszystkie, a nie tylko te z tej samej linii.
				if ( wTabeli || bracia[ j ].offsetTop === moja ) { n++; }
			}
			var ile = wTabeli ? 5 : 3;
			if ( n > 0 ) { el.style.setProperty( '--mz-kolej', n > ile ? ile : n ); }

			oko.observe( el );
		}
	}
} )();
