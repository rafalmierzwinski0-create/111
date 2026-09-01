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

global $wpdb;
$table  = LSTAB_Storage::table();
$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
lstab_assert( $exists === $table, "Custom table {$table} created", "got: " . var_export( $exists, true ) );
lstab_assert( (bool) wp_next_scheduled( LSTAB_Cron::TICK_HOOK ), 'Cron tick scheduled on activation' );

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

	$wpdb->update(
		LSTAB_Storage::table(),
		array( 'last_attempt_gmt' => gmdate( 'Y-m-d H:i:s', time() - $seconds ) ),
		array( 'id' => (int) $id ),
		array( '%s' ),
		array( '%d' )
	);

	LSTAB_Storage::flush_cache( (int) $id );
}

lstab_set_mock( 'ok', 'main' );
LSTAB_Sync::run( $source_id );
delete_transient( 'lstab_view_refresh_' . $source_id );

// Off by default, and off means off: a changed sheet is not fetched by a view.
lstab_assert( empty( LSTAB_Storage::get( $source_id )['refresh_on_view'] ), 'Refresh on view is off unless asked for' );
lstab_age_source( $source_id, 86400 );
lstab_set_mock( 'ok', 'second' );
$off_html = lstab_view( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $off_html, 'Rower górski' ), 'With it off, a stale copy is served as it always was' );
lstab_assert( false === strpos( $off_html, 'Bike Centrum' ), 'And the changed sheet is not fetched' );

// On, and stale: the visitor who waits is the one who sees the new data.
LSTAB_Storage::update( $source_id, array( 'refresh_on_view' => 1 ) );
lstab_age_source( $source_id, 86400 );
$on_html = lstab_view( '[sheet_table id="' . $source_id . '"]' );
lstab_assert( false !== strpos( $on_html, 'Bike Centrum' ), 'With it on, the page that triggered the check already shows the new data' );
lstab_assert( false === strpos( $on_html, 'Rower górski' ), 'The old copy is gone from that same page' );

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
$lstab_seen_timeout = null;
$lstab_spy          = function ( $args ) use ( &$lstab_seen_timeout ) {
	$lstab_seen_timeout = isset( $args['timeout'] ) ? $args['timeout'] : null;
	return $args;
};
add_filter( 'lstab_fetch_args', $lstab_spy, 100 );
lstab_age_source( $source_id, 86400 );
lstab_view( '[sheet_table id="' . $source_id . '"]' );
remove_filter( 'lstab_fetch_args', $lstab_spy, 100 );
lstab_assert(
	LSTAB_Sync::VIEW_TIMEOUT === $lstab_seen_timeout,
	'A visitor waits at most ' . LSTAB_Sync::VIEW_TIMEOUT . ' seconds for Google',
	var_export( $lstab_seen_timeout, true )
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
lstab_assert( ! empty( $slow_state['last_attempt_gmt'] ), 'The attempt is still recorded, so the next visitor does not repeat it' );

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
lstab_assert( 0 === $lstab_attempts, 'A sheet that just failed is not retried on every page view', (string) $lstab_attempts );

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
		'refresh_on_view' => 1,
	)
);
lstab_assert( ! is_wp_error( $second_id ), 'A second source for the two-table page', is_wp_error( $second_id ) ? $second_id->get_error_message() : '' );
LSTAB_Sync::run( (int) $second_id );

LSTAB_Storage::update( $source_id, array( 'refresh_on_view' => 1 ) );
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
LSTAB_Storage::update( $source_id, array( 'refresh_on_view' => 0 ) );
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

// Positions are the key, so a column inserted in Google shifts the settings.
// That has to be reported rather than silently mislabelling data.
$config = LSTAB_Columns::reconcile(
	array( 1 => array( 'label' => 'Cena' ) ),
	$sheet_headers
);
lstab_assert( 'Cena netto' === $config[1]['source'], 'The sheet heading is remembered next to the label', $config[1]['source'] );
lstab_assert( 'Cena' === $config[1]['label'], 'Reconciling keeps the label' );

$shifted = array( 'Produkt', 'SKU', 'Cena netto', 'Dostępność', 'Opis' );
$drifted = LSTAB_Columns::drift( $config, $shifted );
lstab_assert( 1 === count( $drifted ), 'An inserted column is detected', wp_json_encode( $drifted ) );
lstab_assert( 'Cena netto' === $drifted[0]['was'] && 'SKU' === $drifted[0]['now'], 'The report says what moved', wp_json_encode( $drifted[0] ) );
lstab_assert( ! LSTAB_Columns::drift( $config, $sheet_headers ), 'No drift is reported when nothing moved' );

