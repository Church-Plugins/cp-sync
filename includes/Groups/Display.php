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
			'ignore_sticky_posts'  => true,
			'no_found_rows'        => true,
			// CP Groups' archive query replaces posts_per_page. This flag lets us put the shortcode count back.
			'cp_sync_groups_limit' => $parsed['count'],
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
	 * Keep `[cp-sync-groups count]` after CP Groups rewrites the query.
	 *
	 * CP Groups hooks pre_get_posts and sets posts_per_page to its Groups per
	 * page setting for every front-end cp_group query. That replaced a
	 * shortcode count of 3 with whatever that setting was. Run later than
	 * that hook and put the shortcode count back.
	 *
	 * @since 1.2.0
	 * @param object $query Query object with get() and set().
	 * @return void
	 */
	public static function protect_shortcode_count( $query ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
			return;
		}

		$count = $query->get( 'cp_sync_groups_limit' );

		if ( null === $count || false === $count || '' === $count ) {
			return;
		}

		$query->set( 'posts_per_page', (int) $count );
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

			if ( is_string( $thumb ) && '' !== $thumb ) {
				$image = $thumb;
			}
		}

		// Sync keeps the source URL even when the local attachment is missing.
		if ( '' === $image ) {
			$stored = get_post_meta( $post_id, '_thumbnail_url', true );

			if ( is_string( $stored ) ) {
				$image = $stored;
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
		$is_card = array_key_exists( 'card', $args ) ? ! empty( $args['card'] ) : ! empty( $args['summary'] );
		$title   = self::plain_text( isset( $group['title'] ) ? $group['title'] : '' );
		$permalink = isset( $group['permalink'] ) ? $group['permalink'] : '';
		$names   = self::leader_names(
			isset( $group['leaders'] ) ? $group['leaders'] : null,
			isset( $group['leader'] ) ? $group['leader'] : ''
		);

		$html  = '<article class="cp-sync-groups__card">';
		$html .= self::image_html( isset( $group['image'] ) ? $group['image'] : '', $title, $is_card );
		$html .= '<' . $heading . ' class="cp-sync-groups__title">';
		$html .= self::title_html( $title, $permalink, $is_card || ! empty( $args['link_title'] ) );
		$html .= '</' . $heading . '>';
		$html .= self::description_html( isset( $group['content'] ) ? $group['content'] : '', ! empty( $args['summary'] ), $is_card );
		$html .= self::meta_html( __( 'Meets', 'cp-sync' ), isset( $group['time_desc'] ) ? $group['time_desc'] : '' );
		$html .= self::meta_html( __( 'Location', 'cp-sync' ), isset( $group['location'] ) ? $group['location'] : '' );
		$html .= self::leaders_html( $names );
		$html .= self::action_html(
			isset( $group['registration_url'] ) ? $group['registration_url'] : '',
			$permalink,
			$is_card
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
	 * Register link, or Learn more to the single group page.
	 *
	 * Cards always get one of those. Learn more is the group page on this site,
	 * not the ChMS public URL. Mailto links and any non-http(s) value are dropped
	 * so an email cannot leak through the button.
	 *
	 * @since 1.2.0
	 * @param mixed $registration_url Signup URL from the ChMS.
	 * @param mixed $permalink        Single group URL.
	 * @param bool  $card             True for archive and shortcode cards.
	 * @return array{url:string,label:string}|array
	 */
	public static function action_link( $registration_url, $permalink, $card = true ) {
		$registration_url = self::http_url( $registration_url );

		if ( '' !== $registration_url ) {
			return [
				'url'   => $registration_url,
				'label' => __( 'Register', 'cp-sync' ),
			];
		}

		if ( ! $card ) {
			return [];
		}

		$permalink = self::http_url( $permalink );

		if ( '' !== $permalink ) {
			return [
				'url'   => $permalink,
				'label' => __( 'Learn more', 'cp-sync' ),
			];
		}

		return [];
	}

	/**
	 * @param string $url  Image URL.
	 * @param string $alt  Alt text.
	 * @param bool   $card True reserves a placeholder when the image is missing.
	 * @return string
	 */
	protected static function image_html( $url, $alt, $card = false ) {
		$url = self::http_url( $url );

		if ( '' === $url ) {
			if ( ! $card ) {
				return '';
			}

			return '<div class="cp-sync-groups__placeholder" aria-hidden="true"></div>';
		}

		$src = esc_url( $url );

		if ( '' === $src ) {
			if ( ! $card ) {
				return '';
			}

			return '<div class="cp-sync-groups__placeholder" aria-hidden="true"></div>';
		}

		$alt = self::protect_block_template_html( esc_attr( $alt ), true );

		return '<img class="cp-sync-groups__image" src="' . $src . '" alt="' . $alt . '" />';
	}

	/**
	 * @param string $title     Group title.
	 * @param mixed  $permalink Single URL.
	 * @param bool   $link      Whether to link the title.
	 * @return string
	 */
	protected static function title_html( $title, $permalink, $link ) {
		$safe_title = self::protect_block_template_html( esc_html( $title ) );
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
	 * @param bool  $card    True keeps an empty description row so cards line up.
	 * @return string
	 */
	protected static function description_html( $content, $summary, $card = false ) {
		$content = is_string( $content ) ? $content : '';

		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			if ( ! $card ) {
				return '';
			}

			return '<p class="cp-sync-groups__description"></p>';
		}

		if ( $summary ) {
			$text = self::protect_block_template_html( esc_html( wp_trim_words( wp_strip_all_tags( $content ), 40, '…' ) ) );

			if ( '' === $text ) {
				return $card ? '<p class="cp-sync-groups__description"></p>' : '';
			}

			return '<p class="cp-sync-groups__description">' . $text . '</p>';
		}

		$html = self::protect_block_template_html( wp_kses_post( $content ) );

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

		return '<p class="cp-sync-groups__meta"><span class="cp-sync-groups__label">' . esc_html( $label ) . '</span> ' . self::protect_block_template_html( esc_html( $value ) ) . '</p>';
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
			$safe[] = self::protect_block_template_html( esc_html( $name ) );
		}

		$label = ( 1 === count( $names ) )
			? __( 'Leader', 'cp-sync' )
			: __( 'Leaders', 'cp-sync' );

		return '<p class="cp-sync-groups__leaders"><span class="cp-sync-groups__label">' . esc_html( $label ) . '</span> ' . implode( ', ', $safe ) . '</p>';
	}

	/**
	 * @param mixed $registration_url Signup URL.
	 * @param mixed $permalink        Single group URL.
	 * @param bool  $card             True for archive and shortcode cards.
	 * @return string
	 */
	protected static function action_html( $registration_url, $permalink, $card = true ) {
		$action = self::action_link( $registration_url, $permalink, $card );

		if ( ! $action ) {
			return '';
		}

		$href = self::protect_block_template_html( esc_url( $action['url'] ), true );

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
	 * Slug for a group type name.
	 *
	 * sanitize_title() keeps accented and non-Latin names aligned with the
	 * term slug WordPress stored. The ASCII fallback is only for tests and
	 * hosts where that function is unavailable.
	 *
	 * @param string $value Raw attribute.
	 * @return string
	 */
	protected static function slug( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( function_exists( 'sanitize_title' ) ) {
			$slug = sanitize_title( $value );

			return is_string( $slug ) ? $slug : '';
		}

		$value = strtolower( $value );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );

		return trim( (string) $value, '-' );
	}

	/**
	 * Keep square brackets as text so a later do_shortcode() cannot run them.
	 *
	 * @param string $html Markup or plain text.
	 * @return string
	 */
	public static function neutralize_shortcodes( $html ) {
		return str_replace( '[', '&#91;', (string) $html );
	}

	/**
	 * Stop a URL that sits alone on a line or in a paragraph from being autoembedded.
	 *
	 * Block themes run the captured template through WP_Embed::autoembed().
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	public static function guard_autoembed_urls( $html ) {
		$html    = (string) $html;
		$guarded = preg_replace(
			'#(<p(?:\s[^>]*)?>\s*)(https?://[^\s<>"]+)(\s*</p>)#i',
			'$1<span class="cp-sync-groups__url">$2</span>$3',
			$html
		);

		if ( is_string( $guarded ) ) {
			$html = $guarded;
		}

		$guarded = preg_replace(
			'#(^|\n)([ \t]*)(https?://[^\s<>"]+)([ \t]*)(?=\r?\n|$)#',
			'$1$2<span class="cp-sync-groups__url">$3</span>$4',
			$html
		);

		return is_string( $guarded ) ? $guarded : $html;
	}

	/**
	 * Markup captured for a block theme, with ChMS text left as text.
	 *
	 * get_the_block_template_html() runs autoembed, do_shortcode, and
	 * do_blocks on this string. Script and style tags are left unchanged.
	 *
	 * @param string $html       Rendered template HTML, or one attribute value.
	 * @param bool   $attribute True for an attribute value. Autoembed wrapping
	 *                          would turn a registration URL into a span.
	 * @return string
	 */
	public static function protect_block_template_html( $html, $attribute = false ) {
		$html = (string) $html;

		if ( $attribute ) {
			return self::neutralize_shortcodes( $html );
		}

		$parts = preg_split( '#(<script\b[^>]*>.*?</script>|<style\b[^>]*>.*?</style>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( ! is_array( $parts ) ) {
			return self::guard_autoembed_urls( self::neutralize_shortcodes( $html ) );
		}

		foreach ( $parts as $index => $part ) {
			if ( preg_match( '#^<(script|style)\b#i', $part ) ) {
				continue;
			}

			$parts[ $index ] = self::guard_autoembed_urls( self::neutralize_shortcodes( $part ) );
		}

		return implode( '', $parts );
	}
}
