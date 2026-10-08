<?php

defined( 'ABSPATH' ) || exit;

class LSTABP_Filters {
	public function register() {
		add_filter( 'lstab_source_rows', array( $this, 'filter_rows' ), 10, 4 );
		add_filter( 'lstab_filter_supported', '__return_true' );
		add_filter( 'lstab_shortcode_options', array( $this, 'offer_filter' ), 10, 2 );
	}

	public function offer_filter( $options, $source ) {
		$example = __( 'filter="Column is value"', 'live-sheets-table-pro' );

		if ( ! empty( $source['columns'] ) && is_array( $source['columns'] ) ) {
			foreach ( $source['columns'] as $column ) {
				$heading = '';

				if ( is_array( $column ) ) {
					$heading = ! empty( $column['label'] ) ? $column['label'] : ( isset( $column['heading'] ) ? $column['heading'] : '' );
				}

				if ( '' !== trim( (string) $heading ) ) {
					$example = sprintf( 'filter="%s is …"', $heading );
					break;
				}
			}
		}

		$options[] = array(
			'att'   => 'filter',
			'type'  => 'written',
			'write' => $example,
			'means' => __( 'Show only the rows that match, so one sheet can feed several pages', 'live-sheets-table-pro' ),
			'note'  => __( 'Join conditions with a comma; the words to compare with are listed under Pro settings.', 'live-sheets-table-pro' ),
		);

		return $options;
	}

	protected static function operators() {
		return array( '!=', '>=', '<=', '*=', '=', '>', '<' );
	}

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

			$operator = $words[ $word ];
			$value_at = $i + 1;

			if ( '=' === $operator && 'not' === strtolower( $tokens[ $value_at ] ) && $value_at + 1 < count( $tokens ) ) {
				$operator = '!=';
				$value_at++;
			}

			return array(
				'column'   => implode( ' ', array_slice( $tokens, 0, $i ) ),
				'operator' => $operator,
				'value'    => implode( ' ', array_slice( $tokens, $value_at ) ),
			);
		}

		return null;
	}

	protected static function parse_condition( $part ) {
		$part = trim( $part );

		if ( '' === $part ) {
			return null;
		}

		foreach ( self::operators() as $operator ) {
			$position = strpos( $part, $operator );

			if ( false === $position || 0 === $position ) {
				continue;
			}

			return array(
				'column'   => trim( substr( $part, 0, $position ) ),
				'operator' => $operator,
				'value'    => trim( substr( $part, $position + strlen( $operator ) ) ),
			);
		}

		return self::parse_words( $part );
	}

	protected static function all_conditions( $parts ) {
		if ( count( $parts ) < 2 ) {
			return false;
		}

		foreach ( $parts as $part ) {
			if ( ! self::parse_condition( $part ) ) {
				return false;
			}
		}

		return true;
	}

	protected static function split( $expression ) {
		$expression = trim( $expression );
		$worded     = preg_split( '/\s+and\s+/iu', $expression );
		$parts      = is_array( $worded ) && self::all_conditions( $worded )
			? $worded
			: array( $expression );

		$out = array();

		foreach ( $parts as $part ) {
			$pieces = array_map( 'trim', explode( ',', $part ) );

			if ( self::all_conditions( $pieces ) ) {
				$out = array_merge( $out, $pieces );
				continue;
			}

			$out[] = $part;
		}

		return $out;
	}

	public static function parse( $expression ) {
		$conditions = array();

		$expression = html_entity_decode( (string) $expression, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		foreach ( self::split( $expression ) as $part ) {
			$condition = self::parse_condition( $part );

			if ( $condition ) {
				$conditions[] = $condition;
			}
		}

		return $conditions;
	}

	public function filter_rows( $rows, $headers, $source, $args ) {
		$expression = isset( $args['filter'] ) ? (string) $args['filter'] : '';

		if ( '' === trim( $expression ) ) {
			return $rows;
		}

		$conditions = self::parse( $expression );

		if ( ! $conditions ) {
			return $rows;
		}

		$columns = self::column_map( array_values( (array) $headers ), $source );

		$filtered = array();

		foreach ( $rows as $row ) {
			if ( self::matches( (array) $row, $conditions, $columns ) ) {
				$filtered[] = $row;
			}
		}

		return $filtered;
	}

	public static function column_map( $headers, $source ) {
		$map    = array();
		$config = isset( $source['columns_config'] ) ? (array) $source['columns_config'] : array();

		foreach ( $headers as $index => $heading ) {
			$map[ self::key( (string) $heading ) ] = $index;

			$column = isset( $config[ $index ] ) ? $config[ $index ] : array();

			if ( ! empty( $column['label'] ) ) {
				$map[ self::key( (string) $column['label'] ) ] = $index;
			}
		}

		return $map;
	}

	protected static function key( $name ) {
		return function_exists( 'mb_strtolower' )
			? mb_strtolower( trim( $name ), 'UTF-8' )
			: strtolower( trim( $name ) );
	}

	protected static function matches( $row, $conditions, $columns ) {
		foreach ( $conditions as $condition ) {
			$key = self::key( $condition['column'] );

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

	public static function compare( $cell, $operator, $value ) {
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
