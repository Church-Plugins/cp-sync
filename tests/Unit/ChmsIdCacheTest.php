<?php
/**
 * ChMS id cache, hidden-event trash, and permanent removal.
 *
 * A cached id is dropped only when that post is gone (or is a different post
 * type). A trashed post is kept so unhide publishes the same id. With The
 * Events Calendar, a hidden or unapproved event is trashed and later restored.
 * A genuine removal still deletes the post, and the next sync creates a new one.
 *
 * CP Sync's own event pages (cp_event) trash and untrash through the event
 * store. They are a different post type from tribe_events, so each path ends
 * with one published post.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Events\EventSync;
use CP_Sync\Events\VisibilityRemovalNotice;
use CP_Sync\Events\WpEventStore;
use CP_Sync\Integrations\Integration;
use CP_Sync\Setup\Reset;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \CP_Sync\Integrations\Integration
 * @covers \CP_Sync\Setup\Reset::reset_sync_state
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ChmsIdCacheTest extends TestCase {

	/** @var PostWorld */
	private $world;

	/** @var string */
	private $timezone;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->timezone = date_default_timezone_get();
		date_default_timezone_set( 'UTC' );
		$this->world = new PostWorld();

		global $wpdb;
		$wpdb         = new CacheWpdb( $this->world );
		$world        = $this->world;

		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'UTC' ) );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
		Functions\when( 'add_filter' )->alias(
			static function ( $tag, $callback, $priority = 10, $accepted = 1 ) use ( $world ) {
				$world->filters[ $tag ][ $priority ][] = array( $callback, (int) $accepted );
				return true;
			}
		);
		Functions\when( 'remove_filter' )->alias(
			static function ( $tag, $callback, $priority = 10 ) use ( $world ) {
				if ( empty( $world->filters[ $tag ][ $priority ] ) ) {
					return true;
				}

				foreach ( $world->filters[ $tag ][ $priority ] as $index => $row ) {
					if ( $row[0] === $callback ) {
						unset( $world->filters[ $tag ][ $priority ][ $index ] );
					}
				}

				return true;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value, ...$args ) use ( $world ) {
				if ( empty( $world->filters[ $tag ] ) ) {
					return $value;
				}

				ksort( $world->filters[ $tag ] );

				foreach ( $world->filters[ $tag ] as $callbacks ) {
					foreach ( $callbacks as $row ) {
						$call  = array_merge( array( $value ), array_slice( $args, 0, max( 0, $row[1] - 1 ) ) );
						$value = call_user_func_array( $row[0], $call );
					}
				}

				return $value;
			}
		);
		Functions\when( 'add_action' )->alias(
			static function ( $tag, $callback, $priority = 10, $accepted = 1 ) use ( $world ) {
				$world->actions[ $tag ][ $priority ][] = array( $callback, (int) $accepted );
				return true;
			}
		);
		Functions\when( 'do_action' )->alias(
			static function ( $tag, ...$args ) use ( $world ) {
				if ( empty( $world->actions[ $tag ] ) ) {
					return null;
				}

				ksort( $world->actions[ $tag ] );

				foreach ( $world->actions[ $tag ] as $callbacks ) {
					foreach ( $callbacks as $row ) {
						call_user_func_array( $row[0], array_slice( $args, 0, $row[1] ) );
					}
				}

				return null;
			}
		);
		Functions\when( 'taxonomy_exists' )->justReturn( false );
		Functions\when( 'sanitize_title' )->returnArg( 1 );
		Functions\when( 'wp_slash' )->returnArg( 1 );
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'get_post' )->alias(
			static function ( $post ) use ( $world ) {
				return $world->get_post( $post );
			}
		);
		Functions\when( 'get_post_status' )->alias(
			static function ( $post ) use ( $world ) {
				$found = $world->get_post( $post );
				return $found ? $found->post_status : false;
			}
		);
		Functions\when( 'get_post_meta' )->alias(
			static function ( $post_id, $key, $single = false ) use ( $world ) {
				return $world->get_meta( $post_id, $key );
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			static function ( $post_id, $key, $value ) use ( $world ) {
				$world->set_meta( $post_id, $key, $value );
				return true;
			}
		);
		Functions\when( 'delete_post_meta' )->alias(
			static function ( $post_id, $key ) use ( $world ) {
				$id = (int) $post_id;
				if ( isset( $world->posts[ $id ]['meta'][ $key ] ) ) {
					unset( $world->posts[ $id ]['meta'][ $key ] );
				}
				return true;
			}
		);
		Functions\when( 'get_post_thumbnail_id' )->alias(
			static function ( $post_id ) use ( $world ) {
				return (int) $world->get_meta( $post_id, '_thumbnail_id' );
			}
		);
		Functions\when( 'wp_insert_post' )->alias(
			static function ( $postarr ) use ( $world ) {
				return $world->save_post( $postarr );
			}
		);
		Functions\when( 'wp_update_post' )->alias(
			static function ( $postarr ) use ( $world ) {
				return $world->save_post( $postarr );
			}
		);
		Functions\when( 'wp_delete_post' )->alias(
			static function ( $post_id ) use ( $world ) {
				$world->calls[] = array( 'wp_delete_post', (int) $post_id );
				$world->delete_post( $post_id );
				return true;
			}
		);
		Functions\when( 'wp_trash_post' )->alias(
			static function ( $post_id ) use ( $world ) {
				$world->calls[] = array( 'wp_trash_post', (int) $post_id );
				return $world->trash_post( $post_id );
			}
		);
		Functions\when( 'wp_untrash_post' )->alias(
			static function ( $post_id ) use ( $world ) {
				$world->calls[] = array( 'wp_untrash_post', (int) $post_id );
				return $world->untrash_post( $post_id );
			}
		);
		Functions\when( 'wp_publish_post' )->alias(
			static function ( $post_id ) use ( $world ) {
				$world->calls[] = array( 'wp_publish_post', (int) $post_id );
				return $world->publish_post( $post_id );
			}
		);
		Functions\when( 'wp_delete_attachment' )->alias(
			static function ( $post_id ) use ( $world ) {
				$world->calls[] = array( 'wp_delete_attachment', (int) $post_id );
				$world->delete_attachment( $post_id );
				return true;
			}
		);
		Functions\when( 'get_posts' )->alias(
			static function ( $args ) use ( $world ) {
				return $world->ids_for(
					$args['post_type'] ?? '',
					(array) ( $args['post_status'] ?? array( 'publish' ) )
				);
			}
		);
		Functions\when( 'wp_cache_get' )->alias(
			static function ( $key, $group = '' ) use ( $world ) {
				return $world->cache[ $group ][ $key ] ?? false;
			}
		);
		Functions\when( 'wp_cache_set' )->alias(
			static function ( $key, $value, $group = '' ) use ( $world ) {
				$world->cache[ $group ][ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'wp_cache_delete' )->alias(
			static function ( $key, $group = '' ) use ( $world ) {
				unset( $world->cache[ $group ][ $key ] );
				return true;
			}
		);

		$plugin          = new \stdClass();
		$plugin->logging = new class( $world ) {
			/** @var PostWorld */
			private $world;

			public function __construct( PostWorld $world ) {
				$this->world = $world;
			}

			public function log( $message ) {
				$this->world->logs[] = (string) $message;
			}
		};
		Functions\when( 'cp_sync' )->justReturn( $plugin );

		VisibilityRemovalNotice::reset();
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = null;
		date_default_timezone_set( $this->timezone );
		VisibilityRemovalNotice::reset();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_removed_then_filtered_back_in_recreates() {
		$integration = $this->integration();
		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden' ), $this->item( 'kept', 'Kept' ) ) );
		$original = $this->only_published( 'tribe_events', 'hidden' );
		$this->attach_image( $original );

		VisibilityRemovalNotice::reset();
		$integration->sync_feed( array( $this->item( 'kept', 'Kept' ) ) );

		$this->assertNotContains( $original, array_keys( $this->world->posts ) );
		$this->assertSame( array(), $this->world->files );
		$this->assertNotContains( 'wp_untrash_post', $this->call_names() );

		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden again' ), $this->item( 'kept', 'Kept' ) ) );

		$restored = $this->only_published( 'tribe_events', 'hidden' );
		$this->assertNotSame( $original, $restored );
		$this->assertCount( 1, $this->ids_with_status( 'tribe_events', 'hidden', 'publish' ) );
	}

	public function test_deleted_outside_cp_sync_recreates() {
		$integration = $this->integration();
		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden' ) ) );
		$original = $this->only_published( 'tribe_events', 'hidden' );

		$this->world->delete_post( $original );
		$this->assertNull( $integration->get_chms_item_id( 'hidden' ) );

		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden again' ) ) );

		$created = $this->only_published( 'tribe_events', 'hidden' );
		$this->assertNotSame( $original, $created );
		$this->assertSame( array( $created ), $this->ids_with_status( 'tribe_events', 'hidden', 'publish' ) );
	}

	public function test_admin_trashed_post_stays_trashed_and_is_not_duplicated() {
		$integration = $this->integration();
		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden' ), $this->item( 'kept', 'Kept' ) ) );
		$original = $this->only_published( 'tribe_events', 'hidden' );

		$this->world->trash_post( $original );
		$this->assertSame( '', $this->world->get_meta( $original, '_cp_sync_trashed' ) );
		$this->assertSame( $original, (int) $integration->get_chms_item_id( 'hidden' ) );

		$integration->flush_chms_id_cache();
		$this->assertSame( $original, (int) $integration->get_chms_item_id( 'hidden' ) );

		$integration->sync_feed( array( $this->item( 'hidden', 'Shown' ), $this->item( 'kept', 'Kept' ) ) );

		$this->assertSame( array( $original ), $this->ids_with_status( 'tribe_events', 'hidden', 'trash' ) );
		$this->assertSame( array(), $this->ids_with_status( 'tribe_events', 'hidden', 'publish' ) );
		$this->assertNotContains( 'wp_untrash_post', $this->call_names() );
		$this->assertNotContains( 'wp_publish_post', $this->call_names() );
		$logged = implode( "\n", $this->world->logs );
		$this->assertStringContainsString( 'was not moved there by CP Sync', $logged );
	}

	public function test_admin_trashed_item_filtered_out_stays_in_the_trash_and_the_store() {
		$integration = $this->integration();
		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden' ), $this->item( 'kept', 'Kept' ) ) );
		$original = $this->only_published( 'tribe_events', 'hidden' );

		$this->world->trash_post( $original );
		$integration->flush_chms_id_cache();
		$integration->sync_feed( array( $this->item( 'kept', 'Kept' ) ) );

		$this->assertSame( array( $original ), $this->ids_with_status( 'tribe_events', 'hidden', 'trash' ) );
		$this->assertSame( array(), $this->ids_with_status( 'tribe_events', 'hidden', 'publish' ) );
		$this->assertArrayHasKey( 'hidden', $integration->store );
		$this->assertNotContains( 'wp_delete_post', $this->call_names() );
	}

	public function test_filtered_back_in_admin_trashed_item_stays_trashed_without_a_new_post() {
		$integration = $this->integration();
		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden' ), $this->item( 'kept', 'Kept' ) ) );
		$original = $this->only_published( 'tribe_events', 'hidden' );

		$this->world->trash_post( $original );
		$integration->flush_chms_id_cache();
		$integration->sync_feed( array( $this->item( 'kept', 'Kept' ) ) );
		$integration->sync_feed( array( $this->item( 'hidden', 'Shown again' ), $this->item( 'kept', 'Kept' ) ) );

		$this->assertSame( array( $original ), $this->ids_with_status( 'tribe_events', 'hidden', 'trash' ) );
		$this->assertSame( array(), $this->ids_with_status( 'tribe_events', 'hidden', 'publish' ) );
		$this->assertSame( 'Hidden', $this->world->posts[ $original ]['post_title'] );
		$this->assertNotContains( 'wp_delete_post', $this->call_names() );
		$this->assertNotContains( 'wp_untrash_post', $this->call_names() );
		$this->assertNotContains( 'wp_publish_post', $this->call_names() );
		$logged = implode( "\n", $this->world->logs );
		$this->assertStringContainsString( 'was not moved there by CP Sync', $logged );
	}

	public function test_published_item_filtered_out_is_deleted_and_dropped_from_the_store() {
		$integration = $this->integration();
		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden' ), $this->item( 'kept', 'Kept' ) ) );
		$original = $this->only_published( 'tribe_events', 'hidden' );

		$integration->sync_feed( array( $this->item( 'kept', 'Kept' ) ) );

		$this->assertArrayNotHasKey( $original, $this->world->posts );
		$this->assertArrayNotHasKey( 'hidden', $integration->store );
		$this->assertContains( 'wp_delete_post', $this->call_names() );
	}

	public function test_visibility_skip_filtered_out_is_trashed_not_deleted() {
		$integration = $this->integration();
		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden' ), $this->item( 'kept', 'Kept' ) ) );
		$original = $this->only_published( 'tribe_events', 'hidden' );

		VisibilityRemovalNotice::record_skip( 'hidden' );
		$integration->sync_feed( array( $this->item( 'kept', 'Kept' ) ) );

		$this->assertSame( 'trash', $this->world->posts[ $original ]['post_status'] );
		$this->assertSame( '1', (string) $this->world->get_meta( $original, '_cp_sync_trashed' ) );
		$this->assertArrayNotHasKey( 'hidden', $integration->store );
		$this->assertNotContains( 'wp_delete_post', $this->call_names() );
		$this->assertContains( 'wp_trash_post', $this->call_names() );
	}

	public function test_sync_trashed_draft_comes_back_as_a_draft() {
		$integration = $this->integration();
		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden' ) ) );
		$original = $this->only_published( 'tribe_events', 'hidden' );
		$this->world->posts[ $original ]['post_status'] = 'draft';

		$integration->trash_item( 'hidden' );

		$this->assertSame( 'trash', $this->world->posts[ $original ]['post_status'] );
		$this->assertSame( '1', (string) $this->world->get_meta( $original, '_cp_sync_trashed' ) );
		$this->assertSame( 'draft', $this->world->get_meta( $original, '_cp_sync_trashed_status' ) );

		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden' ) ) );

		$this->assertSame( 'draft', $this->world->posts[ $original ]['post_status'] );
		$this->assertSame( '', $this->world->get_meta( $original, '_cp_sync_trashed' ) );
		$this->assertSame( array( $original ), $this->ids_with_status( 'tribe_events', 'hidden', 'draft' ) );
		$this->assertNotContains( 'wp_publish_post', $this->call_names() );
	}

	public function test_sync_trashed_published_post_comes_back_published() {
		$integration = $this->integration();
		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden' ) ) );
		$original = $this->only_published( 'tribe_events', 'hidden' );

		$integration->trash_item( 'hidden' );
		$this->assertSame( 'publish', $this->world->get_meta( $original, '_cp_sync_trashed_status' ) );

		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden again' ) ) );

		$this->assertSame( array( $original ), $this->ids_with_status( 'tribe_events', 'hidden', 'publish' ) );
		$this->assertSame( 'Hidden again', $this->world->posts[ $original ]['post_title'] );
		$this->assertSame( '', $this->world->get_meta( $original, '_cp_sync_trashed' ) );
	}

	public function test_admin_restore_then_admin_trash_is_not_revived() {
		$integration = $this->integration();
		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden' ) ) );
		$original = $this->only_published( 'tribe_events', 'hidden' );

		$integration->trash_item( 'hidden' );
		$this->assertSame( '1', (string) $this->world->get_meta( $original, '_cp_sync_trashed' ) );

		// An administrator restores from the Trash screen. WordPress fires
		// untrashed_post, which drops the sync marker.
		$this->world->untrash_post( $original );
		$this->assertSame( '', $this->world->get_meta( $original, '_cp_sync_trashed' ) );
		$this->assertSame( '', $this->world->get_meta( $original, '_cp_sync_trashed_status' ) );

		$this->world->trash_post( $original );
		$integration->sync_feed( array( $this->item( 'hidden', 'Shown' ) ) );

		$this->assertSame( array( $original ), $this->ids_with_status( 'tribe_events', 'hidden', 'trash' ) );
		$this->assertSame( array(), $this->ids_with_status( 'tribe_events', 'hidden', 'publish' ) );
		$this->assertNotContains( 'wp_publish_post', $this->call_names() );
	}

	public function test_hide_then_unhide_runs_through_dispatch_and_the_queue_worker() {
		Functions\when( 'wp_die' )->justReturn( null );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'absint' )->alias(
			static function ( $value ) {
				return abs( (int) $value );
			}
		);
		Functions\when( 'wp_generate_uuid4' )->justReturn( '11111111-1111-4111-8111-111111111111' );
		Functions\when( 'wp_convert_hr_to_bytes' )->justReturn( PHP_INT_MAX );
		Functions\when( 'get_site_transient' )->justReturn( false );
		Functions\when( 'set_site_transient' )->justReturn( true );
		Functions\when( 'delete_site_transient' )->justReturn( true );
		Functions\when( 'delete_site_option' )->justReturn( true );
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\when( 'wp_unschedule_event' )->justReturn( true );

		$integration = $this->queue_integration();

		// Same request as the fetch: nothing is hidden, both items are queued,
		// dispatch starts the worker, and the worker creates the posts.
		$integration->process( array( $this->item( 'hidden', 'Family Movie Night' ), $this->item( 'kept', 'Kept' ) ) );
		$this->assertSame( 1, $integration->dispatched );
		$integration->run_worker();
		$original = $this->only_published( 'tribe_events', 'hidden' );

		// Fetch records the hidden id, then process() trashes it before dispatch.
		// The worker is a later request, so the static skip list is empty there.
		VisibilityRemovalNotice::record_skip( 'hidden' );
		$integration->process( array( $this->item( 'kept', 'Kept' ) ) );
		$this->assertSame( 'trash', $this->world->posts[ $original ]['post_status'] );
		$this->assertSame( '1', (string) $this->world->get_meta( $original, '_cp_sync_trashed' ) );
		$this->assertSame( 2, $integration->dispatched );

		VisibilityRemovalNotice::reset();
		$integration->run_worker();
		$this->assertSame( 'trash', $this->world->posts[ $original ]['post_status'] );
		$this->assertSame( array( $original ), $this->ids_with_status( 'tribe_events', 'hidden', 'trash' ) );

		// Unhide queues the item. The worker, not process(), restores it.
		$integration->process( array( $this->item( 'hidden', 'Family Movie Night' ), $this->item( 'kept', 'Kept' ) ) );
		$this->assertSame( 'trash', $this->world->posts[ $original ]['post_status'] );
		$this->assertSame( 3, $integration->dispatched );
		VisibilityRemovalNotice::reset();
		$integration->run_worker();

		$this->assertSame( array( $original ), $this->ids_with_status( 'tribe_events', 'hidden', 'publish' ) );
		$this->assertSame( '', $this->world->get_meta( $original, '_cp_sync_trashed' ) );
	}

	public function test_recreated_post_gets_a_new_image_when_the_old_file_is_gone() {
		$integration = $this->image_integration();
		$post        = $this->world->save_post(
			array(
				'post_type'   => 'tribe_events',
				'post_status' => 'publish',
				'post_title'  => 'Registration',
			)
		);
		$this->world->set_meta( $post, '_chms_id', 'reg-1' );

		$dead = $this->world->save_post(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_title'  => 'Old logo',
			)
		);
		$url = 'https://cdn.example.com/logo.jpg';
		$this->world->set_meta( $dead, '_cp_sync_normalized_url', $url );
		$this->world->set_meta( $dead, '_wp_attached_file', '/tmp/cp-sync-missing-logo.jpg' );

		$this->stub_thumbnail_api();

		$this->assertFalse( $integration->get_existing_attachment( $url ) );
		$this->assertSame( '', $this->world->get_meta( $dead, '_cp_sync_normalized_url' ) );

		$fresh = tempnam( sys_get_temp_dir(), 'cpsync' );
		file_put_contents( $fresh, 'image' );
		$integration->fresh_file = $fresh;

		$integration->maybe_sideload_thumb(
			array(
				'thumbnail_url' => $url,
				'post_title'    => 'Registration',
			),
			$post
		);

		$thumb = (int) $this->world->get_meta( $post, '_thumbnail_id' );
		$this->assertGreaterThan( 0, $thumb );
		$this->assertNotSame( $dead, $thumb );
		$this->assertSame( $url, $this->world->get_meta( $post, '_thumbnail_url' ) );
		@unlink( $fresh );
		$this->assertSame( 1, $integration->created );
	}

	public function test_offloaded_image_is_reused_when_the_local_file_is_missing() {
		$integration = $this->image_integration();
		$post        = $this->world->save_post(
			array(
				'post_type'   => 'tribe_events',
				'post_status' => 'publish',
				'post_title'  => 'Registration',
			)
		);
		$kept = $this->world->save_post(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_title'  => 'CDN logo',
			)
		);
		$url = 'https://cdn.example.com/logo.jpg';
		$this->world->set_meta( $kept, '_cp_sync_normalized_url', $url );
		$this->world->set_meta( $kept, '_wp_attached_file', '/tmp/cp-sync-offloaded-logo.jpg' );
		$this->assertFileDoesNotExist( '/tmp/cp-sync-offloaded-logo.jpg' );

		$this->stub_thumbnail_api();
		add_filter(
			'wp_get_attachment_image_src',
			static function ( $src, $attachment_id ) use ( $kept, $url ) {
				if ( (int) $attachment_id === (int) $kept ) {
					return array( $url, 640, 480, false );
				}

				return $src;
			},
			10,
			2
		);

		$integration->maybe_sideload_thumb(
			array(
				'thumbnail_url' => $url,
				'post_title'    => 'Registration',
			),
			$post
		);

		$this->assertSame( 0, $integration->created );
		$this->assertSame( $kept, (int) $this->world->get_meta( $post, '_thumbnail_id' ) );
		$this->assertSame( $url, $this->world->get_meta( $kept, '_cp_sync_normalized_url' ) );
	}

	public function test_existing_post_updates_in_place() {
		$integration = $this->integration();
		$integration->sync_feed( array( $this->item( 'hidden', 'First' ) ) );
		$original = $this->only_published( 'tribe_events', 'hidden' );

		$integration->sync_feed( array( $this->item( 'hidden', 'Second' ) ) );

		$this->assertSame( $original, $this->only_published( 'tribe_events', 'hidden' ) );
		$this->assertSame( 'Second', $this->world->posts[ $original ]['post_title'] );
		$this->assertCount( 1, $this->ids_with_status( 'tribe_events', 'hidden', 'publish' ) );
	}

	public function test_reset_clears_chms_id_cache() {
		$integration = $this->integration();
		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden' ) ) );
		$this->assertNotFalse( $this->world->cache['cp_sync']['chms_id_cache_tec'] ?? false );

		$this->world->cache['cp_sync']['chms_id_cache_other'] = array( 'leave' => 1 );

		$blank = $this->integration();
		$blank->id = '';

		$reset = new ResetCacheProbe();
		$reset->integrations = array( $integration, $blank );
		$summary = $reset->reset_sync_state();

		$this->assertSame( 1, $summary['caches'] );
		$this->assertArrayNotHasKey( 'chms_id_cache_tec', $this->world->cache['cp_sync'] );
		$this->assertSame( array( 'leave' => 1 ), $this->world->cache['cp_sync']['chms_id_cache_other'] );

		$property = ( new ReflectionClass( Integration::class ) )->getProperty( 'chms_id_cache' );
		$property->setAccessible( true );
		$this->assertNull( $property->getValue( $integration ) );

		$post_id = $this->only_published( 'tribe_events', 'hidden' );
		$this->assertSame( $post_id, (int) $integration->get_chms_item_id( 'hidden' ) );
	}

	public function test_remove_item_with_no_post_does_not_delete() {
		$integration = $this->integration();
		$integration->remove_item( 'missing' );

		$this->assertNotContains( 'wp_delete_post', $this->call_names() );
		$this->assertNotContains( 'wp_delete_attachment', $this->call_names() );
	}

	public function test_cached_id_for_another_post_type_is_dropped() {
		$integration = $this->integration();
		$other = $this->world->save_post(
			array(
				'post_type'   => 'cp_event',
				'post_status' => 'publish',
				'post_title'  => 'Display event',
			)
		);
		$this->world->set_meta( $other, '_chms_id', 'hidden' );
		$integration->get_chms_item_id( 'missing' );
		$integration->update_chms_id_cache( 'hidden', $other );

		$this->assertNull( $integration->get_chms_item_id( 'hidden' ) );
	}

	public function test_hide_then_unhide_restores_one_published_post() {
		$integration = $this->integration();
		$integration->sync_feed( array( $this->item( 'hidden', 'Family Movie Night' ), $this->item( 'kept', 'Kept' ) ) );
		$tec_id      = $this->only_published( 'tribe_events', 'hidden' );
		$attachment = $this->attach_image( $tec_id );

		$store = new QuietWpEventStore();
		$now   = '2026-08-01 00:00:00';
		EventSync::sync( array( $this->neutral( 'hidden' ), $this->neutral( 'kept' ) ), $store, $now, 'UTC' );
		$event_id = $this->only_published( 'cp_event', 'hidden' );
		$this->assertNotSame( $tec_id, $event_id );

		$integration->flush_chms_id_cache();
		$this->assertSame( $tec_id, (int) $integration->get_chms_item_id( 'hidden' ) );

		VisibilityRemovalNotice::record_skip( 'hidden' );
		$integration->sync_feed( array( $this->item( 'kept', 'Kept' ) ) );
		EventSync::sync( array( $this->neutral( 'kept' ) ), $store, $now, 'UTC' );

		$this->assertSame( 'trash', $this->world->posts[ $tec_id ]['post_status'] );
		$this->assertSame( 'trash', $this->world->posts[ $event_id ]['post_status'] );
		$this->assertSame( $tec_id, (int) $integration->get_chms_item_id( 'hidden' ) );
		$this->assertCount( 6, $this->world->files );
		$this->assertArrayHasKey( $attachment, $this->world->posts );
		$this->assertNotContains( 'wp_delete_attachment', $this->call_names() );
		$this->assertNotContains( 'wp_delete_post', $this->call_names() );
		$this->assertSame( $attachment, (int) $this->world->get_meta( $tec_id, '_thumbnail_id' ) );

		VisibilityRemovalNotice::reset();
		$integration->sync_feed( array( $this->item( 'hidden', 'Family Movie Night' ), $this->item( 'kept', 'Kept' ) ) );
		EventSync::sync( array( $this->neutral( 'hidden' ), $this->neutral( 'kept' ) ), $store, $now, 'UTC' );

		$this->assertSame( array( $tec_id ), $this->ids_with_status( 'tribe_events', 'hidden', 'publish' ) );
		$this->assertSame( array( $event_id ), $this->ids_with_status( 'cp_event', 'hidden', 'publish' ) );
		$this->assertSame( array(), $this->ids_with_status( 'tribe_events', 'hidden', 'trash' ) );
		$this->assertSame( array(), $this->ids_with_status( 'cp_event', 'hidden', 'trash' ) );
		$this->assertContains( 'wp_untrash_post', $this->call_names() );
		$this->assertNotContains( 'wp_publish_post', $this->call_names() );
		$this->assertSame( '', $this->world->get_meta( $tec_id, '_cp_sync_trashed' ) );
		$this->assertSame( $attachment, (int) $this->world->get_meta( $tec_id, '_thumbnail_id' ) );
		$this->assertCount( 6, $this->world->files );
		$this->assertSame( 'publish', $this->world->posts[ $tec_id ]['post_status'] );
		$this->assertSame( 'publish', $this->world->posts[ $event_id ]['post_status'] );
	}

	/**
	 * @return IdCacheIntegration
	 */
	private function integration() {
		$integration = ( new ReflectionClass( IdCacheIntegration::class ) )->newInstanceWithoutConstructor();
		$integration->world = $this->world;
		$integration->actions();
		return $integration;
	}

	/**
	 * @return QueuePathIntegration
	 */
	private function queue_integration() {
		$integration = ( new ReflectionClass( QueuePathIntegration::class ) )->newInstanceWithoutConstructor();
		$integration->world = $this->world;
		$integration->identifier = 'wp_pull_events';
		$integration->cron_hook_identifier = 'wp_pull_events_cron';
		$integration->actions();
		return $integration;
	}

	/**
	 * @return RecreateImageIntegration
	 */
	private function image_integration() {
		$integration = ( new ReflectionClass( RecreateImageIntegration::class ) )->newInstanceWithoutConstructor();
		$integration->world = $this->world;
		$integration->actions();
		return $integration;
	}

	/**
	 * wp_get_attachment_image_src applies its filter, then falls back to a local
	 * file. set_post_thumbnail() keeps the thumbnail only when that source is set.
	 *
	 * @return void
	 */
	private function stub_thumbnail_api() {
		$world = $this->world;

		Functions\when( 'wp_get_attachment_image_src' )->alias(
			static function ( $attachment_id, $size = 'thumbnail' ) use ( $world ) {
				$src = apply_filters( 'wp_get_attachment_image_src', false, $attachment_id, $size );

				if ( ! empty( $src ) ) {
					return $src;
				}

				$file = $world->get_meta( $attachment_id, '_wp_attached_file' );

				if ( is_string( $file ) && '' !== $file && file_exists( $file ) ) {
					return array( 'file://local', 10, 10, false );
				}

				return false;
			}
		);
		Functions\when( 'set_post_thumbnail' )->alias(
			static function ( $post_id, $thumb_id ) use ( $world ) {
				if ( ! $world->get_post( $post_id ) || ! $world->get_post( $thumb_id ) ) {
					return false;
				}

				if ( ! empty( wp_get_attachment_image_src( $thumb_id, 'thumbnail' ) ) ) {
					$world->set_meta( $post_id, '_thumbnail_id', (int) $thumb_id );
					return true;
				}

				unset( $world->posts[ (int) $post_id ]['meta']['_thumbnail_id'] );
				return false;
			}
		);
	}

	/**
	 * @param string $id    ChMS id.
	 * @param string $title Title.
	 * @return array
	 */
	private function item( $id, $title ) {
		return array(
			'chms_id'      => $id,
			'post_title'   => $title,
			'post_content' => 'Hello',
		);
	}

	/**
	 * @param string $id ChMS id.
	 * @return array
	 */
	private function neutral( $id ) {
		return array(
			'provider'   => 'pco',
			'chms_id'    => $id,
			'series_id'  => '',
			'title'      => 'Family Movie Night',
			'content'    => 'Hello',
			'start_utc'  => 'hidden' === $id ? '2026-09-01 15:00:00' : '2026-09-08 15:00:00',
			'end_utc'    => 'hidden' === $id ? '2026-09-01 16:00:00' : '2026-09-08 16:00:00',
		);
	}

	/**
	 * @param int $post_id Event post id.
	 * @return void
	 */
	private function attach_image( $post_id ) {
		$attachment = $this->world->save_post(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_title'  => 'Family Movie Night Thumbnail',
			)
		);
		$this->world->set_meta( $post_id, '_thumbnail_id', $attachment );
		$this->world->files = array(
			'family-movie-night.jpg'          => true,
			'family-movie-night-150x150.jpg'  => true,
			'family-movie-night-300x200.jpg'  => true,
			'family-movie-night-768x512.jpg'  => true,
			'family-movie-night-1024x683.jpg' => true,
			'family-movie-night-scaled.jpg'   => true,
		);
		$this->world->set_meta( $attachment, '_files', array_keys( $this->world->files ) );

		return $attachment;
	}

	/**
	 * @param string $post_type Post type.
	 * @param string $chms_id   ChMS id.
	 * @return int
	 */
	private function only_published( $post_type, $chms_id ) {
		$ids = $this->ids_with_status( $post_type, $chms_id, 'publish' );
		$this->assertCount( 1, $ids );
		return $ids[0];
	}

	/**
	 * @param string $post_type Post type.
	 * @param string $chms_id   ChMS id.
	 * @param string $status    Post status.
	 * @return int[]
	 */
	private function ids_with_status( $post_type, $chms_id, $status ) {
		$ids = array();

		foreach ( $this->world->posts as $id => $post ) {
			if ( $post['post_type'] !== $post_type || $post['post_status'] !== $status ) {
				continue;
			}
			if ( (string) ( $post['meta']['_chms_id'] ?? '' ) !== (string) $chms_id ) {
				continue;
			}
			$ids[] = (int) $id;
		}

		return $ids;
	}

	/**
	 * @return string[]
	 */
	private function call_names() {
		return array_map(
			static function ( $call ) {
				return $call[0];
			},
			$this->world->calls
		);
	}
}

