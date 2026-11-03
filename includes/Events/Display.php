<?php
/**
 * Front-end events for sites that do not run The Events Calendar.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

require_once dirname( __DIR__ ) . '/Templates.php';

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
		add_filter( 'cp_sync_global_settings', array( __CLASS__, 'fill_display_default' ) );
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
	 * Stored Sync Events value for the active ChMS, or null when unset.
	 *
	 * Null is not the same as the toggle default. Callers that need the
	 * default use PostTypes::sync_is_on().
	 *
	 * @return mixed
	 */
	public static function stored_sync_events() {
		if ( ! function_exists( 'get_option' ) ) {
			return null;
		}

		$global = get_option( 'cp_sync_settings', array() );
		$chms   = 'pco';

		if ( is_array( $global ) && ! empty( $global['chms'] ) && is_string( $global['chms'] ) ) {
			$chms = $global['chms'];
		}

		$key      = 'ccb' === $chms ? 'cp_sync_ccb_settings' : 'cp_sync_pco_settings';
		$settings = get_option( $key, array() );

		if ( ! is_array( $settings ) || empty( $settings['connect'] ) || ! is_array( $settings['connect'] ) ) {
			return null;
		}

		if ( ! array_key_exists( 'sync_events', $settings['connect'] ) ) {
			return null;
		}

		return $settings['connect']['sync_events'];
	}

	/**
	 * Whether the display toggle is on, ignoring The Events Calendar.
	 *
	 * The settings screen uses this to enable Sync Events and the Events tab
	 * while The Events Calendar is inactive. Registration still requires both
	 * toggles, via is_enabled().
	 *
	 * @return bool
	 */
	public static function display_toggle_on() {
		if ( self::tec_is_active() ) {
			return false;
		}

		return PostTypes::display_is_on( self::stored_setting(), self::stored_sync_events() );
	}

	/**
	 * Whether CP Sync should register its event post types and archives.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return PostTypes::should_register( self::tec_is_active(), self::stored_setting(), self::stored_sync_events() );
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
	 * Put the upgrade default on the settings payload when nothing is stored.
	 *
	 * @param mixed $settings Global settings.
	 * @return array
	 */
	public static function fill_display_default( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( ! array_key_exists( self::SETTING, $settings ) ) {
			$settings[ self::SETTING ] = PostTypes::display_is_on( null, self::stored_sync_events() );
		}

		return $settings;
	}

	/**
	 * Register types, the shortcode, and permalinks.
	 *
	 * Turning the feature off flushes rewrite rules once so a Page that
	 * already used /events/ can match again.
	 *
	 * @return void
	 */
	public static function on_init() {
		if ( self::is_enabled() ) {
			PostTypes::register();
			EventsShortcode::register();
		}

		self::maybe_flush_rewrites( self::is_enabled() );
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
		if ( is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() || $query->is_singular() ) {
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
	 * Plugin template for a front-end screen, or '' when these pages are off.
	 *
	 * While The Events Calendar is active this is always '', so neither the
	 * archive nor a single event or series is rendered.
	 *
	 * @param bool   $tec_active      Whether The Events Calendar is active.
	 * @param mixed  $display_setting Stored display value, or null when unset.
	 * @param mixed  $sync_events     Stored sync_events value, or null when unset.
	 * @param string $screen          archive, event, or series.
	 * @return string Template path relative to templates/, without .php.
	 */
	public static function template_for( $tec_active, $display_setting, $sync_events, $screen ) {
		if ( ! PostTypes::should_register( $tec_active, $display_setting, $sync_events ) ) {
			return '';
		}

		if ( 'archive' === $screen ) {
			return 'events/archive';
		}

		if ( 'event' === $screen ) {
			return 'events/single';
		}

		if ( 'series' === $screen ) {
			return 'events/series';
		}

		if ( 'series-archive' === $screen ) {
			return 'events/series-archive';
		}

		return '';
	}

	/**
	 * Swap in the plugin templates, which a theme can override from cp-sync/.
	 *
	 * @param string $template Current template path.
	 * @return string
	 */
	public static function template_include( $template ) {
		$screen = '';

		if ( is_post_type_archive( PostTypes::EVENT ) ) {
			$screen = 'archive';
		} elseif ( is_post_type_archive( PostTypes::SERIES ) ) {
			$screen = 'series-archive';
		} elseif ( is_singular( PostTypes::EVENT ) ) {
			$screen = 'event';
		} elseif ( is_singular( PostTypes::SERIES ) ) {
			$screen = 'series';
		}

		$relative = self::template_for( self::tec_is_active(), self::stored_setting(), self::stored_sync_events(), $screen );

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

		$load = is_post_type_archive( array( PostTypes::EVENT, PostTypes::SERIES ) ) || is_singular( array( PostTypes::EVENT, PostTypes::SERIES ) );

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
	 * Flush permalinks once when event archives turn on or off.
	 *
	 * @param bool $enabled Whether the post types were registered this request.
	 * @return void
	 */
	protected static function maybe_flush_rewrites( $enabled ) {
		// on|3 includes the series archive slug. Sites that stored on|2 flush once.
		$version = $enabled ? 'on|3|' . PostTypes::event_slug() . '|' . PostTypes::series_slug() : 'off';

		if ( get_option( self::REWRITE_OPTION ) === $version ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, $version, false );
	}
}
