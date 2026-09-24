# -*- coding: utf-8 -*-
"""Sekcja „where the whole difference sits" jako sześć kafli o dwóch stronach.

Strona wierzchnia to cudza wada, spód to nasza odpowiedź. Najazd myszą obraca
kafel, kliknięcie go zatrzymuje, przełącznik nad siatką obraca wszystkie.
Całość mieści się w jednym module Kod w Divi.
"""

# Ikony w tle kafla. Kreska, nie wypełnienie — tak samo jak w poprzedniej sekcji.
ZNAKI = {
 # --- wady ---
 'czekanie': '<circle cx="12" cy="12" r="8.4"/><path d="M12 7.4V12l3 1.8"/>',
 'pusto':    '<rect x="3.4" y="4.6" width="17.2" height="14.8" rx="2.4" stroke-dasharray="3 3"/><path d="M8.6 12h6.8"/>',
 'arkusz':   '<rect x="3.4" y="4.6" width="17.2" height="14.8" rx="1.6"/><path d="M3.4 9.5h17.2M3.4 14.5h17.2M9 4.6v14.8M15 4.6v14.8"/>',
 'sufit':    '<path d="M4 6.4h16"/><path d="M12 9.6v9"/><path d="M8.4 15l3.6 3.6 3.6-3.6"/>',
 'cisza':    '<path d="M4.4 5.6h15.2v9.6H9.8l-4 3.2v-3.2H4.4z" stroke-dasharray="3 3"/><path d="M9.6 10.4h.01M12 10.4h.01M14.4 10.4h.01"/>',
 'mrowisko': '<rect x="6.6" y="2.6" width="10.8" height="18.8" rx="2.6"/><path d="M8.8 7h6.4M8.8 9.4h6.4M8.8 11.8h6.4M8.8 14.2h6.4M11.6 7v7.2"/>',
 # --- odpowiedzi ---
 'blyskawica': '<path d="M13.4 2.6L5.6 13.4h5.2l-.8 8 7.8-10.8h-5.2z"/>',
 'serwer':   '<rect x="3" y="4" width="18" height="6" rx="1.6"/><rect x="3" y="14" width="18" height="6" rx="1.6"/><path d="M6.6 7h.01M6.6 17h.01"/>',
 'tarcza':   '<path d="M12 3l7 3v5.4c0 4.2-2.9 7.5-7 8.4-4.1-.9-7-4.2-7-8.4V6l7-3z"/><path d="M8.9 12.1l2.2 2.2 4.1-4.3"/>',
 'suwaki':   '<path d="M4 7h9M19 7h1M4 12h5M15 12h5M4 17h11M21 17h0"/><circle cx="16" cy="7" r="2.1"/><circle cx="12" cy="12" r="2.1"/><circle cx="18" cy="17" r="2.1"/>',
 'nieskonczonosc': '<path d="M8.6 9.3c-2 0-3.6 1.2-3.6 2.7s1.6 2.7 3.6 2.7c3.1 0 4.1-5.4 7.2-5.4 2 0 3.6 1.2 3.6 2.7s-1.6 2.7-3.6 2.7c-3.1 0-4.1-5.4-7.2-5.4z"/>',
 'rozmowa':  '<rect x="3" y="4.5" width="18" height="12.5" rx="3.2"/><path d="M8.2 17l-1.2 3.4 4.4-3.4"/><path d="M8.6 10.8h6.8"/>',
 'telefon':  '<rect x="6.6" y="2.6" width="10.8" height="18.8" rx="2.6"/><path d="M10.6 5.4h2.8M10.1 18.4h3.8"/>',
}

