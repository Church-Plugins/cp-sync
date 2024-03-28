<?php

namespace CP_Connect\Integrations;

use CP_Connect\Admin\Settings;
use CP_Connect\ChMS\ChMSError;
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
			$integrations[ 'cp_groups' ] = '\CP_Connect\Integrations\CP_Groups';
		}

		if ( defined( 'TRIBE_EVENTS_FILE' ) ) {
			$integrations[ 'tec' ] = '\CP_Connect\Integrations\TEC';
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
		add_action( self::$_cron_hook, [ $this, 'pull_content' ] );
	}

	/** Actions ***************************************************/

	/**
	 * trigger the contant pull
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 * @return true|ChMSError
	 */
	public function pull_content() {
		$chms = \CP_Connect\Chms\_Init::get_instance()->get_active_chms_class();

		foreach( self::$_integrations as $integration ) {
			if ( $chms->supports( $integration->id ) ) {
				$result = $integration->pull_data_from_chms( $chms );

				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}

			return true;
		}
	}

	/**
	 * Pull a single integration
	 *
	 * @param string $integration_id The integration to pull.
	 *
	 * @return true|WP_Error
	 */
	public function pull_integration( $integration_id ) {
		$chms = \CP_Connect\Chms\_Init::get_instance()->get_active_chms_class();

		if ( ! isset( self::$_integrations[ $integration_id ] ) ) {
			return new WP_Error( 'invalid_integration', 'Invalid integration' );
		}

		$integration = self::$_integrations[ $integration_id ];

		if ( ! $chms->supports( $integration->id ) ) {
			return new WP_Error( 'integration_not_supported', 'Integration not supported' );
		}

		return $integration->pull_data_from_chms( $chms );
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

		foreach ( self::$_integrations as $integration ) {
			register_rest_route( 'cp-sync/v1', "/pull/{$integration->id}", [
				'methods'  => 'POST',
				'callback' => function() use ( $integration ) {
					$result = $this->pull_integration( $integration->id );
	
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
		if ( is_admin() && Settings::get( 'pull_now' ) ) {
			Settings::set( 'pull_now', '' );
			add_filter( 'cp_sync_process_hard_refresh', '__return_true' );
			do_action( self::$_cron_hook );
		}

		if ( wp_next_scheduled( self::$_cron_hook ) ) {
			return;
		}

		$args = apply_filters( 'cp_sync_cron_args', [
			'timestamp' => time() + HOUR_IN_SECONDS, // schedule to run in the future to allow time for setting up configuration
			'recurrence' => 'hourly',
		] );

		wp_schedule_event( $args[ 'timestamp' ], $args['recurrence'], self::$_cron_hook );
	}
}
