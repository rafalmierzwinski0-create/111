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

/*
 * The editor is one form shown three panes at a time, so a control has to be
 * on the visible pane before it can be clicked. Real people click the tab
 * first; so does this.
 */
const pane = async ( name ) => {
	const tab = page.locator( `[data-lstab-goto="${ name }"]` );

	if ( await tab.count() ) {
		await tab.click();
		await page.waitForTimeout( 120 );
	}
};

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
/*
 * Waiting for the card to come back rather than for the network to go quiet:
 * the quiet moment can be the page we are still standing on, and then every
 * check below reads the screen from before the refresh.
 */
await page.locator( '.lstab-src button:has-text("Refresh")' ).first().click();
await page.waitForSelector( '.lstab-src', { state: 'visible', timeout: 15000 } );

check( await page.locator( '.lstab-src' ).count() > 0, 'Each sheet is a card, not a table row' );

const statusText = await page.locator( '.lstab-state' ).first().innerText();
check( /Up to date/.test( statusText ), 'The card says the sheet is up to date', statusText );

// Working normally must not be coloured: colour on every card all day is not
// a signal, and a real fault then has nothing left to stand out against.
const calmTone = await page.locator( '.lstab-state' ).first().evaluate( ( el ) => getComputedStyle( el ).color );
check( calmTone === 'rgb(91, 109, 107)', 'A healthy sheet is stated in grey, not green', calmTone );

check( await page.locator( '.lstab-shortcode' ).first().isVisible(), 'Shortcode is shown for copy/paste' );
check( await page.locator( '.lstab-src .lstab-copy' ).first().isVisible(), 'And has a copy button beside it' );

// The single most repeated action in the plugin. Reading the clipboard needs a
// permission Chromium will not grant headlessly, so the check is that the
// button reports success — the browser API itself is not ours to test.
// Scoped to the card: the cron notice offers its own copy button, and "the
// first one on the page" stopped meaning the shortcode the moment it did.
const cardCopy = page.locator( '.lstab-src .lstab-copy' ).first();
await cardCopy.click();

// Watched rather than polled. The button says "Copied" for 1.8 seconds and then
// puts its own label back, so a check that arrives late reads the label after it
// has already reverted and calls a working button broken — which is what this
// test did about one run in ten.
const copyConfirmed = await cardCopy.evaluate( ( el ) => new Promise( ( resolve ) => {
	if ( el.classList.contains( 'is-done' ) ) {
		resolve( true );
		return;
	}

	const observer = new MutationObserver( () => {
		if ( el.classList.contains( 'is-done' ) ) {
			observer.disconnect();
			resolve( true );
		}
	} );

	observer.observe( el, { attributes: true, attributeFilter: [ 'class' ] } );

	setTimeout( () => {
		observer.disconnect();
		resolve( false );
	}, 5000 );
} ) );
check( copyConfirmed, 'Clicking it confirms the shortcode was copied' );

check(
	await page.locator( '.lstab-masthead h1' ).first().isVisible(),
	'The plugin has its own heading rather than a bare WordPress title'
);

const usedText = await page.locator( '.lstab-used' ).first().innerText();
check(
	/Cennik|safe to delete/i.test( usedText ),
	'The card says where the table is used, or that nothing uses it',
	usedText
);

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
check( phonePreview.display === 'table', 'Phone width still previews a table', phonePreview.display );

const previewSlider = await page.locator( '.lstab-preview .lstab-scrollbar' ).isVisible();
check( previewSlider, 'The phone preview shows the slider, so the control can be checked too' );

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
await pane( 'look' );

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
await pane( 'general' );
await page.locator( '#lstab-preview-button' ).click();
await page.waitForTimeout( 1200 );
await pane( 'look' );
const afterReload = await page.locator( '.lstab-preview .lstab' ).getAttribute( 'class' );
check( afterReload.includes( 'lstab-style-bordered' ), 'A reloaded preview keeps the chosen preset', afterReload );

await page.locator( 'input[name="style_preset"][value="striped"]' ).check();

// ----------------------------------------------------- visual appearance
section( '3c. Visual appearance editor' );
await pane( 'look' );

const tableStyle = () =>
	page.locator( '.lstab-preview .lstab' ).evaluate( ( el ) => ( {
		accent: el.style.getPropertyValue( '--lstab-accent' ).trim(),
		background: getComputedStyle( el.querySelector( '.lstab-scroll' ) ).backgroundColor,
		padding: getComputedStyle( el.querySelector( 'tbody td' ) ).paddingTop,
		inline: el.getAttribute( 'style' ) || '',
	} ) );

/**
 * The colour the chosen preset is currently giving one token, as #rrggbb.
 *
 * A swatch with no override of its own should be showing exactly this.
 */
