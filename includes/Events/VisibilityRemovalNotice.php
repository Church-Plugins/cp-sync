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
	 * sync that removes upcoming events replaces it. An administrator
	 * dismisses it with the nonce link.
	 *
	 * @param array $skipped_ids Ids the visibility filter skipped.
	 * @param array $removed_ids Ids the sync removed.
	 * @return array The summary.
	 */
	public static function publish( array $skipped_ids, array $removed_ids ) {
		$summary = self::summarize( $skipped_ids, $removed_ids );

		if ( function_exists( 'update_option' ) && $summary['removed_upcoming'] > 0 ) {
			update_option( self::OPTION, $summary, false );
		}

		self::reset();

		return $summary;
	}

	/**
	 * Delete the notice when an administrator follows the Dismiss link.
	 *
	 * @return void
	 */
	public static function maybe_dismiss() {
		if ( ! isset( $_GET[ self::DISMISS_QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below.
			return;
		}

		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! function_exists( 'wp_verify_nonce' ) || ! wp_verify_nonce( $nonce, self::DISMISS_ACTION ) ) {
			return;
		}

		delete_option( self::OPTION );
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

		$message = self::message( $summary );

		if ( '' === $message ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s%s</p></div>',
			esc_html( $message ),
			self::dismiss_link() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- dismiss_link() escapes the URL and label.
		);
	}

	/**
	 * Nonce URL that deletes the notice. Empty when the helpers are unavailable.
	 *
	 * @return string
	 */
	protected static function dismiss_link() {
		if ( ! function_exists( 'wp_nonce_url' ) || ! function_exists( 'add_query_arg' ) ) {
			return '';
		}

		$url = wp_nonce_url(
			add_query_arg( self::DISMISS_QUERY, '1' ),
			self::DISMISS_ACTION
		);

		if ( function_exists( 'esc_url' ) ) {
			$url = esc_url( $url );
		}

		return ' <a class="cp-sync-visibility-notice__dismiss" href="' . $url . '">' . esc_html( __( 'Dismiss', 'cp-sync' ) ) . '</a>';
	}
}
