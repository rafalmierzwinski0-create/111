# -*- coding: utf-8 -*-
"""
Trzy propozycje na miejsce trzech par „krok i okienko” w sekcji
„From sheet to page in two minutes”. Pasek z liczbami pod nimi zostaje bez
zmian, więc tu go nie ma.

Każda odpowiada — choćby po części — na cztery pytania, które zadaje sobie
nowy odwiedzający: co to robi, komu pomaga, dlaczego mu ufać i co zrobić
dalej. Wszystko, co tu stoi, jest prawdą o wtyczce (readme.txt): żadnych
wymyślonych liczb klientów ani opinii.

  A — siatka kafli („bento”): każde pytanie dostaje swój kafel.
  B — oś kroków z jednym dużym ekranem obok, a przy każdym kroku
      „dlaczego to bezpieczne”.
  C — cztery pytania wprost, jako cztery karty ze świecącą krawędzią.

To są makiety do wyboru, a nie moduł do Divi: wybrana propozycja trafi
potem do modul.py, z tym samym utwardzeniem co reszta.

Uruchomienie: python3 landing/dwie-minuty/propozycje/zrob.py
"""

import pathlib

TU = pathlib.Path( __file__ ).resolve().parent

L, P = '&#91;', '&#93;'
NAWIAS = '<span class="p-nawias">' + L + '</span>'

CZCIONKI = ( '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?'
	'family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600'
	'&family=Inria+Serif:wght@300;400&display=swap">' )

