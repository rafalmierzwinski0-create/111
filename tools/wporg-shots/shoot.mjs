/**
 * Takes the ten screenshots of the WordPress.org listing, in the order of the
 * captions under "== Screenshots ==" in readme.txt, from a throwaway test site
 * seeded with the English demo shop (tools/wporg-shots/seed.php) and laid out
 * by tools/wporg-shots/layout.mjs.
 *
 * Usage: node tools/wporg-shots/shoot.mjs <wp root> <base url> [out dir]
 *   e.g. node tools/wporg-shots/shoot.mjs /tmp/lstab-env/wpshots http://127.0.0.1:8091
 *
 * The site needs the Google mock from tests/mock-google-mu-plugin.php, and an
 * admin user "admin" with the password "admin123", as tests/setup-env.sh makes.
 */
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import { execFileSync } from 'child_process';
import fs from 'fs';
import os from 'os';
import path from 'path';
import { fileURLToPath } from 'url';
import { prices, visit, build } from './layout.mjs';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const [ root, BASE ] = process.argv.slice( 2 );
const OUT = process.argv[ 4 ] || path.join( here, '../../wporg-assets' );
const SHEET = 'https://docs.google.com/spreadsheets/d/1Tr4ilS1deB1kesPr1ceL1st2026xYz0aBcDeFgHiJk/edit#gid=0';
const work = fs.mkdtempSync( path.join( os.tmpdir(), 'lstab-shots-' ) );

if ( ! root || ! BASE ) {
	console.error( 'Usage: node tools/wporg-shots/shoot.mjs <wp root> <base url> [out dir]' );
	process.exit( 2 );
}

const php = ( code ) => execFileSync( 'php', [ '-r', `define( 'WP_USE_THEMES', false ); require '${ root }/wp-load.php'; ${ code }` ] ).toString();
const mock = ( mode ) => fs.writeFileSync( `${ root }/wp-content/lstab-mock-state.json`, JSON.stringify( { mode, tab: 'main' } ) );

const seeded = JSON.parse( execFileSync( 'php', [ path.join( here, 'seed.php' ), root ] ).toString() );
const T = seeded.tables;

const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
const desk = await browser.newContext( { viewport: { width: 1280, height: 860 }, deviceScaleFactor: 2 } );
const admin = await desk.newPage();
await admin.goto( BASE + '/wp-login.php' );
await admin.fill( '#user_login', 'admin' );
await admin.fill( '#user_pass', 'admin123' );
await Promise.all( [ admin.waitForURL( /wp-admin/ ), admin.click( '#wp-submit' ) ] );

await build( admin, BASE, seeded.pages.prices, prices( T ) );
await build( admin, BASE, seeded.pages.visit, visit( T ) );

const visitor = await ( await browser.newContext( { viewport: { width: 1280, height: 1300 }, deviceScaleFactor: 2 } ) ).newPage();
const phoneContext = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true };

const calm = async ( page ) => {
	const size = page.viewportSize();
	await page.mouse.move( size.width - 2, size.height - 2 ).catch( () => {} );
	await page.waitForTimeout( 500 );
};

const save = async ( page, n, options = {} ) => {
	await calm( page );
	await page.screenshot( { path: path.join( OUT, `screenshot-${ n }.png` ), ...options } );
	console.log( `  screenshot-${ n }.png` );
};

// The page from just above one element to just below another, measured
// from the top so that the admin bar and menu stay where they belong.
const between = async ( page, from, to, above = 32, below = 32, left = 0 ) => {
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	await page.waitForTimeout( 200 );
	const a = await page.locator( from ).first().boundingBox();
	const b = await page.locator( to ).first().boundingBox();
	const y = Math.max( 0, a.y - above );
	return { fullPage: true, clip: { x: left, y, width: page.viewportSize().width - left, height: b.y + b.height + below - y } };
};
const MENU = 160;

const editScreen = ( id ) => `${ BASE }/wp-admin/admin.php?page=live-sheets-table-edit${ id ? '&source=' + id : '' }`;
const previewRows = '#lstab-preview-stage .lstab-table tbody tr';

// 1. The price list on the shop's page.
await visitor.goto( seeded.urls.prices, { waitUntil: 'networkidle' } );
await save( visitor, 1 );

// 2. The four styles, each a different tab of the same sheet.
await visitor.goto( seeded.urls.visit, { waitUntil: 'networkidle' } );
await save( visitor, 2, await between( visitor, 'h3:has-text("Opening hours")', '.lstab-layout-cards', 40, 48 ) );

