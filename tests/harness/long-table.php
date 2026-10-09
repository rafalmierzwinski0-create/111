<?php
/**
 * Draws one very long table, with pages switched off, under a tight memory
 * limit, and says what came out, for tests/e2e-test.php. A separate process
 * for the same reason as big-sheet.php: the limit has to be in place before
 * WordPress starts.
 *
 * Usage: php -d memory_limit=160M tests/harness/long-table.php <wp root> <rows> [fixed]
 *
 * @package LiveSheetsTable\Tests
 */

define( 'WP_USE_THEMES', false );

// A host that will not let WordPress raise the limit for a big job.
if ( isset( $argv[3] ) && 'fixed' === $argv[3] ) {
	define( 'WP_MAX_MEMORY_LIMIT', ini_get( 'memory_limit' ) );
}

require $argv[1] . '/wp-load.php';

add_filter( 'lstab_max_sources', static fn() => 999 );
add_filter( 'lstab_refresh_on_view', '__return_false' );

$count = max( 1, (int) $argv[2] );
$rows  = array();

for ( $i = 0; $i < $count; $i++ ) {
	$rows[] = array( 'SKU-' . $i, 'Trail Bike Ranger ' . $i, 'Bikes', 'Giant', ( 19 + $i ) . '.99', 'In stock', 'Some short note here', 'Supplier A Ltd.' );
}

$id = (int) LSTAB_Storage::insert(
	array(
		'title'     => 'Long table',
		'sheet_url' => 'https://docs.google.com/spreadsheets/d/LONGTABLELONGTABLE000000/edit',
		'sheet_id'  => 'LONGTABLELONGTABLE000000',
		'per_page'  => 0,
	)
);
LSTAB_Storage::record_success(
	$id,
	array(
		'headers' => array( 'SKU', 'Product', 'Category', 'Brand', 'Price', 'Stock', 'Notes', 'Supplier' ),
		'rows'    => $rows,
	)
);
unset( $rows );

wp_set_current_user( 1 );
$html = do_shortcode( '[sheet_table id="' . $id . '"]' );

echo wp_json_encode(
	array(
		'rows'  => substr_count( $html, '<tr role="row" class="lstab-row"' ),
		'note'  => false !== strpos( $html, 'lstab-too-long' ),
		'pages' => false !== strpos( $html, 'lstab-pager' ),
		'limit' => ini_get( 'memory_limit' ),
		'peak'  => round( memory_get_peak_usage() / MB_IN_BYTES ),
	)
);

LSTAB_Storage::delete( $id );
