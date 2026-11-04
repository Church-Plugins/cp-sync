<?php
/**
 * Upsert, removal, and series grouping for CP Sync events.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\CCB;
use CP_Sync\ChMS\PCO;
use CP_Sync\Events\EventStore;
use CP_Sync\Events\EventSync;
use CP_Sync\Events\TecEventMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Events\EventSync
 */
class EventSyncTest extends TestCase {

	/** @var string */
	private $original_timezone;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->original_timezone = date_default_timezone_get();
		date_default_timezone_set( 'UTC' );
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'UTC' ) );
		Functions\when( 'wp_list_pluck' )->alias(
			static function ( $list, $field ) {
				$list = is_array( $list ) ? $list : array();
				return array_map(
					static function ( $row ) use ( $field ) {
						return is_array( $row ) ? ( $row[ $field ] ?? null ) : null;
					},
					$list
				);
			}
		);
		Functions\when( 'get_option' )->justReturn( array() );
	}

	protected function tearDown(): void {
		date_default_timezone_set( $this->original_timezone );
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_upsert_is_idempotent_per_date() {
		$store = new MemoryEventStore();
		$feed  = array(
			$this->pco( 'inst-1', 'e1', '2026-08-01 15:30:00', '2026-08-01 17:00:00' ),
			$this->pco( 'inst-2', 'e1', '2026-08-08 15:30:00', '2026-08-08 17:00:00' ),
		);

		EventSync::sync( $feed, $store, '2026-07-01 00:00:00', 'UTC' );
		$first = $this->ids( $store->events() );

		EventSync::sync( $feed, $store, '2026-07-01 00:00:00', 'UTC' );
		$second = $this->ids( $store->events() );

		$this->assertCount( 2, $first );
		$this->assertSame( $first, $second );
		$this->assertCount( 2, $this->published( $store->events() ) );
	}

	public function test_dropped_future_dates_are_trashed_and_past_dates_stay() {
		$store = new MemoryEventStore();
		$now   = '2026-08-01 12:00:00';
		$feed  = array(
			$this->pco( 'future', 'e1', '2026-09-01 15:00:00', '2026-09-01 16:00:00' ),
			$this->pco( 'past', 'e2', '2026-01-01 15:00:00', '2026-01-01 16:00:00' ),
			$this->pco( 'keep', 'e3', '2026-08-15 15:00:00', '2026-08-15 16:00:00' ),
		);

		EventSync::sync( $feed, $store, $now, 'UTC' );
		EventSync::sync(
			array( $this->pco( 'keep', 'e3', '2026-08-15 15:00:00', '2026-08-15 16:00:00' ) ),
			$store,
			$now,
			'UTC'
		);

		$by_source = array();
		foreach ( $store->events() as $event ) {
			$by_source[ $event['source_id'] ] = $event['status'];
		}

		$this->assertSame( 'trash', $by_source['future'] );
		$this->assertSame( 'publish', $by_source['past'] );
		$this->assertSame( 'publish', $by_source['keep'] );
	}

	public function test_empty_end_utc_uses_the_start_time() {
		$event = $this->pco( 'open-end', 'e1', '2026-08-01 15:00:00', '' );
		$event['site_timezone'] = 'UTC';

		$item = TecEventMapper::to_tec_item( $event );

		$this->assertSame( '2026-08-01', $item['EventStartDate'] );
		$this->assertSame( '2026-08-01', $item['EventEndDate'] );
		$this->assertSame( $item['EventStartHour'], $item['EventEndHour'] );
		$this->assertSame( $item['EventStartMinute'], $item['EventEndMinute'] );
		$this->assertSame( '15', $item['EventEndHour'] );
		$this->assertSame( '00', $item['EventEndMinute'] );
	}

	public function test_an_event_with_no_end_time_does_not_stop_the_feed() {
		$open = $this->pco( 'open-end', 'e1', '2026-08-01 15:00:00', '' );
		$next = $this->pco( 'after', 'e2', '2026-08-02 15:00:00', '2026-08-02 16:00:00', 'After' );
		$open['site_timezone'] = 'UTC';
		$next['site_timezone'] = 'UTC';

		$mapped = array();

		foreach ( array( $open, $next ) as $event ) {
			$mapped[] = TecEventMapper::to_tec_item( $event );
		}

		$this->assertCount( 2, $mapped );
		$this->assertSame( 'Sunday Service', $mapped[0]['post_title'] );
		$this->assertSame( '2026-08-01', $mapped[0]['EventEndDate'] );
		$this->assertSame( '15', $mapped[0]['EventEndHour'] );
		$this->assertSame( '00', $mapped[0]['EventEndMinute'] );
		$this->assertSame( 'After', $mapped[1]['post_title'] );
		$this->assertSame( '2026-08-02', $mapped[1]['EventStartDate'] );
		$this->assertSame( '16', $mapped[1]['EventEndHour'] );

		$store  = new MemoryEventStore();
		$result = EventSync::sync( array( $open, $next ), $store, '2026-07-01 00:00:00', 'UTC' );

		$this->assertFalse( $result['skipped'] );
		$this->assertSame( 2, $result['events'] );
		$this->assertCount( 2, $this->published( $store->events() ) );
	}

	public function test_an_empty_feed_does_not_remove_stored_events() {
		$store = new MemoryEventStore();
		EventSync::sync(
			array( $this->pco( 'inst-1', 'e1', '2026-09-01 15:00:00', '2026-09-01 16:00:00' ) ),
			$store,
			'2026-08-01 00:00:00',
			'UTC'
		);

		$result = EventSync::sync( array(), $store, '2026-08-01 00:00:00', 'UTC' );

		$this->assertTrue( $result['skipped'] );
		$this->assertSame( 'publish', $store->events()[0]['status'] );
	}

	public function test_pco_event_with_multiple_instances_is_one_series() {
		$store = new MemoryEventStore();
		EventSync::sync(
			array(
				$this->pco( 'inst-1', 'event-9', '2026-08-01 15:30:00', '2026-08-01 17:00:00', 'Sunday Service' ),
				$this->pco( 'inst-2', 'event-9', '2026-08-08 15:30:00', '2026-08-08 17:00:00', 'Sunday Service' ),
			),
			$store,
			'2026-07-01 00:00:00',
			'UTC'
		);

		$series = $this->published( $store->series() );

		$this->assertCount( 1, $series );
		$this->assertSame( 'pco|event-9', $series[0]['series_key'] );
		$this->assertSame( 'Sunday Service', $series[0]['title'] );

		foreach ( $this->published( $store->events() ) as $event ) {
			$this->assertSame( $series[0]['post_id'], $event['series_post_id'] );
		}
	}

	public function test_ccb_event_id_with_multiple_occurrences_is_one_series() {
		$store = new MemoryEventStore();
		EventSync::sync(
			array(
				$this->ccb( '586', '2026-07-15 20:00:00', '2026-07-15 22:00:00' ),
				$this->ccb( '586', '2026-07-22 20:00:00', '2026-07-22 22:00:00' ),
			),
			$store,
			'2026-07-01 00:00:00',
			'UTC'
		);

		$events = $this->published( $store->events() );
		$series = $this->published( $store->series() );

		$this->assertCount( 2, $events );
		$this->assertCount( 1, $series );
		$this->assertSame( 'ccb|586', $series[0]['series_key'] );
		$this->assertNotSame( $events[0]['occurrence_id'], $events[1]['occurrence_id'] );
		$this->assertSame( '586|2026-07-15 20:00:00', $events[0]['occurrence_id'] );
	}

	public function test_a_series_that_shrinks_to_one_date_is_kept() {
		$store  = new MemoryEventStore();
		$now    = '2026-07-01 00:00:00';
		$first  = $this->pco( 'inst-1', 'event-9', '2026-08-01 15:30:00', '2026-08-01 17:00:00' );
		$second = $this->pco( 'inst-2', 'event-9', '2026-08-08 15:30:00', '2026-08-08 17:00:00' );

		EventSync::sync( array( $first, $second ), $store, $now, 'UTC' );
		$this->assertCount( 1, $this->published( $store->series() ) );

		$result = EventSync::sync( array( $first ), $store, $now, 'UTC' );

		$this->assertSame( 0, $result['trashed_series'] );
		$this->assertCount( 1, $this->published( $store->series() ) );
		$this->assertSame( 'publish', $store->series()[0]['status'] );
		$this->assertSame( 'pco|event-9', $store->series()[0]['series_key'] );
		$this->assertSame( 0, $this->published( $store->events() )[0]['series_post_id'] );
	}

	public function test_a_series_with_no_dates_left_in_the_feed_is_trashed() {
		$store = new MemoryEventStore();
		$now   = '2026-07-01 00:00:00';

		EventSync::sync(
			array(
				$this->pco( 'inst-1', 'event-9', '2026-08-01 15:30:00', '2026-08-01 17:00:00' ),
				$this->pco( 'inst-2', 'event-9', '2026-08-08 15:30:00', '2026-08-08 17:00:00' ),
			),
			$store,
			$now,
			'UTC'
		);

		EventSync::sync(
			array( $this->pco( 'other', 'event-other', '2026-09-01 15:30:00', '2026-09-01 17:00:00' ) ),
			$store,
			$now,
			'UTC'
		);

		$by_key = array();
		foreach ( $store->series() as $series ) {
			$by_key[ $series['series_key'] ] = $series['status'];
		}

		$this->assertSame( 'trash', $by_key['pco|event-9'] );
	}

	public function test_a_single_date_creates_no_series() {
		$store = new MemoryEventStore();

		EventSync::sync(
			array( $this->pco( 'inst-1', 'event-9', '2026-08-01 15:30:00', '2026-08-01 17:00:00' ) ),
			$store,
			'2026-07-01 00:00:00',
			'UTC'
		);
		EventSync::sync(
			array( $this->ccb( '586', '2026-07-15 20:00:00', '2026-07-15 22:00:00' ) ),
			$store,
			'2026-07-01 00:00:00',
			'UTC'
		);

		$this->assertCount( 0, $this->published( $store->series() ) );

		foreach ( $this->published( $store->events() ) as $event ) {
			$this->assertSame( 0, $event['series_post_id'] );
		}
	}

	public function test_a_registration_has_no_series() {
		$store = new MemoryEventStore();
		EventSync::sync(
			array(
				array(
					'provider'         => 'pco',
					'chms_id'          => 'reg_44',
					'title'            => 'Signup',
					'start_utc'        => '2026-08-01 15:00:00',
					'end_utc'          => '2026-08-01 16:00:00',
					'registration_url' => 'https://example.com/signup',
				),
			),
			$store,
			'2026-07-01 00:00:00',
			'UTC'
		);

		$this->assertCount( 0, $store->series() );
		$this->assertSame( 'reg_44', $store->events()[0]['occurrence_id'] );
		$this->assertSame( 'https://example.com/signup', $store->events()[0]['registration_url'] );
	}

	public function test_ccb_local_time_uses_the_event_timezone() {
		$event               = $this->ccb( '586', '2026-08-02 00:00:00', '2026-08-02 01:00:00' );
		$event['timezone']   = 'America/Chicago';
		$row                 = EventSync::normalize( $event, 'America/New_York' );

		$this->assertNotNull( $row );
		$this->assertSame( 'America/Chicago', $row['timezone'] );
		$this->assertSame( '2026-08-01 19:00:00', $row['start_local'] );
		$this->assertSame( '2026-08-01 20:00:00', $row['end_local'] );
	}

	public function test_registration_kind_does_not_depend_on_the_id_prefix() {
		$item = TecEventMapper::to_tec_item(
			array(
				'provider'      => 'pco',
				'kind'          => 'registration',
				'chms_id'       => '99',
				'title'         => 'Signup',
				'start_utc'     => '2026-08-01 15:00:00',
				'end_utc'       => '2026-08-01 16:00:00',
				'site_timezone' => 'UTC',
			)
		);

		$this->assertArrayNotHasKey( 'EventURL', $item );
		$this->assertSame( '15', $item['EventStartHour'] );
		$this->assertArrayNotHasKey( 'post_excerpt', $item );
	}

	public function test_local_times_are_stored_in_the_wordpress_timezone() {
		$store = new MemoryEventStore();
		EventSync::sync(
			array( $this->pco( 'inst-1', 'e1', '2026-08-01 15:30:00', '2026-08-01 17:00:00' ) ),
			$store,
			'2026-07-01 00:00:00',
			'America/New_York'
		);

		$event = $store->events()[0];

		$this->assertSame( '2026-08-01 15:30:00', $event['start_utc'] );
		$this->assertSame( '2026-08-01 17:00:00', $event['end_utc'] );
		$this->assertSame( '2026-08-01 11:30:00', $event['start_local'] );
		$this->assertSame( '2026-08-01 13:00:00', $event['end_local'] );
		$this->assertSame( 'America/New_York', $event['timezone'] );
	}

	public function test_neutral_records_carry_the_series_id_the_grouper_uses() {
		$pco = ( new \ReflectionClass( PCO::class ) )->newInstanceWithoutConstructor();
		$neutral = $pco->to_neutral_calendar_event(
			array(
				'id'            => 'inst-1',
				'attributes'    => array( 'all_day_event' => false ),
				'relationships' => array(
					'event'       => array( 'data' => array( 'id' => 'e1' ) ),
					'event_times' => array( 'data' => array( array( 'id' => 't1' ) ) ),
					'tags'        => array( 'data' => array( array( 'id' => 'tag-9' ) ) ),
				),
			),
			array(
				'relational_data' => array(
					'Event'     => array(
						'e1' => array(
							'attributes' => array(
								'name' => 'Sunday Service',
							),
						),
					),
					'EventTime' => array(
						't1' => array(
							'attributes' => array(
								'starts_at' => '2026-08-01T15:30:00Z',
								'ends_at'   => '2026-08-01T17:00:00Z',
							),
						),
					),
					'Tag'       => array(
						'tag-9' => array(
							'id'         => 'tag-9',
							'attributes' => array(
								'taxonomy' => 'cps_topic',
								'name'     => 'Worship',
							),
						),
					),
				),
			)
		);

		$this->assertSame( 'e1', $neutral['series_id'] );
		$this->assertSame( 'inst-1', $neutral['chms_id'] );
		$this->assertSame( array( 'Worship' ), $neutral['categories'] );

		$ccb = ( new \ReflectionClass( CCB::class ) )->newInstanceWithoutConstructor();
		$ccb_neutral = $ccb->to_neutral_event(
			array(
				'event_id'       => '586',
				'name'           => 'Pizza Night',
				'start_datetime' => '2026-07-15 14:00:00',
				'end_datetime'   => '2026-07-15 16:00:00',
				'event_type'     => 'Fellowship',
			),
			array()
		);

		$this->assertSame( '586', $ccb_neutral['series_id'] );
		$this->assertSame( array( 'Fellowship' ), EventSync::category_names( $ccb_neutral ) );
	}

	/**
	 * @param string $instance_id Instance id.
	 * @param string $series_id   Planning Center Event id.
	 * @param string $start       UTC start.
	 * @param string $end         UTC end.
	 * @param string $title       Title.
	 * @return array
	 */
	private function pco( $instance_id, $series_id, $start, $end, $title = 'Sunday Service' ) {
		return array(
			'provider'         => 'pco',
			'chms_id'          => $instance_id,
			'series_id'        => $series_id,
			'title'            => $title,
			'content'          => '<p>Hello</p>',
			'start_utc'        => $start,
			'end_utc'          => $end,
			'registration_url' => 'https://example.com/reg',
			'location_name'    => 'Church',
			'categories'       => array( 'Worship' ),
		);
	}

	/**
	 * @param string $event_id CCB event id.
	 * @param string $start    UTC start.
	 * @param string $end      UTC end.
	 * @return array
	 */
	private function ccb( $event_id, $start, $end ) {
		return array(
			'provider'      => 'ccb',
			'chms_id'       => $event_id,
			'series_id'     => $event_id,
			'title'         => 'Pizza Night',
			'content'       => 'Eating pizza.',
			'start_utc'     => $start,
			'end_utc'       => $end,
			'event_url'     => 'https://demo.ccbchurch.com/event_detail.php?event_id=' . $event_id,
			'location_name' => 'Fellowship Hall',
			'meta_input'    => array( 'event_type' => 'Fellowship' ),
		);
	}

	/**
	 * @param array $rows Stored rows.
	 * @return int[]
	 */
	private function ids( array $rows ) {
		return array_map(
			static function ( $row ) {
				return $row['post_id'];
			},
			$rows
		);
	}

	/**
	 * @param array $rows Stored rows.
	 * @return array
	 */
	private function published( array $rows ) {
		return array_values(
			array_filter(
				$rows,
				static function ( $row ) {
					return 'trash' !== ( $row['status'] ?? '' );
				}
			)
		);
	}
}

