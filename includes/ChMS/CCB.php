<?php

namespace CP_Sync\ChMS;

use CP_Sync_Dependencies\RRule\RRule;

/**
 * Church Community Builder implementation
 */
class CCB extends \CP_Sync\ChMS\ChMS {
	/**
	 * ChMS ID
	 *
	 * @var string
	 */
	public $id = 'ccb';

	/**
	 * API
	 *
	 * @var API\CCB
	 */
	protected $api;

	/**
	 * @var string The settings key for this integration
	 */
	public $settings_key = 'cp_sync_ccb_settings';

	/**
	 * Setup
	 */
	public function setup() {
		// register supported integrations
		$this->add_support(
			'groups',
			[
				'fetch_callback'   => [ $this, 'fetch_groups' ],
				'format_callback'  => [ $this, 'format_group' ],
				'filter_config'    => [ $this, 'get_group_filter_config' ]
			]
		);

		$this->add_support(
			'events',
			[
				'fetch_callback'   => [ $this, 'fetch_events' ],
				'format_callback'  => [ $this, 'format_event' ],
				'filter_config'    => [ $this, 'get_event_filter_config' ],
			]
		);

		// Add filter to handle past event removal
		add_filter( 'cp_sync_events_should_remove_item', [ $this, 'should_remove_event' ], 10, 3 );
	}

	/**
	 * Get the active date range for events
	 *
	 * @return array Array with 'start', 'end', and 'mode' keys
	 */
	public function get_active_date_range() {
		$mode = $this->get_setting( 'date_range_mode', 'current_upcoming', 'events' );

		switch ( $mode ) {
			case 'include_past_30':
				$date_start = date( 'Y-m-d', strtotime( '-30 days' ) );
				$date_end   = date( 'Y-m-d', strtotime( '+1 year' ) );
				break;

			case 'all_future':
				$date_start = date( 'Y-m-d' );
				$date_end   = date( 'Y-m-d', strtotime( '+10 years' ) );
				break;

			case 'custom':
				$date_start = $this->get_setting( 'date_start', null, 'events' );
				$date_end   = $this->get_setting( 'date_end', null, 'events' );

				if ( empty( $date_start ) ) {
					$date_start = date( 'Y-m-d' );
				}
				if ( empty( $date_end ) ) {
					$date_end = date( 'Y-m-d', strtotime( '+1 year' ) );
				}
				break;

			case 'current_upcoming':
			default:
				$date_start = date( 'Y-m-d' );
				$date_end   = date( 'Y-m-d', strtotime( '+1 year' ) );
				break;
		}

		return [
			'start' => $date_start,
			'end'   => $date_end,
			'mode'  => $mode,
		];
	}

	/**
	 * Determine if an event should be removed
	 *
	 * Only removes events outside the configured date range if the user has opted in.
	 * This prevents accidentally deleting events that fall outside the sync window.
	 *
	 * @param bool $should_remove Whether to remove the item (default true)
	 * @param string $chms_id The ChMS ID of the event
	 * @param \CP_Sync\Integrations\Integration $integration The integration instance
	 * @return bool
	 */
	public function should_remove_event( $should_remove, $chms_id, $integration ) {
		// Get the remove_events_outside_range setting (default false)
		$remove_outside_range = $this->get_setting( 'remove_events_outside_range', false, 'events' );

		// If the setting is enabled, remove all events not in the response
		if ( $remove_outside_range ) {
			return true;
		}

		// Otherwise, only remove events that fall WITHIN the configured date range
		// Events outside the range are preserved since we didn't query for them

		// Get the configured date range (must match fetch_events logic)
		$date_range = $this->get_active_date_range();
		$date_start = $date_range['start'];
		$date_end   = $date_range['end'];

		// Get the WordPress post ID for this ChMS ID
		$post_id = $integration->get_chms_item_id( $chms_id );

		if ( ! $post_id ) {
			// If we can't find the post, allow removal
			return true;
		}

		// Get the event start date from post meta
		$event_start = get_post_meta( $post_id, 'event_start', true );

		if ( empty( $event_start ) ) {
			// If there's no start date, allow removal
			return true;
		}

		// Check if the event falls within the configured date range
		$event_date = strtotime( date( 'Y-m-d', strtotime( $event_start ) ) );
		$range_start = strtotime( $date_start );
		$range_end = strtotime( $date_end );

		// If event is outside the configured date range, preserve it
		if ( $event_date < $range_start || $event_date > $range_end ) {
			cp_sync()->logging->log( "Preserving event outside date range (ChMS ID: {$chms_id}, Date: " . date( 'Y-m-d', $event_date ) . ") - not queried from CCB" );
			return false;
		}

		// Event is within the date range but not in API response - it was deleted in CCB
		return true;
	}

