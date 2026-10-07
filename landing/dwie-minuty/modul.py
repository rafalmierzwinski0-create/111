# -*- coding: utf-8 -*-
"""
„Z arkusza na stronę w dwie minuty" — siatka pięciu kafli, jeden moduł Kod.

Kafle odpowiadają na to, o co pyta ktoś, kto widzi stronę pierwszy raz: co to
robi, komu pomaga, jak się to ustawia, dlaczego można temu ufać i co zrobić
dalej. Wcześniej stały tu trzy pary „krok i okienko", które mówiły tylko „jak".
Każde zdanie w kaflach jest prawdą o wtyczce (readme.txt) — żadnych
wymyślonych liczb klientów ani opinii.

Po krawędzi każdego kafla biegnie światło, a ikonki budzą się pod kursorem.
Pas z trzema liczbami (0 kluczy API, bez limitu wierszy, 15 minut), który
stał pod kaflami, został usunięty: powtarzał to, co kafle już mówią.

Bez skryptu. Sam kod strony i style.

UWAGA przy edycji: każdy znacznik musi zostać w jednej linijce. Divi wstawia
w miejscu złamanego wiersza <br />, co rozbija znacznik.
"""

import re

# Nawiasy kwadratowe tylko jako encje — inaczej WordPress wziąłby je
# za shortcode i wykonał, zamiast pokazać.
L, P = '&#91;', '&#93;'

# I nawias otwierający we własnym znaczniku.
#
# Same encje nie wystarczyły. Divi zapisuje treść modułu po swojemu i potrafi
# zamienić „&#91;" z powrotem na „[" — a wtedy WordPress widzi prawdziwy
# shortcode, wykonuje go i zamiast przykładu do przeczytania pojawia się
# komunikat wtyczki („To źródło arkusza już nie istnieje"), w dodatku w języku
# witryny, na angielskiej stronie. Widać to było na żywo.
#
# Shortcode rozpoznaje się po nazwie STYKAJĄCEJ SIĘ z nawiasem, więc znacznik
# między jednym a drugim wyklucza dopasowanie. Był tu pusty <span>, ale edytor
# Divi pusty znacznik po cichu wyrzuca. Nawias w środku znacznika sprawia, że
# nie jest pusty i zostaje. Dla czytającego i kopiującego nic się nie zmienia.
NAWIAS = '<span class="lst-2m-nawias">' + L + '</span>'
PRZYKLAD = NAWIAS + 'sheet_table id=&quot;X&quot;' + P

EN = {
	# Kafle. Każdy ma etykietę (pytanie, na które odpowiada), zdanie-tytuł
	# i treść. Tytuły są zdaniami, a nie hasłami: czyta się je jak odpowiedź.
	'kafle': {
		'robi': ( 'What it does', 'Change a cell in the sheet. The page follows.',
			'Your Google Sheet becomes a real table on your WordPress page &mdash; and keeps itself up to date.' ),
		'arkusz': ( 'Prices', 'Google Sheets', ( 'Product', 'Price' ),
			( ( 'Oak table', '1 290' ), ( 'Ash chair', '349 &rarr; 319' ), ( 'Pine shelf', '215' ) ) ),
		'strona': ( 'yourshop.com/prices', ( 'Product', 'Price' ),
			( ( 'Oak table', '1 290' ), ( 'Ash chair', '319' ), ( 'Pine shelf', '215' ) ),
			'updated 2 min ago', '3 rows' ),
		'co_ile': 'every 15 min',
		# Co dostaje odwiedzający — to, czego reszta kafli nie mówi.
		'cechy': ( 'On your page', (
			( 'lupa', 'Search and sorting', 'Built in, across the whole sheet.' ),
			( 'telefon', 'Cards on a phone', 'Each row becomes a card, no sideways scroll.' ),
			( 'paleta', 'Your colours', 'Set in a visual editor, no code.' ),
		) ),
		'komu': ( 'Who it helps', 'Anyone whose numbers live in a spreadsheet.',
			'If people keep asking you for &ldquo;the latest version&rdquo;, it belongs on your page &mdash; '
			'edited where you already edit it.' ),
		'zetony': ( 'Price lists', 'Timetables', 'Stock levels', 'Menus', 'Event line-ups',
			'Trail conditions', 'Rankings', 'Opening hours' ),
		# Przykłady, a nie klienci: kto i co trzyma w arkuszu. Nikt tu nie
		# udaje, że to prawdziwa firma z prawdziwą opinią.
		'przyklady': ( 'For example', (
			( 'cena', 'A furniture shop', 'Sales update prices in the sheet; the price list on the site follows.' ),
			( 'puchar', 'A sports club', 'The league table is filled in after every match and is on the site right away.' ),
			( 'gory', 'A ski area', 'Trail and snow conditions go into the sheet each morning, before the lifts open.' ),
		) ),
		'jak': ( 'How it works', 'Three steps, no API key.' ),
		'kroki': (
			( 'Share the sheet', 'Anyone with the link, as a Viewer.' ),
			( 'Paste the link, check the preview', 'Nothing goes live until you save.' ),
			( 'Put it on the page', 'A block, an Elementor widget or <code class="lst-2m-kod">' + PRZYKLAD + '</code>' ),
		),
		'ufac': ( 'Why it holds up', 'Your page never shows a broken table.' ),
		'pewne': (
			( 'tarcza', 'The last good copy stays', 'If Google is unreachable or the sheet goes private, visitors still see your table.' ),
			( 'serwer', 'Served from your server', 'Fetched in the background, so nobody waits on Google.' ),
			( 'tabela', 'A real HTML table', 'Search engines can read it, and it is drawn before any code runs.' ),
			( 'klodka', 'Talks to Google only', 'No analytics, no account with us. The data stays in your database.' ),
		),
		'dalej': ( 'Try it on your own sheet.',
			'Free, no row limit, no watermark. You see the table in the preview before anything is published.' ),
		'przyciski': ( ( 'Download free', '#pricing' ), ( 'See what Pro adds', '#compare' ) ),
	},
}

