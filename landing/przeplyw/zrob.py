# -*- coding: utf-8 -*-
"""
Sekcja pod hero: z arkusza na stronę, pokazane, a nie opisane.

Po lewej arkusz Google z cennikiem. Po prawej ta sama treść już na stronie
klienta: prawdziwa tabela z wtyczki, z pigułkami stanu magazynu, słupkiem przy
liczbie sztuk i przyciskiem w kolumnie z adresem. Między nimi strzałka.

Obie strony biorą się z jednego miejsca. `zbierz.php` renderuje tabelę
prawdziwą wtyczką na prawdziwym WordPressie i przy okazji zapisuje surowe
wiersze — arkusz po lewej rysuje się z tych samych wierszy. To naprawdę jest ta
sama treść po obu stronach strzałki, a nie dwa osobno napisane przykłady, i nie
da się tego rozjechać bez przebudowania obu naraz.

Usage:
    php landing/przeplyw/zbierz.php /tmp/lstab-env/wp71   # renderuje tabelę
    python3 landing/przeplyw/zrob.py                      # składa sekcję
    cd landing/przeplyw && node spr.mjs                   # sprawdza ją

UWAGA przy edycji: każdy znacznik musi zostać w jednej linijce. Divi wstawia
w miejscu złamanego wiersza <br />, co rozbija znacznik. Wewnątrz <style>
i <script> Divi nic nie rusza.
"""

import html
import json
import pathlib
import re

TU  = pathlib.Path( __file__ ).parent
CSS = pathlib.Path( '/home/user/111/live-sheets-table/assets/css/lstab-table.css' ).read_text()
JS  = pathlib.Path( '/home/user/111/live-sheets-table/assets/js/lstab-table.js' ).read_text()
M   = json.loads( ( TU / 'markup.json' ).read_text() )

# Ile wierszy pokazuje arkusz po lewej. Dalej i tak nie widać, bo okienko ma
# sufit, a lista, która wychodzi poza kadr, mówi „jest tego więcej” lepiej niż
# lista urwana dokładnie na ostatnim wierszu.
WIERSZY = 5

# Litery kolumn arkusza — tyle, ile kolumn ma cennik.
LITERY = [ 'A', 'B', 'C', 'D', 'E' ]


def dla_divi( kod ):
	"""Kod tabeli tak, żeby przeżył wklejenie w moduł Kod."""
	kod = re.sub( r'>\s+<', '><', kod )
	kod = re.sub( r'\s*\n\s*', ' ', kod )
	# Suwak pod tabelą dostaje szerokość od skryptu; ta z renderu policzona jest
	# dla innego okna.
	kod = re.sub( r'(<div class="lstab-scrollbar-thumb"[^>]*?) style="[^"]*"', r'\1', kod )
	# Filtry, pobieranie i odsyłacze prowadzą na serwer albo w świat. To jest
	# pokaz na stronie sprzedażowej, więc nigdzie nie idą.
	kod = re.sub( r'href="(?!#)[^"]*"', 'href="#"', kod )
	return kod.strip()


TABELA = dla_divi( M[ 'pro' ] )

KROPKI = ( '<span class="lst-pl-kropka"></span><span class="lst-pl-kropka"></span>'
	'<span class="lst-pl-kropka"></span>' )

# Zielony znak arkusza Google. Jeden prosty kształt geometryczny, rysowany
# w treści, bo to jedyny sposób, żeby dostał kolor strony i skalował się z nią.
ZNAK = ( '<svg class="lst-pl-znak" viewBox="0 0 16 20" aria-hidden="true" focusable="false">'
	'<path d="M10 0H2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6l-6-6z" fill="currentColor" '
	'opacity=".22"></path>'
	'<path d="M10 0l6 6h-6V0z" fill="currentColor" opacity=".45"></path>'
	'<path d="M4 9h8v7H4V9zm1 1.5v1.5h2.5v-1.5H5zm3.5 0v1.5H11v-1.5H8.5zM5 13v1.5h2.5V13H5zm3.5 0v1.5H11V13H8.5z" '
	'fill="currentColor"></path></svg>' )

# Strzałka z arkusza do tabeli. Rysuje się raz, przy wejściu sekcji w kadr.
#
# Szeroki, niski łuk: zaczyna się przy prawym boku arkusza, biegnąc poziomo,
# i kończy pionowo, grotem do góry, pod dolną krawędzią okna ze stroną. Kąt ma
# tu znaczenie — łuk, który kończy się na ukos, pokazuje „gdzieś w bok”,
# a chodzi o to, żeby pokazywał „do góry, na stronę”. Ostatni odcinek krzywej
# jest pionowy, więc grot siedzi na kierunku, w którym ta krzywa naprawdę idzie.
#
# Bez „preserveAspectRatio: none”: przy rozciąganiu w jednej osi grot robi się
# krzywy, a jest jedyną częścią tego rysunku, która musi zostać sobą.
STRZALKA = ( '<svg class="lst-pl-luk" viewBox="0 0 160 96" aria-hidden="true" focusable="false">'
	'<path class="lst-pl-luk-linia" d="M12 80 C 66 80, 136 74, 136 22" fill="none" stroke="currentColor" '
	'stroke-width="3" stroke-linecap="round"></path>'
	'<path class="lst-pl-luk-grot" d="M128 33 L136 21 L144 33" fill="none" stroke="currentColor" '
	'stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path></svg>' )


