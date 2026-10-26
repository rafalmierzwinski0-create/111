/*
 * Tables are checked while somebody looks at them, and the page shows it.
 *
 * Run once with the free plugin alone (every 30 minutes) and once with Pro
 * (every 5 minutes), on real pages in a real browser:
 *
 *   1. a page whose copy is younger than the table's time asks nothing;
 *   2. one whose copy is older is drawn from that copy at once (the HTML the
 *      server sends still holds the old rows), then asks in the background,
 *      once, and the new rows appear on the same page without a reload;
 *   3. the next visitor gets the new rows straight away and asks nothing;
 *   4. two visitors arriving together cost Google one download, not two;
 *   5. a table set to refresh only by hand is never checked, however old;
 *   6. with Pro, a private sheet read through the connected account is kept
 *      current the same way, by an anonymous visitor's check.
 *
 * Usage: node tests/refresh-browser.mjs   (servers from tests/setup-env.sh)
 */
import { chromium } from '/tmp/lstab-env/node_modules/playwright/index.mjs';
import { execFileSync } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const SITE = '/tmp/lstab-env/wp71';
const PORT = '8089';
const PRO = 'live-sheets-table-pro/live-sheets-table-pro.php';

let pass = 0, fail = 0;
const ok = ( name, cond, detail = '' ) => {
	if ( cond ) { pass++; console.log( '  \x1b[32mPASS\x1b[0m  ' + name ); }
	else { fail++; console.log( '  \x1b[31mFAIL\x1b[0m  ' + name + ( detail ? '\n        ' + detail : '' ) ); }
};

const php = ( code ) => execFileSync( 'php', [ '-r', `define( 'WP_USE_THEMES', false ); require '${ SITE }/wp-load.php'; ${ code }` ], { encoding: 'utf8' } ).trim();
const harness = ( file, ...args ) => execFileSync( 'php', [ path.join( here, 'harness', file ), SITE, PORT, ...args ], { encoding: 'utf8' } );
const mock = ( mode, tab = 'main' ) => php( `file_put_contents( WP_CONTENT_DIR . '/lstab-mock-state.json', wp_json_encode( array( 'mode' => '${ mode }', 'tab' => '${ tab }', 'oauth' => 'ok' ) ) ); @unlink( WP_CONTENT_DIR . '/lstab-mock-log.txt' );` );
const downloads = () => Number( php( `$f = WP_CONTENT_DIR . '/lstab-mock-log.txt'; echo file_exists( $f ) ? substr_count( (string) file_get_contents( $f ), 'docs.google.com' ) : 0;` ) );

// A copy as old as asked, for both the success and the attempt, with the file
// the browser reads written to match.
const age = ( id, seconds ) => php( `
	global $wpdb;
	$when = gmdate( 'Y-m-d H:i:s', time() - ${ seconds } );
	$wpdb->update( LSTAB_Storage::table(), array( 'last_attempt_gmt' => $when, 'last_success_gmt' => $when ), array( 'id' => ${ id } ) );
	LSTAB_Storage::flush_cache( ${ id } );
	delete_transient( 'lstab_view_refresh_${ id }' );
	delete_transient( LSTAB_Sync::COOLDOWN_PREFIX . ${ id } );
	LSTAB_Freshness::write();
` );

const OLD = 'Rower górski';
const NEW = 'Bike Centrum';

const made = [];
const table = ( title, interval ) => {
	mock( 'ok', 'main' );
	const out = JSON.parse( php( `
		$id = LSTAB_Storage::insert( array( 'title' => '${ title }', 'sheet_url' => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0', 'sheet_id' => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789', 'sheet_kind' => 'doc', 'gid' => '0', 'sync_interval' => ${ interval } ) );
		LSTAB_Sync::run( $id );
		$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => '${ title }', 'post_content' => '[sheet_table id="' . $id . '"]' ) );
		LSTAB_Freshness::write();
		echo wp_json_encode( array( 'id' => $id, 'page' => $page, 'url' => get_permalink( $page ) ) );
	` ) );
	made.push( out );
	return out;
};

const browser = await chromium.launch( { executablePath: '/opt/pw-browsers/chromium' } );

// One visitor: what the server sent, what the page showed at once, whether it
// asked, and what the table holds once the page has settled.
const visit = async ( url, settle = 6000 ) => {
	const context = await browser.newContext();
	const page = await context.newPage();
	const seen = { asked: 0 };
	page.on( 'request', ( r ) => {
		if ( r.url().includes( 'admin-ajax.php' ) && 'POST' === r.method() ) seen.asked++;
	} );
	const response = await page.goto( url, { waitUntil: 'domcontentloaded' } );
	seen.sent = await response.text();
	seen.first = await page.locator( '.lstab-table tbody' ).first().innerText();
	// A mark on this very document: a reload would take it away.
	await page.evaluate( () => { window.lstabSameDocument = true; } );
	await page.waitForTimeout( settle );
	seen.sameDocument = await page.evaluate( () => true === window.lstabSameDocument );
	seen.last = await page.locator( '.lstab-table tbody' ).first().innerText();
	await context.close();
	return seen;
};

