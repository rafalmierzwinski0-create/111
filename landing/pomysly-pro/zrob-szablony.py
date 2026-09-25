# Propozycje szablonów całej tabeli. Każda próbka stoi na PRAWDZIWYM arkuszu
# stylów wtyczki; dodatkowy CSS pod każdą z nich to dokładnie to, co trzeba by
# dopisać, żeby szablon istniał naprawdę.
import html, pathlib

CSS = pathlib.Path( '/home/user/111/live-sheets-table/assets/css/lstab-table.css' ).read_text()

NAGLOWKI = [ ( 'Session', 'start' ), ( 'Starts', 'start' ), ( 'Room', 'start' ),
             ( 'Seats left', 'end' ), ( 'Status', 'start' ) ]
WIERSZE = [
	[ 'Opening keynote',           '09:30', 'Main hall', '120', 'Open'     ],
	[ 'Designing for readability', '10:45', 'Studio',    '18',  'Waitlist' ],
	[ 'Building faster websites',  '11:30', 'Hall B',    '64',  'Open'     ],
	[ 'Working with spreadsheets', '13:00', 'Studio',    '0',   'Full'     ],
	[ 'Accessibility in practice', '15:15', 'Hall B',    '7',   'Waitlist' ],
]

def tabela( skorka, klasy_extra = '', ile = None, podpis = True, kolumny = None ):
	# Próbka w wąskiej kolumnie sama złożyłaby się w karty — i wtedy nie widać
	# tego, co próbka ma pokazać. Mniej kolumn mieści się bez zwijania.
	wiersze = WIERSZE if ile is None else WIERSZE[ :ile ]
	uzyte = list( range( len( NAGLOWKI ) ) ) if kolumny is None else kolumny
	glowa = ''.join(
		'<th scope="col" data-lstab-col="%d" data-lstab-align="%s"><button type="button" class="lstab-sort">'
		'<span class="lstab-sort-label">%s</span><span class="lstab-sort-icon" aria-hidden="true"></span></button></th>'
		% ( nr, NAGLOWKI[ i ][ 1 ], html.escape( NAGLOWKI[ i ][ 0 ] ) ) for nr, i in enumerate( uzyte ) )

	cialo = ''
	for w in wiersze:
		cialo += '<tr class="lstab-row">' + ''.join(
			'<td role="cell" data-label="%s" data-lstab-align="%s">'
			'<span class="lstab-cell-label">%s</span><span class="lstab-cell-value">%s</span></td>'
			% ( html.escape( NAGLOWKI[ i ][ 0 ] ), NAGLOWKI[ i ][ 1 ], html.escape( NAGLOWKI[ i ][ 0 ] ), html.escape( w[ i ] ) )
			for i in uzyte ) + '</tr>'

	return ( '<div class="lstab-container"><div class="lstab lstab-style-' + skorka + ' lstab-cols-' + str( len( uzyte ) ) + ' ' + klasy_extra + '">'
		+ ( '<div class="lstab-controls"><label class="lstab-search">'
			'<input type="search" class="lstab-search-input" placeholder="Search&hellip;"></label>'
			'<span class="lstab-count">5 of 5 rows</span></div>'
			'<p class="lstab-caption">Programme &mdash; day one</p>' if podpis else '' )
		+ '<div class="lstab-scroll"><table class="lstab-table">'
		'<thead><tr>' + glowa + '</tr></thead><tbody>' + cialo + '</tbody></table></div>'
		+ ( '<div class="lstab-foot"><p class="lstab-meta">Updated 4 mins ago</p></div>' if podpis else '' )
		+ '</div></div>' )

SKORKI = [
	( 'karty', 'Karty', 'Każdy wiersz to osobna, zaokrąglona karta z odstępem i delikatnym cieniem. Tabela przestaje wyglądać jak arkusz, a zaczyna jak lista rzeczy. Na telefonie nic się nie zmienia — tam i tak są karty.', False ),
	( 'cicha', 'Cicha', 'Ani jednej linii. Trzyma ją samo światło i wyrównanie, plus jedna gruba kreska pod nagłówkami. Najspokojniejsza z całej stawki i najtrudniejsza do zepsucia.', False ),
	( 'kolumny', 'Kolumnowa', 'Linie pionowe zamiast poziomych, jak w drukowanym rozkładzie jazdy. Dobre tam, gdzie liczy się porównywanie w dół kolumny, a nie czytanie wiersza.', False ),
	( 'terminal', 'Terminal', 'Całość pismem maszynowym: jedna szerokość znaku, wersaliki w nagłówkach, cienkie miętowe linie na ciemnym. Cyfry ustawiają się w kolumnę same z siebie.', True ),
	( 'szklo', 'Szkło', 'Półprzezroczyste tło z rozmyciem tego, co jest pod spodem. Ma sens tylko na zdjęciu albo gradiencie — i wtedy wygląda drożej niż cokolwiek innego tutaj.', True ),
	( 'kontrast', 'Kontrast', 'Ciemny, pełny pasek nagłówków nad jasną tabelą, mocna pierwsza kolumna. Najbliżej tego, co ludzie nazywają „tabelą z prezentacji".', True ),
]

