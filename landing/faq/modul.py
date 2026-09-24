import re

# -*- coding: utf-8 -*-
"""
FAQ w układzie arkusza — jeden moduł Kod w Divi. Bez tytułu: tytuł stawiasz
osobnym modułem Divi, tak samo jak nad tabelą porównawczą.
"""

EN = {
	'naglowki': ( '#', 'Topic', 'Question' ),
	'pytania': [
		( 'API key', 'Do I need a Google API key?',
		  'No. The plugin reads the public export of the sheet, which works for any sheet shared as '
		  '&bdquo;Anyone with the link &ndash; Viewer&rdquo;. No Google Cloud project, no account with '
		  'us. Private sheets, over an authenticated connection, are a Pro feature.' ),

		( 'Refresh', 'How soon does a change in the sheet show up?',
		  'By default the plugin checks Google every 15 minutes, and you can force a refresh by hand at '
		  'any moment. Pro shortens that gap to a minute. Because the page draws from a stored copy, '
		  'your visitor never waits for Google &mdash; the trade is that a change appears at the next '
		  'sync rather than in the same second.' ),

		( 'Phones', 'My table is very wide. What happens on a phone?',
		  'It stays a table, at full text size, with a slider underneath that moves it sideways. Nothing '
		  'shrinks and nothing disappears. The plugin draws that slider itself, because macOS, iOS and '
		  'Android hide the horizontal bar until you are already scrolling &mdash; exactly when it is '
		  'too late to help.' ),

		( 'Safety', 'Is the sheet content safe to display?',
		  'Yes. Everything from the sheet is escaped on output, so a cell holding HTML &mdash; or '
		  'anything that looks like code &mdash; shows up as plain text and cannot inject '
		  'anything into your page.' ),

		( 'Scheduler', 'What if my server has no working scheduler?',
		  'A table older than the interval you set is checked while the page draws, with a hard '
		  'four-second limit and the stored copy as the safety net. That way a site nobody visits '
		  '&mdash; or a server that blocks WP-Cron &mdash; will not quietly publish last week&rsquo;s '
		  'prices.' ),
	],
}

PL = {
	'naglowki': ( '#', 'Temat', 'Pytanie' ),
	'pytania': [
		( 'Klucz API', 'Czy potrzebuję klucza Google API?',
		  'Nie. Wtyczka czyta publiczny eksport arkusza, co działa dla każdego arkusza udostępnionego '
		  'jako &bdquo;Każda osoba mająca link &ndash; Przeglądający&rdquo;. Bez projektu w Google '
		  'Cloud, bez zakładania konta u nas. Arkusze prywatne, przez połączenie z logowaniem, '
		  'są funkcją Pro.' ),

		( 'Odświeżanie', 'Po jakim czasie zmiana w arkuszu pojawia się na stronie?',
		  'Domyślnie wtyczka sprawdza Google co 15 minut, a odświeżenie możesz w każdej chwili wymusić '
		  'ręcznie. Pro skraca ten odstęp do minuty. Strona rysuje się z zapisanej kopii, więc '
		  'odwiedzający nigdy nie czeka na Google &mdash; ceną jest to, że zmiana pojawia się przy '
		  'najbliższej synchronizacji, a nie w tej samej sekundzie.' ),

		( 'Telefony', 'Moja tabela jest bardzo szeroka. Co się stanie na telefonie?',
		  'Zostaje tabelą, w pełnym rozmiarze tekstu, z suwakiem pod spodem, który przesuwa ją na boki. '
		  'Nic się nie kurczy i nic nie znika. Wtyczka rysuje ten suwak sama, bo macOS, iOS i Android '
		  'chowają poziomy pasek, dopóki już nie przewijasz &mdash; czyli dokładnie wtedy, kiedy jest '
		  'za późno, żeby pomógł.' ),

		( 'Bezpieczeństwo', 'Czy treść z arkusza można bezpiecznie wyświetlić?',
		  'Tak. Wszystko z arkusza jest zabezpieczane przy wypisywaniu, więc komórka zawierająca kod '
		  'HTML &mdash; albo cokolwiek, co wygląda jak kod &mdash; pokaże się jako zwykły tekst '
		  'i niczego nie wstrzyknie w Twoją stronę.' ),

		( 'Harmonogram', 'Co, jeśli mój serwer nie ma działającego harmonogramu?',
		  'Tabela starsza niż ustawiony odstęp jest sprawdzana w trakcie rysowania strony, z twardym '
		  'ograniczeniem do czterech sekund i zapisaną kopią jako zabezpieczeniem. Dzięki temu strona, '
		  'na którą nikt nie wchodzi &mdash; albo serwer blokujący WP-Cron &mdash; nie opublikuje po '
		  'cichu cen sprzed tygodnia.' ),
	],
}


