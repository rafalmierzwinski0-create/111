# -*- coding: utf-8 -*-
"""
Podstrona „what it does” — jak wtyczka działa i co potrafi, na jednej stronie.

Nic tu nie jest przepisane ręcznie: tabela to kod z prawdziwego renderu
(landing/mozliwosci/zbierz.php), a trzy zrzuty to prawdziwy kokpit na tym samym
źródle (landing/mozliwosci/zrzuty.mjs). Moduł niesie w sobie arkusz stylów i
skrypt wtyczki, więc sortowanie, szukanie i składanie w karty dzieją się na
stronie sprzedażowej tak samo jak u klienta.

Język strony jest językiem strony głównej, a nie własnym:

* arkusz jako metafora — nagłówek to pasek kolumn, kroki to komórki w wierszu,
  a droga arkusza na stronę to wiersz formuły pod nim;
* para „płyta z tekstem + okienko” na przemian raz z jednej, raz z drugiej
  strony — dokładnie to, co robi sekcja „dwie minuty” na stronie głównej;
* okienka mają belkę z trzema oczkami i mono nazwą, a ekran w nich jest
  wyraźnie ciemniejszy od strony;
* mięta 95 227 207, płyta rgba( 13 18 17 / .62 ), mono IBM Plex.

Sama tabela też jest w kolorach strony: szablon Północ wybrany, a potem
odmalowany próbnikami wtyczki — bo to jest dokładnie to, co wtyczka obiecuje.

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

# ( adres komórki, tytuł, opis )
KROKI = [
	( 'A1', 'Share the sheet',
	  'In Google Sheets: <em>Share &rarr; Anyone with the link &rarr; Viewer</em>. Nothing is installed '
	  'at Google&rsquo;s end, and there is no API key to create.' ),
	( 'B1', 'Paste the link',
	  'The plugin reads the sheet there and then, and shows what it found: the headings, the rows, '
	  'anything that looks wrong. You fix it before it reaches a page.' ),
	( 'C1', 'Put it on a page',
	  'A block, an Elementor widget or the shortcode. Edit the spreadsheet afterwards and the page '
	  'follows on its own.' ),
]

# Wiersz formuły pod arkuszem: co się z arkuszem dzieje, w jednej linii.
DROGA = [
	( 'your sheet', 'in Google, where you already work' ),
	( 'a copy in your database', 'fetched in the background, as often as you like' ),
	( 'your page', 'built on the server, before the visitor asks' ),
]

# ( kolumny tabeli, których dotyczy, poziom, tytuł, opis )
LEGENDA = [
	( 'A-F', 'Free', 'Search and sorting',
	  'Type and the table narrows down, with every hit marked where it was found. Click a heading to '
	  'sort: dates sort as dates, clocks as clocks.' ),
	( 'A', 'Free', 'A pinned first column',
	  'Drag the table sideways and the trail names stay where they are. Without that, a wide table on a '
	  'narrow screen is a row of numbers nobody can name.' ),
	( 'E', 'Pro', 'A colour rule',
	  'When <em>Status</em> is <em>Closed</em>, paint the whole row. Three closed trails are visible '
	  'before anyone has read a word.' ),
	( 'B&nbsp;E', 'Pro', 'A pill, or just a dot',
	  'The same rule, quieter. The value keeps its place and takes a badge, or only a dot beside it. '
	  'Sorting and search still read &ldquo;Open&rdquo;, not a colour.' ),
	( 'C&nbsp;F', 'Pro', 'A bar, and a button',
	  'The bar behind each depth is its share of the deepest snow in the column, and the number stays a '
	  'number: it sorts, and it lands in the download. A column of addresses becomes a column of buttons. '
	  'Two trails have no webcam, and those cells stay empty.' ),
	( 'B&nbsp;E', 'Pro', 'Filters and downloads',
	  'Above the table, a filter your visitors use themselves. Under it, Excel, CSV and print. A download '
	  'holds exactly what is on the screen, filtered rows and hidden columns included.' ),
]

# ( plik, tytuł, zdanie, nazwa okna, trzy krótkie linijki pod spodem )
EKRANY = [
	( 'mz-wyglad', 'Ten styles, then the dials',
	  'Pick a style, then disagree with it: colours one by one, text size, row height and how many lines '
	  'the table draws. Everything you leave alone keeps following the style.',
	  'Appearance',
	  [ 'ten whole-table styles', 'six colour wells, each optional',
	    'text size, row height, lines, corners' ] ),
	( 'mz-reguly', 'A rule reads like a sentence',
	  'When <em>Status</em> is <em>Closed</em>, paint <em>the whole row</em>. No formulas, no code, and '
	  'the colours are worked out on the server, so they are already in the page a visitor receives.',
	  'Colour rules',
	  [ 'the cell, the row, the words, a pill or a dot',
	    'is, is not, more than, contains',
	    'as many rules as the sheet needs' ] ),
	( 'mz-kolumny', 'A column can wear something',
	  'Give a column of numbers a bar, or a column of links a button, in the colours you choose and '
	  'saying what you tell it to say.',
	  'Column looks',
	  [ 'a bar behind a number', 'a button where the cell holds a link',
	    'a badge, or a colour down the whole column' ] ),
]

TELEFON = [
	'the column decides, not the window',
	'every value keeps the name of its column',
	'search, sorting and filters, all still there',
	'one table, not a second mobile copy',
]

WOLNE = [
	( 'The table itself', [
		'Every row your sheet has. No cap at 30, 50 or 100.',
		'A real table in the page code, so Google and screen readers see it.',
		'Search, sorting and pages.',
	] ),
	( 'How it looks', [
		'Three table styles, plus colours, text size, row height, lines and corners.',
		'Cards on a narrow screen, decided by the column, not the window.',
		'A pinned first column and headings that follow the screen down.',
	] ),
	( 'On your page', [
		'Rename, hide, reorder and align columns without touching the sheet.',
		'Your own CSS per table, and a block, an Elementor widget or a shortcode.',
	] ),
]

PRO = [
	( 'More of everything', [
		'As many sheets as you like, checked as often as every minute.',
		'Seven more styles: Cards, Terminal, Glass, Ledger, Contrast, Midnight, Editorial.',
	] ),
	( 'Colour and column looks', [
		'Colour rules: the cell, the whole row, just the words, a pill or a dot.',
		'Column looks: a bar behind a number, a button where there is a link.',
	] ),
	( 'For your visitors', [
		'Filters your visitors use themselves.',
		'Excel, CSV and print, holding exactly what the page shows.',
		'An expandable panel under each row, for the columns that do not fit.',
		'Private sheets, through a Google connection of your own.',
	] ),
]

KROPKI = ( '<span class="lst-mz-kropka"></span><span class="lst-mz-kropka"></span>'
	'<span class="lst-mz-kropka"></span>' )


def etykieta( tekst ):
	"""Mała metka mono nad tytułem sekcji.

	Na całej stronie są DWIE i ani jednej więcej. Metka nad każdym nagłówkiem
	to rytm, który każda strona składana maszynowo ma tak samo: po trzech
	sekcjach przestaje cokolwiek znaczyć, bo miejsce sekcji na stronie i tak
	mówi, czym ona jest. Zostały tam, gdzie naprawdę coś dokładają: pierwsza
	sekcja (nazywa całą podstronę) i tabela (mówi, że to dzieje się teraz).
	"""
	return '<p class="lst-mz-etykieta">' + tekst + '</p>'


def naglowek( tekst ):
	"""Tytuł sekcji: szeryfowy, skalujący się z oknem, z kreską do krawędzi.

	Przedtem każda sekcja miała tylko metkę mono 14 px, więc na całej stronie
	nie było ani jednego napisu większego niż 20 px. Strona była płaska jak
	dokument. Tytuły sekcji na tej witrynie są w Inria Serif i skalują się
	z szerokością okna (tak mówi landing/README.md) — to jedyne miejsce, gdzie
	wolno wyjść poza 14, 18 i 20.
	"""
	return '<h2 class="lst-mz-naglowek">' + tekst + '</h2>'


def komorka( adres, tytul, opis ):
	"""Jeden krok jako komórka arkusza."""
	return ( '<div class="lst-mz-komorka"><p class="lst-mz-adres">' + adres + '</p>'
		'<p class="lst-mz-tytul">' + tytul + '</p>'
		'<p class="lst-mz-opis">' + opis + '</p></div>' )


def etap( nazwa, pod, pierwszy ):
	"""Jeden etap w wierszu formuły, ze strzałką od poprzedniego.

	Strzałka jest znakiem w treści, a nie kreską rysowaną pseudoelementem w
	odstępie między kolumnami. Kreska z grotem wyglądała dokładnie jak literówka
	przyklejona do pierwszej litery etapu; znak „→” czyta się jako strzałka
	w każdej przeglądarce i przy każdej szerokości.

	Stoi PRZED nazwą, a nie po niej: wtedy jest przy tym odstępie, który
	pokonuje, i mówi „to jest dalszy ciąg tamtego”, zamiast wisieć samotnie na
	końcu poprzedniej kolumny.
	"""
	strzalka = '' if pierwszy else '<span class="lst-mz-strzalka" aria-hidden="true">&rarr;</span>'
	return ( '<span class="lst-mz-etap"><span class="lst-mz-etap-nazwa">' + strzalka + nazwa + '</span>'
		'<span class="lst-mz-etap-pod">' + pod + '</span></span>' )


def pozycja( kolumny, tier, tytul, opis ):
	klasa = ' jest-pro' if 'Pro' == tier else ''
	return ( '<div class="lst-mz-pozycja">'
		'<p class="lst-mz-znak' + klasa + '"><span class="lst-mz-znak-adres">' + kolumny + '</span>'
		'<span class="lst-mz-znak-slowo">' + tier + '</span></p>'
		'<p class="lst-mz-tytul">' + tytul + '</p>'
		'<p class="lst-mz-opis">' + opis + '</p></div>' )


def okno( nazwa, srodek, prawa = '', klasa = '' ):
	"""Ramka udająca okno: belka z oczkami i nazwą, a pod nią ekran."""
	po_prawej = ( '<span class="lst-mz-belka-prawa">' + prawa + '</span>' ) if prawa else ''
	return ( '<div class="lst-mz-okno' + klasa + '"><div class="lst-mz-belka">' + KROPKI +
		'<span class="lst-mz-nazwa-okna">' + nazwa + '</span>' + po_prawej + '</div>'
		+ srodek + '</div>' )


def para_tresc( gora, linie ):
	"""Płyta z tekstem: nagłówek u góry, krótkie linijki u dołu.

	Dwa bloki, nie jeden ciąg — luz w wierszu ląduje MIĘDZY nimi, więc płyta
	wygląda na złożoną, a nie na taką, której zabrakło treści. To jest ta sama
	usterka, którą widać było na pierwszy rzut oka: wysoki zrzut obok trzech
	zdań i pół ekranu pustki pod nimi.
	"""
	return '<div class="lst-mz-para-gora">' + gora + '</div>' + punkty( linie )


def punkty( linie ):
	return ( '<ul class="lst-mz-punkty">'
		+ ''.join( '<li>' + x + '</li>' for x in linie ) + '</ul>' )


def para( tresc, prawa, odwrocona, klasa = '' ):
	"""Wiersz strony głównej: płyta z tekstem i okienko obok niej."""
	return ( '<div class="lst-mz-para' + klasa + ( ' jest-odwrocona' if odwrocona else '' ) + '">'
		'<div class="lst-mz-para-tresc">' + tresc + '</div>' + prawa + '</div>' )


def podglad( plik, nazwa ):
	z = ZRZUTY[ plik ]
	return ( '<div class="lst-mz-podglad"><img src="ADRES/' + plik + '.png" alt="' + nazwa + '" '
		'width="' + str( z[ 'w' ] ) + '" height="' + str( z[ 'h' ] ) + '" decoding="async"></div>' )


def ekran( plik, tytul, opis, nazwa, linie, odwrocony ):
	tresc = para_tresc(
		'<p class="lst-mz-adres">' + nazwa + '</p>'
		'<p class="lst-mz-tytul">' + tytul + '</p>'
		'<p class="lst-mz-opis">' + opis + '</p>', linie )
	return para( tresc, okno( 'Dashboard &rsaquo; ' + nazwa, podglad( plik, nazwa ) ), odwrocony )


def lista( tytul, grupy, klasa = '' ):
	"""Lista w grupach, a nie osiem punktów jednym ciągiem.

	Osiem wypunktowań pod rząd czyta się jak lista rzeczy do zrobienia i nikt
	nie dochodzi do końca. Te same osiem pozycji w trzech nazwanych grupach
	czyta się jak trzy rzeczy — a kto szuka konkretu, wie, w której grupie go
	szukać. Ani jedna pozycja nie zniknęła.
	"""
	srodek = ''

	for nazwa, pozycje in grupy:
		srodek += ( '<div class="lst-mz-grupa"><p class="lst-mz-grupa-nazwa">' + nazwa + '</p>'
			'<ul class="lst-mz-lista">'
			+ ''.join( '<li>' + x + '</li>' for x in pozycje ) + '</ul></div>' )

	return ( '<div class="lst-mz-kolumna' + klasa + '"><p class="lst-mz-kolumna-tytul">' + tytul + '</p>'
		'<div class="lst-mz-grupy">' + srodek + '</div></div>' )


SEKCJA = (
	# --- jak to działa: wiersz arkusza i wiersz formuły pod nim -------------
	'<div class="lst-mz-blok">'
	+ etykieta( 'How it works' )
	+ naglowek( 'Three steps, and then it looks after itself' ) +
	'<div class="lst-mz-arkusz">'
	'<div class="lst-mz-litery"><span class="lst-mz-rog"></span>'
	'<span>A</span><span>B</span><span>C</span></div>'
	'<div class="lst-mz-wiersz"><span class="lst-mz-nr">1</span>'
	+ ''.join( komorka( *k ) for k in KROKI ) +
	'</div>'
	'<div class="lst-mz-formula"><span class="lst-mz-fx">fx</span>'
	'<span class="lst-mz-droga">'
	+ ''.join( etap( n, o, 0 == i ) for i, ( n, o ) in enumerate( DROGA ) ) +
	'</span>'
	'</div>'
	'</div>'
	'<p class="lst-mz-nota">Your page is built from the copy in your own database, so nobody waits for '
	'Google. On the day Google will not answer, the last good copy stays on the page while the dashboard '
	'tells you what happened.</p>'
	'</div>'

	# --- tabela na żywo, w takim samym okienku jak zrzuty niżej -------------
	'<div class="lst-mz-blok lst-mz-stol">'
	'<p class="lst-mz-etykieta jest-zywa"><span class="lst-mz-puls"></span>Live on this page</p>'
	+ naglowek( 'This is the plugin, running here' ) +
	'<p class="lst-mz-opis lst-mz-opis-stolu">Ten trails, one spreadsheet. Sort a column, search the box, '
	'filter it. This is the plugin&rsquo;s own output, in the site&rsquo;s own colours: the Midnight '
	'style picked, then disagreed with one colour well at a time.</p>'
	+ okno( 'Trail conditions', '<div class="lst-mz-plansza">' + TABELA + '</div>',
		'10 rows &middot; checked 9 min ago', ' jest-stolem' ) +
	'</div>'

	# --- co na niej widać --------------------------------------------------
	'<div class="lst-mz-blok">'
	+ naglowek( 'What to look for' ) +
	'<p class="lst-mz-wstep">Six things on the table above, and the column each one is sitting in.</p>'
	'<div class="lst-mz-legenda">' + ''.join( pozycja( *p ) for p in LEGENDA ) + '</div>'
	'</div>'

	# --- skąd się to bierze ------------------------------------------------
	'<div class="lst-mz-blok">'
	+ naglowek( 'Where it comes from' ) +
	'<p class="lst-mz-wstep">Three screens from the dashboard, on the very sheet above.</p>'
	'<div class="lst-mz-pary">'
	+ ''.join( ekran( *e, odwrocony = bool( i % 2 ) ) for i, e in enumerate( EKRANY ) ) +
	'</div>'
	'</div>'

	# --- telefon, w tym samym wierszu co wszystko wyżej ---------------------
	'<div class="lst-mz-blok">'
	+ naglowek( 'On a phone, every row becomes a card' ) +
	'<div class="lst-mz-pas">'
	'<div class="lst-mz-pas-bok">'
	'<p class="lst-mz-opis">Each value keeps the name of its column, so nothing has to be guessed from '
	'position. What decides is the width of the column the table sits in, not the width of the screen. '
	'A table in a narrow sidebar folds on a desktop too.'
	'<span class="lst-mz-szeroko"> The frame in the middle is a real phone width, with the same table '
	'in it.</span>'
	'<span class="lst-mz-wasko"> The table above this is doing it right now.</span></p>'
	'</div>'
	'<div class="lst-mz-telefon-rama"><div class="lst-mz-telefon">' + TABELA + '</div></div>'
	'<div class="lst-mz-pas-bok jest-prawy">'
	'<p class="lst-mz-adres">360 px</p>'
	+ punkty( TELEFON ) +
	'</div>'
	'</div>'
	'</div>'

	# --- co jest w czym ----------------------------------------------------
	'<div class="lst-mz-blok">'
	+ naglowek( 'What is in which' ) +
	'<div class="lst-mz-listy">'
	+ lista( 'In the free plugin', WOLNE )
	+ lista( 'Everything above, plus Pro', PRO, ' jest-pro' ) +
	'</div>'
	'<p class="lst-mz-kod"><span class="lst-mz-mono">' + L + 'sheet_table id=&quot;1&quot;' + P + '</span>'
	'<span class="lst-mz-kod-opis">A block, an Elementor widget or this. The same table either way.</span>'
	'<a class="lst-mz-cta" href="ADRES-POBIERANIA">Download free</a></p>'
	'</div>'
)


STYL = r"""
/* ---------------------------------------------------------------- moduł */

