<?php

namespace CP_Sync\Integrations;

use CP_Sync\Admin\Settings;
use CP_Sync\ChMS\ChMSError;
use CP_Sync\Setup\Reset;
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
		'sermons',
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

		if ( function_exists( 'cp_library' ) ) {
			$integrations[ 'cp_library' ] = '\CP_Sync\Integrations\CP_Library';
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
	 * Whether an integration type's required companion plugin is active.
	 *
	 * The two pinned conditions ( mirroring includes() ): Groups needs the CP Groups
	 * plugin ( cp_groups() ), Events needs The Events Calendar ( TRIBE_EVENTS_FILE ).
	 * Any other type is treated as available ( it is gated elsewhere, e.g. the
	 * supported-types check in pull_integration() ).
	 *
	 * @since 0.4.0
	 * @param string $type The integration type ( 'groups' | 'events' ).
	 * @return bool
	 */
	public static function is_integration_available( $type ) {
		switch ( $type ) {
			case 'groups':
				return function_exists( 'cp_groups' );
			case 'events':
				return defined( 'TRIBE_EVENTS_FILE' );
			case 'sermons':
				return function_exists( 'cp_library' );
			default:
				return true;
		}
	}

	/**
	 * The user-facing "required plugin missing" explanation for an integration type.
	 *
	 * Shared by the schema's disabled-toggle help text and the pull guard's WP_Error so
	 * both surfaces speak with one voice.
	 *
	 * @since 0.4.0
	 * @param string $type The integration type ( 'groups' | 'events' ).
	 * @return string
	 */
	public static function integration_unavailable_message( $type ) {
		switch ( $type ) {
			case 'groups':
				return __( 'Requires the CP Groups plugin, which is not active on this site.', 'cp-sync' );
			case 'events':
				return __( 'Requires The Events Calendar plugin, which is not active on this site.', 'cp-sync' );
			case 'sermons':
				return __( 'Requires the CP Library plugin, which is not active on this site.', 'cp-sync' );
			default:
				return __( 'This integration is not available on this site.', 'cp-sync' );
		}
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
				// A type that is unavailable ( plugin missing ) or disabled in settings is
				// a deliberate skip, not a failure — keep pulling the remaining types so a
				// disabled Groups feed never blocks the Events feed ( and vice versa ) on
				// the bulk /pull route or the cron run.
				if ( in_array( $result->get_error_code(), [ 'integration_unavailable', 'integration_disabled' ], true ) ) {
					continue;
				}
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

		// (a) Availability: the required companion plugin must be active. Guards the
		// manual pull buttons, the REST /pull + /pull/{type} routes, and the cron path
		// ( which funnels through pull_content() → here ).
		if ( ! self::is_integration_available( $integration_type ) ) {
			$message = self::integration_unavailable_message( $integration_type );
			cp_sync()->logging->log( sprintf( 'Skipping %s pull: %s', $integration_type, $message ) );
			return new WP_Error( 'integration_unavailable', $message );
		}

		// (b) Enable toggle: the active ChMS's `connect.sync_{type}` setting ( default
		// true, so existing installs are unaffected ). A missing/true value pulls; an
		// explicit false skips.
		$active_chms = \CP_Sync\ChMS\_Init::get_instance()->get_active_chms_class();
		if ( $active_chms && ! $active_chms->get_setting( "sync_{$integration_type}", true, 'connect' ) ) {
			$message = sprintf(
				/* translators: %s: the integration type being synced (e.g. Groups, Events). */
				__( '%s sync is disabled in CP Sync settings.', 'cp-sync' ),
				ucfirst( $integration_type )
			);
			cp_sync()->logging->log( sprintf( 'Skipping %s pull: %s', $integration_type, $message ) );
			return new WP_Error( 'integration_disabled', $message );
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

		// Reset / clear install data. Lives alongside the other cross-cutting
		// operational routes ( /pull, /get-log, /clear-log ) rather than in the
		// per-ChMS routes because a reset spans every integration and every ChMS.
		register_rest_route( 'cp-sync/v1', '/reset', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_reset_request' ],
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		] );

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
	 * Handle a POST /cp-sync/v1/reset request.
	 *
	 * Contract:
	 *   body: { level: 'queue'|'state'|'content'|'connection'|'all',
	 *           confirm: '<the level string, retyped>' }
	 *   - unknown level      -> WP_Error( 'invalid_level', 400 )
	 *   - confirm !== level   -> WP_Error( 'confirm_mismatch', 400 )
	 *   - success            -> { success: true, level, summary: {...} }
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function handle_reset_request( $request ) {
		$level   = sanitize_key( $request->get_param( 'level' ) );
		$confirm = $request->get_param( 'confirm' );

		if ( ! Reset::is_valid_level( $level ) ) {
			return new WP_Error(
				'invalid_level',
				__( 'Invalid reset level.', 'cp-sync' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! Reset::confirm_matches( $level, $confirm ) ) {
			return new WP_Error(
				'confirm_mismatch',
				__( 'The confirmation does not match the requested level.', 'cp-sync' ),
				[ 'status' => 400 ]
			);
		}

		set_time_limit( 0 );

		$reset   = new Reset();
		$summary = $reset->run( $level );

		return rest_ensure_response( [
			'success' => true,
			'level'   => $level,
			'summary' => $summary,
		] );
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