SZABLON = r'''<!-- WERSJA 1 z 23.09 — jeśli pierwsza linijka w module mówi co innego, na stronie jest stary kod. -->
<!-- =========================================================================
     FAQ W UKŁADZIE ARKUSZA — jeden moduł Kod w Divi.

     Bez tytułu: nagłówek sekcji stawiasz osobnym modułem Divi, tak samo jak
     nad tabelą porównawczą.

     Kliknięcie wiersza rozwija odpowiedź jak scaloną komórkę pod spodem.
     Otwarty jest zawsze najwyżej jeden wiersz.

     Szerokość sekcji ustawiasz dwiema wartościami na górze stylu:
     --lst-szerokosc i --lst-max.

     UWAGA: każdy znacznik musi zostać w jednej linijce — Divi wstawia w
     miejscu złamanego wiersza <br />, co rozbija znacznik i wysypuje jego
     atrybuty na stronę.
     ========================================================================= -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">

<section class="lst-faq" id="lst-faq">
	<div class="lst-faq-rama">
		<div class="lst-faq-glowa" aria-hidden="true"><span>{N0}</span><span>{N1}</span><span>{N2}</span></div>
{WIERSZE}
	</div>
</section>

<style>
.lst-faq {
	/* Po tej wartości skrypt poznaje, że arkusz stylów jest na stronie.
	   Gdy go nie ma, skrypt NIE zwija odpowiedzi — lepiej, żeby wyglądały
	   surowo, niż żeby zniknęły. */
	--lst-faq-gotowy: 1;

	--lst-mieta: 95, 227, 207;
	--lst-panel: #1b2221;
	--lst-panel-dol: #161d1c;
	--lst-panel-glowa: #1f2725;
	--lst-linia: rgba( 138, 168, 163, .16 );
	--lst-tekst: #eaf3f1;
	--lst-tekst-2: #9db3b0;
	--lst-tekst-3: #7b918e;
	--lst-mono: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;

	font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif;
	color: var( --lst-tekst );

	/* ---- szerokość: tak jak wiersze na stronie ---- */
	--lst-szerokosc: 90%;
	--lst-max: 1800px;
	--lst-pelna: 100vw;

	width: var( --lst-pelna );
	margin: 0 calc( 50% - var( --lst-pelna ) / 2 );
	overflow-x: clip;
	padding: clamp( 2rem, 4vw, 3.4rem ) 0;
	background: none;
}

.lst-faq.lst-faq { border: 0 !important; outline: 0 !important; }

.lst-faq * { box-sizing: border-box; }

/* Opakowania Divi wokół TEGO modułu — klasę nadaje im skrypt. */
.lst-faq-gniazdo.lst-faq-gniazdo { border: 0 !important; outline: 0 !important; }

/* Divi wstawia <br /> w miejscu każdego złamanego wiersza. */
.lst-faq br { display: none; }

/*
 * Zerowanie tego, co motyw nadaje zwykłym znacznikom. :where() celowo — nie
 * dodaje wagi, więc przegrywa z każdą regułą poniżej. Reset MUSI stać przed
 * nimi: przy równej wadze wygrywa reguła późniejsza, więc reset postawiony
 * na końcu zabierałby krój i odstępy temu, co sam ustawiam.
 */
.lst-faq :where( div, p, span, h2, h3, h4, button, dl, dt, dd, ul, li ) {
	margin: 0;
	padding: 0;
	background: none;
	border: 0;
	border-radius: 0;
	box-shadow: none;
	text-shadow: none;
	text-align: left;
	text-transform: none;
	letter-spacing: normal;
	font: inherit;
	color: inherit;
	width: auto;
	max-width: none;
	min-width: 0;
	list-style: none;
}

/* Ramka. Podwójna klasa celowo: motyw potrafi nadać obramowanie każdemu
   <div> w module i wtedy dookoła pojawia się kreska, której nikt nie zamawiał. */
.lst-faq .lst-faq-rama {
	width: var( --lst-szerokosc );
	max-width: var( --lst-max );
	margin-inline: auto;
	border: 1px solid var( --lst-linia ) !important;
	border-radius: 14px;
	overflow: hidden;
	background: linear-gradient( var( --lst-panel ), var( --lst-panel-dol ) );
	box-shadow: 0 30px 70px -52px rgba( var( --lst-mieta ), .5 );
}

.lst-faq .lst-faq-glowa {
	display: grid;
	grid-template-columns: 3.6rem 11rem 1fr;
	background: var( --lst-panel-glowa );
	border-bottom: 1px solid var( --lst-linia ) !important;
	font-family: var( --lst-mono );
	color: var( --lst-tekst-3 );
}

.lst-faq .lst-faq-glowa span {
	padding: .85rem 1.1rem;
	font-size: .875rem;   /* 14 px */
	letter-spacing: .14em;
	text-transform: uppercase;
}

/* Kreska TYLKO między wierszami. ":first-of-type" tu nie działa, bo pierwszym
   <div> w ramce jest nagłówek kolumn — pierwszy wiersz nigdy nie był „pierwszy
   swojego typu" i dostawał kreskę dublującą się z kreską pod nagłówkiem. */
.lst-faq .lst-faq-wiersz + .lst-faq-wiersz { border-top: 1px solid var( --lst-linia ) !important; }

.lst-faq .lst-faq-pyt {
	display: grid;
	grid-template-columns: 3.6rem 11rem 1fr;
	width: 100%;
	cursor: pointer;
	text-decoration: none !important;
	transition: background-color .2s ease;
}

/*
 * Utwardzenie. Reset :where() wyżej ma wagę zero i przegrywa z motywem, który
 * pisze "button { font-family: ... !important }" — a motywy WordPressa robią
 * tak guzikom rutynowo. Tu, na samych elementach interaktywnych, przebijamy
 * to wprost. Nie stosujemy tego do wszystkiego: !important rozlany po całym
 * module byłby nie do nadpisania z Własnego CSS-a.
 */
.lst-faq .lst-faq-pyt {
	font-family: inherit !important;
	font-size: inherit !important;
	font-weight: 400 !important;
	line-height: inherit !important;
	text-transform: none !important;
	letter-spacing: normal !important;
	background: none !important;
	color: inherit !important;
	padding: 0 !important;
	border: 0 !important;
	border-radius: 0 !important;
	box-shadow: none !important;
	min-height: 0 !important;
}

.lst-faq .lst-faq-nr,
.lst-faq .lst-faq-temat,
.lst-faq .lst-faq-tresc { border: 0 !important; background: none !important; }

.lst-faq .lst-faq-zwoj > p {
	margin: 0 !important;
	background: none !important;
	border: 0 !important;
	text-transform: none !important;
	letter-spacing: normal !important;
}

.lst-faq .lst-faq-pyt:hover { background: rgba( 255, 255, 255, .022 ); }
.lst-faq .lst-faq-pyt:focus-visible { outline: 2px solid rgb( var( --lst-mieta ) ) !important; outline-offset: -2px; }

.lst-faq .lst-faq-nr {
	padding: 1rem 1.1rem;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	color: var( --lst-tekst-3 );
	transition: color .2s ease;
}

.lst-faq .lst-faq-temat {
	padding: 1rem 1.1rem;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	letter-spacing: .06em;
	color: rgb( var( --lst-mieta ) );
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.lst-faq .lst-faq-tresc { padding: 1rem 1.2rem; font-size: 1.25rem; line-height: 1.4; }   /* 20 px */

/* Kreski pionowe i tło kolumny numerów — po utwardzeniu, żeby je przebiły. */
.lst-faq .lst-faq-nr { background: rgba( 255, 255, 255, .018 ) !important;
	border-right: 1px solid var( --lst-linia ) !important; }
.lst-faq .lst-faq-temat { border-right: 1px solid var( --lst-linia ) !important; }

.lst-faq .lst-faq-wiersz.jest-otwarty .lst-faq-nr { color: rgb( var( --lst-mieta ) ); }

/*
 * Rozwijanie: wysokość liczy skrypt, bo "height: auto" nie da się animować.
 * Bez skryptu odpowiedzi są po prostu widoczne — patrz reguła niżej.
 */
.lst-faq .lst-faq-zwoj {
	overflow: hidden;
	transition: height .32s cubic-bezier( .2, .75, .25, 1 );
}

.lst-faq.ma-skrypt .lst-faq-zwoj { height: 0; }

.lst-faq .lst-faq-zwoj > p {
	padding: .2rem 1.2rem 1.35rem calc( 3.6rem + 11rem + 1.2rem );
	max-width: 64rem;
	color: var( --lst-tekst-2 );
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.6;
}

/* Bez skryptu wiersz nie jest przyciskiem — kursor nie może kłamać. */
.lst-faq:not( .ma-skrypt ) .lst-faq-pyt { cursor: default; }

@media ( max-width: 760px ) {
	.lst-faq .lst-faq-glowa,
	.lst-faq .lst-faq-pyt { grid-template-columns: 3rem 1fr; }

	.lst-faq .lst-faq-glowa span:nth-child( 2 ),
	.lst-faq .lst-faq-temat { display: none; }

	.lst-faq .lst-faq-zwoj > p { padding-left: calc( 3rem + 1.2rem ); }
}

@media ( prefers-reduced-motion: reduce ) {
	.lst-faq .lst-faq-zwoj { transition: none; }
	.lst-faq .lst-faq-pyt { transition: none; }
}
</style>
'''


