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
	 * REST namespace for this integration
	 *
	 * @var string
	 */
	public $rest_namespace;

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
	protected function __construct() {
		$this->setup();

		add_action( 'init', [ $this, 'integrations' ], 500 );
		add_action( 'cmb2_save_options-page_fields_cps_main_options_page', [ $this, 'maybe_add_connection_message' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		if ( Settings::get( 'pull_now' ) ) {
			add_action( 'admin_notices', [ $this, 'general_admin_notice' ] );
		}
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
	 * Add the API settings from the ChMS-specific settings
	 *
	 * @since  1.1.0
	 *
	 * @param string $key The key for the setting.
	 * @param mixed  $default The default value for the setting.
	 *
	 * @return mixed|string|void
	 * @author Tanner Moushey, 12/20/23
	 */
	public function get_option( $key, $default = '' ) {
		if ( empty( $this->settings_key ) ) {
			return '';
		}

		return Settings::get( $key, $default, $this->settings_key );
	}

	/**
	 * Add the hooks for the supported integrations
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	abstract public function integrations();

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
	 * Setup integrations
	 *
	 * @since 1.1.0
	 */
	public function setup() {}

	/**
	 * Auth schema properties
	 *
	 * @return array
	 */
	abstract public function get_auth_api_args();

	/**
	 * Check authentication
	 *
	 * @param \stdClass $data The data to check.
	 * @return true
	 * @throws \Exception If the authentication fails.
	 */
	abstract public function check_auth( $data );

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
	public function register_rest_routes() {}

	/**
	 * Shortcut for registering a rest route
	 *
	 * @param string $route The route to register.
	 * @param array  $args The args to register the route with.
	 */
	public function add_rest_route( $path, $args ) {
		register_rest_route( 'cp-sync/v1', "$this->rest_namespace/$path", $args );
	}

	/**
	 * Add support for an integration
	 *
	 * @param string $integration_id The integration id to add support for.
	 * @param array  $args The args to register handlers for the integration.
	 */
	public function add_support( $integration_id, $args ) {
		$this->supported_integrations[ $integration_id ] = $args;
	}

	/**
	 * Check if the ChMS supports the integration
	 *
	 * @param string $integration_id The integration id to check.
	 * @return bool
	 */
	public function supports( $integration_id ) {
		return isset( $this->supported_integrations[ $integration_id ] );
	}

	/**
	 * Get all data from the ChMS
	 *
	 * @param array $filters The filters to apply to the data.
	 * @return array|ChMSError
	 */
	public function get_formatted_data( $integration_id ) {
		if ( ! $this->supports( $integration_id ) ) {
			return new ChMSError( 'integration_not_supported', 'The integration is not supported' );
		}

		$integration_args = $this->supported_integrations[ $integration_id ];

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
		];
	}
}
