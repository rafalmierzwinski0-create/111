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
 *   { "mode": "ok" | "http_403" | "timeout" | "html_login" | "empty" | "ragged"
 *             | "endpoints",
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
		'mode'  => 'ok',
		'tab'   => 'main',
		'oauth' => 'ok',
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
		$state = lstab_mock_state();

		// Google's OAuth token endpoint, for the Pro add-on.
		if ( false !== strpos( $url, 'oauth2.googleapis.com/token' ) ) {
			file_put_contents( LSTAB_MOCK_LOG, gmdate( 'c' ) . " oauth={$state['oauth']} url={$url}\n", FILE_APPEND );

			if ( 'down' === $state['oauth'] ) {
				return new WP_Error( 'http_request_failed', 'Mocked network failure reaching Google.' );
			}

			if ( 'reject' === $state['oauth'] ) {
				return lstab_mock_response(
					400,
					wp_json_encode(
						array(
							'error'             => 'invalid_grant',
							'error_description' => 'Token has been expired or revoked.',
						)
					),
					'application/json'
				);
			}

			$body = isset( $args['body'] ) ? (array) $args['body'] : array();
			$is_refresh = isset( $body['grant_type'] ) && 'refresh_token' === $body['grant_type'];

			$payload = array(
				'access_token' => 'mock-access-' . wp_generate_password( 8, false ),
				'expires_in'   => 3600,
				'scope'        => 'https://www.googleapis.com/auth/spreadsheets.readonly',
				'token_type'   => 'Bearer',
			);

			// Google returns a refresh token only on the first exchange, never
			// on a refresh. Reproducing that is the point of this branch.
			if ( ! $is_refresh ) {
				$payload['refresh_token'] = 'mock-refresh-token';
			}

			return lstab_mock_response( 200, wp_json_encode( $payload ), 'application/json' );
		}

		if ( false === strpos( $url, 'docs.google.com' ) ) {
			return $preempt;
		}

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

		// A sheet that is not shared publicly: only an authenticated request
		// gets data, which is what the Pro private-sheet path must produce.
		if ( 'private_only' === $state['mode'] ) {
			$authorised = isset( $args['headers']['Authorization'] )
				&& 0 === strpos( (string) $args['headers']['Authorization'], 'Bearer ' );

			if ( ! $authorised ) {
				return lstab_mock_response( 403, 'Sorry, unable to open the file at this time.', 'text/html; charset=UTF-8' );
			}

			return lstab_mock_response( 200, (string) file_get_contents( LSTAB_MOCK_FIXTURES . '/sheet-main.csv' ) );
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

		/*
		 * The two endpoints do not answer alike. /export hands back the cells
		 * as they are; gviz/tq infers one type per column, blanks whatever
		 * disagrees, and runs leading rows together into one heading. This
		 * mode serves each its own payload, so a test can tell which one the
		 * plugin actually asked for.
		 */
		if ( 'endpoints' === $state['mode'] ) {
			$fixture = ( false !== strpos( $url, '/export' ) )
				? 'sheet-export-intact.csv'
				: 'sheet-gviz-damaged.csv';

			return lstab_mock_response( 200, (string) file_get_contents( LSTAB_MOCK_FIXTURES . '/' . $fixture ) );
		}

		// A sheet whose sharing settings let the query endpoint answer while
		// the export refuses, which is what "published to the web" produces.
		if ( 'export_denied' === $state['mode'] ) {
			if ( false !== strpos( $url, '/export' ) ) {
				return lstab_mock_response( 403, 'Sorry, unable to open the file at this time.', 'text/html; charset=UTF-8' );
			}

			return lstab_mock_response( 200, (string) file_get_contents( LSTAB_MOCK_FIXTURES . '/sheet-main.csv' ) );
		}

		/*
		 * Anything at all. Exploratory testing needs to be able to hand the
		 * plugin a payload nobody thought to make a fixture of — an empty
		 * sheet, fifty columns, a cell holding a formula error — without
		 * adding a mode per idea.
		 */
		if ( 'custom' === $state['mode'] ) {
			$custom = WP_CONTENT_DIR . '/lstab-mock-custom.csv';

			return lstab_mock_response( 200, file_exists( $custom ) ? (string) file_get_contents( $custom ) : '' );
		}

		// The gid decides which tab is served, mirroring Google's behaviour.
		if ( 'ragged' === $state['mode'] ) {
			// A sheet that fetches fine and comes back with one row short of a
			// cell, which is what a stray quotation mark leaves behind.
			return lstab_mock_response( 200, (string) file_get_contents( LSTAB_MOCK_FIXTURES . '/sheet-ragged.csv' ) );
		}

		$fixture = 'second' === $state['tab'] ? 'sheet-second-tab.csv' : 'sheet-main.csv';
		if ( false !== strpos( $url, 'gid=1734829105' ) ) {
			$fixture = 'sheet-second-tab.csv';
		}

		return lstab_mock_response( 200, (string) file_get_contents( LSTAB_MOCK_FIXTURES . '/' . $fixture ) );
	},
	10,
	3
);
