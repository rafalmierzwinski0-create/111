/**
 * Sprawdzenie modułu „what it does”.
 *
 * Moduł niesie w sobie prawdziwy arkusz stylów i prawdziwy skrypt wtyczki, więc
 * sprawdzane jest jedno i drugie: czy strona się składa, czy tabela naprawdę
 * sortuje i szuka, czy przeżywa <br />, które Divi wstawia w każdym złamaniu
 * wiersza, czy wrogi motyw nie rozbija ani modułu, ani tabeli pod nim, i czy
 * bez JavaScriptu wszystko jest widoczne.
 *
 * Użycie: node landing/mozliwosci/spr.mjs   (skądkolwiek)
 */

import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

// Pliki czytane są względem TEGO pliku, nie względem katalogu, z którego ktoś
// uruchomił sprawdzenie. Inaczej `node landing/mozliwosci/spr.mjs` z korzenia
// repozytorium wywraca się na ENOENT, choć wszystko jest na miejscu.
const TU = path.dirname( fileURLToPath( import.meta.url ) );
const czytaj = ( nazwa ) => fs.readFileSync( path.join( TU, nazwa ), 'utf8' );
// To samo dotyczy zrzutów niżej: zapisywane obok pliku, bo uruchomione
// z korzenia repozytorium zasypywały korzeń sześcioma plikami png.

