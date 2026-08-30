<?php
/**
 * Google Sheets URL parsing and endpoint building.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sheet URL helper.
 */
class LSTAB_Url {

	/**
	 * Hosts we are willing to talk to. Anything else is rejected before a
	 * request is ever made, which also keeps this from becoming an SSRF hole.
	 *
	 * @return array<int,string>
	 */
	public static function allowed_hosts() {
		return (array) apply_filters(
			'lstab_allowed_hosts',
			array( 'docs.google.com', 'spreadsheets.google.com' )
		);
	}

	/**
	 * Parse user input into a sheet reference.
	 *
	 * Accepts a full share/edit URL, a "publish to web" URL, or a bare sheet ID.
	 *
	 * @param string $input Raw user input.
	 * @return array{sheet_id:string,sheet_kind:string,gid:string}|WP_Error
	 */
	public static function parse( $input ) {
		$input = trim( wp_unslash( (string) $input ) );

		if ( '' === $input ) {
			return new WP_Error(
				'lstab_empty_url',
				__( 'Paste the link to your Google Sheet first.', 'live-sheets-table' )
			);
		}

		// A bare sheet ID, pasted without the surrounding URL.
		if ( preg_match( '#^[a-zA-Z0-9-_]{20,}$#', $input ) ) {
			return array(
				'sheet_id'   => $input,
				'sheet_kind' => 'doc',
				'gid'        => '0',
			);
		}

		if ( ! preg_match( '#^https?://#i', $input ) ) {
			$input = 'https://' . ltrim( $input, '/' );
		}

		$url = esc_url_raw( $input, array( 'http', 'https' ) );
		if ( ! $url ) {
			return new WP_Error(
				'lstab_invalid_url',
				__( 'That does not look like a valid link. Copy the address straight from your browser.', 'live-sheets-table' )
			);
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host || ! in_array( strtolower( $host ), self::allowed_hosts(), true ) ) {
			return new WP_Error(
				'lstab_bad_host',
				__( 'Only Google Sheets links are supported. The address must start with https://docs.google.com/spreadsheets/.', 'live-sheets-table' )
			);
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		$sheet_id   = '';
		$sheet_kind = 'doc';

		// Published-to-web documents use /spreadsheets/d/e/<long id>/pubhtml.
		if ( preg_match( '#/spreadsheets/d/e/([a-zA-Z0-9-_]+)#', $path, $m ) ) {
			$sheet_id   = $m[1];
			$sheet_kind = 'pub';
		} elseif ( preg_match( '#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $path, $m ) ) {
			$sheet_id = $m[1];
		}

		if ( '' === $sheet_id ) {
			return new WP_Error(
				'lstab_no_sheet_id',
				__( 'No spreadsheet ID found in that link. Use the address of the sheet itself, for example https://docs.google.com/spreadsheets/d/ABC123/edit.', 'live-sheets-table' )
			);
		}

		return array(
			'sheet_id'   => $sheet_id,
			'sheet_kind' => $sheet_kind,
			'gid'        => self::extract_gid( $url ),
		);
	}

	/**
	 * Pull the tab id out of a URL query string or fragment.
	 *
	 * @param string $url Sheet URL.
	 * @return string
	 */
	public static function extract_gid( $url ) {
		$query    = (string) wp_parse_url( $url, PHP_URL_QUERY );
		$fragment = (string) wp_parse_url( $url, PHP_URL_FRAGMENT );

		foreach ( array( $query, $fragment ) as $part ) {
			if ( '' === $part ) {
				continue;
			}
			$args = array();
			wp_parse_str( $part, $args );
			if ( isset( $args['gid'] ) && preg_match( '#^[0-9]+$#', (string) $args['gid'] ) ) {
				return (string) $args['gid'];
			}
		}

		return '0';
	}

	/**
	 * Sanitise a tab id.
	 *
	 * @param mixed $gid Raw value.
	 * @return string
	 */
	public static function sanitize_gid( $gid ) {
		$gid = preg_replace( '#[^0-9]#', '', (string) $gid );
		return ( null === $gid || '' === $gid ) ? '0' : $gid;
	}

	/**
	 * CSV export endpoint for a sheet reference.
	 *
	 * Uses the Visualization API for normal documents, which works for any
	 * sheet shared as "anyone with the link can view" — no API key, no
	 * "publish to web" step.
	 *
	 * @param string $sheet_id   Spreadsheet ID.
	 * @param string $gid        Tab ID.
	 * @param string $sheet_kind Either 'doc' or 'pub'.
	 * @return string
	 */
	public static function csv_endpoint( $sheet_id, $gid = '0', $sheet_kind = 'doc' ) {
		$gid = self::sanitize_gid( $gid );

		if ( 'pub' === $sheet_kind ) {
			$url = add_query_arg(
				array(
					'output' => 'csv',
					'gid'    => $gid,
					'single' => 'true',
				),
				'https://docs.google.com/spreadsheets/d/e/' . rawurlencode( $sheet_id ) . '/pub'
			);
		} else {
			/*
			 * The export endpoint hands back the cells as they are. The query
			 * endpoint (gviz/tq) does not: it infers one type per column and
			 * blanks every cell that disagrees with it, and it guesses how many
			 * leading rows are headings, running them together into one label.
			 * A price list holding "1 215,50" as text among plain numbers loses
			 * that price and gains a two-row heading — the sheet arrives
			 * damaged before the plugin has read a byte of it.
			 */
			$url = add_query_arg(
				array(
					'format' => 'csv',
					'gid'    => $gid,
				),
				'https://docs.google.com/spreadsheets/d/' . rawurlencode( $sheet_id ) . '/export'
			);
		}

		/**
		 * Filters the endpoint used to download sheet data.
		 *
		 * The Pro add-on uses this to route private sheets through the
		 * authenticated Sheets API instead.
		 *
		 * @param string $url        Download URL.
		 * @param string $sheet_id   Spreadsheet ID.
		 * @param string $gid        Tab ID.
		 * @param string $sheet_kind Document kind.
		 */
		return (string) apply_filters( 'lstab_fetch_url', $url, $sheet_id, $gid, $sheet_kind );
	}

	/**
	 * Where to look when the export endpoint will not answer.
	 *
	 * Not every sheet is reachable both ways: one shared with "anyone with the
	 * link" exports fine, while one only "published to the web" may answer the
	 * query endpoint and refuse the export. That endpoint mangles values, so it
	 * is a last resort rather than the first choice — and headers=1 at least
	 * stops it running the first rows together into one heading.
	 *
	 * @param string $sheet_id   Spreadsheet ID.
	 * @param string $gid        Tab ID.
	 * @param string $sheet_kind Either 'doc' or 'pub'.
	 * @return string Empty when there is nothing else to try.
	 */
	public static function csv_fallback_endpoint( $sheet_id, $gid = '0', $sheet_kind = 'doc' ) {
		if ( 'pub' === $sheet_kind ) {
			return '';
		}

		$url = add_query_arg(
			array(
				'tqx'     => 'out:csv',
				'headers' => '1',
				'gid'     => self::sanitize_gid( $gid ),
			),
			'https://docs.google.com/spreadsheets/d/' . rawurlencode( $sheet_id ) . '/gviz/tq'
		);

		/**
		 * Filters the endpoint tried when the main one will not answer.
		 *
		 * @param string $url        Download URL.
		 * @param string $sheet_id   Spreadsheet ID.
		 * @param string $gid        Tab ID.
		 * @param string $sheet_kind Document kind.
		 */
		return (string) apply_filters( 'lstab_fetch_fallback_url', $url, $sheet_id, $gid, $sheet_kind );
	}

	/**
	 * HTML view endpoint, used to discover the tab list.
	 *
	 * @param string $sheet_id   Spreadsheet ID.
	 * @param string $sheet_kind Either 'doc' or 'pub'.
	 * @return string
	 */
	public static function tabs_endpoint( $sheet_id, $sheet_kind = 'doc' ) {
		if ( 'pub' === $sheet_kind ) {
			return 'https://docs.google.com/spreadsheets/d/e/' . rawurlencode( $sheet_id ) . '/pubhtml';
		}
		return 'https://docs.google.com/spreadsheets/d/' . rawurlencode( $sheet_id ) . '/htmlview';
	}

	/**
	 * Human-facing URL of the sheet, for admin links.
	 *
	 * @param string $sheet_id   Spreadsheet ID.
	 * @param string $gid        Tab ID.
	 * @param string $sheet_kind Document kind.
	 * @return string
	 */
	public static function edit_url( $sheet_id, $gid = '0', $sheet_kind = 'doc' ) {
		if ( 'pub' === $sheet_kind ) {
			return 'https://docs.google.com/spreadsheets/d/e/' . rawurlencode( $sheet_id ) . '/pubhtml';
		}
		return 'https://docs.google.com/spreadsheets/d/' . rawurlencode( $sheet_id ) . '/edit#gid=' . self::sanitize_gid( $gid );
	}
}