	/**
	 * Get the base URL for the ChMS
	 *
	 * @return false|string
	 *
	 * @author Tanner Moushey, 12/24/24
	 */
	public function get_base_url() {
		if ( ! $subdomain = $this->get_setting( 'subdomain', '', 'connect' ) ) {
			return false;
		}

		return "https://{$subdomain}.ccbchurch.com/";
	}

	/**
	 * Get the API instance
	 *
	 * @return API\CCB
	 */
	public function api() {
		if ( ! $this->api ) {
			$this->api = new API\CCB();
		}

		// Update credentials on every call
		$username  = $this->get_setting( 'username', '', 'connect' );
		$password  = $this->get_setting( 'password', '', 'connect' );
		$subdomain = $this->get_setting( 'subdomain', '', 'connect' );

		$this->api->set_credentials( $username, $password, $subdomain );

		return $this->api;
	}

	/**
	 * Remove CCB credentials
	 *
	 * @return void
	 */
	public function remove_token() {
		$this->update_setting( 'username', '', 'connect' );
		$this->update_setting( 'password', '', 'connect' );
		$this->update_setting( 'subdomain', '', 'connect' );
	}

	/****************************************/
	/************ Groups Methods ************/
	/****************************************/

	/**
	 * Fetch groups
	 *
	 * @param int $limit The number of groups to fetch.
	 * @return array
	 */
	public function fetch_groups( $limit = 0 ) {
		// setup filter
		$filter_settings = $this->get_setting( 'filter', [], 'groups' );
		$filter_type     = $filter_settings['type'] ?? 'all';
		$conditions      = $filter_settings['conditions'] ?? [];
		$relational_data = [];

		$filter = new \CP_Sync\Setup\DataFilter(
			$filter_type,
			$conditions,
			$this->get_group_filter_config(),
			$relational_data
		);

		$groups    = [];
		$page      = 0;
		$page_size = 100;
		while( true ) {
			$page++;
			$data = $this->api()->get( 'group_profiles', [
				'per_page'             => $page_size,
				'page'                 => $page,
				'include_image_link'   => '1',
				'include_participants' => 'false',
			] );

			if( is_wp_error( $data ) ) {
				cp_sync()->logging->log( 'Error fetching groups: ' . $data->get_error_message() );
				break;
			}

			// no more groups to fetch
			if ( empty( $data ) ) {
				break;
			}

			// Extract groups from response structure
			$page_groups = [];
			if ( isset( $data['response']['groups']['group'] ) ) {
				$page_groups = $data['response']['groups']['group'];

				// If there's only one group, CCB returns it as an associative array, not an array of groups
				if ( isset( $page_groups['@attributes'] ) || isset( $page_groups['name'] ) ) {
					$page_groups = [ $page_groups ];
				}
			}

			if ( empty( $page_groups ) ) {
				break;
			}

			// Normalize CCB data structure (move @attributes to top level)
			$page_groups = array_map( [ $this, 'normalize_ccb_group' ], $page_groups );

			$before_filter_count = count( $page_groups );

			// Filter to only publicly searchable and active groups to reduce memory usage
			// Note: CCB returns boolean values as strings ('true'/'false') from XML
			$page_groups = array_filter( $page_groups, function( $group ) {
				// Skip inactive groups (inactive = 'true')
				if ( isset( $group['inactive'] ) && $group['inactive'] === 'true' ) {
					return false;
				}

				// Skip groups that are not publicly searchable (public_search_listed != 'true')
				if ( ! isset( $group['public_search_listed'] ) || $group['public_search_listed'] !== 'true' ) {
					return false;
				}

				return true;
			} );

			$after_filter_count = count( $page_groups );
			$filtered_count = $before_filter_count - $after_filter_count;
			if ( $filtered_count > 0 ) {
				cp_sync()->logging->log( "Filtered out {$filtered_count} non-public/inactive groups on page {$page}" );
			}

			$filter->apply( $page_groups );

			$groups = array_merge( $groups, $page_groups );

			// handle limit
			if ( $limit > 0 && count( $groups ) >= $limit ) {
				$groups = array_slice( $groups, 0, $limit );
				break;
			}
		}

		cp_sync()->logging->log( 'Total publicly searchable and active groups fetched: ' . count( $groups ) );

		// Note: CP_Groups plugin handles taxonomy term creation internally via term_exists()
		// We pass group_type and department names in the formatted data, not as taxonomy arrays
		return [
			'items'      => $groups,
			'taxonomies' => [],
			'context'    => [],
		];
	}

