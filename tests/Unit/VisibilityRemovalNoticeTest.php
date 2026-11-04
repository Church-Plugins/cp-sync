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
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );
		Functions\when( 'get_user_meta' )->justReturn( '' );
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

		$this->assertStringContainsString( 'notice notice-warning is-dismissible', $html );
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

	public function test_dismissal_hides_the_notice_only_for_that_user_until_a_newer_removal() {
		$option       = null;
		$meta         = array();
		$current_user = 7;

		Functions\when( 'update_option' )->alias(
			static function ( $key, $value ) use ( &$option ) {
				$option = $value;

				return true;
			}
		);
		Functions\when( 'get_option' )->alias(
			static function () use ( &$option ) {
				return $option;
			}
		);
		Functions\when( 'get_current_user_id' )->alias(
			static function () use ( &$current_user ) {
				return $current_user;
			}
		);
		Functions\when( 'update_user_meta' )->alias(
			static function ( $user_id, $key, $value ) use ( &$meta ) {
				$meta[ $user_id ][ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'get_user_meta' )->alias(
			static function ( $user_id, $key ) use ( &$meta ) {
				return $meta[ $user_id ][ $key ] ?? '';
			}
		);
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

		VisibilityRemovalNotice::publish( array( 'a' ), array( 'a' ) );
		$first_id = $option['id'];
		$this->assertNotSame( '', $first_id );

		ob_start();
		VisibilityRemovalNotice::render();
		$this->assertStringContainsString( 'is-dismissible', ob_get_clean() );

		$_GET[ VisibilityRemovalNotice::DISMISS_QUERY ]    = '1';
		$_GET[ VisibilityRemovalNotice::NOTICE_ID_QUERY ] = $first_id;
		$_GET['_wpnonce']                                  = 'bad';
		VisibilityRemovalNotice::maybe_dismiss();
		$this->assertArrayNotHasKey( 7, $meta );

		$_GET['_wpnonce'] = 'good';
		Functions\when( 'current_user_can' )->justReturn( false );
		VisibilityRemovalNotice::maybe_dismiss();
		$this->assertArrayNotHasKey( 7, $meta );

		Functions\when( 'current_user_can' )->alias(
			static function ( $cap ) {
				return 'manage_options' === $cap;
			}
		);
		VisibilityRemovalNotice::maybe_dismiss();
		$this->assertSame( $first_id, $meta[7][ VisibilityRemovalNotice::USER_META ] );

		ob_start();
		VisibilityRemovalNotice::render();
		$this->assertSame( '', ob_get_clean() );

		$current_user = 8;
		ob_start();
		VisibilityRemovalNotice::render();
		$this->assertStringContainsString( 'is-dismissible', ob_get_clean() );

		$current_user = 7;
		VisibilityRemovalNotice::publish( array( 'c', 'd' ), array( 'c', 'd' ) );
		$this->assertNotSame( $first_id, $option['id'] );

		ob_start();
		VisibilityRemovalNotice::render();
		$again = ob_get_clean();
		$this->assertStringContainsString( 'is-dismissible', $again );
		$this->assertStringContainsString( 'notice-warning', $again );

		unset(
			$_GET[ VisibilityRemovalNotice::DISMISS_QUERY ],
			$_GET[ VisibilityRemovalNotice::NOTICE_ID_QUERY ],
			$_GET['_wpnonce']
		);
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
		$this->assertNotSame( '', $stored[1]['id'] );
	}

	public function test_a_notice_without_an_id_can_be_dismissed() {
		$option       = array(
			'skipped'          => 2,
			'removed_upcoming' => 1,
		);
		$meta         = array();
		$current_user = 4;

		Functions\when( 'get_option' )->alias(
			static function () use ( &$option ) {
				return $option;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( $key, $value ) use ( &$option ) {
				if ( VisibilityRemovalNotice::OPTION === $key ) {
					$option = $value;
				}

				return true;
			}
		);
		Functions\when( 'get_current_user_id' )->alias(
			static function () use ( &$current_user ) {
				return $current_user;
			}
		);
		Functions\when( 'update_user_meta' )->alias(
			static function ( $user_id, $key, $value ) use ( &$meta ) {
				$meta[ $user_id ][ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'get_user_meta' )->alias(
			static function ( $user_id, $key ) use ( &$meta ) {
				return $meta[ $user_id ][ $key ] ?? '';
			}
		);
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'add_query_arg' )->alias(
			static function ( $key, $value, $url = '' ) {
				$base = is_string( $url ) && '' !== $url ? $url : 'https://example.test/wp-admin/';
				$join = false === strpos( $base, '?' ) ? '?' : '&';

				return $base . $join . $key . '=' . $value;
			}
		);
		Functions\when( 'wp_nonce_url' )->returnArg( 1 );
		Functions\when( 'esc_url' )->returnArg( 1 );

		ob_start();
		VisibilityRemovalNotice::render();
		$html = ob_get_clean();

		$this->assertArrayHasKey( 'id', $option );
		$this->assertNotSame( '', $option['id'] );
		$this->assertStringContainsString( 'is-dismissible', $html );
		$this->assertStringContainsString( $option['id'], $html );

		$_GET[ VisibilityRemovalNotice::DISMISS_QUERY ]   = '1';
		$_GET[ VisibilityRemovalNotice::NOTICE_ID_QUERY ] = $option['id'];
		$_GET['_wpnonce']                                 = 'good';
		VisibilityRemovalNotice::maybe_dismiss();

		$this->assertSame( $option['id'], $meta[4][ VisibilityRemovalNotice::USER_META ] );

		ob_start();
		VisibilityRemovalNotice::render();
		$this->assertSame( '', ob_get_clean() );

		$current_user = 5;
		ob_start();
		VisibilityRemovalNotice::render();
		$this->assertStringContainsString( 'is-dismissible', ob_get_clean() );

		unset(
			$_GET[ VisibilityRemovalNotice::DISMISS_QUERY ],
			$_GET[ VisibilityRemovalNotice::NOTICE_ID_QUERY ],
			$_GET['_wpnonce']
		);
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
			static function ( $key, $value, $url = '' ) {
				$base = is_string( $url ) && '' !== $url ? $url : 'https://example.test/wp-admin/';
				$join = false === strpos( $base, '?' ) ? '?' : '&';

				return $base . $join . $key . '=' . $value;
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
		$this->assertStringContainsString( 'display:inline-flex;align-items:center;min-height:44px', $html );
		$this->assertStringContainsString( 'Dismiss', $html );
		$this->assertStringContainsString( VisibilityRemovalNotice::DISMISS_QUERY . '=1', $html );
		$this->assertStringContainsString( VisibilityRemovalNotice::DISMISS_ACTION, $html );
	}
}
