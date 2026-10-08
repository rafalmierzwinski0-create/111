<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Example {
	const KIND = 'example';

	public function register() {
		add_action( 'admin_post_lstab_add_example', array( $this, 'handle_add' ) );
	}

	public static function is_example( $source ) {
		return isset( $source['sheet_kind'] ) && self::KIND === $source['sheet_kind'];
	}

	public static function exists() {
		foreach ( LSTAB_Storage::get_all() as $source ) {
			if ( self::is_example( $source ) ) {
				return true;
			}
		}

		return false;
	}

	public static function table() {
		$headers = array(
			__( 'Service', 'live-sheets-table' ),
			__( 'Time', 'live-sheets-table' ),
			__( 'Price', 'live-sheets-table' ),
			__( 'Availability', 'live-sheets-table' ),
			__( 'Notes', 'live-sheets-table' ),
		);

		$in_stock = __( 'In stock', 'live-sheets-table' );
		$to_order = __( 'To order', 'live-sheets-table' );
		$none     = __( 'Unavailable', 'live-sheets-table' );

		$rows = array(
			array(
				__( 'Basic service', 'live-sheets-table' ),
				__( '45 min', 'live-sheets-table' ),
				'120.00',
				$in_stock,
				__( 'No parts replaced', 'live-sheets-table' ),
			),
			array(
				__( 'Suspension overhaul', 'live-sheets-table' ),
				__( '2 h', 'live-sheets-table' ),
				'340.00',
				$to_order,
				__( 'Up to five working days', 'live-sheets-table' ),
			),
			array(
				__( 'Wheel truing', 'live-sheets-table' ),
				__( '30 min', 'live-sheets-table' ),
				'89.00',
				$in_stock,
				'—',
			),
			array(
				__( 'Chain replacement', 'live-sheets-table' ),
				__( '20 min', 'live-sheets-table' ),
				'1,215.50',
				$none,
				__( 'Part on back order', 'live-sheets-table' ),
			),
			array(
				__( 'Rack fitting', 'live-sheets-table' ),
				__( '1 h', 'live-sheets-table' ),
				'87.00',
				$in_stock,
				__( 'Seatpost or frame mount', 'live-sheets-table' ),
			),
			array(
				__( 'Gear adjustment', 'live-sheets-table' ),
				__( '25 min', 'live-sheets-table' ),
				'69.00',
				$in_stock,
				__( 'Cables included, housing extra', 'live-sheets-table' ),
			),
			array(
				__( 'Brake pads', 'live-sheets-table' ),
				__( '40 min', 'live-sheets-table' ),
				'149.00',
				$to_order,
				__( 'Organic or metallic compound', 'live-sheets-table' ),
			),
			array(
				__( 'Season preparation', 'live-sheets-table' ),
				__( '3 h', 'live-sheets-table' ),
				'459.00',
				$in_stock,
				__( 'Full check, wash and lubrication', 'live-sheets-table' ),
			),
		);

		return array(
			'headers' => $headers,
			'rows'    => $rows,
			'offset'  => 1,
		);
	}

	public static function install() {
		foreach ( LSTAB_Storage::get_all() as $source ) {
			if ( self::is_example( $source ) ) {
				return (int) $source['id'];
			}
		}

		$id = LSTAB_Storage::insert(
			array(
				'title'      => __( 'Example price list', 'live-sheets-table' ),
				'sheet_url'  => '',
				'sheet_id'   => '',
				'sheet_kind' => self::KIND,
				'gid'        => '0',
				'tab_name'   => __( 'Example', 'live-sheets-table' ),
			)
		);

		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}

		$table = self::table();

		LSTAB_Storage::record_success( $id, $table );

		LSTAB_Storage::update(
			$id,
			array( 'columns_config' => LSTAB_Columns::reconcile( array(), $table['headers'] ) )
		);

		return (int) $id;
	}

	public function handle_add() {
		if ( ! current_user_can( LSTAB_Limits::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'live-sheets-table' ) );
		}

		check_admin_referer( 'lstab_add_example' );

		$id = self::install();

		wp_safe_redirect(
			$id
				? add_query_arg(
					array(
						'page'   => LSTAB_Admin::EDIT_SLUG,
						'source' => $id,
					),
					admin_url( 'admin.php' )
				)
				: admin_url( 'admin.php?page=' . LSTAB_Admin::SOURCES_SLUG )
		);
		exit;
	}
}
