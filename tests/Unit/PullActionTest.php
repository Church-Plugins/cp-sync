<?php
/**
 * Capability and nonce checks for admin request actions.
 *
 * The cp_sync_pull callback starts a sync. These tests pin that a logged-out
 * request and a subscriber request do nothing, an administrator with a nonce
 * starts the sync, and scheduled cron still runs.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Admin\RequestAction;
use CP_Sync\Integrations\Integration;
use CP_Sync\Integrations\_Init;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Concrete integration whose health check records that it ran.
 */
class HealthcheckProbe extends Integration {

	/**
	 * Whether the scheduled health check ran.
	 *
	 * @var bool
	 */
	public $ran = false;

	/**
	 * @param array $item Item.
	 */
	public function update_item( $item ) {}

	/**
	 * @param string $taxonomy Taxonomy.
	 * @param array  $args     Arguments.
	 */
	public function register_taxonomy( $taxonomy, $args ) {}

	/**
	 * Record the scheduled health check without the parent process.
	 */
	protected function run_scheduled_healthcheck() {
		$this->ran = true;
	}
}

/**
 * @covers \CP_Sync\Integrations\_Init::handle_pull_action
 * @covers \CP_Sync\Admin\RequestAction
 */
class PullActionTest extends TestCase {

	/**
	 * @var _Init
	 */
	private $init;

	/**
	 * Plugin logger stand-in. Records each line passed to log().
	 *
	 * @var object
	 */
	private $logger;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'sanitize_key' )->alias(
			static function ( $key ) {
				$key = strtolower( (string) $key );
				return preg_replace( '/[^a-z0-9_\-]/', '', $key );
			}
		);

		$this->logger = new class() {
			/** @var string[] */
			public $lines = [];

			public function log( $message = '', $force = false ) {
				$this->lines[] = (string) $message;
			}
		};
		Functions\when( 'cp_sync' )->justReturn( (object) [ 'logging' => $this->logger ] );

		$this->init = $this->getMockBuilder( _Init::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'pull_content' ] )
			->getMock();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Simulate the capability of a role. Non-administrators cannot manage options.
	 *
	 * @param string $role logged_out, subscriber, or administrator.
	 */
	private function stub_role( $role ) {
		Functions\when( 'is_user_logged_in' )->justReturn( 'logged_out' !== $role );
		Functions\when( 'current_user_can' )->alias(
			static function ( $cap ) use ( $role ) {
				return 'administrator' === $role && RequestAction::CAPABILITY === $cap;
			}
		);
	}

	public function test_logged_out_request_does_not_start_sync() {
		$this->stub_role( 'logged_out' );
		$this->init->expects( $this->never() )->method( 'pull_content' );

		$result = $this->init->handle_pull_action(
			[
				'cp_action' => 'cp_sync_pull',
			]
		);

		$this->assertNull( $result );
	}

	public function test_subscriber_request_does_not_start_sync() {
		$this->stub_role( 'subscriber' );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		$this->init->expects( $this->never() )->method( 'pull_content' );

		$result = $this->init->handle_pull_action(
			[
				'cp_action' => 'cp_sync_pull',
				'_wpnonce'  => 'subscriber-nonce',
			]
		);

		$this->assertNull( $result );
	}

	public function test_admin_with_nonce_starts_sync() {
		$this->stub_role( 'administrator' );
		Functions\when( 'wp_verify_nonce' )->alias(
			static function ( $nonce, $action ) {
				return ( 'good-nonce' === $nonce && 'cp_sync_pull' === $action ) ? 1 : false;
			}
		);
		$this->init->expects( $this->once() )->method( 'pull_content' )->willReturn( true );

		$result = $this->init->handle_pull_action(
			[
				'cp_action' => 'cp_sync_pull',
				'_wpnonce'  => 'good-nonce',
			]
		);

		$this->assertTrue( $result );
	}

	public function test_admin_without_nonce_does_not_start_sync() {
		$this->stub_role( 'administrator' );
		$this->init->expects( $this->never() )->method( 'pull_content' );

		$result = $this->init->handle_pull_action(
			[
				'cp_action' => 'cp_sync_pull',
			]
		);

		$this->assertNull( $result );
	}

	public function test_scheduled_cron_starts_sync() {
		Functions\when( 'wp_doing_cron' )->justReturn( true );
		$this->init->expects( $this->once() )->method( 'pull_content' )->willReturn( true );

		$result = $this->init->handle_pull_action();

		$this->assertTrue( $result );
	}

	public function test_cron_request_without_permission_does_not_start_sync() {
		Functions\when( 'wp_doing_cron' )->justReturn( true );
		$this->stub_role( 'logged_out' );
		$this->init->expects( $this->never() )->method( 'pull_content' );

		$result = $this->init->handle_pull_action(
			[
				'cp_action' => 'cp_sync_pull',
			]
		);

		$this->assertNull( $result );
	}

	public function test_request_does_not_run_cron_healthcheck_without_permission() {
		$this->stub_role( 'subscriber' );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		$integration = $this->probe( 'wp_pull_groups' );
		$integration->handle_cron_healthcheck(
			[
				'cp_action' => 'wp_pull_groups_cron',
				'_wpnonce'  => 'subscriber-nonce',
			]
		);

		$this->assertFalse( $integration->ran );
	}

	public function test_scheduled_cron_runs_healthcheck() {
		Functions\when( 'wp_doing_cron' )->justReturn( true );

		$integration = $this->probe( 'wp_pull_events' );
		$integration->handle_cron_healthcheck();

		$this->assertTrue( $integration->ran );
	}

	public function test_skip_logs_when_not_running_from_cron() {
		$this->init->expects( $this->never() )->method( 'pull_content' );

		$result = $this->init->handle_pull_action();

		$this->assertNull( $result );
		$this->assertSame(
			[ 'Scheduled pull skipped: not running from WP-Cron' ],
			$this->logger->lines
		);

		$integration = $this->probe( 'wp_pull_groups' );
		$integration->handle_cron_healthcheck();

		$this->assertFalse( $integration->ran );
		$this->assertSame(
			[
				'Scheduled pull skipped: not running from WP-Cron',
				'Background health check skipped: not running from WP-Cron',
			],
			$this->logger->lines
		);
	}

	/**
	 * Build a health-check probe with the background-process identifier set.
	 *
	 * @param string $identifier Process identifier, such as wp_pull_groups.
	 * @return HealthcheckProbe
	 */
	private function probe( $identifier ) {
		$integration = ( new ReflectionClass( HealthcheckProbe::class ) )->newInstanceWithoutConstructor();
		$property    = new ReflectionProperty( Integration::class, 'identifier' );
		$property->setAccessible( true );
		$property->setValue( $integration, $identifier );

		return $integration;
	}
}
