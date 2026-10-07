<?php
/**
 * Single-argument admin request dispatch.
 *
 * Bundled core runs do_action() with one request array. These tests register the
 * real callbacks, dispatch that way with no logged-in user, and check stored
 * options and cron. A settings save that includes a cp_action key still encrypts
 * and still reschedules.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Admin\Settings;
use CP_Sync\Admin\SyncLock;
use CP_Sync\ChMS\CCB;
use CP_Sync\ChMS\ChMS;
use CP_Sync\ChMS\Encryption;
use CP_Sync\ChMS\PCO;
use CP_Sync\Integrations\CP_Groups;
use CP_Sync\Integrations\CP_Library;
use CP_Sync\Integrations\TEC;
use CP_Sync\Integrations\_Init;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Records lines passed to the plugin logger.
 */
class DispatchLog {

	/**
	 * @var string[]
	 */
	public $lines = [];

	/**
	 * @param string $message Log line.
	 * @param bool   $force   Unused. Matches the plugin logger signature.
	 */
	public function log( $message = '', $force = false ) {
		$this->lines[] = (string) $message;
	}
}

/**
 * Minimal add_action / do_action bus with WordPress accepted-args behavior.
 */
class DispatchHooks {

	/**
	 * @var array<string,array<int,array<int,array{callback:callable,accepted:int}>>>
	 */
	public $hooks = [];

	/**
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted Accepted argument count.
	 */
	public function add( $hook, $callback, $priority = 10, $accepted = 1 ) {
		$this->hooks[ $hook ][ (int) $priority ][] = [
			'callback' => $callback,
			'accepted' => (int) $accepted,
		];
	}

	/**
	 * @param string $hook Hook name.
	 * @param array  $args Arguments passed by the caller.
	 */
	public function do_action( $hook, array $args ) {
		$this->dispatch( $hook, $args, true );
	}

	/**
	 * @param string $hook  Hook name.
	 * @param mixed  $value First filter value.
	 * @param array  $extra Further arguments.
	 * @return mixed
	 */
	public function apply_filters( $hook, $value, array $extra = [] ) {
		return $this->dispatch( $hook, array_merge( [ $value ], $extra ), false );
	}

	/**
	 * @param string $hook         Hook name.
	 * @param array  $args         Arguments.
	 * @param bool   $doing_action True for do_action, which discards callback results.
	 * @return mixed
	 */
	private function dispatch( $hook, array $args, $doing_action ) {
		if ( empty( $this->hooks[ $hook ] ) ) {
			return $args[0] ?? null;
		}

		$priorities = $this->hooks[ $hook ];
		ksort( $priorities );

		$value = $args[0] ?? null;

		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$call     = $args;
				$accepted = $callback['accepted'];
				$count    = count( $call );

				if ( ! $doing_action ) {
					$call[0] = $value;
				}

				if ( 0 === $accepted ) {
					$result = call_user_func( $callback['callback'] );
				} elseif ( $accepted >= $count ) {
					$result = call_user_func_array( $callback['callback'], $call );
				} else {
					$result = call_user_func_array( $callback['callback'], array_slice( $call, 0, $accepted ) );
				}

				if ( ! $doing_action ) {
					$value = $result;
				}
			}
		}

		return $doing_action ? null : $value;
	}
}

/**
 * @covers \CP_Sync\Integrations\_Init::reschedule_cron
 * @covers \CP_Sync\ChMS\ChMS::pre_update_settings
 * @covers \CP_Sync\ChMS\ChMS::decrypt_settings
 */
class RequestDispatchArgsTest extends TestCase {

	/**
	 * @var DispatchHooks
	 */
	private $hooks;

	/**
	 * @var DispatchLog
	 */
	private $logger;

	/**
	 * Option store. Values are what update_option persisted.
	 *
	 * @var array<string,mixed>
	 */
	private $options = [];

