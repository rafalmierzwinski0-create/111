<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Locale {
	public static function span_words() {
		return array(
			/* translators: %s: number of seconds. */
			array( _n( '%s second', '%s seconds', 1, 'tabliko' ), _n( '%s second', '%s seconds', 2, 'tabliko' ) ),
			/* translators: %s: number of minutes. */
			array( _n( '%s minute', '%s minutes', 1, 'tabliko' ), _n( '%s minute', '%s minutes', 2, 'tabliko' ) ),
			/* translators: %s: number of hours. */
			array( _n( '%s hour', '%s hours', 1, 'tabliko' ), _n( '%s hour', '%s hours', 2, 'tabliko' ) ),
			/* translators: %s: number of days. */
			array( _n( '%s day', '%s days', 1, 'tabliko' ), _n( '%s day', '%s days', 2, 'tabliko' ) ),
			/* translators: %s: number of weeks. */
			array( _n( '%s week', '%s weeks', 1, 'tabliko' ), _n( '%s week', '%s weeks', 2, 'tabliko' ) ),
			/* translators: %s: number of months. */
			array( _n( '%s month', '%s months', 1, 'tabliko' ), _n( '%s month', '%s months', 2, 'tabliko' ) ),
			/* translators: %s: number of years. */
			array( _n( '%s year', '%s years', 1, 'tabliko' ), _n( '%s year', '%s years', 2, 'tabliko' ) ),
		);
	}

	public static function span( $from, $to = 0 ) {
		$to   = $to ? (int) $to : time();
		$diff = (int) abs( $to - (int) $from );

		if ( $diff < MINUTE_IN_SECONDS ) {
			$count = max( 1, $diff );
			/* translators: %s: number of seconds. */
			$said = _n( '%s second', '%s seconds', $count, 'tabliko' );
		} elseif ( $diff < HOUR_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / MINUTE_IN_SECONDS ) );
			/* translators: %s: number of minutes. */
			$said = _n( '%s minute', '%s minutes', $count, 'tabliko' );
		} elseif ( $diff < DAY_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / HOUR_IN_SECONDS ) );
			/* translators: %s: number of hours. */
			$said = _n( '%s hour', '%s hours', $count, 'tabliko' );
		} elseif ( $diff < WEEK_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / DAY_IN_SECONDS ) );
			/* translators: %s: number of days. */
			$said = _n( '%s day', '%s days', $count, 'tabliko' );
		} elseif ( $diff < MONTH_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / WEEK_IN_SECONDS ) );
			/* translators: %s: number of weeks. */
			$said = _n( '%s week', '%s weeks', $count, 'tabliko' );
		} elseif ( $diff < YEAR_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / MONTH_IN_SECONDS ) );
			/* translators: %s: number of months. */
			$said = _n( '%s month', '%s months', $count, 'tabliko' );
		} else {
			$count = max( 1, (int) round( $diff / YEAR_IN_SECONDS ) );
			/* translators: %s: number of years. */
			$said = _n( '%s year', '%s years', $count, 'tabliko' );
		}

		return sprintf( $said, number_format_i18n( $count ) );
	}
}
