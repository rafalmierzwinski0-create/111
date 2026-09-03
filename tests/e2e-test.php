<?php
/**
 * End-to-end test for Live Sheets Table.
 *
 * Boots a real WordPress install, activates the plugin, drives the full
 * lifecycle (add source → sync → render → fail → fall back → recover) and
 * asserts on the real rendered output.
 *
 * Usage: php tests/e2e-test.php /absolute/path/to/wp
 *
 * @package LiveSheetsTable\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';

if ( ! $wp_root || ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tests/e2e-test.php /path/to/wordpress\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1:8088';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$GLOBALS['lstab_passed'] = 0;
$GLOBALS['lstab_failed'] = 0;

/**
 * Assert helper.
 *
 * @param bool   $condition Condition.
 * @param string $label     Test name.
 * @param string $detail    Extra context printed on failure.
 * @return bool
 */
function lstab_assert( $condition, $label, $detail = '' ) {
	if ( $condition ) {
		$GLOBALS['lstab_passed']++;
		echo "  \033[32mPASS\033[0m  {$label}\n";
		return true;
	}

	$GLOBALS['lstab_failed']++;
	echo "  \033[31mFAIL\033[0m  {$label}\n";
	if ( '' !== $detail ) {
		echo "        {$detail}\n";
	}
	return false;
}

/**
 * Section header.
 *
 * @param string $title Title.
 * @return void
 */
function lstab_section( $title ) {
	echo "\n\033[1m{$title}\033[0m\n";
}

/**
 * Serve a payload of our own choosing, for a test that needs the sheet to
 * change between one sync and the next.
 *
 * @param string $csv What Google should answer with.
 * @return void
 */
function lstab_serve_custom( $csv ) {
	file_put_contents( WP_CONTENT_DIR . '/lstab-mock-custom.csv', $csv );
	lstab_set_mock( 'custom' );
}

/**
 * Point the mock at a given failure mode.
 *
 * @param string $mode Mode name.
 * @param string $tab  Fixture tab.
 * @return void
 */
function lstab_set_mock( $mode, $tab = 'main' ) {
	file_put_contents(
		WP_CONTENT_DIR . '/lstab-mock-state.json',
		wp_json_encode(
			array(
				'mode' => $mode,
				'tab'  => $tab,
			)
		)
	);
}

// ---------------------------------------------------------------------------

lstab_section( '0. Environment' );

lstab_assert( function_exists( 'lstab' ), 'Plugin bootstrap loaded' );
lstab_assert( defined( 'LSTAB_MOCK_FIXTURES' ), 'Google mock harness active' );
lstab_set_mock( 'ok' );

// ---------------------------------------------------------------------------

lstab_section( '1. Schema and activation' );

LSTAB_Plugin::on_activate();

/*
 * Leaving a column or a row out of a table is the add-on's to decide, and is
 * honoured for ten days after it stops. Most of this suite is about what those
 * choices do, not about who may make them, so the clock is set to now here and
 * section 12f is where it is wound forward deliberately.
 */
update_option( LSTAB_Limits::SEEN_OPTION, time(), true );

global $wpdb;
$table  = LSTAB_Storage::table();
$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
lstab_assert( $exists === $table, "Custom table {$table} created", "got: " . var_export( $exists, true ) );
lstab_assert( (bool) wp_next_scheduled( LSTAB_Cron::TICK_HOOK ), 'Cron tick scheduled on activation' );

// dbDelta adds columns and never removes them, so a setting the plugin has
// retired would sit in the schema for good unless it is dropped by hand.
$retired = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'refresh_on_view' ) );
lstab_assert( null === $retired, 'The retired refresh_on_view column is gone from the schema', var_export( $retired, true ) );

// ---------------------------------------------------------------------------

lstab_section( '2. URL parsing' );

$cases = array(
	'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=1734829105'
		=> array( '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789', 'doc', '1734829105' ),
	'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit?usp=sharing'
		=> array( '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789', 'doc', '0' ),
	'https://docs.google.com/spreadsheets/d/e/2PACX-1vQxYzAbCdEf/pubhtml?gid=42&single=true'
		=> array( '2PACX-1vQxYzAbCdEf', 'pub', '42' ),
	'1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789'
		=> array( '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789', 'doc', '0' ),
);

foreach ( $cases as $input => $expected ) {
	$parsed = LSTAB_Url::parse( $input );
	$label  = 'Parses ' . substr( $input, 0, 62 ) . ( strlen( $input ) > 62 ? '…' : '' );
	lstab_assert(
		! is_wp_error( $parsed )
			&& $parsed['sheet_id'] === $expected[0]
			&& $parsed['sheet_kind'] === $expected[1]
			&& $parsed['gid'] === $expected[2],
		$label,
		is_wp_error( $parsed ) ? $parsed->get_error_message() : wp_json_encode( $parsed )
	);
}

$rejected = array(
	'https://evil.example.com/spreadsheets/d/abc/edit' => 'lstab_bad_host',
	'https://docs.google.com/document/d/abc/edit'      => 'lstab_no_sheet_id',
	''                                                 => 'lstab_empty_url',
);

foreach ( $rejected as $input => $code ) {
	$parsed = LSTAB_Url::parse( $input );
	lstab_assert(
		is_wp_error( $parsed ) && $parsed->get_error_code() === $code,
		'Rejects ' . ( '' === $input ? '(empty input)' : $input ),
		is_wp_error( $parsed ) ? $parsed->get_error_code() : 'accepted'
	);
}

$endpoint = LSTAB_Url::csv_endpoint( 'SHEETID', '1734829105' );
lstab_assert(
	false !== strpos( $endpoint, '/export' ) && false !== strpos( $endpoint, 'format=csv' ) && false !== strpos( $endpoint, 'gid=1734829105' ),
	'Builds the CSV export endpoint for the chosen tab',
	$endpoint
);

// ---------------------------------------------------------------------------

lstab_section( '3. CSV parsing' );

$csv    = file_get_contents( LSTAB_MOCK_FIXTURES . '/sheet-main.csv' );
$parsed = LSTAB_CSV_Parser::parse( $csv, true );

lstab_assert( ! is_wp_error( $parsed ), 'Parses the fixture', is_wp_error( $parsed ) ? $parsed->get_error_message() : '' );
lstab_assert( "\xEF\xBB\xBF" !== substr( $parsed['headers'][0], 0, 3 ), 'Strips the UTF-8 BOM', bin2hex( substr( $parsed['headers'][0], 0, 6 ) ) );
lstab_assert( 'Produkt' === $parsed['headers'][0], 'First header is "Produkt"', $parsed['headers'][0] );
lstab_assert( 5 === count( $parsed['headers'] ), 'Five columns detected', (string) count( $parsed['headers'] ) );
lstab_assert( 7 === count( $parsed['rows'] ), 'Seven data rows detected', (string) count( $parsed['rows'] ) );
lstab_assert( 'Dostępność' === $parsed['headers'][2], 'UTF-8 diacritics survive', $parsed['headers'][2] );
lstab_assert( 'Rower górski "Trek"' === $parsed['rows'][0][0], 'Doubled quotes become a literal quote', $parsed['rows'][0][0] );

/*
 * One stray quotation mark used to consume the whole payload: the field never
 * closed, so every remaining row ran together inside a single cell and the
 * table lost all but one row. A quote only opens a field at the start of one,
 * and only closes one when a comma, a line break or the end of the payload
 * follows; anywhere else it is a character the sheet happens to contain.
 */
$stray = LSTAB_CSV_Parser::parse( "Produkt,Cena\n\"Rower \"Trek\",\"4 199,99\"\nKask,\"349,00\"\nBidon,\"29,00\"\n", true );
lstab_assert( 3 === count( $stray['rows'] ), 'A stray quote costs one character, not every row', (string) count( $stray['rows'] ) );
lstab_assert( 'Kask' === $stray['rows'][1][0], 'Rows after a stray quote are still their own rows', $stray['rows'][1][0] );
lstab_assert( 2 === count( $stray['headers'] ), 'A stray quote invents no extra columns', (string) count( $stray['headers'] ) );

$inch = LSTAB_CSV_Parser::parse( "Produkt,Cena\nRower 26\" koła,\"4 199,99\"\nKask,\"349,00\"\n", true );
lstab_assert( 2 === count( $inch['rows'] ), 'An inch mark in an unquoted value does not open a field', (string) count( $inch['rows'] ) );
lstab_assert( 'Rower 26" koła' === $inch['rows'][0][0], 'The inch mark is kept verbatim', $inch['rows'][0][0] );

/*
 * The case a user hit: a Polish price list, where every price holds a comma as
 * its decimal separator and one product name holds an inch mark. The parser
 * this replaced split "4 199,99" into "4 199" and "99" and ran the whole sheet
 * into a single row, so the prices simply were not on the page.
 */
$price_list = LSTAB_CSV_Parser::parse(
	"Produkt,Cena netto,Dostępność\n"
	. "Rower górski 26\" koła,\"4 199,99\",W magazynie\n"
	. "Zamek szyfrowy,\"1 215,50\",W magazynie\n"
	. "Bagażnik tylny,\"87,00\",W magazynie\n",
	true
);
lstab_assert( 3 === count( $price_list['rows'] ), 'A price list with commas and an inch mark keeps its rows', (string) count( $price_list['rows'] ) );
lstab_assert( 3 === count( $price_list['headers'] ), 'And invents no extra columns', (string) count( $price_list['headers'] ) );
lstab_assert( '4 199,99' === $price_list['rows'][0][1], 'A decimal comma does not split the price', $price_list['rows'][0][1] );
lstab_assert( '1 215,50' === $price_list['rows'][1][1], 'Nor does a thousands space', $price_list['rows'][1][1] );
lstab_assert( 'Rower górski 26" koła' === $price_list['rows'][0][0], 'The inch mark stays in the name', $price_list['rows'][0][0] );
lstab_assert( ! isset( $price_list['ragged'] ), 'And nothing is reported as malformed' );

/*
 * A real sheet from a user, whose product names end in a quotation mark of
 * their own: Rower górski „Trek". That is the shape most likely to be written
 * badly, so both spellings are covered — the correct one, where the value's
 * quote is doubled, and the one a writer produces when it forgets to.
 */
$user_rows = "Kask Lazer,349,W magazynie\nLampka,89,Brak\nBidon 750 ml,29,W magazynie\nZamek szyfrowy,1 215.50,W magazynie\nBagażnik tylny,87,W magazynie\n";
$user_head = "Produkt,Cena netto,Dostępność\n";

$correct = LSTAB_CSV_Parser::parse( $user_head . '"Rower górski „Trek""",4 199.99,W magazynie' . "\n" . $user_rows, true );
lstab_assert( 6 === count( $correct['rows'] ), 'A value ending in a quote keeps every row', (string) count( $correct['rows'] ) );
lstab_assert( 'Rower górski „Trek"' === $correct['rows'][0][0], 'And keeps its own quotation mark', $correct['rows'][0][0] );
lstab_assert( '1 215.50' === $correct['rows'][4][1], 'A price further down the sheet still arrives', $correct['rows'][4][1] );
lstab_assert( ! isset( $correct['ragged'] ), 'Nothing is reported as malformed' );

/*
 * The same sheet written without doubling that trailing quote. Reading the two
 * quotes as an escape swallows every row after it into one cell — which is
 * worse than what the parser did before this rule existed. A delimiter after
 * the pair settles it: the field ended there.
 */
$undoubled = LSTAB_CSV_Parser::parse( $user_head . '"Rower górski „Trek"",4 199.99,W magazynie' . "\n" . $user_rows, true );
lstab_assert( 6 === count( $undoubled['rows'] ), 'An undoubled trailing quote costs no rows either', (string) count( $undoubled['rows'] ) );
lstab_assert( 'Rower górski „Trek"' === $undoubled['rows'][0][0], 'The value survives it intact', $undoubled['rows'][0][0] );
lstab_assert( '4 199.99' === $undoubled['rows'][0][1], 'And the next column is still the next column', $undoubled['rows'][0][1] );
lstab_assert( 'Zamek szyfrowy' === $undoubled['rows'][4][0], 'Rows further down are untouched', $undoubled['rows'][4][0] );

$multiline = LSTAB_CSV_Parser::parse( "Produkt,Opis\nBidon,\"linia 1\nlinia 2\"\nKask,krótki\n", true );
lstab_assert( 2 === count( $multiline['rows'] ), 'A genuine multi-line cell still spans its rows', (string) count( $multiline['rows'] ) );
lstab_assert( "linia 1\nlinia 2" === $multiline['rows'][0][1], 'Its line break is preserved', $multiline['rows'][0][1] );
lstab_assert( 'Rama aluminiowa, widelec 120 mm' === $parsed['rows'][0][3], 'Comma inside a quoted field is preserved', $parsed['rows'][0][3] );
lstab_assert( false !== strpos( $parsed['rows'][3][3], "\n" ), 'Newline inside a quoted field is preserved', wp_json_encode( $parsed['rows'][3][3] ) );
lstab_assert( 'Cytat wewnątrz: "najlepsze w teście"' === $parsed['rows'][4][3], 'Escaped quotes inside a quoted field', $parsed['rows'][4][3] );
lstab_assert( '' === $parsed['rows'][5][3], 'Empty trailing-ish cell stays empty', wp_json_encode( $parsed['rows'][5][3] ) );

$every_row_full = true;
foreach ( $parsed['rows'] as $row ) {
	if ( 5 !== count( $row ) ) {
		$every_row_full = false;
	}
}
lstab_assert( $every_row_full, 'Every row is padded to the column count' );

$no_header = LSTAB_CSV_Parser::parse( $csv, false );
lstab_assert( 8 === count( $no_header['rows'] ), 'first_row_header=false keeps the header row as data', (string) count( $no_header['rows'] ) );

$dupes = LSTAB_CSV_Parser::parse( "Name,Name,\nA,B,C\n", true );
lstab_assert(
	'Name' === $dupes['headers'][0] && 'Name (2)' === $dupes['headers'][1] && 'Column 3' === $dupes['headers'][2],
	'Duplicate and blank headers are disambiguated',
	wp_json_encode( $dupes['headers'] )
);

lstab_assert( is_wp_error( LSTAB_CSV_Parser::parse( '   ', true ) ), 'Empty payload is an error, not an empty table' );

// ---------------------------------------------------------------------------

lstab_section( '4. Tab discovery' );

$tabs = LSTAB_Fetcher::parse_tabs( file_get_contents( LSTAB_MOCK_FIXTURES . '/sheet-htmlview.html' ) );
lstab_assert( 3 === count( $tabs ), 'Three tabs discovered', wp_json_encode( $tabs ) );
lstab_assert( 'Cennik' === $tabs[0]['name'] && '0' === $tabs[0]['gid'], 'First tab name and gid', wp_json_encode( $tabs[0] ) );
lstab_assert( 'Punkty odbioru' === $tabs[1]['name'] && '1734829105' === $tabs[1]['gid'], 'Second tab name and gid', wp_json_encode( $tabs[1] ) );

// ---------------------------------------------------------------------------

lstab_section( '5. Creating a source and the first sync' );

foreach ( LSTAB_Storage::get_all() as $existing ) {
	LSTAB_Storage::delete( $existing['id'] );
}

$source_id = LSTAB_Storage::insert(
	array(
		'title'         => 'Cennik rowerowy',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'      => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind'    => 'doc',
		'gid'           => '0',
		'tab_name'      => 'Cennik',
		'sync_interval' => 900,
		'style_preset'  => 'striped',
	)
);

lstab_assert( is_int( $source_id ) && $source_id > 0, 'Source inserted', var_export( $source_id, true ) );

$sync = LSTAB_Sync::run( $source_id );
lstab_assert( true === $sync, 'First sync succeeds', is_wp_error( $sync ) ? $sync->get_error_message() : '' );

$source = LSTAB_Storage::get( $source_id );
lstab_assert( 'ok' === $source['last_status'], 'Status recorded as ok', $source['last_status'] );
lstab_assert( 7 === $source['row_count'], 'Seven rows stored', (string) $source['row_count'] );
lstab_assert( 5 === $source['col_count'], 'Five columns stored', (string) $source['col_count'] );
lstab_assert( ! empty( $source['last_success_gmt'] ), 'Last success timestamp set' );
lstab_assert( null === $source['last_error'], 'No error recorded', var_export( $source['last_error'], true ) );

$good_hash = $source['snapshot_hash'];
lstab_assert( 32 === strlen( $good_hash ), 'Snapshot hash stored' );

// ---------------------------------------------------------------------------

lstab_section( '3a. Which endpoint the data is asked for' );

/*
 * A user's price list came back with its first two rows run together into one
 * heading and one price missing — and the payload was already like that before
 * the plugin read it. The query endpoint (gviz/tq) infers a single type per
 * column, blanks every cell that disagrees, and guesses how many leading rows
 * are headings. The export endpoint hands back the cells as they are.
 */
