<?php
/**
 * Builds the pages the layout sweep reads: six shapes of sheet, each pinned and
 * not, drawn in every style and in all three layouts.
 *
 * Usage: php tests/harness/layout-sweep-setup.php <wp root> [teardown]
 */
define( 'WP_USE_THEMES', false );
require $argv[1] . '/wp-load.php';
$manifest = dirname( $argv[1] ) . '/layout-sweep.json';

if ( isset( $argv[2] ) && 'teardown' === $argv[2] ) {
	$m = json_decode( (string) @file_get_contents( $manifest ), true );
	foreach ( (array) ( $m['sources'] ?? array() ) as $id ) { LSTAB_Storage::delete( (int) $id ); }
	foreach ( (array) ( $m['pages'] ?? array() ) as $p ) { wp_delete_post( (int) $p['id'], true ); }
	@unlink( $manifest );
	echo "torn down\n";
	exit;
}

add_filter( 'lstab_max_sources', fn() => 999 );

$products = array( 'Trail Bike Ranger 29"', 'Lazer Compact Helmet', 'Lezyne Front Light 800', 'Insulated Bottle 750 ml', 'Pro Gel Gloves', 'Abus Granit Combo Lock', 'Topeak Rear Rack', 'Road Bike Contend AR', 'Garmin Edge 540', 'Lezyne Floor Pump', 'Gore Rain Jacket', 'Shimano Pedals PD-M520' );
$shapes = array();

$rows = array();
foreach ( range( 0, 7 ) as $i ) { $rows[] = array( $products[ $i ], '$' . number_format( 19 + $i * 37.5, 2 ), $i % 3 ? 'In stock' : 'Out of stock' ); }
$shapes['narrow'] = array( array( 'Product', 'Price', 'Stock' ), $rows, array() );

$h = array( 'SKU', 'Product name', 'Category', 'Brand', 'Colour', 'Size', 'Weight (kg)', 'Price (USD)', 'Discount', 'Stock', 'Warehouse', 'Supplier', 'Last updated', 'Notes' );
$rows = array();
foreach ( range( 0, 29 ) as $i ) {
	$rows[] = array( 'SKU-' . ( 10400 + $i * 7 ), $products[ $i % 12 ], array( 'Bikes', 'Helmets', 'Lights', 'Accessories' )[ $i % 4 ], array( 'Giant', 'Lazer', 'Lezyne', 'Abus', 'Topeak' )[ $i % 5 ], array( 'Black', 'Red', 'Matte grey', 'Blue' )[ $i % 4 ], array( 'S', 'M', 'L', 'XL', 'One size' )[ $i % 5 ], number_format( 0.2 + $i * 0.37, 2 ), number_format( 9.99 + $i * 41.3, 2 ), ( $i % 4 ) * 5 . '%', (string) ( 120 - $i * 4 ), array( 'Warsaw', 'Gdańsk', 'Kraków' )[ $i % 3 ], 'Supplier ' . chr( 65 + $i % 6 ) . ' Ltd.', '2026-0' . ( 1 + $i % 9 ) . '-1' . ( $i % 9 ), $i % 5 ? '' : 'Back in stock next week' );
}
$shapes['wide'] = array( $h, $rows, array() );

$long = 'This frame is built from hydroformed aluminium tubing with internal cable routing, a tapered head tube and room for 2.6 inch tyres, which makes it equally happy on forest singletrack and long gravel roads.';
$rows = array();
foreach ( range( 0, 5 ) as $i ) {
	$rows[] = array( $products[ $i ], $i % 2 ? $long : substr( $long, 0, 60 ), 'https://www.example-bike-shop.com/products/' . strtolower( str_replace( ' ', '-', $products[ $i ] ) ) . '?utm_source=sheet&utm_medium=table&utm_campaign=spring', 'customer.service.department@example-bike-shop.com', 'Pneumonoultramicroscopicsilicovolcanoconiosis-' . $i . '-ABCDEFGHIJKLMNOPQRSTUVWXYZ' );
}
$shapes['wordy'] = array( array( 'Name', 'Description', 'Website', 'Contact e-mail', 'A very long heading for a column that goes on and on without stopping' ), $rows, array() );

