# -*- coding: utf-8 -*-
"""
Podstrona „pokaz" — działająca tabela, w której odwiedzający może klikać.

Zamiast zrzutów ekranu pokazujemy to, co wtyczka naprawdę robi: szukanie,
sortowanie po kliknięciu nagłówka, strony, zwężanie do telefonu (tabela sama
zamienia się w karty) i trzy rzeczy z Pro do włączenia przełącznikiem.

Bez JavaScriptu tabela nadal jest widoczna w całości — po prostu bez klikania.

UWAGA przy edycji: każdy znacznik musi zostać w jednej linijce. Divi wstawia
w miejscu złamanego wiersza <br />, co rozbija znacznik. Wewnątrz <style>
i <script> Divi nic nie rusza.
"""

import re

L, P = '&#91;', '&#93;'

# ( produkt, kategoria, cena, stan, data )
TOWARY = [
	( 'Trek Marlin 7 mountain bike',   'bikes',       4199.99, 'jest',      '2026-09-22' ),
	( 'Lezyne 800 front light',        'accessories',  289.00, 'brak',      '2026-09-21' ),
	( 'Insulated bottle, 750 ml',      'accessories',   29.00, 'jest',      '2026-09-23' ),
	( 'Pro Gel gloves',                'clothing',     159.00, 'zamowione', '2026-09-20' ),
	( 'Abus Granit combination lock',  'accessories', 1215.50, 'jest',      '2026-09-23' ),
	( 'Giant Escape 3 city bike',      'bikes',       2899.00, 'jest',      '2026-09-19' ),
	( 'Merino base layer',             'clothing',     249.00, 'brak',      '2026-09-22' ),
	( 'Shimano SPD pedals',            'accessories',  319.00, 'jest',      '2026-09-18' ),
	( 'Rain jacket, unisex',           'clothing',     459.00, 'zamowione', '2026-09-23' ),
	( 'Cube Aim SL hardtail',          'bikes',       3499.00, 'jest',      '2026-09-21' ),
	( 'Garmin Edge 540 computer',      'accessories', 1799.00, 'brak',      '2026-09-20' ),
	( 'Bib shorts, padded',            'clothing',     389.00, 'jest',      '2026-09-22' ),
	( 'Topeak floor pump',             'accessories',  179.00, 'jest',      '2026-09-17' ),
	( 'Kids helmet, 48&ndash;54 cm',   'clothing',     139.00, 'jest',      '2026-09-23' ),
]

EN = {
	'lang': 'en',
	'wstep': 'Everything below is a live table, not a picture. Type in the search box, click a column '
	         'name, or switch the width to Phone and watch the rows fold into cards.',
	'kolumny': [ ( 'Product', 'tekst' ), ( 'Category', 'tekst' ), ( 'Price', 'liczba' ),
	             ( 'Stock', 'tekst' ), ( 'Updated', 'tekst' ) ],
	'kategorie': { 'bikes': 'Bikes', 'clothing': 'Clothing', 'accessories': 'Accessories' },
	'stany': { 'jest': 'In stock', 'brak': 'Out of stock', 'zamowione': 'On order' },
	'szukaj': 'Search the table',
	'wyczysc': 'Clear',
	'szerokosc': 'Width',
	'szer': [ ( 'szeroki', 'Desktop' ), ( 'sredni', 'Tablet' ), ( 'waski', 'Phone' ) ],
	'wierszy': 'rows',
	'odmiana': [ 'row', 'rows', 'rows' ],
	'z': 'of',
	'poprzednia': 'previous',
	'nastepna': 'next',
	'nic': 'Nothing matches that search.',
	'swiezosc': 'checked 4 minutes ago',
	'kod': L + 'sheet_table id=&quot;1&quot;' + P,
	'pro_tytul': 'Three things Pro adds. Switch them on and watch the same table change.',
	'pro': [
		( 'reguly', 'Colour by rules', 'Anything out of stock turns amber.' ),
		( 'filtry', 'Filters for visitors', 'A row of categories your visitor can click.' ),
		( 'pobierz', 'Download', 'Takes exactly what is on screen, filters and all.' ),
	],
	'wszystkie': 'All',
	'csv': 'Download CSV',
	'plik': 'price-list.csv',
	'brak_js': 'This page needs JavaScript for the buttons. The table itself is drawn on the server, '
	           'so it is here either way.',
}

PL = {
	'lang': 'pl',
	'wstep': 'Wszystko poniżej to działająca tabela, a nie obrazek. Wpisz coś w wyszukiwarkę, kliknij '
	         'nazwę kolumny albo przełącz szerokość na Telefon i zobacz, jak wiersze składają się '
	         'w karty.',
	'kolumny': [ ( 'Produkt', 'tekst' ), ( 'Kategoria', 'tekst' ), ( 'Cena', 'liczba' ),
	             ( 'Stan', 'tekst' ), ( 'Zmieniono', 'tekst' ) ],
	'kategorie': { 'bikes': 'Rowery', 'clothing': 'Odzież', 'accessories': 'Akcesoria' },
	'stany': { 'jest': 'Jest', 'brak': 'Brak', 'zamowione': 'Zamówione' },
	'szukaj': 'Szukaj w tabeli',
	'wyczysc': 'Wyczyść',
	'szerokosc': 'Szerokość',
	'szer': [ ( 'szeroki', 'Monitor' ), ( 'sredni', 'Tablet' ), ( 'waski', 'Telefon' ) ],
	'wierszy': 'wierszy',
	'odmiana': [ 'wiersz', 'wiersze', 'wierszy' ],
	'z': 'z',
	'poprzednia': 'poprzednia',
	'nastepna': 'następna',
	'nic': 'Nic nie pasuje do tego wyszukiwania.',
	'swiezosc': 'sprawdzone 4 minuty temu',
	'kod': L + 'sheet_table id=&quot;1&quot;' + P,
	'pro_tytul': 'Trzy rzeczy, które dokłada Pro. Włącz je i patrz, jak zmienia się ta sama tabela.',
	'pro': [
		( 'reguly', 'Kolorowanie regułami', 'Wszystko, czego brakuje, robi się bursztynowe.' ),
		( 'filtry', 'Filtry dla odwiedzających', 'Rząd kategorii, w które gość może kliknąć.' ),
		( 'pobierz', 'Pobieranie', 'Bierze dokładnie to, co widać, razem z filtrami.' ),
	],
	'wszystkie': 'Wszystkie',
	'csv': 'Pobierz CSV',
	'plik': 'cennik.csv',
	'brak_js': 'Przyciski potrzebują JavaScriptu. Sama tabela jest rysowana na serwerze, więc jest '
	           'tu tak czy inaczej.',
}