GESTOSC = [ ( 'ciasno', 'Ciasno' ), ( '', 'Zwykle' ), ( 'luzno', 'Luźno' ) ]
LINIE   = [ ( 'siatka', 'Pełna siatka' ), ( '', 'Tylko wiersze' ), ( 'bez-linii', 'Bez linii' ) ]

sekcje = ''
for i, ( klucz, nazwa, opis, ciemna ) in enumerate( SKORKI, 1 ):
	sekcje += ( '<section class="proba' + ( ' na-ciemnym' if ciemna else '' ) + ( ' na-zdjeciu' if 'szklo' == klucz else '' ) + '">'
		'<p class="numer">' + str( i ) + '</p><h2>' + html.escape( nazwa ) + '</h2>'
		'<p class="opis">' + opis + '</p>' + tabela( klucz ) + '</section>' )

# pokrętła
pokretla = '<section class="proba"><p class="numer">7</p><h2>Dwa pokrętła zamiast dziesięciu szablonów</h2>'
pokretla += ( '<p class="opis">Gęstość i linie osobno, niezależnie od szablonu. Trzy na trzy to dziewięć wyglądów '
	'z dwóch list rozwijanych — i każdy działa z każdym kolorem.</p>' )
pokretla += '<div class="siatka-probek">'
for gk, gn in GESTOSC:
	pokretla += ( '<div class="probka"><p class="etykieta">' + gn + '</p>'
		+ tabela( 'clean', 'lstab-gestosc-' + gk if gk else '', ile = 3, podpis = False, kolumny = [ 0, 1, 3 ] ) + '</div>' )
for lk, ln in LINIE:
	pokretla += ( '<div class="probka"><p class="etykieta">' + ln + '</p>'
		+ tabela( 'clean', 'lstab-linie-' + lk if lk else '', ile = 3, podpis = False, kolumny = [ 0, 1, 3 ] ) + '</div>' )
pokretla += '</div></section>'
sekcje += pokretla

DODATKOWY = """
/* ================= 1. karty ================= */
.lstab-style-karty { --lstab-bg: transparent; --lstab-radius: 14px; }
.lstab-style-karty .lstab-table { border-collapse: separate; border-spacing: 0 10px; }
.lstab-style-karty .lstab-table thead th { border-bottom: 0; padding-bottom: .2em;
	font-size: .78em; letter-spacing: .08em; text-transform: uppercase; color: var( --lstab-fg-faint ); }
.lstab-style-karty .lstab-table tbody tr { background-color: var( --lstab-card, #fff );
	box-shadow: 0 1px 2px rgba( 16, 24, 40, .06 ), 0 6px 16px -10px rgba( 16, 24, 40, .35 ); }
.lstab-style-karty .lstab-table tbody td { border: 0; padding-block: 1em; }
.lstab-style-karty .lstab-table tbody td:first-child { border-radius: 14px 0 0 14px; font-weight: 600; }
.lstab-style-karty .lstab-table tbody td:last-child { border-radius: 0 14px 14px 0; }
.lstab-style-karty .lstab-table tbody tr:hover { background-color: var( --lstab-hover ); }

/* ================= 2. cicha ================= */
.lstab-style-cicha { --lstab-pad-y: 0.85em; }
.lstab-style-cicha .lstab-table thead th { border-bottom: 2px solid var( --lstab-fg );
	font-weight: 600; color: var( --lstab-fg ); text-transform: none; letter-spacing: 0; }
.lstab-style-cicha .lstab-table tbody td { border: 0; }
.lstab-style-cicha .lstab-table tbody tr:hover { background-color: transparent; box-shadow: inset 2px 0 0 var( --lstab-accent ); }
.lstab-style-cicha .lstab-table tbody td:first-child { font-weight: 600; }

/* ================= 3. kolumnowa ================= */
.lstab-style-kolumny .lstab-table tbody td { border-bottom: 0; border-right: 1px solid var( --lstab-border ); }
.lstab-style-kolumny .lstab-table thead th { border-right: 1px solid var( --lstab-border ); }
.lstab-style-kolumny .lstab-table td:last-child,
.lstab-style-kolumny .lstab-table th:last-child { border-right: 0; }
.lstab-style-kolumny .lstab-table tbody tr:nth-child(even) { background-color: var( --lstab-stripe ); }

/* ================= 4. terminal ================= */
.lstab-style-terminal {
	--lstab-bg: #0b1110; --lstab-fg: #d7f5ee; --lstab-fg-faint: #6f938c;
	--lstab-head-bg: #0b1110; --lstab-head-fg: #5fe3cf; --lstab-border: #1b2b28;
	--lstab-border-strong: #2b4340; --lstab-stripe: #0e1615; --lstab-hover: #12211f;
	--lstab-accent: #5fe3cf; --lstab-sticky-bg: #0b1110; --lstab-radius: 4px;
	font-family: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;
	font-variant-numeric: tabular-nums;
}
.lstab-style-terminal .lstab-table thead th { text-transform: uppercase; letter-spacing: .12em; font-size: .78em; }
.lstab-style-terminal .lstab-table tbody td { border-bottom: 1px dashed var( --lstab-border ); }

/* ================= 5. szkło ================= */
.lstab-style-szklo {
	--lstab-bg: rgba( 255, 255, 255, .12 ); --lstab-head-bg: rgba( 255, 255, 255, .16 );
	--lstab-fg: #ffffff; --lstab-fg-faint: rgba( 255, 255, 255, .72 ); --lstab-head-fg: rgba( 255, 255, 255, .86 );
	--lstab-border: rgba( 255, 255, 255, .22 ); --lstab-border-strong: rgba( 255, 255, 255, .4 );
	--lstab-stripe: rgba( 255, 255, 255, .06 ); --lstab-hover: rgba( 255, 255, 255, .2 );
	--lstab-accent: #ffffff; --lstab-sticky-bg: rgba( 20, 30, 40, .55 ); --lstab-radius: 16px;
	backdrop-filter: blur( 14px ); -webkit-backdrop-filter: blur( 14px );
	box-shadow: 0 10px 40px -12px rgba( 0, 0, 0, .5 ), inset 0 1px 0 rgba( 255, 255, 255, .3 );
}

/* ================= 6. kontrast ================= */
.lstab-style-kontrast { --lstab-radius: 12px; }
.lstab-style-kontrast .lstab-table thead th {
	background-color: #14201f; color: #eaf3f1; border-bottom: 0;
	font-size: .8em; letter-spacing: .1em; text-transform: uppercase; padding-block: 1em;
	box-shadow: none;
}
.lstab-style-kontrast .lstab-table thead th:first-child { border-radius: 12px 0 0 0; }
.lstab-style-kontrast .lstab-table thead th:last-child { border-radius: 0 12px 0 0; }
.lstab-style-kontrast .lstab-table tbody td:first-child { font-weight: 600; background-color: var( --lstab-stripe ); }
.lstab-style-kontrast .lstab-table tbody tr:hover td:first-child { background-color: var( --lstab-hover ); }

/* ================= pokrętła ================= */
.lstab-gestosc-ciasno { --lstab-pad-y: 0.38em; --lstab-font-size: 0.9em; }
.lstab-gestosc-luzno  { --lstab-pad-y: 1.15em; }
.lstab-linie-siatka .lstab-table td, .lstab-linie-siatka .lstab-table th { border-right: 1px solid var( --lstab-border ); }
.lstab-linie-siatka .lstab-table td:last-child, .lstab-linie-siatka .lstab-table th:last-child { border-right: 0; }
.lstab-linie-bez-linii .lstab-table tbody td { border-bottom: 0; }
"""

