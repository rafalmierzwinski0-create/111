<?php
/**
 * Renders the table this subpage shows, using the real plugin on a real site.
 *
 * Same idea as landing/szlaki/zbierz.php: a source is created, given a skin,
 * colour rules, column looks and visitor filters exactly the way somebody would
 * in the dashboard, and then rendered. What lands in markup.json is what
 * LSTAB_Renderer actually produced — the page around it only wraps it.
 *
 * Usage: php landing/mozliwosci/zbierz.php /path/to/wp [base-url] [out.json]
 *
 * @package LiveSheetsTable\Landing
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '/tmp/lstab-env/wp71';
$base    = isset( $argv[2] ) ? rtrim( $argv[2], '/' ) : 'http://127.0.0.1:8089';
$out     = isset( $argv[3] ) ? $argv[3] : __DIR__ . '/markup.json';

if ( ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "No WordPress there: {$wp_root}\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = preg_replace( '#^https?://#', '', $base );
$_SERVER['REQUEST_URI']    = '/trails/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( ! is_plugin_active( 'live-sheets-table-pro/live-sheets-table-pro.php' ) ) {
	fwrite( STDERR, "The Pro add-on has to be active: the rules and the column looks come from it.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

// The page is English, so the table is too. The plugin has a language of its
// own, apart from the site's; this is that setting.
$settings           = get_option( LSTAB_Settings::OPTION, array() );
$settings           = is_array( $settings ) ? $settings : array();
$settings['locale'] = 'en_US';
update_option( LSTAB_Settings::OPTION, $settings );
LSTAB_Locale::forget();

copy( __DIR__ . '/trails.csv', WP_CONTENT_DIR . '/lstab-mock-custom.csv' );
file_put_contents( WP_CONTENT_DIR . '/lstab-mock-state.json', wp_json_encode( array( 'mode' => 'custom' ) ) );

foreach ( LSTAB_Storage::get_all() as $existing ) {
	LSTAB_Storage::delete( $existing['id'] );
}

/** The sales site's own colours, so the table looks like it belongs there. */
$mint   = '#5fe3cf';
$amber  = '#f2b544';
$coral  = '#ff8d8d';

$source_id = LSTAB_Storage::insert(
	array(
		'title'         => 'Trail conditions',
		'sheet_url'     => 'https://docs.google.com/spreadsheets/d/1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0',
		'sheet_id'      => '1AbC-dEf_GhIjKlMnOpQrStUvWxYz0123456789',
		'sheet_kind'    => 'doc',
		'gid'           => '0',
		'tab_name'      => 'Trails',
		'sync_interval' => 900,
		'style_preset'  => 'midnight',
		'layout'        => 'auto',
		// Every one of these is a control on the Appearance tab, not CSS.
		'style_vars'    => array(
			'background' => '#0d1513',
			'headerBg'   => '#121d1b',
			'border'     => '#1f2e2b',
			'hover'      => '#16302c',
			'accent'     => $mint,
			'lines'      => 'normal',
			'density'    => 'normal',
		),
	)
);

LSTAB_Sync::run( $source_id );

// Colour rules, in the words the dashboard uses: when Status is Closed, paint
// the whole row; when it is Open, put the value in a pill; difficulty gets a
// dot, which is the quietest of the three.
update_option(
	'lstabp_rules',
	array(
		$source_id => array(
			array( 'column' => 'Status',     'operator' => '=', 'value' => 'Closed',   'style' => '#8c3b46', 'scope' => 'row' ),
			array( 'column' => 'Status',     'operator' => '=', 'value' => 'Open',     'style' => $mint,     'scope' => 'pill' ),
			array( 'column' => 'Status',     'operator' => '=', 'value' => 'Caution',  'style' => $amber,    'scope' => 'pill' ),
			array( 'column' => 'Status',     'operator' => '=', 'value' => 'Closed',   'style' => $coral,    'scope' => 'pill' ),
			array( 'column' => 'Difficulty', 'operator' => '=', 'value' => 'Easy',     'style' => $mint,     'scope' => 'dot' ),
			array( 'column' => 'Difficulty', 'operator' => '=', 'value' => 'Moderate', 'style' => $amber,    'scope' => 'dot' ),
			array( 'column' => 'Difficulty', 'operator' => '=', 'value' => 'Hard',     'style' => $coral,    'scope' => 'dot' ),
		),
	),
	false
);

update_option(
	'lstabp_column_looks',
	array(
		$source_id => array(
			'Snow (cm)' => array( 'look' => 'bar', 'tint' => $mint, 'ink' => '', 'label' => '' ),
			'Webcam'    => array( 'look' => 'button', 'tint' => $mint, 'ink' => '#08201c', 'label' => 'Live view' ),
		),
	),
	false
);

update_option( 'lstabp_facets', array( $source_id => array( 'Difficulty', 'Status' ) ), false );
update_option( 'lstabp_export_sources', array( $source_id => true ), true );

// Backdated, so the line under the table has something to say.
global $wpdb;
$wpdb->update(
	LSTAB_Storage::table(),
	array( 'last_success_gmt' => gmdate( 'Y-m-d H:i:s', time() - 9 * MINUTE_IN_SECONDS ) ),
	array( 'id' => $source_id ),
	array( '%s' ),
	array( '%d' )
);

LSTAB_Storage::flush_cache( $source_id );

$collected = array(
	'id'   => (int) $source_id,
	'pro'  => LSTAB_Renderer::render( array( 'source_id' => $source_id ) ),
);

/*
 * The same sheet as the free plugin draws it: no rules, no column looks, no
 * filters, no downloads. Everything else — the table, the search, the sorting,
 * the folding into cards — is in the free plugin, and the page says so.
 */
update_option( 'lstabp_rules', array(), false );
update_option( 'lstabp_column_looks', array(), false );
update_option( 'lstabp_facets', array(), false );
update_option( 'lstabp_export_sources', array(), true );
LSTAB_Storage::flush_cache( $source_id );

$collected['free'] = LSTAB_Renderer::render( array( 'source_id' => $source_id ) );

file_put_contents( $out, wp_json_encode( $collected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

echo '  source ' . $source_id . ', ' . strlen( $collected['pro'] ) . " bytes of table, written to {$out}\n";