/*
 * Wartości są te same co w sekcjach strony głównej (landing/dwie-minuty,
 * landing/naglowek): ta podstrona ma wyglądać jak dalszy ciąg tamtej strony,
 * a nie jak osobna witryna.
 */
.lst-mz {
	--mz-mieta: 95, 227, 207;
	--mz-tekst: #eaf3f1;
	--mz-tekst-2: #9db3b0;
	--mz-tekst-3: #8fa5a2;
	--mz-plyta: rgba( 13, 18, 17, .62 );
	--mz-plyta-linia: rgba( 138, 168, 163, .1 );
	--mz-linia: rgba( 138, 168, 163, .22 );
	--mz-kreska: rgba( 138, 168, 163, .38 );

	/* Ekran: okienka i tabela stoją na tym samym, wyraźnie ciemniejszym od
	   strony kolorze — inaczej wyglądają jak dziury, a nie jak ekrany. */
	--mz-ekran: #0a1110;
	--mz-ekran-gora: #131d1b;
	--mz-ekran-linia: rgba( 95, 227, 207, .2 );

	--mz-mono: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;
	--mz-szeryf: "Inria Serif", "Iowan Old Style", Georgia, serif;

	/*
	 * Jeden promień na wszystko, co jest płytą albo ekranem, i jeden mniejszy
	 * na żetony. Przedtem było ich pięć: 14, 12, 5, 16 i 26 — a pięć promieni
	 * na jednej stronie to nie system, tylko pięć decyzji podjętych osobno.
	 * Ramka telefonu zostaje poza tym: telefon ma promień telefonu.
	 */
	--mz-luk-plyty: 14px;
	--mz-luk-zetonu: 6px;
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
	position: relative;
	isolation: isolate;
}

