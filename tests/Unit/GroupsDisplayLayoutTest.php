<?php
/**
 * Groups Lite card and single layout, without archive filters.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Groups\Display;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Groups\Display
 */
class GroupsDisplayLayoutTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
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
		Functions\when( 'wp_strip_all_tags' )->alias(
			static function ( $text ) {
				return trim( strip_tags( (string) $text ) );
			}
		);
		Functions\when( 'wp_trim_words' )->alias(
			static function ( $text ) {
				return $text;
			}
		);
		Functions\when( 'wp_kses_post' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_archive_card_is_a_horizontal_summary_without_filter_hooks() {
		$html = Display::render_group(
			array(
				'title'            => 'Neighborhood Table',
				'permalink'        => 'https://church.example/groups/neighborhood-table',
				'image'            => 'https://church.example/photo.jpg',
				'content'          => 'A welcoming space for conversation.',
				'time_desc'        => 'Thursdays at 6:30 PM',
				'location'         => 'Example Community Hall',
				'terms'            => array( 'Community groups', 'Community groups', 'Adults' ),
				'registration_url' => '',
			)
		);

		$this->assertStringContainsString( 'cp-sync-groups__card cp-sync-groups__card--summary', $html );
		$this->assertStringContainsString( 'cp-sync-groups__media', $html );
		$this->assertStringContainsString( 'cp-sync-groups__details', $html );
		$this->assertStringContainsString( 'cp-sync-groups__terms', $html );
		$this->assertStringContainsString( 'Community groups', $html );
		$this->assertStringContainsString( 'Adults', $html );
		$this->assertSame( 1, substr_count( $html, 'Community groups' ) );
		$this->assertStringContainsString( 'cp-sync-groups__metadata', $html );
		$this->assertStringContainsString( 'cp-sync-groups__title"><a href="https://church.example/groups/neighborhood-table"', $html );
		$this->assertStringContainsString( '>Learn more<', $html );
		$this->assertStringNotContainsString( 'href="https://church.example/groups/neighborhood-table">Community groups', $html );
		$this->assertStringNotContainsString( 'cp-group-item', $html );
		$this->assertStringNotContainsString( 'cp-groups-filter', $html );
		$this->assertStringNotContainsString( 'Filter by', $html );
		$this->assertStringNotContainsString( 'Apply filters', $html );
		$this->assertStringNotContainsString( 'cp-sync-groups__filters', $html );

		$terms = strpos( $html, 'cp-sync-groups__terms' );
		$title = strpos( $html, 'cp-sync-groups__title' );
		$meta  = strpos( $html, 'cp-sync-groups__metadata' );
		$copy  = strpos( $html, 'cp-sync-groups__description' );
		$action = strpos( $html, 'Learn more' );

		$this->assertLessThan( $title, $terms );
		$this->assertLessThan( $meta, $title );
		$this->assertLessThan( $copy, $meta );
		$this->assertLessThan( $action, $copy );
	}

	public function test_single_group_omits_media_and_register_when_they_are_absent() {
		$html = Display::render_group(
			array(
				'title'     => 'Prayer',
				'permalink' => 'https://church.example/groups/prayer',
				'content'   => '',
				'leader'    => 'Grace Hopper',
			),
			array(
				'summary'    => false,
				'heading'    => 'h1',
				'link_title' => false,
			)
		);

		$this->assertStringContainsString( 'class="cp-sync-group"', $html );
		$this->assertStringContainsString( '<h1 class="cp-sync-group__title">Prayer</h1>', $html );
		$this->assertStringContainsString( 'Grace Hopper', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'cp-sync-groups__placeholder', $html );
		$this->assertStringNotContainsString( 'cp-sync-group__media', $html );
		$this->assertStringNotContainsString( 'cp-sync-groups__description', $html );
		$this->assertStringNotContainsString( 'cp-sync-groups__button', $html );
		$this->assertStringNotContainsString( 'Register', $html );
		$this->assertStringNotContainsString( 'Learn more', $html );
		$this->assertStringNotContainsString( 'cp-sync-groups__card', $html );
	}

	public function test_single_group_keeps_the_signup_link_and_full_description() {
		$html = Display::render_group(
			array(
				'title'            => 'Neighborhood Table',
				'permalink'        => 'https://church.example/groups/neighborhood-table',
				'image'            => 'https://church.example/photo.jpg',
				'content'          => '<p>Full description.</p>',
				'registration_url' => 'https://church.example/register',
			),
			array(
				'summary'    => false,
				'heading'    => 'h1',
				'link_title' => false,
			)
		);

		$this->assertStringContainsString( 'cp-sync-group__media', $html );
		$this->assertStringContainsString( 'src="https://church.example/photo.jpg"', $html );
		$this->assertStringContainsString( 'alt="Neighborhood Table"', $html );
		$this->assertStringContainsString( 'cp-sync-group__actions', $html );
		$this->assertStringContainsString( 'href="https://church.example/register"', $html );
		$this->assertStringContainsString( '>Register<', $html );
		$this->assertStringContainsString( 'Full description.', $html );
		$this->assertStringNotContainsString( 'Learn more', $html );

		$image = strpos( $html, 'cp-sync-group__media' );
		$title = strpos( $html, 'cp-sync-group__title' );
		$copy  = strpos( $html, 'cp-sync-groups__description' );
		$action = strpos( $html, 'cp-sync-group__actions' );

		$this->assertLessThan( $title, $image );
		$this->assertLessThan( $copy, $title );
		$this->assertLessThan( $action, $copy );
	}

	public function test_a_long_title_is_not_truncated() {
		$title = str_repeat( 'Neighborhood ', 20 ) . 'Table';
		$html  = Display::render_group(
			array(
				'title'     => $title,
				'permalink' => 'https://church.example/groups/long',
			)
		);

		$this->assertStringContainsString( $title, $html );
		$this->assertStringNotContainsString( '…', $html );
	}

	public function test_templates_do_not_add_filter_controls() {
		$archive = file_get_contents( dirname( __DIR__, 2 ) . '/templates/groups/archive.php' );
		$single  = file_get_contents( dirname( __DIR__, 2 ) . '/templates/groups/single.php' );
		$display = file_get_contents( dirname( __DIR__, 2 ) . '/includes/Groups/Display.php' );

		foreach ( array( $archive, $single, $display ) as $source ) {
			$this->assertIsString( $source );
			$this->assertStringNotContainsString( 'Apply filters', $source );
			$this->assertStringNotContainsString( 'Filter by', $source );
			$this->assertStringNotContainsString( 'cp-sync-groups__filters', $source );
			$this->assertStringNotContainsString( 'cp-group-item', $source );
		}

		$this->assertStringContainsString( 'cp-sync-groups--embed', $display );
		$this->assertLessThan( strpos( $single, 'Display::render_post' ), strpos( $single, 'All groups' ) );
	}

	public function test_stylesheet_keeps_type_size_targets_and_reduced_motion() {
		$css = file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/groups.css' );

		$this->assertIsString( $css );
		$this->assertStringContainsString( 'font-size: max(16px, 1rem)', $css );
		$this->assertStringContainsString( 'font-family: inherit', $css );
		$this->assertStringContainsString( 'minmax(0, 35%)', $css );
		$this->assertStringContainsString( 'min-height: 48px', $css );
		$this->assertStringContainsString( 'min-height: 44px', $css );
		$this->assertStringContainsString( 'outline: 3px solid', $css );
		$this->assertStringContainsString( 'prefers-reduced-motion', $css );
		$this->assertStringContainsString( '--cp-gap--md', $css );
		$this->assertStringContainsString( '--cp-border-radius', $css );
		$this->assertStringNotContainsString( 'text-overflow', $css );
		$this->assertStringNotContainsString( 'h1,', $css );
		$this->assertStringNotContainsString( '.cp-button', $css );
	}
}
