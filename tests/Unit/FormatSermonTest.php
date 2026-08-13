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
						'name'     => 'episode.jpg',
						'variants' => [ 'original' => 'https://example.com/art.jpg' ],
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
									'name'     => 'series.jpg',
									'variants' => [
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
				'attributes' => [ 'name' => 'broken.jpg' ],
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
				'Channel' => [ '1351' => [ 'id' => '1351', 'attributes' => [ 'name' => 'Worship Services' ] ] ],
			],
			'speakers_by_id'  => [],
		];

		$result = $this->makePco()->format_sermon( $episode, $context );

		$this->assertSame(
			[ 'id' => '1351', 'title' => 'Worship Services' ],
			$result['cpl']['service_type']
		);
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
