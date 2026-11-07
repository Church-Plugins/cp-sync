<?php
/**
 * Tests for the shared sync-enable toggle FieldDefs, their defaults and help text,
 * and the serializer carrying a per-field `disabled` flag to the client projection.
 *
 * The toggle builder ( ChMS::get_sync_toggle_fields() ) is protected, so a lightweight
 * anonymous subclass exposes it — mirroring SettingsSchemaTest. Tests that need a
 * companion plugin run in a separate process so its function or constant does not leak.
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

	/* ------------------------------------------------- no companion plugin */

	public function test_groups_and_events_are_available_and_off_without_their_plugins() {
		$fields = $this->makeChms()->expose_sync_toggle_fields( [ 'sermons' => false ] );

		$this->assertSame( 'toggle', $fields['sync_groups']['type'] );
		$this->assertFalse( $fields['sync_groups']['default'] );
		$this->assertArrayNotHasKey( 'disabled', $fields['sync_groups'] );
		$this->assertStringContainsString( '/groups/', $fields['sync_groups']['help'] );

		$this->assertSame( 'toggle', $fields['sync_events']['type'] );
		$this->assertFalse( $fields['sync_events']['default'] );
		$this->assertArrayNotHasKey( 'disabled', $fields['sync_events'] );
		$this->assertStringContainsString( '/events/', $fields['sync_events']['help'] );
	}

	public function test_unsaved_toggles_follow_the_same_default_as_the_pull() {
		$this->assertFalse( ChMS::sync_setting_is_enabled( 'groups', null ) );
		$this->assertFalse( ChMS::sync_setting_is_enabled( 'events', null ) );
		$this->assertTrue( ChMS::sync_setting_is_enabled( 'groups', true ) );
		$this->assertTrue( ChMS::sync_setting_is_enabled( 'events', true ) );
	}

	/* ---------------------------------------------------- companion plugins */

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_groups_default_on_and_name_cp_groups_when_it_is_active() {
		eval( 'namespace { function cp_groups() { return true; } }' );

		$fields = $this->makeChms()->expose_sync_toggle_fields( [ 'sermons' => false ] );

		$this->assertTrue( $fields['sync_groups']['default'] );
		$this->assertStringContainsString( 'CP Groups', $fields['sync_groups']['help'] );
		$this->assertFalse( $fields['sync_events']['default'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_events_default_on_and_name_the_events_calendar_when_it_is_active() {
		define( 'TRIBE_EVENTS_FILE', '/tmp/the-events-calendar.php' );

		$fields = $this->makeChms()->expose_sync_toggle_fields( [ 'sermons' => false ] );

		$this->assertTrue( $fields['sync_events']['default'] );
		$this->assertStringContainsString( 'The Events Calendar', $fields['sync_events']['help'] );
		$this->assertFalse( $fields['sync_groups']['default'] );
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
						'fields' => [
							'sync_sermons' => [
								'type'     => 'toggle',
								'label'    => 'Sync Sermons',
								'disabled' => true,
								'help'     => 'Requires the CP Sermons plugin, which is not active on this site.',
							],
							'sync_groups'  => $chms->expose_sync_toggle_fields( [ 'sermons' => false ] )['sync_groups'],
						],
					],
				],
			],
		];

		$output = $chms->get_formatted_settings_schema();
		$fields = $output['connect']['sections'][0]['fields'];

		// The availability signal ( `disabled` ) and its help text survive into the
		// client-safe projection.
		$this->assertTrue( $fields['sync_sermons']['disabled'] );
		$this->assertSame(
			'Requires the CP Sermons plugin, which is not active on this site.',
			$fields['sync_sermons']['help']
		);

		// Available toggle carries no `disabled` key.
		$this->assertArrayNotHasKey( 'disabled', $fields['sync_groups'] );

		// Output remains JSON-serializable ( no closures leaked ).
		json_encode( $output );
		$this->assertSame( JSON_ERROR_NONE, json_last_error() );
	}

	/* ------------------------------------------- _Init availability helpers */

	public function test_groups_and_events_always_have_a_destination() {
		$this->assertTrue( Integrations_Init::is_integration_available( 'groups' ) );
		$this->assertTrue( Integrations_Init::is_integration_available( 'events' ) );
		// Unknown types are not gated by this helper.
		$this->assertTrue( Integrations_Init::is_integration_available( 'unknown' ) );
	}

	public function test_sermons_still_need_cp_sermons() {
		$this->assertFalse( Integrations_Init::is_integration_available( 'sermons' ) );
		$this->assertStringContainsString(
			'CP Sermons',
			Integrations_Init::integration_unavailable_message( 'sermons' )
		);
	}
}
