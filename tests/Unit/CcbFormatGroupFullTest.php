<?php
/**
 * Tests for CCB::format_group() mapping of the group "full" flag.
 *
 * CCB's XML client returns <full> as the strings "true" and "false". A PHP
 * truthy check treats "false" as full, which hides open groups in CP Groups.
 * format_group() touches get_option() only via get_base_url(); the instance
 * is built without its hook-registering constructor.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\CCB;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\CCB::format_group
 */
class CcbFormatGroupFullTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'get_option' )->justReturn( [] );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a CCB instance without invoking the constructor ( which registers WP hooks ).
	 *
	 * @return CCB
	 */
	private function makeCcb(): CCB {
		return ( new \ReflectionClass( CCB::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * @param array $group Raw CCB group payload.
	 * @return array
	 */
	private function format( array $group ): array {
		return $this->makeCcb()->format_group( $group, [] );
	}

	/**
	 * @return array
	 */
	private function group( array $extra = [] ): array {
		return array_merge(
			[
				'id'   => '42',
				'name' => 'Weeknight Study',
			],
			$extra
		);
	}

	public function test_string_true_syncs_as_full() {
		$formatted = $this->format( $this->group( [ 'full' => 'true' ] ) );

		$this->assertSame( 'on', $formatted['meta_input']['is_group_full'] );
	}

	public function test_string_true_ignores_case_and_whitespace() {
		$formatted = $this->format( $this->group( [ 'full' => "  TRUE\n" ] ) );

		$this->assertSame( 'on', $formatted['meta_input']['is_group_full'] );
	}

	public function test_string_false_syncs_as_not_full() {
		$formatted = $this->format( $this->group( [ 'full' => 'false' ] ) );

		$this->assertSame( 0, $formatted['meta_input']['is_group_full'] );
	}

	public function test_string_false_ignores_case_and_whitespace() {
		$formatted = $this->format( $this->group( [ 'full' => '  False  ' ] ) );

		$this->assertSame( 0, $formatted['meta_input']['is_group_full'] );
	}

	public function test_missing_full_syncs_as_not_full() {
		$formatted = $this->format( $this->group() );

		$this->assertSame( 0, $formatted['meta_input']['is_group_full'] );
	}

	public function test_empty_full_syncs_as_not_full() {
		$formatted = $this->format( $this->group( [ 'full' => '' ] ) );

		$this->assertSame( 0, $formatted['meta_input']['is_group_full'] );
	}

	public function test_whitespace_only_full_syncs_as_not_full() {
		$formatted = $this->format( $this->group( [ 'full' => '   ' ] ) );

		$this->assertSame( 0, $formatted['meta_input']['is_group_full'] );
	}
}