/*
 * Moduł nie maluje pod sobą tła: pod nim jest tło całej witryny — siatka,
 * która oddycha pod kursorem. Własna poświata tylko tam, gdzie leży tabela,
 * żeby strona miała środek. Rysowana pseudoelementem, nie tłem, bo utwardzenie
 * na wrogie motywy niżej zdejmuje tła, a tego jednego zdjąć nie może.
 */
.lst-mz::before {
	content: "";
	position: absolute;
	inset: 0;
	z-index: -1;
	pointer-events: none;
	background-image:
		radial-gradient( 62% 40% at 50% 30%, rgba( var( --mz-mieta ), .05 ) 0%, rgba( var( --mz-mieta ), 0 ) 70% );
}

.lst-mz.lst-mz { border: 0 !important; outline: 0 !important; background-color: transparent !important; background-image: none !important; }
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
.lst-mz .lst-mz-adres,
.lst-mz .lst-mz-znak,
.lst-mz .lst-mz-nota,
.lst-mz .lst-mz-litery,
.lst-mz .lst-mz-nr,
.lst-mz .lst-mz-fx,
.lst-mz .lst-mz-etap-pod,
.lst-mz .lst-mz-punkty,
.lst-mz .lst-mz-nazwa-okna,
.lst-mz .lst-mz-belka-prawa,
.lst-mz .lst-mz-kolumna-tytul {
	font-family: var( --mz-mono );
	font-size: .875rem;    /* 14 px */
	line-height: 1.5;
}

/*
 * Etykieta sekcji ciągnie za sobą włoskową kreskę do prawej krawędzi. Jeden
 * znak robi tu za dwie rzeczy: dzieli stronę na rozdziały, a przy przewijaniu
 * rysuje się sam i przez to mówi, że ta sekcja właśnie weszła.
 */
.lst-mz .lst-mz-etykieta {
	display: flex;
	align-items: center;
	gap: .9rem;
	letter-spacing: .14em;
	text-transform: uppercase;
	color: rgb( var( --mz-mieta ) );
}



/*
 * Tytuł sekcji.
 *
 * Do niedawna każda sekcja miała nad sobą tylko metkę mono 14 px i nic więcej,
 * więc na całej stronie nie było ani jednego napisu większego niż dwadzieścia
 * pikseli: wszystko ważyło tyle samo i strona czytała się jak dokument, a nie
 * jak strona. Tytuły tej witryny są szeryfowe i skalują się z oknem — to
 * jedyne miejsce, w którym wolno wyjść poza 14, 18 i 20.
 *
 * Kreska, która przedtem ciągnęła się za metką, ciągnie się teraz za tytułem:
 * dzieli stronę na rozdziały i przy przewijaniu rysuje się sama, więc mówi
 * też „ta sekcja właśnie weszła”.
 */
