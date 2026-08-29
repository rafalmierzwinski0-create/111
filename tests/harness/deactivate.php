<?php
/**
 * Deactivates a plugin on a test site.
 *
 * Usage: php tests/harness/deactivate.php /path/to/site 8089
 *
 * @package LiveSheetsTable\Tests
 */

$site   = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
$port   = isset( $argv[2] ) ? (int) $argv[2] : 8089;
// Named to avoid $plugin, which wp-settings.php overwrites while loading
// active plugins — a collision that quietly empties it after wp-load.
$target = isset( $argv[3] ) ? $argv[3] : 'live-sheets-table/live-sheets-table.php';

if ( ! $site || ! file_exists( $site . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php deactivate.php /path/to/site PORT\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1:' . $port;
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $site . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

deactivate_plugins( array( $target ) );

echo "  deactivated {$target}\n";
