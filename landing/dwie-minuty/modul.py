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

# I jeszcze pusty znacznik zaraz za nawiasem otwierającym.
#
# Same encje nie wystarczyły. Divi zapisuje treść modułu po swojemu i potrafi
# zamienić „&#91;" z powrotem na „[" — a wtedy WordPress widzi prawdziwy
# shortcode, wykonuje go i w okienku, zamiast przykładu do przeczytania,
# pojawia się komunikat wtyczki („To źródło arkusza już nie istnieje"), w
# dodatku w języku witryny, na angielskiej stronie. Widać to było na żywo.
#
# Shortcode rozpoznaje się po nazwie STYKAJĄCEJ SIĘ z nawiasem, więc pusty
# znacznik między jednym a drugim wyklucza dopasowanie raz na zawsze, cokolwiek
# zrobi się z encjami. Dla czytającego i dla kopiującego nic się nie zmienia:
# znacznik nie wnosi do tekstu ani jednego znaku.
PRZERWA = '<span class="lst-2m-nic"></span>'

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
		  'Your page', L + PRZERWA + 'sheet_table id=&quot;1&quot;' + P,
		  'table #1 &middot; 128 rows &middot; checked 4 min ago' ),
	],
	'liczby': [
		( 'pole', '0', 'API keys to paste, for a shared sheet',
		  'You share it with a link and that is the setup. Pro can sign in to your own Google '
		  'account instead, for sheets you would rather keep private.' ),
		( 'wiersze', 'No limit', 'on rows, in the free version',
		  'No watermark and no expiry date either. Free is the whole plugin, minus the extras.' ),
		( 'zegar', '15 min', 'between checks, and nobody waits',
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
		  'Twoja strona', L + PRZERWA + 'sheet_table id=&quot;1&quot;' + P,
		  'tabela #1 &middot; 128 wierszy &middot; sprawdzona 4 min temu' ),
	],
	'liczby': [
		( 'pole', '0', 'kluczy API do wklejenia, przy arkuszu z linkiem',
		  'Udostępniasz arkusz linkiem i to cała konfiguracja. W Pro wtyczka może zamiast tego '
		  'zalogować się na Twoje konto Google &mdash; wtedy arkusz zostaje prywatny.' ),
		( 'wiersze', 'Bez limitu', 'wierszy, w wersji darmowej',
		  'Bez znaku wodnego i bez daty ważności. Darmowa to cała wtyczka, tyle że bez dodatków.' ),
		( 'zegar', '15 min', 'między sprawdzeniami, i nikt nie czeka',
		  'Wtyczka rozmawia z Google w tle. Odwiedzający dostaje tabelę, która już leży na Twoim '
		  'serwerze.' ),
	],
}


