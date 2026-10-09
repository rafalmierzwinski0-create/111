<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Freshness {
	const ACTION = 'lstab_keep_current';

	const FILE = 'live-sheets-table-due.json';

	const RETRY_AFTER_FAILURE = 300;

	protected static $dirty = false;

	public function register() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );

		add_action( 'lstab_after_sync', array( __CLASS__, 'mark_dirty' ) );
		add_action( 'lstab_sync_failed', array( __CLASS__, 'mark_dirty' ) );
		add_action( 'lstab_source_saved', array( __CLASS__, 'mark_dirty' ) );
		add_action( 'lstab_source_deleted', array( __CLASS__, 'mark_dirty' ) );

		add_action( 'shutdown', array( __CLASS__, 'write_if_dirty' ) );
	}

	public static function applies( $source ) {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || LSTAB_Example::is_example( $source ) ) {
			return false;
		}

		return (bool) apply_filters( 'lstab_keep_current', true, $source );
	}

	public static function attributes( $source ) {
		$file = self::file();

		$have = '' !== $file['url'] && file_exists( $file['path'] );

		if ( ! $have && ! get_transient( 'lstab_due_unwritable' ) ) {
			self::mark_dirty();
		}

		return array(
			'data-lstab-ask'   => admin_url( 'admin-ajax.php', 'relative' ),
			'data-lstab-due'   => $have ? wp_make_link_relative( $file['url'] ) : '',
			'data-lstab-copy'  => substr( (string) $source['snapshot_hash'], 0, 12 ),
			'data-lstab-next'  => (string) self::next_due( $source ),
		);
	}

	public static function next_due( $source ) {
		$interval = LSTAB_Limits::interval_of( $source );
		$success  = LSTAB_Sync::since( $source['last_success_gmt'] );
		$next     = PHP_INT_MAX === $success ? time() : time() - $success + $interval;

		if ( 'error' === $source['last_status'] ) {
			$attempt = LSTAB_Sync::since( $source['last_attempt_gmt'] );

			if ( PHP_INT_MAX !== $attempt ) {
				$next = max( $next, time() - $attempt + min( $interval, self::RETRY_AFTER_FAILURE ) );
			}
		}

		return $next;
	}

	public static function checked_at( $source ) {
		$age = LSTAB_Sync::since( isset( $source['last_success_gmt'] ) ? $source['last_success_gmt'] : '' );

		return PHP_INT_MAX === $age ? 0 : time() - $age;
	}

	const ASK_BUDGET = 15;

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

	public static function mark_dirty() {
		self::$dirty = true;
	}

	public static function write_if_dirty() {
		if ( self::$dirty ) {
			self::$dirty = false;
			self::write();
		}
	}

	public static function write() {
		wp_upload_dir();

		$file = self::file();

		if ( '' === $file['path'] || ! wp_is_writable( dirname( $file['path'] ) ) ) {
			set_transient( 'lstab_due_unwritable', 1, DAY_IN_SECONDS );

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
				'f' => self::checked_at( $source ),
			);
		}

		if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}

		$direct = new WP_Filesystem_Direct( null );
		$mode   = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;

		return (bool) $direct->put_contents( $file['path'], (string) wp_json_encode( array( 't' => $due ) ), $mode );
	}

	public static function remove() {
		$file = self::file();

		if ( '' !== $file['path'] && file_exists( $file['path'] ) ) {
			wp_delete_file( $file['path'] );
		}
	}

	public static function handle() {
		nocache_headers();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public, idempotent, and only checks tables that are due.
		$asked = isset( $_POST['tables'] ) ? sanitize_text_field( wp_unslash( $_POST['tables'] ) ) : '';
		$ids   = array_slice( array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $asked ) ) ) ) ), 0, 20 );

		$changed  = array();
		$checked  = array();
		$deadline = microtime( true ) + max( 2, (int) apply_filters( 'lstab_keep_current_budget', self::ASK_BUDGET ) );

		foreach ( $ids as $id ) {
			$left = $deadline - microtime( true );

			if ( $left < 1 ) {
				break;
			}

			$outcome = LSTAB_Sync::refresh_when_asked( $id, $left );

			if ( 'changed' !== $outcome && 'unchanged' !== $outcome ) {
				continue;
			}

			$source = LSTAB_Storage::get( $id );

			if ( ! $source ) {
				continue;
			}

			$checked[ (string) $id ] = self::checked_at( $source );

			if ( 'changed' === $outcome ) {
				$changed[ (string) $id ] = substr( (string) $source['snapshot_hash'], 0, 12 );
			}
		}

		wp_send_json(
			array(
				'changed' => (object) $changed,
				'checked' => (object) $checked,
				'now'     => time(),
			)
		);
	}
}
