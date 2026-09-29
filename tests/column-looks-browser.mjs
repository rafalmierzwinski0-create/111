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

console.log( '\nThe card in the dashboard, handing the preview what is being typed' );

/*
 * The card markup is the one tests/column-looks-test.php rendered from the view
 * itself, so this is the real screen rather than an imitation of it. The admin
 * script is loaded on top, exactly as WordPress loads it.
 */
const cardMarkup = fs.readFileSync( path.join( here, 'fixtures/column-looks-card.html' ), 'utf8' );
const adminJs = fs.readFileSync( path.join( repo, 'live-sheets-table-pro/assets/js/lstabp-admin.js' ), 'utf8' );
const adminCss = fs.readFileSync( path.join( repo, 'live-sheets-table-pro/assets/css/lstabp-admin.css' ), 'utf8' );
const cardFile = path.join( repo, 'build/column-looks-card.html' );

/*
 * The table's own stylesheet goes on the card too, because the dashboard
 * loads it there: the chips that show what a look does are drawn in the
 * table's classes on purpose, so a chip cannot show one thing and the page
 * another. Without it here the chips would be measured unstyled and the
 * check would prove nothing.
 */
fs.writeFileSync( cardFile, `<!doctype html><meta charset="utf-8"><style>${ CSS }</style><style>${ adminCss }</style>${ cardMarkup }<script>${ adminJs }</script>` );

const card = await browser.newPage( { viewport: { width: 1100, height: 700 } } );
const cardErrors = [];

card.on( 'pageerror', ( e ) => cardErrors.push( e.message ) );
await card.goto( 'file://' + cardFile );
await card.waitForTimeout( 200 );

const sessionRow = '.lstabp-look:has( .lstabp-look-name:text-is( "Session" ) )';

/*
 * The chooser is a row of chips now, not a dropdown: each one draws the look it
 * offers, so what is clicked is the thing itself rather than its name. It also
 * lives inside the line, which stays closed until somebody wants to change it —
 * so opening the line is part of choosing, here as on the real screen.
 */
const open = async ( row ) => {
	if ( ! await card.locator( `${ row } .lstabp-look-box[open]` ).count() ) {
		await card.click( `${ row } .lstabp-look-head` );
		await card.waitForTimeout( 60 );
	}
};

const pick = async ( row, look ) => {
	await open( row );
	await card.click( `${ row } .lstabp-look-pick[value="${ look }"]`, { force: true } );
};

/*
 * Opened first, always. A closed line hides everything inside it, so a check
 * for "no colour fields showing" would pass on a line nobody had opened and
 * prove nothing at all.
 */
const shown = async () => {
	await open( sessionRow );

	return card.evaluate( () => {
		const row = [ ...document.querySelectorAll( '.lstabp-look' ) ]
			.find( ( r ) => r.querySelector( '.lstabp-look-name' ).textContent === 'Session' );

		return [ ...row.querySelectorAll( '.lstabp-look-colour' ) ]
			.map( ( f ) => ( { forLook: f.dataset.lstabpFor, seen: getComputedStyle( f ).display !== 'none' } ) );
	} );
};

check(
	( await shown() ).every( ( f ) => ! f.seen ),
	'a column with no look chosen shows no colour fields at all'
);

await pick( sessionRow, 'bar' );
await card.waitForTimeout( 80 );

const asBar = await shown();

check(
	asBar.filter( ( f ) => f.seen ).length === 1 && asBar.find( ( f ) => f.seen ).forLook.includes( 'bar' ),
	'a bar is offered one colour and nothing else',
	JSON.stringify( asBar )
);

await pick( sessionRow, 'button' );
await card.waitForTimeout( 80 );

const asButton = await shown();

check(
	asButton.filter( ( f ) => f.seen ).length === 3,
	'a button is offered a background, a text colour and its own words',
	JSON.stringify( asButton )
);

await card.fill( `${ sessionRow } .lstabp-look-label`, 'Read on' );
await card.waitForTimeout( 80 );

const collected = await card.evaluate( () => {
	const fields = {};

	( window.lstabPreviewFields || [] ).forEach( ( collect ) => Object.assign( fields, collect() ) );

	return fields;
} );

