<?php
/**
 * Runs when the add-on is deleted from the Plugins screen.
 *
 * Two different kinds of thing are stored here and they are not treated alike.
 *
 * The Google token is a credential: a key that opens the spreadsheets of
 * whoever connected the account, for as long as it exists, without asking
 * anybody for a password. Leaving one behind in a database after the code that
 * used it has gone is how a stolen backup turns into a stolen Google account
 * years later. It goes, always, whatever the settings say. Reconnecting is two
 * clicks for somebody who comes back.
 *
 * Everything else — the Google application's own details, colour rules,
 * filters, which sources are private, which may be exported — is work somebody
 * did, and deleting a plugin to reinstall it is a normal thing to do. Those
 * follow the free plugin's "delete everything when I remove the plugin"
 * setting, so the two halves answer the same question the same way.
 *
 * Google itself is never touched. Revoking the connection at Google's end is
 * the account owner's to do, at myaccount.google.com; a plugin being deleted
 * cannot ask for it.
 *
 * @package LiveSheetsTablePro
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clear this add-on's data from the site being uninstalled from.
 *
 * @param bool $everything Whether the site asked for its settings to go too.
 * @return void
 */
function lstabp_uninstall_site( $everything ) {
	global $wpdb;

	// The credential. Always.
	delete_option( 'lstabp_google_token' );

	/*
	 * The half-finished handshakes of anybody who was mid-connection. They
	 * expire on their own within the hour, but there is no reason to leave
	 * rows behind that name a user and mean nothing.
	 */
	$like = $wpdb->esc_like( '_transient_lstabp_oauth_state_' ) . '%';
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-time cleanup; there is no API for a prefix of transients.
		$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $wpdb->esc_like( '_transient_timeout_lstabp_oauth_state_' ) . '%' )
	);

	if ( ! $everything ) {
		return;
	}

	delete_option( 'lstabp_google_client' );
	delete_option( 'lstabp_rules' );
	delete_option( 'lstabp_facets' );
	delete_option( 'lstabp_export_sources' );
	delete_option( 'lstabp_private_sources' );
}

/**
 * Whether this site asked for everything to go.
 *
 * Read straight from the option rather than through the free plugin's class:
 * the free plugin may already have been deleted, and a missing setting means
 * nobody asked for anything to be removed.
 *
 * @return bool
 */
function lstabp_uninstall_wanted() {
	$settings = get_option( 'lstab_settings' );

	return is_array( $settings ) && ! empty( $settings['delete_on_uninstall'] );
}

if ( is_multisite() ) {
	/*
	 * A network holds one of these per site, and a token left on the site
	 * nobody was looking at is the same key as one left on the site they
	 * were.
	 */
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $lstabp_site_id ) {
		switch_to_blog( $lstabp_site_id );
		lstabp_uninstall_site( lstabp_uninstall_wanted() );
		restore_current_blog();
	}
} else {
	lstabp_uninstall_site( lstabp_uninstall_wanted() );
}