	/**
	 * Normalize CCB group data structure
	 *
	 * Moves XML attributes from @attributes to top level
	 *
	 * @param array $group The raw group data from CCB API
	 * @return array Normalized group data
	 */
	private function normalize_ccb_group( $group ) {
		// Move @attributes to top level if present
		if ( isset( $group['@attributes'] ) && is_array( $group['@attributes'] ) ) {
			$group = array_merge( $group['@attributes'], $group );
			unset( $group['@attributes'] );
		}

		// Normalize nested structures that might have @attributes
		$nested_keys = [ 'main_leader', 'group_type', 'public_signup_form' ];
		foreach ( $nested_keys as $key ) {
			if ( isset( $group[$key]['@attributes'] ) && is_array( $group[$key]['@attributes'] ) ) {
				$group[$key] = array_merge( $group[$key]['@attributes'], $group[$key] );
				unset( $group[$key]['@attributes'] );
			}
		}

		// 'image' is a direct string value (URL) or empty array, no normalization needed

		return $group;
	}

	/**
	 * Format group
	 *
	 * @param array $group The group data.
	 * @param array $context The context data.
	 * @return array
	 */
	public function format_group( $group, $context ) {
		$thumbnail = '';

		// CCB returns 'image' (singular) as a URL string, or empty array if no image
		if ( ! empty( $group['image'] ) && is_string( $group['image'] ) ) {
			// Skip placeholder images
			if ( false === strpos( $group['image'], 'group-default' ) ) {
				$thumbnail = $group['image'];
			}
		}

		$base_url = $this->get_base_url();

		$args = [
			'chms_id'          => $group['id'],
			'post_status'      => 'publish',
			'post_title'       => $group['name'] ?? '',
			'post_content'     => $group['description'] ?? '',
			'thumbnail_url'    => $thumbnail,
			'tax_input'        => [],
			'group_category'   => [],
			'group_type'       => [],
			'group_life_stage' => [],
			'meta_input'       => [
				'leader'           => $group['main_leader']['name'] ?? '',
				'leader_email'     => $group['main_leader']['email'] ?? '',
				'registration_url' => $group['public_signup_form']['url'] ?? '',
				'public_url'       => $base_url ? $base_url . 'group_detail.php?group_id=' . $group['id'] : '',
				'is_group_full'    => $group['full'] ? 'on' : 0,
			],
		];

		// Meeting day and time
		// After normalization, meeting_time might be an array with 'id' key or a string
		$meeting_time = null;
		$meeting_day = null;

		if ( is_string( $group['meeting_time'] ?? '' ) && ! empty( $group['meeting_time'] ) ) {
			$meeting_time = $group['meeting_time'];
		} elseif ( is_array( $group['meeting_time'] ?? [] ) && ! empty( $group['meeting_time']['name'] ) ) {
			$meeting_time = $group['meeting_time']['name'];
		}

		if ( is_string( $group['meeting_day'] ?? '' ) && ! empty( $group['meeting_day'] ) ) {
			$meeting_day = $group['meeting_day'];
		} elseif ( is_array( $group['meeting_day'] ?? [] ) && ! empty( $group['meeting_day']['name'] ) ) {
			$meeting_day = $group['meeting_day']['name'];
		}

		if ( $meeting_time ) {
			$timestamp = strtotime( $meeting_time );

			if ( false !== $timestamp ) {
				$args['meta_input']['time_desc'] = date( 'g:ia', $timestamp );

				if ( $meeting_day ) {
					$args['meta_input']['time_desc'] = $meeting_day . 's at ' . $args['meta_input']['time_desc'];
					$args['meta_input']['meeting_day'] = $meeting_day;
				}
			} else {
				cp_sync()->logging->log( "Invalid meeting_time format for group {$group['id']}: {$meeting_time}" );
			}
		}

		// Childcare provided
		$args['meta_input']['kid_friendly'] = ( ! empty( $group['childcare_provided'] ) && 'true' === $group['childcare_provided'] ) ? 'on' : 0;

		// Campus/Location (Area of Town)
		if ( ! empty( $group['campus'] ) && is_string( $group['campus'] ) ) {
			$args['cp_location'] = $group['campus'];
		} elseif ( is_array( $group['area'] ?? [] ) && ! empty( $group['area']['name'] ) ) {
			$args['cp_location'] = $group['area']['name'];
		}

		// CCB API returns group_type and department as strings, not objects with IDs
		// Use the name directly, CP_Groups will handle term creation
		if ( ! empty( $group['group_type'] ) && is_string( $group['group_type'] ) ) {
			$args['group_type'][] = $group['group_type'];
		}

		if ( ! empty( $group['department'] ) && is_string( $group['department'] ) ) {
			$args['group_category'][] = $group['department'];
		}

		return $args;
	}

