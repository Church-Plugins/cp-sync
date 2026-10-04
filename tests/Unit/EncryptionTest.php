<?php
/**
 * Tests for CP_Sync\ChMS\Encryption — the at-rest credential cipher.
 *
 * Pure-logic round-trip coverage plus the two backward-compatibility guarantees
 * that keep existing installs working: legacy plaintext (no marker) is returned
 * untouched, and ciphertext is detectable via its version marker. wp_salt() is
 * stubbed with Brain Monkey so the key derivation runs without WordPress.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\Encryption;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\Encryption
 */
class EncryptionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// Deterministic key material without booting WordPress.
		Functions\when( 'wp_salt' )->justReturn( 'unit-test-salt-value-1234567890' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_round_trip_returns_original_plaintext(): void {
		$secret    = 'super-secret-password!@#$%^&*()';
		$encrypted = Encryption::encrypt( $secret );

		$this->assertNotSame( $secret, $encrypted, 'Ciphertext must differ from plaintext.' );
		$this->assertTrue( Encryption::is_encrypted( $encrypted ), 'Ciphertext must carry a marker.' );
		$this->assertSame( $secret, Encryption::decrypt( $encrypted ), 'Decryption must recover the original.' );
	}

	public function test_ciphertext_is_non_deterministic(): void {
		$secret = 'the-same-input';

		$this->assertNotSame(
			Encryption::encrypt( $secret ),
			Encryption::encrypt( $secret ),
			'Each encryption should use a fresh nonce/IV.'
		);
	}

	public function test_legacy_plaintext_passes_through_unchanged(): void {
		// A value stored before encryption existed has no marker and must be
		// returned verbatim so existing installs are not locked out.
		$legacy = 'legacy-plaintext-api-user';

		$this->assertFalse( Encryption::is_encrypted( $legacy ) );
		$this->assertSame( $legacy, Encryption::decrypt( $legacy ) );
	}

	public function test_empty_and_non_string_values_round_trip(): void {
		$this->assertSame( '', Encryption::encrypt( '' ) );
		$this->assertSame( '', Encryption::decrypt( '' ) );
		$this->assertSame( '', Encryption::decrypt( Encryption::encrypt( '' ) ) );
	}

	public function test_already_encrypted_value_is_not_double_encrypted(): void {
		$encrypted = Encryption::encrypt( 'value' );

		$this->assertSame(
			$encrypted,
			Encryption::encrypt( $encrypted ),
			'Encrypting an already-encrypted value must be a no-op.'
		);
		$this->assertSame( 'value', Encryption::decrypt( $encrypted ) );
	}

	public function test_tampered_ciphertext_does_not_return_plaintext(): void {
		$encrypted = Encryption::encrypt( 'secret' );
		// Flip a character in the base64 body.
		$tampered = substr( $encrypted, 0, -2 ) . ( 'A' === substr( $encrypted, -2, 1 ) ? 'B' : 'A' ) . substr( $encrypted, -1 );

		$this->assertSame( '', Encryption::decrypt( $tampered ), 'Authenticated decryption must fail closed.' );
	}
}