SZABLON += r'''
<script>
( function () {
	'use strict';

	/*
	 * Skrypt może leżeć w zakładce Integracja, czyli tuż za otwarciem <body>
	 * — wtedy w chwili jego uruchomienia FAQ jeszcze nie istnieje. Czekamy
	 * więc na wczytanie treści strony. Gdy skrypt siedzi w module Kod, pod
	 * sekcją, warunek jest już spełniony i rusza od razu.
	 */
	function start() {
		var sekcja = document.getElementById( 'lst-faq' );

		if ( ! sekcja ) {
			return;
		}

		/*
		 * Bez arkusza stylów nie przejmujemy sterowania. Skrypt zwija
		 * odpowiedzi, ustawiając im wysokość zero — gdyby zrobił to bez
		 * stylu, treść zniknęłaby ze strony całkowicie. Lepiej zostawić ją
		 * surową i czytelną.
		 */
		if ( '1' !== window.getComputedStyle( sekcja ).getPropertyValue( '--lst-faq-gotowy' ).trim() ) {
			return;
		}

		/*
		 * Biała kreska dookoła sekcji bierze się czasem nie z niej samej, tylko
		 * z obramowania, które motyw albo Divi nadaje modułowi, kolumnie lub
		 * wierszowi, w którym sekcja siedzi. Nadajemy klasę TYLKO opakowaniom
		 * tego modułu — reszta strony zostaje nietknięta.
		 */
		var rodzic = sekcja.parentNode;

		while ( rodzic && rodzic !== document.body ) {
			if ( rodzic.className && typeof rodzic.className === 'string' &&
				/et_pb_(module|column|row|section)|et_pb_code/.test( rodzic.className ) ) {
				rodzic.className += ' lst-faq-gniazdo';
			}

			rodzic = rodzic.parentNode;
		}

		/* Pełna szerokość okna BEZ paska przewijania — 100vw go wlicza i robi suwak. */
		function szerokosc() {
			sekcja.style.setProperty( '--lst-pelna', document.documentElement.clientWidth + 'px' );
		}

		szerokosc();
		window.addEventListener( 'resize', szerokosc, { passive: true } );

		/*
		 * Dopiero teraz mówimy stylom, że skrypt działa. Do tej chwili wszystkie
		 * odpowiedzi są otwarte — gdyby skrypt nie wystartował (błąd, wyłączony
		 * JavaScript), treść i tak jest do przeczytania.
		 */
		sekcja.className += ' ma-skrypt';

		var wiersze = sekcja.querySelectorAll( '.lst-faq-wiersz' );
		var i;

		for ( i = 0; i < wiersze.length; i++ ) {
			wiersze[ i ].querySelector( '.lst-faq-zwoj' ).style.height = '0px';
		}

		/*
		 * Rozwijanie i zwijanie.
		 *
		 * Pierwsza wersja miała błąd: po rozwinięciu zostawiała nasłuchiwanie na
		 * koniec przejścia, które ustawiało wysokość na "auto". Gdy ktoś kliknął
		 * ponownie, zanim przejście się skończyło, to STARE nasłuchiwanie odpalało
		 * się po zakończeniu ZWIJANIA i przywracało "auto" — wiersz zostawał
		 * widoczny, choć był już zamknięty, i nie dawało się go schować.
		 *
		 * Teraz każda animacja najpierw kasuje poprzednie nasłuchiwanie, startuje
		 * od wysokości ZMIERZONEJ w tej chwili (a nie od zera albo od docelowej),
		 * i radzi sobie z przypadkiem, w którym przejście w ogóle nie zachodzi.
		 */

		function przerwij( zwoj ) {
			if ( zwoj.lstKoniec ) {
				zwoj.removeEventListener( 'transitionend', zwoj.lstKoniec );
				zwoj.lstKoniec = null;
			}
		}

		function animuj( zwoj, otwieramy ) {
			przerwij( zwoj );

			var teraz = zwoj.getBoundingClientRect().height;

			zwoj.style.height = teraz + 'px';
			zwoj.offsetHeight;                      /* wymuszenie przeliczenia */

			var cel = otwieramy ? zwoj.scrollHeight : 0;
			var czas = parseFloat( window.getComputedStyle( zwoj ).transitionDuration ) || 0;

			/*
			 * Bez przejścia (ustawienie „ogranicz animacje") albo gdy nie ma czego
			 * animować, kończymy od razu. Inaczej czekalibyśmy na zdarzenie, które
			 * nigdy nie przyjdzie, i wysokość zostałaby zablokowana na sztywno.
			 */
			if ( 0 === czas || Math.abs( cel - teraz ) < 1 ) {
				zwoj.style.height = otwieramy ? 'auto' : '0px';
				return;
			}

			zwoj.style.height = cel + 'px';

			if ( ! otwieramy ) {
				return;   /* zostaje 0 — nie ma czego zdejmować */
			}

			/*
			 * Po rozwinięciu zdejmujemy stałą wysokość, żeby odpowiedź mogła się
			 * przelać na więcej wierszy przy zwężeniu okna. Zabezpieczenie: jeśli
			 * w międzyczasie wiersz zdążył się zamknąć, nie ruszamy wysokości.
			 */
			zwoj.lstKoniec = function ( e ) {
				if ( 'height' !== e.propertyName ) {
					return;
				}

				przerwij( zwoj );

				if ( zwoj.parentNode.className.indexOf( 'jest-otwarty' ) !== -1 ) {
					zwoj.style.height = 'auto';
				}
			};

			zwoj.addEventListener( 'transitionend', zwoj.lstKoniec );
		}

		sekcja.addEventListener( 'click', function ( e ) {
			var guzik = e.target.closest ? e.target.closest( '.lst-faq-pyt' ) : null;

			if ( ! guzik ) {
				return;
			}

			var wiersz = guzik.parentNode;
			var zwoj = wiersz.querySelector( '.lst-faq-zwoj' );
			var otwarty = wiersz.className.indexOf( 'jest-otwarty' ) !== -1;
			var j;

			/* Jeden otwarty naraz — inaczej sekcja rośnie bez końca. */
			for ( j = 0; j < wiersze.length; j++ ) {
				if ( wiersze[ j ] !== wiersz && wiersze[ j ].className.indexOf( 'jest-otwarty' ) !== -1 ) {
					wiersze[ j ].classList.remove( 'jest-otwarty' );
					wiersze[ j ].querySelector( '.lst-faq-pyt' ).setAttribute( 'aria-expanded', 'false' );
					animuj( wiersze[ j ].querySelector( '.lst-faq-zwoj' ), false );
				}
			}

			wiersz.classList.toggle( 'jest-otwarty', ! otwarty );
			guzik.setAttribute( 'aria-expanded', otwarty ? 'false' : 'true' );

			animuj( zwoj, ! otwarty );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
</script>
'''


