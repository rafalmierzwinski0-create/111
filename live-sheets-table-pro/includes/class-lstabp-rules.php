<?php
/**
 * Conditional formatting: colour a cell by what is in it.
 *
 * "In stock" green and "Sold out" red is the whole idea. A rule names a column,
 * a comparison and a value, and picks a colour off a small classic palette or
 * out of the browser's own colour picker.
 *
 * Contrast is still not left to chance: only the background is ever chosen, and
 * the text colour is worked out from it — dark on a pale colour, white on a
 * strong one — so a rule cannot produce yellow on white however hard it tries.
 *
 * Rules are evaluated on the server while the table is rendered. There is no
 * JavaScript involved and the colours are in the HTML that reaches the visitor,
 * which is the same promise the rest of the plugin makes.
 *
 * @package LiveSheetsTablePro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Conditional formatting rules.
 */
class LSTABP_Rules {

	/**
	 * Where the rules live, keyed by source ID.
	 */
	const OPTION = 'lstabp_rules';

	/**
	 * Rules handed over for one preview request, by source.
	 *
	 * @var array<int,array<int,array<string,mixed>>>
	 */
	protected static $previewing = array();

	/**
	 * How many rules one source may hold.
	 *
	 * Past a certain point a table is not formatted, it is decorated, and every
	 * rule costs a comparison per cell.
	 */
	const MAX_RULES = 20;

	/**
	 * What each rendered table found, keyed by row and column position.
	 *
	 * @var array<int,array<int,string>>
	 */
	protected $cells = array();

	/**
	 * What each rendered row should look like, keyed by row position.
	 *
	 * @var array<int,string>
	 */
	protected $rows = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		// After the row filter at priority 10, so rules are only evaluated for
		// rows that survive, and before column settings, so a rule can read a
		// column the table hides. Anything that drops or reorders rows has to
		// run before this.
		add_filter( 'lstab_source_rows', array( $this, 'capture' ), 20, 4 );
		add_filter( 'lstab_cell_attributes', array( $this, 'attributes' ), 10, 5 );
		// A drawer belongs to the row above it, so a rule that painted the row
		// paints the panel it opens too.
		add_filter( 'lstab_detail_attributes', array( $this, 'detail_attributes' ), 10, 2 );