const run = async ( label, interval, younger, older ) => {
	console.log( `\n\x1b[1m${ label }: every ${ interval / 60 } minutes\x1b[0m` );

	const t = table( `Refresh ${ label }`, interval );

	ok( `${ label }: a new table keeps the time it was given`, String( interval ) === php( `echo LSTAB_Limits::interval_of( LSTAB_Storage::get( ${ t.id } ) );` ) );

	// 1. Younger than its time: nothing is asked.
	mock( 'ok', 'second' );
	age( t.id, younger );
	let v = await visit( t.url, 3000 );
	ok( `${ label }: a copy ${ younger / 60 } minutes old is not checked`, 0 === v.asked && 0 === downloads(), `asked ${ v.asked }, downloads ${ downloads() }` );
	ok( `${ label }: and the page keeps it`, v.last.includes( OLD ) && ! v.last.includes( NEW ), v.last.slice( 0, 80 ) );

	// 2. Older than its time: drawn from the copy, then brought up to date.
	mock( 'ok', 'second' );
	age( t.id, older );
	v = await visit( t.url );
	ok( `${ label }: a copy ${ older / 60 } minutes old is still what the server sends`, v.sent.includes( OLD ) && ! v.sent.includes( NEW ) );
	ok( `${ label }: so the visitor sees a table at once, not a wait`, v.first.includes( OLD ) );
	ok( `${ label }: the page asks for a check, once`, 1 === v.asked, `asked ${ v.asked }` );
	ok( `${ label }: Google is asked once`, 1 === downloads(), `downloads ${ downloads() }` );
	ok( `${ label }: and the new rows appear on the same page`, v.last.includes( NEW ) && ! v.last.includes( OLD ), v.last.slice( 0, 80 ) );
	ok( `${ label }: without the page being reloaded`, v.sameDocument );

	// 3. The next visitor: fresh rows, nothing asked.
	mock( 'ok', 'second' );
	v = await visit( t.url, 3000 );
	ok( `${ label }: the next visitor gets the new rows straight from the server`, v.sent.includes( NEW ) && ! v.sent.includes( OLD ) );
	ok( `${ label }: and asks nothing`, 0 === v.asked && 0 === downloads(), `asked ${ v.asked }, downloads ${ downloads() }` );

	// 4. Two visitors at the same moment.
	php( `LSTAB_Sync::run( ${ t.id } );` );
	mock( 'ok', 'main' );
	age( t.id, older );
	const both = await Promise.all( [ visit( t.url ), visit( t.url ) ] );
	ok( `${ label }: two visitors arriving together cost one download`, 1 === downloads(), `downloads ${ downloads() }, asked ${ both.map( ( b ) => b.asked ).join( '+' ) }` );
	ok( `${ label }: and the update reaches the page`, both.some( ( b ) => b.last.includes( OLD ) && ! b.last.includes( NEW ) ), both.map( ( b ) => b.last.slice( 0, 30 ) ).join( ' | ' ) );

	// 5. Only by hand.
	php( `LSTAB_Storage::update( ${ t.id }, array( 'sync_interval' => 0 ) ); LSTAB_Sync::run( ${ t.id } );` );
	mock( 'ok', 'second' );
	age( t.id, 86400 * 365 );
	v = await visit( t.url, 3000 );
	ok( `${ label }: a table refreshed only by hand is not checked after a year`, 0 === v.asked && 0 === downloads(), `asked ${ v.asked }, downloads ${ downloads() }` );
	ok( `${ label }: and keeps its copy`, v.last.includes( OLD ) && ! v.last.includes( NEW ) );
	php( `LSTAB_Storage::update( ${ t.id }, array( 'sync_interval' => ${ interval } ) );` );

	return t;
};

try {
	harness( 'deactivate.php', PRO );
	await run( 'Free', 1800, 25 * 60, 35 * 60 );

	harness( 'activate.php', PRO );
	ok( 'Pro: a new table starts at five minutes', '300' === php( `echo LSTAB_Limits::starting_interval();` ) );
	const t = await run( 'Pro', 300, 240, 360 );

	// 6. A private sheet: only a request carrying the connected account's token
	//    gets rows, and it is an anonymous visitor's check that has to send it.
	console.log( '\n\x1b[1mPro: a private sheet\x1b[0m' );
	mock( 'ok', 'second' );
	php( `
		LSTABP_Google_Auth::exchange_code( 'fake-auth-code' );
		LSTAB_Sync::run( ${ t.id } );
		LSTABP_Private_Sheets::set_private( ${ t.id }, true );
	` );
	mock( 'private_only' );
	age( t.id, 360 );
	const v = await visit( t.url );
	ok( 'Pro: the private sheet is checked when a visitor looks', 1 === v.asked, `asked ${ v.asked }` );
	ok( 'Pro: through the connected account, and the new rows appear', v.last.includes( OLD ) && ! v.last.includes( NEW ), v.last.slice( 0, 80 ) );
	ok( 'Pro: the table is still in order in the dashboard', 'ok' === php( `echo LSTAB_Storage::get( ${ t.id } )['last_status'];` ) );
	php( `LSTABP_Private_Sheets::set_private( ${ t.id }, false ); delete_option( LSTABP_Google_Auth::OPTION_TOKEN );` );
} finally {
	await browser.close();
	for ( const m of made ) {
		php( `LSTAB_Storage::delete( ${ m.id } ); wp_delete_post( ${ m.page }, true );` );
	}
	php( 'LSTAB_Freshness::write();' );
	harness( 'deactivate.php', PRO );
	mock( 'ok', 'main' );
}

console.log( `\n  ${ pass } passed, ${ fail } failed` );
process.exit( fail ? 1 : 0 );
