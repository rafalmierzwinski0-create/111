<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-lstab-storage.php';
require_once __DIR__ . '/includes/class-lstab-cron.php';
require_once __DIR__ . '/includes/class-lstab-freshness.php';

LSTAB_Cron::unschedule();

LSTAB_Freshness::remove();
delete_option( 'lstab_seen_on' );
delete_option( 'lstab_pro_last_seen' );
delete_transient( 'lstab_due_unwritable' );
delete_transient( 'lstab_usage_map' );
delete_metadata( 'user', 0, 'lstab_grace_dismissed', '', true );

$lstab_settings = (array) get_option( 'lstab_settings', array() );

if ( empty( $lstab_settings['delete_on_uninstall'] ) ) {
	return;
}

LSTAB_Storage::drop();

delete_option( 'lstab_db_version' );
delete_option( 'lstab_tick_schedule' );
delete_option( 'lstab_last_tick' );
delete_option( 'lstab_ragged_sources' );
delete_option( 'lstab_ragged_dismissed' );
delete_option( 'lstab_hidden_alerts' );
delete_option( 'lstab_hidden_alerts_dismissed' );
delete_option( 'lstab_paging_declined' );
delete_option( 'lstab_purge_log' );
delete_option( 'lstab_settings' );
