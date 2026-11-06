<?php
/**
 * Site-timezone labels for an event date.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Format UTC instants for the event templates.
 *
 * @since 1.2.0
 */
class EventFormatter {

	/**
	 * Human label for a start and end, in the given timezone.
	 *
	 * All-day events return the date only. The template adds the "All day" words
	 * so they can be translated at the edge.
	 *
	 * @param string $start_utc UTC `Y-m-d H:i:s`.
	 * @param string $end_utc   UTC `Y-m-d H:i:s`.
	 * @param string $timezone  IANA zone, usually the WordPress site timezone.
	 * @param bool   $all_day   Whether the source marked the date all day.
	 * @return array{text: string, all_day: bool, iso: string}
	 */
	public static function format_when( $start_utc, $end_utc, $timezone, $all_day = false ) {
		$start_utc = trim( (string) $start_utc );

		if ( '' === $start_utc ) {
			return array(
				'text'    => '',
				'all_day' => false,
				'iso'     => '',
			);
		}

		$zone = EventTime::is_valid_timezone( $timezone ) ? $timezone : 'UTC';

		try {
			$start = EventTime::to_local( $start_utc, $zone );
			$end   = EventTime::to_local( '' !== (string) $end_utc ? $end_utc : $start_utc, $zone );
		} catch ( \Exception $e ) {
			return array(
				'text'    => '',
				'all_day' => (bool) $all_day,
				'iso'     => '',
			);
		}

		$iso       = $start->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
		$same_day  = $start->format( 'Y-m-d' ) === $end->format( 'Y-m-d' );
		$start_day = self::format_date( $start );
		$end_day   = self::format_date( $end );

		if ( $all_day ) {
			$text = $same_day ? $start_day : $start_day . ' – ' . $end_day;

			return array(
				'text'    => $text,
				'all_day' => true,
				'iso'     => $iso,
			);
		}

		$start_time = self::format_time( $start );
		$end_time   = self::format_time( $end );

		if ( $same_day ) {
			$text = $start_day . ', ' . $start_time . ' – ' . $end_time;
		} else {
			$text = $start_day . ', ' . $start_time . ' – ' . $end_day . ', ' . $end_time;
		}

		return array(
			'text'    => $text,
			'all_day' => false,
			'iso'     => $iso,
		);
	}

	/**
	 * Localized date when wp_date() exists, otherwise English.
	 *
	 * @param \DateTimeImmutable $local Local instant.
	 * @return string
	 */
	protected static function format_date( \DateTimeImmutable $local ) {
		return self::format_piece( $local, 'F j, Y' );
	}

	/**
	 * Localized time when wp_date() exists, otherwise English.
	 *
	 * @param \DateTimeImmutable $local Local instant.
	 * @return string
	 */
	protected static function format_time( \DateTimeImmutable $local ) {
		return self::format_piece( $local, 'g:i a' );
	}

	/**
	 * Format one piece of a local instant.
	 *
	 * @param \DateTimeImmutable $local  Local instant.
	 * @param string             $format PHP date format.
	 * @return string
	 */
	protected static function format_piece( \DateTimeImmutable $local, $format ) {
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( $format, $local->getTimestamp(), $local->getTimezone() );
		}

		return $local->format( $format );
	}

	/**
	 * Month and day for the hero badge, from the start instant.
	 *
	 * The parts are formatted from the timestamp. They are not parsed out of
	 * the translated date sentence.
	 *
	 * @param string $start_utc UTC `Y-m-d H:i:s`.
	 * @param string $timezone  IANA zone stored on the event.
	 * @return array{valid: bool, month: string, day: string, year: string}
	 */
	public static function badge( $start_utc, $timezone ) {
		$empty = array(
			'valid' => false,
			'month' => '',
			'day'   => '',
			'year'  => '',
		);
		$start = self::utc_instant( $start_utc );

		if ( null === $start ) {
			return $empty;
		}

		$zone = EventTime::is_valid_timezone( $timezone ) ? $timezone : 'UTC';

		try {
			$local = $start->setTimezone( new \DateTimeZone( $zone ) );
		} catch ( \Exception $e ) {
			return $empty;
		}

		return array(
			'valid' => true,
			'month' => self::format_piece( $local, 'M' ),
			'day'   => self::format_piece( $local, 'j' ),
			'year'  => self::format_piece( $local, 'Y' ),
		);
	}

	/**
	 * Whether the event's own end is before $now_utc.
	 *
	 * An empty or unreadable end is not treated as past. An end equal to now
	 * is still current, matching the upcoming-list comparison.
	 *
	 * @param string $end_utc UTC `Y-m-d H:i:s`.
	 * @param string $now_utc UTC `Y-m-d H:i:s`.
	 * @return bool
	 */
	public static function has_ended( $end_utc, $now_utc ) {
		$end = self::utc_instant( $end_utc );
		$now = self::utc_instant( $now_utc );

		if ( null === $end || null === $now ) {
			return false;
		}

		return $end < $now;
	}

	/**
	 * Parse a UTC `Y-m-d H:i:s` instant, or null when it is not a real moment.
	 *
	 * @param mixed $value Candidate timestamp.
	 * @return \DateTimeImmutable|null
	 */
	public static function utc_instant( $value ) {
		$value = trim( (string) $value );

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})$/', $value, $match ) ) {
			return null;
		}

		$year   = (int) $match[1];
		$month  = (int) $match[2];
		$day    = (int) $match[3];
		$hour   = (int) $match[4];
		$minute = (int) $match[5];
		$second = (int) $match[6];

		if ( ! checkdate( $month, $day, $year ) || $hour > 23 || $minute > 59 || $second > 59 ) {
			return null;
		}

		try {
			return new \DateTimeImmutable( sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second ), new \DateTimeZone( 'UTC' ) );
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
