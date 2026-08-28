<?php
/**
 * Plugin Name: Live Sheets Table – Pro preview (development only)
 * Description: Unlocks the Pro-only presets and limits so they can be reviewed without a licence. Do not ship this, and do not run it on a production site.
 * Version: 1.0.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 *
 * This is deliberately nothing but filter calls. It is the same surface the
 * real Pro add-on would use, so if this works, the extension points work.
 *
 * Install by copying this file into wp-content/mu-plugins/, or into
 * wp-content/plugins/ and activating it like any other plugin.
 *
 * @package LiveSheetsTable\Dev
 */

defined( 'ABSPATH' ) || exit;

// Flip the tier. This alone reveals the Pro presets in the picker and lets
// LSTAB_Styles::sanitize() render them.
add_filter( 'lstab_is_pro', '__return_true' );

// Lift the free limits so the rest of the surface can be exercised too.
add_filter(
	'lstab_max_sources',
	static function () {
		return 25;
	}
);

add_filter(
	'lstab_min_sync_interval',
	static function () {
		return 60;
	}
);

/**
 * Make it obvious which site this is running on.
 */
add_action(
	'admin_notices',
	static function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || false === strpos( (string) $screen->id, 'live-sheets-table' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>Live Sheets Table – Pro preview</strong> is active. '
			. 'Pro presets and limits are unlocked for evaluation. Remove this plugin before going live.</p></div>';
	}
);
