<?php
/**
 * Every awkward character a sheet can hold, through every feature.
 *
 * Headings and values with line breaks, double and non-breaking spaces,
 * quotes, ampersands, "<" and ">", square brackets, percent signs, pipes,
 * commas, backslashes, emoji, Polish letters, things shaped like tags and
 * formulas, and text that is literally "&amp;". Each column is renamed,
 * hidden, coloured by a rule, offered under "Show only", given a look,
 * filtered by name in the block and in a shortcode, searched, sorted and
 * downloaded, and every one of those has to work on every column — or, where
 * WordPress itself stands in the way, say what to do instead.
 *
 * Nobody writes a sheet like this on purpose. Somebody's sheet holds one or
 * two of these, which is enough to break one feature for them, quietly.
 *
 * Usage: php tests/characters-test.php /path/to/wordpress   (the add-on active,
 * servers from tests/setup-env.sh running, since downloads go over HTTP)
 *
 * @package LiveSheetsTable\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.WP.AlternativeFunctions

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';

if ( ! $wp_root || ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tests/characters-test.php /path/to/wordpress\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

define( 'WP_USE_THEMES', false );
require $wp_root . '/wp-load.php';

$GLOBALS['ch_passed'] = 0;
$GLOBALS['ch_failed'] = 0;

function ch_assert( $condition, $label, $detail = '' ) {
	if ( $condition ) {
		$GLOBALS['ch_passed']++;
		echo "  \033[32mPASS\033[0m  {$label}\n";
		return;
	}

	$GLOBALS['ch_failed']++;
	echo "  \033[31mFAIL\033[0m  {$label}\n";

	if ( '' !== $detail ) {
		echo '        ' . str_replace( "\n", "\n        ", $detail ) . "\n";
	}
}

function ch_json( $value ) {
	return (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

if ( ! class_exists( 'LSTABP_Filters' ) ) {
	fwrite( STDERR, "The add-on has to be active for this suite.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
add_filter( 'lstab_max_sources', static fn() => 999 );

$headings = array(
	'Product',
	"Price\n(net)",
	'Weight  (kg)',
	'Size <M>',
	'Price [PLN]',
	'Growth 100%AB',
	'"Quoted" & \'apos\'',
	'Zażółć gęślą jaźń',
	'Bike 🚲',
	"Tab\there",
	'a|b, c',
	'=SUM(A1)',
	'C:\\path\\to',
	"No\u{00A0}break",
	'&amp; literal',
	'Rating >= 4',
	'Is it? not',
);

$values = array(
	'<18', '18-25', 'A & B', "Kids' helmet", '"quoted"', 'Size <M>', '50%', '100%AB', 'Zażółć', '🚲 bike',
	"two\nlines", 'double  space', 'pipe|value', 'comma, value', '=1+1', '+48 600 700 800', '02134', '1 215,50',
	'C:\\dir', "nb\u{00A0}sp", '&amp;', '<b>bold</b>', '[x]', '{y}', 'a > b', 'is not', 'has has',
);

// A shortcode cannot carry these at all; WordPress ends the shortcode at "]"
// and takes backslashes out. The columns are reached by a plain name instead.
$no_shortcode = array( 4, 12 );

$count  = count( $values );
$rows   = array();
foreach ( range( 0, 3 ) as $r ) {
	$row = array();
	foreach ( $headings as $c => $heading ) {
		$row[] = 2 === $r ? 'plain' : ( 1 === $r ? $values[ ( $c + 7 ) % $count ] : $values[ $c % $count ] );
	}
	$rows[] = $row;
}
$target = static function ( $c ) use ( $values, $count ) {
	return $values[ $c % $count ];
};

$quote = static function ( $value ) {
	return preg_match( '/[",\n\r]/', $value ) ? '"' . str_replace( '"', '""', $value ) . '"' : $value;
};
$csv   = implode( ',', array_map( $quote, $headings ) ) . "\n";
foreach ( $rows as $row ) {
	$csv .= implode( ',', array_map( $quote, $row ) ) . "\n";
}
file_put_contents( WP_CONTENT_DIR . '/lstab-mock-custom.csv', $csv );
file_put_contents( WP_CONTENT_DIR . '/lstab-mock-state.json', wp_json_encode( array( 'mode' => 'custom', 'tab' => 'main' ) ) );

$id = (int) LSTAB_Storage::insert(
	array(
		'title'         => 'Characters',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/CHARACTERS0000000000000000/edit',
		'sheet_id'      => 'CHARACTERS0000000000000000',
		'sync_interval' => 86400,
	)
);
$synced = LSTAB_Sync::run( $id );
$stored = LSTAB_Storage::get( $id );
$header = (array) $stored['data']['headers'];

// The headings a dashboard card offers, and saves: WordPress's cleaned text.
$offered = array_map( 'sanitize_text_field', array_map( 'strval', $header ) );

$rows_in = static function ( $html ) {
	return substr_count( $html, '<tr role="row" class="lstab-row"' );
};
$view    = static function ( $shortcode ) {
	LSTAB_Sync::reset_view_budget();
	return do_shortcode( $shortcode );
};
$dom     = static function ( $html ) {
	$document = new DOMDocument();
	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	return new DOMXPath( $document );
};
$matching = static function ( $c, $value ) use ( $rows ) {
	$found = 0;
	foreach ( $rows as $row ) {
		if ( LSTABP_Filters::key( $row[ $c ] ) === LSTABP_Filters::key( $value ) ) {
			$found++;
		}
	}
	return $found;
};
$named   = static function ( $c ) use ( $headings ) {
	return 'column ' . $c . ' ' . ch_json( $headings[ $c ] );
};

echo "\n\033[1m1. Reading the sheet\033[0m\n";

ch_assert( true === $synced, 'A sheet full of awkward characters syncs', is_wp_error( $synced ) ? $synced->get_error_message() : '' );
ch_assert( array_map( 'trim', $headings ) === $header, 'Every heading is kept as the sheet wrote it', ch_json( $header ) );
ch_assert( $rows === $stored['data']['rows'], 'And every value' );

echo "\n\033[1m2. Showing it\033[0m\n";

$plain = $view( '[sheet_table id="' . $id . '"]' );
$xpath = $dom( $plain );
$wrong = array();
foreach ( $xpath->query( '//tbody/tr[contains(@class,"lstab-row")]' ) as $r => $tr ) {
	foreach ( $xpath->query( './td/span[@class="lstab-cell-value"]', $tr ) as $c => $span ) {
		if ( $span->textContent !== $rows[ $r ][ $c ] ) {
			$wrong[] = $named( $c ) . ' shows ' . ch_json( $span->textContent ) . ' for ' . ch_json( $rows[ $r ][ $c ] );
		}
	}
}
ch_assert( ! $wrong, 'Every cell reads exactly as in the sheet, "&amp;" and "<b>" included', implode( "\n", $wrong ) );

$wrong = array();
foreach ( $xpath->query( '//thead//th' ) as $c => $th ) {
	if ( trim( preg_replace( '/\s+/u', ' ', $th->textContent ) ) !== trim( preg_replace( '/\s+/u', ' ', $header[ $c ] ) ) ) {
		$wrong[] = $named( $c ) . ' shows ' . ch_json( trim( $th->textContent ) );
	}
}
ch_assert( ! $wrong, 'And every heading', implode( "\n", $wrong ) );
ch_assert( false === strpos( $plain, '<b>bold' ) && false === strpos( $plain, '<M>' ), 'Nothing shaped like a tag reaches the page as a tag' );

echo "\n\033[1m3. Renaming and hiding\033[0m\n";

$config  = LSTAB_Columns::reconcile( array(), $header );
$renamed = array();
$hidden  = array();
foreach ( $headings as $c => $heading ) {
	$set              = $config;
	$set[ $c ]['label'] = 'Renamed ' . $c;
	LSTAB_Storage::update( $id, array( 'columns_config' => LSTAB_Columns::sanitize( $set ) ) );
	if ( false === strpos( $view( '[sheet_table id="' . $id . '"]' ), 'Renamed ' . $c ) ) {
		$renamed[] = $named( $c );
	}

	$set                 = $config;
	$set[ $c ]['hidden'] = true;
	LSTAB_Storage::update( $id, array( 'columns_config' => LSTAB_Columns::sanitize( $set ) ) );
	$shown = substr_count( $view( '[sheet_table id="' . $id . '"]' ), 'role="columnheader"' );
	if ( count( $headings ) - 1 !== $shown ) {
		$hidden[] = $named( $c ) . ": {$shown} headings";
	}
}
LSTAB_Storage::update( $id, array( 'columns_config' => $config ) );
ch_assert( ! $renamed, 'Every column can be renamed', implode( "\n", $renamed ) );
ch_assert( ! $hidden, 'Every column can be hidden, and stays hidden', implode( "\n", $hidden ) );

echo "\n\033[1m4. Colour rules, \"Show only\", column looks\033[0m\n";

$ruled  = array();
$menus  = array();
$looked = array();
foreach ( $headings as $c => $heading ) {
	$want = $matching( $c, $target( $c ) );

	update_option( LSTABP_Rules::OPTION, array( $id => LSTABP_Rules::sanitize( array( array( 'column' => $offered[ $c ], 'operator' => '=', 'value' => $target( $c ), 'style' => '#fbd5d5', 'scope' => 'cell' ) ) ) ), false );
	$painted = substr_count( $view( '[sheet_table id="' . $id . '"]' ), '--lstab-row-tint:#fbd5d5' );
	if ( $painted !== $want ) {
		$ruled[] = $named( $c ) . ", value " . ch_json( $target( $c ) ) . ": {$painted} cells, want {$want}";
	}
	delete_option( LSTABP_Rules::OPTION );

	// The menu's own link is followed, the way a visitor's click would.
	update_option( LSTABP_Facets::OPTION, array( $id => array( $offered[ $c ] ) ), false );
	$menu = $dom( $view( '[sheet_table id="' . $id . '"]' ) );
	$link = null;
	foreach ( $menu->query( '//a[contains(@class,"lstabp-facet-value")]' ) as $a ) {
		$text = $menu->query( './/span[@class="lstabp-facet-text"]', $a )->item( 0 )->textContent;
		if ( LSTABP_Filters::key( $text ) === LSTABP_Filters::key( $target( $c ) ) ) {
			$link = $a->getAttribute( 'href' );
		}
	}
	if ( null === $link ) {
		$menus[] = $named( $c ) . ': ' . ch_json( $target( $c ) ) . ' is not in the menu';
	} else {
		$picked = array();
		parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $picked );
		$_GET = wp_slash( $picked );
		$got  = $rows_in( $view( '[sheet_table id="' . $id . '"]' ) );
		$_GET = array();
		if ( $got !== $want ) {
			$menus[] = $named( $c ) . ', picking ' . ch_json( $target( $c ) ) . ": {$got} rows, want {$want}";
		}
	}
	delete_option( LSTABP_Facets::OPTION );

	update_option( LSTABP_Column_Looks::OPTION, array( $id => LSTABP_Column_Looks::sanitize( array( $offered[ $c ] => array( 'look' => 'pill', 'tint' => '#5fe3cf' ) ) ) ), false );
	$pills = substr_count( $view( '[sheet_table id="' . $id . '"]' ), 'lstabp-pill' );
	if ( $pills < 4 ) {
		$looked[] = $named( $c ) . ": {$pills} pills";
	}
	delete_option( LSTABP_Column_Looks::OPTION );
}
ch_assert( ! $ruled, 'A colour rule finds its cells in every column', implode( "\n", $ruled ) );
ch_assert( ! $menus, 'Every "Show only" menu offers its values, and picking one leaves those rows', implode( "\n", $menus ) );
ch_assert( ! $looked, 'A column look reaches every column', implode( "\n", $looked ) );

echo "\n\033[1m5. Written filters\033[0m\n";

$in_block     = array();
$in_shortcode = array();
foreach ( $headings as $c => $heading ) {
	$want  = $matching( $c, $target( $c ) );
	$name  = trim( preg_replace( '/\s+/u', ' ', $offered[ $c ] ) );
	$typed = $name . ' is ' . $target( $c );

	$got = $rows_in( ( new LSTAB_Block() )->render( array( 'sourceId' => $id, 'filter' => $typed ) ) );
	if ( $got !== $want ) {
		$in_block[] = $named( $c ) . ', ' . ch_json( $typed ) . ": {$got} rows, want {$want}";
	}

	if ( in_array( $c, $no_shortcode, true ) ) {
		continue;
	}

	$got = $rows_in( $view( '[sheet_table id="' . $id . '" filter="' . esc_attr( $typed ) . '"]' ) );
	if ( $got !== $want ) {
		$in_shortcode[] = $named( $c ) . ', ' . ch_json( $typed ) . ": {$got} rows, want {$want}";
	}
}
ch_assert( ! $in_block, 'A filter typed in the block finds its rows by any column, as the column reads on one line', implode( "\n", $in_block ) );
ch_assert( ! $in_shortcode, 'And the same filter in a shortcode, as an editor stores it', implode( "\n", $in_shortcode ) );

$set              = $config;
$set[4]['label']  = 'Price PLN';
$set[12]['label'] = 'Path';
LSTAB_Storage::update( $id, array( 'columns_config' => LSTAB_Columns::sanitize( $set ) ) );
$by_plain_name = $rows_in( $view( '[sheet_table id="' . $id . '" filter="Price PLN is ' . esc_attr( $target( 4 ) ) . ', Path is ' . esc_attr( $target( 12 ) ) . '"]' ) );
LSTAB_Storage::update( $id, array( 'columns_config' => $config ) );
ch_assert( 2 === $by_plain_name, 'A column with "[ ]" or "\\" in its name is reached in a shortcode by a plain name given to it', (string) $by_plain_name );

/*
 * On a real page WordPress turns the "<" and the closing quote of a shortcode
 * into &lt; and &quot; before reading it, so the filter is lost and every row
 * came through. A published page is asked for over HTTP, as a visitor would.
 */
