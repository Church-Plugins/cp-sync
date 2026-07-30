<?php

namespace CP_Sync\ChMS;
use PlanningCenterAPI\PlanningCenterAPI;

/**
 * Planning Center Online implementation
 */
class PCO extends \CP_Sync\ChMS\ChMS {
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

		// Sermons pull from the PCO Publishing app into CP Library. Registered
		// unconditionally ( like Groups/Events ) so the Sync Sermons toggle always renders
		// for PCO; availability ( CP Library active ) is carried by the toggle's disabled
		// state and enforced by Integrations\_Init::pull_integration().
		$this->add_support(
			'sermons',
			[
				'fetch_callback'   => [ $this, 'fetch_sermons' ],
				'format_callback'  => [ $this, 'format_sermon' ],
				'filter_config'    => [ $this, 'get_sermon_filter_config' ],
			]
		);

	}

	/**
	 * Load the active PCO integration.
	 *
	 * Adds the one-time `none` → sync-toggle migration on `admin_init` ( it only
	 * needs to run when PCO is the active ChMS, which is exactly when load() runs ).
	 *
	 * @return void
	 */
	public function load() {
		parent::load();

		add_action( 'admin_init', [ $this, 'maybe_migrate_none_source' ] );
	}

	/**
	 * Compute the migrated settings for a legacy `ecp.source === 'none'` install.
	 *
	 * "Do not pull" is no longer a radio option; its meaning is carried by the
	 * `connect.sync_events` toggle. When the stored source is `none` this returns a
	 * copy of the settings with `connect.sync_events = false` and
	 * `ecp.source = 'calendar'`. Any other ( already-migrated / normal ) source
	 * returns null — signalling no change, which makes the migration idempotent.
	 *
	 * Pure ( no WP ) so it is unit-testable in isolation.
	 *
	 * @since 0.5.0
	 * @param array $settings The full stored settings array ( groups keyed by name ).
	 * @return array|null The migrated settings, or null when nothing changes.
	 */
	public static function migrate_none_source_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return null;
		}

		$source = isset( $settings['ecp']['source'] ) ? $settings['ecp']['source'] : null;

		if ( 'none' !== $source ) {
			return null;
		}

		$settings['ecp']['source']          = 'calendar';
		$settings['connect']['sync_events']  = false;

		return $settings;
	}

	/**
	 * Self-erasing `none` migration, hooked on admin_init.
	 *
	 * One option read + one conditional write: if the pure transform reports a
	 * change ( legacy `none` install ), persist it; otherwise do nothing. Running
	 * again is a no-op because `source` is then `calendar`.
	 *
	 * @return void
	 */
	public function maybe_migrate_none_source() {
		if ( empty( $this->settings_key ) ) {
			return;
		}

		$settings = get_option( $this->settings_key, [] );
		$migrated = self::migrate_none_source_settings( $settings );

		if ( null === $migrated ) {
			return;
		}

		update_option( $this->settings_key, $migrated );
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
		$response = wp_remote_post( CP_SYNC_OAUTH_URL . '/wp-content/themes/churchplugins/oauth/pco/', $request_args );

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

		// These routes feed admin-only option selectors; gate them behind the same
		// capability check the rest of the ChMS routes use.
		$options_permission = function () {
			return current_user_can( 'manage_options' );
		};

		$this->add_rest_route(
			'groups/types',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'fetch_group_types' ],
				'permission_callback' => $options_permission,
			]
		);

		$this->add_rest_route(
			'groups/tag_groups',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'fetch_group_tag_groups' ],
				'permission_callback' => $options_permission,
			]
		);

		$this->add_rest_route(
			'groups/tag_groups/(?P<tag_group>\d+)/tags',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'fetch_group_tags' ],
				'permission_callback' => $options_permission,
			]
		);

		$this->add_rest_route(
			'events/tag_groups',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'fetch_event_tag_groups' ],
				'permission_callback' => $options_permission,
			]
		);

		$this->add_rest_route(
			'events/tag_groups/(?P<tag_group>\d+)/tags',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'fetch_event_tags' ],
				'permission_callback' => $options_permission,
			]
		);

		$this->add_rest_route(
			'events/registration_categories',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'fetch_event_registration_categories' ],
				'permission_callback' => $options_permission,
			]
		);
	}

	/**
	 * Declare the PCO settings screens as schema data.
	 *
	 * Screen keys equal the stored settings groups the current tabs write to:
	 *   - `connect`  → OAuth-only ( no persisted form fields; the connect/disconnect
	 *                  flow is an action widget composed by the tab ).
	 *   - `cp_groups`→ the Groups tab.
	 *   - `ecp`      → the Events tab.
	 *
	 * Field keys equal the exact stored setting keys ( verified against the tab
	 * `updateField()` calls ). Additive only — nothing consumes this yet.
	 *
	 * @since 0.4.0
	 * @return array
	 */
	public function get_settings_schema() {
		$schema = [
			// OAuth connect/disconnect is an action widget ( no persisted credential
			// fields ), but the screen carries the Groups/Events sync-enable toggles so
			// they are stored under `connect.sync_groups` / `connect.sync_events`.
			'connect' => [
				'label'    => __( 'Connect', 'cp-sync' ),
				'sections' => [
					[
						'title'  => __( 'Sync', 'cp-sync' ),
						'fields' => $this->get_sync_toggle_fields(),
					],
				],
			],
			'cp_groups' => [
				'label'    => __( 'Groups', 'cp-sync' ),
				'sections' => [
					[
						'fields' => [
							'types' => [
								'type'     => 'async-multiselect',
								'label'    => __( 'Group Types', 'cp-sync' ),
								'endpoint' => '/cp-sync/v1/pco/groups/types',
							],
							'tag_groups' => [
								'type'     => 'async-multiselect',
								'label'    => __( 'Relevant Tag Groups to Include', 'cp-sync' ),
								'help'     => __( 'Pull these tag groups as separate taxonomies for CP Groups.', 'cp-sync' ),
								'endpoint' => '/cp-sync/v1/pco/groups/tag_groups',
							],
							'visibility' => [
								'type'    => 'radio',
								'label'   => __( 'Visibility', 'cp-sync' ),
								'default' => 'public',
								'options' => [
									[ 'value' => 'all', 'label' => __( 'Show All', 'cp-sync' ) ],
									[ 'value' => 'public', 'label' => __( 'Only Visible in Church Center', 'cp-sync' ) ],
								],
							],
							'filter' => [
								'type'        => 'filter-builder',
								'label'       => __( 'Groups', 'cp-sync' ),
								'filterGroup' => 'groups',
							],
						],
					],
				],
			],
			'ecp' => [
				'label'    => __( 'Events', 'cp-sync' ),
				'sections' => [
					[
						'fields' => [
							'source' => [
								'type'    => 'radio',
								'label'   => __( 'Event source', 'cp-sync' ),
								'default' => 'calendar',
								'options' => [
									[ 'value' => 'calendar', 'label' => __( 'Pull from Calendar', 'cp-sync' ) ],
									[ 'value' => 'registrations', 'label' => __( 'Pull from Registrations', 'cp-sync' ) ],
									[ 'value' => 'both', 'label' => __( 'Calendar AND Registrations', 'cp-sync' ) ],
								],
							],
							// The calendar-only controls below mirror the tab, which only
							// render them when `source` includes calendar ( `calendar` or
							// `both` ). `show_if.is` is an array = in-list match.
							'tag_groups' => [
								'type'     => 'async-multiselect',
								'label'    => __( 'Tag groups', 'cp-sync' ),
								'help'     => __( 'Pull these tag groups as separate taxonomies for The Events Calendar.', 'cp-sync' ),
								'endpoint' => '/cp-sync/v1/pco/events/tag_groups',
								'show_if'  => [ 'field' => 'source', 'is' => [ 'calendar', 'both' ] ],
							],
							'visibility' => [
								'type'    => 'radio',
								'label'   => __( 'Visibility', 'cp-sync' ),
								'default' => 'public',
								'options' => [
									[ 'value' => 'all', 'label' => __( 'Show All', 'cp-sync' ) ],
									[ 'value' => 'public', 'label' => __( 'Only Visible in Church Center', 'cp-sync' ) ],
								],
								'show_if' => [ 'field' => 'source', 'is' => [ 'calendar', 'both' ] ],
							],
							// When both sources are enabled the two filter builders sit
							// adjacent; this disclaimer renders above them ( field order )
							// to warn that events are not deduplicated across the two apps.
							'events_dedup_notice' => [
								'type'    => 'notice',
								'message' => __( 'Events are not deduplicated across Calendar and Registrations — an event published in both will import twice.', 'cp-sync' ),
								'show_if' => [ 'field' => 'source', 'is' => 'both' ],
							],
							'filter' => [
								'type'        => 'filter-builder',
								'label'       => __( 'Calendar Filters', 'cp-sync' ),
								'filterGroup' => 'events',
								'show_if'     => [ 'field' => 'source', 'is' => [ 'calendar', 'both' ] ],
							],
							'registration_filter' => [
								'type'        => 'filter-builder',
								'label'       => __( 'Registrations Filters', 'cp-sync' ),
								'filterGroup' => 'events_registrations',
								'show_if'     => [ 'field' => 'source', 'is' => [ 'registrations', 'both' ] ],
							],
						],
					],
				],
			],
		];

		// Sermons ( PCO Publishing → CP Library ). Persisted under the `cp_library`
		// settings group; only sermons published to the Church Center library are pulled.
		// Always declared ( like Groups/Events ); the Sermons tab is gated client-side on
		// the Sync Sermons toggle's enabled/available state.
		$schema['cp_library'] = [
			'label'    => __( 'Sermons', 'cp-sync' ),
			'sections' => [
				[
					'description' => __( 'Only sermons published to your Church Center library are synced.', 'cp-sync' ),
					'fields'      => [
						'filter' => [
							'type'        => 'filter-builder',
							'label'       => __( 'Sermons', 'cp-sync' ),
							'filterGroup' => 'sermons',
						],
					],
				],
			],
		];

		return $schema;
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
			return [
				'status'  => 'success',
				'message' => 'Connection successful',
				'account' => $this->get_account_details( $response['data'] ),
			];
		}

		cp_sync()->logging->log( 'PCO connection check failed: ' . $this->api()->errorMessage() );
		return false;
	}

	/**
	 * Resolve which PCO account this connection belongs to.
	 *
	 * The person name comes free from the `/people/v2/me` response the connection
	 * check already makes. The ORGANIZATION name (the real "which account" signal
	 * when juggling test vs production) requires one extra call to the People
	 * module root, so it is cached in the `auth` settings group keyed by the
	 * organization id — the extra request happens once per connected org, not on
	 * every settings-page load.
	 *
	 * @param array $me The `data` object from the `/people/v2/me` response.
	 * @return array{person: string, organization: string}
	 */
	protected function get_account_details( $me ) {
		$person = $me['attributes']['name'] ?? '';
		$org_id = $me['relationships']['organization']['data']['id'] ?? '';

		$cached = $this->get_setting( 'account', [], 'auth' );

		if ( $org_id && ( $cached['organization_id'] ?? null ) === $org_id && ! empty( $cached['organization'] ) ) {
			$org_name = $cached['organization'];
		} else {
			// Module root ( /people/v2/ ) returns the Organization object.
			$root     = $this->api()->module( 'people' )->table( '' )->get( 1 );
			$org_name = $root['data']['attributes']['name'] ?? '';

			if ( $org_id && $org_name ) {
				$this->update_setting(
					'account',
					[
						'organization'    => $org_name,
						'organization_id' => $org_id,
					],
					'auth'
				);
			}
		}

		return [
			'person'       => $person,
			'organization' => $org_name,
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
	 * @param int $limit The number of groups to fetch.
	 * @return array {
	 * 	 @type array items   The raw groups from PCO
	 * 	 @type array context The context for the data.
	 * }
	 */
	public function fetch_groups( $limit = 0 ) {
		// Pull groups here
		$api = $this->api()
			->module( 'groups' )
			->table( 'groups' )
			->includes( 'location,group_type,enrollment' );

		// When the visibility setting is "Only Visible in Church Center", push the
		// restriction to the API ( filter=published — "groups that are published on
		// Church Center" ) so unlisted groups are never fetched at all. The
		// client-side public_church_center_web_url condition remains as defence in
		// depth; this just avoids paging through groups that would be discarded.
		if ( 'public' === $this->get_setting( 'visibility', 'public', 'cp_groups' ) ) {
			$api->filter( 'published' );
		}

		$raw = $api->get();

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
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Always caught in ChMS::get_formatted_data() and only written to the log via error_log() (never echoed to the browser); HTML-escaping would corrupt the log output.
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
				'leaders'       => [
					[ 'name' => '', 'email' => $group['attributes']['contact_email'] ?? '', ]
				],
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
	 * Map the stored `source` setting to the list of enabled event sources.
	 *
	 * Pure ( no WP / no API ) so it is unit-testable. `none` and any unknown
	 * value resolve to an empty list, which fetch_events() turns into a
	 * ChMSError no-op ( Review amendment #1 — an empty item list must never
	 * reach process()'s leftover pass or every imported event is hard-deleted ).
	 *
	 * @since 0.5.0
	 * @param string $source Stored `ecp.source` value.
	 * @return string[] Ordered list of enabled sources ( subset of calendar|registrations ).
	 */
	public static function events_sources_for( $source ) {
		switch ( $source ) {
			case 'calendar':
				return [ 'calendar' ];
			case 'registrations':
				return [ 'registrations' ];
			case 'both':
				return [ 'calendar', 'registrations' ];
			default:
				return []; // none / unknown → no-op ( amendment #1 ).
		}
	}

	/**
	 * Merge per-source fetch results into one fetch payload.
	 *
	 * Pure ( no WP / no API ). Each raw item is tagged with `_cp_source` so
	 * format_event() can dispatch and hand each formatter its own source's
	 * relational data — the tag lives ONLY on the raw item, never on formatted
	 * output ( amendment #8: the store-key hash must not change for calendar
	 * installs ). Relational data is namespaced per source ( amendment #6 ) so
	 * EventTime / SignupTime ids from the two independent apps cannot collide.
	 * Taxonomies come from calendar only.
	 *
	 * @since 0.5.0
	 * @param array $results Map of source => fetch result ( items/context/taxonomies ).
	 * @return array { items, context => [ relational_data => [ source => data ] ], taxonomies }
	 */
	public static function merge_event_sources( array $results ) {
		$items      = [];
		$relational = [];
		$taxonomies = [];

		foreach ( $results as $source => $result ) {
			$relational[ $source ] = $result['context']['relational_data'] ?? [];

			foreach ( ( $result['items'] ?? [] ) as $item ) {
				$item['_cp_source'] = $source;
				$items[]            = $item;
			}

			// Only calendar contributes taxonomies ( registrations have none ).
			if ( 'calendar' === $source && ! empty( $result['taxonomies'] ) ) {
				$taxonomies = $result['taxonomies'];
			}
		}

		return [
			'items'      => $items,
			'context'    => [ 'relational_data' => $relational ],
			'taxonomies' => $taxonomies,
		];
	}

	/**
	 * Fetch events from PCO ( Calendar and/or Registrations ).
	 *
	 * Runs every enabled source's fetcher, tags + merges the raw items, and
	 * namespaces each source's relational data. Amendment #1 no-op / abort rules:
	 *   - empty enabled list ( `none`/unknown ) → ChMSError, so process() skips
	 *     entirely and nothing is deleted;
	 *   - if ANY enabled source's fetch errors → abort the whole pull with that
	 *     error, so the healthy source's items are never mistaken for leftovers.
	 */
	public function fetch_events() {
		$source  = $this->get_setting( 'source', 'calendar', 'ecp' );
		$sources = self::events_sources_for( $source );

		cp_sync()->logging->log( 'Fetching events from ' . $source );

		if ( empty( $sources ) ) {
			return new ChMSError( 'pco_fetch_error', 'No event source enabled' );
		}

		$results = [];

		foreach ( $sources as $src ) {
			if ( 'calendar' === $src ) {
				$result = $this->fetch_events_from_calendar();
			} else {
				$result = $this->fetch_events_from_registrations();
			}

			// Partial-failure abort ( amendment #1 ).
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$results[ $src ] = $result;
		}

		return self::merge_event_sources( $results );
	}

	/**
	 * Format an event.
	 *
	 * Dispatches per raw item on its `_cp_source` tag ( set at merge time ) and
	 * passes each source-specific formatter ONLY that source's relational_data
	 * slice ( context.relational_data.<source> ). Untagged items fall back to the
	 * legacy single-source behavior for safety.
	 */
	public function format_event( $event, $context ) {
		$source = isset( $event['_cp_source'] ) ? $event['_cp_source'] : null;

		// Dual-source path: namespaced relational data, one slice per formatter.
		if ( null !== $source && isset( $context['relational_data'][ $source ] ) ) {
			$sub_context = [ 'relational_data' => $context['relational_data'][ $source ] ];

			if ( 'calendar' === $source ) {
				return $this->format_event_from_calendar( $event, $sub_context );
			}
			if ( 'registrations' === $source ) {
				return $this->format_event_from_registrations( $event, $sub_context );
			}
			return false;
		}

		// Fallback ( untagged item / non-namespaced context ): legacy behavior.
		$stored = $this->get_setting( 'source', 'calendar', 'ecp' );
		if ( 'registrations' === $stored ) {
			return $this->format_event_from_registrations( $event, $context );
		}
		return $this->format_event_from_calendar( $event, $context );
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
		cp_sync()->logging->log( 'Starting PCO registration events import' );

		// Documented Registrations API (2025-05-01): the model is `signups`, related
		// dates/location/categories are included as SignupTime / SignupLocation /
		// Category. Signup supports ordering by created_at/updated_at only (no
		// starts_at), and exposes no filter param — so we order by -created_at and
		// skip archived signups client-side below. `at_maximum_capacity` is served
		// only when explicitly requested via a sparse fieldset, so we list every
		// Signup attribute we consume (adding one to a fields request drops the
		// rest for that type). If PCO rejects the shape the pull still succeeds and
		// the sold-out meta is simply omitted (guarded in the formatter).
		$raw_events = $this->api()
			->module( 'registrations' )
			->table( 'signups' )
			->includes( 'signup_times,signup_location,categories' )
			->order( '-created_at' )
			// NOTE: per JSON:API, a sparse fieldset restricts RELATIONSHIPS as well as
			// attributes — the relationship names must be listed or the payload loses
			// the signup_times/signup_location/categories linkage entirely.
			->param( 'fields[Signup]', 'name,description,logo_url,new_registration_url,archived,at_maximum_capacity,signup_times,signup_location,categories' )
			->get();

		// Log API errors
		if ( ! empty( $this->api()->errorMessage() ) ) {
			cp_sync()->logging->log( 'PCO API Error: ' . var_export( $this->api()->errorMessage(), true ) );
		}

		$items = $raw_events['data'] ?? [];

		// No filter param exists on signups; drop archived signups here instead.
		$items = array_values( array_filter( $items, function( $item ) {
			return true !== ( $item['attributes']['archived'] ?? false );
		} ) );

		cp_sync()->logging->log( sprintf( 'PCO API returned %d registration signups', count( $items ) ) );

		if ( empty( $items ) ) {
			cp_sync()->logging->log( 'No registration signups found in PCO (check that signups exist and are not archived)' );
		}

		$relational_data = [];
		foreach( $raw_events['included'] ?? [] as $include ) {
			$relational_data[ $include['type'] ][ $include['id'] ] = $include;
		}

		// Registrations read their OWN filter ( `ecp.registration_filter` ); the
		// calendar keeps `ecp.filter`. ( Amendment #7 — the legacy code read
		// `ecp.filter` and `ecp.visibility` here; both are dropped for signups. )
		$filter_settings = $this->get_setting( 'registration_filter', [], 'ecp' );
		cp_sync()->logging->log( sprintf( 'Filter settings: type=%s, conditions=%d', $filter_settings['type'] ?? 'all', count( $filter_settings['conditions'] ?? [] ) ) );

		$filter_type = $filter_settings['type'] ?? 'all';
		$conditions  = $filter_settings['conditions'] ?? [];

		$filter = new \CP_Sync\Setup\DataFilter(
			$filter_type,
			$conditions,
			$this->get_registration_filter_config(),
			$relational_data
		);

		$filter->apply( $items ); // Apply the filter to the items

		// Log filtering results
		$filtered_count = count( $items );
		cp_sync()->logging->log( sprintf( 'After filtering: %d events remain for import', $filtered_count ) );
		
		if ( $filtered_count > 0 ) {
			$event_names = wp_list_pluck( array_slice( $items, 0, 5 ), 'attributes' );
			$event_names = wp_list_pluck( $event_names, 'name' );
			cp_sync()->logging->log( 'Sample events to import: ' . implode( ', ', $event_names ) . ( $filtered_count > 5 ? '...' : '' ) );
		}

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
			'chms_id'        => $event_instance['id'],
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
		];

		// Featured image
		if ( ! empty( $event['attributes']['image_url'] ) ) {
			$args['thumbnail_url'] = $event['attributes']['image_url'];
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
	public function format_event_from_registrations( $signup, $context ) {
		$relational_data = $context['relational_data'];

		// Dates live on the related SignupTime rows; use the first one. Without a
		// usable start/end there is nothing to import as a dated event.
		$time_ids  = wp_list_pluck( $signup['relationships']['signup_times']['data'] ?? [], 'id' );
		$time_id   = current( $time_ids );

		if ( empty( $time_id ) ) {
			// e.g. an "ongoing" registration — no scheduled dates, so it cannot become
			// a dated event. Log it so the fetched-vs-processed counts add up.
			cp_sync()->logging->log( sprintf(
				'Skipping signup %s (%s): no scheduled dates (signup_times is empty)',
				$signup['id'] ?? '?',
				$signup['attributes']['name'] ?? 'unnamed'
			) );
			return false;
		}

		$start_date = $relational_data['SignupTime'][ $time_id ]['attributes']['starts_at'] ?? '';
		$end_date   = $relational_data['SignupTime'][ $time_id ]['attributes']['ends_at'] ?? '';
		$all_day    = $relational_data['SignupTime'][ $time_id ]['attributes']['all_day'] ?? false;

		if ( ! $start_date || ! $end_date ) {
			cp_sync()->logging->log( sprintf(
				'Skipping signup %s (%s): signup time has no usable start/end date',
				$signup['id'] ?? '?',
				$signup['attributes']['name'] ?? 'unnamed'
			) );
			return false;
		}

		$start_date = ( new \DateTime( $start_date ) )->setTimezone( wp_timezone() );
		$end_date   = ( new \DateTime( $end_date ) )->setTimezone( wp_timezone() );

		// Begin stuffing the output. chms_id is prefixed with `reg_` so registrations
		// and calendar events never collide in the shared store keyspace (plan §3/§4).
		$args = [
			'chms_id'        => 'reg_' . $signup['id'],
			'post_status'    => 'publish',
			'post_title'     => $signup['attributes']['name'] ?? '',
			'post_content'   => $signup['attributes']['description'] ?? '',
			'tax_input'      => [],
			'event_category' => [],
			'thumbnail_url'  => '',
			// The signup page is the event's public website (TEC "Event Website"
			// field, mapped from EventURL in Integrations\TEC). Empty values are
			// dropped downstream by array_filter.
			'EventURL'       => $signup['attributes']['new_registration_url'] ?? '',
			'meta_input'     => [
				'registration_url' => $signup['attributes']['new_registration_url'] ?? '',
			],
			'EventStartDate'   => $start_date->format( 'Y-m-d' ),
			'EventStartHour'   => $start_date->format( 'G' ),
			'EventStartMinute' => $start_date->format( 'i' ),
			'EventEndDate'     => $end_date->format( 'Y-m-d' ),
			'EventEndHour'     => $end_date->format( 'G' ),
			'EventEndMinute'   => $end_date->format( 'i' ),
		];

		if ( $all_day ) {
			$args['EventAllDay'] = true;
		}

		// at_maximum_capacity is only present when the sparse fieldset request
		// succeeded; expose sold-out state only when we actually have the value.
		if ( array_key_exists( 'at_maximum_capacity', $signup['attributes'] ?? [] ) ) {
			$args['meta_input']['registration_sold_out'] = ! empty( $signup['attributes']['at_maximum_capacity'] );
		}

		// Featured image
		if ( ! empty( $signup['attributes']['logo_url'] ) ) {
			$args['thumbnail_url'] = $signup['attributes']['logo_url'];
		}

		// Categories. The documented Category vertex has no slug, so derive a stable
		// slug from the name (TEC does the same when given a non-string key).
		$category_ids = wp_list_pluck( $signup['relationships']['categories']['data'] ?? [], 'id' );
		$categories   = [];
		foreach ( $category_ids as $category_id ) {
			$data = $relational_data['Category'][ $category_id ]['attributes'] ?? [];
			if ( empty( $data['name'] ) ) {
				continue;
			}
			$categories[ sanitize_title( $data['name'] ) ] = $data['name'];
		}

		if ( ! empty( $categories ) ) {
			$args['event_category'] = $categories;
		}

		// Location. signup_location is a to-one relationship; normalize to a list so
		// either an object or an array of one is handled. NOTE: despite the docs
		// listing `address_data`, the live API returns a FLAT shape (name,
		// formatted_address, latitude, longitude, …) — verified against a real
		// account 2026-07-29 — so this uses the dedicated flat parser, NOT the
		// Google-components get_location_details().
		$location_rel = $signup['relationships']['signup_location']['data'] ?? [];
		if ( isset( $location_rel['id'] ) ) {
			$location_rel = [ $location_rel ];
		}
		$location_ids = wp_list_pluck( $location_rel, 'id' );
		foreach ( $location_ids as $location_id ) {
			$data     = $relational_data['SignupLocation'][ $location_id ]['attributes'] ?? [];
			$location = self::parse_signup_location( $data );
			if ( ! empty( $location['venue'] ) ) {
				$args['EventVenue'] = $location;
				break;
			}
		}

		return $args;
	}

	/**
	 * Parse the live SignupLocation attribute shape into TEC venue args.
	 *
	 * The documented Registrations API lists an `address_data` json attribute, but the
	 * live API ( verified 2026-07-29 ) returns a flat shape instead:
	 *   { name, formatted_address ( "street\nCity, ST 12345" ), latitude, longitude,
	 *     location_type, subpremise, url }
	 *
	 * The second address line is parsed as "City, ST ZIP" when it matches that
	 * ( US-style ) pattern; otherwise the raw line is kept as the City so non-US
	 * addresses degrade to name + street + raw-locality rather than being dropped.
	 *
	 * Returns the shape TEC consumes ( `$item['EventVenue']` in Integrations\TEC ):
	 * lowercase `venue`/`address`/`city`/`state`/`zip` keys. Pure ( no WordPress
	 * calls ) so it is unit-testable.
	 *
	 * @param array $attrs The SignupLocation attributes.
	 * @return array TEC EventVenue args ( venue, address?, city?, state?, zip? ), or []
	 *               when there is nothing usable.
	 */
	public static function parse_signup_location( $attrs ) {
		$name    = trim( (string) ( $attrs['name'] ?? '' ) );
		$address = trim( (string) ( $attrs['formatted_address'] ?? '' ) );

		if ( '' === $name && '' === $address ) {
			return [];
		}

		// The venue needs SOME name; fall back to the first address line.
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $address ) ) ) );

		$venue = [
			'venue' => '' !== $name ? $name : ( $lines[0] ?? '' ),
		];

		if ( ! empty( $lines[0] ) ) {
			$venue['address'] = $lines[0];
		}

		if ( ! empty( $lines[1] ) ) {
			if ( preg_match( '/^(.+),\s*([A-Za-z]{2})\s+([0-9][0-9-]{3,9})\z/', $lines[1], $m ) ) {
				$venue['city']  = $m[1];
				$venue['state'] = strtoupper( $m[2] );
				$venue['zip']   = $m[3];
			} else {
				// Non-US / unparseable locality: keep it rather than drop it.
				$venue['city'] = $lines[1];
			}
		}

		return $venue;
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
			// Signup has no date attributes of its own; the dates live on the related
			// SignupTime rows. DataFilter's relation lookup resolves a single related
			// id, so we point at the FIRST signup_time (`...data.0.id`) and read its
			// starts_at/ends_at. Signups with multiple times are filtered on their
			// first occurrence — a documented, pragmatic limitation of the single-id
			// relation mechanism (see review notes / needs live verification).
			'start_date' => [
				'label'         => __( 'Start Date', 'cp-sync' ),
				'path'          => 'relationships.signup_times.data.0.id',
				'relation'      => 'SignupTime',
				'relation_path' => 'attributes.starts_at',
				'type'          => 'date',
				'supports'      => [ 'is_greater_than', 'is_less_than' ],
			],
			'end_date' => [
				'label'         => __( 'End Date', 'cp-sync' ),
				'path'          => 'relationships.signup_times.data.0.id',
				'relation'      => 'SignupTime',
				'relation_path' => 'attributes.ends_at',
				'type'          => 'date',
				'supports'      => [ 'is_greater_than', 'is_less_than' ],
			],
			'event_name' => [
				'label'    => __( 'Event Name', 'cp-sync' ),
				'path'     => 'attributes.name',
				'type'     => 'text',
				'supports' => [ 'contains', 'does_not_contain', 'is_empty', 'is_not_empty', 'is', 'is_not' ],
			],
			'registration_category' => [
				'label'    => __( 'Category', 'cp-sync' ),
				'path'     => 'relationships.categories.data',
				'format'   => fn( $value ) => wp_list_pluck( $value, 'id' ),
				'type'     => 'select',
				'supports' => [ 'is', 'is_not', 'is_empty', 'is_not_empty', 'is_in', 'is_not_in' ],
			],
		];
	}

	/**
	 * Client-facing filter config, adding the `events_registrations` group.
	 *
	 * The base serializer only iterates registered integration types ( groups,
	 * events, sermons ), and it attaches an `optionsFetcher` ONLY for fields that
	 * declare a callable `options`. The registrations config is a pseudo-type ( it
	 * shares the one `events` integration ) and its `registration_category` field
	 * has no options callable — its option route is the dedicated
	 * `/events/registration_categories` endpoint. So the group is hand-built here
	 * ( amendment #3 ) rather than run through the generic formatter: project each
	 * field's label/type/supports, and wire `registration_category` explicitly to
	 * its REST endpoint. The `format` closure is intentionally NOT projected — it
	 * is server-only ( used by the DataFilter in the fetcher ).
	 *
	 * @since 0.5.0
	 * @return array
	 */
	public function get_formatted_filter_config() {
		$output = parent::get_formatted_filter_config();

		$formatted = [];

		foreach ( $this->get_registration_filter_config() as $key => $filter ) {
			$config_item = [
				'label'    => $filter['label'],
				'type'     => $filter['type'] ?? 'text',
				'supports' => $filter['supports'] ?? [],
			];

			if ( 'registration_category' === $key ) {
				$config_item['optionsFetcher'] = [
					'endpoint' => '/cp-sync/v1/pco/events/registration_categories',
					'args'     => [],
				];
			}

			$formatted[ $key ] = $config_item;
		}

		$output['events_registrations'] = $formatted;

		return $output;
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

		// value/label shape so the filter-builder select ( events_registrations
		// group ) resolves options directly — the stored condition value is the
		// PCO Category id, matched server-side by get_registration_filter_config().
		foreach ( $tags as $tag ) {
			$formatted[] = [
				'value' => $tag['id'],
				'label' => $tag['attributes']['name'] ?? '',
			];
		}

		return wp_send_json_success( $formatted, 200 );
	}


	/**
	 * Fetch sermons ( episodes ) from the PCO Publishing app.
	 *
	 * One paginated /publishing/v2/episodes call ( with series + speakerships included )
	 * plus a single /publishing/v2/speakers lookup hydrates everything the formatter
	 * needs. Only episodes published to the Church Center library are returned.
	 *
	 * @param int $limit Optional. Max episodes to fetch ( 0 = all ).
	 * @return array { items, taxonomies, context } consumed by ChMS::get_formatted_data().
	 */
	public function fetch_sermons( $limit = 0 ) {
		// Build a speaker lookup ( id => display name ) once, so each episode's
		// speakerships can be resolved to a name without an N+1 call per episode.
		$speakers_by_id = [];
		$speakers_raw   = $this->api()
			->module( 'publishing' )
			->table( 'speakers' )
			->get();

		foreach ( ( $speakers_raw['data'] ?? [] ) as $speaker ) {
			$attr = $speaker['attributes'] ?? [];
			$name = $attr['formatted_name'] ?? trim( ( $attr['first_name'] ?? '' ) . ' ' . ( $attr['last_name'] ?? '' ) );
			$speakers_by_id[ $speaker['id'] ] = $name;
		}

		// Fetch episodes, newest library publish first, with series + speakerships +
		// channel joined ( channel populates relationships.channel so the DataFilter can
		// match the Channel filter condition client-side ).
		// Always fetch the full published set — NOT capped by $limit. The published-only
		// pass and the client-side DataFilter must run against every episode; if we only
		// fetched the newest $limit, a filter like Channel would see just those and wrongly
		// return nothing ( the matching episodes live deeper in the list ). get_formatted_data()
		// caps how many survivors are FORMATTED for the preview.
		$raw = $this->api()
			->module( 'publishing' )
			->table( 'episodes' )
			->includes( 'series,speakerships,channel' )
			->order( '-published_to_library_at' )
			->get();

		$items = ( ! empty( $raw['data'] ) && is_array( $raw['data'] ) ) ? $raw['data'] : [];

		// Index the JSON:API included[] payload by type => id for relational lookups.
		$relational_data = [];
		foreach ( ( $raw['included'] ?? [] ) as $include ) {
			$relational_data[ $include['type'] ][ $include['id'] ] = $include;
		}

		// Published-only: drop episodes that are not published to the library.
		$items = array_values(
			array_filter(
				$items,
				function( $episode ) {
					return ! empty( $episode['attributes']['published_to_library_at'] );
				}
			)
		);

		// Apply the admin-configured filter ( stored under the `cp_library` group ).
		$filter_settings = $this->get_setting( 'filter', [], 'cp_library' );
		$filter_type     = $filter_settings['type'] ?? 'all';
		$conditions      = $filter_settings['conditions'] ?? [];

		$filter = new \CP_Sync\Setup\DataFilter(
			$filter_type,
			$conditions,
			$this->get_sermon_filter_config(),
			$relational_data
		);

		$filter->apply( $items );

		return [
			'items'      => $items,
			'taxonomies' => [],
			'context'    => [
				'relational_data' => $relational_data,
				'speakers_by_id'  => $speakers_by_id,
			],
		];
	}

	/**
	 * Format a single PCO episode into the CP Library sermon item shape.
	 *
	 * The returned `cpl` sub-array is consumed by Integrations\CP_Library::update_item(),
	 * which delegates the actual write to CP Library's SermonSync facade.
	 *
	 * @param array $episode The raw PCO episode record.
	 * @param array $context { relational_data, speakers_by_id } from fetch_sermons().
	 * @return array The formatted item.
	 */
	public function format_sermon( $episode, $context ) {
		$relational_data = $context['relational_data'] ?? [];
		$speakers_by_id  = $context['speakers_by_id'] ?? [];

		$attr = $episode['attributes'] ?? [];

		$published = $attr['published_to_library_at'] ?? ( $attr['published_live_at'] ?? '' );
		$date_ts   = $published ? strtotime( $published ) : time();

		// Series ( to_one ).
		$series     = null;
		$series_rel = $episode['relationships']['series']['data'] ?? null;
		if ( ! empty( $series_rel['id'] ) ) {
			$series_obj = $relational_data['Series'][ $series_rel['id'] ] ?? null;
			if ( $series_obj && ! empty( $series_obj['attributes']['title'] ) ) {
				$series = [
					'id'    => $series_rel['id'],
					'title' => $series_obj['attributes']['title'],
				];
			}
		}

		// Speakers ( via speakerships join, resolved through the speaker lookup ).
		$speakers        = [];
		$speakership_rel = $episode['relationships']['speakerships']['data'] ?? [];
		foreach ( (array) $speakership_rel as $sref ) {
			if ( empty( $sref['id'] ) ) {
				continue;
			}

			$speakership = $relational_data['Speakership'][ $sref['id'] ] ?? null;
			if ( ! $speakership ) {
				continue;
			}

			$speaker_id = $speakership['relationships']['speaker']['data']['id'] ?? null;
			if ( empty( $speaker_id ) ) {
				continue;
			}

			$name = $speakers_by_id[ $speaker_id ] ?? '';
			if ( '' === trim( (string) $name ) ) {
				continue;
			}

			$speakers[] = [ 'id' => $speaker_id, 'name' => $name ];
		}

		// Media: prefer the Church Center library URLs, falling back to the raw video URL.
		$video_url = $attr['library_video_url'] ?? '';
		if ( '' === trim( (string) $video_url ) ) {
			$video_url = $attr['video_url'] ?? '';
		}
		$audio_url = $attr['library_audio_url'] ?? '';

		return [
			'chms_id'       => $episode['id'],
			'post_status'   => 'publish',
			'post_title'    => $attr['title'] ?? '',
			'post_content'  => $attr['description'] ?? '',
			'thumbnail_url' => $this->get_episode_art( $attr ),
			'tax_input'     => [],
			'cpl'           => [
				'date'      => $date_ts,
				'series'    => $series,
				'speakers'  => $speakers,
				'video_url' => $video_url,
				'audio_url' => $audio_url,
			],
		];
	}

	/**
	 * Resolve a usable image URL from an episode's art hash / thumbnail fields.
	 *
	 * PCO returns `art` as a variable-shape hash; fall back to the video thumbnail URLs
	 * when no direct art URL is present.
	 *
	 * @param array $attr The episode attributes.
	 * @return string The image URL, or '' when none is available.
	 */
	protected function get_episode_art( $attr ) {
		$art = $attr['art'] ?? null;

		if ( is_array( $art ) ) {
			foreach ( [ 'original', 'detail', 'thumbnail', '16x9', '1x1' ] as $key ) {
				if ( ! empty( $art[ $key ] ) && is_string( $art[ $key ] ) ) {
					return $art[ $key ];
				}
			}

			foreach ( $art as $value ) {
				if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return $value;
				}
			}
		} elseif ( is_string( $art ) && '' !== $art ) {
			return $art;
		}

		if ( ! empty( $attr['library_video_thumbnail_url'] ) ) {
			return $attr['library_video_thumbnail_url'];
		}

		return $attr['video_thumbnail_url'] ?? '';
	}

	/**
	 * Filter configuration for the sermons ( episodes ) feed.
	 *
	 * @return array
	 */
	public function get_sermon_filter_config() {
		return [
			'title' => [
				'label'    => __( 'Title', 'cp-sync' ),
				'path'     => 'attributes.title',
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
			'channel' => [
				'label'    => __( 'Channel', 'cp-sync' ),
				'path'     => 'relationships.channel.data.id',
				'type'     => 'select',
				'supports' => [
					'is',
					'is_not',
					'is_in',
					'is_not_in',
				],
				'options'  => function() {
					$raw = $this->api()
						->module( 'publishing' )
						->table( 'channels' )
						->get();

					if ( ! empty( $this->api()->errorMessage() ) ) {
						return new ChMSError( 'pco_fetch_error', $this->api()->errorMessage() );
					}

					$channels = $raw['data'] ?? [];

					$formatted = [];
					foreach ( $channels as $channel ) {
						$formatted[] = [
							'value' => $channel['id'],
							'label' => $channel['attributes']['name'] ?? '',
						];
					}

					return wp_send_json_success( $formatted, 200 );
				},
			],
			'series' => [
				'label'    => __( 'Series', 'cp-sync' ),
				'path'     => 'relationships.series.data.id',
				'type'     => 'select',
				'supports' => [
					'is',
					'is_not',
					'is_in',
					'is_not_in',
					'is_empty',
					'is_not_empty',
				],
				'options'  => function() {
					$raw = $this->api()
						->module( 'publishing' )
						->table( 'series' )
						->order( '-started_at' )
						->get();

					if ( ! empty( $this->api()->errorMessage() ) ) {
						return new ChMSError( 'pco_fetch_error', $this->api()->errorMessage() );
					}

					$series = $raw['data'] ?? [];

					$formatted = [];
					foreach ( $series as $item ) {
						$formatted[] = [
							'value' => $item['id'],
							'label' => $item['attributes']['title'] ?? '',
						];
					}

					return wp_send_json_success( $formatted, 200 );
				},
			],
		];
	}

}
