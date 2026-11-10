<?php
/**
 * Groups Lite: the cp_group post type when CP Groups is not active.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Groups;

require_once dirname( __DIR__ ) . '/Templates.php';

/**
 * Registers the CP Groups post type and taxonomies for sites that do not run CP Groups.
 *
 * CP Groups uses the same slugs, so activating it takes over these posts with no migration.
 * Registration runs at a late `init` and only when `cp_groups()` is absent, the post
 * type is not already registered, and Groups sync is turned on for the active ChMS.
 * An events-only site does not gain /groups/.
 * Rewrite rules are flushed once when Lite turns on, turns off, or hands the posts
 * back to CP Groups.
 *
 * @since 1.2.0
 */
class Lite {

	/**
	 * Option that remembers which rewrite owner last flushed.
	 *
	 * Values: `lite`, `cp-groups`, `off`.
	 *
	 * @var string
	 */
	const REWRITE_STATE_OPTION = 'cp_sync_groups_rewrite_state';

	/**
	 * Stored plugin data version. 1.1.0 did not write this option.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'cp_sync_db_version';

	/**
	 * Returned by a ChMS settings read that happens inside another ChMS settings read.
	 *
	 * This is not "unsaved". Callers must not pass it to sync_setting_is_enabled().
	 *
	 * @var string
	 */
	const SETTINGS_READ_IN_PROGRESS = '__cp_sync_settings_read_in_progress__';

	/**
	 * Release that first shows groups and events without their companion plugins.
	 *
	 * @var string
	 */
	const GROUPS_OPT_IN_VERSION = '1.2.0';

	/**
	 * @var Lite
	 */
	protected static $instance;

	/**
	 * Whether this request registered the post type.
	 *
	 * @var bool
	 */
	protected static $registered = false;

	/**
	 * True while reading a ChMS settings option.
	 *
	 * Those options decrypt through a filter that builds the settings schema.
	 * This guard stops a read made from inside that filter from calling itself.
	 *
	 * @var bool
	 */
	protected static $reading_chms_settings = false;

	/**
	 * Last completed ChMS settings read, keyed by option name.
	 *
	 * @var array<string,mixed>
	 */
	protected static $chms_settings_cache = [];

	/**
	 * @return Lite
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof self ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	protected function __construct() {
		// After ChMS settings filters load ( init 5 ), before event types register ( init 10 ).
		add_action( 'init', [ __CLASS__, 'maybe_upgrade' ], 6 );
		add_action( 'init', [ $this, 'on_init' ], 99 );
		add_filter( 'template_include', [ $this, 'template_include' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		// After CP Groups' pre_get_posts ( priority 10 ), which replaces posts_per_page.
		add_action( 'pre_get_posts', [ Display::class, 'protect_shortcode_count' ], 20 );
	}

	/**
	 * Whether this request registered cp_group.
	 *
	 * @return bool
	 */
	public static function is_registered() {
		return self::$registered;
	}

	/**
	 * Whether Lite should register cp_group and the /groups/ archive.
	 *
	 * The active ChMS's Sync Groups setting decides. A site that syncs events only
	 * does not get the post type.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public static function should_register() {
		return self::groups_sync_is_on();
	}

	/**
	 * Whether Groups sync is turned on for the active ChMS.
	 *
	 * Uses ChMS::sync_setting_is_enabled(), the same check as the pull, so the pull
	 * and registration agree. An unsaved toggle follows sync_toggle_default().
	 *
	 * A read that lands inside another ChMS settings read is not "unsaved".
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public static function groups_sync_is_on() {
		$stored = self::stored_sync_groups();

		if ( self::SETTINGS_READ_IN_PROGRESS === $stored ) {
			return false;
		}

		return \CP_Sync\ChMS\ChMS::sync_setting_is_enabled( 'groups', $stored );
	}

	/**
	 * Saved Sync Groups value, or null when this install has not stored one.
	 *
	 * @since 1.2.0
	 * @return mixed|null
	 */
	public static function stored_sync_groups() {
		$chms_id  = self::active_chms_id();
		$settings = self::read_chms_settings( 'cp_sync_' . $chms_id . '_settings' );

		if ( self::SETTINGS_READ_IN_PROGRESS === $settings ) {
			return self::SETTINGS_READ_IN_PROGRESS;
		}

		if ( ! is_array( $settings ) || ! isset( $settings['connect'] ) || ! is_array( $settings['connect'] ) ) {
			return null;
		}

		if ( ! array_key_exists( 'sync_groups', $settings['connect'] ) ) {
			return null;
		}

		return $settings['connect']['sync_groups'];
	}

