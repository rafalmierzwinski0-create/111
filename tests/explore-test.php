<?php
/**
 * Exploratory test: adversarial sheets and adversarial requests.
 *
 * The end-to-end suite proves the paths the plugin was designed for. This
 * one goes looking for the ones it was not: sheets nobody would write on
 * purpose, and URLs nobody would type by accident.
 *
 * Usage: php tests/explore-test.php /path/to/wordpress
 *
 * @package LiveSheetsTable\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';

if ( ! $wp_root || ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tests/explore-test.php /path/to/wordpress\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1:8089';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$GLOBALS['lstab_passed'] = 0;
$GLOBALS['lstab_failed'] = 0;
$GLOBALS['lstab_notes']  = array();

/**
 * Assert helper.
 *
 * @param bool   $condition Condition.
 * @param string $label     Test name.
 * @param string $detail    Extra context printed on failure.
 * @return bool
 */
function ex_assert( $condition, $label, $detail = '' ) {
	if ( $condition ) {
		$GLOBALS['lstab_passed']++;
		echo "  \033[32mPASS\033[0m  {$label}\n";
		return true;
	}

	$GLOBALS['lstab_failed']++;
	echo "  \033[31mFAIL\033[0m  {$label}\n";
	if ( '' !== $detail ) {
		echo "        " . str_replace( "\n", "\n        ", substr( $detail, 0, 400 ) ) . "\n";
	}
	return false;
}

/**
 * Record something worth a human's attention that is not a pass or a fail.
 *
 * @param string $note Observation.
 * @return void
 */
function ex_note( $note ) {
	$GLOBALS['lstab_notes'][] = $note;
	echo "  \033[33mNOTE\033[0m  {$note}\n";
}

/**
 * Section header.
 *
 * @param string $title Title.
 * @return void
 */
function ex_section( $title ) {
	echo "\n\033[1m{$title}\033[0m\n";
}

/**
 * Serve an arbitrary CSV as the sheet, and sync it.
 *
 * @param int    $id  Source ID.
 * @param string $csv Payload.
 * @return true|WP_Error
 */
function ex_serve( $id, $csv ) {
	file_put_contents( WP_CONTENT_DIR . '/lstab-mock-custom.csv', $csv );
	file_put_contents(
		WP_CONTENT_DIR . '/lstab-mock-state.json',
		wp_json_encode( array( 'mode' => 'custom', 'tab' => 'main' ) )
	);

	return LSTAB_Sync::run( $id );
}

/**
 * Render a source as a fresh page load would.
 *
 * @param int    $id    Source ID.
 * @param string $extra Extra shortcode attributes.
 * @return string
 */
function ex_render( $id, $extra = '' ) {
	LSTAB_Sync::reset_view_budget();

	return do_shortcode( '[sheet_table id="' . $id . '"' . ( $extra ? ' ' . $extra : '' ) . ']' );
}

// ---------------------------------------------------------------------------

LSTAB_Plugin::on_activate();

global $wpdb;
foreach ( LSTAB_Storage::get_all() as $old ) {
	LSTAB_Storage::delete( $old['id'] );
}

$source_id = LSTAB_Storage::insert(
	array(
		'title'         => 'Exploratory',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'      => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind'    => 'doc',
		'gid'           => '0',
		'sync_interval' => 900,
	)
);

if ( is_wp_error( $source_id ) ) {
	fwrite( STDERR, "Could not create the source: " . $source_id->get_error_message() . "\n" );
	exit( 1 );
}

$source_id = (int) $source_id;

// ---------------------------------------------------------------------------

ex_section( 'A. Sheets nobody would write on purpose' );

