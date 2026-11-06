<?php
/**
 * View data for one event or series page.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Prepare the public fields a detail template is allowed to print.
 *
 * Price, dates, and the register link come from stored values. A blank price
 * stays blank. "Free" is used only when the stored price is the word Free or
 * a zero amount. Register uses the same http(s) registration URL as the card
 * button and is omitted after the event's own end.
 *
 * @since 1.2.0
 */
class EventDetail {

	/**
	 * Fields for the detail templates.
	 *
	 * @param array  $source post_id, post_type, post_status, post_password,
	 *                       title, permalink, excerpt, content, image,
	 *                       start_utc, end_utc, timezone, all_day, location,
	 *                       registration_url, price, categories, series_title,
	 *                       series_url, site_host, now_utc.
	 * @param string $context `event` or `series`.
	 * @return array
	 */
	public static function present( array $source, $context = 'event' ) {
		$context    = ( 'series' === $context ) ? 'series' : 'event';
		$start      = isset( $source['start_utc'] ) ? (string) $source['start_utc'] : '';
		$end        = isset( $source['end_utc'] ) ? (string) $source['end_utc'] : '';
		$timezone   = isset( $source['timezone'] ) ? (string) $source['timezone'] : '';
		$all_day    = ! empty( $source['all_day'] );
		$now        = isset( $source['now_utc'] ) ? (string) $source['now_utc'] : '';
		$when       = EventFormatter::format_when( $start, $end, $timezone, $all_day );
		$badge      = EventFormatter::badge( $start, $timezone );
		$verified   = EventTime::is_valid_timezone( $timezone );
		$zone_label = '';

		if ( $verified && $badge['valid'] ) {
			$instant = EventFormatter::utc_instant( $start );

			if ( null !== $instant ) {
				try {
					$zone_label = self::timezone_label( $timezone, $instant->setTimezone( new \DateTimeZone( $timezone ) ) );
				} catch ( \Exception $e ) {
					$zone_label = '';
				}
			}
		}

		$price        = self::price_label( isset( $source['price'] ) ? $source['price'] : '' );
		$location     = self::plain_value( isset( $source['location'] ) ? $source['location'] : '' );
		$excerpt      = self::plain_value( isset( $source['excerpt'] ) ? $source['excerpt'] : '' );
		$content      = isset( $source['content'] ) ? (string) $source['content'] : '';
		$content_text = self::plain_value( $content );
		$register     = EventCard::http_url( isset( $source['registration_url'] ) ? $source['registration_url'] : '' );
		$ended        = ( 'event' === $context ) && EventFormatter::has_ended( $end, $now );
		$image        = EventCard::http_url( isset( $source['image'] ) ? $source['image'] : '' );
		$permalink    = EventCard::http_url( isset( $source['permalink'] ) ? $source['permalink'] : '' );
		$calendar     = false;

		if ( 'event' === $context ) {
			$calendar = CalendarFile::is_available(
				array(
					'post_id'       => isset( $source['post_id'] ) ? (int) $source['post_id'] : 0,
					'post_type'     => PostTypes::EVENT,
					'post_status'   => isset( $source['post_status'] ) ? (string) $source['post_status'] : '',
					'post_password' => isset( $source['post_password'] ) ? (string) $source['post_password'] : '',
					'start_utc'     => $start,
				)
			);
		}

		$categories = array();

		if ( ! empty( $source['categories'] ) && is_array( $source['categories'] ) ) {
			foreach ( $source['categories'] as $name ) {
				$name = self::plain_value( $name );

				if ( '' !== $name && ! in_array( $name, $categories, true ) ) {
					$categories[] = $name;
				}
			}
		}

		$series_title = self::plain_value( isset( $source['series_title'] ) ? $source['series_title'] : '' );
		$series_url   = EventCard::http_url( isset( $source['series_url'] ) ? $source['series_url'] : '' );

		return array(
			'context'           => $context,
			'title'             => self::plain_value( isset( $source['title'] ) ? $source['title'] : '' ),
			'when_text'         => $when['text'],
			'all_day'           => ! empty( $when['all_day'] ) && $badge['valid'],
			'iso'               => $when['iso'],
			'badge'             => $badge,
			'date_confirmed'    => ( 'event' === $context ) && $badge['valid'],
			'timezone_label'    => $zone_label,
			'timezone_verified' => '' !== $zone_label,
			'price'             => $price,
			'location'          => $location,
			'map_url'           => self::map_search_url( $location ),
			'excerpt'           => $excerpt,
			'content'           => $content,
			'has_content'       => '' !== $content_text,
			'show_register'     => '' !== $register && ! $ended,
			'registration_url'  => ( '' !== $register && ! $ended ) ? $register : '',
			'ended'             => $ended,
			'image'             => $image,
			'image_alt'         => self::plain_value( isset( $source['title'] ) ? $source['title'] : '' ),
			'calendar'          => $calendar,
			'calendar_url'      => $calendar ? CalendarFile::download_url( $permalink ) : '',
			'categories'        => $categories,
			'series_title'      => ( '' !== $series_title && '' !== $series_url ) ? $series_title : '',
			'series_url'        => ( '' !== $series_title && '' !== $series_url ) ? $series_url : '',
		);
	}