const presetColour = ( token ) => page.evaluate( ( name ) => {
	const swatch = document.querySelector( '.lstab-swatch[data-lstab-token="' + name + '"]' );
	const table = document.querySelector( '.lstab-preview .lstab' );
	const probe = document.createElement( 'span' );

	probe.style.color = getComputedStyle( table )
		.getPropertyValue( swatch.getAttribute( 'data-lstab-var' ) ).trim();
	document.body.appendChild( probe );
	const parts = ( getComputedStyle( probe ).color || '' ).match( /[0-9.]+/g );
	document.body.removeChild( probe );

	return parts && parts.length >= 3
		? '#' + parts.slice( 0, 3 ).map(
			( n ) => ( '0' + parseInt( n, 10 ).toString( 16 ) ).slice( -2 )
		).join( '' )
		: '#ffffff';
}, token );

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
	await pane( 'look' );
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
		await page.evaluate( () => document.activeElement && document.activeElement.blur() );

		check( String( focused ).includes( probeRgb ), 'Accent survives the dashboard\'s own input styling', focused );
		effective = icon;
	}

	check(
		String( effective ).includes( probeRgb ),
		`Colour control "${ token }" reaches the rendered table`,
		`${ target.selector } ${ target.property } = ${ effective }`
	);
}

await pane( 'look' );
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

// The swatch itself has to let go of the colour too. Leaving #c0392b sitting in
// the picker after clearing it reads as "still set", which is what it looked
// like to everybody who tried it.
const accentSwatchAfterClear = await page.locator(
	'.lstab-swatch[data-lstab-token="accent"] .lstab-color-input'
).inputValue();
check(
	'#c0392b' !== accentSwatchAfterClear,
	'Clearing one colour empties its swatch as well',
	accentSwatchAfterClear
);
check(
	accentSwatchAfterClear === await presetColour( 'accent' ),
	'The cleared swatch shows the colour the preset supplies',
	accentSwatchAfterClear
);

// Reloading the preview must not discard what has been set.
await pane( 'general' );
await page.locator( '#lstab-preview-button' ).click();
await page.waitForTimeout( 1400 );
await pane( 'look' );
const afterReloadStyle = await tableStyle();
check(
	afterReloadStyle.inline.includes( '--lstab-bg' ),
	'Overrides survive a preview reload',
	afterReloadStyle.inline
);

// Reset everything, then save, so the rest of the run sees a clean table.
await pane( 'look' );
await page.locator( '#lstab-reset-appearance' ).click();
await page.waitForTimeout( 150 );
const afterResetAll = await tableStyle();
check( afterResetAll.inline === '', 'Reset clears every override', afterResetAll.inline );

const strandedSwatches = await page.evaluate( () => {
	const stranded = [];

	document.querySelectorAll( '.lstab-swatch' ).forEach( ( swatch ) => {
		const picker = swatch.querySelector( '.lstab-color-input' );
		const table = document.querySelector( '.lstab-preview .lstab' );
		const probe = document.createElement( 'span' );

		probe.style.color = getComputedStyle( table )
			.getPropertyValue( swatch.getAttribute( 'data-lstab-var' ) ).trim();
		document.body.appendChild( probe );
		const parts = ( getComputedStyle( probe ).color || '' ).match( /[0-9.]+/g );
		document.body.removeChild( probe );

		const expected = parts && parts.length >= 3
			? '#' + parts.slice( 0, 3 ).map(
				( n ) => ( '0' + parseInt( n, 10 ).toString( 16 ) ).slice( -2 )
			).join( '' )
			: '#ffffff';

		if ( picker.value !== expected ) {
			stranded.push( swatch.getAttribute( 'data-lstab-token' ) + ': ' + picker.value + ' ≠ ' + expected );
		}
	} );

	return stranded;
} );
check(
	0 === strandedSwatches.length,
	'Reset empties every swatch, not just the table',
	strandedSwatches.join( ', ' )
);

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
await pane( 'look' );
await page.locator( '#lstab-reset-appearance' ).click();
await Promise.all( [
	page.waitForURL( /page=live-sheets-table/ ),
	page.locator( '#lstab-source-form button[type="submit"]' ).click(),
] );
await page.goto( sourceEditUrl, { waitUntil: 'networkidle' } );
await page.waitForSelector( '.lstab-preview .lstab-table', { timeout: 15000 } );

// ---------------------------------------------------------------- own CSS

section( '3d. A table with its own CSS' );

await pane( 'look' );
const cssField = page.locator( '#lstab-custom-css' );
check( await cssField.count() > 0, 'The Appearance tab offers a CSS field' );

await cssField.fill( 'td { outline: 2px dotted rgb(4, 5, 6); }\n.nothing-here { color: red }' );
// The field asks the server for the scoped form 400ms after the last keystroke.
await page.waitForTimeout( 1200 );

const liveCss = await page.locator( 'style.lstab-live-css' ).evaluate( ( el ) => el.textContent );
check(
	liveCss.includes( '[data-lstab-preview="stage"] td' ),
	'What is typed is confined to the preview, not let loose on the dashboard',
	liveCss
);

