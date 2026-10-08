<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Elementor {
	public function register() {
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
	}

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

	public function register_widget( $widgets ) {
		if ( ! class_exists( '\\Elementor\\Widget_Base' ) || ! is_object( $widgets ) || ! method_exists( $widgets, 'register' ) ) {
			return;
		}

		require_once LSTAB_PATH . 'includes/elementor/class-lstab-elementor-widget.php';

		$widgets->register( new LSTAB_Elementor_Widget() );
	}

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
