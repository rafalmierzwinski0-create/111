/**
 * Takes the ten screenshots of the WordPress.org listing, in the order of the
 * captions under "== Screenshots ==" in readme.txt, from a throwaway test site
 * seeded with the English demo shop (tools/wporg-shots/seed.php).
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
import path from 'path';
import { fileURLToPath } from 'url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const [ root, BASE ] = process.argv.slice( 2 );
const OUT = process.argv[ 4 ] || path.join( here, '../../wporg-assets' );
const SHEET = 'https://docs.google.com/spreadsheets/d/1Tr4ilS1deB1kesPr1ceL1st2026xYz0aBcDeFgHiJk/edit#gid=0';

if ( ! root || ! BASE ) {
	console.error( 'Usage: node tools/wporg-shots/shoot.mjs <wp root> <base url> [out dir]' );
	process.exit( 2 );
}

const php = ( code ) => execFileSync( 'php', [ '-r', `define( 'WP_USE_THEMES', false ); require '${ root }/wp-load.php'; ${ code }` ] ).toString();
const mock = ( mode ) => fs.writeFileSync( `${ root }/wp-content/lstab-mock-state.json`, JSON.stringify( { mode, tab: 'main' } ) );

const seeded = JSON.parse( execFileSync( 'php', [ path.join( here, 'seed.php' ), root ] ).toString() );
const [ prices ] = seeded.tables;

const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );

const desk = await browser.newContext( { viewport: { width: 1280, height: 860 }, deviceScaleFactor: 2 } );
const admin = await desk.newPage();
await admin.goto( BASE + '/wp-login.php' );
await admin.fill( '#user_login', 'admin' );
await admin.fill( '#user_pass', 'admin123' );
await Promise.all( [ admin.waitForNavigation(), admin.click( '#wp-submit' ) ] );

const visitor = await ( await browser.newContext( { viewport: { width: 1280, height: 860 }, deviceScaleFactor: 2 } ) ).newPage();
const phone = await ( await browser.newContext( { viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true } ) ).newPage();

const save = async ( page, n, options = {} ) => {
	const size = page.viewportSize();
	await page.mouse.move( size.width - 2, size.height - 2 ).catch( () => {} );
	await page.waitForTimeout( 400 );
	await page.screenshot( { path: path.join( OUT, `screenshot-${ n }.png` ), ...options } );
	console.log( `  screenshot-${ n }.png` );
};

// Everything from the top of the screen down to a little past an element.
const downTo = async ( page, selector, extra ) => {
	const box = await page.locator( selector ).first().boundingBox();
	const height = Math.ceil( box.y + Math.min( box.height, extra ) + 24 );
	await page.setViewportSize( { width: 1280, height } );
	await page.waitForTimeout( 300 );
};

const editScreen = ( id ) => `${ BASE }/wp-admin/admin.php?page=live-sheets-table-edit${ id ? '&source=' + id : '' }`;
const previewTable = '#lstab-preview-stage .lstab-table tbody tr';

// 1. Adding a table: the link is pasted and the table is there before saving.
await admin.goto( editScreen( 0 ) );
await admin.fill( '#lstab-sheet-url', SHEET );
await admin.click( '#lstab-preview-button' );
await admin.waitForSelector( previewTable );
await admin.waitForTimeout( 600 );
await downTo( admin, '#lstab-preview-stage', 380 );
await save( admin, 1 );

// 2. The list of tables.
await admin.setViewportSize( { width: 1280, height: 860 } );
await admin.goto( BASE + '/wp-admin/admin.php?page=live-sheets-table-sources' );
await downTo( admin, '.lstab-src-list', 4000 );
await save( admin, 2 );

// 3. Switching to another tab of the same spreadsheet.
await admin.setViewportSize( { width: 1280, height: 860 } );
await admin.goto( editScreen( prices ) );
await admin.waitForSelector( previewTable );
await admin.waitForTimeout( 800 );
await admin.selectOption( '#lstab-tabs', { label: 'Service menu' } );
await admin.waitForFunction( () => /Safety check/.test( document.querySelector( '#lstab-preview-stage' ).textContent ) );
await admin.waitForTimeout( 600 );
await admin.locator( '#lstab-tabs' ).focus();
await downTo( admin, '#lstab-preview-stage', 380 );
await save( admin, 3 );

// 4. The published table on a desktop screen, as a visitor sees it.
const toTable = async ( page, above ) => {
	await page.evaluate( ( gap ) => {
		const top = document.querySelector( '.lstab' ).getBoundingClientRect().top + window.scrollY;
		window.scrollTo( 0, Math.max( 0, top - gap ) );
	}, above );
	await page.waitForTimeout( 500 );
};
await visitor.goto( seeded.url );
await visitor.waitForSelector( '.lstab-table' );
await toTable( visitor, 250 );
await save( visitor, 4 );

// 5. The same table on a phone, moved a little sideways.
await phone.goto( seeded.url );
await phone.waitForSelector( '.lstab-table' );
await toTable( phone, 120 );
await phone.evaluate( () => {
	const scroller = document.querySelector( '.lstab-scroll' );
	scroller.scrollLeft = 140;
	scroller.dispatchEvent( new Event( 'scroll' ) );
} );
await save( phone, 5 );

// 6. Searching.
await visitor.goto( seeded.url );
await visitor.waitForSelector( '.lstab-table' );
await visitor.locator( '.lstab-search-input' ).first().pressSequentially( 'helmet', { delay: 60 } );
await visitor.waitForTimeout( 800 );
await toTable( visitor, 250 );
await save( visitor, 6 );

// 7. Sorting by price: $18 before $139 before $2,149.
await visitor.goto( seeded.url );
await visitor.waitForSelector( '.lstab-table' );
await visitor.locator( '.lstab-table thead .lstab-sort', { hasText: 'Price' } ).first().click();
await visitor.waitForTimeout( 800 );
await toTable( visitor, 250 );
await save( visitor, 7 );

// 8. Google stops answering: the dashboard says so, and what to do.
mock( 'demo_403' );
php( `global $wpdb; $wpdb->update( LSTAB_Storage::table(), array( 'last_success_gmt' => gmdate( 'Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS ) ), array( 'id' => ${ prices } ) ); LSTAB_Sync::run( ${ prices } );` );
await admin.setViewportSize( { width: 1280, height: 860 } );
await admin.goto( BASE + '/wp-admin/admin.php?page=live-sheets-table-sources' );
await admin.waitForTimeout( 500 );
await save( admin, 8, { clip: { x: 0, y: 0, width: 1280, height: 640 } } );

// 9. Meanwhile the public page still shows the last good copy, all of it.
await visitor.setViewportSize( { width: 1280, height: 1600 } );
await visitor.goto( seeded.url );
await visitor.waitForSelector( '.lstab-table' );
await toTable( visitor, 40 );
await save( visitor, 9 );
mock( 'demo' );
php( `LSTAB_Sync::run( ${ prices } );` );

// 10. The block in the editor, selected, with its settings beside it.
const editor = await ( await browser.newContext( { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, storageState: await desk.storageState() } ) ).newPage();
await editor.goto( `${ BASE }/wp-admin/post.php?post=${ seeded.page }&action=edit` );
const canvas = editor.frameLocator( 'iframe[name="editor-canvas"]' );
await canvas.locator( '.lstab-table' ).first().waitFor( { timeout: 30000 } );
await editor.waitForTimeout( 800 );
await canvas.locator( '.wp-block-live-sheets-table-sheet-table' ).first().click( { position: { x: 30, y: 10 } } );
await editor.waitForTimeout( 1200 );
await save( editor, 10 );

await browser.close();
