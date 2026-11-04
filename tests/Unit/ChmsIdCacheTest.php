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
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);
		Functions\when( 'do_action' )->justReturn( null );
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
		$plugin->logging = new class() {
			public function log( $message ) {}
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

	public function test_trashed_post_is_kept_and_restored() {
		$integration = $this->integration();
		$integration->sync_feed( array( $this->item( 'hidden', 'Hidden' ), $this->item( 'kept', 'Kept' ) ) );
		$original = $this->only_published( 'tribe_events', 'hidden' );

		$this->world->trash_post( $original );
		$this->assertSame( $original, (int) $integration->get_chms_item_id( 'hidden' ) );

		$integration->flush_chms_id_cache();
		$this->assertSame( $original, (int) $integration->get_chms_item_id( 'hidden' ) );

		$integration->sync_feed( array( $this->item( 'hidden', 'Shown' ), $this->item( 'kept', 'Kept' ) ) );

		$this->assertSame( array( $original ), $this->ids_with_status( 'tribe_events', 'hidden', 'publish' ) );
		$this->assertSame( array(), $this->ids_with_status( 'tribe_events', 'hidden', 'trash' ) );
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
		$this->assertContains( 'wp_publish_post', $this->call_names() );
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
		return $integration;
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
			$this->world->posts[ $id ]['post_title']  = $item['post_title'] ?? '';
			$this->world->posts[ $id ]['post_status'] = 'publish';
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

		$previous = $this->posts[ $id ]['previous_status'] ?? 'publish';
		$this->posts[ $id ]['post_status'] = $previous ? $previous : 'publish';

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
		return null;
	}
}
