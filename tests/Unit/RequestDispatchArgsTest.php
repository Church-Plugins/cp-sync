<?php
/**
 * Single-argument admin request dispatch.
 *
 * Bundled core runs do_action() with one request array. These tests call each
 * hook callback that way, with no logged-in user, and expect a quiet skip.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Admin\Settings;
use CP_Sync\Admin\SyncLock;
use CP_Sync\ChMS\CCB;
use CP_Sync\Integrations\CP_Groups;
use CP_Sync\Integrations\TEC;
use CP_Sync\Integrations\_Init;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \CP_Sync\Admin\RequestAction::from_dispatcher
 */
class RequestDispatchArgsTest extends TestCase {

	/**
	 * How many times the capability guard ran.
	 *
	 * @var int
	 */
	private $capability_checks = 0;

	/**
	 * @var _Init
	 */
	private $init;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->capability_checks = 0;

		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'current_user_can' )->alias(
			function () {
				$this->capability_checks++;
				return false;
			}
		);
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'sanitize_key' )->alias(
			static function ( $key ) {
				$key = strtolower( (string) $key );
				return preg_replace( '/[^a-z0-9_\-]/', '', $key );
			}
		);
		Functions\when( 'cp_sync' )->justReturn(
			(object) [
				'logging' => new class() {
					public function log( $message = '', $force = false ) {}
				},
			]
		);

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
	 * Request array shaped like the bundled dispatcher's $_GET payload.
	 *
	 * @param string $action Hook name passed as cp_action.
	 * @return array
	 */
	private function request( $action ) {
		return [
			'cp_action' => $action,
		];
	}

	/**
	 * The capability guard ran and the callback did not throw.
	 */
	private function assert_skipped() {
		$this->assertSame( 1, $this->capability_checks );
	}

	public function test_single_argument_does_not_reschedule() {
		Functions\expect( 'wp_clear_scheduled_hook' )->never();
		Functions\expect( 'wp_schedule_event' )->never();

		$this->init->reschedule_cron( $this->request( 'cp_sync_global_settings_updated' ) );

		$this->assert_skipped();
	}

	public function test_single_argument_does_not_enrich() {
		$ccb = ( new ReflectionClass( CCB::class ) )->newInstanceWithoutConstructor();

		$ccb->maybe_enrich_event_after_update( $this->request( 'cp_sync_events_update_item_after' ) );

		$this->assert_skipped();
	}

	/**
	 * @dataProvider pull_hook_provider
	 *
	 * @param string $hook Pull hook name.
	 */
	public function test_single_argument_does_not_pull( $hook ) {
		$ccb = ( new ReflectionClass( CCB::class ) )->newInstanceWithoutConstructor();

		$result = $ccb->get_formatted_data( $this->request( $hook ) );

		$this->assertNull( $result );
		$this->assert_skipped();
	}

	public function pull_hook_provider() {
		return [
			'groups'  => [ 'cp_sync_pull_groups' ],
			'events'  => [ 'cp_sync_pull_events' ],
			'sermons' => [ 'cp_sync_pull_sermons' ],
		];
	}

	public function test_single_argument_does_not_remove_past_events() {
		Functions\expect( 'get_post_meta' )->never();

		$tec = ( new ReflectionClass( TEC::class ) )->newInstanceWithoutConstructor();

		$result = $tec->preserve_past_events( $this->request( 'cp_sync_events_should_remove_item' ) );

		$this->assertFalse( $result );
		$this->assert_skipped();
	}

	public function test_single_argument_does_not_register_meta_box() {
		Functions\expect( 'get_post_meta' )->never();
		Functions\expect( 'add_meta_box' )->never();

		$lock = ( new ReflectionClass( SyncLock::class ) )->newInstanceWithoutConstructor();

		$lock->register_meta_box( $this->request( 'add_meta_boxes' ) );

		$this->assert_skipped();
	}

	public function test_single_argument_does_not_save_lock() {
		Functions\expect( 'wp_verify_nonce' )->never();
		Functions\expect( 'update_post_meta' )->never();
		Functions\expect( 'delete_post_meta' )->never();

		$lock = ( new ReflectionClass( SyncLock::class ) )->newInstanceWithoutConstructor();

		$lock->save( $this->request( 'save_post' ) );

		$this->assert_skipped();
	}

	public function test_single_argument_does_not_change_settings_on_write() {
		Functions\expect( 'update_option' )->never();

		$ccb     = ( new ReflectionClass( CCB::class ) )->newInstanceWithoutConstructor();
		$payload = $this->request( 'pre_update_option_cp_sync_ccb_settings' );

		$result = $ccb->pre_update_settings( $payload );

		$this->assertSame( $payload, $result );
		$this->assert_skipped();
	}

	public function test_single_argument_does_not_change_settings_on_read() {
		$ccb     = ( new ReflectionClass( CCB::class ) )->newInstanceWithoutConstructor();
		$payload = $this->request( 'option_cp_sync_ccb_settings' );

		$result = $ccb->decrypt_settings( $payload );

		$this->assertSame( $payload, $result );
		$this->assert_skipped();
	}

	public function test_single_argument_does_not_add_facets() {
		Functions\expect( 'wp_list_pluck' )->never();
		Functions\expect( 'get_option' )->never();

		$groups = ( new ReflectionClass( CP_Groups::class ) )->newInstanceWithoutConstructor();
		$payload = $this->request( 'cp_groups_filter_facets' );

		$result = $groups->add_synced_facets( $payload );

		$this->assertSame( $payload, $result );
		$this->assert_skipped();
	}

	public function test_single_argument_does_not_change_content() {
		Functions\expect( 'is_singular' )->never();

		$tec     = ( new ReflectionClass( TEC::class ) )->newInstanceWithoutConstructor();
		$payload = $this->request( 'the_content' );

		$result = $tec->maybe_add_registration_button( $payload );

		$this->assertSame( $payload, $result );
		$this->assert_skipped();
	}

	public function test_single_argument_does_not_change_body_class() {
		Functions\expect( 'get_current_screen' )->never();

		$settings = ( new ReflectionClass( Settings::class ) )->newInstanceWithoutConstructor();
		$payload  = $this->request( 'admin_body_class' );

		$result = $settings->admin_body_class( $payload );

		$this->assertSame( $payload, $result );
		$this->assert_skipped();
	}

	public function test_two_settings_arrays_still_reschedule() {
		Functions\when( 'wp_next_scheduled' )->justReturn( true );
		Functions\expect( 'wp_clear_scheduled_hook' )->once()->with( 'cp_sync_pull' );

		$this->init->reschedule_cron(
			[
				'updateInterval' => 'weekly',
			],
			[
				'updateInterval' => 'hourly',
			]
		);

		$this->assertSame( 0, $this->capability_checks );
	}
}