/**
 * Concrete integration whose posts live in PostWorld.
 */
class IdCacheIntegration extends Integration {

	public $id = 'tec';

	public $type = 'events';

	public $label = 'Events';

	/** @var string */
	protected $post_type = 'tribe_events';

	/** @var PostWorld */
	public $world;

	/** @var array */
	public $store = array();

	/** @var array */
	public $pushed = array();

	public function update_item( $item ) {
		$existing = $this->get_chms_item_id( $item['chms_id'] );

		if ( $existing ) {
			$id = (int) $existing;
			$this->world->posts[ $id ]['post_title'] = $item['post_title'] ?? '';
			return $id;
		}

		$id = $this->world->save_post(
			array(
				'post_type'   => 'tribe_events',
				'post_status' => 'publish',
				'post_title'  => $item['post_title'] ?? '',
			)
		);
		$this->world->set_meta( $id, '_chms_id', $item['chms_id'] );

		return $id;
	}

	public function register_taxonomy( $taxonomy, $args ) {}

	public function get_store( $group = null ) {
		return $this->store;
	}

	public function update_store( $items, $group = null, $retain = array() ) {
		$store = array();

		foreach ( $items as $item ) {
			$store[ $item['chms_id'] ] = $this->create_store_key( $item );
		}

		$this->store = $store + $retain;
	}