$shapes = array(
	'A sheet with nothing in it'          => '',
	'Only a heading row'                  => "Produkt,Cena\n",
	'One column, one row'                 => "Produkt\nRower\n",
	'A single cell'                       => "Rower\n",
	'Every cell empty'                    => "A,B,C\n,,\n,,\n",
	'Headings that repeat'                => "Cena,Cena,Cena\n1,2,3\n",
	'Headings that are blank'             => ",,\n1,2,3\n",
	'Trailing blank lines'                => "A,B\n1,2\n\n\n\n",
	'Windows line endings'                => "A,B\r\n1,2\r\n",
	'Old Mac line endings'                => "A,B\r1,2\r",
	'A cell holding a formula error'      => "Produkt,Cena\nRower,#REF!\nKask,#N/A\n",
	'Leading zeros'                       => "Kod,Nazwa\n007,Bond\n0123,Test\n",
	'A very long single value'            => "A,B\n" . str_repeat( 'x', 20000 ) . ",2\n",
	'Fifty columns'                       => implode( ',', array_map( function ( $i ) { return 'C' . $i; }, range( 1, 50 ) ) ) . "\n" . implode( ',', range( 1, 50 ) ) . "\n",
	'A tab character inside a value'      => "A,B\n\"one\ttwo\",2\n",
	'A null byte inside a value'          => "A,B\n\"one" . chr( 0 ) . "two\",2\n",
	'Emoji and combining marks'           => "A,B\n\"🚲 Rower z\u{0301}o\u{0142}ty\",2\n",
	'Right-to-left text'                  => "A,B\nמחיר,2\n",
	'A value that is only whitespace'     => "A,B\n\"   \",2\n",
	'Numbers in six notations'            => "A\n1 215,50\n1,215.50\n-3\n1e6\n0.0\n(500)\n",
);

foreach ( $shapes as $label => $csv ) {
	$result = ex_serve( $source_id, $csv );
	$html   = ex_render( $source_id );
	$fatal  = false !== stripos( $html, 'fatal error' ) || false !== stripos( $html, 'Warning:' ) || false !== stripos( $html, 'Deprecated:' );

	ex_assert( ! $fatal, "{$label}: renders without a PHP error", substr( $html, 0, 300 ) );

	if ( is_wp_error( $result ) ) {
		ex_note( "{$label}: reported as a sync error — " . $result->get_error_message() );
	}
}

// Specific expectations worth stating rather than just "did not crash".
ex_serve( $source_id, "Kod,Nazwa\n007,Bond\n0123,Test\n" );
$zeros = ex_render( $source_id );
ex_assert( false !== strpos( $zeros, '007' ), 'Leading zeros survive to the page, rather than becoming 7', $zeros );

ex_serve( $source_id, "Cena,Cena,Cena\n1,2,3\n" );
$dupes = ex_render( $source_id );
ex_assert( 3 === substr_count( $dupes, '<th ' ), 'Three repeated headings stay three columns', (string) substr_count( $dupes, '<th ' ) );

ex_serve( $source_id, "A,B\n\"one" . chr( 0 ) . "two\",2\n" );
$nul = ex_render( $source_id );
ex_assert( false === strpos( $nul, chr( 0 ) ), 'A null byte never reaches the page', 'null byte present' );
ex_assert( false !== strpos( $nul, 'onetwo' ), 'And the rest of that value survives', $nul );

// Every other control character is dropped at the same edge, for the same
// reason: invisible in Google, invisible in a browser, poison in a feed.
ex_serve( $source_id, "A,B\n\"one" . chr( 1 ) . chr( 27 ) . chr( 127 ) . "two\",2\n" );
$ctrl = ex_render( $source_id );
ex_assert( ! preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $ctrl ), 'No control characters reach the page at all' );

// A tab and a newline are legitimate inside a cell and must survive.
ex_serve( $source_id, "A,B\n\"one\ttwo\nthree\",2\n" );
$kept = LSTAB_Storage::get( $source_id );
ex_assert( false !== strpos( $kept['data']['rows'][0][0], "\t" ), 'A tab inside a cell survives' );
ex_assert( false !== strpos( $kept['data']['rows'][0][0], "\n" ), 'A newline inside a cell survives' );

// ---------------------------------------------------------------------------

ex_section( 'B. Requests nobody would type by accident' );

ex_serve( $source_id, "Produkt,Cena,Kategoria\nRower,4199,Rowery\nKask,349,Akcesoria\nLampka,89,Akcesoria\nBidon,29,Akcesoria\nZamek,215,Akcesoria\n" );
LSTAB_Storage::update( $source_id, array( 'per_page' => 2 ) );

$args = array(
	'page'   => LSTAB_Paging::arg( $source_id, 'page' ),
	'search' => LSTAB_Paging::arg( $source_id, 'q' ),
	'sort'   => LSTAB_Paging::arg( $source_id, 'sort' ),
	'order'  => LSTAB_Paging::arg( $source_id, 'dir' ),
);