const previewOutline = await page.locator( '.lstab-preview tbody td' ).first().evaluate(
	( el ) => getComputedStyle( el ).outlineColor
);
check( previewOutline.includes( 'rgb(4, 5, 6)' ), 'And it is visibly applied to the preview', previewOutline );

// The point of scoping: a rule for "td" must not reach the dashboard's own
// tables or anything else on the screen.
const escaped = await page.evaluate( () => {
	const outside = document.querySelector( '.lstab-editor-grid' );
	return outside ? getComputedStyle( outside ).outlineColor : '';
} );
check( ! escaped.includes( 'rgb(4, 5, 6)' ), 'Nothing outside the table is touched by it', escaped );

// Save, and check the published page.
await Promise.all( [
	page.waitForURL( /page=live-sheets-table/ ),
	page.locator( '#lstab-source-form button[type="submit"]' ).click(),
] );

const publishedCss = await page.evaluate( async ( base ) => {
	const res = await fetch( base + '/cennik/', { credentials: 'same-origin' } );
	const html = await res.text();
	return {
		hasBlock: html.includes( 'lstab-custom-css' ),
		scoped: /\[data-lstab-id="\d+"\] td\{outline: 2px dotted rgb\(4, 5, 6\);\}/.test( html ),
		unscoped: html.includes( '<style class="lstab-custom-css" data-lstab-css="0"' ),
	};
}, BASE );
check( publishedCss.hasBlock, 'The published page carries the table\'s own style block' );
check( publishedCss.scoped, 'And every rule in it names that table' );

// Put it back, so the rest of the run sees a plain table.
await page.goto( sourceEditUrl, { waitUntil: 'networkidle' } );
await page.waitForSelector( '.lstab-preview .lstab-table', { timeout: 15000 } );
await pane( 'look' );
await page.locator( '#lstab-custom-css' ).fill( '' );
await Promise.all( [
	page.waitForURL( /page=live-sheets-table/ ),
	page.locator( '#lstab-source-form button[type="submit"]' ).click(),
] );
await page.goto( sourceEditUrl, { waitUntil: 'networkidle' } );
await page.waitForSelector( '.lstab-preview .lstab-table', { timeout: 15000 } );

const clearedCss = await page.evaluate( async ( base ) => {
	const res = await fetch( base + '/cennik/', { credentials: 'same-origin' } );
	return ( await res.text() ).includes( 'lstab-custom-css' );
}, BASE );
check( ! clearedCss, 'Clearing the field takes the style block off the page again' );

// Switching tab re-fetches the preview.
section( '4. Switching sheet tab' );
await pane( 'general' );
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
		const bar = wrap.querySelector( '.lstab-scrollbar' );
		return {
			preset: wrap.className,
			hidden: scroll.scrollWidth - scroll.clientWidth,
			stacked: getComputedStyle( table ).display === 'block',
			slider: !! bar && ! bar.hidden && getComputedStyle( bar ).display !== 'none',
		};
	} );
} );

check( clipping.length === 2, 'Both tables are present to measure', String( clipping.length ) );

