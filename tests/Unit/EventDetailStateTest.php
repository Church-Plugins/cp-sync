<?php
/**
 * Event and series detail states: price, map, past, and missing fields.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Events\EventDetail;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Events\EventDetail
 */
class EventDetailStateTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->alias(
			static function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
			}
		);
		Functions\when( 'esc_url' )->alias(
			static function ( $url ) {
				$url = trim( (string) $url );

				return preg_match( '#^https?://#i', $url ) ? $url : '';
			}
		);
		Functions\when( 'wp_strip_all_tags' )->alias(
			static function ( $text ) {
				return trim( strip_tags( (string) $text ) );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_only_a_real_cost_gets_a_price_label() {
		$this->assertSame( '', EventDetail::price_label( '' ) );
		$this->assertSame( '', EventDetail::price_label( '   ' ) );
		$this->assertSame( '', EventDetail::price_label( '<p></p>' ) );
		$this->assertSame( '', EventDetail::price_label( 'Free' ) );
		$this->assertSame( '', EventDetail::price_label( 'free' ) );
		$this->assertSame( '', EventDetail::price_label( '$0.00' ) );
		$this->assertSame( '', EventDetail::price_label( '0' ) );
		$this->assertSame( '$75 – $200', EventDetail::price_label( '$75 – $200' ) );
		$this->assertSame( '$0 – $25', EventDetail::price_label( '$0 – $25' ) );
		$this->assertSame( 'Pay what you can', EventDetail::price_label( 'Pay what you can' ) );
		$this->assertSame( '<script>alert(1)</script>', EventDetail::price_label( '<script>alert(1)</script>' ) );
	}

	public function test_map_link_encodes_the_public_location_and_skips_an_empty_one() {
		$url = EventDetail::map_search_url( "Example Retreat Center\n100 Example Road" );

		$this->assertSame(
			'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( 'Example Retreat Center 100 Example Road' ),
			$url
		);
		$this->assertStringNotContainsString( 'key=', $url );
		$this->assertSame( '', EventDetail::map_search_url( '   ' ) );
	}

	public function test_past_event_hides_register_and_an_ongoing_event_keeps_it() {
		$source = $this->source();
		$past   = EventDetail::present( $source, 'event' );

		$this->assertTrue( $past['ended'] );
		$this->assertFalse( $past['show_register'] );
		$this->assertSame( '', $past['registration_url'] );
		$this->assertSame( '$75 – $200', $past['price'] );
		$this->assertTrue( $past['has_content'] );
		$this->assertTrue( $past['calendar'] );
		$this->assertSame( 'Family Weekend', $past['title'] );

		$source['end_utc'] = '2026-11-22 20:00:00';
		$ongoing           = EventDetail::present( $source, 'event' );

		$this->assertFalse( $ongoing['ended'] );
		$this->assertTrue( $ongoing['show_register'] );
		$this->assertSame( 'https://example.com/register', $ongoing['registration_url'] );
	}

	public function test_blank_price_missing_image_and_missing_description_stay_empty() {
		$source                    = $this->source();
		$source['price']           = '';
		$source['image']           = '';
		$source['content']         = '<p> </p>';
		$source['excerpt']         = '';
		$source['registration_url'] = '';
		$source['end_utc']         = '2026-12-01 00:00:00';
		$view                      = EventDetail::present( $source, 'event' );

		$this->assertSame( '', $view['price'] );
		$this->assertSame( '', $view['image'] );
		$this->assertFalse( $view['has_content'] );
		$this->assertSame( '', $view['excerpt'] );
		$this->assertFalse( $view['show_register'] );
	}

	public function test_invalid_date_omits_the_badge_and_the_calendar_file() {
		$source               = $this->source();
		$source['start_utc']  = '';
		$source['end_utc']    = 'tomorrow';
		$view                 = EventDetail::present( $source, 'event' );

		$this->assertFalse( $view['date_confirmed'] );
		$this->assertFalse( $view['badge']['valid'] );
		$this->assertFalse( $view['calendar'] );
		$this->assertSame( '', $view['calendar_url'] );
		$this->assertSame( '', $view['timezone_label'] );
	}

	public function test_an_unverified_timezone_is_not_labeled() {
		$source              = $this->source();
		$source['timezone']  = 'Not/AZone';
		$source['end_utc']   = '2026-12-01 18:00:00';
		$view                = EventDetail::present( $source, 'event' );

		$this->assertFalse( $view['timezone_verified'] );
		$this->assertSame( '', $view['timezone_label'] );
		$this->assertNotSame( '', $view['when_text'] );
	}

	public function test_series_has_no_badge_and_no_calendar_file() {
		$source = $this->source();
		$view   = EventDetail::present( $source, 'series' );

		$this->assertSame( 'series', $view['context'] );
		$this->assertFalse( $view['date_confirmed'] );
		$this->assertFalse( $view['calendar'] );
		$this->assertSame( '', $view['calendar_url'] );
		$this->assertFalse( $view['ended'] );
		$this->assertTrue( $view['show_register'] );
	}

	public function test_occurrence_row_uses_the_card_button_choice() {
		$registered = EventDetail::occurrence_html(
			array(
				'title'            => 'Family Weekend',
				'permalink'        => 'https://example.com/events/family-weekend',
				'registration_url' => 'https://example.com/register',
				'price'            => 'Free',
				'when'             => 'November 20, 2026',
				'all_day'          => false,
				'iso'              => '2026-11-20T02:00:00Z',
				'location'         => 'Example Hall',
			)
		);
		$plain = EventDetail::occurrence_html(
			array(
				'title'     => 'Prayer',
				'permalink' => 'https://example.com/events/prayer',
			)
		);

		$this->assertStringContainsString( 'href="https://example.com/register"', $registered );
		$this->assertStringContainsString( '>Register</a>', $registered );
		$this->assertStringNotContainsString( 'Free', $registered );
		$this->assertStringNotContainsString( 'price', $registered );
		$this->assertStringContainsString( 'href="https://example.com/events/prayer"', $plain );
		$this->assertStringContainsString( '>Learn more</a>', $plain );
		$this->assertStringNotContainsString( '>Register</a>', $plain );
	}

	/**
	 * A published occurrence whose end is already past.
	 *
	 * @return array
	 */
	private function source() {
		return array(
			'post_id'          => 41,
			'post_type'        => 'cp_event',
			'post_status'      => 'publish',
			'post_password'    => '',
			'title'            => 'Family Weekend',
			'permalink'        => 'https://example.com/events/family-weekend',
			'excerpt'          => 'Time together.',
			'content'          => '<p>Spend a relaxed weekend.</p>',
			'image'            => 'https://example.com/photo.jpg',
			'start_utc'        => '2026-11-01 18:00:00',
			'end_utc'          => '2026-11-02 18:00:00',
			'timezone'         => 'America/Los_Angeles',
			'all_day'          => false,
			'location'         => 'Example Retreat Center, 100 Example Road',
			'registration_url' => 'https://example.com/register',
			'price'            => '$75 – $200',
			'categories'       => array( 'Community', 'Community' ),
			'now_utc'          => '2026-11-06 12:00:00',
		);
	}
}
