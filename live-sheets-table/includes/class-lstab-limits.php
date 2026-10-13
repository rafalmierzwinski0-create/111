<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Limits {
	const DEFAULT_MIN_INTERVAL = 900;

	public static function intervals() {
		$all = array(
			900   => __( 'Every 15 minutes', 'live-sheets-table' ),
			1800  => __( 'Every 30 minutes', 'live-sheets-table' ),
			3600  => __( 'Hourly', 'live-sheets-table' ),
			21600 => __( 'Every 6 hours', 'live-sheets-table' ),
			86400 => __( 'Daily', 'live-sheets-table' ),
		);

		$offered = array();

		foreach ( (array) apply_filters( 'lstab_sync_intervals', $all ) as $seconds => $label ) {
			if ( (int) $seconds >= 60 ) {
				$offered[ (int) $seconds ] = (string) $label;
			}
		}

		if ( ! $offered ) {
			$offered = $all;
		}

		ksort( $offered );

		return $offered;
	}

	public static function min_interval() {
		$offered = array_keys( self::intervals() );

		return $offered ? (int) min( $offered ) : self::DEFAULT_MIN_INTERVAL;
	}

	public static function interval_of( $source ) {
		return max( self::min_interval(), isset( $source['sync_interval'] ) ? (int) $source['sync_interval'] : 0 );
	}

	public static function clamp_interval( $seconds ) {
		$seconds = (int) $seconds;
		$allowed = array_keys( self::intervals() );
		$min     = self::min_interval();
		if ( $seconds < $min ) {
			$seconds = $min;
		}
		if ( in_array( $seconds, $allowed, true ) ) {
			return $seconds;
		}
		foreach ( $allowed as $candidate ) {
			if ( $candidate >= $seconds ) {
				return $candidate;
			}
		}
		return $allowed ? (int) max( $allowed ) : $min;
	}

	public static function capability() {
		return (string) apply_filters( 'lstab_manage_capability', 'manage_options' );
	}
}