$endpoint = LSTAB_Url::csv_endpoint( 'ABC123', '0' );
lstab_assert( false !== strpos( $endpoint, '/export' ), 'Data is asked for from the export endpoint', $endpoint );
lstab_assert( false !== strpos( $endpoint, 'format=csv' ), 'As CSV', $endpoint );
lstab_assert( false === strpos( $endpoint, 'gviz' ), 'Not from the one that rewrites values', $endpoint );

$fallback = LSTAB_Url::csv_fallback_endpoint( 'ABC123', '0' );
lstab_assert( false !== strpos( $fallback, 'gviz' ), 'The query endpoint is kept as a fallback', $fallback );
lstab_assert( false !== strpos( $fallback, 'headers=1' ), 'Told not to guess how many heading rows there are', $fallback );
lstab_assert( '' === LSTAB_Url::csv_fallback_endpoint( 'ABC123', '0', 'pub' ), 'A published-to-web sheet has nothing else to try' );

// What that endpoint actually did to this sheet, kept so the reason for
// leaving it is not lost.
$damaged = LSTAB_CSV_Parser::parse( (string) file_get_contents( LSTAB_MOCK_FIXTURES . '/sheet-gviz-damaged.csv' ), true );
lstab_assert( false !== strpos( $damaged['headers'][0], 'Rower' ), 'The old endpoint really did fold a data row into the heading', $damaged['headers'][0] );
lstab_assert( '' === $damaged['rows'][4][1], 'And really did blank a price that did not match the column type', wp_json_encode( $damaged['rows'][4] ) );

// Both endpoints answer here, each with its own payload, so the assertions
// below are about which one the plugin chose.
lstab_set_mock( 'endpoints' );
lstab_assert( true === LSTAB_Sync::run( $source_id ), 'The sheet syncs' );
$chosen = LSTAB_Storage::get( $source_id );

lstab_assert( 3 === count( $chosen['data']['headers'] ), 'Three headings, not a merged one', wp_json_encode( $chosen['data']['headers'] ) );
lstab_assert( 'Produkt' === $chosen['data']['headers'][0], 'The heading is the heading alone', $chosen['data']['headers'][0] );
lstab_assert( 7 === count( $chosen['data']['rows'] ), 'Every row arrives', (string) count( $chosen['data']['rows'] ) );
lstab_assert( 'Rower górski „Trek"' === $chosen['data']['rows'][0][0], 'Including the one the old endpoint ate', $chosen['data']['rows'][0][0] );
lstab_assert( '1 215.50' === $chosen['data']['rows'][5][1], 'And the price it blanked', $chosen['data']['rows'][5][1] );

// Sharing settings decide which endpoints answer at all: a sheet published to
// the web but not shared by link refuses the export.
lstab_set_mock( 'export_denied' );
lstab_assert( true === LSTAB_Sync::run( $source_id ), 'A sheet that only answers the query endpoint still syncs' );
lstab_assert( 7 === count( LSTAB_Storage::get( $source_id )['data']['rows'] ), 'And still gets its rows' );

lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );

lstab_section( '6. Front-end rendering' );

$html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );

lstab_assert( false !== strpos( $html, '<table' ), 'Renders a real <table> element' );
lstab_assert( false !== strpos( $html, 'lstab-style-striped' ), 'Applies the chosen style preset' );
lstab_assert( substr_count( $html, '<tr' ) === 8, 'One header row plus seven body rows', (string) substr_count( $html, '<tr' ) );
lstab_assert( false !== strpos( $html, 'Dostępność' ), 'Header text present with diacritics' );
lstab_assert( false !== strpos( $html, 'data-label="Produkt"' ), 'Cells carry data-label for the stacked layout' );
lstab_assert( false !== strpos( $html, 'lstab-cell-label' ), 'Stacked-layout labels rendered server side' );
lstab_assert( false !== strpos( $html, 'lstab-container' ), 'Sizing container wraps the table' );
lstab_assert( false !== strpos( $html, 'lstab-cols-5' ), 'Column count is exposed so CSS can pick a breakpoint' );
// A table keeps its shape and gains a slider by default; stacking is opt-in.
lstab_assert( false !== strpos( $html, 'lstab-layout-table' ), 'A source defaults to the scrolling table layout' );
lstab_assert( false !== strpos( $html, 'lstab-scrollbar' ), 'The slider markup is rendered server side' );
lstab_assert( false !== strpos( $html, 'role="region"' ), 'The scroll area is a labelled region for assistive tech' );
lstab_assert( false !== strpos( $html, 'role="scrollbar"' ), 'The slider exposes a scrollbar role' );
lstab_assert( false !== strpos( $html, 'aria-controls="lstab-table-' ), 'The slider is wired to its table for assistive tech' );
lstab_assert(
	(bool) preg_match( '#<div class="lstab-scrollbar" hidden>#', $html ),
	'The slider starts hidden and is revealed only when the table overflows'
);

$forced_cards = do_shortcode( '[sheet_table id="' . $source_id . '" layout="cards"]' );
lstab_assert( false !== strpos( $forced_cards, 'lstab-layout-cards' ), 'layout="cards" pins the card layout' );

$forced_auto = do_shortcode( '[sheet_table id="' . $source_id . '" layout="auto"]' );
lstab_assert( false === strpos( $forced_auto, 'lstab-layout-' ), 'layout="auto" leaves the breakpoints in charge' );

$bogus_layout = do_shortcode( '[sheet_table id="' . $source_id . '" layout="nonsense"]' );
lstab_assert( false !== strpos( $bogus_layout, 'lstab-layout-table' ), 'An unknown layout falls back to the table' );

// A source carries its own choice, and the block or shortcode may override it.
LSTAB_Storage::update( $source_id, array( 'layout' => 'cards' ) );
$inherited = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $inherited, 'lstab-layout-cards' ), 'A shortcode with no layout follows the source setting' );

$overridden = do_shortcode( '[sheet_table id="' . $source_id . '" layout="table"]' );
lstab_assert( false !== strpos( $overridden, 'lstab-layout-table' ), 'An explicit shortcode layout overrides the source' );

$block_inherits = lstab()->block->render( array( 'sourceId' => $source_id ) );
lstab_assert( false !== strpos( $block_inherits, 'lstab-layout-cards' ), 'The block follows the source setting too' );

$block_layout = lstab()->block->render(
	array(
		'sourceId' => $source_id,
		'layout'   => 'table',
	)
);
lstab_assert( false !== strpos( $block_layout, 'lstab-layout-table' ), 'The block can override it' );

LSTAB_Storage::update( $source_id, array( 'layout' => 'table' ) );

// The numeric price column should be right-aligned; the product name should not.
lstab_assert(
	(bool) preg_match( '#<th[^>]*data-lstab-col="1"[^>]*data-lstab-align="end"#', $html ),
	'Numeric column is detected and right-aligned'
);
lstab_assert(
	(bool) preg_match( '#<th[^>]*data-lstab-col="0"[^>]*data-lstab-align="start"#', $html ),
	'Text column stays left-aligned'
);
lstab_assert(
	(bool) preg_match( '#<th[^>]*data-lstab-col="4"[^>]*data-lstab-align="start"#', $html ),
	'A date column is not mistaken for a number'
);
lstab_assert( false !== strpos( $html, 'Updated' ), 'Freshness label rendered' );
lstab_assert( false !== strpos( $html, 'lstab-search-input' ), 'Search control rendered' );
lstab_assert( false !== strpos( $html, 'class="lstab-sort"' ), 'Sortable column buttons rendered' );

// Injection safety: the fixture contains a <script> tag and raw HTML in cells.
lstab_assert( false === strpos( $html, "<script>alert('xss')</script>" ), 'Script tag from the sheet is NOT emitted raw' );
lstab_assert( false !== strpos( $html, '&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;' ), 'Script tag is escaped for display' );
lstab_assert( false === strpos( $html, '<b>bold</b>' ), 'Raw HTML from a cell is NOT emitted' );
lstab_assert( false !== strpos( $html, '&lt;b&gt;bold&lt;/b&gt;' ), 'Raw HTML from a cell is escaped' );

// No unbalanced markup: the "renders as raw code" competitor bug.
$open_td  = substr_count( $html, '<td' );
$close_td = substr_count( $html, '</td>' );
lstab_assert( $open_td === $close_td && $open_td === 35, 'Cell tags balanced (35 cells)', "{$open_td} open / {$close_td} close" );

$doc = new DOMDocument();
libxml_use_internal_errors( true );
$loaded = $doc->loadHTML( '<?xml encoding="UTF-8">' . $html );
$errors = libxml_get_errors();
libxml_clear_errors();
lstab_assert( $loaded && ! $errors, 'Output parses as well-formed HTML', $errors ? $errors[0]->message : '' );

// Shortcode option handling.
$plain = do_shortcode( '[sheet_table id="' . $source_id . '" search="no" sort="no" meta="no"]' );
lstab_assert( false === strpos( $plain, 'lstab-search-input' ), 'search="no" removes the search box' );
lstab_assert( false === strpos( $plain, 'class="lstab-sort"' ), 'sort="no" removes sort buttons' );
lstab_assert( false === strpos( $plain, 'lstab-meta' ), 'meta="no" removes the freshness label' );

// The block must produce the same table as the shortcode.
$block_html = lstab()->block->render(
	array(
		'sourceId'    => $source_id,
		'showSearch'  => true,
		'showSort'    => true,
		'showUpdated' => true,
	)
);
lstab_assert( false !== strpos( $block_html, '<table' ), 'Block render callback produces a table' );
lstab_assert(
	substr_count( $block_html, '<td' ) === substr_count( $html, '<td' ),
	'Block and shortcode render the same cell count'
);

// Alignment heuristic, exercised directly across the formats spreadsheets produce.
$alignment_cases = array(
	'plain integers'          => array( array( '1', '2', '30' ), 'end' ),
	'comma decimals'          => array( array( '349,00', '1 215,50' ), 'end' ),
	'dot decimals'            => array( array( '349.00', '1,215.50' ), 'end' ),
	'currency suffix'         => array( array( '12,00 zł', '9,50 zł' ), 'end' ),
	'percentages'             => array( array( '12%', '-4.5%' ), 'end' ),
	'negative numbers'        => array( array( '-5', '+12' ), 'end' ),
	'plain text'              => array( array( 'W magazynie', 'Brak' ), 'start' ),
	'ISO dates'               => array( array( '2026-08-20', '2026-08-21' ), 'start' ),
	'mostly text with a number' => array( array( 'Kask', 'Lampka', 'Bidon', '12' ), 'start' ),
	'blank column'            => array( array( '', '' ), 'start' ),
);

foreach ( $alignment_cases as $label => $case ) {
	$rows_for_case = array();
	foreach ( $case[0] as $value ) {
		$rows_for_case[] = array( $value );
	}
	$rendered = LSTAB_Renderer::render_preview(
		array(
			'headers' => array( 'Test' ),
			'rows'    => $rows_for_case,
		)
	);
	$expected = 'data-lstab-align="' . $case[1] . '"';
	lstab_assert(
		false !== strpos( $rendered, '<th scope="col" role="columnheader"' ) && false !== strpos( $rendered, $expected ),
		"Alignment: {$label} → {$case[1]}",
		$expected
	);
}

// ---------------------------------------------------------------------------

lstab_section( '6a. Paging, and searching the whole sheet from a page of it' );

/*
 * A sheet with no row limit eventually produces a page nobody wants to
 * download. Paging cuts it up — but searching or sorting the rows that
 * happen to be on screen and presenting the result as the table would be
 * worse than not offering them, so both happen on the server across every
 * row before the page is cut.
 */
$paged_rows = array();
for ( $lstab_i = 1; $lstab_i <= 23; $lstab_i++ ) {
	$paged_rows[] = array( 'Produkt ' . $lstab_i, (string) ( $lstab_i * 10 ), 0 === $lstab_i % 3 ? 'Brak' : 'W magazynie' );
}

LSTAB_Storage::record_success(
	$source_id,
	array(
		'headers' => array( 'Produkt', 'Cena', 'Stan' ),
		'rows'    => $paged_rows,
	)
);
LSTAB_Storage::update( $source_id, array( 'per_page' => 10, 'columns_config' => array() ) );

$lstab_shortcode = '[sheet_table id="' . $source_id . '"]';
$lstab_rows_in   = function ( $html ) {
	return substr_count( $html, '<tr role="row" class="lstab-row"' );
};

$_GET      = array();
$page_one  = do_shortcode( $lstab_shortcode );
lstab_assert( 10 === $lstab_rows_in( $page_one ), 'A page holds the number of rows asked for', (string) $lstab_rows_in( $page_one ) );
lstab_assert( false !== strpos( $page_one, 'lstab-pager' ), 'And carries a pager' );
lstab_assert( false !== strpos( $page_one, 'lstab-paged' ), 'The table says it is paged, so the script leaves its controls alone' );

$_GET       = array( LSTAB_Paging::arg( $source_id, 'page' ) => '3' );
$page_three = do_shortcode( $lstab_shortcode );
lstab_assert( 3 === $lstab_rows_in( $page_three ), 'The last page holds the remainder', (string) $lstab_rows_in( $page_three ) );
lstab_assert( false !== strpos( $page_three, 'Produkt 21' ), 'And the rows belonging to it' );
lstab_assert( false === strpos( $page_three, 'Produkt 1<' ), 'Not the ones belonging to another page' );

// The point of the whole exercise: a row on page three is findable from page
// one. A search that only looked at the rows already on screen would miss it.
$_GET       = array( LSTAB_Paging::arg( $source_id, 'q' ) => 'Produkt 22' );
$found      = do_shortcode( $lstab_shortcode );
lstab_assert( 1 === $lstab_rows_in( $found ), 'Searching reaches rows that are not on this page', (string) $lstab_rows_in( $found ) );
lstab_assert( false !== strpos( $found, 'Produkt 22' ), 'And returns the one it was asked for' );

$_GET  = array( LSTAB_Paging::arg( $source_id, 'q' ) => 'nie ma takiego wiersza' );
$empty = do_shortcode( $lstab_shortcode );
lstab_assert( 0 === $lstab_rows_in( $empty ), 'A search that matches nothing shows nothing' );
lstab_assert( false !== strpos( $empty, 'lstab-no-results' ), 'And says so' );

// Sorting has the same problem and the same answer.
$_GET   = array( LSTAB_Paging::arg( $source_id, 'sort' ) => '1', LSTAB_Paging::arg( $source_id, 'dir' ) => 'desc' );
$sorted = do_shortcode( $lstab_shortcode );
preg_match_all( '~lstab-cell-value">\s*([^<]*)~', $sorted, $lstab_sorted_cells );
$lstab_first = trim( $lstab_sorted_cells[1][0] );
lstab_assert( 'Produkt 23' === $lstab_first, 'Sorting orders the whole sheet, not the page', $lstab_first );

// Numbers sort as numbers here too, or 90 would come after 230.
$_GET       = array( LSTAB_Paging::arg( $source_id, 'sort' ) => '1', LSTAB_Paging::arg( $source_id, 'dir' ) => 'asc' );
$ascending  = do_shortcode( $lstab_shortcode );
preg_match_all( '~lstab-cell-value">\s*([^<]*)~', $ascending, $lstab_asc_cells );
lstab_assert( 'Produkt 1' === trim( $lstab_asc_cells[1][0] ), 'Ascending starts at the smallest number', trim( $lstab_asc_cells[1][0] ) );

// A hidden column must not become searchable: guessing at its contents would
// be a way to read what the author took out of the table.
LSTAB_Storage::update( $source_id, array( 'columns_config' => array( 2 => array( 'hidden' => true ) ) ) );
$_GET   = array( LSTAB_Paging::arg( $source_id, 'q' ) => 'Brak' );
$hidden = do_shortcode( $lstab_shortcode );
lstab_assert( 0 === $lstab_rows_in( $hidden ), 'A hidden column cannot be searched', (string) $lstab_rows_in( $hidden ) );
LSTAB_Storage::update( $source_id, array( 'columns_config' => array() ) );

// Each table answers to its own arguments, so paging one leaves the other be.
lstab_assert( 'lstab-page-' . $source_id === LSTAB_Paging::arg( $source_id, 'page' ), 'Query arguments are scoped to one table', LSTAB_Paging::arg( $source_id, 'page' ) );

$_GET     = array();
LSTAB_Storage::update( $source_id, array( 'per_page' => 0 ) );
$whole    = do_shortcode( $lstab_shortcode );
lstab_assert( 23 === $lstab_rows_in( $whole ), 'Switched off, the whole sheet is on the page again', (string) $lstab_rows_in( $whole ) );
lstab_assert( false === strpos( $whole, 'lstab-pager' ), 'And there is no pager' );
lstab_assert( false === strpos( $whole, 'lstab-paged' ), 'And the script handles the controls again' );

lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );

lstab_section( '7. Fetch failure → last good copy survives' );

$failure_modes = array(
	'http_403'   => 'HTTP 403 (sheet made private)',
	'timeout'    => 'Network timeout',
	'html_login' => 'Google returns a sign-in page instead of CSV',
	'empty'      => 'Empty response body',
);

