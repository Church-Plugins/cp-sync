<?php
/**
 * WordPress posts for the event display sync.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Events;

/**
 * Reads and writes cp_event and cp_event_series posts.
 *
 * @since 1.2.0
 */
class WpEventStore implements EventStore {

	/**
	 * Stored dates, including those in the trash.
	 *
	 * @return array
	 */
	public function events() {
		return $this->load_posts( PostTypes::EVENT, false );
	}

	/**
	 * Stored series, including those in the trash.
	 *
	 * @return array
	 */
	public function series() {
		return $this->load_posts( PostTypes::SERIES, true );
	}

	/**
	 * Create or update one date.
	 *
	 * @param array $record Normalized event.
	 * @return int
	 */
	public function save_event( array $record ) {
		$post_id = $this->persist_post( PostTypes::EVENT, $record, true );

		if ( ! $post_id ) {
			return 0;
		}

		$series_id = isset( $record['series_post_id'] ) ? (int) $record['series_post_id'] : 0;

		$this->update_meta(
			$post_id,
			array(
				Meta::PROVIDER         => $record['provider'],
				Meta::OCCURRENCE_ID    => $record['occurrence_id'],
				Meta::SOURCE_ID        => $record['source_id'],
				Meta::CHMS_ID          => $record['source_id'],
				Meta::START_UTC        => $record['start_utc'],
				Meta::END_UTC          => $record['end_utc'],
				Meta::START_LOCAL      => $record['start_local'],
				Meta::END_LOCAL        => $record['end_local'],
				Meta::TIMEZONE         => $record['timezone'],
				Meta::ALL_DAY          => ! empty( $record['all_day'] ) ? '1' : '0',
				Meta::LOCATION         => $record['location'],
				Meta::IMAGE_URL        => $record['image_url'],
				Meta::REGISTRATION_URL => esc_url_raw( $record['registration_url'] ),
				Meta::SERIES_KEY       => $record['series_key'],
			)
		);

		if ( $series_id > 0 ) {
			update_post_meta( $post_id, Meta::SERIES_ID, $series_id );
		} else {
			delete_post_meta( $post_id, Meta::SERIES_ID );
		}

		$this->assign_categories( $post_id, isset( $record['categories'] ) ? $record['categories'] : array() );
		$this->maybe_set_image( $post_id, $record['image_url'], $record['title'] );

		return $post_id;
	}

	/**
	 * Create or update one series.
	 *
	 * @param array $record Normalized series.
	 * @return int
	 */
	public function save_series( array $record ) {
		$post_id = $this->persist_post( PostTypes::SERIES, $record, false );

		if ( ! $post_id ) {
			return 0;
		}

		$this->update_meta(
			$post_id,
			array(
				Meta::PROVIDER         => $record['provider'],
				Meta::SERIES_KEY       => $record['series_key'],
				Meta::CHMS_ID          => $record['series_key'],
				Meta::IMAGE_URL        => $record['image_url'],
				Meta::REGISTRATION_URL => esc_url_raw( $record['registration_url'] ),
			)
		);

		$this->maybe_set_image( $post_id, $record['image_url'], $record['title'] );

		return $post_id;
	}

