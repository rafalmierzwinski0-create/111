/**
 * The things a person notices in the first minute and no assertion about the
 * markup does: a switch on the edit screen that changes nothing in the preview,
 * a list that opens and cannot be closed, sort arrows too faint to see, a glow
 * painted over the buttons under the table, a column with no lines between its
 * rows while the others have them, a heading row that sticks with rows peeking
 * out above it or through its corners, a table whose edge is lost against the
 * page, and help text that sends the reader to a place or a button that is not
 * there.
 *
 * Every check is measured the way a reader sees it: in pixels on a screenshot,
 * or by clicking and looking at what changed. Nothing here names a style or a
 * control it expects to misbehave; each rule applies to everything on screen,
 * so the next style, card or switch is held to it without anyone remembering
 * to add it.
 *
 * Usage: node tests/ux-audit.mjs   (servers from tests/setup-env.sh; the add-on
 * active for its styles, cards and filter menus)
 */
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import { execFileSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { decodePng } from './harness/png.mjs';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const repo = process.env.LSTAB_REPO || path.join( here, '..' );
const SITE = '/tmp/lstab-env/wp71';
const BASE = 'http://127.0.0.1:8089';
const SHOTS = process.env.LSTAB_UX_SHOTS || '/tmp/lstab-env/ux-audit-failures';
fs.rmSync( SHOTS, { recursive: true, force: true } );
fs.mkdirSync( SHOTS, { recursive: true } );
let kept = 0;
// The two pictures behind a failed comparison, so a failure can be looked at.
const keep = ( name, before, after ) => {
	kept += 1;
	const base = path.join( SHOTS, `${ String( kept ).padStart( 2, '0' ) }-${ name.replace( /[^a-z0-9]+/gi, '-' ).slice( 0, 60 ) }` );
	[].concat( before ).forEach( ( shot, i ) => fs.writeFileSync( `${ base }-before-${ i }.png`, Buffer.from( shot, 'base64' ) ) );
	[].concat( after || [] ).forEach( ( shot, i ) => fs.writeFileSync( `${ base }-after-${ i }.png`, Buffer.from( shot, 'base64' ) ) );
	return base;
};

let passed = 0;
let failed = 0;

const VERBOSE = !! process.env.LSTAB_UX_VERBOSE;

const check = ( ok, what, detail = '' ) => {
	if ( ok ) {
		passed += 1;
		console.log( '  \x1b[32mPASS\x1b[0m ', what, VERBOSE && detail ? `  [${ detail }]` : '' );
		return;
	}
	failed += 1;
	console.log( '  \x1b[31mFAIL\x1b[0m ', what );
	if ( detail ) {
		console.log( '        ', String( detail ).slice( 0, 400 ) );
	}
};

const section = ( title ) => console.log( `\n\x1b[1m${ title }\x1b[0m` );

const distance = ( a, b ) => Math.sqrt( ( a[ 0 ] - b[ 0 ] ) ** 2 + ( a[ 1 ] - b[ 1 ] ) ** 2 + ( a[ 2 ] - b[ 2 ] ) ** 2 );

const luminance = ( c ) => {
	const [ r, g, b ] = c.map( ( v ) => {
		const s = v / 255;
		return s <= 0.03928 ? s / 12.92 : ( ( s + 0.055 ) / 1.055 ) ** 2.4;
	} );
	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

const contrast = ( a, b ) => {
	const [ hi, lo ] = [ luminance( a ), luminance( b ) ].sort( ( x, y ) => y - x );
	return ( hi + 0.05 ) / ( lo + 0.05 );
};

const grab = async ( page ) => decodePng( await page.screenshot() );

const px = ( shot, x, y ) => shot.at( Math.max( 0, Math.min( shot.width - 1, Math.round( x ) ) ), Math.max( 0, Math.min( shot.height - 1, Math.round( y ) ) ) );

execFileSync( 'php', [ path.join( here, 'harness/ux-audit-setup.php' ), SITE, 'teardown' ] );
console.log( '  ' + execFileSync( 'php', [ path.join( here, 'harness/ux-audit-setup.php' ), SITE ] ).toString().trim() );

const manifest = JSON.parse( fs.readFileSync( '/tmp/lstab-env/ux-audit.json', 'utf8' ) );
const sourceId = manifest.source;
const wideId = manifest.wide;

const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );

const visitorContext = await browser.newContext( { viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 } );
const visitor = await visitorContext.newPage();
const errors = [];
visitor.on( 'pageerror', ( e ) => errors.push( e.message ) );

const ownerContext = await browser.newContext( { viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 } );
const owner = await ownerContext.newPage();
owner.on( 'pageerror', ( e ) => errors.push( e.message ) );
await owner.goto( BASE + '/wp-login.php' );
await owner.fill( '#user_login', 'admin' );
await owner.fill( '#user_pass', 'admin123' );
await Promise.all( [ owner.waitForURL( /wp-admin/ ), owner.click( '#wp-submit' ) ] );

// The test server now and then drops a stylesheet; a page drawn without it
// would be judged on how it looks with no styles at all.
const open = async ( page, url ) => {
	for ( let attempt = 0; attempt < 4; attempt += 1 ) {
		await page.goto( url, { waitUntil: 'networkidle' } );
		const styled = await page.evaluate( () => {
			const icon = document.querySelector( '.lstab-sort-icon' );
			return ! icon || icon.getBoundingClientRect().width > 0;
		} );
		if ( styled ) {
			return;
		}
	}
};

const settle = async ( page, ms = 350 ) => {
	await page.waitForLoadState( 'networkidle' ).catch( () => {} );
	await page.waitForTimeout( ms );
};

/*
 * Lines between rows. Where a style draws a line under the cells of a row, it
 * has to be visible in every column of that row: a reader takes a column
 * without lines for a fault, and a tinted first column or a banded column is
 * exactly where a line the colour of the border disappears. The line is the
 * bottom pixel row of each cell; it is compared with the cell's own padding
 * just above it, at the cell's left edge, where there is no text.
 */
section( 'Lines between rows reach every column' );

const LINE = 9;

for ( const { url, skin, pin } of [ ...manifest.pages.map( ( p ) => ( { ...p, pin: 'first column pinned' } ) ), ...manifest.unpinned.map( ( p ) => ( { ...p, pin: 'first column free' } ) ) ] ) {
	await open( visitor, url );
	const cells = await visitor.evaluate( () => {
		const table = document.querySelector( '.lstab-table' );
		const pane = table.closest( '.lstab-scroll' );
		pane.scrollTop = 0;
		pane.scrollIntoView( { block: 'start' } );
		window.scrollBy( 0, -60 );
		// Only the columns in sight: a table wider than its box hides the rest
		// until it is scrolled sideways.
		const box = pane.getBoundingClientRect();
		return [ ...table.querySelectorAll( 'tbody tr:not(.lstab-detail)' ) ].slice( 0, 4 ).map( ( tr ) => [ ...tr.children ].map( ( td ) => {
			const r = td.getBoundingClientRect();
			return { left: r.left, top: r.top, bottom: r.bottom, width: r.width };
		} ).filter( ( c ) => c.left + c.width / 2 < box.right - 4 && c.left >= box.left - 1 ) );
	} );
	await visitor.waitForTimeout( 150 );
	const shot = await grab( visitor );
	const median = ( list ) => [ ...list ].sort( ( a, b ) => a - b )[ Math.floor( list.length / 2 ) ];
	const columns = cells[ 0 ].length;
	// How far the line stands out from its own cell, column by column.
	const strength = [ ...Array( columns ).keys() ].map( ( col ) => median( cells.map( ( row ) => {
		const c = row[ col ];
		// Half way across, clear of a card's rounded corner and of the text,
		// which sits higher up in the cell.
		const x = c.left + c.width / 2;
		// With collapsed borders the line straddles the edge of the box, so
		// the strongest of the pixels round the edge is the line.
		const inside = px( shot, x, Math.floor( c.bottom ) - 5 );
		return Math.max( ...[ -2, -1, 0, 1 ].map( ( d ) => distance( px( shot, x, Math.floor( c.bottom ) + d ), inside ) ) );
	} ) ) );
	const typical = median( strength );
	// A style with lines has them in every column, and about as clearly: one
	// at less than half the strength of the rest reads as missing.
	const faint = typical >= LINE ? strength.map( ( v, i ) => [ v, i ] ).filter( ( [ v ] ) => v < Math.max( LINE, typical / 2 ) ) : [];
	check( 0 === faint.length, `${ skin }, ${ pin }: a line between rows shows in every column, as clearly as in the others`, faint.length ? `column ${ faint.map( ( [ v, i ] ) => `${ i + 1 } (${ Math.round( v ) })` ).join( ', ' ) } against ${ Math.round( typical ) } in the rest` : `strength ${ strength.map( Math.round ).join( ' / ' ) }` );
}

/*
 * Sort arrows. Big enough to see and click, and with enough contrast against
 * the heading behind them: the upper arrow is sampled, a quarter of the way
 * down the icon, against the heading just beside it.
 */
section( 'Sort arrows can be seen' );

const ARROW_SIZE = 12;
const ARROW_CONTRAST = 2;

for ( const { url, skin } of manifest.pages ) {
	await open( visitor, url );
	const icon = await visitor.evaluate( () => {
		const th = document.querySelector( '.lstab-table thead th' );
		th.scrollIntoView( { block: 'center' } );
		const i = th.querySelector( '.lstab-sort-icon' );
		if ( ! i ) {
			return null;
		}
		const r = i.getBoundingClientRect();
		return { x: r.left + r.width / 2, y: r.top + r.height * 0.22, w: r.width, h: r.height, beside: r.right + 3, mid: r.top + r.height / 2 };
	} );
	if ( ! icon ) {
		check( false, `${ skin }: the headings have sort arrows`, 'no .lstab-sort-icon' );
		continue;
	}
	await visitor.waitForTimeout( 150 );
	const shot = await grab( visitor );
	const ratio = contrast( px( shot, icon.x, icon.y ), px( shot, icon.beside, icon.mid ) );
	check( icon.w >= ARROW_SIZE && icon.h >= ARROW_SIZE, `${ skin }: the sort arrows are at least ${ ARROW_SIZE }px`, `${ icon.w.toFixed( 1 ) } × ${ icon.h.toFixed( 1 ) }px` );
	check( ratio >= ARROW_CONTRAST, `${ skin }: the sort arrows stand out from the heading behind them`, `contrast ${ ratio.toFixed( 2 ) }:1` );
}

/*
 * Nothing painted under the controls round the table. The page behind the
 * buttons under a table must be the page: a shadow or a glow from the table
 * reaching under them makes them look stuck to it, or dirty. The gaps beside
 * each button are compared with the page at the very edge of the screen, on
 * the same line.
 */
section( 'The buttons under a table sit on the page, not on its shadow' );

// Beside each button, at its top, a quarter down and half way, the page has
// to be the page; a glow from above reaches the top of a button first.
const besideButtons = ( shot, spots, pageAt ) => {
	const dirty = [];
	for ( const s of spots ) {
		for ( const y of [ s.top + 2, s.top + ( s.bottom - s.top ) / 4, s.y ] ) {
			const page = pageAt( y );
			for ( const x of [ s.left - 4, s.right + 4 ] ) {
				const d = distance( px( shot, x, y ), page );
				if ( d > 12 && ! spots.some( ( o ) => x >= o.left - 1 && x <= o.right + 1 ) ) {
					dirty.push( `${ s.text } (${ Math.round( d ) })` );
				}
			}
		}
	}
	return [ ...new Set( dirty ) ];
};

for ( const { url, skin } of manifest.pages ) {
	await open( visitor, url );
	const spots = await visitor.evaluate( () => {
		const root = document.querySelector( '.lstab' );
		const controls = [ ...root.querySelectorAll( '.lstabp-export a, .lstabp-export button, .lstab-pager a, .lstab-pager span' ) ].filter( ( el ) => el.getBoundingClientRect().width > 0 );
		if ( ! controls.length ) {
			return null;
		}
		controls[ 0 ].scrollIntoView( { block: 'center' } );
		return controls.map( ( el ) => {
			const r = el.getBoundingClientRect();
			return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, y: r.top + r.height / 2, text: el.textContent.trim() };
		} );
	} );
	if ( ! spots ) {
		continue;
	}
	await visitor.waitForTimeout( 150 );
	const shot = await grab( visitor );
	const dirty = besideButtons( shot, spots, ( y ) => px( shot, 2, y ) );
	check( 0 === dirty.length, `${ skin }: nothing from the table is painted under its buttons`, dirty.join( ', ' ) );
}

