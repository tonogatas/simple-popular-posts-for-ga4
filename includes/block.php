<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function spp_ga4_register_block() {
	// Explicitly register the editor script with dependencies to avoid "wp is not defined" or deprecation warnings
	// and ensure proper loading order.
	$script_handle = 'spp-ga4-ranking-editor-script';
	
	wp_register_script(
		$script_handle,
		plugins_url( 'assets/editor.js', dirname( __FILE__ ) ), // pointing to assets/editor.js from includes/block.php
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
		filemtime( SPP_GA4_PATH . 'assets/editor.js' ),
		true
	);

	// Register the block from metadata
	$aligned = register_block_type( SPP_GA4_PATH . 'assets/block.json', array(
		'render_callback' => 'spp_ga4_render_block'
	) );
	
	// Localize script
	$data = array(
		'labels' => array(
			'settings' => __( 'Settings', 'simple-popular-posts-for-ga4' ),
			'title' => __( 'Title:', 'simple-popular-posts-for-ga4' ),
			'title_tag' => __( 'Title Tag:', 'simple-popular-posts-for-ga4' ),
			'title_none' => __( 'No Title Output', 'simple-popular-posts-for-ga4' ),
			'limit' => __( 'Limit (1-20):', 'simple-popular-posts-for-ga4' ),
			'design_preset' => __( 'Design Preset:', 'simple-popular-posts-for-ga4' ),
			'preset_list' => __( 'Default (List)', 'simple-popular-posts-for-ga4' ),
			'preset_numbered' => __( 'Numbered List', 'simple-popular-posts-for-ga4' ),
			'preset_card' => __( 'Card Style', 'simple-popular-posts-for-ga4' ),
			'card_min_width' => __( 'Card Min Width (px):', 'simple-popular-posts-for-ga4' ),
			'card_min_width_help' => __( 'Minimum width of each card. Cards will expand to fill the available space.', 'simple-popular-posts-for-ga4' ),
			'time_range' => __( 'Time Range:', 'simple-popular-posts-for-ga4' ),
			'range_7d' => __( '7 Days (Weekly)', 'simple-popular-posts-for-ga4' ),
			'range_30d' => __( '30 Days (Monthly)', 'simple-popular-posts-for-ga4' ),
			'cat_id' => __( 'Category ID:', 'simple-popular-posts-for-ga4' ),
			'cat_id_help' => __( 'Default includes sub-categories. (Single ID only)', 'simple-popular-posts-for-ga4' ),
			'tag_id' => __( 'Tag ID:', 'simple-popular-posts-for-ga4' ),
			'tag_id_help' => __( '(Single ID only)', 'simple-popular-posts-for-ga4' ),
			'exclude_ids' => __( 'Exclude Post IDs (comma-separated):', 'simple-popular-posts-for-ga4' ),
			'exclude_help' => __( 'e.g. 123, 456 (Max 100 IDs)', 'simple-popular-posts-for-ga4' ),
			'filter_days' => __( 'Filter by Publish Date (days):', 'simple-popular-posts-for-ga4' ),
			'filter_help' => __( '0 for all time. e.g. 365 for 1 year.', 'simple-popular-posts-for-ga4' ),
			'shorten_title' => __( 'Shorten Title (chars):', 'simple-popular-posts-for-ga4' ),
			'shorten_help' => __( 'Truncate the post title to this number of characters. 0 to disable.', 'simple-popular-posts-for-ga4' ),
			'show_thumb' => __( 'Display Thumbnail', 'simple-popular-posts-for-ga4' ),
			'card_style_help' => __( 'Note: In Card Style, images are forced to 16:9 ratio. Set Thumbnail Size above to control resolution.', 'simple-popular-posts-for-ga4' ),
			'thumb_w' => __( 'Thumbnail Size (px):', 'simple-popular-posts-for-ga4' ) . ' W', // Contextually constructed
			'thumb_h' => __( 'H:', 'simple-popular-posts-for-ga4' ),
			'show_date' => __( 'Display Date', 'simple-popular-posts-for-ga4' ),
			'date_format' => __( 'Date Format:', 'simple-popular-posts-for-ga4' ),
			'fmt_wp_default' => __( 'WordPress Default', 'simple-popular-posts-for-ga4' ),
			'prevent_duplicates' => __( 'Prevent duplicates on the same page', 'simple-popular-posts-for-ga4' ),
		)
	);
	
	wp_localize_script( $script_handle, 'sppGa4RankData', $data );

	// Keep this as fallback for standard path if JSONs existed
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( $script_handle, 'simple-popular-posts-for-ga4', SPP_GA4_PATH . 'languages' );
	}
}
add_action( 'init', 'spp_ga4_register_block' );

function spp_ga4_render_block( $attributes ) {
	// Re-map block attributes to widget instance format
	$instance = array(
		'title'         => isset($attributes['title']) ? $attributes['title'] : '',
		'title_tag'     => isset($attributes['title_tag']) ? $attributes['title_tag'] : 'h2',
		'limit'         => isset($attributes['limit']) ? $attributes['limit'] : 5,
		'range'         => isset($attributes['range']) ? $attributes['range'] : '7d',
		'cat_id'        => isset($attributes['cat_id']) ? $attributes['cat_id'] : '',
		'tag_id'        => isset($attributes['tag_id']) ? $attributes['tag_id'] : '',
		'exclude_ids'   => isset($attributes['exclude_ids']) ? $attributes['exclude_ids'] : '',
		'filter_days'   => isset($attributes['filter_days']) ? $attributes['filter_days'] : 0,
		'shorten_title' => isset($attributes['shorten_title']) ? $attributes['shorten_title'] : 0,
		'show_thumb'    => isset($attributes['show_thumb']) ? $attributes['show_thumb'] : true,
		'show_date'     => isset($attributes['show_date']) ? $attributes['show_date'] : true,
		'thumb_w'       => isset($attributes['thumb_w']) ? $attributes['thumb_w'] : 150,
		'thumb_h'       => isset($attributes['thumb_h']) ? $attributes['thumb_h'] : 150,
		'style_preset'  => isset($attributes['style_preset']) ? $attributes['style_preset'] : 'list',
		'card_min_width'=> isset($attributes['card_min_width']) ? $attributes['card_min_width'] : 150,
		'date_format'   => isset($attributes['date_format']) ? $attributes['date_format'] : 'wp_default',
		'prevent_duplicates' => isset($attributes['prevent_duplicates']) ? $attributes['prevent_duplicates'] : false,
	);
	
	$cache_key = 'spp_ga4_block_' . md5( serialize( $instance ) );

	// Reuse the widget's generation logic
	if ( class_exists( 'SPP_GA4_Widget' ) ) {
		return SPP_GA4_Widget::generate_cache( $instance, $cache_key );
	} else {
		return 'SPP GA4 Widget class not found.';
	}
}
