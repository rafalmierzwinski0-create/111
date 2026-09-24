# -*- coding: utf-8 -*-
"""
Podstrona „jak to działa" — wyjaśnienie z prawdziwymi zrzutami z wtyczki.

Każdy blok to jedno zdanie o tym, co wtyczka robi, i zrzut, na którym to widać.
Zrzuty siedzą w oknie z belką, tak samo jak podglądy w sekcji „dwie minuty",
więc strona trzyma się jednego języka wizualnego.

Adresy obrazków zaczynają się od ADRES. Wgrywasz pliki do Multimediów, kopiujesz
adres folderu i podmieniasz ADRES jeden raz — w całym pliku.

UWAGA: każdy znacznik w jednej linijce. Divi wstawia <br /> w złamaniach wiersza.
"""

import re

L, P = '&#91;', '&#93;'

# ( plik, szerokosc, wysokosc )
OBRAZY = {
	'podglad':   ( '01-podglad-przed-zapisem.png', 2636, 1634 ),
	'telefon':   ( '14-telefon.png',               1260, 2526 ),
	'lista':     ( '11-lista-zrodel.png',          2636,  914 ),
	'tabela':    ( '12-tabela-na-stronie.png',     2336, 1996 ),
	'szukanie':  ( '13-szukanie.png',              2336, 1888 ),
	'wyglad':    ( '05-wyglad.png',                2636, 2356 ),
	'kolumny':   ( '07-kolumny.png',               2636,  636 ),
	'awaria':    ( '15-awaria-panel.png',          2636, 1126 ),
	'pro':       ( '17-pro-na-stronie.png',        2336, 2186 ),
	'reguly':    ( '09-pro-reguly.png',            1118, 1072 ),
}

EN = {
	'wstep': 'Every picture below is the plugin itself, on a real WordPress site. Nothing is drawn '
	         'or mocked up.',
	'bloki': [
		( 'podglad', 'You see the table before you save it',
		  'Paste the link to your sheet and choose <em>Load preview</em>. The plugin reads the sheet '
		  'and shows exactly what it found &mdash; how many rows, how many columns, what the headings '
		  'are. If the sheet is wrong, you find out now, not after it is on your page.',
		  'The screen also tells you what a narrow column will do to the table, before you commit to it.' ),
		( 'lista', 'Every sheet in one list',
		  'Each sheet gets a card: whether it is up to date, how many rows it has, how often it is '
		  'checked, and which page it is used on. The shortcode sits there with a copy button, so '
		  'putting the table anywhere takes one click.',
		  '' ),
		( 'tabela', 'The table on your page',
		  'Search, sortable headings and a line saying how fresh the data is. It is a real table in '
		  'the page code, not an embedded frame, so Google sees it and so do people using a screen '
		  'reader.',
		  '' ),
		( 'telefon', 'On a phone it folds into cards',
		  'A wide table does not fit a phone, so each row becomes a card and every value keeps the '
		  'name of its column. What decides is the width of the column the table sits in, not the '
		  'width of the screen &mdash; so a table in a narrow sidebar folds on a desktop too.',
		  '' ),
		( 'szukanie', 'Search says what it matched',
		  'Type and the table narrows down. Every hit is highlighted where it was found, and the '
		  'counter says how much is left, so nobody has to guess why a row is showing.',
		  '' ),
		( 'wyglad', 'Change the look without writing CSS',
		  'Presets for the frame and the rows, then colours you can set one by one: headings, lines, '
		  'stripes, the colour that answers a hover. Underneath there is a field for your own CSS, '
		  'for the day you want something the settings do not cover.',
		  '' ),
		( 'kolumny', 'Rename, hide, align and pin columns',
		  'Your sheet is for you; the table is for your visitors. Rename a heading without touching '
		  'the sheet, hide a column you keep for yourself, push numbers to the right, pin the first '
		  'column so it stays put while the rest scrolls.',
		  '' ),
		( 'awaria', 'When Google has a bad day, your page does not',
		  'The table on your page is served from a copy in your own database, so a visitor never '
		  'waits for Google and never sees an error. The failed check shows up here, in your '
		  'dashboard, where it belongs &mdash; with the history of the last checks.',
		  '' ),
	],
	'pro_tytul': 'What Pro adds',
	'pro_wstep': 'Same table, three more things it can do.',
	'pro_bloki': [
		( 'pro', 'Colours, filters and downloads',
		  'Rows that break a rule are coloured &mdash; here everything out of stock. Above the table, '
		  'a filter your visitor can use. Under it, buttons for Excel, CSV and print, and a download '
		  'contains exactly what is on screen: the rows left after filtering, and the columns you kept.',
		  '' ),
		( 'reguly', 'A rule reads like a sentence',
		  'When <em>Availability</em> is <em>Out of stock</em>, paint <em>the whole row</em>. No '
		  'formulas and no code. Colours are worked out on the server, so they are already in the '
		  'page a visitor receives.',
		  '' ),
	],
	'kod': L + 'sheet_table id=&quot;1&quot;' + P,
	'kod_opis': 'A block, an Elementor widget or this shortcode &mdash; the same table either way.',
}

