<?php
/**
 * Site-timezone labels for event dates.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\Events\EventFormatter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Events\EventFormatter
 */
class EventTimezoneDisplayTest extends TestCase {

	public function test_a_utc_instant_displays_in_the_site_timezone() {
		$when = EventFormatter::format_when(
			'2026-08-01 15:30:00',
			'2026-08-01 17:00:00',
			'America/New_York',
			false
		);

		$this->assertSame( 'August 1, 2026, 11:30 am – 1:00 pm', $when['text'] );
		$this->assertFalse( $when['all_day'] );
		$this->assertSame( '2026-08-01T15:30:00Z', $when['iso'] );
	}

	public function test_winter_offset_uses_standard_time() {
		$when = EventFormatter::format_when(
			'2026-01-15 18:00:00',
			'2026-01-15 19:00:00',
			'America/New_York',
			false
		);

		$this->assertSame( 'January 15, 2026, 1:00 pm – 2:00 pm', $when['text'] );
	}

	public function test_all_day_shows_the_site_local_date_without_a_clock_time() {
		$when = EventFormatter::format_when(
			'2026-08-01 04:00:00',
			'2026-08-02 03:59:00',
			'America/New_York',
			true
		);

		$this->assertSame( 'August 1, 2026', $when['text'] );
		$this->assertTrue( $when['all_day'] );
	}
}
