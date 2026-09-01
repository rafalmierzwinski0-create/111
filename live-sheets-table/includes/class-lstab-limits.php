<?php
/**
 * Free/Pro capability boundaries and extension points.
 *
 * Every limit lives behind a filter so the Pro add-on can lift it without
 * touching (or forking) the free plugin.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Limits registry.
 */
class LSTAB_Limits {

	const DEFAULT_MAX_SOURCES  = 6;
	const DEFAULT_MIN_INTERVAL = 900;

	/**
	 * How long paid-for choices keep working after the add-on stops, in days.
	 */
	const GRACE_DAYS = 10;

	/**
	 * When the add-on was last seen running.
	 */
	const SEEN_OPTION = 'lstab_pro_last_seen';

	/**
	 * Whether a Pro add-on is active.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		return (bool) apply_filters( 'lstab_is_pro', false );
	}

	/**
	 * Whether choices only the add-on can make are still being honoured.
	 *
	 * A licence ending on a Tuesday should not rearrange a public page on the
	 * Tuesday. Hidden columns and rows keep working for ten days after the
	 * add-on stops, which is time enough to notice, renew, or take the choice
	 * out deliberately. After that they are released, and the pages show
	 * everything the sheet holds again.
	 *
	 * @return bool
	 */
	public static function pro_effective() {
		if ( self::is_pro() ) {
			return true;
		}

		return self::grace_remaining() > 0;
	}

	/**
	 * Seconds left of the grace period, or 0 when there is none.
	 *
	 * @return int
	 */
	public static function grace_remaining() {
		$seen = (int) get_option( self::SEEN_OPTION, 0 );

		if ( $seen <= 0 ) {
			return 0;
		}

		$ends = $seen + ( self::GRACE_DAYS * DAY_IN_SECONDS );

		return $ends > time() ? $ends - time() : 0;
	}

	/**
	 * Note that the add-on is running, at most once a day.
	 *
	 * Called on admin page loads rather than on every request: the clock only
	 * needs to be accurate to the day, and an option write on the front end of
	 * a busy site to record something nobody asked about would be a poor trade.
	 *
	 * @return void
	 */
	public static function note_pro_seen() {
		if ( ! self::is_pro() ) {
			return;
		}

		$seen = (int) get_option( self::SEEN_OPTION, 0 );

		if ( $seen > time() - DAY_IN_SECONDS ) {
			return;
		}

		update_option( self::SEEN_OPTION, time(), true );
	}

	/**
	 * Maximum number of saved sources.
	 *
	 * @return int
	 */
	public static function max_sources() {
		$max = (int) apply_filters( 'lstab_max_sources', self::DEFAULT_MAX_SOURCES );
		return $max < 1 ? 1 : $max;
	}

	/**
	 * Shortest sync interval, in seconds.
	 *
	 * @return int
	 */
	public static function min_interval() {
		$min = (int) apply_filters( 'lstab_min_sync_interval', self::DEFAULT_MIN_INTERVAL );
		return $min < 60 ? 60 : $min;
	}

	/**
	 * Whether another source may still be created.
	 *
	 * @return bool
	 */
	public static function can_add_source() {
		return LSTAB_Storage::count_sources() < self::max_sources();
	}

	/**
	 * Selectable sync intervals, keyed by seconds.
	 *
	 * @return array<int,string>
	 */
	public static function intervals() {
		$all = array(
			60    => __( 'Every minute', 'live-sheets-table' ),
			300   => __( 'Every 5 minutes', 'live-sheets-table' ),
			900   => __( 'Every 15 minutes', 'live-sheets-table' ),
			1800  => __( 'Every 30 minutes', 'live-sheets-table' ),
			3600  => __( 'Hourly', 'live-sheets-table' ),
			21600 => __( 'Every 6 hours', 'live-sheets-table' ),
			86400 => __( 'Daily', 'live-sheets-table' ),
		);

		$min = self::min_interval();
		foreach ( array_keys( $all ) as $seconds ) {
			if ( $seconds < $min ) {
				unset( $all[ $seconds ] );
			}
		}

		return apply_filters( 'lstab_sync_intervals', $all );
	}

	/**
	 * Clamp a requested interval to what the current tier allows.
	 *
	 * @param int $seconds Requested interval.
	 * @return int
	 */
	public static function clamp_interval( $seconds ) {
		$seconds   = (int) $seconds;
		$allowed   = array_keys( self::intervals() );
		$min       = self::min_interval();
		if ( $seconds < $min ) {
			$seconds = $min;
		}
		if ( in_array( $seconds, $allowed, true ) ) {
			return $seconds;
		}
		sort( $allowed );
		foreach ( $allowed as $candidate ) {
			if ( $candidate >= $seconds ) {
				return $candidate;
			}
		}
		return $min;
	}

	/**
	 * Capability required to manage sources.
	 *
	 * @return string
	 */
	public static function capability() {
		return (string) apply_filters( 'lstab_manage_capability', 'manage_options' );
	}

	/**
	 * URL shown on upsell markers.
	 *
	 * @return string
	 */
	public static function upgrade_url() {
		return (string) apply_filters( 'lstab_upgrade_url', 'https://example.com/live-sheets-table/pro/' );
	}
}
