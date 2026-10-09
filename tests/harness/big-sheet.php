<?php
/**
 * Fetches one very large tab under a tight memory limit and says what came
 * back, for tests/e2e-test.php. A separate process because the limit has to be
 * set before WordPress starts: WordPress decides once, at start-up, how far it
 * may raise memory for a big job, and on the command line that is "no limit".
 *
 * Usage: php -d memory_limit=300M tests/harness/big-sheet.php <wp root> <megabytes>
 *
 * @package LiveSheetsTable\Tests
 */

define( 'WP_USE_THEMES', false );
require $argv[1] . '/wp-load.php';

$megabytes = max( 1, (int) $argv[2] );
$line      = "some value here,another one\n";
$csv       = "A,B\n" . str_repeat( $line, (int) ( $megabytes * MB_IN_BYTES / strlen( $line ) ) );

add_filter( 'lstab_max_sheet_bytes', static fn() => 64 * MB_IN_BYTES );
add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) use ( $csv ) {
		if ( false === strpos( $url, 'docs.google.com' ) ) {
			return $preempt;
		}

		return array(
			'headers'  => array( 'content-type' => 'text/csv' ),
			'body'     => $csv,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	99,
	3
);

$result = LSTAB_Fetcher::fetch_csv( 'BIGSHEETBIGSHEETBIGSHEET00', '0', 'doc' );

echo wp_json_encode(
	array(
		'code'    => is_wp_error( $result ) ? $result->get_error_code() : 'ok',
		'message' => is_wp_error( $result ) ? $result->get_error_message() : '',
		'bytes'   => is_wp_error( $result ) ? 0 : strlen( $result ),
		'limit'   => ini_get( 'memory_limit' ),
	)
);
