<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Custom_Css {
	const MAX_LENGTH = 20000;

	const MAX_DEPTH = 16;

	const PINNED = ':is(&,&>td):is(.lstab-sticky-first tr:not(.lstab-detail)>:first-child:not(.lstab-painted))';

	protected static $printed = array();

	public static function user_can_edit() {
		return current_user_can( 'unfiltered_html' );
	}

	public static function sanitize( $css ) {
		$css = (string) $css;

		$css = str_replace( array( "\r\n", "\r" ), "\n", $css );

		$css = str_replace( '</', '<\\/', $css );

		$css = (string) preg_replace( '#@import\b[^;]*;?#i', '', $css );

		$css = (string) preg_replace( '#(?<![\w-])(expression|behaviou?r)\s*[:(]|-moz-binding\s*:#i', '', $css );
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
		return self::ascii( self::scope_block( self::strip_comments( (string) $css ), ':is(' . $selector . ', #lstab-none)' ) );
	}

	protected static function ascii( $css ) {
		if ( function_exists( 'mb_scrub' ) ) {
			$css = mb_scrub( $css, 'UTF-8' );
		}

		$escaped = preg_replace_callback(
			'/[\x{80}-\x{10FFFF}]/u',
			function ( $match ) {
				$char  = $match[0];
				$first = ord( $char[0] );
				$code  = $first >= 0xF0 ? $first & 0x07 : ( $first >= 0xE0 ? $first & 0x0F : $first & 0x1F );
				$count = strlen( $char );

				for ( $i = 1; $i < $count; $i++ ) {
					$code = ( $code << 6 ) | ( ord( $char[ $i ] ) & 0x3F );
				}

				return '\\' . dechex( $code ) . ' ';
			},
			$css
		);

		return null === $escaped ? $css : $escaped;
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

	protected static function scope_block( $css, $selector, $level = 0 ) {
		if ( $level > self::MAX_DEPTH ) {
			return '';
		}

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
					if ( preg_match( '#^@scope\b#i', $header ) ) {
						$out .= self::scope_prelude( $header, $selector ) . '{' . self::scope_block( $inner, null, $level + 1 ) . '}';
					} elseif ( preg_match( '#^@(media|supports|container|layer|starting-style)\b#i', $header ) ) {
						$out .= $header . '{' . self::scope_block( $inner, $selector, $level + 1 ) . '}';
					} elseif ( preg_match( '#^@(keyframes|-webkit-keyframes|font-face|counter-style|property|font-feature-values|font-palette-values)\b#i', $header ) ) {
						$out .= $header . '{' . $inner . '}';
					}

					continue;
				}

				$parts = self::prefix_parts( $header, $selector );
				$out  .= implode( ',', $parts ) . '{' . trim( $inner ) . '}' . self::pinned_paint( $parts, $inner, $level );
				continue;
			}

			if ( '}' === $char ) {
				$buffer = '';
				continue;
			}

			if ( ';' === $char && '@' === substr( ltrim( $buffer ), 0, 1 ) ) {
				if ( preg_match( '#^@layer\s+[\w-]+(\s*,\s*[\w-]+)*$#i', trim( $buffer ) ) ) {
					$out .= trim( $buffer ) . ';';
				}
				$buffer = '';
				continue;
			}

			$buffer .= $char;
		}

		return $out;
	}

	protected static function pinned_paint( $parts, $inner, $level ) {
		$parts = array_filter(
			$parts,
			function ( $part ) {
				return ! preg_match( '#::|:(before|after|first-line|first-letter)\b#i', $part );
			}
		);

		$inside = self::pinned_layer( $inner ) . self::nested_paint( $inner, $level + 1 );

		return ( empty( $parts ) || '' === $inside ) ? '' : implode( ',', $parts ) . '{' . $inside . '}';
	}

	protected static function pinned_layer( $inner ) {
		$paint = array();

		foreach ( self::declarations( $inner ) as $declaration ) {
			if ( preg_match( '#^background(-[a-z-]+)?\s*:#i', $declaration ) ) {
				$paint[] = $declaration;
			}
		}

		if ( empty( $paint ) ) {
			return '';
		}

		return self::PINNED . '{background:none;&::before{width:100%;border-radius:inherit;background-image:none;' . implode( ';', $paint ) . '}@media (resolution:1dppx),(resolution:2dppx),(resolution:3dppx),(resolution:4dppx){&:is(td)::before{inset-block:0}}}';
	}

	protected static function nested_paint( $inner, $level ) {
		$out = '';

		if ( $level > self::MAX_DEPTH ) {
			return $out;
		}

		foreach ( self::items( $inner ) as $item ) {
			if ( ! isset( $item[1] ) ) {
				continue;
			}

			list( $header, $body ) = $item;

			if ( '@' !== $header[0] ) {
				$out .= self::pinned_paint( self::prefix_parts( $header, null ), $body, $level );
			} elseif ( preg_match( '#^@(media|supports|container|layer|scope|starting-style)\b#i', $header ) ) {
				$inside = self::pinned_layer( $body ) . self::nested_paint( $body, $level + 1 );
				$out   .= '' === $inside ? '' : $header . '{' . $inside . '}';
			}
		}

		return $out;
	}

	protected static function declarations( $inner ) {
		$found = array();

		foreach ( self::items( $inner ) as $item ) {
			if ( ! isset( $item[1] ) ) {
				$found[] = $item[0];
			}
		}

		return $found;
	}

	protected static function items( $inner ) {
		$items  = array();
		$part   = '';
		$depth  = 0;
		$quote  = '';
		$length = strlen( $inner );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $inner[ $i ];

			if ( '' !== $quote ) {
				$part .= $char;
				if ( '\\' === $char && $i + 1 < $length ) {
					$part .= $inner[ ++$i ];
				} elseif ( $char === $quote ) {
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
			} elseif ( '{' === $char && 0 === $depth ) {
				$end    = self::closing( $inner, $i, '{', '}' );
				$header = trim( $part );

				if ( '' !== $header ) {
					$items[] = array( $header, substr( $inner, $i + 1, max( 0, $end - $i - 1 ) ) );
				}

				$part = '';
				$i    = $end;
				continue;
			} elseif ( ( ';' === $char || '}' === $char ) && 0 === $depth ) {
				if ( '' !== trim( $part ) ) {
					$items[] = array( trim( $part ) );
				}
				$part = '';
				continue;
			}

			$part .= $char;
		}

		if ( '' !== trim( $part ) ) {
			$items[] = array( trim( $part ) );
		}

		return $items;
	}

	protected static function closing( $text, $open, $left = '(', $right = ')' ) {
		$depth  = 0;
		$quote  = '';
		$length = strlen( $text );

		for ( $i = $open; $i < $length; $i++ ) {
			$char = $text[ $i ];

			if ( '' !== $quote ) {
				if ( '\\' === $char ) {
					$i++;
				} elseif ( $char === $quote ) {
					$quote = '';
				}
				continue;
			}

			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
			} elseif ( $left === $char ) {
				$depth++;
			} elseif ( $right === $char && 0 === --$depth ) {
				return $i;
			}
		}

		return $length;
	}

	protected static function scope_prelude( $header, $selector ) {
		if ( null === $selector ) {
			return $header;
		}

		$rest  = trim( substr( $header, 6 ) );
		$root  = '';
		$limit = '';

		if ( '(' === substr( $rest, 0, 1 ) ) {
			$end  = self::closing( $rest, 0 );
			$root = substr( $rest, 1, max( 0, $end - 1 ) );
			$rest = trim( (string) substr( $rest, $end + 1 ) );
		}

		if ( preg_match( '#^to\s*\(#i', $rest ) ) {
			$open  = strpos( $rest, '(' );
			$end   = self::closing( $rest, $open );
			$limit = ' to (' . substr( $rest, $open + 1, max( 0, $end - $open - 1 ) ) . ')';
		}

		$roots = '' === trim( $root ) ? array() : self::prefix_parts( $root, $selector );

		return '@scope (' . ( empty( $roots ) ? $selector : implode( ',', $roots ) ) . ')' . $limit;
	}

	protected static function prefix_parts( $header, $selector ) {
		$parts  = array();
		$part   = '';
		$amp    = false;
		$depth  = 0;
		$quote  = '';
		$length = strlen( $header );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $header[ $i ];

			if ( '' !== $quote ) {
				$part .= $char;
				if ( '\\' === $char && $i + 1 < $length ) {
					$part .= $header[ ++$i ];
				} elseif ( $char === $quote ) {
					$quote = '';
				}
				continue;
			}

			if ( '\\' === $char && $i + 1 < $length ) {
				$part .= $char . $header[ ++$i ];
				continue;
			}

			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
			} elseif ( '(' === $char || '[' === $char ) {
				$depth++;
			} elseif ( ')' === $char || ']' === $char ) {
				$depth = max( 0, $depth - 1 );
			} elseif ( ',' === $char && 0 === $depth ) {
				$parts[] = array( $part, $amp );
				$part    = '';
				$amp     = false;
				continue;
			} elseif ( '&' === $char && null !== $selector ) {
				$part .= $selector;
				$amp   = true;
				continue;
			} elseif ( ctype_space( $char ) ) {
				$part .= ' ' === substr( $part, -1 ) ? '' : ' ';
				continue;
			}

			$part .= $char;
		}

		$parts[] = array( $part, $amp );

		$scoped = array();

		foreach ( $parts as $one ) {
			$text = trim( $one[0] );

			if ( '' === $text ) {
				continue;
			}

			$scoped[] = ( null === $selector || $one[1] ) ? $text : $selector . ' ' . $text;
		}

		return $scoped;
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
