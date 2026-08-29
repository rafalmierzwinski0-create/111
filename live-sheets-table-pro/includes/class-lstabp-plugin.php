<?php
/**
 * Pro bootstrap.
 *
 * @package LiveSheetsTablePro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the Pro features onto the free plugin's hooks.
 */
class LSTABP_Plugin {

	/**
	 * Register everything, but only once the free plugin is actually there.
	 *
	 * @return void
	 */
	public function boot() {
		if ( ! function_exists( 'lstab' ) || ! class_exists( 'LSTAB_Limits' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_core_notice' ) );
			return;
		}

		// Tier and limits. Everything the free plugin gates is a filter, so
		// lifting them needs no change to it at all.
		add_filter( 'lstab_is_pro', '__return_true' );
		add_filter( 'lstab_max_sources', array( $this, 'max_sources' ) );
		add_filter( 'lstab_min_sync_interval', array( $this, 'min_interval' ) );

		( new LSTABP_Google_Auth() )->register();
		( new LSTABP_Private_Sheets() )->register();
		( new LSTABP_Filters() )->register();
		( new LSTABP_Settings() )->register();

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'live-sheets-table-pro',
			false,
			dirname( plugin_basename( LSTABP_FILE ) ) . '/languages'
		);
	}

	/**
	 * Source limit under Pro.
	 *
	 * @return int
	 */
	public function max_sources() {
		return (int) apply_filters( 'lstabp_max_sources', 100 );
	}

	/**
	 * Fastest sync under Pro.
	 *
	 * @return int
	 */
	public function min_interval() {
		return 60;
	}

	/**
	 * Explain why nothing happened when the free plugin is absent.
	 *
	 * @return void
	 */
	public function missing_core_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'Live Sheets Table Pro needs the free Live Sheets Table plugin to be installed and active. Pro adds to it rather than replacing it.', 'live-sheets-table-pro' )
			. '</p></div>';
	}
}
