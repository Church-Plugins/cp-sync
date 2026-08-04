<?php
/**
 * Tests for CP_Sync\ChMS\PCO::api_error_to_rest_error() — flattening the decoded
 * PCO error payload ( [ 'errors' => [ [ 'status', 'title', 'detail' ] ] ] ) into a
 * REST-ready ChMSError that carries the HTTP status, so the client can tell
 * permission failures ( 401/403 → `pco_permission_denied` ) from transient ones.
 *
 * The method is protected, so a lightweight anonymous subclass exposes it —
 * mirroring SyncToggleFieldsTest.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\PCO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\PCO::api_error_to_rest_error
 */
class ApiErrorToRestErrorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'absint' )->alias( fn( $v ) => abs( (int) $v ) );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * A PCO double exposing the protected converter.
	 *
	 * @return PCO
	 */
	private function makePco(): PCO {
		return new class extends PCO {
			// Base constructor registers hooks; replace with a no-op.
			public function __construct() {}

			public function expose_api_error( $error ) {
				return $this->api_error_to_rest_error( $error );
			}
		};
	}

	/** A 403 body maps to the permission-denied code with the status forwarded. */
	public function test_403_maps_to_permission_denied() {
		$error = $this->makePco()->expose_api_error( [
			'errors' => [
				[ 'status' => '403', 'title' => 'Forbidden', 'detail' => 'You do not have access to Groups tags.' ],
			],
		] );

		$this->assertSame( 'pco_permission_denied', $error->get_error_code() );
		$this->assertSame( 'You do not have access to Groups tags.', $error->get_error_message() );
		$this->assertSame( [ 'status' => 403 ], $error->data );
	}

	/** 401 is treated as a permission failure too ( expired/insufficient token ). */
	public function test_401_maps_to_permission_denied() {
		$error = $this->makePco()->expose_api_error( [
			'errors' => [ [ 'status' => '401', 'title' => 'Unauthorized' ] ],
		] );

		$this->assertSame( 'pco_permission_denied', $error->get_error_code() );
	}

	/** Other HTTP statuses stay generic fetch errors but keep their status. */
	public function test_non_permission_status_stays_fetch_error() {
		$error = $this->makePco()->expose_api_error( [
			'errors' => [ [ 'status' => '404', 'title' => 'Not Found', 'detail' => 'The resource was not found.' ] ],
		] );

		$this->assertSame( 'pco_fetch_error', $error->get_error_code() );
		$this->assertSame( [ 'status' => 404 ], $error->data );
	}

	/** With no `detail`, the `title` becomes the message. */
	public function test_title_used_when_detail_missing() {
		$error = $this->makePco()->expose_api_error( [
			'errors' => [ [ 'status' => '403', 'title' => 'Forbidden' ] ],
		] );

		$this->assertSame( 'Forbidden', $error->get_error_message() );
	}

	/** A plain-string payload ( e.g. the Guzzle fallback ) is passed through at 500. */
	public function test_string_payload() {
		$error = $this->makePco()->expose_api_error( 'Unknown Exception in Guzzle request' );

		$this->assertSame( 'pco_fetch_error', $error->get_error_code() );
		$this->assertSame( 'Unknown Exception in Guzzle request', $error->get_error_message() );
		$this->assertSame( [ 'status' => 500 ], $error->data );
	}

	/** Malformed/empty payloads degrade to a default message at 500, never notices. */
	public function test_malformed_payload_defaults() {
		foreach ( [ null, [], [ 'errors' => [] ], [ 'errors' => [ [] ] ] ] as $payload ) {
			$error = $this->makePco()->expose_api_error( $payload );

			$this->assertSame( 'pco_fetch_error', $error->get_error_code() );
			$this->assertSame( 'The request to Planning Center failed.', $error->get_error_message() );
			$this->assertSame( [ 'status' => 500 ], $error->data );
		}
	}
}
