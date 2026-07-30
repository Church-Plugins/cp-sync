<?php
/**
 * Tests for the pure dual-source event helpers on CP_Sync\ChMS\PCO:
 *   - events_sources_for()  — stored `source` → enabled-source list.
 *   - merge_event_sources() — per-source fetch results → one tagged/namespaced
 *                             payload ( amendments #1/#6/#8 ).
 *
 * Both are static and touch no WP / API, so they are exercised directly.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\ChMS\PCO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\PCO::events_sources_for
 * @covers \CP_Sync\ChMS\PCO::merge_event_sources
 */
class EventsSourceMergeTest extends TestCase {

	/* ------------------------------------------------- events_sources_for */

	public function test_calendar_maps_to_calendar_only() {
		$this->assertSame( [ 'calendar' ], PCO::events_sources_for( 'calendar' ) );
	}

	public function test_registrations_maps_to_registrations_only() {
		$this->assertSame( [ 'registrations' ], PCO::events_sources_for( 'registrations' ) );
	}

	public function test_both_maps_to_calendar_and_registrations() {
		$this->assertSame( [ 'calendar', 'registrations' ], PCO::events_sources_for( 'both' ) );
	}

	public function test_none_maps_to_empty_list() {
		// Empty list → fetch_events() returns a ChMSError no-op ( amendment #1 ).
		$this->assertSame( [], PCO::events_sources_for( 'none' ) );
	}

	public function test_unknown_maps_to_empty_list() {
		$this->assertSame( [], PCO::events_sources_for( 'anything-else' ) );
	}

	/* ------------------------------------------------ merge_event_sources */

	public function test_merge_tags_items_and_namespaces_relational_data() {
		$results = [
			'calendar' => [
				'items'      => [ [ 'id' => 'c1' ], [ 'id' => 'c2' ] ],
				'context'    => [ 'relational_data' => [ 'EventTime' => [ '1' => 'cal-time' ] ] ],
				'taxonomies' => [ 'cps_topic' => [ 'taxonomy' => 'cps_topic' ] ],
			],
			'registrations' => [
				'items'      => [ [ 'id' => 'r1' ] ],
				'context'    => [ 'relational_data' => [ 'SignupTime' => [ '1' => 'reg-time' ] ] ],
				'taxonomies' => [],
			],
		];

		$merged = PCO::merge_event_sources( $results );

		// Items concatenated in source order, each tagged with its origin.
		$this->assertCount( 3, $merged['items'] );
		$this->assertSame( 'calendar', $merged['items'][0]['_cp_source'] );
		$this->assertSame( 'calendar', $merged['items'][1]['_cp_source'] );
		$this->assertSame( 'registrations', $merged['items'][2]['_cp_source'] );

		// Relational data is namespaced per source — the shared `1` id cannot collide.
		$this->assertSame(
			'cal-time',
			$merged['context']['relational_data']['calendar']['EventTime']['1']
		);
		$this->assertSame(
			'reg-time',
			$merged['context']['relational_data']['registrations']['SignupTime']['1']
		);
	}

	public function test_taxonomies_come_from_calendar_only() {
		$results = [
			'calendar' => [
				'items'      => [ [ 'id' => 'c1' ] ],
				'context'    => [ 'relational_data' => [] ],
				'taxonomies' => [ 'cps_topic' => [ 'taxonomy' => 'cps_topic' ] ],
			],
			'registrations' => [
				'items'      => [ [ 'id' => 'r1' ] ],
				'context'    => [ 'relational_data' => [] ],
				// A registrations taxonomy ( should be ignored ) — none exist in reality.
				'taxonomies' => [ 'cps_reg' => [ 'taxonomy' => 'cps_reg' ] ],
			],
		];

		$merged = PCO::merge_event_sources( $results );

		$this->assertSame( [ 'cps_topic' => [ 'taxonomy' => 'cps_topic' ] ], $merged['taxonomies'] );
	}

	public function test_registrations_only_merge_has_no_taxonomies() {
		$results = [
			'registrations' => [
				'items'      => [ [ 'id' => 'r1' ] ],
				'context'    => [ 'relational_data' => [ 'SignupTime' => [] ] ],
				'taxonomies' => [],
			],
		];

		$merged = PCO::merge_event_sources( $results );

		$this->assertSame( [], $merged['taxonomies'] );
		$this->assertSame( 'registrations', $merged['items'][0]['_cp_source'] );
		$this->assertArrayHasKey( 'registrations', $merged['context']['relational_data'] );
		$this->assertArrayNotHasKey( 'calendar', $merged['context']['relational_data'] );
	}

	public function test_empty_results_produce_empty_payload() {
		$merged = PCO::merge_event_sources( [] );

		$this->assertSame( [], $merged['items'] );
		$this->assertSame( [], $merged['context']['relational_data'] );
		$this->assertSame( [], $merged['taxonomies'] );
	}
}