.lst-mz .lst-mz-naglowek {
	display: flex;
	align-items: baseline;
	gap: clamp( .9rem, 2vw, 1.4rem );
	font-family: var( --mz-szeryf );
	font-weight: 400;
	font-size: clamp( 1.625rem, 2.6vw, 2.25rem );
	line-height: 1.12;
	letter-spacing: -.01em;
	color: var( --mz-tekst );
}

.lst-mz .lst-mz-naglowek::after {
	content: "";
	flex: 1 1 auto;
	min-width: 2rem;
	height: 1px;
	background-image: linear-gradient( to right, rgba( var( --mz-mieta ), .3 ), rgba( var( --mz-mieta ), 0 ) );
	transform-origin: left center;
	/* Sam tytuł ma się łamać przed kreską, a nie razem z nią. */
	align-self: center;
}

/* Metka nad tytułem jest teraz rzadkością, więc nie ciągnie już własnej
   kreski: dwie kreski jedna nad drugą to nie akcent, tylko szum. */
.lst-mz .lst-mz-blok > .lst-mz-etykieta + .lst-mz-naglowek { margin-top: -.2rem; }

/* ------------------------------------------------- jak to działa: arkusz */

/*
 * Trzy kroki narysowane jako wiersz arkusza.
 *
 * Wcześniej stały tu trzy zwykłe kafelki, a pod nimi trzy inne zwykłe kafelki
 * z drogą arkusza — sześć prostokątów, z których żaden nie mówił, o czym jest
 * ta strona. Arkusz mówi: pasek kolumn u góry, numer wiersza z boku, komórki
 * w środku, a pod spodem wiersz formuły. Ten sam żart, który strona główna
 * robi adresami komórek przy krokach, tylko rozwinięty do całego wiersza.
 *
 * Linie siatki to odstępy jednopikselowe na tle w kolorze linii, a nie ramki:
 * ramki na sąsiadujących komórkach dają podwójną kreskę.
 */
.lst-mz .lst-mz-arkusz {
	border: 1px solid var( --mz-linia );
	border-radius: var( --mz-luk-plyty );
	overflow: hidden;
	background-color: var( --mz-plyta );
}

.lst-mz .lst-mz-litery,
.lst-mz .lst-mz-wiersz {
	display: grid;
	grid-template-columns: 2.4rem repeat( 3, minmax( 0, 1fr ) );
	gap: 1px;
	background-color: var( --mz-linia );
}

.lst-mz .lst-mz-litery > span {
	padding: .4rem .7rem;
	background-color: var( --mz-ekran-gora );
	color: var( --mz-tekst-3 );
	letter-spacing: .14em;
	text-align: center;
}

.lst-mz .lst-mz-litery .lst-mz-rog { background-color: var( --mz-ekran-gora ); }

.lst-mz .lst-mz-nr {
	display: flex;
	align-items: flex-start;
	justify-content: center;
	padding: 1.15rem .4rem;
	background-color: var( --mz-ekran-gora );
	color: var( --mz-tekst-3 );
}

.lst-mz .lst-mz-komorka {
	display: grid;
	align-content: start;
	gap: .45rem;
	padding: 1.15rem 1.3rem 1.3rem;
	background-color: rgba( 13, 18, 17, .78 );
	/*
	 * Jasna kreska po górnej krawędzi. Ciemna komórka bez niej jest dziurą w
	 * stronie; z nią jest płytką, na którą pada światło.
	 */
	box-shadow: inset 0 1px 0 rgba( 255, 255, 255, .04 );
	transition: background-color 200ms ease;
	/*
	 * Wejście, i tylko ono. Komórki są w pierwszym ekranie, więc nie czekają na
	 * przewinięcie; to animacja, a nie „opacity: 0” do odwołania, więc element
	 * bez niej jest po prostu widoczny.
	 */
	animation: lst-mz-wejscie 380ms var( --mz-luk ) both;
}

.lst-mz .lst-mz-komorka:nth-child( 3 ) { animation-delay: 70ms; }
.lst-mz .lst-mz-komorka:nth-child( 4 ) { animation-delay: 140ms; }

@keyframes lst-mz-wejscie {
	from { opacity: 0; transform: translateY( 12px ) scale( .985 ); }
}

.lst-mz .lst-mz-adres { letter-spacing: .14em; color: rgb( var( --mz-mieta ) ); }

/* ------------------------------------------------------- wiersz formuły */

/*
 * Droga arkusza na stronę, napisana tam, gdzie w arkuszu pisze się to, z czego
 * komórka wynika. Trzy etapy w trzech kolumnach, strzałki rysują się raz, po
 * kolei, w stronę, w którą idą dane — i na tym koniec. Jeździła tędy kiedyś
 * kropka w kółko; ruch bez końca na skraju oka nie pokazuje niczego, czego nie
 * pokazuje sama strzałka, a widać go przez cały czas, kiedy się czyta.
 */
.lst-mz .lst-mz-formula {
	display: grid;
	grid-template-columns: 2.4rem minmax( 0, 1fr );
	gap: 1px;
	background-color: var( --mz-linia );
	border-top: 1px solid var( --mz-linia );
}

.lst-mz .lst-mz-fx {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: .85rem .4rem;
	background-color: var( --mz-ekran-gora );
	color: var( --mz-tekst-3 );
	font-style: italic;
}

.lst-mz .lst-mz-droga {
	display: grid;
	grid-template-columns: repeat( 3, minmax( 0, 1fr ) );
	gap: clamp( 1.2rem, 3vw, 2.4rem );
	padding: .85rem clamp( .9rem, 2vw, 1.3rem );
	background-color: var( --mz-ekran );
}

.lst-mz .lst-mz-etap {
	position: relative;
	display: grid;
	gap: .15rem;
	align-content: start;
}

.lst-mz .lst-mz-etap-nazwa {
	font-family: var( --mz-mono );
	font-size: .875rem;
	letter-spacing: .06em;
	text-transform: uppercase;
	color: var( --mz-tekst );
}

.lst-mz .lst-mz-etap-pod { color: var( --mz-tekst-3 ); }

/* Objaśnienie równa się z nazwą, a nie ze strzałką przed nią: w mono znak i
   jego odstęp to 1,15 szerokości znaku. */
.lst-mz .lst-mz-etap:not( :first-child ) .lst-mz-etap-pod { padding-left: 1.15em; }

/*
 * Strzałka po nazwie etapu, raz, po kolei. Wjeżdża samym przesunięciem — bez
 * ruchu i tak jest na swoim miejscu, więc strona bez animacji niczego nie
 * traci.
 */
.lst-mz .lst-mz-strzalka {
	display: inline-block;
	/* Odstęp dopełnieniem, nie marginesem: utwardzenie na wrogie motywy niżej
	   zeruje marginesy na boki z „!important”, więc margines tu nie przeżyje. */
	padding-right: .55em;
	color: rgb( var( --mz-mieta ) );
	animation: lst-mz-strzalka 420ms var( --mz-luk ) both;
	animation-delay: 320ms;
}

.lst-mz .lst-mz-etap:nth-child( 3 ) .lst-mz-strzalka { animation-delay: 460ms; }

