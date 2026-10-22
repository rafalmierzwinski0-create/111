<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Styles {
	public static function all() {
		$presets = array(
			'clean'    => array(
				'label' => __( 'Clean', 'live-sheets-table' ),
			),
			'striped'  => array(
				'label' => __( 'Striped', 'live-sheets-table' ),
			),
			'bordered' => array(
				'label' => __( 'Bordered', 'live-sheets-table' ),
			),
			'midnight' => array(
				'label' => __( 'Midnight', 'live-sheets-table' ),
			),
		);

		return (array) apply_filters( 'lstab_style_presets', $presets );
	}

	public static function thumbnail( $slug ) {
		$cells = '<i></i><i></i><i></i>';
		$rows  = str_repeat( '<span class="lstab-thumb-row">' . $cells . '</span>', 4 );

		return '<span class="lstab-thumb lstab-thumb-' . esc_attr( sanitize_html_class( (string) $slug ) ) . '" aria-hidden="true">'
			. '<span class="lstab-thumb-panel">'
			. '<span class="lstab-thumb-head">' . $cells . '</span>'
			. $rows
			. '</span>'
			. '</span>';
	}

	public static function sanitize( $slug ) {
		$slug      = sanitize_key( (string) apply_filters( 'lstab_style_alias', sanitize_key( (string) $slug ) ) );
		$available = self::all();

		return isset( $available[ $slug ] ) ? $slug : 'clean';
	}
}