def cena( wartosc, jezyk ):
	"""Cena tak, jak pokazałby ją arkusz — i tak, jak trzeba ją potem odczytać przy sortowaniu."""

	if 'pl' == jezyk:
		calosc = '{:,.2f}'.format( wartosc ).replace( ',', ' ' ).replace( '.', ',' )
		return calosc + '&nbsp;zł'

	return '$' + '{:,.2f}'.format( wartosc )


def wiersze( t ):
	kawalki = []

	for nazwa, kategoria, kwota, stan, data in TOWARY:
		kawalki.append(
			'\t\t\t\t\t<tr data-kategoria="' + kategoria + '" data-stan="' + stan + '">'
			'<td><span class="lst-pok-etykieta">' + t[ 'kolumny' ][ 0 ][ 0 ] + '</span>'
			'<span class="lst-pok-wartosc">' + nazwa + '</span></td>'
			'<td><span class="lst-pok-etykieta">' + t[ 'kolumny' ][ 1 ][ 0 ] + '</span>'
			'<span class="lst-pok-wartosc">' + t[ 'kategorie' ][ kategoria ] + '</span></td>'
			'<td class="lst-pok-do-prawej"><span class="lst-pok-etykieta">' + t[ 'kolumny' ][ 2 ][ 0 ] +
			'</span><span class="lst-pok-wartosc">' + cena( kwota, t[ 'lang' ] ) + '</span></td>'
			'<td class="lst-pok-stala"><span class="lst-pok-etykieta">' + t[ 'kolumny' ][ 3 ][ 0 ] +
			'</span><span class="lst-pok-znacznik lst-pok-stan-' + stan + '">' +
			t[ 'stany' ][ stan ] + '</span></td>'
			'<td><span class="lst-pok-etykieta">' + t[ 'kolumny' ][ 4 ][ 0 ] + '</span>'
			'<span class="lst-pok-wartosc lst-pok-mono">' + data + '</span></td></tr>' )

	return '\n'.join( kawalki )


def naglowki( t ):
	kawalki = []

	for nr, ( nazwa, rodzaj ) in enumerate( t[ 'kolumny' ] ):
		klasa = ' class="lst-pok-do-prawej"' if 'liczba' == rodzaj else ''
		kawalki.append(
			'<th' + klasa + ' data-kolumna="' + str( nr ) + '" data-rodzaj="' + rodzaj +
			'" scope="col"><button type="button" class="lst-pok-sort">' + nazwa +
			'<span class="lst-pok-strzalka" aria-hidden="true"></span></button></th>' )

	return ''.join( kawalki )


def filtry( t ):
	kawalki = [ '<button type="button" class="lst-pok-filtr jest-wybrany" data-filtr="">' +
	            t[ 'wszystkie' ] + '</button>' ]

	for klucz, nazwa in t[ 'kategorie' ].items():
		kawalki.append( '<button type="button" class="lst-pok-filtr" data-filtr="' + klucz + '">' +
			nazwa + '</button>' )

	return ''.join( kawalki )


def przelaczniki( t ):
	kawalki = []

	for klucz, nazwa, opis in t[ 'pro' ]:
		kawalki.append(
			'\t\t\t<button type="button" class="lst-pok-pro" data-pro="' + klucz + '" aria-pressed="false">'
			'<span class="lst-pok-dioda" aria-hidden="true"></span>'
			'<span class="lst-pok-pro-tresc"><span class="lst-pok-pro-nazwa">' + nazwa +
			'<span class="lst-pok-plakietka">Pro</span></span>'
			'<span class="lst-pok-pro-opis">' + opis + '</span></span></button>' )

	return '\n'.join( kawalki )


def szerokosci( t ):
	kawalki = []

	for nr, ( klucz, nazwa ) in enumerate( t[ 'szer' ] ):
		wybrany = ' jest-wybrany' if 0 == nr else ''
		kawalki.append( '<button type="button" class="lst-pok-szer' + wybrany + '" data-szer="' +
			klucz + '" aria-pressed="' + ( 'true' if 0 == nr else 'false' ) + '">' + nazwa + '</button>' )

	return ''.join( kawalki )


