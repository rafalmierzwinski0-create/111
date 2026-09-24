# -*- coding: utf-8 -*-
"""
„Z arkusza na stronę w dwie minuty" — układ 4 (krok i podgląd), jeden moduł Kod.

Kroki idą naprzemiennie: raz tekst z lewej i okienko z prawej, raz odwrotnie.
Okienka mają WŁASNE, ciemniejsze tło — mają wyglądać jak ekran, a nie jak
kolejny kafelek strony.

Bez skryptu. Sam kod strony i style.

UWAGA przy edycji: każdy znacznik musi zostać w jednej linijce. Divi wstawia
w miejscu złamanego wiersza <br />, co rozbija znacznik.
"""

import re

# Nawiasy kwadratowe tylko jako encje — inaczej WordPress wziąłby je
# za shortcode i wykonał, zamiast pokazać.
L, P = '&#91;', '&#93;'

EN = {
	'kroki': [
		( 'B10', 'Share the sheet',
		  'In Google Sheets: <em>Share &rarr; General access &rarr; Anyone with the link</em>, as a '
		  '<em>Viewer</em>. No API key and no Google Cloud project. Pro can read a private sheet '
		  'instead, by signing in to your own Google account.',
		  'Google Sheets', 'Share &rarr; Anyone with the link',
		  'sharing: Anyone with the link &middot; Viewer' ),
		( 'B11', 'Paste the link and check the preview',
		  'The plugin shows exactly what it read &mdash; headings, rows, what merged cells did &mdash; '
		  'before you save anything. A sheet with several tabs gets a picker.',
		  'Live Sheets Table', 'docs.google.com/spreadsheets/d/1aZ&hellip;',
		  '3 tabs found &mdash; Prices, Stock, Notes' ),
		( 'B12', 'Put it on the page',
		  'A block, an Elementor widget or a shortcode. The same code draws all three, so they cannot '
		  'drift apart.',
		  'Your page', L + 'sheet_table id=&quot;1&quot;' + P,
		  'table #1 &middot; 128 rows &middot; checked 4 min ago' ),
	],
	'liczby': [
		( '0', 'API keys to paste, for a shared sheet',
		  'You share it with a link and that is the setup. Pro can sign in to your own Google '
		  'account instead, for sheets you would rather keep private.' ),
		( 'No limit', 'on rows, in the free version',
		  'No watermark and no expiry date either. Free is the whole plugin, minus the extras.' ),
		( '15 min', 'between checks, and nobody waits',
		  'The plugin talks to Google in the background. Your visitor is served a table that is '
		  'already sitting on your server.' ),
	],
}

PL = {
	'kroki': [
		( 'B10', 'Udostępnij arkusz',
		  'W Arkuszach Google: <em>Udostępnij &rarr; Dostęp ogólny &rarr; Każda osoba mająca '
		  'link</em>, jako <em>Przeglądający</em>. Bez klucza API i bez projektu w Google Cloud. '
		  'W Pro można zamiast tego czytać arkusz prywatny &mdash; wtyczka loguje się na Twoje '
		  'konto Google.',
		  'Arkusze Google', 'Udostępnij &rarr; Każda osoba mająca link',
		  'udostępnianie: Każda osoba mająca link &middot; Przeglądający' ),
		( 'B11', 'Wklej link i sprawdź podgląd',
		  'Wtyczka pokazuje dokładnie to, co odczytała &mdash; nagłówki, wiersze, co zrobiła ze '
		  'scalonymi komórkami &mdash; zanim cokolwiek zapiszesz. Arkusz z kilkoma kartami dostaje '
		  'przełącznik.',
		  'Live Sheets Table', 'docs.google.com/spreadsheets/d/1aZ&hellip;',
		  '3 tabs found &mdash; Prices, Stock, Notes' ),
		( 'B12', 'Wstaw na stronę',
		  'Blok, widżet Elementora albo shortcode. Ten sam kod rysuje wszystkie trzy, więc nie mogą '
		  'się rozjechać.',
		  'Twoja strona', L + 'sheet_table id=&quot;1&quot;' + P,
		  'tabela #1 &middot; 128 wierszy &middot; sprawdzona 4 min temu' ),
	],
	'liczby': [
		( '0', 'kluczy API do wklejenia, przy arkuszu z linkiem',
		  'Udostępniasz arkusz linkiem i to cała konfiguracja. W Pro wtyczka może zamiast tego '
		  'zalogować się na Twoje konto Google &mdash; wtedy arkusz zostaje prywatny.' ),
		( 'Bez limitu', 'wierszy, w wersji darmowej',
		  'Bez znaku wodnego i bez daty ważności. Darmowa to cała wtyczka, tyle że bez dodatków.' ),
		( '15 min', 'między sprawdzeniami, i nikt nie czeka',
		  'Wtyczka rozmawia z Google w tle. Odwiedzający dostaje tabelę, która już leży na Twoim '
		  'serwerze.' ),
	],
}


