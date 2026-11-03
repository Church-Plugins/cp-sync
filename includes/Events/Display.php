<?php
/**
 * Front-end events for sites that do not run The Events Calendar.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Boot, settings, import, and template routing.
 *
 * @since 1.2.0
 */
class Display {

	const SETTING = 'displayEvents';

	const REWRITE_OPTION = 'cp_sync_events_rewrite';

	/**
	 * Register hooks. Safe to call when The Events Calendar is active.
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( 'init', array( __CLASS__, 'on_init' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'upcoming_archive' ) );
		add_action( 'cp_sync_reset_content', array( __CLASS__, 'delete_imported_content' ) );
	}

	/**
	 * Whether The Events Calendar is the active event destination.
	 *
	 * @return bool
	 */
	public static function tec_is_active() {
		return defined( 'TRIBE_EVENTS_FILE' );
	}

	/**
	 * Stored display value, or null when the site has never saved it.
	 *
	 * @return mixed
	 */
	public static function stored_setting() {
		if ( ! function_exists( 'get_option' ) ) {
			return null;
		}

		$settings = get_option( 'cp_sync_settings', array() );

		if ( ! is_array( $settings ) || ! array_key_exists( self::SETTING, $settings ) ) {
			return null;
		}

		return $settings[ self::SETTING ];
	}

	/**
	 * Whether CP Sync should show its own events.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return PostTypes::should_register( self::tec_is_active(), self::stored_setting() );
	}

	/**
	 * Whether this pull should import into CP Sync event posts.
	 *
	 * @param string $integration_type Integration type.
	 * @return bool
	 */
	public static function should_import( $integration_type ) {
		return 'events' === $integration_type && self::is_enabled();
	}

	/**
	 * Register types, the shortcode, and permalinks.
	 *
	 * @return void
	 */
	public static function on_init() {
		if ( ! self::is_enabled() ) {
			return;
		}

		PostTypes::register();
		EventsShortcode::register();
		self::maybe_flush_rewrites();
	}

	/**
	 * Import neutral records captured during the events pull.
	 *
	 * @param array $neutral_events Neutral event records.
	 * @return true
	 */
	public static function import_neutral( array $neutral_events ) {
		$timezone = EventTime::wordpress_timezone_name();
		$summary  = EventSync::sync( $neutral_events, new WpEventStore(), gmdate( 'Y-m-d H:i:s' ), $timezone );

		if ( function_exists( 'cp_sync' ) ) {
			$plugin = cp_sync();

			if ( is_object( $plugin ) && isset( $plugin->logging ) ) {
				$plugin->logging->log(
					sprintf(
						'Event display sync: %d dates, %d series, %d dates trashed, %d series trashed%s',
						(int) $summary['events'],
						(int) $summary['series'],
						(int) $summary['trashed_events'],
						(int) $summary['trashed_series'],
						! empty( $summary['skipped'] ) ? ' (skipped)' : ''
					)
				);
			}
		}

		return true;
	}

	/**
	 * Upcoming dates, soonest first, on the event archive only.
	 *
	 * @param \WP_Query $query Main query.
	 * @return void
	 */
	public static function upcoming_archive( $query ) {
		if ( is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}

		if ( ! $query->is_post_type_archive( PostTypes::EVENT ) ) {
			return;
		}

		$query->set( 'meta_key', Meta::START_UTC );
		$query->set( 'orderby', 'meta_value' );
		$query->set( 'order', 'ASC' );
		$query->set(
			'meta_query',
			array(
				array(
					'key'     => Meta::END_UTC,
					'value'   => gmdate( 'Y-m-d H:i:s' ),
					'compare' => '>=',
					'type'    => 'CHAR',
				),
			)
		);
	}

	/**
	 * Swap in the plugin templates, which a theme can override from cp-sync/.
	 *
	 * @param string $template Current template path.
	 * @return string
	 */
	public static function template_include( $template ) {
		if ( ! self::is_enabled() ) {
			return $template;
		}

		$relative = '';

		if ( is_post_type_archive( PostTypes::EVENT ) ) {
			$relative = 'events/archive';
		} elseif ( is_singular( PostTypes::EVENT ) ) {
			$relative = 'events/single';
		} elseif ( is_singular( PostTypes::SERIES ) ) {
			$relative = 'events/series';
		}

		if ( '' === $relative ) {
			return $template;
		}

		$found = \CP_Sync\get_template_hierarchy( $relative );

		return ( is_string( $found ) && '' !== $found && file_exists( $found ) ) ? $found : $template;
	}

	/**
	 * Styles for the event screens and the shortcode.
	 *
	 * @return void
	 */
	public static function enqueue_styles() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$load = is_post_type_archive( PostTypes::EVENT ) || is_singular( array( PostTypes::EVENT, PostTypes::SERIES ) );

		if ( ! $load && is_singular() ) {
			$post = get_post();

			if ( $post instanceof \WP_Post && has_shortcode( $post->post_content, EventsShortcode::TAG ) ) {
				$load = true;
			}
		}

		if ( ! $load ) {
			return;
		}

		wp_enqueue_style(
			'cp-sync-events',
			CP_SYNC_PLUGIN_URL . 'assets/css/events.css',
			array(),
			defined( 'CP_SYNC_PLUGIN_VERSION' ) ? CP_SYNC_PLUGIN_VERSION : '1.2.0'
		);
	}

	/**
	 * Delete imported event posts during a content reset.
	 *
	 * @return void
	 */
	public static function delete_imported_content() {
		foreach ( array( PostTypes::EVENT, PostTypes::SERIES ) as $post_type ) {
			$ids = get_posts(
				array(
					'post_type'        => $post_type,
					'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => true,
					'meta_key'         => Meta::CHMS_ID,
				)
			);

			foreach ( $ids as $post_id ) {
				wp_delete_post( (int) $post_id, true );
			}
		}
	}

	/**
	 * Flush permalinks once per slug pair.
	 *
	 * @return void
	 */
	protected static function maybe_flush_rewrites() {
		$version = '1|' . PostTypes::event_slug() . '|' . PostTypes::series_slug();

		if ( get_option( self::REWRITE_OPTION ) === $version ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, $version, false );
	}
}
