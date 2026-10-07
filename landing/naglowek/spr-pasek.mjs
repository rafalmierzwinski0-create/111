/*
 * Pasek menu: przyciski podstron („płynny metal”) i kolumny sekcji.
 * Uruchomienie (z katalogu repozytorium): node landing/naglowek/spr-pasek.mjs [zrzuty-do-katalogu]
 *
 * Strony podaje mały serwer HTTP, bo trzeba sprawdzić zachowanie na
 * podstronie (inna ścieżka niż „/”), a tego z file:// się nie da.
 */
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';
import http from 'http';
import path from 'path';

const TU = path.dirname( new URL( import.meta.url ).pathname );
const ZRZUTY = process.argv[ 2 ] || '';
const pasek = fs.readFileSync( path.join( TU, 'pasek-en.html' ), 'utf8' );

const STRONA = ( tresc ) => `<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Pasek</title>
<style>html,body{margin:0;background:#232a29;color:#eaf3f1;font-family:system-ui}.sekcja{height:900px;padding:40px}</style></head>
<body>${ pasek }${ tresc }</body></html>`;

const GLOWNA = STRONA( [ 'start', 'how', 'different', 'compare', 'pricing', 'faq' ].map( ( id ) => `<div class="sekcja" id="${ id }">${ id }</div>` ).join( '' ) );
const PODSTRONA = STRONA( '<div class="sekcja">How it works subpage</div>' );

const serwer = http.createServer( ( req, res ) => {
	const p = req.url.split( '?' )[ 0 ];
	res.writeHead( 200, { 'Content-Type': 'text/html; charset=utf-8' } );
	res.end( '/' === p ? GLOWNA : PODSTRONA );
} );
await new Promise( ( r ) => serwer.listen( 0, '127.0.0.1', r ) );
const BASE = 'http://127.0.0.1:' + serwer.address().port;

const b = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
let pass = 0, fail = 0;
const ok = ( n, w, d ) => { if ( w ) { pass++; console.log( '  ✓ ', n, ' — ', d ); } else { fail++; console.log( '  ✗ ', n, ' — ', d ); } };

const otworz = async ( adres, w = 1440, h = 900 ) => {
	const c = await b.newContext( { viewport: { width: w, height: h } } );
	const p = await c.newPage();
	const bledy = [];
	p.on( 'pageerror', ( e ) => bledy.push( e.message ) );
	await p.route( /fonts\.googleapis/, ( r ) => r.abort() );
	await p.goto( BASE + adres );
	await p.waitForTimeout( 400 );
	return { p, c, bledy };
};

console.log( '\nstrona główna, 1440 px' );
{
	const { p, c, bledy } = await otworz( '/' );
	const r = await p.evaluate( () => {
		const metal = [ ...document.querySelectorAll( '.lst-metal' ) ];
		const gora = document.querySelector( '.lst-top' ).getBoundingClientRect();
		return {
			adresy: metal.map( ( a ) => a.getAttribute( 'href' ) ).join( ' ' ),
			napisy: metal.map( ( a ) => a.textContent.trim() ).join( ' / ' ),
			wGorze: metal.every( ( a ) => { const r = a.getBoundingClientRect(); return r.top >= gora.top && r.bottom <= gora.bottom; } ),
			obwodka: metal.every( ( a ) => /conic-gradient/.test( getComputedStyle( a, '::before' ).backgroundImage ) && 'none' !== getComputedStyle( a, '::before' ).animationName ),
			kolumnaB: document.querySelector( '.lst-col[href="#how"]' ).textContent.trim(),
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
			tutaj: document.querySelectorAll( '.lst-metal.is-here' ).length,
		};
	} );
	ok( 'dwa przyciski podstron w górnym rzędzie', r.adresy === '/how-it-works/ /contact/' && r.wGorze, `${ r.adresy } (${ r.napisy })` );
	ok( 'metalowa obwódka się obraca', r.obwodka, String( r.obwodka ) );
	ok( 'kolumna B nie dubluje nazwy podstrony', 'B Three steps' === r.kolumnaB, r.kolumnaB );
	ok( 'na stronie głównej żaden przycisk podstrony nie jest „tutaj”', r.tutaj === 0, String( r.tutaj ) );
	ok( 'bez suwaka poziomego', r.poziom === 0, String( r.poziom ) );

	// kolumna sekcji na stronie głównej dalej przewija na miejscu
	await p.click( '.lst-col[href="#compare"]' );
	await p.waitForTimeout( 1200 );
	const poKliku = await p.evaluate( () => ( { url: location.pathname, y: Math.round( scrollY ) } ) );
	ok( 'kolumna na stronie głównej przewija do sekcji', '/' === poKliku.url && poKliku.y > 1500, `${ poKliku.url }, przewinięte ${ poKliku.y } px` );

	if ( ZRZUTY ) {
		await p.evaluate( () => window.scrollTo( 0, 0 ) );
		await p.locator( '.lst-bar' ).screenshot( { path: path.join( ZRZUTY, 'pasek-1440.png' ) } );
		await p.hover( '.lst-metal[href="/contact/"]' );
		await p.waitForTimeout( 400 );
		await p.locator( '.lst-top' ).screenshot( { path: path.join( ZRZUTY, 'pasek-hover.png' ) } );
	}
	ok( 'bez błędów w konsoli', bledy.length === 0, bledy.join( ' | ' ) || '0' );
	await c.close();
}

