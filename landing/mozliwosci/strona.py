# -*- coding: utf-8 -*-
"""
Podstrona „what it does” — jak wtyczka działa i co potrafi, na jednej stronie.

Nic tu nie jest przepisane ręcznie: tabela to kod z prawdziwego renderu
(landing/mozliwosci/zbierz.php), a trzy zrzuty to prawdziwy kokpit na tym samym
źródle (landing/mozliwosci/zrzuty.mjs). Moduł niesie w sobie arkusz stylów i
skrypt wtyczki, więc sortowanie, szukanie i składanie w karty dzieją się na
stronie sprzedażowej tak samo jak u klienta.

Język strony jest językiem strony głównej, a nie własnym:

* arkusz jako metafora — nagłówek to pasek kolumn, kroki to komórki w wierszu,
  a droga arkusza na stronę to wiersz formuły pod nim;
* para „płyta z tekstem + okienko” na przemian raz z jednej, raz z drugiej
  strony — dokładnie to, co robi sekcja „dwie minuty” na stronie głównej;
* okienka mają belkę z trzema oczkami i mono nazwą, a ekran w nich jest
  wyraźnie ciemniejszy od strony;
* mięta 95 227 207, płyta rgba( 13 18 17 / .62 ), mono IBM Plex.

Sama tabela też jest w kolorach strony: szablon Północ wybrany, a potem
odmalowany próbnikami wtyczki — bo to jest dokładnie to, co wtyczka obiecuje.

Adresy zrzutów zaczynają się od ADRES. Wgrywasz trzy pliki z zrzuty/ do
Multimediów, kopiujesz adres folderu i podmieniasz ADRES jeden raz.

UWAGA przy edycji: każdy znacznik musi zostać w jednej linijce. Divi wstawia
w miejscu złamanego wiersza <br />, co rozbija znacznik. Wewnątrz <style>
i <script> Divi nic nie rusza. Dlatego kod tabeli jest zbijany do jednej linii,
a moduł ma „br { display: none }”.
"""

import json
import pathlib
import hashlib
import re
import sys

TU  = pathlib.Path( __file__ ).parent

# Sekcja „How it works” mieszka w landing/arkusz/ i jest stamtąd brana w całości,
# ze znacznikowaniem i arkuszem stylów. Dwie kopie tego samego CSS w dwóch
# plikach to dwie okazje, żeby zmienić jedną i zapomnieć o drugiej.
sys.path.insert( 0, str( TU.parent / 'arkusz' ) )
import arkusz
CSS = pathlib.Path( '/home/user/111/live-sheets-table/assets/css/lstab-table.css' ).read_text()
JS  = pathlib.Path( '/home/user/111/live-sheets-table/assets/js/lstab-table.js' ).read_text()
M   = json.loads( ( TU / 'markup.json' ).read_text() )
ZRZUTY = { z[ 'nazwa' ]: z for z in json.loads( ( TU / 'zrzuty' / 'rozmiary.json' ).read_text() ) }

L, P = '&#91;', '&#93;'

# I nawias otwierający we własnym znaczniku.
#
# Same encje nie wystarczają. Divi zapisuje treść modułu po swojemu i potrafi
# zamienić „&#91;" z powrotem na „[" — a wtedy WordPress widzi prawdziwy
# shortcode i WYKONUJE go. Na żywej stronie zamiast przykładu do przeczytania
# stanął komunikat wtyczki, w dodatku po polsku na angielskiej stronie.
# Zmiana jedynki na iks by tego nie zdjęła: wykonuje się nazwa, a nie numer.
#
# Shortcode rozpoznaje się po nazwie STYKAJĄCEJ SIĘ z nawiasem, więc znacznik
# między jednym a drugim wyklucza dopasowanie. Był tu pusty <span>, ale edytor
# Divi pusty znacznik po cichu wyrzuca i shortcode znów się wykonał. Nawias
# w środku znacznika sprawia, że nie jest pusty i zostaje. Dla czytającego
# i dla kopiującego nic się nie zmienia.
NAWIAS = '<span class="lst-mz-nawias">' + L + '</span>'


def dla_divi( html ):
	"""Kod tabeli tak, żeby przeżył wklejenie w moduł Kod."""
	# Odstęp między znacznikami znika w całości — inaczej Divi wstawi w każdym
	# złamaniu wiersza <br />, a w środku znacznika łamie go na pół.
	html = re.sub( r'>\s+<', '><', html )
	html = re.sub( r'\s*\n\s*', ' ', html )
	# Suwak pod tabelą dostaje szerokość od skryptu; ta z renderu policzona jest
	# dla innego okna.
	html = re.sub( r'(<div class="lstab-scrollbar-thumb"[^>]*?) style="[^"]*"', r'\1', html )
	# Nagłówki idące za ekranem mają sens na stronie z jedną tabelą; tu pod nią
	# jest jeszcze jedna kopia w ramce telefonu.
	html = html.replace( ' lstab-sticky-head', '' )
	# Filtry, pobieranie i kamery prowadzą na serwer albo w świat. To jest pokaz
	# na stronie sprzedażowej, więc odsyłacze nigdzie nie idą.
	html = re.sub( r'href="(?!#)[^"]*"', 'href="#"', html )
	return html.strip()


TABELA = dla_divi( M[ 'pro' ] )


# W ramce telefonu ta sama tabela co wyżej, w tej samej kolejności: od
# pierwszego wiersza arkusza, tak jak zobaczy ją czytelnik na telefonie.

# Ikonki legendy: 24 x 24, rysowane kreska, bez wypelnienia poza tym, co ma
# klase `jest-pelna`. Ten sam zestaw regul co w landing/droga, zeby cala
# witryna miala jedna reke.
IKONY = {
	'szukaj':   '<circle cx="11" cy="11" r="6.4"/><path d="M15.8 15.8L20.5 20.5"/>',
	'kolory':   '<rect x="3.5" y="3.5" width="7" height="7" rx="1.6"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.6"/>'
	            '<rect x="3.5" y="13.5" width="7" height="7" rx="1.6" class="jest-pelna"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.6"/>',
	'odsylacz': '<path d="M10.4 13.6a3.7 3.7 0 0 0 5.2 0l2.7-2.7a3.7 3.7 0 0 0-5.2-5.2l-1.3 1.3"/>'
	            '<path d="M13.6 10.4a3.7 3.7 0 0 0-5.2 0l-2.7 2.7a3.7 3.7 0 0 0 5.2 5.2l1.3-1.3"/>',
	'odswiez':  '<path d="M20 12a8 8 0 1 1-2.6-5.9"/><path d="M20 4v4.6h-4.6"/>',
	'telefon':  '<rect x="6.8" y="2.6" width="10.4" height="18.8" rx="2.6"/><path d="M10.4 18.4h3.2"/>',
	# Trzy paski jeden na drugim zlewaly sie przy 15 px w jeden kloc. Tabela
	# z jednym pomalowanym wierszem w srodku czyta sie od razu.
	# Dwa paski: jeden zwykly, drugi pomalowany. Trzy elementy w tej ikonce
	# zlewaly sie przy 16 px w jeden kloc, bo kreska zjadala przerwy.
	'wiersz':   '<rect x="3" y="6.2" width="18" height="4.4" rx="1.5"/>'
	            '<rect x="3" y="13.4" width="18" height="4.4" rx="1.5" class="jest-pelna"/>',
	'pigulka':  '<rect x="2.6" y="8" width="12.6" height="8" rx="4"/><circle cx="19.6" cy="12" r="1.7" class="jest-pelna"/>',
	# Trzy poziome kreski to ikonka wyrownania do lewej, a nie slupkow.
	'slupki':   '<rect x="3.4" y="12" width="4.4" height="7.6" rx="1.2" class="jest-pelna"/>'
	            '<rect x="9.8" y="6.2" width="4.4" height="13.4" rx="1.2"/>'
	            '<rect x="16.2" y="9.4" width="4.4" height="10.2" rx="1.2"/>',
	'lejek':    '<path d="M3.6 5h16.8l-6.5 7.5v5.6l-3.8 2.3v-7.9z"/>',
}


# ( ikona, poziom, tytul, opis )
LEGENDA = [
	( 'szukaj', 'Free', 'Search and sorting',
	  'Type in the box and the table keeps only the rows that match, with the matching words marked. '
	  'Click any column heading to sort by it. Dates sort as dates and times as times, not as text.' ),
	( 'kolory', 'Free', 'All the colours are settings',
	  'Pick one of nine ready-made looks, then change whatever you want: the background, the heading bar, '
	  'the text, the lines, the row height, the size of the column names. Colour pickers and dropdowns, '
	  'nothing else. This table was made that way and nobody wrote any CSS for it.' ),
	( 'odsylacz', 'Free', 'Web addresses become links',
	  'Put a web address in a column and your visitors get something they can click, instead of a long '
	  'line of text to copy out by hand. In the table above that column is wearing the Pro button look, '
	  'but the clickable link itself is free.' ),
	( 'odswiez', 'Free', 'It checks the sheet for you',
	  'This table looks at the spreadsheet every 15 minutes, and the line underneath tells visitors when '
	  'it last looked. If Google is slow or unreachable, the page keeps showing the last copy it got, so '
	  'nobody lands on an empty table.' ),
	( 'telefon', 'Free', 'It works on phones',
	  'Make the window narrow and watch what happens. Each row turns into its own card, with the column '
	  'name written next to every value. Nobody has to drag a six-column table sideways on a phone.' ),
	( 'wiersz', 'Pro', 'Colour a whole row',
	  'Set a rule: when <em>Status</em> says <em>Closed</em>, paint that row red. The three closed trails '
	  'stand out straight away, before anyone has read a word.' ),
	( 'pigulka', 'Pro', 'Or just a badge, or a dot',
	  'The same kind of rule, only quieter. The value gets a coloured badge around it, or just a small '
	  'dot next to it. The text stays text, so search and sorting still work on it.' ),
	( 'slupki', 'Pro', 'Bars and buttons in a column',
	  'A number can show a bar behind it. The longer the bar, the bigger that number is next to the '
	  'biggest one in the same column. It is still a real number underneath: it sorts, and it goes into '
	  'the download. Web addresses can become buttons with your own wording on them. Cells with nothing '
	  'in them stay empty.' ),
	( 'lejek', 'Pro', 'Filters and downloads',
	  'Above the table, dropdowns your visitors use to narrow it down themselves. Underneath, buttons for '
	  'Excel, CSV and print. A download holds exactly what is on the screen: only the rows left after '
	  'filtering, and none of the hidden columns.' ),
]

# ( plik, tytuł, zdanie, nazwa okna, trzy krótkie linijki pod spodem )
EKRANY = [
	( 'mz-wyglad', 'Ten styles, then the dials',
	  'Pick a style, then disagree with it: colours one by one, text size, row height and how many lines '
	  'the table draws. Everything you leave alone keeps following the style.',
	  'Appearance',
	  [ 'ten whole-table styles', 'six colour wells, each optional',
	    'text size, row height, lines, corners' ] ),
	( 'mz-reguly', 'A rule reads like a sentence',
	  'When <em>Status</em> is <em>Closed</em>, paint <em>the whole row</em>. No formulas, no code, and '
	  'the colours are worked out on the server, so they are already in the page a visitor receives.',
	  'Colour rules',
	  [ 'the cell, the row, the words, a pill or a dot',
	    'is, is not, more than, contains',
	    'as many rules as the sheet needs' ] ),
	( 'mz-kolumny', 'A column can wear something',
	  'Give a column of numbers a bar, or a column of links a button, in the colours you choose and '
	  'saying what you tell it to say.',
	  'Column looks',
	  [ 'a bar behind a number', 'a button where the cell holds a link',
	    'a badge, or a colour down the whole column' ] ),
]

TELEFON = [
	'No zooming, no scrolling sideways',
	'Search, sorting and filters still work',
	'Nothing to set up, it happens on its own',
	'Every detail shows its column heading',
]

WOLNE = [
	( 'The table itself', [
		'Every row your sheet has. No cap at 30, 50 or 100.',
		'A real table in the page code, so Google and screen readers see it.',
		'Search, sorting and pages.',
	] ),
	( 'How it looks', [
		'Three table styles, plus colours, text size, row height, lines and corners.',
		'Cards on a narrow screen, decided by the column, not the window.',
		'A pinned first column and headings that follow the screen down.',
	] ),
	( 'On your page', [
		'Rename, hide, reorder and align columns without touching the sheet.',
		'Your own CSS per table, and a block, an Elementor widget or a shortcode.',
	] ),
]

PRO = [
	( 'More of everything', [
		'As many sheets as you like, checked as often as every minute.',
		'Seven more styles: Cards, Terminal, Glass, Ledger, Contrast, Midnight, Editorial.',
	] ),
	( 'Colour and column looks', [
		'Colour rules: the cell, the whole row, just the words, a pill or a dot.',
		'Column looks: a bar behind a number, a button where there is a link.',
	] ),
	( 'For your visitors', [
		'Filters your visitors use themselves.',
		'Excel, CSV and print, holding exactly what the page shows.',
		'An expandable panel under each row, for the columns that do not fit.',
		'Private sheets, through a Google connection of your own.',
	] ),
]

KROPKI = ( '<span class="lst-mz-kropka"></span><span class="lst-mz-kropka"></span>'
	'<span class="lst-mz-kropka"></span>' )


def etykieta( tekst ):
	"""Mała metka mono nad tytułem sekcji.

	Na całej stronie są DWIE i ani jednej więcej. Metka nad każdym nagłówkiem
	to rytm, który każda strona składana maszynowo ma tak samo: po trzech
	sekcjach przestaje cokolwiek znaczyć, bo miejsce sekcji na stronie i tak
	mówi, czym ona jest. Zostały tam, gdzie naprawdę coś dokładają: pierwsza
	sekcja (nazywa całą podstronę) i tabela (mówi, że to dzieje się teraz).
	"""
	return '<p class="lst-mz-etykieta">' + tekst + '</p>'


def naglowek( tekst ):
	"""Tytuł sekcji: szeryfowy, skalujący się z oknem, z kreską do krawędzi.

	Przedtem każda sekcja miała tylko metkę mono 14 px, więc na całej stronie
	nie było ani jednego napisu większego niż 20 px. Strona była płaska jak
	dokument. Tytuły sekcji na tej witrynie są w Inria Serif i skalują się
	z szerokością okna (tak mówi landing/README.md) — to jedyne miejsce, gdzie
	wolno wyjść poza 14, 18 i 20.
	"""
	return '<h2 class="lst-mz-naglowek">' + tekst + '</h2>'


def pozycja( ikona, tier, tytul, opis ):
	klasa = ' jest-pro' if 'Pro' == tier else ''
	rysunek = '<svg class="lst-mz-ikona" viewBox="0 0 24 24" aria-hidden="true">' + IKONY[ ikona ] + '</svg>'
	return ( '<div class="lst-mz-pozycja">'
		'<p class="lst-mz-znak' + klasa + '"><span class="lst-mz-znak-adres">' + rysunek + '</span>'
		'<span class="lst-mz-znak-slowo">' + tier + '</span></p>'
		'<p class="lst-mz-tytul">' + tytul + '</p>'
		'<p class="lst-mz-opis">' + opis + '</p></div>' )


# Pasek stanu telefonu: godzina i trzy ikonki. To on, bardziej niż cokolwiek
# innego, każe oku uznać prostokąt za telefon — bez niego ekran zaczyna się od
# razu treścią, czego żaden telefon nie robi.
PASEK_STANU = (
	'<div class="lst-mz-telefon-pasek" aria-hidden="true">'
	'<span class="lst-mz-telefon-godzina">9:41</span>'
	'<span class="lst-mz-telefon-ikonki">'
	'<svg class="lst-mz-ikona jest-stan" viewBox="0 0 18 12">'
	'<rect x="0" y="8.4" width="3" height="3.6" rx="1" class="jest-pelna"/>'
	'<rect x="4.6" y="6" width="3" height="6" rx="1" class="jest-pelna"/>'
	'<rect x="9.2" y="3.2" width="3" height="8.8" rx="1" class="jest-pelna"/>'
	'<rect x="13.8" y="0" width="3" height="12" rx="1" class="jest-pelna"/>'
	'</svg>'
	'<svg class="lst-mz-ikona jest-stan" viewBox="0 0 16 12">'
	'<path d="M1 4.3a10.4 10.4 0 0 1 14 0"/>'
	'<path d="M3.6 7.1a6.7 6.7 0 0 1 8.8 0"/>'
	'<circle cx="8" cy="10.2" r="1.3" class="jest-pelna"/>'
	'</svg>'
	'<svg class="lst-mz-ikona jest-stan jest-bateria" viewBox="0 0 26 12">'
	'<rect x="0.6" y="0.6" width="21" height="10.8" rx="3"/>'
	'<rect x="2.4" y="2.4" width="14" height="7.2" rx="1.8" class="jest-pelna"/>'
	'<path d="M23.6 4.4v3.2" stroke-width="2.6"/>'
	'</svg>'
	'</span>'
	'</div>' )


