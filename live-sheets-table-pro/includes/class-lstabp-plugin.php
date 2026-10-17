<?php

defined( 'ABSPATH' ) || exit;

class LSTABP_Plugin {
	const CORE_VERSION = '1.0.0';

	public function boot() {
		if ( ! function_exists( 'lstab' ) || ! class_exists( 'LSTAB_Limits' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_core_notice' ) );
			return;
		}

		if ( ! defined( 'LSTAB_VERSION' ) || version_compare( LSTAB_VERSION, self::CORE_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'old_core_notice' ) );
			return;
		}

		add_filter( 'lstab_sync_intervals', array( $this, 'add_intervals' ) );
		add_action( 'lstab_masthead_title', array( $this, 'masthead_badge' ) );

		( new LSTABP_Styles() )->register();
		( new LSTABP_Google_Auth() )->register();
		( new LSTABP_Private_Sheets() )->register();
		( new LSTABP_Filters() )->register();
		( new LSTABP_Rules() )->register();
		( new LSTABP_Export() )->register();
		( new LSTABP_Facets() )->register();
		( new LSTABP_Column_Looks() )->register();
		( new LSTABP_Settings() )->register();

		add_action( 'admin_init', array( $this, 'forget_vanished_sources' ) );
	}

	public function forget_vanished_sources() {
		$known = get_option( 'lstabp_known_sources', null );
		$now   = array();

		foreach ( LSTAB_Storage::get_all() as $source ) {
			$now[ (int) $source['id'] ] = (string) $source['created_gmt'];
		}

		if ( is_array( $known ) ) {
			foreach ( $known as $id => $created ) {
				if ( ! isset( $now[ (int) $id ] ) || $now[ (int) $id ] !== (string) $created ) {
					do_action( 'lstabp_forget_source', (int) $id );
				}
			}
		}

		if ( $known !== $now ) {
			update_option( 'lstabp_known_sources', $now, false );
		}
	}

	public function add_intervals( $intervals ) {
		$intervals = (array) $intervals;

		$intervals[60]  = __( 'Every minute', 'live-sheets-table-pro' );
		$intervals[300] = __( 'Every 5 minutes', 'live-sheets-table-pro' );

		return $intervals;
	}

	public function masthead_badge() {
		echo '<span class="lstab-masthead-badge">' . LSTAB_Icons::icon( 'spark' ) . 'PRO</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- Static SVG and fixed text.
	}

	public function missing_core_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'Live Sheets Table Pro needs the free Live Sheets Table plugin to be installed and active. Pro adds to it rather than replacing it.', 'live-sheets-table-pro' )
			. '</p></div>';
	}

	public function old_core_notice() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>'
			. esc_html(
				sprintf(
					/* translators: %s: version number of the free plugin. */
					__( 'Live Sheets Table Pro needs Live Sheets Table %s or newer. Update the free plugin and Pro switches itself on again.', 'live-sheets-table-pro' ),
					self::CORE_VERSION
				)
			)
			. '</p></div>';
	}
}