	/**
	 * Display price. Blank stays blank. Free only for a verified zero.
	 *
	 * @param mixed $price Stored registration price.
	 * @return string
	 */
	public static function price_label( $price ) {
		$price = trim( (string) $price );
		$check = self::plain_value( $price );

		if ( '' === $check ) {
			return '';
		}

		if ( self::is_verified_free( $check ) ) {
			return __( 'Free', 'cp-sync' );
		}

		return $price;
	}

	/**
	 * Whether the stored price is the word Free or only zero amounts.
	 *
	 * A range that includes a non-zero amount is not free. Words other than
	 * Free are left as written.
	 *
	 * @param string $price Trimmed price text.
	 * @return bool
	 */
	public static function is_verified_free( $price ) {
		$price = trim( (string) $price );

		if ( '' === $price ) {
			return false;
		}

		if ( preg_match( '/^free$/iu', $price ) ) {
			return true;
		}

		$segments = preg_split( '/\s*(?:\x{2013}|\x{2014}|-)\s*/u', $price );

		if ( ! is_array( $segments ) || ! $segments ) {
			return false;
		}

		$saw_digit = false;

		foreach ( $segments as $segment ) {
			if ( ! preg_match( '/\d/', $segment ) ) {
				return false;
			}

			$saw_digit = true;
			$numeric   = preg_replace( '/[^0-9.]/', '', $segment );

			if ( ! is_numeric( $numeric ) || (float) $numeric !== 0.0 ) {
				return false;
			}
		}

		return $saw_digit;
	}

