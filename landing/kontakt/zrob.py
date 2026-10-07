# -*- coding: utf-8 -*-
"""
Podstrona „Kontakt”, jeden moduł Kod: tytuł, formularz kontaktowy, kafel dla
klientów Pro i kafel z tym, co dopisać do wiadomości.

Formularz WYSYŁA DIVI. W znaczniku stoi shortcode wbudowanego formularza
kontaktowego Divi ([et_pb_contact_form]), który moduł Kod wykonuje — tak samo,
jak wykonał na żywej stronie przykład shortcode'u wtyczki. Divi robi resztę:
sprawdza pola, pyta o prosty rachunek przeciw botom i wysyła wiadomość na adres
administratora z Ustawienia → Ogólne (albo na adres wpisany w email="").
Arkusz tej strony ubiera ten formularz w wygląd witryny.

Gdyby shortcode się nie wykonał (inny kreator niż Divi), skrypt podstawia
w tym miejscu zapasowy formularz o tym samym wyglądzie, który otwiera program
pocztowy z gotową wiadomością na ADRES-EMAIL.

Ten sam materiał co reszta witryny: ciemne płyty z miętową krawędzią,
światło biegnące po obwodzie, ikonki zapalające się pod kursorem.

Uruchomienie: python3 landing/kontakt/zrob.py
"""

import pathlib
import re

TU = pathlib.Path( __file__ ).resolve().parent

# Tylko dla formularza zapasowego. Prawdziwy formularz Divi wysyła na adres
# administratora witryny, więc bez podmiany też działa.
EMAIL = 'ADRES-EMAIL'


def formularz_divi( pola, przycisk, dzieki ):
	"""Shortcode formularza Divi w JEDNEJ linijce — złamany wiersz Divi zamienia na <br />."""
	return ( '[et_pb_contact_form captcha="on" use_redirect="off" submit_button_text="' + przycisk + '"'
		' success_message="' + dzieki + '" title=""]'
		+ ''.join( '[et_pb_contact_field field_id="' + i + '" field_title="' + t + '" field_type="' + typ + '"'
			' fullwidth_field="' + ( 'on' if pelne else 'off' ) + '" required_mark="on"][/et_pb_contact_field]'
			for i, t, typ, pelne in pola )
		+ '[/et_pb_contact_form]' )


EN = {
	'oko': 'Contact',
	'tytul': 'Talk to the people who wrote it.',
	'wstep': 'No ticket queue and no chatbot. Every message is read by someone who works on the plugin, '
		'and we answer in English or Polish.',
	'formularz': ( 'Write to us', 'Questions, bugs, ideas, invoices &mdash; anything at all.',
		( ( 'Name', 'Name', 'input', False ), ( 'Email', 'E-mail', 'email', False ), ( 'Message', 'Message', 'text', True ) ),
		'Send message', 'Thank you! We will get back to you soon.' ),
	'pro': ( 'Pro customers', 'Write from the address you bought with &mdash; we will see it is you, and you go first: '
		'an answer within 24 hours.', 'Write to us' ),
	'szybciej': ( 'Get an answer faster', 'Four things that save a round of questions.' ),
	'lista': (
		( 'arkusz', 'The link to your sheet', 'Or a copy with sample data, if the real one is private.' ),
		( 'wersja', 'Your WordPress and plugin version', 'Both are on the Plugins screen.' ),
		( 'strona', 'The page where the table sits', 'So we can see what your visitors see.' ),
		( 'zrzut', 'A screenshot', 'If something looks wrong, a picture says it faster.' ),
	),
	'faq': ( 'Most questions are already answered.', 'Read the FAQ', '/#faq' ),
	'zapas': ( 'Opens your e-mail app with the message ready to send.', ),
}

PL = {
	'oko': 'Kontakt',
	'tytul': 'Napisz do ludzi, którzy to zrobili.',
	'wstep': 'Bez kolejki zgłoszeń i bez czatbota. Każdą wiadomość czyta ktoś, kto pracuje nad wtyczką, '
		'a odpowiadamy po polsku albo po angielsku.',
	'formularz': ( 'Napisz do nas', 'Pytania, błędy, pomysły, faktury &mdash; cokolwiek.',
		( ( 'Name', 'Imię', 'input', False ), ( 'Email', 'E-mail', 'email', False ), ( 'Message', 'Wiadomość', 'text', True ) ),
		'Wyślij wiadomość', 'Dziękujemy! Odpiszemy wkrótce.' ),
	'pro': ( 'Klienci Pro', 'Napisz z adresu, z którego kupiłeś &mdash; zobaczymy, że to Ty, i jesteś pierwszy w kolejce: '
		'odpowiedź w ciągu 24 godzin.', 'Napisz do nas' ),
	'szybciej': ( 'Szybsza odpowiedź', 'Cztery rzeczy, które oszczędzają rundę pytań.' ),
	'lista': (
		( 'arkusz', 'Link do arkusza', 'Albo kopia z przykładowymi danymi, jeśli prawdziwy jest prywatny.' ),
		( 'wersja', 'Wersja WordPressa i wtyczki', 'Obie są na ekranie Wtyczki.' ),
		( 'strona', 'Strona, na której stoi tabela', 'Żebyśmy widzieli to, co widzą Twoi odwiedzający.' ),
		( 'zrzut', 'Zrzut ekranu', 'Jeśli coś wygląda źle, obrazek powie to szybciej.' ),
	),
	'faq': ( 'Na większość pytań odpowiedź już jest.', 'Przeczytaj FAQ', '/#pytania' ),
	'zapas': ( 'Otworzy Twój program pocztowy z gotową wiadomością.', ),
}


