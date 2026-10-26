<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Limits {
	const DEFAULT_MIN_INTERVAL = 1800;

	const MANUAL = 0;

	const SETTING_MANUAL = -1;

	public static function intervals() {
		$all = array(
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

	public static function choices() {
		return self::intervals() + array( self::MANUAL => __( 'Only when I refresh it', 'live-sheets-table' ) );
	}

	public static function label( $seconds ) {
		$choices = self::choices();
		$seconds = (int) $seconds;

		return isset( $choices[ $seconds ] ) ? $choices[ $seconds ] : $choices[ self::clamp_interval( $seconds ) ];
	}

	public static function min_interval() {
		$offered = array_keys( self::intervals() );

		return $offered ? (int) min( $offered ) : self::DEFAULT_MIN_INTERVAL;
	}

	public static function is_manual( $source ) {
		return isset( $source['sync_interval'] ) && self::MANUAL === (int) $source['sync_interval'];
	}

	public static function interval_of( $source ) {
		if ( self::is_manual( $source ) ) {
			return self::MANUAL;
		}

		return max( self::min_interval(), isset( $source['sync_interval'] ) ? (int) $source['sync_interval'] : 0 );
	}

	public static function starting_interval() {
		$chosen = (int) LSTAB_Settings::get( 'default_interval', 0 );

		if ( self::SETTING_MANUAL === $chosen ) {
			return self::MANUAL;
		}

		return 0 === $chosen ? self::min_interval() : self::clamp_interval( $chosen );
	}

	public static function clamp_interval( $seconds ) {
		$seconds = (int) $seconds;

		if ( self::MANUAL === $seconds ) {
			return self::MANUAL;
		}

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