PL = {
	'kafle': {
		'robi': ( 'Co robi', 'Zmieniasz komórkę w arkuszu. Strona idzie za nim.',
			'Arkusz Google staje się prawdziwą tabelą na stronie WordPress &mdash; i sam pilnuje, żeby była aktualna.' ),
		'arkusz': ( 'Ceny', 'Arkusze Google', ( 'Produkt', 'Cena' ),
			( ( 'Stół dębowy', '1 290' ), ( 'Krzesło', '349 &rarr; 319' ), ( 'Półka', '215' ) ) ),
		'strona': ( 'twojsklep.pl/cennik', ( 'Produkt', 'Cena' ),
			( ( 'Stół dębowy', '1 290' ), ( 'Krzesło', '319' ), ( 'Półka', '215' ) ),
			'zaktualizowano 2 min temu', '3 wiersze' ),
		'co_ile': 'co 15 min',
		'cechy': ( 'Na Twojej stronie', (
			( 'lupa', 'Szukanie i sortowanie', 'Wbudowane, po całym arkuszu.' ),
			( 'telefon', 'Karty na telefonie', 'Każdy wiersz to karta, bez przewijania w bok.' ),
			( 'paleta', 'Twoje kolory', 'W edytorze wizualnym, bez kodu.' ),
		) ),
		'komu': ( 'Komu pomaga', 'Każdemu, kto trzyma liczby w arkuszu.',
			'Jeśli ktoś ciągle prosi Cię o &bdquo;najnowszą wersję&rdquo;, jej miejsce jest na stronie &mdash; '
			'edytowanej tam, gdzie i tak edytujesz.' ),
		'zetony': ( 'Cenniki', 'Rozkłady jazdy', 'Stany magazynowe', 'Menu', 'Programy wydarzeń',
			'Warunki na szlakach', 'Rankingi', 'Godziny otwarcia' ),
		'przyklady': ( 'Na przykład', (
			( 'cena', 'Sklep meblowy', 'Dział sprzedaży zmienia ceny w arkuszu, a cennik na stronie idzie za nim.' ),
			( 'puchar', 'Klub sportowy', 'Tabela ligowa uzupełniana po każdym meczu od razu jest na stronie.' ),
			( 'gory', 'Ośrodek narciarski', 'Stan tras i śniegu trafia do arkusza każdego ranka, zanim ruszą wyciągi.' ),
		) ),
		'jak': ( 'Jak to działa', 'Trzy kroki, bez klucza API.' ),
		'kroki': (
			( 'Udostępnij arkusz', 'Każda osoba mająca link, jako Przeglądający.' ),
			( 'Wklej link i sprawdź podgląd', 'Nic nie trafia na stronę, zanim zapiszesz.' ),
			( 'Wstaw na stronę', 'Blok, widżet Elementora albo <code class="lst-2m-kod">' + PRZYKLAD + '</code>' ),
		),
		'ufac': ( 'Dlaczego to wytrzyma', 'Strona nigdy nie pokaże zepsutej tabeli.' ),
		'pewne': (
			( 'tarcza', 'Zostaje ostatnia dobra kopia', 'Gdy Google nie odpowiada albo arkusz stanie się prywatny, odwiedzający nadal widzą tabelę.' ),
			( 'serwer', 'Podawana z Twojego serwera', 'Pobierana w tle, więc nikt nie czeka na Google.' ),
			( 'tabela', 'Prawdziwa tabela HTML', 'Czytelna dla wyszukiwarek, nawet bez skryptów.' ),
			( 'klodka', 'Rozmawia tylko z Google', 'Bez analityki i bez konta u nas. Dane zostają w Twojej bazie.' ),
		),
		'dalej': ( 'Wypróbuj na swoim arkuszu.',
			'Za darmo, bez limitu wierszy, bez znaku wodnego. Tabelę widzisz w podglądzie, zanim cokolwiek zostanie opublikowane.' ),
		'przyciski': ( ( 'Pobierz za darmo', '#cennik' ), ( 'Zobacz, co daje Pro', '#porownanie' ) ),
	},
}


"""
Ikonki przy powodach, dla których można zaufać. Rysowane kreską w kolorze
tekstu, w kwadraciku z miętową krawędzią. Pod kursorem kwadracik zapala się
na mięto, a w ikonce coś się dorysowuje: ptaszek w tarczy, diody w serwerze,
kolumna w tabeli, kłódka się zamyka. Ruch mówi to samo, co zdanie obok.
"""
def _ikona( srodek ):
	return ( '<svg class="lst-2m-ikona-rys" viewBox="0 0 28 28" fill="none" stroke="currentColor" '
		'stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" '
		'focusable="false">' + srodek + '</svg>' )

IKONY = {
	'tarcza': _ikona( '<path d="M14 3.5 5.5 7v6.5c0 5 3.6 9.3 8.5 11 4.9-1.7 8.5-6 8.5-11V7Z"></path>'
		'<path class="lst-2m-dorys" pathLength="1" d="m10 14 3 3 5.5-6"></path>' ),
	'serwer': _ikona( '<rect x="4.5" y="5" width="19" height="7.5" rx="2"></rect>'
		'<rect x="4.5" y="15.5" width="19" height="7.5" rx="2"></rect>'
		'<path class="lst-2m-dioda" d="M8.5 8.75h.01M8.5 19.25h.01M12 8.75h.01M12 19.25h.01"></path>' ),
	'tabela': _ikona( '<rect x="4" y="5" width="20" height="18" rx="2.5"></rect><path d="M4 10.5h20M11 10.5V23"></path>'
		'<path class="lst-2m-dorys" pathLength="1" d="M17.5 10.5V23"></path>' ),
	'klodka': _ikona( '<rect x="6" y="12" width="16" height="11" rx="2.5"></rect>'
		'<path class="lst-2m-palak" d="M9.5 12V9a4.5 4.5 0 0 1 9 0v3"></path>' ),
}

# Ikonki przykładów: metka z ceną, puchar, góra ze śniegiem na szczycie.
# Budzą się pod kursorem tak samo jak ikonki powodów.
IKONY.update( {
	'cena': _ikona( '<path d="M4.5 13.6V5.5a1 1 0 0 1 1-1h8.1l9 9-9.1 9.1Z"></path>'
		'<circle class="lst-2m-dioda" cx="9.5" cy="9.5" r="1.6"></circle>' ),
	'puchar': _ikona( '<path d="M9 4.5h10v5a5 5 0 0 1-10 0Z"></path><path d="M9 6.5H6.2a3 3 0 0 0 3.1 4.5M19 6.5h2.8a3 3 0 0 1-3.1 4.5M14 14.5v4M10 23.5h8M11.5 18.5h5"></path>'
		'<path class="lst-2m-dorys" pathLength="1" d="m12 8.5 1.5 1.5 2.5-3"></path>' ),
	'lupa': _ikona( '<circle cx="12.5" cy="12.5" r="7"></circle><path class="lst-2m-dorys" pathLength="1" d="m17.6 17.6 5.4 5.4"></path>' ),
	'telefon': _ikona( '<rect x="8" y="3.5" width="12" height="21" rx="2.5"></rect>'
		'<path class="lst-2m-dioda" d="M11 9h6M11 12.5h6M11 16h4"></path>' ),
	'paleta': _ikona( '<path d="M14 4a10 10 0 1 0 0 20c1.5 0 2-1 2-2s-1-1.5-1-2.5 1-1.5 2-1.5h2.5A4.5 4.5 0 0 0 24 13.5C24 8.3 19.5 4 14 4Z"></path>'
		'<circle class="lst-2m-dioda" cx="9.5" cy="13" r="1.3"></circle><circle class="lst-2m-dioda" cx="12.5" cy="8.8" r="1.3"></circle><circle class="lst-2m-dioda" cx="17.5" cy="9" r="1.3"></circle>' ),
	'gory': _ikona( '<path d="M3 23 10.5 9.5l4.6 7.6 3-4.6L25 23Z"></path>'
		'<path class="lst-2m-dorys" pathLength="1" d="m8.2 13.6 2.3 1.4 2.2-1.4"></path>' ),
} )

