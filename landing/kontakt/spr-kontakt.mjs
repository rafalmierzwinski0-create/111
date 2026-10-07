/*
 * Podstrona „Kontakt”. Uruchomienie: node landing/kontakt/spr-kontakt.mjs
 */
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';
import path from 'path';

const TU = path.dirname( new URL( import.meta.url ).pathname );
const en = fs.readFileSync( path.join( TU, 'KONTAKT-en.html' ), 'utf8' );
const pl = fs.readFileSync( path.join( TU, 'KONTAKT-pl.html' ), 'utf8' );

// Divi wstawia <br /> w każdym złamanym wierszu poza <style> i <script>.
const divi = ( s ) => s.split( /(<style>[\s\S]*?<\/style>|<script>[\s\S]*?<\/script>)/ )
	.map( ( c, i ) => ( i % 2 ? c : c.split( '\n' ).join( '<br />\n' ) ) ).join( '' );

/*
 * Tak Divi 4 wypisuje formularz kontaktowy z tego shortcode'u, razem z tymi
 * regułami jego arkusza, które biją się z naszym wyglądem (szare pola bez
 * zaokrągleń, połówki na float, przycisk z ramką i strzałką).
 */
const DIVI_CSS = '.clearfix:after{content:".";display:block;height:0;clear:both;visibility:hidden}'
	+ '.et_pb_contact p input,.et_pb_contact p textarea{background-color:#eee;border:none!important;border-radius:0!important;color:#999;padding:16px;width:100%;font-size:14px}'
	+ '.et_pb_contact_form{margin-left:-3%}.et_pb_contact_field{padding-left:3%;margin-bottom:3%;position:relative;float:left;width:100%}'
	+ '.et_pb_contact_field_half{width:50%}.et_pb_contact_form_label{display:none}'
	+ '.et_contact_bottom_container{float:right;text-align:right;margin-top:-1.5%}.et_pb_contact_right{display:inline-block;float:right}'
	+ '.et_pb_button{font-size:20px;border:2px solid #2ea3f2;border-radius:3px;padding:.3em 1em;background:transparent;color:#2ea3f2}'
	+ '.et_pb_button:after{content:"→";margin-left:.3em}';
const diviFormularz = ( t ) => t.replace( /\[et_pb_contact_form[^\]]*\][\s\S]*?\[\/et_pb_contact_form\]/, ( sc ) => {
	const pola = [ ...sc.matchAll( /field_id="(\w+)" field_title="([^"]+)" field_type="(\w+)" fullwidth_field="(on|off)"/g ) ];
	const przycisk = sc.match( /submit_button_text="([^"]+)"/ )[ 1 ];
	return '<div id="et_pb_contact_form_0" class="et_pb_module et_pb_contact_form_0 et_pb_contact_form_container clearfix"><div class="et-pb-contact-message"></div>'
		+ '<div class="et_pb_contact"><form class="et_pb_contact_form clearfix" method="post" action="">'
		+ pola.map( ( [ , id, tytul, typ, pelne ], i ) => `<p class="et_pb_contact_field et_pb_contact_field_${ i }${ 'off' === pelne ? ' et_pb_contact_field_half' : '' }" data-id="${ id.toLowerCase() }" data-type="${ typ }">`
			+ `<label for="et_pb_contact_${ id.toLowerCase() }_0" class="et_pb_contact_form_label">${ tytul }</label>`
			+ ( 'text' === typ ? `<textarea name="et_pb_contact_${ id.toLowerCase() }_0" id="et_pb_contact_${ id.toLowerCase() }_0" class="et_pb_contact_message input" placeholder="${ tytul }"></textarea>`
				: `<input type="text" id="et_pb_contact_${ id.toLowerCase() }_0" class="input" value="" name="et_pb_contact_${ id.toLowerCase() }_0" placeholder="${ tytul }">` ) + '</p>' ).join( '' )
		+ '<input type="hidden" value="et_contact_proccess" name="et_pb_contactform_submit_0"/>'
		+ '<div class="et_contact_bottom_container"><div class="et_pb_contact_right"><p class="clearfix"><span class="et_pb_contact_captcha_question">3 + 7</span> = <input type="text" size="2" class="input et_pb_contact_captcha" value="" name="et_pb_contact_captcha_0"></p></div>'
		+ `<button type="submit" name="et_builder_submit_button" class="et_pb_contact_submit et_pb_button">${ przycisk }</button></div>`
		+ '<input type="hidden" name="_wpnonce-et-pb-contact-form-submitted-0" value="x"></form></div></div>';
} );

const WROGI = 'div,span,p,h1,a,button{border:2px solid #f0a!important}p,h1{margin:40px!important;background:#ff0!important;'
	+ 'font-family:"Comic Sans MS"!important;text-transform:uppercase!important;letter-spacing:.3em!important}';

