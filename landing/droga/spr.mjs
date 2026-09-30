/**
 * „Cztery kroki" — sprawdzenie w przeglądarce.
 *
 * Blok jest przepisany bez zmian z gotowego kodu, więc sprawdzenie nie ocenia
 * jego wyglądu, tylko pilnuje rzeczy, które psują się po cichu przy
 * tłumaczeniu i przy wklejaniu do Divi: że obie wersje mają ten sam układ, że
 * angielska nie ma polskich ogonków, że strzałka wciąż jedzie i chowa się za
 * kafelkami, i że nic nie rozpycha strony w bok.
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
	await p.waitForTimeout( 300 );
	return { p, c };
};

console.log( '\nblok na stronie' );
{
	const { p, c } = await otworz( 1440 );

	const r = await p.evaluate( () => {
		const kroki = [ ...document.querySelectorAll( '.lst-krok' ) ];
		const blok = document.querySelector( '.lst-kroki' );

		return {
			krokow: kroki.length,
			ikon: document.querySelectorAll( '.lst-kafel svg' ).length,
			wRzedzie: new Set( kroki.map( ( e ) => Math.round( e.getBoundingClientRect().top ) ) ).size,
			// Strzałka to warstwa ::after całego bloku, a kreski to ::before
			// kroków od drugiego w górę.
			strzalka: getComputedStyle( blok, '::after' ).animationName,
			kresek: kroki.filter( ( e ) => 'none' !== getComputedStyle( e, '::before' ).backgroundImage ).length,
			// Kafelek musi stać PIĘTRO WYŻEJ niż strzałka, bo inaczej nie ma
			// czego chować i strzałka przejeżdża po wierzchu ikony.
			kafelNad: getComputedStyle( document.querySelector( '.lst-kafel' ) ).zIndex,
			strzalkaPod: getComputedStyle( blok, '::after' ).zIndex,
			kafelKryje: getComputedStyle( document.querySelector( '.lst-kafel' ) ).backgroundColor,
			tytul: getComputedStyle( document.querySelector( '.lst-krok-tytul' ) ).color,
			opis: getComputedStyle( document.querySelector( '.lst-krok-opis' ) ).color,
			tlo: getComputedStyle( blok ).backgroundImage.slice( 0, 30 ),
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		};
	} );

	ok( 'cztery kroki, cztery ikony', 4 === r.krokow && 4 === r.ikon, `${ r.krokow } / ${ r.ikon }` );
	ok( 'stoją w jednym rzędzie', 1 === r.wRzedzie, `rzędów ${ r.wRzedzie }` );
	ok( 'trzy kreski między nimi', 3 === r.kresek, `${ r.kresek } z 3` );
	ok( 'jedna strzałka i ona jedzie', 'lst-plynie' === r.strzalka, r.strzalka );
	ok( 'kafelek kryje strzałkę, a nie odwrotnie',
		Number( r.kafelNad ) > Number( r.strzalkaPod ) && /rgb\(/.test( r.kafelKryje ) && ! /rgba/.test( r.kafelKryje ),
		`kafel z-index ${ r.kafelNad }, strzałka ${ r.strzalkaPod }, tło kafla ${ r.kafelKryje }` );
	ok( 'blok ma własne tło', /gradient/.test( r.tlo ), r.tlo );
	ok( 'tytuł czytelny (>= 4,5 : 1)', kontrast( r.tytul, 'rgb(30, 36, 35)' ) >= 4.5, `${ kontrast( r.tytul, 'rgb(30, 36, 35)' ) } : 1` );
	ok( 'podpis czytelny (>= 4,5 : 1)', kontrast( r.opis, 'rgb(30, 36, 35)' ) >= 4.5, `${ kontrast( r.opis, 'rgb(30, 36, 35)' ) } : 1` );
	ok( 'bez suwaka poziomego', 0 === r.poziom, String( r.poziom ) );

	await c.close();
}

console.log( '\nna telefonie' );
{
	const { p, c } = await otworz( 390 );

	const r = await p.evaluate( () => ( {
		wRzedzie: new Set( [ ...document.querySelectorAll( '.lst-krok' ) ].map( ( e ) => Math.round( e.getBoundingClientRect().top ) ) ).size,
		dlugaStrzalka: getComputedStyle( document.querySelector( '.lst-kroki' ), '::after' ).display,
		krotkie: [ ...document.querySelectorAll( '.lst-krok' ) ]
			.filter( ( e ) => 'none' !== getComputedStyle( e, '::after' ).animationName ).length,
		poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
	} ) );

	ok( 'kroki stają jeden pod drugim', 4 === r.wRzedzie, `rzędów ${ r.wRzedzie }` );
	ok( 'długi tor znika, a strzałka idzie odcinkami', 'none' === r.dlugaStrzalka && 3 === r.krotkie,
		`tor ${ r.dlugaStrzalka }, odcinków ${ r.krotkie }` );
	ok( 'bez suwaka poziomego', 0 === r.poziom, String( r.poziom ) );

	await c.close();
}

console.log( '\nobie wersje językowe' );
{
	const en = fs.readFileSync( TU + '/KROKI-en.html', 'utf8' );
	const pl = fs.readFileSync( TU + '/KROKI-pl.html', 'utf8' );
	const ile = ( s ) => ( s.match( /class="lst-krok"/g ) || [] ).length;
	const tresc = ( s ) => s.slice( s.indexOf( '<div class="lst-kroki"' ), s.indexOf( '<style>' ) );

	ok( 'ten sam układ po angielsku i po polsku', ile( en ) === ile( pl ) && 4 === ile( en ), `en ${ ile( en ) }, pl ${ ile( pl ) }` );
	ok( 'style identyczne co do znaku', en.slice( en.indexOf( '<style>' ) ) === pl.slice( pl.indexOf( '<style>' ) ),
		'jeden arkusz, dwa pliki' );
	ok( 'polska wersja jest po polsku', /Arkusz Google/.test( pl ), 'Arkusz Google' );
	ok( 'angielska nie ma polskich ogonków', ! /[ąćęłńóśźż]/i.test( tresc( en ) ), 'bez ogonków' );
}

ok( 'bez błędów skryptu', 0 === bledy.length, bledy.join( ' | ' ) || '0' );

await b.close();
console.log( `\n${ pass } PASS, ${ fail } FAIL\n` );
process.exit( fail ? 1 : 0 );
