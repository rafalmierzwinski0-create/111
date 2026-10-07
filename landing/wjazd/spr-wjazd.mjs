/*
 * Wjazd dla całej witryny: strona złożona z prawdziwych sekcji, opakowanych
 * tak jak opakowuje je Divi, z kodem integracji na końcu <body>.
 *
 * Uruchomienie (z katalogu repozytorium): node landing/wjazd/spr-wjazd.mjs
 */
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';
import path from 'path';

const TU = path.dirname( new URL( import.meta.url ).pathname );
const L = path.join( TU, '..' );
const wjazd = fs.readFileSync( path.join( TU, 'WJAZD-integracja.html' ), 'utf8' );

// Kolejność jak na stronie głównej.
const SEKCJE = [ 'naglowek/HERO-en.html', 'dwie-minuty/DWIE-MINUTY-en.html', 'roznica/kafle-en.html',
	'porownanie/porownanie-en.html', 'cennik/cennik-en.html', 'faq/FAQ-en.html', 'stopka/STOPKA-en.html' ];

const modul = ( t ) => `<div class="et_pb_section"><div class="et_pb_row"><div class="et_pb_column"><div class="et_pb_module et_pb_code"><div class="et_pb_code_inner">\n${ t }\n</div></div></div></div></div>`;

const strona = ( { zWjazdem = true } = {} ) => `<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Wjazd</title>
<style>html,body{margin:0;background:#232a29;color:#eaf3f1;font-family:system-ui}
.et_pb_section{padding:40px 0}.et_pb_row{width:90%;max-width:1800px;margin:0 auto}
p{padding-bottom:1em}p:not(.has-background):last-of-type{padding-bottom:0}</style>
</head><body><div id="page-container"><div id="et-main-area"><div id="main-content">
${ SEKCJE.map( ( s ) => modul( fs.readFileSync( path.join( L, s ), 'utf8' ) ) ).join( '\n' ) }
</div></div></div>
${ zWjazdem ? wjazd : '' }
</body></html>`;

fs.writeFileSync( '/tmp/wjazd-strona.html', strona() );
fs.writeFileSync( '/tmp/wjazd-bez.html', strona( { zWjazdem: false } ) );

const b = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
let pass = 0, fail = 0;
const ok = ( n, w, d ) => { if ( w ) { pass++; console.log( '  ✓ ', n, ' — ', d ); } else { fail++; console.log( '  ✗ ', n, ' — ', d ); } };

const otworz = async ( plik, { w = 1440, h = 900, ruch = 'no-preference' } = {} ) => {
	const c = await b.newContext( { viewport: { width: w, height: h }, reducedMotion: ruch } );
	const p = await c.newPage();
	const bledy = [];
	p.on( 'pageerror', ( e ) => bledy.push( e.message ) );
	await p.route( /fonts\.googleapis|fonts\.gstatic/, ( r ) => r.abort() );
	await p.goto( 'file://' + plik );
	await p.waitForTimeout( 300 );
	return { p, c, bledy };
};

const przewin = async ( p ) => {
	await p.evaluate( async () => {
		for ( let y = 0; y < document.body.scrollHeight; y += 300 ) {
			window.scrollTo( 0, y );
			await new Promise( ( r ) => setTimeout( r, 80 ) );
		}
	} );
	await p.waitForTimeout( 1400 );
};

