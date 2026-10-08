<?php
/**
 * Keeping a table on time when nothing else wakes WordPress up.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * The visitor's browser as the alarm clock of last resort.
 *
 * WordPress has no clock. Its schedule runs when a page is requested, and a
 * table that is overdue is also checked as its page is drawn — so on most sites
 * a table is never older than its interval when somebody looks at it, without
 * anybody setting anything up.
 *
 * A page cache breaks both at once. LiteSpeed, WP Rocket and the rest hand out
 * a stored copy of the page without starting WordPress at all, so neither the
 * schedule nor the check-on-view ever runs, and on a quiet site a table set to
 * "every 15 minutes" can sit for hours. Only a real cron job on the server
 * would fix that, and that is a thing to ask of a developer, not of somebody
 * publishing a price list.
 *
 * So the page asks for itself. After a page with a table has loaded — cached
 * or not — its script reads one small file this class keeps in the uploads
 * folder, saying when each table is next due. That file is a plain file: the
 * web server hands it out without PHP, so reading it costs about as much as an
 * image. Only when a table really is overdue does the browser make one request
 * to WordPress, in the background, and WordPress checks Google exactly as the
 * schedule would have. The visitor waits for nothing. If the sheet changed, the
 * page cache is cleared as on any other check, and the table on screen is
 * quietly swapped for the new one.
 *
 * Nothing about it is left to the visitor's clock: "now" is the time the web
 * server stamped on the file it sent. And every request that reaches WordPress
 * goes through the same lock and cooling-off as a check on view, so a hundred
 * visitors arriving the moment a table falls due still mean one request to
 * Google.
 */
class LSTAB_Freshness {

	/** The AJAX action the browser calls. */
	const ACTION = 'lstab_keep_current';

	/** The file, relative to the uploads folder. */
	const FILE = 'live-sheets-table-due.json';

	/** How long a failing sheet is left alone before a visitor may try again. */
	const RETRY_AFTER_FAILURE = 300;

