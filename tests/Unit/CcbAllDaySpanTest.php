<?php
/**
 * Tests for CCB::is_all_day_span().
 *
 * CCB has no explicit all-day flag — an all-day event arrives as a
 * midnight-to-23:59 span. The detector turns that shape into TEC's all-day
 * flag so events render "All Day" instead of "12:00am - 11:59pm".
 * Pure static logic, no WP stubs needed.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\ChMS\CCB;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\CCB::is_all_day_span
 */
class CcbAllDaySpanTest extends TestCase {

	public function test_midnight_to_2359_is_all_day() {
		$this->assertTrue( CCB::is_all_day_span(
			new DateTime( '2026-08-05 00:00:00' ),
			new DateTime( '2026-08-05 23:59:00' )
		) );
	}

	public function test_multi_day_midnight_span_is_all_day() {
		$this->assertTrue( CCB::is_all_day_span(
			new DateTime( '2026-08-05 00:00:00' ),
			new DateTime( '2026-08-07 23:59:59' )
		) );
	}

	public function test_timed_event_is_not_all_day() {
		$this->assertFalse( CCB::is_all_day_span(
			new DateTime( '2026-08-05 14:00:00' ),
			new DateTime( '2026-08-05 16:00:00' )
		) );
	}

	public function test_event_starting_at_midnight_but_ending_early_is_not_all_day() {
		// A midnight service that ends at 1am is a real timed event.
		$this->assertFalse( CCB::is_all_day_span(
			new DateTime( '2026-01-01 00:00:00' ),
			new DateTime( '2026-01-01 01:00:00' )
		) );
	}

	public function test_event_ending_at_2359_but_starting_late_is_not_all_day() {
		// An evening event that runs to end-of-day is still timed.
		$this->assertFalse( CCB::is_all_day_span(
			new DateTime( '2026-08-05 20:00:00' ),
			new DateTime( '2026-08-05 23:59:00' )
		) );
	}
}
