/**
 * The skins and the two dials, measured on the live site.
 *
 * Opens the pages tests/skins-test.php published — real WordPress, real theme,
 * real sheet data, nine skins each drawn three ways — and measures what a
 * visitor would actually see: row heights, which lines are painted, whether a
 * frame is drawn twice, the gaps between cards, the contrast of the small print
 * and what happens to all of it at the width of a phone.
 *
 * Nothing here is drawn by the test itself, which is the point: skin number one
 * looked finished in a mock-up and was uneven on a page.
 *
 * Run tests/skins-test.php first; this reads the manifest it writes.
 *
 * Usage: node tests/skins-browser.mjs
 */

import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import fs from 'fs';
import zlib from 'zlib';
import path from 'path';
import { fileURLToPath } from 'url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const manifestPath = path.join( here, 'fixtures/skins-php-said.json' );

if ( ! fs.existsSync( manifestPath ) ) {
	console.error( 'Run tests/skins-test.php first — it publishes the pages this opens.' );
	process.exit( 1 );
}

const said = JSON.parse( fs.readFileSync( manifestPath, 'utf8' ) );

/**
 * Just enough PNG to count pixels: 8-bit, not interlaced, which is what a
 * screenshot is. Node ships the inflate; the rest is undoing the per-line
 * filter. No dependency, because a test that needs installing is a test that
 * stops being run.
 */
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

let passed = 0;
let failed = 0;

const check = ( ok, what, detail = '' ) => {
	if ( ok ) {
		passed += 1;
		console.log( '  ok  ', what );
		return ok;
	}
	failed += 1;
	console.log( '  FAIL', what );
	if ( detail ) {
		console.log( '       ', detail );
	}
	return ok;
};

const near = ( a, b, slack = 1 ) => Math.abs( a - b ) <= slack;

/**
 * Everything worth knowing about one rendered table, read off the real page.
 *
 * It runs in the browser, so it can only return plain data — hence the long
 * flat object rather than element handles.
 */
