/**
 * Browser-driven end-to-end test + screenshot capture.
 */
import { chromium } from 'playwright';
import fs from 'node:fs';

const BASE = process.env.LSTAB_BASE || 'http://127.0.0.1:8089';
const SHOTS = process.env.LSTAB_SHOTS || new URL( '../../screenshots', import.meta.url ).pathname;
const SCRATCH = process.env.LSTAB_SCRATCH || '/tmp/lstab-env';
const MOCK_STATE = `${ SCRATCH }/wp71/wp-content/lstab-mock-state.json`;
const CHROMIUM = process.env.LSTAB_CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
fs.mkdirSync( SHOTS, { recursive: true } );

const setMock = ( mode, tab = 'main' ) =>
	fs.writeFileSync( MOCK_STATE, JSON.stringify( { mode, tab } ) );

// Start every run from a known-good sheet.
setMock( 'ok' );

let pass = 0, fail = 0;
const check = ( ok, label, detail = '' ) => {
	if ( ok ) { pass++; console.log( `  \x1b[32mPASS\x1b[0m  ${ label }` ); }
	else { fail++; console.log( `  \x1b[31mFAIL\x1b[0m  ${ label }${ detail ? '\n        ' + detail : '' }` ); }
};
const section = ( t ) => console.log( `\n\x1b[1m${ t }\x1b[0m` );

const browser = await chromium.launch( { executablePath: CHROMIUM, args: [ '--no-sandbox' ] } );
const context = await browser.newContext( { viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 2, locale: 'pl-PL' } );

const consoleErrors = [];
context.on( 'weberror', ( e ) => consoleErrors.push( String( e.error() ) ) );

const page = await context.newPage();
page.on( 'console', ( m ) => { if ( m.type() === 'error' ) consoleErrors.push( m.text() ); } );

// --------------------------------------------------------------- login
section( '1. Admin login' );
await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'networkidle' } );
await page.fill( '#user_login', 'admin' );
await page.fill( '#user_pass', 'admin123' );
await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] );
check( page.url().includes( 'wp-admin' ), 'Logged into the dashboard' );

