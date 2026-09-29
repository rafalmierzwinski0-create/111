/**
 * Sprawdzenie modułu „what it does”.
 *
 * Moduł niesie w sobie prawdziwy arkusz stylów i prawdziwy skrypt wtyczki, więc
 * sprawdzane jest jedno i drugie: czy strona się składa, czy tabela naprawdę
 * sortuje i szuka, czy przeżywa <br />, które Divi wstawia w każdym złamaniu
 * wiersza, czy wrogi motyw nie rozbija ani modułu, ani tabeli pod nim, i czy
 * bez JavaScriptu wszystko jest widoczne.
 *
 * Użycie: node landing/mozliwosci/spr.mjs   (z katalogu landing/mozliwosci)
 */

import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';

const modul = fs.readFileSync( 'MOZLIWOSCI-en.html', 'utf8' )
	// Zrzuty na żywej stronie leżą w Multimediach; tu leżą obok pliku.
	.replace( /ADRES\//g, 'zrzuty/' );

let pass = 0, fail = 0;
const ok = ( n, w, d ) => { if ( w ) { pass++; console.log( '  ✓ ', n, ' — ', d ); } else { fail++; console.log( '  ✗ ', n, ' — ', d ); } };

// Divi wstawia <br /> w każdym złamanym wierszu poza <style> i <script>.
const divi = ( s ) => {
	const cz = s.split( /(<style>[\s\S]*?<\/style>|<script>[\s\S]*?<\/script>)/ );
	return cz.map( ( c, i ) => ( i % 2 ? c : c.split( '\n' ).join( '<br />\n' ) ) ).join( '' );
};

// Ten sam motyw, którym mierzy się landing/pokaz-na-zywo/spr.mjs.
const WROGI = 'div,span,p,button,td,th{border:2px solid #f0a!important}'
	+ 'p,td{margin:40px!important;background:#ff0!important;font-family:"Comic Sans MS"!important;text-transform:uppercase!important}'
	+ 'button{color:#00f!important;font-family:"Comic Sans MS"!important}'
	+ 'ul{list-style:disc;padding-left:3em}';

const strona = ( tresc, { wrogi = false, bezJs = false } = {} ) => `<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>What it does</title>
<style>html,body{margin:0;background:#141b1a;color:#eaf3f1;font-family:system-ui}
.et_pb_section{padding:40px 0}.et_pb_row{width:90%;max-width:1800px;margin:0 auto}
${ wrogi ? WROGI : '' }</style>
</head><body><div class="et_pb_section"><div class="et_pb_row">
${ bezJs ? tresc.replace( /<script>[\s\S]*?<\/script>/g, '' ) : tresc }
</div></div></body></html>`;

const b = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );

const otworz = async ( html, { width = 1500, height = 1100, ruch = true } = {} ) => {
	const c = await b.newContext( { viewport: { width, height }, reducedMotion: ruch ? 'no-preference' : 'reduce' } );
	const p = await c.newPage();
	const bledy = [];
	p.on( 'pageerror', ( e ) => bledy.push( e.message ) );
	await p.goto( 'file://' + process.cwd() + '/' );
	await p.setContent( html, { waitUntil: 'load' } );
	// Obrazki mają loading="lazy", a wejście kafelków trwa 260 ms z opóźnieniem.
	await p.evaluate( async () => {
		for ( let y = 0; y < document.body.scrollHeight; y += 700 ) {
			window.scrollTo( 0, y );
			await new Promise( ( r ) => setTimeout( r, 60 ) );
		}
		window.scrollTo( 0, 0 );
	} );
	await p.waitForTimeout( 700 );
	return { p, c, bledy };
};

