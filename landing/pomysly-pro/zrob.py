# Sześć propozycji do wyglądu w Pro, narysowanych PRAWDZIWYM arkuszem stylów
# wtyczki. Dodatkowy CSS pod każdą próbką to dokładnie to, co trzeba byłoby
# dołożyć do wtyczki — żeby propozycja nie obiecywała czegoś, czego potem nie
# da się zrobić.
import html, pathlib, urllib.parse

CSS = pathlib.Path( '/home/user/111/live-sheets-table/assets/css/lstab-table.css' ).read_text()

WIERSZE = [
	( 'Opening keynote',           '09:30', 'Main hall', 120, 'Open'     ),
	( 'Designing for readability', '10:45', 'Studio',     18, 'Waitlist' ),
	( 'Building faster websites',  '11:30', 'Hall B',     64, 'Open'     ),
	( 'Working with spreadsheets', '13:00', 'Studio',      0, 'Full'     ),
	( 'Accessibility in practice', '15:15', 'Hall B',      7, 'Waitlist' ),
	( 'Rebuilding an online shop', '16:00', 'Main hall',  95, 'Open'     ),
]
NAJWIECEJ = max( w[ 3 ] for w in WIERSZE )

MOWCY = [ ( 'Maya Ortiz', '#5fe3cf' ), ( 'Tomas Beck', '#9db4ff' ), ( 'Ada Reyes', '#ffc48a' ),
          ( 'Jonas Lind', '#f2a0c4' ), ( 'Ines Costa', '#a8e6a1' ), ( 'Piotr Nowak', '#ffd97a' ) ]

def awatar( imie, kolor ):
	inicjaly = ''.join( c[ 0 ] for c in imie.split() )[ :2 ]
	svg = ( "<svg xmlns='http://www.w3.org/2000/svg' width='72' height='72'>"
		"<rect width='72' height='72' rx='36' fill='" + kolor + "'/>"
		"<text x='36' y='47' font-family='IBM Plex Sans,sans-serif' font-size='28' font-weight='600'"
		" fill='#0d1513' text-anchor='middle'>" + inicjaly + "</text></svg>" )
	return 'data:image/svg+xml,' + urllib.parse.quote( svg )

def komorka( naglowek, tresc, rownanie = 'start', klasa = '', styl = '' ):
	return ( '<td role="cell" data-label="%s" data-lstab-align="%s"%s%s>'
		'<span class="lstab-cell-label">%s</span><span class="lstab-cell-value">%s</span></td>'
		% ( html.escape( naglowek ), rownanie,
			' class="' + klasa + '"' if klasa else '',
			' style="' + styl + '"' if styl else '',
			html.escape( naglowek ), tresc ) )

def tabela( naglowki, wiersze_html, stopka = '', ile_kolumn = 5 ):
	glowa = ''.join(
		'<th scope="col" data-lstab-col="%d" data-lstab-align="%s"><button type="button" class="lstab-sort">'
		'<span class="lstab-sort-label">%s</span><span class="lstab-sort-icon" aria-hidden="true"></span></button></th>'
		% ( i, r, html.escape( n ) ) for i, ( n, r ) in enumerate( naglowki ) )
	return ( '<div class="lstab-container"><div class="lstab lstab-style-midnight lstab-cols-%d lstab-mieta">'
		'<div class="lstab-scroll"><table class="lstab-table">'
		'<thead><tr>%s</tr></thead><tbody>%s</tbody>%s</table></div></div></div>'
		% ( ile_kolumn, glowa, wiersze_html, stopka ) )

# ---------------------------------------------------------------- 1. mapa ciepła
def mapa_ciepla():
	ciało = ''
	for tytul, godz, sala, miejsca, status in WIERSZE:
		udzial = miejsca / NAJWIECEJ
		ciało += ( '<tr class="lstab-row">'
			+ komorka( 'Session', html.escape( tytul ) )
			+ komorka( 'Starts', godz )
			+ komorka( 'Room', html.escape( sala ) )
			+ komorka( 'Seats left', str( miejsca ), 'end', 'lstabp-heat',
				'--lstabp-heat:' + ( '%.2f' % udzial ) )
			+ komorka( 'Status', status ) + '</tr>' )
	return tabela( [ ( 'Session', 'start' ), ( 'Starts', 'start' ), ( 'Room', 'start' ),
		( 'Seats left', 'end' ), ( 'Status', 'start' ) ], ciało )

