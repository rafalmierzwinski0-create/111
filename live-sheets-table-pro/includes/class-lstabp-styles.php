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
			'label'       => __( 'Editorial', 'live-sheets-table-pro' ),
			'description' => __( 'Serif headings and very thin lines, in the manner of a printed table.', 'live-sheets-table-pro' ),
		);
		$presets['cards']     = array(
			'label'       => __( 'Cards', 'live-sheets-table-pro' ),
			'description' => __( 'Every row its own card, with the page showing between them. A list of things rather than a spreadsheet.', 'live-sheets-table-pro' ),
		);
		$presets['terminal']  = array(
			'label'       => __( 'Terminal', 'live-sheets-table-pro' ),
			'description' => __( 'Typewriter lettering and thin mint lines on near-black. Figures line up of their own accord.', 'live-sheets-table-pro' ),
		);
		$presets['aurora']    = array(
			'label'       => __( 'Aurora', 'live-sheets-table-pro' ),
			'description' => __( 'A heading bar that sweeps through violet, pink and amber over a crisp white table — or through shades of any heading colour you pick. Colourful without getting in the way of the figures.', 'live-sheets-table-pro' ),
		);
		$presets['ledger']    = array(
			'label'       => __( 'Ledger', 'live-sheets-table-pro' ),
			'description' => __( 'Warm paper, a double rule under the headings and a band down every other column. For a sheet of figures: the bands hold the eye in one column, and the numbers line up of their own accord.', 'live-sheets-table-pro' ),
		);
		$presets['contrast']  = array(
			'label'       => __( 'Contrast', 'live-sheets-table-pro' ),
			'description' => __( 'A solid heading bar over a plain table, and a first column with some weight to it.', 'live-sheets-table-pro' ),
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
			LSTABP_VERSION
		);
	}

	public function add_stylesheet( $handles ) {
		$handles   = (array) $handles;
		$handles[] = 'lstabp-styles';

		return $handles;
	}
}