WSPOLNE = r'''
html, body { margin: 0; background: #232a29; color: #eaf3f1; }
body {
	background-image: linear-gradient( to right, rgba( 255, 255, 255, .045 ) 1px, transparent 1px ),
		linear-gradient( to bottom, rgba( 255, 255, 255, .045 ) 1px, transparent 1px );
	background-size: 88px 44px;
}
.p {
	--mieta: 95, 227, 207;
	--tekst: #eaf3f1;
	--tekst-2: #9db3b0;
	--tekst-3: #8fa5a2;
	--plyta: rgba( 13, 18, 17, .62 );
	--plyta-linia: rgba( 138, 168, 163, .12 );
	--ekran: #0a1110;
	--ekran-gora: #131d1b;
	--ekran-linia: rgba( 95, 227, 207, .2 );
	--mono: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;
	--serif: "Inria Serif", "Iowan Old Style", Georgia, serif;
	font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif;
	color: var( --tekst );
	padding: 56px 0 72px;
}
.p * { box-sizing: border-box; }
.p :where( p, h2, h3, ul, li ) { margin: 0; padding: 0; list-style: none; }
.p-rama { width: 90%; max-width: 1800px; margin: 0 auto; }
.p-oko { font-family: var( --mono ); font-size: .875rem; letter-spacing: .06em; color: rgb( var( --mieta ) ); }
.p-tytul { font-family: var( --serif ); font-weight: 400; font-size: clamp( 2.2rem, 4.2vw, 3.6rem ); line-height: 1.08; margin: .35rem 0 0; letter-spacing: -.01em; }
.p-wstep { font-size: 1.125rem; line-height: 1.55; color: var( --tekst-2 ); max-width: 46rem; margin-top: 1rem; }
.p-mono { font-family: var( --mono ); }
.p-mieta { color: rgb( var( --mieta ) ); }
.p-cta {
	display: inline-flex; align-items: center; gap: .5rem;
	padding: .7rem 1.35rem; border-radius: 999px;
	background: rgb( var( --mieta ) ); color: #06100f;
	font-weight: 600; font-size: 1.0625rem; text-decoration: none; white-space: nowrap;
	box-shadow: 0 10px 30px -12px rgba( var( --mieta ), .7 );
}
.p-cta-2 {
	display: inline-flex; align-items: center; gap: .5rem;
	padding: .7rem 1.2rem; border-radius: 999px;
	border: 1px solid rgba( var( --mieta ), .35 ); color: var( --tekst );
	font-weight: 500; font-size: 1rem; text-decoration: none; white-space: nowrap;
}
.p-zeton {
	display: inline-flex; align-items: center; gap: .45rem;
	padding: .38rem .75rem; border-radius: 999px;
	background: rgba( 255, 255, 255, .035 ); border: 1px solid var( --plyta-linia );
	font-size: .9375rem; color: var( --tekst );
}
.p-zeton i { width: 6px; height: 6px; border-radius: 99px; background: rgb( var( --mieta ) ); opacity: .8; }

/* mini arkusz i mini strona, wspólne dla propozycji */
.p-arkusz { font-family: var( --mono ); font-size: .8125rem; border: 1px solid rgba( 138, 168, 163, .22 ); border-radius: 10px; overflow: hidden; background: #0d1413; }
.p-arkusz-gora { display: flex; gap: .4rem; align-items: center; padding: .45rem .7rem; background: #131d1b; color: var( --tekst-3 ); border-bottom: 1px solid rgba( 138, 168, 163, .16 ); }
.p-arkusz-gora b { font-weight: 500; color: var( --tekst-2 ); }
.p-arkusz table { border-collapse: collapse; width: 100%; }
.p-arkusz td, .p-arkusz th { border: 1px solid rgba( 138, 168, 163, .12 ); padding: .38rem .6rem; text-align: left; color: var( --tekst-2 ); font-weight: 400; white-space: nowrap; }
.p-arkusz th { color: var( --tekst-3 ); background: rgba( 255, 255, 255, .02 ); width: 1%; }
.p-arkusz .jest-edycja { outline: 2px solid rgb( var( --mieta ) ); outline-offset: -2px; color: var( --tekst ); background: rgba( var( --mieta ), .08 ); }

.p-strona { border: 1px solid var( --ekran-linia ); border-radius: 12px; overflow: hidden; background: var( --ekran ); box-shadow: 0 30px 60px -36px rgba( 0, 0, 0, .95 ), 0 0 40px -18px rgba( var( --mieta ), .35 ); }
.p-strona-gora { display: flex; align-items: center; gap: .4rem; padding: .55rem .8rem; background: var( --ekran-gora ); border-bottom: 1px solid rgba( 95, 227, 207, .14 ); }
.p-strona-gora i { width: 9px; height: 9px; border-radius: 99px; background: var( --tekst-3 ); opacity: .38; }
.p-strona-gora span { margin-left: .5rem; font-family: var( --mono ); font-size: .8125rem; color: var( --tekst-3 ); background: rgba( 255, 255, 255, .04 ); border-radius: 6px; padding: .15rem .6rem; flex: 1; }
.p-tabela { width: 100%; border-collapse: collapse; font-size: .9375rem; }
.p-tabela th { text-align: left; font-family: var( --mono ); font-size: .75rem; letter-spacing: .08em; text-transform: uppercase; color: var( --tekst-3 ); font-weight: 400; padding: .7rem .9rem; border-bottom: 1px solid rgba( 95, 227, 207, .18 ); }
.p-tabela td { padding: .62rem .9rem; border-bottom: 1px solid rgba( 138, 168, 163, .08 ); color: var( --tekst ); }
.p-tabela td.l { text-align: right; font-variant-numeric: tabular-nums; }
.p-tabela tr.jest-nowy td { background: rgba( var( --mieta ), .08 ); }
.p-tabela tr.jest-nowy td.l { color: rgb( var( --mieta ) ); font-weight: 600; }
.p-stopka { display: flex; justify-content: space-between; padding: .6rem .9rem; font-family: var( --mono ); font-size: .75rem; color: var( --tekst-3 ); }
.p-kropka { display: inline-block; width: 7px; height: 7px; border-radius: 99px; background: rgb( var( --mieta ) ); box-shadow: 0 0 0 4px rgba( var( --mieta ), .15 ); margin-right: .45rem; vertical-align: 1px; }
'''

ARKUSZ = ( '<div class="p-arkusz"><div class="p-arkusz-gora"><b>Prices</b> &middot; Google Sheets</div>'
	'<table><tr><th></th><th>A</th><th>B</th></tr>'
	'<tr><th>1</th><td>Product</td><td>Price</td></tr>'
	'<tr><th>2</th><td>Oak table</td><td>1 290</td></tr>'
	'<tr><th>3</th><td>Ash chair</td><td class="jest-edycja">349 &rarr; 319</td></tr>'
	'<tr><th>4</th><td>Pine shelf</td><td>215</td></tr>'
	'</table></div>' )