	/**
	 * Whether the file needs writing before this request ends.
	 *
	 * @var bool
	 */
	protected static $dirty = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );

		// Anything that moves a table's next due time.
		add_action( 'lstab_after_sync', array( __CLASS__, 'mark_dirty' ) );
		add_action( 'lstab_sync_failed', array( __CLASS__, 'mark_dirty' ) );
		add_action( 'lstab_source_saved', array( __CLASS__, 'mark_dirty' ) );
		add_action( 'lstab_source_deleted', array( __CLASS__, 'mark_dirty' ) );

		// Written once, as the request ends: a scheduled run can check twenty
		// tables, and twenty rewrites of the same file would be nineteen too many.
		add_action( 'shutdown', array( __CLASS__, 'write_if_dirty' ) );
	}

	/**
	 * Whether a rendered table should carry what the browser needs.
	 *
	 * Only on a page a visitor sees: not in the dashboard, not in the block
	 * editor's preview (which arrives over the REST API), and never for the
	 * built-in example, which has nothing to fetch.
	 *
	 * @param array<string,mixed> $source Source row.
	 * @return bool
	 */
	public static function applies( $source ) {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || LSTAB_Example::is_example( $source ) ) {
			return false;
		}

		/**
		 * Whether a page asks to have an overdue table checked.
		 *
		 * There is no setting for it: a table set to fifteen minutes should be
		 * fifteen minutes old at most, and nobody should have to find a switch
		 * for that. The filter is for a site that runs a real cron job and
		 * would rather the browser stayed out of it.
		 *
		 * @param bool  $applies Whether to ask.
		 * @param array $source  Source row.
		 */
		return (bool) apply_filters( 'lstab_keep_current', true, $source );
	}

	/**
	 * Attributes for a rendered table's wrapper.
	 *
	 * @param array<string,mixed> $source Source row.
	 * @return array<string,string>
	 */
	public static function attributes( $source ) {
		$file = self::file();

		// The first page drawn on a site without the file yet writes it as the
		// request ends; until then the browser decides from the page alone.
		if ( '' === $file['url'] || ! file_exists( $file['path'] ) ) {
			self::mark_dirty();
		}

		return array(
			'data-lstab-ask'   => admin_url( 'admin-ajax.php' ),
			'data-lstab-due'   => ( '' !== $file['url'] && file_exists( $file['path'] ) ) ? $file['url'] : '',
			'data-lstab-copy'  => substr( (string) $source['snapshot_hash'], 0, 12 ),
			'data-lstab-next'  => (string) self::next_due( $source ),
		);
	}

	/**
	 * When a table is next worth checking, as a Unix timestamp.
	 *
	 * Measured from the last success, as the check on view is: that is what
	 * the interval promises the visitor. A sheet that is failing is left alone
	 * for a few minutes between tries rather than retried on every page.
	 *
	 * @param array<string,mixed> $source Source row.
	 * @return int
	 */
	public static function next_due( $source ) {
		$interval = max( 60, (int) $source['sync_interval'] );
		$success  = empty( $source['last_success_gmt'] ) ? 0 : (int) strtotime( $source['last_success_gmt'] . ' UTC' );
		$attempt  = empty( $source['last_attempt_gmt'] ) ? 0 : (int) strtotime( $source['last_attempt_gmt'] . ' UTC' );
		$next     = $success + $interval;

		if ( 'error' === $source['last_status'] ) {
			$next = max( $next, $attempt + min( $interval, self::RETRY_AFTER_FAILURE ) );
		}

		return $next;
	}

	/**
	 * Where the file lives.
	 *
	 * @return array{path:string,url:string}
	 */
	public static function file() {
		$uploads = wp_upload_dir( null, false );

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return array(
				'path' => '',
				'url'  => '',
			);
		}

		return array(
			'path' => trailingslashit( $uploads['basedir'] ) . self::FILE,
			'url'  => trailingslashit( set_url_scheme( $uploads['baseurl'] ) ) . self::FILE,
		);
	}

	/**
	 * Note that the file is out of date.
	 *
	 * @return void
	 */
	public static function mark_dirty() {
		self::$dirty = true;
	}

	/**
	 * Write the file if anything changed during this request.
	 *
	 * @return void
	 */
	public static function write_if_dirty() {
		if ( self::$dirty ) {
			self::$dirty = false;
			self::write();
		}
	}

	/**
	 * Write the file: for every table, a short form of its stored copy and when
	 * it is next due. Nothing from the sheet itself is in it.
	 *
	 * @return bool Whether it was written.
	 */
	public static function write() {
		// Creates the uploads folder if this site has never had one.
		wp_upload_dir();

		$file = self::file();

		if ( '' === $file['path'] || ! wp_is_writable( dirname( $file['path'] ) ) ) {
			return false;
		}

		$due = array();

		foreach ( LSTAB_Storage::get_all() as $source ) {
			if ( LSTAB_Example::is_example( $source ) ) {
				continue;
			}

			$due[ (string) $source['id'] ] = array(
				'c' => substr( (string) $source['snapshot_hash'], 0, 12 ),
				'n' => self::next_due( $source ),
			);
		}

		/*
		 * Written directly, as WordPress writes an uploaded image: the uploads
		 * folder is the one place the web server is meant to be able to write.
		 * The general filesystem layer is not used because on a host where it
		 * would ask for FTP details there is nobody here to answer; a folder
		 * that cannot be written to simply leaves the browser deciding from
		 * the page alone.
		 */
		if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}

		$direct = new WP_Filesystem_Direct( null );
		$mode   = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;

		return (bool) $direct->put_contents( $file['path'], (string) wp_json_encode( array( 't' => $due ) ), $mode );
	}

	/**
	 * Remove the file.
	 *
	 * @return void
	 */
	public static function remove() {
		$file = self::file();

		if ( '' !== $file['path'] && file_exists( $file['path'] ) ) {
			wp_delete_file( $file['path'] );
		}
	}

	/**
	 * Answer a browser that found a table overdue.
	 *
	 * Public on purpose, and without a nonce: the page asking is very often a
	 * cached copy, and a nonce printed into it would have expired long before
	 * the page stopped being served. Nothing here can be made to do anything
	 * the schedule would not have done anyway — a table that is not due is not
	 * checked, one already being checked is left alone, a failing one waits out
	 * its cooling-off — so a request that did not come from the page costs one
	 * look at the database and nothing else.
	 *
	 * @return void
	 */
	public static function handle() {
		nocache_headers();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public and idempotent by design; see the docblock.
		$asked = isset( $_POST['tables'] ) ? sanitize_text_field( wp_unslash( $_POST['tables'] ) ) : '';
		$ids   = array_slice( array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $asked ) ) ) ) ), 0, 20 );

		$changed = array();

		foreach ( $ids as $id ) {
			if ( 'changed' === LSTAB_Sync::refresh_when_asked( $id ) ) {
				$source = LSTAB_Storage::get( $id );

				$changed[ (string) $id ] = $source ? substr( (string) $source['snapshot_hash'], 0, 12 ) : '';
			}
		}

		wp_send_json( array( 'changed' => (object) $changed ) );
	}
}
