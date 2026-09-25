/**
 * A column's look, as a browser actually draws it.
 *
 * The table here is built from fixtures/column-looks-php-said.json, which
 * tests/column-looks-test.php writes from the class itself — so what is checked
 * is the markup the plugin really produces, not a hand-typed imitation of it.
 * Run that suite first.
 *
 * Usage: node tests/column-looks-browser.mjs
 */

import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const repo = path.join( here, '..' );
const said = path.join( here, 'fixtures/column-looks-php-said.json' );

if ( ! fs.existsSync( said ) ) {
	console.error( 'fixtures/column-looks-php-said.json is missing — run php tests/column-looks-test.php first.' );
	process.exit( 1 );
}

const CSS = fs.readFileSync( path.join( repo, 'live-sheets-table/assets/css/lstab-table.css' ), 'utf8' );
const table = JSON.parse( fs.readFileSync( said, 'utf8' ) );

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

const attrs = ( map ) => Object.entries( map )
	.map( ( [ name, value ] ) => `${ name }="${ String( value ).replace( /"/g, '&quot;' ) }"` ).join( ' ' );

const head = table.headers.map( ( name, i ) =>
	`<th data-lstab-col="${ i }" data-lstab-align="${ 1 === i ? 'end' : 'start' }"><button type="button" class="lstab-sort"><span class="lstab-sort-label">${ name }</span></button></th>` ).join( '' );

const body = table.rows.map( ( row ) => '<tr class="lstab-row">' + row.map( ( cell, i ) =>
	`<td role="cell" ${ attrs( cell.attributes ) }><span class="lstab-cell-label">${ table.headers[ i ] }</span><span class="lstab-cell-value">${ cell.html || cell.value }</span></td>` ).join( '' ) + '</tr>' ).join( '' );

// A dot and a pill beside it, the two shapes a rule can wear.
const shapes = `<div class="lstab-container"><div class="lstab lstab-style-clean lstab-cols-2" id="shapes"><table class="lstab-table"><tbody>
<tr class="lstab-row"><td><span class="lstab-cell-value">Dot</span></td>
<td class="lstab-ruled lstabp-dot" style="--lstabp-dot:#e11d48;"><span class="lstab-cell-value">Full</span></td></tr>
<tr class="lstab-row"><td><span class="lstab-cell-value">Pill</span></td>
<td class="lstab-ruled lstabp-pill" style="--lstabp-pill-line:#5fe3cf;--lstabp-pill-fill:color-mix(in srgb,#5fe3cf 18%,transparent);--lstabp-pill-ink:color-mix(in srgb,#5fe3cf 55%,currentColor);"><span class="lstab-cell-value">Open</span></td></tr>
</tbody></table></div></div>`;

const page = `<!doctype html><meta charset="utf-8"><style>${ CSS }
body { margin: 0; padding: 20px; background: #fff; font-family: system-ui, sans-serif; }
.dark { background: #0d1513; color: #e9f4f1; padding: 20px; margin-top: 24px; }
.dark .lstab { --lstab-bg: #0d1513; --lstab-fg: #e9f4f1; --lstab-border: #1f2e2b; --lstab-head-bg: #121d1b;
	--lstab-head-fg: #9fb8b4; --lstab-fg-faint: #85a09c; --lstab-accent: #5fe3cf; --lstab-sticky-bg: #0d1513; }
</style>
<div class="lstab-container"><div class="lstab lstab-style-clean lstab-cols-3 lstab-sticky-first" id="light">
<table class="lstab-table"><thead><tr>${ head }</tr></thead><tbody>${ body }</tbody></table></div></div>
${ shapes }
<div class="dark"><div class="lstab-container"><div class="lstab lstab-style-clean lstab-cols-3" id="night">
<table class="lstab-table"><thead><tr>${ head }</tr></thead><tbody>${ body }</tbody></table></div></div></div>`;

const file = path.join( repo, 'build/column-looks.html' );

