/**
 * Sprawdzenie sekcji „z arkusza na stronę”.
 *
 * Najważniejsze jest tu jedno: po obu stronach strzałki ma stać TA SAMA treść.
 * Arkusz po lewej i tabela po prawej biorą się z jednego renderu, więc nie da
 * się ich rozjechać nie przebudowując obu naraz — i dokładnie to jest tutaj
 * mierzone, wiersz po wierszu. Sekcja, w której przykład po lewej mówi co
 * innego niż przykład po prawej, kłamie o produkcie, a wygląda dobrze.
 *
 * Użycie: node landing/przeplyw/spr.mjs   (z katalogu landing/przeplyw)
 */

import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import { pathToFileURL } from 'node:url';
import fs from 'fs';

const dane = JSON.parse( fs.readFileSync( '/home/user/111/landing/przeplyw/markup.json', 'utf8' ) );

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
const bledy = [];

const otworz = async ( szer, wys, ruch = true ) => {
	const c = await b.newContext( {
		viewport: { width: szer, height: wys },
		reducedMotion: ruch ? 'no-preference' : 'reduce',
	} );
	const p = await c.newPage();
	p.on( 'pageerror', ( e ) => bledy.push( e.message ) );
	await p.goto( pathToFileURL( '/home/user/111/landing/przeplyw/PODGLAD.html' ).href, { waitUntil: 'load' } );
	await p.waitForTimeout( 300 );
	return { p, c };
};