SZABLON = r'''<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">

<div class="lst-pok">
	<div class="lst-pok-rama">
		<p class="lst-pok-wstep">{WSTEP}</p>
		<div class="lst-pok-gora">
			<label class="lst-pok-szukaj"><span class="lst-pok-czytnik">{SZUKAJ}</span><input type="search" placeholder="{SZUKAJ}" autocomplete="off"><button type="button" class="lst-pok-kasuj" hidden>{WYCZYSC}</button></label>
			<span class="lst-pok-szerokosci"><span class="lst-pok-podpis">{SZEROKOSC}</span>{SZEROKOSCI}</span>
		</div>
		<div class="lst-pok-filtry" hidden>{FILTRY}</div>
		<div class="lst-pok-scena">
			<div class="lst-pok-pudlo">
				<div class="lst-pok-przewijak">
					<table class="lst-pok-tabela">
						<thead><tr>{NAGLOWKI}</tr></thead>
						<tbody>
{WIERSZE}
						</tbody>
					</table>
				</div>
				<p class="lst-pok-pusto" hidden>{NIC}</p>
				<div class="lst-pok-dol">
					<span class="lst-pok-licznik"><span class="lst-pok-kropka" aria-hidden="true"></span><span class="lst-pok-ile">{ILE} {WIERSZY}</span><span class="lst-pok-swiezosc">{SWIEZOSC}</span></span>
					<span class="lst-pok-pobieranie" hidden><button type="button" class="lst-pok-csv">{CSV}</button></span>
					<span class="lst-pok-strony"><button type="button" class="lst-pok-strona" data-krok="-1">{POPRZEDNIA}</button><span class="lst-pok-ktora">1 {Z} 3</span><button type="button" class="lst-pok-strona" data-krok="1">{NASTEPNA}</button></span>
				</div>
			</div>
		</div>
		<p class="lst-pok-kod"><span class="lst-pok-mono">{KOD}</span></p>
		<p class="lst-pok-pro-tytul">{PRO_TYTUL}</p>
		<div class="lst-pok-przelaczniki">
{PRZELACZNIKI}
		</div>
	</div>
</div>

<style>
.lst-pok {
	--lst-mieta: 95, 227, 207;
	--lst-panel: #1b2221;
	--lst-panel-dol: #161d1c;
	--lst-panel-glowa: #1f2725;
	--lst-linia: rgba( 138, 168, 163, .22 );
	--lst-kreska: rgba( 138, 168, 163, .38 );
	--lst-tekst: #eaf3f1;
	--lst-tekst-2: #b3c6c3;
	--lst-tekst-3: #8fa5a2;
	--lst-bursztyn: 232, 176, 92;
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

.lst-pok.lst-pok { border: 0 !important; outline: 0 !important; background: none !important; }
.lst-pok * { box-sizing: border-box; }
.lst-pok [ hidden ] { display: none !important; }
.lst-pok br { display: none; }

/* Reset musi stac PRZED reszta regul — ma wage zero i inaczej zabralby
   nagłówkom wersaliki, a przyciskom krój. */
.lst-pok :where( div, p, span, button, input, label, table, thead, tbody, tr, th, td ) {
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
	appearance: none;
}

.lst-pok .lst-pok-rama {
	width: var( --lst-szerokosc );
	max-width: var( --lst-max );
	margin-inline: auto;
}

.lst-pok .lst-pok-czytnik { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset( 50% ); }
.lst-pok .lst-pok-mono { font-family: var( --lst-mono ); }

.lst-pok .lst-pok-wstep {
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.55;
	color: var( --lst-tekst-2 );
	max-width: 46rem;
	margin-bottom: 1.5rem;
}

/* ---------- pasek nad tabelą ---------- */

.lst-pok .lst-pok-gora {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: .8rem 1.2rem;
	margin-bottom: .9rem;
}

.lst-pok .lst-pok-szukaj { position: relative; display: flex; align-items: center; flex: 1 1 18rem; max-width: 26rem; }

.lst-pok .lst-pok-szukaj input {
	width: 100%;
	padding: .7rem 5rem .7rem 1rem;
	font-size: 1.125rem;   /* 18 px */
	color: var( --lst-tekst );
	background-color: rgba( 13, 18, 17, .62 );
	border: 1px solid var( --lst-linia ) !important;
	border-radius: 10px;
}

.lst-pok .lst-pok-szukaj input::placeholder { color: var( --lst-tekst-3 ); opacity: 1; }
.lst-pok .lst-pok-szukaj input:focus-visible { outline: 2px solid rgb( var( --lst-mieta ) ) !important; outline-offset: 2px; }

.lst-pok .lst-pok-kasuj {
	position: absolute;
	right: .5rem;
	padding: .3rem .6rem;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	color: var( --lst-tekst-3 );
	border: 1px solid var( --lst-linia ) !important;
	border-radius: 7px;
	cursor: pointer;
}

.lst-pok .lst-pok-kasuj:hover { color: var( --lst-tekst ); border-color: var( --lst-kreska ) !important; }

.lst-pok .lst-pok-szerokosci { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }

.lst-pok .lst-pok-podpis {
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	letter-spacing: .1em;
	text-transform: uppercase;
	color: var( --lst-tekst-3 );
	margin-right: .3rem;
}

.lst-pok .lst-pok-szer,
.lst-pok .lst-pok-filtr {
	padding: .5rem .85rem;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	color: var( --lst-tekst-2 );
	background-color: rgba( 13, 18, 17, .5 );
	border: 1px solid var( --lst-linia ) !important;
	border-radius: 9px;
	cursor: pointer;
	transition: color .18s ease, border-color .18s ease, background-color .18s ease;
}

.lst-pok .lst-pok-szer:hover,
.lst-pok .lst-pok-filtr:hover { color: var( --lst-tekst ); border-color: var( --lst-kreska ) !important; }

.lst-pok .lst-pok-szer.jest-wybrany,
.lst-pok .lst-pok-filtr.jest-wybrany {
	color: #06100f;
	background-color: rgb( var( --lst-mieta ) );
	border-color: rgb( var( --lst-mieta ) ) !important;
}

.lst-pok .lst-pok-filtry { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .9rem; }

/* ---------- scena: to ona udaje szerokość ekranu ---------- */

.lst-pok .lst-pok-scena {
	container-type: inline-size;
	container-name: lstpok;
	width: 100%;
	max-width: 100%;
	transition: width .35s ease;
}

.lst-pok .lst-pok-scena[ data-szer="sredni" ] { width: 760px; }
.lst-pok .lst-pok-scena[ data-szer="waski" ] { width: 390px; }

.lst-pok .lst-pok-pudlo {
	background-color: var( --lst-panel ) !important;
	background-image: linear-gradient( var( --lst-panel ), var( --lst-panel-dol ) ) !important;
	border: 1px solid var( --lst-linia ) !important;
	border-radius: 14px;
	overflow: hidden;
	box-shadow: 0 22px 46px -32px rgba( 0, 0, 0, .9 );
}

.lst-pok .lst-pok-przewijak { overflow-x: auto; }

.lst-pok .lst-pok-tabela { width: 100%; border-collapse: collapse; }

.lst-pok .lst-pok-tabela th {
	background-color: var( --lst-panel-glowa ) !important;
	border-bottom: 1px solid var( --lst-kreska ) !important;
	white-space: nowrap;
}

.lst-pok .lst-pok-sort {
	display: inline-flex;
	align-items: center;
	gap: .4rem;
	width: 100%;
	padding: .8rem 1rem;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	letter-spacing: .1em;
	text-transform: uppercase;
	color: var( --lst-tekst-3 );
	cursor: pointer;
	transition: color .18s ease;
}

.lst-pok .lst-pok-do-prawej .lst-pok-sort { justify-content: flex-end; }
.lst-pok .lst-pok-sort:hover { color: var( --lst-tekst ); }
.lst-pok .lst-pok-sort:focus-visible { outline: 2px solid rgb( var( --lst-mieta ) ) !important; outline-offset: -2px; }

.lst-pok .lst-pok-strzalka { width: .6rem; height: .6rem; opacity: 0; transition: opacity .18s ease; }

.lst-pok th[ data-kierunek ] .lst-pok-sort { color: rgb( var( --lst-mieta ) ); }
.lst-pok th[ data-kierunek ] .lst-pok-strzalka { opacity: 1; }

.lst-pok .lst-pok-strzalka::before { content: "\2191"; }
.lst-pok th[ data-kierunek="malejaco" ] .lst-pok-strzalka::before { content: "\2193"; }

.lst-pok .lst-pok-tabela td {
	padding: .85rem 1rem;
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.4;
	border-top: 1px solid var( --lst-linia ) !important;
	vertical-align: middle;
	/* Motyw, który pisze "td { background: #ff0 !important }", wygrywał z resetem
	   :where() — ten ma wagę zero. Tu przebijamy to wprost. Reguły Pro i podświetlenie
	   wiersza stoją NIŻEJ w pliku i mają wyższą wagę, więc dalej działają. */
	background-color: transparent !important;
	text-transform: none !important;
	letter-spacing: normal !important;
	margin: 0 !important;
}

.lst-pok .lst-pok-tabela tbody tr:first-child td { border-top: 0 !important; }
.lst-pok .lst-pok-do-prawej { text-align: right; }
.lst-pok .lst-pok-tabela tbody tr:hover td { background-color: rgba( 95, 227, 207, .05 ) !important; }

.lst-pok .lst-pok-etykieta { display: none; }

.lst-pok .lst-pok-znacznik {
	display: inline-block;
	padding: .25rem .6rem;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	color: var( --lst-tekst-2 );
	border: 1px solid var( --lst-linia ) !important;
	border-radius: 999px;
	white-space: nowrap;
}

.lst-pok mark {
	padding: 0 .12em;
	color: #06100f;
	background-color: rgb( var( --lst-mieta ) ) !important;
	border-radius: 3px;
}

/* ---------- reguły Pro: bursztyn na brakach ---------- */

.lst-pok .lst-pok-pudlo[ data-reguly="1" ] tbody tr[ data-stan="brak" ] td {
	background-color: rgba( var( --lst-bursztyn ), .1 ) !important;
}

.lst-pok .lst-pok-pudlo[ data-reguly="1" ] tbody tr[ data-stan="brak" ] .lst-pok-znacznik {
	color: rgb( var( --lst-bursztyn ) );
	border-color: rgba( var( --lst-bursztyn ), .5 ) !important;
	background-color: rgba( var( --lst-bursztyn ), .12 ) !important;
}

/* ---------- pasek pod tabelą ---------- */

.lst-pok .lst-pok-pusto {
	padding: 1.6rem 1rem;
	font-size: 1.125rem;   /* 18 px */
	color: var( --lst-tekst-3 );
	text-align: center;
}

.lst-pok .lst-pok-dol {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: .7rem 1rem;
	padding: .85rem 1rem;
	border-top: 1px solid var( --lst-kreska ) !important;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	color: var( --lst-tekst-3 );
}

.lst-pok .lst-pok-licznik { display: inline-flex; align-items: center; gap: .6rem; flex-wrap: wrap; }

.lst-pok .lst-pok-swiezosc::before { content: "\00b7"; margin-right: .6rem; opacity: .7; }

.lst-pok .lst-pok-kropka {
	width: 7px;
	height: 7px;
	border-radius: 999px;
	background-color: rgb( var( --lst-mieta ) ) !important;
	box-shadow: 0 0 10px -1px rgba( var( --lst-mieta ), .8 );
	flex: none;
}

.lst-pok .lst-pok-strony { display: inline-flex; align-items: center; gap: .45rem; }

.lst-pok .lst-pok-strona,
.lst-pok .lst-pok-csv {
	padding: .4rem .75rem;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	color: var( --lst-tekst-2 );
	border: 1px solid var( --lst-linia ) !important;
	border-radius: 8px;
	cursor: pointer;
	transition: color .18s ease, border-color .18s ease;
}

.lst-pok .lst-pok-strona:hover:not( :disabled ),
.lst-pok .lst-pok-csv:hover { color: var( --lst-tekst ); border-color: var( --lst-kreska ) !important; }
.lst-pok .lst-pok-strona:disabled { opacity: .38; cursor: default; }
.lst-pok .lst-pok-ktora { min-width: 4.5rem; text-align: center; }

.lst-pok .lst-pok-kod { margin-top: 1rem; font-size: .875rem;   /* 14 px */ color: var( --lst-tekst-3 ); }
.lst-pok .lst-pok-kod .lst-pok-mono { padding: .3rem .6rem; border: 1px solid var( --lst-linia ) !important; border-radius: 7px; }

/* ---------- przełączniki Pro ---------- */

.lst-pok .lst-pok-pro-tytul {
	margin-top: 2.4rem;
	margin-bottom: .9rem;
	font-size: 1.125rem;   /* 18 px */
	color: var( --lst-tekst-2 );
	max-width: 46rem;
}

.lst-pok .lst-pok-przelaczniki { display: grid; grid-template-columns: repeat( 3, 1fr ); gap: .9rem; }

.lst-pok .lst-pok-pro {
	display: flex;
	align-items: flex-start;
	gap: .8rem;
	padding: 1.05rem 1.2rem 1.15rem;
	text-align: left;
	background-color: rgba( 13, 18, 17, .62 ) !important;
	border: 1px solid var( --lst-linia ) !important;
	border-radius: 14px;
	cursor: pointer;
	transition: border-color .2s ease, transform .2s ease;
}

.lst-pok .lst-pok-pro:hover { border-color: rgba( var( --lst-mieta ), .4 ) !important; transform: translateY( -2px ); }
.lst-pok .lst-pok-pro:focus-visible { outline: 2px solid rgb( var( --lst-mieta ) ) !important; outline-offset: 3px; }

.lst-pok .lst-pok-dioda {
	width: 12px;
	height: 12px;
	margin-top: .35rem;
	border-radius: 999px;
	background-color: rgba( 138, 168, 163, .25 ) !important;
	flex: none;
	transition: background-color .2s ease, box-shadow .2s ease;
}

.lst-pok .lst-pok-pro[ aria-pressed="true" ] { border-color: rgba( var( --lst-mieta ), .55 ) !important; }

.lst-pok .lst-pok-pro[ aria-pressed="true" ] .lst-pok-dioda {
	background-color: rgb( var( --lst-mieta ) ) !important;
	box-shadow: 0 0 14px -1px rgba( var( --lst-mieta ), .85 );
}

.lst-pok .lst-pok-pro-tresc { display: block; }
.lst-pok .lst-pok-pro-nazwa { display: block; font-size: 1.125rem;   /* 18 px */ font-weight: 600; margin-bottom: .25rem; }
.lst-pok .lst-pok-pro-opis { display: block; font-size: 1.125rem;   /* 18 px */ line-height: 1.45; color: var( --lst-tekst-2 ); }

.lst-pok .lst-pok-plakietka {
	display: inline-block;
	margin-left: .5rem;
	padding: .1rem .45rem;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	font-weight: 400;
	color: #06100f;
	background-color: rgb( var( --lst-mieta ) ) !important;
	border-radius: 5px;
	vertical-align: .1em;
}

/* ---------- wąsko: tabela składa się w karty ---------- */

/*
 * To samo zachowanie ma prawdziwa wtyczka — decyduje szerokość pojemnika,
 * a nie szerokość okna. Dlatego przełącznik u góry naprawdę to pokazuje,
 * a nie udaje.
 */
@container lstpok ( max-width: 640px ) {
	.lst-pok .lst-pok-tabela thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset( 50% ); }
	.lst-pok .lst-pok-tabela,
	.lst-pok .lst-pok-tabela tbody,
	.lst-pok .lst-pok-tabela tr,
	.lst-pok .lst-pok-tabela td { display: block; width: auto; }

	.lst-pok .lst-pok-tabela tr { padding: .35rem 0; border-top: 1px solid var( --lst-kreska ) !important; }
	.lst-pok .lst-pok-tabela tbody tr:first-child { border-top: 0 !important; }

	.lst-pok .lst-pok-tabela td {
		display: flex;
		align-items: baseline;
		justify-content: space-between;
		gap: 1rem;
		padding: .4rem 1rem;
		text-align: left;
		border-top: 0 !important;
	}

	.lst-pok .lst-pok-etykieta {
		display: block;
		font-family: var( --lst-mono );
		font-size: .875rem;   /* 14 px */
		letter-spacing: .08em;
		text-transform: uppercase;
		color: var( --lst-tekst-3 );
		flex: none;
	}

	.lst-pok .lst-pok-wartosc { text-align: right; }
	.lst-pok .lst-pok-tabela tbody tr:hover td { background: none !important; }
	.lst-pok .lst-pok-tabela tr:hover { background-color: rgba( 95, 227, 207, .05 ) !important; }
}

/* ---------- utwardzenie na wrogie motywy ---------- */

.lst-pok .lst-pok-wstep,
.lst-pok .lst-pok-pro-tytul,
.lst-pok .lst-pok-kod,
.lst-pok .lst-pok-pusto {
	margin-inline: 0 !important;
	background: none !important;
	border: 0 !important;
	text-align: left !important;
	text-transform: none !important;
	letter-spacing: normal !important;
	font-family: inherit !important;
	color: var( --lst-tekst-2 ) !important;
}

.lst-pok .lst-pok-pusto { text-align: center !important; color: var( --lst-tekst-3 ) !important; }
.lst-pok .lst-pok-kod { color: var( --lst-tekst-3 ) !important; }

.lst-pok .lst-pok-sort,
.lst-pok .lst-pok-etykieta,
.lst-pok .lst-pok-podpis { text-transform: uppercase !important; font-family: var( --lst-mono ) !important; }

.lst-pok .lst-pok-szer,
.lst-pok .lst-pok-filtr,
.lst-pok .lst-pok-strona,
.lst-pok .lst-pok-csv,
.lst-pok .lst-pok-kasuj,
.lst-pok .lst-pok-dol,
.lst-pok .lst-pok-znacznik,
.lst-pok .lst-pok-plakietka { font-family: var( --lst-mono ) !important; text-transform: none !important; letter-spacing: normal !important; }

.lst-pok .lst-pok-tabela td { font-family: inherit !important; color: var( --lst-tekst ) !important; }
.lst-pok .lst-pok-pro-opis { font-family: inherit !important; color: var( --lst-tekst-2 ) !important; }

.et_pb_module:has( .lst-pok ),
.et_pb_column:has( .lst-pok ),
.et_pb_row:has( .lst-pok ) { border: 0 !important; outline: 0 !important; }

@media ( max-width: 900px ) {
	.lst-pok .lst-pok-przelaczniki { grid-template-columns: 1fr; }
	.lst-pok .lst-pok-scena[ data-szer="sredni" ],
	.lst-pok .lst-pok-scena[ data-szer="waski" ] { width: 100%; }
	.lst-pok .lst-pok-szerokosci { display: none; }
}

@media ( prefers-reduced-motion: reduce ) {
	.lst-pok .lst-pok-scena,
	.lst-pok .lst-pok-pro,
	.lst-pok .lst-pok-szer,
	.lst-pok .lst-pok-filtr { transition: none; }
}
</style>
'''


