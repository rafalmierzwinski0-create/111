/**
 * A table's own CSS, put through everything a person who knows CSS might write.
 *
 * Selectors of every shape, at-rules, nesting, odd values, attempts to reach
 * the rest of the page, and what the table does around them: search, sorting,
 * pages, details rows, a newer copy swapped in quietly, right to left, dark
 * mode and print. Every case is the only CSS on the page while it is checked,
 * and checked by what the browser computed, not by what was printed.
 *
 * Then the same cases again, typed into the edit screen, against the live
 * preview; the block editor; and a Pro colour rule, which has to keep its
 * paint over a background from the own CSS, pinned cell included.
 *
 * This was written after a pinned first column ignored every background the
 * own CSS gave it, "&" inside a quoted value broke the whole rule, and
 * scroll-behavior and inline SVG images were mangled by the cleaning meant for
 * old Internet Explorer and for "</style>".
 *
 * Needs the Pro add-on active, for the colour rule.
 *
 * Usage: node tests/custom-css-browser.mjs [/path/to/wp] [port]
 */

import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import { execFileSync } from 'child_process';
import fs from 'fs';
import os from 'os';
import path from 'path';
import { fileURLToPath } from 'url';
import { cases } from './custom-css-cases.mjs';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const SITE = process.argv[ 2 ] || '/tmp/lstab-env/wp71';
const PORT = process.argv[ 3 ] || '8089';
const BASE = `http://127.0.0.1:${ PORT }`;
const scratch = fs.mkdtempSync( path.join( os.tmpdir(), 'lstab-css-' ) );

const harness = ( ...args ) => execFileSync( 'php', [ path.join( here, 'harness/custom-css.php' ), SITE, PORT, ...args ], { encoding: 'utf8' } ).trim();

let passed = 0;
let failed = 0;

const check = ( ok, what, detail = '' ) => {
	if ( ok ) {
		passed += 1;
		console.log( '  ok  ', what );
		return;
	}
	failed += 1;
	console.log( '  FAIL', what );
	if ( detail ) {
		console.log( '       ', String( detail ).slice( 0, 300 ) );
	}
};

const made = JSON.parse( harness( 'setup' ) );
const HOURS = made.tables.hours;
const OTHER = made.tables.workshops;

const setCss = ( css, ...flags ) => {
	const file = path.join( scratch, 'css.txt' );
	fs.writeFileSync( file, css );
	harness( 'css', String( HOURS ), file, ...flags );
};

const helpers = ( root ) => `
	window.HOURS = ${ HOURS };
	window.OTHER = ${ OTHER };
	window.T = ( sel, n = 0 ) => { const r = document.querySelectorAll( ${ JSON.stringify( root ) } )[ n ]; return sel ? r.querySelector( sel ) : r; };
	window.TA = ( sel ) => [ ...document.querySelector( ${ JSON.stringify( root ) } ).querySelectorAll( sel ) ];
	window.cs = ( el, p, ps = null ) => el ? getComputedStyle( el, ps ).getPropertyValue( p ).trim() : 'NO ELEMENT';
	window.cell = ( label, row = 1 ) => T( '.lstab-table tbody tr:nth-child(' + row + ') > td[data-label="' + label + '"]' );
`;

const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );

