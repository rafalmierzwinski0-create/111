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
		add_action( 'lstabp_forget_source', array( __CLASS__, 'forget' ) );

		add_filter( 'lstab_fetch_refused', array( $this, 'try_connected_account' ), 10, 4 );
	}

	/**
	 * Whether the connected account is being tried for a refused sheet.
	 *
	 * @var bool
	 */
	protected static $trying = false;

	/**
	 * Read a sheet through the connected account once its public link stops
	 * working, and remember to do so from then on.
	 *
	 * This is what lets a sheet go private without anyone ticking anything:
	 * add it by link, connect the account, switch link sharing off in Google,
	 * and the next check finds the public door shut, walks through the
	 * account's instead, and marks the table so it goes that way every time.
	 * It also lets a sheet that was never shared at all be previewed and
	 * added. Only a refusal is retried — a sheet that is missing, empty or
	 * slow is not a sharing question — and if the account cannot open it
	 * either, the original refusal is what gets reported, because that is
	 * the one that says what to change.
	 *
	 * @param WP_Error $result     The public refusal.
	 * @param string   $sheet_id   Spreadsheet ID.
	 * @param string   $gid        Tab ID.
	 * @param string   $sheet_kind Document kind.
	 * @return string|WP_Error CSV body, or the refusal unchanged.
	 */
	public function try_connected_account( $result, $sheet_id, $gid, $sheet_kind ) {
		if ( self::$trying || ! is_wp_error( $result ) || ! self::is_refusal( $result ) ) {
			return $result;
		}

		/*
		 * A private sheet refused because the account is gone says so, rather
		 * than telling somebody to share by link a sheet they made private on
		 * purpose.
		 */
		if ( null !== self::$current_source && self::is_private( self::$current_source ) ) {
			$token = LSTABP_Google_Auth::access_token();

			return is_wp_error( $token ) ? $token : $result;
		}

		// Already read through the account, and refused anyway.
		if ( $this->current_source_is_private() || ! LSTABP_Google_Auth::is_connected() ) {
			return $result;
		}

		/*
		 * Only for somebody who could tick the box on the Pro screen. Anyone
		 * else allowed to manage tables could otherwise paste the address of
		 * any spreadsheet the connected account can open and read it.
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			return $result;
		}

		self::$trying = true;
		$second       = LSTAB_Fetcher::fetch_csv( $sheet_id, $gid, $sheet_kind );
		self::$trying = false;

		if ( is_wp_error( $second ) ) {
			return $result;
		}

		if ( null !== self::$current_source ) {
			self::set_private( self::$current_source, true );
		}

		return $second;
	}

	/**
	 * Whether a failure means "you may not see this" rather than anything else.
	 *
	 * Google answers a sheet that is not shared with its sign-in page, or with
	 * 401 or 403; a file it will not show somebody can also come back as 404.
	 *
	 * @param WP_Error $error Failure.
	 * @return bool
	 */
	protected static function is_refusal( $error ) {
		if ( 'lstab_not_public' === $error->get_error_code() ) {
			return true;
		}

		$data = $error->get_error_data();

		return 'lstab_http_status' === $error->get_error_code()
			&& is_array( $data )
			&& isset( $data['status'] )
			&& in_array( (int) $data['status'], array( 401, 403, 404 ), true );
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

			if ( true === $stored[ $id ] || (string) $stored[ $id ] === self::stamp( $source ) ) {
				$private[ $id ] = true;
			}
		}

		return $private;
	}

	/**
	 * What a private mark is tied to: the table, and the spreadsheet it reads.
	 *
	 * A table pointed at a different spreadsheet loses its mark, so the
	 * connected account is never used for a sheet nobody with access to the
	 * Pro settings has seen.
	 *
	 * @param array<string,mixed> $source Source row.
	 * @return string
	 */
	protected static function stamp( $source ) {
		return (string) $source['created_gmt'] . '|' . (string) $source['sheet_id'];
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
					$stored[ $id ] = self::stamp( $source );
				}
			} elseif ( isset( $current[ $id ] ) ) {
				$stored[ $id ] = self::stamp( $source );
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
		if ( false === strpos( $url, '/export' ) || ! ( self::$trying || $this->current_source_is_private() ) ) {
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

		return ( self::$trying || $this->current_source_is_private() ) && LSTABP_Google_Auth::is_connected();
	}
}
