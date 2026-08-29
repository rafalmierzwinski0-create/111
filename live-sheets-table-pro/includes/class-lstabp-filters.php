<?php
/**
 * Filtered views: one sheet, many pages.
 *
 * A shop with twenty categories should not need twenty spreadsheets. The same
 * saved source can be shown filtered down to the rows a page is about:
 *
 *   [sheet_table id="1" filter="Kategoria is Rowery"]
 *   [sheet_table id="1" filter="Stan gt 10"]
 *   [sheet_table id="1" filter="Kategoria is Rowery, Dostępność is W magazynie"]
 *
 * Word operators are the documented form for a reason: WordPress blanks any
 * shortcode attribute containing an unclosed "<" as an XSS precaution, so
 * "Cena<100" would silently arrive empty and filter nothing. Symbols are still
 * accepted, since "=" and ">" survive and an entity-encoded "&lt;" does too,
 * but only the words work everywhere.
 *
 * Filtering happens at render time on the stored copy, so it costs no extra
 * request to Google and a page can filter differently from its neighbour.
 *
 * @package LiveSheetsTablePro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Row filtering.
 */
class LSTABP_Filters {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'lstab_render_rows', array( $this, 'filter_rows' ), 10, 3 );
		add_filter( 'lstab_shortcode_atts', array( $this, 'allow_attribute' ) );
	}

	/**
	 * Let the shortcode carry a filter.
	 *
	 * @param array<string,string> $atts Default attributes.
	 * @return array<string,string>
	 */
	public function allow_attribute( $atts ) {
		$atts['filter'] = '';

		return $atts;
	}

	/**
	 * Symbol operators, longest first so ">=" is not read as ">".
	 *
	 * @return array<int,string>
	 */
	protected static function operators() {
		return array( '!=', '>=', '<=', '*=', '=', '>', '<' );
	}

	/**
	 * Word operators, which survive every editor and page builder.
	 *
	 * @return array<string,string> Word to the symbol it means.
	 */
	protected static function word_operators() {
		return array(
			'is'  => '=',
			'not' => '!=',
			'has' => '*=',
			'gt'  => '>',
			'gte' => '>=',
			'lt'  => '<',
			'lte' => '<=',
		);
	}

	/**
	 * Read one condition written with a word operator.
	 *
	 * The column name may contain spaces, so the first token after the start
	 * that names an operator is taken as the operator and everything around it
	 * as the column and the value.
	 *
	 * @param string $part One condition.
	 * @return array{column:string,operator:string,value:string}|null
	 */
	protected static function parse_words( $part ) {
		$tokens = preg_split( '/\s+/', trim( $part ) );

		if ( ! is_array( $tokens ) || count( $tokens ) < 3 ) {
			return null;
		}

		$words = self::word_operators();

		for ( $i = 1; $i < count( $tokens ) - 1; $i++ ) {
			$word = strtolower( $tokens[ $i ] );

			if ( ! isset( $words[ $word ] ) ) {
				continue;
			}

			return array(
				'column'   => implode( ' ', array_slice( $tokens, 0, $i ) ),
				'operator' => $words[ $word ],
				'value'    => implode( ' ', array_slice( $tokens, $i + 1 ) ),
			);
		}

		return null;
	}

	/**
	 * Parse a filter expression into conditions.
	 *
	 * @param string $expression Raw expression.
	 * @return array<int,array{column:string,operator:string,value:string}>
	 */
	public static function parse( $expression ) {
		$conditions = array();

		// Editors and page builders routinely store "<" and ">" in a shortcode
		// attribute as entities, so a numeric comparison would silently match
		// nothing. Decode before looking for operators.
		$expression = html_entity_decode( (string) $expression, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		foreach ( explode( ',', $expression ) as $part ) {
			$part = trim( $part );

			if ( '' === $part ) {
				continue;
			}

			$matched = false;

			foreach ( self::operators() as $operator ) {
				$position = strpos( $part, $operator );

				if ( false === $position || 0 === $position ) {
					continue;
				}

				$conditions[] = array(
					'column'   => trim( substr( $part, 0, $position ) ),
					'operator' => $operator,
					'value'    => trim( substr( $part, $position + strlen( $operator ) ) ),
				);
				$matched = true;
				break;
			}

			if ( $matched ) {
				continue;
			}

			$worded = self::parse_words( $part );

			if ( $worded ) {
				$conditions[] = $worded;
			}
		}

		return $conditions;
	}

	/**
	 * Apply the filter to the rows being rendered.
	 *
	 * @param array<int,array<int,string>> $rows   Body rows.
	 * @param array<string,mixed>          $source Source row.
	 * @param array<string,mixed>          $args   Rendering options.
	 * @return array<int,array<int,string>>
	 */
	public function filter_rows( $rows, $source, $args ) {
		$expression = isset( $args['filter'] ) ? (string) $args['filter'] : '';

		if ( '' === trim( $expression ) ) {
			return $rows;
		}

		$conditions = self::parse( $expression );

		if ( ! $conditions ) {
			return $rows;
		}

		$headers = isset( $source['data']['headers'] ) ? array_values( (array) $source['data']['headers'] ) : array();

		// Headings the visitor sees may have been renamed or hidden in the free
		// plugin's column settings, so resolve against both.
		$columns = self::column_map( $headers, $source );

		$filtered = array();

		foreach ( $rows as $row ) {
			if ( self::matches( (array) $row, $conditions, $columns ) ) {
				$filtered[] = $row;
			}
		}

		return $filtered;
	}

	/**
	 * Map a column name to its position, by sheet heading or display label.
	 *
	 * @param array<int,string>   $headers Sheet headings.
	 * @param array<string,mixed> $source  Source row.
	 * @return array<string,int>
	 */
	protected static function column_map( $headers, $source ) {
		$map    = array();
		$config = isset( $source['columns_config'] ) ? (array) $source['columns_config'] : array();

		// Positions here are those of the *rendered* table, which is what the
		// row arrays reaching this filter actually contain.
		$position = 0;

		foreach ( $headers as $index => $heading ) {
			$column = isset( $config[ $index ] ) ? $config[ $index ] : array();

			if ( ! empty( $column['hidden'] ) ) {
				continue;
			}

			$map[ self::key( (string) $heading ) ] = $position;

			if ( ! empty( $column['label'] ) ) {
				$map[ self::key( (string) $column['label'] ) ] = $position;
			}

			$position++;
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
	 * Whether a row satisfies every condition.
	 *
	 * @param array<int,string>                                          $row        Row values.
	 * @param array<int,array{column:string,operator:string,value:string}> $conditions Conditions.
	 * @param array<string,int>                                          $columns    Column map.
	 * @return bool
	 */
	protected static function matches( $row, $conditions, $columns ) {
		foreach ( $conditions as $condition ) {
			$key = self::key( $condition['column'] );

			// A condition naming a column that is not there filters nothing
			// away, rather than silently emptying the table.
			if ( ! isset( $columns[ $key ] ) ) {
				continue;
			}

			$cell = isset( $row[ $columns[ $key ] ] ) ? (string) $row[ $columns[ $key ] ] : '';

			if ( ! self::compare( $cell, $condition['operator'], $condition['value'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Compare one cell against one condition.
	 *
	 * @param string $cell     Cell value.
	 * @param string $operator Operator.
	 * @param string $value    Expected value.
	 * @return bool
	 */
	protected static function compare( $cell, $operator, $value ) {
		$left  = self::key( $cell );
		$right = self::key( $value );

		switch ( $operator ) {
			case '=':
				return $left === $right;
			case '!=':
				return $left !== $right;
			case '*=':
				return '' === $right || false !== strpos( $left, $right );
		}

		// The remaining operators are numeric. Spreadsheet numbers arrive with
		// thousands separators and comma decimals, so normalise both sides.
		$left_number  = self::to_number( $cell );
		$right_number = self::to_number( $value );

		if ( null === $left_number || null === $right_number ) {
			return false;
		}

		switch ( $operator ) {
			case '>':
				return $left_number > $right_number;
			case '<':
				return $left_number < $right_number;
			case '>=':
				return $left_number >= $right_number;
			case '<=':
				return $left_number <= $right_number;
		}

		return false;
	}

	/**
	 * Read a spreadsheet-formatted number.
	 *
	 * @param string $value Raw value.
	 * @return float|null
	 */
	protected static function to_number( $value ) {
		$cleaned = preg_replace( '/[\p{Sc}%\s\x{00A0}\x{202F}\x{2009}]/u', '', (string) $value );
		$cleaned = preg_replace( '/(?<=[0-9])\p{L}{1,3}$/u', '', (string) $cleaned );

		if ( null === $cleaned || '' === $cleaned ) {
			return null;
		}

		$last_comma = strrpos( $cleaned, ',' );
		$last_dot   = strrpos( $cleaned, '.' );

		if ( false !== $last_comma && false !== $last_dot ) {
			$cleaned = $last_comma > $last_dot
				? str_replace( array( '.', ',' ), array( '', '.' ), $cleaned )
				: str_replace( ',', '', $cleaned );
		} elseif ( false !== $last_comma ) {
			$cleaned = substr_count( $cleaned, ',' ) > 1
				? str_replace( ',', '', $cleaned )
				: str_replace( ',', '.', $cleaned );
		}

		return is_numeric( $cleaned ) ? (float) $cleaned : null;
	}
}
