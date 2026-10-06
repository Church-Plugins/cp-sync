<?php
/**
 * Capability and nonce checks for admin request actions.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Admin;

/**
 * Limits admin request actions to administrators who present a valid nonce.
 *
 * The bundled admin dispatcher runs do_action() on the cp_action request value
 * and passes the request array as the first argument. Callbacks that share a
 * hook with that dispatcher call these checks. Scheduled cron does not pass
 * the request array.
 *
 * @since 1.0.1
 */
class RequestAction {

	/**
	 * Capability required to run an admin request action.
	 *
	 * Matches the CP Sync settings screen and the REST pull routes.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Query argument that carries the nonce for an admin request action.
	 *
	 * @var string
	 */
	const NONCE_ARG = '_wpnonce';

	/**
	 * Whether this callback invocation is an admin request for $action.
	 *
	 * @since 1.0.1
	 *
	 * @param string $action  Expected action name.
	 * @param mixed  $payload First argument received by the callback.
	 * @return bool
	 */
	public static function is_admin_request( $action, $payload ) {
		if ( ! is_array( $payload ) || ! isset( $payload['cp_action'] ) ) {
			return false;
		}

		return sanitize_key( $payload['cp_action'] ) === $action;
	}

	/**
	 * Whether the current user may run this admin request action.
	 *
	 * Requires the CP Sync admin capability and a nonce created for $action.
	 *
	 * @since 1.0.1
	 *
	 * @param string $action  Nonce action. Matches the cp_action value.
	 * @param mixed  $payload Request array from the admin request dispatcher.
	 * @return bool
	 */
	public static function user_can_run( $action, $payload ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return false;
		}

		return self::has_valid_nonce( $action, $payload );
	}

	/**
	 * Whether an admin request action should do nothing.
	 *
	 * Returns false when $payload is not an admin request, so internal callers
	 * of the same hook are unchanged.
	 *
	 * @since 1.0.1
	 *
	 * @param string $action  Action name.
	 * @param mixed  $payload First argument received by the callback.
	 * @return bool
	 */
	public static function should_skip( $action, $payload ) {
		return self::is_admin_request( $action, $payload ) && ! self::user_can_run( $action, $payload );
	}

	/**
	 * Whether $payload is the single argument from the admin request dispatcher.
	 *
	 * The dispatcher passes one request array. Callbacks that normally receive
	 * several arguments should run this check first and return without changing
	 * state. The capability and nonce guard runs here.
	 *
	 * @since 1.0.1
	 *
	 * @param mixed $payload First argument received by the callback.
	 * @return bool
	 */
	public static function from_dispatcher( $payload ) {
		if ( ! is_array( $payload ) || ! isset( $payload['cp_action'] ) || ! is_scalar( $payload['cp_action'] ) ) {
			return false;
		}

		$action = sanitize_key( (string) $payload['cp_action'] );

		if ( '' === $action || ! self::is_admin_request( $action, $payload ) ) {
			return false;
		}

		self::should_skip( $action, $payload );

		return true;
	}

	/**
	 * Whether the nonce on an admin request action is valid.
	 *
	 * @since 1.0.1
	 *
	 * @param string $action  Nonce action.
	 * @param mixed  $payload Request array.
	 * @return bool
	 */
	public static function has_valid_nonce( $action, $payload ) {
		$nonce = '';

		if ( is_array( $payload ) && isset( $payload[ self::NONCE_ARG ] ) ) {
			$nonce = $payload[ self::NONCE_ARG ];
		}

		if ( ! is_string( $nonce ) || '' === $nonce ) {
			return false;
		}

		return (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), $action );
	}

	/**
	 * Whether the current request is a scheduled cron run.
	 *
	 * @since 1.0.1
	 *
	 * @return bool
	 */
	public static function doing_cron() {
		return function_exists( 'wp_doing_cron' ) && wp_doing_cron();
	}

	/**
	 * Write one debug-log line when an admin request action does nothing.
	 *
	 * Uses the plugin logger, which records only while debug mode is on.
	 *
	 * @since 1.0.1
	 *
	 * @param string $message Plain description of why the action did nothing.
	 * @return void
	 */
	public static function log_skip( $message ) {
		if ( ! function_exists( 'cp_sync' ) ) {
			return;
		}

		$plugin = cp_sync();

		if ( ! is_object( $plugin ) || empty( $plugin->logging ) ) {
			return;
		}

		$plugin->logging->log( $message );
	}
}
