# -*- coding: utf-8 -*-
"""
Generator modułu tła. Wydaje CZTERY pliki z jednego źródła:

  FINAL-modul-kod.html + FINAL-css.css        — wersja na jedną stronę (moduł Kod)
  TLO-cala-witryna.html + TLO-cala-witryna.css — wersja na całą witrynę

Skrypt jest w obu identyczny; różni się tylko ogon CSS-a. Trzymanie dwóch
osobnych kopii skończyło się tym, że poprawka trafiła do jednej, a druga
została ze starym kodem — stąd ten generator.
"""

import re

SKRYPT = r'''<div class="tlo" aria-hidden="true">
	<canvas class="tlo-plotno"></canvas>
	<div class="tlo-ziarno"></div>
</div>

<script>
( function () {
	'use strict';

	/*
	 * Zostawiamy PIERWSZE tło i kasujemy ewentualne kopie (gdyby ten sam kod
	 * był wklejony w dwóch miejscach), a potem upewniamy się, że siedzi na
	 * samym początku strony — Divi potrafi trzymać moduł we własnej „teczce"
	 * warstw i tło nie umie spod niej wyjść.
	 */
	var wszystkie = document.querySelectorAll( '.tlo' );
	var i;

	if ( ! wszystkie.length ) {
		return;
	}

	var tlo = wszystkie[ 0 ];

	for ( i = 1; i < wszystkie.length; i++ ) {
		wszystkie[ i ].parentNode.removeChild( wszystkie[ i ] );
	}

	if ( document.body.firstElementChild !== tlo ) {
		document.body.insertBefore( tlo, document.body.firstChild );
	}

	// W Kreatorze Wizualnym chowamy tło, żeby nie przeszkadzało w edycji.
	if ( document.body.className.indexOf( 'et-fb' ) !== -1 || window.location.search.indexOf( 'et_fb=' ) !== -1 ) {
		tlo.style.display = 'none';
		return;
	}

	// Skrypt podpina się tylko raz, nawet gdy Divi przerysuje moduł.
	if ( window.tloPodpiete ) {
		return;
	}

	window.tloPodpiete = true;

	// Bez kursora i przy wyłączonych animacjach zostaje statyczna siatka z CSS-a.
	if ( window.matchMedia( '( prefers-reduced-motion: reduce )' ).matches ) {
		return;
	}

	if ( ! window.matchMedia( '( hover: hover )' ).matches ) {
		return;
	}

	var plotno = tlo.querySelector( '.tlo-plotno' );

	if ( ! plotno || ! plotno.getContext ) {
		return;
	}

	var pisak = plotno.getContext( '2d' );
	tlo.className += ' ma-plotno';

	var SIATKA_X = 88;    // szerokość oczka siatki
	var SIATKA_Y = 44;    // wysokość oczka
	var ZASIEG   = 230;   // jak daleko od kursora siatka w ogóle się rusza
	var SILA     = 34;    // im więcej, tym mocniejsze ugięcie (patrz uwaga niżej)
	var KROK     = 7;     // co ile pikseli próbkujemy linię
	var PLYNNOSC = 0.12;  // jak szybko światło dogania kursor
	var ODDECH   = 0.015; // pełny cykl oddechu mniej więcej co 7 sekund

	var celX = window.innerWidth / 2;
	var celY = window.innerHeight * 0.4;
	var jestX = celX;
	var jestY = celY;
	var oddech = 0;
	var silaTeraz = SILA;
	var klatkaNr = 0;
	var ruchOstatnio = 0;
	var uchwyt = null;

	function dopasuj() {
		var skala = window.devicePixelRatio || 1;

		plotno.width  = Math.round( window.innerWidth  * skala );
		plotno.height = Math.round( window.innerHeight * skala );
		pisak.setTransform( skala, 0, 0, skala, 0, 0 );
	}

	// smoothstep: dochodzi do zera płasko, więc nie widać granicy zasięgu
	function gladko( t ) {
		return t * t * ( 3 - 2 * t );
	}

	/*
	 * Odsunięcie liczymy WPROST z odległości do kursora (dx, dy), a nie
	 * z kierunku (dx/d, dy/d). Kierunek ma długość 1 po obu stronach linii
	 * i w chwili, gdy kursor ją przecina, przeskakuje na przeciwny — linia
	 * szarpała wtedy o podwójną wartość odsunięcia. Licząc z dx i dy,
	 * przy samym kursorze wychodzi zero i przejście jest płynne.
	 *
	 * Skutek uboczny: największe odsunięcie to około 0,26 × SILA, a nie SILA
	 * — stąd pozornie duża wartość SILA przy bardzo delikatnym ugięciu.
	 */
	function przesun( px, py ) {
		var dx = px - jestX;
		var dy = py - jestY;
		var d = Math.sqrt( dx * dx + dy * dy );

		if ( d > ZASIEG ) {
			return { x: px, y: py, moc: 0 };
		}

		var moc = gladko( 1 - d / ZASIEG );
		var k = ( silaTeraz * moc ) / ZASIEG;

		return { x: px + dx * k, y: py + dy * k, moc: moc };
	}

	function przezSrodki( punkty, od, ile ) {
		pisak.moveTo( punkty[ od ].x, punkty[ od ].y );

		for ( var i = od + 1; i < od + ile - 1; i++ ) {
			var sx = ( punkty[ i ].x + punkty[ i + 1 ].x ) / 2;
			var sy = ( punkty[ i ].y + punkty[ i + 1 ].y ) / 2;
			pisak.quadraticCurveTo( punkty[ i ].x, punkty[ i ].y, sx, sy );
		}

		pisak.lineTo( punkty[ od + ile - 1 ].x, punkty[ od + ile - 1 ].y );
	}

	function prosta( odX, odY, doX, doY ) {
		pisak.beginPath();
		pisak.moveTo( odX, odY );
		pisak.lineTo( doX, doY );
		pisak.lineWidth = 1;
		pisak.strokeStyle = 'rgba( 255, 255, 255, .055 )';
		pisak.stroke();
	}

	function linia( odX, odY, doX, doY, pion ) {
		/*
		 * Linia, która w ogóle nie sięga kursora, idzie prosto — jedno
		 * pociągnięcie zamiast dwustu punktów. Na pełnym ekranie rusza się
		 * kilkanaście linii z pięćdziesięciu i to trzyma rysowanie tanie.
		 */
		var odsuniecie = pion ? Math.abs( odX - jestX ) : Math.abs( odY - jestY );

		if ( odsuniecie > ZASIEG ) {
			prosta( odX, odY, doX, doY );
			return;
		}

		var dlugosc = Math.max( Math.abs( doX - odX ), Math.abs( doY - odY ) );
		var ile = Math.max( 2, Math.ceil( dlugosc / KROK ) );
		var punkty = [];
		var i;

		for ( i = 0; i <= ile; i++ ) {
			var t = i / ile;
			punkty.push( przesun( odX + ( doX - odX ) * t, odY + ( doY - odY ) * t ) );
		}

		pisak.beginPath();
		przezSrodki( punkty, 0, punkty.length );
		pisak.lineWidth = 1;
		pisak.strokeStyle = 'rgba( 255, 255, 255, .055 )';
		pisak.stroke();

		/*
		 * Zielone podświetlenie w ośmiu progach: sąsiednie punkty o podobnej
		 * mocy idą jednym pociągnięciem, więc rysujemy kilka kresek zamiast
		 * kilkuset, a przejście i tak wygląda płynnie.
		 *
		 * Każdy próg malujemy dwa razy: szeroko i słabo (to daje poświatę
		 * wokół kreski, jak przy neonie) oraz wąsko i mocno (sama kreska).
		 */
		var prog = -1;
		var od = 0;

		for ( i = 0; i <= punkty.length; i++ ) {
			var teraz = i < punkty.length ? Math.round( punkty[ i ].moc * 8 ) : -1;

			if ( teraz !== prog ) {
				if ( prog > 0 && i - od >= 2 ) {
					var moc = prog / 8;
					var ilePkt = i - od + ( i < punkty.length ? 1 : 0 );

					pisak.beginPath();
					przezSrodki( punkty, od, ilePkt );
					pisak.lineWidth = 3.5;
					pisak.strokeStyle = 'rgba( 95, 227, 207, ' + ( moc * moc * 0.14 ).toFixed( 3 ) + ' )';
					pisak.stroke();

					pisak.beginPath();
					przezSrodki( punkty, od, ilePkt );
					pisak.lineWidth = 1;
					pisak.strokeStyle = 'rgba( 95, 227, 207, ' + ( 0.07 + moc * 0.55 ).toFixed( 3 ) + ' )';
					pisak.stroke();
				}

				prog = teraz;
				od = i;
			}
		}
	}

	function rysuj() {
		var w = window.innerWidth;
		var h = window.innerHeight;
		var px, py;

		pisak.clearRect( 0, 0, w, h );

		/*
		 * Pół piksela przesunięcia. Kreska o szerokości 1 px narysowana na
		 * całkowitej współrzędnej leży okrakiem na dwóch pikselach i każdy
		 * dostaje połowę krycia — siatka wychodzi rozmyta i dwa razy bledsza,
		 * niż zakładają kolory. Na połówce trafia w jeden piksel.
		 */
		for ( px = 0; px <= w + SIATKA_X; px += SIATKA_X ) {
			linia( px + 0.5, 0, px + 0.5, h, true );
		}

		for ( py = 0; py <= h + SIATKA_Y; py += SIATKA_Y ) {
			linia( 0, py + 0.5, w, py + 0.5, false );
		}
	}

	function klatka() {
		uchwyt = null;

		if ( document.hidden ) {
			return;   // karta w tle — nie ma po co rysować
		}

		klatkaNr++;

		jestX += ( celX - jestX ) * PLYNNOSC;
		jestY += ( celY - jestY ) * PLYNNOSC;

		document.documentElement.style.setProperty( '--mx', jestX.toFixed( 1 ) + 'px' );
		document.documentElement.style.setProperty( '--my', jestY.toFixed( 1 ) + 'px' );

		// Oddech: ugięcie samo rośnie i maleje, więc tło żyje przy nieruchomej myszy.
		oddech += ODDECH;
		silaTeraz = SILA * ( 0.55 + 0.45 * Math.sin( oddech ) );

		/*
		 * Gdy mysz stoi, zmienia się tylko oddech — a on jest na tyle powolny,
		 * że trzydzieści klatek na sekundę wystarcza. Przy ruchu myszy rysujemy
		 * każdą klatkę, żeby nadążać.
		 */
		var spokoj = Date.now() - ruchOstatnio > 250;

		if ( ! spokoj || klatkaNr % 2 === 0 ) {
			rysuj();
		}

		uchwyt = window.requestAnimationFrame( klatka );
	}

	function ruszaj() {
		if ( null === uchwyt ) {
			uchwyt = window.requestAnimationFrame( klatka );
		}
	}

	window.addEventListener( 'pointermove', function ( event ) {
		celX = event.clientX;
		celY = event.clientY;
		ruchOstatnio = Date.now();
		ruszaj();
	}, { passive: true } );

	document.addEventListener( 'mouseleave', function () {
		celX = window.innerWidth / 2;
		celY = window.innerHeight * 0.4;
		ruchOstatnio = Date.now();
	} );

	document.addEventListener( 'visibilitychange', function () {
		if ( ! document.hidden ) {
			ruszaj();
		}
	} );

	window.addEventListener( 'resize', function () {
		dopasuj();
		celX = Math.min( celX, window.innerWidth );
		celY = Math.min( celY, window.innerHeight );
		ruszaj();
	}, { passive: true } );

	dopasuj();
	ruszaj();
}() );
</script>
'''