	/**
	 * Trash a date.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function trash_event( $post_id ) {
		$this->trash_post( $post_id );
	}

	/**
	 * Trash a series.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function trash_series( $post_id ) {
		$this->trash_post( $post_id );
	}

	/**
	 * Load posts of one type into the row shape EventSync expects.
	 *
	 * @param string $post_type Post type.
	 * @param bool   $series    Whether these are series posts.
	 * @return array
	 */
	protected function load_posts( $post_type, $series ) {
		$ids  = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'orderby'          => 'ID',
				'order'            => 'ASC',
			)
		);
		$rows = array();

		foreach ( $ids as $post_id ) {
			$post_id = (int) $post_id;
			$row     = array(
				'post_id'  => $post_id,
				'provider' => (string) get_post_meta( $post_id, Meta::PROVIDER, true ),
				'status'   => (string) get_post_status( $post_id ),
				'locked'   => (bool) get_post_meta( $post_id, Meta::LOCK, true ),
			);

			if ( $series ) {
				$row['series_key'] = (string) get_post_meta( $post_id, Meta::SERIES_KEY, true );
			} else {
				$row['occurrence_id'] = (string) get_post_meta( $post_id, Meta::OCCURRENCE_ID, true );
				$row['start_utc']     = (string) get_post_meta( $post_id, Meta::START_UTC, true );
				$row['end_utc']       = (string) get_post_meta( $post_id, Meta::END_UTC, true );
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Insert or update the post row.
	 *
	 * @param string $post_type Post type.
	 * @param array  $record    Normalized record.
	 * @param bool   $is_event  Whether to include the local date in a new slug.
	 * @return int
	 */
	protected function persist_post( $post_type, array $record, $is_event ) {
		$post_id = isset( $record['post_id'] ) ? (int) $record['post_id'] : 0;
		$postarr = array(
			'post_type'    => $post_type,
			'post_status'  => 'publish',
			'post_title'   => $record['title'],
			'post_content' => $record['content'],
		);

		if ( $is_event ) {
			$postarr['post_excerpt'] = isset( $record['excerpt'] ) ? $record['excerpt'] : '';
		}

		if ( $post_id > 0 ) {
			if ( 'trash' === get_post_status( $post_id ) ) {
				wp_untrash_post( $post_id );
			}
			$postarr['ID'] = $post_id;
		} else {
			$slug_source = $record['title'];

			if ( $is_event && ! empty( $record['start_local'] ) ) {
				$slug_source .= '-' . substr( (string) $record['start_local'], 0, 10 );
			}

			$postarr['post_name'] = sanitize_title( $slug_source );
		}

		$result = wp_insert_post( wp_slash( $postarr ), true );

		if ( is_wp_error( $result ) || ! $result ) {
			return 0;
		}

		return (int) $result;
	}

	/**
	 * Write a map of meta values.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $meta    Key to value.
	 * @return void
	 */
	protected function update_meta( $post_id, array $meta ) {
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Replace the event's categories with the names from the feed.
	 *
	 * @param int      $post_id Post ID.
	 * @param string[] $names   Category names.
	 * @return void
	 */
	protected function assign_categories( $post_id, array $names ) {
		$term_ids = array();

		foreach ( $names as $name ) {
			$name = trim( (string) $name );

			if ( '' === $name ) {
				continue;
			}

			$slug = sanitize_title( $name );
			$term = term_exists( $slug, PostTypes::CATEGORY );

			if ( ! $term ) {
				$term = wp_insert_term( $name, PostTypes::CATEGORY, array( 'slug' => $slug ) );
			}

			if ( is_wp_error( $term ) ) {
				continue;
			}

			$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;

			if ( $term_id > 0 ) {
				$term_ids[] = $term_id;

				if ( ! get_term_meta( $term_id, Meta::CHMS_ID, true ) ) {
					add_term_meta( $term_id, Meta::CHMS_ID, PostTypes::CATEGORY . '_' . $slug, true );
				}
			}
		}

		wp_set_post_terms( $post_id, $term_ids, PostTypes::CATEGORY, false );
	}

	/**
	 * Set the featured image from the ChMS URL when it changes.
	 *
	 * The remote URL is always stored. A failed download leaves that URL for
	 * the template to use.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $url     Image URL.
	 * @param string $title   Post title, used as the attachment title.
	 * @return void
	 */
	protected function maybe_set_image( $post_id, $url, $title ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return;
		}

		if ( ! function_exists( 'media_sideload_image' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		if ( ! function_exists( 'media_sideload_image' ) ) {
			return;
		}

		$normalized = self::normalize_image_url( $url );
		$current    = (string) get_post_meta( $post_id, '_thumbnail_url', true );

		if ( get_post_thumbnail_id( $post_id ) && $current === $normalized ) {
			return;
		}

		$existing = $this->find_attachment( $normalized );

		if ( ! $existing ) {
			$existing = media_sideload_image( $url, $post_id, sanitize_text_field( $title ), 'id' );

			if ( is_wp_error( $existing ) || ! $existing ) {
				return;
			}

			update_post_meta( (int) $existing, '_cp_sync_normalized_url', $normalized );
		}

		set_post_thumbnail( $post_id, (int) $existing );
		update_post_meta( $post_id, '_thumbnail_url', $normalized );
	}

	/**
	 * Attachment already imported from this image URL.
	 *
	 * @param string $normalized URL with tracking parameters removed.
	 * @return int
	 */
	protected function find_attachment( $normalized ) {
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_key'         => '_cp_sync_normalized_url',
				'meta_value'       => $normalized,
			)
		);

		return empty( $ids ) ? 0 : (int) $ids[0];
	}

	/**
	 * Stable image identity, close to the other imports.
	 *
	 * Planning Center file URLs keep the key parameter. Other URLs drop the
	 * query string.
	 *
	 * @param string $url Image URL.
	 * @return string
	 */
	public static function normalize_image_url( $url ) {
		$url = trim( (string) $url );

		if ( false !== strpos( $url, 'planningcenterusercontent.com' ) ) {
			$parts = wp_parse_url( $url );

			if ( ! empty( $parts['query'] ) ) {
				parse_str( $parts['query'], $params );

				if ( ! empty( $params['key'] ) && ! empty( $parts['host'] ) && ! empty( $parts['path'] ) ) {
					$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'https';

					return $scheme . '://' . $parts['host'] . $parts['path'] . '?key=' . $params['key'];
				}
			}
		}

		$bare = explode( '?', $url );

		return $bare[0];
	}

	/**
	 * Move a post to the trash when it is not already there.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	protected function trash_post( $post_id ) {
		$post_id = (int) $post_id;

		if ( $post_id < 1 || 'trash' === get_post_status( $post_id ) ) {
			return;
		}

		wp_trash_post( $post_id );
	}
}