EN = {
 'nad': 'Both sides of every tile',
 'tyt': 'Six problems you already know. Six answers you don&rsquo;t.',
 'lede': 'Every tile starts on the version you have probably already lived with. Hover it to see the other side &mdash; click to keep it there.',
 'przod': 'Usually',
 'tyl': 'Here',
 'wlacz': 'Show all answers',
 'wylacz': 'Back to the problems',
 'obroc': 'turn the tile over',
 'hint': 'hover any tile &mdash; click to keep it turned',
 'hint_dotyk': 'tap any tile to turn it over',
 'kafle': [
  ( 'czekanie', 'Waiting', 'Your page makes the customer wait',
    'They are already on your site while the price list is still being fetched from Google. The one thing they came for is the slowest thing on the page.',
    'blyskawica', 'Instant', 'The prices are there the moment the page opens',
    'The table is already saved on your own server, so nothing is fetched while somebody waits. Google reads it too, which means your prices can turn up in search results.' ),
  ( 'pusto', 'Blank', 'One bad day at Google and your prices are gone',
    'Somebody switches the sheet to private, or Google wobbles for ten minutes, and your customer is looking at an empty box where the price list should be.',
    'tarcza', 'Always up', 'Your prices stay up even when Google does not',
    'Your site keeps its own copy, so the last good version of the price list is always on the page. A blank table has no way of appearing.' ),
  ( 'arkusz', 'Spreadsheet', 'It looks like a spreadsheet dropped into your site',
    'Grey gridlines, the wrong font, somebody else&rsquo;s colours. Making it match the rest of the page means writing CSS &mdash; or paying somebody who can.',
    'suwaki', 'Your style', 'It looks like the rest of your site',
    'Colours, fonts, stripes, borders &mdash; all set by clicking, and you see each change as you make it. No code, and nobody to pay to make a table presentable.' ),
  ( 'sufit', 'Capped', 'Free stops exactly where you need it',
    'Twenty rows, a watermark under the table, a trial with a date on it. You usually find the wall once the page has been built around it.',
    'nieskonczonosc', 'Uncapped', 'Free means the whole thing, not a taster',
    'No row limit, no watermark, no expiry date. Put your real price list in it, use it on a live page, and pay only if you later want the extras.' ),
  ( 'cisza', 'Ignored', 'You write in and nothing comes back',
    'Something stops working on a Friday and the reply is a link to the documentation you had already read. Meanwhile the prices on your site are wrong.',
    'rozmowa', 'Support', 'The person who wrote it answers you',
    'Not a ticket queue &mdash; the developer, within one working day on Pro. And every new WordPress release is tested against the plugin before it reaches you.' ),
  ( 'mrowisko', 'Squint', 'On a phone it turns into an ant hill',
    'Six columns crushed into a screen the width of a hand. Your customer pinches, zooms, gives up, and rings somebody whose prices they could actually read.',
    'telefon', 'Readable', 'It stays readable on a phone',
    'A narrow screen gets proper cards, one item at a time, or a table you swipe sideways &mdash; your choice. Half your customers are on a phone, and all of them can read it.' ),
 ],
}

PL = {
 'nad': 'Dwie strony każdego kafla',
 'tyt': 'Sześć problemów, które znasz. Sześć odpowiedzi, których nie.',
 'lede': 'Każdy kafel zaczyna od wersji, którą pewnie już przerabiałeś. Najedź, żeby zobaczyć drugą stronę &mdash; kliknij, żeby została.',
 'przod': 'Zwykle',
 'tyl': 'Tutaj',
 'wlacz': 'Pokaż wszystkie odpowiedzi',
 'wylacz': 'Wróć do problemów',
 'obroc': 'obróć kafel',
 'hint': 'najedź na dowolny kafel &mdash; kliknij, żeby został obrócony',
 'hint_dotyk': 'stuknij dowolny kafel, żeby go obrócić',
 'kafle': [
  ( 'czekanie', 'Czekanie', 'Twoja strona każe klientowi czekać',
    'Jest już na stronie, a cennik dopiero się pobiera z Google. Najwolniejsza rzecz na stronie to akurat ta, po którą przyszedł.',
    'blyskawica', 'Od razu', 'Ceny są na miejscu, gdy tylko strona się otworzy',
    'Tabela leży gotowa na Twoim serwerze, więc nic się nie pobiera, kiedy ktoś czeka. Wyszukiwarka też ją czyta, więc Twoje ceny mogą trafić do wyników.' ),
  ( 'pusto', 'Pustka', 'Jedna zła godzina u Google i cennika nie ma',
    'Ktoś przestawia arkusz na prywatny albo Google ma dziesięć minut awarii &mdash; a klient patrzy na pustą ramkę w miejscu cennika.',
    'tarcza', 'Zawsze jest', 'Twoje ceny są na stronie, nawet gdy Google ich nie ma',
    'Strona trzyma własną kopię, więc zostaje na niej ostatnia dobra wersja cennika. Pusta tabela nie ma jak się pojawić.' ),
  ( 'arkusz', 'Arkusz', 'Wygląda jak arkusz wrzucony na stronę',
    'Szare kratki, nie ta czcionka, czyjeś kolory. Dopasowanie do reszty strony oznacza pisanie CSS &mdash; albo zapłacenie komuś, kto to umie.',
    'suwaki', 'Twój styl', 'Wygląda jak reszta Twojej strony',
    'Kolory, litery, paski, ramki &mdash; wszystko klikaniem, a zmianę widzisz w trakcie. Bez kodu i bez płacenia komukolwiek za to, żeby tabela ładnie wyglądała.' ),
  ( 'sufit', 'Sufit', 'Darmowa kończy się dokładnie tam, gdzie jej potrzebujesz',
    'Dwadzieścia wierszy, znak wodny pod tabelą, okres próbny z datą. Ścianę znajdujesz zwykle wtedy, gdy strona jest już wokół niej zbudowana.',
    'nieskonczonosc', 'Bez sufitu', 'Darmowa to całość, a nie próbka',
    'Bez limitu wierszy, bez znaku wodnego, bez terminu ważności. Wrzuć prawdziwy cennik, używaj na żywej stronie, a zapłać tylko wtedy, gdy zechcesz dodatków.' ),
  ( 'cisza', 'Bez pomocy', 'Piszesz i nic nie wraca',
    'Coś przestaje działać w piątek, a w odpowiedzi dostajesz odnośnik do dokumentacji, którą już przeczytałeś. W tym czasie ceny na stronie są błędne.',
    'rozmowa', 'Wsparcie', 'Odpisuje Ci człowiek, który to napisał',
    'Nie kolejka zgłoszeń &mdash; autor wtyczki, w Pro w ciągu jednego dnia roboczego. A każde nowe wydanie WordPressa jest na niej sprawdzane, zanim do Ciebie trafi.' ),
  ( 'mrowisko', 'Mrowisko', 'W telefonie robi się z tego mrowisko',
    'Sześć kolumn wciśniętych w ekran szerokości dłoni. Klient przybliża, powiększa, poddaje się i dzwoni tam, gdzie dało się odczytać ceny.',
    'telefon', 'Czytelny', 'W telefonie dalej da się to przeczytać',
    'Wąski ekran dostaje porządne karty, po jednej pozycji naraz, albo tabelę przesuwaną palcem &mdash; do wyboru. Połowa klientów jest na telefonie i każdy to odczyta.' ),
 ],
}


