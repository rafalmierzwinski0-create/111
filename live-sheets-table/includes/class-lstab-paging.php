<?php
/**
 * Pages, searching and sorting for tables too long to send at once.
 *
 * All three happen on the server, over the whole stored sheet, before a single
 * row is written into the page. That is the only arrangement in which they can
 * be trusted: filtering the rows the browser happens to be holding would search
 * one page and call it the table.
 *
 * Every control is an ordinary link or form, so a page of a table has its own
 * address, can be linked to, opened in a new tab, indexed, and used with no
 * JavaScript at all.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Server-side paging.
 */
class LSTAB_Paging {

	/**
	 * Largest page size an author may ask for.
	 *
	 * Past this the page is heavy enough that paging has stopped helping.
	 */
	const MAX_PER_PAGE = 500;

	/**
	 * What each table on this page worked out, keyed by source ID.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	protected static $state = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		/*
		 * Between the row filter at 10 and the conditional formatting that
		 * reads row positions at 20. Paging reorders and drops rows, so
		 * anything keyed by position has to see the result, not the input.
		 */
		add_filter( 'lstab_source_rows', array( __CLASS__, 'filter_rows' ), 15, 4 );
	}

	/**
	 * Cut the sheet down to the page being asked for.
	 *
	 * @param array<int,array<int,string>> $rows    Body rows.
	 * @param array<int,string>            $headers Sheet headings.
	 * @param array<string,mixed>          $source  Source row.
	 * @param array<string,mixed>          $args    Rendering options.
	 * @return array<int,array<int,string>>
	 */
	public static function filter_rows( $rows, $headers, $source, $args ) {
		$source_id = isset( $source['id'] ) ? (int) $source['id'] : 0;
		$per_page  = self::per_page( $source, $args );

		unset( self::$state[ $source_id ] );

		// A preview has no address to carry a page number in.
		if ( $source_id <= 0 || $per_page <= 0 ) {
			return $rows;
		}

		$result = self::apply( $rows, $source_id, $per_page, self::visible_columns( $headers, $source, $args ) );

		self::$state[ $source_id ] = $result;

		return $result['rows'];
	}

	/**
	 * Rows per page for one table, or zero when it is not paged.
	 *
	 * @param array<string,mixed> $source Source row.
	 * @param array<string,mixed> $args   Rendering options.
	 * @return int
	 */
	protected static function per_page( $source, $args ) {
		if ( isset( $args['per_page'] ) && '' !== $args['per_page'] && null !== $args['per_page'] ) {
			return max( 0, (int) $args['per_page'] );
		}

		return isset( $source['per_page'] ) ? max( 0, (int) $source['per_page'] ) : 0;
	}

	/**
	 * Which column positions the visitor will actually be shown.
	 *
	 * Searching the rest would let someone find, by guessing, the contents of
	 * a column the author deliberately left out of the table.
	 *
	 * @param array<int,string>   $headers Sheet headings.
	 * @param array<string,mixed> $source  Source row.
	 * @param array<string,mixed> $args    Rendering options.
	 * @return array<int,int>
	 */
	protected static function visible_columns( $headers, $source, $args ) {
		$config = ( isset( $args['columns'] ) && null !== $args['columns'] )
			? (array) $args['columns']
			: ( isset( $source['columns_config'] ) ? (array) $source['columns_config'] : array() );

		$visible = array();

		foreach ( array_values( (array) $headers ) as $index => $heading ) {
			if ( $config && ! empty( $config[ $index ]['hidden'] ) ) {
				continue;
			}

			$visible[] = $index;
		}

		return $visible;
	}

	/**
	 * What one table worked out for this request.
	 *
	 * @param int $source_id Source ID.
	 * @return array<string,mixed>|null
	 */
	public static function state( $source_id ) {
		$source_id = (int) $source_id;

		return isset( self::$state[ $source_id ] ) ? self::$state[ $source_id ] : null;
	}

	/**
	 * Name of one query argument for one table.
	 *
	 * Several tables can share a page, so each one answers to its own
	 * arguments rather than fighting over "page".
	 *
	 * @param int    $source_id Source ID.
	 * @param string $name      Argument, one of q, page, sort, dir.
	 * @return string
	 */
	public static function arg( $source_id, $name ) {
		return 'lstab-' . $name . '-' . (int) $source_id;
	}

	/**
	 * What the current request asks of one table.
	 *
	 * @param int $source_id Source ID.
	 * @return array{q:string,page:int,sort:int,dir:string}
	 */
	public static function request( $source_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation of public data.
		$q    = isset( $_GET[ self::arg( $source_id, 'q' ) ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::arg( $source_id, 'q' ) ] ) ) : '';
		$page = isset( $_GET[ self::arg( $source_id, 'page' ) ] ) ? absint( wp_unslash( $_GET[ self::arg( $source_id, 'page' ) ] ) ) : 1;
		$sort = isset( $_GET[ self::arg( $source_id, 'sort' ) ] ) ? (int) wp_unslash( $_GET[ self::arg( $source_id, 'sort' ) ] ) : -1;
		$dir  = isset( $_GET[ self::arg( $source_id, 'dir' ) ] ) ? sanitize_key( wp_unslash( $_GET[ self::arg( $source_id, 'dir' ) ] ) ) : 'asc';
		// phpcs:enable

		return array(
			'q'    => $q,
			'page' => max( 1, $page ),
			'sort' => $sort < 0 ? -1 : $sort,
			'dir'  => 'desc' === $dir ? 'desc' : 'asc',
		);
	}

	/**
	 * Narrow, order and cut a sheet down to one page of it.
	 *
	 * @param array<int,array<int,string>> $rows      Every row of the sheet.
	 * @param int                          $source_id Source ID.
	 * @param int                          $per_page  Rows per page.
	 * @return array{rows:array,total:int,matched:int,page:int,pages:int,request:array}
	 */
	public static function apply( $rows, $source_id, $per_page, $columns = null ) {
		$rows     = array_values( (array) $rows );
		$total    = count( $rows );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) $per_page ) );
		$request  = self::request( $source_id );

		if ( '' !== $request['q'] ) {
			$rows = self::search( $rows, $request['q'], $columns );
		}

		if ( $request['sort'] >= 0 ) {
			$rows = self::sort( $rows, $request['sort'], $request['dir'] );
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

	/**
	 * Rows holding the search term anywhere in them.
	 *
	 * @param array<int,array<int,string>> $rows  Rows.
	 * @param string                       $query Search term.
	 * @return array<int,array<int,string>>
	 */
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

	/**
	 * Case-insensitive form of a string, diacritics and all.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	protected static function fold( $text ) {
		$text = (string) $text;

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Rows ordered by one column.
	 *
	 * Numbers are compared as numbers, so 1 215,50 sorts above 349,00 rather
	 * than below it — the same rule the in-page sorting follows.
	 *
	 * @param array<int,array<int,string>> $rows   Rows.
	 * @param int                          $column Column index.
	 * @param string                       $dir    asc or desc.
	 * @return array<int,array<int,string>>
	 */
	protected static function sort( $rows, $column, $dir ) {
		$numeric = true;

		foreach ( $rows as $row ) {
			$value = isset( $row[ $column ] ) ? (string) $row[ $column ] : '';

			if ( '' !== trim( $value ) && ! LSTAB_Renderer::looks_numeric( $value ) ) {
				$numeric = false;
				break;
			}
		}

		usort(
			$rows,
			function ( $a, $b ) use ( $column, $numeric ) {
				$left  = isset( $a[ $column ] ) ? (string) $a[ $column ] : '';
				$right = isset( $b[ $column ] ) ? (string) $b[ $column ] : '';

				if ( $numeric ) {
					$ln = LSTAB_Renderer::to_number( $left );
					$rn = LSTAB_Renderer::to_number( $right );

					if ( $ln === $rn ) {
						return 0;
					}

					return $ln < $rn ? -1 : 1;
				}

				return strnatcasecmp( $left, $right );
			}
		);

		return 'desc' === $dir ? array_reverse( $rows ) : $rows;
	}

	/**
	 * Query arguments a search form has to carry so it does not lose them.
	 *
	 * A form submits only its own fields, so everything else already in the
	 * address — the page a visitor came from, another table's page number —
	 * would be dropped without this.
	 *
	 * @param int $source_id Source ID being searched.
	 * @return array<string,string>
	 */
	public static function carried_fields( $source_id ) {
		$carried = array();
		$own     = array( self::arg( $source_id, 'q' ), self::arg( $source_id, 'page' ) );

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

	/**
	 * The current address with some of this table's arguments changed.
	 *
	 * Other tables' arguments are left alone, so paging one does not reset
	 * its neighbour.
	 *
	 * @param int                  $source_id Source ID.
	 * @param array<string,mixed>  $changes   Argument name to value, null to drop.
	 * @return string
	 */
	public static function url( $source_id, $changes ) {
		$base = remove_query_arg( 'unused' );

		foreach ( $changes as $name => $value ) {
			$arg = self::arg( $source_id, $name );

			$base = ( null === $value || '' === $value )
				? remove_query_arg( $arg, $base )
				: add_query_arg( $arg, rawurlencode( (string) $value ), $base );
		}

		// A page of a table is a place on the page, not the top of it.
		return $base . '#lstab-table-' . (int) $source_id;
	}
}
