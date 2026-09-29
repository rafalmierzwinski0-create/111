# -*- coding: utf-8 -*-
"""
Podstrona „what it does" — jak wtyczka działa i co potrafi, na jednym ekranie.

W przeciwieństwie do jak-dziala/ nie ma tu ani jednego zrzutu ekranu: tabela na
stronie jest prawdziwa. Kod tabeli zdejmuje landing/mozliwosci/zbierz.php z
prawdziwego renderu wtyczki, a moduł dokłada do niego jej własny arkusz stylów
i jej własny skrypt — więc sortowanie, szukanie i składanie w karty na wąskim
ekranie działają na stronie sprzedażowej tak samo jak u klienta.

UWAGA przy edycji: każdy znacznik musi zostać w jednej linijce. Divi wstawia
w miejscu złamanego wiersza <br />, co rozbija znacznik. Wewnątrz <style>
i <script> Divi nic nie rusza. Dlatego kod tabeli jest tu zbijany do jednej
linii, a sam moduł ma „br { display: none }”.
"""

import json
import pathlib
import re

TU  = pathlib.Path( __file__ ).parent
CSS = pathlib.Path( '/home/user/111/live-sheets-table/assets/css/lstab-table.css' ).read_text()
JS  = pathlib.Path( '/home/user/111/live-sheets-table/assets/js/lstab-table.js' ).read_text()
M   = json.loads( ( TU / 'markup.json' ).read_text() )

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
	# Nagłówki idące za ekranem mają sens na stronie z jedną tabelą; tu nad nią
	# jest jeszcze pasek Divi, a niżej druga kopia tabeli w ramce telefonu.
	html = html.replace( ' lstab-sticky-head', '' )
	# Filtry, pobieranie i kamery prowadzą na serwer albo w świat. To jest
	# pokaz na stronie sprzedażowej, więc odsyłacze nigdzie nie idą.
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


def lista( tytul, pozycje, klasa = '' ):
	elementy = ''.join( '<li>' + x + '</li>' for x in pozycje )
	return ( '<div class="lst-mz-kolumna' + klasa + '"><p class="lst-mz-kolumna-tytul">' + tytul + '</p>'
		'<ul class="lst-mz-lista">' + elementy + '</ul></div>' )


SEKCJA = (
	'<p class="lst-mz-wstep">Everything below is the plugin itself. The table is not a picture of one: '
	'it is drawn from a spreadsheet by the same code your visitors would get, so click a heading to sort '
	'it, type in the box to search it, and narrow the window to watch it fold into cards.</p>'

	'<div class="lst-mz-kroki">' + ''.join( krok( *k ) for k in KROKI ) + '</div>'

	'<div class="lst-mz-droga">' + ''.join( etap( *e ) for e in DROGA ) + '</div>'
	'<p class="lst-mz-nota">Your page is built from the copy in your own database, so nobody waits for '
	'Google &mdash; and on the day Google will not answer, the last good copy stays on the page while the '
	'dashboard tells you what happened.</p>'

	'<div class="lst-mz-stol">'
	'<p class="lst-mz-etykieta">Live &mdash; this one really sorts and searches</p>'
	+ TABELA +
	'</div>'

	'<div class="lst-mz-legenda">' + ''.join( pozycja( *p ) for p in LEGENDA ) + '</div>'

	'<div class="lst-mz-telefon-blok">'
	'<div class="lst-mz-telefon-tekst">'
	'<p class="lst-mz-etykieta">On a phone</p>'
	'<p class="lst-mz-tytul">Every row becomes a card</p>'
	'<p class="lst-mz-opis">Each value keeps the name of its column, so nothing has to be guessed from '
	'position. What decides is the width of the column the table sits in, not the width of the screen '
	'&mdash; a table in a narrow sidebar folds on a desktop too.'
	'<span class="lst-mz-szeroko"> The frame beside this is a real phone width, with the same table in it.</span>'
	'<span class="lst-mz-wasko"> The table above this is doing it right now.</span></p>'
	'</div>'
	'<div class="lst-mz-telefon-rama"><div class="lst-mz-telefon">' + TABELA + '</div></div>'
	'</div>'

	'<div class="lst-mz-listy">'
	+ lista( 'In the free plugin', WOLNE )
	+ lista( 'Everything above, plus Pro', PRO, ' jest-pro' ) +
	'</div>'

	'<p class="lst-mz-kod"><span class="lst-mz-mono">' + L + 'sheet_table id=&quot;1&quot;' + P + '</span>'
	'<span class="lst-mz-kod-opis">A block, an Elementor widget or this. The same table either way.</span></p>'
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
	gap: clamp( 2rem, 5vw, 3.4rem );
}

