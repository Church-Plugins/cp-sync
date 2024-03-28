<?php
/**
 * Handle database updates when the plugin is updated.
 *
 * @package CP_Sync
 */

namespace CP_Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VersionUpdate
 *
 * @since 1.1.0
 */
class VersionUpdate {

	/**
	 * Class instance
	 *
	 * @var VersionUpdate
	 */
	protected static $instance;

	/**
	 * Get class instance
	 *
	 * @return VersionUpdate
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof VersionUpdate ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'update' ) );
	}

	/**
	 * Update the plugin
	 */
	public function update() {
		$this->upgrade_to_cmb2();
	}

	/**
	 * Transfer old options to CMB2 options
	 * Check if some old options exists.
	 * If they do, migrate them to new options and delete the old ones.
	 * For upgrades from < 1.1.0
	 *
	 * @since 1.1.0
	 */
	public function upgrade_to_cmb2() {
		$mp_api_config = get_option( 'ministry_platform_api_config' );

		// if ministry platform hasn't been configured or we've already migrated, exit.
		if ( ! $mp_api_config ) {
			return;
		}

		$custom_group_mapping = get_option( 'cp_group_custom_field_mapping' );
		$group_mapping        = get_option( 'ministry_platform_group_mapping' );
		$cps_main_options     = get_option( 'cps_main_options', array() );
		$cps_mp_options       = get_option( 'cps_mp_options', array() );

		$cps_main_options['chms'] = 'mp';

		$old_new_mapping = array(
			'MP_API_ENDPOINT'             => 'mp_api_endpoint',
			'MP_OAUTH_DISCOVERY_ENDPOINT' => 'mp_oauth_discovery_endpoint',
			'MP_CLIENT_ID'                => 'mp_client_id',
			'MP_CLIENT_SECRET'            => 'mp_client_secret',
			'MP_API_SCOPE'                => 'mp_api_scope',
		);

		foreach ( $old_new_mapping as $old_key => $new_key ) {
			if ( isset( $mp_api_config[ $old_key ] ) && ! isset( $cps_main_options[ $new_key ] ) ) {
				$cps_main_options[ $new_key ] = $mp_api_config[ $old_key ];
			}
		}

		if ( isset( $group_mapping['fields'] ) && ! isset( $cps_mp_options['group_fields'] ) ) {
			$cps_mp_options['group_fields'] = wp_json_encode( array_values( $group_mapping['fields'] ) );
		}

		if ( ! empty( $custom_group_mapping ) ) {
			$cps_mp_options['group_mapping'] = wp_json_encode( $custom_group_mapping );
		}

		if ( isset( $group_mapping['mapping'] ) ) {
			foreach ( $group_mapping['mapping'] as $key => $value ) {
				$compound_key = 'group_mapping_' . $key;

				if ( ! isset( $cps_mp_options[ $compound_key ] ) ) {
					$cps_mp_options[ $compound_key ] = $value;
				}
			}
		}

		update_option( 'cps_main_options', $cps_main_options );
		update_option( 'cps_mp_options', $cps_mp_options );

		delete_option( 'ministry_platform_api_config' );
		delete_option( 'ministry_platform_group_mapping' );
		delete_option( 'cp_group_custom_field_mapping' );
	}
}