	/**
	 * Cron events keyed by hook.
	 *
	 * @var array<string,array{timestamp:int,recurrence:string}>
	 */
	private $cron = [];

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}
		if ( ! defined( 'CP_SYNC_PLUGIN_FILE' ) ) {
			define( 'CP_SYNC_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/cp-sync.php' );
		}
		if ( ! defined( 'CP_SYNC_STORE_URL' ) ) {
			define( 'CP_SYNC_STORE_URL', 'https://example.test' );
		}

		$this->reset_schema_filters();
		$this->hooks  = new DispatchHooks();
		$this->logger = new DispatchLog();
		$this->options = [
			'cp_sync_settings'     => [
				'updateInterval' => 'hourly',
			],
			'cp_sync_ccb_settings' => [
				'connect' => [
					'subdomain' => 'kept-church',
					'username'  => 'stored-user',
					'password'  => 'stored-pass',
				],
			],
		];
		$this->cron = [
			'cp_sync_pull' => [
				'timestamp'  => 100,
				'recurrence' => 'hourly',
			],
		];

		$this->install_wordpress();
		$this->boot_plugin();
	}

	protected function tearDown(): void {
		$this->reset_schema_filters();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_single_argument_dispatch_leaves_options_and_cron_unchanged() {
		$options = $this->options;
		$cron    = $this->cron;

		foreach ( $this->dispatched_hooks() as $hook ) {
			$this->assertNotEmpty( $this->hooks->hooks[ $hook ] ?? null, $hook );
			do_action( $hook, [ 'cp_action' => $hook ] );
		}

		$this->assertSame( $options, $this->options );
		$this->assertSame( $cron, $this->cron );
	}

	public function test_settings_payload_with_action_key_is_still_encrypted() {
		update_option(
			'cp_sync_ccb_settings',
			[
				'cp_action' => 'x',
				'connect'   => [
					'subdomain' => 'my-church',
					'username'  => 'api-user',
					'password'  => 'secret-value',
				],
			]
		);

		$stored = $this->options['cp_sync_ccb_settings'];

		$this->assertSame( 'x', $stored['cp_action'] );
		$this->assertSame( 'my-church', $stored['connect']['subdomain'] );
		$this->assertTrue( Encryption::is_encrypted( $stored['connect']['username'] ) );
		$this->assertTrue( Encryption::is_encrypted( $stored['connect']['password'] ) );
		$this->assertNotSame( 'api-user', $stored['connect']['username'] );
		$this->assertNotSame( 'secret-value', $stored['connect']['password'] );

		$read = get_option( 'cp_sync_ccb_settings' );

		$this->assertSame( 'api-user', $read['connect']['username'] );
		$this->assertSame( 'secret-value', $read['connect']['password'] );
		$this->assertSame( 'my-church', $read['connect']['subdomain'] );
		$this->assertSame( 'x', $read['cp_action'] );
	}

	public function test_settings_payload_with_action_key_still_reschedules() {
		$this->options['cp_sync_settings'] = [
			'cp_action'      => 'x',
			'updateInterval' => 'weekly',
		];

		do_action(
			'cp_sync_global_settings_updated',
			[
				'cp_action'      => 'x',
				'updateInterval' => 'weekly',
			],
			[
				'cp_action'      => 'x',
				'updateInterval' => 'hourly',
			]
		);

		$this->assertSame( 'weekly', $this->cron['cp_sync_pull']['recurrence'] );
	}

	public function test_two_argument_item_update_still_runs() {
		do_action( 'cp_sync_events_update_item_after', [ 'title' => 'Sunday' ], 5 );

		$this->assertNotEmpty( $this->logger->lines );
		$this->assertStringContainsString( 'No event ID', $this->logger->lines[0] );
	}

	public function test_unexpected_filter_input_is_returned_unchanged() {
		$pull = [ 'cp_action' => 'cp_sync_pull_groups' ];
		$this->assertSame( $pull, apply_filters( 'cp_sync_pull_groups', $pull ) );

		$remove = [ 'cp_action' => 'cp_sync_events_should_remove_item' ];
		$this->assertSame( $remove, apply_filters( 'cp_sync_events_should_remove_item', $remove ) );

		$this->assertSame( 'keep-me', apply_filters( 'cp_groups_filter_facets', 'keep-me' ) );

		$content = [ 'cp_action' => 'the_content' ];
		$this->assertSame( $content, apply_filters( 'the_content', $content ) );

		$classes = [ 'cp_action' => 'admin_body_class' ];
		$this->assertSame( $classes, apply_filters( 'admin_body_class', $classes ) );
	}

	public function test_typed_callbacks_still_accept_their_normal_arguments() {
		Functions\when( 'is_singular' )->justReturn( false );
		Functions\when( 'get_current_screen' )->justReturn( null );
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$facets = [
			(object) [
				'taxonomy'     => 'cp_group_type',
				'single_label' => 'Type',
				'plural_label' => 'Types',
			],
		];

		$this->assertSame( $facets, apply_filters( 'cp_groups_filter_facets', $facets ) );
		$this->assertSame( 'Hello', apply_filters( 'the_content', 'Hello' ) );
		$this->assertSame( 'wp-admin', apply_filters( 'admin_body_class', 'wp-admin' ) );

		$post     = new \WP_Post();
		$post->ID = 4;

		do_action( 'add_meta_boxes', 'post', $post );
		do_action( 'save_post', 4, $post );

		$this->assertSame( 'kept-church', $this->options['cp_sync_ccb_settings']['connect']['subdomain'] );
		$this->assertSame( 'hourly', $this->cron['cp_sync_pull']['recurrence'] );
	}

	/**
	 * Hooks a cp_action request can reach.
	 *
	 * @return string[]
	 */
	private function dispatched_hooks() {
		return [
			'cp_sync_global_settings_updated',
			'cp_sync_pull',
			'cp_sync_events_update_item_after',
			'cp_sync_pull_groups',
			'cp_sync_pull_events',
			'cp_sync_pull_sermons',
			'cp_sync_events_should_remove_item',
			'add_meta_boxes',
			'save_post',
			'pre_update_option_cp_sync_ccb_settings',
			'option_cp_sync_ccb_settings',
			'cp_groups_filter_facets',
			'the_content',
			'admin_body_class',
			'wp_pull_groups_cron',
			'wp_pull_events_cron',
			'wp_pull_sermons_cron',
		];
	}

	private function install_wordpress() {
		$test = $this;

		Functions\when( 'add_action' )->alias(
			function ( $hook, $callback, $priority = 10, $accepted = 1 ) use ( $test ) {
				$test->hooks->add( $hook, $callback, $priority, $accepted );
			}
		);
		Functions\when( 'add_filter' )->alias(
			function ( $hook, $callback, $priority = 10, $accepted = 1 ) use ( $test ) {
				$test->hooks->add( $hook, $callback, $priority, $accepted );
			}
		);
		Functions\when( 'do_action' )->alias(
			function ( $hook, ...$args ) use ( $test ) {
				$test->hooks->do_action( $hook, $args );
			}
		);
		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $value, ...$extra ) use ( $test ) {
				return $test->hooks->apply_filters( $hook, $value, $extra );
			}
		);
		Functions\when( 'get_option' )->alias(
			function ( $option, $default = false ) use ( $test ) {
				if ( ! array_key_exists( $option, $test->options ) ) {
					return $default;
				}

				return apply_filters( 'option_' . $option, $test->options[ $option ] );
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $option, $value ) use ( $test ) {
				$old = get_option( $option, false );
				$test->options[ $option ] = apply_filters( 'pre_update_option_' . $option, $value, $old );

				return true;
			}
		);
		Functions\when( 'wp_next_scheduled' )->alias(
			function ( $hook ) use ( $test ) {
				return $test->cron[ $hook ]['timestamp'] ?? false;
			}
		);
		Functions\when( 'wp_clear_scheduled_hook' )->alias(
			function ( $hook ) use ( $test ) {
				unset( $test->cron[ $hook ] );
			}
		);
		Functions\when( 'wp_schedule_event' )->alias(
			function ( $timestamp, $recurrence, $hook ) use ( $test ) {
				$test->cron[ $hook ] = [
					'timestamp'  => $timestamp,
					'recurrence' => $recurrence,
				];
			}
		);
		Functions\when( 'wp_list_pluck' )->alias(
			static function ( $list, $field ) {
				$values = [];

				foreach ( (array) $list as $item ) {
					if ( is_object( $item ) && isset( $item->$field ) ) {
						$values[] = $item->$field;
					} elseif ( is_array( $item ) && isset( $item[ $field ] ) ) {
						$values[] = $item[ $field ];
					}
				}

				return $values;
			}
		);
		Functions\when( 'sanitize_key' )->alias(
			static function ( $key ) {
				$key = strtolower( (string) $key );
				return preg_replace( '/[^a-z0-9_\-]/', '', $key );
			}
		);

		foreach ( [ '__', 'esc_html__', 'esc_attr__' ] as $function ) {
			Functions\when( $function )->returnArg( 1 );
		}

		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'wp_salt' )->justReturn( 'unit-test-salt-value-1234567890' );
		Functions\when( 'get_admin_url' )->justReturn( 'https://example.test/wp-admin/' );
		Functions\when( 'register_rest_route' )->justReturn( null );
		Functions\when( 'cp_sync' )->justReturn( (object) [ 'logging' => $this->logger ] );
	}

	private function boot_plugin() {
		_Init::get_instance();
		CCB::get_instance()->load();
		PCO::get_instance()->load();
		new CP_Groups();
		new TEC();
		new CP_Library();
		SyncLock::get_instance();
		Settings::get_instance();
	}

	private function reset_schema_filters() {
		$this->set_static( ChMS::class, 'schema_option_filters_registered', [] );
		$this->set_static( ChMS::class, '_instance', null );
		$this->set_static( _Init::class, '_instance', null );
		$this->set_static( SyncLock::class, '_instance', null );
		$this->set_static( Settings::class, 'instance', null );
	}

	/**
	 * @param class-string $class Class name.
	 * @param string       $property Static property name.
	 * @param mixed        $value Value.
	 */
	private function set_static( $class, $property, $value ) {
		$reflection = new ReflectionProperty( $class, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( null, $value );
	}
}

namespace ChurchPlugins\Admin;

if ( ! class_exists( __NAMESPACE__ . '\Menu', false ) ) {
	class Menu {
		public static function add_support() {}
	}
}

if ( ! class_exists( __NAMESPACE__ . '\Options', false ) ) {
	class Options {
		public static function register_rest_route( $namespace = '', $prefix = '' ) {}
	}
}

namespace ChurchPlugins\Setup\Admin;

if ( ! class_exists( __NAMESPACE__ . '\License', false ) ) {
	class License {
		public function __construct( $item_id = '', $store_item_id = 0, $store_url = '', $file = '', $license_page = '' ) {}
	}
}

