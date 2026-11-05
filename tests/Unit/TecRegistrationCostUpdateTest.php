<?php
/**
 * Cost survival through TEC::update_item(), the path that calls tribe_update_event().
 *
 * The Events Calendar deletes _EventCost when EventCost is missing: a missing
 * cost is normalized to an empty list, and that list deletes the meta. These
 * tests stub that behavior and run the real update method.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Events\Meta;
use CP_Sync\Integrations\Integration;
use CP_Sync\Integrations\TEC;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Integrations\TEC::update_item
 * @covers \CP_Sync\Integrations\TEC::legacy_update_args
 * @covers \CP_Sync\Integrations\TEC::event_cost_update_args
 */
class TecRegistrationCostUpdateTest extends TestCase {

	/** @var array */
	private $meta = array();

	/** @var array */
	private $update_args = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'UTC' ) );
		Functions\when( 'wp_set_post_terms' )->justReturn( array() );
		Functions\when( 'cp_sync' )->justReturn(
			new class() {
				/** @var object */
				public $logging;

				public function __construct() {
					$this->logging = new class() {
						public function log( $message ) {}
					};
				}
			}
		);
		// A cached ChMS id is kept only while get_post() still returns that
		// post. This event is the one the cache maps to post 77.
		Functions\when( 'get_post' )->alias(
			static function ( $post = null ) {
				if ( 77 !== (int) $post ) {
					return null;
				}

				$found     = new \WP_Post();
				$found->ID = 77;

				return $found;
			}
		);
		Functions\when( 'get_post_status' )->justReturn( 'publish' );

		$meta = &$this->meta;
		$args = &$this->update_args;

		Functions\when( 'get_post_meta' )->alias(
			static function ( $post_id, $key = '', $single = false ) use ( &$meta ) {
				unset( $post_id, $single );

				return isset( $meta[ $key ] ) ? $meta[ $key ] : '';
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			static function ( $post_id, $key, $value ) use ( &$meta ) {
				unset( $post_id );
				$meta[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'delete_post_meta' )->alias(
			static function ( $post_id, $key ) use ( &$meta ) {
				unset( $post_id );
				unset( $meta[ $key ] );

				return true;
			}
		);
		// The Events Calendar: a missing EventCost becomes [] and deletes _EventCost.
		Functions\when( 'tribe_update_event' )->alias(
			static function ( $post_id, $update_args ) use ( &$meta, &$args ) {
				unset( $post_id );
				$args = $update_args;
				$cost = array_key_exists( 'EventCost', $update_args ) ? $update_args['EventCost'] : array();

				if ( is_array( $cost ) ) {
					unset( $meta['_EventCost'] );
				} else {
					$meta['_EventCost'] = (string) $cost;
				}

				if ( array_key_exists( 'EventCurrencySymbol', $update_args ) ) {
					$meta['_EventCurrencySymbol'] = (string) $update_args['EventCurrencySymbol'];
				}

				if ( array_key_exists( 'EventCurrencyPosition', $update_args ) ) {
					$meta['_EventCurrencyPosition'] = (string) $update_args['EventCurrencyPosition'];
				}

				return 77;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_fallback_keeps_the_event_cost_through_tribe_update_event() {
		$this->meta = array(
			Meta::REGISTRATION_PRICE => '$25.00',
			'_EventCost'             => '25.00',
			'_EventCurrencySymbol'   => '$',
			'_EventCurrencyPosition' => 'prefix',
			Meta::SYNC_EVENT_COST    => '25.00',
		);

		$this->update(
			array(
				'registration_url' => 'https://church.center/signups/9',
			)
		);

		$this->assertSame( '25.00', $this->update_args['EventCost'] );
		$this->assertSame( '$', $this->update_args['EventCurrencySymbol'] );
		$this->assertSame( 'prefix', $this->update_args['EventCurrencyPosition'] );
		$this->assertSame( '25.00', $this->meta['_EventCost'] );
		$this->assertSame( '$', $this->meta['_EventCurrencySymbol'] );
		$this->assertSame( 'prefix', $this->meta['_EventCurrencyPosition'] );
		$this->assertSame( '$25.00', $this->meta[ Meta::REGISTRATION_PRICE ] );
		$this->assertSame( '25.00', $this->meta[ Meta::SYNC_EVENT_COST ] );
	}

	public function test_hand_cost_survives_a_1_1_0_style_update() {
		$this->meta = array(
			'_EventCost'             => '15.00',
			'_EventCurrencySymbol'   => '£',
			'_EventCurrencyPosition' => 'prefix',
		);

		$this->update( array() );

		$this->assertSame( '15.00', $this->update_args['EventCost'] );
		$this->assertSame( '£', $this->update_args['EventCurrencySymbol'] );
		$this->assertSame( 'prefix', $this->update_args['EventCurrencyPosition'] );
		$this->assertSame( '15.00', $this->meta['_EventCost'] );
		$this->assertSame( '£', $this->meta['_EventCurrencySymbol'] );
		$this->assertArrayNotHasKey( Meta::SYNC_EVENT_COST, $this->meta );
	}

	public function test_hand_cost_survives_an_update_that_has_a_pco_price() {
		$this->meta = array(
			'_EventCost'             => '10.00',
			'_EventCurrencySymbol'   => '€',
			'_EventCurrencyPosition' => 'suffix',
			Meta::SYNC_EVENT_COST    => '25.00',
		);

		$this->update(
			array(
				Meta::REGISTRATION_PRICE => '$40.00',
				'_EventCost'             => '40.00',
				'_EventCurrencySymbol'   => '$',
				'_EventCurrencyPosition' => 'prefix',
				Meta::SYNC_EVENT_COST    => '40.00',
			)
		);

		$this->assertSame( '10.00', $this->update_args['EventCost'] );
		$this->assertSame( '€', $this->update_args['EventCurrencySymbol'] );
		$this->assertSame( 'suffix', $this->update_args['EventCurrencyPosition'] );
		$this->assertSame( '10.00', $this->meta['_EventCost'] );
		$this->assertSame( '€', $this->meta['_EventCurrencySymbol'] );
		$this->assertSame( 'suffix', $this->meta['_EventCurrencyPosition'] );
		$this->assertSame( '25.00', $this->meta[ Meta::SYNC_EVENT_COST ] );
	}

	public function test_clearing_a_sync_cost_deletes_symbol_position_and_marker() {
		$this->meta = array(
			Meta::REGISTRATION_PRICE => '$25.00',
			'_EventCost'             => '25.00',
			'_EventCurrencySymbol'   => '$',
			'_EventCurrencyPosition' => 'prefix',
			Meta::SYNC_EVENT_COST    => '25.00',
		);

		$this->update(
			array(
				Meta::REGISTRATION_PRICE => '',
				Meta::SYNC_EVENT_COST    => '',
			)
		);

		$this->assertArrayNotHasKey( 'EventCost', $this->update_args );
		$this->assertArrayNotHasKey( '_EventCost', $this->meta );
		$this->assertArrayNotHasKey( '_EventCurrencySymbol', $this->meta );
		$this->assertArrayNotHasKey( '_EventCurrencyPosition', $this->meta );
		$this->assertArrayNotHasKey( Meta::SYNC_EVENT_COST, $this->meta );
		$this->assertArrayNotHasKey( Meta::REGISTRATION_PRICE, $this->meta );
		$this->assertNotContains( '', $this->meta, 'A cleared marker must be deleted, not stored as an empty string.' );
	}

	/**
	 * Run update_item() for an existing registration event.
	 *
	 * @param array $meta_input Formatter meta. Empty means a 1.1.0-style item.
	 * @return void
	 */
	private function update( array $meta_input ) {
		$tec      = ( new \ReflectionClass( TEC::class ) )->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty( Integration::class, 'chms_id_cache' );
		$property->setAccessible( true );
		$property->setValue( $tec, array( 'reg_9' => 77 ) );

		$item = array(
			'chms_id'          => 'reg_9',
			'post_title'       => 'Retreat',
			'post_content'     => 'A weekend away.',
			'EventStartDate'   => '2026-09-10',
			'EventStartHour'   => '9',
			'EventStartMinute' => '00',
			'EventEndDate'     => '2026-09-10',
			'EventEndHour'     => '11',
			'EventEndMinute'   => '00',
		);

		if ( ! empty( $meta_input ) ) {
			$item['meta_input'] = $meta_input;
		}

		$tec->update_item( $item );
	}
}
