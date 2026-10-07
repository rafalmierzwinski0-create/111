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

const WROGI = 'div,span,p,h1,a,button{border:2px solid #f0a!important}p,h1{margin:40px!important;background:#ff0!important;'
	+ 'font-family:"Comic Sans MS"!important;text-transform:uppercase!important;letter-spacing:.3em!important}';

const strona = ( t, wrogi = false ) => `<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Kontakt</title>
<style>html,body{margin:0;background:#232a29;color:#eaf3f1;font-family:system-ui}
.et_pb_section{padding:40px 0}.et_pb_row{width:90%;max-width:1800px;margin:0 auto}
p{padding-bottom:1em}p:not(.has-background):last-of-type{padding-bottom:0}${ wrogi ? WROGI : '' }</style>
</head><body><div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><p id="odnosnik">A paragraph.</p></div></div></div>
<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><div class="et_pb_module">
${ t }
</div></div></div></div></body></html>`;

for ( const [ n, t ] of [ [ 'k-zwykla', strona( en ) ], [ 'k-br', strona( divi( en ) ) ], [ 'k-wrogi', strona( en, true ) ], [ 'k-pl', strona( pl ) ] ] ) {
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

console.log( '\n1440 px' );
{
	const { p, c, bledy } = await otworz( 'k-zwykla' );
	const r = await p.evaluate( () => {
		const t = ( s ) => document.querySelector( s );
		const rozm = ( s ) => Math.round( parseFloat( getComputedStyle( t( s ) ).fontSize ) );
		const kafle = [ ...document.querySelectorAll( '.lst-kon-kafel' ) ];
		return {
			h1: document.querySelectorAll( '.lst-kon h1' ).length,
			drog: document.querySelectorAll( '.lst-kon-drogi > .lst-kon-kafel' ).length,
			punktow: document.querySelectorAll( '.lst-kon-punkt' ).length,
			ikon: document.querySelectorAll( '.lst-kon-ikona svg' ).length,
			maile: [ ...document.querySelectorAll( 'a[href^="mailto:"]' ) ].length,
			forum: ( t( 'a[href*="wordpress.org"]' ) || {} ).target,
			rozmiary: [ '.lst-kon-oko', '.lst-kon-wstep', '.lst-kon-kafel-tekst', '.lst-kon-punkt-tytul', '.lst-kon-punkt-tekst', '.lst-kon-przycisk', '.lst-kon-kopiuj' ].map( rozm ).join( '/' ),
			swiatlo: kafle.filter( ( e ) => /conic-gradient/.test( getComputedStyle( e, '::before' ).backgroundImage ) && 'none' !== getComputedStyle( e ).animationName ).length,
			kafli: kafle.length,
			trzyObok: new Set( [ ...document.querySelectorAll( '.lst-kon-drogi > .lst-kon-kafel' ) ].map( ( e ) => Math.round( e.getBoundingClientRect().top ) ) ).size,
			lewa: Math.round( t( '.lst-kon-rama' ).getBoundingClientRect().left ),
			odn: Math.round( t( '#odnosnik' ).getBoundingClientRect().left ),
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
			// Długość wiersza zależy od kroju, którego test nie ładuje; sprawdzane
			// jest to, co zapobiega samotnemu słowu w drugim wierszu.
			wyrownanie: getComputedStyle( t( '.lst-kon-tytul' ) ).textWrap,
		};
	} );
	ok( 'jeden tytuł strony, trzy drogi kontaktu, cztery wskazówki', r.h1 === 1 && r.drog === 3 && r.punktow === 4, `h1 ${ r.h1 }, drogi ${ r.drog }, wskazówki ${ r.punktow }` );
	ok( 'trzy drogi stoją obok siebie', r.trzyObok === 1, `${ r.trzyObok } rząd(y)` );
	ok( 'tytuł bez samotnego słowa w drugim wierszu', /balance/.test( r.wyrownanie ), r.wyrownanie );
	ok( 'każda droga i wskazówka ma ikonkę', r.ikon === 7, `${ r.ikon } z 7` );
	ok( 'dwa przyciski e-mail, forum w nowej karcie', r.maile === 2 && r.forum === '_blank', `maile ${ r.maile }, forum ${ r.forum }` );
	ok( 'rozmiary tylko 14 i 18', r.rozmiary.split( '/' ).every( ( x ) => [ '14', '18' ].includes( x ) ), r.rozmiary );
	ok( 'po krawędzi każdego kafla biegnie światło', r.swiatlo === r.kafli && r.kafli === 4, `${ r.swiatlo } z ${ r.kafli }` );
	ok( 'równo z resztą strony, bez suwaka', Math.abs( r.lewa - r.odn ) <= 1 && r.poziom === 0, `${ r.lewa } vs ${ r.odn }, suwak ${ r.poziom }` );

	// Przycisk „Kopiuj”: adres w schowku, napis zmienia się na chwilę.
	await p.click( '.lst-kon-kopiuj' );
	await p.waitForTimeout( 200 );
	const schowek = await p.evaluate( () => navigator.clipboard.readText() );
	const napis = await p.locator( '.lst-kon-kopiuj' ).textContent();
	ok( 'przycisk kopiuje adres i mówi, że skopiował', 'ADRES-EMAIL' === schowek && 'Copied' === napis, `schowek „${ schowek }”, napis „${ napis }”` );

	await p.hover( '.lst-kon-kafel.jest-droga' );
	await p.waitForTimeout( 400 );
	const ikona = await p.evaluate( () => getComputedStyle( document.querySelector( '.lst-kon-kafel.jest-droga .lst-kon-ikona' ) ).backgroundColor );
	ok( 'ikonka zapala się pod kursorem', /95, 227, 207/.test( ikona ), ikona );
	ok( 'bez błędów w konsoli', bledy.length === 0, bledy.join( ' | ' ) || '0' );
	await c.close();
}

console.log( '\npo wstawieniu <br /> przez Divi' );
{
	const { p, c } = await otworz( 'k-br' );
	const r = await p.evaluate( () => ( {
		drog: document.querySelectorAll( '.lst-kon-drogi > .lst-kon-kafel' ).length,
		br: [ ...document.querySelectorAll( '.lst-kon br' ) ].some( ( x ) => 'none' !== getComputedStyle( x ).display ),
	} ) );
	ok( 'układ przeżywa <br />', r.drog === 3 && ! r.br, `${ r.drog } drogi, widoczne br: ${ r.br }` );
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
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		};
	} );
	ok( 'kafle jeden pod drugim, bez suwaka', r.pelne && r.poziom === 0, `pełna szerokość: ${ r.pelne }, suwak ${ r.poziom }` );
	await c.close();
}

console.log( '\nwersja polska' );
{
	const { p, c } = await otworz( 'k-pl' );
	await p.click( '.lst-kon-kopiuj' );
	await p.waitForTimeout( 200 );
	const r = await p.evaluate( () => ( {
		tytul: document.querySelector( '.lst-kon-tytul' ).textContent,
		napis: document.querySelector( '.lst-kon-kopiuj' ).textContent,
		faq: document.querySelector( '.lst-kon-faq a' ).getAttribute( 'href' ),
	} ) );
	ok( 'polskie teksty i kotwica FAQ', r.tytul.startsWith( 'Napisz do ludzi' ) && r.napis === 'Skopiowano' && r.faq === '/#pytania', `${ r.tytul } / ${ r.napis } / ${ r.faq }` );
	await c.close();
}

console.log( `\n${ pass } PASS, ${ fail } FAIL` );
await b.close();
process.exit( fail ? 1 : 0 );
