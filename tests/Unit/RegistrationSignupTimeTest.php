<?php
/**
 * Tests for which Planning Center signup time registration filters and import use.
 *
 * The selector ignores API order: the next upcoming starts_at, or the most
 * recent past time when none are upcoming. The date filter and
 * to_neutral_registration_event() both use that time.
 *
 * A signup with no usable time is not given a time id. On this branch a date
 * filter still hits DataFilter's missing-relation error for that signup
 * (issue #72). These tests cover the selector and the import, and they only
 * run a date filter over signups that have a usable time.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\PCO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\PCO::select_registration_signup_time_id
 * @covers \CP_Sync\ChMS\PCO::apply_registration_filter
 * @covers \CP_Sync\ChMS\PCO::to_neutral_registration_event
 */
class RegistrationSignupTimeTest extends TestCase {

	/**
	 * 2026-11-04 12:00:00 UTC.
	 *
	 * @var int
	 */
	private $now;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->now = ( new \DateTimeImmutable( '2026-11-04T12:00:00Z' ) )->getTimestamp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_wp_error' )->alias(
			static fn( $thing ) => $thing instanceof \WP_Error
		);
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'UTC' ) );
		Functions\when( 'cp_sync' )->justReturn(
			new class() {
				public $logging;
				public function __construct() {
					$this->logging = new class() {
						public function log( $message ) {}
					};
				}
			}
		);
		Functions\when( 'sanitize_title' )->alias(
			static fn( $title ) => strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( $title ) ) )
		);
		Functions\when( 'wp_list_pluck' )->alias(
			static function ( $list, $field ) {
				return array_map(
					static fn( $row ) => is_array( $row ) ? ( $row[ $field ] ?? null ) : ( $row->$field ?? null ),
					$list
				);
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function makePco(): PCO {
		return ( new \ReflectionClass( PCO::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * @param string[] $time_ids SignupTime ids, in API order.
	 */
	private function signup( string $id, array $time_ids ): array {
		$data = [];
		foreach ( $time_ids as $time_id ) {
			$data[] = [ 'type' => 'SignupTime', 'id' => $time_id ];
		}

		return [
			'id'            => $id,
			'attributes'    => [
				'name'                 => 'Signup ' . $id,
				'description'          => '',
				'new_registration_url' => 'https://church.center/signups/' . $id,
			],
			'relationships' => [
				'signup_times'    => [ 'data' => $data ],
				'categories'      => [ 'data' => [] ],
				'signup_location' => [ 'data' => [] ],
			],
		];
	}

	/**
	 * @param array<string, array{0: string, 1: string}> $specs Id => [ starts_at, ends_at ].
	 */
	private function times( array $specs ): array {
		$records = [];
		foreach ( $specs as $id => $range ) {
			$records[ $id ] = [
				'id'         => $id,
				'attributes' => [
					'starts_at' => $range[0],
					'ends_at'   => $range[1],
					'all_day'   => false,
				],
			];
		}

		return [ 'SignupTime' => $records ];
	}

	private function cond( string $selector, string $compare, string $value ): array {
		return [
			'selector' => $selector,
			'compare'  => $compare,
			'value'    => $value,
		];
	}

	/**
	 * @param array $items
	 * @param array $relational
	 * @param array $conditions
	 * @return array|\WP_Error
	 */
	private function filter( array $items, array $relational, array $conditions ) {
		return $this->makePco()->apply_registration_filter(
			$items,
			$relational,
			[
				'type'       => 'all',
				'conditions' => $conditions,
			],
			$this->now
		);
	}

	private function import( array $signup, array $relational, $now = null ) {
		$context = [ 'relational_data' => $relational ];
		if ( null !== $now ) {
			$context['now'] = $now;
		}

		return $this->makePco()->to_neutral_registration_event( $signup, $context );
	}

	public function test_unordered_times_use_the_next_upcoming_time_not_api_order(): void {
		// API order is Dec, Oct, Nov. The first row is a later future time.
		// A start-date threshold of Nov 25 would keep Dec and drop Nov 20.
		$relational = $this->times( [
			'dec' => [ '2026-12-10T15:00:00Z', '2026-12-10T17:00:00Z' ],
			'oct' => [ '2026-10-15T15:00:00Z', '2026-10-15T17:00:00Z' ],
			'nov' => [ '2026-11-20T15:00:00Z', '2026-11-20T17:00:00Z' ],
		] );
		$signup     = $this->signup( 'unordered', [ 'dec', 'oct', 'nov' ] );

		$this->assertSame( 'nov', PCO::select_registration_signup_time_id( $signup, $relational, $this->now ) );

		$kept = $this->filter(
			[ $signup ],
			$relational,
			[ $this->cond( 'start_date', 'is_greater_than', '2026-11-25T00:00:00Z' ) ]
		);
		$this->assertSame( [], array_column( $kept, 'id' ) );

		$neutral = $this->import( $signup, $relational, $this->now );
		$this->assertSame( '2026-11-20 15:00:00', $neutral['start_utc'] );
		$this->assertSame( '2026-11-20 17:00:00', $neutral['end_utc'] );
	}

	public function test_mix_of_past_and_future_picks_the_earliest_upcoming_time(): void {
		// Support repro: the API lists the past time first. A future start-date
		// filter used to drop the signup. The upcoming time is the one that
		// matters, including its end — not the past time's later end.
		$relational = $this->times( [
			'oct' => [ '2026-10-15T15:00:00Z', '2026-12-20T17:00:00Z' ],
			'nov' => [ '2026-11-20T15:00:00Z', '2026-11-20T17:00:00Z' ],
			'dec' => [ '2026-12-10T15:00:00Z', '2026-12-10T17:00:00Z' ],
		] );
		$signup     = $this->signup( 'mixed', [ 'oct', 'dec', 'nov' ] );

		$this->assertSame( 'nov', PCO::select_registration_signup_time_id( $signup, $relational, $this->now ) );

		$by_start = $this->filter(
			[ $signup ],
			$relational,
			[ $this->cond( 'start_date', 'is_greater_than', '2026-11-01T00:00:00Z' ) ]
		);
		$this->assertSame( [ 'mixed' ], array_column( $by_start, 'id' ) );

		$by_end = $this->filter(
			[ $signup ],
			$relational,
			[ $this->cond( 'end_date', 'is_greater_than', '2026-12-01T00:00:00Z' ) ]
		);
		$this->assertSame( [], array_column( $by_end, 'id' ) );

		$neutral = $this->import( $by_start[0], $relational );
		$this->assertSame( '2026-11-20 15:00:00', $neutral['start_utc'] );
		$this->assertSame( '2026-11-20 17:00:00', $neutral['end_utc'] );
	}

	public function test_all_past_times_fall_back_to_the_most_recent(): void {
		// API order is the older time first. Filtering on Oct 1 keeps the
		// signup because Oct 15 is selected, not Sep 1.
		$relational = $this->times( [
			'sep' => [ '2026-09-01T15:00:00Z', '2026-09-01T17:00:00Z' ],
			'oct' => [ '2026-10-15T15:00:00Z', '2026-10-15T17:00:00Z' ],
		] );
		$signup     = $this->signup( 'past', [ 'sep', 'oct' ] );

		$this->assertSame( 'oct', PCO::select_registration_signup_time_id( $signup, $relational, $this->now ) );

		$kept = $this->filter(
			[ $signup ],
			$relational,
			[ $this->cond( 'start_date', 'is_greater_than', '2026-10-01T00:00:00Z' ) ]
		);
		$this->assertSame( [ 'past' ], array_column( $kept, 'id' ) );

		$neutral = $this->import( $kept[0], $relational );
		$this->assertSame( '2026-10-15 15:00:00', $neutral['start_utc'] );
		$this->assertSame( '2026-10-15 17:00:00', $neutral['end_utc'] );
	}

	/**
	 * No usable time: the selector returns null and the import skips the signup.
	 * A sibling that has a time still imports. A date filter over the signup
	 * with no time is issue #72 (this branch does not change DataFilter).
	 */
	public function test_no_times_selects_nothing_and_import_skips_without_blocking_a_sibling(): void {
		$relational = $this->times( [
			'dec'   => [ '2026-12-10T15:00:00Z', '2026-12-10T17:00:00Z' ],
			'blank' => [ '', '2026-12-10T17:00:00Z' ],
		] );
		$empty      = $this->signup( 'ongoing', [] );
		$dangling   = $this->signup( 'dangling', [ 'missing' ] );
		$unusable   = $this->signup( 'blank', [ 'blank' ] );
		$dated      = $this->signup( 'dated', [ 'dec' ] );

		$this->assertNull( PCO::select_registration_signup_time_id( $empty, $relational, $this->now ) );
		$this->assertNull( PCO::select_registration_signup_time_id( $dangling, $relational, $this->now ) );
		$this->assertNull( PCO::select_registration_signup_time_id( $unusable, $relational, $this->now ) );

		$this->assertFalse( $this->import( $empty, $relational, $this->now ) );
		$this->assertFalse( $this->import( $dangling, $relational, $this->now ) );
		$this->assertFalse( $this->import( $unusable, $relational, $this->now ) );

		$neutral = $this->import( $dated, $relational, $this->now );
		$this->assertSame( '2026-12-10 15:00:00', $neutral['start_utc'] );

		// No date condition: the ongoing signup stays in the list and is skipped
		// at import. The dated signup is stamped and imports on its time.
		$unfiltered = $this->filter( [ $empty, $dated ], $relational, [] );
		$this->assertSame( [ 'ongoing', 'dated' ], array_column( $unfiltered, 'id' ) );
		$this->assertFalse( $this->import( $unfiltered[0], $relational ) );
		$this->assertSame( '2026-12-10 15:00:00', $this->import( $unfiltered[1], $relational )['start_utc'] );
	}

	public function test_import_uses_the_stamped_time_the_filter_compared(): void {
		$relational = $this->times( [
			'oct' => [ '2026-10-15T15:00:00Z', '2026-10-15T17:00:00Z' ],
			'dec' => [ '2026-12-10T15:00:00Z', '2026-12-10T17:00:00Z' ],
		] );
		$signup     = $this->signup( 'stamped', [ 'oct', 'dec' ] );
		$october    = ( new \DateTimeImmutable( '2026-10-01T12:00:00Z' ) )->getTimestamp();

		$annotated = PCO::annotate_registration_signup_times( [ $signup ], $relational, $this->now );
		$this->assertSame( 'dec', $annotated[0][ PCO::SELECTED_SIGNUP_TIME_ID ] );

		// As of October 1 the helper would pick Oct 15. The stamp from November
		// still wins, which is the time the filter compared.
		$neutral = $this->import( $annotated[0], $relational, $october );
		$this->assertSame( '2026-12-10 15:00:00', $neutral['start_utc'] );
		$this->assertSame( '2026-12-10 17:00:00', $neutral['end_utc'] );
	}

	public function test_time_starting_now_is_upcoming_and_equal_starts_keep_list_order(): void {
		$exact      = ( new \DateTimeImmutable( '@' . $this->now ) )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
		$relational = $this->times( [
			'past'  => [ '2026-10-15T15:00:00Z', '2026-10-15T17:00:00Z' ],
			'now'   => [ $exact, '2026-11-04T14:00:00Z' ],
			'later' => [ '2026-12-10T15:00:00Z', '2026-12-10T17:00:00Z' ],
		] );
		$signup     = $this->signup( 'boundary', [ 'later', 'past', 'now' ] );

		$this->assertSame( 'now', PCO::select_registration_signup_time_id( $signup, $relational, $this->now ) );

		$tied = $this->times( [
			'first'  => [ '2026-12-10T15:00:00Z', '2026-12-10T16:00:00Z' ],
			'second' => [ '2026-12-10T15:00:00Z', '2026-12-10T18:00:00Z' ],
		] );
		$signup = $this->signup( 'tie', [ 'first', 'second' ] );

		$this->assertSame( 'first', PCO::select_registration_signup_time_id( $signup, $tied, $this->now ) );
		$neutral = $this->import( $signup, $tied, $this->now );
		$this->assertSame( '2026-12-10 16:00:00', $neutral['end_utc'] );
	}
}
