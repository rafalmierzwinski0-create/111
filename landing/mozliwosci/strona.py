# -*- coding: utf-8 -*-
"""
Podstrona „what it does” — jak wtyczka działa i co potrafi, na jednej stronie.

Nic tu nie jest przepisane ręcznie: tabela to kod z prawdziwego renderu
(landing/mozliwosci/zbierz.php), a trzy zrzuty to prawdziwy kokpit na tym samym
źródle (landing/mozliwosci/zrzuty.mjs). Moduł niesie w sobie arkusz stylów i
skrypt wtyczki, więc sortowanie, szukanie i składanie w karty dzieją się na
stronie sprzedażowej tak samo jak u klienta.

Adresy zrzutów zaczynają się od ADRES. Wgrywasz trzy pliki z zrzuty/ do
Multimediów, kopiujesz adres folderu i podmieniasz ADRES jeden raz.

UWAGA przy edycji: każdy znacznik musi zostać w jednej linijce. Divi wstawia
w miejscu złamanego wiersza <br />, co rozbija znacznik. Wewnątrz <style>
i <script> Divi nic nie rusza. Dlatego kod tabeli jest zbijany do jednej linii,
a moduł ma „br { display: none }”.
"""

import json
import pathlib
import re

TU  = pathlib.Path( __file__ ).parent
CSS = pathlib.Path( '/home/user/111/live-sheets-table/assets/css/lstab-table.css' ).read_text()
JS  = pathlib.Path( '/home/user/111/live-sheets-table/assets/js/lstab-table.js' ).read_text()
M   = json.loads( ( TU / 'markup.json' ).read_text() )
ZRZUTY = { z[ 'nazwa' ]: z for z in json.loads( ( TU / 'zrzuty' / 'rozmiary.json' ).read_text() ) }

L, P = '&#91;', '&#93;'


def dla_divi( html ):
	"""Kod tabeli tak, żeby przeżył wklejenie w moduł Kod."""
	# Odstęp między znacznikami znika w całości — inaczej Divi wstawi w każdym
	# złamaniu wiersza <br />, a w środku znacznika łamie go na pół.
	html = re.sub( r'>\s+<', '><', html )
	html = re.sub( r'\s*\n\s*', ' ', html )
	# Suwak pod tabelą dostaje szerokość od skryptu; ta z renderu policzona jest
	# dla innego okna.
	html = re.sub( r'(<div class="lstab-scrollbar-thumb"[^>]*?) style="[^"]*"', r'\1', html )
	# Nagłówki idące za ekranem mają sens na stronie z jedną tabelą; tu pod nią
	# jest jeszcze jedna kopia w ramce telefonu.
	html = html.replace( ' lstab-sticky-head', '' )
	# Filtry, pobieranie i kamery prowadzą na serwer albo w świat. To jest pokaz
	# na stronie sprzedażowej, więc odsyłacze nigdzie nie idą.
	html = re.sub( r'href="(?!#)[^"]*"', 'href="#"', html )
	return html.strip()


TABELA = dla_divi( M[ 'pro' ] )

KROKI = [
	( '01', 'Share the sheet',
	  'In Google Sheets: <em>Share &rarr; Anyone with the link &rarr; Viewer</em>. Nothing is installed '
	  'at Google&rsquo;s end, and there is no API key to create.' ),
	( '02', 'Paste the link',
	  'The plugin reads the sheet there and then, and shows what it found &mdash; the headings, the rows, '
	  'anything that looks wrong. You fix it before it reaches a page.' ),
	( '03', 'Put it on a page',
	  'A block, an Elementor widget or the shortcode. Edit the spreadsheet afterwards and the page '
	  'follows on its own.' ),
]

DROGA = [
	( 'Your sheet', 'in Google, where you already work' ),
	( 'A copy in your database', 'fetched in the background, as often as you like' ),
	( 'Your page', 'built on the server, before the visitor asks' ),
]