SZABLON = r'''<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Inria+Serif:wght@400&display=swap">

<div class="lst-2m">
	<div class="lst-2m-rama">
		<div class="lst-2m-pary">
{PARY}
		</div>
		<div class="lst-2m-liczby">
{LICZBY}
		</div>
	</div>
</div>

<style>
.lst-2m {
	--lst-mieta: 95, 227, 207;
	--lst-panel: #1b2221;
	--lst-panel-dol: #161d1c;
	--lst-linia: rgba( 138, 168, 163, .22 );

	/* Kreska między krokami. Przy .16 miała kontrast 1,32:1 do tła strony,
	   czyli w praktyce jej nie było. .38 daje 2,02:1 i widać ją. */
	--lst-kreska: rgba( 138, 168, 163, .38 );
	--lst-tekst: #eaf3f1;
	--lst-tekst-2: #9db3b0;
	--lst-tekst-3: #8fa5a2;
	--lst-mono: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;
	--lst-serif: "Inria Serif", "Iowan Old Style", Georgia, serif;

	/* Płyta pod tekstem kroku. Półprzezroczysta, więc poświata tła nadal
	   przez nią przechodzi — tylko przygaszona, żeby litery nie ginęły. */
	--lst-plyta: rgba( 13, 18, 17, .62 );
	--lst-plyta-linia: rgba( 138, 168, 163, .1 );

	/* ---- tło podglądów ------------------------------------------------
	   Okienko ma udawać ekran, więc jest wyraźnie ciemniejsze od strony
	   i od kafelków. Bez tego zlewało się z tłem i wyglądało jak dziura. */
	--lst-ekran: #0a1110;
	--lst-ekran-dol: #060c0b;
	--lst-ekran-gora: #131d1b;
	--lst-ekran-linia: rgba( 95, 227, 207, .2 );

	font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif;
	color: var( --lst-tekst );

	--lst-szerokosc: 90%;
	--lst-max: 1800px;
	--lst-pelna: 100vw;

	/* Pełna szerokość okna, jak tabela, cennik i stopka — inaczej 90%
	   liczyłoby się od wiersza Divi, który sam ma już 90%. */
	width: var( --lst-pelna );
	margin: 0 calc( 50% - var( --lst-pelna ) / 2 );
	overflow-x: clip;
	padding: clamp( 1rem, 2vw, 1.6rem ) 0 clamp( 2rem, 4vw, 3.4rem );
}

.lst-2m.lst-2m { border: 0 !important; outline: 0 !important; background: none !important; }
.lst-2m * { box-sizing: border-box; }
.lst-2m br { display: none; }

/* Reset musi stac PRZED reszta regul — ma wage zero, wiec inaczej
   zabralby krok tekstom i adresom komorek. */
.lst-2m :where( div, p, span, em, a ) {
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

.lst-2m .lst-2m-rama {
	width: var( --lst-szerokosc );
	max-width: var( --lst-max );
	margin-inline: auto;
}

/* ---------- para: krok i jego podgląd ---------- */

.lst-2m .lst-2m-para {
	display: grid;
	grid-template-columns: minmax( 0, 1fr ) minmax( 0, 1fr );
	gap: clamp( 1.2rem, 3vw, 3rem );
	align-items: center;
	padding-block: clamp( 1.4rem, 2.6vw, 2.2rem );
	border-top: 1px solid var( --lst-kreska );
}

.lst-2m .lst-2m-para:first-child { border-top: 0; padding-top: 0; }

/* Co drugi krok odwrócony: okienko po lewej, tekst po prawej. */
.lst-2m .lst-2m-para:nth-child( even ) .lst-2m-okno { order: -1; }

/* Płyta ma tę samą szerokość co okienko obok, więc rząd czyta się jak dwa
   panele. Sam wiersz tekstu jest w środku ograniczony, żeby dało się czytać. */
.lst-2m .lst-2m-tresc {
	padding: 1.15rem 1.3rem 1.25rem;
	background-color: var( --lst-plyta );
	border: 1px solid var( --lst-plyta-linia );
	border-radius: 14px;
}

.lst-2m .lst-2m-krok,
.lst-2m .lst-2m-opis { max-width: 42rem; }

.lst-2m .lst-2m-adres {
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	letter-spacing: .06em;
	color: var( --lst-tekst-3 );
	margin-bottom: .3rem;
}

.lst-2m .lst-2m-krok {
	font-size: 1.25rem;   /* 20 px */
	font-weight: 600;
	line-height: 1.25;
	margin-bottom: .45rem;
}

.lst-2m .lst-2m-opis {
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.55;
	color: var( --lst-tekst-2 );
}

.lst-2m .lst-2m-opis em { font-style: normal; color: rgb( var( --lst-mieta ) ); }

/* ---------- okienko podglądu ---------- */

.lst-2m .lst-2m-okno {
	border: 1px solid var( --lst-ekran-linia ) !important;
	border-radius: 12px;
	overflow: hidden;
	background-color: var( --lst-ekran ) !important;
	background-image: linear-gradient( var( --lst-ekran ), var( --lst-ekran-dol ) ) !important;
	box-shadow: 0 22px 46px -30px rgba( 0, 0, 0, .95 ), 0 0 34px -18px rgba( var( --lst-mieta ), .35 );
}

.lst-2m .lst-2m-belka {
	display: flex;
	align-items: center;
	gap: .45rem;
	padding: .6rem .85rem;
	background: var( --lst-ekran-gora ) !important;
	border-bottom: 1px solid rgba( 95, 227, 207, .14 ) !important;
}

.lst-2m .lst-2m-oczko {
	display: block;
	width: 9px;
	height: 9px;
	border-radius: 999px;
	background: var( --lst-tekst-3 ) !important;
	opacity: .38;
	flex: none;
}

.lst-2m .lst-2m-nazwa-okna {
	margin-left: .5rem;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	color: var( --lst-tekst-3 );
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.lst-2m .lst-2m-ekran {
	padding: 1.15rem 1rem 1.25rem;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	line-height: 1.5;
	color: var( --lst-tekst-2 );

	/* Siatka komórek w środku — subtelna, ale od razu widać, że to ekran
	   arkusza, a nie zwykłe pole tekstowe. */
	background-image: linear-gradient( to right, rgba( 255, 255, 255, .035 ) 1px, transparent 1px ),
		linear-gradient( to bottom, rgba( 255, 255, 255, .035 ) 1px, transparent 1px );
	background-size: 68px 34px;
}

.lst-2m .lst-2m-znak { color: rgb( var( --lst-mieta ) ); flex: none; }

/* Wiersz polecenia: strzałka obok treści, a długi adres łamie się pod sobą,
   nie pod strzałką. */
.lst-2m .lst-2m-wiersz { display: flex; align-items: baseline; gap: .45rem; }
.lst-2m .lst-2m-linia { min-width: 0; word-break: break-word; }

/* Druga linijka — to, co z polecenia wyszło. Ściemniona, żeby nie
   konkurowała z pierwszą, i wcięta pod treść, a nie pod strzałkę. */
.lst-2m .lst-2m-wynik { margin-top: .5rem; padding-left: 1.05rem; color: var( --lst-tekst-3 ); }

/* ---------- pasek liczb ---------- */

.lst-2m .lst-2m-liczby {
	display: grid;
	grid-template-columns: repeat( 3, 1fr );
	gap: 1px;
	background: var( --lst-linia );
	border: 1px solid var( --lst-linia ) !important;
	border-radius: 14px;
	overflow: hidden;
	margin-top: clamp( 1.6rem, 3vw, 2.6rem );
}

.lst-2m .lst-2m-liczba {
	background-color: var( --lst-panel ) !important;
	background-image: linear-gradient( var( --lst-panel ), var( --lst-panel-dol ) ) !important;
	padding: 1.3rem 1.4rem 1.4rem;
}

.lst-2m .lst-2m-duza {
	font-family: var( --lst-serif );
	font-weight: 400;
	font-size: clamp( 2rem, 3.4vw, 2.7rem );
	line-height: 1;
	color: rgb( var( --lst-mieta ) );
	margin-bottom: .5rem;
}

.lst-2m .lst-2m-pod { font-size: 1.125rem;   /* 18 px */ font-weight: 600; margin-bottom: .35rem; }
.lst-2m .lst-2m-tekst { font-size: 1.125rem;   /* 18 px */ line-height: 1.5; color: var( --lst-tekst-2 ); }

/* ---------- utwardzenie na wrogie motywy ---------- */

/* Reset :where() ma wagę zero i przegrywa z motywem, który pisze
   "p { margin: 40px !important }". Tu przebijamy to wprost. */
.lst-2m .lst-2m-adres,
.lst-2m .lst-2m-krok,
.lst-2m .lst-2m-opis,
.lst-2m .lst-2m-duza,
.lst-2m .lst-2m-pod,
.lst-2m .lst-2m-tekst,
.lst-2m .lst-2m-nazwa-okna,
.lst-2m .lst-2m-wynik {
	margin-inline: 0 !important;
	margin-top: 0 !important;
	background: none !important;
	border: 0 !important;
	text-align: left !important;
	text-transform: none !important;
	letter-spacing: normal !important;
}

.lst-2m .lst-2m-adres, .lst-2m .lst-2m-nazwa-okna { font-family: var( --lst-mono ) !important; text-transform: none !important; }
.lst-2m .lst-2m-krok { font-family: inherit !important; text-transform: none !important; letter-spacing: normal !important; margin-bottom: .45rem !important; }
.lst-2m .lst-2m-opis { font-family: inherit !important; color: var( --lst-tekst-2 ) !important; }
.lst-2m .lst-2m-duza { font-family: var( --lst-serif ) !important; color: rgb( var( --lst-mieta ) ) !important; }
.lst-2m .lst-2m-ekran { font-family: var( --lst-mono ) !important; color: var( --lst-tekst-2 ) !important; }
.lst-2m .lst-2m-wynik { font-family: var( --lst-mono ) !important; color: var( --lst-tekst-3 ) !important; margin-top: .5rem !important; }
.lst-2m .lst-2m-oczko { border: 0 !important; }
.lst-2m .lst-2m-tresc { background-color: var( --lst-plyta ) !important; border: 1px solid var( --lst-plyta-linia ) !important; }

/* Obramowanie i dopełnienie, które Divi albo motyw nadaje opakowaniu
   modułu, obrysowałoby sekcję. Zdejmujemy je przez ":has", bez skryptu. */
.et_pb_module:has( .lst-2m ),
.et_pb_column:has( .lst-2m ),
.et_pb_row:has( .lst-2m ) { border: 0 !important; outline: 0 !important; }

@media ( max-width: 900px ) {
	.lst-2m .lst-2m-para { grid-template-columns: 1fr; gap: 1.1rem; }
	.lst-2m .lst-2m-para:nth-child( even ) .lst-2m-okno { order: 0; }
	.lst-2m .lst-2m-liczby { grid-template-columns: 1fr; }
}

@media ( prefers-reduced-motion: reduce ) {
	.lst-2m .lst-2m-okno { transition: none; }
}
</style>

'''


