<?php
/**
 * ChMS integrations base functionality to be extended by specific integrations
 *
 * @package CP_Sync
 */

namespace CP_Sync\ChMS;

use CP_Sync\Admin\Settings;
use CP_Sync\Setup\RateLimiter;

/**
 * The base ChMS class must have the following abstractions:
 * - handle aggregating the full list of data from the ChMS to be sent to the integration handler
 * - Provide Rest API routes for the integration authentication
 * 
 * The child ChMS class must provide the following:
 * - A method to get all ChMS data based on a set of filters
 * - A method to format a single ChMS record into a standard format
 * - A method to authenticate with the ChMS
 * - A method to check the connection to the ChMS
 */

/**
 * Base class for ChMS integrations
 */
abstract class ChMS {

	/**
	 * Class instance
	 *
	 * @var self
	 */
	protected static $_instance;

	/**
	 * Unique ID for this integration
	 *
	 * @var self
	 */
	public $id;

	/**
	 * Type of integration
	 *
	 * @var string
	 */
	public $label;

	/**
	 * Settings key for this integration
	 *
	 * @var string
	 */
	public $settings_key = '';

	/**
	 * Array of supported integrations
	 *
	 * @var array
	 */
	protected $supported_integrations = [];

	/**
	 * Only make one instance of PostType
	 *
	 * @return self
	 */
	public static function get_instance() {
		$class = get_called_class();

		if ( ! self::$_instance instanceof $class ) {
			self::$_instance = new $class();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 */
	protected function __construct() {}

	/**
	 * Invoke the ChMS
	 */
	public function load() {
		$this->setup();

		add_action( 'cmb2_save_options-page_fields_cps_main_options_page', [ $this, 'maybe_add_connection_message' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		// add_action( 'admin_init', [ $this, 'maybe_save_token' ] );

		if ( Settings::get( 'pull_now' ) ) {
			add_action( 'admin_notices', [ $this, 'general_admin_notice' ] );
		}

		foreach ( $this->supported_integrations as $integration_type => $_args ) {
			add_filter( "cp_sync_pull_$integration_type", [ $this, 'get_formatted_data' ], 10, 2 );
		}
	}

	/**
	 * Attempt to save the token
	 */
	public function maybe_save_token() {
		if ( empty( $_GET['token'] ) || empty( $_GET['_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['_nonce'], 'cpSync' ) ) {
			return;
		}

		$refresh_token = isset( $_GET['refresh_token'] ) ? sanitize_text_field( $_GET['refresh_token'] ) : '';

		$this->save_token( sanitize_text_field( $_GET['token'] ), $refresh_token );
		cp_sync()->logging->log( 'Token saved' );

		wp_die( 'Authentication successful' );
	}
	
	/**
	 * Get group filter config for frontend.
	 */
	public function get_formatted_filter_config() {
		$output = [];

		foreach( $this->supported_integrations as $integration_type => $args ) {
			if( $args['filter_config'] && is_callable( $args['filter_config'] ) ) {
				$config = $args['filter_config']();

				$formatted = [];

				foreach( $config as $key => $filter ) {
					$config_item = [
						'label'    => $filter['label'],
						'type'     => $filter['type'] ?? 'text',
						'supports' => $filter['supports'] ?? [],
					];

					if ( isset( $filter['options'] ) ) {
						if ( is_array( $filter['options'] ) ) {
							$config_item['options'] = $filter['options'];
						} else if ( is_callable( $filter['options'] ) ) {
							// give path to REST endpoint
							$config_item['optionsFetcher'] = [
								'endpoint' => "/cp-sync/v1/selector/$integration_type/$key",
								'args'     => $filter['args'] ?? [],
							];
						}
					}

					$formatted[ $key ] = $config_item;
				}

				$output[ $integration_type ] = $formatted;
			}
		}

		return $output;	
	}

	/**
	 * Build REST endpoints based on filter configs
	 */
	public function register_filter_endpoints() {
		foreach ( $this->supported_integrations as $integration_type => $args ) {
			if ( ! isset( $args['filter_config'] ) ) {
				continue;
			}

			$filters = $args['filter_config']();

			foreach( $filters as $key => $filter) {
				if ( ! isset( $filter['options'] ) || ! is_callable( $filter['options'] ) ) {
					continue;
				}
	
				register_rest_route(
					'cp-sync/v1',
					"/selector/$integration_type/$key",
					[
						'methods'             => 'GET',
						'callback'            => $filter['options'],
						'args'                => $filter['args'] ?? [],
						'permission_callback' => function () {
							return current_user_can( 'manage_options' );
						},
					]
				);
			}
		}
	}

	/**
	 * Save the token
	 */
	public function save_token( $token, $refresh_token = '' ) {
		$this->update_setting( 'token', $token, 'auth' );
		$this->update_setting( 'last_token_refresh', time(), 'auth' );

		if ( $refresh_token ) {
			$this->update_setting( 'refresh_token', $refresh_token, 'auth' );
		}
	}

	/**
	 * Get the token
	 */
	public function get_token() {
		$this->get_setting( 'token', '', 'auth' );
	}

	/**
	 * Remove the token
	 *
	 * @author Tanner Moushey, 5/19/24
	 */
	public function remove_token() {
		$this->update_setting( 'token', '', 'auth' );
		$this->update_setting( 'last_token_refresh', '', 'auth' );
		$this->update_setting( 'refresh_token', '', 'auth' );
	}

	/**
	 * Display primary admin notice
	 */
	public function general_admin_notice() {
		echo '<div class="notice notice-success is-dismissible">
             <p>Processing pull request.</p>
         </div>';
	}

	/**
	 * Get a single setting
	 * 
	 * @param string $key The key to get.
	 * @param mixed $default The default value to return.
	 * @param string|false $group The group to get the setting from.
	 */
	public function get_setting( $key, $default = '', $group = false ) {
		$data = get_option( $this->settings_key, [] );

		if ( $group ) {
			$data = isset( $data[ $group ] ) ? $data[ $group ] : [];
		}

		return isset( $data[$key] ) ? $data[$key] : $default; 
	}


	/**
	 * Set the provided option
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return bool|string
	 *
	 * @author Tanner Moushey, 5/19/24
	 */
	public function update_setting( $key, $value, $group = false ) {
		if ( empty( $this->settings_key ) ) {
			return '';
		}

		$existing = get_option( $this->settings_key, [] );

		if ( $group ) {
			$existing[ $group ] = isset( $existing[ $group ] ) ? $existing[ $group ] : [];
			$existing[ $group ][ $key ] = $value;
		} else {
			$existing[ $key ] = $value;
		}

		return update_option( $this->settings_key, $existing );
	}

	/**
	 * Return the associated location id for the congregation id
	 *
	 * @param int $congregation_id The congregation id from PCO.
	 *
	 * @return false|mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_location_term( $congregation_id ) {
		$map = $this->get_congregation_map();

		if ( isset( $map[ $congregation_id ] ) ) {
			return $map[ $congregation_id ];
		}

		return false;
	}

	/**
	 * A map of values to associate the ChMS congregation ID with the correct Location
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_congregation_map() {
		return apply_filters( 'cp_sync_congregation_map', [] );
	}

	/**
	 * Utility to turn an aribratray string into a useable slug
	 *
	 * @param string $string The string to convert.
	 * @return string
	 */
	public static function string_to_slug( $string ) {
		return str_replace( ' ', '_', strtolower( $string ) );
	}

	/**
	 * Get preview feed
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_data_preview( $request ) {
		// integration type
		$integration_type = $request->get_param( 'type' );

		if ( ! $this->supports( $integration_type ) ) {
			return new ChMSError( 'unsupported_integration_type', 'The integration type is not supported' );
		}

		$data = $this->get_formatted_data( null, $integration_type, 10 );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// format data into normalized response
		$preview = [];

		foreach ( $data['posts'] as $item ) {
			$preview_item = [
				'chmsID'    => $item['chms_id'] ?? '',
				'title'     => $item['post_title'] ?? '',
				'content'   => $item['post_content'] ?? '',
				'thumbnail' => $item['thumbnail_url'] ?? '',
				'meta'      => []
			];

			foreach ( $item['tax_input'] as $taxonomy => $term_ids ) {
				$tax_label = $data['taxonomies'][ $taxonomy ]['plural_label'];
				$tax_terms = $data['taxonomies'][ $taxonomy ]['terms'];

				$preview_item['meta'][ $tax_label ] = array_map( fn( $term_id ) => $tax_terms[ $term_id ], $term_ids );
			}

			$preview[] = $preview_item;
		}

		return rest_ensure_response( [
			'items' => $preview,
			'count' => $data['count'] ?? 0,
		] );	
	}

	/**
	 * Setup integrations
	 *
	 * @since 1.0.0
	 */
	public function setup() {}

	/**
	 * Check the connection to the ChMS
	 *
	 * @since  1.0.4
	 *
	 * @return null
	 */
	public function maybe_add_connection_message() {
		$response = $this->check_connection();

		if ( ! $response ) {
			return;
		}

		$response['type'] = 'success' === $response['status'] ? 'updated' : 'error';
		update_option( 'cp_settings_message', $response );
	}

	/**
	 * Check the connection to the ChMS
	 *
	 * @since  1.0.4
	 *
	 * @return bool | array
	 */
	public function check_connection() {
		return false;
	}

	/**
	 * Register ChMS specific rest routes
	 *
	 * @since 1.1.0
	 */
	public function register_rest_routes() {
		$this->register_filter_endpoints();

		$this->add_rest_route(
			"preview/(?P<type>[a-zA-Z0-9-]+)",
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_data_preview' ],
			]
		);

		$this->add_rest_route(
			'check-connection',
			[
				'methods'  => 'GET',
				'callback' => function () {
					$data = $this->check_connection();

					if ( ! $data ) {
						return rest_ensure_response( [ 'connected' => false, 'message' => __( 'No connection data found', 'cp-sync' ) ] );
					}

					return rest_ensure_response(
						[
							'connected' => 'success' === $data['status'],
							'message'   => $data['message'],
						]
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);
		
		$this->add_rest_route(
			'disconnect',
			[
				'methods'  => 'POST',
				'callback' => function ( $request ) {
					try {
						$this->remove_token();
						return rest_ensure_response( [ 'success' => true ] );
					} catch ( \Exception $e ) {
						return new \WP_Error( 'authentication_failed', $e->getMessage(), [ 'status' => 401 ] );
					}
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				}
			]
		);
	}

	/**
	 * Get the settings for the ChMS
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function get_settings( $request ) {
		$settings = get_option( "cp_sync_{$this->id}_settings", [] );

		return rest_ensure_response( $settings );
	}

	/**
	 * Save the settings for the ChMS
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function save_settings( $request ) {
		$settings = $request->get_param( 'data' );

		update_option( "cp_sync_{$this->id}_settings", $settings );

		return rest_ensure_response( $settings );
	}

	/**
	 * Shortcut for registering a rest route
	 *
	 * @param string $route The route to register.
	 * @param array  $args The args to register the route with.
	 */
	public function add_rest_route( $path, $args ) {
		register_rest_route( 'cp-sync/v1', "/$this->id/$path", $args );
	}

	/**
	 * Add support for an integration type
	 *
	 * @param string $integration_type The integration type to add support for.
	 * @param array  $args The args to register handlers for the integration.
	 */
	public function add_support( $integration_type, $args ) {
		$this->supported_integrations[ $integration_type ] = $args;
	}

	/**
	 * Check if the ChMS supports the integration type
	 *
	 * @param string $integration_type The integration type to check.
	 * @return bool
	 */
	public function supports( $integration_type ) {
		return isset( $this->supported_integrations[ $integration_type ] );
	}

	/**
	 * Get all data from the ChMS
	 *
	 * @param array|null $existing_data The existing data that has been pulled.
	 * @param string $integration_type The integration type to get data for.
	 * @param number $limit The number of items to pull.
	 * @return array|ChMSError
	 */
	public function get_formatted_data( $existing_data, $integration_type, $limit = 0 ) {
		$integration_args = $this->supported_integrations[ $integration_type ];

		if ( ! is_callable( $integration_args['fetch_callback'] ) ) {
			return new ChMSError( 'fetch_callback_not_set', 'The fetch callback is not set' );
		}

		if ( ! is_callable( $integration_args['format_callback'] ) ) {
			return new ChMSError( 'format_callback_not_set', 'The format callback is not set' );
		}

		$data = call_user_func( $integration_args['fetch_callback'] );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$formatted_items = [];

		$items      = $data['items'];
		$context    = $data['context'];
		$taxonomies = $data['taxonomies'] ?? [];

		$item_count = count( $items );

		for ( $i = 0; $i < $item_count; $i++ ) {
			$item = $items[ $i ];

			if ( count( $formatted_items ) >= $limit ) {
				break;
			}

			try {
				$data = call_user_func( $integration_args['format_callback'], $item, $context );
			} catch ( ChMSException $e ) {

				// check if it is a rate limit error
				if ( 429 === $e->getCode() ) {
					$seconds = $e->getData()['wait'] ?? 5;

					sleep( $seconds ); // wait for the rate limit to reset

					// reprocess the item
					$i--;
					continue;
				} else {
					error_log( $e->getMessage() );
					continue; // skip the item
				}
			}

			if ( $data ) {
				$formatted_items[] = $data;
			}			
		}

		return [
			'posts'      => $formatted_items,
			'taxonomies' => $taxonomies,
			'count'      => $item_count,
		];
	}
}