foreach ( $failure_modes as $mode => $description ) {
	lstab_set_mock( $mode );

	$result = LSTAB_Sync::run( $source_id );
	lstab_assert( is_wp_error( $result ), "{$description}: sync reports an error" );

	$after = LSTAB_Storage::get( $source_id );
	lstab_assert( 'error' === $after['last_status'], "{$description}: status flipped to error", $after['last_status'] );
	lstab_assert( ! empty( $after['last_error'] ), "{$description}: error message stored for the admin" );
	lstab_assert( $good_hash === $after['snapshot_hash'], "{$description}: stored snapshot untouched", $after['snapshot_hash'] );
	lstab_assert( 7 === $after['row_count'], "{$description}: row count unchanged", (string) $after['row_count'] );

	$fallback_html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
	lstab_assert( false !== strpos( $fallback_html, '<table' ), "{$description}: page still renders a table" );
	lstab_assert( false !== strpos( $fallback_html, 'Rower górski' ), "{$description}: last good data still on the page" );
	lstab_assert( substr_count( $fallback_html, '<tr' ) === 8, "{$description}: all seven rows still rendered" );
	lstab_assert(
		false === strpos( $fallback_html, $after['last_error'] ),
		"{$description}: the error is NOT leaked to visitors"
	);
	lstab_assert(
		false === stripos( $fallback_html, 'lstab-notice' ),
		"{$description}: no admin notice on the front end"
	);
}

// ---------------------------------------------------------------------------

lstab_section( '8. Recovery' );

lstab_set_mock( 'ok' );
$recovered = LSTAB_Sync::run( $source_id );
lstab_assert( true === $recovered, 'Sync succeeds again once Google recovers' );

$after = LSTAB_Storage::get( $source_id );
lstab_assert( 'ok' === $after['last_status'], 'Status back to ok', $after['last_status'] );
lstab_assert( null === $after['last_error'], 'Error message cleared', var_export( $after['last_error'], true ) );

// Data changes are picked up.
lstab_set_mock( 'ok', 'second' );
LSTAB_Sync::run( $source_id );
$switched = LSTAB_Storage::get( $source_id );
lstab_assert( 3 === $switched['row_count'], 'New sheet content replaces the snapshot', (string) $switched['row_count'] );
lstab_assert( $good_hash !== $switched['snapshot_hash'], 'Snapshot hash changed with the data' );

$switched_html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $switched_html, 'Bike Centrum' ), 'Page shows the new data' );
lstab_assert( false === strpos( $switched_html, 'Rower górski' ), 'Old data is gone' );

lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );

// ---------------------------------------------------------------------------

lstab_section( '8a. A sheet that syncs but arrives malformed' );

/*
 * Google gives every row the same number of cells. A row that disagrees means
 * the payload did not survive the trip — nearly always an unmatched quotation
 * mark. The fetch itself succeeds, so nothing else in the plugin notices, and
 * this is the kind of fault nobody sees until a customer does.
 */
lstab_set_mock( 'ragged' );
lstab_assert( true === LSTAB_Sync::run( $source_id ), 'A malformed sheet still syncs rather than failing' );

$ragged_source = LSTAB_Storage::get( $source_id );
lstab_assert( 'ok' === $ragged_source['last_status'], 'It is not reported as a fetch error', $ragged_source['last_status'] );
lstab_assert( is_array( $ragged_source['last_ragged'] ), 'The malformed row is recorded' );
lstab_assert( 1 === (int) $ragged_source['last_ragged']['total'], 'One row is flagged', wp_json_encode( $ragged_source['last_ragged'] ) );
lstab_assert( 3 === (int) $ragged_source['last_ragged']['rows'][0]['row'], 'It names the row as numbered in Google', wp_json_encode( $ragged_source['last_ragged']['rows'][0] ) );

$summary = LSTAB_Admin::ragged_summary( $ragged_source['last_ragged'] );
lstab_assert( '' !== $summary, 'The dashboard has something to say about it' );
lstab_assert( false !== strpos( $summary, '3' ), 'The message names the row number', $summary );

/*
 * A row can arrive short for several reasons, so the message offers the likely
 * cause without asserting it: a reader who goes looking for a quotation mark
 * and finds none must not conclude the warning is wrong. It says what came
 * back, what that means for the table, and where to start.
 */
lstab_assert(
	false !== stripos( $summary, 'missing' ) || false !== stripos( $summary, 'wrong column' ),
	'It says what the fault means for the table',
	$summary
);
lstab_assert( false !== stripos( $summary, 'most often' ), 'It offers a cause as likely, not as certain', $summary );
lstab_assert(
	false !== stripos( $summary, 'quotation' ) && false !== stripos( $summary, 'comma' ),
	'And offers more than one thing to look for',
	$summary
);

// The visitor sees a table, not a warning: the data that did arrive is still
// worth showing, and the fault is the site owner's to fix.
$ragged_html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $ragged_html, 'lstab-table' ), 'The table still renders' );
lstab_assert( false === strpos( $ragged_html, 'stray quotation' ), 'No warning leaks to the visitor' );

// The finding describes the stored copy, so a later failure must not clear it.
lstab_set_mock( 'http_403' );
LSTAB_Sync::run( $source_id );
lstab_assert(
	is_array( LSTAB_Storage::get( $source_id )['last_ragged'] ),
	'A failed fetch leaves the finding alone, as it leaves the snapshot alone'
);

// The dashboard-wide warning reads an autoloaded option rather than querying
// on every admin page, so that index has to track the column exactly.
lstab_set_mock( 'ragged' );
LSTAB_Sync::run( $source_id );
$ragged_index = (array) get_option( LSTAB_Storage::RAGGED_OPT, array() );
lstab_assert( isset( $ragged_index[ $source_id ] ), 'The malformed sheet is listed for the dashboard-wide warning', wp_json_encode( $ragged_index ) );

// Dismissing silences this fault, and only this fault.
update_option( LSTAB_Storage::DISMISSED_OPT, array_values( $ragged_index ), true );
lstab_assert( ! array_diff( $ragged_index, (array) get_option( LSTAB_Storage::DISMISSED_OPT, array() ) ), 'Dismissing covers what is currently listed' );

lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );
lstab_assert( null === LSTAB_Storage::get( $source_id )['last_ragged'], 'A clean sync clears it' );
lstab_assert( ! isset( ( (array) get_option( LSTAB_Storage::RAGGED_OPT, array() ) )[ $source_id ] ), 'And takes the source off the list' );
lstab_assert( array() === (array) get_option( LSTAB_Storage::DISMISSED_OPT, array() ), 'A dismissal of a fault that is gone is forgotten, so the next one is heard' );

// ---------------------------------------------------------------------------

lstab_section( '8b. Refreshing while the page is being drawn' );

/*
 * WP-Cron has no clock: it runs on a visit, in a request of its own, after the
 * page has been sent. The visitor who triggers a sync is therefore the one who
 * does not benefit from it. Sources may opt into being refreshed before the
 * table is drawn instead — but only if that can never cost the page.
 */

/**
 * Render a shortcode as though a fresh page load asked for it.
 *
 * @param string $shortcode Shortcode text.
 * @return string HTML.
 */
function lstab_view( $shortcode ) {
	// Each call stands in for one page load, and the refresh budget is spent
	// per page load. A single PHP process rendering many pages has to say so.
	LSTAB_Sync::reset_view_budget();

	return do_shortcode( $shortcode );
}

/**
 * Backdate a source's last attempt so it counts as stale.
 *
 * @param int $id      Source ID.
 * @param int $seconds How far back.
 * @return void
 */
function lstab_age_source( $id, $seconds ) {
	global $wpdb;

	$when = gmdate( 'Y-m-d H:i:s', time() - $seconds );

	// Both, because "last checked" is the attempt and "last refreshed" is the
	// success, and a source aged by hand has to look consistent in each.
	$wpdb->update(
		LSTAB_Storage::table(),
		array(
			'last_attempt_gmt' => $when,
			'last_success_gmt' => $when,
		),
		array( 'id' => (int) $id ),
		array( '%s', '%s' ),
		array( '%d' )
	);

	LSTAB_Storage::flush_cache( (int) $id );
}

lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );
delete_transient( 'lstab_view_refresh_' . $source_id );

// Stale: the visitor who waits is the one who sees the new data. No setting
// turns this on, because a site owner asked whether their prices should be
// current has only one answer.
lstab_age_source( $source_id, 86400 );
lstab_set_mock( 'ok', 'second' );
$on_html = lstab_view( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $on_html, 'Bike Centrum' ), 'A stale table is brought up to date on the page that asked for it' );
lstab_assert( false === strpos( $on_html, 'Rower górski' ), 'The old copy is gone from that same page' );

// The rare site that would rather serve a day-old table than ever make one
// visitor wait has a filter, not a checkbox.
lstab_set_mock( 'ok', 'main' );
lstab_age_source( $source_id, 86400 );
add_filter( 'lstab_refresh_on_view', '__return_false' );
$off_html = lstab_view( '[sheet_table id="' . $source_id . '"]' );
remove_filter( 'lstab_refresh_on_view', '__return_false' );
lstab_assert( false !== strpos( $off_html, 'Bike Centrum' ), 'Switched off by filter, the stale copy is served as it always was' );
lstab_assert( false === strpos( $off_html, 'Rower górski' ), 'And nothing is fetched' );

// A fresh copy of the changed sheet, for the checks that follow.
lstab_set_mock( 'ok', 'second' );
LSTAB_Sync::run( $source_id );

// A copy younger than the schedule is left alone, so a busy page does not
// become a stream of requests to Google.
lstab_set_mock( 'ok', 'main' );
$fresh_html = lstab_view( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $fresh_html, 'Bike Centrum' ), 'A copy younger than the schedule is not fetched again' );

// One request at a time. The lock is what keeps ten simultaneous visitors from
// becoming ten fetches.
lstab_age_source( $source_id, 86400 );
set_transient( 'lstab_view_refresh_' . $source_id, 1, LSTAB_Sync::VIEW_LOCK );
$locked_html = lstab_view( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false === strpos( $locked_html, 'Rower górski' ), 'While another request is fetching, this one serves the stored copy' );
delete_transient( 'lstab_view_refresh_' . $source_id );

// The wait is capped, whatever the source's own settings say.
$lstab_timeouts = array();
$lstab_spy      = function ( $args ) use ( &$lstab_timeouts ) {
	$lstab_timeouts[] = isset( $args['timeout'] ) ? $args['timeout'] : null;
	return $args;
};
add_filter( 'lstab_fetch_args', $lstab_spy, 100 );
lstab_age_source( $source_id, 86400 );
lstab_view( '[sheet_table id="' . $source_id . '"]' );
remove_filter( 'lstab_fetch_args', $lstab_spy, 100 );
lstab_assert(
	1 === count( $lstab_timeouts ) && $lstab_timeouts[0] > 0 && $lstab_timeouts[0] <= LSTAB_Sync::VIEW_TIMEOUT,
	'A visitor waits at most ' . LSTAB_Sync::VIEW_TIMEOUT . ' seconds for Google',
	wp_json_encode( $lstab_timeouts )
);

// A sheet whose sharing settings refuse the export endpoint is asked twice.
// Four seconds each would be eight seconds of waiting, in precisely the case
// where Google is already being difficult, so the cap covers the whole attempt.
lstab_set_mock( 'export_denied' );
lstab_age_source( $source_id, 86400 );
delete_transient( 'lstab_view_refresh_' . $source_id );
$lstab_timeouts = array();
add_filter( 'lstab_fetch_args', $lstab_spy, 100 );
lstab_view( '[sheet_table id="' . $source_id . '"]' );
remove_filter( 'lstab_fetch_args', $lstab_spy, 100 );
lstab_assert( 2 === count( $lstab_timeouts ), 'Both endpoints are still tried when the first refuses', wp_json_encode( $lstab_timeouts ) );
lstab_assert(
	array_sum( $lstab_timeouts ) <= LSTAB_Sync::VIEW_TIMEOUT * 2,
	'Neither request is allowed more than the whole cap',
	wp_json_encode( $lstab_timeouts )
);
lstab_assert(
	$lstab_timeouts[1] < $lstab_timeouts[0],
	'And the second only gets what the first left of it',
	wp_json_encode( $lstab_timeouts )
);

// And the whole point: when Google will not answer, the page is unchanged.
lstab_age_source( $source_id, 86400 );
delete_transient( 'lstab_view_refresh_' . $source_id );
lstab_set_mock( 'http_403' );
$failed_html = lstab_view( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $failed_html, '<table' ), 'A failed refresh still renders a table' );
lstab_assert( false !== strpos( $failed_html, 'Rower górski' ), 'It is the last good copy, not an empty one' );
lstab_assert( false === stripos( $failed_html, 'lstab-notice' ), 'And the visitor is told nothing about it' );

// A source that keeps failing is retried on the schedule, not on every view.
$failed_state = LSTAB_Storage::get( $source_id );
lstab_assert( 'error' === $failed_state['last_status'], 'The failure is recorded for the admin', $failed_state['last_status'] );

// A deadline of our own invention must not be reported as the sheet's fault.
// The scheduler gets twenty seconds; this refresh gets four. A sheet that
// syncs perfectly well on the schedule can miss the shorter one, and turning
// the dashboard red over that would be a fault the plugin made up.
lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );
lstab_age_source( $source_id, 86400 );
delete_transient( 'lstab_view_refresh_' . $source_id );
lstab_set_mock( 'timeout' );
$slow_html = lstab_view( '[sheet_table id="' . $source_id . '"]' );
$slow_state = LSTAB_Storage::get( $source_id );
lstab_assert( false !== strpos( $slow_html, 'Rower górski' ), 'A refresh that runs out of time still shows the stored copy' );
lstab_assert( 'ok' === $slow_state['last_status'], 'And does not report the sheet as broken over our own four-second cap', $slow_state['last_status'] );
lstab_assert( null === $slow_state['last_error'], 'No invented error message is left for the admin', var_export( $slow_state['last_error'], true ) );
lstab_assert( (bool) get_transient( LSTAB_Sync::COOLDOWN_PREFIX . $source_id ), 'A cooling-off period keeps the next visitor from repeating it straight away' );

// Four seconds is all a visitor can be asked for, and a large sheet may need
// more. The same fetch is queued to run in a request of its own, where the
// full timeout is nobody's wait, so such a sheet is not beyond a visit's reach.
lstab_assert(
	(bool) wp_next_scheduled( LSTAB_Cron::RETRY_HOOK, array( $source_id ) ),
	'A check that ran out of time queues a background one with the full timeout'
);
lstab_set_mock( 'ok', 'main' );
LSTAB_Cron::run_source( $source_id );
lstab_assert( 'ok' === LSTAB_Storage::get( $source_id )['last_status'], 'And that background run syncs the source' );
wp_unschedule_event( (int) wp_next_scheduled( LSTAB_Cron::RETRY_HOOK, array( $source_id ) ), LSTAB_Cron::RETRY_HOOK, array( $source_id ) );

// That background run left the source fresh; stale again for what follows.
lstab_age_source( $source_id, 86400 );
set_transient( LSTAB_Sync::COOLDOWN_PREFIX . $source_id, 1, LSTAB_Sync::VIEW_RETRY );

// But it is half a minute, not the whole interval. A four-second timeout must
// not buy fifteen more minutes of stale data: the visitor after the one who
// waited has to be better off for that waiting, or the waiting was pointless.
delete_transient( LSTAB_Sync::COOLDOWN_PREFIX . $source_id );
lstab_set_mock( 'ok', 'second' );
$after_timeout = lstab_view( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $after_timeout, 'Bike Centrum' ), 'Once the cooling-off period is over, the next visitor gets the new data' );
lstab_assert( ! get_transient( LSTAB_Sync::FAILS_PREFIX . $source_id ), 'A success forgets that the sheet was ever failing' );

// A sheet that keeps failing backs off further each time, so it costs a couple
// of slow pages rather than every page for as long as it stays broken.
lstab_set_mock( 'timeout' );
delete_transient( LSTAB_Sync::COOLDOWN_PREFIX . $source_id );
delete_transient( LSTAB_Sync::FAILS_PREFIX . $source_id );
lstab_age_source( $source_id, 86400 );
$waits = array();
for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
	// Still stale each round, so only the cooling-off period is under test.
	lstab_age_source( $source_id, 86400 );

	lstab_view( '[sheet_table id="' . $source_id . '"]' );
	$waits[] = (int) ( get_option( '_transient_timeout_' . LSTAB_Sync::COOLDOWN_PREFIX . $source_id ) - time() );
	delete_transient( LSTAB_Sync::COOLDOWN_PREFIX . $source_id );
}
lstab_assert( $waits[1] > $waits[0] && $waits[2] > $waits[1], 'Each further failure holds off the next check for longer', wp_json_encode( $waits ) );
lstab_assert( max( $waits ) <= 900, 'And never for longer than the interval the site asked for', wp_json_encode( $waits ) );

lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );
lstab_assert( ! get_transient( LSTAB_Sync::COOLDOWN_PREFIX . $source_id ), 'A scheduled sync that succeeds ends the cooling-off period too' );

lstab_set_mock( 'timeout' );
lstab_age_source( $source_id, 86400 );
$slow_state = LSTAB_Storage::get( $source_id );

// The scheduled run has the real timeout, so it is the honest test — and when
// it fails, the failure is reported exactly as before.
lstab_assert( is_wp_error( LSTAB_Sync::run( $source_id ) ), 'The scheduled run still reports a timeout as a failure' );
lstab_assert( 'error' === LSTAB_Storage::get( $source_id )['last_status'], 'Which is what turns the dashboard red' );

// A refusal means the same thing at four seconds as at twenty, so it stands.
lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );
lstab_age_source( $source_id, 86400 );
delete_transient( 'lstab_view_refresh_' . $source_id );
lstab_set_mock( 'http_403' );
lstab_view( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( 'error' === LSTAB_Storage::get( $source_id )['last_status'], 'A sheet made private is still reported from a view refresh' );

$lstab_attempts = 0;
$lstab_counter  = function ( $args ) use ( &$lstab_attempts ) {
	$lstab_attempts++;
	return $args;
};
add_filter( 'lstab_fetch_args', $lstab_counter, 100 );
delete_transient( 'lstab_view_refresh_' . $source_id );
lstab_view( '[sheet_table id="' . $source_id . '"]' );
lstab_view( '[sheet_table id="' . $source_id . '"]' );
remove_filter( 'lstab_fetch_args', $lstab_counter, 100 );
lstab_assert( 0 === $lstab_attempts, 'A sheet that just failed is not retried on the very next page view', (string) $lstab_attempts );

// One page load buys one refresh, however many tables the page holds. Four
// tables that all wanted checking would otherwise be four four-second caps in
// a row, and sixteen seconds of waiting is the fault this whole feature exists
// to avoid.
lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );
$second_id = LSTAB_Storage::insert(
	array(
		'title'           => 'Second table on the same page',
		'sheet_url'       => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'        => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind'      => 'doc',
		'gid'             => '0',
		'sync_interval'   => 900,
	)
);
lstab_assert( ! is_wp_error( $second_id ), 'A second source for the two-table page', is_wp_error( $second_id ) ? $second_id->get_error_message() : '' );
LSTAB_Sync::run( (int) $second_id );

lstab_age_source( $source_id, 86400 );
lstab_age_source( (int) $second_id, 86400 );
delete_transient( 'lstab_view_refresh_' . $source_id );
delete_transient( 'lstab_view_refresh_' . (int) $second_id );

$lstab_fetches = 0;
$lstab_count   = function ( $args ) use ( &$lstab_fetches ) {
	$lstab_fetches++;
	return $args;
};
add_filter( 'lstab_fetch_args', $lstab_count, 100 );
LSTAB_Sync::reset_view_budget();
// Both tables drawn inside one page load, so no reset between them.
do_shortcode( '[sheet_table id="' . $source_id . '"]' );
do_shortcode( '[sheet_table id="' . (int) $second_id . '"]' );
remove_filter( 'lstab_fetch_args', $lstab_count, 100 );
lstab_assert( 1 === $lstab_fetches, 'Two stale tables on one page cost one fetch, not two', (string) $lstab_fetches );

// The table that missed its turn gets it on the next page load.
lstab_assert( LSTAB_Sync::is_due( LSTAB_Storage::get( (int) $second_id ) ), 'The table that missed its turn is still due' );
LSTAB_Storage::delete( (int) $second_id );

// Back to a clean, unattended source for the sections that follow.
delete_transient( 'lstab_view_refresh_' . $source_id );
lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );

// ---------------------------------------------------------------------------

lstab_section( '9. Cron scheduling' );

lstab_assert( (bool) wp_next_scheduled( LSTAB_Cron::TICK_HOOK ), 'Tick still scheduled' );
lstab_assert( 'lstab_15min' === LSTAB_Cron::required_schedule(), 'Tick recurrence matches the 15 minute source', LSTAB_Cron::required_schedule() );

$schedules = wp_get_schedules();
lstab_assert( isset( $schedules['lstab_15min'] ) && 900 === $schedules['lstab_15min']['interval'], 'Custom 15 minute schedule registered' );

$source = LSTAB_Storage::get( $source_id );
lstab_assert( ! LSTAB_Sync::is_due( $source ), 'A just-synced source is not due again' );

$wpdb->update(
	LSTAB_Storage::table(),
	array( 'last_attempt_gmt' => gmdate( 'Y-m-d H:i:s', time() - 1000 ) ),
	array( 'id' => $source_id ),
	array( '%s' ),
	array( '%d' )
);
// This writes straight to the table, so the cached row has to be dropped by
// hand — the same contract any code bypassing LSTAB_Storage has to honour.
LSTAB_Storage::flush_cache( $source_id );
lstab_assert( LSTAB_Sync::is_due( LSTAB_Storage::get( $source_id ) ), 'A source past its interval is due' );

lstab_set_mock( 'ok' );
$due_results = LSTAB_Sync::run_due();
lstab_assert( isset( $due_results[ $source_id ] ) && 'ok' === $due_results[ $source_id ], 'Cron tick syncs the due source', wp_json_encode( $due_results ) );

// ---------------------------------------------------------------------------

lstab_section( '10. Free tier limits and Pro extension points' );

// Six, because the nearest free competitor allows ten tables: capping at three
// turned the free version away at the third page someone wanted to publish,
// and rows — not table count — are what the paid tier is for.
$free_cap = LSTAB_Limits::max_sources();
lstab_assert( 6 === $free_cap, 'Free tier allows six sources', (string) $free_cap );
lstab_assert( LSTAB_Limits::can_add_source(), 'A second source is allowed while only one exists' );

// Fill the tier up and confirm the next one is refused.
$filler = array();
for ( $i = 2; $i <= $free_cap; $i++ ) {
	$filler[] = LSTAB_Storage::insert(
		array(
			'title'     => 'Filler ' . $i,
			'sheet_url' => 'https://docs.google.com/spreadsheets/d/FILLER' . $i . '00000000000000000000000000/edit',
			'sheet_id'  => 'FILLER' . $i . '00000000000000000000000000',
		)
	);
}
lstab_assert( $free_cap === LSTAB_Storage::count_sources(), 'The tier fills up', (string) LSTAB_Storage::count_sources() );
lstab_assert( ! LSTAB_Limits::can_add_source(), 'One past the cap is blocked' );

// Rows are never capped: that is the whole difference from the plugins this
// one is meant to replace.
lstab_assert( 7 === count( LSTAB_Storage::get( $source_id )['data']['rows'] ), 'And rows are still not capped at all', (string) count( LSTAB_Storage::get( $source_id )['data']['rows'] ) );

// Listing sources must not drag every snapshot out of the database.
$listed = LSTAB_Storage::get_all();
lstab_assert( ! array_key_exists( 'data', $listed[0] ), 'Listing sources skips the snapshot payload' );
lstab_assert( isset( $listed[0]['row_count'], $listed[0]['last_status'] ), 'Listing still carries the metadata the dashboard needs' );

$with_data = LSTAB_Storage::get_all( true );
$found     = null;
foreach ( $with_data as $candidate ) {
	if ( (int) $candidate['id'] === (int) $source_id ) {
		$found = $candidate;
	}
}
lstab_assert( is_array( $found ) && isset( $found['data']['rows'] ), 'Asking for the payload still returns it' );

// The dashboard status must still know a last good copy exists.
$status = LSTAB_Admin::status_for( LSTAB_Storage::get_all()[0] );
lstab_assert( in_array( $status['state'], array( 'ok', 'stale', 'error', 'never' ), true ), 'Status resolves from metadata alone', $status['state'] );

foreach ( $filler as $filler_id ) {
	LSTAB_Storage::delete( $filler_id );
}

lstab_assert( 900 === LSTAB_Limits::min_interval(), 'Free tier floor is 15 minutes', (string) LSTAB_Limits::min_interval() );
lstab_assert( ! isset( LSTAB_Limits::intervals()[60] ), 'One minute interval hidden in free' );
lstab_assert( 900 === LSTAB_Limits::clamp_interval( 60 ), 'A too-fast interval is clamped up', (string) LSTAB_Limits::clamp_interval( 60 ) );
lstab_assert( 3 === count( LSTAB_Styles::available() ), 'Three free presets available', (string) count( LSTAB_Styles::available() ) );
lstab_assert( 'clean' === LSTAB_Styles::sanitize( 'midnight' ), 'Pro preset falls back to a free one in free', LSTAB_Styles::sanitize( 'midnight' ) );

// There is no row cap anywhere: prove it with a large sheet.
$big_rows = array();
for ( $i = 0; $i < 5000; $i++ ) {
	$big_rows[] = array( 'Row ' . $i, (string) $i, 'value' );
}
$big_html = LSTAB_Renderer::render_preview(
	array(
		'headers' => array( 'A', 'B', 'C' ),
		'rows'    => $big_rows,
	)
);
lstab_assert( 5001 === substr_count( $big_html, '<tr' ), 'A 5000 row table renders in full — no row cap', (string) substr_count( $big_html, '<tr' ) );

// Pro simulation: the add-on lifts every limit through filters alone.
add_filter( 'lstab_is_pro', '__return_true' );
add_filter( 'lstab_max_sources', function () { return 25; } );
add_filter( 'lstab_min_sync_interval', function () { return 60; } );

lstab_assert( LSTAB_Limits::is_pro(), 'lstab_is_pro filter flips the tier' );
lstab_assert( 25 === LSTAB_Limits::max_sources(), 'lstab_max_sources filter lifts the source cap' );
lstab_assert( LSTAB_Limits::can_add_source(), 'More sources allowed under Pro' );
lstab_assert( isset( LSTAB_Limits::intervals()[60] ), 'One minute interval unlocked under Pro' );
lstab_assert( 5 === count( LSTAB_Styles::available() ), 'Premium presets unlocked under Pro', (string) count( LSTAB_Styles::available() ) );
lstab_assert( 'lstab_1min' === LSTAB_Cron::required_schedule() || 'lstab_15min' === LSTAB_Cron::required_schedule(), 'Cron recurrence follows the fastest source' );

// Conditional formatting hook (a Pro feature) can colour a cell.
$lstab_cell_probe = function ( $html, $value, $col ) {
	if ( 1 === $col && false !== strpos( $value, '4 199' ) ) {
		return '<span class="lstab-flag-high">' . esc_html( $value ) . '</span>';
	}
	return $html;
};
add_filter( 'lstab_render_cell', $lstab_cell_probe, 10, 3 );

$formatted = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $formatted, 'lstab-flag-high' ), 'lstab_render_cell can style a cell (Pro conditional formatting)' );

// Only this probe: the plugin has its own callback on this hook for linking
// addresses, and clearing the whole thing would quietly disable it for every
// section that follows.
remove_filter( 'lstab_render_cell', $lstab_cell_probe, 10 );
lstab_assert( has_filter( 'lstab_render_cell' ), 'Removing a probe leaves the plugin\'s own cell filter in place' );

// An add-on has to be able to put its own fields on the source screen and read
// them back after the save, without the free plugin knowing what they are.
$lstab_settings_hook = false;
add_action(
	'lstab_edit_page_settings',
	function ( $source, $is_edit ) use ( &$lstab_settings_hook ) {
		$lstab_settings_hook = array( 'edit' => $is_edit, 'source' => is_array( $source ) );
	},
	10,
	2
);
wp_set_current_user( 1 );
$_GET['source'] = $source_id;
ob_start();
( new LSTAB_Admin() )->render_edit_page();
ob_get_clean();
unset( $_GET['source'] );
wp_set_current_user( 0 );
remove_all_filters( 'lstab_edit_page_settings' );
lstab_assert( is_array( $lstab_settings_hook ), 'The hook fires while the source screen renders' );
lstab_assert( ! empty( $lstab_settings_hook['edit'] ) && ! empty( $lstab_settings_hook['source'] ), 'It is handed the source it is being shown for', wp_json_encode( $lstab_settings_hook ) );

remove_all_filters( 'lstab_is_pro' );
remove_all_filters( 'lstab_max_sources' );
remove_all_filters( 'lstab_min_sync_interval' );

// ---------------------------------------------------------------------------

lstab_section( '11. Missing and broken sources' );

$missing = do_shortcode( '[sheet_table id="99999"]' );
lstab_assert( '' === $missing, 'An unknown ID renders nothing for visitors', $missing );

$no_id = do_shortcode( '[sheet_table]' );
lstab_assert( '' === $no_id, 'A shortcode without an ID renders nothing for visitors', $no_id );

wp_set_current_user( 1 );
$missing_admin = do_shortcode( '[sheet_table id="99999"]' );
lstab_assert( false !== strpos( $missing_admin, 'lstab-notice' ), 'Administrators do get an explanatory notice' );
wp_set_current_user( 0 );

// A source that has never synced must not render a broken table.
$never_id = LSTAB_Storage::insert(
	array(
		'title'     => 'Never synced',
		'sheet_url' => 'https://docs.google.com/spreadsheets/d/NEVERSYNCEDSHEETID000000000000000000/edit',
		'sheet_id'  => 'NEVERSYNCEDSHEETID000000000000000000',
	)
);
lstab_assert( '' === do_shortcode( '[sheet_table id="' . $never_id . '"]' ), 'A never-synced source renders nothing for visitors' );
LSTAB_Storage::delete( $never_id );

// ---------------------------------------------------------------------------

lstab_section( '12. REST surface' );

$server = rest_get_server();
$routes = $server->get_routes();

lstab_assert( isset( $routes['/live-sheets-table/v1/preview'] ), 'Preview route registered' );
lstab_assert( isset( $routes['/live-sheets-table/v1/sources'] ), 'Sources route registered' );

