<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Cron {
	const TICK_HOOK     = 'lstab_sync_tick';
	const RETRY_HOOK    = 'lstab_sync_source';
	const TICK_OPTION   = 'lstab_tick_schedule';
	const LAST_TICK_OPT = 'lstab_last_tick';

	public function register() {
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval
		add_action( self::TICK_HOOK, array( $this, 'run_tick' ) );
		add_action( self::RETRY_HOOK, array( __CLASS__, 'run_source' ) );
		add_action( 'lstab_source_saved', array( __CLASS__, 'ensure_scheduled' ) );
		add_action( 'lstab_source_deleted', array( __CLASS__, 'ensure_scheduled' ) );
	}

	public function add_schedules( $schedules ) {
		foreach ( self::schedule_map() as $slug => $seconds ) {
			if ( isset( $schedules[ $slug ] ) ) {
				continue;
			}
			$schedules[ $slug ] = array(
				'interval' => $seconds,
				'display'  => sprintf(
					/* translators: %s: human readable duration, e.g. "15 minutes". */
					__( 'Live Sheets Table: every %s', 'live-sheets-table' ),
					LSTAB_Locale::span( 0, $seconds )
				),
			);
		}

		return $schedules;
	}

	public static function schedule_map() {
		return array(
			'lstab_1min'  => 60,
			'lstab_5min'  => 300,
			'lstab_15min' => 900,
			'lstab_30min' => 1800,
			'lstab_1hour' => 3600,
			'lstab_6hour' => 21600,
			'lstab_1day'  => DAY_IN_SECONDS,
		);
	}

	public static function required_schedule() {
		$shortest = 0;

		foreach ( LSTAB_Storage::get_all() as $source ) {
			$interval = LSTAB_Limits::interval_of( $source );
			if ( 0 === $shortest || $interval < $shortest ) {
				$shortest = $interval;
			}
		}

		if ( 0 === $shortest ) {
			$shortest = LSTAB_Limits::min_interval();
		}

		$best = 'lstab_15min';
		foreach ( self::schedule_map() as $slug => $seconds ) {
			if ( $seconds <= $shortest ) {
				$best = $slug;
			}
		}

		return $best;
	}

	public static function ensure_scheduled() {
		$needed  = self::required_schedule();
		$current = get_option( self::TICK_OPTION );
		$next    = wp_next_scheduled( self::TICK_HOOK );

		if ( $next && $current === $needed ) {
			return;
		}

		self::unschedule();
		wp_schedule_event( time() + 60, $needed, self::TICK_HOOK );
		update_option( self::TICK_OPTION, $needed );
	}

	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::TICK_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::TICK_HOOK );
			$timestamp = wp_next_scheduled( self::TICK_HOOK );
		}
		delete_option( self::TICK_OPTION );
		delete_option( self::LAST_TICK_OPT );
	}

	public function run_tick() {
		update_option( self::LAST_TICK_OPT, time(), false );

		LSTAB_Sync::run_due();
	}

	public static function run_source( $id ) {
		LSTAB_Sync::run( (int) $id );
	}

	public static function health() {
		$worst     = null;
		$worst_age = 0;

		foreach ( LSTAB_Storage::get_all() as $source ) {
			if ( LSTAB_Example::is_example( $source ) ) {
				continue;
			}

			if ( empty( $source['last_success_gmt'] ) ) {
				continue;
			}

			if ( 'error' === $source['last_status'] ) {
				continue;
			}

			$interval = LSTAB_Limits::interval_of( $source );
			$age      = time() - (int) strtotime( $source['last_success_gmt'] . ' UTC' );

			if ( $age <= max( 3 * $interval, HOUR_IN_SECONDS ) ) {
				continue;
			}

			if ( $age > $worst_age ) {
				$worst     = $source;
				$worst_age = $age;
			}
		}

		if ( ! $worst ) {
			return array(
				'state'   => 'ok',
				'message' => '',
				'calm'    => '',
				'detail'  => '',
			);
		}

		return array(
			'state'   => 'stale',
			'message' => sprintf(
				/* translators: 1: source title, 2: human readable time difference, e.g. "2 hours". */
				__( '“%1$s” has not been refreshed for %2$s.', 'live-sheets-table' ),
				$worst['title'],
				LSTAB_Locale::span( time() - $worst_age, time() )
			),
			'calm'    => __( 'Your pages still show the last copy that arrived, so nothing is broken for visitors.', 'live-sheets-table' ),
			'detail'  => __( 'WordPress runs scheduled work only when a page is requested, so a quiet site falls behind. On a busy site the usual causes are a page cache, a security plugin, or scheduling disabled by the host.', 'live-sheets-table' ),
		);
	}

	public static function system_cron_line() {
		$expressions = array(
			60    => '* * * * *',
			300   => '*/5 * * * *',
			900   => '*/15 * * * *',
			1800  => '*/30 * * * *',
			3600  => '0 * * * *',
			21600 => '0 */6 * * *',
		);

		$interval   = self::current_interval();
		$expression = isset( $expressions[ $interval ] ) ? $expressions[ $interval ] : '0 3 * * *';
		$url        = site_url( 'wp-cron.php?doing_wp_cron' );

		return $expression . ' curl -s ' . $url . ' >/dev/null 2>&1';
	}

	public static function current_interval() {
		$slug      = (string) get_option( self::TICK_OPTION );
		$schedules = self::schedule_map();

		return isset( $schedules[ $slug ] ) ? $schedules[ $slug ] : 900;
	}
}
