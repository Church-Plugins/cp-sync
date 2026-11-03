<?php
/**
 * Event and series post types for sites that do not use The Events Calendar.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Registration rules and post-type arguments.
 *
 * The Events Calendar keeps its own event posts. These types are registered
 * only when that plugin is absent, event sync is on, and the display setting
 * is on. An upgrade that has never saved the display setting leaves it off
 * unless event sync was already turned on, so an existing /events/ page is
 * left alone.
 *
 * @since 1.2.0
 */
class PostTypes {

	const EVENT = 'cp_event';

	const SERIES = 'cp_event_series';

	const CATEGORY = 'cp_event_category';

	/**
	 * Whether the CP Sync event types and their public archives should be registered.
	 *
	 * Requires The Events Calendar to be absent, event sync to be on, and the
	 * display setting to be on. A missing display setting is on only when event
	 * sync was already stored as on.
	 *
	 * @param bool  $tec_active      Whether The Events Calendar is active.
	 * @param mixed $display_setting Stored display value, or null when unset.
	 * @param mixed $sync_events     Stored sync_events value, or null when unset.
	 *                               Null means the toggle default (on) for the
	 *                               sync check, and "not already on" for the
	 *                               display default.
	 * @return bool
	 */
	public static function should_register( $tec_active, $display_setting, $sync_events = null ) {
		if ( $tec_active ) {
			return false;
		}

		return self::display_is_on( $display_setting, $sync_events ) && self::sync_is_on( $sync_events );
	}

	/**
	 * Whether the display toggle is on.
	 *
	 * A missing value is off unless $sync_events is an explicit on. That keeps
	 * an upgrade from claiming /events/ when events were not already syncing.
	 *
	 * @param mixed $display_setting Stored display value, or null when unset.
	 * @param mixed $sync_events     Stored sync_events value, or null when unset.
	 * @return bool
	 */
	public static function display_is_on( $display_setting, $sync_events = null ) {
		if ( null === $display_setting ) {
			return null !== $sync_events && self::setting_is_on( $sync_events );
		}

		return self::setting_is_on( $display_setting );
	}

	/**
	 * Whether event sync is on.
	 *
	 * A missing value uses the Connect toggle default, which is on.
	 *
	 * @param mixed $sync_events Stored sync_events value, or null when unset.
	 * @return bool
	 */
	public static function sync_is_on( $sync_events ) {
		if ( null === $sync_events ) {
			return true;
		}

		return self::setting_is_on( $sync_events );
	}

	/**
	 * Whether a stored value is an explicit on.
	 *
	 * Null, false, and 0 are off. Only real on values count.
	 *
	 * @param mixed $value Stored setting.
	 * @return bool
	 */
	public static function setting_is_on( $value ) {
		if ( true === $value || 1 === $value || 1.0 === $value ) {
			return true;
		}

		if ( ! is_string( $value ) ) {
			return false;
		}

		return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * A non-empty slug source so a post without a title still has a permalink.
	 *
	 * @param string $title    Post title. Empty uses $fallback.
	 * @param string $unique   Extra token, such as the date or series key.
	 * @param string $fallback Used when the title is empty.
	 * @return string
	 */
	public static function public_slug( $title, $unique = '', $fallback = 'event' ) {
		$title  = trim( (string) $title );
		$unique = trim( (string) $unique );
		$base   = '' !== $title ? $title : $fallback;

		if ( '' !== $unique ) {
			$base .= '-' . $unique;
		}

		return $base;
	}

	/**
	 * Public slug for event permalinks and the archive.
	 *
	 * @return string
	 */
	public static function event_slug() {
		$slug = apply_filters( 'cp_sync_event_rewrite_slug', 'events' );

		return self::sanitize_slug( $slug, 'events' );
	}

	/**
	 * Public slug for series permalinks.
	 *
	 * Defaults to event-series so it does not take /series/ from CP Sermons.
	 * A site without that conflict can filter this to series.
	 *
	 * @return string
	 */
	public static function series_slug() {
		$slug = apply_filters( 'cp_sync_event_series_rewrite_slug', 'event-series' );

		return self::sanitize_slug( $slug, 'event-series' );
	}

	/**
	 * Register both post types and the category taxonomy.
	 *
	 * @return void
	 */
	public static function register() {
		register_post_type( self::EVENT, self::event_args() );
		register_post_type( self::SERIES, self::series_args() );
		register_taxonomy( self::CATEGORY, array( self::EVENT ), self::category_args() );
	}

	/**
	 * Arguments for the date post type.
	 *
	 * @return array
	 */
	public static function event_args() {
		$labels = array(
			'name'               => __( 'Events', 'cp-sync' ),
			'singular_name'      => __( 'Event', 'cp-sync' ),
			'add_new_item'       => __( 'Add New Event', 'cp-sync' ),
			'edit_item'          => __( 'Edit Event', 'cp-sync' ),
			'new_item'           => __( 'New Event', 'cp-sync' ),
			'view_item'          => __( 'View Event', 'cp-sync' ),
			'search_items'       => __( 'Search Events', 'cp-sync' ),
			'not_found'          => __( 'No events found', 'cp-sync' ),
			'not_found_in_trash' => __( 'No events found in Trash', 'cp-sync' ),
			'all_items'          => __( 'All Events', 'cp-sync' ),
			'archives'           => __( 'Events', 'cp-sync' ),
			'menu_name'          => __( 'Events', 'cp-sync' ),
		);

		return array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'has_archive'        => true,
			'rewrite'            => array(
				'slug'       => self::event_slug(),
				'with_front' => false,
			),
			'menu_icon'          => 'dashicons-calendar-alt',
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'taxonomies'         => array( self::CATEGORY ),
		);
	}