const strona = ( t, wrogi = false, zDivi = false ) => `<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Kontakt</title>
<style>html,body{margin:0;background:#232a29;color:#eaf3f1;font-family:system-ui}
.et_pb_section{padding:40px 0}.et_pb_row{width:90%;max-width:1800px;margin:0 auto}
p{padding-bottom:1em}p:not(.has-background):last-of-type{padding-bottom:0}${ zDivi ? DIVI_CSS : '' }${ wrogi ? WROGI : '' }</style>
</head><body><div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><p id="odnosnik">A paragraph.</p></div></div></div>
<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><div class="et_pb_module">
${ t }
</div></div></div></div></body></html>`;

for ( const [ n, t ] of [ [ 'k-zwykla', strona( diviFormularz( en ), false, true ) ], [ 'k-zapas', strona( en ) ], [ 'k-br', strona( divi( diviFormularz( en ) ), false, true ) ], [ 'k-wrogi', strona( diviFormularz( en ), true, true ) ], [ 'k-pl', strona( pl ) ] ] ) {
	fs.writeFileSync( `/tmp/${ n }.html`, t );
}

const b = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
let pass = 0, fail = 0;
const ok = ( n, w, d ) => { if ( w ) { pass++; console.log( '  ✓ ', n, ' — ', d ); } else { fail++; console.log( '  ✗ ', n, ' — ', d ); } };

const otworz = async ( n, w = 1440, h = 900 ) => {
	const c = await b.newContext( { viewport: { width: w, height: h } } );
	await c.grantPermissions( [ 'clipboard-read', 'clipboard-write' ] );
	const p = await c.newPage();
	const bledy = [];
	p.on( 'pageerror', ( e ) => bledy.push( e.message ) );
	await p.route( /fonts\.googleapis/, ( r ) => r.abort() );
	await p.goto( `file:///tmp/${ n }.html` );
	await p.waitForTimeout( 400 );
	return { p, c, bledy };
};