PL = {
	'wstep': 'Każdy obrazek poniżej to sama wtyczka, na prawdziwej stronie WordPress. Nic tu nie jest '
	         'narysowane ani udawane.',
	'bloki': [
		( 'podglad', 'Widzisz tabelę, zanim ją zapiszesz',
		  'Wklejasz link do arkusza i wybierasz <em>Wczytaj podgląd</em>. Wtyczka czyta arkusz i '
		  'pokazuje dokładnie to, co znalazła &mdash; ile wierszy, ile kolumn, jakie nagłówki. Jeśli '
		  'coś jest nie tak, dowiadujesz się teraz, a nie wtedy, gdy to już wisi na stronie.',
		  'Ten sam ekran mówi, co z tabelą zrobi wąska kolumna &mdash; zanim się na nią zgodzisz.' ),
		( 'lista', 'Wszystkie arkusze na jednej liście',
		  'Każdy arkusz ma swoją kartę: czy jest aktualny, ile ma wierszy, jak często jest sprawdzany '
		  'i na której stronie go użyto. Obok leży shortcode z przyciskiem kopiowania, więc wstawienie '
		  'tabeli gdziekolwiek to jedno kliknięcie.',
		  '' ),
		( 'tabela', 'Tabela na Twojej stronie',
		  'Wyszukiwarka, klikalne nagłówki i linijka mówiąca, jak świeże są dane. To prawdziwa tabela '
		  'w kodzie strony, a nie wklejona ramka &mdash; więc widzi ją Google i widzą ją czytniki '
		  'ekranu.',
		  '' ),
		( 'telefon', 'Na telefonie składa się w karty',
		  'Szeroka tabela nie mieści się na telefonie, więc każdy wiersz staje się kartą, a każda '
		  'wartość zachowuje nazwę swojej kolumny. Decyduje szerokość kolumny, w której tabela siedzi, '
		  'a nie szerokość ekranu &mdash; więc tabela w wąskim pasku bocznym złoży się też na '
		  'monitorze.',
		  '' ),
		( 'szukanie', 'Wyszukiwarka mówi, co znalazła',
		  'Piszesz, a tabela się zawęża. Każde trafienie jest podświetlone w miejscu, w którym padło, '
		  'a licznik mówi, ile zostało &mdash; nikt nie musi zgadywać, czemu dany wiersz się pokazuje.',
		  '' ),
		( 'wyglad', 'Zmieniasz wygląd bez pisania CSS-a',
		  'Gotowe warianty ramki i wierszy, a pod nimi kolory do ustawienia po kolei: nagłówki, linie, '
		  'paski, kolor odpowiedzi na najechanie myszą. Niżej jest pole na własny CSS &mdash; na ten '
		  'dzień, gdy zechcesz coś, czego ustawienia nie obejmują.',
		  '' ),
		( 'kolumny', 'Zmieniasz nazwy, chowasz, wyrównujesz i przypinasz kolumny',
		  'Arkusz jest dla Ciebie, tabela dla odwiedzających. Zmień nagłówek, nie ruszając arkusza. '
		  'Schowaj kolumnę, którą trzymasz dla siebie. Zepchnij liczby na prawo. Przypnij pierwszą '
		  'kolumnę, żeby stała w miejscu, gdy reszta się przewija.',
		  '' ),
		( 'awaria', 'Gdy Google ma gorszy dzień, Twoja strona nie ma',
		  'Tabela na stronie jest podawana z kopii w Twojej własnej bazie, więc odwiedzający nigdy nie '
		  'czeka na Google i nigdy nie widzi błędu. Nieudane sprawdzenie pokazuje się tutaj, w panelu, '
		  'czyli tam, gdzie jego miejsce &mdash; razem z historią ostatnich prób.',
		  '' ),
	],
	'pro_tytul': 'Co dokłada Pro',
	'pro_wstep': 'Ta sama tabela, trzy rzeczy więcej.',
	'pro_bloki': [
		( 'pro', 'Kolory, filtry i pobieranie',
		  'Wiersze, które łamią regułę, są pokolorowane &mdash; tutaj wszystko, czego brakuje. Nad '
		  'tabelą filtr, z którego może skorzystać odwiedzający. Pod nią przyciski do Excela, CSV i '
		  'druku, a pobrany plik zawiera dokładnie to, co widać na ekranie: wiersze, które zostały po '
		  'filtrowaniu, i kolumny, które zostawiłeś.',
		  '' ),
		( 'reguly', 'Reguła czyta się jak zdanie',
		  'Kiedy <em>Dostępność</em> to <em>Brak</em>, pomaluj <em>cały wiersz</em>. Bez formuł i bez '
		  'kodu. Kolory są wyliczane na serwerze, więc są już w stronie, którą dostaje odwiedzający.',
		  '' ),
	],
	'kod': L + 'sheet_table id=&quot;1&quot;' + P,
	'kod_opis': 'Blok, widżet Elementora albo ten shortcode &mdash; ta sama tabela w każdym przypadku.',
}


