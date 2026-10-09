<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Shortcode {
	const TAG = 'sheet_table';

	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
		add_shortcode( 'live_sheets_table', array( $this, 'render' ) );
	}

	public function render( $atts ) {
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
				'filter'    => self::filter_text( $atts['filter'] ),
			)
		) );
	}

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
			'filter'  => '',
		);
	}

	public static function filter_text( $value ) {
		$value = wp_check_invalid_utf8( is_scalar( $value ) ? (string) $value : '' );

		return trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $value ) );
	}

	protected static function boolish( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return ! in_array( strtolower( trim( (string) $value ) ), array( 'no', 'false', '0', 'off', '' ), true );
	}
}