	/**
	 * Whether this site already stored ChMS settings before 1.2.0 first ran.
	 *
	 * Saving global settings (the ChMS picker or the update interval) happens
	 * on a brand-new install and does not count. Only a saved PCO or CCB
	 * settings option means the site was already set up.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public static function is_existing_install() {
		foreach ( [ 'pco', 'ccb' ] as $chms_id ) {
			$settings = self::read_chms_settings( 'cp_sync_' . $chms_id . '_settings' );

			if ( is_array( $settings ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Read a ChMS settings option without re-entering its decrypt filter.
	 *
	 * @since 1.2.0
	 * @param string $option Option name.
	 * @return mixed|null Null when called re-entrantly or the option is missing.
	 */
	protected static function read_chms_settings( $option ) {
		if ( self::$reading_chms_settings ) {
			if ( array_key_exists( $option, self::$chms_settings_cache ) ) {
				return self::$chms_settings_cache[ $option ];
			}

			return self::SETTINGS_READ_IN_PROGRESS;
		}

		self::$reading_chms_settings = true;
		$value = get_option( $option, null );
		self::$reading_chms_settings = false;

		if ( self::SETTINGS_READ_IN_PROGRESS !== $value ) {
			self::$chms_settings_cache[ $option ] = $value;
		}

		return $value;
	}

	/**
	 * One-time upgrade from before 1.2.0.
	 *
	 * An update must not start publishing /groups/ or /events/. See
	 * save_sync_state_from_1_1(). Fresh installs are only marked current, and their
	 * toggles follow ChMS::sync_toggle_default().
	 *
	 * @since 1.2.0
	 * @param bool|null $cp_groups_active True when cp_groups() exists. Anything
	 *                                    other than a bool ( WordPress passes ''
	 *                                    to init callbacks ) detects it.
	 * @param bool|null $tec_active       True when The Events Calendar is active.
	 *                                    Anything other than a bool detects it.
	 * @return void
	 */
	public static function maybe_upgrade( $cp_groups_active = null, $tec_active = null ) {
		$stored = get_option( self::DB_VERSION_OPTION, '' );

		if ( is_string( $stored ) && '' !== $stored && version_compare( $stored, self::GROUPS_OPT_IN_VERSION, '>=' ) ) {
			return;
		}

		if ( ! is_bool( $cp_groups_active ) ) {
			$cp_groups_active = function_exists( 'cp_groups' );
		}

		if ( ! is_bool( $tec_active ) ) {
			$tec_active = defined( 'TRIBE_EVENTS_FILE' );
		}

		if ( self::has_pre_1_2_data() ) {
			self::save_sync_state_from_1_1( $cp_groups_active, $tec_active );
		}

		update_option( self::DB_VERSION_OPTION, self::GROUPS_OPT_IN_VERSION, false );
	}

	/**
	 * ChMS settings saved by a copy of CP Sync from before 1.2.0.
	 *
	 * Global settings alone do not count. A new site saves those as soon as an
	 * admin picks a church management system.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public static function has_pre_1_2_data() {
		return self::is_existing_install();
	}

	/**
	 * Save what Sync Groups and Sync Events did on 1.1.0.
	 *
	 * Those feeds only ran with CP Groups or The Events Calendar active. Without the
	 * plugin the toggle is saved off, even when it was saved on, because a saved on
	 * did nothing on 1.1.0 and now creates public pages. With the plugin, a saved
	 * value is kept and an unsaved toggle is saved on, which was the 1.1.0 default.
	 *
	 * @since 1.2.0
	 * @param bool $cp_groups_active Whether cp_groups() exists during the upgrade.
	 * @param bool $tec_active       Whether The Events Calendar is active during the upgrade.
	 * @return void
	 */
	public static function save_sync_state_from_1_1( $cp_groups_active, $tec_active ) {
		$companions = [
			'sync_groups' => $cp_groups_active,
			'sync_events' => $tec_active,
		];

		foreach ( [ 'pco', 'ccb' ] as $chms_id ) {
			$option   = 'cp_sync_' . $chms_id . '_settings';
			$settings = self::read_chms_settings( $option );

			if ( ! is_array( $settings ) ) {
				continue;
			}

			if ( ! isset( $settings['connect'] ) || ! is_array( $settings['connect'] ) ) {
				$settings['connect'] = [];
			}

			$changed = false;

			foreach ( $companions as $key => $companion_active ) {
				if ( $companion_active && array_key_exists( $key, $settings['connect'] ) ) {
					continue;
				}

				if ( array_key_exists( $key, $settings['connect'] ) && $settings['connect'][ $key ] === $companion_active ) {
					continue;
				}

				$settings['connect'][ $key ] = $companion_active;
				$changed                     = true;
			}

			if ( $changed ) {
				update_option( $option, $settings );
				self::$chms_settings_cache[ $option ] = $settings;
			}
		}
	}

