/**
 * Pas „którędy idą dane" — sprawdzenie w przeglądarce.
 *
 * Użycie: node landing/droga/spr.mjs
 */

import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import { pathToFileURL } from 'node:url';
import fs from 'node:fs';

const TU = '/home/user/111/landing/droga';
let pass = 0, fail = 0;

const ok = ( co, warunek, szczegol = '' ) => {
	console.log( `  ${ warunek ? '\u001b[32m✓\u001b[0m' : '\u001b[31m✗\u001b[0m' }  ${ co }` + ( szczegol ? `  —  ${ szczegol }` : '' ) );
	warunek ? pass += 1 : fail += 1;
};

const jasnosc = ( c ) => {
	const [ r, g, b ] = c.match( /\d+/g ).map( Number ).slice( 0, 3 );
	const f = ( v ) => { const s = v / 255; return s <= 0.03928 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 ); };
	return 0.2126 * f( r ) + 0.7152 * f( g ) + 0.0722 * f( b );
};

const kontrast = ( a, b ) => {
	const x = jasnosc( a ), y = jasnosc( b );
	return Math.round( ( ( Math.max( x, y ) + 0.05 ) / ( Math.min( x, y ) + 0.05 ) ) * 100 ) / 100;
};

const b = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
const bledy = [];

const otworz = async ( szer ) => {
	const c = await b.newContext( { viewport: { width: szer, height: 900 } } );
	const p = await c.newPage();
	p.on( 'pageerror', ( e ) => bledy.push( e.message ) );
	await p.goto( pathToFileURL( TU + '/proba.html' ).href, { waitUntil: 'load' } );
	await p.waitForTimeout( 250 );
	return { p, c };
};

console.log( '\npas na stronie' );
{
	const { p, c } = await otworz( 1500 );

	const r = await p.evaluate( () => {
		const kroki = [ ...document.querySelectorAll( '.lst-dr-krok' ) ];
		const styl = ( el, pseudo ) => getComputedStyle( el, pseudo );

		return {
			krokow: kroki.length,
			ikon: document.querySelectorAll( '.lst-dr-ikona' ).length,
			// Kreska i grot są warstwami, nie znacznikami: sprawdzane po tym,
			// czym są naprawdę.
			kresek: kroki.filter( ( e ) => /gradient/.test( styl( e, '::before' ).backgroundImage ) ).length,
			grotow: kroki.filter( ( e ) => parseFloat( styl( e, '::after' ).borderLeftWidth ) > 0 ).length,
			plynie: kroki.filter( ( e ) => 'none' !== styl( e, '::before' ).animationName ).length,
			rozmiary: [ ...new Set( [ ...document.querySelectorAll( '.lst-dr [class*="lst-dr-"]' ) ]
				.filter( ( e ) => [ ...e.childNodes ].some( ( n ) => 3 === n.nodeType && n.nodeValue.trim() ) )
				.map( ( e ) => Math.round( parseFloat( getComputedStyle( e ).fontSize ) ) ) ) ].sort( ( a, x ) => a - x ),
			myslniki: ( document.querySelector( '.lst-dr' ).innerText.match( /[–—]/g ) || [] ).length,
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
			tytul: getComputedStyle( document.querySelector( '.lst-dr-tytul' ) ).color,
			opis: getComputedStyle( document.querySelector( '.lst-dr-opis' ) ).color,
			strona: getComputedStyle( document.body ).backgroundColor,
			mono: getComputedStyle( document.querySelector( '.lst-dr-opis' ) ).fontFamily,
			wRzedzie: new Set( kroki.map( ( e ) => Math.round( e.getBoundingClientRect().top ) ) ).size,
		};
	} );

	ok( 'cztery przystanki, cztery ikony', 4 === r.krokow && 4 === r.ikon, `${ r.krokow } / ${ r.ikon }` );
	ok( 'stoją w jednym rzędzie', 1 === r.wRzedzie, `rzędów ${ r.wRzedzie }` );
	ok( 'trzy kreski z grotem, po jednej między przystankami', 3 === r.kresek && 3 === r.grotow,
		`kresek ${ r.kresek }, grotów ${ r.grotow }` );
	ok( 'i po każdej biegnie rozjaśnienie', 3 === r.plynie, `${ r.plynie } z 3` );
	ok( 'rozmiary pisma tylko 14, 18 i 20', r.rozmiary.every( ( x ) => [ 14, 18, 20 ].includes( x ) ), r.rozmiary.join( '/' ) );
	ok( 'podpisy pisane monospace, jak wszystko, co udaje arkusz', /Plex Mono/.test( r.mono ), r.mono.slice( 0, 30 ) );
	ok( 'ani jednego myślnika', 0 === r.myslniki, String( r.myslniki ) );
	ok( 'tytuł czytelny (>= 4,5 : 1)', kontrast( r.tytul, r.strona ) >= 4.5, `${ kontrast( r.tytul, r.strona ) } : 1` );
	ok( 'podpis czytelny (>= 4,5 : 1)', kontrast( r.opis, r.strona ) >= 4.5, `${ kontrast( r.opis, r.strona ) } : 1` );
	ok( 'bez suwaka poziomego', 0 === r.poziom, String( r.poziom ) );

	await c.close();
}

console.log( '\nna telefonie' );
{
	const { p, c } = await otworz( 390 );

	const r = await p.evaluate( () => ( {
		wRzedzie: new Set( [ ...document.querySelectorAll( '.lst-dr-krok' ) ].map( ( e ) => Math.round( e.getBoundingClientRect().top ) ) ).size,
		poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
	} ) );

	ok( 'przystanki stają jeden pod drugim', 4 === r.wRzedzie, `rzędów ${ r.wRzedzie }` );
	ok( 'bez suwaka poziomego', 0 === r.poziom, String( r.poziom ) );

	await c.close();
}

console.log( '\nobie wersje językowe' );
{
	const en = fs.readFileSync( TU + '/DROGA-en.html', 'utf8' );
	const pl = fs.readFileSync( TU + '/DROGA-pl.html', 'utf8' );
	const kroki = ( s ) => ( s.match( /lst-dr-krok/g ) || [] ).length;

	ok( 'ten sam układ po angielsku i po polsku', kroki( en ) === kroki( pl ) && kroki( en ) > 0,
		`en ${ kroki( en ) }, pl ${ kroki( pl ) }` );
	ok( 'polska wersja jest po polsku', /Arkusz Google/.test( pl ) && ! /Your Google Sheet/.test( pl ), 'Arkusz Google' );
	ok( 'angielska nie ma polskich ogonków', ! /[ąćęłńóśźż]/i.test( en.slice( 0, en.indexOf( '<style>' ) ) ), 'bez ogonków' );
}

ok( 'bez błędów skryptu', 0 === bledy.length, bledy.join( ' | ' ) || '0' );

await b.close();
console.log( `\n${ pass } PASS, ${ fail } FAIL\n` );
process.exit( fail ? 1 : 0 );