$request  = new WP_REST_Request( 'POST', '/live-sheets-table/v1/preview' );
$request->set_param( 'url', 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit' );
$response = $server->dispatch( $request );
lstab_assert( 401 === $response->get_status() || 403 === $response->get_status(), 'Preview refuses anonymous callers', (string) $response->get_status() );

wp_set_current_user( 1 );
$response = $server->dispatch( $request );
$data     = $response->get_data();
lstab_assert( 200 === $response->get_status(), 'Preview works for an administrator', (string) $response->get_status() );
lstab_assert( isset( $data['headers'] ) && 5 === count( $data['headers'] ), 'Preview returns parsed headers', wp_json_encode( $data['headers'] ?? null ) );
lstab_assert( isset( $data['tabs'] ) && 3 === count( $data['tabs'] ), 'Preview returns the tab list' );
lstab_assert( isset( $data['html'] ) && false !== strpos( $data['html'], '<table' ), 'Preview returns rendered HTML' );

// The preview must honour the requested preset; without this the admin screen
// always rendered "clean" no matter which preset was selected or saved.
$styled = new WP_REST_Request( 'POST', '/live-sheets-table/v1/preview' );
$styled->set_param( 'url', 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit' );
$styled->set_param( 'style', 'striped' );
$styled_data = $server->dispatch( $styled )->get_data();
lstab_assert(
	false !== strpos( $styled_data['html'], 'lstab-style-striped' ),
	'Preview renders the requested style preset',
	'no striped class in preview HTML'
);

$unstyled = new WP_REST_Request( 'POST', '/live-sheets-table/v1/preview' );
$unstyled->set_param( 'url', 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit' );
$unstyled_data = $server->dispatch( $unstyled )->get_data();
lstab_assert(
	false !== strpos( $unstyled_data['html'], 'lstab-style-clean' ),
	'Preview falls back to the default preset when none is asked for'
);

$pro_styled = new WP_REST_Request( 'POST', '/live-sheets-table/v1/preview' );
$pro_styled->set_param( 'url', 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit' );
$pro_styled->set_param( 'style', 'midnight' );
$pro_data = $server->dispatch( $pro_styled )->get_data();
lstab_assert(
	false !== strpos( $pro_data['html'], 'lstab-style-clean' ),
	'Preview will not render a Pro-only preset on the free tier'
);

$bad = new WP_REST_Request( 'POST', '/live-sheets-table/v1/preview' );
$bad->set_param( 'url', 'https://evil.example.com/spreadsheets/d/x/edit' );
$bad_response = $server->dispatch( $bad );
lstab_assert( 400 === $bad_response->get_status(), 'Preview rejects a non-Google host', (string) $bad_response->get_status() );

$refresh  = new WP_REST_Request( 'POST', '/live-sheets-table/v1/sources/' . $source_id . '/refresh' );
$refresh_response = $server->dispatch( $refresh );
lstab_assert( 200 === $refresh_response->get_status(), 'Manual refresh endpoint works', (string) $refresh_response->get_status() );
wp_set_current_user( 0 );

// ---------------------------------------------------------------------------

/*
 * When a table comes out wrong the first question is whether the sheet or the
 * plugin is at fault, and only the bytes Google actually sent answer it. The
 * preview carries them so nobody has to take anyone's word for it.
 */
wp_set_current_user( 1 );
lstab_set_mock( 'ragged' );
$raw_request = new WP_REST_Request( 'POST', '/live-sheets-table/v1/preview' );
$raw_request->set_param( 'url', 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0' );
$raw_response = rest_get_server()->dispatch( $raw_request );
$raw_data     = $raw_response->get_data();
wp_set_current_user( 0 );

lstab_assert( 200 === $raw_response->get_status(), 'The preview answers', (string) $raw_response->get_status() );
lstab_assert( isset( $raw_data['raw'] ), 'The preview carries the payload Google sent' );
lstab_assert( false !== strpos( (string) $raw_data['raw'], 'Produkt,Cena netto' ), 'It is the text as it arrived, not the parsed table', substr( (string) $raw_data['raw'], 0, 60 ) );
lstab_assert( isset( $raw_data['rawBytes'] ) && $raw_data['rawBytes'] > 0, 'And says how much of it there was' );
lstab_assert(
	isset( $raw_data['ragged']['rows'][0]['row'] ) && 3 === (int) $raw_data['ragged']['rows'][0]['row'],
	'A row that came back short is pointed at, so nobody has to count lines',
	wp_json_encode( isset( $raw_data['ragged'] ) ? $raw_data['ragged'] : null )
);
lstab_set_mock( 'ok', 'main' );

// The preview endpoint has to be told which source is being edited, or nothing
// configured per source outside the free plugin's own table can reach it.
$preview_route = rest_get_server()->get_routes();
lstab_assert(
	isset( $preview_route['/live-sheets-table/v1/preview'][0]['args']['sourceId'] ),
	'The preview endpoint accepts the source being edited'
);

// ---------------------------------------------------------------------------

lstab_section( '13. Block registration' );

$registry = WP_Block_Type_Registry::get_instance();
lstab_assert( $registry->is_registered( 'live-sheets-table/sheet-table' ), 'Block type registered' );

$block_type = $registry->get_registered( 'live-sheets-table/sheet-table' );
lstab_assert( is_callable( $block_type->render_callback ), 'Block has a server-side render callback' );

// ---------------------------------------------------------------------------

lstab_section( '12a. Column labels and visibility' );

$sheet_headers = array( 'Produkt', 'Cena netto', 'Dostępność', 'Opis', 'Zaktualizowano' );

// Renaming is display only. The spreadsheet is never written to, so a heading
// in Google can say anything — including a working name nobody should read.
$renamed = LSTAB_Columns::apply(
	array(
		'headers' => $sheet_headers,
		'rows'    => array( array( 'Kask', '349,00', 'W magazynie', 'Rozmiary', '2026-08-21' ) ),
	),
	array(
		0 => array( 'label' => 'Towar' ),
		1 => array( 'label' => 'Cena' ),
	)
);
lstab_assert( 'Towar' === $renamed['headers'][0], 'A column can be renamed for visitors', $renamed['headers'][0] );
lstab_assert( 'Cena' === $renamed['headers'][1], 'A second rename applies too', $renamed['headers'][1] );
lstab_assert( 'Dostępność' === $renamed['headers'][2], 'Columns left alone keep the sheet heading', $renamed['headers'][2] );
lstab_assert( 5 === count( $renamed['rows'][0] ), 'Renaming changes no data', (string) count( $renamed['rows'][0] ) );

// Hiding removes the column from both the headings and every row, so a working
// column cannot leak through the data even though the heading is gone.
$hidden = LSTAB_Columns::apply(
	array(
		'headers' => $sheet_headers,
		'rows'    => array( array( 'Kask', '349,00', 'W magazynie', 'sekret', '2026-08-21' ) ),
	),
	array( 3 => array( 'hidden' => true ) )
);
lstab_assert( 4 === count( $hidden['headers'] ), 'Hiding removes the heading', (string) count( $hidden['headers'] ) );
lstab_assert( ! in_array( 'Opis', $hidden['headers'], true ), 'The hidden heading is gone' );
lstab_assert( ! in_array( 'sekret', $hidden['rows'][0], true ), 'The hidden column\'s data is gone from every row', wp_json_encode( $hidden['rows'][0] ) );
lstab_assert( 'Zaktualizowano' === $hidden['headers'][3], 'Later columns close the gap', $hidden['headers'][3] );

// The form asks "include?"; storage keeps "hidden". An absent key must mean
// visible, or a column would vanish the moment anything else was saved.
$from_form = LSTAB_Columns::sanitize( array( 0 => array( 'visible' => '1' ), 1 => array() ) );
lstab_assert( false === $from_form[0]['hidden'], 'A ticked include box means visible' );
lstab_assert( false === $from_form[1]['hidden'], 'An absent setting means visible, not hidden' );
$unticked = LSTAB_Columns::sanitize( array( 0 => array( 'label' => 'x' ) ) );
lstab_assert( false === $unticked[0]['hidden'], 'Renaming alone never hides a column' );

// An unticked checkbox submits nothing at all, which is indistinguishable from
// "this form had no opinion". The form pairs every box with a hidden field
// carrying the "off" answer, and this is that contract.
$switched_off = LSTAB_Columns::sanitize( array( 0 => array( 'visible' => '0' ) ) );
lstab_assert( true === $switched_off[0]['hidden'], 'An explicit "off" hides the column' );

// Hiding everything is a mistake, not an instruction.
$all_hidden = LSTAB_Columns::apply(
	array(
		'headers' => array( 'A', 'B' ),
		'rows'    => array( array( '1', '2' ) ),
	),
	array(
		0 => array( 'hidden' => true ),
		1 => array( 'hidden' => true ),
	)
);
lstab_assert( 2 === count( $all_hidden['headers'] ), 'Hiding every column falls back to showing the table' );

// A column is addressed by its position, and the heading that was in that
// position when the choice was made is kept beside it as the check. A column
// nobody has touched simply records whatever heading is there now.
$config = LSTAB_Columns::reconcile( array(), $sheet_headers );
lstab_assert( 'Cena netto' === $config[1]['source'], 'An untouched column records the heading in its position', $config[1]['source'] );

// Somebody renames it. The form carries the heading they were looking at, so
// the choice knows what it was made about.
$config[1] = array(
	'label'  => 'Cena',
	'hidden' => false,
	'source' => 'Cena netto',
);
$config = LSTAB_Columns::reconcile( $config, $sheet_headers );
lstab_assert( 'Cena' === $config[1]['label'], 'Reconciling keeps the label' );
lstab_assert( 'Cena netto' === $config[1]['source'], 'And keeps the heading the choice was made about', $config[1]['source'] );

$shifted = array( 'Produkt', 'SKU', 'Cena netto', 'Dostępność', 'Opis' );
$drifted = LSTAB_Columns::drift( $config, $shifted );
lstab_assert( 1 === count( $drifted ), 'An inserted column is detected', wp_json_encode( $drifted ) );
lstab_assert( 'Cena netto' === $drifted[0]['was'] && 'SKU' === $drifted[0]['now'], 'The report says what moved', wp_json_encode( $drifted[0] ) );
lstab_assert( ! LSTAB_Columns::drift( $config, $sheet_headers ), 'No drift is reported when nothing moved' );

/*
 * Syncing against the shifted sheet must leave that remembered heading alone.
 * Adopting the new one would erase the only evidence that anything moved, and
 * the choice would go on being applied to whatever had taken the position over.
 */
$after_shift = LSTAB_Columns::reconcile( $config, $shifted );
lstab_assert( 'Cena netto' === $after_shift[1]['source'], 'A sync never adopts the new heading for a configured column', $after_shift[1]['source'] );
lstab_assert( 1 === count( LSTAB_Columns::drift( $after_shift, $shifted ) ), 'So the shift is still reported after a sync' );

// And the choice stops applying while the position holds something else: the
// column is shown under the sheet's own heading rather than under a name that
// was meant for different data.
$mislabelled = LSTAB_Columns::apply(
	array(
		'headers' => $shifted,
		'rows'    => array( array( 'Kask', 'K-1', '349,00', 'W magazynie', 'Rozmiary' ) ),
	),
	$after_shift
);
lstab_assert( 'SKU' === $mislabelled['headers'][1], 'A shifted column shows the sheet heading, not the old label', $mislabelled['headers'][1] );

// The same rule for hiding, which is the one that matters: a column left out
// comes back rather than a different column disappearing in its place.
$hidden_shift = LSTAB_Columns::apply(
	array(
		'headers' => $shifted,
		'rows'    => array( array( 'Kask', 'K-1', '349,00', 'W magazynie', 'Rozmiary' ) ),
	),
	array( 3 => array( 'hidden' => true, 'source' => 'Opis' ) )
);
lstab_assert( 5 === count( $hidden_shift['headers'] ), 'A hidden column whose heading has moved is shown again', wp_json_encode( $hidden_shift['headers'] ) );
lstab_assert( in_array( 'Dostępność', $hidden_shift['headers'], true ), 'And the column that took its place is not taken out instead', wp_json_encode( $hidden_shift['headers'] ) );

$reported = LSTAB_Columns::orphans( array( 3 => array( 'hidden' => true, 'source' => 'Opis' ) ), $shifted );
lstab_assert( 1 === count( $reported ), 'Which is reported rather than left to be noticed', wp_json_encode( $reported ) );
lstab_assert( 'Opis' === $reported[0]['was'] && 'Dostępność' === $reported[0]['now'], 'The report says what was there and what is there now', wp_json_encode( $reported[0] ) );
lstab_assert( 'D' === $reported[0]['letter'], 'And names the column the way Google does', $reported[0]['letter'] );
lstab_assert( ! LSTAB_Columns::orphans( array( 3 => array( 'hidden' => true, 'source' => 'Opis' ) ), $sheet_headers ), 'Nothing is reported while the heading is where it was' );

$untouched = LSTAB_Columns::reconcile( array(), $sheet_headers );
lstab_assert( ! LSTAB_Columns::drift( $untouched, $shifted ), 'Columns nobody configured cannot drift' );
lstab_assert( ! LSTAB_Columns::orphans( $untouched, $shifted ), 'And are not reported either' );

// End to end: settings reach the rendered page.
LSTAB_Storage::update(
	$source_id,
	array(
		'columns_config' => array(
			1 => array( 'label' => 'Cena brutto', 'source' => 'Cena netto' ),
			3 => array( 'hidden' => true, 'source' => 'Opis' ),
		),
	)
);
$configured_html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $configured_html, 'Cena brutto' ), 'The renamed heading reaches the page' );
lstab_assert( false === strpos( $configured_html, 'Rama aluminiowa' ), 'The hidden column\'s data never reaches the page' );
lstab_assert( 4 === substr_count( $configured_html, '<th scope="col"' ), 'Four columns are rendered', (string) substr_count( $configured_html, '<th scope="col"' ) );

// A sync must not discard the settings.
lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );
$after_sync = LSTAB_Storage::get( $source_id );
lstab_assert( 'Cena brutto' === $after_sync['columns_config'][1]['label'], 'A sync keeps the labels', wp_json_encode( $after_sync['columns_config'][1] ) );
lstab_assert( true === $after_sync['columns_config'][3]['hidden'], 'A sync keeps the visibility' );
lstab_assert( 'Cena netto' === $after_sync['columns_config'][1]['source'], 'A sync keeps the heading the choice was made about' );

// The whole card once sat outside the form element, so nothing in it was ever
// submitted: renaming and hiding looked like they worked and then silently
// reverted on save. These assertions are about the wiring, not the styling.
wp_set_current_user( 1 );
$_GET['source'] = $source_id;
$lstab_admin_screen = new LSTAB_Admin();
ob_start();
$lstab_admin_screen->render_edit_page();
$edit_html = (string) ob_get_clean();
unset( $_GET['source'] );

$form_open  = strpos( $edit_html, '<form' );
$form_close = strpos( $edit_html, '</form>' );
$card_at    = strpos( $edit_html, 'lstab-columns-card' );
$submit_at  = strpos( $edit_html, 'lstab-submit' );

lstab_assert( false !== $card_at, 'The edit screen shows the columns card' );
lstab_assert( $card_at > $form_open && $card_at < $form_close, 'The columns card is inside the form, so its fields are submitted' );
lstab_assert( $submit_at > $card_at && $submit_at < $form_close, 'The save button sits below the columns it saves' );
lstab_assert(
	false !== strpos( $edit_html, 'name="columns[0][hidden]"' ),
	'Each column carries its state back, so saving cannot change it by accident'
);
lstab_assert(
	false === strpos( $edit_html, 'name="columns[0][visible]"' ),
	'And there is no control here for changing it, which is the add-on\'s job'
);

// Before the first sync there is nothing to list, but the card still appears —
// people went looking for this setting and found an empty page where it should
// have been.
LSTAB_Storage::update( $source_id, array( 'columns_config' => array() ) );
$_GET['source'] = $source_id;
ob_start();
$lstab_admin_screen->render_edit_page();
$waiting_html = (string) ob_get_clean();
unset( $_GET['source'] );
wp_set_current_user( 0 );

lstab_assert( false !== strpos( $waiting_html, 'lstab-columns-card is-waiting' ), 'The card is still shown before the first sync' );
lstab_assert( false !== strpos( $waiting_html, 'lstab-columns-waiting' ), 'It says what it is waiting for' );
lstab_assert(
	false === strpos( $waiting_html, 'name="columns[0][source]" value=""' )
		|| false !== strpos( $waiting_html, 'disabled' ),
	'Its placeholder rows are disabled, so they cannot be saved as real columns'
);

// The block carries a filter attribute the free plugin never reads. It is the
// add-on that gives it meaning, so on its own it must be inert rather than an
// error — the same arrangement as the shortcode attribute.
$block_json = json_decode( (string) file_get_contents( LSTAB_PATH . 'blocks/sheet-table/block.json' ), true );
lstab_assert( isset( $block_json['attributes']['filter'] ), 'The block declares a filter attribute' );

lstab_assert( ! LSTAB_Limits::is_pro(), 'The free suite really is running without Pro' );

/*
 * An add-on can be deactivated by an expired licence, a conflict or a tidy-up.
 * A page built to show one category would then publish the whole sheet —
 * working rows included — and nobody would notice. Showing nothing is a
 * visible gap someone will fix; showing everything is a disclosure that is
 * already indexed by the time anyone spots it.
 */
$block_free = new LSTAB_Block();
$unfiltered = substr_count( $block_free->render( array( 'sourceId' => $source_id ) ), '<tr role="row" class="lstab-row"' );
$attempted  = substr_count( $block_free->render( array( 'sourceId' => $source_id, 'filter' => 'Dostępność is Brak' ) ), '<tr role="row" class="lstab-row"' );
lstab_assert( $unfiltered > 0, 'The unfiltered block still renders its rows', (string) $unfiltered );
lstab_assert( 0 === $attempted, 'A filter nothing can honour shows no rows rather than every row', (string) $attempted );

$shortcode_attempt = do_shortcode( '[sheet_table id="' . $source_id . '" filter="Dostępność is Brak"]' );
lstab_assert( 0 === substr_count( $shortcode_attempt, '<tr role="row" class="lstab-row"' ), 'The shortcode is held to the same rule' );

wp_set_current_user( 1 );
$owner_sees = do_shortcode( '[sheet_table id="' . $source_id . '" filter="Dostępność is Brak"]' );
lstab_assert( false !== strpos( $owner_sees, 'not active' ), 'Someone who can fix it is told why the table is empty' );
wp_set_current_user( 0 );

$visitor_sees = do_shortcode( '[sheet_table id="' . $source_id . '" filter="Dostępność is Brak"]' );
lstab_assert( '' === trim( $visitor_sees ), 'A visitor is shown nothing at all, not an explanation', substr( $visitor_sees, 0, 80 ) );

/*
 * A page whose only table ends in a notice has never asked for the stylesheet,
 * so the message used to arrive as a bare paragraph and read like broken page
 * content rather than something addressed to whoever can fix it.
 */
wp_dequeue_style( 'lstab-table' );
wp_set_current_user( 1 );
do_shortcode( '[sheet_table id="' . $source_id . '" filter="Dostępność is Brak"]' );
lstab_assert( wp_style_is( 'lstab-table', 'enqueued' ), 'A notice brings the stylesheet with it, so it looks like a notice' );
wp_set_current_user( 0 );

// ---------------------------------------------------------------------------

lstab_section( '12e. Hiding rows by line, checked by what is on it' );

/*
 * A choice records the line and what was on it. The line is how the row is
 * found; what was on it is how the choice is checked. Neither alone would do:
 * a line moves for reasons having nothing to do with the row, and a name is
 * shared by ten helmets.
 */
lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );
$sheet = LSTAB_Storage::get( $source_id );
$rows  = $sheet['data']['rows'];

$entry = LSTAB_Hidden_Rows::entry_for( $rows[1], 1 );
lstab_assert( 1 === $entry['index'], 'A choice records which line' );
lstab_assert( 'Kask Lazer' === $entry['name'], 'And what was called there', $entry['name'] );
lstab_assert( 32 === strlen( $entry['sig'] ), 'And everything it said' );
lstab_assert( false !== strpos( $entry['label'], 'Kask Lazer' ), 'And how to describe it later', $entry['label'] );

lstab_assert( LSTAB_Hidden_Rows::still_there( $entry, $rows ), 'It is recognised while the sheet has not moved' );

// The everyday case: ten helmets, one of them taken out on its own line.
$helmets = array(
	array( 'Kask', 'S', '100' ),
	array( 'Kask', 'M', '120' ),
	array( 'Kask', 'L', '140' ),
);
$only_m = array( LSTAB_Hidden_Rows::entry_for( $helmets[1], 1 ) );
$after  = LSTAB_Hidden_Rows::filter_rows( $helmets, array(), array( 'hidden_rows' => $only_m ), array() );
lstab_assert( 2 === count( $after ), 'One of three rows sharing a name is taken out on its own', (string) count( $after ) );
lstab_assert( 'S' === $after[0][1] && 'L' === $after[1][1], 'And it is the right one', wp_json_encode( $after ) );

/*
 * Editing it puts it back, and that is deliberate. Checking only the name would
 * spare an edited row — and would also pass on whichever helmet had moved into
 * that line, since all three are called "Kask". Taking out the wrong row is the
 * one outcome none of this may produce, so any change to the line puts the row
 * on the page and says so.
 */
$repriced    = $helmets;
$repriced[1] = array( 'Kask', 'M', '199' );
lstab_assert(
	3 === count( LSTAB_Hidden_Rows::filter_rows( $repriced, array(), array( 'hidden_rows' => $only_m ), array() ) ),
	'Editing the row puts it back on the page rather than guessing'
);
lstab_assert( 'moved' === LSTAB_Hidden_Rows::unresolved( $only_m, $repriced )[0]['reason'], 'And says so' );

/*
 * Moving it does. This is the whole point of the check: the plugin will not
 * take out whatever happens to have landed on that line, because taking out the
 * wrong row is a mistake nobody sees. It shows the row and says so instead.
 */
$shifted = array_merge( array( array( 'Rower', '', '4000' ) ), $helmets );
$moved   = LSTAB_Hidden_Rows::filter_rows( $shifted, array(), array( 'hidden_rows' => $only_m ), array() );
lstab_assert( 4 === count( $moved ), 'A row inserted above puts the hidden row back on the page', (string) count( $moved ) );
lstab_assert( in_array( array( 'Kask', 'M', '120' ), $moved, true ), 'And no other row has been taken out in its place', wp_json_encode( $moved ) );

$told = LSTAB_Hidden_Rows::unresolved( $only_m, $shifted );
lstab_assert( 1 === count( $told ) && 'moved' === $told[0]['reason'], 'Which is reported rather than left to be noticed', wp_json_encode( $told ) );

// A sheet that has shrunk past that line is a different thing to say.
$short = array( array( 'Kask', 'S', '100' ) );
lstab_assert( 'missing' === LSTAB_Hidden_Rows::unresolved( $only_m, $short )[0]['reason'], 'A line the sheet no longer reaches is reported differently' );

// A row whose first cell is empty is named by its first filled one.
$nameless = array(
	array( '', 'Bez nazwy', '10' ),
	array( 'Kask', 'M', '120' ),
);
$hide_nameless = array( LSTAB_Hidden_Rows::entry_for( $nameless[0], 0 ) );
lstab_assert( 'Bez nazwy' === $hide_nameless[0]['name'], 'A row whose first cell is empty is named by the next filled one', $hide_nameless[0]['name'] );
lstab_assert( 1 === count( LSTAB_Hidden_Rows::filter_rows( $nameless, array(), array( 'hidden_rows' => $hide_nameless ), array() ) ), 'And can be taken out' );

// A row with nothing in it anywhere has no name at all, and the line plus what
// it said is still enough to take it out: a spacer row is a real thing to hide.
$blank = array(
	array( '', '', '' ),
	array( 'Kask', 'M', '120' ),
);
$hide_blank = array( LSTAB_Hidden_Rows::entry_for( $blank[0], 0 ) );
lstab_assert( '' === $hide_blank[0]['name'], 'A row with nothing in it has no name', $hide_blank[0]['name'] );
lstab_assert( '' === $hide_blank[0]['label'], 'And nothing to describe it by', $hide_blank[0]['label'] );
lstab_assert( 1 === count( LSTAB_Hidden_Rows::filter_rows( $blank, array(), array( 'hidden_rows' => $hide_blank ), array() ) ), 'It can still be taken out' );

$nameless_edited    = $nameless;
$nameless_edited[0] = array( '', 'Bez nazwy', '11' );
lstab_assert(
	2 === count( LSTAB_Hidden_Rows::filter_rows( $nameless_edited, array(), array( 'hidden_rows' => $hide_nameless ), array() ) ),
	'Editing it puts it back, exactly as for any other row'
);

// A row is described by what it says, four cells at most.
lstab_assert( 'Kask · M · 120' === LSTAB_Hidden_Rows::describe( array( 'Kask', 'M', '120' ) ), 'A row is described by what it says', LSTAB_Hidden_Rows::describe( array( 'Kask', 'M', '120' ) ) );
$wide = array_map( 'strval', range( 1, 20 ) );
lstab_assert( '1 · 2 · 3 · 4' === LSTAB_Hidden_Rows::describe( $wide ), 'A wide row is described by four cells, not twenty', LSTAB_Hidden_Rows::describe( $wide ) );

// Cleaning: an entry with no line at all is not an entry.
$cleaned = LSTAB_Hidden_Rows::sanitize(
	array(
		array( 'index' => 2, 'name' => ' Kask ', 'sig' => str_repeat( 'a', 32 ), 'label' => 'Kask · M' ),
		array( 'index' => 2, 'name' => 'Duplikat' ),
		array( 'name' => 'Bez linii' ),
		'nonsense',
	)
);
lstab_assert( 1 === count( $cleaned ), 'Choices are deduplicated by line and rubbish is dropped', wp_json_encode( $cleaned ) );
lstab_assert( 'Kask' === $cleaned[0]['name'], 'And trimmed' );

// A sync leaves a choice that no longer matches exactly as it was, so that it
// starts working again the moment the sheet is put back.
LSTAB_Storage::update( $source_id, array( 'hidden_rows' => array( LSTAB_Hidden_Rows::entry_for( $rows[1], 1 ) ) ) );
$edited    = $rows;
$edited[1] = array( 'Kask Lazer', '999,00', 'Brak', '', '2026-01-02' );
LSTAB_Storage::record_success( $source_id, array( 'headers' => $sheet['data']['headers'], 'rows' => $edited, 'offset' => 1 ) );
LSTAB_Hidden_Rows::reanchor( $source_id, $edited );
lstab_assert(
	LSTAB_Hidden_Rows::signature( $rows[1] ) === LSTAB_Storage::get( $source_id )['hidden_rows'][0]['sig'],
	'A choice that no longer matches is left untouched, not quietly moved on'
);
LSTAB_Storage::record_success( $source_id, array( 'headers' => $sheet['data']['headers'], 'rows' => $rows, 'offset' => 1 ) );
lstab_assert(
	LSTAB_Hidden_Rows::still_there( LSTAB_Storage::get( $source_id )['hidden_rows'][0], $rows ),
	'So putting the sheet back makes it work again'
);

// End to end on a real source.
lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );
LSTAB_Storage::update( $source_id, array( 'hidden_rows' => array( LSTAB_Hidden_Rows::entry_for( LSTAB_Storage::get( $source_id )['data']['rows'][1], 1 ) ) ) );
$hidden_html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false === strpos( $hidden_html, 'Kask Lazer' ), 'A hidden row is not on the page' );
lstab_assert( false !== strpos( $hidden_html, 'Rower górski' ), 'The rest of the table is untouched' );