# Skrypt jest osobno, bo w nim nawiasy kwadratowe i słowo "script" są w porządku —
# sprawdzarka patrzy tylko na znacznikowanie przed <style>.
SKRYPT = r'''<script>
( function () {
	"use strict";

	var NA_STRONIE = 5;
	var SLOWA = {WYRAZY};

	/* "1 rows" wygląda jak usterka. Polski ma trzy formy: 1 wiersz,
	   2-4 wiersze, 5+ wierszy — z wyjątkiem nastek, które idą do trzeciej. */
	function odmien( ile ) {
		if ( 1 === ile ) { return SLOWA.odmiana[ 0 ]; }

		var ostatnia = ile % 10;
		var dwie = ile % 100;

		if ( ostatnia >= 2 && ostatnia <= 4 && ( dwie < 12 || dwie > 14 ) ) {
			return SLOWA.odmiana[ 1 ];
		}

		return SLOWA.odmiana[ 2 ];
	}

	function bezOgonkow( tekst ) {
		return tekst.toLowerCase().normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' );
	}

	/* Cena tak, jak ją widzi człowiek: "$1,215.50" albo "1 215,50 zl".
	   Odcinamy wszystko poza cyframi i separatorami, potem zgadujemy,
	   który znak jest przecinkiem dziesiętnym. */
	function naLiczbe( tekst ) {
		var czysty = tekst.replace( /[^0-9.,-]/g, '' );

		if ( '' === czysty ) { return null; }

		var przecinek = czysty.lastIndexOf( ',' );
		var kropka = czysty.lastIndexOf( '.' );

		if ( przecinek > kropka ) {
			czysty = czysty.replace( /\./g, '' ).replace( ',', '.' );
		} else {
			czysty = czysty.replace( /,/g, '' );
		}

		var liczba = parseFloat( czysty );

		return isNaN( liczba ) ? null : liczba;
	}

	function uruchom() {
		var korzen = document.querySelector( '.lst-pok' );

		if ( ! korzen || korzen.getAttribute( 'data-gotowy' ) ) { return; }

		korzen.setAttribute( 'data-gotowy', '1' );

		var pudlo = korzen.querySelector( '.lst-pok-pudlo' );
		var scena = korzen.querySelector( '.lst-pok-scena' );
		var cialo = korzen.querySelector( '.lst-pok-tabela tbody' );
		var wszystkie = [].slice.call( cialo.querySelectorAll( 'tr' ) );
		var kolejnosc = wszystkie.slice();
		var szukaj = korzen.querySelector( '.lst-pok-szukaj input' );
		var kasuj = korzen.querySelector( '.lst-pok-kasuj' );
		var pusto = korzen.querySelector( '.lst-pok-pusto' );
		var ile = korzen.querySelector( '.lst-pok-ile' );
		var ktora = korzen.querySelector( '.lst-pok-ktora' );
		var pasekFiltrow = korzen.querySelector( '.lst-pok-filtry' );
		var pobieranie = korzen.querySelector( '.lst-pok-pobieranie' );
		var glowy = [].slice.call( korzen.querySelectorAll( '.lst-pok-tabela th' ) );

		var stan = { fraza: '', kategoria: '', strona: 1 };

		/* Oryginalny tekst komórek zapamiętujemy raz. Podświetlanie przepisuje
		   zawartość, więc bez tego druga zmiana frazy szukałaby już w <mark>. */
		wszystkie.forEach( function ( wiersz ) {
			[].forEach.call( wiersz.cells, function ( komorka ) {
				var wartosc = komorka.querySelector( '.lst-pok-wartosc' );

				if ( wartosc ) { wartosc.setAttribute( 'data-tresc', wartosc.textContent ); }
			} );
		} );

		/* Sama wartość, bez ukrytej nazwy pola, którą komórka nosi na potrzeby
		   układu kart. Inaczej "price" pasowałoby do każdego wiersza. */
		function tekstKomorki( komorka ) {
			var w = komorka.querySelector( '.lst-pok-wartosc' ) || komorka.querySelector( '.lst-pok-znacznik' );

			return ( w ? w.textContent : komorka.textContent ).trim();
		}

		function nazwaKolumny( glowa ) {
			var przycisk = glowa.querySelector( '.lst-pok-sort' );

			return przycisk ? ( przycisk.firstChild ? przycisk.firstChild.nodeValue || '' : '' ).trim() : '';
		}

		function tekstWiersza( wiersz ) {
			return [].map.call( wiersz.cells, tekstKomorki ).join( ' ' );
		}

		function pasuje( wiersz ) {
			if ( stan.kategoria && wiersz.getAttribute( 'data-kategoria' ) !== stan.kategoria ) {
				return false;
			}

			if ( ! stan.fraza ) { return true; }

			return bezOgonkow( tekstWiersza( wiersz ) ).indexOf( stan.fraza ) !== -1;
		}

		function odswiezKomorki( wiersz ) {
			[].forEach.call( wiersz.cells, function ( komorka ) {
				var wartosc = komorka.querySelector( '.lst-pok-wartosc' );

				if ( wartosc && wartosc.querySelector( 'mark' ) ) {
					wartosc.textContent = wartosc.getAttribute( 'data-tresc' ) || '';
				}
			} );
		}

		function podswietl( wiersz ) {
			[].forEach.call( wiersz.cells, function ( komorka ) {
				var wartosc = komorka.querySelector( '.lst-pok-wartosc' );

				if ( ! wartosc ) { return; }

				var tresc = wartosc.getAttribute( 'data-tresc' ) || '';

				if ( ! stan.fraza ) { wartosc.textContent = tresc; return; }

				var plaski = bezOgonkow( tresc );
				var gdzie = plaski.indexOf( stan.fraza );

				if ( gdzie === -1 ) { wartosc.textContent = tresc; return; }

				wartosc.textContent = '';
				var koniec = gdzie + stan.fraza.length;
				wartosc.appendChild( document.createTextNode( tresc.slice( 0, gdzie ) ) );
				var znak = document.createElement( 'mark' );
				znak.textContent = tresc.slice( gdzie, koniec );
				wartosc.appendChild( znak );
				wartosc.appendChild( document.createTextNode( tresc.slice( koniec ) ) );
			} );
		}

		function odswiez() {
			var widoczne = kolejnosc.filter( pasuje );
			var stron = Math.max( 1, Math.ceil( widoczne.length / NA_STRONIE ) );

			if ( stan.strona > stron ) { stan.strona = stron; }
			if ( stan.strona < 1 ) { stan.strona = 1; }

			var od = ( stan.strona - 1 ) * NA_STRONIE;

			/* Podświetlenie zdejmujemy ze WSZYSTKICH wierszy, nie tylko z widocznych.
			   Inaczej wiersz, który po zmianie frazy zszedł na inną stronę, wracał
			   z nieaktualnym <mark> w środku. */
			wszystkie.forEach( function ( wiersz ) { wiersz.style.display = 'none'; } );

			var naStronie = widoczne.slice( od, od + NA_STRONIE );

			wszystkie.forEach( function ( wiersz ) {
				if ( naStronie.indexOf( wiersz ) === -1 ) { odswiezKomorki( wiersz ); }
			} );

			naStronie.forEach( function ( wiersz ) {
				wiersz.style.display = '';
				podswietl( wiersz );
				cialo.appendChild( wiersz );
			} );

			/* Wiersze schowane też przestawiamy, żeby kolejność po sortowaniu
			   była prawdziwa, a nie tylko na widocznej stronie. */
			kolejnosc.forEach( function ( wiersz ) {
				if ( 'none' === wiersz.style.display ) { cialo.appendChild( wiersz ); }
			} );

			pusto.hidden = widoczne.length !== 0;
			ile.textContent = widoczne.length + ' ' + odmien( widoczne.length );
			ktora.textContent = stan.strona + ' ' + SLOWA.z + ' ' + stron;

			korzen.querySelectorAll( '.lst-pok-strona' ).forEach( function ( przycisk ) {
				var krok = parseInt( przycisk.getAttribute( 'data-krok' ), 10 );
				przycisk.disabled = ( krok < 0 && stan.strona === 1 ) || ( krok > 0 && stan.strona === stron );
			} );

			kasuj.hidden = '' === szukaj.value;
		}

		function sortuj( glowa ) {
			var nr = parseInt( glowa.getAttribute( 'data-kolumna' ), 10 );
			var rodzaj = glowa.getAttribute( 'data-rodzaj' );
			var byl = glowa.getAttribute( 'data-kierunek' );
			var wDol = 'rosnaco' === byl;

			glowy.forEach( function ( g ) { g.removeAttribute( 'data-kierunek' ); } );
			glowa.setAttribute( 'data-kierunek', wDol ? 'malejaco' : 'rosnaco' );

			kolejnosc = kolejnosc.slice().sort( function ( a, b ) {
				var x = tekstKomorki( a.cells[ nr ] );
				var y = tekstKomorki( b.cells[ nr ] );
				var wynik;

				if ( 'liczba' === rodzaj ) {
					var lx = naLiczbe( x );
					var ly = naLiczbe( y );
					wynik = ( null === lx ? -Infinity : lx ) - ( null === ly ? -Infinity : ly );
				} else {
					wynik = bezOgonkow( x ).localeCompare( bezOgonkow( y ) );
				}

				return wDol ? -wynik : wynik;
			} );

			stan.strona = 1;
			odswiez();
		}

		glowy.forEach( function ( glowa ) {
			glowa.querySelector( '.lst-pok-sort' ).addEventListener( 'click', function () { sortuj( glowa ); } );
		} );

		szukaj.addEventListener( 'input', function () {
			stan.fraza = bezOgonkow( szukaj.value.trim() );
			stan.strona = 1;
			odswiez();
		} );

		kasuj.addEventListener( 'click', function () {
			szukaj.value = '';
			stan.fraza = '';
			stan.strona = 1;
			odswiez();
			szukaj.focus();
		} );

		korzen.querySelectorAll( '.lst-pok-strona' ).forEach( function ( przycisk ) {
			przycisk.addEventListener( 'click', function () {
				stan.strona += parseInt( przycisk.getAttribute( 'data-krok' ), 10 );
				odswiez();
			} );
		} );

		korzen.querySelectorAll( '.lst-pok-szer' ).forEach( function ( przycisk ) {
			przycisk.addEventListener( 'click', function () {
				korzen.querySelectorAll( '.lst-pok-szer' ).forEach( function ( inny ) {
					inny.classList.remove( 'jest-wybrany' );
					inny.setAttribute( 'aria-pressed', 'false' );
				} );

				przycisk.classList.add( 'jest-wybrany' );
				przycisk.setAttribute( 'aria-pressed', 'true' );
				scena.setAttribute( 'data-szer', przycisk.getAttribute( 'data-szer' ) );
			} );
		} );

		korzen.querySelectorAll( '.lst-pok-filtr' ).forEach( function ( przycisk ) {
			przycisk.addEventListener( 'click', function () {
				korzen.querySelectorAll( '.lst-pok-filtr' ).forEach( function ( inny ) {
					inny.classList.remove( 'jest-wybrany' );
				} );

				przycisk.classList.add( 'jest-wybrany' );
				stan.kategoria = przycisk.getAttribute( 'data-filtr' );
				stan.strona = 1;
				odswiez();
			} );
		} );

		korzen.querySelectorAll( '.lst-pok-pro' ).forEach( function ( przycisk ) {
			przycisk.addEventListener( 'click', function () {
				var wlaczony = 'true' !== przycisk.getAttribute( 'aria-pressed' );
				przycisk.setAttribute( 'aria-pressed', wlaczony ? 'true' : 'false' );
				var co = przycisk.getAttribute( 'data-pro' );

				if ( 'reguly' === co ) {
					pudlo.setAttribute( 'data-reguly', wlaczony ? '1' : '0' );
				}

				if ( 'filtry' === co ) {
					pasekFiltrow.hidden = ! wlaczony;

					if ( ! wlaczony ) {
						stan.kategoria = '';
						korzen.querySelectorAll( '.lst-pok-filtr' ).forEach( function ( f, nr ) {
							f.classList.toggle( 'jest-wybrany', 0 === nr );
						} );
						odswiez();
					}
				}

				if ( 'pobierz' === co ) { pobieranie.hidden = ! wlaczony; }
			} );
		} );

		korzen.querySelector( '.lst-pok-csv' ).addEventListener( 'click', function () {
			var linie = [ glowy.map( nazwaKolumny ) ];

			kolejnosc.filter( pasuje ).forEach( function ( wiersz ) {
				linie.push( [].map.call( wiersz.cells, tekstKomorki ) );
			} );

			var tekst = linie.map( function ( linia ) {
				return linia.map( function ( pole ) { return '"' + pole.replace( /"/g, '""' ) + '"'; } ).join( ',' );
			} ).join( '\n' );

			var plik = new Blob( [ '﻿' + tekst ], { type: 'text/csv;charset=utf-8' } );
			var adres = URL.createObjectURL( plik );
			var odnosnik = document.createElement( 'a' );
			odnosnik.href = adres;
			odnosnik.download = SLOWA.plik;
			document.body.appendChild( odnosnik );
			odnosnik.click();
			document.body.removeChild( odnosnik );
			setTimeout( function () { URL.revokeObjectURL( adres ); }, 1000 );
		} );

		odswiez();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', uruchom );
	} else {
		uruchom();
	}
} )();
</script>
'''


