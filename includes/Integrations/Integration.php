<?php
namespace CP_Connect\Integrations;

use CP_Connect\Exception;

abstract class Integration extends \WP_Background_Process {

	/**
	 * @var | Unique ID for this integration
	 */
	public $id;

	/**
	 * @var | The type of content (Events, Groups, Etc)
	 */
	public $type;

	/**
	 * @var | Label for this integration
	 */
	public $label;

	/**
	 * Set the Action
	 */
	public function __construct() {
		$this->action = 'pull_' . $this->type;

		$this->actions();
		parent::__construct();
	}

	/**
	 * Might need to do something in an integration
	 *
	 * @since  1.1.0
	 *
	 * @author Tanner Moushey, 12/20/23
	 */
	public function actions() {
		add_action( 'init', [ $this, 'load_taxonomies' ] );
	}

	/**
	 * Pull the content from the ChMS which should hook in through the filter.
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function process( $items ) {
		/**
		 * Filter the items before processing.
		 *
		 * @param array $items The items to process.
		 * @param \CP_Connect\Integrations\Integration $integration The integration instance.
		 * @return array
		 * @since 1.1.0
		 */
		$items = apply_filters( 'cp_sync_process_items', $items, $this );

		/**
		 * Whether to do a hard refresh.
		 *
		 * @param bool $hard_refresh Whether to do a hard refresh.
		 * @param array $items The items to process.
		 * @param \CP_Connect\Integrations\Integration $integration The integration instance.
		 * @return bool
		 * @since 1.0.0
		 */
		$hard_refresh = apply_filters( 'cp_sync_process_hard_refresh', true, $items, $this );

		$item_store = $this->get_store();

		foreach( $items as $item ) {
			$store = $item_store[ $item['chms_id'] ] ?? false;
			
			if ( $hard_refresh ) {
				$item[ md5( time() ) ] = time(); // add a random key to force an update
			}

			// check if any of the provided values have changed
			if ( $this->create_store_key( $item ) !== $store ) {
				/**
				 * Modify the item before processing.
				 *
				 * @param array $item The item to process.
				 * @param Integration $integration The integration instance.
				 * @return array
				 */
				$item_data = apply_filters( "cp_sync_{$this->type}_item", $item, $this );
				$this->push_to_queue( $item_data );
			}

			unset( $item_store[ $item['chms_id'] ] );
		}

		foreach( $item_store as $chms_id => $hash ) {
			$this->remove_item( $chms_id );
		}

		$this->update_store( $items );

