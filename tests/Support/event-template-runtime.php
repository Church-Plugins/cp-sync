<?php
/**
 * Just enough of WordPress for the real event templates to run.
 *
 * get_template_hierarchy() is not defined here. The test loads includes/Templates.php.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

if ( ! class_exists( 'CP_Sync_Template_Post' ) ) {
	class CP_Sync_Template_Post extends WP_Post {
		/** @var string */
		public $post_title = '';

		/** @var string */
		public $post_content = '';

		/** @var string */
		public $post_excerpt = '';

		/** @var string */
		public $post_name = '';

		/** @var string */
		public $post_type = 'cp_event';

		/** @var string */
		public $post_status = 'publish';
	}
}

if ( ! class_exists( 'CP_Sync_Template_Query' ) ) {
	class CP_Sync_Template_Query {
		/** @var CP_Sync_Template_Post[] */
		public $posts = array();

		/** @var int */
		public $index = 0;

		/** @var CP_Sync_Template_Post|null */
		public $post = null;

		public function rewind() {
			$this->index = 0;
		}

		public function have_posts() {
			if ( $this->index < count( $this->posts ) ) {
				return true;
			}

			$this->rewind();

			return false;
		}

		public function the_post() {
			$this->post      = $this->posts[ $this->index ];
			$GLOBALS['post'] = $this->post;
			$this->index++;
		}
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		/** @var array */
		public $posts = array();

		public function __construct( $args = array() ) {
			$this->posts = isset( $GLOBALS['cp_sync_template_extra_posts'] ) ? $GLOBALS['cp_sync_template_extra_posts'] : array();
		}
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( ! is_array( $args ) ) {
			$args = array();
		}

		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'locate_template' ) ) {
	function locate_template( $templates, $load = false, $require_once = true ) {
		return '';
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'cp_sync' ) ) {
	function cp_sync() {
		return new class() {
			public function get_plugin_path() {
				return dirname( __DIR__, 2 ) . '/';
			}
		};
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		$url = trim( (string) $url );

		return preg_match( '#^https?://#i', $url ) ? $url : '';
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $content ) {
		return (string) $content;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
}

if ( ! function_exists( 'get_header' ) ) {
	function get_header() {}
}

if ( ! function_exists( 'get_footer' ) ) {
	function get_footer() {}
}

if ( ! function_exists( 'post_class' ) ) {
	function post_class( $class = '' ) {
		echo 'class="' . esc_attr( $class ) . '"';
	}
}

if ( ! function_exists( 'post_type_archive_title' ) ) {
	function post_type_archive_title( $prefix = '', $display = true ) {
		$title = isset( $GLOBALS['cp_sync_template_archive_title'] ) ? $GLOBALS['cp_sync_template_archive_title'] : 'Events';

		if ( $display ) {
			echo esc_html( $prefix . $title );
		}

		return $prefix . $title;
	}
}

if ( ! function_exists( 'have_posts' ) ) {
	function have_posts() {
		return $GLOBALS['wp_query']->have_posts();
	}
}

if ( ! function_exists( 'the_post' ) ) {
	function the_post() {
		$GLOBALS['wp_query']->the_post();
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post = null ) {
		if ( null === $post ) {
			return $GLOBALS['post'];
		}

		return $post;
	}
}

if ( ! function_exists( 'setup_postdata' ) ) {
	/**
	 * WordPress does not assign the global post here. Templates that call
	 * this must set $GLOBALS['post'] themselves.
	 *
	 * @param mixed $post Post object. Unused, matching core.
	 * @return void
	 */
	function setup_postdata( $post ) {
		unset( $post );
	}
}

if ( ! function_exists( 'wp_reset_postdata' ) ) {
	function wp_reset_postdata() {
		if ( isset( $GLOBALS['wp_query'] ) && is_object( $GLOBALS['wp_query'] ) && isset( $GLOBALS['wp_query']->post ) ) {
			$GLOBALS['post'] = $GLOBALS['wp_query']->post;
		}
	}
}

if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
		return (int) $GLOBALS['post']->ID;
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post = null ) {
		if ( null === $post ) {
			return (string) $GLOBALS['post']->post_title;
		}

		if ( is_object( $post ) ) {
			return (string) $post->post_title;
		}

		foreach ( array( $GLOBALS['wp_query']->posts, $GLOBALS['cp_sync_template_extra_posts'] ) as $list ) {
			foreach ( (array) $list as $item ) {
				if ( (int) $item->ID === (int) $post ) {
					return (string) $item->post_title;
				}
			}
		}

		return '';
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = null ) {
		$post = null === $post ? $GLOBALS['post'] : $post;
		$name = is_object( $post ) ? $post->post_name : 'item';
		$type = is_object( $post ) ? $post->post_type : 'cp_event';
		$base = 'cp_event_series' === $type ? 'event-series' : 'events';

		return 'https://example.com/' . $base . '/' . $name;
	}
}

if ( ! function_exists( 'get_post_type_archive_link' ) ) {
	function get_post_type_archive_link( $post_type ) {
		return 'cp_event_series' === $post_type ? 'https://example.com/event-series/' : 'https://example.com/events/';
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$id    = is_object( $post_id ) ? (int) $post_id->ID : (int) $post_id;
		$store = isset( $GLOBALS['cp_sync_template_meta'][ $id ] ) ? $GLOBALS['cp_sync_template_meta'][ $id ] : array();

		if ( '' === $key ) {
			return $store;
		}

		return isset( $store[ $key ] ) ? $store[ $key ] : '';
	}
}

if ( ! function_exists( 'get_post_status' ) ) {
	function get_post_status( $post = null ) {
		return 'publish';
	}
}

if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $post_id ) {
		$post = $GLOBALS['post'];

		return isset( $post->$field ) ? $post->$field : '';
	}
}

if ( ! function_exists( 'has_post_thumbnail' ) ) {
	function has_post_thumbnail( $post = null ) {
		return false;
	}
}

if ( ! function_exists( 'has_excerpt' ) ) {
	function has_excerpt( $post = null ) {
		$post = $GLOBALS['post'];

		return isset( $post->post_excerpt ) && '' !== $post->post_excerpt;
	}
}

if ( ! function_exists( 'get_the_excerpt' ) ) {
	function get_the_excerpt( $post = null ) {
		return (string) $GLOBALS['post']->post_excerpt;
	}
}

if ( ! function_exists( 'the_posts_pagination' ) ) {
	function the_posts_pagination( $args = array() ) {
		echo '<nav class="navigation pagination"><a class="prev page-numbers" href="https://example.com/events/page/2">Previous</a></nav>';
	}
}
