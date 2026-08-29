<?php
/**
 * Conditional formatting: colour a cell by what is in it.
 *
 * "In stock" green and "Sold out" red is the whole idea. A rule names a column,
 * a comparison and a value, and picks one of a fixed set of looks — a palette
 * rather than free colour pickers, so a table stays legible whoever built it
 * and so contrast is not left to chance.
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

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'lstab_edit_page_settings', array( $this, 'render_card' ), 10, 2 );
		add_action( 'lstab_source_saved', array( $this, 'save' ) );
		add_action( 'lstab_source_deleted', array( $this, 'forget' ) );
	}

	/**
	 * The looks a rule may apply.
	 *
	 * Backgrounds are pale and text is dark, so every pairing clears the
	 * contrast bar without the person setting it up having to think about it.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function styles() {
		return array(
			'red'    => array(
				'label' => __( 'Red', 'live-sheets-table-pro' ),
				'css'   => 'background-color:#fdecec;color:#8a1c1c;',
			),
			'amber'  => array(
				'label' => __( 'Amber', 'live-sheets-table-pro' ),
				'css'   => 'background-color:#fff5e0;color:#7a4b00;',
			),
			'green'  => array(
				'label' => __( 'Green', 'live-sheets-table-pro' ),
				'css'   => 'background-color:#e9f7ee;color:#14532d;',
			),
			'blue'   => array(
				'label' => __( 'Blue', 'live-sheets-table-pro' ),
				'css'   => 'background-color:#e8f1fd;color:#1e3a8a;',
			),
			'grey'   => array(
				'label' => __( 'Grey', 'live-sheets-table-pro' ),
				'css'   => 'background-color:#f1f2f4;color:#3f4249;',
			),
			'bold'   => array(
				'label' => __( 'Bold text', 'live-sheets-table-pro' ),
				'css'   => 'font-weight:700;',
			),
			'strike' => array(
				'label' => __( 'Struck through', 'live-sheets-table-pro' ),
				'css'   => 'text-decoration:line-through;opacity:0.62;',
			),
		);
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
		$all = self::all();
		$key = (int) $source_id;

		return isset( $all[ $key ] ) ? self::sanitize( $all[ $key ] ) : array();
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
		$styles    = self::styles();
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
			$style    = isset( $rule['style'] ) ? sanitize_key( (string) $rule['style'] ) : 'red';

			$clean[] = array(
				'column'   => $column,
				'operator' => isset( $operators[ $operator ] ) ? $operator : '=',
				'value'    => isset( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '',
				'style'    => isset( $styles[ $style ] ) ? $style : 'red',
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
		$styles   = self::styles();

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

				$css = $styles[ $rule['style'] ]['css'];

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

		wp_localize_script( 'lstabp-admin', 'lstabpRules', array( 'styles' => $swatches ) );
	}

	/**
	 * Print the rules card on the source screen.
	 *
	 * @param array<string,mixed>|null $source  Source row, or null while adding.
	 * @param bool                     $is_edit Whether an existing source is being edited.
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
