<?php

defined( 'ABSPATH' ) || exit;

class LSTABP_Rules {
	const OPTION = 'lstabp_rules';

	protected static $previewing = array();

	const MAX_RULES = 20;

	protected $cells = array();

	protected $shapes = array();

	protected $rows = array();

	protected $places = array();

	public function register() {
		add_filter( 'lstab_source_rows', array( $this, 'capture' ), 20, 4 );
		add_filter( 'lstab_cell_attributes', array( $this, 'attributes' ), 10, 5 );
		add_filter( 'lstab_sort_ranks', array( $this, 'sort_ranks' ), 10, 3 );
		add_filter( 'lstab_detail_attributes', array( $this, 'detail_attributes' ), 10, 2 );

		add_action( 'lstab_preview_request', array( $this, 'preview_request' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'lstab_edit_pane_cards', array( $this, 'render_pane_card' ), 10, 3 );
		add_action( 'lstab_source_saved', array( $this, 'save' ) );
		add_action( 'lstab_source_deleted', array( $this, 'forget' ) );
		add_action( 'lstabp_forget_source', array( $this, 'forget' ) );
	}

	public static function palette() {
		return array(
			'#fbd5d5' => __( 'Red', 'live-sheets-table-pro' ),
			'#fbdcbc' => __( 'Orange', 'live-sheets-table-pro' ),
			'#f7ecac' => __( 'Yellow', 'live-sheets-table-pro' ),
			'#cfebd9' => __( 'Green', 'live-sheets-table-pro' ),
			'#c4e4e1' => __( 'Teal', 'live-sheets-table-pro' ),
			'#d0e1f8' => __( 'Blue', 'live-sheets-table-pro' ),
			'#dad0f3' => __( 'Purple', 'live-sheets-table-pro' ),
			'#f6d2e2' => __( 'Pink', 'live-sheets-table-pro' ),
			'#e1e5e9' => __( 'Grey', 'live-sheets-table-pro' ),
		);
	}

	public static function effects() {
		return array(
			'bold'   => array(
				'label' => __( 'Bold text', 'live-sheets-table-pro' ),
				'chip'  => __( 'B', 'live-sheets-table-pro' ),
				'css'   => 'font-weight:700;',
			),
			'strike' => array(
				'label' => __( 'Struck through', 'live-sheets-table-pro' ),
				'chip'  => __( 'S', 'live-sheets-table-pro' ),
				'css'   => 'text-decoration:line-through;opacity:0.62;',
			),
		);
	}

	protected static function legacy() {
		return array(
			'red'   => '#fbd5d5',
			'amber' => '#fbdcbc',
			'green' => '#cfebd9',
			'blue'  => '#d0e1f8',
			'grey'  => '#e1e5e9',

			'#fdecec' => '#fbd5d5',
			'#ffe9d6' => '#fbdcbc',
			'#fdf6cf' => '#f7ecac',
			'#e9f7ee' => '#cfebd9',
			'#dff2f0' => '#c4e4e1',
			'#e8f1fd' => '#d0e1f8',
			'#eee9fb' => '#dad0f3',
			'#fce9f1' => '#f6d2e2',
			'#f1f2f4' => '#e1e5e9',
		);
	}

	const INDEX_PLACEHOLDER = 'lstabp-new';

	const DEFAULT_STYLE = '#fbd5d5';

	public static function styles() {
		$styles = array();

		foreach ( self::palette() as $hex => $label ) {
			$styles[ $hex ] = array(
				'label' => $label,
				'css'   => self::css_for( $hex ),
			);
		}

		foreach ( self::effects() as $key => $effect ) {
			$styles[ $key ] = array(
				'label' => $effect['label'],
				'css'   => $effect['css'],
			);
		}

		return $styles;
	}

	public static function css_for( $style, $scope = 'cell' ) {
		$effects = self::effects();

		if ( isset( $effects[ $style ] ) ) {
			return $effects[ $style ]['css'];
		}

		$hex = self::hex( $style );

		if ( '' === $hex ) {
			$hex = self::DEFAULT_STYLE;
		}

		if ( 'text' === $scope ) {
			return 'color:' . $hex . ';';
		}

		if ( 'pill' === $scope || 'dot' === $scope ) {
			if ( 'dot' === $scope ) {
				return '--lstabp-dot:' . $hex . ';';
			}

			return '--lstabp-pill-line:' . $hex . ';'
				. '--lstabp-pill-fill:color-mix(in srgb,' . $hex . ' 18%,transparent);'
				. '--lstabp-pill-ink:color-mix(in srgb,' . $hex . ' 35%,currentColor);';
		}

		$ink   = self::ink( $hex );
		$paint = 'background-color:' . $hex . ';color:' . $ink . ';';

		$paint .= '--lstab-fg-faint:color-mix(in srgb,' . $ink . ' 78%,' . $hex . ');';

		$paint .= '--lstabp-pill-ink-set:' . $ink . ';';

		$paint .= '--lstabp-pill-fill-set:transparent;';

		if ( 'row' === $scope ) {
			$paint .= '--lstab-row-tint:' . $hex . ';';
		}

		return $paint;
	}

	public static function hex( $raw ) {
		$raw = strtolower( trim( (string) $raw ) );

		if ( preg_match( '~^#([0-9a-f]{3})$~', $raw, $short ) ) {
			$raw = '#' . $short[1][0] . $short[1][0] . $short[1][1] . $short[1][1] . $short[1][2] . $short[1][2];
		}

		return preg_match( '~^#[0-9a-f]{6}$~', $raw ) ? $raw : '';
	}

	public static function ink( $hex ) {
		$hex = self::hex( $hex );

		if ( '' === $hex ) {
			return '#1d2327';
		}

		$candidates = array( self::deepen( $hex, 0.26 ), self::deepen( $hex, 0.15 ), '#1d2327', '#ffffff', '#000000' );
		$best       = '#1d2327';
		$best_ratio = 0.0;

		foreach ( $candidates as $candidate ) {
			$ratio = self::contrast( $hex, $candidate );

			if ( $ratio >= 4.5 ) {
				return $candidate;
			}

			if ( $ratio > $best_ratio ) {
				$best       = $candidate;
				$best_ratio = $ratio;
			}
		}

		return $best;
	}

	protected static function deepen( $hex, $light = 0.26 ) {
		$red   = hexdec( substr( $hex, 1, 2 ) ) / 255;
		$green = hexdec( substr( $hex, 3, 2 ) ) / 255;
		$blue  = hexdec( substr( $hex, 5, 2 ) ) / 255;

		$max   = max( $red, $green, $blue );
		$min   = min( $red, $green, $blue );
		$own   = ( $max + $min ) / 2;
		$span  = $max - $min;

		$saturation = 0.0;

		if ( $span > 0 ) {
			$saturation = $own > 0.5 ? $span / ( 2 - $max - $min ) : $span / ( $max + $min );
		}

		if ( $saturation < 0.2 ) {
			return $light > 0.2 ? '#3f4249' : '#1d2327';
		}

		if ( $max === $red ) {
			$hue = ( $green - $blue ) / $span + ( $green < $blue ? 6 : 0 );
		} elseif ( $max === $green ) {
			$hue = ( $blue - $red ) / $span + 2;
		} else {
			$hue = ( $red - $green ) / $span + 4;
		}

		$hue /= 6;

		return self::from_hsl( $hue, min( 0.75, max( 0.42, $saturation * 1.8 ) ), $light );
	}

	protected static function contrast( $one, $two ) {
		$first  = self::luminance( $one );
		$second = self::luminance( $two );

		return ( max( $first, $second ) + 0.05 ) / ( min( $first, $second ) + 0.05 );
	}

	protected static function luminance( $hex ) {
		$weights = array( 0.2126, 0.7152, 0.0722 );
		$total   = 0.0;

		foreach ( array( 1, 3, 5 ) as $index => $offset ) {
			$channel = hexdec( substr( $hex, $offset, 2 ) ) / 255;
			$channel = $channel <= 0.03928 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 );
			$total  += $weights[ $index ] * $channel;
		}

		return $total;
	}

