# -*- coding: utf-8 -*-
"""Cennik — trzy karty planów jako jeden moduł Kod w Divi."""

EN = {
 'odznaka': 'Most popular',
 'uwaga': 'Design preview. Prices and plan names are placeholders &mdash; to be settled before launch.',
 'plany': [
  { 'nad': 'Plan 01', 'nazwa': 'Free', 'kwota': '$0', 'dopisek': '&mdash; forever',
    'polecany': False, 'przycisk': 'Get it from WordPress.org', 'adres': '#',
    'punkty': [ '6 sheets, unlimited rows', 'Refresh every 15 minutes', 'Support on the WordPress.org forum' ] },
  { 'nad': 'Plan 02', 'nazwa': 'Pro', 'kwota': '$49', 'dopisek': '/ year &mdash; 5 sites',
    'polecany': True, 'przycisk': 'Buy Pro', 'adres': '#',
    'punkty': [ 'Everything in Free, unlimited sheets', 'Refresh as often as every minute',
                'Private sheets, colour rules, filters', 'Export to Excel, CSV and print', 'Email support within 24 h' ] },
  { 'odmiana': ' lst-cen-agencja', 'nad': 'Plan 03', 'nazwa': 'Agency', 'kwota': '$149', 'dopisek': '/ year &mdash; 25 sites',
    'polecany': False, 'przycisk': 'Buy Agency', 'adres': '#',
    'punkty': [ 'Everything in Pro', 'One licence for 25 client sites', 'Single consolidated invoice' ] },
 ],
}

PL = {
 'odznaka': 'Najczęściej wybierany',
 'uwaga': 'Podgląd wyglądu. Ceny i nazwy planów są tymczasowe &mdash; do ustalenia przed startem.',
 'plany': [
  { 'nad': 'Plan 01', 'nazwa': 'Darmowa', 'kwota': '0 zł', 'dopisek': '&mdash; na zawsze',
    'polecany': False, 'przycisk': 'Pobierz z WordPress.org', 'adres': '#',
    'punkty': [ '6 arkuszy, bez limitu wierszy', 'Odświeżanie co 15 minut', 'Wsparcie na forum WordPress.org' ] },
  { 'nad': 'Plan 02', 'nazwa': 'Pro', 'kwota': '199 zł', 'dopisek': '/ rok &mdash; 5 stron',
    'polecany': True, 'przycisk': 'Kup Pro', 'adres': '#',
    'punkty': [ 'Wszystko z darmowej, bez limitu arkuszy', 'Odświeżanie nawet co minutę',
                'Prywatne arkusze, reguły kolorów, filtry', 'Eksport do Excela, CSV i druk', 'Odpowiedź mailem w dobę' ] },
  { 'odmiana': ' lst-cen-agencja', 'nad': 'Plan 03', 'nazwa': 'Agencja', 'kwota': '599 zł', 'dopisek': '/ rok &mdash; 25 stron',
    'polecany': False, 'przycisk': 'Kup Agencję', 'adres': '#',
    'punkty': [ 'Wszystko z Pro', 'Jedna licencja na 25 stron klientów', 'Jedna zbiorcza faktura' ] },
 ],
}


# UWAGA: każdy znacznik MUSI zostać w jednej linijce — Divi wstawia w miejscu

# złamanego wiersza <br />, co rozbija znacznik i wysypuje jego atrybuty na stronę.

PTASZEK = '<span class="lst-cen-znak" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M5 12.6l4.6 4.6L19 7.4"/></svg></span>'