// ----------------------------------------------------- source list screen
section( '2. Source list screen' );
await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table`, { waitUntil: 'networkidle' } );

// Sync once so the run does not inherit a previous run's outage state.
await page.locator( 'button:has-text("Refresh now")' ).first().click();
await page.waitForLoadState( 'networkidle' );

const statusText = await page.locator( '.lstab-status' ).first().innerText();
check( /Last sync OK/.test( statusText ), 'List shows a green "last sync OK" status', statusText );
check( await page.locator( '.lstab-shortcode' ).first().isVisible(), 'Shortcode is shown for copy/paste' );
check( await page.locator( 'button:has-text("Refresh now")' ).first().isVisible(), '"Refresh now" button present' );
await page.screenshot( { path: `${ SHOTS }/02-source-list-ok.png`, fullPage: true } );

// ------------------------------------------------------- add/edit + preview
section( '3. Add source screen with live preview' );
// Read the saved source id straight off the list screen's shortcode.
const shortcode = await page.locator( '.lstab-shortcode' ).first().innerText();
const sourceId = Number( ( shortcode.match( /id="(\d+)"/ ) || [] )[ 1 ] || 0 );
check( sourceId > 0, `List screen exposes a usable shortcode (source #${ sourceId })`, shortcode );

// REST must refuse a cookie-authenticated call that carries no nonce.
const unauthorised = await page.evaluate( async ( base ) => {
	const res = await fetch( base + '/wp-json/live-sheets-table/v1/preview', {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify( { url: 'https://docs.google.com/spreadsheets/d/x/edit' } ),
	} );
	return res.status;
}, BASE );
check( unauthorised === 401 || unauthorised === 403, 'REST preview rejects a nonce-less request', String( unauthorised ) );
// That probe is meant to fail, so its console noise is not a plugin defect.
consoleErrors.length = 0;

await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table-edit&source=${ sourceId }`, { waitUntil: 'networkidle' } );
await page.waitForSelector( '.lstab-preview .lstab-table', { timeout: 15000 } );

const previewStatus = await page.locator( '#lstab-preview-status' ).innerText();
check( /Found 7 rows across 5 columns/.test( previewStatus ), 'Preview reports 7 rows × 5 columns', previewStatus );

const previewRows = await page.locator( '.lstab-preview tbody tr' ).count();
check( previewRows === 7, 'Preview renders all seven rows', String( previewRows ) );

check( await page.locator( '#lstab-tabs-wrap' ).isVisible(), 'Tab picker appeared for a multi-tab sheet' );
const tabOptions = await page.locator( '#lstab-tabs option' ).allInnerTexts();
check( tabOptions.length === 3 && tabOptions[ 0 ] === 'Cennik', 'Tab picker lists all three tabs', JSON.stringify( tabOptions ) );

// The sheet contains a <script> tag; it must be visible as text, never executed.
const xssCell = await page.locator( '.lstab-preview td', { hasText: 'alert(' } ).first().innerText();
check( xssCell.includes( "<script>alert('xss')</script>" ), 'Script tag from the sheet shows as literal text', xssCell );
check( await page.evaluate( () => ! window.__lstabXss ), 'No script from the sheet executed' );

await page.screenshot( { path: `${ SHOTS }/01-add-source-preview.png`, fullPage: true } );

// Switching tab re-fetches the preview.
section( '4. Switching sheet tab' );
await page.selectOption( '#lstab-tabs', '1734829105' );
await page.waitForFunction(
	() => document.querySelectorAll( '.lstab-preview tbody tr' ).length === 3,
	null,
	{ timeout: 15000 }
);
const tabHeaders = await page.locator( '.lstab-preview thead th' ).allInnerTexts();
check( tabHeaders.join( ',' ).includes( 'Miasto' ), 'Switching tab loads the other sheet', JSON.stringify( tabHeaders ) );
await page.screenshot( { path: `${ SHOTS }/03-tab-switch-preview.png`, fullPage: true } );

// Put it back on the first tab.
await page.selectOption( '#lstab-tabs', '0' );
await page.waitForFunction( () => document.querySelectorAll( '.lstab-preview tbody tr' ).length === 7, null, { timeout: 15000 } );

// --------------------------------------------------------- front-end desktop
section( '5. Front end — desktop' );
await page.goto( `${ BASE }/cennik/`, { waitUntil: 'networkidle' } );

const tables = await page.locator( '.lstab-table' ).count();
check( tables === 2, 'Both the block and the shortcode rendered a table', String( tables ) );

const rows = await page.locator( '.lstab-style-striped tbody tr' ).count();
check( rows === 7, 'Seven data rows on the page', String( rows ) );

const labelHidden = await page.locator( '.lstab-style-striped .lstab-cell-label' ).first().isVisible();
check( ! labelHidden, 'Stacked-layout labels are hidden on desktop' );

await page.screenshot( { path: `${ SHOTS }/04-frontend-desktop.png`, fullPage: true } );

// ----------------------------------------------------------------- search
section( '6. Search' );
await page.fill( '.lstab-style-striped .lstab-search-input', 'kask' );
await page.waitForTimeout( 300 );
let visible = await page.locator( '.lstab-style-striped tbody tr:not([hidden])' ).count();
check( visible === 1, 'Search narrows to one matching row', String( visible ) );
const countText = await page.locator( '.lstab-style-striped .lstab-count' ).innerText();
check( /1 of 7 rows/.test( countText ), 'Row counter updates', countText );
await page.screenshot( { path: `${ SHOTS }/06-frontend-search.png` } );

await page.fill( '.lstab-style-striped .lstab-search-input', 'nieistniejące' );
await page.waitForTimeout( 300 );
check( await page.locator( '.lstab-style-striped .lstab-no-results' ).isVisible(), 'Empty search shows the "no rows" message' );

await page.fill( '.lstab-style-striped .lstab-search-input', '' );
await page.waitForTimeout( 300 );
visible = await page.locator( '.lstab-style-striped tbody tr:not([hidden])' ).count();
check( visible === 7, 'Clearing the search restores every row', String( visible ) );

// ------------------------------------------------------------------ sorting
section( '7. Sorting' );
const firstColumn = async () =>
	page.locator( '.lstab-style-striped tbody tr:not([hidden]) td:first-child .lstab-cell-value' ).allInnerTexts();

const original = await firstColumn();
await page.locator( '.lstab-style-striped thead th' ).first().locator( 'button' ).click();
const ascending = await firstColumn();
check(
	JSON.stringify( ascending ) === JSON.stringify( [ ...ascending ].sort( ( a, b ) => a.localeCompare( b, 'pl' ) ) ),
	'Ascending text sort is correct',
	JSON.stringify( ascending )
);

await page.locator( '.lstab-style-striped thead th' ).first().locator( 'button' ).click();
const descending = await firstColumn();
check( JSON.stringify( descending ) === JSON.stringify( [ ...ascending ].reverse() ), 'Descending sort reverses it' );

await page.locator( '.lstab-style-striped thead th' ).first().locator( 'button' ).click();
check( JSON.stringify( await firstColumn() ) === JSON.stringify( original ), 'Third click restores the sheet order' );

// Numeric column: "1 215,50" must sort above "349,00", not below (string sort would fail).
const priceHeader = page.locator( '.lstab-style-striped thead th' ).nth( 1 ).locator( 'button' );
await priceHeader.click();
const prices = await page.locator( '.lstab-style-striped tbody tr:not([hidden]) td:nth-child(2) .lstab-cell-value' ).allInnerTexts();
const numeric = prices.map( ( p ) => parseFloat( p.replace( /[^\d,]/g, '' ).replace( ',', '.' ) ) );
check(
	numeric.every( ( v, i ) => i === 0 || numeric[ i - 1 ] <= v ),
	'Prices sort numerically, not alphabetically',
	JSON.stringify( prices )
);
await page.screenshot( { path: `${ SHOTS }/07-frontend-sorted.png` } );

// ------------------------------------------------------- front end — mobile
section( '8. Front end — mobile card layout' );
const mobile = await browser.newContext( {
	viewport: { width: 390, height: 900 },
	deviceScaleFactor: 3,
	isMobile: true,
	hasTouch: true,
	locale: 'pl-PL',
} );
const mpage = await mobile.newPage();
await mpage.goto( `${ BASE }/cennik/`, { waitUntil: 'networkidle' } );

const headBox = await mpage.locator( '.lstab-style-striped thead' ).boundingBox();
check(
	headBox !== null && headBox.height <= 2 && headBox.width <= 2,
	'Header row is visually collapsed on narrow screens (still in the a11y tree)',
	JSON.stringify( headBox )
);
check( await mpage.locator( '.lstab-style-striped .lstab-cell-label' ).first().isVisible(), 'Per-cell labels become visible (card layout)' );

// Below 440px the label goes on its own line so long headings never break mid-word.
const cellDisplay = await mpage.locator( '.lstab-style-striped tbody td' ).first().evaluate( ( el ) => getComputedStyle( el ).display );
check( cellDisplay === 'block', 'Narrow phones stack label above value', cellDisplay );

const longestLabel = await mpage.locator( '.lstab-style-striped .lstab-cell-label' ).last();
const labelBox = await longestLabel.boundingBox();
const labelLine = await longestLabel.evaluate( ( el ) => parseFloat( getComputedStyle( el ).lineHeight ) || el.offsetHeight );
check(
	labelBox.height < labelLine * 1.6,
	'Long labels such as "Zaktualizowano" stay on one line',
	`height ${ labelBox.height } vs line ${ labelLine }`
);

// A mid-width container (tablet, or a table in a content column) keeps the compact two-column card.
await mpage.setViewportSize( { width: 560, height: 900 } );
await mpage.waitForTimeout( 200 );
const midDisplay = await mpage.locator( '.lstab-style-striped tbody td' ).first().evaluate( ( el ) => getComputedStyle( el ).display );
check( midDisplay === 'grid', 'Mid-width screens use the compact label/value grid', midDisplay );
const captionBox = await mpage.locator( '.lstab-style-striped .lstab-caption' ).boundingBox();
const wrapBox = await mpage.locator( '.lstab-style-striped' ).boundingBox();
check(
	captionBox.width > wrapBox.width * 0.8,
	'Caption spans the table width in card mode',
	`caption ${ Math.round( captionBox.width ) } vs wrap ${ Math.round( wrapBox.width ) }`
);
await mpage.screenshot( { path: `${ SHOTS }/05b-frontend-tablet-cards.png`, fullPage: false } );
await mpage.setViewportSize( { width: 390, height: 900 } );
await mpage.waitForTimeout( 200 );

const bodyOverflow = await mpage.evaluate( () => ( {
	scroll: document.documentElement.scrollWidth,
	client: document.documentElement.clientWidth,
} ) );
check(
	bodyOverflow.scroll <= bodyOverflow.client + 1,
	'No horizontal page scrolling on mobile',
	JSON.stringify( bodyOverflow )
);

await mpage.screenshot( { path: `${ SHOTS }/05-frontend-mobile-cards.png`, fullPage: true } );

// ------------------------------------------------------- failure → fallback
section( '9. Sync failure — visitor still sees the last good copy' );
setMock( 'http_403' );

await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table`, { waitUntil: 'networkidle' } );
await Promise.all( [
	page.waitForURL( /page=live-sheets-table/ ),
	page.locator( 'button:has-text("Refresh now")' ).first().click(),
] );
await page.waitForLoadState( 'networkidle' );

