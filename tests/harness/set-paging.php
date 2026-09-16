<?php
/**
 * Turn pages on or off for one source, and say how many rows to a page.
 *
 * Driving this through the settings screen would test the settings screen; the
 * section that uses it is about what happens to a link a visitor clicks, so the
 * setting is made the short way.
 *
 * Usage: php tests/harness/set-paging.php /path/to/wp <source-id> <per-page>
 *
 * @package LiveSheetsTable\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';

if ( ! $wp_root || ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php set-paging.php /path/to/wp <source-id> <per-page>\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1:8089';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';

$source_id = isset( $argv[2] ) ? (int) $argv[2] : 0;
$per_page  = isset( $argv[3] ) ? (int) $argv[3] : 0;

if ( ! LSTAB_Storage::get( $source_id ) ) {
	fwrite( STDERR, "No such source: {$source_id}\n" );
	exit( 1 );
}

LSTAB_Storage::update( $source_id, array( 'per_page' => $per_page ) );

echo 'source ' . $source_id . ' per_page=' . $per_page . "\n";