strona = '''<!doctype html><html lang="pl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Szablony całej tabeli</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Inria+Serif:ital,wght@0,300&display=swap">
<style>
''' + CSS + DODATKOWY + '''
body { margin: 0; background: #f6f8f8; color: #1c2024; font-family: "IBM Plex Sans", system-ui, sans-serif; }
.owijka { max-width: 1120px; margin: 0 auto; padding: 50px 24px 90px; }
h1 { font-family: "Inria Serif", Georgia, serif; font-weight: 300; font-size: 42px; margin: 0 0 12px; }
.wstep { font-size: 20px; line-height: 1.5; color: #4b5563; max-width: 62ch; margin: 0 0 12px; }
.proba { margin: 30px -24px 0; padding: 34px 24px 40px; border-radius: 18px; }
.proba.na-ciemnym { background: #0b1110; color: #e9f4f1; }
.proba.na-zdjeciu { background-image: linear-gradient( 135deg, #1f6f8b, #6b3fa0 55%, #c2557a ); color: #fff; }
.numer { font-family: "IBM Plex Mono", monospace; font-size: 14px; letter-spacing: .14em; color: #2f7d6e; margin: 0 0 6px; }
.na-ciemnym .numer, .na-zdjeciu .numer { color: #5fe3cf; }
h2 { font-family: "Inria Serif", Georgia, serif; font-weight: 300; font-size: 30px; margin: 0 0 8px; }
.na-ciemnym h2, .na-ciemnym .opis { color: #e9f4f1; }
.na-zdjeciu h2, .na-zdjeciu .opis { color: #fff; }
.opis { font-size: 18px; line-height: 1.5; color: #4b5563; max-width: 70ch; margin: 0 0 22px; }
.siatka-probek { display: grid; grid-template-columns: repeat( auto-fit, minmax( 440px, 1fr ) ); gap: 26px 24px; }
.etykieta { font-family: "IBM Plex Mono", monospace; font-size: 14px; letter-spacing: .1em;
	text-transform: uppercase; color: #6b7280; margin: 0 0 8px; }
</style></head><body><div class="owijka">
<h1>Szablony całej tabeli</h1>
<p class="wstep">Dziś wtyczka ma pięć: Clean, Striped, Bordered, a w Pro Midnight i Editorial. To są propozycje na kolejne — każda narysowana prawdziwym arkuszem stylów wtyczki, na tych samych danych.</p>
''' + sekcje + '''</div></body></html>'''

pathlib.Path( 'skorki.html' ).write_text( strona )
print( 'ok', len( strona ) )
