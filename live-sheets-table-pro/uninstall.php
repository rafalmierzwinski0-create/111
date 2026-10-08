<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function lstabp_uninstall_site( $everything ) {
	global $wpdb;

	delete_option( 'lstabp_google_token' );

	$like = $wpdb->esc_like( '_transient_lstabp_oauth_state_' ) . '%';
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-time cleanup; there is no API for a prefix of transients.
		$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $wpdb->esc_like( '_transient_timeout_lstabp_oauth_state_' ) . '%' )
	);

	delete_option( 'lstabp_known_sources' );

	if ( ! $everything ) {
		return;
	}

	delete_option( 'lstabp_google_client' );
	delete_option( 'lstabp_column_looks' );
	delete_option( 'lstabp_rules' );
	delete_option( 'lstabp_facets' );
	delete_option( 'lstabp_export_sources' );
	delete_option( 'lstabp_private_sources' );
}

function lstabp_uninstall_wanted() {
	$settings = get_option( 'lstab_settings' );

	return is_array( $settings ) && ! empty( $settings['delete_on_uninstall'] );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $lstabp_site_id ) {
		switch_to_blog( $lstabp_site_id );
		lstabp_uninstall_site( lstabp_uninstall_wanted() );
		restore_current_blog();
	}
} else {
	lstabp_uninstall_site( lstabp_uninstall_wanted() );
}
