<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Url {
	public static function allowed_hosts() {
		return (array) apply_filters(
			'lstab_allowed_hosts',
			array( 'docs.google.com', 'spreadsheets.google.com' )
		);
	}

	public static function parse( $input ) {
		$input = trim( wp_unslash( (string) $input ) );

		if ( '' === $input ) {
			return new WP_Error(
				'lstab_empty_url',
				__( 'Paste the link to your Google Sheet first.', 'live-sheets-table' )
			);
		}

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

		if ( preg_match( '#/spreadsheets/(?:u/\d+/)?d/e/([a-zA-Z0-9-_]+)#', $path, $m ) ) {
			$sheet_id   = $m[1];
			$sheet_kind = 'pub';
		} elseif ( preg_match( '#/spreadsheets/(?:u/\d+/)?d/([a-zA-Z0-9-_]+)#', $path, $m ) ) {
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

	public static function sanitize_gid( $gid ) {
		$gid = preg_replace( '#[^0-9]#', '', (string) $gid );
		return ( null === $gid || '' === $gid ) ? '0' : $gid;
	}

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
			$url = add_query_arg(
				array(
					'format' => 'csv',
					'gid'    => $gid,
				),
				'https://docs.google.com/spreadsheets/d/' . rawurlencode( $sheet_id ) . '/export'
			);
		}

		return (string) apply_filters( 'lstab_fetch_url', $url, $sheet_id, $gid, $sheet_kind );
	}

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

		return (string) apply_filters( 'lstab_fetch_fallback_url', $url, $sheet_id, $gid, $sheet_kind );
	}

	public static function tabs_endpoint( $sheet_id, $sheet_kind = 'doc' ) {
		if ( 'pub' === $sheet_kind ) {
			return 'https://docs.google.com/spreadsheets/d/e/' . rawurlencode( $sheet_id ) . '/pubhtml';
		}
		return 'https://docs.google.com/spreadsheets/d/' . rawurlencode( $sheet_id ) . '/htmlview';
	}

	public static function edit_url( $sheet_id, $gid = '0', $sheet_kind = 'doc' ) {
		if ( 'pub' === $sheet_kind ) {
			return 'https://docs.google.com/spreadsheets/d/e/' . rawurlencode( $sheet_id ) . '/pubhtml';
		}
		return 'https://docs.google.com/spreadsheets/d/' . rawurlencode( $sheet_id ) . '/edit#gid=' . self::sanitize_gid( $gid );
	}
}
