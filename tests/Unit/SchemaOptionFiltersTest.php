<?php
/**
 * Tests for the schema-driven at-rest encryption plumbing on CP_Sync\ChMS\ChMS.
 *
 * Exercises the generic option-filter mechanism ( Increment 3, Deliverable 2 ) that
 * replaced CCB's bespoke pre_update_settings()/decrypt_settings():
 *   - register_schema_option_filters() wires the pre_update_option / option filter
 *     pair only when the schema declares `encrypt` / `validate` fields, and never
 *     double-registers for a given settings key.
 *   - pre_update_settings() encrypts `encrypt: true` fields at their group/field path
 *     and strips invalid `validate: 'subdomain'` values ( keep-prior-valid-or-blank ).
 *   - decrypt_settings() transparently decrypts on read and passes legacy plaintext
 *     through unchanged.
 *
 * Real Encryption is used ( wp_salt stubbed for deterministic key material ); the ChMS
 * singleton is never booted — a lightweight anonymous subclass supplies the schema and
 * a no-op constructor so no WP hooks are registered incidentally.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\ChMS;
use CP_Sync\ChMS\Encryption;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\ChMS::register_schema_option_filters
 * @covers \CP_Sync\ChMS\ChMS::pre_update_settings
 * @covers \CP_Sync\ChMS\ChMS::decrypt_settings
 * @covers \CP_Sync\ChMS\ChMS::validate_strip_at_option
 * @covers \CP_Sync\ChMS\ChMS::collect_schema_fields
 */
class SchemaOptionFiltersTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		// Deterministic key material for the real Encryption helper.
		Functions\when( 'wp_salt' )->justReturn( 'unit-test-salt-value-1234567890' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a ChMS test double with the given schema and settings key.
	 *
	 * @param array  $schema       The schema get_settings_schema() should return.
	 * @param string $settings_key The wp_options key ( drives filter hook names ).
	 * @return ChMS
	 */
	private function makeChms( array $schema, string $settings_key ): ChMS {
		$chms = new class extends ChMS {
			/** @var array */
			public $test_schema = [];

			// Public no-op constructor: skip the base ctor so no filters register
			// incidentally during instantiation.
			public function __construct() {}

			public function get_settings_schema() {
				return $this->test_schema;
			}
		};

		$chms->settings_key = $settings_key;
		$chms->test_schema  = $schema;

		return $chms;
	}

	/** The CCB-shaped schema: two encrypted credentials + a validated subdomain. */
	private function ccbSchema(): array {
		return [
			'connect' => [
				'label'    => 'Connect',
				'sections' => [
					[
						'fields' => [
							'subdomain' => [ 'type' => 'text', 'validate' => 'subdomain' ],
							'username'  => [ 'type' => 'text', 'sanitize' => 'raw_credential', 'encrypt' => true ],
							'password'  => [ 'type' => 'text', 'sanitize' => 'raw_credential', 'encrypt' => true ],
						],
					],
				],
			],
		];
	}

	/** A unique settings key so the per-key registration guard never collides. */
	private function uniqueKey(): string {
		return 'cp_sync_test_' . uniqid( '', true );
	}

	/* ------------------------------------------------------ filter registration */

	public function test_registers_both_filters_for_encrypt_and_validate_schema() {
		$key = $this->uniqueKey();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'pre_update_option_' . $key, \Mockery::type( 'array' ), 10, 2 );
		Functions\expect( 'add_filter' )
			->once()
			->with( 'option_' . $key, \Mockery::type( 'array' ) );

		$this->makeChms( $this->ccbSchema(), $key )->register_schema_option_filters();

		// Brain Monkey verifies the expectations in tearDown; keep PHPUnit from
		// flagging the test as assertion-less.
		$this->addToAssertionCount( 1 );
	}

	public function test_validate_only_schema_registers_pre_update_only() {
		$key    = $this->uniqueKey();
		$schema = [
			'connect' => [
				'sections' => [
					[ 'fields' => [ 'subdomain' => [ 'type' => 'text', 'validate' => 'subdomain' ] ] ],
				],
			],
		];

		// pre_update_option registered ( for the validate strip ); no option decrypt filter.
		Functions\expect( 'add_filter' )->once()->with( 'pre_update_option_' . $key, \Mockery::type( 'array' ), 10, 2 );

		$this->makeChms( $schema, $key )->register_schema_option_filters();
		$this->addToAssertionCount( 1 );
	}

	public function test_no_server_attributes_registers_no_filters() {
		$key    = $this->uniqueKey();
		$schema = [
			'groups' => [
				'sections' => [
					[ 'fields' => [ 'visibility' => [ 'type' => 'radio' ] ] ],
				],
			],
		];

		Functions\expect( 'add_filter' )->never();

		$this->makeChms( $schema, $key )->register_schema_option_filters();
		$this->addToAssertionCount( 1 );
	}

	public function test_does_not_double_register_for_same_settings_key() {
		$key = $this->uniqueKey();

		// Only the first call wires the two filters; the second is a guarded no-op.
		Functions\expect( 'add_filter' )->twice();

		$chms = $this->makeChms( $this->ccbSchema(), $key );
		$chms->register_schema_option_filters();
		$chms->register_schema_option_filters();
		$this->addToAssertionCount( 1 );
	}

	/* --------------------------------------------------------- encrypt on save */

	public function test_pre_update_encrypts_marked_fields_only() {
		$chms = $this->makeChms( $this->ccbSchema(), $this->uniqueKey() );

		$saved = $chms->pre_update_settings(
			[
				'connect' => [
					'subdomain' => 'my-church',
					'username'  => 'api-user',
					'password'  => 'p@ss<w>rd%20!',
				],
			],
			false
		);

		// Credentials encrypted, subdomain left as plaintext.
		$this->assertTrue( Encryption::is_encrypted( $saved['connect']['username'] ) );
		$this->assertTrue( Encryption::is_encrypted( $saved['connect']['password'] ) );
		$this->assertSame( 'my-church', $saved['connect']['subdomain'] );

		// Special characters survive the round trip.
		$this->assertSame( 'p@ss<w>rd%20!', Encryption::decrypt( $saved['connect']['password'] ) );
	}

	public function test_pre_update_skips_empty_credentials() {
		$chms = $this->makeChms( $this->ccbSchema(), $this->uniqueKey() );

		$saved = $chms->pre_update_settings(
			[ 'connect' => [ 'subdomain' => 'my-church', 'username' => '', 'password' => '' ] ],
			false
		);

		$this->assertSame( '', $saved['connect']['username'] );
		$this->assertSame( '', $saved['connect']['password'] );
	}

	public function test_pre_update_passes_non_array_through() {
		$chms = $this->makeChms( $this->ccbSchema(), $this->uniqueKey() );

		$this->assertSame( 'scalar', $chms->pre_update_settings( 'scalar', false ) );
	}

	/* -------------------------------------------------- subdomain strip at option */

	public function test_pre_update_strips_invalid_subdomain_keeping_prior_valid() {
		Functions\when( 'update_option' )->justReturn( true );

		$chms = $this->makeChms( $this->ccbSchema(), $this->uniqueKey() );

		$saved = $chms->pre_update_settings(
			[ 'connect' => [ 'subdomain' => 'bad/subdomain' ] ],
			[ 'connect' => [ 'subdomain' => 'prior-valid' ] ]
		);

		$this->assertSame( 'prior-valid', $saved['connect']['subdomain'] );
	}

	public function test_pre_update_strips_invalid_subdomain_to_blank_without_prior() {
		Functions\when( 'update_option' )->justReturn( true );

		$chms = $this->makeChms( $this->ccbSchema(), $this->uniqueKey() );

		$saved = $chms->pre_update_settings(
			[ 'connect' => [ 'subdomain' => 'bad subdomain' ] ],
			false
		);

		$this->assertSame( '', $saved['connect']['subdomain'] );
	}

	public function test_pre_update_keeps_valid_subdomain() {
		$chms = $this->makeChms( $this->ccbSchema(), $this->uniqueKey() );

		$saved = $chms->pre_update_settings(
			[ 'connect' => [ 'subdomain' => 'good-sub-123' ] ],
			false
		);

		$this->assertSame( 'good-sub-123', $saved['connect']['subdomain'] );
	}

	/* ------------------------------------------------------- decrypt on read */

	public function test_decrypt_recovers_encrypted_credentials() {
		$chms = $this->makeChms( $this->ccbSchema(), $this->uniqueKey() );

		$stored = [
			'connect' => [
				'subdomain' => 'my-church',
				'username'  => Encryption::encrypt( 'api-user' ),
				'password'  => Encryption::encrypt( 'secret-pass' ),
			],
		];

		$read = $chms->decrypt_settings( $stored );

		$this->assertSame( 'api-user', $read['connect']['username'] );
		$this->assertSame( 'secret-pass', $read['connect']['password'] );
		$this->assertSame( 'my-church', $read['connect']['subdomain'] );
	}

	public function test_decrypt_passes_legacy_plaintext_through() {
		$chms = $this->makeChms( $this->ccbSchema(), $this->uniqueKey() );

		// Values with no cipher marker predate encryption and must round-trip verbatim.
		$read = $chms->decrypt_settings(
			[ 'connect' => [ 'username' => 'legacy-user', 'password' => 'legacy-pass' ] ]
		);

		$this->assertSame( 'legacy-user', $read['connect']['username'] );
		$this->assertSame( 'legacy-pass', $read['connect']['password'] );
	}

	public function test_encrypt_then_decrypt_is_identity() {
		$chms = $this->makeChms( $this->ccbSchema(), $this->uniqueKey() );

		$original = [
			'connect' => [
				'subdomain' => 'my-church',
				'username'  => 'user@example.com',
				'password'  => 'C0mpl3x! <pass> %20 &value',
			],
		];

		$read = $chms->decrypt_settings( $chms->pre_update_settings( $original, false ) );

		$this->assertSame( $original['connect']['username'], $read['connect']['username'] );
		$this->assertSame( $original['connect']['password'], $read['connect']['password'] );
	}

	public function test_decrypt_passes_non_array_through() {
		$chms = $this->makeChms( $this->ccbSchema(), $this->uniqueKey() );

		$this->assertSame( 'scalar', $chms->decrypt_settings( 'scalar' ) );
	}
}
