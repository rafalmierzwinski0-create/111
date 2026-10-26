/*
 * Nothing shows through a heading row that sticks while the rows scroll.
 *
 * Every style, with a sheet wide enough to scroll inside its own frame and
 * again on a full-width page where the heading row sticks to the top of the
 * window. Each time the heading row is photographed stuck, twice, with
 * different rows underneath: any pixel that differs is a row showing through
 * it or round its edge. And once more under a theme that makes table headings
 * transparent, as some do.
 *
 * Written after rows were seen passing through the heading of the Ledger and
 * Cards styles, whose headings had no background of their own.
 *
 * Needs the Pro add-on active, for the six styles it brings.
 *
 * Usage: node tests/sticky-head-browser.mjs   (servers from tests/setup-env.sh)
 */
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import { execFileSync } from 'child_process';
import fs from 'fs';
import os from 'os';
import path from 'path';

const SITE = '/tmp/lstab-env/wp71';

let pass = 0, fail = 0;
const ok = ( name, cond, detail = '' ) => {
	if ( cond ) { pass++; console.log( '  \x1b[32mPASS\x1b[0m  ' + name ); }
	else { fail++; console.log( '  \x1b[31mFAIL\x1b[0m  ' + name + ( detail ? '\n        ' + detail : '' ) ); }
};

const php = ( code ) => execFileSync( 'php', [ '-r', `define( 'WP_USE_THEMES', false ); require '${ SITE }/wp-load.php'; ${ code }` ], { encoding: 'utf8' } ).trim();

// Eight columns of sixty rows, headings as a Polish shop would write them.
const made = JSON.parse( php( `
	wp_set_current_user( 1 );
	$head = array( 'Produkt ASD', 'Cena netto', 'Dostępność', 'Zamowienie', 'Produkt', 'Cena netto (2)', 'Dostępność (2)', 'Zamowienie (2)' );
	$csv  = implode( ',', $head ) . "\\n";
	for ( $i = 1; $i <= 60; $i++ ) {
		$csv .= "Produkt $i," . ( 100 + $i ) . " zł," . ( $i % 3 ? 'Dostępny' : 'Brak' ) . ",Zam-$i,Rzecz $i," . ( 50 + $i ) . " zł," . ( $i % 2 ? 'Tak' : 'Nie' ) . ",Z-$i\\n";
	}
	file_put_contents( WP_CONTENT_DIR . '/lstab-mock-custom.csv', $csv );
	file_put_contents( WP_CONTENT_DIR . '/lstab-mock-state.json', wp_json_encode( array( 'mode' => 'custom', 'tab' => 'main' ) ) );
	$out = array();
	foreach ( array_keys( LSTAB_Styles::all() ) as $skin ) {
		$id = LSTAB_Storage::insert( array( 'title' => "Sticky heading $skin", 'sheet_url' => 'https://docs.google.com/spreadsheets/d/1STICKYSTICKYSTICKY00000000000000000000/edit', 'sheet_id' => '1STICKYSTICKYSTICKY00000000000000000000', 'style_preset' => $skin, 'layout' => 'table', 'sticky_head' => 1, 'sticky_first' => 1 ) );
		LSTAB_Sync::run( $id );
		$pages = array();
		foreach ( array( 'inner' => '', 'page' => ',"align":"full"' ) as $how => $align ) {
			$pages[ $how ] = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "Sticky heading $skin $how", 'post_content' => '<!-- wp:paragraph --><p>Above.</p><!-- /wp:paragraph -->' . "\\n\\n" . '<!-- wp:live-sheets-table/sheet-table {"sourceId":' . $id . $align . '} /-->' . "\\n\\n" . str_repeat( '<!-- wp:paragraph --><p>Below.</p><!-- /wp:paragraph -->' . "\\n\\n", 30 ) ) );
		}
		$out[] = array( 'skin' => $skin, 'id' => $id, 'pages' => $pages, 'inner' => get_permalink( $pages['inner'] ), 'page' => get_permalink( $pages['page'] ) );
	}
	echo wp_json_encode( $out );
` ) );

const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium' } );
const compare = await ( await browser.newContext() ).newPage();