CSS_WSPOLNY = '''.tlo {
	position: fixed;
	inset: 0;
	z-index: -1;
	background: #232a29;
	overflow: hidden;
}

/* Siatka statyczna — zapasowa. Skrypt ją chowa, gdy przejmuje rysowanie. */
.tlo::before {
	content: "";
	position: absolute;
	inset: 0;
	background-image:
		linear-gradient( to right, rgba( 255, 255, 255, .055 ) 1px, transparent 1px ),
		linear-gradient( to bottom, rgba( 255, 255, 255, .055 ) 1px, transparent 1px );
	background-size: 88px 44px;
}

.tlo.ma-plotno::before { display: none; }

.tlo-plotno {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	display: none;
}

.tlo.ma-plotno .tlo-plotno { display: block; }

/* Miękka poświata pod kursorem. */
.tlo::after {
	content: "";
	position: absolute;
	inset: 0;
	background: radial-gradient(
		circle 270px at var( --mx, 50% ) var( --my, 40% ),
		rgba( 95, 227, 207, .16 ) 0%,
		rgba( 95, 227, 207, .056 ) 38%,
		transparent 70%
	);
}

.tlo-ziarno {
	position: absolute;
	inset: 0;
	opacity: .045;
	pointer-events: none;
	background-image: url( "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='3'/%3E%3C/filter%3E%3Crect width='160' height='160' filter='url(%23n)'/%3E%3C/svg%3E" );
}

/* Na dotyku nie ma kursora, więc nie ma czego uginać — zostaje sama siatka. */
@media ( hover: none ) {
	.tlo::after { display: none; }
}
'''

