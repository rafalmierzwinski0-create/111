/**
 * Sprawdzenie sekcji „How it works”.
 *
 * Podświetlenia są całe na „:has()” i na „:hover”, więc nie da się ich
 * sprawdzić czytaniem arkusza stylów: trzeba najechać kursorem i zmierzyć, co
 * się naprawdę zapaliło. Tym bardziej, że „:has()” jest dokładnie tym rodzajem
 * selektora, który da się napisać tak, że pasuje do wszystkiego naraz.
 *
 * Użycie: node landing/arkusz/spr.mjs   (z katalogu landing/arkusz)
 */

import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import { pathToFileURL } from 'node:url';

let pass = 0, fail = 0;

const ok = ( co, warunek, szczegol = '' ) => {
	if ( warunek ) {
		pass += 1;
		console.log( '  \u001b[32m✓\u001b[0m ', co, szczegol ? '  —  ' + szczegol : '' );
		return;
	}
	fail += 1;
	console.log( '  \u001b[31m✗\u001b[0m ', co, szczegol ? '  —  ' + szczegol : '' );
};

const b = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
const p = await b.newPage( { viewport: { width: 1280, height: 900 } } );
const bledy = [];
p.on( 'pageerror', ( e ) => bledy.push( e.message ) );
await p.goto( pathToFileURL( '/home/user/111/landing/arkusz/PODGLAD.html' ).href, { waitUntil: 'load' } );
await p.waitForTimeout( 300 );