def telefon( srodek ):
	"""Aparat: obudowa, przyciski, wyspa z okiem, szkło i ekran.

	Wszystko, co ma znaczyć „telefon”, siedzi w sylwetce i w czterech
	szczegółach: wyspa z obiektywem, pasek stanu, odbicie na szkle i kreska
	gestu. Bez nich to zaokrąglony prostokąt wokół tabeli.
	"""
	# Korpus: dwadzieścia cztery cienkie warstwy obudowy jedna za drugą.
	# Przy dwunastu grubszych refleks na boku rozpadał się na pasy, a na
	# rogach na schodki. Kiedy aparat się
	# odchyla, ich brzegi układają się w bok telefonu — z grubością,
	# zaokrąglonymi rogami i światłem, które gaśnie w głąb.
	korpus = ''.join( '<span class="lst-mz-telefon-warstwa" style="--i:' + str( i ) + '"></span>'
		for i in range( 1, 25 ) )

	# Guziki w scenie 3D: piętnaście płaskich warstw na całą ramę, każda
	# z czterema plasterkami guzików, wsuniętych w połowę kroku między
	# warstwy korpusu (we wspólnej płaszczyźnie przeglądarka nie wie, co jest
	# z przodu, i guzik migał). Najbliższy plaster wygląda dokładnie jak
	# guzik w spoczynku, więc na wprost nic się nie zmienia.
	guziki = ''.join( '<span class="lst-mz-telefon-plastry' + ( ' jest-przod' if 5 == i else '' )
		+ '" style="--i:' + str( i ) + '">'
		'<b class="jest-cisza"></b><b class="jest-glosniej"></b>'
		'<b class="jest-ciszej"></b><b class="jest-bok"></b></span>'
		for i in range( 5, 20 ) )

	# Stojak się nie rusza. Mierzony jest on, a nie aparat: aparat w ruchu
	# zmienia kształt razem z nachyleniem, więc mierzony w ruchu goniłby sam
	# siebie i drgał na brzegach.
	return ( '<div class="lst-mz-telefon-stojak">'
		'<div class="lst-mz-telefon-rama">'
		'<span class="lst-mz-telefon-cien"></span>'
		+ korpus + guziki +
		'<span class="lst-mz-telefon-guzik jest-cisza"></span>'
		'<span class="lst-mz-telefon-guzik jest-glosniej"></span>'
		'<span class="lst-mz-telefon-guzik jest-ciszej"></span>'
		'<span class="lst-mz-telefon-guzik jest-bok"></span>'
		'<div class="lst-mz-telefon">' + PASEK_STANU + srodek + '</div>'
		'<span class="lst-mz-telefon-wyspa"><span class="lst-mz-telefon-oko"></span></span>'
		'<span class="lst-mz-telefon-blysk"></span>'
		'<span class="lst-mz-telefon-kreska"></span>'
		'</div>'
		'</div>' )


def okno( nazwa, srodek, prawa = '', klasa = '' ):
	"""Ramka udająca okno: belka z oczkami i nazwą, a pod nią ekran."""
	po_prawej = ( '<span class="lst-mz-belka-prawa">' + prawa + '</span>' ) if prawa else ''
	return ( '<div class="lst-mz-okno' + klasa + '"><div class="lst-mz-belka">' + KROPKI +
		'<span class="lst-mz-nazwa-okna">' + nazwa + '</span>' + po_prawej + '</div>'
		+ srodek + '</div>' )


def para_tresc( gora, linie ):
	"""Płyta z tekstem: nagłówek u góry, krótkie linijki u dołu.

	Dwa bloki, nie jeden ciąg — luz w wierszu ląduje MIĘDZY nimi, więc płyta
	wygląda na złożoną, a nie na taką, której zabrakło treści. To jest ta sama
	usterka, którą widać było na pierwszy rzut oka: wysoki zrzut obok trzech
	zdań i pół ekranu pustki pod nimi.
	"""
	return '<div class="lst-mz-para-gora">' + gora + '</div>' + punkty( linie )


def punkty( linie ):
	return ( '<ul class="lst-mz-punkty">'
		+ ''.join( '<li>' + x + '</li>' for x in linie ) + '</ul>' )


def para( tresc, prawa, odwrocona, klasa = '' ):
	"""Wiersz strony głównej: płyta z tekstem i okienko obok niej."""
	return ( '<div class="lst-mz-para' + klasa + ( ' jest-odwrocona' if odwrocona else '' ) + '">'
		'<div class="lst-mz-para-tresc">' + tresc + '</div>' + prawa + '</div>' )


def podglad( plik, nazwa ):
	z = ZRZUTY[ plik ]
	return ( '<div class="lst-mz-podglad"><img src="ADRES/' + plik + '.png" alt="' + nazwa + '" '
		'width="' + str( z[ 'w' ] ) + '" height="' + str( z[ 'h' ] ) + '" decoding="async"></div>' )


def ekran( plik, tytul, opis, nazwa, linie, odwrocony ):
	tresc = para_tresc(
		'<p class="lst-mz-adres">' + nazwa + '</p>'
		'<p class="lst-mz-tytul">' + tytul + '</p>'
		'<p class="lst-mz-opis">' + opis + '</p>', linie )
	return para( tresc, okno( 'Dashboard &rsaquo; ' + nazwa, podglad( plik, nazwa ) ), odwrocony )


def lista( tytul, grupy, klasa = '' ):
	"""Lista w grupach, a nie osiem punktów jednym ciągiem.

	Osiem wypunktowań pod rząd czyta się jak lista rzeczy do zrobienia i nikt
	nie dochodzi do końca. Te same osiem pozycji w trzech nazwanych grupach
	czyta się jak trzy rzeczy — a kto szuka konkretu, wie, w której grupie go
	szukać. Ani jedna pozycja nie zniknęła.
	"""
	srodek = ''

	for nazwa, pozycje in grupy:
		srodek += ( '<div class="lst-mz-grupa"><p class="lst-mz-grupa-nazwa">' + nazwa + '</p>'
			'<ul class="lst-mz-lista">'
			+ ''.join( '<li>' + x + '</li>' for x in pozycje ) + '</ul></div>' )

	return ( '<div class="lst-mz-kolumna' + klasa + '"><p class="lst-mz-kolumna-tytul">' + tytul + '</p>'
		'<div class="lst-mz-grupy">' + srodek + '</div></div>' )


# Sekcja z tabelą, wyjęta do zmiennej, bo wychodzi w dwóch miejscach: w całej
# podstronie i jako osobny moduł do wklejenia gdzie indziej. Jedno źródło, więc
# nie da się poprawić jednego i zapomnieć o drugim.
BLOK_STOL = (
	'<div class="lst-mz-blok lst-mz-stol">'
	'<p class="lst-mz-etykieta jest-zywa"><span class="lst-mz-puls"></span>Live on this page</p>'
	+ naglowek( 'Set it up once, then forget it' ) +
	'<p class="lst-mz-opis lst-mz-opis-stolu">The sheet changes, the page changes. No logging in, '
	'no pasting rows, no asking the developer. Set it up once and go back to whatever you were '
	'doing.</p>'
	+ okno( 'Example table, built with the plugin', '<div class="lst-mz-plansza">' + TABELA + '</div>',
		'10 rows from one Google Sheet', ' jest-stolem' ) +
	'</div>' )


# Dwa bloki wyjęte do zmiennych z tego samego powodu co BLOK_STOL: wychodzą
# i w całej podstronie, i osobno, do wklejenia gdzie indziej. Jedno źródło.
BLOK_LEGENDA = (
	'<div class="lst-mz-blok">'
	+ naglowek( 'What to look for' ) +
	'<p class="lst-mz-wstep">Nine things on the table above. Five of them are in the free plugin.</p>'
	'<div class="lst-mz-legenda">' + ''.join( pozycja( *p ) for p in LEGENDA ) + '</div>'
	'</div>' )

BLOK_SKAD = (
	'<div class="lst-mz-blok">'
	+ naglowek( 'Where it comes from' ) +
	'<p class="lst-mz-wstep">Three screens from the dashboard, on the very sheet above.</p>'
	'<div class="lst-mz-pary">'
	+ ''.join( ekran( *e, odwrocony = bool( i % 2 ) ) for i, e in enumerate( EKRANY ) ) +
	'</div>'
	'</div>' )


BLOK_TELEFON = (
	'<div class="lst-mz-blok">'
	+ naglowek( 'On a phone, every row becomes a card' ) +
	'<div class="lst-mz-pas">'
	'<div class="lst-mz-pas-bok">'
	'<p class="lst-mz-opis">No zooming, no scrolling sideways. On a phone every row becomes a card you '
	'can read at a glance, with search and filters right above it. It is the same table, so there is '
	'nothing extra to set up or maintain. Most of your visitors read on a phone, and they get the same '
	'up-to-date data as everyone else.</p>'
	'</div>'
	+ telefon( TABELA ) +
	'<div class="lst-mz-pas-bok jest-prawy">'
	'<p class="lst-mz-adres">On a phone</p>'
	+ punkty( TELEFON ) +
	'</div>'
	'</div>'
	'</div>' )

BLOK_LISTY = (
	'<div class="lst-mz-blok">'
	+ naglowek( 'What is in which' ) +
	'<div class="lst-mz-listy">'
	+ lista( 'In the free plugin', WOLNE )
	+ lista( 'Everything above, plus Pro', PRO, ' jest-pro' ) +
	'</div>'
	'<p class="lst-mz-kod"><span class="lst-mz-mono">' + NAWIAS + 'sheet_table id=&quot;X&quot;' + P + '</span>'
	'<span class="lst-mz-kod-opis">A block, an Elementor widget or this. The same table either way.</span>'
	'<a class="lst-mz-cta" href="ADRES-POBIERANIA">Download free</a></p>'
	'</div>' )


SEKCJA = (
	# --- jak to działa: wiersz arkusza i wiersz formuły pod nim -------------
	'<div class="lst-mz-blok">'
	+ etykieta( 'How it works' )
	+ naglowek( 'Three steps, and then it looks after itself' )
	+ arkusz.sekcja() +
	'</div>'

	# --- tabela na żywo, w takim samym okienku jak zrzuty niżej -------------
	+ BLOK_STOL

	# --- co na niej widać --------------------------------------------------
	+ BLOK_LEGENDA

	# --- skąd się to bierze ------------------------------------------------
	+ BLOK_SKAD

	# --- telefon, w tym samym wierszu co wszystko wyżej ---------------------
	+ BLOK_TELEFON

	# --- co jest w czym ----------------------------------------------------
	+ BLOK_LISTY
)


