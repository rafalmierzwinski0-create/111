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
		/*
		 * Czy arkusz pasuje do znacznikowania.
		 *
		 * Moduł idzie do Divi w dwóch kawałkach i łatwo podmienić jeden,
		 * a drugi zostawić. Strona wygląda wtedy jak zepsuta i nie ma po czym
		 * poznać dlaczego. Odcisk mówi to jednym zdaniem w konsoli.
		 */
		var wKodzie = korzenie[ k ].getAttribute( 'data-odcisk' );
		var wArkuszu = ( getComputedStyle( korzenie[ k ] ).getPropertyValue( '--mz-odcisk' ) || '' ).replace( /["'\s]/g, '' );
		if ( wKodzie && wArkuszu && wKodzie !== wArkuszu && window.console ) {
			console.warn( 'lst-mz: arkusz stylow nie pasuje do kodu modulu (kod ' + wKodzie
				+ ', arkusz ' + wArkuszu + '). Wklej obie czesci z tej samej paczki.' );
		}

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

/*
 * Nachylenie aparatu za kursorem.
 *
 * Tylko tam, gdzie jest prawdziwa mysz: na dotyku „najechanie” odpala się
 * przy stuknięciu i aparat zostawałby przekrzywiony. Przy „mniej ruchu” nic.
 * Prostokąt aparatu jest mierzony raz, przy wejściu kursora: mierzony w ruchu
 * zmieniałby się razem z nachyleniem i aparat drgałby, goniąc sam siebie.
 * Zapis do stylu raz na klatkę, nie przy każdym ruchu myszy.
 */
( function () {
	if ( ! window.matchMedia || ! window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches ) { return; }
	if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) { return; }

	var fony = document.querySelectorAll( '.lst-mz .lst-mz-telefon-rama' );

	for ( var i = 0; i < fony.length; i++ ) {
		( function ( fon ) {
			// Skrypt bywa na stronie kilka razy, bo każda sekcja wklejona
			// w całości niesie swój. Aparat podpinany jest raz.
			if ( fon.getAttribute( 'data-mz-nachyl' ) ) { return; }
			fon.setAttribute( 'data-mz-nachyl', '1' );

			var r = null;
			var x = 0.5;
			var y = 0.5;
			var klatka = 0;

			var rysuj = function () {
				klatka = 0;
				fon.style.setProperty( '--mz-nachyl-y', ( ( x - 0.5 ) * 26 ).toFixed( 2 ) + 'deg' );
				fon.style.setProperty( '--mz-nachyl-x', ( ( 0.5 - y ) * 16 ).toFixed( 2 ) + 'deg' );
				fon.style.setProperty( '--mz-blask-x', ( x * 100 ).toFixed( 1 ) + '%' );
				fon.style.setProperty( '--mz-blask-y', ( y * 100 ).toFixed( 1 ) + '%' );
			};

			fon.addEventListener( 'pointerenter', function () {
				r = fon.getBoundingClientRect();
				fon.classList.add( 'jest-nad' );
			} );

			fon.addEventListener( 'pointermove', function ( e ) {
				if ( ! r ) { r = fon.getBoundingClientRect(); }
				x = Math.min( 1, Math.max( 0, ( e.clientX - r.left ) / r.width ) );
				y = Math.min( 1, Math.max( 0, ( e.clientY - r.top ) / r.height ) );
				if ( ! klatka ) { klatka = window.requestAnimationFrame( rysuj ); }
			} );

			fon.addEventListener( 'pointerleave', function () {
				if ( klatka ) { window.cancelAnimationFrame( klatka ); klatka = 0; }
				r = null;
				fon.classList.remove( 'jest-nad' );
				fon.style.setProperty( '--mz-nachyl-x', '0deg' );
				fon.style.setProperty( '--mz-nachyl-y', '0deg' );
			} );
		} )( fony[ i ] );
	}
} )();