	/**
	 * Get the group filter configuration
	 *
	 * @return array
	 */
	public function get_group_filter_config() {
		return [
			'name' => [
				'label'    => __( 'Name', 'cp-sync' ),
				'path'     => 'name',
				'type'     => 'text',
				'supports' => [
					'is',
					'is_not',
					'contains',
					'does_not_contain',
				],
			],
			'group_type' => [
				'label'         => __( 'Group Type', 'cp-sync' ),
				'path'          => 'group_type',
				'type'          => 'text',
				'supports'      => [
					'is',
					'is_not',
					'contains',
					'does_not_contain',
					'is_empty',
					'is_not_empty',
				],
			],
			'inactive' => [
				'label'    => __( 'Inactive', 'cp-sync' ),
				'path'     => 'inactive',
				'type'     => 'select',
				'options'  => [
					[ 'value' => true, 'label' => __( 'Yes', 'cp-sync' ) ],
					[ 'value' => false,'label' => __( 'No', 'cp-sync' ) ],
				],
				'supports' => [ 'is' ],
			],
			'full' => [
				'label' => __( 'Group is full', 'cp-sync' ),
				'path'  => 'full',
				'type'  => 'select',
				'options' => [
					[ 'value' => true, 'label' => __( 'Yes', 'cp-sync' ) ],
					[ 'value' => false,'label' => __( 'No', 'cp-sync' ) ],
				],
				'supports' => [ 'is' ],
			],
			'membership_type' => [
				'label' 	=> __( 'Membership Type', 'cp-sync' ),
				'path'  	=> 'membership_type',
				'type'  	=> 'select',
				'options' => [
					[ 'value' => 'OPEN_TO_ALL', 'label' => __( 'Open to all', 'cp-sync' ) ],
					[ 'value' => 'REQUEST_REQUIRED', 'label' => __( 'Request required', 'cp-sync' ) ],
				]
			],
			'leader_name' => [
				'label'    => __( 'Leader Name', 'cp-sync' ),
				'path'     => 'main_leader.name',
				'type'     => 'text',
				'supports' => [
					'is',
					'is_not',
					'contains',
					'does_not_contain',
				],
			],
			'leader_email' => [
				'label'    => __( 'Leader Email', 'cp-sync' ),
				'path'     => 'main_leader.email',
				'type'     => 'text',
				'supports' => [
					'is',
					'is_not',
					'contains',
					'does_not_contain',
				],
			],
			'department' => [
				'label'    => __( 'Department', 'cp-sync' ),
				'path'     => 'department',
				'type'     => 'text',
				'supports' => [
					'is',
					'is_not',
					'contains',
					'does_not_contain',
					'is_empty',
					'is_not_empty',
				],
			],
			'campus' => [
				'label'    => __( 'Campus', 'cp-sync' ),
				'path'     => 'campus',
				'type'     => 'text',
				'supports' => [
					'is',
					'is_not',
					'contains',
					'does_not_contain',
					'is_empty',
					'is_not_empty',
				],
			],
			'childcare_provided' => [
				'label'    => __( 'Childcare Provided', 'cp-sync' ),
				'path'     => 'childcare_provided',
				'type'     => 'select',
				'options'  => [
					[ 'value' => 'true', 'label' => __( 'Yes', 'cp-sync' ) ],
					[ 'value' => 'false', 'label' => __( 'No', 'cp-sync' ) ],
				],
				'supports' => [ 'is' ],
			],
			// 'department' => [
			// 	'label' => __( 'Department', 'cp-sync' ),
			// 	'path' => 'department.id',
			// 	'type' => 'select',
			// 	'supports' => [
			// 		'is',
			// 		'is_not',
			// 		'is_in',
			// 		'is_not_in',
			// 		'is_empty',
			// 		'is_not_empty',
			// 	],
			// 	'options' => fn() => wp_send_json_success( $this->fetch_departments() ),
			// ]
		];
	}

