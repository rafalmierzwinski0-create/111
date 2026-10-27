<?php

defined( 'ABSPATH' ) || exit;

class LSTABP_Styles {
	public function register() {
		add_filter( 'lstab_style_presets', array( $this, 'add_presets' ) );
		add_filter( 'lstab_style_alias', array( $this, 'alias' ) );
		add_filter( 'lstab_table_styles', array( $this, 'add_stylesheet' ) );
		add_action( 'init', array( $this, 'register_stylesheet' ), 5 );
	}

	public function add_presets( $presets ) {
		$presets = (array) $presets;

		$presets['editorial'] = array(
			'label' => __( 'Editorial', 'live-sheets-table-pro' ),
		);
		$presets['cards']     = array(
			'label' => __( 'Cards', 'live-sheets-table-pro' ),
		);
		$presets['terminal']  = array(
			'label' => __( 'Terminal', 'live-sheets-table-pro' ),
		);
		$presets['aurora']    = array(
			'label' => __( 'Aurora', 'live-sheets-table-pro' ),
		);
		$presets['ledger']    = array(
			'label' => __( 'Ledger', 'live-sheets-table-pro' ),
		);
		$presets['contrast']  = array(
			'label' => __( 'Contrast', 'live-sheets-table-pro' ),
		);

		return $presets;
	}

	public function alias( $slug ) {
		return 'glass' === $slug ? 'aurora' : $slug;
	}

	public function register_stylesheet() {
		wp_register_style(
			'lstabp-styles',
			LSTABP_URL . 'assets/css/lstabp-styles.css',
			array(),
			LSTABP_Plugin::asset_version( 'assets/css/lstabp-styles.css' )
		);
	}

	public function add_stylesheet( $handles ) {
		$handles   = (array) $handles;
		$handles[] = 'lstabp-styles';

		return $handles;
	}
}