def arkusz():
	"""Okienko arkusza: pasek liter, numery wierszy i surowe wartości."""
	naglowki = M[ 'headers' ][ : len( LITERY ) ]
	wiersze = M[ 'rows' ][ : WIERSZY ]

	litery = ( '<div class="lst-pl-litery"><span class="lst-pl-rog" aria-hidden="true"></span>'
		+ ''.join( '<span>' + x + '</span>' for x in LITERY ) + '</div>' )

	# Wiersz pierwszy to nagłówki arkusza — w arkuszu są zwykłym wierszem
	# i tutaj też nim są, tyle że grubszym.
	srodek = ( '<div class="lst-pl-wiersz jest-naglowkiem"><span class="lst-pl-nr">1</span>'
		+ ''.join( '<span class="lst-pl-cela">' + html.escape( x ) + '</span>' for x in naglowki )
		+ '</div>' )

	for i, wiersz in enumerate( wiersze ):
		srodek += ( '<div class="lst-pl-wiersz"><span class="lst-pl-nr">' + str( i + 2 ) + '</span>'
			+ ''.join( '<span class="lst-pl-cela">' + html.escape( x ) + '</span>'
				for x in wiersz[ : len( LITERY ) ] )
			+ '</div>' )

	return ( '<div class="lst-pl-okno jest-arkuszem">'
		'<div class="lst-pl-belka">' + KROPKI + ZNAK +
		'<span class="lst-pl-nazwa">prices.xls</span></div>'
		'<div class="lst-pl-siatka">' + litery + srodek + '</div>'
		'</div>' )


def strona():
	"""Okienko strony klienta: prawdziwa tabela z wtyczki."""
	return ( '<div class="lst-pl-okno jest-strona">'
		'<div class="lst-pl-belka">' + KROPKI +
		'<span class="lst-pl-nazwa">yourshop.com/prices</span>'
		'<span class="lst-pl-belka-prawa">checked 4 min ago</span></div>'
		'<div class="lst-pl-plansza">' + TABELA + '</div>'
		'</div>' )


# Cztery rzeczy, które widać w tabeli obok, nazwane po imieniu.
#
# Próbka jest zbudowana z tego samego, z czego zrobiła je wtyczka, w tych samych
# kolorach — czyli oko skacze z napisu na tabelę i z powrotem, i za każdym razem
# trafia. Metka ODDZIELNIE od próbki, bo próbka nie jest napisem: czytnik ekranu
# dostaje samo zdanie.
CZYTANKA = [
	( 'jest-pigulka', 'IN STOCK', 'a word becomes a badge' ),
	( 'jest-wierszem', '', 'a row paints itself' ),
	( 'jest-slupkiem', '', 'a number becomes a bar' ),
	( 'jest-przyciskiem', 'Open', 'a link becomes a button' ),
]


def czytanka():
	"""Legenda pod akapitem: próbka, a obok zdanie o tym, co ona jest."""
	pozycje = ''

	for klasa, slowo, zdanie in CZYTANKA:
		srodek = html.escape( slowo )

		# Słupek to dwa prostokąty, jeden w drugim, a nie przejście koloru:
		# utwardzenie na wrogie motywy gasi wszystkie obrazki tła, a to jest
		# właśnie obrazek tła.
		if 'jest-slupkiem' == klasa:
			srodek = '<span class="lst-pl-wypelnienie"></span>'

		pozycje += ( '<li class="lst-pl-pozycja">'
			'<span class="lst-pl-probka ' + klasa + '" aria-hidden="true">' + srodek + '</span>'
			'<span class="lst-pl-zdanie">' + html.escape( zdanie ) + '</span></li>' )

	return '<ul class="lst-pl-czytanka">' + pozycje + '</ul>'


def slowo():
	"""Kolumna z tekstem, po lewej stronie kompozycji.

	Mówi to, czego obrazek powiedzieć nie może: że nikt tego nie przepisywał
	i że tabela sama do arkusza wraca. Reszta to nazwanie po imieniu czterech
	rzeczy, które w tabeli obok widać, ale których nikt by nie nazwał
	ustawieniem, gdyby mu nie powiedzieć.
	"""
	return ( '<div class="lst-pl-slowo">'
		'<h2 class="lst-pl-naglowek">Nobody retyped a single row.</h2>'
		'<p class="lst-pl-akapit">On the left, the file you already keep. '
		'On the right, a real page with the plugin on it, drawing that same file '
		'in your colours and reading it again every fifteen minutes.</p>'
		+ czytanka() +
		'<p class="lst-pl-stopka-slowa">All four are settings, picked once.</p>'
		'<p class="lst-pl-dalej"><a class="lst-pl-odsylacz" href="ADRES-MOZLIWOSCI">'
		'See everything it can do<span class="lst-pl-grot-tekstowy" aria-hidden="true">&#8250;</span></a></p>'
		'</div>' )


SEKCJA = ( '<div class="lst-pl"><div class="lst-pl-rama"><div class="lst-pl-uklad">'
	+ slowo() +
	'<div class="lst-pl-scena">'
	+ strona() +
	'<div class="lst-pl-przod">'
	+ arkusz() +
	'<span class="lst-pl-strzalka">' + STRZALKA + '</span>'
	'</div>'
	'</div>'
	'</div></div></div>' )


