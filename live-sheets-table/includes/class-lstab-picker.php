<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Picker {
	const MAX_ROWS = 200;

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, LSTAB_Admin::EDIT_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'lstab-picker',
			LSTAB_URL . 'assets/css/lstab-picker.css',
			array(),
			LSTAB_Plugin::asset_version( 'assets/css/lstab-picker.css' )
		);

		wp_enqueue_script(
			'lstab-picker',
			LSTAB_URL . 'assets/js/lstab-picker.js',
			array(),
			LSTAB_Plugin::asset_version( 'assets/js/lstab-picker.js' ),
			true
		);

		wp_localize_script(
			'lstab-picker',
			'lstabPicker',
			array(
				'i18n' => array(
					'showRowAgain' => __( 'Show this row again', 'live-sheets-table' ),
					'notThereNow'  => __( 'not on that line now', 'live-sheets-table' ),
					'shown'        => __( 'Shown', 'live-sheets-table' ),
					'hidden'       => __( 'Hidden', 'live-sheets-table' ),
					'inDetails'    => __( 'In the details', 'live-sheets-table' ),
					/* translators: 1: page number, 2: number of pages, 3: total rows. */
					'rowsPage'     => __( 'Rows — page %1$s of %2$s (%3$s in all)', 'live-sheets-table' ),
					/* translators: 1: page number, 2: number of pages, 3: total columns. */
					'colsPage'     => __( 'Columns — page %1$s of %2$s (%3$s in all)', 'live-sheets-table' ),
				),
			)
		);
	}

	public static function render_card( $source, $is_edit ) {
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

		include LSTAB_PATH . 'includes/views/picker-card.php';
	}
}