clipping.forEach( ( t ) => {
	// The rule is that a column is never hidden without a way to reach it. That
	// is satisfied three ways: the table fits, it stacked into cards, or it
	// scrolls and says so with a visible slider. What is not allowed — and what
	// used to happen — is a clipped table with none of the three.
	check(
		t.hidden <= 2 || t.stacked || t.slider,
		`No unreachable columns (${ t.preset.replace( /lstab-?/g, '' ).trim() })`,
		`${ t.hidden }px hidden, stacked=${ t.stacked }, slider=${ t.slider }`
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
section( '8. Front end — narrow screens and the slider' );
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
check( headDisplay === 'table-header-group', 'Column headings stay on screen', headDisplay );
// The slider is the whole point: a native overlay scrollbar is invisible until
// you already know to scroll, which is what made a clipped table look broken.
const sliderVisible = await mpage.locator( '.lstab-style-striped .lstab-scrollbar' ).isVisible();
check( sliderVisible, 'A scrolling table shows the slider on a phone' );

// The default is now a table that scrolls, so a phone keeps the tabular shape.
const phoneTable = await mpage.locator( '.lstab-style-striped .lstab-table' ).evaluate( ( el ) => getComputedStyle( el ).display );
check( phoneTable === 'table', 'A phone keeps the table rather than stacking it', phoneTable );

// The pinned first column is what makes sideways scrolling usable: without it
// the numbers on screen stop belonging to any particular row.
const pinned = await mpage.evaluate( async () => {
	const root = document.querySelector( '.lstab-style-striped' );
	const scroller = root.querySelector( '.lstab-scroll' );
	const firstCell = root.querySelector( 'tbody td:first-child' );
	const secondCell = root.querySelector( 'tbody td:nth-child(2)' );

	scroller.scrollLeft = 0;
	scroller.dispatchEvent( new Event( 'scroll' ) );
	await new Promise( ( r ) => requestAnimationFrame( r ) );

	const firstBefore = firstCell.getBoundingClientRect().left;
	const secondBefore = secondCell.getBoundingClientRect().left;

	scroller.scrollLeft = 200;
	scroller.dispatchEvent( new Event( 'scroll' ) );
	await new Promise( ( r ) => requestAnimationFrame( r ) );

	return {
		position: getComputedStyle( firstCell ).position,
		background: getComputedStyle( firstCell ).backgroundColor,
		firstBefore: Math.round( firstBefore ),
		firstAfter: Math.round( firstCell.getBoundingClientRect().left ),
		secondBefore: Math.round( secondBefore ),
		secondAfter: Math.round( secondCell.getBoundingClientRect().left ),
		width: Math.round( firstCell.getBoundingClientRect().width ),
		viewport: Math.round( scroller.clientWidth ),
		scrolledClass: root.classList.contains( 'lstab-is-scrolled' ),
	};
} );

check( pinned.position === 'sticky', 'The first column is pinned', pinned.position );
check(
	Math.abs( pinned.firstAfter - pinned.firstBefore ) <= 1,
	'The first column stays put while the rest scrolls',
	JSON.stringify( pinned )
);
check(
	pinned.secondAfter < pinned.secondBefore - 50,
	'The other columns really did move',
	JSON.stringify( pinned )
);
check(
	pinned.background !== 'rgba(0, 0, 0, 0)',
	'The pinned column is opaque, so rows do not show through it',
	pinned.background
);
check(
	pinned.width <= pinned.viewport * 0.5,
	'The pinned column cannot eat the screen; there is still room to scroll',
	`${ pinned.width }px of ${ pinned.viewport }px`
);
check( pinned.scrolledClass, 'A divider is shown once content is hidden behind the pinned column' );

// Dragging the slider must move the table, and the two must stay in step.
const dragResult = await mpage.evaluate( async () => {
	const root = document.querySelector( '.lstab-style-striped' );
	const scroller = root.querySelector( '.lstab-scroll' );
	const thumb = root.querySelector( '.lstab-scrollbar-thumb' );
	const track = root.querySelector( '.lstab-scrollbar-track' );

	const before = scroller.scrollLeft;
	const thumbBefore = thumb.getBoundingClientRect().left;
	const trackRect = track.getBoundingClientRect();

	const send = ( type, x ) => thumb.dispatchEvent( new PointerEvent( type, {
		bubbles: true, cancelable: true, pointerId: 1, clientX: x,
		clientY: trackRect.top + trackRect.height / 2,
	} ) );

	send( 'pointerdown', thumbBefore + 5 );
	send( 'pointermove', trackRect.right );
	send( 'pointerup', trackRect.right );

	await new Promise( ( r ) => requestAnimationFrame( r ) );

	return {
		before,
		after: scroller.scrollLeft,
		max: scroller.scrollWidth - scroller.clientWidth,
		thumbAfter: thumb.getBoundingClientRect().left,
		thumbBefore,
	};
} );

check( dragResult.max > 0, 'The phone-width table really does overflow', JSON.stringify( dragResult ) );
check( dragResult.after > dragResult.before, 'Dragging the slider scrolls the table', JSON.stringify( dragResult ) );
check( dragResult.thumbAfter > dragResult.thumbBefore, 'The thumb follows the drag', JSON.stringify( dragResult ) );

// And scrolling the table must move the slider back.
const syncResult = await mpage.evaluate( async () => {
	const root = document.querySelector( '.lstab-style-striped' );
	const scroller = root.querySelector( '.lstab-scroll' );
	const thumb = root.querySelector( '.lstab-scrollbar-thumb' );

	scroller.scrollLeft = 0;
	scroller.dispatchEvent( new Event( 'scroll' ) );
	await new Promise( ( r ) => requestAnimationFrame( r ) );

	return {
		transform: thumb.style.transform,
		valuenow: thumb.getAttribute( 'aria-valuenow' ),
	};
} );
check( syncResult.valuenow === '0', 'Scrolling the table moves the slider back', JSON.stringify( syncResult ) );

// Keyboard users need it too.
const keyboardResult = await mpage.evaluate( async () => {
	const root = document.querySelector( '.lstab-style-striped' );
	const scroller = root.querySelector( '.lstab-scroll' );
	const thumb = root.querySelector( '.lstab-scrollbar-thumb' );

	scroller.scrollLeft = 0;
	thumb.focus();
	thumb.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'End', bubbles: true, cancelable: true } ) );
	await new Promise( ( r ) => requestAnimationFrame( r ) );

	return { scrollLeft: Math.round( scroller.scrollLeft ), max: Math.round( scroller.scrollWidth - scroller.clientWidth ) };
} );
check(
	keyboardResult.scrollLeft === keyboardResult.max && keyboardResult.max > 0,
	'End key jumps the table to its far edge',
	JSON.stringify( keyboardResult )
);