	public function push_to_queue( $data ) {
		$this->pushed[] = $data;
		return $this;
	}

	public function save() {
		return $this;
	}

	public function dispatch() {
		return array();
	}

	/**
	 * @param array $items Feed items.
	 * @return void
	 */
	public function sync_feed( array $items ) {
		$this->pushed = array();
		$this->process( $items );
		$queued       = $this->pushed;
		$this->pushed = array();

		foreach ( $queued as $item ) {
			$this->task( $item );
		}
	}
}

/**
 * Uses the real queue: process() saves a batch and dispatch() returns, then
 * run_worker() is the later request that calls handle().
 */
class QueuePathIntegration extends IdCacheIntegration {

	/** @var array */
	public $batches = array();

	/** @var int */
	public $dispatched = 0;

	/** @var string */
	public $identifier = 'wp_pull_events';

	/** @var string */
	public $cron_hook_identifier = 'wp_pull_events_cron';

	public function push_to_queue( $data ) {
		if ( ! is_array( $this->data ) ) {
			$this->data = array();
		}

		$this->data[] = $data;
		return $this;
	}

	public function save() {
		if ( ! empty( $this->data ) ) {
			$this->batches[] = (object) array(
				'key'  => 'batch-' . count( $this->batches ),
				'data' => $this->data,
			);
		}

		$this->data = array();
		return $this;
	}