LEGENDA = [
	( 'Free', 'Search and sorting',
	  'Type and the table narrows down, with every hit marked where it was found. Click a heading to '
	  'sort: dates sort as dates, clocks as clocks.' ),
	( 'Free', 'A pinned first column',
	  'Drag the table sideways and the trail names stay where they are. Without that, a wide table on a '
	  'narrow screen is a row of numbers nobody can name.' ),
	( 'Pro', 'A colour rule',
	  'When <em>Status</em> is <em>Closed</em>, paint the whole row. Three closed trails are visible '
	  'before anyone has read a word.' ),
	( 'Pro', 'A pill, or just a dot',
	  'The same rule, quieter. The value keeps its place and takes a badge, or only a dot beside it. '
	  'Sorting and search still read &ldquo;Open&rdquo;, not a colour.' ),
	( 'Pro', 'A bar, and a button',
	  'The bar behind each depth is its share of the deepest snow in the column, and the number stays a '
	  'number &mdash; it sorts, and it lands in the download. A column of addresses becomes a column of '
	  'buttons; two trails have no webcam, and those cells stay empty.' ),
	( 'Pro', 'Filters and downloads',
	  'Above the table, a filter your visitors use themselves. Under it, Excel, CSV and print &mdash; and '
	  'a download holds exactly what is on the screen, filtered rows and hidden columns included.' ),
]

# ( plik, tytuł, zdanie, okno )
EKRANY = [
	( 'mz-wyglad', 'Nine styles, and two dials',
	  'Pick a style, then disagree with it: colours one by one, text size, row height and how many lines '
	  'the table draws. Everything you leave alone keeps following the style.',
	  'Appearance' ),
	( 'mz-reguly', 'A rule reads like a sentence',
	  'When <em>Status</em> is <em>Closed</em>, paint <em>the whole row</em>. No formulas, no code, and '
	  'the colours are worked out on the server, so they are already in the page a visitor receives.',
	  'Colour rules' ),
	( 'mz-kolumny', 'A column can wear something',
	  'Give a column of numbers a bar, or a column of links a button &mdash; in the colours you choose '
	  'and saying what you tell it to say.',
	  'Column looks' ),
]

WOLNE = [
	'Every row your sheet has. No cap at 30, 50 or 100.',
	'A real table in the page code, so Google and screen readers see it.',
	'Three table styles, plus colours, text size, row height, lines and corners.',
	'Search, sorting and pages.',
	'Cards on a narrow screen &mdash; decided by the column, not the window.',
	'A pinned first column and headings that follow the screen down.',
	'Rename, hide, reorder and align columns without touching the sheet.',
	'Your own CSS per table, and a block, an Elementor widget or a shortcode.',
]

PRO = [
	'As many sheets as you like, checked as often as every minute.',
	'Six more styles: Cards, Terminal, Glass, Contrast, Midnight, Editorial.',
	'Colour rules: the cell, the whole row, just the words, a pill or a dot.',
	'Column looks: a bar behind a number, a button where there is a link.',
	'Filters your visitors use themselves.',
	'Excel, CSV and print, holding exactly what the page shows.',
	'An expandable panel under each row, for the columns that do not fit.',
	'Private sheets, through a Google connection of your own.',
]


def etykieta( tekst ):
	return '<p class="lst-mz-etykieta">' + tekst + '</p>'


def krok( numer, tytul, opis ):
	return ( '<div class="lst-mz-krok"><p class="lst-mz-numer">' + numer + '</p>'
		'<p class="lst-mz-tytul">' + tytul + '</p>'
		'<p class="lst-mz-opis">' + opis + '</p></div>' )


def etap( nazwa, pod ):
	return ( '<div class="lst-mz-etap"><p class="lst-mz-etap-nazwa">' + nazwa + '</p>'
		'<p class="lst-mz-etap-pod">' + pod + '</p></div>' )


def pozycja( tier, tytul, opis ):
	klasa = ' jest-pro' if 'Pro' == tier else ''
	return ( '<div class="lst-mz-pozycja"><p class="lst-mz-znak' + klasa + '">' + tier + '</p>'
		'<p class="lst-mz-tytul">' + tytul + '</p>'
		'<p class="lst-mz-opis">' + opis + '</p></div>' )


def ekran( plik, tytul, opis, okno, odwrocony ):
	z = ZRZUTY[ plik ]
	kropki = '<span class="lst-mz-kropka"></span><span class="lst-mz-kropka"></span><span class="lst-mz-kropka"></span>'
	return ( '<div class="lst-mz-ekran' + ( ' jest-odwrocony' if odwrocony else '' ) + '">'
		'<div class="lst-mz-ekran-tresc"><p class="lst-mz-tytul">' + tytul + '</p>'
		'<p class="lst-mz-opis">' + opis + '</p></div>'
		'<div class="lst-mz-okno"><div class="lst-mz-belka">' + kropki +
		'<span class="lst-mz-nazwa-okna">' + okno + '</span></div>'
		'<img src="ADRES/' + plik + '.png" alt="' + okno + '" width="' + str( z[ 'w' ] ) + '" '
		'height="' + str( z[ 'h' ] ) + '" loading="lazy" decoding="async"></div>'
		'</div>' )