@keyframes lst-mz-kreska { from { transform: scaleX( 0 ); } }
@keyframes lst-mz-kreska-w-dol { from { transform: scaleY( 0 ); } }
@keyframes lst-mz-strzalka { from { transform: translateX( -.4em ); } }

.lst-mz .lst-mz-nota { color: var( --mz-tekst-3 ); max-width: 52rem; }

/* ------------------------------------------------------------- okienko */

/*
 * Jedno okienko na całej stronie: trzyma zrzuty z kokpitu, ekran telefonu
 * i samą tabelę. Dzięki temu tabela na żywo i zrzuty czytają się jak rzeczy
 * z jednego miejsca, a nie jak obrazek obok obrazka.
 */
.lst-mz .lst-mz-okno {
	position: relative;
	display: flex;
	flex-direction: column;
	min-width: 0;
	border: 1px solid var( --mz-ekran-linia );
	border-radius: var( --mz-luk-plyty );
	overflow: hidden;
	background-color: var( --mz-ekran );
	box-shadow: 0 22px 46px -30px rgba( 0, 0, 0, .95 ), 0 0 34px -18px rgba( var( --mz-mieta ), .3 );
	transition: transform 220ms var( --mz-luk ), box-shadow 220ms var( --mz-luk );
}

.lst-mz .lst-mz-belka {
	display: flex;
	align-items: center;
	gap: .45rem;
	flex: none;
	padding: .55rem .8rem;
	background-color: var( --mz-ekran-gora );
	border-bottom: 1px solid rgba( var( --mz-mieta ), .14 );
}

.lst-mz .lst-mz-kropka {
	width: 8px;
	height: 8px;
	flex: none;
	border-radius: 50%;
	background-color: rgba( 138, 168, 163, .35 );
}