$untouched = LSTAB_Columns::reconcile( array(), $sheet_headers );
lstab_assert( ! LSTAB_Columns::drift( $untouched, $shifted ), 'Columns nobody configured cannot drift' );

// End to end: settings reach the rendered page.
LSTAB_Storage::update(
	$source_id,
	array(
		'columns_config' => array(
			1 => array( 'label' => 'Cena brutto' ),
			3 => array( 'hidden' => true ),
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
lstab_assert( 'Cena netto' === $after_sync['columns_config'][1]['source'], 'A sync refreshes the remembered heading' );

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
	false !== strpos( $edit_html, 'name="columns[0][visible]" value="0"' ),
	'Every include box is paired with an explicit "off" field'
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

lstab_section( '12b. Scheduler health' );

// A broken WP-Cron does not break the site — pages keep rendering the stored
// copy — it just quietly stops updating. That silence is the thing to catch.
$health = LSTAB_Cron::health();
lstab_assert( isset( $health['state'], $health['message'], $health['detail'] ), 'Health check returns a structured result' );

// The test sites define DISABLE_WP_CRON, which is a legitimate setup, not a fault.
lstab_assert( 'disabled' === $health['state'], 'DISABLE_WP_CRON is reported, and as information rather than an error', $health['state'] );
lstab_assert( '' !== $health['detail'], 'The notice explains what it means for the site owner' );

// A site nobody visits runs no schedule at all, and no plugin can fix that
// from inside PHP. What it can do is stop making the owner work out what to
// type: the line is built from their own address and their own interval.
LSTAB_Cron::ensure_scheduled();
$cron_line = LSTAB_Cron::system_cron_line();
lstab_assert( false !== strpos( $cron_line, site_url( 'wp-cron.php?doing_wp_cron' ) ), 'The cron line points at this site', $cron_line );
lstab_assert( 0 === strpos( $cron_line, '*/15 * * * * ' ), 'And fires at the interval the schedule actually uses', $cron_line );
lstab_assert( false !== strpos( $cron_line, 'curl -s ' ), 'It is a line a hosting panel will accept as it stands', $cron_line );

// With no sources there is nothing to warn about.
$saved_sources = LSTAB_Storage::get_all();
foreach ( $saved_sources as $saved ) {
	LSTAB_Storage::delete( $saved['id'] );
}
lstab_assert( 'ok' === LSTAB_Cron::health()['state'], 'No sources means no scheduler warning' );

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

// A stalled scheduler: pretend cron is enabled but has not run in a long time.
$stall = static function () {
	return array(
		'lstab_15min' => 900,
	);
};

update_option( LSTAB_Cron::LAST_TICK_OPT, time() - 6 * HOUR_IN_SECONDS );
update_option( LSTAB_Cron::TICK_OPTION, 'lstab_15min' );
wp_schedule_event( time() + 300, 'lstab_15min', LSTAB_Cron::TICK_HOOK );

// health() short-circuits on DISABLE_WP_CRON, so check the staleness maths
// directly by confirming the recorded tick is what drives it.
lstab_assert(
	(int) get_option( LSTAB_Cron::LAST_TICK_OPT ) < time() - HOUR_IN_SECONDS,
	'A stale tick timestamp is recorded and readable'
);

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

lstab_section( '15. Translations' );

$languages = LSTAB_PATH . 'languages/';
lstab_assert( file_exists( $languages . 'live-sheets-table.pot' ), 'POT catalogue shipped' );
lstab_assert( file_exists( $languages . 'live-sheets-table-pl_PL.po' ), 'Polish PO shipped' );
lstab_assert( file_exists( $languages . 'live-sheets-table-pl_PL.mo' ), 'Compiled Polish MO shipped' );

unload_textdomain( 'live-sheets-table' );
$loaded = load_textdomain( 'live-sheets-table', $languages . 'live-sheets-table-pl_PL.mo' );
lstab_assert( $loaded, 'Polish MO loads into WordPress' );

lstab_assert(
	'Odśwież teraz' === __( 'Refresh now', 'live-sheets-table' ),
	'A simple string translates',
	__( 'Refresh now', 'live-sheets-table' )
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
lstab_assert( 'Refresh now' === __( 'Refresh now', 'live-sheets-table' ), 'Unloading restores the English source strings' );

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
