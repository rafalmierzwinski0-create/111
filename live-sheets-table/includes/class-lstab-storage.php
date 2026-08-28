<?php
/**
 * Persistence layer: one custom table holding sources plus their last good snapshot.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Source storage.
 */
class LSTAB_Storage {

	const DB_VERSION    = '1.0.0';
	const DB_VERSION_OPT = 'lstab_db_version';

	/**
	 * Fully qualified table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'lstab_sources';
	}

	/**
	 * Create or upgrade the schema.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL DEFAULT '',
			sheet_url text NOT NULL,
			sheet_id varchar(191) NOT NULL DEFAULT '',
			sheet_kind varchar(20) NOT NULL DEFAULT 'doc',
			gid varchar(32) NOT NULL DEFAULT '0',
			tab_name varchar(255) NOT NULL DEFAULT '',
			sync_interval int(10) unsigned NOT NULL DEFAULT 900,
			first_row_header tinyint(1) NOT NULL DEFAULT 1,
			style_preset varchar(50) NOT NULL DEFAULT 'clean',
			snapshot longtext NULL,
			snapshot_hash varchar(32) NOT NULL DEFAULT '',
			row_count int(10) unsigned NOT NULL DEFAULT 0,
			col_count int(10) unsigned NOT NULL DEFAULT 0,
			last_status varchar(20) NOT NULL DEFAULT 'never',
			last_error text NULL,
			last_attempt_gmt datetime NULL DEFAULT NULL,
			last_success_gmt datetime NULL DEFAULT NULL,
			created_gmt datetime NOT NULL,
			updated_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY last_attempt_gmt (last_attempt_gmt)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPT, self::DB_VERSION );
	}

	/**
	 * Run install when the stored schema version is behind.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPT ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Default column values for a new source.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'title'            => '',
			'sheet_url'        => '',
			'sheet_id'         => '',
			'sheet_kind'       => 'doc',
			'gid'              => '0',
			'tab_name'         => '',
			'sync_interval'    => LSTAB_Limits::min_interval(),
			'first_row_header' => 1,
			'style_preset'     => 'clean',
		);
	}

	/**
	 * Insert a source.
	 *
	 * @param array<string,mixed> $data Column values.
	 * @return int|WP_Error New source ID.
	 */
	public static function insert( $data ) {
		global $wpdb;

		$now  = current_time( 'mysql', true );
		$data = wp_parse_args( $data, self::defaults() );

		$row = array(
			'title'            => (string) $data['title'],
			'sheet_url'        => (string) $data['sheet_url'],
			'sheet_id'         => (string) $data['sheet_id'],
			'sheet_kind'       => (string) $data['sheet_kind'],
			'gid'              => (string) $data['gid'],
			'tab_name'         => (string) $data['tab_name'],
			'sync_interval'    => (int) $data['sync_interval'],
			'first_row_header' => empty( $data['first_row_header'] ) ? 0 : 1,
			'style_preset'     => (string) $data['style_preset'],
			'snapshot'         => null,
			'snapshot_hash'    => '',
			'row_count'        => 0,
			'col_count'        => 0,
			'last_status'      => 'never',
			'last_error'       => null,
			'last_attempt_gmt' => null,
			'last_success_gmt' => null,
			'created_gmt'      => $now,
			'updated_gmt'      => $now,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table, no core API available.
		$inserted = $wpdb->insert( self::table(), $row, $formats );

		if ( false === $inserted ) {
			return new WP_Error( 'lstab_db_insert_failed', __( 'Could not save the sheet source.', 'live-sheets-table' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a source's configuration columns.
	 *
	 * @param int                 $id   Source ID.
	 * @param array<string,mixed> $data Column values.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$allowed = array(
			'title'            => '%s',
			'sheet_url'        => '%s',
			'sheet_id'         => '%s',
			'sheet_kind'       => '%s',
			'gid'              => '%s',
			'tab_name'         => '%s',
			'sync_interval'    => '%d',
			'first_row_header' => '%d',
			'style_preset'     => '%s',
		);

		$row     = array();
		$formats = array();
		foreach ( $allowed as $column => $format ) {
			if ( array_key_exists( $column, $data ) ) {
				$row[ $column ] = '%d' === $format ? (int) $data[ $column ] : (string) $data[ $column ];
				$formats[]      = $format;
			}
		}

		if ( ! $row ) {
			return false;
		}

		$row['updated_gmt'] = current_time( 'mysql', true );
		$formats[]          = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table, no core API available.
		return false !== $wpdb->update( self::table(), $row, array( 'id' => (int) $id ), $formats, array( '%d' ) );
	}

	/**
	 * Store a successful sync result.
	 *
	 * The snapshot is only ever replaced on success, which is what guarantees
	 * the front end keeps showing the last good copy after a failed fetch.
	 *
	 * @param int                 $id   Source ID.
	 * @param array<string,mixed> $data Parsed table {headers, rows}.
	 * @return bool
	 */
	public static function record_success( $id, $data ) {
		global $wpdb;

		$now      = current_time( 'mysql', true );
		$encoded  = wp_json_encode( $data );
		$headers  = isset( $data['headers'] ) && is_array( $data['headers'] ) ? $data['headers'] : array();
		$rows     = isset( $data['rows'] ) && is_array( $data['rows'] ) ? $data['rows'] : array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table, no core API available.
		return false !== $wpdb->update(
			self::table(),
			array(
				'snapshot'         => $encoded,
				'snapshot_hash'    => md5( (string) $encoded ),
				'row_count'        => count( $rows ),
				'col_count'        => count( $headers ),
				'last_status'      => 'ok',
				'last_error'       => null,
				'last_attempt_gmt' => $now,
				'last_success_gmt' => $now,
				'updated_gmt'      => $now,
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Store a failed sync attempt, leaving the last good snapshot untouched.
	 *
	 * @param int    $id      Source ID.
	 * @param string $message Human readable error.
	 * @return bool
	 */
	public static function record_failure( $id, $message ) {
		global $wpdb;

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table, no core API available.
		return false !== $wpdb->update(
			self::table(),
			array(
				'last_status'      => 'error',
				'last_error'       => (string) $message,
				'last_attempt_gmt' => $now,
				'updated_gmt'      => $now,
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Fetch a single source.
	 *
	 * @param int $id Source ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be a placeholder.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ),
			ARRAY_A
		);
		// phpcs:enable

		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Fetch all sources, newest first.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_all() {
		global $wpdb;

		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be a placeholder.
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A );
		// phpcs:enable

		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Number of stored sources.
	 *
	 * @return int
	 */
	public static function count_sources() {
		global $wpdb;

		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be a placeholder.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:enable
	}

	/**
	 * Delete a source.
	 *
	 * @param int $id Source ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table, no core API available.
		$deleted = $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );

		if ( $deleted ) {
			do_action( 'lstab_source_deleted', (int) $id );
		}

		return (bool) $deleted;
	}

	/**
	 * Normalise raw DB values into typed PHP values.
	 *
	 * @param array<string,mixed> $row Raw row.
	 * @return array<string,mixed>
	 */
	protected static function hydrate( $row ) {
		$row['id']               = (int) $row['id'];
		$row['sync_interval']    = (int) $row['sync_interval'];
		$row['first_row_header'] = (bool) $row['first_row_header'];
		$row['row_count']        = (int) $row['row_count'];
		$row['col_count']        = (int) $row['col_count'];

		$decoded = ( null === $row['snapshot'] || '' === $row['snapshot'] )
			? null
			: json_decode( (string) $row['snapshot'], true );

		$row['data'] = ( is_array( $decoded ) && isset( $decoded['headers'], $decoded['rows'] ) )
			? $decoded
			: null;

		unset( $row['snapshot'] );

		return $row;
	}

	/**
	 * Drop the table (uninstall only).
	 *
	 * @return void
	 */
	public static function drop() {
		global $wpdb;

		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be a placeholder.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		// phpcs:enable

		delete_option( self::DB_VERSION_OPT );
	}
}