/*
 * The edge of the table. In the table layout a reader has to see where the
 * table ends and the page begins: by a frame, a shadow, or a background of its
 * own. Measured on its left side, half way down.
 */
section( 'Every table has an edge the eye can find' );

for ( const { url, skin } of manifest.pages ) {
	await open( visitor, url );
	const box = await visitor.evaluate( () => {
		const s = document.querySelector( '.lstab-scroll' );
		s.scrollIntoView( { block: 'center' } );
		const r = s.getBoundingClientRect();
		const cell = document.querySelector( '.lstab-table tbody tr td' ).getBoundingClientRect();
		return { left: r.left, y: r.top + Math.min( r.height / 2, 300 ), rowLeft: cell.left, rowY: cell.top + cell.height / 2 };
	} );
	await visitor.waitForTimeout( 150 );
	const shot = await grab( visitor );
	const page = px( shot, Math.max( 2, box.left - 12 ), box.y );
	const edgeAt = ( x, y ) => Math.max( distance( px( shot, x, y ), page ), distance( px( shot, x + 1, y ), page ), distance( px( shot, x - 2, y ), page ) );
	const edge = Math.max( edgeAt( box.left, box.y ), edgeAt( box.rowLeft, box.rowY ) );
	const inside = Math.max( distance( px( shot, box.left + 6, box.y ), page ), distance( px( shot, box.rowLeft + 6, box.rowY ), page ) );
	check( edge >= 14 || inside >= 14, `${ skin }: the table’s edge stands out from the page`, `edge ${ Math.round( edge ) }, inside ${ Math.round( inside ) }` );
}