def _ikona( srodek ):
	return ( '<svg class="lst-kon-rys" viewBox="0 0 28 28" fill="none" stroke="currentColor" '
		'stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" '
		'focusable="false">' + srodek + '</svg>' )


"""
Ikonki. Każda ma część, która pod kursorem coś robi: koperta się otwiera,
piorun błyska, kropki mrugają, aparat pstryka. Ruch mówi to samo, co tytuł obok.
"""
IKONY = {
	'list': _ikona( '<rect x="3.5" y="7" width="21" height="15" rx="2.5"></rect>'
		'<path class="lst-kon-klapka" d="m4.5 8.5 9.5 7 9.5-7"></path>' ),
	'piorun': _ikona( '<path class="lst-kon-blysk" d="M15.5 3.5 6.5 16h7l-1 8.5 9-12.5h-7Z"></path>' ),
	'arkusz': _ikona( '<rect x="4" y="5" width="20" height="18" rx="2.5"></rect><path d="M4 11h20M4 17h20M11 5v18"></path>' ),
	'wersja': _ikona( '<path d="M5 5h8.5l9.5 9.5-8.5 8.5L5 13.5Z"></path><circle class="lst-kon-mrug" cx="9.5" cy="9.5" r="1.6"></circle>' ),
	'strona': _ikona( '<rect x="3.5" y="5" width="21" height="18" rx="2.5"></rect><path d="M3.5 10h21"></path>'
		'<path class="lst-kon-mrug" d="M7 7.5h.01M9.5 7.5h.01M12 7.5h.01"></path>' ),
	'zrzut': _ikona( '<path d="M4 9.5A1.5 1.5 0 0 1 5.5 8h3l2-2.5h7l2 2.5h3A1.5 1.5 0 0 1 24 9.5v11a1.5 1.5 0 0 1-1.5 1.5h-17A1.5 1.5 0 0 1 4 20.5Z"></path>'
		'<circle class="lst-kon-migawka" cx="14" cy="14.5" r="4"></circle>' ),
}

CZCIONKI = ( '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500'
	'&family=IBM+Plex+Sans:wght@400;500;600&family=Inria+Serif:wght@400&display=swap">' )