SZABLON = r'''<!-- WERSJA 1 z 22.09 — jeśli pierwsza linijka w module mówi co innego, na stronie jest stary kod. -->
<!-- =========================================================================
     CENNIK — trzy karty planów, jeden moduł Kod w Divi.

     Adresy przycisków są na razie puste (#). Podmień je na swoje linki
     w znacznikach <a class="lst-cen-przycisk" href="#">.

     UWAGA: każdy znacznik musi zostać w jednej linijce — patrz wyżej.
     ========================================================================= -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Inria+Serif:wght@300;400;700&display=swap">

<section class="lst-cen" id="lst-cen">
	<svg class="lst-cen-filtr" aria-hidden="true" focusable="false" width="0" height="0"><defs><filter id="lst-cen-szum" x="-35%" y="-35%" width="170%" height="170%" color-interpolation-filters="sRGB"><feTurbulence type="fractalNoise" baseFrequency="0.0042 0.0088" numOctaves="3" seed="7" result="szum"><animate attributeName="baseFrequency" dur="26s" values="0.0042 0.0088; 0.0071 0.0052; 0.0036 0.0095; 0.0042 0.0088" calcMode="spline" keyTimes="0; 0.34; 0.67; 1" keySplines="0.45 0 0.55 1; 0.45 0 0.55 1; 0.45 0 0.55 1" repeatCount="indefinite"></animate></feTurbulence><feDisplacementMap in="SourceGraphic" in2="szum" scale="38" xChannelSelector="R" yChannelSelector="G"></feDisplacementMap></filter></defs></svg>
	<div class="lst-cen-siatka">
{KARTY}
	</div>
	<p class="lst-cen-uwaga">{UWAGA}</p>
</section>

<style>
/* ===========================================================================
   Wszystko pod .lst-cen , więc Divi zostaje nietknięte — i odwrotnie.
   =========================================================================== */
.lst-cen {
	--lst-mieta: 95, 227, 207;
	--lst-panel: #1b2221;
	--lst-panel-dol: #161d1c;
	--lst-linia: rgba( 138, 168, 163, .16 );
	--lst-tekst: #eaf3f1;
	--lst-tekst-2: #9db3b0;
	--lst-tekst-3: #7b918e;
	--lst-neon-1: 95, 227, 207;    /* mięta — kolor marki */
	--lst-neon-2: 214, 255, 248;   /* rozgrzany rdzeń, prawie biały */
	--lst-neon-3: 32, 168, 172;    /* głęboka zieleń morska */
	/* Uwaga: w neonie NIE MA już ciemnego odcinka. Gradient stożkowy odwzorowuje
	   się na prostokąt nierówno — przy krawędziach jeden mały zakres kąta rozciąga
	   się na długi odcinek ramki. Ciemny stop potrafił przez to rozlać się na całą
	   górną krawędź i wyglądało to tak, jakby neon gasł i po chwili wracał. */
	--lst-neon-0: 46, 190, 190;    /* najciemniejszy odcinek — nadal jasny */

	--lst-mono: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;
	--lst-serif: "Inria Serif", "Iowan Old Style", Georgia, serif;

	font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif;
	color: var( --lst-tekst );

	/* ---- szerokość: tak jak wiersze na stronie ---- */
	--lst-szerokosc: 90%;
	--lst-max: 1800px;
	--lst-pelna: 100vw;

	width: var( --lst-pelna );
	margin: 0 calc( 50% - var( --lst-pelna ) / 2 );
	padding: clamp( 2rem, 4vw, 3.4rem ) 0;
	background: none;

	/* Poświata kart sięga poza ich krawędzie; "clip" ucina to w poziomie,
	   żeby strona nie dostała poziomego suwaka. */
	overflow-x: clip;
}

.lst-cen.lst-cen { border: 0 !important; outline: 0 !important; }
.lst-cen * { box-sizing: border-box; }

/* SVG z filtrem to tylko przepis, nie obrazek — nie ma zajmować miejsca w treści. */
.lst-cen .lst-cen-filtr { position: absolute; width: 0; height: 0; overflow: hidden; pointer-events: none; }
.lst-cen br { display: none; }

/* Opakowania Divi wokół TEGO modułu — klasę nadaje im skrypt. Bez tego
   obramowanie kolumny albo wiersza obrysowuje karty i wygląda jak ich własne. */
.lst-cen-gniazdo.lst-cen-gniazdo { border: 0 !important; outline: 0 !important; }

/*
 * Zerowanie tego, co motyw nadaje zwykłym znacznikom. :where() celowo —
 * nie dodaje wagi, więc przegrywa z każdą regułą modułu poniżej.
 */
.lst-cen :where( div, p, h3, span, a, ul, li, svg, section ) {
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

/* ---------- siatka ---------- */
.lst-cen-siatka { width: var( --lst-szerokosc ); max-width: var( --lst-max ); margin-inline: auto; display: grid; grid-template-columns: 1fr; gap: clamp( 1rem, 1.8vw, 1.6rem ); align-items: stretch; }

@media ( min-width: 900px ) { .lst-cen-siatka { grid-template-columns: repeat( 3, 1fr ); } }

/* ---------- karta ---------- */
/*
 * Karta składa się z DWÓCH warstw i to nie jest ozdobnik.
 *
 * Zewnętrzna (.lst-cen-karta) jest przezroczysta i trzyma neon. Wewnętrzna
 * (.lst-cen-panel) ma nieprzezroczyste tło i całą treść, i leży NAD neonem —
 * dlatego z neonu widać tylko dwupikselowy brzeg dookoła.
 *
 * Wcześniej neon był pod kartą przez ujemną warstwę. Działało, dopóki karta
 * nie dostała przy najechaniu delikatnego uniesienia — a uniesienie zamyka
 * kartę we własnym porządku rysowania i wtedy „pod kartą" zaczyna znaczyć
 * „nad jej tłem". Cały gradient wylewał się na kartę i tekst znikał w bieli.
 * Przy dwóch warstwach kolejność wynika z budowy, więc nie da się jej zepsuć.
 */
.lst-cen .lst-cen-karta {
	display: flex;
	border: 1px solid var( --lst-linia ) !important;
	border-radius: 16px;
	position: relative;
	transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
}

.lst-cen .lst-cen-panel {
	position: relative;
	z-index: 1;
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: .55rem;
	padding: 1.7rem 1.6rem 1.6rem;
	border-radius: 15px;
	overflow: hidden;
	background: linear-gradient( var( --lst-panel ), var( --lst-panel-dol ) );
}

/* Włos światła po wewnętrznej stronie górnej krawędzi — tak wygląda szkło
   położone na czymś podświetlonym. Tylko na karcie Pro. */
.lst-cen .lst-cen-polecana .lst-cen-panel { box-shadow: inset 0 1px 0 rgba( var( --lst-neon-2 ), .22 ); }

/* Treść ma stać nad przebłyskiem, nie pod nim. */
.lst-cen .lst-cen-panel > * { position: relative; z-index: 1; }

/* ===========================================================================
   POŁYSK — światło przesuwające się w karcie przy najechaniu.

   Wybrane wykończenie: satyna — promienie rozmyte tak, że nie widać punktu,
   w którym się schodzą.

   Zasady, które trzymają to w ryzach:
   — krycie 26%, żeby tekst pod spodem został czytelny,
   — tryb „screen": warstwa DODAJE światła zamiast malować po wierzchu,
   — warstwa jest większa od karty i rozmyta, więc przy krawędziach nie
     widać, gdzie się kończy,
   — treść leży nad połyskiem.
   =========================================================================== */

.lst-cen .lst-cen-panel::before {
	content: "";
	position: absolute;

	/* Większe niż karta: rozmycie ma gasnąć poza kadrem, nie przy samym brzegu. */
	inset: -45%;
	z-index: 0;
	pointer-events: none;
	opacity: 0;
	mix-blend-mode: screen;
	transition: opacity .5s ease;
}


/* ---------------------------------------------------------------------------
   1. SATYNA — jedna warstwa promieni, mocno rozmyta.

   Ostre promienie schodzące się w jeden punkt to właśnie to, co wygląda
   twardo. Rozmycie 40 px zamienia je w miękkie smugi bez wyraźnego środka.
   --------------------------------------------------------------------------- */
.lst-cen .lst-cen-panel::before {
	background: conic-gradient( from var( --lst-neon-kat ) at var( --lst-neon-cx ) var( --lst-neon-cy ),
		rgba( var( --lst-neon-0 ), .4 ) 0deg, rgba( var( --lst-neon-3 ), .8 ) 60deg,
		rgba( var( --lst-neon-1 ), 1 ) 120deg, rgba( var( --lst-neon-3 ), .8 ) 190deg,
		rgba( var( --lst-neon-0 ), .35 ) 250deg, rgba( var( --lst-neon-1 ), .95 ) 320deg,
		rgba( var( --lst-neon-0 ), .4 ) 360deg );
	filter: blur( 40px );
}

/*
 * Kąt obrotu i środek gradientu muszą być ZAREJESTROWANE jako typ, inaczej
 * przeglądarka traktuje je jak zwykły tekst: nie umie ich płynnie zmieniać,
 * a gradient, który z nich korzysta, bywa uznany za nieprawidłowy i w ogóle
 * się nie rysuje. Ta deklaracja jest warunkiem działania całego efektu.
 */
@property --lst-neon-kat {
	syntax: "<angle>";
	inherits: true;
	initial-value: 0deg;
}

@property --lst-neon-cx {
	syntax: "<percentage>";
	inherits: true;
	initial-value: 50%;
}

@property --lst-neon-cy {
	syntax: "<percentage>";
	inherits: true;
	initial-value: 42%;
}

@keyframes lst-cen-obrot { to { --lst-neon-kat: 360deg; } }

/*
 * Dryf środka gradientu. Dwie osie, dwa różne i niepodzielne przez siebie
 * okresy (47 i 61 sekund) — przez to wzór nigdy nie wraca do tego samego
 * układu i nie widać, że cokolwiek się zapętla. Ruch jest na tyle powolny,
 * że nie rzuca się w oczy; czuć go dopiero, gdy się patrzy dłużej.
 */
@keyframes lst-cen-dryf-x {
	from { --lst-neon-cx: 40%; }
	to   { --lst-neon-cx: 60%; }
}

@keyframes lst-cen-dryf-y {
	from { --lst-neon-cy: 34%; }
	to   { --lst-neon-cy: 52%; }
}


.lst-cen .lst-cen-polecana {
	border-color: transparent !important;
	box-shadow: none;
	animation:
		lst-cen-obrot 16s linear infinite,
		lst-cen-dryf-x 47s ease-in-out infinite alternate,
		lst-cen-dryf-y 61s ease-in-out infinite alternate;
}

/* Rurka neonu. */
.lst-cen .lst-cen-polecana::before {
	content: "";
	position: absolute;
	inset: -2px;
	border-radius: 18px;
	z-index: 0;
	pointer-events: none;
	background: conic-gradient( from var( --lst-neon-kat ) at var( --lst-neon-cx ) var( --lst-neon-cy ),
		rgba( var( --lst-neon-3 ), 1 ) 0deg,
		rgba( var( --lst-neon-1 ), 1 ) 30deg,
		rgba( var( --lst-neon-2 ), 1 ) 58deg,
		rgba( var( --lst-neon-1 ), 1 ) 86deg,
		rgba( var( --lst-neon-0 ), 1 ) 130deg,
		rgba( var( --lst-neon-3 ), 1 ) 172deg,
		rgba( var( --lst-neon-1 ), 1 ) 212deg,
		rgba( var( --lst-neon-2 ), 1 ) 240deg,
		rgba( var( --lst-neon-1 ), 1 ) 268deg,
		rgba( var( --lst-neon-0 ), 1 ) 312deg,
		rgba( var( --lst-neon-3 ), 1 ) 352deg,
		rgba( var( --lst-neon-3 ), 1 ) 360deg );
}

/* Poświata. Ten sam gradient, ale zniekształcony szumem i mocno rozmyty. */
.lst-cen .lst-cen-polecana::after {
	content: "";
	position: absolute;
	inset: -11px;
	border-radius: 26px;
	z-index: 0;
	pointer-events: none;
	opacity: .92;
	background: conic-gradient( from var( --lst-neon-kat ) at var( --lst-neon-cx ) var( --lst-neon-cy ),
		rgba( var( --lst-neon-3 ), .78 ) 0deg,
		rgba( var( --lst-neon-1 ), .95 ) 30deg,
		rgba( var( --lst-neon-2 ), 1 ) 58deg,
		rgba( var( --lst-neon-1 ), .95 ) 86deg,
		rgba( var( --lst-neon-0 ), .8 ) 130deg,
		rgba( var( --lst-neon-3 ), .82 ) 172deg,
		rgba( var( --lst-neon-1 ), .95 ) 212deg,
		rgba( var( --lst-neon-2 ), 1 ) 240deg,
		rgba( var( --lst-neon-1 ), .95 ) 268deg,
		rgba( var( --lst-neon-0 ), .8 ) 312deg,
		rgba( var( --lst-neon-3 ), .78 ) 352deg,
		rgba( var( --lst-neon-3 ), .78 ) 360deg );
	filter: url( #lst-cen-szum ) blur( 10px );
}

/* ---------- treść karty ---------- */
.lst-cen-nad { font-family: var( --lst-mono ); font-size: .78rem; letter-spacing: .14em; text-transform: uppercase; color: var( --lst-tekst-3 ); }
.lst-cen-polecana .lst-cen-nad { color: rgb( var( --lst-mieta ) ); }

.lst-cen-rzad { display: flex; align-items: baseline; flex-wrap: wrap; gap: .6rem; margin-top: .2rem; }
/* Nazwy planów w rozmiarze tytułu sekcji — ten sam clamp co .lst-jazda-tyt
   i .lst-roznica-tyt, żeby zmiana rozmiaru tytułów gdziekolwiek indziej
   nie zostawiła cennika w tyle. */
.lst-cen-nazwa { font-family: var( --lst-serif ); font-weight: 400; font-size: clamp( 1.9rem, 4.4vw, 3.2rem ); line-height: 1.06; color: var( --lst-tekst ); }

.lst-cen-odznaka { font-family: var( --lst-mono ); font-size: .68rem; letter-spacing: .12em; text-transform: uppercase; color: rgb( var( --lst-mieta ) ); border: 1px solid rgba( var( --lst-mieta ), .45 ) !important; border-radius: 999px; padding: .28rem .6rem; white-space: nowrap; }

.lst-cen-cena { display: flex; align-items: baseline; flex-wrap: wrap; gap: .5rem; margin: .5rem 0 .9rem; }
.lst-cen-kwota { font-family: var( --lst-serif ); font-weight: 400; font-size: clamp( 2.6rem, 4.6vw, 3.4rem ); line-height: 1; color: var( --lst-tekst ); }
.lst-cen-dopisek { font-family: var( --lst-mono ); font-size: .84rem; color: var( --lst-tekst-3 ); }

/* Wymuszenie, bo motywy lubią nadawać listom kropki i wcięcie regułą
   z !important — a wtedy obok każdego ptaszka pojawia się jeszcze kropka. */
.lst-cen .lst-cen-lista { display: flex; flex-direction: column; gap: .62rem; margin: 0 0 1.6rem !important; padding: 0 !important; list-style: none !important; }
.lst-cen .lst-cen-lista li { display: flex; align-items: flex-start; gap: .6rem; margin: 0 !important; padding: 0 !important; list-style: none !important; font-size: .96rem; line-height: 1.45; color: var( --lst-tekst-2 ); }
.lst-cen-polecana .lst-cen-lista li { color: var( --lst-tekst ); }

/* Ptaszek w ramce o narzuconym rozmiarze — motywy potrafią mieć własną regułę
   dla każdego <svg> na stronie, mocniejszą od zwykłej klasy. */
.lst-cen .lst-cen-znak { flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; margin-top: .12rem; overflow: hidden; color: rgb( var( --lst-mieta ) ); }
.lst-cen .lst-cen-znak > svg { display: block; width: 100% !important; height: 100% !important; max-width: none !important; min-width: 0 !important; }

/* ---------- przycisk ---------- */
/* margin-top: auto dosuwa przycisk do dołu, więc we wszystkich kartach
   stoi na tej samej wysokości, choć list punktów jest różnej długości. */
.lst-cen .lst-cen-przycisk {
	margin-top: auto;
	display: block;
	text-align: center;
	padding: .95rem 1.2rem;
	font-size: 1.125rem;
	font-weight: 600;
	border-radius: 10px;
	border: 1px solid var( --lst-linia ) !important;
	color: var( --lst-tekst );
	background: rgba( 255, 255, 255, .02 );
	text-decoration: none !important;
	cursor: pointer;
	transition: background-color .2s ease, border-color .2s ease, color .2s ease;
}

.lst-cen .lst-cen-przycisk:hover { border-color: rgba( var( --lst-mieta ), .5 ) !important; background: rgba( var( --lst-mieta ), .08 ); }
.lst-cen .lst-cen-przycisk:focus-visible { outline: 2px solid rgb( var( --lst-mieta ) ) !important; outline-offset: 3px; }

/*
 * PRZYCISK PRO — platyna.
 *
 * Dwie warstwy tła, nie jeden kolor:
 *  — spód to pionowe przejście od jasnej mięty do ciemniejszej. Samo to już
 *    daje wrażenie wypukłości, bo tak zachowuje się światło na metalu.
 *  — wierzch to wąskie, jasne pasmo, które przy najechaniu przejeżdża w bok.
 *
 * Pasmo jest WARSTWĄ TŁA, a nie osobnym elementem — dzięki temu z automatu
 * leży pod napisem i nie trzeba nic podnosić ani przesuwać.
 */
.lst-cen .lst-cen-przycisk-mocny {
	/* Napis wyraźnie większy niż w pozostałych kartach (20 px wobec 18 px) —
	   to ten plan ma być tym wybieranym, a wielkość napisu na przycisku
	   czyta się szybciej niż cokolwiek innego. */
	font-size: 1.25rem;

	color: #0d1b19;
	border-color: rgba( var( --lst-neon-1 ), .9 ) !important;
	background-repeat: no-repeat;
	background-size: 250% 100%, 100% 100%;
	background-position: 170% 0, 0 0;
	background-image:
		linear-gradient( 105deg, rgba( 255, 255, 255, 0 ) 38%, rgba( 255, 255, 255, .6 ) 50%, rgba( 255, 255, 255, 0 ) 62% ),
		linear-gradient( 180deg, rgb( var( --lst-neon-2 ) ) 0%, rgb( var( --lst-neon-1 ) ) 42%, rgba( var( --lst-neon-3 ), .95 ) 100% );
	box-shadow: inset 0 1px 0 rgba( 255, 255, 255, .55 ), 0 6px 18px -10px rgba( var( --lst-neon-1 ), .8 );
	transition: filter .25s ease, box-shadow .25s ease;

	/* Przebłysk przejeżdża sam z siebie, mniej więcej co pięć sekund —
	   przycisk „żyje" także wtedy, gdy nikt na niego nie patrzy. */
	animation: lst-cen-platyna 5s ease-in-out infinite;
}

/*
 * Pasmo przejeżdża w niecałe półtorej sekundy, a potem czeka — stąd przerwa
 * w klatkach od 45% do końca. Bez tej przerwy połysk lata bez ustanku
 * i zamiast drogiego wygląda na nerwowy.
 */
@keyframes lst-cen-platyna {
	0%   { background-position: 170% 0, 0 0; }
	45%  { background-position: -70% 0, 0 0; }
	100% { background-position: -70% 0, 0 0; }
}

/* ---------------------------------------------------------------------------
   AGENCY — inny wzór i chłodniejsze barwy.

   Zamiast promieni wychodzących ze środka: dwie szerokie wstęgi światła
   przesuwające się po skosie, w odcieniach przechodzących w błękit. Dzięki
   temu trzecia karta odróżnia się od pozostałych na pierwszy rzut oka,
   a nadal siedzi w tej samej palecie.
   --------------------------------------------------------------------------- */
.lst-cen .lst-cen-agencja .lst-cen-panel::before {
	--lst-chlod-1: 132, 214, 228;
	--lst-chlod-2: 64, 158, 186;

	background: linear-gradient( 112deg,
		rgba( var( --lst-chlod-2 ), 0 ) 8%,
		rgba( var( --lst-chlod-2 ), .75 ) 28%,
		rgba( var( --lst-chlod-1 ), 1 ) 42%,
		rgba( var( --lst-chlod-2 ), .6 ) 54%,
		rgba( var( --lst-chlod-1 ), .9 ) 66%,
		rgba( var( --lst-chlod-2 ), 0 ) 88% );
	background-size: 230% 100%;
	filter: blur( 34px );
	animation: lst-cen-wstega 19s ease-in-out infinite alternate;
}

@keyframes lst-cen-wstega {
	from { background-position: 0% 50%; }
	to   { background-position: 100% 50%; }
}

/* ---------------------------------------------------------------------------
   NAJECHANIE MYSZĄ. Wszystko, co dzieje się na karcie pod kursorem.
   --------------------------------------------------------------------------- */
@media ( hover: hover ) and ( pointer: fine ) {
	.lst-cen .lst-cen-karta:hover { transform: translateY( -3px ); border-color: rgba( var( --lst-mieta ), .34 ) !important; box-shadow: 0 22px 50px -34px rgba( var( --lst-mieta ), .45 ); }

	/* Połysk wchodzi miękko. */
	.lst-cen .lst-cen-karta:hover .lst-cen-panel::before { opacity: .26; }

	/* Pro świeci mocniej — to ona ma być tą droższą. */
	.lst-cen .lst-cen-polecana:hover .lst-cen-panel::before { opacity: .34; }

	/*
	 * Gradient obraca się tylko wtedy, gdy jest widoczny. Karta Pro kręci
	 * pierwszy kąt bez przerwy, bo napędza nim też swój neon — i celowo NIE
	 * restartujemy jej animacji przy najechaniu, bo neon by wtedy podskoczył.
	 */
	/* Agency nie obraca gradientu — jej wstęgi mają własny ruch. */
	.lst-cen .lst-cen-karta:not( .lst-cen-polecana ):not( .lst-cen-agencja ):hover {
		animation:
			lst-cen-obrot 22s linear infinite,
			lst-cen-dryf-x 47s ease-in-out infinite alternate,
			lst-cen-dryf-y 61s ease-in-out infinite alternate;
	}

	/*
	 * Przycisk reaguje WYŁĄCZNIE na siebie.
	 *
	 * Wcześniej zapalał się razem z całą kartą, przez co zmieniał kolor, gdy
	 * kursor był zupełnie gdzie indziej — wyglądało to, jakby robił to bez
	 * powodu. Przycisk ma mówić „kliknij MNIE", a nie „coś się dzieje obok".
	 */
	.lst-cen .lst-cen-przycisk:hover { border-color: rgb( var( --lst-mieta ) ) !important; background: rgba( var( --lst-mieta ), .2 ); color: rgb( var( --lst-mieta ) ); }

	/*
	 * Mysz na przycisku Pro: przyciemniamy tło, dajemy biały napis i puszczamy
	 * platynowe pasmo. Biel na jasnej mięcie byłaby nieczytelna — dopiero
	 * przyciemnienie daje jej kontrast.
	 */
	.lst-cen .lst-cen-przycisk-mocny:hover {
		animation: lst-cen-platyna 2.6s ease-in-out infinite;
		background-color: transparent;
		color: #ffffff;
		filter: none;
		border-color: rgba( var( --lst-neon-3 ), 1 ) !important;
		background-image:
			linear-gradient( 105deg, rgba( 255, 255, 255, 0 ) 38%, rgba( 255, 255, 255, .34 ) 50%, rgba( 255, 255, 255, 0 ) 62% ),
			linear-gradient( 180deg, rgba( var( --lst-neon-3 ), 1 ) 0%, rgb( 16, 104, 112 ) 55%, rgb( 12, 82, 90 ) 100% );
		box-shadow: inset 0 1px 0 rgba( 255, 255, 255, .28 ), 0 10px 26px -12px rgba( var( --lst-neon-1 ), .8 );
	}
}

/* ---------- uwaga pod cennikiem ---------- */
.lst-cen-uwaga { width: var( --lst-szerokosc ); max-width: var( --lst-max ); margin-inline: auto; margin-top: clamp( 1rem, 2vw, 1.6rem ); font-family: var( --lst-mono ); font-size: .78rem; line-height: 1.6; color: var( --lst-tekst-3 ); border: 1px solid var( --lst-linia ) !important; border-radius: 10px; padding: .9rem 1.1rem; }

@media ( prefers-reduced-motion: reduce ) {
	.lst-cen .lst-cen-karta, .lst-cen .lst-cen-przycisk { transition: none; }
	.lst-cen .lst-cen-karta:hover { transform: none; }
	.lst-cen .lst-cen-karta:not( .lst-cen-polecana ):hover,
	.lst-cen .lst-cen-panel::before,
	.lst-cen .lst-cen-agencja .lst-cen-panel::before,
	.lst-cen .lst-cen-przycisk-mocny,
	.lst-cen .lst-cen-przycisk-mocny:hover { animation: none; }
	.lst-cen .lst-cen-polecana,
	.lst-cen .lst-cen-polecana::before,
	.lst-cen .lst-cen-polecana::after { animation: none; filter: blur( 14px ); }
}
</style>

<script>
( function () {
	'use strict';

	function start() {
		var sekcja = document.getElementById( 'lst-cen' );

		if ( ! sekcja ) {
			return;
		}

		/* Szerokość okna BEZ paska przewijania — samo 100vw pasek dolicza
		   i strona dostaje poziomy suwak, którego nikt nie chciał. */
		function pelna() {
			sekcja.style.setProperty( '--lst-pelna', document.documentElement.clientWidth + 'px' );
		}

		pelna();
		window.addEventListener( 'resize', pelna );

		/* Zdejmujemy obramowanie z własnych opakowań — patrz uwaga przy CSS. */
		var rodzic = sekcja.parentNode;

		while ( rodzic && rodzic !== document.body && rodzic.classList ) {
			rodzic.classList.add( 'lst-cen-gniazdo' );

			if ( rodzic.classList.contains( 'et_pb_section' ) ) {
				break;
			}

			rodzic = rodzic.parentNode;
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
</script>
'''

