<?php
/**
 * Admin-facing count of events skipped by the visibility filter.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Records visibility skips and tells admins when those skips remove
 * upcoming events that were already on the site.
 *
 * @since 1.1.0
 */
class VisibilityRemovalNotice {

	/**
	 * Option that holds the last summary for the admin notice.
	 */
	const OPTION = 'cp_sync_event_visibility_notice';

	/**
	 * Query arg and nonce action for the Dismiss link.
	 */
	const DISMISS_ACTION = 'cp_sync_dismiss_event_visibility_notice';

	/**
	 * Query flag on the Dismiss link.
	 */
	const DISMISS_QUERY = 'cp_sync_dismiss_event_visibility';

	/**
	 * Query arg carrying the notice id the Dismiss link applies to.
	 */
	const NOTICE_ID_QUERY = 'cp_sync_visibility_notice_id';

	/**
	 * Per-user record of the notice id that administrator dismissed.
	 */
	const USER_META = 'cp_sync_event_visibility_dismissed';

	/**
	 * Makes each saved removal a new notice, even in the same second.
	 *
	 * @var int
	 */
	protected static $sequence = 0;

	/**
	 * Instance ids skipped during the current pull.
	 *
	 * @var array
	 */
	protected static $skipped_ids = array();

	/**
	 * Forget skips from an earlier pull in this process.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$skipped_ids = array();
	}

	/**
	 * Instance ids recorded so far.
	 *
	 * @return array
	 */
	public static function skipped_ids() {
		return self::$skipped_ids;
	}

	/**
	 * Remember one skipped event id.
	 *
	 * @param mixed $chms_id ChMS id of the skipped event.
	 * @return void
	 */
	public static function record_skip( $chms_id ) {
		if ( null === $chms_id || '' === (string) $chms_id ) {
			return;
		}

		self::$skipped_ids[] = (string) $chms_id;
	}

	/**
	 * Compare skipped ids with the ids the sync actually removed.
	 *
	 * A skip only counts as a removal when that event was already tracked
	 * and the removal pass deleted it. Past events the removal pass keeps
	 * are not included. Ids are compared as strings so numeric ChMS ids
	 * still match.
	 *
	 * @param array $skipped_ids Ids the visibility filter skipped.
	 * @param array $removed_ids Ids the sync removed.
	 * @return array{skipped: int, removed_upcoming: int}
	 */
	public static function summarize( array $skipped_ids, array $removed_ids ) {
		$skipped = array_values( array_unique( array_map( 'strval', $skipped_ids ) ) );
		$removed = array_map( 'strval', $removed_ids );
		$caused  = array_intersect( $skipped, $removed );

		return array(
			'skipped'          => count( $skipped ),
			'removed_upcoming' => count( $caused ),
		);
	}

	/**
	 * Notice text. Empty when no upcoming event was removed.
	 *
	 * @param array $summary summarize() result.
	 * @return string
	 */
	public static function message( array $summary ) {
		$removed = (int) ( $summary['removed_upcoming'] ?? 0 );

		if ( $removed < 1 ) {
			return '';
		}

		/* translators: 1: events skipped by the visibility filter, 2: upcoming events removed from the site */
		$text = __( 'CP Sync skipped %1$d calendar events that are hidden or not approved. %2$d upcoming events already on this site were removed because of that. Past events were kept.', 'cp-sync' );

		return sprintf( $text, (int) ( $summary['skipped'] ?? 0 ), $removed );
	}

	/**
	 * Store the summary for the admin notice.
	 *
	 * A later sync that removes nothing leaves the notice in place. A later
	 * sync that removes upcoming events replaces it and gets a new id, so
	 * a previous per-user dismissal does not hide the new one.
	 *
	 * @param array $skipped_ids Ids the visibility filter skipped.
	 * @param array $removed_ids Ids the sync removed.
	 * @return array The summary.
	 */
	public static function publish( array $skipped_ids, array $removed_ids ) {
		$summary = self::summarize( $skipped_ids, $removed_ids );

		if ( function_exists( 'update_option' ) && $summary['removed_upcoming'] > 0 ) {
			$summary['id'] = self::notice_id( $skipped_ids, $removed_ids );
			update_option( self::OPTION, $summary, false );
		}

		self::reset();

		return $summary;
	}

	/**
	 * Id for this removal. A later removal gets a different id.
	 *
	 * @param array $skipped_ids Ids the visibility filter skipped.
	 * @param array $removed_ids Ids the sync removed.
	 * @return string
	 */
	protected static function notice_id( array $skipped_ids, array $removed_ids ) {
		self::$sequence++;

		return substr(
			hash(
				'sha256',
				implode( ',', array_map( 'strval', $skipped_ids ) )
				. '|' . implode( ',', array_map( 'strval', $removed_ids ) )
				. '|' . microtime( true )
				. '|' . self::$sequence
			),
			0,
			16
		);
	}

	/**
	 * Remember that the current user dismissed this notice id.
	 *
	 * The option stays, so other administrators still see it. The id must
	 * match the notice that is stored now.
	 *
	 * @param string $notice_id Notice id from the link or the X button.
	 * @return bool
	 */
	public static function remember_dismissal( $notice_id ) {
		$notice_id = (string) $notice_id;
		$summary   = function_exists( 'get_option' ) ? get_option( self::OPTION ) : null;
		$current   = ( is_array( $summary ) && isset( $summary['id'] ) ) ? (string) $summary['id'] : '';

		if ( '' === $notice_id || '' === $current || $notice_id !== $current ) {
			return false;
		}

		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

		if ( $user_id < 1 || ! function_exists( 'update_user_meta' ) ) {
			return false;
		}

		update_user_meta( $user_id, self::USER_META, $current );

		return true;
	}

