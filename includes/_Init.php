<?php
namespace CP_Sync;

use CP_Sync\Admin\Settings;

/**
 * Provides the global $cp_sync object
 *
 * @author costmo
 */
class _Init {

	/**
	 * @var
	 */
	protected static $_instance;

	/**
	 * @var Setup\_Init
	 */
	public $setup;

	public $enqueue;

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
	 * Class constructor: Add Hooks and Actions
	 *
	 */
	protected function __construct() {
		$this->enqueue = new \WPackio\Enqueue( 'cpSync', 'dist', $this->get_version(), 'plugin', CP_SYNC_PLUGIN_FILE );
		add_action( 'cp_core_loaded', [ $this, 'maybe_setup' ], - 9999 );
		add_action( 'init', [ $this, 'maybe_init' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue' ] );
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

		$this->includes();
		$this->actions();
	}

	/**
	 * Actions that must run through the `init` hook
	 *
	 * @return void
	 * @author costmo
	 */
	public function maybe_init() {

		if ( ! $this->check_required_plugins() ) {
			return;
		}

	}

	/**
	 * `wp_enqueue_scripts` actions for the app's compiled sources
	 *
	 * @return void
	 * @author costmo
	 */
	public function app_enqueue() {
		$this->enqueue->enqueue( 'styles', 'main', [] );
		$this->enqueue->enqueue( 'scripts', 'main', [] );
	}

	public function admin_enqueue() {
		$this->enqueue->enqueue( 'styles', 'admin', [] );
		$assets = $this->enqueue->enqueue( 'scripts', 'admin', [ 'js_dep' => [ 'jquery' ] ] );
		wp_localize_script( $assets['js'][0]['handle'], 'cpSync', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'cpSync' ),
		] );
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
	}

	protected function actions() {}

	/**
	 * Required Plugins notice
	 *
	 * @return void
	 */
	public function required_plugins() {
		printf( '<div class="error"><p>%s</p></div>', __( 'Your system does not meet the requirements for Church Plugins - Staff', 'cp-sync' ) );
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
		return __( 'Church Plugins - Sync', 'cp-sync' );
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
		return '0.0.1';
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

}
