<?php
/**
 * Tests for CP_Sync\ChMS\PCO::format_sermon() — the pure mapping from a PCO Publishing
 * episode ( plus its included series/speakership context ) to the CP Library sermon item
 * shape consumed by Integrations\CP_Library::update_item().
 *
 * format_sermon()/get_episode_art() touch no WordPress functions and no API client, so
 * the PCO instance is built without its ( hook-registering ) constructor via reflection.
 * The one exception is the diagnostic log emitted when a series carries art that cannot
 * be resolved, which needs the plugin singleton stubbed.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\ChMS\PCO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\ChMS\PCO::format_sermon
 * @covers \CP_Sync\ChMS\PCO::get_episode_art
 * @covers \CP_Sync\ChMS\PCO::resolve_art_url
 */
class FormatSermonTest extends TestCase {

	/** @var array Messages captured from the plugin logger. */
	private $logged = [];

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->logged = [];

		$logger          = new class( $this->logged ) {
			public $sink;
			public function __construct( &$sink ) { $this->sink = &$sink; }
			public function log( $message ) { $this->sink[] = $message; }
		};
		$plugin          = new class { public $logging; };
		$plugin->logging = $logger;

		Functions\when( 'cp_sync' )->justReturn( $plugin );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

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
				// PCO's real shape: a File object with the sizes under attributes.variants.
				'art'                     => [
					'type'       => 'File',
					'id'         => 165919,
					'attributes' => [
						'name'              => 'episode.jpg',
						'signed_identifier' => 'eyJfcmFpbHMiOnsiZGF0YSI6OTk5fX0=--abc123',
						'variants'          => [ 'original' => 'https://example.com/art.jpg' ],
					],
				],
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
							'art'   => [
								'type'       => 'File',
								'id'         => 242547,
								'attributes' => [
									'name'              => 'series.jpg',
									'signed_identifier' => 'eyJfcmFpbHMiOnsiZGF0YSI6ODg4fX0=--def456',
									'variants'          => [
										'original' => 'https://example.com/series-art.jpg',
										'large'    => 'https://example.com/series-large.jpg',
									],
								],
							],
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
	 * Variants are resolved by preference order, not by whatever comes first.
	 *
	 * PCO does not guarantee which renditions exist, so a File object missing
	 * `original` must still yield the largest available one.
	 */
	public function test_series_art_falls_back_through_the_variants() {
		$result = $this->makePco()->format_sermon(
			$this->episodeWithSeriesArt(
				'105',
				'57',
				[
					'type'       => 'File',
					'id'         => 1,
					'attributes' => [
						'signed_identifier' => 'sig',
						// No `original`; `medium` outranks `small` despite key order.
						'variants' => [
							'small'  => 'https://example.com/small.jpg',
							'medium' => 'https://example.com/medium.jpg',
						],
					],
				]
			),
			$this->contextWithSeries( '57', 'Acts', [
				'type'       => 'File',
				'id'         => 1,
				'attributes' => [
					'signed_identifier' => 'sig',
					'variants' => [
						'small'  => 'https://example.com/small.jpg',
						'medium' => 'https://example.com/medium.jpg',
					],
				],
			] )
		);

		$this->assertSame( 'https://example.com/medium.jpg', $result['cpl']['series']['thumbnail_url'] );
	}

	/**
	 * The documented flat-hash shape still resolves.
	 *
	 * The API docs type `art` only as "hash"; PCO currently serves a File object. If it
	 * ever serves the flat shape instead, this must not silently stop working — that is
	 * the exact failure mode that shipped a no-op the first time.
	 */
	public function test_series_art_accepts_a_flat_size_hash() {
		$result = $this->makePco()->format_sermon(
			$this->episodeWithSeriesArt( '106', '58', null ),
			$this->contextWithSeries( '58', 'Jude', [ 'original' => 'https://example.com/flat.jpg' ] )
		);

		$this->assertSame( 'https://example.com/flat.jpg', $result['cpl']['series']['thumbnail_url'] );
	}

