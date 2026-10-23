<?php
/**
 * pull_content() keeps pulling the remaining types after a failure.
 *
 * Groups runs first. A transient Groups API error must not block the Events
 * and Sermons pulls in the same run, and the first real failure is still
 * returned to the caller ( cron log, REST /pull ).
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Integrations\_Init;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * @covers \CP_Sync\Integrations\_Init::pull_content
 */
class PullContentTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ) {
				return $thing instanceof WP_Error;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build an _Init whose pull_integration() returns a fixed result per type.
	 *
	 * @param array $results Result keyed by type. Missing types return true.
	 * @param array $pulled  Receives each type in the order it was pulled.
	 * @return _Init
	 */
	private function init_with_results( array $results, array &$pulled ) {
		$init = $this->getMockBuilder( _Init::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'pull_integration' ] )
			->getMock();

		$init->method( 'pull_integration' )->willReturnCallback(
			static function ( $type ) use ( $results, &$pulled ) {
				$pulled[] = $type;
				return $results[ $type ] ?? true;
			}
		);

		return $init;
	}

	public function test_all_types_succeed_returns_true() {
		$pulled = [];
		$init   = $this->init_with_results( [], $pulled );

		$this->assertTrue( $init->pull_content() );
		$this->assertSame( [ 'groups', 'events', 'sermons' ], $pulled );
	}

	public function test_groups_failure_still_pulls_events_and_sermons() {
		$pulled = [];
		$error  = new WP_Error( 'pco_fetch_error', 'Groups request failed' );
		$init   = $this->init_with_results( [ 'groups' => $error ], $pulled );

		$this->assertSame( $error, $init->pull_content() );
		$this->assertSame( [ 'groups', 'events', 'sermons' ], $pulled );
	}

	public function test_first_failure_is_returned_when_several_fail() {
		$pulled  = [];
		$groups  = new WP_Error( 'pco_fetch_error', 'Groups request failed' );
		$sermons = new WP_Error( 'pco_fetch_error', 'Sermons request failed' );
		$init    = $this->init_with_results( [ 'groups' => $groups, 'sermons' => $sermons ], $pulled );

		$this->assertSame( $groups, $init->pull_content() );
		$this->assertSame( [ 'groups', 'events', 'sermons' ], $pulled );
	}

	public function test_disabled_and_unavailable_types_are_not_failures() {
		$pulled = [];
		$init   = $this->init_with_results(
			[
				'groups'  => new WP_Error( 'integration_disabled', 'Groups disabled' ),
				'sermons' => new WP_Error( 'integration_unavailable', 'CP Sermons missing' ),
			],
			$pulled
		);

		$this->assertTrue( $init->pull_content() );
		$this->assertSame( [ 'groups', 'events', 'sermons' ], $pulled );
	}
}