STYL = r'''
.lst-kon {
	--lst-mieta: 95, 227, 207;
	--lst-panel: #1b2221;
	--lst-panel-dol: #161d1c;
	--lst-tekst: #eaf3f1;
	--lst-tekst-2: #9db3b0;
	--lst-tekst-3: #8fa5a2;
	--lst-promien: 18px;
	--lst-mono: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;
	--lst-serif: "Inria Serif", "Iowan Old Style", Georgia, serif;
	--lst-pelna: 100vw;

	font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif;
	color: var( --lst-tekst );

	/* Pełna szerokość okna, jak reszta sekcji: 90% liczone od wiersza Divi,
	   który sam ma 90%, dawałoby stronę węższą niż pozostałe. */
	width: var( --lst-pelna );
	margin: 0 calc( 50% - var( --lst-pelna ) / 2 );
	overflow-x: clip;
	/* Góra o 30% niższa niż na innych podstronach: kontakt ma od razu
	   pokazać formularz, a nie powietrze. */
	padding: clamp( 1.75rem, 4.2vw, 3.5rem ) 0 clamp( 3rem, 6vw, 5rem );
}

.lst-kon.lst-kon { border: 0 !important; outline: 0 !important; background: none !important; }
.lst-kon * { box-sizing: border-box; }
.lst-kon br { display: none; }

/* Reset stoi PRZED resztą reguł: ma wagę zero i inaczej zabrałby im wygląd. */
.lst-kon :where( div, p, span, a, h1, ul, li, button ) {
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
}

.lst-kon .lst-kon-rama { width: 90%; max-width: 1800px; margin-inline: auto; }

/* ---------- góra ---------- */

.lst-kon .lst-kon-oko {
	font-family: var( --lst-mono );
	font-size: .875rem;   /* 14 px */
	letter-spacing: .06em;
	color: rgb( var( --lst-mieta ) );
}

/* Tytuł strony: ten sam szeryf i ta sama skala co tytuły sekcji. */
.lst-kon .lst-kon-tytul {
	font-family: var( --lst-serif );
	font-weight: 400;
	font-size: clamp( 2.4rem, 5vw, 4.4rem );
	line-height: 1.05;
	letter-spacing: -.01em;
	margin-top: .4rem;
	max-width: 24ch;
	/* Wiersze równej długości: bez jednego słowa samotnie w drugim wierszu. */
	text-wrap: balance;
}

.lst-kon .lst-kon-wstep {
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.6;
	color: var( --lst-tekst-2 );
	margin-top: 1.1rem;
	max-width: 44rem;
}

/* ---------- kafle ---------- */

/* Formularz po lewej, szerszy; po prawej kafel Pro i wskazówki, jeden pod
   drugim. To, co dopisać do wiadomości, stoi obok miejsca, w którym się pisze. */
.lst-kon .lst-kon-uklad {
	display: grid;
	grid-template-columns: minmax( 0, 1.35fr ) minmax( 0, 1fr );
	gap: clamp( .9rem, 1.4vw, 1.25rem );
	align-items: stretch;
	margin-top: clamp( 1.6rem, 3vw, 2.4rem );
}

.lst-kon .lst-kon-bok { display: grid; gap: clamp( .9rem, 1.4vw, 1.25rem ); }

.lst-kon .lst-kon-kafel {
	position: relative;
	display: flex;
	flex-direction: column;
	padding: clamp( 1.3rem, 2.2vw, 1.75rem ) clamp( 1.2rem, 2vw, 1.7rem );
	border-radius: var( --lst-promien );
	border: 1px solid rgba( var( --lst-mieta ), .18 ) !important;
	background-color: var( --lst-panel ) !important;
	background-image:
		linear-gradient( 180deg, rgba( 255, 255, 255, .04 ), rgba( 255, 255, 255, 0 ) 42% ),
		radial-gradient( 20rem 13rem at var( --lst-x, 14% ) -8%, rgba( var( --lst-mieta ), .15 ), transparent ),
		linear-gradient( var( --lst-panel ), var( --lst-panel-dol ) ) !important;
	box-shadow:
		inset 0 1px 0 rgba( 255, 255, 255, .05 ),
		0 20px 44px -26px rgba( 0, 0, 0, .95 ),
		0 2px 10px -6px rgba( 0, 0, 0, .6 );
}

/* Pierwsza droga jest główna: mocniejsza krawędź i poświata od lewej. */
.lst-kon .lst-kon-kafel.jest-glowna {
	border-color: rgba( var( --lst-mieta ), .32 ) !important;
	background-image:
		linear-gradient( 160deg, rgba( var( --lst-mieta ), .14 ), rgba( var( --lst-mieta ), .03 ) 50%, rgba( 0, 0, 0, 0 ) 80% ),
		linear-gradient( var( --lst-panel ), var( --lst-panel-dol ) ) !important;
}

.lst-kon .lst-kon-ikona {
	display: grid;
	place-items: center;
	width: 3rem;
	height: 3rem;
	border-radius: 14px;
	border: 1px solid rgba( var( --lst-mieta ), .26 ) !important;
	background-color: rgba( var( --lst-mieta ), .08 ) !important;
	color: rgb( var( --lst-mieta ) );
	flex: none;
}

.lst-kon .lst-kon-rys { width: 1.65rem; height: 1.65rem; overflow: visible; }
.lst-kon .lst-kon-klapka,
.lst-kon .lst-kon-blysk,
.lst-kon .lst-kon-migawka { transform-box: fill-box; transform-origin: 50% 50%; }
.lst-kon .lst-kon-klapka { transform-origin: 50% 0; }

.lst-kon .lst-kon-kafel-tytul {
	font-family: var( --lst-serif );
	font-weight: 400;
	font-size: clamp( 1.5rem, 2.1vw, 1.85rem );
	line-height: 1.15;
	margin-top: 1.1rem;
}

.lst-kon .lst-kon-kafel-tekst {
	font-size: 1.125rem;   /* 18 px */
	line-height: 1.55;
	color: var( --lst-tekst-2 );
	margin-top: .5rem;
}

/*
 * Formularz. Ten sam wygląd dla formularza Divi i dla zapasowego: pola jak
 * ekran (ciemniejsze od kafla, z miętową krawędzią po kliknięciu), przycisk
 * jak wszystkie główne przyciski witryny. Arkusz Divi pisze swoje pola
 * z „!important” (szare tło, zero zaokrągleń), więc tu też trzeba.
 */
/* Formularz wypełnia kafel do dołu, a rośnie pole wiadomości: kafel kończy
   się na tej samej wysokości co kolumna obok, bez pustego miejsca pod
   przyciskiem. */
.lst-kon .lst-kon-formularz,
.lst-kon .lst-kon-formularz .et_pb_contact_form_container,
.lst-kon .lst-kon-formularz .et_pb_contact { display: flex !important; flex-direction: column; flex: 1 1 auto; }
.lst-kon .lst-kon-formularz { margin-top: 1.3rem; }
.lst-kon .lst-kon-formularz form { flex: 1 1 auto; grid-template-rows: auto minmax( 11rem, 1fr ) auto; }
.lst-kon .et_pb_contact_form > .et_pb_contact_field[data-type="text"],
.lst-kon .lst-kon-pole.jest-pelne { display: flex !important; }
.lst-kon .et_pb_contact_form > .et_pb_contact_field[data-type="text"] textarea,
.lst-kon .lst-kon-pole.jest-pelne textarea { flex: 1; height: auto !important; }

.lst-kon .et_pb_contact_form_container,
.lst-kon .et_pb_contact { margin: 0 !important; padding: 0 !important; background: none !important; border: 0 !important; }
.lst-kon .et_pb_contact_main_title { display: none !important; }

.lst-kon .et_pb_contact_form,
.lst-kon .lst-kon-zapas {
	display: grid !important;
	grid-template-columns: repeat( 2, minmax( 0, 1fr ) );
	gap: .85rem;
	margin: 0 !important;
}

/* „clearfix” Divi dokłada formularzowi niewidoczny ::after, który w siatce
   zająłby własną komórkę i dołożył odstęp pod przyciskiem. */
.lst-kon .et_pb_contact_form::before,
.lst-kon .et_pb_contact_form::after,
.lst-kon .et_pb_contact_right p::after { display: none !important; }

.lst-kon .et_pb_contact_form > .et_pb_contact_field,
.lst-kon .lst-kon-pole {
	float: none !important;
	width: auto !important;
	margin: 0 !important;
	padding: 0 !important;
}

.lst-kon .et_pb_contact_form > .et_pb_contact_field:not( .et_pb_contact_field_half ),
.lst-kon .lst-kon-pole.jest-pelne,
.lst-kon .et_contact_bottom_container,
.lst-kon .lst-kon-dol { grid-column: 1 / -1; }

/* Etykiety czyta czytnik ekranu; widać podpowiedź w samym polu. */
.lst-kon .et_pb_contact_form_label,
.lst-kon .lst-kon-etykieta {
	position: absolute !important;
	width: 1px !important;
	height: 1px !important;
	overflow: hidden !important;
	clip: rect( 0 0 0 0 ) !important;
}

.lst-kon .et_pb_contact_form input.input,
.lst-kon .et_pb_contact_form textarea,
.lst-kon .lst-kon-zapas input,
.lst-kon .lst-kon-zapas textarea {
	display: block;
	width: 100% !important;
	padding: .85rem 1rem !important;
	border: 1px solid rgba( var( --lst-mieta ), .2 ) !important;
	border-radius: 12px !important;
	background-color: #0d1413 !important;
	color: var( --lst-tekst ) !important;
	font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif !important;
	font-size: 1.125rem !important;   /* 18 px */
	line-height: 1.5 !important;
	box-shadow: none !important;
	transition: border-color .2s ease, box-shadow .2s ease;
}

.lst-kon .et_pb_contact_form textarea,
.lst-kon .lst-kon-zapas textarea { min-height: 11rem; resize: vertical; }

.lst-kon .et_pb_contact_form input::placeholder,
.lst-kon .et_pb_contact_form textarea::placeholder,
.lst-kon .lst-kon-zapas input::placeholder,
.lst-kon .lst-kon-zapas textarea::placeholder { color: var( --lst-tekst-3 ) !important; opacity: 1; }

.lst-kon .et_pb_contact_form input.input:focus,
.lst-kon .et_pb_contact_form textarea:focus,
.lst-kon .lst-kon-zapas input:focus,
.lst-kon .lst-kon-zapas textarea:focus {
	outline: 0 !important;
	border-color: rgb( var( --lst-mieta ) ) !important;
	box-shadow: 0 0 0 3px rgba( var( --lst-mieta ), .18 ) !important;
}

/* Pole, które Divi oznaczył jako źle wypełnione. */
.lst-kon .et_pb_contact_form .et_contact_error { border-color: #ff8f7a !important; }

.lst-kon .et_contact_bottom_container,
.lst-kon .lst-kon-dol {
	display: flex !important;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: .8rem 1.2rem;
	float: none !important;
	margin: .3rem 0 0 !important;
}

/* Rachunek przeciw botom („3 + 7 =”) Divi stawia przed przyciskiem. */
.lst-kon .et_pb_contact_right {
	display: flex;
	align-items: center;
	float: none !important;
	margin: 0 !important;
	color: var( --lst-tekst-2 );
	font-family: var( --lst-mono );
	font-size: 1.125rem;
}

.lst-kon .et_pb_contact_right p { display: flex; align-items: center; gap: .5rem; margin: 0 !important; padding: 0 !important; }
.lst-kon .et_pb_contact_right input.et_pb_contact_captcha { width: 4.5rem !important; padding: .55rem .7rem !important; text-align: center; }

.lst-kon .lst-kon-uwaga { font-size: .875rem; color: var( --lst-tekst-3 ); }

.lst-kon .et_pb_contact_submit,
.lst-kon .lst-kon-wyslij {
	margin: 0 0 0 auto !important;
	padding: .7rem 1.5rem !important;
	border: 0 !important;
	border-radius: 999px !important;
	background: rgb( var( --lst-mieta ) ) !important;
	color: #06100f !important;
	font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif !important;
	font-size: 1.125rem !important;
	font-weight: 600 !important;
	line-height: 1.2 !important;
	letter-spacing: normal !important;
	text-transform: none !important;
	cursor: pointer;
	box-shadow: 0 10px 30px -12px rgba( var( --lst-mieta ), .7 );
	transition: transform .2s cubic-bezier( .23, 1, .32, 1 ), box-shadow .2s ease;
}

/* Divi dokłada przyciskom strzałkę w ::after; tu jej nie ma. */
.lst-kon .et_pb_contact_submit::after { display: none !important; }

/* Komunikat Divi: błędy przed wysłaniem albo podziękowanie po. */
.lst-kon .et-pb-contact-message { margin: 0 0 .8rem !important; color: var( --lst-tekst ); font-size: 1.125rem; }
.lst-kon .et-pb-contact-message:empty { display: none; }
.lst-kon .et-pb-contact-message ul { margin: .4rem 0 0 1.2rem !important; color: #ffb4a6; }
.lst-kon .et-pb-contact-message p {
	padding: .9rem 1rem !important;
	border-radius: 12px;
	border: 1px solid rgba( var( --lst-mieta ), .3 );
	background: rgba( var( --lst-mieta ), .08 );
}

.lst-kon .lst-kon-przyciski { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: auto; padding-top: 1.4rem; }

.lst-kon .lst-kon-przycisk {
	display: inline-flex;
	align-items: center;
	padding: .65rem 1.25rem;
	border-radius: 999px;
	font-size: 1.125rem;   /* 18 px */
	font-weight: 600;
	line-height: 1.2;
	white-space: nowrap;
	text-decoration: none !important;
	transition: transform .2s cubic-bezier( .23, 1, .32, 1 ), box-shadow .2s ease, background-color .2s ease;
}

.lst-kon .lst-kon-przycisk.jest-glowny {
	background-color: rgb( var( --lst-mieta ) ) !important;
	color: #06100f !important;
	box-shadow: 0 10px 30px -12px rgba( var( --lst-mieta ), .7 );
}

.lst-kon .lst-kon-przycisk.jest-drugi {
	border: 1px solid rgba( var( --lst-mieta ), .35 ) !important;
	color: var( --lst-tekst ) !important;
}

/* ---------- szybciej ---------- */

.lst-kon .lst-kon-szybciej { --lst-x: 90%; }

.lst-kon .lst-kon-lista {
	display: grid;
	gap: 1rem;
	margin-top: 1.2rem;
}

.lst-kon .lst-kon-punkt { display: grid; grid-template-columns: 3rem minmax( 0, 1fr ); gap: .9rem; align-items: start; }
.lst-kon .lst-kon-punkt-tytul { font-size: 1.125rem; font-weight: 600; line-height: 1.35; }
.lst-kon .lst-kon-punkt-tekst { font-size: 1.125rem; line-height: 1.45; color: var( --lst-tekst-2 ); }

.lst-kon .lst-kon-faq {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: .8rem 1.2rem;
	margin-top: 1.6rem;
	padding-top: 1.2rem;
	border-top: 1px solid rgba( 138, 168, 163, .14 ) !important;
	font-size: 1.125rem;
	color: var( --lst-tekst-2 );
}

/* ---------- światło biegnące po krawędzi ---------- */

/*
 * To samo światło co na kaflach strony głównej: pełne koło koloru obrócone
 * o kąt, przycięte maską do samej ramki. Kąt musi być zgłoszony przez
 * @property, inaczej nie da się go płynnie obracać.
 */
@property --lst-obrot {
	syntax: "<angle>";
	initial-value: 0deg;
	inherits: true;
}

.lst-kon .lst-kon-kafel::before,
.lst-kon .lst-kon-kafel::after {
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

.lst-kon .lst-kon-kafel::after { padding: 3.5px; filter: blur( 6px ); opacity: .75; }

.lst-kon .lst-kon-kafel { animation: lst-kon-obieg 10s linear infinite; }
.lst-kon .lst-kon-kafel:nth-child( 2 ) { animation-delay: -3.3s; }
.lst-kon .lst-kon-kafel:nth-child( 3 ) { animation-delay: -6.6s; }
.lst-kon .lst-kon-szybciej { animation-duration: 14s; animation-delay: -1.8s; }

@keyframes lst-kon-obieg { to { --lst-obrot: 1turn; } }
@keyframes lst-kon-otworz { 50% { transform: scaleY( -.6 ); } }
@keyframes lst-kon-mrugaj { 50% { opacity: .15; } }
@keyframes lst-kon-blyskaj { 30% { transform: scale( 1.18 ) rotate( -6deg ); } 60% { transform: scale( .96 ); } }
@keyframes lst-kon-pstryk { 40% { transform: scale( .55 ); } }

/* ---------- najechanie ---------- */

@media ( hover: hover ) and ( pointer: fine ) {
	.lst-kon .lst-kon-kafel { transition: transform .25s cubic-bezier( .23, 1, .32, 1 ), box-shadow .25s ease, border-color .25s ease; }
	.lst-kon .lst-kon-kafel:hover {
		transform: translateY( -2px );
		border-color: rgba( var( --lst-mieta ), .4 ) !important;
		box-shadow:
			inset 0 1px 0 rgba( 255, 255, 255, .07 ),
			0 26px 52px -26px rgba( 0, 0, 0, .95 ),
			0 3px 12px -6px rgba( 0, 0, 0, .65 );
	}

	/* Ikonka zapala się na mięto, tak jak na stronie głównej, a jej rysunek
	   robi swoje: koperta się otwiera, w dymku mrugają kropki, piorun błyska,
	   aparat pstryka. */
	.lst-kon .lst-kon-ikona {
		transition: background-color .25s ease, color .25s ease, border-color .25s ease,
			transform .3s cubic-bezier( .23, 1, .32, 1 ), box-shadow .3s ease;
	}

	.lst-kon .lst-kon-kafel.jest-droga:hover .lst-kon-ikona,
	.lst-kon .lst-kon-punkt:hover .lst-kon-ikona {
		background-color: rgb( var( --lst-mieta ) ) !important;
		border-color: rgb( var( --lst-mieta ) ) !important;
		color: #06100f;
		transform: translateY( -2px ) rotate( -4deg );
		box-shadow: 0 10px 24px -10px rgba( var( --lst-mieta ), .8 );
	}

	.lst-kon .lst-kon-kafel:hover .lst-kon-klapka { animation: lst-kon-otworz .6s cubic-bezier( .23, 1, .32, 1 ); }
	.lst-kon .lst-kon-kafel:hover .lst-kon-mrug,
	.lst-kon .lst-kon-punkt:hover .lst-kon-mrug { animation: lst-kon-mrugaj .45s steps( 2, jump-none ) 3; }
	.lst-kon .lst-kon-kafel:hover .lst-kon-blysk { animation: lst-kon-blyskaj .5s cubic-bezier( .23, 1, .32, 1 ); }
	.lst-kon .lst-kon-punkt:hover .lst-kon-migawka { animation: lst-kon-pstryk .35s ease; }

	.lst-kon .lst-kon-przycisk.jest-glowny:hover { transform: translateY( -2px ); box-shadow: 0 16px 34px -12px rgba( var( --lst-mieta ), .85 ); }
	.lst-kon .lst-kon-przycisk.jest-drugi:hover { transform: translateY( -2px ); background-color: rgba( var( --lst-mieta ), .1 ) !important; }
	.lst-kon .et_pb_contact_submit:hover,
	.lst-kon .lst-kon-wyslij:hover { transform: translateY( -2px ); box-shadow: 0 16px 34px -12px rgba( var( --lst-mieta ), .85 ); }
}

/* ---------- utwardzenie na wrogie motywy ---------- */

.lst-kon .lst-kon-oko,
.lst-kon .lst-kon-tytul,
.lst-kon .lst-kon-wstep,
.lst-kon .lst-kon-kafel-tytul,
.lst-kon .lst-kon-kafel-tekst,
.lst-kon .lst-kon-punkt-tytul,
.lst-kon .lst-kon-punkt-tekst {
	margin-inline: 0 !important;
	margin-bottom: 0 !important;
	padding: 0 !important;
	background: none !important;
	border: 0 !important;
	text-align: left !important;
	text-transform: none !important;
}

.lst-kon .lst-kon-oko { font-family: var( --lst-mono ) !important; letter-spacing: .06em !important; color: rgb( var( --lst-mieta ) ) !important; margin-top: 0 !important; }
.lst-kon .lst-kon-tytul,
.lst-kon .lst-kon-kafel-tytul { font-family: var( --lst-serif ) !important; color: var( --lst-tekst ) !important; letter-spacing: normal !important; }
.lst-kon .lst-kon-tytul { margin-top: .4rem !important; }
.lst-kon .lst-kon-kafel-tytul { margin-top: 1.1rem !important; }
.lst-kon .lst-kon-wstep,
.lst-kon .lst-kon-kafel-tekst,
.lst-kon .lst-kon-punkt-tekst { font-family: inherit !important; color: var( --lst-tekst-2 ) !important; letter-spacing: normal !important; }
.lst-kon .lst-kon-wstep { margin-top: 1.1rem !important; }
.lst-kon .lst-kon-kafel-tekst { margin-top: .5rem !important; }
.lst-kon .lst-kon-punkt-tytul { font-family: inherit !important; color: var( --lst-tekst ) !important; margin-top: 0 !important; letter-spacing: normal !important; }
.lst-kon .lst-kon-punkt-tekst { margin-top: 0 !important; }
.lst-kon .lst-kon-przycisk { font-family: "IBM Plex Sans", -apple-system, "Segoe UI", Roboto, sans-serif !important; text-transform: none !important; letter-spacing: normal !important; }

/* Obramowanie, które Divi albo motyw nadaje opakowaniu modułu. */
.et_pb_module:has( .lst-kon ),
.et_pb_column:has( .lst-kon ),
.et_pb_row:has( .lst-kon ) { border: 0 !important; outline: 0 !important; }

@media ( max-width: 1100px ) {
	.lst-kon .lst-kon-uklad { grid-template-columns: minmax( 0, 1fr ); }
	/* Jedna kolumna: nie ma czego dorównywać, pole wiadomości ma swoją wysokość. */
	.lst-kon .lst-kon-formularz form { grid-template-rows: none; }
}

@media ( max-width: 700px ) {
	.lst-kon .et_pb_contact_form,
	.lst-kon .lst-kon-zapas { grid-template-columns: minmax( 0, 1fr ); }
}

@media ( prefers-reduced-motion: reduce ) {
	.lst-kon .lst-kon-kafel { animation: none !important; transition: none; }
	.lst-kon .lst-kon-ikona { transition: none; }
	.lst-kon [class*="lst-kon-"] { animation: none !important; }
}

@media print {
	.lst-kon .lst-kon-kafel { animation: none !important; }
}
'''

