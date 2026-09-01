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
	const RETRY_HOOK    = 'lstab_sync_source';
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
		add_action( self::RETRY_HOOK, array( __CLASS__, 'run_source' ) );
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
	 * Sync one named source in the background.
	 *
	 * Queued when a check made while a visitor waited ran out of its four
	 * seconds. This runs in a request of its own, after the page has gone, so
	 * it has the full timeout — which is the difference between a large sheet
	 * never being refreshed by a visit and being refreshed moments after one.
	 *
	 * @param int $id Source ID.
	 * @return void
	 */
	public static function run_source( $id ) {
		LSTAB_Sync::run( (int) $id );
	}

	/**
	 * Whether the tables are actually being kept up to date.
	 *
	 * This used to report on the mechanism: whether WP-Cron was switched off,
	 * whether the event was scheduled. That was the wrong thing to look at.
	 * DISABLE_WP_CRON in wp-config.php is the normal setup on any host that
	 * runs a real system cron, so the plugin was warning perfectly healthy
	 * sites about a fault they did not have — and a warning that fires when
	 * nothing is wrong teaches people to ignore warnings.
	 *
	 * What matters to the site owner is whether the data on their pages is
	 * current, and that can simply be measured. A site whose sheets are fresh
	 * is fine however that happened — background schedule, system cron, or a
	 * visitor's own page load. A site whose sheets have fallen well behind has
	 * a real problem worth naming, whatever the configuration says.
	 *
	 * @return array{state:string,message:string,detail:string}
	 */
	public static function health() {
		$worst     = null;
		$worst_age = 0;

		foreach ( LSTAB_Storage::get_all() as $source ) {
			// A source that has never synced is reported where it is listed,
			// and by the table itself. Nothing to add here.
			if ( empty( $source['last_success_gmt'] ) ) {
				continue;
			}

			$interval = max( 60, (int) $source['sync_interval'] );
			$age      = time() - (int) strtotime( $source['last_success_gmt'] . ' UTC' );

			// Three missed rounds is past any reasonable jitter on a quiet
			// site, and an hour keeps a one-minute interval from crying wolf.
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
				'detail'  => '',
			);
		}

		return array(
			'state'   => 'stale',
			'message' => sprintf(
				/* translators: 1: source title, 2: human readable time difference, e.g. "2 hours". */
				__( '“%1$s” has not been refreshed for %2$s.', 'live-sheets-table' ),
				$worst['title'],
				human_time_diff( time() - $worst_age, time() )
			),
			'detail'  => __( 'Your pages are still showing the last copy that arrived, so nothing is broken for visitors. But WordPress runs scheduled work only when someone visits the site, so a quiet site falls behind — and on a site that should be busy this usually means something is stopping it: a security plugin, a page cache answering every request without running WordPress, or a host that switches scheduling off without replacing it.', 'live-sheets-table' ),
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
