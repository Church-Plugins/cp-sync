<?php
/**
 * Tests for the zero-items removal guard in Integration::process().
 *
 * A fetch that returns zero items while the store tracks existing imports is far
 * more likely a silent upstream failure (killed request, masked API error) than a
 * genuinely emptied ChMS. process() must abort untouched in that case — queueing
 * nothing, removing nothing, and leaving the store intact — instead of deleting
 * every previously imported post as a "leftover".
 *
 * Follows the IntegrationLockTest harness pattern: a dependency-free concrete
 * subclass built via newInstanceWithoutConstructor(), WP functions stubbed with
 * Brain Monkey.
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
 * Concrete stand-in that records every side effect process() can produce, so the
 * tests can assert exactly which ones happened without a database or queue.
 */
class RemovalGuardIntegration extends Integration {

	public $type  = 'events';
	public $label = 'Events';

	/** @var array chms_id => stored hash */
	public $store = [];

	/** @var array */
	public $pushed = [];

	/** @var array */
	public $removed = [];

	/** @var bool */
	public $dispatched = false;

	/** @var mixed What dispatch() should return ( [] by default, or a WP_Error ). */
	public $dispatch_result = [];

	/** @var array|null null = update_store() never called */
	public $store_updated = null;

	public function get_store( $group = null ) {
		return $this->store;
	}

	public function update_store( $items, $group = null ) {
		$this->store_updated = $items;
	}

	public function push_to_queue( $data ) {
		$this->pushed[] = $data;
		return $this;
	}

	public function remove_item( $chms_id ) {
		$this->removed[] = $chms_id;
	}

	public function is_locked( $chms_id ) {
		return false;
	}

	public function save() {
		return $this;
	}

	public function dispatch() {
		$this->dispatched = true;
		return $this->dispatch_result;
	}

	public function update_item( $item ) {}

	public function register_taxonomy( $taxonomy, $args ) {}
}

/**
 * @covers \CP_Sync\Integrations\Integration::process
 */
class IntegrationRemovalGuardTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'apply_filters' )->alias(
			static fn( $tag, $value ) => $value
		);
		Functions\when( 'is_wp_error' )->alias(
			static fn( $thing ) => $thing instanceof \WP_Error
		);

		// process() logs through the plugin singleton; give it a no-op logger.
		$plugin          = new class { public $logging; };
		$plugin->logging = new class { public function log( $message ) {} };
		Functions\when( 'cp_sync' )->justReturn( $plugin );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function integration( array $store = [] ): RemovalGuardIntegration {
		$integration = ( new ReflectionClass( RemovalGuardIntegration::class ) )->newInstanceWithoutConstructor();
		$integration->store = $store;
		return $integration;
	}

	public function test_zero_items_with_tracked_imports_aborts_untouched() {
		$integration = $this->integration( [ 'evt_1' => 'hash1', 'evt_2' => 'hash2' ] );

		$integration->process( [] );

		$this->assertSame( [], $integration->removed, 'Nothing may be removed on a zero-item fetch' );
		$this->assertSame( [], $integration->pushed, 'Nothing may be queued on a zero-item fetch' );
		$this->assertFalse( $integration->dispatched, 'The queue must not be dispatched' );
		$this->assertNull( $integration->store_updated, 'The store must be left intact' );
	}

	public function test_zero_items_with_empty_store_is_a_legitimate_initial_state() {
		// Nothing tracked, nothing fetched — a fresh install syncing an empty ChMS.
		$integration = $this->integration( [] );

		$integration->process( [] );

		$this->assertSame( [], $integration->removed );
		$this->assertTrue( $integration->dispatched, 'A normal (empty) run still completes' );
	}

	public function test_dispatch_returning_wp_error_does_not_break_the_run() {
		// A non-blocking dispatch can return WP_Error (blocked loopback, busy
		// worker pool) — process() must log-and-complete, never throw, because
		// the health-check cron can still pick the queue up.
		$integration = $this->integration();
		$integration->dispatch_result = new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );

		$integration->process( [ [ 'chms_id' => 'evt_1' ] ] );

		$this->assertTrue( $integration->dispatched );
		$this->assertNotNull( $integration->store_updated, 'The run completes normally after a dispatch error' );
	}

	public function test_fetched_items_still_remove_genuine_leftovers() {
		$integration = $this->integration( [ 'evt_1' => 'hash1', 'evt_gone' => 'hash2' ] );

		$integration->process( [ [ 'chms_id' => 'evt_1' ] ] );

		$this->assertSame( [ 'evt_gone' ], $integration->removed, 'Leftover removal must still work on a non-empty fetch' );
		$this->assertTrue( $integration->dispatched );
		$this->assertNotNull( $integration->store_updated );
	}
}
