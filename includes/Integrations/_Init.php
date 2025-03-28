<?php

namespace CP_Sync\Integrations;

use CP_Sync\Admin\Settings;
use CP_Sync\ChMS\ChMSError;
use WP_Error;

/**
 * Setup integration initialization
 */
class _Init {

	/**
	 * @var _Init
	 */
	protected static $_instance;

	/**
	 * The string to use for the cron pull
	 *
	 * @var string
	 */
	public static $_cron_hook = 'cp_sync_pull';

	/**
	 * @var Integration[]
	 */
	protected static $_integrations = [];

	/**
	 * @var string[]
	 */
	public static $supported_types = [
		'groups',
		'events',
	];

	/**
	 * Only make one instance of _Init
	 *
	 * @return _Init
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof _Init ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 */
	protected function __construct() {
		$this->includes();
		$this->actions();
	}

	/**
	 * Admin init includes
	 *
	 * @return void
	 */
	protected function includes() {
		$integrations = [];

		if ( function_exists( 'cp_groups' ) ) {
			$integrations[ 'cp_groups' ] = '\CP_Sync\Integrations\CP_Groups';
		}

		if ( defined( 'TRIBE_EVENTS_FILE' ) ) {
			$integrations[ 'tec' ] = '\CP_Sync\Integrations\TEC';
		}

		foreach( $integrations as $key => $integration ) {
			if ( ! class_exists( $integration ) ) {
				continue;
			}

			self::$_integrations[ $key ] = new $integration;
		}
	}

	/**
	 * Return integrations
	 *
	 * @return Integration[]
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_integrations() {
		return self::$_integrations;
	}

	/**
	 * Handle actions
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	protected function actions() {
		add_action( 'init', [ $this, 'schedule_cron' ], 999 );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'cp_sync_global_settings_updated', [ $this, 'reschedule_cron' ], 10, 2 );
		add_action( self::$_cron_hook, [ $this, 'pull_content' ] );
	}

	/** Actions ***************************************************/

	/**
	 * trigger the contant pull
	 *
	 * @since  1.0.0
	 * 
	 * @return true|WP_Error
	 */
	public function pull_content() {
		foreach( self::$supported_types as $type ) {
			$result = $this->pull_integration( $type );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}

	/**
	 * Pull a single integration
	 *
	 * @param string $integration_id The integration id to pull.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure, false if no data was pulled.
	 */
	public function pull_integration( $integration_type ) {
		if ( ! in_array( $integration_type, self::$supported_types ) ) {
			return new WP_Error( 'invalid_integration_type', 'Invalid integration type. Supported types are `' . implode( '`, `', self::$supported_types ) . '`' );
		}

		/**
		 * Pull data from the ChMS. The active ChMS will hook in and
		 * return the formatted data if it supports the integration type.
		 *
		 * @param array|null $result The result of the pull.
		 * @param string     $integration_type The integration type to pull.
		 * @return array|null|WP_Error
		 */
		$data = apply_filters( "cp_sync_pull_$integration_type", null, $integration_type );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( null !== $data ) {
			// process the data for all integrations that have this type
			foreach( self::$_integrations as $integration ) {
				if ( $integration->type === $integration_type ) {
					$integration->process_formatted_data( $data );
				}
			}
			
			return true;
		}

		return false;
	}

	/**
	 * Register rest API routes
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route( 'cp-sync/v1', '/pull', [
			'methods'  => 'POST',
			'callback' => function() {
				set_time_limit( 0 );

				$result = $this->pull_content();

				if ( is_wp_error( $result ) ) {
					return new \WP_Error( 'pull_error', $result->get_error_message() );
				}

				return wp_send_json_success();
			},
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		] );

		register_rest_route('cp-sync/v1', '/get-log', array(
			'methods' => 'GET',
			'callback' => function() {
				return cp_sync()->logging->get_file_contents();
			},
			'permission_callback' => function() {
				return current_user_can('manage_options');
			}
		));

		register_rest_route('cp-sync/v1', '/clear-log', array(
			'methods' => 'POST',
			'callback' => function() {
				return cp_sync()->logging->clear_log_file();
			},
			'permission_callback' => function() {
				return current_user_can('manage_options');
			}
		));

		foreach ( self::$supported_types as $type ) {
			register_rest_route( 'cp-sync/v1', "/pull/{$type}", [
				'methods'  => 'POST',
				'callback' => function() use ( $type ) {
					$result = $this->pull_integration( $type );
	
					if ( is_wp_error( $result ) ) {
						return $result;
					}
	
					return wp_send_json_success();
				},
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			] );
		}		
	}


	/**
	 * Schedule the cron to pull data from the ChMS
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function schedule_cron() {
		if ( wp_next_scheduled( self::$_cron_hook ) ) {
			return;
		}

		$args = apply_filters( 'cp_sync_cron_args', [
			'timestamp'  => time() + HOUR_IN_SECONDS, // schedule to run in the future to allow time for setting up configuration
			'recurrence' => Settings::get( 'updateInterval', 'hourly', 'cp_sync_settings' ),
		] );

		wp_schedule_event( $args[ 'timestamp' ], $args['recurrence'], self::$_cron_hook );
	}
	
	/**
	 * Reschedule the cron to pull data from the ChMS
	 *
	 * @param array $settings The new settings
	 * @param array $old_settings The old settings
	 */
	public function reschedule_cron( $settings, $old_settings ) {
		if ( $settings['updateInterval'] !== $old_settings['updateInterval'] ) {
			wp_clear_scheduled_hook( self::$_cron_hook );
			$this->schedule_cron();
		}
	}
}
