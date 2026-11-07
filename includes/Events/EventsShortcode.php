<?php
/**
 * [cp-sync-events] shortcode.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Upcoming-event list for a page or post.
 *
 * Attributes: limit, category, series. limit defaults to 5. category is a
 * category slug. series is a series post ID or its slug.
 *
 * @since 1.2.0
 */
class EventsShortcode {

	const TAG = 'cp-sync-events';

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public static function register() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Normalize shortcode attributes.
	 *
	 * @param mixed $atts Attribute array from WordPress, or null.
	 * @return array{limit: int, category: string, series: string}
	 */
	public static function parse_atts( $atts ) {
		if ( ! is_array( $atts ) ) {
			$atts = array();
		}

		if ( ! isset( $atts['limit'] ) || '' === $atts['limit'] ) {
			$limit = 5;
		} else {
			$limit = (int) $atts['limit'];

			if ( $limit < 1 ) {
				$limit = -1;
			}
		}

		$category = isset( $atts['category'] ) ? self::slug( $atts['category'] ) : '';
		$series   = isset( $atts['series'] ) ? trim( (string) $atts['series'] ) : '';

		return array(
			'limit'    => $limit,
			'category' => $category,
			'series'   => $series,
		);
	}

	/**
	 * WP_Query arguments for the parsed attributes.
	 *
	 * $series_post_id is the resolved series post. Pass 0 when the shortcode
	 * did not ask for a series. Pass a negative id when a slug did not match
	 * so the query returns nothing instead of every event.
	 *
	 * @param array  $parsed         Result of parse_atts().
	 * @param string $now_utc        Current UTC `Y-m-d H:i:s`.
	 * @param int    $series_post_id Resolved series post ID.
	 * @return array
	 */
	public static function query_args( array $parsed, $now_utc, $series_post_id = 0 ) {
		$args = array(
			'post_type'           => PostTypes::EVENT,
			'post_status'         => 'publish',
			'has_password'        => false,
			'posts_per_page'      => (int) $parsed['limit'],
			'meta_key'            => Meta::START_UTC,
			'orderby'             => 'meta_value',
			'order'               => 'ASC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'meta_query'          => array(
				array(
					'key'     => Meta::END_UTC,
					'value'   => (string) $now_utc,
					'compare' => '>=',
					'type'    => 'CHAR',
				),
			),
		);

		if ( '' !== $parsed['category'] ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => PostTypes::CATEGORY,
					'field'    => 'slug',
					'terms'    => $parsed['category'],
				),
			);
		}

		if ( 0 !== (int) $series_post_id ) {
			$args['meta_query'][] = array(
				'key'   => Meta::SERIES_ID,
				'value' => (string) (int) $series_post_id,
			);
		}

		return $args;
	}

	/**
	 * Render the shortcode.
	 *
	 * @param mixed $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$parsed    = self::parse_atts( $atts );
		$series_id = self::resolve_series_id( $parsed['series'] );
		$now       = gmdate( 'Y-m-d H:i:s' );

		if ( ! class_exists( '\WP_Query' ) ) {
			return '';
		}

		$query = new \WP_Query( self::query_args( $parsed, $now, $series_id ) );
		$html  = \CP_Sync\get_template_part(
			'events/list',
			array(
				'events'          => $query->posts,
				'show_series_link' => true,
			),
			true
		);
		wp_reset_postdata();

		return is_string( $html ) ? '<div class="cp-sync-display cp-sync-display--embed cp-sync-events">' . $html . '</div>' : '';
	}

	/**
	 * Turn a series attribute into a post ID.
	 *
	 * @param string $series Post ID or slug. Empty when the attribute is omitted.
	 * @return int
	 */
	public static function resolve_series_id( $series ) {
		$series = trim( (string) $series );

		if ( '' === $series ) {
			return 0;
		}

		if ( ctype_digit( $series ) ) {
			return (int) $series;
		}

		if ( ! function_exists( 'get_page_by_path' ) ) {
			return -1;
		}

		$post = get_page_by_path( self::slug( $series ), OBJECT, PostTypes::SERIES );

		return ( $post && isset( $post->ID ) ) ? (int) $post->ID : -1;
	}

	/**
	 * Slug form of a category or series name.
	 *
	 * @param mixed $value Raw attribute.
	 * @return string
	 */
	public static function slug( $value ) {
		$value = trim( (string) $value );

		if ( function_exists( 'sanitize_title' ) ) {
			return sanitize_title( $value );
		}

		$value = strtolower( $value );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );

		return trim( (string) $value, '-' );
	}
}