/*
 * The heading row once it sticks. Scrolled well into a long table, the row of
 * column names has to sit flush at the top of what scrolls — the screen, or
 * the screen under the admin bar for someone logged in — with nothing of the
 * rows showing above it or through its corners.
 */
section( 'The heading row sticks cleanly' );

const stuckHead = async ( page, label, scrollIn, expectTop ) => {
	const at = await page.evaluate( scrollIn );
	await page.waitForTimeout( 300 );
	const head = await page.evaluate( () => {
		const root = document.querySelector( '#lstab-preview .lstab, .lstab' );
		const ths = [ ...root.querySelectorAll( '.lstab-table thead tr:first-child > th' ) ];
		const first = ths[ 0 ].getBoundingClientRect();
		const last = ths[ ths.length - 1 ].getBoundingClientRect();
		return { top: first.top, left: first.left, right: last.right, bottom: first.bottom };
	} );
	const top = expectTop( at );
	const shot = await grab( page );
	const inner = px( shot, head.left + ( head.right - head.left ) / 2, head.top + 3 );
	const corners = [ px( shot, head.left + 1, head.top + 1 ), px( shot, head.right - 2, head.top + 1 ) ];
	// A rounded box shows the page in its corners, which is right; what must not
	// show there is the table's own rows, scrolled up under the heading.
	const row = px( shot, head.left + 14, head.bottom + 4 );
	const backdrop = px( shot, 2, head.top + 1 );
	const leaks = distance( row, inner ) > 40 && distance( row, backdrop ) > 12 ? corners.filter( ( c ) => distance( c, row ) < 25 ) : [];
	check( Math.abs( head.top - top ) <= 1, `${ label }: the stuck heading row sits flush at the top`, `heading at ${ head.top.toFixed( 1 ) }px, top of the scrolling area at ${ top.toFixed( 1 ) }px` );
	check( 0 === leaks.length, `${ label }: no row shows through its corners`, leaks.map( ( c ) => c.join( ',' ) ).join( ' | ' ) );
};

// A table wider than the page scrolls in a box of its own, and its heading
// row sticks to the top of that box; one that fits sticks to the top of the
// screen, under the admin bar for someone logged in.
const scrollIntoTable = () => {
	const pane = document.querySelector( '.lstab-scroll' );
	const style = getComputedStyle( pane );
	const bar = document.getElementById( 'wpadminbar' );
	const under = bar ? bar.getBoundingClientRect().bottom : 0;
	if ( /auto|scroll/.test( style.overflowY ) && pane.scrollHeight > pane.clientHeight + 4 ) {
		pane.scrollIntoView( { block: 'start' } );
		window.scrollBy( 0, -( under + 20 ) );
		pane.scrollTop = 500;
		return pane.getBoundingClientRect().top + pane.clientTop;
	}
	const t = document.querySelector( '.lstab-table' );
	window.scrollTo( 0, t.getBoundingClientRect().top + window.scrollY + 500 );
	return under;
};

for ( const { url, skin } of manifest.pages ) {
	await open( visitor, url );
	await stuckHead( visitor, `${ skin }, visitor`, scrollIntoTable, ( at ) => at );
}

for ( const width of [ 1280, 1700 ] ) {
	await owner.setViewportSize( { width, height: 900 } );
	await owner.goto( manifest.pages[ 0 ].url, { waitUntil: 'networkidle' } );
	await stuckHead( owner, `${ manifest.pages[ 0 ].skin } at ${ width }px, logged in under the admin bar`, scrollIntoTable, ( at ) => at );
}
await owner.setViewportSize( { width: 1280, height: 900 } );