const readTable = ( id ) => {
	const wrap = document.querySelector( `.lstab[data-lstab-id="${ id }"]` );
	if ( ! wrap ) {
		return { missing: true };
	}

	const cs = ( el, pseudo ) => getComputedStyle( el, pseudo || null );
	const box = ( el ) => {
		const r = el.getBoundingClientRect();
		return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width, height: r.height };
	};

	/* The colour actually behind an element: walk up until something is opaque,
	   compositing each translucent layer on the way, the way the screen does. */
	const parse = ( colour ) => {
		const m = colour.match( /rgba?\(([^)]+)\)/ );
		if ( ! m ) {
			return null;
		}
		const parts = m[ 1 ].split( /[\s,\/]+/ ).filter( ( s ) => '' !== s ).map( Number );
		return { r: parts[ 0 ], g: parts[ 1 ], b: parts[ 2 ], a: undefined === parts[ 3 ] ? 1 : parts[ 3 ] };
	};

	const over = ( top, bottom ) => ( {
		r: top.r * top.a + bottom.r * ( 1 - top.a ),
		g: top.g * top.a + bottom.g * ( 1 - top.a ),
		b: top.b * top.a + bottom.b * ( 1 - top.a ),
		a: 1,
	} );

	const behind = ( el ) => {
		/*
		 * The layers between this text and something opaque, outermost last —
		 * and, where an element frosts what is behind it, the multiplication
		 * that does the darkening. backdrop-filter: brightness() does not cover
		 * the backdrop, it multiplies it, which is the whole reason the Glass
		 * skin can be see-through and still legible; a walker that only added up
		 * background colours would read that panel as nearly transparent and
		 * report a contrast the screen never shows.
		 */
		const layers = [];
		let node = el;

		while ( node && node.nodeType === 1 ) {
			const style = cs( node );
			const filter = style.backdropFilter || style.webkitBackdropFilter || 'none';
			const bright = /brightness\(\s*([\d.]+)%?\s*\)/.exec( filter );
			const colour = parse( style.backgroundColor );
			const image = style.backgroundImage;

			if ( image && 'none' !== image ) {
				const stops = [ ...image.matchAll( /rgba?\([^)]+\)/g ) ].map( ( s ) => parse( s[ 0 ] ) ).filter( Boolean );
				const solid = stops.filter( ( s ) => s.a > 0.9 );
				if ( solid.length ) {
					layers.push( { colour: solid[ Math.floor( solid.length / 2 ) ] } );
					break;
				}
			}

			if ( colour && colour.a > 0 ) {
				layers.push( { colour } );
				if ( colour.a >= 0.999 ) {
					break;
				}
			}

			if ( bright ) {
				const k = Number( bright[ 1 ] ) * ( filter.includes( bright[ 1 ] + '%' ) ? 0.01 : 1 );
				layers.push( { bright: k } );
			}

			node = node.parentElement;
		}

		let out = { r: 255, g: 255, b: 255, a: 1 };

		for ( let i = layers.length - 1; i >= 0; i -= 1 ) {
			const step = layers[ i ];

			if ( step.bright !== undefined ) {
				out = { r: out.r * step.bright, g: out.g * step.bright, b: out.b * step.bright, a: 1 };
				continue;
			}

			out = step.colour.a >= 0.999 ? step.colour : over( step.colour, out );
		}

		return out;
	};

	const lum = ( c ) => {
		const f = ( v ) => {
			const s = v / 255;
			return s <= 0.03928 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 );
		};
		return 0.2126 * f( c.r ) + 0.7152 * f( c.g ) + 0.0722 * f( c.b );
	};

	const ratio = ( el ) => {
		const ink = parse( cs( el ).color );
		const paper = behind( el );
		const front = ink.a >= 0.999 ? ink : over( ink, paper );
		const a = lum( front );
		const b = lum( paper );
		return Math.round( ( ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 ) ) * 100 ) / 100;
	};

	const scroll = wrap.querySelector( '.lstab-scroll' );
	const table = wrap.querySelector( '.lstab-table' );
	const thead = table.querySelector( 'thead' );
	const heads = [ ...table.querySelectorAll( 'thead th' ) ];
	const rows = [ ...table.querySelectorAll( 'tbody tr.lstab-row' ) ];
	const cells = rows.length ? [ ...rows[ 0 ].children ] : [];
	const label = table.querySelector( 'tbody .lstab-cell-label' );

	const scrollStyle = cs( scroll );
	const rowStyle = rows.length ? cs( rows[ 0 ] ) : null;
	/*
	 * The middle of the row, not the front of it. The pinned first column takes
	 * `background-color: inherit` from a rule of its own and paints its face
	 * with a backdrop underneath instead, so measuring the first cell answers a
	 * question about pinning rather than about the skin.
	 */
	const cellStyle = cells.length > 1 ? cs( cells[ 1 ] ) : ( cells.length ? cs( cells[ 0 ] ) : null );
	const firstStyle = cells.length ? cs( cells[ 0 ] ) : null;
	const lastStyle = cells.length ? cs( cells[ cells.length - 1 ] ) : null;
	const headStyle = heads.length ? cs( heads[ 0 ] ) : null;

	return {
		rows: rows.length,
		columns: cells.length,
		mode: cs( wrap ).getPropertyValue( '--lstab-table-mode' ).trim(),
		containerWidth: Math.round( wrap.getBoundingClientRect().width ),
		theadDisplay: cs( thead ).display,

		/*
		 * Czy nagłówek jest jednego koloru.
		 *
		 * Przypięta pierwsza kolumna maluje pod sobą własny, nieprzezroczysty
		 * podkład — musi, bo przez przezroczysty prześwitywałyby kolumny
		 * przejeżdżające pod nią. Ten podkład jest PŁASKI. Jeżeli szablon
		 * maluje nagłówek gradientem, płaskie pole obok gradientu daje szew
		 * w poprzek nagłówka, dokładnie tam, gdzie oko zaczyna czytać. Przy
		 * okazji gradient wpisany w arkusz na sztywno przykrywa kolor wybrany
		 * próbnikiem „Tło nagłówka”, więc ten próbnik przestaje działać.
		 *
		 * Pas malowany co drugą kolumną to co innego i jest w porządku: tam
		 * sąsiednie nagłówki mają się różnić. Poznaje się go po tym, że obraz
		 * ma wszystkie stopnie w jednym kolorze (albo go nie ma wcale),
		 * a różnica siedzi w tle komórki.
		 */
		headGradient: ( () => {
			const at = heads.length > 2 ? heads[ 2 ] : heads[ heads.length - 1 ];

			if ( ! at ) {
				return { image: 'none', stops: 0 };
			}

			const image = cs( at ).backgroundImage;
			const stops = [ ...new Set( ( image.match( /(?:rgba?|color)\([^)]+\)/g ) || [] ) ) ];

			return { image: image.slice( 0, 60 ), stops: stops.length };
		} )(),

		/*
		 * The backdrop the pinned first column paints, measured against the
		 * cell that owns it.
		 *
		 * It is absolutely positioned, and a pinned cell is positioned too, so
		 * normally it lands exactly over its own cell. Unpin the column — which
		 * is what the card layout does — and the cell goes back to static: the
		 * backdrop then looks further up for something positioned, finds the
		 * whole table, and paints the row's colour as one slab across it. On a
		 * phone that slab covered the search box, the filters and the first
		 * cards. Nothing here is hypothetical; it shipped.
		 */
		stickyFaceWidth: ( () => {
			const first = rows.length ? rows[ 0 ].querySelector( 'td:first-child' ) : null;

			if ( ! first ) {
				return { face: 0, cell: 0 };
			}

			return {
				face: Math.round( parseFloat( cs( first, '::before' ).width ) || 0 ),
				cell: Math.round( first.getBoundingClientRect().width ),
			};
		} )(),

		rowHeight: rows.length ? Math.round( rows[ 0 ].getBoundingClientRect().height ) : 0,
		/*
		 * The shortest row, which is the one whose values all fit on one line.
		 * The first row does not: its description wraps, and how many lines it
		 * wraps to depends on the column widths, which the row height setting
		 * also moves — so comparing the first row across two settings measures
		 * the wrapping and not the height.
		 */
		minRowHeight: rows.length ? Math.min( ...rows.map( ( r ) => Math.round( r.getBoundingClientRect().height ) ) ) : 0,
		padTop: cellStyle ? parseFloat( cellStyle.paddingTop ) : 0,
		fontSize: cellStyle ? parseFloat( cellStyle.fontSize ) : 0,

		colLine: cellStyle ? parseFloat( cellStyle.borderRightWidth ) : 0,
		lastColLine: lastStyle ? parseFloat( lastStyle.borderRightWidth ) : 0,
		rowLine: rowStyle ? parseFloat( rowStyle.borderBottomWidth ) : 0,
		cellLine: cellStyle ? parseFloat( cellStyle.borderBottomWidth ) : 0,
		rowLineStyle: rowStyle ? rowStyle.borderBottomStyle : '',
		lineToken: cs( wrap ).getPropertyValue( '--lstab-row-line' ).trim(),

		frameBorder: parseFloat( scrollStyle.borderTopWidth ),
		frameShadow: scrollStyle.boxShadow,
		frameMarginTop: parseFloat( scrollStyle.marginTop ),
		frameMarginBottom: parseFloat( scrollStyle.marginBottom ),
		framePadLeft: parseFloat( scrollStyle.paddingLeft ),
		backdrop: scrollStyle.backdropFilter || scrollStyle.webkitBackdropFilter || 'none',
		framePaper: scrollStyle.backgroundColor,

		headLine: headStyle ? parseFloat( headStyle.borderBottomWidth ) : 0,
		headShadow: headStyle ? headStyle.boxShadow : '',
		headPaper: headStyle ? headStyle.backgroundColor : '',
		headPadTop: headStyle ? parseFloat( headStyle.paddingTop ) : 0,

		rowPaper: rowStyle ? rowStyle.backgroundColor : '',
		/*
		 * Ile widać między jednym wierszem a następnym. Pasek, którego nikt nie
		 * zobaczy, nie jest paskiem: pierwszy kolor różnił się od papieru o
		 * jeden procent — mierzył się jako nałożony i wyglądał jak Czysty.
		 */
		stripe: ( () => {
			if ( rows.length < 2 ) {
				return 1;
			}
			const parse = ( c ) => ( c.match( /\d+/g ) || [ 255, 255, 255 ] ).map( Number );
			const lum = ( c ) => { const f = ( v ) => { const s = v / 255; return s <= 0.03928 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 ); };
				return 0.2126 * f( c[ 0 ] ) + 0.7152 * f( c[ 1 ] ) + 0.0722 * f( c[ 2 ] ); };
			const a = lum( parse( cs( rows[ 0 ] ).backgroundColor ) );
			const b = lum( parse( cs( rows[ 1 ] ).backgroundColor ) );
			return Math.round( ( ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 ) ) * 1000 ) / 1000;
		} )(),
		cellPaper: cellStyle ? cellStyle.backgroundColor : '',
		cellImage: cellStyle ? cellStyle.backgroundImage : '',
		firstCellPaper: firstStyle ? firstStyle.backgroundColor : '',
		secondCellPaper: cellStyle ? cellStyle.backgroundColor : '',

		/*
		 * Ile widać między jedną kolumną a następną — to samo, co „stripe”
		 * mierzy między wierszami. Ledger cieniuje kolumny zamiast wierszy, a
		 * pas, którego nikt nie zobaczy, nie jest pasem.
		 */
		band: ( () => {
			if ( ! cells.length || cells.length < 2 ) {
				return 1;
			}

			/*
			 * Skomponowane, nie odczytane wprost. Na większości skór tło siedzi
			 * na wierszu, a komórka jest przezroczysta — porównanie dwóch
			 * przezroczystości dałoby czerń kontra czerń i liczbę bez sensu.
			 * Stąd wspinaczka w górę, aż trafi się na coś nieprzezroczystego.
			 */
			/*
			 * Dwa zapisy, nie jeden. color-mix() wraca jako
			 * "color(srgb 0.95 0.92 0.85)" — składowe od zera do jedynki, a nie
			 * od zera do 255. Czytane jak rgb() dawały luminancję prawie zera i
			 * pas „widoczny” dwudziestokrotnie, czyli liczbę bez sensu, która
			 * przeszłaby każdy próg, jaki bym postawił.
			 */
			const parse = ( c ) => {
				const n = ( c.match( /[\d.]+/g ) || [ 255, 255, 255 ] ).map( Number );
				const skala = /^color\(/.test( c ) ? 255 : 1;

				return { r: n[ 0 ] * skala, g: n[ 1 ] * skala, b: n[ 2 ] * skala, a: undefined === n[ 3 ] ? 1 : n[ 3 ] };
			};
			const paper = ( el ) => {
				let node = el;
				let out = { r: 255, g: 255, b: 255, a: 1 };
				const stack = [];

				while ( node && 1 === node.nodeType ) {
					const c = parse( cs( node ).backgroundColor );
					if ( c.a > 0 ) {
						stack.push( c );
						if ( c.a >= 0.999 ) {
							break;
						}
					}
					node = node.parentElement;
				}

				for ( let i = stack.length - 1; i >= 0; i-- ) {
					const t = stack[ i ];
					out = {
						r: t.r * t.a + out.r * ( 1 - t.a ),
						g: t.g * t.a + out.g * ( 1 - t.a ),
						b: t.b * t.a + out.b * ( 1 - t.a ),
						a: 1,
					};
				}

				return out;
			};
			const lum = ( c ) => { const f = ( v ) => { const s = v / 255; return s <= 0.03928 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 ); };
				return 0.2126 * f( c.r ) + 0.7152 * f( c.g ) + 0.0722 * f( c.b ); };
			const a = lum( paper( cells[ 0 ] ) );
			const b = lum( paper( cells[ 1 ] ) );

			return Math.round( ( ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 ) ) * 1000 ) / 1000;
		} )(),
		headRuleStyle: headStyle ? headStyle.borderBottomStyle : '',
		numerals: cs( table ).fontVariantNumeric,
		radiusFirst: firstStyle ? firstStyle.borderTopLeftRadius : '',

		stickyBackdrop: cells.length ? cs( cells[ 0 ], '::before' ).backgroundColor : '',
		stickyBackdropBottom: cells.length ? cs( cells[ 0 ], '::before' ).bottom : '',
		stickyHeadBackdrop: heads.length ? cs( heads[ 0 ], '::before' ).backgroundColor : '',

		fontFamily: cs( table ).fontFamily,

		/* Card geometry: the gap above the first card, between two cards, and
		   below the last one. Even means all three are the same. */
		gapHeadToFirst: rows.length ? Math.round( rows[ 0 ].getBoundingClientRect().top - thead.getBoundingClientRect().bottom ) : 0,
		gapBetween: rows.length > 1 ? Math.round( rows[ 1 ].getBoundingClientRect().top - rows[ 0 ].getBoundingClientRect().bottom ) : 0,
		gapAfterLast: rows.length ? Math.round( scroll.getBoundingClientRect().bottom - rows[ rows.length - 1 ].getBoundingClientRect().bottom ) : 0,

		inkRatio: rows.length ? ratio( rows[ 0 ].querySelector( '.lstab-cell-value' ) || cells[ 0 ] ) : 0,
		headRatio: heads.length ? ratio( heads[ 0 ].querySelector( '.lstab-sort-label' ) || heads[ 0 ] ) : 0,
		labelRatio: label ? ratio( label ) : 0,
		labelShown: label ? cs( label ).display : 'none',

		tableBox: box( table ),
		scrollBox: box( scroll ),
	};
};

