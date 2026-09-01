<?php
/**
 * Rows the author has taken out of the table.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hiding individual rows, by what they say rather than where they sit.
 *
 * The obvious way to remember "hide this row" is to remember its number. It is
 * also wrong: the sheet is a live document, and someone inserting a line at the
 * top of it would silently hide a different row from the one that was chosen.
 * Nothing would look broken — a table would simply stop showing a product, and
 * start showing one that was meant to be hidden.
 *
 * So a hidden row is remembered by the value in its first filled column, which
 * is nearly always what names the row: a product, a person, a date. Rows can
 * then be added, removed and reordered in Google all day without disturbing
 * anything. Renaming that value does bring the row back, which is the one case
 * worth knowing about and the only one where the identity really has changed.
 */
class LSTAB_Hidden_Rows {

	/**
	 * How many rows one table may hide.
	 *
	 * Past this it is a filter, not a hand-picked exception, and Pro has one.
	 */
	const MAX_ROWS = 500;

	/**
	 * Longest key kept, in characters.
	 */
	const MAX_KEY = 300;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		/*
		 * Before paging at 15, so a hidden row is not counted, does not take up
		 * a place on a page, and cannot be found by searching. After the row
		 * filter at 10, so an add-on still decides which rows exist at all.
		 */
		add_filter( 'lstab_source_rows', array( __CLASS__, 'filter_rows' ), 12, 4 );
	}

	/**
	 * What identifies one row.
	 *
	 * @param array<int,string> $row Row cells.
	 * @return string Key, or '' for a row with nothing in it.
	 */
	public static function key_for( $row ) {
		foreach ( (array) $row as $cell ) {
			$cell = self::tidy( (string) $cell );

			if ( '' !== $cell ) {
				return $cell;
			}
		}

		return '';
	}

	/**
	 * Collapse the differences that are not differences.
	 *
	 * A value copied out of a cell and one read from the payload can disagree
	 * about runs of whitespace without disagreeing about anything a person
	 * would notice.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	protected static function tidy( $value ) {
		$value = preg_replace( '/\s+/u', ' ', (string) $value );

		return trim( (string) $value );
	}

	/**
	 * Clean a stored or submitted list of keys.
	 *
	 * @param mixed $keys Anything.
	 * @return array<int,string>
	 */
	public static function sanitize( $keys ) {
		if ( ! is_array( $keys ) ) {
			return array();
		}

		$clean = array();

		foreach ( $keys as $key ) {
			if ( is_array( $key ) || is_object( $key ) ) {
				continue;
			}

			$key = self::tidy( sanitize_text_field( (string) $key ) );

			if ( '' === $key ) {
				continue;
			}

			$clean[] = function_exists( 'mb_substr' ) ? mb_substr( $key, 0, self::MAX_KEY ) : substr( $key, 0, self::MAX_KEY );
		}

		$clean = array_values( array_unique( $clean ) );

		return array_slice( $clean, 0, self::MAX_ROWS );
	}

	/**
	 * Drop the rows this source hides.
	 *
	 * @param array<int,array<int,string>> $rows    Body rows.
	 * @param array<int,string>            $headers Sheet headings.
	 * @param array<string,mixed>          $source  Source row.
	 * @param array<string,mixed>          $args    Rendering options.
	 * @return array<int,array<int,string>>
	 */
	public static function filter_rows( $rows, $headers, $source, $args ) {
		$hidden = isset( $source['hidden_rows'] ) ? self::sanitize( $source['hidden_rows'] ) : array();

		if ( ! $hidden ) {
			return $rows;
		}

		$hidden = array_flip( $hidden );
		$kept   = array();

		foreach ( (array) $rows as $row ) {
			if ( isset( $hidden[ self::key_for( $row ) ] ) ) {
				continue;
			}

			$kept[] = $row;
		}

		return $kept;
	}

	/**
	 * Whether a row would be hidden, for the admin preview.
	 *
	 * @param array<int,string> $row  Row cells.
	 * @param array<int,string> $keys Hidden keys.
	 * @return bool
	 */
	public static function is_hidden( $row, $keys ) {
		$key = self::key_for( $row );

		return '' !== $key && in_array( $key, (array) $keys, true );
	}
}
