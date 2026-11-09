<?php
/**
 * Small stand-ins for the event canvas tests.
 *
 * load_template() matches the WordPress loader: the template runs with the
 * query globals, not the caller's local variables.
 *
 * @package CP_Sync
 */

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * @param array<string, mixed> $args     Arguments.
	 * @param array<string, mixed> $defaults Defaults.
	 * @return array<string, mixed>
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		if ( ! is_array( $args ) ) {
			$args = array();
		}

		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	/**
	 * @param string $value Path.
	 * @return string
	 */
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'cp_sync' ) ) {
	/**
	 * @return object
	 */
	function cp_sync() {
		return new class() {
			/**
			 * @return string
			 */
			public function get_plugin_path() {
				return dirname( __DIR__, 2 ) . '/';
			}
		};
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	/**
	 * @param string|string[] $post_type Post type.
	 * @return bool
	 */
	function is_singular( $post_type = '' ) {
		unset( $post_type );

		return ! empty( $GLOBALS['cp_sync_canvas_is_singular'] );
	}
}

if ( ! function_exists( 'is_post_type_archive' ) ) {
	/**
	 * @param string|string[] $post_type Post type.
	 * @return bool
	 */
	function is_post_type_archive( $post_type = '' ) {
		$active = isset( $GLOBALS['cp_sync_canvas_post_type'] ) ? (string) $GLOBALS['cp_sync_canvas_post_type'] : '';

		if ( is_array( $post_type ) ) {
			return in_array( $active, $post_type, true );
		}

		if ( '' === $post_type ) {
			return '' !== $active;
		}

		return $active === $post_type;
	}
}

if ( ! function_exists( 'load_template' ) ) {
	/**
	 * @param string $template_file Template path.
	 * @param bool   $load_once     Require once when true.
	 * @return void
	 */
	function load_template( $_template_file, $load_once = true ) {
		global $wp_query;

		if ( isset( $wp_query->query_vars ) && is_array( $wp_query->query_vars ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- WordPress load_template() does this.
			extract( $wp_query->query_vars, EXTR_SKIP );
		}

		if ( $load_once ) {
			require_once $_template_file;
		} else {
			require $_template_file;
		}
	}
}