STYL = r"""
/* ---------------------------------------------------------------- sekcja */

/*
 * Z arkusza na stronę, pokazane zamiast opisanego.
 *
 * Wartości są te same co w sekcjach strony głównej (landing/naglowek,
 * landing/dwie-minuty): ta sekcja stoi zaraz pod hero i ma wyglądać jak jego
 * dalszy ciąg.
 */
.lst-pl {
	--pl-mieta: 95, 227, 207;
	--pl-zielen: 74, 200, 130;
	--pl-tekst: #eaf3f1;
	--pl-tekst-2: #9db3b0;
	--pl-tekst-3: #8fa5a2;
	--pl-plyta: rgba( 13, 18, 17, .62 );
	--pl-linia: rgba( 138, 168, 163, .22 );
	--pl-ekran: #0a1110;
	--pl-ekran-gora: #131d1b;
	/* Arkusz jest odrobinę jaśniejszy od strony klienta: dwa okna w tym samym
	   kolorze czytają się jak jedno okno, a tu chodzi o to, że to są dwa różne
	   miejsca. */
	--pl-papier: #151e1d;
	--pl-mono: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;
	--pl-szeryf: "Inria Serif", "Iowan Old Style", Georgia, serif;
	--pl-luk: cubic-bezier( .23, 1, .32, 1 );

	font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif;
	color: var( --pl-tekst );

	--pl-szerokosc: 90%;
	--pl-max: 1240px;
	--pl-pelna: 100vw;
	/*
	 * O ile kompozycja wychodzi poza szynę, na której stoi reszta strony.
	 *
	 * Dokładnie tyle, ile zostaje z marginesu do krawędzi okna, ale nie więcej
	 * niż 6rem: okno strony klienta ma dojść najwyżej DO krawędzi ekranu i ani
	 * piksela dalej. Przycięte okno wyglądałoby jak zrzut zrobiony byle jak,
	 * a przycięta byłaby akurat kolumna z przyciskami, czyli to, czym ta tabela
	 * się chwali. Wystaje, a nie ucieka: tyle wystarczy, żeby było widać, że
	 * prawa strona jest szersza niż wszystko inne na tej stronie.
	 */
	--pl-wyjscie: min( 4rem, calc( max( 5vw, ( 100vw - var( --pl-max ) ) / 2 ) * .55 ) );

	width: var( --pl-pelna );
	margin: 0 calc( 50% - var( --pl-pelna ) / 2 );
	overflow-x: clip;
	padding: clamp( 1rem, 2vw, 1.8rem ) 0 clamp( 2rem, 4vw, 3.4rem );
}

.lst-pl.lst-pl { border: 0 !important; outline: 0 !important; background: none !important; }
.lst-pl * { box-sizing: border-box; }
.lst-pl br { display: none; }

/*
 * Reset po nazwie klasy, a nie po nazwie znacznika: pod spodem siedzi cały
 * arkusz stylów wtyczki i jego divów, spanów i odsyłaczy nie wolno tknąć.
 */
.lst-pl [class*="lst-pl-"] {
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

.lst-pl .lst-pl-rama {
	width: var( --pl-szerokosc );
	max-width: var( --pl-max );
	margin-inline: auto;
}

/* ------------------------------------------------------------ dwie kolumny */

/*
 * Słowo i obrazek obok siebie, ale nie po połowie.
 *
 * Kompozycja jest tu treścią, a nie ilustracją do tekstu, więc dostaje tyle
 * miejsca, ile potrzebuje, i jeszcze trochę: wychodzi poza szynę, na której
 * stoi reszta strony. Tabela z pięcioma kolumnami składa się w karty poniżej
 * 700 px SWOJEJ szerokości, a karty w tym miejscu nie mówiłyby nic o tym, co
 * wtyczka potrafi — więc dwie kolumny są dopiero wtedy, gdy obie naprawdę się
 * mieszczą. Niżej tekst staje nad obrazkiem i nic nie traci.
 */
.lst-pl .lst-pl-uklad {
	display: grid;
	gap: clamp( 1.6rem, 3vw, 2.6rem );
}

.lst-pl .lst-pl-slowo {
	display: grid;
	align-content: start;
	/* Wiersz tekstu dłuższy niż mniej więcej 70 znaków czyta się źle, a przy
	   jednej kolumnie nic go z boku nie trzyma. */
	max-width: 44rem;
}

.lst-pl .lst-pl-naglowek {
	font-family: var( --pl-szeryf );
	font-weight: 400;
	/* Tytuły tej witryny są szeryfowe i skalują się z oknem: jedyne miejsce,
	   w którym wolno wyjść poza 14, 18 i 20. Mniejszy niż tytuł w hero, bo stoi
	   zaraz pod nim i ma być jego dalszym ciągiem, a nie drugim otwarciem. */
	font-size: clamp( 1.5rem, 2.2vw, 2rem );
	line-height: 1.14;
	letter-spacing: -.01em;
	color: var( --pl-tekst );
	/* Dwa słowa w drugim wierszu zamiast jednego sierotki. Przeglądarka, która
	   tego nie zna, łamie tytuł po staremu i nic się nie psuje. */
	text-wrap: balance;
}

.lst-pl .lst-pl-akapit {
	margin-top: .9rem;
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.6;
	color: var( --pl-tekst-2 );
	text-wrap: pretty;
}

/* ------------------------------------------------------------- czytanka */

/*
 * Cztery rzeczy, które w tabeli obok widać, nazwane po imieniu.
 *
 * Bez tego są po prostu ładne: nikt nie zgadnie, że kolorowa pigułka i pasek
 * pod liczbą to USTAWIENIA, a nie sposób, w jaki ta jedna tabela została
 * narysowana. Próbki są w tych samych kolorach co tabela, więc oko skacze
 * z napisu na tabelę i za każdym razem trafia.
 *
 * Kolumna próbek ma stałą szerokość, żeby zdania zaczynały się równo: legenda,
 * w której początki wierszy skaczą, przestaje wyglądać na legendę.
 */
.lst-pl .lst-pl-czytanka {
	display: grid;
	gap: .5rem;
	margin-top: 1.5rem;
}

.lst-pl .lst-pl-pozycja {
	display: grid;
	grid-template-columns: 5.6rem minmax( 0, 1fr );
	align-items: center;
	gap: .9rem;
}

.lst-pl .lst-pl-probka {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 1.5rem;
	font-size: .875rem;   /* 14 px */
	line-height: 1;
	border-radius: 999px;
}

.lst-pl .lst-pl-probka.jest-pigulka {
	border: 1px solid rgba( var( --pl-mieta ), .5 );
	color: #a9ece1;
}

/* Wiersz i słupek to prostokąty, nie pigułki: w tabeli też nimi są. */
.lst-pl .lst-pl-probka.jest-wierszem,
.lst-pl .lst-pl-probka.jest-slupkiem {
	height: 1.35rem;
	border-radius: 5px;
}

.lst-pl .lst-pl-probka.jest-wierszem { background-color: #5a2733; }
.lst-pl .lst-pl-probka.jest-slupkiem { background-color: rgba( var( --pl-mieta ), .12 ); justify-content: flex-start; }

/* Dwa prostokąty, jeden w drugim, a nie przejście koloru: utwardzenie na wrogie
   motywy gasi wszystkie obrazki tła, a przejście jest obrazkiem tła. */
.lst-pl .lst-pl-wypelnienie {
	display: block;
	width: 58%;
	height: 100%;
	border-radius: 5px;
	background-color: rgba( var( --pl-mieta ), .32 );
}

.lst-pl .lst-pl-probka.jest-przyciskiem {
	padding: 0 .75rem;
	font-weight: 600;
	background-color: rgb( var( --pl-mieta ) );
	color: #06100f;
}

.lst-pl .lst-pl-zdanie {
	font-size: .875rem;   /* 14 px */
	line-height: 1.5;
	color: var( --pl-tekst-2 );
}

.lst-pl .lst-pl-stopka-slowa {
	margin-top: 1rem;
	font-size: .875rem;   /* 14 px */
	line-height: 1.5;
	color: var( --pl-tekst-3 );
}

.lst-pl .lst-pl-dalej { margin-top: 1.1rem; }

.lst-pl .lst-pl-odsylacz {
	display: inline-flex;
	align-items: baseline;
	gap: .4rem;
	font-size: .875rem;   /* 14 px */
	line-height: 1.5;
	color: rgb( var( --pl-mieta ) );
	text-decoration: none;
	border-bottom: 1px solid rgba( var( --pl-mieta ), .32 );
	padding-bottom: .12rem;
	transition: border-color .18s var( --pl-luk ), color .18s var( --pl-luk );
}

.lst-pl .lst-pl-odsylacz:hover { border-bottom-color: rgb( var( --pl-mieta ) ); }
.lst-pl .lst-pl-odsylacz:focus-visible { outline: 2px solid rgb( var( --pl-mieta ) ); outline-offset: 3px; border-radius: 2px; }
.lst-pl .lst-pl-grot-tekstowy { font-size: 1.125rem; line-height: 1; }

/* ------------------------------------------------------------- scena */

/*
 * Dwa okna, jedno za drugim.
 *
 * Strona klienta stoi z tyłu i zajmuje całą szerokość — bo to ona jest tym,
 * co się sprzedaje. Arkusz leży na niej z przodu, przy lewej krawędzi,
 * mniejszy: jest źródłem, a nie celem. Zachodzenie jednego na drugie robi tu
 * całą robotę, bo mówi „to jest to samo, tylko przepuszczone przez wtyczkę”
 * bez ani jednego słowa.
 */
.lst-pl .lst-pl-scena {
	position: relative;
	/* Miejsce na to, co arkusz wystaje poza okno strony: z lewej i pod spodem. */
	padding: 0 0 clamp( 4rem, 11vw, 10rem ) clamp( 0rem, 6vw, 7rem );
}

.lst-pl .lst-pl-przod {
	position: absolute;
	left: 0;
	bottom: 0;
	width: min( 34rem, 52% );
	display: grid;
}

/* ------------------------------------------------------------- okienka */

.lst-pl .lst-pl-okno {
	position: relative;
	display: flex;
	flex-direction: column;
	min-width: 0;
	border: 1px solid rgba( var( --pl-mieta ), .2 );
	border-radius: 14px;
	overflow: hidden;
	background-color: var( --pl-ekran );
	box-shadow: 0 22px 46px -30px rgba( 0, 0, 0, .95 ), 0 0 34px -18px rgba( var( --pl-mieta ), .3 );
}

/*
 * Okno strony ma sufit i gaśnie u dołu.
 *
 * Bez sufitu tabela ciągnie się aż do stopki z pobieraniem, a arkusz, który na
 * nią nachodzi, zasłania właśnie tę stopkę — czyli jedyną rzecz w tym oknie,
 * która jest przyciskiem. Z sufitem tabela kończy się zanikiem, arkusz kładzie
 * się na zanikniętym rogu i nic działającego nie ginie pod spodem. Zanik mówi
 * przy okazji to, co trzeba: wierszy jest więcej.
 */
.lst-pl .lst-pl-okno.jest-strona {
	margin-left: auto;
	width: 100%;
	max-height: clamp( 22rem, 40vw, 32rem );
}

/*
 * Zanik u dołu okna.
 *
 * Mówi „wierszy jest więcej” i jest zarazem tym, na czym leży arkusz: tam,
 * gdzie arkusz nachodzi na okno, tabela ma już gasnąć, żeby jego górna krawędź
 * nie ucinała ostrego napisu w pół. Dlatego w układzie dwukolumnowym zanik
 * zaczyna się wyżej: dokładnie tam, gdzie zaczyna się arkusz.
 */
.lst-pl .lst-pl-okno.jest-strona .lst-pl-plansza {
	min-height: 0;
	overflow: hidden;
	-webkit-mask-image: linear-gradient( to bottom, #000 var( --pl-zanik, 86% ), transparent var( --pl-koniec, 99% ) );
	mask-image: linear-gradient( to bottom, #000 var( --pl-zanik, 86% ), transparent var( --pl-koniec, 99% ) );
}

/* Arkusz jest bliżej czytelnika, więc jego cień jest mocniejszy i krótszy. */
.lst-pl .lst-pl-okno.jest-arkuszem {
	width: 100%;
	background-color: var( --pl-papier );
	border-color: rgba( var( --pl-zielen ), .3 );
	box-shadow: 0 26px 44px -22px rgba( 0, 0, 0, .95 ), 0 0 0 1px rgba( 0, 0, 0, .35 );
}

.lst-pl .lst-pl-belka {
	display: flex;
	align-items: center;
	gap: .45rem;
	flex: none;
	padding: .55rem .8rem;
	background-color: var( --pl-ekran-gora );
	border-bottom: 1px solid rgba( var( --pl-mieta ), .14 );
}

.lst-pl .lst-pl-okno.jest-arkuszem .lst-pl-belka {
	background-color: #18231f;
	border-bottom-color: rgba( var( --pl-zielen ), .2 );
}

.lst-pl .lst-pl-kropka {
	width: 8px;
	height: 8px;
	flex: none;
	border-radius: 50%;
	background-color: rgba( 138, 168, 163, .35 );
}

.lst-pl .lst-pl-znak {
	width: 13px;
	height: 16px;
	flex: none;
	margin-left: .5rem;
	color: rgb( var( --pl-zielen ) );
}

.lst-pl .lst-pl-nazwa,
.lst-pl .lst-pl-belka-prawa {
	font-family: var( --pl-mono );
	font-size: .875rem;   /* 14 px */
	line-height: 1.5;
	color: var( --pl-tekst-3 );
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.lst-pl .lst-pl-nazwa { margin-left: .4rem; color: var( --pl-tekst-2 ); }
.lst-pl .lst-pl-belka-prawa { margin-left: auto; padding-left: 1rem; }

/* ---------------------------------------------------------- siatka arkusza */

/*
 * Wiersze arkusza są tą samą treścią, którą wtyczka narysowała po prawej.
 * Rysowane z tych samych danych, więc nie da się ich rozjechać: zmiana cennika
 * zmienia obie strony naraz albo żadnej.
 */
.lst-pl .lst-pl-siatka {
	display: grid;
	font-family: var( --pl-mono );
	font-size: .875rem;   /* 14 px */
	line-height: 1.5;
	/* Arkusz urywa się u dołu: lista, która wychodzi poza kadr, mówi „jest tego
	   więcej” lepiej niż lista skończona równo na ostatnim wierszu. */
	max-height: 15rem;
	overflow: hidden;
	-webkit-mask-image: linear-gradient( to bottom, #000 78%, transparent 99% );
	mask-image: linear-gradient( to bottom, #000 78%, transparent 99% );
}

.lst-pl .lst-pl-litery,
.lst-pl .lst-pl-wiersz {
	display: grid;
	grid-template-columns: 1.9rem minmax( 0, 1.7fr ) minmax( 0, .8fr ) minmax( 0, 1fr ) minmax( 0, .7fr ) minmax( 0, 1.4fr );
	gap: 1px;
	background-color: rgba( 138, 168, 163, .16 );
}

.lst-pl .lst-pl-litery > span {
	padding: .2rem .45rem;
	background-color: #1d2a26;
	color: var( --pl-tekst-3 );
	text-align: center;
	letter-spacing: .1em;
}

.lst-pl .lst-pl-nr {
	padding: .35rem .3rem;
	background-color: #1d2a26;
	color: var( --pl-tekst-3 );
	text-align: center;
}

.lst-pl .lst-pl-cela {
	padding: .35rem .55rem;
	background-color: var( --pl-papier );
	color: var( --pl-tekst-2 );
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.lst-pl .lst-pl-wiersz.jest-naglowkiem .lst-pl-cela { color: var( --pl-tekst ); font-weight: 500; }

/* ------------------------------------------------------ tabela na stronie */

.lst-pl .lst-pl-plansza { padding: clamp( .6rem, 1.4vw, 1.1rem ); }

/* Sama tabela broni się swoim arkuszem; tu tylko tyle, żeby cudze marginesy
   nie wypchnęły jej poza szerokość sekcji. */
.lst-pl .lstab-container, .lst-pl .lstab { margin-inline: 0 !important; max-width: 100%; }

/* --------------------------------------------------------------- strzałka */

/*
 * Łuk z arkusza do tabeli, rysowany raz, kiedy sekcja wchodzi w kadr. Ruch ma
 * tu coś do powiedzenia — pokazuje kierunek, w którym idą dane — więc się
 * dzieje; i dzieje się raz, bo powtarzany w kółko przestałby cokolwiek mówić,
 * a widać go przez cały czas, kiedy się czyta.
 */
.lst-pl .lst-pl-strzalka {
	position: absolute;
	/*
	 * W pasie POD oknem strony, na prawo od arkusza.
	 *
	 * Przedtem łuk biegł przez tabelę i ginął w niej: cienka miętowa kreska na
	 * tle wierszy, pigułek i słupków, czyli na tle rzeczy, które same są
	 * miętowe. Tu ma pod sobą samo tło strony, więc widać ją całą, a grot i tak
	 * dochodzi do dolnej krawędzi okna i pokazuje, dokąd te dane idą.
	 *
	 * Wysokość równa dopełnieniu sceny od dołu, czyli dokładnie tyle, ile ten
	 * pas ma: łuk zaczyna się przy dolnej krawędzi arkusza i kończy przy dolnej
	 * krawędzi okna.
	 */
	left: 100%;
	bottom: 0;
	width: clamp( 8rem, 19vw, 14rem );
	height: clamp( 6rem, 13vw, 9.8rem );
	margin-left: clamp( .6rem, 2vw, 1.8rem );
	color: rgb( var( --pl-mieta ) );
	pointer-events: none;
}

.lst-pl .lst-pl-luk { display: block; width: 100%; height: 100%; overflow: visible; }

/* ------------------------------------------------------------------- ruch */

/*
 * Wszystko, co się tu rusza, rusza się raz i mówi coś o produkcie: łuk rysuje
 * kierunek, w którym idą dane, a wiersze tabeli przyjeżdżają po kolei, bo tak
 * właśnie arkusz ląduje na stronie. Nic nie startuje od „opacity: 0” do
 * odwołania — sekcja jest kompletna w pierwszej klatce, także na zrzucie całej
 * strony i na wydruku.
 */
@supports ( animation-timeline: view() ) {
	.lst-pl .lst-pl-luk-linia {
		stroke-dasharray: 200;
		animation: lst-pl-rysuj 900ms var( --pl-luk ) both;
		animation-timeline: view();
		animation-range: entry 10% cover 34%;
	}

	.lst-pl .lst-pl-luk-grot {
		animation: lst-pl-grot 300ms var( --pl-luk ) both;
		animation-timeline: view();
		animation-range: entry 26% cover 38%;
	}

	.lst-pl .lst-pl-okno {
		animation: lst-pl-wjazd 620ms var( --pl-luk ) both;
		animation-timeline: view();
		animation-range: entry 4% cover 26%;
	}

	.lst-pl .lstab-row:nth-child( -n + 6 ) {
		animation: lst-pl-wiersz 420ms var( --pl-luk ) both;
		animation-timeline: view( block );
		animation-range: entry 2% cover 22%;
	}

	.lst-pl .lstab-row:nth-child( 2 ) { animation-delay: 34ms; }
	.lst-pl .lstab-row:nth-child( 3 ) { animation-delay: 68ms; }
	.lst-pl .lstab-row:nth-child( 4 ) { animation-delay: 102ms; }
	.lst-pl .lstab-row:nth-child( 5 ) { animation-delay: 136ms; }
	.lst-pl .lstab-row:nth-child( 6 ) { animation-delay: 170ms; }
}

/* Linia startuje pełną kreską schowaną za przesunięciem, a nie przezroczysta:
   bez osi widoku łuk jest po prostu narysowany. */
@keyframes lst-pl-rysuj { from { stroke-dashoffset: 200; } }
@keyframes lst-pl-grot { from { transform: translateY( 8px ); } }
@keyframes lst-pl-wjazd { from { transform: translateY( 16px ); } }
@keyframes lst-pl-wiersz { from { transform: translateY( 7px ); } }

@media print {
	.lst-pl [class*="lst-pl-"],
	.lst-pl .lst-pl-luk-linia,
	.lst-pl .lst-pl-luk-grot,
	.lst-pl .lstab-row { animation: none !important; }
}

@media ( prefers-reduced-motion: reduce ) {
	/* „Mniej ruchu” znaczy mniej ruchu, nie mniej treści: wszystko, co ruch
	   pokazywał, zostaje na ekranie w stanie końcowym. */
	.lst-pl [class*="lst-pl-"],
	.lst-pl .lst-pl-luk-linia,
	.lst-pl .lst-pl-luk-grot,
	.lst-pl .lstab-row { animation: none !important; }
	.lst-pl .lst-pl-luk-linia { stroke-dasharray: none; }
}

/* --------------------------------------------- utwardzenie na wrogie motywy */

/*
 * Motyw pod modułem bywa pisany z „!important”. Twardo trzymane jest tylko to,
 * czym taki motyw rozbija układ: marginesy na boki, wyrównanie, wersaliki,
 * krój i cudze tła z ramkami.
 */
.lst-pl [class*="lst-pl-"] {
	margin-inline: 0 !important;
	background-image: none !important;
	text-align: left !important;
	text-transform: none !important;
	letter-spacing: normal !important;
	font-family: inherit !important;
}

.lst-pl .lst-pl-rama { margin-inline: auto !important; }

.lst-pl .lst-pl-okno { background-color: var( --pl-ekran ) !important; border: 1px solid rgba( var( --pl-mieta ), .2 ) !important; }
.lst-pl .lst-pl-okno.jest-arkuszem { background-color: var( --pl-papier ) !important; border-color: rgba( var( --pl-zielen ), .3 ) !important; }
.lst-pl .lst-pl-belka { background-color: var( --pl-ekran-gora ) !important; border: 0 !important; border-bottom: 1px solid rgba( var( --pl-mieta ), .14 ) !important; }
.lst-pl .lst-pl-okno.jest-arkuszem .lst-pl-belka { background-color: #18231f !important; }
.lst-pl .lst-pl-litery > span,
.lst-pl .lst-pl-nr { background-color: #1d2a26 !important; }
.lst-pl .lst-pl-cela { background-color: var( --pl-papier ) !important; }
.lst-pl .lst-pl-litery,
.lst-pl .lst-pl-wiersz { background-color: rgba( 138, 168, 163, .16 ) !important; }
.lst-pl .lst-pl-litery > span { text-align: center !important; }
.lst-pl .lst-pl-nr { text-align: center !important; }

.lst-pl .lst-pl-nazwa,
.lst-pl .lst-pl-belka-prawa,
.lst-pl .lst-pl-siatka { font-family: var( --pl-mono ) !important; }

/* Tytuł jest szeryfowy, a utwardzenie wyżej każe wszystkiemu dziedziczyć krój
   po sekcji. Wyjątek jeden i wypisany. */
.lst-pl .lst-pl-naglowek { font-family: var( --pl-szeryf ) !important; }

.lst-pl .lst-pl-probka.jest-wierszem { background-color: #5a2733 !important; }
.lst-pl .lst-pl-probka.jest-slupkiem { background-color: rgba( var( --pl-mieta ), .12 ) !important; }
.lst-pl .lst-pl-wypelnienie { background-color: rgba( var( --pl-mieta ), .32 ) !important; }
.lst-pl .lst-pl-probka.jest-przyciskiem { background-color: rgb( var( --pl-mieta ) ) !important; color: #06100f !important; }

/* ----------------------------------------------------------- dwie kolumny */

/*
 * Dopiero tutaj, bo dopiero tutaj obie kolumny się mieszczą.
 *
 * Poniżej tej szerokości tabela przestaje się mieścić w swoim oknie: najpierw
 * wystaje jej ostatnia kolumna i okno zaczyna się przewijać w bok, a jeszcze
 * niżej tabela składa się w karty. Karty w tym miejscu nie mówią nic o tym, co
 * wtyczka potrafi. Lepiej postawić tekst nad obrazkiem, niż mieć dwie kolumny
 * i pusty obrazek, więc próg jest tam, gdzie tabela naprawdę się mieści, i jest
 * zmierzony, a nie zgadnięty.
 */
@media ( min-width: 1240px ) {
	.lst-pl .lst-pl-uklad {
		/* Wąska kolumna na słowo, cała reszta na obrazek: to obrazek jest tu
		   treścią, a tekst go podpisuje. */
		grid-template-columns: clamp( 18rem, 21vw, 21rem ) minmax( 0, 1fr );
		align-items: start;
		gap: clamp( 2rem, 3.2vw, 3.4rem );
	}

	/* Tytuł zaczyna się mniej więcej tam, gdzie górna krawędź okna: wspólna
	   linia u góry jest tym, co trzyma dwie różne rzeczy obok siebie. */
	.lst-pl .lst-pl-slowo { max-width: none; padding-top: clamp( .25rem, 1.4vw, 1.4rem ); }

	.lst-pl .lst-pl-scena {
		/* Poza szynę, na prawo. Z „!important”, bo utwardzenie na wrogie motywy
		   wyżej zeruje marginesy na boki wszystkiemu, co ma klasę tej sekcji,
		   i bez tego kompozycja po cichu zostaje na szynie. */
		margin-right: calc( -1 * var( --pl-wyjscie ) ) !important;
		/* Arkusz wystaje z okna w lewo mniej niż przedtem: tam, gdzie kiedyś było
		   puste tło, stoi teraz kolumna z tekstem, a arkusz ma się o nią opierać,
		   nie na nią wchodzić. */
		padding-left: clamp( 0rem, 2.6vw, 3rem );
		/*
		 * Pas pod oknem, w którym leży arkusz i biegnie łuk. Liczony w rem,
		 * a nie w procentach szerokości, bo to on decyduje, JAK GŁĘBOKO arkusz
		 * wchodzi na okno, a to musi wyjść tak samo na każdym szerokim ekranie.
		 *
		 * Arkusz jest wysoki na 16.4rem i stoi dnem na dnie tego pasa, więc na
		 * okno wchodzi dokładnie tym, co zostaje: około siedemdziesięciu pikseli.
		 * Tyle, ile mierzy zanik u dołu okna. Wcześniej wchodził na nie o wiele
		 * głębiej i jego górna krawędź ucinała w pół wiersz tabeli, z nazwą
		 * produktu przeciętą na dwoje. Teraz kładzie się na tym, co i tak już
		 * gaśnie, i nic ostrego nie jest przecięte.
		 */
		padding-bottom: 12rem;
	}

	/*
	 * Zanik kończy się DOKŁADNIE tam, gdzie zaczyna się arkusz.
	 *
	 * Nie „mniej więcej tam”: krawędź arkusza jest nieprzezroczysta i wszystko,
	 * co pod nią wejdzie, zostaje ucięte w pół. Raz padło na drugi wiersz nazwy
	 * produktu i z „Insulated Bottle 750 ml” zostało „750”, co wygląda na
	 * usterkę tabeli, a nie na kompozycję. Kiedy tabela jest tam już całkiem
	 * przezroczysta, nie ma czego ucinać: wiersze rozpływają się i dopiero pod
	 * nimi leży arkusz.
	 */
	.lst-pl .lst-pl-okno.jest-strona {
		/*
		 * Stała wysokość, a nie ułamek szerokości okna przeglądarki.
		 *
		 * To ona decyduje, ile wierszy widać, i to od niej liczy się, gdzie
		 * kończy się zanik, a zaczyna arkusz. Przy wysokości zależnej od
		 * szerokości okna to samo ustawienie wypadało raz pod wierszem, raz
		 * w jego połowie, a przy 1180 px widać było dwa i pół wiersza: za mało,
		 * żeby malowany wiersz w ogóle się pokazał, a to on jest tu dowodem.
		 */
		max-height: 32rem;
		--pl-zanik: 72%;
		--pl-koniec: 85%;
	}

	/*
	 * Łuk na całą wysokość pasa, w proporcji swojego rysunku (160 : 96), żeby
	 * grot doszedł możliwie blisko dolnej krawędzi okna. Rysunek zachowuje
	 * proporcje sam z siebie, więc pudełko w innej proporcji zostawiłoby wokół
	 * niego puste pole i grot zatrzymałby się w połowie drogi.
	 */
	.lst-pl .lst-pl-strzalka { width: 20rem; height: 12rem; }

	/* Arkusz kładzie się na ROGU okna, a nie na jego środku: pigułki, słupki
	   i przyciski muszą zostać widoczne, bo po to ta tabela tu stoi. Na wąskiej
	   scenie te same 52 procent zakrywały połowę tabeli. */
	.lst-pl .lst-pl-przod { width: min( 23rem, 41% ); }
}

/* ------------------------------------------------------------------ wąsko */

/*
 * Wąsko okna przestają na siebie nachodzić i stają jedno pod drugim, a łuk
 * kładzie się w pionie. Arkusz idzie na górę, bo to on jest pierwszy w tej
 * historii, a na szerokim ekranie stoi z przodu tylko dlatego, że tam „z
 * przodu” znaczy „bliżej”, a nie „wcześniej”.
 */
@media ( max-width: 900px ) {
	.lst-pl .lst-pl-scena { display: grid; gap: 0; padding: 0; }
	.lst-pl .lst-pl-przod {
		position: static;
		width: auto;
		display: grid;
		justify-items: center;
		order: -1;
	}
	/* Wąsko strzałka wraca do zwykłego biegu tekstu i staje między oknami,
	   obrócona, bo droga biegnie teraz z góry na dół, a nie w bok. Na szerokim
	   ekranie jest ustawiona bezwzględnie przy prawym boku arkusza i tam by
	   została — czyli poza ekranem. */
	.lst-pl .lst-pl-strzalka {
		position: static;
		width: clamp( 2.6rem, 13vw, 3.6rem );
		height: clamp( 2.6rem, 13vw, 3.6rem );
		margin: 1.4rem 0 1.8rem;
		transform: rotate( 90deg );
	}
	.lst-pl .lst-pl-siatka { max-height: 13rem; }
	/* Okno na tyle wysokie, żeby było w nim co oglądać: przy ułamku szerokości
	   okna przeglądarki mieściły się tu dwa wiersze i zanik. */
	.lst-pl .lst-pl-okno.jest-strona { max-height: clamp( 24rem, 62vw, 32rem ); }
}
"""

