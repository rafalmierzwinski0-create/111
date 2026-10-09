<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Limits {
	const DEFAULT_MAX_SOURCES  = 6;
	const DEFAULT_MIN_INTERVAL = 900;

	const GRACE_DAYS = 10;

	const SEEN_OPTION = 'lstab_pro_last_seen';

	public static function is_pro() {
		return (bool) apply_filters( 'lstab_is_pro', false );
	}

	public static function pro_effective() {
		if ( self::is_pro() ) {
			return true;
		}

		return self::grace_remaining() > 0;
	}

	public static function grace_remaining() {
		$seen = (int) get_option( self::SEEN_OPTION, 0 );

		if ( $seen <= 0 ) {
			return 0;
		}

		$ends = $seen + ( self::GRACE_DAYS * DAY_IN_SECONDS );

		return $ends > time() ? $ends - time() : 0;
	}

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

	public static function max_sources() {
		$max = (int) apply_filters( 'lstab_max_sources', self::DEFAULT_MAX_SOURCES );
		return $max < 1 ? 1 : $max;
	}

	public static function min_interval() {
		$min = (int) apply_filters( 'lstab_min_sync_interval', self::DEFAULT_MIN_INTERVAL );
		return $min < 60 ? 60 : $min;
	}

	public static function can_add_source() {
		return LSTAB_Storage::count_sources() < self::max_sources();
	}

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

	public static function interval_of( $source ) {
		return max( 60, self::min_interval(), isset( $source['sync_interval'] ) ? (int) $source['sync_interval'] : 0 );
	}

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

	public static function capability() {
		return (string) apply_filters( 'lstab_manage_capability', 'manage_options' );
	}

	public static function upgrade_url() {
		return (string) apply_filters( 'lstab_upgrade_url', 'https://example.com/live-sheets-table/pro/' );
	}
}
