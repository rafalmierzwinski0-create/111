<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Fetcher {
	const DEFAULT_TIMEOUT = 20;

	public static function fetch_csv( $sheet_id, $gid = '0', $sheet_kind = 'doc' ) {
		$result = self::fetch_from( LSTAB_Url::csv_endpoint( $sheet_id, $gid, $sheet_kind ) );

		if ( ! is_wp_error( $result ) ) {
			return $result;
		}

		$fallback = LSTAB_Url::csv_fallback_endpoint( $sheet_id, $gid, $sheet_kind );

		if ( '' !== $fallback ) {
			$second = self::fetch_from( $fallback );

			if ( ! is_wp_error( $second ) ) {
				return $second;
			}
		}

		return apply_filters( 'lstab_fetch_refused', $result, $sheet_id, $gid, $sheet_kind );
	}

	protected static function fetch_from( $url ) {
		$response = self::request( $url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = (string) wp_remote_retrieve_body( $response );
		$type = (string) wp_remote_retrieve_header( $response, 'content-type' );

		if ( self::looks_like_html( $body, $type ) ) {
			return new WP_Error(
				'lstab_not_public',
				__( 'Google returned a sign-in page instead of data. Open the sheet, choose Share, and set access to "Anyone with the link – Viewer".', 'live-sheets-table' )
			);
		}

		if ( '' === trim( $body ) ) {
			return new WP_Error(
				'lstab_empty_response',
				__( 'Google returned an empty response for this tab.', 'live-sheets-table' )
			);
		}

		return $body;
	}

	public static function fetch_table( $sheet_id, $gid = '0', $sheet_kind = 'doc', $first_row_header = true ) {
		$csv = self::fetch_csv( $sheet_id, $gid, $sheet_kind );
		if ( is_wp_error( $csv ) ) {
			return $csv;
		}

		return LSTAB_CSV_Parser::parse( $csv, $first_row_header );
	}

	public static function fetch_tabs( $sheet_id, $sheet_kind = 'doc' ) {
		$response = self::request( LSTAB_Url::tabs_endpoint( $sheet_id, $sheet_kind ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = (string) wp_remote_retrieve_body( $response );
		$tabs = self::parse_tabs( $body );

		if ( ! $tabs ) {
			return new WP_Error(
				'lstab_no_tabs',
				__( 'Could not read the tab list for this spreadsheet.', 'live-sheets-table' )
			);
		}

		return $tabs;
	}

	public static function parse_tabs( $html ) {
		$tabs = array();
		$seen = array();

		if ( preg_match_all( '#\{"name":"((?:[^"\\\\]|\\\\.)*)"(?:(?!\{"name").)*?"gid":"?([0-9]+)"?#s', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				self::collect_tab( $tabs, $seen, $match[2], $match[1] );
			}
		}

		if ( ! $tabs && preg_match_all( '#id="sheet-button-([0-9]+)"[^>]*>([^<]*)<#', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				self::collect_tab( $tabs, $seen, $match[1], $match[2] );
			}
		}

		return $tabs;
	}

	protected static function collect_tab( &$tabs, &$seen, $gid, $name ) {
		$gid = LSTAB_Url::sanitize_gid( $gid );

		if ( isset( $seen[ $gid ] ) ) {
			return;
		}

		$decoded = json_decode( '"' . $name . '"' );
		$label   = is_string( $decoded ) ? $decoded : $name;
		$label   = html_entity_decode( $label, ENT_QUOTES, 'UTF-8' );
		$label   = trim( wp_strip_all_tags( $label ) );

		if ( '' === $label ) {
			return;
		}

		$seen[ $gid ] = true;
		$tabs[]       = array(
			'gid'  => $gid,
			'name' => $label,
		);
	}

	protected static function request( $url ) {
		$args = array(
			'timeout'     => self::DEFAULT_TIMEOUT,
			'redirection' => 5,
			'sslverify'   => true,
			'headers'     => array(
				'Accept' => 'text/csv, text/plain, text/html;q=0.5, */*;q=0.1',
			),
			'user-agent'  => 'LiveSheetsTable/' . LSTAB_VERSION . '; ' . home_url( '/' ),
		);

		$args = (array) apply_filters( 'lstab_fetch_args', $args, $url );

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'lstab_http_error',
				sprintf(
					/* translators: %s: underlying transport error. */
					__( 'Could not reach Google: %s', 'live-sheets-table' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error( 'lstab_http_status', self::status_message( $code ), array( 'status' => $code ) );
		}

		return $response;
	}

	protected static function status_message( $code ) {
		switch ( $code ) {
			case 401:
			case 403:
				return __( 'Google refused access to this sheet (HTTP 403). Open the sheet, choose Share, and set access to "Anyone with the link – Viewer".', 'live-sheets-table' );
			case 404:
				return __( 'Google could not find this spreadsheet (HTTP 404). Check that the link is correct and the file has not been deleted.', 'live-sheets-table' );
			case 429:
				return __( 'Google is rate limiting requests (HTTP 429). The next scheduled sync will try again.', 'live-sheets-table' );
			default:
				return sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Google responded with HTTP %d.', 'live-sheets-table' ),
					$code
				);
		}
	}

	protected static function looks_like_html( $body, $content_type ) {
		if ( false !== stripos( $content_type, 'text/html' ) ) {
			return true;
		}

		$head = strtolower( ltrim( substr( $body, 0, 512 ) ) );

		return 0 === strpos( $head, '<!doctype html' ) || 0 === strpos( $head, '<html' );
	}
}
