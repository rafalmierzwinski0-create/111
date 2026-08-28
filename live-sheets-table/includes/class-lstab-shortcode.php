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
		$atts = shortcode_atts(
			array(
				'id'      => 0,
				'search'  => 'yes',
				'sort'    => 'yes',
				'meta'    => 'yes',
				'style'   => '',
				'caption' => '',
				'class'   => '',
				'layout'  => 'auto',
			),
			$atts,
			self::TAG
		);

		return LSTAB_Renderer::render(
			array(
				'source_id' => absint( $atts['id'] ),
				'search'    => self::boolish( $atts['search'] ),
				'sort'      => self::boolish( $atts['sort'] ),
				'show_meta' => self::boolish( $atts['meta'] ),
				'style'     => sanitize_key( $atts['style'] ),
				'caption'   => sanitize_text_field( $atts['caption'] ),
				'class'     => sanitize_html_class( $atts['class'] ),
				'layout'    => sanitize_key( $atts['layout'] ),
			)
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