const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );
const page = await browser.newPage( { viewport: { width: 1440, height: 1000 } } );
const problems = [];

page.on( 'pageerror', ( e ) => problems.push( e.message ) );
page.on( 'console', ( m ) => {
	if ( 'error' === m.type() ) {
		problems.push( m.text() );
	}
} );

/** Everything measured, keyed skin → variant. */
const desktop = {};
const phone = {};

for ( const entry of said.pages ) {
	const variant = `${ entry.density }/${ entry.lines }`;

	await page.setViewportSize( { width: 1440, height: 1000 } );
	await page.goto( entry.url, { waitUntil: 'networkidle' } );

	for ( const table of entry.tables ) {
		desktop[ table.skin ] = desktop[ table.skin ] || {};
		desktop[ table.skin ][ variant ] = await page.evaluate( readTable, table.id );
	}

	await page.setViewportSize( { width: 390, height: 900 } );
	await page.waitForTimeout( 150 );

	for ( const table of entry.tables ) {
		phone[ table.skin ] = phone[ table.skin ] || {};
		phone[ table.skin ][ variant ] = await page.evaluate( readTable, table.id );
	}
}

const skins = said.skins;
const asIs = 'normal/normal';
const tight = 'compact/grid';
const airy = 'roomy/none';

// ------------------------------------------------------- every skin renders

console.log( '\nEvery skin, on a real page' );

