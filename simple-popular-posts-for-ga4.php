<?php
/**
 * Plugin Name:       Simple Popular Posts for GA4
 * Plugin URI:        https://github.com/tonogatas/simple-popular-posts-for-ga4
 * Description:       A simple popular posts widget that retrieves ranking data from Google Analytics 4 (GA4).
 * Version:           0.1.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            tonogata
 * Author URI:        https://www.bousaid.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-popular-posts-for-ga4
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants
define( 'SPP_GA4_VERSION', '0.1.2' );
define( 'SPP_GA4_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPP_GA4_URL', plugin_dir_url( __FILE__ ) );

// Includes
require_once SPP_GA4_PATH . 'includes/encryption.php';
require_once SPP_GA4_PATH . 'includes/data-fetcher.php';
require_once SPP_GA4_PATH . 'includes/admin-settings.php';
require_once SPP_GA4_PATH . 'includes/widget.php';
require_once SPP_GA4_PATH . 'includes/shortcode.php';
require_once SPP_GA4_PATH . 'includes/block.php';

// Enqueue Scripts
add_action( 'wp_enqueue_scripts', 'spp_ga4_enqueue_scripts' );
function spp_ga4_enqueue_scripts() {
	// Check option to disable CSS
	if ( get_option( 'spp_ga4_disable_css' ) !== '1' ) {
		wp_enqueue_style( 'spp-ga4-style', SPP_GA4_URL . 'assets/style.css', array(), SPP_GA4_VERSION );
	}
}

// Load Text Domain
add_action( 'plugins_loaded', 'spp_ga4_load_textdomain' );
function spp_ga4_load_textdomain() {
	load_plugin_textdomain( 'simple-popular-posts-for-ga4', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
}

// Activation Hook
register_activation_hook( __FILE__, 'spp_ga4_activate' );

// Add Settings Link to Plugins Page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'spp_ga4_add_settings_link' );
function spp_ga4_add_settings_link( $links ) {
	$settings_link = '<a href="options-general.php?page=spp-ga4-settings">' . esc_html__( 'Settings', 'simple-popular-posts-for-ga4' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}

function spp_ga4_activate() {
	// Check if Cron is enabled (Default to '0' / Disabled)
	if ( get_option( 'spp_ga4_cron_enabled', '0' ) !== '1' ) {
		return;
	}

	if ( ! wp_next_scheduled( 'spp_ga4_daily_event' ) ) {
		$time_str = get_option( 'spp_ga4_cron_time', '00:00' );
		// Calculate timestamp for next occurrence of the specified time (in WP local time)
		// We use current_time('timestamp') which accounts for timezone, but wp_schedule_event expects a Unix timestamp (UTC-ish if not careful, but WP usually handles local offset if passing time based on it).
		// Best practice: passing a timestamp that represents "Next X:00".
		
		// If explicit time set:
		if ( $time_str ) {
			// Parse HH:mm
			$parts = explode( ':', $time_str );
			$hour = isset( $parts[0] ) ? intval( $parts[0] ) : 0;
			$minute = isset( $parts[1] ) ? intval( $parts[1] ) : 0;
			
			// Get timestamp for today at that time
			// current_datetime() returns DateTimeImmutable with WP timezone.
			$dt = current_datetime()->setTime( $hour, $minute, 0 );
			
			// If already passed today, move to tomorrow
			if ( $dt->getTimestamp() < time() ) {
				$dt = $dt->modify( '+1 day' );
			}
			$schedule_time = $dt->getTimestamp();
		} else {
			// Fallback (should not happen with sanitizer, but just in case)
			$schedule_time = time();
		}
		
		wp_schedule_event( $schedule_time, 'daily', 'spp_ga4_daily_event' );
	}
}

// Deactivation Hook
register_deactivation_hook( __FILE__, 'spp_ga4_deactivate' );
function spp_ga4_deactivate() {
	wp_clear_scheduled_hook( 'spp_ga4_daily_event' );
}
