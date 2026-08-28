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

// ---------------------------------------------------- preview width switcher
section( '3a. Preview width switcher' );

// At a laptop width the side-by-side pane used to be ~620px, so a five-column
// table previewed as cards while the published page rendered a table.
const previewTableDisplay = await page.locator( '.lstab-preview .lstab-table' ).evaluate( ( el ) => ( {
	display: getComputedStyle( el ).display,
	container: Math.round( el.closest( '.lstab-container' ).getBoundingClientRect().width ),
} ) );
check(
	previewTableDisplay.display === 'table',
	'A five-column preview shows as a table at full width',
	JSON.stringify( previewTableDisplay )
);

await page.locator( '.lstab-width-button[data-lstab-width="390"]' ).click();
await page.waitForTimeout( 350 );
const phonePreview = await page.locator( '.lstab-preview .lstab-table' ).evaluate( ( el ) => ( {
	display: getComputedStyle( el ).display,
	container: Math.round( el.closest( '.lstab-container' ).getBoundingClientRect().width ),
} ) );
check( phonePreview.container <= 392, 'Phone width constrains the preview stage', String( phonePreview.container ) );
check( phonePreview.display === 'block', 'Phone width previews the card layout', phonePreview.display );

await page.locator( '.lstab-width-button[data-lstab-width="650"]' ).click();
await page.waitForTimeout( 350 );
const narrowPreview = await page.locator( '.lstab-preview .lstab-container' ).evaluate( ( el ) =>
	Math.round( el.getBoundingClientRect().width )
);
check( narrowPreview <= 652 && narrowPreview > 392, 'Narrow-column width sits between the two', String( narrowPreview ) );

const pressed = await page.locator( '.lstab-width-button[data-lstab-width="650"]' ).getAttribute( 'aria-pressed' );
check( pressed === 'true', 'The active width button reports itself as pressed', String( pressed ) );

await page.locator( '.lstab-width-button[data-lstab-width=""]' ).click();
await page.waitForTimeout( 350 );

// --------------------------------------------------------- style presets
section( '3b. Style presets change the preview' );

// The preview is what the author checks before saving, so a preset that does
// not visibly apply there makes the control look broken.
const presetProbe = async ( slug ) => {
	await page.locator( `input[name="style_preset"][value="${ slug }"]` ).check();
	await page.waitForTimeout( 150 );
	return page.locator( '.lstab-preview .lstab' ).evaluate( ( el ) => ( {
		classes: el.className,
		stripe: getComputedStyle( el.querySelector( 'tbody tr:nth-child(2)' ) ).backgroundColor,
		frame: getComputedStyle( el.querySelector( '.lstab-scroll' ) ).borderRightWidth,
		cellBorder: getComputedStyle( el.querySelector( 'tbody td' ) ).borderRightWidth,
	} ) );
};

const clean = await presetProbe( 'clean' );
check( clean.classes.includes( 'lstab-style-clean' ), 'Clean preset applies its class', clean.classes );

const striped = await presetProbe( 'striped' );
check( striped.classes.includes( 'lstab-style-striped' ), 'Striped preset applies its class', striped.classes );
check( striped.stripe !== clean.stripe, 'Striped preset actually tints alternate rows', `${ clean.stripe } → ${ striped.stripe }` );

const bordered = await presetProbe( 'bordered' );
check( bordered.classes.includes( 'lstab-style-bordered' ), 'Bordered preset applies its class', bordered.classes );
check( bordered.cellBorder !== clean.cellBorder, 'Bordered preset actually draws cell borders', `${ clean.cellBorder } → ${ bordered.cellBorder }` );

// Only one preset class may be present, or the last one would not win.
const presetClassCount = ( bordered.classes.match( /lstab-style-/g ) || [] ).length;
check( presetClassCount === 1, 'Exactly one preset class is applied at a time', bordered.classes );

// Reloading the preview must keep the chosen preset rather than reverting.
await page.locator( '#lstab-preview-button' ).click();
await page.waitForTimeout( 1200 );
const afterReload = await page.locator( '.lstab-preview .lstab' ).getAttribute( 'class' );
check( afterReload.includes( 'lstab-style-bordered' ), 'A reloaded preview keeps the chosen preset', afterReload );

await page.locator( 'input[name="style_preset"][value="striped"]' ).check();

// ----------------------------------------------------- visual appearance
section( '3c. Visual appearance editor' );

