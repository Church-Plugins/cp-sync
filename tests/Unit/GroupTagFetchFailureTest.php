<?php
/**
 * A failed group-tag request must abort the sync.
 *
 * get() returns false on a timeout or a 5xx. Treating that as an empty tag
 * list makes Integration::process_taxonomies delete every synced cps_* taxonomy
 * and its terms. Each of the three fetches ( the tag group list, the tags in
 * a selected group, and a group's own tags ) has to come back as an error
 * instead, and processing that error must leave the stored taxonomies alone.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\PCO;
use CP_Sync\Integrations\Integration;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * PCO double with settings supplied by the test.
 */
class TagFetchPco extends PCO {

	/** @var array */
	public $settings = [];

	public function get_setting( $key, $default = '', $group = false ) {
		return array_key_exists( $key, $this->settings ) ? $this->settings[ $key ] : $default;
	}
}

/**
 * Records taxonomy and term removal instead of touching WordPress.
 */
class TaxonomyGuardIntegration extends Integration {

	public $type = 'groups';

	public $id = 'cp_groups';

	public $label = 'Groups';

	/** @var array */
	public $removed_taxonomies = [];

	/** @var array */
	public $removed_terms = [];

	/** @var array */
	public $queued = [];

	/** @var bool */
	public $store_updated = false;

	/**
	 * Optional per-group store overrides. Null keeps the defaults below.
	 *
	 * @var array|null
	 */
	public $stores = null;

	public function is_locked( $chms_id ) {
		return false;
	}

	public function save() {
		return $this;
	}

	public function dispatch() {
		return [];
	}

	public function get_store( $group = null ) {
		if ( is_array( $this->stores ) && array_key_exists( $group, $this->stores ) ) {
			return $this->stores[ $group ];
		}

		if ( 'taxonomies' === $group ) {
			return [
				'cps_campus'    => [ 'chms_id' => 'cps_campus' ],
				'cp_group_type' => [ 'chms_id' => 'cp_group_type' ],
			];
		}

		if ( 'cps_campus' === $group ) {
			return [
				'55' => [ 'name' => 'North' ],
			];
		}

		if ( 'cp_group_type' === $group ) {
			return [
				'3' => [ 'name' => 'Small Group' ],
			];
		}

		if ( null === $group ) {
			return [
				'10' => 'hash',
			];
		}

		return [];
	}

	public function update_store( $items, $group = null, $retain = [] ) {
		$this->store_updated = true;
	}

	public function push_to_queue( $data ) {
		$this->queued[] = $data;
		return $this;
	}

	public function remove_term( $chms_id ) {
		$this->removed_terms[] = $chms_id;
	}

	protected function remove_taxonomy( $taxonomy ) {
		$this->removed_taxonomies[] = $taxonomy;
	}

	protected function create_taxonomy( $taxonomy ) {}

	public function update_item( $item ) {}

	public function register_taxonomy( $taxonomy, $args ) {}
}

/**
 * @covers \CP_Sync\ChMS\PCO::fetch_groups
 * @covers \CP_Sync\ChMS\PCO::format_group
 * @covers \CP_Sync\ChMS\ChMS::get_formatted_data
 */
class GroupTagFetchFailureTest extends TestCase {

