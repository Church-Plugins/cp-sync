<?php
/**
 * Group meta CP Groups reads when it takes over Lite posts.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Groups;

/**
 * Defaults for meta that CP Groups queries.
 *
 * CP Groups hides full groups by default. Its archive query requires the meta
 * key `is_group_full` to equal 0. A group saved without that key does not match,
 * so it disappears from the archive and from any query that uses the same filter,
 * including a single group page. CP Groups itself writes 0 on save
 * ( Group::save_post ). Lite has to write the same default.
 *
 * The other checkbox filters ( kid_friendly, handicap_accessible, is_virtual )
 * run only when a visitor asks for them, and they match the value `on`. CP Groups
 * does not store a default for those, so a missing key is the same as "not on".
 *
 * @since 1.2.0
 */
class GroupMeta {

	/**
	 * Meta key CP Groups' hide-full query requires.
	 *
	 * @var string
	 */
	const FULL_KEY = 'is_group_full';

	/**
	 * Option set after the one-time backfill of groups that predate this default.
	 *
	 * @var string
	 */
	const BACKFILL_OPTION = 'cp_sync_group_full_backfill';

	/**
	 * Fill meta CP Groups expects when the ChMS did not send a value.
	 *
	 * An explicit full flag (`on`) is kept. A missing or blank flag becomes 0,
	 * which is what CP Groups stores for a group that is not full.
	 *
	 * @since 1.2.0
	 * @param array $meta Group meta destined for wp_insert_post().
	 * @return array
	 */
	public static function ensure( array $meta ) {
		if ( ! array_key_exists( self::FULL_KEY, $meta ) || self::is_blank( $meta[ self::FULL_KEY ] ) ) {
			$meta[ self::FULL_KEY ] = 0;
		}

		return $meta;
	}

	/**
	 * Whether CP Groups' default archive query ( hide full groups ) includes this meta.
	 *
	 * The query is `is_group_full = 0`. A missing key does not match. `on` is full
	 * and does not match.
	 *
	 * @since 1.2.0
	 * @param array $meta Stored group meta.
	 * @return bool
	 */
	public static function visible_in_cp_groups_archive( array $meta ) {
		if ( ! array_key_exists( self::FULL_KEY, $meta ) ) {
			return false;
		}

		return self::is_not_full( $meta[ self::FULL_KEY ] );
	}

	/**
	 * Whether a single group page can still load under that same filter.
	 *
	 * CP Groups skips the filter for a parsed single query, but a query that
	 * still carries it ( the case that 404s ) finds the group only when the
	 * meta matches and the post is published.
	 *
	 * @since 1.2.0
	 * @param array $post post_status and meta_input.
	 * @return bool
	 */
	public static function single_is_reachable( array $post ) {
		$status = isset( $post['post_status'] ) ? $post['post_status'] : '';

		if ( 'publish' !== $status ) {
			return false;
		}

		$meta = ( isset( $post['meta_input'] ) && is_array( $post['meta_input'] ) ) ? $post['meta_input'] : [];

		return self::visible_in_cp_groups_archive( $meta );
	}

	/**
	 * Post IDs that need the not-full default written.
	 *
	 * @since 1.2.0
	 * @param array[] $posts Each item is [ 'ID' => int, 'meta' => array ].
	 * @return array<int,int> Post ID => 0.
	 */
	public static function backfill_updates( array $posts ) {
		$updates = [];

		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) || ! isset( $post['ID'] ) ) {
				continue;
			}

			$meta = ( isset( $post['meta'] ) && is_array( $post['meta'] ) ) ? $post['meta'] : [];

			if ( array_key_exists( self::FULL_KEY, $meta ) && ! self::is_blank( $meta[ self::FULL_KEY ] ) ) {
				continue;
			}

			$updates[ (int) $post['ID'] ] = 0;
		}

		return $updates;
	}

	/**
	 * Write `is_group_full = 0` on cp_group posts that do not have the key yet.
	 *
	 * Runs once, on init, including the request where CP Groups is activated,
	 * so the archive query in that same request can still find the groups.
	 * Does nothing until the post type exists, and does not mark itself done
	 * before then.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function backfill() {
		if ( get_option( self::BACKFILL_OPTION ) ) {
			return;
		}

		if ( ! post_type_exists( 'cp_group' ) ) {
			return;
		}

		$ids = get_posts(
			[
				'post_type'      => 'cp_group',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => [
					[
						'key'     => self::FULL_KEY,
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);

		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				update_post_meta( (int) $id, self::FULL_KEY, 0 );
			}
		}

		update_option( self::BACKFILL_OPTION, 1, false );
	}

	/**
	 * @param mixed $value Meta value.
	 * @return bool
	 */
	protected static function is_blank( $value ) {
		return null === $value || '' === $value;
	}

	/**
	 * @param mixed $value Meta value.
	 * @return bool
	 */
	protected static function is_not_full( $value ) {
		if ( true === $value || 1 === $value || '1' === $value || 'on' === $value ) {
			return false;
		}

		return 0 === $value || '0' === $value;
	}
}
