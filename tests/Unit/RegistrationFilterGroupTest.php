<?php
/**
 * Tests for PCO::get_formatted_filter_config() — the hand-built
 * `events_registrations` client filter group ( amendment #3 ).
 *
 * The group is projected from get_registration_filter_config() with explicit
 * type/supports and an explicit optionsFetcher endpoint for
 * `registration_category` ( which has no options callable ). The PCO instance is
 * built without its ( hook-registering ) constructor via reflection, then setup()
 * populates the supported integrations the base formatter iterates.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\PCO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\PCO::get_formatted_filter_config
 */
class RegistrationFilterGroupTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function makePco(): PCO {
		$pco = ( new \ReflectionClass( PCO::class ) )->newInstanceWithoutConstructor();
		$pco->setup(); // populate supported_integrations for the base formatter
		return $pco;
	}

	public function test_output_contains_both_events_and_events_registrations_groups() {
		$output = $this->makePco()->get_formatted_filter_config();

		// The calendar events group ( from the base formatter ) still exists...
		$this->assertArrayHasKey( 'events', $output );
		// ...alongside the new registrations pseudo-type group.
		$this->assertArrayHasKey( 'events_registrations', $output );
		// And the other registered integrations are untouched.
		$this->assertArrayHasKey( 'groups', $output );
		$this->assertArrayHasKey( 'sermons', $output );
	}

	public function test_registration_category_wires_explicit_options_endpoint() {
		$output = $this->makePco()->get_formatted_filter_config();
		$group  = $output['events_registrations'];

		$this->assertArrayHasKey( 'registration_category', $group );
		$this->assertSame(
			'/cp-sync/v1/pco/events/registration_categories',
			$group['registration_category']['optionsFetcher']['endpoint']
		);
		$this->assertSame( 'select', $group['registration_category']['type'] );
	}

	public function test_projected_fields_carry_type_and_supports() {
		$group = $this->makePco()->get_formatted_filter_config()['events_registrations'];

		$this->assertSame( 'date', $group['start_date']['type'] );
		$this->assertContains( 'is_greater_than', $group['start_date']['supports'] );
		$this->assertSame( 'text', $group['event_name']['type'] );
	}

	public function test_group_is_json_serializable_without_closures() {
		// get_registration_filter_config() carries a `format` closure that must NOT
		// leak into the client projection.
		$output = $this->makePco()->get_formatted_filter_config();

		$json = json_encode( $output['events_registrations'] );

		$this->assertSame( JSON_ERROR_NONE, json_last_error() );
		$this->assertStringNotContainsString( 'Closure', $json );
		$this->assertArrayNotHasKey( 'format', $output['events_registrations']['registration_category'] );
	}
}