/** Najgorszy kontrast wśród moich własnych napisów. */
const kontrast = ( p ) => p.evaluate( () => {
	const parse = ( c ) => { const m = ( c || '' ).match( /rgba?\(([^)]+)\)/ ); if ( ! m ) return null;
		const a = m[ 1 ].split( /[\s,\/]+/ ).filter( Boolean ).map( Number );
		return { r: a[ 0 ], g: a[ 1 ], b: a[ 2 ], a: a[ 3 ] === undefined ? 1 : a[ 3 ] }; };
	const over = ( t, u ) => ( { r: t.r * t.a + u.r * ( 1 - t.a ), g: t.g * t.a + u.g * ( 1 - t.a ), b: t.b * t.a + u.b * ( 1 - t.a ), a: 1 } );
	const behind = ( el ) => {
		/*
		 * The layers between this text and something opaque, outermost last —
		 * and, where an element frosts what is behind it, the multiplication
		 * that does the darkening. backdrop-filter: brightness() does not cover
		 * the backdrop, it multiplies it, which is the whole reason the Glass
		 * skin can be see-through and still legible; a walker that only added up
		 * background colours would read that panel as nearly transparent and
		 * report a contrast the screen never shows.
		 */
		const layers = [];
		let node = el;

		while ( node && node.nodeType === 1 ) {
			const style = getComputedStyle( node );
			const filter = style.backdropFilter || style.webkitBackdropFilter || 'none';
			const bright = /brightness\(\s*([\d.]+)%?\s*\)/.exec( filter );
			const colour = parse( style.backgroundColor );
			const image = style.backgroundImage;

			if ( image && 'none' !== image ) {
				const stops = [ ...image.matchAll( /rgba?\([^)]+\)/g ) ].map( ( s ) => parse( s[ 0 ] ) ).filter( Boolean );
				const solid = stops.filter( ( s ) => s.a > 0.9 );
				if ( solid.length ) {
					layers.push( { colour: solid[ Math.floor( solid.length / 2 ) ] } );
					break;
				}
			}

			if ( colour && colour.a > 0 ) {
				layers.push( { colour } );
				if ( colour.a >= 0.999 ) {
					break;
				}
			}

			if ( bright ) {
				const k = Number( bright[ 1 ] ) * ( filter.includes( bright[ 1 ] + '%' ) ? 0.01 : 1 );
				layers.push( { bright: k } );
			}

			node = node.parentElement;
		}

		let out = { r: 255, g: 255, b: 255, a: 1 };

		for ( let i = layers.length - 1; i >= 0; i -= 1 ) {
			const step = layers[ i ];

			if ( step.bright !== undefined ) {
				out = { r: out.r * step.bright, g: out.g * step.bright, b: out.b * step.bright, a: 1 };
				continue;
			}

			out = step.colour.a >= 0.999 ? step.colour : over( step.colour, out );
		}

		return out;
	};
	const lum = ( c ) => { const f = ( v ) => { const s = v / 255; return s <= 0.03928 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 ); };
		return 0.2126 * f( c.r ) + 0.7152 * f( c.g ) + 0.0722 * f( c.b ); };
	let worst = { r: 99, co: '' };
	document.querySelectorAll( '.lst-mz [class*="lst-mz-"]' ).forEach( ( el ) => {
		if ( ! el.textContent.trim() || el.children.length && ! el.childNodes[ 0 ].nodeValue ) return;
		const ink = parse( getComputedStyle( el ).color ); if ( ! ink ) return;
		const pap = behind( el );
		const front = ink.a >= 0.999 ? ink : over( ink, pap );
		const a = lum( front ), c = lum( pap );
		const r = Math.round( ( ( Math.max( a, c ) + 0.05 ) / ( Math.min( a, c ) + 0.05 ) ) * 100 ) / 100;
		if ( r < worst.r ) worst = { r, co: el.className + ' „' + el.textContent.trim().slice( 0, 24 ) + '”' };
	} );
	return worst;
} );