const modul = czytaj( 'MOZLIWOSCI-en.html' )
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
// „table{border}” siedzi w arkuszu niejednego motywu i to ono rysowało
// białą obwódkę wokół tabeli na stronie klienta. Tu jest w wrogim motywie,
// żeby sprawdzenie niżej miało co mierzyć.
const WROGI = 'div,span,p,button,td,th,table,thead,tbody,tr{border:2px solid #f0a!important}'
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
	await p.goto( 'file://' + TU + '/' );
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
	// color-mix() wraca jako „color(srgb 0.28 0.55 0.5)” — składowe od zera do
	// jedynki, nie do 255 — a tusz pigułki to właśnie color-mix(). Czytnik pod
	// samo rgb() zwracał null i cicho pomijał dokładnie to, co ma zmierzyć.
	const parse = ( c ) => { c = ( c || '' ).trim(); const m = c.match( /^(?:rgba?|color)\(([^)]+)\)/ ); if ( ! m ) return null;
		const skala = c.startsWith( 'color(' ) ? 255 : 1;
		const a = m[ 1 ].replace( /^srgb\s+/, '' ).split( /[\s,\/]+/ ).filter( Boolean ).map( Number );
		return { r: a[ 0 ] * skala, g: a[ 1 ] * skala, b: a[ 2 ] * skala, a: a[ 3 ] === undefined ? 1 : a[ 3 ] }; };
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
	/*
	 * Napisy strony i wartości w tabeli, jednym przejściem. Tabela jest tu
	 * przedmiotem sprzedaży, więc nieczytelna pigułka na niej jest usterką
	 * strony tak samo jak nieczytelny nagłówek — a przez to, że wcześniej
	 * chodziło to wyłącznie po „lst-mz-*”, żadna wartość w tabeli nigdy nie
	 * była zmierzona.
	 */
	document.querySelectorAll( '.lst-mz [class*="lst-mz-"], .lst-mz [class*="lst-ar-"], .lst-mz .lstab tbody .lstab-cell-value' ).forEach( ( el ) => {
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
		krokow: document.querySelectorAll( '.lst-ar-komorka' ).length,
		etapow: document.querySelectorAll( '.lst-ar-etap' ).length,
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
		/*
		 * Tylko te, które mają własny tekst: pojemnik dziedziczy 16 px po
		 * stronie i niczym nim nie pisze. Tytuły sekcji są poza tą listą —
		 * landing/README.md wyjmuje je spod reguły 14/18/20, bo skalują się
		 * z szerokością okna. Sprawdzane są osobno, niżej.
		 */
		rozmiary: [ ...new Set( [ ...document.querySelectorAll( '.lst-mz [class*="lst-mz-"]:not( .lst-mz-naglowek ), .lst-mz [class*="lst-ar-"]' ) ]
			.filter( ( e ) => [ ...e.childNodes ].some( ( n ) => 3 === n.nodeType && n.nodeValue.trim() ) )
			.map( ( e ) => Math.round( parseFloat( getComputedStyle( e ).fontSize ) ) ) ) ].sort( ( a, x ) => a - x ),

		// Tytuły sekcji: szeryfowe i większe od reszty, jak na całej witrynie.
		tytuly: [ ...document.querySelectorAll( '.lst-mz-naglowek' ) ].map( ( e ) => ( {
			krój: getComputedStyle( e ).fontFamily.split( ',' )[ 0 ].replace( /"/g, '' ),
			px: Math.round( parseFloat( getComputedStyle( e ).fontSize ) ),
		} ) ),

		// Metka mono nad tytułem jest rzadkością, a nie rytmem: najwyżej jedna
		// na trzy sekcje, inaczej przestaje cokolwiek znaczyć.
		metek: document.querySelectorAll( '.lst-mz-etykieta' ).length,
		sekcji: document.querySelectorAll( '.lst-mz-blok' ).length,

		/*
		 * Ile pozycji legendy obiecuje zdanie nad nią. Liczba wpisana tu na
		 * sztywno znaczyła tylko tyle, że ktoś ją ostatnio poprawił; to pilnuje
		 * prawdziwej usterki: wstęp mówi „dziewięć”, a kafli jest osiem.
		 */
		obiecane: ( () => {
			const slowa = { three: 3, four: 4, five: 5, six: 6, seven: 7, eight: 8, nine: 9, ten: 10, eleven: 11, twelve: 12 };
			const wstep = [ ...document.querySelectorAll( '.lst-mz-wstep' ) ]
				.find( ( e ) => /on the table above/i.test( e.textContent ) );
			const pierwsze = wstep ? wstep.textContent.trim().split( /\s+/ )[ 0 ].toLowerCase() : '';

			return slowa[ pierwsze ] || Number( pierwsze ) || 0;
		} )(),
		/*
		 * Kazda pozycja ma swoj rysunek, i zaden nie jest uzyty dwa razy.
		 * Przedtem stala tu litera kolumny, a przy rzeczach, ktore nie siedza
		 * w zadnej kolumnie, wychodzilo z tego „Under it” i „Narrow it”.
		 * Powtorzony rysunek to zwykle pomylka przy kopiowaniu.
		 */
		rysunki: ( () => {
			const karty = [ ...document.querySelectorAll( '.lst-mz-legenda .lst-mz-pozycja' ) ];
			const ksztalty = karty.map( ( k ) => {
				const svg = k.querySelector( '.lst-mz-znak-adres .lst-mz-ikona' );

				return svg ? svg.innerHTML.replace( /\s+/g, '' ) : '';
			} );

			return {
				kart: karty.length,
				zRysunkiem: ksztalty.filter( Boolean ).length,
				roznych: new Set( ksztalty.filter( Boolean ) ).size,
				// Litery kolumn i polecenia dla czytelnika: ma ich tam nie byc.
				litery: karty.filter( ( k ) => /\S/.test( ( k.querySelector( '.lst-mz-znak-adres' ) || {} ).textContent || '' ) ).length,
			};
		} )(),
		wolnych: [ ...document.querySelectorAll( '.lst-mz-legenda .lst-mz-znak-slowo' ) ].filter( ( e ) => /free/i.test( e.textContent ) ).length,
		platnych: [ ...document.querySelectorAll( '.lst-mz-legenda .lst-mz-znak-slowo' ) ].filter( ( e ) => /pro/i.test( e.textContent ) ).length,

		// Myślnik i półpauza: zero. Są znakiem, po którym poznaje się tekst
		// pisany maszynowo, i na tej stronie nie ma ich ani jednej.
		myslniki: ( document.querySelector( '.lst-mz' ).innerText.match( /[\u2013\u2014]/g ) || [] ).length,
	} ) );

	ok( 'trzy kroki, trzy etapy, a legenda tyle pozycji, ile obiecuje jej wstęp',
		r.krokow === 3 && r.etapow === 3 && r.pozycji === r.obiecane,
		`${ r.krokow }/${ r.etapow }, pozycji ${ r.pozycji }, wstęp obiecuje ${ r.obiecane }` );
	/*
	 * Ta podstrona ma sprzedawać wtyczkę, a nie sam dodatek Pro: na tabeli ma
	 * być widać więcej rzeczy z wersji darmowej niż z płatnej. Liczone, bo
	 * legenda rośnie o kolejne „a to jest w Pro” samo z siebie.
	 */
	ok( 'każda pozycja legendy ma swój rysunek, żaden nie powtórzony, żadnych liter',
		r.rysunki.zRysunkiem === r.rysunki.kart && r.rysunki.roznych === r.rysunki.kart && r.rysunki.litery === 0,
		`kart ${ r.rysunki.kart }, z rysunkiem ${ r.rysunki.zRysunkiem }, różnych ${ r.rysunki.roznych }, z napisem ${ r.rysunki.litery }` );
	ok( 'na tabeli widać więcej rzeczy z wersji darmowej niż z Pro',
		r.wolnych > r.platnych, `Free ${ r.wolnych }, Pro ${ r.platnych }` );
	ok( 'dwie tabele: na stronie i w telefonie', r.tabel === 2, String( r.tabel ) );
	ok( 'dziesięć wierszy, sześć kolumn', r.wierszy === 10 && r.kolumn === 6, `${ r.wierszy } × ${ r.kolumn }` );
	ok( 'wszystko z Pro jest na tabeli', r.pigulek === 10 && r.kropek === 10 && r.slupkow === 10 && r.przyciskow === 10 && r.filtrow === 2 && r.pobran === 3,
		`pigułek ${ r.pigulek }, kropek ${ r.kropek }, słupków ${ r.slupkow }, przycisków ${ r.przyciskow }, filtrów ${ r.filtrow }, pobrań ${ r.pobran }` );
	ok( 'bez suwaka poziomego', r.poziom === 0, String( r.poziom ) );
	ok( 'rozmiary pisma tylko 14, 18 i 20', r.rozmiary.every( ( x ) => [ 14, 18, 20 ].includes( x ) ), r.rozmiary.join( '/' ) );
	ok( 'tytuły sekcji są szeryfowe i większe od reszty',
		r.tytuly.length === 6 && r.tytuly.every( ( t ) => 'Inria Serif' === t.krój && t.px > 20 ),
		r.tytuly.map( ( t ) => `${ t.krój } ${ t.px }` ).join( ', ' ) );
	ok( 'metka mono najwyżej raz na trzy sekcje',
		r.metek <= Math.ceil( r.sekcji / 3 ), `${ r.metek } metek na ${ r.sekcji } sekcji` );
	ok( 'ani jednego myślnika na stronie', 0 === r.myslniki, `${ r.myslniki }` );
	ok( 'bez błędów skryptu', bledy.length === 0, bledy.join( ' | ' ) || '0' );

	const zrzuty = await p.evaluate( () => ( {
		ile: document.querySelectorAll( '.lst-mz-okno img' ).length,
		wczytane: [ ...document.querySelectorAll( '.lst-mz-okno img' ) ].filter( ( i ) => i.complete && i.naturalWidth > 0 ).length,
		puste: [ ...document.querySelectorAll( '.lst-mz-okno img' ) ].filter( ( i ) => ! i.complete || ! i.naturalWidth ).map( ( i ) => i.getAttribute( 'src' ) ),
		/*
		 * Obie tabele na tej stronie (ta na szerokim ekranie i ta w telefonie)
		 * stoją na szablonie KARTY, przemalowanym na kolory strony. Karty, bo
		 * strona pokazuje, co wtyczka potrafi, a nie że umie narysować kratkę:
		 * pomalowany wiersz jest tu pomalowaną kartą, a na telefonie to samo
		 * ustawienie robi z tabeli listę kart, więc jedno i drugie wygląda jak
		 * jedno. Sprawdzane, bo szablon wraca do Północy przy każdej próbie
		 * „przywrócenia jak było".
		 */
		karty: document.querySelectorAll( '.lst-mz .lstab-style-cards' ).length,
		/* I że karty naprawdę są kartami: wiersz bez własnego koloru, a kolor
		   na komórkach, bo inaczej zaokrąglone rogi nie byłyby zaokrąglone. */
		wierszBezTla: ( () => {
			const w = document.querySelector( '.lst-mz .lstab-style-cards tbody tr.lstab-row' );
			return w ? getComputedStyle( w ).backgroundColor : '';
		} )(),
		/*
		 * Jasność tabeli kontra jasność strony i kontra ekran okienek.
		 *
		 * Tabela ma być z tej strony, a nie z innej: ciemna jak okienka pod nią
		 * i tylko odrobinę ciemniejsza od samej strony. Wcześniej stał tu ciepły
		 * papier — jedyny jasny przedmiot na ciemnej stronie — i właśnie to
		 * wyglądało jak wklejone z innej witryny.
		 */
		jasnosc: ( () => {
			const lum = ( c ) => {
				const n = ( c.match( /[\d.]+/g ) || [ 0, 0, 0 ] ).map( Number );
				const skala = /^color\(/.test( c ) ? 255 : 1;
				const f = ( v ) => { const x = ( v * skala ) / 255; return x <= 0.03928 ? x / 12.92 : Math.pow( ( x + 0.055 ) / 1.055, 2.4 ); };

				return 0.2126 * f( n[ 0 ] ) + 0.7152 * f( n[ 1 ] ) + 0.0722 * f( n[ 2 ] );
			};
			/*
			 * Mierzona jest KARTA, nie panel przewijania.
			 *
			 * W szablonie Karty panel nie ma własnego tła — kolor siedzi na
			 * komórkach, bo tylko tak karta zachowuje zaokrąglone rogi. Czytany
			 * stamtąd „papier” wychodził rgba(0, 0, 0, 0), czyli jasność zero,
			 * i sprawdzenie przechodziło na przezroczystości: przepuściłoby
			 * dowolny kolor tabeli, byle sama tabela nic nie malowała. Czytana
			 * jest komórka, czyli to, co widać.
			 */
			const papier = lum( getComputedStyle( document.querySelector( '.lst-mz-stol .lstab-table tbody td' ) ).backgroundColor );
			const strona = lum( getComputedStyle( document.body ).backgroundColor );
			const okno = lum( getComputedStyle( document.querySelector( '.lst-mz-okno' ) ).backgroundColor );

			return {
				papier: Math.round( papier * 1000 ) / 1000,
				strona: Math.round( strona * 1000 ) / 1000,
				okno: Math.round( okno * 1000 ) / 1000,
			};
		} )(),
		/*
		 * Pasek nagłówków ma SWÓJ kolor.
		 *
		 * Karty przychodzą z przezroczystym nagłówkiem, więc napisy kolumn
		 * leżały wprost na ekranie okienka i nic ich nie trzymało razem.
		 * Sprawdzane trzy rzeczy naraz: że pasek jest w ogóle pomalowany, że
		 * nie jest tym samym kolorem co karty pod nim (bo wtedy pierwsza karta
		 * zlewa się z paskiem) i że nie jest ekranem okienka (czyli że próbnik
		 * „Tło nagłówka” naprawdę dotarł na stronę, a nie został zjedzony przez
		 * szablon).
		 */
		pasek: ( () => {
			const th = document.querySelector( '.lst-mz-stol .lstab-table thead th' );
			const td = document.querySelector( '.lst-mz-stol .lstab-table tbody td' );
			const ek = document.querySelector( '.lst-mz-stol .lstab-scroll' );

			return {
				glowa: getComputedStyle( th ).backgroundColor,
				karta: getComputedStyle( td ).backgroundColor,
				ekran: getComputedStyle( ek ).backgroundColor,
			};
		} )(),
		podniesiony: getComputedStyle( document.querySelector( '.lst-mz-okno.jest-stolem' ) ).boxShadow,
	} ) );
	ok( 'trzy zrzuty z kokpitu, wszystkie wczytane', zrzuty.ile === 3 && zrzuty.wczytane === 3, zrzuty.puste.join( ', ' ) || '3 z 3' );
	ok( 'obie tabele są na szablonie Karty, przemalowanym na kolory strony',
		zrzuty.karty === 2, `kart ${ zrzuty.karty }` );
	ok( 'i wiersz nie ma własnego tła, bo inaczej karta straciłaby rogi',
		/rgba\(0, 0, 0, 0\)|transparent/.test( zrzuty.wierszBezTla ), zrzuty.wierszBezTla );
	ok( 'tabela jest z tej strony: ciemna jak okienka, nie jaśniejsza od strony',
		zrzuty.jasnosc.papier < 0.05 && Math.abs( zrzuty.jasnosc.papier - zrzuty.jasnosc.okno ) < 0.01,
		`tabela ${ zrzuty.jasnosc.papier }, okno ${ zrzuty.jasnosc.okno }, strona ${ zrzuty.jasnosc.strona }` );
	ok( 'pasek nagłówków ma własny kolor, inny niż karty i niż ekran pod nimi',
		! /rgba\(0, 0, 0, 0\)|transparent/.test( zrzuty.pasek.glowa )
			&& zrzuty.pasek.glowa !== zrzuty.pasek.karta
			&& zrzuty.pasek.glowa !== zrzuty.pasek.ekran,
		`pasek ${ zrzuty.pasek.glowa }, karta ${ zrzuty.pasek.karta }, ekran ${ zrzuty.pasek.ekran }` );
	ok( 'i naprawdę leży na stronie, a nie jest w nią wpuszczona',
		/rgba?\(/.test( zrzuty.podniesiony ) && 'none' !== zrzuty.podniesiony, zrzuty.podniesiony );

	const ruch = await p.evaluate( () => ( {
		niewidoczne: [ ...document.querySelectorAll( '.lst-mz [class*="lst-mz-"], .lst-mz [class*="lst-ar-"]' ) ].filter( ( e ) => getComputedStyle( e ).opacity === '0' ).length,
		animowane: [ ...document.querySelectorAll( '.lst-ar-komorka' ) ].map( ( e ) => getComputedStyle( e ).animationName ),
		kreska: getComputedStyle( document.querySelector( '.lst-ar-strzalka' ) ).animationName,
		puls: getComputedStyle( document.querySelector( '.lst-mz-puls' ) ).animationName,
		ile: getComputedStyle( document.querySelector( '.lst-mz-puls' ) ).animationIterationCount,
	} ) );
	ok( 'jest ruch, a mimo to nic nie jest schowane', ruch.niewidoczne === 0 && ruch.animowane.every( ( x ) => x === 'lst-ar-wejscie' ) && ruch.kreska === 'lst-ar-strzalka' && ruch.puls === 'lst-mz-puls',
		`schowanych ${ ruch.niewidoczne }, wejście ${ ruch.animowane[ 0 ] }, strzałka ${ ruch.kreska }, puls ${ ruch.puls }` );
	// Nic nie może migać bez końca — puls ma odliczoną liczbę powtórzeń.
	ok( 'żadna animacja nie chodzi w kółko bez końca', ruch.ile !== 'infinite', `powtórzeń pulsu: ${ ruch.ile }` );

	/*
	 * Arkusz z krokami przyjeżdża z landing/arkusz/ razem ze swoim CSS-em.
	 * Podświetlenia siedzą w nim na „:has()” i na „!important”, a podstrona
	 * ma własne utwardzenie na wrogie motywy, też z „!important” — czyli
	 * dokładnie ta sytuacja, w której jedna reguła cicho zjada drugą. Mierzone
	 * jest to, co widać po najechaniu, a nie to, co napisane w arkuszu.
	 */
	await p.hover( '.lst-ar-komorka:nth-child( 3 )' );
	await p.waitForTimeout( 160 );

	const siatka = await p.evaluate( () => {
		const mieta = ( c ) => /95,\s*227,\s*207/.test( c );
		const litery = [ ...document.querySelectorAll( '.lst-ar-litery > span' ) ].slice( 1 );

		return {
			zapalone: litery.map( ( e ) => mieta( getComputedStyle( e ).color ) ),
			tlo: mieta( getComputedStyle( litery[ 1 ] ).backgroundColor ),
			adres: getComputedStyle( document.querySelector( '.lst-ar-rog' ), '::after' ).content.replace( /"/g, '' ),
			etapy: [ ...document.querySelectorAll( '.lst-ar-etap-nazwa' ) ].map( ( e ) => mieta( getComputedStyle( e ).color ) ),
		};
	} );

	await p.mouse.move( 5, 5 );
	await p.waitForTimeout( 120 );

	ok( 'arkusz reaguje jak arkusz także wewnątrz podstrony',
		siatka.zapalone.every( ( x, i ) => x === ( 1 === i ) ) && siatka.tlo && 'B1' === siatka.adres
			&& siatka.etapy.every( ( x, i ) => x === ( 1 === i ) ),
		`litery ${ siatka.zapalone.map( ( x ) => x ? '1' : '0' ).join( '' ) }, tło ${ siatka.tlo }, pole nazwy ${ siatka.adres }, etapy ${ siatka.etapy.map( ( x ) => x ? '1' : '0' ).join( '' ) }` );

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

	/*
	 * Mierzone zachowaniem, nie czytaniem reguł: strona jest przewijana na trzy
	 * wysokości i za każdym razem nic w module nie ma prawa być NIECZYTELNE.
	 *
	 * Próg, nie zero. Element przed swoim zakresem siedzi w klatce startowej,
	 * więc „jeszcze nie wszedł” to stan, w jakim ktoś może go zastać: na
	 * zrzucie całej strony, na wydruku, w czytniku, który nie przewija. Dawniej
	 * było tu „musi mieć 1”, co znaczyło, że wjazd nie mógł ruszać
	 * przezroczystości w ogóle i sekcje wchodziły sztywno. Klatka startowa ma
	 * dziś 0.4: rozjaśnienie widać w ruchu, a w spoczynku to przygaszony
	 * akapit, który nadal się czyta. Pilnowane jest właśnie to 0.4 — zejście
	 * niżej, a tym bardziej do zera, jest usterką.
	 */
	const PROG = 0.4;
	const znikajace = [];
	let osiUzyte = 0;

	for ( const gdzie of [ 0, 0.5, 1 ] ) {
		await p.evaluate( ( u ) => window.scrollTo( 0, ( document.body.scrollHeight - innerHeight ) * u ), gdzie );
		await p.waitForTimeout( 120 );

		const stan = await p.evaluate( ( prog ) => {
			const schowane = [ ...document.querySelectorAll( '.lst-mz [class*="lst-mz-"], .lst-mz [class*="lst-ar-"], .lst-mz .lstab-row' ) ]
				.filter( ( e ) => Number( getComputedStyle( e ).opacity ) < prog - 0.001 )
				.map( ( e ) => e.className.toString().slice( 0, 40 ) + ' ' + getComputedStyle( e ).opacity );
			// Uzbrojone, czyli skrypt ruszyl i stan startowy jest na miejscu.
			const uzbrojone = document.querySelectorAll( '.lst-mz.lst-mz-ruch' ).length;
			const dojechaly = document.querySelectorAll( '.lst-mz .jest-tu' ).length;

			return { schowane, uzbrojone, dojechaly };
		}, PROG );

		osiUzyte = Math.max( osiUzyte, stan.uzbrojone && stan.dojechaly ? stan.dojechaly : 0 );
		znikajace.push( ...stan.schowane );
	}

	await p.evaluate( () => window.scrollTo( 0, 0 ) );
	await p.waitForTimeout( 120 );

	/*
	 * Wjazd uzbraja skrypt, nie arkusz.
	 *
	 * Przedtem siedzial on na `animation-timeline: view()`, czyli na funkcji,
	 * ktorej nie ma ani Firefox, ani Safari starsze niz 26: tam nie dzialo sie
	 * nic. Obserwator widocznosci dziala wszedzie, wiec sprawdzane jest to, co
	 * naprawde widac — ile rzeczy dojechało po przewinieciu strony.
	 */
	ok( 'wjazd naprawdę się odpala', osiUzyte > 0, `rzeczy, które dojechały: ${ osiUzyte }` );
	ok( 'i na żadnej wysokości strony nic nie schodzi poniżej czytelności',
		0 === znikajace.length, znikajace.slice( 0, 4 ).join( ' | ' ) || `nic poniżej ${ PROG }` );

	const rama = await p.evaluate( () => {
		const k = document.querySelector( '.lst-mz-rama' ).getBoundingClientRect();
		return { lewo: Math.round( k.left ), prawo: Math.round( innerWidth - k.right ) };
	} );
	ok( 'strona jest wyśrodkowana — luz z lewej równa się luzowi z prawej',
		Math.abs( rama.lewo - rama.prawo ) <= 1 && rama.lewo > 0, `z lewej ${ rama.lewo } px, z prawej ${ rama.prawo } px` );

	const k = await kontrast( p );
	ok( 'najsłabszy napis ma co najmniej 4,5 : 1', k.r >= 4.5, `${ k.r } : 1 — ${ k.co }` );

	await p.locator( '.lst-mz' ).screenshot( { path: path.join( TU, 'mozliwosci-1500.png' ) } );
	await p.locator( '.lst-mz-legenda' ).screenshot( { path: path.join( TU, 'mozliwosci-legenda.png' ) } );
	await p.locator( '.lst-mz-stol' ).screenshot( { path: path.join( TU, 'mozliwosci-stol.png' ) } );
	await p.locator( '.lst-mz-para' ).first().screenshot( { path: path.join( TU, 'mozliwosci-ekran.png' ) } );
	await p.locator( '.lst-mz-pas' ).screenshot( { path: path.join( TU, 'mozliwosci-telefon.png' ) } );
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
		krokow: document.querySelectorAll( '.lst-ar-komorka' ).length,
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
			krok: [ s( '.lst-mz-para-tresc', 'borderTopWidth' ), s( '.lst-ar-komorka', 'backgroundColor' ) ],
			numer: s( '.lst-ar-adres', 'fontFamily' ).split( ',' )[ 0 ].replace( /"/g, '' ),
			znak: s( '.lst-mz-znak', 'textTransform' ),
			// Tabela broni się własnym arkuszem, a przed motywem malującym
			// każdą komórkę „!important” nie obroni się żadna. Sprawdzamy, że
			// dalej działa i mieści się w stronie.
			wierszy: document.querySelectorAll( '.lst-mz-stol tbody tr.lstab-row' ).length,
			pigulek: document.querySelectorAll( '.lst-mz-stol .lstabp-pill' ).length,
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
			/*
			 * Trzy pudła nośne tabeli bez cudzej ramki.
			 *
			 * Panel przewijania to zwykły <div>, więc motyw malujący ramkę
			 * każdemu divowi obrysowywał całą tabelę kreską, której nie ma
			 * w szablonie — u klienta białą. Ten wrogi motyw robi dokładnie
			 * to samo, a sprawdzenie tego nie widziało: patrzyło tylko, czy
			 * wierszy jest dziesięć.
			 */
			rama: [ '.lst-mz-stol .lstab', '.lst-mz-stol .lstab-container', '.lst-mz-stol .lstab-scroll',
				'.lst-mz-stol .lstab-table', '.lst-mz-stol .lstab-table > thead', '.lst-mz-stol .lstab-table > tbody' ]
				.map( ( sel ) => {
					const e = document.querySelector( sel );
					if ( ! e ) { return sel + ' brak'; }
					const g = getComputedStyle( e );

					return sel.split( ' ' ).slice( 1 ).join( ' ' ) + ' ' + g.borderTopWidth + '/' + g.outlineWidth;
				} ),
		};
	} );
	ok( 'motyw nie przejmuje modułu',
		r.opis[ 0 ] === 'IBM Plex Sans' && r.opis[ 1 ] === 'left' && r.opis[ 2 ] === 'none' && r.opis[ 3 ] === '0px' && r.opis[ 4 ] === '18px',
		r.opis.join( ' / ' ) );
	ok( 'kafelki i etykiety zostają sobą', r.krok[ 0 ] === '1px' && r.numer === 'IBM Plex Mono' && r.znak === 'uppercase',
		`ramka ${ r.krok[ 0 ] }, adres ${ r.numer }, znak ${ r.znak }` );
	ok( 'i nie dostaje od motywu ramki, której nie ma w szablonie',
		r.rama.every( ( x ) => /0px\/0px$/.test( x ) ), r.rama.join( ', ' ) );
	ok( 'tabela dalej jest cała i mieści się w stronie', r.wierszy === 10 && r.pigulek === 10 && r.poziom === 0,
		`${ r.wierszy } wierszy, ${ r.pigulek } pigułek, suwak ${ r.poziom }` );
	await c.close();
}