KARTA = (
 '\t\t<div class="lst-cen-karta{POLECANA}{ODMIANA}">'
 '<div class="lst-cen-panel">'
 '<p class="lst-cen-nad">{NAD}</p>'
 '<div class="lst-cen-rzad"><h3 class="lst-cen-nazwa">{NAZWA}</h3>{ODZNAKA}</div>'
 '<p class="lst-cen-cena"><span class="lst-cen-kwota">{KWOTA}</span><span class="lst-cen-dopisek">{DOPISEK}</span></p>'
 '<ul class="lst-cen-lista">{PUNKTY}</ul>'
 '<a class="lst-cen-przycisk{MOCNY}" href="{ADRES}">{PRZYCISK}</a>'
 '</div>'
 '</div>'
)


def podstaw( wzor, pola ):
	for k, v in pola.items():
		wzor = wzor.replace( '{' + k + '}', v )
	return wzor


def sprawdz_stel( html, plik ):
	"""Liczy nawiasy klamrowe w <style>. Dwa razy z rzędu zostawiłem po cięciu
	nadmiarowy nawias i przez to reszta stylu przestawała działać — ta kontrola
	wyłapuje to, zanim cokolwiek trafi do przeglądarki."""
	import re as _re
	styl = _re.search( r'<style>(.*?)</style>', html, _re.S ).group( 1 )

	# Samo liczenie nawiasów nie wystarcza: przy jednym z cięć wyparował cały
	# blok najechania, a nawiasy dalej się zgadzały. Dlatego pilnujemy też,
	# żeby te reguły w ogóle istniały.
	for musi in ( '@property --lst-neon-kat', '@media ( hover: hover )', ':hover .lst-cen-panel::before',
	              'lst-cen-platyna', '.lst-cen-panel::before', '.lst-cen-karta:hover' ):
		if musi not in styl:
			raise SystemExit( plik + ': w stylu brakuje "' + musi + '" — coś wypadło przy zmianie' )

	bez_uwag = _re.sub( r'/\*.*?\*/', '', styl, flags=_re.S )
	otwarte, zamkniete = bez_uwag.count( '{' ), bez_uwag.count( '}' )

	if otwarte != zamkniete:
		raise SystemExit( plik + ': nawiasy w <style> się nie zgadzają — otwartych ' + str( otwarte ) + ', zamkniętych ' + str( zamkniete ) )


