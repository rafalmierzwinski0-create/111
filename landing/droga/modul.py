# -*- coding: utf-8 -*-
"""
„Którędy idą dane" — cztery przystanki, jeden moduł Kod.

Pas pod sekcją o dwóch minutach: arkusz, pobranie w tle, kopia w bazie, strona
odwiedzającego. Mówi rzecz, której nie widać na żadnym zrzucie — że między
Google a czytelnikiem stoi KOPIA, i dlatego strona nie czeka na Google.

Bez skryptu. Sam kod strony i style.

UWAGA przy edycji: każdy znacznik musi zostać w jednej linijce. Divi wstawia
w miejscu złamanego wiersza <br />, co rozbija znacznik.
"""

import pathlib
import re

TU = pathlib.Path( __file__ ).parent


# Cztery ikony, jedną kreską, w jednym pudełku 24 na 24. Rysowane, a nie brane
# z kroju pisma: ikona z czcionki wygląda w każdej przeglądarce inaczej,
# a w połowie z nich stoi za nisko.
IKONY = {
	'arkusz': ( '<rect x="3" y="3" width="18" height="18" rx="3"></rect>'
		'<path d="M3 9h18M9 9v12"></path>' ),
	'pobranie': ( '<path d="M20 12a8 8 0 1 1-2.4-5.7"></path>'
		'<path d="M20 4v4h-4"></path>' ),
	'baza': ( '<ellipse cx="12" cy="6" rx="8" ry="3"></ellipse>'
		'<path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6"></path>'
		'<path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"></path>' ),
	'strona': ( '<rect x="2.5" y="4" width="19" height="13" rx="2.5"></rect>'
		'<path d="M9 21h6M12 17v4"></path>' ),
}

EN = {
	'kroki': [
		( 'arkusz', 'Your Google Sheet',
		  'You change what you want,', 'where you already work.' ),
		( 'pobranie', 'Fetched in the background',
		  'The plugin looks for changes', 'every fifteen minutes.' ),
		( 'baza', 'A copy in your database',
		  'The table lives with you,', 'not at Google.' ),
		( 'strona', 'Your visitor&rsquo;s page',
		  'Current data straight away,', 'with nothing to wait for.' ),
	],
}

PL = {
	'kroki': [
		( 'arkusz', 'Arkusz Google',
		  'Zmieniasz, co chcesz,', 'tam gdzie pracujesz.' ),
		( 'pobranie', 'Pobranie w tle',
		  'Wtyczka sama sprawdza', 'zmiany, co 15 minut.' ),
		( 'baza', 'Kopia w Twojej bazie',
		  'Tabela żyje u Ciebie,', 'nie u Google.' ),
		( 'strona', 'Strona odwiedzającego',
		  'Aktualne dane od razu,', 'bez sekundy czekania.' ),
	],
}


