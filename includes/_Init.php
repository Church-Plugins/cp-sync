<?php
namespace CP_Sync;

require_once CP_SYNC_PLUGIN_DIR . 'includes/ChurchPlugins/Setup/Plugin.php';

/**
 * Provides the global $cp_sync object
 *
 * @author costmo
 */
class _Init extends \ChurchPlugins\Setup\Plugin {

	/**
	 * @var Setup\_Init
	 */
	public $setup;

	/**
	 * @var \ChurchPlugins\Logging
	 */
	public $logging;

	/**
	 * Get plugin directory
	 *
	 * @return string
	 */
	public function get_plugin_dir() {
		return CP_SYNC_PLUGIN_DIR;
	}

	/**
	 * Get plugin URL
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		return CP_SYNC_PLUGIN_URL;
	}

	/**
	 * Class constructor: Add Hooks and Actions
	 *
	 */
	protected function __construct() {
		parent::__construct();
		// Attach the `window.cpSync` localization to the settings SPA bundle
		// (`cp-sync-admin-settings`, enqueued by Admin\Settings). Late priority so
		// the handle is already registered by the time we add the inline script.
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue' ], 20 );
	}

	/**
	 * Plugin setup entry hub
	 *
	 * @return void
	 */
	public function maybe_setup() {
		if ( ! $this->check_required_plugins() ) {
			return;
		}
		parent::maybe_setup();
	}

	/**
	 * Localize `window.cpSync` onto the settings SPA bundle.
	 *
	 * The React settings app (built by wp-scripts, enqueued as
	 * `cp-sync-admin-settings` in Admin\Settings) still consumes
	 * `window.cpSync.{ajaxUrl,nonce,oauthURL,adminUrl}` (e.g. the PCO OAuth
	 * connect flow). We attach it as an inline script only when that handle is
	 * actually enqueued (the settings screen), so it is a no-op elsewhere.
	 *
	 * @return void
	 */
	public function admin_enqueue() {
		if ( ! wp_script_is( 'cp-sync-admin-settings', 'enqueued' ) ) {
			return;
		}

		$data = [
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'cpSync' ),
			'oauthURL' => CP_SYNC_OAUTH_URL,
			'adminUrl' => admin_url(),
		];

		wp_add_inline_script(
			'cp-sync-admin-settings',
			'window.cpSync = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}

	/**
	 * Includes
	 *
	 * @return void
	 */
	protected function includes() {
		Admin\_Init::get_instance();
		ChMS\_Init::get_instance();
		Integrations\_Init::get_instance();
		$this->setup = Setup\_Init::get_instance();
		$this->logging = new \ChurchPlugins\Logging( $this->get_id(), $this->is_debug_mode() );
	}

	protected function actions() {}

	/**
	 * Required Plugins notice
	 *
	 * @return void
	 */
	public function required_plugins() {
		printf( '<div class="error"><p>%s</p></div>', esc_html__( 'Your system does not meet the requirements for Church Plugins - Staff', 'cp-sync' ) );
	}

	/** Helper Methods **************************************/

	public function get_default_thumb() {
		return CP_SYNC_PLUGIN_URL . '/app/public/logo512.png';
	}

	/**
	 * Make sure required plugins are active
	 *
	 * @return bool
	 */
	protected function check_required_plugins() {

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		// @todo check for requirements before loading
		if ( 1 ) {
			return true;
		}

		add_action( 'admin_notices', array( $this, 'required_plugins' ) );

		return false;
	}

	/**
	 * Gets the plugin support URL
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_support_url() {
		return 'https://churchplugins.com/support';
	}

	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.0.0
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'CP Sync', 'cp-sync' );
	}

	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.0.0
	 * @return string the plugin name
	 */
	public function get_plugin_path() {
		return CP_SYNC_PLUGIN_DIR;
	}

	/**
	 * Provide a unique ID tag for the plugin
	 *
	 * @return string
	 */
	public function get_id() {
		return 'cp-sync';
	}

	/**
	 * Provide a unique ID tag for the plugin
	 *
	 * @return string
	 */
	public function get_version() {
		return CP_SYNC_PLUGIN_VERSION;
	}

	/**
	 * Get the API namespace to use
	 *
	 * @return string
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_api_namespace() {
		return $this->get_id() . '/v1';
	}

	public function enabled() {
		return true;
	}

	/**
	 * Return the debug mode
	 *
	 * @return mixed|null
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey, 6/9/24
	 */
	public function is_debug_mode() {
		$debug_mode = false;
		$settings = get_option( 'cp_sync_settings', [] );

		if ( ! empty( $settings['debugMode'] ) ) {
			$debug_mode = true;
		}

		if ( defined( 'CP_SYNC_DEBUG' ) && CP_SYNC_DEBUG ) {
			$debug_mode = true;
		}

		return apply_filters( 'cp_sync_debug_mode', $debug_mode );
	}

}