WIERSZ = ( '\t\t<div class="lst-faq-wiersz">\n'
	'\t\t\t<button class="lst-faq-pyt" type="button" aria-expanded="false" aria-controls="lst-faq-{NR}">'
	'<span class="lst-faq-nr">{NR}</span>'
	'<span class="lst-faq-temat">{TEMAT}</span>'
	'<span class="lst-faq-tresc">{PYTANIE}</span></button>\n'
	'\t\t\t<div class="lst-faq-zwoj" id="lst-faq-{NR}" role="region"><p>{ODPOWIEDZ}</p></div>\n'
	'\t\t</div>' )


def podstaw( wzor, pola ):
	for klucz, wartosc in pola.items():
		wzor = wzor.replace( '{' + klucz + '}', wartosc )

	return wzor


def sprawdz( html, plik ):
	"""Rzeczy, których brak psuł już kiedyś moduł po cichu."""

	for musi in ( '.lst-faq br { display: none; }', 'lst-faq-gniazdo', 'ma-skrypt',
	              'aria-expanded', 'prefers-reduced-motion', 'clientWidth', 'przerwij(',
	              '--lst-faq-gotowy',
	              '.lst-faq .lst-faq-glowa span {' ):
		if musi not in html:
			raise SystemExit( plik + ': brakuje ' + musi )

	styl = re.search( r'<style>(.*?)</style>', html, re.S ).group( 1 )
	bez_uwag = re.sub( r'/\*.*?\*/', '', styl, flags=re.S )

	if bez_uwag.count( '{' ) != bez_uwag.count( '}' ):
		raise SystemExit( plik + ': klamry w <style> się nie zgadzają' )

	# reset musi stać PRZED regułami, które sam ustawia
	if styl.index( ':where(' ) > styl.index( '.lst-faq .lst-faq-rama' ):
		raise SystemExit( plik + ': reset :where() stoi po regułach — zabierze im krój i odstępy' )

	# Każdy znacznik w jednej linijce — sprawdzamy TYLKO samo znacznikowanie.
	# Komentarze HTML rozciągają się na wiele wierszy z założenia, a w CSS-ie
	# i w skrypcie "<" oraz ">" to porównania i selektory, nie znaczniki.
	markup = html[ : html.index( '<style>' ) ]
	markup = re.sub( r'<!--.*?-->', '', markup, flags=re.S )

	for nr, linia in enumerate( markup.split( '\n' ), 1 ):
		if linia.count( '<' ) != linia.count( '>' ):
			raise SystemExit( plik + ': znacznik rozbity na dwie linijki, wiersz ' + str( nr ) )