# Formularz zapasowy. Wchodzi tylko wtedy, gdy shortcode Divi się nie
# wykonał i w miejscu formularza stoi goły tekst „[et_pb_contact_form…”.
# Ma te same pola i ten sam wygląd; wysyłka otwiera program pocztowy
# z gotową wiadomością.
SKRYPT = r"""( function () {
	var gotowe = function ( zrob ) {
		if ( 'loading' === document.readyState ) { document.addEventListener( 'DOMContentLoaded', zrob ); } else { zrob(); }
	};
	gotowe( function () {
		var miejsca = document.querySelectorAll( '.lst-kon-formularz' );
		for ( var i = 0; i < miejsca.length; i++ ) {
			var m = miejsca[ i ];
			if ( m.querySelector( 'form' ) || m.textContent.indexOf( 'et_pb_contact_form' ) < 0 ) { continue; }
			var pola = {
				pola: m.getAttribute( 'data-pola' ).split( ';' ).map( function ( x ) { var c = x.split( '|' ); c[ 3 ] = '1' === c[ 3 ]; return c; } ),
				przycisk: m.getAttribute( 'data-przycisk' ),
				uwaga: m.getAttribute( 'data-uwaga' ),
				adres: m.getAttribute( 'data-adres' ),
				temat: m.getAttribute( 'data-temat' ),
			};
			var f = document.createElement( 'form' );
			f.className = 'lst-kon-zapas';
			var html = '';
			for ( var p = 0; p < pola.pola.length; p++ ) {
				var pole = pola.pola[ p ];
				var id = 'lst-kon-' + pole[ 0 ] + '-' + i;
				html += '<p class="lst-kon-pole' + ( pole[ 3 ] ? ' jest-pelne' : '' ) + '"><label class="lst-kon-etykieta" for="' + id + '">' + pole[ 1 ] + '</label>'
					+ ( 'text' === pole[ 2 ]
						? '<textarea id="' + id + '" name="' + pole[ 0 ] + '" rows="6" required placeholder="' + pole[ 1 ] + '"></textarea>'
						: '<input id="' + id + '" name="' + pole[ 0 ] + '" type="' + ( 'email' === pole[ 2 ] ? 'email' : 'text' ) + '" required placeholder="' + pole[ 1 ] + '">' )
					+ '</p>';
			}
			html += '<div class="lst-kon-dol"><span class="lst-kon-uwaga">' + pola.uwaga + '</span>'
				+ '<button class="lst-kon-wyslij" type="submit">' + pola.przycisk + '</button></div>';
			f.innerHTML = html;
			f.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var d = this.elements;
				var tresc = d.Message.value + '\n\n' + d.Name.value + ' <' + d.Email.value + '>';
				window.location.href = 'mailto:' + pola.adres + '?subject=' + encodeURIComponent( pola.temat + ' ' + d.Name.value )
					+ '&body=' + encodeURIComponent( tresc );
			} );
			m.innerHTML = '';
			m.appendChild( f );
		}
	} );
} )();"""