// A table that fits must not show a slider at all.
await mpage.setViewportSize( { width: 1500, height: 900 } );
await mpage.waitForTimeout( 400 );
const wideSlider = await mpage.evaluate( () => {
	const root = document.querySelector( '.lstab-style-striped' );
	const scroller = root.querySelector( '.lstab-scroll' );
	return {
		hidden: root.querySelector( '.lstab-scrollbar' ).hidden,
		overflow: Math.round( scroller.scrollWidth - scroller.clientWidth ),
	};
} );
check( wideSlider.overflow <= 2 && wideSlider.hidden, 'A table that fits shows no slider', JSON.stringify( wideSlider ) );
await mpage.setViewportSize( { width: 390, height: 900 } );
await mpage.waitForTimeout( 400 );

// Full-size text is the point of scrolling rather than shrinking: the competitor
// complaint was microscopic type, not sideways movement.
const phoneTypography = await mpage.evaluate( () => {
	const root = document.querySelector( '.lstab-style-striped' );
	return {
		cell: parseFloat( getComputedStyle( root.querySelector( 'tbody td' ) ).fontSize ),
		body: parseFloat( getComputedStyle( document.body ).fontSize ),
	};
} );
check(
	phoneTypography.cell >= phoneTypography.body * 0.8,
	'Text stays full size on a phone; the table scrolls instead of shrinking',
	JSON.stringify( phoneTypography )
);

// The card layout is still available, and still works, when a source asks for it.
await mpage.setViewportSize( { width: 560, height: 900 } );
await mpage.waitForTimeout( 200 );
const cardsOnDemand = await mpage.evaluate( () => {
	const root = document.querySelector( '.lstab-style-striped' );
	root.classList.remove( 'lstab-layout-table' );
	root.classList.add( 'lstab-layout-cards' );
	const table = root.querySelector( '.lstab-table' );
	const cell = root.querySelector( 'tbody td' );
	const result = {
		table: getComputedStyle( table ).display,
		cell: getComputedStyle( cell ).display,
		slider: getComputedStyle( root.querySelector( '.lstab-scrollbar' ) ).display,
	};
	root.classList.remove( 'lstab-layout-cards' );
	root.classList.add( 'lstab-layout-table' );
	return result;
} );
check( cardsOnDemand.table === 'block', 'The card layout still stacks when chosen', JSON.stringify( cardsOnDemand ) );
check( cardsOnDemand.cell === 'grid', 'Card cells still use the label/value grid', JSON.stringify( cardsOnDemand ) );
check( cardsOnDemand.slider === 'none', 'Cards have nothing to scroll, so no slider', JSON.stringify( cardsOnDemand ) );
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
await mpage.screenshot( { path: `${ SHOTS }/05b-frontend-narrow-slider.png`, fullPage: false } );
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

await mpage.screenshot( { path: `${ SHOTS }/05-frontend-mobile-slider.png`, fullPage: true } );

// ------------------------------------------------------- failure → fallback
section( '9. Sync failure — visitor still sees the last good copy' );
setMock( 'http_403' );

await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table`, { waitUntil: 'networkidle' } );
await Promise.all( [
	page.waitForURL( /page=live-sheets-table/ ),
	page.locator( '.lstab-src button:has-text("Refresh")' ).first().click(),
] );
await page.waitForLoadState( 'networkidle' );

const failedStatus = await page.locator( '.lstab-state' ).first().innerText();
check( /Google did not answer/.test( failedStatus ), 'Dashboard shows the sync error', failedStatus );

const detail = await page.locator( '.lstab-src-note' ).first().innerText();
check( /last good copy/.test( detail ), 'Dashboard says the public page is unaffected', detail );
// The reassurance must not cost the diagnosis: a fault with no stated cause is
// how a sheet stays broken for a week.
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

// ------------------------------------------------------- columns and widths
section( '9a. Column settings' );
setMock( 'ok' );

await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table-edit&source=${ sourceId }`, { waitUntil: 'networkidle' } );
await page.waitForSelector( '.lstab-preview .lstab-table', { timeout: 20000 } );
// The pane is chosen once the editor is on screen; asking for it on the list
// screen, where the tabs do not exist, quietly does nothing.
await pane( 'hide' );

/*
 * `hidden` is only a display rule, and the grid sets its own display, so it
 * once stayed on screen while marked hidden — the preview hung beside an
 * empty column. Checking what is painted, not what is marked.
 */
/*
 * Renaming a column is worth watching happen, so the preview stays beside the
 * column list. `hidden` is only a display rule and the grid sets its own
 * display, so this checks what is painted rather than what is marked.
 */
check(
	await page.locator( '.lstab-preview-pane' ).isVisible(),
	'The preview stays beside the column settings'
);
check(
	await page.locator( '.lstab-columns-card' ).isVisible(),
	'And the column settings are on screen'
);
check(
	! ( await page.locator( '[data-lstab-pane="look"]' ).isVisible() ),
	'While the appearance pane is really gone, not merely marked hidden'
);

