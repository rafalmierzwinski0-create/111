# Tabela przykładowa pokazana tak, jak wygląda PO USTAWIENIU wtyczki:
# ciemna skórka, własne kolory, reguły malujące wiersze, filtry kolumn,
# przypięta pierwsza kolumna, sortowanie i przyciski pobierania.
#
# Wszystko z prawdziwego arkusza stylów wtyczki i w znacznikowaniu, które
# wypisują LSTAB_Renderer, LSTABP_Facets i LSTABP_Export — nic nie jest
# dorysowane.
import html, pathlib

CSS = pathlib.Path( '/home/user/111/live-sheets-table/assets/css/lstab-table.css' ).read_text()

NAGLOWKI = [ 'Session', 'Starts', 'Room', 'Seats left', 'Status' ]
ROWNANIE = [ 'start', 'start', 'start', 'end', 'start' ]
WIERSZE = [
	[ 'Opening keynote: the web we keep',         '09:30', 'Main hall', '120', 'Open'     ],
	[ 'Designing tables people actually read',    '10:45', 'Studio',    '18',  'Waitlist' ],
	[ 'Ship it Friday: release habits that hold', '11:30', 'Hall B',    '64',  'Open'     ],
	[ 'Workshop: a spreadsheet as your backend',  '13:00', 'Studio',    '0',   'Full'     ],
	[ 'Accessibility clinic: bring your page',    '15:15', 'Hall B',    '7',   'Waitlist' ],
	[ 'Fifteen years of WordPress, honestly',     '16:00', 'Main hall', '95',  'Open'     ],
	[ 'Lightning talks: five minutes each',       '17:00', 'Studio',    '31',  'Open'     ],
	[ 'Closing panel and prize draw',             '17:45', 'Main hall', '0',   'Full'     ],
]

# Reguły kolorów z Pro: "gdy Status jest Full — pomaluj cały wiersz".
# Wtyczka wypisuje je jako styl na wierszu: background-color + czytelny tusz.
# Po poprawce reguła podaje też "--lstab-row-tint", dzięki czemu przypięta
# pierwsza kolumna powtarza kolor zamiast zostać w barwie tabeli.
REGULY = {
	'Full':     'background-color:#4a1f28;color:#ffe3e8;--lstab-fg-faint:color-mix(in srgb,#ffe3e8 78%,#4a1f28);--lstab-row-tint:#4a1f28;',
	'Waitlist': 'background-color:#4a3a12;color:#ffeec4;--lstab-fg-faint:color-mix(in srgb,#ffeec4 78%,#4a3a12);--lstab-row-tint:#4a3a12;',
}

def facet( nazwa, wartosci ):
	pozycje = ''.join(
		'<a class="lstabp-facet-value" rel="nofollow" href="#">'
		'<span class="lstabp-facet-box" aria-hidden="true"></span>'
		'<span class="lstabp-facet-text">%s</span>'
		'<span class="lstabp-facet-count">%d</span></a>' % ( html.escape( w ), n )
		for w, n in wartosci )
	return ( '<details class="lstabp-facet"><summary><b>' + html.escape( nazwa ) + ':</b>'
		'<span class="lstabp-facet-now">any</span></summary>'
		'<div class="lstabp-facet-menu">' + pozycje + '</div></details>' )

def tabela( ile = None, karty = False ):
	wiersze = WIERSZE if ile is None else WIERSZE[ :ile ]

	glowa = ''
	for i, n in enumerate( NAGLOWKI ) :
		sort = ' is-sorted is-asc' if 1 == i else ''
		glowa += ( '<th scope="col" role="columnheader" data-lstab-col="%d" data-lstab-align="%s"%s>'
			'<button type="button" class="lstab-sort%s"><span class="lstab-sort-label">%s</span>'
			'<span class="lstab-sort-icon" aria-hidden="true"></span></button></th>'
			% ( i, ROWNANIE[ i ], ' aria-sort="ascending"' if 1 == i else '', sort, html.escape( n ) ) )

	cialo = ''
	for w in wiersze:
		styl = REGULY.get( w[ 4 ], '' )
		cialo += '<tr role="row" class="lstab-row"' + ( ' style="' + styl + '"' if styl else '' ) + '>'
		for i, k in enumerate( w ):
			cialo += ( '<td role="cell" data-label="%s" data-lstab-align="%s">'
				'<span class="lstab-cell-label">%s</span><span class="lstab-cell-value">%s</span></td>'
				% ( html.escape( NAGLOWKI[ i ] ), ROWNANIE[ i ], html.escape( NAGLOWKI[ i ] ), html.escape( k ) ) )
		cialo += '</tr>'

	filtry = ( '<div class="lstabp-facets"><span class="lstabp-facets-label">Show only:</span>'
		+ facet( 'Room', [ ( 'Main hall', 3 ), ( 'Hall B', 2 ), ( 'Studio', 3 ) ] )
		+ facet( 'Status', [ ( 'Open', 4 ), ( 'Waitlist', 2 ), ( 'Full', 2 ) ] ) + '</div>' )

	eksport = ( '<p class="lstabp-export">'
		'<a class="lstabp-export-button" href="#" rel="nofollow">Download for Excel</a>'
		'<a class="lstabp-export-button" href="#" rel="nofollow">Download CSV</a>'
		'<button type="button" class="lstabp-export-button">Print</button></p>' )

	return ( '<div class="lstab-container"><div class="lstab lstab-style-midnight lstab-cols-5'
		' lstab-sticky-first lstab-sticky-head lstab-mieta">'
		+ filtry +
		'<div class="lstab-controls">'
		'<label class="lstab-search"><input type="search" class="lstab-search-input" placeholder="Search&hellip;" value=""></label>'
		'<span class="lstab-count">' + str( len( wiersze ) ) + ' of 8 rows</span></div>'
		'<p class="lstab-caption">Programme &mdash; day one</p>'
		'<div class="lstab-scroll" tabindex="0" role="region" aria-label="Table, scrollable sideways">'
		'<table class="lstab-table" role="table"><thead role="rowgroup"><tr role="row">' + glowa + '</tr></thead>'
		'<tbody role="rowgroup">' + cialo + '</tbody></table></div>'
		+ eksport +
		'<div class="lstab-foot"><p class="lstab-meta">Updated 4 mins ago</p></div>'
		'</div></div>' )

