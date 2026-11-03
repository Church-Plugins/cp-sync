<?php
/**
 * Map a neutral event record onto the array The Events Calendar already consumes.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * TEC field mapper.
 *
 * The layout flags reproduce the array each formatter emitted before the
 * neutral record existed, so an existing site keeps the same TEC fields
 * when the conversion timezone matches the one previously used.
 *
 * @since 1.1.0
 */
class TecEventMapper {

	/**
	 * Build the TEC item for a neutral event.
	 *
	 * @param array $event Neutral event record.
	 * @return array
	 */
	public static function to_tec_item( array $event ) {
		$layout = $event['layout'] ?? 'calendar';

		if ( 'registrations' === $layout ) {
			return self::registrations_item( $event );
		}

		if ( 'ccb' === $layout ) {
			return self::ccb_item( $event );
		}

		return self::calendar_item( $event );
	}

	/**
	 * Local start and end in the WordPress site timezone.
	 *
	 * The neutral record's `timezone` is the ChMS zone and is not the zone
	 * written onto the TEC event.
	 *
	 * @param array $event Neutral event record.
	 * @return \DateTimeImmutable[]
	 */
	protected static function local_range( array $event ) {
		$timezone = $event['site_timezone'] ?? 'UTC';

		if ( ! EventTime::is_valid_timezone( $timezone ) ) {
			$timezone = 'UTC';
		}

		return array(
			EventTime::to_local( $event['start_utc'], $timezone ),
			EventTime::to_local( $event['end_utc'], $timezone ),
		);
	}

	/**
	 * Planning Center calendar layout.
	 *
	 * @param array $event Neutral event record.
	 * @return array
	 */
	protected static function calendar_item( array $event ) {
		list( $start, $end ) = self::local_range( $event );

		$item = array(
			'chms_id'           => $event['chms_id'],
			'post_status'       => $event['post_status'] ?? 'publish',
			'post_title'        => $event['title'] ?? '',
			'post_content'      => $event['content'] ?? '',
			'post_excerpt'      => $event['excerpt'] ?? '',
			'tax_input'         => $event['tax_input'] ?? array(),
			'event_category'    => $event['event_category'] ?? array(),
			'thumbnail_url'     => $event['thumbnail_url'] ?? '',
			'meta_input'        => $event['meta_input'] ?? array(),
			'EventStartDate'    => $start->format( 'Y-m-d' ),
			'EventEndDate'      => $end->format( 'Y-m-d' ),
			'EventAllDay'       => ! empty( $event['all_day'] ),
			'EventStartHour'    => $start->format( 'G' ),
			'EventStartMinute'  => $start->format( 'i' ),
			'EventEndHour'      => $end->format( 'G' ),
			'EventEndMinute'    => $end->format( 'i' ),
			'EventURL'          => $event['event_url'] ?? '',
		);

		if ( ! empty( $event['venue'] ) && is_array( $event['venue'] ) ) {
			$item['EventVenue'] = $event['venue'];
		}

		return $item;
	}

	/**
	 * Planning Center registrations layout.
	 *
	 * @param array $event Neutral event record.
	 * @return array
	 */
	protected static function registrations_item( array $event ) {
		list( $start, $end ) = self::local_range( $event );

		$item = array(
			'chms_id'           => $event['chms_id'],
			'post_status'       => $event['post_status'] ?? 'publish',
			'post_title'        => $event['title'] ?? '',
			'post_content'      => $event['content'] ?? '',
			'tax_input'         => $event['tax_input'] ?? array(),
			'event_category'    => $event['event_category'] ?? array(),
			'thumbnail_url'     => $event['thumbnail_url'] ?? '',
			'meta_input'        => $event['meta_input'] ?? array(),
			'EventStartDate'    => $start->format( 'Y-m-d' ),
			'EventStartHour'    => $start->format( 'G' ),
			'EventStartMinute'  => $start->format( 'i' ),
			'EventEndDate'      => $end->format( 'Y-m-d' ),
			'EventEndHour'      => $end->format( 'G' ),
			'EventEndMinute'    => $end->format( 'i' ),
		);

		if ( ! empty( $event['all_day'] ) ) {
			$item['EventAllDay'] = true;
		}

		if ( ! empty( $event['venue'] ) && is_array( $event['venue'] ) ) {
			$item['EventVenue'] = $event['venue'];
		}

		return $item;
	}

	/**
	 * Church Community Builder layout.
	 *
	 * @param array $event Neutral event record.
	 * @return array
	 */
	protected static function ccb_item( array $event ) {
		list( $start, $end ) = self::local_range( $event );

		return array(
			'chms_id'        => $event['chms_id'],
			'post_status'    => $event['post_status'] ?? 'publish',
			'post_title'     => $event['title'] ?? '',
			'post_content'   => $event['content'] ?? '',
			'thumbnail_url'  => $event['thumbnail_url'] ?? '',
			'EventStartDate' => $start->format( 'Y-m-d H:i:s' ),
			'EventEndDate'   => $end->format( 'Y-m-d H:i:s' ),
			'EventAllDay'    => ! empty( $event['all_day'] ),
			'EventURL'       => $event['event_url'] ?? '',
			'tax_input'      => $event['tax_input'] ?? array(),
			'event_category' => $event['event_category'] ?? array(),
			'meta_input'     => $event['meta_input'] ?? array(),
		);
	}
}