// The card once sat outside the form, so nothing typed into it was ever sent.
check(
	await page.locator( '#lstab-source-form .lstab-column-list' ).count() === 1,
	'Columns card is inside the form'
);
check(
	await page.locator( '#lstab-source-form .lstab-submit button[type=submit]' ).count() === 1,
	'Save button is inside the form'
);

const columnRows = page.locator( '.lstab-column-list tbody tr' );
check( await columnRows.count() === 5, 'One row per column in the sheet', String( await columnRows.count() ) );

// Whether a column is in the table is the add-on's to change; here it is only
// reported, and carried back unchanged so that saving cannot alter it.
check(
	await page.locator( '.lstab-column-list tbody tr input[name$="[hidden]"]' ).count() === 5,
	'Every column carries its state back with the form'
);
check(
	await page.locator( '.lstab-column-list tbody tr input[type=checkbox]' ).count() === 0,
	'And there is no control here for changing it'
);

const labelField = columnRows.nth( 0 ).locator( 'input[type=text]' );
await labelField.fill( 'Nazwa produktu' );
await labelField.blur();
await page.waitForFunction( () => {
	const th = document.querySelector( '.lstab-preview thead th' );
	return th && /NAZWA PRODUKTU/i.test( th.innerText );
}, null, { timeout: 20000 } ).catch( () => {} );
check(
	/NAZWA PRODUKTU/i.test( await page.locator( '.lstab-preview thead th' ).first().innerText() ),
	'Renaming a column updates the preview'
);

await page.locator( '.lstab-submit button[type=submit]' ).click();
await page.waitForLoadState( 'networkidle' );
await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table-edit&source=${ sourceId }`, { waitUntil: 'networkidle' } );

// Coming back should land on the pane you were working in, not throw you to
// the front of the form. Stated here too, so the check does not depend on it.
check(
	await page.locator( '[data-lstab-goto="hide"]' ).evaluate( ( el ) => el.classList.contains( 'is-on' ) ),
	'Reopening the editor returns to the pane you were on'
);
await pane( 'hide' );

check(
	await columnRows.nth( 0 ).locator( 'input[type=text]' ).inputValue() === 'Nazwa produktu',
	'The new name survives the save'
);
const savedStates = await page.locator( '.lstab-column-list tbody tr input[name$="[hidden]"]' ).evaluateAll( ( els ) => els.map( ( e ) => e.value ) );
check(
	JSON.stringify( savedStates ) === JSON.stringify( [ '0', '0', '0', '0', '0' ] ),
	'Saving from here leaves every column exactly as it was',
	JSON.stringify( savedStates )
);
await page.locator( '.lstab-columns-card' ).screenshot( { path: `${ SHOTS }/13-columns-card.png` } );

await apage.goto( `${ BASE }/cennik/`, { waitUntil: 'networkidle' } );
await apage.waitForTimeout( 400 );
const publishedHeads = await apage.locator( '.lstab' ).first().locator( 'thead th' ).allInnerTexts();
check( publishedHeads.length === 5, 'The published page shows every column', String( publishedHeads.length ) );
check( /NAZWA PRODUKTU/i.test( publishedHeads[ 0 ] ), 'The published page uses the new name', publishedHeads[ 0 ] );

section( '9c. The plugin\'s own screens' );

// remove_submenu_page() looks like the way to keep a screen off the sidebar and
// is not: it also removes the right to open it, and the page then answers with
// "Sorry, you are not allowed". Only a real request finds that out.
const settingsResponse = await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table-settings`, { waitUntil: 'networkidle' } );
check( settingsResponse.status() === 200, 'The settings screen opens', String( settingsResponse.status() ) );
check(
	! ( await page.locator( 'body' ).innerText() ).includes( 'not allowed' ),
	'And is allowed, not merely registered'
);

const tabLabels = await page.locator( '.lstab-tabs .nav-tab' ).allInnerTexts();
check( tabLabels.length >= 2, 'The screens are tabs across the top', JSON.stringify( tabLabels ) );
check(
	await page.locator( '.lstab-tabs .nav-tab-active' ).innerText() === 'Settings',
	'And the tab you are on is the one marked'
);

const sidebar = await page.locator( '#adminmenu a[href*="live-sheets-table"]' ).evaluateAll( ( els ) => els.map( ( e ) => e.getAttribute( 'href' ) ) );
check(
	! sidebar.some( ( href ) => href && href.includes( 'live-sheets-table-settings' ) ),
	'Settings is not also a line in the sidebar',
	JSON.stringify( sidebar )
);

/*
 * Saving from here must not disturb anything it does not show. Waiting for the
 * address the save redirects to, rather than for the network to go quiet: the
 * quiet moment can be the page we are still standing on, and then the check
 * reads the screen from before the save.
 */
await page.selectOption( 'select[name="lstab_settings[default_interval]"]', '3600' );
await Promise.all( [ page.waitForURL( /lstab-saved/ ), page.locator( '.lstab-submit button[type=submit]' ).click() ] );
check(
	( await page.locator( 'body' ).innerText() ).includes( 'Settings saved' ),
	'Saving says so'
);
check(
	await page.locator( 'select[name="lstab_settings[default_interval]"]' ).inputValue() === '3600',
	'And the choice sticks'
);
await page.locator( '.wrap' ).first().screenshot( { path: `${ SHOTS }/38-settings.png` } );