def zbuduj( t, plik ):
	pary = []

	for adres, krok, opis, nazwa_okna, ekran, wynik in t[ 'kroki' ]:
		pary.append(
			'\t\t\t<div class="lst-2m-para">'
			'<div class="lst-2m-tresc">'
			'<p class="lst-2m-adres">' + adres + '</p>'
			'<p class="lst-2m-krok">' + krok + '</p>'
			'<p class="lst-2m-opis">' + opis + '</p></div>'
			'<div class="lst-2m-okno">'
			'<div class="lst-2m-belka">'
			'<span class="lst-2m-oczko"></span><span class="lst-2m-oczko"></span>'
			'<span class="lst-2m-oczko"></span>'
			'<span class="lst-2m-nazwa-okna">' + nazwa_okna + '</span></div>'
			'<div class="lst-2m-ekran">'
			'<div class="lst-2m-wiersz"><span class="lst-2m-znak">&rsaquo;</span> '
			'<span class="lst-2m-linia">' + ekran + '</span></div>'
			'<div class="lst-2m-wynik">' + wynik + '</div></div></div></div>' )

	liczby = []

	for duza, pod, tekst in t[ 'liczby' ]:
		liczby.append(
			'\t\t\t<div class="lst-2m-liczba">'
			'<p class="lst-2m-duza">' + duza + '</p>'
			'<p class="lst-2m-pod">' + pod + '</p>'
			'<p class="lst-2m-tekst">' + tekst + '</p></div>' )

	html = ( SZABLON
		.replace( '{PARY}', '\n'.join( pary ) )
		.replace( '{LICZBY}', '\n'.join( liczby ) ) )

	sprawdz( html, plik )

	with open( plik, 'w', encoding='utf-8' ) as f:
		f.write( html )

	print( plik + ' — kroków: ' + str( len( pary ) ) + ', linii: ' + str( len( html.split( chr( 10 ) ) ) ) )
	return html


