<?php

namespace CP_Sync\Integrations;

use CP_Sync\Exception;

/**
 * CP Library ( sermons ) integration.
 *
 * Destination side of the `sermons` type: takes a formatted episode ( produced by the
 * active ChMS's `format_sermon` ) and creates/updates a CP Library sermon ( `cpl_item` )
 * along with its series, speakers and media. The heavy model-layer work is delegated to
 * CP Library's own `SermonSync` facade so this plugin never reimplements the custom-table
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
	 * @throws Exception If CP Library is unavailable or the save fails.
	 */
	public function update_item( $item ) {
		if ( ! class_exists( '\CP_Library\Util\SermonSync' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in Integration::task() and only logged.
			throw new Exception( 'CP Library SermonSync facade is not available; is CP Library up to date?' );
		}

		$cpl = $item['cpl'] ?? [];

		$args = [
			'ID'        => $this->get_chms_item_id( $item['chms_id'] ) ?: 0,
			'source'    => 'pco',
			'title'     => $item['post_title'] ?? '',
			'content'   => $item['post_content'] ?? '',
			'status'    => $item['post_status'] ?? 'publish',
			'date'      => $cpl['date'] ?? 0,
			'series'    => $cpl['series'] ?? null,
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
	 * Bind a dynamically-created taxonomy to the sermon post type.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @param array  $args     The taxonomy registration args.
	 */
	public function register_taxonomy( $taxonomy, $args ) {
		register_taxonomy( $taxonomy, 'cpl_item', $args );
	}
}