STRONA = ( '<div class="p-strona"><div class="p-strona-gora"><i></i><i></i><i></i><span>yourshop.com/prices</span></div>'
	'<table class="p-tabela"><tr><th>Product</th><th style="text-align:right">Price</th></tr>'
	'<tr><td>Oak table</td><td class="l">1 290</td></tr>'
	'<tr class="jest-nowy"><td>Ash chair</td><td class="l">319</td></tr>'
	'<tr><td>Pine shelf</td><td class="l">215</td></tr></table>'
	'<div class="p-stopka"><span><span class="p-kropka"></span>updated 2 min ago</span><span>3 rows</span></div></div>' )

DLA_KOGO = ( 'Price lists', 'Timetables', 'Stock levels', 'Menus', 'Event line-ups', 'Trail conditions', 'Rankings', 'Opening hours' )


def zetony( ile = len( DLA_KOGO ) ):
	return ''.join( '<span class="p-zeton"><i></i>' + z + '</span>' for z in DLA_KOGO[ :ile ] )


def strona( tytul, styl, tresc ):
	return ( '<!doctype html>\n<html lang="en">\n<meta charset="utf-8">\n'
		'<meta name="viewport" content="width=device-width,initial-scale=1">\n'
		'<title>' + tytul + '</title>\n' + CZCIONKI + '\n'
		'<style>' + WSPOLNE + styl + '</style>\n'
		'<div class="p"><div class="p-rama">' + tresc + '</div></div>\n' )


NAGLOWEK = ( '<p class="p-oko">Three steps</p>'
	'<h2 class="p-tytul">From sheet to page in two minutes</h2>' )


# ------------------------------------------------------------- A: bento

STYL_A = r'''
.a-siatka { display: grid; grid-template-columns: repeat( 12, minmax( 0, 1fr ) ); gap: 18px; margin-top: 2.6rem; }
.a-kafel {
	position: relative; border-radius: 20px; padding: 1.6rem 1.7rem 1.7rem;
	background: linear-gradient( 180deg, rgba( 255, 255, 255, .035 ), rgba( 255, 255, 255, 0 ) 40% ), var( --plyta );
	border: 1px solid var( --plyta-linia );
	box-shadow: inset 0 1px 0 rgba( 255, 255, 255, .05 ), 0 30px 60px -45px rgba( 0, 0, 0, .9 );
	overflow: hidden;
}
.a-kafel::before {
	content: ""; position: absolute; inset: -1px; border-radius: inherit; pointer-events: none;
	background: radial-gradient( 420px circle at var( --x, 85% ) var( --y, 0% ), rgba( var( --mieta ), .16 ), transparent 60% );
}
.a-1 { grid-column: span 7; }
.a-2 { grid-column: span 5; }
.a-3 { grid-column: span 5; }
.a-4 { grid-column: span 7; }
.a-5 { grid-column: span 12; display: flex; align-items: center; gap: 2rem; padding: 1.4rem 1.6rem 1.4rem 2rem;
	background: linear-gradient( 100deg, rgba( var( --mieta ), .16 ), rgba( var( --mieta ), .04 ) 45%, rgba( 13, 18, 17, .62 ) 80% );
	border-color: rgba( var( --mieta ), .28 ); }
.a-etykieta { font-family: var( --mono ); font-size: .8125rem; letter-spacing: .1em; text-transform: uppercase; color: var( --tekst-3 ); }
.a-h { font-family: var( --serif ); font-weight: 400; font-size: 1.85rem; line-height: 1.15; margin-top: .55rem; }
.a-p { font-size: 1.0625rem; line-height: 1.55; color: var( --tekst-2 ); margin-top: .6rem; max-width: 36rem; }
.a-przeplyw { display: grid; grid-template-columns: minmax( 0, 1fr ) auto minmax( 0, 1.1fr ); align-items: center; gap: 1.1rem; margin-top: 1.5rem; }
.a-strzalka { display: grid; justify-items: center; gap: .35rem; font-family: var( --mono ); font-size: .75rem; color: var( --tekst-3 ); }
.a-strzalka svg { width: 64px; height: 14px; color: rgb( var( --mieta ) ); }
.a-zetony { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1.3rem; }
.a-kroki { margin-top: 1.2rem; display: grid; gap: .65rem; }
.a-krok { display: grid; grid-template-columns: 2rem 1fr; gap: .8rem; align-items: start; }
.a-nr { width: 2rem; height: 2rem; border-radius: 10px; display: grid; place-items: center; font-family: var( --mono ); font-size: .8125rem; color: rgb( var( --mieta ) ); background: rgba( var( --mieta ), .1 ); border: 1px solid rgba( var( --mieta ), .25 ); }
.a-krok b { display: block; font-weight: 600; font-size: 1.0625rem; }
.a-krok span { color: var( --tekst-2 ); font-size: .9375rem; line-height: 1.45; }
.a-krok code { font-family: var( --mono ); font-size: .875rem; color: rgb( var( --mieta ) ); }
.a-pewne { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem 1.6rem; margin-top: 1.3rem; }
.a-pewne li { display: grid; grid-template-columns: 28px 1fr; gap: .7rem; }
.a-pewne svg { width: 28px; height: 28px; color: rgb( var( --mieta ) ); }
.a-pewne b { display: block; font-weight: 600; font-size: 1.0625rem; line-height: 1.3; }
.a-pewne span { color: var( --tekst-2 ); font-size: .9375rem; line-height: 1.45; }
.a-5 .a-h { margin: 0; font-size: 1.6rem; }
.a-5 .a-p { margin-top: .3rem; }
.a-5 .a-przyciski { margin-left: auto; display: flex; gap: .7rem; }
@media ( max-width: 900px ) {
	.a-1, .a-2, .a-3, .a-4 { grid-column: span 12; }
	.a-5 { flex-direction: column; align-items: flex-start; }
	.a-5 .a-przyciski { margin-left: 0; flex-wrap: wrap; }
	.a-przeplyw { grid-template-columns: 1fr; }
	.a-pewne { grid-template-columns: 1fr; }
}
'''