const failedStatus = await page.locator( '.lstab-status' ).first().innerText();
check( /Sync error/.test( failedStatus ), 'Dashboard shows the sync error', failedStatus );
const detail = await page.locator( '.lstab-status-detail' ).first().innerText();
check( /403|Share/.test( detail ), 'Dashboard explains what to do about it', detail );
await page.screenshot( { path: `${ SHOTS }/08-source-list-error.png`, fullPage: true } );

// The public page must be unchanged.
const anon = await browser.newContext( { viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 2 } );
const apage = await anon.newPage();
await apage.goto( `${ BASE }/cennik/`, { waitUntil: 'networkidle' } );

const fallbackRows = await apage.locator( '.lstab-style-striped tbody tr' ).count();
check( fallbackRows === 7, 'Visitor still sees all seven rows after the failure', String( fallbackRows ) );
check( await apage.locator( 'text=Rower górski' ).first().isVisible(), 'Last good data still on the page' );
const bodyText = await apage.locator( 'body' ).innerText();
check( ! /403|Sync error|lstab-notice/i.test( bodyText ), 'No error text leaked to the visitor' );
await apage.screenshot( { path: `${ SHOTS }/09-frontend-during-outage.png`, fullPage: true } );

// -------------------------------------------------------------- block editor
section( '10. Block editor' );
setMock( 'ok' );

