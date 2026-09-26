# Strona pokazowa: jeden arkusz Google, pokazany tak, jak wygląda po ustawieniu
# wtyczki. Każda tabela na tej stronie to kod zdjęty z prawdziwego renderu
# (landing/szlaki/zbierz.php) i narysowany prawdziwym arkuszem stylów wtyczki.
import json, pathlib, re

TU  = pathlib.Path( __file__ ).parent
CSS = pathlib.Path( '/home/user/111/live-sheets-table/assets/css/lstab-table.css' ).read_text()
JS  = pathlib.Path( '/home/user/111/live-sheets-table/assets/js/lstab-table.js' ).read_text()
M   = json.loads( ( TU / 'markup.json' ).read_text() )

def przygotuj( html ):
	# Suwak dostaje szerokość od skryptu; ta ze zbierania policzona jest dla
	# innego okna, więc niech policzy swoją.
	html = re.sub( r'(<div class="lstab-scrollbar-thumb"[^>]*?) style="[^"]*"', r'\1', html )
	# Nagłówki idące za ekranem mają sens na stronie z jedną tabelą; tutaj jest
	# ich osiem jedna pod drugą.
	html = html.replace( ' lstab-sticky-head', '' )
	# Na żywej stronie filtry, pobieranie i kamery prowadzą na serwer albo w
	# świat. Ta strona jest pokazem, więc odsyłacze nigdzie nie prowadzą —
	# wygląd i znacznikowanie zostają takie same.
	html = re.sub( r'href="(?!#)[^"]*"', 'href="#"', html )
	return html

W = { k: przygotuj( v ) for k, v in M[ 'warianty' ].items() }
P = { k: przygotuj( v ) for k, v in M[ 'pokretla' ].items() }

def sekcja( klasa, tresc ):
	# Jedna owijka na sekcję, a nie szerokość nakładana na każde dziecko z
	# osobna: nagłówek jest elementem, a nie klasą, więc przy tym drugim
	# sposobem jako jedyny dostawał wyśrodkowanie i stał o trzydzieści pikseli
	# dalej niż wszystko nad nim.
	return '<section class="sek ' + klasa + '"><div class="owijka">' + tresc + '</div></section>'

def naglowek( metka, tytul, opis ):
	return ( '<p class="metka">' + metka + '</p><h2>' + tytul + '</h2>'
		+ ( '<p class="opis">' + opis + '</p>' if opis else '' ) )

CO_ROBI_CO = [
	( 'Reguła koloru', 'Status jest „Zamknięty” — pomaluj cały wiersz. Trzy zamknięte szlaki widać, zanim ktokolwiek zacznie czytać.' ),
	( 'Pigułka', 'Ta sama reguła w cichszej postaci: wartość zostaje wartością, dostaje tylko obwódkę. Sortowanie i szukanie czytają „Otwarty”, nie kolor.' ),
	( 'Kropka', 'Najcichsza z trzech. Kolumna trudności nie potrzebuje kolorowych pasków, żeby dało się po niej przejechać wzrokiem.' ),
	( 'Słupek', 'Długość to udział w najgłębszym śniegu w kolumnie, liczony od zera. Liczba zostaje liczbą — da się po niej sortować i pobrać ją do Excela.' ),
	( 'Przycisk', 'Kolumna z adresami staje się kolumną przycisków, w wybranym kolorze tła i tekstu. Dwa szlaki nie mają kamery i tam przycisku nie ma: przycisk donikąd jest gorszy niż puste miejsce.' ),
	( 'Filtry i pobieranie', 'Nad tabelą wybór po trudności i statusie, pod nią Excel, CSV i wydruk. Jedno i drugie dla odwiedzającego, nie dla redaktora.' ),
]

lista = '<dl class="co-robi">' + ''.join(
	'<div class="pozycja"><dt>' + n + '</dt><dd>' + o + '</dd></div>' for n, o in CO_ROBI_CO ) + '</dl>'

SZABLONY = [
	( 'cards', 'Karty', 'papier', 'Każdy wiersz osobno, a między nimi widać stronę. Na telefonie tak wygląda każdy szablon — tu wygląda tak od razu.' ),
	( 'contrast', 'Kontrast', 'papier', 'Pełny pasek nagłówków i mocniejsza pierwsza kolumna. Tabela do pokazania na ekranie w sali.' ),
	( 'editorial', 'Redakcyjny', 'papier', 'Szeryfowe nagłówki, cienkie linie, kreska zamykająca listę. Tabela do czytania, nie do klikania.' ),
	( 'terminal', 'Terminal', 'noc', 'Jedna szerokość znaku, wersaliki, mięta na prawie czarnym. Cyfry ustawiają się w kolumnę same z siebie.' ),
]

