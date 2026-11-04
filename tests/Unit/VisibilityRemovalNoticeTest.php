<?php
/**
 * Counts and admin notice for visibility skips that remove upcoming events.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Events\VisibilityRemovalNotice;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Events\VisibilityRemovalNotice
 */
class VisibilityRemovalNoticeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		VisibilityRemovalNotice::reset();
	}

	protected function tearDown(): void {
		VisibilityRemovalNotice::reset();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_summarize_counts_only_skipped_ids_that_were_removed() {
		$summary = VisibilityRemovalNotice::summarize(
			[ 'hidden-1', 'never-imported', 12, 'hidden-1' ],
			[ 'hidden-1', 'other', 12 ]
		);

		$this->assertSame( 3, $summary['skipped'] );
		$this->assertSame( 2, $summary['removed_upcoming'] );
	}

	public function test_message_includes_both_counts_only_when_upcoming_events_were_removed() {
		$this->assertSame( '', VisibilityRemovalNotice::message( [ 'skipped' => 4, 'removed_upcoming' => 0 ] ) );

		$message = VisibilityRemovalNotice::message( [ 'skipped' => 4, 'removed_upcoming' => 2 ] );

		$this->assertStringContainsString( '4', $message );
		$this->assertStringContainsString( '2', $message );
		$this->assertStringContainsString( 'upcoming', $message );
	}

	public function test_publish_keeps_the_notice_when_a_later_sync_removes_nothing() {
		$stored  = null;
		$deleted = 0;

		Functions\when( 'update_option' )->alias(
			static function ( $key, $value ) use ( &$stored ) {
				$stored = [ $key, $value ];
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function () use ( &$deleted ) {
				$deleted++;
				return true;
			}
		);

		$summary = VisibilityRemovalNotice::publish( [ 'a', 'b' ], [ 'a' ] );

		$this->assertSame( 2, $summary['skipped'] );
		$this->assertSame( 1, $summary['removed_upcoming'] );
		$this->assertSame( VisibilityRemovalNotice::OPTION, $stored[0] );
		$this->assertSame( 0, $deleted );

		VisibilityRemovalNotice::publish( [ 'a' ], [] );

		$this->assertSame( 0, $deleted );
		$this->assertSame( 1, $stored[1]['removed_upcoming'] );
	}

	public function test_render_shows_the_counts_to_admins_and_stays_quiet_otherwise() {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn(
			[
				'skipped'          => 4,
				'removed_upcoming' => 2,
			]
		);

		ob_start();
		VisibilityRemovalNotice::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( '4', $html );
		$this->assertStringContainsString( '2', $html );

		Functions\when( 'get_option' )->justReturn( [ 'skipped' => 4, 'removed_upcoming' => 0 ] );

		ob_start();
		VisibilityRemovalNotice::render();
		$this->assertSame( '', ob_get_clean() );

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( [ 'skipped' => 4, 'removed_upcoming' => 2 ] );

		ob_start();
		VisibilityRemovalNotice::render();
		$this->assertSame( '', ob_get_clean() );
	}
}
