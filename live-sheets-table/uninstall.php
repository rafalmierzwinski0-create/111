<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Deleting a plugin to reinstall it is a normal thing to do; losing every table
 * you had configured because of it is not. So nothing is removed unless the
 * site has said, on the settings screen, that it wants that. The schedule is
 * cleared either way, because leaving an event behind that nothing can answer
 * is untidy and costs the site a wasted wake-up.
 *
 * Spreadsheets in Google are never touched, whatever is chosen here.
 *
 * @package LiveSheetsTable
 */

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

LSTAB_Cron::unschedule();

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
