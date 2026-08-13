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
	 * Namespace SermonSync uses for the external ids of records we create.
	 *
	 * Every related record ( series, service type, speaker ) is keyed
	 * `{source}_{kind}_{externalId}`, so this prefix identifies the ones that came
	 * from this plugin as opposed to another sync source.
	 *
	 * @var string
	 */
	const SOURCE = 'pco';

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

		// Service Types are a CP Sermons Pro feature. When they are off, SermonSync
		// discards the service type entirely — so resolving its artwork first would
		// download an image and park it in the media library for a record that is never
		// created. Hand the service type over unresolved in that case.
		$service_type = $cpl['service_type'] ?? null;

		if ( $this->service_types_enabled() ) {
			$service_type = $this->resolve_art_attachment( $service_type );
		}

		$args = [
			'ID'        => $this->get_chms_item_id( $item['chms_id'] ) ?: 0,
			'source'    => self::SOURCE,
			'title'     => $item['post_title'] ?? '',
			'content'   => $item['post_content'] ?? '',
			'status'    => $item['post_status'] ?? 'publish',
			'date'      => $cpl['date'] ?? 0,
			'series'    => $this->resolve_art_attachment( $cpl['series'] ?? null ),
			'service_type' => $service_type,
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
	 * Remove imported sermons AND the related records created alongside them.
	 *
	 * The base implementation only removes posts of this integration's own post type
	 * carrying a `_chms_id`, which is just the sermons. Series, service types and
	 * speakers live in their own post types and are marked by SermonSync's external
	 * id instead, so a content reset left them behind — and, once artwork was being
	 * imported, left them pointing at attachments the reset then deleted.
	 *
	 * Scoped to this plugin's own source prefix so a library also synced from another
	 * source keeps that source's records.
	 *
	 * NOTE: SermonSync adopts an existing post whose title matches rather than creating
	 * a duplicate, and stamps its external id on it. A series built by hand and later
	 * adopted therefore carries the marker and IS removed here. That is the intended
	 * reading of a content reset — remove everything the ChMS is tracking — but it does
	 * mean the reset can take records the ChMS did not originally create.
	 *
	 * @since 1.0.0
	 * @return array{items:int,terms:int,taxonomies:int} Counts of what was removed.
	 */
	public function remove_all_content() {
		$summary = parent::remove_all_content();

		$summary['items'] += $this->remove_related_records();

		return $summary;
	}

	/**
	 * Delete the series / service types / speakers this plugin's syncs are tracking.
	 *
	 * @since 1.0.0
	 * @return int The number of posts removed.
	 */
	protected function remove_related_records() {
		global $wpdb;

		if ( ! class_exists( '\CP_Library\Util\SermonSync' ) ) {
			return 0;
		}

		$post_types = array_values( array_filter( [
			$this->related_post_type( '\CP_Library\Models\ItemType' ),
			$this->related_post_type( '\CP_Library\Models\ServiceType' ),
			$this->related_post_type( '\CP_Library\Models\Speaker' ),
		] ) );

		if ( empty( $post_types ) ) {
			return 0;
		}

		// Selected by marker alone and filtered by post type in PHP: the set is bounded
		// by how many series/speakers a library has, and a fixed query beats
		// interpolating a variable-length IN list into prepare().
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID, p.post_type
			FROM $wpdb->posts p
			JOIN $wpdb->postmeta pm ON pm.post_id = p.ID
			WHERE pm.meta_key = %s
			AND pm.meta_value LIKE %s",
			\CP_Library\Util\SermonSync::EXTERNAL_ID_META,
			$wpdb->esc_like( self::SOURCE . '_' ) . '%'
		) );

		$removed = 0;

		foreach ( (array) $rows as $row ) {
			if ( ! in_array( $row->post_type, $post_types, true ) ) {
				continue;
			}

			wp_delete_post( (int) $row->ID, true );
			$removed++;
		}

		return $removed;
	}

	/**
	 * The post type a CP Sermons model registers, when that model is available.
	 *
	 * @since 1.0.0
	 * @param string $model Fully-qualified model class name.
	 * @return string The post type, or '' when the model is not loaded.
	 */
	protected function related_post_type( $model ) {
		return class_exists( $model ) ? (string) $model::get_prop( 'post_type' ) : '';
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

		$synced_url = get_post_meta( $id, '_thumbnail_url', true );

		// Only remove an image THIS plugin attached, and only while it is still the one
		// attached. `_thumbnail_url` records what a sync last set, but WordPress leaves
		// it alone when an admin swaps the featured image in the editor — so its mere
		// presence would let a later sync delete a hand-picked image. Confirm the
		// attachment currently in the slot is the one sideloaded for that URL.
		if ( ! $synced_url ) {
			return;
		}

		$thumb_id = get_post_thumbnail_id( $id );

		if ( ! $thumb_id || get_post_meta( $thumb_id, '_cp_sync_normalized_url', true ) !== $synced_url ) {
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
	 * Whether CP Sermons has Service Types turned on.
	 *
	 * Guarded rather than called directly: this runs inside the queue worker, where CP
	 * Sermons may be a different version than the one this was written against.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	protected function service_types_enabled() {
		if ( ! function_exists( 'cp_library' ) ) {
			return false;
		}

		$post_types = cp_library()->setup->post_types ?? null;

		if ( ! $post_types || ! method_exists( $post_types, 'service_type_enabled' ) ) {
			return false;
		}

		return (bool) $post_types->service_type_enabled();
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
