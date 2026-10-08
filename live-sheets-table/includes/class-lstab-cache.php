<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Cache {
	const LOG_OPTION = 'lstab_purge_log';

	public static function enabled( $source_id = 0 ) {
		return (bool) apply_filters( 'lstab_clear_page_cache', true, (int) $source_id );
	}

	const MAX_PAGES = 50;

	public static function purge( $source_id ) {
		$source_id = (int) $source_id;

		if ( ! self::enabled( $source_id ) ) {
			return array(
				'scope' => 'off',
				'posts' => 0,
			);
		}

		$posts = wp_list_pluck( LSTAB_Usage::places( $source_id ), 'id' );
		$posts = array_values( array_unique( array_map( 'intval', $posts ) ) );

		if ( ! $posts || count( $posts ) > self::MAX_PAGES ) {
			return array(
				'scope' => 'none',
				'posts' => 0,
			);
		}

		foreach ( $posts as $post_id ) {
			self::purge_post( (int) $post_id );
		}

		do_action( 'lstab_purge_page_cache', $posts, $source_id );

		self::record( $source_id, 'pages', count( $posts ) );

		return array(
			'scope' => 'pages',
			'posts' => count( $posts ),
		);
	}

	public function register() {
		add_action(
			'lstab_source_saved',
			static function ( $source_id ) {
				LSTAB_Cache::purge( (int) $source_id );
			}
		);

		add_action(
			'lstab_source_deleted',
			static function ( $source_id ) {
				LSTAB_Cache::purge( (int) $source_id );
				LSTAB_Cache::forget( (int) $source_id );
			}
		);
	}

	protected static function purge_post( $post_id ) {
		if ( $post_id <= 0 ) {
			return;
		}

		clean_post_cache( $post_id );

		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $post_id );
		}

		if ( function_exists( 'w3tc_flush_post' ) ) {
			w3tc_flush_post( $post_id );
		}

		if ( function_exists( 'wp_cache_post_change' ) ) {
			wp_cache_post_change( $post_id );
		}

		if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'singleDeleteCache' ) ) {
			$GLOBALS['wp_fastest_cache']->singleDeleteCache( false, $post_id );
		}

		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
			WpeCommon::purge_varnish_cache( $post_id );
		}

		do_action( 'litespeed_purge_post', $post_id );
		do_action( 'cache_enabler_clear_page_cache_by_post', $post_id );
		do_action( 'wphb_clear_page_cache', $post_id );

		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache( get_permalink( $post_id ) );
		}
	}

	protected static function record( $source_id, $scope, $posts ) {
		$log = (array) get_option( self::LOG_OPTION, array() );

		$log[ (int) $source_id ] = array(
			'time'  => time(),
			'scope' => (string) $scope,
			'posts' => (int) $posts,
		);

		update_option( self::LOG_OPTION, $log, false );
	}

	public static function last( $source_id ) {
		$log = (array) get_option( self::LOG_OPTION, array() );

		return isset( $log[ (int) $source_id ] ) ? (array) $log[ (int) $source_id ] : null;
	}

	public static function forget( $source_id ) {
		$log = (array) get_option( self::LOG_OPTION, array() );

		if ( ! isset( $log[ (int) $source_id ] ) ) {
			return;
		}

		unset( $log[ (int) $source_id ] );
		update_option( self::LOG_OPTION, $log, false );
	}
}
