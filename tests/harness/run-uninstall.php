<?php
/**
 * Runs a plugin's uninstall.php the way WordPress does.
 *
 * WordPress loads the site, defines WP_UNINSTALL_PLUGIN and includes the file
 * in a request of its own. This does the same, from the command line, so a
 * test can delete a plugin for real instead of asserting on what the file
 * looks like. A separate process is not a detail: uninstall.php declares
 * functions, so it can only be included once, and a test that wants to see
 * both answers has to ask twice.
 *
 * Usage: php tests/harness/run-uninstall.php /path/to/wp plugin-dir/plugin.php
 *
 * @package LiveSheetsTable\Tests
 */

/*
 * Held as constants rather than variables. Loading WordPress runs thousands of
 * lines in this same global scope, and it has variables called $plugin and
 * $file of its own — a plain variable here is quietly somebody else's by the
 * time the file is included.
 */
define( 'LSTAB_UNINSTALL_ROOT', isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '' );
define( 'LSTAB_UNINSTALL_TARGET', isset( $argv[2] ) ? $argv[2] : '' );

if ( ! LSTAB_UNINSTALL_ROOT || ! file_exists( LSTAB_UNINSTALL_ROOT . '/wp-load.php' ) || ! LSTAB_UNINSTALL_TARGET ) {
	fwrite( STDERR, "Usage: php run-uninstall.php /path/to/wp plugin-dir/plugin.php\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once LSTAB_UNINSTALL_ROOT . '/wp-load.php';

define( 'LSTAB_UNINSTALL_FILE', WP_PLUGIN_DIR . '/' . dirname( LSTAB_UNINSTALL_TARGET ) . '/uninstall.php' );

if ( ! file_exists( LSTAB_UNINSTALL_FILE ) ) {
	fwrite( STDERR, 'No uninstall.php for ' . LSTAB_UNINSTALL_TARGET . "\n" );
	exit( 1 );
}

define( 'WP_UNINSTALL_PLUGIN', LSTAB_UNINSTALL_TARGET );

require LSTAB_UNINSTALL_FILE;

echo 'uninstalled ' . LSTAB_UNINSTALL_TARGET . "\n";
