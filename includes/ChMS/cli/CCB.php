<?php
/**
 * WP CLI commands for CCB integration debugging
 *
 * @package CP_Sync
 * @since 1.2.0
 */

namespace CP_Sync\ChMS\cli;

use CP_Sync\ChMS\CCB;

// Make the `cp-sync ccb` command available to WP-CLI
if ( defined( '\WP_CLI' ) && \WP_CLI ) {
	\WP_CLI::add_command( 'cp-sync ccb', '\CP_Sync\ChMS\cli\CCB_CLI' );
}

/**
 * CCB API debugging commands
 */
class CCB_CLI {

	/**
	 * Test CCB API connection and show raw response
	 *
	 * ## EXAMPLES
	 *
	 *     wp cp-sync ccb test-connection
	 *
	 * @when after_wp_load
	 */
	public function test_connection( $args, $assoc_args ) {
		$ccb = CCB::get_instance();

		\WP_CLI::line( 'Testing CCB connection...' );
		\WP_CLI::line( '' );

		// Test connection
		$result = $ccb->check_connection();

		if ( isset( $result['status'] ) && $result['status'] === 'success' ) {
			\WP_CLI::success( 'Connection successful!' );
		} else {
			\WP_CLI::error( 'Connection failed: ' . ( $result['message'] ?? 'Unknown error' ) );
		}

		// Get raw api_status response
		\WP_CLI::line( '' );
		\WP_CLI::line( 'Raw API Status Response:' );
		\WP_CLI::line( str_repeat( '-', 50 ) );

		$raw_data = $ccb->api()->get( 'api_status', [] );

		if ( is_wp_error( $raw_data ) ) {
			\WP_CLI::error( 'API Error: ' . $raw_data->get_error_message() );
		}

		$this->print_data( $raw_data );
	}

	/**
	 * Fetch groups from CCB API
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Number of groups to fetch (default: 10)
	 *
	 * [--raw]
	 * : Show raw API response before normalization
	 *
	 * [--page=<number>]
	 * : Page number to fetch (default: 1)
	 *
	 * ## EXAMPLES
	 *
	 *     wp cp-sync ccb get-groups
	 *     wp cp-sync ccb get-groups --limit=5
	 *     wp cp-sync ccb get-groups --raw
	 *     wp cp-sync ccb get-groups --page=2
	 *
	 * @when after_wp_load
	 */
	public function get_groups( $args, $assoc_args ) {
		$ccb   = CCB::get_instance();
		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 10;
		$raw   = isset( $assoc_args['raw'] );
		$page  = isset( $assoc_args['page'] ) ? (int) $assoc_args['page'] : 1;

		\WP_CLI::line( "Fetching groups from CCB (page {$page}, limit {$limit})..." );
		\WP_CLI::line( '' );

		// Get raw response first
		$raw_data = $ccb->api()->get( 'group_profiles', [
			'per_page'           => $limit,
			'page'               => $page,
			'include_image_link' => '1',
		] );

		if ( is_wp_error( $raw_data ) ) {
			\WP_CLI::error( 'API Error: ' . $raw_data->get_error_message() );
		}

		if ( $raw ) {
			\WP_CLI::line( 'Raw API Response:' );
			\WP_CLI::line( str_repeat( '-', 50 ) );
			$this->print_data( $raw_data );
			return;
		}

		// Extract and normalize groups
		$groups = [];
		if ( isset( $raw_data['response']['groups']['group'] ) ) {
			$groups = $raw_data['response']['groups']['group'];

			// If there's only one group, CCB returns it as an associative array
			if ( isset( $groups['@attributes'] ) || isset( $groups['name'] ) ) {
				$groups = [ $groups ];
			}

			// Normalize
			$groups = array_map( function( $group ) use ( $ccb ) {
				return $this->normalize_group( $group );
			}, $groups );
		}

		if ( empty( $groups ) ) {
			\WP_CLI::warning( 'No groups found' );
			return;
		}

		\WP_CLI::success( 'Found ' . count( $groups ) . ' groups' );
		\WP_CLI::line( '' );

		// Display groups in table format
		$table_data = [];
		foreach ( $groups as $group ) {
			$table_data[] = [
				'ID'          => $group['id'] ?? '',
				'Name'        => $group['name'] ?? '',
				'Type'        => isset( $group['group_type']['name'] ) ? $group['group_type']['name'] : '',
				'Department'  => $group['department'] ?? '',
			];
		}

		\WP_CLI\Utils\format_items( 'table', $table_data, [ 'ID', 'Name', 'Type', 'Department' ] );

		// Show detailed view of first group
		if ( ! empty( $groups[0] ) ) {
			\WP_CLI::line( '' );
			\WP_CLI::line( 'Detailed view of first group:' );
			\WP_CLI::line( str_repeat( '-', 50 ) );
			$this->print_data( $groups[0] );
		}
	}

