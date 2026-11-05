<?php
/**
 * Every event card has a linked title and one button.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Events\EventCard;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Events\EventCard
 */
class EventCardTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_every_card_has_a_title_link_and_a_button() {
		$with_registration = EventCard::render(
			array(
				'title'            => 'Sunday Service',
				'permalink'        => 'https://example.com/events/sunday-service',
				'registration_url' => 'https://example.com/register',
				'description'      => 'Worship together.',
				'image'            => 'https://example.com/photo.jpg',
			)
		);
		$without_registration = EventCard::render(
			array(
				'title'     => 'Prayer Night',
				'permalink' => 'https://example.com/events/prayer-night',
			)
		);

		foreach ( array( $with_registration, $without_registration ) as $html ) {
			$this->assertStringContainsString( 'cp-sync-event-card__title', $html );
			$this->assertMatchesRegularExpression( '/<h2 class="cp-sync-event-card__title"><a href="https:\/\/example\.com\/events\/[^"]+">/', $html );
			$this->assertStringContainsString( 'cp-sync-event-card__button', $html );
			$this->assertStringContainsString( 'cp-sync-event-card__media', $html );
			$this->assertStringContainsString( 'cp-sync-event-card__summary', $html );
		}

		$this->assertStringContainsString( '>Sunday Service</a>', $with_registration );
		$this->assertStringContainsString( 'href="https://example.com/register"', $with_registration );
		$this->assertStringContainsString( '>Register</a>', $with_registration );

		$this->assertStringContainsString( '>Prayer Night</a>', $without_registration );
		$this->assertStringContainsString( 'href="https://example.com/events/prayer-night"', $without_registration );
		$this->assertStringContainsString( '>Learn more</a>', $without_registration );
		$this->assertStringNotContainsString( '>Register</a>', $without_registration );
	}

	public function test_a_missing_image_or_description_still_reserves_its_slot() {
		$html = EventCard::render(
			array(
				'title'     => 'No extras',
				'permalink' => 'https://example.com/events/no-extras',
			)
		);

		$this->assertStringContainsString( '<div class="cp-sync-event-card__media"></div>', $html );
		$this->assertStringContainsString( '<p class="cp-sync-event-card__summary"></p>', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_price_is_escaped_and_shown_next_to_register() {
		$html = EventCard::render(
			array(
				'title'            => 'Camp',
				'permalink'        => 'https://example.com/events/camp',
				'registration_url' => 'https://example.com/register',
				'price'            => '<script>alert(1)</script>',
			)
		);

		$price    = strpos( $html, 'cp-sync-event-card__price' );
		$register = strpos( $html, '>Register</a>' );

		$this->assertNotFalse( $price );
		$this->assertNotFalse( $register );
		$this->assertLessThan( $register, $price );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}
}
