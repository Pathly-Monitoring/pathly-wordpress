<?php
/**
 * Storage of the Pathly API key.
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

/**
 * The key lives in a non-autoloaded option, encrypted with a secret derived
 * from the site salts (wp-config.php): a database dump alone does not reveal
 * it. It is never printed back once saved.
 */
class Pathly_Key_Store {

	const OPTION = 'pathly_api_key';

	/**
	 * True when the value looks like a Pathly key.
	 *
	 * @param string $key Candidate key.
	 * @return bool
	 */
	public static function is_valid_format( $key ) {
		return 1 === preg_match( '/^sp_[A-Za-z0-9_\-]{16,200}\z/', (string) $key );
	}

	/**
	 * Encryption secret, 32 bytes.
	 *
	 * @return string
	 */
	private static function secret() {
		return hash_hmac( 'sha256', 'pathly-api-key', wp_salt( 'auth' ), true );
	}

	/**
	 * Saves the key.
	 *
	 * @param string $key API key, already checked.
	 * @return void
	 */
	public static function save( $key ) {
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $key, $nonce, self::secret() );
		update_option( self::OPTION, bin2hex( $nonce . $cipher ), false );
	}

	/**
	 * The key, or an empty string when none is saved or the salts changed.
	 *
	 * @return string
	 */
	public static function get() {
		$stored = get_option( self::OPTION, '' );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return '';
		}
		$raw = ctype_xdigit( $stored ) && 0 === strlen( $stored ) % 2 ? hex2bin( $stored ) : '';
		if ( strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$plain = sodium_crypto_secretbox_open(
			substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
			substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
			self::secret()
		);
		return false === $plain ? '' : $plain;
	}

	/**
	 * True when a readable key is saved.
	 *
	 * @return bool
	 */
	public static function has() {
		return '' !== self::get();
	}

	/**
	 * Removes the key.
	 *
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}

	/**
	 * Masked form for display: prefix and last four characters.
	 *
	 * @param string $key API key.
	 * @return string
	 */
	public static function mask( $key ) {
		return substr( $key, 0, 3 ) . str_repeat( '•', 8 ) . substr( $key, -4 );
	}
}
