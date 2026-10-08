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
		$interval = max( 60, (int) $source['sync_interval'] );
		$success  = empty( $source['last_success_gmt'] ) ? 0 : (int) strtotime( $source['last_success_gmt'] . ' UTC' );
		$attempt  = empty( $source['last_attempt_gmt'] ) ? 0 : (int) strtotime( $source['last_attempt_gmt'] . ' UTC' );
		$next     = $success + $interval;

		if ( 'error' === $source['last_status'] ) {
			$next = max( $next, $attempt + min( $interval, self::RETRY_AFTER_FAILURE ) );
		}

		return $next;
	}

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
				'f' => empty( $source['last_success_gmt'] ) ? 0 : (int) strtotime( $source['last_success_gmt'] . ' UTC' ),
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
