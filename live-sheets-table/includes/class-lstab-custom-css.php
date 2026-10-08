<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Custom_Css {
	const MAX_LENGTH = 20000;

	protected static $printed = array();

	public static function user_can_edit() {
		return current_user_can( 'unfiltered_html' );
	}

	public static function sanitize( $css ) {
		$css = (string) $css;

		$css = str_replace( array( "\r\n", "\r" ), "\n", $css );

		do {
			$before = $css;
			$css    = str_replace( '</', '', $css );
		} while ( $before !== $css );

		$css = (string) preg_replace( '#@import\b[^;]*;?#i', '', $css );

		$css = (string) preg_replace( '#(expression|behaviou?r|-moz-binding)\s*[:(]#i', '', $css );
		$css = (string) preg_replace( '#javascript\s*:#i', '', $css );

		if ( strlen( $css ) > self::MAX_LENGTH ) {
			$css = function_exists( 'mb_strcut' )
				? mb_strcut( $css, 0, self::MAX_LENGTH, 'UTF-8' )
				: substr( $css, 0, self::MAX_LENGTH );
		}

		return trim( $css );
	}

	public static function selector( $source_id ) {
		return '[data-lstab-id="' . (int) $source_id . '"]';
	}

	public static function scope( $css, $selector ) {
		return self::scope_block( self::strip_comments( (string) $css ), $selector );
	}

	protected static function strip_comments( $css ) {
		$out    = '';
		$length = strlen( $css );
		$quote  = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $css[ $i ];

			if ( '' !== $quote ) {
				$out .= $char;

				if ( '\\' === $char && $i + 1 < $length ) {
					$out .= $css[ $i + 1 ];
					$i++;
					continue;
				}

				if ( $char === $quote ) {
					$quote = '';
				}
				continue;
			}

			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
				$out  .= $char;
				continue;
			}

			if ( '/' === $char && $i + 1 < $length && '*' === $css[ $i + 1 ] ) {
				$end = strpos( $css, '*/', $i + 2 );
				$i   = ( false === $end ) ? $length : $end + 1;
				continue;
			}

			$out .= $char;
		}

		return $out;
	}

	protected static function scope_block( $css, $selector ) {
		$out    = '';
		$buffer = '';
		$length = strlen( $css );
		$quote  = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $css[ $i ];

			if ( '' !== $quote ) {
				$buffer .= $char;

				if ( '\\' === $char && $i + 1 < $length ) {
					$buffer .= $css[ $i + 1 ];
					$i++;
					continue;
				}

				if ( $char === $quote ) {
					$quote = '';
				}
				continue;
			}

			if ( '"' === $char || "'" === $char ) {
				$quote   = $char;
				$buffer .= $char;
				continue;
			}

			if ( '{' === $char ) {
				$depth = 1;
				$j     = $i + 1;
				$inner_quote = '';

				while ( $j < $length && $depth > 0 ) {
					$inner_char = $css[ $j ];

					if ( '' !== $inner_quote ) {
						if ( '\\' === $inner_char ) {
							$j += 2;
							continue;
						}
						if ( $inner_char === $inner_quote ) {
							$inner_quote = '';
						}
						$j++;
						continue;
					}

					if ( '"' === $inner_char || "'" === $inner_char ) {
						$inner_quote = $inner_char;
						$j++;
						continue;
					}

					if ( '{' === $inner_char ) {
						$depth++;
					} elseif ( '}' === $inner_char ) {
						$depth--;
					}
					$j++;
				}

				$inner  = substr( $css, $i + 1, $j - $i - 2 );
				$header = trim( $buffer );
				$buffer = '';
				$i      = $j - 1;

				if ( '' === $header ) {
					continue;
				}

				if ( '@' === $header[0] ) {
					if ( preg_match( '#^@(media|supports|container|layer|scope)\b#i', $header ) ) {
						$out .= $header . '{' . self::scope_block( $inner, $selector ) . '}';
					} elseif ( preg_match( '#^@(keyframes|-webkit-keyframes|font-face|page|counter-style|property)\b#i', $header ) ) {
						$out .= $header . '{' . $inner . '}';
					}

					continue;
				}

				$out .= self::prefix_selector( $header, $selector ) . '{' . trim( $inner ) . '}';
				continue;
			}

			if ( '}' === $char ) {
				$buffer = '';
				continue;
			}

			if ( ';' === $char && '@' === substr( ltrim( $buffer ), 0, 1 ) ) {
				$buffer = '';
				continue;
			}

			$buffer .= $char;
		}

		return $out;
	}

	protected static function prefix_selector( $header, $selector ) {
		$parts = array();
		$depth = 0;
		$part  = '';

		$length = strlen( $header );
		$quote  = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$char = $header[ $i ];

			if ( '' !== $quote ) {
				$part .= $char;
				if ( $char === $quote ) {
					$quote = '';
				}
				continue;
			}

			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
			} elseif ( '(' === $char ) {
				$depth++;
			} elseif ( ')' === $char ) {
				$depth = max( 0, $depth - 1 );
			} elseif ( ',' === $char && 0 === $depth ) {
				$parts[] = $part;
				$part    = '';
				continue;
			}

			$part .= $char;
		}

		$parts[] = $part;

		$scoped = array();

		foreach ( $parts as $one ) {
			$one = trim( preg_replace( '#\s+#', ' ', $one ) );

			if ( '' === $one ) {
				continue;
			}

			$scoped[] = false !== strpos( $one, '&' )
				? str_replace( '&', $selector, $one )
				: $selector . ' ' . $one;
		}

		return implode( ',', $scoped );
	}

	public static function style_tag( $source_id, $css, $once = true ) {
		$source_id = (int) $source_id;
		$css       = self::sanitize( $css );

		if ( '' === $css || ( $once && isset( self::$printed[ $source_id ] ) ) ) {
			return '';
		}

		$scoped = self::scope( $css, self::selector( $source_id ) );

		if ( '' === trim( $scoped ) ) {
			return '';
		}

		if ( false !== stripos( $scoped, '</' ) ) {
			return '';
		}

		if ( $once ) {
			self::$printed[ $source_id ] = true;
		}

		return '<style class="lstab-custom-css" data-lstab-css="' . $source_id . '">' . $scoped . '</style>';
	}

	public static function reset_printed() {
		self::$printed = array();
	}
}
