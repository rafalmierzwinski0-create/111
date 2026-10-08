<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Storage {
	const DB_VERSION     = '1.10.0';
	const DB_VERSION_OPT = 'lstab_db_version';

	const RAGGED_OPT = 'lstab_ragged_sources';

	const DISMISSED_OPT = 'lstab_ragged_dismissed';
	const CACHE_GROUP    = 'lstab_sources';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'lstab_sources';
	}

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
			sticky_head tinyint(1) NOT NULL DEFAULT 1,
			link_cells tinyint(1) NOT NULL DEFAULT 1,
			per_page int(10) unsigned NOT NULL DEFAULT 0,
			columns_config text NULL,
			hidden_rows text NULL,
			style_vars text NULL,
			custom_css text NULL,
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'refresh_on_view' ) ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$wpdb->query( "ALTER TABLE {$table} DROP COLUMN refresh_on_view" );
		}

		if ( ! get_option( LSTAB_Limits::SEEN_OPTION ) && ! LSTAB_Limits::is_pro() && self::any_column_hidden() ) {
			update_option( LSTAB_Limits::SEEN_OPTION, time(), true );
		}

		update_option( self::DB_VERSION_OPT, self::DB_VERSION );
	}

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

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPT ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function defaults() {
		return array(
			'title'            => '',
			'sheet_url'        => '',
			'sheet_id'         => '',
			'sheet_kind'       => 'doc',
			'gid'              => '0',
			'tab_name'         => '',
			'sync_interval'    => max( LSTAB_Limits::min_interval(), (int) LSTAB_Settings::get( 'default_interval', 0 ) ),
			'first_row_header' => 1,
			'style_preset'     => LSTAB_Styles::sanitize( (string) LSTAB_Settings::get( 'default_style', 'clean' ) ),
			'layout'           => 'table',
			'sticky_first'     => 1,
			'sticky_head'      => 1,
			'link_cells'       => 1,
			'per_page'         => 0,
			'columns_config'   => array(),
			'hidden_rows'      => array(),
			'style_vars'       => LSTAB_Customizer::defaults(),
			'custom_css'       => '',
		);
	}

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
			'sticky_head'      => empty( $data['sticky_head'] ) ? 0 : 1,
			'link_cells'       => empty( $data['link_cells'] ) ? 0 : 1,
			'per_page'         => max( 0, (int) $data['per_page'] ),
			'columns_config'   => wp_json_encode( LSTAB_Columns::sanitize( $data['columns_config'] ) ),
			'hidden_rows'      => wp_json_encode( LSTAB_Hidden_Rows::sanitize( isset( $data['hidden_rows'] ) ? $data['hidden_rows'] : array() ) ),
			'style_vars'       => wp_json_encode( LSTAB_Customizer::sanitize( $data['style_vars'] ) ),
			'custom_css'       => LSTAB_Custom_Css::sanitize( isset( $data['custom_css'] ) ? $data['custom_css'] : '' ),
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

		$integer_columns = array(
			'sync_interval',
			'first_row_header',
			'sticky_first',
			'sticky_head',
			'link_cells',
			'per_page',
			'row_count',
			'col_count',
		);

		$formats = array();
		foreach ( array_keys( $row ) as $lstab_column ) {
			$formats[] = in_array( $lstab_column, $integer_columns, true ) ? '%d' : '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table, no core API available.
		$inserted = $wpdb->insert( self::table(), $row, $formats );

		if ( false === $inserted ) {
			return new WP_Error( 'lstab_db_insert_failed', __( 'Could not save the sheet source.', 'live-sheets-table' ) );
		}

		return (int) $wpdb->insert_id;
	}

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
			'sticky_head'      => '%d',
			'link_cells'       => '%d',
			'per_page'         => '%d',
			'columns_config'   => '%s',
			'hidden_rows'      => '%s',
			'style_vars'       => '%s',
			'custom_css'       => '%s',
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
			} elseif ( 'custom_css' === $column ) {
				$row[ $column ] = LSTAB_Custom_Css::sanitize( $data[ $column ] );
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

		$dismissed = array_values( array_intersect( (array) get_option( self::DISMISSED_OPT, array() ), $index ) );
		update_option( self::DISMISSED_OPT, $dismissed, true );
	}

	public static function ragged_signature( $id, $ragged ) {
		return md5( (int) $id . '|' . (string) wp_json_encode( $ragged ) );
	}

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

	const LOG_LENGTH = 7;

	protected static function log_with( $id, $outcome ) {
		$source = self::get( $id );
		$log    = $source && isset( $source['sync_log'] ) ? (string) $source['sync_log'] : '';
		$log    = preg_replace( '/[^ox]/', '', $log ) . $outcome;

		return substr( $log, -self::LOG_LENGTH );
	}

	public static function history( $source ) {
		$log  = isset( $source['sync_log'] ) ? preg_replace( '/[^ox]/', '', (string) $source['sync_log'] ) : '';
		$out  = array();

		foreach ( str_split( (string) $log ) as $mark ) {
			$out[] = 'x' === $mark ? 'error' : 'ok';
		}

		return $out;
	}

	public static function get( $id ) {
		global $wpdb;

		$id = (int) $id;

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

		wp_cache_set( $id, null === $source ? 0 : $source, self::CACHE_GROUP );

		return $source;
	}

	public static function flush_cache( $id ) {
		wp_cache_delete( (int) $id, self::CACHE_GROUP );
	}

	public static function get_all( $with_data = false ) {
		global $wpdb;

		$table   = self::table();
		$columns = $with_data ? '*' : self::meta_columns();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names cannot be placeholders.
		$rows = $wpdb->get_results( "SELECT {$columns} FROM {$table} ORDER BY id ASC", ARRAY_A );
		// phpcs:enable

		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	protected static function meta_columns() {
		return 'id, title, sheet_url, sheet_id, sheet_kind, gid, tab_name, sync_interval, '
			. 'first_row_header, style_preset, layout, sticky_first, sticky_head, link_cells, per_page, columns_config, hidden_rows, style_vars, custom_css, '
			. 'snapshot_hash, row_count, col_count, sync_log, '
			. 'last_status, last_error, last_ragged, last_attempt_gmt, last_success_gmt, created_gmt, updated_gmt';
	}

	public static function count_sources() {
		global $wpdb;

		$table = self::table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be a placeholder.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE sheet_kind <> %s", LSTAB_Example::KIND ) );
		// phpcs:enable
	}

	public static function delete( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table, no core API available.
		$deleted = $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );

		self::flush_cache( $id );
		self::index_ragged( (int) $id, null );

		if ( $deleted ) {
			self::reset_numbering();

			do_action( 'lstab_source_deleted', (int) $id );
		}

		return (bool) $deleted;
	}

	protected static function reset_numbering() {
		global $wpdb;

		if ( self::count_sources() > 0 ) {
			return;
		}

		$table = self::table();

		$errors = $wpdb->hide_errors();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be a placeholder.
		$wpdb->query( "ALTER TABLE {$table} AUTO_INCREMENT = 1" );

		if ( $wpdb->get_var( "SELECT name FROM sqlite_master WHERE type='table' AND name='sqlite_sequence'" ) ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM sqlite_sequence WHERE name = %s', $table ) );
		}
		// phpcs:enable

		if ( $errors ) {
			$wpdb->show_errors();
		}

		$wpdb->last_error = '';
	}

	protected static function hydrate( $row ) {
		$row['id']               = (int) $row['id'];
		$row['sync_interval']    = (int) $row['sync_interval'];
		$row['first_row_header'] = (bool) $row['first_row_header'];
		$row['row_count']        = (int) $row['row_count'];
		$row['col_count']        = (int) $row['col_count'];

		$row['sticky_first'] = ! isset( $row['sticky_first'] ) || (bool) $row['sticky_first'];
		$row['sticky_head']  = ! isset( $row['sticky_head'] ) || (bool) $row['sticky_head'];
		$row['link_cells']   = ! isset( $row['link_cells'] ) || (bool) $row['link_cells'];
		$row['per_page']     = isset( $row['per_page'] ) ? max( 0, (int) $row['per_page'] ) : 0;

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

		$row['custom_css'] = isset( $row['custom_css'] ) ? (string) $row['custom_css'] : '';

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

	public static function drop() {
		global $wpdb;

		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be a placeholder.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		// phpcs:enable

		delete_option( self::DB_VERSION_OPT );
	}
}
