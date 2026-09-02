<?php
/**
 * Rows the author has taken out of the table.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hiding individual rows, by where they are and what is there.
 *
 * A choice records two things: the line the row was on, and what that row was
 * called. The line is how the row is found again; the name is how it is checked.
 *
 * Finding it by the line alone would be unsafe on a live document, because the
 * line is the one thing about a row that changes for reasons having nothing to
 * do with the row: someone inserting a line above moves it without touching it.
 * So before a row is taken out of the table, what is on that line now has to
 * still be what was there when the choice was made.
 *
 * When it is not, nothing is hidden and the dashboard says so. That is the whole
 * safety of this: the failure becomes a row appearing that should not — a
 * mistake somebody can see, and is told about — rather than the wrong row
 * disappearing, which is a mistake nobody sees at all.
 */
class LSTAB_Hidden_Rows {

	/**
	 * How many rows one table may hide.
	 *
	 * Past this it is a filter, not a hand-picked exception, and Pro has one.
	 */
	const MAX_ROWS = 500;

	/**
	 * Longest stored piece of text, in characters.
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
	 * What a row is called: its first filled cell.
	 *
	 * @param array<int,string> $row Row cells.
	 * @return string Name, or '' for a row with nothing in it.
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
	 * What one row says, in full.
	 *
	 * The check for rows with no name to be checked by.
	 *
	 * @param array<int,string> $row Row cells.
	 * @return string
	 */
	public static function signature( $row ) {
		$cells = array();

		foreach ( (array) $row as $cell ) {
			$cells[] = self::tidy( (string) $cell );
		}

		return md5( implode( "\x1F", $cells ) );
	}

	/**
	 * A row put into words, for saying which one is meant.
	 *
	 * A name alone is often no help: it may be a date, a code, or the number 3.
	 * A few of the filled cells shown together is how the row reads on the page
	 * and therefore how someone will recognise it. Four at most — a row of
	 * twenty columns quoted in full is the row again, in a sentence, and nobody
	 * reads to the end of it.
	 *
	 * @param array<int,string> $cells Row cells.
	 * @param int               $parts How many filled cells to show.
	 * @return string
	 */
	public static function describe( $cells, $parts = 4 ) {
		$shown = array();

		foreach ( (array) $cells as $cell ) {
			$cell = self::tidy( (string) $cell );

			if ( '' === $cell ) {
				continue;
			}

			$shown[] = function_exists( 'mb_substr' ) ? mb_substr( $cell, 0, 40 ) : substr( $cell, 0, 40 );

			if ( count( $shown ) >= $parts ) {
				break;
			}
		}

		return implode( ' · ', $shown );
	}

	/**
	 * Everything a choice records about one row.
	 *
	 * @param array<int,string> $row   Row cells.
	 * @param int               $index Position among the stored rows.
	 * @return array{index:int,name:string,sig:string,label:string}
	 */
	public static function entry_for( $row, $index ) {
		return array(
			'index' => max( 0, (int) $index ),
			'name'  => self::key_for( $row ),
			'sig'   => self::signature( $row ),
			'label' => self::describe( $row ),
		);
	}