	/**
	 * A File object carrying no usable variants resolves to '' rather than leaking
	 * the object's own scalars ( 'File', the integer id ) as an image URL.
	 */
	public function test_series_art_with_no_variants_resolves_empty() {
		$result = $this->makePco()->format_sermon(
			$this->episodeWithSeriesArt( '107', '59', null ),
			$this->contextWithSeries( '59', 'Titus', [
				'type'       => 'File',
				'id'         => 999,
				'attributes' => [ 'name' => 'broken.jpg', 'signed_identifier' => 'sig' ],
			] )
		);

		$this->assertSame( '', $result['cpl']['series']['thumbnail_url'] );

		// Art that is present but unreadable must be reported. A silent '' here is what
		// let the File-object shape ship as a no-op in the first place.
		$this->assertNotEmpty( $this->logged, 'Unresolvable series art must be logged' );
		$this->assertStringContainsString( 'Titus', $this->logged[0] );
	}

	/**
	 * A series with no art at all is normal, not an anomaly, and must stay silent.
	 */
	public function test_series_without_art_logs_nothing() {
		$this->makePco()->format_sermon(
			$this->episodeWithSeriesArt( '108', '60', null ),
			$this->contextWithSeries( '60', 'Philemon', null )
		);

		$this->assertSame( [], $this->logged );
	}

	/**
	 * The episode's channel maps to a service type.
	 *
	 * PCO exposes the channel name as `name`, not `title` as on Series — reading the
	 * wrong key would silently yield no service type at all.
	 */
	public function test_channel_maps_to_service_type() {
		$episode = [
			'id'            => '110',
			'attributes'    => [
				'title'                   => 'Sunday Message',
				'published_to_library_at' => '2026-06-01T12:00:00Z',
			],
			'relationships' => [
				'channel' => [ 'data' => [ 'type' => 'Channel', 'id' => '1351' ] ],
			],
		];

		$context = [
			'relational_data' => [
				'Channel' => [
					'1351' => [
						'id'         => '1351',
						'attributes' => [
							'name' => 'Worship Services',
							'art'  => $this->fileObject( 'https://example.com/channel.jpg' ),
						],
					],
				],
			],
			'speakers_by_id'  => [],
		];

		$result = $this->makePco()->format_sermon( $episode, $context );

		$this->assertSame(
			[ 'id' => '1351', 'title' => 'Worship Services', 'thumbnail_url' => 'https://example.com/channel.jpg' ],
			$result['cpl']['service_type']
		);
	}

	/**
	 * A channel's podcast_art outranks its general art.
	 *
	 * CP Sermons serves the service type's featured image as podcast channel artwork,
	 * and podcast_art is the square, feed-sized rendition PCO keeps for exactly that.
	 */
	public function test_channel_prefers_podcast_art_over_general_art() {
		$episode = [
			'id'            => '113',
			'attributes'    => [
				'title'                   => 'Podcast Art',
				'published_to_library_at' => '2026-06-15T12:00:00Z',
			],
			'relationships' => [
				'channel' => [ 'data' => [ 'type' => 'Channel', 'id' => '1367' ] ],
			],
		];

		$context = [
			'relational_data' => [
				'Channel' => [
					'1367' => [
						'id'         => '1367',
						'attributes' => [
							'name'        => 'Devotionals',
							'art'         => $this->fileObject( 'https://example.com/general.jpg' ),
							'podcast_art' => $this->fileObject( 'https://example.com/podcast.jpg' ),
						],
					],
				],
			],
			'speakers_by_id'  => [],
		];

		$result = $this->makePco()->format_sermon( $episode, $context );

		$this->assertSame( 'https://example.com/podcast.jpg', $result['cpl']['service_type']['thumbnail_url'] );
	}

