<?php
/**
 * Tests for PCO::format_event() dual-source dispatch.
 *
 * Each merged raw item is tagged with `_cp_source` and its source's relational
 * data is namespaced under context.relational_data.<source>. format_event() must
 * route the item to the matching formatter, hand it ONLY its own slice
 * ( amendment #6 ), and never let the `_cp_source` tag reach the formatted output
 * ( amendment #8 ). Untagged items fall back to the legacy single-source path.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\PCO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\PCO::format_event
 */
class FormatEventDispatchTest extends TestCase {

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

	private function makePco(): PCO {
		return ( new \ReflectionClass( PCO::class ) )->newInstanceWithoutConstructor();
	}

	/** A merged context with BOTH sources present ( the collision-prone shape ). */
	private function mergedContext(): array {
		return [
			'relational_data' => [
				'calendar' => [
					'Event'     => [ 'e1' => [ 'id' => 'e1', 'attributes' => [ 'name' => 'Calendar Event' ] ] ],
					'EventTime' => [
						'1' => [ 'id' => '1', 'attributes' => [ 'starts_at' => '2026-08-01T10:00:00Z', 'ends_at' => '2026-08-01T11:00:00Z' ] ],
					],
				],
				'registrations' => [
					'SignupTime' => [
						// Same id `1` as the calendar EventTime — must NOT be cross-read.
						'1' => [ 'id' => '1', 'attributes' => [ 'starts_at' => '2026-09-10T09:00:00Z', 'ends_at' => '2026-09-10T11:00:00Z', 'all_day' => false ] ],
					],
				],
			],
		];
	}

	public function test_registrations_tagged_item_routes_to_registrations_formatter() {
		$item = [
			'_cp_source' => 'registrations',
			'id'         => '77',
			'attributes' => [ 'name' => 'Retreat', 'description' => '', 'new_registration_url' => 'https://church.center/77' ],
			'relationships' => [
				'signup_times'    => [ 'data' => [ [ 'type' => 'SignupTime', 'id' => '1' ] ] ],
				'categories'      => [ 'data' => [] ],
				'signup_location' => [ 'data' => [] ],
			],
		];

		$result = $this->makePco()->format_event( $item, $this->mergedContext() );

		// reg_ prefix proves the registrations formatter ran...
		$this->assertSame( 'reg_77', $result['chms_id'] );
		// ...and it read ITS OWN SignupTime `1` ( Sept ), not the calendar EventTime `1` ( Aug ).
		$this->assertSame( '2026-09-10', $result['EventStartDate'] );
		// The routing tag never leaks into the formatted output.
		$this->assertArrayNotHasKey( '_cp_source', $result );
	}

	public function test_calendar_tagged_item_routes_to_calendar_formatter() {
		$item = [
			'_cp_source' => 'calendar',
			'id'         => 'inst-9',
			'attributes' => [ 'all_day_event' => false ],
			'relationships' => [
				'event'       => [ 'data' => [ 'id' => 'e1' ] ],
				'event_times' => [ 'data' => [ [ 'id' => '1' ] ] ],
				'tags'        => [ 'data' => [] ],
			],
		];

		$result = $this->makePco()->format_event( $item, $this->mergedContext() );

		// Unprefixed id from the calendar formatter...
		$this->assertSame( 'inst-9', $result['chms_id'] );
		$this->assertSame( 'Calendar Event', $result['post_title'] );
		// ...reading ITS OWN EventTime `1` ( Aug ), not the registrations SignupTime `1` ( Sept ).
		$this->assertSame( '2026-08-01', $result['EventStartDate'] );
		$this->assertArrayNotHasKey( '_cp_source', $result );
	}

	public function test_untagged_item_falls_back_to_calendar_by_default() {
		// Legacy single-source context ( relational_data NOT namespaced ).
		Functions\when( 'get_option' )->justReturn( [] ); // stored source → default 'calendar'

		$context = [
			'relational_data' => [
				'Event'     => [ 'e1' => [ 'id' => 'e1', 'attributes' => [ 'name' => 'Legacy Event' ] ] ],
				'EventTime' => [ '1' => [ 'id' => '1', 'attributes' => [ 'starts_at' => '2026-08-01T10:00:00Z', 'ends_at' => '2026-08-01T11:00:00Z' ] ] ],
			],
		];

		$item = [
			'id'         => 'inst-1',
			'attributes' => [ 'all_day_event' => false ],
			'relationships' => [
				'event'       => [ 'data' => [ 'id' => 'e1' ] ],
				'event_times' => [ 'data' => [ [ 'id' => '1' ] ] ],
				'tags'        => [ 'data' => [] ],
			],
		];

		$result = $this->makePco()->format_event( $item, $context );

		$this->assertSame( 'inst-1', $result['chms_id'] );
		$this->assertSame( 'Legacy Event', $result['post_title'] );
	}
}