console.log( '\nbez JavaScriptu' );
{
	const { p, c } = await otworz( strona( modul, { bezJs: true } ) );
	const r = await p.evaluate( () => ( {
		/*
		 * Czytelne, nie „pełne jedynki”. Bez skryptu strona nadal ma wjazdy na
		 * osi widoku, bo one są z CSS-a, więc rzecz, do której jeszcze nikt nie
		 * dojechał, jest przygaszona i tak ma być. Usterką jest dopiero zejście
		 * poniżej progu, czyli coś, czego nie da się przeczytać.
		 */
		czytelnych: [ ...document.querySelectorAll( '.lst-ar-komorka, .lst-mz-pozycja, .lst-ar-etap' ) ].filter( ( x ) => Number( getComputedStyle( x ).opacity ) >= 0.4 - 0.001 ).length,
		wszystkich: document.querySelectorAll( '.lst-ar-komorka, .lst-mz-pozycja, .lst-ar-etap' ).length,
		najciemniejszy: Math.min( ...[ ...document.querySelectorAll( '.lst-ar-komorka, .lst-mz-pozycja, .lst-ar-etap' ) ].map( ( x ) => Number( getComputedStyle( x ).opacity ) ) ),
		wierszy: [ ...document.querySelectorAll( '.lst-mz-stol tbody tr.lstab-row' ) ].filter( ( x ) => ! x.hidden ).length,
	} ) );
	ok( 'bez skryptu wszystko czytelne', r.czytelnych === r.wszystkich && r.wierszy === 10,
		`${ r.czytelnych } z ${ r.wszystkich }, najciemniejszy ${ r.najciemniejszy }, ${ r.wierszy } wierszy` );
	await c.close();
}