STYL = r"""
/* ---------------------------------------------------------------- moduł */

/*
 * Wartości są te same co w sekcjach strony głównej (landing/dwie-minuty,
 * landing/naglowek): ta podstrona ma wyglądać jak dalszy ciąg tamtej strony,
 * a nie jak osobna witryna.
 */
.lst-mz {
	--mz-mieta: 95, 227, 207;
	--mz-tekst: #eaf3f1;
	--mz-tekst-2: #9db3b0;
	--mz-tekst-3: #8fa5a2;
	--mz-plyta: rgba( 13, 18, 17, .62 );
	--mz-plyta-linia: rgba( 138, 168, 163, .1 );
	--mz-linia: rgba( 138, 168, 163, .22 );
	--mz-kreska: rgba( 138, 168, 163, .38 );

	/* Ekran: okienka i tabela stoją na tym samym, wyraźnie ciemniejszym od
	   strony kolorze — inaczej wyglądają jak dziury, a nie jak ekrany. */
	--mz-ekran: #0a1110;
	--mz-ekran-gora: #131d1b;
	--mz-ekran-linia: rgba( 95, 227, 207, .2 );

	--mz-mono: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;
	--mz-szeryf: "Inria Serif", "Iowan Old Style", Georgia, serif;

	/*
	 * Jeden promień na wszystko, co jest płytą albo ekranem, i jeden mniejszy
	 * na żetony. Przedtem było ich pięć: 14, 12, 5, 16 i 26 — a pięć promieni
	 * na jednej stronie to nie system, tylko pięć decyzji podjętych osobno.
	 * Ramka telefonu zostaje poza tym: telefon ma promień telefonu.
	 */
	--mz-luk-plyty: 14px;
	--mz-luk-zetonu: 6px;
	/* Mocniejszy ease-out niż wbudowany: ruch rusza od razu, a dochodzi
	   spokojnie. Wbudowane krzywe są na to za miękkie. */
	--mz-luk: cubic-bezier( .23, 1, .32, 1 );

	font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif;
	color: var( --mz-tekst );

	/*
	 * Dokładnie tyle, ile ma wiersz Divi na tej witrynie: 90% i nie więcej
	 * niż 1800 px. Moduł wychodzi z wiersza na całą szerokość okna i sam
	 * odtwarza tę kolumnę, więc jego treść staje w jednej linii ze wszystkim
	 * innym na stronie. Przedtem stało tu 1240 px — węziej niż reszta witryny,
	 * i ta podstrona wyglądała jak wklejona z innego projektu.
	 */
	--mz-szerokosc: 90%;
	--mz-max: 1800px;
	--mz-pelna: 100vw;

	width: var( --mz-pelna );
	margin: 0 calc( 50% - var( --mz-pelna ) / 2 );
	overflow-x: clip;
	padding: clamp( 1rem, 2vw, 1.6rem ) 0 clamp( 2rem, 4vw, 3.4rem );
	position: relative;
	isolation: isolate;
}

/*
 * Moduł nie maluje pod sobą tła: pod nim jest tło całej witryny — siatka,
 * która oddycha pod kursorem. Własna poświata tylko tam, gdzie leży tabela,
 * żeby strona miała środek. Rysowana pseudoelementem, nie tłem, bo utwardzenie
 * na wrogie motywy niżej zdejmuje tła, a tego jednego zdjąć nie może.
 */
.lst-mz::before {
	content: "";
	position: absolute;
	inset: 0;
	z-index: -1;
	pointer-events: none;
	background-image:
		radial-gradient( 62% 40% at 50% 30%, rgba( var( --mz-mieta ), .05 ) 0%, rgba( var( --mz-mieta ), 0 ) 70% );
}

.lst-mz.lst-mz { border: 0 !important; outline: 0 !important; background-color: transparent !important; background-image: none !important; }
.lst-mz * { box-sizing: border-box; }
.lst-mz br { display: none; }

/*
 * Reset po nazwie klasy, a nie po nazwie znacznika: pod spodem siedzi cały
 * arkusz stylów wtyczki i jego divów, spanów i odsyłaczy nie wolno tknąć.
 * Wszystko, co jest moje, ma klasę zaczynającą się od „lst-mz-”.
 */
.lst-mz [class*="lst-mz-"] {
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

.lst-mz .lst-mz-rama {
	width: var( --mz-szerokosc );
	max-width: var( --mz-max );
	margin-inline: auto;
	display: grid;
	gap: clamp( 2.4rem, 6vw, 4.2rem );
}

/* Każdy blok to jedna myśl: etykieta, rzecz, i odstęp pod spodem. */
.lst-mz .lst-mz-blok { display: grid; gap: clamp( 1rem, 2.4vw, 1.6rem ); }

.lst-mz .lst-mz-mono { font-family: var( --mz-mono ); }

.lst-mz .lst-mz-wstep,
.lst-mz .lst-mz-opis {
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.55;
	color: var( --mz-tekst-2 );
	max-width: 44rem;
}

.lst-mz .lst-mz-tytul {
	font-size: 1.25rem;    /* 20 px */
	font-weight: 600;
	line-height: 1.25;
	max-width: 34rem;
}

.lst-mz .lst-mz-opis em { font-style: normal; color: rgb( var( --mz-mieta ) ); }

.lst-mz .lst-mz-etykieta,
.lst-mz .lst-mz-adres,
.lst-mz .lst-mz-znak,
.lst-mz .lst-mz-punkty,
.lst-mz .lst-mz-nazwa-okna,
.lst-mz .lst-mz-belka-prawa,
.lst-mz .lst-mz-kolumna-tytul {
	font-family: var( --mz-mono );
	font-size: .875rem;    /* 14 px */
	line-height: 1.5;
}

/*
 * Etykieta sekcji ciągnie za sobą włoskową kreskę do prawej krawędzi. Jeden
 * znak robi tu za dwie rzeczy: dzieli stronę na rozdziały, a przy przewijaniu
 * rysuje się sam i przez to mówi, że ta sekcja właśnie weszła.
 */
.lst-mz .lst-mz-etykieta {
	display: flex;
	align-items: center;
	gap: .9rem;
	letter-spacing: .14em;
	text-transform: uppercase;
	color: rgb( var( --mz-mieta ) );
}



/*
 * Tytuł sekcji.
 *
 * Do niedawna każda sekcja miała nad sobą tylko metkę mono 14 px i nic więcej,
 * więc na całej stronie nie było ani jednego napisu większego niż dwadzieścia
 * pikseli: wszystko ważyło tyle samo i strona czytała się jak dokument, a nie
 * jak strona. Tytuły tej witryny są szeryfowe i skalują się z oknem — to
 * jedyne miejsce, w którym wolno wyjść poza 14, 18 i 20.
 *
 * Kreska, która przedtem ciągnęła się za metką, ciągnie się teraz za tytułem:
 * dzieli stronę na rozdziały i przy przewijaniu rysuje się sama, więc mówi
 * też „ta sekcja właśnie weszła”.
 */
.lst-mz .lst-mz-naglowek {
	display: flex;
	align-items: baseline;
	gap: clamp( .9rem, 2vw, 1.4rem );
	font-family: var( --mz-szeryf );
	font-weight: 400;
	font-size: clamp( 1.625rem, 2.6vw, 2.25rem );
	line-height: 1.12;
	letter-spacing: -.01em;
	color: var( --mz-tekst );
}

.lst-mz .lst-mz-naglowek::after {
	content: "";
	flex: 1 1 auto;
	min-width: 2rem;
	/*
	 * Ta sama kreska co na stronie głównej: 2 px, zaokrąglona i miętowa od
	 * mocnego końca do zera. Przedtem był tu włos o przezroczystości .3, który
	 * na tle tej witryny ledwo było widać, i podstrona wyglądała przy głównej
	 * jak wyblakła.
	 */
	height: 2px;
	border-radius: 2px;
	background-image: linear-gradient( to right,
		rgba( var( --mz-mieta ), .9 ),
		rgba( var( --mz-mieta ), .62 ) 40%,
		rgba( var( --mz-mieta ), .34 ) 75%,
		rgba( var( --mz-mieta ), .1 ) 93%,
		transparent );
	transform-origin: left center;
	/* Sam tytuł ma się łamać przed kreską, a nie razem z nią. */
	align-self: center;
}

/* Metka nad tytułem jest teraz rzadkością, więc nie ciągnie już własnej
   kreski: dwie kreski jedna nad drugą to nie akcent, tylko szum. */
.lst-mz .lst-mz-blok > .lst-mz-etykieta + .lst-mz-naglowek { margin-top: -.2rem; }

/* ------------------------------------------------------------- okienko */

/*
 * Jedno okienko na całej stronie: trzyma zrzuty z kokpitu, ekran telefonu
 * i samą tabelę. Dzięki temu tabela na żywo i zrzuty czytają się jak rzeczy
 * z jednego miejsca, a nie jak obrazek obok obrazka.
 */
.lst-mz .lst-mz-okno {
	position: relative;
	display: flex;
	flex-direction: column;
	min-width: 0;
	border: 1px solid var( --mz-ekran-linia );
	border-radius: var( --mz-luk-plyty );
	overflow: hidden;
	background-color: var( --mz-ekran );
	box-shadow: 0 22px 46px -30px rgba( 0, 0, 0, .95 ), 0 0 34px -18px rgba( var( --mz-mieta ), .3 );
	transition: transform 220ms var( --mz-luk ), box-shadow 220ms var( --mz-luk );
}

.lst-mz .lst-mz-belka {
	display: flex;
	align-items: center;
	gap: .45rem;
	flex: none;
	padding: .55rem .8rem;
	background-color: var( --mz-ekran-gora );
	border-bottom: 1px solid rgba( var( --mz-mieta ), .14 );
}

.lst-mz .lst-mz-kropka {
	width: 8px;
	height: 8px;
	flex: none;
	border-radius: 50%;
	background-color: rgba( 138, 168, 163, .35 );
}

.lst-mz .lst-mz-nazwa-okna {
	margin-left: .4rem;
	color: var( --mz-tekst-3 );
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.lst-mz .lst-mz-belka-prawa {
	margin-left: auto;
	padding-left: 1rem;
	color: var( --mz-tekst-3 );
	white-space: nowrap;
}

/*
 * Okienko ze zrzutem ma sufit.
 *
 * Zrzuty są wysokie: „Wygląd” to 1216 pikseli, czyli po przeskalowaniu do
 * połowy wiersza ponad sześćset. Wiersz robił się wtedy tak wysoki jak obraz,
 * a płyta z tekstem obok — mimo że rozciągnięta — miała trzy czwarte wysokości
 * pustej. Sufit zrównuje obie strony, a ściemnienie u dołu mówi, że ekran ma
 * dalszy ciąg. To jest uczciwsze niż kadr przycięty na sztywno: nie udaje, że
 * kokpit kończy się akurat tam.
 */
.lst-mz .lst-mz-okno:not( .jest-stolem ) { max-height: clamp( 16rem, 25vw, 22rem ); }

.lst-mz .lst-mz-podglad { flex: 1 1 auto; min-height: 0; overflow: hidden; }

.lst-mz .lst-mz-okno img {
	display: block;
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: top left;
	transition: transform 320ms var( --mz-luk );
}

/* Ściemnienie u dołu okna: „ten ekran ma dalszy ciąg”. Szerokiego nie dotyczy,
   bo tam nic nie zostało urwane i fałszywa zapowiedź dalszego ciągu kłamie. */
.lst-mz .lst-mz-okno:not( .jest-stolem ):not( .jest-szerokie )::after {
	content: "";
	position: absolute;
	inset: auto 0 0 0;
	height: 3.5rem;
	pointer-events: none;
	background-image: linear-gradient( to bottom, rgba( 10, 17, 16, 0 ), rgba( 10, 17, 16, .92 ) );
}

/* ----------------------------------------------------- tabela na żywo */

.lst-mz .lst-mz-opis-stolu { margin-top: -.6rem; max-width: 52rem; }

.lst-mz .lst-mz-etykieta.jest-zywa { display: flex; align-items: center; gap: .5rem; }

.lst-mz .lst-mz-puls {
	width: 7px;
	height: 7px;
	border-radius: 50%;
	background-color: rgb( var( --mz-mieta ) );
	box-shadow: 0 0 0 0 rgba( var( --mz-mieta ), .55 );
	/* Trzy razy i koniec. Kropka przy słowie „live” ma raz zwrócić uwagę, a nie
	   mrugać komuś nad tabelą przez cały czas, kiedy ją czyta. */
	animation: lst-mz-puls 1.8s var( --mz-luk ) 3;
}

@keyframes lst-mz-puls {
	0%   { box-shadow: 0 0 0 0 rgba( var( --mz-mieta ), .5 ); }
	70%  { box-shadow: 0 0 0 7px rgba( var( --mz-mieta ), 0 ); }
	100% { box-shadow: 0 0 0 0 rgba( var( --mz-mieta ), 0 ); }
}

/* Tabela dostaje w okienku trochę powietrza — jej własne tło jest tym samym
   ekranem, więc nie widać, gdzie kończy się okno, a zaczyna arkusz. */
.lst-mz .lst-mz-plansza { padding: clamp( .6rem, 1.4vw, 1.1rem ); }

/* ------------------------------------------------------------- legenda */

.lst-mz .lst-mz-legenda {
	display: grid;
	grid-template-columns: repeat( 3, minmax( 0, 1fr ) );
	gap: clamp( 1.1rem, 2.4vw, 1.8rem );
}

/*
 * Kafelek, a nie luźny tekst. Sześć akapitów stojących na gołym tle czyta się
 * jak lista rzeczy do zrobienia; sześć płytek czyta się jak sześć rzeczy.
 */
.lst-mz .lst-mz-pozycja {
	display: grid;
	gap: .4rem;
	align-content: start;
	padding: 1.1rem 1.25rem 1.25rem;
	background-color: var( --mz-plyta );
	border: 1px solid var( --mz-plyta-linia );
	border-radius: var( --mz-luk-plyty );
	box-shadow: inset 0 1px 0 rgba( 255, 255, 255, .045 );
	/*
	 * W tę stronę 160 ms, z powrotem 240 ms (patrz „:hover” niżej): odpowiedź
	 * natychmiastowa, powrót spokojny. Wypisane właściwości, nigdy „all”.
	 */
	transition: border-color 240ms var( --mz-luk ), box-shadow 240ms var( --mz-luk ),
		transform 240ms var( --mz-luk );
}

/*
 * Znacznik jest komórką arkusza, tak jak żetony w nagłówku strony: z lewej
 * rysunek, z prawej wartość. Przedtem po lewej stały litery kolumn, czyli
 * „A-F”, „Under it”, „Narrow it”. Przy rzeczach, które nie siedzą w żadnej
 * kolumnie, nie było czego tam wpisać i wychodziły z tego polecenia dla
 * czytelnika. Rysunek mówi to samo bez słowa i nie udaje adresu.
 */
.lst-mz .lst-mz-znak {
	display: inline-flex;
	justify-self: start;
	align-items: stretch;
	overflow: hidden;
	border: 1px solid var( --mz-linia );
	border-radius: var( --mz-luk-zetonu );
	letter-spacing: .1em;
	text-transform: uppercase;
}

.lst-mz .lst-mz-znak-adres {
	display: inline-flex;
	align-items: center;
	padding: .25rem .5rem;
	background-color: rgba( 138, 168, 163, .12 );
	border-right: 1px solid var( --mz-linia );
	color: var( --mz-tekst-3 );
}

/*
 * Kreska, nie plama: wypełnione jest tylko to, co ma „jest-pelna”, czyli
 * pomalowany wiersz i kropka. Reszta ma być tak samo lekka jak włoskowe
 * kreski pod tytułami.
 */
.lst-mz .lst-mz-ikona {
	width: 16px;
	height: 16px;
	display: block;
	fill: none;
	stroke: currentColor;
	stroke-width: 1.6;
	stroke-linecap: round;
	stroke-linejoin: round;
}

.lst-mz .lst-mz-ikona .jest-pelna { fill: currentColor; stroke: none; }

.lst-mz .lst-mz-znak-slowo { padding: .1rem .55rem; color: var( --mz-tekst-3 ); }

.lst-mz .lst-mz-znak.jest-pro { border-color: rgba( var( --mz-mieta ), .45 ); }
.lst-mz .lst-mz-znak.jest-pro .lst-mz-znak-adres { border-right-color: rgba( var( --mz-mieta ), .45 ); }
.lst-mz .lst-mz-znak.jest-pro .lst-mz-znak-slowo { color: rgb( var( --mz-mieta ) ); background-color: rgba( var( --mz-mieta ), .1 ); }

/* ------------------------------------------- para: tekst i okienko obok */

/*
 * Wiersz strony głównej, przeniesiony tutaj: płyta z tekstem po jednej
 * stronie, okienko po drugiej, co drugi wiersz odwrócony, a między wierszami
 * włoskowa kreska.
 *
 * „stretch” jest tu najważniejszą deklaracją w całym pliku. Przedtem tekst
 * stał luzem obok wysokiego zrzutu i pod nim zostawał metr pustego miejsca;
 * teraz obie strony mają tę samą wysokość, a obraz jest przycięty do niej.
 */
.lst-mz .lst-mz-pary { display: grid; }

.lst-mz .lst-mz-para {
	display: grid;
	grid-template-columns: minmax( 0, 1fr ) minmax( 0, 1fr );
	gap: clamp( 1.2rem, 3vw, 2.6rem );
	align-items: stretch;
	padding-block: clamp( 1.4rem, 2.6vw, 2.2rem );
	border-top: 1px solid var( --mz-kreska );
}

.lst-mz .lst-mz-pary > .lst-mz-para:first-child { border-top: 0; padding-top: 0; }

.lst-mz .lst-mz-para.jest-odwrocona .lst-mz-okno { order: -1; }

.lst-mz .lst-mz-para-tresc {
	display: grid;
	gap: .55rem;
	/*
	 * Nagłówek u góry, linijki u dołu, a luz między nimi. Przy „start” cały
	 * zapas wysokości zbierał się pod tekstem i płyta wyglądała na pustą w
	 * trzech czwartych — to było dokładnie to, co widać było gołym okiem.
	 */
	align-content: space-between;
	padding: 1.15rem 1.3rem 1.3rem;
	background-color: var( --mz-plyta );
	border: 1px solid var( --mz-plyta-linia );
	border-radius: var( --mz-luk-plyty );
	box-shadow: inset 0 1px 0 rgba( 255, 255, 255, .045 );
}

.lst-mz .lst-mz-para-gora { display: grid; gap: .55rem; align-content: start; }

/* Trzy krótkie linijki pod zdaniem: płyta ma mieć treść, a nie samo zdanie
   i pustkę pod nim. Mono, bo to są nazwy ustawień, a nie proza. */
.lst-mz .lst-mz-punkty {
	display: grid;
	gap: .3rem;
	margin-top: .35rem;
	padding-top: .7rem;
	border-top: 1px solid var( --mz-plyta-linia );
	color: var( --mz-tekst-3 );
}

.lst-mz .lst-mz-punkty li {
	position: relative;
	padding-left: 1.05rem;
	list-style: none;
}

.lst-mz .lst-mz-punkty li::before {
	content: "";
	position: absolute;
	left: 0;
	top: .62em;
	width: .35rem;
	height: 1px;
	background-color: rgba( var( --mz-mieta ), .7 );
}

/* --------------------------------------------------- telefon: pas z aparatem */

/*
 * Telefon nie jest kolejną parą „płyta i okienko”.
 *
 * Trzy takie pary stoją wyżej i to jest jeden szereg, ten sam, który robi
 * sekcja „dwie minuty” na stronie głównej. Czwarta para pod nimi nie byłaby
 * już szeregiem, tylko przyzwyczajeniem. Tu aparat stoi w środku, a tekst po
 * obu jego stronach: inny układ, a przy okazji telefon przestaje być dodatkiem
 * obok zdania i staje się przedmiotem sekcji.
 */
.lst-mz .lst-mz-pas {
	display: grid;
	grid-template-columns: minmax( 0, 1fr ) auto minmax( 0, 1fr );
	gap: clamp( 1.2rem, 2.6vw, 2.2rem );
	align-items: center;
	padding-top: clamp( .6rem, 1.4vw, 1rem );
}

.lst-mz .lst-mz-pas-bok { display: grid; gap: .6rem; align-content: center; }

/* Obie strony wyrównane tak samo: jedna do środka, druga do góry wygląda
   jak dwie decyzje podjęte osobno. */

.lst-mz .lst-mz-pas-bok .lst-mz-punkty { margin-top: 0; padding-top: 0; border-top: 0; }

/* -------------------------------------------------------------- telefon */

.lst-mz .lst-mz-wasko { display: none; }

.lst-mz .lst-mz-telefon-stojak {
	position: relative;
	display: block;
	width: fit-content;
	max-width: 100%;
	/* Room underneath for the shadow it casts on the floor. */
	padding-bottom: 34px;
}

/*
 * Pod aparatem: cień na podłodze i poświata za nim. Zdjęcie produktu stoi na
 * czymś; aparat zawieszony w pustce, bez cienia pod sobą, wygląda na
 * wycięty z kartki. Cień jest na stojaku, który się nie rusza, a oddycha
 * w takt unoszenia: gdy aparat idzie w górę, cień rozmywa się i blednie.
 */
.lst-mz .lst-mz-telefon-stojak::before,
.lst-mz .lst-mz-telefon-stojak::after {
	content: "";
	position: absolute;
	pointer-events: none;
	z-index: -1;
}

.lst-mz .lst-mz-telefon-stojak::before {
	inset: 6% -18% 14% -18%;
	background: radial-gradient( 50% 46% at 50% 52%, rgba( var( --mz-mieta ), .2 ), rgba( var( --mz-mieta ), .07 ) 45%, rgba( var( --mz-mieta ), 0 ) 72% );
	filter: blur( 8px );
}

.lst-mz .lst-mz-telefon-stojak::after {
	left: 9%;
	right: 13%;
	bottom: 0;
	height: 46px;
	background: radial-gradient( 50% 50% at 50% 50%, rgba( 0, 0, 0, .72 ), rgba( 0, 0, 0, .32 ) 45%, rgba( 0, 0, 0, 0 ) 72% );
	animation: lst-mz-cien 7s ease-in-out infinite alternate;
}

@keyframes lst-mz-cien {
	from { scale: 1; opacity: 1; }
	to { scale: .86; opacity: .72; }
}

@keyframes lst-mz-unos {
	from { translate: 0 0; }
	to { translate: 0 -10px; }
}

/*
 * Aparat.
 *
 * Telefon poznaje się po sylwetce, a dopiero potem po szczegółach, więc
 * najpierw sylwetka: wysoki, bardzo mocno zaokrąglony, z ramką ekranu równej
 * szerokości ze wszystkich czterech stron. Potem cztery szczegóły, które
 * robią resztę: metalowy bok łapiący światło, wyspa z obiektywem, pasek stanu
 * nad treścią i odbicie na szkle.
 *
 * Gradienty są tu wyjątkiem od utwardzenia na wrogie motywy, które zdejmuje
 * wszystkim elementom modułu „background-image". Bez nich bok telefonu jest
 * płaską plamą, a metalu nie da się udać samym cieniem. Wyjątek jest wąski
 * i wypisany z nazwy na dole, w bloku utwardzenia.
 */
.lst-mz .lst-mz-telefon-rama {
	--mz-fon-bok: linear-gradient( 145deg,
		#5a6f6a 0%, #2c3b38 12%, #1a2422 34%,
		#161f1d 62%, #2a3936 84%, #536862 100% );
	--mz-fon-szklo: linear-gradient( 128deg,
		rgba( 255, 255, 255, .11 ) 0%,
		rgba( 255, 255, 255, .045 ) 14%,
		rgba( 255, 255, 255, 0 ) 34%,
		rgba( 255, 255, 255, 0 ) 100% );

	/*
	 * Poza w spoczynku: na wprost. Próbowana była poza bokiem do patrzącego,
	 * z bokiem rysowanym cieniem — i przy każdym najechaniu i zjechaniu było
	 * widać, jak narysowany bok ustępuje prawdziwemu i z powrotem. Na wprost
	 * bok i tak chowa się za przodem, więc scena 3D włącza się i gaśnie
	 * niewidocznie, a grubość wychodzi dopiero z ruchem, płynnie.
	 */
	--mz-poza-x: 0deg;
	--mz-poza-y: 0deg;

	--mz-fon-krawedz:
		/* krawędź metalu: jasna u góry, ciemna u dołu */
		inset 0 1.5px 0 rgba( 255, 255, 255, .3 ),
		inset 0 -1.5px 0 rgba( 0, 0, 0, .5 ),
		inset 1.5px 0 0 rgba( 255, 255, 255, .16 ),
		inset -1.5px 0 0 rgba( 255, 255, 255, .08 ),
		/* wąski połysk na szlifie tuż przy szybie */
		inset 0 0 0 4px rgba( 255, 255, 255, .025 );

	position: relative;
	width: fit-content;
	max-width: 100%;
	padding: 12px;
	border-radius: 52px;
	background-color: #1a2422;
	background-image: var( --mz-fon-bok );
	/* Cień pod aparatem rzuca osobna warstwa, patrz „lst-mz-telefon-cien”. */
	box-shadow: var( --mz-fon-krawedz );
	animation: lst-mz-unos 7s ease-in-out infinite alternate;

	/*
	 * Najechanie jak w bibliotece Steama: aparat odchyla się w stronę kursora
	 * i lekko unosi, a po szkle przesuwa się blask. Kąty i położenie blasku
	 * wpisuje skrypt; tu jest tylko to, jak za nimi nadąża. Powrót jest
	 * wolniejszy niż wejście, żeby aparat odkładał się, a nie odskakiwał.
	 */
	/*
	 * Grubość. Telefon ma bok, a nie tylko przód: za ekranem stoją dwadzieścia
	 * cztery warstwy obudowy, co półtora piksela z ćwiercią, razem
	 * czterdzieści dwa. To
	 * mniej więcej proporcja prawdziwego aparatu przy tej szerokości.
	 */
	--mz-krok: 1.75;
	--mz-grubosc: calc( var( --mz-krok ) * 1px );
	/*
	 * W spoczynku aparat jest płaski, bez sceny 3D. Scena z kilkunastoma
	 * warstwami jest dla przeglądarki ciężka: przy przewijaniu Chrome
	 * składał ją kaflami i dolny kafel potrafił wyjść jasnym prostokątem
	 * z ostrymi rogami, szerszym niż sam aparat. Głębię włącza skrypt
	 * klasą „jest-3d” na czas najechania i zdejmuje, gdy aparat się odłoży.
	 */
	transform-style: flat;
	transform: perspective( 1400px )
		rotateX( var( --mz-nachyl-x, var( --mz-poza-x ) ) )
		rotateY( var( --mz-nachyl-y, var( --mz-poza-y ) ) )
		translateY( var( --mz-uniesienie, 0px ) );
	/*
	 * Bez przejścia na transform: ruch prowadzi skrypt, klatka po klatce,
	 * z wygładzeniem. Przejście CSS przy każdym ruchu myszy zaczynało od
	 * nowa i przy szybkim ruchu aparat szarpał.
	 */
}

/* Ta sama poza i ta sama perspektywa, tylko już jako scena z warstwami:
   przejście z jednego w drugie nie może być widać. */
.lst-mz .lst-mz-telefon-rama.jest-3d {
	transform-style: preserve-3d;
}

.lst-mz .lst-mz-telefon-cien {
	position: absolute;
	inset: 0;
	border-radius: 52px;
	pointer-events: none;
	box-shadow:
		/* aparat leży na stronie, a nie jest w nią wpuszczony */
		0 2px 2px -1px rgba( 0, 0, 0, .5 ),
		0 36px 70px -36px rgba( 0, 0, 0, .95 ),
		0 0 70px -30px rgba( var( --mz-mieta ), .4 );
	transition: box-shadow 420ms var( --mz-luk );
}

/*
 * W scenie 3D cień stoi za całą bryłą, za najdalszą warstwą. Rzucany
 * przez samą ramę leżałby w płaszczyźnie przodu, przed guzikami, które są
 * w głębi, i przyciemniał je w chwili najechania.
 */
.lst-mz .lst-mz-telefon-rama.jest-3d .lst-mz-telefon-cien {
	transform: translateZ( calc( 24.5 * var( --mz-grubosc ) * -1 ) )
		scale( calc( 1 + 24.5 * var( --mz-krok ) / 1400 ) );
}

.lst-mz .lst-mz-telefon-rama.jest-nad .lst-mz-telefon-cien {
	box-shadow:
		0 2px 2px -1px rgba( 0, 0, 0, .5 ),
		0 50px 90px -40px rgba( 0, 0, 0, 1 ),
		0 0 80px -28px rgba( var( --mz-mieta ), .5 );
}

/*
 * Warstwy korpusu. Każda kolejna dalej w głąb i odrobinę ciemniejsza, więc
 * bok czyta się jak metal oświetlony od przodu. Jasny, nie czarny: ciemny bok
 * zlewał się z ciemną stroną i grubości nie było widać wcale. W spoczynku
 * ich nie ma wcale (patrz „jest-3d” wyżej); stają za przodem dopiero, gdy
 * aparat zaczyna się odchylać.
 */
.lst-mz .lst-mz-telefon-warstwa {
	display: none;
	position: absolute;
	/* O piksel w głąb obrysu: na wprost żaden brzeg warstwy nie wyjrzy zza
	   przodu nawet półprzezroczystym pikselem wygładzania. */
	inset: 1px;
	border-radius: 51px;
	pointer-events: none;
	background-color: #2a3835;
	/* Ten sam profil co bok w spoczynku: najjaśniej na środku zaokrąglenia. */
	background-color: color-mix( in srgb, #b3c5c1 calc( 100% - max( calc( ( var( --i ) - 12 ) * 7.5% ), calc( ( 12 - var( --i ) ) * 7.5% ) ) ), #1a2422 );
	box-shadow: inset 0 0 0 1px rgba( 255, 255, 255, .04 );
	/*
	 * Perspektywa zmniejsza to, co dalej, w stronę środka ramy. Na wprost
	 * guzik w głębi chował się przez to za ramą i w chwili najechania
	 * zamiast rąbka zostawała blada kreska. Każda warstwa jest więc
	 * powiększona dokładnie o tyle, o ile perspektywa (1400 px) ją zmniejsza:
	 * na wprost cała bryła rzutuje się jak płaski aparat w spoczynku, co do
	 * piksela, i przejście w scenę 3D jest niewidoczne.
	 */
	transform: translateZ( calc( var( --i ) * var( --mz-grubosc ) * -1 ) )
		scale( calc( 1 + var( --i ) * var( --mz-krok ) / 1400 ) );
}

/* Czarna szczelina między metalem a ekranem: na prawdziwym aparacie to ona
   oddziela jedno od drugiego i bez niej ekran wygląda jak naklejka. */
.lst-mz .lst-mz-telefon-rama::after {
	content: "";
	position: absolute;
	inset: 10px;
	border-radius: 42px;
	border: 2px solid #05100e;
	pointer-events: none;
}

/* ----------------------------------------------------------------- ekran */

.lst-mz .lst-mz-telefon {
	position: relative;
	/* 390 px: szerokość dzisiejszego telefonu, a nie sprzed dekady. */
	width: 390px;
	max-width: 100%;
	/* Wyżej niż przedtem: przy 1,68 aparat czytał się jak mały tablet. */
	height: clamp( 42rem, 52vw, 48rem );
	overflow: hidden;
	/* Trochę luzu na boki: treść dotykająca krawędzi szkła wygląda na uciętą,
	   a na prawdziwym ekranie nic nie leży przy samej krawędzi. */
	padding-inline: 7px;
	border-radius: 40px;
	background-color: var( --mz-ekran );
	-webkit-mask-image: linear-gradient( to bottom, #000 78%, transparent 99% );
	mask-image: linear-gradient( to bottom, #000 78%, transparent 99% );
}

/* --------------------------------------------------------- pasek stanu */

/*
 * Godzina po lewej, zasięg, wi-fi i bateria po prawej, a między nimi miejsce
 * na wyspę. Dziewiąta czterdzieści jeden, bo tak chodzą zegary na wszystkich
 * zdjęciach tego aparatu i oko nie zatrzymuje się na tym ani na chwilę.
 */
.lst-mz .lst-mz-telefon-pasek {
	display: flex;
	align-items: center;
	justify-content: space-between;
	height: 44px;
	padding: 0 14px 0 18px;
	color: #eef7f4;
}

.lst-mz .lst-mz-telefon-godzina {
	font-size: .875rem;    /* 14 px */
	font-weight: 600;
	line-height: 1;
	letter-spacing: .01em;
}

.lst-mz .lst-mz-telefon-ikonki { display: inline-flex; align-items: center; gap: 5px; }

.lst-mz .lst-mz-ikona.jest-stan { width: auto; height: 11px; stroke-width: 1.5; }
.lst-mz .lst-mz-ikona.jest-stan.jest-bateria { height: 12px; }

/* ----------------------------------------------------------------- wyspa */

/*
 * Wyspa leży NA ekranie, a pasek stanu ma dokładnie jej wysokość, więc nic
 * się pod nią nie chowa. W środku obiektyw: ciemne oko z cienkim, zimnym
 * pierścieniem i jednym punktem światła. To ten punkt sprawia, że wyspa
 * przestaje być czarną pigułką.
 */
.lst-mz .lst-mz-telefon-wyspa {
	position: absolute;
	z-index: 3;
	top: 24px;
	left: 50%;
	transform: translateX( -50% );
	display: flex;
	align-items: center;
	justify-content: flex-end;
	width: 92px;
	height: 26px;
	padding-right: 7px;
	border-radius: 999px;
	background-color: #000;
	box-shadow: inset 0 0 0 1px rgba( 255, 255, 255, .06 );
}

.lst-mz .lst-mz-telefon-oko {
	position: relative;
	width: 11px;
	height: 11px;
	border-radius: 50%;
	background-color: #0b1a19;
	box-shadow:
		inset 0 0 0 1px rgba( 120, 200, 190, .28 ),
		inset 0 0 3px 1px rgba( 0, 0, 0, .9 );
}

.lst-mz .lst-mz-telefon-oko::after {
	content: "";
	position: absolute;
	top: 2px;
	left: 2.4px;
	width: 3px;
	height: 3px;
	border-radius: 50%;
	background-color: rgba( 180, 240, 230, .55 );
}

/* ------------------------------------------------------------------ szkło */

/* Odbicie: jedno miękkie pasmo światła z lewego górnego rogu. Nic więcej,
   bo szkło, po którym biegnie pięć refleksów, wygląda jak kalkomania. */
.lst-mz .lst-mz-telefon-blysk {
	position: absolute;
	z-index: 4;
	inset: 12px;
	border-radius: 40px;
	pointer-events: none;
	background-image: var( --mz-fon-szklo );
	box-shadow: inset 0 0 0 1px rgba( 255, 255, 255, .055 );
}

/*
 * Blask, który idzie za kursorem: miękkie białe światło w miejscu, nad którym
 * jest wskaźnik. Na pseudoelemencie, bo jego przezroczystość da się płynnie
 * zgasić, a gradientu sterowanego zmiennymi już nie.
 */
.lst-mz .lst-mz-telefon-blysk::after {
	content: "";
	position: absolute;
	inset: 0;
	border-radius: inherit;
	background-image: radial-gradient( 300px circle at var( --mz-blask-x, 50% ) var( --mz-blask-y, 20% ),
		rgba( 255, 255, 255, .16 ), rgba( 255, 255, 255, .05 ) 38%, rgba( 255, 255, 255, 0 ) 70% );
	opacity: 0;
	transition: opacity 420ms ease;
}

.lst-mz .lst-mz-telefon-rama.jest-nad .lst-mz-telefon-blysk::after { opacity: 1; transition-duration: 180ms; }

/* Kreska gestu u dołu ekranu. */
.lst-mz .lst-mz-telefon-kreska {
	position: absolute;
	z-index: 5;
	bottom: 20px;
	left: 50%;
	transform: translateX( -50% );
	width: 134px;
	height: 5px;
	border-radius: 999px;
	background-color: rgba( 234, 243, 241, .4 );
}

/* --------------------------------------------------------------- guziki */

/*
 * Cztery, tak jak na aparacie: przełącznik ciszy i dwa klawisze głośności po
 * lewej, przycisk boczny po prawej.
 *
 * Każdy jest bryłką na boku, a nie paskiem na obudowie. Z przodu widać z niej
 * tylko rąbek wystający zza krawędzi: wąski, ciemniejszy od ramy, bo bok
 * odwraca się od światła, z jasną linią po stronie, z której pada światło, i
 * z zaokrąglonymi końcami. To jest guzik w spoczynku; w scenie 3D zastępują
 * go plasterki niżej, a najbliższy z nich wygląda dokładnie tak samo.
 */
.lst-mz .lst-mz-telefon-guzik {
	position: absolute;
	left: -3px;
	width: 3px;
	border-radius: 2px 0 0 2px;
	background-color: #33433f;
	box-shadow:
		/* jasna linia na zewnętrznej krawędzi, od światła */
		inset 1px 0 0 rgba( 255, 255, 255, .42 ),
		/* końce schodzą w cień, bo są zaokrąglone */
		inset 0 4px 3px -3px rgba( 0, 0, 0, .55 ),
		inset 0 -4px 3px -3px rgba( 0, 0, 0, .55 ),
		/* metal boku, ciemniejszy niż front ramy */
		inset 0 0 0 6px #4f625e,
		/* cień tam, gdzie guzik wychodzi z ramy */
		-1px 0 1.5px rgba( 0, 0, 0, .45 );
}

/*
 * Guziki w scenie 3D: piętnaście warstw na całą ramę, w każdej cztery
 * plasterki. Plasterki stoją w połowie kroku między warstwami korpusu —
 * w tej samej płaszczyźnie co korpus przeglądarka nie umiała rozstrzygnąć,
 * co jest z przodu, i na guzikach skakały ząbki. Plasterek wchodzi dwa
 * piksele w korpus, żeby między guzikiem a bokiem nie było szczeliny.
 * Warstwa wyrównuje perspektywę tak samo jak korpus (patrz wyżej), więc
 * guzik na wprost leży dokładnie tam, gdzie płaski rąbek w spoczynku,
 * a najbliższy plasterek wygląda jak on.
 */
.lst-mz .lst-mz-telefon-plastry {
	display: none;
	position: absolute;
	inset: 0;
	pointer-events: none;
	transform-style: preserve-3d;
	transform: translateZ( calc( ( var( --i ) + .5 ) * var( --mz-grubosc ) * -1 ) )
		scale( calc( 1 + ( var( --i ) + .5 ) * var( --mz-krok ) / 1400 ) );
}

.lst-mz .lst-mz-telefon-plastry > b {
	position: absolute;
	left: -3px;
	width: 5px;
	border-radius: 2px 0 0 2px;
	background-color: color-mix( in srgb, #9fb2ae calc( 100% - max( calc( ( var( --i ) - 12 ) * 7.5% ), calc( ( 12 - var( --i ) ) * 7.5% ) ) ), #1a2422 );
	box-shadow:
		inset 0 1px 0 rgba( 255, 255, 255, .22 ),
		inset 0 -1px 0 rgba( 0, 0, 0, .4 );
}

.lst-mz .lst-mz-telefon-plastry > b.jest-bok {
	left: auto;
	right: -3px;
	top: 176px;
	height: 92px;
	border-radius: 0 2px 2px 0;
}

/* Najbliższy plasterek to przód guzika: ten sam co rąbek w spoczynku. */
.lst-mz .lst-mz-telefon-plastry.jest-przod > b {
	background-color: #33433f;
	box-shadow:
		inset 1px 0 0 rgba( 255, 255, 255, .42 ),
		inset 0 4px 3px -3px rgba( 0, 0, 0, .55 ),
		inset 0 -4px 3px -3px rgba( 0, 0, 0, .55 ),
		inset 0 0 0 6px #4f625e,
		-1px 0 1.5px rgba( 0, 0, 0, .45 );
}

.lst-mz .lst-mz-telefon-plastry.jest-przod > b.jest-bok {
	box-shadow:
		inset -1px 0 0 rgba( 255, 255, 255, .3 ),
		inset 0 4px 3px -3px rgba( 0, 0, 0, .55 ),
		inset 0 -4px 3px -3px rgba( 0, 0, 0, .55 ),
		inset 0 0 0 6px #4a5c58,
		1px 0 1.5px rgba( 0, 0, 0, .45 );
}

/* W scenie 3D bryłę robią warstwy; płaski rąbek ustępuje plasterkom. */
.lst-mz .lst-mz-telefon-rama.jest-3d .lst-mz-telefon-warstwa,
.lst-mz .lst-mz-telefon-rama.jest-3d .lst-mz-telefon-plastry { display: block; }
.lst-mz .lst-mz-telefon-rama.jest-3d .lst-mz-telefon-guzik { visibility: hidden; }


.lst-mz .lst-mz-telefon-guzik.jest-cisza,
.lst-mz .lst-mz-telefon-plastry > b.jest-cisza { top: 104px; height: 28px; }
.lst-mz .lst-mz-telefon-guzik.jest-glosniej,
.lst-mz .lst-mz-telefon-plastry > b.jest-glosniej { top: 150px; height: 56px; }
.lst-mz .lst-mz-telefon-guzik.jest-ciszej,
.lst-mz .lst-mz-telefon-plastry > b.jest-ciszej { top: 218px; height: 56px; }

/* Po prawej wszystko w lustrze: rąbek na prawo, ściana obraca się w drugą stronę. */
.lst-mz .lst-mz-telefon-guzik.jest-bok {
	left: auto;
	right: -3px;
	top: 176px;
	height: 92px;
	border-radius: 0 2px 2px 0;
	box-shadow:
		inset -1px 0 0 rgba( 255, 255, 255, .3 ),
		inset 0 4px 3px -3px rgba( 0, 0, 0, .55 ),
		inset 0 -4px 3px -3px rgba( 0, 0, 0, .55 ),
		inset 0 0 0 6px #4a5c58,
		1px 0 1.5px rgba( 0, 0, 0, .45 );
}


/* --------------------------------------------------------- co jest w czym */

.lst-mz .lst-mz-listy {
	display: grid;
	grid-template-columns: repeat( auto-fit, minmax( 20rem, 1fr ) );
	gap: clamp( 1.2rem, 3vw, 2.2rem );
}

.lst-mz .lst-mz-kolumna {
	display: grid;
	gap: 0;
	align-content: start;
	overflow: hidden;
	background-color: var( --mz-plyta );
	border: 1px solid var( --mz-plyta-linia );
	border-radius: var( --mz-luk-plyty );
	box-shadow: inset 0 1px 0 rgba( 255, 255, 255, .045 );
	transition: border-color 200ms ease;
}

.lst-mz .lst-mz-kolumna.jest-pro { border-color: rgba( var( --mz-mieta ), .28 ); }

/* Tytuł listy siedzi w pasku jak nagłówek kolumny w arkuszu. */
.lst-mz .lst-mz-kolumna-tytul {
	padding: .55rem 1.35rem;
	background-color: var( --mz-ekran-gora );
	border-bottom: 1px solid var( --mz-plyta-linia );
	letter-spacing: .12em;
	text-transform: uppercase;
	color: var( --mz-tekst-3 );
}

.lst-mz .lst-mz-kolumna.jest-pro .lst-mz-kolumna-tytul { color: rgb( var( --mz-mieta ) ); }

/*
 * Osiem pozycji w trzech nazwanych grupach zamiast jednego ciągu wypunktowań.
 * Ciąg ośmiu kropek czyta się jak lista rzeczy do zrobienia i nikt nie dochodzi
 * do końca; trzy grupy czyta się jak trzy rzeczy, a kto szuka konkretu, wie,
 * w której grupie szukać.
 */
.lst-mz .lst-mz-grupy { display: grid; padding: 1.1rem 1.35rem 1.35rem; gap: 1rem; }

.lst-mz .lst-mz-grupa { display: grid; gap: .5rem; }

.lst-mz .lst-mz-grupa + .lst-mz-grupa { padding-top: 1rem; border-top: 1px solid var( --mz-plyta-linia ); }

.lst-mz .lst-mz-grupa-nazwa {
	font-size: .875rem;
	font-weight: 600;
	line-height: 1.4;
	color: var( --mz-tekst );
}

.lst-mz .lst-mz-lista { display: grid; gap: .55rem; margin: 0; padding: 0; list-style: none; }

.lst-mz .lst-mz-lista li {
	position: relative;
	margin: 0;
	padding-left: 1.4rem;
	list-style: none;
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.5;
	color: var( --mz-tekst-2 );
}

.lst-mz .lst-mz-lista li::before {
	content: "";
	position: absolute;
	left: .15rem;
	top: .62em;
	width: .42rem;
	height: .42rem;
	border-radius: 50%;
	background-color: rgba( var( --mz-mieta ), .55 );
}

.lst-mz .lst-mz-kolumna.jest-pro .lst-mz-lista li::before { background-color: rgb( var( --mz-mieta ) ); }

/* ------------------------------------------------------------ shortcode */

/*
 * Pasek z przykładem: kod, opis, a przycisk na samym końcu. Układ to siatka,
 * a nie „margin-left: auto” — utwardzenie zeruje marginesy na boki i przycisk
 * stawał zaraz za opisem, z pustą połową paska za sobą. Przycisk siedzi
 * w pasku z tym samym odstępem z góry, z dołu i z prawej, a pasek jest
 * zaokrąglony tak, żeby jego łuk biegł równo z łukiem przycisku.
 */
.lst-mz .lst-mz-kod {
	--mz-kod-wcisk: .5rem;
	display: grid;
	grid-template-columns: auto minmax( 0, 1fr ) auto;
	align-items: center;
	column-gap: 1.5rem;
	padding: var( --mz-kod-wcisk ) var( --mz-kod-wcisk ) var( --mz-kod-wcisk ) 1.5rem;
	background-color: var( --mz-plyta );
	border: 1px solid var( --mz-plyta-linia );
	border-radius: 999px;
	font-size: .875rem;
}

/* Wąsko trzy rzeczy w jednym wierszu się nie mieszczą: jedna pod drugą,
   przycisk na całą szerokość, pasek z promieniem płyty. */
@media ( max-width: 760px ) {
	.lst-mz .lst-mz-kod {
		--mz-kod-wcisk: .75rem;
		grid-template-columns: minmax( 0, 1fr );
		row-gap: .6rem;
		padding: 1rem 1.1rem 1.1rem;
		border-radius: var( --mz-luk-plyty );
	}
	.lst-mz .lst-mz-kod .lst-mz-cta { margin-top: .4rem; text-align: center !important; }
}

.lst-mz .lst-mz-kod .lst-mz-mono { font-family: var( --mz-mono ); color: rgb( var( --mz-mieta ) ); }
.lst-mz .lst-mz-kod-opis { color: var( --mz-tekst-3 ); font-size: .875rem; }

/*
 * Jedno wezwanie na całej podstronie, i to na samym jej końcu: kto doczytał aż
 * tutaj, wie już wszystko, czego się dowie. Napis jest ten sam co na stronie
 * głównej, bo dwa różne napisy na to samo działanie to dwa działania w głowie
 * czytającego. Ciemny tusz na miętowym tle, czyli to samo, co robi przycisk
 * w nagłówku witryny.
 */
.lst-mz .lst-mz-cta {
	padding: .55rem 1.35rem;
	border-radius: 999px;
	background-color: rgb( var( --mz-mieta ) );
	color: #06100f;
	font-size: 1.125rem;
	font-weight: 600;
	line-height: 1.3;
	white-space: nowrap;
	text-decoration: none;
	transition: transform 160ms var( --mz-luk ), filter 160ms ease;
}

.lst-mz .lst-mz-cta:active { transform: translateY( 1px ); }

/* ------------------------------------------------------------- najechanie */

@media ( hover: hover ) and ( pointer: fine ) {
	/*
	 * Karta jest pod kursorem dziesiątki razy na jednej stronie, więc ruch ma
	 * być ledwie wyczuwalny: trzy piksele w górę, cień, który je uzasadnia,
	 * i cieplejsza krawędź. Samo przesunięcie bez cienia wyglądało jak skok.
	 */
	.lst-mz .lst-mz-pozycja:hover {
		border-color: rgba( var( --mz-mieta ), .34 );
		transform: translateY( -3px );
		box-shadow: inset 0 1px 0 rgba( 255, 255, 255, .07 ),
			0 18px 30px -26px rgba( 0, 0, 0, .95 ),
			0 0 26px -18px rgba( var( --mz-mieta ), .5 );
		transition-duration: 160ms;
	}
	.lst-mz .lst-mz-kolumna:hover { border-color: rgba( var( --mz-mieta ), .34 ); }
	.lst-mz .lst-mz-cta:hover { filter: brightness( 1.08 ); transform: translateY( -1px ); }
	.lst-mz .lst-mz-okno:not( .jest-stolem ):hover { transform: translateY( -2px ); box-shadow: 0 26px 50px -30px rgba( 0, 0, 0, .95 ), 0 0 40px -16px rgba( var( --mz-mieta ), .4 ); }
	.lst-mz .lst-mz-okno:not( .jest-stolem ):hover img { transform: scale( 1.012 ); }
}

/* --------------------------------------------- ruch, który niesie przewijanie */

/*
 * Wjazd sekcji przy przewijaniu.
 *
 * Trzy zasady, wszystkie wymuszone, a nie deklarowane.
 *
 * Po pierwsze: NIGDY NIE SCHODZI DO ZERA PRZEZROCZYSTOŚCI. Wjazd z „opacity: 0”
 * tu już kiedyś stał i test pokazał dziewiętnaście elementów niewidocznych
 * w spoczynku: rzecz, do której nikt jeszcze nie dojechał, znikała na zrzucie
 * całej strony, na wydruku i w czytniku, który nie przewija. Klatka startowa
 * ma 0.4 — rozjaśnienie widać w ruchu tak samo dobrze, a najgorsze, co może
 * się stać, to przygaszony akapit, który nadal się czyta.
 *
 * Po drugie: ruszane są `translate`, `scale` i `opacity`, nigdy `transform`.
 * `transform` należy do najechania, a animacja z „both” zabierałaby mu go po
 * zakończeniu — karta podskakiwała wtedy natychmiast, bez przejścia.
 *
 * Po trzecie: uzbraja to SKRYPT, nie arkusz. Stan startowy wisi na klasie
 * `lst-mz-ruch`, którą dokłada JavaScript; bez skryptu nic nie jest uzbrojone
 * i strona stoi gotowa. Przedtem stała tu oś widoku (`animation-timeline`),
 * przez co ruch istniał wyłącznie w Chrome, Edge i Safari 26 — w Firefoksie
 * i w starszym Safari nie działo się nic. Obserwator widoczności działa
 * wszędzie i da się go sprawdzić w teście.
 */
.lst-mz-ruch .lst-mz-blok > .lst-mz-etykieta,
.lst-mz-ruch .lst-mz-blok > .lst-mz-wstep,
.lst-mz-ruch .lst-mz-blok > .lst-mz-naglowek,
.lst-mz-ruch .lst-mz-stol > .lst-mz-etykieta,
.lst-mz-ruch .lst-mz-stol > .lst-mz-opis-stolu {
	translate: 0 18px;
	opacity: .4;
}

.lst-mz-ruch .lst-mz-legenda .lst-mz-pozycja,
.lst-mz-ruch .lst-mz-para,
.lst-mz-ruch .lst-mz-pas,
.lst-mz-ruch .lst-mz-listy .lst-mz-kolumna,
.lst-mz-ruch .lst-mz-kod,
.lst-mz-ruch .lst-mz-okno.jest-stolem {
	translate: 0 30px;
	scale: .985;
	opacity: .4;
}

.lst-mz-ruch .lst-mz-stol .lstab-row {
	translate: 0 7px;
	opacity: .55;
}

/* Kreska przy tytule dociąga się razem z nim. */
.lst-mz-ruch .lst-mz-naglowek::after { transform: scaleX( 0 ); }

/*
 * Dojechało. Animacja, nie przejście: kafelek ma już własne `transition` na
 * najechanie i dopisanie się do niego tą samą właściwością skasowałoby tamto.
 */
.lst-mz-ruch .lst-mz-blok > .lst-mz-etykieta.jest-tu,
.lst-mz-ruch .lst-mz-blok > .lst-mz-wstep.jest-tu,
.lst-mz-ruch .lst-mz-blok > .lst-mz-naglowek.jest-tu,
.lst-mz-ruch .lst-mz-stol > .lst-mz-etykieta.jest-tu,
.lst-mz-ruch .lst-mz-stol > .lst-mz-opis-stolu.jest-tu {
	animation: lst-mz-wjazd 520ms var( --mz-luk ) both;
}

.lst-mz-ruch .lst-mz-legenda .lst-mz-pozycja.jest-tu,
.lst-mz-ruch .lst-mz-para.jest-tu,
.lst-mz-ruch .lst-mz-pas.jest-tu,
.lst-mz-ruch .lst-mz-listy .lst-mz-kolumna.jest-tu,
.lst-mz-ruch .lst-mz-kod.jest-tu,
.lst-mz-ruch .lst-mz-okno.jest-stolem.jest-tu {
	animation: lst-mz-podniesienie 560ms var( --mz-luk ) both;
}

.lst-mz-ruch .lst-mz-stol .lstab-row.jest-tu {
	animation: lst-mz-wiersz 420ms var( --mz-luk ) both;
}

.lst-mz-ruch .lst-mz-naglowek.jest-tu::after {
	animation: lst-mz-kreska 620ms var( --mz-luk ) both;
}

/*
 * Odstęp między sąsiadami: kafelki legendy i wiersze tabeli wchodzą po kolei.
 * Numer wpisuje skrypt, bo tylko on wie, które dziecko jest które po złożeniu
 * siatki. Sześćdziesiąt milisekund na kafelek, trzydzieści cztery na wiersz —
 * dziesięć wierszy po kolei to już czekanie, a nie powitanie, więc numer jest
 * ucinany po szóstym.
 */
.lst-mz-ruch .lst-mz-legenda .lst-mz-pozycja.jest-tu { animation-delay: calc( var( --mz-kolej, 0 ) * 60ms ); }
.lst-mz-ruch .lst-mz-stol .lstab-row.jest-tu { animation-delay: calc( var( --mz-kolej, 0 ) * 34ms ); }

/*
 * Same przesunięcia — patrz zasada pierwsza wyżej. Pisane na `translate`
 * i `scale`, a NIE na `transform`.
 *
 * Powód: te animacje chodzą na osi widoku i mają „both”, więc po przejechaniu
 * sekcji dalej trzymają właściwość, którą ruszają. Kiedy ruszały `transform`,
 * zabierały go najechaniu: karta podskakiwała o te dwa piksele NATYCHMIAST,
 * bez przejścia, bo wartość podawała skończona animacja, a nie przejście.
 * `translate` i `scale` to osobne właściwości i składają się z `transform`,
 * więc jedno nie wchodzi drugiemu w drogę.
 */
@keyframes lst-mz-wjazd {
	from { translate: 0 18px; opacity: .4; }
	to { translate: none; opacity: 1; }
}

@keyframes lst-mz-wiersz {
	from { translate: 0 7px; opacity: .55; }
	to { translate: none; opacity: 1; }
}

@keyframes lst-mz-podniesienie {
	from { translate: 0 30px; scale: .985; opacity: .4; }
	to { translate: none; scale: none; opacity: 1; }
}

@keyframes lst-mz-kreska {
	from { transform: scaleX( 0 ); }
	to { transform: none; }
}

/*
 * Na papierze nie ma przewijania, więc nie ma też czego dojeżdżać. Oś widoku w
 * druku jest niczyją ziemią; tu jest po prostu wyłączona i strona wychodzi
 * taka, jaka jest na końcu ruchu.
 */
@media print {
	.lst-mz [class*="lst-mz-"],
	.lst-mz .lst-mz-naglowek::after,
	.lst-mz .lst-mz-stol .lstab-row { animation: none !important; }

	/* Samo wyłączenie animacji zostawiłoby stan startowy, czyli przygaszoną
	   i przesuniętą treść. Rozbrajany jest też on. */
	.lst-mz-ruch [class*="lst-mz-"],
	.lst-mz-ruch .lstab-row { translate: none !important; scale: none !important; opacity: 1 !important; }
	.lst-mz-ruch .lst-mz-naglowek::after { transform: none !important; }
}

@media ( prefers-reduced-motion: reduce ) {
	/*
	 * „Mniej ruchu” znaczy mniej ruchu, nie mniej treści: animacje znikają, a
	 * wszystko, co one pokazywały, zostaje na ekranie w stanie końcowym.
	 */
	.lst-mz [class*="lst-mz-"],
	.lst-mz .lst-mz-naglowek::after,
	.lst-mz .lst-mz-stol .lstab-row { animation: none !important; }

	/* Skrypt przy „mniej ruchu” nic nie uzbraja, ale gdyby ustawienie zmieniło
	   się już po wczytaniu strony, stan startowy ma zniknąć razem z animacją. */
	.lst-mz-ruch [class*="lst-mz-"],
	.lst-mz-ruch .lstab-row { translate: none !important; scale: none !important; opacity: 1 !important; }
	.lst-mz-ruch .lst-mz-naglowek::after { transform: none !important; }

	.lst-mz .lst-mz-pozycja,
	.lst-mz .lst-mz-okno,
	.lst-mz .lst-mz-okno img,
	.lst-mz .lst-mz-cta,
	.lst-mz .lst-mz-kolumna { transition: none; }

	/* Przy „mniej ruchu” aparat stoi w swojej pozie i się nie unosi; skrypt
	   go wtedy nie nachyla. */
	.lst-mz .lst-mz-telefon-rama,
	.lst-mz .lst-mz-telefon-stojak::after { animation: none !important; }
}

/* --------------------------------------------- utwardzenie na wrogie motywy */

/*
 * Motyw pod modułem bywa pisany z „!important”. Twardo trzymane jest tylko to,
 * czym taki motyw rozbija układ: marginesy na boki, wyrównanie, wersaliki,
 * krój i cudze tła z ramkami. Pionowe marginesy zostają miękkie — dołożone
 * powietrze niczego nie psuje, a walka o każdą właściwość kończy się modułem,
 * którego nie da się już poprawić z zewnątrz.
 */
.lst-mz [class*="lst-mz-"] {
	margin-inline: 0 !important;
	background-image: none !important;
	text-align: left !important;
	text-transform: none !important;
	letter-spacing: normal !important;
	font-family: inherit !important;
}

/*
 * Rama jest wyjątkiem od reguły wyżej: to jedyny element, który MA mieć
 * marginesy na boki, bo one go środkują. Utwardzenie zabrało jej „auto” i cała
 * strona przykleiła się do lewej krawędzi. Ta reguła ma tę samą swoistość, więc
 * musi stać PO tamtej, żeby wygrać.
 */
.lst-mz .lst-mz-rama { margin-inline: auto !important; }

.lst-mz .lst-mz-kolumna-tytul { background-color: var( --mz-ekran-gora ) !important; }

.lst-mz .lst-mz-para-tresc,
.lst-mz .lst-mz-pozycja,
.lst-mz .lst-mz-kolumna,
.lst-mz .lst-mz-kod { background-color: var( --mz-plyta ) !important; border: 1px solid var( --mz-plyta-linia ) !important; }

/*
 * Aparat jest jedynym miejscem w module, gdzie gradient MUSI przejść przez
 * utwardzenie: bok telefonu bez niego jest płaską plamą, a metalu nie da się
 * udać samym cieniem. Wyjątek jest wąski i wypisany z nazwy.
 */
.lst-mz .lst-mz-telefon-rama { background-color: #1a2422 !important; background-image: var( --mz-fon-bok ) !important; border: 0 !important; border-radius: 52px !important; }
.lst-mz .lst-mz-telefon-cien { background: none !important; border: 0 !important; border-radius: 52px !important; }
.lst-mz .lst-mz-telefon-blysk { background-image: var( --mz-fon-szklo ) !important; border-radius: 40px !important; }
.lst-mz .lst-mz-telefon-wyspa { background-color: #000 !important; border-radius: 999px !important; }
.lst-mz .lst-mz-telefon-oko { background-color: #0b1a19 !important; border-radius: 50% !important; }
.lst-mz .lst-mz-telefon-kreska { background-color: rgba( 234, 243, 241, .4 ) !important; border-radius: 999px !important; }
.lst-mz .lst-mz-telefon-guzik,
.lst-mz .lst-mz-telefon-plastry.jest-przod > b { background-color: #33433f !important; }
.lst-mz .lst-mz-telefon-warstwa { background-color: color-mix( in srgb, #b3c5c1 calc( 100% - max( calc( ( var( --i ) - 12 ) * 7.5% ), calc( ( 12 - var( --i ) ) * 7.5% ) ) ), #1a2422 ) !important; border: 0 !important; border-radius: 51px !important; }
.lst-mz .lst-mz-telefon-godzina { font-family: inherit !important; }
.lst-mz .lst-mz-telefon { background-color: var( --mz-ekran ) !important; border-radius: 40px !important; }
.lst-mz .lst-mz-okno { background-color: var( --mz-ekran ) !important; border: 1px solid var( --mz-ekran-linia ) !important; }
.lst-mz .lst-mz-belka { background-color: var( --mz-ekran-gora ) !important; border: 0 !important; border-bottom: 1px solid rgba( var( --mz-mieta ), .14 ) !important; }

.lst-mz .lst-mz-znak {
	border: 1px solid var( --mz-linia ) !important;
	text-transform: uppercase !important;
	font-family: var( --mz-mono ) !important;
}

.lst-mz .lst-mz-znak.jest-pro { border-color: rgba( var( --mz-mieta ), .45 ) !important; }

/* Motyw malujący wszystkie svg zalałby rysunki na płask. */
.lst-mz .lst-mz-ikona { fill: none !important; stroke: currentColor !important; }
.lst-mz .lst-mz-ikona.jest-stan { height: 11px !important; }
.lst-mz .lst-mz-ikona .jest-pelna { fill: currentColor !important; stroke: none !important; }
.lst-mz .lst-mz-znak-adres { background-color: rgba( 138, 168, 163, .12 ) !important; border-right: 1px solid var( --mz-linia ) !important; }
.lst-mz .lst-mz-znak.jest-pro .lst-mz-znak-slowo { background-color: rgba( var( --mz-mieta ), .1 ) !important; }

.lst-mz .lst-mz-etykieta,
.lst-mz .lst-mz-kolumna-tytul { text-transform: uppercase !important; }

.lst-mz .lst-mz-naglowek { font-family: var( --mz-szeryf ) !important; }
.lst-mz .lst-mz-cta { background-color: rgb( var( --mz-mieta ) ) !important; color: #06100f !important; }
/* Przycisk stoi w pasku pisanym krojem maszynowym, ale sam jest zwykłym
   napisem, jak przycisk w nagłówku witryny. */
.lst-mz .lst-mz-kod .lst-mz-cta { font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif !important; }

.lst-mz .lst-mz-adres,
.lst-mz .lst-mz-etykieta,
.lst-mz .lst-mz-punkty,
.lst-mz .lst-mz-nazwa-okna,
.lst-mz .lst-mz-belka-prawa,
.lst-mz .lst-mz-kolumna-tytul,
.lst-mz .lst-mz-kod,
.lst-mz .lst-mz-kod-opis,
.lst-mz .lst-mz-mono { font-family: var( --mz-mono ) !important; }

/* Sama tabela broni się swoim arkuszem; tu tylko tyle, żeby cudze marginesy
   nie wypchnęły jej poza szerokość strony. */
.lst-mz .lstab-container, .lst-mz .lstab { margin-inline: 0 !important; max-width: 100%; }

/*
 * Cudza ramka wokół tabeli.
 *
 * Panel przewijania tabeli to zwykły <div>, więc motyw, który maluje ramkę
 * wszystkim divom, maluje ją i jemu: na stronie klienta wokół całej tabeli
 * stanęła biała kreska, której nie ma ani w szablonie, ani w próbnikach.
 * Arkusz wtyczki pisze tam „border: var( --lstab-frame-border )” bez
 * „!important”, więc z motywem przegrywa — a nie dostanie „!important”,
 * bo u klienta motyw bywa jedynym miejscem, gdzie da się tabelę obrysować.
 *
 * Tutaj szablon jest znany i jest nim Karty, w których ramki nie ma wcale:
 * karty trzymają się własnym cieniem.
 *
 * Zdejmowane są pudła nośne i sam <table>, bo „table { border: 1px solid }”
 * siedzi w arkuszu niejednego motywu — i to ono rysowało tę białą obwódkę,
 * a nie panel przewijania. Nigdy komórki i nigdy wiersz: komórka rysuje obrys
 * karty, a wiersz nosi ramkę karty na telefonie. „border: 0” zmiotłoby jedno
 * i drugie.
 */
.lst-mz .lstab,
.lst-mz .lstab-container,
.lst-mz .lstab-scroll,
.lst-mz .lstab-table,
.lst-mz .lstab-table > thead,
.lst-mz .lstab-table > tbody,
.lst-mz .lstab-table > tfoot { border: 0 !important; }

/* Obwódka od klawiatury zostaje: zdejmowana jest tylko ta, której nikt nie
   prosił. Panel przewijania da się przewinąć klawiszami i musi być widać,
   że jest zaznaczony. */
.lst-mz .lstab:not( :focus-visible ),
.lst-mz .lstab-container:not( :focus-visible ),
.lst-mz .lstab-scroll:not( :focus-visible ) { outline: 0 !important; }

/* ------------------------------------------------------------- wąsko */

@media ( max-width: 1180px ) {
	.lst-mz .lst-mz-legenda { grid-template-columns: repeat( 2, minmax( 0, 1fr ) ); }
}

/* Na wąskim ekranie w belce mieści się nazwa okna albo nic — druga połowa
   urywała się w połowie słowa. */
@media ( max-width: 640px ) {
	.lst-mz .lst-mz-belka-prawa { display: none; }
}

@media ( max-width: 900px ) {
	.lst-mz .lst-mz-legenda,
	.lst-mz .lst-mz-para { grid-template-columns: minmax( 0, 1fr ); }

	.lst-mz .lst-mz-para.jest-odwrocona .lst-mz-okno { order: 0; }

	/* Kreska za tytułem ma sens, kiedy tytuł mieści się w jednym wierszu.
	   Przy dwóch łamie się obok pierwszego i wygląda jak zgubiony znak. */
	.lst-mz .lst-mz-naglowek::after { display: none; }
	.lst-mz .lst-mz-pas { grid-template-columns: minmax( 0, 1fr ); }
	.lst-mz .lst-mz-telefon-stojak,
	.lst-mz .lst-mz-telefon-rama { display: none; }
	.lst-mz .lst-mz-szeroko { display: none; }
	.lst-mz .lst-mz-wasko { display: inline; }
}
"""

