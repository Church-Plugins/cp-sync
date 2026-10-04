<?php
/**
 * Tests for the real Integration::update_store(), including the $retain merge.
 *
 * The store maps chms_id => hash, and ChMS IDs are numeric for both supported
 * integrations ( PCO event-instance IDs, CCB event/group IDs ), so PHP stores them
 * as INTEGER array keys. That makes the merge strategy load-bearing: array_merge()
 * renumbers integer keys 0,1,2… and would silently destroy the whole mapping — every
 * item would look changed on every sync, and the leftover pass would compare bogus
 * IDs. The union operator preserves them.
 *
 * IntegrationRemovalGuardTest deliberately stubs update_store() out to observe what
 * process() passes it; these tests exercise the real method instead, with numeric
 * chms_ids, so that class of bug cannot come back unnoticed.
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
 * Concrete stand-in that keeps the real update_store() / create_store_key().
 */
class StoreMergeIntegration extends Integration {

	public $type  = 'events';
	public $label = 'Events';

	public function update_item( $item ) {}

	public function register_taxonomy( $taxonomy, $args ) {}
}

/**
 * @covers \CP_Sync\Integrations\Integration::update_store
 */
class IntegrationStoreMergeTest extends TestCase {

	/** @var array Captured update_option() calls: key => value */
	private $options = [];

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->options = [];

		Functions\when( 'update_option' )->alias(
			function ( $key, $value, $autoload = null ) {
				$this->options[ $key ] = $value;
				return true;
			}
		);

		Functions\when( 'apply_filters' )->alias(
			static fn( $tag, $value ) => $value
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function integration(): StoreMergeIntegration {
		return ( new ReflectionClass( StoreMergeIntegration::class ) )->newInstanceWithoutConstructor();
	}

	private function stored(): array {
		return $this->options['cp_sync_store_events'] ?? [];
	}

	public function test_numeric_chms_ids_survive_as_keys() {
		$integration = $this->integration();

		$integration->update_store( [ [ 'chms_id' => '586' ], [ 'chms_id' => '1204' ] ] );

		$this->assertSame(
			[ 586, 1204 ],
			array_keys( $this->stored() ),
			'Numeric ChMS IDs must remain the store keys, never be renumbered'
		);
	}

	public function test_retained_numeric_ids_survive_the_merge() {
		// The past-event case: '900' is no longer in the fetch but was preserved, so
		// its previously recorded hash has to carry forward under its own ID.
		$integration = $this->integration();

		$integration->update_store(
			[ [ 'chms_id' => '586' ] ],
			null,
			[ '900' => 'preserved_hash' ]
		);

		$stored = $this->stored();

		$this->assertArrayHasKey( 900, $stored, 'A retained past event keeps its own ChMS ID as the key' );
		$this->assertSame( 'preserved_hash', $stored[900], 'A retained entry keeps its previously recorded hash' );
		$this->assertArrayHasKey( 586, $stored, 'Fetched items are still stored alongside retained ones' );
		$this->assertCount( 2, $stored );
	}

	public function test_fetched_hash_wins_over_a_retained_one_for_the_same_id() {
		// Defensive: an ID should never be both fetched and retained, but if it were,
		// the freshly computed hash must be the one that sticks.
		$integration = $this->integration();

		$integration->update_store(
			[ [ 'chms_id' => '586' ] ],
			null,
			[ '586' => 'stale_hash' ]
		);

		$this->assertNotSame( 'stale_hash', $this->stored()[586] );
	}

	public function test_string_chms_ids_are_unaffected() {
		$integration = $this->integration();

		$integration->update_store(
			[ [ 'chms_id' => 'evt_abc' ] ],
			null,
			[ 'evt_past' => 'preserved_hash' ]
		);

		$stored = $this->stored();

		$this->assertSame( 'preserved_hash', $stored['evt_past'] );
		$this->assertArrayHasKey( 'evt_abc', $stored );
	}

	public function test_empty_retain_leaves_the_store_untouched() {
		// The taxonomy call sites pass no $retain — they must be unaffected.
		$integration = $this->integration();

		$integration->update_store( [ [ 'chms_id' => '586' ], [ 'chms_id' => '1204' ] ], null, [] );

		$this->assertSame( [ 586, 1204 ], array_keys( $this->stored() ) );
	}

	public function test_group_parameter_still_targets_its_own_option() {
		$integration = $this->integration();

		$integration->update_store( [ [ 'chms_id' => '586' ] ], 'taxonomies' );

		$this->assertArrayHasKey( 'cp_sync_store_events_taxonomies', $this->options );
		$this->assertArrayNotHasKey( 'cp_sync_store_events', $this->options );
	}
}
