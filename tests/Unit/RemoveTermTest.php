<?php
/**
 * Tests for Integration::remove_term().
 *
 * The ChMS id passed in is the compound `_chms_id` term meta
 * (`{taxonomy}_{chms_id}`), for example `cp_group_type_cptest1`. That string
 * finds the term. wp_delete_term() has to receive the WordPress term ID and
 * the real taxonomy slug, or the delete fails and the term is left in place.
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
 * Concrete Integration that supplies the term ids remove_term() looks up.
 */
class RemoveTermIntegration extends Integration {

	/** @var array */
	public $term_ids = [];

	public function get_chms_term_ids( ...$chms_ids ) {
		return $this->term_ids;
	}

	public function update_item( $item ) {}

	public function register_taxonomy( $taxonomy, $args ) {}
}

/**
 * @covers \CP_Sync\Integrations\Integration::remove_term
 */
class RemoveTermTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @return RemoveTermIntegration
	 */
	private function integration( array $term_ids ) {
		$integration = ( new ReflectionClass( RemoveTermIntegration::class ) )->newInstanceWithoutConstructor();
		$integration->term_ids = $term_ids;
		return $integration;
	}

	public function test_remove_term_deletes_by_term_id_and_real_taxonomy() {
		Functions\expect( 'get_term' )
			->once()
			->with( 42 )
			->andReturn( (object) [
				'term_id'  => 42,
				'taxonomy' => 'cp_group_type',
			] );
		Functions\expect( 'wp_delete_term' )
			->once()
			->with( 42, 'cp_group_type' )
			->andReturn( true );

		$this->integration( [ 42 ] )->remove_term( 'cp_group_type_cptest1' );

		// Brain Monkey checks the expectations in tearDown.
		$this->addToAssertionCount( 1 );
	}
}