def zbuduj( t ):
	tytul, tekst, pola, przycisk, dzieki = t[ 'formularz' ]
	# Bez nawiasów kwadratowych (Divi i WordPress biorą je za shortcode):
	# pola rozdzielone średnikiem, części pola pionową kreską.
	zapas = ( ' data-pola="' + ';'.join( '|'.join( ( i, tt, typ, '1' if pelne else '0' ) ) for i, tt, typ, pelne in pola ) + '"'
		' data-przycisk="' + przycisk + '" data-uwaga="' + t[ 'zapas' ][ 0 ] + '" data-adres="' + EMAIL + '"'
		' data-temat="' + tytul + '"' )
	formularz = ( '<div class="lst-kon-kafel jest-droga jest-glowna" id="formularz">'
		'<span class="lst-kon-ikona">' + IKONY[ 'list' ] + '</span>'
		'<p class="lst-kon-kafel-tytul">' + tytul + '</p>'
		'<p class="lst-kon-kafel-tekst">' + tekst + '</p>'
		'<div class="lst-kon-formularz"' + zapas + '>' + formularz_divi( pola, przycisk, dzieki ) + '</div></div>' )

	pro_tytul, pro_tekst, pro_przycisk = t[ 'pro' ]
	pro = ( '<div class="lst-kon-kafel jest-droga">'
		'<span class="lst-kon-ikona">' + IKONY[ 'piorun' ] + '</span>'
		'<p class="lst-kon-kafel-tytul">' + pro_tytul + '</p>'
		'<p class="lst-kon-kafel-tekst">' + pro_tekst + '</p>'
		'<div class="lst-kon-przyciski"><a class="lst-kon-przycisk jest-drugi" href="#formularz">' + pro_przycisk + '</a></div></div>' )

	sz_tytul, sz_tekst = t[ 'szybciej' ]
	pytanie, faq_przycisk, adres_faq = t[ 'faq' ]
	szybciej = ( '<div class="lst-kon-kafel lst-kon-szybciej">'
		'<p class="lst-kon-kafel-tytul">' + sz_tytul + '</p><p class="lst-kon-kafel-tekst">' + sz_tekst + '</p>'
		'<div class="lst-kon-lista">' + ''.join(
			'<div class="lst-kon-punkt"><span class="lst-kon-ikona">' + IKONY[ ikona ] + '</span><div>'
			'<p class="lst-kon-punkt-tytul">' + pt + '</p><p class="lst-kon-punkt-tekst">' + po + '</p></div></div>'
			for ikona, pt, po in t[ 'lista' ] ) + '</div>'
		'<div class="lst-kon-faq"><span>' + pytanie + '</span>'
		'<a class="lst-kon-przycisk jest-drugi" href="' + adres_faq + '">' + faq_przycisk + '</a></div></div>' )

	znacznik = ( '<div class="lst-kon"><div class="lst-kon-rama">'
		'<p class="lst-kon-oko">' + t[ 'oko' ] + '</p>'
		'<h1 class="lst-kon-tytul">' + t[ 'tytul' ] + '</h1>'
		'<p class="lst-kon-wstep">' + t[ 'wstep' ] + '</p>'
		'<div class="lst-kon-uklad">' + formularz + '<div class="lst-kon-bok">' + pro + szybciej + '</div></div>'
		'</div></div>' )

	return ( CZCIONKI + '\n\n' + znacznik + '\n\n<style>' + STYL + '</style>\n\n<script>\n' + SKRYPT + '\n</script>\n' )