strona = '''<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Example table</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Inria+Serif:ital,wght@0,300;0,400&display=swap">
<style>
''' + CSS + '''

/* ---- Kolory ustawione w ekranie „Wygląd" wtyczki: mięta zamiast błękitu,
       tło pod kolor strony. To nie jest inny arkusz stylów, tylko te same
       pokrętła, które dostaje każdy użytkownik. ---- */
.lstab-mieta {
	--lstab-bg: #0d1513;
	--lstab-head-bg: #121d1b;
	--lstab-sticky-bg: #0d1513;
	--lstab-fg: #e9f4f1;
	--lstab-fg-muted: #9fb8b4;
	--lstab-fg-faint: #85a09c;
	--lstab-head-fg: #9fb8b4;
	--lstab-border: #1f2e2b;
	--lstab-border-strong: #334743;
	--lstab-stripe: #101a18;
	--lstab-hover: #16302c;
	--lstab-accent: #5fe3cf;
	--lstab-shadow: 0 2px 10px rgba( 0, 0, 0, .5 ), 0 0 0 1px rgba( 95, 227, 207, .08 );
}
.lstab-mieta .lstab-table thead th {
	background-image: linear-gradient( to bottom, #16241f, #121d1b );
	border-bottom-color: #334743;
	box-shadow: inset 0 -1px 0 #334743;
}
.lstab-mieta .lstab-search-input { background-color: #121d1b; border-color: #2a3b38; color: var( --lstab-fg ); }

body { margin: 0; background: #232a29; color: #e9f4f1;
	font-family: "IBM Plex Sans", system-ui, sans-serif;
	background-image: linear-gradient( to right, rgba( 255, 255, 255, .035 ) 1px, transparent 1px ),
		linear-gradient( to bottom, rgba( 255, 255, 255, .035 ) 1px, transparent 1px );
	background-size: 88px 44px; }
.owijka { max-width: 1240px; margin: 0 auto; padding: 54px 24px 70px; }
h1 { font-family: "Inria Serif", Georgia, serif; font-weight: 300; font-size: 44px; margin: 0 0 10px; letter-spacing: -.015em; }
.wstep { font-size: 20px; line-height: 1.5; color: #b3c6c3; max-width: 60ch; margin: 0 0 34px; }
.metka { font-family: "IBM Plex Mono", monospace; font-size: 14px; letter-spacing: .14em;
	text-transform: uppercase; color: #8fa5a2; margin: 46px 0 14px; }
.waska { width: 390px; max-width: 100%; }
.uwaga { font-size: 14px; color: #8fa5a2; margin: 12px 0 0; }
</style></head><body><div class="owijka">
<h1>The example table</h1>
<p class="wstep">The same eight rows, set up the way the plugin lets you set them up: its own colours, rules that paint a row, filters your visitors can use, a pinned first column and downloads under the table.</p>

<p class="metka">Na stronie</p>
''' + tabela() + '''
<p class="uwaga">Wiersze &bdquo;Full&rdquo; i &bdquo;Waitlist&rdquo; maluje regu&#322;a z Pro &mdash; kolor liczy si&#281; na serwerze, wi&#281;c jest ju&#380; w kodzie strony, kt&#243;ry dostaje odwiedzaj&#261;cy.</p>

<p class="metka">W w&#261;skiej kolumnie &mdash; sk&#322;ada si&#281; w karty</p>
<div class="waska">''' + tabela( ile = 3, karty = True ) + '''</div>
</div></body></html>'''

pathlib.Path( 'tabela-ladna.html' ).write_text( strona )
print( 'ok', len( strona ) )
