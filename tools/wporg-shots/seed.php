<?php
/**
 * Fills a throwaway test site with the demo shop the directory screenshots
 * show: the free plugin only, three tables read from the English demo sheet
 * of the Google mock, and one page that shows the first of them.
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

$state = WP_CONTENT_DIR . '/lstab-mock-state.json';
file_put_contents( $state, wp_json_encode( array( 'mode' => 'demo', 'tab' => 'main' ) ) );

foreach ( LSTAB_Storage::get_all() as $existing ) {
	LSTAB_Storage::delete( (int) $existing['id'] );
}
foreach ( get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $post ) {
	wp_delete_post( $post->ID, true );
}

$sheet = '1Tr4ilS1deB1kesPr1ceL1st2026xYz0aBcDeFgHiJk';
$made  = array();
foreach (
	array(
		array( 'Bikes & gear prices', '0', 'Bikes & gear', 3600, 'clean' ),
		array( 'Service menu', '1734829105', 'Service menu', 86400, 'striped' ),
		array( 'Our shops', '884466221', 'Our shops', 21600, 'clean' ),
	) as $t
) {
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
	$made[] = $id;
}

$page = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Prices',
		'post_name'    => 'prices',
		'post_content' => "<!-- wp:paragraph -->\n<p>Every bike and accessory we carry, with today’s price. The list comes straight from our stock sheet, so what you see here is what is on the shop floor.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:live-sheets-table/sheet-table {\"sourceId\":{$made[0]},\"align\":\"wide\"} /-->\n\n<!-- wp:paragraph -->\n<p>Looking for a repair? See the service menu at any of our three shops.</p>\n<!-- /wp:paragraph -->",
	)
);

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

echo wp_json_encode( array( 'tables' => $made, 'page' => $page, 'url' => get_permalink( $page ) ) ) . "\n";
