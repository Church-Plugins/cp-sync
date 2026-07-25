<?php
/**
 * Tests for CP_Sync\Setup\DataFilter — the engine that decides which items sync.
 *
 * Covers the pure path resolver, condition applicability, the all/any matching
 * logic, a representative set of compare operators, array-valued fields, and the
 * WP_Error paths. WordPress functions the class touches (__, is_wp_error) are
 * stubbed with Brain Monkey; WP_Error itself is stubbed in tests/bootstrap.php.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Setup\DataFilter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Setup\DataFilter
 */
class DataFilterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_wp_error' )->alias(
			static fn( $thing ) => $thing instanceof \WP_Error
		);
		Functions\when( 'wp_list_pluck' )->alias(
			static function ( $list, $field ) {
				return array_map(
					static fn( $row ) => is_array( $row ) ? ( $row[ $field ] ?? null ) : ( $row->$field ?? null ),
					$list
				);
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/** Build a filter over a single selector mapped to a dot-path. */
	private function filter( string $type, array $conditions, ?array $config = null ): DataFilter {
		$config = $config ?? [ 'field' => [ 'path' => 'field' ] ];
		return new DataFilter( $type, $conditions, $config );
	}

	private function cond( string $selector, string $compare, $value ): array {
		return [ 'selector' => $selector, 'compare' => $compare, 'value' => $value ];
	}

	/* ---------------------------------------------------------------------- get_val() */

	public function test_get_val_returns_top_level_key() {
		$f = new DataFilter();
		$this->assertSame( 'x', $f->get_val( [ 'a' => 'x' ], 'a' ) );
	}

	public function test_get_val_resolves_nested_dot_path() {
		$f = new DataFilter();
		$this->assertSame( 5, $f->get_val( [ 'a' => [ 'b' => [ 'c' => 5 ] ] ], 'a.b.c' ) );
	}

	public function test_get_val_returns_default_for_missing_path() {
		$f = new DataFilter();
		$this->assertSame( 'def', $f->get_val( [ 'a' => 1 ], 'missing', 'def' ) );
		$this->assertNull( $f->get_val( [ 'a' => [ 'b' => 1 ] ], 'a.z' ) );
	}

	public function test_get_val_returns_scalar_data_unchanged() {
		$f = new DataFilter();
		$this->assertSame( 'scalar', $f->get_val( 'scalar', 'a.b' ) );
	}

	/* ------------------------------------------------------ applicable_conditions() */

	public function test_applicable_conditions_drops_selectors_absent_from_config() {
		$f    = new DataFilter();
		$kept = $f->applicable_conditions(
			[ [ 'selector' => 'keep' ], [ 'selector' => 'ghost' ] ],
			[ 'keep' => [ 'path' => 'k' ] ]
		);

		$this->assertCount( 1, $kept );
		$this->assertSame( 'keep', $kept[0]['selector'] );
	}

	/* ------------------------------------------------------------------- check() all */

	public function test_all_passes_when_single_condition_matches() {
		$f = $this->filter( 'all', [ $this->cond( 'field', 'is', 'Hello' ) ] );
		$this->assertTrue( $f->check( [ 'field' => 'Hello' ] ) );
	}

	public function test_all_fails_when_condition_does_not_match() {
		$f = $this->filter( 'all', [ $this->cond( 'field', 'is', 'Hello' ) ] );
		$this->assertFalse( $f->check( [ 'field' => 'Goodbye' ] ) );
	}

	public function test_all_requires_every_condition_to_pass() {
		$config = [
			'a' => [ 'path' => 'a' ],
			'b' => [ 'path' => 'b' ],
		];
		$f = new DataFilter(
			'all',
			[ $this->cond( 'a', 'is', 'x' ), $this->cond( 'b', 'is', 'y' ) ],
			$config
		);

		$this->assertTrue( $f->check( [ 'a' => 'x', 'b' => 'y' ] ) );
		$this->assertFalse( $f->check( [ 'a' => 'x', 'b' => 'nope' ] ) );
	}

	public function test_all_with_no_conditions_matches_everything() {
		$f = $this->filter( 'all', [] );
		$this->assertTrue( $f->check( [ 'field' => 'anything' ] ) );
	}

	/* ------------------------------------------------------------------- check() any */

	public function test_any_passes_when_at_least_one_condition_matches() {
		$config = [
			'a' => [ 'path' => 'a' ],
			'b' => [ 'path' => 'b' ],
		];
		$f = new DataFilter(
			'any',
			[ $this->cond( 'a', 'is', 'x' ), $this->cond( 'b', 'is', 'y' ) ],
			$config
		);

		$this->assertTrue( $f->check( [ 'a' => 'no', 'b' => 'y' ] ) );
	}

	public function test_any_fails_when_no_condition_matches() {
		$config = [
			'a' => [ 'path' => 'a' ],
			'b' => [ 'path' => 'b' ],
		];
		$f = new DataFilter(
			'any',
			[ $this->cond( 'a', 'is', 'x' ), $this->cond( 'b', 'is', 'y' ) ],
			$config
		);

		$this->assertFalse( $f->check( [ 'a' => 'no', 'b' => 'no' ] ) );
	}

	/* --------------------------------------------------------------- compare operators */

	public function test_is_not_operator() {
		$f = $this->filter( 'all', [ $this->cond( 'field', 'is_not', 'x' ) ] );
		$this->assertTrue( $f->check( [ 'field' => 'y' ] ) );
		$this->assertFalse( $f->check( [ 'field' => 'x' ] ) );
	}

	public function test_contains_operator() {
		$f = $this->filter( 'all', [ $this->cond( 'field', 'contains', 'ell' ) ] );
		$this->assertTrue( $f->check( [ 'field' => 'Hello' ] ) );
		$this->assertFalse( $f->check( [ 'field' => 'World' ] ) );
	}

	public function test_is_empty_and_is_not_empty_operators() {
		$empty = $this->filter( 'all', [ $this->cond( 'field', 'is_empty', null ) ] );
		$this->assertTrue( $empty->check( [ 'field' => '' ] ) );
		$this->assertFalse( $empty->check( [ 'field' => 'x' ] ) );

		$not_empty = $this->filter( 'all', [ $this->cond( 'field', 'is_not_empty', null ) ] );
		$this->assertTrue( $not_empty->check( [ 'field' => 'x' ] ) );
		$this->assertFalse( $not_empty->check( [ 'field' => '' ] ) );
	}

	/* ----------------------------------------------------------------- array-valued data */

	public function test_array_valued_field_passes_when_any_element_matches() {
		$f = $this->filter( 'all', [ $this->cond( 'field', 'is', 'x' ) ] );
		$this->assertTrue( $f->check( [ 'field' => [ 'a', 'x', 'b' ] ] ) );
		$this->assertFalse( $f->check( [ 'field' => [ 'a', 'b', 'c' ] ] ) );
	}

	/* ------------------------------------------------------------------- error paths */

	public function test_missing_compare_yields_wp_error() {
		$f = new DataFilter(
			'all',
			[ [ 'selector' => 'field', 'value' => 'x' ] ], // no 'compare'
			[ 'field' => [ 'path' => 'field' ] ]
		);

		$this->assertInstanceOf( \WP_Error::class, $f->check( [ 'field' => 'x' ] ) );
	}

	public function test_missing_path_in_config_yields_wp_error() {
		// Selector is present in config (so it survives applicable_conditions) but
		// has no 'path', which passes_condition treats as an invalid filter config.
		$f = new DataFilter(
			'all',
			[ $this->cond( 'field', 'is', 'x' ) ],
			[ 'field' => [ 'label' => 'no path here' ] ]
		);

		$result = $f->check( [ 'field' => 'x' ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_filter_config', $result->get_error_code() );
	}

	/* ----------------------------------------------------- formatted compare options */

	public function test_get_formatted_compare_options_shape() {
		$options = DataFilter::get_formatted_compare_options();

		$this->assertNotEmpty( $options );
		foreach ( $options as $option ) {
			$this->assertArrayHasKey( 'value', $option );
			$this->assertArrayHasKey( 'label', $option );
			$this->assertArrayHasKey( 'type', $option );
			$this->assertArrayHasKey( 'default', $option );
		}

		// 'default' falls back to 'type' when a compare option declares no explicit default.
		$by_value = array_column( $options, null, 'value' );
		$this->assertSame( 'text', $by_value['contains']['default'] ); // contains has no explicit default
	}

	/* ------------------------------------------------------------- apply() (in place) */

	public function test_apply_all_keeps_only_items_passing_the_condition() {
		$f     = $this->filter( 'all', [ $this->cond( 'field', 'is', 'x' ) ] );
		$items = [ [ 'field' => 'x' ], [ 'field' => 'y' ], [ 'field' => 'x' ] ];

		$result = $f->apply( $items );

		$this->assertNull( $result ); // no return value on success
		$this->assertSame( [ [ 'field' => 'x' ], [ 'field' => 'x' ] ], array_values( $items ) );
	}

	public function test_apply_all_handles_consecutive_leading_removals_without_index_skew() {
		// Two leading items fail back-to-back — the case most likely to expose an
		// off-by-one in the array_splice( $items, $i--, 1 ) reindexing.
		$f     = $this->filter( 'all', [ $this->cond( 'field', 'is', 'x' ) ] );
		$items = [ [ 'field' => 'no' ], [ 'field' => 'no' ], [ 'field' => 'x' ], [ 'field' => 'no' ] ];

		$f->apply( $items );

		$this->assertSame( [ [ 'field' => 'x' ] ], array_values( $items ) );
	}

	public function test_apply_all_requires_every_condition() {
		$config = [ 'a' => [ 'path' => 'a' ], 'b' => [ 'path' => 'b' ] ];
		$f      = new DataFilter( 'all', [ $this->cond( 'a', 'is', 'x' ), $this->cond( 'b', 'is', 'y' ) ], $config );
		$items  = [
			[ 'a' => 'x', 'b' => 'y' ],  // passes both
			[ 'a' => 'x', 'b' => 'no' ], // fails b
			[ 'a' => 'no', 'b' => 'y' ], // fails a
		];

		$f->apply( $items );

		$this->assertSame( [ [ 'a' => 'x', 'b' => 'y' ] ], array_values( $items ) );
	}

	public function test_apply_any_keeps_items_passing_at_least_one_condition() {
		$config = [ 'a' => [ 'path' => 'a' ], 'b' => [ 'path' => 'b' ] ];
		$f      = new DataFilter( 'any', [ $this->cond( 'a', 'is', 'x' ), $this->cond( 'b', 'is', 'y' ) ], $config );
		$items  = [
			[ 'a' => 'x', 'b' => 'no' ],  // passes a
			[ 'a' => 'no', 'b' => 'no' ], // passes neither
			[ 'a' => 'no', 'b' => 'y' ],  // passes b
		];

		$f->apply( $items );

		$this->assertSame(
			[ [ 'a' => 'x', 'b' => 'no' ], [ 'a' => 'no', 'b' => 'y' ] ],
			array_values( $items )
		);
	}

	public function test_apply_returns_wp_error_on_invalid_condition() {
		$f     = new DataFilter( 'all', [ $this->cond( 'field', 'is', 'x' ) ], [ 'field' => [ 'label' => 'no path' ] ] );
		$items = [ [ 'field' => 'x' ] ];

		$this->assertInstanceOf( \WP_Error::class, $f->apply( $items ) );
	}

	/* ------------------------------------------------- remaining compare operators */

	public function test_numeric_comparison_operators() {
		$gt = $this->filter( 'all', [ $this->cond( 'field', 'is_greater_than', 5 ) ] );
		$this->assertTrue( $gt->check( [ 'field' => 10 ] ) );
		$this->assertFalse( $gt->check( [ 'field' => 5 ] ) );

		$lt = $this->filter( 'all', [ $this->cond( 'field', 'is_less_than', 5 ) ] );
		$this->assertTrue( $lt->check( [ 'field' => 3 ] ) );
		$this->assertFalse( $lt->check( [ 'field' => 5 ] ) );
	}

	public function test_does_not_contain_operator() {
		$f = $this->filter( 'all', [ $this->cond( 'field', 'does_not_contain', 'x' ) ] );
		$this->assertTrue( $f->check( [ 'field' => 'hello' ] ) );
		$this->assertFalse( $f->check( [ 'field' => 'xenon' ] ) );
	}

	public function test_is_in_and_is_not_in_operators() {
		$expected = [ [ 'value' => 'a' ], [ 'value' => 'b' ] ];

		$in = $this->filter( 'all', [ $this->cond( 'field', 'is_in', $expected ) ] );
		$this->assertTrue( $in->check( [ 'field' => 'a' ] ) );
		$this->assertFalse( $in->check( [ 'field' => 'z' ] ) );

		$not_in = $this->filter( 'all', [ $this->cond( 'field', 'is_not_in', $expected ) ] );
		$this->assertTrue( $not_in->check( [ 'field' => 'z' ] ) );
		$this->assertFalse( $not_in->check( [ 'field' => 'a' ] ) );
	}

	/* ----------------------------------------------------- format callback + relations */

	public function test_format_callback_transforms_value_before_compare() {
		$config = [ 'field' => [ 'path' => 'field', 'format' => static fn( $v ) => strtoupper( (string) $v ) ] ];
		$f      = new DataFilter( 'all', [ $this->cond( 'field', 'is', 'X' ) ], $config );

		$this->assertTrue( $f->check( [ 'field' => 'x' ] ) );
		$this->assertFalse( $f->check( [ 'field' => 'y' ] ) );
	}

	public function test_relational_data_resolves_through_related_record() {
		$config     = [ 'group' => [ 'path' => 'group_id', 'relation' => 'groups', 'relation_path' => 'name' ] ];
		$relational = [ 'groups' => [ 'g1' => [ 'name' => 'Youth' ], 'g2' => [ 'name' => 'Adults' ] ] ];
		$f          = new DataFilter( 'all', [ $this->cond( 'group', 'is', 'Youth' ) ], $config, $relational );

		$this->assertTrue( $f->check( [ 'group_id' => 'g1' ] ) );
		$this->assertFalse( $f->check( [ 'group_id' => 'g2' ] ) );
	}

	public function test_relational_data_missing_record_yields_wp_error() {
		$config     = [ 'group' => [ 'path' => 'group_id', 'relation' => 'groups', 'relation_path' => 'name' ] ];
		$relational = [ 'groups' => [ 'g1' => [ 'name' => 'Youth' ] ] ];
		$f          = new DataFilter( 'all', [ $this->cond( 'group', 'is', 'Youth' ) ], $config, $relational );

		$result = $f->check( [ 'group_id' => 'nonexistent' ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_filter_config', $result->get_error_code() );
	}
}
