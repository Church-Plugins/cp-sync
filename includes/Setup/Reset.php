<?php
/**
 * Reset / clear CP-Sync install data.
 *
 * Provides one supported way to return an install to a known state at selectable
 * levels. Each level composes the ones below it where it makes sense:
 *
 *   queue      -> clear_queue()      background-process batches, status flags,
 *                                    process locks, health-check crons.
 *   state      -> reset_sync_state() queue + sync-state options
 *                                    (cp_sync_store_%, cp_sync_taxonomies_%,
 *                                    cp_sync_corrupt_batch_%).
 *   content    -> reset_content()    imported posts/terms/taxonomies + sideloaded
 *                                    image attachments and their cache directory.
 *   connection -> reset_connection() per-ChMS credential/token options,
 *                                    connection-test message, active-ChMS selection.
 *   all        -> reset_all()        state + content + connection + cp_sync_settings
 *                                    + all scheduled events.
 *
 * The queue lives in wp_sitemeta on multisite, so it is only ever touched through
 * the Integration API ( get_batches() / delete() / get_identifier() ), never with
 * raw delete_option().
 *
 * NOTE ON uninstall.php: reset_all() CANNOT be reused from uninstall.php. That file
 * runs standalone with the plugin un-booted, so no `init` hooks fire and the
 * Integrations\_Init / ChMS\_Init registries this service iterates are EMPTY — a
 * call would silently no-op the queue/content/connection steps. uninstall.php is
 * therefore deliberately self-contained direct deletion. Only PURE-STATIC helpers
 * here ( e.g. state_option_prefixes() ) are safe to share with it; anything that
 * depends on runtime registration is not.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Setup;

use CP_Sync\ChMS\_Init as ChMS_Init;
use CP_Sync\Integrations\_Init as Integrations_Init;

/**
 * Reset service.
 */
class Reset {

	/**
	 * The selectable reset levels, ordered least to most destructive.
	 *
	 * @var string[]
	 */
	const LEVELS = [ 'queue', 'state', 'content', 'connection', 'all' ];

	/**
	 * Return the selectable levels.
	 *
	 * @return string[]
	 */
	public static function levels() {
		return self::LEVELS;
	}

	/**
	 * Is the given value a known reset level?
	 *
	 * @param mixed $level The level to check.
	 * @return bool
	 */
	public static function is_valid_level( $level ) {
		return is_string( $level ) && in_array( $level, self::LEVELS, true );
	}

	/**
	 * Does the retyped confirmation strictly match the level?
	 *
	 * The confirmation must be the level string, retyped exactly. A non-string
	 * ( e.g. a missing param arriving as null ) never matches.
	 *
	 * @param mixed $level   The requested level.
	 * @param mixed $confirm The confirmation value supplied by the caller.
	 * @return bool
	 */
	public static function confirm_matches( $level, $confirm ) {
		return is_string( $confirm ) && $level === $confirm;
	}

	/**
	 * Map a level to the service method that performs it.
	 *
	 * @param mixed $level The level.
	 * @return string|null The method name, or null for an unknown level.
	 */
	public static function level_method( $level ) {
		$map = [
			'queue'      => 'clear_queue',
			'state'      => 'reset_sync_state',
			'content'    => 'reset_content',
			'connection' => 'reset_connection',
			'all'        => 'reset_all',
		];

		return isset( $map[ $level ] ) ? $map[ $level ] : null;
	}

	/**
	 * The wp_options name prefixes wiped by a sync-state reset.
	 *
	 * Matched by wildcard so ChMS-driven, changeable names ( per-type stores,
	 * per-taxonomy stores, taxonomy definitions, quarantined batches ) are all
	 * covered without enumerating individual slugs.
	 *
	 * @return string[]
	 */
	public static function state_option_prefixes() {
		return [
			'cp_sync_store_',
			'cp_sync_taxonomies_',
			'cp_sync_corrupt_batch_',
		];
	}

	/**
	 * Run a reset at the given level.
	 *
	 * Callers are expected to validate the level first ( see is_valid_level() ).
	 *
	 * @param string $level The level to run.
	 * @return array|null The summary, or null for an unknown level.
	 */
	public function run( $level ) {
		$method = self::level_method( $level );

		if ( ! $method ) {
			return null;
		}

		return $this->{$method}();
	}

