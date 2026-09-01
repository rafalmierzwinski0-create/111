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
	 * Cell auto-linking.
	 *
	 * @var LSTAB_Links
	 */
	public $links;

	/**
	 * Server-side paging.
	 *
	 * @var LSTAB_Paging
	 */
	public $paging;

	/**
	 * Elementor widget registration.
	 *
	 * @var LSTAB_Elementor
	 */
	public $elementor;

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
		$this->links     = new LSTAB_Links();
		$this->paging    = new LSTAB_Paging();
		$this->elementor = new LSTAB_Elementor();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'plugins_loaded', array( LSTAB_Storage::class, 'maybe_upgrade' ) );

		$this->cron->register();
		$this->block->register();
		$this->rest->register();
		$this->shortcode->register();
		$this->links->register();
		$this->paging->register();
		$this->elementor->register();

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
	 * Cache-busting version for a bundled asset.
	 *
	 * Using the plugin version alone means a stylesheet rewritten between two
	 * releases that forgot to bump it keeps being served from the browser (and
	 * any page-cache) under the old URL. Folding in the file's modification
	 * time makes the URL change whenever the file actually does, so a stale
	 * asset cannot survive an upgrade.
	 *
	 * @param string $relative_path Path within the plugin directory.
	 * @return string
	 */
	public static function asset_version( $relative_path ) {
		$file = LSTAB_PATH . ltrim( $relative_path, '/' );

		if ( ! is_readable( $file ) ) {
			return LSTAB_VERSION;
		}

		$modified = filemtime( $file );

		return $modified ? LSTAB_VERSION . '.' . $modified : LSTAB_VERSION;
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
			self::asset_version( 'assets/css/lstab-table.css' )
		);

		wp_register_script(
			'lstab-table',
			LSTAB_URL . 'assets/js/lstab-table.js',
			array(),
			self::asset_version( 'assets/js/lstab-table.js' ),
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