	/** @var object */
	private $logger;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
		Functions\when( 'wp_list_pluck' )->alias(
			static function ( $list, $field ) {
				if ( ! is_array( $list ) ) {
					return [];
				}

				return array_column( $list, $field );
			}
		);
		// A groups-list failure that is not aborted still builds the Group Type
		// labels. Without this, that path dies here instead of reaching the
		// taxonomy assertions.
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value, ...$extra ) {
				unset( $extra );

				return $value;
			}
		);

		$plugin          = new class() {
			public $logging;
		};
		$plugin->logging = new class() {
			/** @var array */
			public $messages = [];

			public function log( $message ) {
				$this->messages[] = $message;
			}
		};
		$this->logger    = $plugin->logging;
		Functions\when( 'cp_sync' )->justReturn( $plugin );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @return array
	 */
	public function failurePayloads() {
		return [
			'timeout'      => [ 'Connection refused', 'Connection refused' ],
			'server error' => [
				[
					'errors' => [
						[
							'status' => '500',
							'detail' => 'server exploded',
						],
					],
				],
				'server exploded',
			],
		];
	}

	/**
	 * @return array
	 */
	public function groupsListFailures() {
		return [
			'timeout' => [ 'Connection refused', 'Connection refused' ],
			'false'   => [ null, 'groups request failed with no error detail' ],
			'client error' => [
				[
					'errors' => [
						[
							'status' => '404',
							'detail' => 'not found',
						],
					],
				],
				'not found',
			],
		];
	}

	/**
	 * Tag groups still succeed. The groups-list failure has to abort by itself,
	 * or the sync builds an empty Group Type list and prunes stored terms.
	 *
	 * @dataProvider groupsListFailures
	 * @param mixed  $error    Value errorMessage() returns after the groups get().
	 * @param string $expected Fragment that must survive on the ChMSError.
	 */
	public function test_groups_list_failure_keeps_existing_taxonomies( $error, $expected ) {
		$pco = $this->pco(
			[
				[ 'response' => false, 'error' => $error ],
				[ 'response' => [ 'data' => [] ], 'error' => null ],
			]
		);
		$pco->add_support(
			'groups',
			[
				'fetch_callback'  => [ $pco, 'fetch_groups' ],
				'format_callback' => [ $pco, 'format_group' ],
			]
		);

		$result      = $pco->get_formatted_data( null, 'groups' );
		$integration = ( new ReflectionClass( TaxonomyGuardIntegration::class ) )->newInstanceWithoutConstructor();
		$integration->process_formatted_data( $result );

		$this->assertSame( [], $integration->removed_terms );
		$this->assertSame( [], $integration->removed_taxonomies );
		$this->assertSame( [], $integration->queued );
		$this->assertFalse( $integration->store_updated );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'pco_fetch_error', $result->get_error_code() );
		$this->assertStringContainsString( $expected, $result->get_error_message() );
	}

	/**
	 * @dataProvider failurePayloads
	 * @param mixed  $error    Value errorMessage() returns after the failed get().
	 * @param string $expected Fragment that must survive on the ChMSError.
	 */
	public function test_tag_groups_list_failure_keeps_existing_taxonomies( $error, $expected ) {
		$pco = $this->pco(
			[
				[ 'response' => $this->groupsResponse(), 'error' => null ],
				[ 'response' => false, 'error' => $error ],
			]
		);
		$pco->add_support(
			'groups',
			[
				'fetch_callback'  => [ $pco, 'fetch_groups' ],
				'format_callback' => [ $pco, 'format_group' ],
			]
		);

		$result = $pco->get_formatted_data( null, 'groups' );

		$this->assertTaxonomiesKept( $result, $expected );
	}

	/**
	 * @dataProvider failurePayloads
	 * @param mixed  $error    Value errorMessage() returns after the failed get().
	 * @param string $expected Fragment that must survive on the ChMSError.
	 */
	public function test_tag_group_tags_failure_keeps_existing_taxonomies( $error, $expected ) {
		$pco = $this->pco(
			[
				[ 'response' => $this->groupsResponse(), 'error' => null ],
				[
					'response' => [
						'data' => [
							[
								'id'         => 'tg1',
								'type'       => 'TagGroup',
								'attributes' => [ 'name' => 'Campus' ],
							],
						],
					],
					'error'    => null,
				],
				[ 'response' => false, 'error' => $error ],
			]
		);
		$pco->settings['tag_groups'] = [
			[ 'id' => 'tg1' ],
		];
		$pco->add_support(
			'groups',
			[
				'fetch_callback'  => [ $pco, 'fetch_groups' ],
				'format_callback' => [ $pco, 'format_group' ],
			]
		);

		$result = $pco->get_formatted_data( null, 'groups' );

		$this->assertTaxonomiesKept( $result, $expected );
	}

	/**
	 * @dataProvider failurePayloads
	 * @param mixed  $error    Value errorMessage() returns after the failed get().
	 * @param string $expected Fragment that must survive on the ChMSError.
	 */
	public function test_per_group_tags_failure_keeps_existing_taxonomies( $error, $expected ) {
		$pco      = $this->pco(
			[
				[ 'response' => false, 'error' => $error ],
			]
		);
		$pco->add_support(
			'groups',
			[
				'fetch_callback'  => function () {
					return [
						'items'      => [
							[
								'type'          => 'Group',
								'id'            => '10',
								'attributes'    => [
									'name'        => 'Tuesday Group',
									'created_at'  => '2024-06-01T15:00:00Z',
									'archived_at' => '',
								],
								'relationships' => [],
							],
						],
						'taxonomies' => [
							'cps_campus' => [
								'plural_label' => 'Campus',
								'single_label' => 'Campus',
								'taxonomy'     => 'cps_campus',
								'terms'        => [ '55' => 'North' ],
							],
						],
						'context'    => [
							'relational_data'  => [],
							'leaders_by_group' => [],
						],
					];
				},
				'format_callback' => [ $pco, 'format_group' ],
			]
		);

		$result = $pco->get_formatted_data( null, 'groups' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertNotSame( 'chms_error', $result->get_error_code() );
		$this->assertTaxonomiesKept( $result, $expected );
	}

	/**
	 * A successful fetch of zero groups still builds an empty Group Type list
	 * from those groups. Stored terms must survive that, and the skip is logged.
	 */
	public function test_successful_empty_group_fetch_keeps_stored_terms() {
		$pco = $this->pco(
			[
				[ 'response' => [ 'data' => [], 'included' => [] ], 'error' => null ],
				[ 'response' => [ 'data' => [] ], 'error' => null ],
			]
		);
		$pco->add_support(
			'groups',
			[
				'fetch_callback'  => [ $pco, 'fetch_groups' ],
				'format_callback' => [ $pco, 'format_group' ],
			]
		);

		$result      = $pco->get_formatted_data( null, 'groups' );
		$integration = $this->integration();
		$integration->process_formatted_data( $result );

		$this->assertIsArray( $result );
		$this->assertSame( [], $result['posts'] );
		$this->assertArrayHasKey( 'cp_group_type', $result['taxonomies'] );
		$this->assertSame( [], $result['taxonomies']['cp_group_type']['terms'] );
		$this->assertSame( [], $integration->removed_terms );
		$this->assertSame( [], $integration->removed_taxonomies );
		$this->assertFalse( $integration->store_updated );
		$this->assertTrue( $this->loggedSkip() );
	}

	/**
	 * Published-only sync. Every group of Group Type 9 is unpublished, so this
	 * fetch does not include that type. The term is still deleted site-wide,
	 * including from locked posts. Keeping those assignments is a separate
	 * decision; the empty-fetch guard does not change this case.
	 */
	public function test_group_type_unused_by_fetched_groups_is_still_removed() {
		$pco = $this->pco(
			[
				[ 'response' => $this->publishedGroupResponse(), 'error' => null ],
				[ 'response' => [ 'data' => [] ], 'error' => null ],
				[ 'response' => [ 'data' => [], 'included' => [] ], 'error' => null ],
				[ 'response' => [ 'data' => [] ], 'error' => null ],
			]
		);
		$pco->settings['visibility'] = 'public';
		$pco->add_support(
			'groups',
			[
				'fetch_callback'  => [ $pco, 'fetch_groups' ],
				'format_callback' => [ $pco, 'format_group' ],
			]
		);

		$result      = $pco->get_formatted_data( null, 'groups' );
		$integration = $this->integration();
		$integration->stores = [
			'taxonomies'    => [
				'cp_group_type' => [ 'chms_id' => 'cp_group_type' ],
			],
			'cp_group_type' => [
				'3' => [ 'name' => 'Small Group' ],
				'9' => [ 'name' => 'Youth' ],
			],
		];
		$integration->process_formatted_data( $result );

		$this->assertNotEmpty( $result['posts'] );
		$this->assertSame( [ '3' => 'Small Group' ], $result['taxonomies']['cp_group_type']['terms'] );
		$this->assertSame( [ 'cp_group_type_9' ], $integration->removed_terms );
		$this->assertFalse( $this->loggedSkip() );
	}

	/**
	 * A complete fetch that still has posts deletes a term the ChMS no longer returns.
	 */
	public function test_complete_non_empty_fetch_deletes_a_removed_term() {
		$integration         = $this->integration();
		$integration->stores = [
			'taxonomies'    => [
				'cp_group_type' => [ 'chms_id' => 'cp_group_type' ],
			],
			'cp_group_type' => [
				'3' => [ 'name' => 'Small Group' ],
				'9' => [ 'name' => 'Youth' ],
			],
		];

		$integration->process_formatted_data(
			[
				'posts'      => [
					[
						'chms_id'    => '10',
						'post_title' => 'Tuesday Group',
					],
				],
				'taxonomies' => [
					'cp_group_type' => [
						'taxonomy'     => 'cp_group_type',
						'single_label' => 'Group Type',
						'plural_label' => 'Group Types',
						'terms'        => [ '3' => 'Small Group' ],
					],
				],
			]
		);

		$this->assertSame( [ 'cp_group_type_9' ], $integration->removed_terms );
		$this->assertNotContains( 'cp_group_type_3', $integration->removed_terms );
		$this->assertFalse( $this->loggedSkip() );
	}

	/**
	 * @return TaxonomyGuardIntegration
	 */
	private function integration() {
		return ( new ReflectionClass( TaxonomyGuardIntegration::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * @return bool
	 */
	private function loggedSkip() {
		foreach ( $this->logger->messages as $message ) {
			if ( false !== strpos( $message, 'Skipping term removal' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * One published group of type 3. Type 9 is not in the payload.
	 *
	 * @return array
	 */
	private function publishedGroupResponse() {
		return [
			'data'     => [
				[
					'id'            => '10',
					'type'          => 'Group',
					'attributes'    => [
						'name'                         => 'Tuesday Group',
						'created_at'                   => '2024-06-01T15:00:00Z',
						'archived_at'                  => '',
						'description'                  => '',
						'public_church_center_web_url' => 'https://example.churchcenter.com/groups/tuesday',
					],
					'relationships' => [
						'group_type' => [
							'data' => [
								'type' => 'GroupType',
								'id'   => '3',
							],
						],
					],
				],
			],
			'included' => [
				[
					'id'         => '3',
					'type'       => 'GroupType',
					'attributes' => [ 'name' => 'Small Group' ],
				],
			],
		];
	}

	/**
	 * @param array $queue Scripted get() results, in call order.
	 * @return TagFetchPco
	 */
	private function pco( array $queue ) {
		$pco           = ( new ReflectionClass( TagFetchPco::class ) )->newInstanceWithoutConstructor();
		$pco->settings = [
			'visibility' => 'all',
			'tag_groups' => [],
			'filter'     => [],
		];
		$pco->api      = new class( $queue ) {
			public $queue = [];
			public $error;

			public function __construct( array $queue ) {
				$this->queue = $queue;
			}

			public function module( $module ) {
				return $this;
			}

			public function table( $table ) {
				return $this;
			}

			public function includes( $includes ) {
				return $this;
			}

			public function filter( $filter ) {
				return $this;
			}

			public function id( $id ) {
				return $this;
			}

			public function associations( $associations ) {
				return $this;
			}

			public function get() {
				$next = array_shift( $this->queue );
				$this->error = is_array( $next ) && array_key_exists( 'error', $next ) ? $next['error'] : null;

				return is_array( $next ) && array_key_exists( 'response', $next ) ? $next['response'] : false;
			}

			public function errorMessage() {
				return $this->error;
			}
		};

		return $pco;
	}

	/**
	 * @return array
	 */
	private function groupsResponse() {
		return [
			'data'     => [
				[
					'id'   => '10',
					'type' => 'Group',
				],
			],
			'included' => [],
		];
	}

	/**
	 * @param mixed  $result   Return value of get_formatted_data().
	 * @param string $expected Fragment of the API error that must be on the ChMSError.
	 */
	private function assertTaxonomiesKept( $result, $expected ) {
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'pco_fetch_error', $result->get_error_code() );
		$this->assertStringContainsString( $expected, $result->get_error_message() );

		$integration = ( new ReflectionClass( TaxonomyGuardIntegration::class ) )->newInstanceWithoutConstructor();
		$integration->process_formatted_data( $result );

		$this->assertSame( [], $integration->removed_taxonomies );
		$this->assertSame( [], $integration->removed_terms );
		$this->assertFalse( $integration->store_updated );
	}
}
