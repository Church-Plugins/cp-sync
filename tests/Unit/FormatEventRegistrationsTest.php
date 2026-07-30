<?php
/**
 * Tests for CP_Sync\ChMS\PCO::format_event_from_registrations() — the pure mapping
 * from a documented Planning Center Registrations `Signup` (plus its included
 * SignupTime / SignupLocation / Category context) to the CP Sync event item shape
 * consumed by the TEC integration.
 *
 * The method touches no API client. The WordPress helpers it calls
 * (wp_list_pluck / wp_timezone / sanitize_title) are stubbed with Brain Monkey,
 * and the PCO instance is built without its (hook-registering) constructor via
 * reflection — mirroring FormatSermonTest.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\PCO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\PCO::format_event_from_registrations
 */
class FormatEventRegistrationsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'UTC' ) );
		// The formatter logs skipped signups; provide a no-op logger.
		Functions\when( 'cp_sync' )->justReturn(
			new class() {
				public $logging;
				public function __construct() {
					$this->logging = new class() {
						public function log( $message ) {}
					};
				}
			}
		);
		Functions\when( 'sanitize_title' )->alias(
			static fn( $title ) => strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( $title ) ) )
		);
		Functions\when( 'wp_list_pluck' )->alias(
			static function ( $list, $field ) {
				return array_map(
					static fn( $row ) => is_array( $row ) ? ( $row[ $field ] ?? null ) : ( $row->$field ?? null ),
					$list
				);
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a PCO instance without invoking the constructor ( which registers WP hooks ).
	 */
	private function makePco(): PCO {
		return ( new \ReflectionClass( PCO::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * A fully-populated signup maps every field, resolving dates/category/location
	 * from context, prefixes the chms_id with `reg_`, and derives the sold-out flag.
	 */
	public function test_maps_full_signup() {
		$signup = [
			'id'         => '77',
			'attributes' => [
				'name'                 => 'Summer Camp',
				'description'          => 'A week of fun.',
				'logo_url'             => 'https://example.com/logo.png',
				'new_registration_url' => 'https://church.center/signups/77',
				'archived'             => false,
				'at_maximum_capacity'  => true,
			],
			'relationships' => [
				'signup_times'    => [ 'data' => [ [ 'type' => 'SignupTime', 'id' => 'st1' ] ] ],
				'categories'      => [ 'data' => [ [ 'type' => 'Category', 'id' => 'c1' ] ] ],
				'signup_location' => [ 'data' => [ 'type' => 'SignupLocation', 'id' => 'loc1' ] ],
			],
		];

		$context = [
			'relational_data' => [
				'SignupTime' => [
					'st1' => [
						'id'         => 'st1',
						'attributes' => [
							'starts_at' => '2026-08-01T14:00:00Z',
							'ends_at'   => '2026-08-05T18:30:00Z',
							'all_day'   => false,
						],
					],
				],
				'Category' => [
					'c1' => [ 'id' => 'c1', 'attributes' => [ 'name' => 'Youth Events' ] ],
				],
				'SignupLocation' => [
					'loc1' => [
						'id'         => 'loc1',
						'attributes' => [
							// Live SignupLocation shape (flat), not the documented address_data.
							'name'              => 'Main Campus',
							'formatted_address' => "123 Main St\nSpringfield, IL 62704",
						],
					],
				],
			],
		];

		$result = $this->makePco()->format_event_from_registrations( $signup, $context );

		// reg_ prefix (the store-key migration).
		$this->assertSame( 'reg_77', $result['chms_id'] );

		$this->assertSame( 'Summer Camp', $result['post_title'] );
		$this->assertSame( 'A week of fun.', $result['post_content'] );
		$this->assertSame( 'https://example.com/logo.png', $result['thumbnail_url'] );

		// Registration URL used directly, no suffix.
		$this->assertSame( 'https://church.center/signups/77', $result['meta_input']['registration_url'] );

		// Sold-out derived from at_maximum_capacity when present.
		$this->assertTrue( $result['meta_input']['registration_sold_out'] );

		// Dates from the first signup_time.
		$this->assertSame( '2026-08-01', $result['EventStartDate'] );
		$this->assertSame( '14', $result['EventStartHour'] );
		$this->assertSame( '00', $result['EventStartMinute'] );
		$this->assertSame( '2026-08-05', $result['EventEndDate'] );
		$this->assertSame( '18', $result['EventEndHour'] );
		$this->assertSame( '30', $result['EventEndMinute'] );
		$this->assertArrayNotHasKey( 'EventAllDay', $result );

		// Category keyed by a slug derived from the name (Category has no slug attribute).
		$this->assertSame( [ 'youth-events' => 'Youth Events' ], $result['event_category'] );

		// Location resolved from SignupLocation into TEC's EventVenue contract.
		$this->assertSame( 'Main Campus', $result['EventVenue']['venue'] );
		$this->assertSame( '123 Main St', $result['EventVenue']['address'] );
		$this->assertSame( 'Springfield', $result['EventVenue']['city'] );
		$this->assertSame( 'IL', $result['EventVenue']['state'] );
		$this->assertSame( '62704', $result['EventVenue']['zip'] );
	}

	/**
	 * An all-day signup sets EventAllDay.
	 */
	public function test_all_day_flag() {
		$signup  = $this->minimalSignup();
		$context = $this->minimalContext();
		$context['relational_data']['SignupTime']['st1']['attributes']['all_day'] = true;

		$result = $this->makePco()->format_event_from_registrations( $signup, $context );

		$this->assertTrue( $result['EventAllDay'] );
	}

	/**
	 * A signup with no signup_times cannot be dated → skipped ( false ).
	 */
	public function test_missing_signup_times_returns_false() {
		$signup = $this->minimalSignup();
		$signup['relationships']['signup_times']['data'] = [];

		$result = $this->makePco()->format_event_from_registrations( $signup, $this->minimalContext() );

		$this->assertFalse( $result );
	}

	/**
	 * A signup whose first signup_time lacks start/end is skipped ( false ).
	 */
	public function test_missing_dates_returns_false() {
		$signup  = $this->minimalSignup();
		$context = $this->minimalContext();
		$context['relational_data']['SignupTime']['st1']['attributes'] = [ 'all_day' => false ];

		$result = $this->makePco()->format_event_from_registrations( $signup, $context );

		$this->assertFalse( $result );
	}

	/**
	 * When at_maximum_capacity is absent ( sparse fieldset unavailable ), no sold-out
	 * meta is emitted rather than defaulting to a false value.
	 */
	public function test_sold_out_omitted_when_attribute_absent() {
		$signup = $this->minimalSignup();
		unset( $signup['attributes']['at_maximum_capacity'] );

		$result = $this->makePco()->format_event_from_registrations( $signup, $this->minimalContext() );

		$this->assertArrayNotHasKey( 'registration_sold_out', $result['meta_input'] );
	}

	/**
	 * at_maximum_capacity present and false → sold-out meta emitted as false.
	 */
	public function test_sold_out_false_when_attribute_present_and_false() {
		$signup = $this->minimalSignup();
		$signup['attributes']['at_maximum_capacity'] = false;

		$result = $this->makePco()->format_event_from_registrations( $signup, $this->minimalContext() );

		$this->assertArrayHasKey( 'registration_sold_out', $result['meta_input'] );
		$this->assertFalse( $result['meta_input']['registration_sold_out'] );
	}

	/** A minimal but valid signup ( dated, no category/location ). */
	private function minimalSignup(): array {
		return [
			'id'         => '5',
			'attributes' => [
				'name'                 => 'Retreat',
				'description'          => '',
				'new_registration_url' => 'https://church.center/signups/5',
				'archived'             => false,
				'at_maximum_capacity'  => false,
			],
			'relationships' => [
				'signup_times'    => [ 'data' => [ [ 'type' => 'SignupTime', 'id' => 'st1' ] ] ],
				'categories'      => [ 'data' => [] ],
				'signup_location' => [ 'data' => [] ],
			],
		];
	}

	/** Context matching minimalSignup(). */
	private function minimalContext(): array {
		return [
			'relational_data' => [
				'SignupTime' => [
					'st1' => [
						'id'         => 'st1',
						'attributes' => [
							'starts_at' => '2026-09-10T09:00:00Z',
							'ends_at'   => '2026-09-10T11:00:00Z',
							'all_day'   => false,
						],
					],
				],
			],
		];
	}

	/**
	 * parse_signup_location: the LIVE (flat) SignupLocation shape — verified against a
	 * real account 2026-07-29; the documented `address_data` attribute is not returned.
	 */
	public function test_parse_signup_location_us_address(): void {
		$venue = PCO::parse_signup_location( [
			'name'              => 'Church',
			'formatted_address' => "401 Wabash Ave\nGranite Falls, WA 98252",
		] );

		$this->assertSame( 'Church', $venue['venue'] );
		$this->assertSame( '401 Wabash Ave', $venue['address'] );
		$this->assertSame( 'Granite Falls', $venue['city'] );
		$this->assertSame( 'WA', $venue['state'] );
		$this->assertSame( '98252', $venue['zip'] );
	}

	public function test_parse_signup_location_unparseable_locality_kept_as_city(): void {
		$venue = PCO::parse_signup_location( [
			'name'              => 'Overseas Campus',
			'formatted_address' => "12 Rue de Rivoli\n75001 Paris",
		] );

		$this->assertSame( 'Overseas Campus', $venue['venue'] );
		$this->assertSame( '12 Rue de Rivoli', $venue['address'] );
		$this->assertSame( '75001 Paris', $venue['city'] );
		$this->assertArrayNotHasKey( 'state', $venue );
	}

	public function test_parse_signup_location_name_falls_back_to_street(): void {
		$venue = PCO::parse_signup_location( [
			'formatted_address' => "401 Wabash Ave\nGranite Falls, WA 98252",
		] );

		$this->assertSame( '401 Wabash Ave', $venue['venue'] );
	}

	public function test_parse_signup_location_empty_yields_no_venue(): void {
		$this->assertSame( [], PCO::parse_signup_location( [] ) );
		$this->assertSame( [], PCO::parse_signup_location( [ 'name' => '', 'formatted_address' => '' ] ) );
	}
}