"""
Trzy przyrządy, po jednym na liczbę.

Liczba mówi ILE, a rysunek obok mówi CZEGO — i to jest cała różnica między
tabelką z trzema liczbami a czymś, na co się patrzy. Każdy jest narysowany
z tego samego, z czego zbudowany jest produkt: puste pole, którego nie trzeba
wypełnić; wiersze, które się nie kończą; tarcza z kwadransem.

Rysowane, a nie pisane: znak nieskończoności albo zegar z kroju pisma wygląda
w każdym kroju inaczej, a w połowie z nich stoi za nisko. Każdy stoi w pudełku
120 na 72, więc wszystkie trzy wiszą na jednej linii, choć żaden nie jest
podobny do pozostałych.
"""
PRZYRZADY = {

	# Puste pole z migającym kursorem: nie ma czego wkleić.
	'pole': ( '<svg class="lst-2m-przyrzad" viewBox="0 0 120 72" aria-hidden="true" focusable="false">'
		'<rect class="lst-2m-ramka" x="1.5" y="6" width="117" height="60" rx="14" fill="none" '
		'stroke="currentColor" stroke-width="1.5" stroke-dasharray="7 6"></rect>'
		'<rect class="lst-2m-kursor" x="20" y="24" width="2.5" height="24" rx="1.25" '
		'fill="currentColor"></rect></svg>' ),

	# Wiersze, które gasną, zamiast się kończyć.
	'wiersze': ( '<svg class="lst-2m-przyrzad" viewBox="0 0 120 72" aria-hidden="true" focusable="false">'
		+ ''.join(
			'<rect class="lst-2m-belka-w" x="0" y="' + str( y ) + '" width="' + str( w ) + '" '
			'height="6" rx="3" fill="currentColor" opacity="' + o + '"></rect>'
			for y, w, o in (
				( 0, 118, '.95' ), ( 12, 104, '.8' ), ( 24, 112, '.62' ), ( 36, 88, '.44' ),
				( 48, 98, '.28' ), ( 60, 72, '.14' ),
			)
		)
		+ '</svg>' ),

	# Tarcza z zaznaczonym kwadransem.
	'zegar': ( '<svg class="lst-2m-przyrzad" viewBox="0 0 120 72" aria-hidden="true" focusable="false">'
		'<g transform="translate( 24 0 )">'
		'<circle cx="36" cy="36" r="33" fill="none" stroke="currentColor" stroke-width="1.5" '
		'opacity=".3"></circle>'
		+ ''.join(
			'<rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" rx="1" '
			'fill="currentColor" opacity=".45"></rect>'
			for x, y, w, h in (
				( '35', '0', '2', '7' ), ( '65', '35', '7', '2' ),
				( '35', '65', '2', '7' ), ( '0', '35', '7', '2' ),
			)
		)
		+ '<path class="lst-2m-kwadrans" d="M36 3 A33 33 0 0 1 69 36" fill="none" '
		'stroke="currentColor" stroke-width="5" stroke-linecap="round"></path>'
		'<circle class="lst-2m-wskaz" cx="69" cy="36" r="4.5" fill="currentColor"></circle>'
		'</g></svg>' ),
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
	/*
	 * Trzy liczby jako pasek, a nie trzy kafelki.
	 *
	 * Przedtem stały w zamkniętej ramce, przedzielone kreskami na krzyż —
	 * czyli w tabeli. Tabela z trzema liczbami wygląda jak zestawienie, przez
	 * które się przelatuje wzrokiem, a to jest miejsce, w którym sekcja mówi
	 * swoje trzy najmocniejsze zdania. Teraz jest to pas: jedna kreska nad nim,
	 * dwie cienkie pionowe w środku i dużo powietrza. Nic nie jest pudełkiem,
	 * więc liczby mają czym oddychać, a rysunki mają gdzie stanąć.
	 */
	display: grid;
	grid-template-columns: repeat( 3, minmax( 0, 1fr ) );
	column-gap: clamp( 1.6rem, 3.4vw, 3.6rem );
	position: relative;
	margin-top: clamp( 2.2rem, 4vw, 3.6rem );
	padding-top: clamp( 1.6rem, 3vw, 2.6rem );
}

/* Kreska nad pasem: zaczyna się miętowo tam, gdzie zaczyna się pierwsza
   liczba, i gaśnie w prawo. Jednolita szara na całą szerokość czytała się
   jak górna krawędź tabeli. */
.lst-2m .lst-2m-liczby::before {
	content: "";
	position: absolute;
	left: 0;
	right: 0;
	top: 0;
	height: 2px;
	border-radius: 2px;
	pointer-events: none;
	background-image: linear-gradient( to right,
		rgba( var( --lst-mieta ), .5 ),
		rgba( 138, 168, 163, .3 ) 34%,
		rgba( 138, 168, 163, .12 ) 72%,
		transparent ) !important;
}

.lst-2m .lst-2m-liczba {
	position: relative;
	display: grid;
	align-content: start;
	padding-right: clamp( 0rem, 1.4vw, 1.4rem );
}

/*
 * Kreska między kolumnami.
 *
 * Szara kreska o stałej jasności ginęła w tle: na ciemnej stronie 1 piksel
 * przy 22 procentach krycia to 1,2 : 1, czyli w praktyce nic. Podniesienie
 * krycia robi z niej ramkę tabeli, czyli dokładnie to, od czego ten pas
 * uciekł.
 *
 * Więc nie jednolita kreska, tylko ZANIKAJĄCA, i w kolorze akcentu: mocna
 * u góry, przy przyrządzie, gdzie oko właśnie jest, i gasnąca ku dołowi.
 * Widać ją od razu, a mimo to niczego nie zamyka — przy dolnej krawędzi nie
 * ma już czego domykać. Osobna warstwa, bo obramowania nie da się zrobić
 * przejściem koloru.
 */
.lst-2m .lst-2m-liczba + .lst-2m-liczba {
	padding-left: clamp( 1.6rem, 3.4vw, 3.6rem );
}

.lst-2m .lst-2m-liczba + .lst-2m-liczba::after {
	content: "";
	position: absolute;
	left: 0;
	top: 0;
	bottom: 1.5rem;
	width: 2px;
	border-radius: 2px;
	pointer-events: none;
	background-image: linear-gradient( to bottom,
		rgba( var( --lst-mieta ), .5 ),
		rgba( var( --lst-mieta ), .22 ) 38%,
		rgba( 138, 168, 163, .07 ) 78%,
		transparent ) !important;
}