	/**
	 * A channel with no podcast_art falls back to its general art.
	 */
	public function test_channel_falls_back_to_general_art() {
		$episode = [
			'id'            => '114',
			'attributes'    => [
				'title'                   => 'General Art',
				'published_to_library_at' => '2026-06-22T12:00:00Z',
			],
			'relationships' => [
				'channel' => [ 'data' => [ 'type' => 'Channel', 'id' => '1367' ] ],
			],
		];

		$context = [
			'relational_data' => [
				'Channel' => [
					'1367' => [
						'id'         => '1367',
						'attributes' => [
							'name'        => 'Devotionals',
							'art'         => $this->fileObject( 'https://example.com/general.jpg' ),
							// PCO returns null here on every channel observed in practice.
							'podcast_art' => null,
						],
					],
				],
			],
			'speakers_by_id'  => [],
		];

		$result = $this->makePco()->format_sermon( $episode, $context );

		$this->assertSame( 'https://example.com/general.jpg', $result['cpl']['service_type']['thumbnail_url'] );
	}

	/**
	 * Build PCO's File-object art payload around a single `original` variant.
	 *
	 * @param string $url The original rendition URL.
	 * @return array
	 */
	private function fileObject( $url ) {
		return [
			'type'       => 'File',
			'id'         => 12473005,
			'attributes' => [
				// A real upload carries a signed blob id; PCO's generated placeholders
				// leave it empty. Its presence is what marks this as genuine artwork.
				'signed_identifier' => 'eyJfcmFpbHMiOnsiZGF0YSI6MTExNjA4OX19--0d5dd132',
				'variants'          => [ 'original' => $url ],
			],
		];
	}

	/**
	 * Build PCO's auto-assigned placeholder art payload.
	 *
	 * @param string      $url    The gradient URL.
	 * @param string|null $source The `source` attribute; null omits it, as Series and
	 *                            Channel art objects do.
	 * @return array
	 */
	private function placeholderArt( $url, $source = 'default' ) {
		$attributes = [
			'name'              => '926-large.png',
			'signed_identifier' => '',
			'variants'          => [ 'original' => $url ],
		];

		if ( null !== $source ) {
			$attributes['source'] = $source;
		}

		return [ 'type' => 'File', 'id' => 926, 'attributes' => $attributes ];
	}

	/**
	 * An episode with no channel yields a null service type, which SermonSync reads
	 * as "clear any existing association" rather than "leave it alone".
	 */
	public function test_missing_channel_yields_null_service_type() {
		$result = $this->makePco()->format_sermon(
			$this->episodeWithSeriesArt( '111', '61', null ),
			$this->contextWithSeries( '61', 'Hebrews', null )
		);

		$this->assertNull( $result['cpl']['service_type'] );
	}

	/**
	 * A channel reference whose record is absent from the included payload must not
	 * produce a half-built service type.
	 */
	public function test_unresolvable_channel_yields_null_service_type() {
		$episode = [
			'id'            => '112',
			'attributes'    => [
				'title'                   => 'Orphan Channel',
				'published_to_library_at' => '2026-06-08T12:00:00Z',
			],
			'relationships' => [
				'channel' => [ 'data' => [ 'type' => 'Channel', 'id' => '9999' ] ],
			],
		];

		$result = $this->makePco()->format_sermon( $episode, [ 'relational_data' => [], 'speakers_by_id' => [] ] );

		$this->assertNull( $result['cpl']['service_type'] );
	}

	/**
	 * Build a minimal episode that references a series.
	 *
	 * @param string     $episode_id The episode id.
	 * @param string     $series_id  The series id it points at.
	 * @param array|null $unused     Ignored; keeps call sites readable.
	 * @return array
	 */
	private function episodeWithSeriesArt( $episode_id, $series_id, $unused = null ) {
		return [
			'id'            => $episode_id,
			'attributes'    => [
				'title'                   => 'Episode ' . $episode_id,
				'published_to_library_at' => '2026-05-01T12:00:00Z',
			],
			'relationships' => [
				'series' => [ 'data' => [ 'type' => 'Series', 'id' => $series_id ] ],
			],
		];
	}