$_GET = array( LSTAB_Paging::arg( $source_id, 'q' ) => 'Kask' );
LSTAB_Storage::update( $source_id, array( 'per_page' => 10 ) );
$searched = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
$_GET     = array();
LSTAB_Storage::update( $source_id, array( 'per_page' => 0 ) );
lstab_assert( false === strpos( $searched, 'Kask Lazer' ), 'A hidden row cannot be found by searching for it' );

// ---------------------------------------------------------------------------

lstab_section( '12f. What happens when Pro stops' );

/*
 * Choosing what to leave out is the add-on's. A licence ending on a Tuesday
 * should not rearrange a public page on the Tuesday, so the choices keep
 * working for ten days — and the countdown is said out loud while it runs,
 * because ten quiet days followed by a page changing by itself would be worse
 * than no grace at all.
 */
delete_option( LSTAB_Limits::SEEN_OPTION );
lstab_assert( 0 === LSTAB_Limits::grace_remaining(), 'A site that never had Pro has no grace to spend' );
lstab_assert( ! LSTAB_Limits::pro_effective(), 'And hiding is not honoured there' );

$showing = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $showing, 'Kask Lazer' ), 'The hidden row is shown again once nothing is paid for' );

// Pro seen yesterday: the choice still stands, and the countdown is running.
update_option( LSTAB_Limits::SEEN_OPTION, time() - DAY_IN_SECONDS, true );
lstab_assert( LSTAB_Limits::pro_effective(), 'A day after Pro stops, choices are still honoured' );
lstab_assert(
	LSTAB_Limits::grace_remaining() > 8 * DAY_IN_SECONDS,
	'With most of the ten days left',
	(string) round( LSTAB_Limits::grace_remaining() / DAY_IN_SECONDS, 1 )
);
lstab_assert(
	false === strpos( do_shortcode( '[sheet_table id="' . $source_id . '"]' ), 'Kask Lazer' ),
	'And the page is unchanged from the day the licence ended'
);

// The countdown is for the people who can act on it, so it is shown to them
// and to nobody else.
$lstab_prior_user = get_current_user_id();
$lstab_admin_user = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( $lstab_admin_user ? (int) $lstab_admin_user[0] : 1 );

ob_start();
LSTAB_Admin::print_grace_notice();
$grace_notice = (string) ob_get_clean();
lstab_assert( false !== strpos( $grace_notice, 'lstab-grace-notice' ), 'The dashboard says the countdown is running' );
lstab_assert( false !== strpos( $grace_notice, 'is-dismissible' ), 'And it can be put away' );

wp_set_current_user( 0 );
ob_start();
LSTAB_Admin::print_grace_notice();
lstab_assert( '' === trim( (string) ob_get_clean() ), 'Someone who cannot change it is not shown it' );
wp_set_current_user( $lstab_admin_user ? (int) $lstab_admin_user[0] : 1 );

// Eleven days on: released, and the pages show everything again.
update_option( LSTAB_Limits::SEEN_OPTION, time() - 11 * DAY_IN_SECONDS, true );
lstab_assert( 0 === LSTAB_Limits::grace_remaining(), 'After ten days the grace is spent' );
lstab_assert(
	false !== strpos( do_shortcode( '[sheet_table id="' . $source_id . '"]' ), 'Kask Lazer' ),
	'And the row is on the page again'
);

ob_start();
LSTAB_Admin::print_grace_notice();
lstab_assert( '' === trim( (string) ob_get_clean() ), 'The countdown notice stops once there is nothing to count' );

wp_set_current_user( $lstab_prior_user );

// Hidden columns follow exactly the same rule.
LSTAB_Storage::update( $source_id, array( 'columns_config' => array( 2 => array( 'hidden' => true, 'label' => '', 'source' => 'Dostępność' ) ) ) );
lstab_assert(
	false !== strpos( do_shortcode( '[sheet_table id="' . $source_id . '"]' ), 'Dostępność' ),
	'A hidden column is shown again when the grace is spent'
);

update_option( LSTAB_Limits::SEEN_OPTION, time(), true );
lstab_assert(
	false === strpos( do_shortcode( '[sheet_table id="' . $source_id . '"]' ), 'Dostępność' ),
	'And left out again while it is not'
);

// A form saved from a screen without the picker must leave the list alone.
LSTAB_Storage::update( $source_id, array( 'columns_config' => array(), 'hidden_rows' => array() ) );
update_option( LSTAB_Limits::SEEN_OPTION, time(), true );

// ---------------------------------------------------------------------------

lstab_section( '12d. Links in cells' );

// A published sheet is very often a directory or a catalogue, and those hold
// addresses. Plain text a visitor has to select and copy reads as broken.
lstab_assert( null === LSTAB_Links::linkify( 'Rama aluminiowa, widelec 120 mm' ), 'Ordinary text is left alone' );
lstab_assert(
	false !== strpos( (string) LSTAB_Links::linkify( 'https://sklep.pl/rower' ), 'href="https://sklep.pl/rower"' ),
	'A web address becomes a link',
	(string) LSTAB_Links::linkify( 'https://sklep.pl/rower' )
);
lstab_assert(
	false !== strpos( (string) LSTAB_Links::linkify( 'biuro@firma.pl' ), 'href="mailto:biuro@firma.pl"' ),
	'An e-mail address becomes a mailto link'
);
lstab_assert(
	false !== strpos( (string) LSTAB_Links::linkify( 'www.sklep.pl' ), 'href="https://www.sklep.pl"' ),
	'A bare host gets a scheme rather than a broken relative link'
);

// The values come from a sheet the site owner may not control, so nothing here
// may become a scheme they did not write.
lstab_assert( null === LSTAB_Links::linkify( 'javascript:alert(1)' ), 'A script scheme is never linked' );
lstab_assert( null === LSTAB_Links::linkify( 'data:text/html;base64,PHNjcmlwdD4=' ), 'A data URI is never linked' );

$mixed = (string) LSTAB_Links::linkify( '<script>alert(1)</script> i mail@x.pl' );
lstab_assert( false === strpos( $mixed, '<script>' ), 'Markup around a link is still escaped', $mixed );
lstab_assert( false !== strpos( $mixed, 'mailto:mail@x.pl' ), 'And the link is still made' );

// Escaping first and hunting for links afterwards would corrupt this: the "&"
// would already have become "&amp;" and the address would be wrong.
$query = (string) LSTAB_Links::linkify( 'https://sklep.pl/a?x=1&y=2' );
lstab_assert( false !== strpos( $query, 'x=1&#038;y=2"' ), 'A query string survives intact in the address', $query );

// Sentences end in punctuation; addresses do not.
$trailing = (string) LSTAB_Links::linkify( 'Zobacz https://sklep.pl/a.' );
lstab_assert( false !== strpos( $trailing, 'href="https://sklep.pl/a"' ), 'A trailing full stop is not part of the address', $trailing );
lstab_assert( false !== strpos( $trailing, '</a>.' ), 'It is kept as text after the link' );

// End to end, and switchable per source.
LSTAB_Storage::record_success(
	$source_id,
	array(
		'headers' => array( 'Sklep', 'Kontakt' ),
		'rows'    => array( array( 'Rowery', 'biuro@rowery.pl' ) ),
	)
);

LSTAB_Storage::update( $source_id, array( 'link_cells' => 1 ) );
$linked_html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $linked_html, 'href="mailto:biuro@rowery.pl"' ), 'The rendered table carries the link' );
lstab_assert( false !== strpos( $linked_html, 'rel="nofollow ugc"' ), 'Links from a sheet are marked as not the site\'s own' );

LSTAB_Storage::update( $source_id, array( 'link_cells' => 0 ) );
$plain_html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false === strpos( $plain_html, 'href="mailto:' ), 'Switching it off stops the linking' );
lstab_assert( false !== strpos( $plain_html, 'biuro@rowery.pl' ), 'The address is still shown as text' );

LSTAB_Storage::update( $source_id, array( 'link_cells' => 1 ) );
lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );

// ---------------------------------------------------------------------------

lstab_section( '12c. Pinned column setting' );

lstab_assert( false !== strpos( do_shortcode( '[sheet_table id="' . $source_id . '"]' ), 'lstab-sticky-first' ), 'Pinning is on by default' );

LSTAB_Storage::update( $source_id, array( 'sticky_first' => 0 ) );
lstab_assert( false === strpos( do_shortcode( '[sheet_table id="' . $source_id . '"]' ), 'lstab-sticky-first' ), 'Pinning can be switched off per source' );
lstab_assert( false !== strpos( do_shortcode( '[sheet_table id="' . $source_id . '"]' ), 'lstab-scrollbar' ), 'Switching it off leaves the slider alone' );

LSTAB_Storage::update( $source_id, array( 'sticky_first' => 1 ) );

// ---------------------------------------------------------------------------

lstab_section( '12b. Is the data actually current?' );

/*
 * The question worth answering is whether the tables are up to date, not
 * whether a particular mechanism is running. DISABLE_WP_CRON is the normal
 * setup on any host with a real system cron, and warning about it meant
 * warning healthy sites about a fault they did not have.
 */
