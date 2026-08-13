<?php
/**
 * Tests for PlanningCenterAPI::rateLimitWait().
 *
 * The 429 retry honors PCO's Retry-After header but must never sleep unbounded
 * ( a huge or garbage header would stall the crawl worse than the rate limit
 * itself ). Pure static logic, no WP or HTTP needed.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PlanningCenterAPI\PlanningCenterAPI;

/**
 * @covers \PlanningCenterAPI\PlanningCenterAPI::rateLimitWait
 */
class RateLimitWaitTest extends TestCase {

	public function test_honors_a_normal_retry_after_header() {
		$this->assertSame( 12, PlanningCenterAPI::rateLimitWait( '12' ) );
		$this->assertSame( 12, PlanningCenterAPI::rateLimitWait( 12 ) );
	}

	public function test_missing_or_garbage_header_falls_back_to_default() {
		$this->assertSame( PlanningCenterAPI::DEFAULT_RATE_LIMIT_WAIT, PlanningCenterAPI::rateLimitWait( '' ) );
		$this->assertSame( PlanningCenterAPI::DEFAULT_RATE_LIMIT_WAIT, PlanningCenterAPI::rateLimitWait( null ) );
		$this->assertSame( PlanningCenterAPI::DEFAULT_RATE_LIMIT_WAIT, PlanningCenterAPI::rateLimitWait( 'soon' ) );
	}

	public function test_zero_and_negative_values_fall_back_to_default() {
		$this->assertSame( PlanningCenterAPI::DEFAULT_RATE_LIMIT_WAIT, PlanningCenterAPI::rateLimitWait( '0' ) );
		$this->assertSame( PlanningCenterAPI::DEFAULT_RATE_LIMIT_WAIT, PlanningCenterAPI::rateLimitWait( -5 ) );
	}

	public function test_huge_header_is_clamped_to_the_ceiling() {
		$this->assertSame( PlanningCenterAPI::MAX_RATE_LIMIT_WAIT, PlanningCenterAPI::rateLimitWait( '3600' ) );
	}
}