	/**
	 * Level: queue.
	 *
	 * Clear the background-process queue for every registered integration: its
	 * batches, its paused/cancelled status flag, its process lock ( a site
	 * transient — a stale one blocks the next run ), and its health-check cron.
	 *
	 * @return array Summary counts keyed by step.
	 */
	public function clear_queue() {
		$summary = [
			'batches'      => 0,
			'status_flags' => 0,
			'locks'        => 0,
			'healthchecks' => 0,
		];

		foreach ( $this->get_integrations() as $integration ) {
			$identifier = $integration->get_identifier();

			// Batches — read and delete through the integration so they are located
			// and removed wherever they live ( wp_sitemeta on multisite ).
			foreach ( $integration->get_batches() as $batch ) {
				$integration->delete( $batch->key );
				$summary['batches']++;
			}

			// Status flag ( "{$identifier}_status" ) is a site option.
			if ( delete_site_option( $identifier . '_status' ) ) {
				$summary['status_flags']++;
			}

			// Process lock is a SITE TRANSIENT, not an option.
			if ( delete_site_transient( $identifier . '_process_lock' ) ) {
				$summary['locks']++;
			}

			// Health-check cron re-dispatches the queue; clear it too.
			$hook = $identifier . '_cron';
			if ( wp_next_scheduled( $hook ) ) {
				wp_clear_scheduled_hook( $hook );
				$summary['healthchecks']++;
			}
		}

		return $summary;
	}

	/**
	 * Level: state.
	 *
	 * Everything clear_queue() does, plus the sync-state options so the next pull
	 * re-imports everything ( existing posts update in place, matched by _chms_id ).
	 *
	 * @return array Summary, with the queue summary nested under 'queue'.
	 */
	public function reset_sync_state() {
		$summary = [
			'queue'   => $this->clear_queue(),
			'options' => [],
		];

		foreach ( self::state_option_prefixes() as $prefix ) {
			$summary['options'][ $prefix . '%' ] = $this->delete_options_like( $prefix );
		}

		return $summary;
	}

	/**
	 * Level: content.
	 *
	 * Delete the imported posts, terms and taxonomies for every integration, plus
	 * the sideloaded image attachments and the on-disk image cache directory.
	 *
	 * @return array Summary counts keyed by step.
	 */
	public function reset_content() {
		$summary = [
			'items'       => 0,
			'terms'       => 0,
			'taxonomies'  => 0,
			'attachments' => 0,
			'files'       => 0,
		];

		foreach ( $this->get_integrations() as $integration ) {
			$result = $integration->remove_all_content();

			$summary['items']      += $result['items'];
			$summary['terms']      += $result['terms'];
			$summary['taxonomies'] += $result['taxonomies'];
		}

		$summary['attachments'] = $this->delete_sideloaded_attachments();
		$summary['files']       = $this->remove_image_cache_dir();

		return $summary;
	}

	/**
	 * Level: connection.
	 *
	 * Remove every registered ChMS's credential/token option, the last
	 * connection-test message, and the active-ChMS selection.
	 *
	 * The active ChMS is NOT stored in its own option — it lives inside
	 * cp_sync_settings under the 'chms' key ( cp_sync_active_chms is only a filter
	 * name ). Unsetting that key reverts the selection to its default without
	 * discarding the rest of the global settings, which are the domain of
	 * reset_all().
	 *
	 * @return array Summary counts keyed by step.
	 */
	public function reset_connection() {
		$summary = [
			'chms_settings'      => 0,
			'connection_message' => false,
			'active_chms_reset'  => false,
		];

		foreach ( $this->get_chms_list() as $chms ) {
			if ( empty( $chms->settings_key ) ) {
				continue;
			}

			if ( delete_option( $chms->settings_key ) ) {
				$summary['chms_settings']++;
			}
		}

		$summary['connection_message'] = delete_option( 'cp_settings_message' );

		$settings = get_option( 'cp_sync_settings', [] );

		if ( is_array( $settings ) && array_key_exists( 'chms', $settings ) ) {
			unset( $settings['chms'] );
			update_option( 'cp_sync_settings', $settings );
			$summary['active_chms_reset'] = true;
		}

		return $summary;
	}