check(
	collected.looks && collected.looks.Session && 'button' === collected.looks.Session.look,
	'the preview is told what is being chosen, before anything is saved',
	JSON.stringify( collected.looks )
);
check(
	collected.looks && collected.looks.Session && 'Read on' === collected.looks.Session.label,
	'including the words typed into the button a moment ago'
);
check(
	collected.looks && collected.looks[ 'Seats left' ] && 'bar' === collected.looks[ 'Seats left' ].look,
	'and what was already saved, so nothing disappears while something else is chosen'
);
// Set back to ordinary here, rather than assumed: the card was rendered with
// Booking wearing a button, so leaving it alone would have proved nothing.
await pick( '.lstabp-look:has( .lstabp-look-name:text-is( "Booking" ) )', '' );
await card.waitForTimeout( 80 );

const afterOrdinary = await card.evaluate( () => {
	const fields = {};

	( window.lstabPreviewFields || [] ).forEach( ( collect ) => Object.assign( fields, collect() ) );

	return fields;
} );

check(
	afterOrdinary.looks && ! afterOrdinary.looks.Booking,
	'a column set back to ordinary is left out',
	JSON.stringify( afterOrdinary.looks )
);
check( Array.isArray( collected.facets ), 'the filter columns travel with it too' );

console.log( '\nThe closed line says what the column is' );

/*
 * The whole point of the rewrite. A card of twenty columns is read closed —
 * name, and what that column looks like now. A line that still showed the old
 * look after a choice was made would be worse than a line showing nothing,
 * because it is read without being opened.
 */
const line = () => card.evaluate( () => {
	const row = [ ...document.querySelectorAll( '.lstabp-look' ) ]
		.find( ( r ) => r.querySelector( '.lstabp-look-name' ).textContent === 'Session' );
	const now = row.querySelector( '.lstabp-look-now' );

	const face = now.querySelector( '.lstabp-look-face' );

	return {
		says: now.querySelector( '.lstabp-look-now-name' ).textContent.trim(),
		draws: face ? face.className : '(nothing drawn)',
		open: !! row.querySelector( '.lstabp-look-box[open]' ),
	};
} );

await pick( sessionRow, 'bar' );
await card.waitForTimeout( 80 );

const asBarLine = await line();

check(
	/bar|słupek/i.test( asBarLine.says ) && asBarLine.draws.includes( 'lstabp-bar' ),
	'the closed line follows the choice, drawing it and naming it',
	JSON.stringify( asBarLine )
);

await pick( sessionRow, 'button' );
await card.waitForTimeout( 80 );

const asButtonLine = await line();

check(
	asButtonLine.says !== asBarLine.says && asButtonLine.draws.includes( 'lstabp-look-face' ),
	'and follows it again when it changes',
	`${ asBarLine.says } → ${ asButtonLine.says }`
);

// An ordinary column has nothing to show, and most columns are ordinary:
// twenty identical grey boxes saying the same number is the repetition this
// card was rewritten to stop.
await pick( sessionRow, '' );
await card.waitForTimeout( 80 );

const asPlainLine = await line();

check(
	'(nothing drawn)' === asPlainLine.draws,
	'an ordinary column draws nothing at all — it only says so',
	JSON.stringify( asPlainLine )
);

await pick( sessionRow, 'button' );
await card.waitForTimeout( 80 );

check(
	( await line() ).draws.includes( 'lstabp-look-face' ),
	'and the picture comes back when a look is chosen again'
);

const restAt = await card.evaluate( () => ( {
	closed: [ ...document.querySelectorAll( '.lstabp-look-box' ) ].filter( ( d ) => ! d.open ).length,
	all: document.querySelectorAll( '.lstabp-look-box' ).length,
	/*
	 * checkVisibility, not offsetParent: a closed <details> hides what is
	 * inside it with content-visibility rather than display, and offsetParent
	 * happily reports a chip nobody can see.
	 */
	chips: [ ...document.querySelectorAll( '.lstabp-look-opt' ) ].filter( ( o ) => o.checkVisibility( { contentVisibilityAuto: true, visibilityProperty: true } ) ).length,
} ) );

check(
	restAt.closed === restAt.all - 1 && restAt.chips === 5,
	'and only the line being worked on shows its five choices — not every line at once',
	JSON.stringify( restAt )
);

console.log( '\nThe chooser shows what it is offering' );

/*
 * The whole reason the dropdown was replaced. A chip has to be the look it
 * names — an actual badge, an actual bar — or it is a second label saying the
 * same thing the first one said.
 */