def lista( tytul, pozycje, klasa = '' ):
	elementy = ''.join( '<li>' + x + '</li>' for x in pozycje )
	return ( '<div class="lst-mz-kolumna' + klasa + '"><p class="lst-mz-kolumna-tytul">' + tytul + '</p>'
		'<ul class="lst-mz-lista">' + elementy + '</ul></div>' )


SEKCJA = (
	# --- jak to działa: trzy kroki i droga arkusza -------------------------
	'<div class="lst-mz-blok">'
	+ etykieta( 'How it works' ) +
	'<div class="lst-mz-kroki">' + ''.join( krok( *k ) for k in KROKI ) + '</div>'
	'<div class="lst-mz-droga">' + ''.join( etap( *e ) for e in DROGA ) + '</div>'
	'<p class="lst-mz-nota">Your page is built from the copy in your own database, so nobody waits for '
	'Google &mdash; and on the day Google will not answer, the last good copy stays on the page while the '
	'dashboard tells you what happened.</p>'
	'</div>'

	# --- tabela na żywo ----------------------------------------------------
	'<div class="lst-mz-blok lst-mz-stol">'
	'<p class="lst-mz-etykieta jest-zywa"><span class="lst-mz-puls"></span>Live on this page</p>'
	'<p class="lst-mz-opis lst-mz-opis-stolu">Ten trails, one spreadsheet. Sort a column, search the box, '
	'filter it &mdash; this is the plugin&rsquo;s own output, running here.</p>'
	'<div class="lst-mz-szklo">' + TABELA + '</div>'
	'</div>'

	# --- co na niej widać --------------------------------------------------
	'<div class="lst-mz-blok">'
	+ etykieta( 'What to look for' ) +
	'<div class="lst-mz-legenda">' + ''.join( pozycja( *p ) for p in LEGENDA ) + '</div>'
	'</div>'

	# --- skąd się to bierze ------------------------------------------------
	'<div class="lst-mz-blok">'
	+ etykieta( 'Where it comes from' ) +
	'<p class="lst-mz-wstep">Three screens from the dashboard, on the very sheet above.</p>'
	'<div class="lst-mz-ekrany">'
	+ ''.join( ekran( *e, odwrocony = bool( i % 2 ) ) for i, e in enumerate( EKRANY ) ) +
	'</div>'
	'</div>'

	# --- telefon -----------------------------------------------------------
	'<div class="lst-mz-blok lst-mz-telefon-blok">'
	'<div class="lst-mz-telefon-tekst">'
	+ etykieta( 'On a phone' ) +
	'<p class="lst-mz-tytul">Every row becomes a card</p>'
	'<p class="lst-mz-opis">Each value keeps the name of its column, so nothing has to be guessed from '
	'position. What decides is the width of the column the table sits in, not the width of the screen '
	'&mdash; a table in a narrow sidebar folds on a desktop too.'
	'<span class="lst-mz-szeroko"> The frame beside this is a real phone width, with the same table in it.</span>'
	'<span class="lst-mz-wasko"> The table above this is doing it right now.</span></p>'
	'</div>'
	'<div class="lst-mz-telefon-rama"><div class="lst-mz-telefon lst-mz-szklo">' + TABELA + '</div></div>'
	'</div>'

	# --- co jest w czym ----------------------------------------------------
	'<div class="lst-mz-blok">'
	+ etykieta( 'What is in which' ) +
	'<div class="lst-mz-listy">'
	+ lista( 'In the free plugin', WOLNE )
	+ lista( 'Everything above, plus Pro', PRO, ' jest-pro' ) +
	'</div>'
	'<p class="lst-mz-kod"><span class="lst-mz-mono">' + L + 'sheet_table id=&quot;1&quot;' + P + '</span>'
	'<span class="lst-mz-kod-opis">A block, an Elementor widget or this. The same table either way.</span></p>'
	'</div>'
)


