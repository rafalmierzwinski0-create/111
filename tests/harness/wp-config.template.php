<?php
/**
 * Test-site configuration. __PORT__ is substituted by tests/setup-env.sh.
 *
 * @package LiveSheetsTable\Tests
 */

define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY',         'lstab-test-auth-key' );
define( 'SECURE_AUTH_KEY',  'lstab-test-secure-auth' );
define( 'LOGGED_IN_KEY',    'lstab-test-logged-in' );
define( 'NONCE_KEY',        'lstab-test-nonce' );
define( 'AUTH_SALT',        'lstab-test-auth-salt' );
define( 'SECURE_AUTH_SALT', 'lstab-test-secure-salt' );
define( 'LOGGED_IN_SALT',   'lstab-test-logged-salt' );
define( 'NONCE_SALT',       'lstab-test-nonce-salt' );

$table_prefix = 'wp_';

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );
define( 'WP_HOME', 'http://127.0.0.1:__PORT__' );
define( 'WP_SITEURL', 'http://127.0.0.1:__PORT__' );

// The suites drive the scheduler explicitly rather than on page views.
define( 'DISABLE_WP_CRON', true );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
