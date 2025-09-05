<?php
namespace CP_Sync\Integrations;

use CP_Sync\Exception;

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
	 * The associated post type
	 *
	 * @var string
	 */
	protected $post_type;

	/**
	 * ChMS ID => Post ID cache
	 *
	 * @var array
	 */
	protected $chms_id_cache = null;

	/**
	 * The image cache directory
	 *
	 * @var string
	 */
	protected $image_cache_dir = null;

	/**
	 * Set the Action
	 */
	public function __construct() {
		$this->action = 'pull_' . $this->type;
		$this->image_cache_dir = apply_filters( 'cp_sync_image_cache_dir', 'cp-sync' ) . '/' . $this->type;

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
		 * @param \CP_Sync\Integrations\Integration $integration The integration instance.
		 * @return array
		 * @since 1.1.0
		 */
		$items = apply_filters( 'cp_sync_process_items', $items, $this );

		cp_sync()->logging->log( 'Processing ' . count( $items ) . ' items for ' . $this->label );

		/**
		 * Whether to do a hard refresh.
		 *
		 * @param bool $hard_refresh Whether to do a hard refresh.
		 * @param array $items The items to process.
		 * @param \CP_Sync\Integrations\Integration $integration The integration instance.
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

		cp_sync()->logging->log( 'Process disbatched for ' . $this->label );
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
		if ( isset( $item['_is_term'] ) ) {
			return $this->process_term( $item );
		}

		$tax_input = $item['tax_input'] ?? [];

		unset( $item['tax_input'] ); // child classes don't need access to this

		try {
			$id = $this->update_item( $item );
			$this->update_chms_id_cache( $item['chms_id'], $id );
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
	 * Return thumbnail url without url params to allow for AWS auth keys
	 *
	 * @param $url
	 *
	 * @return mixed|string
	 *
	 * @author Tanner Moushey, 12/27/24
	 */
	public function normalize_thumbnail_url( $url  ) {
		return apply_filters( 'cp_sync_normalize_thumbnail_url', explode( '?', $url )[0], $url, $this );
	}

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

		if ( empty( $item['thumbnail_url'] ) ) {
			return;
		}

		// remove any query strings from the thumbnail URL
		$thumbnail_url = $this->normalize_thumbnail_url( $item['thumbnail_url'] );

		// import the image and set as the thumbnail
		if ( get_post_meta( $id, '_thumbnail_url', true ) == $thumbnail_url ) {
			return;
		}

		$thumb_id = $this->sideload_image( $item, $id );

		if ( is_wp_error( $thumb_id ) ) {
			cp_sync()->logging->log( 'Could not import image: ' . $thumb_id->get_error_message() );
		} else if ( $thumb_id ) {
			set_post_thumbnail( $id, $thumb_id );
			update_post_meta( $id, '_thumbnail_url', $thumbnail_url );
		}
	}

	public function sideload_image( $item, $id ) {
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$thumbnail_url = $item['thumbnail_url'];
		$file_name     = sanitize_title( $item['post_title'] ) . '-' . $id . '-' . md5( $thumbnail_url );

		// Validate URL
		if ( ! filter_var( $thumbnail_url, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error( 'invalid_url', __( 'The provided thumbnail URL is invalid.', 'your-text-domain' ) );
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return new \WP_Error( 'upload_error', __( 'Unable to retrieve upload directory.', 'your-text-domain' ) );
		}

		// Ensure the directory exists
		$image_cache_path = trailingslashit( $upload_dir['basedir'] ) . $this->image_cache_dir;
		if ( ! file_exists( $image_cache_path ) && ! wp_mkdir_p( $image_cache_path ) ) {
			return new \WP_Error( 'directory_creation_failed', __( 'Failed to create cache directory.', 'your-text-domain' ) );
		}
		chmod( $image_cache_path, 0755 );

		// Retrieve the image from the URL
		$response = wp_remote_get( $thumbnail_url, [
			'timeout'   => 10,
			'sslverify' => true,
		] );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'http_request_failed', __( 'Failed to fetch the image from the URL.', 'your-text-domain' ) );
		}

		// Retrieve image content
		$image_content = wp_remote_retrieve_body( $response );

		// Save the image temporarily
		$temp_file_name = sanitize_file_name( $file_name . '.tmp' );
		$save_path     = trailingslashit( $image_cache_path ) . $temp_file_name;

		if ( false === file_put_contents( $save_path, $image_content ) ) {
			return new \WP_Error( 'file_save_failed', __( 'Failed to save the image locally.', 'your-text-domain' ) );
		}

		// Detect MIME type
		$mime_type = wp_remote_retrieve_header( $response, 'content-type' );
		if ( 'application/octet-stream' == $mime_type ) {
			$mime_type = $this->detect_mime_type_with_fallback( $thumbnail_url, $save_path, $mime_type );
		}

		// Supported MIME types and corresponding extensions
		$supported_mime_types = apply_filters( 'cp_sync_image_mime_types', [
			'image/jpeg' => 'jpg',
			'image/jpg'  => 'jpg',
			'image/jpe'  => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		] );

		if ( ! array_key_exists( $mime_type, $supported_mime_types ) ) {
			unlink( $save_path );

			return new \WP_Error( 'unsupported_mime_type', __( 'The MIME type is not supported.', 'your-text-domain' ) );
		}

		// Rename the file with the correct extension
		$extension      = $supported_mime_types[ $mime_type ];
		$final_file_name  = sanitize_file_name( $file_name . '.' . $extension );
		$final_save_path = trailingslashit( $image_cache_path ) . $final_file_name;
		rename( $save_path, $final_save_path );

		// Validate saved file
		if ( ! getimagesize( $final_save_path ) ) {
			unlink( $final_save_path );

			return new \WP_Error( 'invalid_image', __( 'The saved file is not a valid image.', 'your-text-domain' ) );
		}

		// Prepare attachment data
		$attachment_data = [
			'post_mime_type' => $mime_type,
			'post_title'     => sanitize_text_field( $item['post_title'] . ' Thumbnail' ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		// Insert attachment into the WordPress Media Library
		$attachment_id = wp_insert_attachment( $attachment_data, $final_save_path, $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			unlink( $final_save_path );

			return $attachment_id;
		}

		// Generate attachment metadata and update
		$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $final_save_path );
		wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

		return $attachment_id;

	}

	/**
	 * Detect the MIME type of a file, prioritizing the Content-Type header.
	 *
	 * @param string $thumbnail_url The URL of the file.
	 * @param string $file_path The local path to the downloaded file.
	 * @param string $default_mime_type The default MIME type to return if detection fails.
	 * @return string The detected MIME type.
	 */
	public function detect_mime_type_with_fallback($thumbnail_url, $file_path, $default_mime_type = 'application/octet-stream') {
		// Check the Content-Type header from the URL
		$response = wp_remote_head( $thumbnail_url, [
			'timeout'   => 10,
			'sslverify' => true,
		] );

		$fallback_mime_type = apply_filters( 'churchplugins_fallback_mime_type', [
			'application/xml',
			'application/octet-stream'
		] );

		if ( ! is_wp_error( $response ) ) {
			$header_mime_type = wp_remote_retrieve_header( $response, 'content-type' );
			if ( $header_mime_type && ! in_array( $header_mime_type, $fallback_mime_type ) ) {
				return $header_mime_type;
			}
		}

		// Fallback: Use finfo to detect MIME type from the local file
		if ( function_exists( 'finfo_open' ) ) {
			$finfo          = finfo_open( FILEINFO_MIME_TYPE );
			$file_mime_type = finfo_file( $finfo, $file_path );
			finfo_close( $finfo );

			if ( $file_mime_type ) {
				return $file_mime_type;
			}
		}

		// Default to the specified MIME type
		return $default_mime_type;
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
	 * Prime the ChMS ID => Post ID cache
	 */
	public function prime_cache() {
		if ( null !== $this->chms_id_cache ) {
			return;
		}

		$cache_key = 'chms_id_cache_' . $this->id;

		if ( wp_cache_get( $cache_key, 'cp_sync' ) ) {
			$this->chms_id_cache = wp_cache_get( $cache_key, 'cp_sync' );
			return;
		}

		global $wpdb;

		// get all chms_ids for this post type using a join
		// this is so we only get the meta for the correct post type
		$chms_ids = $wpdb->get_results( "
			SELECT pm.meta_value AS chms_id, pm.post_id
			FROM $wpdb->postmeta pm
			JOIN $wpdb->posts p ON pm.post_id = p.ID
			WHERE pm.meta_key = '_chms_id'
			AND p.post_type = '{$this->post_type}'
		" );

		$this->chms_id_cache = [];

		if ( is_array( $chms_ids ) ) {
			foreach( $chms_ids as $chms_id ) {
				$this->chms_id_cache[ $chms_id->chms_id ] = $chms_id->post_id;
			}
		}
		wp_cache_set( $cache_key, $this->chms_id_cache, 'cp_sync' );
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
		$this->prime_cache();
		return $this->chms_id_cache[ $chms_id ] ?? null;
	}

	/**
	 * Update the chms id cache
	 *
	 * @param $chms_id
	 * @param $post_id
	 */
	public function update_chms_id_cache( $chms_id, $post_id ) {
		$this->chms_id_cache[ $chms_id ] = $post_id;
		wp_cache_set( 'chms_id_cache_' . $this->id, $this->chms_id_cache, 'cp_sync' );
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
		if ( ! empty( $item['thumbnail_url'] ) ) {
			$item['thumbnail_url'] = $this->normalize_thumbnail_url( $item['thumbnail_url'] );
		}

		return md5( serialize( $item ) );
	}

	/**
	 * Pull all data from a ChMS and enqueue it
	 *
	 * @param array $data The formatted data from the ChMS.
	 * @return true|\CP_Sync\Chms\ChMSError
	 */
	public function process_formatted_data( $data ) {
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