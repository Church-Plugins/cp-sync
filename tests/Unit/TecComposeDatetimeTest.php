<?php
/**
 * Tests for TEC::compose_datetime().
 *
 * The ChMS formatters emit EventStartDate as a bare `Y-m-d` with the time split
 * into separate Hour/Minute keys; TEC's ORM parses a bare date as midnight. This
 * helper folds the parts back together — the fix for every synced event landing
 * at 12:00am. Pure static logic, no WP stubs needed.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\Integrations\TEC;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Integrations\TEC::compose_datetime
 */
class TecComposeDatetimeTest extends TestCase {

	public function test_composes_full_datetime_from_parts() {
		$this->assertSame( '2026-08-05 19:30:00', TEC::compose_datetime( '2026-08-05', '19', '30' ) );
	}

	public function test_pads_single_digit_hour_and_minute() {
		$this->assertSame( '2026-08-05 09:05:00', TEC::compose_datetime( '2026-08-05', '9', '5' ) );
	}

	public function test_hour_zero_is_a_valid_time_not_a_missing_one() {
		// Midnight is a legitimate start time; hour "0" must not degrade to a bare date.
		$this->assertSame( '2026-08-05 00:15:00', TEC::compose_datetime( '2026-08-05', '0', '15' ) );
	}

	public function test_accepts_integer_parts() {
		$this->assertSame( '2026-08-05 07:00:00', TEC::compose_datetime( '2026-08-05', 7, 0 ) );
	}

	public function test_missing_hour_yields_bare_date() {
		// No time information — preserve the pre-fix behavior of passing the date through.
		$this->assertSame( '2026-08-05', TEC::compose_datetime( '2026-08-05', null, null ) );
		$this->assertSame( '2026-08-05', TEC::compose_datetime( '2026-08-05', '', '' ) );
	}

	public function test_missing_minute_defaults_to_zero() {
		$this->assertSame( '2026-08-05 19:00:00', TEC::compose_datetime( '2026-08-05', '19', null ) );
	}

	public function test_empty_date_yields_empty_string() {
		// array_filter() in update_item() strips '' so TEC never receives a bogus date.
		$this->assertSame( '', TEC::compose_datetime( '', '19', '30' ) );
	}
}