$lost_page = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Characters lost filter',
		'post_content' => '[sheet_table id="' . $id . '" filter="Product is <18"]',
	)
);
$lost_html = (string) wp_remote_retrieve_body( wp_remote_get( get_permalink( $lost_page ), array( 'timeout' => 30 ) ) );
wp_delete_post( $lost_page, true );
ch_assert( 0 === $rows_in( $lost_html ), 'A shortcode whose filter WordPress could not read shows a visitor no rows, rather than every row', (string) $rows_in( $lost_html ) );

wp_set_current_user( 1 );
$lost_note = $view( '[sheet_table id="' . $id . '" filter="Product is &lt;18&quot;]' );
$curly     = $view( '[sheet_table id=”' . $id . '”]' );
wp_set_current_user( 0 );
ch_assert( false !== strpos( $lost_note, 'lt instead of' ), 'And tells the site owner how to write it', wp_strip_all_tags( $lost_note ) );
ch_assert( false !== strpos( $curly, 'curly' ), 'Curly quotes pasted from a document are named as the reason a table is missing', wp_strip_all_tags( $curly ) );
ch_assert( 2 === $rows_in( $view( '[sheet_table id="' . $id . '" filter="Product is &lt;18"]' ) ), 'A "<" written as &lt; still works' );

echo "\n\033[1m6. Searching and sorting a table with pages\033[0m\n";

