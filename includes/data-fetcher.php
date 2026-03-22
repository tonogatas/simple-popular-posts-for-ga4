<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPP_GA4_Fetcher {

	private $lock_transient = 'spp_ga4_fetch_lock';
	private $last_fetch_option = 'spp_ga4_last_fetch_time';

	public function __construct() {
		add_action( 'spp_ga4_daily_event', array( $this, 'fetch_ga4_data' ) );
	}

	/**
	 * Main fetching logic.
	 *
	 * @param bool $force If true, bypass 12-hour check.
	 */
	public function fetch_ga4_data( $force = false ) {
		// 1. Rate Limiting Check (12 Hours)
		$last_fetch = get_option( $this->last_fetch_option, 0 );
		if ( ! $force && ( time() - $last_fetch < 12 * HOUR_IN_SECONDS ) ) {
			return;
		}

		// 2. Transients Lock (Process Concurrency)
		if ( get_transient( $this->lock_transient ) ) {
			error_log( 'SPP GA4: Fetch aborted due to active lock.' );
			return;
		}
		set_transient( $this->lock_transient, true, 10 * MINUTE_IN_SECONDS );
		
		// Increase time limit for this process
		set_time_limit( 300 );

		$update_count = 0;

		try {
			// 3. Preparation
			$raw_key = get_option( 'spp_ga4_service_account_json' );
			$property_id = get_option( 'spp_ga4_property_id' );

			if ( empty( $raw_key ) ) {
				throw new Exception( __( 'Missing GA4 Credentials: Service Account JSON is empty.', 'simple-popular-posts-for-ga4' ) );
			}
			if ( empty( $property_id ) ) {
				throw new Exception( __( 'Missing GA4 Credentials: Property ID is empty.', 'simple-popular-posts-for-ga4' ) );
			}

			// Try Plain Text first (Migration support)
			$credentials = json_decode( $raw_key, true );
			
			// If not valid JSON, try attempting decryption
			if ( ! $credentials ) {
				$decrypted = SPP_GA4_Encryption::decrypt( $raw_key );
				$credentials = json_decode( $decrypted, true );
			}

			if ( ! $credentials ) {
				throw new Exception( __( 'Invalid JSON Key or Decryption Failed. If you migrated the site or changed the WordPress salts, please re-save the JSON key in the settings.', 'simple-popular-posts-for-ga4' ) );
			}

			// 4. Authenticate (Get Access Token)
			$token = $this->get_access_token( $credentials );
			if ( ! $token ) {
				throw new Exception( __( 'Failed to acquire Access Token.', 'simple-popular-posts-for-ga4' ) );
			}

			// 5. Build Request
			$limit = get_option( 'spp_ga4_fetch_limit', 1000 );
			
			// Requesting 7-day and 30-day ranges in one go.
			$request_body = array(
				'dateRanges' => array(
					array( 'startDate' => '7daysAgo', 'endDate' => '2daysAgo' ),  // Range 0
					array( 'startDate' => '30daysAgo', 'endDate' => '2daysAgo' ), // Range 1
				),
				'dimensions' => array(
					array( 'name' => 'pagePath' ),
				),
				'metrics' => array(
					array( 'name' => 'screenPageViews' ),
				),
				'limit' => $limit,
			);

			$api_url = "https://analyticsdata.googleapis.com/v1beta/properties/{$property_id}:runReport";

			$response = wp_remote_post( $api_url, array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => json_encode( $request_body ),
				'timeout' => 45,
			) );

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( $code !== 200 ) {
				$body = wp_remote_retrieve_body( $response );
				throw new Exception( "API Error ($code): $body" );
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			
			// 6. Process Data
			if ( isset( $data['rows'] ) && is_array( $data['rows'] ) ) {
				$aggregated = array(); // pagePath => [ '7d' => 0, '30d' => 0 ]

				foreach ( $data['rows'] as $row ) {
					$path = $row['dimensionValues'][0]['value'];
					$views = isset( $row['metricValues'][0]['value'] ) ? intval( $row['metricValues'][0]['value'] ) : 0;
					
					// Find date range index (Assuming implicit 'dateRange' dimension if multiple ranges)
					$range_val = '';
					if ( count( $row['dimensionValues'] ) > 1 ) {
						$range_val = $row['dimensionValues'][1]['value'];
					}
					
					// Map "date_range_0" -> 7d, "date_range_1" -> 30d
					if ( $range_val === 'date_range_0' ) {
						if (!isset($aggregated[$path])) $aggregated[$path] = ['7d'=>0, '30d'=>0];
						$aggregated[$path]['7d'] += $views;
					} elseif ( $range_val === 'date_range_1' ) {
						if (!isset($aggregated[$path])) $aggregated[$path] = ['7d'=>0, '30d'=>0];
						$aggregated[$path]['30d'] += $views;
					}
				}

				// 7. Update Post Meta
				
				// CLEANUP: Remove old ranking data to prevent stale entries (e.g. if limit is lowered)
				global $wpdb;
				$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key IN ('_spp_ga4_pv_7d', '_spp_ga4_pv_30d')" );

				foreach ( $aggregated as $path => $counts ) {
					$post_id = url_to_postid( $path );
					if ( $post_id > 0 ) {
						update_post_meta( $post_id, '_spp_ga4_pv_7d', $counts['7d'] );
						update_post_meta( $post_id, '_spp_ga4_pv_30d', $counts['30d'] );
						$update_count++;
					}
				}
				
				// 8. Update Last Fetch Time & Clear Lock
				update_option( $this->last_fetch_option, time() );
				delete_transient( $this->lock_transient );
				
				// 9. Cache Warming
				$this->warm_cache_for_widgets();

				// Save Success Stats
				update_option( 'spp_ga4_last_run_stats', array(
					'time' => current_time( 'mysql' ), // Local WP time
					'count' => $update_count,
					'status' => 'success',
					'message' => 'Completed successfully.',
				));

				error_log( "SPP GA4: Data updated successfully. ($update_count posts)" );

			} else {
				// Success (No Data) - Inform user about potential delay
			throw new Exception( __( 'Connection successful, but no data returned. GA4 data may take 24-48 hours to propagate after initial setup.', 'simple-popular-posts-for-ga4' ) );
			}

		} catch ( Exception $e ) {
			delete_transient( $this->lock_transient );
			error_log( 'SPP GA4 Error: ' . $e->getMessage() );
			
			// Save Error Stats
			update_option( 'spp_ga4_last_run_stats', array(
				'time' => current_time( 'mysql' ),
				'count' => $update_count,
				'status' => 'error',
				'message' => $e->getMessage(),
			));
		}
	}

	private function warm_cache_for_widgets() {
		// Get all instances of our widget
		$widget_instances = get_option( 'widget_spp_ga4_widget' ); // Option name based on widget ID base
		if ( ! empty( $widget_instances ) && is_array( $widget_instances ) ) {
			foreach ( $widget_instances as $number => $instance ) {
				if ( ! is_numeric( $number ) || empty( $instance ) ) continue;
				
				// Regenerate cache for this widget instance.
				if ( class_exists( 'SPP_GA4_Widget' ) ) {
					SPP_GA4_Widget::generate_cache( $instance, 'spp_ga4_cache_' . $number );
				}
			}
		}
	}

	/**
	 * Create JWT for Google Auth.
	 */
	private function get_access_token( $credentials ) {
		$header = json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) );
		$now = time();
		$payload = json_encode( array(
			'iss' => $credentials['client_email'],
			'sub' => $credentials['client_email'],
			'aud' => 'https://oauth2.googleapis.com/token',
			'iat' => $now,
			'exp' => $now + 3600,
			'scope' => 'https://www.googleapis.com/auth/analytics.readonly'
		) );

		$base64_header = $this->base64url_encode( $header );
		$base64_payload = $this->base64url_encode( $payload );

		$signature = '';
		$success = openssl_sign(
			$base64_header . "." . $base64_payload,
			$signature,
			$credentials['private_key'],
			'SHA256'
		);

		if ( ! $success ) {
			return false;
		}

		$jwt = $base64_header . "." . $base64_payload . "." . $this->base64url_encode( $signature );

		// Exchange JWT for Access Token
		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'body' => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion' => $jwt
			)
		) );

		if ( is_wp_error( $response ) ) return false;
		
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $body['access_token'] ) ? $body['access_token'] : false;
	}

	private function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}

new SPP_GA4_Fetcher();
