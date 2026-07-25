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
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/** Build a filter over a single selector mapped to a dot-path. */
	private function filter( string $type, array $conditions, array $config = null ): DataFilter {
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
}
