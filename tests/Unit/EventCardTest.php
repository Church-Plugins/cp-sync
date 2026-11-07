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
		}

		$this->assertStringContainsString( '>Sunday Service</a>', $with_registration );
		$this->assertStringContainsString( 'href="https://example.com/register"', $with_registration );
		$this->assertStringContainsString( '>Register</a>', $with_registration );

		$this->assertStringContainsString( '>Prayer Night</a>', $without_registration );
		$this->assertStringContainsString( 'href="https://example.com/events/prayer-night"', $without_registration );
		$this->assertStringContainsString( '>Learn more</a>', $without_registration );
		$this->assertStringNotContainsString( '>Register</a>', $without_registration );
	}

	public function test_a_missing_date_or_description_leaves_nothing_behind() {
		$html = EventCard::render(
			array(
				'title'     => 'No extras',
				'permalink' => 'https://example.com/events/no-extras',
			)
		);

		$this->assertStringNotContainsString( 'cp-sync-event-card__date', $html );
		$this->assertStringNotContainsString( 'cp-sync-event-card__summary', $html );
		$this->assertStringNotContainsString( 'cp-sync-event-card__terms', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_badge_and_categories_are_escaped() {
		$html = EventCard::render(
			array(
				'title'      => 'Retreat',
				'permalink'  => 'https://example.com/events/retreat',
				'categories' => array( 'Family & <b>Kids</b>', '' ),
				'badge'      => array(
					'valid'   => true,
					'weekday' => 'Fri',
					'day'     => '20',
					'month'   => 'Nov',
				),
			)
		);

		$this->assertStringContainsString( '<strong class="cp-sync-event-card__day">20</strong>', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringNotContainsString( '<b>Kids', $html );
		$this->assertLessThan( strpos( $html, 'cp-sync-event-card__title' ), strpos( $html, 'cp-sync-event-card__date' ) );
	}

	public function test_image_shows_only_when_there_is_one() {
		$with = EventCard::render(
			array(
				'title'     => 'Camp',
				'permalink' => 'https://example.com/events/camp',
				'image'     => 'https://example.com/camp.jpg',
			)
		);
		$bad  = EventCard::render(
			array(
				'title'     => 'Camp',
				'permalink' => 'https://example.com/events/camp',
				'image'     => 'javascript:alert(1)',
			)
		);

		$this->assertStringContainsString( '<img src="https://example.com/camp.jpg" alt=""', $with );
		$this->assertLessThan( strpos( $with, 'cp-sync-event-card__body' ), strpos( $with, 'cp-sync-event-card__media' ) );
		$this->assertStringNotContainsString( 'cp-sync-event-card__media', $bad );
		$this->assertStringNotContainsString( 'alert(1)', $bad );
	}

	public function test_button_uses_the_shared_cp_button() {
		$html = EventCard::render(
			array(
				'title'     => 'Camp',
				'permalink' => 'https://example.com/events/camp',
			)
		);

		$this->assertStringContainsString( 'cp-sync-event-card__button cp-button', $html );
	}

	public function test_cards_never_show_a_price() {
		$html = EventCard::render(
			array(
				'title'            => 'Camp',
				'permalink'        => 'https://example.com/events/camp',
				'registration_url' => 'https://example.com/register',
				'price'            => '$75',
			)
		);

		$this->assertStringContainsString( '>Register</a>', $html );
		$this->assertStringNotContainsString( '$75', $html );
		$this->assertStringNotContainsString( 'price', $html );
	}
}
