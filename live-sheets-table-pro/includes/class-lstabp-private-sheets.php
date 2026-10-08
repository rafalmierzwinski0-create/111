<?php
/**
 * Reading sheets that are not shared publicly.
 *
 * The free plugin downloads a sheet's public CSV export, which needs the sheet
 * shared as "anyone with the link". That is fine for a price list and useless
 * for anything a business would rather not hand out: buying prices, stock,
 * client lists, staff data.
 *
 * This routes the same download through the Sheets API using the connected
 * account instead, so the spreadsheet can stay entirely private. It reaches the
 * free plugin only through lstab_fetch_url and lstab_fetch_args.
 *
 * @package LiveSheetsTablePro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Private sheet support.
 */
class LSTABP_Private_Sheets {

	const META_OPTION = 'lstabp_private_sources';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'lstab_fetch_url', array( $this, 'maybe_authenticated_url' ), 10, 4 );
		add_filter( 'lstab_fetch_args', array( $this, 'maybe_authorise' ), 10, 2 );

		// The fetch filters see a URL, not a source, so note which source the
		// sync is working on before it starts.
		add_action( 'lstab_before_sync', array( __CLASS__, 'remember_source' ) );

		add_action( 'lstab_source_deleted', array( __CLASS__, 'forget' ) );
	}

	/**
	 * Drop a deleted table from the private list.
	 *
	 * @param int $source_id Source ID.
	 * @return void
	 */
	public static function forget( $source_id ) {
		$stored = (array) get_option( self::META_OPTION, array() );

		if ( isset( $stored[ (int) $source_id ] ) ) {
			unset( $stored[ (int) $source_id ] );
			update_option( self::META_OPTION, $stored, false );
		}
	}

	/**
	 * Sources the site owner marked as private.
	 *
	 * Kept in Pro's own option rather than the free plugin's table: the free
	 * schema should not carry columns that only mean something here.
	 *
	 * A mark belongs to one table, not to its number. Numbers start again from
	 * 1 once every table has been deleted, and a table can be deleted while Pro
	 * is switched off and cannot hear about it. So each mark holds the moment
	 * its table was created, and only counts for a table that still exists and
	 * was created at that moment: a new table that happens to get an old
	 * number starts out public, as every new table does. Marks saved before
	 * this was done hold `true` and count for whichever table has the number.
	 *
	 * @return array<int,bool>
	 */
	public static function private_sources() {
		$stored = (array) get_option( self::META_OPTION, array() );

		if ( ! $stored ) {
			return array();
		}

		$private = array();

		foreach ( LSTAB_Storage::get_all() as $source ) {
			$id = (int) $source['id'];

			if ( ! isset( $stored[ $id ] ) ) {
				continue;
			}

			if ( true === $stored[ $id ] || (string) $stored[ $id ] === (string) $source['created_gmt'] ) {
				$private[ $id ] = true;
			}
		}

		return $private;
	}

	/**
	 * Mark a source private or public.
	 *
	 * @param int  $source_id Source ID.
	 * @param bool $private   Whether it needs the connected account.
	 * @return void
	 */
	public static function set_private( $source_id, $private ) {
		$stored  = array();
		$current = self::private_sources();

		// Rewritten from scratch each time, so a mark left by a deleted table
		// is dropped the next time anything is saved.
		foreach ( LSTAB_Storage::get_all() as $source ) {
			$id = (int) $source['id'];

			if ( $id === (int) $source_id ) {
				if ( $private ) {
					$stored[ $id ] = (string) $source['created_gmt'];
				}
			} elseif ( isset( $current[ $id ] ) ) {
				$stored[ $id ] = (string) $source['created_gmt'];
			}
		}

		update_option( self::META_OPTION, $stored, false );
	}

	/**
	 * Whether a source is marked private.
	 *
	 * @param int $source_id Source ID.
	 * @return bool
	 */
	public static function is_private( $source_id ) {
		$sources = self::private_sources();

		return ! empty( $sources[ (int) $source_id ] );
	}

	/**
	 * Point a private sheet's download at the authenticated endpoint.
	 *
	 * @param string $url        Public export URL.
	 * @param string $sheet_id   Spreadsheet ID.
	 * @param string $gid        Tab ID.
	 * @param string $sheet_kind Document kind.
	 * @return string
	 */
	public function maybe_authenticated_url( $url, $sheet_id, $gid, $sheet_kind ) {
		if ( ! $this->applies( $sheet_id, $url ) ) {
			return $url;
		}

		// The API exports the whole spreadsheet or a named range, so the tab is
		// selected by gid on the export endpoint rather than in the path.
		return add_query_arg(
			array(
				'format' => 'csv',
				'gid'    => rawurlencode( $gid ),
			),
			'https://docs.google.com/spreadsheets/d/' . rawurlencode( $sheet_id ) . '/export'
		);
	}

	/**
	 * Attach the bearer token to a private sheet's request.
	 *
	 * @param array<string,mixed> $args Request arguments.
	 * @param string              $url  Target URL.
	 * @return array<string,mixed>
	 */
	public function maybe_authorise( $args, $url ) {
		if ( false === strpos( $url, '/export' ) || ! $this->current_source_is_private() ) {
			return $args;
		}

		$token = LSTABP_Google_Auth::access_token();

		if ( is_wp_error( $token ) ) {
			// Leave the request unauthenticated. Google will refuse it and the
			// free plugin's own error handling will report that clearly, which
			// beats inventing a second failure path here.
			return $args;
		}

		$args['headers'] = isset( $args['headers'] ) ? (array) $args['headers'] : array();
		$args['headers']['Authorization'] = 'Bearer ' . $token;

		return $args;
	}

	/**
	 * Whether the source currently syncing is a private one.
	 *
	 * @var int|null
	 */
	protected static $current_source = null;

	/**
	 * Remember which source is being synced.
	 *
	 * The fetch filters receive a URL, not a source, so the id is captured from
	 * the sync lifecycle the free plugin already announces.
	 *
	 * @param array<string,mixed> $source Source row.
	 * @return void
	 */
	public static function remember_source( $source ) {
		self::$current_source = isset( $source['id'] ) ? (int) $source['id'] : null;
	}

	/**
	 * Whether the source being synced right now is private.
	 *
	 * @return bool
	 */
	protected function current_source_is_private() {
		return null !== self::$current_source && self::is_private( self::$current_source );
	}

	/**
	 * Whether this fetch is for a private sheet.
	 *
	 * @param string $sheet_id Spreadsheet ID.
	 * @param string $url      Current URL.
	 * @return bool
	 */
	protected function applies( $sheet_id, $url ) {
		unset( $sheet_id, $url );

		return $this->current_source_is_private() && LSTABP_Google_Auth::is_connected();
	}
}