def skrot( css ):
	"""Ten sam arkusz stylów, bez komentarzy i bez pustych miejsc.

	Arkusz wtyczki jest w połowie komentarzem, bo tak jest napisany cały ten
	projekt, i to jest dobrze — ale do modułu w Divi idzie nie po to, żeby go
	ktoś czytał. Dwieście kilobajtów w jednym polu edytora wizualnego potrafi
	zawiesić edytor razem z całą stroną; zdarzyło się to na żywo.

	Cięte są komentarze i ciągi białych znaków, i nic więcej: spacje wokół
	działań zostają, bo „calc( 100% - 2 * x )" bez spacji przestaje być
	poprawnym wyrażeniem. Cudzysłowy są pilnowane, żeby „content" z gwiazdką
	w środku nie został wzięty za początek komentarza.
	"""
	wynik = []
	i = 0
	cudzyslow = ''

	while i < len( css ):
		z = css[ i ]

		if cudzyslow:
			wynik.append( z )
			if '\\' == z and i + 1 < len( css ):
				wynik.append( css[ i + 1 ] )
				i += 2
				continue
			if z == cudzyslow:
				cudzyslow = ''
			i += 1
			continue

		if z in '"\'':
			cudzyslow = z
			wynik.append( z )
			i += 1
			continue

		if '/' == z and css[ i + 1 : i + 2 ] == '*':
			koniec = css.find( '*/', i + 2 )
			i = len( css ) if -1 == koniec else koniec + 2
			if wynik and not wynik[ -1 ].isspace():
				wynik.append( ' ' )
			continue

		if z.isspace():
			if wynik and not wynik[ -1 ].isspace():
				wynik.append( ' ' )
			i += 1
			continue

		wynik.append( z )
		i += 1

	return ''.join( wynik ).strip()