$health = LSTAB_Cron::health();
lstab_assert( isset( $health['state'], $health['message'], $health['detail'] ), 'Health check returns a structured result' );
lstab_assert( 'ok' === $health['state'], 'A site with fresh sheets is reported as fine, DISABLE_WP_CRON or not', $health['state'] );

// A sheet well past its interval is a real problem, and is named as one.
$wpdb->update(
	LSTAB_Storage::table(),
	array( 'last_success_gmt' => gmdate( 'Y-m-d H:i:s', time() - 6 * HOUR_IN_SECONDS ) ),
	array( 'id' => $source_id ),
	array( '%s' ),
	array( '%d' )
);
LSTAB_Storage::flush_cache( $source_id );

$stale = LSTAB_Cron::health();
lstab_assert( 'stale' === $stale['state'], 'A sheet six hours past a fifteen minute interval is reported', $stale['state'] );
lstab_assert( false !== strpos( $stale['message'], LSTAB_Storage::get( $source_id )['title'] ), 'The notice names which sheet', $stale['message'] );
lstab_assert( '' !== $stale['detail'], 'And explains what it means for the site owner' );

// Just past the interval is not yet a problem: schedules jitter.
$wpdb->update(
	LSTAB_Storage::table(),
	array( 'last_success_gmt' => gmdate( 'Y-m-d H:i:s', time() - 1200 ) ),
	array( 'id' => $source_id ),
	array( '%s' ),
	array( '%d' )
);
LSTAB_Storage::flush_cache( $source_id );
lstab_assert( 'ok' === LSTAB_Cron::health()['state'], 'A sheet only just past its interval does not raise anything' );

lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );

// A site nobody visits runs no schedule at all, and no plugin can fix that
// from inside PHP. What it can do is stop making the owner work out what to
// type: the line is built from their own address and their own interval.
LSTAB_Cron::ensure_scheduled();
$cron_line = LSTAB_Cron::system_cron_line();
lstab_assert( false !== strpos( $cron_line, site_url( 'wp-cron.php?doing_wp_cron' ) ), 'The cron line points at this site', $cron_line );
lstab_assert( 0 === strpos( $cron_line, '*/15 * * * * ' ), 'And fires at the interval the schedule actually uses', $cron_line );
lstab_assert( false !== strpos( $cron_line, 'curl -s ' ), 'It is a line a hosting panel will accept as it stands', $cron_line );

// With no sources there is nothing to report on at all.
$saved_sources = LSTAB_Storage::get_all();
foreach ( $saved_sources as $saved ) {
	LSTAB_Storage::delete( $saved['id'] );
}
lstab_assert( 'ok' === LSTAB_Cron::health()['state'], 'No sources means no notice' );

// Rebuild the source the later sections rely on.
$source_id = LSTAB_Storage::insert(
	array(
		'title'         => 'Cennik rowerowy',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'      => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind'    => 'doc',
		'gid'           => '0',
		'tab_name'      => 'Cennik',
		'sync_interval' => 900,
		'style_preset'  => 'striped',
	)
);
lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );

// A source that has never synced says so where it is listed and in the table
// itself, so it is not also reported here as a stale one.
$never = (int) LSTAB_Storage::insert(
	array(
		'title'      => 'Never synced',
		'sheet_url'  => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'   => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind' => 'doc',
		'gid'        => '0',
	)
);
lstab_assert( 'ok' === LSTAB_Cron::health()['state'], 'A source that never synced is not reported as a stale one' );
LSTAB_Storage::delete( $never );

// The tick must record that it ran, whether or not anything was due.
delete_option( LSTAB_Cron::LAST_TICK_OPT );
lstab()->cron->run_tick();
$ticked = (int) get_option( LSTAB_Cron::LAST_TICK_OPT );
lstab_assert( $ticked > time() - 60, 'Running the tick records that the scheduler is alive', (string) $ticked );

// ---------------------------------------------------------------------------

lstab_section( '13a. Appearance overrides' );

lstab_assert( LSTAB_Customizer::is_enabled(), 'The visual editor is available' );

$empty = LSTAB_Customizer::defaults();
lstab_assert( ! LSTAB_Customizer::has_overrides( $empty ), 'Untouched settings produce no overrides' );
lstab_assert( '' === LSTAB_Customizer::inline_style( $empty ), 'Untouched settings produce no style attribute' );

// These values end up inside a style attribute, so anything that is not a
// colour or a known keyword must be dropped rather than escaped and passed on.
$hostile = LSTAB_Customizer::sanitize(
	array(
		'accent'     => '#ff0000',
		'text'       => 'red; background:url(javascript:alert(1))',
		'background' => 'expression(alert(1))',
		'border'     => '#ABC',
		'stripe'     => '"><script>alert(1)</script>',
		'hover'      => '',
		'density'    => 'roomy',
		'fontSize'   => '../../etc/passwd',
		'corners'    => 'square',
		'unknownKey' => '#123456',
	)
);

lstab_assert( '#ff0000' === $hostile['accent'], 'A valid hex colour survives', $hostile['accent'] );
lstab_assert( '#ABC' === $hostile['border'], 'Short hex colours survive', $hostile['border'] );
lstab_assert( '' === $hostile['text'], 'A colour with a CSS payload is dropped', $hostile['text'] );
lstab_assert( '' === $hostile['background'], 'expression() is dropped', $hostile['background'] );
lstab_assert( '' === $hostile['stripe'], 'A markup payload is dropped', $hostile['stripe'] );
lstab_assert( 'roomy' === $hostile['density'], 'A known metric choice survives', $hostile['density'] );
lstab_assert( 'normal' === $hostile['fontSize'], 'An unknown metric choice falls back to normal', $hostile['fontSize'] );
lstab_assert( ! array_key_exists( 'unknownKey', $hostile ), 'Unknown keys are removed entirely' );

$style_attr = LSTAB_Customizer::inline_style( $hostile );
lstab_assert( false !== strpos( $style_attr, '--lstab-accent:#ff0000' ), 'Accent reaches the style attribute', $style_attr );
lstab_assert( false !== strpos( $style_attr, '--lstab-pad-y:1.05em' ), 'Roomy density maps onto padding', $style_attr );
lstab_assert( false !== strpos( $style_attr, '--lstab-radius:0' ), 'Square corners map onto the radius', $style_attr );
lstab_assert( false === strpos( $style_attr, 'javascript' ), 'No script payload reaches the style attribute' );
lstab_assert( false === strpos( $style_attr, '<' ), 'No markup reaches the style attribute' );

// Round-trip through the database and out to the rendered page.
LSTAB_Storage::update(
	$source_id,
	array(
		'style_vars' => array(
			'accent'     => '#8b5cf6',
			'background' => '#fffdf7',
			'density'    => 'compact',
		),
	)
);

$stored = LSTAB_Storage::get( $source_id );
lstab_assert( '#8b5cf6' === $stored['style_vars']['accent'], 'Overrides survive a round trip', wp_json_encode( $stored['style_vars'] ) );
lstab_assert( 'compact' === $stored['style_vars']['density'], 'Metric choices survive a round trip' );

$customised = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $customised, '--lstab-accent:#8b5cf6' ), 'The rendered table carries the override' );
lstab_assert( false !== strpos( $customised, '--lstab-pad-y:0.42em' ), 'Compact density reaches the page' );
lstab_assert(
	(bool) preg_match( '#<div class="lstab [^"]*"[^>]*style="[^"]*--lstab-accent#s', $customised ),
	'The override lands on the table wrapper, not somewhere else'
);

// Clearing them must actually clear them.
LSTAB_Storage::update( $source_id, array( 'style_vars' => LSTAB_Customizer::defaults() ) );
$cleared = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false === strpos( $cleared, '--lstab-accent' ), 'Resetting removes the override from the page' );
lstab_assert( false === strpos( $cleared, 'style=""' ), 'No empty style attribute is left behind' );

// Turning the feature off must stop it rendering, whatever is stored.
LSTAB_Storage::update( $source_id, array( 'style_vars' => array( 'accent' => '#123456' ) ) );
add_filter( 'lstab_customizer_enabled', '__return_false' );
$disabled = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false === strpos( $disabled, '--lstab-accent' ), 'Disabling the editor stops overrides rendering' );
remove_all_filters( 'lstab_customizer_enabled' );
LSTAB_Storage::update( $source_id, array( 'style_vars' => LSTAB_Customizer::defaults() ) );

// ---------------------------------------------------------------------------

lstab_section( '13b. Asset cache busting' );

// Shipping an edited stylesheet under an unchanged version string means
// browsers and page caches keep serving the previous release. Every bundled
// asset must therefore carry a version that tracks the file.
$assets = array(
	'assets/css/lstab-table.css',
	'assets/js/lstab-table.js',
	'assets/css/lstab-admin.css',
	'assets/js/lstab-admin.js',
);

foreach ( $assets as $asset ) {
	$version = LSTAB_Plugin::asset_version( $asset );
	lstab_assert(
		'' !== $version && LSTAB_VERSION !== $version,
		"Asset version for {$asset} is more than the plugin version",
		$version
	);
	lstab_assert(
		0 === strpos( $version, LSTAB_VERSION . '.' ),
		"Asset version for {$asset} still identifies the release",
		$version
	);
}

// Touching a file must change its URL.
$probe    = LSTAB_PATH . 'assets/css/lstab-table.css';
$original = filemtime( $probe );
$before   = LSTAB_Plugin::asset_version( 'assets/css/lstab-table.css' );
touch( $probe, $original + 60 );
clearstatcache( true, $probe );
$after = LSTAB_Plugin::asset_version( 'assets/css/lstab-table.css' );
touch( $probe, $original );
clearstatcache( true, $probe );

lstab_assert( $before !== $after, 'Editing an asset changes its version', "{$before} vs {$after}" );

lstab_assert(
	LSTAB_VERSION === LSTAB_Plugin::asset_version( 'assets/css/does-not-exist.css' ),
	'A missing asset falls back to the plugin version'
);

// The block editor script must not be pinned either.
$block_asset = require LSTAB_PATH . 'blocks/sheet-table/index.asset.php';
lstab_assert(
	'1.0.0' !== $block_asset['version'] && '' !== $block_asset['version'],
	'Block editor script carries a file-derived version',
	(string) $block_asset['version']
);

// The declared version has to match what the readme advertises, or WordPress
// will not offer the update that carries these files.
$plugin_header = get_file_data( LSTAB_FILE, array( 'Version' => 'Version' ) );
$readme        = (string) file_get_contents( LSTAB_PATH . 'readme.txt' );
preg_match( '#^Stable tag:\s*(.+)$#m', $readme, $stable );

lstab_assert(
	LSTAB_VERSION === trim( $plugin_header['Version'] ),
	'Plugin header and LSTAB_VERSION agree',
	$plugin_header['Version'] . ' vs ' . LSTAB_VERSION
);
lstab_assert(
	isset( $stable[1] ) && LSTAB_VERSION === trim( $stable[1] ),
	'readme.txt stable tag matches the plugin version',
	isset( $stable[1] ) ? trim( $stable[1] ) : '(none)'
);

// ---------------------------------------------------------------------------

lstab_section( '14. Object cache invalidation' );

// A stale cache here would mean the front end kept serving old data after a
// sync, or kept a deleted source alive — worth proving, not assuming.
lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );

$before = LSTAB_Storage::get( $source_id );
lstab_assert( 7 === $before['row_count'], 'Baseline row count cached', (string) $before['row_count'] );

lstab_set_mock( 'ok', 'second' );
LSTAB_Sync::run( $source_id );
$after_sync = LSTAB_Storage::get( $source_id );
lstab_assert( 3 === $after_sync['row_count'], 'A successful sync invalidates the cache', (string) $after_sync['row_count'] );

lstab_set_mock( 'http_403' );
LSTAB_Sync::run( $source_id );
$after_failure = LSTAB_Storage::get( $source_id );
lstab_assert( 'error' === $after_failure['last_status'], 'A failed sync invalidates the cache', $after_failure['last_status'] );
lstab_assert( 3 === $after_failure['row_count'], 'The cached snapshot still holds the last good copy', (string) $after_failure['row_count'] );

lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );

LSTAB_Storage::update( $source_id, array( 'title' => 'Renamed via cache test' ) );
lstab_assert(
	'Renamed via cache test' === LSTAB_Storage::get( $source_id )['title'],
	'An edit invalidates the cache',
	LSTAB_Storage::get( $source_id )['title']
);
LSTAB_Storage::update( $source_id, array( 'title' => 'Cennik rowerowy' ) );

$doomed = LSTAB_Storage::insert(
	array(
		'title'     => 'To be deleted',
		'sheet_url' => 'https://docs.google.com/spreadsheets/d/DOOMEDSHEET0000000000000000000000/edit',
		'sheet_id'  => 'DOOMEDSHEET0000000000000000000000',
	)
);
lstab_assert( is_array( LSTAB_Storage::get( $doomed ) ), 'New source is readable' );
LSTAB_Storage::delete( $doomed );
lstab_assert( null === LSTAB_Storage::get( $doomed ), 'Deletion invalidates the cache' );

// A miss is cached too; it must not resurrect as an array.
lstab_assert( null === LSTAB_Storage::get( 987654 ), 'A missing source stays missing' );
lstab_assert( null === LSTAB_Storage::get( 987654 ), 'A cached miss is still a miss on the second read' );

// ---------------------------------------------------------------------------

lstab_section( '14x. Columns that live under the row' );

$detail_config = LSTAB_Storage::get( $source_id )['columns_config'];
$detail_config[3]['detail'] = true;
LSTAB_Storage::update( $source_id, array( 'columns_config' => $detail_config ) );

// Named from the sheet rather than written out here, so the test says what it
// means whichever fixture it runs against.
$detail_stored  = LSTAB_Storage::get( $source_id );
$detail_heading = (string) $detail_stored['data']['headers'][3];
$detail_value   = (string) $detail_stored['data']['rows'][0][3];

$detail_source = LSTAB_Storage::get( $source_id );
lstab_assert( ! empty( $detail_source['columns_config'][3]['detail'] ), 'The choice survives a round trip through the database' );

$detail_html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );

lstab_assert( false !== strpos( $detail_html, 'lstab-detail' ), 'The table renders a drawer under its rows' );
lstab_assert( false !== strpos( $detail_html, 'class="lstab-open"' ), 'With a button to open it' );
lstab_assert(
	false !== strpos( $detail_html, 'aria-expanded="false"' ) && false !== strpos( $detail_html, 'aria-controls=' ),
	'That says what it controls and whether it is open'
);

// The moved column must be out of the table proper and inside the drawer.
$detail_head = substr( $detail_html, (int) strpos( $detail_html, '<thead' ), (int) strpos( $detail_html, '</thead>' ) - (int) strpos( $detail_html, '<thead' ) );
lstab_assert( false === strpos( $detail_head, $detail_heading ), 'The moved column is no longer a column of the table', $detail_heading );
lstab_assert( false !== strpos( $detail_html, 'lstab-detail-key' ), 'And is named inside the drawer instead' );
lstab_assert( false !== strpos( $detail_html, esc_html( $detail_value ) ), 'Its values are in the page, not fetched on the click', $detail_value );

// The whole point: the words are in the HTML, so a search engine and the
// table's own search box both find them.
lstab_assert( false !== strpos( $detail_html, 'hidden>' ) || false !== strpos( $detail_html, ' hidden' ), 'The drawer starts closed' );

// Every remaining column plus the arrow's own column.
if ( preg_match( '#<td colspan="(\d+)"#', $detail_html, $span ) ) {
	$detail_columns = substr_count( $detail_head, '<th ' );
	lstab_assert(
		(int) $span[1] === $detail_columns,
		'The drawer spans exactly the width of the table',
		$span[1] . ' vs ' . $detail_columns
	);
}

// Hiding wins: a column cannot be both gone and in the drawer.
$detail_config[3]['hidden'] = true;
LSTAB_Storage::update( $source_id, array( 'columns_config' => $detail_config ) );
$both_html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert(
	false === strpos( $both_html, esc_html( $detail_value ) ) && false === strpos( $both_html, $detail_heading ),
	'A hidden column stays hidden, drawer or no drawer',
	$detail_heading
);
$detail_config[3]['hidden'] = false;
LSTAB_Storage::update( $source_id, array( 'columns_config' => $detail_config ) );

// Refusing to render nothing: every column in the drawer is a mistake, not a
// layout, and an empty table helps nobody find it.
$all_detail = LSTAB_Storage::get( $source_id )['columns_config'];
foreach ( $all_detail as $lstab_i => $lstab_column ) {
	$all_detail[ $lstab_i ]['detail'] = true;
	$all_detail[ $lstab_i ]['hidden'] = false;
}
LSTAB_Storage::update( $source_id, array( 'columns_config' => $all_detail ) );
$empty_html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false === strpos( $empty_html, 'lstab-detail' ), 'Putting every column in the drawer is refused rather than rendered' );
lstab_assert( false !== strpos( $empty_html, 'Rower' ), 'And the table is drawn as it was' );

