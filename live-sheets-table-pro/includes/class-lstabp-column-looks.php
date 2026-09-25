<?php
/**
 * What a whole column looks like, beyond its colours.
 *
 * A colour rule answers "this value is special". This answers a different
 * question: "this column is a measurement", or "this column is a way through
 * to somewhere". Both are about the column as a whole, so neither belongs in a
 * rule — there is no condition to write, and writing one per row would be a
 * chore that says nothing.
 *
 * Two looks, for now:
 *
 *   bar     a bar behind the number, as long as the number is large. A column
 *           of figures becomes a chart without stopping being a column of
 *           figures — the value stays on top, and stays text.
 *   button  a cell holding a link becomes a button, in colours of your own.
 *
 * @package LiveSheetsTablePro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Per-column presentation.
 */
class LSTABP_Column_Looks {

	/**
	 * Where the choices live.
	 */
	const OPTION = 'lstabp_column_looks';

	/**
	 * How long a button's own label may be.
	 */
	const MAX_LABEL = 40;

	/**
	 * Looks being chosen right now, for the length of one preview request.
	 *
	 * @var array<int,array<string,array<string,string>>>
	 */
	protected static $previewing = array();

	/**
	 * Bar share per rendered cell, 0 to 1.
	 *
	 * @var array<int,array<int,float>>
	 */
	protected $bars = array();

	/**
	 * Rendered column positions wearing each look.
	 *
	 * @var array<int,string>
	 */
	protected $columns = array();

	/**
	 * The look each rendered column wears, with its colours.
	 *
	 * @var array<int,array<string,string>>
	 */
	protected $settings = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		/*
		 * After the colour rules at 20: a rule may colour a row this column
		 * sits in, and the bar is drawn on top of whatever the row is wearing.
		 * The rows themselves are the same either way — nothing here drops or
		 * reorders one.
		 */
		add_filter( 'lstab_source_rows', array( $this, 'capture' ), 25, 4 );
		add_filter( 'lstab_cell_attributes', array( $this, 'attributes' ), 12, 5 );

		// After the free plugin's linkifier at 5, which would otherwise have
		// the last word about what a cell holding an address looks like.
		add_filter( 'lstab_render_cell', array( $this, 'render_cell' ), 8, 5 );

		// A look being chosen exists only in the form until it is saved, so the
		// preview is handed it directly — otherwise the only way to see a bar
		// would be to save and go and look, which is the round trip a preview
		// is for avoiding.
		add_action( 'lstab_preview_request', array( $this, 'preview_request' ), 10, 2 );

