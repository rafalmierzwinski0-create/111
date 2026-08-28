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

	const DEFAULT_MAX_SOURCES  = 1;
	const DEFAULT_MIN_INTERVAL = 900;

	/**
	 * Whether a Pro add-on is active.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		return (bool) apply_filters( 'lstab_is_pro', false );
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
