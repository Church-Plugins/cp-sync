<?php

namespace CP_Sync\ChMS;

use CP_Sync_Dependencies\RRule\RRule;

/**
 * Planning Center Online implementation
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

		// update on every call
		$this->api->set_access_token( $this->get_token() );

		return $this->api;
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
			$data = $this->api()->get( 'groups', [ 'per_page' => $page_size, 'page' => $page ] );
			
			if( is_wp_error( $data ) ) {
				cp_sync()->logging->log( 'Error fetching groups: ' . $data->get_error_message() );
				break;
			}

			// no more groups to fetch
			if ( empty( $data ) ) {
				break;
			}

			$filter->apply( $data );

			$groups = array_merge( $groups, $data );

			// handle limit
			if ( $limit > 0 && count( $groups ) >= $limit ) {
				$groups = array_slice( $groups, 0, $limit );
				break;
			}
		}

		$taxonomies = [];

		$taxonomies['cp_group_type'] = [
			'plural_label' => __( 'Group Types', 'cp-sync' ),
			'single_label' => __( 'Group Type', 'cp-sync' ),
			'taxonomy'     => 'cp_group_type',
			'terms'        => []
		];

		$group_types = $this->fetch_group_types();
		foreach ( $group_types as $group_type ) {
			$taxonomies['cp_group_type']['terms'][ $group_type['value'] ] = $group_type['label'];
		}

		$taxonomies['cps_department'] = [
			'plural_label' => __( 'Departments', 'cp-sync' ),
			'single_label' => __( 'Department', 'cp-sync' ),
			'taxonomy'     => 'cps_department',
			'terms'        => []
		];

		$departments = $this->fetch_departments();
		foreach ( $departments as $department ) {
			$taxonomies['cps_department']['terms'][ $department['value'] ] = $department['label'];
		}

		return [
			'items'      => $groups,
			'taxonomies' => $taxonomies,
			'context'    => [],
		];
	}

	/**
	 * Format group
	 *
	 * @param array $group The group data.
	 * @param array $context The context data.
	 * @return array
	 */
	public function format_group( $group, $context ) {
		$args = [
			'chms_id'          => $group['id'],
			'post_status'      => 'publish',
			'post_title'       => $group['name'] ?? '',
			'post_content'     => $group['description'] ?? '',
			'thumbnail_url'    => $group['images']['large'] ?? '',
			'tax_input'        => [],
			'meta_input'       => [
				'leaders'  => [ [
					'name'  => $group['main_leader']['name'] ?? '',
					'email' => $group['main_leader']['email'] ?? '',
				] ],
				'public_url'    => $group['public_signup_form']['url'] ?? '',
			],
		];

		if ( isset( $group['group_type']['id'] ) && ! empty( $group['group_type']['id'] ) ) {
			$args['tax_input']['cp_group_type'] = [ $group['group_type']['id'] ];
		}

		if ( isset( $group['department_id'] ) && ! empty( $group['department_id'] ) ) {
			$args['tax_input']['cps_department'] = [ $group['department_id'] ];
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
				'path'          => 'group_type.name',
				'type'          => 'select',
				'supports'      => [
					'is',
					'is_not',
					'is_in',
					'is_not_in',
					'is_empty',
					'is_not_empty',
				],
				'options'      => fn() => wp_send_json_success( $this->fetch_group_types() ),
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
		$group_types = $this->api()->get( 'group_types', [ 'per_page' => 100 ] );
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
		$departments = $this->api()->get( 'departments', [ 'per_page' => 100 ] );
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

		$events = $this->api()->get( 'calendar', [ 'type' => 'PUBLIC' ] );		

		if ( is_wp_error( $events ) ) {
			cp_sync()->logging->log( 'Error fetching events: ' . $events->get_error_message() );
			return [
				'items'      => [],
				'taxonomies' => [],
				'context'    => [],
			];
		}

		$filter->apply( $events );

		if ( $limit > 0 ) {
			$events = array_slice( $events, 0, $limit );
		}

		return [
			'items'      => $events,
			'taxonomies' => [],
			'context'    => [],
		];
	}

	/**
	 * Format event
	 *
	 * @param array $event The event data.
	 * @param array $context The context data.
	 * @return array
	 */
	public function format_event( $event, $context ) {
		$initial_start_date = new \DateTime( $event['event']['start'] );
		$initial_end_date   = new \DateTime( $event['event']['end'] );
		$start_date         = new \DateTime( $event['start'] );
		$end_date           = new \DateTime( $event['end'] );
		$hashed_id          = md5( $event['event_id'] . $start_date->getTimestamp() );

		$args = [
			'chms_id'               => $hashed_id,
			'post_status'           => 'publish',
			'post_title'            => $event['event']['name'] ?? '',
			'post_content'          => $event['event']['description'] ?? '',
			'thumbnail_url'         => $event['event']['images']['large'],
			'EventInitialStartDate' => $initial_start_date->format( 'Y-m-d H:i:s' ),
			'EventInitialEndDate'   => $initial_end_date->format( 'Y-m-d H:i:s' ),
			'EventStartDate'        => $start_date->format( 'Y-m-d H:i:s' ),
			'EventEndDate'          => $end_date->format( 'Y-m-d H:i:s' ),
			'EventTimezone'         => $start_date->getTimezone()->getName(),
		];

		$address = $event['event']['address'] ?? [];

		if ( ! empty( $address ) ) {
			$full_address = $address['street'] . ', ' . $address['city'] . ', ' . $address['state'] . ' ' . $address['zip'];

			$args['EventVenue'] = [
				'venue'    => $address['street'],
				'country'  => '',
				'address'  => $full_address,
				'street'   => $address['street'],
				'city'     => $address['city'],
				'state'    => $address['state'],
				'zip'      => $address['zip'],
			];
		}

		// TODO: Once ECP fixes some major bugs with event recurrence
		// this can be re-implemented. This will require replacing the
		// hashed ID/date with a single event per ID. For now we have 
		// to create a custom series from the list of events.
		if ( false && $event['event']['recurs'] ) {
			// map CCB's recurrence to RRule
			$recurrence_map = [
				'D' => 'DAILY',
				'W' => 'WEEKLY',
				'M' => 'MONTHLY',
				'Y' => 'YEARLY',
			];

			$rrule_args = [
				'FREQ'     => $recurrence_map[ $event['event']['recurrence']['frequency'] ],
				'INTERVAL' => $event['event']['recurrence']['interval'],
				'DTSTART'  => $start_date->format( 'Y-m-d' ),
			];

			if ( ! empty( $event['event']['recur_end_date'] ) ) {
				$recur_end_date = new \DateTime( $event['event']['recur_end_date'] );
				$rrule_args['UNTIL'] = $recur_end_date->format( 'Y-m-d' );
			} else {
				$rrule_args['COUNT'] = 50; // don't schedule more than 50 events out for sanity
			}

			if ( ! empty( $event['event']['recurrence']['frequency_modifier'] ) ) {
				$rrule_args['BYDAY'] = str_replace( ' ', ',', $event['event']['recurrence']['frequency_modifier'] );
			}

			$rrule = new RRule( $rrule_args );
			$args['EventRecurrence'] = 'RRULE:' . $rrule->__toString();
		}

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
				'path'  => 'event.name',
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
				'path'  => 'event.description',
				'type'  => 'text',
				'supports' => [
					'contains',
					'does_not_contain',
				],
			],
			'start_date' => [
				'label'    => __( 'Start Date', 'cp-sync' ),
				'path'     => 'event.start',
				'type'     => 'date',
				'supports' => [
					'is',
					'is_not',
					'is_less_than',
					'is_greater_than',
				],
				'format' => 'strtotime'
			],
			'end_date' => [
				'label'    => __( 'End Date', 'cp-sync' ),
				'path'     => 'event.end',
				'type'     => 'date',
				'supports' => [
					'is',
					'is_not',
					'is_less_than',
					'is_greater_than',
				],
				'format' => 'strtotime'
			],
			'zip_code' => [
				'label' => __( 'Zip Code', 'cp-sync' ),
				'path'  => 'event.address.zip',
				'type'  => 'text',
				'supports' => [
					'is',
					'is_not',
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
	public function settings_entrypoint_data( $entrypoint_data ) {}

	/**
	 * Get access token
	 */
	public function get_token() {
		$last_refresh = absint( $this->get_setting( 'last_token_refresh', 0, 'auth' ) );

		if ( 0 === $last_refresh ) {
			return false;
		}

		if ( time() - $last_refresh > HOUR_IN_SECONDS ) {
			$this->refresh_token();
		}

		return $this->get_setting( 'token', '', 'auth' );
	}

	/**
	 * Refresh the access token
	 */
	public function refresh_token() {
		// Prepare the request parameters
		$request_args = [
			'body' => [
				'action'        => 'refresh',
				'refresh_token' => $this->get_setting( 'refresh_token', '', 'auth' ),
				'subdomain'     => $this->get_setting( 'subdomain', '', 'connect' ),
			],
		];

		// Make the request using the WordPress HTTP API
		$response = wp_remote_post( CP_SYNC_OAUTH_URL . '/wp-content/themes/churchplugins/oauth/pushpay/', $request_args );

		// Check for errors
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			cp_sync()->logging->log( "Error retrieving token: $error_message" );
		} else {
			$response_body = wp_remote_retrieve_body( $response );
			$response_code = wp_remote_retrieve_response_code( $response );

			if ( $response_code == 200 ) {
				// Process the response
				$token_data = json_decode( $response_body, true );
				// Handle the token data (e.g., save it)

				if ( isset( $token_data['access_token'], $token_data['refresh_token'] ) ) {
					$this->save_token( $token_data['access_token'], $token_data['refresh_token'] );
					cp_sync()->logging->log( 'CCB token refreshed' );
				}
			} else {
				cp_sync()->logging->log( "Error retrieving token: HTTP $response_code" );
			}
		}
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
		$this->get_token(); // Refresh the token if necessary
		$last_refresh = absint( $this->get_setting( 'last_token_refresh', 0, 'auth' ) );

		if ( time() - $last_refresh < HOUR_IN_SECONDS ) {
			return [ 'status' => 'success', 'message' => 'Connection successful' ];
		}

		return [ 'status' => 'error', 'message' => 'Not connected' ];
	}
}