SZABLON = r'''<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">

<div class="lst-jak">
	<div class="lst-jak-rama">
		<p class="lst-jak-wstep">{WSTEP}</p>
		<div class="lst-jak-bloki">
{BLOKI}
		</div>
		<p class="lst-jak-kod"><span class="lst-jak-mono">{KOD}</span><span class="lst-jak-kod-opis">{KOD_OPIS}</span></p>
		<div class="lst-jak-pro-glowa">
			<p class="lst-jak-pro-etykieta">Pro</p>
			<p class="lst-jak-pro-tytul">{PRO_TYTUL}</p>
			<p class="lst-jak-pro-wstep">{PRO_WSTEP}</p>
		</div>
		<div class="lst-jak-bloki">
{PRO_BLOKI}
		</div>
	</div>
</div>

<style>
.lst-jak {
	--lst-mieta: 95, 227, 207;
	--lst-panel: #1b2221;
	--lst-panel-dol: #161d1c;
	--lst-linia: rgba( 138, 168, 163, .22 );
	--lst-kreska: rgba( 138, 168, 163, .38 );
	--lst-tekst: #eaf3f1;
	--lst-tekst-2: #b3c6c3;
	--lst-tekst-3: #8fa5a2;
	--lst-plyta: rgba( 13, 18, 17, .62 );
	--lst-plyta-linia: rgba( 138, 168, 163, .1 );
	--lst-ekran: #0a1110;
	--lst-ekran-gora: #131d1b;
	--lst-ekran-linia: rgba( 95, 227, 207, .2 );
	--lst-mono: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;

	font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif;
	color: var( --lst-tekst );

	--lst-szerokosc: 90%;
	--lst-max: 1800px;
	--lst-pelna: 100vw;

	width: var( --lst-pelna );
	margin: 0 calc( 50% - var( --lst-pelna ) / 2 );
	overflow-x: clip;
	padding: clamp( 1rem, 2vw, 1.6rem ) 0 clamp( 2rem, 4vw, 3.4rem );
}

.lst-jak.lst-jak { border: 0 !important; outline: 0 !important; background: none !important; }
.lst-jak * { box-sizing: border-box; }
.lst-jak br { display: none; }

/* Reset musi stac PRZED reszta regul — ma wage zero. */
.lst-jak :where( div, p, span, em, a, img ) {
	margin: 0;
	padding: 0;
	background: none;
	border: 0;
	border-radius: 0;
	box-shadow: none;
	text-align: left;
	text-transform: none;
	letter-spacing: normal;
	font: inherit;
	color: inherit;
	width: auto;
	max-width: none;
	min-width: 0;
}

.lst-jak .lst-jak-rama {
	width: var( --lst-szerokosc );
	max-width: var( --lst-max );
	margin-inline: auto;
}

.lst-jak .lst-jak-mono { font-family: var( --lst-mono ); }

.lst-jak .lst-jak-wstep {
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.55;
	color: var( --lst-tekst-2 );
	max-width: 44rem;
	margin-bottom: clamp( 1.6rem, 3vw, 2.4rem );
}

/* ---------- blok: tekst i zrzut ---------- */

.lst-jak .lst-jak-blok {
	display: grid;
	grid-template-columns: minmax( 0, 1fr ) minmax( 0, 1.15fr );
	gap: clamp( 1.2rem, 3vw, 3rem );
	align-items: center;
	padding-block: clamp( 1.6rem, 3vw, 2.6rem );
	border-top: 1px solid var( --lst-kreska );
}

.lst-jak .lst-jak-blok:first-child { border-top: 0; padding-top: 0; }

/* Co drugi blok z pary „tekst obok zrzutu" odwrócony — zrzut po lewej. */
.lst-jak .lst-jak-blok.jest-odwrocony .lst-jak-okno { order: -1; }

/*
 * Szeroki i niski zrzut (cały ekran panelu) na połowie szerokości jest
 * nieczytelny — litery schodzą poniżej pięciu pikseli. Taki dostaje cały rząd:
 * tekst nad nim, obrazek pod spodem.
 */
.lst-jak .lst-jak-blok.jest-szeroki { grid-template-columns: 1fr; }
.lst-jak .lst-jak-blok.jest-szeroki .lst-jak-tresc { max-width: 52rem; }

.lst-jak .lst-jak-tresc {
	padding: 1.15rem 1.3rem 1.25rem;
	background-color: var( --lst-plyta );
	border: 1px solid var( --lst-plyta-linia );
	border-radius: 14px;
}

.lst-jak .lst-jak-numer {
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	letter-spacing: .1em;
	color: var( --lst-tekst-3 );
	margin-bottom: .3rem;
}

.lst-jak .lst-jak-tytul {
	font-size: 1.25rem;   /* 20 px */
	font-weight: 600;
	line-height: 1.25;
	margin-bottom: .5rem;
	max-width: 34rem;
}

.lst-jak .lst-jak-opis {
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.55;
	color: var( --lst-tekst-2 );
	max-width: 38rem;
}

.lst-jak .lst-jak-opis em { font-style: normal; color: rgb( var( --lst-mieta ) ); }

.lst-jak .lst-jak-dopisek {
	display: block;
	margin-top: .7rem;
	padding-top: .7rem;
	border-top: 1px solid var( --lst-plyta-linia );
	font-size: .875rem;   /* 14 px */
	font-family: var( --lst-mono );
	line-height: 1.5;
	color: var( --lst-tekst-3 );
	max-width: 38rem;
}

/* ---------- okno ze zrzutem ---------- */

.lst-jak .lst-jak-okno {
	border: 1px solid var( --lst-ekran-linia );
	border-radius: 12px;
	overflow: hidden;
	background-color: var( --lst-ekran );
	box-shadow: 0 22px 46px -30px rgba( 0, 0, 0, .95 ), 0 0 34px -18px rgba( var( --lst-mieta ), .3 );
}

.lst-jak .lst-jak-belka {
	display: flex;
	align-items: center;
	gap: .45rem;
	padding: .6rem .85rem;
	background-color: var( --lst-ekran-gora );
	border-bottom: 1px solid rgba( 95, 227, 207, .14 );
}

.lst-jak .lst-jak-oczko {
	display: block;
	width: 9px;
	height: 9px;
	border-radius: 999px;
	background-color: var( --lst-tekst-3 );
	opacity: .38;
	flex: none;
}

.lst-jak .lst-jak-nazwa-okna {
	margin-left: .5rem;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	color: var( --lst-tekst-3 );
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.lst-jak .lst-jak-okno img {
	display: block;
	width: 100%;
	height: auto;
}

/* Wysoki zrzut z telefonu przycinamy od dołu — inaczej sam ciągnie cały rząd. */
.lst-jak .lst-jak-okno.jest-wysoki { max-height: 620px; }
.lst-jak .lst-jak-okno.jest-wysoki img { object-fit: cover; object-position: top; max-height: 580px; }

/* ---------- shortcode między blokami ---------- */

.lst-jak .lst-jak-kod {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: .8rem;
	margin-top: clamp( 1.6rem, 3vw, 2.4rem );
	font-size: .875rem;   /* 14 px */
	color: var( --lst-tekst-3 );
}

.lst-jak .lst-jak-kod .lst-jak-mono {
	padding: .4rem .7rem;
	background-color: var( --lst-plyta );
	border: 1px solid var( --lst-linia );
	border-radius: 8px;
	color: var( --lst-tekst-2 );
}

/* ---------- głowa części Pro ---------- */

.lst-jak .lst-jak-pro-glowa {
	margin-top: clamp( 2.6rem, 5vw, 4rem );
	padding-top: clamp( 1.6rem, 3vw, 2.4rem );
	border-top: 1px solid var( --lst-kreska );
}

.lst-jak .lst-jak-pro-etykieta {
	display: inline-block;
	padding: .15rem .5rem;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	color: #06100f;
	background-color: rgb( var( --lst-mieta ) );
	border-radius: 5px;
	margin-bottom: .6rem;
}

.lst-jak .lst-jak-pro-tytul { font-size: 1.25rem;   /* 20 px */ font-weight: 600; margin-bottom: .35rem; }
.lst-jak .lst-jak-pro-wstep { font-size: 1.125rem;   /* 18 px */ color: var( --lst-tekst-2 ); margin-bottom: .6rem; }

/* ---------- utwardzenie na wrogie motywy ---------- */

.lst-jak .lst-jak-wstep,
.lst-jak .lst-jak-numer,
.lst-jak .lst-jak-tytul,
.lst-jak .lst-jak-opis,
.lst-jak .lst-jak-nazwa-okna,
.lst-jak .lst-jak-kod,
.lst-jak .lst-jak-pro-tytul,
.lst-jak .lst-jak-pro-wstep {
	margin-inline: 0 !important;
	margin-top: 0 !important;
	background: none !important;
	border: 0 !important;
	text-align: left !important;
	text-transform: none !important;
	letter-spacing: normal !important;
}

.lst-jak .lst-jak-numer,
.lst-jak .lst-jak-nazwa-okna,
.lst-jak .lst-jak-dopisek,
.lst-jak .lst-jak-mono { font-family: var( --lst-mono ) !important; }

.lst-jak .lst-jak-tytul,
.lst-jak .lst-jak-opis,
.lst-jak .lst-jak-wstep { font-family: inherit !important; }

.lst-jak .lst-jak-opis,
.lst-jak .lst-jak-wstep,
.lst-jak .lst-jak-pro-wstep { color: var( --lst-tekst-2 ) !important; }

.lst-jak .lst-jak-tresc { background-color: var( --lst-plyta ) !important; border: 1px solid var( --lst-plyta-linia ) !important; }
.lst-jak .lst-jak-okno img { border: 0 !important; border-radius: 0 !important; box-shadow: none !important; max-width: 100% !important; }
.lst-jak .lst-jak-oczko { border: 0 !important; }
.lst-jak .lst-jak-pro-etykieta { background-color: rgb( var( --lst-mieta ) ) !important; color: #06100f !important; }

.et_pb_module:has( .lst-jak ),
.et_pb_column:has( .lst-jak ),
.et_pb_row:has( .lst-jak ) { border: 0 !important; outline: 0 !important; }

@media ( max-width: 900px ) {
	.lst-jak .lst-jak-blok { grid-template-columns: 1fr; gap: 1.1rem; }
	.lst-jak .lst-jak-blok:nth-child( even ) .lst-jak-okno { order: 0; }
	.lst-jak .lst-jak-okno.jest-wysoki { max-height: none; }
	.lst-jak .lst-jak-okno.jest-wysoki img { max-height: none; }
}
</style>

'''


