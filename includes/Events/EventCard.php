<?php
/**
 * Markup for one event date in a list.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Event cards.
 *
 * Every card has a linked title and one button. The button says Register
 * when the church management system sent a URL. Otherwise it says Learn
 * more and links to the event page. The image and description slots are
 * always present so a missing image or description does not change the
 * card's height.
 *
 * @since 1.2.0
 */
class EventCard {

	/**
	 * HTML for one card.
	 *
	 * @param array $event Event fields: title, permalink, registration_url,
	 *                     image, description, when, all_day, iso, location,
	 *                     series_title, series_url.
	 * @param array $args  show_series_link.
	 * @return string
	 */
	public static function render( array $event, array $args = array() ) {
		$show_series = ! isset( $args['show_series_link'] ) || $args['show_series_link'];
		$title       = isset( $event['title'] ) ? (string) $event['title'] : '';
		$permalink   = isset( $event['permalink'] ) ? (string) $event['permalink'] : '';
		$button      = self::button(
			isset( $event['registration_url'] ) ? $event['registration_url'] : '',
			$permalink
		);

		$html  = '<article class="cp-sync-event-card">';
		$html .= self::image_html( isset( $event['image'] ) ? $event['image'] : '', $title );
		$html .= '<h2 class="cp-sync-event-card__title">';
		$html .= self::title_link( $title, $permalink );
		$html .= '</h2>';
		$html .= self::when_html(
			isset( $event['when'] ) ? $event['when'] : '',
			! empty( $event['all_day'] ),
			isset( $event['iso'] ) ? $event['iso'] : ''
		);
		$html .= self::line_html( 'cp-sync-event-card__location', isset( $event['location'] ) ? $event['location'] : '' );
		$html .= self::description_html( isset( $event['description'] ) ? $event['description'] : '' );

		if ( $show_series ) {
			$html .= self::series_html(
				isset( $event['series_title'] ) ? $event['series_title'] : '',
				isset( $event['series_url'] ) ? $event['series_url'] : ''
			);
		}

		$html .= self::button_html( $button['url'], $button['label'] );
		$html .= '</article>';

		return $html;
	}

	/**
	 * The card button.
	 *
	 * Register uses the ChMS URL. Learn more uses the event permalink.
	 *
	 * @param mixed  $registration_url ChMS registration URL.
	 * @param string $permalink        Event permalink.
	 * @return array{url: string, label: string}
	 */
	public static function button( $registration_url, $permalink ) {
		$registration_url = self::http_url( $registration_url );

		if ( '' !== $registration_url ) {
			return array(
				'url'   => $registration_url,
				'label' => __( 'Register', 'cp-sync' ),
			);
		}

		return array(
			'url'   => self::http_url( $permalink ),
			'label' => __( 'Learn more', 'cp-sync' ),
		);
	}

	/**
	 * Image slot. Always rendered so cards stay the same height.
	 *
	 * @param mixed  $url Image URL.
	 * @param string $alt Alt text.
	 * @return string
	 */
	protected static function image_html( $url, $alt ) {
		$url = self::http_url( $url );
		$html = '<div class="cp-sync-event-card__media">';

		if ( '' !== $url ) {
			$html .= '<img src="' . self::esc_url_attr( $url ) . '" alt="' . self::esc_attr_text( $alt ) . '">';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Linked title. The link is the tap target.
	 *
	 * @param string $title     Event title.
	 * @param string $permalink Event permalink.
	 * @return string
	 */
	protected static function title_link( $title, $permalink ) {
		$permalink = self::http_url( $permalink );
		$text      = self::esc_html_text( $title );

		if ( '' === $permalink ) {
			return $text;
		}

		return '<a href="' . self::esc_url_attr( $permalink ) . '">' . $text . '</a>';
	}

	/**
	 * Description slot. Always rendered, empty when the event has none.
	 *
	 * @param mixed $description Excerpt or content.
	 * @return string
	 */
	protected static function description_html( $description ) {
		$text = self::plain_text( $description );

		return '<p class="cp-sync-event-card__summary">' . self::esc_html_text( $text ) . '</p>';
	}

	/**
	 * Date line. Omitted when there is no label. The summary slot holds the height.
	 *
	 * @param mixed  $when    Formatted date.
	 * @param bool   $all_day Whether to add the all-day words.
	 * @param string $iso     Machine-readable start.
	 * @return string
	 */
	protected static function when_html( $when, $all_day, $iso ) {
		$when = self::plain_text( $when );

		if ( '' === $when ) {
			return '';
		}

		$html  = '<p class="cp-sync-event-card__when">';
		$html .= '<time datetime="' . self::esc_attr_text( $iso ) . '">' . self::esc_html_text( $when ) . '</time>';

		if ( $all_day ) {
			$html .= ' <span class="cp-sync-event-card__all-day">' . self::esc_html_text( __( '(All day)', 'cp-sync' ) ) . '</span>';
		}

		$html .= '</p>';

		return $html;
	}

	/**
	 * Optional one-line field.
	 *
	 * @param string $class CSS class.
	 * @param mixed  $value Text.
	 * @return string
	 */
	protected static function line_html( $class, $value ) {
		$value = self::plain_text( $value );

		if ( '' === $value ) {
			return '';
		}

		return '<p class="' . self::esc_attr_text( $class ) . '">' . self::esc_html_text( $value ) . '</p>';
	}

	/**
	 * Series link.
	 *
	 * @param mixed  $title Series title.
	 * @param mixed  $url   Series permalink.
	 * @return string
	 */
	protected static function series_html( $title, $url ) {
		$title = self::plain_text( $title );
		$url   = self::http_url( $url );

		if ( '' === $title || '' === $url ) {
			return '';
		}

		return '<p class="cp-sync-event-card__series">' . self::esc_html_text( __( 'Part of', 'cp-sync' ) ) . ' <a href="' . self::esc_url_attr( $url ) . '">' . self::esc_html_text( $title ) . '</a></p>';
	}

	/**
	 * The shared card button.
	 *
	 * @param string $url   Destination.
	 * @param string $label Register or Learn more.
	 * @return string
	 */
	protected static function button_html( $url, $label ) {
		$html = '<p class="cp-sync-event-card__action">';

		if ( '' === $url ) {
			$html .= '<span class="cp-sync-event-card__button">' . self::esc_html_text( $label ) . '</span>';
		} else {
			$html .= '<a class="cp-sync-event-card__button" href="' . self::esc_url_attr( $url ) . '">' . self::esc_html_text( $label ) . '</a>';
		}

		$html .= '</p>';

		return $html;
	}

	/**
	 * http or https URL, or an empty string.
	 *
	 * @param mixed $url Candidate URL.
	 * @return string
	 */
	public static function http_url( $url ) {
		$url = trim( (string) $url );

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * Plain one-line text.
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

		$value = preg_replace( '/\s+/', ' ', $value );

		return trim( (string) $value );
	}

	/**
	 * Escape HTML text.
	 *
	 * @param string $value Text.
	 * @return string
	 */
	protected static function esc_html_text( $value ) {
		if ( function_exists( 'esc_html' ) ) {
			return esc_html( $value );
		}

		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Escape an attribute.
	 *
	 * @param string $value Attribute value.
	 * @return string
	 */
	protected static function esc_attr_text( $value ) {
		if ( function_exists( 'esc_attr' ) ) {
			return esc_attr( $value );
		}

		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Escape a URL for an href or src.
	 *
	 * @param string $url URL that already passed http_url().
	 * @return string
	 */
	protected static function esc_url_attr( $url ) {
		if ( function_exists( 'esc_url' ) ) {
			return esc_url( $url );
		}

		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
}
