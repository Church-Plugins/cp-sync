<?php
/**
 * Force CP_Sync\ChMS\Encryption onto pure-PHP sodium_compat.
 *
 * Encryption calls unqualified sodium_* functions. PHP resolves those inside
 * the CP_Sync\ChMS namespace before the global ext-sodium functions, so defining
 * them here redirects encrypt/decrypt without a production test seam.
 *
 * Load this only from EncryptionCompatTest, which runs in its own process.
 * Defining these functions in the main suite would shadow ext-sodium for every
 * later Encryption call in that process.
 *
 * The open() wrapper matches WordPress's php72compat.php: ParagonIE throws
 * SodiumException on a bad MAC, and the polyfill turns that into false, which
 * is what ext-sodium returns. memzero() is left throwing, which is the bug.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Fixtures {

	/**
	 * Counters and switches for the compat shims. Not used in production.
	 */
	class SodiumCompatTestHooks {

		/**
		 * How many times the namespaced sodium_memzero() shim ran.
		 *
		 * @var int
		 */
		public static $memzero_calls = 0;

		/**
		 * When true, the next secretbox() shim throws instead of encrypting.
		 *
		 * @var bool
		 */
		public static $fail_next_secretbox = false;
	}
}

namespace {

	if ( ! function_exists( 'wp_salt' ) ) {
		/**
		 * Deterministic salt so key derivation matches across native and compat.
		 *
		 * @param string $scheme Unused. Present so the signature matches WordPress.
		 * @return string
		 */
		function wp_salt( $scheme = 'auth' ) {
			return 'unit-test-salt-value-1234567890';
		}
	}
}

namespace CP_Sync\ChMS {

	/**
	 * Pure-PHP secretbox. SodiumException (bad key, or the forced failure) propagates.
	 *
	 * @param string $message Plaintext.
	 * @param string $nonce   24-byte nonce.
	 * @param string $key     32-byte key.
	 * @return string
	 * @throws \SodiumException When secretbox fails. Encryption must not catch this.
	 */
	function sodium_crypto_secretbox( $message, $nonce, $key ) {
		\ParagonIE_Sodium_Compat::$disableFallbackForUnitTests = true;

		if ( \CP_Sync\Tests\Fixtures\SodiumCompatTestHooks::$fail_next_secretbox ) {
			\CP_Sync\Tests\Fixtures\SodiumCompatTestHooks::$fail_next_secretbox = false;
			throw new \SodiumException( 'forced secretbox failure' );
		}

		return \ParagonIE_Sodium_Compat::crypto_secretbox( $message, $nonce, $key );
	}

	/**
	 * Pure-PHP secretbox_open, with WordPress's polyfill failure mode (false).
	 *
	 * @param string $ciphertext Ciphertext plus MAC.
	 * @param string $nonce      24-byte nonce.
	 * @param string $key        32-byte key.
	 * @return string|false
	 */
	function sodium_crypto_secretbox_open( $ciphertext, $nonce, $key ) {
		\ParagonIE_Sodium_Compat::$disableFallbackForUnitTests = true;

		try {
			return \ParagonIE_Sodium_Compat::crypto_secretbox_open( $ciphertext, $nonce, $key );
		} catch ( \Error $ex ) {
			unset( $ex );
			return false;
		} catch ( \Exception $ex ) {
			unset( $ex );
			return false;
		}
	}

	/**
	 * sodium_compat memzero. Throws SodiumException when the native extension is not used.
	 *
	 * @param string $string Buffer to wipe.
	 * @return void
	 * @throws \SodiumException Always, in compat-only mode.
	 * @throws \RuntimeException When the polyfill did not throw, so the test is not really compat-only.
	 */
	function sodium_memzero( &$string ) {
		\ParagonIE_Sodium_Compat::$disableFallbackForUnitTests = true;
		\CP_Sync\Tests\Fixtures\SodiumCompatTestHooks::$memzero_calls++;

		\ParagonIE_Sodium_Compat::memzero( $string );

		throw new \RuntimeException( 'sodium_compat memzero did not throw; compat-only mode is not active.' );
	}
}
