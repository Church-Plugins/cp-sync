<?php
/**
 * Uninstall cleanup for CP Sync.
 *
 * Runs when WordPress deletes the plugin, and ONLY deletes anything when the site
 * has opted in via the "Delete all data on uninstall" toggle
 * ( cp_sync_settings['deleteDataOnUninstall'] ). With the toggle off — its default,
 * and the state of any site that never touched it — this file returns immediately
 * and nothing destructive happens.
 *
 * -----------------------------------------------------------------------------
 * WHY THIS FILE IS SELF-CONTAINED ( do not "simplify" it into Reset::reset_all() )
 * -----------------------------------------------------------------------------
 * uninstall.php runs standalone: WordPress core is loaded, but THE PLUGIN IS NOT
 * BOOTED. No `init` hooks fire, so \CP_Sync\Integrations\_Init and
 * \CP_Sync\ChMS\_Init have EMPTY registries. \CP_Sync\Setup\Reset::reset_all()
 * iterates those registries to find the queue, content and connection to clear, so
 * calling it here would silently no-op those steps. Everything below is therefore
 * hand-rolled, direct deletion that depends on nothing the plugin registers at run
 * time.
 *
 * The one pure-static helper worth reusing from Reset ( state_option_prefixes() )
 * lists `cp_sync_store_`, `cp_sync_taxonomies_` and `cp_sync_corrupt_batch_` — every
 * entry begins with `cp_sync_`, so the broad `cp_sync_%` sweep below already deletes
 * all of them. Reusing that finer list ( which would mean requiring the autoloader )
 * would be redundant, not DRY, and would trade this file's valuable self-containment
 * for nothing. The pattern lists are kept in sync by convention: any new persisted
 * name that is NOT `cp_sync_%` / `wp_pull_%` must be added here as well as to Reset.
 *
 * Multisite: this file iterates sites for per-site data ( options, content, uploads,
 * cron ) and clears network-level queue/status/lock artifacts ( which live in
 * wp_sitemeta ) once. Site iteration is capped ( see CP_SYNC_UNINSTALL_MAX_SITES )
 * so an enormous network cannot make a single uninstall request run unbounded; larger
 * networks should reset per site with `wp cp-sync reset --level=all` before deleting.
 *
 * The whole run is wrapped so a stray exception cannot fatal the uninstall ( which
 * would abort plugin deletion and show the operator an ugly error ).
 *
 * @package CP_Sync
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Opt-in gate. Read the global settings once and bail unless the toggle is truthy.
 *
 * On multisite this reads the current ( main ) site's settings and treats the choice
 * as network-wide: uninstalling the plugin is a network action, so one deliberate
 * opt-in governs the whole delete.
 */
$cp_sync_settings = get_option( 'cp_sync_settings', array() );

if ( empty( $cp_sync_settings['deleteDataOnUninstall'] ) ) {
	return;
}

/**
 * Defensive cap on how many sites a single uninstall request will walk. get_sites()
 * defaults to 100; this raises it while still bounding the work.
 */
if ( ! defined( 'CP_SYNC_UNINSTALL_MAX_SITES' ) ) {
	define( 'CP_SYNC_UNINSTALL_MAX_SITES', 10000 );
}

/**
 * Delete every wp_options row whose name begins with $prefix.
 *
 * delete_option() ( not a raw DELETE ) so the object cache is invalidated. Only the
 * trailing wildcard is a wildcard — the prefix is run through esc_like().
 *
 * @param wpdb   $wpdb   WordPress database handle.
 * @param string $prefix Literal option-name prefix.
 * @return void
 */
function cp_sync_uninstall_delete_options_like( $wpdb, $prefix ) {
	$like = $wpdb->esc_like( $prefix ) . '%';

	$names = $wpdb->get_col( $wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$like
	) );

	foreach ( $names as $name ) {
		delete_option( $name );
	}
}

/**
 * Delete every network ( site ) option in wp_sitemeta whose name begins with $prefix.
 *
 * On multisite the background-process queue and its status flags live in wp_sitemeta,
 * not wp_options. delete_site_option() keeps the object cache consistent.
 *
 * @param wpdb   $wpdb   WordPress database handle.
 * @param string $prefix Literal option-name prefix.
 * @return void
 */
