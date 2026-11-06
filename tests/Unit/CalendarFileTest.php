<?php
/**
 * Local .ics export for one public event date.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Events\CalendarFile;
use CP_Sync\Events\EventTime;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Events\CalendarFile
 */
class CalendarFileTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->alias(
			static function ( $value ) {
				if ( is_array( $value ) || is_object( $value ) ) {
					return '';
				}

				$value = trim( strip_tags( (string) $value ) );
				$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );

				return trim( (string) $value );
			}
		);
	}

	protected function tearDown(): void {
		unset( $_GET['cp-sync-ics'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_published_occurrence_exports_escaped_folded_crlf() {
		$record = $this->record();
		$record['title']    = 'Hello, world; meet at 6';
		$record['content']  = "Line one\nLine two";
		$record['location'] = 'Hall A, Room 4';
		$body               = CalendarFile::contents( $record );

		$this->assertStringContainsString( "\r\n", $body );
		$this->assertStringNotContainsString( "\n", str_replace( "\r\n", '', $body ) );
		$this->assertStringContainsString( 'UID:cp-sync-event-41@example.com', $body );
		$this->assertStringContainsString( 'SUMMARY:Hello\, world\; meet at 6', $body );
		$this->assertStringContainsString( 'DESCRIPTION:Line one\nLine two', $body );
		$this->assertStringContainsString( 'LOCATION:Hall A\, Room 4', $body );
		$this->assertStringContainsString( 'DTSTART:20261120T020000Z', $body );
		$this->assertStringContainsString( 'DTEND:20261120T040000Z', $body );
		$this->assertStringNotContainsString( 'https://secret.example/register', $body );
		$this->assertStringNotContainsString( 'token=abc', $body );
		$this->assertStringNotContainsString( 'leader@secret.example', $body );
		$this->assertStringNotContainsString( 'pco-occurrence', $body );
		$this->assertStringNotContainsString( 'chms-99', $body );

		foreach ( explode( "\r\n", rtrim( $body, "\r\n" ) ) as $line ) {
			$this->assertLessThanOrEqual( 75, strlen( $line ), $line );
		}

		$long = $this->record();
		$long['title'] = str_repeat( 'Neighborhood ', 12 );
		$folded        = CalendarFile::contents( $long );
		$lines         = explode( "\r\n", rtrim( $folded, "\r\n" ) );
		$saw_fold      = false;

		foreach ( $lines as $line ) {
			$this->assertLessThanOrEqual( 75, strlen( $line ) );

			if ( isset( $line[0] ) && ' ' === $line[0] ) {
				$saw_fold = true;
			}
		}

		$this->assertTrue( $saw_fold );
		$this->assertSame( $body, CalendarFile::contents( $record ) );
	}

	public function test_all_day_uses_an_exclusive_date_and_dst_keeps_utc() {
		$record            = $this->record();
		$record['all_day'] = true;
		$record['start_utc'] = '2026-11-20 08:00:00';
		$record['end_utc']   = '2026-11-23 07:59:00';
		$record['timezone']  = 'America/Los_Angeles';
		$body                = CalendarFile::contents( $record );

		$this->assertStringContainsString( 'DTSTART;VALUE=DATE:20261120', $body );
		$this->assertStringContainsString( 'DTEND;VALUE=DATE:20261123', $body );
		$this->assertStringNotContainsString( 'T080000', $body );

		$summer = EventTime::to_utc( '2026-07-15 13:00:00', 'America/New_York' );
		$winter = EventTime::to_utc( '2026-01-15 13:00:00', 'America/New_York' );
		$this->assertNotNull( $summer );
		$this->assertNotNull( $winter );

		$summer_record              = $this->record();
		$summer_record['start_utc'] = EventTime::format_utc( $summer );
		$summer_record['end_utc']   = EventTime::format_utc( $summer->modify( '+1 hour' ) );
		$winter_record              = $this->record();
		$winter_record['start_utc'] = EventTime::format_utc( $winter );
		$winter_record['end_utc']   = EventTime::format_utc( $winter->modify( '+1 hour' ) );

		$this->assertStringContainsString( 'DTSTART:20260715T170000Z', CalendarFile::contents( $summer_record ) );
		$this->assertStringContainsString( 'DTSTART:20260115T180000Z', CalendarFile::contents( $winter_record ) );
	}

	public function test_unavailable_records_and_bad_flags_are_not_files() {
		$good     = $this->record();
		$response = CalendarFile::response_for_record( $good, '1' );
		$this->assertSame( 200, $response['status'] );
		$this->assertSame( 'text/calendar; charset=utf-8', $response['headers']['Content-Type'] );
		$this->assertSame( 'attachment; filename="family-weekend-nov-20.ics"', $response['headers']['Content-Disposition'] );
		$this->assertStringNotContainsString( "\r", $response['headers']['Content-Disposition'] );

		$cases = array(
			array( 'post_type' => 'cp_event_series' ),
			array( 'post_status' => 'private' ),
			array( 'post_status' => 'draft' ),
			array( 'post_password' => 'secret' ),
			array( 'post_id' => 0 ),
			array( 'start_utc' => 'tomorrow' ),
			array( 'start_utc' => '2026-13-40 00:00:00' ),
		);

		foreach ( $cases as $override ) {
			$record = array_merge( $good, $override );
			$this->assertFalse( CalendarFile::is_available( $record ) );
			$this->assertSame( '', CalendarFile::contents( $record ) );
			$this->assertSame( 404, CalendarFile::response_for_record( $record, '1' )['status'] );
		}

		$this->assertSame( 404, CalendarFile::response_for_record( $good, 'no' )['status'] );
		$this->assertSame( 404, CalendarFile::response_for_record( array(), '1' )['status'] );
	}

	public function test_filename_and_request_flag_reject_injection() {
		$this->assertSame( 'family-weekend-nov-20-php.ics', CalendarFile::filename( "Family Weekend: Nov 20\r\n.php" ) );
		$this->assertStringNotContainsString( '"', CalendarFile::filename( 'Say "hello"' ) );
		$this->assertStringNotContainsString( "\r", CalendarFile::filename( "bad\r\nname" ) );
		$this->assertSame( 'event.ics', CalendarFile::filename( '...' ) );

		$this->assertNull( CalendarFile::request_flag() );
		$_GET['cp-sync-ics'] = '1';
		$this->assertSame( '1', CalendarFile::request_flag() );
		$_GET['cp-sync-ics'] = array( '1' );
		$this->assertSame( 'invalid', CalendarFile::request_flag() );
		$_GET['cp-sync-ics'] = "1\r\nX-Evil: yes";
		$this->assertSame( 'invalid', CalendarFile::request_flag() );
	}

	/**
	 * A public occurrence plus private fields the file must ignore.
	 *
	 * @return array
	 */
	private function record() {
		return array(
			'post_id'          => 41,
			'post_type'        => 'cp_event',
			'post_status'      => 'publish',
			'post_password'    => '',
			'title'            => 'Family Weekend: Nov 20',
			'content'          => 'Public description.',
			'location'         => 'Example Hall',
			'start_utc'        => '2026-11-20 02:00:00',
			'end_utc'          => '2026-11-20 04:00:00',
			'timezone'         => 'America/Los_Angeles',
			'all_day'          => false,
			'site_host'        => 'example.com',
			'now_utc'          => '2026-11-06 12:00:00',
			'registration_url' => 'https://secret.example/register?token=abc',
			'provider'         => 'pco',
			'occurrence_id'    => 'pco-occurrence',
			'source_id'        => 'chms-99',
			'leader_email'     => 'leader@secret.example',
		);
	}
}
