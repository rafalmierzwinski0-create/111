/**
 * Browser test for the Pro picker: click a heading, click a row, save, and
 * check that the published page agrees.
 *
 * The PHP suite proves the rules. This proves that a person clicking things in
 * a browser reaches them — which is the whole reason the picker exists, and the
 * one thing PHP cannot check.
 */
import { chromium } from 'playwright';
import fs from 'node:fs';

const BASE = process.env.LSTAB_BASE || 'http://127.0.0.1:8089';
const SHOTS = process.env.LSTAB_SHOTS || new URL( '../../screenshots', import.meta.url ).pathname;
const CHROMIUM = process.env.LSTAB_CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
fs.mkdirSync( SHOTS, { recursive: true } );

let pass = 0, fail = 0;
const check = ( ok, label, detail = '' ) => {
	if ( ok ) { pass++; console.log( `  \x1b[32mPASS\x1b[0m  ${ label }` ); }
	else { fail++; console.log( `  \x1b[31mFAIL\x1b[0m  ${ label }${ detail ? '\n        ' + detail : '' }` ); }
};
const section = ( t ) => console.log( `\n\x1b[1m${ t }\x1b[0m` );

const browser = await chromium.launch( { executablePath: CHROMIUM, args: [ '--no-sandbox' ] } );
const context = await browser.newContext( { viewport: { width: 1280, height: 1000 }, deviceScaleFactor: 2, locale: 'pl-PL' } );
const page = await context.newPage();

const errors = [];
page.on( 'console', ( m ) => { if ( m.type() === 'error' && ! /net::ERR/.test( m.text() ) ) errors.push( m.text() ); } );

section( '1. Opening the picker' );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'networkidle' } );
await page.fill( '#user_login', 'admin' );
await page.fill( '#user_pass', 'admin123' );
await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] );