		add_action( 'lstab_edit_pane_cards', array( $this, 'render_pane_card' ), 20, 3 );
		add_action( 'lstab_source_saved', array( $this, 'save' ) );
		add_action( 'lstab_source_deleted', array( $this, 'forget' ) );
	}

	/**
	 * Every stored choice.
	 *
	 * @return array<int,array<string,array<string,string>>>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * What one source asked for, by heading.
	 *
	 * @param int $source_id Source ID.
	 * @return array<string,array<string,string>>
	 */
	public static function for_source( $source_id ) {
		$key = (int) $source_id;

		if ( isset( self::$previewing[ $key ] ) ) {
			return self::$previewing[ $key ];
		}

		$all = self::all();

		return isset( $all[ $key ] ) ? self::sanitize( (array) $all[ $key ] ) : array();
	}

	/**
	 * Hand the preview what is being chosen right now.
	 *
	 * @param WP_REST_Request $request   The preview request.
	 * @param int             $source_id Source being previewed.
	 * @return void
	 */
	public function preview_request( $request, $source_id ) {
		$looks = $request->get_param( 'looks' );

		if ( ! is_array( $looks ) ) {
			return;
		}

		self::$previewing[ (int) $source_id ] = self::sanitize( $looks );
	}

	/**
	 * Clean a submitted or stored set of looks.
	 *
	 * Used by the save and by the preview alike, so what somebody sees while
	 * choosing is what they get once they have saved.
	 *
	 * @param array<string,mixed> $raw Raw looks, keyed by heading.
	 * @return array<string,array<string,string>>
	 */
	public static function sanitize( $raw ) {
		$looks = self::looks();
		$clean = array();

		foreach ( (array) $raw as $heading => $setting ) {
			$heading = sanitize_text_field( (string) $heading );
			$setting = (array) $setting;
			$look    = isset( $setting['look'] ) ? (string) $setting['look'] : '';

			if ( '' === $heading || ! isset( $looks[ $look ] ) ) {
				continue;
			}

			$clean[ $heading ] = array(
				'look'  => $look,
				'tint'  => LSTABP_Rules::hex( isset( $setting['tint'] ) ? $setting['tint'] : '' ),
				'ink'   => LSTABP_Rules::hex( isset( $setting['ink'] ) ? $setting['ink'] : '' ),
				'label' => mb_substr(
					sanitize_text_field( isset( $setting['label'] ) ? (string) $setting['label'] : '' ),
					0,
					self::MAX_LABEL
				),
			);
		}

		return $clean;
	}

	/**
	 * The looks a column may wear.
	 *
	 * @return array<string,string>
	 */
	public static function looks() {
		return array(
			'bar'    => __( 'A bar behind the number', 'live-sheets-table-pro' ),
			'button' => __( 'A button, if the cell holds a link', 'live-sheets-table-pro' ),
		);
	}

	/**
	 * Work out each bar's length before the table is drawn.
	 *
	 * @param array<int,array<int,string>> $rows    Rows.
	 * @param array<int,string>            $headers Sheet headings.
	 * @param array<string,mixed>          $source  Source row.
	 * @param array<string,mixed>          $args    Rendering options.
	 * @return array<int,array<int,string>>
	 */
	public function capture( $rows, $headers, $source, $args ) {
		$this->bars     = array();
		$this->columns  = array();
		$this->settings = array();

		$chosen = self::for_source( isset( $source['id'] ) ? $source['id'] : 0 );

		if ( ! $chosen ) {
			return $rows;
		}

		$headers  = array_values( (array) $headers );
		$map      = LSTABP_Filters::column_map( $headers, $source );
		$rendered = LSTABP_Rules::rendered_positions( $headers, $source, $args );
		$rows     = array_values( (array) $rows );

		foreach ( $chosen as $heading => $setting ) {
			$key = LSTABP_Rules::key( (string) $heading );

			if ( ! isset( $map[ $key ] ) ) {
				continue;
			}

			$position = $map[ $key ];

			// A hidden column has no cell to dress.
			if ( ! isset( $rendered[ $position ] ) ) {
				continue;
			}

			$at                    = $rendered[ $position ];
			$this->columns[ $at ]  = $setting['look'];
			$this->settings[ $at ] = $setting;

			if ( 'bar' !== $setting['look'] ) {
				continue;
			}

			$this->measure( $rows, $position, $at );
		}

		return $rows;
	}

	/**
	 * Turn one column of numbers into shares of its own largest.
	 *
	 * The baseline is zero whenever every value is positive, which is what a
	 * reader expects: a column of 100, 110, 120 drawn from its own smallest
	 * would show 100 as nothing at all and 120 as everything, which is a lie
	 * about numbers that are all much the same. A column that does go below
	 * zero is measured from its smallest, because there is no other honest
	 * place to start.
	 *
	 * @param array<int,array<int,string>> $rows     Rows.
	 * @param int                          $position Position in the sheet.
	 * @param int                          $at       Position in the table.
	 * @return void
	 */
	protected function measure( $rows, $position, $at ) {
		$numbers = array();

		foreach ( $rows as $row_index => $row ) {
			$cell = isset( $row[ $position ] ) ? (string) $row[ $position ] : '';

			if ( '' === trim( $cell ) || ! LSTAB_Renderer::looks_numeric( $cell ) ) {
				continue;
			}

			$numbers[ $row_index ] = LSTAB_Renderer::to_number( $cell );
		}

		if ( ! $numbers ) {
			return;
		}

		$smallest = min( $numbers );
		$largest  = max( $numbers );

		/*
		 * Every value the same. Measured from zero each of them is the largest,
		 * so each would get a full bar — a column painted end to end, saying
		 * nothing at all. There is nothing to compare here, so nothing is
		 * drawn.
		 */
		if ( $smallest === $largest ) {
			return;
		}

		$floor = $smallest > 0 ? 0.0 : $smallest;
		$span  = $largest - $floor;

		if ( $span <= 0 ) {
			return;
		}

		foreach ( $numbers as $row_index => $number ) {
			$this->bars[ $row_index ][ $at ] = ( $number - $floor ) / $span;
		}
	}

	/**
	 * Dress a cell.
	 *
	 * @param array<string,string> $attributes Attribute map.
	 * @param string               $value      Raw cell value.
	 * @param int                  $col_index  Column index.
	 * @param int                  $row_index  Row index.
	 * @param array<string,mixed>  $source     Source row.
	 * @return array<string,string>
	 */
	public function attributes( $attributes, $value, $col_index, $row_index, $source ) {
		if ( ! isset( $this->columns[ $col_index ] ) ) {
			return $attributes;
		}

		$look    = $this->columns[ $col_index ];
		$setting = $this->settings[ $col_index ];
		$css     = '';

		if ( 'bar' === $look ) {
			if ( ! isset( $this->bars[ $row_index ][ $col_index ] ) ) {
				return $attributes;
			}

			/*
			 * A bar with nothing in it still says "this row has a value, and
			 * it is the smallest one" — so the shortest bar is a sliver rather
			 * than nothing, and an empty cell stays empty.
			 */
			$share = 2 + $this->bars[ $row_index ][ $col_index ] * 98;
			$css  .= '--lstabp-bar:' . number_format( $share, 2, '.', '' ) . '%;';

			if ( '' !== $setting['tint'] ) {
				$css .= '--lstabp-bar-colour:' . $setting['tint'] . ';';
			}
		}

		if ( 'button' === $look ) {
			if ( '' !== $setting['tint'] ) {
				$css .= '--lstabp-cta-bg:' . $setting['tint'] . ';';
			}

			if ( '' !== $setting['ink'] ) {
				$css .= '--lstabp-cta-ink:' . $setting['ink'] . ';';
			}
		}

		if ( '' === $css ) {
			return $attributes;
		}

		$classes = isset( $attributes['class'] ) ? $attributes['class'] . ' ' : '';

		$attributes['class'] = trim( $classes . 'lstabp-' . $look );
		$attributes['style'] = isset( $attributes['style'] ) ? $attributes['style'] . $css : $css;

		return $attributes;
	}

	/**
	 * Draw a link column as a button.
	 *
	 * Only an address becomes one. A cell in the same column holding a note,
	 * a dash or nothing at all is left exactly as it is: a button labelled
	 * "Book" that goes nowhere is worse than the note it replaced.
	 *
	 * @param string|null          $custom    What another filter already made of the cell.
	 * @param string               $value     Raw cell value.
	 * @param int                  $col_index Column index.
	 * @param int                  $row_index Row index.
	 * @param array<string,mixed>  $source    Source row.
	 * @return string|null
	 */
	public function render_cell( $custom, $value, $col_index, $row_index, $source ) {
		if ( ! isset( $this->columns[ $col_index ] ) || 'button' !== $this->columns[ $col_index ] ) {
			return $custom;
		}

		$address = trim( (string) $value );

		if ( ! preg_match( '#^https?://#i', $address ) ) {
			return $custom;
		}

		$safe = esc_url( $address );

		if ( '' === $safe ) {
			return $custom;
		}

		$label = $this->settings[ $col_index ]['label'];

		if ( '' === $label ) {
			$label = __( 'Open', 'live-sheets-table-pro' );
		}

		return '<a class="lstabp-cta-link" href="' . $safe . '" rel="nofollow noopener">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Print the card on the Appearance pane.
	 *
	 * @param string                   $pane    Pane being drawn.
	 * @param array<string,mixed>|null $source  Source row.
	 * @param bool                     $is_edit Editing an existing source.
	 * @return void
	 */
	public function render_pane_card( $pane, $source, $is_edit ) {
		if ( 'look' !== $pane ) {
			return;
		}

		$lstabp_chosen  = ( $is_edit && $source ) ? self::for_source( $source['id'] ) : array();
		$lstabp_looks   = self::looks();
		$lstabp_headers = ( $is_edit && $source && ! empty( $source['data']['headers'] ) )
			? array_values( (array) $source['data']['headers'] )
			: array();

		require LSTABP_PATH . 'includes/views/column-looks-card.php';
	}

	/**
	 * Store what was submitted with a source.
	 *
	 * Reached only from the free plugin's save handler, which has already
	 * checked the nonce and the capability.
	 *
	 * @param int $source_id Source ID.
	 * @return void
	 */
	public function save( $source_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['_lstabp_looks_present'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Sanitised in sanitize().
		$raw   = isset( $_POST['lstabp_looks'] ) ? (array) wp_unslash( $_POST['lstabp_looks'] ) : array();
		$clean = self::sanitize( $raw );

		$all                     = self::all();
		$all[ (int) $source_id ] = $clean;

		if ( ! $clean ) {
			unset( $all[ (int) $source_id ] );
		}

		update_option( self::OPTION, $all, false );
	}

	/**
	 * Drop a deleted source's choices.
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