# Skrypt wjazdu. Pół kilobajta, bez zależności, nie dotyka tabeli.
#
# Uzbraja stan startowy klasą na korzeniu modułu i dokłada `jest-tu`, kiedy
# rzecz wjedzie w ekran. Uzbraja SKRYPT, nie arkusz, więc bez JavaScriptu nic
# nie jest przygaszone ani przesunięte: strona stoi gotowa.
RUCH = r"""( function () {
	/*
	 * Skrypt może stać w <head>, na początku <body> albo na końcu — Divi
	 * w zakładce Integracja wkleja kod tam, gdzie mu wygodnie, a nie tam,
	 * gdzie skrypt by chciał. Przed złożeniem strony nie ma jeszcze ani
	 * modułów, ani aparatu: wjazd nic by nie uzbroił, a telefon nie dostałby
	 * nachylenia. Dlatego oba czekają, aż strona będzie gotowa.
	 */
	var poGotowej = function ( zrob ) {
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', zrob );
		} else {
			zrob();
		}
	};

	poGotowej( function () {
		var korzenie = document.querySelectorAll( '.lst-mz' );
		if ( ! korzenie.length || ! window.IntersectionObserver ) { return; }
		if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) { return; }

		var CELE = '.lst-mz-blok > .lst-mz-etykieta, .lst-mz-blok > .lst-mz-wstep, .lst-mz-blok > .lst-mz-naglowek,'
			+ '.lst-mz-stol > .lst-mz-etykieta, .lst-mz-stol > .lst-mz-opis-stolu,'
			+ '.lst-mz-legenda .lst-mz-pozycja, .lst-mz-para, .lst-mz-pas,'
			+ '.lst-mz-listy .lst-mz-kolumna, .lst-mz-kod, .lst-mz-okno.jest-stolem,'
			+ '.lst-mz-stol .lstab-row';

		var oko = new IntersectionObserver( function ( wpisy ) {
			for ( var i = 0; i < wpisy.length; i++ ) {
				if ( wpisy[ i ].isIntersecting ) {
					wpisy[ i ].target.classList.add( 'jest-tu' );
					oko.unobserve( wpisy[ i ].target );
				}
			}
		}, { rootMargin: '0px 0px -6% 0px', threshold: 0.06 } );

		for ( var k = 0; k < korzenie.length; k++ ) {
			/*
			 * Czy arkusz pasuje do znacznikowania.
			 *
			 * Moduł idzie do Divi w dwóch kawałkach i łatwo podmienić jeden,
			 * a drugi zostawić. Strona wygląda wtedy jak zepsuta i nie ma po czym
			 * poznać dlaczego. Odcisk mówi to jednym zdaniem w konsoli.
			 */
			var wKodzie = korzenie[ k ].getAttribute( 'data-odcisk' );

			/*
			 * Starsza wersja tego samego arkusza gdzieś na stronie.
			 *
			 * Stary arkusz nie zna odcisku, więc porównanie niżej go nie widzi,
			 * a wczytany PO nowym przykrywa go regułami o tej samej wadze: aparat
			 * robi się niski i płaski, wyspa kurczy się do kropki, godzina chowa
			 * się za rogiem ekranu. Tak było na żywej stronie. Arkusz tej strony
			 * poznać po „--mz-mieta” na .lst-mz; aktualny niesie też odcisk.
			 */
			if ( 0 === k && wKodzie && window.console ) {
				var starych = 0;
				for ( var s = 0; s < document.styleSheets.length; s++ ) {
					var reguly = null;
					try { reguly = document.styleSheets[ s ].cssRules; } catch ( blad ) { reguly = null; }
					if ( ! reguly ) { continue; }
					var moj = false;
					var biezacy = false;
					for ( var q = 0; q < reguly.length; q++ ) {
						if ( '.lst-mz' !== reguly[ q ].selectorText || ! reguly[ q ].style ) { continue; }
						if ( reguly[ q ].style.getPropertyValue( '--mz-mieta' ) ) { moj = true; }
						if ( reguly[ q ].style.getPropertyValue( '--mz-odcisk' ).replace( /["'\s]/g, '' ) === wKodzie ) { biezacy = true; }
					}
					if ( moj && ! biezacy ) { starych++; }
				}
				if ( starych ) {
					console.warn( 'lst-mz: na stronie jest ' + starych + ' starsza wersja arkusza stylow. Usun ja z pola '
						+ '"Wlasny CSS" w Divi i wyczysc pamiec statycznych plikow CSS (Divi > Opcje motywu > Kreator > Zaawansowane).' );
				}
			}
			var wArkuszu = ( getComputedStyle( korzenie[ k ] ).getPropertyValue( '--mz-odcisk' ) || '' ).replace( /["'\s]/g, '' );
			if ( wKodzie && wArkuszu && wKodzie !== wArkuszu && window.console ) {
				console.warn( 'lst-mz: arkusz stylow nie pasuje do kodu modulu (kod ' + wKodzie
					+ ', arkusz ' + wArkuszu + '). Wklej obie czesci z tej samej paczki.' );
			}

			korzenie[ k ].classList.add( 'lst-mz-ruch' );

			var cele = korzenie[ k ].querySelectorAll( CELE );
			for ( var i = 0; i < cele.length; i++ ) {
				var el = cele[ i ];

				/*
				 * Numer W SWOIM WIERSZU, nie w całej siatce.
				 *
				 * Przedtem szedł numer po kolei przez wszystkie dziewięć kafelków,
				 * więc ostatni czekał 360 ms. Siedział wtedy przygaszony na środku
				 * ekranu, po czym skakał do pełni: dokładnie ten przeskok na dole,
				 * który widać na filmie. Teraz każdy wiersz zaczyna od nowa, więc
				 * żaden kafelek nie czeka dłużej niż dwa odstępy.
				 *
				 * Wiersz poznajemy po tym, że sąsiad stoi na tej samej wysokości.
				 * Działa tak samo przy trzech kolumnach, przy dwóch i przy jednej,
				 * bo przy jednej każdy kafelek jest sam w swoim wierszu i odstępu
				 * nie dostaje wcale.
				 */
				var bracia = el.parentNode.children;
				var wTabeli = 'TR' === el.tagName;
				var moja = el.offsetTop;
				var n = 0;
				for ( var j = 0; j < bracia.length && bracia[ j ] !== el; j++ ) {
					// Wiersze tabeli stoją jeden pod drugim i mają schodzić po kolei,
					// więc tam liczą się wszystkie, a nie tylko te z tej samej linii.
					if ( wTabeli || bracia[ j ].offsetTop === moja ) { n++; }
				}
				var ile = wTabeli ? 5 : 3;
				if ( n > 0 ) { el.style.setProperty( '--mz-kolej', n > ile ? ile : n ); }

				oko.observe( el );
			}
		}
	} );

	/*
	 * Nachylenie aparatu za kursorem.
	 *
	 * Tylko tam, gdzie jest prawdziwa mysz: na dotyku „najechanie” odpala się
	 * przy stuknięciu i aparat zostawałby przekrzywiony. Przy „mniej ruchu” nic.
	 * Prostokąt aparatu jest mierzony raz, przy wejściu kursora: mierzony w ruchu
	 * zmieniałby się razem z nachyleniem i aparat drgałby, goniąc sam siebie.
	 * Zapis do stylu raz na klatkę, nie przy każdym ruchu myszy.
	 */
	poGotowej( function () {
		if ( ! window.matchMedia || ! window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches ) { return; }
		if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) { return; }

		/*
		 * Nachylenie aparatu za kursorem, płynne.
		 *
		 * Trzy rzeczy, które psuły poprzednią wersję i które widać było na
		 * nagraniu ze strony:
		 *
		 * 1. Położenie aparatu było mierzone raz, przy wejściu kursora. Po
		 *    przewinięciu strony ten pomiar był nieaktualny, więc przy
		 *    następnym ruchu myszy aparat przeskakiwał na drugą stronę.
		 *    Teraz mierzony jest przy każdym ruchu, i to stojak, który się
		 *    nie rusza, a nie sam aparat.
		 * 2. Przewijanie pod nieruchomym kursorem przechylało aparat samo:
		 *    przeglądarka uznawała, że kursor „wszedł”. Teraz przewijanie
		 *    odkłada aparat, a do ruchu wraca on dopiero, gdy ręka naprawdę
		 *    poruszy myszą.
		 * 3. Wejście i wyjście zależały od kształtu aparatu w ruchu, który
		 *    przy brzegu odsuwał się spod kursora, a potem wracał: aparat
		 *    migał. Teraz decyduje prostokąt stojaka.
		 *
		 * Sam ruch: aparat goni swój cel z wygładzeniem, klatka po klatce,
		 * niezależnie od tego, jak często przychodzą zdarzenia myszy.
		 */
		var stojaki = document.querySelectorAll( '.lst-mz .lst-mz-telefon-stojak' );
		var aparaty = [];

		for ( var i = 0; i < stojaki.length; i++ ) {
			var fon = stojaki[ i ].querySelector( '.lst-mz-telefon-rama' );
			// Skrypt bywa na stronie kilka razy, bo każda sekcja wklejona
			// w całości niesie swój. Aparat podpinany jest raz.
			if ( ! fon || fon.getAttribute( 'data-mz-nachyl' ) ) { continue; }
			fon.setAttribute( 'data-mz-nachyl', '1' );
			// Poza spoczynkowa jest w arkuszu; aparat zaczyna w niej i do niej wraca.
			var styl = window.getComputedStyle( fon );
			var pozaY = parseFloat( styl.getPropertyValue( '--mz-poza-y' ) ) || 0;
			var pozaX = parseFloat( styl.getPropertyValue( '--mz-poza-x' ) ) || 0;
			aparaty.push( { stojak: stojaki[ i ], fon: fon, nad: false, px: pozaX, py: pozaY,
				x: pozaY, y: pozaX, unies: 0, cx: pozaY, cy: pozaX, cunies: 0, bx: 0.5, by: 0.5 } );
		}
		if ( ! aparaty.length ) { return; }

		var klatka = 0;
		var ostatnio = 0;
		var poPrzewinieciu = false;
		var ekranX = -1;
		var ekranY = -1;

		var krok = function ( teraz ) {
			var dt = ostatnio ? Math.min( 64, teraz - ostatnio ) : 16;
			ostatnio = teraz;
			var dalej = false;

			for ( var k = 0; k < aparaty.length; k++ ) {
				var a = aparaty[ k ];
				// Za ręką szybciej, z powrotem wolniej: odkłada się, a nie odskakuje.
				var sila = 1 - Math.pow( a.nad ? 0.84 : 0.9, dt / 16.7 );
				a.cx += ( a.x - a.cx ) * sila;
				a.cy += ( a.y - a.cy ) * sila;
				a.cunies += ( a.unies - a.cunies ) * sila;

				if ( Math.abs( a.x - a.cx ) > 0.01 || Math.abs( a.y - a.cy ) > 0.01 || Math.abs( a.unies - a.cunies ) > 0.02 ) {
					dalej = true;
				} else {
					a.cx = a.x; a.cy = a.y; a.cunies = a.unies;
					// Odłożony do końca: scena 3D schodzi ze strony, zostaje
					// płaski aparat, którego przewijanie nie rozsypie.
					if ( ! a.nad ) { a.fon.classList.remove( 'jest-3d' ); }
				}

				a.fon.style.setProperty( '--mz-nachyl-y', a.cx.toFixed( 3 ) + 'deg' );
				a.fon.style.setProperty( '--mz-nachyl-x', a.cy.toFixed( 3 ) + 'deg' );
				a.fon.style.setProperty( '--mz-uniesienie', a.cunies.toFixed( 2 ) + 'px' );
				a.fon.style.setProperty( '--mz-blask-x', ( a.bx * 100 ).toFixed( 1 ) + '%' );
				a.fon.style.setProperty( '--mz-blask-y', ( a.by * 100 ).toFixed( 1 ) + '%' );
			}

			klatka = dalej ? window.requestAnimationFrame( krok ) : 0;
			if ( ! dalej ) { ostatnio = 0; }
		};

		var ruszaj = function () {
			if ( ! klatka ) { klatka = window.requestAnimationFrame( krok ); }
		};

		var odloz = function ( a ) {
			if ( ! a.nad ) { return; }
			a.nad = false;
			a.x = a.py; a.y = a.px; a.unies = 0;
			a.fon.classList.remove( 'jest-nad' );
			ruszaj();
		};

		document.addEventListener( 'pointermove', function ( e ) {
			if ( 'mouse' !== e.pointerType && 'pen' !== e.pointerType ) { return; }

			// Po przewinięciu przeglądarka sama dosyła ruch myszy w tym samym
			// miejscu ekranu. To nie jest ręka; ruszamy dopiero po prawdziwym.
			if ( poPrzewinieciu && e.screenX === ekranX && e.screenY === ekranY ) { return; }
			poPrzewinieciu = false;
			ekranX = e.screenX;
			ekranY = e.screenY;

			for ( var k = 0; k < aparaty.length; k++ ) {
				var a = aparaty[ k ];
				var r = a.stojak.getBoundingClientRect();
				var w = r.width ? ( e.clientX - r.left ) / r.width : -1;
				var h = r.height ? ( e.clientY - r.top ) / r.height : -1;

				if ( w < 0 || w > 1 || h < 0 || h > 1 ) { odloz( a ); continue; }

				if ( ! a.nad ) {
					a.nad = true;
					a.fon.classList.add( 'jest-nad', 'jest-3d' );
				}
				a.x = ( w - 0.5 ) * 26;
				a.y = ( 0.5 - h ) * 16;
				a.unies = -6;
				a.bx = w;
				a.by = h;
				ruszaj();
			}
		}, { passive: true } );

		var wszystkieOdloz = function () {
			for ( var k = 0; k < aparaty.length; k++ ) { odloz( aparaty[ k ] ); }
		};

		window.addEventListener( 'scroll', function () {
			poPrzewinieciu = true;
			wszystkieOdloz();
		}, { passive: true } );

		document.documentElement.addEventListener( 'pointerleave', wszystkieOdloz );
		window.addEventListener( 'blur', wszystkieOdloz );
	} );
} )();"""