def ikona( d ):
	return ( '<svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.6" '
		'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>' )

IKONY = {
	'tarcza': ikona( '<path d="M14 3.5 5.5 7v6.5c0 5 3.6 9.3 8.5 11 4.9-1.7 8.5-6 8.5-11V7Z"/><path d="m10 14 3 3 5.5-6"/>' ),
	'serwer': ikona( '<rect x="4.5" y="5" width="19" height="7.5" rx="2"/><rect x="4.5" y="15.5" width="19" height="7.5" rx="2"/><path d="M8.5 8.75h.01M8.5 19.25h.01"/>' ),
	'tabela': ikona( '<rect x="4" y="5" width="20" height="18" rx="2.5"/><path d="M4 10.5h20M11 10.5V23"/>' ),
	'klodka': ikona( '<rect x="6" y="12" width="16" height="11" rx="2.5"/><path d="M9.5 12V9a4.5 4.5 0 0 1 9 0v3"/>' ),
}

STRZALKA = ( '<svg viewBox="0 0 64 14" fill="none" stroke="currentColor" stroke-width="1.6" '
	'stroke-linecap="round" stroke-dasharray="3 4"><path d="M2 7h56"/><path d="m54 2 6 5-6 5" stroke-dasharray="0"/></svg>' )

TRESC_A = ( NAGLOWEK +
	'<div class="a-siatka">'

	# Co robi
	'<div class="a-kafel a-1" style="--x:15%;--y:0%">'
	'<p class="a-etykieta">What it does</p>'
	'<h3 class="a-h">Change a cell in the sheet. The page follows.</h3>'
	'<p class="a-p">Your Google Sheet becomes a real table on your WordPress page &mdash; and keeps itself up to date.</p>'
	'<div class="a-przeplyw">' + ARKUSZ +
	'<div class="a-strzalka">' + STRZALKA + '<span>every 15 min</span></div>' + STRONA + '</div>'
	'</div>'

	# Komu
	'<div class="a-kafel a-2" style="--x:90%;--y:0%">'
	'<p class="a-etykieta">Who it helps</p>'
	'<h3 class="a-h">Anyone whose numbers live in a spreadsheet.</h3>'
	'<p class="a-p">If people keep asking you for &ldquo;the latest version&rdquo;, it belongs on your page &mdash; '
	'edited where you already edit it.</p>'
	'<div class="a-zetony">' + zetony() + '</div>'
	'</div>'

	# Jak
	'<div class="a-kafel a-3" style="--x:10%;--y:100%">'
	'<p class="a-etykieta">How it works</p>'
	'<h3 class="a-h">Three steps, no API key.</h3>'
	'<div class="a-kroki">'
	'<div class="a-krok"><span class="a-nr">1</span><div><b>Share the sheet</b><span>Anyone with the link, as a Viewer.</span></div></div>'
	'<div class="a-krok"><span class="a-nr">2</span><div><b>Paste the link, check the preview</b><span>Nothing goes live until you save.</span></div></div>'
	'<div class="a-krok"><span class="a-nr">3</span><div><b>Put it on the page</b><span>A block, an Elementor widget or <code>' + NAWIAS + 'sheet_table id=&quot;X&quot;' + P + '</code>.</span></div></div>'
	'</div></div>'

	# Zaufanie
	'<div class="a-kafel a-4" style="--x:100%;--y:100%">'
	'<p class="a-etykieta">Why it holds up</p>'
	'<h3 class="a-h">Your page never shows a broken table.</h3>'
	'<ul class="a-pewne">'
	'<li>' + IKONY[ 'tarcza' ] + '<div><b>The last good copy stays</b><span>If Google is unreachable or the sheet goes private, visitors still see your table.</span></div></li>'
	'<li>' + IKONY[ 'serwer' ] + '<div><b>Served from your server</b><span>Fetched in the background, so nobody waits on Google.</span></div></li>'
	'<li>' + IKONY[ 'tabela' ] + '<div><b>A real &lt;table&gt; in the HTML</b><span>Readable by search engines, even with scripts off.</span></div></li>'
	'<li>' + IKONY[ 'klodka' ] + '<div><b>Talks to Google only</b><span>No analytics, no account with us. Data stays in your database.</span></div></li>'
	'</ul></div>'

	# Co dalej
	'<div class="a-kafel a-5">'
	'<div><h3 class="a-h">Try it on your own sheet.</h3>'
	'<p class="a-p">Free, no row limit, no watermark. You see the table in the preview before anything is published.</p></div>'
	'<div class="a-przyciski"><a class="p-cta" href="#">Download free</a><a class="p-cta-2" href="#">See what Pro adds</a></div>'
	'</div>'

	'</div>' )


