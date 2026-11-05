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
			__( 'Service', 'tabliko' ),
			__( 'Time', 'tabliko' ),
			__( 'Price', 'tabliko' ),
			__( 'Availability', 'tabliko' ),
			__( 'Notes', 'tabliko' ),
		);

		$in_stock = __( 'In stock', 'tabliko' );
		$to_order = __( 'To order', 'tabliko' );
		$none     = __( 'Unavailable', 'tabliko' );

		$rows = array(
			array(
				__( 'Basic service', 'tabliko' ),
				__( '45 min', 'tabliko' ),
				'120.00',
				$in_stock,
				__( 'No parts replaced', 'tabliko' ),
			),
			array(
				__( 'Suspension overhaul', 'tabliko' ),
				__( '2 h', 'tabliko' ),
				'340.00',
				$to_order,
				__( 'Up to five working days', 'tabliko' ),
			),
			array(
				__( 'Wheel truing', 'tabliko' ),
				__( '30 min', 'tabliko' ),
				'89.00',
				$in_stock,
				'—',
			),
			array(
				__( 'Chain replacement', 'tabliko' ),
				__( '20 min', 'tabliko' ),
				'1,215.50',
				$none,
				__( 'Part on back order', 'tabliko' ),
			),
			array(
				__( 'Rack fitting', 'tabliko' ),
				__( '1 h', 'tabliko' ),
				'87.00',
				$in_stock,
				__( 'Seatpost or frame mount', 'tabliko' ),
			),
			array(
				__( 'Gear adjustment', 'tabliko' ),
				__( '25 min', 'tabliko' ),
				'69.00',
				$in_stock,
				__( 'Cables included, housing extra', 'tabliko' ),
			),
			array(
				__( 'Brake pads', 'tabliko' ),
				__( '40 min', 'tabliko' ),
				'149.00',
				$to_order,
				__( 'Organic or metallic compound', 'tabliko' ),
			),
			array(
				__( 'Season preparation', 'tabliko' ),
				__( '3 h', 'tabliko' ),
				'459.00',
				$in_stock,
				__( 'Full check, wash and lubrication', 'tabliko' ),
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
				'title'      => __( 'Example price list', 'tabliko' ),
				'sheet_url'  => '',
				'sheet_id'   => '',
				'sheet_kind' => self::KIND,
				'gid'        => '0',
				'tab_name'   => __( 'Example', 'tabliko' ),
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
			wp_die( esc_html__( 'You do not have permission to do that.', 'tabliko' ) );
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
