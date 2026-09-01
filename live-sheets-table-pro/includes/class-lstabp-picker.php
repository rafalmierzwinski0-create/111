<?php
/**
 * Point at what you want gone.
 *
 * @package LiveSheetsTablePro
 */

defined( 'ABSPATH' ) || exit;

/**
 * The sheet itself, as a control for hiding columns and rows.
 *
 * The free plugin can hide columns through a list of checkboxes, and knows how
 * to keep hidden rows out of a table, out of a search and out of a download.
 * What it does not have is this: the sheet drawn as a table you click, which is
 * the difference between a setting people find and one they do not.
 *
 * The storage stays in the free plugin on purpose. Someone who lets a licence
 * lapse must not have rows they hid quietly reappear on a public page — hiding
 * is subtractive, so honouring it costs nothing and forgetting it could publish
 * something. What they lose is the ability to change it by pointing.
 */
class LSTABP_Picker {

	/**
	 * How many rows are offered for clicking.
	 *
	 * Past this, picking rows one at a time is the wrong tool and the filter is
	 * the right one.
	 */
	const MAX_ROWS = 200;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'lstab_edit_page_settings', array( $this, 'render_card' ), 5, 2 );
	}

	/**
	 * Load the picker's own script and styles on the source screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
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
					/* translators: %d: how many rows say the same thing. */
					'alsoMatches'  => __( '%d rows', 'live-sheets-table-pro' ),
					'notInSheet'   => __( 'not in the sheet now', 'live-sheets-table-pro' ),
				),
			)
		);
	}

	/**
	 * How many rows of the sheet answer to each key.
	 *
	 * A row is remembered by what it says, so two rows saying the same thing
	 * are one choice, not two. That is usually right and occasionally a
	 * surprise, and a surprise is only a problem while it is invisible.
	 *
	 * @param array<int,array<int,string>> $rows Sheet rows.
	 * @return array<string,int>
	 */
	public static function key_counts( $rows ) {
		$counts = array();

		foreach ( (array) $rows as $row ) {
			$key = LSTAB_Hidden_Rows::key_for( $row );

			if ( '' === $key ) {
				continue;
			}

			$counts[ $key ] = isset( $counts[ $key ] ) ? $counts[ $key ] + 1 : 1;
		}

		return $counts;
	}

	/**
	 * Print the picker on the source screen.
	 *
	 * @param array<string,mixed>|null $source  Source row, or null while adding.
	 * @param bool                     $is_edit Whether an existing source is being edited.
	 * @return void
	 */
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
		$counts  = self::key_counts( $rows );
		$shown   = array_slice( $rows, 0, self::MAX_ROWS );

		include LSTABP_PATH . 'includes/views/picker-card.php';
	}
}