console.log( '\nmoduł na stronie' );
{
	const { p, c, bledy } = await otworz( strona( modul ) );
	const r = await p.evaluate( () => ( {
		krokow: document.querySelectorAll( '.lst-mz-krok' ).length,
		etapow: document.querySelectorAll( '.lst-mz-etap' ).length,
		pozycji: document.querySelectorAll( '.lst-mz-pozycja' ).length,
		tabel: document.querySelectorAll( '.lst-mz .lstab' ).length,
		wierszy: document.querySelectorAll( '.lst-mz-stol tbody tr.lstab-row' ).length,
		kolumn: document.querySelectorAll( '.lst-mz-stol thead th' ).length,
		pigulek: document.querySelectorAll( '.lst-mz-stol .lstabp-pill' ).length,
		kropek: document.querySelectorAll( '.lst-mz-stol .lstabp-dot' ).length,
		slupkow: document.querySelectorAll( '.lst-mz-stol .lstabp-bar' ).length,
		przyciskow: document.querySelectorAll( '.lst-mz-stol .lstabp-cta-link' ).length,
		filtrow: document.querySelectorAll( '.lst-mz-stol .lstabp-facet' ).length,
		pobran: document.querySelectorAll( '.lst-mz-stol .lstabp-export-button' ).length,
		poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		// Tylko te, które mają własny tekst: pojemnik dziedziczy 16 px po
		// stronie i niczym nim nie pisze.
		rozmiary: [ ...new Set( [ ...document.querySelectorAll( '.lst-mz [class*="lst-mz-"]' ) ]
			.filter( ( e ) => [ ...e.childNodes ].some( ( n ) => 3 === n.nodeType && n.nodeValue.trim() ) )
			.map( ( e ) => Math.round( parseFloat( getComputedStyle( e ).fontSize ) ) ) ) ].sort( ( a, x ) => a - x ),
	} ) );

	ok( 'trzy kroki, trzy etapy, sześć pozycji legendy', r.krokow === 3 && r.etapow === 3 && r.pozycji === 6, `${ r.krokow }/${ r.etapow }/${ r.pozycji }` );
	ok( 'dwie tabele: na stronie i w telefonie', r.tabel === 2, String( r.tabel ) );
	ok( 'dziesięć wierszy, sześć kolumn', r.wierszy === 10 && r.kolumn === 6, `${ r.wierszy } × ${ r.kolumn }` );
	ok( 'wszystko z Pro jest na tabeli', r.pigulek === 10 && r.kropek === 10 && r.slupkow === 10 && r.przyciskow === 8 && r.filtrow === 2 && r.pobran === 3,
		`pigułek ${ r.pigulek }, kropek ${ r.kropek }, słupków ${ r.slupkow }, przycisków ${ r.przyciskow }, filtrów ${ r.filtrow }, pobrań ${ r.pobran }` );
	ok( 'bez suwaka poziomego', r.poziom === 0, String( r.poziom ) );
	ok( 'rozmiary pisma tylko 14, 18 i 20', r.rozmiary.every( ( x ) => [ 14, 18, 20 ].includes( x ) ), r.rozmiary.join( '/' ) );
	ok( 'bez błędów skryptu', bledy.length === 0, bledy.join( ' | ' ) || '0' );

	const zrzuty = await p.evaluate( () => ( {
		ile: document.querySelectorAll( '.lst-mz-okno img' ).length,
		wczytane: [ ...document.querySelectorAll( '.lst-mz-okno img' ) ].filter( ( i ) => i.complete && i.naturalWidth > 0 ).length,
		puste: [ ...document.querySelectorAll( '.lst-mz-okno img' ) ].filter( ( i ) => ! i.complete || ! i.naturalWidth ).map( ( i ) => i.getAttribute( 'src' ) ),
		szklo: document.querySelectorAll( '.lst-mz-szklo .lstab-style-glass' ).length,
		tlo: getComputedStyle( document.querySelector( '.lst-mz-szklo' ) ).backgroundImage.includes( 'gradient' ),
		rozmycie: getComputedStyle( document.querySelector( '.lst-mz-szklo .lstab-scroll' ) ).backdropFilter,
	} ) );
	ok( 'trzy zrzuty z kokpitu, wszystkie wczytane', zrzuty.ile === 3 && zrzuty.wczytane === 3, zrzuty.puste.join( ', ' ) || '3 z 3' );
	ok( 'tabela jest szklana i ma przez co patrzeć', zrzuty.szklo === 2 && zrzuty.tlo && /blur/.test( zrzuty.rozmycie ),
		`szkło ${ zrzuty.szklo }, gradient ${ zrzuty.tlo }, ${ zrzuty.rozmycie }` );

	const ruch = await p.evaluate( () => ( {
		niewidoczne: [ ...document.querySelectorAll( '.lst-mz [class*="lst-mz-"]' ) ].filter( ( e ) => getComputedStyle( e ).opacity === '0' ).length,
		animowane: [ ...document.querySelectorAll( '.lst-mz-krok' ) ].map( ( e ) => getComputedStyle( e ).animationName ),
		kreska: getComputedStyle( document.querySelector( '.lst-mz-etap + .lst-mz-etap' ), '::before' ).animationName,
		puls: getComputedStyle( document.querySelector( '.lst-mz-puls' ) ).animationName,
		ile: getComputedStyle( document.querySelector( '.lst-mz-puls' ) ).animationIterationCount,
	} ) );
	ok( 'jest ruch, a mimo to nic nie jest schowane', ruch.niewidoczne === 0 && ruch.animowane.every( ( x ) => x === 'lst-mz-wejscie' ) && ruch.kreska === 'lst-mz-kreska' && ruch.puls === 'lst-mz-puls',
		`schowanych ${ ruch.niewidoczne }, wejście ${ ruch.animowane[ 0 ] }, kreska ${ ruch.kreska }, puls ${ ruch.puls }` );
	// Nic nie może migać bez końca — puls ma odliczoną liczbę powtórzeń.
	ok( 'żadna animacja nie chodzi w kółko bez końca', ruch.ile !== 'infinite', `powtórzeń pulsu: ${ ruch.ile }` );

	/*
	 * Moduł niesie arkusz wtyczki w tym samym <style>, więc jedna zbłąkana
	 * klamra w moim CSS zjada regułę wtyczki stojącą za nią — tak raz zginęło
	 * „container-type” i tabela przestała się składać w karty na telefonie.
	 * Stąd ten czujnik: liczę reguły, które naprawdę doszły do przeglądarki.
	 */
	const arkusz = await p.evaluate( () => {
		const c = document.querySelector( '.lst-mz-stol .lstab' ).closest( '.lstab-container' );
		const arkusze = [ ...document.styleSheets ].filter( ( x ) => { try { return !! x.cssRules; } catch ( e ) { return false; } } );
		const sierotki = arkusze.flatMap( ( x ) => [ ...x.cssRules ] ).filter( ( r ) => r.selectorText && /[{}]/.test( r.selectorText ) ).length;
		return { typ: getComputedStyle( c ).containerType, sierotki };
	} );
	ok( 'arkusz wtyczki dojechał cały — zapytanie kontenerowe działa',
		arkusz.typ === 'inline-size' && arkusz.sierotki === 0, `container-type ${ arkusz.typ }, pokiereszowanych selektorów ${ arkusz.sierotki }` );

	const rama = await p.evaluate( () => {
		const k = document.querySelector( '.lst-mz-rama' ).getBoundingClientRect();
		return { lewo: Math.round( k.left ), prawo: Math.round( innerWidth - k.right ) };
	} );
	ok( 'strona jest wyśrodkowana — luz z lewej równa się luzowi z prawej',
		Math.abs( rama.lewo - rama.prawo ) <= 1 && rama.lewo > 0, `z lewej ${ rama.lewo } px, z prawej ${ rama.prawo } px` );

	const k = await kontrast( p );
	ok( 'najsłabszy napis ma co najmniej 4,5 : 1', k.r >= 4.5, `${ k.r } : 1 — ${ k.co }` );

	await p.locator( '.lst-mz' ).screenshot( { path: 'mozliwosci-1500.png' } );
	await p.locator( '.lst-mz-legenda' ).screenshot( { path: 'mozliwosci-legenda.png' } );
	await p.locator( '.lst-mz-stol' ).screenshot( { path: 'mozliwosci-stol.png' } );
	await p.locator( '.lst-mz-ekran' ).first().screenshot( { path: 'mozliwosci-ekran.png' } );
	await p.locator( '.lst-mz-telefon-blok' ).screenshot( { path: 'mozliwosci-telefon.png' } );
	await p.locator( '.lst-mz-telefon-blok' ).screenshot( { path: 'mozliwosci-telefon.png' } );
	await c.close();
}

