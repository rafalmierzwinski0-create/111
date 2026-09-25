/**
 * A colour rule being typed, as the dashboard sees it.
 *
 * The card here is the one tests/rules-preview-test.php rendered from the view
 * itself, with the real admin script loaded on top — so this is the screen
 * somebody actually uses. What it checks is the half the PHP suite cannot: that
 * the browser collects what has been typed and hands it to the preview, and
 * that the swatch answers a change before any of it is saved.
 *
 * Usage: node tests/rules-preview-browser.mjs
 */

import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const repo = path.join( here, '..' );
const cardPath = path.join( here, 'fixtures/rules-card.html' );

if ( ! fs.existsSync( cardPath ) ) {
	console.error( 'fixtures/rules-card.html is missing — run php tests/rules-preview-test.php first.' );
	process.exit( 1 );
}

const markup = fs.readFileSync( cardPath, 'utf8' );
const adminJs = fs.readFileSync( path.join( repo, 'live-sheets-table-pro/assets/js/lstabp-admin.js' ), 'utf8' );
const adminCss = fs.readFileSync( path.join( repo, 'live-sheets-table-pro/assets/css/lstabp-admin.css' ), 'utf8' );
const tableCss = fs.readFileSync( path.join( repo, 'live-sheets-table/assets/css/lstab-table.css' ), 'utf8' );

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
		console.log( '       ', detail );
	}
};

const file = path.join( repo, 'build/rules-card.html' );

fs.mkdirSync( path.dirname( file ), { recursive: true } );
fs.writeFileSync( file, `<!doctype html><meta charset="utf-8"><style>${ tableCss }${ adminCss }</style>${ markup }<script>${ adminJs }</script>` );

const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
const tab = await browser.newPage( { viewport: { width: 1200, height: 800 } } );
const errors = [];

tab.on( 'pageerror', ( e ) => errors.push( e.message ) );
await tab.goto( 'file://' + file );
await tab.waitForTimeout( 200 );

const collected = () => tab.evaluate( () => {
	const fields = {};

	( window.lstabPreviewFields || [] ).forEach( ( collect ) => Object.assign( fields, collect() ) );

	return fields;
} );

console.log( '\nWhat the card hands the preview' );

const first = await collected();

check( Array.isArray( first.rules ), 'the rules travel with every preview request', JSON.stringify( first.rules ) );
check( first.rules.length === 1, 'a blank line waiting at the bottom is not a rule', `${ first.rules.length }` );
check(
	first.rules[ 0 ] && 'Status' === first.rules[ 0 ].column && 'Full' === first.rules[ 0 ].value && 'pill' === first.rules[ 0 ].scope,
	'the saved rule is handed over as it stands',
	JSON.stringify( first.rules[ 0 ] )
);

console.log( '\nA rule being changed, before anything is saved' );

const line = '.lstabp-rule:first-child';

await tab.fill( `${ line } .lstabp-rule-value`, 'Waitlist' );
await tab.selectOption( `${ line } select[name*="[scope]"]`, 'dot' );
await tab.waitForTimeout( 120 );

const changed = await collected();

check(
	changed.rules[ 0 ] && 'Waitlist' === changed.rules[ 0 ].value && 'dot' === changed.rules[ 0 ].scope,
	'what was typed a moment ago is what the preview is told',
	JSON.stringify( changed.rules[ 0 ] )
);

const swatch = await tab.evaluate( ( selector ) => {
	const face = document.querySelector( selector + ' .lstabp-swatch' );
	const before = getComputedStyle( face, '::before' );

	return {
		classes: face.className,
		style: face.getAttribute( 'style' ),
		dotDrawn: before.content !== 'none',
		dotColour: before.backgroundColor,
	};
}, line );

check( swatch.classes.includes( 'lstabp-dot-face' ), 'the swatch answers the change at once', swatch.classes );
check( ! swatch.classes.includes( 'lstabp-pill-face' ), 'and stops wearing the shape it had' );
check( swatch.dotDrawn, 'the dot on the swatch is really drawn', swatch.style );

// A colour picked from the palette, and the swatch again.
await tab.click( `${ line } .lstabp-paint-chip:nth-of-type(3) input` );
await tab.waitForTimeout( 120 );

const repainted = await tab.evaluate( ( selector ) => {
	const face = document.querySelector( selector + ' .lstabp-swatch' );

	return { style: face.getAttribute( 'style' ), chosen: document.querySelector( selector + ' .lstabp-style-input:checked' ).value };
}, line );

check(
	repainted.style.includes( repainted.chosen ),
	'a colour picked from the palette reaches the swatch',
	`${ repainted.chosen } → ${ repainted.style }`
);

const afterColour = await collected();

check(
	afterColour.rules[ 0 ].style === repainted.chosen,
	'and reaches the preview too',
	JSON.stringify( afterColour.rules[ 0 ] )
);

console.log( '\nAn empty line is still not a rule' );

await tab.fill( `${ line } .lstabp-rule-value`, '' );
await tab.selectOption( `${ line } .lstabp-rule-column`, '' );
await tab.waitForTimeout( 120 );

const emptied = await collected();

check( emptied.rules.length === 0, 'a rule with no column named is dropped, not sent half-made', JSON.stringify( emptied.rules ) );

check( errors.length === 0, 'no errors in the console', errors.join( ' | ' ) );

await browser.close();

console.log( `\n${ passed } passed, ${ failed } failed` );
process.exit( failed > 0 ? 1 : 0 );
