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
		if ( '' === self::$needle ) {
			return $html;
		}

		$pattern = '~(' . preg_quote( self::$needle, '~' ) . ')~iu';

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

			$pieces = preg_split( $pattern, html_entity_decode( $part, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), -1, PREG_SPLIT_DELIM_CAPTURE );

			if ( ! is_array( $pieces ) || count( $pieces ) < 2 ) {
				$out .= $part;
				continue;
			}

			foreach ( $pieces as $at => $piece ) {
				$out .= 1 === $at % 2 ? '<mark class="lstab-hit">' . esc_html( $piece ) . '</mark>' : esc_html( $piece );
			}
		}

		return $out;
	}
}
