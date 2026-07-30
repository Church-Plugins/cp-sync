<?php
/**
 * Tests for the schema-driven REST save walk on CP_Sync\ChMS\ChMS.
 *
 * Exercises ChMS::sanitize_settings_by_schema() and its helpers — the per-field
 * sanitize/validate dispatch that replaced the blanket sanitizer + hard-coded
 * credential carve-out in the per-ChMS POST route ( Increment 3, Deliverable 1 ).
 *
 * These are pure-logic static methods, so they are called directly ( no ChMS
 * singleton is booted ). WordPress helpers the walk touches ( sanitize_text_field,
 * sanitize_key, is_wp_error, __ ) are stubbed with Brain Monkey; WP_Error itself is
 * provided by tests/bootstrap.php.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\ChMS;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\ChMS::sanitize_settings_by_schema
 * @covers \CP_Sync\ChMS\ChMS::sanitize_field_value
 * @covers \CP_Sync\ChMS\ChMS::apply_sanitize_rule
 * @covers \CP_Sync\ChMS\ChMS::default_sanitize_for_type
 * @covers \CP_Sync\ChMS\ChMS::validate_field_value
 * @covers \CP_Sync\ChMS\ChMS::index_schema_fields
 * @covers \CP_Sync\ChMS\ChMS::sanitize_settings_recursive
 */
class SettingsSanitizeWalkTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg( 1 );

		Functions\when( 'is_wp_error' )->alias(
			static fn( $thing ) => $thing instanceof \WP_Error
		);

		// A faithful-enough sanitize_text_field: strips tags, collapses whitespace,
		// drops control chars, trims. Distinct from raw_credential ( which preserves
		// tags and surrounding whitespace ), which is what several tests rely on.
		Functions\when( 'sanitize_text_field' )->alias(
			static function ( $s ) {
				if ( ! is_string( $s ) ) {
					return $s;
				}
				$s = preg_replace( '/<[^>]*>/', '', $s );
				$s = preg_replace( '/[\r\n\t]+/', ' ', $s );
				$s = preg_replace( '/[\x00-\x1F\x7F]/', '', $s );
				return trim( preg_replace( '/ +/', ' ', $s ) );
			}
		);

		Functions\when( 'sanitize_key' )->alias(
			static fn( $s ) => is_string( $s ) ? strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $s ) ) : $s
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/** The CCB-shaped schema most tests exercise. */
	private function schema(): array {
		return [
			'connect' => [
				'label'    => 'Connect',
				'sections' => [
					[
						'fields' => [
							'subdomain' => [ 'type' => 'text', 'validate' => 'subdomain' ],
							'username'  => [ 'type' => 'text', 'sanitize' => 'raw_credential', 'encrypt' => true ],
							'password'  => [ 'type' => 'text', 'sanitize' => 'raw_credential', 'encrypt' => true ],
							'slug'      => [ 'type' => 'text', 'sanitize' => 'key' ],
						],
					],
				],
			],
			'events' => [
				'label'    => 'Events',
				'sections' => [
					[
						'fields' => [
							'remove_events_outside_range' => [ 'type' => 'checkbox' ],
							'max_items'                   => [ 'type' => 'number' ],
							'filter'                      => [ 'type' => 'filter-builder' ],
						],
					],
				],
			],
		];
	}

	/* ------------------------------------------------- declared-field dispatch */

	public function test_raw_credential_preserves_special_characters() {
		// Tags, %-octets and surrounding whitespace must survive; only control
		// characters are stripped. sanitize_text_field would have mangled all of it.
		$password = "  p@ss<w>rd%20!\x01\x7f  ";

		$out = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			[ 'connect' => [ 'password' => $password ] ]
		);

		$this->assertSame( '  p@ss<w>rd%20!  ', $out['connect']['password'] );
	}

	public function test_raw_credential_non_string_passes_through() {
		$out = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			[ 'connect' => [ 'username' => 12345 ] ]
		);

		$this->assertSame( 12345, $out['connect']['username'] );
	}

	/**
	 * A `notice` field carries no stored value, so it never appears in an incoming
	 * payload and the walk simply never touches it ( the disclaimer is display-only ).
	 * Declaring it in the schema must not affect sanitization of real fields.
	 */
	public function test_notice_field_is_never_processed_by_the_walk() {
		$schema = [
			'ecp' => [
				'label'    => 'Events',
				'sections' => [
					[
						'fields' => [
							'source'              => [ 'type' => 'radio' ],
							'events_dedup_notice' => [ 'type' => 'notice', 'message' => 'Heads up.' ],
						],
					],
				],
			],
		];

		// The client only ever sends `source`; the notice has no key.
		$out = ChMS::sanitize_settings_by_schema( $schema, [ 'ecp' => [ 'source' => 'both' ] ] );

		$this->assertSame( [ 'ecp' => [ 'source' => 'both' ] ], $out );
		$this->assertArrayNotHasKey( 'events_dedup_notice', $out['ecp'] );
	}

	public function test_key_rule_applies_sanitize_key() {
		$out = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			[ 'connect' => [ 'slug' => 'My Slug!!' ] ]
		);

		$this->assertSame( 'myslug', $out['connect']['slug'] );
	}

	public function test_default_text_field_uses_sanitize_text_field() {
		// subdomain has no `sanitize` -> inferred `text` -> sanitize_text_field.
		$out = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			[ 'connect' => [ 'subdomain' => '  <b>mychurch</b>  ' ] ]
		);

		$this->assertSame( 'mychurch', $out['connect']['subdomain'] );
	}

	/* ---------------------------------------------------- type-inferred defaults */

	public function test_checkbox_type_infers_bool_cast() {
		$out = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			[ 'events' => [ 'remove_events_outside_range' => '1' ] ]
		);
		$this->assertTrue( $out['events']['remove_events_outside_range'] );

		$out = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			[ 'events' => [ 'remove_events_outside_range' => 0 ] ]
		);
		$this->assertFalse( $out['events']['remove_events_outside_range'] );
	}

	public function test_number_type_infers_int_cast() {
		$out = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			[ 'events' => [ 'max_items' => '42abc' ] ]
		);

		$this->assertSame( 42, $out['events']['max_items'] );
	}

	public function test_filter_builder_type_recurses_and_preserves_structure() {
		$filter = [
			'type'       => 'any',
			'conditions' => [
				[ 'selector' => 'name', 'compare' => 'is', 'value' => '<b>Youth</b>' ],
			],
		];

		$out = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			[ 'events' => [ 'filter' => $filter ] ]
		);

		// Structure preserved, leaf strings sanitized, list keys intact.
		$this->assertSame( 'any', $out['events']['filter']['type'] );
		$this->assertSame( 'is', $out['events']['filter']['conditions'][0]['compare'] );
		$this->assertSame( 'Youth', $out['events']['filter']['conditions'][0]['value'] );
	}

	/* --------------------------------------------------------- unknown-key fallback */

	public function test_unknown_widget_keys_are_kept_and_sanitized() {
		// date_range_mode / date_start / date_end are custom-widget keys the schema
		// deliberately does not declare — they must survive, never be dropped.
		$out = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			[
				'events' => [
					'date_range_mode' => 'custom',
					'date_start'      => '2026-01-01',
					'date_end'        => '<i>2026-12-31</i>',
				],
			]
		);

		$this->assertSame( 'custom', $out['events']['date_range_mode'] );
		$this->assertSame( '2026-01-01', $out['events']['date_start'] );
		$this->assertSame( '2026-12-31', $out['events']['date_end'] );
	}

	public function test_top_level_non_screen_group_falls_back_to_generic() {
		$out = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			[ 'not_a_screen' => [ 'foo' => '<b>bar</b>', 'flag' => true ] ]
		);

		$this->assertSame( 'bar', $out['not_a_screen']['foo'] );
		$this->assertTrue( $out['not_a_screen']['flag'] );
	}

	public function test_non_array_group_value_falls_back_to_generic() {
		$out = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			[ 'connect' => '<b>scalar</b>' ]
		);

		$this->assertSame( 'scalar', $out['connect'] );
	}

	/* ------------------------------------------------------------- validate rules */

	public function test_valid_subdomain_passes() {
		$out = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			[ 'connect' => [ 'subdomain' => 'my-church-123' ] ]
		);

		$this->assertSame( 'my-church-123', $out['connect']['subdomain'] );
	}

	public function test_empty_subdomain_is_allowed() {
		$out = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			[ 'connect' => [ 'subdomain' => '' ] ]
		);

		$this->assertSame( '', $out['connect']['subdomain'] );
	}

	public function test_invalid_subdomain_returns_wp_error_400() {
		$result = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			// The slash survives sanitize_text_field, so the value reaching validate is
			// still malformed and must be rejected ( not silently stripped ).
			[ 'connect' => [ 'subdomain' => 'bad/subdomain' ] ]
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_subdomain', $result->get_error_code() );
		$this->assertSame( 400, $result->data['status'] );
		$this->assertSame( 'subdomain', $result->data['field'] );
	}

	public function test_validation_failure_short_circuits_the_walk() {
		// Even with other valid groups present, the first failure aborts with WP_Error.
		$result = ChMS::sanitize_settings_by_schema(
			$this->schema(),
			[
				'connect' => [ 'subdomain' => 'has spaces' ],
				'events'  => [ 'max_items' => '5' ],
			]
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/* --------------------------------------------------------------- index helper */

	public function test_index_schema_fields_flattens_screens_and_sections() {
		$index = ChMS::index_schema_fields( $this->schema() );

		$this->assertArrayHasKey( 'connect', $index );
		$this->assertArrayHasKey( 'subdomain', $index['connect'] );
		$this->assertArrayHasKey( 'filter', $index['events'] );
		$this->assertSame( 'raw_credential', $index['connect']['username']['sanitize'] );
	}

	public function test_index_of_non_array_is_empty() {
		$this->assertSame( [], ChMS::index_schema_fields( 'nope' ) );
	}
}
