<?php

defined( 'ABSPATH' ) || exit;

class LSTABP_Private_Sheets {
	const META_OPTION = 'lstabp_private_sources';

	public function register() {
		add_filter( 'lstab_fetch_url', array( $this, 'maybe_authenticated_url' ), 10, 4 );
		add_filter( 'lstab_fetch_args', array( $this, 'maybe_authorise' ), 10, 2 );

		add_action( 'lstab_before_sync', array( __CLASS__, 'remember_source' ) );

		add_action( 'lstab_source_deleted', array( __CLASS__, 'forget' ) );
		add_action( 'lstabp_forget_source', array( __CLASS__, 'forget' ) );

		add_filter( 'lstab_fetch_refused', array( $this, 'try_connected_account' ), 10, 4 );
	}

	protected static $trying = false;

	public function try_connected_account( $result, $sheet_id, $gid, $sheet_kind ) {
		if ( self::$trying || ! is_wp_error( $result ) || ! self::is_refusal( $result ) ) {
			return $result;
		}

		if ( null !== self::$current_source && self::is_private( self::$current_source ) ) {
			$token = LSTABP_Google_Auth::access_token();

			return is_wp_error( $token ) ? $token : $result;
		}

		if ( $this->current_source_is_private() || ! LSTABP_Google_Auth::is_connected() ) {
			return $result;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return $result;
		}

		self::$trying = true;
		$second       = LSTAB_Fetcher::fetch_csv( $sheet_id, $gid, $sheet_kind );
		self::$trying = false;

		if ( is_wp_error( $second ) ) {
			return $result;
		}

		if ( null !== self::$current_source ) {
			self::set_private( self::$current_source, true );
		}

		return $second;
	}

	protected static function is_refusal( $error ) {
		if ( 'lstab_not_public' === $error->get_error_code() ) {
			return true;
		}

		$data = $error->get_error_data();

		return 'lstab_http_status' === $error->get_error_code()
			&& is_array( $data )
			&& isset( $data['status'] )
			&& in_array( (int) $data['status'], array( 401, 403, 404 ), true );
	}

	public static function forget( $source_id ) {
		$stored = (array) get_option( self::META_OPTION, array() );

		if ( isset( $stored[ (int) $source_id ] ) ) {
			unset( $stored[ (int) $source_id ] );
			update_option( self::META_OPTION, $stored, false );
		}
	}

	public static function private_sources() {
		$stored = (array) get_option( self::META_OPTION, array() );

		if ( ! $stored ) {
			return array();
		}

		$private = array();

		foreach ( LSTAB_Storage::get_all() as $source ) {
			$id = (int) $source['id'];

			if ( ! isset( $stored[ $id ] ) ) {
				continue;
			}

			if ( true === $stored[ $id ] || (string) $stored[ $id ] === self::stamp( $source ) ) {
				$private[ $id ] = true;
			}
		}

		return $private;
	}

	protected static function stamp( $source ) {
		return (string) $source['created_gmt'] . '|' . (string) $source['sheet_id'];
	}

	public static function set_private( $source_id, $private ) {
		$stored  = array();
		$current = self::private_sources();

		foreach ( LSTAB_Storage::get_all() as $source ) {
			$id = (int) $source['id'];

			if ( $id === (int) $source_id ) {
				if ( $private ) {
					$stored[ $id ] = self::stamp( $source );
				}
			} elseif ( isset( $current[ $id ] ) ) {
				$stored[ $id ] = self::stamp( $source );
			}
		}

		update_option( self::META_OPTION, $stored, false );
	}

	public static function is_private( $source_id ) {
		$sources = self::private_sources();

		return ! empty( $sources[ (int) $source_id ] );
	}

	public function maybe_authenticated_url( $url, $sheet_id, $gid, $sheet_kind ) {
		if ( ! $this->applies( $sheet_id, $url ) ) {
			return $url;
		}

		return add_query_arg(
			array(
				'format' => 'csv',
				'gid'    => rawurlencode( $gid ),
			),
			'https://docs.google.com/spreadsheets/d/' . rawurlencode( $sheet_id ) . '/export'
		);
	}

	public function maybe_authorise( $args, $url ) {
		if ( false === strpos( $url, '/export' ) || ! ( self::$trying || $this->current_source_is_private() ) ) {
			return $args;
		}

		$token = LSTABP_Google_Auth::access_token();

		if ( is_wp_error( $token ) ) {
			return $args;
		}

		$args['headers'] = isset( $args['headers'] ) ? (array) $args['headers'] : array();
		$args['headers']['Authorization'] = 'Bearer ' . $token;

		return $args;
	}

	protected static $current_source = null;

	public static function remember_source( $source ) {
		self::$current_source = isset( $source['id'] ) ? (int) $source['id'] : null;
	}

	protected function current_source_is_private() {
		return null !== self::$current_source && self::is_private( self::$current_source );
	}

	protected function applies( $sheet_id, $url ) {
		unset( $sheet_id, $url );

		return ( self::$trying || $this->current_source_is_private() ) && LSTABP_Google_Auth::is_connected();
	}
}
