/**
 * Colours a reader picks, and the rows a rule paints, seen in paint.
 *
 * Three things that went wrong without any computed style saying so: a
 * background colour chosen by hand that stopped at the pinned first column on
 * the skins that named their own, a row hover that never reached a cell a rule
 * had painted, and a rule's colour on the first column hidden under the pinned
 * backdrop. The slider outline has its own colour too, and follows the line
 * colour until it is given one.
 *
 * Usage: node tests/colours-browser.mjs
 */

import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';
import path from 'path';
import zlib from 'zlib';
import { fileURLToPath } from 'url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const repo = path.join( here, '..' );
const CSS = fs.readFileSync( path.join( repo, 'live-sheets-table/assets/css/lstab-table.css' ), 'utf8' );
const skins = [ 'clean', 'striped', 'bordered', 'midnight', 'editorial', 'cards', 'terminal', 'aurora', 'ledger', 'contrast' ];

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

const decodePng = ( buffer ) => {
	let at = 8;
	let head = null;
	const parts = [];

	while ( at < buffer.length ) {
		const length = buffer.readUInt32BE( at );
		const type = buffer.toString( 'ascii', at + 4, at + 8 );
		const body = buffer.subarray( at + 8, at + 8 + length );

		if ( 'IHDR' === type ) {
			head = {
				width: body.readUInt32BE( 0 ),
				height: body.readUInt32BE( 4 ),
				depth: body[ 8 ],
				colour: body[ 9 ],
				interlace: body[ 12 ],
			};
		} else if ( 'IDAT' === type ) {
			parts.push( Buffer.from( body ) );
		} else if ( 'IEND' === type ) {
			break;
		}

		at += 12 + length;
	}

	if ( ! head || 8 !== head.depth || head.interlace ) {
		throw new Error( 'unexpected PNG: ' + JSON.stringify( head ) );
	}

	const channels = { 0: 1, 2: 3, 4: 2, 6: 4 }[ head.colour ];
	const stride = head.width * channels;
	const raw = zlib.inflateSync( Buffer.concat( parts ) );
	const out = Buffer.alloc( head.height * stride );

	for ( let y = 0; y < head.height; y += 1 ) {
		const filter = raw[ y * ( stride + 1 ) ];
		const line = raw.subarray( y * ( stride + 1 ) + 1, ( y + 1 ) * ( stride + 1 ) );
		const here = y * stride;
		const above = here - stride;

		for ( let i = 0; i < stride; i += 1 ) {
			const left = i >= channels ? out[ here + i - channels ] : 0;
			const up = y ? out[ above + i ] : 0;
			const corner = y && i >= channels ? out[ above + i - channels ] : 0;
			let value = line[ i ];

			if ( 1 === filter ) {
				value += left;
			} else if ( 2 === filter ) {
				value += up;
			} else if ( 3 === filter ) {
				value += ( left + up ) >> 1;
			} else if ( 4 === filter ) {
				const p = left + up - corner;
				const dl = Math.abs( p - left );
				const du = Math.abs( p - up );
				const dc = Math.abs( p - corner );
				value += dl <= du && dl <= dc ? left : ( du <= dc ? up : corner );
			}

			out[ here + i ] = value & 0xff;
		}
	}

	return {
		width: head.width,
		height: head.height,
		at: ( x, y ) => {
			const i = y * stride + x * channels;
			return [ out[ i ], out[ i + 1 ], out[ i + 2 ] ];
		},
	};
};

const rowPaint = 'background-color:#fde68a;color:#1c1917;--lstab-row-tint:#fde68a;';
const cellPaint = 'background-color:#bbf7d0;color:#052e16;--lstab-row-tint:#bbf7d0;';
const cell = ( text, paint = '' ) => `<td${ paint ? ` class="lstab-ruled lstab-painted" style="${ paint }"` : '' }><span class="lstab-cell-value">${ text }</span></td>`;

const table = ( skin, style ) => `<div class="lstab-container"><div class="lstab lstab-style-${ skin } lstab-sticky-first lstab-layout-table" id="t-${ skin }" style="${ style }">
<div class="lstab-scroll"><table class="lstab-table"><thead><tr><th>A</th><th>B</th><th>C</th></tr></thead><tbody>
<tr role="row" class="lstab-row">${ cell( 'Lamp' ) }${ cell( '1' ) }${ cell( 'x' ) }</tr>
<tr role="row" class="lstab-row">${ cell( 'Row rule', rowPaint ) }${ cell( '2', rowPaint ) }${ cell( 'y', rowPaint ) }</tr>
<tr role="row" class="lstab-row">${ cell( 'Cell rule', cellPaint ) }${ cell( '3', cellPaint ) }${ cell( 'z' ) }</tr>
</tbody></table></div>
<div class="lstab-scrollbar is-floating"><div class="lstab-scrollbar-track"><div class="lstab-scrollbar-thumb"></div></div></div>
</div></div>`;

