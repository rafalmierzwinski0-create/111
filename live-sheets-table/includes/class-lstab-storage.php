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

	const DB_VERSION     = '1.8.0';
	const DB_VERSION_OPT = 'lstab_db_version';

	/**
	 * Which sources last came back malformed, and what the fault looked like.
	 *
	 * A derived index of the last_ragged column, kept so the dashboard-wide
	 * warning costs an autoloaded option read rather than a query on every
	 * single admin page. It is rewritten by every sync, so it cannot drift for
	 * long, and the column remains the truth.
	 */
	const RAGGED_OPT = 'lstab_ragged_sources';

	/**
	 * Signatures of malformed-sheet warnings the site owner has dismissed.
	 */
	const DISMISSED_OPT = 'lstab_ragged_dismissed';
	const CACHE_GROUP    = 'lstab_sources';

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
			layout varchar(20) NOT NULL DEFAULT 'table',
			sticky_first tinyint(1) NOT NULL DEFAULT 1,
			link_cells tinyint(1) NOT NULL DEFAULT 1,
			per_page int(10) unsigned NOT NULL DEFAULT 0,
			columns_config text NULL,
			hidden_rows text NULL,
			style_vars text NULL,
			snapshot longtext NULL,
			snapshot_hash varchar(32) NOT NULL DEFAULT '',
			row_count int(10) unsigned NOT NULL DEFAULT 0,
			col_count int(10) unsigned NOT NULL DEFAULT 0,
			last_status varchar(20) NOT NULL DEFAULT 'never',
			sync_log varchar(64) NOT NULL DEFAULT '',
			last_error text NULL,
			last_ragged text NULL,
			last_attempt_gmt datetime NULL DEFAULT NULL,
			last_success_gmt datetime NULL DEFAULT NULL,
			created_gmt datetime NOT NULL,
			updated_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY last_attempt_gmt (last_attempt_gmt)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		/*
		 * dbDelta adds columns and never removes them, so a column the plugin
		 * has stopped using would sit in the table for good, waiting to confuse
		 * whoever reads the schema next. refresh_on_view was a per-table
		 * setting for two versions; checking before drawing the page is now
		 * simply how the plugin works, and there is nothing left to store.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'refresh_on_view' ) ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$wpdb->query( "ALTER TABLE {$table} DROP COLUMN refresh_on_view" );
		}

		/*
		 * Leaving a column out used to be free, and is not any more. A site
		 * that already has one hidden must not have it reappear on a public
		 * page the moment it updates: the grace clock starts now instead, so
		 * the owner gets the same ten days and the same notice as anyone whose
		 * add-on has stopped.
		 */
		if ( ! get_option( LSTAB_Limits::SEEN_OPTION ) && ! LSTAB_Limits::is_pro() && self::any_column_hidden() ) {
			update_option( LSTAB_Limits::SEEN_OPTION, time(), true );
		}

		update_option( self::DB_VERSION_OPT, self::DB_VERSION );
	}

	/**
	 * Whether any source leaves a column out of its table.
	 *
	 * @return bool
	 */
	protected static function any_column_hidden() {
		foreach ( self::get_all() as $source ) {
			foreach ( (array) $source['columns_config'] as $column ) {
				if ( ! empty( $column['hidden'] ) ) {
					return true;
				}
			}
		}

		return false;
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
			// A site can say what a new table should start with; failing that,
			// as often as the licence allows.
			'sync_interval'    => max( LSTAB_Limits::min_interval(), (int) LSTAB_Settings::get( 'default_interval', 0 ) ),
			'first_row_header' => 1,
			'style_preset'     => 'clean',
			'layout'           => 'table',
			'sticky_first'     => 1,
			'link_cells'       => 1,
			'per_page'         => 0,
			'columns_config'   => array(),
			'hidden_rows'      => array(),
			'style_vars'       => LSTAB_Customizer::defaults(),
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
			'layout'           => (string) $data['layout'],
			'sticky_first'     => empty( $data['sticky_first'] ) ? 0 : 1,
			'link_cells'       => empty( $data['link_cells'] ) ? 0 : 1,
			'per_page'         => max( 0, (int) $data['per_page'] ),
			'columns_config'   => wp_json_encode( LSTAB_Columns::sanitize( $data['columns_config'] ) ),
			'hidden_rows'      => wp_json_encode( LSTAB_Hidden_Rows::sanitize( isset( $data['hidden_rows'] ) ? $data['hidden_rows'] : array() ) ),
			'style_vars'       => wp_json_encode( LSTAB_Customizer::sanitize( $data['style_vars'] ) ),
			'snapshot'         => null,
			'snapshot_hash'    => '',
			'row_count'        => 0,
			'col_count'        => 0,
			'last_status'      => 'never',
			'sync_log'         => '',
			'last_error'       => null,
			'last_ragged'      => null,
			'last_attempt_gmt' => null,
			'last_success_gmt' => null,
			'created_gmt'      => $now,
			'updated_gmt'      => $now,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

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
			'layout'           => '%s',
			'sticky_first'     => '%d',
			'link_cells'       => '%d',
			'per_page'         => '%d',
			'columns_config'   => '%s',
			'hidden_rows'      => '%s',
			'style_vars'       => '%s',
		);

		$row     = array();
		$formats = array();
		foreach ( $allowed as $column => $format ) {
			if ( ! array_key_exists( $column, $data ) ) {
				continue;
			}

			if ( 'style_vars' === $column ) {
				$row[ $column ] = wp_json_encode( LSTAB_Customizer::sanitize( $data[ $column ] ) );
			} elseif ( 'hidden_rows' === $column ) {
				$row[ $column ] = wp_json_encode( LSTAB_Hidden_Rows::sanitize( $data[ $column ] ) );
			} elseif ( 'columns_config' === $column ) {
				$row[ $column ] = wp_json_encode( LSTAB_Columns::sanitize( $data[ $column ] ) );
			} elseif ( '%d' === $format ) {
				$row[ $column ] = (int) $data[ $column ];
			} else {
				$row[ $column ] = (string) $data[ $column ];
			}

			$formats[] = $format;
		}

		if ( ! $row ) {
			return false;
		}

		$row['updated_gmt'] = current_time( 'mysql', true );
		$formats[]          = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table, no core API available.
		$updated = false !== $wpdb->update( self::table(), $row, array( 'id' => (int) $id ), $formats, array( '%d' ) );

		self::flush_cache( $id );

		return $updated;
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
		$updated = false !== $wpdb->update(
			self::table(),
			array(
				'snapshot'         => $encoded,
				'snapshot_hash'    => md5( (string) $encoded ),
				'row_count'        => count( $rows ),
				'col_count'        => count( $headers ),
				'sync_log'         => self::log_with( (int) $id, 'o' ),
				'last_status'      => 'ok',
				'last_error'       => null,
				// Describes the copy just stored, so it is written here and
				// left alone by a failure, exactly like the snapshot itself.
				'last_ragged'      => isset( $data['ragged'] ) ? wp_json_encode( $data['ragged'] ) : null,
				'last_attempt_gmt' => $now,
				'last_success_gmt' => $now,
				'updated_gmt'      => $now,
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		self::flush_cache( $id );
		self::index_ragged( (int) $id, isset( $data['ragged'] ) ? $data['ragged'] : null );

		return $updated;
	}

	/**
	 * Record, or clear, one source's place in the malformed-sheet index.
	 *
	 * @param int        $id     Source ID.
	 * @param array|null $ragged The finding, or null when the sheet was clean.
	 * @return void
	 */
	protected static function index_ragged( $id, $ragged ) {
		$index = (array) get_option( self::RAGGED_OPT, array() );
		$had   = isset( $index[ $id ] );

		if ( $ragged ) {
			$index[ $id ] = self::ragged_signature( $id, $ragged );
		} elseif ( $had ) {
			unset( $index[ $id ] );
		} else {
			return;
		}

		update_option( self::RAGGED_OPT, $index, true );

		// A dismissal is of one particular fault, so anything no longer in the
		// index has nothing left to suppress.
		$dismissed = array_values( array_intersect( (array) get_option( self::DISMISSED_OPT, array() ), $index ) );
		update_option( self::DISMISSED_OPT, $dismissed, true );
	}

	/**
	 * A stable name for one particular fault.
	 *
	 * Dismissing a warning has to silence that fault and not the next one, so
	 * the signature covers what was found rather than just which source found
	 * it.
	 *
	 * @param int   $id     Source ID.
	 * @param array $ragged The finding.
	 * @return string
	 */
	public static function ragged_signature( $id, $ragged ) {
		return md5( (int) $id . '|' . (string) wp_json_encode( $ragged ) );
	}

	/**
	 * Put a source's reported health back as it was.
	 *
	 * Used after an attempt that is not evidence about the sheet: a refresh
	 * made while someone waited, cut off by this plugin's own four-second
	 * deadline rather than by anything wrong at Google. The attempt time stays
	 * as record_failure() left it, so the next visitor does not try again
	 * immediately; only the verdict is withdrawn.
	 *
	 * @param int         $id     Source ID.
	 * @param string      $status Status to restore.
	 * @param string|null $error  Error message to restore, or null.
	 * @return bool
	 */
	public static function restore_status( $id, $status, $error ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table, no core API available.
		$updated = false !== $wpdb->update(
			self::table(),
			array(
				'last_status' => (string) $status,
				'last_error'  => null === $error ? null : (string) $error,
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		self::flush_cache( $id );

		return $updated;
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
		$updated = false !== $wpdb->update(
			self::table(),
			array(
				'last_status'      => 'error',
				'last_error'       => (string) $message,
				'sync_log'         => self::log_with( (int) $id, 'x' ),
				'last_attempt_gmt' => $now,
				'updated_gmt'      => $now,
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		self::flush_cache( $id );

		return $updated;
	}

	/**
	 * How many checks the dashboard draws beside a source.
	 *
	 * Seven is enough to tell a single hiccup from a run of them, and short
	 * enough to read without counting.
	 */
	const LOG_LENGTH = 7;

	/**
	 * One source's recent history with a new outcome added.
	 *
	 * Kept as a string of single characters — one per check, oldest first —
	 * because it is drawn as seven little bars and never queried. A column of
	 * dates would be a table of its own for something nobody will ever sort.
	 *
	 * @param int    $id      Source ID.
	 * @param string $outcome 'o' for a good check, 'x' for a failed one.
	 * @return string
	 */
	protected static function log_with( $id, $outcome ) {
		$source = self::get( $id );
		$log    = $source && isset( $source['sync_log'] ) ? (string) $source['sync_log'] : '';
		$log    = preg_replace( '/[^ox]/', '', $log ) . $outcome;

		return substr( $log, -self::LOG_LENGTH );
	}

	/**
	 * One source's recent history, oldest first.
	 *
	 * @param array<string,mixed> $source Source row.
	 * @return array<int,string> Each entry 'ok' or 'error'.
	 */
	public static function history( $source ) {
		$log  = isset( $source['sync_log'] ) ? preg_replace( '/[^ox]/', '', (string) $source['sync_log'] ) : '';
		$out  = array();

		foreach ( str_split( (string) $log ) as $mark ) {
			$out[] = 'x' === $mark ? 'error' : 'ok';
		}

		return $out;
	}

	/**
	 * Fetch a single source.
	 *
	 * @param int $id Source ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$id = (int) $id;

		// Every page view that renders a table reads this row, snapshot and all.
		// On a site with a persistent object cache that turns into one query
		// per sync instead of one per request; without one, nothing changes.
		$cached = wp_cache_get( $id, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be a placeholder.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);
		// phpcs:enable

		$source = $row ? self::hydrate( $row ) : null;

		// Cache the miss too, so a shortcode pointing at a deleted source does
		// not re-query on every request.
		wp_cache_set( $id, null === $source ? 0 : $source, self::CACHE_GROUP );

		return $source;
	}

	/**
	 * Drop a source from the object cache.
	 *
	 * @param int $id Source ID.
	 * @return void
	 */
	public static function flush_cache( $id ) {
		wp_cache_delete( (int) $id, self::CACHE_GROUP );
	}

	/**
	 * Fetch all sources.
	 *
	 * Snapshots can run to megabytes, and the callers that list sources — the
	 * dashboard table, the cron due-check, the block picker — only need the
	 * metadata. They pass false so the payload is left in the database.
	 *
	 * @param bool $with_data Include the stored snapshot.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_all( $with_data = false ) {
		global $wpdb;

		$table   = self::table();
		$columns = $with_data ? '*' : self::meta_columns();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names cannot be placeholders.
		$rows = $wpdb->get_results( "SELECT {$columns} FROM {$table} ORDER BY id ASC", ARRAY_A );
		// phpcs:enable

		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Every column except the snapshot payload.
	 *
	 * Written out rather than built dynamically so the query stays a constant
	 * string with no caller-supplied input anywhere near it.
	 *
	 * @return string
	 */
	protected static function meta_columns() {
		return 'id, title, sheet_url, sheet_id, sheet_kind, gid, tab_name, sync_interval, '
			. 'first_row_header, style_preset, layout, sticky_first, link_cells, per_page, columns_config, hidden_rows, style_vars, '
			. 'snapshot_hash, row_count, col_count, sync_log, '
			. 'last_status, last_error, last_ragged, last_attempt_gmt, last_success_gmt, created_gmt, updated_gmt';
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

		self::flush_cache( $id );
		self::index_ragged( (int) $id, null );

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

		$row['sticky_first'] = ! isset( $row['sticky_first'] ) || (bool) $row['sticky_first'];
		$row['link_cells']   = ! isset( $row['link_cells'] ) || (bool) $row['link_cells'];
		$row['per_page']     = isset( $row['per_page'] ) ? max( 0, (int) $row['per_page'] ) : 0;

		// Decoded here so every screen reads a structure rather than JSON.
		if ( array_key_exists( 'last_ragged', $row ) ) {
			$ragged = ( null === $row['last_ragged'] || '' === $row['last_ragged'] )
				? null
				: json_decode( (string) $row['last_ragged'], true );

			$row['last_ragged'] = ( is_array( $ragged ) && isset( $ragged['expected'], $ragged['total'] ) )
				? $ragged
				: null;
		}

		$row['hidden_rows'] = LSTAB_Hidden_Rows::sanitize(
			isset( $row['hidden_rows'] ) ? json_decode( (string) $row['hidden_rows'], true ) : array()
		);

		$row['columns_config'] = LSTAB_Columns::sanitize(
			isset( $row['columns_config'] ) ? json_decode( (string) $row['columns_config'], true ) : array()
		);

		$row['style_vars'] = LSTAB_Customizer::sanitize(
			isset( $row['style_vars'] ) ? json_decode( (string) $row['style_vars'], true ) : array()
		);

		// Metadata-only reads have no snapshot column; 'data' stays absent so a
		// caller cannot mistake "not loaded" for "no data stored".
		if ( array_key_exists( 'snapshot', $row ) ) {
			$decoded = ( null === $row['snapshot'] || '' === $row['snapshot'] )
				? null
				: json_decode( (string) $row['snapshot'], true );

			$row['data'] = ( is_array( $decoded ) && isset( $decoded['headers'], $decoded['rows'] ) )
				? $decoded
				: null;

			unset( $row['snapshot'] );
		}

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
