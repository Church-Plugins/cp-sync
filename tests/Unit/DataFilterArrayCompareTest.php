<?php
/**
 * List-valued filter compares, including a missing Category relationship.
 *
 * A positive compare passes when any value matches, so an empty list fails.
 * A negative compare passes only when no value matches, so an empty list passes.
 * Is Empty and Is Not Empty look at the whole list. A null or omitted value is
 * an empty list. Scalars stay scalars.
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
class DataFilterArrayCompareTest extends TestCase {

	const MISSING = "\0missing";

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
		Functions\when( 'wp_list_pluck' )->alias(
			static function ( $list, $field ) {
				$values = array();

				foreach ( $list as $row ) {
					if ( is_array( $row ) ) {
						$values[] = $row[ $field ] ?? null;
					} elseif ( is_object( $row ) ) {
						$values[] = $row->$field ?? null;
					} else {
						$values[] = null;
					}
				}

				return $values;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @dataProvider compares
	 */
	public function test_every_compare_against_lists_missing_and_scalars( $compare, $expected, $value, $passes ) {
		$filter = new DataFilter(
			'all',
			array( array( 'selector' => 'field', 'compare' => $compare, 'value' => $expected ) ),
			array( 'field' => array( 'path' => 'field' ) )
		);

		$item = self::MISSING === $value ? array() : array( 'field' => $value );

		$this->assertSame( $passes, $filter->check( $item ) );
	}

	public function compares() {
		$in = array( array( 'value' => 'internal' ) );
		$rows = array();

		$list_cases = array(
			'is'              => array( 'internal', array( 'internal' ), array( 'internal', 'youth' ), array( 'youth' ), true, true, false, false, false ),
			'is_not'          => array( 'internal', array( 'internal' ), array( 'internal', 'youth' ), array( 'youth' ), false, false, true, true, true ),
			'contains'        => array( 'tern', array( 'internal' ), array( 'internal', 'youth' ), array( 'youth' ), true, true, false, false, false ),
			'does_not_contain' => array( 'tern', array( 'internal' ), array( 'internal', 'youth' ), array( 'youth' ), false, false, true, true, true ),
			'is_in'           => array( $in, array( 'internal' ), array( 'internal', 'youth' ), array( 'youth' ), true, true, false, false, false ),
			'is_not_in'       => array( $in, array( 'internal' ), array( 'internal', 'youth' ), array( 'youth' ), false, false, true, true, true ),
			'is_greater_than' => array( 5, array( 10 ), array( 10, 3 ), array( 3 ), true, true, false, false, false ),
			'is_less_than'    => array( 5, array( 10 ), array( 10, 3 ), array( 3 ), false, true, true, false, false ),
			'is_empty'        => array( null, array( 'internal' ), array( 'internal', 'youth' ), array( 'youth' ), false, false, false, true, true ),
			'is_not_empty'    => array( null, array( 'internal' ), array( 'internal', 'youth' ), array( 'youth' ), true, true, true, false, false ),
		);

		foreach ( $list_cases as $compare => $spec ) {
			list( $expected, $only_x, $both, $only_y, $pass_x, $pass_both, $pass_y, $pass_empty, $pass_missing ) = $spec;
			$rows[] = array( $compare, $expected, $only_x, $pass_x );
			$rows[] = array( $compare, $expected, $both, $pass_both );
			$rows[] = array( $compare, $expected, $only_y, $pass_y );
			$rows[] = array( $compare, $expected, array(), $pass_empty );
			$rows[] = array( $compare, $expected, self::MISSING, $pass_missing );
		}

		$scalars = array(
			array( 'is', 'internal', 'internal', true ),
			array( 'is', 'internal', 'youth', false ),
			array( 'is', false, false, true ),
			array( 'is', 0, 0, true ),
			array( 'is_not', 'internal', 'youth', true ),
			array( 'is_not', 'internal', 'internal', false ),
			array( 'contains', 'tern', 'internal', true ),
			array( 'contains', 'tern', 'youth', false ),
			array( 'does_not_contain', 'tern', 'youth', true ),
			array( 'does_not_contain', 'tern', 'internal', false ),
			array( 'is_in', $in, 'internal', true ),
			array( 'is_in', $in, 'youth', false ),
			array( 'is_not_in', $in, 'youth', true ),
			array( 'is_not_in', $in, 'internal', false ),
			array( 'is_greater_than', 5, 10, true ),
			array( 'is_greater_than', 5, 3, false ),
			array( 'is_greater_than', 5, 5, false ),
			array( 'is_greater_than', -1, 0, true ),
			array( 'is_less_than', 5, 3, true ),
			array( 'is_less_than', 5, 10, false ),
			array( 'is_less_than', 5, 5, false ),
			array( 'is_empty', null, '', true ),
			array( 'is_empty', null, 'internal', false ),
			array( 'is_empty', null, 0, true ),
			array( 'is_empty', null, '0', true ),
			array( 'is_empty', null, false, true ),
			array( 'is_not_empty', null, 'internal', true ),
			array( 'is_not_empty', null, '', false ),
			array( 'is_not_empty', null, 0, false ),
		);

		return array_merge( $rows, $scalars );
	}

	public function test_category_issue_table() {
		$this->assertFalse( $this->category( 'is_not', 'internal', $this->categories( array( 'internal' ) ) ) );
		$this->assertFalse( $this->category( 'is_not', 'internal', $this->categories( array( 'internal', 'youth' ) ) ) );
		$this->assertTrue( $this->category( 'is_not', 'internal', $this->categories( array() ) ) );
		$this->assertTrue( $this->category( 'is_not', 'internal', array() ) );

		$in = array( array( 'value' => 'internal' ) );
		$this->assertFalse( $this->category( 'is_not_in', $in, $this->categories( array( 'internal' ) ) ) );
		$this->assertFalse( $this->category( 'is_not_in', $in, $this->categories( array( 'internal', 'youth' ) ) ) );
		$this->assertTrue( $this->category( 'is_not_in', $in, $this->categories( array() ) ) );
		$this->assertTrue( $this->category( 'is_not_in', $in, array() ) );

		$this->assertFalse( $this->category( 'is_empty', null, $this->categories( array( 'internal' ) ) ) );
		$this->assertTrue( $this->category( 'is_empty', null, $this->categories( array() ) ) );
		$this->assertTrue( $this->category( 'is_empty', null, array() ) );
		$this->assertTrue(
			$this->category(
				'is_empty',
				null,
				array( 'relationships' => array( 'categories' => array( 'data' => null ) ) )
			)
		);

		$this->assertTrue( $this->category( 'is_not_empty', null, $this->categories( array( 'youth' ) ) ) );
		$this->assertFalse( $this->category( 'is_not_empty', null, array() ) );
	}

	/**
	 * @param string $compare  Compare key.
	 * @param mixed  $expected Expected value.
	 * @param array  $item     Signup-shaped item.
	 * @return bool
	 */
	private function category( $compare, $expected, array $item ) {
		$filter = new DataFilter(
			'all',
			array( array( 'selector' => 'registration_category', 'compare' => $compare, 'value' => $expected ) ),
			array(
				'registration_category' => array(
					'path'   => 'relationships.categories.data',
					'format' => static function ( $value ) {
						return wp_list_pluck( $value, 'id' );
					},
				),
			)
		);

		return $filter->check( $item );
	}

	/**
	 * @param string[] $ids Category ids.
	 * @return array
	 */
	private function categories( array $ids ) {
		$data = array();

		foreach ( $ids as $id ) {
			$data[] = array(
				'type' => 'Category',
				'id'   => $id,
			);
		}

		return array(
			'relationships' => array(
				'categories' => array(
					'data' => $data,
				),
			),
		);
	}
}