def zbuduj( t, plik ):
	wiersze = []

	for i, ( temat, pytanie, odpowiedz ) in enumerate( t[ 'pytania' ] ):
		wiersze.append( podstaw( WIERSZ, {
			'NR': str( i + 1 ), 'TEMAT': temat, 'PYTANIE': pytanie, 'ODPOWIEDZ': odpowiedz } ) )

	html = podstaw( SZABLON, {
		'N0': t[ 'naglowki' ][ 0 ], 'N1': t[ 'naglowki' ][ 1 ], 'N2': t[ 'naglowki' ][ 2 ],
		'WIERSZE': '\n'.join( wiersze ) } )

	sprawdz( html, plik )

	with open( plik, 'w', encoding='utf-8' ) as f:
		f.write( html )

	print( plik + ' — pytań: ' + str( len( wiersze ) ) )


zbuduj( EN, 'faq-en.html' )
zbuduj( PL, 'faq-pl.html' )


# ---------------------------------------------------------------------------
# Strony próbne budujemy tutaj, z tego samego świeżo wygenerowanego modułu.
# Trzymane osobno potrafiły zostać w tyle i testy sprawdzały wczorajszą wersję.
# ---------------------------------------------------------------------------

GLOWA_PROBY = '''<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>FAQ</title>
<style>
 html,body{margin:0;background:#141a19;color:#eaf3f1;font-family:"IBM Plex Sans",system-ui,sans-serif}
 .et_pb_section{padding:40px 0}
 .et_pb_row{width:90%;max-width:1800px;margin:0 auto}
 .wysoko{height:60vh}
</style>{WROGI}</head><body>
<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><p id="odnosnik">A paragraph in the ordinary Divi column.</p></div></div></div>
<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><div class="et_pb_module" id="gniazdo">
'''

