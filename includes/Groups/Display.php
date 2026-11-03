<?php
/**
 * Front-end markup for a synced group.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Groups;

/**
 * Renders group cards and the [cp-sync-groups] shortcode.
 *
 * Leader emails are never read into the markup. Names come from the CP Groups
 * 1.2 `leaders` rows first, then from the legacy `leader` value.
 *
 * @since 1.2.0
 */
class Display {

	/**
	 * Default number of groups when the shortcode omits count.
	 *
	 * @var int
	 */
	const DEFAULT_COUNT = 12;

	/**
	 * [cp-sync-groups count="12" type="small-groups"]
	 *
	 * `type` is the cp_group_type slug. `group_type` is accepted as an alias.
	 * count="-1" shows every published group.
	 *
	 * @since 1.2.0
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		if ( ! post_type_exists( 'cp_group' ) ) {
			return '';
		}

		$query = new \WP_Query( self::query_args( $atts ) );

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();

			return '<p class="cp-sync-groups__empty">' . esc_html__( 'No groups found.', 'cp-sync' ) . '</p>';
		}

		$html = '<div class="cp-sync-groups"><ul class="cp-sync-groups__list">';

		while ( $query->have_posts() ) {
			$query->the_post();
			$html .= '<li class="cp-sync-groups__item">';
			$html .= self::render_post(
				get_the_ID(),
				[
					'summary'    => true,
					'heading'    => 'h2',
					'link_title' => true,
				]
			);
			$html .= '</li>';
		}

		$html .= '</ul></div>';

		wp_reset_postdata();

		return $html;
	}

	/**
	 * WP_Query arguments for the shortcode.
	 *
	 * @since 1.2.0
	 * @param array|string $atts Shortcode attributes.
	 * @return array
	 */
	public static function query_args( $atts ) {
		$parsed = self::parse_shortcode_atts( $atts );
		$args   = [
			'post_type'           => 'cp_group',
			'post_status'         => 'publish',
			'posts_per_page'      => $parsed['count'],
			'orderby'             => 'title',
			'order'               => 'ASC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		];

		if ( '' !== $parsed['type'] ) {
			$args['tax_query'] = [
				[
					'taxonomy' => 'cp_group_type',
					'field'    => 'slug',
					'terms'    => $parsed['type'],
				],
			];
		}

		return $args;
	}

	/**
	 * Normalize shortcode attributes.
	 *
	 * @since 1.2.0
	 * @param array|string $atts Raw attributes.
	 * @return array{count:int,type:string}
	 */
	public static function parse_shortcode_atts( $atts ) {
		if ( ! is_array( $atts ) ) {
			$atts = [];
		}

		$count = array_key_exists( 'count', $atts ) ? $atts['count'] : self::DEFAULT_COUNT;

		if ( ! is_numeric( $count ) ) {
			$count = self::DEFAULT_COUNT;
		}

		$count = (int) $count;

		if ( 0 === $count || $count < -1 ) {
			$count = self::DEFAULT_COUNT;
		}

		$raw_type = '';

		if ( isset( $atts['type'] ) && '' !== trim( (string) $atts['type'] ) ) {
			$raw_type = (string) $atts['type'];
		} elseif ( isset( $atts['group_type'] ) ) {
			$raw_type = (string) $atts['group_type'];
		}

		return [
			'count' => $count,
			'type'  => self::slug( $raw_type ),
		];
	}

	/**
	 * Markup for one group post.
	 *
	 * @since 1.2.0
	 * @param int   $post_id Post ID.
	 * @param array $args    summary, heading (h1|h2), link_title.
	 * @return string
	 */
	public static function render_post( $post_id, $args = [] ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return '';
		}

		$image = '';

		if ( function_exists( 'get_the_post_thumbnail_url' ) ) {
			$thumb = get_the_post_thumbnail_url( $post_id, 'large' );

			if ( is_string( $thumb ) ) {
				$image = $thumb;
			}
		}

		$permalink = get_permalink( $post );

		return self::render_group(
			[
				'title'            => get_the_title( $post ),
				'permalink'        => is_string( $permalink ) ? $permalink : '',
				'image'            => $image,
				'content'          => isset( $post->post_content ) ? $post->post_content : '',
				'time_desc'        => get_post_meta( $post_id, 'time_desc', true ),
				'location'         => get_post_meta( $post_id, 'location', true ),
				'leaders'          => get_post_meta( $post_id, 'leaders', true ),
				'leader'           => get_post_meta( $post_id, 'leader', true ),
				'registration_url' => get_post_meta( $post_id, 'registration_url', true ),
				'public_url'       => get_post_meta( $post_id, 'public_url', true ),
			],
			$args
		);
	}

