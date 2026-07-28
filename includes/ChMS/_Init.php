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
require_once CP_SYNC_PLUGIN_DIR . '/includes/ChMS/cli/CCB.php';
require_once CP_SYNC_PLUGIN_DIR . '/includes/ChMS/cli/Tests.php';

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
		add_action( 'admin_init', [ $this, 'handle_oauth_redirect' ] );
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

	/**
	 * Recursively sanitize a settings data array before persisting.
	 *
	 * Walks nested arrays and applies sanitize_text_field() to string leaf values.
	 * Non-string scalars (bool, int, float) and null are preserved untouched so
	 * that legitimately typed settings values are not corrupted.
	 *
	 * Note: REST request params are not slash-escaped (unlike $_POST), so no
	 * wp_unslash() is applied here. sanitize_text_field() strips tags and collapses
	 * whitespace/newlines; none of the stored settings (ChMS slug, license, beta/status
	 * flags, feed types, post statuses, filter field paths, comparison operators and
	 * match values) legitimately require HTML or multi-line content, so this is safe
	 * for every current field.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed The sanitized value.
	 */
	protected static function sanitize_settings_data( $value ) {
		// Canonical implementation lives on the base ChMS class so the per-ChMS
		// schema walk and the global route share one generic sanitizer.
		return ChMS::sanitize_settings_recursive( $value );
	}

	/** Actions ***************************************************/

	/**
	 * Register rest routes.
	 *
	 * @since  1.1.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			'cp-sync/v1',
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
			'cp-sync/v1',
			'settings',
			[
				'methods'  => 'POST',
				'callback' => function ( $request ){
					$data = $request->get_param( 'data' );

					if ( ! is_array( $data ) ) {
						return new WP_Error( 'invalid_data', __( 'Invalid data', 'cp-sync' ), [ 'status' => 400 ] );
					}

					// Sanitize every leaf value before persisting.
					$data = self::sanitize_settings_data( $data );

					// The `chms` field selects a ChMS class, so it must be a safe slug.
					if ( isset( $data['chms'] ) ) {
						$data['chms'] = sanitize_key( $data['chms'] );
					}

					$old_settings = $settings = get_option( 'cp_sync_settings', [] );

					foreach ( $data as $key => $value ) {
						$settings[ $key ] = $value;
					}

					/**
					 * CP Sync global settings updated
					 *
					 * @param array $settings The updated settings
					 * @param array $old_settings The old settings
					 */
					do_action( 'cp_sync_global_settings_updated', $settings, $old_settings );

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
					$chms       = sanitize_key( $request->get_param( 'chms' ) ); // selects a ChMS class; must be a safe slug.
					$chms_class = self::get_chms( $chms );
					$data       = $request->get_param( 'data' );

					if ( ! is_array( $data ) ) {
						return new WP_Error( 'invalid_data', __( 'Invalid data', 'cp-sync' ), [ 'status' => 400 ] );
					}

					if ( ! $chms_class ) {
						return new WP_Error( 'invalid_chms', __( 'Invalid ChMS', 'cp-sync' ), [ 'status' => 400 ] );
					}

					// Schema-driven sanitize + validate walk. The ChMS schema is the single
					// declaration of per-field server behavior: declared fields dispatch on
					// their `sanitize`/`validate` rules ( so credentials are control-strip
					// only, not HTML-sanitized, and a bad subdomain becomes a real 400 ),
					// while undeclared custom-widget keys fall back to the generic recursive
					// sanitizer and are never dropped. At-rest encryption stays at the option
					// layer ( see ChMS::register_schema_option_filters ) so non-REST writers
					// cannot store plaintext.
					$data = ChMS::sanitize_settings_by_schema( $chms_class->get_settings_schema(), $data );

					if ( is_wp_error( $data ) ) {
						return $data;
					}

					$settings = get_option( $chms_class->settings_key, [] );

					unset( $data['auth'] ); // this should never be updated from the client, only server side

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

		register_rest_route(
			'cp-sync/v1',
			'/(?P<chms>[a-zA-Z0-9-]+)/filters',
			[
				'methods'  => 'GET',
				'callback' => function( $request ) {
					$chms       = $request->get_param( 'chms' );
					$chms_class = self::get_chms( $chms );
					$chms_class->setup(); // make sure the integrations are loaded
					
					$filter_config = $chms_class->get_formatted_filter_config();

					return rest_ensure_response( $filter_config );
				},
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			],
		);

		register_rest_route(
			'cp-sync/v1',
			'/(?P<chms>[a-zA-Z0-9-]+)/schema',
			[
				'methods'  => 'GET',
				'callback' => function( $request ) {
					$chms       = $request->get_param( 'chms' );
					$chms_class = self::get_chms( $chms );

					if ( ! $chms_class ) {
						return new WP_Error( 'invalid_chms', __( 'Invalid ChMS', 'cp-sync' ), [ 'status' => 400 ] );
					}

					$chms_class->setup(); // make sure the integrations are loaded ( filter-builder configs )

					return rest_ensure_response( $chms_class->get_formatted_settings_schema() );
				},
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			],
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
		// This is an OAuth callback *return* from an external bridge (the churchplugins theme
		// bridge), so it cannot carry a standard WP nonce. Access is gated by the
		// current_user_can( 'manage_options' ) capability check below. The real CSRF fix
		// (OAuth `state` param + host allowlist) lives in the bridge and is tracked separately.
		if ( ! isset( $_GET['cp_sync_oauth'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback return, guarded by the capability check below.
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
		$active_chms = $this->get_active_chms_class();

		if ( ! $active_chms ) {
			return;
		}

		// OAuth callback return from the external bridge; cannot carry a WP nonce. Guarded by the
		// current_user_can( 'manage_options' ) check in handle_oauth_redirect(). See note there.
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback return, guarded by capability check in handle_oauth_redirect().
		/**
		 * Filter the token
		 *
		 * @param string $token The token
		 * @param ChMS $active_chms The active ChMS class
		 * @return string
		 */
		$token = apply_filters( 'cp_sync_oauth_token', $token, $active_chms );

		$refresh_token = isset( $_GET['refresh_token'] ) ? sanitize_text_field( wp_unslash( $_GET['refresh_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback return, guarded by capability check in handle_oauth_redirect().
		/**
		 * Filter the refresh token
		 *
		 * @param string $refresh_token The refresh token
		 * @param ChMS $active_chms The active ChMS class
		 * @return string
		 */
		$refresh_token = apply_filters( 'cp_sync_oauth_refresh_token', $refresh_token, $active_chms );

		$active_chms->save_token( $token, $refresh_token );

		cp_sync()->logging->log( 'OAuth token saved' );

		$target_origin = parse_url( home_url(), PHP_URL_SCHEME ) . '://' . parse_url( home_url(), PHP_URL_HOST );
		?>
		<script>
			window.postMessage({
				success: true,
				type: 'cp_sync_oauth',
			}, '<?php echo esc_url( $target_origin ); ?>');
		</script>
		<?php
	}
}