STOPKA_PROBY = '''
</div></div></div></div>
<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><p id="odnosnik2">After.</p><div class="wysoko"></div></div></div></div>
</body></html>'''


def divi_bry( html ):
	"""Divi wstawia <br /> na każdym złamaniu linii POZA <style> i <script>."""
	kawalki = re.split( r'(<style>.*?</style>|<script>.*?</script>)', html, flags=re.S )
	wynik = []

	for i, kawalek in enumerate( kawalki ):
		wynik.append( kawalek if i % 2 else kawalek.replace( '\n', '<br />\n' ) )

	return ''.join( wynik )


def zbuduj_probe():
	with open( 'faq-en.html', encoding='utf-8' ) as f:
		modul = f.read()

	strony = (
		( 'proba.html',       modul,             '' ),
		( 'proba-br.html',    divi_bry( modul ), '' ),
		( 'proba-wrogi.html', modul,             '<link rel="stylesheet" href="motyw-wrogi.css">' ),
	)

	for plik, tresc, wrogi in strony:
		with open( plik, 'w', encoding='utf-8' ) as f:
			f.write( GLOWA_PROBY.replace( '{WROGI}', wrogi ) + tresc + STOPKA_PROBY )

		print( plik + ' — strona próbna odświeżona' )


zbuduj_probe()


# ---------------------------------------------------------------------------
# Wersja rozdzielona na trzy części.
#
# WordPress usuwa <style> i <script> z treści zapisanej przez użytkownika bez
# uprawnienia "unfiltered_html" (mechanizm wp_kses). Dzieje się tak nie zawsze
# — zależy od tego, kto i przez co zapisuje stronę — i dlatego moduł „czasem
# się rozwala": zostaje samo znacznikowanie, bez wyglądu i bez rozwijania.
#
# Lekarstwo jest takie samo jak przy tle: styl i skrypt idą tam, gdzie
# WordPress ich nie tyka, a w module Kod zostaje czyste znacznikowanie.
# ---------------------------------------------------------------------------

def rozdziel( plik_zrodlowy, plik_kod, plik_css, plik_skrypt ):
	with open( plik_zrodlowy, encoding='utf-8' ) as f:
		html = f.read()

	styl = re.search( r'<style>(.*?)</style>', html, re.S ).group( 1 ).strip()
	skrypt = re.search( r'<script>.*?</script>', html, re.S ).group( 0 )

	kod = re.sub( r'<style>.*?</style>', '', html, flags=re.S )
	kod = re.sub( r'<script>.*?</script>', '', kod, flags=re.S ).strip() + '\n'

	kod = kod.replace(
		'     UWAGA: każdy znacznik musi zostać w jednej linijce — Divi wstawia w',
		'     Styl i skrypt są OSOBNO — patrz dwa pozostałe pliki. Tak musi być:\n'
		'     WordPress usuwa <style> i <script> z treści modułu, gdy zapisuje ją\n'
		'     użytkownik bez uprawnienia „unfiltered_html". Wtedy zostaje samo\n'
		'     znacznikowanie i sekcja wygląda jak goły tekst.\n\n'
		'     UWAGA: każdy znacznik musi zostać w jednej linijce — Divi wstawia w' )

	naglowek_css = (
		'/* =========================================================================\n'
		'   FAQ W UKŁADZIE ARKUSZA — styl.\n\n'
		'   Wklej do: Divi → Opcje motywu → Ogólne → Własny CSS.\n'
		'   Znacznikowanie idzie do modułu Kod, skrypt do zakładki Integracja.\n'
		'   ========================================================================= */\n\n' )

	naglowek_skryptu = (
		'<!-- =========================================================================\n'
		'     FAQ W UKŁADZIE ARKUSZA — skrypt.\n\n'
		'     Wklej do: Divi → Opcje motywu → Integracja → „Dodaj kod do sekcji\n'
		'     <body>". Na stronach bez FAQ nic nie robi.\n'
		'     ========================================================================= -->\n' )

	for plik, tresc in ( ( plik_kod, kod ),
	                     ( plik_css, naglowek_css + styl + '\n' ),
	                     ( plik_skrypt, naglowek_skryptu + skrypt + '\n' ) ):
		with open( plik, 'w', encoding='utf-8' ) as f:
			f.write( tresc )

		print( plik + ' — ' + str( len( tresc.split( chr( 10 ) ) ) ) + ' linii' )


