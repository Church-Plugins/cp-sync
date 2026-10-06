<?php
/**
 * Tests for Planning Center group leaders.
 *
 * Leaders are not on the group record. They come from
 * GET /groups/v2/memberships?filter=leader&include=person, then format_group()
 * writes the name and email onto the meta CP Groups displays (`leader` and
 * `leader_email`, plus a `leaders` list for CP Groups 1.2).
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\PCO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\PCO::leaders_from_memberships
 * @covers \CP_Sync\ChMS\PCO::group_leader_meta
 * @covers \CP_Sync\ChMS\PCO::fetch_group_leaders
 * @covers \CP_Sync\ChMS\PCO::format_group
 */
class FormatGroupLeadersTest extends TestCase {

	/** @var array */
	private $logged = [];

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->logged = [];
		$logged       = &$this->logged;

		$logger          = new class( $logged ) {
			public $sink;
			public function __construct( &$sink ) {
				$this->sink = &$sink;
			}
			public function log( $message ) {
				$this->sink[] = $message;
			}
		};
		$plugin          = new class {
			public $logging;
		};
		$plugin->logging = $logger;

		Functions\when( 'cp_sync' )->justReturn( $plugin );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @return PCO
	 */
	private function makePco() {
		return ( new \ReflectionClass( PCO::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Chainable stand-in for PlanningCenterAPI. Records the query and returns a canned payload.
	 *
	 * @param mixed $response Value get() returns.
	 * @param mixed $error    Value errorMessage() returns.
	 * @return object
	 */
	private function fakeApi( $response, $error = null ) {
		return new class( $response, $error ) {
			public $calls = [];
			public $response;
			public $error;

			public function __construct( $response, $error ) {
				$this->response = $response;
				$this->error    = $error;
			}

			public function module( $module ) {
				$this->calls[] = [ 'module', $module ];
				return $this;
			}

			public function table( $table ) {
				$this->calls[] = [ 'table', $table ];
				return $this;
			}

			public function id( $id ) {
				$this->calls[] = [ 'id', $id ];
				return $this;
			}

			public function associations( $associations ) {
				$this->calls[] = [ 'associations', $associations ];
				return $this;
			}

			public function includes( $includes ) {
				$this->calls[] = [ 'includes', $includes ];
				return $this;
			}

			public function filter( $filter ) {
				$this->calls[] = [ 'filter', $filter ];
				return $this;
			}

			public function get() {
				$this->calls[] = [ 'get' ];
				return $this->response;
			}

			public function errorMessage() {
				return $this->error;
			}
		};
	}

	/**
	 * Memberships payload: two leaders on group 10 (one email-less), a member, a duplicate, and a leader whose person was not included.
	 *
	 * @return array
	 */
	private function membershipsPayload() {
		return [
			'data'     => [
				[
					'type'          => 'Membership',
					'id'            => '1',
					'attributes'    => [ 'role' => 'leader' ],
					'relationships' => [
						'group'  => [ 'data' => [ 'type' => 'Group', 'id' => '10' ] ],
						'person' => [ 'data' => [ 'type' => 'Person', 'id' => '7' ] ],
					],
				],
				[
					'type'          => 'Membership',
					'id'            => '2',
					'attributes'    => [ 'role' => 'leader' ],
					'relationships' => [
						'group'  => [ 'data' => [ 'type' => 'Group', 'id' => '10' ] ],
						'person' => [ 'data' => [ 'type' => 'Person', 'id' => '8' ] ],
					],
				],
				// Same leader reported twice must not be stored twice.
				[
					'type'          => 'Membership',
					'id'            => '3',
					'attributes'    => [ 'role' => 'Leader' ],
					'relationships' => [
						'group'  => [ 'data' => [ 'type' => 'Group', 'id' => '10' ] ],
						'person' => [ 'data' => [ 'type' => 'Person', 'id' => '7' ] ],
					],
				],
				[
					'type'          => 'Membership',
					'id'            => '4',
					'attributes'    => [ 'role' => 'member' ],
					'relationships' => [
						'group'  => [ 'data' => [ 'type' => 'Group', 'id' => '10' ] ],
						'person' => [ 'data' => [ 'type' => 'Person', 'id' => '9' ] ],
					],
				],
				[
					'type'          => 'Membership',
					'id'            => '5',
					'attributes'    => [ 'role' => 'leader' ],
					'relationships' => [
						'group'  => [ 'data' => [ 'type' => 'Group', 'id' => '11' ] ],
						'person' => [ 'data' => [ 'type' => 'Person', 'id' => '404' ] ],
					],
				],
				[
					'type'          => 'Membership',
					'id'            => '6',
					'attributes'    => [ 'role' => 'leader' ],
					'relationships' => [
						'person' => [ 'data' => [ 'type' => 'Person', 'id' => '7' ] ],
					],
				],
			],
			'included' => [
				[
					'type'       => 'Person',
					'id'         => '7',
					'attributes' => [
						'first_name'      => 'Jane',
						'last_name'       => 'Doe',
						'email_addresses' => [
							[ 'address' => 'jane.other@example.com', 'primary' => false, 'location' => 'Work' ],
							[ 'address' => 'jane@example.com', 'primary' => true, 'location' => 'Home' ],
						],
					],
				],
				[
					'type'       => 'Person',
					'id'         => '8',
					'attributes' => [
						'first_name'      => 'John',
						'last_name'       => 'Smith',
						'email_addresses' => [],
					],
				],
				[
					'type'       => 'Person',
					'id'         => '9',
					'attributes' => [
						'first_name'      => 'Member',
						'last_name'       => 'Only',
						'email_addresses' => [
							[ 'address' => 'member@example.com', 'primary' => true ],
						],
					],
				],
			],
		];
	}

	public function test_parses_leaders_and_skips_members_and_missing_people() {
		$payload = $this->membershipsPayload();
		$by_group = PCO::leaders_from_memberships( $payload['data'], $payload['included'] );

		$this->assertSame(
			[
				[
					'name'  => 'Jane Doe',
					'email' => 'jane@example.com',
				],
				[
					'name'  => 'John Smith',
					'email' => '',
				],
			],
			$by_group['10']
		);
		$this->assertArrayNotHasKey( '11', $by_group );
		$this->assertCount( 1, $by_group );
	}

	public function test_empty_and_non_array_payloads_have_no_leaders() {
		$this->assertSame( [], PCO::leaders_from_memberships( [], [] ) );
		$this->assertSame( [], PCO::leaders_from_memberships( null, null ) );
		$this->assertSame(
			[
				'leader'       => '',
				'leader_email' => '',
				'leaders'      => [],
			],
			PCO::group_leader_meta( [] )
		);
	}

	public function test_leader_meta_joins_names_and_uses_the_first_available_email() {
		$meta = PCO::group_leader_meta(
			[
				[ 'name' => 'John Smith', 'email' => '' ],
				[ 'name' => 'Jane Doe', 'email' => 'jane@example.com' ],
				[ 'name' => '', 'email' => '' ],
				'not-a-row',
			]
		);

		$this->assertSame( 'John Smith, Jane Doe', $meta['leader'] );
		$this->assertSame( 'jane@example.com', $meta['leader_email'] );
		$this->assertSame(
			[
				[ 'name' => 'John Smith', 'email' => '' ],
				[ 'name' => 'Jane Doe', 'email' => 'jane@example.com' ],
			],
			$meta['leaders']
		);
	}

	public function test_fetch_uses_one_leader_memberships_request() {
		$payload = $this->membershipsPayload();
		$api     = $this->fakeApi( $payload );
		$pco     = $this->makePco();
		$pco->api = $api;

		$leaders = $pco->fetch_group_leaders();

		$this->assertSame(
			[
				[ 'module', 'groups' ],
				[ 'table', 'memberships' ],
				[ 'includes', 'person' ],
				[ 'filter', 'leader' ],
				[ 'get' ],
			],
			$api->calls
		);
		$this->assertSame( 'Jane Doe', $leaders['10'][0]['name'] );
		$this->assertSame( 'jane@example.com', $leaders['10'][0]['email'] );
		$this->assertSame( [], $this->logged );
	}

	public function test_failed_leader_fetch_syncs_without_leaders() {
		$api      = $this->fakeApi( false, [ 'errors' => [ [ 'detail' => 'nope' ] ] ] );
		$pco      = $this->makePco();
		$pco->api = $api;

		$this->assertSame( [], $pco->fetch_group_leaders() );
		$this->assertNotEmpty( $this->logged );
	}

	public function test_format_group_stores_leader_name_and_email() {
		$payload = $this->membershipsPayload();
		$pco     = $this->makePco();
		$pco->api = $this->fakeApi( [ 'data' => [] ] );

		$formatted = $pco->format_group(
			$this->group(
				'10',
				[ 'contact_email' => 'group@example.com' ],
				[
					'enrollment' => [ 'data' => [ 'type' => 'Enrollment', 'id' => '55' ] ],
					'group_type' => [ 'data' => [ 'type' => 'GroupType', 'id' => '3' ] ],
				]
			),
			[
				'relational_data'  => [
					'Enrollment' => [
						'55' => [
							'type'       => 'Enrollment',
							'id'         => '55',
							'attributes' => [ 'status' => 'open', 'auto_closed' => false ],
						],
					],
					'GroupType'  => [
						'3' => [
							'type'       => 'GroupType',
							'id'         => '3',
							'attributes' => [ 'name' => 'Small Group' ],
						],
					],
				],
				'leaders_by_group' => PCO::leaders_from_memberships( $payload['data'], $payload['included'] ),
			]
		);

		$this->assertSame( 'Jane Doe, John Smith', $formatted['meta_input']['leader'] );
		$this->assertSame( 'jane@example.com', $formatted['meta_input']['leader_email'] );
		$this->assertSame(
			[
				[ 'name' => 'Jane Doe', 'email' => 'jane@example.com' ],
				[ 'name' => 'John Smith', 'email' => '' ],
			],
			$formatted['meta_input']['leaders']
		);
		$this->assertArrayNotHasKey( 'is_group_full', $formatted['meta_input'] );
		$this->assertSame( [ '3' ], $formatted['tax_input']['cp_group_type'] );
	}

	public function test_format_group_without_leaders_or_related_records() {
		$pco      = $this->makePco();
		$pco->api = $this->fakeApi( [ 'data' => [] ] );

		$formatted = $pco->format_group(
			$this->group(
				'20',
				[ 'contact_email' => 'group@example.com' ],
				[
					'enrollment' => [ 'data' => [ 'type' => 'Enrollment', 'id' => 'missing-enrollment' ] ],
					'group_type' => [ 'data' => [ 'type' => 'GroupType', 'id' => 'missing-type' ] ],
					'location'   => [ 'data' => null ],
				]
			),
			[
				'relational_data'  => [],
				'leaders_by_group' => [],
			]
		);

		$this->assertSame( '', $formatted['meta_input']['leader'] );
		$this->assertSame( '', $formatted['meta_input']['leader_email'] );
		$this->assertArrayNotHasKey( 'leaders', $formatted['meta_input'] );
		$this->assertArrayNotHasKey( 'is_group_full', $formatted['meta_input'] );
		$this->assertArrayNotHasKey( 'cp_group_type', $formatted['tax_input'] );
		$this->assertArrayNotHasKey( 'location', $formatted['meta_input'] );
	}

	public function test_format_group_marks_closed_enrollment_full() {
		$pco      = $this->makePco();
		$pco->api = $this->fakeApi( [ 'data' => [] ] );

		$formatted = $pco->format_group(
			$this->group(
				'30',
				[],
				[
					'enrollment' => [ 'data' => [ 'type' => 'Enrollment', 'id' => '9' ] ],
				]
			),
			[
				'relational_data' => [
					'Enrollment' => [
						'9' => [
							'type'       => 'Enrollment',
							'id'         => '9',
							'attributes' => [ 'status' => 'closed', 'auto_closed' => false ],
						],
					],
				],
			]
		);

		$this->assertSame( 'on', $formatted['meta_input']['is_group_full'] );
		$this->assertSame( '', $formatted['meta_input']['leader'] );
	}

	/**
	 * Minimal group resource. Related records are optional and may be absent.
	 *
	 * @param string $id             PCO group id.
	 * @param array  $attributes     Extra attributes.
	 * @param array  $relationships  Relationship linkages.
	 * @return array
	 */
	private function group( $id, $attributes = [], $relationships = [] ) {
		return [
			'type'          => 'Group',
			'id'            => $id,
			'attributes'    => array_merge(
				[
					'name'                          => 'Tuesday Group',
					'description'                   => 'A group',
					'created_at'                    => '2024-06-01T15:00:00Z',
					'archived_at'                   => '',
					'header_image'                  => [ 'original' => '' ],
					'public_church_center_web_url'  => 'https://example.org/groups/tuesday',
				],
				$attributes
			),
			'relationships' => $relationships,
		];
	}
}