	/**
	 * Fetch events from CCB API
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Number of events to fetch (default: 10)
	 *
	 * [--raw]
	 * : Show raw API response before normalization
	 *
	 * ## EXAMPLES
	 *
	 *     wp cp-sync ccb get-events
	 *     wp cp-sync ccb get-events --limit=5
	 *     wp cp-sync ccb get-events --raw
	 *
	 * @when after_wp_load
	 */
	public function get_events( $args, $assoc_args ) {
		$ccb   = CCB::get_instance();
		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 10;
		$raw   = isset( $assoc_args['raw'] );

		\WP_CLI::line( "Fetching events from CCB (limit {$limit})..." );
		\WP_CLI::line( '' );

		// Fetch events from today through 3 months in the future
		// Note: public_calendar_listing does NOT support pagination
		$date_start = date( 'Y-m-d' );
		$date_end   = date( 'Y-m-d', strtotime( '+3 months' ) );

		// Get raw response (no pagination - returns all events in date range)
		$raw_data = $ccb->api()->get( 'public_calendar_listing', [
			'date_start' => $date_start,
			'date_end'   => $date_end,
		] );

		if ( is_wp_error( $raw_data ) ) {
			\WP_CLI::error( 'API Error: ' . $raw_data->get_error_message() );
		}

		if ( $raw ) {
			\WP_CLI::line( 'Raw API Response:' );
			\WP_CLI::line( str_repeat( '-', 50 ) );
			$this->print_data( $raw_data );
			return;
		}

		// Extract and normalize events
		// public_calendar_listing returns response->items->item
		$events = [];
		if ( isset( $raw_data['response']['items']['item'] ) ) {
			$events = $raw_data['response']['items']['item'];

			// If there's only one event, CCB returns it as an associative array
			if ( isset( $events['@attributes'] ) || isset( $events['event_name'] ) ) {
				$events = [ $events ];
			}

			// Normalize
			$events = array_map( function( $event ) {
				return $this->normalize_event( $event );
			}, $events );

			// Apply limit after fetching all events
			if ( $limit > 0 && count( $events ) > $limit ) {
				$events = array_slice( $events, 0, $limit );
			}
		}

		if ( empty( $events ) ) {
			\WP_CLI::warning( 'No events found' );
			\WP_CLI::line( '' );
			\WP_CLI::line( 'Response structure:' );
			$this->print_data( $raw_data );
			return;
		}

		// Apply limit
		if ( $limit > 0 ) {
			$events = array_slice( $events, 0, $limit );
		}

		\WP_CLI::success( 'Found ' . count( $events ) . ' events' );
		\WP_CLI::line( '' );

		// Display events in table format
		$table_data = [];
		foreach ( $events as $event ) {
			// CCB returns location as a simple string (venue name only, no address)
			$venue_display = ! empty( $event['location_name'] ) ? $event['location_name'] : '(no venue)';

			$table_data[] = [
				'ID'       => $event['id'] ?? '',
				'Date'     => $event['start_date'] ?? '',
				'Name'     => $event['name'] ?? '',
				'Venue'    => $venue_display,
				'Image'    => ! empty( $event['image'] ) && is_string( $event['image'] ) ? 'Yes' : 'No',
			];
		}

		\WP_CLI\Utils\format_items( 'table', $table_data, [ 'ID', 'Date', 'Name', 'Venue', 'Image' ] );

		// Show detailed view of first event
		if ( ! empty( $events[0] ) ) {
			\WP_CLI::line( '' );
			\WP_CLI::line( 'Detailed view of first event:' );
			\WP_CLI::line( str_repeat( '-', 50 ) );

			if ( ! empty( $events[0]['location_name'] ) ) {
				\WP_CLI::line( '' );
				\WP_CLI::line( 'VENUE DATA:' );
				\WP_CLI::line( str_repeat( '=', 50 ) );
				\WP_CLI::line( 'Location Name: ' . $events[0]['location_name'] );

				// Show address if available (from event_profiles endpoint)
				if ( ! empty( $events[0]['location_street_address'] ) || ! empty( $events[0]['location_city'] ) ) {
					if ( ! empty( $events[0]['location_street_address'] ) ) {
						\WP_CLI::line( 'Street: ' . $events[0]['location_street_address'] );
					}
					if ( ! empty( $events[0]['location_city'] ) ) {
						\WP_CLI::line( 'City: ' . $events[0]['location_city'] );
					}
					if ( ! empty( $events[0]['location_state'] ) ) {
						\WP_CLI::line( 'State: ' . $events[0]['location_state'] );
					}
					if ( ! empty( $events[0]['location_zip'] ) ) {
						\WP_CLI::line( 'Zip: ' . $events[0]['location_zip'] );
					}
				} else {
					\WP_CLI::line( 'Note: Only venue name available (full address requires event_profiles endpoint)' );
				}
			} else {
				\WP_CLI::line( '' );
				\WP_CLI::line( 'No venue data available for this event' );
			}

			\WP_CLI::line( '' );
			\WP_CLI::line( 'FULL EVENT DATA:' );
			\WP_CLI::line( str_repeat( '=', 50 ) );
			$this->print_data( $events[0] );
		}
	}