rozdziel( 'faq-en.html', '/tmp/nieuzywany.html', 'faq-css.css', 'faq-skrypt.html' )


# ---------------------------------------------------------------------------
# WERSJA BEZPIECZNA DLA KREATORA WIZUALNEGO
#
# Kreator Divi renderuje treść modułu Kod na żywo i potrafi ją odrzucić
# komunikatem „Ta treść nie mogła zostać wyświetlona". Wywala się na tym, co
# wykracza poza proste znacznikowanie. Dlatego ten wariant nie zawiera:
#
#   * komentarzy HTML  (<!-- ... -->)  — wielolinijkowe są najgorsze
#   * znaczników <style> i <script>    — i tak wycina je WordPress
#   * nawiasów kwadratowych            — to składnia skrótów WordPressa
#   * znacznika <section>              — zwykły <div> niczego nie zmienia
#
# Zostaje samo znacznikowanie, każdy znacznik w jednej linijce.
# ---------------------------------------------------------------------------

def wersja_bezpieczna( plik_zrodlowy, plik_docelowy ):
	with open( plik_zrodlowy, encoding='utf-8' ) as f:
		html = f.read()

	kod = re.sub( r'<style>.*?</style>', '', html, flags=re.S )
	kod = re.sub( r'<script>.*?</script>', '', kod, flags=re.S )
	kod = re.sub( r'<!--.*?-->', '', kod, flags=re.S )
	kod = re.sub( r'<link[^>]*>', '', kod )

	kod = kod.replace( '<section class="lst-faq" id="lst-faq">', '<div class="lst-faq" id="lst-faq">' )
	kod = kod.replace( '</section>', '</div>' )

	kod = '\n'.join( l.rstrip() for l in kod.split( '\n' ) if l.strip() ) + '\n'

	sprawdz_bezpieczna( kod, plik_docelowy )

	with open( plik_docelowy, 'w', encoding='utf-8' ) as f:
		f.write( kod )

	print( plik_docelowy + ' — ' + str( len( kod.split( chr( 10 ) ) ) - 1 ) + ' linii, ' + str( len( kod ) ) + ' znaków' )


def sprawdz_bezpieczna( kod, plik ):
	"""Wszystko, na czym Kreator Wizualny albo WordPress potrafi się wyłożyć."""

	zakazane = (
		( '<!--',     'komentarz HTML' ),
		( '-->',      'koniec komentarza HTML' ),
		( '<style',   'znacznik <style>' ),
		( '<script',  'znacznik <script>' ),
		( '<link',    'znacznik <link>' ),
		( '<section', 'znacznik <section>' ),
		( '[',        'nawias kwadratowy (składnia skrótów WordPressa)' ),
		( ']',        'nawias kwadratowy (składnia skrótów WordPressa)' ),
	)

	# Filtry bezpieczeństwa (Wordfence i spółka) potrafią odrzucić ZAPIS, gdy
	# zobaczą w treści słowo "script" — nawet zapisane encjami.
	if 'script' in kod.lower():
		raise SystemExit( plik + ': w treści jest słowo „script" — filtr bezpieczeństwa może odrzucić zapis' )

	for co, opis in zakazane:
		if co in kod:
			raise SystemExit( plik + ': znaleziono ' + opis )

	for nr, linia in enumerate( kod.split( '\n' ), 1 ):
		if linia.count( '<' ) != linia.count( '>' ):
			raise SystemExit( plik + ': znacznik rozbity na dwie linijki, wiersz ' + str( nr ) )

	# znaczniki muszą się domykać
	otwarte = re.findall( r'<(\w+)(?:\s[^>]*)?>', kod )
	zamkniete = re.findall( r'</(\w+)>', kod )
	puste = { 'br', 'img', 'input', 'hr', 'meta' }

	for znacznik in set( otwarte ):
		if znacznik in puste:
			continue

		if otwarte.count( znacznik ) != zamkniete.count( znacznik ):
			raise SystemExit( plik + ': <' + znacznik + '> otwarty ' + str( otwarte.count( znacznik ) ) +
				' razy, zamknięty ' + str( zamkniete.count( znacznik ) ) )

	if 'id="lst-faq"' not in kod:
		raise SystemExit( plik + ': brak id="lst-faq" — skrypt nie znajdzie sekcji' )


wersja_bezpieczna( 'faq-en.html', 'faq-en-kod.html' )
wersja_bezpieczna( 'faq-pl.html', 'faq-pl-kod.html' )


# ---------------------------------------------------------------------------
# JEDEN PLIK DO ZAKŁADKI INTEGRACJA — styl razem ze skryptem.
#
# Pole „Własny CSS" w Divi ma wbudowany sprawdzacz składni sprzed lat: nie zna
# zmiennych CSS (--nazwa) i podkreśla każdą taką linijkę jako błąd. Reguły są
# poprawne i przeglądarka je rozumie, ale pole zasypuje użytkownika czerwonymi
# znacznikami i nie wiadomo, co jest prawdziwym problemem.
#
# Pole „Dodaj kod do sekcji <body>" przyjmuje zwykły kod HTML i nie sprawdza
# niczego. Wkładamy więc styl tam, razem ze skryptem. Kod trafia tuż za
# otwarcie <body>, czyli PRZED sekcją FAQ — więc nic nie mignie bez stylu.
# ---------------------------------------------------------------------------

