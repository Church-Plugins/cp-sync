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
 * Registration runs at a late `init` and only when `cp_groups()` is absent and the post
 * type is not already registered. Rewrite rules are flushed once when Lite turns on,
 * turns off, or hands the posts back to CP Groups.
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
	 * The toggle is on when it has never been saved and CP Groups is absent.
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
	 * Whether "Display groups with CP Sync" is on.
	 *
	 * Missing setting means on. CP Groups being active does not change the stored
	 * value; Lite still refuses to register while `cp_groups()` exists.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public static function is_display_enabled() {
		$settings = get_option( 'cp_sync_settings', [] );

		if ( ! is_array( $settings ) || ! array_key_exists( self::DISPLAY_SETTING, $settings ) ) {
			return true;
		}

		return self::is_truthy( $settings[ self::DISPLAY_SETTING ] );
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
			return;
		} elseif ( self::is_display_enabled() && $this->register_objects() ) {
			self::$registered = true;
			$state            = 'lite';
		}

		$this->flush_rewrites_on_change( $state );
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
