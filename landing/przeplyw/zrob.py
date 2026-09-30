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


SEKCJA = ( '<div class="lst-pl"><div class="lst-pl-rama">'
	'<div class="lst-pl-scena">'
	+ strona() +
	'<div class="lst-pl-przod">'
	+ arkusz() +
	'<span class="lst-pl-strzalka">' + STRZALKA + '</span>'
	'</div>'
	'</div>'
	'</div></div>' )


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
	--pl-luk: cubic-bezier( .23, 1, .32, 1 );

	font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif;
	color: var( --pl-tekst );

	--pl-szerokosc: 90%;
	--pl-max: 1240px;
	--pl-pelna: 100vw;

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

.lst-pl .lst-pl-okno.jest-strona .lst-pl-plansza {
	min-height: 0;
	overflow: hidden;
	-webkit-mask-image: linear-gradient( to bottom, #000 86%, transparent 99% );
	mask-image: linear-gradient( to bottom, #000 86%, transparent 99% );
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
}
"""

STRONA = (
	'<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">\n'
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