const editUrl = ( id ) => `${ BASE }/wp-admin/admin.php?page=live-sheets-table-edit&source=${ id }`;

await owner.goto( editUrl( sourceId ), { waitUntil: 'networkidle' } );
await settle( owner, 600 );
await stuckHead( owner, 'the admin preview', () => {
	const p = document.getElementById( 'lstab-preview' );
	p.scrollIntoView( { block: 'start' } );
	window.scrollBy( 0, -40 );
	p.scrollTop = 500;
	return p.getBoundingClientRect().top + p.clientTop;
}, ( at ) => at );

/*
 * Every control that changes how the table looks changes the preview. One at
 * a time, each switch, choice and colour is changed and the preview photographed
 * before and after; a control that leaves the picture as it was either does
 * nothing or does it somewhere the person setting it cannot see.
 */
section( 'Every control on the edit screen shows its effect in the preview' );

const freshEdit = async ( pane, id = manifest.paged ) => {
	await open( owner, editUrl( id ) );
	await settle( owner, 500 );
	await owner.locator( `.lstab-pane-tab[data-lstab-goto="${ pane }"]` ).click();
	await owner.waitForTimeout( 250 );
};

const hoverRow = async () => {
	const cell = await owner.locator( '#lstab-preview .lstab-table tbody td' ).first().boundingBox().catch( () => null );
	if ( cell ) {
		await owner.mouse.move( cell.x + cell.width / 2, cell.y + cell.height / 2 );
		await owner.waitForTimeout( 200 );
	}
};

const previewShot = async ( sideways = false ) => {
	// The "updated … ago" line counts up by itself, so its words are set to
	// one fixed wording first; whether the line is there still shows.
	const steady = () => owner.evaluate( () => {
		document.querySelectorAll( '#lstab-preview .lstab-meta' ).forEach( ( p ) => {
			p.textContent = 'Updated a moment ago';
		} );
	} );
	const frame = async ( end ) => {
		const box = await owner.locator( '#lstab-preview' ).evaluate( ( el, [ bottom, aside ] ) => {
			el.scrollIntoView( { block: 'start' } );
			window.scrollBy( 0, -40 );
			el.scrollTop = bottom ? el.scrollHeight : 0;
			const pane = el.querySelector( '.lstab-scroll' );
			if ( pane && aside ) {
				pane.scrollLeft = Math.min( 160, pane.scrollWidth - pane.clientWidth ) * ( 'rtl' === getComputedStyle( pane ).direction ? -1 : 1 );
				pane.dispatchEvent( new Event( 'scroll' ) );
			}
			const r = el.getBoundingClientRect();
			return { x: r.left, y: Math.max( 0, r.top ), width: r.width, height: Math.min( r.height, window.innerHeight - Math.max( 0, r.top ) ) };
		}, [ end, sideways ] );
		await owner.waitForTimeout( sideways ? 200 : 0 );
		await hoverRow();
		await steady();
		// A redrawn table settles over a few frames (columns evened out, the
		// slider placed): photographed until two pictures in a row agree.
		let last = ( await owner.screenshot( { clip: box } ) ).toString( 'base64' );
		for ( let tries = 0; tries < 6; tries += 1 ) {
			await owner.waitForTimeout( 250 );
			await steady();
			const next = ( await owner.screenshot( { clip: box } ) ).toString( 'base64' );
			if ( next === last ) {
				return next;
			}
			last = next;
		}
		return last;
	};
	// The top of the preview and its foot, where the pages, the "updated" line
	// and the buttons are.
	return [ await frame( false ), await frame( true ) ];
};

// What a control does may only show where the table is too wide for its
// column, or once it is scrolled sideways, so a control that changed nothing
// at full width is tried again on the phone width, scrolled sideways, before
// it counts as changing nothing.
const narrow = async ( act ) => {
	const phone = owner.locator( '[data-lstab-width="390"]' );
	if ( ! await phone.count() ) {
		return false;
	}
	await phone.click();
	await owner.waitForTimeout( 500 );
	const before = await previewShot( true );
	await act();
	return differ( before, await previewShot( true ) );
};

const redrawn = async ( act ) => {
	await Promise.all( [
		owner.waitForResponse( ( r ) => /live-sheets-table\/v1\/(redraw|preview)/.test( decodeURIComponent( r.url() ) ), { timeout: 4000 } ).catch( () => null ),
		act(),
	] );
	await owner.waitForTimeout( 650 );
};

await freshEdit( 'general' );
const controls = await owner.evaluate( () => {
	const out = [];
	const name = ( el ) => {
		const label = el.closest( 'label' );
		const row = el.closest( '.lstab-showhide-row, .lstab-swatch, .lstab-metric, .lstabp-facet-pick, .lstab-checkbox, .lstab-field, p' );
		return ( ( label && label.innerText ) || ( row && row.innerText ) || el.name || el.id || '' ).replace( /\s+/g, ' ' ).trim().slice( 0, 60 );
	};
	document.querySelectorAll( '[data-lstab-shortcode-builder] [data-lstab-set="hide"]' ).forEach( ( b, i ) => {
		out.push( { kind: 'click', selector: `[data-lstab-shortcode-builder] [data-lstab-att="${ b.closest( '[data-lstab-att]' ).dataset.lstabAtt }"] [data-lstab-set="hide"]`, pane: 'general', label: 'Shortcode card: hide ' + b.closest( '.lstab-showhide-row' ).innerText.split( '\n' )[ 0 ] } );
	} );
	const pane = document.querySelector( '[data-lstab-pane="look"]' );
	pane.querySelectorAll( 'input[type="checkbox"], input[type="radio"]:not(:checked), select, input[type="number"], input[type="color"]' ).forEach( ( el ) => {
		const shut = el.closest( '[hidden]' );
		if ( ( shut && shut !== pane ) || el.disabled || 'hidden' === el.type || el.closest( '.lstabp-rule' ) ) {
			return;
		}
		// A path no other element matches: its id, or the chain of positions
		// from the nearest ancestor that has one.
		const path = ( node ) => {
			const steps = [];
			while ( node && node !== document.body ) {
				if ( node.id ) {
					steps.unshift( `#${ CSS.escape( node.id ) }` );
					break;
				}
				const same = [ ...node.parentElement.children ].filter( ( c ) => c.tagName === node.tagName );
				steps.unshift( `${ node.tagName.toLowerCase() }:nth-of-type(${ same.indexOf( node ) + 1 })` );
				node = node.parentElement;
			}
			return steps.join( ' > ' );
		};
		const selector = path( el );
		if ( document.querySelectorAll( selector ).length !== 1 ) {
			return;
		}
		const kind = 'SELECT' === el.tagName ? 'select' : ( 'number' === el.type || 'color' === el.type ? el.type : 'click' );
		out.push( { kind, selector, pane: 'look', label: ( { radio: 'Choose ', select: 'Change ', number: 'Set ', color: 'Pick a colour for ' }[ 'click' === kind ? el.type : kind ] || 'Tick ' ) + name( el ) } );
	} );
	return out;
} );

