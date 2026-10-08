<?php
/**
 * Lengths of time in the plugin's own words.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * The plugin speaks English and only English: there is no language setting,
 * and no translations ship with it. Its strings still go through gettext, so a
 * translation added to WordPress later is picked up the ordinary way.
 *
 * What is left here is the one thing that needed help to stay in the plugin's
 * language on a site set to another one: a length of time.
 */
class LSTAB_Locale {

	/**
	 * A length of time, in the plugin's own words.
	 *
	 * WordPress's own human_time_diff() answers in the site's language, which
	 * would leave "1 tydzień" sitting in the middle of an English sentence on
	 * a site set to Polish. Only this plugin's own durations come through
	 * here; core's own function is left alone, so nobody else's dates change.
	 *
	 * The thresholds are core's, so the two never disagree about whether
	 * something is a day old or a week old.
	 *
	 * @param int $from Timestamp.
	 * @param int $to   Timestamp. Defaults to now.
	 * @return string
	 */
	public static function span( $from, $to = 0 ) {
		$to   = $to ? (int) $to : time();
		$diff = (int) abs( $to - (int) $from );

		if ( $diff < MINUTE_IN_SECONDS ) {
			$count = max( 1, $diff );
			/* translators: %s: number of seconds. */
			$said = _n( '%s second', '%s seconds', $count, 'live-sheets-table' );
		} elseif ( $diff < HOUR_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / MINUTE_IN_SECONDS ) );
			/* translators: %s: number of minutes. */
			$said = _n( '%s minute', '%s minutes', $count, 'live-sheets-table' );
		} elseif ( $diff < DAY_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / HOUR_IN_SECONDS ) );
			/* translators: %s: number of hours. */
			$said = _n( '%s hour', '%s hours', $count, 'live-sheets-table' );
		} elseif ( $diff < WEEK_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / DAY_IN_SECONDS ) );
			/* translators: %s: number of days. */
			$said = _n( '%s day', '%s days', $count, 'live-sheets-table' );
		} elseif ( $diff < MONTH_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / WEEK_IN_SECONDS ) );
			/* translators: %s: number of weeks. */
			$said = _n( '%s week', '%s weeks', $count, 'live-sheets-table' );
		} elseif ( $diff < YEAR_IN_SECONDS ) {
			$count = max( 1, (int) round( $diff / MONTH_IN_SECONDS ) );
			/* translators: %s: number of months. */
			$said = _n( '%s month', '%s months', $count, 'live-sheets-table' );
		} else {
			$count = max( 1, (int) round( $diff / YEAR_IN_SECONDS ) );
			/* translators: %s: number of years. */
			$said = _n( '%s year', '%s years', $count, 'live-sheets-table' );
		}

		return sprintf( $said, number_format_i18n( $count ) );
	}
}
