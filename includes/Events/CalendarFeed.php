<?php
/**
 * Dates for the events calendar.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Public, read-only REST route behind the calendar on /events/.
 *
 * Returns only published dates without a password, the same dates the public
 * pages list. Times are the event's stored local clock time, with no offset, so
 * the calendar shows what the event page shows and does not shift them to the
 * visitor's timezone.
 *
 * @since 1.2.0
 */
class CalendarFeed {

	const REST_NAMESPACE = 'cp-sync/v1';

	const ROUTE = '/events';

	/**
	 * Most dates returned for one request.
	 */
	const MAX_EVENTS = 500;

	/**
	 * Widest range one request can ask for. A month grid shows at most 42 days.
	 */
	const MAX_RANGE_DAYS = 100;

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public static function register_routes() {
		if ( ! Display::is_enabled() ) {
			return;
		}

		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'start'    => array( 'required' => true ),
					'end'      => array( 'required' => true ),
					'category' => array( 'required' => false ),
				),
			)
		);
	}

	/**
	 * Handle one request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle( $request ) {
		$range = self::parse_range( $request->get_param( 'start' ), $request->get_param( 'end' ) );

		if ( null === $range ) {
			return new \WP_Error( 'invalid_range', __( 'Invalid date range.', 'cp-sync' ), array( 'status' => 400 ) );
		}

		$query  = new \WP_Query( self::query_args( $range['start'], $range['end'], $request->get_param( 'category' ) ) );
		$events = array();

		foreach ( $query->posts as $post ) {
			$event = self::format_event( self::row_for_post( $post ) );

			if ( null !== $event ) {
				$events[] = $event;
			}
		}

		return rest_ensure_response( array( 'events' => $events ) );
	}

	/**
	 * Validate the requested dates.
	 *
	 * @param mixed $start `Y-m-d`.
	 * @param mixed $end   `Y-m-d`, after $start.
	 * @return array{start:string,end:string}|null Null when the range is not usable.
	 */
	public static function parse_range( $start, $end ) {
		$start = self::parse_day( $start );
		$end   = self::parse_day( $end );

		if ( null === $start || null === $end || $end <= $start ) {
			return null;
		}

		if ( (int) $start->diff( $end )->days > self::MAX_RANGE_DAYS ) {
			return null;
		}

		return array(
			'start' => $start->format( 'Y-m-d' ),
			'end'   => $end->format( 'Y-m-d' ),
		);
	}

	/**
	 * @param mixed $value Candidate date.
	 * @return \DateTimeImmutable|null
	 */
	protected static function parse_day( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return null;
		}

		$day = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new \DateTimeZone( 'UTC' ) );

		return ( $day && $day->format( 'Y-m-d' ) === $value ) ? $day : null;
	}

	/**
	 * WP_Query arguments for dates that overlap a range.
	 *
	 * A local date can fall on a different UTC date, so the range is widened by a
	 * day on each side. The calendar only draws what falls in its visible dates.
	 *
	 * @param string $start    `Y-m-d`.
	 * @param string $end      `Y-m-d`.
	 * @param mixed  $category Category slug.
	 * @return array
	 */
	public static function query_args( $start, $end, $category = '' ) {
		$from = gmdate( 'Y-m-d H:i:s', (int) strtotime( $start . ' 00:00:00 UTC' ) - 86400 );
		$to   = gmdate( 'Y-m-d H:i:s', (int) strtotime( $end . ' 00:00:00 UTC' ) + 86400 );
		$args = array(
			'post_type'           => PostTypes::EVENT,
			'post_status'         => 'publish',
			'has_password'        => false,
			'posts_per_page'      => self::MAX_EVENTS,
			'meta_key'            => Meta::START_UTC,
			'orderby'             => 'meta_value',
			'order'               => 'ASC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'meta_query'          => array(
				array(
					'key'     => Meta::START_UTC,
					'value'   => $to,
					'compare' => '<',
					'type'    => 'CHAR',
				),
				array(
					'key'     => Meta::END_UTC,
					'value'   => $from,
					'compare' => '>=',
					'type'    => 'CHAR',
				),
			),
		);

		$category = is_string( $category ) ? EventsShortcode::slug( $category ) : '';

		if ( '' !== $category ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => PostTypes::CATEGORY,
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		return $args;
	}

	/**
	 * Read the stored fields for one date.
	 *
	 * @param \WP_Post $post Event post.
	 * @return array
	 */
	protected static function row_for_post( $post ) {
		$id    = (int) $post->ID;
		$terms = get_the_terms( $id, PostTypes::CATEGORY );
		$names = array();

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$names[] = (string) $term->name;
			}
		}

		return array(
			'id'         => $id,
			'title'      => get_the_title( $post ),
			'url'        => (string) get_permalink( $post ),
			'start_utc'  => (string) get_post_meta( $id, Meta::START_UTC, true ),
			'end_utc'    => (string) get_post_meta( $id, Meta::END_UTC, true ),
			'timezone'   => (string) get_post_meta( $id, Meta::TIMEZONE, true ),
			'all_day'    => '1' === (string) get_post_meta( $id, Meta::ALL_DAY, true ),
			'location'   => (string) get_post_meta( $id, Meta::LOCATION, true ),
			'categories' => $names,
		);
	}

	/**
	 * One date in the shape FullCalendar reads.
	 *
	 * `url` is the date's own page. The text fields are plain text, not HTML. The
	 * script must write them with textContent.
	 *
	 * @param array $row id, title, url, start_utc, end_utc, timezone, all_day,
	 *                   location, categories.
	 * @return array|null Null when the date cannot be read.
	 */
	public static function format_event( array $row ) {
		$start = EventFormatter::utc_instant( isset( $row['start_utc'] ) ? $row['start_utc'] : '' );

		if ( null === $start ) {
			return null;
		}

		$zone    = isset( $row['timezone'] ) ? (string) $row['timezone'] : '';
		$zone    = EventTime::is_valid_timezone( $zone ) ? $zone : 'UTC';
		$tz      = new \DateTimeZone( $zone );
		$local   = $start->setTimezone( $tz );
		$end     = EventFormatter::utc_instant( isset( $row['end_utc'] ) ? $row['end_utc'] : '' );
		$end     = ( null !== $end && $end > $start ) ? $end->setTimezone( $tz ) : null;
		$all_day = ! empty( $row['all_day'] );
		$last    = null !== $end ? $end : $local;

		if ( $all_day ) {
			// FullCalendar reads an all-day end as the day after the last day.
			$start_value = $local->format( 'Y-m-d' );
			$end_value   = null !== $end ? $end->modify( '+1 day' )->format( 'Y-m-d' ) : null;
		} else {
			$start_value = $local->format( 'Y-m-d\TH:i:s' );
			$end_value   = null !== $end ? $end->format( 'Y-m-d\TH:i:s' ) : null;
		}

		$categories = array();

		if ( ! empty( $row['categories'] ) && is_array( $row['categories'] ) ) {
			foreach ( $row['categories'] as $name ) {
				$name = self::text( $name );

				if ( '' !== $name ) {
					$categories[] = $name;
				}
			}
		}

		return array(
			'id'            => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'title'         => self::text( isset( $row['title'] ) ? $row['title'] : '' ),
			'url'           => EventCard::http_url( isset( $row['url'] ) ? $row['url'] : '' ),
			'start'         => $start_value,
			'end'           => $end_value,
			'allDay'        => $all_day,
			'extendedProps' => array(
				'lastDay'    => $last->format( 'Y-m-d' ),
				'startTime'  => $all_day ? '' : EventFormatter::time_label( $local ),
				'timeRange'  => EventFormatter::time_range( $local, $end, $all_day ),
				'location'   => self::text( isset( $row['location'] ) ? $row['location'] : '' ),
				'categories' => $categories,
			),
		);
	}

	/**
	 * Plain text with HTML entities decoded. get_the_title() encodes `&`.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected static function text( $value ) {
		return html_entity_decode( EventDetail::plain_value( $value ), ENT_QUOTES, 'UTF-8' );
	}
}
