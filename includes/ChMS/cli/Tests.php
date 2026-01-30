<?php
/**
 * WP CLI integration tests for CP-Sync
 *
 * @package CP_Sync
 * @since 1.2.0
 */

namespace CP_Sync\ChMS\cli;

use CP_Sync\ChMS\CCB;
use CP_Sync\Integrations\TEC;

// Make the `cp-sync test` command available to WP-CLI
if ( defined( '\WP_CLI' ) && \WP_CLI ) {
	\WP_CLI::add_command( 'cp-sync test', '\CP_Sync\ChMS\cli\Tests_CLI' );
}

/**
 * Integration tests for CP-Sync
 */
class Tests_CLI {

	/**
	 * Import a single event by ChMS ID
	 *
	 * ## OPTIONS
	 *
	 * <chms_id>
	 * : The ChMS ID of the event to import
	 *
	 * [--verbose]
	 * : Show detailed output including item data
	 *
	 * ## EXAMPLES
	 *
	 *     wp cp-sync test import-event 458fc45f7343d4070383ff067294b1fa
	 *     wp cp-sync test import-event 458fc45f7343d4070383ff067294b1fa --verbose
	 *
	 * @when after_wp_load
	 */
	public function import_event( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'ChMS ID is required' );
		}

		$chms_id = $args[0];
		$verbose = isset( $assoc_args['verbose'] );

		\WP_CLI::line( '═══════════════════════════════════════════════════' );
		\WP_CLI::line( 'Testing Single Event Import' );
		\WP_CLI::line( '═══════════════════════════════════════════════════' );
		\WP_CLI::line( "ChMS ID: {$chms_id}" );
		\WP_CLI::line( '' );

		// Get CCB instance
		$ccb = CCB::get_instance();

		// Fetch all events to find this one
		\WP_CLI::line( 'Fetching events from CCB...' );
		$result = $ccb->fetch_events();

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( 'Failed to fetch events: ' . $result->get_error_message() );
		}

		$events = $result['items'] ?? [];

		\WP_CLI::success( 'Fetched ' . count( $events ) . ' events' );
		\WP_CLI::line( '' );

		// Find the specific event
		$event_data = null;
		foreach ( $events as $event ) {
			if ( isset( $event['chms_id'] ) && $event['chms_id'] === $chms_id ) {
				$event_data = $event;
				break;
			}
		}

		if ( ! $event_data ) {
			\WP_CLI::error( "Event with ChMS ID {$chms_id} not found in API response" );
		}

		\WP_CLI::success( 'Found event: ' . ( $event_data['post_title'] ?? 'Unknown' ) );

		if ( $verbose ) {
			\WP_CLI::line( '' );
			\WP_CLI::line( 'Event Data:' );
			\WP_CLI::line( '─────────────────────────────────────────────────' );
			$this->print_data( $event_data );
			\WP_CLI::line( '' );
		}

		// Check if event already exists
		$existing_id = $this->get_existing_event_id( $chms_id );
		if ( $existing_id ) {
			\WP_CLI::line( "Event already exists with WordPress ID: {$existing_id}" );
			\WP_CLI::line( '' );
		}

		// Get TEC integration instance
		$tec = new TEC();

		\WP_CLI::line( 'Attempting to import event...' );
		\WP_CLI::line( '─────────────────────────────────────────────────' );

		// Enable error logging
		$original_error_reporting = error_reporting();
		error_reporting( E_ALL );

		try {
			// Call the update_item method directly
			$result = $tec->update_item( $event_data );

			if ( ! $result || is_wp_error( $result ) ) {
				$error_msg = is_wp_error( $result ) ? $result->get_error_message() : 'update_item returned false/null';
				\WP_CLI::error( "Import failed: {$error_msg}" );
			}

			\WP_CLI::line( '' );
			\WP_CLI::success( "Event imported successfully! WordPress ID: {$result}" );
			\WP_CLI::line( '' );

			// Show event details
			$post = get_post( $result );
			if ( $post ) {
				\WP_CLI::line( 'Event Details:' );
				\WP_CLI::line( "  Title: {$post->post_title}" );
				\WP_CLI::line( "  Status: {$post->post_status}" );
				\WP_CLI::line( "  URL: " . get_permalink( $result ) );

				// Get event dates
				$start_date = get_post_meta( $result, '_EventStartDate', true );
				$end_date = get_post_meta( $result, '_EventEndDate', true );
				if ( $start_date ) {
					\WP_CLI::line( "  Start: {$start_date}" );
				}
				if ( $end_date ) {
					\WP_CLI::line( "  End: {$end_date}" );
				}

				// Get categories
				$categories = wp_get_post_terms( $result, 'tribe_events_cat' );
				if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
					$cat_names = array_map( function( $cat ) { return $cat->name; }, $categories );
					\WP_CLI::line( '  Categories: ' . implode( ', ', $cat_names ) );
				}
			}

		} catch ( \Throwable $e ) {
			\WP_CLI::line( '' );
			\WP_CLI::error( sprintf(
				"Exception caught:\n  Type: %s\n  Message: %s\n  File: %s:%d\n  Trace:\n%s",
				get_class( $e ),
				$e->getMessage(),
				$e->getFile(),
				$e->getLine(),
				$e->getTraceAsString()
			) );
		} finally {
			error_reporting( $original_error_reporting );
		}
	}

	/**
	 * Process the event queue with detailed logging
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Maximum number of items to process (default: unlimited)
	 *
	 * [--verbose]
	 * : Show detailed output for each item
	 *
	 * ## EXAMPLES
	 *
	 *     wp cp-sync test process-queue
	 *     wp cp-sync test process-queue --limit=5
	 *     wp cp-sync test process-queue --verbose
	 *
	 * @when after_wp_load
	 */
	public function process_queue( $args, $assoc_args ) {
		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0;
		$verbose = isset( $assoc_args['verbose'] );

		\WP_CLI::line( '═══════════════════════════════════════════════════' );
		\WP_CLI::line( 'Processing Event Queue' );
		\WP_CLI::line( '═══════════════════════════════════════════════════' );
		\WP_CLI::line( '' );

		// Check for queued items
		global $wpdb;
		$batch_options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s ORDER BY option_name",
				'%wp_cp_sync_events_batch_%'
			)
		);

		if ( empty( $batch_options ) ) {
			\WP_CLI::warning( 'No items in queue. Run event sync first.' );
			return;
		}

		\WP_CLI::success( 'Found ' . count( $batch_options ) . ' batch(es) in queue' );

		// Count total items
		$total_items = 0;
		foreach ( $batch_options as $batch ) {
			$items = maybe_unserialize( $batch->option_value );
			if ( is_array( $items ) ) {
				$count = count( $items );
				$total_items += $count;
				\WP_CLI::line( "  - {$batch->option_name}: {$count} items" );
			}
		}
		\WP_CLI::line( "  Total items: {$total_items}" );
		\WP_CLI::line( '' );

		if ( $limit > 0 ) {
			\WP_CLI::line( "Processing limit: {$limit} items" );
			\WP_CLI::line( '' );
		}

		// Get TEC integration
		$tec = new TEC();

		// Process queue
		$processed = 0;
		$successful = 0;
		$failed = 0;
		$errors = [];

		\WP_CLI::line( 'Processing items...' );
		\WP_CLI::line( '─────────────────────────────────────────────────' );

		foreach ( $batch_options as $batch ) {
			$items = maybe_unserialize( $batch->option_value );
			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				if ( $limit > 0 && $processed >= $limit ) {
					\WP_CLI::line( '' );
					\WP_CLI::warning( 'Reached processing limit' );
					break 2;
				}

				$processed++;
				$chms_id = $item['chms_id'] ?? 'unknown';
				$title = $item['post_title'] ?? 'Unknown';

				\WP_CLI::line( sprintf( '[%d/%d] %s (ID: %s)', $processed, $total_items, $title, $chms_id ) );

				if ( $verbose ) {
					\WP_CLI::line( '  Data: ' . json_encode( array_keys( $item ) ) );
				}

				try {
					$result = $tec->task( $item );

					if ( false === $result ) {
						// Task returns false when complete
						$successful++;
						\WP_CLI::line( \WP_CLI::colorize( '  %G✓ Processed successfully%n' ) );
					} else {
						// Unexpected return value
						\WP_CLI::warning( "  Unexpected return value: " . var_export( $result, true ) );
					}

				} catch ( \Throwable $e ) {
					$failed++;
					$error_msg = sprintf(
						'%s: %s in %s:%d',
						get_class( $e ),
						$e->getMessage(),
						basename( $e->getFile() ),
						$e->getLine()
					);

					\WP_CLI::line( \WP_CLI::colorize( "%R  ✗ Error: {$error_msg}%n" ) );

					$errors[] = [
						'item' => $title,
						'chms_id' => $chms_id,
						'error' => $error_msg,
					];

					if ( $verbose ) {
						\WP_CLI::line( '  Stack trace:' );
						\WP_CLI::line( '  ' . str_replace( "\n", "\n  ", $e->getTraceAsString() ) );
					}
				}

				\WP_CLI::line( '' );
			}

			// Delete the processed batch if we completed it
			if ( $limit === 0 || $processed < $limit ) {
				delete_option( $batch->option_name );
				\WP_CLI::line( "Deleted batch: {$batch->option_name}" );
				\WP_CLI::line( '' );
			}
		}

		// Summary
		\WP_CLI::line( '═══════════════════════════════════════════════════' );
		\WP_CLI::line( 'Queue Processing Summary' );
		\WP_CLI::line( '═══════════════════════════════════════════════════' );
		\WP_CLI::line( "Total Processed: {$processed}" );
		\WP_CLI::line( \WP_CLI::colorize( "%GSuccessful: {$successful}%n" ) );
		\WP_CLI::line( \WP_CLI::colorize( "%RFailed: {$failed}%n" ) );

		if ( ! empty( $errors ) ) {
			\WP_CLI::line( '' );
			\WP_CLI::line( 'Failed Items:' );
			\WP_CLI::line( '─────────────────────────────────────────────────' );
			foreach ( $errors as $error ) {
				\WP_CLI::line( "  • {$error['item']} ({$error['chms_id']})" );
				\WP_CLI::line( "    {$error['error']}" );
			}
		}

		if ( $failed > 0 ) {
			\WP_CLI::error( "Queue processing completed with {$failed} errors" );
		} else {
			\WP_CLI::success( 'Queue processing completed successfully!' );
		}
	}

	/**
	 * Run a full sync test with progress monitoring
	 *
	 * ## OPTIONS
	 *
	 * [--skip-fetch]
	 * : Skip fetching events and only process existing queue
	 *
	 * [--limit=<number>]
	 * : Maximum number of items to process from queue
	 *
	 * ## EXAMPLES
	 *
	 *     wp cp-sync test full-sync
	 *     wp cp-sync test full-sync --skip-fetch
	 *     wp cp-sync test full-sync --limit=10
	 *
	 * @when after_wp_load
	 */
	public function full_sync( $args, $assoc_args ) {
		$skip_fetch = isset( $assoc_args['skip-fetch'] );
		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0;

		\WP_CLI::line( '═══════════════════════════════════════════════════' );
		\WP_CLI::line( 'Full Event Sync Test' );
		\WP_CLI::line( '═══════════════════════════════════════════════════' );
		\WP_CLI::line( '' );

		if ( ! $skip_fetch ) {
			// Step 1: Fetch events from API
			\WP_CLI::line( 'Step 1: Fetching events from CCB...' );
			\WP_CLI::line( '─────────────────────────────────────────────────' );

			$ccb = CCB::get_instance();
			$events = $ccb->fetch_events();

			if ( is_wp_error( $events ) ) {
				\WP_CLI::error( 'Failed to fetch events: ' . $events->get_error_message() );
			}

			\WP_CLI::success( 'Fetched ' . count( $events ) . ' events from API' );
			\WP_CLI::line( '' );

			// Step 2: Queue events for import
			\WP_CLI::line( 'Step 2: Queueing events for import...' );
			\WP_CLI::line( '─────────────────────────────────────────────────' );

			$init = \CP_Sync\Integrations\_Init::get_instance();
			$result = $init->pull_integration( 'events' );

			if ( is_wp_error( $result ) ) {
				\WP_CLI::error( 'Failed to queue events: ' . $result->get_error_message() );
			}

			\WP_CLI::success( 'Events queued successfully' );
			\WP_CLI::line( '' );
		}

		// Step 3: Process the queue
		\WP_CLI::line( 'Step 3: Processing event queue...' );
		\WP_CLI::line( '─────────────────────────────────────────────────' );
		\WP_CLI::line( '' );

		$process_args = [];
		if ( $limit > 0 ) {
			$process_args['limit'] = $limit;
		}

		$this->process_queue( [], $process_args );
	}

	/**
	 * Test the specific problematic event
	 *
	 * ## EXAMPLES
	 *
	 *     wp cp-sync test problem-event
	 *
	 * @when after_wp_load
	 */
	public function problem_event( $args, $assoc_args ) {
		$problematic_id = '458fc45f7343d4070383ff067294b1fa';

		\WP_CLI::line( '═══════════════════════════════════════════════════' );
		\WP_CLI::line( 'Testing Problematic Event' );
		\WP_CLI::line( '═══════════════════════════════════════════════════' );
		\WP_CLI::line( "ChMS ID: {$problematic_id}" );
		\WP_CLI::line( 'This event has been causing sync to hang' );
		\WP_CLI::line( '' );

		$this->import_event( [ $problematic_id ], [ 'verbose' => true ] );
	}

	/**
	 * Clear all event queue batches
	 *
	 * ## EXAMPLES
	 *
	 *     wp cp-sync test clear-queue
	 *
	 * @when after_wp_load
	 */
	public function clear_queue( $args, $assoc_args ) {
		\WP_CLI::line( 'Clearing event queue...' );
		\WP_CLI::line( '' );

		global $wpdb;
		$batch_options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
				'%wp_cp_sync_events_batch_%'
			)
		);

		if ( empty( $batch_options ) ) {
			\WP_CLI::warning( 'No batches found in queue' );
			return;
		}

		\WP_CLI::line( 'Found ' . count( $batch_options ) . ' batch(es)' );

		foreach ( $batch_options as $batch ) {
			delete_option( $batch->option_name );
			\WP_CLI::line( "  ✓ Deleted {$batch->option_name}" );
		}

		// Also clear the process lock
		delete_option( 'wp_cp_sync_events_process_lock' );

		\WP_CLI::line( '' );
		\WP_CLI::success( 'Queue cleared successfully!' );
	}

	/**
	 * Show queue status and contents
	 *
	 * ## OPTIONS
	 *
	 * [--detailed]
	 * : Show detailed information about each item
	 *
	 * ## EXAMPLES
	 *
	 *     wp cp-sync test queue-status
	 *     wp cp-sync test queue-status --detailed
	 *
	 * @when after_wp_load
	 */
	public function queue_status( $args, $assoc_args ) {
		$detailed = isset( $assoc_args['detailed'] );

		\WP_CLI::line( '═══════════════════════════════════════════════════' );
		\WP_CLI::line( 'Event Queue Status' );
		\WP_CLI::line( '═══════════════════════════════════════════════════' );
		\WP_CLI::line( '' );

		global $wpdb;
		$batch_options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s ORDER BY option_name",
				'%wp_cp_sync_events_batch_%'
			)
		);

		if ( empty( $batch_options ) ) {
			\WP_CLI::warning( 'No batches in queue' );
			return;
		}

		\WP_CLI::line( 'Batches: ' . count( $batch_options ) );
		\WP_CLI::line( '' );

		$total_items = 0;
		foreach ( $batch_options as $batch ) {
			$items = maybe_unserialize( $batch->option_value );
			if ( ! is_array( $items ) ) {
				continue;
			}

			$count = count( $items );
			$total_items += $count;

			\WP_CLI::line( "Batch: {$batch->option_name}" );
			\WP_CLI::line( "  Items: {$count}" );

			if ( $detailed ) {
				\WP_CLI::line( '  Contents:' );
				foreach ( $items as $idx => $item ) {
					$chms_id = $item['chms_id'] ?? 'unknown';
					$title = $item['post_title'] ?? 'Unknown';
					\WP_CLI::line( "    [{$idx}] {$title} (ID: {$chms_id})" );
				}
			}

			\WP_CLI::line( '' );
		}

		// Check for process lock
		$lock = get_option( 'wp_cp_sync_events_process_lock' );
		if ( $lock ) {
			\WP_CLI::line( 'Process Lock: Active' );
			\WP_CLI::line( "  Lock time: {$lock}" );
		} else {
			\WP_CLI::line( 'Process Lock: None' );
		}

		\WP_CLI::line( '' );
		\WP_CLI::line( '─────────────────────────────────────────────────' );
		\WP_CLI::success( "Total items in queue: {$total_items}" );
	}

	/**
	 * Get the WordPress ID of an existing event by ChMS ID
	 *
	 * @param string $chms_id ChMS ID to look up
	 * @return int|null WordPress post ID or null if not found
	 */
	private function get_existing_event_id( $chms_id ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'chms_id' AND meta_value = %s",
			$chms_id
		) );
	}

	/**
	 * Pretty print data
	 *
	 * @param mixed $data Data to print
	 */
	private function print_data( $data ) {
		if ( is_array( $data ) || is_object( $data ) ) {
			\WP_CLI::line( json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		} else {
			\WP_CLI::line( print_r( $data, true ) );
		}
	}
}