try {
	console.log( '\nOn the page' );

	for ( const [ name, css, fn, opts = {} ] of cases ) {
		setCss( css, ...( opts.php ? [ 'detail=on' ] : [] ) );

		const context = await browser.newContext( { viewport: { width: opts.width || 1100, height: 900 } } );
		const page = await context.newPage();

		if ( opts.media ) {
			await page.emulateMedia( opts.media );
		}
		await page.addInitScript( helpers( `[data-lstab-id="${ HOURS }"]` ) );

		let result = [ false, '' ];
		try {
			await page.goto( made.url );
			await page.waitForSelector( `[data-lstab-id="${ HOURS }"] .lstab-table` );
			result = await page.evaluate( `(${ fn.toString() })()` );
		} catch ( e ) {
			result = [ false, 'ERROR ' + e.message.split( '\n' )[ 0 ] ];
		}
		check( result[ 0 ], name, result[ 1 ] );

		if ( opts.php ) {
			setCss( '', 'detail=off' );
		}
		await context.close();
	}

	// A real Tab, not a focus() call, so :focus-visible is what a keyboard sees.
	setCss( '.lstab-table thead :is(a, button):focus-visible { outline: 4px dashed rgb(1,2,3) !important; }' );
	{
		const page = await browser.newPage();
		await page.goto( made.url );
		await page.click( `[data-lstab-id="${ HOURS }"] .lstab-search-input` );
		let ring = '';
		for ( let i = 0; i < 6 && ! ring.includes( 'rgb(1, 2, 3)' ); i++ ) {
			await page.keyboard.press( 'Tab' );
			ring = await page.evaluate( () => document.activeElement.closest( 'thead' ) ? getComputedStyle( document.activeElement ).outlineColor + ' ' + getComputedStyle( document.activeElement ).outlineStyle : 'not in the heading row' );
		}
		check( ring.includes( 'rgb(1, 2, 3)' ) && ring.includes( 'dashed' ), 'keyboard: an own focus ring on a sort control', ring );
		await page.close();
	}

	console.log( '\nA table split into pages' );

	setCss( '&.lstab-paged td[data-label="Day"] { color: rgb(1,2,3); } tbody tr:nth-child(even) > td { background-color: rgb(4,5,6); }', 'per_page=3' );
	{
		const page = await browser.newPage();
		await page.goto( made.url );
		const read = ( n ) => page.evaluate( ( [ id, n ] ) => {
			const r = document.querySelectorAll( `[data-lstab-id="${ id }"]` )[ n ];
			return {
				day: r.querySelector( 'tbody tr:first-child > td[data-label="Day"] .lstab-cell-value' ).textContent,
				colour: getComputedStyle( r.querySelector( 'td[data-label="Day"]' ) ).color,
				stripe: getComputedStyle( r.querySelector( 'tbody tr:nth-child(2) > td:nth-child(2)' ) ).backgroundColor,
				pin: getComputedStyle( r.querySelector( 'tbody tr:nth-child(2) > td:first-child' ), '::before' ).backgroundColor,
				blocks: document.querySelectorAll( `style[data-lstab-css="${ id }"]` ).length,
			};
		}, [ HOURS, n ] );
		const styled = ( got ) => 'rgb(1, 2, 3)' === got.colour && 'rgb(4, 5, 6)' === got.stripe && 'rgb(4, 5, 6)' === got.pin && 1 === got.blocks;

		const first = await read( 0 );
		check( styled( first ), 'page 1 wears the own CSS, & as the paged table included', JSON.stringify( first ) );

		for ( const n of [ 0, 1 ] ) {
			await page.locator( `[data-lstab-id="${ HOURS }"] >> nth=${ n }` ).locator( 'a.lstab-page-link:not(.is-disabled)' ).first().click();
			await page.waitForFunction( ( [ id, n, day ] ) => document.querySelectorAll( `[data-lstab-id="${ id }"]` )[ n ].querySelector( 'tbody tr:first-child > td[data-label="Day"] .lstab-cell-value' ).textContent !== day, [ HOURS, n, first.day ], { timeout: 10000 } ).catch( () => {} );
			const turned = await read( n );
			check( turned.day !== first.day && styled( turned ), `copy ${ n + 1 } turned to page 2 without a reload, still styled`, JSON.stringify( turned ) );
		}
		await page.close();
	}
	setCss( '', 'per_page=0' );

	console.log( '\nA newer copy swapped in quietly' );

	setCss( 'td[data-label="Day"] { color: rgb(1,2,3); }' );
	{
		const page = await browser.newPage();
		let served = false;
		// The page claims an older copy than the one the server holds, and the
		// file of due tables says so too: exactly what a page cache serves.
		await page.route( made.url, async ( route ) => {
			if ( served ) {
				return route.continue();
			}
			served = true;
			const response = await route.fetch();
			const html = ( await response.text() )
				.replace( new RegExp( `(data-lstab-id="${ HOURS }"[^>]*?)data-lstab-copy="[^"]*"`, 'g' ), '$1data-lstab-copy="older"' )
				.replace( /data-lstab-due="[^"]*"/g, `data-lstab-due="${ BASE }/lstab-due-test.json"` );
			return route.fulfill( { response, body: html } );
		} );
		await page.route( /lstab-due-test\.json/, ( route ) => route.fulfill( { contentType: 'application/json', body: JSON.stringify( { t: { [ HOURS ]: { c: 'newest', n: 0, f: 0 } } } ) } ) );
		await page.goto( made.url );
		await page.waitForFunction( ( id ) => [ ...document.querySelectorAll( `[data-lstab-id="${ id }"]` ) ].every( ( r ) => 'older' !== r.getAttribute( 'data-lstab-copy' ) ), HOURS, { timeout: 10000 } ).catch( () => {} );
		const state = await page.evaluate( ( id ) => ( {
			copies: [ ...document.querySelectorAll( `[data-lstab-id="${ id }"]` ) ].map( ( r ) => r.getAttribute( 'data-lstab-copy' ) ),
			colours: [ ...document.querySelectorAll( `[data-lstab-id="${ id }"]` ) ].map( ( r ) => getComputedStyle( r.querySelector( 'td[data-label="Day"]' ) ).color ),
			blocks: document.querySelectorAll( `style[data-lstab-css="${ id }"]` ).length,
		} ), HOURS );
		check( ! state.copies.includes( 'older' ), 'both copies were swapped for the newer one', JSON.stringify( state.copies ) );
		check( state.colours.every( ( c ) => 'rgb(1, 2, 3)' === c ) && 1 === state.blocks, 'and both still wear the own CSS, from one style block', JSON.stringify( state ) );
		await page.close();
	}

	console.log( '\nA Pro colour rule' );

	harness( 'rule', String( HOURS ), 'on' );
	setCss( 'td { background-color: rgb(1,2,3); }' );
	{
		const page = await browser.newPage();
		await page.goto( made.url );
		const got = await page.evaluate( ( id ) => {
			const r = document.querySelector( `[data-lstab-id="${ id }"]` );
			const friday = [ ...r.querySelectorAll( 'tbody tr' ) ].find( ( tr ) => tr.textContent.includes( 'Friday' ) );
			const monday = r.querySelector( 'tbody tr:first-child' );
			return {
				fridayCell: getComputedStyle( friday.children[ 1 ] ).backgroundColor,
				fridayPin: getComputedStyle( friday.children[ 0 ], '::before' ).backgroundImage,
				mondayCell: getComputedStyle( monday.children[ 1 ] ).backgroundColor,
				mondayPin: getComputedStyle( monday.children[ 0 ], '::before' ).backgroundColor,
			};
		}, HOURS );
		check( 'rgb(251, 213, 213)' === got.fridayCell && got.fridayPin.includes( '251, 213, 213' ), 'a painted row keeps its paint over the own background, pinned cell included', JSON.stringify( got ) );
		check( 'rgb(1, 2, 3)' === got.mondayCell && 'rgb(1, 2, 3)' === got.mondayPin, 'and every other row takes the own background, pinned cell included', JSON.stringify( got ) );
		await page.close();
	}
	harness( 'rule', String( HOURS ), 'off' );

	console.log( '\nIn the live preview, typed into the edit screen' );

	setCss( '' );
	const admin = await ( await browser.newContext( { viewport: { width: 1280, height: 900 } } ) ).newPage();
	await admin.goto( BASE + '/wp-login.php' );
	await admin.fill( '#user_login', 'admin' );
	await admin.fill( '#user_pass', 'admin123' );
	await admin.click( '#wp-submit' );
	await admin.waitForURL( /wp-admin/ );
	await admin.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table-edit&source=${ HOURS }` );
	await admin.click( '.lstab-pane-tab[data-lstab-goto="look"]' );
	await admin.evaluate( helpers( '#lstab-preview-stage .lstab' ) );

	// What the preview cannot show: a second copy, another table, the page
	// round it, and what a visitor does to the table.
	const pageOnly = /same table twice|reaching the other table|search:|sorting:|keyboard|focus-within|right to left|details row|body, html|@scope cannot|escaping/;

	for ( const [ name, css, fn, opts = {} ] of cases ) {
		if ( pageOnly.test( name ) || opts.media || opts.width || opts.php ) {
			continue;
		}
		await admin.evaluate( () => {
			const sheet = document.querySelector( 'style.lstab-live-css' );
			if ( sheet ) {
				sheet.textContent = '';
				sheet.removeAttribute( 'data-lstab-css' );
			}
		} );
		await admin.fill( '#lstab-custom-css', css );
		await admin.locator( '#lstab-custom-css' ).dispatchEvent( 'change' );

		let result = [ false, '' ];
		try {
			await admin.waitForFunction( () => ( document.querySelector( 'style.lstab-live-css' )?.textContent || '' ).length > 0, null, { timeout: 5000 } ).catch( () => {} );
			// The checks that read the printed rules look for the table's own
			// style block; in the preview the live sheet stands in for it.
			await admin.evaluate( ( id ) => document.querySelector( 'style.lstab-live-css' )?.setAttribute( 'data-lstab-css', String( id ) ), HOURS );
			await admin.waitForTimeout( 150 );
			result = await admin.evaluate( `(${ fn.toString() })()` );
		} catch ( e ) {
			result = [ false, 'ERROR ' + e.message.split( '\n' )[ 0 ] ];
		}
		check( result[ 0 ], 'preview: ' + name, result[ 1 ] );
	}

	console.log( '\nIn the block editor' );

	setCss( 'td[data-label="Day"] { color: rgb(1,2,3); } tbody tr:nth-child(2) > td { background-color: rgb(4,5,6); }' );
	await admin.goto( `${ BASE }/wp-admin/post.php?post=${ made.page }&action=edit` );
	{
		const canvas = admin.frameLocator( 'iframe[name="editor-canvas"]' );
		let colour = '';
		let pinned = '';
		try {
			const day = canvas.locator( `[data-lstab-id="${ HOURS }"] td[data-label="Day"]` ).first();
			await day.waitFor( { timeout: 30000 } );
			colour = await day.evaluate( ( el ) => getComputedStyle( el ).color );
			pinned = await canvas.locator( `[data-lstab-id="${ HOURS }"] tbody tr:nth-child(2) > td:first-child` ).first().evaluate( ( el ) => getComputedStyle( el, '::before' ).backgroundColor );
		} catch ( e ) {
			colour = 'ERROR ' + e.message.split( '\n' )[ 0 ];
		}
		check( 'rgb(1, 2, 3)' === colour, 'the block shows the table with its own CSS', colour );
		check( 'rgb(4, 5, 6)' === pinned, 'a background from it reaches the pinned cell there too', pinned );
	}
} finally {
	await browser.close();
	console.log( harness( 'drop' ) );
	fs.rmSync( scratch, { recursive: true, force: true } );
}

console.log( `\n  ${ passed } passed, ${ failed } failed` );
process.exit( failed ? 1 : 0 );
