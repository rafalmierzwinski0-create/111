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
	 * @return array<string,array{label:string,var:string,description:string}>
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
					'label' => __( 'Lines', 'live-sheets-table' ),
					'var'   => '--lstab-border',
				),
				'stripe'     => array(
					'label' => __( 'Striped rows', 'live-sheets-table' ),
					'var'   => '--lstab-stripe',
				),
				'hover'      => array(
					'label' => __( 'Row hover', 'live-sheets-table' ),
					'var'   => '--lstab-hover',
				),
				'accent'     => array(
					'label' => __( 'Accent', 'live-sheets-table' ),
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
				'fontSize' => array(
					'label'   => __( 'Text size', 'live-sheets-table' ),
					'choices' => array(
						'small'  => __( 'Small', 'live-sheets-table' ),
						'normal' => __( 'Normal', 'live-sheets-table' ),
						'large'  => __( 'Large', 'live-sheets-table' ),
					),
					'vars'    => array(
						'small'  => array( '--lstab-font-size' => '0.86em' ),
						'normal' => array(),
						'large'  => array( '--lstab-font-size' => '1.04em' ),
					),
				),
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
				'corners'  => array(
					'label'   => __( 'Corners', 'live-sheets-table' ),
					'choices' => array(
						'square'  => __( 'Square', 'live-sheets-table' ),
						'normal'  => __( 'Rounded', 'live-sheets-table' ),
						'pill'    => __( 'Very rounded', 'live-sheets-table' ),
					),
					'vars'    => array(
						'square' => array( '--lstab-radius' => '0' ),
						'normal' => array(),
						'pill'   => array( '--lstab-radius' => '18px' ),
					),
				),
			)
		);
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