/*
 * Poświata pod przyrządem.
 *
 * Kolor ma tu robotę do zrobienia: liczby są jedynym miętowym miejscem w tej
 * sekcji i mają się świecić jak wskazania przyrządu, a nie leżeć na płasko.
 *
 * Osobna warstwa, a nie tło kolumny, bo tło kończy się na krawędzi kolumny
 * i widać wtedy jasny prostokąt z ostrymi bokami. Ta warstwa wystaje poza
 * kolumnę i gaśnie w powietrzu. „isolation: isolate" robi z kolumny własne
 * piętro, więc warstwa spod spodu ląduje pod jej treścią, a nie pod tłem
 * całej strony, gdzie po prostu jej nie widać.
 */
.lst-2m .lst-2m-liczba { isolation: isolate; }

.lst-2m .lst-2m-liczba::before {
	content: "";
	position: absolute;
	left: -3rem;
	top: -3.5rem;
	width: 17rem;
	height: 13rem;
	z-index: -1;
	pointer-events: none;
	background-image: radial-gradient( closest-side,
		rgba( var( --lst-mieta ), .16 ), transparent ) !important;
}

.lst-2m .lst-2m-liczba + .lst-2m-liczba::before { left: clamp( -1.4rem, 1vw, 0rem ); }

/* ---------- przyrządy ---------- */

/*
 * Rysunek nad liczbą, w jej kolorze, zawsze tej samej wysokości: trzy różne
 * rzeczy wiszą wtedy na jednej linii i pas czyta się jak jeden przyrząd,
 * a nie jak trzy naklejki.
 */
.lst-2m .lst-2m-przyrzad {
	display: block;
	width: 7.5rem;
	height: 4.5rem;
	margin-bottom: .9rem;
	color: rgb( var( --lst-mieta ) );
	overflow: visible;
}

.lst-2m .lst-2m-liczba.jest-pole .lst-2m-przyrzad { color: rgba( var( --lst-mieta ), .85 ); }

.lst-2m .lst-2m-duza {
	font-family: var( --lst-serif );
	font-weight: 400;
	/* Duża liczba to jeden z dwóch wyjątków od 14, 18 i 20 na tej witrynie
	   (drugim są tytuły sekcji) i tu ten wyjątek ma być widać. */
	font-size: clamp( 2.4rem, 4.4vw, 3.8rem );
	line-height: .95;
	letter-spacing: -.02em;
	color: rgb( var( --lst-mieta ) );
	margin-bottom: .55rem;
}

.lst-2m .lst-2m-pod {
	font-size: 1.125rem;   /* 18 px */
	font-weight: 600;
	line-height: 1.35;
	margin-bottom: .4rem;
}

.lst-2m .lst-2m-tekst {
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.55;
	color: var( --lst-tekst-2 );
	max-width: 26rem;
}

/* ---------- ruch przyrządów ---------- */

/*
 * Każdy rusza się raz, kiedy pas wchodzi w kadr, i każdy mówi ruchem to samo,
 * co mówi liczbą: kursor mruga w pustym polu, wiersze wjeżdżają jeden po
 * drugim i nie kończą się, kwadrans rysuje się na tarczy.
 *
 * Nic nie startuje niewidoczne: bez osi widoku wszystko jest po prostu
 * narysowane, więc pas jest kompletny w pierwszej klatce, także na zrzucie
 * całej strony i na wydruku.
 */
@supports ( animation-timeline: view() ) {
	/*
	 * Kreski ramki przebiegają raz dookoła pustego pola. Kursor NIE mruga na
	 * osi widoku: mruganie to znikanie, a na osi widoku „zniknięte" zostaje
	 * tak długo, jak długo strona stoi w tym miejscu — czyli dowolnie długo.
	 * Pierwsza wersja gubiła przez to kursor na zrzucie i na wydruku.
	 */
	.lst-2m .lst-2m-ramka {
		animation: lst-2m-marsz 900ms linear both;
		animation-timeline: view();
		animation-range: entry 8% cover 30%;
	}

	.lst-2m .lst-2m-belka-w {
		animation: lst-2m-wjazd 500ms cubic-bezier( .23, 1, .32, 1 ) both;
		animation-timeline: view();
		animation-range: entry 8% cover 28%;
	}

	.lst-2m .lst-2m-belka-w:nth-child( 2 ) { animation-delay: 60ms; }
	.lst-2m .lst-2m-belka-w:nth-child( 3 ) { animation-delay: 120ms; }
	.lst-2m .lst-2m-belka-w:nth-child( 4 ) { animation-delay: 180ms; }
	.lst-2m .lst-2m-belka-w:nth-child( 5 ) { animation-delay: 240ms; }
	.lst-2m .lst-2m-belka-w:nth-child( 6 ) { animation-delay: 300ms; }

	.lst-2m .lst-2m-kwadrans {
		stroke-dasharray: 48;
		animation: lst-2m-rysuj 800ms cubic-bezier( .23, 1, .32, 1 ) both;
		animation-timeline: view();
		animation-range: entry 8% cover 32%;
	}

	.lst-2m .lst-2m-wskaz {
		animation: lst-2m-wskaz 800ms cubic-bezier( .23, 1, .32, 1 ) both;
		animation-timeline: view();
		animation-range: entry 8% cover 32%;
	}
}

