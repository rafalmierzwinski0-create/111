<?php
/**
 * WP-Cron scheduling.
 *
 * A single recurring "tick" drives every source. The tick runs at the shortest
 * interval any source asks for, and each source is refreshed only once its own
 * interval has elapsed. That keeps one event in the schedule no matter how many
 * sources exist, and makes a per-source interval change a no-op reschedule.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cron controller.
 */
class LSTAB_Cron {

	const TICK_HOOK    = 'lstab_sync_tick';
	const TICK_OPTION  = 'lstab_tick_schedule';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval
		add_action( self::TICK_HOOK, array( $this, 'run_tick' ) );
		add_action( 'lstab_source_saved', array( __CLASS__, 'ensure_scheduled' ) );
		add_action( 'lstab_source_deleted', array( __CLASS__, 'ensure_scheduled' ) );
	}

	/**
	 * Add the custom recurrences the plugin offers.
	 *
	 * @param array<string,array<string,mixed>> $schedules Existing schedules.
	 * @return array<string,array<string,mixed>>
	 */
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
					human_time_diff( 0, $seconds )
				),
			);
		}

		return $schedules;
	}

	/**
	 * Schedule slugs mapped to their length in seconds.
	 *
	 * @return array<string,int>
	 */
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

	/**
	 * Pick the schedule slug that matches the shortest configured interval.
	 *
	 * @return string
	 */
	public static function required_schedule() {
		$shortest = 0;

		foreach ( LSTAB_Storage::get_all() as $source ) {
			$interval = max( 60, (int) $source['sync_interval'] );
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

	/**
	 * Make sure the tick is scheduled at the right recurrence.
	 *
	 * @return void
	 */
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

	/**
	 * Remove every queued tick.
	 *
	 * @return void
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::TICK_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::TICK_HOOK );
			$timestamp = wp_next_scheduled( self::TICK_HOOK );
		}
		delete_option( self::TICK_OPTION );
	}

	/**
	 * Cron callback: refresh whatever is due.
	 *
	 * @return void
	 */
	public function run_tick() {
		LSTAB_Sync::run_due();
	}
}
