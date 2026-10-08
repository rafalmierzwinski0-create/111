<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Usage {
	const TTL = HOUR_IN_SECONDS;

	const CACHE = 'lstab_usage_map';

	const SEEN = 'lstab_seen_on';

	const SEEN_TTL = 30 * DAY_IN_SECONDS;

	const SEEN_MAX = 200;

	protected static $seen = null;

	protected static $seen_dirty = false;

	public function register() {
		add_action( 'save_post', array( __CLASS__, 'forget' ) );
		add_action( 'deleted_post', array( __CLASS__, 'forget' ) );
		add_action( 'lstab_source_deleted', array( __CLASS__, 'forget' ) );
		add_action( 'lstab_source_deleted', array( __CLASS__, 'forget_seen' ) );
		add_action( 'shutdown', array( __CLASS__, 'save_seen' ) );
	}

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

	protected static function seen() {
		if ( null === self::$seen ) {
			self::$seen = (array) get_option( self::SEEN, array() );
		}

		return self::$seen;
	}

	public static function save_seen() {
		if ( self::$seen_dirty ) {
			self::$seen_dirty = false;
			update_option( self::SEEN, self::$seen, false );
			self::forget();
		}
	}

	public static function forget_seen( $source_id ) {
		$seen = self::seen();

		if ( isset( $seen[ (int) $source_id ] ) ) {
			unset( $seen[ (int) $source_id ] );
			self::$seen = $seen;
			update_option( self::SEEN, $seen, false );
		}
	}

	public static function forget() {
		delete_transient( self::CACHE );
	}

	public static function map() {
		$cached = get_transient( self::CACHE );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

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

	public static function ids_in( $content ) {
		$found = array();

		if ( preg_match_all( '/\[sheet_table[^\]]*\bid\s*=\s*["\']?(\d+)/i', $content, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				$found[] = (int) $id;
			}
		}

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

	public static function places( $source_id ) {
		$map = self::map();

		return isset( $map[ (int) $source_id ] ) ? $map[ (int) $source_id ] : array();
	}
}
