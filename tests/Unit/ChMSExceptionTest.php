<?php
/**
 * ChMSException accepts the string error codes the ChMS throws.
 *
 * PHP 8 rejects a string as Exception::$code, so `new ChMSException( 'pco_fetch_error', ... )`
 * used to be an uncaught TypeError. The string is kept separately, and a numeric
 * code ( including the string "429" ) still lands on getCode() so rate limits retry.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\ChMS\ChMSException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\ChMSException
 */
class ChMSExceptionTest extends TestCase {

	public function test_string_error_code_does_not_raise_a_type_error() {
		$exception = new ChMSException(
			'pco_fetch_error',
			[
				'errors' => [
					[ 'detail' => 'server exploded' ],
				],
			]
		);

		$this->assertSame( 0, $exception->getCode() );
		$this->assertSame( 'pco_fetch_error', $exception->getErrorCode() );
		$this->assertStringContainsString( 'server exploded', $exception->getMessage() );
	}

	public function test_numeric_code_stays_an_integer_for_rate_limit_retries() {
		$exception = new ChMSException( '429', 'slow down', [ 'wait' => 3 ] );

		$this->assertSame( 429, $exception->getCode() );
		$this->assertSame( 429, $exception->getErrorCode() );
		$this->assertSame( [ 'wait' => 3 ], $exception->getData() );
	}
}