# UWAGA dla całego szablonu: każdy znacznik MUSI zostać w jednej linijce.
# Divi wstawia w miejscu złamanego wiersza <br />, co rozbija znacznik i wysypuje
# jego atrybuty na stronę jako widoczny tekst.

SZABLON = r'''<!-- =========================================================================
     SZEŚĆ KAFLI O DWÓCH STRONACH — jeden moduł Kod w Divi.

     Wierzch kafla to wada, którą klient zna. Spód to nasza odpowiedź.
     Najazd myszą obraca kafel, kliknięcie zostawia go obróconym, przełącznik
     nad siatką obraca wszystkie po kolei.

     Sekcja NIE przejmuje przewijania strony i nie wychodzi poza wiersz Divi,
     więc sama z siebie stoi równo z resztą strony.

     UWAGA: każdy znacznik musi zostać w jednej linijce — patrz wyżej.
     ========================================================================= -->
<!-- Kroje pisma. Zasada: tytuły serif (Inria), reszta IBM Plex Sans.
     Jeśli strona ładuje je gdzie indziej, tę linijkę można skasować. -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Inria+Serif:wght@300;400;700&display=swap">

<section class="lst-roznica" id="lst-roznica">
	<div class="lst-roznica-glowa">
		<p class="lst-roznica-nad">{NAD}</p>
		<h2 class="lst-roznica-tyt">{TYT}</h2>
		<p class="lst-roznica-lede">{LEDE}</p>
	</div>
	<div class="lst-roznica-sterowanie">
		<button type="button" class="lst-roznica-przelacznik" id="lst-roznica-przelacznik" aria-pressed="false" data-wlacz="{WLACZ}" data-wylacz="{WYLACZ}"><span class="lst-roznica-znaczek" aria-hidden="true"></span><span class="lst-roznica-napis">{WLACZ}</span></button>
		<p class="lst-roznica-hint"><span class="lst-hint-mysz">{HINT}</span><span class="lst-hint-dotyk">{HINT_DOTYK}</span></p>
	</div>
	<div class="lst-roznica-siatka">
{KAFLE}
	</div>
</section>

<style>
/* ===========================================================================
   Wszystko pod .lst- , więc Divi zostaje nietknięte — i odwrotnie.
   =========================================================================== */
.lst-roznica {
	--lst-mieta: 95, 227, 207;
	--lst-tlo: 30, 36, 35;
	--lst-panel: #232b2a;
	--lst-panel-dol: #1d2423;
	/* Ciemniej niż tło strony (#232a29). Wcześniej było #242a29 — różnica
	   jednego punktu, przez co kafle rozpływały się w tle. */
	--lst-wada: #1a201f;
	--lst-wada-dol: #151b1a;
	--lst-linia: rgba( 138, 168, 163, .16 );
	--lst-linia-wada: rgba( 138, 168, 163, .3 );
	--lst-tekst: #eaf3f1;
	--lst-tekst-2: #9db3b0;
	--lst-tekst-3: #7b918e;
	--lst-szary: #8b9996;
	--lst-szary-2: #6f7d7b;
	--lst-mono: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;
	--lst-serif: "Inria Serif", "Iowan Old Style", Georgia, serif;

	font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif;
	color: var( --lst-tekst );

	/* ---- szerokość ------------------------------------------------------
	 * Ustawione tak jak wiersze na stronie: 90% okna, ale nie więcej niż
	 * 1800 px. Chcesz inaczej — zmień te dwie wartości i nic więcej.
	 *
	 * Sekcja wychodzi najpierw na pełną szerokość okna, a dopiero potem sama
	 * się zawęża. Dzięki temu trzyma tę samą szerokość niezależnie od tego,
	 * jak ustawiony jest wiersz Divi, w którym moduł siedzi.
	 */
	--lst-szerokosc: 90%;
	--lst-max: 1800px;

	/* Podmieniane przez skrypt na szerokość okna BEZ paska przewijania —
	   samo 100vw liczy pasek i potrafi zrobić poziomy suwak. */
	--lst-pelna: 100vw;

	width: var( --lst-pelna );
	margin: 0 calc( 50% - var( --lst-pelna ) / 2 );
	padding: clamp( 2.4rem, 5vw, 4rem ) 0;
	background: none;
	border: 0;
}


.lst-roznica * { box-sizing: border-box; }

/*
 * Zerowanie tego, co motyw nadaje zwykłym znacznikom.
 *
 * Bez tego moduł wygląda inaczej na każdej stronie, bo motywy stylują same
 * znaczniki — najbardziej <article>, który w WordPressie oznacza wpis na blogu
 * i rutynowo dostaje własne tło, ramkę i margines wewnętrzny. Efekt: za każdym
 * kaflem stało kwadratowe, nieprzezroczyste pudło.
 *
 * :where() celowo — nie dodaje wagi, więc te zerowania przegrywają z każdą
 * regułą modułu poniżej i niczego mu nie psują.
 */
.lst-roznica :where( div, p, h2, h3, span, button, svg, a, ul, li, article, section ) {
	margin: 0;
	padding: 0;
	background: none;
	border: 0;
	border-radius: 0;
	box-shadow: none;
	text-shadow: none;
	text-align: left;
	text-transform: none;
	text-decoration: none;
	letter-spacing: normal;
	list-style: none;
	float: none;
	min-width: 0;
	max-width: none;
	font: inherit;
	color: inherit;
}

/*
 * Treść wraca do szerokości reszty strony. MUSI stać po zerowaniu powyżej —
 * zerowanie kasuje max-width i marginesy, więc postawiona wcześniej nic by nie dała.
 */
.lst-roznica-glowa,
.lst-roznica-sterowanie,
.lst-roznica-siatka,
.lst-roznica-postep { width: var( --lst-szerokosc ); max-width: var( --lst-max ); margin-inline: auto; }

/* Divi wstawia <br /> w miejscu każdego złamanego wiersza. Te, które mimo
   wszystko przejdą, nie mają prawa rozpychać układu. */
.lst-roznica br { display: none; }

/* ---------- nagłówek ---------- */
.lst-roznica-glowa { display: flex; flex-direction: column; gap: .5rem; }
.lst-roznica-nad { margin: 0; font-family: var( --lst-mono ); font-size: .72rem; letter-spacing: .14em; text-transform: uppercase; color: rgb( var( --lst-mieta ) ); }
.lst-roznica-tyt { margin: 0; font-family: var( --lst-serif ); font-weight: 400; font-size: clamp( 1.9rem, 4.4vw, 3.2rem ); line-height: 1.06; color: var( --lst-tekst ); }
.lst-roznica-lede { margin: 0; max-width: 46rem; font-size: 1rem; line-height: 1.6; color: var( --lst-tekst-2 ); }

/* ---------- przełącznik nad siatką ---------- */
.lst-roznica-sterowanie { display: flex; flex-wrap: wrap; align-items: center; gap: .8rem 1.2rem; margin-block: clamp( 1.2rem, 2.6vw, 2rem ); }

.lst-roznica-przelacznik { display: inline-flex; align-items: center; gap: .6rem; margin: 0; padding: .55rem .95rem .55rem .6rem; font: inherit; font-size: .9rem; font-weight: 500; color: var( --lst-tekst ); background: var( --lst-panel ); border: 1px solid var( --lst-linia ); border-radius: 999px; cursor: pointer; transition: border-color .2s ease, background-color .2s ease; }
.lst-roznica-przelacznik:hover { border-color: rgba( var( --lst-mieta ), .5 ); }
.lst-roznica-przelacznik:focus-visible { outline: 2px solid rgb( var( --lst-mieta ) ); outline-offset: 2px; }

/* Sam suwak. Zwykły <span>, bo pole wyboru w Divi bywa przemalowane motywem. */
.lst-roznica-znaczek { position: relative; flex: 0 0 auto; width: 34px; height: 20px; border-radius: 999px; background: rgba( 138, 168, 163, .22 ); transition: background-color .25s ease; }
.lst-roznica-znaczek::after { content: ""; position: absolute; top: 3px; left: 3px; width: 14px; height: 14px; border-radius: 50%; background: var( --lst-szary ); transition: transform .25s cubic-bezier( .2, .8, .3, 1 ), background-color .25s ease; }
.lst-roznica-przelacznik[aria-pressed="true"] .lst-roznica-znaczek { background: rgba( var( --lst-mieta ), .35 ); }
.lst-roznica-przelacznik[aria-pressed="true"] .lst-roznica-znaczek::after { transform: translateX( 14px ); background: rgb( var( --lst-mieta ) ); }

.lst-roznica-hint { margin: 0; font-family: var( --lst-mono ); font-size: .72rem; letter-spacing: .04em; color: var( --lst-tekst-3 ); }

/* Na ekranie dotykowym nie ma myszy, więc nie każemy nikomu najeżdżać. */
.lst-hint-dotyk { display: none; }

@media ( hover: none ) {
	.lst-hint-mysz { display: none; }
	.lst-hint-dotyk { display: inline; }
}

/* Bez skryptu przełącznik nie ma czym ruszać, więc go nie pokazujemy. */
.lst-roznica:not( .lst-ma-skrypt ) .lst-roznica-przelacznik { display: none; }

/* ---------- siatka ---------- */
.lst-roznica-siatka { display: grid; grid-template-columns: 1fr; gap: clamp( .9rem, 1.6vw, 1.4rem ); }

@media ( min-width: 700px ) { .lst-roznica-siatka { grid-template-columns: repeat( 2, 1fr ); } }
@media ( min-width: 1100px ) { .lst-roznica-siatka { grid-template-columns: repeat( 3, 1fr ); } }

/* ---------- kafel ---------- */
/*
 * Obrót robi rotateY na wewnętrznej ramce. Obie strony leżą w tej samej komórce
 * siatki, więc kafel jest tak wysoki jak dłuższa z nich — inaczej po obrocie
 * tekst wychodziłby poza krawędź.
 */
.lst-kafel { --lst-kat: 0deg; position: relative; perspective: 1400px; cursor: pointer; border-radius: 16px; }
.lst-kafel:focus { outline: none; }
.lst-kafel:focus-visible { outline: 2px solid rgb( var( --lst-mieta ) ); outline-offset: 4px; border-radius: 18px; }

.lst-kafel-obrot { display: grid; height: 100%; transform-style: preserve-3d; transform: rotateY( var( --lst-kat ) ); transition: transform .85s cubic-bezier( .2, .75, .25, 1 ); }

/*
 * clip-path obok overflow: przy obrocie w trzech wymiarach samo overflow bywa
 * pomijane i to, co miało zostać pod krawędzią kafla, wychodzi na wierzch.
 */
.lst-kafel-bok { grid-area: 1 / 1; display: flex; flex-direction: column; gap: .55rem; padding: 1.5rem 1.4rem 1.7rem; border-radius: 16px; position: relative; overflow: hidden; clip-path: inset( 0 round 16px ); backface-visibility: hidden; -webkit-backface-visibility: hidden; }

/* Wierzch: cudza wada. Matowo, szaro, obramowanie przerywane. */
/*
 * Strona z pytaniem. Delikatny spad plus miękka poświata — bez niej kafel
 * zlewał się z siatką tła, bo oba miały niemal ten sam kolor.
 */
.lst-kafel-problem {
	background: linear-gradient( var( --lst-wada ), var( --lst-wada-dol ) );
	border: 1px dashed var( --lst-linia-wada );
	color: var( --lst-szary );
	box-shadow:
		0 18px 40px -26px rgba( 0, 0, 0, .85 ),
		0 0 34px -14px rgba( var( --lst-mieta ), .16 ),
		0 1px 0 rgba( 255, 255, 255, .03 ) inset;
}

/* Spód: nasza odpowiedź. Kolor, ostre litery, pełna ramka. */
.lst-kafel-odpowiedz { transform: rotateY( 180deg ); background: linear-gradient( var( --lst-panel ), var( --lst-panel-dol ) ); border: 1px solid var( --lst-linia ); color: var( --lst-tekst ); box-shadow: 0 1px 0 rgba( var( --lst-mieta ), .1 ) inset; }

.lst-kafel-etykieta { margin: 0; font-family: var( --lst-mono ); font-size: .68rem; letter-spacing: .16em; text-transform: uppercase; }
.lst-kafel-problem .lst-kafel-etykieta { color: var( --lst-szary-2 ); }
.lst-kafel-odpowiedz .lst-kafel-etykieta { color: rgb( var( --lst-mieta ) ); }

.lst-kafel-slowo { margin: 0; font-family: var( --lst-serif ); font-weight: 400; font-size: clamp( 2rem, 3.4vw, 2.7rem ); line-height: 1; }
.lst-kafel-problem .lst-kafel-slowo { color: var( --lst-szary ); }
.lst-kafel-odpowiedz .lst-kafel-slowo { color: var( --lst-tekst ); }

.lst-kafel-lead { margin: 0; font-size: .95rem; font-weight: 600; line-height: 1.35; }
.lst-kafel-problem .lst-kafel-lead { color: var( --lst-szary ); }
.lst-kafel-odpowiedz .lst-kafel-lead { color: var( --lst-tekst ); }

.lst-kafel-tekst { margin: 0; font-size: .92rem; line-height: 1.6; }
.lst-kafel-problem .lst-kafel-tekst { color: var( --lst-szary-2 ); }
.lst-kafel-odpowiedz .lst-kafel-tekst { color: var( --lst-tekst-2 ); }

/*
 * Znak w tle: nisko z prawej, ledwo widoczny, przycięty krawędzią kafla.
 *
 * Rysunek siedzi w osobnej ramce o narzuconym rozmiarze, a nie luzem w kaflu.
 * Powód jest praktyczny: motywy potrafią mieć własną regułę dla każdego <svg>
 * na stronie (choćby "svg { width: 100% }"), która jest mocniejsza od zwykłej
 * klasy. Taka reguła rozdmuchiwała znak do rozmiaru całego kafla i wylewała go
 * poza jego krawędzie. Teraz reguła motywu trafia w rysunek wewnątrz ramki,
 * czyli w "100% ze 132 px" — i nic się nie rusza.
 */
.lst-roznica .lst-kafel .lst-kafel-znak { position: absolute; right: 20px; bottom: 20px; width: 132px; height: 132px; overflow: hidden; pointer-events: none; display: block; }

/* Jedyne miejsce z !important w całym module — patrz wyżej: motyw też go używa. */
.lst-roznica .lst-kafel .lst-kafel-znak > svg { position: absolute; top: 0; left: 0; display: block; width: 100% !important; height: 100% !important; max-width: none !important; min-width: 0 !important; }

.lst-kafel-problem .lst-kafel-znak { color: var( --lst-szary ); opacity: .06; }
.lst-kafel-odpowiedz .lst-kafel-znak { color: rgb( var( --lst-mieta ) ); opacity: .09; }

/* ---------- co obraca kafel ----------
 *
 *   najazd myszą  -> odpowiedź, na czas, gdy mysz na nim stoi
 *   kliknięcie    -> odpowiedź zostaje po zjechaniu myszą
 *   drugi klik    -> z powrotem wada
 *
 * Najazd i kliknięcie ciągną w tę samą stronę, więc najazd na zatrzymany kafel
 * niczego nie zmienia — odpowiedź po prostu dalej stoi.
 */
@media ( hover: hover ) and ( pointer: fine ) {
	.lst-kafel:hover, .lst-kafel:focus-visible { --lst-kat: 180deg; }
	.lst-kafel:hover .lst-kafel-obrot { box-shadow: 0 10px 30px rgba( 0, 0, 0, .22 ); }
	.lst-kafel:hover .lst-kafel-problem { box-shadow: 0 18px 40px -22px rgba( 0, 0, 0, .9 ), 0 0 44px -10px rgba( var( --lst-mieta ), .26 ); }

	/* Bez ".lst-bez-najazdu": po drugim kliknięciu kafel wraca na pytanie,
	   choć mysz wciąż na nim leży — wtedy świecić nie ma czego. */
	.lst-kafel:hover:not( .lst-bez-najazdu ),
	.lst-kafel:focus-visible:not( .lst-bez-najazdu ) {
		animation: lst-kafel-blysk .95s cubic-bezier( .2, .75, .25, 1 ) forwards;
	}
}

/*
 * Błysk przy odwróceniu. Poświata rozbłyskuje w chwili obrotu i opada do
 * spokojnego poziomu — zwraca uwagę dokładnie wtedy, gdy odpowiedź się
 * pojawia, a potem przestaje przeszkadzać.
 *
 * Siedzi na opakowaniu, które się NIE obraca. Gdyby była na samej odwrotnej
 * stronie, kręciłaby się razem z nią i w połowie ruchu świeciłaby tyłem.
 */
@keyframes lst-kafel-blysk {
	0% {
		box-shadow: 0 0 0 0 rgba( var( --lst-mieta ), 0 );
	}

	45% {
		box-shadow: 0 0 0 2px rgba( var( --lst-mieta ), .6 ),
			0 0 56px -4px rgba( var( --lst-mieta ), .75 );
	}

	100% {
		box-shadow: 0 0 0 1px rgba( var( --lst-mieta ), .3 ),
			0 0 26px -8px rgba( var( --lst-mieta ), .32 );
	}
}

.lst-kafel.jest-odwrocony {
	--lst-kat: 180deg;
	animation: lst-kafel-blysk .95s cubic-bezier( .2, .75, .25, 1 ) forwards;
}

/*
 * Drugie kliknięcie ma odwrócić kafel od razu, a mysz wtedy wciąż na nim stoi
 * i sama w sobie kazałaby pokazywać odpowiedź. Ta klasa wyłącza najazd do czasu,
 * aż mysz naprawdę zjedzie z kafla — wtedy skrypt ją zdejmuje i najazd znów
 * działa normalnie. Musi stać PO regule najazdu, bo obie ważą tyle samo i
 * rozstrzyga kolejność.
 */
.lst-kafel.lst-bez-najazdu { --lst-kat: 0deg; }

/*
 * Ekran dotykowy bez skryptu: nie ma ani najazdu, ani klikania, więc zostałyby
 * same wady. W takim wypadku pokazujemy od razu odpowiedzi.
 */
@media ( hover: none ) {
	.lst-roznica:not( .lst-ma-skrypt ) .lst-kafel { --lst-kat: 180deg; }
}

/* Kto prosił o spokój, dostaje spokojną poświatę bez rozbłysku. */
@media ( prefers-reduced-motion: reduce ) {
	.lst-kafel.jest-odwrocony,
	.lst-kafel:hover:not( .lst-bez-najazdu ),
	.lst-kafel:focus-visible:not( .lst-bez-najazdu ) {
		animation: none;
		box-shadow: 0 0 0 1px rgba( var( --lst-mieta ), .3 ), 0 0 26px -8px rgba( var( --lst-mieta ), .32 );
	}
}

/* Kto prosił o spokój, dostaje przeskok zamiast obrotu. */
@media ( prefers-reduced-motion: reduce ) {
	.lst-kafel-obrot { transition: none; }
	.lst-roznica-znaczek::after { transition: none; }
}

/*
 * Przeglądarka bez obrotu w trzech wymiarach — zamiast obracać, przenikamy
 * jedną stronę w drugą. Treść jest ta sama, więc nic nie ginie.
 */
@supports not ( transform-style: preserve-3d ) {
	.lst-kafel-obrot { transform: none; }
	.lst-kafel-bok { transform: none; backface-visibility: visible; transition: opacity .3s ease; }
	.lst-kafel-odpowiedz { opacity: 0; }
	.lst-kafel.jest-odwrocony .lst-kafel-odpowiedz { opacity: 1; }
	.lst-kafel.jest-odwrocony .lst-kafel-problem { opacity: 0; }
	@media ( hover: hover ) {
		.lst-kafel:hover .lst-kafel-odpowiedz { opacity: 1; }
		.lst-kafel:hover .lst-kafel-problem { opacity: 0; }
	}
	.lst-kafel.lst-bez-najazdu .lst-kafel-odpowiedz { opacity: 0; }
	.lst-kafel.lst-bez-najazdu .lst-kafel-problem { opacity: 1; }
}
</style>

<script>
( function () {
	'use strict';

	function start() {
		var sekcja = document.getElementById( 'lst-roznica' );

		if ( ! sekcja ) {
			return;
		}

		var kafle = [].slice.call( sekcja.querySelectorAll( '.lst-kafel' ) );
		var przelacznik = document.getElementById( 'lst-roznica-przelacznik' );

		/* Dopiero to odsłania przełącznik — bez skryptu nie miałby czym ruszać. */
		sekcja.classList.add( 'lst-ma-skrypt' );

		/*
		 * Szerokość okna bez paska przewijania. Bez tego sekcja jest o szerokość
		 * paska za szeroka i strona dostaje poziomy suwak.
		 */
		function pelna() {
			sekcja.style.setProperty( '--lst-pelna', document.documentElement.clientWidth + 'px' );
		}

		pelna();
		window.addEventListener( 'resize', pelna );

		function obroc( kafel, stan ) {
			kafel.classList.toggle( 'jest-odwrocony', stan );
			kafel.setAttribute( 'aria-pressed', stan ? 'true' : 'false' );

			/*
			 * Zdejmując zatrzymanie, wyłączamy też najazd — inaczej kafel stałby
			 * dalej na odpowiedzi, bo mysz wciąż na nim jest. Zatrzymując,
			 * wyłączenie kasujemy, żeby nie walczyło z obrotem.
			 */
			kafel.classList.toggle( 'lst-bez-najazdu', ! stan );
		}

		kafle.forEach( function ( kafel ) {
			/* Mysz zjechała z kafla — najazd znów ma działać normalnie. */
			kafel.addEventListener( 'mouseleave', function () { kafel.classList.remove( 'lst-bez-najazdu' ); } );
			kafel.addEventListener( 'blur', function () { kafel.classList.remove( 'lst-bez-najazdu' ); } );

			kafel.addEventListener( 'click', function () {
				obroc( kafel, ! kafel.classList.contains( 'jest-odwrocony' ) );
				zbierz();
			} );

			/* Spacja i Enter mają działać jak kliknięcie — to jest przycisk. */
			kafel.addEventListener( 'keydown', function ( e ) {
				if ( ' ' === e.key || 'Enter' === e.key || 'Spacebar' === e.key ) {
					e.preventDefault();
					kafel.click();
				}
			} );
		} );

		function zbierz() {
			if ( ! przelacznik ) {
				return;
			}

			var ile = kafle.filter( function ( k ) { return k.classList.contains( 'jest-odwrocony' ); } ).length;
			var wszystkie = ile === kafle.length;

			przelacznik.setAttribute( 'aria-pressed', wszystkie ? 'true' : 'false' );
			przelacznik.querySelector( '.lst-roznica-napis' ).textContent = wszystkie ? przelacznik.getAttribute( 'data-wylacz' ) : przelacznik.getAttribute( 'data-wlacz' );
		}

		if ( przelacznik ) {
			przelacznik.addEventListener( 'click', function () {
				var wlaczamy = 'true' !== przelacznik.getAttribute( 'aria-pressed' );

				/*
				 * Fala zamiast jednoczesnego klapnięcia sześciu kafli. Opóźnienie
				 * zdejmujemy zaraz po obrocie, żeby zwykłe najechanie myszą
				 * odpowiadało od razu.
				 */
				kafle.forEach( function ( kafel, i ) {
					var ramka = kafel.querySelector( '.lst-kafel-obrot' );

					if ( ramka ) {
						ramka.style.transitionDelay = ( i * 90 ) + 'ms';
						window.setTimeout( function () { ramka.style.transitionDelay = ''; }, 950 + i * 90 );
					}

					obroc( kafel, wlaczamy );
				} );

				zbierz();
			} );
		}

		zbierz();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
</script>
'''

