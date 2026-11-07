<?php
/**
 * Event post types register only when Sync Events is on and The Events
 * Calendar is absent.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\ChMS;
use CP_Sync\Events\Display;
use CP_Sync\Events\EventsShortcode;
use CP_Sync\Events\Meta;
use CP_Sync\Events\PostTypes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Events\PostTypes::should_register
 * @covers \CP_Sync\Events\PostTypes::event_args
 * @covers \CP_Sync\Events\PostTypes::series_args
 * @covers \CP_Sync\Events\PostTypes::category_args
 * @covers \CP_Sync\Events\PostTypes::public_slug
 * @covers \CP_Sync\Events\Display::is_enabled
 * @covers \CP_Sync\Events\Display::template_for
 * @covers \CP_Sync\Events\Display::series_archive
 * @covers \CP_Sync\Events\Display::series_archive_query_vars
 * @covers \CP_Sync\Events\Meta::event_meta
 * @covers \CP_Sync\ChMS\ChMS::get_sync_toggle_fields
 */
class EventRegistrationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_registration_stays_off_while_the_events_calendar_is_active() {
		$this->assertFalse( PostTypes::should_register( true, true ) );
		$this->assertFalse( PostTypes::should_register( true, null ) );
	}

	public function test_nothing_from_cp_event_shows_while_the_events_calendar_is_active() {
		$this->assertFalse( PostTypes::should_register( true, true ) );
		$this->assertSame( '', Display::template_for( true, true, 'archive' ) );
		$this->assertSame( '', Display::template_for( true, true, 'event' ) );
		$this->assertSame( '', Display::template_for( true, true, 'series' ) );
		$this->assertSame( '', Display::template_for( true, true, 'series-archive' ) );
		$this->assertSame( '', Display::template_for( true, true, 'category' ) );
	}

	public function test_archives_register_only_when_sync_events_is_on() {
		$this->assertTrue( PostTypes::should_register( false, true ) );
		$this->assertTrue( PostTypes::should_register( false, '1' ) );

		$this->assertFalse( PostTypes::should_register( false, null ) );
		$this->assertFalse( PostTypes::should_register( false, false ) );
		$this->assertFalse( PostTypes::should_register( false, '0' ) );
		$this->assertFalse( PostTypes::should_register( false, 'off' ) );
	}

	public function test_events_stay_off_when_nothing_is_stored() {
		Functions\when( 'get_option' )->justReturn( array() );

		$this->assertFalse( Display::is_enabled() );
		$this->assertNull( Display::stored_sync_events() );
	}

	public function test_saved_sync_events_turns_the_event_pages_on() {
		Functions\when( 'get_option' )->alias(
			static function ( $key, $default = array() ) {
				if ( 'cp_sync_pco_settings' === $key ) {
					return array(
						'connect' => array( 'sync_events' => true ),
					);
				}
				return $default;
			}
		);

		$this->assertTrue( Display::stored_sync_events() );
		$this->assertTrue( Display::is_enabled() );
	}

	public function test_event_types_have_no_admin_screens_and_stay_public() {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		foreach ( array( PostTypes::event_args(), PostTypes::series_args() ) as $args ) {
			$this->assertFalse( $args['show_ui'] );
			$this->assertFalse( $args['show_in_menu'] );
			$this->assertFalse( $args['show_in_admin_bar'] );
			$this->assertFalse( $args['show_in_rest'] );
			$this->assertTrue( $args['public'] );
			$this->assertTrue( $args['publicly_queryable'] );
			$this->assertNotEmpty( $args['has_archive'] );
		}

		$category = PostTypes::category_args();

		$this->assertFalse( $category['show_ui'] );
		$this->assertFalse( $category['show_admin_column'] );
		$this->assertFalse( $category['show_in_rest'] );
		$this->assertTrue( $category['public'] );
	}

	/**
	 * Registration must agree with the pull for every stored value, or events
	 * import into cp_event while its post types are never registered.
	 *
	 * @dataProvider stored_sync_values
	 */
	public function test_sync_check_matches_the_pull( $stored ) {
		$this->assertSame(
			ChMS::sync_setting_is_enabled( 'events', $stored ),
			PostTypes::sync_is_on( $stored )
		);
		$this->assertSame(
			ChMS::sync_setting_is_enabled( 'events', $stored ),
			PostTypes::should_register( false, $stored )
		);
	}

	public function stored_sync_values() {
		return array(
			'unset'          => array( null ),
			'true'           => array( true ),
			'false'          => array( false ),
			'int 1'          => array( 1 ),
			'int 0'          => array( 0 ),
			'string 1'       => array( '1' ),
			'string 0'       => array( '0' ),
			'empty string'   => array( '' ),
			'string "false"' => array( 'false' ),
			'string "no"'    => array( 'no' ),
		);
	}

	public function test_stored_sync_events_reads_the_filtered_active_chms() {
		Functions\when( 'get_option' )->alias(
			static function ( $key, $default = array() ) {
				if ( 'cp_sync_settings' === $key ) {
					return array( 'chms' => 'pco' );
				}
				if ( 'cp_sync_ccb_settings' === $key ) {
					return array(
						'connect' => array( 'sync_events' => false ),
					);
				}
				if ( 'cp_sync_pco_settings' === $key ) {
					return array(
						'connect' => array( 'sync_events' => true ),
					);
				}
				return $default;
			}
		);
		Monkey\Filters\expectApplied( 'cp_sync_active_chms' )->andReturn( 'ccb' );

		$this->assertFalse( Display::stored_sync_events() );
	}

	public function test_sync_events_toggle_is_available_without_the_events_calendar() {
		$chms = new class() extends ChMS {
			public function __construct() {}

			public function expose( $availability ) {
				return $this->get_sync_toggle_fields( $availability );
			}
		};

		$fields = $chms->expose( array( 'sermons' => false ) );

		$this->assertArrayNotHasKey( 'disabled', $fields['sync_events'] );
		$this->assertStringContainsString( '/events/', $fields['sync_events']['help'] );
	}

	public function test_archive_query_meta_always_has_a_default() {
		$meta = Meta::event_meta(
			array(
				'provider'      => 'pco',
				'occurrence_id' => 'inst-1',
				'source_id'     => 'inst-1',
				'start_utc'     => '2026-08-01 15:30:00',
			)
		);

		$args = EventsShortcode::query_args(
			array(
				'limit'    => 5,
				'category' => '',
				'series'   => '',
			),
			'2026-07-01 00:00:00',
			0
		);

		$this->assertNotSame( '', $meta[ Meta::START_UTC ] );
		$this->assertSame( $meta[ Meta::START_UTC ], $meta[ Meta::END_UTC ] );
		$this->assertSame( 'UTC', $meta[ Meta::TIMEZONE ] );
		$this->assertSame( '0', $meta[ Meta::ALL_DAY ] );
		$this->assertSame( '', $meta[ Meta::LOCATION ] );
		$this->assertSame( '', $meta[ Meta::REGISTRATION_URL ] );
		$this->assertSame( '0', $meta[ Meta::SERIES_ID ] );
		$this->assertSame( Meta::START_UTC, $args['meta_key'] );
		$this->assertSame( Meta::END_UTC, $args['meta_query'][0]['key'] );
		$this->assertArrayHasKey( $args['meta_key'], $meta );
		$this->assertArrayHasKey( $args['meta_query'][0]['key'], $meta );

		$series = Meta::series_meta( array( 'provider' => 'ccb' ) );
		$this->assertSame( '', $series[ Meta::SERIES_KEY ] );
		$this->assertSame( '', $series[ Meta::IMAGE_URL ] );
		$this->assertSame( '', $series[ Meta::REGISTRATION_URL ] );
	}

	public function test_series_archive_query_keeps_published_series_and_skips_event_dates() {
		Functions\when( 'is_admin' )->justReturn( false );

		$query = new class() {
			/** @var array */
			public $vars = array(
				'post_type' => 'cp_event',
				'meta_key'  => '_cp_start_utc',
				'meta_query' => array(
					array(
						'key' => '_cp_end_utc',
					),
				),
			);

			public function is_main_query() {
				return true;
			}

			public function is_singular() {
				return false;
			}

			public function is_post_type_archive( $type = '' ) {
				return '' === $type || PostTypes::SERIES === $type;
			}

			public function set( $key, $value ) {
				$this->vars[ $key ] = $value;
			}
		};

		Display::upcoming_archive( $query );
		Display::series_archive( $query );

		$this->assertSame( PostTypes::SERIES, $query->vars['post_type'] );
		$this->assertSame( 'publish', $query->vars['post_status'] );
		$this->assertSame( '', $query->vars['meta_key'] );
		$this->assertSame( array(), $query->vars['meta_query'] );
	}

	public function test_series_archive_uses_the_event_series_slug() {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$args = PostTypes::series_args();

		$this->assertSame( 'event-series', $args['has_archive'] );
		$this->assertSame( 'event-series', $args['rewrite']['slug'] );
		$this->assertFalse( $args['rewrite']['with_front'] );
	}

	public function test_upgrade_from_the_previous_rewrite_version_flushes_once() {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$stored  = 'on|2|events|event-series';
		$flushed = 0;
		Functions\when( 'get_option' )->alias(
			static function ( $key, $default = false ) use ( &$stored ) {
				if ( Display::REWRITE_OPTION === $key ) {
					return $stored;
				}

				return $default;
			}
		);
		$autoload = null;
		Functions\when( 'update_option' )->alias(
			static function ( $key, $value, $load = null ) use ( &$stored, &$autoload ) {
				if ( Display::REWRITE_OPTION === $key ) {
					$stored   = $value;
					$autoload = $load;
				}

				return true;
			}
		);
		Functions\when( 'flush_rewrite_rules' )->alias(
			static function () use ( &$flushed ) {
				$flushed++;
			}
		);

		$method = new \ReflectionMethod( Display::class, 'maybe_flush_rewrites' );
		$method->setAccessible( true );
		$method->invoke( null, true );
		$method->invoke( null, true );

		$this->assertSame( 1, $flushed );
		$this->assertSame( 'on|3|events|event-series', $stored );
		$this->assertTrue( $autoload );
	}

	public function test_recording_off_does_not_flush_permalinks() {
		$stored   = false;
		$flushed  = 0;
		$autoload = null;

		Functions\when( 'get_option' )->alias(
			static function ( $key, $default = false ) use ( &$stored ) {
				if ( Display::REWRITE_OPTION === $key ) {
					return false === $stored ? $default : $stored;
				}

				return $default;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( $key, $value, $load = null ) use ( &$stored, &$autoload ) {
				if ( Display::REWRITE_OPTION === $key ) {
					$stored   = $value;
					$autoload = $load;
				}

				return true;
			}
		);
		Functions\when( 'flush_rewrite_rules' )->alias(
			static function () use ( &$flushed ) {
				$flushed++;
			}
		);

		$method = new \ReflectionMethod( Display::class, 'maybe_flush_rewrites' );
		$method->setAccessible( true );
		$method->invoke( null, false );
		$method->invoke( null, false );

		$this->assertSame( 0, $flushed );
		$this->assertSame( 'off', $stored );
		$this->assertTrue( $autoload );
	}

	public function test_rewrite_flush_is_scheduled_after_init() {
		$hooks = array();

		Functions\when( 'add_action' )->alias(
			static function ( $hook, $callback ) use ( &$hooks ) {
				$method  = is_array( $callback ) ? (string) $callback[1] : '';
				$hooks[] = $hook . '|' . $method;

				return true;
			}
		);
		Functions\when( 'add_filter' )->justReturn( true );

		Display::boot();

		$this->assertContains( 'init|on_init', $hooks );
		$this->assertContains( 'wp_loaded|flush_rewrites_after_init', $hooks );
	}

	public function test_category_archive_uses_the_upcoming_event_query_and_template() {
		Functions\when( 'is_admin' )->justReturn( false );

		$query = new class() {
			/** @var array */
			public $vars = array();

			public function is_main_query() {
				return true;
			}

			public function is_singular() {
				return false;
			}

			public function is_post_type_archive( $type = '' ) {
				unset( $type );

				return false;
			}

			public function is_tax( $taxonomy = '' ) {
				return PostTypes::CATEGORY === $taxonomy;
			}

			public function set( $key, $value ) {
				$this->vars[ $key ] = $value;
			}
		};

		Display::upcoming_archive( $query );

		$this->assertSame( Meta::START_UTC, $query->vars['meta_key'] );
		$this->assertSame( 'ASC', $query->vars['order'] );
		$this->assertSame( Meta::END_UTC, $query->vars['meta_query'][0]['key'] );
		$this->assertSame( '>=', $query->vars['meta_query'][0]['compare'] );
		$this->assertSame( 'events/archive', Display::template_for( false, true, 'category' ) );
	}

	public function test_rest_settings_include_filtered_defaults() {
		Functions\when( 'get_option' )->justReturn( array( 'chms' => 'pco' ) );
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				if ( 'cp_sync_global_settings' === $tag && is_array( $value ) ) {
					$value['addedByFilter'] = true;
				}

				return $value;
			}
		);

		$settings = \CP_Sync\Admin\Settings::filtered_global_settings();

		$this->assertSame( 'pco', $settings['chms'] );
		$this->assertTrue( $settings['addedByFilter'] );
	}

	public function test_a_blank_title_still_gets_a_permalink_slug() {
		$this->assertSame( 'event-2026-08-01', PostTypes::public_slug( '', '2026-08-01' ) );
		$this->assertSame( 'event-series-pco-e1', PostTypes::public_slug( ' ', 'pco-e1', 'event-series' ) );
		$this->assertNotSame( '', PostTypes::public_slug( '', '', 'event-series' ) );
	}
}
