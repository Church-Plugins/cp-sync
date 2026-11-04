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
	 * A later sync that removes nothing must not clear the notice. The
	 * hourly cron would otherwise delete it before an administrator logs in.
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
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html( $message )
		);
	}
}