	/**
	 * Fetch group types
	 *
	 * @return array
	 */
	public function fetch_group_types() {
		$group_types = $this->api()->get( 'group_type_list', [] );
		$options = [];
		foreach ( $group_types as $group_type ) {
			$options[] = [
				'value' => $group_type['id'],
				'label' => $group_type['name'],
			];
		}
		return $options;
	}

	/**
	 * Fetch departments
	 *
	 * @return array
	 */
	public function fetch_departments() {
		$departments = $this->api()->get( 'group_groupings', [] );
		$options = [];
		foreach ( $departments as $department ) {
			$options[] = [
				'value' => $department['id'],
				'label' => $department['name'],
			];
		}
		return $options;
	}

	/****************************************/
	/************ Events Methods ************/
	/****************************************/

	/**
	 * Fetch events from the ChMS
	 *
	 * @param int $limit The number of events to fetch.
	 */
	public function fetch_events( $limit = 0 ) {
		$filter_settings = $this->get_setting( 'filter', [], 'events' );
		$filter_type     = $filter_settings['type'] ?? 'all';
		$conditions      = $filter_settings['conditions'] ?? [];
		$relational_data = [];

		$filter          = new \CP_Sync\Setup\DataFilter(
			$filter_type,
			$conditions,
			$this->get_event_filter_config(),
			$relational_data
		);

		// Use public_calendar_listing endpoint to fetch events by date range
		// Date range is now configured in settings, not filters
		// Note: public_calendar_listing does NOT support pagination

		// Get date range based on mode
		$date_range = $this->get_active_date_range();
		$date_start = $date_range['start'];
		$date_end   = $date_range['end'];

		// Build API parameters - only include dates that were set
		$api_params = [];
		if ( null !== $date_start ) {
			$api_params['date_start'] = $date_start;

			// Ensure start date is not after end date
			if ( $date_start > $date_end ) {
				cp_sync()->logging->log( "Warning: start_date ({$date_start}) is after end_date ({$date_end}). Swapping dates." );
				$temp = $date_start;
				$date_start = $date_end;
				$date_end = $temp;
				$api_params['date_start'] = $date_start;
			}
		}

		if ( null !== $date_end ) {
			$api_params['date_end'] = $date_end;
		}

		$date_range_msg = 'Fetching events from CCB';
		if ( isset( $api_params['date_start'] ) ) {
			$date_range_msg .= " from {$api_params['date_start']}";
		}
		if ( isset( $api_params['date_end'] ) ) {
			$date_range_msg .= " to {$api_params['date_end']}";
		}
		cp_sync()->logging->log( $date_range_msg );

		$events = [];

		// Single API call - no pagination support
		$data = $this->api()->get( 'public_calendar_listing', $api_params );

		if ( is_wp_error( $data ) ) {
			cp_sync()->logging->log( 'Error fetching events: ' . $data->get_error_message() );
			return [
				'items'      => [],
				'taxonomies' => [],
				'context'    => [],
			];
		}

		if ( empty( $data ) ) {
			cp_sync()->logging->log( 'Empty response from public_calendar_listing API' );
			return [
				'items'      => [],
				'taxonomies' => [],
				'context'    => [],
			];
		}

		// Check for API errors
		if ( isset( $data['response']['errors'] ) ) {
			$errors = $data['response']['errors'];
			if ( is_array( $errors ) ) {
				$error_msg = isset( $errors['error'] ) ? json_encode( $errors['error'] ) : json_encode( $errors );
			} else {
				$error_msg = (string) $errors;
			}
			cp_sync()->logging->log( 'CCB API Error: ' . $error_msg );
			return [
				'items'      => [],
				'taxonomies' => [],
				'context'    => [],
			];
		}

		// Log the response structure for debugging
		cp_sync()->logging->log( 'API Response keys: ' . implode( ', ', array_keys( $data ) ) );
		if ( isset( $data['response'] ) ) {
			cp_sync()->logging->log( 'Response keys: ' . implode( ', ', array_keys( $data['response'] ) ) );
		}

		// Extract events from response structure
		// public_calendar_listing returns response->items->item
		if ( isset( $data['response']['items']['item'] ) ) {
			$events = $data['response']['items']['item'];

			// If there's only one event, CCB returns it as an associative array
			if ( isset( $events['@attributes'] ) || isset( $events['event_name'] ) ) {
				$events = [ $events ];
			}

			// Normalize CCB data structure (move @attributes to top level)
			$events = array_map( [ $this, 'normalize_ccb_event' ], $events );

			// Apply filters
			$filter->apply( $events );

			// Apply limit if specified
			if ( $limit > 0 && count( $events ) > $limit ) {
				$events = array_slice( $events, 0, $limit );
			}

			cp_sync()->logging->log( 'Successfully fetched ' . count( $events ) . ' events' );
		} else {
			cp_sync()->logging->log( 'No events found in response' );
		}

		return [
			'items'      => $events,
			'taxonomies' => [],
			'context'    => [],
		];
	}

