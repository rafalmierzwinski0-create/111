<?php
/**
 * Which pages actually use a table.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Finds the posts and pages a sheet source appears on.
 *
 * This answers the one question the dashboard could never answer before, and
 * the one people are most afraid of getting wrong: *can I delete this?* Without
 * it, deleting is a guess, so nobody deletes anything and the list fills up
 * with sources whose purpose everyone has forgotten.
 *
 * Both ways of placing a table are looked for — the shortcode and the block —
 * because a site usually has some of each and a half-answer is worse than none.
 */
class LSTAB_Usage {

	/**
	 * How long a scan is trusted for.
	 *
	 * Editing a post clears this anyway; the expiry is only a backstop for
	 * content that arrives some other way, such as an import.
	 */
	const TTL = HOUR_IN_SECONDS;

	/**
	 * Transient holding the whole map.
	 */
	const CACHE = 'lstab_usage_map';

	/**
	 * Option holding where each table was actually drawn.
	 */
	const SEEN = 'lstab_seen_on';

	/**
	 * How long a sighting counts after the table was last drawn there.
	 */
	const SEEN_TTL = 30 * DAY_IN_SECONDS;

	/**
	 * How many pages are remembered per table.
	 */
	const SEEN_MAX = 200;

	/**
	 * Sightings, loaded once per request.
	 *
	 * @var array<int,array<int,int>>|null
	 */
	protected static $seen = null;