	/**
	 * Outbound map search for the public location text.
	 *
	 * The query is the stored text, encoded. No geocoding request is made
	 * while the page renders, and no API key is added.
	 *
	 * @param string $location Public location text.
	 * @return string
	 */
	public static function map_search_url( $location ) {
		$location = self::plain_value( $location );
		$location = trim( preg_replace( '/\s+/', ' ', $location ) );

		if ( '' === $location ) {
			return '';
		}

		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $location );
	}

	/**
	 * Verified zone name for one instant.
	 *
	 * Uses the long localized name when intl can produce one, otherwise the
	 * stored IANA name. An invalid zone returns an empty string.
	 *
	 * @param string             $timezone Stored zone.
	 * @param \DateTimeImmutable $at       Local instant.
	 * @return string
	 */
	public static function timezone_label( $timezone, \DateTimeImmutable $at ) {
		if ( ! EventTime::is_valid_timezone( $timezone ) ) {
			return '';
		}

		if ( class_exists( '\IntlTimeZone' ) ) {
			$intl    = \IntlTimeZone::createTimeZone( $timezone );
			$locale  = function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US';
			$daylight = (bool) $at->format( 'I' );
			$label   = $intl->getDisplayName( $daylight, \IntlTimeZone::DISPLAY_LONG, '' !== $locale ? $locale : 'en_US' );

			if ( is_string( $label ) && '' !== $label && false === strpos( $label, 'Unknown' ) ) {
				return $label;
			}
		}

		return $timezone;
	}

	/**
	 * One upcoming date on a series page.
	 *
	 * The button uses EventCard::button(), the same Register or Learn more
	 * choice as a card. The title links to that date.
	 *
	 * @param array $event title, permalink, registration_url, price, when,
	 *                     all_day, iso, location.
	 * @return string
	 */
	public static function occurrence_html( array $event ) {
		$title     = self::plain_value( isset( $event['title'] ) ? $event['title'] : '' );
		$permalink = EventCard::http_url( isset( $event['permalink'] ) ? $event['permalink'] : '' );
		$button    = EventCard::button(
			isset( $event['registration_url'] ) ? $event['registration_url'] : '',
			$permalink
		);
		$when      = self::plain_value( isset( $event['when'] ) ? $event['when'] : '' );
		$location  = self::plain_value( isset( $event['location'] ) ? $event['location'] : '' );
		$price     = self::price_label( isset( $event['price'] ) ? $event['price'] : '' );
		$html      = '<article class="cp-sync-series__date-item">';
		$html     .= '<h3 class="cp-sync-series__date-title">';

		if ( '' !== $permalink ) {
			$html .= '<a href="' . self::esc_url( $permalink ) . '">' . self::esc( $title ) . '</a>';
		} else {
			$html .= self::esc( $title );
		}

		$html .= '</h3>';

		if ( '' !== $when ) {
			$html .= '<p class="cp-sync-series__when"><time datetime="' . self::esc( isset( $event['iso'] ) ? $event['iso'] : '' ) . '">' . self::esc( $when ) . '</time>';

			if ( ! empty( $event['all_day'] ) ) {
				$html .= ' <span class="cp-sync-series__all-day">' . self::esc( __( 'All day', 'cp-sync' ) ) . '</span>';
			}

			$html .= '</p>';
		}

		if ( '' !== $location ) {
			$html .= '<p class="cp-sync-series__location">' . self::esc( $location ) . '</p>';
		}

		$html .= '<p class="cp-sync-series__action">';

		if ( '' !== $price ) {
			$html .= '<strong class="cp-sync-event__price">' . self::esc( $price ) . '</strong> ';
		}

		$button_url = EventCard::http_url( $button['url'] );

		if ( '' !== $button_url ) {
			$html .= '<a class="cp-sync-event__button" href="' . self::esc_url( $button_url ) . '">' . self::esc( $button['label'] ) . '</a>';
		}

		$html .= '</p></article>';

		return $html;
	}

	/**
	 * Text with tags removed.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function plain_value( $value ) {
		$value = trim( (string) $value );

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$value = trim( wp_strip_all_tags( $value ) );
		} else {
			$value = trim( strip_tags( $value ) );
		}

		return $value;
	}

	/**
	 * Escape text and keep square brackets from running as shortcodes.
	 *
	 * @param string $value Plain text.
	 * @return string
	 */
	public static function esc( $value ) {
		$value = (string) $value;

		if ( function_exists( 'esc_html' ) ) {
			$value = esc_html( $value );
		} else {
			$value = htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
		}

		return str_replace( '[', '&#91;', $value );
	}

	/**
	 * Escape a URL that already passed EventCard::http_url().
	 *
	 * @param string $url URL.
	 * @return string
	 */
	protected static function esc_url( $url ) {
		if ( function_exists( 'esc_url' ) ) {
			return esc_url( $url );
		}

		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
}