await page.selectOption( 'select[name="lstab_settings[default_interval]"]', '0' );
await Promise.all( [ page.waitForURL( /lstab-saved/ ), page.locator( '.lstab-submit button[type=submit]' ).click() ] );

section( '9b. Even column widths' );

const widthReport = await apage.locator( '.lstab' ).evaluateAll( ( els ) => els.map( ( el ) => {
	const scroll = el.querySelector( '.lstab-scroll' );
	const table = el.querySelector( '.lstab-table' );
	const cols = [ ...table.querySelectorAll( 'thead th' ) ].map( ( e ) => Math.round( e.getBoundingClientRect().width ) );
	return {
		even: el.classList.contains( 'lstab-even' ),
		overflow: scroll.scrollWidth - scroll.clientWidth,
		spread: cols.length ? Math.max( ...cols ) - Math.min( ...cols ) : 0,
		cols
	};
} ) );

// The invariant that matters: asking for equal shares must never be the reason
// a table starts to scroll. Percentage widths make a table report a wider
// max-content, and left unchecked that turned an 811px table into 1826px.
check(
	widthReport.every( ( r ) => ! r.even || r.overflow <= 2 ),
	'Even columns never push a table into scrolling',
	JSON.stringify( widthReport )
);
check(
	widthReport.some( ( r ) => r.even ),
	'A table with room to spare gets even columns',
	JSON.stringify( widthReport )
);
widthReport.filter( ( r ) => r.even ).forEach( ( r, i ) => {
	check( r.spread <= 4, `Evened table ${ i + 1 } really has equal columns`, r.cols.join( '/' ) );
} );
await apage.locator( '.lstab' ).first().screenshot( { path: `${ SHOTS }/14-even-columns.png` } );

// Put the source back the way the rest of the run expects to find it.
await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table-edit&source=${ sourceId }`, { waitUntil: 'networkidle' } );
await columnRows.nth( 0 ).locator( 'input[type=text]' ).fill( '' );
await page.locator( '.lstab-submit button[type=submit]' ).click();
await page.waitForLoadState( 'networkidle' );

// The panel that settles "is it the sheet or the plugin?".
await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table-edit&source=${ sourceId }`, { waitUntil: 'networkidle' } );
await page.waitForSelector( '.lstab-preview .lstab-table', { timeout: 20000 } );
await page.waitForTimeout( 400 );
const rawPanel = page.locator( '#lstab-raw-wrap' );
check( await rawPanel.count() === 1 && ! ( await rawPanel.isHidden() ), 'The source screen can show what Google sent' );
await page.locator( '#lstab-raw-wrap summary' ).click();
const rawValue = await page.locator( '#lstab-raw' ).inputValue();
check( /Produkt,Cena netto/.test( rawValue ), 'It shows the text as it arrived, not the parsed table', rawValue.slice( 0, 50 ) );
check( /\n/.test( rawValue ), 'Line breaks are kept, since that is what it is for' );

// ------------------------------------------------- malformed sheet, and links
section( '9c. A sheet that arrives malformed' );

// The fetch succeeds, so nothing else in the plugin notices. Only someone who
// can fix it is told, and the public page carries on as normal.
setMock( 'ragged' );
await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table`, { waitUntil: 'networkidle' } );
await page.locator( '.lstab-src button:has-text("Refresh")' ).first().click();
await page.waitForLoadState( 'networkidle' );

const raggedNotice = await page.locator( '.lstab-src-note--warn' ).count();
check( raggedNotice === 1, 'The sources list flags the malformed row', String( raggedNotice ) );
const raggedText = raggedNotice ? await page.locator( '.lstab-src-note--warn' ).first().innerText() : '';
check( /\d/.test( raggedText ), 'It names a row number to look at', raggedText );
check( ! /error/i.test( await page.locator( '.lstab-state' ).first().innerText() ), 'It is not reported as a failed sync' );
await page.screenshot( { path: `${ SHOTS }/18-ragged-warning.png`, fullPage: false } );

await apage.goto( `${ BASE }/cennik/`, { waitUntil: 'networkidle' } );
const publicBody = await apage.locator( 'body' ).innerText();
check( await apage.locator( '.lstab-table' ).count() > 0, 'The public page still renders a table' );
check( ! /quotation mark|cudzysłów/i.test( publicBody ), 'The visitor is told nothing about it' );

// Back to a clean sheet; the warning must clear itself rather than linger.
setMock( 'ok' );
await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table`, { waitUntil: 'networkidle' } );
await page.locator( '.lstab-src button:has-text("Refresh")' ).first().click();
await page.waitForLoadState( 'networkidle' );
check( await page.locator( '.lstab-src-note--warn' ).count() === 0, 'A clean sync clears the warning' );