	/**
	 * Whether this user already dismissed the notice that is stored.
	 *
	 * @param array $summary Stored summary.
	 * @param int   $user_id User ID. Zero uses the current user.
	 * @return bool
	 */
	public static function is_dismissed_by( array $summary, $user_id = 0 ) {
		$notice_id = isset( $summary['id'] ) ? (string) $summary['id'] : '';

		if ( '' === $notice_id || ! function_exists( 'get_user_meta' ) ) {
			return false;
		}

		if ( $user_id < 1 ) {
			$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		}

		if ( $user_id < 1 ) {
			return false;
		}

		return (string) get_user_meta( $user_id, self::USER_META, true ) === $notice_id;
	}

	/**
	 * No-JS Dismiss link. Stores this notice id on the current user.
	 *
	 * @return void
	 */
	public static function maybe_dismiss() {
		if ( ! isset( $_GET[ self::DISMISS_QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below.
			return;
		}

		if ( ! self::can_dismiss() ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! function_exists( 'wp_verify_nonce' ) || ! wp_verify_nonce( $nonce, self::DISMISS_ACTION ) ) {
			return;
		}

		$notice_id = isset( $_GET[ self::NOTICE_ID_QUERY ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::NOTICE_ID_QUERY ] ) ) : '';

		self::remember_dismissal( $notice_id );
	}

	/**
	 * X-button handler. Same nonce and capability as the Dismiss link.
	 *
	 * @return void
	 */
	public static function ajax_dismiss() {
		if ( ! self::can_dismiss() ) {
			self::json_error( 403 );
			return;
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! function_exists( 'wp_verify_nonce' ) || ! wp_verify_nonce( $nonce, self::DISMISS_ACTION ) ) {
			self::json_error( 403 );
			return;
		}

		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

		if ( ! self::remember_dismissal( $key ) ) {
			self::json_error( 400 );
			return;
		}

		if ( function_exists( 'wp_send_json_success' ) ) {
			wp_send_json_success();
		}
	}

	/**
	 * @return bool
	 */
	protected static function can_dismiss() {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	/**
	 * @param int $status HTTP status.
	 * @return void
	 */
	protected static function json_error( $status ) {
		if ( function_exists( 'wp_send_json_error' ) ) {
			wp_send_json_error( null, (int) $status );
		}
	}

	/**
	 * Print the admin notice when a previous sync removed upcoming events.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$summary = get_option( self::OPTION );

		if ( ! is_array( $summary ) ) {
			return;
		}

		if ( self::is_dismissed_by( $summary ) ) {
			return;
		}

		$message = self::message( $summary );

		if ( '' === $message ) {
			return;
		}

		$notice_id = isset( $summary['id'] ) ? (string) $summary['id'] : '';
		$nonce     = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( self::DISMISS_ACTION ) : '';

		printf(
			'<div class="notice notice-warning is-dismissible cp-sync-visibility-notice" data-cp-sync-notice="%1$s" data-nonce="%2$s"><p>%3$s%4$s</p></div>%5$s',
			esc_attr( $notice_id ),
			esc_attr( $nonce ),
			esc_html( $message ),
			self::dismiss_link( $notice_id ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- dismiss_link() escapes the URL and label.
			self::dismiss_script() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Script contains only a fixed action name.
		);
	}

	/**
	 * No-JS Dismiss link for this notice id.
	 *
	 * @param string $notice_id Current notice id.
	 * @return string
	 */
	protected static function dismiss_link( $notice_id ) {
		if ( ! function_exists( 'wp_nonce_url' ) || ! function_exists( 'add_query_arg' ) ) {
			return '';
		}

		$url = add_query_arg( self::DISMISS_QUERY, '1' );
		$url = add_query_arg( self::NOTICE_ID_QUERY, $notice_id, $url );
		$url = wp_nonce_url( $url, self::DISMISS_ACTION );

		if ( function_exists( 'esc_url' ) ) {
			$url = esc_url( $url );
		}

		return ' <a class="cp-sync-visibility-notice__dismiss" style="display:inline-flex;align-items:center;min-height:44px;" href="' . $url . '">' . esc_html( __( 'Dismiss', 'cp-sync' ) ) . '</a>';
	}

	/**
	 * Persist the X button for this notice. WordPress adds the button to
	 * `.is-dismissible` and does not store the click.
	 *
	 * @return string
	 */
	protected static function dismiss_script() {
		$action = self::DISMISS_ACTION;

		return '<script>(function(){document.addEventListener("click",function(event){var button=event.target&&event.target.closest?event.target.closest(".cp-sync-visibility-notice .notice-dismiss"):null;if(!button||!window.ajaxurl){return;}var notice=button.closest(".cp-sync-visibility-notice");if(!notice){return;}var body=new FormData();body.append("action","' . $action . '");body.append("nonce",notice.getAttribute("data-nonce")||"");body.append("key",notice.getAttribute("data-cp-sync-notice")||"");window.fetch(window.ajaxurl,{method:"POST",credentials:"same-origin",body:body});});}());</script>';
	}
}
