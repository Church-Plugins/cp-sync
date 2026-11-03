<?php
/**
 * Hidden, unapproved, and not-listed rows are skipped.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\CCB;
use CP_Sync\ChMS\PCO;
use CP_Sync\Events\EventVisibility;
use CP_Sync\Events\VisibilityRemovalNotice;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Events\EventVisibility
 * @covers \CP_Sync\ChMS\PCO::format_event_from_calendar
 * @covers \CP_Sync\ChMS\CCB::format_event
 */
class EventVisibilityTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'UTC' ) );
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
		Functions\when( 'get_option' )->justReturn( [] );
	}

	protected function tearDown(): void {
		VisibilityRemovalNotice::reset();
		Monkey\tearDown();
		parent::tearDown();
	}

	private function makePco(): PCO {
		return ( new \ReflectionClass( PCO::class ) )->newInstanceWithoutConstructor();
	}

	private function makeCcb(): CCB {
		return ( new \ReflectionClass( CCB::class ) )->newInstanceWithoutConstructor();
	}

	private function calendar( array $event_attributes ): array {
		$instance = [
			'id'         => 'inst-1',
			'attributes' => [ 'all_day_event' => false ],
			'relationships' => [
				'event'       => [ 'data' => [ 'id' => 'e1' ] ],
				'event_times' => [ 'data' => [ [ 'id' => 't1' ] ] ],
				'tags'        => [ 'data' => [] ],
			],
		];
		$context = [
			'relational_data' => [
				'Event'     => [
					'e1' => [
						'attributes' => array_merge(
							[ 'name' => 'Service' ],
							$event_attributes
						),
					],
				],
				'EventTime' => [
					't1' => [
						'attributes' => [
							'starts_at' => '2026-08-01T15:00:00Z',
							'ends_at'   => '2026-08-01T16:00:00Z',
						],
					],
				],
			],
		];

		return [ $instance, $context ];
	}

	public function test_hidden_calendar_event_is_skipped() {
		list( $instance, $context ) = $this->calendar(
			[
				'visible_in_church_center' => false,
				'approval_status'          => 'A',
			]
		);

		$this->assertFalse( $this->makePco()->format_event_from_calendar( $instance, $context ) );
	}

	public function test_explicitly_unapproved_calendar_events_are_skipped() {
		foreach ( [ 'P', 'R' ] as $status ) {
			list( $instance, $context ) = $this->calendar(
				[
					'visible_in_church_center' => true,
					'approval_status'          => $status,
				]
			);
			$this->assertFalse( $this->makePco()->format_event_from_calendar( $instance, $context ), $status );
		}
	}

	public function test_approval_status_a_missing_null_and_empty_are_approved() {
		$this->assertFalse( EventVisibility::approval_status_blocks_sync( 'A' ) );
		$this->assertFalse( EventVisibility::approval_status_blocks_sync( 'a' ) );
		$this->assertFalse( EventVisibility::approval_status_blocks_sync( null ) );
		$this->assertFalse( EventVisibility::approval_status_blocks_sync( '' ) );
		$this->assertFalse( EventVisibility::approval_status_blocks_sync( '   ' ) );
		$this->assertTrue( EventVisibility::approval_status_blocks_sync( 'P' ) );
		$this->assertTrue( EventVisibility::approval_status_blocks_sync( 'R' ) );

		$pco = $this->makePco();

		foreach ( [ 'A', 'a', '' ] as $status ) {
			$attributes = [
				'visible_in_church_center' => true,
				'approval_status'          => $status,
			];
			list( $instance, $context ) = $this->calendar( $attributes );
			$result = $pco->format_event_from_calendar( $instance, $context );
			$this->assertSame( 'Service', $result['post_title'], 'status ' . var_export( $status, true ) );
		}

		// Missing approval_status: the key is absent entirely.
		list( $instance, $context ) = $this->calendar( [ 'visible_in_church_center' => true ] );
		$this->assertSame( 'Service', $pco->format_event_from_calendar( $instance, $context )['post_title'] );

		// Null is a present key, not a missing one.
		list( $instance, $context ) = $this->calendar(
			[
				'visible_in_church_center' => true,
				'approval_status'          => null,
			]
		);
		$this->assertSame( 'Service', $pco->format_event_from_calendar( $instance, $context )['post_title'] );
	}

	public function test_show_all_does_not_filter_hidden_or_unapproved_events() {
		Functions\when( 'get_option' )->alias(
			static function ( $key, $default = [] ) {
				if ( 'cp_sync_pco_settings' === $key ) {
					return [ 'ecp' => [ 'visibility' => 'all' ] ];
				}
				return $default;
			}
		);

		$pco = $this->makePco();
		$this->assertFalse( $pco->calendar_visibility_is_restrictive() );

		list( $instance, $context ) = $this->calendar(
			[
				'visible_in_church_center' => false,
				'approval_status'          => 'P',
			]
		);

		$result = $pco->format_event_from_calendar( $instance, $context );

		$this->assertSame( 'Service', $result['post_title'] );
	}

	public function test_only_visible_in_church_center_is_the_restrictive_option() {
		Functions\when( 'get_option' )->alias(
			static function ( $key, $default = [] ) {
				if ( 'cp_sync_pco_settings' === $key ) {
					return [ 'ecp' => [ 'visibility' => 'public' ] ];
				}
				return $default;
			}
		);

		$this->assertTrue( $this->makePco()->calendar_visibility_is_restrictive() );
	}

	public function test_visible_approved_calendar_event_syncs() {
		list( $instance, $context ) = $this->calendar(
			[
				'visible_in_church_center' => true,
				'approval_status'          => 'A',
				'link_only'                => true,
			]
		);

		$result = $this->makePco()->format_event_from_calendar( $instance, $context );

		$this->assertSame( 'Service', $result['post_title'] );
		$this->assertSame( '2026-08-01', $result['EventStartDate'] );
	}

	public function test_string_true_counts_as_visible() {
		$this->assertTrue(
			EventVisibility::pco_calendar_is_syncable(
				[
					'visible_in_church_center' => 'true',
					'approval_status'          => 'A',
				]
			)
		);
	}

	public function test_missing_calendar_flags_still_sync() {
		list( $instance, $context ) = $this->calendar( [] );

		$result = $this->makePco()->format_event_from_calendar( $instance, $context );

		$this->assertSame( 'inst-1', $result['chms_id'] );
	}

	public function test_fetch_filter_drops_hidden_instances_and_keeps_rows_without_an_event() {
		$items = [
			[
				'id'            => 'keep',
				'relationships' => [ 'event' => [ 'data' => [ 'id' => 'e1' ] ] ],
			],
			[
				'id'            => 'drop',
				'relationships' => [ 'event' => [ 'data' => [ 'id' => 'e2' ] ] ],
			],
			[
				'id'            => 'no-event',
				'relationships' => [ 'event' => [ 'data' => [ 'id' => 'missing' ] ] ],
			],
		];
		$relational = [
			'Event' => [
				'e1' => [ 'attributes' => [ 'visible_in_church_center' => true, 'approval_status' => 'A' ] ],
				'e2' => [ 'attributes' => [ 'visible_in_church_center' => true, 'approval_status' => 'P' ] ],
			],
		];

		$kept = EventVisibility::filter_pco_calendar_items( $items, $relational );

		$this->assertSame( [ 'keep', 'no-event' ], array_column( $kept, 'id' ) );
	}

	public function test_ccb_unapproved_draft_and_unlisted_rows_are_skipped() {
		$base = [
			'event_id'       => '3',
			'name'           => 'Private',
			'start_datetime' => '2026-08-05 10:00:00',
			'end_datetime'   => '2026-08-05 11:00:00',
		];
		$ccb = $this->makeCcb();

		$this->assertFalse( $ccb->format_event( array_merge( $base, [ 'approval_status' => 'pending' ] ), [] ) );
		$this->assertFalse( $ccb->format_event( array_merge( $base, [ 'approval_status' => 'APPROVED', 'is_draft' => 'true' ] ), [] ) );
		$this->assertFalse( $ccb->format_event( array_merge( $base, [ 'listed' => 'false' ] ), [] ) );
		$this->assertFalse( $ccb->format_event( array_merge( $base, [ 'is_approved' => false ] ), [] ) );
		$this->assertFalse( $ccb->format_event( array_merge( $base, [ 'visibility' => 'private' ] ), [] ) );
	}

	public function test_ccb_approved_and_plain_public_listing_sync() {
		$ccb = $this->makeCcb();

		$approved = $ccb->format_event(
			[
				'event_id'        => '3',
				'name'            => 'Public',
				'start_datetime'  => '2026-08-05 10:00:00',
				'end_datetime'    => '2026-08-05 11:00:00',
				'approval_status' => 'approved',
				'listed'          => 'true',
			],
			[]
		);
		$this->assertSame( 'Public', $approved['post_title'] );
		$this->assertSame( 'approved', $approved['meta_input']['approval_status'] );

		$listing = $ccb->format_event(
			[
				'event_id'       => '8',
				'name'           => 'From the public calendar',
				'start_datetime' => '2026-08-05 10:00:00',
				'end_datetime'   => '2026-08-05 11:00:00',
			],
			[]
		);
		$this->assertSame( '8', $listing['chms_id'] );
	}

	public function test_pco_groups_skip_unlisted_and_non_public_when_the_flag_is_present() {
		$items = [
			[ 'id' => 'public', 'attributes' => [ 'name' => 'A', 'listed' => true, 'visibility' => 'public' ] ],
			[ 'id' => 'unlisted', 'attributes' => [ 'name' => 'B', 'listed' => false, 'visibility' => 'public' ] ],
			[ 'id' => 'private', 'attributes' => [ 'name' => 'C', 'listed' => true, 'visibility' => 'private' ] ],
			[ 'id' => 'legacy', 'attributes' => [ 'name' => 'D', 'public_church_center_web_url' => 'https://example.com' ] ],
		];

		$kept = EventVisibility::filter_pco_groups( $items );

		$this->assertSame( [ 'public', 'legacy' ], array_column( $kept, 'id' ) );
	}
}
