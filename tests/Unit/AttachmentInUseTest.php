<?php
/**
 * Tests for Convenience::attachment_in_use().
 *
 * Sideloaded images are deduplicated by their normalized source URL, so ONE
 * attachment can be the featured image of several synced posts — a series graphic
 * reused across its sermons is exactly that shape. Both deletion paths
 * ( Integration::remove_item and Reset::delete_sideloaded_attachments ) ask this
 * before deleting, so that removing one post cannot blank another's image.
 *
 * $wpdb is a hand-rolled stub: it records the prepared arguments and returns a
 * canned row, which is enough to pin the two things that matter — that the
 * exclusion is applied, and that a non-empty result means "in use".
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\Setup\Convenience;
use PHPUnit\Framework\TestCase;

/**
 * Minimal $wpdb stand-in.
 */
class FakeWpdb {

	/** @var string */
	public $postmeta = 'wp_postmeta';

	/** @var string */
	public $posts = 'wp_posts';

	/** @var mixed What get_var() should return. */
	public $var = null;

	/** @var array The args passed to the last prepare() call. */
	public $prepared = [];

	public function prepare( $query, ...$args ) {
		$this->prepared = $args;
		return $query;
	}

	public function get_var( $query ) {
		return $this->var;
	}
}

/**
 * @covers \CP_Sync\Setup\Convenience::attachment_in_use
 */
class AttachmentInUseTest extends TestCase {

	/** @var FakeWpdb */
	private $wpdb;

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb       = new FakeWpdb();
		$this->wpdb = $wpdb;
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = null;
		parent::tearDown();
	}

	public function test_another_post_using_the_attachment_means_in_use() {
		$this->wpdb->var = 4242;

		$this->assertTrue( Convenience::attachment_in_use( 99 ) );
	}

	public function test_no_rows_means_not_in_use() {
		$this->wpdb->var = null;

		$this->assertFalse( Convenience::attachment_in_use( 99 ) );
	}

	public function test_the_post_being_deleted_is_excluded_from_the_check() {
		// remove_item() asks while the post it is about to delete STILL references the
		// attachment. Without the exclusion every attachment would look in use and none
		// would ever be cleaned up.
		$this->wpdb->var = null;

		Convenience::attachment_in_use( 99, 1234 );

		$this->assertSame( [ 99, 1234 ], $this->wpdb->prepared );
	}

	public function test_excluding_nothing_passes_a_post_id_no_row_can_match() {
		// The reset sweep has no post to exclude; 0 is never a real post id.
		Convenience::attachment_in_use( 99 );

		$this->assertSame( [ 99, 0 ], $this->wpdb->prepared );
	}

	public function test_arguments_are_cast_to_integers() {
		Convenience::attachment_in_use( '99', '1234' );

		$this->assertSame( [ 99, 1234 ], $this->wpdb->prepared );
	}

	public function test_a_falsy_attachment_id_short_circuits_without_querying() {
		// get_post_thumbnail_id() returns 0 for a post with no image; that must not
		// become a query, let alone a truthy "in use" answer.
		$this->wpdb->var = 4242;

		$this->assertFalse( Convenience::attachment_in_use( 0 ) );
		$this->assertSame( [], $this->wpdb->prepared, 'No query should have been prepared' );
	}
}
