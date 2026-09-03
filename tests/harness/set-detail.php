<?php
/**
 * Move one column into the details drawer, or back out of it.
 *
 * Driving this through the picker would test the picker; this section is about
 * what a visitor gets, so the setting is made the short way.
 *
 * Usage: php tests/harness/set-detail.php /path/to/wp <source-id> <column> <1|0>
 *
 * @package LiveSheetsTable\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';

if ( ! $wp_root || ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php set-detail.php /path/to/wp <source-id> <column> <1|0>\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1:8089';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';

$source_id = isset( $argv[2] ) ? (int) $argv[2] : 0;
$column    = isset( $argv[3] ) ? (int) $argv[3] : 0;
$on        = isset( $argv[4] ) ? '1' === $argv[4] : false;

$source = LSTAB_Storage::get( $source_id );

if ( ! $source ) {
	fwrite( STDERR, "No such source: {$source_id}\n" );
	exit( 1 );
}

$config = $source['columns_config'];

if ( ! isset( $config[ $column ] ) ) {
	fwrite( STDERR, "No such column: {$column}\n" );
	exit( 1 );
}

$config[ $column ]['detail'] = $on;

LSTAB_Storage::update( $source_id, array( 'columns_config' => $config ) );

echo 'column ' . $column . ' detail=' . ( $on ? '1' : '0' ) . "\n";
