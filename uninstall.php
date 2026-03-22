<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if "Delete on Uninstall" option is enabled
if ( get_option( 'spp_ga4_delete_on_uninstall', '1' ) === '1' ) {
	global $wpdb;

	// 1. Delete all plugin options
	delete_option( 'spp_ga4_service_account_json' );
	delete_option( 'spp_ga4_property_id' );
	delete_option( 'spp_ga4_cron_time' );
	delete_option( 'spp_ga4_delete_on_uninstall' );
	delete_option( 'spp_ga4_last_fetch_time' );
	delete_option( 'spp_ga4_last_run_stats' );
	
	// Delete Widget option (base ID)
	delete_option( 'widget_spp_ga4_widget' );

	// 2. Delete all Post Meta keys
	delete_post_meta_by_key( '_spp_ga4_pv_7d' );
	delete_post_meta_by_key( '_spp_ga4_pv_30d' );

	// 3. Clear Transients
	delete_transient( 'spp_ga4_fetch_lock' );
	
	// If the server uses an external object cache, transients are stored there, so the DB query isn't needed.
	if ( ! wp_using_ext_object_cache() ) {
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_spp_ga4_%' OR option_name LIKE '_transient_timeout_spp_ga4_%'" );
	}
}

// Clear Schedule (Always good practice, though register_deactivation_hook should have handled it)
wp_clear_scheduled_hook( 'spp_ga4_daily_event' );
