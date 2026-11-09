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
 * The markup and class names match CP Groups' parts/group-list.php and
 * parts/group-single.php, so its styles and design changes apply here too.
 * There is no modal, filter, or contact form.
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
	 * Term groups in CP Groups' display order, keyed by their class suffix.
	 *
	 * @var array<string,string>
	 */
	const TERM_GROUPS = [
		'category'   => 'cp_group_category',
		'type'       => 'cp_group_type',
		'life-stage' => 'cp_group_life_stage',
	];

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

			return '<p class="cp-sync-display cp-sync-display--embed">' . esc_html__( 'No groups found.', 'cp-sync' ) . '</p>';
		}

		$html = '<div class="cp-sync-display cp-sync-display--embed cp-groups-archive"><div class="cp-groups-archive--list">';

		while ( $query->have_posts() ) {
			$query->the_post();
			$html .= '<div class="cp-groups-archive--list--item">';
			$html .= self::render_post(
				get_the_ID(),
				[
					'summary'    => true,
					'heading'    => 'h2',
					'link_title' => true,
				]
			);
			$html .= '</div>';
		}

		$html .= '</div></div>';

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
				'public_url'       => get_post_meta( $post_id, 'public_url', true ),
				'accessible'       => get_post_meta( $post_id, 'handicap_accessible', true ),
				'kid_friendly'     => get_post_meta( $post_id, 'kid_friendly', true ),
				'meets_online'     => get_post_meta( $post_id, 'meets_online', true ),
				'is_full'          => get_post_meta( $post_id, GroupMeta::FULL_KEY, true ),
				'terms'            => self::group_terms( $post_id ),
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

		$args = array_merge(
			[
				'summary'    => true,
				'heading'    => 'h2',
				'link_title' => true,
			],
			is_array( $args ) ? $args : []
		);

		return empty( $args['summary'] ) ? self::single_html( $group, $args ) : self::item_html( $group, $args );
	}

	/**
	 * One group in a list. Matches CP Groups' parts/group-list.php.
	 *
	 * @param array $group Group fields.
	 * @param array $args  heading, link_title.
	 * @return string
	 */
	protected static function item_html( $group, $args ) {
		$title     = self::plain_text( isset( $group['title'] ) ? $group['title'] : '' );
		$permalink = isset( $group['permalink'] ) ? $group['permalink'] : '';
		$heading   = ( 'h1' === $args['heading'] ) ? 'h1' : 'h2';

		$html  = '<div class="cp-group-item cp-group-item--disable-modal">';
		$html .= '<div class="cp-group-item--thumb">' . self::thumb_html( isset( $group['image'] ) ? $group['image'] : '', $title, $permalink ) . '</div>';
		$html .= '<div class="cp-group-item--details">';
		$html .= self::terms_html( isset( $group['terms'] ) ? $group['terms'] : [], 'cp-group-item' );
		$html .= '<' . $heading . ' class="cp-group-item--title">' . self::title_html( $title, $permalink, true ) . '</' . $heading . '>';
		$html .= self::meta_html( $group, 'cp-group-item--meta' );
		$html .= self::description_html( isset( $group['content'] ) ? $group['content'] : '', true, 'cp-group-item--content' );
		$html .= self::attributes_html( $group );
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * One group's page. Matches CP Groups' parts/group-single.php.
	 *
	 * @param array $group Group fields.
	 * @param array $args  heading, link_title.
	 * @return string
	 */
	protected static function single_html( $group, $args ) {
		$title     = self::plain_text( isset( $group['title'] ) ? $group['title'] : '' );
		$permalink = isset( $group['permalink'] ) ? $group['permalink'] : '';
		$heading   = ( 'h2' === $args['heading'] ) ? 'h2' : 'h1';
		$names     = self::leader_names(
			isset( $group['leaders'] ) ? $group['leaders'] : null,
			isset( $group['leader'] ) ? $group['leader'] : ''
		);
		$image     = self::image_html( isset( $group['image'] ) ? $group['image'] : '', $title );

		$html = '<div class="cp-group-single">';

		if ( '' !== $image ) {
			$html .= '<div class="cp-group-single--thumb">' . $image . '</div>';
		}

		$html .= '<div class="cp-group-single--details">';
		$html .= self::terms_html( isset( $group['terms'] ) ? $group['terms'] : [], 'cp-group-single' );
		$html .= '<' . $heading . ' class="cp-group-single--title">' . self::title_html( $title, $permalink, ! empty( $args['link_title'] ) ) . '</' . $heading . '>';
		$html .= self::leaders_html( $names );
		$html .= self::meta_html( $group, 'cp-group-single--meta' );
		$html .= self::description_html( isset( $group['content'] ) ? $group['content'] : '', false, 'cp-group-single--content' );
		$html .= self::attributes_html( $group );
		$html .= self::actions_html( $group );
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Category, type, and life stage terms for one group, with their archive links.
	 *
	 * Groups in the order CP Groups shows them. A term without a usable link
	 * keeps an empty URL and prints as a label.
	 *
	 * @since 1.2.0
	 * @param int $post_id Group post ID.
	 * @return array<string,array<int,array{name:string,url:string}>>
	 */
	public static function group_terms( $post_id ) {
		$groups = [];

		if ( ! function_exists( 'get_the_terms' ) ) {
			return $groups;
		}

		foreach ( self::TERM_GROUPS as $key => $taxonomy ) {
			$found = get_the_terms( $post_id, $taxonomy );

			if ( ! is_array( $found ) ) {
				continue;
			}

			foreach ( $found as $term ) {
				if ( ! is_object( $term ) || ! isset( $term->name ) ) {
					continue;
				}

				$url = function_exists( 'get_term_link' ) ? get_term_link( $term ) : '';

				$groups[ $key ][] = [
					'name' => self::plain_text( $term->name ),
					'url'  => is_string( $url ) ? $url : '',
				];
			}
		}

		return $groups;
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
	 * Term buttons, like CP Groups. The first group is solid and the rest are outlined.
	 *
	 * @param mixed  $terms  Result of group_terms().
	 * @param string $prefix cp-group-item or cp-group-single.
	 * @return string
	 */
	protected static function terms_html( $terms, $prefix ) {
		if ( ! is_array( $terms ) ) {
			return '';
		}

		$html = '';

		foreach ( array_keys( self::TERM_GROUPS ) as $key ) {
			if ( empty( $terms[ $key ] ) || ! is_array( $terms[ $key ] ) ) {
				continue;
			}

			$class   = 'cp-button is-xsmall' . ( 'category' === $key ? '' : ' is-transparent' );
			$buttons = '';
			$seen    = [];

			foreach ( $terms[ $key ] as $term ) {
				$name = self::plain_text( isset( $term['name'] ) ? $term['name'] : '' );

				if ( '' === $name || isset( $seen[ $name ] ) ) {
					continue;
				}

				$seen[ $name ] = true;
				$label         = self::protect_block_template_html( esc_html( $name ) );
				$url           = self::http_url( isset( $term['url'] ) ? $term['url'] : '' );
				$href          = '' !== $url ? esc_url( $url ) : '';

				$buttons .= '' !== $href
					? '<a class="' . $class . '" href="' . self::protect_block_template_html( $href, true ) . '">' . $label . '</a>'
					: '<span class="' . $class . '">' . $label . '</span>';
			}

			if ( '' !== $buttons ) {
				$html .= '<div class="' . $prefix . '--' . $key . '">' . $buttons . '</div>';
			}
		}

		return '' !== $html ? '<div class="' . $prefix . '--categories">' . $html . '</div>' : '';
	}

	/**
	 * Icon from the same font CP products use.
	 *
	 * @param string $name Material icon name.
	 * @return string
	 */
	protected static function icon( $name ) {
		return '<span class="material-icons-outlined" aria-hidden="true">' . esc_html( $name ) . '</span>';
	}

	/**
	 * Meeting time and location.
	 *
	 * @param array  $group Group fields.
	 * @param string $class Wrapper class.
	 * @return string
	 */
	protected static function meta_html( $group, $class ) {
		$rows = '';

		foreach ( [
			'time_desc' => [ 'cp-group--item--meta--start-time', 'calendar_today' ],
			'location'  => [ 'cp-group--item--meta--location', 'location_on' ],
		] as $key => $row ) {
			$value = self::plain_text( isset( $group[ $key ] ) ? $group[ $key ] : '' );

			if ( '' !== $value ) {
				$rows .= '<div class="' . $row[0] . '">' . self::icon( $row[1] ) . ' ' . self::protect_block_template_html( esc_html( $value ) ) . '</div>';
			}
		}

		return '' !== $rows ? '<div class="' . $class . '">' . $rows . '</div>' : '';
	}

	/**
	 * Accessible, kid friendly, online, and full labels.
	 *
	 * @param array $group Group fields.
	 * @return string
	 */
	protected static function attributes_html( $group ) {
		$html = '';

		foreach ( [
			'accessible'   => [ 'accessible', 'accessible', __( 'Wheelchair Accessible', 'cp-sync' ) ],
			'kid_friendly' => [ 'kid-friendly', 'child_care', __( 'Kid Friendly', 'cp-sync' ) ],
			'meets_online' => [ 'meets-online', 'videocam', __( 'Meets Online', 'cp-sync' ) ],
			'is_full'      => [ 'is-full', 'report', __( 'Full', 'cp-sync' ) ],
		] as $key => $attribute ) {
			if ( self::is_on( isset( $group[ $key ] ) ? $group[ $key ] : '' ) ) {
				$html .= '<span class="cp-group-item--attributes--' . $attribute[0] . '">' . self::icon( $attribute[1] ) . ' ' . esc_html( $attribute[2] ) . '</span>';
			}
		}

		return '' !== $html ? '<div class="cp-group-item--attributes">' . $html . '</div>' : '';
	}

	/**
	 * Whether a stored checkbox value is on.
	 *
	 * @param mixed $value Meta value.
	 * @return bool
	 */
	protected static function is_on( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$value = strtolower( self::plain_text( $value ) );

		return '' !== $value && ! in_array( $value, [ '0', 'off', 'false', 'no' ], true );
	}

	/**
	 * Image for a list card, set as a background like CP Groups.
	 *
	 * @param mixed  $url       Image URL.
	 * @param string $alt       Alt text.
	 * @param mixed  $permalink Single group URL.
	 * @return string
	 */
	protected static function thumb_html( $url, $alt, $permalink ) {
		$src   = esc_url( self::css_safe_url( self::http_url( $url ) ) );
		$link  = self::http_url( $permalink );
		$href  = '' !== $link ? ' href="' . self::protect_block_template_html( esc_url( $link ), true ) . '" aria-hidden="true" tabindex="-1"' : '';

		if ( '' === $src ) {
			return '<a class="cp-group-item--thumb--canvas is-empty"' . $href . '></a>';
		}

		$safe_src = self::protect_block_template_html( $src, true );

		return '<a class="cp-group-item--thumb--canvas"' . $href . ' style="background: url(\'' . $safe_src . '\') 0% 0% / cover;">'
			. '<img alt="' . self::protect_block_template_html( esc_attr( $alt ), true ) . '" src="' . $safe_src . '"></a>';
	}

	/**
	 * Percent-encode the characters that could end a CSS url() value.
	 *
	 * esc_url() writes a quote as an HTML entity, and the browser decodes that
	 * before it reads the style attribute. Encoding first leaves nothing to decode.
	 * Entities are decoded before that, so &#39; is encoded as a quote and the
	 * semicolon in &amp; is not mistaken for one that ends a CSS declaration.
	 *
	 * @param string $url http(s) URL.
	 * @return string
	 */
	protected static function css_safe_url( $url ) {
		return str_replace(
			[ "'", '"', '(', ')', '\\', ';' ],
			[ '%27', '%22', '%28', '%29', '%5C', '%3B' ],
			html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' )
		);
	}

	/**
	 * Image for a group's page.
	 *
	 * @param mixed  $url Image URL.
	 * @param string $alt Alt text.
	 * @return string
	 */
	protected static function image_html( $url, $alt ) {
		$src = esc_url( self::http_url( $url ) );

		if ( '' === $src ) {
			return '';
		}

		return '<img src="' . self::protect_block_template_html( $src, true ) . '" alt="' . self::protect_block_template_html( esc_attr( $alt ), true ) . '" />';
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
	 * @param mixed  $content Description.
	 * @param bool   $summary True trims to plain text for a card.
	 * @param string $class   Wrapper class.
	 * @return string
	 */
	protected static function description_html( $content, $summary, $class ) {
		$content = is_string( $content ) ? $content : '';

		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return '';
		}

		$html = $summary
			? self::protect_block_template_html( esc_html( wp_trim_words( wp_strip_all_tags( $content ), 40, '…' ) ) )
			: self::protect_block_template_html( wp_kses_post( $content ) );

		return '' !== trim( $html ) ? '<div class="' . $class . '">' . $html . '</div>' : '';
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

		return '<h6 class="cp-group-single--leader">' . implode( ', ', $safe ) . '</h6>';
	}

	/**
	 * View Details and Register Now, like CP Groups.
	 *
	 * Mailto links and any non-http(s) value are dropped so an email cannot leak
	 * through a button.
	 *
	 * @param array $group Group fields.
	 * @return string
	 */
	protected static function actions_html( $group ) {
		$html = '';

		foreach ( [
			'public_url'       => [ 'cp-button is-large', __( 'View Details', 'cp-sync' ) ],
			'registration_url' => [ 'cp-button', __( 'Register Now', 'cp-sync' ) ],
		] as $key => $action ) {
			$url = self::http_url( isset( $group[ $key ] ) ? $group[ $key ] : '' );

			if ( '' === $url ) {
				continue;
			}

			$href = self::protect_block_template_html( esc_url( $url ), true );

			if ( '' !== $href ) {
				$html .= '<div class="cp-group-single--registration-url"><a class="' . $action[0] . '" href="' . $href . '" target="_blank" rel="noopener">' . esc_html( $action[1] ) . '</a></div>';
			}
		}

		return '' !== $html ? '<div class="cp-group-single--actions">' . $html . '</div>' : '';
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
			'$1<span class="cp-group-url">$2</span>$3',
			$html
		);

		if ( is_string( $guarded ) ) {
			$html = $guarded;
		}

		$guarded = preg_replace(
			'#(^|\n)([ \t]*)(https?://[^\s<>"]+)([ \t]*)(?=\r?\n|$)#',
			'$1$2<span class="cp-group-url">$3</span>$4',
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
