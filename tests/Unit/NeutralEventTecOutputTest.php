<?php
/**
 * Neutral event record → The Events Calendar field output.
 *
 * Proves the TEC item is field-for-field the same as the pre-mapper
 * formatters when the conversion timezone matches the one those formatters
 * used, and that an organization timezone from the ChMS replaces that
 * assumption (including across a DST change and a midnight boundary).
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\CCB;
use CP_Sync\ChMS\PCO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\PCO::format_event_from_calendar
 * @covers \CP_Sync\ChMS\PCO::format_event_from_registrations
 * @covers \CP_Sync\ChMS\PCO::to_neutral_calendar_event
 * @covers \CP_Sync\ChMS\CCB::format_event
 * @covers \CP_Sync\Events\TecEventMapper
 * @covers \CP_Sync\Events\EventTime
 */
class NeutralEventTecOutputTest extends TestCase {

	/** @var string */
	private $original_timezone;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->original_timezone = date_default_timezone_get();
		date_default_timezone_set( 'UTC' );

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
		Functions\when( 'get_option' )->alias(
			static function ( $key, $default = [] ) {
				if ( 'cp_sync_ccb_settings' === $key ) {
					return [
						'connect' => [ 'subdomain' => 'demo' ],
					];
				}
				return $default;
			}
		);
	}

	protected function tearDown(): void {
		date_default_timezone_set( $this->original_timezone );
		Monkey\tearDown();
		parent::tearDown();
	}

	private function makePco(): PCO {
		return ( new \ReflectionClass( PCO::class ) )->newInstanceWithoutConstructor();
	}

	private function makeCcb(): CCB {
		return ( new \ReflectionClass( CCB::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Calendar instance shaped like a live Planning Center payload.
	 *
	 * @param array $event_attributes    Overrides for the related Event.
	 * @param array $instance_attributes Overrides for the instance.
	 * @return array
	 */
	private function calendarFixture( array $event_attributes = [], array $instance_attributes = [] ): array {
		$instance = [
			'id'         => 'inst-1',
			'attributes' => array_merge(
				[
					'all_day_event'    => false,
					'location'         => 'Church - 401 Wabash Ave, Granite Falls, WA 98252, USA',
					'church_center_url' => 'https://church.center/calendar/event/1',
				],
				$instance_attributes
			),
			'relationships' => [
				'event'       => [ 'data' => [ 'id' => 'e1' ] ],
				'event_times' => [ 'data' => [ [ 'id' => 't1' ] ] ],
				'tags'        => [ 'data' => [ [ 'id' => 'tag-9' ] ] ],
			],
		];

		$context = [
			'relational_data' => [
				'Event'     => [
					'e1' => [
						'id'         => 'e1',
						'attributes' => array_merge(
							[
								'name'             => 'Sunday Service',
								'description'      => '<p>Hello</p>',
								'summary'          => 'Short',
								'registration_url' => 'https://example.com/reg',
								'image_url'        => 'https://example.com/img.png',
							],
							$event_attributes
						),
					],
				],
				'EventTime' => [
					't1' => [
						'id'         => 't1',
						'attributes' => [
							'starts_at' => '2026-08-01T15:30:00Z',
							'ends_at'   => '2026-08-01T17:00:00Z',
						],
					],
				],
				'Tag'       => [
					'tag-9' => [
						'id'         => 'tag-9',
						'attributes' => [
							'taxonomy' => 'cps_topic',
							'name'     => 'Worship',
						],
					],
				],
			],
		];

		return [ $instance, $context ];
	}

	public function test_pco_calendar_tec_fields_match_when_site_timezone_is_used() {
		list( $instance, $context ) = $this->calendarFixture();

		$result = $this->makePco()->format_event_from_calendar( $instance, $context );

		$this->assertSame(
			[
				'chms_id'          => 'inst-1',
				'post_status'      => 'publish',
				'post_title'       => 'Sunday Service',
				'post_content'     => '<p>Hello</p>',
				'post_excerpt'     => 'Short',
				'tax_input'        => [
					'cps_topic' => [ 'tag-9' ],
				],
				'event_category'   => [],
				'thumbnail_url'    => 'https://example.com/img.png',
				'meta_input'       => [
					'registration_url' => 'https://example.com/reg',
				],
				'EventStartDate'   => '2026-08-01',
				'EventEndDate'     => '2026-08-01',
				'EventAllDay'      => false,
				'EventStartHour'   => '15',
				'EventStartMinute' => '30',
				'EventEndHour'     => '17',
				'EventEndMinute'   => '00',
				'EventURL'         => 'https://example.com/reg',
				'EventVenue'       => [
					'venue'   => 'Church',
					'state'   => 'WA',
					'zip'     => '98252',
					'address' => '401 Wabash Ave',
					'city'    => 'Granite Falls',
				],
			],
			$result
		);
	}

	public function test_pco_calendar_neutral_record_stores_utc() {
		list( $instance, $context ) = $this->calendarFixture();

		$neutral = $this->makePco()->to_neutral_calendar_event( $instance, $context );

		$this->assertSame( 'pco', $neutral['provider'] );
		$this->assertSame( '2026-08-01 15:30:00', $neutral['start_utc'] );
		$this->assertSame( '2026-08-01 17:00:00', $neutral['end_utc'] );
		$this->assertSame( 'UTC', $neutral['site_timezone'] );
		$this->assertSame( 'Church', $neutral['location_name'] );
		$this->assertSame( 'https://example.com/reg', $neutral['registration_url'] );
		$this->assertArrayNotHasKey( 'EventTimezone', $this->makePco()->format_event_from_calendar( $instance, $context ) );
	}

	public function test_tec_times_use_the_site_timezone_not_the_organization_timezone() {
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'America/Los_Angeles' ) );

		list( $instance, $context ) = $this->calendarFixture();
		$context['organization_timezone'] = 'America/Chicago';
		$context['relational_data']['EventTime']['t1']['attributes'] = [
			'starts_at' => '2026-07-15T18:00:00Z',
			'ends_at'   => '2026-07-15T19:30:00Z',
		];

		$pco    = $this->makePco();
		$result = $pco->format_event_from_calendar( $instance, $context );

		// July: Chicago is 13:00 CDT, Los Angeles (the site) is 11:00 PDT.
		$this->assertSame( '2026-07-15', $result['EventStartDate'] );
		$this->assertSame( '11', $result['EventStartHour'] );
		$this->assertSame( '00', $result['EventStartMinute'] );
		$this->assertSame( '12', $result['EventEndHour'] );
		$this->assertSame( '30', $result['EventEndMinute'] );
		$this->assertArrayNotHasKey( 'EventTimezone', $result );

		$neutral = $pco->to_neutral_calendar_event( $instance, $context );
		$this->assertSame( '2026-07-15 18:00:00', $neutral['start_utc'] );
		$this->assertSame( 'America/Los_Angeles', $neutral['site_timezone'] );
	}

	public function test_site_timezone_dst_and_midnight_crossing() {
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'America/Los_Angeles' ) );

		list( $instance, $context ) = $this->calendarFixture();
		$context['organization_timezone'] = 'America/Chicago';

		$winter = $context;
		$winter['relational_data']['EventTime']['t1']['attributes'] = [
			'starts_at' => '2026-01-15T18:00:00Z',
			'ends_at'   => '2026-01-15T19:00:00Z',
		];
		$winter_result = $this->makePco()->format_event_from_calendar( $instance, $winter );
		// 10:00 PST, not 12:00 CST.
		$this->assertSame( '10', $winter_result['EventStartHour'] );
		$this->assertSame( '2026-01-15', $winter_result['EventStartDate'] );

		$summer = $context;
		$summer['relational_data']['EventTime']['t1']['attributes'] = [
			'starts_at' => '2026-07-15T18:00:00Z',
			'ends_at'   => '2026-07-15T19:00:00Z',
		];
		$summer_result = $this->makePco()->format_event_from_calendar( $instance, $summer );
		// 11:00 PDT, not 13:00 CDT.
		$this->assertSame( '11', $summer_result['EventStartHour'] );

		$late = $context;
		$late['relational_data']['EventTime']['t1']['attributes'] = [
			'starts_at' => '2026-01-15T05:30:00Z',
			'ends_at'   => '2026-01-15T06:30:00Z',
		];
		$late_result = $this->makePco()->format_event_from_calendar( $instance, $late );
		$this->assertSame( '2026-01-14', $late_result['EventStartDate'] );
		$this->assertSame( '21', $late_result['EventStartHour'] );
		$this->assertSame( '30', $late_result['EventStartMinute'] );
		$this->assertSame( '2026-01-14', $late_result['EventEndDate'] );
		$this->assertSame( '22', $late_result['EventEndHour'] );
	}

	public function test_registrations_tec_fields_match_for_the_existing_signup_shape() {
		$signup = [
			'id'         => '77',
			'attributes' => [
				'name'                 => 'Summer Camp',
				'description'          => 'A week of fun.',
				'logo_url'             => 'https://example.com/logo.png',
				'new_registration_url' => 'https://church.center/signups/77',
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
				'SignupTime'     => [
					'st1' => [
						'attributes' => [
							'starts_at' => '2026-08-01T14:00:00Z',
							'ends_at'   => '2026-08-05T18:30:00Z',
							'all_day'   => false,
						],
					],
				],
				'Category'       => [
					'c1' => [ 'attributes' => [ 'name' => 'Youth Events' ] ],
				],
				'SignupLocation' => [
					'loc1' => [
						'attributes' => [
							'name'              => 'Main Campus',
							'formatted_address' => "123 Main St\nSpringfield, IL 62704",
						],
					],
				],
			],
		];

		$result = $this->makePco()->format_event_from_registrations( $signup, $context );

		$this->assertSame(
			[
				'chms_id'          => 'reg_77',
				'post_status'      => 'publish',
				'post_title'       => 'Summer Camp',
				'post_content'     => 'A week of fun.',
				'tax_input'        => [],
				'event_category'   => [ 'youth-events' => 'Youth Events' ],
				'thumbnail_url'    => 'https://example.com/logo.png',
				'meta_input'       => [
					'registration_url'      => 'https://church.center/signups/77',
					'registration_sold_out' => true,
				],
				'EventStartDate'   => '2026-08-01',
				'EventStartHour'   => '14',
				'EventStartMinute' => '00',
				'EventEndDate'     => '2026-08-05',
				'EventEndHour'     => '18',
				'EventEndMinute'   => '30',
				'EventVenue'       => [
					'venue'   => 'Main Campus',
					'address' => '123 Main St',
					'city'    => 'Springfield',
					'state'   => 'IL',
					'zip'     => '62704',
				],
			],
			$result
		);
		$this->assertArrayNotHasKey( 'EventURL', $result );
		$this->assertArrayNotHasKey( 'EventAllDay', $result );
	}

	public function test_ccb_tec_fields_match_when_the_event_names_its_timezone() {
		$event = [
			'event_id'       => '586',
			'name'           => 'Pizza Night',
			'description'    => 'Eating pizza.',
			'start_datetime' => '2026-07-15 14:00:00',
			'end_datetime'   => '2026-07-15 16:30:00',
			'timezone'       => 'America/Denver',
			'event_type'     => 'Open to All',
			'group'          => 'Youth',
			'organizer'      => 'Larry',
			'image'          => 'https://example.com/pizza.png',
			'location_name'  => 'Fellowship Hall',
			'_chms_modified' => '2026-07-01 12:00:00',
		];

		$ccb     = $this->makeCcb();
		$result  = $ccb->format_event( $event, [] );
		$neutral = $ccb->to_neutral_event( $event, [] );

		$this->assertSame(
			[
				'chms_id'        => '586',
				'post_status'    => 'publish',
				'post_title'     => 'Pizza Night',
				'post_content'   => 'Eating pizza.',
				'thumbnail_url'  => 'https://example.com/pizza.png',
				'EventStartDate' => '2026-07-15 14:00:00',
				'EventEndDate'   => '2026-07-15 16:30:00',
				'EventAllDay'    => false,
				'EventTimezone'  => 'America/Denver',
				'EventURL'       => 'https://demo.ccbchurch.com/event_detail.php?event_id=586',
				'tax_input'      => [],
				'event_category' => [],
				'meta_input'     => [
					'event_type'      => 'Open to All',
					'group_name'      => 'Youth',
					'organizer_name'  => 'Larry',
					'approval_status' => '',
					'_chms_modified'  => '2026-07-01 12:00:00',
				],
			],
			$result
		);

		// July in Denver is MDT (UTC-6). The TEC clock stays 14:00 in that zone.
		$this->assertSame( 'America/Denver', $neutral['timezone'] );
		$this->assertSame( '2026-07-15 20:00:00', $neutral['start_utc'] );
		$this->assertSame( '2026-07-15 22:30:00', $neutral['end_utc'] );
		$this->assertSame( 'Fellowship Hall', $neutral['location_name'] );
	}

	public function test_ccb_all_day_span_and_winter_offset() {
		$event = [
			'event_id'       => '9',
			'name'           => 'Retreat',
			'start_datetime' => '2026-01-15 00:00:00',
			'end_datetime'   => '2026-01-16 23:59:00',
			'timezone'       => 'America/Denver',
		];

		$result  = $this->makeCcb()->format_event( $event, [] );
		$neutral = $this->makeCcb()->to_neutral_event( $event, [] );

		$this->assertTrue( $result['EventAllDay'] );
		$this->assertSame( 'America/Denver', $result['EventTimezone'] );
		$this->assertSame( '2026-01-15 00:00:00', $result['EventStartDate'] );
		$this->assertSame( '2026-01-16 23:59:00', $result['EventEndDate'] );
		// January in Denver is MST (UTC-7).
		$this->assertSame( '2026-01-15 07:00:00', $neutral['start_utc'] );
	}

	public function test_ccb_timezone_valid_invalid_and_missing() {
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'America/Los_Angeles' ) );
		date_default_timezone_set( 'UTC' );

		$base = [
			'event_id'       => '12',
			'name'           => 'Prayer',
			'start_datetime' => '2026-08-05 14:00:00',
			'end_datetime'   => '2026-08-05 15:00:00',
		];
		$ccb = $this->makeCcb();

		$valid = $ccb->format_event( array_merge( $base, [ 'timezone' => 'America/Denver' ] ), [] );
		$valid_neutral = $ccb->to_neutral_event( array_merge( $base, [ 'timezone' => 'America/Denver' ] ), [] );
		$this->assertSame( '2026-08-05 14:00:00', $valid['EventStartDate'] );
		$this->assertSame( 'America/Denver', $valid['EventTimezone'] );
		// 14:00 MDT is 20:00 UTC.
		$this->assertSame( '2026-08-05 20:00:00', $valid_neutral['start_utc'] );

		$missing = $ccb->format_event( $base, [] );
		$missing_neutral = $ccb->to_neutral_event( $base, [] );
		$this->assertSame( '2026-08-05 14:00:00', $missing['EventStartDate'] );
		$this->assertSame( 'America/Los_Angeles', $missing['EventTimezone'] );
		// 14:00 PDT is 21:00 UTC, not 14:00 UTC from PHP's default zone.
		$this->assertSame( '2026-08-05 21:00:00', $missing_neutral['start_utc'] );

		$invalid = $ccb->format_event( array_merge( $base, [ 'timezone' => 'Not/AZone' ] ), [] );
		$invalid_neutral = $ccb->to_neutral_event( array_merge( $base, [ 'timezone' => 'Not/AZone' ] ), [] );
		$this->assertSame( '2026-08-05 14:00:00', $invalid['EventStartDate'] );
		$this->assertSame( 'America/Los_Angeles', $invalid['EventTimezone'] );
		$this->assertSame( '2026-08-05 21:00:00', $invalid_neutral['start_utc'] );
		$this->assertSame( $missing_neutral['end_utc'], $invalid_neutral['end_utc'] );
	}

	public function test_ccb_without_any_timezone_keeps_the_wordpress_timezone() {
		$event = [
			'name'           => 'Untitled',
			'start_datetime' => '2026-08-05 14:00:00',
			'end_datetime'   => '2026-08-05 15:00:00',
		];

		$result = $this->makeCcb()->format_event( $event, [] );

		$this->assertSame( '2026-08-05 14:00:00', $result['EventStartDate'] );
		$this->assertSame( 'UTC', $result['EventTimezone'] );
		$this->assertSame(
			md5( 'Untitled' . ( new \DateTime( '2026-08-05 14:00:00' ) )->getTimestamp() ),
			$result['chms_id']
		);
	}
}