// A test that aims at the wrong query argument proves nothing while looking
// like proof, so the names are checked against the real ones first.
ex_assert( "lstab-page-{$source_id}" === $args['page'], 'The tester is aiming at the real paging argument', $args['page'] );

$attacks = array(
	'A page far past the end'       => array( $args['page'] => '999' ),
	'A negative page'               => array( $args['page'] => '-5' ),
	'A page that is not a number'   => array( $args['page'] => 'abc' ),
	'A page that is an array'       => array( $args['page'] => array( 'x' ) ),
	'A sort column out of range'    => array( $args['sort'] => '99' ),
	'A negative sort column'        => array( $args['sort'] => '-1' ),
	'An order that is not asc/desc' => array( $args['order'] => 'DROP TABLE' ),
	'A search holding markup'       => array( $args['search'] => '<script>alert(1)</script>' ),
	'A search holding a quote'      => array( $args['search'] => 'Rower" onmouseover="x' ),
	'A search holding SQL'          => array( $args['search'] => "' OR 1=1 --" ),
	'A search of 5000 characters'   => array( $args['search'] => str_repeat( 'a', 5000 ) ),
	'A search holding a null byte'  => array( $args['search'] => "a" . chr( 0 ) . "b" ),
	'Everything at once'            => array(
		$args['page']   => '-1',
		$args['sort']   => '99',
		$args['order']  => '<b>',
		$args['search'] => '<img src=x onerror=alert(1)>',
	),
);

foreach ( $attacks as $label => $query ) {
	$_GET = $query;
	$html = ex_render( $source_id );
	$_GET = array();

	$broken = false !== stripos( $html, 'fatal error' ) || false !== stripos( $html, 'Warning:' ) || false !== stripos( $html, 'Deprecated:' );
	ex_assert( ! $broken, "{$label}: no PHP error", substr( $html, 0, 300 ) );
	ex_assert(
		false === strpos( $html, '<script>alert' ) && false === strpos( $html, 'onerror=' ) && false === strpos( $html, 'onmouseover="x' ),
		"{$label}: nothing executable reaches the page"
	);
	ex_assert( false !== strpos( $html, '<table' ) || false !== strpos( $html, 'lstab-empty' ), "{$label}: still answers with a table or an honest empty state" );
}

// The search box has to hand back what was typed without handing back markup.
$_GET = array( $args['search'] => '"><script>alert(1)</script>' );
$echoed = ex_render( $source_id );
$_GET   = array();
ex_assert( false === strpos( $echoed, '"><script>' ), 'The search box does not re-emit what was typed as markup' );

// ---------------------------------------------------------------------------

ex_section( 'C. Shortcodes written by hand' );

$shortcodes = array(
	'[sheet_table]'                        => 'no id at all',
	'[sheet_table id=""]'                  => 'an empty id',
	'[sheet_table id="abc"]'               => 'an id that is not a number',
	'[sheet_table id="0"]'                 => 'a zero id',
	'[sheet_table id="-1"]'                => 'a negative id',
	'[sheet_table id="999999"]'            => 'an id that does not exist',
	'[sheet_table id="1 OR 1=1"]'          => 'an id carrying SQL',
	'[sheet_table id="' . $source_id . '" per_page="abc"]' => 'a per_page that is not a number',
	'[sheet_table id="' . $source_id . '" per_page="-5"]'  => 'a negative per_page',
	'[sheet_table id="' . $source_id . '" unknown="x"]'    => 'an attribute that does not exist',
);

foreach ( $shortcodes as $shortcode => $label ) {
	LSTAB_Sync::reset_view_budget();
	$html   = do_shortcode( $shortcode );
	$broken = false !== stripos( $html, 'fatal error' ) || false !== stripos( $html, 'Warning:' ) || false !== stripos( $html, 'Deprecated:' );
	ex_assert( ! $broken, "A shortcode with {$label}: no PHP error", substr( $html, 0, 300 ) );
}

// ---------------------------------------------------------------------------

ex_section( 'D. Two tables, one page' );

LSTAB_Storage::update( $source_id, array( 'per_page' => 2 ) );