// The message straight after the action, and everywhere else in the dashboard.
setMock( 'ragged' );
await page.locator( '.lstab-src button:has-text("Refresh")' ).first().click();
await page.waitForLoadState( 'networkidle' );
const syncNotice = page.locator( '.notice.is-dismissible' ).first();
const syncText = await syncNotice.innerText();
check( /notice-warning/.test( await syncNotice.getAttribute( 'class' ) ), 'The message after a sync is a warning, not a plain success', await syncNotice.getAttribute( 'class' ) );
check( /\d/.test( syncText ) && syncText.length > 40, 'It says what was wrong, not just that it synced', syncText.replace( /\s+/g, ' ' ).slice( 0, 90 ) );
await syncNotice.screenshot( { path: `${ SHOTS }/24-after-sync-notice.png` } );

await page.goto( `${ BASE }/wp-admin/index.php`, { waitUntil: 'networkidle' } );
const elsewhere = page.locator( '.notice-warning:has-text("Live Sheets Table")' );
check( await elsewhere.count() === 1, 'The warning follows you around the dashboard, not only the plugin screens' );
check( /\brow 3\b/i.test( await elsewhere.first().innerText() ), 'And still names the row', ( await elsewhere.first().innerText() ).replace( /\s+/g, ' ' ).slice( 0, 90 ) );
await elsewhere.first().screenshot( { path: `${ SHOTS }/23-global-notice.png` } );

await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table`, { waitUntil: 'networkidle' } );
check(
	await page.locator( '.notice-warning:has-text("Live Sheets Table:")' ).count() === 0,
	'It is not repeated on the screen that already shows it beside the source'
);

// Dismissing silences this fault; a fault that comes back is heard again.
await page.goto( `${ BASE }/wp-admin/index.php`, { waitUntil: 'networkidle' } );
await page.locator( 'a:has-text("Hide this")' ).first().click();
await page.waitForLoadState( 'networkidle' );
check( await page.locator( '.notice-warning:has-text("Live Sheets Table")' ).count() === 0, 'Dismissing it works' );

setMock( 'ok' );
await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table`, { waitUntil: 'networkidle' } );
await page.locator( '.lstab-src button:has-text("Refresh")' ).first().click();
await page.waitForLoadState( 'networkidle' );
setMock( 'ragged' );
await page.locator( '.lstab-src button:has-text("Refresh")' ).first().click();
await page.waitForLoadState( 'networkidle' );
await page.goto( `${ BASE }/wp-admin/index.php`, { waitUntil: 'networkidle' } );
check(
	await page.locator( '.notice-warning:has-text("Live Sheets Table")' ).count() === 1,
	'A fault that returns after being dismissed is raised again'
);

setMock( 'ok' );
await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table`, { waitUntil: 'networkidle' } );
await page.locator( '.lstab-src button:has-text("Refresh")' ).first().click();
await page.waitForLoadState( 'networkidle' );

section( '9d. Links in cells' );

await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table-edit&source=${ sourceId }`, { waitUntil: 'networkidle' } );
await pane( 'look' );
const linkToggle = page.locator( 'input[name="link_cells"]' );
check( await linkToggle.count() === 1, 'The source screen offers the setting' );
check( await linkToggle.isChecked(), 'It is on for a new source' );

await linkToggle.uncheck();
await page.locator( '.lstab-submit button[type=submit]' ).click();
await page.waitForLoadState( 'networkidle' );
await page.goto( `${ BASE }/wp-admin/admin.php?page=live-sheets-table-edit&source=${ sourceId }`, { waitUntil: 'networkidle' } );
await pane( 'look' );
check( ! ( await page.locator( 'input[name="link_cells"]' ).isChecked() ), 'Turning it off sticks' );

await page.locator( 'input[name="link_cells"]' ).check();
await page.locator( '.lstab-submit button[type=submit]' ).click();
await page.waitForLoadState( 'networkidle' );

// A link that reads as body text is not obviously a link, which defeats the
// point of making them clickable.
await apage.goto( `${ BASE }/cennik/`, { waitUntil: 'networkidle' } );
await apage.waitForTimeout( 300 );
const linkStyle = await apage.evaluate( () => {
	const table = document.querySelector( '.lstab' );
	const cell = table ? table.querySelector( '.lstab-cell-value' ) : null;
	if ( ! cell ) { return null; }
	const accent = getComputedStyle( table ).getPropertyValue( '--lstab-accent' ).trim();
	return { accent, body: getComputedStyle( cell ).color };
} );
check( linkStyle && linkStyle.accent !== '', 'The table exposes an accent colour for links to use', JSON.stringify( linkStyle ) );

// Without the add-on there is nothing listening for a filter, so the field is
// not offered — a control that accepts text and changes nothing is worse than
// no control.
check(
	! ( await page.evaluate( () => !! ( window.lstabBlock && window.lstabBlock.isPro ) ) ),
	'The free build does not claim to be Pro'
);

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
