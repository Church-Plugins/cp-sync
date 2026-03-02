<?php

namespace CP_Sync\Admin;

use CP_Sync\Setup\DataFilter;

/**
 * Plugin settings
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Class instance.
	 *
	 * @var Settings
	 */
	protected static $instance;

	/**
	 * License manager.
	 *
	 * @var \ChurchPlugins\Setup\Admin\License
	 */
	public $license;

	/**
	 * Only make one instance of \CP_Sync\Settings
	 *
	 * @return Settings
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof Settings ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get a value from the options table
	 *
	 * @param string $key Option key.
	 * @param mixed  $default Default value.
	 * @param string $group Option group.
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function get( $key, $default = '', $group = 'cps_main_options' ) {
		$options = get_option( $group, [] );

		if ( isset( $options[ $key ] ) ) {
			$value = $options[ $key ];
		} else {
			$value = $default;
		}

		return apply_filters( 'cps_settings_get', $value, $key, $group );
	}

	/**
	 * Update an option in the options table.
	 *
	 * @param string $key Option key.
	 * @param mixed  $value Option value.
	 * @param string $group Option group.
	 */
	public static function set( $key, $value, $group = 'cps_main_options' ) {
		$options = get_option( $group, [] );

		$options[ $key ] = $value;

		return update_option( $group, $options );
	}

	/**
	 * Get advanced options
	 *
	 * @param string $key Option key.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function get_advanced( $key, $default = '' ) {
		return self::get( $key, $default, 'cps_advanced_options' );
	}

	/**
	 * Class constructor. Add admin hooks and actions
	 */
	protected function __construct() {
		add_action( 'admin_menu', [ $this, 'settings_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		\ChurchPlugins\Admin\Options::register_rest_route( 'cp-sync/v1', 'cps_' );

		$this->license = new \ChurchPlugins\Setup\Admin\License( 'cps_license', 438, CP_SYNC_STORE_URL, CP_SYNC_PLUGIN_FILE, get_admin_url( null, 'admin.php?page=cps_license' ) );
	}

	/**
	 * Initializes the settings page.
	 *
	 * @since 1.1.0
	 */
	public function settings_page() {
		add_submenu_page(
			'options-general.php',
			__( 'Settings', 'cp-sync' ),
			__( 'CP Sync', 'cp-sync' ),
			'manage_options',
			'cps_settings',
			[ $this, 'settings_page_content' ]
		);
	}

	/**
	 * Outputs the settings react entrypoint.
	 */
	public function settings_page_content() {
		/**
		 * Filters the initial global settings sent to the settings page.
		 *
		 * @param array $global_settings The entrypoint data.
		 * @return array
		 * @since 1.0.0
		 */
		$global_settings = apply_filters( 'cp_sync_global_settings', get_option( 'cp_sync_settings', [] ) );
		$compare_options = DataFilter::get_formatted_compare_options();
		?>
		<style>
			.cp-sync-loading {
				padding: 32px;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}
			.cp-sync-loading h1 {
				font-size: 24px;
				font-weight: 400;
				margin: 0 0 24px;
				color: #1d2327;
			}
			.cp-sync-loading__skeleton {
				display: flex;
				flex-direction: column;
				gap: 12px;
				margin-bottom: 24px;
			}
			.cp-sync-loading__bar {
				height: 16px;
				border-radius: 4px;
				background: linear-gradient(90deg, #e0e0e0 25%, #f0f0f0 50%, #e0e0e0 75%);
				background-size: 200% 100%;
				animation: cp-sync-shimmer 1.5s infinite ease-in-out;
			}
			.cp-sync-loading__text {
				color: #787c82;
				font-size: 14px;
				margin: 0;
			}
			@keyframes cp-sync-shimmer {
				0% { background-position: 200% 0; }
				100% { background-position: -200% 0; }
			}
		</style>
		<div
			class="cp_settings_root cp-sync"
			data-settings='<?php echo wp_json_encode( $global_settings ); ?>'
			data-compare-options='<?php echo wp_json_encode( $compare_options ); ?>'
		>
			<div class="cp-sync-loading">
				<h1><?php esc_html_e( 'CP Sync', 'cp-sync' ); ?></h1>
				<div class="cp-sync-loading__skeleton">
					<div class="cp-sync-loading__bar" style="width: 40%;"></div>
					<div class="cp-sync-loading__bar" style="width: 70%;"></div>
					<div class="cp-sync-loading__bar" style="width: 55%;"></div>
				</div>
				<p class="cp-sync-loading__text"><?php esc_html_e( 'Loading settings...', 'cp-sync' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts and styles for the settings page.
	 *
	 * @since 1.1.0
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( 'settings_page_cps_settings' !== $screen->id ) {
			return;
		}

		global $cp_sync;

		$cp_sync->enqueue_asset( 'admin-settings' );
		$cp_sync->enqueue_asset( 'admin-settings', [], false, true );
	}
}