console.log( '\ntabela naprawdę działa' );
{
	const { p, c } = await otworz( strona( modul ) );
	const przed = await p.$$eval( '.lst-mz-stol tbody tr.lstab-row td:nth-child(3) .lstab-cell-value', ( n ) => n.map( ( x ) => x.textContent.trim() ) );
	await p.click( '.lst-mz-stol thead th:nth-child(3) .lstab-sort' );
	await p.waitForTimeout( 250 );
	const po = await p.$$eval( '.lst-mz-stol tbody tr.lstab-row td:nth-child(3) .lstab-cell-value', ( n ) => n.map( ( x ) => x.textContent.trim() ) );
	const rosnaco = po.map( Number ).every( ( v, i, a ) => ! i || a[ i - 1 ] <= v );
	ok( 'kliknięcie nagłówka sortuje', rosnaco && po.join() !== przed.join(), po.join( ' ' ) );

	await p.fill( '.lst-mz-stol .lstab-search-input', 'windgap' );
	await p.waitForTimeout( 300 );
	const widocznych = await p.$$eval( '.lst-mz-stol tbody tr.lstab-row', ( n ) => n.filter( ( x ) => ! x.hidden ).length );
	const zaznaczone = await p.$$eval( '.lst-mz-stol .lstab-hit', ( n ) => n.length );
	ok( 'szukanie zawęża i zaznacza trafienie', widocznych === 1 && zaznaczone > 0, `${ widocznych } wiersz, ${ zaznaczone } zaznaczeń` );
	await c.close();
}