	/**
	 * Normalize CCB event data structure
	 *
	 * Moves XML attributes from @attributes to top level and
	 * standardizes public_calendar_listing structure
	 *
	 * @param array $event The raw event data from CCB API
	 * @return array Normalized event data
	 */
	private function normalize_ccb_event( $event ) {
		// Move @attributes to top level if present
		if ( isset( $event['@attributes'] ) && is_array( $event['@attributes'] ) ) {
			$event = array_merge( $event['@attributes'], $event );
			unset( $event['@attributes'] );
		}

		// public_calendar_listing returns different field names than event_profiles
		// Normalize the structure to match what format_event expects
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

		// Map leader to organizer
		if ( isset( $event['leader_full_name'] ) ) {
			$event['organizer'] = $event['leader_full_name'];
		}

		// Map group_name if present
		if ( isset( $event['group_name'] ) ) {
			$event['group'] = $event['group_name'];
		}

		return $event;
	}

	/**
	 * Format event
	 *
	 * @param array $event The event data.
	 * @param array $context The context data.
	 * @return array
	 */
	public function format_event( $event, $context ) {
		// Data has been normalized in normalize_ccb_event
		$start_date = new \DateTime( $event['start_datetime'] ?? 'now' );
		$end_date   = new \DateTime( $event['end_datetime'] ?? 'now' );

		// Use event ID as chms_id (event_id for public_calendar_listing)
		$event_id = $event['event_id'] ?? $event['id'] ?? md5( $event['name'] . $start_date->getTimestamp() );

		$base_url = $this->get_base_url();
		$event_url = $base_url ? $base_url . 'event_detail.php?event_id=' . $event_id : '';

		// Handle image (public_calendar_listing uses 'image' field)
		$thumbnail = '';
		if ( ! empty( $event['image'] ) && is_string( $event['image'] ) ) {
			$thumbnail = $event['image'];
		}

		$args = [
			// post data
			'chms_id'       => $event_id,
			'post_status'   => 'publish',
			'post_title'    => trim( $event['name'] ?? '' ),
			'post_content'  => $event['description'] ?? '',
			'thumbnail_url' => $thumbnail,

			// Event Data
			'EventStartDate' => $start_date->format( 'Y-m-d H:i:s' ),
			'EventEndDate'   => $end_date->format( 'Y-m-d H:i:s' ),
			'EventTimezone'  => $event['timezone'] ?? $start_date->getTimezone()->getName(),
			'EventURL'       => $event_url,

			'tax_input'      => [],
			'event_category' => [],

			// extra meta
			'meta_input' => [
				'event_type'       => $event['event_type'] ?? '',
				'group_name'       => $event['group'] ?? '',
				'organizer_name'   => $event['organizer'] ?? '',
				'approval_status'  => $event['approval_status'] ?? '',
			],
		];

		// Add location as venue if present
		// public_calendar_listing has location_street_address, location_city, etc.
		if ( ! empty( $event['location_name'] ) || ! empty( $event['location_street_address'] ) ) {
			$full_address = trim(
				( $event['location_street_address'] ?? '' ) . ', ' .
				( $event['location_city'] ?? '' ) . ', ' .
				( $event['location_state'] ?? '' ) . ' ' .
				( $event['location_zip'] ?? '' )
			);

			$args['EventVenue'] = [
				'venue'   => $event['location_name'] ?? '',
				'country' => '',
				'address' => $full_address,
				'street'  => $event['location_street_address'] ?? '',
				'city'    => $event['location_city'] ?? '',
				'state'   => $event['location_state'] ?? '',
				'zip'     => $event['location_zip'] ?? '',
			];
		}

		// Note: public_calendar_listing returns individual occurrences, not recurring definitions
		// Each occurrence is returned as a separate item in the API response

		return $args;
	}

