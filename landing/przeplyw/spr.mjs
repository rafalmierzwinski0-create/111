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
		rozmiary: [ ...new Set( [ ...document.querySelectorAll( '.lst-pl [class*="lst-pl-"]:not( .lst-pl-naglowek )' ) ]
			.filter( ( e ) => [ ...e.childNodes ].some( ( n ) => 3 === n.nodeType && n.nodeValue.trim() ) )
			.map( ( e ) => Math.round( parseFloat( getComputedStyle( e ).fontSize ) ) ) ) ].sort( ( a, x ) => a - x ),
		myslniki: ( document.querySelector( '.lst-pl' ).innerText.match( /[–—]/g ) || [] ).length,
		// Tytuł sekcji to jedyne miejsce na tej witrynie, w którym wolno wyjść
		// poza 14, 18 i 20 — i jedyne, które jest szeryfowe.
		tytul: ( () => {
			const h = document.querySelector( '.lst-pl-naglowek' );
			if ( ! h ) return null;
			const cs = getComputedStyle( h );
			return { kroj: cs.fontFamily.split( ',' )[ 0 ].replace( /["']/g, '' ), px: Math.round( parseFloat( cs.fontSize ) ) };
		} )(),
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

	/*
	 * I nie leży na tabeli.
	 *
	 * Cienka miętowa kreska na tle wierszy, pigułek i słupków — czyli na tle
	 * rzeczy, które same są miętowe — ginie. Łuk ma pod sobą samo tło strony,
	 * w pasie pod oknem, i dopiero wtedy widać go całego. Mierzone na
	 * prostokątach, bo to jedyny sposób, żeby to zostało zmierzone: na oko
	 * przeszło raz i wyglądało znośnie.
	 */
	const luk = await p.evaluate( () => {
		const a = document.querySelector( '.lst-pl-strzalka' ).getBoundingClientRect();
		const t = document.querySelector( '.lst-pl .lstab-table' ).getBoundingClientRect();
		const o = document.querySelector( '.lst-pl-okno.jest-strona' ).getBoundingClientRect();

		/*
		 * Tabela jest wyższa niż okno i wychodzi poza nie — okno ją przycina.
		 * Liczy się część WIDOCZNA, czyli tabela przecięta oknem: mierzenie
		 * całego prostokąta tabeli pokazywałoby nachodzenie tam, gdzie nie ma
		 * czego narysować.
		 */
		const widoczna = {
			left: Math.max( t.left, o.left ),
			right: Math.min( t.right, o.right ),
			top: Math.max( t.top, o.top ),
			bottom: Math.min( t.bottom, o.bottom ),
		};
		const wspolne = Math.max( 0, Math.min( a.right, widoczna.right ) - Math.max( a.left, widoczna.left ) )
			* Math.max( 0, Math.min( a.bottom, widoczna.bottom ) - Math.max( a.top, widoczna.top ) );

		return { wspolne: Math.round( wspolne ), pole: Math.round( a.width * a.height ) };
	} );

	ok( 'i nie leży na tabeli', 0 === luk.wspolne, `wspólnych ${ luk.wspolne } z ${ luk.pole } px kw.` );
	ok( 'bez suwaka poziomego', 0 === r.poziom, String( r.poziom ) );
	ok( 'rozmiary pisma tylko 14, 18 i 20', r.rozmiary.every( ( x ) => [ 14, 18, 20 ].includes( x ) ), r.rozmiary.join( '/' ) );
	ok( 'ani jednego myślnika', 0 === r.myslniki, String( r.myslniki ) );
	ok( 'tytuł sekcji jest szeryfowy i większy niż reszta',
		r.tytul && 'Inria Serif' === r.tytul.kroj && r.tytul.px > 20,
		r.tytul ? `${ r.tytul.kroj } ${ r.tytul.px }` : 'brak' );
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

console.log( '\ndwie kolumny' );
{
	const { p, c } = await otworz( 1500, 1000 );

	const u = await p.evaluate( () => {
		const slowo = document.querySelector( '.lst-pl-slowo' ).getBoundingClientRect();
		const scena = document.querySelector( '.lst-pl-scena' ).getBoundingClientRect();
		const rama  = document.querySelector( '.lst-pl-rama' ).getBoundingClientRect();
		const okno  = document.querySelector( '.lst-pl-okno.jest-strona' ).getBoundingClientRect();
		const ark   = document.querySelector( '.lst-pl-okno.jest-arkuszem' ).getBoundingClientRect();
		const zachodzi = ( a, b ) => Math.max( 0, Math.min( a.right, b.right ) - Math.max( a.left, b.left ) )
			* Math.max( 0, Math.min( a.bottom, b.bottom ) - Math.max( a.top, b.top ) );

		return {
			obok: slowo.right <= scena.left + 1 && slowo.top < okno.bottom,
			naOknie: Math.round( zachodzi( slowo, okno ) ),
			naArkuszu: Math.round( zachodzi( slowo, ark ) ),
			pozaSzyne: Math.round( scena.right - rama.right ),
			doKrawedzi: Math.round( window.innerWidth - okno.right ),
			// Tabela z pięcioma kolumnami poniżej 700 px własnej szerokości składa
			// się w karty. Karty w tym miejscu nie mówią nic o tym, co wtyczka
			// potrafi, więc szerokość okna jest tu warunkiem, a nie skutkiem.
			szerokoscOkna: Math.round( okno.width ),
			tryb: getComputedStyle( document.querySelector( '.lst-pl .lstab-table' ) ).getPropertyValue( '--lstab-table-mode' ).trim(),
			// Wspólna linia u góry: tytuł zaczyna się mniej więcej tam, gdzie okno.
			odstepGory: Math.round( Math.abs( slowo.top - okno.top ) ),
		};
	} );

	ok( 'słowo po lewej, obrazek po prawej', u.obok, `nachodzenie: okno ${ u.naOknie }, arkusz ${ u.naArkuszu }` );
	ok( 'i tekst nie leży na żadnym z okien', 0 === u.naOknie && 0 === u.naArkuszu,
		`okno ${ u.naOknie }, arkusz ${ u.naArkuszu } px kw.` );
	ok( 'kompozycja wychodzi poza szynę, ale nie poza ekran',
		u.pozaSzyne > 8 && u.doKrawedzi > 8, `poza szynę ${ u.pozaSzyne }, do krawędzi ${ u.doKrawedzi }` );
	ok( 'tabela zostaje tabelą, a nie kartami', u.szerokoscOkna > 700 && '1' === u.tryb,
		`okno ${ u.szerokoscOkna } px, tryb ${ u.tryb }` );
	ok( 'tytuł zaczyna się tam, gdzie okno', u.odstepGory <= 40, `${ u.odstepGory } px` );

	await c.close();
}

/*
 * Na progu dwóch kolumn, czyli w najciaśniejszym miejscu, w którym one w ogóle
 * są. Tu właśnie tabela najpierw złożyłaby się w karty, a dwie kolumny z pustym
 * obrazkiem są gorsze niż jedna kolumna z pełnym.
 */
console.log( '\nna samym progu dwóch kolumn' );
{
	const { p, c } = await otworz( 1240, 1000 );

	const u = await p.evaluate( () => {
		const sc = document.querySelector( '.lst-pl .lstab-scroll' );

		return {
			kolumn: getComputedStyle( document.querySelector( '.lst-pl-uklad' ) ).gridTemplateColumns.split( ' ' ).length,
			okno: Math.round( document.querySelector( '.lst-pl-okno.jest-strona' ).getBoundingClientRect().width ),
			tryb: getComputedStyle( document.querySelector( '.lst-pl .lstab-table' ) ).getPropertyValue( '--lstab-table-mode' ).trim(),
			// Tabela szersza niż jej okno przewija się w bok i ostatnia kolumna
			// jest ucięta krawędzią okna. Wygląda to na uszkodzony zrzut, a nie
			// na tabelę, którą da się przesunąć. To jest powód, dla którego próg
			// dwóch kolumn stoi tam, gdzie stoi.
			przewija: sc ? sc.scrollWidth - sc.clientWidth : -1,
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		};
	} );

	ok( 'dwie kolumny już są', 2 === u.kolumn, `${ u.kolumn }` );
	ok( 'a tabela wciąż jest tabelą', u.okno > 700 && '1' === u.tryb, `okno ${ u.okno } px, tryb ${ u.tryb }` );
	ok( 'i mieści się w oknie w całości', 0 === u.przewija, `${ u.przewija } px poza oknem` );
	ok( 'bez suwaka poziomego', 0 === u.poziom, String( u.poziom ) );

	await c.close();
}

/* Tuż pod progiem tekst staje nad obrazkiem i nic nie traci. */
console.log( '\ntuż pod progiem' );
{
	const { p, c } = await otworz( 1180, 1000 );

	const u = await p.evaluate( () => {
		const slowo = document.querySelector( '.lst-pl-slowo' ).getBoundingClientRect();
		const scena = document.querySelector( '.lst-pl-scena' ).getBoundingClientRect();
		return {
			jednaKolumna: getComputedStyle( document.querySelector( '.lst-pl-uklad' ) ).gridTemplateColumns.split( ' ' ).length,
			nad: slowo.bottom <= scena.top + 1,
			okno: Math.round( document.querySelector( '.lst-pl-okno.jest-strona' ).getBoundingClientRect().width ),
			tryb: getComputedStyle( document.querySelector( '.lst-pl .lstab-table' ) ).getPropertyValue( '--lstab-table-mode' ).trim(),
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		};
	} );

	ok( 'jedna kolumna, tekst nad obrazkiem', 1 === u.jednaKolumna && u.nad, `kolumn ${ u.jednaKolumna }` );
	ok( 'i tabela dalej jest tabelą', u.okno > 700 && '1' === u.tryb, `okno ${ u.okno } px, tryb ${ u.tryb }` );
	ok( 'bez suwaka poziomego', 0 === u.poziom, String( u.poziom ) );

	await c.close();
}

/*
 * Te same napisy jako moduł sam dla siebie (SLOWO-en.html): do wstawienia
 * w osobną kolumnę Divi. Sprawdzane osobno, bo ma osobny arkusz stylów —
 * a dwa arkusze do jednego bloku rozjeżdżają się pierwszego dnia, w którym
 * ktoś poprawi jeden z nich.
 */
console.log( '\nsame napisy, osobny moduł' );
{
	const c = await b.newContext( { viewport: { width: 520, height: 900 }, deviceScaleFactor: 1 } );
	const p = await c.newPage();
	const bledy = [];
	p.on( 'pageerror', ( e ) => bledy.push( e.message ) );
	await p.goto( pathToFileURL( '/home/user/111/landing/przeplyw/SLOWO-podglad.html' ).href, { waitUntil: 'load' } );
	await p.waitForTimeout( 250 );

	const u = await p.evaluate( () => {
		const cs = getComputedStyle( document.querySelector( '.lst-sl-naglowek' ) );

		return {
			tytul: document.querySelector( '.lst-sl-naglowek' ).textContent.trim(),
			kroj: cs.fontFamily.split( ',' )[ 0 ].replace( /["']/g, '' ),
			px: Math.round( parseFloat( cs.fontSize ) ),
			punktow: document.querySelectorAll( '.lst-sl-punkt' ).length,
			ptaszkow: document.querySelectorAll( '.lst-sl-ptaszek' ).length,
			przycisk: ( document.querySelector( '.lst-sl-przycisk' ) || {} ).getAttribute
				? document.querySelector( '.lst-sl-przycisk' ).getAttribute( 'href' ) : '',
			rozmiary: [ ...new Set( [ ...document.querySelectorAll( '.lst-sl [class*="lst-sl-"]:not( .lst-sl-naglowek )' ) ]
				.filter( ( e ) => [ ...e.childNodes ].some( ( n ) => 3 === n.nodeType && n.nodeValue.trim() ) )
				.map( ( e ) => Math.round( parseFloat( getComputedStyle( e ).fontSize ) ) ) ) ].sort( ( a, x ) => a - x ),
			myslniki: ( document.querySelector( '.lst-sl' ).innerText.match( /[–—]/g ) || [] ).length,
			poziom: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		};
	} );

	// Te same słowa co w całej sekcji: jedne stałe, dwa pliki.
	const wSekcji = await ( async () => {
		const c2 = await b.newContext( { viewport: { width: 1500, height: 1000 } } );
		const p2 = await c2.newPage();
		await p2.goto( pathToFileURL( '/home/user/111/landing/przeplyw/PODGLAD.html' ).href, { waitUntil: 'load' } );
		const t = await p2.evaluate( () => ( {
			tytul: document.querySelector( '.lst-pl-naglowek' ).textContent.trim(),
			punkty: [ ...document.querySelectorAll( '.lst-pl-zdanie' ) ].map( ( e ) => e.textContent.trim() ),
		} ) );
		await c2.close();
		return t;
	} )();

	const punkty = await p.evaluate( () => [ ...document.querySelectorAll( '.lst-sl-zdanie' ) ].map( ( e ) => e.textContent.trim() ) );

	ok( 'moduł ma tytuł, cztery ptaszki i przycisk',
		4 === u.punktow && 4 === u.ptaszkow && 'ADRES-POBIERANIA' === u.przycisk,
		`punktów ${ u.punktow }, ptaszków ${ u.ptaszkow }, przycisk „${ u.przycisk }”` );
	ok( 'i są to te same słowa co w całej sekcji',
		u.tytul === wSekcji.tytul && punkty.join( '|' ) === wSekcji.punkty.join( '|' ),
		`${ u.tytul.slice( 0, 30 ) }… / ${ punkty.length } punktów` );
	ok( 'tytuł szeryfowy i większy niż reszta', 'Inria Serif' === u.kroj && u.px > 20, `${ u.kroj } ${ u.px }` );
	ok( 'rozmiary pisma tylko 14, 18 i 20', u.rozmiary.every( ( x ) => [ 14, 18, 20 ].includes( x ) ), u.rozmiary.join( '/' ) );
	ok( 'ani jednego myślnika', 0 === u.myslniki, String( u.myslniki ) );
	ok( 'bez suwaka poziomego w wąskiej kolumnie', 0 === u.poziom, String( u.poziom ) );
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
