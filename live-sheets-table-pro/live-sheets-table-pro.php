<?php
/**
 * Plugin Name:       Live Sheets Table Pro
 * Description:       Extends Live Sheets Table with private Google Sheets, filters, filter menus for visitors, colour rules, column looks, Excel, CSV and print downloads, six more table styles and updates as often as every 5 minutes.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.0
 * Requires Plugins:  live-sheets-table
 * Author:            Live Sheets Table
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       live-sheets-table-pro
 *
 * A separate plugin on purpose. WordPress.org does not allow paid code inside a
 * free plugin, nor a free plugin that downloads its paid half at runtime, so
 * everything here reaches the free plugin through its published hooks and
 * nothing in the free plugin knows this exists.
 *
 * @package LiveSheetsTablePro
 */

defined( 'ABSPATH' ) || exit;

define( 'LSTABP_VERSION', '1.0.0' );
define( 'LSTABP_FILE', __FILE__ );
define( 'LSTABP_PATH', plugin_dir_path( __FILE__ ) );
define( 'LSTABP_URL', plugin_dir_url( __FILE__ ) );

require_once LSTABP_PATH . 'includes/class-lstabp-styles.php';
require_once LSTABP_PATH . 'includes/class-lstabp-google-auth.php';
require_once LSTABP_PATH . 'includes/class-lstabp-private-sheets.php';
require_once LSTABP_PATH . 'includes/class-lstabp-filters.php';
require_once LSTABP_PATH . 'includes/class-lstabp-rules.php';
require_once LSTABP_PATH . 'includes/class-lstabp-xlsx.php';
require_once LSTABP_PATH . 'includes/class-lstabp-export.php';
require_once LSTABP_PATH . 'includes/class-lstabp-facets.php';
require_once LSTABP_PATH . 'includes/class-lstabp-column-looks.php';
require_once LSTABP_PATH . 'includes/class-lstabp-settings.php';
require_once LSTABP_PATH . 'includes/class-lstabp-plugin.php';

function lstabp() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new LSTABP_Plugin();
	}
	return $instance;
}

add_action( 'plugins_loaded', array( lstabp(), 'boot' ), 20 );
