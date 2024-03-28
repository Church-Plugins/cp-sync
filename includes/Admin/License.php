<?php

namespace CP_Connect\Admin;

/**
 * Plugin licensing class
 *
 * TODO: Determine whether the "EDD" keys/functionality contained in this class is appropriate
 *
 */
class License {

	/**
	 * @var License
	 */
	protected static $_instance;

	/**
	 * Only make one instance of \RCP_UM\License
	 *
	 * @return License
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof License ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 *
	 */
	protected function __construct() {
		add_action( 'admin_init', array( $this, 'check_license'      ) );
		add_action( 'admin_init', array( $this, 'activate_license'   ) );
		add_action( 'admin_init', array( $this, 'deactivate_license' ) );
		add_action( 'admin_init', array( $this, 'plugin_updater'     ) );
	}

	/**
	 * Handle License activation
	 *
	 * @return void
	 */
	public function activate_license() {

		// listen for our activate button to be clicked
		if ( ! isset( $_POST['cp_sync_license_activate'], $_POST['cp_sync_nonce'] ) ) {
			return;
		}

		// run a quick security check
		if ( ! check_admin_referer( 'cp_sync_nonce', 'cp_sync_nonce' ) ) {
			return;
		} // get out if we didn't click the Activate button

		// retrieve the license from the database
		$license = trim( get_option( 'cp_sync_license_key' ) );

		// data to send in our API request
		$api_params = array(
			'edd_action' => 'activate_license',
			'license'    => $license,
			'item_name'  => urlencode( CP_SYNC_ITEM_NAME ), // the name of our product in EDD
			'url'        => home_url()
		);

		// Call the custom API.
		$response = wp_remote_post( CP_SYNC_STORE_URL, array(
			'timeout'   => 15,
			'sslverify' => false,
			'body'      => $api_params
		) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) ) {
			return;
		}

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// $license_data->license will be either "valid" or "invalid"
		update_option( 'cp_sync_license_status', $license_data->license );
		delete_transient( 'cp_sync_license_check' );

	}

	/**
	 * Handle License deactivation
	 *
	 * @return void
	 */
	public function deactivate_license() {

		// listen for our activate button to be clicked
		if ( ! isset( $_POST['cp_sync_license_deactivate'], $_POST['cp_sync_nonce'] ) ) {
			return;
		}

		// run a quick security check
		if ( ! check_admin_referer( 'cp_sync_nonce', 'cp_sync_nonce' ) ) {
			return;
		}

		// retrieve the license from the database
		$license = trim( get_option( 'cp_sync_license_key' ) );

		// data to send in our API request
		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $license,
			'item_name'  => urlencode( CP_SYNC_ITEM_NAME ), // the name of our product in EDD
			'url'        => home_url()
		);

		// Call the custom API.
		$response = wp_remote_post( RCPTX_STORE_URL, array(
			'timeout'   => 15,
			'sslverify' => false,
			'body'      => $api_params
		) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) ) {
			return;
		}

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// $license_data->license will be either "deactivated" or "failed"
		if ( $license_data->license == 'deactivated' ) {
			delete_option( 'cp_sync_license_status' );
			delete_transient( 'cp_sync_license_check' );
		}

	}

	/**
	 * Check license
	 *
	 * @return void
	 * @since       1.0.0
	 */
	public function check_license() {

		// Don't fire when saving settings
		if ( ! empty( $_POST['cp_sync_nonce'] ) ) {
			return;
		}

		$license = get_option( 'cp_sync_license_key' );
		$status  = get_transient( 'cp_sync_license_check' );

		if ( $status === false && $license ) {

			$api_params = array(
				'edd_action' => 'check_license',
				'license'    => trim( $license ),
				'item_name'  => urlencode( CP_SYNC_ITEM_NAME ),
				'url'        => home_url()
			);

			$response = wp_remote_post( CP_SYNC_STORE_URL, array( 'timeout' => 35, 'sslverify' => false, 'body' => $api_params ) );

			if ( is_wp_error( $response ) ) {
				return;
			}

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			$status = $license_data->license;

			update_option( 'cp_sync_license_status', $status );

			set_transient( 'cp_sync_license_check', $license_data->license, DAY_IN_SECONDS );

			if ( $status !== 'valid' ) {
				delete_option( 'cp_sync_license_status' );
			}
		}

	}

	/**
	 * Plugin Updater
	 *
	 * @return void
	 */
	public function plugin_updater() {
		// load our custom updater
		if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
			include( CP_SYNC_PLUGIN_DIR . '/includes/updater.php' );
		}

		// retrieve our license key from the DB
		$license_key = trim( get_option( 'cp_sync_license_key' ) );

		// setup the updater
		new \EDD_SL_Plugin_Updater( CP_SYNC_STORE_URL, CP_SYNC_PLUGIN_FILE, array(
				'version'   => CP_SYNC_PLUGIN_VERSION,    // current version number
				'license'   => $license_key,     // license key (used get_option above to retrieve from DB)
				'item_name' => urlencode( CP_SYNC_ITEM_NAME ), // the name of our product in EDD
				'author'    => 'Tanner Moushey'  // author of this plugin
			)
		);

	}
}
