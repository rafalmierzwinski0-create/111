<?php

defined( 'ABSPATH' ) || exit;

class LSTABP_Custom_Css {
	const OPTION = 'lstabp_custom_css';

	const REST_NAMESPACE = 'live-sheets-table-pro/v1';

	const MAX_LENGTH = 20000;

	const MAX_DEPTH = 16;

	const PINNED = ':is(&,&>td):is(.lstab-sticky-first tr:not(.lstab-detail)>:first-child:not(.lstab-painted))';

	protected static $printed = array();

	public function register() {
		add_filter( 'lstab_rendered_table', array( $this, 'add_style' ), 10, 2 );
		add_action( 'lstab_edit_pane_cards', array( $this, 'render_pane_card' ), 30, 3 );
		add_action( 'lstab_source_saved', array( $this, 'save' ) );
		add_action( 'lstab_source_deleted', array( $this, 'forget' ) );
		add_action( 'lstabp_forget_source', array( $this, 'forget' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, LSTAB_Admin::EDIT_SLUG ) || ! self::user_can_edit() ) {
			return;
		}

		wp_enqueue_script(
			'lstabp-css',
			LSTABP_URL . 'assets/js/lstabp-css.js',
			array( 'wp-api-fetch' ),
			LSTABP_Plugin::asset_version( 'assets/js/lstabp-css.js' ),
			true
		);
	}

	public static function all() {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	public static function for_source( $source_id ) {
		$all = self::all();

		return isset( $all[ (int) $source_id ] ) ? (string) $all[ (int) $source_id ] : '';
	}

	public static function store( $source_id, $css ) {
		$all = self::all();
		$css = self::sanitize( $css );

		if ( '' === $css ) {
			unset( $all[ (int) $source_id ] );
		} else {
			$all[ (int) $source_id ] = $css;
		}

		update_option( self::OPTION, $all, false );
	}

	public function add_style( $html, $source ) {
		$source_id = isset( $source['id'] ) ? (int) $source['id'] : 0;

		if ( $source_id <= 0 ) {
			return $html;
		}

		$tag = self::style_tag( $source_id, self::for_source( $source_id ) );

		if ( '' === $tag ) {
			return $html;
		}

		$opening = '<div class="lstab-container">';
		$at      = strpos( $html, $opening );

		if ( false === $at ) {
			return $tag . $html;
		}

		return substr_replace( $html, $opening . $tag, $at, strlen( $opening ) );
	}

	public function save( $source_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Runs inside the core plugin's save handler, after its nonce and capability checks.
		if ( ! isset( $_POST['_lstabp_custom_css_present'] ) || ! self::user_can_edit() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Cleaned in sanitize(); sanitize_text_field would eat the newlines and braces this is made of.
		self::store( $source_id, wp_unslash( $_POST['lstabp_custom_css'] ?? '' ) );
	}

	public function forget( $source_id ) {
		$all = self::all();

		if ( ! isset( $all[ (int) $source_id ] ) ) {
			return;
		}

		unset( $all[ (int) $source_id ] );
		update_option( self::OPTION, $all, false );
	}

	public function render_pane_card( $pane, $source, $is_edit ) {
		if ( 'look' !== $pane || ! self::user_can_edit() ) {
			return;
		}

		$lstabp_css_id    = ( $is_edit && $source ) ? (int) $source['id'] : 0;
		$lstabp_css_value = $lstabp_css_id ? self::for_source( $lstabp_css_id ) : '';

		require LSTABP_PATH . 'includes/views/custom-css-card.php';
	}

	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/scoped-css',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'scoped_css' ),
				'permission_callback' => array( $this, 'can_write_css' ),
				'args'                => array(
					'css'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'selector' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	public function can_write_css() {
		if ( current_user_can( LSTAB_Limits::capability() ) && self::user_can_edit() ) {
			return true;
		}

		return new WP_Error(
			'lstabp_forbidden',
			__( 'You are not allowed to write CSS on this site.', 'live-sheets-table-pro' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public function scoped_css( $request ) {
		$selector = trim( (string) $request->get_param( 'selector' ) );

		if ( ! preg_match( '#^\[data-lstab-preview="[a-z0-9-]{1,40}"\]$#', $selector ) ) {
			$selector = '[data-lstab-preview="none"]';
		}

		return rest_ensure_response(
			array(
				'css' => self::scope( self::sanitize( (string) $request->get_param( 'css' ) ), $selector . ' .lstab' ),
			)
		);
	}

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
