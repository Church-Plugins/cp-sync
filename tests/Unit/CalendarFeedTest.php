<?php
/**
 * Dates sent to the events calendar.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Events\CalendarFeed;
use CP_Sync\Events\Display;
use CP_Sync\Events\Meta;
use CP_Sync\Events\PostTypes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Events\CalendarFeed
 * @covers \CP_Sync\Events\Display::current_view
 */
class CalendarFeedTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		unset( $_GET['view'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * A stored event row.
	 *
	 * @param array $overrides Fields to change.
	 * @return array
	 */
	private function row( array $overrides = array() ) {
		return array_merge(
			array(
				'id'         => 7,
				'title'      => 'Food &amp; Fellowship',
				'url'        => 'https://example.com/events/food',
				'start_utc'  => '2026-11-20 02:00:00',
				'end_utc'    => '2026-11-20 04:00:00',
				'timezone'   => 'America/Chicago',
				'all_day'    => false,
				'location'   => 'Hall',
				'categories' => array( 'Family &amp; Kids' ),
			),
			$overrides
		);
	}

	public function test_times_are_the_events_local_clock_with_no_offset() {
		$event = CalendarFeed::format_event( $this->row() );

		// 02:00 UTC is the evening before in Chicago, so it belongs on the 19th.
		$this->assertSame( '2026-11-19T20:00:00', $event['start'] );
		$this->assertSame( '2026-11-19T22:00:00', $event['end'] );
		$this->assertSame( '2026-11-19', $event['extendedProps']['lastDay'] );
		$this->assertSame( '8:00 pm', $event['extendedProps']['startTime'] );
		$this->assertSame( '8:00 pm – 10:00 pm', $event['extendedProps']['timeRange'] );
		$this->assertStringNotContainsString( 'Z', $event['start'] );
		$this->assertDoesNotMatchRegularExpression( '/[+-]\d\d:\d\d$/', $event['start'] );
	}

	public function test_text_is_plain_and_decoded_because_the_script_writes_text_nodes() {
		$event = CalendarFeed::format_event(
			$this->row(
				array(
					'title'    => '<b>Food</b> &amp; Fellowship',
					'location' => '<script>alert(1)</script>Hall',
				)
			)
		);

		$this->assertSame( 'Food & Fellowship', $event['title'] );
		$this->assertSame( 'Family & Kids', $event['extendedProps']['categories'][0] );
		$this->assertStringNotContainsString( '<', $event['extendedProps']['location'] );
		$this->assertArrayNotHasKey( 'price', $event['extendedProps'] );
	}

	public function test_all_day_end_is_the_day_after_the_last_day() {
		$event = CalendarFeed::format_event(
			$this->row(
				array(
					'all_day'   => true,
					'start_utc' => '2026-11-20 06:00:00',
					'end_utc'   => '2026-11-22 05:59:00',
				)
			)
		);

		$this->assertTrue( $event['allDay'] );
		$this->assertSame( '2026-11-20', $event['start'] );
		$this->assertSame( '2026-11-22', $event['end'] );
		$this->assertSame( '2026-11-21', $event['extendedProps']['lastDay'] );
		$this->assertSame( '', $event['extendedProps']['startTime'] );
		$this->assertSame( 'All day', $event['extendedProps']['timeRange'] );
	}

	public function test_multi_day_date_keeps_its_last_day_and_shows_dates_in_the_range() {
		$event = CalendarFeed::format_event(
			$this->row(
				array(
					'start_utc' => '2026-11-21 02:00:00',
					'end_utc'   => '2026-11-22 20:00:00',
				)
			)
		);

		$this->assertSame( '2026-11-22', $event['extendedProps']['lastDay'] );
		$this->assertStringContainsString( 'Nov 20', $event['extendedProps']['timeRange'] );
		$this->assertStringContainsString( 'Nov 22', $event['extendedProps']['timeRange'] );
	}

	public function test_an_end_at_or_before_the_start_is_not_a_real_end() {
		$event = CalendarFeed::format_event( $this->row( array( 'end_utc' => '2026-11-20 02:00:00' ) ) );

		$this->assertNull( $event['end'] );
		$this->assertSame( '2026-11-19', $event['extendedProps']['lastDay'] );
		$this->assertSame( '8:00 pm', $event['extendedProps']['timeRange'] );
	}

	public function test_an_unreadable_start_is_left_out_and_a_bad_zone_uses_utc() {
		$this->assertNull( CalendarFeed::format_event( $this->row( array( 'start_utc' => 'TBD' ) ) ) );

		$event = CalendarFeed::format_event( $this->row( array( 'timezone' => 'Not/AZone' ) ) );

		$this->assertSame( '2026-11-20T02:00:00', $event['start'] );
	}

	public function test_a_non_web_url_is_not_sent() {
		$event = CalendarFeed::format_event( $this->row( array( 'url' => 'javascript:alert(1)' ) ) );

		$this->assertSame( '', $event['url'] );
	}

	/**
	 * @dataProvider bad_ranges
	 */
	public function test_bad_ranges_are_rejected( $start, $end ) {
		$this->assertNull( CalendarFeed::parse_range( $start, $end ) );
	}

	public function bad_ranges() {
		return array(
			'missing'      => array( null, '2026-12-01' ),
			'not a date'   => array( 'soon', '2026-12-01' ),
			'impossible'   => array( '2026-02-31', '2026-03-05' ),
			'with a time'  => array( '2026-11-01T00:00:00', '2026-12-01' ),
			'array'        => array( array( '2026-11-01' ), '2026-12-01' ),
			'backwards'    => array( '2026-12-01', '2026-11-01' ),
			'same day'     => array( '2026-11-01', '2026-11-01' ),
			'too wide'     => array( '2026-01-01', '2027-01-01' ),
		);
	}

	public function test_a_month_grid_range_is_accepted() {
		$this->assertSame(
			array(
				'start' => '2026-11-01',
				'end'   => '2026-12-06',
			),
			CalendarFeed::parse_range( '2026-11-01', '2026-12-06' )
		);
	}

	public function test_query_returns_only_public_dates_that_overlap_the_range() {
		$args = CalendarFeed::query_args( '2026-11-01', '2026-12-06', 'Family Night' );

		$this->assertSame( PostTypes::EVENT, $args['post_type'] );
		$this->assertSame( 'publish', $args['post_status'] );
		$this->assertFalse( $args['has_password'] );
		$this->assertSame( CalendarFeed::MAX_EVENTS, $args['posts_per_page'] );

		$by_key = array_column( $args['meta_query'], null, 'key' );

		// One day of padding for dates whose local day differs from their UTC day.
		$this->assertSame( '2026-12-07 00:00:00', $by_key[ Meta::START_UTC ]['value'] );
		$this->assertSame( '<', $by_key[ Meta::START_UTC ]['compare'] );
		$this->assertSame( '2026-10-31 00:00:00', $by_key[ Meta::END_UTC ]['value'] );
		$this->assertSame( '>=', $by_key[ Meta::END_UTC ]['compare'] );
		$this->assertSame( PostTypes::CATEGORY, $args['tax_query'][0]['taxonomy'] );
		$this->assertSame( 'family-night', $args['tax_query'][0]['terms'] );
	}

	public function test_no_category_adds_no_tax_query() {
		$this->assertArrayNotHasKey( 'tax_query', CalendarFeed::query_args( '2026-11-01', '2026-12-06', '' ) );
		$this->assertArrayNotHasKey( 'tax_query', CalendarFeed::query_args( '2026-11-01', '2026-12-06', array( 'a' ) ) );
	}

	public function test_calendar_is_the_default_view() {
		$this->assertSame( 'calendar', Display::current_view() );

		$_GET['view'] = 'list';
		$this->assertSame( 'list', Display::current_view() );

		$_GET['view'] = '<script>';
		$this->assertSame( 'calendar', Display::current_view() );

		$_GET['view'] = array( 'list' );
		$this->assertSame( 'calendar', Display::current_view() );
	}
}