	/**
	 * Level: all.
	 *
	 * State ( which includes the queue ), content and connection, plus the global
	 * settings option and every scheduled event. Cron MUST be cleared or a
	 * scheduled run would immediately repopulate the state we just wiped.
	 *
	 * @return array Summary, with each lower level nested under its own key.
	 */
	public function reset_all() {
		$summary = [
			'state'      => $this->reset_sync_state(),
			'content'    => $this->reset_content(),
			'connection' => $this->reset_connection(),
			'settings'   => false,
			'cron'       => 0,
		];

		$summary['settings'] = delete_option( 'cp_sync_settings' );

		// Recurring content pull.
		if ( wp_next_scheduled( Integrations_Init::$_cron_hook ) ) {
			wp_clear_scheduled_hook( Integrations_Init::$_cron_hook );
			$summary['cron']++;
		}

		// Background-process health-checks ( also cleared by clear_queue(); repeated
		// here so a full reset is complete even if an integration is not registered
		// at queue-clear time ).
		foreach ( $this->get_integrations() as $integration ) {
			$hook = $integration->get_identifier() . '_cron';
			if ( wp_next_scheduled( $hook ) ) {
				wp_clear_scheduled_hook( $hook );
				$summary['cron']++;
			}
		}

		return $summary;
	}

	/**
	 * Registered integrations.
	 *
	 * @return \CP_Sync\Integrations\Integration[]
	 */
	protected function get_integrations() {
		return Integrations_Init::get_instance()->get_integrations();
	}

	/**
	 * Registered ChMS integrations, keyed by id.
	 *
	 * @return \CP_Sync\ChMS\ChMS[]
	 */
	protected function get_chms_list() {
		return ChMS_Init::$supported_chms;
	}

	/**
	 * Delete every wp_option whose name begins with the given prefix.
	 *
	 * Uses delete_option() ( not a raw DELETE ) so the object cache is invalidated.
	 * The lookup is a prepared LIKE with the prefix run through esc_like() so only
	 * the trailing wildcard is treated as a wildcard.
	 *
	 * @param string $prefix The literal option-name prefix.
	 * @return int Number of options deleted.
	 */
	protected function delete_options_like( $prefix ) {
		global $wpdb;

		$like = $wpdb->esc_like( $prefix ) . '%';

		$names = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
			$like
		) );

		foreach ( $names as $name ) {
			delete_option( $name );
		}

		return count( $names );
	}

	/**
	 * Delete the attachments created when images were sideloaded during import.
	 *
	 * They are tagged with the _cp_sync_normalized_url meta ( see
	 * Integration::sideload_image() ), so they can be found regardless of which
	 * post they were attached to. wp_delete_attachment( $id, true ) removes the
	 * files as well as the attachment post.
	 *
	 * @return int Number of attachments deleted.
	 */
	protected function delete_sideloaded_attachments() {
		global $wpdb;

		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s",
			'_cp_sync_normalized_url'
		) );

		$count = 0;

		foreach ( $ids as $id ) {
			if ( wp_delete_attachment( (int) $id, true ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Remove the uploads image cache directory ( {uploads}/cp-sync ) and its files.
	 *
	 * @return int Number of files removed.
	 */
	protected function remove_image_cache_dir() {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return 0;
		}

		$dir  = apply_filters( 'cp_sync_image_cache_dir', 'cp-sync' );
		$path = trailingslashit( $uploads['basedir'] ) . $dir;

		return $this->remove_directory( $path );
	}

	/**
	 * Recursively delete a directory and everything in it.
	 *
	 * @param string $path Absolute path.
	 * @return int Number of files ( not directories ) removed.
	 */
	protected function remove_directory( $path ) {
		if ( ! is_dir( $path ) ) {
			return 0;
		}

		$count = 0;

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
				$count++;
			}
		}

		rmdir( $path );

		return $count;
	}
}
