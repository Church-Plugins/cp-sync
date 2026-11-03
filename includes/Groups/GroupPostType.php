<?php
/**
 * cp_group post type for Groups Lite.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Groups;

use ChurchPlugins\Setup\PostTypes\PostType;

/**
 * Registers `cp_group` with the same public slug CP Groups uses.
 *
 * Metaboxes are intentionally empty. Lite displays synced groups; it does not
 * edit leader rows, and it does not load CMB2 fields.
 *
 * @since 1.2.0
 */
class GroupPostType extends PostType {

	/**
	 * Child constructor.
	 */
	public function __construct() {
		$this->post_type    = 'cp_group';
		$this->single_label = __( 'Group', 'cp-sync' );
		$this->plural_label = __( 'Groups', 'cp-sync' );

		parent::__construct();
	}

	/**
	 * Lite has no admin metaboxes.
	 *
	 * @return void
	 */
	public function register_metaboxes() {}

	/**
	 * Archive at /groups/, matching CP Groups.
	 *
	 * The slug is fixed. A translated plural label must not move the archive.
	 *
	 * @return array
	 */
	public function get_args() {
		$args                 = parent::get_args();
		$args['menu_icon']    = 'dashicons-groups';
		$args['has_archive']  = 'groups';
		$args['rewrite']      = [
			'slug'       => 'groups',
			'with_front' => false,
		];
		$args['supports'][]   = 'page-attributes';

		return $args;
	}
}
