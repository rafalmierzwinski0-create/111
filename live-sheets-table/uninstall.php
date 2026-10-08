<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-lstab-limits.php';
require_once __DIR__ . '/includes/class-lstab-settings.php';
require_once __DIR__ . '/includes/class-lstab-columns.php';
require_once __DIR__ . '/includes/class-lstab-hidden-rows.php';
require_once __DIR__ . '/includes/class-lstab-customizer.php';
require_once __DIR__ . '/includes/class-lstab-storage.php';
require_once __DIR__ . '/includes/class-lstab-cron.php';
require_once __DIR__ . '/includes/class-lstab-freshness.php';

LSTAB_Cron::unschedule();

LSTAB_Freshness::remove();
delete_option( 'lstab_seen_on' );
delete_transient( 'lstab_due_unwritable' );

if ( ! LSTAB_Settings::get( 'delete_on_uninstall' ) ) {
	return;
}

LSTAB_Storage::drop();

delete_option( 'lstab_db_version' );
delete_option( 'lstab_tick_schedule' );
delete_option( 'lstab_last_tick' );
delete_option( 'lstab_ragged_sources' );
delete_option( 'lstab_ragged_dismissed' );
delete_option( LSTAB_Cache::LOG_OPTION );
delete_option( LSTAB_Limits::SEEN_OPTION );
delete_option( LSTAB_Settings::OPTION );
