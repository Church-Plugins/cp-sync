<?php

namespace CP_Sync\Integrations;

use CP_Sync\Exception;

class CP_Groups extends Integration {

	public $id = 'cp_groups';

	public $type = 'groups';

	public $label = 'Groups';

	public function update_item( $item ) {
		if ( $id = $this->get_chms_item_id( $item['chms_id'] ) ) {
			$item['ID'] = $id;
		}

		unset( $item['chms_id'] );

		$item['post_type'] = 'cp_group';

		if ( is_object( $item['post_content'] ) ) {
			$item['post_content'] = '';
		}

		$id = wp_insert_post( $item );

		if ( ! $id ) {
			throw new Exception( 'Group could not be created' );
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
	}

	public function register_taxonomy($taxonomy, $args) {
		register_taxonomy( $taxonomy, 'cp_group', $args );
	}
}