KAFEL = (
 '\t\t<div class="lst-kafel" role="button" tabindex="0" aria-pressed="false" aria-label="{ARIA}">'
 '<div class="lst-kafel-obrot">'
 '<div class="lst-kafel-bok lst-kafel-problem">'
 '<p class="lst-kafel-etykieta">{ETY_P}</p>'
 '<p class="lst-kafel-slowo">{SLOWO_P}</p>'
 '<p class="lst-kafel-lead">{LEAD_P}</p>'
 '<p class="lst-kafel-tekst">{TEKST_P}</p>'
 '<span class="lst-kafel-znak" aria-hidden="true"><svg viewBox="0 0 24 24" width="132" height="132" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" focusable="false">{ZNAK_P}</svg></span>'
 '</div>'
 '<div class="lst-kafel-bok lst-kafel-odpowiedz">'
 '<p class="lst-kafel-etykieta">{ETY_O}</p>'
 '<p class="lst-kafel-slowo">{SLOWO_O}</p>'
 '<p class="lst-kafel-lead">{LEAD_O}</p>'
 '<p class="lst-kafel-tekst">{TEKST_O}</p>'
 '<span class="lst-kafel-znak" aria-hidden="true"><svg viewBox="0 0 24 24" width="132" height="132" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" focusable="false">{ZNAK_O}</svg></span>'
 '</div>'
 '</div>'
 '</div>'
)