# ------------------------------------------------------------- B: oś kroków

STYL_B = r'''
.b-uklad { display: grid; grid-template-columns: minmax( 0, 1fr ) minmax( 0, 1.05fr ); gap: clamp( 2rem, 5vw, 5rem ); margin-top: 2.6rem; align-items: start; }
.b-dla { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: 1.4rem; }
.b-dla > span:first-child { font-family: var( --mono ); font-size: .8125rem; color: var( --tekst-3 ); margin-right: .3rem; }
.b-os { position: relative; margin-top: 2.2rem; }
.b-os::before { content: ""; position: absolute; left: 21px; top: 22px; bottom: 34px; width: 2px;
	background: linear-gradient( rgb( var( --mieta ) ), rgba( var( --mieta ), .55 ) 60%, rgba( var( --mieta ), .12 ) ); border-radius: 2px; }
.b-krok { position: relative; display: grid; grid-template-columns: 44px 1fr; gap: 1.2rem; padding-bottom: 1.9rem; }
.b-nr { position: relative; z-index: 1; width: 44px; height: 44px; border-radius: 99px; display: grid; place-items: center;
	font-family: var( --mono ); font-size: .875rem; color: rgb( var( --mieta ) );
	background: #141c1b; border: 1px solid rgba( var( --mieta ), .45 ); box-shadow: 0 0 0 6px #232a29, 0 0 24px -4px rgba( var( --mieta ), .5 ); }
.b-krok.jest-teraz .b-nr { background: rgb( var( --mieta ) ); color: #06100f; font-weight: 600; }
.b-h { font-weight: 600; font-size: 1.3rem; line-height: 1.3; margin-top: .55rem; }
.b-p { font-size: 1.0625rem; line-height: 1.55; color: var( --tekst-2 ); margin-top: .35rem; max-width: 34rem; }
.b-p em { font-style: normal; color: rgb( var( --mieta ) ); }
.b-pewne { display: inline-flex; align-items: center; gap: .5rem; margin-top: .7rem; padding: .35rem .7rem; border-radius: 8px;
	background: rgba( var( --mieta ), .07 ); border: 1px solid rgba( var( --mieta ), .18 ); font-size: .875rem; color: var( --tekst ); }
.b-pewne svg { width: 16px; height: 16px; color: rgb( var( --mieta ) ); flex: none; }
.b-dalej { display: flex; align-items: center; gap: .8rem; margin-top: .4rem; padding-left: calc( 44px + 1.2rem ); }
.b-ekran { position: sticky; top: 2rem; align-self: center; }
.b-ekran .p-strona { transform: perspective( 1600px ) rotateY( -6deg ) rotateX( 2deg ); transform-origin: left center; }
.b-plakietka { position: absolute; display: inline-flex; align-items: center; gap: .5rem; padding: .55rem .85rem; border-radius: 12px;
	background: rgba( 19, 29, 27, .92 ); border: 1px solid rgba( var( --mieta ), .3 ); font-size: .875rem; color: var( --tekst );
	box-shadow: 0 18px 40px -20px rgba( 0, 0, 0, .9 ), 0 0 30px -14px rgba( var( --mieta ), .6 ); backdrop-filter: blur( 6px ); white-space: nowrap; }
.b-plakietka svg { width: 16px; height: 16px; color: rgb( var( --mieta ) ); }
.b-pl-1 { top: 96px; right: -14px; }
.b-pl-2 { bottom: 6px; left: 0; }
.b-pl-3 { top: 36px; right: -14px; }
.b-ekran .p-arkusz { position: absolute; width: 52%; top: 0; left: 0; transform: rotate( -2.5deg ); box-shadow: 0 24px 50px -24px rgba( 0, 0, 0, .95 ); z-index: -1; }
.b-ekran-w { position: relative; padding: 150px 0 64px 88px; isolation: isolate; }
.b-ekran .p-tabela td { padding-block: .8rem; }
@media ( max-width: 900px ) {
	.b-uklad { grid-template-columns: 1fr; }
	.b-ekran { position: static; }
	/* Wąsko sama strona z jedną plakietką: arkusz i reszta plakietek
	   tylko by się na siebie nakładały. */
	.b-ekran-w { padding: 0 0 54px; }
	.b-ekran .p-arkusz, .b-pl-1, .b-pl-3 { display: none; }
	.b-ekran .p-strona { transform: none; }
	.b-dalej { padding-left: 0; flex-wrap: wrap; }
}
'''