	/**
	 * Arguments for the series post type.
	 *
	 * @return array
	 */
	public static function series_args() {
		$labels = array(
			'name'               => __( 'Event Series', 'cp-sync' ),
			'singular_name'      => __( 'Event Series', 'cp-sync' ),
			'add_new_item'       => __( 'Add New Event Series', 'cp-sync' ),
			'edit_item'          => __( 'Edit Event Series', 'cp-sync' ),
			'new_item'           => __( 'New Event Series', 'cp-sync' ),
			'view_item'          => __( 'View Event Series', 'cp-sync' ),
			'search_items'       => __( 'Search Event Series', 'cp-sync' ),
			'not_found'          => __( 'No event series found', 'cp-sync' ),
			'not_found_in_trash' => __( 'No event series found in Trash', 'cp-sync' ),
			'all_items'          => __( 'Event Series', 'cp-sync' ),
			'menu_name'          => __( 'Event Series', 'cp-sync' ),
		);

		return array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=' . self::EVENT,
			'show_in_rest'       => true,
			'has_archive'        => false,
			'rewrite'            => array(
				'slug'       => self::series_slug(),
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
		);
	}

	/**
	 * Arguments for the single event category taxonomy.
	 *
	 * @return array
	 */
	public static function category_args() {
		$labels = array(
			'name'          => __( 'Event Categories', 'cp-sync' ),
			'singular_name' => __( 'Event Category', 'cp-sync' ),
			'search_items'  => __( 'Search Event Categories', 'cp-sync' ),
			'all_items'     => __( 'All Event Categories', 'cp-sync' ),
			'edit_item'     => __( 'Edit Event Category', 'cp-sync' ),
			'update_item'   => __( 'Update Event Category', 'cp-sync' ),
			'add_new_item'  => __( 'Add New Event Category', 'cp-sync' ),
			'new_item_name' => __( 'New Event Category Name', 'cp-sync' ),
			'menu_name'     => __( 'Categories', 'cp-sync' ),
		);

		return array(
			'labels'            => $labels,
			'public'            => true,
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array(
				'slug'       => 'event-category',
				'with_front' => false,
			),
		);
	}

	/**
	 * A rewrite slug WordPress can register.
	 *
	 * @param mixed  $slug     Filter result.
	 * @param string $fallback Used when the filter returns nothing usable.
	 * @return string
	 */
	protected static function sanitize_slug( $slug, $fallback ) {
		$slug = is_string( $slug ) ? $slug : '';
		$slug = function_exists( 'sanitize_title' ) ? sanitize_title( $slug ) : strtolower( $slug );
		$slug = trim( $slug, '/' );

		if ( '' === $slug ) {
			return $fallback;
		}

		return $slug;
	}
}
