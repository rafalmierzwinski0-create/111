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
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'save_post', array( __CLASS__, 'forget' ) );
		add_action( 'deleted_post', array( __CLASS__, 'forget' ) );
		add_action( 'lstab_source_deleted', array( __CLASS__, 'forget' ) );
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