STRZALKA = ( '<svg class="lst-2m-strzalka-rys" viewBox="0 0 64 14" fill="none" stroke="currentColor" '
	'stroke-width="1.6" stroke-linecap="round" aria-hidden="true" focusable="false">'
	'<path class="lst-2m-bieg" d="M2 7h54"></path><path d="m54 2 6 5-6 5"></path></svg>' )

SZABLON = r'''<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Inria+Serif:wght@400&display=swap">

<div class="lst-2m">
	<div class="lst-2m-rama">
		<div class="lst-2m-kafle">
{KAFLE}
		</div>
	</div>
</div>

<style>
.lst-2m {
	--lst-mieta: 95, 227, 207;
	--lst-panel: #1b2221;
	--lst-panel-dol: #161d1c;
	--lst-linia: rgba( 138, 168, 163, .22 );

	--lst-tekst: #eaf3f1;
	--lst-tekst-2: #9db3b0;
	--lst-tekst-3: #8fa5a2;
	--lst-promien: 18px;
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

/* ---------- kafle ---------- */

/*
 * Pięć kafli w siatce dwunastu kolumn: dwa na górze (co robi, komu), dwa
 * w środku (jak, dlaczego), a pod nimi pas z tym, co zrobić dalej. Szerokości
 * są nierówne — 7 i 5, potem 5 i 7 — żeby siatka nie wyglądała jak tabela
 * czterech równych pudełek; większy kafel stoi raz z lewej, raz z prawej.
 */
.lst-2m .lst-2m-kafle {
	display: grid;
	grid-template-columns: repeat( 12, minmax( 0, 1fr ) );
	gap: clamp( .9rem, 1.4vw, 1.25rem );
}

/*
 * Głębia z trzech warstw: światło z góry (jasny pasek pod krawędzią),
 * poświata akcentu w rogu i cień pod spodem. Do tego światło biegnące po
 * obwodzie (patrz „światło biegnące po krawędzi” niżej).
 */
.lst-2m .lst-2m-kafel {
	position: relative;
	grid-column: span 6;
	padding: clamp( 1.3rem, 2.2vw, 1.75rem ) clamp( 1.2rem, 2vw, 1.7rem ) clamp( 1.4rem, 2.4vw, 1.8rem );
	border-radius: var( --lst-promien );
	border: 1px solid rgba( var( --lst-mieta ), .18 ) !important;
	background-color: var( --lst-panel ) !important;
	background-image:
		linear-gradient( 180deg, rgba( 255, 255, 255, .04 ), rgba( 255, 255, 255, 0 ) 42% ),
		radial-gradient( 22rem 14rem at var( --lst-x, 14% ) var( --lst-y, -8% ), rgba( var( --lst-mieta ), .15 ), transparent ),
		linear-gradient( var( --lst-panel ), var( --lst-panel-dol ) ) !important;
	box-shadow:
		inset 0 1px 0 rgba( 255, 255, 255, .05 ),
		0 20px 44px -26px rgba( 0, 0, 0, .95 ),
		0 2px 10px -6px rgba( 0, 0, 0, .6 );
}

.lst-2m .lst-2m-kafel.jest-robi { grid-column: span 7; }
.lst-2m .lst-2m-kafel.jest-komu { grid-column: span 5; --lst-x: 86%; }
.lst-2m .lst-2m-kafel.jest-jak { grid-column: span 5; --lst-y: 108%; }
.lst-2m .lst-2m-kafel.jest-ufac { grid-column: span 7; --lst-x: 86%; --lst-y: 108%; }

/* Na laptopie arkusz, strzałka i strona obok siebie nie mieszczą się
   w siedmiu kolumnach: górne kafle idą na całą szerokość, jeden pod drugim. */
@media ( max-width: 1280px ) {
	.lst-2m .lst-2m-kafel.jest-robi,
	.lst-2m .lst-2m-kafel.jest-komu { grid-column: 1 / -1; }
}

/* Pas „co dalej” na całą szerokość, z mocniejszą poświatą od lewej: to jedyne
   miejsce w sekcji, które prowadzi gdzieś dalej. */
.lst-2m .lst-2m-kafel.jest-dalej {
	grid-column: 1 / -1;
	display: flex;
	align-items: center;
	gap: 1.5rem 2rem;
	padding-block: clamp( 1.2rem, 2vw, 1.5rem );
	border-color: rgba( var( --lst-mieta ), .3 ) !important;
	background-image:
		linear-gradient( 100deg, rgba( var( --lst-mieta ), .16 ), rgba( var( --lst-mieta ), .04 ) 45%, rgba( 0, 0, 0, 0 ) 80% ),
		linear-gradient( var( --lst-panel ), var( --lst-panel-dol ) ) !important;
}

.lst-2m .lst-2m-etykieta {
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	letter-spacing: .1em;
	text-transform: uppercase;
	color: var( --lst-tekst-3 );
}

/*
 * Zdanie-tytuł kafla. Trzeci wyjątek od 14, 18 i 20 na tej stronie (po dużych
 * liczbach i tytułach sekcji): ten sam szeryf co tytuły sekcji, tylko
 * mniejszy. Tytuł jest zdaniem i ma się czytać jak odpowiedź, a nie jak hasło.
 */
.lst-2m .lst-2m-kafel-tytul {
	font-family: var( --lst-serif );
	font-weight: 400;
	font-size: clamp( 1.5rem, 2.1vw, 1.85rem );
	line-height: 1.15;
	letter-spacing: -.005em;
	margin-top: .5rem;
}

.lst-2m .lst-2m-kafel-tekst {
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.55;
	color: var( --lst-tekst-2 );
	margin-top: .55rem;
	max-width: 38rem;
}

/* ---------- co robi: arkusz → strona ---------- */

.lst-2m .lst-2m-przeplyw {
	display: grid;
	grid-template-columns: minmax( 0, 1fr ) auto minmax( 0, 1.1fr );
	align-items: center;
	gap: 1rem;
	margin-top: 1.4rem;
}

.lst-2m .lst-2m-arkusz,
.lst-2m .lst-2m-strona {
	font-size: .875rem;   /* 14 px */
	border-radius: 10px;
	overflow: hidden;
}

.lst-2m .lst-2m-arkusz {
	font-family: var( --lst-mono );
	border: 1px solid rgba( 138, 168, 163, .22 ) !important;
	background-color: #0d1413 !important;
}

.lst-2m .lst-2m-arkusz-gora,
.lst-2m .lst-2m-strona-gora {
	display: flex;
	align-items: center;
	gap: .45rem;
	padding: .5rem .75rem;
	background-color: var( --lst-ekran-gora ) !important;
	border-bottom: 1px solid rgba( 95, 227, 207, .14 ) !important;
	color: var( --lst-tekst-3 );
	white-space: nowrap;
	overflow: hidden;
}

.lst-2m .lst-2m-arkusz-gora b { font-weight: 500; color: var( --lst-tekst-2 ); }

/* Arkusz i strona to siatki, a nie <table>: motyw, który rysuje ramki
   tabelom, nie ma tu czego złapać, a Divi nie ma czego połamać. */
.lst-2m .lst-2m-komorki {
	display: grid;
	grid-template-columns: 2rem minmax( 0, 1.4fr ) minmax( 0, 1fr );
}

.lst-2m .lst-2m-komorki > span {
	padding: .4rem .6rem;
	border-right: 1px solid rgba( 138, 168, 163, .12 ) !important;
	border-bottom: 1px solid rgba( 138, 168, 163, .12 ) !important;
	color: var( --lst-tekst-2 );
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.lst-2m .lst-2m-komorki > span.jest-glowka { color: var( --lst-tekst-3 ); background-color: rgba( 255, 255, 255, .02 ) !important; }

/* Komórka, którą ktoś właśnie zmienia: zaznaczona tak, jak zaznacza ją arkusz. */
.lst-2m .lst-2m-komorki > span.jest-zmiana {
	outline: 2px solid rgb( var( --lst-mieta ) );
	outline-offset: -2px;
	color: var( --lst-tekst );
	background-color: rgba( var( --lst-mieta ), .08 ) !important;
}

.lst-2m .lst-2m-strona {
	border: 1px solid var( --lst-ekran-linia ) !important;
	background-color: var( --lst-ekran ) !important;
	box-shadow: 0 22px 46px -30px rgba( 0, 0, 0, .95 ), 0 0 34px -18px rgba( var( --lst-mieta ), .35 );
}

.lst-2m .lst-2m-strona-gora .lst-2m-oczko {
	display: block;
	width: 9px;
	height: 9px;
	border-radius: 999px;
	background: var( --lst-tekst-3 ) !important;
	opacity: .38;
	flex: none;
}

.lst-2m .lst-2m-adres-strony {
	margin-left: .35rem;
	padding: .1rem .55rem;
	border-radius: 6px;
	background-color: rgba( 255, 255, 255, .04 ) !important;
	font-family: var( --lst-mono );
	overflow: hidden;
	text-overflow: ellipsis;
}

.lst-2m .lst-2m-wiersze { display: grid; grid-template-columns: minmax( 0, 1fr ) auto; }

.lst-2m .lst-2m-wiersze > span {
	padding: .6rem .85rem;
	border-bottom: 1px solid rgba( 138, 168, 163, .08 ) !important;
	color: var( --lst-tekst );
	white-space: nowrap;
}

.lst-2m .lst-2m-wiersze > span:nth-child( even ) { text-align: right !important; font-variant-numeric: tabular-nums; }

.lst-2m .lst-2m-wiersze > span.jest-glowka {
	font-family: var( --lst-mono );
	letter-spacing: .08em;
	text-transform: uppercase;
	color: var( --lst-tekst-3 );
	border-bottom-color: rgba( 95, 227, 207, .18 ) !important;
}

.lst-2m .lst-2m-wiersze > span.jest-nowy { background-color: rgba( var( --lst-mieta ), .08 ) !important; }
.lst-2m .lst-2m-wiersze > span.jest-nowy.jest-liczba { color: rgb( var( --lst-mieta ) ); font-weight: 600; }

.lst-2m .lst-2m-stopka-strony {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
	gap: .2rem .8rem;
	padding: .55rem .85rem;
	font-family: var( --lst-mono );
	color: var( --lst-tekst-3 );
	white-space: nowrap;
}

.lst-2m .lst-2m-kropka {
	display: inline-block;
	width: 7px;
	height: 7px;
	margin-right: .45rem;
	border-radius: 99px;
	background-color: rgb( var( --lst-mieta ) ) !important;
	box-shadow: 0 0 0 4px rgba( var( --lst-mieta ), .15 );
	vertical-align: 1px;
}

.lst-2m .lst-2m-strzalka {
	display: grid;
	justify-items: center;
	gap: .35rem;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	color: var( --lst-tekst-3 );
	white-space: nowrap;
}

.lst-2m .lst-2m-strzalka-rys { width: 64px; height: 14px; color: rgb( var( --lst-mieta ) ); overflow: visible; }

/* Kreska strzałki płynie w stronę strony, cały czas, ale spokojnie: dane
   idą z arkusza na stronę, a nie stoją. */
.lst-2m .lst-2m-bieg { stroke-dasharray: 3 4; animation: lst-2m-plyn 1.6s linear infinite; }

@keyframes lst-2m-plyn { to { stroke-dashoffset: -14; } }
@keyframes lst-2m-dorys { from { stroke-dashoffset: 1; } }
@keyframes lst-2m-mrug { 50% { opacity: .15; } }
@keyframes lst-2m-zatrzask { 0% { transform: translateY( -3px ); } 70% { transform: translateY( .6px ); } }
@keyframes lst-2m-puls { 50% { background-color: rgba( 95, 227, 207, .22 ); } }

/* ---------- komu: żetony ---------- */

.lst-2m .lst-2m-zetony { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1.3rem; }

.lst-2m .lst-2m-zeton {
	display: inline-flex;
	align-items: center;
	gap: .45rem;
	padding: .38rem .75rem;
	border-radius: 999px;
	border: 1px solid rgba( 138, 168, 163, .16 ) !important;
	background-color: rgba( 255, 255, 255, .035 ) !important;
	font-size: .875rem;   /* 14 px */
	color: var( --lst-tekst );
}

.lst-2m .lst-2m-zeton::before {
	content: "";
	width: 6px;
	height: 6px;
	border-radius: 99px;
	background-color: rgb( var( --lst-mieta ) );
	opacity: .8;
}

/*
 * Przykłady pod żetonami. Kafel „komu” stoi obok wyższego kafla z arkuszem,
 * więc ma miejsce: przykłady siadają na jego dole, oddzielone cienką kreską,
 * a żetony zostają przy tekście. Na węższym ekranie kafel jest na całą
 * szerokość i przykłady idą zaraz pod żetonami.
 */
.lst-2m .lst-2m-kafel.jest-komu,
.lst-2m .lst-2m-kafel.jest-robi { display: flex; flex-direction: column; }

/* Pod arkuszem i stroną: co dostaje odwiedzający. Trzy obok siebie, na dole
   kafla, tak jak przykłady w kaflu obok — oba górne kafle kończą się tą samą
   kreską i tą samą etykietą. */
.lst-2m .lst-2m-cechy {
	display: grid;
	grid-template-columns: repeat( 3, minmax( 0, 1fr ) );
	gap: .95rem 1.2rem;
	margin-top: auto;
	padding-top: 1.4rem;
}

.lst-2m .lst-2m-cechy > .lst-2m-etykieta {
	grid-column: 1 / -1;
	padding-top: 1.1rem !important;
	border-top: 1px solid rgba( 138, 168, 163, .14 ) !important;
}

.lst-2m .lst-2m-przyklady {
	display: grid;
	gap: .95rem;
	margin-top: auto;
	padding-top: 1.4rem;
}

.lst-2m .lst-2m-przyklady > .lst-2m-etykieta {
	padding-top: 1.1rem !important;
	border-top: 1px solid rgba( 138, 168, 163, .14 ) !important;
}

.lst-2m .lst-2m-przyklad { display: grid; grid-template-columns: 2.6rem minmax( 0, 1fr ); gap: .85rem; align-items: start; }
.lst-2m .lst-2m-przyklad-kto { font-size: 1.125rem; font-weight: 600; line-height: 1.35; }
.lst-2m .lst-2m-przyklad-co { font-size: 1.125rem; line-height: 1.45; color: var( --lst-tekst-2 ); }

/* ---------- jak: trzy kroki ---------- */

.lst-2m .lst-2m-kroki { display: grid; gap: .8rem; margin-top: 1.2rem; }

.lst-2m .lst-2m-krok {
	display: grid;
	grid-template-columns: 2.25rem minmax( 0, 1fr );
	gap: .85rem;
	align-items: start;
}

.lst-2m .lst-2m-nr {
	display: grid;
	place-items: center;
	width: 2.25rem;
	height: 2.25rem;
	border-radius: 10px;
	border: 1px solid rgba( var( --lst-mieta ), .28 ) !important;
	background-color: rgba( var( --lst-mieta ), .1 ) !important;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	color: rgb( var( --lst-mieta ) );
}

.lst-2m .lst-2m-krok-tytul { font-size: 1.125rem; font-weight: 600; line-height: 1.35; }
.lst-2m .lst-2m-krok-tekst { font-size: 1.125rem; line-height: 1.45; color: var( --lst-tekst-2 ); }
.lst-2m .lst-2m-kod { font-family: var( --lst-mono ); font-size: 1rem; color: rgb( var( --lst-mieta ) ); white-space: nowrap; }

/* ---------- dlaczego: cztery powody ---------- */

.lst-2m .lst-2m-pewne {
	display: grid;
	grid-template-columns: repeat( 2, minmax( 0, 1fr ) );
	gap: 1.1rem 1.6rem;
	margin-top: 1.3rem;
}

.lst-2m .lst-2m-powod { display: grid; grid-template-columns: 2.6rem minmax( 0, 1fr ); gap: .85rem; align-items: start; }

.lst-2m .lst-2m-ikona {
	display: grid;
	place-items: center;
	width: 2.6rem;
	height: 2.6rem;
	border-radius: 12px;
	border: 1px solid rgba( var( --lst-mieta ), .26 ) !important;
	background-color: rgba( var( --lst-mieta ), .08 ) !important;
	color: rgb( var( --lst-mieta ) );
}

.lst-2m .lst-2m-ikona-rys { width: 1.6rem; height: 1.6rem; overflow: visible; }

/* Część ikonki, która dorysowuje się pod kursorem. W spoczynku jest już
   narysowana, żeby nic nie było niedokończone na telefonie i na wydruku. */
.lst-2m .lst-2m-dorys { stroke-dasharray: 1; stroke-dashoffset: 0; }
.lst-2m .lst-2m-palak { transform-box: fill-box; transform-origin: 100% 100%; }

.lst-2m .lst-2m-powod-tytul { font-size: 1.125rem; font-weight: 600; line-height: 1.35; }
.lst-2m .lst-2m-powod-tekst { font-size: 1.125rem; line-height: 1.45; color: var( --lst-tekst-2 ); }

/* ---------- co dalej ---------- */

.lst-2m .lst-2m-kafel.jest-dalej .lst-2m-kafel-tytul { margin-top: 0; }
.lst-2m .lst-2m-kafel.jest-dalej .lst-2m-kafel-tekst { margin-top: .35rem; }

.lst-2m .lst-2m-przyciski { display: flex; flex-wrap: wrap; gap: .7rem; margin-left: auto; }

.lst-2m .lst-2m-przycisk {
	display: inline-flex;
	align-items: center;
	padding: .7rem 1.35rem;
	border-radius: 999px;
	font-size: 1.125rem;   /* 18 px */
	font-weight: 600;
	line-height: 1.2;
	white-space: nowrap;
	text-decoration: none !important;
	transition: transform .2s cubic-bezier( .23, 1, .32, 1 ), box-shadow .2s ease, background-color .2s ease;
}

.lst-2m .lst-2m-przycisk.jest-glowny {
	background-color: rgb( var( --lst-mieta ) ) !important;
	color: #06100f !important;
	box-shadow: 0 10px 30px -12px rgba( var( --lst-mieta ), .7 );
}

.lst-2m .lst-2m-przycisk.jest-drugi {
	border: 1px solid rgba( var( --lst-mieta ), .35 ) !important;
	color: var( --lst-tekst ) !important;
}

/* ---------- światło biegnące po krawędzi ---------- */

/*
 * Kąt, o który obrócone jest światło. Własność musi być ZGŁOSZONA, bo inaczej
 * przeglądarka traktuje ją jak zwykły napis i nie ma czego animować: skok
 * z 0 na 360 stopni bez wartości pomiędzy. Zgłoszona jako kąt, animuje się
 * płynnie. Dziedziczona, bo obracamy ją na kaflu, a czytają ją obie warstwy
 * światła pod spodem.
 *
 * Przeglądarka, która „@property" nie zna, zostaje z kątem 0: światło stoi
 * w prawym górnym rogu i wygląda jak zwykły akcent na krawędzi. Nic nie ginie.
 */
@property --lst-obrot {
	syntax: "<angle>";
	initial-value: 0deg;
	inherits: true;
}

/*
 * Dwie warstwy tego samego światła: ostra i rozmyta.
 *
 * Obie są pełnym kołem koloru obróconym o ten sam kąt, przyciętym maską do
 * samej ramki — to znaczy: maluje się cały prostokąt, a potem wycina z niego
 * środek, i zostaje obwódka. Ostra jest krawędzią, rozmyta poświatą, przez
 * którą krawędź „rozświetla" to, obok czego właśnie przejeżdża.
 *
 * Każdy kafel startuje w innym miejscu obiegu (ujemne opóźnienie), bo
 * światła idące równo wyglądają jak jedna animacja rozciągnięta na kilka
 * pudełek, a nie jak osobne przedmioty.
 */
.lst-2m .lst-2m-kafel::before,
.lst-2m .lst-2m-kafel::after {
	content: "";
	position: absolute;
	inset: -1px;
	border-radius: calc( var( --lst-promien ) + 1px );
	padding: 1.5px;
	pointer-events: none;
	background-image: conic-gradient( from var( --lst-obrot ),
		rgba( var( --lst-mieta ), 0 ) 0turn,
		rgba( var( --lst-mieta ), .95 ) .06turn,
		rgba( var( --lst-mieta ), .25 ) .12turn,
		rgba( var( --lst-mieta ), 0 ) .2turn,
		rgba( var( --lst-mieta ), 0 ) 1turn ) !important;
	-webkit-mask-image: linear-gradient( #000 0 0 ), linear-gradient( #000 0 0 );
	-webkit-mask-clip: content-box, border-box;
	-webkit-mask-composite: xor;
	mask-image: linear-gradient( #000 0 0 ), linear-gradient( #000 0 0 );
	mask-clip: content-box, border-box;
	mask-composite: exclude;
}

.lst-2m .lst-2m-kafel::after {
	padding: 3.5px;
	filter: blur( 6px );
	opacity: .75;
}

/* Obieg trwa tyle, żeby światło szło spokojnie po długim obwodzie kafla,
   a nie pędziło. Pas „co dalej” jest najdłuższy, więc jego obieg też. */
.lst-2m .lst-2m-kafel {
	animation: lst-2m-obieg 11s linear infinite;
}

.lst-2m .lst-2m-kafel.jest-komu { animation-delay: -3.1s; }
.lst-2m .lst-2m-kafel.jest-jak { animation-delay: -6.4s; }
.lst-2m .lst-2m-kafel.jest-ufac { animation-delay: -8.7s; }
.lst-2m .lst-2m-kafel.jest-dalej { animation-delay: -1.6s; animation-duration: 14s; }

@keyframes lst-2m-obieg { to { --lst-obrot: 1turn; } }

/* ---------- najechanie ---------- */

/* Tylko tam, gdzie jest czym najechać. */
@media ( hover: hover ) and ( pointer: fine ) {
	.lst-2m .lst-2m-kafel {
		transition: transform .25s cubic-bezier( .23, 1, .32, 1 ),
			box-shadow .25s cubic-bezier( .23, 1, .32, 1 ),
			border-color .25s ease;
	}

	/* Blok unosi się o dwa piksele, a cień robi się głębszy: tyle, żeby było
	   czuć, że to przedmiot, a nie plama na tle. Nic stąd nie prowadzi dalej,
	   więc nic nie udaje przycisku. */
	.lst-2m .lst-2m-kafel:hover {
		transform: translateY( -2px );
		border-color: rgba( var( --lst-mieta ), .32 ) !important;
		box-shadow:
			inset 0 1px 0 rgba( 255, 255, 255, .07 ),
			0 26px 52px -26px rgba( 0, 0, 0, .95 ),
			0 3px 12px -6px rgba( 0, 0, 0, .65 );
	}

	/*
	 * Ikonki budzą się pod kursorem, każda po swojemu, i każda mówi ruchem to
	 * samo co zdanie obok: w tarczy dorysowuje się ptaszek, w serwerze mrugają
	 * diody, w tabeli dorysowuje się kolumna, kłódka się zatrzaskuje. Kwadracik
	 * zapala się na mięto, a rysunek ciemnieje, żeby było go widać na mięcie.
	 */
	.lst-2m .lst-2m-ikona,
	.lst-2m .lst-2m-nr,
	.lst-2m .lst-2m-zeton {
		transition: background-color .25s ease, color .25s ease, border-color .25s ease,
			transform .3s cubic-bezier( .23, 1, .32, 1 ), box-shadow .3s ease;
	}

	.lst-2m .lst-2m-powod:hover .lst-2m-ikona,
	.lst-2m .lst-2m-przyklad:hover .lst-2m-ikona {
		background-color: rgb( var( --lst-mieta ) ) !important;
		border-color: rgb( var( --lst-mieta ) ) !important;
		color: #06100f;
		transform: translateY( -2px ) rotate( -4deg );
		box-shadow: 0 10px 24px -10px rgba( var( --lst-mieta ), .8 );
	}

	.lst-2m .lst-2m-przyklad:hover .lst-2m-dorys,
	.lst-2m .lst-2m-powod:hover .lst-2m-dorys { animation: lst-2m-dorys .5s cubic-bezier( .23, 1, .32, 1 ) both; }
	.lst-2m .lst-2m-przyklad:hover .lst-2m-dioda,
	.lst-2m .lst-2m-powod:hover .lst-2m-dioda { animation: lst-2m-mrug .9s steps( 2, jump-none ) 2; }
	.lst-2m .lst-2m-powod:hover .lst-2m-palak { animation: lst-2m-zatrzask .45s cubic-bezier( .23, 1, .32, 1 ) both; }

	/* Krok pod kursorem: numer zapala się tak samo jak ikonka. */
	.lst-2m .lst-2m-krok:hover .lst-2m-nr {
		background-color: rgb( var( --lst-mieta ) ) !important;
		border-color: rgb( var( --lst-mieta ) ) !important;
		color: #06100f;
		transform: scale( 1.08 );
		box-shadow: 0 8px 20px -8px rgba( var( --lst-mieta ), .8 );
	}

	/* Żeton pod kursorem: krawędź w kolorze akcentu i kropka, która świeci. */
	.lst-2m .lst-2m-zeton:hover {
		border-color: rgba( var( --lst-mieta ), .55 ) !important;
		transform: translateY( -1px );
	}

	.lst-2m .lst-2m-zeton:hover::before { box-shadow: 0 0 0 4px rgba( var( --lst-mieta ), .2 ); opacity: 1; }

	/* Kafel „co robi” pod kursorem: zmieniana komórka pulsuje, a kreska
	   strzałki przyspiesza — dane właśnie idą na stronę. */
	.lst-2m .lst-2m-kafel.jest-robi:hover .lst-2m-komorki > span.jest-zmiana { animation: lst-2m-puls 1.2s ease-in-out infinite; }
	.lst-2m .lst-2m-kafel.jest-robi:hover .lst-2m-bieg { animation-duration: .6s; }

	.lst-2m .lst-2m-przycisk.jest-glowny:hover { transform: translateY( -2px ); box-shadow: 0 16px 34px -12px rgba( var( --lst-mieta ), .85 ); }
	.lst-2m .lst-2m-przycisk.jest-drugi:hover { transform: translateY( -2px ); background-color: rgba( var( --lst-mieta ), .1 ) !important; }
}

/* ---------- utwardzenie na wrogie motywy ---------- */

/* Reset :where() ma wagę zero i przegrywa z motywem, który pisze
   "p { margin: 40px !important }". Tu przebijamy to wprost. */
.lst-2m .lst-2m-etykieta,
.lst-2m .lst-2m-kafel-tytul,
.lst-2m .lst-2m-kafel-tekst,
.lst-2m .lst-2m-krok-tytul,
.lst-2m .lst-2m-krok-tekst,
.lst-2m .lst-2m-powod-tytul,
.lst-2m .lst-2m-powod-tekst,
.lst-2m .lst-2m-przyklad-kto,
.lst-2m .lst-2m-przyklad-co {
	margin-inline: 0 !important;
	margin-bottom: 0 !important;
	padding: 0 !important;
	background: none !important;
	border: 0 !important;
	text-align: left !important;
	text-transform: none !important;
	letter-spacing: normal !important;
}



.lst-2m .lst-2m-etykieta { font-family: var( --lst-mono ) !important; text-transform: uppercase !important; letter-spacing: .1em !important; color: var( --lst-tekst-3 ) !important; margin-top: 0 !important; }
.lst-2m .lst-2m-kafel-tytul { font-family: var( --lst-serif ) !important; color: var( --lst-tekst ) !important; }
.lst-2m .lst-2m-kafel-tekst,
.lst-2m .lst-2m-krok-tekst,
.lst-2m .lst-2m-powod-tekst { font-family: inherit !important; color: var( --lst-tekst-2 ) !important; margin-top: 0 !important; }
.lst-2m .lst-2m-kafel-tekst { margin-top: .55rem !important; }
.lst-2m .lst-2m-kafel.jest-dalej .lst-2m-kafel-tekst { margin-top: .35rem !important; }
.lst-2m .lst-2m-krok-tytul,
.lst-2m .lst-2m-powod-tytul { font-family: inherit !important; color: var( --lst-tekst ) !important; margin-top: 0 !important; }
.lst-2m .lst-2m-przyklad-kto { font-family: inherit !important; color: var( --lst-tekst ) !important; margin-top: 0 !important; }
.lst-2m .lst-2m-przyklad-co { font-family: inherit !important; color: var( --lst-tekst-2 ) !important; margin-top: 0 !important; }
.lst-2m .lst-2m-arkusz,
.lst-2m .lst-2m-stopka-strony,
.lst-2m .lst-2m-kod,
.lst-2m .lst-2m-nr,
.lst-2m .lst-2m-strzalka { font-family: var( --lst-mono ) !important; }
.lst-2m .lst-2m-zeton,
.lst-2m .lst-2m-przycisk { font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif !important; text-transform: none !important; }
.lst-2m .lst-2m-wiersze > span.jest-glowka { text-transform: uppercase !important; }
.lst-2m .lst-2m-oczko { border: 0 !important; }
.lst-2m .lst-2m-kod { background: none !important; padding: 0 !important; border: 0 !important; }

/* Obramowanie i dopełnienie, które Divi albo motyw nadaje opakowaniu
   modułu, obrysowałoby sekcję. Zdejmujemy je przez ":has", bez skryptu. */
.et_pb_module:has( .lst-2m ),
.et_pb_column:has( .lst-2m ),
.et_pb_row:has( .lst-2m ) { border: 0 !important; outline: 0 !important; }

@media ( max-width: 900px ) {
	/* Podwójna klasa: reguły szerokości kafli niżej i wyżej mają po dwie
	   klasy, a ta musi z nimi wygrać. */
	.lst-2m .lst-2m-kafel.lst-2m-kafel { grid-column: 1 / -1; }
	.lst-2m .lst-2m-przeplyw { grid-template-columns: minmax( 0, 1fr ); justify-items: stretch; }
	.lst-2m .lst-2m-strzalka { transform: rotate( 90deg ); margin: .2rem auto; }
	.lst-2m .lst-2m-strzalka span { display: none; }
	.lst-2m .lst-2m-pewne,
	.lst-2m .lst-2m-cechy { grid-template-columns: minmax( 0, 1fr ); }
	.lst-2m .lst-2m-kafel.jest-dalej { flex-direction: column; align-items: flex-start; }
	.lst-2m .lst-2m-przyciski { margin-left: 0; }
}

@media ( prefers-reduced-motion: reduce ) {
	/* „Mniej ruchu" znaczy mniej ruchu, nie mniej treści: przyrządy zostają na
	   ekranie w stanie końcowym. */
	.lst-2m .lst-2m-kafel,
	.lst-2m .lst-2m-ikona,
	.lst-2m .lst-2m-nr { transition: none; }
	.lst-2m .lst-2m-kafel,
	.lst-2m .lst-2m-bieg { animation: none !important; }
}

@media print {
	.lst-2m .lst-2m-kafel,
	.lst-2m .lst-2m-bieg { animation: none !important; }
}
</style>

'''


