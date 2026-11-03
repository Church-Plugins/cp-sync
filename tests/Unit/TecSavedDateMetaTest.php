<?php
/**
 * Date meta The Events Calendar stores on create and on a later update.
 *
 * Organization timezone America/Chicago, site timezone America/Los_Angeles.
 * Planning Center's saved local clock is the site zone. Church Community
 * Builder keeps its wall clock and its event or campus zone.
 *
 * saved_date_meta() is this plugin's model of what TEC stores. It is not
 * The Events Calendar. The unit environment does not load TEC.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\CCB;
use CP_Sync\ChMS\PCO;
use CP_Sync\Integrations\TEC;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Integrations\TEC::saved_date_meta
 * @covers \CP_Sync\Integrations\TEC::persist_event_date_meta
 * @covers \CP_Sync\Integrations\TEC::legacy_update_args
 */
class TecSavedDateMetaTest extends TestCase {

	/** @var array */
	private $meta = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->meta = array(
			'_EventTimezone' => 'America/Los_Angeles',
		);

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'America/Los_Angeles' ) );
		Functions\when( 'sanitize_title' )->alias(
			static fn( $title ) => strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( (string) $title ) ) )
		);
		Functions\when( 'wp_list_pluck' )->alias(
			static function ( $list, $field ) {
				return array_map(
					static fn( $row ) => is_array( $row ) ? ( $row[ $field ] ?? null ) : null,
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
			static function ( $key, $default = array() ) {
				if ( 'cp_sync_ccb_settings' === $key ) {
					return array( 'connect' => array( 'subdomain' => 'demo' ) );
				}
				return $default;
			}
		);

		$store = &$this->meta;
		Functions\when( 'update_post_meta' )->alias(
			static function ( $post_id, $key, $value ) use ( &$store ) {
				$store[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_post_meta' )->alias(
			static function ( $post_id, $key ) use ( &$store ) {
				unset( $store[ $key ] );
				return true;
			}
		);
	}

	protected function tearDown(): void {
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
	 * Meta from the legacy update args.
	 *
	 * saved_date_meta() is this plugin's model of TEC storage, not TEC itself.
	 *
	 * @param array  $item             Formatted event.
	 * @param string $zone             Zone of the local clock.
	 * @param bool   $expect_timezone  Whether the update args carry EventTimezone.
	 * @return array
	 */
	private function meta_after_update( array $item, $zone, $expect_timezone = false ) {
		$args = TEC::legacy_update_args( $item );

		if ( $expect_timezone ) {
			$this->assertSame( $item['EventTimezone'], $args['EventTimezone'] );
		} else {
			$this->assertArrayNotHasKey( 'EventTimezone', $args );
		}

		return TEC::saved_date_meta( $args, $zone );
	}

	private function calendarItem( $start_utc, $end_utc, $all_day = false ) {
		$instance = array(
			'id'         => 'inst-1',
			'attributes' => array( 'all_day_event' => $all_day ),
			'relationships' => array(
				'event'       => array( 'data' => array( 'id' => 'e1' ) ),
				'event_times' => array( 'data' => array( array( 'id' => 't1' ) ) ),
				'tags'        => array( 'data' => array() ),
			),
		);
		$context = array(
			'organization_timezone' => 'America/Chicago',
			'relational_data'       => array(
				'Event'     => array(
					'e1' => array(
						'attributes' => array(
							'name'                     => 'Service',
							'visible_in_church_center' => true,
							'approval_status'          => 'A',
						),
					),
				),
				'EventTime' => array(
					't1' => array(
						'attributes' => array(
							'starts_at' => $start_utc,
							'ends_at'   => $end_utc,
						),
					),
				),
			),
		);

		return $this->makePco()->format_event_from_calendar( $instance, $context );
	}

	public function test_create_and_update_save_the_same_site_local_times() {
		$item = $this->calendarItem( '2026-07-15T18:00:00Z', '2026-07-15T19:30:00Z' );

		// Master wrote Los Angeles local time and did not set the organization zone.
		$this->assertSame( '2026-07-15', $item['EventStartDate'] );
		$this->assertSame( '11', $item['EventStartHour'] );
		$this->assertSame( '00', $item['EventStartMinute'] );
		$this->assertSame( '12', $item['EventEndHour'] );
		$this->assertSame( '30', $item['EventEndMinute'] );
		$this->assertArrayNotHasKey( 'EventTimezone', $item );

		$created = TEC::saved_date_meta( $item, 'America/Los_Angeles' );
		$updated = $this->meta_after_update( $item, 'America/Los_Angeles' );

		$this->assertSame( $created, $updated );
		$this->assertSame( '2026-07-15 11:00:00', $created['_EventStartDate'] );
		$this->assertSame( '2026-07-15 12:30:00', $created['_EventEndDate'] );
		$this->assertSame( '2026-07-15 18:00:00', $created['_EventStartDateUTC'] );
		$this->assertSame( '2026-07-15 19:30:00', $created['_EventEndDateUTC'] );
		$this->assertSame( 'no', $created['_EventAllDay'] );

		TEC::persist_event_date_meta( 42, $item );
		$after_create = $this->meta;
		TEC::persist_event_date_meta( 42, $item );

		$this->assertSame( $after_create, $this->meta );
		$this->assertSame( 'America/Los_Angeles', $this->meta['_EventTimezone'] );
		$this->assertSame( '2026-07-15 11:00:00', $this->meta['_EventStartDate'] );
		$this->assertSame( '2026-07-15 18:00:00', $this->meta['_EventStartDateUTC'] );
	}

	public function test_all_day_create_and_update_store_the_same_bounds() {
		// Midnight through 23:59 in Los Angeles (PDT, UTC-7).
		$item = $this->calendarItem( '2026-07-15T07:00:00Z', '2026-07-16T06:59:00Z', true );

		$this->assertTrue( $item['EventAllDay'] );
		$this->assertSame( '2026-07-15', $item['EventStartDate'] );
		$this->assertSame( '0', $item['EventStartHour'] );
		$this->assertSame( '2026-07-15', $item['EventEndDate'] );
		$this->assertSame( '23', $item['EventEndHour'] );
		$this->assertSame( '59', $item['EventEndMinute'] );
		$this->assertArrayNotHasKey( 'EventTimezone', $item );

		$created = TEC::saved_date_meta( $item, 'America/Los_Angeles' );
		$updated = $this->meta_after_update( $item, 'America/Los_Angeles' );

		$this->assertSame( $created, $updated );
		$this->assertSame( '2026-07-15 00:00:00', $created['_EventStartDate'] );
		$this->assertSame( '2026-07-15 23:59:59', $created['_EventEndDate'] );
		$this->assertSame( '2026-07-15 07:00:00', $created['_EventStartDateUTC'] );
		$this->assertSame( '2026-07-16 06:59:59', $created['_EventEndDateUTC'] );
		$this->assertSame( 'yes', $created['_EventAllDay'] );

		TEC::persist_event_date_meta( 7, $item );
		$after_create = $this->meta;
		TEC::persist_event_date_meta( 7, $item );
		$this->assertSame( $after_create, $this->meta );
		$this->assertSame( 'America/Los_Angeles', $this->meta['_EventTimezone'] );
	}

	public function test_dst_boundary_uses_the_site_offset_not_chicago() {
		// Between the Chicago and Los Angeles fallbacks on 2026-11-01.
		// 08:30Z is 02:30 CST but 01:30 PDT. 10:30Z is 02:30 PST.
		$item = $this->calendarItem( '2026-11-01T08:30:00Z', '2026-11-01T10:30:00Z' );

		$this->assertSame( '2026-11-01', $item['EventStartDate'] );
		$this->assertSame( '1', $item['EventStartHour'] );
		$this->assertSame( '30', $item['EventStartMinute'] );
		$this->assertSame( '2', $item['EventEndHour'] );
		$this->assertSame( '30', $item['EventEndMinute'] );

		$created = TEC::saved_date_meta( $item, 'America/Los_Angeles' );
		$updated = $this->meta_after_update( $item, 'America/Los_Angeles' );

		$this->assertSame( $created, $updated );
		$this->assertSame( '2026-11-01 01:30:00', $created['_EventStartDate'] );
		$this->assertSame( '2026-11-01 02:30:00', $created['_EventEndDate'] );
		$this->assertSame( '2026-11-01 08:30:00', $created['_EventStartDateUTC'] );
		$this->assertSame( '2026-11-01 10:30:00', $created['_EventEndDateUTC'] );
		$this->assertNotSame( '2026-11-01 02:30:00', $created['_EventStartDate'] );
	}

	public function test_ccb_create_and_update_keep_the_ccb_wall_clock_and_zone() {
		$event = array(
			'event_id'       => '44',
			'name'           => 'Prayer',
			'start_datetime' => '2026-08-05 14:00:00',
			'end_datetime'   => '2026-08-05 15:00:00',
			'timezone'       => 'America/Chicago',
		);

		$item = $this->makeCcb()->format_event( $event, array() );

		// Master's CCB output: the clock CCB sent, labeled with that zone.
		$this->assertSame( '2026-08-05 14:00:00', $item['EventStartDate'] );
		$this->assertSame( '2026-08-05 15:00:00', $item['EventEndDate'] );
		$this->assertSame( 'America/Chicago', $item['EventTimezone'] );

		$created = TEC::saved_date_meta( $item, 'America/Chicago' );
		$updated = $this->meta_after_update( $item, 'America/Chicago', true );

		$this->assertSame( $created, $updated );
		$this->assertSame( '2026-08-05 14:00:00', $created['_EventStartDate'] );
		$this->assertSame( '2026-08-05 15:00:00', $created['_EventEndDate'] );
		// August in Chicago is CDT (UTC-5).
		$this->assertSame( '2026-08-05 19:00:00', $created['_EventStartDateUTC'] );
		$this->assertSame( '2026-08-05 20:00:00', $created['_EventEndDateUTC'] );

		TEC::persist_event_date_meta( 44, $item );
		$after_create = $this->meta;
		TEC::persist_event_date_meta( 44, $item );

		$this->assertSame( $after_create, $this->meta );
		$this->assertSame( 'America/Chicago', $this->meta['_EventTimezone'] );
		$this->assertSame( '2026-08-05 14:00:00', $this->meta['_EventStartDate'] );
		$this->assertSame( '2026-08-05 19:00:00', $this->meta['_EventStartDateUTC'] );
	}

	public function test_timed_event_does_not_write_all_day_no() {
		$item    = $this->calendarItem( '2026-07-15T18:00:00Z', '2026-07-15T19:00:00Z' );
		$deleted = array();
		$written = array();
		$store   = &$this->meta;

		Functions\when( 'delete_post_meta' )->alias(
			static function ( $post_id, $key ) use ( &$deleted, &$store ) {
				$deleted[] = $key;
				unset( $store[ $key ] );
				return true;
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			static function ( $post_id, $key, $value ) use ( &$written, &$store ) {
				$written[]    = $key;
				$store[ $key ] = $value;
				return true;
			}
		);

		$this->meta['_EventAllDay'] = 'no';

		$created = TEC::persist_event_date_meta( 9, $item );
		$updated = TEC::persist_event_date_meta( 9, $item );

		$this->assertArrayNotHasKey( '_EventAllDay', $created );
		$this->assertArrayNotHasKey( '_EventAllDay', $updated );
		$this->assertSame( 'no', $this->meta['_EventAllDay'] );
		$this->assertNotContains( '_EventAllDay', $written );
		$this->assertNotContains( '_EventAllDay', $deleted );
	}

	public function test_update_does_not_replace_an_existing_timezone() {
		$item = $this->calendarItem( '2026-07-15T18:00:00Z', '2026-07-15T19:00:00Z' );
		$this->meta['_EventTimezone'] = 'America/Los_Angeles';

		TEC::persist_event_date_meta( 3, $item );

		$this->assertSame( 'America/Los_Angeles', $this->meta['_EventTimezone'] );
		$this->assertSame( '2026-07-15 11:00:00', $this->meta['_EventStartDate'] );
	}
}