fs.mkdirSync( path.dirname( file ), { recursive: true } );
fs.writeFileSync( file, page );

const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
const tab = await browser.newPage( { viewport: { width: 1100, height: 900 } } );
const errors = [];

tab.on( 'pageerror', ( e ) => errors.push( e.message ) );
await tab.goto( 'file://' + file );
await tab.waitForTimeout( 300 );

console.log( '\nThe bar' );

const bars = await tab.evaluate( () => [ ...document.querySelectorAll( '#light .lstabp-bar' ) ].map( ( cell ) => {
	const drawn = getComputedStyle( cell, '::after' );
	const value = cell.querySelector( '.lstab-cell-value' );

	return {
		says: value.textContent,
		wanted: getComputedStyle( cell ).getPropertyValue( '--lstabp-bar' ).trim(),
		width: parseFloat( drawn.width ),
		cell: cell.getBoundingClientRect().width,
		colour: drawn.backgroundColor,
		opacity: parseFloat( drawn.opacity ),
		valueOnTop: getComputedStyle( value ).position,
	};
} ) );

// Four rows carry a number, zero among them — and zero gets a sliver rather
// than nothing, so that a row with a value can never look like a blank.
check( bars.length === 4, 'a bar on every row that has a number', `${ bars.length } bars` );

for ( const bar of bars ) {
	const share = parseFloat( bar.wanted );
	const drawn = bar.width / bar.cell * 100;

	check(
		Math.abs( drawn - share ) < 1.5,
		`"${ bar.says }" — the bar is ${ share.toFixed( 0 ) }% of the cell`,
		`drawn ${ drawn.toFixed( 1 ) }%`
	);
}

check(
	bars.every( ( b ) => b.colour.startsWith( 'rgb(95, 227, 207' ) ),
	'the bar wears the colour that was chosen',
	bars.map( ( b ) => b.colour ).join( ' | ' )
);
check(
	bars.every( ( b ) => b.opacity < 0.4 && b.valueOnTop === 'relative' ),
	'the number sits on top of its bar, and the bar stays a wash',
	bars.map( ( b ) => `${ b.opacity } / ${ b.valueOnTop }` ).join( ' | ' )
);

const widest = await tab.evaluate( () => {
	const cells = [ ...document.querySelectorAll( '#light .lstabp-bar' ) ];
	const largest = cells.find( ( c ) => c.querySelector( '.lstab-cell-value' ).textContent === '120' );

	return parseFloat( getComputedStyle( largest, '::after' ).width ) / largest.getBoundingClientRect().width;
} );

check( widest > 0.97, 'the largest number fills its cell', `${ ( widest * 100 ).toFixed( 1 ) }%` );

console.log( '\nThe button' );

const buttons = await tab.evaluate( () => {
	const lum = ( s ) => {
		const scale = s.startsWith( 'color(' ) ? 1 : 255;
		const [ r, g, b ] = s.match( /[\d.]+/g ).slice( 0, 3 ).map( Number )
			.map( ( v ) => { v /= scale; return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 ); } );
		return 0.2126 * r + 0.7152 * g + 0.0722 * b;
	};
	const contrast = ( a, b ) => { const [ x, y ] = [ lum( a ), lum( b ) ].sort( ( m, n ) => n - m ); return +( ( x + 0.05 ) / ( y + 0.05 ) ).toFixed( 2 ); };

	return [ ...document.querySelectorAll( '#light .lstabp-cta-link' ) ].map( ( link ) => {
		const st = getComputedStyle( link );
		return {
			says: link.textContent,
			href: link.getAttribute( 'href' ),
			rel: link.getAttribute( 'rel' ),
			bg: st.backgroundColor,
			ink: st.color,
			round: st.borderTopLeftRadius,
			underlined: st.textDecorationLine,
			contrast: contrast( st.color, st.backgroundColor ),
		};
	} );
} );