check( controls.filter( ( c ) => 'look' === c.pane ).length >= 15, `the Appearance tab has the switches and choices this rule is about (${ controls.filter( ( c ) => 'look' === c.pane ).length } found)` );

// Two pictures differ when enough of their pixels differ by enough to see:
// a redraw can move a pixel or two by a shade with nothing to see.
const differ = ( a, b ) => {
	if ( Array.isArray( a ) ) {
		return a.some( ( part, i ) => differ( part, b[ i ] ) );
	}
	if ( a === b ) {
		return false;
	}
	const x = decodePng( Buffer.from( a, 'base64' ) );
	const y = decodePng( Buffer.from( b, 'base64' ) );
	if ( x.width !== y.width || x.height !== y.height ) {
		return true;
	}
	let seen = 0;
	for ( let row = 0; row < x.height; row += 1 ) {
		for ( let col = 0; col < x.width; col += 1 ) {
			if ( distance( x.at( col, row ), y.at( col, row ) ) > 24 && ++seen > 30 ) {
				return true;
			}
		}
	}
	return false;
};

const quiet = [
	// Drawn only while the slider floats over the rows, which depends on where
	// the page happens to be scrolled; tests/colours-browser.mjs checks it.
	/^Pick a colour for Slider outline/,
	// A rule line's column does nothing until the rule also has a value to match.
	/lstabp_rules\[\d+\]\[(column|operator|scope|style)\]/,
];

// Two redraws with nothing changed must look the same, or a difference after
// a change would prove nothing.
await owner.locator( '.lstab-pane-tab[data-lstab-goto="look"]' ).click();
await owner.waitForTimeout( 300 );
const still = await previewShot();
const stillHtml = await owner.evaluate( () => document.getElementById( 'lstab-preview-stage' ).innerHTML );
await redrawn( () => owner.evaluate( () => window.lstabRedrawPreview && window.lstabRedrawPreview() ) );
const again = await previewShot();
const againHtml = await owner.evaluate( () => document.getElementById( 'lstab-preview-stage' ).innerHTML );
let firstChange = '';
if ( differ( still, again ) ) {
	const a = stillHtml.replace( /\s+/g, ' ' );
	const b = againHtml.replace( /\s+/g, ' ' );
	let i = 0;
	while ( i < a.length && a[ i ] === b[ i ] ) {
		i += 1;
	}
	firstChange = i >= Math.min( a.length, b.length ) && a.length === b.length ? 'the markup is the same; only the picture differs' : `markup differs at: …${ a.slice( Math.max( 0, i - 80 ), i + 80 ) }… / …${ b.slice( Math.max( 0, i - 80 ), i + 80 ) }…`;
	firstChange += ' — pictures in ' + keep( 'redrawn-unchanged', still, again );
}
check( ! differ( still, again ), 'the preview looks the same when it is redrawn with nothing changed, so a difference below means something', firstChange );

const setValue = async ( target, kind ) => {
	await redrawn( () => target.evaluate( ( el, type ) => {
		if ( 'color' === type ) {
			el.value = '#d0021b' === el.value ? '#1a7f37' : '#d0021b';
		} else {
			const min = '' === el.min ? 1 : Number( el.min );
			const max = '' === el.max ? 100 : Number( el.max );
			const now = Number( el.value ) || Math.round( ( min + max ) / 2 );
			el.value = String( now + 4 <= max ? now + 4 : Math.max( min, now - 4 ) );
		}
		el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}, kind ) );
	await owner.waitForTimeout( 'number' === kind ? 600 : 0 );
};