	/**
	 * Call any CCB API service directly
	 *
	 * ## OPTIONS
	 *
	 * <service>
	 * : The CCB service name (e.g., api_status, group_profiles, etc.)
	 *
	 * [--args=<json>]
	 * : JSON string of query parameters
	 *
	 * ## EXAMPLES
	 *
	 *     wp cp-sync ccb get-service api_status
	 *     wp cp-sync ccb get-service group_profiles --args='{"page":"1","per_page":"5"}'
	 *     wp cp-sync ccb get-service form_list
	 *
	 * @when after_wp_load
	 */
	public function get_service( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Service name is required' );
		}

		$service = $args[0];
		$ccb     = CCB::get_instance();

		// Parse args if provided
		$query_args = [];
		if ( isset( $assoc_args['args'] ) ) {
			$query_args = json_decode( $assoc_args['args'], true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				\WP_CLI::error( 'Invalid JSON in --args parameter' );
			}
		}

		\WP_CLI::line( "Calling CCB service: {$service}" );
		if ( ! empty( $query_args ) ) {
			\WP_CLI::line( 'Query parameters: ' . json_encode( $query_args ) );
		}
		\WP_CLI::line( '' );

		$data = $ccb->api()->get( $service, $query_args );

		if ( is_wp_error( $data ) ) {
			\WP_CLI::error( 'API Error: ' . $data->get_error_message() );
		}

