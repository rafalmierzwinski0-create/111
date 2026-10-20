<?php
/**
 * Builds what the UX audit looks at: one long sheet read through the mock
 * Google exactly as a real one is, shown on one page per style, with the
 * add-on's downloads and filter menus switched on where the add-on is active.
 *
 * The mock is put in "custom" mode with this sheet as its answer, so the edit
 * screen's own preview reads the same rows the pages show. Teardown puts the
 * mock back as it was.
 *
 * Usage: php tests/harness/ux-audit-setup.php <wp root> [teardown]
 */
define( 'WP_USE_THEMES', false );
require $argv[1] . '/wp-load.php';

$manifest = dirname( $argv[1] ) . '/ux-audit.json';
$state    = WP_CONTENT_DIR . '/lstab-mock-state.json';
$custom   = WP_CONTENT_DIR . '/lstab-mock-custom.csv';

if ( isset( $argv[2] ) && 'teardown' === $argv[2] ) {
	$m = json_decode( (string) @file_get_contents( $manifest ), true );
	foreach ( (array) ( $m['sources'] ?? array() ) as $id ) {
		LSTAB_Storage::delete( (int) $id );
	}
	foreach ( (array) ( $m['all_pages'] ?? $m['pages'] ?? array() ) as $p ) {
		wp_delete_post( (int) $p['id'], true );
	}
	if ( isset( $m['state'] ) ) {
		file_put_contents( $state, $m['state'] );
	} else {
		@unlink( $state );
	}
	if ( isset( $m['custom'] ) ) {
		file_put_contents( $custom, $m['custom'] );
	} else {
		@unlink( $custom );
	}
	@unlink( $manifest );
	echo "torn down\n";
	exit;
}

$names = array( 'Trail Bike Ranger 29"', 'Lazer Compact Helmet', 'Lezyne Front Light 800', 'Insulated Bottle 750 ml', 'Pro Gel Gloves', 'Abus Granit Combo Lock', 'Topeak Rear Rack', 'Road Bike Contend AR', 'Garmin Edge 540', 'Lezyne Floor Pump', 'Gore Rain Jacket', 'Shimano RC3 Shoes', 'Thule Roof Rack', 'Seasonal Bike Service' );
$lines = array( '"Product","Unit price","Availability","Category","Website"' );
for ( $i = 0; $i < 42; $i++ ) {
	$lines[] = sprintf(
		'"%s","$%s","%s","%s","%s"',
		str_replace( '"', '""', $names[ $i % 14 ] . ( $i >= 14 ? ' ' . ( 1 + intdiv( $i, 14 ) ) : '' ) ),
		number_format( 20 + $i * 37.5, 2 ),
		array( 'In stock', 'Out of stock', 'Backorder' )[ $i % 3 ],
		array( 'Bikes', 'Accessories', 'Apparel', 'Electronics' )[ $i % 4 ],
		$i % 2 ? '' : 'https://shop.example.com/p/' . $i
	);
}

$saved = array();
if ( file_exists( $state ) ) {
	$saved['state'] = (string) file_get_contents( $state );
}
if ( file_exists( $custom ) ) {
	$saved['custom'] = (string) file_get_contents( $custom );
}

file_put_contents( $custom, implode( "\n", $lines ) . "\n" );
file_put_contents( $state, wp_json_encode( array( 'mode' => 'custom', 'tab' => 'main' ) ) );

$id = LSTAB_Storage::insert(
	array(
		'title'         => 'UX audit shop',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/UXAUDITUXAUDITUXAUDIT00000/edit',
		'sheet_id'      => 'UXAUDITUXAUDITUXAUDIT00000',
		'sheet_kind'    => 'id',
		'gid'           => '0',
		'sync_interval' => 86400,
		'sticky_first'  => 1,
		'sticky_head'   => 1,
		'link_cells'    => 1,
		'layout'        => 'table',
	)
);
if ( is_wp_error( $id ) ) {
	die( $id->get_error_message() . "\n" );
}

$synced = LSTAB_Sync::run( $id );
if ( is_wp_error( $synced ) ) {
	die( 'sync failed: ' . $synced->get_error_message() . "\n" );
}

$paged = LSTAB_Storage::insert(
	array(
		'title'         => 'UX audit paged',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/UXAUDITUXAUDITUXAUDIT00000/edit',
		'sheet_id'      => 'UXAUDITUXAUDITUXAUDIT00000',
		'sheet_kind'    => 'id',
		'gid'           => '0',
		'sync_interval' => 86400,
		'sticky_first'  => 1,
		'sticky_head'   => 1,
		'link_cells'    => 1,
		'layout'        => 'table',
		'per_page'      => 10,
	)
);
LSTAB_Sync::run( $paged );