def mala( d ):
	return ( '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" '
		'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>' )

PTASZEK = mala( '<path d="m3.5 8.5 3 3 6-7"/>' )
TARCZA = mala( '<path d="M8 1.8 3 3.8v3.7c0 2.9 2.1 5.4 5 6.4 2.9-1 5-3.5 5-6.4V3.8Z"/><path d="m6 8 1.6 1.6L10.5 6.5"/>' )
ZEGAR = mala( '<circle cx="8" cy="8" r="6"/><path d="M8 4.5V8l2.5 1.5"/>' )

TRESC_B = ( NAGLOWEK +
	'<p class="p-wstep">Live Sheets Table turns a Google Sheet into a real, responsive table on your WordPress '
	'page. You keep editing the sheet; the page keeps up.</p>'
	'<div class="b-dla"><span>Made for</span>' + zetony( 6 ) + '</div>'
	'<div class="b-uklad">'

	'<div><div class="b-os">'
	'<div class="b-krok"><span class="b-nr">01</span><div><h3 class="b-h">Share the sheet</h3>'
	'<p class="b-p">In Google Sheets: <em>Share &rarr; Anyone with the link</em>, as a <em>Viewer</em>.</p>'
	'<span class="b-pewne">' + PTASZEK + 'No API key, no Google Cloud project, no account with us</span></div></div>'

	'<div class="b-krok"><span class="b-nr">02</span><div><h3 class="b-h">Paste the link and check the preview</h3>'
	'<p class="b-p">You see exactly what was read &mdash; headings, rows, the right tab &mdash; before you save.</p>'
	'<span class="b-pewne">' + PTASZEK + 'Nothing is published until it looks right</span></div></div>'

	'<div class="b-krok"><span class="b-nr">03</span><div><h3 class="b-h">Put it on the page</h3>'
	'<p class="b-p">A block, an Elementor widget, or a shortcode anywhere else &mdash; Divi included.</p>'
	'<span class="b-pewne">' + PTASZEK + 'One renderer behind all three, so they never drift apart</span></div></div>'

	'<div class="b-krok jest-teraz"><span class="b-nr">04</span><div><h3 class="b-h">Then leave it alone</h3>'
	'<p class="b-p">The plugin checks the sheet in the background. If Google is ever unreachable, '
	'visitors keep seeing the last good copy &mdash; never an error.</p>'
	'<span class="b-pewne">' + TARCZA + 'Served from your server, readable by search engines</span></div></div>'
	'</div>'
	'<div class="b-dalej"><a class="p-cta" href="#">Download free</a><span class="p-mono" style="font-size:.875rem;color:var(--tekst-3)">no row limit &middot; no watermark</span></div>'
	'</div>'

	'<div class="b-ekran"><div class="b-ekran-w">' + ARKUSZ + STRONA +
	'<span class="b-plakietka b-pl-1">' + ZEGAR + 'Checked 2 min ago</span>'
	'<span class="b-plakietka b-pl-2">' + TARCZA + 'Google down? Last good copy stays</span>'
	'<span class="b-plakietka b-pl-3">' + PTASZEK + 'A real &lt;table&gt;, no JS needed</span>'
	'</div></div>'

	'</div>' )