console.log( '\nsekcja na stronie' );
{
	const { p, c } = await otworz( 1500, 1000 );

	const r = await p.evaluate( () => ( {
		okien: document.querySelectorAll( '.lst-pl-okno' ).length,
		arkuszy: document.querySelectorAll( '.lst-pl-okno.jest-arkuszem' ).length,
		tabel: document.querySelectorAll( '.lst-pl .lstab' ).length,
		wierszy: document.querySelectorAll( '.lst-pl .lstab tbody tr.lstab-row' ).length,
		kolumn: document.querySelectorAll( '.lst-pl .lstab thead th' ).length,
		// To, czym ta tabela ma się pochwalić: pigułki, słupki, przyciski, filtr.
		pigulek: document.querySelectorAll( '.lst-pl .lstabp-pill' ).length,
		slupkow: document.querySelectorAll( '.lst-pl .lstabp-bar' ).length,
		przyciskow: document.querySelectorAll( '.lst-pl .lstabp-cta-link' ).length,
		filtrow: document.querySelectorAll( '.lst-pl .lstabp-facet' ).length,
		malowanych: document.querySelectorAll( '.lst-pl .lstab tbody tr.lstab-row td[style*="--lstab-row-tint"]' ).length,
		strzalek: document.querySelectorAll( '.lst-pl-luk' ).length,
		poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		rozmiary: [ ...new Set( [ ...document.querySelectorAll( '.lst-pl [class*="lst-pl-"]' ) ]
			.filter( ( e ) => [ ...e.childNodes ].some( ( n ) => 3 === n.nodeType && n.nodeValue.trim() ) )
			.map( ( e ) => Math.round( parseFloat( getComputedStyle( e ).fontSize ) ) ) ) ].sort( ( a, x ) => a - x ),
		myslniki: ( document.querySelector( '.lst-pl' ).innerText.match( /[–—]/g ) || [] ).length,
		schowane: [ ...document.querySelectorAll( '.lst-pl [class*="lst-pl-"]' ) ]
			.filter( ( e ) => '0' === getComputedStyle( e ).opacity ).length,
	} ) );

	ok( 'dwa okna: arkusz i strona', 2 === r.okien && 1 === r.arkuszy, `${ r.okien } okna, arkuszy ${ r.arkuszy }` );
	ok( 'po prawej stoi prawdziwa tabela z wtyczki', 1 === r.tabel && 8 === r.wierszy && 5 === r.kolumn,
		`${ r.wierszy } wierszy, ${ r.kolumn } kolumn` );
	ok( 'i widać na niej to, czym wtyczka się chwali',
		r.pigulek === 8 && r.slupkow === 8 && r.przyciskow === 8 && r.filtrow === 1 && r.malowanych > 0,
		`pigułek ${ r.pigulek }, słupków ${ r.slupkow }, przycisków ${ r.przyciskow }, filtrów ${ r.filtrow }, malowanych komórek ${ r.malowanych }` );
	ok( 'strzałka jest', 1 === r.strzalek, String( r.strzalek ) );
	ok( 'bez suwaka poziomego', 0 === r.poziom, String( r.poziom ) );
	ok( 'rozmiary pisma tylko 14, 18 i 20', r.rozmiary.every( ( x ) => [ 14, 18, 20 ].includes( x ) ), r.rozmiary.join( '/' ) );
	ok( 'ani jednego myślnika', 0 === r.myslniki, String( r.myslniki ) );
	ok( 'nic nie jest niewidoczne w spoczynku', 0 === r.schowane, String( r.schowane ) );

	/*
	 * Serce tej sekcji. Wartości w arkuszu po lewej muszą być dosłownie tymi
	 * samymi wartościami, które wtyczka narysowała po prawej — bo obie strony
	 * biorą się z jednego renderu. Sprawdzane na wierszach, nie na nagłówkach:
	 * nagłówki łatwo przepisać ręcznie i nie zauważyć, że reszta się rozjechała.
	 */
	const para = await p.evaluate( () => {
		const czysto = ( x ) => x.replace( /\s+/g, ' ' ).trim();
		const arkusz = [ ...document.querySelectorAll( '.lst-pl-wiersz:not( .jest-naglowkiem )' ) ]
			.map( ( w ) => [ ...w.querySelectorAll( '.lst-pl-cela' ) ].map( ( e ) => czysto( e.textContent ) ) );
		const tabela = [ ...document.querySelectorAll( '.lst-pl .lstab tbody tr.lstab-row' ) ]
			.map( ( w ) => [ ...w.querySelectorAll( '.lstab-cell-value' ) ].map( ( e ) => czysto( e.textContent ) ) );

		return { arkusz, tabela };
	} );

	const rozne = [];

	para.arkusz.forEach( ( wiersz, i ) => {
		wiersz.forEach( ( cela, j ) => {
			// Kolumna z adresem staje się po prawej przyciskiem, więc jej treść
			// z założenia jest inna: przycisk mówi „Open”, a nie adres.
			if ( j === wiersz.length - 1 ) {
				return;
			}
			const tam = ( para.tabela[ i ] || [] )[ j ];
			if ( cela !== tam ) {
				rozne.push( `w${ i + 1 }k${ j + 1 }: „${ cela }” vs „${ tam }”` );
			}
		} );
	} );

	ok( 'po obu stronach strzałki stoi ta sama treść, wiersz w wiersz',
		0 === rozne.length && para.arkusz.length > 0, rozne.slice( 0, 3 ).join( ' | ' ) || `${ para.arkusz.length } wierszy zgodnych` );

	// A dane w arkuszu to naprawdę to, co zapisał render, a nie coś dopisanego
	// w szablonie strony.
	ok( 'i jest to ta sama treść co w markup.json',
		para.arkusz[ 0 ][ 0 ] === dane.rows[ 0 ][ 0 ] && para.arkusz[ 0 ][ 1 ] === dane.rows[ 0 ][ 1 ],
		`${ para.arkusz[ 0 ][ 0 ] } / ${ dane.rows[ 0 ][ 0 ] }` );

	// Kontrast najsłabszego napisu w arkuszu: mono na ciemnym papierze.
	const kontrast = await p.evaluate( () => {
		const parse = ( c ) => {
			c = ( c || '' ).trim();
			const m = /^(?:rgba?|color)\(([^)]+)\)/.exec( c );
			if ( ! m ) return null;
			const skala = c.startsWith( 'color(' ) ? 255 : 1;
			const a = m[ 1 ].replace( /^srgb\s+/, '' ).split( /[\s,\/]+/ ).filter( Boolean ).map( Number );
			return { r: a[ 0 ] * skala, g: a[ 1 ] * skala, b: a[ 2 ] * skala, a: undefined === a[ 3 ] ? 1 : a[ 3 ] };
		};
		const over = ( t, u ) => ( { r: t.r * t.a + u.r * ( 1 - t.a ), g: t.g * t.a + u.g * ( 1 - t.a ), b: t.b * t.a + u.b * ( 1 - t.a ), a: 1 } );
		const behind = ( el ) => {
			const L = []; let n = el;
			while ( n && 1 === n.nodeType ) {
				const c = parse( getComputedStyle( n ).backgroundColor );
				if ( c && c.a > 0 ) { L.push( c ); if ( c.a >= 0.999 ) break; }
				n = n.parentElement;
			}
			let o = L[ L.length - 1 ] || { r: 255, g: 255, b: 255, a: 1 };
			for ( let i = L.length - 2; i >= 0; i -= 1 ) o = over( L[ i ], o );
			return o;
		};
		const lum = ( c ) => {
			const f = ( v ) => { const s = v / 255; return s <= 0.03928 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 ); };
			return 0.2126 * f( c.r ) + 0.7152 * f( c.g ) + 0.0722 * f( c.b );
		};
		let worst = { r: 99, co: '' };
		document.querySelectorAll( '.lst-pl [class*="lst-pl-"], .lst-pl .lstab tbody .lstab-cell-value' ).forEach( ( el ) => {
			if ( ! el.textContent.trim() ) return;
			if ( el.children.length && ! [ ...el.childNodes ].some( ( n ) => 3 === n.nodeType && n.nodeValue.trim() ) ) return;
			const ink = parse( getComputedStyle( el ).color );
			if ( ! ink ) return;
			const pap = behind( el );
			const f = ink.a >= 0.999 ? ink : over( ink, pap );
			const a = lum( f ), c = lum( pap );
			const r = Math.round( ( ( Math.max( a, c ) + 0.05 ) / ( Math.min( a, c ) + 0.05 ) ) * 100 ) / 100;
			if ( r < worst.r ) worst = { r, co: String( el.className ).slice( 0, 30 ) + ' „' + el.textContent.trim().slice( 0, 18 ) + '”' };
		} );
		return worst;
	} );

	/*
	 * Ostatni wiersz arkusza jest przygaszony maską, bo wychodzi poza kadr —
	 * to nie jest napis do czytania, tylko zapowiedź, że jest tego więcej.
	 * Mierzone jest to, co czytelne ma być.
	 */
	ok( 'najsłabszy napis ma co najmniej 4,5 : 1', kontrast.r >= 4.5, `${ kontrast.r } : 1 — ${ kontrast.co }` );
	ok( 'bez błędów skryptu', 0 === bledy.length, bledy.join( ' | ' ) || '0' );

	await c.close();
}