def zbuduj( t, plik ):
	karty = []

	for plan in t['plany']:
		punkty = ''.join( '<li>' + PTASZEK + '<span>' + p + '</span></li>' for p in plan['punkty'] )
		karty.append( podstaw( KARTA, {
			'POLECANA': ' lst-cen-polecana' if plan['polecany'] else '',
			'ODMIANA':  plan.get( 'odmiana', '' ),
			'MOCNY':    ' lst-cen-przycisk-mocny' if plan['polecany'] else '',
			'ODZNAKA':  '<span class="lst-cen-odznaka">' + t['odznaka'] + '</span>' if plan['polecany'] else '',
			'NAD': plan['nad'], 'NAZWA': plan['nazwa'], 'KWOTA': plan['kwota'], 'DOPISEK': plan['dopisek'],
			'PUNKTY': punkty, 'PRZYCISK': plan['przycisk'], 'ADRES': plan['adres'],
		} ) )

	html = podstaw( SZABLON, { 'KARTY': '\n'.join( karty ), 'UWAGA': t['uwaga'] } )

	with open( plik, 'w', encoding='utf-8' ) as f:
		f.write( html )

	sprawdz_stel( html, plik )
	print( plik + ' — planów: ' + str( len( karty ) ) )


zbuduj( EN, 'cennik-en.html' )
zbuduj( PL, 'cennik-pl.html' )