console.log( '\n1440 px, formularz Divi' );
{
	const { p, c, bledy } = await otworz( 'k-zwykla' );
	const r = await p.evaluate( () => {
		const t = ( s ) => document.querySelector( s );
		const rozm = ( s ) => Math.round( parseFloat( getComputedStyle( t( s ) ).fontSize ) );
		const kafle = [ ...document.querySelectorAll( '.lst-kon-kafel' ) ];
		const pole = ( s ) => { const e = t( s ); const st = getComputedStyle( e ); const r = e.getBoundingClientRect(); return { tlo: st.backgroundColor, rog: parseFloat( st.borderTopLeftRadius ), top: Math.round( r.top ), lewa: Math.round( r.left ), szer: Math.round( r.width ) }; };
		const guzik = t( '.et_pb_contact_submit' );
		return {
			h1: document.querySelectorAll( '.lst-kon h1' ).length,
			kafli: kafle.length,
			punktow: document.querySelectorAll( '.lst-kon-punkt' ).length,
			ikon: document.querySelectorAll( '.lst-kon-ikona svg' ).length,
			forum: document.querySelectorAll( 'a[href*="wordpress.org"]' ).length,
			imie: pole( '#et_pb_contact_name_0' ), mail: pole( '#et_pb_contact_email_0' ), tresc: pole( '#et_pb_contact_message_0' ),
			formSzer: Math.round( t( '.et_pb_contact_form' ).getBoundingClientRect().width ),
			formLewa: Math.round( t( '.et_pb_contact_form' ).getBoundingClientRect().left ),
			guzikTlo: getComputedStyle( guzik ).backgroundColor,
			guzikStrzalka: getComputedStyle( guzik, '::after' ).display,
			formularzObokBoku: Math.round( t( '.lst-kon-bok' ).getBoundingClientRect().top ) === Math.round( t( '#formularz' ).getBoundingClientRect().top ),
			rozmiary: [ '.lst-kon-oko', '.lst-kon-wstep', '.lst-kon-kafel-tekst', '.lst-kon-punkt-tytul', '.lst-kon-punkt-tekst', '.lst-kon-przycisk', '#et_pb_contact_message_0' ].map( rozm ).join( '/' ),
			swiatlo: kafle.filter( ( e ) => /conic-gradient/.test( getComputedStyle( e, '::before' ).backgroundImage ) && 'none' !== getComputedStyle( e ).animationName ).length,
			lewa: Math.round( t( '.lst-kon-rama' ).getBoundingClientRect().left ),
			odn: Math.round( t( '#odnosnik' ).getBoundingClientRect().left ),
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
			wyrownanie: getComputedStyle( t( '.lst-kon-tytul' ) ).textWrap,
			zapas: document.querySelectorAll( '.lst-kon-zapas' ).length,
			surowy: /et_pb_contact_form\]/.test( t( '.lst-kon-formularz' ).textContent ),
		};
	} );
	ok( 'tytuł, formularz, kafel Pro i wskazówki — bez kafla „wersja darmowa”', r.h1 === 1 && r.kafli === 3 && r.punktow === 4 && r.forum === 0,
		`h1 ${ r.h1 }, kafli ${ r.kafli }, wskazówek ${ r.punktow }, linków do forum ${ r.forum }` );
	ok( 'formularz Divi stoi na miejscu, zapasowy się nie włącza', r.zapas === 0 && ! r.surowy, `zapasowych ${ r.zapas }` );
	ok( 'formularz po lewej, kafel Pro i wskazówki obok', r.formularzObokBoku, String( r.formularzObokBoku ) );
	ok( 'imię i e-mail obok siebie, wiadomość pod nimi na całą szerokość',
		r.imie.top === r.mail.top && r.mail.lewa > r.imie.lewa && r.tresc.top > r.imie.top && Math.abs( r.tresc.szer - r.formSzer ) <= 1 && r.tresc.lewa === r.formLewa,
		`imię ${ r.imie.top }/${ r.imie.lewa }, e-mail ${ r.mail.top }/${ r.mail.lewa }, wiadomość ${ r.tresc.szer } z ${ r.formSzer }` );
	ok( 'pola w wyglądzie strony, nie szare Divi', 'rgb(13, 20, 19)' === r.tresc.tlo && r.tresc.rog >= 10 && 'rgb(13, 20, 19)' === r.imie.tlo,
		`tło ${ r.tresc.tlo }, zaokrąglenie ${ r.tresc.rog }` );
	ok( 'przycisk miętowy, bez strzałki Divi', /95, 227, 207/.test( r.guzikTlo ) && 'none' === r.guzikStrzalka, `${ r.guzikTlo }, strzałka ${ r.guzikStrzalka }` );
	ok( 'tytuł bez samotnego słowa w drugim wierszu', /balance/.test( r.wyrownanie ), r.wyrownanie );
	ok( 'każdy kafel i wskazówka ma ikonkę', r.ikon === 6, `${ r.ikon } z 6` );
	ok( 'rozmiary tylko 14 i 18', r.rozmiary.split( '/' ).every( ( x ) => [ '14', '18' ].includes( x ) ), r.rozmiary );
	ok( 'po krawędzi każdego kafla biegnie światło', r.swiatlo === r.kafli, `${ r.swiatlo } z ${ r.kafli }` );
	ok( 'równo z resztą strony, bez suwaka', Math.abs( r.lewa - r.odn ) <= 1 && r.poziom === 0, `${ r.lewa } vs ${ r.odn }, suwak ${ r.poziom }` );

	// W polach da się pisać.
	await p.fill( '#et_pb_contact_name_0', 'Ola' );
	await p.fill( '#et_pb_contact_message_0', 'Hello there' );
	const wpisane = await p.evaluate( () => document.querySelector( '#et_pb_contact_name_0' ).value + ' / ' + document.querySelector( '#et_pb_contact_message_0' ).value );
	ok( 'w polach da się pisać', 'Ola / Hello there' === wpisane, wpisane );

	await p.hover( '.lst-kon-kafel.jest-droga:not(.jest-glowna)' );
	await p.waitForTimeout( 400 );
	const ikona = await p.evaluate( () => getComputedStyle( document.querySelector( '.lst-kon-kafel.jest-droga:not(.jest-glowna) .lst-kon-ikona' ) ).backgroundColor );
	ok( 'ikonka zapala się pod kursorem', /95, 227, 207/.test( ikona ), ikona );
	ok( 'bez błędów w konsoli', bledy.length === 0, bledy.join( ' | ' ) || '0' );
	await c.close();
}

console.log( '\nbez Divi: formularz zapasowy' );
{
	const { p, c, bledy } = await otworz( 'k-zapas' );
	const r = await p.evaluate( () => {
		const f = document.querySelector( '.lst-kon-zapas' );
		return {
			jest: !! f,
			pol: f ? f.querySelectorAll( 'input, textarea' ).length : 0,
			wymagane: f ? f.querySelectorAll( '[required]' ).length : 0,
			surowy: /et_pb_contact/.test( document.querySelector( '.lst-kon-formularz' ).textContent ),
			tlo: f ? getComputedStyle( f.querySelector( 'textarea' ) ).backgroundColor : '',
		};
	} );
	ok( 'zamiast gołego shortcode’u formularz zapasowy w tym samym wyglądzie', r.jest && r.pol === 3 && r.wymagane === 3 && ! r.surowy && 'rgb(13, 20, 19)' === r.tlo,
		`pól ${ r.pol }, wymaganych ${ r.wymagane }, goły tekst ${ r.surowy }, tło ${ r.tlo }` );
	await p.fill( '.lst-kon-zapas textarea', 'Test' );
	ok( 'i da się w nim pisać', 'Test' === await p.inputValue( '.lst-kon-zapas textarea' ), 'Test' );
	ok( 'bez błędów w konsoli', bledy.length === 0, bledy.join( ' | ' ) || '0' );
	await c.close();
}

