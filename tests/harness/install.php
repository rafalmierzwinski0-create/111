<?php
/**
 * Installs a WordPress test site.
 *
 * Usage: php tests/harness/install.php /path/to/site 8089
 *
 * @package LiveSheetsTable\Tests
 */

$site = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
$port = isset( $argv[2] ) ? (int) $argv[2] : 8089;

if ( ! $site || ! file_exists( $site . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php install.php /path/to/site PORT\n" );
	exit( 1 );
}

define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST']      = '127.0.0.1:' . $port;
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $site . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

if ( is_blog_installed() ) {
	echo "  already installed\n";
	exit( 0 );
}

$result = wp_install( 'Live Sheets Table – Test Site', 'admin', 'admin@example.com', true, '', 'admin123' );

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules( false );

echo '  installed (user ' . (int) $result['user_id'] . ")\n";