	/**
	 * Markup for an already-loaded group.
	 *
	 * `leader_email` is ignored on purpose. Emails inside `leaders` rows are ignored.
	 *
	 * @since 1.2.0
	 * @param array $group Group fields.
	 * @param array $args  summary, heading (h1|h2), link_title.
	 * @return string
	 */
	public static function render_group( $group, $args = [] ) {
		if ( ! is_array( $group ) ) {
			return '';
		}

		$defaults = [
			'summary'    => true,
			'heading'    => 'h2',
			'link_title' => true,
		];

		if ( ! is_array( $args ) ) {
			$args = [];
		}

		$args = array_merge( $defaults, $args );

		$heading = ( 'h1' === $args['heading'] ) ? 'h1' : 'h2';
		$title   = self::plain_text( isset( $group['title'] ) ? $group['title'] : '' );
		$names   = self::leader_names(
			isset( $group['leaders'] ) ? $group['leaders'] : null,
			isset( $group['leader'] ) ? $group['leader'] : ''
		);

		$html  = '<article class="cp-sync-groups__card">';
		$html .= self::image_html( isset( $group['image'] ) ? $group['image'] : '', $title );
		$html .= '<' . $heading . ' class="cp-sync-groups__title">';
		$html .= self::title_html( $title, isset( $group['permalink'] ) ? $group['permalink'] : '', ! empty( $args['link_title'] ) );
		$html .= '</' . $heading . '>';
		$html .= self::description_html( isset( $group['content'] ) ? $group['content'] : '', ! empty( $args['summary'] ) );
		$html .= self::meta_html( __( 'Meets', 'cp-sync' ), isset( $group['time_desc'] ) ? $group['time_desc'] : '' );
		$html .= self::meta_html( __( 'Location', 'cp-sync' ), isset( $group['location'] ) ? $group['location'] : '' );
		$html .= self::leaders_html( $names );
		$html .= self::action_html(
			isset( $group['registration_url'] ) ? $group['registration_url'] : '',
			isset( $group['public_url'] ) ? $group['public_url'] : ''
		);
		$html .= '</article>';

		return $html;
	}

	/**
	 * Leader names from CP Groups 1.2 rows, then the legacy leader field.
	 *
	 * Rows that only have an email are skipped. The email is never returned.
	 *
	 * @since 1.2.0
	 * @param mixed  $leaders       `leaders` meta.
	 * @param mixed  $legacy_leader `leader` meta.
	 * @return string[]
	 */
	public static function leader_names( $leaders, $legacy_leader ) {
		$names = [];

		if ( is_array( $leaders ) ) {
			if ( isset( $leaders['name'] ) && ! isset( $leaders[0] ) ) {
				$leaders = [ $leaders ];
			}

			foreach ( $leaders as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$name = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

				if ( '' !== $name ) {
					$names[] = $name;
				}
			}
		}

		if ( $names ) {
			return $names;
		}

		$legacy = self::plain_text( $legacy_leader );

		if ( '' === $legacy ) {
			return [];
		}

		return [ $legacy ];
	}

	/**
	 * Register link, or Learn more when only a public page URL exists.
	 *
	 * Mailto links and any non-http(s) value are dropped so an email cannot
	 * leak through the button.
	 *
	 * @since 1.2.0
	 * @param mixed $registration_url Signup URL from the ChMS.
	 * @param mixed $public_url       Public group page from the ChMS.
	 * @return array{url:string,label:string}|array
	 */
	public static function action_link( $registration_url, $public_url ) {
		$registration_url = self::http_url( $registration_url );

		if ( '' !== $registration_url ) {
			return [
				'url'   => $registration_url,
				'label' => __( 'Register', 'cp-sync' ),
			];
		}

		$public_url = self::http_url( $public_url );

		if ( '' !== $public_url ) {
			return [
				'url'   => $public_url,
				'label' => __( 'Learn more', 'cp-sync' ),
			];
		}

		return [];
	}

