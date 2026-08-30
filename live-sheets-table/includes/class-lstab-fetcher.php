<?php
/**
 * Remote fetching of sheet data and tab lists.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * HTTP layer.
 */
class LSTAB_Fetcher {

	const DEFAULT_TIMEOUT = 20;

	/**
	 * Download the CSV export of a sheet tab.
	 *
	 * @param string $sheet_id   Spreadsheet ID.
	 * @param string $gid        Tab ID.
	 * @param string $sheet_kind Document kind.
	 * @return string|WP_Error CSV body.
	 */
	public static function fetch_csv( $sheet_id, $gid = '0', $sheet_kind = 'doc' ) {
		$result = self::fetch_from( LSTAB_Url::csv_endpoint( $sheet_id, $gid, $sheet_kind ) );

		if ( ! is_wp_error( $result ) ) {
			return $result;
		}

		/*
		 * Sharing settings decide which endpoints answer: a sheet published to
		 * the web but not shared by link refuses the export and answers the
		 * query endpoint. That one damages values, so it is only worth trying
		 * once the good one has said no.
		 */
		$fallback = LSTAB_Url::csv_fallback_endpoint( $sheet_id, $gid, $sheet_kind );

		if ( '' === $fallback ) {
			return $result;
		}

		$second = self::fetch_from( $fallback );

		// The first refusal is the one worth reporting: it explains what to
		// change in the sheet's sharing settings.
		return is_wp_error( $second ) ? $result : $second;
	}

	/**
	 * Download one endpoint and check that it really answered with data.
	 *
	 * @param string $url Endpoint.
	 * @return string|WP_Error CSV body.
	 */
	protected static function fetch_from( $url ) {
		$response = self::request( $url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = (string) wp_remote_retrieve_body( $response );
		$type = (string) wp_remote_retrieve_header( $response, 'content-type' );

		// A private sheet answers with Google's sign-in page instead of CSV.
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

	/**
	 * Fetch and parse a sheet tab in one step.
	 *
	 * @param string $sheet_id         Spreadsheet ID.
	 * @param string $gid              Tab ID.
	 * @param string $sheet_kind       Document kind.
	 * @param bool   $first_row_header Treat first row as headers.
	 * @return array{headers:array,rows:array}|WP_Error
	 */
	public static function fetch_table( $sheet_id, $gid = '0', $sheet_kind = 'doc', $first_row_header = true ) {
		$csv = self::fetch_csv( $sheet_id, $gid, $sheet_kind );
		if ( is_wp_error( $csv ) ) {
			return $csv;
		}

		return LSTAB_CSV_Parser::parse( $csv, $first_row_header );
	}

	/**
	 * Discover the tabs of a spreadsheet.
	 *
	 * Google exposes no public tab-listing endpoint without an API key, so the
	 * HTML view is scraped instead. Failure is never fatal: the caller falls
	 * back to the single tab named in the URL.
	 *
	 * @param string $sheet_id   Spreadsheet ID.
	 * @param string $sheet_kind Document kind.
	 * @return array<int,array{gid:string,name:string}>|WP_Error
	 */
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

	/**
	 * Extract tab names and IDs from Google's HTML view.
	 *
	 * @param string $html HTML payload.
	 * @return array<int,array{gid:string,name:string}>
	 */
	public static function parse_tabs( $html ) {
		$tabs = array();
		$seen = array();

		// The bootstrap JSON blob carries {"name":"Sheet1", ... "gid":"0"} style entries.
		if ( preg_match_all( '#\{"name":"((?:[^"\\\\]|\\\\.)*)"(?:(?!\{"name").)*?"gid":"?([0-9]+)"?#s', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				self::collect_tab( $tabs, $seen, $match[2], $match[1] );
			}
		}

		// Published views render a plain button list instead.
		if ( ! $tabs && preg_match_all( '#id="sheet-button-([0-9]+)"[^>]*>([^<]*)<#', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				self::collect_tab( $tabs, $seen, $match[1], $match[2] );
			}
		}

		return $tabs;
	}

	/**
	 * Add a decoded, de-duplicated tab to the list.
	 *
	 * @param array<int,array{gid:string,name:string}> $tabs Accumulator, by reference.
	 * @param array<string,bool>                       $seen Seen gids, by reference.
	 * @param string                                   $gid  Tab ID.
	 * @param string                                   $name Raw tab name.
	 * @return void
	 */
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

	/**
	 * Perform the HTTP request with shared arguments and error mapping.
	 *
	 * @param string $url Target URL.
	 * @return array<string,mixed>|WP_Error
	 */
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

		/**
		 * Filters the wp_remote_get() arguments used for sheet requests.
		 *
		 * @param array  $args Request arguments.
		 * @param string $url  Target URL.
		 */
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

	/**
	 * Turn an HTTP status into advice the site owner can act on.
	 *
	 * @param int $code Status code.
	 * @return string
	 */
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

	/**
	 * Detect an HTML payload where CSV was expected.
	 *
	 * @param string $body         Response body.
	 * @param string $content_type Response content type.
	 * @return bool
	 */
	protected static function looks_like_html( $body, $content_type ) {
		if ( false !== stripos( $content_type, 'text/html' ) ) {
			return true;
		}

		$head = strtolower( ltrim( substr( $body, 0, 512 ) ) );

		return 0 === strpos( $head, '<!doctype html' ) || 0 === strpos( $head, '<html' );
	}
}
