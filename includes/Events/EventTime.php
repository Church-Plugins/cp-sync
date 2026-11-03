<?php
/**
 * UTC and timezone helpers for the neutral event record.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Convert ChMS instants without guessing a zone from an address.
 *
 * @since 1.1.0
 */
class EventTime {

	/**
	 * Whether $timezone is a zone PHP can apply.
	 *
	 * @param mixed $timezone Candidate zone name.
	 * @return bool
	 */
	public static function is_valid_timezone( $timezone ) {
		if ( ! is_string( $timezone ) || '' === $timezone ) {
			return false;
		}

		try {
			new \DateTimeZone( $timezone );
		} catch ( \Exception $e ) {
			return false;
		}

		return true;
	}

	/**
	 * First candidate PHP accepts as a timezone, or ''.
	 *
	 * @param array $candidates Zone names, most preferred first.
	 * @return string
	 */
	public static function first_valid_timezone( array $candidates ) {
		foreach ( $candidates as $candidate ) {
			if ( self::is_valid_timezone( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Parse an instant into UTC.
	 *
	 * ISO-8601 values with Z or a numeric offset keep that offset. A naive
	 * `Y-m-d H:i:s` string is read in $naive_timezone (the zone the source
	 * already expressed, not a zone inferred from a street address).
	 *
	 * @param mixed  $value          The source datetime.
	 * @param string $naive_timezone Zone for values that carry no offset.
	 * @return \DateTimeImmutable|null
	 */
	public static function to_utc( $value, $naive_timezone = 'UTC' ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return null;
		}

		$value = trim( $value );

		try {
			if ( self::has_explicit_offset( $value ) ) {
				$instant = new \DateTimeImmutable( $value );
			} else {
				$zone    = self::is_valid_timezone( $naive_timezone ) ? $naive_timezone : 'UTC';
				$instant = new \DateTimeImmutable( $value, new \DateTimeZone( $zone ) );
			}
		} catch ( \Exception $e ) {
			return null;
		}

		return $instant->setTimezone( new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * Format an instant as UTC `Y-m-d H:i:s`.
	 *
	 * @param \DateTimeImmutable $instant The instant.
	 * @return string
	 */
	public static function format_utc( \DateTimeImmutable $instant ) {
		return $instant->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Shift a UTC `Y-m-d H:i:s` string into $timezone.
	 *
	 * @param string $utc_string UTC timestamp without a zone suffix.
	 * @param string $timezone   IANA zone (or another zone PHP accepts).
	 * @return \DateTimeImmutable
	 */
	public static function to_local( $utc_string, $timezone ) {
		$zone = self::is_valid_timezone( $timezone ) ? $timezone : 'UTC';
		$utc  = new \DateTimeImmutable( $utc_string, new \DateTimeZone( 'UTC' ) );

		return $utc->setTimezone( new \DateTimeZone( $zone ) );
	}

	/**
	 * Whether a datetime string already names its offset.
	 *
	 * @param string $value The datetime string.
	 * @return bool
	 */
	protected static function has_explicit_offset( $value ) {
		if ( preg_match( '/[zZ]\z/', $value ) ) {
			return true;
		}

		return (bool) preg_match( '/[+-]\d{2}:?\d{2}\z/', $value );
	}
}