# ------------------------------------------------------------- C: cztery pytania

STYL_C = r'''
.c-siatka { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 2.6rem; }
.c-karta {
	position: relative; border-radius: 22px; padding: 1.8rem 1.9rem 1.9rem;
	/* Świecąca krawędź: obrys z gradientu, jaśniejszy w jednym rogu i gasnący
	   wzdłuż boków, bez szwu, który zostawiał gradient stożkowy. */
	border: 1px solid transparent;
	background: linear-gradient( #151c1b, #151c1b ) padding-box,
		linear-gradient( var( --kat, 135deg ), rgba( var( --mieta ), .6 ), rgba( 138, 168, 163, .14 ) 38%, rgba( 138, 168, 163, .1 ) ) border-box;
	isolation: isolate; overflow: hidden;
	display: flex; flex-direction: column;
}
.c-karta::after {
	content: ""; position: absolute; z-index: -2; width: 340px; height: 340px; border-radius: 50%;
	left: var( --x, 70% ); top: var( --y, -30% ); transform: translate( -50%, -50% );
	background: radial-gradient( rgba( var( --mieta ), .16 ), transparent 65% );
}
.c-pyt { display: flex; align-items: center; gap: .7rem; font-family: var( --mono ); font-size: .8125rem; letter-spacing: .1em; text-transform: uppercase; color: var( --tekst-3 ); }
.c-pyt b { font-weight: 500; color: rgb( var( --mieta ) ); }
.c-h { font-family: var( --serif ); font-weight: 400; font-size: 2.1rem; line-height: 1.12; margin-top: .7rem; }
.c-p { font-size: 1.0625rem; line-height: 1.55; color: var( --tekst-2 ); margin-top: .7rem; max-width: 34rem; }
.c-dol { margin-top: auto; padding-top: 1.5rem; }
.c-przeplyw { display: grid; grid-template-columns: minmax( 0, .9fr ) auto minmax( 0, 1fr ); gap: .9rem; align-items: center; }
.c-przeplyw .p-strona { box-shadow: none; }
.c-strz { width: 46px; color: rgb( var( --mieta ) ); }
.c-zetony { display: flex; flex-wrap: wrap; gap: .5rem; }
.c-lista { display: grid; gap: .75rem; }
.c-lista li { display: grid; grid-template-columns: 22px 1fr; gap: .7rem; font-size: 1rem; line-height: 1.45; color: var( --tekst-2 ); }
.c-lista li b { color: var( --tekst ); font-weight: 600; }
.c-lista svg { width: 22px; height: 22px; color: rgb( var( --mieta ) ); margin-top: 1px; }
.c-kroki { display: grid; grid-template-columns: repeat( 3, 1fr ); gap: .6rem; margin-bottom: 1.3rem; }
.c-kroki div { padding: .75rem .8rem; border-radius: 12px; background: rgba( 255, 255, 255, .03 ); border: 1px solid var( --plyta-linia ); font-size: .9375rem; line-height: 1.35; }
.c-kroki span { display: block; font-family: var( --mono ); font-size: .75rem; color: rgb( var( --mieta ) ); margin-bottom: .3rem; }
.c-karta.jest-cta { background: linear-gradient( 160deg, #17312c, #121a19 60% ) padding-box,
	linear-gradient( var( --kat ), rgba( var( --mieta ), .75 ), rgba( var( --mieta ), .2 ) 45%, rgba( 138, 168, 163, .12 ) ) border-box; }
.c-karta.jest-gora .c-dol { margin-top: 0; }
.c-przyciski { display: flex; flex-wrap: wrap; gap: .7rem; align-items: center; }
@media ( max-width: 900px ) { .c-siatka { grid-template-columns: 1fr; } .c-przeplyw { grid-template-columns: 1fr; } .c-kroki { grid-template-columns: 1fr; } }
'''