for ( const c of controls ) {
	if ( quiet.some( ( q ) => q.test( c.label ) ) ) {
		continue;
	}
	await freshEdit( c.pane );
	let target = owner.locator( c.selector ).first();
	// A control inside a closed panel is opened up first, as a person would.
	await target.evaluate( ( el ) => {
		for ( let d = el.closest( 'details' ); d; d = d.parentElement.closest( 'details' ) ) {
			d.open = true;
		}
	} ).catch( () => {} );
	if ( ! await target.isVisible().catch( () => false ) ) {
		if ( VERBOSE ) {
			console.log( '         (not visible, skipped)', c.label );
		}
		continue;
	}
	await target.scrollIntoViewIfNeeded().catch( () => {} );
	if ( 'select' === c.kind ) {
		const options = await target.evaluate( ( s ) => [ ...s.options ].map( ( o ) => [ o.value, o.textContent.trim() ] ) );
		const now = await target.inputValue();
		for ( const [ value, text ] of options.filter( ( o ) => o[ 0 ] !== now ) ) {
			await freshEdit( c.pane );
			target = owner.locator( c.selector ).first();
			await target.scrollIntoViewIfNeeded().catch( () => {} );
			const before = await previewShot();
			await redrawn( () => target.selectOption( value ) );
			let after = await previewShot();
			if ( ! differ( before, after ) ) {
				await freshEdit( c.pane );
				target = owner.locator( c.selector ).first();
				if ( await narrow( () => redrawn( () => target.selectOption( value ) ) ) ) {
					after = null;
				}
			}
			const moved = null === after || differ( before, after );
			check( moved, `${ c.label.split( ' ' ).slice( 0, 3 ).join( ' ' ) } → “${ text }” — changes the preview`, moved ? '' : 'the preview looked the same before and after, at full width and on a phone' );
		}
		continue;
	}
	const before = await previewShot();
	if ( 'number' === c.kind || 'color' === c.kind ) {
		await setValue( target, c.kind );
	} else {
		await redrawn( () => target.click( { force: true } ) );
	}
	let after = await previewShot();
	if ( ! differ( before, after ) ) {
		await freshEdit( c.pane );
		target = owner.locator( c.selector ).first();
		const again = 'number' === c.kind || 'color' === c.kind ? setValue : () => redrawn( () => target.click( { force: true } ) );
		if ( await narrow( () => again( target, c.kind ) ) ) {
			after = null;
		}
	}
	const moved = null === after || differ( before, after );
	check( moved, `${ c.label } — changes the preview`, moved ? '' : 'the preview looked the same before and after, at full width and on a phone — picture in ' + keep( c.label, before ) );
}

/*
 * "Lines" says what it does in every style: "No lines" leaves no line under
 * any cell, "Full grid" puts one down between the columns too. Measured in the
 * preview, the way the person choosing it sees it.
 */
section( 'Lines: “No lines” and “Full grid” do what they say, in every style' );

// A line is a thin band unlike both sides of it; where one row's shade simply
// gives way to the next, as on a striped table, there is no line at all.
const lineAt = ( shot, c, edge ) => {
	const at = 'bottom' === edge
		? ( d ) => px( shot, c.left + c.width / 2, Math.floor( c.bottom ) + d )
		: ( d ) => px( shot, Math.floor( c.right ) + d, c.top + ( c.bottom - c.top ) * 0.8 );
	const near = at( -5 );
	const far = at( 5 );
	return Math.max( ...[ -2, -1, 0, 1 ].map( ( d ) => Math.min( distance( at( d ), near ), distance( at( d ), far ) ) ) );
};

const previewCells = () => owner.evaluate( () => {
	const preview = document.getElementById( 'lstab-preview' );
	preview.scrollIntoView( { block: 'start' } );
	window.scrollBy( 0, -40 );
	preview.scrollTop = 0;
	const pane = preview.querySelector( '.lstab-scroll' );
	const box = pane.getBoundingClientRect();
	const spacing = getComputedStyle( preview.querySelector( '.lstab-table' ) ).borderSpacing.split( ' ' ).map( parseFloat );
	if ( ( spacing[ 1 ] ?? spacing[ 0 ] ) > 2 ) {
		return { cards: true };
	}
	return [ ...preview.querySelectorAll( '.lstab-table tbody tr:not(.lstab-detail)' ) ].slice( 1, 4 ).map( ( tr ) => [ ...tr.children ].map( ( td ) => {
		const r = td.getBoundingClientRect();
		return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width };
	} ).filter( ( c ) => c.right < box.right - 6 && c.left >= box.left - 1 && c.bottom < window.innerHeight - 4 ) );
} );

const linesSelect = '[data-lstab-pane="look"] select[data-lstab-token="lines"]';
await freshEdit( 'look' );
if ( await owner.locator( linesSelect ).count() ) {
	const styles = await owner.evaluate( () => [ ...document.querySelectorAll( '[data-lstab-pane="look"] input[name="style_preset"]' ) ].map( ( r ) => r.value ) );
	for ( const style of styles ) {
		for ( const [ choice, want ] of [ [ 'none', 'no line under any cell' ], [ 'grid', 'a line between the columns' ] ] ) {
			await freshEdit( 'look' );
			const radio = owner.locator( `[data-lstab-pane="look"] input[name="style_preset"][value="${ style }"]` );
			if ( ! await radio.isChecked() ) {
				await redrawn( () => radio.click( { force: true } ) );
			}
			await owner.locator( '[data-lstab-pane="look"] input[name="layout"][value="table"]' ).click( { force: true } );
			await owner.locator( linesSelect ).selectOption( choice );
			await owner.waitForTimeout( 400 );
			const cells = await previewCells();
			if ( cells.cards ) {
				// Rows set apart as cards keep their outline: that is the card,
				// not a line between rows.
				if ( 'none' === choice ) {
					continue;
				}
			}
			await owner.mouse.move( 2, 2 );
			await owner.waitForTimeout( 150 );
			const shot = await grab( owner );
			if ( 'none' === choice ) {
				const left = cells.flatMap( ( row ) => row.map( ( c, i ) => [ lineAt( shot, c, 'bottom' ), i ] ) ).filter( ( [ v ] ) => v >= LINE );
				check( 0 === left.length, `${ style}: “No lines” leaves ${ want }`, left.map( ( [ v, i ] ) => `column ${ i + 1 } (${ Math.round( v ) })` ).join( ', ' ) );
			} else if ( ! cells.cards ) {
				const between = cells.flatMap( ( row ) => row.slice( 0, -1 ).map( ( c ) => lineAt( shot, c, 'right' ) ) );
				const shown = between.filter( ( v ) => v >= LINE ).length;
				check( shown >= Math.ceil( between.length * 0.8 ), `${ style }: “Full grid” draws ${ want }`, `${ shown } of ${ between.length } edges have a line` );
			}
		}
	}
} else {
	check( false, 'the Appearance tab has the “Lines” choice' );
}

/*
 * The same in the preview on the edit screen, in every style: the buttons
 * under the table sit on the preview's own background.
 */
