<?php
/**
 * Tests for the generic settings-schema serializer on CP_Sync\ChMS\ChMS.
 *
 * Exercises ChMS::get_formatted_settings_schema() — the client-safe projection of
 * a declared settings schema (the generalization of get_formatted_filter_config()).
 * Rather than booting the real PCO/CCB singletons (whose constructors register WP
 * hooks / option filters), the serializer is tested through a lightweight anonymous
 * subclass that only overrides get_settings_schema().
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\ChMS;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\ChMS::get_formatted_settings_schema
 * @covers \CP_Sync\ChMS\ChMS::format_schema_field
 * @covers \CP_Sync\ChMS\ChMS::get_settings_schema
 */
class SettingsSchemaTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// The serializer itself calls no WP functions, but keep the i18n stub for
		// parity with the other unit tests and any schema that builds labels via __().
		Functions\when( '__' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a ChMS test double whose schema is whatever the test supplies.
	 *
	 * @param array  $schema The schema get_settings_schema() should return.
	 * @param string $id     The ChMS id (drives optionsFetcher endpoint paths).
	 * @return ChMS
	 */
	private function makeChms( array $schema, string $id = 'test' ): ChMS {
		$chms = new class extends ChMS {
			/** @var array */
			public $test_schema = [];

			// Base constructor is protected; expose a public no-op so the double is
			// instantiable without registering any hooks.
			public function __construct() {}

			public function get_settings_schema() {
				return $this->test_schema;
			}
		};

		$chms->id          = $id;
		$chms->test_schema = $schema;

		return $chms;
	}

	/** A representative schema covering every serializer branch. */
	private function representativeSchema(): array {
		return [
			'connect' => [
				'label'    => 'Connect',
				'sections' => [
					[
						'title'       => 'Credentials',
						'description' => 'Enter your credentials.',
						'fields'      => [
							// Plain field — must pass through untouched.
							'subdomain' => [
								'type'    => 'text',
								'label'   => 'Subdomain',
								'default' => '',
							],
							// Server-only attributes — must be stripped.
							'password' => [
								'type'      => 'text',
								'inputType' => 'password',
								'label'     => 'Password',
								'sanitize'  => 'raw_credential',
								'validate'  => 'subdomain',
								'encrypt'   => true,
							],
						],
					],
				],
			],
			'groups' => [
				'label'    => 'Groups',
				'sections' => [
					[
						'fields' => [
							// Static option list — passes through as `options`.
							'visibility' => [
								'type'    => 'radio',
								'label'   => 'Visibility',
								'options' => [
									[ 'value' => 'all', 'label' => 'All' ],
									[ 'value' => 'public', 'label' => 'Public' ],
								],
							],
							// Dynamic option list — closure converts to optionsFetcher.
							'group_type' => [
								'type'    => 'select',
								'label'   => 'Group Type',
								'options' => static function () {
									return [ [ 'value' => 1, 'label' => 'Dynamic' ] ];
								},
							],
						],
					],
				],
			],
		];
	}

	/* -------------------------------------------------------------- empty schema */

	public function test_empty_schema_serializes_to_empty_array() {
		$chms = $this->makeChms( [] );

		$this->assertSame( [], $chms->get_formatted_settings_schema() );
	}

	/* ----------------------------------------------------------- JSON safety */

	public function test_output_is_json_serializable_without_error() {
		$chms   = $this->makeChms( $this->representativeSchema() );
		$output = $chms->get_formatted_settings_schema();

		$json = json_encode( $output );

		$this->assertSame( JSON_ERROR_NONE, json_last_error() );
		$this->assertIsString( $json );
		// A closure that leaked through would serialize to an empty object / warning.
		$this->assertStringNotContainsString( 'Closure', $json );
	}

	/* -------------------------------------------------------- structural passthrough */

	public function test_screen_and_section_shape_is_preserved() {
		$chms   = $this->makeChms( $this->representativeSchema() );
		$output = $chms->get_formatted_settings_schema();

		$this->assertArrayHasKey( 'connect', $output );
		$this->assertSame( 'Connect', $output['connect']['label'] );

		$section = $output['connect']['sections'][0];
		$this->assertSame( 'Credentials', $section['title'] );
		$this->assertSame( 'Enter your credentials.', $section['description'] );
		$this->assertArrayHasKey( 'fields', $section );
	}

	public function test_plain_field_passes_through_untouched() {
		$chms   = $this->makeChms( $this->representativeSchema() );
		$output = $chms->get_formatted_settings_schema();

		$field = $output['connect']['sections'][0]['fields']['subdomain'];

		$this->assertSame(
			[
				'type'    => 'text',
				'label'   => 'Subdomain',
				'default' => '',
			],
			$field
		);
	}

	/* ---------------------------------------------------------- server-only stripping */

	public function test_server_only_attributes_are_stripped() {
		$chms   = $this->makeChms( $this->representativeSchema() );
		$output = $chms->get_formatted_settings_schema();

		$field = $output['connect']['sections'][0]['fields']['password'];

		$this->assertArrayNotHasKey( 'sanitize', $field );
		$this->assertArrayNotHasKey( 'validate', $field );
		$this->assertArrayNotHasKey( 'encrypt', $field );

		// Non-server-only attributes on the same field survive.
		$this->assertSame( 'password', $field['inputType'] );
		$this->assertSame( 'text', $field['type'] );
	}

	/* ------------------------------------------------------------- options handling */

	public function test_static_options_pass_through() {
		$chms   = $this->makeChms( $this->representativeSchema() );
		$output = $chms->get_formatted_settings_schema();

		$field = $output['groups']['sections'][0]['fields']['visibility'];

		$this->assertSame(
			[
				[ 'value' => 'all', 'label' => 'All' ],
				[ 'value' => 'public', 'label' => 'Public' ],
			],
			$field['options']
		);
		$this->assertArrayNotHasKey( 'optionsFetcher', $field );
	}

	public function test_callable_options_convert_to_options_fetcher() {
		$chms   = $this->makeChms( $this->representativeSchema(), 'pco' );
		$output = $chms->get_formatted_settings_schema();

		$field = $output['groups']['sections'][0]['fields']['group_type'];

		// The closure is gone; a REST descriptor takes its place.
		$this->assertArrayNotHasKey( 'options', $field );
		$this->assertArrayHasKey( 'optionsFetcher', $field );
		$this->assertSame(
			'/cp-sync/v1/pco/selector/groups/group_type',
			$field['optionsFetcher']['endpoint']
		);
	}

	/* ------------------------------------------------ show_if + notice ( dual-source ) */

	/** An array `show_if.is` ( in-list match ) must pass through the serializer untouched. */
	public function test_array_show_if_is_passes_through_untouched() {
		$schema = [
			'ecp' => [
				'label'    => 'Events',
				'sections' => [
					[
						'fields' => [
							'filter' => [
								'type'    => 'filter-builder',
								'label'   => 'Calendar Filters',
								'show_if' => [ 'field' => 'source', 'is' => [ 'calendar', 'both' ] ],
							],
						],
					],
				],
			],
		];

		$field = $this->makeChms( $schema )->get_formatted_settings_schema()['ecp']['sections'][0]['fields']['filter'];

		$this->assertSame( [ 'field' => 'source', 'is' => [ 'calendar', 'both' ] ], $field['show_if'] );
	}

	/** A `notice` field ( static message, no stored value ) serializes with its message + show_if intact. */
	public function test_notice_field_passes_through() {
		$schema = [
			'ecp' => [
				'label'    => 'Events',
				'sections' => [
					[
						'fields' => [
							'events_dedup_notice' => [
								'type'    => 'notice',
								'message' => 'Not deduplicated.',
								'show_if' => [ 'field' => 'source', 'is' => 'both' ],
							],
						],
					],
				],
			],
		];

		$field = $this->makeChms( $schema )->get_formatted_settings_schema()['ecp']['sections'][0]['fields']['events_dedup_notice'];

		$this->assertSame( 'notice', $field['type'] );
		$this->assertSame( 'Not deduplicated.', $field['message'] );
		$this->assertSame( [ 'field' => 'source', 'is' => 'both' ], $field['show_if'] );
	}
}
