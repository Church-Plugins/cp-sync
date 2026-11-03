<?php
/**
 * Event post types register only when The Events Calendar is absent.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Events\Display;
use CP_Sync\Events\PostTypes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Events\PostTypes::should_register
 * @covers \CP_Sync\Events\Display::is_enabled
 */
class EventRegistrationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_registration_stays_off_while_the_events_calendar_is_active() {
		$this->assertFalse( PostTypes::should_register( true, true ) );
		$this->assertFalse( PostTypes::should_register( true, null ) );
		$this->assertFalse( PostTypes::should_register( true, false ) );
	}

	public function test_registration_is_on_by_default_when_the_events_calendar_is_absent() {
		$this->assertTrue( PostTypes::should_register( false, null ) );
		$this->assertTrue( PostTypes::should_register( false, true ) );
		$this->assertTrue( PostTypes::should_register( false, '1' ) );
		$this->assertTrue( PostTypes::should_register( false, 'on' ) );
	}

	public function test_registration_is_off_when_the_display_setting_is_off() {
		$this->assertFalse( PostTypes::should_register( false, false ) );
		$this->assertFalse( PostTypes::should_register( false, 0 ) );
		$this->assertFalse( PostTypes::should_register( false, '0' ) );
		$this->assertFalse( PostTypes::should_register( false, 'false' ) );
		$this->assertFalse( PostTypes::should_register( false, 'off' ) );
	}

	public function test_display_setting_reads_the_stored_value_when_the_events_calendar_is_absent() {
		Functions\when( 'get_option' )->alias(
			static function ( $key, $default = array() ) {
				if ( 'cp_sync_settings' === $key ) {
					return array( 'displayEvents' => false );
				}
				return $default;
			}
		);

		$this->assertFalse( Display::is_enabled() );
	}

	public function test_display_setting_defaults_on_when_nothing_is_stored() {
		Functions\when( 'get_option' )->justReturn( array() );

		$this->assertTrue( Display::is_enabled() );
		$this->assertNull( Display::stored_setting() );
	}
}
