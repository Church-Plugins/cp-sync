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
 * type is not already registered, Groups sync is turned on for the active ChMS, and
 * "Display groups with CP Sync" is on. An events-only site does not gain /groups/.
 * Rewrite rules are flushed once when Lite turns on, turns off, or hands the posts
 * back to CP Groups.
 *
 * @since 1.2.0
 */
class Lite {

	/**
	 * Global settings key for the display toggle. Must match the Advanced screen schema.
	 *
	 * @var string
	 */
	const DISPLAY_SETTING = 'displayGroupsWithSync';

	/**
	 * Option that remembers which rewrite owner last flushed.
	 *
	 * Values: `lite`, `cp-groups`, `off`.
	 *
	 * @var string
	 */
	const REWRITE_STATE_OPTION = 'cp_sync_groups_rewrite_state';

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
	 * Those options decrypt through a filter that rebuilds the settings schema,
	 * which asks whether Groups sync is on. Without this guard the read calls
	 * itself.
	 *
	 * @var bool
	 */
	protected static $reading_chms_settings = false;

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
		add_action( 'init', [ $this, 'on_init' ], 99 );
		add_filter( 'template_include', [ $this, 'template_include' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_filter( 'cp_sync_global_settings', [ $this, 'expose_display_default' ] );
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
	 * Whether group sync has somewhere to write.
	 *
	 * CP Groups wins when it is active. Otherwise the display toggle decides.
	 * The archive is registered only when Groups sync is also on; see should_register().
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public static function target_available() {
		if ( function_exists( 'cp_groups' ) ) {
			return true;
		}

		return self::is_display_enabled();
	}

	/**
	 * Whether Lite should register cp_group and the /groups/ archive.
	 *
	 * Both the display toggle and the active ChMS's Sync Groups setting must be on.
	 * A site that syncs events only does not get the post type.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public static function should_register() {
		return self::is_display_enabled() && self::groups_sync_is_on();
	}

	/**
	 * Whether "Display groups with CP Sync" is on.
	 *
	 * A saved value wins. When the key has never been saved, a new install defaults
	 * on. An existing install whose Groups sync is off defaults off, so an upgrade
	 * does not start showing groups.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public static function is_display_enabled() {
		$settings = get_option( 'cp_sync_settings', [] );

		if ( is_array( $settings ) && array_key_exists( self::DISPLAY_SETTING, $settings ) ) {
			return self::is_truthy( $settings[ self::DISPLAY_SETTING ] );
		}

		return self::display_default();
	}

	/**
	 * Default for a missing display toggle.
	 *
	 * Events-only sites saved Sync Groups off. The display toggle stays off for
	 * them so an upgrade does not add /groups/. An unsaved Sync Groups value is
	 * on, the same as the sync engine, so the display toggle defaults on too.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public static function display_default() {
		if ( self::is_existing_install() && ! self::groups_sync_is_on() ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether Groups sync is turned on for the active ChMS.
	 *
	 * Uses ChMS::sync_setting_is_enabled(), the same check as the pull. An
	 * unsaved toggle is on for groups. Registration must follow that, or the
	 * sync writes cp_group posts while /groups/ is not a registered archive.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public static function groups_sync_is_on() {
		return \CP_Sync\ChMS\ChMS::sync_setting_is_enabled( 'groups', self::stored_sync_groups() );
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

		if ( ! is_array( $settings ) || ! isset( $settings['connect'] ) || ! is_array( $settings['connect'] ) ) {
			return null;
		}

		if ( ! array_key_exists( 'sync_groups', $settings['connect'] ) ) {
			return null;
		}

		return $settings['connect']['sync_groups'];
	}

	/**
	 * Whether this site already stored ChMS settings before the display toggle.
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
			return null;
		}

		self::$reading_chms_settings = true;
		$value = get_option( $option, null );
		self::$reading_chms_settings = false;

		return $value;
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

		return __( 'When enabled, groups are synced from your ChMS and shown on your site.', 'cp-sync' );
	}

	/**
	 * Late init: register Lite, or record that CP Groups owns the posts.
	 *
	 * @since 1.2.0
	 * @param bool|null $cp_groups_active Null checks cp_groups(). Tests pass a bool.
	 * @return void
	 */
	public function on_init( $cp_groups_active = null ) {
		if ( null === $cp_groups_active ) {
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
		if ( 'lite' === $state || 'lite' === $stored ) {
			flush_rewrite_rules( false );
		}

		update_option( self::REWRITE_STATE_OPTION, $state );
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
		} elseif ( is_post_type_archive( 'cp_group' ) ) {
			$slug = 'groups/archive';
		} else {
			return $template;
		}

		$file = \CP_Sync\get_template_hierarchy( $slug );

		if ( ! $file ) {
			return $template;
		}

		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			global $_wp_current_template_content;

			ob_start();
			include $file;
			$_wp_current_template_content = ob_get_clean();

			return $template;
		}

		return $file;
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

		if ( self::is_registered() && ( is_post_type_archive( 'cp_group' ) || is_singular( 'cp_group' ) ) ) {
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

		$path = CP_SYNC_PLUGIN_DIR . 'assets/css/groups.css';

		wp_enqueue_style(
			'cp-sync-groups',
			CP_SYNC_PLUGIN_URL . 'assets/css/groups.css',
			[],
			file_exists( $path ) ? (string) filemtime( $path ) : '1.2.0'
		);
	}

	/**
	 * Put the computed display default into the settings screen when nothing is stored.
	 *
	 * The Advanced toggle then shows off on an events-only upgrade instead of the
	 * schema's new-install default.
	 *
	 * @since 1.2.0
	 * @param mixed $settings Global settings passed to the settings app.
	 * @return mixed
	 */
	public function expose_display_default( $settings ) {
		if ( ! is_array( $settings ) || array_key_exists( self::DISPLAY_SETTING, $settings ) ) {
			return $settings;
		}

		$settings[ self::DISPLAY_SETTING ] = self::display_default();

		return $settings;
	}

	/**
	 * Interpret a saved toggle value.
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	protected static function is_truthy( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return 0 !== (int) $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), [ '1', 'true', 'yes', 'on' ], true );
		}

		return false;
	}
}
