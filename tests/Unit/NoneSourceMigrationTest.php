<?php
/**
 * Tests for the pure `none` → sync-toggle settings transform on
 * CP_Sync\ChMS\PCO::migrate_none_source_settings().
 *
 * "Do not pull" ( ecp.source === 'none' ) is retired as a radio option; its
 * meaning migrates to connect.sync_events = false with ecp.source reset to
 * 'calendar'. The transform is pure ( no WP ) and idempotent.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\ChMS\PCO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\PCO::migrate_none_source_settings
 */
class NoneSourceMigrationTest extends TestCase {

	public function test_none_migrates_to_sync_off_and_calendar_source() {
		$settings = [
			'connect' => [ 'sync_events' => true, 'sync_groups' => true ],
			'ecp'     => [ 'source' => 'none', 'filter' => [ 'type' => 'all' ] ],
		];

		$migrated = PCO::migrate_none_source_settings( $settings );

		$this->assertIsArray( $migrated );
		$this->assertFalse( $migrated['connect']['sync_events'] );
		$this->assertSame( 'calendar', $migrated['ecp']['source'] );

		// Unrelated settings are preserved.
		$this->assertTrue( $migrated['connect']['sync_groups'] );
		$this->assertSame( [ 'type' => 'all' ], $migrated['ecp']['filter'] );
	}

	public function test_calendar_source_is_not_changed() {
		$settings = [
			'connect' => [ 'sync_events' => true ],
			'ecp'     => [ 'source' => 'calendar' ],
		];

		$this->assertNull( PCO::migrate_none_source_settings( $settings ) );
	}

	public function test_registrations_source_is_not_changed() {
		$settings = [ 'ecp' => [ 'source' => 'registrations' ] ];

		$this->assertNull( PCO::migrate_none_source_settings( $settings ) );
	}

	public function test_both_source_is_not_changed() {
		$settings = [ 'ecp' => [ 'source' => 'both' ] ];

		$this->assertNull( PCO::migrate_none_source_settings( $settings ) );
	}

	public function test_missing_source_is_not_changed() {
		$settings = [ 'connect' => [ 'sync_events' => true ] ];

		$this->assertNull( PCO::migrate_none_source_settings( $settings ) );
	}

	public function test_non_array_input_is_not_changed() {
		$this->assertNull( PCO::migrate_none_source_settings( 'not-an-array' ) );
		$this->assertNull( PCO::migrate_none_source_settings( null ) );
	}

	public function test_migration_is_idempotent() {
		$settings = [
			'connect' => [ 'sync_events' => true ],
			'ecp'     => [ 'source' => 'none' ],
		];

		// First run migrates.
		$once = PCO::migrate_none_source_settings( $settings );
		$this->assertIsArray( $once );

		// Second run over the already-migrated result reports no change.
		$twice = PCO::migrate_none_source_settings( $once );
		$this->assertNull( $twice );
	}
}