$second_id = LSTAB_Storage::insert(
	array(
		'title'         => 'Exploratory second',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'      => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind'    => 'doc',
		'gid'           => '0',
		'sync_interval' => 900,
		'per_page'      => 2,
	)
);
$second_id = (int) $second_id;
ex_serve( $second_id, "Produkt,Cena\nAlfa,1\nBeta,2\nGamma,3\nDelta,4\n" );

// Page two of the first table must not move the second one.
$_GET = array( LSTAB_Paging::arg( $source_id, 'page' ) => '2' );
LSTAB_Sync::reset_view_budget();
$first_html  = do_shortcode( '[sheet_table id="' . $source_id . '"]' );
$second_html = do_shortcode( '[sheet_table id="' . $second_id . '"]' );
$_GET        = array();

ex_assert( false !== strpos( $first_html, 'Lampka' ), 'Paging one table moves that table', $first_html );
ex_assert( false !== strpos( $second_html, 'Alfa' ), 'And leaves the other one on page one', $second_html );

// The same source twice on one page is the case nobody plans for.
$_GET = array( LSTAB_Paging::arg( $source_id, 'page' ) => '2' );
LSTAB_Sync::reset_view_budget();
$twice = do_shortcode( '[sheet_table id="' . $source_id . '"]' ) . do_shortcode( '[sheet_table id="' . $source_id . '"]' );
$_GET  = array();
ex_assert( 2 === substr_count( $twice, '<table' ), 'The same table twice on one page renders twice', (string) substr_count( $twice, '<table' ) );
ex_note( 'The same source twice shares one set of paging links, so both copies turn the page together.' );

LSTAB_Storage::delete( $second_id );
LSTAB_Storage::update( $source_id, array( 'per_page' => 0 ) );

// ---------------------------------------------------------------------------

ex_section( 'E. Sorting a column of mixed things' );

ex_serve( $source_id, "Nazwa,Cena\nA,1 215\nB,349\nC,brak\nD,-40\nE,\nF,1 000 000\n" );
LSTAB_Storage::update( $source_id, array( 'per_page' => 10 ) );

$_GET = array(
	LSTAB_Paging::arg( 'sort', $source_id )  => '1',
	LSTAB_Paging::arg( 'order', $source_id ) => 'asc',
);
$sorted = ex_render( $source_id );
$_GET   = array();

preg_match_all( '/<td[^>]*>(.*?)<\/td>/s', $sorted, $cells );
$values = array();
for ( $i = 1; $i < count( $cells[1] ); $i += 2 ) {
	$values[] = trim( html_entity_decode( wp_strip_all_tags( $cells[1][ $i ] ) ) );
}
ex_assert( ! empty( $values ), 'A mixed column still renders' );

// One "brak" in a price list used to turn every price into text, and text
// sorts 1 000 000 below 1 215 because "1" sorts below "2". The browser sorts
// an unpaged table by comparing each pair, so the server has to agree.
$_GET  = array(
	LSTAB_Paging::arg( $source_id, 'sort' ) => '1',
	LSTAB_Paging::arg( $source_id, 'dir' )  => 'asc',
);
$order = array();
foreach ( LSTAB_Paging::apply( LSTAB_Storage::get( $source_id )['data']['rows'], $source_id, 10 )['rows'] as $row ) {
	$order[] = $row[1];
}
$_GET = array();
ex_assert(
	array( '-40', '349', '1 215', '1 000 000', 'brak', '' ) === $order,
	'Numbers sort as numbers even where the column also holds words',
	wp_json_encode( $order )
);

$_GET = array(
	LSTAB_Paging::arg( $source_id, 'sort' ) => '1',
	LSTAB_Paging::arg( $source_id, 'dir' )  => 'desc',
);
$reversed = array();
foreach ( LSTAB_Paging::apply( LSTAB_Storage::get( $source_id )['data']['rows'], $source_id, 10 )['rows'] as $row ) {
	$reversed[] = $row[1];
}
$_GET = array();
ex_assert( '' === end( $reversed ), 'A blank cell stays at the bottom when the order is reversed', wp_json_encode( $reversed ) );

// ---------------------------------------------------------------------------

ex_section( 'F. The source disappears underneath a page' );

$doomed = (int) LSTAB_Storage::insert(
	array(
		'title'      => 'Doomed',
		'sheet_url'  => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'   => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind' => 'doc',
		'gid'        => '0',
	)
);
ex_serve( $doomed, "A,B\n1,2\n" );
LSTAB_Storage::delete( $doomed );