section( 'Under the preview, too, the buttons sit on the background' );

if ( manifest.pro ) {
	await freshEdit( 'look', sourceId );
	const styles = await owner.evaluate( () => [ ...document.querySelectorAll( '[data-lstab-pane="look"] input[name="style_preset"]' ) ].map( ( r ) => r.value ) );
	for ( const style of styles ) {
		await freshEdit( 'look', sourceId );
		const radio = owner.locator( `[data-lstab-pane="look"] input[name="style_preset"][value="${ style }"]` );
		if ( ! await radio.isChecked() ) {
			await redrawn( () => radio.click( { force: true } ) );
		}
		const found = await owner.evaluate( () => {
			const preview = document.getElementById( 'lstab-preview' );
			const controls = [ ...preview.querySelectorAll( '.lstabp-export a, .lstabp-export button' ) ].filter( ( el ) => el.getBoundingClientRect().width > 0 );
			if ( ! controls.length ) {
				return null;
			}
			preview.scrollTop = preview.scrollHeight;
			controls[ 0 ].scrollIntoView( { block: 'center' } );
			const edge = preview.getBoundingClientRect().left + 3;
			return { edge, spots: controls.map( ( el ) => {
				const r = el.getBoundingClientRect();
				return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, y: r.top + r.height / 2, text: el.textContent.trim() };
			} ) };
		} );
		if ( ! found ) {
			check( false, `${ style }: the preview has its download buttons`, 'none found' );
			continue;
		}
		await owner.waitForTimeout( 200 );
		const shot = await grab( owner );
		const dirty = besideButtons( shot, found.spots, ( y ) => px( shot, found.edge, y ) );
		check( 0 === dirty.length, `${ style }, in the preview: nothing from the table is painted under its buttons`, dirty.join( ', ' ) );
	}
}

/*
 * Lists that fold. A list shortened with "Show more" or "Show them all" can be
 * shortened again once it has been opened, and goes back to the length it
 * started at.
 */
section( 'A list that opens can be closed again' );

const foldable = async ( page, where ) => {
	const groups = await page.evaluate( () => [ ...document.querySelectorAll( 'button, a' ) ]
		.filter( ( b ) => /^\s*show (them all|all|\d+ more)/i.test( b.textContent ) && b.getBoundingClientRect().width > 0 )
		.map( ( b, i ) => {
			b.setAttribute( 'data-ux-fold', String( i ) );
			return { i, text: b.textContent.trim().slice( 0, 40 ) };
		} ) );
	for ( const g of groups ) {
		const opener = page.locator( `[data-ux-fold="${ g.i }"]` );
		if ( ! await opener.isVisible().catch( () => false ) ) {
			continue;
		}
		const box = opener.locator( 'xpath=..' );
		const count = () => box.evaluate( ( p ) => {
			const list = p.previousElementSibling;
			return list ? [ ...list.children ].filter( ( c ) => ! c.hidden ).length : 0;
		} );
		const atFirst = await count();
		await opener.click();
		await page.waitForTimeout( 150 );
		const closer = box.locator( 'button, a' ).filter( { hasText: /fewer|less|collapse|hide (them|the list)/i } ).filter( { visible: true } ).first();
		const can = await closer.count() > 0;
		check( can, `${ where }: after “${ g.text }” there is a way to fold the list again` );
		if ( can ) {
			await closer.click();
			await page.waitForTimeout( 150 );
			check( await count() === atFirst, `${ where }: and folding it puts it back the way it started` );
		}
	}
	return groups.length;
};

await owner.goto( editUrl( wideId ), { waitUntil: 'networkidle' } );
await owner.locator( '.lstab-pane-tab[data-lstab-goto="look"]' ).click();
await owner.waitForTimeout( 300 );
const folds = await foldable( owner, 'the edit screen of a wide sheet' );
if ( manifest.pro ) {
	check( folds > 0, 'the wide sheet’s edit screen has lists that fold, so the rule above was really tried' );
}

/*
 * Menus that open over the table close the way menus do: a click anywhere
 * else, or Escape.
 */
section( 'A menu that opens over the table closes on a click elsewhere or Escape' );

if ( manifest.pro ) {
	await visitor.goto( manifest.pages[ 0 ].url, { waitUntil: 'networkidle' } );
	const menus = visitor.locator( 'details.lstabp-facet' );
	if ( await menus.count() ) {
		const open = () => visitor.evaluate( () => [ ...document.querySelectorAll( 'details.lstabp-facet' ) ].filter( ( d ) => d.open ).length );
		await menus.first().locator( 'summary' ).click();
		check( 1 === await open(), 'a filter menu opens' );
		await visitor.mouse.click( 5, 890 );
		check( 0 === await open(), 'and a click elsewhere closes it' );
		await menus.first().locator( 'summary' ).click();
		await visitor.keyboard.press( 'Escape' );
		check( 0 === await open(), 'and so does Escape' );
		await menus.first().locator( 'summary' ).click();
		await menus.nth( 1 ).locator( 'summary' ).click();
		check( 1 === await open(), 'and opening another closes the first' );
	} else {
		check( false, 'the audit page has filter menus to try', 'no details.lstabp-facet' );
	}
}

/*
 * What the help text points at. A path written "A → B" has to lead somewhere:
 * B a screen under the A menu, or a heading on the A tab of the edit screen. A
 * button or label named in quotation marks has to exist with that wording on
 * some screen of the plugin, or on the page a visitor sees.
 */
section( 'Help text points at things that exist' );

const strings = JSON.parse( execFileSync( 'php', [ path.join( here, 'harness/ui-strings.php' ), repo ] ).toString() );

