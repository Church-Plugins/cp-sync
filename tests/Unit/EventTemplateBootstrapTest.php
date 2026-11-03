<?php
/**
 * Render the event templates through the real template loader.
 *
 * get_template_hierarchy() is not mocked. The test fails if the plugin
 * bootstrap does not load includes/Templates.php.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync_Template_Post;
use CP_Sync_Template_Query;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\get_template_hierarchy
 * @covers \CP_Sync\get_template_part
 */
class EventTemplateBootstrapTest extends TestCase {

	/** @var CP_Sync_Template_Post */
	private $event;

	/** @var CP_Sync_Template_Post */
	private $series;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html_e' )->alias(
			static function ( $text ) {
				echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
			}
		);
		Functions\when( 'esc_html' )->alias(
			static function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
			}
		);
		Functions\when( 'esc_attr' )->alias(
			static function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
			}
		);
		Functions\when( 'esc_url' )->alias(
			static function ( $url ) {
				$url = trim( (string) $url );

				return preg_match( '#^https?://#i', $url ) ? $url : '';
			}
		);
		Functions\when( 'wp_kses_post' )->returnArg( 1 );
		Functions\when( 'wp_strip_all_tags' )->alias(
			static function ( $text ) {
				return trim( strip_tags( (string) $text ) );
			}
		);
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults = array() ) {
				return array_merge( (array) $defaults, is_array( $args ) ? $args : array() );
			}
		);

		$bootstrap = file_get_contents( dirname( __DIR__, 2 ) . '/cp-sync.php' );
		$this->assertIsString( $bootstrap );

		if ( ! preg_match( "/require_once CP_SYNC_PLUGIN_DIR \\. '([^']*Templates\\.php)'/", $bootstrap, $match ) ) {
			$this->fail( 'cp-sync.php must load includes/Templates.php before the event templates run.' );
		}

		require_once dirname( __DIR__, 2 ) . '/' . $match[1];
		require_once __DIR__ . '/../Support/event-template-runtime.php';

		$this->assertTrue( function_exists( 'CP_Sync\\get_template_hierarchy' ) );

		$this->event           = new CP_Sync_Template_Post();
		$this->event->ID       = 41;
		$this->event->post_title   = 'Sunday Service';
		$this->event->post_name    = 'sunday-service';
		$this->event->post_content = '<p>Worship together.</p>';
		$this->event->post_type    = 'cp_event';

		$this->series              = new CP_Sync_Template_Post();
		$this->series->ID          = 9;
		$this->series->post_title  = 'Sunday Services';
		$this->series->post_name   = 'sunday-services';
		$this->series->post_content = '<p>Every week.</p>';
		$this->series->post_type   = 'cp_event_series';

		$GLOBALS['cp_sync_template_meta'] = array(
			41 => array(
				'_cp_start_utc'        => '2026-08-01 15:30:00',
				'_cp_end_utc'          => '2026-08-01 17:00:00',
				'_cp_timezone'         => 'UTC',
				'_cp_all_day'          => '0',
				'_cp_location'         => 'Sanctuary',
				'_cp_registration_url' => 'https://example.com/register',
				'_cp_image_url'        => 'https://example.com/service.jpg',
				'_cp_series_id'        => '9',
			),
			9  => array(
				'_cp_registration_url' => '',
				'_cp_image_url'        => 'https://example.com/series.jpg',
			),
		);
		$GLOBALS['cp_sync_template_extra_posts'] = array( $this->event );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_archive_single_and_series_templates_render() {
		$archive = $this->render_main_template( 'events/archive', array( $this->event ), 'Events' );
		$this->assertStringContainsString( 'Sunday Service', $archive );
		$this->assertStringContainsString( 'href="https://example.com/events/sunday-service"', $archive );
		$this->assertStringContainsString( '>Register</a>', $archive );
		$this->assertStringContainsString( 'cp-sync-event-card__button', $archive );
		$this->assertStringContainsString( 'navigation pagination', $archive );

		$single = $this->render_main_template( 'events/single', array( $this->event ), 'Events' );
		$this->assertStringContainsString( '<h1>Sunday Service</h1>', $single );
		$this->assertStringContainsString( 'src="https://example.com/service.jpg"', $single );
		$this->assertStringContainsString( '>Register</a>', $single );
		$this->assertStringContainsString( 'All events', $single );

		$series = $this->render_main_template( 'events/series', array( $this->series ), 'Event Series' );
		$this->assertStringContainsString( '<h1>Sunday Services</h1>', $series );
		$this->assertStringContainsString( 'src="https://example.com/series.jpg"', $series );
		$this->assertStringContainsString( 'Sunday Service', $series );
		$this->assertStringContainsString( '>Register</a>', $series );

		$series_archive = $this->render_main_template( 'events/series-archive', array( $this->series ), 'Event Series' );
		$this->assertStringContainsString( 'Sunday Services', $series_archive );
		$this->assertStringContainsString( 'href="https://example.com/event-series/sunday-services"', $series_archive );
		$this->assertStringContainsString( '>Learn more</a>', $series_archive );
		$this->assertStringContainsString( 'cp-sync-event-card__media', $series_archive );
		$this->assertStringContainsString( 'cp-sync-event-card__summary', $series_archive );
	}

	/**
	 * Render one top-level template with the main query set to $posts.
	 *
	 * @param string                   $template Template path without .php.
	 * @param CP_Sync_Template_Post[]  $posts    Posts for the main loop.
	 * @param string                   $title    Archive title.
	 * @return string
	 */
	private function render_main_template( $template, array $posts, $title ) {
		$query         = new CP_Sync_Template_Query();
		$query->posts  = $posts;
		$GLOBALS['wp_query'] = $query;
		$GLOBALS['cp_sync_template_archive_title'] = $title;

		$html = \CP_Sync\get_template_part( $template, array(), true );

		$this->assertIsString( $html );
		$this->assertNotSame( '', $html );

		return $html;
	}
}
