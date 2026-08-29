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
		$config  = self::sanitize( $config );
		$updated = array();

		foreach ( array_values( (array) $headers ) as $index => $heading ) {
			$existing = isset( $config[ $index ] ) ? $config[ $index ] : array(
				'label'  => '',
				'hidden' => false,
				'source' => '',
			);

			$updated[ $index ] = array(
				'label'  => $existing['label'],
				'hidden' => $existing['hidden'],
				'source' => sanitize_text_field( (string) $heading ),
			);
		}

		return $updated;
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

		foreach ( $headers as $index => $heading ) {
			$column = isset( $config[ $index ] ) ? $config[ $index ] : null;

			if ( $column && $column['hidden'] ) {
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
