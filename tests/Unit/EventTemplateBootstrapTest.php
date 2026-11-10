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
		Functions\when( 'wp_is_block_theme' )->justReturn( false );
		Functions\when( 'block_template_part' )->justReturn( null );
		Functions\when( 'get_header' )->justReturn( null );
		Functions\when( 'get_footer' )->justReturn( null );
		Functions\when( 'get_option' )->justReturn( '' );
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
		Functions\when( 'esc_attr_e' )->alias(
			static function ( $text ) {
				echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
			}
		);
		Functions\when( 'get_the_terms' )->justReturn( false );
		Functions\when( 'get_terms' )->justReturn( array() );
		Functions\when( 'get_queried_object' )->justReturn( null );
		Functions\when( 'remove_query_arg' )->justReturn( 'https://example.com/events/' );
		Functions\when( 'add_query_arg' )->alias(
			static function ( $key, $value, $url ) {
				return $url . '?' . $key . '=' . $value;
			}
		);
		Functions\when( 'wp_strip_all_tags' )->alias(
			static function ( $text ) {
				return trim( strip_tags( (string) $text ) );
			}
		);
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'post_password_required' )->justReturn( false );
		Functions\when( 'get_the_password_form' )->justReturn( '<form class="post-password-form"></form>' );
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
				'_cp_start_utc'        => '2026-12-01 15:30:00',
				'_cp_end_utc'          => '2026-12-01 17:00:00',
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
		$GLOBALS['cp_sync_template_is_tax']      = false;
		$GLOBALS['cp_sync_template_is_category'] = false;
		$GLOBALS['cp_sync_template_is_tag']      = false;
		unset( $GLOBALS['cp_sync_template_term_title'] );
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
		$this->assertStringContainsString( 'data-cp-sync-calendar', $archive );
		$this->assertLessThan( strpos( $archive, 'Sunday Service' ), strpos( $archive, '<noscript>' ) );
		$this->assertStringContainsString( 'aria-current="page"', $archive );
		$this->assertStringNotContainsString( 'cp-sync-events__filter', $archive );

		$_GET['view'] = 'list';
		$list         = $this->render_main_template( 'events/archive', array( $this->event ), 'Events' );
		unset( $_GET['view'] );
		$this->assertStringNotContainsString( 'data-cp-sync-calendar', $list );
		$this->assertStringNotContainsString( '<noscript>', $list );
		$this->assertStringContainsString( 'cp-sync-event-card__button', $list );

		$single = $this->render_main_template( 'events/single', array( $this->event ), 'Events' );
		$this->assertMatchesRegularExpression( '/<h1 class="cp-sync-event__title">Sunday Service<\/h1>/', $single );
		$this->assertStringContainsString( 'src="https://example.com/service.jpg"', $single );
		$this->assertStringContainsString( '>Register</a>', $single );
		$this->assertStringContainsString( 'All events', $single );
		$this->assertStringContainsString( 'Date &amp; time', $single );
		$this->assertStringContainsString( 'Add to calendar', $single );
		$this->assertStringContainsString( 'cp-sync-ics=1', $single );
		$this->assertStringContainsString( 'https://www.google.com/maps/search/?api=1&query=Sanctuary', $single );
		$this->assertStringNotContainsString( 'This event has ended.', $single );

		$series = $this->render_main_template( 'events/series', array( $this->series ), 'Event Series' );
		$this->assertMatchesRegularExpression( '/<h1 class="cp-sync-event__title">Sunday Services<\/h1>/', $series );
		$this->assertStringContainsString( 'src="https://example.com/series.jpg"', $series );
		$this->assertStringContainsString( 'Sunday Service', $series );
		$this->assertStringContainsString( '>Register</a>', $series );
		$this->assertStringContainsString( 'Upcoming dates', $series );
		$this->assertStringNotContainsString( 'Add to calendar', $series );
		$this->assertStringNotContainsString( 'cp-sync-event__calendar', $series );
		$this->assertStringNotContainsString( 'No upcoming dates listed.', $series );

		$series_archive = $this->render_main_template( 'events/series-archive', array( $this->series ), 'Event Series' );
		$this->assertStringContainsString( 'Sunday Services', $series_archive );
		$this->assertStringContainsString( 'href="https://example.com/event-series/sunday-services"', $series_archive );
		$this->assertStringContainsString( '>Learn more</a>', $series_archive );
		$this->assertStringContainsString( 'cp-sync-series-card', $series_archive );
		$this->assertStringNotContainsString( 'cp-sync-event-card', $series_archive );
		$this->assertStringNotContainsString( '<time', $series_archive );
	}

	public function test_password_protected_pages_show_only_the_password_form() {
		Functions\when( 'post_password_required' )->justReturn( true );

		foreach ( array( 'events/single' => $this->event, 'events/series' => $this->series ) as $template => $post ) {
			$html = $this->render_main_template( $template, array( $post ), 'Events' );

			$this->assertStringContainsString( 'post-password-form', $html, $template );
			$this->assertStringNotContainsString( 'Worship together.', $html, $template );
			$this->assertStringNotContainsString( 'Every week.', $html, $template );
			$this->assertStringNotContainsString( 'Sanctuary', $html, $template );
			$this->assertStringNotContainsString( '>Register</a>', $html, $template );
		}
	}

	public function test_taxonomy_archive_heading_uses_the_term_name() {
		$GLOBALS['cp_sync_template_is_tax']     = true;
		$GLOBALS['cp_sync_template_term_title'] = 'Youth <group>';

		$html = $this->render_main_template( 'events/archive', array( $this->event ), '' );

		$this->assertMatchesRegularExpression( '/<h1 class="page-title">\s*Youth &lt;group&gt;\s*<\/h1>/', $html );

		$GLOBALS['cp_sync_template_is_tax']      = false;
		$GLOBALS['cp_sync_template_is_category'] = true;
		$GLOBALS['cp_sync_template_term_title']  = 'Small Groups';

		$category = $this->render_main_template( 'events/archive', array( $this->event ), 'Events' );

		$this->assertMatchesRegularExpression( '/<h1 class="page-title">\s*Small Groups\s*<\/h1>/', $category );

		$GLOBALS['cp_sync_template_is_category'] = false;
		$GLOBALS['cp_sync_template_is_tag']      = true;
		$GLOBALS['cp_sync_template_term_title']  = 'Outreach';

		$tag = $this->render_main_template( 'events/archive', array( $this->event ), 'Events' );

		$this->assertMatchesRegularExpression( '/<h1 class="page-title">\s*Outreach\s*<\/h1>/', $tag );

		$GLOBALS['cp_sync_template_is_tag']     = false;
		$GLOBALS['cp_sync_template_term_title'] = 'Youth';

		$archive = $this->render_main_template( 'events/archive', array( $this->event ), 'Events' );

		$this->assertMatchesRegularExpression( '/<h1 class="page-title">\s*Events\s*<\/h1>/', $archive );
	}

	public function test_series_archive_lists_each_published_series_without_an_event_date() {
		$past                 = new CP_Sync_Template_Post();
		$past->ID             = 11;
		$past->post_title     = 'Finished Retreat';
		$past->post_name      = 'finished-retreat';
		$past->post_content   = 'Already happened.';
		$past->post_type      = 'cp_event_series';
		$future               = new CP_Sync_Template_Post();
		$future->ID           = 12;
		$future->post_title   = 'Advent Series';
		$future->post_name    = 'advent-series';
		$future->post_content = 'Still ahead.';
		$future->post_type    = 'cp_event_series';

		$html = $this->render_main_template( 'events/series-archive', array( $past, $future, $this->event ), 'Event Series' );

		$this->assertStringContainsString( 'Finished Retreat', $html );
		$this->assertStringContainsString( 'href="https://example.com/event-series/finished-retreat"', $html );
		$this->assertStringContainsString( 'Advent Series', $html );
		$this->assertStringContainsString( 'href="https://example.com/event-series/advent-series"', $html );
		$this->assertStringContainsString( 'cp-sync-series-card', $html );
		$this->assertStringNotContainsString( 'cp-sync-event-card', $html );
		$this->assertStringNotContainsString( '<time', $html );
		$this->assertStringNotContainsString( 'Sunday Service', $html );
		$this->assertStringNotContainsString( 'href="https://example.com/events/sunday-service"', $html );
	}

	public function test_event_card_hides_the_date_when_start_meta_is_missing() {
		$undated               = new CP_Sync_Template_Post();
		$undated->ID           = 77;
		$undated->post_title   = 'Undated Gathering';
		$undated->post_name    = 'undated-gathering';
		$undated->post_content = 'No clock on this one.';
		$undated->post_type    = 'cp_event';
		$GLOBALS['post']       = $undated;

		$html = \CP_Sync\get_template_part(
			'events/card',
			array(
				'show_series_link' => false,
			),
			true
		);

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'Undated Gathering', $html );
		$this->assertStringContainsString( 'href="https://example.com/events/undated-gathering"', $html );
		$this->assertStringNotContainsString( 'cp-sync-event-card__when', $html );
		$this->assertStringNotContainsString( '<time', $html );
	}

	public function test_only_the_single_page_shows_a_price_and_never_for_a_free_event() {
		$saved = $GLOBALS['cp_sync_template_meta'][41];
		$GLOBALS['cp_sync_template_meta'][41]['_cp_registration_price'] = '<script>alert(1)</script>';

		$single = $this->render_main_template( 'events/single', array( $this->event ), 'Events' );
		$this->assertStringContainsString( 'cp-sync-event__price', $single );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $single );
		$this->assertStringNotContainsString( '<script>', $single );
		$this->assertLessThan( strpos( $single, '>Register</a>' ), strpos( $single, 'cp-sync-event__price' ) );

		$_GET['view'] = 'list';
		$list         = $this->render_main_template( 'events/archive', array( $this->event ), 'Events' );
		unset( $_GET['view'] );
		$this->assertStringContainsString( '>Register</a>', $list );
		$this->assertStringNotContainsString( 'alert(1)', $list );
		$this->assertStringNotContainsString( 'price', $list );

		$GLOBALS['cp_sync_template_meta'][41]['_cp_registration_price'] = 'Free';

		$free = $this->render_main_template( 'events/single', array( $this->event ), 'Events' );

		$GLOBALS['cp_sync_template_meta'][41] = $saved;

		$this->assertStringContainsString( '>Register</a>', $free );
		$this->assertStringNotContainsString( 'cp-sync-event__price', $free );
		$this->assertStringNotContainsString( '>Free<', $free );
	}

	public function test_list_renders_a_different_title_and_link_for_each_event() {
		$second              = new CP_Sync_Template_Post();
		$second->ID          = 42;
		$second->post_title  = 'Wednesday Prayer';
		$second->post_name   = 'wednesday-prayer';
		$second->post_type   = 'cp_event';

		$decoy             = new CP_Sync_Template_Post();
		$decoy->ID         = 99;
		$decoy->post_title = 'Not On The List';
		$decoy->post_name  = 'not-on-the-list';
		$decoy->post_type  = 'cp_event';
		$GLOBALS['post']   = $decoy;

		$html = \CP_Sync\get_template_part(
			'events/list',
			array(
				'events'           => array( $this->event, $second ),
				'show_series_link' => false,
			),
			true
		);

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'Sunday Service', $html );
		$this->assertStringContainsString( 'Wednesday Prayer', $html );
		$this->assertStringContainsString( 'href="https://example.com/events/sunday-service"', $html );
		$this->assertStringContainsString( 'href="https://example.com/events/wednesday-prayer"', $html );
		$this->assertStringNotContainsString( 'Not On The List', $html );
	}

	public function test_a_past_event_keeps_its_record_and_drops_register() {
		$saved = $GLOBALS['cp_sync_template_meta'][41];
		$GLOBALS['cp_sync_template_meta'][41]['_cp_start_utc']          = '2019-12-31 22:00:00';
		$GLOBALS['cp_sync_template_meta'][41]['_cp_end_utc']            = '2020-01-01 00:00:00';
		$GLOBALS['cp_sync_template_meta'][41]['_cp_registration_price'] = '$10';

		$html = $this->render_main_template( 'events/single', array( $this->event ), 'Events' );

		$GLOBALS['cp_sync_template_meta'][41] = $saved;

		$this->assertStringContainsString( 'This event has ended.', $html );
		$this->assertStringContainsString( 'Worship together.', $html );
		$this->assertStringContainsString( 'cp-sync-event__price', $html );
		$this->assertStringContainsString( '$10', $html );
		$this->assertStringContainsString( 'Add to calendar', $html );
		$this->assertStringNotContainsString( '>Register</a>', $html );
	}

	public function test_missing_image_and_invalid_date_do_not_invent_a_hero_or_a_file() {
		$saved = $GLOBALS['cp_sync_template_meta'][41];
		$GLOBALS['cp_sync_template_meta'][41]['_cp_image_url'] = '';

		$no_image = $this->render_main_template( 'events/single', array( $this->event ), 'Events' );

		$this->assertStringContainsString( 'cp-sync-event__hero--no-image', $no_image );
		$this->assertStringNotContainsString( 'cp-sync-event__media', $no_image );
		$this->assertStringNotContainsString( '<img', $no_image );

		$GLOBALS['cp_sync_template_meta'][41]['_cp_start_utc'] = '';
		$GLOBALS['cp_sync_template_meta'][41]['_cp_end_utc']   = 'not-a-date';

		$undated = $this->render_main_template( 'events/single', array( $this->event ), 'Events' );

		$GLOBALS['cp_sync_template_meta'][41] = $saved;

		$this->assertStringContainsString( 'Date to be confirmed', $undated );
		$this->assertStringNotContainsString( 'cp-sync-event__badge', $undated );
		$this->assertStringNotContainsString( 'Add to calendar', $undated );
		$this->assertStringNotContainsString( '1970', $undated );
	}

	public function test_series_dates_partial_says_when_nothing_upcoming_is_listed() {
		$html = \CP_Sync\get_template_part(
			'events/series-dates',
			array(
				'events' => array(),
			),
			true
		);

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'No upcoming dates listed.', $html );
		$this->assertStringNotContainsString( 'cp-sync-event__calendar', $html );
		$this->assertStringNotContainsString( '.ics', $html );
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