def sprawdz( html, plik ):
	"""To, co potrafi wyłożyć Kreator Wizualny albo psuje moduł po cichu."""

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

	if styl.index( ':where(' ) > styl.index( '.lst-2m .lst-2m-rama' ):
		raise SystemExit( plik + ': reset :where() stoi po regułach' )

	for musi in ( '.lst-2m br { display: none; }', 'prefers-reduced-motion',
	              '--lst-ekran:', '.et_pb_module:has( .lst-2m )' ):
		if musi not in html:
			raise SystemExit( plik + ': brakuje ' + musi )

	otwarte = re.findall( r'<(\w+)(?:\s[^>]*)?>', znacznikowanie )
	zamkniete = re.findall( r'</(\w+)>', znacznikowanie )

	for znacznik in set( otwarte ):
		if znacznik in ( 'br', 'link' ):
			continue

		if otwarte.count( znacznik ) != zamkniete.count( znacznik ):
			raise SystemExit( plik + ': <' + znacznik + '> otwarty ' + str( otwarte.count( znacznik ) ) +
				', zamknięty ' + str( zamkniete.count( znacznik ) ) )


def podglad( html_en, html_pl ):
	"""Strona próbna — budowana w tym samym przebiegu, żeby test nie oglądał wczorajszej wersji."""

	strona = ( '<!doctype html>\n<html lang="pl">\n<head>\n<meta charset="utf-8">\n'
		'<meta name="viewport" content="width=device-width,initial-scale=1">\n'
		'<title>Dwie minuty — moduł</title>\n'
		'<style>\nhtml, body { margin: 0; background: #232a29; color: #eaf3f1;\n'
		'\tfont-family: system-ui, sans-serif;\n'
		'\tbackground-image: linear-gradient( to right, rgba( 255, 255, 255, .055 ) 1px, transparent 1px ),\n'
		'\t\tlinear-gradient( to bottom, rgba( 255, 255, 255, .055 ) 1px, transparent 1px );\n'
		'\tbackground-size: 88px 44px; }\n'
		'.et_pb_section { padding: 40px 0; }\n'
		'.et_pb_row { width: 90%; max-width: 1800px; margin: 0 auto; }\n'
		'</style>\n</head>\n<body>\n'
		'<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column">'
		'<div class="et_pb_module">\n' + html_en + '\n</div></div></div></div>\n'
		'</body>\n</html>\n' )

	with open( 'proba.html', 'w', encoding='utf-8' ) as f:
		f.write( strona )

	print( 'proba.html' )


en = zbuduj( EN, 'DWIE-MINUTY-en.html' )
pl = zbuduj( PL, 'DWIE-MINUTY-pl.html' )
podglad( en, pl )
