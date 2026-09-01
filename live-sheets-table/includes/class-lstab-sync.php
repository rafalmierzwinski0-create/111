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
	 * How long one visitor is made to wait for a sheet, in seconds.
	 *
	 * Short on purpose. A stale table is a small problem; a page that hangs
	 * because Google is slow is the fault this plugin exists to avoid, and it
	 * would be a strange way to acquire it.
	 */
	const VIEW_TIMEOUT = 4;

	/**
	 * How long one attempt keeps others from trying, in seconds.
	 */
	const VIEW_LOCK = 30;

	/**
	 * Whether this request has already spent its refresh.
	 *
	 * The cap is per visitor, not per table. A page holding four tables that
	 * all wanted checking would otherwise be four caps in a row, and the
	 * visitor would wait sixteen seconds for a plugin whose entire argument is
	 * that nobody should wait for Google. One table is brought up to date and
	 * the rest are served from store; on the next visit it is another one's
	 * turn, and the scheduler covers them all regardless.
	 *
	 * @var bool
	 */
	protected static $view_spent = false;

	/**
	 * Start the refresh budget over.
	 *
	 * The budget is per page load, and a page load is one PHP request, so it
	 * normally resets by itself. Anything that renders many pages inside a
	 * single process — WP-CLI, a test harness — has to say when one page ends
	 * and the next begins, because PHP gives it no way to notice.
	 *
	 * @return void
	 */
	public static function reset_view_budget() {
		self::$view_spent = false;
	}

	/**
	 * Bring a sheet up to date while someone is looking at it.
	 *
	 * WordPress has no clock of its own: WP-Cron runs on visits, and it runs
	 * *after* the page has been sent. So the visitor whose arrival triggers a
	 * sync is the one who does not benefit from it, and on a quiet site nobody
	 * triggers one at all. This closes that gap for every table — the check
	 * happens before the page is drawn, so the person who waited for it is the
	 * person who sees it.
	 *
	 * It costs nothing on a site where the schedule is working, because there
	 * is never anything to do: the copy is younger than the interval and this
	 * returns immediately. It only ever fetches when the schedule has failed to
	 * keep up, which is exactly when someone needs it to.
	 *
	 * Four things keep it from becoming the problem it solves. Only one request
	 * fetches at a time, so ten simultaneous visitors do not become ten requests
	 * to Google. One page load buys one refresh, so a page of four tables is
	 * still one wait rather than four. The wait itself is capped, and a sheet
	 * that does not answer in time leaves the stored copy on the page. And a
	 * source that has just failed is not retried until its interval has passed,
	 * so a broken sheet costs one slow page rather than every page.
	 *
	 * @param array<string,mixed> $source Source row.
	 * @return array<string,mixed> The source, refreshed if it was worth it.
	 */
	public static function refresh_for_view( $source ) {
		if ( empty( $source['id'] ) ) {
			return $source;
		}

		/**
		 * Whether a stale table may be checked before the page is drawn.
		 *
		 * There is no setting for this, on purpose: a site owner asked whether
		 * their prices should be current has only one answer, and a checkbox
		 * that only ever gets ticked is a question not worth asking. The filter
		 * is here for the rare site that would rather serve a day-old table
		 * than ever make one visitor wait.
		 *
		 * @param bool  $allowed Whether to check.
		 * @param array $source  Source row.
		 */
		if ( ! apply_filters( 'lstab_refresh_on_view', true, $source ) ) {
			return $source;
		}

		// Cron already has its own turn at this, and nobody is waiting there.
		if ( wp_doing_cron() ) {
			return $source;
		}

		// One table's worth of waiting per page, however many tables it holds.
		if ( self::$view_spent ) {
			return $source;
		}

		// Never while another request is already doing it.
		$lock = 'lstab_view_refresh_' . (int) $source['id'];

		if ( get_transient( $lock ) ) {
			return $source;
		}

		$interval = max( 60, (int) $source['sync_interval'] );
		$attempt  = empty( $source['last_attempt_gmt'] ) ? 0 : strtotime( $source['last_attempt_gmt'] . ' UTC' );

		// Measured from the last attempt rather than the last success, so a
		// sheet that keeps failing is not fetched again on every page view.
		if ( $attempt && ( time() - $attempt ) < $interval ) {
			return $source;
		}

		self::$view_spent = true;
		set_transient( $lock, 1, self::VIEW_LOCK );

		/*
		 * The cap is a deadline for the whole attempt, not a timeout per
		 * request. A sheet whose sharing settings refuse the export endpoint is
		 * tried twice, and four seconds each would be eight seconds of waiting
		 * — in exactly the case where Google is already being slow. So each
		 * request gets what is left of the four, and once nothing is left the
		 * second endpoint is not tried at all.
		 */
		$deadline = microtime( true ) + self::VIEW_TIMEOUT;

		$shorten = function ( $args ) use ( $deadline ) {
			$args['timeout'] = max( 0.5, $deadline - microtime( true ) );

			return $args;
		};

		$give_up = function ( $url ) use ( $deadline ) {
			return microtime( true ) < $deadline ? $url : '';
		};

		add_filter( 'lstab_fetch_args', $shorten, 99 );
		add_filter( 'lstab_fetch_fallback_url', $give_up, 99 );
		$result = self::run( (int) $source['id'] );
		remove_filter( 'lstab_fetch_args', $shorten, 99 );
		remove_filter( 'lstab_fetch_fallback_url', $give_up, 99 );

		delete_transient( $lock );

		if ( is_wp_error( $result ) ) {
			/*
			 * The stored copy is still on the page, which is the whole promise.
			 *
			 * A transport error here is not evidence about the sheet: this
			 * refresh was given four seconds instead of the usual twenty, so a
			 * sheet the scheduler fetches perfectly well can miss that deadline
			 * and be reported as broken. Turning the dashboard red over a
			 * deadline of our own invention would be a fault this plugin made
			 * up. The verdict is withdrawn and left to the scheduled run, which
			 * fetches under the real timeout and is the honest test. Failures
			 * that mean the same thing at four seconds as at twenty — a 403, a
			 * sign-in page, an empty body — are recorded as failures.
			 */
			if ( 'lstab_http_error' === $result->get_error_code() ) {
				LSTAB_Storage::restore_status( (int) $source['id'], $source['last_status'], $source['last_error'] );
			}

			return $source;
		}

		$fresh = LSTAB_Storage::get( (int) $source['id'] );

		return $fresh ? $fresh : $source;
	}

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

		// Remember which heading each position actually carried, so a column
		// inserted in the sheet can be reported rather than silently shifting
		// every configured label one place along.
		$columns = LSTAB_Columns::reconcile(
			isset( $source['columns_config'] ) ? $source['columns_config'] : array(),
			isset( $table['headers'] ) ? $table['headers'] : array()
		);

		LSTAB_Storage::update( $id, array( 'columns_config' => $columns ) );
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