console.log( '\nmniej ruchu' );
{
	const { p, c } = await otworz( 1500, 1000, false );
	const r = await p.evaluate( () => ( {
		luk: getComputedStyle( document.querySelector( '.lst-pl-luk-linia' ) ).animationName,
		kreski: getComputedStyle( document.querySelector( '.lst-pl-luk-linia' ) ).strokeDasharray,
		okno: getComputedStyle( document.querySelector( '.lst-pl-okno' ) ).animationName,
		widocznych: [ ...document.querySelectorAll( '.lst-pl-okno, .lst-pl-luk' ) ]
			.filter( ( e ) => '1' === getComputedStyle( e ).opacity ).length,
	} ) );
	ok( 'przy prefers-reduced-motion nic się nie rysuje, a wszystko widać',
		'none' === r.luk && 'none' === r.okno && 'none' === r.kreski && 3 === r.widocznych,
		`łuk ${ r.luk }, kreski ${ r.kreski }, okno ${ r.okno }, widocznych ${ r.widocznych }` );
	await c.close();
}

console.log( '\nwąsko' );
{
	const { p, c } = await otworz( 390, 900 );
	const r = await p.evaluate( () => {
		const arkusz = document.querySelector( '.lst-pl-okno.jest-arkuszem' ).getBoundingClientRect();
		const strona = document.querySelector( '.lst-pl-okno.jest-strona' ).getBoundingClientRect();
		const luk = document.querySelector( '.lst-pl-strzalka' ).getBoundingClientRect();

		return {
			// Arkusz idzie na górę: to on jest pierwszy w tej historii.
			kolejnosc: arkusz.bottom <= strona.top + 1,
			// Strzałka stoi między oknami, a nie poza ekranem.
			strzalkaMiedzy: luk.top >= arkusz.bottom - 1 && luk.bottom <= strona.top + 1
				&& luk.left >= 0 && luk.right <= innerWidth,
			karty: getComputedStyle( document.querySelector( '.lst-pl .lstab thead' ) ).display,
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		};
	} );
	ok( 'arkusz staje nad stroną, jedno pod drugim', r.kolejnosc );
	ok( 'strzałka stoi między oknami i mieści się w ekranie', r.strzalkaMiedzy );
	ok( 'tabela składa się w karty', 'none' === r.karty, r.karty );
	ok( 'bez suwaka poziomego na telefonie', 0 === r.poziom, String( r.poziom ) );
	await c.close();
}

await b.close();
console.log( `\n${ pass } PASS, ${ fail } FAIL\n` );
process.exit( fail ? 1 : 0 );