szablony = ''
for klucz, nazwa, tlo, opis in SZABLONY:
	szablony += sekcja( tlo, naglowek( 'Szablon', nazwa, opis ) + W[ klucz ] )

POKRETLA = [
	( 'ciasno', 'Ciasno, pełna siatka', 'Wysokość wiersza: ciasno. Linie: pełna siatka.' ),
	( 'zwykle', 'Tak, jak przychodzi', 'Obydwa pokrętła zostawione w spokoju — decyduje szablon.' ),
	( 'luzno', 'Luźno, bez linii', 'Wysokość wiersza: luźno. Linie: żadne.' ),
]

pokretla = ''
for klucz, nazwa, opis in POKRETLA:
	pokretla += ( '<p class="etykieta">' + nazwa + ' <span class="cicho">— ' + opis + '</span></p>'
		+ '<div class="probka">' + P[ klucz ] + '</div>' )

STRONA = '''<title>Szlaki prosto z arkusza</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Inria+Serif:ital,wght@0,300;0,400&display=swap">
<style>
''' + CSS + '''

/* ------------------------------------------------------------ strona */

:root {
	color-scheme: dark;
	--noc: #0b1210;
	--noc-2: #101a18;
	--papier: #f7f5ef;
	--papier-2: #efece2;
	--tusz: #e9f4f1;
	--tusz-cichy: #93aca8;
	--tusz-ciemny: #16231f;
	--tusz-ciemny-cichy: #5c6b66;
	--mieta: #5fe3cf;
	--mieta-ciemna: #17695a;
	--linia: rgba( 233, 244, 241, .12 );
}

body {
	margin: 0;
	background: var( --noc );
	color: var( --tusz );
	font-family: "IBM Plex Sans", system-ui, sans-serif;
	font-size: 18px;
	line-height: 1.55;
	-webkit-font-smoothing: antialiased;
}

.sek { padding: 64px 16px; }
.owijka { max-width: 1180px; margin-inline: auto; }
.noc { background: var( --noc-2 ); color-scheme: dark; }
/* Kartka papieru położona na ciemnej stronie: szablony, które są dla światła,
   dostają światło. color-scheme ustawione wprost, bo same tabele o nie pytają
   — przez light-dark() w arkuszu wtyczki. */
.papier { background: var( --papier ); color: var( --tusz-ciemny ); color-scheme: light; }
.papier .metka { color: var( --mieta-ciemna ); }
.papier .opis, .papier .etykieta { color: var( --tusz-ciemny-cichy ); }
.papier h2 { color: var( --tusz-ciemny ); }
.papier + .papier { padding-top: 0; }

h1, h2 { font-family: "Inria Serif", Georgia, serif; font-weight: 300; text-wrap: balance; }
h1 { font-size: 54px; line-height: 1.04; letter-spacing: -.02em; margin: 0 0 18px; }
h2 { font-size: 32px; line-height: 1.15; margin: 0 0 10px; }
.metka { font-family: "IBM Plex Mono", monospace; font-size: 14px; letter-spacing: .16em;
	text-transform: uppercase; color: var( --mieta ); margin: 0 0 10px; }
.opis { font-size: 18px; color: var( --tusz-cichy ); max-width: 68ch; margin: 0 0 28px; }
.wstep { font-size: 20px; color: var( --tusz-cichy ); max-width: 60ch; margin: 0 0 6px; }
.etykieta { font-family: "IBM Plex Mono", monospace; font-size: 14px; letter-spacing: .1em;
	text-transform: uppercase; color: var( --tusz-cichy ); margin: 34px 0 10px; }
.cicho { text-transform: none; letter-spacing: 0; opacity: .75; }

/* Góra strony: mgła nad doliną, i szyba położona na niej. */
.gora {
	position: relative;
	padding: 92px 16px 76px;
	/* Mgła nad doliną: dwa światła i jedno zimne pasmo pod spodem. Szyba ma
	   przez co patrzeć, więc kolor musi sięgać tam, gdzie stoi tabela. */
	background:
		radial-gradient( 90% 60% at 8% 4%, #1f6f63 0%, rgba( 31, 111, 99, 0 ) 62% ),
		radial-gradient( 70% 55% at 92% 26%, #3a4f86 0%, rgba( 58, 79, 134, 0 ) 60% ),
		radial-gradient( 120% 70% at 50% 108%, #123a3a 0%, rgba( 18, 58, 58, 0 ) 65% ),
		linear-gradient( 168deg, #10201f 0%, #0b1210 78% );
}
.gora::after {
	content: "";
	position: absolute;
	inset: auto 0 0 0;
	height: 1px;
	background: linear-gradient( to right, transparent, var( --linia ), transparent );
}
.gora .wstep { margin-bottom: 34px; }

.co-robi { display: grid; grid-template-columns: repeat( auto-fit, minmax( 300px, 1fr ) );
	gap: 26px 40px; margin: 0; }
.co-robi .pozycja { display: grid; gap: 6px; }
.co-robi dt { font-family: "IBM Plex Mono", monospace; font-size: 14px; letter-spacing: .12em;
	text-transform: uppercase; color: var( --mieta ); }
.co-robi dd { margin: 0; font-size: 18px; color: var( --tusz-cichy ); }

/* Ekran telefonu jest oknem, nie kartką: dziesięć kart jedna pod drugą
   rozciągnęłoby tę sekcję na dwa ekrany, a i tak widać już po trzech, jak to
   działa. Reszta jest ucięta tak, jak ucina ją telefon. */
.telefon { width: 390px; max-width: 100%; margin: 0; max-height: 680px; overflow: hidden;
	-webkit-mask-image: linear-gradient( to bottom, #000 76%, transparent 99% );
	mask-image: linear-gradient( to bottom, #000 76%, transparent 99% ); }
/* Próbka pokrętła jest o odstępach, nie o danych: kilka pierwszych wierszy
   mówi wszystko, a trzy pełne tabele jedna pod drugą mówią to trzy razy. */
.probka { max-height: 330px; overflow: hidden;
	-webkit-mask-image: linear-gradient( to bottom, #000 80%, transparent 99% );
	mask-image: linear-gradient( to bottom, #000 80%, transparent 99% ); }
.telefon-rama { border: 1px solid var( --linia ); border-radius: 26px; padding: 14px;
	background: var( --noc-2 ); width: fit-content; max-width: 100%; box-sizing: border-box; }

.koniec { font-size: 14px; color: var( --tusz-cichy ); border-top: 1px solid var( --linia );
	padding-top: 22px; margin-top: 8px; }

@media ( max-width: 640px ) {
	h1 { font-size: 36px; }
	h2 { font-size: 26px; }
	.sek, .gora { padding-left: 16px; padding-right: 16px; }
	.sek { padding-top: 44px; padding-bottom: 44px; }
	.gora { padding-top: 56px; }
}
</style>

<section class="gora"><div class="owijka">
<p class="metka">Arkusz Google · odświeża się sam</p>
<h1>Szlaki prosto z arkusza</h1>
<p class="wstep">Dziesięć wierszy w arkuszu i nic poza tym. Kropki, pigułki, słupki i przyciski niżej ustawia się w kokpicie,
a liczy na serwerze — odwiedzający dostaje gotową tabelę, a nie kręcące się kółeczko.</p>
''' + W[ 'glass' ] + '''
</div></section>

''' + sekcja( 'noc', naglowek( 'Co robi co', 'Sześć rzeczy nad tą tabelą', 'Wszystkie z ekranu „Wygląd”, żadna nie wymaga wiersza kodu.' ) + lista ) + '''

''' + sekcja( 'noc', naglowek( 'Ten sam arkusz', 'Dziewięć szablonów, jedno źródło', 'Szablon to klasa na tabeli. Dane, reguły i wygląd kolumn zostają takie same — zmienia się tylko to, co widać.' ) ) + szablony + '''

''' + sekcja( 'papier', naglowek( 'Dwa pokrętła', 'Gęstość i linie osobno', 'Niezależne od szablonu i od siebie. Ta sama tabela w szablonie Kontrast, trzy razy.' ) + pokretla ) + '''

''' + sekcja( 'noc', naglowek( 'Na telefonie', 'Tabela składa się w karty', 'Nie dlatego, że okno jest wąskie, tylko dlatego, że wąska jest kolumna, w której tabela stoi — więc działa to także w pasku bocznym. Każda wartość dostaje swoją nazwę.' )
	+ '<div class="telefon-rama"><div class="telefon">' + W[ 'cards' ] + '</div></div>'
	+ '<p class="koniec">Odsyłacze na tej stronie nigdzie nie prowadzą — filtry, pobieranie i kamery działają na żywej stronie, przez serwer. Wszystko inne to prawdziwy kod wtyczki, wraz z jej arkuszem stylów i skryptem: sortowanie i szukanie w tabelach wyżej naprawdę działają.</p>' ) + '''

<script>''' + JS + '''</script>'''

( TU / 'SZLAKI.html' ).write_text( STRONA )
print( 'ok', len( STRONA ) )
