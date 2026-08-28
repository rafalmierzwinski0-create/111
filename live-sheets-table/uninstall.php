<?php
/**
 * Removes every trace of the plugin when it is deleted.
 *
 * @package LiveSheetsTable
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-lstab-limits.php';
require_once __DIR__ . '/includes/class-lstab-customizer.php';
require_once __DIR__ . '/includes/class-lstab-storage.php';
require_once __DIR__ . '/includes/class-lstab-cron.php';

LSTAB_Cron::unschedule();
LSTAB_Storage::drop();

delete_option( 'lstab_db_version' );
delete_option( 'lstab_tick_schedule' );
