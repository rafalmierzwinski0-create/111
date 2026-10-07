# -*- coding: utf-8 -*-
"""
„Z arkusza na stronę w dwie minuty" — siatka pięciu kafli nad paskiem liczb,
jeden moduł Kod.

Kafle odpowiadają na to, o co pyta ktoś, kto widzi stronę pierwszy raz: co to
robi, komu pomaga, jak się to ustawia, dlaczego można temu ufać i co zrobić
dalej. Wcześniej stały tu trzy pary „krok i okienko", które mówiły tylko „jak".
Każde zdanie w kaflach jest prawdą o wtyczce (readme.txt) — żadnych
wymyślonych liczb klientów ani opinii.

Po krawędzi każdego kafla biegnie to samo światło co po blokach z liczbami,
a ikonki budzą się pod kursorem.

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
		'komu': ( 'Who it helps', 'Anyone whose numbers live in a spreadsheet.',
			'If people keep asking you for &ldquo;the latest version&rdquo;, it belongs on your page &mdash; '
			'edited where you already edit it.' ),
		'zetony': ( 'Price lists', 'Timetables', 'Stock levels', 'Menus', 'Event line-ups',
			'Trail conditions', 'Rankings', 'Opening hours' ),
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
			( 'tabela', 'A real &lt;table&gt; in the HTML', 'Search engines can read it, and it is drawn before any code runs.' ),
			( 'klodka', 'Talks to Google only', 'No analytics, no account with us. The data stays in your database.' ),
		),
		'dalej': ( 'Try it on your own sheet.',
			'Free, no row limit, no watermark. You see the table in the preview before anything is published.' ),
		'przyciski': ( ( 'Download free', '#pricing' ), ( 'See what Pro adds', '#compare' ) ),
	},
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
	'kafle': {
		'robi': ( 'Co robi', 'Zmieniasz komórkę w arkuszu. Strona idzie za nim.',
			'Arkusz Google staje się prawdziwą tabelą na stronie WordPress &mdash; i sam pilnuje, żeby była aktualna.' ),
		'arkusz': ( 'Ceny', 'Arkusze Google', ( 'Produkt', 'Cena' ),
			( ( 'Stół dębowy', '1 290' ), ( 'Krzesło', '349 &rarr; 319' ), ( 'Półka', '215' ) ) ),
		'strona': ( 'twojsklep.pl/cennik', ( 'Produkt', 'Cena' ),
			( ( 'Stół dębowy', '1 290' ), ( 'Krzesło', '319' ), ( 'Półka', '215' ) ),
			'zaktualizowano 2 min temu', '3 wiersze' ),
		'co_ile': 'co 15 min',
		'komu': ( 'Komu pomaga', 'Każdemu, kto trzyma liczby w arkuszu.',
			'Jeśli ktoś ciągle prosi Cię o &bdquo;najnowszą wersję&rdquo;, jej miejsce jest na stronie &mdash; '
			'edytowanej tam, gdzie i tak edytujesz.' ),
		'zetony': ( 'Cenniki', 'Rozkłady jazdy', 'Stany magazynowe', 'Menu', 'Programy wydarzeń',
			'Warunki na szlakach', 'Rankingi', 'Godziny otwarcia' ),
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
			( 'tabela', 'Prawdziwa &lt;table&gt; w HTML', 'Czytelna dla wyszukiwarek, nawet bez skryptów.' ),
			( 'klodka', 'Rozmawia tylko z Google', 'Bez analityki i bez konta u nas. Dane zostają w Twojej bazie.' ),
		),
		'dalej': ( 'Wypróbuj na swoim arkuszu.',
			'Za darmo, bez limitu wierszy, bez znaku wodnego. Tabelę widzisz w podglądzie, zanim cokolwiek zostanie opublikowane.' ),
		'przyciski': ( ( 'Pobierz za darmo', '#cennik' ), ( 'Zobacz, co daje Pro', '#porownanie' ) ),
	},
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
		'<circle class="lst-2m-tarcza" cx="36" cy="36" r="33" fill="none" stroke="currentColor" stroke-width="1.5" '
		'opacity=".3"></circle>'
		+ ''.join(
			'<rect class="lst-2m-godzina" x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" rx="1" '
			'fill="currentColor" opacity=".45"></rect>'
			for x, y, w, h in (
				( '35', '0', '2', '7' ), ( '65', '35', '7', '2' ),
				( '35', '65', '2', '7' ), ( '0', '35', '7', '2' ),
			)
		)
		# Kwadrans i jego koniec w jednej grupie: pod kursorem obraca się cała,
		# jak wskazówka, która właśnie robi pełny obieg.
		+ '<g class="lst-2m-wskazowka"><path class="lst-2m-kwadrans" d="M36 3 A33 33 0 0 1 69 36" fill="none" '
		'stroke="currentColor" stroke-width="5" stroke-linecap="round"></path>'
		'<circle class="lst-2m-wskaz" cx="69" cy="36" r="4.5" fill="currentColor"></circle></g>'
		'</g></svg>' ),
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

STRZALKA = ( '<svg class="lst-2m-strzalka-rys" viewBox="0 0 64 14" fill="none" stroke="currentColor" '
	'stroke-width="1.6" stroke-linecap="round" aria-hidden="true" focusable="false">'
	'<path class="lst-2m-bieg" d="M2 7h54"></path><path d="m54 2 6 5-6 5"></path></svg>' )

SZABLON = r'''<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Inria+Serif:wght@400&display=swap">

<div class="lst-2m">
	<div class="lst-2m-rama">
		<div class="lst-2m-kafle">
{KAFLE}
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

	/*
	 * Kreska między krokami.
	 *
	 * Szara przy .16 miała kontrast 1,32 : 1 do tła strony, czyli w praktyce
	 * jej nie było; .38 dawało 2,02 : 1 i ledwo ją było widać. Szarość jest tu
	 * ślepą uliczką: na ciemnozielonym tle szary o dowolnym kryciu albo ginie,
	 * albo wygląda jak brud. Kreska jest więc miętowa — w kolorze, który ta
	 * witryna i tak nosi — i zanikająca w prawo, tak samo jak kreska nad pasem
	 * z liczbami. Jeden obraz na obie, żeby sekcja mówiła jednym językiem.
	 */
	--lst-kreska: rgba( var( --lst-mieta ), .9 );
	--lst-kreska-obraz: linear-gradient( to right,
		rgba( var( --lst-mieta ), .9 ),
		rgba( var( --lst-mieta ), .62 ) 40%,
		rgba( var( --lst-mieta ), .34 ) 75%,
		rgba( var( --lst-mieta ), .1 ) 93%,
		transparent );
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
 * Kafel wygląda tak samo jak blok z liczbą pod spodem: to samo tło, ta sama
 * krawędź, to samo światło z góry i poświata w rogu, i to samo światło
 * biegnące po obwodzie (patrz „światło biegnące po krawędzi” niżej). Cała
 * sekcja jest przez to z jednego materiału.
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
 * liczbach i tytułach sekcji): ten sam szeryf co liczby pod spodem, tylko
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
	gap: clamp( .9rem, 1.8vw, 1.5rem );
	position: relative;
	/* Kreska nad pasem stoi dokładnie w połowie: tyle samo powietrza nad nią
	   (od kafli) co pod nią (do bloków). Nierówne odstępy wyglądały, jakby
	   kreska należała do kafli, a nie dzieliła sekcji na dwie części. */
	--lst-pol-odstepu: clamp( 1.6rem, 3vw, 2.6rem );
	margin-top: var( --lst-pol-odstepu );
	/* plus grubość samej kreski, która stoi na górze dopełnienia */
	padding-top: calc( var( --lst-pol-odstepu ) + 2px );
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
	background-image: var( --lst-kreska-obraz ) !important;
}

/*
 * Trzy osobne bloki, a nie trzy kolumny przedzielone kreską.
 *
 * Blok trzyma się kupy sam z siebie i nie potrzebuje niczego, co by go
 * domykało: ma swoje tło, swoją krawędź i swój cień, więc widać go z drugiego
 * końca pokoju. To, od czego ten pas uciekał, to nie były kafelki — to była
 * JEDNA ramka na trzy liczby, ze ściankami na krzyż, czyli tabela. Trzy
 * osobne bloki z odstępem między nimi to co innego.
 *
 * Głębia z trzech warstw, nie z jednej: światło z góry (jasny pasek pod
 * krawędzią), poświata akcentu w lewym górnym rogu i cień pod spodem. Żadna
 * z nich sama nie robi wrażenia, a razem blok odrywa się od strony.
 */
.lst-2m .lst-2m-liczba {
	position: relative;
	display: grid;
	align-content: start;
	padding: clamp( 1.3rem, 2.2vw, 1.75rem ) clamp( 1.2rem, 2vw, 1.6rem ) clamp( 1.4rem, 2.4vw, 1.9rem );
	border-radius: var( --lst-promien );
	border: 1px solid rgba( var( --lst-mieta ), .18 ) !important;
	background-color: var( --lst-panel ) !important;
	background-image:
		linear-gradient( 180deg, rgba( 255, 255, 255, .04 ), rgba( 255, 255, 255, 0 ) 42% ),
		radial-gradient( 17rem 12rem at 14% -8%, rgba( var( --lst-mieta ), .16 ), transparent ),
		linear-gradient( var( --lst-panel ), var( --lst-panel-dol ) ) !important;
	box-shadow:
		inset 0 1px 0 rgba( 255, 255, 255, .05 ),
		0 20px 44px -26px rgba( 0, 0, 0, .95 ),
		0 2px 10px -6px rgba( 0, 0, 0, .6 );
}

/* ---------- światło biegnące po krawędzi ---------- */

/*
 * Kąt, o który obrócone jest światło. Własność musi być ZGŁOSZONA, bo inaczej
 * przeglądarka traktuje ją jak zwykły napis i nie ma czego animować: skok
 * z 0 na 360 stopni bez wartości pomiędzy. Zgłoszona jako kąt, animuje się
 * płynnie. Dziedziczona, bo obracamy ją na bloku, a czytają ją obie warstwy
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
 * Każdy blok startuje w innym miejscu obiegu (ujemne opóźnienie), bo trzy
 * światła idące równo wyglądają jak jedna animacja rozciągnięta na trzy
 * pudełka, a nie jak trzy przedmioty.
 */
.lst-2m .lst-2m-liczba::before,
.lst-2m .lst-2m-liczba::after,
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

.lst-2m .lst-2m-liczba::after,
.lst-2m .lst-2m-kafel::after {
	padding: 3.5px;
	filter: blur( 6px );
	opacity: .75;
}

.lst-2m .lst-2m-liczba,
.lst-2m .lst-2m-kafel {
	animation: lst-2m-obieg 7s linear infinite;
}

/* Kafle są większe niż bloki z liczbami, więc obieg trwa dłużej — światło
   ma iść tym samym tempem, a nie pędzić po dłuższym obwodzie. Każdy kafel
   startuje gdzie indziej, tak jak bloki pod nimi. */
.lst-2m .lst-2m-kafel { animation-duration: 11s; }
.lst-2m .lst-2m-kafel.jest-komu { animation-delay: -3.1s; }
.lst-2m .lst-2m-kafel.jest-jak { animation-delay: -6.4s; }
.lst-2m .lst-2m-kafel.jest-ufac { animation-delay: -8.7s; }
.lst-2m .lst-2m-kafel.jest-dalej { animation-delay: -1.6s; animation-duration: 14s; }

.lst-2m .lst-2m-liczba:nth-child( 2 ) { animation-delay: -2.4s; }
.lst-2m .lst-2m-liczba:nth-child( 3 ) { animation-delay: -4.8s; }

@keyframes lst-2m-obieg { to { --lst-obrot: 1turn; } }

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
@keyframes lst-2m-obrot { to { transform: rotate( 1turn ); } }

/* Wskazówka obraca się wokół środka tarczy, a nie wokół rogu rysunku. */
.lst-2m .lst-2m-wskaz,
.lst-2m .lst-2m-wskazowka { transform-origin: 36px 36px; }

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

	.lst-2m .lst-2m-liczba,
	.lst-2m .lst-2m-kafel {
		transition: transform .25s cubic-bezier( .23, 1, .32, 1 ),
			box-shadow .25s cubic-bezier( .23, 1, .32, 1 ),
			border-color .25s ease;
	}

	/* Blok unosi się o dwa piksele, a cień robi się głębszy: tyle, żeby było
	   czuć, że to przedmiot, a nie plama na tle. Nic stąd nie prowadzi dalej,
	   więc nic nie udaje przycisku. */
	.lst-2m .lst-2m-liczba:hover,
	.lst-2m .lst-2m-kafel:hover {
		transform: translateY( -2px );
		border-color: rgba( var( --lst-mieta ), .32 ) !important;
		box-shadow:
			inset 0 1px 0 rgba( 255, 255, 255, .07 ),
			0 26px 52px -26px rgba( 0, 0, 0, .95 ),
			0 3px 12px -6px rgba( 0, 0, 0, .65 );
	}

	.lst-2m .lst-2m-liczba:hover .lst-2m-ramka { stroke-dasharray: 130 0; }
	.lst-2m .lst-2m-liczba:hover .lst-2m-belka-w { opacity: 1; }

	/*
	 * Tarcza pod kursorem: wskazówka robi jeden pełny obieg — kwadrans mija
	 * i zaczyna się następny, czyli to, co liczba obok mówi słowami — a tarcza
	 * i kreski godzin jaśnieją, tak jak pole z kursorem przestaje być
	 * kreskowane, a wiersze się zapalają.
	 */
	.lst-2m .lst-2m-tarcza,
	.lst-2m .lst-2m-godzina { transition: opacity .3s ease; }
	.lst-2m .lst-2m-liczba:hover .lst-2m-tarcza { opacity: .7; }
	.lst-2m .lst-2m-liczba:hover .lst-2m-godzina { opacity: 1; }
	.lst-2m .lst-2m-liczba:hover .lst-2m-wskazowka { animation: lst-2m-obrot 1.3s cubic-bezier( .65, 0, .35, 1 ) both; }

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

	.lst-2m .lst-2m-powod:hover .lst-2m-ikona {
		background-color: rgb( var( --lst-mieta ) ) !important;
		border-color: rgb( var( --lst-mieta ) ) !important;
		color: #06100f;
		transform: translateY( -2px ) rotate( -4deg );
		box-shadow: 0 10px 24px -10px rgba( var( --lst-mieta ), .8 );
	}

	.lst-2m .lst-2m-powod:hover .lst-2m-dorys { animation: lst-2m-dorys .5s cubic-bezier( .23, 1, .32, 1 ) both; }
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
.lst-2m .lst-2m-duza,
.lst-2m .lst-2m-pod,
.lst-2m .lst-2m-tekst {
	margin-inline: 0 !important;
	margin-bottom: 0 !important;
	padding: 0 !important;
	background: none !important;
	border: 0 !important;
	text-align: left !important;
	text-transform: none !important;
	letter-spacing: normal !important;
}

.lst-2m .lst-2m-duza,
.lst-2m .lst-2m-pod,
.lst-2m .lst-2m-tekst { margin-top: 0 !important; }
.lst-2m .lst-2m-duza { margin-bottom: .55rem !important; }
.lst-2m .lst-2m-pod { margin-bottom: .4rem !important; }

.lst-2m .lst-2m-etykieta { font-family: var( --lst-mono ) !important; text-transform: uppercase !important; letter-spacing: .1em !important; color: var( --lst-tekst-3 ) !important; margin-top: 0 !important; }
.lst-2m .lst-2m-kafel-tytul { font-family: var( --lst-serif ) !important; color: var( --lst-tekst ) !important; }
.lst-2m .lst-2m-kafel-tekst,
.lst-2m .lst-2m-krok-tekst,
.lst-2m .lst-2m-powod-tekst { font-family: inherit !important; color: var( --lst-tekst-2 ) !important; margin-top: 0 !important; }
.lst-2m .lst-2m-kafel-tekst { margin-top: .55rem !important; }
.lst-2m .lst-2m-kafel.jest-dalej .lst-2m-kafel-tekst { margin-top: .35rem !important; }
.lst-2m .lst-2m-krok-tytul,
.lst-2m .lst-2m-powod-tytul { font-family: inherit !important; color: var( --lst-tekst ) !important; margin-top: 0 !important; }
.lst-2m .lst-2m-duza { font-family: var( --lst-serif ) !important; color: rgb( var( --lst-mieta ) ) !important; }
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
	.lst-2m .lst-2m-pewne { grid-template-columns: minmax( 0, 1fr ); }
	.lst-2m .lst-2m-kafel.jest-dalej { flex-direction: column; align-items: flex-start; }
	.lst-2m .lst-2m-przyciski { margin-left: 0; }
	.lst-2m .lst-2m-liczby { grid-template-columns: 1fr; }
}

@media ( prefers-reduced-motion: reduce ) {
	/* „Mniej ruchu" znaczy mniej ruchu, nie mniej treści: przyrządy zostają na
	   ekranie w stanie końcowym. */
	.lst-2m .lst-2m-kafel,
	.lst-2m .lst-2m-liczba,
	.lst-2m .lst-2m-ikona,
	.lst-2m .lst-2m-nr { transition: none; }
	.lst-2m .lst-2m-liczba,
	.lst-2m .lst-2m-kafel,
	.lst-2m .lst-2m-bieg { animation: none !important; }
	.lst-2m .lst-2m-ramka,
	.lst-2m .lst-2m-belka-w,
	.lst-2m .lst-2m-kwadrans,
	.lst-2m .lst-2m-wskaz { animation: none !important; }
	.lst-2m .lst-2m-kwadrans { stroke-dasharray: none; }
}

@media print {
	.lst-2m .lst-2m-liczba,
	.lst-2m .lst-2m-kafel,
	.lst-2m .lst-2m-bieg { animation: none !important; }
	.lst-2m .lst-2m-ramka,
	.lst-2m .lst-2m-belka-w,
	.lst-2m .lst-2m-kwadrans,
	.lst-2m .lst-2m-wskaz { animation: none !important; }
	.lst-2m .lst-2m-kwadrans { stroke-dasharray: none; }
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
	kafle.append( kafel( 'robi', etykieta, tytul, '<p class="lst-2m-kafel-tekst">' + tekst + '</p>' + przeplyw( k ) ) )

	etykieta, tytul, tekst = k[ 'komu' ]
	kafle.append( kafel( 'komu', etykieta, tytul, '<p class="lst-2m-kafel-tekst">' + tekst + '</p>'
		'<div class="lst-2m-zetony">' + ''.join( '<span class="lst-2m-zeton">' + z + '</span>' for z in k[ 'zetony' ] ) + '</div>' ) )

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

	liczby = []

	for przyrzad, duza, pod, tekst in t[ 'liczby' ]:
		liczby.append(
			'\t\t\t<div class="lst-2m-liczba jest-' + przyrzad + '">'
			+ PRZYRZADY[ przyrzad ] +
			'<p class="lst-2m-duza">' + duza + '</p>'
			'<p class="lst-2m-pod">' + pod + '</p>'
			'<p class="lst-2m-tekst">' + tekst + '</p></div>' )

	html = ( SZABLON
		.replace( '{KAFLE}', '\n'.join( kafle ) )
		.replace( '{LICZBY}', '\n'.join( liczby ) ) )

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