// Pixels that differ by more than a rounding error between two photographs.
const differ = ( a, b ) => compare.evaluate( async ( [ a, b ] ) => {
	const read = async ( s ) => {
		const bitmap = await createImageBitmap( await ( await fetch( 'data:image/png;base64,' + s ) ).blob() );
		const canvas = new OffscreenCanvas( bitmap.width, bitmap.height );
		const context = canvas.getContext( '2d' );
		context.drawImage( bitmap, 0, 0 );
		return context.getImageData( 0, 0, bitmap.width, bitmap.height );
	};
	const A = await read( a ), B = await read( b );
	if ( A.width !== B.width || A.height !== B.height ) return -1;
	let count = 0;
	for ( let i = 0; i < A.data.length; i += 4 ) {
		if ( Math.max( Math.abs( A.data[ i ] - B.data[ i ] ), Math.abs( A.data[ i + 1 ] - B.data[ i + 1 ] ), Math.abs( A.data[ i + 2 ] - B.data[ i + 2 ] ) ) > 40 ) count++;
	}
	return count;
}, [ a, b ] );

// The heading row, stuck, with the given distance scrolled.
const photograph = async ( page, how, distance ) => {
	const table = page.locator( '.lstab' ).first();
	const frame = table.locator( '.lstab-scroll' );
	if ( 'inner' === how ) {
		await frame.evaluate( ( el, y ) => { el.scrollTop = y; }, distance );
	} else {
		const top = await frame.evaluate( ( el ) => el.getBoundingClientRect().top + scrollY );
		await page.evaluate( ( y ) => scrollTo( { top: y, behavior: 'instant' } ), top + distance );
	}
	await page.waitForTimeout( 300 );
	const cell = await table.locator( 'thead th' ).first().boundingBox();
	const box = await frame.boundingBox();
	const x = Math.max( 0, box.x - 4 );
	const viewport = page.viewportSize();
	const clip = { x, y: Math.max( 0, cell.y - 4 ), width: Math.min( box.width + 8, viewport.width - x ), height: Math.floor( cell.height + Math.min( 4, cell.y ) ) - 1 };
	return { shot: ( await page.screenshot( { clip } ) ).toString( 'base64' ), stuck: await table.evaluate( ( el ) => el.classList.contains( 'lstab-head-stuck' ) ) };
};

const THEME = '.wp-block-post-content thead th, .entry-content thead th { background: transparent; }';

try {
	for ( const t of made ) {
		for ( const [ how, width ] of [ [ 'inner', 1280 ], [ 'page', 1460 ] ] ) {
			for ( const theme of [ false, true ] ) {
				const page = await browser.newPage( { viewport: { width, height: 800 } } );
				await page.goto( t[ how ] );
				if ( theme ) {
					await page.addStyleTag( { content: THEME } );
				}
				const frame = page.locator( '.lstab .lstab-scroll' ).first();
				const mode = await page.locator( '.lstab' ).first().evaluate( ( el ) => el.classList.contains( 'lstab-has-slider' ) ? 'inner' : 'page' );
				if ( mode !== how ) {
					// At this width the sheet scrolls in its own frame, which the
					// other pass already covers for this style.
					await page.close();
					continue;
				}
				if ( 'inner' === how ) {
					await frame.evaluate( ( el ) => el.scrollIntoView() );
					await page.evaluate( () => scrollBy( 0, -30 ) );
				}
				const first = await photograph( page, how, 333 );
				const second = await photograph( page, how, 666 );
				const leaked = await differ( first.shot, second.shot );
				const label = `${ t.skin }, ${ 'inner' === how ? 'scrolling inside its frame' : 'stuck to the top of the window' }${ theme ? ', under a theme with transparent headings' : '' }`;
				ok( `${ label }: the heading row sticks`, first.stuck && second.stuck );
				const clean = 0 <= leaked && leaked <= 12;
				let where = '';
				if ( ! clean ) {
					where = path.join( os.tmpdir(), `lstab-sticky-${ t.skin }-${ how }${ theme ? '-theme' : '' }` );
					fs.writeFileSync( where + '-a.png', Buffer.from( first.shot, 'base64' ) );
					fs.writeFileSync( where + '-b.png', Buffer.from( second.shot, 'base64' ) );
				}
				ok( `${ label }: nothing shows through it`, clean, `${ leaked } pixels changed; the two photographs are ${ where }-a.png and -b.png` );
				await page.close();
			}
		}
	}
} finally {
	await browser.close();
	for ( const t of made ) {
		php( `LSTAB_Storage::delete( ${ t.id } ); wp_delete_post( ${ t.pages.inner }, true ); wp_delete_post( ${ t.pages.page }, true );` );
	}
	php( `file_put_contents( WP_CONTENT_DIR . '/lstab-mock-state.json', wp_json_encode( array( 'mode' => 'ok', 'tab' => 'main' ) ) );` );
}

console.log( `\n  ${ pass } passed, ${ fail } failed` );
process.exit( fail ? 1 : 0 );