await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table`, { waitUntil: 'networkidle' } );
const editHref = await page.locator( '.lstab-src a:has-text("Edit")' ).first().getAttribute( 'href' );
await page.goto( editHref, { waitUntil: 'networkidle' } );

// The editor shows three panes; the picker lives on the third.
await page.locator( '[data-lstab-goto="hide"]' ).click();
await page.waitForTimeout( 150 );

check( await page.locator( '#lstabp-picker' ).count() === 1, 'The picker is on the source screen' );
check( await page.locator( '#lstabp-picker' ).isVisible(), 'And is on screen once its tab is chosen' );
check( await page.locator( '#lstabp-picker thead button[data-lstabp-column]' ).count() > 0, 'Headings are buttons' );
check( await page.locator( '#lstabp-picker tbody button[data-lstabp-row]' ).count() > 0, 'So are rows' );

section( '2. Clicking a heading and a row' );

const heading = page.locator( '#lstabp-picker thead button[data-lstabp-column="2"]' );
const headingName = ( await heading.innerText() ).trim();
await heading.click();
check(
	await page.locator( '#lstabp-picker th.is-hidden' ).count() > 0,
	'A clicked heading is drawn as dropped'
);
check(
	await page.locator( '.lstab-column-list .lstab-state-hidden' ).count() === 1,
	'And the list of columns says so too, without a reload'
);

const rowLabel = ( await page.locator( '#lstabp-picker tbody tr' ).nth( 1 ).locator( 'td' ).first().innerText() ).trim();
await page.locator( '#lstabp-picker tbody tr' ).nth( 1 ).locator( 'button[data-lstabp-row]' ).click();
await page.waitForTimeout( 300 );

const chips = await page.locator( '#lstabp-hidden-rows-chips .lstabp-chip' ).allInnerTexts();
check( chips.length === 1, 'A clicked row is listed below the table', JSON.stringify( chips ) );
check( chips[ 0 ].includes( rowLabel ), 'By name', JSON.stringify( chips ) );

const rowLine = await page.locator( '#lstabp-picker tbody tr' ).nth( 1 ).getAttribute( 'data-lstabp-line' );
check( chips[ 0 ].includes( rowLine ), 'And by the line Google shows it on', JSON.stringify( chips ) + ' line ' + rowLine );

const fieldCount = await page.locator( '#lstabp-hidden-rows-fields input[name^="hidden_rows"]' ).count();
check( fieldCount === 4, 'It carries the line, the name, what it said and how to check it', String( fieldCount ) );
check(
	await page.locator( '#lstabp-hidden-rows-fields input[name$="[index]"]' ).first().getAttribute( 'value' ) === '1',
	'And the line it remembers is the row that was clicked'
);

await page.locator( '.lstabp-picker-card' ).scrollIntoViewIfNeeded();
await page.waitForTimeout( 200 );
await page.locator( '.lstabp-picker-card' ).screenshot( { path: `${ SHOTS }/37-picker.png` } );

section( '3. Saving, and the published page' );

await Promise.all( [
	page.waitForLoadState( 'networkidle' ),
	page.locator( '.lstab-submit button[type=submit]' ).first().click()
] );
await page.waitForTimeout( 1200 );

const front = await context.newPage();

/*
 * Headings are rendered in capitals by the stylesheet, and innerText reports
 * what is rendered. Comparing case-sensitively made "the column is gone" pass
 * whether it was gone or not, which is worse than no check at all.
 */
const pageText = async () => {
	await front.goto( `${ BASE }/cennik/`, { waitUntil: 'networkidle' } );
	return ( await front.locator( 'body' ).innerText() ).toLocaleLowerCase( 'pl' );
};
const shows = ( text, needle ) => text.includes( needle.toLocaleLowerCase( 'pl' ) );

const body = await pageText();

check( ! shows( body, headingName ), `The published page drops the column "${ headingName }"` );
check( ! shows( body, rowLabel ), `And the row "${ rowLabel }"` );
check( shows( body, 'Rower górski' ), 'While the rest of the table is untouched' );

section( '4. Clicking again brings it back' );

await page.goto( editHref, { waitUntil: 'networkidle' } );
await page.locator( '[data-lstab-goto="hide"]' ).click();
await page.waitForTimeout( 150 );
check(
	await page.locator( '#lstabp-picker th.is-hidden' ).count() > 0,
	'The choice is still shown as made after a reload'
);
check(
	await page.locator( '#lstabp-hidden-rows-chips .lstabp-chip' ).count() === 1,
	'And so is the hidden row'
);

await page.locator( '#lstabp-picker thead button[data-lstabp-column="2"]' ).click();
await page.locator( '#lstabp-hidden-rows-chips .lstabp-chip-remove' ).first().click();
await page.waitForTimeout( 300 );
check( await page.locator( '#lstabp-picker th.is-hidden' ).count() === 0, 'A second click brings the column back' );
check( await page.locator( '#lstabp-hidden-rows-chips .lstabp-chip' ).count() === 0, 'And the chip removes the row' );

await Promise.all( [
	page.waitForLoadState( 'networkidle' ),
	page.locator( '.lstab-submit button[type=submit]' ).first().click()
] );
await page.waitForTimeout( 1200 );

const restored = await pageText();
check( shows( restored, headingName ), 'The published page has the column back' );
check( shows( restored, rowLabel ), 'And the row' );

// ------------------------------------------- moving a column under the row
section( '5. Moving a column into the details' );

// The save above left the browser on the list of sources, so the editor has to
// be opened again before there is a picker to click.
await page.goto( editHref, { waitUntil: 'networkidle' } );
await page.locator( '[data-lstab-goto="hide"]' ).click();
await page.waitForTimeout( 200 );

const chevron = page.locator( '#lstabp-picker thead button[data-lstabp-detail="2"]' );
check( await chevron.count() === 1, 'Each heading offers a second mark for the details' );

await chevron.click();
await page.waitForTimeout( 200 );
check( 'true' === await chevron.getAttribute( 'aria-pressed' ), 'Clicking it marks the column as moved' );
check(
	'In the details' === ( await page.locator( '.lstab-column-list tbody tr' ).nth( 2 ).locator( '.lstab-column-state span' ).innerText() ).trim(),
	'And the list of columns says so, without a reload'
);

// Hiding a column takes it out of the drawer too: "hidden but also in the
// details" is not a state anybody means.
await page.locator( '#lstabp-picker thead button[data-lstabp-column="2"]' ).click();
await page.waitForTimeout( 200 );
check( 'false' === await chevron.getAttribute( 'aria-pressed' ), 'Hiding a column takes it out of the details as well' );
check( await chevron.isDisabled(), 'And the arrow is not offered while it is hidden' );

await page.locator( '#lstabp-picker thead button[data-lstabp-column="2"]' ).click();
await page.waitForTimeout( 200 );
check( ! ( await chevron.isDisabled() ), 'Putting it back offers the arrow again' );

await chevron.click();
await page.waitForTimeout( 200 );
await page.screenshot( { path: `${ SHOTS }/48-picker-details.png`, fullPage: true } );

await Promise.all( [
	page.waitForLoadState( 'networkidle' ),
	page.locator( '.lstab-submit button[type=submit]' ).first().click()
] );
await page.waitForTimeout( 1200 );

const withDrawer = await pageText();
check( ! shows( withDrawer, headingName ), 'The published table no longer has it as a column' );
// pageText() reports what is rendered, and a drawer starts closed, so this one
// asks the page itself rather than reading its visible text.
const drawerOnPage = await front.locator( '.lstab-open' ).count();
check( drawerOnPage > 0, 'And offers the drawer instead', String( drawerOnPage ) );
check(
	await front.locator( '.lstab-detail-key' ).first().innerText() !== '',
	'With the moved column named inside it'
);

// Put it back.
await page.goto( editHref, { waitUntil: 'networkidle' } );
await page.locator( '[data-lstab-goto="hide"]' ).click();
await page.waitForTimeout( 250 );
await page.locator( '#lstabp-picker thead button[data-lstabp-detail="2"]' ).click();
await Promise.all( [
	page.waitForLoadState( 'networkidle' ),
	page.locator( '.lstab-submit button[type=submit]' ).first().click()
] );
await page.waitForTimeout( 800 );

check( errors.length === 0, 'No script errors anywhere in the run', errors.join( '\n' ) );

console.log( '\n' + '─'.repeat( 60 ) );
console.log( `  \x1b[32m${ pass } passed\x1b[0m, \x1b[31m${ fail } failed\x1b[0m` );
console.log( '─'.repeat( 60 ) );

await browser.close();
process.exit( fail > 0 ? 1 : 0 );
