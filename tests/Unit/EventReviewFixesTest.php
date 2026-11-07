<?php
/**
 * Fixes from the review of the event detail and calendar file work.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Events\CalendarFile;
use CP_Sync\Events\Display;
use CP_Sync\Events\EventCard;
use CP_Sync\Events\EventDetail;
use CP_Sync\Events\EventFormatter;
use CP_Sync\Events\EventsShortcode;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Events\CalendarFile
 * @covers \CP_Sync\Events\Display::maybe_download_calendar
 * @covers \CP_Sync\Events\EventDetail
 * @covers \CP_Sync\Events\EventFormatter::has_ended
 */
class EventReviewFixesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'wp_strip_all_tags' )->alias(
			static function ( $text ) {
				return strip_tags( (string) $text );
			}
		);
		$escape = static function ( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		};
		Functions\when( 'esc_html' )->alias( $escape );
		Functions\when( 'esc_attr' )->alias( $escape );
		Functions\when( 'esc_url' )->alias( $escape );
	}

	protected function tearDown(): void {
		unset( $_GET['cp-sync-ics'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @requires extension intl
	 */
	public function test_manual_utc_offset_is_labeled_with_the_offset() {
		$at = new \DateTimeImmutable( '2026-06-15 13:00:00', new \DateTimeZone( '-05:00' ) );

		$this->assertSame( '-05:00', EventDetail::timezone_label( '-05:00', $at ) );
		$this->assertSame( '+05:30', EventDetail::timezone_label( '+05:30', $at ) );
		$this->assertSame( '', EventDetail::timezone_label( 'Not/AZone', $at ) );
	}

	/**
	 * @requires extension intl
	 */
	public function test_named_zone_keeps_its_long_name() {
		$at = new \DateTimeImmutable( '2026-06-15 13:00:00', new \DateTimeZone( 'America/New_York' ) );

		$this->assertSame( 'Eastern Daylight Time', EventDetail::timezone_label( 'America/New_York', $at ) );
	}

	public function test_end_equal_to_start_runs_through_the_start_date() {
		$start = '2026-11-20 01:00:00'; // 7:00 pm on Nov 19 in Chicago.
		$zone  = 'America/Chicago';

		$this->assertFalse( EventFormatter::has_ended( $start, '2026-11-20 01:00:01', $start, $zone ) );
		$this->assertFalse( EventFormatter::has_ended( $start, '2026-11-20 05:59:59', $start, $zone ) );
		$this->assertTrue( EventFormatter::has_ended( $start, '2026-11-20 06:00:00', $start, $zone ) );
	}

	public function test_end_before_start_is_not_a_real_end() {
		$this->assertFalse( EventFormatter::has_ended( '2026-11-06 22:00:00', '2026-12-01 12:00:00', '2030-08-05 15:00:00', 'UTC' ) );
	}

	public function test_real_end_after_start_still_ends_on_time() {
		$this->assertFalse( EventFormatter::has_ended( '2026-11-20 03:00:00', '2026-11-20 03:00:00', '2026-11-20 01:00:00', 'UTC' ) );
		$this->assertTrue( EventFormatter::has_ended( '2026-11-20 03:00:00', '2026-11-20 03:00:01', '2026-11-20 01:00:00', 'UTC' ) );
	}

	public function test_present_keeps_register_after_a_same_time_start() {
		$view = EventDetail::present(
			array(
				'start_utc'        => '2026-11-20 01:00:00',
				'end_utc'          => '2026-11-20 01:00:00',
				'timezone'         => 'America/Chicago',
				'registration_url' => 'https://example.com/register',
				'now_utc'          => '2026-11-20 01:00:01',
			),
			'event'
		);

		$this->assertFalse( $view['ended'] );
		$this->assertTrue( $view['show_register'] );
	}

	/**
	 * @dataProvider content_cases
	 *
	 * @param string $content  Stored content.
	 * @param bool   $expected Whether the About section shows.
	 */
	public function test_about_section_shows_for_text_and_media( $content, $expected ) {
		$view = EventDetail::present( array( 'content' => $content ), 'series' );

		$this->assertSame( $expected, $view['has_content'] );
	}

	/**
	 * @return array
	 */
	public static function content_cases() {
		return array(
			'text'             => array( '<p>Hello</p>', true ),
			'image'            => array( '<p><img src="https://example.com/a.jpg"></p>', true ),
			'video'            => array( '<video src="https://example.com/a.mp4"></video>', true ),
			'audio'            => array( '<audio src="https://example.com/a.mp3"></audio>', true ),
			'iframe is removed by kses' => array( '<iframe src="https://example.com"></iframe>', false ),
			'blank paragraph'  => array( '<p> </p>', false ),
			'word starting img' => array( '<imgur>', false ),
		);
	}

	public function test_calendar_text_is_decoded_with_block_breaks() {
		$body = CalendarFile::contents(
			$this->record(
				array(
					'title'    => 'Food &amp; Fellowship',
					'content'  => '<p>Food &amp; Fellowship</p><p>Bring a friend&nbsp;!</p>A<br>B<ul><li>One</li><li>Two</li></ul>',
					'location' => 'Hall &amp; Annex',
				)
			)
		);
		$body = str_replace( "\r\n ", '', $body );

		$this->assertStringContainsString( 'SUMMARY:Food & Fellowship', $body );
		$this->assertStringContainsString( 'DESCRIPTION:Food & Fellowship\n\nBring a friend !\n\nA\nB\nOne\nTwo', $body );
		$this->assertStringContainsString( 'LOCATION:Hall & Annex', $body );
		$this->assertStringNotContainsString( '&amp', $body );
		$this->assertStringNotContainsString( '&nbsp', $body );
	}

	public function test_calendar_text_keeps_a_literal_angle_bracket_once_decoded() {
		$body = CalendarFile::contents( $this->record( array( 'content' => 'Ages &lt;5 and &gt;65' ) ) );

		$this->assertStringContainsString( 'DESCRIPTION:Ages <5 and >65', $body );
	}

	public function test_calendar_filename_uses_the_decoded_title() {
		$this->assertSame( 'food-fellowship.ics', CalendarFile::filename( 'Food &amp; Fellowship' ) );
	}

	public function test_download_flag_is_ignored_off_a_single_event() {
		$_GET['cp-sync-ics'] = '1';
		Functions\when( 'is_singular' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\expect( 'status_header' )->never();

		Display::maybe_download_calendar();

		$this->addToAssertionCount( 1 );
	}

	public function test_download_flag_is_ignored_while_event_sync_is_off() {
		$_GET['cp-sync-ics'] = '0';
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'get_option' )->justReturn( array( 'connect' => array( 'sync_events' => false ) ) );
		Functions\expect( 'status_header' )->never();

		Display::maybe_download_calendar();

		$this->addToAssertionCount( 1 );
	}

	public function test_lists_leave_out_password_protected_posts() {
		$parsed = EventsShortcode::parse_atts( array() );

		$this->assertFalse( EventsShortcode::query_args( $parsed, '2026-08-01 00:00:00' )['has_password'] );
		$this->assertFalse( Display::series_archive_query_vars()['has_password'] );
	}

	/**
	 * @dataProvider free_cases
	 *
	 * @param string $price Stored price.
	 * @param bool   $free  Whether it reads as Free.
	 */
	public function test_only_zero_amounts_read_as_free( $price, $free ) {
		$this->assertSame( $free, EventDetail::is_verified_free( $price ), $price );
	}

	/**
	 * @return array
	 */
	public static function free_cases() {
		return array(
			array( 'Free', true ),
			array( '0', true ),
			array( '$0', true ),
			array( '$0.00', true ),
			array( '0,00 €', true ),
			array( '0.00 USD', true ),
			array( '$0 – $0', true ),
			array( '0 - 0', true ),
			array( '$0 deposit', false ),
			array( 'Ages 0', false ),
			array( 'Age 0+', false ),
			array( 'Room 0', false ),
			array( '$0 per child', false ),
			array( 'Ages 0-5', false ),
			array( '$0 – $25', false ),
			array( '$10', false ),
			array( '-0', false ),
			array( 'No cost', false ),
		);
	}

	public function test_detail_page_keeps_a_cost_that_only_starts_at_zero() {
		$this->assertSame( '', EventDetail::price_label( '$0.00' ) );
		$this->assertSame( '$0 deposit', EventDetail::price_label( '$0 deposit' ) );
		$this->assertSame( '$0 – $25', EventDetail::price_label( '$0 – $25' ) );
	}

	/**
	 * Calendar record.
	 *
	 * @param array $overrides Fields to change.
	 * @return array
	 */
	private function record( array $overrides = array() ) {
		return $overrides + array(
			'post_id'       => 41,
			'post_type'     => 'cp_event',
			'post_status'   => 'publish',
			'post_password' => '',
			'title'         => 'Family Weekend',
			'content'       => 'Public description.',
			'location'      => 'Example Hall',
			'start_utc'     => '2026-11-20 02:00:00',
			'end_utc'       => '2026-11-20 04:00:00',
			'timezone'      => 'America/Los_Angeles',
			'all_day'       => false,
			'site_host'     => 'example.com',
			'now_utc'       => '2026-11-06 12:00:00',
		);
	}
}
