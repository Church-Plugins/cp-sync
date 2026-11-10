<?php
/**
 * Markup for one series on the series archive.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Series archive cards.
 *
 * A series card follows the series page: image, title, description, and
 * Register when the church management system sent a URL. It has no event
 * date. Learn more links to the series when there is no registration URL.
 * The image and description slots stay present so the card height holds.
 *
 * @since 1.2.0
 */
class SeriesCard {

	/**
	 * HTML for one series card.
	 *
	 * @param array $series title, permalink, registration_url, image, description.
	 * @return string
	 */
	public static function render( array $series ) {
		$title     = isset( $series['title'] ) ? (string) $series['title'] : '';
		$permalink = isset( $series['permalink'] ) ? (string) $series['permalink'] : '';
		$button    = EventCard::button(
			isset( $series['registration_url'] ) ? $series['registration_url'] : '',
			$permalink
		);

		$html  = '<article class="cp-sync-series-card">';
		$html .= self::image_html( isset( $series['image'] ) ? $series['image'] : '', $title );
		$html .= '<div class="cp-sync-series-card__body">';
		$html .= '<h2 class="cp-sync-series-card__title">';
		$html .= self::title_link( $title, $permalink );
		$html .= '</h2>';
		$html .= self::description_html( isset( $series['description'] ) ? $series['description'] : '' );
		$html .= self::button_html( $button['url'], $button['label'] );
		$html .= '</div></article>';

		return $html;
	}

	/**
	 * Image slot.
	 *
	 * @param mixed  $url Image URL.
	 * @param string $alt Alt text.
	 * @return string
	 */
	protected static function image_html( $url, $alt ) {
		$url  = EventCard::http_url( $url );
		$html = '<div class="cp-sync-series-card__media">';

		if ( '' !== $url ) {
			$html .= '<img src="' . self::esc_url_attr( $url ) . '" alt="' . self::esc_attr_text( $alt ) . '">';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Linked series title.
	 *
	 * @param string $title     Series title.
	 * @param string $permalink Series permalink.
	 * @return string
	 */
	protected static function title_link( $title, $permalink ) {
		$permalink = EventCard::http_url( $permalink );
		$text      = self::esc_html_text( $title );

		if ( '' === $permalink ) {
			return $text;
		}

		return '<a href="' . self::esc_url_attr( $permalink ) . '">' . $text . '</a>';
	}

	/**
	 * Short description. The stylesheet limits it to two lines.
	 *
	 * @param mixed $description Excerpt or content.
	 * @return string
	 */
	protected static function description_html( $description ) {
		$text = self::plain_text( $description );

		return '' !== $text ? '<p class="cp-sync-series-card__summary">' . self::esc_html_text( $text ) . '</p>' : '';
	}

	/**
	 * Register or Learn more.
	 *
	 * @param string $url   Destination.
	 * @param string $label Button label.
	 * @return string
	 */
	protected static function button_html( $url, $label ) {
		$html = '<p class="cp-sync-series-card__action">';

		if ( '' === $url ) {
			$html .= '<span class="cp-sync-series-card__button cp-button is-light">' . self::esc_html_text( $label ) . '</span>';
		} else {
			$html .= '<a class="cp-sync-series-card__button cp-button" href="' . self::esc_url_attr( $url ) . '">' . self::esc_html_text( $label ) . '</a>';
		}

		$html .= '</p>';

		return $html;
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
