<?php
/**
 * [cp-sync-events] attribute parsing and query arguments.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\Events\EventsShortcode;
use CP_Sync\Events\Meta;
use CP_Sync\Events\PostTypes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Events\EventsShortcode
 */
class EventsShortcodeTest extends TestCase {

	public function test_limit_category_and_series_arguments() {
		$parsed = EventsShortcode::parse_atts(
			array(
				'limit'    => '3',
				'category' => 'Worship Night',
				'series'   => '12',
			)
		);

		$this->assertSame( 3, $parsed['limit'] );
		$this->assertSame( 'worship-night', $parsed['category'] );
		$this->assertSame( '12', $parsed['series'] );

		$args = EventsShortcode::query_args( $parsed, '2026-08-01 00:00:00', 12 );

		$this->assertSame( PostTypes::EVENT, $args['post_type'] );
		$this->assertSame( 3, $args['posts_per_page'] );
		$this->assertSame( 'ASC', $args['order'] );
		$this->assertSame( Meta::END_UTC, $args['meta_query'][0]['key'] );
		$this->assertSame( '2026-08-01 00:00:00', $args['meta_query'][0]['value'] );
		$this->assertSame( PostTypes::CATEGORY, $args['tax_query'][0]['taxonomy'] );
		$this->assertSame( 'worship-night', $args['tax_query'][0]['terms'] );
		$this->assertSame( Meta::SERIES_ID, $args['meta_query'][1]['key'] );
		$this->assertSame( '12', $args['meta_query'][1]['value'] );
	}

	public function test_omitted_arguments_use_the_defaults() {
		$parsed = EventsShortcode::parse_atts( array() );

		$this->assertSame( 5, $parsed['limit'] );
		$this->assertSame( '', $parsed['category'] );
		$this->assertSame( '', $parsed['series'] );

		$args = EventsShortcode::query_args( $parsed, '2026-08-01 00:00:00', 0 );

		$this->assertSame( 5, $args['posts_per_page'] );
		$this->assertArrayNotHasKey( 'tax_query', $args );
		$this->assertCount( 1, $args['meta_query'] );
	}

	public function test_a_zero_limit_lists_every_upcoming_date() {
		$parsed = EventsShortcode::parse_atts( array( 'limit' => '0' ) );

		$this->assertSame( -1, $parsed['limit'] );
		$this->assertSame( -1, EventsShortcode::query_args( $parsed, '2026-08-01 00:00:00' )['posts_per_page'] );
	}

	public function test_an_unknown_series_slug_does_not_match_every_event() {
		$parsed = EventsShortcode::parse_atts( array( 'series' => 'missing-series' ) );
		$args   = EventsShortcode::query_args( $parsed, '2026-08-01 00:00:00', EventsShortcode::resolve_series_id( 'missing-series' ) );

		$this->assertSame( '-1', $args['meta_query'][1]['value'] );
	}
}
