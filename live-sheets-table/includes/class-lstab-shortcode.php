<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Shortcode {
	const TAG = 'sheet_table';

	protected static $written = '';

	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
		add_shortcode( 'live_sheets_table', array( $this, 'render' ) );
		add_filter( 'pre_do_shortcode_tag', array( __CLASS__, 'remember_written' ), 10, 4 );
	}

	public static function remember_written( $output, $tag, $attr, $match ) {
		if ( self::TAG === $tag || 'live_sheets_table' === $tag ) {
			self::$written = is_array( $match ) && isset( $match[0] ) ? (string) $match[0] : '';
		}

		return $output;
	}

	public function render( $atts ) {
		$written       = self::$written;
		self::$written = '';

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

		if ( '' === trim( (string) $atts['filter'] ) && preg_match( '/\bfilter\s*=/i', $written ) && ! preg_match( '/\bfilter\s*=\s*(""|\'\')/i', $written ) ) {
			return LSTAB_Renderer::notice(
				__( 'WordPress could not read this table\'s filter, so no rows are shown. A shortcode cannot hold “<”: write lt instead of < and lte instead of <=, for example filter="Price lt 100". Check, too, that the quotes are straight (") rather than curly (”).', 'live-sheets-table' )
			);
		}

		if ( ! absint( $atts['id'] ) && preg_match( '/\bid\s*=\s*[^\s"\'\]]/iu', $written ) ) {
			return LSTAB_Renderer::notice(
				__( 'WordPress could not read which table this shortcode asks for. Its quotes are probably curly (”), which happens when a shortcode is copied from a document or another website. Type them again as straight quotes ("), for example [sheet_table id="1"].', 'live-sheets-table' )
			);
		}

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