console.log( '\nDivi wstawia <br />' );
{
	const { p, c, bledy } = await otworz( strona( divi( modul ) ) );
	const r = await p.evaluate( () => ( {
		wierszy: document.querySelectorAll( '.lst-mz-stol tbody tr.lstab-row' ).length,
		krokow: document.querySelectorAll( '.lst-mz-krok' ).length,
		brWidoczne: [ ...document.querySelectorAll( '.lst-mz br' ) ].some( ( x ) => getComputedStyle( x ).display !== 'none' ),
		rozbite: document.body.innerHTML.includes( 'data-lstab-id="' ) === false,
		poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
	} ) );
	ok( 'układ i tabela przeżywają <br />', r.wierszy === 10 && r.krokow === 3 && ! r.brWidoczne && ! r.rozbite && r.poziom === 0 && bledy.length === 0,
		`${ r.wierszy } wierszy, ${ r.krokow } kroki, suwak ${ r.poziom }, błędy ${ bledy.length }` );
	await c.close();
}

console.log( '\nwrogi motyw' );
{
	const { p, c } = await otworz( strona( modul, { wrogi: true } ) );
	const r = await p.evaluate( () => {
		const s = ( sel, wl ) => { const e = document.querySelector( sel ); return e ? getComputedStyle( e )[ wl ] : null; };
		return {
			opis: [ s( '.lst-mz-opis', 'fontFamily' ).split( ',' )[ 0 ].replace( /"/g, '' ),
				s( '.lst-mz-opis', 'textAlign' ), s( '.lst-mz-opis', 'textTransform' ),
				s( '.lst-mz-opis', 'marginLeft' ), s( '.lst-mz-opis', 'fontSize' ) ],
			krok: [ s( '.lst-mz-krok', 'borderTopWidth' ), s( '.lst-mz-krok', 'backgroundColor' ) ],
			numer: s( '.lst-mz-numer', 'fontFamily' ).split( ',' )[ 0 ].replace( /"/g, '' ),
			znak: s( '.lst-mz-znak', 'textTransform' ),
			// Tabela broni się własnym arkuszem, a przed motywem malującym
			// każdą komórkę „!important” nie obroni się żadna. Sprawdzamy, że
			// dalej działa i mieści się w stronie.
			wierszy: document.querySelectorAll( '.lst-mz-stol tbody tr.lstab-row' ).length,
			pigulek: document.querySelectorAll( '.lst-mz-stol .lstabp-pill' ).length,
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		};
	} );
	ok( 'motyw nie przejmuje modułu',
		r.opis[ 0 ] === 'IBM Plex Sans' && r.opis[ 1 ] === 'left' && r.opis[ 2 ] === 'none' && r.opis[ 3 ] === '0px' && r.opis[ 4 ] === '18px',
		r.opis.join( ' / ' ) );
	ok( 'kafelki i etykiety zostają sobą', r.krok[ 0 ] === '1px' && r.numer === 'IBM Plex Mono' && r.znak === 'uppercase',
		`ramka ${ r.krok[ 0 ] }, numer ${ r.numer }, znak ${ r.znak }` );
	ok( 'tabela dalej jest cała i mieści się w stronie', r.wierszy === 10 && r.pigulek === 10 && r.poziom === 0,
		`${ r.wierszy } wierszy, ${ r.pigulek } pigułek, suwak ${ r.poziom }` );
	await c.close();
}

console.log( '\nbez JavaScriptu' );
{
	const { p, c } = await otworz( strona( modul, { bezJs: true } ) );
	const r = await p.evaluate( () => ( {
		widocznych: [ ...document.querySelectorAll( '.lst-mz-krok, .lst-mz-pozycja, .lst-mz-etap' ) ].filter( ( x ) => getComputedStyle( x ).opacity === '1' ).length,
		wszystkich: document.querySelectorAll( '.lst-mz-krok, .lst-mz-pozycja, .lst-mz-etap' ).length,
		wierszy: [ ...document.querySelectorAll( '.lst-mz-stol tbody tr.lstab-row' ) ].filter( ( x ) => ! x.hidden ).length,
	} ) );
	ok( 'wszystko widoczne bez skryptu', r.widocznych === r.wszystkich && r.wierszy === 10, `${ r.widocznych } z ${ r.wszystkich }, ${ r.wierszy } wierszy` );
	await c.close();
}

console.log( '\nmniej ruchu' );
{
	const { p, c } = await otworz( strona( modul ), { ruch: false } );
	const r = await p.evaluate( () => ( {
		widocznych: [ ...document.querySelectorAll( '.lst-mz-krok, .lst-mz-pozycja, .lst-mz-etap' ) ].filter( ( x ) => getComputedStyle( x ).opacity === '1' ).length,
		animacje: [ ...document.querySelectorAll( '.lst-mz-krok' ) ].map( ( x ) => getComputedStyle( x ).animationName ),
		kreska: getComputedStyle( document.querySelector( '.lst-mz-etap + .lst-mz-etap' ), '::before' ).animationName,
		puls: getComputedStyle( document.querySelector( '.lst-mz-puls' ) ).animationName,
	} ) );
	ok( 'przy prefers-reduced-motion nic się nie rusza, a wszystko widać',
		r.widocznych === 12 && r.animacje.every( ( x ) => x === 'none' ) && r.kreska === 'none' && r.puls === 'none',
		`widocznych ${ r.widocznych }, wejście ${ r.animacje[ 0 ] }, kreska ${ r.kreska }, puls ${ r.puls }` );
	await c.close();
}

console.log( '\ntelefon' );
{
	const { p, c } = await otworz( strona( modul ), { width: 390, height: 900 } );
	const r = await p.evaluate( () => ( {
		kolumny: getComputedStyle( document.querySelector( '.lst-mz-kroki' ) ).gridTemplateColumns.split( ' ' ).length,
		karty: getComputedStyle( document.querySelector( '.lst-mz-stol thead' ) ).display,
		etykiety: getComputedStyle( document.querySelector( '.lst-mz-stol .lstab-cell-label' ) ).display,
		poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
	} ) );
	ok( 'jedna kolumna i tabela złożona w karty', r.kolumny === 1 && r.karty === 'none' && r.etykiety === 'block', `${ r.kolumny } kolumna, thead ${ r.karty }` );

	const pigulka = await p.evaluate( () => {
		const el = document.querySelector( '.lst-mz-stol .lstabp-pill .lstab-cell-value' );
		const karta = el.closest( 'td' );
		return { pigulka: Math.round( el.getBoundingClientRect().width ), karta: Math.round( karta.getBoundingClientRect().width ) };
	} );
	ok( 'pigułka w karcie jest szerokości słowa, nie karty', pigulka.pigulka < pigulka.karta * 0.6,
		`${ pigulka.pigulka } px w karcie ${ pigulka.karta } px` );
	ok( 'bez suwaka poziomego na telefonie', r.poziom === 0, String( r.poziom ) );
	await p.locator( '.lst-mz' ).screenshot( { path: 'mozliwosci-390.png' } );
	await c.close();
}

console.log( `\n${ pass } PASS, ${ fail } FAIL` );
await b.close();
process.exit( fail ? 1 : 0 );
