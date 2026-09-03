<?php
/**
 * Telling the page cache that a table has changed.
 *
 * This is the failure nobody attributes to caching. The sheet is edited, the
 * plugin fetches it on time, the dashboard shows the new figure — and the
 * visitor still sees yesterday's price, because the page they are served was
 * built hours ago and stored by a caching plugin that has no idea anything
 * happened. Nothing in this plugin is broken and everything about it looks
 * broken, which is exactly the review it earns.
 *
 * The reason it happens is that a page cache is cleared by editing a post, and
 * a sheet arriving from Google is not that. So the plugin has to say so itself.
 *
 * Two things keep it well-mannered. It fires only when the sheet actually
 * changed — the stored copy is hashed, so a check that found nothing new clears
 * nothing. And it clears the pages the table is actually on, found through the
 * usage map the dashboard already builds, rather than throwing away the whole
 * site's cache because one price moved.
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

/**
 * Page-cache integration.
 */
class LSTAB_Cache {

	/**
	 * Where the last clearing of each source is recorded, for the dashboard.
	 */
	const LOG_OPTION = 'lstab_purge_log';

	/**
	 * What the site has chosen to clear.
	 *
	 * @return string One of 'pages', 'site', 'off'.
	 */
	public static function mode() {
		$mode = (string) LSTAB_Settings::get( 'purge_cache', 'pages' );

		return in_array( $mode, array( 'pages', 'site', 'off' ), true ) ? $mode : 'pages';
	}

	/**
	 * The choices offered on the settings screen.
	 *
	 * @return array<string,string>
	 */
	public static function modes() {
		return array(
			'pages' => __( 'Only the pages this table is on', 'live-sheets-table' ),
			'site'  => __( 'The whole cache, every time', 'live-sheets-table' ),
			'off'   => __( 'Nothing — I will clear it myself', 'live-sheets-table' ),
		);
	}

	/**
	 * Clear whatever holds an old copy of one table.
	 *
	 * @param int $source_id Source ID.
	 * @return array{scope:string,posts:int} What was done.
	 */
	public static function purge( $source_id ) {
		$source_id = (int) $source_id;
		$mode      = self::mode();

		if ( 'off' === $mode ) {
			return array(
				'scope' => 'off',
				'posts' => 0,
			);
		}

		if ( 'site' === $mode ) {
			self::purge_site();
			self::record( $source_id, 'site', 0 );

			return array(
				'scope' => 'site',
				'posts' => 0,
			);
		}

		// places() answers with a list of pages, each carrying its own ID — the
		// list's own keys are just positions.
		$posts = wp_list_pluck( LSTAB_Usage::places( $source_id ), 'id' );
		$posts = array_values( array_unique( array_map( 'intval', $posts ) ) );

		foreach ( $posts as $post_id ) {
			self::purge_post( (int) $post_id );
		}

		/**
		 * Fires after the pages holding one table have been cleared.
		 *
		 * The hook for anything this plugin has not heard of: a host's own
		 * cache, a CDN, a reverse proxy. It is given the pages that were
		 * cleared and the table that changed.
		 *
		 * @param array<int,int> $posts     Post IDs whose cache was cleared.
		 * @param int            $source_id Source that changed.
		 */
		do_action( 'lstab_purge_page_cache', $posts, $source_id );

		self::record( $source_id, 'pages', count( $posts ) );

		return array(
			'scope' => 'pages',
			'posts' => count( $posts ),
		);
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		// Saving changes what the page looks like as surely as new data does: a
		// hidden column or a different style is a different page.
		add_action( 'lstab_source_saved', array( __CLASS__, 'purge' ) );

		add_action(
			'lstab_source_deleted',
			static function ( $source_id ) {
				// The pages that held it are now pages without it.
				LSTAB_Cache::purge( (int) $source_id );
				LSTAB_Cache::forget( (int) $source_id );
			}
		);
	}

