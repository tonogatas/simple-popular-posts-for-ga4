<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPP_GA4_Widget extends WP_Widget {

	public static $shared_exclude_ids = array();

	public function __construct() {
		parent::__construct(
			'spp_ga4_widget',
			__( 'Simple Popular Posts (GA4)', 'simple-popular-posts-for-ga4' ),
			array( 'description' => __( 'Displays popular posts based on GA4 data.', 'simple-popular-posts-for-ga4' ) )
		);
	}

	public function widget( $args, $instance ) {
		$base_cache_key = 'spp_ga4_cache_' . $this->number . '_' . md5( serialize( $instance ) );
		echo wp_kses_post( self::generate_cache( $instance, $base_cache_key, $args ) );
	}

	/**
	 * Generate and Cache Widget HTML.
	 */
	public static function generate_cache( $instance, $cache_key, $args = array() ) {
		$prevent_duplicates = ! empty( $instance['prevent_duplicates'] ) ? (bool) $instance['prevent_duplicates'] : false;

		if ( $prevent_duplicates && ! empty( self::$shared_exclude_ids ) ) {
			$cache_key .= '_' . md5( serialize( self::$shared_exclude_ids ) );
		}

		if ( ! is_customize_preview() && ! is_admin() ) {
			$cached_output = get_transient( $cache_key );
			if ( is_array( $cached_output ) && isset( $cached_output['html'] ) && isset( $cached_output['post_ids'] ) ) {
				self::$shared_exclude_ids = array_merge( self::$shared_exclude_ids, $cached_output['post_ids'] );
				return $cached_output['html'];
			}
		}

		// Initialize style_preset early for use in default args
		$style_preset = ! empty( $instance['style_preset'] ) ? $instance['style_preset'] : 'list';

		// Defaults for args if called from cron (empty)
		if ( empty( $args ) ) {
			$args = array(
				'before_widget' => '<div class="widget spp-ga4-widget spp-ga4-preset-' . esc_attr( $style_preset ) . '">',
				'after_widget'  => '</div>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			);
		}

		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$title_tag = ! empty( $instance['title_tag'] ) ? $instance['title_tag'] : 'h2';

		// Override args if custom tag is selected (and not empty args)
		if ( ! empty( $title_tag ) && in_array( $title_tag, array( 'h2', 'h3', 'h4', 'span' ) ) ) {
			$args['before_title'] = '<' . $title_tag . ' class="widget-title">';
			$args['after_title']  = '</' . $title_tag . '>';
		}
		$limit = ! empty( $instance['limit'] ) ? intval( $instance['limit'] ) : 5;
		$range = ! empty( $instance['range'] ) ? $instance['range'] : '7d'; // 7d or 30d
		$cat_id = ! empty( $instance['cat_id'] ) ? intval( $instance['cat_id'] ) : '';
		$tag_id = ! empty( $instance['tag_id'] ) ? intval( $instance['tag_id'] ) : '';
		$exclude_ids_str = ! empty( $instance['exclude_ids'] ) ? $instance['exclude_ids'] : '';
		$shorten_title = ! empty( $instance['shorten_title'] ) ? intval( $instance['shorten_title'] ) : 0;
		$thumb_w = ! empty( $instance['thumb_w'] ) ? intval( $instance['thumb_w'] ) : 150;
		$thumb_h = ! empty( $instance['thumb_h'] ) ? intval( $instance['thumb_h'] ) : 150;
		$show_date = isset( $instance['show_date'] ) ? (bool) $instance['show_date'] : true;
		$date_format = ! empty( $instance['date_format'] ) ? $instance['date_format'] : 'wp_default';
		$show_thumb = isset( $instance['show_thumb'] ) ? (bool) $instance['show_thumb'] : true; // Default ON
		$filter_days = ! empty( $instance['filter_days'] ) ? intval( $instance['filter_days'] ) : 0;
		$style_preset = ! empty( $instance['style_preset'] ) ? $instance['style_preset'] : 'list';

		$meta_key = '_spp_ga4_pv_' . $range;

		$query_args = array(
			'post_type'      => 'post',
			'posts_per_page' => $limit,
			'meta_key'       => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'ignore_sticky_posts' => true,
		);

		if ( $cat_id ) {
			$query_args['cat'] = $cat_id;
		}
		if ( $tag_id ) {
			$query_args['tag_id'] = $tag_id;
		}

		// Process Exclude IDs
		$exclude_ids = array();
		if ( ! empty( $exclude_ids_str ) ) {
			$exclude_ids = array_map( 'intval', explode( ',', $exclude_ids_str ) );
			$exclude_ids = array_filter( $exclude_ids ); // Remove 0s
		}
		
		if ( $prevent_duplicates && ! empty( self::$shared_exclude_ids ) ) {
			$exclude_ids = array_merge( $exclude_ids, self::$shared_exclude_ids );
			$exclude_ids = array_unique( $exclude_ids );
		}

		// Safety Limit in Logic as well
		if ( count( $exclude_ids ) > 100 ) {
			$exclude_ids = array_slice( $exclude_ids, 0, 100 );
		}

		if ( ! empty( $exclude_ids ) ) {
			$query_args['post__not_in'] = $exclude_ids; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
		}

		// Filter by Post Date (Published within X days)
		if ( $filter_days > 0 ) {
			$query_args['date_query'] = array(
				array(
					'after' => $filter_days . ' days ago',
					'inclusive' => true,
				),
			);
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
		$rank_query = new WP_Query( $query_args );

		ob_start();
		
		// Inject preset class if using default wrapper matches
		// Note: Themes often provide their own before_widget with dynamic classes.
		// Instead of modifying before_widget via regex, we wrap the internal list in a div with the preset class.
		
		echo wp_kses_post( $args['before_widget'] );
		if ( ! empty( $title ) && $title_tag !== 'none' ) { // Only show title if set and not disabled
			echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
		}
		
		echo '<div class="spp-ga4-container spp-ga4-preset-' . esc_attr( $style_preset ) . '">';

		if ( $rank_query->have_posts() ) {
			$list_style = '';
			if ( $style_preset === 'card' ) {
				$min_w = ! empty( $instance['card_min_width'] ) ? intval( $instance['card_min_width'] ) : 150;
				$list_style = ' style="grid-template-columns: repeat(auto-fill, minmax(' . $min_w . 'px, 1fr));"';
			}
			echo wp_kses_post( '<ul class="wpp-list"' . $list_style . '>' ); 
			
			$rendered_ids = array();
			while ( $rank_query->have_posts() ) {
				$rank_query->the_post();
				$rendered_ids[] = get_the_ID();
				$post_title = get_the_title();
				
				// Fix: UTF-8 for Japanese
				if ( $shorten_title > 0 && mb_strlen( $post_title, 'UTF-8' ) > $shorten_title ) {
					$post_title = mb_substr( $post_title, 0, $shorten_title, 'UTF-8' ) . '...';
				}
				
				// Thumbnail with enforced style
				$thumb = '';
				if ( $show_thumb && has_post_thumbnail() ) {
					$thumb = get_the_post_thumbnail( get_the_ID(), array( $thumb_w, $thumb_h ), array( 
						'class' => 'wpp-thumbnail',
						'alt'   => get_the_title(),
					) );
				}

				echo '<li>';
				if ( $thumb ) {
					echo '<a href="' . esc_url( get_permalink() ) . '">' . wp_kses_post( $thumb ) . '</a>';
				}

				echo '<div class="wpp-content">';
				echo '<a href="' . esc_url( get_permalink() ) . '" class="wpp-post-title" title="' . esc_attr( get_the_title() ) . '">' . esc_html( $post_title ) . '</a>';
				
				// Date Display
				if ( $show_date ) {
					$d_format = $date_format;
					if ( $d_format === 'wp_default' ) {
						$d_format = get_option( 'date_format' );
					}
					echo '<span class="post-date">' . get_the_date( $d_format ) . '</span>';
				}
				echo '</div>'; // End wpp-content
				
				echo '</li>';
			}
			echo '</ul>';
			wp_reset_postdata();
		} else {
			echo '<p>' . esc_html__( 'No data available.', 'simple-popular-posts-for-ga4' ) . '</p>';
		}
		echo '</div>'; // End container
		
		echo wp_kses_post( $args['after_widget'] );

		$output = ob_get_clean();

		self::$shared_exclude_ids = array_unique( array_merge( self::$shared_exclude_ids, isset( $rendered_ids ) ? $rendered_ids : array() ) );

		// Save to Transient
		if ( ! is_customize_preview() && ! is_admin() ) {
			set_transient( $cache_key, array( 'html' => $output, 'post_ids' => isset( $rendered_ids ) ? $rendered_ids : array() ), 25 * HOUR_IN_SECONDS );
		}

		return $output;
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$limit = ! empty( $instance['limit'] ) ? $instance['limit'] : 5;
		$range = ! empty( $instance['range'] ) ? $instance['range'] : '7d';
		$cat_id = ! empty( $instance['cat_id'] ) ? $instance['cat_id'] : '';
		$tag_id = ! empty( $instance['tag_id'] ) ? $instance['tag_id'] : '';
		$exclude_ids = ! empty( $instance['exclude_ids'] ) ? $instance['exclude_ids'] : '';
		$shorten_title = ! empty( $instance['shorten_title'] ) ? $instance['shorten_title'] : '';
		$thumb_w = ! empty( $instance['thumb_w'] ) ? $instance['thumb_w'] : 150;
		$thumb_h = ! empty( $instance['thumb_h'] ) ? $instance['thumb_h'] : 150;
		$show_date = isset( $instance['show_date'] ) ? (bool) $instance['show_date'] : true;
		$date_format = ! empty( $instance['date_format'] ) ? $instance['date_format'] : 'wp_default';
		$prevent_duplicates = isset( $instance['prevent_duplicates'] ) ? (bool) $instance['prevent_duplicates'] : false;

		$show_thumb = isset( $instance['show_thumb'] ) ? (bool) $instance['show_thumb'] : true; // Default ON
		$filter_days = ! empty( $instance['filter_days'] ) ? intval( $instance['filter_days'] ) : 0;
		$style_preset = ! empty( $instance['style_preset'] ) ? $instance['style_preset'] : 'list';
		$title_tag = ! empty( $instance['title_tag'] ) ? $instance['title_tag'] : 'h2';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'simple-popular-posts-for-ga4' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title_tag' ) ); ?>"><?php esc_html_e( 'Title Tag:', 'simple-popular-posts-for-ga4' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title_tag' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title_tag' ) ); ?>">
				<option value="h2" <?php selected( $title_tag, 'h2' ); ?>>H2</option>
				<option value="h3" <?php selected( $title_tag, 'h3' ); ?>>H3</option>
				<option value="h4" <?php selected( $title_tag, 'h4' ); ?>>H4</option>
				<option value="span" <?php selected( $title_tag, 'span' ); ?>>span</option>
				<option value="none" <?php selected( $title_tag, 'none' ); ?>><?php esc_html_e( 'No Title Output', 'simple-popular-posts-for-ga4' ); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'style_preset' ) ); ?>"><?php esc_html_e( 'Design Preset:', 'simple-popular-posts-for-ga4' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'style_preset' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'style_preset' ) ); ?>">
				<option value="list" <?php selected( $style_preset, 'list' ); ?>><?php esc_html_e( 'Default (List)', 'simple-popular-posts-for-ga4' ); ?></option>
				<option value="numbered" <?php selected( $style_preset, 'numbered' ); ?>><?php esc_html_e( 'Numbered List', 'simple-popular-posts-for-ga4' ); ?></option>
				<option value="card" <?php selected( $style_preset, 'card' ); ?>><?php esc_html_e( 'Card Style', 'simple-popular-posts-for-ga4' ); ?></option>
			</select>
			<br><small><?php esc_html_e( 'Note: In Card Style, images are forced to 16:9 ratio. Set Thumbnail Size above to control resolution.', 'simple-popular-posts-for-ga4' ); ?></small>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'card_min_width' ) ); ?>"><?php esc_html_e( 'Card Min Width (px):', 'simple-popular-posts-for-ga4' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'card_min_width' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'card_min_width' ) ); ?>" type="number" min="100" value="<?php echo ! empty( $instance['card_min_width'] ) ? esc_attr( $instance['card_min_width'] ) : 150; ?>">
			<br><small><?php esc_html_e( 'Minimum width of each card. Cards will expand to fill the available space.', 'simple-popular-posts-for-ga4' ); ?></small>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( 'Limit (1-20):', 'simple-popular-posts-for-ga4' ); ?></label>
			<span class="dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Number of posts to display in the ranking.', 'simple-popular-posts-for-ga4' ); ?>" style="color:#888; font-size:16px; margin-top:2px; cursor:help;"></span>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="number" min="1" max="20" value="<?php echo esc_attr( $limit ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'range' ) ); ?>"><?php esc_html_e( 'Time Range:', 'simple-popular-posts-for-ga4' ); ?></label>
			<span class="dashicons dashicons-editor-help" title="<?php esc_attr_e( 'The period for which GA4 data is aggregated (7 days or 30 days).', 'simple-popular-posts-for-ga4' ); ?>" style="color:#888; font-size:16px; margin-top:2px; cursor:help;"></span>
			<select id="<?php echo esc_attr( $this->get_field_id( 'range' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'range' ) ); ?>">
				<option value="7d" <?php selected( $range, '7d' ); ?>><?php esc_html_e( '7 Days (Weekly)', 'simple-popular-posts-for-ga4' ); ?></option>
				<option value="30d" <?php selected( $range, '30d' ); ?>><?php esc_html_e( '30 Days (Monthly)', 'simple-popular-posts-for-ga4' ); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'cat_id' ) ); ?>"><?php esc_html_e( 'Category ID:', 'simple-popular-posts-for-ga4' ); ?></label>
			<span class="dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Enter Category ID to filter posts. Blank for all categories.', 'simple-popular-posts-for-ga4' ); ?>" style="color:#888; font-size:16px; margin-top:2px; cursor:help;"></span>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'cat_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'cat_id' ) ); ?>" type="text" value="<?php echo esc_attr( $cat_id ); ?>">
			<br><small><?php esc_html_e( 'Default includes sub-categories. (Single ID only)', 'simple-popular-posts-for-ga4' ); ?></small>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'tag_id' ) ); ?>"><?php esc_html_e( 'Tag ID:', 'simple-popular-posts-for-ga4' ); ?></label>
			<span class="dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Enter Tag ID to filter posts. Blank for all tags.', 'simple-popular-posts-for-ga4' ); ?>" style="color:#888; font-size:16px; margin-top:2px; cursor:help;"></span>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'tag_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'tag_id' ) ); ?>" type="text" value="<?php echo esc_attr( $tag_id ); ?>">
			<br><small><?php esc_html_e( '(Single ID only)', 'simple-popular-posts-for-ga4' ); ?></small>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'exclude_ids' ) ); ?>"><?php esc_html_e( 'Exclude Post IDs (comma-separated):', 'simple-popular-posts-for-ga4' ); ?></label>
			<span class="dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Specify Post IDs to exclude from the ranking.', 'simple-popular-posts-for-ga4' ); ?>" style="color:#888; font-size:16px; margin-top:2px; cursor:help;"></span>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'exclude_ids' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'exclude_ids' ) ); ?>" type="text" value="<?php echo esc_attr( $exclude_ids ); ?>">
			<br><small><?php esc_html_e( 'e.g. 123, 456 (Max 100 IDs)', 'simple-popular-posts-for-ga4' ); ?></small>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'filter_days' ) ); ?>"><?php esc_html_e( 'Filter by Publish Date (days):', 'simple-popular-posts-for-ga4' ); ?></label>
			<span class="dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Exclude articles older than X days from the ranking. Set to 0 to include all articles.', 'simple-popular-posts-for-ga4' ); ?>" style="color:#888; font-size:16px; margin-top:2px; cursor:help;"></span>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'filter_days' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'filter_days' ) ); ?>" type="number" min="0" value="<?php echo esc_attr( $filter_days ); ?>">
			<br><small><?php esc_html_e( '0 for all time. e.g. 365 for 1 year.', 'simple-popular-posts-for-ga4' ); ?></small>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'shorten_title' ) ); ?>"><?php esc_html_e( 'Shorten Title (chars):', 'simple-popular-posts-for-ga4' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'shorten_title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'shorten_title' ) ); ?>" type="number" min="0" value="<?php echo esc_attr( $shorten_title ); ?>">
			<br><small><?php esc_html_e( 'Truncate the post title to this number of characters. 0 to disable.', 'simple-popular-posts-for-ga4' ); ?></small>

		</p>
		<p>
			<input class="checkbox" type="checkbox" <?php checked( $prevent_duplicates ); ?> id="<?php echo esc_attr( $this->get_field_id( 'prevent_duplicates' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'prevent_duplicates' ) ); ?>" />
			<label for="<?php echo esc_attr( $this->get_field_id( 'prevent_duplicates' ) ); ?>"><?php esc_html_e( 'Prevent duplicates on the same page', 'simple-popular-posts-for-ga4' ); ?></label>
		</p>
		<p>
			<input class="checkbox" type="checkbox" <?php checked( $show_thumb ); ?> id="<?php echo esc_attr( $this->get_field_id( 'show_thumb' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_thumb' ) ); ?>" />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_thumb' ) ); ?>"><?php esc_html_e( 'Display Thumbnail', 'simple-popular-posts-for-ga4' ); ?></label>
		</p>
		<p>
			<label><?php esc_html_e( 'Thumbnail Size (px):', 'simple-popular-posts-for-ga4' ); ?></label><br>
			<label for="<?php echo esc_attr( $this->get_field_id( 'thumb_w' ) ); ?>"><?php esc_html_e( 'W:', 'simple-popular-posts-for-ga4' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'thumb_w' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'thumb_w' ) ); ?>" type="number" value="<?php echo esc_attr( $thumb_w ); ?>">
			<label for="<?php echo esc_attr( $this->get_field_id( 'thumb_h' ) ); ?>"><?php esc_html_e( 'H:', 'simple-popular-posts-for-ga4' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'thumb_h' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'thumb_h' ) ); ?>" type="number" value="<?php echo esc_attr( $thumb_h ); ?>">
		</p>
		<p>
			<input class="checkbox" type="checkbox" <?php checked( $show_date ); ?> id="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_date' ) ); ?>" />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>"><?php esc_html_e( 'Display Date', 'simple-popular-posts-for-ga4' ); ?></label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'date_format' ) ); ?>"><?php esc_html_e( 'Date Format:', 'simple-popular-posts-for-ga4' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'date_format' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'date_format' ) ); ?>">
				<option value="wp_default" <?php selected( $date_format, 'wp_default' ); ?>><?php esc_html_e( 'WordPress Default', 'simple-popular-posts-for-ga4' ); ?></option>
				<option value="Y/m/d" <?php selected( $date_format, 'Y/m/d' ); ?>><?php echo esc_html( wp_date( 'Y/m/d' ) ); ?></option>
				<option value="Y-m-d" <?php selected( $date_format, 'Y-m-d' ); ?>><?php echo esc_html( wp_date( 'Y-m-d' ) ); ?></option>
				<option value="d/m/Y" <?php selected( $date_format, 'd/m/Y' ); ?>><?php echo esc_html( wp_date( 'd/m/Y' ) ); ?></option>
				<option value="F j, Y" <?php selected( $date_format, 'F j, Y' ); ?>><?php echo esc_html( wp_date( 'F j, Y' ) ); ?></option>
			</select>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['limit'] = ( ! empty( $new_instance['limit'] ) ) ? intval( $new_instance['limit'] ) : 5;
		$instance['range'] = ( ! empty( $new_instance['range'] ) ) ? sanitize_text_field( $new_instance['range'] ) : '7d';
		$instance['cat_id'] = ( ! empty( $new_instance['cat_id'] ) ) ? intval( $new_instance['cat_id'] ) : '';
		$instance['tag_id'] = ( ! empty( $new_instance['tag_id'] ) ) ? intval( $new_instance['tag_id'] ) : '';
		
		// Sanitize and Limit Exclude IDs to 100
		$exclude_ids_raw = ( ! empty( $new_instance['exclude_ids'] ) ) ? sanitize_text_field( $new_instance['exclude_ids'] ) : '';
		if ( ! empty( $exclude_ids_raw ) ) {
			$ids = array_map( 'trim', explode( ',', $exclude_ids_raw ) );
			$ids = array_filter( $ids, 'is_numeric' ); // Ensure numeric
			if ( count( $ids ) > 100 ) {
				$ids = array_slice( $ids, 0, 100 ); // Truncate to 100
			}
			$instance['exclude_ids'] = implode( ',', $ids );
		} else {
			$instance['exclude_ids'] = '';
		}

		$instance['filter_days'] = ! empty( $new_instance['filter_days'] ) ? max( 0, intval( $new_instance['filter_days'] ) ) : 0;
		
		$valid_presets = array( 'list', 'numbered', 'card' );
		$instance['style_preset'] = ( ! empty( $new_instance['style_preset'] ) && in_array( $new_instance['style_preset'], $valid_presets ) ) ? $new_instance['style_preset'] : 'list';
		$instance['card_min_width'] = ( ! empty( $new_instance['card_min_width'] ) ) ? intval( $new_instance['card_min_width'] ) : 150;

		$valid_tags = array( 'h2', 'h3', 'h4', 'span', 'none' );
		$instance['title_tag'] = ( ! empty( $new_instance['title_tag'] ) && in_array( $new_instance['title_tag'], $valid_tags ) ) ? $new_instance['title_tag'] : 'h2';

		// Ensure non-negative
		$instance['shorten_title'] = ( ! empty( $new_instance['shorten_title'] ) ) ? max( 0, intval( $new_instance['shorten_title'] ) ) : 0;
		
		$instance['thumb_w'] = ( ! empty( $new_instance['thumb_w'] ) ) ? intval( $new_instance['thumb_w'] ) : 150;
		$instance['thumb_h'] = ( ! empty( $new_instance['thumb_h'] ) ) ? intval( $new_instance['thumb_h'] ) : 150;
		$instance['show_date'] = ( ! empty( $new_instance['show_date'] ) ) ? (bool) $new_instance['show_date'] : false;
		$instance['date_format'] = ( ! empty( $new_instance['date_format'] ) ) ? sanitize_text_field( $new_instance['date_format'] ) : 'wp_default';
		$instance['show_thumb'] = isset( $new_instance['show_thumb'] ) ? (bool) $new_instance['show_thumb'] : false;
		$instance['prevent_duplicates'] = isset( $new_instance['prevent_duplicates'] ) ? (bool) $new_instance['prevent_duplicates'] : false;

		// Clear cache using the unified key structure
		delete_transient( 'spp_ga4_cache_' . $this->number );

		return $instance;
	}
}

function spp_ga4_register_widget() {
	register_widget( 'SPP_GA4_Widget' );
}
add_action( 'widgets_init', 'spp_ga4_register_widget' );
