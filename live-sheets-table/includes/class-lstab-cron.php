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

	const TICK_HOOK     = 'lstab_sync_tick';
	const TICK_OPTION   = 'lstab_tick_schedule';
	const LAST_TICK_OPT = 'lstab_last_tick';

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
		delete_option( self::LAST_TICK_OPT );
	}

	/**
	 * Cron callback: refresh whatever is due.
	 *
	 * @return void
	 */
	public function run_tick() {
		// Recorded even when nothing was due: this is the proof that the
		// scheduler is running at all, which is what health() reports on.
		update_option( self::LAST_TICK_OPT, time(), false );

		LSTAB_Sync::run_due();
	}

	/**
	 * Whether the scheduler is actually running.
	 *
	 * A broken WP-Cron does not break the site: pages keep rendering the stored
	 * copy. It just quietly stops updating, and the first person to notice is
	 * usually the site owner's customer. This is what turns that into a notice
	 * in the dashboard instead.
	 *
	 * @return array{state:string,message:string,detail:string}
	 */
	public static function health() {
		$sources = LSTAB_Storage::get_all();

		if ( ! $sources ) {
			return array(
				'state'   => 'ok',
				'message' => '',
				'detail'  => '',
			);
		}

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return array(
				'state'   => 'disabled',
				'message' => __( 'Scheduled syncing is switched off on this site.', 'live-sheets-table' ),
				'detail'  => __( 'DISABLE_WP_CRON is set in wp-config.php, which is normal on hosts that run a real system cron. Your tables are still kept up to date either way: a table older than its interval is checked as the page is drawn. Background checking is simply the tidier way to do it, because then nobody waits.', 'live-sheets-table' ),
			);
		}

		if ( ! wp_next_scheduled( self::TICK_HOOK ) ) {
			return array(
				'state'   => 'unscheduled',
				'message' => __( 'The sync schedule is missing.', 'live-sheets-table' ),
				'detail'  => __( 'Another plugin or a maintenance tool may have cleared it. Saving any sheet source restores it.', 'live-sheets-table' ),
			);
		}

		$last = (int) get_option( self::LAST_TICK_OPT, 0 );

		if ( ! $last ) {
			// Nothing has run yet. Only a concern once the first run is overdue.
			$next = (int) wp_next_scheduled( self::TICK_HOOK );
			if ( $next && $next > time() - HOUR_IN_SECONDS ) {
				return array(
					'state'   => 'ok',
					'message' => '',
					'detail'  => '',
				);
			}
		}

		$interval = self::current_interval();
		$overdue  = time() - ( $last ? $last : (int) wp_next_scheduled( self::TICK_HOOK ) );

		// Three missed cycles is past any reasonable jitter on a quiet site.
		if ( $overdue > max( 3 * $interval, HOUR_IN_SECONDS ) ) {
			return array(
				'state'   => 'stalled',
				'message' => sprintf(
					/* translators: %s: human readable time difference, e.g. "2 hours". */
					__( 'Sheets have not been checked for %s.', 'live-sheets-table' ),
					human_time_diff( $last ? $last : time() - $overdue, time() )
				),
				'detail'  => __( 'WordPress runs scheduled work when someone visits the site, so a quiet site can fall behind. On a site that should be busy this usually means WP-Cron is blocked — by a security plugin, a page cache serving every request, or a host that disables it. Your tables are not out of date because of it: one is checked as the page is drawn whenever it is older than its interval. It just means a visitor occasionally does the waiting that the schedule should have done for them.', 'live-sheets-table' ),
			);
		}

		return array(
			'state'   => 'ok',
			'message' => '',
			'detail'  => '',
		);
	}

	/**
	 * The system-cron line that would drive this site's schedule.
	 *
	 * WordPress has no clock. Its schedule runs when someone visits, which
	 * means a site nobody visits never checks anything — no plugin can fix
	 * that from inside PHP, because no PHP runs. What can be fixed is the
	 * asking: a site owner told to "set up a system cron" has to go and work
	 * out what to type, while a line built from their own address can be
	 * pasted into a hosting panel as it stands.
	 *
	 * @return string
	 */
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

	/**
	 * Length of the currently scheduled tick, in seconds.
	 *
	 * @return int
	 */
	public static function current_interval() {
		$slug      = (string) get_option( self::TICK_OPTION );
		$schedules = self::schedule_map();

		return isset( $schedules[ $slug ] ) ? $schedules[ $slug ] : 900;
	}
}