const screens = [
	'/wp-admin/admin.php?page=live-sheets-table',
	'/wp-admin/admin.php?page=live-sheets-table-sources',
	'/wp-admin/admin.php?page=live-sheets-table-settings',
	'/wp-admin/admin.php?page=live-sheets-table-pro',
	'/wp-admin/admin.php?page=live-sheets-table-edit',
];
const visible = new Set();
const remember = async ( page ) => {
	const texts = await page.evaluate( () => [ ...document.querySelectorAll( 'button, a, label, summary, h1, h2, h3, h4, th, legend, option, strong, b, .lstab-meta, .lstab-pane-tab, .lstab-row-title, .lstab-card-title, .lstab-preset-name, .lstab-showhide-label, .lstab-swatch-name, .lstabp-look-word, .lstab-picker-legend-chip, input[type="submit"], [aria-label]' ) ]
		.flatMap( ( el ) => [ el.innerText || el.value || '', el.getAttribute( 'aria-label' ) || '' ] ) );
	texts.forEach( ( t ) => t.split( /\n+/ ).forEach( ( line ) => visible.add( line.replace( /\s+/g, ' ' ).trim().toLowerCase() ) ) );
};
for ( const s of screens ) {
	await owner.goto( BASE + s, { waitUntil: 'networkidle' } );
	await remember( owner );
}
await owner.goto( editUrl( sourceId ), { waitUntil: 'networkidle' } );
for ( const p of [ 'general', 'look', 'hide' ] ) {
	await owner.locator( `.lstab-pane-tab[data-lstab-goto="${ p }"]` ).click();
	await owner.waitForTimeout( 200 );
	await remember( owner );
}
await visitor.goto( manifest.pages[ 0 ].url, { waitUntil: 'networkidle' } );
await remember( visitor );

const panes = await owner.evaluate( () => Object.fromEntries( [ ...document.querySelectorAll( '.lstab-pane-tab' ) ].map( ( t ) => [
	t.innerText.trim().toLowerCase(),
	[ ...document.querySelectorAll( `[data-lstab-pane="${ t.dataset.lstabGoto }"]` ) ].flatMap( ( p ) => [ ...p.querySelectorAll( 'h2, h3, .lstab-card-title, .lstab-subhead, .lstab-choice-head strong, label' ) ].map( ( h ) => h.innerText.replace( /\s+/g, ' ' ).trim().toLowerCase() ) ),
] ) ) );
const submenu = await owner.evaluate( () => [ ...document.querySelectorAll( '#adminmenu .wp-submenu a' ) ].map( ( a ) => a.innerText.trim().toLowerCase() ) );
const menuTop = await owner.evaluate( () => [ ...document.querySelectorAll( '#adminmenu .wp-menu-name' ) ].map( ( a ) => a.innerText.trim().toLowerCase() ) );

// Words from Google's screens and example values a reader types, not this plugin's labels.
const outside = /^(share|general access|anyone with the link|viewer|restricted|in production|in testing|testing|web application|authorised redirect uris|external|advanced|go to|google sheets api|cron jobs|apis & services|library|enable|publish to web|out of stock|in stock|category is bikes|category is bikes, helmets|is not|is|and|curl|\$1 200|1 215,50|1215\.5|price lt 100|lt|lte|<|<=|google sheets table)$/i;

const paths = new Set();
const quoted = new Set();
for ( const s of strings ) {
	for ( const m of s.text.matchAll( /([A-Z][\w &]{1,30}?) → ([A-Z][\w &]{1,30}?)(?=[.,:;)”"]|\s(?:and|to|or|screen|and then)\b|$)/g ) ) {
		paths.add( JSON.stringify( [ m[ 1 ].trim(), m[ 2 ].trim(), s.where ] ) );
	}
	for ( const m of s.text.matchAll( /“([^”]{2,40})”/g ) ) {
		// Labels start with a capital; a phrase in lower case is an example of
		// what to type or how something reads, unless it has a gap ("…") in it.
		if ( /%/.test( m[ 1 ] ) || m[ 1 ].split( ' ' ).length > 6 || ( /^[a-z]/.test( m[ 1 ] ) && ! m[ 1 ].includes( '…' ) ) ) {
			continue;
		}
		quoted.add( JSON.stringify( [ m[ 1 ], s.where ] ) );
	}
}

for ( const entry of paths ) {
	const [ from, to, where ] = JSON.parse( entry );
	const a = from.toLowerCase();
	const b = to.toLowerCase();
	if ( /share|general access|apis & services|google/i.test( from ) ) {
		continue;
	}
	const ok = ( menuTop.includes( a ) && submenu.includes( b ) )
		|| ( panes[ a ] && panes[ a ].some( ( h ) => h === b || h.startsWith( b ) ) );
	check( !! ok, `“${ from } → ${ to }” leads somewhere`, ok ? '' : `in ${ where }: no screen “${ to }” under the ${ from } menu, and no heading “${ to }” on a ${ from } tab` );
}

for ( const entry of quoted ) {
	const [ label, where ] = JSON.parse( entry );
	const want = label.toLowerCase().replace( /[.…]+$/, '' );
	if ( outside.test( want ) ) {
		continue;
	}
	const pattern = want.includes( '…' ) ? new RegExp( '^' + want.split( '…' ).map( ( p ) => p.trim().replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) ).join( '.+' ) + '$' ) : null;
	const shown = [ ...visible ].some( ( v ) => v === want || v.replace( /[.…:]+$/, '' ) === want || v.startsWith( want + ' ' ) || ( pattern && pattern.test( v ) ) );
	const label_in_code = strings.some( ( s ) => s.text.toLowerCase().replace( /[.…:]+$/, '' ) === want );
	check( shown || label_in_code, `“${ label }” is on a screen with that wording`, shown || label_in_code ? '' : `named in ${ where }, but no screen and no label in the code says it` );
}

check( 0 === errors.length, 'no script errors on any page', [ ...new Set( errors ) ].join( ' | ' ) );

await browser.close();
execFileSync( 'php', [ path.join( here, 'harness/ux-audit-setup.php' ), SITE, 'teardown' ] );

console.log( `\n  ${ passed } passed, ${ failed } failed` );
process.exit( failed ? 1 : 0 );
