/**
 * Zrzuty tej sekcji do pokazania: na tle strony i bez tła.
 *
 * Bez tła znaczy naprawdę bez tła — przezroczysty kanał alfa, a nie ciemny
 * prostokąt. Okna mają własne tła i zostają, znika tylko strona pod nimi, więc
 * obrazek da się położyć na czymkolwiek. Cienie lądują na przezroczystości
 * i tak mają zostać: bez nich arkusz przestaje leżeć na oknie.
 *
 * Użycie: node landing/przeplyw/zdjecie.mjs   (z katalogu landing/przeplyw)
 */

import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import { pathToFileURL } from 'node:url';

const TU = '/home/user/111/landing/przeplyw';

const b = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );

// --- na tle strony -------------------------------------------------------
{
	const p = await b.newPage( { viewport: { width: 1440, height: 1400 }, deviceScaleFactor: 2 } );
	await p.goto( pathToFileURL( TU + '/PODGLAD.html' ).href, { waitUntil: 'load' } );
	await p.waitForTimeout( 500 );

	const r = await ( await p.$( '.lst-pl' ) ).boundingBox();
	await p.screenshot( {
		path: TU + '/przeplyw-podglad.png',
		clip: { x: 0, y: Math.max( 0, r.y - 24 ), width: 1440, height: r.height + 48 },
	} );
	console.log( '  przeplyw-podglad.png  ', 1440 * 2, '×', Math.round( ( r.height + 48 ) * 2 ) );
	await p.close();
}

// --- bez tła -------------------------------------------------------------
{
	const p = await b.newPage( { viewport: { width: 1440, height: 1400 }, deviceScaleFactor: 2 } );

	/*
	 * Z PODGLĄDU, a nie z samego PRZEPLYW-en.html.
	 *
	 * Moduł jest fragmentem do wklejenia w Divi i nie ma własnej deklaracji
	 * typu dokumentu. Otwarty wprost z pliku wchodzi w tryb zgodności ze
	 * starociami, a w nim <table> NIE dziedziczy koloru tekstu po tym, w czym
	 * stoi: cała tabela wychodzi wtedy szara, tylko wiersz pomalowany regułą
	 * zostaje biały, bo ma kolor wpisany wprost. Na stronie tego nie widać,
	 * bo strona ma doctype — widać tylko na zrzucie, i wygląda to jak usterka
	 * wtyczki, którą nie jest.
	 *
	 * Podgląd ma doctype, więc zrzut robi się z niego, a tło strony zdejmuje
	 * się dopiero tutaj. „omitBackground” każe przeglądarce nie domalowywać
	 * białego prostokąta pod spodem.
	 */
	await p.goto( pathToFileURL( TU + '/PODGLAD.html' ).href, { waitUntil: 'load' } );
	await p.waitForTimeout( 500 );
	await p.addStyleTag( { content: 'html,body{background:transparent!important;background-image:none!important}' } );
	await p.waitForTimeout( 200 );

	const el = await p.$( '.lst-pl' );
	await el.screenshot( { path: TU + '/przeplyw-bez-tla.png', omitBackground: true } );

	const r = await el.boundingBox();
	console.log( '  przeplyw-bez-tla.png  ', Math.round( r.width * 2 ), '×', Math.round( r.height * 2 ) );
	await p.close();
}

/* --- sam obrazek, bez napisów ---------------------------------------------
 *
 * Kompozycja jako obrazek sam dla siebie: dwa okna i łuk, bez kolumny
 * z tekstem. Do wstawienia jako zwykły obrazek albo obok modułu SLOWO-en.html,
 * kiedy napisy mają stać osobno.
 *
 * Kolumna z tekstem jest wyjmowana ze strony, a nie chowana: schowana zostawia
 * po sobie kolumnę siatki i obrazek stanąłby w prawej połowie kadru. Scena
 * wraca przy tym do środka (bez wyjścia poza szynę, bo tu nie ma już żadnej
 * szyny, z której można by wyjść) i odzyskuje dawne dopełnienie z lewej, żeby
 * arkusz znowu wystawał z okna tak, jak wystawał, zanim obok stanął tekst.
 */
const bezNapisow = async ( p ) => {
	await p.evaluate( () => {
		document.querySelector( '.lst-pl-slowo' ).remove();
		const u = document.querySelector( '.lst-pl-uklad' );
		u.style.display = 'block';
		const s = document.querySelector( '.lst-pl-scena' );
		s.style.setProperty( 'margin-right', '0', 'important' );
		s.style.paddingLeft = 'clamp( 0rem, 6vw, 7rem )';
	} );
	await p.waitForTimeout( 250 );
};

/*
 * Kadr liczony z tego, co narysowane, a nie z pudełka sekcji.
 *
 * Sekcja jest szeroka na całe okno, a kompozycja stoi w niej odsunięta od
 * krawędzi o margines strony — na zrzucie zostawał przez to przezroczysty pas
 * z lewej i z prawej. Na stronie taki obrazek przestaje wyglądać na
 * wyśrodkowany, bo jego treść jest węższa niż on sam.
 *
 * Kadr idzie więc dokładnie po krawędziach okien: od lewej krawędzi arkusza do
 * prawej krawędzi okna strony. Nic się przy tym nie traci, bo cienie tych okien
 * mają rozmycie mniejsze niż ujemny rozrzut i w bok nie sięgają ani o piksel.
 * Na dole zostaje tyle, ile sięga cień pod arkuszem.
 */
const kadr = async ( p ) => p.evaluate( () => {
	const pudla = [ ...document.querySelectorAll( '.lst-pl-okno, .lst-pl-luk' ) ]
		.map( ( e ) => e.getBoundingClientRect() );
	const lewo = Math.min( ...pudla.map( ( r ) => r.left ) );
	const prawo = Math.max( ...pudla.map( ( r ) => r.right ) );
	const gora = Math.min( ...pudla.map( ( r ) => r.top ) );
	const dol = Math.max( ...pudla.map( ( r ) => r.bottom ) );

	return {
		x: Math.round( lewo + scrollX ),
		y: Math.round( gora + scrollY ),
		width: Math.round( prawo - lewo ),
		// Cień pod arkuszem: 26 px przesunięcia i tyle samo rozmycia w dół.
		height: Math.round( dol - gora + 30 ),
	};
} );

{
	const p = await b.newPage( { viewport: { width: 1440, height: 1400 }, deviceScaleFactor: 2 } );
	await p.goto( pathToFileURL( TU + '/PODGLAD.html' ).href, { waitUntil: 'load' } );
	await p.waitForTimeout( 500 );
	await bezNapisow( p );

	const c = await kadr( p );
	await p.screenshot( { path: TU + '/przeplyw-obrazek.png', clip: c } );
	console.log( '  przeplyw-obrazek.png  ', c.width * 2, '×', c.height * 2 );
	await p.close();
}

{
	const p = await b.newPage( { viewport: { width: 1440, height: 1400 }, deviceScaleFactor: 2 } );
	await p.goto( pathToFileURL( TU + '/PODGLAD.html' ).href, { waitUntil: 'load' } );
	await p.waitForTimeout( 500 );
	await bezNapisow( p );
	await p.addStyleTag( { content: 'html,body{background:transparent!important;background-image:none!important}' } );
	await p.waitForTimeout( 200 );

	const c = await kadr( p );
	await p.screenshot( { path: TU + '/przeplyw-obrazek-bez-tla.png', clip: c, omitBackground: true } );
	console.log( '  przeplyw-obrazek-bez-tla.png  ', c.width * 2, '×', c.height * 2 );
	await p.close();
}

await b.close();
