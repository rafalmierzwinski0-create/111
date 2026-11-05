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
					'label' => __( 'Text', 'tabliko' ),
					'var'   => '--lstab-fg',
				),
				'background' => array(
					'label' => __( 'Background', 'tabliko' ),
					'var'   => '--lstab-bg',
				),
				'headerText' => array(
					'label' => __( 'Header text', 'tabliko' ),
					'var'   => '--lstab-head-fg',
				),
				'headerBg'   => array(
					'label' => __( 'Header background', 'tabliko' ),
					'var'   => '--lstab-head-bg',
				),
				'border'     => array(
					'label' => __( 'Line colour', 'tabliko' ),
					'note'  => __( 'Between rows and around cells', 'tabliko' ),
					'var'   => '--lstab-border',
				),
				'stripe'     => array(
					'label' => __( 'Striped rows', 'tabliko' ),
					'note'  => __( 'The colour of every other row', 'tabliko' ),
					'var'   => '--lstab-stripe',
					'style' => 'striped',
				),
				'hover'      => array(
					'label' => __( 'Row hover', 'tabliko' ),
					'note'  => __( 'When the mouse is over a row', 'tabliko' ),
					'var'   => '--lstab-hover',
				),
				'accent'     => array(
					'label' => __( 'Accent', 'tabliko' ),
					'note'  => __( 'Links, sort arrows and page numbers', 'tabliko' ),
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
					'label'   => __( 'Row height', 'tabliko' ),
					'choices' => array(
						'compact' => __( 'Compact', 'tabliko' ),
						'normal'  => __( 'Normal', 'tabliko' ),
						'roomy'   => __( 'Roomy', 'tabliko' ),
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
					'label'   => __( 'Lines', 'tabliko' ),
					'note'    => __( 'Between the rows, and down between the columns', 'tabliko' ),
					'choices' => array(
						'grid'   => __( 'Full grid', 'tabliko' ),
						'normal' => __( 'Rows only', 'tabliko' ),
						'none'   => __( 'No lines', 'tabliko' ),
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
					'label'   => __( 'Page buttons', 'tabliko' ),
					'note'    => __( 'Where the page buttons sit under a table split into pages. The “updated … ago” line moves to the other side.', 'tabliko' ),
					'paged'   => true,
					'choices' => array(
						'left'   => __( 'Left', 'tabliko' ),
						'normal' => __( 'Centred', 'tabliko' ),
						'right'  => __( 'Right', 'tabliko' ),
					),
					'vars'    => array(
						'left'   => array(
							'--lstab-pager-col'   => '1',
							'--lstab-pager-place' => 'start',
							'--lstab-meta-col-paged'   => '3',
							'--lstab-meta-place-paged' => 'end',
						),
						'normal' => array(),
						'right'  => array(
							'--lstab-pager-col'   => '3',
							'--lstab-pager-place' => 'end',
							'--lstab-meta-col-paged'   => '1',
							'--lstab-meta-place-paged' => 'start',
						),
					),
				),
				'corners'  => array(
					'label'   => __( 'Corners', 'tabliko' ),
					'choices' => array(
						'square'  => __( 'Square', 'tabliko' ),
						'normal'  => __( 'Rounded', 'tabliko' ),
						'pill'    => __( 'Very rounded', 'tabliko' ),
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
					'label'  => __( 'Text size', 'tabliko' ),
					'note'   => __( 'The values in the rows. Leave empty to follow the style.', 'tabliko' ),
					'var'    => '--lstab-text-size',
					'min'    => 11,
					'max'    => 24,
					'legacy' => array(
						'small' => 14,
						'large' => 17,
					),
				),
				'headFontSize' => array(
					'label'  => __( 'Heading text size', 'tabliko' ),
					'note'   => __( 'The column names. Leave empty to follow the style.', 'tabliko' ),
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

		if ( ! empty( $values['headerText'] ) ) {
			$map['--lstab-head-strong'] = $values['headerText'];

			if ( ! empty( $values['headerBg'] ) ) {
				$map['--lstab-sort-on'] = $values['headerText'];
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