def okno( klucz, nazwa_okna, wysoki=False ):
	plik, szer, wys = OBRAZY[ klucz ]
	klasa = 'lst-jak-okno jest-wysoki' if wysoki else 'lst-jak-okno'

	return ( '<div class="' + klasa + '">'
		'<div class="lst-jak-belka">'
		'<span class="lst-jak-oczko"></span><span class="lst-jak-oczko"></span>'
		'<span class="lst-jak-oczko"></span>'
		'<span class="lst-jak-nazwa-okna">' + nazwa_okna + '</span></div>'
		'<img src="ADRES/' + plik + '" alt="' + nazwa_okna + '" loading="lazy" decoding="async" '
		'width="' + str( szer ) + '" height="' + str( wys ) + '"></div>' )


NAZWY_OKIEN = {
	'podglad':  'WordPress &rsaquo; Live Sheets Table',
	'lista':    'WordPress &rsaquo; Sheet sources',
	'tabela':   'yoursite.com/price-list',
	'telefon':  'yoursite.com &mdash; phone',
	'szukanie': 'yoursite.com/price-list',
	'wyglad':   'WordPress &rsaquo; Appearance',
	'kolumny':  'WordPress &rsaquo; Columns and rows',
	'awaria':   'WordPress &rsaquo; Sheet sources',
	'pro':      'yoursite.com/price-list',
	'reguly':   'WordPress &rsaquo; Colour rules',
}


