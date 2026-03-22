<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPP_GA4_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		// Hook to reschedule when cron time option changes
		add_action( 'update_option_spp_ga4_cron_time', array( $this, 'reschedule_cron_event' ), 10, 2 );
	}

	public function add_plugin_page() {
		add_options_page(
			__( 'Simple Popular Posts for GA4', 'simple-popular-posts-for-ga4' ),
			__( 'Simple Popular Posts', 'simple-popular-posts-for-ga4' ),
			'manage_options',
			'spp-ga4-settings',
			array( $this, 'create_admin_page' )
		);
	}

	public function create_admin_page() {
		$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'settings';
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Simple Popular Posts for GA4 Settings', 'simple-popular-posts-for-ga4' ); ?></h1>
			
			<h2 class="nav-tab-wrapper">
				<a href="?page=spp-ga4-settings&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Settings', 'simple-popular-posts-for-ga4' ); ?></a>
				<a href="?page=spp-ga4-settings&tab=docs" class="nav-tab <?php echo $active_tab === 'docs' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Usage', 'simple-popular-posts-for-ga4' ); ?></a>
			</h2>

			<?php if ( $active_tab === 'settings' ) : ?>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'spp_ga4_option_group' );
					do_settings_sections( 'spp-ga4-settings' );
					submit_button();
					?>
				</form>
				
				<hr>

				<div class="spp-ga4-manual-fetch-panel" style="background: #fff; padding: 20px; border: 1px solid #ccc; margin-top: 20px;">
					<h3><?php echo esc_html__( 'Manual Data Fetch', 'simple-popular-posts-for-ga4' ); ?></h3>
					<p><?php echo esc_html__( 'Fetch latest ranking data from GA4 immediately (Recommended: once a day).', 'simple-popular-posts-for-ga4' ); ?></p>
					<form method="post" action="">
						<?php wp_nonce_field( 'spp_ga4_manual_fetch', 'spp_ga4_fetch_nonce' ); ?>
						<input type="hidden" name="action" value="spp_ga4_manual_fetch">
						<?php submit_button( __( 'Fetch Data Now', 'simple-popular-posts-for-ga4' ), 'secondary', 'manual_fetch', true ); ?>
					</form>
				</div>
				
				<br>
				
				<div class="spp-ga4-status-panel" style="background: #fff; padding: 20px; border: 1px solid #ccc; margin-top: 20px;">
					<h3><?php echo esc_html__( 'System Status', 'simple-popular-posts-for-ga4' ); ?></h3>
					<?php $this->render_system_status(); ?>
				</div>

				<br>
				
				<div class="spp-ga4-notes" style="background: #fff; padding: 20px; border: 1px solid #ccc; max-width: 800px; margin-top: 20px;">
					<h3><?php echo esc_html__( 'Notes & Limitations', 'simple-popular-posts-for-ga4' ); ?></h3>
					<ul style="list-style-type: disc; padding-left: 20px;">
						<li><?php _e( '<strong>Limitation</strong>: This plugin fetches data for the top X posts based on the "Fetch Limit" setting. Posts ranking below this limit will not have their PV data updated.', 'simple-popular-posts-for-ga4' ); ?></li>
						<li><?php _e( '<strong>API Quota</strong>: Designed to stay within GA4 free quotas (daily execution). Please monitor your own API usage. (Est. usage: ~50 tokens per request)', 'simple-popular-posts-for-ga4' ); ?>
							<br>
							<?php
							$quota_query = urlencode( __( 'Google Analytics Data API Quota Limits', 'simple-popular-posts-for-ga4' ) );
							$quota_text  = __( 'Check API Quota Limits (Google Search)', 'simple-popular-posts-for-ga4' );
							printf( 
								'<a href="https://www.google.com/search?q=%s" target="_blank" rel="noopener noreferrer" style="text-decoration:none;">%s <span class="dashicons dashicons-external" style="font-size:14px; vertical-align:middle;"></span></a>', 
								$quota_query, 
								$quota_text 
							);
							?>
						</li>
					</ul>
				</div>


			<?php endif; ?>

			<?php if ( $active_tab === 'docs' ) : ?>
				<div class="spp-ga4-docs" style="background: #fff; padding: 20px; border: 1px solid #ccc; max-width: 900px; margin-top: 20px;">
					<?php $this->render_readme(); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function page_init() {
		// Handle Manual Fetch
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'spp_ga4_manual_fetch' ) {
			if ( ! isset( $_POST['spp_ga4_fetch_nonce'] ) || ! wp_verify_nonce( $_POST['spp_ga4_fetch_nonce'], 'spp_ga4_manual_fetch' ) ) {
				add_settings_error( 'spp_ga4_messages', 'spp_ga4_error', __( 'Security check failed.', 'simple-popular-posts-for-ga4' ), 'error' );
			} else if ( ! current_user_can( 'manage_options' ) ) {
				add_settings_error( 'spp_ga4_messages', 'spp_ga4_error', __( 'You do not have permission.', 'simple-popular-posts-for-ga4' ), 'error' );
			} else {
				try {
					$fetcher = new SPP_GA4_Fetcher(); 
					$fetcher->fetch_ga4_data( true );
					add_settings_error( 'spp_ga4_messages', 'spp_ga4_success', __( 'Data fetch triggered. Check System Status or debug logs for results.', 'simple-popular-posts-for-ga4' ), 'updated' );
				} catch ( Exception $e ) {
					add_settings_error( 'spp_ga4_messages', 'spp_ga4_error', sprintf( __( 'Fetch Failed: %s', 'simple-popular-posts-for-ga4' ), $e->getMessage() ), 'error' );
				}
			}
		}

		// Register Settings
		register_setting( 'spp_ga4_option_group', 'spp_ga4_service_account_json', array( $this, 'sanitize_json' ) );
		register_setting( 'spp_ga4_option_group', 'spp_ga4_property_id', array( $this, 'sanitize_property_id' ) );
		register_setting( 'spp_ga4_option_group', 'spp_ga4_cron_enabled', array( $this, 'sanitize_checkbox' ) );
		register_setting( 'spp_ga4_option_group', 'spp_ga4_cron_time', array( $this, 'sanitize_text' ) );
		register_setting( 'spp_ga4_option_group', 'spp_ga4_fetch_limit', array( $this, 'sanitize_limit' ) );
		register_setting( 'spp_ga4_option_group', 'spp_ga4_delete_on_uninstall', array( $this, 'sanitize_checkbox' ) );
		register_setting( 'spp_ga4_option_group', 'spp_ga4_disable_css', array( $this, 'sanitize_checkbox' ) );

		// Hook to reschedule when enabled option changes
		add_action( 'update_option_spp_ga4_cron_enabled', array( $this, 'reschedule_cron_event_toggle' ), 10, 2 );

		// Section: Basic Settings
		add_settings_section(
			'spp_ga4_setting_section',
			__( 'Basic Settings', 'simple-popular-posts-for-ga4' ),
			array( $this, 'section_info' ),
			'spp-ga4-settings'
		);

		add_settings_field(
			'spp_ga4_service_account_json',
			__( 'Service Account JSON Key', 'simple-popular-posts-for-ga4' ),
			array( $this, 'service_account_json_callback' ),
			'spp-ga4-settings',
			'spp_ga4_setting_section'
		);

		add_settings_field(
			'spp_ga4_property_id',
			__( 'GA4 Property ID', 'simple-popular-posts-for-ga4' ),
			array( $this, 'property_id_callback' ),
			'spp-ga4-settings',
			'spp_ga4_setting_section'
		);

		add_settings_field(
			'spp_ga4_fetch_limit',
			__( 'Fetch Limit (Rows)', 'simple-popular-posts-for-ga4' ),
			array( $this, 'fetch_limit_callback' ),
			'spp-ga4-settings',
			'spp_ga4_setting_section'
		);

		add_settings_field(
			'spp_ga4_disable_css',
			__( 'Disable Default CSS', 'simple-popular-posts-for-ga4' ),
			array( $this, 'disable_css_callback' ),
			'spp-ga4-settings',
			'spp_ga4_setting_section'
		);



		// Section: Cron Settings
		add_settings_section(
			'spp_ga4_cron_section',
			__( 'Auto-Fetch Schedule', 'simple-popular-posts-for-ga4' ),
			function() { print __( 'Settings for automatic data fetching.', 'simple-popular-posts-for-ga4' ); },
			'spp-ga4-settings'
		);

		add_settings_field(
			'spp_ga4_cron_enabled',
			__( 'Enable Auto-Fetch', 'simple-popular-posts-for-ga4' ),
			array( $this, 'cron_enabled_callback' ),
			'spp-ga4-settings',
			'spp_ga4_cron_section'
		);

		add_settings_field(
			'spp_ga4_cron_info',
			__( 'Cron Event Name', 'simple-popular-posts-for-ga4' ),
			function() { echo __( '<code>spp_ga4_daily_event</code> (Daily)', 'simple-popular-posts-for-ga4' ); },
			'spp-ga4-settings',
			'spp_ga4_cron_section'
		);

		add_settings_field(
			'spp_ga4_cron_time',
			__( 'Start Time', 'simple-popular-posts-for-ga4' ),
			array( $this, 'cron_time_callback' ),
			'spp-ga4-settings',
			'spp_ga4_cron_section'
		);

		// Section: Uninstall Settings (Moved to bottom)
		add_settings_section(
			'spp_ga4_uninstall_section',
			__( 'Uninstall Settings', 'simple-popular-posts-for-ga4' ),
			function() { print __( 'Behavior when deleting the plugin.', 'simple-popular-posts-for-ga4' ); },
			'spp-ga4-settings'
		);

		add_settings_field(
			'spp_ga4_delete_on_uninstall',
			__( 'Delete Data', 'simple-popular-posts-for-ga4' ),
			array( $this, 'delete_on_uninstall_callback' ),
			'spp-ga4-settings',
			'spp_ga4_uninstall_section'
		);
	}

	public function sanitize_json( $input ) {
		$input = trim( $input );
		
		// If input is the placeholder, keep the existing value (which is already encrypted in DB)
		if ( $input === '(Saved) ************' ) {
			return get_option( 'spp_ga4_service_account_json' );
		}

		$decoded = json_decode( $input );
		
		// If input is empty, just return it (empty)
		if ( empty( $input ) ) {
			return $input;
		}

		if ( $input && $decoded === null ) {
			add_settings_error( 'spp_ga4_service_account_json', 'invalid_json', __( 'Invalid JSON format.', 'simple-popular-posts-for-ga4' ), 'error' );
			return get_option( 'spp_ga4_service_account_json' ); 
		}

		// Security: Validate required fields for Service Account JSON
		if ( $input && ( ! isset( $decoded->type ) || ! isset( $decoded->project_id ) || ! isset( $decoded->private_key ) || ! isset( $decoded->client_email ) ) ) {
			add_settings_error( 'spp_ga4_service_account_json', 'invalid_json_structure', __( 'Invalid Service Account JSON. Missing required fields.', 'simple-popular-posts-for-ga4' ), 'error' );
			return get_option( 'spp_ga4_service_account_json' ); 
		}

		// Encrypt before saving
		return SPP_GA4_Encryption::encrypt( $input );
	}

	public function sanitize_text( $input ) {
		return sanitize_text_field( $input );
	}

	public function sanitize_property_id( $input ) {
		$val = sanitize_text_field( trim( $input ) );
		
		if ( empty( $val ) ) return $val;

		// Check for forbidden prefixes (G-, UA-)
		if ( preg_match( '/^(G-|UA-)/i', $val ) ) {
			add_settings_error( 'spp_ga4_property_id', 'invalid_id_format', __( 'Error: You entered a Measurement ID (G-xxx / UA-xxx). Please enter the numeric "Property ID".', 'simple-popular-posts-for-ga4' ), 'error' );
			return get_option( 'spp_ga4_property_id' ); // Revert
		}
		
		// Check if purely numeric (optional, but good for "Numeric Only")
		if ( ! is_numeric( $val ) ) {
			add_settings_error( 'spp_ga4_property_id', 'non_numeric_id', __( 'Error: Property ID must be numeric.', 'simple-popular-posts-for-ga4' ), 'error' );
			return get_option( 'spp_ga4_property_id' );
		}

		return $val;
	}
	
	public function sanitize_checkbox( $input ) {
		return ( $input === '1' ) ? '1' : '0';
	}

	public function section_info() {
		print __( 'Enter connection info for <a href="https://developers.google.com/analytics/devguides/reporting/data/v1" target="_blank" rel="noopener noreferrer">Google Analytics Data API</a>.', 'simple-popular-posts-for-ga4' );
	}
	
	// CALLBACKS
	
	public function delete_on_uninstall_callback() {
		$val = get_option( 'spp_ga4_delete_on_uninstall' );
		// Default to '1' (checked) if not set, or if explicitly '1'
		$is_checked = ( $val === false || $val === '1' );
		
		echo '<label>';
		echo '<input type="checkbox" name="spp_ga4_delete_on_uninstall" value="1" ' . checked( $is_checked, true, false ) . ' />';
		echo ' ' . __( 'Completely delete all settings and ranking data upon plugin deletion.', 'simple-popular-posts-for-ga4' );
		echo '</label>';
		echo '<p class="description">' . __( '<strong style="color: #d63638;">[IMPORTANT] Enabled by default.</strong><br>Uncheck only if you intend to reinstall and keep settings.', 'simple-popular-posts-for-ga4' ) . '</p>';
	}

	public function service_account_json_callback() {
		$val = get_option( 'spp_ga4_service_account_json' );
		$display_val = ! empty( $val ) ? '(Saved) ************' : '';
		
		printf(
			'<textarea id="spp_ga4_service_account_json" name="spp_ga4_service_account_json" rows="4" cols="80" style="font-family: monospace;">%s</textarea>',
			esc_textarea( $display_val )
		);
		
		// Helper Link for obtaining JSON Key
		$search_query = urlencode( __( 'How to get Google Analytics Data API Service Account JSON Key', 'simple-popular-posts-for-ga4' ) );
		$link_text    = __( 'Search Google for "How to get Service Account JSON Key"', 'simple-popular-posts-for-ga4' );
		$google_link  = sprintf( 
			'<a href="https://www.google.com/search?q=%s" target="_blank" rel="noopener noreferrer">%s</a>', 
			$search_query, 
			$link_text 
		);

		echo '<p class="description">';
		
		// 1. Paste instruction
		printf(
			/* translators: 1: 'entire' (bold text), 2: '{', 3: '}' */
			esc_html__( 'Paste the %1$s content of the JSON key file (starting with %2$s and ending with %3$s).', 'simple-popular-posts-for-ga4' ),
			'<strong>' . esc_html__( 'entire', 'simple-popular-posts-for-ga4' ) . '</strong>',
			'<code>{</code>',
			'<code>}</code>'
		);
		
		echo '<br><strong>' . esc_html__( 'Setup Guide:', 'simple-popular-posts-for-ga4' ) . '</strong><br>';
		
		// 2. Step 1
		printf(
			/* translators: 1: Link to Google Cloud Console with text "Google Cloud Console", 2: "API & Services", 3: "Enable 'Google Analytics Data API'" */
			esc_html__( '1. %1$s > %2$s > %3$s.', 'simple-popular-posts-for-ga4' ),
			'<a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com" target="_blank">' . esc_html__( 'Google Cloud Console', 'simple-popular-posts-for-ga4' ) . '</a>',
			esc_html__( 'API & Services', 'simple-popular-posts-for-ga4' ),
			__( 'Enable "Google Analytics Data API"', 'simple-popular-posts-for-ga4' )
		);
		echo '<br>';

		// 3. Step 2
		printf(
			/* translators: 1: Link to Service Accounts with text "IAM & Admin > Service Accounts", 2: "Create Account & Key (JSON)" */
			esc_html__( '2. %1$s > %2$s.', 'simple-popular-posts-for-ga4' ),
			'<a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank">' . esc_html__( 'IAM & Admin > Service Accounts', 'simple-popular-posts-for-ga4' ) . '</a>',
			esc_html__( 'Create Account & Key (JSON)', 'simple-popular-posts-for-ga4' )
		);
		echo '<br>';

		// 4. Step 3
		printf(
			/* translators: 1: Link to GA4 Admin with text "GA4 Admin", 2: "Property Access Management", 3: "Add the service account email as 'Viewer'" */
			esc_html__( '3. %1$s > %2$s > %3$s.', 'simple-popular-posts-for-ga4' ),
			'<a href="https://analytics.google.com/analytics/web/#/admin" target="_blank">' . esc_html__( 'GA4 Admin', 'simple-popular-posts-for-ga4' ) . '</a>',
			esc_html__( 'Property Access Management', 'simple-popular-posts-for-ga4' ),
			esc_html__( 'Add the service account email as "Viewer"', 'simple-popular-posts-for-ga4' )
		);

		echo '<br><br><strong>' . esc_html__( 'Note:', 'simple-popular-posts-for-ga4' ) . '</strong> ' . esc_html__( 'The JSON key is securely encrypted using your WordPress installation\'s unique salt keys. If you migrate your site to a new server or change your wp-config.php security salts, you must re-save your JSON key here to generate a new valid encryption.', 'simple-popular-posts-for-ga4' );

		echo '<br><br><strong>' . esc_html__( 'Need Help?', 'simple-popular-posts-for-ga4' ) . '</strong> ' . $google_link;
		echo '</p>';
	}

	public function property_id_callback() {
		printf(
			'<input type="text" id="spp_ga4_property_id" name="spp_ga4_property_id" value="%s" />',
			esc_attr( get_option( 'spp_ga4_property_id' ) )
		);
		echo '<p class="description">' . __( 'Numeric ID of your GA4 Property.<br><strong>Important:</strong> This is <strong>NOT</strong> the Measurement ID (G-xxxx, UA-xxxx). It must be the <strong>numeric Property ID</strong>.<br><strong>How to find:</strong> GA4 Admin > Property Settings > Property Details > Property ID.', 'simple-popular-posts-for-ga4' ) . '</p>';
	}

	public function fetch_limit_callback() {
		$limit = get_option( 'spp_ga4_fetch_limit', 1000 );
		printf(
			'<input type="number" id="spp_ga4_fetch_limit" name="spp_ga4_fetch_limit" value="%s" min="100" max="10000" />',
			esc_attr( $limit )
		);
		echo '<p class="description">' . __( 'Max number of pages to fetch (100 - 10000). Set this number based on your total posts (e.g., if you have 100 posts, set to 100). Lower limit reduces DB load. Default: 1000.', 'simple-popular-posts-for-ga4' ) . '<br><strong style="color: #d63638;">' . __( 'Warning:', 'simple-popular-posts-for-ga4' ) . '</strong> ' . __( 'Setting this to several thousand can cause high database load and may lead to connection timeouts on some servers.', 'simple-popular-posts-for-ga4' ) . '</p>';
	}

	public function sanitize_limit( $input ) {
		$val = intval( $input );
		
		if ( $val < 100 ) {
			add_settings_error( 'spp_ga4_fetch_limit', 'limit_too_low', sprintf( __( 'Fetch Limit must be between %d and %d.', 'simple-popular-posts-for-ga4' ), 100, 10000 ), 'error' );
			return 100;
		}
		if ( $val > 10000 ) {
			add_settings_error( 'spp_ga4_fetch_limit', 'limit_too_high', sprintf( __( 'Fetch Limit must be between %d and %d.', 'simple-popular-posts-for-ga4' ), 100, 10000 ), 'error' );
			return 10000;
		}
		
		return $val;
	}

	public function cron_time_callback() {
		$current_val = get_option( 'spp_ga4_cron_time', '00:00' );
		echo '<select name="spp_ga4_cron_time" id="spp_ga4_cron_time">';
		for ( $i = 0; $i < 24; $i++ ) {
			$time = sprintf( '%02d:00', $i );
			printf( '<option value="%s" %s>%s</option>', $time, selected( $current_val, $time, false ), $time );
		}
		echo '</select>';
		echo '<p class="description">' . __( 'Process starts on the first access after the specified time once a day.<br>Note: Schedule is re-registered upon saving.', 'simple-popular-posts-for-ga4' ) . '</p>';
	}

	public function disable_css_callback() {
		$checked = get_option( 'spp_ga4_disable_css' ) === '1' ? 'checked' : '';
		echo '<label>';
		echo '<input type="checkbox" name="spp_ga4_disable_css" value="1" ' . $checked . ' />';
		echo ' ' . __( 'Do not load plugin CSS.', 'simple-popular-posts-for-ga4' );
		echo '</label>';
		echo '<p class="description">' . __( 'Check this if you want to use your theme\'s styles or custom CSS. When enabled, the plugin\'s <code>style.css</code> file will <strong>NOT</strong> be loaded (improving performance by reducing requests), and the "Design Presets" feature will effectively be disabled (class names will still be output).', 'simple-popular-posts-for-ga4' ) . '<br>' . __( 'You can then write your own CSS targeting the <code>.spp-ga4-container</code> class.', 'simple-popular-posts-for-ga4' ) . '</p>';
	}
	
	public function cron_enabled_callback() {
		$checked = get_option( 'spp_ga4_cron_enabled', '0' ) === '1' ? 'checked' : '';
		echo '<label>';
		echo '<input type="checkbox" name="spp_ga4_cron_enabled" value="1" ' . $checked . ' />';
		echo ' ' . __( 'Enable daily background data fetching.', 'simple-popular-posts-for-ga4' );
		echo '</label>';
	}

	// Reschedule cron event when time option is updated
	public function reschedule_cron_event( $old_value, $new_value ) {
		// Ensure scheduled event is cleared if disabled.
		$is_enabled = get_option( 'spp_ga4_cron_enabled' ) === '1';
		
		if ( ! $is_enabled ) {
			wp_clear_scheduled_hook( 'spp_ga4_daily_event' );
			return;
		}

		error_log( "SPP GA4: Cron reschedule triggered. Old: $old_value, New: $new_value" );
		
		if ( $old_value === $new_value ) return;
		
		$this->schedule_event( $new_value );
	}

	// Reschedule when enabled toggle changes
	public function reschedule_cron_event_toggle( $old_value, $new_value ) {
		$time_str = get_option( 'spp_ga4_cron_time', '00:00' );
		
		if ( $new_value === '1' ) {
			// Enabled
			$this->schedule_event( $time_str );
			
			// Validation: Check if credentials are empty
			$raw_key = get_option( 'spp_ga4_service_account_json' );
			$property_id = get_option( 'spp_ga4_property_id' );
			if ( empty( $raw_key ) || empty( $property_id ) ) {
				add_settings_error( 'spp_ga4_cron_enabled', 'spp_ga4_warning', __( 'Warning: Auto-fetch enabled but credentials are missing.', 'simple-popular-posts-for-ga4' ), 'warning' );
			}

		} else {
			// Disabled
			wp_clear_scheduled_hook( 'spp_ga4_daily_event' );
		}
	}

	private function schedule_event( $time_str ) {
		// 1. Clear existing
		wp_clear_scheduled_hook( 'spp_ga4_daily_event' );

		// 2. Calculate new schedule time
		$parts = explode( ':', $time_str );
		$hour = isset( $parts[0] ) ? intval( $parts[0] ) : 0;
		$minute = isset( $parts[1] ) ? intval( $parts[1] ) : 0;
		
		$dt = current_datetime()->setTime( $hour, $minute, 0 );
		
		// If the new time for today has already passed, schedule for tomorrow
		if ( $dt->getTimestamp() < time() ) {
			$dt = $dt->modify( '+1 day' );
		}
		
		$new_ts = $dt->getTimestamp();
		error_log( "SPP GA4: Scheduling new event at " . $dt->format( 'Y-m-d H:i:s P' ) . " (TS: $new_ts)" );
		
		// 3. Schedule
		wp_schedule_event( $new_ts, 'daily', 'spp_ga4_daily_event' );
	}

	public function render_system_status() {
		global $wpdb;
		
		// 1. Last Execution Logic
		$stats = get_option( 'spp_ga4_last_run_stats', false );
		
		echo '<table class="widefat striped" style="max-width: 600px;">';
		echo '<thead><tr><th colspan="2">' . __( 'System Status', 'simple-popular-posts-for-ga4' ) . ' / DB</th></tr></thead>';
		echo '<tbody>';
		
		// Last Run
		echo '<tr><td style="width: 150px;"><strong>' . __( 'Last Run Time', 'simple-popular-posts-for-ga4' ) . '</strong></td><td>';
		if ( $stats ) {
			echo esc_html( $stats['time'] );
			if ( isset($stats['status']) && $stats['status'] === 'success' ) {
				echo ' <span style="color: green; font-weight: bold;">' . __( '[Success]', 'simple-popular-posts-for-ga4' ) . '</span>';
			} else {
				echo ' <span style="color: red; font-weight: bold;">' . __( '[Error]', 'simple-popular-posts-for-ga4' ) . '</span>';
			}
		} else {
			echo __( 'Not run yet', 'simple-popular-posts-for-ga4' );
		}
		echo '</td></tr>';

		// Count / Message
		echo '<tr><td><strong>' . __( 'Result / Log', 'simple-popular-posts-for-ga4' ) . '</strong></td><td>';
		if ( $stats ) {
			printf( __( 'Updated: %s posts', 'simple-popular-posts-for-ga4' ) . '<br>', intval( $stats['count'] ) );
			if ( ! empty( $stats['message'] ) ) echo '<span style="color: #666;">' . esc_html( $stats['message'] ) . '</span>';
		} else {
			echo '-';
		}
		echo '</td></tr>';

		// Next Schedule
		$next_schedule = wp_next_scheduled( 'spp_ga4_daily_event' );
		echo '<tr><td><strong>' . __( 'Next Schedule', 'simple-popular-posts-for-ga4' ) . '</strong></td><td>';
		if ( $next_schedule ) {
			// Convert to local time
			echo esc_html( wp_date( 'Y-m-d H:i:s', $next_schedule ) );
		} else {
			echo '<span style="color: red;">' . __( 'No schedule (Please reactivate plugin)', 'simple-popular-posts-for-ga4' ) . '</span>';
		}
		echo '</td></tr>';

		// Custom Key Status
		echo '<tr><td><strong>' . __( 'Custom Security Key', 'simple-popular-posts-for-ga4' ) . '</strong></td><td>';
		if ( defined( 'SPP_GA4_CUSTOM_KEY' ) && SPP_GA4_CUSTOM_KEY !== '' ) {
			echo '<span style="color: green; font-weight: bold;">' . __( 'Active (Enhanced Security)', 'simple-popular-posts-for-ga4' ) . '</span>';
			echo ' <small>(' . __( 'Defined in wp-config.php', 'simple-popular-posts-for-ga4' ) . ')</small>';
		} else {
			echo '<span style="color: #666;">' . __( 'Not Set (Standard Security)', 'simple-popular-posts-for-ga4' ) . '</span>';
		}
		echo '</td></tr>';

		// DB Usage
		// Count records in postmeta for our keys
		$key_pattern = $wpdb->esc_like( '_spp_ga4_pv_' ) . '%';
		$row_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key LIKE %s", $key_pattern ) );
		
		echo '<tr><td><strong>' . __( 'DB Usage (wp_postmeta)', 'simple-popular-posts-for-ga4' ) . '</strong></td><td>';
		printf( __( 'Table: <code>%s</code>', 'simple-popular-posts-for-ga4' ) . '<br>', $wpdb->postmeta );
		printf( __( 'Related Records: <strong>%s</strong> rows (approx)', 'simple-popular-posts-for-ga4' ) . '<br>', number_format( $row_count ) );
		echo __( '<small>* Records used by this plugin (<code>_spp_ga4_pv_7d</code>, <code>_spp_ga4_pv_30d</code>)</small>', 'simple-popular-posts-for-ga4' );
		echo '</td></tr>';
		
		echo '</tbody></table>';
	}

	public function render_readme() {
		$readme_path = SPP_GA4_PATH . 'readme.txt';
		if ( ! file_exists( $readme_path ) ) {
			echo '<p>' . __( 'Documentation file not found.', 'simple-popular-posts-for-ga4' ) . '</p>';
			return;
		}

		$content = file_get_contents( $readme_path );
		
		// WordPress Readme Parser for Admin Display
		// 1. Remove Header (Before "== Description ==")
		$content = preg_replace( '/^.*?(?=== Description ==)/s', '', $content );
		
		// 2. Sections (== Heading == -> h3)
		$content = preg_replace( '/^==\s+(.+?)\s+==/m', '<h3>$1</h3>', $content );
		
		// 3. Sub-sections (= Heading = -> h4)
		$content = preg_replace( '/^=\s+(.+?)\s+=/m', '<h4>$1</h4>', $content );

		// 4. Code Blocks (Simple indented or backticks - supporting backticks here as used in previous markdown)
		// WP Readme mostly uses backticks for code inline.
		$content = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $content );

		// 5. Lists (* Item)
		$content = preg_replace( '/^\*\s+(.+)$/m', '<li>$1</li>', $content );


		// 6. Links (Markdown style is supported in WP readme sometimes, or bare URLs)
		// Support Markdown style links as they are parsed by WP Directory.
		$content = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $content );
		
		// 7. Auto paragraphs
		$content = wpautop( $content );

		echo '<div class="markdown-body">';
		echo $content;
		echo '</div>';
	}



}

if ( is_admin() ) {
	$spp_ga4_admin = new SPP_GA4_Admin();
}