STYL = r"""
/* ---------------------------------------------------------------- moduł */

.lst-mz {
	--mz-mieta: 95, 227, 207;
	--mz-tekst: #eaf3f1;
	--mz-tekst-2: #b3c6c3;
	--mz-tekst-3: #8fa5a2;
	--mz-plyta: rgba( 13, 18, 17, .62 );
	--mz-plyta-linia: rgba( 138, 168, 163, .12 );
	--mz-kreska: rgba( 138, 168, 163, .28 );
	--mz-mono: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;
	/* Mocniejszy ease-out niż wbudowany: ruch rusza od razu, a dochodzi
	   spokojnie. Wbudowane krzywe są na to za miękkie. */
	--mz-luk: cubic-bezier( .23, 1, .32, 1 );

	font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif;
	color: var( --mz-tekst );

	--mz-szerokosc: 90%;
	--mz-max: 1240px;
	--mz-pelna: 100vw;

	width: var( --mz-pelna );
	margin: 0 calc( 50% - var( --mz-pelna ) / 2 );
	overflow-x: clip;
	padding: clamp( 1rem, 2vw, 1.6rem ) 0 clamp( 2rem, 4vw, 3.4rem );
}

.lst-mz.lst-mz { border: 0 !important; outline: 0 !important; background: none !important; }
.lst-mz * { box-sizing: border-box; }
.lst-mz br { display: none; }

/*
 * Reset po nazwie klasy, a nie po nazwie znacznika: pod spodem siedzi cały
 * arkusz stylów wtyczki i jego divów, spanów i odsyłaczy nie wolno tknąć.
 * Wszystko, co jest moje, ma klasę zaczynającą się od „lst-mz-”.
 */
.lst-mz [class*="lst-mz-"] {
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
	list-style: none;
	width: auto;
	max-width: none;
	min-width: 0;
}

.lst-mz .lst-mz-rama {
	width: var( --mz-szerokosc );
	max-width: var( --mz-max );
	margin-inline: auto;
	display: grid;
	gap: clamp( 2.4rem, 6vw, 4.2rem );
}

/* Każdy blok to jedna myśl: etykieta, rzecz, i odstęp pod spodem. */
.lst-mz .lst-mz-blok { display: grid; gap: clamp( 1rem, 2.4vw, 1.6rem ); }

.lst-mz .lst-mz-mono { font-family: var( --mz-mono ); }

.lst-mz .lst-mz-wstep,
.lst-mz .lst-mz-opis {
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.55;
	color: var( --mz-tekst-2 );
	max-width: 44rem;
}

.lst-mz .lst-mz-tytul {
	font-size: 1.25rem;    /* 20 px */
	font-weight: 600;
	line-height: 1.25;
	max-width: 34rem;
}

.lst-mz .lst-mz-opis em { font-style: normal; color: rgb( var( --mz-mieta ) ); }

.lst-mz .lst-mz-etykieta,
.lst-mz .lst-mz-numer,
.lst-mz .lst-mz-znak,
.lst-mz .lst-mz-nota,
.lst-mz .lst-mz-etap-pod,
.lst-mz .lst-mz-nazwa-okna,
.lst-mz .lst-mz-kolumna-tytul {
	font-family: var( --mz-mono );
	font-size: .875rem;    /* 14 px */
	line-height: 1.5;
}

.lst-mz .lst-mz-etykieta {
	letter-spacing: .14em;
	text-transform: uppercase;
	color: rgb( var( --mz-mieta ) );
}

/* ---------------------------------------------------------- trzy kroki */

.lst-mz .lst-mz-kroki {
	display: grid;
	grid-template-columns: repeat( 3, minmax( 0, 1fr ) );
	gap: clamp( .8rem, 1.6vw, 1.2rem );
}

.lst-mz .lst-mz-krok {
	display: grid;
	align-content: start;
	gap: .45rem;
	padding: 1.15rem 1.3rem 1.3rem;
	background-color: var( --mz-plyta );
	border: 1px solid var( --mz-plyta-linia );
	border-radius: 14px;
	/* Jedna deklaracja na wszystko, co ten kafelek animuje — inaczej wejście
	   i najechanie kasują się nawzajem. */
	transition: transform 200ms var( --mz-luk ), border-color 200ms ease;
	/*
	 * Wejście, i tylko ono. Kafelki są w pierwszym ekranie, więc nie czekają na
	 * przewinięcie; to animacja, a nie „opacity: 0” do odwołania, więc element
	 * bez niej jest po prostu widoczny.
	 */
	animation: lst-mz-wejscie 380ms var( --mz-luk ) both;
}

.lst-mz .lst-mz-krok:nth-child( 2 ) { animation-delay: 70ms; }
.lst-mz .lst-mz-krok:nth-child( 3 ) { animation-delay: 140ms; }

@keyframes lst-mz-wejscie {
	from { opacity: 0; transform: translateY( 12px ) scale( .985 ); }
}

.lst-mz .lst-mz-numer { letter-spacing: .14em; color: rgb( var( --mz-mieta ) ); }

/* --------------------------------------------------- droga arkusz → strona */

.lst-mz .lst-mz-droga {
	display: grid;
	grid-template-columns: repeat( 3, minmax( 0, 1fr ) );
	gap: clamp( 1.4rem, 3vw, 2.6rem );
}

.lst-mz .lst-mz-etap {
	position: relative;
	display: grid;
	gap: .3rem;
	align-content: start;
	padding: 1rem 1.1rem 1.05rem;
	border: 1px solid var( --mz-kreska );
	border-radius: 12px;
	animation: lst-mz-wejscie 380ms var( --mz-luk ) both;
	animation-delay: 210ms;
}

.lst-mz .lst-mz-etap:nth-child( 2 ) { animation-delay: 280ms; }
.lst-mz .lst-mz-etap:nth-child( 3 ) { animation-delay: 350ms; }

/*
 * Kreska między etapami rysuje się raz, w stronę, w którą idą dane, i na tym
 * kończy. Jeździła tędy kropka w kółko i to był zły pomysł: ruch bez końca na
 * skraju oka nie pokazuje niczego, czego nie pokazuje sama kreska, a widać go
 * przez cały czas, kiedy się czyta.
 */
.lst-mz .lst-mz-etap + .lst-mz-etap::before {
	content: "";
	position: absolute;
	top: 50%;
	right: 100%;
	width: clamp( 1.4rem, 3vw, 2.6rem );
	height: 1px;
	background-color: var( --mz-kreska );
	transform-origin: left center;
	animation: lst-mz-kreska 420ms var( --mz-luk ) both;
	animation-delay: 320ms;
}

.lst-mz .lst-mz-etap:nth-child( 3 )::before { animation-delay: 390ms; }

@keyframes lst-mz-kreska-w-dol {
	from { transform: scaleY( 0 ); }
}

@keyframes lst-mz-kreska {
	from { transform: scaleX( 0 ); }
}

.lst-mz .lst-mz-etap-nazwa { font-size: 1.125rem; font-weight: 600; line-height: 1.3; }
.lst-mz .lst-mz-etap-pod { color: var( --mz-tekst-3 ); }
.lst-mz .lst-mz-nota { color: var( --mz-tekst-3 ); max-width: 52rem; }

/* ------------------------------------------------------- tabela na żywo */

.lst-mz .lst-mz-opis-stolu { margin-top: -.6rem; }

.lst-mz .lst-mz-etykieta.jest-zywa { display: flex; align-items: center; gap: .5rem; }

.lst-mz .lst-mz-puls {
	width: 7px;
	height: 7px;
	border-radius: 50%;
	background-color: rgb( var( --mz-mieta ) );
	box-shadow: 0 0 0 0 rgba( var( --mz-mieta ), .55 );
	/* Trzy razy i koniec. Kropka przy słowie „live” ma raz zwrócić uwagę, a nie
	   mrugać komuś nad tabelą przez cały czas, kiedy ją czyta. */
	animation: lst-mz-puls 1.8s var( --mz-luk ) 3;
}

@keyframes lst-mz-puls {
	0%   { box-shadow: 0 0 0 0 rgba( var( --mz-mieta ), .5 ); }
	70%  { box-shadow: 0 0 0 7px rgba( var( --mz-mieta ), 0 ); }
	100% { box-shadow: 0 0 0 0 rgba( var( --mz-mieta ), 0 ); }
}

/*
 * Szyba musi mieć przez co patrzeć: szablon Szkło jest matową taflą, a tafla
 * nad czernią to czerń. Pod tabelą leży więc światło w barwach strony —
 * mięta z jednej strony, głęboki błękit z drugiej.
 */
.lst-mz .lst-mz-szklo {
	padding: clamp( .7rem, 1.6vw, 1.4rem );
	border-radius: 22px;
	background:
		radial-gradient( 80% 70% at 10% 8%, #1f7a6b 0%, rgba( 31, 122, 107, 0 ) 62% ),
		radial-gradient( 70% 60% at 92% 92%, #2c4a7d 0%, rgba( 44, 74, 125, 0 ) 60% ),
		linear-gradient( 152deg, #12302e 0%, #0d1a23 100% );
	box-shadow: inset 0 1px 0 rgba( 255, 255, 255, .06 );
}

/* ------------------------------------------------------------- legenda */

.lst-mz .lst-mz-legenda {
	display: grid;
	grid-template-columns: repeat( 3, minmax( 0, 1fr ) );
	gap: clamp( 1.1rem, 2.4vw, 1.8rem );
}

.lst-mz .lst-mz-pozycja { display: grid; gap: .35rem; align-content: start; }

.lst-mz .lst-mz-znak {
	justify-self: start;
	padding: .1rem .5rem;
	border: 1px solid var( --mz-kreska );
	border-radius: 999px;
	letter-spacing: .1em;
	text-transform: uppercase;
	color: var( --mz-tekst-3 );
}

.lst-mz .lst-mz-znak.jest-pro {
	color: rgb( var( --mz-mieta ) );
	border-color: rgba( var( --mz-mieta ), .45 );
	background-color: rgba( var( --mz-mieta ), .1 );
}

/* ------------------------------------------------------- zrzuty z kokpitu */

.lst-mz .lst-mz-ekrany { display: grid; gap: clamp( 1.6rem, 3.5vw, 2.8rem ); }

.lst-mz .lst-mz-ekran {
	display: grid;
	grid-template-columns: minmax( 0, 1fr ) minmax( 0, 1.25fr );
	gap: clamp( 1.2rem, 3vw, 2.6rem );
	align-items: center;
}

.lst-mz .lst-mz-ekran.jest-odwrocony {
	grid-template-columns: minmax( 0, 1.25fr ) minmax( 0, 1fr );
}

.lst-mz .lst-mz-ekran.jest-odwrocony .lst-mz-okno { order: -1; }
.lst-mz .lst-mz-ekran-tresc { display: grid; gap: .5rem; align-content: start; }

.lst-mz .lst-mz-okno {
	border: 1px solid rgba( var( --mz-mieta ), .2 );
	border-radius: 12px;
	overflow: hidden;
	background-color: #0a1110;
	box-shadow: 0 22px 46px -30px rgba( 0, 0, 0, .95 ), 0 0 34px -18px rgba( var( --mz-mieta ), .3 );
	transition: transform 220ms var( --mz-luk ), box-shadow 220ms var( --mz-luk );
}

.lst-mz .lst-mz-belka {
	display: flex;
	align-items: center;
	gap: .45rem;
	padding: .55rem .8rem;
	background-color: #131d1b;
	border-bottom: 1px solid rgba( var( --mz-mieta ), .14 );
}

.lst-mz .lst-mz-kropka {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	background-color: rgba( 138, 168, 163, .35 );
}

.lst-mz .lst-mz-nazwa-okna { margin-left: .4rem; color: var( --mz-tekst-3 ); }

.lst-mz .lst-mz-okno img {
	display: block;
	width: 100%;
	height: auto;
	transition: transform 320ms var( --mz-luk );
}

/* -------------------------------------------------------------- telefon */

.lst-mz .lst-mz-telefon-blok {
	grid-template-columns: minmax( 0, 34rem ) auto;
	justify-content: space-between;
	gap: clamp( 1.4rem, 4vw, 3rem );
	align-items: start;
}

.lst-mz .lst-mz-telefon-tekst { display: grid; gap: .5rem; align-content: start; }
.lst-mz .lst-mz-wasko { display: none; }

.lst-mz .lst-mz-telefon-rama {
	width: fit-content;
	max-width: 100%;
	padding: 14px;
	border: 1px solid var( --mz-kreska );
	border-radius: 26px;
	background-color: var( --mz-plyta );
}

/*
 * Ekran telefonu jest oknem, nie kartką: dziesięć kart jedna pod drugą
 * rozciągnęłoby tę sekcję na dwa ekrany, a widać już po trzech.
 */
.lst-mz .lst-mz-telefon {
	width: 360px;
	max-width: 100%;
	max-height: 700px;
	overflow: hidden;
	border-radius: 16px;
	-webkit-mask-image: linear-gradient( to bottom, #000 78%, transparent 99% );
	mask-image: linear-gradient( to bottom, #000 78%, transparent 99% );
}

/* --------------------------------------------------------- co jest w czym */

.lst-mz .lst-mz-listy {
	display: grid;
	grid-template-columns: repeat( auto-fit, minmax( 20rem, 1fr ) );
	gap: clamp( 1.2rem, 3vw, 2.2rem );
}

.lst-mz .lst-mz-kolumna {
	display: grid;
	gap: .7rem;
	align-content: start;
	padding: 1.2rem 1.35rem 1.35rem;
	background-color: var( --mz-plyta );
	border: 1px solid var( --mz-plyta-linia );
	border-radius: 14px;
	transition: border-color 200ms ease;
}

.lst-mz .lst-mz-kolumna.jest-pro { border-color: rgba( var( --mz-mieta ), .28 ); }

.lst-mz .lst-mz-kolumna-tytul { letter-spacing: .12em; text-transform: uppercase; color: var( --mz-tekst-3 ); }
.lst-mz .lst-mz-kolumna.jest-pro .lst-mz-kolumna-tytul { color: rgb( var( --mz-mieta ) ); }

.lst-mz .lst-mz-lista { display: grid; gap: .55rem; margin: 0; padding: 0; list-style: none; }

.lst-mz .lst-mz-lista li {
	position: relative;
	margin: 0;
	padding-left: 1.4rem;
	list-style: none;
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.5;
	color: var( --mz-tekst-2 );
}

.lst-mz .lst-mz-lista li::before {
	content: "";
	position: absolute;
	left: .15rem;
	top: .62em;
	width: .42rem;
	height: .42rem;
	border-radius: 50%;
	background-color: rgba( var( --mz-mieta ), .55 );
}

.lst-mz .lst-mz-kolumna.jest-pro .lst-mz-lista li::before { background-color: rgb( var( --mz-mieta ) ); }

/* ------------------------------------------------------------ shortcode */

.lst-mz .lst-mz-kod {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: .6rem 1rem;
	padding: .9rem 1.1rem;
	background-color: var( --mz-plyta );
	border: 1px solid var( --mz-plyta-linia );
	border-radius: 12px;
	font-size: .875rem;
}

.lst-mz .lst-mz-kod .lst-mz-mono { font-family: var( --mz-mono ); color: rgb( var( --mz-mieta ) ); }
.lst-mz .lst-mz-kod-opis { color: var( --mz-tekst-3 ); font-size: .875rem; }

/* ------------------------------------------------------------- najechanie */

@media ( hover: hover ) and ( pointer: fine ) {
	.lst-mz .lst-mz-krok:hover { border-color: rgba( var( --mz-mieta ), .34 ); transform: translateY( -2px ); }
	.lst-mz .lst-mz-kolumna:hover { border-color: rgba( var( --mz-mieta ), .34 ); }
	.lst-mz .lst-mz-okno:hover { transform: translateY( -2px ); box-shadow: 0 26px 50px -30px rgba( 0, 0, 0, .95 ), 0 0 40px -16px rgba( var( --mz-mieta ), .4 ); }
	.lst-mz .lst-mz-okno:hover img { transform: scale( 1.012 ); }
}

/* --------------------------------------------------------- mniej ruchu */

/*
 * Mniej ruchu znaczy mniej ruchu, a nie inny ruch: wejście, kropka i puls
 * znikają, a wszystko, co pokazywały, zostaje na miejscu i widoczne.
 */
@media ( prefers-reduced-motion: reduce ) {
	.lst-mz .lst-mz-krok,
	.lst-mz .lst-mz-etap,
	.lst-mz .lst-mz-puls,
	.lst-mz .lst-mz-etap + .lst-mz-etap::before { animation: none; }

	.lst-mz .lst-mz-krok,
	.lst-mz .lst-mz-okno,
	.lst-mz .lst-mz-okno img,
	.lst-mz .lst-mz-kolumna { transition: none; }
}

/* --------------------------------------------- utwardzenie na wrogie motywy */

/*
 * Motyw pod modułem bywa pisany z „!important”. Twardo trzymane jest tylko to,
 * czym taki motyw rozbija układ: marginesy na boki, wyrównanie, wersaliki,
 * krój i cudze tła z ramkami. Pionowe marginesy zostają miękkie — dołożone
 * powietrze niczego nie psuje, a walka o każdą właściwość kończy się modułem,
 * którego nie da się już poprawić z zewnątrz.
 */
.lst-mz [class*="lst-mz-"] {
	margin-inline: 0 !important;
	background-image: none !important;
	text-align: left !important;
	text-transform: none !important;
	letter-spacing: normal !important;
	font-family: inherit !important;
}

/*
 * Rama jest wyjątkiem od reguły wyżej: to jedyny element, który MA mieć
 * marginesy na boki, bo one go środkują. Utwardzenie zabrało jej „auto” i cała
 * strona przykleiła się do lewej krawędzi. Ta reguła ma tę samą swoistość, więc
 * musi stać PO tamtej, żeby wygrać.
 */
.lst-mz .lst-mz-rama { margin-inline: auto !important; }

.lst-mz .lst-mz-szklo { background-image:
	radial-gradient( 80% 70% at 10% 8%, #1f7a6b 0%, rgba( 31, 122, 107, 0 ) 62% ),
	radial-gradient( 70% 60% at 92% 92%, #2c4a7d 0%, rgba( 44, 74, 125, 0 ) 60% ),
	linear-gradient( 152deg, #12302e 0%, #0d1a23 100% ) !important; }

.lst-mz .lst-mz-krok,
.lst-mz .lst-mz-kolumna,
.lst-mz .lst-mz-kod { background-color: var( --mz-plyta ) !important; border: 1px solid var( --mz-plyta-linia ) !important; }

.lst-mz .lst-mz-etap { background-color: transparent !important; border: 1px solid var( --mz-kreska ) !important; }
.lst-mz .lst-mz-telefon-rama { background-color: var( --mz-plyta ) !important; border: 1px solid var( --mz-kreska ) !important; }
.lst-mz .lst-mz-okno { background-color: #0a1110 !important; border: 1px solid rgba( var( --mz-mieta ), .2 ) !important; }
.lst-mz .lst-mz-belka { background-color: #131d1b !important; border: 0 !important; border-bottom: 1px solid rgba( var( --mz-mieta ), .14 ) !important; }

.lst-mz .lst-mz-znak {
	border: 1px solid var( --mz-kreska ) !important;
	text-transform: uppercase !important;
	font-family: var( --mz-mono ) !important;
}

.lst-mz .lst-mz-znak.jest-pro { border-color: rgba( var( --mz-mieta ), .45 ) !important; background-color: rgba( var( --mz-mieta ), .1 ) !important; }

.lst-mz .lst-mz-etykieta,
.lst-mz .lst-mz-kolumna-tytul { text-transform: uppercase !important; }

.lst-mz .lst-mz-numer,
.lst-mz .lst-mz-etykieta,
.lst-mz .lst-mz-nota,
.lst-mz .lst-mz-etap-pod,
.lst-mz .lst-mz-nazwa-okna,
.lst-mz .lst-mz-kolumna-tytul,
.lst-mz .lst-mz-kod,
.lst-mz .lst-mz-kod-opis,
.lst-mz .lst-mz-mono { font-family: var( --mz-mono ) !important; }

/* Sama tabela broni się swoim arkuszem; tu tylko tyle, żeby cudze marginesy
   nie wypchnęły jej poza szerokość strony. */
.lst-mz .lstab-container, .lst-mz .lstab { margin-inline: 0 !important; max-width: 100%; }

/* ------------------------------------------------------------- telefon */

@media ( max-width: 1180px ) {
	.lst-mz .lst-mz-legenda { grid-template-columns: repeat( 2, minmax( 0, 1fr ) ); }
}

@media ( max-width: 900px ) {
	.lst-mz .lst-mz-legenda,
	.lst-mz .lst-mz-kroki,
	.lst-mz .lst-mz-droga,
	.lst-mz .lst-mz-ekran,
	.lst-mz .lst-mz-telefon-blok { grid-template-columns: minmax( 0, 1fr ); }

	.lst-mz .lst-mz-ekran.jest-odwrocony .lst-mz-okno { order: 0; }

	/* W jednej kolumnie łącznik biegnie z góry na dół, nie z boku na bok. */
	.lst-mz .lst-mz-etap + .lst-mz-etap::before {
		top: auto;
		bottom: 100%;
		right: auto;
		left: 1.5rem;
		width: 1px;
		height: clamp( 1.4rem, 3vw, 2.6rem );
		transform-origin: top center;
		animation-name: lst-mz-kreska-w-dol;
	}

	.lst-mz .lst-mz-telefon-rama { display: none; }
	.lst-mz .lst-mz-szeroko { display: none; }
	.lst-mz .lst-mz-wasko { display: inline; }
}
"""