	/**
	 * Build a context whose Series carries the given `art` payload.
	 *
	 * @param string $series_id The series id.
	 * @param string $title     The series title.
	 * @param mixed  $art       The `art` attribute to attach.
	 * @return array
	 */
	private function contextWithSeries( $series_id, $title, $art ) {
		return [
			'relational_data' => [
				'Series' => [
					$series_id => [
						'id'         => $series_id,
						'attributes' => [ 'title' => $title, 'art' => $art ],
					],
				],
			],
			'speakers_by_id'  => [],
		];
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
	 * An episode with no artwork yields NO image, not a video still.
	 *
	 * CP Sermons' template falls back to the series image and then the service type
	 * image, but only while the sermon's own featured image is empty. Substituting a
	 * frame grab here — which this used to do — pins every sermon to a still and the
	 * cascade never runs.
	 */
	public function test_episode_without_art_yields_no_image() {
		$episode = [
			'id'         => '104',
			'attributes' => [
				'title'                       => 'No Art',
				'published_to_library_at'     => '2026-04-01T12:00:00Z',
				'library_video_thumbnail_url' => 'https://example.com/thumb.jpg',
				'video_thumbnail_url'         => 'https://example.com/thumb2.jpg',
			],
			'relationships' => [],
		];

		$result = $this->makePco()->format_sermon( $episode, [ 'relational_data' => [], 'speakers_by_id' => [] ] );

		$this->assertSame( '', $result['thumbnail_url'] );
	}

	/* ------------------------------------------------------- placeholder artwork */

	/**
	 * PCO's auto-assigned placeholder is not artwork.
	 *
	 * Every episode has `art`; PCO fills an empty one with a generated gradient. On the
	 * calendar this was found on, 189 of 200 episodes carried one, so importing them
	 * replaced every sermon image with noise. Episodes mark it as `source: default`.
	 */
	public function test_episode_placeholder_art_is_ignored() {
		$episode = [
			'id'            => '120',
			'attributes'    => [
				'title'                   => 'Placeholder',
				'published_to_library_at' => '2026-07-01T12:00:00Z',
				'art'                     => $this->placeholderArt( 'https://cdn.example.net/926-large.png' ),
			],
			'relationships' => [],
		];

		$result = $this->makePco()->format_sermon( $episode, [ 'relational_data' => [], 'speakers_by_id' => [] ] );

		$this->assertSame( '', $result['thumbnail_url'] );
	}

	/**
	 * Series and Channel art objects carry NO `source` key, so the placeholder check
	 * cannot rely on it — an empty `signed_identifier` is the signal that holds for
	 * every record type.
	 */
	public function test_series_and_channel_placeholder_art_is_ignored() {
		$episode = [
			'id'            => '121',
			'attributes'    => [
				'title'                   => 'Placeholder Relations',
				'published_to_library_at' => '2026-07-08T12:00:00Z',
			],
			'relationships' => [
				'series'  => [ 'data' => [ 'type' => 'Series', 'id' => '70' ] ],
				'channel' => [ 'data' => [ 'type' => 'Channel', 'id' => '80' ] ],
			],
		];

		$context = [
			'relational_data' => [
				'Series'  => [
					'70' => [
						'id'         => '70',
						// `source` omitted, exactly as PCO serves related records.
						'attributes' => [ 'title' => 'Stock Series', 'art' => $this->placeholderArt( 'https://cdn.example.net/1-large.png', null ) ],
					],
				],
				'Channel' => [
					'80' => [
						'id'         => '80',
						'attributes' => [ 'name' => 'Stock Channel', 'art' => $this->placeholderArt( 'https://cdn.example.net/2-large.png', null ) ],
					],
				],
			],
			'speakers_by_id'  => [],
		];

		$result = $this->makePco()->format_sermon( $episode, $context );

		$this->assertSame( '', $result['cpl']['series']['thumbnail_url'] );
		$this->assertSame( '', $result['cpl']['service_type']['thumbnail_url'] );
	}

	/**
	 * Placeholder art must not trip the "art present but unresolvable" diagnostic —
	 * it resolves to nothing on purpose, which is not an anomaly worth logging.
	 */
	public function test_placeholder_art_logs_nothing() {
		$this->makePco()->format_sermon(
			$this->episodeWithSeriesArt( '122', '71', null ),
			$this->contextWithSeries( '71', 'Stock', $this->placeholderArt( 'https://cdn.example.net/3-large.png', null ) )
		);

		$this->assertSame( [], $this->logged );
	}
}