@keyframes lst-2m-marsz { from { stroke-dashoffset: 52; } }
@keyframes lst-2m-wjazd { from { transform: translateX( -10px ); } }
@keyframes lst-2m-rysuj { from { stroke-dashoffset: 48; } }
@keyframes lst-2m-wskaz { from { transform: rotate( -90deg ); } }

/* Wskazówka obraca się wokół środka tarczy, a nie wokół rogu rysunku. */
.lst-2m .lst-2m-wskaz { transform-origin: 36px 36px; }

/* ---------- najechanie ---------- */

/*
 * Tylko tam, gdzie jest czym najechać. Przyrząd budzi się pod kursorem: pole
 * przestaje być kreskowane, bo właśnie takie pole się zaznacza, a tarcza
 * jaśnieje. Nic się nie przesuwa, bo pas nie jest przyciskiem.
 */
@media ( hover: hover ) and ( pointer: fine ) {
	.lst-2m .lst-2m-ramka,
	.lst-2m .lst-2m-kwadrans,
	.lst-2m .lst-2m-belka-w { transition: opacity .25s ease, stroke-dasharray .25s ease; }

	.lst-2m .lst-2m-liczba:hover .lst-2m-ramka { stroke-dasharray: 130 0; }
	.lst-2m .lst-2m-liczba:hover .lst-2m-belka-w { opacity: 1; }
}

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
	.lst-2m .lst-2m-liczby { grid-template-columns: 1fr; row-gap: clamp( 1.4rem, 5vw, 2rem ); }
	.lst-2m .lst-2m-liczba + .lst-2m-liczba {
		padding-left: 0;
		padding-top: clamp( 1.4rem, 5vw, 2rem );
	}
	/* Wąsko kolumny stoją jedna pod drugą, więc kreska kładzie się w poprzek
	   i gaśnie w prawo, tam, gdzie kończy się tekst. */
	.lst-2m .lst-2m-liczba + .lst-2m-liczba::after {
		left: 0;
		right: 20%;
		top: 0;
		bottom: auto;
		width: auto;
		height: 2px;
		background-image: linear-gradient( to right,
			rgba( var( --lst-mieta ), .5 ),
			rgba( var( --lst-mieta ), .22 ) 38%,
			rgba( 138, 168, 163, .07 ) 78%,
			transparent ) !important;
	}
	.lst-2m .lst-2m-liczba + .lst-2m-liczba::before { left: clamp( -1rem, -2vw, -.5rem ); }
}

@media ( prefers-reduced-motion: reduce ) {
	/* „Mniej ruchu" znaczy mniej ruchu, nie mniej treści: przyrządy zostają na
	   ekranie w stanie końcowym. */
	.lst-2m .lst-2m-okno { transition: none; }
	.lst-2m .lst-2m-ramka,
	.lst-2m .lst-2m-belka-w,
	.lst-2m .lst-2m-kwadrans,
	.lst-2m .lst-2m-wskaz { animation: none !important; }
	.lst-2m .lst-2m-kwadrans { stroke-dasharray: none; }
}

@media print {
	.lst-2m .lst-2m-ramka,
	.lst-2m .lst-2m-belka-w,
	.lst-2m .lst-2m-kwadrans,
	.lst-2m .lst-2m-wskaz { animation: none !important; }
	.lst-2m .lst-2m-kwadrans { stroke-dasharray: none; }
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

	for przyrzad, duza, pod, tekst in t[ 'liczby' ]:
		liczby.append(
			'\t\t\t<div class="lst-2m-liczba jest-' + przyrzad + '">'
			+ PRZYRZADY[ przyrzad ] +
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

	# Nazwa shortcode'u nigdzie nie może stykać się z nawiasem, ani wprost, ani
	# przez encję: Divi zamienia encje z powrotem na nawiasy, a WordPress
	# wykonuje wtedy to, co miało być przykładem do przeczytania.
	for zbitka in ( '[sheet_table', '&#91;sheet_table', '&#x5B;sheet_table' ):
		if zbitka in html:
			raise SystemExit( plik + ': „' + zbitka + '" — WordPress wykona to jako shortcode' )

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