def zbuduj_integracje( plik_zrodlowy, plik_docelowy ):
	with open( plik_zrodlowy, encoding='utf-8' ) as f:
		html = f.read()

	styl = re.search( r'<style>.*?</style>', html, re.S ).group( 0 )
	skrypt = re.search( r'<script>.*?</script>', html, re.S ).group( 0 )
	czcionki = re.search( r'<link[^>]*fonts\.googleapis[^>]*>', html ).group( 0 )

	naglowek = (
		'<!-- =========================================================================\n'
		'     FAQ W UKŁADZIE ARKUSZA — styl i obsługa.\n\n'
		'     Wklej CAŁOŚĆ do: Divi → Opcje motywu → Integracja → „Dodaj kod do\n'
		'     sekcji <body>". Nic z tego nie idzie do pola Własny CSS — tamtejszy\n'
		'     sprawdzacz składni nie zna zmiennych CSS i zgłasza fałszywe błędy.\n\n'
		'     Na stronach bez FAQ ten kod nic nie robi.\n'
		'     ========================================================================= -->\n' )

	tresc = naglowek + czcionki + '\n\n' + styl + '\n\n' + skrypt + '\n'

	if '--lst-faq-gotowy' not in tresc:
		raise SystemExit( plik_docelowy + ': brak znacznika --lst-faq-gotowy' )

	if tresc.count( '<style>' ) != 1 or tresc.count( '<script>' ) != 1:
		raise SystemExit( plik_docelowy + ': style/skrypt nie zgadzają się' )

	with open( plik_docelowy, 'w', encoding='utf-8' ) as f:
		f.write( tresc )

	print( plik_docelowy + ' — ' + str( len( tresc.split( chr( 10 ) ) ) ) + ' linii (styl + skrypt razem)' )


zbuduj_integracje( 'faq-en.html', 'faq-integracja.html' )


# ---------------------------------------------------------------------------
# JEDEN PLIK — tak jak tabela porównawcza i cennik.
#
# Tamte moduły mają <style> i <script> w środku i działają, więc WordPress
# ich nie wycina. Zostaje tylko to, co naprawdę mogło wyłożyć Kreator
# Wizualny: wielolinijkowe komentarze HTML. Usuwamy je i tyle.
# ---------------------------------------------------------------------------

def jeden_plik( plik_zrodlowy, plik_docelowy ):
	with open( plik_zrodlowy, encoding='utf-8' ) as f:
		html = f.read()

	# Komentarze HTML wycinamy TYLKO ze znacznikowania. W <style> i <script>
	# komentarze są innego rodzaju (/* */) i mają zostać — to one tłumaczą kod.
	czesci = re.split( r'(<style>.*?</style>|<script>.*?</script>)', html, flags=re.S )
	czesci = [ ( c if i % 2 else re.sub( r'<!--.*?-->', '', c, flags=re.S ) )
	           for i, c in enumerate( czesci ) ]
	kod = ''.join( czesci )

	kod = kod.replace( '<section class="lst-faq" id="lst-faq">', '<div class="lst-faq" id="lst-faq">' )
	kod = kod.replace( '</section>', '</div>' )
	kod = re.sub( r'\n{3,}', '\n\n', kod ).strip() + '\n'

	znacznikowanie = kod[ : kod.index( '<style>' ) ]

	for co, opis in ( ( '<!--', 'komentarz HTML' ), ( '<section', 'znacznik <section>' ),
	                  ( '[', 'nawias kwadratowy' ), ( ']', 'nawias kwadratowy' ) ):
		if co in znacznikowanie:
			raise SystemExit( plik_docelowy + ': w znacznikowaniu jest ' + opis )

	if 'script' in znacznikowanie.lower():
		raise SystemExit( plik_docelowy + ': w treści jest słowo „script"' )

	for nr, linia in enumerate( znacznikowanie.split( '\n' ), 1 ):
		if linia.count( '<' ) != linia.count( '>' ):
			raise SystemExit( plik_docelowy + ': znacznik rozbity na dwie linijki, wiersz ' + str( nr ) )

	for musi in ( '--lst-faq-gotowy', 'ma-skrypt', 'przerwij(', 'aria-expanded' ):
		if musi not in kod:
			raise SystemExit( plik_docelowy + ': brakuje ' + musi )

	with open( plik_docelowy, 'w', encoding='utf-8' ) as f:
		f.write( kod )

	print( plik_docelowy + ' — ' + str( len( kod.split( chr( 10 ) ) ) ) + ' linii, jeden plik' )


jeden_plik( 'faq-en.html', 'FAQ-en.html' )
jeden_plik( 'faq-pl.html', 'FAQ-pl.html' )
