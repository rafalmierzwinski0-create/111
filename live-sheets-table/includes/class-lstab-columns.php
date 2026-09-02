<?php
/**
 * Per-source column settings: display labels and visibility.
 *
 * Columns are addressed by position, never by their heading. Renaming a column
 * here changes only what visitors see; the spreadsheet is never written to, so
 * a heading in Google can be changed, or contain a working name nobody should
 * read, without any conflict.
 *
 * Because position is the key, the headings seen at the last sync are stored
 * alongside, so an inserted or removed column can be reported rather than
 * silently shifting every label one place to the left.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Column configuration.
 */
class LSTAB_Columns {

	/**
	 * Clean a submitted or stored configuration.
	 *
	 * @param mixed $raw Raw configuration.
	 * @return array<int,array{label:string,hidden:bool,source:string}>
	 */
	public static function sanitize( $raw ) {
		$clean = array();

		foreach ( (array) $raw as $index => $column ) {
			if ( ! is_numeric( $index ) || ! is_array( $column ) ) {
				continue;
			}

			// The form asks "include this column?", because a checkbox reads
			// better as an opt-in than as an opt-out. Storage keeps 'hidden',
			// so an absent key means visible rather than accidentally hidden.
			$hidden = array_key_exists( 'visible', $column )
				? empty( $column['visible'] )
				: ! empty( $column['hidden'] );

			$clean[ (int) $index ] = array(
				'label'  => isset( $column['label'] ) ? sanitize_text_field( (string) $column['label'] ) : '',
				'hidden' => $hidden,
				'source' => isset( $column['source'] ) ? sanitize_text_field( (string) $column['source'] ) : '',
			);
		}

		ksort( $clean );

		return $clean;
	}

	/**
	 * Record the headings a sync actually returned.
	 *
	 * Existing labels and visibility are kept; only the remembered source
	 * heading is refreshed, so drift can be detected on the next look.
	 *
	 * @param array<int,array<string,mixed>> $config  Stored configuration.
	 * @param array<int,string>              $headers Headings from the sheet.
	 * @return array<int,array<string,mixed>>
	 */
	public static function reconcile( $config, $headers ) {
		$config   = self::sanitize( $config );
		$headers  = array_map( 'sanitize_text_field', array_map( 'strval', array_values( (array) $headers ) ) );
		$blank    = array(
			'label'  => '',
			'hidden' => false,
			'source' => '',
		);

		/*
		 * Settings follow their heading, not their place in the row.
		 *
		 * Keeping them by position looks harmless and is not: someone adding a
		 * column in Google shifts every setting one place along, so the column
		 * left out of the table becomes a different column — and the one that
		 * was meant to be private is published. Nothing about the page looks
		 * wrong, which is what makes it worth this much care.
		 *
		 * A heading is only followed while it is unambiguous: one setting
		 * remembering it, one column in the sheet carrying it. Anything else
		 * falls back to position, which is no worse than it ever was.
		 */
		$remembered = array();

		foreach ( $config as $index => $column ) {
			if ( '' === $column['source'] ) {
				continue;
			}

			$remembered[ $column['source'] ][] = $index;
		}

		$heading_counts = array_count_values( $headers );
		$claimed        = array();
		$updated        = array();

		// First pass: every heading that can be recognised takes its own back.
		foreach ( $headers as $index => $heading ) {
			if ( '' === $heading || ! isset( $remembered[ $heading ] ) ) {
				continue;
			}

			if ( 1 !== count( $remembered[ $heading ] ) || 1 !== $heading_counts[ $heading ] ) {
				continue;
			}

			$from = $remembered[ $heading ][0];

			$claimed[ $from ]  = true;
			$updated[ $index ] = array(
				'label'  => $config[ $from ]['label'],
				'hidden' => $config[ $from ]['hidden'],
				'source' => $heading,
			);
		}

		// Second pass: whatever is left keeps the place it had.
		foreach ( $headers as $index => $heading ) {
			if ( isset( $updated[ $index ] ) ) {
				continue;
			}

			$existing = ( isset( $config[ $index ] ) && ! isset( $claimed[ $index ] ) ) ? $config[ $index ] : $blank;

			$updated[ $index ] = array(
				'label'  => $existing['label'],
				'hidden' => $existing['hidden'],
				'source' => $heading,
			);
		}

		ksort( $updated );

		return $updated;
	}