.lst-mz .lst-mz-mono { font-family: var( --mz-mono ); }

.lst-mz .lst-mz-wstep {
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.55;
	color: var( --mz-tekst-2 );
	max-width: 46rem;
}

.lst-mz .lst-mz-tytul {
	font-size: 1.25rem;    /* 20 px */
	font-weight: 600;
	line-height: 1.25;
	max-width: 34rem;
}

.lst-mz .lst-mz-opis {
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.55;
	color: var( --mz-tekst-2 );
	max-width: 40rem;
}

.lst-mz .lst-mz-opis em { font-style: normal; color: rgb( var( --mz-mieta ) ); }

.lst-mz .lst-mz-etykieta,
.lst-mz .lst-mz-numer,
.lst-mz .lst-mz-znak,
.lst-mz .lst-mz-nota,
.lst-mz .lst-mz-etap-pod,
.lst-mz .lst-mz-kolumna-tytul {
	font-family: var( --mz-mono );
	font-size: .875rem;    /* 14 px */
	line-height: 1.5;
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
	/* Jedna deklaracja na wszystko, co ten kafelek animuje — inaczej reguła
	   od pojawiania się i reguła od najechania kasują się nawzajem. */
	transition: border-color 200ms ease;
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
}

/* Strzałka w przerwie między etapami, a nie w środku kafelka. */
.lst-mz .lst-mz-etap + .lst-mz-etap::before {
	content: "";
	position: absolute;
	top: 50%;
	right: 100%;
	width: clamp( 1.4rem, 3vw, 2.6rem );
	height: 1px;
	background-color: var( --mz-kreska );
}

.lst-mz .lst-mz-etap + .lst-mz-etap::after {
	content: "";
	position: absolute;
	top: 50%;
	right: calc( 100% - 1px );
	width: 6px;
	height: 6px;
	border-top: 1px solid var( --mz-kreska );
	border-right: 1px solid var( --mz-kreska );
	transform: translate( 0, -50% ) rotate( 45deg );
}

.lst-mz .lst-mz-etap-nazwa { font-size: 1.125rem; font-weight: 600; line-height: 1.3; }
.lst-mz .lst-mz-etap-pod { color: var( --mz-tekst-3 ); }

.lst-mz .lst-mz-nota {
	color: var( --mz-tekst-3 );
	max-width: 52rem;
	margin-top: calc( -1 * clamp( 1rem, 3vw, 2rem ) );
}

/* ------------------------------------------------------- tabela na żywo */

.lst-mz .lst-mz-stol { display: grid; gap: .7rem; }

.lst-mz .lst-mz-etykieta {
	letter-spacing: .12em;
	text-transform: uppercase;
	color: rgb( var( --mz-mieta ) );
}

/* ------------------------------------------------------------- legenda */

.lst-mz .lst-mz-legenda {
	display: grid;
	grid-template-columns: repeat( 3, minmax( 0, 1fr ) );
	gap: clamp( 1.1rem, 2.4vw, 1.8rem );
}

.lst-mz .lst-mz-pozycja {
	display: grid;
	gap: .35rem;
	align-content: start;
}

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

/* -------------------------------------------------------------- telefon */