CSS_JEDNA_STRONA = '''
/* -------------------------------------------------------------------------
   Odsłonięcie tła na JEDNEJ stronie. 103315 to numer strony — znajdziesz go
   w adresie edycji (post=103315). Na inną stronę podmień ten numer.
   ------------------------------------------------------------------------- */

body.page-id-103315 #page-container,
body.page-id-103315 #et-main-area,
body.page-id-103315 #main-content,
body.page-id-103315 .et_pb_section,
body.page-id-103315 .et_pb_row,
body.page-id-103315.et-db #et-boc .et-l { background-color: transparent !important; }
'''

CSS_CALA_WITRYNA = '''
/* -------------------------------------------------------------------------
   Odsłonięcie tła na WSZYSTKICH stronach.

   Opakowania strony nigdy nie mają własnego tła w projekcie, więc czyścimy
   je na twardo.
   ------------------------------------------------------------------------- */

#page-container,
#et-main-area,
#main-content,
.et-db #et-boc .et-l {
	background-color: transparent !important;
}

/*
 * Sekcje i wiersze to inna sprawa: Divi domyślnie maluje je na biało, ale
 * bywa, że ustawiasz im tło świadomie. Dlatego czyścimy TYLKO te, którym
 * sam Divi nie nadał klasy "et_pb_with_background" — czyli te, którym tła
 * nie ustawiłeś. Sekcja z kolorem wybranym w kreatorze zostaje nietknięta.
 */
.et_pb_section:not( .et_pb_with_background ),
.et_pb_row:not( .et_pb_with_background ) {
	background-color: transparent !important;
}
'''