def bloki( lista, od=1 ):
	kawalki = []
	odwrotnie = False

	for nr, ( klucz, tytul, opis, dopisek ) in enumerate( lista, od ):
		_, szer, wys = OBRAZY[ klucz ]
		szeroki = ( szer / wys ) >= 1.6

		if szeroki:
			klasa = 'lst-jak-blok jest-szeroki'
		else:
			klasa = 'lst-jak-blok jest-odwrocony' if odwrotnie else 'lst-jak-blok'
			odwrotnie = not odwrotnie

		dodatek = ( '<span class="lst-jak-dopisek">' + dopisek + '</span>' ) if dopisek else ''
		kawalki.append(
			'\t\t\t<div class="' + klasa + '"><div class="lst-jak-tresc">'
			'<p class="lst-jak-numer">' + ( '%02d' % nr ) + '</p>'
			'<p class="lst-jak-tytul">' + tytul + '</p>'
			'<p class="lst-jak-opis">' + opis + dodatek + '</p></div>'
			+ okno( klucz, NAZWY_OKIEN[ klucz ], 'telefon' == klucz ) + '</div>' )

	return '\n'.join( kawalki )


def zbuduj( t, plik ):
	html = ( SZABLON
		.replace( '{WSTEP}', t[ 'wstep' ] )
		.replace( '{BLOKI}', bloki( t[ 'bloki' ] ) )
		.replace( '{KOD_OPIS}', t[ 'kod_opis' ] )
		.replace( '{KOD}', t[ 'kod' ] )
		.replace( '{PRO_TYTUL}', t[ 'pro_tytul' ] )
		.replace( '{PRO_WSTEP}', t[ 'pro_wstep' ] )
		.replace( '{PRO_BLOKI}', bloki( t[ 'pro_bloki' ], len( t[ 'bloki' ] ) + 1 ) ) )

	sprawdz( html, plik )

	with open( plik, 'w', encoding='utf-8' ) as f:
		f.write( html )

	print( plik + ' — bloków: ' + str( len( t[ 'bloki' ] ) + len( t[ 'pro_bloki' ] ) ) +
		', linii: ' + str( len( html.split( chr( 10 ) ) ) ) )
	return html