def kafel( klasa, etykieta, tytul, srodek ):
	return ( '\t\t\t<div class="lst-2m-kafel jest-' + klasa + '">'
		'<p class="lst-2m-etykieta">' + etykieta + '</p>'
		'<p class="lst-2m-kafel-tytul">' + tytul + '</p>' + srodek + '</div>' )


def przeplyw( k ):
	"""Arkusz z jedną zmienianą komórką, strzałka i ta sama tabela na stronie."""
	nazwa, skad, glowki, wiersze = k[ 'arkusz' ]
	komorki = [ '<span class="jest-glowka"></span><span class="jest-glowka">A</span><span class="jest-glowka">B</span>',
		'<span class="jest-glowka">1</span><span>' + glowki[ 0 ] + '</span><span>' + glowki[ 1 ] + '</span>' ]
	for nr, ( a, b ) in enumerate( wiersze, 2 ):
		zmiana = ' class="jest-zmiana"' if '&rarr;' in b else ''
		komorki.append( '<span class="jest-glowka">' + str( nr ) + '</span><span>' + a + '</span>'
			'<span' + zmiana + '>' + b + '</span>' )
	arkusz = ( '<div class="lst-2m-arkusz"><div class="lst-2m-arkusz-gora"><b>' + nazwa + '</b> &middot; ' + skad + '</div>'
		'<div class="lst-2m-komorki">' + ''.join( komorki ) + '</div></div>' )

	adres, glowki, wiersze, kiedy, ile = k[ 'strona' ]
	pola = [ '<span class="jest-glowka">' + glowki[ 0 ] + '</span><span class="jest-glowka">' + glowki[ 1 ] + '</span>' ]
	for nr, ( a, b ) in enumerate( wiersze ):
		# Na stronie podświetlony jest ten wiersz, którego komórkę zmieniono w arkuszu.
		nowy = '&rarr;' in k[ 'arkusz' ][ 3 ][ nr ][ 1 ]
		pola.append( ( '<span class="jest-nowy">' if nowy else '<span>' ) + a + '</span>'
			'<span class="jest-liczba' + ( ' jest-nowy' if nowy else '' ) + '">' + b + '</span>' )
	strona = ( '<div class="lst-2m-strona"><div class="lst-2m-strona-gora">'
		'<span class="lst-2m-oczko"></span><span class="lst-2m-oczko"></span><span class="lst-2m-oczko"></span>'
		'<span class="lst-2m-adres-strony">' + adres + '</span></div>'
		'<div class="lst-2m-wiersze">' + ''.join( pola ) + '</div>'
		'<div class="lst-2m-stopka-strony"><span><span class="lst-2m-kropka"></span>' + kiedy + '</span>'
		'<span>' + ile + '</span></div></div>' )

	return ( '<div class="lst-2m-przeplyw">' + arkusz +
		'<div class="lst-2m-strzalka">' + STRZALKA + '<span>' + k[ 'co_ile' ] + '</span></div>' + strona + '</div>' )


