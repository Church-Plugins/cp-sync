<?php
/**
 * Persistence used by the event display sync.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Stored events and series.
 *
 * The sync decides what to write. An implementation only loads and saves.
 *
 * @since 1.2.0
 */
interface EventStore {

	/**
	 * Events already stored, including trashed ones.
	 *
	 * Each row has post_id, provider, occurrence_id, end_utc, start_utc,
	 * status, and locked.
	 *
	 * @return array
	 */
	public function events();

	/**
	 * Series already stored, including trashed ones.
	 *
	 * Each row has post_id, provider, series_key, status, and locked.
	 *
	 * @return array
	 */
	public function series();

	/**
	 * Create or update one date.
	 *
	 * Pass post_id to update that post, including restoring it from the trash.
	 *
	 * @param array $record Normalized event.
	 * @return int Post ID.
	 */
	public function save_event( array $record );

	/**
	 * Create or update one series.
	 *
	 * @param array $record Normalized series.
	 * @return int Post ID.
	 */
	public function save_series( array $record );

	/**
	 * Move a date to the trash.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function trash_event( $post_id );

	/**
	 * Move a series to the trash.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function trash_series( $post_id );
}
