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
		add_action( 'admin_init', [ $this, 'handle_oauth_redirect' ], 1 );
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

					if ( ! $chms_class ) {
						return new WP_Error( 'invalid_chms', __( 'Unknown ChMS', 'cp-sync' ), [ 'status' => 404 ] );
					}

					$settings = get_option( $chms_class->settings_key, [] );

					// Tokens are server-side only; the POST route drops this group too.
					unset( $settings['auth'] );

					return rest_ensure_response( $settings );
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
			'/(?P<chms>[a-zA-Z0-9-]+)/compare-options',
			[
				'methods'  => 'GET',
				'callback' => function() {
					return rest_ensure_response( \CP_Sync\Setup\DataFilter::get_compare_options() );
				},
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
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
	 *
	 * Runs early on admin_init and terminates the request. The authorization service
	 * redirects back to admin_url(), so this callback lands on the Dashboard -- a screen
	 * plenty of plugins redirect away from on admin_init. Saving the token here rather
	 * than during admin_head keeps it out of the way of those redirects.
	 */
	public function handle_oauth_redirect() {
		if ( ! isset( $_GET['cp_sync_oauth'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->render_oauth_response( $this->process_oauth_redirect() );
	}

	/**
	 * Validate the OAuth redirect and store the returned token.
	 *
	 * @return array {
	 *     @type bool   $success Whether a token was stored.
	 *     @type string $message Detail to show when it was not.
	 * }
	 */
	protected function process_oauth_redirect() {
		$nonce = isset( $_GET['_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_nonce'] ) ) : '';

		// The authorization service round-trips the nonce it was handed at the start of the flow.
		if ( ! wp_verify_nonce( $nonce, 'cpSync' ) ) {
			cp_sync()->logging->log( 'OAuth redirect rejected: nonce verification failed' );

			return [
				'success' => false,
				'message' => __( 'This authorization link is invalid or has expired. Please try connecting again.', 'cp-sync' ),
			];
		}

		$active_chms = $this->get_active_chms_class();

		if ( ! $active_chms ) {
			return [
				'success' => false,
				'message' => __( 'No ChMS is currently active.', 'cp-sync' ),
			];
		}

		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		/**
		 * Filter the token
		 *
		 * @param string $token The token
		 * @param ChMS $active_chms The active ChMS class
		 * @return string
		 */
		$token = apply_filters( 'cp_sync_oauth_token', $token, $active_chms );

		$refresh_token = isset( $_GET['refresh_token'] ) ? sanitize_text_field( wp_unslash( $_GET['refresh_token'] ) ) : '';
		/**
		 * Filter the refresh token
		 *
		 * @param string $refresh_token The refresh token
		 * @param ChMS $active_chms The active ChMS class
		 * @return string
		 */
		$refresh_token = apply_filters( 'cp_sync_oauth_refresh_token', $refresh_token, $active_chms );

		// Saving an empty token would overwrite a working connection.
		if ( empty( $token ) ) {
			cp_sync()->logging->log( 'OAuth redirect returned no token; existing token left untouched' );

			return [
				'success' => false,
				'message' => __( 'The authorization service did not return a token. Please try again.', 'cp-sync' ),
			];
		}

		$active_chms->save_token( $token, $refresh_token );

		cp_sync()->logging->log( 'OAuth token saved' );

		return [
			'success' => true,
			'message' => '',
		];
	}

	/**
	 * Hand the result back to the settings screen that opened this window, then exit.
	 *
	 * The result goes out over both BroadcastChannel and window.opener. A COOP header
	 * anywhere in the authorization chain severs window.opener for good, and the settings
	 * screen would sit and wait; BroadcastChannel does not depend on the opener handle.
	 *
	 * @param array $result Result from process_oauth_redirect().
	 */
	protected function render_oauth_response( $result ) {
		$payload = array_merge( $result, [ 'type' => 'cp_sync_oauth' ] );
		$notice  = $result['success']
			? __( 'Authorization successful. You can close this window.', 'cp-sync' )
			: $result['message'];

		nocache_headers();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<title><?php esc_html_e( 'Finishing authorization', 'cp-sync' ); ?></title>
</head>
<body>
	<p><?php echo esc_html( $notice ); ?></p>
	<script>
		( function () {
			var payload = <?php echo wp_json_encode( $payload ); ?>;
			var origin  = <?php echo wp_json_encode( $this->get_oauth_origin() ); ?>;

			// Left open deliberately: closing a channel right after posting can drop the
			// message before it is delivered. This document is about to go away regardless.
			try {
				new BroadcastChannel( 'cp_sync_oauth' ).postMessage( payload );
			} catch ( e ) {}

			try {
				if ( window.opener && ! window.opener.closed ) {
					window.opener.postMessage( payload, origin );
				}
			} catch ( e ) {}
		} )();
	</script>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * The origin of the settings screen, for postMessage targeting.
	 *
	 * Derived from admin_url() rather than home_url(): the two can differ, and the port
	 * matters on local and staging installs.
	 *
	 * @return string
	 */
	protected function get_oauth_origin() {
		$parts  = wp_parse_url( admin_url() );
		$origin = $parts['scheme'] . '://' . $parts['host'];

		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . $parts['port'];
		}

		return $origin;
	}
}