	/**
	 * Get the event filter configuration
	 *
	 * @return array
	 */
	public function get_event_filter_config() {
		return [
			'name' => [
				'label' => __( 'Name', 'cp-sync' ),
				'path'  => 'name',
				'type'  => 'text',
				'supports' => [
					'is',
					'is_not',
					'contains',
					'does_not_contain',
				],
			],
			'description' => [
				'label' => __( 'Description', 'cp-sync' ),
				'path'  => 'description',
				'type'  => 'text',
				'supports' => [
					'contains',
					'does_not_contain',
				],
			],
			'event_type' => [
				'label'    => __( 'Event Type', 'cp-sync' ),
				'path'     => 'event_type',
				'type'     => 'text',
				'supports' => [
					'is',
					'is_not',
					'contains',
					'is_empty',
					'is_not_empty',
				],
			],
			'group_name' => [
				'label'    => __( 'Group Name', 'cp-sync' ),
				'path'     => 'group_name',
				'type'     => 'text',
				'supports' => [
					'is',
					'is_not',
					'contains',
					'does_not_contain',
				],
			],
			'grouping_name' => [
				'label'    => __( 'Ministry Area', 'cp-sync' ),
				'path'     => 'grouping_name',
				'type'     => 'text',
				'supports' => [
					'is',
					'is_not',
					'contains',
					'does_not_contain',
					'is_empty',
					'is_not_empty',
				],
			],
		];
	}
	
	/**
	 * Get the settings entrypoint data
	 *
	 * @param array $entrypoint_data The entrypoint data.
	 * @return array
	 */
	public function settings_entrypoint_data( $entrypoint_data ) {
		return $entrypoint_data;
	}


	/**
	 * Register rest API routes
	 */
	public function register_rest_routes() {
		parent::register_rest_routes();
	}

	/**
	 * Check the ChMS connection
	 */
	public function check_connection() {
		$username  = $this->get_setting( 'username', '', 'connect' );
		$password  = $this->get_setting( 'password', '', 'connect' );
		$subdomain = $this->get_setting( 'subdomain', '', 'connect' );

		if ( empty( $username ) || empty( $password ) || empty( $subdomain ) ) {
			return [ 'status' => 'error', 'message' => 'Missing credentials' ];
		}

		// Test the connection by making a simple API call
		$result = $this->api()->get( 'api_status', [] );

		if ( is_wp_error( $result ) ) {
			cp_sync()->logging->log( 'CCB connection check failed: ' . $result->get_error_message() );
			return [ 'status' => 'error', 'message' => $result->get_error_message() ];
		}

		cp_sync()->logging->log( 'CCB connection check successful' );
		return [ 'status' => 'success', 'message' => 'Connection successful' ];
	}
}