for ( const skin of skins ) {
	const d = desktop[ skin ][ asIs ];
	check( ! d.missing && d.rows > 0, `${ skin }: the table is there, with rows`, JSON.stringify( d ).slice( 0, 160 ) );
	check( d.columns === 5, `${ skin }: all five columns`, `${ d.columns }` );
	// Without this the rest would be measuring the card layout and calling it a
	// table, which is how a suite passes while the page is wrong.
	check( d.containerWidth > 700 && 'none' !== d.theadDisplay, `${ skin }: it is a table at this width, not cards`, `${ d.containerWidth }px, thead ${ d.theadDisplay }` );
	check( '1' === d.mode, `${ skin }: the table-mode factor is 1 here`, d.mode );
	check( d.inkRatio >= 4.5, `${ skin }: the values are readable (${ d.inkRatio }:1)`, `${ d.inkRatio }` );
	check( d.headRatio >= 4.5, `${ skin }: the column names are readable (${ d.headRatio }:1)`, `${ d.headRatio }` );
	/*
	 * A heading is one colour. Anything with more than one colour in it cannot
	 * be matched by the pinned column's flat backdrop, and paints over the
	 * Header background well besides.
	 */
	check(
		d.headGradient.stops <= 1,
		`${ skin }: the heading is one colour, so the pinned column can match it`,
		JSON.stringify( d.headGradient )
	);
	// And in the table layout it covers its own cell and not a pixel more.
	check(
		Math.abs( d.stickyFaceWidth.face - d.stickyFaceWidth.cell ) <= 1,
		`${ skin }: the pinned column's backdrop is exactly its own cell wide`,
		JSON.stringify( d.stickyFaceWidth )
	);
	if ( 'cards' === skin ) {
		// The card's own right-hand edge, which is the one skin where a line on
		// the last cell is the point rather than a leak.
		check( near( d.lastColLine, 1 ), `${ skin }: the card's outside edge is drawn`, `${ d.lastColLine }` );
	} else {
		check( 0 === d.lastColLine, `${ skin }: no stray line down the outside edge`, `${ d.lastColLine }` );
	}
	const sticky = d.stickyBackdrop.match( /rgba?\(([^)]+)\)/ );
	const alpha = sticky ? Number( sticky[ 1 ].split( /[\s,\/]+/ ).filter( ( s ) => '' !== s )[ 3 ] ?? 1 ) : 0;

	if ( 'glass' === skin ) {
		// The one skin that waits: a solid band down a see-through panel is
		// only worth having once something is sliding underneath it. Checked
		// while it is sliding, further down.
		check( 0 === alpha, `${ skin }: the panel is not interrupted while nothing is scrolling`, d.stickyBackdrop );
	} else {
		check( alpha > 0.5, `${ skin }: the pinned column has something solid behind it`, d.stickyBackdrop );
	}
	// And that backdrop stops short of the line between rows, which is painted
	// underneath it; see the pixel count further down.
	check(
		parseFloat( d.stickyBackdropBottom ) === parseFloat( d.lineToken || '0' ),
		`${ skin }: and that backdrop stops short of the line`,
		`${ d.stickyBackdropBottom } against a ${ d.lineToken } line`
	);
}

// ------------------------------------------------------------- the two dials

console.log( '\nRow height — the dial moves every skin' );

for ( const skin of skins ) {
	const compact = desktop[ skin ][ tight ];
	const normal = desktop[ skin ][ asIs ];
	const roomy = desktop[ skin ][ airy ];

	check(
		compact.padTop < normal.padTop && normal.padTop < roomy.padTop,
		`${ skin }: compact < normal < roomy`,
		`${ compact.padTop } / ${ normal.padTop } / ${ roomy.padTop }`
	);
	check(
		compact.minRowHeight < normal.minRowHeight && normal.minRowHeight < roomy.minRowHeight,
		`${ skin }: and a one-line row really is shorter and taller`,
		`${ compact.minRowHeight }px / ${ normal.minRowHeight }px / ${ roomy.minRowHeight }px`
	);
}

console.log( '\nLines — the dial overrules every skin' );

for ( const skin of skins ) {
	const grid = desktop[ skin ][ tight ];
	const bare = desktop[ skin ][ airy ];

	check( near( grid.colLine, 1 ), `${ skin }: a full grid draws the line between columns`, `${ grid.colLine }` );
	check( grid.rowLine + grid.cellLine > 0, `${ skin }: and keeps the line between rows`, `${ grid.rowLine } / ${ grid.cellLine }` );
	check( 0 === bare.colLine, `${ skin }: no lines takes the column line away`, `${ bare.colLine }` );
	check(
		0 === bare.rowLine && 0 === bare.cellLine,
		`${ skin }: no lines takes the row line away too`,
		`row ${ bare.rowLine }, cell ${ bare.cellLine }`
	);
	check( '0px' === bare.lineToken, `${ skin }: and says so in the token`, bare.lineToken );
}

// Bordered is the case that proves the dial and the preset speak the same
// language: its grid is the preset's, and the dial can still take it off.
check(
	near( desktop.bordered[ asIs ].colLine, 1 ),
	'Bordered comes with its grid, without being asked',
	`${ desktop.bordered[ asIs ].colLine }`
);
check(
	0 === desktop.bordered[ airy ].colLine,
	'and the same table with "no lines" has none',
	`${ desktop.bordered[ airy ].colLine }`
);
check(
	0 === desktop.clean[ asIs ].colLine && near( desktop.clean[ tight ].colLine, 1 ),
	'Clean has no vertical lines until it is asked for a grid',
	`${ desktop.clean[ asIs ].colLine } → ${ desktop.clean[ tight ].colLine }`
);

check(
	desktop.striped[ asIs ].stripe >= 1.1,
	`Striped really is striped (${ desktop.striped[ asIs ].stripe } between one row and the next)`,
	`${ desktop.striped[ asIs ].stripe }`
);
check(
	1 === desktop.clean[ asIs ].stripe,
	'and Clean is not',
	`${ desktop.clean[ asIs ].stripe }`
);

// ---------------------------------------------------------------- the cards

console.log( '\nCards — the four things that were wrong in the mock-up' );

const cards = desktop.cards[ asIs ];