check( buttons.length === 2, 'only the cells holding an address became buttons', `${ buttons.length }` );
check( buttons.every( ( b ) => b.says === 'Book a seat' ), 'each says what the column was told to say' );
check( buttons.every( ( b ) => b.bg === 'rgb(95, 227, 207)' ), 'the chosen background reaches it', buttons.map( ( b ) => b.bg ).join( ' | ' ) );
check( buttons.every( ( b ) => b.ink === 'rgb(6, 16, 15)' ), 'so does the chosen text colour', buttons.map( ( b ) => b.ink ).join( ' | ' ) );
check( buttons.every( ( b ) => b.contrast >= 4.5 ), 'and the words on it can be read', buttons.map( ( b ) => `${ b.contrast }:1` ).join( ' | ' ) );
check( buttons.every( ( b ) => parseFloat( b.round ) > 20 && 'none' === b.underlined ), 'it looks like a button, not a link' );
check( buttons.every( ( b ) => ( b.rel || '' ).includes( 'noopener' ) ), 'it carries rel="noopener"' );

const plain = await tab.evaluate( () =>
	[ ...document.querySelectorAll( '#light tbody tr' ) ].map( ( r ) => r.children[ 2 ].textContent.trim() ) );

check(
	plain[ 2 ] === 'BookingAsk at the desk' || plain[ 2 ].includes( 'ask at the desk' ),
	'a note in the same column is still just a note',
	plain[ 2 ]
);

console.log( '\nThe two shapes a rule can wear' );

const shapesSeen = await tab.evaluate( () => {
	const dot = document.querySelector( '#shapes .lstabp-dot .lstab-cell-value' );
	const pill = document.querySelector( '#shapes .lstabp-pill .lstab-cell-value' );
	const before = getComputedStyle( dot, '::before' );
	const face = getComputedStyle( pill );

	return {
		dotDrawn: before.content !== 'none',
		dotColour: before.backgroundColor,
		dotRound: before.borderTopLeftRadius,
		dotText: dot.textContent,
		pillBorder: face.borderTopWidth,
		pillRound: face.borderTopLeftRadius,
		pillText: pill.textContent,
	};
} );

check( shapesSeen.dotDrawn && shapesSeen.dotColour === 'rgb(225, 29, 72)', 'the dot is drawn, in the rule\'s colour', shapesSeen.dotColour );
check( parseFloat( shapesSeen.dotRound ) > 3, 'and it is round' );
check( shapesSeen.dotText === 'Full', 'the value beside it is untouched', shapesSeen.dotText );
check( shapesSeen.pillBorder === '1px' && parseFloat( shapesSeen.pillRound ) > 20, 'the pill is still a pill' );
check( shapesSeen.pillText === 'Open', 'and its value is untouched too', shapesSeen.pillText );

console.log( '\nOn a dark table' );

const night = await tab.evaluate( () => {
	const bar = document.querySelector( '#night .lstabp-bar' );
	const link = document.querySelector( '#night .lstabp-cta-link' );

	return {
		barColour: getComputedStyle( bar, '::after' ).backgroundColor,
		buttonBg: getComputedStyle( link ).backgroundColor,
		buttonInk: getComputedStyle( link ).color,
	};
} );

check(
	night.barColour.startsWith( 'rgb(95, 227, 207' ) && night.buttonBg === 'rgb(95, 227, 207)' && night.buttonInk === 'rgb(6, 16, 15)',
	'the colours are the ones chosen, whatever the table is wearing',
	`${ night.barColour } / ${ night.buttonBg } / ${ night.buttonInk }`
);

check( errors.length === 0, 'no errors in the console', errors.join( ' | ' ) );

await tab.screenshot( { path: path.join( repo, 'build/column-looks.png' ), fullPage: true } );
await browser.close();

console.log( `\n${ passed } passed, ${ failed } failed` );
process.exit( failed > 0 ? 1 : 0 );
