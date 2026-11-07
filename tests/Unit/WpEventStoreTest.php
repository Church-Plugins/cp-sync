<?php
/**
 * Updates to CP Sync event posts keep the public permalink stable.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Events\WpEventStore;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Events\WpEventStore
 */
class WpEventStoreTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_slash' )->returnArg( 1 );
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'wp_set_post_terms' )->justReturn( array() );
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_an_update_keeps_the_slug_date_and_author() {
		$existing = array(
			'ID'           => 42,
			'post_name'    => 'youth-night-2026-11-05',
			'post_date'    => '2026-11-01 10:00:00',
			'post_author'  => 7,
			'post_title'   => 'Youth Night',
			'post_content' => 'Old',
			'post_excerpt' => 'Old excerpt',
			'post_status'  => 'publish',
			'post_type'    => 'cp_event',
		);
		$written  = null;

		Functions\when( 'wp_update_post' )->alias(
			static function ( $postarr ) use ( &$written, $existing ) {
				$merged  = array_merge( $existing, $postarr );
				$written = $merged;

				return (int) $merged['ID'];
			}
		);
		Functions\when( 'wp_insert_post' )->alias(
			static function ( $postarr ) use ( &$written ) {
				$written = $postarr;

				return (int) ( $postarr['ID'] ?? 0 );
			}
		);

		$store = new WpEventStore();
		$id    = $store->save_event(
			array(
				'post_id'          => 42,
				'title'            => 'Youth Night renamed',
				'content'          => 'New body',
				'excerpt'          => 'New excerpt',
				'provider'         => 'pco',
				'occurrence_id'    => 'inst-1',
				'source_id'        => 'e1',
				'start_utc'        => '2026-11-05 23:00:00',
				'end_utc'          => '2026-11-06 01:00:00',
				'start_local'      => '2026-11-05 18:00:00',
				'end_local'        => '2026-11-05 20:00:00',
				'timezone'         => 'America/Chicago',
				'image_url'        => '',
				'registration_url' => 'https://example.com/reg',
				'categories'       => array(),
			)
		);

		$this->assertSame( 42, $id );
		$this->assertIsArray( $written );
		$this->assertSame( 'youth-night-2026-11-05', $written['post_name'] );
		$this->assertSame( '2026-11-01 10:00:00', $written['post_date'] );
		$this->assertSame( 7, (int) $written['post_author'] );
		$this->assertSame( 'Youth Night renamed', $written['post_title'] );
	}
}
