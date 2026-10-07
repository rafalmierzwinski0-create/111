( function () {
	var poGotowej = function ( zrob ) {
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', zrob );
		} else {
			zrob();
		}
	};
	poGotowej( function () {
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
			var wKodzie = korzenie[ k ].getAttribute( 'data-odcisk' );
			if ( 0 === k && wKodzie && window.console ) {
				var starych = 0;
				for ( var s = 0; s < document.styleSheets.length; s++ ) {
					var reguly = null;
					try { reguly = document.styleSheets[ s ].cssRules; } catch ( blad ) { reguly = null; }
					if ( ! reguly ) { continue; }
					var moj = false;
					var biezacy = false;
					for ( var q = 0; q < reguly.length; q++ ) {
						if ( '.lst-mz' !== reguly[ q ].selectorText || ! reguly[ q ].style ) { continue; }
						if ( reguly[ q ].style.getPropertyValue( '--mz-mieta' ) ) { moj = true; }
						if ( reguly[ q ].style.getPropertyValue( '--mz-odcisk' ).replace( /["'\s]/g, '' ) === wKodzie ) { biezacy = true; }
					}
					if ( moj && ! biezacy ) { starych++; }
				}
				if ( starych ) {
					console.warn( 'lst-mz: na stronie jest ' + starych + ' starsza wersja arkusza stylow. Usun ja z pola '
						+ '"Wlasny CSS" w Divi i wyczysc pamiec statycznych plikow CSS (Divi > Opcje motywu > Kreator > Zaawansowane).' );
				}
			}
			var wArkuszu = ( getComputedStyle( korzenie[ k ] ).getPropertyValue( '--mz-odcisk' ) || '' ).replace( /["'\s]/g, '' );
			if ( wKodzie && wArkuszu && wKodzie !== wArkuszu && window.console ) {
				console.warn( 'lst-mz: arkusz stylow nie pasuje do kodu modulu (kod ' + wKodzie
					+ ', arkusz ' + wArkuszu + '). Wklej obie czesci z tej samej paczki.' );
			}
			korzenie[ k ].classList.add( 'lst-mz-ruch' );
			var cele = korzenie[ k ].querySelectorAll( CELE );
			for ( var i = 0; i < cele.length; i++ ) {
				var el = cele[ i ];
				var bracia = el.parentNode.children;
				var wTabeli = 'TR' === el.tagName;
				var moja = el.offsetTop;
				var n = 0;
				for ( var j = 0; j < bracia.length && bracia[ j ] !== el; j++ ) {
					if ( wTabeli || bracia[ j ].offsetTop === moja ) { n++; }
				}
				var ile = wTabeli ? 5 : 3;
				if ( n > 0 ) { el.style.setProperty( '--mz-kolej', n > ile ? ile : n ); }
				oko.observe( el );
			}
		}
	} );
	poGotowej( function () {
		if ( ! window.matchMedia || ! window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches ) { return; }
		if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) { return; }
		var stojaki = document.querySelectorAll( '.lst-mz .lst-mz-telefon-stojak' );
		var aparaty = [];
		for ( var i = 0; i < stojaki.length; i++ ) {
			var fon = stojaki[ i ].querySelector( '.lst-mz-telefon-rama' );
			if ( ! fon || fon.getAttribute( 'data-mz-nachyl' ) ) { continue; }
			fon.setAttribute( 'data-mz-nachyl', '1' );
			var styl = window.getComputedStyle( fon );
			var pozaY = parseFloat( styl.getPropertyValue( '--mz-poza-y' ) ) || 0;
			var pozaX = parseFloat( styl.getPropertyValue( '--mz-poza-x' ) ) || 0;
			aparaty.push( { stojak: stojaki[ i ], fon: fon, nad: false, px: pozaX, py: pozaY,
				x: pozaY, y: pozaX, unies: 0, cx: pozaY, cy: pozaX, cunies: 0, bx: 0.5, by: 0.5 } );
		}
		if ( ! aparaty.length ) { return; }
		var klatka = 0;
		var ostatnio = 0;
		var poPrzewinieciu = false;
		var ekranX = -1;
		var ekranY = -1;
		var krok = function ( teraz ) {
			var dt = ostatnio ? Math.min( 64, teraz - ostatnio ) : 16;
			ostatnio = teraz;
			var dalej = false;
			for ( var k = 0; k < aparaty.length; k++ ) {
				var a = aparaty[ k ];
				var sila = 1 - Math.pow( a.nad ? 0.84 : 0.9, dt / 16.7 );
				a.cx += ( a.x - a.cx ) * sila;
				a.cy += ( a.y - a.cy ) * sila;
				a.cunies += ( a.unies - a.cunies ) * sila;
				if ( Math.abs( a.x - a.cx ) > 0.01 || Math.abs( a.y - a.cy ) > 0.01 || Math.abs( a.unies - a.cunies ) > 0.02 ) {
					dalej = true;
				} else {
					a.cx = a.x; a.cy = a.y; a.cunies = a.unies;
					if ( ! a.nad ) { a.fon.classList.remove( 'jest-3d' ); }
				}
				a.fon.style.setProperty( '--mz-nachyl-y', a.cx.toFixed( 3 ) + 'deg' );
				a.fon.style.setProperty( '--mz-nachyl-x', a.cy.toFixed( 3 ) + 'deg' );
				a.fon.style.setProperty( '--mz-uniesienie', a.cunies.toFixed( 2 ) + 'px' );
				a.fon.style.setProperty( '--mz-blask-x', ( a.bx * 100 ).toFixed( 1 ) + '%' );
				a.fon.style.setProperty( '--mz-blask-y', ( a.by * 100 ).toFixed( 1 ) + '%' );
			}
			klatka = dalej ? window.requestAnimationFrame( krok ) : 0;
			if ( ! dalej ) { ostatnio = 0; }
		};
		var ruszaj = function () {
			if ( ! klatka ) { klatka = window.requestAnimationFrame( krok ); }
		};
		var odloz = function ( a ) {
			if ( ! a.nad ) { return; }
			a.nad = false;
			a.x = a.py; a.y = a.px; a.unies = 0;
			a.fon.classList.remove( 'jest-nad' );
			ruszaj();
		};
		document.addEventListener( 'pointermove', function ( e ) {
			if ( 'mouse' !== e.pointerType && 'pen' !== e.pointerType ) { return; }
			if ( poPrzewinieciu && e.screenX === ekranX && e.screenY === ekranY ) { return; }
			poPrzewinieciu = false;
			ekranX = e.screenX;
			ekranY = e.screenY;
			for ( var k = 0; k < aparaty.length; k++ ) {
				var a = aparaty[ k ];
				var r = a.stojak.getBoundingClientRect();
				var w = r.width ? ( e.clientX - r.left ) / r.width : -1;
				var h = r.height ? ( e.clientY - r.top ) / r.height : -1;
				if ( w < 0 || w > 1 || h < 0 || h > 1 ) { odloz( a ); continue; }
				if ( ! a.nad ) {
					a.nad = true;
					a.fon.classList.add( 'jest-nad', 'jest-3d' );
				}
				a.x = ( w - 0.5 ) * 26;
				a.y = ( 0.5 - h ) * 16;
				a.unies = -6;
				a.bx = w;
				a.by = h;
				ruszaj();
			}
		}, { passive: true } );
		var wszystkieOdloz = function () {
			for ( var k = 0; k < aparaty.length; k++ ) { odloz( aparaty[ k ] ); }
		};
		window.addEventListener( 'scroll', function () {
			poPrzewinieciu = true;
			wszystkieOdloz();
		}, { passive: true } );
		document.documentElement.addEventListener( 'pointerleave', wszystkieOdloz );
		window.addEventListener( 'blur', wszystkieOdloz );
	} );
} )();
