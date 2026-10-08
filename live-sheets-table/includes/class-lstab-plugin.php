<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Plugin {
	public $cron;

	public $admin;

	public $block;

	public $rest;

	public $shortcode;

	public $links;

	public $highlight;

	public $paging;

	public $hidden_rows;

	public $settings;

	public $hidden_alerts;

	public $usage;

	public $cache;

	public $example;

	public $elementor;

	public $freshness;

	public function boot() {
		$this->cron      = new LSTAB_Cron();
		$this->admin     = new LSTAB_Admin();
		$this->block     = new LSTAB_Block();
		$this->rest      = new LSTAB_Rest();
		$this->shortcode = new LSTAB_Shortcode();
		$this->links     = new LSTAB_Links();
		$this->highlight = new LSTAB_Highlight();
		$this->paging    = new LSTAB_Paging();
		$this->hidden_rows = new LSTAB_Hidden_Rows();
		$this->settings    = new LSTAB_Settings();
		$this->hidden_alerts = new LSTAB_Hidden_Alerts();
		$this->usage         = new LSTAB_Usage();
		$this->cache         = new LSTAB_Cache();
		$this->example       = new LSTAB_Example();
		$this->elementor = new LSTAB_Elementor();
		$this->freshness = new LSTAB_Freshness();

		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'plugins_loaded', array( LSTAB_Storage::class, 'maybe_upgrade' ) );

		$this->cron->register();
		$this->block->register();
		$this->rest->register();
		$this->shortcode->register();
		$this->links->register();
		$this->highlight->register();
		$this->paging->register();
		$this->hidden_rows->register();
		$this->settings->register();
		$this->hidden_alerts->register();
		$this->usage->register();
		$this->cache->register();
		$this->example->register();
		$this->elementor->register();
		$this->freshness->register();

		if ( is_admin() ) {
			$this->admin->register();
		}
	}

	public static function asset_version( $relative_path ) {
		$file = LSTAB_PATH . ltrim( $relative_path, '/' );

		if ( ! is_readable( $file ) ) {
			return LSTAB_VERSION;
		}

		$modified = filemtime( $file );

		return $modified ? LSTAB_VERSION . '.' . $modified : LSTAB_VERSION;
	}

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

	public static function on_activate() {
		LSTAB_Storage::install();
		LSTAB_Cron::ensure_scheduled();
	}

	public static function on_deactivate() {
		LSTAB_Cron::unschedule();
	}
}
