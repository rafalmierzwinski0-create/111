/*
 * A page served from a page cache still brings its tables up to date.
 *
 * A page cache hands out a stored copy without starting WordPress, so the
 * schedule and the check-on-view never run. Here the cache is played by the
 * browser itself: the first visit's HTML is kept, the sheet then changes in
 * "Google" and the table falls overdue, and every later visit is answered
 * with that kept HTML — exactly what LiteSpeed or WP Rocket would send.
 *
 * Checked:
 *   1. the first visitor to the old copy asks WordPress, once, in the
 *      background, and the table on screen becomes the new one;
 *   2. the next visitor to the same old copy does not ask again — the due
 *      file already says a newer copy exists — and still gets the new table;
 *   3. a visitor who has typed into the search box keeps their table;
 *   4. a fresh page asks nothing at all.
 *
 * Usage: node tests/keep-current-browser.mjs   (servers from tests/setup-env.sh)
 */
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import { execFileSync } from 'child_process';

const SITE = '/tmp/lstab-env/wp71';
const BASE = 'http://127.0.0.1:8089';

let pass = 0, fail = 0;
const ok = ( name, cond, detail = '' ) => {
	if ( cond ) { pass++; console.log( '  \x1b[32mPASS\x1b[0m  ' + name ); }
	else { fail++; console.log( '  \x1b[31mFAIL\x1b[0m  ' + name + ( detail ? '\n        ' + detail : '' ) ); }
};

// A little PHP inside the site, for the parts a visitor cannot do.
const php = ( code ) => execFileSync( 'php', [ '-r', `define( 'WP_USE_THEMES', false ); require '${ SITE }/wp-load.php'; ${ code }` ], { encoding: 'utf8' } ).trim();
const mock = ( tab ) => php( `file_put_contents( WP_CONTENT_DIR . '/lstab-mock-state.json', wp_json_encode( array( 'mode' => 'ok', 'tab' => '${ tab }' ) ) );` );

mock( 'main' );
const made = JSON.parse( php( `
	$id = LSTAB_Storage::insert( array( 'title' => 'Keep current', 'sheet_url' => 'https://docs.google.com/spreadsheets/d/KEEPCURRENTSHEET0000000000000000000000/edit', 'sheet_id' => 'KEEPCURRENTSHEET0000000000000000000000', 'sync_interval' => 900 ) );
	LSTAB_Sync::run( $id );
	$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Keep current', 'post_content' => '[sheet_table id="' . $id . '"]' ) );
	LSTAB_Freshness::write();
	echo wp_json_encode( array( 'id' => $id, 'page' => $page, 'url' => get_permalink( $page ) ) );
` ) );

const b = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' } );

const visit = async ( served, todo ) => {
	const c = await b.newContext();
	const p = await c.newPage();
	const seen = { asked: 0, due: 0, swapped: 0 };
	p.on( 'request', ( r ) => {
		if ( r.url().includes( 'admin-ajax.php' ) && 'POST' === r.method() ) seen.asked++;
		if ( r.url().includes( 'live-sheets-table-due.json' ) ) seen.due++;
		if ( r.url().includes( 'lstab-copy=' ) ) seen.swapped++;
	} );
	if ( served ) {
		// The page cache: the page's own address answers with the kept HTML.
		await p.route( ( url ) => url.href.split( '#' )[ 0 ] === made.url, ( r ) => r.fulfill( { status: 200, contentType: 'text/html; charset=UTF-8', body: served } ) );
	}
	await p.goto( made.url );
	if ( todo ) await todo( p );
	await p.waitForTimeout( 2500 );
	const state = await p.evaluate( () => {
		const root = document.querySelector( '.lstab[data-lstab-id]' );
		return { copy: root.getAttribute( 'data-lstab-copy' ), text: root.textContent.replace( /\s+/g, ' ' ) };
	} );
	await c.close();
	return { ...seen, ...state };
};

console.log( '\nA page from a page cache keeps its tables current' );

// The first, honest visit: this is the HTML the cache keeps.
const first = await b.newContext();
const fp = await first.newPage();
await fp.goto( made.url );
const cachedHtml = await fp.content();
const oldCopy = await fp.evaluate( () => document.querySelector( '.lstab[data-lstab-id]' ).getAttribute( 'data-lstab-copy' ) );
await first.close();

const fresh = await visit( null );
ok( 'A fresh page asks WordPress for nothing', 0 === fresh.asked && 0 === fresh.swapped, JSON.stringify( fresh ) );

// The sheet changes in Google, and the table falls overdue.
mock( 'second' );
php( `global $wpdb; $wpdb->update( LSTAB_Storage::table(), array( 'last_success_gmt' => gmdate( 'Y-m-d H:i:s', time() - 7200 ) ), array( 'id' => ${ made.id } ) ); LSTAB_Storage::flush_cache( ${ made.id } ); LSTAB_Freshness::write();` );

const one = await visit( cachedHtml );
ok( 'The first visitor to the old copy asks once, in the background', 1 === one.asked && 1 === one.due, JSON.stringify( one ) );
ok( 'And sees the new table without reloading', one.copy && one.copy !== oldCopy, `${ oldCopy } → ${ one.copy }` );

const two = await visit( cachedHtml );
ok( 'The next visitor to the same old copy does not ask again', 0 === two.asked, JSON.stringify( two ) );
ok( 'But still gets the new table', two.copy === one.copy && 1 === two.swapped, JSON.stringify( two ) );

const typing = await visit( cachedHtml, async ( p ) => { await p.fill( '.lstab-search-input', 'x' ); } );
ok( 'A visitor already searching keeps the table they are using', typing.copy === oldCopy, `${ typing.copy }` );

php( `wp_delete_post( ${ made.page }, true ); LSTAB_Storage::delete( ${ made.id } ); file_put_contents( WP_CONTENT_DIR . '/lstab-mock-state.json', wp_json_encode( array( 'mode' => 'ok', 'tab' => 'main' ) ) );` );
await b.close();

console.log( `\n${ pass } passed, ${ fail } failed` );
process.exit( fail ? 1 : 0 );