	/**
	 * @param string $url Image URL.
	 * @param string $alt Alt text.
	 * @return string
	 */
	protected static function image_html( $url, $alt ) {
		$url = self::http_url( $url );

		if ( '' === $url ) {
			return '';
		}

		$src = esc_url( $url );

		if ( '' === $src ) {
			return '';
		}

		return '<img class="cp-sync-groups__image" src="' . $src . '" alt="' . esc_attr( $alt ) . '" />';
	}

	/**
	 * @param string $title     Group title.
	 * @param mixed  $permalink Single URL.
	 * @param bool   $link      Whether to link the title.
	 * @return string
	 */
	protected static function title_html( $title, $permalink, $link ) {
		$safe_title = esc_html( $title );
		$permalink  = self::http_url( $permalink );

		if ( ! $link || '' === $permalink ) {
			return $safe_title;
		}

		$href = esc_url( $permalink );

		if ( '' === $href ) {
			return $safe_title;
		}

		return '<a href="' . $href . '">' . $safe_title . '</a>';
	}

	/**
	 * @param mixed $content Description.
	 * @param bool  $summary True trims to plain text for a card.
	 * @return string
	 */
	protected static function description_html( $content, $summary ) {
		$content = is_string( $content ) ? $content : '';

		if ( '' === trim( $content ) ) {
			return '';
		}

		if ( $summary ) {
			$text = esc_html( wp_trim_words( wp_strip_all_tags( $content ), 40, '…' ) );

			if ( '' === $text ) {
				return '';
			}

			return '<p class="cp-sync-groups__description">' . $text . '</p>';
		}

		$html = wp_kses_post( $content );

		if ( '' === trim( $html ) ) {
			return '';
		}

		return '<div class="cp-sync-groups__description">' . $html . '</div>';
	}

	/**
	 * @param string $label Visible label.
	 * @param mixed  $value Meta value.
	 * @return string
	 */
	protected static function meta_html( $label, $value ) {
		$value = self::plain_text( $value );

		if ( '' === $value ) {
			return '';
		}

		return '<p class="cp-sync-groups__meta"><span class="cp-sync-groups__label">' . esc_html( $label ) . '</span> ' . esc_html( $value ) . '</p>';
	}

	/**
	 * @param string[] $names Leader names.
	 * @return string
	 */
	protected static function leaders_html( $names ) {
		if ( ! $names ) {
			return '';
		}

		$safe = [];

		foreach ( $names as $name ) {
			$safe[] = esc_html( $name );
		}

		$label = ( 1 === count( $names ) )
			? __( 'Leader', 'cp-sync' )
			: __( 'Leaders', 'cp-sync' );

		return '<p class="cp-sync-groups__leaders"><span class="cp-sync-groups__label">' . esc_html( $label ) . '</span> ' . implode( ', ', $safe ) . '</p>';
	}

	/**
	 * @param mixed $registration_url Signup URL.
	 * @param mixed $public_url       Public page URL.
	 * @return string
	 */
	protected static function action_html( $registration_url, $public_url ) {
		$action = self::action_link( $registration_url, $public_url );

		if ( ! $action ) {
			return '';
		}

		$href = esc_url( $action['url'] );

		if ( '' === $href ) {
			return '';
		}

		return '<p class="cp-sync-groups__action"><a class="cp-sync-groups__button" href="' . $href . '">' . esc_html( $action['label'] ) . '</a></p>';
	}

	/**
	 * Scalar text, trimmed.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected static function plain_text( $value ) {
		if ( is_string( $value ) || is_numeric( $value ) ) {
			return trim( (string) $value );
		}

		return '';
	}

	/**
	 * http(s) URL, or an empty string.
	 *
	 * @param mixed $url Candidate URL.
	 * @return string
	 */
	protected static function http_url( $url ) {
		$url = self::plain_text( $url );

		if ( '' === $url || false !== strpos( $url, ' ' ) ) {
			return '';
		}

		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

		if ( 'http' !== $scheme && 'https' !== $scheme ) {
			return '';
		}

		return $url;
	}

	/**
	 * ASCII slug, close to sanitize_title for group type names.
	 *
	 * @param string $value Raw attribute.
	 * @return string
	 */
	protected static function slug( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );

		return trim( (string) $value, '-' );
	}
}