def sprawdz( html, plik ):
	"""To, co Divi psuje po cichu: złamane wiersze, nawiasy i encje znaczników."""
	znacznik = html[ html.index( '<div class="lst-kon">' ) : html.index( '<style>' ) ]
	if '\n' in znacznik.strip():
		raise SystemExit( plik + ': znacznik rozbity na kilka linijek — Divi wstawi <br />' )
	# Nawiasy wolno mieć tylko w shortcodzie formularza, który MA się wykonać.
	bez_formularza = re.sub( r'\[(/?)et_pb_contact_(form|field)[^\]]*\]', '', znacznik )
	for co in ( '[', ']', '&lt;', '&gt;', '&#91;' ):
		if co in bez_formularza:
			raise SystemExit( plik + ': w treści jest „' + co + '” — Divi zrobi z tego shortcode albo znacznik' )
	if 'script' in znacznik.lower():
		raise SystemExit( plik + ': w treści jest słowo „script”' )
	bez_uwag = re.sub( r'/\*.*?\*/', '', html[ html.index( '<style>' ) : html.index( '</style>' ) ], flags=re.S )
	if bez_uwag.count( '{' ) != bez_uwag.count( '}' ):
		raise SystemExit( plik + ': klamry w <style> się nie zgadzają' )
	for z in set( re.findall( r'<(\w+)[\s>]', znacznik ) ):
		if znacznik.count( '<' + z + ' ' ) + znacznik.count( '<' + z + '>' ) != znacznik.count( '</' + z + '>' ):
			raise SystemExit( plik + ': <' + z + '> otwarty i zamknięty różną liczbę razy' )


for nazwa, t in ( ( 'KONTAKT-en.html', EN ), ( 'KONTAKT-pl.html', PL ) ):
	html = zbuduj( t )
	sprawdz( html, nazwa )
	( TU / nazwa ).write_text( html )
	print( nazwa, len( html ) // 1024, 'kB' )
