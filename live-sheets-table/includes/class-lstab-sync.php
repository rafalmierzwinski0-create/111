<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Sync {
	const VIEW_TIMEOUT = 4;

	public static function view_timeout() {
		$chosen = (int) LSTAB_Settings::get( 'view_timeout', self::VIEW_TIMEOUT );

		return (int) apply_filters( 'lstab_view_timeout', max( 2, min( 15, $chosen ) ) );
	}

	const VIEW_LOCK = 30;

	const VIEW_RETRY = 30;

	const COOLDOWN_PREFIX = 'lstab_view_cooldown_';

	const FAILS_PREFIX = 'lstab_view_fails_';

	protected static $view_spent = false;

	protected static function start_cooldown( $id, $interval ) {
		$fails = (int) get_transient( self::FAILS_PREFIX . $id ) + 1;
		$wait  = min( $interval, self::VIEW_RETRY * pow( 2, $fails - 1 ) );

		set_transient( self::COOLDOWN_PREFIX . $id, 1, (int) $wait );

		set_transient( self::FAILS_PREFIX . $id, $fails, (int) max( $interval, $wait * 4 ) );
	}

	protected static function end_cooldown( $id ) {
		delete_transient( self::COOLDOWN_PREFIX . $id );
		delete_transient( self::FAILS_PREFIX . $id );
	}

	public static function reset_view_budget() {
		self::$view_spent = false;
	}

	public static function since( $gmt ) {
		$then = empty( $gmt ) ? 0 : (int) strtotime( $gmt . ' UTC' );

		if ( $then <= 0 || $then > time() + 300 ) {
			return PHP_INT_MAX;
		}

		return max( 0, time() - $then );
	}

	protected static function run_within( $source, $seconds ) {
		$id       = (int) $source['id'];
		$deadline = microtime( true ) + max( 1, $seconds );

		$shorten = function ( $args ) use ( $deadline ) {
			$args['timeout'] = max( 0.5, min( isset( $args['timeout'] ) ? (float) $args['timeout'] : LSTAB_Fetcher::DEFAULT_TIMEOUT, $deadline - microtime( true ) ) );

			return $args;
		};

		$give_up = function ( $url ) use ( $deadline ) {
			return microtime( true ) < $deadline ? $url : '';
		};

		add_filter( 'lstab_fetch_args', $shorten, 99 );
		add_filter( 'lstab_fetch_fallback_url', $give_up, 99 );
		$result = self::run( $id );
		remove_filter( 'lstab_fetch_args', $shorten, 99 );
		remove_filter( 'lstab_fetch_fallback_url', $give_up, 99 );

		if ( is_wp_error( $result ) && 'lstab_http_error' === $result->get_error_code() ) {
			LSTAB_Storage::restore_status( $id, $source['last_status'], $source['last_error'] );

			if ( ! wp_next_scheduled( LSTAB_Cron::RETRY_HOOK, array( $id ) ) ) {
				wp_schedule_single_event( time(), LSTAB_Cron::RETRY_HOOK, array( $id ) );
			}
		}

		return $result;
	}

	public static function refresh_for_view( $source ) {
		if ( empty( $source['id'] ) ) {
			return $source;
		}

		if ( ! apply_filters( 'lstab_refresh_on_view', true, $source ) ) {
			return $source;
		}

		if ( wp_doing_cron() ) {
			return $source;
		}

		if ( self::$view_spent ) {
			return $source;
		}

		$id = (int) $source['id'];

		$lock = 'lstab_view_refresh_' . $id;

		if ( get_transient( $lock ) ) {
			return $source;
		}

		if ( get_transient( self::COOLDOWN_PREFIX . $id ) ) {
			return $source;
		}

		$interval = LSTAB_Limits::interval_of( $source );

		if ( self::since( $source['last_success_gmt'] ) < $interval ) {
			return $source;
		}

		self::$view_spent = true;
		set_transient( $lock, 1, self::VIEW_LOCK );

		$result = self::run_within( $source, self::view_timeout() );

		delete_transient( $lock );

		if ( is_wp_error( $result ) ) {
			self::start_cooldown( $id, $interval );

			return $source;
		}

		$fresh = LSTAB_Storage::get( (int) $source['id'] );

		return $fresh ? $fresh : $source;
	}

	public static function refresh_when_asked( $id, $seconds = 15 ) {
		$id     = (int) $id;
		$source = $id > 0 ? LSTAB_Storage::get( $id ) : null;

		if ( ! $source || LSTAB_Example::is_example( $source ) ) {
			return 'unknown';
		}

		$interval = LSTAB_Limits::interval_of( $source );

		if ( self::since( $source['last_success_gmt'] ) < ( $interval - 30 ) ) {
			return 'not-due';
		}

		$lock = 'lstab_view_refresh_' . $id;

		if ( get_transient( $lock ) || get_transient( self::COOLDOWN_PREFIX . $id ) ) {
			return 'busy';
		}

		set_transient( $lock, 1, self::VIEW_LOCK );
		$before = (string) $source['snapshot_hash'];
		$result = self::run_within( $source, $seconds );
		delete_transient( $lock );

		if ( is_wp_error( $result ) ) {
			self::start_cooldown( $id, $interval );

			return 'failed';
		}

		$after = LSTAB_Storage::get( $id );

		return ( $after && (string) $after['snapshot_hash'] !== $before ) ? 'changed' : 'unchanged';
	}

	public static function run( $id ) {
		$source = LSTAB_Storage::get( $id );

		if ( ! $source ) {
			return new WP_Error( 'lstab_unknown_source', __( 'That sheet source no longer exists.', 'live-sheets-table' ) );
		}

		if ( LSTAB_Example::is_example( $source ) ) {
			return true;
		}

		do_action( 'lstab_before_sync', $source );

		LSTAB_Storage::touch_attempt( $id );

		$table = LSTAB_Fetcher::fetch_table(
			$source['sheet_id'],
			$source['gid'],
			$source['sheet_kind'],
			(bool) $source['first_row_header']
		);

		if ( is_wp_error( $table ) ) {
			LSTAB_Storage::record_failure( $id, $table->get_error_message() );

			do_action( 'lstab_sync_failed', $source, $table );

			return $table;
		}

		$table = (array) apply_filters( 'lstab_parsed_table', $table, $source );

		$columns = LSTAB_Columns::reconcile(
			isset( $source['columns_config'] ) ? $source['columns_config'] : array(),
			isset( $table['headers'] ) ? $table['headers'] : array()
		);

		$changed = md5( (string) wp_json_encode( $table ) ) !== (string) $source['snapshot_hash'];

		LSTAB_Storage::update( $id, array( 'columns_config' => $columns ) );

		if ( ! LSTAB_Storage::record_success( $id, $table ) ) {
			$stored = new WP_Error(
				'lstab_db_write_failed',
				__( 'The sheet arrived but could not be saved in the database. It may be too large for this server.', 'live-sheets-table' )
			);
			LSTAB_Storage::record_failure( $id, $stored->get_error_message() );
			do_action( 'lstab_sync_failed', $source, $stored );

			return $stored;
		}

		self::end_cooldown( $id );

		LSTAB_Hidden_Rows::reanchor( $id, isset( $table['rows'] ) ? $table['rows'] : array() );

		if ( $changed ) {
			LSTAB_Cache::purge( (int) $id );

			do_action( 'lstab_source_changed', (int) $id, $table, $source );
		}

		do_action( 'lstab_after_sync', $source, $table );

		return true;
	}

	public static function run_due( $force = false ) {
		$results = array();
		$due     = array();

		foreach ( LSTAB_Storage::get_all() as $source ) {
			if ( $force || self::is_due( $source ) ) {
				$due[] = $source;
			}
		}

		usort(
			$due,
			static function ( $a, $b ) {
				return strcmp( (string) $a['last_attempt_gmt'], (string) $b['last_attempt_gmt'] );
			}
		);

		$budget = (int) apply_filters( 'lstab_tick_budget', 10 );
		$start  = microtime( true );

		foreach ( $due as $source ) {
			if ( ! $force && $results && microtime( true ) - $start > $budget ) {
				break;
			}

			$result                    = self::run( $source['id'] );
			$results[ $source['id'] ] = is_wp_error( $result ) ? $result->get_error_message() : 'ok';
		}

		return $results;
	}

	public static function is_due( $source ) {
		if ( LSTAB_Example::is_example( $source ) ) {
			return false;
		}

		$interval = LSTAB_Limits::interval_of( $source );

		return self::since( $source['last_attempt_gmt'] ) >= ( $interval - 30 );
	}
}
