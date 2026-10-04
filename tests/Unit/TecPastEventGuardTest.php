<?php
/**
 * Tests for TEC::is_past_event().
 *
 * ChMS queries are windowed ( PCO's calendar crawl is hardcoded to `future`
 * event_instances, CCB's to a configured date range ), so an event that passes its
 * end date simply stops appearing in the fetch. The sync cleanup would otherwise
 * read that absence as "deleted at the source" and force-delete the post. This
 * helper is the "is it past?" decision that keeps event history intact.
 *
 * Pure static logic on zero-padded `Y-m-d H:i:s` strings — no WP stubs needed.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\Integrations\TEC;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Integrations\TEC::is_past_event
 */
class TecPastEventGuardTest extends TestCase {

	/** A fixed "now" so these tests never depend on the wall clock. */
	private const NOW = '2026-08-09 14:30:00';

	public function test_event_that_ended_yesterday_is_past() {
		$this->assertTrue( TEC::is_past_event( '2026-08-08 21:00:00', self::NOW ) );
	}

	public function test_event_that_ended_earlier_today_is_past() {
		$this->assertTrue( TEC::is_past_event( '2026-08-09 12:00:00', self::NOW ) );
	}

	public function test_future_event_is_not_past() {
		$this->assertFalse( TEC::is_past_event( '2026-12-25 10:00:00', self::NOW ) );
	}

	public function test_event_ending_later_today_is_not_past() {
		$this->assertFalse( TEC::is_past_event( '2026-08-09 18:00:00', self::NOW ) );
	}

	public function test_currently_running_multi_day_event_is_not_past() {
		// The END date decides, so an event that started days ago but runs through
		// next week must not be treated as history.
		$this->assertFalse( TEC::is_past_event( '2026-08-15 17:00:00', self::NOW ) );
	}

	public function test_all_day_event_survives_its_final_day() {
		// TEC stores all-day events ending at 23:59:59 — an all-day event happening
		// today is still current at 14:30.
		$this->assertFalse( TEC::is_past_event( '2026-08-09 23:59:59', self::NOW ) );
	}

	public function test_all_day_event_is_past_the_next_day() {
		$this->assertTrue( TEC::is_past_event( '2026-08-08 23:59:59', self::NOW ) );
	}

	public function test_exact_now_is_not_past() {
		// Boundary: an event ending exactly now has not yet passed.
		$this->assertFalse( TEC::is_past_event( self::NOW, self::NOW ) );
	}

	public function test_year_boundary_orders_correctly() {
		// Guards the string comparison: lexicographic order must track chronology
		// across a year rollover, not just within one year.
		$this->assertTrue( TEC::is_past_event( '2025-12-31 23:59:59', self::NOW ) );
		$this->assertFalse( TEC::is_past_event( '2027-01-01 00:00:00', self::NOW ) );
	}

	public function test_missing_end_date_is_undeterminable() {
		// null ( not false ) so the caller can distinguish "not past" from "no idea"
		// and fall through to the normal removal for malformed records.
		$this->assertNull( TEC::is_past_event( '', self::NOW ) );
		$this->assertNull( TEC::is_past_event( null, self::NOW ) );
	}

	public function test_missing_now_is_undeterminable() {
		$this->assertNull( TEC::is_past_event( '2026-08-08 21:00:00', '' ) );
	}
}