def podstaw( wzor, pola ):
	for klucz, wartosc in pola.items():
		wzor = wzor.replace( '{' + klucz + '}', wartosc )
	return wzor


def zbuduj( t, plik ):
	kafle = []

	for znak_p, slowo_p, lead_p, tekst_p, znak_o, slowo_o, lead_o, tekst_o in t['kafle']:
		kafle.append( podstaw( KAFEL, {
			'ARIA':    slowo_p + ' &rarr; ' + slowo_o + ' &mdash; ' + t['obroc'],
			'ETY_P':   t['przod'],  'SLOWO_P': slowo_p, 'LEAD_P': lead_p, 'TEKST_P': tekst_p, 'ZNAK_P': ZNAKI[ znak_p ],
			'ETY_O':   t['tyl'],    'SLOWO_O': slowo_o, 'LEAD_O': lead_o, 'TEKST_O': tekst_o, 'ZNAK_O': ZNAKI[ znak_o ],
		} ) )

	html = podstaw( SZABLON, {
		'NAD': t['nad'], 'TYT': t['tyt'], 'LEDE': t['lede'],
		'WLACZ': t['wlacz'], 'WYLACZ': t['wylacz'], 'HINT': t['hint'], 'HINT_DOTYK': t['hint_dotyk'],
		'KAFLE': '\n'.join( kafle ),
	} )

	with open( plik, 'w', encoding='utf-8' ) as f:
		f.write( html )

	print( plik + ' — kafli: ' + str( len( kafle ) ) )


zbuduj( EN, 'kafle-en.html' )
zbuduj( PL, 'kafle-pl.html' )
