<?php
/**
 * Per-table custom CSS.
 *
 * Every appearance setting in this plugin is a choice from a list, and a list
 * can never cover the last five per cent: a theme with its own idea about table
 * borders, a heading that needs one more pixel of padding, a client who wants
 * their brand's font. Without somewhere to put a rule, that five per cent
 * becomes a support ticket the plugin cannot answer.
 *
 * Two things make it safe to offer.
 *
 * First, every rule is confined to the table it was written for. CSS has no
 * scope of its own, so a rule typed here is rewritten with the table's own
 * selector in front of it: "td { padding: 2em }" becomes
 * "[data-lstab-id='7'] td { padding: 2em }" and cannot reach the theme's other
 * tables, the menu, or the page around it.
 *
 * Second, the only string that can end a style block is "</style", so "</" is
 * removed on the way in. It is not valid CSS anywhere outside a quoted string,
 * so nothing legitimate is lost, and no amount of cleverness inside the field
 * can get out of the block and become markup.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Storage, scoping and output of per-source CSS.
 */
class LSTAB_Custom_Css {

	/**
	 * Longest accepted, in bytes.
	 *
	 * Generous for the handful of rules this is for, and small enough that a
	 * paste of an entire framework is refused rather than stored and printed on
	 * every page view.
	 */
	const MAX_LENGTH = 20000;

	/**
	 * Sources already printed this request, so a page holding the same table
	 * twice does not carry its rules twice.
	 *
	 * @var array<int,bool>
	 */
	protected static $printed = array();

	/**
	 * Whether the current user may write CSS.
	 *
	 * Managing tables and writing CSS are different powers: a rule can cover
	 * the page it is on or pull an image from anywhere, which is the same power
	 * as writing raw markup into a post. WordPress already has a name for who
	 * is allowed that — unfiltered_html — so this follows it rather than
	 * inventing a second answer. On an ordinary site that is administrators and
	 * editors; on a network, the network's administrators.
	 *
	 * @return bool
	 */
	public static function user_can_edit() {
		return current_user_can( 'unfiltered_html' );
	}

	/**
	 * Clean a submitted stylesheet.
	 *
	 * Deliberately not a CSS validator. A rule this does not understand is the
	 * author's problem and the browser will ignore it; the job here is only to
	 * make sure nothing in the field can leave the style block or reach out of
	 * the site.
	 *
	 * @param string $css Raw input.
	 * @return string
	 */
	public static function sanitize( $css ) {
		$css = (string) $css;

		// Normalise line endings so a stored stylesheet is byte-identical
		// whichever operating system it was typed on.
		$css = str_replace( array( "\r\n", "\r" ), "\n", $css );

		/*
		 * The one sequence that can close the style block. Not valid CSS
		 * outside a string, so nothing usable is lost with it — but removing it
		 * once is not enough: taking "</" out of "<</" + "/style" joins what was
		 * on either side back into "</style", which is exactly the thing being
		 * removed. Repeating until the text stops changing is the only honest
		 * way to say it is gone.
		 */
		do {
			$before = $css;
			$css    = str_replace( '</', '', $css );
		} while ( $before !== $css );

		/*
		 * @import would let a table pull a stylesheet from another server on
		 * every page view — a request the site owner did not make, on a page
		 * they thought was theirs, and a way round the scoping below. Rules
		 * belong in the field.
		 */
		$css = (string) preg_replace( '#@import\b[^;]*;?#i', '', $css );

		// Old Internet Explorer would run script from these. Long dead, but
		// they cost nothing to refuse and are unmistakably not styling.
		$css = (string) preg_replace( '#(expression|behaviou?r|-moz-binding)\s*[:(]#i', '', $css );
		$css = (string) preg_replace( '#javascript\s*:#i', '', $css );

		if ( strlen( $css ) > self::MAX_LENGTH ) {
			// On a character boundary, or the stored value ends in half a
			// letter — which is not valid UTF-8 and which the database is
			// entitled to reject.
			$css = function_exists( 'mb_strcut' )
				? mb_strcut( $css, 0, self::MAX_LENGTH, 'UTF-8' )
				: substr( $css, 0, self::MAX_LENGTH );
		}

		return trim( $css );
	}

	/**
	 * The selector one table's rules are confined to.
	 *
	 * @param int $source_id Source ID.
	 * @return string
	 */
	public static function selector( $source_id ) {
		return '[data-lstab-id="' . (int) $source_id . '"]';
	}