# ---------------------------------------------------------------- 2. słupki
def slupki():
	ciało = ''
	for tytul, godz, sala, miejsca, status in WIERSZE:
		udzial = miejsca / NAJWIECEJ
		ciało += ( '<tr class="lstab-row">'
			+ komorka( 'Session', html.escape( tytul ) )
			+ komorka( 'Starts', godz )
			+ komorka( 'Room', html.escape( sala ) )
			+ komorka( 'Seats left', str( miejsca ), 'end', 'lstabp-bar',
				'--lstabp-bar:' + ( '%.1f%%' % ( udzial * 100 ) ) )
			+ komorka( 'Status', status ) + '</tr>' )
	return tabela( [ ( 'Session', 'start' ), ( 'Starts', 'start' ), ( 'Room', 'start' ),
		( 'Seats left', 'end' ), ( 'Status', 'start' ) ], ciało )

# ---------------------------------------------------------------- 3. miniatury
def miniatury():
	ciało = ''
	for ( tytul, godz, sala, miejsca, status ), ( imie, kolor ) in zip( WIERSZE, MOWCY ):
		obraz = '<img class="lstabp-thumb" src="' + awatar( imie, kolor ) + '" alt="" width="72" height="72">'
		ciało += ( '<tr class="lstab-row">'
			+ komorka( 'Speaker', obraz, 'start', 'lstabp-media' )
			+ komorka( 'Session', html.escape( tytul ) )
			+ komorka( 'Starts', godz )
			+ komorka( 'Room', html.escape( sala ) )
			+ komorka( 'Seats left', str( miejsca ), 'end' ) + '</tr>' )
	return tabela( [ ( 'Speaker', 'start' ), ( 'Session', 'start' ), ( 'Starts', 'start' ),
		( 'Room', 'start' ), ( 'Seats left', 'end' ) ], ciało )

# ---------------------------------------------------------------- 4. link jak przycisk
def przyciski():
	ciało = ''
	for tytul, godz, sala, miejsca, status in WIERSZE:
		pelne = 0 == miejsca
		guzik = ( '<span class="lstabp-cta is-off">Full</span>' if pelne
			else '<a class="lstabp-cta" href="#">Book a seat</a>' )
		ciało += ( '<tr class="lstab-row">'
			+ komorka( 'Session', html.escape( tytul ) )
			+ komorka( 'Starts', godz )
			+ komorka( 'Room', html.escape( sala ) )
			+ komorka( 'Seats left', str( miejsca ), 'end' )
			+ komorka( 'Booking', guzik, 'start', 'lstabp-action' ) + '</tr>' )
	return tabela( [ ( 'Session', 'start' ), ( 'Starts', 'start' ), ( 'Room', 'start' ),
		( 'Seats left', 'end' ), ( 'Booking', 'start' ) ], ciało )

# ---------------------------------------------------------------- 5. wiersz podsumowania
def podsumowanie():
	ciało = ''
	for tytul, godz, sala, miejsca, status in WIERSZE:
		ciało += ( '<tr class="lstab-row">'
			+ komorka( 'Session', html.escape( tytul ) )
			+ komorka( 'Starts', godz )
			+ komorka( 'Room', html.escape( sala ) )
			+ komorka( 'Seats left', str( miejsca ), 'end' )
			+ komorka( 'Status', status ) + '</tr>' )
	suma = sum( w[ 3 ] for w in WIERSZE )
	stopka = ( '<tfoot class="lstabp-totals"><tr>'
		'<td colspan="3"><span class="lstab-cell-value">6 sessions</span></td>'
		'<td data-lstab-align="end"><span class="lstab-cell-label">Seats left</span>'
		'<span class="lstab-cell-value">' + str( suma ) + '</span></td>'
		'<td><span class="lstab-cell-value">total</span></td></tr></tfoot>' )
	return tabela( [ ( 'Session', 'start' ), ( 'Starts', 'start' ), ( 'Room', 'start' ),
		( 'Seats left', 'end' ), ( 'Status', 'start' ) ], ciało, stopka )

