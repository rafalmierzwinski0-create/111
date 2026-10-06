/**
 * The Striped style shades every other row a reader can see.
 *
 * It used to shade every other <tr> in the body, and two kinds of <tr> are not
 * rows anybody sees: the drawer under a row, hidden until it is opened, and a
 * row a search has hidden. A table with drawers has one after every row, so
 * every visible row stood at an odd position and the whole table came out one
 * colour. This builds that table from the plugin's own stylesheet and script
 * and reads the colours back.
 *
 * Usage: node tests/stripes-browser.mjs
 */

import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const repo = path.join( here, '..' );

const CSS = fs.readFileSync( path.join( repo, 'live-sheets-table/assets/css/lstab-table.css' ), 'utf8' );
const JS_ = fs.readFileSync( path.join( repo, 'live-sheets-table/assets/js/lstab-table.js' ), 'utf8' );

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

const names = [ 'Lamp', 'Bottle', 'Helmet', 'Bike', 'Lock', 'Rack' ];

const table = ( drawers ) => {
	const rows = names.map( ( name, i ) =>
		`<tr role="row" class="lstab-row" data-lstab-row="${ i }"><td><span class="lstab-cell-value">${ name }</span></td><td><span class="lstab-cell-value">${ i }</span></td></tr>`
		+ ( drawers ? `<tr class="lstab-detail" data-lstab-detail-for="${ i }" hidden><td colspan="2"><div class="lstab-detail-inner">more</div></td></tr>` : '' ) ).join( '' );

	return `<div class="lstab-container"><div class="lstab lstab-style-striped lstab-cols-2" id="${ drawers ? 'drawers' : 'plain' }">
<div class="lstab-controls"><label class="lstab-search"><input type="search" class="lstab-search-input"></label></div>
<div class="lstab-scroll"><table class="lstab-table"><thead><tr><th>Item</th><th>N</th></tr></thead><tbody>${ rows }</tbody></table></div></div></div>`;
};

const file = path.join( repo, 'build/stripes-browser.html' );

fs.mkdirSync( path.dirname( file ), { recursive: true } );
fs.writeFileSync( file, `<!doctype html><meta charset="utf-8"><style>${ CSS }</style>${ table( false ) }${ table( true ) }<script>${ JS_ }</script>` );

const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
const tab = await browser.newPage( { viewport: { width: 900, height: 900 } } );
const errors = [];

tab.on( 'pageerror', ( e ) => errors.push( e.message ) );
await tab.goto( 'file://' + file );
await tab.waitForTimeout( 200 );

const shades = ( id ) => tab.evaluate( ( id ) => [ ...document.querySelectorAll( `#${ id } tr.lstab-row` ) ]
	.filter( ( r ) => ! r.hidden )
	.map( ( r ) => getComputedStyle( r ).backgroundColor ), id );

const alternates = ( list ) => list.length > 2 && list.every( ( c, i ) => ( i % 2 ? c !== list[ 0 ] : c === list[ 0 ] ) );

console.log( '\nEvery other row, as a reader counts them' );

const plain = await shades( 'plain' );
check( alternates( plain ), 'a plain table alternates', plain.join( ' | ' ) );

const drawers = await shades( 'drawers' );
check( alternates( drawers ), 'a table with a drawer under every row alternates too', drawers.join( ' | ' ) );
check( drawers[ 1 ] === plain[ 1 ], 'in the same two shades', `${ drawers[ 1 ] } / ${ plain[ 1 ] }` );

await tab.fill( '#drawers .lstab-search-input', 'l' );
await tab.waitForTimeout( 250 );

const searched = await shades( 'drawers' );
check( searched.length < names.length && alternates( searched ), 'and so do the rows a search leaves', searched.join( ' | ' ) );

await tab.fill( '#drawers .lstab-search-input', '' );
await tab.waitForTimeout( 250 );

// The second row's drawer, opened: it wears its row's shade.
const opened = await tab.evaluate( () => {
	const row = document.querySelectorAll( '#drawers tr.lstab-row' )[ 1 ];
	const drawer = row.nextElementSibling;

	drawer.hidden = false;

	const out = [ getComputedStyle( row ).backgroundColor, getComputedStyle( drawer ).backgroundColor ];

	drawer.hidden = true;

	return out;
} );
check( opened[ 0 ] === opened[ 1 ], 'an opened drawer wears the shade of the row it belongs to', opened.join( ' / ' ) );

check( errors.length === 0, 'no errors in the console', errors.join( ' | ' ) );

await browser.close();

console.log( `\n${ passed } passed, ${ failed } failed` );
process.exit( failed > 0 ? 1 : 0 );
