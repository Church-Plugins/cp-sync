<?php
/**
 * Planning Center registration prices: one amount, a range, free, none,
 * hidden selection types, and the currency symbol.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\PCO;
use CP_Sync\Events\EventSync;
use CP_Sync\Events\Meta;
use CP_Sync\Integrations\TEC;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\PCO::registration_price
 * @covers \CP_Sync\ChMS\PCO::registration_price_from_signup
 * @covers \CP_Sync\Integrations\TEC::price_meta_writes
 */
class RegistrationPriceTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_single_price_is_the_symbol_plus_the_formatted_amount() {
		$price = PCO::registration_price(
			array(
				$this->type( 2500, '25.00', '$' ),
			)
		);

		$this->assertSame( '$25.00', $price['display'] );
		$this->assertSame( '25.00', $price['cost'] );
		$this->assertSame( '$', $price['symbol'] );
		$this->assertSame( 'prefix', $price['position'] );

		$same = PCO::registration_price(
			array(
				$this->type( 2500, '25.00', '$', true, 'a' ),
				$this->type( 2500, '25.00', '$', true, 'b' ),
			)
		);

		$this->assertSame( '$25.00', $same['display'] );
		$this->assertSame( '25.00', $same['cost'] );
	}

	public function test_several_prices_are_a_range_from_lowest_to_highest() {
		$price = PCO::registration_price(
			array(
				$this->type( 4000, '40.00', '$', true, 'adult' ),
				$this->type( 1000, '10.00', '$', true, 'child' ),
				$this->type( 2500, '25.00', '$', true, 'student' ),
			)
		);

		$this->assertSame( '$10.00 - $40.00', $price['display'] );
		$this->assertSame( '10.00 - 40.00', $price['cost'] );
		$this->assertSame( '$', $price['symbol'] );
		$this->assertSame( 'prefix', $price['position'] );
	}

	public function test_all_zero_prices_are_free() {
		$price = PCO::registration_price(
			array(
				$this->type( 0, '0.00', '$', true, 'adult' ),
				$this->type( 0, '0.00', '$', true, 'child' ),
			)
		);

		$this->assertSame( 'Free', $price['display'] );
		$this->assertSame( 'Free', $price['cost'] );
		$this->assertSame( '$', $price['symbol'] );
	}

	public function test_no_selection_types_has_no_price() {
		$this->assertSame(
			array(
				'display'  => '',
				'cost'     => '',
				'symbol'   => '',
				'position' => '',
			),
			PCO::registration_price( array() )
		);

		$unlinked = PCO::registration_price_from_signup(
			array(
				'id'            => '5',
				'relationships' => array(),
			),
			array(
				'SelectionType' => array(
					'1' => $this->type( 2500, '25.00', '$' ),
				),
			)
		);

		$this->assertSame( '', $unlinked['display'] );
		$this->assertSame( '', $unlinked['cost'] );

		$missing_amount = PCO::registration_price(
			array(
				array(
					'attributes' => array(
						'publicly_available'    => true,
						'price_currency_symbol' => '$',
					),
				),
			)
		);

		$this->assertSame( '', $missing_amount['display'] );
		$this->assertSame( '', $missing_amount['cost'] );
	}

	public function test_hidden_selection_types_are_skipped() {
		$only_public = PCO::registration_price(
			array(
				$this->type( 2500, '25.00', '$', true, 'public' ),
				$this->type( 10000, '100.00', '$', false, 'hidden' ),
			)
		);

		$this->assertSame( '$25.00', $only_public['display'] );
		$this->assertSame( '25.00', $only_public['cost'] );

		$only_hidden = PCO::registration_price(
			array(
				$this->type( 10000, '100.00', '$', false, 'hidden' ),
			)
		);

		$this->assertSame( '', $only_hidden['display'] );
		$this->assertSame( '', $only_hidden['cost'] );
	}

	public function test_currency_symbol_comes_from_the_selection_type() {
		$price = PCO::registration_price(
			array(
				$this->type( 1250, '12.50', '€' ),
			)
		);

		$this->assertSame( '€12.50', $price['display'] );
		$this->assertSame( '12.50', $price['cost'] );
		$this->assertSame( '€', $price['symbol'] );
		$this->assertSame( 'prefix', $price['position'] );

		$range = PCO::registration_price(
			array(
				$this->type( 500, '5.00', '£', true, 'low' ),
				$this->type( 2000, '20.00', '£', true, 'high' ),
			)
		);

		$this->assertSame( '£5.00 - £20.00', $range['display'] );
		$this->assertSame( '£', $range['symbol'] );
	}

	public function test_resync_stores_the_price_and_clears_it_when_it_is_gone() {
		$stored = EventSync::normalize(
			array(
				'provider'           => 'pco',
				'chms_id'            => 'reg_9',
				'start_utc'          => '2026-08-01 15:00:00',
				'registration_price' => '$25.00',
			),
			'UTC'
		);
		$meta = Meta::event_meta( $stored );

		$this->assertSame( '$25.00', $meta[ Meta::REGISTRATION_PRICE ] );

		$cleared = EventSync::normalize(
			array(
				'provider'           => 'pco',
				'chms_id'            => 'reg_9',
				'start_utc'          => '2026-08-01 15:00:00',
				'registration_price' => '',
			),
			'UTC'
		);
		$cleared_meta = Meta::event_meta( $cleared );

		$this->assertArrayHasKey( Meta::REGISTRATION_PRICE, $cleared_meta );
		$this->assertSame( '', $cleared_meta[ Meta::REGISTRATION_PRICE ] );

		$calendar = Meta::event_meta(
			EventSync::normalize(
				array(
					'provider'  => 'pco',
					'chms_id'   => 'inst-1',
					'start_utc' => '2026-08-01 15:00:00',
				),
				'UTC'
			)
		);

		$this->assertArrayNotHasKey( Meta::REGISTRATION_PRICE, $calendar );
	}

	public function test_events_calendar_cost_is_written_and_cleared() {
		$writes = TEC::price_meta_writes(
			array(
				Meta::REGISTRATION_PRICE => '€12.50',
				'_EventCost'             => '12.50',
				'_EventCurrencySymbol'   => '€',
				'_EventCurrencyPosition' => 'prefix',
				'registration_url'       => 'https://example.com/register',
			)
		);

		$this->assertSame( '€12.50', $writes[ Meta::REGISTRATION_PRICE ] );
		$this->assertSame( '12.50', $writes['_EventCost'] );
		$this->assertSame( '€', $writes['_EventCurrencySymbol'] );
		$this->assertSame( 'prefix', $writes['_EventCurrencyPosition'] );
		$this->assertArrayNotHasKey( 'registration_url', $writes );

		$clear = TEC::price_meta_writes(
			array(
				Meta::REGISTRATION_PRICE => '',
				'_EventCost'             => '',
				'_EventCurrencySymbol'   => '',
				'_EventCurrencyPosition' => '',
			)
		);

		$this->assertNull( $clear[ Meta::REGISTRATION_PRICE ] );
		$this->assertNull( $clear['_EventCost'] );
		$this->assertNull( $clear['_EventCurrencySymbol'] );
		$this->assertNull( $clear['_EventCurrencyPosition'] );
	}

	/**
	 * One SelectionType resource.
	 *
	 * @param int    $cents     price_cents.
	 * @param string $formatted price_formatted.
	 * @param string $symbol    price_currency_symbol.
	 * @param bool   $public    publicly_available.
	 * @param string $id        Resource id.
	 * @return array
	 */
	private function type( $cents, $formatted, $symbol, $public = true, $id = '1' ) {
		return array(
			'id'         => $id,
			'type'       => 'SelectionType',
			'attributes' => array(
				'name'                   => 'Option',
				'price_cents'            => $cents,
				'price_formatted'        => $formatted,
				'price_currency'         => 'USD',
				'price_currency_symbol'  => $symbol,
				'publicly_available'     => $public,
			),
		);
	}
}
