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
	 * Supported ChMS
	 * 
	 * @var string[]
	 */
	public static $supported_chms = [];

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
		add_action( 'template_redirect', [ $this, 'handle_oauth_redirect' ] );
	}

	/**
	 * Register a supported ChMS
	 *
	 * @param ChMS $chms The ChMS class to register
	 */
	public static function register_chms( $chms ) {
		$chms_class = $chms::get_instance();
		self::$supported_chms[ $chms_class->id ] = $chms_class;
	}

	/**
	 * Get a supported ChMS
	 *
	 * @param string $id The ChMS ID
	 * @return ChMS | false
	 */
	public static function get_chms( $id ) {
		if ( isset( self::$supported_chms[ $id ] ) ) {
			return self::$supported_chms[ $id ];
		}

		return false;
	}

	/** Actions ***************************************************/

	/**
	 * Register rest routes.
	 *
	 * @since  1.1.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			'/cp-sync/v1',
			'settings',
			[
				'methods'  => 'GET',
				'callback' => function () {
					return get_option( 'cp_sync_settings', [] );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		register_rest_route(
			'/cp-sync/v1',
			'settings',
			[
				'methods'  => 'POST',
				'callback' => function ( $request ){
					$data = $request->get_param( 'data' );

					if ( ! is_array( $data ) ) {
						return new WP_Error( 'invalid_data', __( 'Invalid data', 'cp-sync' ), [ 'status' => 400 ] );
					}

					$settings = get_option( 'cp_sync_settings', [] );

					foreach ( $data as $key => $value ) {
						$settings[ $key ] = $value;
					}

					update_option( 'cp_sync_settings', $settings );

					return rest_ensure_response( [ 'success' => true ] );
				},
				'args' => [
					'data' => [
						'type' => 'object',
						'required' => true,
						'properties' => [
							'chms' => [
								'type' => 'string',
							],
							'license' => [
								'type' => 'string',
							],
							'beta' => [
								'type' => 'boolean',
							],
							'status' => [
								'type' => 'string',
							]
						],
					],
				],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		/**
		 * Routes for getting/updating a specific ChMS's settings.
		 * 
		 * This is implemented here instead of the base ChMS class because
		 * we may want to access settings for an inactive ChMS
		 */

		register_rest_route(
			'cp-sync/v1',
			'/(?P<chms>[a-zA-Z0-9-]+)/settings',
			[
				'methods'  => 'GET',
				'callback' => function( $request ) {
					$chms = $request->get_param( 'chms' );

					$chms_class = self::get_chms( $chms );

					return get_option( $chms_class->settings_key, [] );
				},
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			]
		);

		register_rest_route(
			'cp-sync/v1',
			'/(?P<chms>[a-zA-Z0-9-]+)/settings',
			[
				'methods'  => 'POST',
				'callback' => function( $request ) {
					$chms       = $request->get_param( 'chms' );
					$chms_class = self::get_chms( $chms );
					$data       = $request->get_param( 'data' );

					if ( ! is_array( $data ) ) {
						return new WP_Error( 'invalid_data', __( 'Invalid data', 'cp-sync' ), [ 'status' => 400 ] );
					}

					$settings = get_option( $chms_class->settings_key, [] );

					foreach ( $data as $key => $value ) {
						$settings[ $key ] = $value;
					}

					update_option( $chms_class->settings_key, $settings );

					return rest_ensure_response( [ 'success' => true ] );
				},
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
				'args'     => [
					'data' => [
						'type'        => 'object',
						'required'    => true,
						'description' => 'The settings data to save',
					],
				],
			]
		);
	}

	/**
	 * Admin init includes
	 *
	 * @return void
	 */
	public function includes() {
		$this->register_chms( PCO::class );
		$this->register_chms( CCB::class );

		$active_chms = $this->get_active_chms_class();
		if ( $active_chms ) {
			$active_chms->load(); // Trigger the active ChMS class to load
		}
	}

	/**
	 * Get the active ChMS class
	 *
	 * @return ChMS | false
	 */
	public function get_active_chms_class() {
		return self::get_chms( $this->get_active_chms() );
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
		return apply_filters( 'cp_sync_active_chms', Settings::get( 'chms', 'pco', 'cp_sync_settings' ) );
	}

	/**
	 * Handle OAuth redirect
	 */
	public function handle_oauth_redirect() {
		if ( ! isset( $_GET['cp_sync_oauth'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_action( 'admin_head', [ $this, 'add_oauth_script' ] );
	}

	/**
	 * Add the OAuth script
	 */
	public function add_oauth_script() {
		$target_origin = parse_url( home_url(), PHP_URL_SCHEME ) . '://' . parse_url( home_url(), PHP_URL_HOST );
		?>
		<script>
			window.postMessage({
				success: true,
				type: 'cp_sync_oauth',
			}, '<?php echo esc_url( $target_origin ); ?>');
			window.close();
		</script>
		<?php
	}
}
