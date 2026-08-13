<?php
namespace CP_Sync\Integrations;

use CP_Sync\Exception;

abstract class Integration extends \WP_Background_Process {

	/**
	 * Post meta key that, when truthy, locks a post against sync updates and removal.
	 *
	 * A site admin sets this from the post edit screen after customizing an imported
	 * post so a later sync never overwrites or deletes their changes. Honored in
	 * task() ( no update ), process() ( no queue, no removal ), and is_locked().
	 *
	 * @var string
	 */
	const LOCK_META_KEY = '_cp_sync_lock';

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
		 * @since 1.0.0
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

		// Safety net: zero items fetched while the store tracks existing imports is
		// far more likely a silent upstream failure than a genuinely emptied ChMS —
		// and proceeding would delete every previously imported post as "leftover".
		// Abort untouched ( no queue, no removals, store intact ); a legitimately
		// emptied ChMS can be reflected with the Reset tool's content level.
		if ( empty( $items ) && ! empty( $item_store ) ) {
			cp_sync()->logging->log( sprintf(
				'ABORTING %s sync: fetch returned 0 items while %d are tracked locally. Treating this as a failed fetch, not a mass removal. If you really removed everything in your ChMS, use the Reset tool (content level) to clear imported content.',
				$this->label,
				count( $item_store )
			) );
			return;
		}

		$locked_ids = [];

		foreach( $items as $item ) {
			// A locked post has been customized by an admin who does not want sync to
			// touch it. Never queue it for update, and unset it from the leftover store
			// so the removal pass below cannot delete it either.
			if ( $this->is_locked( $item['chms_id'] ) ) {
				$locked_ids[ $item['chms_id'] ] = true;
				unset( $item_store[ $item['chms_id'] ] );
				continue;
			}

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

		// Leftovers a filter chose to keep ( e.g. past events, see
		// TEC::preserve_past_events ). Their existing hashes are merged back into the
		// store below so later syncs keep re-evaluating them — otherwise they would
		// fall out of tracking on the first run and a filter flipped to "remove" later
		// could never reach them.
		$preserved = [];

		foreach( $item_store as $chms_id => $hash ) {
			// Never remove a locked post, even if it has disappeared from the ChMS —
			// the admin has chosen to keep this post as-is.
			if ( $this->is_locked( $chms_id ) ) {
				continue;
			}

			/**
			 * Filter whether an item should be removed.
			 *
			 * @param bool $should_remove Whether to remove the item. Default true.
			 * @param string $chms_id The ChMS ID of the item.
			 * @param Integration $integration The integration instance.
			 * @return bool
			 */
			$should_remove = apply_filters( "cp_sync_{$this->type}_should_remove_item", true, $chms_id, $this );

			if ( $should_remove ) {
				$this->remove_item( $chms_id );
			} else {
				$preserved[ $chms_id ] = $hash;
			}
		}

		// Record hashes for everything EXCEPT locked items. While locked, an item's
		// hash must not be tracked — otherwise unlocking would compare the incoming
		// hash to the one recorded during the lock, see "unchanged", and never queue
		// the post, leaving it silently diverged ("uncheck to resume syncing" would
		// be a lie). With no recorded hash, the first sync after unlocking always
		// queues the item and brings the post back in line with the ChMS.
		if ( ! empty( $locked_ids ) ) {
			$items = array_values(
				array_filter(
					$items,
					function ( $item ) use ( $locked_ids ) {
						return empty( $locked_ids[ $item['chms_id'] ] );
					}
				)
			);
		}

		$this->update_store( $items, null, $preserved );

		$dispatched = $this->save()->dispatch();

		// dispatch() fires a short-timeout, non-blocking loopback POST that starts
		// the queue worker. A WP_Error here USUALLY means the request could not be
		// sent ( blocked loopback, security plugin, DNS ) — but a busy worker pool
		// can also time out the handoff even though the worker still starts, so
		// this is a diagnostic breadcrumb, not proof of failure. Either way the
		// health-check cron re-dispatches a stalled queue.
		if ( is_wp_error( $dispatched ) ) {
			cp_sync()->logging->log( 'Process dispatched for ' . $this->label . ', but the loopback request did not confirm delivery (' . $dispatched->get_error_message() . '). If no items import, the worker likely never started; the health-check cron will retry the queue.' );
		} else {
			cp_sync()->logging->log( 'Process dispatched for ' . $this->label );
		}
	}

