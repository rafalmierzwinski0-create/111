<?php
/**
 * Sync orchestration.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs a fetch for one source and records the outcome.
 */
class LSTAB_Sync {

	/**
	 * Sync a single source.
	 *
	 * On failure the stored snapshot is deliberately left alone, so the front
	 * end keeps serving the last good copy instead of an empty or broken table.
	 *
	 * @param int $id Source ID.
	 * @return true|WP_Error
	 */
	public static function run( $id ) {
		$source = LSTAB_Storage::get( $id );

		if ( ! $source ) {
			return new WP_Error( 'lstab_unknown_source', __( 'That sheet source no longer exists.', 'live-sheets-table' ) );
		}

		/**
		 * Fires before a source is synced.
		 *
		 * @param array $source Source row.
		 */
		do_action( 'lstab_before_sync', $source );

		$table = LSTAB_Fetcher::fetch_table(
			$source['sheet_id'],
			$source['gid'],
			$source['sheet_kind'],
			(bool) $source['first_row_header']
		);

		if ( is_wp_error( $table ) ) {
			LSTAB_Storage::record_failure( $id, $table->get_error_message() );

			/**
			 * Fires when a sync attempt fails.
			 *
			 * @param array    $source Source row.
			 * @param WP_Error $table  The failure.
			 */
			do_action( 'lstab_sync_failed', $source, $table );

			return $table;
		}

		/**
		 * Filters the parsed table before it is stored.
		 *
		 * @param array $table  Parsed table {headers, rows}.
		 * @param array $source Source row.
		 */
		$table = (array) apply_filters( 'lstab_parsed_table', $table, $source );

		LSTAB_Storage::record_success( $id, $table );

		/**
		 * Fires after a source syncs successfully.
		 *
		 * @param array $source Source row.
		 * @param array $table  Parsed table.
		 */
		do_action( 'lstab_after_sync', $source, $table );

		return true;
	}

	/**
	 * Sync every source whose interval has elapsed.
	 *
	 * @param bool $force Ignore the interval and sync everything.
	 * @return array<int,string> Map of source ID to 'ok' or an error message.
	 */
	public static function run_due( $force = false ) {
		$results = array();

		foreach ( LSTAB_Storage::get_all() as $source ) {
			if ( ! $force && ! self::is_due( $source ) ) {
				continue;
			}

			$result                    = self::run( $source['id'] );
			$results[ $source['id'] ] = is_wp_error( $result ) ? $result->get_error_message() : 'ok';
		}

		return $results;
	}

	/**
	 * Whether a source is due for a refresh.
	 *
	 * @param array<string,mixed> $source Source row.
	 * @return bool
	 */
	public static function is_due( $source ) {
		if ( empty( $source['last_attempt_gmt'] ) ) {
			return true;
		}

		$last = strtotime( $source['last_attempt_gmt'] . ' UTC' );
		if ( ! $last ) {
			return true;
		}

		$interval = max( 60, (int) $source['sync_interval'] );

		// Allow a small amount of slack so a cron tick that fires a few seconds
		// early does not push the refresh a whole interval into the future.
		return ( time() - $last ) >= ( $interval - 30 );
	}
}