# ---------------------------------------------------------------------------
# Strony próbne. Budujemy je TUTAJ, z tego samego świeżo wygenerowanego kodu,
# żeby nigdy nie mogły się rozjechać z plikami do wklejenia w Divi. Wcześniej
# powstawały osobno i przez to testy potrafiły sprawdzać wczorajszą wersję.
# ---------------------------------------------------------------------------

GLOWA = '''<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Pricing</title>
<style>
 html,body{margin:0;background:#0f1514;color:#eaf3f1;font-family:"IBM Plex Sans",system-ui,sans-serif}
 body{background-image:linear-gradient(rgba(138,168,163,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(138,168,163,.045) 1px,transparent 1px);background-size:96px 96px}
 .et_pb_section{padding:40px 0}
 .et_pb_row{width:90%;max-width:1800px;margin:0 auto}
 .wysoko{height:50vh}
</style></head><body>
<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><p id="odnosnik">A paragraph in the ordinary Divi column.</p></div></div></div>
<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><div class="et_pb_module" id="gniazdo">
'''

STOPKA = '''
</div></div></div></div>
<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><p id="odnosnik2">After.</p><div class="wysoko"></div></div></div></div>
</body></html>'''


def divi_bry( html ):
	import re as _re

	"""Divi wstawia <br /> na każdym złamaniu linii POZA <style> i <script>."""
	kawalki = _re.split( r'(<style>.*?</style>|<script>.*?</script>)', html, flags=_re.S )
	wynik = []

	for i, kawalek in enumerate( kawalki ):
		wynik.append( kawalek if i % 2 else kawalek.replace( '\n', '<br />\n' ) )

	return ''.join( wynik )


def zbuduj_probe():
	with open( 'cennik-en.html', encoding='utf-8' ) as f:
		modul = f.read()

	for plik, tresc in ( ( 'proba.html', modul ), ( 'proba-br.html', divi_bry( modul ) ) ):
		with open( plik, 'w', encoding='utf-8' ) as f:
			f.write( GLOWA + tresc + STOPKA )

		print( plik + ' — strona próbna odświeżona' )


zbuduj_probe()