		\WP_CLI::success( 'Response received' );
		\WP_CLI::line( '' );
		$this->print_data( $data );
	}

	/**
	 * Helper to normalize group data
	 *
	 * @param array $group Raw group data
	 * @return array Normalized group data
	 */
	private function normalize_group( $group ) {
		// Move @attributes to top level if present
		if ( isset( $group['@attributes'] ) && is_array( $group['@attributes'] ) ) {
			$group = array_merge( $group['@attributes'], $group );
			unset( $group['@attributes'] );
		}

		// Normalize nested structures
		$nested_keys = [ 'main_leader', 'group_type', 'public_signup_form', 'images' ];
		foreach ( $nested_keys as $key ) {
			if ( isset( $group[$key]['@attributes'] ) && is_array( $group[$key]['@attributes'] ) ) {
				$group[$key] = array_merge( $group[$key]['@attributes'], $group[$key] );
				unset( $group[$key]['@attributes'] );
			}
		}

		return $group;
	}

	/**
	 * Helper to normalize event data
	 *
	 * @param array $event Raw event data
	 * @return array Normalized event data
	 */
	private function normalize_event( $event ) {
		// Move @attributes to top level if present
		if ( isset( $event['@attributes'] ) && is_array( $event['@attributes'] ) ) {
			$event = array_merge( $event['@attributes'], $event );
			unset( $event['@attributes'] );
		}

		// public_calendar_listing returns different field names
		// Normalize the structure for display
		if ( isset( $event['event_name'] ) ) {
			$event['name'] = $event['event_name'];
		}

		if ( isset( $event['event_description'] ) ) {
			$event['description'] = $event['event_description'];
		}

		// Combine date and time fields into datetime
		if ( isset( $event['date'] ) && isset( $event['start_time'] ) ) {
			$event['start_datetime'] = $event['date'] . ' ' . $event['start_time'];
		}

		if ( isset( $event['date'] ) && isset( $event['end_time'] ) ) {
			$event['end_datetime'] = $event['date'] . ' ' . $event['end_time'];
		}

		// Normalize location field
		// CCB API returns location in two different formats:
		// - public_calendar_listing: simple string
		// - event_profiles: nested object with full address
		if ( isset( $event['location'] ) ) {
			if ( is_string( $event['location'] ) && ! empty( $event['location'] ) ) {
				$event['location_name'] = trim( $event['location'] );
			} elseif ( is_array( $event['location'] ) && ! empty( $event['location'] ) ) {
				// Handle nested location object
				$location_mapping = [
					'name'           => 'location_name',
					'street_address' => 'location_street_address',
					'city'           => 'location_city',
					'state'          => 'location_state',
					'zip'            => 'location_zip',
				];

				foreach ( $location_mapping as $ccb_key => $flat_key ) {
					if ( isset( $event['location'][$ccb_key] ) && ! empty( $event['location'][$ccb_key] ) ) {
						$event[$flat_key] = $event['location'][$ccb_key];
					}
				}
			}
		}

		return $event;
	}

	/**
	 * Clear the groups store to force re-import
	 *
	 * ## EXAMPLES
	 *
	 *     wp cp-sync ccb clear-store
	 *
	 * @when after_wp_load
	 */
	public function clear_store( $args, $assoc_args ) {
		\WP_CLI::line( 'Clearing groups store...' );

		// Clear all group-related stores
		delete_option( 'cp_sync_store_groups' );
		delete_option( 'cp_sync_store_groups_cp_group_type' );
		delete_option( 'cp_sync_store_groups_cps_department' );
		delete_option( 'cp_sync_store_groups_taxonomies' );

		\WP_CLI::success( 'Groups store cleared!' );
		\WP_CLI::line( '' );
		\WP_CLI::line( 'Now run: wp cp-sync ccb pull_groups' );
	}

	/**
	 * Trigger a manual pull of groups with debug output
	 *
	 * ## EXAMPLES
	 *
	 *     wp cp-sync ccb pull-groups
	 *
	 * @when after_wp_load
	 */
	public function pull_groups( $args, $assoc_args ) {
		\WP_CLI::line( 'Triggering manual groups pull...' );
		\WP_CLI::line( '' );

		// Trigger the pull using the proper method
		$init = \CP_Sync\Integrations\_Init::get_instance();
		$result = $init->pull_integration( 'groups' );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( 'Pull failed: ' . $result->get_error_message() );
		}

		\WP_CLI::success( 'Pull triggered successfully!' );
		\WP_CLI::line( '' );

		// Check if items were queued
		global $wpdb;
		$queue_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE '%wp_pull_groups_batch_%'"
		);
		\WP_CLI::line( "Items in queue: {$queue_count}" );
		\WP_CLI::line( '' );

		if ( $queue_count > 0 ) {
			\WP_CLI::line( 'Run: wp cp-sync ccb process_queue' );
		} else {
			\WP_CLI::warning( 'No items were queued. Check the logs for details.' );
		}
	}

	/**
	 * Process the background queue for groups
	 *
	 * ## EXAMPLES
	 *
	 *     wp cp-sync ccb process-queue
	 *
	 * @when after_wp_load
	 */
	public function process_queue( $args, $assoc_args ) {
		\WP_CLI::line( 'Checking background queue status...' );
		\WP_CLI::line( '' );

		// Check for queued items
		global $wpdb;
		$batch_options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s",
				'%wp_pull_groups_batch_%'
			)
		);

		if ( empty( $batch_options ) ) {
			\WP_CLI::warning( 'No items in queue. Run "Pull Now" first to queue items.' );
			return;
		}

		\WP_CLI::success( 'Found ' . count( $batch_options ) . ' batch(es) in queue' );

		// Show queue details
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

		// Instantiate the groups integration
		if ( ! class_exists( '\CP_Sync\Integrations\CP_Groups' ) ) {
			\WP_CLI::error( 'CP_Groups integration class not found. Is CP Groups plugin active?' );
		}

		$groups_integration = new \CP_Sync\Integrations\CP_Groups();
		\WP_CLI::line( 'Using integration: ' . $groups_integration->label );
		\WP_CLI::line( '' );

		// Process the queue
		\WP_CLI::line( 'Processing queue...' );
		\WP_CLI::line( str_repeat( '-', 50 ) );

		// Manually trigger the background process
		$processed = 0;
		$errors = 0;

		foreach ( $batch_options as $batch ) {
			$items = maybe_unserialize( $batch->option_value );
			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $key => $item ) {
				$processed++;

				$item_name = 'Item';
				if ( isset( $item['_is_term'] ) ) {
					$item_name = 'Term: ' . ( $item['name'] ?? '?' );
				} elseif ( isset( $item['post_title'] ) ) {
					$item_name = 'Group: ' . $item['post_title'];
				}

				\WP_CLI::line( sprintf( '[%d/%d] %s', $processed, $total_items, $item_name ) );

				try {
					$result = $groups_integration->task( $item );
					if ( false === $result ) {
						// Task returns false when complete (per WP_Background_Process)
						\WP_CLI::line( '  ✓ Processed successfully' );
					}
				} catch ( \Exception $e ) {
					$errors++;
					\WP_CLI::warning( '  ✗ Error: ' . $e->getMessage() );
					error_log( 'CP-Sync Queue Error: ' . $e->getMessage() );
					error_log( print_r( $item, true ) );
				}
			}

			// Delete the processed batch
			delete_option( $batch->option_name );
		}

		\WP_CLI::line( '' );
		\WP_CLI::line( str_repeat( '-', 50 ) );
		\WP_CLI::success( sprintf(
			'Queue processing complete! Processed: %d, Errors: %d',
			$processed,
			$errors
		) );
	}

	/**
	 * Test filter functionality with sample data
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : Type of data to filter (groups or events)
	 *
	 * <selector>
	 * : The filter selector (e.g., group_type, department, event_type)
	 *
	 * <compare>
	 * : Comparison operator (is, is_not, contains, does_not_contain, is_empty, is_not_empty)
	 *
	 * [<value>]
	 * : Value to compare against (not needed for is_empty/is_not_empty)
	 *
	 * [--limit=<number>]
	 * : Number of items to test (default: 20)
	 *
	 * ## EXAMPLES
	 *
	 *     # Test group_type filter
	 *     wp cp-sync ccb test-filters groups group_type is "Community Group"
	 *
	 *     # Test department contains filter
	 *     wp cp-sync ccb test-filters groups department contains "Adult"
	 *
	 *     # Test childcare filter
	 *     wp cp-sync ccb test-filters groups childcare_provided is "true"
	 *
	 *     # Test event_type filter
	 *     wp cp-sync ccb test-filters events event_type is "Open to All"
	 *
	 *     # Test zip_code filter
	 *     wp cp-sync ccb test-filters events zip_code is "66062"
	 *
	 *     # Test is_empty
	 *     wp cp-sync ccb test-filters groups campus is_empty
	 *
	 * @when after_wp_load
	 */
	public function test_filters( $args, $assoc_args ) {
		if ( count( $args ) < 3 ) {
			\WP_CLI::error( 'Usage: wp cp-sync ccb test-filters <type> <selector> <compare> [<value>]' );
		}

		$type     = $args[0]; // groups or events
		$selector = $args[1]; // filter selector
		$compare  = $args[2]; // comparison operator
		$value    = $args[3] ?? null; // comparison value
		$limit    = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 20;

		// Validate type
		if ( ! in_array( $type, [ 'groups', 'events' ] ) ) {
			\WP_CLI::error( 'Type must be either "groups" or "events"' );
		}

		$ccb = CCB::get_instance();

		// Get filter config
		if ( 'groups' === $type ) {
			$filter_config = $ccb->get_group_filter_config();
		} else {
			$filter_config = $ccb->get_event_filter_config();
		}

		// Validate selector
		if ( ! isset( $filter_config[ $selector ] ) ) {
			\WP_CLI::error( sprintf(
				'Invalid selector "%s". Available: %s',
				$selector,
				implode( ', ', array_keys( $filter_config ) )
			) );
		}

		$filter = $filter_config[ $selector ];

		// Validate comparison
		$compare_options = \CP_Sync\Setup\DataFilter::get_compare_options();
		if ( ! isset( $compare_options[ $compare ] ) ) {
			\WP_CLI::error( sprintf(
				'Invalid comparison "%s". Available: %s',
				$compare,
				implode( ', ', array_keys( $compare_options ) )
			) );
		}

		// Check if value is required
		$requires_value = ! in_array( $compare, [ 'is_empty', 'is_not_empty' ] );
		if ( $requires_value && null === $value ) {
			\WP_CLI::error( sprintf( 'Comparison "%s" requires a value', $compare ) );
		}

		\WP_CLI::line( '═══════════════════════════════════════════════════' );
		\WP_CLI::line( sprintf( 'Testing %s filter: %s', $type, $selector ) );
		\WP_CLI::line( '═══════════════════════════════════════════════════' );
		\WP_CLI::line( sprintf( 'Filter path: %s', $filter['path'] ) );
		\WP_CLI::line( sprintf( 'Comparison: %s', $compare ) );
		if ( $value ) {
			\WP_CLI::line( sprintf( 'Value: %s', $value ) );
		}
		\WP_CLI::line( '' );

		// Fetch data
		\WP_CLI::line( sprintf( 'Fetching %d %s from CCB...', $limit, $type ) );

		if ( 'groups' === $type ) {
			$raw_data = $ccb->api()->get( 'group_profiles', [
				'per_page'           => $limit,
				'page'               => 1,
				'include_image_link' => '1',
			] );

			if ( is_wp_error( $raw_data ) ) {
				\WP_CLI::error( 'API Error: ' . $raw_data->get_error_message() );
			}

			$items = [];
			if ( isset( $raw_data['response']['groups']['group'] ) ) {
				$items = $raw_data['response']['groups']['group'];

				// Normalize if single item
				if ( isset( $items['@attributes'] ) || isset( $items['name'] ) ) {
					$items = [ $items ];
				}

				// Normalize
				$items = array_map( function( $item ) {
					return $this->normalize_group( $item );
				}, $items );
			}
		} else {
			$date_start = date( 'Y-m-d' );
			$date_end   = date( 'Y-m-d', strtotime( '+3 months' ) );

			$raw_data = $ccb->api()->get( 'public_calendar_listing', [
				'date_start' => $date_start,
				'date_end'   => $date_end,
			] );

			if ( is_wp_error( $raw_data ) ) {
				\WP_CLI::error( 'API Error: ' . $raw_data->get_error_message() );
			}

			$items = [];
			if ( isset( $raw_data['response']['items']['item'] ) ) {
				$items = $raw_data['response']['items']['item'];

				// Normalize if single item
				if ( isset( $items['@attributes'] ) || isset( $items['event_name'] ) ) {
					$items = [ $items ];
				}

				// Normalize
				$items = array_map( function( $item ) {
					return $this->normalize_event( $item );
				}, $items );

				// Apply limit
				if ( $limit > 0 && count( $items ) > $limit ) {
					$items = array_slice( $items, 0, $limit );
				}
			}
		}

		if ( empty( $items ) ) {
			\WP_CLI::warning( sprintf( 'No %s found', $type ) );
			return;
		}

		\WP_CLI::success( sprintf( 'Found %d %s', count( $items ), $type ) );
		\WP_CLI::line( '' );

		// Create filter condition
		$condition = [
			'selector' => $selector,
			'compare'  => $compare,
		];

		if ( $value ) {
			$condition['value'] = $value;
		}

		// Create DataFilter instance
		$data_filter = new \CP_Sync\Setup\DataFilter( 'all', [ $condition ], $filter_config );

		// Test each item
		\WP_CLI::line( '─────────────────────────────────────────────────' );
		\WP_CLI::line( 'Testing items:' );
		\WP_CLI::line( '─────────────────────────────────────────────────' );

		$passed = 0;
		$failed = 0;

		foreach ( $items as $item ) {
			$result = $data_filter->check( $item );

			if ( is_wp_error( $result ) ) {
				\WP_CLI::warning( sprintf(
					'Error checking item: %s',
					$result->get_error_message()
				) );
				continue;
			}

			$item_name = $item['name'] ?? 'Unknown';
			$field_value = $data_filter->get_val( $item, $filter['path'] );

			// Format the display value
			if ( is_array( $field_value ) ) {
				$display_value = '[' . implode( ', ', $field_value ) . ']';
			} elseif ( null === $field_value ) {
				$display_value = '(null)';
			} elseif ( '' === $field_value ) {
				$display_value = '(empty string)';
			} else {
				$display_value = $field_value;
			}

			if ( $result ) {
				$passed++;
				\WP_CLI::line( \WP_CLI::colorize( '%G✓%n ' . $item_name . ' → ' . $display_value ) );
			} else {
				$failed++;
				\WP_CLI::line( \WP_CLI::colorize( '%R✗%n ' . $item_name . ' → ' . $display_value ) );
			}
		}

		\WP_CLI::line( '' );
		\WP_CLI::line( '─────────────────────────────────────────────────' );
		\WP_CLI::success( sprintf(
			'Filter test complete! Passed: %d, Failed: %d',
			$passed,
			$failed
		) );
		\WP_CLI::line( '─────────────────────────────────────────────────' );

		// Show sample of passing item data structure
		if ( $passed > 0 ) {
			\WP_CLI::line( '' );
			\WP_CLI::line( 'Sample item data (first passing item):' );
			\WP_CLI::line( '═══════════════════════════════════════════════════' );

			// Find first passing item
			foreach ( $items as $item ) {
				if ( $data_filter->check( $item ) ) {
					$this->print_data( $item );
					break;
				}
			}
		}
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