const tableStyle = () =>
	page.locator( '.lstab-preview .lstab' ).evaluate( ( el ) => ( {
		accent: el.style.getPropertyValue( '--lstab-accent' ).trim(),
		background: getComputedStyle( el.querySelector( '.lstab-scroll' ) ).backgroundColor,
		padding: getComputedStyle( el.querySelector( 'tbody td' ) ).paddingTop,
		inline: el.getAttribute( 'style' ) || '',
	} ) );

const beforeCustomising = await tableStyle();
check( beforeCustomising.accent === '', 'A fresh source has no overrides', beforeCustomising.inline );

// Colour inputs cannot be typed into, so drive them the way the browser does.
await page.locator( '.lstab-swatch[data-lstab-token="accent"] .lstab-color-input' ).evaluate( ( el ) => {
	el.value = '#c0392b';
	el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
} );
await page.waitForTimeout( 150 );

const afterAccent = await tableStyle();
check( afterAccent.accent === '#c0392b', 'Picking a colour applies it live', afterAccent.inline );

await page.locator( '.lstab-swatch[data-lstab-token="background"] .lstab-color-input' ).evaluate( ( el ) => {
	el.value = '#fff7e6';
	el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
} );
await page.waitForTimeout( 150 );
const afterBackground = await tableStyle();
check(
	afterBackground.background !== beforeCustomising.background,
	'A background colour visibly changes the table',
	`${ beforeCustomising.background } → ${ afterBackground.background }`
);

await page.selectOption( '#lstab-metric-density', 'roomy' );
await page.waitForTimeout( 150 );
const afterDensity = await tableStyle();
check(
	parseFloat( afterDensity.padding ) > parseFloat( beforeCustomising.padding ),
	'Row height actually changes the padding',
	`${ beforeCustomising.padding } → ${ afterDensity.padding }`
);

// Every colour control must actually reach the page. A token that maps to a
// property nothing reads looks like a working control and does nothing — which
// is exactly what "Header background" did on every preset but Bordered.
const colourTargets = {
	text: { selector: 'tbody td', property: 'color' },
	background: { selector: '.lstab-scroll', property: 'backgroundColor' },
	headerText: { selector: 'thead th', property: 'color' },
	headerBg: { selector: 'thead th', property: 'backgroundColor' },
	border: { selector: 'tbody tr', property: 'borderBottomColor' },
	stripe: { selector: 'tbody tr:nth-child(2)', property: 'backgroundColor' },
	accent: { selector: '.lstab-search-input', property: 'borderColor' },
};

const probeColour = '#ff00ff';
const probeRgb = 'rgb(255, 0, 255)';

// Row backgrounds and the focus ring animate over ~120-150ms. Sampling a
// computed colour mid-transition yields a value a few units off and makes this
// section flaky, so take animation out of the measurement entirely.
await page.addStyleTag( {
	content: '.lstab-preview *, .lstab-preview *::before, .lstab-preview *::after { transition: none !important; animation: none !important; }',
} );

for ( const [ token, target ] of Object.entries( colourTargets ) ) {
	await page.locator( '#lstab-reset-appearance' ).click();
	await page.waitForTimeout( 80 );

	await page.locator( `.lstab-swatch[data-lstab-token="${ token }"] .lstab-color-input` ).evaluate( ( el, v ) => {
		el.value = v;
		el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}, probeColour );
	await page.waitForTimeout( 120 );

	const computed = await page.locator( '.lstab-preview .lstab' ).evaluate( ( el, t ) => {
		const node = el.querySelector( t.selector );
		return node ? getComputedStyle( node )[ t.property ] : null;
	}, target );

	// The accent only shows on interactive parts: the sorted column's arrow and
	// the focused search field. Check both, since the dashboard's own input
	// styling has historically fought the latter.
	let effective = computed;
	if ( 'accent' === token ) {
		await page.locator( '.lstab-preview thead th .lstab-sort' ).first().click();
		await page.waitForTimeout( 150 );
		const icon = await page.locator( '.lstab-preview thead th[aria-sort] .lstab-sort-icon' ).evaluate(
			( el ) => getComputedStyle( el ).backgroundColor
		);

		await page.locator( '.lstab-preview .lstab-search-input' ).focus();
		await page.waitForTimeout( 120 );
		const focused = await page.locator( '.lstab-preview .lstab-search-input' ).evaluate(
			( el ) => getComputedStyle( el ).borderColor
		);
		await page.locator( '#lstab-title' ).focus();

		check( String( focused ).includes( probeRgb ), 'Accent survives the dashboard\'s own input styling', focused );
		effective = icon;
	}

	check(
		String( effective ).includes( probeRgb ),
		`Colour control "${ token }" reaches the rendered table`,
		`${ target.selector } ${ target.property } = ${ effective }`
	);
}

