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
			'description' => __( 'Serif headings, thin lines and a thin frame, like a table in a printed book.', 'live-sheets-table-pro' ),
		);
		$presets['cards']     = array(
			'label'       => __( 'Cards', 'live-sheets-table-pro' ),
			'description' => __( 'Every row is its own card, with space between them. Suits a list of things, such as products or people, more than a sheet of figures.', 'live-sheets-table-pro' ),
		);
		$presets['terminal']  = array(
			'label'       => __( 'Terminal', 'live-sheets-table-pro' ),
			'description' => __( 'Typewriter-style letters and thin mint lines on a near-black background. Numbers line up neatly.', 'live-sheets-table-pro' ),
		);
		$presets['aurora']    = array(
			'label'       => __( 'Aurora', 'live-sheets-table-pro' ),
			'description' => __( 'A row of column names that fades from violet through pink to amber, over a clean white table. If you pick your own heading colour, it fades through shades of that colour instead.', 'live-sheets-table-pro' ),
		);
		$presets['ledger']    = array(
			'label'       => __( 'Ledger', 'live-sheets-table-pro' ),
			'description' => __( 'A warm paper colour, a double line under the column names and every other column shaded. Made for tables of figures: the shading helps the eye follow one column, and numbers line up neatly.', 'live-sheets-table-pro' ),
		);
		$presets['contrast']  = array(
			'label'       => __( 'Contrast', 'live-sheets-table-pro' ),
			'description' => __( 'A dark row of column names over a plain table, with a shaded, bold first column.', 'live-sheets-table-pro' ),
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
