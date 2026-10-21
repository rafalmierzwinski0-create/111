/**
 * Takes the ten screenshots of the WordPress.org listing, in the order of the
 * captions under "== Screenshots ==" in readme.txt, from a throwaway test site
 * seeded with two demo businesses (tools/wporg-shots/seed.php), laid out by
 * tools/wporg-shots/layout.mjs and framed by tools/wporg-shots/frames.mjs.
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
import { plants, walks, gigs, styles, build } from './layout.mjs';
import { browserWindow, twoPhones, phoneViewport, styleGrid, render } from './frames.mjs';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const [ root, BASE ] = process.argv.slice( 2 );
const OUT = process.argv[ 4 ] || path.join( here, '../../wporg-assets' );
const SHEET = 'https://docs.google.com/spreadsheets/d/1FernH0useSh0pSheet2026xYz0aBcDeFgHiJkLmN/edit#gid=0';
const work = fs.mkdtempSync( path.join( os.tmpdir(), 'lstab-shots-' ) );

if ( ! root || ! BASE ) {
	console.error( 'Usage: node tools/wporg-shots/shoot.mjs <wp root> <base url> [out dir]' );
	process.exit( 2 );
}

const php = ( code ) => execFileSync( 'php', [ '-r', `define( 'WP_USE_THEMES', false ); require '${ root }/wp-load.php'; ${ code }` ] ).toString();
const mock = ( mode ) => fs.writeFileSync( `${ root }/wp-content/lstab-mock-state.json`, JSON.stringify( { mode, tab: 'main' } ) );
const raw = ( name ) => path.join( work, `${ name }.png` );
const final = ( n ) => path.join( OUT, `screenshot-${ n }.png` );

const BG = {
	venue: 'radial-gradient(1400px 900px at 90% 0%, rgba(255,138,92,0.35) 0%, rgba(255,138,92,0) 60%), radial-gradient(1300px 1000px at 0% 100%, rgba(124,92,255,0.30) 0%, rgba(124,92,255,0) 60%), linear-gradient(160deg, #0d0f15, #1a1d27)',
	fern: 'radial-gradient(1400px 900px at 8% 0%, #fbf8f0 0%, rgba(251,248,240,0) 62%), radial-gradient(1300px 1000px at 100% 100%, #c9dcc4 0%, rgba(201,220,196,0) 62%), #e6ede1',
	walks: 'radial-gradient(1300px 900px at 88% 0%, rgba(196,146,60,0.38) 0%, rgba(196,146,60,0) 62%), radial-gradient(1200px 900px at 0% 100%, rgba(80,110,150,0.35) 0%, rgba(80,110,150,0) 60%), linear-gradient(160deg, #121a25, #22303f)',
	phones: 'radial-gradient(1200px 900px at 20% 10%, #fdf9f1 0%, rgba(253,249,241,0) 60%), radial-gradient(1200px 1000px at 90% 100%, #d6c7ab 0%, rgba(214,199,171,0) 60%), #efe7da',
	styles: 'radial-gradient(1400px 900px at 0% 0%, #ffffff 0%, rgba(255,255,255,0) 60%), radial-gradient(1400px 1000px at 100% 100%, #d7e3ef 0%, rgba(215,227,239,0) 60%), #e9eef4',
	admin: 'radial-gradient(1400px 900px at 0% 0%, #f4fbf9 0%, rgba(244,251,249,0) 60%), radial-gradient(1400px 1000px at 100% 100%, #c6e3dc 0%, rgba(198,227,220,0) 60%), #e0efeb',
};

const seeded = JSON.parse( execFileSync( 'php', [ path.join( here, 'seed.php' ), root ] ).toString() );
const T = seeded.tables;

const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
const desk = await browser.newContext( { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 } );
const admin = await desk.newPage();
await admin.goto( BASE + '/wp-login.php' );
await admin.fill( '#user_login', 'admin' );
await admin.fill( '#user_pass', 'admin123' );
await Promise.all( [ admin.waitForURL( /wp-admin/ ), admin.click( '#wp-submit' ) ] );

await build( admin, BASE, seeded.pages.plants, plants( T, BASE ) );
await build( admin, BASE, seeded.pages.walks, walks( T, BASE ) );
await build( admin, BASE, seeded.pages.gigs, gigs( T ) );
await build( admin, BASE, seeded.pages.styles, styles( T ) );

const visitor = await ( await browser.newContext( { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 } ) ).newPage();
const phoneTall = phoneViewport();
const phoneContext = { viewport: { width: 390, height: phoneTall }, deviceScaleFactor: 3, isMobile: true, hasTouch: true };
const framer = await ( await browser.newContext( { deviceScaleFactor: 1 } ) ).newPage();

const calm = async ( page ) => {
	const size = page.viewportSize();
	await page.mouse.move( size.width - 2, size.height - 2 ).catch( () => {} );
	await page.waitForTimeout( 500 );
};

const shoot = async ( page, name, options = {} ) => {
	await calm( page );
	await page.screenshot( { path: raw( name ), ...options } );
	return raw( name );
};

const sizeOf = ( file ) => {
	const b = fs.readFileSync( file );
	return { width: b.readUInt32BE( 16 ), height: b.readUInt32BE( 20 ) };
};

// A window that fits its screenshot, with the background round it.
const framed = async ( n, image, url, background, extra = {} ) => {
	const side = extra.side || 200;
	const top = extra.top || 110;
	const { width, height } = sizeOf( image );
	const shown = Math.round( height * ( 2560 - side * 2 ) / width );
	const frame = browserWindow( { image, url, background, side, top, height: extra.height || top + 72 + shown + 110 } );
	await render( framer, frame, final( n ), work );
	console.log( `  screenshot-${ n }.png` );
};

// The page from just above one element to just below another, measured from
// the top so that the admin bar and menu stay where they belong.
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

// 1. The music venue's listings, in the dark Midnight style.
await visitor.setViewportSize( { width: 1440, height: 1500 } );
await visitor.goto( seeded.urls.gigs, { waitUntil: 'networkidle' } );
await framed( 1, await shoot( visitor, 'gigs' ), 'yoursite.com/whats-on', BG.venue, { height: 2200 } );

// 2. The walking tours' departures.
await visitor.setViewportSize( { width: 1440, height: 1300 } );
await visitor.goto( seeded.urls.walks, { waitUntil: 'networkidle' } );
await framed( 2, await shoot( visitor, 'walks' ), 'yoursite.com/dates', BG.walks, { height: 2200 } );

// 3. Phones: the price list with its slider, and the departures as cards.
const phoneA = await ( await browser.newContext( phoneContext ) ).newPage();
await phoneA.goto( seeded.urls.plants, { waitUntil: 'networkidle' } );
await phoneA.evaluate( () => {
	const head = [ ...document.querySelectorAll( 'h2' ) ].find( ( h ) => /greenhouse/.test( h.textContent ) );
	window.scrollTo( 0, head.getBoundingClientRect().top + window.scrollY - 28 );
	const scroller = document.querySelector( '.lstab-scroll' );
	const heads = scroller.querySelectorAll( 'thead th' );
	scroller.scrollLeft = heads[ 2 ].offsetLeft - heads[ 0 ].offsetWidth;
	scroller.dispatchEvent( new Event( 'scroll' ) );
} );
const phoneB = await ( await browser.newContext( phoneContext ) ).newPage();
await phoneB.goto( seeded.urls.walks, { waitUntil: 'networkidle' } );
await phoneB.evaluate( () => {
	const head = [ ...document.querySelectorAll( 'h2' ) ].find( ( h ) => /departures/.test( h.textContent ) );
	window.scrollTo( 0, head.getBoundingClientRect().top + window.scrollY - 28 );
} );
await render( framer, twoPhones( {
	left: { image: await shoot( phoneA, 'phone-plants' ), url: 'yoursite.com' },
	right: { image: await shoot( phoneB, 'phone-walks' ), url: 'yoursite.com' },
	background: BG.phones,
} ), final( 3 ), work );
console.log( '  screenshot-3.png' );

// 4. The four styles, each with a different tab of the same spreadsheet.
await visitor.setViewportSize( { width: 1440, height: 900 } );
await visitor.goto( seeded.urls.styles, { waitUntil: 'networkidle' } );
const blocks = visitor.locator( '.wp-block-live-sheets-table-sheet-table' );
const cards = [];
const about = [
	[ 'Clean', 'Thin lines and plenty of space, in your theme’s fonts.' ],
	[ 'Striped', 'Every other row shaded, for long lists.' ],
	[ 'Bordered', 'Lines round every cell, for tables of numbers.' ],
	[ 'Midnight', 'Light text on a dark table, for dark designs.' ],
];
for ( let i = 0; i < 4; i++ ) {
	await calm( visitor );
	const image = raw( `style-${ i }` );
	await blocks.nth( i ).screenshot( { path: image } );
	cards.push( { name: about[ i ][ 0 ], tag: 'Free', line: about[ i ][ 1 ], image, ...sizeOf( image ) } );
}
await render( framer, styleGrid( { cards, background: BG.styles } ), final( 4 ), work );
console.log( '  screenshot-4.png' );

// 5. Search and sort together: plants for bright rooms, cheapest first.
await visitor.setViewportSize( { width: 1440, height: 1000 } );
await visitor.goto( seeded.urls.plants, { waitUntil: 'networkidle' } );
await visitor.locator( '.lstab-search-input' ).first().pressSequentially( 'bright', { delay: 50 } );
await visitor.waitForTimeout( 600 );
await visitor.locator( '.lstab-table thead .lstab-sort', { hasText: 'Price' } ).first().click();
await visitor.waitForTimeout( 600 );
await visitor.evaluate( () => {
	const head = [ ...document.querySelectorAll( 'h2' ) ].find( ( h ) => /greenhouse/.test( h.textContent ) );
	window.scrollTo( 0, head.getBoundingClientRect().top + window.scrollY - 56 );
} );
await framed( 5, await shoot( visitor, 'search' ), 'yoursite.com/price-list', BG.fern );

// 6. Adding a table: the link is pasted and the table is there before saving.
await admin.goto( editScreen( 0 ) );
await admin.fill( '#lstab-sheet-url', SHEET );
await admin.click( '#lstab-preview-button' );
await admin.waitForSelector( previewRows );
await admin.fill( '#lstab-title', 'Plant price list' );
await admin.evaluate( () => document.activeElement.blur() );
await admin.waitForTimeout( 800 );
await framed( 6, await shoot( admin, 'add', await between( admin, '#wpadminbar', `${ previewRows }:nth-child(5)`, 0, 10 ) ), 'yoursite.com/wp-admin', BG.admin );

// 7. Styles and colours, on the price list's own settings.
await admin.goto( editScreen( T.plants ), { waitUntil: 'networkidle' } );
await admin.waitForSelector( previewRows );
await admin.locator( '.lstab-pane-tab[data-lstab-goto="look"]' ).click();
await admin.waitForTimeout( 800 );
await framed( 7, await shoot( admin, 'look', await between( admin, 'h3.lstab-subhead:has-text("Table style")', '.lstab-appearance', 24, 24, MENU ) ), 'yoursite.com/wp-admin', BG.admin );

// 8. Columns: one renamed, one tucked into the details.
await admin.locator( '.lstab-pane-tab[data-lstab-goto="hide"]' ).click();
await admin.waitForTimeout( 500 );
await admin.locator( '.lstab-columns-card input[type="text"]' ).nth( 1 ).fill( 'Best spot' );
await admin.locator( 'button[data-lstab-pick-detail="2"]' ).click();
await admin.waitForTimeout( 900 );
await framed( 8, await shoot( admin, 'columns', await between( admin, '.lstab-columns-card', '#lstab-picker tbody tr:nth-child(6)', 24, 12, MENU ) ), 'yoursite.com/wp-admin', BG.admin );

// 9. The list of tables while Google refuses one of them.
mock( 'demo_403' );
php( `global $wpdb; $wpdb->update( LSTAB_Storage::table(), array( 'last_success_gmt' => gmdate( 'Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS ) ), array( 'id' => ${ T.plants } ) ); LSTAB_Sync::run( ${ T.plants } );` );
await admin.setViewportSize( { width: 1440, height: 1060 } );
await admin.goto( BASE + '/wp-admin/admin.php?page=live-sheets-table-sources', { waitUntil: 'networkidle' } );
await framed( 9, await shoot( admin, 'list' ), 'yoursite.com/wp-admin', BG.admin );
mock( 'demo' );
php( `LSTAB_Sync::run( ${ T.plants } );` );

// 10. The block in the editor, selected, with its settings beside it.
const editor = await ( await browser.newContext( { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, storageState: await desk.storageState() } ) ).newPage();
await editor.goto( `${ BASE }/wp-admin/post.php?post=${ seeded.pages.plants }&action=edit` );
await editor.waitForFunction( () => window.wp && window.wp.data && window.wp.data.select( 'core/editor' ).getCurrentPostId() );
await editor.evaluate( () => window.wp.data.dispatch( 'core/editor' ).setRenderingMode && window.wp.data.dispatch( 'core/editor' ).setRenderingMode( 'post-only' ) );
const canvas = editor.frameLocator( 'iframe[name="editor-canvas"]' );
const block = canvas.locator( '.wp-block-live-sheets-table-sheet-table' ).first();
await canvas.locator( '.lstab-table' ).first().waitFor( { timeout: 30000 } );
await block.scrollIntoViewIfNeeded();
await block.click( { position: { x: 30, y: 10 } } );
await editor.waitForTimeout( 600 );
await canvas.locator( 'h2' ).first().evaluate( ( h ) => {
	h.scrollIntoView( { block: 'start' } );
	h.ownerDocument.defaultView.scrollBy( 0, -60 );
} );
await editor.waitForTimeout( 1200 );
await framed( 10, await shoot( editor, 'editor' ), 'yoursite.com/wp-admin', BG.admin );

await browser.close();
fs.rmSync( work, { recursive: true, force: true } );
