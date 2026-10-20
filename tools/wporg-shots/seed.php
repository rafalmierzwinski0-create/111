<?php
/**
 * Fills a throwaway test site with the demo shop the directory screenshots
 * show: the free plugin only, five tables read from the English demo sheet of
 * the Google mock, and two empty pages that shoot.mjs lays out in the block
 * editor, so their markup is exactly what the editor itself saves.
 *
 * Never run against a site anyone uses: every table and page on it goes.
 *
 * Usage: php tools/wporg-shots/seed.php <wp root>
 */
define( 'WP_USE_THEMES', false );
require $argv[1] . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

deactivate_plugins( 'live-sheets-table-pro/live-sheets-table-pro.php', true );
switch_theme( 'twentytwentyfive' );
update_option( 'blogname', 'Trailside Bikes' );
update_option( 'blogdescription', 'Bikes, gear and repairs' );

copy( __DIR__ . '/no-emoji-mu.php', WP_CONTENT_DIR . '/mu-plugins/screenshots-no-emoji.php' );
file_put_contents( WP_CONTENT_DIR . '/lstab-mock-state.json', wp_json_encode( array( 'mode' => 'demo', 'tab' => 'main' ) ) );

foreach ( LSTAB_Storage::get_all() as $existing ) {
	LSTAB_Storage::delete( (int) $existing['id'] );
}
foreach ( get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $post ) {
	wp_delete_post( $post->ID, true );
}

$sheet  = '1Tr4ilS1deB1kesPr1ceL1st2026xYz0aBcDeFgHiJk';
$tables = array(
	'prices'   => array( 'Bikes & gear prices', '0', 'Bikes & gear', 3600, 'striped', 'table', array( 'headerBg' => '#123f30', 'headerText' => '#ffffff', 'stripe' => '#f3f8f5', 'hover' => '#e4f2ea', 'accent' => '#1f7a55', 'border' => '#dde9e2' ) ),
	'service'  => array( 'Service menu', '1734829105', 'Service menu', 86400, 'striped', 'table', array() ),
	'shops'    => array( 'Our shops', '884466221', 'Our shops', 21600, 'clean', 'cards', array() ),
	'hours'    => array( 'Opening hours', '1122334455', 'Opening hours', 86400, 'bordered', 'table', array( 'density' => 'compact' ) ),
	'rides'    => array( 'Group rides', '556677889', 'Group rides', 3600, 'midnight', 'table', array() ),
);
$made  = array();
foreach ( $tables as $key => $t ) {
	$id = LSTAB_Storage::insert(
		array(
			'title'         => $t[0],
			'sheet_url'     => "https://docs.google.com/spreadsheets/d/$sheet/edit#gid={$t[1]}",
			'sheet_id'      => $sheet,
			'sheet_kind'    => 'doc',
			'gid'           => $t[1],
			'tab_name'      => $t[2],
			'sync_interval' => $t[3],
			'style_preset'  => $t[4],
			'layout'        => $t[5],
			'style_vars'    => $t[6],
			'sticky_head'   => 1,
			'sticky_first'  => 1,
			'link_cells'    => 1,
		)
	);
	if ( is_wp_error( $id ) ) {
		die( $id->get_error_message() . "\n" );
	}
	$synced = LSTAB_Sync::run( $id );
	if ( is_wp_error( $synced ) ) {
		die( 'update failed: ' . $synced->get_error_message() . "\n" );
	}
	$made[ $key ] = $id;
}

$pages = array();
foreach ( array( 'prices' => 'Prices', 'visit' => 'Visit us' ) as $slug => $title ) {
	$pages[ $slug ] = wp_insert_post(
		array(
			'post_type'     => 'page',
			'post_status'   => 'publish',
			'post_title'    => $title,
			'post_name'     => 'prices' === $slug ? 'prices' : 'visit-us',
			'menu_order'    => 'prices' === $slug ? 1 : 2,
			'page_template' => 'page-no-title',
		)
	);
}

$admin = get_user_by( 'login', 'admin' );
wp_update_user( array( 'ID' => $admin->ID, 'display_name' => 'Sam', 'first_name' => 'Sam' ) );
update_user_meta(
	$admin->ID,
	$GLOBALS['wpdb']->get_blog_prefix() . 'persisted_preferences',
	array(
		'core/edit-post' => array( 'welcomeGuide' => false, 'fullscreenMode' => true ),
		'core'           => array( 'isComplementaryAreaVisible' => true ),
		'_modified'      => gmdate( 'c' ),
	)
);

echo wp_json_encode(
	array(
		'tables' => $made,
		'pages'  => $pages,
		'urls'   => array_map( 'get_permalink', $pages ),
	)
) . "\n";