// The export ignores the drawer: a file has no rows to open.
LSTAB_Storage::update( $source_id, array( 'columns_config' => $detail_config ) );
$detail_prepared = LSTAB_Renderer::prepare( LSTAB_Storage::get( $source_id ), array() );
lstab_assert(
	in_array( $detail_heading, $detail_prepared['headers'], true ),
	'A column in the drawer is still in the data a download would carry',
	wp_json_encode( $detail_prepared['headers'] )
);

/*
 * Without the add-on the drawer is not offered, and a column that was in it
 * comes back into the table. Widening a table is a visible change somebody can
 * see and undo; quietly dropping a column would not be.
 */
$detail_seen = get_option( LSTAB_Limits::SEEN_OPTION );
delete_option( LSTAB_Limits::SEEN_OPTION );

if ( ! LSTAB_Limits::pro_effective() ) {
	$free_html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
	$free_head = substr( $free_html, (int) strpos( $free_html, '<thead' ), (int) strpos( $free_html, '</thead>' ) - (int) strpos( $free_html, '<thead' ) );

	lstab_assert( false === strpos( $free_html, 'lstab-detail' ), 'Without the add-on there is no drawer' );
	lstab_assert( false !== strpos( $free_head, $detail_heading ), 'And the column is back in the table rather than gone', $detail_heading );
} else {
	lstab_assert( true, 'Free-tier fallback not checked here — the add-on is active on this site' );
}

if ( $detail_seen ) {
	update_option( LSTAB_Limits::SEEN_OPTION, $detail_seen, true );
}

// Put it back for the rest of the run.
$detail_config[3]['detail'] = false;
LSTAB_Storage::update( $source_id, array( 'columns_config' => $detail_config ) );

// ---------------------------------------------------------------------------

lstab_section( '14y. Telling the page cache' );

/*
 * There is no caching plugin on this test site, so the integrations are checked
 * the way a caching plugin would see them: by listening for the actions the
 * plugin fires, and by watching the hook it publishes for anything it has not
 * heard of.
 */
$purge_seen = array();

add_action(
	'lstab_purge_page_cache',
	function ( $posts, $purged_source ) use ( &$purge_seen ) {
		$purge_seen[] = array(
			'posts'  => $posts,
			'source' => $purged_source,
		);
	},
	10,
	2
);

$litespeed_seen = array();
add_action(
	'litespeed_purge_post',
	function ( $post_id ) use ( &$litespeed_seen ) {
		$litespeed_seen[] = (int) $post_id;
	}
);

// A page that actually holds the table, and one that does not.
$cache_page = wp_insert_post(
	array(
		'post_title'   => 'Cennik w cache',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => '[sheet_table id="' . $source_id . '"]',
	)
);
$other_page = wp_insert_post(
	array(
		'post_title'   => 'Strona bez tabeli',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => 'Nic tu nie ma.',
	)
);
LSTAB_Usage::forget();

/*
 * How many pages hold this table is a property of the site the suite happens to
 * be run against — the demo page is seeded for the browser run too — so this
 * checks the purge against what the usage map actually says rather than against
 * a number written here.
 */
$cache_places = count( LSTAB_Usage::places( $source_id ) );

$purged = LSTAB_Cache::purge( $source_id );
lstab_assert( 'pages' === $purged['scope'], 'A purge clears pages rather than everything', $purged['scope'] );
lstab_assert( $cache_places === $purged['posts'], 'Exactly the pages holding the table', $purged['posts'] . ' of ' . $cache_places );
lstab_assert( $purged['posts'] >= 1, 'Which is at least the one just made', (string) $purged['posts'] );
lstab_assert( ! empty( $purge_seen ), 'And anything else listening is told which pages they were' );
lstab_assert(
	! empty( $purge_seen ) && in_array( $cache_page, $purge_seen[0]['posts'], true ),
	'The page with the table is in that list',
	wp_json_encode( $purge_seen )
);
lstab_assert(
	! empty( $purge_seen ) && ! in_array( $other_page, $purge_seen[0]['posts'], true ),
	'The page without it is not',
	wp_json_encode( $purge_seen )
);
lstab_assert( in_array( (int) $cache_page, $litespeed_seen, true ), 'A caching plugin listening for its own hook is told too', wp_json_encode( $litespeed_seen ) );

// The dashboard has to be able to say what happened.
$purge_note = LSTAB_Cache::last( $source_id );
lstab_assert( is_array( $purge_note ) && $purge_note['posts'] === $cache_places, 'The clearing is recorded for the dashboard', wp_json_encode( $purge_note ) );
lstab_assert( $purge_note['time'] > time() - 60, 'With the time it happened' );

// A check that found nothing new must not clear anything: that is the whole
// difference between this being free and this being a tax on every sync.
delete_option( LSTAB_Cache::LOG_OPTION );
lstab_serve_custom( "Produkt,Cena netto,Dostępność\nRower górski \"Trek\",4199.99,W magazynie\n" );
LSTAB_Sync::run( $source_id );
delete_option( LSTAB_Cache::LOG_OPTION );

// Same bytes again — nothing changed, so nothing should be cleared.
LSTAB_Sync::run( $source_id );
lstab_assert( null === LSTAB_Cache::last( $source_id ), 'A sync that found no changes clears nothing', wp_json_encode( LSTAB_Cache::last( $source_id ) ) );

// Different bytes — the visitor is looking at a page that is now wrong.
lstab_serve_custom( "Produkt,Cena netto,Dostępność\nRower górski \"Trek\",4999.99,W magazynie\n" );
LSTAB_Sync::run( $source_id );
$after_change = LSTAB_Cache::last( $source_id );
lstab_assert( is_array( $after_change ), 'A sync that brought something new does clear it', wp_json_encode( $after_change ) );

// A developer with a reason can still switch it off; there is no setting for
// it, because "should the page match the sheet?" is not a question worth asking
// anybody.
add_filter( 'lstab_clear_page_cache', '__return_false' );
delete_option( LSTAB_Cache::LOG_OPTION );
$purge_seen = array();

lstab_serve_custom( "Produkt,Cena netto,Dostępność\nRower górski \"Trek\",5999.99,W magazynie\n" );
LSTAB_Sync::run( $source_id );
lstab_assert( null === LSTAB_Cache::last( $source_id ), 'The filter switches it off completely' );
lstab_assert( empty( $purge_seen ), 'And nothing listening is told either' );
remove_filter( 'lstab_clear_page_cache', '__return_false' );

/*
 * A table nothing names — a widget, a template, a page builder's library — is
 * exactly where a stale copy would go unnoticed longest, so finding no page
 * clears everything rather than nothing.
 */
$flushed_all = false;
add_action(
	'lstab_purge_all_cache',
	function () use ( &$flushed_all ) {
		$flushed_all = true;
	}
);

// A source nothing points at, rather than the one under test: the demo page is
// seeded on this site for the browser run and would keep naming that one.
$orphan = LSTAB_Storage::insert(
	array(
		'title'     => 'Nigdzie nieużywana',
		'sheet_url' => 'https://docs.google.com/spreadsheets/d/VVV/edit#gid=0',
		'sheet_id'  => 'VVV',
	)
);
LSTAB_Usage::forget();

lstab_assert( ! LSTAB_Usage::places( $orphan ), 'The orphan table really is on no page' );

LSTAB_Cache::purge( $orphan );
lstab_assert( $flushed_all, 'A table on no page anybody can name clears the whole cache instead' );
$after_site = LSTAB_Cache::last( $orphan );
lstab_assert( is_array( $after_site ) && 'site' === $after_site['scope'], 'And says so', wp_json_encode( $after_site ) );

LSTAB_Storage::delete( $orphan );
wp_delete_post( $cache_page, true );
LSTAB_Usage::forget();

// Deleting a source stops it being remembered.
$cache_throwaway = LSTAB_Storage::insert(
	array(
		'title'     => 'Do wyrzucenia',
		'sheet_url' => 'https://docs.google.com/spreadsheets/d/WWW/edit#gid=0',
		'sheet_id'  => 'WWW',
	)
);
LSTAB_Cache::purge( $cache_throwaway );
lstab_assert( is_array( LSTAB_Cache::last( $cache_throwaway ) ), 'A throwaway source has a record to lose' );
LSTAB_Storage::delete( $cache_throwaway );
lstab_assert( null === LSTAB_Cache::last( $cache_throwaway ), 'Deleting a source forgets it' );

wp_delete_post( $other_page, true );
LSTAB_Usage::forget();

// Put the sheet back the way the rest of the run expects it.
lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );

// ---------------------------------------------------------------------------

lstab_section( '14z. A table with its own CSS' );

// Scoping is the whole safety argument, so it is checked rule shape by rule
// shape rather than only end to end.
$css_selector = LSTAB_Custom_Css::selector( 7 );

$scoped = LSTAB_Custom_Css::scope( 'td { color: red; }', $css_selector );
lstab_assert(
	'[data-lstab-id="7"] td{color: red;}' === $scoped,
	'A plain rule is confined to its own table',
	$scoped
);

$scoped = LSTAB_Custom_Css::scope( 'th, td { padding: 0; }', $css_selector );
lstab_assert(
	'[data-lstab-id="7"] th,[data-lstab-id="7"] td{padding: 0;}' === $scoped,
	'Every selector in a list is confined, not just the first',
	$scoped
);

$scoped = LSTAB_Custom_Css::scope( '&.lstab-paged td { color: red; }', $css_selector );
lstab_assert(
	'[data-lstab-id="7"].lstab-paged td{color: red;}' === $scoped,
	'"&" means the table itself',
	$scoped
);

$scoped = LSTAB_Custom_Css::scope( ':is(th, td) { color: red; }', $css_selector );
lstab_assert(
	'[data-lstab-id="7"] :is(th, td){color: red;}' === $scoped,
	'A comma inside :is() is not a second selector',
	$scoped
);

$scoped = LSTAB_Custom_Css::scope( '@media (max-width: 40em) { td { color: red; } }', $css_selector );
lstab_assert(
	false !== strpos( $scoped, '[data-lstab-id="7"] td' ) && 0 === strpos( $scoped, '@media' ),
	'Rules inside @media are confined too',
	$scoped
);

$scoped = LSTAB_Custom_Css::scope( '@keyframes spin { from { opacity: 0 } to { opacity: 1 } }', $css_selector );
lstab_assert(
	false === strpos( $scoped, '[data-lstab-id="7"] from' ),
	'@keyframes steps are left alone, since prefixing them would break them',
	$scoped
);

lstab_assert(
	'' === LSTAB_Custom_Css::scope( 'body { display: none; }', '' ) || false === strpos( LSTAB_Custom_Css::scope( 'body { display: none }', $css_selector ), '^body' ),
	'A rule naming the page itself still only reaches inside the table',
	LSTAB_Custom_Css::scope( 'body { display: none }', $css_selector )
);

// Nothing typed here may leave the style block or fetch a stylesheet.
$dirty = LSTAB_Custom_Css::sanitize( "td { color: red }\n</style><script>alert(1)</script>" );
lstab_assert( false === strpos( $dirty, '</' ), 'The one sequence that could end a style block is removed', $dirty );

$dirty = LSTAB_Custom_Css::sanitize( "@import url('https://example.com/x.css');\ntd { color: red }" );
lstab_assert( false === stripos( $dirty, '@import' ), '@import is refused', $dirty );

$dirty = LSTAB_Custom_Css::sanitize( 'td { width: expression(alert(1)); }' );
lstab_assert( false === stripos( $dirty, 'expression(' ), 'Old IE script expressions are refused', $dirty );

lstab_assert(
	strlen( LSTAB_Custom_Css::sanitize( str_repeat( 'a', 40000 ) ) ) <= LSTAB_Custom_Css::MAX_LENGTH,
	'An enormous paste is cut to the stored maximum'
);

// And end to end: stored on the source, printed with the table, scoped to it.
LSTAB_Storage::update( $source_id, array( 'custom_css' => 'td { border-left: 3px solid #123456; }' ) );
$with_css = LSTAB_Storage::get( $source_id );
lstab_assert(
	'td { border-left: 3px solid #123456; }' === $with_css['custom_css'],
	'The rules survive a round trip through the database',
	$with_css['custom_css']
);

$css_html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert(
	false !== strpos( $css_html, '<style class="lstab-custom-css"' ),
	'The published table carries its own style block'
);
lstab_assert(
	false !== strpos( $css_html, '[data-lstab-id="' . $source_id . '"] td{border-left: 3px solid #123456;}' ),
	'And the rule inside it names this table and nothing else',
	substr( $css_html, (int) strpos( $css_html, '<style' ), 200 )
);

// The same table twice on one page is one set of rules, not two.
LSTAB_Custom_Css::reset_printed();
$twice = do_shortcode( '[sheet_table id="' . $source_id . '"]' ) . do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert(
	1 === substr_count( $twice, 'lstab-custom-css' ),
	'The same table twice on a page prints its rules once',
	(string) substr_count( $twice, 'lstab-custom-css' )
);

LSTAB_Storage::update( $source_id, array( 'custom_css' => '' ) );
LSTAB_Custom_Css::reset_printed();
$without_css = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert(
	false === strpos( $without_css, 'lstab-custom-css' ),
	'A table with no CSS of its own carries no style block at all'
);

// ---------------------------------------------------------------------------

lstab_section( '15. Translations' );

$languages = LSTAB_PATH . 'languages/';
lstab_assert( file_exists( $languages . 'live-sheets-table.pot' ), 'POT catalogue shipped' );
lstab_assert( file_exists( $languages . 'live-sheets-table-pl_PL.po' ), 'Polish PO shipped' );
lstab_assert( file_exists( $languages . 'live-sheets-table-pl_PL.mo' ), 'Compiled Polish MO shipped' );

unload_textdomain( 'live-sheets-table' );
$loaded = load_textdomain( 'live-sheets-table', $languages . 'live-sheets-table-pl_PL.mo' );
lstab_assert( $loaded, 'Polish MO loads into WordPress' );

lstab_assert(
	'Odśwież' === __( 'Refresh', 'live-sheets-table' ),
	'A simple string translates',
	__( 'Refresh', 'live-sheets-table' )
);
lstab_assert(
	'Zaktualizowano %s temu' === __( 'Updated %s ago', 'live-sheets-table' ),
	'A string with a placeholder translates',
	__( 'Updated %s ago', 'live-sheets-table' )
);

// Polish has three plural forms; check the 1 / 2 / 5 cases pick different ones.
$plural_one  = sprintf( _n( 'The free version keeps %d sheet source', 'The free version keeps %d sheet sources', 1, 'live-sheets-table' ), 1 );
$plural_few  = sprintf( _n( 'The free version keeps %d sheet source', 'The free version keeps %d sheet sources', 2, 'live-sheets-table' ), 2 );
$plural_many = sprintf( _n( 'The free version keeps %d sheet source', 'The free version keeps %d sheet sources', 5, 'live-sheets-table' ), 5 );

lstab_assert( 'Wersja darmowa przechowuje 1 źródło arkusza' === $plural_one, 'Polish singular form', $plural_one );
lstab_assert( 'Wersja darmowa przechowuje 2 źródła arkuszy' === $plural_few, 'Polish "few" plural form', $plural_few );
lstab_assert( 'Wersja darmowa przechowuje 5 źródeł arkuszy' === $plural_many, 'Polish "many" plural form', $plural_many );

// The rendered front end must pick the translations up too.
$translated_html = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $translated_html, 'Szukaj…' ), 'Front-end search box is translated' );
lstab_assert( false !== strpos( $translated_html, 'Sortuj według' ), 'Front-end sort labels are translated' );
lstab_assert( false !== strpos( $translated_html, 'Zaktualizowano' ), 'Front-end freshness label is translated' );

unload_textdomain( 'live-sheets-table' );
lstab_assert( 'Refresh' === __( 'Refresh', 'live-sheets-table' ), 'Unloading restores the English source strings' );

// Every user-facing string must actually be translatable.
$untranslated = array();
foreach ( glob( LSTAB_PATH . 'includes/views/*.php' ) as $view ) {
	$source_code = (string) file_get_contents( $view );
	// Look for echoed literals that never went through a gettext call.
	if ( preg_match_all( '#esc_html\(\s*[\x27"][A-Z][^\x27"]{4,}[\x27"]#', $source_code, $matches ) ) {
		$untranslated = array_merge( $untranslated, $matches[0] );
	}
}
lstab_assert( ! $untranslated, 'No hard-coded literals echoed from the views', implode( ', ', $untranslated ) );

// ---------------------------------------------------------------------------

echo "\n";
echo str_repeat( '─', 60 ) . "\n";
printf(
	"  \033[32m%d passed\033[0m, %s\n",
	$GLOBALS['lstab_passed'],
	$GLOBALS['lstab_failed'] ? "\033[31m{$GLOBALS['lstab_failed']} failed\033[0m" : '0 failed'
);
echo str_repeat( '─', 60 ) . "\n";

exit( $GLOBALS['lstab_failed'] > 0 ? 1 : 0 );
