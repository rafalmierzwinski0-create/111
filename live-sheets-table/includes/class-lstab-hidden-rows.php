<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Hidden_Rows {
	const MAX_ROWS = 500;

	const MAX_KEY = 300;

	public function register() {
		add_filter( 'lstab_source_rows', array( __CLASS__, 'filter_rows' ), 5, 4 );
	}

	public static function key_for( $row ) {
		foreach ( (array) $row as $cell ) {
			$cell = self::tidy( (string) $cell );

			if ( '' !== $cell ) {
				return $cell;
			}
		}

		return '';
	}

	public static function signature( $row ) {
		$cells = array();

		foreach ( (array) $row as $cell ) {
			$cells[] = self::tidy( (string) $cell );
		}

		return md5( implode( "\x1F", $cells ) );
	}

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

	public static function entry_for( $row, $index ) {
		return array(
			'index' => max( 0, (int) $index ),
			'name'  => self::key_for( $row ),
			'sig'   => self::signature( $row ),
			'label' => self::describe( $row ),
		);
	}

	protected static function tidy( $value ) {
		$value = preg_replace( '/\s+/u', ' ', (string) $value );

		return trim( (string) $value );
	}

	protected static function clip( $value ) {
		$value = self::tidy( sanitize_text_field( (string) $value ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::MAX_KEY ) : substr( $value, 0, self::MAX_KEY );
	}

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

	public static function still_there( $entry, $rows ) {
		if ( ! isset( $rows[ $entry['index'] ] ) || '' === $entry['sig'] ) {
			return false;
		}

		return self::signature( $rows[ $entry['index'] ] ) === $entry['sig'];
	}

	public static function line_for( $source, $index ) {
		$offset = isset( $source['data']['offset'] ) ? (int) $source['data']['offset'] : 0;

		return $offset + (int) $index + 1;
	}

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