const chips = await card.evaluate( () => {
	const row = [ ...document.querySelectorAll( '.lstabp-look' ) ]
		.find( ( r ) => r.querySelector( '.lstabp-look-name' ).textContent === 'Seats left' );
	const face = ( look ) => row.querySelector( `.lstabp-look-pick[value="${ look }"]` )
		.closest( '.lstabp-look-opt' ).querySelector( '.lstabp-look-face' );

	const bar = face( 'bar' );
	const badge = face( 'pill' ).querySelector( '.lstabp-pill-face' );
	const tinted = face( 'tint' );
	const cta = face( 'button' ).querySelector( '.lstabp-cta-link' );
	const plain = face( '' );

	return {
		barWidth: getComputedStyle( bar, '::after' ).width,
		barColour: getComputedStyle( bar, '::after' ).backgroundColor,
		badgeRound: getComputedStyle( badge ).borderRadius,
		badgeBorder: getComputedStyle( badge ).borderTopWidth,
		tintedBg: getComputedStyle( tinted ).backgroundColor,
		ctaRound: getComputedStyle( cta ).borderRadius,
		ctaBg: getComputedStyle( cta ).backgroundColor,
		plainBg: getComputedStyle( plain ).backgroundColor,
		heights: [ bar, face( 'pill' ), tinted, face( 'button' ), plain ].map( ( f ) => Math.round( f.getBoundingClientRect().height ) ),
	};
} );

check(
	parseFloat( chips.barWidth ) > 10 && chips.barColour.startsWith( 'rgb(95, 227, 207' ),
	'the bar chip really draws a bar, in that column\'s colour',
	`${ chips.barWidth } / ${ chips.barColour }`
);
check(
	parseFloat( chips.badgeRound ) > 20 && chips.badgeBorder === '1px',
	'the pill chip really is the badge',
	`${ chips.badgeRound } / ${ chips.badgeBorder }`
);
check(
	chips.tintedBg !== chips.plainBg && chips.tintedBg !== 'rgba(0, 0, 0, 0)',
	'the whole-column chip really is painted, and the ordinary one is not',
	`${ chips.tintedBg } vs ${ chips.plainBg }`
);
check(
	parseFloat( chips.ctaRound ) > 20 && chips.ctaBg !== 'rgba(0, 0, 0, 0)',
	'and the button chip really is a button',
	`${ chips.ctaRound } / ${ chips.ctaBg }`
);
check(
	new Set( chips.heights ).size === 1,
	'every chip is the same height, so a row of them can be compared',
	chips.heights.join( ', ' )
);

// Moving the colour beside them repaints them: a chip showing teal beside a
// picker set to crimson is worse than no chip at all.
// ":text-is()" is Playwright's own, and this runs inside the page.
const named = `[ ...document.querySelectorAll( '.lstabp-look' ) ].find( ( r ) => r.querySelector( '.lstabp-look-name' ).textContent === 'Seats left' )`;

await card.evaluate( `( () => {
	const field = ${ named }.querySelector( '.lstabp-look-tint' );

	field.value = '#e11d48';
	field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
} )()` );
await card.waitForTimeout( 80 );

const repainted = await card.evaluate( `( () => {
	const row = ${ named };
	const face = ( look ) => row.querySelector( '.lstabp-look-pick[value="' + look + '"]' ).closest( '.lstabp-look-opt' ).querySelector( '.lstabp-look-face' );

	return {
		bar: getComputedStyle( face( 'bar' ), '::after' ).backgroundColor,
		badge: getComputedStyle( face( 'pill' ).querySelector( '.lstabp-pill-face' ) ).borderTopColor,
		tinted: getComputedStyle( face( 'tint' ) ).backgroundColor,
	};
} )()` );

check(
	repainted.bar.startsWith( 'rgb(225, 29, 72' ) && repainted.badge.startsWith( 'rgb(225, 29, 72' ) && repainted.tinted.startsWith( 'rgb(225, 29, 72' ),
	'and they follow the colour picker as it moves',
	JSON.stringify( repainted )
);

// A painted column works out its own readable ink, so a dark colour cannot be
// chosen with the text left dark on top of it.
const readable = await card.evaluate( `( () => {
	const face = ${ named }.querySelector( '.lstabp-look-pick[value="tint"]' ).closest( '.lstabp-look-opt' ).querySelector( '.lstabp-look-face' );
	const rgb = ( c ) => c.match( /\\d+/g ).slice( 0, 3 ).map( Number );
	const lum = ( x ) => {
		const v = rgb( x ).map( ( ch ) => {
			const s = ch / 255;

			return s <= 0.03928 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 );
		} );

		return 0.2126 * v[ 0 ] + 0.7152 * v[ 1 ] + 0.0722 * v[ 2 ];
	};
	const one = lum( getComputedStyle( face ).backgroundColor );
	const two = lum( getComputedStyle( face ).color );

	return Math.round( ( ( Math.max( one, two ) + 0.05 ) / ( Math.min( one, two ) + 0.05 ) ) * 100 ) / 100;
} )()` );