check( 0 === cards.frameBorder, 'no frame drawn round the cards', `${ cards.frameBorder }px` );
check( 'none' === cards.frameShadow, 'and no shadow round them either', cards.frameShadow );
check( 'rgba(0, 0, 0, 0)' === cards.framePaper, 'the page shows between the cards', cards.framePaper );
check( 0 === cards.headLine, 'no line under the column names', `${ cards.headLine }px` );
check( 'none' === cards.headShadow, 'and no inset shadow pretending to be one', cards.headShadow );
check(
	parseFloat( cards.radiusFirst ) > 8,
	'the card corners are actually rounded',
	cards.radiusFirst
);
check(
	near( cards.gapHeadToFirst, cards.gapBetween ) && near( cards.gapBetween, cards.gapAfterLast ),
	'the gaps are even: above the first card, between cards, below the last',
	`${ cards.gapHeadToFirst } / ${ cards.gapBetween } / ${ cards.gapAfterLast }`
);
check(
	near( cards.frameMarginTop, -cards.gapBetween ) && near( cards.frameMarginBottom, -cards.gapBetween ),
	'and the two outer gaps are taken back, so the list is not sitting low',
	`${ cards.frameMarginTop } / ${ cards.frameMarginBottom }`
);
check( cards.framePadLeft > 0, 'the shadow has room inside the scrolling box', `${ cards.framePadLeft }` );
check(
	'rgba(0, 0, 0, 0)' === cards.rowPaper && 'rgba(0, 0, 0, 0)' !== cards.cellPaper,
	'the cards are painted by their cells, not by the row',
	`row ${ cards.rowPaper }, cell ${ cards.cellPaper }`
);
check(
	'rgba(0, 0, 0, 0)' === cards.firstCellPaper,
	'the pinned first cell leaves its face to the backdrop, as every skin does',
	cards.firstCellPaper
);
check(
	'none' !== desktop.cards[ asIs ].cellImage,
	'and the cells carry the layer a hover or a rule tints',
	cards.cellImage
);

// Hover, on the real page, because a card that loses its hover is a card
// nobody can tell is a row.
await page.setViewportSize( { width: 1440, height: 1000 } );
await page.goto( said.pages[ 0 ].url, { waitUntil: 'networkidle' } );

const cardsId = said.pages[ 0 ].tables.find( ( t ) => 'cards' === t.skin ).id;
const cardCell = `.lstab[data-lstab-id="${ cardsId }"] tbody tr.lstab-row:first-child td:nth-child(2)`;

await page.hover( cardCell );
await page.waitForTimeout( 120 );

const hovered = await page.evaluate( ( selector ) => {
	const cell = document.querySelector( selector );
	const row = cell.closest( 'tr' );
	return {
		tint: getComputedStyle( row ).getPropertyValue( '--lstab-row-tint' ).trim(),
		image: getComputedStyle( cell ).backgroundImage,
	};
}, cardCell );

check( '' !== hovered.tint && 'transparent' !== hovered.tint, 'hovering a card still tints it', JSON.stringify( hovered ) );
check( /rgb/.test( hovered.image ), 'and the tint is painted on the cell, corners and all', hovered.image );

// ------------------------------------------------------- the other three

console.log( '\nTerminal, Glass and Contrast' );

const terminal = desktop.terminal[ asIs ];
check( /mono/i.test( terminal.fontFamily ), 'Terminal is set in one width of letter', terminal.fontFamily );
check( terminal.rowLine > 0, 'and it keeps a line between its rows', `${ terminal.rowLine }` );
check( terminal.inkRatio >= 7, `Terminal is high contrast (${ terminal.inkRatio }:1)`, `${ terminal.inkRatio }` );

const glass = desktop.glass[ asIs ];
check( /blur/.test( glass.backdrop ), 'Glass blurs what is behind it', glass.backdrop );
check( 'rgba(0, 0, 0, 0)' === glass.rowPaper, 'and its rows add no second tint', glass.rowPaper );
check( glass.inkRatio >= 4.5, `Glass stays readable over its gradient (${ glass.inkRatio }:1)`, `${ glass.inkRatio }` );

const glassId = said.pages[ 0 ].tables.find( ( t ) => 'glass' === t.skin ).id;

const dragged = await page.evaluate( async ( id ) => {
	const wrap = document.querySelector( `.lstab[data-lstab-id="${ id }"]` );
	const scroll = wrap.querySelector( '.lstab-scroll' );

	scroll.scrollLeft = 140;
	await new Promise( ( done ) => setTimeout( done, 200 ) );

	const first = wrap.querySelector( 'tbody tr.lstab-row' ).children[ 0 ];

	return {
		scrolled: wrap.classList.contains( 'lstab-is-scrolled' ),
		backdrop: getComputedStyle( first, '::before' ).backgroundColor,
	};
}, glassId );

const draggedAlpha = Number( ( dragged.backdrop.match( /rgba?\(([^)]+)\)/ ) || [ , '0,0,0,0' ] )[ 1 ]
	.split( /[\s,\/]+/ ).filter( ( s ) => '' !== s )[ 3 ] ?? 1 );

check( dragged.scrolled, 'Glass knows when it has been dragged sideways', JSON.stringify( dragged ) );
check( draggedAlpha > 0.5, 'and then its pinned column has something solid behind it', dragged.backdrop );

const contrast = desktop.contrast[ asIs ];
const headAlpha = ( contrast.headPaper.match( /rgba?\(([^)]+)\)/ ) || [ , '0,0,0,0' ] )[ 1 ]
	.split( /[\s,\/]+/ ).filter( ( s ) => '' !== s );
check( 1 === Number( headAlpha[ 3 ] ?? 1 ), 'Contrast has a solid heading bar', contrast.headPaper );
check( contrast.headRatio >= 4.5, `and the names on it are readable (${ contrast.headRatio }:1)`, `${ contrast.headRatio }` );
check(
	contrast.firstCellPaper !== contrast.secondCellPaper,
	'its first column carries more weight than the rest',
	`${ contrast.firstCellPaper } vs ${ contrast.secondCellPaper }`
);
check(
	contrast.headPadTop > desktop.clean[ asIs ].headPadTop,
	'and its heading bar is taller than an ordinary one',
	`${ contrast.headPadTop } vs ${ desktop.clean[ asIs ].headPadTop }`
);

const ledger = desktop.ledger[ asIs ];

/*
 * Ledger is the one skin in the set that shades columns instead of rows, so
 * its checks are the mirror image of the Striped ones: the band has to be
 * visible between one column and the next, and the rows have to stay plain —
 * two devices shading one table would cross-hatch it.
 */