		// Rules being typed, so the preview shows them before anything is saved.
		add_action( 'lstab_preview_request', array( $this, 'preview_request' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		/*
		 * On the Appearance tab, where a colour belongs. It used to print under
		 * "Columns and rows" — the only pane the free plugin offered an add-on —
		 * so a card about colour lived under a heading about which columns to
		 * keep, and read as if it were about hiding things.
		 */
		add_action( 'lstab_edit_pane_cards', array( $this, 'render_pane_card' ), 10, 3 );
		add_action( 'lstab_source_saved', array( $this, 'save' ) );
		add_action( 'lstab_source_deleted', array( $this, 'forget' ) );
	}

	/**
	 * The classic colours offered as ready-made choices.
	 *
	 * Nine, pale, one per hue. A palette this size is a decision somebody can
	 * make in a second; a full picker in its place is a decision nobody makes
	 * well, which is why the picker is beside it rather than instead of it.
	 *
	 * @return array<string,string> Hex colour to its name.
	 */
	public static function palette() {
		/*
		 * A notch deeper than the first set, which was pale enough that a
		 * coloured cell read as a printing artefact rather than a decision.
		 * Still light: the ink is chosen against whichever of these is picked,
		 * so the value stays readable, and a table is not a highlighter pen.
		 */
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

	/**
	 * The two looks that are not a colour at all.
	 *
	 * @return array<string,array<string,string>>
	 */
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

	/**
	 * What the five named colours of the first version meant.
	 *
	 * Rules saved then hold a word rather than a colour, and there is no upgrade
	 * step to run: the word is translated to its colour every time it is read.
	 *
	 * @return array<string,string>
	 */
	protected static function legacy() {
		return array(
			'red'   => '#fbd5d5',
			'amber' => '#fbdcbc',
			'green' => '#cfebd9',
			'blue'  => '#d0e1f8',
			'grey'  => '#e1e5e9',

			/*
			 * The palette's own first set, which was pale enough that a
			 * coloured cell read as a printing artefact. A rule saved then
			 * still holds one of these; without this it would come back as a
			 * colour of its own, off the palette, which is not what anybody
			 * chose. The colour a page shows barely moves.
			 */
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

	/**
	 * The colour a rule falls back to.
	 *
	 * The palette's first entry: a rule with no colour yet is drawn on the
	 * first swatch, so the row of chips shows one of its own selected rather
	 * than the wheel at the end, which would say "a colour of your own" about
	 * a choice nobody has made.
	 */
	const DEFAULT_STYLE = '#fbd5d5';

	/**
	 * Every look that can be chosen, named and drawn.
	 *
	 * Kept in the shape the first version used — a key, a label and a block of
	 * CSS — so everything that reads it did not have to change.
	 *
	 * @return array<string,array<string,string>>
	 */
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

	/**
	 * The CSS one look is made of.
	 *
	 * @param string $style A hex colour, or the key of an effect.
	 * @return string Declarations, ending in a semicolon.
	 */
	public static function css_for( $style ) {
		$effects = self::effects();

		if ( isset( $effects[ $style ] ) ) {
			return $effects[ $style ]['css'];
		}

		$hex = self::hex( $style );

		if ( '' === $hex ) {
			$hex = self::DEFAULT_STYLE;
		}

		return 'background-color:' . $hex . ';color:' . self::ink( $hex ) . ';';
	}

	/**
	 * A colour, or nothing if it is not one.
	 *
	 * @param mixed $raw Candidate colour.
	 * @return string '#rrggbb', or ''.
	 */
	public static function hex( $raw ) {
		$raw = strtolower( trim( (string) $raw ) );

		if ( preg_match( '~^#([0-9a-f]{3})$~', $raw, $short ) ) {
			$raw = '#' . $short[1][0] . $short[1][0] . $short[1][1] . $short[1][1] . $short[1][2] . $short[1][2];
		}

		return preg_match( '~^#[0-9a-f]{6}$~', $raw ) ? $raw : '';
	}

	/**
	 * Text that can be read on a given background.
	 *
	 * Two candidates are drawn up and the one with the better contrast wins.
	 * The first is the colour's own hue darkened almost to ink, which is what
	 * makes a red cell look designed rather than merely coloured; the second is
	 * plain white, for a colour too strong to carry any shade of itself.
	 *
	 * Picking by measurement rather than by a brightness threshold matters for
	 * exactly the colours somebody is most likely to reach for out of the
	 * picker: a vivid green reads as dark to the usual weighting and as bright
	 * to the eye, and got white text nobody could read.
	 *
	 * @param string $hex Background colour.
	 * @return string Text colour.
	 */
	public static function ink( $hex ) {
		$hex = self::hex( $hex );

		if ( '' === $hex ) {
			return '#1d2327';
		}

		/*
		 * In the order they would be chosen by hand: the colour's own hue, then
		 * the same hue deeper, then the admin's own ink, then white for a
		 * background dark enough that nothing else will do, and pure black for
		 * the light ones where even the ink is a shade too soft. The first that
		 * clears the readability bar wins; if a colour is awkward enough that
		 * none of them does — a mid-tone olive is the classic — the best of the
		 * five stands.
		 */
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

	/**
	 * The same colour, taken down to nearly ink.
	 *
	 * @param string $hex   Background colour.
	 * @param float  $light How dark to take it, 0 to 1.
	 * @return string
	 */
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

		/*
		 * Grey has no hue worth keeping, and a grey with a trace of one is
		 * worse than none: deepening #f1f2f4 by its hue turned the text navy,
		 * because the little blue in it is all there is to amplify.
		 */
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

		// Deep enough to read on the palest tint, and given back some of the
		// colour a pale tint has almost none of.
		return self::from_hsl( $hue, min( 0.75, max( 0.42, $saturation * 1.8 ) ), $light );
	}

	/**
	 * How far apart two colours are, as the accessibility guidelines count it.
	 *
	 * @param string $one First colour.
	 * @param string $two Second colour.
	 * @return float Contrast ratio, 1 to 21.
	 */
	protected static function contrast( $one, $two ) {
		$first  = self::luminance( $one );
		$second = self::luminance( $two );

		return ( max( $first, $second ) + 0.05 ) / ( min( $first, $second ) + 0.05 );
	}

	/**
	 * How much light a colour puts out, by the sRGB definition.
	 *
	 * @param string $hex Colour.
	 * @return float 0 to 1.
	 */
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

	/**
	 * Hue, saturation and lightness back to a hex colour.
	 *
	 * @param float $hue        0-1.
	 * @param float $saturation 0-1.
	 * @param float $light      0-1.
	 * @return string '#rrggbb'.
	 */
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

	/**
	 * One rule's chosen look, whatever shape it arrived in.
	 *
	 * @param mixed $style  The chosen look: a hex colour, an effect, an old
	 *                      colour name, or the word 'custom'.
	 * @param mixed $custom The colour picker's value, read when 'custom'.
	 * @return string A hex colour or an effect key.
	 */
	public static function sanitize_style( $style, $custom = '' ) {
		$style  = is_scalar( $style ) ? strtolower( trim( (string) $style ) ) : '';
		$legacy = self::legacy();

		if ( isset( $legacy[ $style ] ) ) {
			return $legacy[ $style ];
		}

		if ( isset( self::effects()[ $style ] ) ) {
			return $style;
		}

		/*
		 * The palette is a set of radio buttons and the picker is a field of
		 * its own, so that choosing a colour of your own still works with
		 * JavaScript switched off: the radio says "custom", and the colour
		 * itself arrives in the other field.
		 */
		if ( 'custom' === $style ) {
			$style = self::hex( $custom );

			return '' === $style ? self::DEFAULT_STYLE : $style;
		}

		$hex = self::hex( $style );

		return '' === $hex ? self::DEFAULT_STYLE : $hex;
	}

	/**
	 * Comparisons a rule may make, in the words the filter syntax uses.
	 *
	 * @return array<string,string> Operator to its label.
	 */
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

	/**
	 * Every stored rule set.
	 *
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Rules for one source.
	 *
	 * @param int $source_id Source ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function for_source( $source_id ) {
		$key = (int) $source_id;

		/*
		 * A preview being drawn while somebody types has rules that exist only
		 * in the form. They are handed over for the length of that one request
		 * and stand in for the stored set.
		 */
		if ( isset( self::$previewing[ $key ] ) ) {
			return self::$previewing[ $key ];
		}

		$all = self::all();

		return isset( $all[ $key ] ) ? self::sanitize( $all[ $key ] ) : array();
	}

	/**
	 * Take the rules being typed out of a preview request.
	 *
	 * @param WP_REST_Request $request   The request.
	 * @param int             $source_id Source being previewed.
	 * @return void
	 */
	public function preview_request( $request, $source_id ) {
		$rules = $request->get_param( 'rules' );

		if ( ! is_array( $rules ) ) {
			return;
		}

		self::$previewing[ (int) $source_id ] = self::sanitize( $rules );
	}

	/**
	 * Clean a submitted or stored rule set.
	 *
	 * A rule with no column named is an empty form row, not a rule.
	 *
	 * @param mixed $raw Raw rules.
	 * @return array<int,array<string,mixed>>
	 */
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
				'scope'    => ( isset( $rule['scope'] ) && 'row' === $rule['scope'] ) ? 'row' : 'cell',
			);

			if ( count( $clean ) >= self::MAX_RULES ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * Work out what each row and cell should look like.
	 *
	 * Done once per table rather than per cell: a rule set is compared against
	 * every row here, and the cell filter then only reads the answer.
	 *
	 * @param array<int,array<int,string>> $rows    Body rows.
	 * @param array<int,string>            $headers Sheet headings.
	 * @param array<string,mixed>          $source  Source row.
	 * @param array<string,mixed>          $args    Rendering options.
	 * @return array<int,array<int,string>>
	 */
	public function capture( $rows, $headers, $source, $args ) {
		$this->cells = array();
		$this->rows  = array();

		$rules = self::for_source( isset( $source['id'] ) ? $source['id'] : 0 );

		if ( ! $rules ) {
			return $rows;
		}

		$headers  = array_values( (array) $headers );
		$columns  = LSTABP_Filters::column_map( $headers, $source );
		$rendered = self::rendered_positions( $headers, $source, $args );

		foreach ( array_values( (array) $rows ) as $row_index => $row ) {
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

				$css = self::css_for( $rule['style'] );

				if ( 'row' === $rule['scope'] ) {
					$this->rows[ $row_index ] = $css;
					continue;
				}

				// A rule on a hidden column can still colour the row, but it
				// has no cell of its own to colour.
				if ( isset( $rendered[ $position ] ) ) {
					$this->cells[ $row_index ][ $rendered[ $position ] ] = $css;
				}
			}
		}

		return $rows;
	}

	/**
	 * Paint the drawer under a row the same colour as the row.
	 *
	 * @param array<string,string> $attributes Attribute map.
	 * @param int                  $row_index  Row the drawer belongs to.
	 * @return array<string,string>
	 */
	public function detail_attributes( $attributes, $row_index ) {
		if ( ! isset( $this->rows[ $row_index ] ) ) {
			return $attributes;
		}

		$attributes['class'] = trim( ( isset( $attributes['class'] ) ? $attributes['class'] . ' ' : '' ) . 'lstab-ruled' );
		$attributes['style'] = ( isset( $attributes['style'] ) ? $attributes['style'] : '' ) . $this->rows[ $row_index ];

		return $attributes;
	}

	/**
	 * Add the style to a cell that a rule picked out.
	 *
	 * @param array<string,string> $attributes Attribute map.
	 * @param string               $value      Cell value.
	 * @param int                  $col_index  Column index in the rendered table.
	 * @param int                  $row_index  Row index.
	 * @param array<string,mixed>  $source     Source row.
	 * @return array<string,string>
	 */
	public function attributes( $attributes, $value, $col_index, $row_index, $source ) {
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

		$attributes['class'] = trim( ( isset( $attributes['class'] ) ? $attributes['class'] . ' ' : '' ) . 'lstab-ruled' );
		$attributes['style'] = isset( $attributes['style'] ) ? $attributes['style'] . $css : $css;

		return $attributes;
	}

	/**
	 * Map a sheet column position to its position in the rendered table.
	 *
	 * Hidden columns are gone by the time cells are written, so the positions
	 * a rule works with have to be translated to match.
	 *
	 * @param array<int,string>   $headers Sheet headings.
	 * @param array<string,mixed> $source  Source row.
	 * @param array<string,mixed> $args    Rendering options.
	 * @return array<int,int>
	 */
	protected static function rendered_positions( $headers, $source, $args ) {
		$config = ( isset( $args['columns'] ) && null !== $args['columns'] )
			? $args['columns']
			: ( isset( $source['columns_config'] ) ? $source['columns_config'] : array() );

		$config = (array) $config;
		$map    = array();
		$shown  = 0;

		foreach ( $headers as $index => $heading ) {
			if ( $config && ! empty( $config[ $index ]['hidden'] ) ) {
				continue;
			}

			$map[ $index ] = $shown;
			$shown++;
		}

		return $map;
	}

	/**
	 * Normalise a column name for comparison.
	 *
	 * @param string $name Column name.
	 * @return string
	 */
	protected static function key( $name ) {
		return function_exists( 'mb_strtolower' )
			? mb_strtolower( trim( $name ), 'UTF-8' )
			: strtolower( trim( $name ) );
	}

	/**
	 * Style the card. Everything else on the screen is the free plugin's.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
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
				// Only the ready-made looks. A colour of somebody's own is
				// worked out in the browser, by the same reasoning as ink().
				'styles' => $swatches,
			)
		);
	}

	/**
	 * Print the rules card on the source screen.
	 *
	 * @param array<string,mixed>|null $source  Source row, or null while adding.
	 * @param bool                     $is_edit Whether an existing source is being edited.
	 * @return void
	 */
	public function render_pane_card( $pane, $source, $is_edit ) {
		if ( 'look' !== $pane ) {
			return;
		}

		$this->render_card( $source, $is_edit );
	}

	/**
	 * Print the rules card.
	 *
	 * @param array<string,mixed>|null $source  Source row.
	 * @param bool                     $is_edit Editing an existing source.
	 * @return void
	 */
	public function render_card( $source, $is_edit ) {
		$rules   = ( $is_edit && $source ) ? self::for_source( $source['id'] ) : array();
		$headers = ( $is_edit && $source && ! empty( $source['data']['headers'] ) )
			? array_values( (array) $source['data']['headers'] )
			: array();

		require LSTABP_PATH . 'includes/views/rules-card.php';
	}

	/**
	 * Store the rules submitted with a source.
	 *
	 * Only reached from the free plugin's save handler, which has already
	 * checked the nonce and the capability.
	 *
	 * @param int $source_id Source ID.
	 * @return void
	 */
	public function save( $source_id ) {
		// A screen without the card must not wipe rules it never showed.
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

	/**
	 * Drop a deleted source's rules.
	 *
	 * @param int $source_id Source ID.
	 * @return void
	 */
	public function forget( $source_id ) {
		$all = self::all();

		if ( ! isset( $all[ (int) $source_id ] ) ) {
			return;
		}

		unset( $all[ (int) $source_id ] );
		update_option( self::OPTION, $all, false );
	}
}
