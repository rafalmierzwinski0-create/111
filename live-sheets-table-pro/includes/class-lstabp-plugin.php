<?php

defined( 'ABSPATH' ) || exit;

class LSTABP_Plugin {
	public function boot() {
		if ( ! function_exists( 'lstab' ) || ! class_exists( 'LSTAB_Limits' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_core_notice' ) );
			return;
		}

		add_filter( 'lstab_is_pro', '__return_true' );
		add_filter( 'lstab_max_sources', array( $this, 'max_sources' ) );
		add_filter( 'lstab_min_sync_interval', array( $this, 'min_interval' ) );

		( new LSTABP_Google_Auth() )->register();
		( new LSTABP_Private_Sheets() )->register();
		( new LSTABP_Filters() )->register();
		( new LSTABP_Rules() )->register();
		( new LSTABP_Export() )->register();
		( new LSTABP_Facets() )->register();
		( new LSTABP_Column_Looks() )->register();
		( new LSTABP_Picker() )->register();
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

	public function max_sources() {
		return (int) apply_filters( 'lstabp_max_sources', 100 );
	}

	public function min_interval() {
		return 60;
	}

	public function missing_core_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'Live Sheets Table Pro needs the free Live Sheets Table plugin to be installed and active. Pro adds to it rather than replacing it.', 'live-sheets-table-pro' )
			. '</p></div>';
	}
}