def srednia( d ):
	return ( '<svg viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="1.6" '
		'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>' )

TRESC_C = ( NAGLOWEK +
	'<p class="p-wstep">Four things you should know before you install anything.</p>'
	'<div class="c-siatka">'

	'<div class="c-karta" style="--kat:135deg;--x:85%;--y:-20%">'
	'<p class="c-pyt"><b>01</b> What does it do?</p>'
	'<h3 class="c-h">Your Google Sheet, as a real table on your page.</h3>'
	'<p class="c-p">Edit the sheet the way you always do. The table on your site follows on its own, every 15 minutes or sooner.</p>'
	'<div class="c-dol"><div class="c-przeplyw">' + ARKUSZ +
	'<svg class="c-strz" viewBox="0 0 46 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M2 7h38" stroke-dasharray="3 4"/><path d="m36 2 6 5-6 5"/></svg>'
	+ STRONA + '</div></div></div>'

	'<div class="c-karta jest-gora" style="--kat:225deg;--x:15%;--y:-20%">'
	'<p class="c-pyt"><b>02</b> Who is it for?</p>'
	'<h3 class="c-h">Anyone who keeps their numbers in a spreadsheet.</h3>'
	'<p class="c-p">Shops, clubs, schools, venues, agencies &mdash; whoever updates a list more often than they want to touch their website.</p>'
	'<div class="c-dol"><div class="c-zetony">' + zetony() + '</div></div></div>'

	'<div class="c-karta jest-gora" style="--kat:315deg;--x:85%;--y:120%">'
	'<p class="c-pyt"><b>03</b> Why trust it?</p>'
	'<h3 class="c-h">It is built to never break your page.</h3>'
	'<div class="c-dol"><ul class="c-lista">'
	'<li>' + srednia( '<path d="M11 2.5 4 5.5v5c0 4 2.9 7.5 7 8.9 4.1-1.4 7-4.9 7-8.9v-5Z"/><path d="m8 11 2.3 2.3L14.5 9"/>' ) + '<span><b>Google down? The last good copy stays.</b> Visitors never see an error or an empty table.</span></li>'
	'<li>' + srednia( '<rect x="3.5" y="4" width="15" height="6" rx="1.5"/><rect x="3.5" y="12" width="15" height="6" rx="1.5"/>' ) + '<span><b>Served from your server.</b> Fetched in the background, so nobody waits.</span></li>'
	'<li>' + srednia( '<rect x="3" y="4" width="16" height="14" rx="2"/><path d="M3 8.5h16M8.5 8.5V18"/>' ) + '<span><b>A real &lt;table&gt;.</b> Readable by search engines, even without JavaScript.</span></li>'
	'<li>' + srednia( '<rect x="5" y="9.5" width="12" height="9" rx="2"/><path d="M7.5 9.5V7a3.5 3.5 0 0 1 7 0v2.5"/>' ) + '<span><b>Private by design.</b> No analytics, no account with us &mdash; it only talks to Google.</span></li>'
	'</ul></div></div>'

	'<div class="c-karta jest-cta" style="--kat:45deg;--x:20%;--y:120%">'
	'<p class="c-pyt"><b>04</b> What now?</p>'
	'<h3 class="c-h">Two minutes, and it costs nothing.</h3>'
	'<p class="c-p">No row limit, no watermark, no expiry date. Pro adds the extras when you need them.</p>'
	'<div class="c-dol"><div class="c-kroki">'
	'<div><span>1</span>Share the sheet with a link</div>'
	'<div><span>2</span>Paste it and check the preview</div>'
	'<div><span>3</span>Block, widget or shortcode</div>'
	'</div><div class="c-przyciski"><a class="p-cta" href="#">Download free</a><a class="p-cta-2" href="#">Compare with Pro</a></div></div></div>'

	'</div>' )


for nazwa, tytul, styl, tresc in (
	( 'A-bento', 'A — bento', STYL_A, TRESC_A ),
	( 'B-os-krokow', 'B — oś kroków', STYL_B, TRESC_B ),
	( 'C-cztery-pytania', 'C — cztery pytania', STYL_C, TRESC_C ),
):
	( TU / ( nazwa + '.html' ) ).write_text( strona( tytul, styl, tresc ) )