.lst-mz .lst-mz-nazwa-okna {
	margin-left: .4rem;
	color: var( --mz-tekst-3 );
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.lst-mz .lst-mz-belka-prawa {
	margin-left: auto;
	padding-left: 1rem;
	color: var( --mz-tekst-3 );
	white-space: nowrap;
}

/*
 * Okienko ze zrzutem ma sufit.
 *
 * Zrzuty są wysokie: „Wygląd” to 1216 pikseli, czyli po przeskalowaniu do
 * połowy wiersza ponad sześćset. Wiersz robił się wtedy tak wysoki jak obraz,
 * a płyta z tekstem obok — mimo że rozciągnięta — miała trzy czwarte wysokości
 * pustej. Sufit zrównuje obie strony, a ściemnienie u dołu mówi, że ekran ma
 * dalszy ciąg. To jest uczciwsze niż kadr przycięty na sztywno: nie udaje, że
 * kokpit kończy się akurat tam.
 */
.lst-mz .lst-mz-okno:not( .jest-stolem ) { max-height: clamp( 16rem, 25vw, 22rem ); }

.lst-mz .lst-mz-podglad { flex: 1 1 auto; min-height: 0; overflow: hidden; }

.lst-mz .lst-mz-okno img {
	display: block;
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: top left;
	transition: transform 320ms var( --mz-luk );
}

/* Ściemnienie u dołu okna: „ten ekran ma dalszy ciąg”. Szerokiego nie dotyczy,
   bo tam nic nie zostało urwane i fałszywa zapowiedź dalszego ciągu kłamie. */
.lst-mz .lst-mz-okno:not( .jest-stolem ):not( .jest-szerokie )::after {
	content: "";
	position: absolute;
	inset: auto 0 0 0;
	height: 3.5rem;
	pointer-events: none;
	background-image: linear-gradient( to bottom, rgba( 10, 17, 16, 0 ), rgba( 10, 17, 16, .92 ) );
}

/* ----------------------------------------------------- tabela na żywo */

.lst-mz .lst-mz-opis-stolu { margin-top: -.6rem; max-width: 52rem; }

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

/* Tabela dostaje w okienku trochę powietrza — jej własne tło jest tym samym
   ekranem, więc nie widać, gdzie kończy się okno, a zaczyna arkusz. */
.lst-mz .lst-mz-plansza { padding: clamp( .6rem, 1.4vw, 1.1rem ); }

/* ------------------------------------------------------------- legenda */

.lst-mz .lst-mz-legenda {
	display: grid;
	grid-template-columns: repeat( 3, minmax( 0, 1fr ) );
	gap: clamp( 1.1rem, 2.4vw, 1.8rem );
}

/*
 * Kafelek, a nie luźny tekst. Sześć akapitów stojących na gołym tle czyta się
 * jak lista rzeczy do zrobienia; sześć płytek czyta się jak sześć rzeczy.
 */
.lst-mz .lst-mz-pozycja {
	display: grid;
	gap: .4rem;
	align-content: start;
	padding: 1.1rem 1.25rem 1.25rem;
	background-color: var( --mz-plyta );
	border: 1px solid var( --mz-plyta-linia );
	border-radius: var( --mz-luk-plyty );
	box-shadow: inset 0 1px 0 rgba( 255, 255, 255, .045 );
	transition: border-color 200ms ease, transform 200ms var( --mz-luk );
}

/*
 * Znacznik jest komórką arkusza, tak jak żetony w nagłówku strony: z lewej
 * adres, z prawej wartość. Adres nie jest ozdobą — to litera kolumny w tabeli
 * wyżej, więc żeton mówi, gdzie na niej tego szukać.
 */
.lst-mz .lst-mz-znak {
	display: inline-flex;
	justify-self: start;
	align-items: stretch;
	overflow: hidden;
	border: 1px solid var( --mz-linia );
	border-radius: var( --mz-luk-zetonu );
	letter-spacing: .1em;
	text-transform: uppercase;
}

.lst-mz .lst-mz-znak-adres {
	padding: .1rem .45rem;
	background-color: rgba( 138, 168, 163, .12 );
	border-right: 1px solid var( --mz-linia );
	color: var( --mz-tekst-3 );
}

.lst-mz .lst-mz-znak-slowo { padding: .1rem .55rem; color: var( --mz-tekst-3 ); }

.lst-mz .lst-mz-znak.jest-pro { border-color: rgba( var( --mz-mieta ), .45 ); }
.lst-mz .lst-mz-znak.jest-pro .lst-mz-znak-adres { border-right-color: rgba( var( --mz-mieta ), .45 ); }
.lst-mz .lst-mz-znak.jest-pro .lst-mz-znak-slowo { color: rgb( var( --mz-mieta ) ); background-color: rgba( var( --mz-mieta ), .1 ); }

/* ------------------------------------------- para: tekst i okienko obok */

/*
 * Wiersz strony głównej, przeniesiony tutaj: płyta z tekstem po jednej
 * stronie, okienko po drugiej, co drugi wiersz odwrócony, a między wierszami
 * włoskowa kreska.
 *
 * „stretch” jest tu najważniejszą deklaracją w całym pliku. Przedtem tekst
 * stał luzem obok wysokiego zrzutu i pod nim zostawał metr pustego miejsca;
 * teraz obie strony mają tę samą wysokość, a obraz jest przycięty do niej.
 */
.lst-mz .lst-mz-pary { display: grid; }

.lst-mz .lst-mz-para {
	display: grid;
	grid-template-columns: minmax( 0, 1fr ) minmax( 0, 1fr );
	gap: clamp( 1.2rem, 3vw, 2.6rem );
	align-items: stretch;
	padding-block: clamp( 1.4rem, 2.6vw, 2.2rem );
	border-top: 1px solid var( --mz-kreska );
}

.lst-mz .lst-mz-pary > .lst-mz-para:first-child { border-top: 0; padding-top: 0; }

.lst-mz .lst-mz-para.jest-odwrocona .lst-mz-okno { order: -1; }

.lst-mz .lst-mz-para-tresc {
	display: grid;
	gap: .55rem;
	/*
	 * Nagłówek u góry, linijki u dołu, a luz między nimi. Przy „start” cały
	 * zapas wysokości zbierał się pod tekstem i płyta wyglądała na pustą w
	 * trzech czwartych — to było dokładnie to, co widać było gołym okiem.
	 */
	align-content: space-between;
	padding: 1.15rem 1.3rem 1.3rem;
	background-color: var( --mz-plyta );
	border: 1px solid var( --mz-plyta-linia );
	border-radius: var( --mz-luk-plyty );
	box-shadow: inset 0 1px 0 rgba( 255, 255, 255, .045 );
}

.lst-mz .lst-mz-para-gora { display: grid; gap: .55rem; align-content: start; }

/* Trzy krótkie linijki pod zdaniem: płyta ma mieć treść, a nie samo zdanie
   i pustkę pod nim. Mono, bo to są nazwy ustawień, a nie proza. */
.lst-mz .lst-mz-punkty {
	display: grid;
	gap: .3rem;
	margin-top: .35rem;
	padding-top: .7rem;
	border-top: 1px solid var( --mz-plyta-linia );
	color: var( --mz-tekst-3 );
}

.lst-mz .lst-mz-punkty li {
	position: relative;
	padding-left: 1.05rem;
	list-style: none;
}

.lst-mz .lst-mz-punkty li::before {
	content: "";
	position: absolute;
	left: 0;
	top: .62em;
	width: .35rem;
	height: 1px;
	background-color: rgba( var( --mz-mieta ), .7 );
}

/* --------------------------------------------------- telefon: pas z aparatem */

/*
 * Telefon nie jest kolejną parą „płyta i okienko”.
 *
 * Trzy takie pary stoją wyżej i to jest jeden szereg, ten sam, który robi
 * sekcja „dwie minuty” na stronie głównej. Czwarta para pod nimi nie byłaby
 * już szeregiem, tylko przyzwyczajeniem. Tu aparat stoi w środku, a tekst po
 * obu jego stronach: inny układ, a przy okazji telefon przestaje być dodatkiem
 * obok zdania i staje się przedmiotem sekcji.
 */
.lst-mz .lst-mz-pas {
	display: grid;
	grid-template-columns: minmax( 0, 1fr ) auto minmax( 0, 1fr );
	gap: clamp( 1.2rem, 2.6vw, 2.2rem );
	align-items: center;
	padding-top: clamp( .6rem, 1.4vw, 1rem );
}

.lst-mz .lst-mz-pas-bok { display: grid; gap: .6rem; align-content: center; }

/* Obie strony wyrównane tak samo: jedna do środka, druga do góry wygląda
   jak dwie decyzje podjęte osobno. */

.lst-mz .lst-mz-pas-bok .lst-mz-punkty { margin-top: 0; padding-top: 0; border-top: 0; }

/* -------------------------------------------------------------- telefon */

.lst-mz .lst-mz-wasko { display: none; }

.lst-mz .lst-mz-telefon-rama {
	width: fit-content;
	max-width: 100%;
	padding: 14px;
	border: 1px solid var( --mz-kreska );
	border-radius: 26px;
	background-color: var( --mz-plyta );
	box-shadow: inset 0 1px 0 rgba( 255, 255, 255, .06 ), 0 26px 50px -34px rgba( 0, 0, 0, .95 );
}

/*
 * Ekran telefonu jest oknem, nie kartką: dziesięć kart jedna pod drugą
 * rozciągnęłoby tę sekcję na dwa ekrany, a widać już po trzech.
 */
.lst-mz .lst-mz-telefon {
	width: 360px;
	max-width: 100%;
	height: clamp( 22rem, 30vw, 26rem );
	overflow: hidden;
	border-radius: 16px;
	background-color: var( --mz-ekran );
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
	gap: 0;
	align-content: start;
	overflow: hidden;
	background-color: var( --mz-plyta );
	border: 1px solid var( --mz-plyta-linia );
	border-radius: var( --mz-luk-plyty );
	box-shadow: inset 0 1px 0 rgba( 255, 255, 255, .045 );
	transition: border-color 200ms ease;
}

.lst-mz .lst-mz-kolumna.jest-pro { border-color: rgba( var( --mz-mieta ), .28 ); }

/* Tytuł listy siedzi w pasku jak nagłówek kolumny w arkuszu. */
.lst-mz .lst-mz-kolumna-tytul {
	padding: .55rem 1.35rem;
	background-color: var( --mz-ekran-gora );
	border-bottom: 1px solid var( --mz-plyta-linia );
	letter-spacing: .12em;
	text-transform: uppercase;
	color: var( --mz-tekst-3 );
}

.lst-mz .lst-mz-kolumna.jest-pro .lst-mz-kolumna-tytul { color: rgb( var( --mz-mieta ) ); }

/*
 * Osiem pozycji w trzech nazwanych grupach zamiast jednego ciągu wypunktowań.
 * Ciąg ośmiu kropek czyta się jak lista rzeczy do zrobienia i nikt nie dochodzi
 * do końca; trzy grupy czyta się jak trzy rzeczy, a kto szuka konkretu, wie,
 * w której grupie szukać.
 */
.lst-mz .lst-mz-grupy { display: grid; padding: 1.1rem 1.35rem 1.35rem; gap: 1rem; }

.lst-mz .lst-mz-grupa { display: grid; gap: .5rem; }

.lst-mz .lst-mz-grupa + .lst-mz-grupa { padding-top: 1rem; border-top: 1px solid var( --mz-plyta-linia ); }

.lst-mz .lst-mz-grupa-nazwa {
	font-size: .875rem;
	font-weight: 600;
	line-height: 1.4;
	color: var( --mz-tekst );
}

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
	border-radius: var( --mz-luk-plyty );
	font-size: .875rem;
}

.lst-mz .lst-mz-kod .lst-mz-mono { font-family: var( --mz-mono ); color: rgb( var( --mz-mieta ) ); }
.lst-mz .lst-mz-kod-opis { color: var( --mz-tekst-3 ); font-size: .875rem; }

/*
 * Jedno wezwanie na całej podstronie, i to na samym jej końcu: kto doczytał aż
 * tutaj, wie już wszystko, czego się dowie. Napis jest ten sam co na stronie
 * głównej, bo dwa różne napisy na to samo działanie to dwa działania w głowie
 * czytającego. Ciemny tusz na miętowym tle, czyli to samo, co robi przycisk
 * w nagłówku witryny.
 */
.lst-mz .lst-mz-cta {
	margin-left: auto;
	padding: .5rem 1.15rem;
	border-radius: 999px;
	background-color: rgb( var( --mz-mieta ) );
	color: #06100f;
	font-size: 1.125rem;
	font-weight: 600;
	line-height: 1.3;
	white-space: nowrap;
	text-decoration: none;
	transition: transform 160ms var( --mz-luk ), filter 160ms ease;
}

.lst-mz .lst-mz-cta:active { transform: translateY( 1px ); }

/* ------------------------------------------------------------- najechanie */

