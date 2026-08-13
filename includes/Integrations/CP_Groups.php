<?php

namespace CP_Sync\Integrations;

use CP_Sync\Exception;

class CP_Groups extends Integration {

	public $id = 'cp_groups';

	public $type = 'groups';

	public $label = 'Groups';

	protected $post_type = 'cp_group';

	public function update_item( $item ) {
		if ( $id = $this->get_chms_item_id( $item['chms_id'] ) ) {
			$item['ID'] = $id;
		}

		unset( $item['chms_id'] );

		$item['post_type'] = 'cp_group';

		// Ensure post_content is a string
		if ( is_object( $item['post_content'] ) || is_array( $item['post_content'] ) ) {
			$item['post_content'] = '';
		}

		// If the plugin version is 1.2.0 or greater, we need to update the leader meta
		if ( defined( 'CP_GROUPS_PLUGIN_VERSION' ) && version_compare( CP_GROUPS_PLUGIN_VERSION, '1.2.0', '>=' ) ) {
			$item['meta_input']['leaders'] = [
				[
					'name' => $item['meta_input']['leader'] ?? '',
					'email' => $item['meta_input']['leader_email'] ?? ''
				]
			];

			unset( $item['meta_input']['leader'] );
			unset( $item['meta_input']['leader_email'] );
		}

		$id = wp_insert_post( $item );

		if ( ! $id || is_wp_error( $id ) ) {
			$error_message = is_wp_error( $id ) ? $id->get_error_message() : 'wp_insert_post returned 0';
			cp_sync()->logging->log( 'ERROR: Group could not be created: ' . $error_message );
			cp_sync()->logging->log( 'Item data: ' . json_encode( $item, JSON_PRETTY_PRINT ) );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Always caught in Integration::process_item() and only written to the log (never echoed to the browser); HTML-escaping would corrupt the log output.
			throw new Exception( 'Group could not be created: ' . $error_message );
		}

		$taxonomies = [ 'group_category', 'group_type', 'group_life_stage' ];

		foreach( $taxonomies as $tax ) {
			$taxonomy = 'cp_' . $tax;
			$categories = [];

			if ( empty( $item[ $tax ] ) ) {
				wp_set_post_terms( $id, [], $taxonomy );
				continue;
			}

			foreach( $item[ $tax ] as $category ) {

				if ( ! $term = term_exists( $category, $taxonomy ) ) {
					$term = wp_insert_term( $category, $taxonomy );
				}

				if ( ! is_wp_error( $term ) ) {
					$categories[] = $term['term_id'];
				}
			}

			wp_set_post_terms( $id, $categories, $taxonomy );
		}

		return $id;
	}

	public function actions() {
		parent::actions();

		add_filter( 'cp_groups_filter_facets', [ $this, 'add_synced_facets' ] );
	}

	/**
	 * Add the synced ChMS taxonomies ( tag groups ) as facets on the group archive filter.
	 *
	 * Every taxonomy pulled from the ChMS is included automatically; a CP Groups
	 * setting to control which facets display is planned there, not here.
	 *
	 * @param array $facets Facet taxonomy objects ( ->taxonomy, ->single_label, ->plural_label ).
	 * @return array
	 */
	public function add_synced_facets( $facets ) {
		$existing   = wp_list_pluck( $facets, 'taxonomy' );
		$taxonomies = get_option( "cp_sync_taxonomies_{$this->id}", [] );

		foreach ( $taxonomies as $slug => $data ) {
			// cp_group_type is stored with the sync data but already a core CP Groups facet.
			if ( in_array( $slug, $existing, true ) || ! taxonomy_exists( $slug ) ) {
				continue;
			}

			$facets[] = (object) [
				'taxonomy'     => $slug,
				'single_label' => $data['single_label'] ?? $slug,
				'plural_label' => $data['plural_label'] ?? $slug,
			];
		}

		return $facets;
	}

	public function register_taxonomy($taxonomy, $args) {
		register_taxonomy( $taxonomy, 'cp_group', $args );
	}
}