LSTAB_Storage::update( $id, array( 'per_page' => 10 ) );
$searched = array();
$marked   = array();
$sorted   = array();
foreach ( $headings as $c => $heading ) {
	$typed = trim( preg_replace( '/\s+/u', ' ', $target( $c ) ) );
	$want  = $matching( $c, $target( $c ) );

	$_GET = array( LSTAB_Paging::arg( $id, 'q' ) => wp_slash( $typed ) );
	$html = $view( '[sheet_table id="' . $id . '"]' );
	$_GET = array();
	if ( $rows_in( $html ) < $want ) {
		$searched[] = $named( $c ) . ', searching ' . ch_json( $typed ) . ': ' . $rows_in( $html ) . " rows, want at least {$want}";
	}

	$found = $dom( $html );
	foreach ( $found->query( '//tbody/tr[contains(@class,"lstab-row")]' ) as $tr ) {
		$cells = $found->query( './td/span[@class="lstab-cell-value"]', $tr );
		foreach ( $rows as $row ) {
			if ( $cells->item( 0 ) && $cells->item( 0 )->textContent === $row[0] ) {
				foreach ( $cells as $i => $span ) {
					if ( $span->textContent !== $row[ $i ] ) {
						$marked[] = 'searching ' . ch_json( $typed ) . ': ' . $named( $i ) . ' reads ' . ch_json( $span->textContent );
					}
				}
			}
		}
	}

	$_GET = array(
		LSTAB_Paging::arg( $id, 'sort' ) => (string) $c,
		LSTAB_Paging::arg( $id, 'dir' )  => 'desc',
	);
	$got  = $rows_in( $view( '[sheet_table id="' . $id . '"]' ) );
	$_GET = array();
	if ( 4 !== $got ) {
		$sorted[] = $named( $c ) . ": {$got} rows";
	}
}
LSTAB_Storage::update( $id, array( 'per_page' => 0 ) );
ch_assert( ! $searched, 'Searching finds every value, across a line break or a double space too', implode( "\n", $searched ) );
ch_assert( ! $marked, 'And marking what it found leaves every cell reading as written', implode( "\n", array_unique( $marked ) ) );
ch_assert( ! $sorted, 'Sorting by any column keeps every row', implode( "\n", $sorted ) );