	public function get_batches( $limit = 0 ) {
		$batches = $this->batches;

		if ( $limit ) {
			$batches = array_slice( $batches, 0, $limit );
		}

		return $batches;
	}

	public function update( $key, $data ) {
		foreach ( $this->batches as $batch ) {
			if ( $batch->key === $key ) {
				$batch->data = $data;
			}
		}

		return $this;
	}

	public function delete( $key ) {
		$this->batches = array_values(
			array_filter(
				$this->batches,
				static function ( $batch ) use ( $key ) {
					return $batch->key !== $key;
				}
			)
		);

		return $this;
	}

	public function dispatch() {
		$this->dispatched++;
		return array( 'response' => array( 'code' => 200 ) );
	}

	/**
	 * The queue worker. Skipped ids from the fetch request are already gone.
	 *
	 * @return void
	 */
	public function run_worker() {
		VisibilityRemovalNotice::reset();
		$this->handle();
	}
}

/**
 * Sideloads a new file when the stored attachment cannot be reused.
 */
class RecreateImageIntegration extends IdCacheIntegration {

	/** @var string */
	public $fresh_file = '';

	/** @var int */
	public $created = 0;

	public function sideload_image( $item, $id, $ignore_existing = false ) {
		$normalized = $this->normalize_thumbnail_url( $item['thumbnail_url'] );

		if ( ! $ignore_existing ) {
			$existing = $this->get_existing_attachment( $normalized );

			if ( $existing ) {
				return $existing;
			}
		}

		$this->created++;

		$attachment = $this->world->save_post(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_title'  => 'Fresh logo',
			)
		);
		$this->world->set_meta( $attachment, '_wp_attached_file', $this->fresh_file );
		$this->world->set_meta( $attachment, '_cp_sync_normalized_url', $normalized );