await page.locator( '#lstab-reset-appearance' ).click();
await page.waitForTimeout( 100 );
await page.locator( '.lstab-swatch[data-lstab-token="accent"] .lstab-color-input' ).evaluate( ( el ) => {
	el.value = '#c0392b';
	el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
} );
await page.locator( '.lstab-swatch[data-lstab-token="background"] .lstab-color-input' ).evaluate( ( el ) => {
	el.value = '#fff7e6';
	el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
} );
await page.waitForTimeout( 120 );

// Clearing one colour must leave the others alone.
await page.locator( '.lstab-swatch[data-lstab-token="accent"] .lstab-color-clear' ).click();
await page.waitForTimeout( 150 );
const afterClear = await tableStyle();
check( afterClear.accent === '', 'Resetting one colour removes just that one', afterClear.inline );
check( afterClear.inline.includes( '--lstab-bg' ), 'The other colours survive that reset', afterClear.inline );

// Reloading the preview must not discard what has been set.
await page.locator( '#lstab-preview-button' ).click();
await page.waitForTimeout( 1400 );
const afterReloadStyle = await tableStyle();
check(
	afterReloadStyle.inline.includes( '--lstab-bg' ),
	'Overrides survive a preview reload',
	afterReloadStyle.inline
);

// Reset everything, then save, so the rest of the run sees a clean table.
await page.locator( '#lstab-reset-appearance' ).click();
await page.waitForTimeout( 150 );
const afterResetAll = await tableStyle();
check( afterResetAll.inline === '', 'Reset clears every override', afterResetAll.inline );

// Set one, save, and confirm it reaches the published page.
await page.locator( '.lstab-swatch[data-lstab-token="accent"] .lstab-color-input' ).evaluate( ( el ) => {
	el.value = '#116149';
	el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
} );
await Promise.all( [
	page.waitForURL( /page=live-sheets-table/ ),
	page.locator( '#lstab-source-form button[type="submit"]' ).click(),
] );
await page.waitForLoadState( 'networkidle' );

const publishedStyle = await page.evaluate( async ( base ) => {
	const res = await fetch( base + '/cennik/', { credentials: 'same-origin' } );
	const html = await res.text();
	return html.includes( '--lstab-accent:#116149' );
}, BASE );
check( publishedStyle, 'A saved override reaches the published page' );

// Put it back.
const sourceEditUrl = `${ BASE }/wp-admin/admin.php?page=live-sheets-table-edit&source=${ sourceId }`;
await page.goto( sourceEditUrl, { waitUntil: 'networkidle' } );
await page.waitForSelector( '.lstab-preview .lstab-table', { timeout: 15000 } );
await page.locator( '#lstab-reset-appearance' ).click();
await Promise.all( [
	page.waitForURL( /page=live-sheets-table/ ),
	page.locator( '#lstab-source-form button[type="submit"]' ).click(),
] );
await page.goto( sourceEditUrl, { waitUntil: 'networkidle' } );
await page.waitForSelector( '.lstab-preview .lstab-table', { timeout: 15000 } );

// Switching tab re-fetches the preview.
section( '4. Switching sheet tab' );
await page.selectOption( '#lstab-tabs', '1734829105' );
await page.waitForFunction(
	() => document.querySelectorAll( '.lstab-preview tbody tr' ).length === 3,
	null,
	{ timeout: 15000 }
);
const tabHeaders = await page.locator( '.lstab-preview thead th' ).allInnerTexts();
check(
	tabHeaders.join( ',' ).toLowerCase().includes( 'miasto' ),
	'Switching tab loads the other sheet',
	JSON.stringify( tabHeaders )
);
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

// --------------------------------------------------- no silently clipped columns
section( '5b. Nothing is clipped without a way to reach it' );

const clipping = await page.evaluate( () => {
	return Array.from( document.querySelectorAll( '.lstab' ) ).filter( ( wrap ) =>
		wrap.querySelector( '.lstab-scroll' ) && wrap.querySelector( '.lstab-table' )
	).map( ( wrap ) => {
		const scroll = wrap.querySelector( '.lstab-scroll' );
		const table = wrap.querySelector( '.lstab-table' );
		return {
			preset: wrap.className,
			hidden: scroll.scrollWidth - scroll.clientWidth,
			stacked: getComputedStyle( table ).display === 'block',
			scrollable: getComputedStyle( scroll ).overflowX === 'auto',
		};
	} );
} );