console.log( '\nmniej ruchu' );
{
	const { p, c } = await otworz( strona( modul ), { ruch: false } );
	const r = await p.evaluate( () => ( {
		widocznych: [ ...document.querySelectorAll( '.lst-ar-komorka, .lst-mz-pozycja, .lst-ar-etap' ) ].filter( ( x ) => getComputedStyle( x ).opacity === '1' ).length,
		// Liczone, nie wpisane: dołożona pozycja legendy nie jest usterką.
		wszystkich: document.querySelectorAll( '.lst-ar-komorka, .lst-mz-pozycja, .lst-ar-etap' ).length,
		animacje: [ ...document.querySelectorAll( '.lst-ar-komorka' ) ].map( ( x ) => getComputedStyle( x ).animationName ),
		kreska: getComputedStyle( document.querySelector( '.lst-ar-strzalka' ) ).animationName,
		puls: getComputedStyle( document.querySelector( '.lst-mz-puls' ) ).animationName,
	} ) );
	ok( 'przy prefers-reduced-motion nic się nie rusza, a wszystko widać',
		r.widocznych === r.wszystkich && r.animacje.every( ( x ) => x === 'none' ) && r.kreska === 'none' && r.puls === 'none',
		`widocznych ${ r.widocznych } z ${ r.wszystkich }, wejście ${ r.animacje[ 0 ] }, kreska ${ r.kreska }, puls ${ r.puls }` );
	await c.close();
}