	protected static function from_hsl( $hue, $saturation, $light ) {
		$high = $light < 0.5 ? $light * ( 1 + $saturation ) : $light + $saturation - $light * $saturation;
		$low  = 2 * $light - $high;

		$channel = static function ( $shift ) use ( $high, $low ) {
			$shift = fmod( $shift + 1, 1 );

			if ( $shift < 1 / 6 ) {
				$value = $low + ( $high - $low ) * 6 * $shift;
			} elseif ( $shift < 1 / 2 ) {
				$value = $high;
			} elseif ( $shift < 2 / 3 ) {
				$value = $low + ( $high - $low ) * ( 2 / 3 - $shift ) * 6;
			} else {
				$value = $low;
			}

			return str_pad( dechex( (int) round( $value * 255 ) ), 2, '0', STR_PAD_LEFT );
		};

		return '#' . $channel( $hue + 1 / 3 ) . $channel( $hue ) . $channel( $hue - 1 / 3 );
	}

	public static function sanitize_style( $style, $custom = '' ) {
		$style  = is_scalar( $style ) ? strtolower( trim( (string) $style ) ) : '';
		$legacy = self::legacy();

		if ( isset( $legacy[ $style ] ) ) {
			return $legacy[ $style ];
		}

		if ( isset( self::effects()[ $style ] ) ) {
			return $style;
		}

		if ( 'custom' === $style ) {
			$style = self::hex( $custom );

			return '' === $style ? self::DEFAULT_STYLE : $style;
		}

		$hex = self::hex( $style );

		return '' === $hex ? self::DEFAULT_STYLE : $hex;
	}