def sprawdz( html, plik ):
	znacznikowanie = html[ : html.index( '<style>' ) ]

	for co, opis in ( ( '<!--', 'komentarz HTML' ), ( '<section', 'znacznik <section>' ),
	                  ( '[', 'nawias kwadratowy' ), ( ']', 'nawias kwadratowy' ) ):
		if co in znacznikowanie:
			raise SystemExit( plik + ': w znacznikowaniu jest ' + opis )

	if 'script' in znacznikowanie.lower():
		raise SystemExit( plik + ': w treści jest słowo „script"' )

	for nr, linia in enumerate( znacznikowanie.split( '\n' ), 1 ):
		if linia.count( '<' ) != linia.count( '>' ):
			raise SystemExit( plik + ': znacznik rozbity na dwie linijki, wiersz ' + str( nr ) )

	styl = re.search( r'<style>(.*?)</style>', html, re.S ).group( 1 )
	bez_uwag = re.sub( r'/\*.*?\*/', '', styl, flags=re.S )

	if bez_uwag.count( '{' ) != bez_uwag.count( '}' ):
		raise SystemExit( plik + ': klamry w <style> się nie zgadzają' )

	if styl.index( ':where(' ) > styl.index( '.lst-jak .lst-jak-rama' ):
		raise SystemExit( plik + ': reset :where() stoi po regułach' )

	# każdy obrazek musi mieć adres do podmiany, alt i wymiary
	for obraz in re.findall( r'<img [^>]*>', znacznikowanie ):
		for musi in ( 'src="ADRES/', 'alt="', 'width="', 'height="', 'loading="lazy"' ):
			if musi not in obraz:
				raise SystemExit( plik + ': obrazkowi brakuje ' + musi )

	otwarte = re.findall( r'<(\w+)(?:\s[^>]*)?>', znacznikowanie )
	zamkniete = re.findall( r'</(\w+)>', znacznikowanie )

	for znacznik in set( otwarte ):
		if znacznik in ( 'br', 'link', 'img' ):
			continue

		if otwarte.count( znacznik ) != zamkniete.count( znacznik ):
			raise SystemExit( plik + ': <' + znacznik + '> otwarty ' + str( otwarte.count( znacznik ) ) +
				', zamknięty ' + str( zamkniete.count( znacznik ) ) )