@media ( hover: hover ) and ( pointer: fine ) {
	.lst-mz .lst-mz-komorka:hover { background-color: rgba( 20, 30, 28, .86 ); }
	.lst-mz .lst-mz-pozycja:hover { border-color: rgba( var( --mz-mieta ), .34 ); transform: translateY( -2px ); }
	.lst-mz .lst-mz-kolumna:hover { border-color: rgba( var( --mz-mieta ), .34 ); }
	.lst-mz .lst-mz-cta:hover { filter: brightness( 1.08 ); transform: translateY( -1px ); }
	.lst-mz .lst-mz-okno:not( .jest-stolem ):hover { transform: translateY( -2px ); box-shadow: 0 26px 50px -30px rgba( 0, 0, 0, .95 ), 0 0 40px -16px rgba( var( --mz-mieta ), .4 ); }
	.lst-mz .lst-mz-okno:not( .jest-stolem ):hover img { transform: scale( 1.012 ); }
}

/* --------------------------------------------- ruch, który niesie przewijanie */

/*
 * Dwie zasady, obie wymuszone, a nie deklarowane.
 *
 * Po pierwsze: ruch na osi widoku NIE RUSZA PRZEZROCZYSTOŚCI. Tylko przesunięcia.
 * Kuszące jest wjechać treścią z „opacity: 0”, i tak to tu najpierw stało — po
 * czym test pokazał dziewiętnaście elementów niewidocznych w spoczynku. Element
 * przed swoim zakresem siedzi w klatce startowej, więc „jeszcze nie wszedł”
 * znaczy „niewidoczny”: na zrzucie całej strony, na wydruku, w czytniku, który
 * nie przewija, i u każdego, komu oś widoku zadziała inaczej, niż zakładałem.
 * Przesunięte o czternaście pikseli zdanie jest zdaniem. Przezroczyste nie ma.
 *
 * Po drugie: całość siedzi w @supports, więc przeglądarka, która osi nie zna,
 * dostaje stronę gotową, bez ani jednej reguły z tego bloku.
 */
@supports ( animation-timeline: view() ) {

	.lst-mz .lst-mz-blok:not( :first-child ) > .lst-mz-etykieta,
	.lst-mz .lst-mz-blok:not( :first-child ) > .lst-mz-wstep,
	.lst-mz .lst-mz-blok:not( :first-child ) > .lst-mz-naglowek,
	.lst-mz .lst-mz-legenda .lst-mz-pozycja,
	.lst-mz .lst-mz-para,
	.lst-mz .lst-mz-pas,
	.lst-mz .lst-mz-listy .lst-mz-kolumna,
	.lst-mz .lst-mz-kod,
	.lst-mz .lst-mz-stol > .lst-mz-etykieta,
	.lst-mz .lst-mz-stol > .lst-mz-opis-stolu {
		animation: lst-mz-wjazd 520ms var( --mz-luk ) both;
		animation-timeline: view();
		animation-range: entry 4% cover 26%;
	}

	/*
	 * Kreska przy etykiecie rysuje się sama, kiedy sekcja wchodzi. To ten sam
	 * znak, który dzieli stronę na rozdziały — tu robi dodatkowo za wskaźnik:
	 * dociągnięta kreska znaczy „ta sekcja jest już twoja”.
	 */
	.lst-mz .lst-mz-naglowek::after {
		animation: lst-mz-kreska 620ms var( --mz-luk ) both;
		animation-timeline: view();
		animation-range: entry 6% cover 30%;
	}

	/*
	 * Wiersze tabeli przyjeżdżają po kolei. To jedyna animacja na tej stronie,
	 * która mówi coś o produkcie, a nie o stronie: tak właśnie arkusz ląduje na
	 * stronie, wiersz po wierszu. Tylko sześć pierwszych i tylko 34 ms odstępu —
	 * dziesięć wierszy po kolei to już czekanie, a nie powitanie.
	 */
	.lst-mz .lst-mz-stol .lstab-row:nth-child( -n + 6 ) {
		animation: lst-mz-wiersz 420ms var( --mz-luk ) both;
		animation-timeline: view( block );
		animation-range: entry 2% cover 22%;
	}

	.lst-mz .lst-mz-stol .lstab-row:nth-child( 2 ) { animation-delay: 34ms; }
	.lst-mz .lst-mz-stol .lstab-row:nth-child( 3 ) { animation-delay: 68ms; }
	.lst-mz .lst-mz-stol .lstab-row:nth-child( 4 ) { animation-delay: 102ms; }
	.lst-mz .lst-mz-stol .lstab-row:nth-child( 5 ) { animation-delay: 136ms; }
	.lst-mz .lst-mz-stol .lstab-row:nth-child( 6 ) { animation-delay: 170ms; }

	/* Okno z tabelą podnosi się: jedyne miejsce, gdzie wysokość coś znaczy. */
	.lst-mz .lst-mz-okno.jest-stolem {
		animation: lst-mz-arkusz 640ms var( --mz-luk ) both;
		animation-timeline: view();
		animation-range: entry 2% cover 24%;
	}
}

/* Same przesunięcia — patrz zasada pierwsza wyżej. */
@keyframes lst-mz-wjazd {
	from { transform: translateY( 14px ); }
}

@keyframes lst-mz-wiersz {
	from { transform: translateY( 7px ); }
}

@keyframes lst-mz-arkusz {
	from { transform: translateY( 22px ) scale( .988 ); }
}

/*
 * Na papierze nie ma przewijania, więc nie ma też czego dojeżdżać. Oś widoku w
 * druku jest niczyją ziemią; tu jest po prostu wyłączona i strona wychodzi
 * taka, jaka jest na końcu ruchu.
 */
@media print {
	.lst-mz [class*="lst-mz-"],
	.lst-mz .lst-mz-naglowek::after,
	.lst-mz .lst-mz-stol .lstab-row { animation: none !important; }
}

