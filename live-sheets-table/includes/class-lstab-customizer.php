<?php
/**
 * Per-source appearance overrides.
 *
 * Every colour and metric the stylesheet uses is already a CSS custom property
 * on the table wrapper, so an override is simply that property set inline. No
 * generated stylesheet, no !important, and a value left empty falls straight
 * back to whatever the chosen preset defines.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Appearance token registry.
 */
class LSTAB_Customizer {

	/**
	 * Whether the visual editor is offered at all.
	 *
	 * Exposed as a filter so the appearance panel can be moved behind the Pro
	 * add-on later without touching this code.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) apply_filters( 'lstab_customizer_enabled', true );
	}

	/**
	 * Editable colour tokens.
	 *
	 * @return array<string,array{label:string,var:string,note?:string}>
	 */
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
				/*
				 * One of these belongs to one style, and until now it
				 * sat among the rest on all nine — a colour well that does
				 * nothing is a colour well somebody sets, saves, and then goes
				 * looking for on the page. 'style' says which style a swatch
				 * is for; the screen shows it only when that style is chosen.
				 */
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

	/**
	 * Editable metric tokens, each a fixed set of choices rather than a free
	 * number, so a table cannot be configured into something unreadable.
	 *
	 * @return array<string,array{label:string,choices:array,vars:array}>
	 */
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
					/*
					 * Lengths, not whole borders, so this says nothing about the
					 * colour: Line colour above stays in charge of that whichever
					 * choice is made here. A table folded into cards on a phone
					 * ignores both — see --lstab-table-mode in the stylesheet.
					 */
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
						/*
						 * Each choice places both things in the strip, and
						 * places them apart: the freshness line keeps left
						 * unless the buttons are already there, in which case
						 * it goes to the far side rather than under them.
						 */
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

	/**
	 * Editable sizes, in whole pixels, each held between a floor and a ceiling.
	 *
	 * Small, Normal and Large were too coarse to match a theme by, and a free
	 * number is how a table ends up with sixty-pixel values. So: a number, and
	 * a range that keeps it a table. Anything past the ceiling is held at the
	 * ceiling rather than refused, so "60" still gets the biggest size there is.
	 *
	 * The two are independent. The text size reaches the values in the rows and
	 * nothing else; the headings keep their own size whatever the rows are set
	 * to, which is what somebody enlarging the figures expects.
	 *
	 * @return array<string,array{label:string,note:string,var:string,min:int,max:int,legacy:array<string,int>}>
	 */
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
					// What the old Small, Normal and Large came to, on the
					// sixteen-pixel text most themes set.
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

	/**
	 * One size, made safe: a whole number of pixels inside its range, or ''.
	 *
	 * @param mixed                $raw  What was submitted or stored.
	 * @param array<string,mixed> $size The size's definition.
	 * @return string
	 */
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

	/**
	 * Empty override set.
	 *
	 * @return array<string,string>
	 */
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

	/**
	 * Clean a submitted or stored override set.
	 *
	 * Anything unrecognised is dropped rather than passed through, so nothing
	 * from this array can reach the style attribute unchecked.
	 *
	 * @param mixed $raw Raw values.
	 * @return array<string,string>
	 */
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

	/**
	 * Whether any override is actually set.
	 *
	 * @param array<string,string> $values Sanitised overrides.
	 * @return bool
	 */
	public static function has_overrides( $values ) {
		foreach ( self::css_map( $values ) as $unused ) {
			return true;
		}

		return false;
	}

	/**
	 * Resolve overrides into CSS custom properties.
	 *
	 * @param array<string,string> $values Sanitised overrides.
	 * @return array<string,string> Property name to value.
	 */
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

	/**
	 * Build the inline style attribute value for a table wrapper.
	 *
	 * @param array<string,string> $values Sanitised overrides.
	 * @return string Empty when nothing is overridden.
	 */
	public static function inline_style( $values ) {
		$declarations = array();

		foreach ( self::css_map( $values ) as $property => $value ) {
			// Both halves are ours: property names come from the registry and
			// values are already hex colours or fixed keyword lookups. This is
			// belt and braces against a filter returning something odd.
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
