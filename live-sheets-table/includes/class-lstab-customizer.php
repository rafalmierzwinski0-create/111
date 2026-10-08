<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Customizer {
	public static function is_enabled() {
		return (bool) apply_filters( 'lstab_customizer_enabled', true );
	}

	public static function colors() {
		return (array) apply_filters(
			'lstab_customizer_colors',
			array(
				'text'       => array(
					'label' => __( 'Text', 'live-sheets-table' ),
					'var'   => '--lstab-fg',
				),
				'background' => array(
					'label' => __( 'Background', 'live-sheets-table' ),
					'var'   => '--lstab-bg',
				),
				'headerText' => array(
					'label' => __( 'Header text', 'live-sheets-table' ),
					'var'   => '--lstab-head-fg',
				),
				'headerBg'   => array(
					'label' => __( 'Header background', 'live-sheets-table' ),
					'var'   => '--lstab-head-bg',
				),
				'border'     => array(
					'label' => __( 'Line colour', 'live-sheets-table' ),
					'note'  => __( 'Between rows and around cells', 'live-sheets-table' ),
					'var'   => '--lstab-border',
				),
				'stripe'     => array(
					'label' => __( 'Striped rows', 'live-sheets-table' ),
					'note'  => __( 'The colour of every other row', 'live-sheets-table' ),
					'var'   => '--lstab-stripe',
					'style' => 'striped',
				),
				'hover'      => array(
					'label' => __( 'Row hover', 'live-sheets-table' ),
					'note'  => __( 'When the mouse is over a row', 'live-sheets-table' ),
					'var'   => '--lstab-hover',
				),
				'accent'     => array(
					'label' => __( 'Accent', 'live-sheets-table' ),
					'note'  => __( 'Links, sort arrows and page numbers', 'live-sheets-table' ),
					'var'   => '--lstab-accent',
				),
			)
		);
	}

	public static function metrics() {
		return (array) apply_filters(
			'lstab_customizer_metrics',
			array
			(
				'density'  => array(
					'label'   => __( 'Row height', 'live-sheets-table' ),
					'choices' => array(
						'compact' => __( 'Compact', 'live-sheets-table' ),
						'normal'  => __( 'Normal', 'live-sheets-table' ),
						'roomy'   => __( 'Roomy', 'live-sheets-table' ),
					),
					'vars'    => array(
						'compact' => array(
							'--lstab-pad-y' => '0.42em',
							'--lstab-pad-x' => '0.7em',
						),
						'normal'  => array(),
						'roomy'   => array(
							'--lstab-pad-y' => '1.05em',
							'--lstab-pad-x' => '1.2em',
						),
					),
				),
				'lines'    => array(
					'label'   => __( 'Lines', 'live-sheets-table' ),
					'note'    => __( 'Between the rows, and down between the columns', 'live-sheets-table' ),
					'choices' => array(
						'grid'   => __( 'Full grid', 'live-sheets-table' ),
						'normal' => __( 'Rows only', 'live-sheets-table' ),
						'none'   => __( 'No lines', 'live-sheets-table' ),
					),
					'vars'    => array(
						'grid'   => array(
							'--lstab-row-line' => '1px',
							'--lstab-col-line' => '1px',
						),
						'normal' => array(),
						'none'   => array(
							'--lstab-row-line' => '0px',
							'--lstab-col-line' => '0px',
						),
					),
				),
				'pagerAlign' => array(
					'label'   => __( 'Page buttons', 'live-sheets-table' ),
					'choices' => array(
						'left'   => __( 'Left', 'live-sheets-table' ),
						'normal' => __( 'Centred', 'live-sheets-table' ),
						'right'  => __( 'Right', 'live-sheets-table' ),
					),
					'vars'    => array(
						'left'   => array(
							'--lstab-pager-col'   => '1',
							'--lstab-pager-place' => 'start',
							'--lstab-meta-col'    => '3',
							'--lstab-meta-place'  => 'end',
						),
						'normal' => array(),
						'right'  => array(
							'--lstab-pager-col'   => '3',
							'--lstab-pager-place' => 'end',
							'--lstab-meta-col'    => '1',
							'--lstab-meta-place'  => 'start',
						),
					),
				),
				'corners'  => array(
					'label'   => __( 'Corners', 'live-sheets-table' ),
					'choices' => array(
						'square'  => __( 'Square', 'live-sheets-table' ),
						'normal'  => __( 'Rounded', 'live-sheets-table' ),
						'pill'    => __( 'Very rounded', 'live-sheets-table' ),
					),
					'vars'    => array(
						'square' => array( '--lstab-radius' => '0px' ),
						'normal' => array(),
						'pill'   => array( '--lstab-radius' => '18px' ),
					),
				),
			)
		);
	}

	public static function sizes() {
		return (array) apply_filters(
			'lstab_customizer_sizes',
			array(
				'fontSize'     => array(
					'label'  => __( 'Text size', 'live-sheets-table' ),
					'note'   => __( 'The values in the rows. Leave empty to follow the style.', 'live-sheets-table' ),
					'var'    => '--lstab-text-size',
					'min'    => 11,
					'max'    => 24,
					'legacy' => array(
						'small' => 14,
						'large' => 17,
					),
				),
				'headFontSize' => array(
					'label'  => __( 'Heading text size', 'live-sheets-table' ),
					'note'   => __( 'The column names. Leave empty to follow the style.', 'live-sheets-table' ),
					'var'    => '--lstab-head-font-size',
					'min'    => 10,
					'max'    => 22,
					'legacy' => array(
						'small' => 11,
						'large' => 14,
					),
				),
			)
		);
	}

	public static function sanitize_size( $raw, $size ) {
		$raw = strtolower( trim( (string) $raw ) );

		if ( isset( $size['legacy'][ $raw ] ) ) {
			return (string) (int) $size['legacy'][ $raw ];
		}

		if ( ! preg_match( '/^(\d{1,4})(?:\.\d+)?\s*(?:px)?$/', $raw, $found ) ) {
			return '';
		}

		return (string) max( (int) $size['min'], min( (int) $size['max'], (int) $found[1] ) );
	}

	public static function defaults() {
		$defaults = array();

		foreach ( array_keys( self::colors() ) as $key ) {
			$defaults[ $key ] = '';
		}
		foreach ( array_keys( self::metrics() ) as $key ) {
			$defaults[ $key ] = 'normal';
		}
		foreach ( array_keys( self::sizes() ) as $key ) {
			$defaults[ $key ] = '';
		}

		return $defaults;
	}

	public static function sanitize( $raw ) {
		$raw   = is_array( $raw ) ? $raw : array();
		$clean = self::defaults();

		foreach ( self::colors() as $key => $unused ) {
			if ( ! isset( $raw[ $key ] ) ) {
				continue;
			}
			$color = sanitize_hex_color( trim( (string) $raw[ $key ] ) );
			$clean[ $key ] = $color ? $color : '';
		}

		foreach ( self::metrics() as $key => $metric ) {
			if ( ! isset( $raw[ $key ] ) ) {
				continue;
			}
			$value = sanitize_key( (string) $raw[ $key ] );
			$clean[ $key ] = isset( $metric['choices'][ $value ] ) ? $value : 'normal';
		}

		foreach ( self::sizes() as $key => $size ) {
			if ( isset( $raw[ $key ] ) ) {
				$clean[ $key ] = self::sanitize_size( $raw[ $key ], $size );
			}
		}

		return $clean;
	}

	public static function has_overrides( $values ) {
		foreach ( self::css_map( $values ) as $unused ) {
			return true;
		}

		return false;
	}

	public static function css_map( $values ) {
		$values = self::sanitize( $values );
		$map    = array();

		foreach ( self::colors() as $key => $color ) {
			if ( ! empty( $values[ $key ] ) ) {
				$map[ $color['var'] ] = $values[ $key ];
			}
		}

		foreach ( self::metrics() as $key => $metric ) {
			$choice = isset( $values[ $key ] ) ? $values[ $key ] : 'normal';
			if ( ! isset( $metric['vars'][ $choice ] ) ) {
				continue;
			}
			foreach ( $metric['vars'][ $choice ] as $property => $value ) {
				$map[ $property ] = $value;
			}
		}

		foreach ( self::sizes() as $key => $size ) {
			if ( isset( $values[ $key ] ) && '' !== $values[ $key ] ) {
				$map[ $size['var'] ] = (int) $values[ $key ] . 'px';
			}
		}

		return $map;
	}

	public static function inline_style( $values ) {
		$declarations = array();

		foreach ( self::css_map( $values ) as $property => $value ) {
			$property = preg_replace( '/[^a-z0-9-]/i', '', $property );
			$value    = preg_replace( '/[^a-z0-9#.%,()\/ -]/i', '', (string) $value );

			if ( '' === $property || '' === $value ) {
				continue;
			}

			$declarations[] = $property . ':' . $value;
		}

		return $declarations ? implode( ';', $declarations ) . ';' : '';
	}
}