def zbuduj( t, plik ):
	slowa = ( '{ odmiana: [ "' + '", "'.join( t[ 'odmiana' ] ) + '" ], z: "' + t[ 'z' ] +
	          '", plik: "' + t[ 'plik' ] + '" }' )

	html = ( SZABLON
		.replace( '{WSTEP}', t[ 'wstep' ] )
		.replace( '{SZUKAJ}', t[ 'szukaj' ] )
		.replace( '{WYCZYSC}', t[ 'wyczysc' ] )
		.replace( '{SZEROKOSCI}', szerokosci( t ) )
		.replace( '{SZEROKOSC}', t[ 'szerokosc' ] )
		.replace( '{FILTRY}', filtry( t ) )
		.replace( '{NAGLOWKI}', naglowki( t ) )
		.replace( '{WIERSZE}', wiersze( t ) )
		.replace( '{NIC}', t[ 'nic' ] )
		.replace( '{ILE}', str( len( TOWARY ) ) )
		.replace( '{WIERSZY}', t[ 'wierszy' ] )
		.replace( '{SWIEZOSC}', t[ 'swiezosc' ] )
		.replace( '{CSV}', t[ 'csv' ] )
		.replace( '{POPRZEDNIA}', t[ 'poprzednia' ] )
		.replace( '{NASTEPNA}', t[ 'nastepna' ] )
		.replace( '{Z}', t[ 'z' ] )
		.replace( '{KOD}', t[ 'kod' ] )
		.replace( '{PRO_TYTUL}', t[ 'pro_tytul' ] )
		.replace( '{PRZELACZNIKI}', przelaczniki( t ) )
		+ SKRYPT.replace( '{WYRAZY}', slowa ) )

	sprawdz( html, plik )

	with open( plik, 'w', encoding='utf-8' ) as f:
		f.write( html )

	print( plik + ' — wierszy: ' + str( len( TOWARY ) ) + ', linii: ' + str( len( html.split( chr( 10 ) ) ) ) )
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

	if styl.index( ':where(' ) > styl.index( '.lst-pok .lst-pok-rama' ):
		raise SystemExit( plik + ': reset :where() stoi po regułach' )

	for musi in ( '.lst-pok br { display: none; }', 'prefers-reduced-motion',
	              'container-name: lstpok', '.et_pb_module:has( .lst-pok )' ):
		if musi not in html:
			raise SystemExit( plik + ': brakuje ' + musi )

	otwarte = re.findall( r'<(\w+)(?:\s[^>]*)?>', znacznikowanie )
	zamkniete = re.findall( r'</(\w+)>', znacznikowanie )

	for znacznik in set( otwarte ):
		if znacznik in ( 'br', 'link', 'input' ):
			continue

		if otwarte.count( znacznik ) != zamkniete.count( znacznik ):
			raise SystemExit( plik + ': <' + znacznik + '> otwarty ' + str( otwarte.count( znacznik ) ) +
				', zamknięty ' + str( zamkniete.count( znacznik ) ) )


