<?php
/**
 * Downloadable calendar file for one public event date.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Build an iCalendar document from a stored occurrence.
 *
 * Only a published, public cp_event with a real start instant can be
 * exported. The file is generated here. Nothing is fetched from a remote
 * calendar. Series pages are not exportable, and private meta is not read.
 *
 * @since 1.2.0
 */
class CalendarFile {

	const QUERY_FLAG = 'cp-sync-ics';

	/**
	 * Public fields used to build a file.
	 *
	 * @param array $record post_id, post_type, post_status, post_password,
	 *                      title, content, location, start_utc, end_utc,
	 *                      timezone, all_day, site_host, now_utc.
	 * @return bool
	 */
	public static function is_available( array $record ) {
		$type = isset( $record['post_type'] ) ? (string) $record['post_type'] : '';

		if ( PostTypes::EVENT !== $type ) {
			return false;
		}

		$status = isset( $record['post_status'] ) ? (string) $record['post_status'] : '';

		if ( 'publish' !== $status ) {
			return false;
		}

		if ( ! empty( $record['post_password'] ) ) {
			return false;
		}

		$post_id = isset( $record['post_id'] ) ? (int) $record['post_id'] : 0;

		if ( $post_id <= 0 ) {
			return false;
		}

		return null !== EventFormatter::utc_instant( isset( $record['start_utc'] ) ? $record['start_utc'] : '' );
	}

	/**
	 * iCalendar text for an available record, or an empty string.
	 *
	 * @param array $record See is_available(). Extra keys are ignored.
	 * @return string
	 */
	public static function contents( array $record ) {
		if ( ! self::is_available( $record ) ) {
			return '';
		}

		$start = EventFormatter::utc_instant( $record['start_utc'] );
		$end   = EventFormatter::utc_instant( isset( $record['end_utc'] ) ? $record['end_utc'] : '' );

		if ( null === $start ) {
			return '';
		}

		if ( null === $end || $end < $start ) {
			$end = $start;
		}

		$all_day = ! empty( $record['all_day'] );
		$zone    = isset( $record['timezone'] ) ? (string) $record['timezone'] : '';
		$lines   = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Church Plugins//CP Sync//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:' . self::uid( (int) $record['post_id'], isset( $record['site_host'] ) ? $record['site_host'] : '' ),
			'DTSTAMP:' . self::stamp( isset( $record['now_utc'] ) ? $record['now_utc'] : '' ),
		);

		if ( $all_day ) {
			$span    = self::all_day_span( $start, $end, $zone );
			$lines[] = 'DTSTART;VALUE=DATE:' . $span['start'];
			$lines[] = 'DTEND;VALUE=DATE:' . $span['end'];
		} else {
			$lines[] = 'DTSTART:' . $start->format( 'Ymd\THis\Z' );
			$lines[] = 'DTEND:' . $end->format( 'Ymd\THis\Z' );
		}

		$lines[] = 'SUMMARY:' . self::escape_text( isset( $record['title'] ) ? $record['title'] : '' );

		$description = self::plain_text( isset( $record['content'] ) ? $record['content'] : '' );

		if ( '' !== $description ) {
			$lines[] = 'DESCRIPTION:' . self::escape_text( $description );
		}

		$location = self::plain_text( isset( $record['location'] ) ? $record['location'] : '' );

		if ( '' !== $location ) {
			$lines[] = 'LOCATION:' . self::escape_text( $location );
		}

		$lines[] = 'END:VEVENT';
		$lines[] = 'END:VCALENDAR';

		$folded = array();

		foreach ( $lines as $line ) {
			$folded[] = self::fold( $line );
		}