console.log( '\npo wstawieniu <br /> przez Divi' );
{
	const { p, c } = await otworz( 'k-br' );
	const r = await p.evaluate( () => ( {
		kafli: document.querySelectorAll( '.lst-kon-kafel' ).length,
		br: [ ...document.querySelectorAll( '.lst-kon br' ) ].some( ( x ) => 'none' !== getComputedStyle( x ).display ),
	} ) );
	ok( 'układ przeżywa <br />', r.kafli === 3 && ! r.br, `${ r.kafli } kafle, widoczne br: ${ r.br }` );
	await c.close();
}

console.log( '\nwrogi motyw' );
{
	const { p, c } = await otworz( 'k-wrogi' );
	const r = await p.evaluate( () => {
		const st = ( s ) => getComputedStyle( document.querySelector( s ) );
		return {
			tytul: st( '.lst-kon-tytul' ).fontFamily.split( ',' )[ 0 ].replace( /"/g, '' ),
			tekst: st( '.lst-kon-kafel-tekst' ).fontFamily.split( ',' )[ 0 ].replace( /"/g, '' ),
			wersaliki: st( '.lst-kon-kafel-tekst' ).textTransform,
			tlo: st( '.lst-kon-kafel-tekst' ).backgroundColor,
			margines: st( '.lst-kon-kafel-tekst' ).marginLeft,
		};
	} );
	ok( 'motyw nie przejmuje kroju ani wersalików', r.tytul === 'Inria Serif' && r.tekst === 'IBM Plex Sans' && r.wersaliki === 'none', `${ r.tytul } / ${ r.tekst } / ${ r.wersaliki }` );
	ok( 'motyw nie maluje tła ani marginesów', r.tlo === 'rgba(0, 0, 0, 0)' && r.margines === '0px', `${ r.tlo }, ${ r.margines }` );
	await c.close();
}

console.log( '\n390 px, telefon' );
{
	const { p, c } = await otworz( 'k-zwykla', 390, 840 );
	const r = await p.evaluate( () => {
		const rama = document.querySelector( '.lst-kon-rama' ).getBoundingClientRect().width;
		return {
			pelne: [ ...document.querySelectorAll( '.lst-kon-kafel' ) ].every( ( e ) => Math.abs( e.getBoundingClientRect().width - rama ) <= 1 ),
			// między polem e-mail a wiadomością zwykły odstęp, nie dziura
			przerwa: Math.round( document.querySelector( '#et_pb_contact_message_0' ).getBoundingClientRect().top - document.querySelector( '#et_pb_contact_email_0' ).getBoundingClientRect().bottom ),
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		};
	} );
	ok( 'kafle jeden pod drugim, bez suwaka', r.pelne && r.poziom === 0, `pełna szerokość: ${ r.pelne }, suwak ${ r.poziom }` );
	ok( 'pola formularza jedno pod drugim, bez dziury', r.przerwa > 0 && r.przerwa < 24, `odstęp ${ r.przerwa } px` );
	await c.close();
}

console.log( '\nwersja polska' );
{
	const { p, c } = await otworz( 'k-pl' );
	const r = await p.evaluate( () => ( {
		tytul: document.querySelector( '.lst-kon-tytul' ).textContent,
		pola: [ ...document.querySelectorAll( '.lst-kon-zapas [placeholder]' ) ].map( ( e ) => e.placeholder ).join( ', ' ),
		guzik: ( document.querySelector( '.lst-kon-wyslij' ) || {} ).textContent,
		faq: document.querySelector( '.lst-kon-faq a' ).getAttribute( 'href' ),
	} ) );
	ok( 'polskie teksty, pola i kotwica FAQ', r.tytul.startsWith( 'Napisz do ludzi' ) && r.pola === 'Imię, E-mail, Wiadomość' && r.guzik === 'Wyślij wiadomość' && r.faq === '/#pytania',
		`${ r.tytul } / ${ r.pola } / ${ r.guzik } / ${ r.faq }` );
	await c.close();
}

console.log( `\n${ pass } PASS, ${ fail } FAIL` );
await b.close();
process.exit( fail ? 1 : 0 );
