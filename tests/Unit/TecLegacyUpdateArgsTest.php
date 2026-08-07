<?php
/**
 * Tests for TEC::legacy_update_args().
 *
 * tribe_update_event() routes through the legacy Tribe__Events__API, which only
 * reads CamelCase Event* keys and re-saves the STORED date when EventStartDate
 * is absent. This builder feeds updates the formatter's own keys — without it,
 * date/time fixes never reach existing events (the "2000 events stuck at
 * midnight" failure). Pure static logic, no WP stubs needed.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\Integrations\TEC;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Integrations\TEC::legacy_update_args
 */
class TecLegacyUpdateArgsTest extends TestCase {

	public function test_maps_full_date_time_keys_for_a_timed_event() {
		$args = TEC::legacy_update_args( [
			'post_title'       => 'Bible Study',
			'post_content'     => 'Weekly study.',
			'EventStartDate'   => '2026-08-05',
			'EventStartHour'   => '19',
			'EventStartMinute' => '30',
			'EventEndDate'     => '2026-08-05',
			'EventEndHour'     => '21',
			'EventEndMinute'   => '00',
			'EventAllDay'      => false,
		] );

		$this->assertSame( 'Bible Study', $args['post_title'] );
		$this->assertSame( '2026-08-05', $args['EventStartDate'] );
		$this->assertSame( '19', $args['EventStartHour'] );
		$this->assertSame( '30', $args['EventStartMinute'] );
		$this->assertSame( '21', $args['EventEndHour'] );
	}

	public function test_all_day_false_is_present_so_a_stale_flag_gets_cleared() {
		// The create path's array_filter() strips false; the update path must NOT —
		// presence of EventAllDay=false is what clears all-day on a now-timed event.
		$args = TEC::legacy_update_args( [
			'EventStartDate' => '2026-08-05',
			'EventStartHour' => '19',
			'EventAllDay'    => false,
		] );

		$this->assertArrayHasKey( 'EventAllDay', $args );
		$this->assertFalse( $args['EventAllDay'] );
	}

	public function test_all_day_true_survives() {
		$args = TEC::legacy_update_args( [
			'EventStartDate' => '2026-08-05',
			'EventAllDay'    => true,
		] );

		$this->assertTrue( $args['EventAllDay'] );
	}

	public function test_missing_start_date_omits_every_date_key() {
		// EventStartDate must be ABSENT (not '') so TEC keeps the stored date.
		$args = TEC::legacy_update_args( [ 'post_title' => 'No dates' ] );

		$this->assertArrayNotHasKey( 'EventStartDate', $args );
		$this->assertArrayNotHasKey( 'EventEndDate', $args );
		$this->assertArrayNotHasKey( 'EventAllDay', $args );
		$this->assertSame( 'No dates', $args['post_title'] );
	}

	public function test_missing_end_date_falls_back_to_start_date() {
		$args = TEC::legacy_update_args( [ 'EventStartDate' => '2026-08-05' ] );

		$this->assertSame( '2026-08-05', $args['EventEndDate'] );
		$this->assertSame( '0', $args['EventStartHour'] );
		$this->assertSame( '00', $args['EventStartMinute'] );
	}
}