$gone = ex_render( $doomed );
ex_assert( false === strpos( $gone, '<table' ), 'A deleted source renders no table' );
ex_assert( false === strpos( $gone, 'Fatal' ), 'And no error' );

wp_set_current_user( 0 );
$gone_visitor = ex_render( $doomed );
ex_assert( '' === trim( $gone_visitor ), 'A visitor sees nothing at all where a deleted table was', $gone_visitor );

// ---------------------------------------------------------------------------

ex_section( 'F2. Attacking the CSS field on purpose' );

/*
 * The field's whole safety argument is two sentences: nothing typed here can
 * end the style block, and nothing typed here can reach outside the table. Both
 * are attacked directly rather than demonstrated with a well-behaved example.
 */

$css_sel = LSTAB_Custom_Css::selector( 42 );

/**
 * Whether a stylesheet, once cleaned and scoped, could end its own style block.
 *
 * @param string $css Attempt.
 * @return bool
 */
function ex_escapes_style_block( $css ) {
	$block = LSTAB_Custom_Css::style_tag( 42, $css, false );

	// Everything after the opening tag is what the browser reads as CSS. If
	// "</" appears in it before the tag this code wrote, the block ended early
	// and whatever followed became markup.
	$inside = substr( $block, (int) strpos( $block, '>' ) + 1 );
	$inside = substr( $inside, 0, max( 0, strlen( $inside ) - strlen( '</style>' ) ) );

	return false !== stripos( $inside, '</' );
}

$attacks = array(
	'the plain attempt'                 => 'td{}</style><script>alert(1)</script>',
	'doubling up, so one pass rebuilds it' => 'td{}<</' . '/style><script>alert(1)</script>',
	'three deep'                        => 'td{}<<</' . '//style><img src=x onerror=alert(1)>',
	'hidden in a comment'               => 'td{ /* </' . 'style> */ color: red }',
	'split by a comment'                => 'td{}<' . '/*x*/' . '/style>',
	'hidden in a string'                => 'td{ content: "</' . 'style><script>alert(1)</script>" }',
	'in a selector'                     => '</' . 'style><script>alert(1)</script> td { color: red }',
	'upper case'                        => 'td{}<</' . '/STYLE><SCRIPT>alert(1)</SCRIPT>',
);

foreach ( $attacks as $label => $attempt ) {
	ex_assert( ! ex_escapes_style_block( $attempt ), "Cannot end the style block: {$label}" );
}

// Scope: every rule must carry the table's selector, whatever shape it arrives in.
$escapes = array(
	'a plain rule'            => 'body { display: none }',
	'a closing brace first'   => '} body { display: none }',
	'a brace inside a string' => 'td { content: "}" } body { display: none }',
	'an unknown at-rule'      => '@nonsense { body { display: none } }',
	'a nested media query'    => '@media screen { @media print { body { display: none } } }',
	'a comma-separated list'  => 'td, body { display: none }',
	'a comma inside :not()'   => 'td:not(a, b), body { display: none }',
	'a stray opening brace'   => 'td { color: red } { body { display: none } }',
);

foreach ( $escapes as $label => $attempt ) {
	$scoped = LSTAB_Custom_Css::scope( LSTAB_Custom_Css::sanitize( $attempt ), $css_sel );

	// Every rule in the result has to begin with the table's own selector.
	$loose = false;
	foreach ( explode( '}', $scoped ) as $chunk ) {
		if ( false === strpos( $chunk, '{' ) ) {
			continue;
		}

		$head = trim( substr( $chunk, 0, (int) strpos( $chunk, '{' ) ) );
		$head = trim( $head, '{' );

		if ( '' === $head || '@' === substr( ltrim( $head ), 0, 1 ) ) {
			continue;
		}

		// Split the way the code under test does: a comma inside :not() or
		// :is() belongs to that selector, it does not start a new one.
		$one   = '';
		$paren = 0;
		$list  = array();
		foreach ( str_split( $head ) as $letter ) {
			if ( '(' === $letter ) {
				$paren++;
			} elseif ( ')' === $letter ) {
				$paren = max( 0, $paren - 1 );
			} elseif ( ',' === $letter && 0 === $paren ) {
				$list[] = $one;
				$one    = '';
				continue;
			}
			$one .= $letter;
		}
		$list[] = $one;

		foreach ( $list as $selector_part ) {
			if ( 0 !== strpos( trim( $selector_part ), $css_sel ) ) {
				$loose = true;
			}
		}
	}

	ex_assert( ! $loose, "Cannot reach outside the table: {$label}", $scoped );
}

