<?php
/**
 * Tests for CP_Sync\ChMS\PCO::parse_calendar_location() — turning a Calendar event
 * instance's free-text `location` string into TEC's EventVenue contract.
 *
 * Pure logic ( no WordPress / API ), so the parser is exercised statically.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\ChMS\PCO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\PCO::parse_calendar_location
 */
class ParseCalendarLocationTest extends TestCase {

	/** The canonical "Name - Street, City, ST ZIP, Country" shape ( live-verified ). */
	public function test_full_named_address() {
		$v = PCO::parse_calendar_location( 'Church - 401 Wabash Ave, Granite Falls, WA 98252, USA' );

		$this->assertSame( 'Church', $v['venue'] );
		$this->assertSame( '401 Wabash Ave', $v['address'] );
		$this->assertSame( 'Granite Falls', $v['city'] );
		$this->assertSame( 'WA', $v['state'] );
		$this->assertSame( '98252', $v['zip'] );
	}

	/** ZIP+4 is accepted. */
	public function test_zip_plus_four() {
		$v = PCO::parse_calendar_location( 'Main Campus - 1 A St, Springfield, IL 62704-1234, USA' );

		$this->assertSame( 'IL', $v['state'] );
		$this->assertSame( '62704-1234', $v['zip'] );
	}

	/** No leading "Name - " → the first address line becomes the venue name. */
	public function test_address_without_name_prefix() {
		$v = PCO::parse_calendar_location( '401 Wabash Ave, Granite Falls, WA 98252' );

		$this->assertSame( '401 Wabash Ave', $v['venue'] );
		$this->assertSame( 'Granite Falls', $v['city'] );
		$this->assertSame( 'WA', $v['state'] );
		$this->assertSame( '98252', $v['zip'] );
	}

	/** A bare room name ( no comma structure ) degrades to a venue name only. */
	public function test_plain_room_name() {
		$v = PCO::parse_calendar_location( 'Room 101' );

		$this->assertSame( [ 'venue' => 'Room 101' ], $v );
	}

	/** "Name - Room" with no address keeps the name and no address fields. */
	public function test_named_room_no_address() {
		$v = PCO::parse_calendar_location( 'East Building - Nursery' );

		$this->assertSame( [ 'venue' => 'East Building' ], $v );
	}

	/** Empty input yields no venue. */
	public function test_empty() {
		$this->assertSame( [], PCO::parse_calendar_location( '' ) );
		$this->assertSame( [], PCO::parse_calendar_location( '   ' ) );
	}

	/** No state/zip anchor: still keeps name + best-effort street/city, drops nothing silently. */
	public function test_international_without_state_zip() {
		$v = PCO::parse_calendar_location( 'Parish Hall - 12 Rue de Rivoli, Paris, France' );

		$this->assertSame( 'Parish Hall', $v['venue'] );
		$this->assertSame( '12 Rue de Rivoli', $v['address'] );
		$this->assertSame( 'Paris', $v['city'] );
		$this->assertArrayNotHasKey( 'state', $v );
	}
}
