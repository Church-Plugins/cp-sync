<?php
/**
 * Tests for the shared Groups/Events sync-enable toggle FieldDefs and their
 * availability-driven disabled/help injection, plus the serializer carrying the
 * per-field `disabled` flag through to the client projection.
 *
 * The toggle builder ( ChMS::get_sync_toggle_fields() ) accepts an injectable
 * availability map so both the "companion plugin present" and "absent" states can
 * be exercised without defining cp_groups() / TRIBE_EVENTS_FILE. It is protected,
 * so a lightweight anonymous subclass exposes it — mirroring SettingsSchemaTest.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\ChMS;
use CP_Sync\Integrations\_Init as Integrations_Init;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\ChMS::get_sync_toggle_fields
 * @covers \CP_Sync\ChMS\ChMS::format_schema_field
 * @covers \CP_Sync\Integrations\_Init::is_integration_available
 * @covers \CP_Sync\Integrations\_Init::integration_unavailable_message
 */
class SyncToggleFieldsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// The toggle labels/help and the "requires …" strings are wrapped in __().
		Functions\when( '__' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * A ChMS double exposing the protected toggle builder and the serializer.
	 *
	 * @return ChMS
	 */
	private function makeChms(): ChMS {
		$chms = new class extends ChMS {
			/** @var array */
			public $test_schema = [];

			// Base constructor registers hooks; replace with a no-op.
			public function __construct() {}

			public function get_settings_schema() {
				return $this->test_schema;
			}

			// Expose the protected builder with injectable availability.
			public function expose_sync_toggle_fields( $availability = null ) {
				return $this->get_sync_toggle_fields( $availability );
			}
		};

		$chms->id = 'test';

		return $chms;
	}

	/* ----------------------------------------------------- both available */

	public function test_both_available_emit_enabled_toggles() {
		$fields = $this->makeChms()->expose_sync_toggle_fields(
			[ 'groups' => true, 'events' => true ]
		);

		$this->assertSame( 'toggle', $fields['sync_groups']['type'] );
		$this->assertTrue( $fields['sync_groups']['default'] );
		$this->assertArrayNotHasKey( 'disabled', $fields['sync_groups'] );

		$this->assertSame( 'toggle', $fields['sync_events']['type'] );
		$this->assertTrue( $fields['sync_events']['default'] );
		$this->assertArrayNotHasKey( 'disabled', $fields['sync_events'] );

		// The normal ( available ) help text is NOT the "requires …" explanation.
		$this->assertStringNotContainsString( 'Requires', $fields['sync_groups']['help'] );
		$this->assertStringNotContainsString( 'Requires', $fields['sync_events']['help'] );
	}

	/* --------------------------------------------------- groups missing */

	public function test_groups_unavailable_disables_only_groups_toggle() {
		$fields = $this->makeChms()->expose_sync_toggle_fields(
			[ 'groups' => false, 'events' => true ]
		);

		$this->assertTrue( $fields['sync_groups']['disabled'] );
		$this->assertSame(
			'Requires the CP Groups plugin, which is not active on this site.',
			$fields['sync_groups']['help']
		);
		// default stays true even while disabled ( it is the stored fallback ).
		$this->assertTrue( $fields['sync_groups']['default'] );

		// Events toggle untouched.
		$this->assertArrayNotHasKey( 'disabled', $fields['sync_events'] );
	}

	/* --------------------------------------------------- events missing */

	public function test_events_unavailable_disables_only_events_toggle() {
		$fields = $this->makeChms()->expose_sync_toggle_fields(
			[ 'groups' => true, 'events' => false ]
		);

		$this->assertTrue( $fields['sync_events']['disabled'] );
		$this->assertSame(
			'Requires The Events Calendar plugin, which is not active on this site.',
			$fields['sync_events']['help']
		);

		$this->assertArrayNotHasKey( 'disabled', $fields['sync_groups'] );
	}

	/* ---------------------------------------------------- both missing */

	public function test_both_unavailable_disables_both_toggles() {
		$fields = $this->makeChms()->expose_sync_toggle_fields(
			[ 'groups' => false, 'events' => false ]
		);

		$this->assertTrue( $fields['sync_groups']['disabled'] );
		$this->assertTrue( $fields['sync_events']['disabled'] );
	}

	/* ------------------------------------- serializer passes disabled through */

	public function test_serializer_projects_disabled_flag_to_client() {
		$chms = $this->makeChms();

		$chms->test_schema = [
			'connect' => [
				'label'    => 'Connect',
				'sections' => [
					[
						'title'  => 'Sync',
						'fields' => $chms->expose_sync_toggle_fields(
							[ 'groups' => false, 'events' => true ]
						),
					],
				],
			],
		];

		$output = $chms->get_formatted_settings_schema();
		$fields = $output['connect']['sections'][0]['fields'];

		// The availability signal ( `disabled` ) and the swapped help text survive
		// into the client-safe projection.
		$this->assertTrue( $fields['sync_groups']['disabled'] );
		$this->assertSame(
			'Requires the CP Groups plugin, which is not active on this site.',
			$fields['sync_groups']['help']
		);

		// Available toggle carries no `disabled` key.
		$this->assertArrayNotHasKey( 'disabled', $fields['sync_events'] );

		// Output remains JSON-serializable ( no closures leaked ).
		json_encode( $output );
		$this->assertSame( JSON_ERROR_NONE, json_last_error() );
	}

	/* ------------------------------------------- _Init availability helpers */

	public function test_is_integration_available_false_when_plugins_absent() {
		// In the WP-free unit env neither cp_groups() nor TRIBE_EVENTS_FILE exist.
		$this->assertFalse( Integrations_Init::is_integration_available( 'groups' ) );
		$this->assertFalse( Integrations_Init::is_integration_available( 'events' ) );
		// Unknown types are not gated by this helper.
		$this->assertTrue( Integrations_Init::is_integration_available( 'unknown' ) );
	}

	public function test_unavailable_message_maps_type_to_plugin_name() {
		$this->assertStringContainsString(
			'CP Groups',
			Integrations_Init::integration_unavailable_message( 'groups' )
		);
		$this->assertStringContainsString(
			'The Events Calendar',
			Integrations_Init::integration_unavailable_message( 'events' )
		);
	}
}
