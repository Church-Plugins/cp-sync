<?php

namespace CP_Sync\Integrations;

use CP_Sync\Exception;

/**
 * CP Sermons ( formerly CP Library ) sermons integration.
 *
 * Destination side of the `sermons` type: takes a formatted episode ( produced by the
 * active ChMS's `format_sermon` ) and creates/updates a CP Sermons sermon ( `cpl_item` )
 * along with its series, speakers and media. The heavy model-layer work is delegated to
 * CP Sermons' own `SermonSync` facade so this plugin never reimplements the custom-table
 * plumbing ( dual meta writes, `do_enclosure`, model-id resolution ).
 */
class CP_Library extends Integration {

	public $id = 'cp_library';

	public $type = 'sermons';

	public $label = 'Sermons';

	protected $post_type = 'cpl_item';

	/**
	 * Create or update a single sermon from a formatted item.
	 *
	 * @param array $item The formatted item ( see PCO::format_sermon ).
	 * @return int|\WP_Error The `cpl_item` post id.
	 * @throws Exception If CP Sermons is unavailable or the save fails.
	 */
	public function update_item( $item ) {
		if ( ! class_exists( '\CP_Library\Util\SermonSync' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in Integration::task() and only logged.
			throw new Exception( 'CP Sermons SermonSync facade is not available; is CP Sermons up to date?' );
		}

		$cpl = $item['cpl'] ?? [];

		$args = [
			'ID'        => $this->get_chms_item_id( $item['chms_id'] ) ?: 0,
			'source'    => 'pco',
			'title'     => $item['post_title'] ?? '',
			'content'   => $item['post_content'] ?? '',
			'status'    => $item['post_status'] ?? 'publish',
			'date'      => $cpl['date'] ?? 0,
			'series'    => $this->resolve_art_attachment( $cpl['series'] ?? null ),
			'service_type' => $this->resolve_art_attachment( $cpl['service_type'] ?? null ),
			'speakers'  => $cpl['speakers'] ?? [],
			'video_url' => $cpl['video_url'] ?? '',
			'audio_url' => $cpl['audio_url'] ?? '',
			'scripture' => $cpl['scripture'] ?? [],
			'topics'    => $cpl['topics'] ?? [],
			'season'    => $cpl['season'] ?? [],
		];

		$id = \CP_Library\Util\SermonSync::upsert( $args );

		if ( is_wp_error( $id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in Integration::task() and only logged.
			throw new Exception( 'Sermon could not be saved: ' . $id->get_error_message() );
		}

		return $id;
	}

	/**
	 * Attach the sermon's artwork, or clear a previously synced one.
	 *
	 * The base implementation only ever sets an image. That is right for events and
	 * groups, but a sermon with no artwork of its own must end up with an EMPTY
	 * featured image so CP Sermons' template can fall back to the series image and
	 * then the service type image. Leaving a stale one attached silently disables
	 * that cascade — including the placeholder gradients an earlier sync imported.
	 *
	 * @since 1.0.0
	 * @param array $item The formatted item.
	 * @param int   $id   The sermon post id.
	 */
	public function maybe_sideload_thumb( $item, $id ) {
		if ( ! empty( $item['thumbnail_url'] ) ) {
			parent::maybe_sideload_thumb( $item, $id );
			return;
		}

		// Only remove an image THIS plugin attached. maybe_sideload_thumb() records
		// `_thumbnail_url` whenever it sets one, so its absence means the image was
		// chosen by hand and must survive.
		if ( ! get_post_meta( $id, '_thumbnail_url', true ) ) {
			return;
		}

		delete_post_thumbnail( $id );
		delete_post_meta( $id, '_thumbnail_url' );

		cp_sync()->logging->log( sprintf(
			'Cleared synced artwork from sermon %d ( the episode has none of its own in PCO ); the template will fall back to series, then service type.',
			$id
		) );
	}

	/**
	 * Swap a related record's source art URL for a local attachment id.
	 *
	 * Used for both the series and the service type. SermonSync owns creation of those
	 * posts, so it is the only side that can set the featured image — but it is a data
	 * facade and should not be doing HTTP. This plugin already has the downloader ( with
	 * normalization, media-library reuse and MIME sniffing ), so the URL is resolved to
	 * an attachment id here and handed over as data.
	 *
	 * The download happens at most once per distinct art URL: sideload_image() returns
	 * an existing attachment when one is already recorded for the normalized URL, so the
	 * second and later sermons sharing a series or channel cost a lookup, not a fetch.
	 * That matters here — PCO art URLs are signed and rotate, and it is
	 * normalize_thumbnail_url() keying them on their `key` parameter that keeps a
	 * re-sync from re-downloading the same image.
	 *
	 * @since 1.0.0
	 * @param array|null $record The formatted series or service type, or null.
	 * @return array|null The record with `thumbnail_id` set when art was resolved.
	 */
	protected function resolve_art_attachment( $record ) {
		if ( empty( $record ) || empty( $record['thumbnail_url'] ) ) {
			return $record;
		}

		// sideload_image() reads thumbnail_url and post_title off the item it is given;
		// the record has no post yet, so pass 0 as the attachment parent.
		$thumb_id = $this->sideload_image(
			[
				'thumbnail_url' => $record['thumbnail_url'],
				'post_title'    => $record['title'] ?? '',
			],
			0
		);

		if ( is_wp_error( $thumb_id ) ) {
			cp_sync()->logging->log( 'Could not import art for "' . ( $record['title'] ?? '' ) . '": ' . $thumb_id->get_error_message() );
			return $record;
		}

		if ( $thumb_id ) {
			$record['thumbnail_id'] = (int) $thumb_id;
		}

		return $record;
	}

	/**
	 * Bind a dynamically-created taxonomy to the sermon post type.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @param array  $args     The taxonomy registration args.
	 */
	public function register_taxonomy( $taxonomy, $args ) {
		register_taxonomy( $taxonomy, 'cpl_item', $args );
	}
}
