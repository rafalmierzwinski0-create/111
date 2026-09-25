<?php
/**
 * Table style presets.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Preset registry.
 */
class LSTAB_Styles {

	/**
	 * All presets, including the ones reserved for Pro.
	 *
	 * @return array<string,array{label:string,description:string,pro:bool}>
	 */
	public static function all() {
		$presets = array(
			'clean'     => array(
				'label'       => __( 'Clean', 'live-sheets-table' ),
				'description' => __( 'Light lines between rows and generous spacing. Uses your theme\'s fonts.', 'live-sheets-table' ),
				'pro'         => false,
			),
			'striped'   => array(
				'label'       => __( 'Striped', 'live-sheets-table' ),
				'description' => __( 'Alternating row shading, which makes a long list easier to follow.', 'live-sheets-table' ),
				'pro'         => false,
			),
			'bordered'  => array(
				'label'       => __( 'Bordered', 'live-sheets-table' ),
				'description' => __( 'A full grid with a shaded heading row. Suits dense columns of numbers.', 'live-sheets-table' ),
				'pro'         => false,
			),
			'midnight'  => array(
				'label'       => __( 'Midnight', 'live-sheets-table' ),
				'description' => __( 'A dark table with high contrast.', 'live-sheets-table' ),
				'pro'         => true,
			),
			'editorial' => array(
				'label'       => __( 'Editorial', 'live-sheets-table' ),
				'description' => __( 'Serif headings and very thin lines, in the manner of a printed table.', 'live-sheets-table' ),
				'pro'         => true,
			),
			'cards'     => array(
				'label'       => __( 'Cards', 'live-sheets-table' ),
				'description' => __( 'Every row its own card, with the page showing between them. A list of things rather than a spreadsheet.', 'live-sheets-table' ),
				'pro'         => true,
			),
			'terminal'  => array(
				'label'       => __( 'Terminal', 'live-sheets-table' ),
				'description' => __( 'Typewriter lettering and thin mint lines on near-black. Figures line up of their own accord.', 'live-sheets-table' ),
				'pro'         => true,
			),
			'glass'     => array(
				'label'       => __( 'Glass', 'live-sheets-table' ),
				'description' => __( 'A frosted panel that lets what is behind it show through. Needs a photograph or a gradient underneath to mean anything.', 'live-sheets-table' ),
				'pro'         => true,
			),
			'contrast'  => array(
				'label'       => __( 'Contrast', 'live-sheets-table' ),
				'description' => __( 'A solid heading bar over a plain table, and a first column with some weight to it.', 'live-sheets-table' ),
				'pro'         => true,
			),
		);

		/**
		 * Filters the available style presets.
		 *
		 * The Pro add-on registers its own presets here and flips 'pro' to
		 * false on the ones it unlocks.
		 *
		 * @param array $presets Preset definitions keyed by slug.
		 */
		return (array) apply_filters( 'lstab_style_presets', $presets );
	}

	/**
	 * Presets the current tier may actually use.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function available() {
		if ( LSTAB_Limits::is_pro() ) {
			return self::all();
		}

		return array_filter(
			self::all(),
			static function ( $preset ) {
				return empty( $preset['pro'] );
			}
		);
	}

	/**
	 * Coerce a preset slug to one the current tier can render.
	 *
	 * @param string $slug Requested preset.
	 * @return string
	 */
	public static function sanitize( $slug ) {
		$slug      = sanitize_key( (string) $slug );
		$available = self::available();

		return isset( $available[ $slug ] ) ? $slug : 'clean';
	}
}