def bez_komentarzy_js( js ):
	"""Skrypt wjazdu bez komentarzy, w takim kształcie, w jakim idzie do Divi.

	Komentarze są pisane dla tego repozytorium i są dłuższe niż sam kod;
	w polu edytora wizualnego tylko ważą. Cięte są wyłącznie bloki komentarzy
	i linie zaczynające się od „//”: w tym skrypcie nie ma napisów ani
	wyrażeń regularnych, w których stałby taki znak, i pilnuje tego warunek
	niżej.
	"""
	assert '//' not in re.sub( r'^\s*//.*$', '', re.sub( r'/\*.*?\*/', '', js, flags = re.S ), flags = re.M ), 'w kodzie stoi //, którego nie da się bezpiecznie ciąć'
	js = re.sub( r'/\*.*?\*/', '', js, flags = re.S )
	js = re.sub( r'^\s*//.*\n', '', js, flags = re.M )
	js = re.sub( r'\n\s*\n+', '\n', js )

	return js.strip()


RUCH = bez_komentarzy_js( RUCH )


def z_odciskiem( arkusz_css, znacznik_html ):
	"""Odcisk arkusza wpisany i w CSS, i w znacznikowanie.

	Moduł idzie do Divi w dwóch kawałkach: znacznikowanie do modułu Kod, arkusz
	do opcji motywu. Kto podmieni jedno i zapomni o drugim, dostaje stronę,
	która wygląda jak zepsuta, i nie ma po czym poznać dlaczego — zdarzyło się
	to z telefonem: nowe znacznikowanie trafiło na stary arkusz, ikonki paska
	stanu ustawiły się w słupek, a obudowa została płaskim prostokątem.

	Skrót ląduje jako wartość w CSS i jako atrybut przy znacznikowaniu.
	Skrypt porównuje jedno z drugim i mówi w konsoli, że się rozjechało.

	Liczony z arkusza TEJ STRONY, a nie z całego pliku, który idzie do Divi.
	Pliki różnych sekcji różnią się tym, czy niosą jeszcze arkusz wtyczki albo
	sekcji „jak to działa”, i jeden arkusz z całej podstrony w opcjach motywu
	obsługuje wszystkie sekcje naraz. Liczony z całego pliku, odcisk krzyczałby
	przy dokładnie tym ustawieniu, które jest zalecane. Tak mówi tylko to, co
	ma mówić: czy kod i arkusz są z tego samego wydania strony. To samo wydanie
	daje ten sam odcisk, więc przebudowanie bez zmian niczego nie rusza.
	"""
	odcisk = hashlib.sha1( skrot( STYL ).encode( 'utf-8' ) ).hexdigest()[ :8 ]
	css = arkusz_css + '\n.lst-mz { --mz-odcisk: "' + odcisk + '"; }'
	html = znacznik_html.replace( '<div class="lst-mz">', '<div class="lst-mz" data-odcisk="' + odcisk + '">', 1 )

	return css, html


