<?php
/**
 * RFC 4180 CSV parser.
 *
 * Written by hand rather than leaning on str_getcsv() because Google exports
 * routinely contain quoted fields with embedded commas, quotes and newlines,
 * and str_getcsv() cannot see past a single line.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * CSV parser.
 */
class LSTAB_CSV_Parser {

	/**
	 * Parse a CSV payload into a header row plus body rows.
	 *
	 * @param string $csv              Raw CSV text.
	 * @param bool   $first_row_header Treat the first row as column labels.
	 * @return array{headers:array<int,string>,rows:array<int,array<int,string>>}|WP_Error
	 */
	public static function parse( $csv, $first_row_header = true ) {
		$csv = self::normalise_encoding( (string) $csv );

		if ( '' === trim( $csv ) ) {
			return new WP_Error(
				'lstab_empty_csv',
				__( 'The sheet returned no data. Check that the tab you picked actually contains rows.', 'live-sheets-table' )
			);
		}

		$grid = self::to_grid( $csv );

		// Row numbers are reported back to the site owner, so they have to be
		// the numbers they see in Google. Blank rows are trimmed off the top
		// below, which would otherwise shift every one of them.
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
		);

		// Carried with the data rather than reported separately, so it travels
		// into the stored copy and is replaced the moment a clean sync lands.
		if ( $ragged ) {
			$parsed['ragged'] = $ragged;
		}

