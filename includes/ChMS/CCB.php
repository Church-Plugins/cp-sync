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
	 * @var string The settings key for this integration
	 */
	public $settings_key = 'cp_sync_ccb_settings';

	/**
	 * Setup
	 */
	public function setup() {}

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
	public function refresh_token() {}

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
		return [ 'status' => 'success', 'message' => 'Connection successful' ];
	}
}