function cp_sync_uninstall_delete_network_options_like( $wpdb, $prefix ) {
	if ( ! is_multisite() || empty( $wpdb->sitemeta ) ) {
		return;
	}

	$like = $wpdb->esc_like( $prefix ) . '%';

	$names = $wpdb->get_col( $wpdb->prepare(
		"SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
		$like
	) );

	foreach ( $names as $name ) {
		delete_site_option( $name );
	}
}

/**
 * Delete the process-lock site transients matching wp_pull_%.
 *
 * Single site: the `_site_transient_wp_pull_%` rows live in wp_options. Multisite
 * network: they live in wp_sitemeta. Either way we resolve the transient NAME and
 * call delete_site_transient(), which removes the value and its `_timeout_` companion
 * and clears the cache. Timeout rows are skipped so we never double-handle them.
 *
 * @param wpdb $wpdb WordPress database handle.
 * @return void
 */
function cp_sync_uninstall_delete_pull_lock_transients( $wpdb ) {
	$like = '_site_transient_' . $wpdb->esc_like( 'wp_pull_' ) . '%';

	if ( is_multisite() && ! empty( $wpdb->sitemeta ) ) {
		$keys = $wpdb->get_col( $wpdb->prepare(
			"SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
			$like
		) );
	} else {
		$keys = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$like
		) );
	}

	foreach ( $keys as $key ) {
		if ( 0 === strpos( $key, '_site_transient_timeout_' ) ) {
			continue;
		}

		delete_site_transient( substr( $key, strlen( '_site_transient_' ) ) );
	}
}

/**
 * Delete imported posts for the given post types — ONLY those carrying _chms_id.
 *
 * `tribe_events` is SHARED with The Events Calendar, so deleting by post type alone
 * would destroy events the user created by hand. The _chms_id postmeta is the only
 * marker of a CP-Sync-owned item, so it is the hard gate here. wp_delete_post( id,
 * true ) is used ( not raw SQL ) so postmeta, term relationships and the like clean
 * up too. Deletion is chunked so a large install survives, and because each delete
 * removes the _chms_id row the query naturally drains to empty.
 *
 * @param wpdb     $wpdb       WordPress database handle.
 * @param string[] $post_types Post types to purge.
 * @return void
 */
function cp_sync_uninstall_delete_tagged_posts( $wpdb, $post_types ) {
	foreach ( $post_types as $post_type ) {
		do {
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				WHERE p.post_type = %s
				LIMIT 200",
				'_chms_id',
				$post_type
			) );

			foreach ( $ids as $id ) {
				wp_delete_post( (int) $id, true );
			}
		} while ( ! empty( $ids ) );
	}
}

/**
 * Delete imported terms — ONLY those carrying _chms_id term meta.
 *
 * Same reasoning as posts: shared taxonomies ( e.g. tribe_events_cat ) may hold terms
 * the user created, so only CP-Sync-tagged terms are removed. wp_delete_term() cleans
 * up term meta and relationships. Chunked and self-draining.
 *
 * @param wpdb $wpdb WordPress database handle.
 * @return void
 */
function cp_sync_uninstall_delete_tagged_terms( $wpdb ) {
	do {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT tt.term_id, tt.taxonomy
			FROM {$wpdb->term_taxonomy} tt
			INNER JOIN {$wpdb->termmeta} tm ON tm.term_id = tt.term_id AND tm.meta_key = %s
			LIMIT 200",
			'_chms_id'
		) );

		foreach ( $rows as $row ) {
			wp_delete_term( (int) $row->term_id, $row->taxonomy );
		}
	} while ( ! empty( $rows ) );
}

/**
 * Delete the attachments created when images were sideloaded during import.
 *
 * They carry the _cp_sync_normalized_url meta ( see Integration::sideload_image() ),
 * so they can be found regardless of which post they hung off. wp_delete_attachment(
 * id, true ) removes the files as well as the attachment post.
 *
 * @param wpdb $wpdb WordPress database handle.
 * @return void
 */
function cp_sync_uninstall_delete_sideloaded_attachments( $wpdb ) {
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
		'_cp_sync_normalized_url'
	) );

	foreach ( $ids as $id ) {
		wp_delete_attachment( (int) $id, true );
	}
}

/**
 * Recursively delete a directory and everything under it.
 *
 * @param string $path Absolute path.
 * @return void
 */