check( readable >= 4.5, 'the ink on a painted column is readable against it', `${ readable } : 1` );

check( cardErrors.length === 0, 'no errors on the card', cardErrors.join( ' | ' ) );

console.log( '\nA long list, ten at a time' );

/*
 * Twenty-four columns, one of them already set and near the end. A sheet is
 * allowed fifty, and a card drawing a row per column pushed everything after
 * it off the bottom of the screen.
 */
const longMarkup = fs.readFileSync( path.join( here, 'fixtures/column-looks-long.html' ), 'utf8' );
const longFile = path.join( repo, 'build/column-looks-long.html' );

/*
 * What wp_localize_script() puts on the page beside the script, written by the
 * PHP suite from the plugin's own strings. The button under a folded list is
 * drawn from these, and a page without them keeps the whole list — which is
 * the right thing to do and would quietly turn this into no test at all.
 */
const localised = fs.readFileSync( path.join( here, 'fixtures/admin-settings.json' ), 'utf8' );
const pageHead = `<!doctype html><meta charset="utf-8"><style>${ CSS }</style><style>${ adminCss }</style>`;
const script = `<script>window.lstabpRules = ${ localised };</script><script>${ adminJs }</script>`;

fs.writeFileSync( longFile, pageHead + longMarkup + script );

const longPage = await browser.newPage( { viewport: { width: 1100, height: 700 } } );
const longErrors = [];

longPage.on( 'pageerror', ( e ) => longErrors.push( e.message ) );

// Without the script the whole list is on the page, which is the only state in
// which every column can still be reached with JavaScript switched off.
await longPage.setContent( pageHead + longMarkup );
await longPage.waitForTimeout( 80 );

/*
 * Measured as the screen draws it, not as the "hidden" property reports it.
 * The attribute is one selector weaker than a class, so a row can carry it and
 * still be on the page — which is exactly what happened the first time.
 */
const withoutScript = await longPage.$$eval( '.lstabp-look', ( rows ) => rows.filter( ( r ) => 'none' !== getComputedStyle( r ).display ).length );

check( withoutScript === 24, 'with no script every column is on the page', `${ withoutScript }` );

await longPage.goto( 'file://' + longFile );
await longPage.waitForTimeout( 200 );

const visible = () => longPage.$$eval( '.lstabp-look', ( rows ) => rows
	.filter( ( r ) => 'none' !== getComputedStyle( r ).display )
	.map( ( r ) => r.querySelector( '.lstabp-look-name' ).textContent.trim() ) );

const atFirst = await visible();

/*
 * Ten, and the twentieth column as well. What is already set is never folded
 * away: ten rows hiding the one setting somebody came back to change would be
 * worse than the long list they replaced.
 */
check( atFirst.length === 11, 'ten to start with, not twenty-four', atFirst.join( ', ' ) );
check(
	atFirst.includes( 'Column 20' ) && atFirst.slice( 0, 10 ).join() === [ ...Array( 10 ) ].map( ( x, i ) => `Column ${ i + 1 }` ).join(),
	'and the one already set is there too, wherever it sits in the sheet',
	atFirst.join( ', ' )
);

const buttonSays = await longPage.textContent( '.lstabp-fold-more .lstab-mini' );

check(
	/10/.test( buttonSays ) && /13/.test( buttonSays ),
	'the button says how many come next and how many are left',
	buttonSays
);

await longPage.click( '.lstabp-fold-more .lstab-mini' );
await longPage.waitForTimeout( 80 );

const afterOne = await visible();

check( afterOne.length === 20, 'a click brings the next ten', `${ afterOne.length }` );

await longPage.click( '.lstabp-fold-all' );
await longPage.waitForTimeout( 80 );

const afterAll = await visible();
const buttonGone = await longPage.$eval( '.lstabp-fold-more', ( p ) => 'none' === getComputedStyle( p ).display );

check( afterAll.length === 24, 'and "show them all" brings the lot', `${ afterAll.length }` );
check( buttonGone, 'with the button gone once there is nothing left to show' );

check( longErrors.length === 0, 'no errors on the long card', longErrors.join( ' | ' ) );

check( errors.length === 0, 'no errors in the console', errors.join( ' | ' ) );

await tab.screenshot( { path: path.join( repo, 'build/column-looks.png' ), fullPage: true } );
await browser.close();

console.log( `\n${ passed } passed, ${ failed } failed` );
process.exit( failed > 0 ? 1 : 0 );
