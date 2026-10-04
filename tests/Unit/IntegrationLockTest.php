<?php
/**
 * Tests for the per-post sync lock on CP_Sync\Integrations\Integration.
 *
 * is_locked() is the single predicate the three sync guards ( task() update,
 * process() queue, process() removal ) share, so covering it covers the lock's
 * behavior. The Integration base extends WP_Background_Process, so we build the
 * unit under test with newInstanceWithoutConstructor() ( no WP boot ) and stub
 * the WP functions it touches with Brain Monkey.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Integrations\Integration;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Concrete, dependency-free stand-in for the abstract Integration. Overrides the
 * one collaborator is_locked() needs ( the chms_id => post_id lookup ) so the
 * test controls it without a database.
 */
class LockTestIntegration extends Integration {

	/** @var array chms_id => post_id */
	public $map = [];

	public function get_chms_item_id( $chms_id ) {
		return $this->map[ $chms_id ] ?? null;
	}

	public function update_item( $item ) {}

	public function register_taxonomy( $taxonomy, $args ) {}
}

/**
 * @covers \CP_Sync\Integrations\Integration::is_locked
 */
class IntegrationLockTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// is_locked() passes its result through this filter; identity is the default.
		Functions\when( 'apply_filters' )->alias(
			static fn( $tag, $value ) => $value
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function integration( array $map = [] ): LockTestIntegration {
		$integration = ( new ReflectionClass( LockTestIntegration::class ) )->newInstanceWithoutConstructor();
		$integration->map = $map;
		return $integration;
	}

	public function test_unimported_item_is_never_locked() {
		// No post for this chms_id => nothing to lock, and get_post_meta must not be called.
		Functions\expect( 'get_post_meta' )->never();

		$this->assertFalse( $this->integration()->is_locked( 'abc123' ) );
	}

	public function test_imported_item_with_lock_meta_is_locked() {
		Functions\when( 'get_post_meta' )
			->justReturn( '1' );

		$this->assertTrue( $this->integration( [ 'abc123' => 42 ] )->is_locked( 'abc123' ) );
	}

	public function test_imported_item_without_lock_meta_is_not_locked() {
		// An unset lock meta reads back as '' from WordPress.
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$this->assertFalse( $this->integration( [ 'abc123' => 42 ] )->is_locked( 'abc123' ) );
	}

	public function test_filter_can_force_lock_on_an_unlocked_post() {
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'apply_filters' )->justReturn( true );

		$this->assertTrue( $this->integration( [ 'abc123' => 42 ] )->is_locked( 'abc123' ) );
	}
}
