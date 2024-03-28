<?php
/**
 * Setup ChMS integration
 *
 * @package CP_Sync
 */

namespace CP_Sync\ChMS;

use CP_Sync\Admin\Settings;
use WP_Error;

require_once CP_SYNC_PLUGIN_DIR . '/includes/ChMS/cli/PCO.php';
require_once CP_SYNC_PLUGIN_DIR . '/includes/ChMS/ccb-api/ccb-api.php';

/**
 * Setup integration initialization
 */
class _Init {

	/**
	 * Class instance
	 *
	 * @var _Init
	 */
	protected static $_instance;

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
		$this->actions();
	}

	/**
	 * ChMS init includes
	 *
	 * @return void
	 */
	protected function actions() {
		add_action( 'init', [ $this, 'includes' ], 5 );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/** Actions ***************************************************/

	/**
	 * Register rest routes.
	 *
	 * @since  1.1.0
	 */
	public function register_rest_routes() {
		$chms = $this->get_active_chms_class();

		if ( ! $chms ) {
			return;
		}

		register_rest_route(
			'cp-sync/v1',
			"$chms->rest_namespace/check-connection",
			[
				'methods'  => 'GET',
				'callback' => function () use ( $chms ) {
					$data = $chms->check_connection();

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

		register_rest_route(
			'cp-sync/v1',
			"$chms->rest_namespace/authenticate",
			[
				'methods'  => 'POST',
				'callback' => function ( $request ) use ( $chms ) {
					try {
						$authorized = $chms->check_auth( $request->get_param( 'data' ) );
						return rest_ensure_response( [ 'authorized' => $authorized ] );
					} catch ( \Exception $e ) {
						return new \WP_Error( 'authentication_failed', $e->getMessage(), [ 'status' => 401 ] );
					}
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args' => $chms->get_auth_api_args(),
			]
		);
	}

	/**
	 * Admin init includes
	 *
	 * @return void
	 */
	public function includes() {
		$this->get_active_chms_class(); // Trigger the active ChMS class to load
	}

	/**
	 * Get the active ChMS class
	 *
	 * @return \CP_Sync\ChMS\ChMS | false
	 */
	public function get_active_chms_class() {
		$active_chms = $this->get_active_chms();

		switch ( $active_chms ) {
			case 'mp':
				return MinistryPlatform::get_instance();
			case 'pco':
				return PCO::get_instance();
			case 'ccb':
				return ChurchCommunityBuilder::get_instance();
		}

		return false;
	}

	/**
	 * Get the active ChMS
	 *
	 * @return string
	 */
	public function get_active_chms() {
		/**
		 * Filter the active ChMS
		 *
		 * @param string The active ChMS.
		 * @return string
		 */
		return apply_filters( 'cp_sync_active_chms', Settings::get( 'chms' ) );
	}
}
