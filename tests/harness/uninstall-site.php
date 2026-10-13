<?php
/**
 * Prepares a site for tests/uninstall-test.php, or reports what the plugin
 * left behind on it. Run in a process of its own every time: the test deletes
 * the plugin between calls, and a WordPress loaded once would answer from what
 * it had cached before.
 *
 * Usage: php tests/harness/uninstall-site.php /path/to/site PORT state|keep|wipe
 *
 * @package LiveSheetsTable\Tests
 */

define( 'WP_USE_THEMES', false );

$_SERVER['HTTP_HOST']      = '127.0.0.1:' . (int) $argv[2];
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require rtrim( $argv[1], '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wpdb;

$action = isset( $argv[3] ) ? $argv[3] : 'state';

if ( 'keep' === $action || 'wipe' === $action ) {
	$settings                        = (array) get_option( 'lstab_settings', array() );
	$settings['delete_on_uninstall'] = 'wipe' === $action;
	update_option( 'lstab_settings', $settings );

	// What an older version, and the plugin's own housekeeping, leave about.
	update_option( 'lstab_seen_on', array( 1 => time() ) );
	update_option( 'lstab_pro_last_seen', time() - 30 * DAY_IN_SECONDS );
	update_option( 'lstab_hidden_alerts', array( 1 => array( 'rows' => array() ) ) );
	update_option( 'lstab_paging_declined', array( 1 ) );
	set_transient( 'lstab_usage_map', array( 1 => array() ), HOUR_IN_SECONDS );
	update_user_meta( 1, 'lstab_grace_dismissed', time() );

	echo "prepared {$action}\n";
	exit;
}

$options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name", $wpdb->esc_like( 'lstab' ) . '%' ) );
$options = array_values( array_filter( $options, static fn( $name ) => 0 !== strpos( $name, 'lstabp' ) ) );
$table   = $wpdb->prefix . 'lstab_sources';

echo wp_json_encode(
	array(
		'active'  => is_plugin_active( 'live-sheets-table/live-sheets-table.php' ),
		'table'   => $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ),
		'options' => $options,
		'tick'    => (bool) wp_next_scheduled( 'lstab_sync_tick' ),
		'meta'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s", 'lstab_grace_dismissed' ) ),
	)
), "\n";
