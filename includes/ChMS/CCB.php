<?php

namespace CP_Sync\ChMS;

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

		// $this->add_support(
		// 	'events',
		// 	[
		// 		'fetch_callback'   => [ $this, 'fetch_events' ],
		// 		'format_callback'  => [ $this, 'format_event' ],
		// 		'filter_config'    => [ $this, 'get_event_filter_config' ],
		// 	]
		// );
	}

	/**
	 * Get the API instance
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
	 * @param array $args The arguments to fetch groups
	 * @return array
	 */
	public function fetch_groups( $args ) {
		$access_token = $this->get_token();

		if ( ! $access_token ) {
			return [];
		}

		// make request to churchplugins endpoint to fetch groups
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
				'options'      => function() {
					$group_types = $this->api()->get( 'group_types', [ 'per_page' => 100 ] );
					$options = [];
					foreach ( $group_types as $group_type ) {
						$options[] = [
							'value' => $group_type['id'],
							'label' => $group_type['name'],
						];
					}
					return wp_send_json_success( $options );
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
			// 'location' => [
			// 	'label' => __( 'Location', 'cp-sync' ),
			// 	'path'  => 'relationships.location.data.id',
			// 	'type' => 'text',
			// 	'supports' => [
			// 		'is',
			// 		'is_not',
			// 		'is_empty',
			// 		'is_not_empty',
			// 	],
			// ],
			// 'enrollment_status' => [
			// 	'label'         => __( 'Enrollment Status', 'cp-sync' ),
			// 	'path'          => 'relationships.enrollment.data.id',
			// 	'relation'      => 'Enrollment',
			// 	'relation_path' => 'attributes.status',
			// 	'type'          => 'select',
			// 	'supports'      => [
			// 		'is',
			// 		'is_not',
			// 		'is_in',
			// 		'is_not_in',
			// 	],
			// 	'options' => [
			// 		[ 'value' => 'open', 'label' => 'Open' ],
			// 		[ 'value' => 'closed', 'label' => 'Closed' ],
			// 		[ 'value' => 'full', 'label' => 'Full' ],
			// 		[ 'value' => 'private', 'label' => 'Private' ],
			// 	],
			// ],
			// 'enrollment_strategy' => [
			// 	'label'         => __( 'Enrollment Strategy', 'cp-sync' ),
			// 	'path'          => 'relationships.enrollment.data.id',
			// 	'relation'      => 'Enrollment',
			// 	'relation_path' => 'attributes.strategy',
			// 	'type'          => 'select',
			// 	'supports'      => [
			// 		'is',
			// 		'is_not',
			// 		'is_in',
			// 		'is_not_in',
			// 	],
			// 	'options' => [
			// 		[ 'value' => 'request_to_join', 'label' => 'Request to Join' ],
			// 		[ 'value' => 'open_signup', 'label' => 'Open Signup' ],
			// 	],
			// ],
			// 'visibility' => [
			// 	'label' => __( 'Visibility', 'cp-sync' ),
			// 	'path'  => 'attributes.public_church_center_web_url',
			// 	'type'  => 'text',
			// 	'supports' => [
			// 		'is_empty',
			// 		'is_not_empty',
			// 	],
			// ],
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
