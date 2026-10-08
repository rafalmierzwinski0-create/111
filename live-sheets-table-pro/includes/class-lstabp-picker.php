<?php

defined( 'ABSPATH' ) || exit;

class LSTABP_Picker {
	const MAX_ROWS = 200;

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'lstab_edit_page_settings', array( $this, 'render_card' ), 5, 2 );
	}

	public function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, LSTAB_Admin::EDIT_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'lstabp-picker',
			LSTABP_URL . 'assets/css/lstabp-picker.css',
			array(),
			LSTABP_VERSION
		);

		wp_enqueue_script(
			'lstabp-picker',
			LSTABP_URL . 'assets/js/lstabp-picker.js',
			array(),
			LSTABP_VERSION,
			true
		);

		wp_localize_script(
			'lstabp-picker',
			'lstabpPicker',
			array(
				'i18n' => array(
					'showRowAgain' => __( 'Show this row again', 'live-sheets-table-pro' ),
					'notThereNow'  => __( 'not on that line now', 'live-sheets-table-pro' ),
					'shown'        => __( 'Shown', 'live-sheets-table-pro' ),
					'hidden'       => __( 'Hidden', 'live-sheets-table-pro' ),
					'inDetails'    => __( 'In the details', 'live-sheets-table-pro' ),
					/* translators: 1: page number, 2: number of pages, 3: total rows. */
					'rowsPage'     => __( 'Rows — page %1$s of %2$s (%3$s in all)', 'live-sheets-table-pro' ),
					/* translators: 1: page number, 2: number of pages, 3: total columns. */
					'colsPage'     => __( 'Columns — page %1$s of %2$s (%3$s in all)', 'live-sheets-table-pro' ),
				),
			)
		);
	}

	public function render_card( $source, $is_edit ) {
		if ( ! $is_edit || ! is_array( $source ) ) {
			return;
		}

		$headers = isset( $source['data']['headers'] ) ? (array) $source['data']['headers'] : array();
		$rows    = isset( $source['data']['rows'] ) ? (array) $source['data']['rows'] : array();

		if ( ! $headers && ! $rows ) {
			return;
		}

		$columns = isset( $source['columns_config'] ) ? (array) $source['columns_config'] : array();
		$hidden  = LSTAB_Hidden_Rows::sanitize( isset( $source['hidden_rows'] ) ? $source['hidden_rows'] : array() );
		$dropped = LSTAB_Hidden_Rows::positions( $hidden, $rows );
		$offset  = isset( $source['data']['offset'] ) ? (int) $source['data']['offset'] : 0;
		$shown   = array_slice( $rows, 0, self::MAX_ROWS );

		include LSTABP_PATH . 'includes/views/picker-card.php';
	}
}
