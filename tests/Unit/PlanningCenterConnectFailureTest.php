<?php
/**
 * A network failure ( Guzzle ConnectException ) has no HTTP response.
 * The client used to call getResponse() on it, which does not exist and
 * aborted the groups sync. The request must fail closed instead.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use PlanningCenterAPI\PlanningCenterAPI;

/**
 * @covers \PlanningCenterAPI\PlanningCenterAPI
 */
class PlanningCenterConnectFailureTest extends TestCase {

	public function test_connection_failure_returns_false_and_keeps_the_message() {
		$api    = new PlanningCenterAPI();
		$client = new class() {
			public function request() {
				throw new ConnectException(
					'Connection refused',
					new Request( 'GET', 'https://api.planningcenteronline.com/groups/v2/memberships' )
				);
			}
		};

		$execute = new \ReflectionMethod( PlanningCenterAPI::class, 'execute' );
		$execute->setAccessible( true );

		$result = $execute->invoke( $api, 'https://api.planningcenteronline.com/groups/v2/memberships', $client );

		$this->assertFalse( $result );
		$this->assertSame( 'Connection refused', $api->errorMessage() );
	}
}