check( clipping.length === 2, 'Both tables are present to measure', String( clipping.length ) );

clipping.forEach( ( t ) => {
	// Either the table fits, or it has stacked into cards. A five-column table
	// inside a ~650px theme column used to do neither and lost two columns.
	check(
		t.hidden === 0 || t.stacked,
		`No hidden columns in a theme-width column (${ t.preset.replace( /lstab-?/g, '' ).trim() })`,
		`${ t.hidden }px hidden, stacked=${ t.stacked }`
	);
} );

// A genuinely wide table on a wide screen may still scroll — but must say so.
await page.setViewportSize( { width: 1600, height: 1000 } );
await page.waitForTimeout( 200 );
const affordance = await page.evaluate( () => {
	const scroll = document.querySelector( '.lstab-scroll' );
	const style = getComputedStyle( scroll );
	return {
		hasEdgeFade: style.backgroundImage.includes( 'radial-gradient' ),
		attachment: style.backgroundAttachment,
	};
} );
check( affordance.hasEdgeFade, 'Scrollable tables carry an edge-fade affordance', affordance.attachment );
await page.setViewportSize( { width: 1440, height: 1000 } );
await page.waitForTimeout( 200 );

// --------------------------------------------------------- numeric alignment
section( '5c. Numeric columns' );

const alignments = await page.evaluate( () => {
	const heads = document.querySelectorAll( '.lstab-style-striped thead th' );
	return Array.from( heads ).map( ( th ) => ( {
		label: th.textContent.trim(),
		align: th.getAttribute( 'data-lstab-align' ),
		computed: getComputedStyle( th ).textAlign,
	} ) );
} );
const priceColumn = alignments.find( ( a ) => a.label.startsWith( 'Cena' ) );
const productColumn = alignments.find( ( a ) => a.label.startsWith( 'Produkt' ) );
check( priceColumn && priceColumn.align === 'end', 'Price column marked numeric', JSON.stringify( priceColumn ) );
check( priceColumn && priceColumn.computed === 'right', 'Price column renders right-aligned', priceColumn && priceColumn.computed );
check( productColumn && productColumn.align === 'start', 'Product column stays left-aligned', JSON.stringify( productColumn ) );

const tabular = await page.evaluate( () =>
	getComputedStyle( document.querySelector( '.lstab-table' ) ).fontVariantNumeric
);
check( tabular.includes( 'tabular-nums' ), 'Figures are tabular so decimals line up', tabular );

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

const headDisplay = await mpage.locator( '.lstab-style-striped thead' ).evaluate( ( el ) => getComputedStyle( el ).display );
check( headDisplay === 'none', 'Header row is dropped on narrow screens; per-cell labels carry the meaning', headDisplay );
check( await mpage.locator( '.lstab-style-striped .lstab-cell-label' ).first().isVisible(), 'Per-cell labels become visible (card layout)' );

// Below 420px the label goes on its own line so long headings never break mid-word.
const stackedPair = await mpage.locator( '.lstab-style-striped tbody td' ).first().evaluate( ( el ) => {
	const label = el.querySelector( '.lstab-cell-label' ).getBoundingClientRect();
	const value = el.querySelector( '.lstab-cell-value' ).getBoundingClientRect();
	return { labelBottom: label.bottom, valueTop: value.top, labelLeft: label.left, valueLeft: value.left };
} );
check(
	stackedPair.valueTop >= stackedPair.labelBottom - 1,
	'Narrow phones stack the label above the value',
	JSON.stringify( stackedPair )
);

// Right alignment is for table columns; inside a card it would strand the
// number away from its label.
const cardAlign = await mpage.evaluate( () => {
	const cell = document.querySelector( '.lstab-style-striped tbody td[data-lstab-align="end"]' );
	return cell ? getComputedStyle( cell ).textAlign : null;
} );
check( cardAlign === 'left', 'Numeric cells align left inside a card', String( cardAlign ) );

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
const captionAbove = await mpage.locator( '.lstab-style-striped' ).evaluate( ( wrap ) => {
	const cap = wrap.querySelector( '.lstab-caption' ).getBoundingClientRect();
	const box = wrap.querySelector( '.lstab-scroll' ).getBoundingClientRect();
	return cap.bottom <= box.top + 1;
} );
check( captionAbove, 'Caption sits above the table frame, not inside it' );
const labelled = await mpage.locator( '.lstab-style-striped .lstab-table' ).getAttribute( 'aria-labelledby' );
check( !! labelled, 'Table is still associated with its caption for screen readers', String( labelled ) );
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
