<?php

defined( 'ABSPATH' ) || exit;

class LSTAB_Cron {
	const RETRY_HOOK = 'lstab_sync_source';

	const OLD_TICK_HOOK = 'lstab_sync_tick';

	public function register() {
		add_action( self::RETRY_HOOK, array( __CLASS__, 'run_source' ) );
		add_action( 'init', array( __CLASS__, 'drop_old_schedule' ) );
	}

	public static function drop_old_schedule() {
		if ( wp_installing() || ! wp_next_scheduled( self::OLD_TICK_HOOK ) ) {
			return;
		}

		wp_clear_scheduled_hook( self::OLD_TICK_HOOK );
		delete_option( 'lstab_tick_schedule' );
		delete_option( 'lstab_last_tick' );
	}

	public static function run_source( $id ) {
		LSTAB_Sync::run( (int) $id );
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::OLD_TICK_HOOK );
		wp_unschedule_hook( self::RETRY_HOOK );
		delete_option( 'lstab_tick_schedule' );
		delete_option( 'lstab_last_tick' );
	}
}
