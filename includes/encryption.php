<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPP_GA4_Encryption {
	
	private static $method = 'aes-256-cbc';
	
	// Unique salt for this plugin to prevent decryption by generic tools
	private const SECRET_SUFFIX = 'X9#mP2$vLq@5z!';

	/**
	 * Encrypt data using WordPress Salt + Plugin Secret.
	 *
	 * @param string $data Plain text data.
	 * @return string Base64 encoded string containing IV and Encrypted data.
	 */
	public static function encrypt( $data ) {
		if ( empty( $data ) ) {
			return $data;
		}

		$user_key = defined( 'SPP_GA4_CUSTOM_KEY' ) ? SPP_GA4_CUSTOM_KEY : '';
		$key = wp_salt( 'auth' ) . self::SECRET_SUFFIX . $user_key;
		// Ensure key length is 32 bytes for AES-256
		$key = substr( hash( 'sha256', $key ), 0, 32 );
		
		$iv_length = openssl_cipher_iv_length( self::$method );
		$iv = openssl_random_pseudo_bytes( $iv_length );
		
		$encrypted = openssl_encrypt( $data, self::$method, $key, 0, $iv );
		
		// Return: IV + Encrypted Data (Base64 encoded)
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt data found in database.
	 *
	 * @param string $data Base64 encoded Encrypted data.
	 * @return string Decrypted plain text.
	 */
	public static function decrypt( $data ) {
		if ( empty( $data ) ) {
			return $data;
		}

		$raw = base64_decode( $data );
		$iv_length = openssl_cipher_iv_length( self::$method );
		
		if ( strlen( $raw ) < $iv_length ) {
			return $data; // Not encrypted or invalid
		}

		$iv = substr( $raw, 0, $iv_length );
		$encrypted = substr( $raw, $iv_length );
		
		$user_key = defined( 'SPP_GA4_CUSTOM_KEY' ) ? SPP_GA4_CUSTOM_KEY : '';
		$key = wp_salt( 'auth' ) . self::SECRET_SUFFIX . $user_key;
		$key = substr( hash( 'sha256', $key ), 0, 32 );

		return openssl_decrypt( $encrypted, self::$method, $key, 0, $iv );
	}
}