def zbuduj( t, plik ):
	k = t[ 'kafle' ]
	kafle = []

	etykieta, tytul, tekst = k[ 'robi' ]
	naglowek_cech, cechy = k[ 'cechy' ]
	kafle.append( kafel( 'robi', etykieta, tytul, '<p class="lst-2m-kafel-tekst">' + tekst + '</p>' + przeplyw( k ) +
		'<div class="lst-2m-cechy"><p class="lst-2m-etykieta">' + naglowek_cech + '</p>' + ''.join(
			'<div class="lst-2m-przyklad"><span class="lst-2m-ikona">' + IKONY[ ikona ] + '</span><div>'
			'<p class="lst-2m-przyklad-kto">' + kto + '</p><p class="lst-2m-przyklad-co">' + co + '</p></div></div>'
			for ikona, kto, co in cechy ) + '</div>' ) )

	etykieta, tytul, tekst = k[ 'komu' ]
	naglowek_przykladow, przyklady = k[ 'przyklady' ]
	kafle.append( kafel( 'komu', etykieta, tytul, '<p class="lst-2m-kafel-tekst">' + tekst + '</p>'
		'<div class="lst-2m-zetony">' + ''.join( '<span class="lst-2m-zeton">' + z + '</span>' for z in k[ 'zetony' ] ) + '</div>'
		'<div class="lst-2m-przyklady"><p class="lst-2m-etykieta">' + naglowek_przykladow + '</p>' + ''.join(
			'<div class="lst-2m-przyklad"><span class="lst-2m-ikona">' + IKONY[ ikona ] + '</span><div>'
			'<p class="lst-2m-przyklad-kto">' + kto + '</p><p class="lst-2m-przyklad-co">' + co + '</p></div></div>'
			for ikona, kto, co in przyklady ) + '</div>' ) )

	etykieta, tytul = k[ 'jak' ]
	kafle.append( kafel( 'jak', etykieta, tytul, '<div class="lst-2m-kroki">' + ''.join(
		'<div class="lst-2m-krok"><span class="lst-2m-nr">' + str( nr ) + '</span><div>'
		'<p class="lst-2m-krok-tytul">' + kt + '</p><p class="lst-2m-krok-tekst">' + ko + '</p></div></div>'
		for nr, ( kt, ko ) in enumerate( k[ 'kroki' ], 1 ) ) + '</div>' ) )

	etykieta, tytul = k[ 'ufac' ]
	kafle.append( kafel( 'ufac', etykieta, tytul, '<div class="lst-2m-pewne">' + ''.join(
		'<div class="lst-2m-powod jest-' + ikona + '"><span class="lst-2m-ikona">' + IKONY[ ikona ] + '</span><div>'
		'<p class="lst-2m-powod-tytul">' + pt + '</p><p class="lst-2m-powod-tekst">' + po + '</p></div></div>'
		for ikona, pt, po in k[ 'pewne' ] ) + '</div>' ) )

	tytul, tekst = k[ 'dalej' ]
	( glowny, glowny_adres ), ( drugi, drugi_adres ) = k[ 'przyciski' ]
	kafle.append( '\t\t\t<div class="lst-2m-kafel jest-dalej"><div>'
		'<p class="lst-2m-kafel-tytul">' + tytul + '</p><p class="lst-2m-kafel-tekst">' + tekst + '</p></div>'
		'<div class="lst-2m-przyciski">'
		'<a class="lst-2m-przycisk jest-glowny" href="' + glowny_adres + '">' + glowny + '</a>'
		'<a class="lst-2m-przycisk jest-drugi" href="' + drugi_adres + '">' + drugi + '</a></div></div>' )

	html = ( SZABLON
		.replace( '{KAFLE}', '\n'.join( kafle ) ) )

	sprawdz( html, plik )

	with open( plik, 'w', encoding='utf-8' ) as f:
		f.write( html )

	print( plik + ' — kafli: ' + str( len( kafle ) ) + ', linii: ' + str( len( html.split( chr( 10 ) ) ) ) )
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

	# Znaczniki zapisane encjami Divi zamienia z powrotem na znaczniki, tak
	# samo jak nawiasy: napis „A real &lt;table&gt;” stał się na żywej stronie
	# prawdziwą tabelą, która wciągnęła w siebie resztę sekcji.
	if '&lt;' in znacznikowanie or '&gt;' in znacznikowanie:
		raise SystemExit( plik + ': w treści jest &lt; albo &gt; — Divi zrobi z tego prawdziwy znacznik' )

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
