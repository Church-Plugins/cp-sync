<?php

namespace CP_Connect\ChMS;

use CP_Connect\Admin\Settings;
use CP_Connect\Setup\DataFilter;
use PlanningCenterAPI\PlanningCenterAPI;

/**
 * Planning Center Online implementation
 */
class PCO extends \CP_Connect\ChMS\ChMS {

	/**
	 * Rest API namespace
	 */
	public $rest_namespace = '/pco';

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
	 * Setup
	 */
	public function setup() {
		// register supported integrations
		$this->add_support(
			'cp_groups',
			[
				'fetch_callback'   => [ $this, 'fetch_groups' ],
				'format_callback'  => [ $this, 'format_group' ],
			]
		);

		$this->add_support(
			'tec',
			[
				'fetch_callback'   => [ $this, 'fetch_events' ],
				'format_callback'  => [ $this, 'format_event' ],
			]
		);

		$this->load_connection_parameters();

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
		$compare_options      = DataFilter::get_compare_options();

		$entrypoint_group_filter_options = []; // Format the group filter config for the entrypoint
		foreach ( $group_filter_options as $key => $config ) {
			$entrypoint_group_filter_options[ $key ] = $config['label'];
		}

		$entrypoint_event_filter_options = []; // Format the event filter config for the entrypoint
		foreach ( $event_filter_options as $key => $config ) {
			$entrypoint_event_filter_options[ $key ] = $config['label'];
		}

		$entrypoint_compare_options = []; // Format the compare config for the entrypoint
		foreach ( $compare_options as $key => $config ) {
			$entrypoint_compare_options[] = [
				'value' => $key,
				'label' => $config['label'],
				'type'  => $config['type'],
			];
		}

		$entrypoint_data['pco'] = [
			'group_filter_options' => $entrypoint_group_filter_options,
			'event_filter_options' => $entrypoint_event_filter_options,
			'compare_options'      => $entrypoint_compare_options,
		];

		return $entrypoint_data;
	}

	/**
	 * Get authentication API args for the rest API
	 *
	 * @return array
	 */
	public function get_auth_api_args() {
		return [
			'app_id' => [
				'type'        => 'string',
				'description' => 'The App ID for the PCO API',
				'required'    => true,
			],
			'secret' => [
				'type'        => 'string',
				'description' => 'The Secret for the PCO API',
				'required'    => true,
			],
		];
	}

	/**
	 * Check the connection to the ChMS
	 *
	 * @param array $data The data to check the connection with. (View get_auth_api_args for the required data)
	 */
	public function check_auth( $data ) {
		return true;
	}

	/**
	 * Load auth connection parameters from the database
	 * @return bool
	 */
	public function load_connection_parameters( $option_slug = '' ) {
		$app_id = Settings::get( 'app_id', '', 'cpc_pco_connect' );
		$secret = Settings::get( 'secret', '', 'cpc_pco_connect' );

		if( empty( $app_id ) || empty( $secret ) ) {
			return false;
		}

		putenv( "PCO_APPLICATION_ID=$app_id" );
		putenv( "PCO_SECRET=$secret" );

		return true;
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
		}

