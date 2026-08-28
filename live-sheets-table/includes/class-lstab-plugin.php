<?php
/**
 * Plugin bootstrap.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the pieces together.
 */
class LSTAB_Plugin {

	/**
	 * Cron controller.
	 *
	 * @var LSTAB_Cron
	 */
	public $cron;

	/**
	 * Admin controller.
	 *
	 * @var LSTAB_Admin
	 */
	public $admin;

	/**
	 * Block controller.
	 *
	 * @var LSTAB_Block
	 */
	public $block;

	/**
	 * REST controller.
	 *
	 * @var LSTAB_Rest
	 */
	public $rest;

	/**
	 * Shortcode controller.
	 *
	 * @var LSTAB_Shortcode
	 */
	public $shortcode;

	/**
	 * Register everything.
	 *
	 * @return void
	 */
	public function boot() {
		$this->cron      = new LSTAB_Cron();
		$this->admin     = new LSTAB_Admin();
		$this->block     = new LSTAB_Block();
		$this->rest      = new LSTAB_Rest();
		$this->shortcode = new LSTAB_Shortcode();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'plugins_loaded', array( LSTAB_Storage::class, 'maybe_upgrade' ) );

		$this->cron->register();
		$this->block->register();
		$this->rest->register();
		$this->shortcode->register();

		if ( is_admin() ) {
			$this->admin->register();
		}
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'live-sheets-table',
			false,
			dirname( LSTAB_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register (but do not enqueue) front-end assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'lstab-table',
			LSTAB_URL . 'assets/css/lstab-table.css',
			array(),
			LSTAB_VERSION
		);

		wp_register_script(
			'lstab-table',
			LSTAB_URL . 'assets/js/lstab-table.js',
			array(),
			LSTAB_VERSION,
			true
		);

		wp_script_add_data( 'lstab-table', 'strategy', 'defer' );
	}

	/**
	 * Activation: create the schema and start the schedule.
	 *
	 * @return void
	 */
	public static function on_activate() {
		LSTAB_Storage::install();
		LSTAB_Cron::ensure_scheduled();
	}

	/**
	 * Deactivation: stop the schedule, keep the data.
	 *
	 * @return void
	 */
	public static function on_deactivate() {
		LSTAB_Cron::unschedule();
	}
}