def podglad( html ):
	# w podglądzie ADRES wskazuje na lokalny katalog ze zrzutami
	strona = ( '<!doctype html>\n<html lang="pl">\n<head>\n<meta charset="utf-8">\n'
		'<meta name="viewport" content="width=device-width,initial-scale=1">\n'
		'<title>Jak działa wtyczka</title>\n'
		'<style>\nhtml, body { margin: 0; background: #232a29; color: #eaf3f1;\n'
		'\tfont-family: system-ui, sans-serif;\n'
		'\tbackground-image: linear-gradient( to right, rgba( 255, 255, 255, .055 ) 1px, transparent 1px ),\n'
		'\t\tlinear-gradient( to bottom, rgba( 255, 255, 255, .055 ) 1px, transparent 1px );\n'
		'\tbackground-size: 88px 44px; }\n'
		'.et_pb_section { padding: 40px 0; }\n'
		'.et_pb_row { width: 90%; max-width: 1800px; margin: 0 auto; }\n'
		'</style>\n</head>\n<body>\n'
		'<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column">'
		'<div class="et_pb_module">\n' + html.replace( 'ADRES/', 'zrzuty/' ) + '\n</div></div></div></div>\n'
		'</body>\n</html>\n' )

	with open( 'proba-jak.html', 'w', encoding='utf-8' ) as f:
		f.write( strona )

	print( 'proba-jak.html' )


en = zbuduj( EN, 'JAK-DZIALA-en.html' )
pl = zbuduj( PL, 'JAK-DZIALA-pl.html' )
podglad( en )
