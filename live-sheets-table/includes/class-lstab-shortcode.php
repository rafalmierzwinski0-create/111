<?php
/**
 * Shortcode wrapper.
 *
 * Deliberately a thin adapter over LSTAB_Renderer: the block and the shortcode
 * must never drift apart.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode handler.
 */
class LSTAB_Shortcode {

	const TAG = 'sheet_table';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
		add_shortcode( 'live_sheets_table', array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		/**
		 * Filters the attributes the shortcode understands.
		 *
		 * An add-on registering a name here also receives it in the render
		 * arguments, so a feature can be added without editing this file.
		 *
		 * @param array $defaults Attribute names mapped to default values.
		 */
		$defaults = (array) apply_filters(
			'lstab_shortcode_atts',
			array(
				'id'      => 0,
				'search'  => 'yes',
				'sort'    => 'yes',
				'meta'    => 'yes',
				'style'   => '',
				'caption' => '',
				'class'   => '',
				'layout'  => 'inherit',
				'filter'  => '',
			)
		);

		$atts = shortcode_atts( $defaults, $atts, self::TAG );

		// Anything an add-on registered rides along to the renderer untouched
		// beyond a light sanitise, since only that add-on knows its shape.
		$extra = array();
		foreach ( $atts as $name => $value ) {
			if ( ! array_key_exists( $name, self::core_atts() ) ) {
				$extra[ $name ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
			}
		}

		return LSTAB_Renderer::render( array_merge( $extra,
			array(
				'source_id' => absint( $atts['id'] ),
				'search'    => self::boolish( $atts['search'] ),
				'sort'      => self::boolish( $atts['sort'] ),
				'show_meta' => self::boolish( $atts['meta'] ),
				'style'     => sanitize_key( $atts['style'] ),
				'caption'   => sanitize_text_field( $atts['caption'] ),
				'class'     => sanitize_html_class( $atts['class'] ),
				'layout'    => sanitize_key( $atts['layout'] ),
				'filter'    => sanitize_text_field( $atts['filter'] ),
			)
		) );
	}

	/**
	 * The attributes this plugin owns.
	 *
	 * @return array<string,mixed>
	 */
	protected static function core_atts() {
		return array(
			'id'      => 0,
			'search'  => 'yes',
			'sort'    => 'yes',
			'meta'    => 'yes',
			'style'   => '',
			'caption' => '',
			'class'   => '',
			'layout'  => 'inherit',
			// Declared here, though nothing in the free plugin acts on it: the
			// plugin has to be able to see that a page asked for only some
			// rows, so it can refuse to show all of them when the add-on that
			// does the filtering is gone.
			'filter'  => '',
		);
	}

	/**
	 * Interpret the loose truthy values people actually type in shortcodes.
	 *
	 * @param mixed $value Attribute value.
	 * @return bool
	 */
	protected static function boolish( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return ! in_array( strtolower( trim( (string) $value ) ), array( 'no', 'false', '0', 'off', '' ), true );
	}
}