console.log( '\npodstrona „How it works”' );
{
	const { p, c, bledy } = await otworz( '/how-it-works/' );
	const tutaj = await p.evaluate( () => [ ...document.querySelectorAll( '.lst-metal.is-here' ) ].map( ( a ) => a.getAttribute( 'href' ) + ' ' + a.getAttribute( 'aria-current' ) ) );
	ok( 'przycisk bieżącej podstrony świeci i ma aria-current', tutaj.length === 1 && tutaj[ 0 ] === '/how-it-works/ page', tutaj.join( ', ' ) );
	await Promise.all( [ p.waitForURL( /\/#pricing$/ ), p.click( '.lst-col[href="#pricing"]' ) ] );
	ok( 'kolumna sekcji z podstrony prowadzi na stronę główną do sekcji', p.url().endsWith( '/#pricing' ), p.url() );
	ok( 'bez błędów w konsoli', bledy.length === 0, bledy.join( ' | ' ) || '0' );
	await c.close();
}

console.log( '\n390 px, telefon' );
{
	const { p, c } = await otworz( '/', 390, 840 );
	const r = await p.evaluate( () => {
		const gora = document.querySelector( '.lst-top' );
		return {
			mieszczaSie: gora.scrollWidth <= gora.clientWidth + 1,
			napisyUkryte: [ ...document.querySelectorAll( '.lst-metal-napis' ) ].every( ( s ) => 'none' === getComputedStyle( s ).display ),
			okragle: [ ...document.querySelectorAll( '.lst-metal' ) ].every( ( a ) => Math.abs( a.offsetWidth - a.offsetHeight ) <= 1 ),
			opisy: [ ...document.querySelectorAll( '.lst-metal' ) ].map( ( a ) => a.getAttribute( 'aria-label' ) ).join( ', ' ),
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		};
	} );
	ok( 'na telefonie same okrągłe ikonki, wszystko się mieści', r.mieszczaSie && r.napisyUkryte && r.okragle && r.poziom === 0,
		`mieści się ${ r.mieszczaSie }, napisy ukryte ${ r.napisyUkryte }, okrągłe ${ r.okragle }, suwak ${ r.poziom }` );
	ok( 'ikonki mają opis dla czytników ekranu', 'How it works, Contact' === r.opisy, r.opisy );
	if ( ZRZUTY ) { await p.locator( '.lst-bar' ).screenshot( { path: path.join( ZRZUTY, 'pasek-390.png' ) } ); }
	await c.close();
}

console.log( `\n${ pass } PASS, ${ fail } FAIL` );
await b.close();
serwer.close();
process.exit( fail ? 1 : 0 );