function cp_sync_uninstall_remove_directory( $path ) {
	if ( ! is_dir( $path ) ) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );
		} else {
			wp_delete_file( $item->getPathname() );
		}
	}

	rmdir( $path );
}

/**
 * Remove the uploads image-cache directory ( {uploads}/cp-sync ) for the current site.
 *
 * @return void
 */
function cp_sync_uninstall_remove_image_cache_dir() {
	$uploads = wp_upload_dir();

	if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
		return;
	}

	// The plugin is not booted, so this filter has no listeners and returns the
	// 'cp-sync' default; it is applied only to honour any mu-plugin override.
	$dir = apply_filters( 'cp_sync_image_cache_dir', 'cp-sync' );

	cp_sync_uninstall_remove_directory( trailingslashit( $uploads['basedir'] ) . $dir );
}

/**
 * Clean up everything that belongs to the CURRENT site ( blog ).
 *
 * @return void
 */
function cp_sync_uninstall_current_site() {
	global $wpdb;

	// 1. Options: all plugin options, the connection-test message, and — on single
	//    site — the queue/status options ( on multisite those live in wp_sitemeta and
	//    are handled once by cp_sync_uninstall_network() ).
	cp_sync_uninstall_delete_options_like( $wpdb, 'cp_sync_' );
	delete_option( 'cp_settings_message' );
	cp_sync_uninstall_delete_options_like( $wpdb, 'wp_pull_' );

	// 2. Process-lock transients ( single site only; network locks handled once ).
	if ( ! is_multisite() ) {
		cp_sync_uninstall_delete_pull_lock_transients( $wpdb );
	}

	// 3. Imported content — only items/terms tagged with _chms_id.
	cp_sync_uninstall_delete_tagged_posts( $wpdb, array( 'cp_group', 'tribe_events' ) );
	cp_sync_uninstall_delete_tagged_terms( $wpdb );

	// 4. Sideloaded attachments + the on-disk image cache directory.
	cp_sync_uninstall_delete_sideloaded_attachments( $wpdb );
	cp_sync_uninstall_remove_image_cache_dir();

	// 4b. The debug log file. ChurchPlugins\Logging writes it to the uploads
	// basedir as {wp_hash(home_url('/'))}-cp-sync.log — reproduced here because
	// the plugin (and its Logging instance) is not booted during uninstall.
	// home_url() varies per site, so this is correctly per-site under
	// switch_to_blog().
	$cp_sync_uploads = wp_upload_dir();
	if ( empty( $cp_sync_uploads['error'] ) && ! empty( $cp_sync_uploads['basedir'] ) ) {
		$cp_sync_log_file = trailingslashit( $cp_sync_uploads['basedir'] ) . wp_hash( home_url( '/' ) ) . '-cp-sync.log';
		if ( file_exists( $cp_sync_log_file ) ) {
			wp_delete_file( $cp_sync_log_file );
		}
	}

	// 5. Scheduled events ( WP-Cron is per-site ).
	foreach ( array( 'cp_sync_pull', 'wp_pull_groups_cron', 'wp_pull_events_cron' ) as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
}

/**
 * Clean up the network-level artifacts that live in wp_sitemeta ( multisite only ):
 * the background-process batches + status flags and the process-lock site transients.
 *
 * @return void
 */
function cp_sync_uninstall_network() {
	global $wpdb;

	cp_sync_uninstall_delete_network_options_like( $wpdb, 'wp_pull_' );
	cp_sync_uninstall_delete_pull_lock_transients( $wpdb );
}

// -----------------------------------------------------------------------------
// Run. Wrapped so nothing here can fatal the uninstall.
// -----------------------------------------------------------------------------
try {
	if ( is_multisite() ) {
		$site_ids = get_sites(
			array(
				'number' => CP_SYNC_UNINSTALL_MAX_SITES,
				'fields' => 'ids',
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );

			try {
				cp_sync_uninstall_current_site();
			} catch ( \Throwable $e ) {
				// Keep going: one broken site must not block the rest of the cleanup.
			}

			restore_current_blog();
		}

		cp_sync_uninstall_network();
	} else {
		cp_sync_uninstall_current_site();
	}
} catch ( \Throwable $e ) {
	// Swallow: a failed cleanup must never abort the plugin's own deletion.
}
