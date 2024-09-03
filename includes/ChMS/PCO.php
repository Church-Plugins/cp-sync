<?php

namespace CP_Sync\ChMS;

use CP_Sync\Admin\Settings;
use CP_Sync\Setup\DataFilter;
use PlanningCenterAPI\PlanningCenterAPI;

/**
 * Planning Center Online implementation
 */
class PCO extends \CP_Sync\ChMS\ChMS {

	/**
	 * Class integrations
	 */
	public function integrations() {}

	/**
	 * Singleton instance of the third-party API client
	 *
	 * @var PlanningCenterAPI
	 */
	public $api = null;

	/**
	 * ChMS ID
	 *
	 * @var string
	 */
	public $id = 'pco';

	/**
	 * @var string The settings key for this integration
	 */
	public $settings_key = 'cp_sync_pco_settings';

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

		add_filter( 'cp_sync_settings_entrypoint_data', [ $this, 'settings_entrypoint_data' ] );
	}

	/**
	 * Get the settings entrypoint data
	 *
	 * @param array $entrypoint_data The entrypoint data.
	 * @return array
	 */
	public function settings_entrypoint_data( $entrypoint_data ) {
		$group_filter_options = $this->get_group_filter_config();
		$event_filter_options = $this->get_event_filter_config();

		$entrypoint_group_filter_options = []; // Format the group filter config for the entrypoint
		foreach ( $group_filter_options as $key => $config ) {
			$entrypoint_group_filter_options[ $key ] = $config['label'];
		}

		$entrypoint_event_filter_options = []; // Format the event filter config for the entrypoint
		foreach ( $event_filter_options as $key => $config ) {
			$entrypoint_event_filter_options[ $key ] = $config['label'];
		}
		
		$entrypoint_data['pco'] = [
			'group_filter_options' => $entrypoint_group_filter_options,
			'event_filter_options' => $entrypoint_event_filter_options,
		];

		return $entrypoint_data;
	}

	/**
	 * Singleton instance of the third-party API client
	 *
	 * @return PlanningCenterAPI
	 * @author costmo
	 */
	public function api() {
		if( empty( $this->api ) ) {
			$this->api = new PlanningCenterAPI();

			if ( $this->get_token() ) {
				$this->api->authorization = 'Authorization: Bearer ' . $this->get_token();
			}
		}
		
		return $this->api;
	}

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

	public function refresh_token() {
		// Prepare the request parameters
		$request_args = [
			'body' => [
				'action'        => 'refresh',
				'refresh_token' => $this->get_setting( 'refresh_token', '', 'auth' ),
			],
		];

		// Make the request using the WordPress HTTP API
		$response = wp_remote_post( 'https://churchplugins.com/wp-content/themes/churchplugins/oauth/pco/', $request_args );

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
					cp_sync()->logging->log( 'PCO token refreshed' );
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

		$this->add_rest_route(
			'groups/types',
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'fetch_group_types' ],
			]
		);

		$this->add_rest_route(
			'groups/tag_groups',
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'fetch_group_tag_groups' ],
			]
		);

		$this->add_rest_route(
			'groups/tag_groups/(?P<tag_group>\d+)/tags',
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'fetch_group_tags' ],
			]
		);

		$this->add_rest_route(
			'events/tag_groups',
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'fetch_event_tag_groups' ],
			]
		);

		$this->add_rest_route(
			'events/tag_groups/(?P<tag_group>\d+)/tags',
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'fetch_event_tags' ],
			]
		);

		$this->add_rest_route(
			'events/registration_categories',
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'fetch_event_registration_categories' ],
			]
		);
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
				'path'     => 'attributes.name',
				'type'     => 'text',
				'supports' => [
					'is',
					'is_not',
					'contains',
					'does_not_contain',
				],
			],
			'description' => [
				'label'    => __( 'Description', 'cp-sync' ),
				'path'     => 'attributes.description',
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
			'group_type' => [
				'label'         => __( 'Group Type', 'cp-sync' ),
				'path'          => 'relationships.group_type.data.id',
				'relation'      => 'GroupType',
				'relation_path' => 'id',
				'type'          => 'select',
				'supports'      => [
					'is',
					'is_not',
					'is_in',
					'is_not_in',
					'is_empty',
					'is_not_empty',
				],
				'options'      => function() {
					$raw = $this->api()
						->module( 'groups' )
						->table( 'group_types' )
						->get();
						
					if ( ! empty( $this->api()->errorMessage() ) ) {
						return new ChMSError( 'pco_fetch_error', $this->api()->errorMessage() );
					}

					if ( empty( $raw ) ) {
						return new ChMSError( 'pco_data_not_found', 'The data was not found in PCO' );
					}

					$group_types = $raw['data'] ? (array) $raw['data'] : [];

					$formatted = [];

					foreach ( $group_types as $group_type ) {
						$formatted[] = [
							'value' => $group_type['id'],
							'label' => $group_type['attributes']['name'] ?? '',
						];
					}

					return wp_send_json_success( $formatted, 200 );
				}
			],
			// 'group_tag' => [
			// 	'label'         => __( 'Group Tag', 'cp-sync' ),
			// 	'path'          => 'relationships.tags.data.id',
			// 	'relation'      => 'Tag',
			// 	'relation_path' => 'id',
			// 	'type'          => 'select',
			// 	'supports'      => [
			// 		'is',
			// 		'is_not',
			// 		'is_in',
			// 		'is_not_in',
			// 		'is_empty',
			// 		'is_not_empty',
			// 	],
			// 	'options' => function() {
			// 		$tag_groups = $this->fetch_all_group_tags();

			// 		$tags = [];

			// 		foreach ( $tag_groups as $id => $tag_group ) {
			// 			foreach ( $tag_group['tags'] as $tag ) {
			// 				$tags[] = [
			// 					'value' => $id . ':' . $tag['id'],
			// 					'label' => $tag_group['name'] . ': ' . $tag['attributes']['name'],
			// 				];
			// 			}
			// 		}

			// 		return wp_send_json_success( $tags );
			// 	}
			// ],
			'location' => [
				'label' => __( 'Location', 'cp-sync' ),
				'path'  => 'relationships.location.data.id',
				'type' => 'text',
				'supports' => [
					'is',
					'is_not',
					'is_empty',
					'is_not_empty',
				],
			],
			'enrollment_status' => [
				'label'         => __( 'Enrollment Status', 'cp-sync' ),
				'path'          => 'relationships.enrollment.data.id',
				'relation'      => 'Enrollment',
				'relation_path' => 'attributes.status',
				'type'          => 'select',
				'supports'      => [
					'is',
					'is_not',
					'is_in',
					'is_not_in',
				],
				'options' => [
					[ 'value' => 'open', 'label' => 'Open' ],
					[ 'value' => 'closed', 'label' => 'Closed' ],
					[ 'value' => 'full', 'label' => 'Full' ],
					[ 'value' => 'private', 'label' => 'Private' ],
				],
			],
			'enrollment_strategy' => [
				'label'         => __( 'Enrollment Strategy', 'cp-sync' ),
				'path'          => 'relationships.enrollment.data.id',
				'relation'      => 'Enrollment',
				'relation_path' => 'attributes.strategy',
				'type'          => 'select',
				'supports'      => [
					'is',
					'is_not',
					'is_in',
					'is_not_in',
				],
				'options' => [
					[ 'value' => 'request_to_join', 'label' => 'Request to Join' ],
					[ 'value' => 'open_signup', 'label' => 'Open Signup' ],
				],
			],
			'visibility' => [
				'label' => __( 'Visibility', 'cp-sync' ),
				'path'  => 'attributes.public_church_center_web_url',
				'type'  => 'text',
				'supports' => [
					'is_empty',
					'is_not_empty',
				],
			],
		];
	}

	public function check_connection() {
		$response = $this->api()
			->module( 'people' )
			->table( 'me' )
			->get(1);

		if ( isset( $response['data'] ) && ! empty( $response['data']['id'] ) ) {
			cp_sync()->logging->log( 'PCO connection check successful' );
			return [ 'status' => 'success', 'message' => 'Connection successful' ];
		}

		cp_sync()->logging->log( 'PCO connection check failed: ' . $this->api()->errorMessage() );
		return false;
	}

	/**
	 * Fetch all group tags.
	 *
	 * @return array
	 */
	public function fetch_all_group_tags() {
		$tag_groups = $this->api()
			->module( 'groups' )
			->table( 'tag_groups' )
			->get();

		if ( ! empty( $tag_groups['data'] ) ) {
			$tag_groups = $tag_groups['data'];
		}

		$data = [];

		foreach ( $tag_groups as $tag_group ) {
			$tags = $this->api()
				->module( 'groups' )
				->table( 'tag_groups' )
				->id( $tag_group['id'] )
				->associations( 'tags' )
				->get();

			$tags = $tags['data'] ?? [];

			$data[ $tag_group['id'] ] = array(
				'name' => $tag_group['attributes']['name'],
				'tags' => $tags,
			);
		}

		return $data;
	}

	/**
	 * Fetch groups from PCO
	 *
	 * @return array {
	 * 	 @type array items   The raw groups from PCO
	 * 	 @type array context The context for the data.
	 * }
	 */
	public function fetch_groups() {
		// Pull groups here
		$raw = $this->api()
			->module( 'groups' )
			->table( 'groups' )
			->includes( 'location,group_type,enrollment' )
			->get();

		// Collapse and normalize the response
		$items = [];

		if ( ! empty( $raw ) && is_array( $raw ) && ! empty( $raw['data'] ) && is_array( $raw['data'] ) ) {
			$items = $raw['data'];
		}

		// build relational data out of the API response
		$relational_data = [];

		foreach ( $raw['included'] as $include ) {
			$relational_data[ $include['type'] ][ $include['id'] ] = $include;
		}

		$taxonomies = [];

		// get all group tags
		$tag_groups = $this->api()
			->module( 'groups' )
			->table( 'tag_groups' )
			->get();

		$tag_groups = $tag_groups['data'] ?? [];

		$include_tag_groups = $this->get_setting( 'tag_groups', [], 'cp_groups' );
		$include_tag_groups = wp_list_pluck( $include_tag_groups, 'id' );

		foreach ( $tag_groups as $tag_group ) {
			if ( ! in_array( $tag_group['id'], $include_tag_groups ) ) {
				continue;
			}

			$tags = $this->api()
				->module( 'groups' )
				->table( 'tag_groups' )
				->id( $tag_group['id'] )
				->associations( 'tags' )
				->get();

			$tags = $tags['data'] ?? [];

			$tax_slug = 'cps_' . sanitize_title( $tag_group['attributes']['name'] );

			$taxonomy_data = [
				'plural_label' => $tag_group['attributes']['name'],
				'single_label' => $tag_group['attributes']['name'],
				'taxonomy'     => $tax_slug,
				'terms'        => []
			];

			foreach ( $tags as $tag ) {
				$taxonomy_data['terms'][ $tag['id'] ] = $tag['attributes']['name'];

				// sets the taxonomy so we can assign to proper taxonomy in the group formatter
				$tag['attributes']['taxonomy'] = $tax_slug;

				$relational_data[ 'Tag' ][ $tag['id'] ] = $tag;
			}

			$taxonomies[ $tax_slug ] = $taxonomy_data;
		}

		$taxonomies['cp_group_type'] = [
			'plural_label' => __( 'Group Types', 'cp-sync' ),
			'single_label' => __( 'Group Type', 'cp-sync' ),
			'taxonomy'     => 'cp_group_type',
			'terms'        => []
		];

		foreach ( $relational_data['GroupType'] as $group_type ) {
			$taxonomies['cp_group_type']['terms'][ $group_type['id'] ] = $group_type['attributes']['name'];
		}

		// setup a filter
		$filter_settings = $this->get_setting( 'filter', [], 'cp_groups' );
		$filter_type     = $filter_settings['type'] ?? 'all';
		$conditions      = $filter_settings['conditions'] ?? [];

		$public_groups_only    = 'public' === $this->get_setting( 'visibility', 'public', 'cp_groups' );
		$enrollment_status     = $this->get_setting( 'enrollment_status', [], 'cp_groups' );
		$enrollment_strategies = $this->get_setting( 'enrollment_strategies', [], 'cp_groups' );

		// add a few custom conditions not based on the filter UI
		if ( $public_groups_only ) {
			$conditions[] = [
				'compare' => 'is_not_empty',
				'value'   => 'attributes.public_church_center_web_url',
				'type'    => 'visibility',
			];
		}

		if ( ! empty( $enrollment_status ) ) {
			$conditions[] = [
				'compare' => 'is_in',
				'value'   => $enrollment_status,
				'type'    => 'enrollment_status',
			];
		}

		if ( ! empty( $enrollment_strategies ) ) {
			$conditions[] = [
				'compare' => 'is_in',
				'value'   => $enrollment_strategies,
				'type'    => 'enrollment_strategy',
			];
		}

		$filter = new \CP_Sync\Setup\DataFilter(
			$filter_type,
			$conditions,
			$this->get_group_filter_config(),
			$relational_data
		);

		$filter->apply( $items ); // Apply the filter to the items

		return [
			'items'      => $items,
			'taxonomies' => $taxonomies,
			'context'    => [
				'relational_data' => $relational_data,
			],
		];
	}

	/**
	 * Format a group for the CP Sync integration
	 *
	 * @param array $group The group to format.
	 * @param array {
	 * 	@type DataFilter $filter The filter to check the group against.
	 * 	@type array      $relational_data The relational data for the group.
	 * } $context The context for the data.
	 * @return array|bool The formatted group or false if the group should be skipped.
	 */
	public function format_group( $group, $context ) {
		$relational_data = $context['relational_data'];

		$group_tags = $this->api()
			->module( 'groups' )
			->table( 'groups' )
			->id( $group['id'] )
			->associations( 'tags' )
			->get();

		if ( $this->api()->errorMessage() ) {
			throw new ChMSException( 'pco_fetch_error', $this->api()->errorMessage() );
		}

		$group_tags = $group_tags['data'] ?? [];

		// populate relational data
		$item_details = [];
		if ( ! empty( $group['relationships'] ) ) {
			foreach ( $group['relationships'] as $relationship ) {
				$relational_data_type = $relationship['data']['type'] ?? null;
				$relational_data_id   = $relationship['data']['id'] ?? null;

				if ( empty( $relational_data_type ) || empty( $relational_data_id ) ) {
					continue;
				}
				
				$item_details[ $relational_data_type ] = $relational_data[ $relational_data_type ][ $relational_data_id ];
			}
		}

		// setup formatted group data to send to integration
		$start_date = strtotime( $group['attributes']['created_at'] ?? null );
		$end_date   = strtotime( $group['attributes']['archived_at'] ?? null );

		$args = [
			'chms_id'          => $group['id'],
			'post_status'      => 'publish',
			'post_title'       => $group['attributes']['name'] ?? '',
			'post_content'     => $group['attributes']['description'] ?? '',
			'tax_input'        => [],
			'group_type'       => [],
			'group_category'   => [], // not used
			'group_life_stage' => [], // not used
			'thumbnail_url'    => $group['attributes']['header_image']['original'] ?? '',
			'meta_input'       => [
				'leader_email'  => $group['attributes']['contact_email'] ?? '',
				'start_date'    => date( 'Y-m-d', $start_date ),
				'end_date'      => ! empty( $end_date ) ? date( 'Y-m-d', $end_date ) : null,
				'public_url'    => $group['attributes']['public_church_center_web_url'] ?? '',
			],
		];

		// Meeting frequency
		if ( ! empty( $group['attributes']['schedule'] ) ) {
			$args['meta_input']['frequency'] = $group['attributes']['schedule'];
		}

		// Location address
		if ( ! empty( $item_details['Location']['attributes']['full_formatted_address'] ) ) {
			$args['meta_input']['location'] = $item_details['Location']['attributes']['full_formatted_address'];
		}
		
		// Time description
		if ( ! empty( $group['attributes']['schedule'] ) ) {
			$args['meta_input']['time_desc'] = $group['attributes']['schedule'];
		}

		$enrollment = $item_details['Enrollment']['attributes'];
		if ( 'closed' === $enrollment['status'] || true === $enrollment['auto_closed'] ) {
			$args['meta_input']['is_group_full'] = 'on';
		}

		foreach ( $group_tags as $tag ) {
			$tag_data = $relational_data['Tag'][ $tag['id'] ] ?? false;

			if ( ! $tag_data ) {
				continue;
			}

			$args['tax_input'][ $tag_data['attributes']['taxonomy'] ][] = $tag_data['id'];
		}

		$args['tax_input'][ 'cp_group_type' ] = [ $item_details['GroupType']['id'] ];

		// TODO: Look this up
		// if ( ! empty( $group['Congregation_ID'] ) ) {
		// 	if ( $location = $this->get_location_term( $group['Congregation_ID'] ) ) {
		// 		$args['tax_input']['cp_location'] = $location;
		// 	}
		// }

		return $args;
	}

	/**
	 * Get group types from PCO - a rest endpoint handler
	 */
	public function fetch_group_types() {
		$raw = $this->api()
			->module( 'groups' )
			->table( 'group_types' )
			->get();
			
		if ( ! empty( $this->api()->errorMessage() ) ) {
			return new ChMSError( 'pco_fetch_error', $this->api()->errorMessage() );
		}

		if ( empty( $raw ) ) {
			return new ChMSError( 'pco_data_not_found', 'The data was not found in PCO' );
		}

		$group_types = $raw['data'] ? (array) $raw['data'] : [];

		$formatted = [];

		foreach ( $group_types as $group_type ) {
			$formatted[] = [
				'id'   => $group_type['id'],
				'name' => $group_type['attributes']['name'] ?? '',
				'desc' => $group_type['attributes']['description'] ?? '',
			];
		}

		return wp_send_json_success( $formatted, 200 );
	}

	/**
	 * Get group tag types from PCO - a rest endpoint handler
	 */
	public function fetch_group_tag_groups() {
		$raw = $this->api()
			->module( 'groups' )
			->table( 'tag_groups' )
			->get();
		
		if ( ! empty( $this->api()->errorMessage() ) ) {
			return new ChMSError( 'pco_fetch_error', $this->api()->errorMessage() );
		}

		if ( empty( $raw ) ) {
			return new ChMSError( 'pco_data_not_found', 'The data was not found in PCO' );
		}

		$group_types = $raw['data'] ? (array) $raw['data'] : [];

		$formatted = [];

		foreach ( $group_types as $group_type ) {
			$formatted[] = [
				'id'   => $group_type['id'],
				'name' => $group_type['attributes']['name'] ?? '',
			];
		}

		return wp_send_json_success( $formatted, 200 );
	}

	/**
	 * Get the tags for a specific tag group - a rest endpoint handler
	 */
	public function fetch_group_tags( $request ) {
		$tag_group = $request->get_param( 'tag_group' );

		$raw = $this->api()
			->module( 'groups' )
			->table( 'tag_groups' )
			->id( $tag_group )
			->associations( 'tags' )
			->get();

		if ( ! empty( $this->api()->errorMessage() ) ) {
			return new ChMSError( 'pco_fetch_error', $this->api()->errorMessage() );
		}

		if ( empty( $raw ) ) {
			return new ChMSError( 'pco_data_not_found', 'The data was not found in PCO' );
		}

		$tags = $raw['data'] ? (array) $raw['data'] : [];

		$formatted = [];

		foreach ( $tags as $tag ) {
			$formatted[] = [
				'id'   => $tag['id'],
				'name' => $tag['attributes']['name'] ?? '',
			];
		}

		return wp_send_json_success( $formatted, 200 );
	}

	/**
	 * The Events Calendar Support Methods
	 */

	/**
	 * Fetch events from PCO
	 */
	public function fetch_events() {
		$source = $this->get_setting( 'source', 'calendar', 'ecp' );

		if ( 'calendar' === $source ) {
			return $this->fetch_events_from_calendar();
		} else if ( 'registrations' === $source ) {
			return $this->fetch_events_from_registrations();
		} else {
			return new ChMSError( 'pco_fetch_error', 'Invalid source type' );
		}
	}

	/**
	 * Format an event
	 */
	public function format_event( $event, $context ) {
		$source = $this->get_setting( 'source', 'calendar', 'ecp' );

		if ( 'calendar' === $source ) {
			return $this->format_event_from_calendar( $event, $context );
		} else if ( 'registrations' === $source ) {
			return $this->format_event_from_registrations( $event, $context );
		} else {
			return new ChMSError( 'pco_fetch_error', 'Invalid source type' );
		}
	}

	/**
	 * Fetch events from PCO calendar
	 *
	 * @return array {
	 * 	 @type array items   The raw events from PCO
	 * 	 @type array context The context for the data.
	 *   @type array taxonomies The taxonomies for the events.
	 * }
	 * @throws ChMSException If there is an error fetching the events.
	 */
	public function fetch_events_from_calendar() {
	
		$raw_events = $this->api()
			->module('calendar')
			->table('event_instances')
			->includes('event,event_times,tags')
			->filter('future')
			->order('starts_at')
			->get();

		$tag_groups = $this->api()
			->module( 'calendar' )
			->table('tag_groups')
			->get();

		if( !empty( $this->api()->errorMessage() ) ) {
			cp_sync()->logging->log( var_export( $this->api()->errorMessage(), true ) );
			return new ChMSError( 'pco_fetch_error', $this->api()->errorMessage() );
		}

		$items      = $raw_events['data'] ?? [];
		$tag_groups = $tag_groups['data'] ?? [];

		$relational_data = [];
		$taxonomies      = [];

		foreach( $raw_events['included'] as $include ) {
			$relational_data[ $include['type'] ][ $include['id'] ] = $include;
		}

		foreach ( $raw_events['data'] as $event ) {
			$relational_data[ $event['type'] ][ $event['id'] ] = $event;
		}

		$selected_tag_groups = $this->get_setting( 'tag_groups', [], 'ecp' );
		$selected_tag_groups = wp_list_pluck( $selected_tag_groups, 'id' );

		foreach ( $tag_groups as $tag_group ) {
			if ( ! in_array( $tag_group['id'], $selected_tag_groups ) ) {
				continue;
			}

			$tax_slug = 'cps_' . sanitize_title( $tag_group['attributes']['name'] );

			$taxonomy_data = [
				'plural_label' => $tag_group['attributes']['name'],
				'single_label' => $tag_group['attributes']['name'],
				'taxonomy'     => $tax_slug,
				'terms'        => []
			];

			$tags = $this->api()
				->module( 'calendar' )
				->table( 'tag_groups' )
				->id( $tag_group['id'] )
				->associations( 'tags' )
				->get();

			$tags = $tags['data'] ?? [];

			foreach ( $tags as $tag ) {
				$taxonomy_data['terms'][ $tag['id'] ] = $tag['attributes']['name'];

				// sets the taxonomy so we can assign to propery taxonomy in the event formatter
				$tag['attributes']['taxonomy'] = $tax_slug;

				$relational_data[ 'Tag' ][ $tag['id'] ] = $tag;
			}

			$taxonomies[ $tax_slug ] = $taxonomy_data;
		}

		$filter_settings = $this->get_setting( 'filter', [], 'ecp' );

		$filter_type = $filter_settings['type'] ?? 'all';
		$conditions  = $filter_settings['conditions'] ?? [];

		$public_events_only = 'public' === $this->get_setting( 'visibility', 'public', 'ecp' );

		if ( $public_events_only ) {
			$conditions[] = [
				'compare' => 'is_not_empty',
				'type'    => 'visible_in_church_center',
			];
		}

		$filter = new \CP_Sync\Setup\DataFilter(
			$filter_type,
			$conditions,
			$this->get_event_filter_config(),
			$relational_data
		);

		$filter->apply( $items );

		return [
			'items'   => $items,
			'context' => [
				'relational_data' => $relational_data,
			],
			'taxonomies' => $taxonomies,
		];
	}

	/**
	 * Fetch events from PCO registrations
	 * 
	 * @return array {
	 * 	 @type array items   The raw events from PCO
	 * 	 @type array context The context for the data.
	 *   @type array taxonomies The taxonomies for the events.
	 * }
	 */
	public function fetch_events_from_registrations() {
		$raw_events = $this->api()
			->module( 'registrations' )
			->table( 'events' )
			->includes( 'categories,event_location,event_times' )
			->order( 'starts_at' )
			->filter( 'unarchived,published' )
			->get();

		$items = $raw_events['data'] ?? [];

		$categories = [];

		$relational_data = [];
		foreach( $raw_events['included'] as $include ) {
			$relational_data[ $include['type'] ][ $include['id'] ] = $include;
		}

		$filter_settings = $this->get_setting( 'filter', [], 'ecp' );

		$filter_type = $filter_settings['type'] ?? 'all';
		$conditions  = $filter_settings['conditions'] ?? [];

		$public_events_only = 'public' === $this->get_setting( 'visibility', 'public', 'ecp' );

		if ( $public_events_only ) {
			$conditions[] = [
				'compare' => 'is_not_empty',
				'type'    => 'visible_in_church_center',
			];
		}

		$filter = new \CP_Sync\Setup\DataFilter(
			$filter_type,
			$conditions,
			$this->get_registration_filter_config(),
			$relational_data
		);

		$filter->apply( $items ); // Apply the filter to the items

		return [
			'items'      => $items,
			'context'    => [
				'relational_data' => $relational_data,
			],
			'taxonomies' => [],
		];
	}

	/**
	 * Format an event from PCO calendar for the CP Sync integration
	 *
	 * @param array $event_instance The event instance to format.
	 * @param array $context The context for the data.
	 * @return array|bool The formatted event or false if the event should be skipped.
	 */
	public function format_event_from_calendar( $event_instance, $context ) {
		$relational_data = $context['relational_data'];

		// Sanity check the event
		$event_id = $event_instance['relationships']['event']['data']['id'] ?? 0;
		
		if( empty( $event_id ) ) {
			return false;
		}

		// Pull top-level event details
		$event = $relational_data['Event'][ $event_id ] ?? [];

		$time_ids   = wp_list_pluck( $event_instance['relationships']['event_times']['data'], 'id' );
		$start_date = $end_date = false;

		$date_time = current( $time_ids );

		if ( empty( $date_time ) ) {
			return false;
		}

		$start_date = $relational_data['EventTime'][ $date_time ]['attributes']['starts_at'] ?? '';
		$end_date   = $relational_data['EventTime'][ $date_time ]['attributes']['ends_at'] ?? '';

		if ( ! $start_date || ! $end_date ) {
			return false;
		}

		$start_date = (new \DateTime( $start_date ))->setTimezone( wp_timezone() );
		$end_date   = (new \DateTime( $end_date ))->setTimezone( wp_timezone() );

		// Begin stuffing the output
		$args = [
			'chms_id'        => $event['id'],
			'post_status'    => 'publish',
			'post_title'     => $event['attributes']['name'] ?? '',
			'post_content'   => $event['attributes']['description'] ?? '',
			'post_excerpt'   => $event['attributes']['summary'] ?? '',
			'tax_input'      => [],
			'event_category' => [],
			'thumbnail_url'  => '',
			'meta_input'     => [
				'registration_url' => $event['attributes']['registration_url'] ?? '',
			],
			'EventStartDate'        => $start_date->format( 'Y-m-d' ),
			'EventEndDate'          => $end_date->format( 'Y-m-d' ),
			'EventAllDay'           => $event_instance['attributes']['all_day_event'] ?? false,
			'EventStartHour'        => $start_date->format( 'G' ),
			'EventStartMinute'      => $start_date->format( 'i' ),
			// 'EventStartMeridian'    => $event[''],
			'EventEndHour'          => $end_date->format( 'G' ),
			'EventEndMinute'        => $end_date->format( 'i' ),
			// 'EventEndMeridian'      => $event[''],
			// 'EventHideFromUpcoming' => $event[''],
			// 'EventShowMapLink'      => $event[''],
			// 'EventShowMap'          => $event[''],
			// 'EventCost'             => $event[''],
			'EventURL'              => $event['attributes']['registration_url'] ?? '',
			'FeaturedImage'         => $event['attributes']['image_url'] ?? '',
		];

		// Featured image
		if ( ! empty( $event['attributes']['image_url'] ) ) {
			$url = explode( '?', $event['attributes']['image_url'], 2 );
			$args['thumbnail_url'] = $url[0] . '?tecevent-' . sanitize_title( $args['post_title'] ) . '.jpeg&' . $url[1];
		}

		// Generic location - a long string with an entire address
		// if ( ! empty( $event_instance['attributes']['location'] ) ) {
		// 	$args['tax_input']['cp_location'] = $event_instance['attributes']['location'];
		// }

		// Get the event's tags and pair them with appropriate taxonomies
		$tags = wp_list_pluck( $event_instance['relationships']['tags']['data'], 'id' );

		foreach ( $tags as $tag ) {
			$tag_data = $relational_data['Tag'][ $tag ] ?? false;

			if ( ! $tag_data ) {
				continue;
			}

			$args['tax_input'][ $tag_data['attributes']['taxonomy'] ][] = $tag_data['id'];
		}

		// 	// This "tag" is a campus/location
		// 	if( in_array( 'campus', $included_taxonomies ) ) {

		// 		$campus =  $this->pull_campus_details( $tag_text );

		// 		// Map to Location/Venue for TEC
		// 		if( !empty( $campus ) && is_array( $campus ) ) {
		// 			$args['Venue'] = [
		// 				'Venue'    => $tag_text,
		// 				'Country'  => $campus['country'] ?? '',
		// 				'Address'  => $campus['street'] ?? '',
		// 				'City'     => $campus['city'] ?? '',
		// 				'State'    => $campus['state'] ?? '',
		// 				'Zip'      => $campus['zip'],
		// 			];
		// 		}

		// 	} else if( in_array( 'event_type', $included_taxonomies ) ) {
		// 		// This is a TEC Event Category
		// 		$args['event_category'][] = $tag_text;
		// 	} else if( in_array( 'ministry_group', $included_taxonomies ) ) {
		// 		// This is a TEC Event Category
		// 		$args['tax_input']['cp_ministry'] = $tag_text;
		// 	} else {

		// 		// This is something else - we're assuming that it's a taxonomy and that the taxonomy is already registered
		// 		foreach( $included_taxonomies as $loop_tax ) {
		// 			$args['tax_input'][ $loop_tax ] = $tag_text;
		// 		}
		// 	}
		// }

		// // Add event contact info
		// if( !empty( $event['contact'] ) ) {

		// 	// Normalize variables so they're easier to work with
		// 	$contact_email = $event['contact']['contact_data']['email_addresses'][0]['address'] ?? '';
		// 	$contact_phone = $event['contact']['contact_data']['phone_numbers'][0]['number'] ?? '';
		// 	$first_name = $event['contact']['first_name'] ?? '';
		// 	$last_name = $event['contact']['last_name'] ?? '';
		// 	$use_name = $first_name . ' ' . $last_name;

		// 	// Only include if the person is not "empty" (depending on the ChMS definition of "empty")
		// 	if( !empty( $first_name ) && 'no owner' !== strtolower( $use_name ) ) {
		// 		$args['Organizer'] = [
		// 			'Organizer' => $use_name,
		// 			'Email'     => $contact_email,
		// 			'Phone'     => $contact_phone,
		// 		];
		// 	}

		// }

		// throw new ChMSException( 400, 'The event formatting is not yet implemented' );

		return $args;
	}

	/**
	 * Format an event from PCO registrations for the CP Sync integration
	 * 
	 * @param array $event The event to format.
	 * @param array $context The context for the data.
	 * @return array|bool The formatted event or false if the event should be skipped.
	 */
	public function format_event_from_registrations( $event, $context ) {
		$relational_data = $context['relational_data'];

		// Begin stuffing the output
		$args = [
			'chms_id'        => $event['id'],
			'post_status'    => 'publish',
			'post_title'     => $event['attributes']['name'] ?? '',
			'post_content'   => $event['attributes']['description'] ?? '',
			// 'post_excerpt'   => $event['attributes']['summary'] ?? '',
			'tax_input'      => [],
			'event_category' => [],
			'thumbnail_url'  => '',
			'meta_input'     => [
				'registration_url' => '',
			],
			// 'EventStartDate'        => $start_date->format( 'Y-m-d' ),
			// 'EventEndDate'          => $end_date->format( 'Y-m-d' ),
			// 'EventAllDay'           => true,
			// 'EventStartHour'        => $start_date->format( 'G' ),
			// 'EventStartMinute'      => $start_date->format( 'i' ),
			// 'EventStartMeridian'    => $event[''],
			// 'EventEndHour'          => $end_date->format( 'G' ),
			// 'EventEndMinute'        => $end_date->format( 'i' ),
			// 'EventEndMeridian'      => $event[''],
			// 'EventHideFromUpcoming' => $event[''],
			// 'EventShowMapLink'      => $event[''],
			// 'EventShowMap'          => $event[''],
			// 'EventCost'             => $event[''],
			// 'EventURL'              => $event[''],
			// 'FeaturedImage'         => $event['attributes']['image_url'] ?? '',
		];

		if ( 'none' !== $event['attributes']['registration_type'] ) {
			$args['meta_input']['registration_url'] = trailingslashit( $event['attributes']['public_url'] ) . 'reservations/new/';
			$args['meta_input']['registration_sold_out'] = false;

			if ( ! empty( $event['attributes']['at_maximum_capacity'] ) ) {
				$args['meta_input']['registration_sold_out'] = true;
			}
		}

		$time_ids  = wp_list_pluck( $event['relationships']['event_times']['data'], 'id' );
		$date_time = current( $time_ids );

		if ( empty( $date_time ) ) {
			return false;
		}

		$start_date = $relational_data['EventTime'][ $date_time ]['attributes']['starts_at'] ?? '';
		$end_date   = $relational_data['EventTime'][ $date_time ]['attributes']['ends_at'] ?? '';
		$all_day    = $relational_data['EventTime'][ $date_time ]['attributes']['all_day'] ?? false;

		if ( ! $start_date || ! $end_date ) {
			return false;
		}

		$start_date = (new \DateTime( $start_date ))->setTimezone( wp_timezone() );
		$end_date   = (new \DateTime( $end_date ))->setTimezone( wp_timezone() );

		$args['EventStartDate'] = $start_date->format( 'Y-m-d' );
		$args['EventStartHour'] = $start_date->format( 'G' );
		$args['EventStartMinute'] = $start_date->format( 'i' );
		$args['EventEndDate'] = $end_date->format( 'Y-m-d' );
		$args['EventEndHour'] = $end_date->format( 'G' );
		$args['EventEndMinute'] = $end_date->format( 'i' );

		if ( $all_day ) {
			$args['EventAllDay'] = true;
		}

		// Featured image
		if ( ! empty( $event['attributes']['logo_url'] ) ) {
			$args['thumbnail_url'] = $event['attributes']['logo_url'];
		}

		$category_ids = wp_list_pluck( $event['relationships']['categories']['data'], 'id' );
		$categories = [];
		foreach ( $category_ids as $category_id ) {
			$data = $relational_data['Category'][ $category_id ]['attributes'];
			$categories[ $data['slug'] ] = $data['name'];
		}

		if ( ! empty( $categories ) ) {
			$args['event_category'] = $categories;
		}

		$location_ids = wp_list_pluck( $event['relationships']['event_location']['data'], 'id' );
		foreach ( $location_ids as $location_id ) {
			$data = $relational_data['Location'][ $location_id ]['attributes'];
			$location = $this->get_location_details( $data );
			if ( ! empty( $location['Venue'] ) ) {
				$args['Venue'] = $location;
				break;
			}
		}

		return $args;
	}

	/**
	 * Get location details for provided location
	 *
	 * @since  1.0.0
	 *
	 * @param array $location_data The location data to format.
	 *
	 * @return array
	 * @author Tanner Moushey, 12/20/23
	 */
	protected function get_location_details( $location_data ) {
		if ( empty( $location_data['address_data'] ) || empty( $location_data['name'] ) ) {
			return [];
		}

		$location = [
			'Venue'   => $location_data['name'],
			'Country' => '',
			'Address' => '',
			'City'    => '',
			'State'   => '',
			'Zip'     => '',
		];

		foreach( $location_data['address_data'] as $part ) {
			if ( empty( $part['types'] ) ) {
				continue;
			}

			if ( in_array( 'street_number', $part['types'] ) ) {
				$location['Address'] = $part['long_name'] . $location['Address'];
			}

			if ( in_array( 'route', $part['types'] ) ) {
				$location['Address'] .= $part['long_name'];
			}

			if ( in_array( 'locality', $part['types'] ) ) {
				$location['City'] = $part['long_name'];
			}

			if ( in_array( 'administrative_area_level_1', $part['types'] ) ) {
				$location['State'] = $part['long_name'];
			}

			if ( in_array( 'country', $part['types'] ) ) {
				$location['Country'] = $part['long_name'];
			}

			if ( in_array( 'postal_code', $part['types'] ) ) {
				$location['Zip'] = $part['long_name'];
			}
		}

		return $location;
	}

	/**
	 * Event filter config
	 *
	 * @return array
	 */
	public function get_event_filter_config() {
		return [
			'start_date' => [
				'label' => __( 'Start Date', 'cp-sync' ),
				'path'  => 'attributes.starts_at',
				'type'  => 'date',
				'supports' => [ 'is_greater_than', 'is_less_than' ]
			],
			'end_date' => [
				'label' => __( 'End Date', 'cp-sync' ),
				'path'  => 'attributes.ends_at',
				'type'  => 'date',
				'supports' => [ 'is_greater_than', 'is_less_than' ]
			],
			'recurrence' => [
				'label' => __( 'Recurrence', 'cp-sync' ),
				'path'  => 'attributes.recurrence',
				'type'  => 'select',
				'options' => [
					[ 'value' => 'daily', 'label' => 'Daily' ],
					[ 'value' => 'weekly', 'label' => 'Weekly' ],
					[ 'value' => 'monthly', 'label' => 'Monthly' ],
					[ 'value' => 'yearly', 'label' => 'Yearly' ],
				],
				'supports' => [ 'is', 'is_not', 'is_empty', 'is_not_empty', 'is_in', 'is_not_in' ]
			],
			'recurrence_description' => [
				'label' => __( 'Recurrence Description', 'cp-sync' ),
				'path'  => 'attributes.recurrence_description',
				'type'  => 'text',
				'supports' => [ 'contains', 'does_not_contain', 'is_empty', 'is_not_empty', 'is', 'is_not' ]
			],
			'event_name' => [
				'label'         => __( 'Event Name', 'cp-sync' ),
				'path'          => 'relationships.event.data.id',
				'relation'      => 'Event',
				'relation_path' => 'attributes.name',
				'type'          => 'text',
				'supports'      => [ 'contains', 'does_not_contain', 'is_empty', 'is_not_empty', 'is', 'is_not' ]
			],
			'visible_in_church_center' => [
				'label'         => __( 'Visible in Church Center', 'cp-sync' ),
				'path'          => 'relationships.event.data.id',
				'relation'      => 'Event',
				'relation_path' => 'attributes.visible_in_church_center',
				'type'          => 'text',
				'supports'      => [ 'is_empty', 'is_not_empty' ]
			],
		];
	}

	/**
	 * Event filter config
	 *
	 * @return array
	 */
	public function get_registration_filter_config() {
		return [
			'start_date' => [
				'label' => __( 'Start Date', 'cp-sync' ),
				'path'  => 'attributes.first_event_date',
			],
			'end_date' => [
				'label' => __( 'End Date', 'cp-sync' ),
				'path'  => 'attributes.ends_at',
			],
			'event_name' => [
				'label'         => __( 'Event Name', 'cp-sync' ),
				'path'          => 'attributes.name',
			],
			'visible_in_church_center' => [
				'label'         => __( 'Visible in Church Center', 'cp-sync' ),
				'path'          => 'relationships.event.data.id',
				'relation'      => 'Event',
				'relation_path' => 'attributes.visible_in_church_center',
			],
			'registration_category' => [
				'label'         => __( 'Category', 'cp-sync' ),
				'path'          => 'relationships.categories.data',
				'format'        => fn( $value ) => wp_list_pluck( $value, 'id' ),
			],
		];
	}

	/**
	 * Get the available tag groups for events - a rest endpoint handler
	 */
	public function fetch_event_tag_groups() {
		$raw = $this->api()
			->module( 'calendar' )
			->table( 'tag_groups' )
			->get();
		
		if ( ! empty( $this->api()->errorMessage() ) ) {
			return new ChMSError( 'pco_fetch_error', $this->api()->errorMessage() );
		}

		if ( empty( $raw ) ) {
			return new ChMSError( 'pco_data_not_found', 'The data was not found in PCO' );
		}

		$tag_groups = $raw['data'] ?? [];

		$formatted = [];

		foreach ( $tag_groups as $tag_group ) {
			$formatted[] = [
				'id'   => $tag_group['id'],
				'name' => $tag_group['attributes']['name'] ?? '',
			];
		}

		return wp_send_json_success( $formatted, 200 );
	}

	/**
	 * Get the tags for a specific tag group - a rest endpoint handler
	 */
	public function fetch_event_tags( $request ) {
		$tag_group = $request->get_param( 'tag_group' );

		$raw = $this->api()
			->module( 'calendar' )
			->table( 'tag_groups' )
			->id( $tag_group )
			->associations( 'tags' )
			->get();

		if ( ! empty( $this->api()->errorMessage() ) ) {
			return new ChMSError( 'pco_fetch_error', $this->api()->errorMessage() );
		}

		if ( empty( $raw ) ) {
			return new ChMSError( 'pco_data_not_found', 'The data was not found in PCO' );
		}

		$tags = $raw['data'] ?? [];

		$formatted = [];

		foreach ( $tags as $tag ) {
			$formatted[] = [
				'id'   => $tag['id'],
				'name' => $tag['attributes']['name'] ?? '',
			];
		}

		return wp_send_json_success( $formatted, 200 );
	}

	/**
	 * Get registration categories from PCO - a rest endpoint handler
	 */
	public function fetch_event_registration_categories( $request ) {
		$raw = $this->api()
			->module( 'registrations' )
			->table( 'categories' )
			->get();

		if ( ! empty( $this->api()->errorMessage() ) ) {
			return new ChMSError( 'pco_fetch_error', $this->api()->errorMessage() );
		}

		if ( empty( $raw ) ) {
			return new ChMSError( 'pco_data_not_found', 'The data was not found in PCO' );
		}

		$tags = $raw['data'] ?? [];

		$formatted = [];

		foreach ( $tags as $tag ) {
			$formatted[] = [
				'id'   => $tag['id'],
				'name' => $tag['attributes']['name'] ?? '',
			];
		}

		return wp_send_json_success( $formatted, 200 );
	}

}
