<?php
/**
 * Post meta keys for CP Sync events and series.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Meta key names shared by the sync and the templates.
 *
 * @since 1.2.0
 */
class Meta {

	const PROVIDER = '_cp_provider';

	const OCCURRENCE_ID = '_cp_occurrence_id';

	const SOURCE_ID = '_cp_source_id';

	/**
	 * Same marker uninstall.php uses to find imported posts.
	 */
	const CHMS_ID = '_chms_id';

	const START_UTC = '_cp_start_utc';

	const END_UTC = '_cp_end_utc';

	const START_LOCAL = '_cp_start_local';

	const END_LOCAL = '_cp_end_local';

	const TIMEZONE = '_cp_timezone';

	const ALL_DAY = '_cp_all_day';

	const LOCATION = '_cp_location';

	const IMAGE_URL = '_cp_image_url';

	const REGISTRATION_URL = '_cp_registration_url';

	/**
	 * Display price from Planning Center Registrations.
	 *
	 * The key is omitted when the response did not include selection types,
	 * so a stored price is left alone. An empty string is written only when
	 * a good response shows the signup has no public price.
	 */
	const REGISTRATION_PRICE = '_cp_registration_price';

	/**
	 * `_EventCost` value last written by a registration sync.
	 *
	 * A later sync clears `_EventCost` only when the stored cost still equals
	 * this marker. A different value is a hand edit and is left in place.
	 */
	const SYNC_EVENT_COST = '_cp_sync_event_cost';

	/**
	 * WordPress post ID of the series this date belongs to.
	 */
	const SERIES_ID = '_cp_series_id';

	/**
	 * Provider plus ChMS series id, used to upsert the series post.
	 */
	const SERIES_KEY = '_cp_series_key';

	/**
	 * Same key Integrations\Integration uses for the per-post sync lock.
	 */
	const LOCK = '_cp_sync_lock';

	/**
	 * Meta for one date. Every key the archive and series list query is set.
	 *
	 * End defaults to the start. Timezone defaults to UTC. All-day defaults
	 * to 0. Series id defaults to 0 so a date with no series is still stored
	 * with the key and is not dropped by a meta query that requires it.
	 *
	 * @param array $record Normalized event.
	 * @return array
	 */
	public static function event_meta( array $record ) {
		$start = isset( $record['start_utc'] ) ? (string) $record['start_utc'] : '';
		$end   = isset( $record['end_utc'] ) ? (string) $record['end_utc'] : '';

		if ( '' === $end ) {
			$end = $start;
		}

		$zone = isset( $record['timezone'] ) ? (string) $record['timezone'] : '';

		if ( '' === $zone ) {
			$zone = 'UTC';
		}

		$start_local = isset( $record['start_local'] ) ? (string) $record['start_local'] : '';
		$end_local   = isset( $record['end_local'] ) ? (string) $record['end_local'] : '';

		if ( '' === $start_local ) {
			$start_local = $start;
		}

		if ( '' === $end_local ) {
			$end_local = $end;
		}

		$series_id = isset( $record['series_post_id'] ) ? (int) $record['series_post_id'] : 0;

		if ( $series_id < 0 ) {
			$series_id = 0;
		}

		$meta = array(
			self::PROVIDER         => isset( $record['provider'] ) ? (string) $record['provider'] : '',
			self::OCCURRENCE_ID    => isset( $record['occurrence_id'] ) ? (string) $record['occurrence_id'] : '',
			self::SOURCE_ID        => isset( $record['source_id'] ) ? (string) $record['source_id'] : '',
			self::CHMS_ID          => isset( $record['source_id'] ) ? (string) $record['source_id'] : '',
			self::START_UTC        => $start,
			self::END_UTC          => $end,
			self::START_LOCAL      => $start_local,
			self::END_LOCAL        => $end_local,
			self::TIMEZONE         => $zone,
			self::ALL_DAY          => ! empty( $record['all_day'] ) ? '1' : '0',
			self::LOCATION         => isset( $record['location'] ) ? (string) $record['location'] : '',
			self::IMAGE_URL        => isset( $record['image_url'] ) ? (string) $record['image_url'] : '',
			self::REGISTRATION_URL => isset( $record['registration_url'] ) ? (string) $record['registration_url'] : '',
			self::SERIES_KEY       => isset( $record['series_key'] ) ? (string) $record['series_key'] : '',
			self::SERIES_ID        => (string) $series_id,
		);

		// Absent means leave the stored price alone. Present and empty clears
		// a price a good response showed is gone.
		if ( array_key_exists( 'registration_price', $record ) ) {
			$meta[ self::REGISTRATION_PRICE ] = (string) $record['registration_price'];
		}

		return $meta;
	}

	/**
	 * Meta for one series. Image and registration default to empty strings.
	 *
	 * @param array $record Normalized series.
	 * @return array
	 */
	public static function series_meta( array $record ) {
		$series_key = isset( $record['series_key'] ) ? (string) $record['series_key'] : '';

		return array(
			self::PROVIDER         => isset( $record['provider'] ) ? (string) $record['provider'] : '',
			self::SERIES_KEY       => $series_key,
			self::CHMS_ID          => $series_key,
			self::IMAGE_URL        => isset( $record['image_url'] ) ? (string) $record['image_url'] : '',
			self::REGISTRATION_URL => isset( $record['registration_url'] ) ? (string) $record['registration_url'] : '',
		);
	}
}