/*
 * Widełki, nie próg. Sam pomiar był raz zepsuty — color-mix() wraca w innej
 * skali niż rgb() — i „widoczny 20 razy” przeszedł każdy próg od dołu, jaki
 * bym postawił. Pas ma być widoczny i ma być pasem, a nie drugim papierem.
 */
check(
	ledger.band >= 1.1 && ledger.band <= 1.5,
	`Ledger really is banded down its columns (${ ledger.band } between one column and the next)`,
	`${ ledger.band }`
);
check(
	1 === ledger.stripe,
	'and its rows are left plain, so the two never cross-hatch',
	`${ ledger.stripe }`
);
check( 'double' === ledger.headRuleStyle, 'the rule under its headings is a double one', ledger.headRuleStyle );
check(
	/tabular-nums/.test( ledger.numerals ),
	'and its figures are of one width, so a column of them lines up',
	ledger.numerals
);
check( ledger.inkRatio >= 4.5, `Ledger is readable on its warm paper (${ ledger.inkRatio }:1)`, `${ ledger.inkRatio }` );
check(
	ledger.labelRatio >= 4.5 || 'none' === ledger.labelShown,
	`including the quietest print on it (${ ledger.labelRatio }:1)`,
	`${ ledger.labelRatio }`
);

// ----------------------------------------------------------- on a phone

console.log( '\nOn a phone — every skin folds, and nothing leaks through' );

for ( const skin of skins ) {
	const p = phone[ skin ][ asIs ];
	const gridOnPhone = phone[ skin ][ tight ];

	check( 'none' === p.theadDisplay, `${ skin }: the headings step aside for cards`, p.theadDisplay );
	check( '0' === p.mode, `${ skin }: the table-mode factor is 0`, p.mode );
	check( 'block' === p.labelShown, `${ skin }: every value is introduced by its column`, p.labelShown );
	check( p.labelRatio >= 4.5, `${ skin }: and that label is readable (${ p.labelRatio }:1)`, `${ p.labelRatio }` );
	check( p.inkRatio >= 4.5, `${ skin }: the values are readable on a card too (${ p.inkRatio }:1)`, `${ p.inkRatio }` );
	// The whole reason the factor exists: a table asked for a full grid must not
	// draw a vertical rule down a card.
	check( 0 === gridOnPhone.colLine, `${ skin }: a full grid draws no line down a card`, `${ gridOnPhone.colLine }` );
	/*
	 * And no skin may shade one value differently from the next inside a card.
	 * Ledger is the one that could: its band is drawn down alternate columns,
	 * and a column means nothing once the table is one card per row — it would
	 * come out tinting every other *value*, which says nothing at all.
	 */
	check( p.band <= 1.02, `${ skin }: and no band shades one value and not the next`, `${ p.band }` );
	/*
	 * Nothing is pinned in a card, so the pinned column's backdrop has no work
	 * to do — and if it is drawn anyway it is drawn against the whole table.
	 */
	check(
		0 === p.stickyFaceWidth.face,
		`${ skin }: and the pinned column paints no backdrop where nothing is pinned`,
		JSON.stringify( p.stickyFaceWidth )
	);
}

const cardsPhone = phone.cards[ asIs ];
check( 0 === parseFloat( cardsPhone.radiusFirst ), 'Cards: the cell corners let the row do the rounding on a phone', cardsPhone.radiusFirst );
check( 0 === cardsPhone.frameMarginTop && 0 === cardsPhone.frameMarginBottom, 'Cards: and nothing is pulled up when there is no border-spacing to pull', `${ cardsPhone.frameMarginTop } / ${ cardsPhone.frameMarginBottom }` );
check( 0 === cardsPhone.framePadLeft, 'Cards: no shadow room reserved where there is no shadow', `${ cardsPhone.framePadLeft }` );

// -------------------------------------- the line, where the pinning happens

/*
 * Counted in pixels off a screenshot, because this is the one thing computed
 * styles cannot answer: whether the line between rows is actually painted
 * where the pinned first column is.
 *
 * That column paints an opaque backdrop of its own, above anything the cell
 * underneath it drew. A line drawn as the cell's own background — which is how
 * the Terminal skin drew its dashes to begin with — disappears under it, and
 * the table ends up separated everywhere except the column a reader looks at
 * first. Nothing in the computed styles says so: the background is there, it
 * is simply never seen. Every skin is checked, because the next skin somebody
 * writes can make the same mistake.
 *
 * At 1×, where the arithmetic is exact. Chromium puts a pinned column on a
 * layer of its own and rounds that layer to whole device pixels, so on a 2×
 * screen a row whose height lands on a fraction can have its line shifted a
 * pixel or swallowed by the rounding — it happens to plain Clean as readily as
 * to anything added here, it is not something a stylesheet can reach, and
 * measuring it would only record which fractions this build of Chromium
 * rounds which way.
 */
console.log( '\nThe line between rows, under the pinned column' );

const flat = await browser.newContext( { viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 } );
const sharp = await flat.newPage();

await sharp.goto( said.pages[ 0 ].url, { waitUntil: 'networkidle' } );