		return $attachment;
	}
}

/**
 * Event store that skips image and category writes.
 */
class QuietWpEventStore extends WpEventStore {

	protected function assign_categories( $post_id, array $names ) {}

	protected function maybe_set_image( $post_id, $url, $title ) {}
}

/**
 * Reset service with the WordPress queue and option deletes stubbed out.
 */
class ResetCacheProbe extends Reset {

	/** @var array */
	public $integrations = array();

	public function clear_queue() {
		return array(
			'batches'      => 0,
			'status_flags' => 0,
			'locks'        => 0,
			'healthchecks' => 0,
		);
	}

	protected function get_integrations() {
		return $this->integrations;
	}

	protected function delete_options_like( $prefix ) {
		return 0;
	}
}

/**
 * In-memory posts, meta, object cache, and image files.
 */
class PostWorld {

	/** @var array */
	public $posts = array();

	/** @var array */
	public $files = array();

	/** @var array */
	public $cache = array();

	/** @var array */
	public $calls = array();

	/** @var array */
	public $filters = array();

	/** @var array */
	public $actions = array();

	/** @var string[] */
	public $logs = array();

	/** @var int */
	private $next = 1;

	public function save_post( $postarr ) {
		if ( is_object( $postarr ) ) {
			$postarr = get_object_vars( $postarr );
		}

		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

		if ( $id > 0 && isset( $this->posts[ $id ] ) ) {
			foreach ( array( 'post_status', 'post_title', 'post_content', 'post_type', 'post_excerpt', 'post_name' ) as $key ) {
				if ( array_key_exists( $key, $postarr ) ) {
					$this->posts[ $id ][ $key ] = $postarr[ $key ];
				}
			}
			return $id;
		}

		$id = $this->next++;
		$this->posts[ $id ] = array(
			'ID'           => $id,
			'post_type'    => $postarr['post_type'] ?? 'post',
			'post_status'  => $postarr['post_status'] ?? 'publish',
			'post_title'   => $postarr['post_title'] ?? '',
			'post_content' => $postarr['post_content'] ?? '',
			'meta'         => array(),
		);

		return $id;
	}

