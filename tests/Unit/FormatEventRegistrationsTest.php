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
							'name'         => 'Main Campus',
							'address_data' => [
								[ 'types' => [ 'street_number' ], 'long_name' => '123' ],
								[ 'types' => [ 'route' ], 'long_name' => ' Main St' ],
								[ 'types' => [ 'locality' ], 'long_name' => 'Springfield' ],
								[ 'types' => [ 'administrative_area_level_1' ], 'long_name' => 'IL' ],
								[ 'types' => [ 'postal_code' ], 'long_name' => '62704' ],
								[ 'types' => [ 'country' ], 'long_name' => 'USA' ],
							],
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

		// Location resolved from SignupLocation.
		$this->assertSame( 'Main Campus', $result['Venue']['Venue'] );
		$this->assertSame( '123 Main St', $result['Venue']['Address'] );
		$this->assertSame( 'Springfield', $result['Venue']['City'] );
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
}