const pageId = await page.evaluate( async ( base ) => {
	const res = await fetch( base + '/wp-json/wp/v2/pages?slug=cennik', { credentials: 'same-origin' } );
	const json = await res.json();
	return json.length ? json[ 0 ].id : 0;
}, BASE );

await page.goto( `${ BASE }/wp-admin/post.php?post=${ pageId }&action=edit`, { waitUntil: 'networkidle' } );
await page.waitForTimeout( 4000 );

// Dismiss the welcome modal if it appears.
const modalClose = page.locator( '.components-modal__header button[aria-label]' );
if ( await modalClose.count() ) { await modalClose.first().click().catch( () => {} ); }

const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
let editorTable = await canvas.locator( '.lstab-table' ).count().catch( () => 0 );
if ( editorTable === 0 ) { editorTable = await page.locator( '.lstab-table' ).count(); }
check( editorTable > 0, 'Block renders a live server-side preview inside the editor', String( editorTable ) );
await page.screenshot( { path: `${ SHOTS }/10-block-editor.png`, fullPage: false } );

// ------------------------------------------------------------------ wrap up
section( '11. Console health' );
const realErrors = consoleErrors.filter( ( e ) => ! /favicon|net::ERR_/.test( e ) );
check( realErrors.length === 0, 'No JavaScript errors across the whole run', realErrors.join( ' | ' ) );

await browser.close();

console.log( '\n' + '─'.repeat( 60 ) );
console.log( `  \x1b[32m${ pass } passed\x1b[0m, ${ fail ? `\x1b[31m${ fail } failed\x1b[0m` : '0 failed' }` );
console.log( '─'.repeat( 60 ) );
process.exit( fail ? 1 : 0 );