.lst-mz .lst-mz-telefon-blok {
	display: grid;
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
 * rozciągnęłoby tę sekcję na dwa ekrany, a widać już po trzech. Reszta jest
 * ucięta tak, jak ucina ją telefon.
 */
.lst-mz .lst-mz-telefon {
	width: 360px;
	max-width: 100%;
	max-height: 560px;
	overflow: hidden;
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
}

.lst-mz .lst-mz-kolumna.jest-pro { border-color: rgba( var( --mz-mieta ), .28 ); }

.lst-mz .lst-mz-kolumna-tytul {
	letter-spacing: .12em;
	text-transform: uppercase;
	color: var( --mz-tekst-3 );
}

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
	max-width: none;
}

.lst-mz .lst-mz-krok,
.lst-mz .lst-mz-kolumna,
.lst-mz .lst-mz-kod { background-color: var( --mz-plyta ) !important; border: 1px solid var( --mz-plyta-linia ) !important; }

.lst-mz .lst-mz-etap { background-color: transparent !important; border: 1px solid var( --mz-kreska ) !important; }

.lst-mz .lst-mz-telefon-rama { background-color: var( --mz-plyta ) !important; border: 1px solid var( --mz-kreska ) !important; }

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
.lst-mz .lst-mz-kolumna-tytul,
.lst-mz .lst-mz-kod,
.lst-mz .lst-mz-kod-opis,
.lst-mz .lst-mz-mono { font-family: var( --mz-mono ) !important; }

.lst-mz .lst-mz-wstep,
.lst-mz .lst-mz-opis,
.lst-mz .lst-mz-lista li { max-width: 46rem; }

/* Sama tabela broni się swoim arkuszem; tu tylko tyle, żeby cudze marginesy
   nie wypchnęły jej poza szerokość strony. */
.lst-mz .lstab-container, .lst-mz .lstab { margin-inline: 0 !important; max-width: 100%; }

@media ( hover: hover ) and ( pointer: fine ) {
	.lst-mz .lst-mz-krok:hover { border-color: rgba( var( --mz-mieta ), .34 ); }
}

@media ( prefers-reduced-motion: reduce ) {
	.lst-mz .lst-mz-krok { transition: none; }
}

/* ------------------------------------------------------------- telefon */

@media ( max-width: 1180px ) {
	.lst-mz .lst-mz-legenda { grid-template-columns: repeat( 2, minmax( 0, 1fr ) ); }
}

@media ( max-width: 900px ) {
	.lst-mz .lst-mz-legenda { grid-template-columns: minmax( 0, 1fr ); }

	.lst-mz .lst-mz-kroki,
	.lst-mz .lst-mz-droga,
	.lst-mz .lst-mz-telefon-blok { grid-template-columns: minmax( 0, 1fr ); }

	.lst-mz .lst-mz-etap + .lst-mz-etap::before {
		top: auto;
		bottom: 100%;
		right: auto;
		left: 1.5rem;
		width: 1px;
		height: clamp( 1.4rem, 3vw, 2.6rem );
	}

	.lst-mz .lst-mz-etap + .lst-mz-etap::after {
		top: auto;
		bottom: calc( 100% - 1px );
		right: auto;
		left: calc( 1.5rem - 2px );
		transform: translate( -50%, 50% ) rotate( 135deg );
	}

	.lst-mz .lst-mz-nota { margin-top: 0; }
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

# Podgląd do otwarcia w przeglądarce: podrabia tło i dopełnienia Divi, żeby dało
# się obejrzeć sekcję taką, jaka wyjdzie na stronie. Do Divi idzie wyłącznie
# MOZLIWOSCI-en.html.
PODGLAD = (
	'<title>What it does</title>\n'
	'<style>\n'
	'html, body { margin: 0; background: #141b1a; color: #eaf3f1;\n'
	'\tfont-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif; }\n'
	'.podrobka-divi { padding: 40px 0; }\n'
	'.podrobka-divi-rzad { width: 90%; max-width: 1800px; margin: 0 auto; }\n'
	'</style>\n'
	'<div class="podrobka-divi"><div class="podrobka-divi-rzad">\n'
	+ STRONA +
	'\n</div></div>\n'
)

( TU / 'PODGLAD.html' ).write_text( PODGLAD )

print( 'ok', len( STRONA ), 'znaków modułu' )
