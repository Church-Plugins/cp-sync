<?php

namespace CP_Connect\Admin;

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
	 * Only make one instance of \CP_Connect\Settings
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
	public static function get( $key, $default = '', $group = 'cpc_main_options' ) {
		$options = get_option( $group, [] );

		if ( isset( $options[ $key ] ) ) {
			$value = $options[ $key ];
		} else {
			$value = $default;
		}

		return apply_filters( 'cpc_settings_get', $value, $key, $group );
	}

	/**
	 * Update an option in the options table.
	 *
	 * @param string $key Option key.
	 * @param mixed  $value Option value.
	 * @param string $group Option group.
	 */
	public static function set( $key, $value, $group = 'cpc_main_options' ) {
		$options = get_option( $group, [] );

		$options[ $key ] = $value;

		update_option( $group, $options );
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
		return self::get( $key, $default, 'cpc_advanced_options' );
	}

	/**
	 * Class constructor. Add admin hooks and actions
	 */
	protected function __construct() {
		add_action( 'admin_menu', [ $this, 'settings_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		\ChurchPlugins\Admin\Options::register_rest_route( 'cp-connect/v1', 'cpc_' );

		$this->license = new \ChurchPlugins\Setup\Admin\License( 'cpc_license', 438, CP_CONNECT_STORE_URL, CP_CONNECT_PLUGIN_FILE, get_admin_url( null, 'admin.php?page=cpc_license' ) );
	}

	/**
	 * Initializes the settings page.
	 *
	 * @since 1.1.0
	 */
	public function settings_page() {
		add_submenu_page(
			'options-general.php',
			__( 'Settings', 'cp-connect' ),
			__( 'CP Connect', 'cp-connect' ),
			'manage_options',
			'cpc_settings',
			[ $this, 'settings_page_content' ]
		);
	}

	/**
	 * Outputs the settings react entrypoint.
	 */
	public function settings_page_content() {
		$entrypoint_data = [
			'chms' => Settings::get( 'chms', 'mp' ),
		];

		/**
		 * Filters the entrypoint data for the settings page.
		 *
		 * @param array $entrypoint_data The entrypoint data.
		 * @return array
		 * @since 1.1.0
		 */
		$entrypoint_data = apply_filters( 'cp_connect_settings_entrypoint_data', $entrypoint_data );

		?>
		<div
			class="cp_settings_root cp-connect"
			data-initial='<?php echo wp_json_encode( $entrypoint_data ); ?>'
		></div>
		<?php
	}

	/**
	 * Enqueue scripts and styles for the settings page.
	 *
	 * @since 1.1.0
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( 'settings_page_cpc_settings' !== $screen->id ) {
			return;
		}
		\ChurchPlugins\Helpers::enqueue_asset( 'admin-settings' );
		\ChurchPlugins\Helpers::enqueue_asset( 'admin-settings', [], false, true );
	}
}
