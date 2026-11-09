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

	const REWRITE_OPTION = 'cp_sync_events_rewrite';

	/**
	 * Register hooks. Safe to call when The Events Calendar is active.
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( 'init', array( __CLASS__, 'on_init' ) );
		// After other plugins register post types on init. Flushing at
		// priority 10 drops those rules until permalinks are saved again.
		add_action( 'wp_loaded', array( __CLASS__, 'flush_rewrites_after_init' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		// Priority 9 runs after an earlier template swap and before the default
		// template filters. The canvas lookup itself happens inside this callback,
		// ahead of the capture, because that lookup replaces the template content.
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 9 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_download_calendar' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'upcoming_archive' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'series_archive' ), 11 );
		add_action( 'cp_sync_reset_content', array( __CLASS__, 'delete_imported_content' ) );
		add_action( 'rest_api_init', array( CalendarFeed::class, 'register_routes' ) );
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
	 * Stored Sync Events value for the active ChMS, or null when unset.
	 *
	 * Null is not the same as the toggle default. Callers that need the
	 * default use PostTypes::sync_is_on().
	 *
	 * Reads the same option the pull reads, including when a site filters
	 * cp_sync_active_chms.
	 *
	 * @return mixed
	 */
	public static function stored_sync_events() {
		if ( ! function_exists( 'get_option' ) ) {
			return null;
		}

		$chms     = \CP_Sync\Groups\Lite::active_chms_id();
		$settings = get_option( 'cp_sync_' . $chms . '_settings', array() );

		if ( ! is_array( $settings ) || empty( $settings['connect'] ) || ! is_array( $settings['connect'] ) ) {
			return null;
		}

		if ( ! array_key_exists( 'sync_events', $settings['connect'] ) ) {
			return null;
		}

		return $settings['connect']['sync_events'];
	}

	/**
	 * Whether CP Sync should register its event post types and archives.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return PostTypes::should_register( self::tec_is_active(), self::stored_sync_events() );
	}

	/**
	 * Help text for the Connect tab's Sync Events toggle.
	 *
	 * @return string
	 */
	public static function sync_events_help() {
		if ( self::tec_is_active() ) {
			return __( 'When enabled, events are synced from your ChMS to The Events Calendar.', 'cp-sync' );
		}

		return __( 'When enabled, events are synced from your ChMS and shown on your site at /events/ and /event-series/. These lists replace any page already using those addresses.', 'cp-sync' );
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
	}

	/**
	 * Flush permalinks after init, once every plugin has registered.
	 *
	 * @return void
	 */
	public static function flush_rewrites_after_init() {
		self::maybe_flush_rewrites( self::is_enabled() );
	}

	/**
	 * Import neutral records captured during the events pull.
	 *
	 * @param array $neutral_events Neutral event records.
	 * @return true
	 */
	public static function import_neutral( array $neutral_events, $store = null, $now_utc = null ) {
		if ( ! $store instanceof EventStore ) {
			$store = new WpEventStore();
		}

		if ( ! is_string( $now_utc ) || '' === $now_utc ) {
			$now_utc = gmdate( 'Y-m-d H:i:s' );
		}

		$timezone = EventTime::wordpress_timezone_name();
		$summary  = EventSync::sync( $neutral_events, $store, $now_utc, $timezone );

		$removed = ( isset( $summary['removed_source_ids'] ) && is_array( $summary['removed_source_ids'] ) )
			? $summary['removed_source_ids']
			: array();

		VisibilityRemovalNotice::publish( VisibilityRemovalNotice::skipped_ids(), $removed );

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
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return;
		}

		if ( ! is_object( $query ) || ! method_exists( $query, 'is_main_query' ) || ! method_exists( $query, 'is_post_type_archive' ) || ! method_exists( $query, 'set' ) ) {
			return;
		}

		if ( ! $query->is_main_query() || ( method_exists( $query, 'is_singular' ) && $query->is_singular() ) ) {
			return;
		}

		if ( $query->is_post_type_archive( PostTypes::SERIES ) ) {
			return;
		}

		$category = method_exists( $query, 'is_tax' ) && $query->is_tax( PostTypes::CATEGORY );

		if ( ! $category && ! $query->is_post_type_archive( PostTypes::EVENT ) ) {
			return;
		}

		$query->set( 'meta_key', Meta::START_UTC );
		$query->set( 'orderby', 'meta_value' );
		$query->set( 'order', 'ASC' );
		$query->set( 'has_password', false );
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
	 * Published series for /event-series/, including series whose dates are past.
	 *
	 * The event archive limits itself to upcoming dates. This query does not.
	 * A series has no start meta, so a date meta query would drop it.
	 *
	 * @return array
	 */
	public static function series_archive_query_vars() {
		return array(
			'post_type'    => PostTypes::SERIES,
			'post_status'  => 'publish',
			'has_password' => false,
			'meta_key'     => '',
			'meta_query'   => array(),
		);
	}

	/**
	 * Apply the series archive query on the main request.
	 *
	 * @param \WP_Query $query Main query.
	 * @return void
	 */
	public static function series_archive( $query ) {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return;
		}

		if ( ! is_object( $query ) || ! method_exists( $query, 'is_main_query' ) || ! method_exists( $query, 'is_post_type_archive' ) || ! method_exists( $query, 'set' ) ) {
			return;
		}

		if ( ! $query->is_main_query() || ( method_exists( $query, 'is_singular' ) && $query->is_singular() ) ) {
			return;
		}

		if ( ! $query->is_post_type_archive( PostTypes::SERIES ) ) {
			return;
		}

		foreach ( self::series_archive_query_vars() as $key => $value ) {
			$query->set( $key, $value );
		}
	}

	/**
	 * Plugin template for a front-end screen, or '' when these pages are off.
	 *
	 * While The Events Calendar is active this is always '', so neither the
	 * archive nor a single event or series is rendered.
	 *
	 * @param bool   $tec_active  Whether The Events Calendar is active.
	 * @param mixed  $sync_events Stored sync_events value, or null when unset.
	 * @param string $screen      archive, event, or series.
	 * @return string Template path relative to templates/, without .php.
	 */
	public static function template_for( $tec_active, $sync_events, $screen ) {
		if ( ! PostTypes::should_register( $tec_active, $sync_events ) ) {
			return '';
		}

		if ( 'archive' === $screen || 'category' === $screen ) {
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
	 * Classic themes receive the PHP template. Block themes keep their canvas.
	 * The event markup, including header and footer template parts, is rendered
	 * first and stored as the canvas content so wp_head() can print the
	 * scripts and layout styles those parts registered.
	 *
	 * @param string $template Current template path.
	 * @return string
	 */
	public static function template_include( $template ) {
		$screen = '';

		if ( function_exists( 'is_tax' ) && is_tax( PostTypes::CATEGORY ) ) {
			$screen = 'category';
		} elseif ( is_post_type_archive( PostTypes::EVENT ) ) {
			$screen = 'archive';
		} elseif ( is_post_type_archive( PostTypes::SERIES ) ) {
			$screen = 'series-archive';
		} elseif ( is_singular( PostTypes::EVENT ) ) {
			$screen = 'event';
		} elseif ( is_singular( PostTypes::SERIES ) ) {
			$screen = 'series';
		}

		$relative = self::template_for( self::tec_is_active(), self::stored_sync_events(), $screen );

		if ( '' === $relative ) {
			return $template;
		}

		$found = \CP_Sync\get_template_hierarchy( $relative );

		if ( ! is_string( $found ) || '' === $found || ! file_exists( $found ) ) {
			return $template;
		}

		$canvas = self::block_canvas_template( $template );
		if ( '' === $canvas ) {
			return $found;
		}

		global $_wp_current_template_content, $post;

		$previous = ( isset( $post ) && $post instanceof \WP_Post ) ? $post : null;

		ob_start();
		load_template( $found, false );
		$_wp_current_template_content = \CP_Sync\Groups\Display::protect_block_template_html( ob_get_clean() );

		if ( $previous instanceof \WP_Post && function_exists( 'setup_postdata' ) ) {
			$post = $previous;
			setup_postdata( $previous );
		} elseif ( function_exists( 'wp_reset_postdata' ) ) {
			wp_reset_postdata();
		}

		return $canvas;
	}

	/**
	 * Canvas path for the current request.
	 *
	 * A block theme can still hand this filter a PHP template when a more
	 * specific PHP file wins over the block index. Looking up the index canvas
	 * here, before the capture, is what puts the event markup in the canvas.
	 * When that lookup does not return the canvas file, the plugin template is
	 * used directly.
	 *
	 * An incoming path that is already the canvas is kept. Looking it up again
	 * would register the canvas head hooks a second time.
	 *
	 * @param mixed $template Incoming template path.
	 * @return string Canvas path, or an empty string when the canvas is absent.
	 */
	private static function block_canvas_template( $template ) {
		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return '';
		}

		if ( ! function_exists( 'locate_block_template' ) ) {
			return '';
		}

		if ( self::is_template_canvas( $template ) ) {
			return $template;
		}

		$located = locate_block_template( '', 'index', array( 'index' ) );

		return self::is_template_canvas( $located ) ? $located : '';
	}

	/**
	 * Whether a path is the WordPress block template canvas.
	 *
	 * @param mixed $path Template path.
	 * @return bool
	 */
	private static function is_template_canvas( $path ) {
		return is_string( $path )
			&& '' !== $path
			&& file_exists( $path )
			&& 'template-canvas.php' === basename( $path );
	}

	/**
	 * Which view the event list is showing. The calendar is the default.
	 *
	 * @return string calendar or list.
	 */
	public static function current_view() {
		// A read-only display choice. It is compared to a fixed value and never stored.
		$view = isset( $_GET['view'] ) && is_string( $_GET['view'] ) ? $_GET['view'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput

		return 'list' === $view ? 'list' : 'calendar';
	}

	/**
	 * Address of this page in one view.
	 *
	 * @param string $view calendar or list.
	 * @return string
	 */
	public static function view_url( $view ) {
		$url = remove_query_arg( array( 'view', 'paged' ) );

		// The list's page number lives in the path, and the calendar has no pages.
		$url = preg_replace( '#/page/\d+/?(?=\?|$)#', '/', (string) $url );

		return 'list' === $view ? add_query_arg( 'view', 'list', $url ) : $url;
	}

	/**
	 * Whether this request shows the calendar.
	 *
	 * @return bool
	 */
	public static function is_calendar_screen() {
		$screen = is_post_type_archive( PostTypes::EVENT ) || ( function_exists( 'is_tax' ) && is_tax( PostTypes::CATEGORY ) );

		return $screen && 'calendar' === self::current_view();
	}

	/**
	 * Slug of the category this archive is showing, or an empty string.
	 *
	 * @return string
	 */
	public static function current_category() {
		if ( ! function_exists( 'is_tax' ) || ! is_tax( PostTypes::CATEGORY ) ) {
			return '';
		}

		$term = get_queried_object();

		return ( is_object( $term ) && isset( $term->slug ) ) ? (string) $term->slug : '';
	}

	/**
	 * Categories for the filter. Empty when a site has none, which hides the filter.
	 *
	 * @return array<string,string> Slug to name.
	 */
	public static function category_options() {
		if ( ! function_exists( 'get_terms' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => PostTypes::CATEGORY,
				'hide_empty' => true,
			)
		);

		if ( ! is_array( $terms ) ) {
			return array();
		}

		$options = array();

		foreach ( $terms as $term ) {
			if ( is_object( $term ) && isset( $term->slug, $term->name ) ) {
				$options[ (string) $term->slug ] = html_entity_decode( (string) $term->name, ENT_QUOTES, 'UTF-8' );
			}
		}

		return $options;
	}

	/**
	 * Settings for the calendar script.
	 *
	 * `now` is the site's clock, so "today" does not depend on the visitor's
	 * timezone. Dates and times come from the server in the event's own timezone.
	 *
	 * @return array
	 */
	public static function calendar_config() {
		return array(
			'restUrl'  => esc_url_raw( rest_url( CalendarFeed::REST_NAMESPACE . CalendarFeed::ROUTE ) ),
			'now'      => wp_date( 'Y-m-d\TH:i:s' ),
			'firstDay' => (int) get_option( 'start_of_week', 0 ),
			'locale'   => str_replace( '_', '-', (string) get_locale() ),
			'category' => self::current_category(),
			'i18n'     => array(
				'today'     => __( 'Today', 'cp-sync' ),
				'prevMonth' => __( 'Previous month', 'cp-sync' ),
				'nextMonth' => __( 'Next month', 'cp-sync' ),
				/* translators: %d: how many more events are on this day. */
				'more'      => __( '+%d more', 'cp-sync' ),
				'loading'   => __( 'Loading events…', 'cp-sync' ),
				'error'     => __( 'Events could not be loaded. Please try again.', 'cp-sync' ),
				'noMonth'   => __( 'No events scheduled this month.', 'cp-sync' ),
				'noDay'     => __( 'No events on this day.', 'cp-sync' ),
				/* translators: %d: number of events on this day. */
				'dayEvents' => __( '%d events', 'cp-sync' ),
			),
		);
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

		if ( ! $load && function_exists( 'is_tax' ) && is_tax( PostTypes::CATEGORY ) ) {
			$load = true;
		}

		if ( ! $load && is_singular() ) {
			$post = get_post();

			if ( $post instanceof \WP_Post && has_shortcode( $post->post_content, EventsShortcode::TAG ) ) {
				$load = true;
			}
		}

		if ( ! $load ) {
			return;
		}

		cp_sync()->enqueue_asset( 'events-display', array(), null, true );
		wp_enqueue_style( 'material-icons' );

		if ( self::is_calendar_screen() ) {
			cp_sync()->enqueue_asset( 'events-calendar', array(), null, true );
			$calendar = cp_sync()->enqueue_asset( 'events-calendar', array(), null, false, true );

			if ( is_array( $calendar ) ) {
				wp_add_inline_script( $calendar['handle'], 'window.cpSyncCalendar = ' . wp_json_encode( self::calendar_config() ) . ';', 'before' );
			}
		}

		$script = CP_SYNC_PLUGIN_DIR . 'assets/js/events-display.js';

		if ( defined( 'CP_SYNC_PLUGIN_DIR' ) && file_exists( $script ) ) {
			wp_enqueue_script(
				'cp-sync-events-display',
				CP_SYNC_PLUGIN_URL . 'assets/js/events-display.js',
				array(),
				(string) filemtime( $script ),
				true
			);
		}
	}

	/**
	 * Download a calendar file for the public event being viewed.
	 *
	 * The flag only means something on a single event while these pages are on.
	 * Every other URL ignores it and renders as usual. On an event, a bad flag
	 * or a date that cannot be exported is a 404. The file is built from the
	 * local post. No remote calendar is fetched.
	 *
	 * @return void
	 */
	public static function maybe_download_calendar() {
		$flag = CalendarFile::request_flag();

		if ( null === $flag || ! is_singular( PostTypes::EVENT ) || ! self::is_enabled() ) {
			return;
		}

		$post = function_exists( 'get_queried_object' ) ? get_queried_object() : null;

		CalendarFile::emit( CalendarFile::response_for( $post, $flag ) );
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
		$stored  = get_option( self::REWRITE_OPTION );

		if ( $stored === $version ) {
			self::ensure_rewrite_option_autoloads( $version );
			return;
		}

		// The first request after an upgrade on a site that uses The Events
		// Calendar only records "off". Groups Lite does the same when it is
		// not the one taking over /groups/.
		$first_off = ! $enabled && ( false === $stored || null === $stored || '' === $stored );

		if ( ! $first_off && function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}

		if ( function_exists( 'update_option' ) ) {
			update_option( self::REWRITE_OPTION, $version, true );
		}
	}

	/**
	 * Autoload the rewrite marker so the front end does not query it every request.
	 *
	 * @param string $version Stored marker.
	 * @return void
	 */
	protected static function ensure_rewrite_option_autoloads( $version ) {
		unset( $version );

		if ( ! function_exists( 'wp_load_alloptions' ) ) {
			return;
		}

		$all = wp_load_alloptions();

		if ( ! is_array( $all ) || array_key_exists( self::REWRITE_OPTION, $all ) ) {
			return;
		}

		// One update of the autoload flag. Deleting the row first lets another
		// request treat the marker as missing and flush permalinks.
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( self::REWRITE_OPTION, true );
			return;
		}

		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'update' ) || empty( $wpdb->options ) ) {
			return;
		}

		$wpdb->update(
			$wpdb->options,
			array( 'autoload' => 'yes' ),
			array( 'option_name' => self::REWRITE_OPTION )
		);

		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( 'alloptions', 'options' );
		}
	}
}