STRONA = (
	'<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Inria+Serif:ital,wght@0,300;0,400&display=swap">\n'
	'\n' + SEKCJA + '\n'
	'\n<style>\n' + STYL + CSS + '\n</style>\n'
	'\n<script>\n' + JS + '\n</script>\n'
)

( TU / 'PRZEPLYW-en.html' ).write_text( STRONA )

# Podgląd: podrabia tło witryny (#232a29 i siatkę 88 × 44, jak
# landing/naglowek/HERO-podglad.html) i dopełnienia Divi. Do Divi idzie
# wyłącznie PRZEPLYW-en.html.
PODGLAD = (
	'<!doctype html>\n<html lang="en">\n<meta charset="utf-8">\n'
	'<title>From a sheet to your page</title>\n'
	'<style>\n'
	'html, body { margin: 0; background: #232a29; color: #eaf3f1;\n'
	'\tfont-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif; }\n'
	'body { background-image: linear-gradient( to right, rgba( 255, 255, 255, .04 ) 1px, transparent 1px ),\n'
	'\tlinear-gradient( to bottom, rgba( 255, 255, 255, .04 ) 1px, transparent 1px );\n'
	'\tbackground-size: 88px 44px; }\n'
	'.podrobka-divi { padding: 60px 0; }\n'
	'.podrobka-divi-rzad { width: 90%; max-width: 1800px; margin: 0 auto; }\n'
	'</style>\n'
	'<div class="podrobka-divi"><div class="podrobka-divi-rzad">\n'
	+ STRONA +
	'\n</div></div>\n'
)

( TU / 'PODGLAD.html' ).write_text( PODGLAD )

print( 'ok', len( STRONA ), 'znaków modułu' )
