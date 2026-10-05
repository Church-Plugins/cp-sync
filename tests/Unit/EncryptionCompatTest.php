<?php
/**
 * Compat-only coverage for CP_Sync\ChMS\Encryption.
 *
 * tests/Unit/EncryptionTest.php exercises the native sodium extension. This
 * class covers hosts where that extension is missing and WordPress has loaded
 * sodium_compat. sodium_compat implements secretbox, but its sodium_memzero()
 * always throws SodiumException.
 *
 * The test PHP binary often has ext-sodium compiled in, and a userland
 * polyfill cannot replace an existing global function. Encryption calls the
 * unqualified sodium_* names, which PHP looks up in CP_Sync\ChMS first. The
 * fixture defines those namespaced functions as wrappers around
 * ParagonIE_Sodium_Compat with $disableFallbackForUnitTests set, so
 * encrypt/decrypt hit the pure-PHP implementation and the throwing memzero.
 * Each test runs in its own process so the shims do not leak into
 * EncryptionTest.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\ChMS\Encryption;
use CP_Sync\Tests\Fixtures\SodiumCompatTestHooks;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\Encryption
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class EncryptionCompatTest extends TestCase {

	/**
	 * Install the compat shims and prove memzero throws before any assertion.
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'CP_Sync\\ChMS\\sodium_memzero' ) ) {
			require dirname( __DIR__ ) . '/Fixtures/ChmsSodiumCompatShims.php';
		}

		\ParagonIE_Sodium_Compat::$disableFallbackForUnitTests = true;
		SodiumCompatTestHooks::$fail_next_secretbox            = false;
		SodiumCompatTestHooks::$memzero_calls                  = 0;

		$buffer = 'compat-memzero-probe';
		try {
			\CP_Sync\ChMS\sodium_memzero( $buffer );
			$this->fail( 'Compat sodium_memzero() must throw SodiumException.' );
		} catch ( \SodiumException $e ) {
			$this->assertStringContainsString( 'not implemented', $e->getMessage() );
		}

		SodiumCompatTestHooks::$memzero_calls = 0;
	}

	public function test_encrypt_returns_sodium_prefix_and_decrypts(): void {
		$secret    = 'ccb-api-p@ss';
		$encrypted = Encryption::encrypt( $secret );

		$this->assertIsString( $encrypted );
		$this->assertStringStartsWith( Encryption::SODIUM_PREFIX, $encrypted );
		$this->assertNotSame( $secret, $encrypted );
		$this->assertTrue( Encryption::is_encrypted( $encrypted ) );
		$this->assertGreaterThan( 0, SodiumCompatTestHooks::$memzero_calls );
		$this->assertSame( $secret, Encryption::decrypt( $encrypted ) );
	}

	public function test_unmarked_plaintext_passes_through_decrypt(): void {
		$legacy = 'legacy-plaintext-api-user';

		$this->assertFalse( Encryption::is_encrypted( $legacy ) );
		$this->assertSame( $legacy, Encryption::decrypt( $legacy ) );
		$this->assertSame( 0, SodiumCompatTestHooks::$memzero_calls );
	}

	public function test_native_ciphertext_decrypts_under_compat(): void {
		$secret = 'migrated-from-sodium-host';
		$key    = $this->encryption_key();
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = \sodium_crypto_secretbox( $secret, $nonce, $key );
		$stored = Encryption::SODIUM_PREFIX . base64_encode( $nonce . $cipher );

		$this->assertSame( $secret, Encryption::decrypt( $stored ) );
		$this->assertGreaterThan( 0, SodiumCompatTestHooks::$memzero_calls );
	}

	public function test_compat_ciphertext_decrypts_under_native(): void {
		$secret    = 'saved-on-compat-host';
		$encrypted = Encryption::encrypt( $secret );
		$raw       = base64_decode( substr( $encrypted, strlen( Encryption::SODIUM_PREFIX ) ), true );

		$this->assertNotFalse( $raw );
		$this->assertGreaterThan( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, strlen( $raw ) );

		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = \sodium_crypto_secretbox_open( $cipher, $nonce, $this->encryption_key() );

		$this->assertSame( $secret, $plain );
	}

	public function test_corrupted_ciphertext_does_not_return_plaintext(): void {
		$secret    = 'secret';
		$encrypted = Encryption::encrypt( $secret );
		$tampered  = substr( $encrypted, 0, -2 ) . ( 'A' === substr( $encrypted, -2, 1 ) ? 'B' : 'A' ) . substr( $encrypted, -1 );

		$this->assertNotSame( $secret, $tampered );
		$this->assertSame( '', Encryption::decrypt( $tampered ) );
	}

	public function test_secretbox_failure_is_not_swallowed(): void {
		SodiumCompatTestHooks::$fail_next_secretbox = true;

		$this->expectException( \SodiumException::class );
		$this->expectExceptionMessage( 'forced secretbox failure' );

		Encryption::encrypt( 'must-not-be-stored-as-plaintext' );
	}

	/**
	 * The same key Encryption derives via the stubbed wp_salt().
	 *
	 * @return string
	 */
	private function encryption_key() {
		$method = new \ReflectionMethod( Encryption::class, 'get_key' );
		$method->setAccessible( true );

		return $method->invoke( null );
	}
}
