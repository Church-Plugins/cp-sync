<?php
/**
 * Tests for DataFilter's behavior with an empty (or fully-discarded) condition
 * list.
 *
 * "No conditions" must mean "no filtering". The 'any' branch of apply() used to
 * collect matches into a fresh array — with zero conditions nothing ever
 * matched, so a filter group saved as "any" with no conditions silently wiped
 * the entire feed (every preview and sync returned zero items). The same
 * wipe-out fired when every condition was discarded by applicable_conditions(),
 * e.g. the internally-appended conditions that used the key `type` instead of
 * `selector`.
 *
 * Pure logic — no WP functions are reached on these paths.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\Setup\DataFilter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Setup\DataFilter::apply
 * @covers \CP_Sync\Setup\DataFilter::check
 */
class DataFilterEmptyConditionsTest extends TestCase {

	private const ITEMS = [
		[ 'id' => '1', 'attributes' => [ 'name' => 'Alpha' ] ],
		[ 'id' => '2', 'attributes' => [ 'name' => 'Beta' ] ],
	];

	public function test_any_with_no_conditions_passes_everything() {
		$items  = self::ITEMS;
		$filter = new DataFilter( 'any', [], [], [] );

		$filter->apply( $items );

		$this->assertCount( 2, $items, "'any' with zero conditions must not filter anything" );
	}

	public function test_all_with_no_conditions_passes_everything() {
		$items  = self::ITEMS;
		$filter = new DataFilter( 'all', [], [], [] );

		$filter->apply( $items );

		$this->assertCount( 2, $items );
	}

	public function test_any_with_only_discarded_conditions_passes_everything() {
		// A condition keyed `type` (not `selector`) is dropped by
		// applicable_conditions() — the filter must then behave as unconditioned
		// rather than wiping the feed.
		$items  = self::ITEMS;
		$filter = new DataFilter(
			'any',
			[ [ 'compare' => 'is_not_empty', 'type' => 'visible_in_church_center' ] ],
			[ 'name' => [ 'path' => 'attributes.name', 'type' => 'text' ] ],
			[]
		);

		$filter->apply( $items );

		$this->assertCount( 2, $items );
	}

	public function test_check_with_no_conditions_passes_for_both_types() {
		$this->assertTrue( ( new DataFilter( 'any', [], [], [] ) )->check( self::ITEMS[0] ) );
		$this->assertTrue( ( new DataFilter( 'all', [], [], [] ) )->check( self::ITEMS[0] ) );
	}
}
