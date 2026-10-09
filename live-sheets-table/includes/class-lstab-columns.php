<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Columns {
	public static function sanitize( $raw ) {
		$clean = array();

		foreach ( (array) $raw as $index => $column ) {
			if ( ! is_numeric( $index ) || ! is_array( $column ) ) {
				continue;
			}

			$hidden = array_key_exists( 'visible', $column )
				? empty( $column['visible'] )
				: ! empty( $column['hidden'] );

			$clean[ (int) $index ] = array(
				'label'  => isset( $column['label'] ) ? sanitize_text_field( (string) $column['label'] ) : '',
				'hidden' => $hidden,
				'detail' => ! empty( $column['detail'] ),
				'source' => isset( $column['source'] ) ? sanitize_text_field( (string) $column['source'] ) : '',
			);
		}

		ksort( $clean );

		return $clean;
	}

	public static function reconcile( $config, $headers ) {
		$config  = self::sanitize( $config );
		$headers = array_map( 'sanitize_text_field', array_map( 'strval', array_values( (array) $headers ) ) );
		$updated = array();

		foreach ( $headers as $index => $heading ) {
			$existing = isset( $config[ $index ] ) ? $config[ $index ] : array(
				'label'  => '',
				'hidden' => false,
				'detail' => false,
				'source' => '',
			);

			$configured = '' !== $existing['label'] || $existing['hidden'] || ! empty( $existing['detail'] );

			$updated[ $index ] = array(
				'label'  => $existing['label'],
				'hidden' => $existing['hidden'],
				'detail' => ! empty( $existing['detail'] ),
				'source' => $configured ? $existing['source'] : $heading,
			);
		}

		return $updated;
	}

	public static function is_same( $heading, $source ) {
		return sanitize_text_field( (string) $heading ) === (string) $source;
	}

	public static function letter( $index ) {
		$index  = max( 0, (int) $index );
		$letter = '';

		do {
			$letter = chr( 65 + ( $index % 26 ) ) . $letter;
			$index  = intdiv( $index, 26 ) - 1;
		} while ( $index >= 0 );

		return $letter;
	}

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

			$now = isset( $headers[ $index ] ) ? $headers[ $index ] : '';

			if ( self::is_same( $now, $column['source'] ) ) {
				continue;
			}

			$orphaned[] = array(
				'was'    => $column['source'],
				'now'    => $now,
				'hidden' => (bool) $column['hidden'],
				'label'  => $column['label'],
				'letter' => self::letter( $index ),
			);
		}

		return $orphaned;
	}

	public static function drift( $config, $headers ) {
		$config  = self::sanitize( $config );
		$headers = array_values( (array) $headers );
		$drifted = array();

		foreach ( $config as $index => $column ) {
			if ( '' === $column['source'] || ( '' === $column['label'] && ! $column['hidden'] ) ) {
				continue;
			}

			$now = isset( $headers[ $index ] ) ? (string) $headers[ $index ] : '';

			if ( ! self::is_same( $now, $column['source'] ) ) {
				$drifted[] = array(
					'index' => (int) $index,
					'was'   => $column['source'],
					'now'   => $now,
				);
			}
		}

		return $drifted;
	}

	public static function kept( $headers, $config ) {
		$headers = array_values( (array) $headers );
		$all     = array_keys( $headers );
		$config  = self::sanitize( $config );

		if ( ! $config || ! LSTAB_Limits::pro_effective() ) {
			return $all;
		}

		$keep = array();

		foreach ( $headers as $index => $heading ) {
			$column  = isset( $config[ $index ] ) ? $config[ $index ] : null;
			$matches = $column && ( '' === $column['source'] || self::is_same( $heading, $column['source'] ) );

			if ( $matches && $column['hidden'] ) {
				continue;
			}

			$keep[] = $index;
		}

		return $keep ? $keep : $all;
	}

	public static function apply( $data, $config ) {
		$config = self::sanitize( $config );

		if ( ! $config ) {
			return $data;
		}

		$headers = isset( $data['headers'] ) ? array_values( (array) $data['headers'] ) : array();
		$rows    = isset( $data['rows'] ) ? (array) $data['rows'] : array();

		$keep    = array();
		$labels  = array();
		$details = array();

		$may_detail = LSTAB_Limits::pro_effective();

		$may_hide = LSTAB_Limits::pro_effective();

		foreach ( $headers as $index => $heading ) {
			$column = isset( $config[ $index ] ) ? $config[ $index ] : null;

			$matches = $column && ( '' === $column['source'] || self::is_same( $heading, $column['source'] ) );

			if ( $matches && $column['hidden'] && $may_hide ) {
				continue;
			}

			$keep[]   = $index;
			$labels[] = ( $matches && '' !== $column['label'] ) ? $column['label'] : (string) $heading;

			if ( $matches && ! empty( $column['detail'] ) && $may_detail ) {
				$details[] = count( $keep ) - 1;
			}
		}

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

		if ( count( $details ) >= count( $keep ) ) {
			$details = array();
		}

		return array(
			'headers' => $labels,
			'rows'    => $filtered,
			'details' => $details,
		);
	}
}