# ---------------------------------------------------------------- 6. kropka przy wartości
KROPKI = { 'Open': '#5fe3cf', 'Waitlist': '#ffc48a', 'Full': '#ff8f9c' }
def kropki():
	ciało = ''
	for tytul, godz, sala, miejsca, status in WIERSZE:
		ciało += ( '<tr class="lstab-row">'
			+ komorka( 'Session', html.escape( tytul ) )
			+ komorka( 'Starts', godz )
			+ komorka( 'Room', html.escape( sala ) )
			+ komorka( 'Seats left', str( miejsca ), 'end' )
			+ komorka( 'Status', status, 'start', 'lstabp-dot',
				'--lstabp-dot:' + KROPKI[ status ] ) + '</tr>' )
	return tabela( [ ( 'Session', 'start' ), ( 'Starts', 'start' ), ( 'Room', 'start' ),
		( 'Seats left', 'end' ), ( 'Status', 'start' ) ], ciało )

PROBY = [
	( '1', 'Mapa ciepła na kolumnie liczbowej',
	  'Tło komórki tym mocniejsze, im większa liczba — wzrokiem widać, gdzie jest dużo, a gdzie pusto, bez czytania cyfr. Skala liczona od najmniejszej i największej wartości w kolumnie.', mapa_ciepla ),
	( '2', 'Słupki w komórce',
	  'To samo, ale jako długość paska za wartością. Czyta się jak wykres słupkowy wbudowany w tabelę; liczba zostaje na wierzchu i dalej jest zwykłym tekstem.', slupki ),
	( '3', 'Miniatury ze zdjęć',
	  'Kolumna z adresem obrazka staje się kolumną ze zdjęciem — produkt, człowiek, okładka. Dziś taka kolumna pokazuje goły link. To najmocniej poszerza zastosowania wtyczki.', miniatury ),
	( '4', 'Link jako przycisk',
	  'Adres zamienia się w przycisk z własnym napisem, w kolorach tabeli. Reguła może go wygasić, gdy wiersz na to zasługuje — tu „Full” zamiast „Book a seat”.', przyciski ),
	( '5', 'Wiersz podsumowania',
	  'Suma, średnia albo licznik pod kolumną, oddzielone linią. Liczone na serwerze z całej tabeli, nie z jednej strony wyników.', podsumowanie ),
	( '6', 'Kropka przy wartości',
	  'Najcichszy sposób na status: mała kolorowa kropka przed wartością zamiast malowania komórki. Dobra tam, gdzie pigułka jest już za głośna.', kropki ),
]

sekcje = ''
for numer, tytul, opis, budowniczy in PROBY:
	sekcje += ( '<section class="proba"><p class="numer">' + numer + '</p>'
		'<h2>' + html.escape( tytul ) + '</h2><p class="opis">' + opis + '</p>'
		+ budowniczy() + '</section>' )