SZABLON = r'''<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">

<div class="lst-dr">
	<div class="lst-dr-rama">
		<ol class="lst-dr-droga">
{KROKI}
		</ol>
	</div>
</div>

<style>
.lst-dr {
	--lst-mieta: 95, 227, 207;
	--lst-tekst: #eaf3f1;
	--lst-tekst-2: #9db3b0;
	--lst-kafel: #131b1a;
	--lst-mono: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;

	font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif;
	color: var( --lst-tekst );

	/* Pełna szerokość okna, jak reszta sekcji tej witryny: inaczej 90%
	   liczyłoby się od wiersza Divi, który sam ma już 90%. */
	width: 100vw;
	margin: 0 calc( 50% - 50vw );
	overflow-x: clip;
	padding: clamp( 1.6rem, 3vw, 2.8rem ) 0;
}

.lst-dr.lst-dr { border: 0 !important; outline: 0 !important; background: none !important; }
.lst-dr * { box-sizing: border-box; }
.lst-dr br { display: none; }

/* Reset musi stać PRZED resztą reguł — ma wagę zero. */
.lst-dr :where( div, p, span, ol, li ) {
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

.lst-dr .lst-dr-rama {
	width: 90%;
	max-width: 1800px;
	margin-inline: auto;
}

/* ---------- cztery przystanki ---------- */

.lst-dr .lst-dr-droga {
	display: grid;
	grid-template-columns: repeat( 4, minmax( 0, 1fr ) );
	gap: clamp( 1rem, 2vw, 2rem );
}

.lst-dr .lst-dr-krok {
	position: relative;
	display: grid;
	justify-items: center;
	text-align: center !important;
}

/*
 * Kreska od przystanku do przystanku, z biegnącym po niej rozjaśnieniem.
 *
 * Rozjaśnienie jest tu treścią, a nie ozdobą: cała ta sekcja mówi o tym, że
 * dane JADĄ, i to jedyne miejsce, w którym widać kierunek. Zrobione tłem,
 * a nie kropką, bo kropka musiałaby znać długość kreski, a ta zmienia się
 * z szerokością okna; tło o szerokości 40 procent przesuwane od minus 40 do
 * 140 procent przejeżdża każdą kreskę w całości, jakakolwiek by była.
 *
 * Każda kreska startuje później od poprzedniej, więc rozjaśnienia idą jedno
 * za drugim, od arkusza do strony, a nie wszystkie naraz.
 */
.lst-dr .lst-dr-krok:not( :last-child )::before {
	content: "";
	position: absolute;
	top: 1.55rem;
	left: calc( 50% + 2.4rem );
	right: calc( -50% + 2.4rem );
	height: 2px;
	border-radius: 2px;
	pointer-events: none;
	background-image:
		linear-gradient( to right, transparent, rgba( var( --lst-mieta ), .95 ) 50%, transparent ),
		linear-gradient( rgba( var( --lst-mieta ), .3 ), rgba( var( --lst-mieta ), .3 ) ) !important;
	background-size: 40% 100%, 100% 100%;
	background-repeat: no-repeat;
	background-position: -40% 0, 0 0;
	animation: lst-dr-plyn 3.4s linear infinite;
}

.lst-dr .lst-dr-krok:nth-child( 2 )::before { animation-delay: .4s; }
.lst-dr .lst-dr-krok:nth-child( 3 )::before { animation-delay: .8s; }

@keyframes lst-dr-plyn {
	from { background-position: -40% 0, 0 0; }
	to { background-position: 140% 0, 0 0; }
}

/* Grot na końcu każdej kreski: kreska mówi „połączone", grot mówi „w tę
   stronę", a tu chodzi wyłącznie o tę drugą rzecz. */
.lst-dr .lst-dr-krok:not( :last-child )::after {
	content: "";
	position: absolute;
	top: 1.2rem;
	right: calc( -50% + 2.4rem );
	width: 0;
	height: 0;
	pointer-events: none;
	border-top: 4px solid transparent;
	border-bottom: 4px solid transparent;
	border-left: 6px solid rgba( var( --lst-mieta ), .75 );
}

/* ---------- kafel z ikoną ---------- */

.lst-dr .lst-dr-kafel {
	display: grid;
	place-items: center;
	width: 3.1rem;
	height: 3.1rem;
	border-radius: 14px;
	color: rgb( var( --lst-mieta ) );
	background-color: var( --lst-kafel ) !important;
	border: 1px solid rgba( var( --lst-mieta ), .28 ) !important;
	box-shadow:
		inset 0 1px 0 rgba( 255, 255, 255, .05 ),
		0 0 22px -8px rgba( var( --lst-mieta ), .45 ),
		0 12px 26px -18px rgba( 0, 0, 0, .9 );
}

.lst-dr .lst-dr-ikona {
	display: block;
	width: 1.5rem;
	height: 1.5rem;
	fill: none;
	stroke: currentColor;
	stroke-width: 1.6;
	stroke-linecap: round;
	stroke-linejoin: round;
}

.lst-dr .lst-dr-tytul {
	margin-top: 1.1rem;
	font-size: 1.125rem;   /* 18 px */
	font-weight: 600;
	line-height: 1.3;
}

.lst-dr .lst-dr-opis {
	margin-top: .5rem;
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	line-height: 1.7;
	color: var( --lst-tekst-2 );
}

/* ---------- utwardzenie na wrogie motywy ---------- */

.lst-dr .lst-dr-tytul,
.lst-dr .lst-dr-opis,
.lst-dr .lst-dr-krok {
	margin-inline: 0 !important;
	background: none !important;
	border: 0 !important;
	text-align: center !important;
	text-transform: none !important;
	letter-spacing: normal !important;
}

.lst-dr .lst-dr-tytul { font-family: inherit !important; color: var( --lst-tekst ) !important; }
.lst-dr .lst-dr-opis { font-family: var( --lst-mono ) !important; color: var( --lst-tekst-2 ) !important; }
.lst-dr .lst-dr-droga { padding: 0 !important; list-style: none !important; }

/* ---------- wąsko ---------- */

/*
 * Wąsko przystanki stają jeden pod drugim, a kreska kładzie się w pionie
 * i dalej biegnie w dół: droga jest ta sama, tylko obrócona.
 */
@media ( max-width: 860px ) {
	.lst-dr .lst-dr-droga { grid-template-columns: 1fr; gap: clamp( 2.2rem, 7vw, 3rem ); }

	.lst-dr .lst-dr-krok:not( :last-child )::before {
		top: auto;
		bottom: calc( -1 * clamp( 2.2rem, 7vw, 3rem ) );
		left: 50%;
		right: auto;
		width: 2px;
		height: clamp( 2.2rem, 7vw, 3rem );
		transform: translateX( -1px );
		background-image:
			linear-gradient( to bottom, transparent, rgba( var( --lst-mieta ), .95 ) 50%, transparent ),
			linear-gradient( rgba( var( --lst-mieta ), .3 ), rgba( var( --lst-mieta ), .3 ) ) !important;
		background-size: 100% 40%, 100% 100%;
		background-position: 0 -40%, 0 0;
		animation-name: lst-dr-plyn-pion;
	}

	.lst-dr .lst-dr-krok:not( :last-child )::after {
		top: auto;
		bottom: calc( -1 * clamp( 2.2rem, 7vw, 3rem ) );
		right: auto;
		left: 50%;
		transform: translateX( -4px );
		border-top: 6px solid rgba( var( --lst-mieta ), .75 );
		border-bottom: 0;
		border-left: 4px solid transparent;
		border-right: 4px solid transparent;
	}

	@keyframes lst-dr-plyn-pion {
		from { background-position: 0 -40%, 0 0; }
		to { background-position: 0 140%, 0 0; }
	}
}

@media ( prefers-reduced-motion: reduce ) {
	/* „Mniej ruchu" znaczy mniej ruchu, nie mniej treści: kreska zostaje
	   narysowana, razem z rozjaśnieniem w miejscu, w którym akurat jest. */
	.lst-dr .lst-dr-krok::before { animation: none !important; }
}

@media print {
	.lst-dr .lst-dr-krok::before { animation: none !important; }
}
</style>
'''