	public function get_post( $post ) {
		if ( is_object( $post ) && isset( $post->ID ) ) {
			$post = $post->ID;
		}

		$id = (int) $post;

		if ( ! isset( $this->posts[ $id ] ) ) {
			return null;
		}

		$object               = new \stdClass();
		$object->ID           = $id;
		$object->post_type    = $this->posts[ $id ]['post_type'];
		$object->post_status  = $this->posts[ $id ]['post_status'];
		$object->post_title   = $this->posts[ $id ]['post_title'];

		return $object;
	}

	public function set_meta( $post_id, $key, $value ) {
		$this->posts[ (int) $post_id ]['meta'][ $key ] = $value;
	}

	public function get_meta( $post_id, $key ) {
		return $this->posts[ (int) $post_id ]['meta'][ $key ] ?? '';
	}

	public function delete_post( $post_id ) {
		unset( $this->posts[ (int) $post_id ] );
	}

	public function trash_post( $post_id ) {
		$id = (int) $post_id;

		if ( ! isset( $this->posts[ $id ] ) ) {
			return null;
		}

		$this->posts[ $id ]['previous_status'] = $this->posts[ $id ]['post_status'];
		$this->posts[ $id ]['post_status']     = 'trash';

		return $this->get_post( $id );
	}

	public function untrash_post( $post_id ) {
		$id = (int) $post_id;

		if ( ! isset( $this->posts[ $id ] ) ) {
			return null;
		}

		if ( 'trash' !== $this->posts[ $id ]['post_status'] ) {
			return false;
		}

		// WordPress 5.6 restores to draft unless wp_untrash_post_status says otherwise.
		$previous = $this->posts[ $id ]['previous_status'] ?? '';
		$status   = apply_filters( 'wp_untrash_post_status', 'draft', $id, $previous );
		$this->posts[ $id ]['post_status'] = ( is_string( $status ) && '' !== $status ) ? $status : 'draft';
		do_action( 'untrashed_post', $id, $previous );

		return $this->get_post( $id );
	}