NAGLOWEK_JEDNA = '''/* =========================================================================
   TŁO STRONY — siatka, która delikatnie oddycha pod kursorem, z zielonym
   podświetleniem linii.

   Kod HTML idzie do modułu Kod w Divi, ten arkusz do:
   Divi → Opcje motywu → Ogólne → Własny CSS.
   ========================================================================= */

'''

NAGLOWEK_WITRYNA = '''/* =========================================================================
   TŁO WITRYNY — siatka, która delikatnie oddycha pod kursorem, z zielonym
   podświetleniem linii. Działa na KAŻDEJ stronie, bez modułu Kod.

   Kod HTML idzie do: Divi → Opcje motywu → Integracja → „Dodaj kod do
   sekcji <body>". Ten arkusz do: Divi → Opcje motywu → Ogólne → Własny CSS.
   ========================================================================= */

'''


def sprawdz( skrypt ):
	"""Kilka rzeczy, których brak psuł już kiedyś tło po cichu."""
	for musi in ( 'ma-plotno', 'prefers-reduced-motion', 'et-fb', 'tloPodpiete',
	              'devicePixelRatio', 'px + 0.5', 'document.hidden' ):
		if musi not in skrypt:
			raise SystemExit( 'w skrypcie brakuje: ' + musi )

	if 'querySelectorAll( \'body > .tlo\' )' in skrypt:
		raise SystemExit( 'stare kasowanie "body > .tlo" — w trybie całej witryny kasuje samo tło' )

	if skrypt.count( '<script>' ) != 1 or skrypt.count( '</script>' ) != 1:
		raise SystemExit( 'znaczniki <script> się nie zgadzają' )


def zapisz( plik, tresc ):
	with open( plik, 'w', encoding='utf-8' ) as f:
		f.write( tresc )

	print( plik + ' — ' + str( len( tresc.split( chr( 10 ) ) ) ) + ' linii' )


sprawdz( SKRYPT )

zapisz( 'FINAL-modul-kod.html', SKRYPT )
zapisz( 'FINAL-css.css', NAGLOWEK_JEDNA + CSS_WSPOLNY + CSS_JEDNA_STRONA )
zapisz( 'TLO-cala-witryna.html', SKRYPT )
zapisz( 'TLO-cala-witryna.css', NAGLOWEK_WITRYNA + CSS_WSPOLNY + CSS_CALA_WITRYNA )
