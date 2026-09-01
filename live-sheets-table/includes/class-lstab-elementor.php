<?php
/**
 * Elementor support.
 *
 * The block covers the WordPress editor and the shortcode covers everything
 * else, but Elementor keeps its own catalogue: a plugin that is not in it is
 * not there at all, and its users have to paste a shortcode into a text widget
 * and lose the live preview. This puts the table in the catalogue.
 *
 * Nothing here renders anything of its own — the widget hands its settings to
 * the same renderer the block and the shortcode use, so all three always agree.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Elementor widget registration.
 */
class LSTAB_Elementor {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		// Fires only when Elementor is active, so nothing here costs anything
		// on a site without it.
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
	}

	/**
	 * Give the widget a category of its own in the panel.
	 *
	 * @param object $manager Elementor's category manager.
	 * @return void
	 */
	public function register_category( $manager ) {
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'add_category' ) ) {
			return;
		}

		$manager->add_category(
			'live-sheets-table',
			array(
				'title' => __( 'Google Sheets', 'live-sheets-table' ),
				'icon'  => 'eicon-table',
			)
		);
	}

	/**
	 * Add the widget to Elementor's catalogue.
	 *
	 * @param object $widgets Elementor's widget manager.
	 * @return void
	 */
	public function register_widget( $widgets ) {
		if ( ! class_exists( '\\Elementor\\Widget_Base' ) || ! is_object( $widgets ) || ! method_exists( $widgets, 'register' ) ) {
			return;
		}

		// Required here rather than at load: the parent class only exists once
		// Elementor itself has loaded.
		require_once LSTAB_PATH . 'includes/elementor/class-lstab-elementor-widget.php';

		$widgets->register( new LSTAB_Elementor_Widget() );
	}

	/**
	 * Sources offered in the widget's picker.
	 *
	 * @return array<int,string>
	 */
	public static function source_options() {
		$options = array( 0 => __( 'Select a sheet…', 'live-sheets-table' ) );

		foreach ( LSTAB_Storage::get_all() as $source ) {
			$options[ (int) $source['id'] ] = sprintf(
				/* translators: 1: source title, 2: row count. */
				__( '%1$s (%2$s rows)', 'live-sheets-table' ),
				$source['title'],
				number_format_i18n( (int) $source['row_count'] )
			);
		}

		return $options;
	}
}