$h = array( 'Product', 'Price', 'Stock', 'Category', 'Description', 'Weight', 'Warranty', 'Supplier', 'Notes' );
$rows = array();
foreach ( range( 0, 9 ) as $i ) { $rows[] = array( $products[ $i ], '$' . ( 20 + $i * 13 ), $i % 3 ? 'In stock' : 'Out', array( 'Bikes', 'Lights' )[ $i % 2 ], substr( $long, 0, 40 + $i * 10 ), ( 0.5 + $i ) . ' kg', ( 1 + $i % 3 ) . ' years', 'Supplier ' . $i, $i % 2 ? 'Fragile' : '' ); }
$shapes['drawers'] = array( $h, $rows, array( 4 => 'detail', 5 => 'detail', 6 => 'detail', 7 => 'detail', 8 => 'detail' ) );

$shapes['single'] = array( array( 'Item' ), array_map( fn( $p ) => array( $p ), array_slice( $products, 0, 5 ) ), array() );

$h = array( 'Date', 'Team', 'Score', 'Change', 'Comment', 'Link' );
$rows = array();
foreach ( range( 0, 11 ) as $i ) { $rows[] = array( $i % 4 ? '1' . $i . '.03.2026' : '', $i % 3 ? 'Team ' . chr( 65 + $i ) : '', $i % 5 ? (string) ( $i * 3 - 7 ) : '', $i % 2 ? '+' . $i . '.5%' : '-' . $i . '%', $i % 6 ? '' : 'Rain delay', $i % 4 ? '' : 'https://example.com/match/' . $i ); }
$shapes['sparse'] = array( $h, $rows, array( 1 => 'hidden' ) );

$skins = array_keys( LSTAB_Styles::all() );
$sources = array();
$pages = array();

foreach ( $shapes as $shape => list( $headers, $rows, $flags ) ) {
	foreach ( array( 'pinned' => 1, 'free' => 0 ) as $pin => $on ) {
		$id = LSTAB_Storage::insert( array(
			'title' => "Sweep $shape $pin", 'sheet_url' => 'https://docs.google.com/spreadsheets/d/sweepsweepsweepsweepsweep' . count( $sources ) . '/edit',
			'sheet_id' => 'sweepsweepsweepsweepsweep' . count( $sources ), 'sheet_kind' => 'id', 'gid' => '0', 'sync_interval' => 86400,
			'sticky_first' => $on, 'sticky_head' => $on, 'link_cells' => 1, 'per_page' => 'wide' === $shape && $on ? 10 : 0,
		) );
		if ( is_wp_error( $id ) ) { die( $id->get_error_message() ); }
		LSTAB_Storage::record_success( $id, array( 'headers' => $headers, 'rows' => $rows ) );
		$config = LSTAB_Columns::reconcile( array(), $headers );
		foreach ( $flags as $i => $flag ) { $config[ $i ][ $flag ] = true; $config[ $i ]['source'] = $headers[ $i ]; }
		LSTAB_Storage::update( $id, array( 'columns_config' => $config ) );
		$sources[] = $id;

		foreach ( array( 'table', 'auto', 'cards' ) as $layout ) {
			$content = '';
			foreach ( $skins as $skin ) {
				$content .= "<!-- wp:paragraph --><p>$shape / $pin / $layout / <strong>$skin</strong></p><!-- /wp:paragraph -->\n";
				$content .= "<!-- wp:shortcode -->[sheet_table id=\"$id\" style=\"$skin\" layout=\"$layout\"]<!-- /wp:shortcode -->\n";
			}
			$post = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "Sweep $shape $pin $layout", 'post_name' => "sweep-$shape-$pin-$layout", 'post_content' => $content ) );
			$pages[] = array( 'id' => $post, 'url' => get_permalink( $post ), 'shape' => $shape, 'pin' => $pin, 'layout' => $layout );
		}
	}
}

file_put_contents( $manifest, wp_json_encode( array( 'sources' => $sources, 'pages' => $pages, 'skins' => $skins ) ) );
echo count( $sources ) . " sources, " . count( $pages ) . " pages\n";
