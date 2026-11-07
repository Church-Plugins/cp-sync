<?php
/**
 * CP Groups taxonomies for Groups Lite.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Groups;

use ChurchPlugins\Setup\Taxonomies\Taxonomy;

/**
 * One CP Groups taxonomy (type, category, or life stage) registered onto cp_group.
 *
 * @since 1.2.0
 */
class GroupTaxonomy extends Taxonomy {

	/**
	 * Public rewrite slug.
	 *
	 * @var string
	 */
	protected $rewrite_slug = '';

	/**
	 * @param string $taxonomy    Taxonomy name, such as cp_group_type.
	 * @param string $single      Singular label.
	 * @param string $plural      Plural label.
	 * @param string $rewrite_slug Rewrite slug CP Groups uses for this taxonomy.
	 */
	public function __construct( $taxonomy, $single, $plural, $rewrite_slug ) {
		$this->taxonomy     = $taxonomy;
		$this->single_label = $single;
		$this->plural_label = $plural;
		$this->rewrite_slug = $rewrite_slug;

		parent::__construct();
	}

	/**
	 * Attach the taxonomy to groups.
	 *
	 * @return string[]
	 */
	public function get_object_types() {
		return [ 'cp_group' ];
	}

	/**
	 * Lite does not seed terms. Sync inserts them.
	 *
	 * @return array
	 */
	public function get_terms() {
		return [];
	}

	/**
	 * Lite does not ship term metadata.
	 *
	 * @return array
	 */
	public function get_term_data() {
		return [];
	}

	/**
	 * Hierarchical, like CP Groups, with a fixed rewrite slug and no admin screens.
	 *
	 * @return array
	 */
	public function get_args() {
		$args                       = parent::get_args();
		$args['show_ui']            = false;
		$args['show_admin_column']  = false;
		$args['show_in_quick_edit'] = false;
		$args['hierarchical']       = true;
		$args['show_in_rest']       = false;
		$args['rewrite']            = [
			'slug' => $this->rewrite_slug,
		];

		return $args;
	}
}