def podglad( html ):
	strona = ( '<!doctype html>\n<html lang="pl">\n<head>\n<meta charset="utf-8">\n'
		'<meta name="viewport" content="width=device-width,initial-scale=1">\n'
		'<title>Pokaz wtyczki</title>\n'
		'<style>\nhtml, body { margin: 0; background: #232a29; color: #eaf3f1;\n'
		'\tfont-family: system-ui, sans-serif;\n'
		'\tbackground-image: linear-gradient( to right, rgba( 255, 255, 255, .055 ) 1px, transparent 1px ),\n'
		'\t\tlinear-gradient( to bottom, rgba( 255, 255, 255, .055 ) 1px, transparent 1px );\n'
		'\tbackground-size: 88px 44px; }\n'
		'.et_pb_section { padding: 40px 0; }\n'
		'.et_pb_row { width: 90%; max-width: 1800px; margin: 0 auto; }\n'
		'</style>\n</head>\n<body>\n'
		'<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column">'
		'<div class="et_pb_module">\n' + html + '\n</div></div></div></div>\n'
		'</body>\n</html>\n' )

	with open( 'proba.html', 'w', encoding='utf-8' ) as f:
		f.write( strona )

	print( 'proba.html' )


en = zbuduj( EN, 'POKAZ-en.html' )
pl = zbuduj( PL, 'POKAZ-pl.html' )
podglad( en )
