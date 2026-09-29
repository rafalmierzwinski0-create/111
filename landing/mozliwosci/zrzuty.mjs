/**
 * Trzy zrzuty z prawdziwego kokpitu, na tym samym źródle, które stoi na
 * podstronie. Każdy przycięty do jednej karty — tej, która tłumaczy jedną
 * rzecz. Nie więcej: strona ma pokazywać wtyczkę, a nie album.
 *
 * Wymaga, żeby najpierw poszło landing/mozliwosci/zbierz.php — ono zakłada
 * źródło i ustawia mu reguły, wygląd kolumn i filtry.
 *
 * Użycie: node landing/mozliwosci/zrzuty.mjs
 */

import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';

const BAZA = 'http://127.0.0.1:8089';
const KAT = new URL( './zrzuty/', import.meta.url ).pathname;

fs.mkdirSync( KAT, { recursive: true } );

const b = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
const c = await b.newContext( { viewport: { width: 1500, height: 1300 }, deviceScaleFactor: 2 } );
const p = await c.newPage();

const zrobione = [];

/**
 * Zrzut elementu z marginesem — bez niego karta wygląda jak ucięta — i z
 * górnym ograniczeniem wysokości. Karta z siedmioma regułami ma dwa i pół
 * tysiąca pikseli i na stronie byłaby wieżowcem; trzy reguły mówią to samo.
 */
async function kadr( nazwa, selektor, { luz = 14, wysokosc = 0, odGory = null, doDolu = null, luzDol = 14 } = {} ) {
	const el = p.locator( selektor ).first();
	await el.scrollIntoViewIfNeeded();
	await p.evaluate( () => window.scrollBy( 0, -60 ) );
	await p.waitForTimeout( 350 );

	const r = await el.boundingBox();

	if ( odGory ) {
		const start = await p.locator( odGory ).first().boundingBox();
		r.height -= start.y - r.y;
		r.y = start.y;
	}

	// Zrzut ma się kończyć na krawędzi czegoś, a nie w połowie pola wyboru:
	// ucięty w pół kontrolki wygląda na pomyłkę, a nie na kadr.
	if ( doDolu ) {
		const koniec = await p.locator( doDolu ).last().boundingBox();
		r.height = koniec.y + koniec.height + luzDol - r.y;
	}

	if ( wysokosc && r.height > wysokosc ) {
		r.height = wysokosc;
	}
	const strona = await p.evaluate( () => ( { w: document.documentElement.clientWidth, h: document.documentElement.clientHeight } ) );
	const clip = {
		x: Math.max( 0, r.x - luz ),
		y: Math.max( 0, r.y - luz ),
		width: Math.min( strona.w - Math.max( 0, r.x - luz ), r.width + luz * 2 ),
		height: Math.min( strona.h - Math.max( 0, r.y - luz ), r.height + luz * 2 ),
	};

	await p.screenshot( { path: `${ KAT }${ nazwa }.png`, clip } );
	zrobione.push( { nazwa, w: Math.round( clip.width * 2 ), h: Math.round( clip.height * 2 ) } );
	console.log( `  ✓ ${ nazwa }  ${ Math.round( clip.width * 2 ) }×${ Math.round( clip.height * 2 ) }` );
}

await p.goto( `${ BAZA }/wp-login.php` );
await p.fill( '#user_login', 'admin' );
await p.fill( '#user_pass', 'admin123' );
await p.click( '#wp-submit' );
await p.waitForLoadState( 'networkidle' );

// Ekran źródła, zakładka Wygląd: szablony, kolory i pokrętła.
await p.goto( `${ BAZA }/wp-admin/admin.php?page=live-sheets-table-edit&source=1`, { waitUntil: 'networkidle' } );
await p.waitForTimeout( 500 );

const zakladka = async ( nazwa ) => {
	const t = p.locator( `[data-lstab-goto="${ nazwa }"]` );
	if ( await t.count() ) {
		await t.first().click();
		await p.waitForTimeout( 400 );
	}
};

// Pasek WordPressa nad wszystkim nie mówi nic o wtyczce, a wchodzi w kadr.
await p.addStyleTag( { content: '#wpadminbar{display:none!important}html{margin-top:0!important}' } );

await zakladka( 'look' );
await kadr( 'mz-wyglad', '.lstab-appearance', { doDolu: '.lstab-metric:nth-of-type(4) select' } );
await kadr( 'mz-reguly', '.lstabp-rules-card', { odGory: '.lstabp-rule', doDolu: '.lstabp-rule:nth-of-type(3)' } );
await kadr( 'mz-kolumny', '.lstabp-looks-card', { odGory: '.lstabp-look', doDolu: '.lstabp-look:last-of-type' } );

fs.writeFileSync( `${ KAT }rozmiary.json`, JSON.stringify( zrobione, null, 1 ) );

console.log( '\n  ' + zrobione.length + ' zrzutów w ' + KAT );
await b.close();
