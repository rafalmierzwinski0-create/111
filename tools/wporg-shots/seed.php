<?php
/**
 * Fills a throwaway test site with the three demo businesses the directory
 * screenshots show: the free plugin only, the showcase theme from ./theme,
 * seven tables read from the English demo sheet of the Google mock, and empty
 * pages that shoot.mjs lays out in the block editor, so their markup is
 * exactly what the editor itself saves.
 *
 * Never run against a site anyone uses: every table and page on it goes.
 *
 * Usage: php tools/wporg-shots/seed.php <wp root>
 */
define( 'WP_USE_THEMES', false );
require $argv[1] . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

deactivate_plugins( 'live-sheets-table-pro/live-sheets-table-pro.php', true );

$theme = get_theme_root() . '/showcase';
if ( ! is_dir( $theme ) ) {
	mkdir( $theme . '/templates', 0755, true );
	mkdir( $theme . '/parts', 0755, true );
}
foreach ( array( 'style.css', 'functions.php', 'theme.json', 'templates/showcase-fernhouse.html', 'templates/showcase-walks.html', 'templates/showcase-venue.html', 'parts/header-fernhouse.html', 'parts/footer-fernhouse.html', 'parts/header-walks.html', 'parts/footer-walks.html', 'parts/header-venue.html', 'parts/footer-venue.html' ) as $file ) {
	copy( __DIR__ . '/theme/' . $file, $theme . '/' . $file );
}
switch_theme( 'showcase' );
update_option( 'blogname', 'Fernhouse' );
update_option( 'blogdescription', 'Houseplants, pots and good advice' );

if ( ! is_dir( WP_CONTENT_DIR . '/mu-plugins' ) ) {
	mkdir( WP_CONTENT_DIR . '/mu-plugins', 0755, true );
}
copy( __DIR__ . '/no-emoji-mu.php', WP_CONTENT_DIR . '/mu-plugins/screenshots-no-emoji.php' );
file_put_contents( WP_CONTENT_DIR . '/lstab-mock-state.json', wp_json_encode( array( 'mode' => 'demo', 'tab' => 'main' ) ) );

foreach ( LSTAB_Storage::get_all() as $existing ) {
	LSTAB_Storage::delete( (int) $existing['id'] );
}
foreach ( get_posts( array( 'post_type' => array( 'page', 'post', 'wp_template', 'wp_template_part' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $post ) {
	wp_delete_post( $post->ID, true );
}

$plants_css = 'th { letter-spacing: 0.08em; }
td[data-label="Plant"] { font-weight: 600; }
td[data-label="Price"] { font-weight: 600; font-variant-numeric: tabular-nums; }
td[data-label="Light"], td[data-label="Pot"] { color: #6b665c; }';

$walks_css = 'th { letter-spacing: 0.08em; }
td[data-label="Walk"] { font-weight: 600; }
td[data-label="Price"] { font-weight: 600; }
td[data-label="Date"], td[data-label="Starts"] { font-variant-numeric: tabular-nums; }';

$gigs_css = 'th { letter-spacing: 0.1em; }
td[data-label="Artist"] { font-weight: 700; color: #ffffff; }
td[data-label="Date"] { color: #ff8a5c; font-weight: 600; font-variant-numeric: tabular-nums; }
td[data-label="Genre"], td[data-label="Doors"] { color: #a39d94; }
td[data-label="From"] { font-weight: 700; color: #ffffff; }';

$sheet  = '1FernH0useSh0pSheet2026xYz0aBcDeFgHiJkLmN';
$tables = array(
	'plants'    => array( 'Plant price list', '0', 'Plants', 3600, 'clean', 'table', array( 'text' => '#24231f', 'headerBg' => '#f6f1e7', 'headerText' => '#5b5345', 'border' => '#ece6da', 'hover' => '#f8f4ec', 'accent' => '#2f6b4f', 'density' => 'roomy', 'corners' => 'pill', 'fontSize' => 16, 'headFontSize' => 12 ), $plants_css ),
	'walks'     => array( 'Tour departures', '556677889', 'Tour departures', 900, 'striped', 'auto', array( 'text' => '#1d232b', 'headerBg' => '#18222f', 'headerText' => '#f3ead8', 'stripe' => '#faf6ee', 'hover' => '#f4ecdc', 'border' => '#ece4d4', 'accent' => '#9a6a22', 'density' => 'roomy', 'fontSize' => 16, 'headFontSize' => 12 ), $walks_css ),
	'workshops' => array( 'Workshops', '1734829105', 'Workshops', 86400, 'striped', 'table', array(), '' ),
	'shops'     => array( 'Our greenhouses', '884466221', 'Shops', 21600, 'clean', 'cards', array(), '' ),
	'hours'     => array( 'Opening hours', '1122334455', 'Opening hours', 86400, 'bordered', 'table', array(), '' ),
	'delivery'  => array( 'Delivery areas', '2233445566', 'Delivery', 86400, 'midnight', 'table', array(), '' ),
	'gigs'      => array( 'Gig listings', '3344556677', 'Gigs', 900, 'midnight', 'auto', array( 'background' => '#12151c', 'headerBg' => '#0b0d12', 'headerText' => '#ff8a5c', 'text' => '#ece8e1', 'border' => '#222631', 'hover' => '#1a1e27', 'accent' => '#ff8a5c', 'density' => 'roomy', 'corners' => 'pill', 'fontSize' => 16, 'headFontSize' => 12 ), $gigs_css ),
);
$made   = array();
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
			'custom_css'    => $t[7],
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
foreach (
	array(
		'plants' => array( 'Price list', 'price-list', 'showcase-fernhouse' ),
		'walks'  => array( 'Dates and seats', 'dates', 'showcase-walks' ),
		'gigs'   => array( 'What’s on', 'whats-on', 'showcase-venue' ),
		'styles' => array( 'Styles', 'styles', 'page-no-title' ),
	) as $key => $p
) {
	$pages[ $key ] = wp_insert_post(
		array(
			'post_type'     => 'page',
			'post_status'   => 'publish',
			'post_title'    => $p[0],
			'post_name'     => $p[1],
			'page_template' => $p[2],
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
		'core'           => array( 'isComplementaryAreaVisible' => true, 'fixedToolbar' => true ),
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
