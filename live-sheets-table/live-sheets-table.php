<?php
/**
 * Plugin Name:       Live Sheets Table – Google Sheets to WordPress
 * Plugin URI:        https://example.com/live-sheets-table
 * Description:       Publish a Google Sheet as a fast, responsive, auto-refreshing table. Server-side rendered, cached locally, never breaks the page when Google is unreachable.
 * Version:           2.6.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Live Sheets Table
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       live-sheets-table
 * Domain Path:       /languages
 *
 * @package LiveSheetsTable
 */

defined( 'ABSPATH' ) || exit;

define( 'LSTAB_VERSION', '2.6.0' );
define( 'LSTAB_FILE', __FILE__ );
define( 'LSTAB_PATH', plugin_dir_path( __FILE__ ) );
define( 'LSTAB_URL', plugin_dir_url( __FILE__ ) );
define( 'LSTAB_BASENAME', plugin_basename( __FILE__ ) );

require_once LSTAB_PATH . 'includes/class-lstab-limits.php';
require_once LSTAB_PATH . 'includes/class-lstab-columns.php';
require_once LSTAB_PATH . 'includes/class-lstab-customizer.php';
require_once LSTAB_PATH . 'includes/class-lstab-links.php';
require_once LSTAB_PATH . 'includes/class-lstab-paging.php';
require_once LSTAB_PATH . 'includes/class-lstab-elementor.php';
require_once LSTAB_PATH . 'includes/class-lstab-storage.php';
require_once LSTAB_PATH . 'includes/class-lstab-url.php';
require_once LSTAB_PATH . 'includes/class-lstab-csv-parser.php';
require_once LSTAB_PATH . 'includes/class-lstab-fetcher.php';
require_once LSTAB_PATH . 'includes/class-lstab-sync.php';
require_once LSTAB_PATH . 'includes/class-lstab-cron.php';
require_once LSTAB_PATH . 'includes/class-lstab-styles.php';
require_once LSTAB_PATH . 'includes/class-lstab-renderer.php';
require_once LSTAB_PATH . 'includes/class-lstab-shortcode.php';
require_once LSTAB_PATH . 'includes/class-lstab-block.php';
require_once LSTAB_PATH . 'includes/class-lstab-rest.php';
require_once LSTAB_PATH . 'includes/class-lstab-admin.php';
require_once LSTAB_PATH . 'includes/class-lstab-plugin.php';

/**
 * Main plugin instance.
 *
 * @return LSTAB_Plugin
 */
function lstab() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new LSTAB_Plugin();
	}
	return $instance;
}

lstab()->boot();

register_activation_hook( __FILE__, array( 'LSTAB_Plugin', 'on_activate' ) );
register_deactivation_hook( __FILE__, array( 'LSTAB_Plugin', 'on_deactivate' ) );