	/**
	 * Active ChMS id, used to find that system's settings option.
	 *
	 * @since 1.2.0
	 * @return string
	 */
	public static function active_chms_id() {
		$global  = get_option( 'cp_sync_settings', [] );
		$chms_id = 'pco';

		if ( is_array( $global ) && isset( $global['chms'] ) && is_string( $global['chms'] ) && preg_match( '/^[a-z0-9_-]+$/', $global['chms'] ) ) {
			$chms_id = $global['chms'];
		}

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'cp_sync_active_chms', $chms_id );

			if ( is_string( $filtered ) && preg_match( '/^[a-z0-9_-]+$/', $filtered ) ) {
				$chms_id = $filtered;
			}
		}

		return $chms_id;
	}

	/**
	 * Help text for the Connect tab's Sync Groups toggle.
	 *
	 * @since 1.2.0
	 * @return string
	 */
	public static function sync_groups_help() {
		if ( function_exists( 'cp_groups' ) ) {
			return __( 'When enabled, groups are synced from your ChMS to CP Groups.', 'cp-sync' );
		}

		return __( 'When enabled, groups are synced from your ChMS and shown on your site at /groups/. This list replaces any page already using that address.', 'cp-sync' );
	}

	/**
	 * Late init: register Lite, or record that CP Groups owns the posts.
	 *
	 * @since 1.2.0
	 * @param bool|null $cp_groups_active True when cp_groups() exists. Anything
	 *                                    other than a bool ( WordPress passes ''
	 *                                    to init callbacks ) detects it. Tests pass a bool.
	 * @return void
	 */
	public function on_init( $cp_groups_active = null ) {
		if ( ! is_bool( $cp_groups_active ) ) {
			$cp_groups_active = function_exists( 'cp_groups' );
		}

		add_shortcode( 'cp-sync-groups', [ Display::class, 'shortcode' ] );

		$state = 'off';

		if ( $cp_groups_active ) {
			$state = 'cp-groups';
		} elseif ( post_type_exists( 'cp_group' ) ) {
			// Something else already registered the type. Leave its rewrite rules alone.
			GroupMeta::backfill();
			return;
		} elseif ( self::should_register() && $this->register_objects() ) {
			self::$registered = true;
			$state            = 'lite';
		}

		$this->flush_rewrites_on_change( $state );
		GroupMeta::backfill();
	}

	/**
	 * Register cp_group and the three CP Groups taxonomies.
	 *
	 * @since 1.2.0
	 * @return bool Whether registration ran.
	 */
	public function register_objects() {
		$post_type = new GroupPostType();
		$post_type->register_post_type();

		foreach ( self::taxonomies() as $taxonomy ) {
			$object = new GroupTaxonomy(
				$taxonomy['taxonomy'],
				$taxonomy['single'],
				$taxonomy['plural'],
				$taxonomy['slug']
			);
			$object->register_taxonomy();
		}

		return true;
	}

	/**
	 * Taxonomy definitions matching CP Groups defaults.
	 *
	 * The life-stage rewrite slug keeps the space CP Groups registers
	 * ( `strtolower( 'Life Stages' )` ). Terms are stored on the taxonomy name,
	 * so the rewrite slug only has to match for the public URL.
	 *
	 * @since 1.2.0
	 * @return array[]
	 */
	public static function taxonomies() {
		return [
			[
				'taxonomy' => 'cp_group_type',
				'single'   => __( 'Type', 'cp-sync' ),
				'plural'   => __( 'Types', 'cp-sync' ),
				'slug'     => 'types',
			],
			[
				'taxonomy' => 'cp_group_category',
				'single'   => __( 'Category', 'cp-sync' ),
				'plural'   => __( 'Categories', 'cp-sync' ),
				'slug'     => 'categories',
			],
			[
				'taxonomy' => 'cp_group_life_stage',
				'single'   => __( 'Life Stage', 'cp-sync' ),
				'plural'   => __( 'Life Stages', 'cp-sync' ),
				'slug'     => 'life stages',
			],
		];
	}

	/**
	 * Flush permalinks once when the owner of /groups/ changes.
	 *
	 * @since 1.2.0
	 * @param string $state lite|cp-groups|off.
	 * @return void
	 */
	public function flush_rewrites_on_change( $state ) {
		if ( ! is_string( $state ) || '' === $state ) {
			return;
		}

		$stored = get_option( self::REWRITE_STATE_OPTION, '' );

		if ( ! is_string( $stored ) ) {
			$stored = '';
		}

		if ( $stored === $state ) {
			return;
		}

		// Flush when Lite starts showing groups, stops, or hands them to CP Groups.
		// Recording an existing CP Groups site for the first time does not flush.
		// The flush waits until wp_loaded so post types registered later on init
		// are included. The option is recorded now so this request does not queue twice.
		if ( 'lite' === $state || 'lite' === $stored ) {
			$this->queue_rewrite_flush();
		}

		update_option( self::REWRITE_STATE_OPTION, $state );
	}

	/**
	 * Whether a permalink flush is waiting for wp_loaded.
	 *
	 * @var bool
	 */
	protected $rewrite_flush_pending = false;

	/**
	 * Flush permalinks after init, once other plugins have registered.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	protected function queue_rewrite_flush() {
		if ( $this->rewrite_flush_pending ) {
			return;
		}

		$this->rewrite_flush_pending = true;

		if ( function_exists( 'did_action' ) && did_action( 'wp_loaded' ) ) {
			$this->run_rewrite_flush();
			return;
		}

		if ( function_exists( 'add_action' ) ) {
			add_action( 'wp_loaded', array( $this, 'run_rewrite_flush' ) );
			return;
		}

		$this->run_rewrite_flush();
	}

	/**
	 * Run a flush that queue_rewrite_flush() deferred.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function run_rewrite_flush() {
		if ( ! $this->rewrite_flush_pending ) {
			return;
		}

		$this->rewrite_flush_pending = false;
		flush_rewrite_rules( false );
	}

	/**
	 * Swap in the groups archive or single template.
	 *
	 * Classic themes receive the PHP template. Block themes keep their canvas
	 * and get the same markup as the template content, including header and
	 * footer template parts. A file in the theme's cp-sync/ folder wins.
	 *
	 * @since 1.2.0
	 * @param string $template Current template path.
	 * @return string
	 */
	public function template_include( $template ) {
		if ( ! self::is_registered() ) {
			return $template;
		}

		if ( is_singular( 'cp_group' ) ) {
			$slug = 'groups/single';
		} elseif ( is_post_type_archive( 'cp_group' ) || self::is_group_term_archive() ) {
			$slug = 'groups/archive';
		} else {
			return $template;
		}

		$file = \CP_Sync\get_template_hierarchy( $slug );

		if ( ! $file ) {
			return $template;
		}

		$canvas = \CP_Sync\render_for_block_canvas( $file, $template );

		return '' !== $canvas ? $canvas : $file;
	}

	/**
	 * Whether this is the archive for one group type, category, or life stage.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public static function is_group_term_archive() {
		return function_exists( 'is_tax' ) && is_tax( array_column( self::taxonomies(), 'taxonomy' ) );
	}

	/**
	 * Front-end stylesheet for the archive, the single group, and the shortcode.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function enqueue_styles() {
		if ( is_admin() ) {
			return;
		}

		$load = false;

		if ( self::is_registered() && ( is_post_type_archive( 'cp_group' ) || is_singular( 'cp_group' ) || self::is_group_term_archive() ) ) {
			$load = true;
		}

		if ( is_singular() ) {
			$post = get_post();

			if ( $post instanceof \WP_Post && has_shortcode( $post->post_content, 'cp-sync-groups' ) ) {
				$load = true;
			}
		}

		if ( ! $load || ! defined( 'CP_SYNC_PLUGIN_DIR' ) || ! defined( 'CP_SYNC_PLUGIN_URL' ) ) {
			return;
		}

		cp_sync()->enqueue_asset( 'groups-display', [], null, true );
		wp_enqueue_style( 'material-icons' );

		$script = CP_SYNC_PLUGIN_DIR . 'assets/js/groups-display.js';

		if ( file_exists( $script ) ) {
			wp_enqueue_script(
				'cp-sync-groups-display',
				CP_SYNC_PLUGIN_URL . 'assets/js/groups-display.js',
				[],
				(string) filemtime( $script ),
				true
			);
		}
	}
}
