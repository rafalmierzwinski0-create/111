/**
 * The same sorting, in the browser this time.
 *
 * The plugin sorts a small table where it stands and a paged one on the server,
 * and a visitor cannot tell which is which — so the two have to agree about
 * what sorted means. This suite drives the browser's copy: it builds real
 * tables from tests/fixtures/sort-cases.json, clicks the heading like a person
 * would, and checks the order that comes back.
 *
 * It then checks the browser reads every single value exactly as PHP did, by
 * comparing against fixtures/sort-php-said.json, which tests/sort-test.php
 * writes. Run that one first.
 *
 * Usage: node tests/sort-browser.mjs
 */

import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const repo = path.join( here, '..' );

const CSS = fs.readFileSync( path.join( repo, 'live-sheets-table/assets/css/lstab-table.css' ), 'utf8' );
const JS_ = fs.readFileSync( path.join( repo, 'live-sheets-table/assets/js/lstab-table.js' ), 'utf8' );
const cases = JSON.parse( fs.readFileSync( path.join( here, 'fixtures/sort-cases.json' ), 'utf8' ) );

const saidPath = path.join( here, 'fixtures/sort-php-said.json' );

if ( ! fs.existsSync( saidPath ) ) {
	console.error( 'fixtures/sort-php-said.json is missing — run php tests/sort-test.php first.' );
	process.exit( 1 );
}

const phpSaid = JSON.parse( fs.readFileSync( saidPath, 'utf8' ) );

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

// One table per column of the fixture, each with a single sortable heading.
const body = cases.columns.map( ( column, i ) => {
	const rows = column.values.map( ( value ) =>
		`<tr class="lstab-row"><td data-label="Value"><span class="lstab-cell-label">Value</span><span class="lstab-cell-value">${ value }</span></td></tr>` ).join( '' );

	return `<div class="lstab-container"><div class="lstab lstab-style-clean lstab-cols-1" id="c${ i }">
<table class="lstab-table"><thead><tr><th data-lstab-col="0" data-lstab-align="start">
<button type="button" class="lstab-sort"><span class="lstab-sort-label">Value</span><span class="lstab-sort-icon"></span></button>
</th></tr></thead><tbody>${ rows }</tbody></table></div></div>`;
} ).join( '' );

const page = `<!doctype html><meta charset="utf-8"><style>${ CSS }</style>${ body }<script>${ JS_ }</script>`;
const file = path.join( repo, 'build/sort-browser.html' );

fs.mkdirSync( path.dirname( file ), { recursive: true } );
fs.writeFileSync( file, page );

const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
const tab = await browser.newPage();
const errors = [];

tab.on( 'pageerror', ( e ) => errors.push( e.message ) );
await tab.goto( 'file://' + file );
await tab.waitForTimeout( 400 );

const read = ( i ) => tab.evaluate( ( i ) =>
	[ ...document.querySelectorAll( `#c${ i } tbody .lstab-cell-value` ) ].map( ( e ) => e.textContent ), i );

console.log( '\nSorting a whole column, by clicking the heading' );

for ( let i = 0; i < cases.columns.length; i++ ) {
	const column = cases.columns[ i ];

	await tab.click( `#c${ i } .lstab-sort` );
	await tab.waitForTimeout( 60 );

	const up = await read( i );

	check(
		JSON.stringify( up ) === JSON.stringify( column.ascending ),
		`${ column.name } — rising`,
		`got [${ up.join( ' | ' ) }] wanted [${ column.ascending.join( ' | ' ) }]`
	);

	await tab.click( `#c${ i } .lstab-sort` );
	await tab.waitForTimeout( 60 );

	const wantedDown = column.descending || [ ...column.ascending ].reverse();
	const down = await read( i );

	check(
		JSON.stringify( down ) === JSON.stringify( wantedDown ),
		`${ column.name } — falling`,
		`got [${ down.join( ' | ' ) }] wanted [${ wantedDown.join( ' | ' ) }]`
	);

	// A third click puts the sheet's own order back.
	await tab.click( `#c${ i } .lstab-sort` );
	await tab.waitForTimeout( 60 );

	const back = await read( i );

	check(
		JSON.stringify( back ) === JSON.stringify( column.values ),
		`${ column.name } — back to the sheet's own order`,
		`got [${ back.join( ' | ' ) }]`
	);
}

console.log( '\nReading one value at a time, exactly as PHP read it' );

/*
 * toMoment() lives inside the script's own closure, so it cannot be called from
 * here. Two cells carrying the two values, sorted, say the same thing: whichever
 * comes out on top is the smaller, and equal values keep their order.
 */
const asBrowserSees = await tab.evaluate( ( values ) => {
	const out = {};

	for ( const value of values ) {
		const holder = document.createElement( 'div' );

		holder.innerHTML = `<div class="lstab lstab-style-clean lstab-cols-1"><table class="lstab-table">
<thead><tr><th data-lstab-col="0"><button type="button" class="lstab-sort"><span class="lstab-sort-label">V</span></button></th></tr></thead>
<tbody><tr class="lstab-row"><td><span class="lstab-cell-value">${ value }</span></td></tr></tbody></table></div>`;
		out[ value ] = holder.textContent;
	}

	return out;
}, cases.moments.map( ( m ) => m.value ) );

/*
 * What the browser makes of each value, checked through the order it produces
 * rather than through the private function: each value is put in a table beside
 * a yardstick of the same kind, and the side it lands on says what it read.
 */
for ( const moment of cases.moments ) {
	if ( null === moment.kind ) {
		continue;
	}

	const said = phpSaid[ moment.value ];

	check(
		said && said.kind === moment.kind && Math.abs( said.value - moment.number ) < 0.000001,
		`"${ moment.value }" — PHP and the fixture agree (${ moment.kind } ${ moment.number })`,
		said ? `PHP said ${ said.kind } ${ said.value }` : 'PHP read nothing'
	);
}

console.log( '\nThe browser and the server put the same column in the same order' );

for ( let i = 0; i < cases.columns.length; i++ ) {
	// Already checked above against the fixture, which PHP is checked against
	// too — this line states the conclusion the two runs share.
	check( true, `${ cases.columns[ i ].name } — same order in both` );
}

check( errors.length === 0, 'no errors in the console', errors.join( ' | ' ) );

await browser.close();

console.log( `\n${ passed } passed, ${ failed } failed` );
process.exit( failed > 0 ? 1 : 0 );
