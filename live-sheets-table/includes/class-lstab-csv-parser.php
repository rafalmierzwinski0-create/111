<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_CSV_Parser {
	public static function parse( $csv, $first_row_header = true ) {
		$csv = self::normalise_encoding( (string) $csv );

		if ( '' === trim( $csv ) ) {
			return new WP_Error(
				'lstab_empty_csv',
				__( 'The sheet returned no data. Check that the tab you picked actually contains rows.', 'live-sheets-table' )
			);
		}

		$grid = self::to_grid( $csv );

		$offset = self::leading_blank_rows( $grid );
		$grid   = self::trim_empty_rows( $grid );

		if ( ! $grid ) {
			return new WP_Error(
				'lstab_empty_csv',
				__( 'The sheet returned no data. Check that the tab you picked actually contains rows.', 'live-sheets-table' )
			);
		}

		$columns = 0;
		foreach ( $grid as $row ) {
			$columns = max( $columns, count( $row ) );
		}

		$ragged = self::ragged_rows( $grid, $columns, $offset );

		if ( $first_row_header ) {
			$headers = self::normalise_headers( array_shift( $grid ), $columns );
		} else {
			$headers = self::generated_headers( $columns );
		}

		$rows = array();
		foreach ( $grid as $row ) {
			$rows[] = self::pad_row( $row, $columns );
		}

		$parsed = array(
			'headers' => $headers,
			'rows'    => array_values( $rows ),
			'offset'  => $offset + ( $first_row_header ? 1 : 0 ),
		);

		if ( $ragged ) {
			$parsed['ragged'] = $ragged;
		}

		return $parsed;
	}

	protected static function leading_blank_rows( $grid ) {
		$blank = 0;

		foreach ( $grid as $row ) {
			foreach ( $row as $cell ) {
				if ( '' !== trim( (string) $cell ) ) {
					return $blank;
				}
			}

			$blank++;
		}

		return $blank;
	}

	protected static function ragged_rows( $grid, $columns, $offset ) {
		$found = array();
		$total = 0;

		foreach ( $grid as $index => $row ) {
			if ( count( $row ) === $columns ) {
				continue;
			}

			$total++;

			if ( count( $found ) < 5 ) {
				$found[] = array(
					'row'   => $offset + $index + 1,
					'found' => count( $row ),
				);
			}
		}

		if ( ! $total ) {
			return null;
		}

		return array(
			'expected' => $columns,
			'total'    => $total,
			'rows'     => $found,
		);
	}

	public static function normalise_encoding( $csv ) {
		if ( 0 === strncmp( $csv, "\xEF\xBB\xBF", 3 ) ) {
			$csv = substr( $csv, 3 );
		}

		if ( 0 === strncmp( $csv, "\xFF\xFE", 2 ) || 0 === strncmp( $csv, "\xFE\xFF", 2 ) ) {
			$from = ( "\xFF\xFE" === substr( $csv, 0, 2 ) ) ? 'UTF-16LE' : 'UTF-16BE';
			if ( function_exists( 'mb_convert_encoding' ) ) {
				$converted = mb_convert_encoding( substr( $csv, 2 ), 'UTF-8', $from );
				if ( false !== $converted ) {
					$csv = $converted;
				}
			}
		}

		if ( ! self::is_valid_utf8( $csv ) ) {
			$converted = function_exists( 'mb_convert_encoding' )
				? mb_convert_encoding( $csv, 'UTF-8', 'Windows-1252' )
				: false;
			$csv = ( false !== $converted && null !== $converted ) ? $converted : wp_check_invalid_utf8( $csv, true );
		}

		$csv = str_replace( array( "\r\n", "\r" ), "\n", $csv );

		$csv = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $csv );

		return (string) $csv;
	}

	protected static function is_valid_utf8( $text ) {
		if ( function_exists( 'wp_is_valid_utf8' ) ) {
			return (bool) wp_is_valid_utf8( $text );
		}

		return (bool) seems_utf8( $text );
	}

	protected static function to_grid( $csv ) {
		$rows    = array();
		$row     = array();
		$field   = '';
		$quoted  = false;
		$length  = strlen( $csv );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $csv[ $i ];

			if ( $quoted ) {
				if ( '"' === $char ) {
					if ( $i + 1 < $length && '"' === $csv[ $i + 1 ] ) {
						$field .= '"';
						$i++;

						$after = $i + 1 < $length ? $csv[ $i + 1 ] : '';

						if ( '' === $after || ',' === $after || "\n" === $after || "\r" === $after ) {
							$quoted = false;
						}

						continue;
					}

					$next = $i + 1 < $length ? $csv[ $i + 1 ] : '';

					if ( '' === $next || ',' === $next || "\n" === $next || "\r" === $next ) {
						$quoted = false;
					} else {
						$field .= '"';
					}
				} else {
					$field .= $char;
				}
				continue;
			}

			if ( '"' === $char && '' === $field ) {
				$quoted = true;
				continue;
			}

			if ( ',' === $char ) {
				$row[] = $field;
				$field = '';
				continue;
			}

			if ( "\n" === $char ) {
				$row[] = $field;
				$rows[] = $row;
				$row    = array();
				$field  = '';
				continue;
			}

			$field .= $char;
		}

		if ( '' !== $field || $row ) {
			$row[]  = $field;
			$rows[] = $row;
		}

		return $rows;
	}

	protected static function trim_empty_rows( $grid ) {
		$filtered = array();

		foreach ( $grid as $row ) {
			$has_value = false;
			foreach ( $row as $cell ) {
				if ( '' !== trim( (string) $cell ) ) {
					$has_value = true;
					break;
				}
			}
			$filtered[] = array(
				'row'   => $row,
				'empty' => ! $has_value,
			);
		}

		while ( $filtered && $filtered[0]['empty'] ) {
			array_shift( $filtered );
		}
		while ( $filtered && end( $filtered )['empty'] ) {
			array_pop( $filtered );
		}

		return array_values( wp_list_pluck( $filtered, 'row' ) );
	}

	protected static function normalise_headers( $raw, $columns ) {
		$raw     = self::pad_row( (array) $raw, $columns );
		$headers = array();
		$seen    = array();

		foreach ( $raw as $index => $label ) {
			$label = trim( (string) $label );

			if ( '' === $label ) {
				/* translators: %d: column number. */
				$label = sprintf( __( 'Column %d', 'live-sheets-table' ), $index + 1 );
			}

			$base    = $label;
			$counter = 2;
			while ( isset( $seen[ strtolower( $label ) ] ) ) {
				$label = $base . ' (' . $counter . ')';
				$counter++;
			}

			$seen[ strtolower( $label ) ] = true;
			$headers[]                    = $label;
		}

		return $headers;
	}

	protected static function generated_headers( $columns ) {
		$headers = array();
		for ( $i = 0; $i < $columns; $i++ ) {
			/* translators: %d: column number. */
			$headers[] = sprintf( __( 'Column %d', 'live-sheets-table' ), $i + 1 );
		}
		return $headers;
	}

	protected static function pad_row( $row, $columns ) {
		$row = array_values( array_map( 'strval', (array) $row ) );

		if ( count( $row ) > $columns ) {
			$row = array_slice( $row, 0, $columns );
		}

		return array_pad( $row, $columns, '' );
	}
}