	/**
	 * Process the taxonomies
	 *
	 * @param $taxonomies
	 */
	public function process_taxonomies( $taxonomies ) {
		$saved_taxonomies = $this->get_store( 'taxonomies' );

		$hard_refresh = apply_filters( 'cp_sync_process_hard_refresh', true, $taxonomies, $this );
		cp_sync()->logging->log( 'Processing taxonomies with hard_refresh: ' . ( $hard_refresh ? 'true' : 'false' ) );

		$total_terms_queued = 0;
		foreach( $taxonomies as $taxonomy => $data ) {
			$saved_terms = $this->get_store( $taxonomy );

			$terms     = $data['terms'] ?? [];
			$formatted = [];

			cp_sync()->logging->log( "Processing {$taxonomy} with " . count( $terms ) . " terms" );

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
					$total_terms_queued++;
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

		cp_sync()->logging->log( "Total terms queued: {$total_terms_queued}" );

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
			cp_sync()->logging->log( 'Processing term: ' . $item['name'] );
			$result = $this->process_term( $item );
			cp_sync()->logging->log( 'Term processed: ' . ( $result === false ? 'success' : 'failed' ) );
			return $result;
		}

		$chms_id = $item['chms_id'] ?? 'unknown';
		$title = $item['post_title'] ?? 'unknown';
		cp_sync()->logging->log( "Processing group {$chms_id}: {$title}" );

		// Authoritative lock guard: whatever queued this item, a locked post is never
		// written. Mirrors the process() guards so no code path can overwrite it.
		if ( $this->is_locked( $chms_id ) ) {
			cp_sync()->logging->log( "Skipping locked item {$chms_id}: {$title} ( sync disabled for this post )" );
			return false;
		}

		$tax_input = $item['tax_input'] ?? [];

		unset( $item['tax_input'] ); // child classes don't need access to this

		try {
			$id = $this->update_item( $item );

			if ( ! $id || is_wp_error( $id ) ) {
				$error_msg = is_wp_error( $id ) ? $id->get_error_message() : 'update_item returned false/null';
				cp_sync()->logging->log( "ERROR: Could not import {$chms_id}: {$error_msg}" );
				error_log( "CP-Sync: Could not import item {$chms_id}: {$error_msg}" );
				error_log( 'Item data: ' . json_encode( $item ) );
				// Skip this item instead of retrying forever
				return false;
			}

			cp_sync()->logging->log( "Group {$chms_id} created with ID: {$id}" );
			$this->update_chms_id_cache( $item['chms_id'], $id );
		} catch ( \Throwable $e ) {
			cp_sync()->logging->log( 'ERROR: Could not import group: ' . $e->getMessage() );
			cp_sync()->logging->log( 'Error in file: ' . $e->getFile() . ' on line ' . $e->getLine() );
			error_log( 'CP-Sync: Could not import item: ' . json_encode( $item ) );
			error_log( 'CP-Sync Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			// Skip this item instead of retrying forever
			return false;
		}

		foreach ( $tax_input as $taxonomy => $terms ) {
			$term_ids = $this->get_chms_term_ids( ...array_map( fn( $term ) => $taxonomy . '_' . $term, $terms ) );
			cp_sync()->logging->log( "Setting {$taxonomy} terms: " . implode( ', ', $term_ids ) );
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
		do_action( "cp_sync_{$this->type}_update_item_after", $item, $id );
		do_action( 'cp_' . $this->id . '_update_item_after', $item, $id );
		return false;
	}

	protected function complete() {
		parent::complete();
	}

	/**
	 * Keys already reported as unreadable, to keep the log to one entry per batch.
	 *
	 * @var array
	 */
	protected $reported_corrupt_batches = [];

	/**
	 * Whether the queue column charset has been reported yet this run.
	 *
	 * @var bool
	 */
	protected $charset_logged = false;

	/**
	 * Encode a batch into a form that survives any column charset.
	 *
	 * A serialized array records the byte length of every string it contains. If MySQL
	 * converts the value on the way in, because the column charset cannot represent a
	 * character, those lengths no longer match their strings and the batch can never be
	 * unserialized again. handle() then treats the unreadable batch as an empty one and
	 * deletes it without calling task(), so the sync reports success and imports nothing.
	 *
	 * Base64 output is plain ASCII, which every charset stores identically, so the value
	 * read back is byte for byte the value written.
	 *
	 * @param array $data The batch.
	 * @return string
	 */
	protected function encode_batch( $data ) {
		return base64_encode( serialize( $data ) );
	}

	/**
	 * Decode a batch, accepting batches queued before encoding was introduced.
	 *
	 * @param mixed $data The stored batch.
	 * @return mixed The batch, or the raw value when it cannot be read.
	 */
	protected function decode_batch( $data ) {
		// Batches queued by an older version were stored as arrays.
		if ( is_array( $data ) || ! is_string( $data ) ) {
			return $data;
		}

		$decoded = base64_decode( $data, true );

		if ( false === $decoded ) {
			return $data;
		}

		$unserialized = @unserialize( $decoded ); // phpcs:ignore

		return false === $unserialized ? $data : $unserialized;
	}

	/**
	 * Report the charset of the column the queue is stored in.
	 *
	 * Anything other than utf8mb4 means MySQL will replace characters it cannot represent
	 * when items are written to wp_posts, so titles and content import with substitutions.
	 * The queue itself is protected by encode_batch(), but the database still needs
	 * converting for the content to arrive intact.
	 *
	 * @return void
	 */
	protected function log_queue_charset() {
		global $wpdb;

		if ( $this->charset_logged ) {
			return;
		}

		$this->charset_logged = true;

		list( $table, $key_column, $value_column ) = $this->get_batch_table();

		// Reads the collation of the column itself, not DB_CHARSET.
		$charset = $wpdb->get_col_charset( $table, $value_column );

		if ( is_wp_error( $charset ) || empty( $charset ) ) {
			return;
		}

		cp_sync()->logging->log( "Queue column {$table}.{$value_column} charset: {$charset}" );

		if ( 'utf8mb4' !== $charset ) {
			cp_sync()->logging->log( "WARNING: The database is {$charset} rather than utf8mb4. Characters it cannot represent, such as bullets and curly quotes, will be replaced with '?' when content is imported. Converting the database to utf8mb4 will preserve them." );
		}
	}

	/**
	 * Save the queue, encoded, and confirm it actually reached the database.
	 *
	 * Mirrors WP_Background_Process::save() so that the batch can be encoded on the way in
	 * and the key kept for verification. The parent ignores the result of
	 * update_site_option(), so a write that never lands leaves the queue silently empty.
	 *
	 * @return $this
	 */
	public function save() {
		$count = count( $this->data );

		$this->log_queue_charset();

		if ( ! $count ) {
			cp_sync()->logging->log( "WARNING: Nothing was queued for {$this->label}, so there is nothing to import." );
			$this->data = [];
			return $this;
		}

		$key = $this->generate_key();

		update_site_option( $key, $this->encode_batch( $this->data ) );

		$this->data = [];

		$this->verify_batch_persisted( $key, $count );

		return $this;
	}

	/**
	 * Keep the stored batch encoded as handle() works through it.
	 *
	 * @param string $key  The batch key.
	 * @param array  $data The remaining items.
	 * @return $this
	 */
	public function update( $key, $data ) {
		if ( ! empty( $data ) ) {
			update_site_option( $key, $this->encode_batch( $data ) );
		}

		return $this;
	}

	/**
	 * Read a freshly saved batch back out of the database and confirm it survived.
	 *
	 * @param string|null $key            The batch key.
	 * @param int         $expected_count Number of items handed to save().
	 * @return void
	 */
	protected function verify_batch_persisted( $key, $expected_count ) {
		global $wpdb;

		if ( empty( $key ) ) {
			return;
		}

		list( $table, $column, $value_column ) = $this->get_batch_table();

		// Query the database directly. get_site_option() would return the value from
		// the object cache even when the write never reached MySQL.
		$raw = $wpdb->get_var(
			$wpdb->prepare( "SELECT {$value_column} FROM {$table} WHERE {$column} = %s", $key ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( null === $raw ) {
			cp_sync()->logging->log( "ERROR: Queued {$expected_count} items for {$this->label} but batch {$key} was not saved to the database. Nothing will be imported." );

			if ( ! empty( $wpdb->last_error ) ) {
				cp_sync()->logging->log( "ERROR: Database reported: {$wpdb->last_error}" );
			}

			return;
		}

		$decoded = $this->decode_batch( $raw );

		if ( ! is_array( $decoded ) ) {
			cp_sync()->logging->log( "ERROR: Batch {$key} for {$this->label} was saved but cannot be read back. Nothing will be imported." );
			$this->report_corrupt_batch( $key, $raw );
			return;
		}

		if ( count( $decoded ) !== $expected_count ) {
			cp_sync()->logging->log( "WARNING: Queued {$expected_count} items for {$this->label} but only " . count( $decoded ) . " were saved." );
		}
	}

	/**
	 * Report batches that cannot be unserialized.
	 *
	 * handle() treats a batch whose data fails to unserialize as an empty, fully
	 * processed batch and deletes it without ever calling task(). Log it on the way
	 * past so the failure is visible rather than silent.
	 *
	 * @param int $limit Number of batches to return.
	 * @return array
	 */
	public function get_batches( $limit = 0 ) {
		$batches = parent::get_batches( $limit );

		foreach ( $batches as $batch ) {
			$batch->data = $this->decode_batch( $batch->data );

			if ( is_array( $batch->data ) ) {
				continue;
			}

			cp_sync()->logging->log( "ERROR: Batch {$batch->key} for {$this->label} is unreadable and will be discarded without importing. This usually means the queue was corrupted when it was written to the database." );
			$this->report_corrupt_batch( $batch->key );
		}

		return $batches;
	}

	/**
	 * Log diagnostics for an unreadable batch and keep a copy of the raw value.
	 *
	 * The batch is left for handle() to delete. Retaining it in the queue would
	 * stall every future sync behind data that can never be unserialized.
	 *
	 * @param string      $key The batch key.
	 * @param string|null $raw The raw value, looked up when not supplied.
	 * @return void
	 */
	protected function report_corrupt_batch( $key, $raw = null ) {
		global $wpdb;

		if ( isset( $this->reported_corrupt_batches[ $key ] ) ) {
			return;
		}

		$this->reported_corrupt_batches[ $key ] = true;

		if ( null === $raw ) {
			list( $table, $column, $value_column ) = $this->get_batch_table();

			$raw = $wpdb->get_var(
				$wpdb->prepare( "SELECT {$value_column} FROM {$table} WHERE {$column} = %s", $key ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}

		if ( null === $raw ) {
			return;
		}

		cp_sync()->logging->log( 'Corrupt batch is ' . strlen( $raw ) . ' bytes, is_serialized: ' . ( is_serialized( $raw ) ? 'true' : 'false' ) );
		cp_sync()->logging->log( 'Corrupt batch starts: ' . substr( $raw, 0, 200 ) );

		// Keep the most recent corrupt batch for inspection. One option per integration
		// so repeated failures cannot grow unbounded.
		update_option( "cp_sync_corrupt_batch_{$this->action}", $raw, false );
	}

	/**
	 * The table and columns the queue is stored in, matching WP_Background_Process.
	 *
	 * @return array [ table, key column, value column ]
	 */
	protected function get_batch_table() {
		global $wpdb;

		if ( is_multisite() ) {
			return [ $wpdb->sitemeta, 'meta_key', 'meta_value' ];
		}

		return [ $wpdb->options, 'option_name', 'option_value' ];
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
		// Handle Planning Center URLs specially - use the 'key' parameter as the unique identifier
		if ( strpos( $url, 'planningcenterusercontent.com' ) !== false ) {
			$parsed = parse_url( $url );
			if ( isset( $parsed['query'] ) ) {
				parse_str( $parsed['query'], $params );

				// Use the 'key' parameter which is the unique identifier for PCO images
				if ( isset( $params['key'] ) ) {
					return $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'] . '?key=' . $params['key'];
				}
			}
		}

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
		if ( get_post_thumbnail_id( $id ) && get_post_meta( $id, '_thumbnail_url', true ) == $thumbnail_url ) {
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

	/**
	 * Check if an attachment with this normalized URL already exists
	 *
	 * @param string $normalized_url The normalized thumbnail URL
	 * @return int|false The attachment ID if found, false otherwise
	 * @since  1.2.0
	 * @author Tanner Moushey
	 */
	public function get_existing_attachment( $normalized_url ) {
		global $wpdb;

		// Query for attachments with this normalized URL
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM $wpdb->postmeta
			WHERE meta_key = '_cp_sync_normalized_url'
			AND meta_value = %s
			LIMIT 1",
			$normalized_url
		) );

		// Verify the attachment still exists
		if ( $attachment_id && get_post( $attachment_id ) ) {
			return (int) $attachment_id;
		}

		return false;
	}

	public function sideload_image( $item, $id ) {
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$thumbnail_url = $item['thumbnail_url'];
		$normalized_url = $this->normalize_thumbnail_url( $thumbnail_url );

		// Check if we already have this image in the media library
		$existing_attachment = $this->get_existing_attachment( $normalized_url );
		if ( $existing_attachment ) {
			return $existing_attachment;
		}

		$file_name     = sanitize_title( $item['post_title'] ) . '-' . $id . '-' . md5( $normalized_url );

		// Validate URL
		if ( ! filter_var( $thumbnail_url, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error( 'invalid_url', __( 'The provided thumbnail URL is invalid.', 'cp-sync' ) );
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return new \WP_Error( 'upload_error', __( 'Unable to retrieve upload directory.', 'cp-sync' ) );
		}

		// Ensure the directory exists
		$image_cache_path = trailingslashit( $upload_dir['basedir'] ) . $this->image_cache_dir;
		if ( ! file_exists( $image_cache_path ) && ! wp_mkdir_p( $image_cache_path ) ) {
			return new \WP_Error( 'directory_creation_failed', __( 'Failed to create cache directory.', 'cp-sync' ) );
		}
		chmod( $image_cache_path, 0755 );

		// Retrieve the image from the URL
		$response = wp_remote_get( $thumbnail_url, [
			'timeout'   => 10,
			'sslverify' => true,
		] );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'http_request_failed', __( 'Failed to fetch the image from the URL.', 'cp-sync' ) );
		}

		// Retrieve image content
		$image_content = wp_remote_retrieve_body( $response );

		// Save the image temporarily
		$temp_file_name = sanitize_file_name( $file_name . '.tmp' );
		$save_path     = trailingslashit( $image_cache_path ) . $temp_file_name;

		if ( false === file_put_contents( $save_path, $image_content ) ) {
			return new \WP_Error( 'file_save_failed', __( 'Failed to save the image locally.', 'cp-sync' ) );
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

			return new \WP_Error( 'unsupported_mime_type', __( 'The MIME type is not supported.', 'cp-sync' ) );
		}

		// Rename the file with the correct extension
		$extension      = $supported_mime_types[ $mime_type ];
		$final_file_name  = sanitize_file_name( $file_name . '.' . $extension );
		$final_save_path = trailingslashit( $image_cache_path ) . $final_file_name;
		rename( $save_path, $final_save_path );

		// Validate saved file
		if ( ! getimagesize( $final_save_path ) ) {
			unlink( $final_save_path );

			return new \WP_Error( 'invalid_image', __( 'The saved file is not a valid image.', 'cp-sync' ) );
		}

		// Prepare attachment data
		$attachment_data = [
			'post_mime_type' => $mime_type,
			'post_title'     => sanitize_text_field( $item['post_title'] . ' Thumbnail' ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		// Insert attachment into the WordPress Media Library
		$attachment_id = wp_insert_attachment( $attachment_data, $final_save_path, $id );

		if ( is_wp_error( $attachment_id ) ) {
			unlink( $final_save_path );

			return $attachment_id;
		}

		// Generate attachment metadata and update
		$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $final_save_path );
		wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

		// Store the normalized URL so we can reuse this image for other posts
		update_post_meta( $attachment_id, '_cp_sync_normalized_url', $normalized_url );

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
	 * Remove every piece of content this integration has imported.
	 *
	 * Deletes all posts of this integration's post type that carry a `_chms_id`
	 * (via the existing public remove_item() helper) and every taxonomy this
	 * integration registered (via remove_taxonomy(), which also deletes the
	 * taxonomy's terms and its stored definition).
	 *
	 * This lives on the Integration rather than in the Reset service because the
	 * post type ( protected $post_type ) and the taxonomy definitions
	 * ( cp_sync_taxonomies_{id} ) are the integration's own encapsulated state.
	 * Exposing a single thin wrapper keeps that knowledge here and avoids widening
	 * the public surface of the lower-level helpers.
	 *
	 * @since 1.0.0
	 * @return array{items:int,terms:int,taxonomies:int} Counts of what was removed.
	 */
	public function remove_all_content() {
		global $wpdb;

		$summary = [
			'items'      => 0,
			'terms'      => 0,
			'taxonomies' => 0,
		];

		$chms_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT pm.meta_value
			FROM $wpdb->postmeta pm
			JOIN $wpdb->posts p ON pm.post_id = p.ID
			WHERE pm.meta_key = '_chms_id'
			AND p.post_type = %s",
			$this->post_type
		) );

		foreach ( array_unique( $chms_ids ) as $chms_id ) {
			$this->remove_item( $chms_id );
			$summary['items']++;
		}

		$taxonomies = get_option( "cp_sync_taxonomies_{$this->id}", [] );

		foreach ( array_keys( (array) $taxonomies ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				$terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );

				if ( ! is_wp_error( $terms ) ) {
					$summary['terms'] += count( $terms );
				}
			}

			// remove_taxonomy() deletes the terms, the stored definition, and unregisters it.
			$this->remove_taxonomy( $taxonomy );
			$summary['taxonomies']++;
		}

		return $summary;
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

		// Only delete the image if nothing else is using it. Sideloaded attachments are
		// deduplicated by source URL, so this one may equally be the featured image of
		// another synced post — deleting it with this post would break that one.
		// $id is excluded from the check because it still references the attachment here.
		if ( $thumb = get_post_thumbnail_id( $id ) ) {
			if ( ! \CP_Sync\Setup\Convenience::attachment_in_use( $thumb, $id ) ) {
				wp_delete_attachment( $thumb, true );
			}
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
		$chms_ids = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.meta_value AS chms_id, pm.post_id
			FROM $wpdb->postmeta pm
			JOIN $wpdb->posts p ON pm.post_id = p.ID
			WHERE pm.meta_key = '_chms_id'
			AND p.post_type = %s",
			$this->post_type
		) );

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
	 * Whether the imported post for a ChMS id is locked against sync.
	 *
	 * A locked post ( see LOCK_META_KEY ) has been customized by an admin who does
	 * not want sync to update or remove it. Returns false for items that have not
	 * been imported yet ( no post to lock ).
	 *
	 * @param string $chms_id The ChMS id.
	 * @return bool
	 */
	public function is_locked( $chms_id ) {
		$post_id = $this->get_chms_item_id( $chms_id );

		if ( ! $post_id ) {
			return false;
		}

		$locked = (bool) get_post_meta( $post_id, self::LOCK_META_KEY, true );

		/**
		 * Filter whether an imported post is locked against sync.
		 *
		 * @param bool        $locked      Whether the post is locked.
		 * @param int         $post_id     The post id.
		 * @param string      $chms_id     The ChMS id.
		 * @param Integration $integration The integration instance.
		 * @since 1.0.0
		 */
		return (bool) apply_filters( 'cp_sync_item_is_locked', $locked, $post_id, $chms_id, $this );
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
	 * @param array $retain Existing chms_id => hash entries to carry forward for items
	 *                      that are no longer in the fetch but were deliberately kept.
	 * @since  1.0.0
	 * @updated 1.1.0 - Added group parameter
	 * @author Tanner Moushey
	 */
	public function update_store( $items, $group = null, $retain = [] ) {
		$store = [];

		foreach( $items as $item ) {
			$store[ $item['chms_id'] ] = $this->create_store_key( $item );
		}

		// Union, NOT array_merge(): ChMS IDs are numeric ( PCO instance IDs, CCB event
		// IDs ), so PHP stores them as integer keys and array_merge() would renumber
		// them 0,1,2… — silently destroying the chms_id => hash mapping this store
		// exists to hold. `+` preserves every key, and the left operand wins, so a
		// freshly fetched hash beats a retained one.
		$store = $store + $retain;

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
		 * @since 1.0.0
		 */
		do_action( "cp_sync_load_taxonomy_{$this->id}", $taxonomy, $args );

		$this->register_taxonomy( $taxonomy, $args );
	}

	/**
	 * Load all taxonomies.
	 * @since 1.0.0
	 */
	public function load_taxonomies() {
		$taxonomies = get_option( "cp_sync_taxonomies_{$this->id}", [] );

		foreach( $taxonomies as $data ) {
			$this->load_taxonomy( $data['taxonomy'] );
		}
	}
}