		return $parsed;
	}

	/**
	 * Count blank rows at the top of a grid.
	 *
	 * @param array<int,array<int,string>> $grid Parsed grid.
	 * @return int
	 */
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

	/**
	 * Find rows holding a different number of cells from the rest.
	 *
	 * Google gives every row the same number of cells, always. A row that
	 * disagrees means the payload did not survive the trip intact — most often
	 * an unmatched quotation mark, which runs two rows together. Nothing is
	 * corrected here: the table still renders, and the site owner is told where
	 * to look.
	 *
	 * @param array<int,array<int,string>> $grid    Parsed grid.
	 * @param int                          $columns Cells the widest row holds.
	 * @param int                          $offset  Blank rows trimmed off the top.
	 * @return array{expected:int,total:int,rows:array<int,array{row:int,found:int}>}|null
	 */
	protected static function ragged_rows( $grid, $columns, $offset ) {
		$found = array();
		$total = 0;

		foreach ( $grid as $index => $row ) {
			if ( count( $row ) === $columns ) {
				continue;
			}

			$total++;

			// A handful is enough to find the problem; a list of two hundred
			// is just a wall.
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

	/**
	 * Strip a UTF-8 BOM and coerce the payload to valid UTF-8.
	 *
	 * @param string $csv Raw payload.
	 * @return string
	 */
	public static function normalise_encoding( $csv ) {
		// UTF-8 BOM.
		if ( 0 === strncmp( $csv, "\xEF\xBB\xBF", 3 ) ) {
			$csv = substr( $csv, 3 );
		}

		// UTF-16 BOMs: convert rather than mangle.
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
			// Google always serves UTF-8; this only guards hand-edited or proxied payloads.
			$converted = function_exists( 'mb_convert_encoding' )
				? mb_convert_encoding( $csv, 'UTF-8', 'Windows-1252' )
				: false;
			$csv = ( false !== $converted && null !== $converted ) ? $converted : wp_check_invalid_utf8( $csv, true );
		}

		// Normalise line endings so the scanner only has to deal with "\n".
		$csv = str_replace( array( "\r\n", "\r" ), "\n", $csv );

		/*
		 * Control characters that mean nothing in a cell are dropped here, at
		 * the edge, rather than left to travel through the parser, the
		 * database, the page and any feed or export built from it. A null byte
		 * pasted into a spreadsheet is invisible in Google and invisible in a
		 * browser, but it truncates C strings, makes XML invalid, and is the
		 * kind of thing that turns up months later as an unexplained blank.
		 * Tab and newline are left alone: both are legitimate inside a cell.
		 */
		$csv = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $csv );

		return (string) $csv;
	}

	/**
	 * UTF-8 validity check that works across supported WordPress versions.
	 *
	 * seems_utf8() was deprecated in WordPress 6.9 in favour of
	 * wp_is_valid_utf8(); the plugin still supports 6.0, so pick whichever
	 * exists rather than emitting a deprecation notice on new installs.
	 *
	 * @param string $text Text to check.
	 * @return bool
	 */
	protected static function is_valid_utf8( $text ) {
		if ( function_exists( 'wp_is_valid_utf8' ) ) {
			return (bool) wp_is_valid_utf8( $text );
		}

		return (bool) seems_utf8( $text );
	}

	/**
	 * Scan CSV text into a two dimensional array.
	 *
	 * @param string $csv Normalised CSV text.
	 * @return array<int,array<int,string>>
	 */
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
					// A doubled quote inside a quoted field is a literal quote.
					if ( $i + 1 < $length && '"' === $csv[ $i + 1 ] ) {
						$field .= '"';
						$i++;

						/*
						 * Normally the field carries on after an escaped
						 * quote. But a value that ends in a quote of its own —
						 * Rower górski „Trek" — written by something that
						 * forgot to double it leaves these same two characters
						 * exactly where the field ends. Reading them as an
						 * escape then swallows every remaining row into this
						 * one cell. A delimiter straight afterwards settles
						 * it: the pair was the value's quote and the field's
						 * closing one.
						 */
						$after = $i + 1 < $length ? $csv[ $i + 1 ] : '';

						if ( '' === $after || ',' === $after || "\n" === $after || "\r" === $after ) {
							$quoted = false;
						}

						continue;
					}

					/*
					 * A real closing quote is followed by a comma, a line
					 * break, or nothing at all. Anything else means the sheet
					 * holds a stray quotation mark, and ending the field here
					 * would swallow every remaining row into this one cell —
					 * a single stray quote could turn a seven-row table into
					 * one row of run-together text. Keep it as a character.
					 */
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

			// A quote only opens a quoted field at the start of one. Halfway
			// through, it is just a character the sheet happens to contain.
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

		// Flush whatever is still buffered when the payload has no trailing newline.
		if ( '' !== $field || $row ) {
			$row[]  = $field;
			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Drop trailing rows that are entirely empty.
	 *
	 * @param array<int,array<int,string>> $grid Parsed grid.
	 * @return array<int,array<int,string>>
	 */
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

		// Trim empties from both ends but keep blank separator rows in the middle.
		while ( $filtered && $filtered[0]['empty'] ) {
			array_shift( $filtered );
		}
		while ( $filtered && end( $filtered )['empty'] ) {
			array_pop( $filtered );
		}

		return array_values( wp_list_pluck( $filtered, 'row' ) );
	}

	/**
	 * Clean up header labels, filling in blanks and de-duplicating.
	 *
	 * @param array<int,string> $raw     Raw header row.
	 * @param int               $columns Column count.
	 * @return array<int,string>
	 */
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

	/**
	 * Placeholder headers for sheets whose first row is data.
	 *
	 * @param int $columns Column count.
	 * @return array<int,string>
	 */
	protected static function generated_headers( $columns ) {
		$headers = array();
		for ( $i = 0; $i < $columns; $i++ ) {
			/* translators: %d: column number. */
			$headers[] = sprintf( __( 'Column %d', 'live-sheets-table' ), $i + 1 );
		}
		return $headers;
	}

	/**
	 * Pad or truncate a row so every row has the same width.
	 *
	 * @param array<int,string> $row     Row values.
	 * @param int               $columns Target width.
	 * @return array<int,string>
	 */
	protected static function pad_row( $row, $columns ) {
		$row = array_values( array_map( 'strval', (array) $row ) );

		if ( count( $row ) > $columns ) {
			$row = array_slice( $row, 0, $columns );
		}

		return array_pad( $row, $columns, '' );
	}
}