def zbuduj( t, plik ):
	kroki = []

	for ikona, tytul, linia1, linia2 in t[ 'kroki' ]:
		kroki.append(
			'\t\t\t<li class="lst-dr-krok">'
			'<span class="lst-dr-kafel">'
			'<svg class="lst-dr-ikona" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
			+ IKONY[ ikona ] +
			'</svg></span>'
			'<p class="lst-dr-tytul">' + tytul + '</p>'
			'<p class="lst-dr-opis">' + linia1 + ' ' + linia2 + '</p></li>' )

	html = SZABLON.replace( '{KROKI}', '\n'.join( kroki ) )

	sprawdz( html, plik )
	( TU / plik ).write_text( html, encoding='utf-8' )
	print( plik + ' — przystanków: ' + str( len( kroki ) ) + ', znaków: ' + str( len( html ) ) )
	return html


def sprawdz( html, plik ):
	"""To, co potrafi wyłożyć Kreator Wizualny albo psuje moduł po cichu."""

	znacznikowanie = html[ : html.index( '<style>' ) ]

	for numer, wiersz in enumerate( znacznikowanie.split( '\n' ), 1 ):
		if wiersz.count( '<' ) and re.search( r'<[^>]*$', wiersz ):
			raise SystemExit( plik + ': znacznik rozbity na dwie linijki, wiersz ' + str( numer ) )

	# Nawiasy kwadratowe w treści WordPress wziąłby za shortcode.
	if '[' in znacznikowanie:
		raise SystemExit( plik + ': nawias kwadratowy w treści' )


PODGLAD = (
	'<!doctype html>\n<html lang="en">\n<meta charset="utf-8">\n'
	'<title>Which way the data goes</title>\n'
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
	'{TRESC}'
	'\n</div></div>\n'
)


en = zbuduj( EN, 'DROGA-en.html' )
zbuduj( PL, 'DROGA-pl.html' )

( TU / 'proba.html' ).write_text( PODGLAD.replace( '{TRESC}', en ), encoding='utf-8' )
print( 'proba.html' )
