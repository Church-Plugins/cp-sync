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

	public function test_a_missing_start_does_not_become_the_current_time() {
		$when = EventFormatter::format_when( '', '', 'America/New_York', false );

		$this->assertSame( '', $when['text'] );
		$this->assertSame( '', $when['iso'] );
		$this->assertFalse( $when['all_day'] );
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

	public function test_badge_comes_from_the_timestamp_not_the_sentence() {
		$badge = EventFormatter::badge( '2026-11-20 02:00:00', 'America/Los_Angeles' );

		$this->assertTrue( $badge['valid'] );
		$this->assertSame( 'Nov', $badge['month'] );
		$this->assertSame( '19', $badge['day'] );
		$this->assertSame( '2026', $badge['year'] );
		$this->assertFalse( EventFormatter::badge( 'tomorrow', 'America/Los_Angeles' )['valid'] );
		$this->assertFalse( EventFormatter::badge( '', 'UTC' )['valid'] );
	}

	public function test_cross_year_range_keeps_both_years() {
		$when = EventFormatter::format_when(
			'2026-12-31 20:00:00',
			'2027-01-01 10:00:00',
			'America/Los_Angeles',
			false
		);

		$this->assertStringContainsString( '2026', $when['text'] );
		$this->assertStringContainsString( '2027', $when['text'] );

		$badge = EventFormatter::badge( '2026-12-31 20:00:00', 'America/Los_Angeles' );

		$this->assertSame( 'Dec', $badge['month'] );
		$this->assertSame( '31', $badge['day'] );
		$this->assertSame( '2026', $badge['year'] );
	}

	public function test_has_ended_uses_the_end_instant() {
		$this->assertTrue( EventFormatter::has_ended( '2026-11-01 00:00:00', '2026-11-06 12:00:00' ) );
		$this->assertFalse( EventFormatter::has_ended( '2026-11-06 12:00:00', '2026-11-06 12:00:00' ) );
		$this->assertFalse( EventFormatter::has_ended( '2026-11-20 00:00:00', '2026-11-06 12:00:00' ) );
		$this->assertFalse( EventFormatter::has_ended( '', '2026-11-06 12:00:00' ) );
		$this->assertFalse( EventFormatter::has_ended( 'not-a-date', '2026-11-06 12:00:00' ) );
		$this->assertFalse( EventFormatter::has_ended( '2026-11-22 20:00:00', '2026-11-21 12:00:00' ) );
	}
}
