/**
 * Every screen of the plugin in the dashboard, at phone, tablet and desk
 * widths: nothing may push the screen sideways or run out of its card.
 *
 * WordPress's own admin stylesheet is written for the desk. A text field it
 * sizes at 25em, a table whose first column refuses to wrap, a line of code
 * with no spaces in it: each fits on a laptop and pushes a phone's screen
 * sideways, and nobody tries the dashboard on a phone until a customer does.
 * The columns card broke that way, with the headings of the sheet standing one
 * letter to a line.
 *
 * For each screen, and each pane of the edit screen, the browser is asked:
 *   - does the page scroll sideways;
 *   - does anything stand past the edge of the content area, other than what
 *     sits in a box made to scroll;
 *   - does any block of text run out of its own box.
 *
 * Usage: node tests/admin-sweep.mjs   (servers from tests/setup-env.sh, the
 * demo source seeded, the add-on active so its cards are on the screens too)
 */
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';

const BASE = process.env.LSTAB_BASE || 'http://127.0.0.1:8089';

const screens = [
	[ 'start', 'live-sheets-table', [] ],
	[ 'sources', 'live-sheets-table-sources', [] ],
	[ 'edit', 'live-sheets-table-edit&source=1', [ 'general', 'look', 'hide' ] ],
	[ 'new sheet', 'live-sheets-table-edit', [ 'general', 'look', 'hide' ] ],
	[ 'settings', 'live-sheets-table-settings', [] ],
	[ 'pro', 'live-sheets-table-pro', [] ],
];

const inspect = () => {
	const out = [];
	const vw = document.documentElement.clientWidth;

	if ( ! document.querySelector( '.lstab-admin, .lstab-masthead, .lstab-start' ) ) {
		return [ 'the plugin screen did not draw' ];
	}

	if ( document.documentElement.scrollWidth > vw + 1 ) {
		out.push( `the page scrolls sideways: ${ document.documentElement.scrollWidth } > ${ vw }` );
	}

	const area = document.querySelector( '#wpbody-content' );
	const edge = area ? area.getBoundingClientRect().right : vw;
	const scrolls = ( el ) => {
		for ( let up = el.parentElement; up && up !== document.body; up = up.parentElement ) {
			if ( /(auto|scroll|hidden|clip)/.test( getComputedStyle( up ).overflowX ) ) {
				return true;
			}
		}
		return false;
	};
	const name = ( el ) => `${ el.tagName.toLowerCase() }${ el.className && 'string' === typeof el.className ? '.' + el.className.trim().split( /\s+/ ).slice( 0, 2 ).join( '.' ) : '' }`;

	document.querySelectorAll( '#wpbody-content *' ).forEach( ( el ) => {
		const box = el.getBoundingClientRect();
		const style = getComputedStyle( el );

		if ( ! box.width || ! box.height || 'hidden' === style.visibility || 'fixed' === style.position || scrolls( el ) ) {
			return;
		}

		if ( box.right > edge + 1 || box.left < -1 ) {
			out.push( `${ name( el ) } stands at ${ Math.round( box.left ) }–${ Math.round( box.right ) }, the content ends at ${ Math.round( edge ) }` );
		}
	} );

	document.querySelectorAll( '#wpbody-content :is(p, label, span, button, a, code, h1, h2, h3, li, td, th)' ).forEach( ( el ) => {
		const style = getComputedStyle( el );

		if ( 'inline' === style.display || 'visible' !== style.overflowX || ! el.clientWidth || scrolls( el ) ) {
			return;
		}

		if ( el.scrollWidth > el.clientWidth + 2 ) {
			out.push( `text runs out of ${ name( el ) }: ${ el.scrollWidth } > ${ el.clientWidth } "${ el.textContent.trim().slice( 0, 30 ) }"` );
		}
	} );

	return [ ...new Set( out ) ];
};

const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
const page = await ( await browser.newContext( { viewport: { width: 1280, height: 900 } } ) ).newPage();
const errors = [];
page.on( 'pageerror', ( error ) => errors.push( error.message ) );

await page.goto( `${ BASE }/wp-login.php` );
await page.fill( '#user_login', 'admin' );
await page.fill( '#user_pass', 'admin123' );
await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] );

let failed = 0;
let looked = 0;

for ( const width of [ 360, 600, 782, 1024, 1440 ] ) {
	await page.setViewportSize( { width, height: 900 } );
	const problems = [];

	for ( const [ label, slug, panes ] of screens ) {
		await page.goto( `${ BASE }/wp-admin/admin.php?page=${ slug }`, { waitUntil: 'networkidle' } );

		for ( const pane of panes.length ? panes : [ '' ] ) {
			if ( pane ) {
				await page.evaluate( ( which ) => {
					const tab = document.querySelector( `[data-lstab-goto="${ which }"]` );

					if ( tab ) {
						tab.click();
					}
				}, pane );
				await page.waitForTimeout( 300 );
			}

			looked++;
			( await page.evaluate( inspect ) ).slice( 0, 6 ).forEach( ( found ) => problems.push( `${ label }${ pane ? ' / ' + pane : '' }: ${ found }` ) );
		}
	}

	if ( problems.length ) {
		failed += problems.length;
		console.log( `  \x1b[31mFAIL\x1b[0m  ${ width }px: ${ problems.length } problems` );
		problems.slice( 0, 12 ).forEach( ( line ) => console.log( `        ${ line }` ) );
	} else {
		console.log( `  \x1b[32mPASS\x1b[0m  ${ width }px: every screen keeps to the width it is given` );
	}
}

if ( errors.length ) {
	failed++;
	console.log( `  \x1b[31mFAIL\x1b[0m  script errors: ${ [ ...new Set( errors ) ].join( ' | ' ) }` );
} else {
	console.log( '  \x1b[32mPASS\x1b[0m  no script errors on any screen' );
}

await browser.close();

console.log( `\n  ${ looked } screens looked at, ${ failed } problems` );
process.exit( failed ? 1 : 0 );
