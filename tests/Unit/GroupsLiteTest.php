<?php
/**
 * Groups Lite: registration, handover, leader names, and the shortcode.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\PCO;
use CP_Sync\Groups\Display;
use CP_Sync\Groups\GroupMeta;
use CP_Sync\Groups\Lite;
use CP_Sync\Integrations\CP_Groups;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @covers \CP_Sync\Groups\Lite
 * @covers \CP_Sync\Groups\Display
 * @covers \CP_Sync\Integrations\CP_Groups::leader_storage_version
 */
class GroupsLiteTest extends TestCase {

	/**
	 * @var array<string,mixed>
	 */
	private $options = [];

	/**
	 * @var int
	 */
	private $flushes = 0;

	/**
	 * @var array<string,mixed>|null
	 */
	private $registered_post_type = null;

	/**
	 * @var array<string,array>
	 */
	private $registered_taxes = [];

	/**
	 * @var bool
	 */
	private $type_exists = false;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', sys_get_temp_dir() . '/wp/' );
		}

		require_once dirname( __DIR__, 2 ) . '/includes/ChurchPlugins/Exception.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ChurchPlugins/Setup/PostTypes/PostType.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ChurchPlugins/Setup/Taxonomies/Taxonomy.php';

		$this->options              = [];
		$this->flushes              = 0;
		$this->registered_post_type = null;
		$this->registered_taxes     = [];
		$this->type_exists          = false;
		$this->reset_registered_flag();
		$this->install_wordpress();
	}

	protected function tearDown(): void {
		$this->reset_registered_flag();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_registers_group_post_type_and_taxonomies_when_cp_groups_is_absent() {
		Lite::get_instance()->on_init( false );

		$this->assertSame( 'cp_group', $this->registered_post_type['type'] );
		$this->assertSame( 'groups', $this->registered_post_type['args']['rewrite']['slug'] );
		$this->assertFalse( $this->registered_post_type['args']['rewrite']['with_front'] );
		$this->assertSame( 'groups', $this->registered_post_type['args']['has_archive'] );
		$this->assertContains( 'thumbnail', $this->registered_post_type['args']['supports'] );

		$this->assertSame( [ 'cp_group' ], $this->registered_taxes['cp_group_type']['objects'] );
		$this->assertSame( 'types', $this->registered_taxes['cp_group_type']['args']['rewrite']['slug'] );
		$this->assertSame( [ 'cp_group' ], $this->registered_taxes['cp_group_category']['objects'] );
		$this->assertSame( 'categories', $this->registered_taxes['cp_group_category']['args']['rewrite']['slug'] );
		$this->assertSame( [ 'cp_group' ], $this->registered_taxes['cp_group_life_stage']['objects'] );
		$this->assertSame( 'life stages', $this->registered_taxes['cp_group_life_stage']['args']['rewrite']['slug'] );
		$this->assertTrue( Lite::is_registered() );
	}

	public function test_does_not_register_when_cp_groups_is_active() {
		$this->options[ Lite::REWRITE_STATE_OPTION ] = 'lite';

		Lite::get_instance()->on_init( true );

		$this->assertNull( $this->registered_post_type );
		$this->assertSame( [], $this->registered_taxes );
		$this->assertFalse( Lite::is_registered() );
		$this->assertSame( 'cp-groups', $this->options[ Lite::REWRITE_STATE_OPTION ] );
		$this->assertSame( 1, $this->flushes );
	}

	public function test_does_not_register_when_the_post_type_already_exists() {
		$this->type_exists = true;

		Lite::get_instance()->on_init( false );

		$this->assertNull( $this->registered_post_type );
		$this->assertSame( 0, $this->flushes );
		$this->assertArrayNotHasKey( Lite::REWRITE_STATE_OPTION, $this->options );
	}

	public function test_does_not_register_when_groups_sync_is_off() {
		// Events-only site: Sync Groups is off, even if the display toggle is on.
		$this->options['cp_sync_settings']     = [
			'chms'                   => 'pco',
			'displayGroupsWithSync'  => true,
		];
		$this->options['cp_sync_pco_settings'] = [
			'connect' => [
				'sync_groups' => false,
				'sync_events' => true,
			],
		];

		Lite::get_instance()->on_init( false );

		$this->assertNull( $this->registered_post_type );
		$this->assertFalse( Lite::is_registered() );
		$this->assertSame( 'off', $this->options[ Lite::REWRITE_STATE_OPTION ] );
	}

	public function test_upgrade_with_groups_sync_off_defaults_display_off() {
		// Existing install, display key never saved, Groups sync explicitly off.
		$this->options['cp_sync_settings']     = [
			'chms'           => 'pco',
			'updateInterval' => 'daily',
		];
		$this->options['cp_sync_pco_settings'] = [
			'connect' => [
				'sync_groups' => false,
			],
		];

		$this->assertTrue( Lite::is_existing_install() );
		$this->assertFalse( Lite::groups_sync_is_on() );
		$this->assertFalse( Lite::display_default() );
		$this->assertFalse( Lite::is_display_enabled() );

		Lite::get_instance()->on_init( false );

		$this->assertNull( $this->registered_post_type );
		$this->assertFalse( Lite::is_registered() );
	}

	public function test_existing_install_registers_when_sync_and_display_are_on() {
		$this->options['cp_sync_settings']     = [
			'chms' => 'ccb',
		];
		$this->options['cp_sync_ccb_settings'] = [
			'connect' => [
				'sync_groups' => true,
			],
		];

		$this->assertTrue( Lite::display_default() );
		$this->assertTrue( Lite::should_register() );

		Lite::get_instance()->on_init( false );

		$this->assertSame( 'cp_group', $this->registered_post_type['type'] );
		$this->assertSame( 'groups', $this->registered_post_type['args']['has_archive'] );
		$this->assertTrue( Lite::is_registered() );
	}

	public function test_does_not_register_when_display_is_off() {
		$this->options['cp_sync_settings']           = [ 'displayGroupsWithSync' => false ];
		$this->options[ Lite::REWRITE_STATE_OPTION ] = 'lite';

		Lite::get_instance()->on_init( false );

		$this->assertNull( $this->registered_post_type );
		$this->assertSame( 'off', $this->options[ Lite::REWRITE_STATE_OPTION ] );
		$this->assertSame( 1, $this->flushes );
	}

	public function test_recording_an_existing_cp_groups_site_does_not_flush() {
		Lite::get_instance()->on_init( true );

		$this->assertSame( 0, $this->flushes );
		$this->assertSame( 'cp-groups', $this->options[ Lite::REWRITE_STATE_OPTION ] );
		$this->assertNull( $this->registered_post_type );
	}

	public function test_flushes_rewrite_rules_once_when_lite_turns_on() {
		$lite = Lite::get_instance();

		$lite->on_init( false );
		$lite->on_init( false );

		$this->assertSame( 'lite', $this->options[ Lite::REWRITE_STATE_OPTION ] );
		$this->assertSame( 1, $this->flushes );
	}

	public function test_leader_names_prefer_rows_then_fall_back_to_legacy_leader() {
		$this->assertSame(
			[ 'Ada Lovelace', 'Grace Hopper' ],
			Display::leader_names(
				[
					[
						'id'    => '',
						'name'  => 'Ada Lovelace',
						'email' => 'ada@example.com',
					],
					[
						'id'    => 4,
						'name'  => 'Grace Hopper',
						'email' => 'grace@example.com',
					],
				],
				'Legacy Leader'
			)
		);

		$this->assertSame(
			[ 'Grace Hopper' ],
			Display::leader_names(
				[
					[
						'id'    => 4,
						'name'  => '',
						'email' => 'only-email@example.com',
					],
				],
				'Grace Hopper'
			)
		);

		$this->assertSame(
			[ 'Jane Doe, John Smith' ],
			Display::leader_names( [], 'Jane Doe, John Smith' )
		);

		$this->assertSame( [], Display::leader_names( [], '' ) );
	}

	public function test_rendered_group_never_outputs_leader_emails() {
		$html = Display::render_group(
			[
				'title'            => 'Young Adults <script>',
				'permalink'        => 'https://church.example/groups/young-adults',
				'image'            => 'https://church.example/photo.jpg',
				'content'          => 'Meet in the hall.',
				'time_desc'        => 'Thursdays at 6pm',
				'location'         => 'Room 4',
				'leaders'          => [
					[
						'id'    => '',
						'name'  => 'Ada Lovelace',
						'email' => 'ada@example.com',
					],
				],
				'leader'           => 'Should Not Show',
				'leader_email'     => 'secret@example.com',
				'registration_url' => 'https://church.example/register',
				'public_url'       => 'https://church.example/public',
			]
		);

		$this->assertStringContainsString( 'Ada Lovelace', $html );
		$this->assertStringNotContainsString( 'Should Not Show', $html );
		$this->assertStringNotContainsString( 'ada@example.com', $html );
		$this->assertStringNotContainsString( 'secret@example.com', $html );
		$this->assertStringNotContainsString( 'only-email@example.com', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'Thursdays at 6pm', $html );
		$this->assertStringContainsString( 'Room 4', $html );
		$this->assertStringContainsString( 'href="https://church.example/register"', $html );
		$this->assertStringContainsString( '>Register<', $html );
		$this->assertStringNotContainsString( 'Learn more', $html );

		$fallback = Display::render_group(
			[
				'title'            => 'Prayer',
				'content'          => 'Weekly prayer.',
				'leaders'          => [],
				'leader'           => 'Grace Hopper',
				'leader_email'     => 'grace@example.com',
				'registration_url' => 'mailto:grace@example.com',
				'public_url'       => 'https://church.example/prayer',
			]
		);

		$this->assertStringContainsString( 'Grace Hopper', $fallback );
		$this->assertStringNotContainsString( 'grace@example.com', $fallback );
		$this->assertStringContainsString( 'href="https://church.example/prayer"', $fallback );
		$this->assertStringContainsString( '>Learn more<', $fallback );
		$this->assertStringNotContainsString( '>Register<', $fallback );
	}

	public function test_shortcode_count_and_group_type_build_the_query() {
		$args = Display::query_args(
			[
				'count' => '6',
				'type'  => 'Small Groups',
			]
		);

		$this->assertSame( 'cp_group', $args['post_type'] );
		$this->assertSame( 6, $args['posts_per_page'] );
		$this->assertSame( 'cp_group_type', $args['tax_query'][0]['taxonomy'] );
		$this->assertSame( 'slug', $args['tax_query'][0]['field'] );
		$this->assertSame( 'small-groups', $args['tax_query'][0]['terms'] );

		$alias = Display::query_args(
			[
				'count'      => 3,
				'group_type' => 'bible-study',
			]
		);

		$this->assertSame( 3, $alias['posts_per_page'] );
		$this->assertSame( 'bible-study', $alias['tax_query'][0]['terms'] );

		$all = Display::query_args( [ 'count' => '-1' ] );

		$this->assertSame( -1, $all['posts_per_page'] );
		$this->assertArrayNotHasKey( 'tax_query', $all );

		$this->assertSame( Display::DEFAULT_COUNT, Display::query_args( [] )['posts_per_page'] );
	}

	public function test_lite_stores_the_cp_groups_1_2_leader_shape() {
		$this->assertSame( '1.2.0', CP_Groups::leader_storage_version() );
	}

	public function test_lite_sync_group_stays_on_the_archive_and_single_after_cp_groups() {
		// Planning Center does not send is_group_full when the group is open.
		$synced = [
			'post_status' => 'publish',
			'post_title'  => 'Young Adults',
			'meta_input'  => [
				'time_desc' => 'Thursdays at 6pm',
				'location'  => 'Room 4',
			],
		];

		$this->assertFalse( GroupMeta::visible_in_cp_groups_archive( $synced['meta_input'] ) );
		$this->assertFalse( GroupMeta::single_is_reachable( $synced ) );

		$synced['meta_input'] = GroupMeta::ensure( $synced['meta_input'] );

		$this->assertSame( 0, $synced['meta_input']['is_group_full'] );
		$this->assertTrue( GroupMeta::visible_in_cp_groups_archive( $synced['meta_input'] ) );
		$this->assertTrue( GroupMeta::single_is_reachable( $synced ) );

		$full = GroupMeta::ensure(
			[
				'is_group_full' => 'on',
				'time_desc'     => 'Mondays',
			]
		);
		$this->assertSame( 'on', $full['is_group_full'] );
		$this->assertFalse( GroupMeta::visible_in_cp_groups_archive( $full ) );
	}

	public function test_backfill_writes_the_not_full_default_only_when_the_flag_is_missing() {
		$updates = GroupMeta::backfill_updates(
			[
				[
					'ID'   => 5,
					'meta' => [ 'time_desc' => 'Thursdays' ],
				],
				[
					'ID'   => 6,
					'meta' => [ 'is_group_full' => 'on' ],
				],
				[
					'ID'   => 7,
					'meta' => [ 'is_group_full' => 0 ],
				],
			]
		);

		$this->assertSame( [ 5 => 0 ], $updates );
	}

	public function test_shortcode_count_is_not_replaced_by_cp_groups_per_page() {
		$args = Display::query_args( [ 'count' => '3' ] );

		$this->assertSame( 3, $args['posts_per_page'] );
		$this->assertSame( 3, $args['cp_sync_groups_limit'] );

		$query = new class( $args ) {
			/** @var array */
			private $vars;

			public function __construct( array $vars ) {
				$this->vars = $vars;
			}

			public function get( $key ) {
				return array_key_exists( $key, $this->vars ) ? $this->vars[ $key ] : null;
			}

			public function set( $key, $value ) {
				$this->vars[ $key ] = $value;
			}
		};

		// CP Groups' pre_get_posts replaces the shortcode count with Groups per page.
		$query->set( 'posts_per_page', 6 );

		Display::protect_shortcode_count( $query );

		$this->assertSame( 3, $query->get( 'posts_per_page' ) );
	}

	public function test_group_boundary_dates_do_not_pass_null_to_strtotime() {
		$empty = PCO::group_boundary_dates( null, null );

		$this->assertSame( '', $empty['start_date'] );
		$this->assertNull( $empty['end_date'] );

		$open = PCO::group_boundary_dates( '2024-06-01', '' );

		$this->assertSame( '2024-06-01', $open['start_date'] );
		$this->assertNull( $open['end_date'] );
	}

	/**
	 * @return void
	 */
	private function install_wordpress() {
		$test = $this;

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->alias(
			static function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
			}
		);
		Functions\when( 'esc_attr' )->alias(
			static function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
			}
		);
		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		Functions\when( 'wp_trim_words' )->returnArg( 1 );
		Functions\when( 'wp_kses_post' )->returnArg( 1 );
		Functions\when( 'add_shortcode' )->justReturn( null );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'sanitize_title' )->alias(
			static function ( $title ) {
				return strtolower( (string) $title );
			}
		);
		Functions\when( 'get_option' )->alias(
			static function ( $option, $default = false ) use ( $test ) {
				if ( ! array_key_exists( $option, $test->options ) ) {
					return $default;
				}

				return $test->options[ $option ];
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( $option, $value ) use ( $test ) {
				$test->options[ $option ] = $value;

				return true;
			}
		);
		Functions\when( 'flush_rewrite_rules' )->alias(
			static function () use ( $test ) {
				$test->flushes++;
			}
		);
		Functions\when( 'post_type_exists' )->alias(
			static function () use ( $test ) {
				return $test->type_exists;
			}
		);
		Functions\when( 'register_post_type' )->alias(
			static function ( $type, $args ) use ( $test ) {
				$test->registered_post_type = [
					'type' => $type,
					'args' => $args,
				];
			}
		);
		Functions\when( 'register_taxonomy' )->alias(
			static function ( $taxonomy, $objects, $args ) use ( $test ) {
				$test->registered_taxes[ $taxonomy ] = [
					'objects' => $objects,
					'args'    => $args,
				];
			}
		);
		Functions\when( 'get_posts' )->justReturn( [] );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );
	}

	/**
	 * @return void
	 */
	private function reset_registered_flag() {
		$property = new ReflectionProperty( Lite::class, 'registered' );
		$property->setAccessible( true );
		$property->setValue( null, false );
	}
}