console.log( '\n1440 px' );
{
	const { p, c, bledy } = await otworz( '/tmp/wjazd-strona.html' );
	const przed = await p.evaluate( () => {
		const czeka = [ ...document.querySelectorAll( '[data-wjazd="czeka"]' ) ];
		return {
			czeka: czeka.length,
			// nic, co widać od razu, nie jest przygaszone
			wOknie: czeka.filter( ( e ) => e.getBoundingClientRect().top < innerHeight * .9 ).length,
			hero: document.querySelectorAll( '.lst-h [data-wjazd]' ).length,
			kafli: document.querySelectorAll( '.lst-2m-kafel[data-wjazd]' ).length,
			wKaflu: document.querySelectorAll( '.lst-2m-kafel [data-wjazd]' ).length,
			tlo: getComputedStyle( document.querySelector( '[data-wjazd="czeka"]' ) ).opacity,
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		};
	} );
	ok( 'pod ekranem są rzeczy czekające na wjazd', przed.czeka >= 20, `${ przed.czeka } czeka` );
	ok( 'nic widocznego od razu nie jest przygaszone', przed.wOknie === 0 && przed.hero === 0, `w oknie ${ przed.wOknie }, w hero ${ przed.hero }` );
	ok( 'kafel wjeżdża w całości, nie po kawałku', przed.kafli >= 4 && przed.wKaflu === 0, `kafli ${ przed.kafli }, kawałków w kaflach ${ przed.wKaflu }` );
	ok( 'czekające są przygaszone', '0.4' === przed.tlo, przed.tlo );
	ok( 'bez suwaka poziomego od przesunięcia', przed.poziom === 0, String( przed.poziom ) );

	// W połowie wjazdu: rzecz jest w drodze, a światło na kaflu dalej biegnie.
	const kafel = p.locator( '.lst-2m-kafel.jest-ufac' );
	await kafel.evaluate( ( e ) => e.scrollIntoView( { block: 'center' } ) );
	await p.waitForTimeout( 140 );
	const wDrodze = await kafel.evaluate( ( e ) => ( {
		opacity: parseFloat( getComputedStyle( e ).opacity ),
		swiatlo: getComputedStyle( e ).animationName,
		stan: e.getAttribute( 'data-wjazd' ),
	} ) );
	ok( 'kafel wjeżdża po przewinięciu', 'jest' === wDrodze.stan && wDrodze.opacity < 1, `stan ${ wDrodze.stan }, krycie ${ wDrodze.opacity.toFixed( 2 ) }` );
	ok( 'światło na krawędzi biegnie dalej w trakcie wjazdu', /lst-2m-obieg/.test( wDrodze.swiatlo ), wDrodze.swiatlo );

	await przewin( p );
	const po = await p.evaluate( () => {
		const wszystkie = [ ...document.querySelectorAll( '[data-wjazd]' ) ];
		const nieDojechaly = wszystkie.filter( ( e ) => {
			const st = getComputedStyle( e );
			return 'jest' !== e.getAttribute( 'data-wjazd' ) || parseFloat( st.opacity ) < .999
				|| ( st.translate !== 'none' && st.translate !== '0px' && st.translate !== '0px 0px' );
		} );
		return {
			razem: wszystkie.length,
			nie: nieDojechaly.length,
			pierwszy: nieDojechaly[ 0 ] ? nieDojechaly[ 0 ].className + ' ' + getComputedStyle( nieDojechaly[ 0 ] ).opacity : '',
			swiatlo: getComputedStyle( document.querySelector( '.lst-2m-kafel' ) ).animationName,
		};
	} );
	ok( 'po przejechaniu strony wszystko stoi na swoim miejscu', po.nie === 0, `${ po.razem - po.nie } z ${ po.razem } ${ po.pierwszy }` );
	ok( 'światło na kaflach biegnie dalej po wjeździe', /lst-2m-obieg/.test( po.swiatlo ), po.swiatlo );

	// Najechanie dalej unosi kafel: wjazd nie zabrał mu transformacji.
	await p.locator( '.lst-2m-kafel.jest-jak' ).hover();
	await p.waitForTimeout( 400 );
	const unies = await p.locator( '.lst-2m-kafel.jest-jak' ).evaluate( ( e ) => new DOMMatrixReadOnly( getComputedStyle( e ).transform ).m42 );
	ok( 'najechanie dalej unosi kafel', unies < -1, `przesunięcie ${ unies } px` );

	// Karta obracana wjeżdża w całości i dalej obraca się pod kursorem.
	const karta = p.locator( '.lst-kafel-obrot' ).first();
	await karta.evaluate( ( e ) => e.scrollIntoView( { block: 'center' } ) );
	await p.waitForTimeout( 800 );
	await karta.hover();
	await p.waitForTimeout( 1200 );
	const obrot = await karta.evaluate( ( e ) => ( { wjazd: e.getAttribute( 'data-wjazd' ), m11: new DOMMatrixReadOnly( getComputedStyle( e ).transform ).m11 } ) );
	ok( 'karta obracana wjeżdża cała i dalej się obraca', 'jest' === obrot.wjazd && obrot.m11 < -.9, `wjazd ${ obrot.wjazd }, m11 ${ obrot.m11 }` );
	ok( 'bez błędów w konsoli', bledy.length === 0, bledy.join( ' | ' ) || '0' );
	await c.close();
}

console.log( '\n390 px, telefon' );
{
	const { p, c, bledy } = await otworz( '/tmp/wjazd-strona.html', { w: 390, h: 840 } );
	const czeka = await p.evaluate( () => document.querySelectorAll( '[data-wjazd="czeka"]' ).length );
	await przewin( p );
	const nie = await p.evaluate( () => [ ...document.querySelectorAll( '[data-wjazd]' ) ].filter( ( e ) => parseFloat( getComputedStyle( e ).opacity ) < .999 ).length );
	const poziom = await p.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	ok( 'na telefonie też wjeżdża i dojeżdża do końca', czeka >= 20 && nie === 0 && poziom === 0, `czeka ${ czeka }, nie dojechało ${ nie }, suwak ${ poziom }` );
	ok( 'bez błędów w konsoli', bledy.length === 0, bledy.join( ' | ' ) || '0' );
	await c.close();
}

console.log( '\nmniej ruchu' );
{
	const { p, c } = await otworz( '/tmp/wjazd-strona.html', { ruch: 'reduce' } );
	const ile = await p.evaluate( () => document.querySelectorAll( '[data-wjazd]' ).length );
	ok( '„ogranicz ruch” w systemie: nic nie jest uzbrojone', ile === 0, `${ ile } uzbrojonych` );
	await c.close();
}

console.log( '\nbez kodu wjazdu strona wygląda tak samo po przewinięciu' );
{
	const zrzut = async ( plik ) => {
		const { p, c } = await otworz( plik );
		await przewin( p );
		await p.evaluate( () => window.scrollTo( 0, 0 ) );
		await p.waitForTimeout( 300 );
		const wys = await p.evaluate( () => document.body.scrollHeight );
		await c.close();
		return wys;
	};
	const z = await zrzut( '/tmp/wjazd-strona.html' );
	const bez = await zrzut( '/tmp/wjazd-bez.html' );
	ok( 'wjazd nie zmienia wysokości strony', z === bez, `${ z } vs ${ bez } px` );
}

console.log( `\n${ pass } PASS, ${ fail } FAIL` );
await b.close();
process.exit( fail ? 1 : 0 );