CZCIONKI = ( '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?'
	'family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600'
	'&family=Inria+Serif:ital,wght@0,300;0,400&display=swap">' )

ZNACZNIK = '<div class="lst-mz"><div class="lst-mz-rama">' + SEKCJA + '</div></div>'
ARKUSZ = skrot( STYL + arkusz.STYL + CSS )
ARKUSZ, ZNACZNIK = z_odciskiem( ARKUSZ, ZNACZNIK )

STRONA = (
	CZCIONKI + '\n'
	'\n' + ZNACZNIK + '\n'
	'\n<style>\n' + ARKUSZ + '\n</style>\n'
	'\n<script>\n' + JS + '\n\n' + RUCH + '\n</script>\n'
)

# Strażnik: nazwa shortcode'u nigdzie nie może stykać się z nawiasem, ani
# wprost, ani przez encję. Inaczej WordPress wykona przykład zamiast go
# pokazać, a zobaczy się to dopiero na żywej stronie.
for _zbitka in ( '[sheet_table', '&#91;sheet_table', '&#x5B;sheet_table' ):
	if _zbitka in ZNACZNIK:
		raise SystemExit( 'w module jest „' + _zbitka + '" — WordPress wykona to jako shortcode' )

( TU / 'MOZLIWOSCI-en.html' ).write_text( STRONA )

"""
Do wklejenia w Divi, w trzech kawałkach.

Cała podstrona w jednym polu edytora wizualnego to ponad dwieście kilobajtów,
a edytor trzyma to w pamięci i przerysowuje przy każdym naciśnięciu klawisza.
Potrafi na tym stanąć razem z całą stroną. Rozdzielone idzie tam, gdzie każdy
kawałek waży tyle, ile ma ważyć:

* MOZLIWOSCI-kod.html -> moduł Kod (sam znacznik)
* MOZLIWOSCI-css.css  -> Divi > Opcje motywu > Własny CSS
* MOZLIWOSCI-js.js    -> Divi > Opcje motywu > Integracja > przed </body>,
                         w <script>. Bez niego tabela na tej stronie jest
                         kompletna i wygląda tak samo, tylko wyszukiwarka,
                         sortowanie i filtry nic nie robią.
"""

( TU / 'MOZLIWOSCI-kod.html' ).write_text( CZCIONKI + '\n\n' + ZNACZNIK + '\n' )
( TU / 'MOZLIWOSCI-css.css' ).write_text( ARKUSZ + '\n' )
( TU / 'MOZLIWOSCI-js.js' ).write_text( JS + '\n\n' + RUCH + '\n' )

# Gotowe do wklejenia w Divi → Opcje motywu → Integracja, już w znacznikach.
#
# Arkusz NIE idzie do pola „Własny CSS”. Tamto pole sprawdza CSS starym
# walidatorem (CSSLint), który nie zna zmiennych CSS, color-mix, osobnych
# „translate” i „scale” ani niczego nowszego z ostatnich lat, i zasypuje
# poprawny arkusz setką błędów „Expected RBRACE”. Pole „kod w <head>” niczego
# takiego nie robi, a arkusz w <style> działa tam dokładnie tak samo.
# Znaczniki są dołożone tutaj, bo dopisywane ręcznie to kolejny krok, w którym
# łatwo o pomyłkę.
# Nazwa zaczyna się od INTEGRACJA, a nie od nazwy podstrony, bo pliki
# „MOZLIWOSCI-…html” wyglądały jak jeszcze jedna sekcja do modułu Kod. Wklejony
# tam sam <style> albo sam <script> nie pokazuje niczego.
( TU / 'INTEGRACJA-head.html' ).write_text(
	'<!-- DO: Divi > Opcje motywu > Integracja > Dodaj kod do <head>. NIE do modulu Kod. -->\n'
	'<style>\n' + ARKUSZ + '\n</style>\n' )
( TU / 'INTEGRACJA-body.html' ).write_text(
	'<!-- DO: Divi > Opcje motywu > Integracja > Dodaj kod do <body>. NIE do modulu Kod. -->\n'
	'<script>\n' + JS + '\n\n' + RUCH + '\n</script>\n' )

# Podgląd do otwarcia w przeglądarce: podrabia tło i dopełnienia Divi, i
# podstawia lokalne adresy zrzutów. Do Divi idzie wyłącznie MOZLIWOSCI-en.html.
#
# Tło jest TAKIE SAME jak w landing/naglowek/HERO-podglad.html: #232a29 i
# siatka 88 × 44. Wcześniej podgląd malował pod modułem własną, ciemniejszą
# czerń — i cała ta podstrona była projektowana pod tło, którego na stronie
# nie ma.
#
# Podgląd udaje stronę Divi, a strona Divi ma deklarację typu dokumentu. Bez
# niej przeglądarka idzie w tryb zgodności, w którym tabela NIE dziedziczy
# koloru tekstu po swoim otoczeniu.
PODGLAD = (
	'<!doctype html>\n<html lang="en">\n<meta charset="utf-8">\n'
	'<title>What it does</title>\n'
	'<style>\n'
	'html, body { margin: 0; background: #232a29; color: #eaf3f1;\n'
	'\tfont-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif; }\n'
	'body { background-image: linear-gradient( to right, rgba( 255, 255, 255, .04 ) 1px, transparent 1px ),\n'
	'\tlinear-gradient( to bottom, rgba( 255, 255, 255, .04 ) 1px, transparent 1px );\n'
	'\tbackground-size: 88px 44px; }\n'
	'.podrobka-divi { padding: 40px 0; }\n'
	'.podrobka-divi-rzad { width: 90%; max-width: 1800px; margin: 0 auto; }\n'
	'</style>\n'
	'<div class="podrobka-divi"><div class="podrobka-divi-rzad">\n'
	+ STRONA.replace( 'ADRES/', 'zrzuty/' ) +
	'\n</div></div>\n'
)

( TU / 'PODGLAD.html' ).write_text( PODGLAD )

# --- sama sekcja z tabelą, do wklejenia osobno -------------------------------
#
# Ta sama zmienna BLOK_STOL, którą niesie podstrona, więc nie da się poprawić
# jednego i zapomnieć o drugim. Arkusz jest ten sam minus CSS sekcji „jak to
# działa”, bo tego bloku tu nie ma — a sam arkusz wtyczki zostaje, bo bez niego
# tabela to goła kratka.
STOL_ZNACZNIK = '<div class="lst-mz"><div class="lst-mz-rama">' + BLOK_STOL + '</div></div>'
STOL_ARKUSZ   = skrot( STYL + CSS )
STOL_ARKUSZ, STOL_ZNACZNIK = z_odciskiem( STOL_ARKUSZ, STOL_ZNACZNIK )

STOL = (
	CZCIONKI + '\n'
	'\n' + STOL_ZNACZNIK + '\n'
	'\n<style>\n' + STOL_ARKUSZ + '\n</style>\n'
	'\n<script>\n' + JS + '\n\n' + RUCH + '\n</script>\n'
)

( TU / 'STOL-en.html' ).write_text( STOL )
( TU / 'STOL-kod.html' ).write_text( CZCIONKI + '\n\n' + STOL_ZNACZNIK + '\n' )
( TU / 'STOL-css.css' ).write_text( STOL_ARKUSZ + '\n' )
( TU / 'STOL-js.js' ).write_text( JS + '\n\n' + RUCH + '\n' )

