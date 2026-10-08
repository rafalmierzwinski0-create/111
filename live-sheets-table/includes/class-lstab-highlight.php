<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Highlight {
	protected static $needle = '';

	public function register() {
		add_filter( 'lstab_render_cell', array( __CLASS__, 'cell' ), 20, 5 );
	}

	public static function begin( $needle ) {
		self::$needle = trim( (string) $needle );
	}

	public static function end() {
		self::$needle = '';
	}

	public static function cell( $html, $value, $col_index = 0, $row_index = 0, $source = array() ) {
		if ( '' === self::$needle ) {
			return $html;
		}

		$marked = self::mark( null === $html ? esc_html( (string) $value ) : (string) $html );

		return null === $html && $marked === esc_html( (string) $value ) ? null : $marked;
	}

	public static function mark( $html ) {
		$needle = esc_html( self::$needle );

		if ( '' === $needle ) {
			return $html;
		}

		$pattern = '~' . preg_quote( $needle, '~' ) . '~iu';

		$parts = preg_split( '~(<[^>]*>)~', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out   = '';

		foreach ( (array) $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}

			if ( '<' === $part[0] ) {
				$out .= $part;
				continue;
			}

			$replaced = preg_replace( $pattern, '<mark class="lstab-hit">$0</mark>', $part );

			$out .= null === $replaced ? $part : $replaced;
		}

		return $out;
	}
}