$free = LSTAB_Storage::insert(
	array(
		'title'         => 'UX audit unpinned',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/UXAUDITUXAUDITUXAUDIT00000/edit',
		'sheet_id'      => 'UXAUDITUXAUDITUXAUDIT00000',
		'sheet_kind'    => 'id',
		'gid'           => '0',
		'sync_interval' => 86400,
		'sticky_first'  => 0,
		'sticky_head'   => 1,
		'layout'        => 'table',
	)
);
LSTAB_Sync::run( $free );

$tinted = LSTAB_Storage::insert(
	array(
		'title'         => 'UX audit dark header',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/UXAUDITUXAUDITUXAUDIT00000/edit',
		'sheet_id'      => 'UXAUDITUXAUDITUXAUDIT00000',
		'sheet_kind'    => 'id',
		'gid'           => '0',
		'sync_interval' => 86400,
		'style_preset'  => 'striped',
		'style_vars'    => array( 'headerBg' => '#123f30', 'headerText' => '#ffffff', 'accent' => '#1f7a55' ),
	)
);
LSTAB_Sync::run( $tinted );
$tinted_page = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'UX audit dark header',
		'post_name'    => 'ux-audit-dark-header',
		'post_content' => "<!-- wp:shortcode -->[sheet_table id=\"$tinted\"]<!-- /wp:shortcode -->",
	)
);

$heads = array();
for ( $c = 1; $c <= 14; $c++ ) {
	$heads[] = 'Field ' . $c;
}
$rows = array();
for ( $i = 0; $i < 30; $i++ ) {
	$row = array();
	for ( $c = 0; $c < 14; $c++ ) {
		$row[] = 'v' . ( ( $i + $c ) % 4 );
	}
	$rows[] = $row;
}
$wide = LSTAB_Storage::insert(
	array(
		'title'         => 'UX audit wide',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/UXAUDITWIDEUXAUDITWIDE0000/edit',
		'sheet_id'      => 'UXAUDITWIDEUXAUDITWIDE0000',
		'sheet_kind'    => 'id',
		'gid'           => '0',
		'sync_interval' => 86400,
	)
);
LSTAB_Storage::record_success( $wide, array( 'headers' => $heads, 'rows' => $rows, 'offset' => 1 ) );

$pro = class_exists( 'LSTABP_Export' );
if ( $pro ) {
	// The add-on drops what it holds for a table number it has seen with a
	// different creation time, on the next screen of the dashboard. A number an
	// earlier test used and gave back would take these settings with it, so
	// the tables made here are put on its list first, as a dashboard visit would.
	$known = array();
	foreach ( LSTAB_Storage::get_all() as $made ) {
		$known[ (int) $made['id'] ] = (string) $made['created_gmt'];
	}
	update_option( 'lstabp_known_sources', $known, false );

	$all        = (array) get_option( LSTABP_Export::OPTION, array() );
	$all[ $id ] = true;
	update_option( LSTABP_Export::OPTION, $all, true );

	$facets        = (array) get_option( LSTABP_Facets::OPTION, array() );
	$facets[ $id ] = array( 'Availability', 'Category' );
	update_option( LSTABP_Facets::OPTION, $facets, false );
}

$pages    = array();
$unpinned = array();
foreach ( array_keys( LSTAB_Styles::all() ) as $skin ) {
	$post    = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => "UX audit $skin",
			'post_name'    => "ux-audit-$skin",
			'post_content' => "<!-- wp:shortcode -->[sheet_table id=\"$id\" style=\"$skin\"]<!-- /wp:shortcode -->\n<!-- wp:paragraph --><p>" . str_repeat( 'Text under the table so the page can scroll past it. ', 60 ) . "</p><!-- /wp:paragraph -->",
		)
	);
	$pages[] = array(
		'id'   => $post,
		'url'  => get_permalink( $post ),
		'skin' => $skin,
	);
	$loose    = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => "UX audit $skin unpinned",
			'post_name'    => "ux-audit-$skin-unpinned",
			'post_content' => "<!-- wp:shortcode -->[sheet_table id=\"$free\" style=\"$skin\"]<!-- /wp:shortcode -->",
		)
	);
	$unpinned[] = array(
		'id'   => $loose,
		'url'  => get_permalink( $loose ),
		'skin' => $skin,
	);
}

$tinted_entry = array(
	'id'   => $tinted_page,
	'url'  => get_permalink( $tinted_page ),
	'skin' => 'striped with its own dark header',
);
file_put_contents( $manifest, wp_json_encode( array( 'sources' => array( $id, $wide, $paged, $free, $tinted ), 'source' => $id, 'wide' => $wide, 'paged' => $paged, 'pages' => $pages, 'unpinned' => $unpinned, 'tinted' => $tinted_entry, 'all_pages' => array_merge( $pages, $unpinned, array( $tinted_entry ) ), 'pro' => $pro ) + $saved ) );
echo count( $pages ) . " pages for source $id" . ( $pro ? ' (with the add-on)' : '' ) . "\n";