const stan = () => p.evaluate( () => {
	const litery = [ ...document.querySelectorAll( '.lst-ar-litery > span' ) ];
	const etapy = [ ...document.querySelectorAll( '.lst-ar-etap .lst-ar-etap-nazwa' ) ];
	const mieta = ( c ) => /95,\s*227,\s*207/.test( c );

	return {
		// Litery: pierwsza to pole nazwy, dalej A, B, C.
		zapalone: litery.slice( 1 ).map( ( e ) => mieta( getComputedStyle( e ).color ) ),
		numer: mieta( getComputedStyle( document.querySelector( '.lst-ar-nr' ) ).color ),
		adres: getComputedStyle( document.querySelector( '.lst-ar-rog' ), '::after' ).content.replace( /"/g, '' ),
		etapy: etapy.map( ( e ) => mieta( getComputedStyle( e ).color ) ),
		obrys: [ ...document.querySelectorAll( '.lst-ar-komorka' ) ]
			.map( ( e ) => getComputedStyle( e, '::before' ).boxShadow )
			.map( ( x ) => 'none' !== x && /95,\s*227,\s*207/.test( x ) ),
		uchwyt: [ ...document.querySelectorAll( '.lst-ar-komorka' ) ]
			.map( ( e ) => 'none' !== getComputedStyle( e, '::after' ).content ),
	};
} );

console.log( '\nw spoczynku' );
{
	const s = await stan();
	ok( 'żadna litera nie jest zapalona', s.zapalone.every( ( x ) => ! x ), s.zapalone.join( '/' ) );
	ok( 'pole nazwy pokazuje A1', 'A1' === s.adres, s.adres );
	ok( 'żadna komórka nie jest zaznaczona', s.obrys.every( ( x ) => ! x ) && s.uchwyt.every( ( x ) => ! x ) );
	ok( 'żaden etap nie jest zapalony', s.etapy.every( ( x ) => ! x ) );
}

console.log( '\nnajechanie na komórkę' );
for ( const [ i, adres ] of [ 'A1', 'B1', 'C1' ].entries() ) {
	await p.hover( `.lst-ar-komorka:nth-child( ${ i + 2 } )` );
	await p.waitForTimeout( 160 );
	const s = await stan();

	ok( `${ adres }: zapala się litera swojej kolumny i żadna inna`,
		s.zapalone.every( ( x, j ) => x === ( j === i ) ), s.zapalone.map( ( x ) => x ? '■' : '·' ).join( '' ) );
	ok( `${ adres }: zapala się numer wiersza`, s.numer );
	ok( `${ adres }: pole nazwy pokazuje adres tej komórki`, adres === s.adres, s.adres );
	ok( `${ adres }: tylko ta komórka ma obrys i uchwyt`,
		s.obrys.every( ( x, j ) => x === ( j === i ) ) && s.uchwyt.every( ( x, j ) => x === ( j === i ) ) );
	ok( `${ adres }: zapala się ten etap, który z tego kroku wynika`,
		s.etapy.every( ( x, j ) => x === ( j === i ) ), s.etapy.map( ( x ) => x ? '■' : '·' ).join( '' ) );
}

console.log( '\nnajechanie na etap w wierszu formuły' );
for ( const i of [ 0, 1, 2 ] ) {
	await p.hover( `.lst-ar-etap:nth-child( ${ i + 1 } )` );
	await p.waitForTimeout( 160 );
	const s = await stan();
	ok( `etap ${ i + 1 }: zapala się litera kroku, z którego wynika`,
		s.zapalone.every( ( x, j ) => x === ( j === i ) ), s.zapalone.map( ( x ) => x ? '■' : '·' ).join( '' ) );
}

console.log( '\nreszta' );
{
	await p.mouse.move( 5, 5 );
	await p.waitForTimeout( 160 );
	const s = await stan();
	ok( 'po zjechaniu kursorem wszystko gaśnie', s.zapalone.every( ( x ) => ! x ) && 'A1' === s.adres );

	const r = await p.evaluate( () => ( {
		poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		rozmiary: [ ...new Set( [ ...document.querySelectorAll( '.lst-ar [class*="lst-ar-"]' ) ]
			.filter( ( e ) => [ ...e.childNodes ].some( ( n ) => 3 === n.nodeType && n.nodeValue.trim() ) )
			.map( ( e ) => Math.round( parseFloat( getComputedStyle( e ).fontSize ) ) ) ) ].sort( ( a, x ) => a - x ),
		myslniki: ( document.querySelector( '.lst-ar' ).innerText.match( /[–—]/g ) || [] ).length,
		schowane: [ ...document.querySelectorAll( '.lst-ar [class*="lst-ar-"]' ) ]
			.filter( ( e ) => '0' === getComputedStyle( e ).opacity ).length,
	} ) );

	ok( 'bez suwaka poziomego', 0 === r.poziom, String( r.poziom ) );
	ok( 'rozmiary pisma tylko 14, 18 i 20', r.rozmiary.every( ( x ) => [ 14, 18, 20 ].includes( x ) ), r.rozmiary.join( '/' ) );
	ok( 'ani jednego myślnika', 0 === r.myslniki, String( r.myslniki ) );
	ok( 'nic nie jest niewidoczne w spoczynku', 0 === r.schowane, String( r.schowane ) );
	ok( 'bez błędów skryptu', 0 === bledy.length, bledy.join( ' | ' ) || '0' );
}

console.log( '\nwąsko' );
{
	const m = await b.newPage( { viewport: { width: 390, height: 900 } } );
	await m.goto( pathToFileURL( '/home/user/111/landing/arkusz/PODGLAD.html' ).href, { waitUntil: 'load' } );
	await m.waitForTimeout( 200 );
	const r = await m.evaluate( () => ( {
		litery: getComputedStyle( document.querySelector( '.lst-ar-litery' ) ).display,
		kolumny: getComputedStyle( document.querySelector( '.lst-ar-wiersz' ) ).gridTemplateColumns.split( ' ' ).length,
		poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
	} ) );
	ok( 'pasek liter znika, a komórki idą jedna pod drugą', 'none' === r.litery && 1 === r.kolumny,
		`${ r.litery }, ${ r.kolumny } kolumna` );
	ok( 'bez suwaka poziomego na telefonie', 0 === r.poziom, String( r.poziom ) );
}

await b.close();
console.log( `\n${ pass } PASS, ${ fail } FAIL\n` );
process.exit( fail ? 1 : 0 );
