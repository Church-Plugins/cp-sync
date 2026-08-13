<?php
/**
 * Tests for CP_Sync\ChMS\PCO::format_sermon() — the pure mapping from a PCO Publishing
 * episode ( plus its included series/speakership context ) to the CP Library sermon item
 * shape consumed by Integrations\CP_Library::update_item().
 *
 * format_sermon()/get_episode_art() touch no WordPress functions and no API client, so
 * the PCO instance is built without its ( hook-registering ) constructor via reflection.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\ChMS\PCO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\PCO::format_sermon
 * @covers \CP_Sync\ChMS\PCO::get_episode_art
 */
class FormatSermonTest extends TestCase {

	/**
	 * Build a PCO instance without invoking the constructor ( which registers WP hooks ).
	 *
	 * @return PCO
	 */
	private function makePco(): PCO {
		return ( new \ReflectionClass( PCO::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * A fully-populated episode maps every field, resolving series + speaker from context.
	 */
	public function test_maps_full_episode() {
		$episode = [
			'id'         => '101',
			'attributes' => [
				'title'                   => 'Grace Abounds',
				'description'             => 'A message about grace.',
				'published_to_library_at' => '2026-01-15T12:00:00Z',
				'library_video_url'       => 'https://example.com/video.mp4',
				'library_audio_url'       => 'https://example.com/audio.mp3',
				'art'                     => [ 'original' => 'https://example.com/art.jpg' ],
			],
			'relationships' => [
				'series'       => [ 'data' => [ 'type' => 'Series', 'id' => '55' ] ],
				'speakerships' => [ 'data' => [ [ 'type' => 'Speakership', 'id' => '900' ] ] ],
			],
		];

		$context = [
			'relational_data' => [
				// Series art is deliberately a DIFFERENT image from the episode's, so a
				// regression that reuses the episode art for the series is visible.
				'Series'      => [
					'55' => [
						'id'         => '55',
						'attributes' => [
							'title' => 'Romans',
							'art'   => [ 'original' => 'https://example.com/series-art.jpg' ],
						],
					],
				],
				'Speakership' => [
					'900' => [
						'id'            => '900',
						'relationships' => [ 'speaker' => [ 'data' => [ 'type' => 'Speaker', 'id' => '7' ] ] ],
					],
				],
			],
			'speakers_by_id'  => [ '7' => 'Jane Pastor' ],
		];

		$result = $this->makePco()->format_sermon( $episode, $context );

		$this->assertSame( '101', $result['chms_id'] );
		$this->assertSame( 'Grace Abounds', $result['post_title'] );
		$this->assertSame( 'A message about grace.', $result['post_content'] );
		$this->assertSame( 'https://example.com/art.jpg', $result['thumbnail_url'] );
		$this->assertSame( strtotime( '2026-01-15T12:00:00Z' ), $result['cpl']['date'] );
		$this->assertSame(
			[
				'id'            => '55',
				'title'         => 'Romans',
				'thumbnail_url' => 'https://example.com/series-art.jpg',
			],
			$result['cpl']['series']
		);
		$this->assertSame( [ [ 'id' => '7', 'name' => 'Jane Pastor' ] ], $result['cpl']['speakers'] );
		$this->assertSame( 'https://example.com/video.mp4', $result['cpl']['video_url'] );
		$this->assertSame( 'https://example.com/audio.mp3', $result['cpl']['audio_url'] );
	}

	/**
	 * With no series relationship, series is null and speakers is an empty list.
	 */
	public function test_missing_series_and_speakers() {
		$episode = [
			'id'            => '102',
			'attributes'    => [
				'title'                   => 'Standalone',
				'published_to_library_at' => '2026-02-01T12:00:00Z',
			],
			'relationships' => [],
		];

		$result = $this->makePco()->format_sermon( $episode, [ 'relational_data' => [], 'speakers_by_id' => [] ] );

		$this->assertNull( $result['cpl']['series'] );
		$this->assertSame( [], $result['cpl']['speakers'] );
		$this->assertSame( '', $result['cpl']['video_url'] );
		$this->assertSame( '', $result['cpl']['audio_url'] );
	}

	/**
	 * A series with no art still maps, with an empty thumbnail_url.
	 *
	 * PCO leaves `art` off entirely when a series has no graphic, so the key must be
	 * present-but-empty rather than missing — CP_Library::resolve_series_art() checks
	 * it before attempting any download.
	 */
	public function test_series_without_art_yields_empty_thumbnail_url() {
		$episode = [
			'id'         => '104',
			'attributes' => [
				'title'                   => 'No Series Art',
				'published_to_library_at' => '2026-04-01T12:00:00Z',
			],
			'relationships' => [
				'series' => [ 'data' => [ 'type' => 'Series', 'id' => '56' ] ],
			],
		];

		$context = [
			'relational_data' => [
				'Series' => [ '56' => [ 'id' => '56', 'attributes' => [ 'title' => 'Psalms' ] ] ],
			],
			'speakers_by_id'  => [],
		];

		$result = $this->makePco()->format_sermon( $episode, $context );

		$this->assertSame( '', $result['cpl']['series']['thumbnail_url'] );
		$this->assertSame( 'Psalms', $result['cpl']['series']['title'] );
	}

	/**
	 * The series art hash is resolved by preference order, not by whatever comes first.
	 *
	 * PCO does not guarantee which sizes are present or in what order, so a hash
	 * missing `original` must still yield the best available size.
	 */
	public function test_series_art_falls_back_through_the_hash() {
		$episode = [
			'id'         => '105',
			'attributes' => [
				'title'                   => 'Art Fallback',
				'published_to_library_at' => '2026-05-01T12:00:00Z',
			],
			'relationships' => [
				'series' => [ 'data' => [ 'type' => 'Series', 'id' => '57' ] ],
			],
		];

		$context = [
			'relational_data' => [
				'Series' => [
					'57' => [
						'id'         => '57',
						'attributes' => [
							'title' => 'Acts',
							// No `original`; `detail` outranks `thumbnail` despite order.
							'art'   => [
								'thumbnail' => 'https://example.com/small.jpg',
								'detail'    => 'https://example.com/detail.jpg',
							],
						],
					],
				],
			],
			'speakers_by_id'  => [],
		];

		$result = $this->makePco()->format_sermon( $episode, $context );

		$this->assertSame( 'https://example.com/detail.jpg', $result['cpl']['series']['thumbnail_url'] );
	}

	/**
	 * When no library video URL is present, the raw video_url is used as a fallback.
	 */
	public function test_video_url_falls_back_to_raw() {
		$episode = [
			'id'         => '103',
			'attributes' => [
				'title'                   => 'Fallback Video',
				'published_to_library_at' => '2026-03-01T12:00:00Z',
				'video_url'               => 'https://example.com/raw.mp4',
			],
			'relationships' => [],
		];

		$result = $this->makePco()->format_sermon( $episode, [ 'relational_data' => [], 'speakers_by_id' => [] ] );

		$this->assertSame( 'https://example.com/raw.mp4', $result['cpl']['video_url'] );
	}

	/**
	 * When art has no direct URL, the thumbnail URL fields are used as a fallback.
	 */
	public function test_art_falls_back_to_thumbnail() {
		$episode = [
			'id'         => '104',
			'attributes' => [
				'title'                       => 'Thumb Fallback',
				'published_to_library_at'     => '2026-04-01T12:00:00Z',
				'library_video_thumbnail_url' => 'https://example.com/thumb.jpg',
			],
			'relationships' => [],
		];

		$result = $this->makePco()->format_sermon( $episode, [ 'relational_data' => [], 'speakers_by_id' => [] ] );

		$this->assertSame( 'https://example.com/thumb.jpg', $result['thumbnail_url'] );
	}
}