/**
 * In-memory stand-in for the WordPress event store.
 */
class MemoryEventStore implements EventStore {

	/** @var array */
	public $events = array();

	/** @var array */
	public $series = array();

	/** @var int */
	private $next = 1;

	public function events() {
		return array_values( $this->events );
	}

	public function series() {
		return array_values( $this->series );
	}

	public function save_event( array $record ) {
		$id = ! empty( $record['post_id'] ) ? (int) $record['post_id'] : $this->next++;
		$record['post_id'] = $id;
		$record['status']  = 'publish';
		$record['locked']  = ! empty( $record['locked'] );
		$this->events[ $id ] = $record;

		return $id;
	}

	public function save_series( array $record ) {
		$id = ! empty( $record['post_id'] ) ? (int) $record['post_id'] : $this->next++;
		$record['post_id'] = $id;
		$record['status']  = 'publish';
		$record['locked']  = ! empty( $record['locked'] );
		$this->series[ $id ] = $record;

		return $id;
	}

	public function trash_event( $post_id ) {
		if ( isset( $this->events[ (int) $post_id ] ) ) {
			$this->events[ (int) $post_id ]['status'] = 'trash';
		}
	}

	public function trash_series( $post_id ) {
		if ( isset( $this->series[ (int) $post_id ] ) ) {
			$this->series[ (int) $post_id ]['status'] = 'trash';
		}
	}
}