	/**
	 * Collapse the differences that are not differences.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	protected static function tidy( $value ) {
		$value = preg_replace( '/\s+/u', ' ', (string) $value );

		return trim( (string) $value );
	}

	/**
	 * Cut a stored string down to size.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	protected static function clip( $value ) {
		$value = self::tidy( sanitize_text_field( (string) $value ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::MAX_KEY ) : substr( $value, 0, self::MAX_KEY );
	}

	/**
	 * Clean a stored or submitted list of hidden rows.
	 *
	 * @param mixed $entries Anything.
	 * @return array<int,array{index:int,name:string,sig:string,label:string}>
	 */
	public static function sanitize( $entries ) {
		if ( ! is_array( $entries ) ) {
			return array();
		}

		$clean = array();
		$seen  = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['index'] ) || ! is_scalar( $entry['index'] ) ) {
				continue;
			}

			$index = (int) $entry['index'];

			if ( $index < 0 || isset( $seen[ $index ] ) ) {
				continue;
			}

			$sig = isset( $entry['sig'] ) && is_scalar( $entry['sig'] )
				? strtolower( preg_replace( '/[^a-f0-9]/i', '', (string) $entry['sig'] ) )
				: '';

			$seen[ $index ] = true;
			$clean[]        = array(
				'index' => $index,
				'name'  => isset( $entry['name'] ) && is_scalar( $entry['name'] ) ? self::clip( $entry['name'] ) : '',
				'sig'   => 32 === strlen( $sig ) ? $sig : '',
				'label' => isset( $entry['label'] ) && is_scalar( $entry['label'] ) ? self::clip( $entry['label'] ) : '',
			);
		}

		return array_slice( $clean, 0, self::MAX_ROWS );
	}

	/**
	 * Whether the line still holds exactly what it held when it was chosen.
	 *
	 * Everything the row said, not merely its name. Checking the name alone
	 * looks kinder — a price change would not put the row back — but it cannot
	 * see a shift in a sheet where rows share a name: ten products all called
	 * "Kask", one line inserted above, and the check passes on the helmet that
	 * has moved into that line. Then the wrong one disappears, quietly, which
	 * is the single outcome none of this may ever produce.
	 *
	 * So any change to that line puts the row back on the page and says so.
	 * Editing a hidden row costs a click to hide it again; the alternative
	 * costs somebody a product missing from their price list and no way to
	 * know.
	 *
	 * @param array{index:int,name:string,sig:string,label:string} $entry Stored choice.
	 * @param array<int,array<int,string>>                         $rows  Sheet rows.
	 * @return bool
	 */
	public static function still_there( $entry, $rows ) {
		if ( ! isset( $rows[ $entry['index'] ] ) || '' === $entry['sig'] ) {
			return false;
		}

		return self::signature( $rows[ $entry['index'] ] ) === $entry['sig'];
	}

	/**
	 * The line number Google shows beside one of the stored rows.
	 *
	 * Our first stored row is the sheet's second line whenever there is a
	 * heading, and further down again for every blank line above it. A line
	 * number that is off by one is worse than no line number.
	 *
	 * @param array<string,mixed> $source Source row.
	 * @param int                 $index  Position among the stored rows.
	 * @return int
	 */
	public static function line_for( $source, $index ) {
		$offset = isset( $source['data']['offset'] ) ? (int) $source['data']['offset'] : 0;

		return $offset + (int) $index + 1;
	}

	/**
	 * Which stored rows are to be taken out of the table.
	 *
	 * @param array<int,array<string,mixed>> $entries Stored choices.
	 * @param array<int,array<int,string>>   $rows    Sheet rows.
	 * @return array<int,bool> Positions, keyed for lookup.
	 */
	public static function positions( $entries, $rows ) {
		$rows  = array_values( (array) $rows );
		$found = array();

		foreach ( self::sanitize( $entries ) as $entry ) {
			if ( self::still_there( $entry, $rows ) ) {
				$found[ $entry['index'] ] = true;
			}
		}

		return $found;
	}

	/**
	 * Choices that no longer describe what is on their line, and why.
	 *
	 * @param array<int,array<string,mixed>> $entries Stored choices.
	 * @param array<int,array<int,string>>   $rows    Sheet rows.
	 * @return array<int,array{label:string,index:int,reason:string}>
	 */
	public static function unresolved( $entries, $rows ) {
		$rows    = array_values( (array) $rows );
		$stalled = array();

		foreach ( self::sanitize( $entries ) as $entry ) {
			if ( self::still_there( $entry, $rows ) ) {
				continue;
			}

			$stalled[] = array(
				'label'  => '' !== $entry['label'] ? $entry['label'] : $entry['name'],
				'index'  => $entry['index'],
				'reason' => isset( $rows[ $entry['index'] ] ) ? 'moved' : 'missing',
			);
		}

		return $stalled;
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
		$entries = isset( $source['hidden_rows'] ) ? self::sanitize( $source['hidden_rows'] ) : array();

		if ( ! $entries || ! LSTAB_Limits::pro_effective() ) {
			return $rows;
		}

		$drop = self::positions( $entries, $rows );

		if ( ! $drop ) {
			return $rows;
		}

		$kept = array();

		foreach ( array_values( (array) $rows ) as $index => $row ) {
			if ( isset( $drop[ $index ] ) ) {
				continue;
			}

			$kept[] = $row;
		}

		return $kept;
	}

	/**
	 * Keep the record of each hidden row current.
	 *
	 * Run after every sync. A row still on its line is described again as it
	 * now reads, so an edit to it is carried rather than piling up until
	 * nothing matches. A row that is not there is left exactly as it was: the
	 * choice has not stopped meaning anything, it has stopped applying, and the
	 * dashboard is what says so.
	 *
	 * @param int                          $id   Source ID.
	 * @param array<int,array<int,string>> $rows Sheet rows as just stored.
	 * @return void
	 */
	public static function reanchor( $id, $rows ) {
		$source = LSTAB_Storage::get( $id );

		if ( ! $source || empty( $source['hidden_rows'] ) ) {
			return;
		}

		$rows    = array_values( (array) $rows );
		$updated = array();
		$changed = false;

		foreach ( self::sanitize( $source['hidden_rows'] ) as $entry ) {
			if ( ! self::still_there( $entry, $rows ) ) {
				$updated[] = $entry;
				continue;
			}

			$fresh = self::entry_for( $rows[ $entry['index'] ], $entry['index'] );

			if ( $fresh !== $entry ) {
				$changed = true;
			}

			$updated[] = $fresh;
		}

		if ( $changed ) {
			LSTAB_Storage::update( $id, array( 'hidden_rows' => $updated ) );
		}
	}
}