echo "\n\033[1m7. Downloads\033[0m\n";

update_option( LSTABP_Export::OPTION, array( $id => true ), true );
$offer = $view( '[sheet_table id="' . $id . '"]' );
preg_match( '#href="([^"]*format=csv[^"]*)"#', $offer, $csv_link );
preg_match( '#href="([^"]*format=xlsx[^"]*)"#', $offer, $xlsx_link );

$defused = static function ( $value ) {
	return ( '' !== $value && preg_match( '/^[=+\-@\t\r]/', $value ) && ! LSTAB_Renderer::looks_numeric( $value ) ) ? "'" . $value : $value;
};

$response = isset( $csv_link[1] ) ? wp_remote_get( html_entity_decode( $csv_link[1] ), array( 'timeout' => 30 ) ) : null;
ch_assert( $response && 200 === (int) wp_remote_retrieve_response_code( $response ), 'The CSV download is served', $response ? (string) wp_remote_retrieve_response_code( $response ) : 'no link' );
$handle = fopen( 'php://memory', 'w+' );
fwrite( $handle, preg_replace( '/^\xEF\xBB\xBF/', '', (string) wp_remote_retrieve_body( $response ) ) );
rewind( $handle );
$lines = array();
while ( false !== ( $line = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
	$lines[] = $line;
}
fclose( $handle );
$wrong = array();
foreach ( array_merge( array( $header ), $rows ) as $r => $row ) {
	foreach ( $row as $c => $value ) {
		if ( ( isset( $lines[ $r ][ $c ] ) ? $lines[ $r ][ $c ] : null ) !== $defused( $value ) ) {
			$wrong[] = "line {$r}, " . $named( $c ) . ': ' . ch_json( isset( $lines[ $r ][ $c ] ) ? $lines[ $r ][ $c ] : null ) . ' for ' . ch_json( $value );
		}
	}
}
ch_assert( ! $wrong, 'It holds every heading and value exactly, with formulas made harmless', implode( "\n", $wrong ) );

$response = isset( $xlsx_link[1] ) ? wp_remote_get( html_entity_decode( $xlsx_link[1] ), array( 'timeout' => 30 ) ) : null;
ch_assert( $response && 200 === (int) wp_remote_retrieve_response_code( $response ), 'The Excel download is served', $response ? (string) wp_remote_retrieve_response_code( $response ) : 'no link' );
$file = tempnam( get_temp_dir(), 'ch-xlsx' );
file_put_contents( $file, (string) wp_remote_retrieve_body( $response ) );
$zip = new ZipArchive();
$zip->open( $file );
$sheet = simplexml_load_string( (string) $zip->getFromName( 'xl/worksheets/sheet1.xml' ) );
$zip->close();
unlink( $file );
$grid = array();
foreach ( $sheet ? $sheet->sheetData->row : array() as $xml_row ) {
	foreach ( $xml_row->c as $xml_cell ) {
		preg_match( '/^([A-Z]+)(\d+)$/', (string) $xml_cell['r'], $place );
		$column = 0;
		foreach ( str_split( $place[1] ) as $letter ) {
			$column = $column * 26 + ( ord( $letter ) - 64 );
		}
		$grid[ (int) $place[2] - 1 ][ $column - 1 ] = isset( $xml_cell->is ) ? (string) $xml_cell->is->t : array( (string) $xml_cell->v );
	}
}
$wrong = array();
foreach ( array_merge( array( $header ), $rows ) as $r => $row ) {
	foreach ( $row as $c => $value ) {
		$got = isset( $grid[ $r ][ $c ] ) ? $grid[ $r ][ $c ] : '';
		$ok  = is_array( $got )
			? abs( (float) $got[0] - LSTAB_Renderer::to_number( $value ) ) < 1e-9
			: $got === preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );
		if ( ! $ok ) {
			$wrong[] = "line {$r}, " . $named( $c ) . ': ' . ch_json( $got ) . ' for ' . ch_json( $value );
		}
	}
}
ch_assert( $sheet && ! $wrong, 'The Excel file holds every heading and value exactly, as text or as the same number', implode( "\n", $wrong ) );
delete_option( LSTABP_Export::OPTION );

LSTAB_Storage::delete( $id );
file_put_contents( WP_CONTENT_DIR . '/lstab-mock-state.json', wp_json_encode( array( 'mode' => 'ok', 'tab' => 'main' ) ) );

echo "\n" . str_repeat( '─', 60 ) . "\n";
printf(
	"  \033[32m%d passed\033[0m, %s\n",
	$GLOBALS['ch_passed'],
	$GLOBALS['ch_failed'] ? "\033[31m{$GLOBALS['ch_failed']} failed\033[0m" : '0 failed'
);
echo str_repeat( '─', 60 ) . "\n";

exit( $GLOBALS['ch_failed'] > 0 ? 1 : 0 );