// A rule with a brace inside a quoted value must survive intact, or the safety
// measure would be quietly corrupting ordinary stylesheets.
$quoted = LSTAB_Custom_Css::scope( 'td::after { content: "}"; color: red }', $css_sel );
ex_assert(
	false !== strpos( $quoted, 'content: "}"' ) && false !== strpos( $quoted, 'color: red' ),
	'A brace inside a quoted value is content, not the end of the rule',
	$quoted
);

$commented = LSTAB_Custom_Css::scope( 'td::after { content: "/* not a comment */" }', $css_sel );
ex_assert(
	false !== strpos( $commented, 'not a comment' ),
	'And "/*" inside a quoted value is not a comment either',
	$commented
);

// Fetching a stylesheet from somewhere else on every page view.
$imported = LSTAB_Custom_Css::sanitize( "@import url(https://example.com/x.css);\ntd{color:red}" );
ex_assert( false === stripos( $imported, 'example.com' ), '@import cannot pull in another site\'s stylesheet', $imported );

// A cut paste must not leave the stored value ending in half a character.
$long = LSTAB_Custom_Css::sanitize( str_repeat( 'ą', LSTAB_Custom_Css::MAX_LENGTH ) );
ex_assert(
	$long === wp_check_invalid_utf8( $long, true ) && '' !== $long,
	'An over-long paste is cut on a character boundary, not mid-letter'
);

// The live preview endpoint takes the selector from the caller, so it is the
// one place a scope could be chosen rather than given.
$css_rest = new WP_REST_Request( 'POST', '/live-sheets-table/v1/scoped-css' );
$css_rest->set_param( 'css', 'td { color: red }' );
$css_rest->set_param( 'selector', 'body' );
wp_set_current_user( 1 );
$css_reply = rest_do_request( $css_rest )->get_data();
ex_assert(
	false === strpos( (string) $css_reply['css'], 'body td' ),
	'The preview endpoint refuses a selector of the caller\'s choosing',
	(string) $css_reply['css']
);

$css_rest = new WP_REST_Request( 'POST', '/live-sheets-table/v1/scoped-css' );
$css_rest->set_param( 'css', 'td { color: red }' );
$css_rest->set_param( 'selector', '[data-lstab-preview="stage"]' );
wp_set_current_user( 0 );
$css_denied = rest_do_request( $css_rest );
ex_assert( $css_denied->is_error(), 'And a visitor cannot call it at all' );
wp_set_current_user( 1 );

// ---------------------------------------------------------------------------

ex_section( 'G. Deactivate, reactivate, and the data is still there' );

ex_serve( $source_id, "Produkt,Cena\nRower,4199\n" );
$before = LSTAB_Storage::get( $source_id );

LSTAB_Plugin::on_deactivate();
ex_assert( ! wp_next_scheduled( LSTAB_Cron::TICK_HOOK ), 'Deactivation clears the schedule' );

LSTAB_Plugin::on_activate();
$after = LSTAB_Storage::get( $source_id );
ex_assert( (bool) wp_next_scheduled( LSTAB_Cron::TICK_HOOK ), 'Reactivation restores it' );
ex_assert( $before['snapshot_hash'] === $after['snapshot_hash'], 'And the stored sheet survived the round trip' );

$html = ex_render( $source_id );
ex_assert( false !== strpos( $html, 'Rower' ), 'The table renders again straight away' );

// ---------------------------------------------------------------------------

echo "\n" . str_repeat( '─', 60 ) . "\n";
printf(
	"  \033[32m%d passed\033[0m, \033[31m%d failed\033[0m, \033[33m%d notes\033[0m\n",
	$GLOBALS['lstab_passed'],
	$GLOBALS['lstab_failed'],
	count( $GLOBALS['lstab_notes'] )
);
echo str_repeat( '─', 60 ) . "\n";

exit( $GLOBALS['lstab_failed'] > 0 ? 1 : 0 );
