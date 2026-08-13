<?php
/**
 * Reversible encryption helper for credentials stored at rest.
 *
 * @package CP_Sync
 */

namespace CP_Sync\ChMS;

/**
 * Encryption helper.
 *
 * Encrypts short secrets (e.g. ChMS API credentials) before they are written to
 * the options table, so a database dump alone does not expose them as plaintext.
 * The key is derived from the site's WordPress salts, so a stolen database
 * without wp-config.php cannot be decrypted.
 *
 * Every ciphertext carries a short version/algorithm marker. That marker lets the
 * matching decrypt routine be selected and — critically — lets legacy PLAINTEXT
 * values (which have no marker) be detected and returned untouched, so existing
 * installs keep working and are transparently re-encrypted on their next save.
 *
 * Kept as a tiny static helper rather than a trait: CCB is the only consumer for
 * now, but credential storage is a cross-ChMS concern, so a standalone class is
 * both reusable and unit-testable without booting WordPress.
 */
class Encryption {

	/**
	 * Marker for libsodium (secretbox) ciphertext.
	 *
	 * @var string
	 */
	const SODIUM_PREFIX = 'cpsx1:';

	/**
	 * Marker for OpenSSL (AES-256-GCM) ciphertext.
	 *
	 * @var string
	 */
	const OPENSSL_PREFIX = 'cpsx2:';

	/**
	 * Encrypt a value for storage.
	 *
	 * Empty strings and non-strings are returned unchanged so blank credentials
	 * round-trip cleanly. Already-encrypted values are returned as-is to avoid
	 * double-encryption.
	 *
	 * @param string $plaintext The value to encrypt.
	 * @return string The marked ciphertext, or the original value when there is
	 *                nothing to encrypt / no crypto extension is available.
	 */
	public static function encrypt( $plaintext ) {
		if ( ! is_string( $plaintext ) || '' === $plaintext ) {
			return $plaintext;
		}

		if ( self::is_encrypted( $plaintext ) ) {
			return $plaintext;
		}

		$key = self::get_key();

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );

			if ( function_exists( 'sodium_memzero' ) ) {
				sodium_memzero( $key );
			}

			return self::SODIUM_PREFIX . base64_encode( $nonce . $cipher );
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );

			// Fail closed: if encryption fails, do not persist the plaintext.
			if ( false === $cipher ) {
				return '';
			}

			return self::OPENSSL_PREFIX . base64_encode( $iv . $tag . $cipher );
		}

		// No crypto extension available. Both are bundled with modern PHP, so
		// this is effectively unreachable; return unchanged rather than lose data.
		return $plaintext;
	}

	/**
	 * Decrypt a stored value.
	 *
	 * Values without a recognized marker are treated as legacy plaintext and
	 * returned unchanged, preserving backward compatibility for installs that
	 * predate encryption.
	 *
	 * @param string $value The stored value (ciphertext or legacy plaintext).
	 * @return string The decrypted value, the original plaintext, or '' when a
	 *                marked ciphertext cannot be decrypted.
	 */
	public static function decrypt( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		if ( 0 === strpos( $value, self::SODIUM_PREFIX ) ) {
			if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
				return '';
			}

			$raw = base64_decode( substr( $value, strlen( self::SODIUM_PREFIX ) ), true );

			if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return '';
			}

			$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$key    = self::get_key();
			$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );

			if ( function_exists( 'sodium_memzero' ) ) {
				sodium_memzero( $key );
			}

			return false === $plain ? '' : $plain;
		}

		if ( 0 === strpos( $value, self::OPENSSL_PREFIX ) ) {
			if ( ! function_exists( 'openssl_decrypt' ) ) {
				return '';
			}

			$raw = base64_decode( substr( $value, strlen( self::OPENSSL_PREFIX ) ), true );

			// Minimum payload is 12-byte IV + 16-byte tag + at least 1 byte cipher.
			if ( false === $raw || strlen( $raw ) <= 28 ) {
				return '';
			}

			$iv     = substr( $raw, 0, 12 );
			$tag    = substr( $raw, 12, 16 );
			$cipher = substr( $raw, 28 );
			$key    = self::get_key();
			$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

			return false === $plain ? '' : $plain;
		}

		// No marker: legacy plaintext. Return unchanged for backward compatibility.
		return $value;
	}

	/**
	 * Whether a value carries one of our ciphertext markers.
	 *
	 * @param string $value The value to inspect.
	 * @return bool
	 */
	public static function is_encrypted( $value ) {
		return is_string( $value )
			&& ( 0 === strpos( $value, self::SODIUM_PREFIX ) || 0 === strpos( $value, self::OPENSSL_PREFIX ) );
	}

	/**
	 * Derive a 32-byte key from the site's WordPress salts.
	 *
	 * Never hardcodes a key. Uses wp_salt('secure_auth') when WordPress is loaded,
	 * falling back to the AUTH_KEY constant (defined in wp-config.php), and finally
	 * to a static string only in the unlikely event neither is available.
	 *
	 * @return string Raw 32-byte key suitable for secretbox and AES-256.
	 */
	private static function get_key() {
		if ( function_exists( 'wp_salt' ) ) {
			$salt = wp_salt( 'secure_auth' );
		} elseif ( defined( 'AUTH_KEY' ) && '' !== AUTH_KEY ) {
			$salt = AUTH_KEY;
		} else {
			$salt = 'cp-sync-insecure-default-key';
		}

		return hash( 'sha256', 'cp-sync/credential/v1|' . $salt, true );
	}
}