STRONA = (
	'<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">\n'
	'\n<div class="lst-mz"><div class="lst-mz-rama">' + SEKCJA + '</div></div>\n'
	'\n<style>\n' + STYL + CSS + '\n</style>\n'
	'\n<script>\n' + JS + '\n</script>\n'
)

( TU / 'MOZLIWOSCI-en.html' ).write_text( STRONA )

# Podgląd do otwarcia w przeglądarce: podrabia tło i dopełnienia Divi, i
# podstawia lokalne adresy zrzutów. Do Divi idzie wyłącznie MOZLIWOSCI-en.html.
PODGLAD = (
	'<title>What it does</title>\n'
	'<style>\n'
	'html, body { margin: 0; background: #141b1a; color: #eaf3f1;\n'
	'\tfont-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif; }\n'
	'.podrobka-divi { padding: 40px 0; }\n'
	'.podrobka-divi-rzad { width: 90%; max-width: 1800px; margin: 0 auto; }\n'
	'</style>\n'
	'<div class="podrobka-divi"><div class="podrobka-divi-rzad">\n'
	+ STRONA.replace( 'ADRES/', 'zrzuty/' ) +
	'\n</div></div>\n'
)

( TU / 'PODGLAD.html' ).write_text( PODGLAD )

print( 'ok', len( STRONA ), 'znaków modułu' )