		return $this->api;
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
	}

	/**
	 * CP Groups Support Methods
	 */

	/**
	 * Get the group filter configuration
	 *
	 * @return array
	 */
	public function get_group_filter_config() {
		return [
			'name' => [
				'label' => __( 'Name', 'cp-sync' ),
				'path'  => 'attributes.name',
			],
			'description' => [
				'label' => __( 'Description', 'cp-sync' ),
				'path'  => 'attributes.description',
			],
			'group_type' => [
				'label'         => __( 'Group Type', 'cp-sync' ),
				'path'          => 'relationships.group_type.data.id',
				'relation'      => 'GroupType',
				'relation_path' => 'id',
			],
			'location' => [
				'label' => __( 'Location', 'cp-sync' ),
				'path'  => 'relationships.location.data.id',
			],
			'enrollment_status' => [
				'label'         => __( 'Enrollment Status', 'cp-sync' ),
				'path'          => 'relationships.enrollment.data.id',
				'relation'      => 'Enrollment',
				'relation_path' => 'attributes.status',
			],
			'enrollment_strategy' => [
				'label'         => __( 'Enrollment Strategy', 'cp-sync' ),
				'path'          => 'relationships.enrollment.data.id',
				'relation'      => 'Enrollment',
				'relation_path' => 'attributes.strategy',
			],
			'visibility' => [
				'label' => __( 'Visibility', 'cp-sync' ),
				'path'  => 'attributes.public_church_center_web_url',
			],
		];
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

		$include_tag_groups = Settings::get( 'tag_groups', [], 'cpc_pco_groups' );
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

			$tax_slug = 'cpc_' . sanitize_title( $tag_group['attributes']['name'] );

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
		$filter_settings = Settings::get( 'filter', [], 'cpc_pco_groups' );
		$filter_type     = $filter_settings['type'] ?? 'all';
		$conditions      = $filter_settings['conditions'] ?? [];

		$public_groups_only    = 'public' === Settings::get( 'visibility', 'public', 'cpc_pco_groups' );
		$enrollment_status     = Settings::get( 'enrollment_status', [], 'cpc_pco_groups' );
		$enrollment_strategies = Settings::get( 'enrollment_strategies', [], 'cpc_pco_groups' );

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

		$filter = new \CP_Connect\Setup\DataFilter(
			$filter_type,
			$conditions,
			$this->get_group_filter_config(),
			$relational_data
		);

		$filter->apply( $items ); // Apply the filter to the items

		return [
			'items'   => $items,
			'context' => [
				'relational_data' => $relational_data,
			],
			'taxonomies' => $taxonomies,
		];
	}

	/**
	 * Format a group for the CP Connect integration
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

		// Group Type
		if ( ! empty( $item_details['GroupType']['attributes']['name'] ) ) {
			$args['group_type'][] = $item_details['GroupType']['attributes']['name'];
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
	 *
	 * @return array {
	 * 	 @type array items   The raw events from PCO
	 * 	 @type array context The context for the data.
	 * }
	 * @throws ChMSException If there is an error fetching the events.
	 */
	public function fetch_events() {
		// Pull upcoming events
		$event_instances =
			$this->api()
				->module('calendar')
				->table('event_instances')
				->includes('event,event_times,tags')
				->filter('future')
				->order('starts_at')
				->get();

		$events =
			$this->api()
				->module( 'calendar' )
				->table('events')
				->includes('owner' )
				->get();

		$tag_groups =
			$this->api()
				->module( 'calendar' )
				->table('tag_groups')
				->get();

		if( !empty( $this->api()->errorMessage() ) ) {
			error_log( var_export( $this->api()->errorMessage(), true ) );
			return new ChMSError( 'pco_fetch_error', $this->api()->errorMessage() );
		}

		$items      = $event_instances['data'] ?? [];
		$events     = $events['data'] ?? [];
		$tag_groups = $tag_groups['data'] ?? [];

		$relational_data = [];
		$taxonomies      = [];

		foreach( $event_instances['included'] as $include ) {
			$relational_data[ $include['type'] ][ $include['id'] ] = $include;
		}

		foreach ( $events as $event ) {
			$relational_data[ $event['type'] ][ $event['id'] ] = $event;
		}

		$selected_tag_groups = Settings::get( 'tag_groups', [], 'cpc_pco_events' );
		$selected_tag_groups = wp_list_pluck( $selected_tag_groups, 'id' );

		foreach ( $tag_groups as $tag_group ) {
			if ( ! in_array( $tag_group['id'], $selected_tag_groups ) ) {
				continue;
			}

			$tax_slug = 'cpc_' . sanitize_title( $tag_group['attributes']['name'] );

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

		$filter_settings = Settings::get( 'filter', [], 'cpc_pco_events' );

		$filter_type = $filter_settings['type'] ?? 'all';
		$conditions  = $filter_settings['conditions'] ?? [];

		$public_events_only = 'public' === Settings::get( 'visibility', 'public', 'cpc_pco_events' );

		if ( $public_events_only ) {
			$conditions[] = [
				'compare' => 'is_not_empty',
				'type'    => 'visible_in_church_center',
			];
		}

		$filter = new \CP_Connect\Setup\DataFilter(
			$filter_type,
			$conditions,
			$this->get_event_filter_config(),
			$relational_data
		);

		$filter->apply( $items ); // Apply the filter to the items

		return [
			'items'   => $items,
			'context' => [
				'events'          => $events,
				'relational_data' => $relational_data,
			],
			'taxonomies' => $taxonomies,
		];
	}

	/**
	 * Format an event for the CP Connect integration
	 *
	 * @param array $event_instance The event instance to format.
	 * @param array $context The context for the data.
	 * @return array|bool The formatted event or false if the event should be skipped.
	 */
	public function format_event( $event_instance, $context ) {
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

		// // Fesatured image
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
	 * Event filter config
	 *
	 * @return array
	 */
	public function get_event_filter_config() {
		return [
			'start_date' => [
				'label' => __( 'Start Date', 'cp-sync' ),
				'path'  => 'attributes.starts_at',
			],
			'end_date' => [
				'label' => __( 'End Date', 'cp-sync' ),
				'path'  => 'attributes.ends_at',
			],
			'recurrence' => [
				'label' => __( 'Recurrence', 'cp-sync' ),
				'path'  => 'attributes.recurrence',
			],
			'recurrence_description' => [
				'label' => __( 'Recurrence Description', 'cp-sync' ),
				'path'  => 'attributes.recurrence_description',
			],
			'event_name' => [
				'label'         => __( 'Event Name', 'cp-sync' ),
				'path'          => 'relationships.event.data.id',
				'relation'      => 'Event',
				'relation_path' => 'attributes.name',
			],
			'visible_in_church_center' => [
				'label'         => __( 'Visible in Church Center', 'cp-sync' ),
				'path'          => 'relationships.event.data.id',
				'relation'      => 'Event',
				'relation_path' => 'attributes.visible_in_church_center',
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

}
