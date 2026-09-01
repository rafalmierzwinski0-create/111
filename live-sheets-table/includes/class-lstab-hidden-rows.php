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
 * The obvious way to remember "hide this row" is to remember its number, and it
 * is wrong for a live document: someone inserting a line at the top of the sheet
 * would silently hide a different row from the one that was chosen. Nothing
 * would look broken — a table would simply stop showing one product and start
 * showing another that was meant to be hidden.
 *
 * So a choice records what the row said, twice over. The whole row, cell by
 * cell, which tells ten products all called "Kask" apart exactly. And the row's
 * name — its first filled cell — which is what survives someone editing it.
 *
 * Finding it again then goes in that order. The row that matches in full is the
 * row that was chosen, however far it has moved. If nothing matches in full the
 * row has been edited, so the one of that name is taken instead — but only while
 * exactly one row carries it, because a name shared by several is no longer an
 * answer to "which one". In that last case nothing is hidden and the screen says
 * so: a row that should be hidden and is not is a mistake someone can see, and
 * the wrong row disappearing is not.
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
	 * What one row says, in full.
	 *
	 * Two rows that agree on every cell are indistinguishable to a reader, so
	 * they may as well be indistinguishable here. Two that differ anywhere —
	 * a size, a price — are told apart exactly, which is the case a name alone
	 * could never handle: ten rows called "Kask" are ten different products.
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
	 * What a row is called: its first filled cell.
	 *
	 * The fallback, for when the row has been edited since it was hidden. A
	 * price changing must not put a row back on a public page, and the name is
	 * what survives an edit.
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
	 * Everything needed to find one row again.
	 *
	 * @param array<int,string> $row Row cells.
	 * @return array{name:string,sig:string}
	 */
	public static function entry_for( $row ) {
		return array(
			'name' => self::key_for( $row ),
			'sig'  => self::signature( $row ),
		);
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
	 * Clean a stored or submitted list of hidden rows.
	 *
	 * A plain string is accepted as a name with no signature, which is what
	 * sites saved before rows were recognised in full.
	 *
	 * @param mixed $entries Anything.
	 * @return array<int,array{name:string,sig:string}>
	 */
	public static function sanitize( $entries ) {
		if ( ! is_array( $entries ) ) {
			return array();
		}

		$clean = array();
		$seen  = array();

		foreach ( $entries as $entry ) {
			if ( is_string( $entry ) ) {
				$entry = array( 'name' => $entry );
			}

			if ( ! is_array( $entry ) ) {
				continue;
			}

			$name = isset( $entry['name'] ) && is_scalar( $entry['name'] )
				? self::tidy( sanitize_text_field( (string) $entry['name'] ) )
				: '';
			$sig  = isset( $entry['sig'] ) && is_scalar( $entry['sig'] )
				? strtolower( preg_replace( '/[^a-f0-9]/i', '', (string) $entry['sig'] ) )
				: '';

			if ( 32 !== strlen( $sig ) ) {
				$sig = '';
			}

			if ( '' === $name && '' === $sig ) {
				continue;
			}

			if ( '' !== $name ) {
				$name = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, self::MAX_KEY ) : substr( $name, 0, self::MAX_KEY );
			}

			$fingerprint = $name . '|' . $sig;

			if ( isset( $seen[ $fingerprint ] ) ) {
				continue;
			}

			$seen[ $fingerprint ] = true;
			$clean[]              = array(
				'name' => $name,
				'sig'  => $sig,
			);
		}

		return array_slice( $clean, 0, self::MAX_ROWS );
	}

	/**
	 * How many rows of a sheet answer to each name.
	 *
	 * @param array<int,array<int,string>> $rows Sheet rows.
	 * @return array<string,int>
	 */
	public static function name_counts( $rows ) {
		$counts = array();

		foreach ( (array) $rows as $row ) {
			$name = self::key_for( $row );

			if ( '' === $name ) {
				continue;
			}

			$counts[ $name ] = isset( $counts[ $name ] ) ? $counts[ $name ] + 1 : 1;
		}

		return $counts;
	}

	/**
	 * How many rows of a sheet are identical, cell for cell.
	 *
	 * The only case where one choice really does speak for several rows, and
	 * the only one worth warning about: rows that differ anywhere are told
	 * apart exactly, so ten products called "Kask" are ten separate choices.
	 *
	 * @param array<int,array<int,string>> $rows Sheet rows.
	 * @return array<string,int>
	 */
	public static function signature_counts( $rows ) {
		$counts = array();

		foreach ( (array) $rows as $row ) {
			$sig            = self::signature( $row );
			$counts[ $sig ] = isset( $counts[ $sig ] ) ? $counts[ $sig ] + 1 : 1;
		}

		return $counts;
	}

	/**
	 * Which rows of a sheet one stored choice now points at.
	 *
	 * Exactly the row it was made on, while that row is unchanged. If it has
	 * been edited, the row of that name — but only while there is just one, so
	 * an edit can never quietly hide a different product. Where a name has
	 * become ambiguous nothing is hidden at all: showing a row that should be
	 * hidden is a mistake someone can see, and hiding the wrong one is not.
	 *
	 * @param array{name:string,sig:string}  $entry Stored choice.
	 * @param array<int,array<int,string>>   $rows  Sheet rows.
	 * @param array<string,int>              $names How many rows carry each name.
	 * @return array<int,int> Row positions.
	 */
	public static function matches( $entry, $rows, $names ) {
		$found = array();

		if ( '' !== $entry['sig'] ) {
			foreach ( $rows as $index => $row ) {
				if ( self::signature( $row ) === $entry['sig'] ) {
					$found[] = $index;
				}
			}
		}

		if ( $found ) {
			return $found;
		}

		if ( '' === $entry['name'] || ! isset( $names[ $entry['name'] ] ) || 1 !== $names[ $entry['name'] ] ) {
			return array();
		}

		foreach ( $rows as $index => $row ) {
			if ( self::key_for( $row ) === $entry['name'] ) {
				$found[] = $index;
			}
		}

		return $found;
	}

	/**
	 * Choices that no longer point at anything, and why.
	 *
	 * @param array<int,array{name:string,sig:string}> $entries Stored choices.
	 * @param array<int,array<int,string>>             $rows    Sheet rows.
	 * @return array<int,array{name:string,reason:string}>
	 */
	public static function unresolved( $entries, $rows ) {
		$names   = self::name_counts( $rows );
		$stalled = array();

		foreach ( self::sanitize( $entries ) as $entry ) {
			if ( self::matches( $entry, $rows, $names ) ) {
				continue;
			}

			$stalled[] = array(
				'name'   => $entry['name'],
				'reason' => ( isset( $names[ $entry['name'] ] ) && $names[ $entry['name'] ] > 1 ) ? 'ambiguous' : 'missing',
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

		$drop = self::positions( $entries, array_values( (array) $rows ) );

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
	 * Every row position a set of choices points at.
	 *
	 * @param array<int,array{name:string,sig:string}> $entries Stored choices.
	 * @param array<int,array<int,string>>             $rows    Sheet rows.
	 * @return array<int,bool> Positions, keyed for lookup.
	 */
	public static function positions( $entries, $rows ) {
		$names = self::name_counts( $rows );
		$found = array();

		foreach ( self::sanitize( $entries ) as $entry ) {
			foreach ( self::matches( $entry, $rows, $names ) as $index ) {
				$found[ $index ] = true;
			}
		}

		return $found;
	}

	/**
	 * Whether one row of a sheet is hidden, for the admin screen.
	 *
	 * @param array<int,string>                        $row     Row cells.
	 * @param array<int,array{name:string,sig:string}> $entries Stored choices.
	 * @param array<int,array<int,string>>             $rows    Every row of the sheet.
	 * @return bool
	 */
	public static function is_hidden( $row, $entries, $rows = null ) {
		if ( null === $rows ) {
			$rows = array( $row );
		}

		$rows  = array_values( (array) $rows );
		$names = self::name_counts( $rows );

		foreach ( self::sanitize( $entries ) as $entry ) {
			foreach ( self::matches( $entry, $rows, $names ) as $index ) {
				if ( isset( $rows[ $index ] ) && $rows[ $index ] === $row ) {
					return true;
				}
			}
		}

		return false;
	}
}