	/**
	 * Clear one page, in whatever is caching it.
	 *
	 * Each of these is the published way to clear one post in that plugin. The
	 * ones that are actions can be fired without checking: an action nobody is
	 * listening to costs nothing. The ones that are functions or methods have
	 * to be there first.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	protected static function purge_post( $post_id ) {
		if ( $post_id <= 0 ) {
			return;
		}

		// Core's own object cache for the post, which a persistent object cache
		// would otherwise keep serving to the renderer itself.
		clean_post_cache( $post_id );

		// WP Rocket.
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $post_id );
		}

		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_post' ) ) {
			w3tc_flush_post( $post_id );
		}

		// WP Super Cache.
		if ( function_exists( 'wp_cache_post_change' ) ) {
			wp_cache_post_change( $post_id );
		}

		// WP Fastest Cache, which exposes an object rather than a function.
		if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'singleDeleteCache' ) ) {
			$GLOBALS['wp_fastest_cache']->singleDeleteCache( false, $post_id );
		}

		// WP Engine's own page cache.
		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
			WpeCommon::purge_varnish_cache( $post_id );
		}

		// LiteSpeed Cache, Cache Enabler and Hummingbird all listen for these.
		do_action( 'litespeed_purge_post', $post_id );
		do_action( 'cache_enabler_clear_page_cache_by_post', $post_id );
		do_action( 'wphb_clear_page_cache', $post_id );

		// SiteGround's optimiser clears by address rather than by post.
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache( get_permalink( $post_id ) );
		}
	}

	/**
	 * Clear everything, for a site that has said it wants that.
	 *
	 * @return void
	 */
	protected static function purge_site() {
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
			$GLOBALS['wp_fastest_cache']->deleteCache( true );
		}

		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
			WpeCommon::purge_varnish_cache();
		}

		do_action( 'litespeed_purge_all' );
		do_action( 'cache_enabler_clear_complete_cache' );
		do_action( 'wphb_clear_page_cache' );
		do_action( 'rt_nginx_helper_purge_all' );
		do_action( 'breeze_clear_all_cache' );

		if ( function_exists( 'sg_cachepress_purge_everything' ) ) {
			sg_cachepress_purge_everything();
		}

		/**
		 * Fires after the whole page cache has been cleared.
		 */
		do_action( 'lstab_purge_all_cache' );
	}

	/**
	 * Remember what was cleared, so the dashboard can say so.
	 *
	 * The first question on a support thread about a stale table is whether the
	 * cache was cleared at all, and "we cleared 3 pages at 14:05" answers it
	 * without anybody having to reproduce anything.
	 *
	 * @param int    $source_id Source ID.
	 * @param string $scope     'pages' or 'site'.
	 * @param int    $posts     How many pages were cleared.
	 * @return void
	 */
	protected static function record( $source_id, $scope, $posts ) {
		$log = (array) get_option( self::LOG_OPTION, array() );

		$log[ (int) $source_id ] = array(
			'time'  => time(),
			'scope' => (string) $scope,
			'posts' => (int) $posts,
		);

		// One entry per source, and sources are few, so this cannot grow into
		// something that has to be cleaned up on a schedule.
		update_option( self::LOG_OPTION, $log, false );
	}

	/**
	 * The last clearing for one source, or nothing.
	 *
	 * @param int $source_id Source ID.
	 * @return array{time:int,scope:string,posts:int}|null
	 */
	public static function last( $source_id ) {
		$log = (array) get_option( self::LOG_OPTION, array() );

		return isset( $log[ (int) $source_id ] ) ? (array) $log[ (int) $source_id ] : null;
	}

	/**
	 * Forget a deleted source.
	 *
	 * @param int $source_id Source ID.
	 * @return void
	 */
	public static function forget( $source_id ) {
		$log = (array) get_option( self::LOG_OPTION, array() );

		if ( ! isset( $log[ (int) $source_id ] ) ) {
			return;
		}

		unset( $log[ (int) $source_id ] );
		update_option( self::LOG_OPTION, $log, false );
	}
}