	public function publish_post( $post_id ) {
		$id = (int) $post_id;

		if ( ! isset( $this->posts[ $id ] ) ) {
			return null;
		}

		$this->posts[ $id ]['post_status'] = 'publish';

		return $this->get_post( $id );
	}

	public function delete_attachment( $post_id ) {
		$id    = (int) $post_id;
		$names = $this->posts[ $id ]['meta']['_files'] ?? array();

		foreach ( $names as $name ) {
			unset( $this->files[ $name ] );
		}

		unset( $this->posts[ $id ] );
	}

	public function ids_for( $post_type, array $statuses ) {
		$ids = array();

		foreach ( $this->posts as $id => $post ) {
			if ( $post['post_type'] === $post_type && in_array( $post['post_status'], $statuses, true ) ) {
				$ids[] = (int) $id;
			}
		}

		sort( $ids );

		return $ids;
	}
}

/**
 * $wpdb stand-in for the ChMS id query and the image-in-use check.
 */
class CacheWpdb {

	public $posts = 'wp_posts';

	public $postmeta = 'wp_postmeta';

	public $options = 'wp_options';

	public $sitemeta = 'wp_sitemeta';

	/** @var PostWorld */
	private $world;

	public function __construct( PostWorld $world ) {
		$this->world = $world;
	}

	public function prepare( $query, ...$args ) {
		return array(
			'query' => $query,
			'args'  => $args,
		);
	}

	public function get_results( $prepared ) {
		$type = is_array( $prepared ) ? ( $prepared['args'][0] ?? '' ) : '';
		$rows = array();

		foreach ( $this->world->posts as $id => $post ) {
			if ( $post['post_type'] !== $type ) {
				continue;
			}

			$chms_id = $post['meta']['_chms_id'] ?? '';

			if ( '' === (string) $chms_id ) {
				continue;
			}

			$rows[] = (object) array(
				'chms_id' => (string) $chms_id,
				'post_id' => (int) $id,
			);
		}

		return $rows;
	}

	public function get_var( $prepared ) {
		if ( ! is_array( $prepared ) ) {
			return null;
		}

		$query = $prepared['query'] ?? '';

		if ( false === strpos( $query, '_cp_sync_normalized_url' ) ) {
			return null;
		}

		$url = $prepared['args'][0] ?? '';

		foreach ( $this->world->posts as $id => $post ) {
			if ( ( $post['meta']['_cp_sync_normalized_url'] ?? '' ) === $url ) {
				return (string) $id;
			}
		}

		return null;
	}
}