// 3. Phones: the price list with its slider, and the shops as cards.
const phoneA = await ( await browser.newContext( phoneContext ) ).newPage();
await phoneA.goto( seeded.urls.prices, { waitUntil: 'networkidle' } );
await phoneA.evaluate( () => {
	const head = document.querySelector( 'h2' );
	window.scrollTo( 0, head.getBoundingClientRect().top + window.scrollY - 24 );
	const scroller = document.querySelector( '.lstab-scroll' );
	const heads = scroller.querySelectorAll( 'thead th' );
	scroller.scrollLeft = heads[ 2 ].offsetLeft - heads[ 0 ].offsetWidth;
	scroller.dispatchEvent( new Event( 'scroll' ) );
} );
await calm( phoneA );
await phoneA.screenshot( { path: path.join( work, 'phone-a.png' ) } );
const phoneB = await ( await browser.newContext( phoneContext ) ).newPage();
await phoneB.goto( seeded.urls.visit, { waitUntil: 'networkidle' } );
await phoneB.evaluate( () => {
	const head = [ ...document.querySelectorAll( 'h3' ) ].find( ( h ) => /Our shops/.test( h.textContent ) );
	window.scrollTo( 0, head.getBoundingClientRect().top + window.scrollY - 24 );
} );
await calm( phoneB );
await phoneB.screenshot( { path: path.join( work, 'phone-b.png' ) } );
execFileSync( 'python3', [ '-I', path.join( here, 'phones.py' ), path.join( work, 'phone-a.png' ), path.join( work, 'phone-b.png' ), path.join( OUT, 'screenshot-3.png' ) ] );
console.log( '  screenshot-3.png' );

// 4. Search and sort together: helmets, cheapest first.
await visitor.goto( seeded.urls.prices, { waitUntil: 'networkidle' } );
await visitor.locator( '.lstab-search-input' ).first().pressSequentially( 'helmet', { delay: 50 } );
await visitor.waitForTimeout( 600 );
await visitor.locator( '.lstab-table thead .lstab-sort', { hasText: 'Price' } ).first().click();
await visitor.waitForTimeout( 600 );
await save( visitor, 4, await between( visitor, 'h2:has-text("Today")', '.lstab-meta', 40, 40 ) );

// 5. Adding a table: the link is pasted and the table is there before saving.
await admin.goto( editScreen( 0 ) );
await admin.fill( '#lstab-sheet-url', SHEET );
await admin.click( '#lstab-preview-button' );
await admin.waitForSelector( previewRows );
await admin.fill( '#lstab-title', 'Bikes & gear prices' );
await admin.evaluate( () => document.activeElement.blur() );
await admin.waitForTimeout( 800 );
await save( admin, 5, await between( admin, '#wpadminbar', `${ previewRows }:nth-child(6)`, 0, 8 ) );

// 6. Styles and colours, on the price list's own settings.
await admin.goto( editScreen( T.prices ), { waitUntil: 'networkidle' } );
await admin.waitForSelector( previewRows );
await admin.locator( '.lstab-pane-tab[data-lstab-goto="look"]' ).click();
await admin.waitForTimeout( 800 );
await save( admin, 6, await between( admin, 'h3.lstab-subhead:has-text("Table style")', '.lstab-appearance', 28, 24, MENU ) );

// 7. Columns: one renamed, one tucked into the details.
await admin.locator( '.lstab-pane-tab[data-lstab-goto="hide"]' ).click();
await admin.waitForTimeout( 500 );
await admin.locator( '.lstab-columns-card input[type="text"]' ).nth( 4 ).fill( 'Stock' );
await admin.locator( 'button[data-lstab-pick-detail="2"]' ).click();
await admin.waitForTimeout( 900 );
await save( admin, 7, await between( admin, '.lstab-columns-card', '#lstab-picker tbody tr:nth-child(7)', 24, 12, MENU ) );

// 8. The list of tables.
await admin.goto( BASE + '/wp-admin/admin.php?page=live-sheets-table-sources', { waitUntil: 'networkidle' } );
await save( admin, 8, await between( admin, '#wpadminbar', '.lstab-src:nth-of-type(3)', 0, 16 ) );

// 9. Google stops answering: the dashboard says so, and what to do.
mock( 'demo_403' );
php( `global $wpdb; $wpdb->update( LSTAB_Storage::table(), array( 'last_success_gmt' => gmdate( 'Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS ) ), array( 'id' => ${ T.prices } ) ); LSTAB_Sync::run( ${ T.prices } );` );
await admin.goto( BASE + '/wp-admin/admin.php?page=live-sheets-table-sources', { waitUntil: 'networkidle' } );
await save( admin, 9, await between( admin, '#wpadminbar', '.lstab-src:nth-of-type(2)', 0, 16 ) );
mock( 'demo' );
php( `LSTAB_Sync::run( ${ T.prices } );` );

// 10. The block in the editor, selected, with its settings beside it.
const editor = await ( await browser.newContext( { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, storageState: await desk.storageState() } ) ).newPage();
await editor.goto( `${ BASE }/wp-admin/post.php?post=${ seeded.pages.prices }&action=edit` );
const canvas = editor.frameLocator( 'iframe[name="editor-canvas"]' );
const block = canvas.locator( '.wp-block-live-sheets-table-sheet-table' ).first();
await canvas.locator( '.lstab-table' ).first().waitFor( { timeout: 30000 } );
await block.scrollIntoViewIfNeeded();
await block.click( { position: { x: 30, y: 10 } } );
await editor.waitForTimeout( 600 );
await canvas.locator( 'h2' ).first().evaluate( ( h ) => {
	h.scrollIntoView( { block: 'start' } );
	h.ownerDocument.defaultView.scrollBy( 0, -120 );
} );
await editor.waitForTimeout( 1200 );
await save( editor, 10 );

await browser.close();
fs.rmSync( work, { recursive: true, force: true } );