( TU / 'STOL-podglad.html' ).write_text(
	'<!doctype html>\n<html lang="en">\n<meta charset="utf-8">\n'
	'<title>This is the plugin, running here</title>\n'
	'<style>\n'
	'html, body { margin: 0; background: #232a29; color: #eaf3f1;\n'
	'\tfont-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif; }\n'
	'body { background-image: linear-gradient( to right, rgba( 255, 255, 255, .04 ) 1px, transparent 1px ),\n'
	'\tlinear-gradient( to bottom, rgba( 255, 255, 255, .04 ) 1px, transparent 1px );\n'
	'\tbackground-size: 88px 44px; }\n'
	'.podrobka-divi { padding: 40px 0; }\n'
	'.podrobka-divi-rzad { width: 90%; max-width: 1800px; margin: 0 auto; }\n'
	'</style>\n'
	'<div class="podrobka-divi"><div class="podrobka-divi-rzad">\n'
	+ STOL +
	'\n</div></div>\n'
)


def przytnij( css, znacznik, zywe, tylko = '' ):
	"""Arkusz bez reguł, które nie mają w tym znaczniku czego dotknąć.

	Moduł z telefonem niesie w sobie cały arkusz podstrony i cały arkusz
	wtyczki, choć z obu potrzebuje ułamka: reszta to tabela na stole, legenda,
	listy, a z wtyczki dziewięć szablonów, których telefon nie nosi. Razem to
	ponad sto kilobajtów w jednym module Kod, a przy takim rozmiarze Divi
	potrafi zawiesić edytor i całą stronę.

	Reguła zostaje, jeśli każda klasa, której wymaga, jest w znaczniku albo na
	liście klas dokładanych w ruchu (wjazd, najechanie, scena 3D). Klasy
	w :not(), :is(), :where() i :has() niczego nie wymagają, więc się nie
	liczą. To ocena ostrożna: reguła z wątpliwościami zostaje, odpada tylko
	ta, która na pewno niczego tu nie złapie. @keyframes i inne bloki bez
	selektorów zostają w całości; blok @media czy @supports, któremu nic nie
	zostało w środku, odpada.

	Z `tylko` odpadają wyłącznie reguły, którym brakuje klasy o tym
	początku — reszta zostaje, nawet jeśli w znaczniku nie ma jej klas.
	"""
	obecne = set()
	for klasy in re.findall( r'class="([^"]*)"', znacznik ):
		obecne.update( klasy.split() )
	obecne.update( zywe )

	def wymaga( selektor ):
		bez = selektor
		# Zawartość nawiasów :not() itp. niczego nie wymaga; zagnieżdżenia
		# zdejmowane od środka.
		while True:
			nowy = re.sub( r':(?:not|is|where|has|nth-child|nth-of-type|nth-last-child)\([^()]*\)', '', bez )
			if nowy == bez:
				break
			bez = nowy
		bez = re.sub( r'\[[^\]]*\]', '', bez )
		return set( re.findall( r'\.(-?[_a-zA-Z][\w-]*)', bez ) )

	def podziel( lista ):
		czesci, glebia, start = [], 0, 0
		for i, z in enumerate( lista ):
			if z in '([':
				glebia += 1
			elif z in ')]':
				glebia -= 1
			elif ',' == z and 0 == glebia:
				czesci.append( lista[ start:i ] )
				start = i + 1
		czesci.append( lista[ start: ] )
		return [ c.strip() for c in czesci if c.strip() ]

	def blok( tekst, i ):
		"""Indeks zamykającej klamry dla klamry otwartej tuż przed i."""
		glebia, cudzyslow = 1, ''
		while i < len( tekst ):
			z = tekst[ i ]
			if cudzyslow:
				if '\\' == z:
					i += 1
				elif z == cudzyslow:
					cudzyslow = ''
			elif z in '"\'':
				cudzyslow = z
			elif '{' == z:
				glebia += 1
			elif '}' == z:
				glebia -= 1
				if 0 == glebia:
					return i
			i += 1
		return len( tekst )

	def tnij( tekst ):
		wynik, i = [], 0
		while i < len( tekst ):
			otw = tekst.find( '{', i )
			if -1 == otw:
				wynik.append( tekst[ i: ] )
				break
			glowa = tekst[ i:otw ]
			zam = blok( tekst, otw + 1 )
			srodek = tekst[ otw + 1:zam ]
			naglowek = glowa.strip()
			if naglowek.startswith( '@' ):
				if re.match( r'@(media|supports|container|layer)\b', naglowek ):
					wnetrze = tnij( srodek )
					if wnetrze.strip():
						wynik.append( glowa + '{' + wnetrze + '}' )
				else:
					wynik.append( glowa + '{' + srodek + '}' )
			else:
				zostaja = [ sel for sel in podziel( naglowek )
					if not any( k.startswith( tylko ) for k in wymaga( sel ) - obecne ) ]
				if zostaja:
					wynik.append( ','.join( zostaja ) + '{' + srodek + '}' )
			i = zam + 1
		return ''.join( wynik )

	return tnij( css )


# --- dwie sekcje bez tabeli, do wklejenia osobno -----------------------------
#
# Tu nie ma tabeli, więc nie ma po co nieść arkusza ani skryptu wtyczki: same
# te dwa pliki ważyłyby osiem razy tyle, co cała reszta modułu. Idzie tylko CSS
# tej strony.
MALY_ARKUSZ = skrot( STYL )


def osobno( nazwa, blok, opis, uwaga = '' ):
	"""Jeden blok jako samodzielny moduł: całość i to samo w trzech kawałkach.

	`uwaga` ląduje komentarzem na samej górze pliku. Sekcja ze zrzutami nie
	zadziała bez jednej podmiany, a instrukcja w README to instrukcja, której
	przy wklejaniu nikt nie ma przed oczami.
	"""
	czapka = ( '<!-- ' + uwaga + ' -->\n\n' ) if uwaga else ''
	znacznik = '<div class="lst-mz"><div class="lst-mz-rama">' + blok + '</div></div>'
	arkusz_tu, znacznik = z_odciskiem( MALY_ARKUSZ, znacznik )
	# Telefon to jedna trzecia arkusza, a w tych sekcjach go nie ma.
	arkusz_tu = przytnij( arkusz_tu, znacznik, set(), 'lst-mz-telefon' )
	calosc = ( czapka +
		CZCIONKI + '\n'
		'\n' + znacznik + '\n'
		'\n<style>\n' + arkusz_tu + '\n</style>\n'
		'\n<script>\n' + RUCH + '\n</script>\n'
	)

	( TU / ( nazwa + '-en.html' ) ).write_text( calosc )
	( TU / ( nazwa + '-kod.html' ) ).write_text( czapka + CZCIONKI + '\n\n' + znacznik + '\n' )
	( TU / ( nazwa + '-css.css' ) ).write_text( arkusz_tu + '\n' )
	( TU / ( nazwa + '-js.js' ) ).write_text( RUCH + '\n' )

	( TU / ( nazwa + '-podglad.html' ) ).write_text(
		'<!doctype html>\n<html lang="en">\n<meta charset="utf-8">\n'
		'<title>' + opis + '</title>\n'
		'<style>\n'
		'html, body { margin: 0; background: #232a29; color: #eaf3f1;\n'
		'\tfont-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif; }\n'
		'body { background-image: linear-gradient( to right, rgba( 255, 255, 255, .04 ) 1px, transparent 1px ),\n'
		'\tlinear-gradient( to bottom, rgba( 255, 255, 255, .04 ) 1px, transparent 1px );\n'
		'\tbackground-size: 88px 44px; }\n'
		'.podrobka-divi { padding: 40px 0; }\n'
		'.podrobka-divi-rzad { width: 90%; max-width: 1800px; margin: 0 auto; }\n'
		'.pusto { height: 90vh; margin: 0; display: grid; place-items: center;\n'
		'\tfont: 14px "IBM Plex Mono", ui-monospace, monospace; letter-spacing: .12em;\n'
		'\ttext-transform: uppercase; color: rgba( 234, 243, 241, .35 ); }\n'
		'</style>\n'
		# Pusty ekran nad sekcją i pod nią. Bez tego cała sekcja mieści się
		# w oknie zaraz po otwarciu, czyli jest już PO swoim zakresie wjazdu
		# i stoi gotowa: wygląda, jakby animacji nie było wcale. Na stronie nad
		# nią jest hero i dojeżdża się do niej przewijaniem.
		'<div class="podrobka-divi"><div class="podrobka-divi-rzad">\n'
		'<p class="pusto">przewiń w dół</p>\n'
		+ calosc.replace( 'ADRES/', 'zrzuty/' ) +
		'\n<p class="pusto"></p>\n'
		'</div></div>\n'
	)

	return calosc


def osobno_z_tabela( nazwa, blok, opis ):
	"""Blok, w którym siedzi prawdziwa tabela: niesie arkusz i skrypt wtyczki.

	Osobna droga niż `osobno()`, bo tam chodzi właśnie o to, żeby tych
	czterdziestu kilobajtów nie wlec.
	"""
	znacznik = '<div class="lst-mz"><div class="lst-mz-rama">' + blok + '</div></div>'
	arkusz_tu, znacznik = z_odciskiem( skrot( STYL + CSS ), znacznik )
	calosc = (
		CZCIONKI + '\n'
		'\n' + znacznik + '\n'
		'\n<style>\n' + arkusz_tu + '\n</style>\n'
		'\n<script>\n' + JS + '\n\n' + RUCH + '\n</script>\n'
	)

	( TU / ( nazwa + '-en.html' ) ).write_text( calosc )
	( TU / ( nazwa + '-kod.html' ) ).write_text( CZCIONKI + '\n\n' + znacznik + '\n' )
	( TU / ( nazwa + '-css.css' ) ).write_text( arkusz_tu + '\n' )
	( TU / ( nazwa + '-js.js' ) ).write_text( JS + '\n\n' + RUCH + '\n' )

	( TU / ( nazwa + '-podglad.html' ) ).write_text(
		'<!doctype html>\n<html lang="en">\n<meta charset="utf-8">\n'
		'<title>' + opis + '</title>\n'
		'<style>\n'
		'html, body { margin: 0; background: #232a29; color: #eaf3f1;\n'
		'\tfont-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif; }\n'
		'body { background-image: linear-gradient( to right, rgba( 255, 255, 255, .04 ) 1px, transparent 1px ),\n'
		'\tlinear-gradient( to bottom, rgba( 255, 255, 255, .04 ) 1px, transparent 1px );\n'
		'\tbackground-size: 88px 44px; }\n'
		'.podrobka-divi { padding: 40px 0; }\n'
		'.podrobka-divi-rzad { width: 90%; max-width: 1800px; margin: 0 auto; }\n'
		'.pusto { height: 90vh; margin: 0; display: grid; place-items: center;\n'
		'\tfont: 14px "IBM Plex Mono", ui-monospace, monospace; letter-spacing: .12em;\n'
		'\ttext-transform: uppercase; color: rgba( 234, 243, 241, .35 ); }\n'
		'</style>\n'
		'<div class="podrobka-divi"><div class="podrobka-divi-rzad">\n'
		'<p class="pusto">przewiń w dół</p>\n'
		+ calosc +
		'\n<p class="pusto"></p>\n'
		'</div></div>\n'
	)

	return calosc


LEGENDA_HTML = osobno( 'LEGENDA', BLOK_LEGENDA, 'What to look for' )
TELEFON_HTML = osobno_z_tabela( 'TELEFON', BLOK_TELEFON, 'On a phone, every row becomes a card' )


# --- sekcja z telefonem SAMA, odporna na starsze kopie arkusza --------------
#
# Na żywej stronie stoją sekcje wklejone kiedyś w całości, każda z własnym
# <style> z kopią arkusza z tamtego dnia. Te kopie wczytują się PO tej sekcji
# i mają te same selektory, więc wygrywają: telefon robił się niski i płaski,
# wyspa kurczyła się do kropki. Żeby podmienić JEDNĄ sekcję i nie ruszać
# reszty, ta sekcja dostaje identyfikator, a jej arkusz przypina go do każdego
# selektora korzenia. Identyfikator waży więcej niż każda liczba klas, więc
# jej reguły wygrywają ze starszymi kopiami niezależnie od kolejności, także
# te z !important.
#
# Bez skryptu wtyczki: na stronie i tak stoi on już przy sekcji z tabelą,
# a pilnuje się sam, żeby nie podpiąć tej samej tabeli dwa razy. Arkusz
# wtyczki zostaje, bo bez niego tabela w telefonie to goła kratka. Skrypt
# wjazdu i nachylenia zostaje, bo to on rusza telefonem.
KOTWICA = 'lst-mz-fon'


def zakotwicz( css, kotwica ):
	"""Każde odwołanie do korzenia modułu dostaje identyfikator sekcji."""
	css = re.sub( r'(?<![\w-])\.lst-mz-ruch(?![\w-])', '#' + kotwica + '.lst-mz-ruch', css )
	css = re.sub( r'(?<![\w-])\.lst-mz(?![\w-])', '#' + kotwica + '.lst-mz', css )

	return css


# Klasy, których w znaczniku nie ma, a pojawiają się w ruchu: wjazd, najechanie
# i scena 3D telefonu.
FON_ZYWE = { 'lst-mz-ruch', 'jest-tu', 'jest-nad', 'jest-3d' }

_fon_arkusz, _fon_znacznik = z_odciskiem( skrot( STYL ),
	'<div class="lst-mz"><div class="lst-mz-rama">' + BLOK_TELEFON + '</div></div>' )
_fon_znacznik = _fon_znacznik.replace( '<div class="lst-mz" ', '<div class="lst-mz" id="' + KOTWICA + '" ', 1 )
_fon_arkusz = przytnij( zakotwicz( _fon_arkusz, KOTWICA ) + '\n' + skrot( CSS ), _fon_znacznik, FON_ZYWE )

TELEFON_SAM = (
	'<!-- Sekcja "On a phone" w calosci: wklej do JEDNEGO modulu Kod, w miejsce poprzedniej. -->\n'
	+ CZCIONKI + '\n'
	'\n' + _fon_znacznik + '\n'
	'\n<style>\n' + _fon_arkusz + '\n</style>\n'
	'\n<script>\n' + RUCH + '\n</script>\n'
)
( TU / 'TELEFON-sam.html' ).write_text( TELEFON_SAM )
LISTY_HTML = osobno( 'LISTY', BLOK_LISTY, 'What is in which',
	'ZANIM WKLEISZ: w tym pliku jest jeden przycisk Download free z adresem '
	'ADRES-POBIERANIA. Zamien go na adres, pod ktorym lezy wtyczka do pobrania.' )
SKAD_HTML = osobno( 'SKAD', BLOK_SKAD, 'Where it comes from',
	'ZANIM WKLEISZ: zamien w tym pliku kazde ADRES/ na adres folderu '
	'z Multimediow, np. https://rizznet.pl/wp-content/uploads/2026/10/ '
	'Bez tego przegladarka prosi o plik ADRES/mz-wyglad.png, ktorego nie ma, '
	'i trzy zrzuty sie nie pokazuja. Adres bierzesz tak: Multimedia > klikasz '
	'wgrany zrzut > kopiujesz adres pliku > odcinasz z niego sama nazwe pliku.' )

# --- to samo, ale z wpisanym adresem Multimediów ----------------------------
#
# Pliki z `ADRES` zostają: są przenośne i przeżyją przeniesienie witryny albo
# wgranie zrzutów jeszcze raz w innym miesiącu. Obok nich leżą bliźniaki, w
# których adres jest już wpisany, bo podmiana w edytorze Divi to miejsce, w
# którym łatwo o pomyłkę, a bez niej strona prosi o plik „ADRES/mz-wyglad.png”
# i zrzuty się nie pokazują.
ADRES_MEDIA = 'https://rizznet.pl/wp-content/uploads/2026/10/'

for plik in sorted( TU.glob( '*.html' ) ):
	if plik.name.endswith( '-gotowe.html' ):
		continue

	tresc = plik.read_text()
	if 'ADRES/' not in tresc:
		continue

	tresc = tresc.replace( 'ADRES/', ADRES_MEDIA )
	# Uwaga o podmianie przestaje być prawdą w pliku, w którym podmiana już jest.
	tresc = re.sub(
		r'<!-- ZANIM WKLEISZ:.*?-->',
		'<!-- Adres Multimediow jest juz wpisany: ' + ADRES_MEDIA + ' '
		'Wklejasz bez zadnych podmian. Zrzuty musza lezec pod tym adresem pod '
		'nazwami mz-wyglad.png, mz-reguly.png i mz-kolumny.png. -->',
		tresc, flags = re.S )
	( TU / plik.name.replace( '.html', '-gotowe.html' ) ).write_text( tresc )

print( 'ok', len( STRONA ), 'znaków modułu' )
for nazwa, ile in (
	( 'sekcja z tabelą', len( STOL ) ),
	( 'What to look for', len( LEGENDA_HTML ) ),
	( 'Where it comes from', len( SKAD_HTML ) ),
	( 'On a phone', len( TELEFON_HTML ) ),
	( 'What is in which', len( LISTY_HTML ) ),
):
	print( '   ', nazwa, '-', ile, 'znaków' )