const file = path.join( repo, 'build/colours-browser.html' );

fs.mkdirSync( path.dirname( file ), { recursive: true } );
fs.writeFileSync( file, `<!doctype html><meta charset="utf-8"><style>${ CSS } body{background:#fff;margin:0;padding:8px} .lstab-container{width:560px}</style>`
	+ skins.map( ( skin ) => table( skin, '--lstab-bg:#ffd6d6;' ) ).join( '' )
	+ table( 'clean', '--lstab-border:#00ff00;' ).replace( 'id="t-clean"', 'id="line-only"' )
	+ table( 'clean', '--lstab-border:#00ff00;--lstab-slider-line:#ff0000;' ).replace( 'id="t-clean"', 'id="own-line"' ) );

const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
const page = await browser.newPage( { viewport: { width: 640, height: 900 }, deviceScaleFactor: 1 } );

await page.goto( 'file://' + file );

const points = ( id, row ) => page.evaluate( ( [ id, row ] ) => {
	const tr = document.getElementById( id ).querySelectorAll( 'tbody tr' )[ row ];
	tr.scrollIntoView( { block: 'center' } );
	return [ ...tr.children ].map( ( td ) => {
		const box = td.getBoundingClientRect();
		return [ Math.round( box.right - 8 ), Math.round( box.top + 5 ) ];
	} );
}, [ id, row ] );

const colours = async ( spots ) => {
	const shot = decodePng( await page.screenshot() );
	return spots.map( ( [ x, y ] ) => shot.at( x, y ).slice( 0, 3 ).join( ',' ) );
};

const hover = async ( id, row ) => {
	const box = await page.evaluate( ( [ id, row ] ) => {
		const r = document.getElementById( id ).querySelectorAll( 'tbody tr' )[ row ].getBoundingClientRect();
		return [ r.left + r.width / 2, r.top + r.height / 2 ];
	}, [ id, row ] );
	await page.mouse.move( box[ 0 ], box[ 1 ] );
	await page.waitForTimeout( 350 );
};

const away = async () => {
	await page.mouse.move( 2, 2 );
	await page.waitForTimeout( 350 );
};

console.log( '\nA background picked by hand reaches the pinned first column' );

for ( const skin of skins ) {
	await away();
	const spots = await points( `t-${ skin }`, 0 );
	const [ first, , third ] = await colours( spots );
	check( first === third, `${ skin }: the first column wears the chosen background like the rest`, `${ first } against ${ third }` );
}

console.log( '\nA rule\'s colour on the pinned first column' );

for ( const skin of skins ) {
	await away();
	const spots = await points( `t-${ skin }`, 2 );
	const [ first, second ] = await colours( spots );
	check( first === second, `${ skin }: a painted first cell shows its colour, not the backdrop`, `${ first } against ${ second }` );
}

console.log( '\nThe row hover reaches cells a rule has painted' );

for ( const skin of skins ) {
	for ( const row of [ 1, 2 ] ) {
		await away();
		const spots = await points( `t-${ skin }`, row );
		const before = await colours( spots );
		await hover( `t-${ skin }`, row );
		const after = await colours( spots );
		const still = before.map( ( c, i ) => c === after[ i ] ? i + 1 : 0 ).filter( Boolean );

		check( 0 === still.length, `${ skin }: hovering a ${ 1 === row ? 'row' : 'cell' } rule lights every cell of the row`, still.length ? `column ${ still.join( ', ' ) } did not change` : '' );
	}
}

console.log( '\nThe slider outline' );

const outlines = await page.evaluate( () => [ 'line-only', 'own-line' ].map( ( id ) => getComputedStyle( document.querySelector( `#${ id } .lstab-scrollbar` ) ).borderTopColor ) );
check( 'rgb(0, 255, 0)' === outlines[ 0 ], 'follows the line colour until it is given its own', outlines[ 0 ] );
check( 'rgb(255, 0, 0)' === outlines[ 1 ], 'and wears its own once it is', outlines[ 1 ] );

await browser.close();

console.log( `\n  ${ passed } passed, ${ failed } failed` );
process.exit( failed ? 1 : 0 );
