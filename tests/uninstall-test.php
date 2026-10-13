<?php
/**
 * Deletes the free plugin the way WordPress does: switched off first, then its
 * uninstall.php run in a request of its own, with none of the plugin loaded.
 * Running it with the plugin still active hid that the file reached for a
 * class WordPress had not loaded, and every deletion ended in a fatal error.
 *
 * The site is put back afterwards: the plugin is switched on again, which
 * builds its table anew.
 *
 * Usage: php tests/uninstall-test.php /path/to/site PORT
 *
 * @package LiveSheetsTable\Tests
 */

$site = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
$port = isset( $argv[2] ) ? (int) $argv[2] : 8090;

if ( ! $site || ! file_exists( $site . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tests/uninstall-test.php /path/to/site PORT\n" );
	exit( 1 );
}

$pass = 0;
$fail = 0;

function lstab_u_assert( $ok, $label, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) {
		$pass++;
		echo "  \033[32mPASS\033[0m  {$label}\n";
	} else {
		$fail++;
		echo "  \033[31mFAIL\033[0m  {$label}" . ( '' !== $detail ? "\n        {$detail}" : '' ) . "\n";
	}
}

function lstab_u_json( $value ) {
	return (string) json_encode( $value );
}

function lstab_u_run( $script, $args ) {
	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/harness/' . $script );
	foreach ( $args as $arg ) {
		$command .= ' ' . escapeshellarg( (string) $arg );
	}
	return (string) shell_exec( $command . ' 2>&1' );
}

$state = static function () use ( $site, $port ) {
	return json_decode( trim( lstab_u_run( 'uninstall-site.php', array( $site, $port, 'state' ) ) ), true );
};

$plugin = 'live-sheets-table/live-sheets-table.php';

echo "\n\033[1mDeleting the plugin, settings kept\033[0m\n";

lstab_u_run( 'activate.php', array( $site, $port ) );
lstab_u_run( 'uninstall-site.php', array( $site, $port, 'keep' ) );
lstab_u_run( 'deactivate.php', array( $site, $port, $plugin ) );

$before = $state();
lstab_u_assert( is_array( $before ) && ! $before['active'], 'The plugin is switched off before it is deleted, as WordPress requires', lstab_u_json( $before ) );

$out = lstab_u_run( 'run-uninstall.php', array( $site, $plugin ) );
lstab_u_assert( false !== strpos( $out, 'uninstalled ' . $plugin ) && false === stripos( $out, 'critical error' ), 'Its uninstall routine runs to the end with none of the plugin loaded', trim( substr( $out, -400 ) ) );

$after = $state();
lstab_u_assert( ! empty( $after['table'] ), 'The tables are kept when nobody asked for them to go' );
lstab_u_assert( in_array( 'lstab_settings', (array) $after['options'], true ), 'And so are the settings' );
lstab_u_assert( empty( $after['tick'] ), 'The background schedule is cleared either way' );
lstab_u_assert( ! in_array( 'lstab_seen_on', (array) $after['options'], true ) && ! in_array( 'lstab_pro_last_seen', (array) $after['options'], true ), 'And so is what an older version left behind', implode( ', ', (array) $after['options'] ) );
lstab_u_assert( 0 === (int) $after['meta'], 'Including a note it kept on a user' );

echo "\n\033[1mDeleting the plugin, everything asked to go\033[0m\n";

lstab_u_run( 'uninstall-site.php', array( $site, $port, 'wipe' ) );
$out = lstab_u_run( 'run-uninstall.php', array( $site, $plugin ) );
lstab_u_assert( false !== strpos( $out, 'uninstalled ' . $plugin ) && false === stripos( $out, 'critical error' ), 'Its uninstall routine runs to the end', trim( substr( $out, -400 ) ) );

$after = $state();
lstab_u_assert( empty( $after['table'] ), 'Its table is gone' );
lstab_u_assert( array() === (array) $after['options'], 'And not one of its settings is left', implode( ', ', (array) $after['options'] ) );

echo "\n\033[1mPutting the site back\033[0m\n";

lstab_u_run( 'activate.php', array( $site, $port ) );
$back = $state();
lstab_u_assert( ! empty( $back['active'] ) && ! empty( $back['table'] ) && ! empty( $back['tick'] ), 'Switched on again, it builds its table and its schedule anew', lstab_u_json( $back ) );

echo "\n" . str_repeat( '─', 60 ) . "\n";
echo "  \033[32m{$pass} passed\033[0m, " . ( $fail ? "\033[31m{$fail} failed\033[0m" : '0 failed' ) . "\n";
echo str_repeat( '─', 60 ) . "\n";

exit( $fail ? 1 : 0 );