@media ( prefers-reduced-motion: reduce ) {
	/*
	 * „Mniej ruchu” znaczy mniej ruchu, nie mniej treści: animacje znikają, a
	 * wszystko, co one pokazywały, zostaje na ekranie w stanie końcowym.
	 */
	.lst-mz [class*="lst-mz-"],
	.lst-mz .lst-mz-naglowek::after,
	.lst-mz .lst-mz-stol .lstab-row { animation: none !important; }

	.lst-mz .lst-mz-komorka,
	.lst-mz .lst-mz-pozycja,
	.lst-mz .lst-mz-okno,
	.lst-mz .lst-mz-okno img,
	.lst-mz .lst-mz-cta,
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

.lst-mz .lst-mz-arkusz,
.lst-mz .lst-mz-litery,
.lst-mz .lst-mz-wiersz,
.lst-mz .lst-mz-formula { background-color: var( --mz-linia ) !important; }

.lst-mz .lst-mz-arkusz { background-color: var( --mz-plyta ) !important; border: 1px solid var( --mz-linia ) !important; }
.lst-mz .lst-mz-komorka { background-color: rgba( 13, 18, 17, .78 ) !important; }
.lst-mz .lst-mz-litery > span,
.lst-mz .lst-mz-nr,
.lst-mz .lst-mz-fx,
.lst-mz .lst-mz-kolumna-tytul { background-color: var( --mz-ekran-gora ) !important; }
.lst-mz .lst-mz-droga { background-color: var( --mz-ekran ) !important; }

.lst-mz .lst-mz-para-tresc,
.lst-mz .lst-mz-pozycja,
.lst-mz .lst-mz-kolumna,
.lst-mz .lst-mz-kod { background-color: var( --mz-plyta ) !important; border: 1px solid var( --mz-plyta-linia ) !important; }

.lst-mz .lst-mz-telefon-rama { background-color: var( --mz-plyta ) !important; border: 1px solid var( --mz-kreska ) !important; }
.lst-mz .lst-mz-telefon { background-color: var( --mz-ekran ) !important; }
.lst-mz .lst-mz-okno { background-color: var( --mz-ekran ) !important; border: 1px solid var( --mz-ekran-linia ) !important; }
.lst-mz .lst-mz-belka { background-color: var( --mz-ekran-gora ) !important; border: 0 !important; border-bottom: 1px solid rgba( var( --mz-mieta ), .14 ) !important; }

.lst-mz .lst-mz-znak {
	border: 1px solid var( --mz-linia ) !important;
	text-transform: uppercase !important;
	font-family: var( --mz-mono ) !important;
}

.lst-mz .lst-mz-znak.jest-pro { border-color: rgba( var( --mz-mieta ), .45 ) !important; }
.lst-mz .lst-mz-znak-adres { background-color: rgba( 138, 168, 163, .12 ) !important; border-right: 1px solid var( --mz-linia ) !important; }
.lst-mz .lst-mz-znak.jest-pro .lst-mz-znak-slowo { background-color: rgba( var( --mz-mieta ), .1 ) !important; }

.lst-mz .lst-mz-etykieta,
.lst-mz .lst-mz-etap-nazwa,
.lst-mz .lst-mz-kolumna-tytul,
.lst-mz .lst-mz-litery > span { text-transform: uppercase !important; }

.lst-mz .lst-mz-naglowek { font-family: var( --mz-szeryf ) !important; }
.lst-mz .lst-mz-cta { background-color: rgb( var( --mz-mieta ) ) !important; color: #06100f !important; }

.lst-mz .lst-mz-adres,
.lst-mz .lst-mz-etykieta,
.lst-mz .lst-mz-nota,
.lst-mz .lst-mz-litery,
.lst-mz .lst-mz-nr,
.lst-mz .lst-mz-fx,
.lst-mz .lst-mz-etap-nazwa,
.lst-mz .lst-mz-etap-pod,
.lst-mz .lst-mz-punkty,
.lst-mz .lst-mz-nazwa-okna,
.lst-mz .lst-mz-belka-prawa,
.lst-mz .lst-mz-kolumna-tytul,
.lst-mz .lst-mz-kod,
.lst-mz .lst-mz-kod-opis,
.lst-mz .lst-mz-mono { font-family: var( --mz-mono ) !important; }

/* Sama tabela broni się swoim arkuszem; tu tylko tyle, żeby cudze marginesy
   nie wypchnęły jej poza szerokość strony. */
.lst-mz .lstab-container, .lst-mz .lstab { margin-inline: 0 !important; max-width: 100%; }

/* ------------------------------------------------------------- wąsko */

@media ( max-width: 1180px ) {
	.lst-mz .lst-mz-legenda { grid-template-columns: repeat( 2, minmax( 0, 1fr ) ); }
}

/* Na wąskim ekranie w belce mieści się nazwa okna albo nic — druga połowa
   urywała się w połowie słowa. */
@media ( max-width: 640px ) {
	.lst-mz .lst-mz-belka-prawa { display: none; }
}

@media ( max-width: 900px ) {
	.lst-mz .lst-mz-legenda,
	.lst-mz .lst-mz-para { grid-template-columns: minmax( 0, 1fr ); }

	.lst-mz .lst-mz-para.jest-odwrocona .lst-mz-okno { order: 0; }

	/* Wąsko arkusz przestaje być wierszem i staje się kolumną: trzy komórki
	   jedna pod drugą, a pasek liter i numer wiersza znikają, bo wiersz
	   z jedną komórką nie jest już wierszem. */
	.lst-mz .lst-mz-litery { display: none; }
	.lst-mz .lst-mz-wiersz { grid-template-columns: minmax( 0, 1fr ); }
	.lst-mz .lst-mz-nr { display: none; }
	.lst-mz .lst-mz-formula { grid-template-columns: minmax( 0, 1fr ); }
	.lst-mz .lst-mz-fx { display: none; }
	.lst-mz .lst-mz-droga { grid-template-columns: minmax( 0, 1fr ); gap: 1.1rem; }

	/*
	 * W jednej kolumnie strzałek nie ma: pokazywałyby w prawo tam, gdzie droga
	 * biegnie w dół. A skoro ich nie ma, to i wcięcie pod nie znika.
	 */
	.lst-mz .lst-mz-strzalka { display: none; }
	.lst-mz .lst-mz-etap:not( :first-child ) .lst-mz-etap-pod { padding-left: 0; }

	/* Kreska za tytułem ma sens, kiedy tytuł mieści się w jednym wierszu.
	   Przy dwóch łamie się obok pierwszego i wygląda jak zgubiony znak. */
	.lst-mz .lst-mz-naglowek::after { display: none; }
	.lst-mz .lst-mz-pas { grid-template-columns: minmax( 0, 1fr ); }
	.lst-mz .lst-mz-telefon-rama { display: none; }
	.lst-mz .lst-mz-szeroko { display: none; }
	.lst-mz .lst-mz-wasko { display: inline; }
}
"""

STRONA = (
	'<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Inria+Serif:ital,wght@0,300;0,400&display=swap">\n'
	'\n<div class="lst-mz"><div class="lst-mz-rama">' + SEKCJA + '</div></div>\n'
	'\n<style>\n' + STYL + CSS + '\n</style>\n'
	'\n<script>\n' + JS + '\n</script>\n'
)

( TU / 'MOZLIWOSCI-en.html' ).write_text( STRONA )

# Podgląd do otwarcia w przeglądarce: podrabia tło i dopełnienia Divi, i
# podstawia lokalne adresy zrzutów. Do Divi idzie wyłącznie MOZLIWOSCI-en.html.
#
# Tło jest TAKIE SAME jak w landing/naglowek/HERO-podglad.html: #232a29 i
# siatka 88 × 44. Wcześniej podgląd malował pod modułem własną, ciemniejszą
# czerń — i cała ta podstrona była projektowana pod tło, którego na stronie
# nie ma.
#
# Podgląd udaje stronę Divi, a strona Divi ma deklarację typu dokumentu. Bez
# niej przeglądarka idzie w tryb zgodności, w którym tabela NIE dziedziczy
# koloru tekstu po swoim otoczeniu.
PODGLAD = (
	'<!doctype html>\n<html lang="en">\n<meta charset="utf-8">\n'
	'<title>What it does</title>\n'
	'<style>\n'
	'html, body { margin: 0; background: #232a29; color: #eaf3f1;\n'
	'\tfont-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif; }\n'
	'body { background-image: linear-gradient( to right, rgba( 255, 255, 255, .04 ) 1px, transparent 1px ),\n'
	'\tlinear-gradient( to bottom, rgba( 255, 255, 255, .04 ) 1px, transparent 1px );\n'
	'\tbackground-size: 88px 44px; }\n'
	'.podrobka-divi { padding: 40px 0; }\n'
	'.podrobka-divi-rzad { width: 90%; max-width: 1800px; margin: 0 auto; }\n'
	'</style>\n'
	'<div class="podrobka-divi"><div class="podrobka-divi-rzad">\n'
	+ STRONA.replace( 'ADRES/', 'zrzuty/' ) +
	'\n</div></div>\n'
)

( TU / 'PODGLAD.html' ).write_text( PODGLAD )

print( 'ok', len( STRONA ), 'znaków modułu' )