console.log( '\ntelefon' );
{
	const { p, c } = await otworz( strona( modul ), { width: 390, height: 900 } );
	const r = await p.evaluate( () => ( {
		kolumny: getComputedStyle( document.querySelector( '.lst-ar-wiersz' ) ).gridTemplateColumns.split( ' ' ).length,
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
	await p.locator( '.lst-mz' ).screenshot( { path: path.join( TU, 'mozliwosci-390.png' ) } );
	await c.close();
}

/*
 * Dwie sekcje wychodzą też osobno, do wklejenia gdzie indziej bez reszty
 * podstrony. Sprawdzane jako samodzielne strony, bo tak będą wklejone: czy
 * niosą ze sobą swój CSS, czy nic nie zostało przezroczyste i czy nie wloką
 * za sobą arkusza i skryptu wtyczki, których nie mają po co nieść.
 */
/*
 * Najechanie na kafelek legendy ma byc przejsciem, a nie skokiem.
 *
 * Animacja wjazdu chodzi na osi widoku i ma `both`, wiec po przejechaniu
 * sekcji dalej trzyma wlasciwosc, ktora ruszala. Kiedy ruszala `transform`,
 * zabierala go najechaniu i karta podskakiwala natychmiast: przejscie nie
 * mialo czego animowac. Mierzone jest to, co widac: ile kafelek przejechal
 * tuz po najechaniu i ile po zakonczeniu.
 */
/*
 * Kafelki legendy maja wchodzic PO KOLEI.
 *
 * Na swiezej stronie, bo `otworz` przewija ja na dol i z powrotem: po tym
 * wszystko juz dojechalo i nie ma czego mierzyc. Tu strona jest otwierana
 * sama, przewijana raz do legendy i czytana w trakcie wjazdu.
 */
console.log( '\nukos' );
{
	const c = await b.newContext( { viewport: { width: 1500, height: 1100 } } );
	const p = await c.newPage();
	await p.goto( 'file://' + TU + '/' );
	await p.setContent( strona( modul ), { waitUntil: 'load' } );
	await p.waitForTimeout( 200 );
	const gora = await p.evaluate( () => {
		const k = document.querySelector( '.lst-mz-legenda' ).getBoundingClientRect();

		return Math.round( k.top + scrollY - innerHeight * 0.55 );
	} );
	await p.evaluate( ( y ) => window.scrollTo( 0, y ), gora );

	/*
	 * Próbkowane przez całe wejście, nie w jednej chwili. Pierwsza kolumna
	 * rusza od razu, trzecia ma 120 ms odstępu, więc różnica rośnie i znika;
	 * trafienie w jedną klatkę zależałoby od tego, jak szybko tego dnia chodzi
	 * maszyna. Brana jest największa różnica, jaka w ogóle wystąpiła.
	 */
	let ukos = [ 0.4, 0.4 ];
	for ( let i = 0; i < 16; i++ ) {
		await p.waitForTimeout( 50 );
		const teraz = await p.evaluate( () => [ 0, 2 ].map( ( k ) => {
			const e = document.querySelectorAll( '.lst-mz-legenda .lst-mz-pozycja' )[ k ];

			return Number( getComputedStyle( e ).opacity );
		} ) );
		if ( teraz[ 0 ] - teraz[ 1 ] > ukos[ 0 ] - ukos[ 1 ] ) { ukos = teraz; }
	}
	/*
	 * Odstep liczony W WIERSZU, nie przez cala siatke.
	 *
	 * Przy plaskim numerze ostatni kafelek czekal 360 ms: siedzial przygaszony
	 * na srodku ekranu i dopiero potem skakal do pelni. Sprawdzane jest wiec,
	 * ze numery wracaja do zera na poczatku kazdego wiersza, a nie rosna do
	 * konca.
	 */
	const odstepy = await p.evaluate( () => [ ...document.querySelectorAll( '.lst-mz-legenda .lst-mz-pozycja' ) ]
		.map( ( e ) => Number( e.style.getPropertyValue( '--mz-kolej' ) || 0 ) ) );
	ok( 'odstęp wraca do zera w każdym wierszu, więc nikt nie czeka na dole',
		odstepy.length > 3 && Math.max.apply( null, odstepy ) <= 3
			&& odstepy.filter( ( n ) => 0 === n ).length > 1,
		`[ ${ odstepy.join( ', ' ) } ]` );

	ok( 'kafelki legendy wchodzą po kolei, a nie wszystkie naraz',
		ukos[ 0 ] - ukos[ 1 ] > 0.05,
		`pierwsza kolumna ${ ukos[ 0 ].toFixed( 2 ) }, trzecia ${ ukos[ 1 ].toFixed( 2 ) }` );
	await c.close();
}

console.log( '\nnajechanie' );
{
	const { p, c } = await otworz( strona( modul ) );
	const karta = await p.$( '.lst-mz-legenda .lst-mz-pozycja' );
	await karta.scrollIntoViewIfNeeded();
	await p.waitForTimeout( 400 );
	const ile = ( e ) => e.evaluate( ( x ) => {
		const m = new DOMMatrixReadOnly( getComputedStyle( x ).transform );

		return Math.round( m.m42 * 100 ) / 100;
	} );
	const spoczynek = await ile( karta );
	await karta.hover();
	const zaraz = await ile( karta );
	await p.waitForTimeout( 400 );
	const koniec = await ile( karta );
	ok( 'kafelek podnosi się płynnie, a nie skacze',
		0 === spoczynek && koniec < -1 && zaraz > koniec * 0.9,
		`spoczynek ${ spoczynek }, zaraz po ${ zaraz }, koniec ${ koniec }` );

	const kreska = await p.evaluate( () => {
		const g = getComputedStyle( document.querySelector( '.lst-mz-naglowek' ), '::after' );

		return { wysokosc: g.height, obraz: g.backgroundImage };
	} );
	// Ta sama kreska co na stronie glownej: 2 px i mieta, a nie wlos.
	ok( 'kreska przy tytule jest gruba i zielona, jak na stronie głównej',
		'2px' === kreska.wysokosc && /95,\s*227,\s*207/.test( kreska.obraz ),
		`${ kreska.wysokosc }, ${ kreska.obraz.slice( 0, 60 ) }` );
	await c.close();
}

/*
 * Przykład shortcode'u ma się CZYTAĆ, a nie wykonywać.
 *
 * Na żywej stronie wykonał się i zamiast przykładu stanął komunikat wtyczki
 * „Nie wybrano jeszcze arkusza.", po polsku na angielskiej stronie. Same
 * encje nie wystarczają, bo Divi zamienia je z powrotem na nawiasy; nazwa nie
 * może się z nawiasem stykać w ogóle. Zmiana numeru nic by tu nie dała, bo
 * wykonywała się nazwa.
 */
console.log( '\nprzykład shortcode\'u' );
{
	const { p, c } = await otworz( strona( modul ) );
	const widac = await p.evaluate( () => {
		const e = document.querySelector( '.lst-mz-kod .lst-mz-mono' );

		return e ? e.textContent.trim() : '';
	} );
	const zbitki = [ '[sheet_table', '&#91;sheet_table', '&#x5B;sheet_table' ].filter( ( z ) => modul.includes( z ) );
	ok( 'przykład czyta się jak shortcode, a nie da się go wykonać',
		'[sheet_table id="1"]' === widac && 0 === zbitki.length,
		`na ekranie ${ widac }, zbitek w pliku ${ zbitki.length }` );

	/*
	 * Obudowa ma wyglądać jak telefon. Poznaje się go po sylwetce, nie po
	 * ozdobach, więc mierzone jest to: wyraźnie wyższa niż szersza, mocno
	 * zaokrąglone rogi, wyspa u góry i kreska gestu u dołu.
	 */
	const fon = await p.evaluate( () => {
		const rama = document.querySelector( '.lst-mz-telefon-rama' );
		const ekran = rama.querySelector( '.lst-mz-telefon' );
		const k = rama.getBoundingClientRect();
		const e = ekran.getBoundingClientRect();

		return {
			proporcja: Math.round( ( k.height / k.width ) * 100 ) / 100,
			promien: parseFloat( getComputedStyle( rama ).borderTopLeftRadius ),
			// Ramka ekranu równej szerokości z czterech stron: nierówna od razu
			// zdradza, że to prostokąt, a nie aparat.
			ramkaRowna: Math.round( e.left - k.left ) === Math.round( k.right - e.right ),
			// Metal: bok musi nieść gradient, inaczej jest płaską plamą.
			metal: /gradient/.test( getComputedStyle( rama ).backgroundImage ),
			szklo: /gradient/.test( getComputedStyle( rama.querySelector( '.lst-mz-telefon-blysk' ) || rama ).backgroundImage ),
			oko: !! rama.querySelector( '.lst-mz-telefon-wyspa .lst-mz-telefon-oko' ),
			godzina: ( rama.querySelector( '.lst-mz-telefon-godzina' ) || {} ).textContent || '',
			ikonekStanu: rama.querySelectorAll( '.lst-mz-telefon-pasek .lst-mz-ikona' ).length,
			kreska: !! rama.querySelector( '.lst-mz-telefon-kreska' ),
			guzikow: rama.querySelectorAll( '.lst-mz-telefon-guzik' ).length,
		};
	} );
	ok( 'to wygląda jak telefon: sylwetka, metal, szkło, wyspa z okiem, pasek stanu',
		fon.proporcja > 1.5 && fon.promien >= 44 && fon.ramkaRowna && fon.metal && fon.szklo
			&& fon.oko && /\d/.test( fon.godzina ) && 3 === fon.ikonekStanu
			&& fon.kreska && 4 === fon.guzikow,
		`proporcja ${ fon.proporcja }, róg ${ fon.promien }px, ramka równa ${ fon.ramkaRowna }, metal ${ fon.metal }, `
		+ `szkło ${ fon.szklo }, oko ${ fon.oko }, zegar ${ fon.godzina }, ikonek ${ fon.ikonekStanu }, guzików ${ fon.guzikow }` );
	await c.close();
}

/*
 * Najechanie na aparat jak w bibliotece Steama: odchyla się w stronę kursora,
 * blask idzie za wskaźnikiem, a po wyjściu wszystko wraca na zero. Mierzony
 * jest kierunek nachylenia z dwóch przeciwnych rogów, bo efekt, który
 * przechyla aparat zawsze w tę samą stronę, wygląda dokładnie tak samo
 * zepsuty, jak brak efektu.
 */
console.log( '\nnachylenie aparatu' );
{
	const { p, c } = await otworz( strona( modul ) );
	const fon = await p.$( '.lst-mz-telefon-rama' );
	await fon.scrollIntoViewIfNeeded();
	await p.waitForTimeout( 300 );
	const r = await fon.boundingBox();
	const obrotY = () => fon.evaluate( ( e ) => {
		const m = new DOMMatrixReadOnly( getComputedStyle( e ).transform );

		// m13 to składowa obrotu wokół osi pionowej: znak mówi, w którą stronę.
		return Math.round( m.m13 * 1000 ) / 1000;
	} );

	await p.mouse.move( r.x + r.width * 0.9, r.y + r.height * 0.5, { steps: 6 } );
	await p.waitForTimeout( 350 );
	const prawo = await obrotY();
	const blask = await fon.evaluate( ( e ) => getComputedStyle( e.querySelector( '.lst-mz-telefon-blysk' ), '::after' ).opacity );

	await p.mouse.move( r.x + r.width * 0.1, r.y + r.height * 0.5, { steps: 6 } );
	await p.waitForTimeout( 350 );
	const lewo = await obrotY();


	/*
	 * Grubość: warstwy korpusu muszą naprawdę stać w przestrzeni, za
	 * przodem. Gdyby coś w obudowie spłaszczyło przestrzeń (overflow, filtr,
	 * maska na samej ramie), warstwy ległyby w płaszczyźnie przodu i bok by
	 * zniknął, a nic by tego nie zgłosiło.
	 */
	const bryla = await fon.evaluate( ( e ) => {
		const w = e.querySelectorAll( '.lst-mz-telefon-warstwa' );
		const ostatnia = w[ w.length - 1 ];

		return {
			przestrzen: getComputedStyle( e ).transformStyle,
			warstw: w.length,
			glebia: ostatnia ? Math.round( new DOMMatrixReadOnly( getComputedStyle( ostatnia ).transform ).m43 ) : 0,
		};
	} );
	// Powrót jest celowo wolniejszy niż wejście: aparat się odkłada.
	await p.mouse.move( 5, 5, { steps: 3 } );
	await p.waitForTimeout( 1600 );
	const potem = await obrotY();

	/*
	 * W spoczynku bez sceny 3D. Scena z warstwami korpusu, zostawiona na
	 * stałe, rozsypywała się w Chrome przy przewijaniu: dolny kafel wychodził
	 * jasnym prostokątem z ostrymi rogami, szerszym niż aparat.
	 */
	const plasko = await fon.evaluate( ( e ) => ( {
		przestrzen: getComputedStyle( e ).transformStyle,
		ksztalt: getComputedStyle( e ).transform,
		widacWarstw: [ ...e.querySelectorAll( '.lst-mz-telefon-warstwa, .lst-mz-telefon-guzik' ) ].filter( ( w ) => 'none' !== getComputedStyle( w ).display ).length,
	} ) );
	ok( 'odłożony telefon jest płaski: bez sceny 3D, korpus schowany',
		'flat' === plasko.przestrzen && 'none' === plasko.ksztalt && 0 === plasko.widacWarstw,
		`${ plasko.przestrzen }, ${ plasko.ksztalt }, widocznych warstw ${ plasko.widacWarstw }` );

	ok( 'telefon ma grubość: korpus stoi za ekranem w przestrzeni',
		'preserve-3d' === bryla.przestrzen && 12 === bryla.warstw && bryla.glebia <= -36,
		`${ bryla.przestrzen }, warstw ${ bryla.warstw }, najgłębsza ${ bryla.glebia }px` );

	ok( 'aparat odchyla się w stronę kursora, świeci blaskiem i wraca na zero',
		prawo * lewo < 0 && Math.abs( prawo ) > 0.03 && '1' === blask && 0 === potem,
		`z prawej ${ prawo }, z lewej ${ lewo }, blask ${ blask }, po wyjściu ${ potem }` );

	// Odcisk: kod modułu i arkusz z tej samej paczki muszą się zgadzać.
	const odcisk = await p.evaluate( () => {
		const k = document.querySelector( '.lst-mz' );

		return [ k.getAttribute( 'data-odcisk' ), getComputedStyle( k ).getPropertyValue( '--mz-odcisk' ).replace( /["\s]/g, '' ) ];
	} );
	ok( 'kod i arkusz noszą ten sam odcisk', !! odcisk[ 0 ] && odcisk[ 0 ] === odcisk[ 1 ], odcisk.join( ' / ' ) );
	await c.close();
}

/*
 * Starszy arkusz na stronie. Na żywej stronie stara kopia z pola „Własny CSS”
 * wczytywała się PO nowej i przykrywała ją: aparat 390 × 446 zamiast
 * 414 × 792, wyspa skurczona do kropki, godzina schowana za rogiem. Stary
 * arkusz nie zna odcisku, więc tu jest udawany: ten sam arkusz bez reguły
 * z odciskiem, dołożony za nowym. Skrypt ma to zauważyć, a bez niego milczeć.
 */
console.log( '\nstarsza kopia arkusza' );
{
	const nowy = czytaj( 'MOZLIWOSCI-css.css' );
	const stary = nowy.replace( /\.lst-mz \{ --mz-odcisk: "[^"]*"; \}/, '' );
	const glosy = [];
	for ( const zeStarym of [ false, true ] ) {
		const c = await b.newContext( { viewport: { width: 1500, height: 1100 } } );
		const p = await c.newPage();
		const ostrz = [];
		p.on( 'console', ( m ) => 'warning' === m.type() && /starsza wersja/.test( m.text() ) && ostrz.push( m.text() ) );
		await p.goto( 'file://' + TU + '/' );
		await p.setContent( '<!doctype html><html><head><meta charset="utf-8"><style>' + nowy + '</style>'
			+ ( zeStarym ? '<style>' + stary + '</style>' : '' ) + '</head><body>'
			+ czytaj( 'TELEFON-kod.html' ) + '<script>' + czytaj( 'MOZLIWOSCI-js.js' ) + '</script></body></html>', { waitUntil: 'load' } );
		await p.waitForTimeout( 200 );
		glosy.push( ostrz.length );
		await c.close();
	}
	ok( 'starszy arkusz na stronie jest zgłaszany, a sam nowy nie',
		0 === glosy[ 0 ] && 1 === glosy[ 1 ], `sam nowy: ${ glosy[ 0 ] } ostrzeżeń, z kopią bez odcisku: ${ glosy[ 1 ] }` );
}

/*
 * Skrypt przed modułem. Divi wkleja kod z zakładki Integracja tam, gdzie mu
 * wygodnie — do <head> albo na początek <body> — czyli zanim na stronie
 * stoi jakikolwiek moduł. Poprzednia wersja szukała modułów od razu, nic nie
 * znajdowała i milczała: bez wjazdu, bez nachylenia, bez jednego błędu.
 */
console.log( '\nskrypt przed modułem' );
{
	const c = await b.newContext( { viewport: { width: 1500, height: 1100 } } );
	const p = await c.newPage();
	await p.goto( 'file://' + TU + '/' );
	await p.setContent( '<!doctype html><html><head><meta charset="utf-8">' + czytaj( 'INTEGRACJA-head.html' )
		+ czytaj( 'INTEGRACJA-body.html' ) + '</head><body>' + czytaj( 'TELEFON-kod.html' ) + '</body></html>', { waitUntil: 'load' } );
	const fon = await p.$( '.lst-mz-telefon-rama' );
	await fon.scrollIntoViewIfNeeded();
	await p.waitForTimeout( 400 );
	const r = await fon.boundingBox();
	await p.mouse.move( r.x + r.width * 0.9, r.y + r.height * 0.5, { steps: 5 } );
	await p.waitForTimeout( 300 );
	const s = await p.evaluate( () => ( {
		uzbrojony: !! document.querySelector( '.lst-mz.lst-mz-ruch' ),
		nachylony: document.querySelector( '.lst-mz-telefon-rama' ).classList.contains( 'jest-nad' ),
	} ) );
	ok( 'skrypt w <head> i tak uzbraja wjazd i nachyla telefon', s.uzbrojony && s.nachylony,
		`wjazd ${ s.uzbrojony }, nachylenie ${ s.nachylony }` );
	await c.close();
}

/*
 * Podstrona złożona tak, jak ma stać: każda sekcja jako sam kod, jeden arkusz
 * w <head>, jeden skrypt. Sekcje wklejane w całości niosą własny <style>
 * z kopią arkusza z dnia, w którym je wklejono, i taka kopia przykrywa nowy
 * arkusz w każdej innej sekcji: tak telefon zrobił się na żywej stronie niski
 * i płaski, choć w „Własnym CSS” niczego starego nie było.
 */
console.log( '\npodstrona z samych kodów sekcji' );
{
	const sekcje = [ 'STOL-kod.html', 'LEGENDA-kod.html', 'SKAD-kod-gotowe.html', 'TELEFON-kod.html', 'LISTY-kod.html' ];
	const c = await b.newContext( { viewport: { width: 1920, height: 1100 } } );
	const p = await c.newPage();
	const ostrz = [];
	p.on( 'console', ( m ) => 'warning' === m.type() && /lst-mz/.test( m.text() ) && ostrz.push( m.text() ) );
	await p.goto( 'file://' + TU + '/' );
	await p.setContent( '<!doctype html><html><head><meta charset="utf-8">' + czytaj( 'INTEGRACJA-head.html' ) + '</head><body>'
		+ sekcje.map( czytaj ).join( '' ) + czytaj( 'INTEGRACJA-body.html' ) + '</body></html>', { waitUntil: 'load' } );
	await p.waitForTimeout( 300 );
	const s = await p.evaluate( () => {
		const k = document.querySelector( '.lst-mz-telefon-rama' ).getBoundingClientRect();

		return {
			kodow: [ ...document.querySelectorAll( '.lst-mz' ) ].length,
			stylow: document.querySelectorAll( 'body style' ).length,
			wysokosc: Math.round( k.height ),
			wyspa: getComputedStyle( document.querySelector( '.lst-mz-telefon-wyspa' ) ).width,
		};
	} );
	ok( 'pięć sekcji z samych kodów: żadnego <style> w treści, telefon pełny, konsola cicha',
		5 === s.kodow && 0 === s.stylow && s.wysokosc > 700 && '92px' === s.wyspa && 0 === ostrz.length,
		`sekcji ${ s.kodow }, <style> w treści ${ s.stylow }, telefon ${ s.wysokosc }px, wyspa ${ s.wyspa }, ostrzeżeń ${ ostrz.length }` );
	await c.close();
}

/*
 * Telefon jako JEDEN moduł, bez Integracji i bez „Własnego CSS”, wśród sekcji
 * wklejonych kiedyś w całości. Ich stare kopie arkusza wczytują się później
 * i mają te same selektory; tu są udawane regułami, które robiły na żywej
 * stronie dokładnie to, co było widać: zerowały dopełnienia i szerokości,
 * skracały ekran i malowały obudowę na płasko, także z !important. Sekcja
 * z identyfikatorem ma z nimi wygrać bez względu na kolejność.
 */
console.log( '\ntelefon jako jeden moduł' );
{
	const stare = '<style>.lst-mz [class*="lst-mz-"]{padding:0;width:auto;background:none;border-radius:0}'
		+ '.lst-mz .lst-mz-telefon{height:26rem}'
		+ '.lst-mz .lst-mz-telefon-rama{background-color:#123 !important;border-radius:26px !important}</style>';
	const c = await b.newContext( { viewport: { width: 1920, height: 1100 } } );
	const p = await c.newPage();
	await p.goto( 'file://' + TU + '/' );
	await p.setContent( '<!doctype html><html><head><meta charset="utf-8"></head><body>'
		+ czytaj( 'TELEFON-sam.html' ) + stare + '</body></html>', { waitUntil: 'load' } );
	const fon = await p.$( '#lst-mz-fon .lst-mz-telefon-rama' );
	await fon.scrollIntoViewIfNeeded();
	await p.waitForTimeout( 400 );
	const r = await fon.boundingBox();
	await p.mouse.move( r.x + r.width * 0.9, r.y + r.height * 0.5, { steps: 5 } );
	await p.waitForTimeout( 300 );
	const s = await fon.evaluate( ( e ) => ( {
		wysokosc: e.offsetHeight,
		rog: getComputedStyle( e ).borderTopLeftRadius,
		metal: /gradient/.test( getComputedStyle( e ).backgroundImage ),
		wyspa: getComputedStyle( e.querySelector( '.lst-mz-telefon-wyspa' ) ).width,
		pasek: getComputedStyle( e.querySelector( '.lst-mz-telefon-pasek' ) ).paddingLeft,
		przechyla: e.classList.contains( 'jest-nad' ),
	} ) );
	ok( 'telefon jako jeden moduł wygrywa ze starszymi kopiami arkusza i sam się przechyla',
		s.wysokosc > 700 && '52px' === s.rog && s.metal && '92px' === s.wyspa && '18px' === s.pasek && s.przechyla,
		JSON.stringify( s ) );
	await c.close();
}

/*
 * Ruch aparatu bez artefaktów, z nagrania z żywej strony:
 * - przewijanie pod nieruchomym kursorem przechylało aparat samo,
 * - przy krawędzi aparat migał, bo odsuwał się spod kursora,
 * - jeden przejazd ręką w prawo dawał cztery zmiany kierunku, bo położenie
 *   aparatu było mierzone raz, sprzed przewinięcia.
 */
console.log( '\nruch aparatu bez artefaktów' );
{
	const c = await b.newContext( { viewport: { width: 1600, height: 900 } } );
	const p = await c.newPage();
	await p.goto( 'file://' + TU + '/' );
	await p.setContent( '<!doctype html><html><head><meta charset="utf-8"></head><body style="margin:0">'
		+ '<div style="height:900px"></div>' + czytaj( 'TELEFON-sam.html' ) + '<div style="height:1400px"></div></body></html>', { waitUntil: 'load' } );
	const kat = () => p.evaluate( () => {
		const e = document.querySelector( '.lst-mz-telefon-rama' );
		const m = new DOMMatrixReadOnly( getComputedStyle( e ).transform );

		return { y: Math.asin( Math.max( -1, Math.min( 1, -m.m13 ) ) ) * 180 / Math.PI, nad: e.classList.contains( 'jest-nad' ) };
	} );

	await p.mouse.move( 800, 450 );
	let przewijanie = false;
	for ( let k = 0; k < 14; k++ ) {
		await p.mouse.wheel( 0, 90 );
		await p.waitForTimeout( 50 );
		const s = await kat();
		if ( s.nad || Math.abs( s.y ) > 0.2 ) { przewijanie = true; }
	}

	const r = await ( await p.$( '.lst-mz-telefon-stojak' ) ).boundingBox();
	await p.mouse.move( r.x + r.width * 0.3, r.y + r.height / 2, { steps: 4 } );
	await p.waitForTimeout( 400 );
	const proby = [];
	for ( let k = 0; k <= 20; k++ ) {
		await p.mouse.move( r.x + r.width * ( 0.3 + 0.65 * k / 20 ), r.y + r.height / 2 );
		await p.waitForTimeout( 16 );
		proby.push( ( await kat() ).y );
	}
	let zawrotki = 0;
	let poprz = 0;
	for ( let k = 1; k < proby.length; k++ ) {
		const d = proby[ k ] - proby[ k - 1 ];
		if ( Math.abs( d ) > 0.05 ) {
			if ( poprz && Math.sign( d ) !== Math.sign( poprz ) ) { zawrotki++; }
			poprz = d;
		}
	}

	const brzeg = [];
	for ( let k = 0; k < 16; k++ ) {
		await p.mouse.move( r.x + r.width * 0.985, r.y + r.height / 2 + ( k % 2 ) );
		await p.waitForTimeout( 25 );
		brzeg.push( ( await kat() ).nad );
	}

	ok( 'przewijanie nie przechyla, ręka prowadzi bez zawrotek, przy krawędzi nie miga',
		! przewijanie && 0 === zawrotki && proby[ proby.length - 1 ] > proby[ 0 ] && brzeg.every( Boolean ),
		`przechyla przy przewijaniu ${ przewijanie }, zawrotek ${ zawrotki }, od ${ proby[ 0 ].toFixed( 1 ) }° do ${ proby[ proby.length - 1 ].toFixed( 1 ) }°, krawędź stała ${ brzeg.every( Boolean ) }` );
	await c.close();
}

console.log( '\nsekcje osobno' );
for ( const [ nazwa, plik, co, zTabela ] of [
	[ 'What to look for', 'LEGENDA-en.html', '.lst-mz-legenda .lst-mz-pozycja', false ],
	[ 'Where it comes from', 'SKAD-en.html', '.lst-mz-pary .lst-mz-para', false ],
	[ 'What is in which', 'LISTY-en.html', '.lst-mz-listy .lst-mz-kolumna', false ],
	// Ta jedna niesie prawdziwą tabelę, więc arkusz i skrypt wtyczki ma nieść.
	[ 'On a phone', 'TELEFON-en.html', '.lst-mz-telefon .lstab-row', true ],
] ) {
	const tresc = czytaj( plik ).replace( /ADRES\//g, 'zrzuty/' );
	const { p, c } = await otworz( tresc );
	const r = await p.evaluate( ( sel ) => ( {
		ile: document.querySelectorAll( sel ).length,
		// Kroj z <style> w module, a nie odziedziczony po stronie: gdyby CSS
		// nie dojechał, tytuł byłby bezszeryfowy.
		krojTytulu: getComputedStyle( document.querySelector( '.lst-mz-naglowek' ) ).fontFamily.split( ',' )[ 0 ].replace( /"/g, '' ),
		niewidoczne: [ ...document.querySelectorAll( '.lst-mz [class*="lst-mz-"]' ) ].filter( ( e ) => getComputedStyle( e ).opacity === '0' ).length,
		poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
	} ), co );
	ok( nazwa + ' stoi sam: treść, własny krój, nic przezroczystego, bez suwaka',
		r.ile > 0 && r.krojTytulu === 'Inria Serif' && r.niewidoczne === 0 && r.poziom === 0,
		`${ r.ile } sztuk, ${ r.krojTytulu }, przezroczystych ${ r.niewidoczne }, suwak ${ r.poziom }` );
	/*
	 * Szukany jest ARKUSZ wtyczki, a nie nazwy jej klas: CSS tej strony ma
	 * własne reguły pisane na `.lstab-*` (utwardzenie, tabela w okienku)
	 * i jedzie z każdym modułem, bo to jeden plik. Szablon Karty istnieje
	 * wyłącznie w arkuszu wtyczki, więc po nim poznać, że ktoś doczepił tu
	 * całe 40 kB, którego ta sekcja nie ma po co nieść.
	 *
	 * Skrypt jest, ale wlasny i jeden: pol kilobajta wjazdu. Skrypt wtyczki
	 * poznac po jego wlasnej nazwie w naglowku pliku.
	 */
	ok( nazwa + ( zTabela ? ' niesie arkusz i skrypt wtyczki, bo ma tabelę' : ' nie wlecze za sobą arkusza ani skryptu wtyczki' ),
		zTabela
			? tresc.includes( 'lstab-style-cards' ) && tresc.includes( 'Live Sheets Table' )
			: ! tresc.includes( 'lstab-style-cards' ) && ! tresc.includes( 'Live Sheets Table' )
				&& 1 === ( tresc.match( /<script/g ) || [] ).length && tresc.length < 40 * 1024,
		`${ Math.round( tresc.length / 1024 ) } kB` );
	await c.close();
}

/*
 * Bliźniaki z wpisanym adresem Multimediów. Sprawdzane jest to, co da się
 * sprawdzić stąd: że nie został ani jeden `ADRES`, że każdy obrazek ma adres
 * bezwzględny po https i że nazwy plików są te, które poszły do Multimediów.
 * Czy pod tym adresem naprawdę coś leży, wie tylko ta witryna.
 */
console.log( '\nz wpisanym adresem' );
for ( const plik of [ 'SKAD-kod-gotowe.html', 'SKAD-en-gotowe.html', 'MOZLIWOSCI-kod-gotowe.html' ] ) {
	const tresc = czytaj( plik );
	const adresy = [ ...tresc.matchAll( /<img[^>]*src="([^"]*)"/g ) ].map( ( m ) => m[ 1 ] );
	const nazwy = adresy.map( ( a ) => a.split( '/' ).pop() ).sort().join( ' ' );
	ok( plik + ': żadnego ADRES, same adresy https',
		! tresc.includes( 'ADRES/' ) && adresy.length > 0 && adresy.every( ( a ) => a.startsWith( 'https://' ) )
			&& nazwy === 'mz-kolumny.png mz-reguly.png mz-wyglad.png',
		`${ adresy.length } obrazków, ${ nazwy }` );
}

console.log( `\n${ pass } PASS, ${ fail } FAIL` );
await b.close();
process.exit( fail ? 1 : 0 );
