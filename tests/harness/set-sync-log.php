<?php
/**
 * Write a source's run of check results by hand.
 *
 * The card draws one mark per check, and a run with a failure in it is the
 * only way to see that the marks say anything at all. Producing one for real
 * would mean six sync cycles for a picture.
 *
 * Usage: php tests/harness/set-sync-log.php /path/to/wp <source-id> <log>
 * The log is the stored shape: "o" for a check that worked, "x" for one that
 * did not, oldest first.
 *
 * @package LiveSheetsTable\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';

if ( ! $wp_root || ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php set-sync-log.php /path/to/wp <source-id> <log>\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1:8089';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $wp_root . '/wp-load.php';

$source_id = isset( $argv[2] ) ? (int) $argv[2] : 0;
$log       = isset( $argv[3] ) ? preg_replace( '/[^ox]/', '', (string) $argv[3] ) : '';

global $wpdb;

$wpdb->update( LSTAB_Storage::table(), array( 'sync_log' => $log ), array( 'id' => $source_id ) );
wp_cache_delete( $source_id, LSTAB_Storage::CACHE_GROUP );

echo $log;
