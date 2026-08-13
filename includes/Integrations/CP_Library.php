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
			'series'    => $this->resolve_series_art( $cpl['series'] ?? null ),
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
	 * Swap a series' source art URL for a local attachment id.
	 *
	 * SermonSync owns series post creation, so it is the only side that can set the
	 * featured image — but it is a data facade and should not be doing HTTP. This
	 * plugin already has the downloader ( with normalization, media-library reuse and
	 * MIME sniffing ), so the URL is resolved to an attachment id here and handed over
	 * as data.
	 *
	 * The download happens at most once per distinct art URL: sideload_image() returns
	 * an existing attachment when one is already recorded for the normalized URL, so
	 * the second and later sermons in a series cost a lookup, not a fetch.
	 *
	 * @since 1.0.0
	 * @param array|null $series The formatted series ( see PCO::format_sermon ), or null.
	 * @return array|null The series with `thumbnail_id` set when art was resolved.
	 */
	protected function resolve_series_art( $series ) {
		if ( empty( $series ) || empty( $series['thumbnail_url'] ) ) {
			return $series;
		}

		// sideload_image() reads thumbnail_url and post_title off the item it is given;
		// the series has no post yet, so pass 0 as the attachment parent.
		$thumb_id = $this->sideload_image(
			[
				'thumbnail_url' => $series['thumbnail_url'],
				'post_title'    => $series['title'] ?? '',
			],
			0
		);

		if ( is_wp_error( $thumb_id ) ) {
			cp_sync()->logging->log( 'Could not import series art for "' . ( $series['title'] ?? '' ) . '": ' . $thumb_id->get_error_message() );
			return $series;
		}

		if ( $thumb_id ) {
			$series['thumbnail_id'] = (int) $thumb_id;
		}

		return $series;
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
