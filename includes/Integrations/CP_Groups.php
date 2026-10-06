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

		// CP Groups 1.2 replaced the single leader fields with a `leaders` list.
		// A ChMS that already built that list ( Planning Center can have several
		// leaders ) keeps it; otherwise fold leader / leader_email into one row.
		if ( defined( 'CP_GROUPS_PLUGIN_VERSION' ) && version_compare( CP_GROUPS_PLUGIN_VERSION, '1.2.0', '>=' ) && isset( $item['meta_input'] ) && is_array( $item['meta_input'] ) ) {
			$item['meta_input'] = self::prepare_leader_meta( $item['meta_input'], CP_GROUPS_PLUGIN_VERSION );
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

	/**
	 * Shape leader meta for the installed CP Groups version.
	 *
	 * Below 1.2 the plugin displays `leader` and `leader_email`, so those keys
	 * are left as the ChMS formatter set them. From 1.2 the plugin stores a
	 * `leaders` list of name/email rows. An existing list is kept ( so every
	 * Planning Center leader survives ); a single leader/leader_email pair is
	 * folded into one row, matching the previous behavior for CCB.
	 *
	 * @param array  $meta_input     Group meta destined for wp_insert_post().
	 * @param string $plugin_version CP_GROUPS_PLUGIN_VERSION.
	 * @return array
	 */
	public static function prepare_leader_meta( $meta_input, $plugin_version ) {
		if ( ! is_array( $meta_input ) ) {
			return [];
		}

		if ( ! is_string( $plugin_version ) || version_compare( $plugin_version, '1.2.0', '<' ) ) {
			return $meta_input;
		}

		if ( ! self::leader_rows_present( $meta_input['leaders'] ?? null ) ) {
			$meta_input['leaders'] = [
				[
					'name'  => $meta_input['leader'] ?? '',
					'email' => $meta_input['leader_email'] ?? '',
				],
			];
		}

		unset( $meta_input['leader'] );
		unset( $meta_input['leader_email'] );

		return $meta_input;
	}

	/**
	 * Whether `leaders` is a non-empty list of name/email rows.
	 *
	 * @param mixed $leaders Candidate meta value.
	 * @return bool
	 */
	protected static function leader_rows_present( $leaders ) {
		if ( ! is_array( $leaders ) || array() === $leaders ) {
			return false;
		}

		$found = false;

		foreach ( $leaders as $leader ) {
			if ( ! is_array( $leader ) ) {
				return false;
			}

			$name  = isset( $leader['name'] ) && is_string( $leader['name'] ) ? trim( $leader['name'] ) : '';
			$email = isset( $leader['email'] ) && is_string( $leader['email'] ) ? trim( $leader['email'] ) : '';

			if ( '' !== $name || '' !== $email ) {
				$found = true;
			}
		}

		return $found;
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