	public static function operators() {
		return array(
			'='  => __( 'is', 'live-sheets-table-pro' ),
			'!=' => __( 'is not', 'live-sheets-table-pro' ),
			'*=' => __( 'contains', 'live-sheets-table-pro' ),
			'>'  => __( 'is greater than', 'live-sheets-table-pro' ),
			'>=' => __( 'is at least', 'live-sheets-table-pro' ),
			'<'  => __( 'is less than', 'live-sheets-table-pro' ),
			'<=' => __( 'is at most', 'live-sheets-table-pro' ),
		);
	}

	public static function all() {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	public static function for_source( $source_id ) {
		$key = (int) $source_id;

		if ( isset( self::$previewing[ $key ] ) ) {
			return self::$previewing[ $key ];
		}

		$all = self::all();

		return isset( $all[ $key ] ) ? self::sanitize( $all[ $key ] ) : array();
	}

	public function preview_request( $request, $source_id ) {
		$rules = $request->get_param( 'rules' );

		if ( ! is_array( $rules ) ) {
			return;
		}

		self::$previewing[ (int) $source_id ] = self::sanitize( $rules );
	}

	public static function sanitize( $raw ) {
		$operators = self::operators();
		$clean     = array();

		foreach ( (array) $raw as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$column = isset( $rule['column'] ) ? sanitize_text_field( (string) $rule['column'] ) : '';

			if ( '' === $column ) {
				continue;
			}

			$operator = isset( $rule['operator'] ) ? (string) $rule['operator'] : '=';

			$clean[] = array(
				'column'   => $column,
				'operator' => isset( $operators[ $operator ] ) ? $operator : '=',
				'value'    => isset( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '',
				'style'    => self::sanitize_style(
					isset( $rule['style'] ) ? $rule['style'] : '',
					isset( $rule['custom'] ) ? $rule['custom'] : ''
				),
				'scope'    => ( isset( $rule['scope'] ) && in_array( $rule['scope'], array( 'row', 'text', 'pill', 'dot' ), true ) )
					? $rule['scope']
					: 'cell',
			);

			if ( count( $clean ) >= self::MAX_RULES ) {
				break;
			}
		}

		return $clean;
	}

	public function capture( $rows, $headers, $source, $args ) {
		$this->cells  = array();
		$this->shapes = array();
		$this->rows   = array();
		$this->places = array();

		$rules = self::for_source( isset( $source['id'] ) ? $source['id'] : 0 );

		if ( ! $rules ) {
			return $rows;
		}

		$headers  = array_values( (array) $headers );
		$columns  = LSTABP_Filters::column_map( $headers, $source );
		$rendered = self::rendered_positions( $headers, $source, $args );
		$ranks    = self::ranks( $rules, $headers, $source );

		foreach ( array_values( (array) $rows ) as $row_index => $row ) {
			foreach ( $ranks as $position => $order ) {
				$value = isset( $row[ $position ] ) ? self::key( (string) $row[ $position ] ) : '';

				if ( isset( $rendered[ $position ], $order[ $value ] ) ) {
					$this->places[ $row_index ][ $rendered[ $position ] ] = $order[ $value ];
				}
			}

			foreach ( $rules as $rule ) {
				$key = self::key( $rule['column'] );

				if ( ! isset( $columns[ $key ] ) ) {
					continue;
				}

				$position = $columns[ $key ];
				$cell     = isset( $row[ $position ] ) ? (string) $row[ $position ] : '';

				if ( ! LSTABP_Filters::compare( $cell, $rule['operator'], $rule['value'] ) ) {
					continue;
				}

				$css = self::css_for( $rule['style'], $rule['scope'] );

				if ( 'row' === $rule['scope'] ) {
					$this->rows[ $row_index ] = $css;
					continue;
				}

				if ( isset( $rendered[ $position ] ) ) {
					$this->cells[ $row_index ][ $rendered[ $position ] ] = $css;

					if ( 'pill' === $rule['scope'] || 'dot' === $rule['scope'] ) {
						$this->shapes[ $row_index ][ $rendered[ $position ] ] = $rule['scope'];
					}
				}
			}
		}

		return $rows;
	}

	public function detail_attributes( $attributes, $row_index ) {
		if ( ! isset( $this->rows[ $row_index ] ) ) {
			return $attributes;
		}

		$attributes['class'] = trim( ( isset( $attributes['class'] ) ? $attributes['class'] . ' ' : '' ) . 'lstab-ruled' );
		$attributes['style'] = ( isset( $attributes['style'] ) ? $attributes['style'] : '' ) . $this->rows[ $row_index ];

		return $attributes;
	}

	public function attributes( $attributes, $value, $col_index, $row_index, $source ) {
		if ( isset( $this->places[ $row_index ][ $col_index ] ) ) {
			$attributes['data-lstab-rank'] = (string) $this->places[ $row_index ][ $col_index ];
		}

		$css = '';

		if ( isset( $this->rows[ $row_index ] ) ) {
			$css .= $this->rows[ $row_index ];
		}

		if ( isset( $this->cells[ $row_index ][ $col_index ] ) ) {
			$css .= $this->cells[ $row_index ][ $col_index ];
		}

		if ( '' === $css ) {
			return $attributes;
		}

		$lstabp_classes = isset( $attributes['class'] ) ? $attributes['class'] . ' ' : '';

		$lstabp_classes .= 'lstab-ruled';

		if ( isset( $this->shapes[ $row_index ][ $col_index ] ) ) {
			$lstabp_classes .= ' lstabp-' . $this->shapes[ $row_index ][ $col_index ];
		}

		$attributes['class'] = trim( $lstabp_classes );
		$attributes['style'] = isset( $attributes['style'] ) ? $attributes['style'] . $css : $css;

		return $attributes;
	}

	public static function ranks( $rules, $headers, $source ) {
		$columns = LSTABP_Filters::column_map( array_values( (array) $headers ), $source );
		$named   = array();

		foreach ( (array) $rules as $rule ) {
			$value = self::key( (string) $rule['value'] );
			$key   = self::key( (string) $rule['column'] );

			if ( '=' !== $rule['operator'] || 'row' === $rule['scope'] || '' === $value || ! isset( $columns[ $key ] ) ) {
				continue;
			}

			$position = $columns[ $key ];

			if ( ! isset( $named[ $position ] ) ) {
				$named[ $position ] = array();
			}

			if ( ! in_array( $value, $named[ $position ], true ) ) {
				$named[ $position ][] = $value;
			}
		}

		$ranks = array();

		foreach ( $named as $position => $values ) {
			if ( count( $values ) >= 2 ) {
				$ranks[ $position ] = array_flip( $values );
			}
		}

		return $ranks;
	}

	public function sort_ranks( $ranks, $headers, $source ) {
		$rules = self::for_source( isset( $source['id'] ) ? $source['id'] : 0 );

		return (array) $ranks + self::ranks( $rules, $headers, $source );
	}

	public static function rendered_positions( $headers, $source, $args ) {
		$config = ( isset( $args['columns'] ) && null !== $args['columns'] )
			? $args['columns']
			: ( isset( $source['columns_config'] ) ? $source['columns_config'] : array() );

		return array_flip( LSTAB_Columns::kept( $headers, (array) $config ) );
	}

	public static function key( $name ) {
		return function_exists( 'mb_strtolower' )
			? mb_strtolower( trim( $name ), 'UTF-8' )
			: strtolower( trim( $name ) );
	}

	public function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, LSTAB_Admin::EDIT_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'lstabp-admin',
			LSTABP_URL . 'assets/css/lstabp-admin.css',
			array(),
			LSTABP_VERSION
		);

		wp_enqueue_script(
			'lstabp-admin',
			LSTABP_URL . 'assets/js/lstabp-admin.js',
			array(),
			LSTABP_VERSION,
			true
		);

		$swatches = array();
		foreach ( self::styles() as $key => $style ) {
			$swatches[ $key ] = $style['css'];
		}

		wp_localize_script(
			'lstabp-admin',
			'lstabpRules',
			array(
				'styles'   => $swatches,
				'maxRules' => self::MAX_RULES,
			) + self::fold_words()
		);
	}