	/**
	 * Put a selector in front of every rule.
	 *
	 * @param string $css      Sanitised stylesheet.
	 * @param string $selector Selector to confine it to.
	 * @return string
	 */
	public static function scope( $css, $selector ) {
		// Comments first: a brace inside one would otherwise be read as the
		// start of a rule and throw the rest of the file out of step.
		return self::scope_block( self::strip_comments( (string) $css ), $selector );
	}

	/**
	 * Remove comments, leaving anything inside a quoted string alone.
	 *
	 * Done by hand rather than with a pattern because a pattern cannot tell a
	 * comment from the characters "/*" inside content: "…", and removing half a
	 * string would turn a harmless rule into a broken one.
	 *
	 * @param string $css Stylesheet.
	 * @return string
	 */
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

	/**
	 * Scope one level of rules, descending into any at-rule that holds more.
	 *
	 * @param string $css      A stylesheet, or the inside of an at-rule.
	 * @param string $selector Selector to confine it to.
	 * @return string
	 */
	protected static function scope_block( $css, $selector ) {
		$out    = '';
		$buffer = '';
		$length = strlen( $css );
		$quote  = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $css[ $i ];

			/*
			 * Braces and commas inside a quoted value are content, not
			 * structure: content: "}" is a perfectly ordinary rule, and reading
			 * its brace as the end of the block would throw everything after it
			 * out of step.
			 */
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
						// These hold ordinary rules, which still need scoping.
						$out .= $header . '{' . self::scope_block( $inner, $selector ) . '}';
					} elseif ( preg_match( '#^@(keyframes|-webkit-keyframes|font-face|page|counter-style|property)\b#i', $header ) ) {
						// These hold something else entirely — percentages, a
						// font description — and prefixing it would break them.
						$out .= $header . '{' . $inner . '}';
					}

					// Anything else at-shaped is left out: it is either a typo
					// or a rule this plugin has no business printing.
					continue;
				}

				$out .= self::prefix_selector( $header, $selector ) . '{' . trim( $inner ) . '}';
				continue;
			}

			if ( '}' === $char ) {
				// A stray closing brace, from a rule that was never opened.
				$buffer = '';
				continue;
			}

			if ( ';' === $char && '@' === substr( ltrim( $buffer ), 0, 1 ) ) {
				// An at-rule with no block of its own, such as @charset.
				$buffer = '';
				continue;
			}

			$buffer .= $char;
		}

		return $out;
	}

	/**
	 * Confine one comma-separated selector list to the table.
	 *
	 * "&" stands for the table itself, so "&.lstab-paged td" can reach the
	 * wrapper rather than only its descendants. Without it the selector is
	 * treated as something inside the table, which is what almost every rule
	 * written here will be.
	 *
	 * @param string $header   Selector list as typed.
	 * @param string $selector Selector to confine it to.
	 * @return string
	 */
	protected static function prefix_selector( $header, $selector ) {
		$parts = array();
		$depth = 0;
		$part  = '';

		// Split on commas, but not the ones inside :is(), :not() and friends,
		// and not the ones inside an attribute selector's quoted value.
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

	/**
	 * The style block for one source, or nothing.
	 *
	 * @param int    $source_id Source ID.
	 * @param string $css       Stored stylesheet.
	 * @param bool   $once      Print at most once per source per request.
	 * @return string
	 */
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

		/*
		 * The belt to sanitize()'s braces. Everything above is meant to make
		 * this impossible; if some future edit makes it possible again, the
		 * table loses its styling rather than the page losing its style block.
		 */
		if ( false !== stripos( $scoped, '</' ) ) {
			return '';
		}

		if ( $once ) {
			self::$printed[ $source_id ] = true;
		}

		// Not escaped, and it does not need to be: sanitize() has already taken
		// out the only sequence that could end this element, and scope() has
		// rebuilt the rest from parsed pieces rather than passing text through.
		return '<style class="lstab-custom-css" data-lstab-css="' . $source_id . '">' . $scoped . '</style>';
	}

	/**
	 * Forget what has been printed, so one request can render the same table
	 * twice on purpose — the preview endpoint being the case that needs it.
	 *
	 * @return void
	 */
	public static function reset_printed() {
		self::$printed = array();
	}
}