		return implode( "\r\n", $folded ) . "\r\n";
	}

	/**
	 * Safe attachment name. Letters, numbers, and hyphens only.
	 *
	 * @param string $title Event title.
	 * @return string
	 */
	public static function filename( $title ) {
		$title = self::plain_text( $title );
		$slug  = function_exists( 'sanitize_title' ) ? sanitize_title( $title ) : strtolower( $title );
		$slug  = strtolower( (string) $slug );
		$slug  = preg_replace( '/[^a-z0-9]+/', '-', $slug );
		$slug  = trim( (string) $slug, '-' );

		if ( strlen( $slug ) > 80 ) {
			$slug = trim( substr( $slug, 0, 80 ), '-' );
		}

		if ( '' === $slug ) {
			$slug = 'event';
		}

		return $slug . '.ics';
	}

	/**
	 * Permalink with the download flag, or an empty string.
	 *
	 * @param string $permalink Public event permalink.
	 * @return string
	 */
	public static function download_url( $permalink ) {
		$permalink = EventCard::http_url( $permalink );

		if ( '' === $permalink ) {
			return '';
		}

		$join = false === strpos( $permalink, '?' ) ? '?' : '&';

		return $permalink . $join . self::QUERY_FLAG . '=1';
	}

	/**
	 * Download flag on the current request.
	 *
	 * Null when the flag is absent. `1` when it asks for the file. `invalid`
	 * for any other value, including an array.
	 *
	 * @return string|null
	 */
	public static function request_flag() {
		if ( ! isset( $_GET[ self::QUERY_FLAG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read of an already published event. Not a state change.
			return null;
		}

		// Public download of a post the visitor can already open. Not a state change.
		$value = isset( $_GET[ self::QUERY_FLAG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_FLAG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return ( '1' === $value ) ? '1' : 'invalid';
	}

	/**
	 * Status, headers, and body for a download request.
	 *
	 * @param mixed  $post A post object, or null.
	 * @param string $flag Result of request_flag() other than null.
	 * @return array{status: int, headers: array<string, string>, body: string}
	 */
	public static function response_for( $post, $flag ) {
		$record = self::record_from_post( $post );

		return self::response_for_record( $record, $flag );
	}

	/**
	 * Status, headers, and body for an already loaded record.
	 *
	 * @param array  $record See is_available().
	 * @param string $flag   `1` or anything else.
	 * @return array{status: int, headers: array<string, string>, body: string}
	 */
	public static function response_for_record( array $record, $flag ) {
		$missing = array(
			'status'  => 404,
			'headers' => array(),
			'body'    => '',
		);

		if ( '1' !== (string) $flag || ! self::is_available( $record ) ) {
			return $missing;
		}

		return array(
			'status'  => 200,
			'headers' => array(
				'Content-Type'           => 'text/calendar; charset=utf-8',
				'Content-Disposition'    => 'attachment; filename="' . self::filename( isset( $record['title'] ) ? $record['title'] : '' ) . '"',
				'X-Content-Type-Options' => 'nosniff',
				'X-Robots-Tag'           => 'noindex, nofollow',
				'Cache-Control'          => 'private, no-store',
			),
			'body'    => self::contents( $record ),
		);
	}

	/**
	 * Send a prepared response and stop.
	 *
	 * @param array $response Result of response_for().
	 * @return void
	 */
	public static function emit( array $response ) {
		$status = isset( $response['status'] ) ? (int) $response['status'] : 404;

		if ( function_exists( 'status_header' ) ) {
			status_header( $status );
		} elseif ( ! headers_sent() ) {
			header( 'HTTP/1.1 ' . ( 200 === $status ? '200 OK' : '404 Not Found' ) );
		}

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		$headers = ( isset( $response['headers'] ) && is_array( $response['headers'] ) ) ? $response['headers'] : array();

		foreach ( $headers as $name => $value ) {
			if ( ! is_string( $name ) || ! is_string( $value ) || headers_sent() ) {
				continue;
			}

			header( $name . ': ' . $value );
		}

		if ( 200 === $status ) {
			// text/calendar, with TEXT values already escaped. Not HTML.
			echo isset( $response['body'] ) ? $response['body'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		exit;
	}

	/**
	 * Copy only the public fields a calendar file is allowed to see.
	 *
	 * @param mixed $post Post object.
	 * @return array
	 */
	public static function record_from_post( $post ) {
		if ( ! is_object( $post ) || ! isset( $post->ID ) ) {
			return array();
		}

		$post_id = (int) $post->ID;
		$host    = '';

		if ( function_exists( 'home_url' ) && function_exists( 'wp_parse_url' ) ) {
			$parsed = wp_parse_url( (string) home_url( '/' ), PHP_URL_HOST );
			$host   = is_string( $parsed ) ? $parsed : '';
		}

		return array(
			'post_id'       => $post_id,
			'post_type'     => isset( $post->post_type ) ? (string) $post->post_type : '',
			'post_status'   => isset( $post->post_status ) ? (string) $post->post_status : '',
			'post_password' => isset( $post->post_password ) ? (string) $post->post_password : '',
			'title'         => isset( $post->post_title ) ? (string) $post->post_title : '',
			'content'       => isset( $post->post_content ) ? (string) $post->post_content : '',
			'location'      => (string) get_post_meta( $post_id, Meta::LOCATION, true ),
			'start_utc'     => (string) get_post_meta( $post_id, Meta::START_UTC, true ),
			'end_utc'       => (string) get_post_meta( $post_id, Meta::END_UTC, true ),
			'timezone'      => (string) get_post_meta( $post_id, Meta::TIMEZONE, true ),
			'all_day'       => '1' === (string) get_post_meta( $post_id, Meta::ALL_DAY, true ),
			'site_host'     => $host,
			'now_utc'       => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Stable UID for one event post on one site.
	 *
	 * @param int    $post_id Event post ID.
	 * @param string $host    Public site host.
	 * @return string
	 */
	public static function uid( $post_id, $host ) {
		$host = strtolower( trim( (string) $host ) );
		$host = preg_replace( '/[^a-z0-9.-]/', '', $host );

		if ( ! is_string( $host ) || '' === $host ) {
			$host = 'cp-sync.local';
		}

		return 'cp-sync-event-' . (int) $post_id . '@' . $host;
	}

	/**
	 * Exclusive all-day end, as DATE values in the event zone.
	 *
	 * A local midnight end that is after the start is already exclusive.
	 * Any other end uses the next local calendar day.
	 *
	 * @param \DateTimeImmutable $start    UTC start.
	 * @param \DateTimeImmutable $end      UTC end.
	 * @param string             $timezone Stored zone. Invalid zones use UTC dates.
	 * @return array{start: string, end: string}
	 */
	public static function all_day_span( \DateTimeImmutable $start, \DateTimeImmutable $end, $timezone ) {
		$zone_name = EventTime::is_valid_timezone( $timezone ) ? $timezone : 'UTC';
		$zone      = new \DateTimeZone( $zone_name );
		$local_start = $start->setTimezone( $zone );
		$local_end   = $end->setTimezone( $zone );
		$start_day   = $local_start->format( 'Ymd' );

		if ( '00:00:00' === $local_end->format( 'H:i:s' ) && $local_end > $local_start ) {
			$exclusive = $local_end->format( 'Ymd' );
		} else {
			$exclusive = $local_end->modify( '+1 day' )->format( 'Ymd' );
		}

		if ( $exclusive <= $start_day ) {
			$exclusive = $local_start->modify( '+1 day' )->format( 'Ymd' );
		}

		return array(
			'start' => $start_day,
			'end'   => $exclusive,
		);
	}

	/**
	 * Fold one content line at 75 octets without splitting a UTF-8 character.
	 *
	 * @param string $line Unfolded line.
	 * @return string
	 */
	public static function fold( $line ) {
		$line   = (string) $line;
		$limit  = 75;
		$result = '';
		$bytes  = 0;
		$length = strlen( $line );
		$index  = 0;

		while ( $index < $length ) {
			$width = self::utf8_width( $line, $index );

			if ( $bytes + $width > $limit ) {
				$result .= "\r\n ";
				$bytes   = 1;
			}

			$result .= substr( $line, $index, $width );
			$bytes  += $width;
			$index  += $width;
		}

		return $result;
	}

	/**
	 * Escape a TEXT value.
	 *
	 * @param string $value Raw text.
	 * @return string
	 */
	public static function escape_text( $value ) {
		$value = self::plain_text( $value );
		$value = str_replace( '\\', '\\\\', $value );
		$value = str_replace( ';', '\\;', $value );
		$value = str_replace( ',', '\\,', $value );
		$value = str_replace( array( "\r\n", "\r", "\n" ), '\\n', $value );

		return $value;
	}

	/**
	 * UTC stamp, or the current UTC time when $now_utc is unusable.
	 *
	 * @param string $now_utc UTC `Y-m-d H:i:s`.
	 * @return string
	 */
	protected static function stamp( $now_utc ) {
		$now = EventFormatter::utc_instant( $now_utc );

		if ( null === $now ) {
			$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		}

		return $now->format( 'Ymd\THis\Z' );
	}

	/**
	 * Plain text for a calendar field. Tags are removed. Private keys are not read.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected static function plain_text( $value ) {
		$value = (string) $value;

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$value = wp_strip_all_tags( $value );
		} else {
			$value = strip_tags( $value );
		}

		return trim( $value );
	}

	/**
	 * Octet length of the UTF-8 character at $index.
	 *
	 * @param string $line  Line.
	 * @param int    $index Byte offset.
	 * @return int
	 */
	protected static function utf8_width( $line, $index ) {
		$ord = ord( $line[ $index ] );

		if ( $ord < 0x80 ) {
			return 1;
		}

		$length = strlen( $line );
		$width  = 1;

		if ( ( $ord & 0xE0 ) === 0xC0 ) {
			$width = 2;
		} elseif ( ( $ord & 0xF0 ) === 0xE0 ) {
			$width = 3;
		} elseif ( ( $ord & 0xF8 ) === 0xF0 ) {
			$width = 4;
		}

		if ( $index + $width > $length ) {
			return 1;
		}

		return $width;
	}
}
