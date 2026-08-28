<?php
/**
 * Plugin Name: Live Sheets Table – Google mock (test harness)
 * Description: Intercepts outbound requests to docs.google.com and answers them from local fixtures, so the plugin can be exercised end to end without internet access. Never ship this.
 *
 * The interception happens at WordPress's own pre_http_request filter, which
 * means every line of the plugin's fetch, parse, cron and fallback code runs
 * exactly as it would in production — only the network hop is faked.
 *
 * Control it by writing a JSON state file:
 *   { "mode": "ok" | "http_403" | "timeout" | "html_login" | "empty",
 *     "tab": "main" | "second" }
 *
 * @package LiveSheetsTable\Tests
 */

defined( 'ABSPATH' ) || exit;

// __DIR__ resolves through the mu-plugins symlink to this file's real location.
define( 'LSTAB_MOCK_FIXTURES', __DIR__ . '/fixtures' );
define( 'LSTAB_MOCK_STATE', WP_CONTENT_DIR . '/lstab-mock-state.json' );
define( 'LSTAB_MOCK_LOG', WP_CONTENT_DIR . '/lstab-mock-log.txt' );

/**
 * Read the current mock configuration.
 *
 * @return array<string,string>
 */
function lstab_mock_state() {
	$defaults = array(
		'mode' => 'ok',
		'tab'  => 'main',
	);

	if ( ! file_exists( LSTAB_MOCK_STATE ) ) {
		return $defaults;
	}

	$decoded = json_decode( (string) file_get_contents( LSTAB_MOCK_STATE ), true );

	return is_array( $decoded ) ? array_merge( $defaults, $decoded ) : $defaults;
}

/**
 * Build a fake HTTP response.
 *
 * @param int    $code    Status code.
 * @param string $body    Body.
 * @param string $type    Content type.
 * @return array<string,mixed>
 */
function lstab_mock_response( $code, $body, $type = 'text/csv; charset=UTF-8' ) {
	return array(
		'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( array( 'content-type' => $type ) ),
		'body'     => $body,
		'response' => array(
			'code'    => $code,
			'message' => 200 === $code ? 'OK' : 'Error',
		),
		'cookies'  => array(),
		'filename' => null,
	);
}

add_filter(
	'pre_http_request',
	function ( $preempt, $args, $url ) {
		if ( false === strpos( $url, 'docs.google.com' ) ) {
			return $preempt;
		}

		$state = lstab_mock_state();

		file_put_contents(
			LSTAB_MOCK_LOG,
			gmdate( 'c' ) . " mode={$state['mode']} tab={$state['tab']} url={$url}\n",
			FILE_APPEND
		);

		// Tab discovery request.
		if ( false !== strpos( $url, '/htmlview' ) || false !== strpos( $url, '/pubhtml' ) ) {
			if ( 'ok' !== $state['mode'] ) {
				return new WP_Error( 'http_request_failed', 'Mocked failure for tab discovery.' );
			}
			return lstab_mock_response(
				200,
				(string) file_get_contents( LSTAB_MOCK_FIXTURES . '/sheet-htmlview.html' ),
				'text/html; charset=UTF-8'
			);
		}

		switch ( $state['mode'] ) {
			case 'http_403':
				return lstab_mock_response( 403, 'Sorry, unable to open the file at this time.', 'text/html; charset=UTF-8' );

			case 'timeout':
				return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 20000 milliseconds' );

			case 'html_login':
				return lstab_mock_response(
					200,
					'<!DOCTYPE html><html><head><title>Sign in - Google Accounts</title></head><body>Sign in</body></html>',
					'text/html; charset=UTF-8'
				);

			case 'empty':
				return lstab_mock_response( 200, '' );
		}

		// The gid decides which tab is served, mirroring Google's behaviour.
		$fixture = 'second' === $state['tab'] ? 'sheet-second-tab.csv' : 'sheet-main.csv';
		if ( false !== strpos( $url, 'gid=1734829105' ) ) {
			$fixture = 'sheet-second-tab.csv';
		}

		return lstab_mock_response( 200, (string) file_get_contents( LSTAB_MOCK_FIXTURES . '/' . $fixture ) );
	},
	10,
	3
);
