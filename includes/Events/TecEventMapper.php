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
 * Planning Center times are the UTC instant in the WordPress site timezone,
 * with no EventTimezone. Church Community Builder keeps its wall clock in
 * the event timezone, or the site timezone when that zone is missing or
 * not one PHP can apply.
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
		$is_ccb          = 'ccb' === ( $event['provider'] ?? '' );
		$is_registration = ! $is_ccb && 0 === strpos( (string) ( $event['chms_id'] ?? '' ), 'reg_' );
		$zone            = $is_ccb ? ( $event['timezone'] ?? '' ) : ( $event['site_timezone'] ?? '' );

		if ( ! EventTime::is_valid_timezone( $zone ) ) {
			$zone = 'UTC';
		}

		$start = EventTime::to_local( $event['start_utc'], $zone );
		$end   = EventTime::to_local( $event['end_utc'], $zone );

		$item = array(
			'chms_id'      => $event['chms_id'],
			'post_status'  => $event['post_status'] ?? 'publish',
			'post_title'   => $event['title'] ?? '',
			'post_content' => $event['content'] ?? '',
		);

		if ( $is_ccb ) {
			$item['thumbnail_url']  = $event['thumbnail_url'] ?? '';
			$item['EventStartDate'] = $start->format( 'Y-m-d H:i:s' );
			$item['EventEndDate']   = $end->format( 'Y-m-d H:i:s' );
			$item['EventAllDay']    = ! empty( $event['all_day'] );
			$item['EventTimezone']  = $zone;
			$item['EventURL']       = $event['event_url'] ?? '';
			$item['tax_input']      = $event['tax_input'] ?? array();
			$item['event_category'] = $event['event_category'] ?? array();
			$item['meta_input']     = $event['meta_input'] ?? array();

			return $item;
		}

		if ( ! $is_registration ) {
			$item['post_excerpt'] = $event['excerpt'] ?? '';
		}

		$item['tax_input']      = $event['tax_input'] ?? array();
		$item['event_category'] = $event['event_category'] ?? array();
		$item['thumbnail_url']  = $event['thumbnail_url'] ?? '';
		$item['meta_input']     = $event['meta_input'] ?? array();

		if ( $is_registration ) {
			$item['EventStartDate']   = $start->format( 'Y-m-d' );
			$item['EventStartHour']   = $start->format( 'G' );
			$item['EventStartMinute'] = $start->format( 'i' );
			$item['EventEndDate']     = $end->format( 'Y-m-d' );
			$item['EventEndHour']     = $end->format( 'G' );
			$item['EventEndMinute']   = $end->format( 'i' );

			if ( ! empty( $event['all_day'] ) ) {
				$item['EventAllDay'] = true;
			}
		} else {
			$item['EventStartDate']   = $start->format( 'Y-m-d' );
			$item['EventEndDate']     = $end->format( 'Y-m-d' );
			$item['EventAllDay']      = ! empty( $event['all_day'] );
			$item['EventStartHour']   = $start->format( 'G' );
			$item['EventStartMinute'] = $start->format( 'i' );
			$item['EventEndHour']     = $end->format( 'G' );
			$item['EventEndMinute']   = $end->format( 'i' );
			$item['EventURL']         = $event['event_url'] ?? '';
		}

		if ( ! empty( $event['venue'] ) && is_array( $event['venue'] ) ) {
			$item['EventVenue'] = $event['venue'];
		}

		return $item;
	}
}