for ( const table of said.pages[ 0 ].tables ) {
	const where = await sharp.evaluate( ( id ) => {
		const wrap = document.querySelector( `.lstab[data-lstab-id="${ id }"]` );
		const scroll = wrap.querySelector( '.lstab-scroll' );
		const row = wrap.querySelector( 'tbody tr.lstab-row' );
		const first = row.children[ 0 ];
		const box = scroll.getBoundingClientRect();
		const line = row.getBoundingClientRect();
		const pinned = first.getBoundingClientRect();

		return {
			y: line.bottom - box.top,
			pinned: pinned.right - box.left,
			width: box.width,
		};
	}, table.id );

	const shot = decodePng( await ( await sharp.$( `.lstab[data-lstab-id="${ table.id }"] .lstab-scroll` ) ).screenshot() );
	const paper = shot.at( Math.round( where.pinned / 2 ), Math.round( where.y ) - 6 );
	const different = ( x, y ) => {
		const p = shot.at( x, y );
		return Math.abs( p[ 0 ] - paper[ 0 ] ) + Math.abs( p[ 1 ] - paper[ 1 ] ) + Math.abs( p[ 2 ] - paper[ 2 ] ) > 8;
	};

	const edge = Math.round( where.pinned );
	const right_edge = Math.min( shot.width, Math.round( where.width ) ) - 12;
	let best = { left: 0, right: 0 };

	// A border sits on the grid line between two rows, so it can round either
	// way; the band is two pixels wide on each side of where it should be.
	for ( let y = Math.round( where.y ) - 2; y <= Math.round( where.y ) + 2; y += 1 ) {
		if ( y < 0 || y >= shot.height ) {
			continue;
		}

		let left = 0;
		let right = 0;

		// Clear of the very edges, where a frame or a rounded corner lives.
		for ( let x = 12; x < right_edge; x += 1 ) {
			if ( ! different( x, y ) ) {
				continue;
			}
			if ( x < edge - 6 ) {
				left += 1;
			} else if ( x > edge + 6 ) {
				right += 1;
			}
		}

		best = { left: Math.max( best.left, left ), right: Math.max( best.right, right ) };
	}

	const leftShare = best.left / Math.max( 1, edge - 18 );
	const rightShare = best.right / Math.max( 1, right_edge - edge - 6 );

	check(
		rightShare > 0.2,
		`${ table.skin }: there is a line between the rows to measure`,
		`${ Math.round( rightShare * 100 ) }% of the width`
	);
	check(
		leftShare > rightShare * 0.5,
		`${ table.skin }: and it is painted under the pinned column too`,
		`${ Math.round( leftShare * 100 ) }% there against ${ Math.round( rightShare * 100 ) }% elsewhere`
	);
}

await flat.close();

// ------------------------------------ a skin picked, then changed by hand

console.log( '\nA skin picked, then changed by hand' );

await page.setViewportSize( { width: 1440, height: 1000 } );
await page.goto( said.tuned.url, { waitUntil: 'networkidle' } );

const tuned = await page.evaluate( readTable, said.tuned.id );
const tunedVars = await page.evaluate( ( id ) => {
	const wrap = document.querySelector( `.lstab[data-lstab-id="${ id }"]` );
	const cs = getComputedStyle( wrap );
	return {
		bg: cs.getPropertyValue( '--lstab-bg' ).trim(),
		border: cs.getPropertyValue( '--lstab-border' ).trim(),
		padY: cs.getPropertyValue( '--lstab-pad-y' ).trim(),
		colLine: cs.getPropertyValue( '--lstab-col-line' ).trim(),
		radius: cs.getPropertyValue( '--lstab-radius' ).trim(),
		classes: wrap.className,
	};
}, said.tuned.id );

check( /lstab-style-cards/.test( tunedVars.classes ), 'it is still the Cards skin', tunedVars.classes );
check( '#fff7ed' === tunedVars.bg.toLowerCase(), 'with the background colour the author chose', tunedVars.bg );
check( '#e7c9a9' === tunedVars.border.toLowerCase(), 'the line colour the author chose', tunedVars.border );
check( '1.05em' === tunedVars.padY, 'the roomier row the author chose', tunedVars.padY );
check( '1px' === tunedVars.colLine, 'the grid the author chose', tunedVars.colLine );
check( '0' === tunedVars.radius, 'and square corners, over the skin\'s own 14px', tunedVars.radius );
check( 0 === parseFloat( tuned.radiusFirst ), 'which the cards really are drawn with', tuned.radiusFirst );
check( near( tuned.colLine, 1 ), 'the lines really are drawn down the cards', `${ tuned.colLine }` );
check(
	tuned.padTop > desktop.cards[ asIs ].padTop,
	'and the rows really are roomier than the skin makes them',
	`${ tuned.padTop } vs ${ desktop.cards[ asIs ].padTop }`
);
check( tuned.inkRatio >= 4.5, `all of it still readable (${ tuned.inkRatio }:1)`, `${ tuned.inkRatio }` );

const naglowek = await page.evaluate( ( id ) => {
	const th = document.querySelector( `.lstab[data-lstab-id="${ id }"] thead th` );
	return { tlo: getComputedStyle( th ).backgroundColor, ink: getComputedStyle( th ).color };
}, said.tuned.id );

check(
	'rgb(43, 26, 74)' === naglowek.tlo && 'rgb(255, 233, 199)' === naglowek.ink,
	'and the heading really wears the colours that were picked for it',
	`${ naglowek.tlo } / ${ naglowek.ink }`
);

// -------------------------------------------- glass, in a colour of its own

console.log( '\nGlass, tinted, over the worst backdrop there is' );

/*
 * "Glass colour" takes an ordinary opaque colour, as every colour well on that
 * screen does, and the pane lays it on at a seventh so the page still shows
 * through. A tint can only lighten, so white over a white page is the case
 * that decides whether the setting is safe to offer at all — and it is the
 * case somebody reaches by picking the first colour in the picker.
 */
await page.goto( said.glass.url, { waitUntil: 'networkidle' } );
await page.waitForTimeout( 300 );

const tinted = await page.evaluate( ( id ) => {
	const root = document.querySelector( `.lstab[data-lstab-id="${ id }"]` );
	const pane = root.querySelector( '.lstab-scroll' );
	const seen = getComputedStyle( pane );

	return {
		tint: getComputedStyle( root ).getPropertyValue( '--lstab-glass-tint' ).trim(),
		paneBg: seen.backgroundColor,
		filter: seen.backdropFilter || seen.webkitBackdropFilter,
	};
}, said.glass.id );

check( '#ffffff' === tinted.tint.toLowerCase(), 'the colour chosen for the glass reaches the table', tinted.tint );
/*
 * The alpha, whichever way the browser writes it: color-mix() comes back as
 * "color(srgb 1 1 1 / 0.14)" rather than as rgba(), and a check written for
 * rgba() alone would fail on a pane that is perfectly correct.
 */
const alpha = ( colour ) => {
	const slashed = /\/\s*([\d.]+%?)\s*\)/.exec( colour );

	if ( slashed ) {
		return slashed[ 1 ].endsWith( '%' ) ? parseFloat( slashed[ 1 ] ) / 100 : parseFloat( slashed[ 1 ] );
	}

	const parts = colour.replace( /^[a-z]+\(|\)$/g, '' ).split( ',' );

	return 4 === parts.length ? parseFloat( parts[ 3 ] ) : 1;
};