DODATKOWY = """
/* ---- 1. mapa ciepła: siła koloru z jednej zmiennej na komórce ---- */
.lstabp-heat { position: relative; }
.lstabp-heat::before { content: ""; position: absolute; inset: 0; pointer-events: none;
	background-color: rgb( var( --lstabp-heat-rgb, 95 227 207 ) / calc( var( --lstabp-heat, 0 ) * 0.45 ) ); }
.lstabp-heat .lstab-cell-value { position: relative; }

/* ---- 2. słupki: szerokość paska z jednej zmiennej ---- */
.lstabp-bar { position: relative; }
.lstabp-bar::before { content: ""; position: absolute; left: 0; top: 12%; bottom: 12%; border-radius: 3px;
	width: var( --lstabp-bar, 0 ); background-color: rgb( var( --lstabp-bar-rgb, 95 227 207 ) / .3 ); }
.lstabp-bar .lstab-cell-value { position: relative; }

/* ---- 3. miniatury ---- */
.lstabp-thumb { display: block; width: 44px; height: 44px; border-radius: 999px; object-fit: cover; }
.lstabp-media .lstab-cell-value { display: inline-flex; }

/* ---- 4. link jako przycisk ---- */
.lstabp-cta { display: inline-block; padding: .34em 1em; border-radius: 999px; text-decoration: none;
	background-color: rgb( var( --lstab-accent-rgb, 95 227 207 ) ); color: #06100f; font-size: .88em; font-weight: 600; }
.lstabp-cta.is-off { background-color: transparent; border: 1px solid var( --lstab-border-strong );
	color: var( --lstab-fg-faint ); font-weight: 400; }

/* ---- 5. wiersz podsumowania ---- */
.lstabp-totals td { border-top: 2px solid var( --lstab-border-strong ); font-weight: 600;
	padding-top: .8em; color: var( --lstab-fg ); }
.lstabp-totals td:last-child { font-weight: 400; color: var( --lstab-fg-faint ); text-transform: uppercase;
	letter-spacing: .06em; font-size: .8em; }

/* ---- 6. kropka ---- */
.lstabp-dot .lstab-cell-value::before { content: ""; display: inline-block; width: .6em; height: .6em;
	margin-right: .55em; border-radius: 999px; background-color: var( --lstabp-dot, currentColor );
	vertical-align: baseline; }
"""

strona = '''<!doctype html><html lang="pl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Pomysły do wyglądu w Pro</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Inria+Serif:ital,wght@0,300&display=swap">
<style>
''' + CSS + DODATKOWY + '''
.lstab-mieta { --lstab-bg: #0d1513; --lstab-head-bg: #121d1b; --lstab-sticky-bg: #0d1513;
	--lstab-fg: #e9f4f1; --lstab-fg-muted: #9fb8b4; --lstab-fg-faint: #85a09c; --lstab-head-fg: #9fb8b4;
	--lstab-border: #1f2e2b; --lstab-border-strong: #334743; --lstab-stripe: #101a18; --lstab-hover: #16302c;
	--lstab-accent: #5fe3cf; --lstab-accent-rgb: 95 227 207;
	--lstab-shadow: 0 2px 10px rgba( 0, 0, 0, .5 ), 0 0 0 1px rgba( 95, 227, 207, .08 ); }
.lstab-mieta .lstab-table thead th { background-image: linear-gradient( to bottom, #16241f, #121d1b );
	border-bottom-color: #334743; box-shadow: inset 0 -1px 0 #334743; }
body { margin: 0; background: #232a29; color: #e9f4f1; font-family: "IBM Plex Sans", system-ui, sans-serif;
	background-image: linear-gradient( to right, rgba( 255, 255, 255, .035 ) 1px, transparent 1px ),
		linear-gradient( to bottom, rgba( 255, 255, 255, .035 ) 1px, transparent 1px );
	background-size: 88px 44px; }
.owijka { max-width: 1100px; margin: 0 auto; padding: 50px 24px 80px; }
h1 { font-family: "Inria Serif", Georgia, serif; font-weight: 300; font-size: 42px; margin: 0 0 12px; }
.wstep { font-size: 20px; line-height: 1.5; color: #b3c6c3; max-width: 62ch; margin: 0 0 20px; }
.proba { margin: 54px 0 0; }
.numer { font-family: "IBM Plex Mono", monospace; font-size: 14px; color: #5fe3cf; letter-spacing: .14em; margin: 0 0 6px; }
h2 { font-family: "Inria Serif", Georgia, serif; font-weight: 300; font-size: 30px; margin: 0 0 8px; }
.opis { font-size: 18px; line-height: 1.5; color: #b3c6c3; max-width: 70ch; margin: 0 0 20px; }
</style></head><body><div class="owijka">
<h1>Sześć pomysłów do wyglądu w Pro</h1>
<p class="wstep">Każda próbka jest narysowana prawdziwym arkuszem stylów wtyczki, na tych samych danych. To, co widać, to nie rysunek w programie graficznym — to tabela wtyczki z dołożonym kawałkiem CSS, który trzeba by do niej dopisać.</p>
''' + sekcje + '''</div></body></html>'''

pathlib.Path( 'pomysly.html' ).write_text( strona )
print( 'ok', len( strona ) )
