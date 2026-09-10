<?php
/**
 * Delete a source, for a test that made one and does not want to leave it behind.
 *
 * Usage: php tests/harness/drop-source.php /path/to/wp 12
 *
 * @package LiveSheetsTable\Tests
 */

define( 'LSTAB_DROP_ROOT', isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '' );
define( 'LSTAB_DROP_ID', isset( $argv[2] ) ? (int) $argv[2] : 0 );

if ( ! LSTAB_DROP_ROOT || ! file_exists( LSTAB_DROP_ROOT . '/wp-load.php' ) || ! LSTAB_DROP_ID ) {
	fwrite( STDERR, "Usage: php drop-source.php /path/to/wp <source id>\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once LSTAB_DROP_ROOT . '/wp-load.php';

LSTAB_Storage::delete( LSTAB_DROP_ID );

echo 'dropped ' . LSTAB_DROP_ID . "\n";
