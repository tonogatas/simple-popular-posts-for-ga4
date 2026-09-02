<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode: [spp_ga4_ranking]
 * 
 * Attributes:
 * - title: Widget title (default: empty)
 * - limit: Number of posts (default: 5)
 * - range: '7d' or '30d' (default: '7d')
 * - cat_id: Category ID (default: empty)
 * - tag_id: Tag ID (default: empty)
 * - exclude_ids: Comma-separated Post IDs to exclude (default: empty)
 * - shorten_title: Max characters for title (default: 0 = no shorten)
 * - thumb_w: Thumbnail width (default: 150)
 * - thumb_h: Thumbnail height (default: 150)
 * - show_date: 1 to show date, 0 to hide (default: 0)
 * - show_thumb: 1 to show thumb, 0 to hide (default: 1)
 * - style_preset: 'list', 'numbered', 'card' (default: 'list')
 */
function spp_ga4_ranking_shortcode_handler( $atts ) {
	$atts = shortcode_atts( array(
		'title'         => '',
		'limit'         => 5,
		'range'         => '7d',
		'cat_id'        => '',
		'tag_id'        => '',
		'exclude_ids'   => '',
		'shorten_title' => 0,
		'thumb_w'       => 150,
		'thumb_h'       => 150,
		'show_date'     => 0,
		'show_thumb'    => 1,
		'filter_days'   => 0,
		'style_preset'  => 'list',
		'title_tag'     => 'h2',
		'prevent_duplicates' => 0,
	), $atts, 'spp_ga4_ranking' );

	// Prepare instance array for the Widget's generator
	$instance = $atts;
	
	// Ensure types match what strict checks might expect (though widget checks empty/isset)
	$instance['limit'] = intval( $instance['limit'] );
	$instance['shorten_title'] = intval( $instance['shorten_title'] );
	$instance['filter_days'] = intval( $instance['filter_days'] );
	$instance['thumb_w'] = intval( $instance['thumb_w'] );
	$instance['thumb_h'] = intval( $instance['thumb_h'] );
	$instance['show_date'] = (bool) $instance['show_date'];
	$instance['show_thumb'] = (bool) $instance['show_thumb'];
	$instance['prevent_duplicates'] = (bool) $instance['prevent_duplicates'];

	// Generate a unique cache key for this specific shortcode configuration
	// We use a different prefix to avoid colliding with numbered widget instances
	$cache_key = 'spp_ga4_sc_' . md5( serialize( $instance ) );

	if ( class_exists( 'SPP_GA4_Widget' ) ) {
		// Use the Widget's static method to generate HTML.
		// Passing empty args uses the default widget wrappers defined in generate_cache.
		return SPP_GA4_Widget::generate_cache( $instance, $cache_key );
	}

	return '';
}
add_shortcode( 'spp_ga4_ranking', 'spp_ga4_ranking_shortcode_handler' );