	/**
	 * Whether the sightings changed during this request.
	 *
	 * @var bool
	 */
	protected static $seen_dirty = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'save_post', array( __CLASS__, 'forget' ) );
		add_action( 'deleted_post', array( __CLASS__, 'forget' ) );
		add_action( 'lstab_source_deleted', array( __CLASS__, 'forget' ) );
		add_action( 'lstab_source_deleted', array( __CLASS__, 'forget_seen' ) );
		add_action( 'shutdown', array( __CLASS__, 'save_seen' ) );
	}

	/**
	 * Note the page a table is being drawn on.
	 *
	 * Finds what the content scan cannot: a table in an Elementor widget, a
	 * sidebar, a theme template or any page builder's own storage. Written at
	 * most once a day per table and page.
	 *
	 * @param int $source_id Source ID.
	 * @return void
	 */
	public static function saw( $source_id ) {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! is_singular() ) {
			return;
		}

		$post_id = (int) get_queried_object_id();

		if ( $post_id <= 0 ) {
			return;
		}

		$seen = self::seen();
		$last = isset( $seen[ $source_id ][ $post_id ] ) ? (int) $seen[ $source_id ][ $post_id ] : 0;

		if ( time() - $last < DAY_IN_SECONDS ) {
			return;
		}

		$seen[ $source_id ][ $post_id ] = time();
		arsort( $seen[ $source_id ] );
		$seen[ $source_id ] = array_slice( $seen[ $source_id ], 0, self::SEEN_MAX, true );

		self::$seen       = $seen;
		self::$seen_dirty = true;
	}

	/**
	 * Every recorded sighting.
	 *
	 * @return array<int,array<int,int>>
	 */
	protected static function seen() {
		if ( null === self::$seen ) {
			self::$seen = (array) get_option( self::SEEN, array() );
		}

		return self::$seen;
	}

	/**
	 * Store the sightings if any were added.
	 *
	 * @return void
	 */
	public static function save_seen() {
		if ( self::$seen_dirty ) {
			self::$seen_dirty = false;
			update_option( self::SEEN, self::$seen, false );
			self::forget();
		}
	}

	/**
	 * Drop a deleted table's sightings.
	 *
	 * @param int $source_id Source ID.
	 * @return void
	 */
	public static function forget_seen( $source_id ) {
		$seen = self::seen();

		if ( isset( $seen[ (int) $source_id ] ) ) {
			unset( $seen[ (int) $source_id ] );
			self::$seen = $seen;
			update_option( self::SEEN, $seen, false );
		}
	}

	/**
	 * Drop the cached scan.
	 *
	 * @return void
	 */
	public static function forget() {
		delete_transient( self::CACHE );
	}

	/**
	 * Every source that is placed somewhere, and where.
	 *
	 * One query for the whole dashboard rather than one per row: a list of ten
	 * sources would otherwise be ten scans of the posts table.
	 *
	 * @return array<int,array<int,array{id:int,title:string,url:string}>>
	 */
	public static function map() {
		$cached = get_transient( self::CACHE );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		// Statuses somebody would call "on the site". A table in the trash is
		// not a reason to keep a source, and saying so would be misleading.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT ID, post_title, post_content
			 FROM {$wpdb->posts}
			 WHERE post_status IN ( 'publish', 'future', 'draft', 'pending', 'private' )
			   AND post_type NOT IN ( 'revision', 'attachment' )
			   AND ( post_content LIKE '%sheet_table%' OR post_content LIKE '%live-sheets-table/sheet-table%' )
			 LIMIT 500"
		);

		$map = array();

		foreach ( (array) $rows as $row ) {
			foreach ( self::ids_in( (string) $row->post_content ) as $source_id ) {
				if ( ! isset( $map[ $source_id ] ) ) {
					$map[ $source_id ] = array();
				}

				// One page listed once, however many tables it holds.
				if ( isset( $map[ $source_id ][ (int) $row->ID ] ) ) {
					continue;
				}

				$map[ $source_id ][ (int) $row->ID ] = array(
					'id'    => (int) $row->ID,
					'title' => '' !== trim( (string) $row->post_title )
						? (string) $row->post_title
						: __( '(no title)', 'live-sheets-table' ),
					'url'   => (string) get_edit_post_link( (int) $row->ID, 'raw' ),
				);
			}
		}

		foreach ( self::seen() as $source_id => $posts ) {
			foreach ( (array) $posts as $post_id => $when ) {
				$post_id = (int) $post_id;

				if ( isset( $map[ $source_id ][ $post_id ] ) || time() - (int) $when > self::SEEN_TTL ) {
					continue;
				}

				$post = get_post( $post_id );

				if ( ! $post || ! in_array( $post->post_status, array( 'publish', 'future', 'draft', 'pending', 'private' ), true ) ) {
					continue;
				}

				$map[ $source_id ][ $post_id ] = array(
					'id'    => $post_id,
					'title' => '' !== trim( (string) $post->post_title )
						? (string) $post->post_title
						: __( '(no title)', 'live-sheets-table' ),
					'url'   => (string) get_edit_post_link( $post_id, 'raw' ),
				);
			}
		}

		foreach ( $map as $source_id => $places ) {
			$map[ $source_id ] = array_values( $places );
		}

		set_transient( self::CACHE, $map, self::TTL );

		return $map;
	}

	/**
	 * Source IDs referenced by one piece of content.
	 *
	 * @param string $content Post content.
	 * @return array<int,int>
	 */
	public static function ids_in( $content ) {
		$found = array();

		// [sheet_table id="12"], id='12' and id=12 are all in the wild, because
		// people copy the shortcode and then retype it.
		if ( preg_match_all( '/\[sheet_table[^\]]*\bid\s*=\s*["\']?(\d+)/i', $content, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				$found[] = (int) $id;
			}
		}

		// The block stores its attributes as JSON in the comment delimiter.
		if ( preg_match_all( '/wp:live-sheets-table\/sheet-table\s+(\{.*?\})/s', $content, $blocks ) ) {
			foreach ( $blocks[1] as $json ) {
				$attrs = json_decode( $json, true );

				if ( is_array( $attrs ) && ! empty( $attrs['sourceId'] ) ) {
					$found[] = (int) $attrs['sourceId'];
				}
			}
		}

		return array_values( array_unique( array_filter( $found ) ) );
	}

	/**
	 * Where one source is used.
	 *
	 * @param int $source_id Source ID.
	 * @return array<int,array{id:int,title:string,url:string}>
	 */
	public static function places( $source_id ) {
		$map = self::map();

		return isset( $map[ (int) $source_id ] ) ? $map[ (int) $source_id ] : array();
	}
}
