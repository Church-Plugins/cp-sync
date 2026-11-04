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

	public function test_dismiss_deletes_the_notice_only_with_a_valid_nonce_and_capability() {
		$option = array(
			'skipped'          => 4,
			'removed_upcoming' => 2,
		);
		$deleted = 0;

		Functions\when( 'current_user_can' )->alias(
			static function ( $cap ) {
				return 'manage_options' === $cap;
			}
		);
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'wp_verify_nonce' )->alias(
			static function ( $nonce, $action ) {
				return 'good' === $nonce && VisibilityRemovalNotice::DISMISS_ACTION === $action;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function ( $key ) use ( &$option, &$deleted ) {
				if ( VisibilityRemovalNotice::OPTION === $key ) {
					$option = null;
					$deleted++;
				}

				return true;
			}
		);

		$_GET[ VisibilityRemovalNotice::DISMISS_QUERY ] = '1';
		$_GET['_wpnonce']                               = 'bad';
		VisibilityRemovalNotice::maybe_dismiss();
		$this->assertSame( 0, $deleted );
		$this->assertNotNull( $option );

		Functions\when( 'current_user_can' )->justReturn( false );
		$_GET['_wpnonce'] = 'good';
		VisibilityRemovalNotice::maybe_dismiss();
		$this->assertSame( 0, $deleted );

		Functions\when( 'current_user_can' )->alias(
			static function ( $cap ) {
				return 'manage_options' === $cap;
			}
		);
		VisibilityRemovalNotice::maybe_dismiss();
		$this->assertSame( 1, $deleted );
		$this->assertNull( $option );

		unset( $_GET[ VisibilityRemovalNotice::DISMISS_QUERY ], $_GET['_wpnonce'] );
	}

	public function test_a_newer_removal_replaces_the_notice() {
		$stored = null;

		Functions\when( 'update_option' )->alias(
			static function ( $key, $value ) use ( &$stored ) {
				$stored = array( $key, $value );

				return true;
			}
		);
		Functions\when( 'delete_option' )->justReturn( true );

		VisibilityRemovalNotice::publish( array( 'a' ), array( 'a' ) );
		$this->assertSame( 1, $stored[1]['removed_upcoming'] );

		VisibilityRemovalNotice::publish( array( 'c', 'd', 'e' ), array( 'c', 'd' ) );

		$this->assertSame( VisibilityRemovalNotice::OPTION, $stored[0] );
		$this->assertSame( 3, $stored[1]['skipped'] );
		$this->assertSame( 2, $stored[1]['removed_upcoming'] );
	}

	public function test_render_includes_a_nonce_dismiss_link_for_admins() {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn(
			array(
				'skipped'          => 4,
				'removed_upcoming' => 2,
			)
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( $key, $value ) {
				return 'https://example.test/wp-admin/?' . $key . '=' . $value;
			}
		);
		Functions\when( 'wp_nonce_url' )->alias(
			static function ( $url, $action ) {
				return $url . '&_wpnonce=nonce&action=' . $action;
			}
		);
		Functions\when( 'esc_url' )->returnArg( 1 );

		ob_start();
		VisibilityRemovalNotice::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'cp-sync-visibility-notice__dismiss', $html );
		$this->assertStringContainsString( 'Dismiss', $html );
		$this->assertStringContainsString( VisibilityRemovalNotice::DISMISS_QUERY . '=1', $html );
		$this->assertStringContainsString( VisibilityRemovalNotice::DISMISS_ACTION, $html );
	}
}
