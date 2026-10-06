<?php
/**
 * Tests for CP_Groups::prepare_leader_meta().
 *
 * Released CP Groups ( 1.1 ) displays `leader` and `leader_email`. CP Groups 1.2
 * stores a `leaders` list. The 1.2 reshape must not collapse a list the ChMS
 * formatter already built.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\Integrations\CP_Groups;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Integrations\CP_Groups::prepare_leader_meta
 */
class GroupLeaderMetaTest extends TestCase {

	public function test_versions_before_1_2_keep_the_single_leader_fields() {
		$meta = [
			'leader'       => 'Jane Doe, John Smith',
			'leader_email' => 'jane@example.com',
			'leaders'      => [
				[ 'name' => 'Jane Doe', 'email' => 'jane@example.com' ],
				[ 'name' => 'John Smith', 'email' => '' ],
			],
		];

		$this->assertSame( $meta, CP_Groups::prepare_leader_meta( $meta, '1.1.18' ) );
	}

	public function test_version_1_2_keeps_every_leader_row() {
		$meta = CP_Groups::prepare_leader_meta(
			[
				'leader'       => 'Jane Doe, John Smith',
				'leader_email' => 'jane@example.com',
				'leaders'      => [
					[ 'name' => 'Jane Doe', 'email' => 'jane@example.com' ],
					[ 'name' => 'John Smith', 'email' => 'john@example.com' ],
				],
			],
			'1.2.0'
		);

		$this->assertSame(
			[
				'leaders' => [
					[ 'name' => 'Jane Doe', 'email' => 'jane@example.com' ],
					[ 'name' => 'John Smith', 'email' => 'john@example.com' ],
				],
			],
			$meta
		);
	}

	public function test_version_1_2_folds_a_single_leader_when_no_list_is_present() {
		$meta = CP_Groups::prepare_leader_meta(
			[
				'leader'       => 'Pat Lee',
				'leader_email' => 'pat@example.com',
			],
			'1.2.1'
		);

		$this->assertSame(
			[
				'leaders' => [
					[ 'name' => 'Pat Lee', 'email' => 'pat@example.com' ],
				],
			],
			$meta
		);
	}

	public function test_version_1_2_clears_leaders_when_the_fields_are_present_but_empty() {
		$meta = CP_Groups::prepare_leader_meta(
			[
				'leader'       => '',
				'leader_email' => '',
				'start_date'   => '2024-06-01',
			],
			'1.2.0'
		);

		$this->assertSame(
			[
				'start_date' => '2024-06-01',
				'leaders'    => [
					[ 'name' => '', 'email' => '' ],
				],
			],
			$meta
		);
	}

	public function test_version_1_2_does_not_invent_an_empty_row_when_leader_fields_are_absent() {
		$meta = [
			'start_date' => '2024-06-01',
			'public_url' => 'https://example.org/groups/tuesday',
		];

		$this->assertSame( $meta, CP_Groups::prepare_leader_meta( $meta, '1.2.0' ) );
	}

	public function test_version_1_2_ignores_an_empty_leaders_list() {
		$meta = CP_Groups::prepare_leader_meta(
			[
				'leader'       => 'Pat Lee',
				'leader_email' => 'pat@example.com',
				'leaders'      => [],
			],
			'1.2.0'
		);

		$this->assertSame(
			[
				'leaders' => [
					[ 'name' => 'Pat Lee', 'email' => 'pat@example.com' ],
				],
			],
			$meta
		);
	}
}