check(
	alpha( tinted.paneBg ) > 0 && alpha( tinted.paneBg ) < 0.5,
	'and tints the pane without closing it — it is still see-through',
	`${ tinted.paneBg } → alpha ${ alpha( tinted.paneBg ) }`
);
check(
	/blur/.test( tinted.filter ) && /brightness/.test( tinted.filter ),
	'the pane is still frosted, and still darkens what is behind it',
	tinted.filter
);

const tintedInk = await page.evaluate( readTable, said.glass.id );

check(
	tintedInk.inkRatio >= 4.5,
	`and the values on it are still readable (${ tintedInk.inkRatio }:1)`,
	`${ tintedInk.inkRatio }`
);
check(
	tintedInk.labelRatio >= 4.5 || 'none' === tintedInk.labelShown,
	`including the quietest print on it (${ tintedInk.labelRatio }:1)`,
	`${ tintedInk.labelRatio } / ${ tintedInk.labelShown }`
);

/*
 * The badge, in the colours nobody should have to think twice about picking.
 *
 * A pill's word is the rule's colour mixed with the table's own ink, and the
 * share of that mix is the whole safety margin: the table's ink is readable on
 * the table's paper by construction, so the less of the chosen colour the word
 * keeps, the safer it is. At 55 per cent a bright mint measured 3.44 to 1 on
 * warm paper — a reading nobody had, because the badge's word had never been
 * measured on a real page. This is that page: white, mint, black and amber, on
 * every skin there is.
 */
await page.goto( said.pills.url, { waitUntil: 'networkidle' } );
await page.waitForTimeout( 300 );

const readPills = ( id ) => {
	const wrap = document.querySelector( `.lstab[data-lstab-id="${ id }"]` );

	if ( ! wrap ) {
		return { missing: true };
	}

	/*
	 * color-mix() comes back as "color(srgb 0.28 0.55 0.5)" — components from
	 * nought to one rather than nought to 255 — and the pill's ink is nothing
	 * but a color-mix(). A parser written for rgb() alone reads that as black,
	 * or as nothing at all, and quietly skips the very thing being measured.
	 */
	const parse = ( colour ) => {
		const text = ( colour || '' ).trim();
		const m = /^(?:rgba?|color)\(([^)]+)\)/.exec( text );

		if ( ! m ) {
			return null;
		}

		const scale = text.startsWith( 'color(' ) ? 255 : 1;
		const parts = m[ 1 ].replace( /^srgb\s+/, '' ).split( /[\s,\/]+/ ).filter( ( x ) => '' !== x ).map( Number );

		return { r: parts[ 0 ] * scale, g: parts[ 1 ] * scale, b: parts[ 2 ] * scale, a: undefined === parts[ 3 ] ? 1 : parts[ 3 ] };
	};

	const over = ( top, bottom ) => ( {
		r: top.r * top.a + bottom.r * ( 1 - top.a ),
		g: top.g * top.a + bottom.g * ( 1 - top.a ),
		b: top.b * top.a + bottom.b * ( 1 - top.a ),
		a: 1,
	} );

	const behind = ( el ) => {
		const layers = [];
		let node = el;

		while ( node && 1 === node.nodeType ) {
			const style = getComputedStyle( node );
			const filter = style.backdropFilter || style.webkitBackdropFilter || 'none';
			const bright = /brightness\(\s*([\d.]+)%?\s*\)/.exec( filter );
			const image = style.backgroundImage;
			const colour = parse( style.backgroundColor );

			if ( image && 'none' !== image ) {
				const stops = [ ...image.matchAll( /(?:rgba?|color)\([^)]+\)/g ) ].map( ( x ) => parse( x[ 0 ] ) ).filter( Boolean );
				const solid = stops.filter( ( x ) => x.a > 0.9 );

				if ( solid.length ) {
					layers.push( { colour: solid[ Math.floor( solid.length / 2 ) ] } );
					break;
				}
			}

			if ( colour && colour.a > 0 ) {
				layers.push( { colour } );

				if ( colour.a >= 0.999 ) {
					break;
				}
			}

			if ( bright ) {
				layers.push( { bright: Number( bright[ 1 ] ) * ( filter.includes( bright[ 1 ] + '%' ) ? 0.01 : 1 ) } );
			}

			node = node.parentElement;
		}

		let out = { r: 255, g: 255, b: 255, a: 1 };

		for ( let i = layers.length - 1; i >= 0; i -= 1 ) {
			const step = layers[ i ];

			if ( undefined !== step.bright ) {
				out = { r: out.r * step.bright, g: out.g * step.bright, b: out.b * step.bright, a: 1 };
				continue;
			}

			out = step.colour.a >= 0.999 ? step.colour : over( step.colour, out );
		}

		return out;
	};

	const lum = ( c ) => {
		const f = ( v ) => {
			const s = v / 255;
			return s <= 0.03928 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 );
		};
		return 0.2126 * f( c.r ) + 0.7152 * f( c.g ) + 0.0722 * f( c.b );
	};

	const words = [ ...wrap.querySelectorAll( 'tbody .lstabp-pill .lstab-cell-value' ) ];
	const seen = [];

	for ( const word of words ) {
		const ink = parse( getComputedStyle( word ).color );

		if ( ! ink ) {
			seen.push( { ratio: 0, ink: getComputedStyle( word ).color, unreadable: true } );
			continue;
		}

		const paper = behind( word );
		const front = ink.a >= 0.999 ? ink : over( ink, paper );
		const a = lum( front );
		const b = lum( paper );

		seen.push( {
			ratio: Math.round( ( ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 ) ) * 100 ) / 100,
			line: getComputedStyle( word ).borderTopColor,
		} );
	}

	seen.sort( ( x, y ) => x.ratio - y.ratio );

	return { badges: words.length, worst: seen[ 0 ] || null };
};

for ( const one of said.pills.tables ) {
	const pills = await page.evaluate( readPills, one.id );

	if ( ! check( ! pills.missing && pills.badges > 0, `${ one.skin }: the badges are drawn`, JSON.stringify( pills ) ) ) {
		continue;
	}

	check(
		pills.worst.ratio >= 4.5,
		`${ one.skin }: the worst badge anybody can pick is still readable (${ pills.worst.ratio }:1)`,
		JSON.stringify( pills.worst )
	);
}

// ---------------------------------------------------------------- the end

check( 0 === problems.length, 'no script errors on any of the pages', problems.join( ' | ' ) );

await browser.close();

console.log( `\n  ${ passed } passed${ failed ? `, ${ failed } failed` : '' }\n` );
process.exit( failed ? 1 : 0 );
