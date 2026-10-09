<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Paging {
	const MAX_PER_PAGE = 500;

	const AUTO_THRESHOLD = 200;

	const AUTO_PER_PAGE = 50;

	const DECLINED_OPT = 'lstab_paging_declined';

	public static function auto_threshold() {
		return max( 1, (int) apply_filters( 'lstab_auto_paging_threshold', self::AUTO_THRESHOLD ) );
	}

	public static function auto_per_page() {
		$rows = (int) apply_filters( 'lstab_auto_paging_per_page', self::AUTO_PER_PAGE );

		return min( self::MAX_PER_PAGE, max( 1, $rows ) );
	}

	public static function is_long( $source ) {
		return isset( $source['row_count'] ) && (int) $source['row_count'] >= self::auto_threshold();
	}

	public static function should_offer( $source ) {
		if ( empty( $source['id'] ) || ! empty( $source['per_page'] ) || ! self::is_long( $source ) ) {
			return false;
		}

		return ! in_array( (int) $source['id'], self::declined(), true );
	}

	public static function declined() {
		return array_map( 'absint', (array) get_option( self::DECLINED_OPT, array() ) );
	}

	public static function decline( $source_id ) {
		$declined = self::declined();

		if ( ! in_array( (int) $source_id, $declined, true ) ) {
			$declined[] = (int) $source_id;
			update_option( self::DECLINED_OPT, $declined, true );
		}
	}

	public static function forget( $source_id ) {
		$declined = array_values( array_diff( self::declined(), array( (int) $source_id ) ) );
		update_option( self::DECLINED_OPT, $declined, true );
	}

	protected static $state = array();

	public function register() {
		add_filter( 'lstab_source_rows', array( __CLASS__, 'filter_rows' ), 15, 4 );

		add_action( 'lstab_source_deleted', array( __CLASS__, 'forget' ) );
	}

	public static function filter_rows( $rows, $headers, $source, $args ) {
		$source_id = isset( $source['id'] ) ? (int) $source['id'] : 0;
		$per_page  = self::per_page( $source, $args );

		unset( self::$state[ $source_id ] );

		if ( $source_id <= 0 || $per_page <= 0 ) {
			return $rows;
		}

		$ranks = (array) apply_filters( 'lstab_sort_ranks', array(), $headers, $source, $args );

		$result = self::apply( $rows, $source_id, $per_page, self::visible_columns( $headers, $source, $args ), $ranks );

		self::$state[ $source_id ] = $result;

		return $result['rows'];
	}

	protected static function per_page( $source, $args ) {
		if ( isset( $args['per_page'] ) && '' !== $args['per_page'] && null !== $args['per_page'] ) {
			return max( 0, (int) $args['per_page'] );
		}

		return isset( $source['per_page'] ) ? max( 0, (int) $source['per_page'] ) : 0;
	}

	protected static function visible_columns( $headers, $source, $args ) {
		$config = ( isset( $args['columns'] ) && null !== $args['columns'] )
			? (array) $args['columns']
			: ( isset( $source['columns_config'] ) ? (array) $source['columns_config'] : array() );

		return LSTAB_Columns::kept( $headers, $config );
	}

	public static function state( $source_id ) {
		$source_id = (int) $source_id;

		return isset( self::$state[ $source_id ] ) ? self::$state[ $source_id ] : null;
	}

	protected static $drawn = array();

	protected static $current = array();

	public static function begin_instance( $source_id, $args = array() ) {
		$source_id = (int) $source_id;
		$filter    = isset( $args['filter'] ) ? strtolower( trim( (string) $args['filter'] ) ) : '';
		$per_page  = isset( $args['per_page'] ) && null !== $args['per_page'] && '' !== $args['per_page'] ? (string) (int) $args['per_page'] : '';

		self::$drawn[ $source_id ] = ( isset( self::$drawn[ $source_id ] ) ? self::$drawn[ $source_id ] : 0 ) + 1;

		self::$current[ $source_id ] = array(
			'key'    => ( '' === $filter && '' === $per_page ) ? '' : substr( md5( $filter . '|' . $per_page ), 0, 6 ),
			'number' => self::$drawn[ $source_id ],
		);
	}

	public static function end_instance( $source_id ) {
		unset( self::$current[ (int) $source_id ] );
	}

	public static function suffix( $source_id ) {
		$source_id = (int) $source_id;

		return empty( self::$current[ $source_id ]['key'] ) ? '' : '-' . self::$current[ $source_id ]['key'];
	}

	public static function element_suffix( $source_id ) {
		$source_id = (int) $source_id;
		$number    = isset( self::$current[ $source_id ]['number'] ) ? (int) self::$current[ $source_id ]['number'] : 1;

		return $number > 1 ? '-' . $number : '';
	}

	public static function arg( $source_id, $name ) {
		return 'lstab-' . $name . '-' . (int) $source_id . self::suffix( $source_id );
	}

	public static function request( $source_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation of public data.
		$q    = isset( $_GET[ self::arg( $source_id, 'q' ) ] ) ? html_entity_decode( sanitize_text_field( wp_unslash( $_GET[ self::arg( $source_id, 'q' ) ] ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
		$page = isset( $_GET[ self::arg( $source_id, 'page' ) ] ) ? absint( wp_unslash( $_GET[ self::arg( $source_id, 'page' ) ] ) ) : 1;
		$sort = isset( $_GET[ self::arg( $source_id, 'sort' ) ] ) ? intval( wp_unslash( $_GET[ self::arg( $source_id, 'sort' ) ] ) ) : -1;
		$dir  = isset( $_GET[ self::arg( $source_id, 'dir' ) ] ) ? sanitize_key( wp_unslash( $_GET[ self::arg( $source_id, 'dir' ) ] ) ) : 'asc';
		// phpcs:enable

		return array(
			'q'    => $q,
			'page' => max( 1, $page ),
			'sort' => $sort < 0 ? -1 : $sort,
			'dir'  => 'desc' === $dir ? 'desc' : 'asc',
		);
	}

	public static function apply( $rows, $source_id, $per_page, $columns = null, $ranks = array() ) {
		$rows     = array_values( (array) $rows );
		$total    = count( $rows );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) $per_page ) );
		$request  = self::request( $source_id );

		if ( '' !== $request['q'] ) {
			$rows = self::search( $rows, $request['q'], $columns );
		}

		$sort = $request['sort'];

		if ( $sort >= 0 && is_array( $columns ) ) {
			$sort = isset( $columns[ $sort ] ) ? (int) $columns[ $sort ] : -1;
		}

		if ( $sort >= 0 ) {
			$rows = self::sort(
				$rows,
				$sort,
				$request['dir'],
				isset( $ranks[ $sort ] ) ? (array) $ranks[ $sort ] : array()
			);
		}

		$matched = count( $rows );
		$pages   = max( 1, (int) ceil( $matched / $per_page ) );
		$page    = min( $request['page'], $pages );

		return array(
			'rows'    => array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ),
			'total'   => $total,
			'matched' => $matched,
			'page'    => $page,
			'pages'   => $pages,
			'request' => $request,
		);
	}

	protected static function search( $rows, $query, $columns = null ) {
		$needle = self::fold( $query );

		if ( '' === $needle ) {
			return $rows;
		}

		$found = array();

		foreach ( $rows as $row ) {
			$row = (array) $row;

			if ( is_array( $columns ) ) {
				$searchable = array();
				foreach ( $columns as $index ) {
					$searchable[] = isset( $row[ $index ] ) ? $row[ $index ] : '';
				}
			} else {
				$searchable = $row;
			}

			if ( false !== strpos( self::fold( implode( ' ', $searchable ) ), $needle ) ) {
				$found[] = $row;
			}
		}

		return $found;
	}

	protected static function fold( $text ) {
		$text = (string) $text;

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	protected static function sort( $rows, $column, $dir, $ranks = array() ) {
		$direction = 'desc' === $dir ? -1 : 1;
		$ranks     = (array) $ranks;

		usort(
			$rows,
			function ( $a, $b ) use ( $column, $direction, $ranks ) {
				$left  = isset( $a[ $column ] ) ? trim( (string) $a[ $column ] ) : '';
				$right = isset( $b[ $column ] ) ? trim( (string) $b[ $column ] ) : '';

				if ( '' === $left && '' === $right ) {
					return 0;
				}

				if ( '' === $left ) {
					return 1;
				}

				if ( '' === $right ) {
					return -1;
				}

				if ( $ranks ) {
					$left_key    = self::fold( $left );
					$right_key   = self::fold( $right );
					$left_place  = isset( $ranks[ $left_key ] ) ? (int) $ranks[ $left_key ] : null;
					$right_place = isset( $ranks[ $right_key ] ) ? (int) $ranks[ $right_key ] : null;

					if ( null !== $left_place || null !== $right_place ) {
						if ( null === $left_place || null === $right_place ) {
							return ( null === $left_place ? 1 : -1 ) * $direction;
						}

						if ( $left_place !== $right_place ) {
							return ( $left_place < $right_place ? -1 : 1 ) * $direction;
						}
					}
				}

				$left_moment  = LSTAB_Renderer::to_moment( $left );
				$right_moment = LSTAB_Renderer::to_moment( $right );

				if ( null !== $left_moment || null !== $right_moment ) {
					$left_rank  = LSTAB_Renderer::moment_rank( $left_moment );
					$right_rank = LSTAB_Renderer::moment_rank( $right_moment );

					if ( $left_rank !== $right_rank ) {
						return ( $left_rank < $right_rank ? -1 : 1 ) * $direction;
					}

					if ( null !== $left_moment && null !== $right_moment ) {
						if ( $left_moment['value'] === $right_moment['value'] ) {
							return 0;
						}

						return ( $left_moment['value'] < $right_moment['value'] ? -1 : 1 ) * $direction;
					}
				}

				if ( LSTAB_Renderer::looks_numeric( $left ) && LSTAB_Renderer::looks_numeric( $right ) ) {
					$ln = LSTAB_Renderer::to_number( $left );
					$rn = LSTAB_Renderer::to_number( $right );

					if ( $ln === $rn ) {
						return 0;
					}

					return ( $ln < $rn ? -1 : 1 ) * $direction;
				}

				return strnatcasecmp( $left, $right ) * $direction;
			}
		);

		return $rows;
	}

	public static function carried_fields( $source_id ) {
		$carried = array();
		$own     = array( self::arg( $source_id, 'q' ), self::arg( $source_id, 'page' ), 'lstab-copy' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation of public data.
		foreach ( (array) $_GET as $name => $value ) {
			$name = sanitize_text_field( (string) $name );

			if ( '' === $name || in_array( $name, $own, true ) || ! is_scalar( $value ) ) {
				continue;
			}

			$carried[ $name ] = sanitize_text_field( wp_unslash( (string) $value ) );
		}

		return $carried;
	}

	public static function url( $source_id, $changes ) {
		$base = remove_query_arg( 'lstab-copy' );

		foreach ( $changes as $name => $value ) {
			$arg = self::arg( $source_id, $name );

			$base = ( null === $value || '' === $value )
				? remove_query_arg( $arg, $base )
				: add_query_arg( $arg, rawurlencode( (string) $value ), $base );
		}

		return $base . '#lstab-table-' . (int) $source_id . self::element_suffix( $source_id );
	}
}