		$this->save()->dispatch();
	}

	/**
	 * Process the taxonomies
	 *
	 * @param $taxonomies
	 */
	public function process_taxonomies( $taxonomies ) {
		$saved_taxonomies = $this->get_store( 'taxonomies' );

		$hard_refresh = apply_filters( 'cp_sync_process_hard_refresh', true, $taxonomies, $this );

		foreach( $taxonomies as $taxonomy => $data ) {
			$saved_terms = $this->get_store( $taxonomy );

			$terms     = $data['terms'] ?? [];
			$formatted = [];

			foreach ( $terms as $chms_id => $name ) {
				$hashed_data = $saved_terms[ $chms_id ] ?? false;

				$term_data = [
					'_is_term' => true,
					'chms_id'  => $chms_id,
					'name'     => $name,
					'taxonomy' => $taxonomy,
				];

				if ( $hard_refresh ) {
					$term_data[ md5( time() ) ] = time(); // add a random key to force an update
				}

				if ( $this->create_store_key( $term_data ) !== $hashed_data ) {
					$this->push_to_queue( $term_data );
				}

				unset( $saved_terms[ $chms_id ] );

				$formatted[ $chms_id ] = $term_data;
			}

			// delete any terms that are no longer in the ChMS results
			foreach ( $saved_terms as $chms_id => $_ ) {
				$this->remove_term( "{$taxonomy}_{$chms_id}" );
			}

			$this->update_store( $formatted, $taxonomy );

			unset( $saved_taxonomies[ $taxonomy ] );
		}

		// delete any taxonomies that are no longer in the ChMS results
		foreach ( $saved_taxonomies as $taxonomy => $_ ) {
			$this->remove_taxonomy( $taxonomy );
		}

		$this->update_store( array_map( fn( $taxonomy ) => [ 'chms_id' => $taxonomy['taxonomy'] ], $taxonomies ), 'taxonomies' );
	}

	/**
	 * Process the term
	 *
	 * @param $term
	 */
	public function process_term( $term ) {
		// check for existing term
		$existing_term = term_exists( $term['name'], $term['taxonomy'] );

		if ( is_wp_error( $existing_term) ) {
			error_log( 'Could not check for existing term: ' . json_encode( $term ) );
			return false;
		}

		if ( ! $existing_term ) {
			$new_term = wp_insert_term( $term['name'], $term['taxonomy'] );

			if ( is_wp_error( $new_term ) ) {
				error_log( 'Could not import term: ' . json_encode( $term ) );
				return false;
			}

			// create compound chms_id for term
			add_term_meta( $new_term['term_id'], '_chms_id', $term['taxonomy'] . '_' . $term['chms_id'] );
		}

		return false;
	}

	/**
	 * The task for handling individual item updates
	 *
	 * @param $item
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function task( $item ) {
		if ( $item['_is_term'] ) {
			return $this->process_term( $item );
		}

		$tax_input = $item['tax_input'] ?? [];

		unset( $item['tax_input'] ); // child classes don't need access to this

		try {
			$id = $this->update_item( $item );
		} catch ( Exception $e ) {
			error_log( 'Could not import item: ' . json_encode( $item ) );
			error_log( $e );
			return false;
		}

		foreach ( $tax_input as $taxonomy => $terms ) {
			$term_ids = $this->get_chms_term_ids( ...array_map( fn( $term ) => $taxonomy . '_' . $term, $terms ) );
			wp_set_post_terms( $id, $term_ids, $taxonomy );
		}

		$this->maybe_sideload_thumb( $item, $id );
		$this->maybe_update_location( $item, $id );

		// re-save the post to trigger slug calculation post location update
		wp_update_post( get_post( $id ) );

		// Save ChMS ID
		if ( ! empty( $item['chms_id'] ) ) {
			update_post_meta( $id, '_chms_id', $item['chms_id'] );
		}

		do_action( 'cp_update_item_after', $item, $id, $this );
		do_action( 'cp_' . $this->id . '_update_item_after', $item, $id );
		return false;
	}

	protected function complete() {
		parent::complete();
	}

	/**
	 * Update the post with the associated data
	 *
	 * @param $item
	 *
	 * @throws Exception
	 * @return int | bool The post ID on success, FALSE on failure
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	abstract function update_item( $item );

	/**
	 * Register a taxonomy.
	 *
	 * @param string $taxonomy The taxonomy to register.
	 * @param array $args The arguments to register the taxonomy with.
	 * @since  1.1.0
	 * @return void
	 */
	abstract function register_taxonomy( $taxonomy, $args );

	/**
	 * Import item thumbnail
	 *
	 * @param $item
	 * @param $id
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function maybe_sideload_thumb( $item, $id ) {
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		// import the image and set as the thumbnail
		if ( ! empty( $item['thumbnail_url'] ) && get_post_meta( $id, '_thumbnail_url', true ) !== $item['thumbnail_url'] ) {
			$thumb_id = media_sideload_image( $item['thumbnail_url'], $id, $item['post_title'] . ' Thumbnail', 'id' );

			if ( ! is_wp_error( $thumb_id ) ) {
				set_post_thumbnail( $id, $thumb_id );
				update_post_meta( $id, '_thumbnail_url', $item['thumbnail_url'] );
			}
		}
	}

	/**
	 * @param $item
	 * @param $id
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function maybe_update_location( $item, $id ) {
		if ( ! taxonomy_exists( 'cp_location' ) ) {
			return;
		}

		$location = empty( $item['cp_location'] ) ? false : $item['cp_location'];
		wp_set_post_terms( $id, $location, 'cp_location' );
	}

	/**
	 * Remove a term via a chms_id
	 *
	 * @param $chms_id
	 *
	 * @since  1.1.0
	 */
	public function remove_term( $chms_id ) {
		$id = $this->get_chms_term_ids( $chms_id );

		if ( ! $id ) {
			return;
		}

		wp_delete_term( current( $id ), $chms_id );
	}

	/**
	 * Remove all posts associated with this chms_id, there should only be one
	 *
	 * @param $chms_id
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function remove_item( $chms_id ) {
		$id = $this->get_chms_item_id( $chms_id );

		if ( $thumb = get_post_thumbnail_id( $id ) ) {
			wp_delete_attachment( $thumb, true );
		}

		wp_delete_post( $id, true );
	}

	/**
	 * Get the post associated with the provided item
	 *
	 * @param $chms_id
	 *
	 * @return string|null
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_chms_item_id( $chms_id ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_chms_id' AND meta_value = %s", $chms_id ) );
	}

	/**
	 * Get term by chms_id
	 *
	 * @param $chms_ids The chms_id(s) to search for.
	 * 
	 * @return array|null
	 * @since  1.1.0
	 */
	public function get_chms_term_ids( ...$chms_ids ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_id FROM $wpdb->termmeta WHERE meta_key = '_chms_id' AND meta_value IN (" . implode( ',', array_fill( 0, count( $chms_ids ), '%s' ) ) . ")",
				...$chms_ids
			)
		);

		if ( ! $results ) {
			return [];
		}

		return array_map( 'intval', wp_list_pluck( $results, 'term_id' ) );
	}

	/**
	 * Get the stored hash values from the last pull
	 *
	 * @param string $group The group to pull from.
	 * @return false|mixed|void
	 * @since  1.0.0
	 * @updated 1.1.0 - Added group parameter
	 * @author Tanner Moushey
	 */
	public function get_store( $group = null ) {
		$key = "cp_sync_store_{$this->type}";

		if ( $group ) {
			$key .= "_{$group}";
		}

		return get_option( $key, [] );
	}

	/**
	 * Update the store cache so we know what to update each time
	 *
	 * @param array $items The items to update.
	 * @param string $group The group to update.
	 * @since  1.0.0
	 * @updated 1.1.0 - Added group parameter
	 * @author Tanner Moushey
	 */
	public function update_store( $items, $group = null ) {
		$store = [];

		foreach( $items as $item ) {
			$store[ $item['chms_id'] ] = $this->create_store_key( $item );
		}

		$key = "cp_sync_store_{$this->type}";

		if ( $group ) {
			$key .= "_{$group}";
		}

		update_option( $key, $store, false );
	}

	/**
	 * Create the store key
	 *
	 * @param $item
	 *
	 * @return string
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function create_store_key( $item ) {
		return md5( serialize( $item ) );
	}

	/**
	 * Pull all data from a ChMS and enqueue it
	 *
	 * @param \CP_Connect\ChMS\ChMS $chms The ChMS to pull from.
	 * @return true|\CP_Connect\Chms\ChMSError
	 */
	public function pull_data_from_chms( $chms ) {
		$data = $chms->get_formatted_data( $this->id );

		if ( is_wp_error( $data ) ) {
			return $data;
		}
	
		$posts      = $data['posts'] ?? [];
		$taxonomies = $data['taxonomies'] ?? [];

		$posts      = apply_filters( 'cp_sync_pull_items', $posts, $this );
		$taxonomies = apply_filters( 'cp_sync_pull_taxonomies', $taxonomies, $this );

		if ( is_array( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$this->create_taxonomy( $taxonomy );
			}
	
			$this->process_taxonomies( $taxonomies );
		}
	
		$this->process( $posts );
	}

	/**
	 * Create a taxonomy and save it in wp_options
	 *
	 * @param array $taxonomy The taxonomy to create.
	 * @since  1.1.0
	 */
	protected function create_taxonomy( $taxonomy ) {
		$slug = $taxonomy['taxonomy'];

		// save data in an option to create taxonomies for the integration
		$data = get_option( "cp_sync_taxonomies_{$this->id}", [] );

		$data[ $slug ] = [
			'single_label' => $taxonomy['single_label'],
			'plural_label' => $taxonomy['plural_label'],
			'taxonomy'     => $slug,
		];

		update_option( "cp_sync_taxonomies_{$this->id}", $data );

		$this->load_taxonomy( $slug );
	}

	/**
	 * Remove a taxonomy and delete it from wp_options
	 *
	 * @param string $taxonomy The taxonomy to remove.
	 * @since  1.1.0
	 */
	protected function remove_taxonomy( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$slug = $taxonomy;

		// remove data in an option to create taxonomies for the integration
		$data = get_option( "cp_sync_taxonomies_{$this->id}", [] );

		unset( $data[ $slug ] );

		update_option( "cp_sync_taxonomies_{$this->id}", $data );

		// remove all terms
		$terms = get_terms( $slug, [ 'hide_empty' => false ] );

		foreach( $terms as $term ) {
			wp_delete_term( $term->term_id, $slug );
		}

		unregister_taxonomy( $slug );
	}

	/**
	 * Define a taxonomy based on data stored in wp_options and call the child register_taxonomy method
	 *
	 * @param string $taxonomy The taxonomy to load.
	 */
	protected function load_taxonomy( $taxonomy ) {
		if ( taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$taxonomy_data = get_option( "cp_sync_taxonomies_{$this->id}", [] );

		if ( ! isset( $taxonomy_data[ $taxonomy ] ) ) {
			return;
		}

		$taxonomy_data = $taxonomy_data[ $taxonomy ];

		$single_label = $taxonomy_data['single_label'];
		$plural_label = $taxonomy_data['plural_label'];

		$labels = [
			'name'              => $plural_label,
			'singular_name'     => $single_label,
			'search_items'      => 'Search ' . $plural_label,
			'all_items'         => 'All ' . $plural_label,
			'parent_item'       => 'Parent ' . $single_label,
			'parent_item_colon' => 'Parent ' . $single_label . ':',
			'edit_item'         => 'Edit ' . $single_label,
			'update_item'       => 'Update ' . $single_label,
			'add_new_item'      => 'Add New ' . $single_label,
			'new_item_name'     => 'New ' . $single_label . ' Name',
			'menu_name'         => $plural_label,
			'not_found'         => 'No ' . $plural_label . " found",
			'no_terms'          => 'No ' . $plural_label,
		];

		$args   = [
			'public'            => true,
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'show_in_menu'      => true,
			'show_in_nav_menus' => true,
			'show_tag_cloud'    => true,
			'show_admin_column' => true,
			'slug'				      => $taxonomy,
		];

		/**
		 * Register a taxonomy for the integration
		 *
		 * @param string $taxonomy The taxonomy to register.
		 * @param array  $args     The arguments to register the taxonomy with.
		 * @since 1.1.0
		 */
		do_action( "cp_sync_load_taxonomy_{$this->id}", $taxonomy, $args );

		$this->register_taxonomy( $taxonomy, $args );
	}

	/**
	 * Load all taxonomies.
	 * @since 1.1.0
	 */
	public function load_taxonomies() {
		$taxonomies = get_option( "cp_sync_taxonomies_{$this->id}", [] );

		foreach( $taxonomies as $data ) {
			$this->load_taxonomy( $data['taxonomy'] );
		}
	}
}