	public static function fold_words() {
		return array(
			/* translators: 1: how many more are about to be shown, 2: how many are still hidden. */
			'foldMore' => __( 'Show %1$s more — %2$s still hidden', 'live-sheets-table-pro' ),
			'foldAll'  => __( 'Show them all', 'live-sheets-table-pro' ),
		);
	}

	public function render_pane_card( $pane, $source, $is_edit ) {
		if ( 'look' !== $pane ) {
			return;
		}

		$this->render_card( $source, $is_edit );
	}

	public function render_card( $source, $is_edit ) {
		$rules   = ( $is_edit && $source ) ? self::for_source( $source['id'] ) : array();
		$headers = ( $is_edit && $source && ! empty( $source['data']['headers'] ) )
			? array_values( (array) $source['data']['headers'] )
			: array();

		require LSTABP_PATH . 'includes/views/rules-card.php';
	}

	public function save( $source_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['_lstabp_rules_present'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Sanitised field by field below.
		$raw = isset( $_POST['lstabp_rules'] ) ? wp_unslash( $_POST['lstabp_rules'] ) : array();

		$all                     = self::all();
		$all[ (int) $source_id ] = self::sanitize( $raw );

		if ( ! $all[ (int) $source_id ] ) {
			unset( $all[ (int) $source_id ] );
		}

		update_option( self::OPTION, $all, false );
	}

	public function forget( $source_id ) {
		$all = self::all();

		if ( ! isset( $all[ (int) $source_id ] ) ) {
			return;
		}

		unset( $all[ (int) $source_id ] );
		update_option( self::OPTION, $all, false );
	}
}