	/**
	 * A column's letter, as Google labels it.
	 *
	 * @param int $index Column position, counting from zero.
	 * @return string
	 */
	public static function letter( $index ) {
		$index  = max( 0, (int) $index );
		$letter = '';

		do {
			$letter = chr( 65 + ( $index % 26 ) ) . $letter;
			$index  = intdiv( $index, 26 ) - 1;
		} while ( $index >= 0 );

		return $letter;
	}

	/**
	 * Settings whose heading is nowhere in the sheet any more.
	 *
	 * Only the ones that were doing something: a renamed column that had been
	 * left out of the table is now in it, and a renamed column that had been
	 * relabelled is showing the sheet's own heading. Both are worth telling
	 * someone about; a column nobody had configured is not.
	 *
	 * @param array<int,array<string,mixed>> $config  Stored configuration.
	 * @param array<int,string>              $headers Headings from the sheet.
	 * @return array<int,array{was:string,hidden:bool,label:string}>
	 */
	public static function orphans( $config, $headers ) {
		$config   = self::sanitize( $config );
		$headers  = array_map( 'strval', array_values( (array) $headers ) );
		$orphaned = array();

		foreach ( $config as $index => $column ) {
			if ( '' === $column['source'] ) {
				continue;
			}

			if ( '' === $column['label'] && ! $column['hidden'] ) {
				continue;
			}

			if ( in_array( $column['source'], $headers, true ) ) {
				continue;
			}

			$orphaned[] = array(
				'was'    => $column['source'],
				'hidden' => (bool) $column['hidden'],
				'label'  => $column['label'],
				'letter' => self::letter( $index ),
			);
		}

		return $orphaned;
	}

	/**
	 * Columns whose heading no longer matches what was configured.
	 *
	 * @param array<int,array<string,mixed>> $config  Stored configuration.
	 * @param array<int,string>              $headers Headings from the sheet.
	 * @return array<int,array{index:int,was:string,now:string}>
	 */
	public static function drift( $config, $headers ) {
		$config  = self::sanitize( $config );
		$headers = array_values( (array) $headers );
		$drifted = array();

		foreach ( $config as $index => $column ) {
			// Only a remembered heading can drift, and only where a label was set:
			// an untouched column has nothing to point at the wrong data.
			if ( '' === $column['source'] || ( '' === $column['label'] && ! $column['hidden'] ) ) {
				continue;
			}

			$now = isset( $headers[ $index ] ) ? (string) $headers[ $index ] : '';

			if ( $now !== $column['source'] ) {
				$drifted[] = array(
					'index' => (int) $index,
					'was'   => $column['source'],
					'now'   => $now,
				);
			}
		}

		return $drifted;
	}

	/**
	 * Apply the configuration to a parsed table.
	 *
	 * @param array{headers:array,rows:array} $data   Parsed table.
	 * @param array<int,array<string,mixed>>  $config Stored configuration.
	 * @return array{headers:array,rows:array}
	 */
	public static function apply( $data, $config ) {
		$config = self::sanitize( $config );

		if ( ! $config ) {
			return $data;
		}

		$headers = isset( $data['headers'] ) ? array_values( (array) $data['headers'] ) : array();
		$rows    = isset( $data['rows'] ) ? (array) $data['rows'] : array();

		$keep    = array();
		$labels  = array();

		// Leaving a column out is the add-on's to decide, and is honoured for a
		// grace period after it stops so that a lapsed licence does not
		// rearrange a public page the same day. Renaming stays free.
		$may_hide = LSTAB_Limits::pro_effective();

		foreach ( $headers as $index => $heading ) {
			$column = isset( $config[ $index ] ) ? $config[ $index ] : null;

			if ( $column && $column['hidden'] && $may_hide ) {
				continue;
			}

			$keep[]   = $index;
			$labels[] = ( $column && '' !== $column['label'] ) ? $column['label'] : (string) $heading;
		}

		// Refuse to render nothing: hiding every column is a configuration
		// mistake, and an empty table helps nobody diagnose it.
		if ( ! $keep ) {
			return $data;
		}

		$filtered = array();
		foreach ( $rows as $row ) {
			$row     = array_values( (array) $row );
			$trimmed = array();
			foreach ( $keep as $index ) {
				$trimmed[] = isset( $row[ $index ] ) ? $row[ $index ] : '';
			}
			$filtered[] = $trimmed;
		}

		return array(
			'headers' => $labels,
			'rows'    => $filtered,
		);
	}
}
