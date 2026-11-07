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

	public function test_list_card_uses_the_cp_groups_markup_without_filter_hooks() {
		$html = Display::render_group(
			array(
				'title'            => 'Neighborhood Table',
				'permalink'        => 'https://church.example/groups/neighborhood-table',
				'image'            => 'https://church.example/photo.jpg',
				'content'          => 'A welcoming space for conversation.',
				'time_desc'        => 'Thursdays at 6:30 PM',
				'location'         => 'Example Community Hall',
				'terms'            => array(
					'category'   => array(
						array(
							'name' => 'Community groups',
							'url'  => 'https://church.example/categories/community/',
						),
						array(
							'name' => 'Community groups',
							'url'  => 'https://church.example/categories/community/',
						),
					),
					'life-stage' => array(
						array(
							'name' => 'Adults',
							'url'  => '',
						),
					),
				),
			)
		);

		foreach ( array( 'cp-group-item cp-group-item--disable-modal', 'cp-group-item--thumb--canvas', 'cp-group-item--details', 'cp-group-item--categories', 'cp-group-item--meta', 'cp-group-item--content' ) as $class ) {
			$this->assertStringContainsString( $class, $html );
		}

		$this->assertSame( 1, substr_count( $html, 'Community groups' ) );
		$this->assertStringContainsString( '<a class="cp-button is-xsmall" href="https://church.example/categories/community/">Community groups</a>', $html );
		// A term with no usable link is a label, not a dead link.
		$this->assertStringContainsString( '<span class="cp-button is-xsmall is-transparent">Adults</span>', $html );
		$this->assertStringContainsString( 'cp-group-item--title"><a href="https://church.example/groups/neighborhood-table"', $html );
		$this->assertStringNotContainsString( 'cp-groups-filter', $html );
		$this->assertStringNotContainsString( 'cp-group-modal', $html );
		$this->assertStringNotContainsString( 'Filter by', $html );

		$terms = strpos( $html, 'cp-group-item--categories' );
		$title = strpos( $html, 'cp-group-item--title' );
		$meta  = strpos( $html, 'cp-group-item--meta' );
		$copy  = strpos( $html, 'cp-group-item--content' );

		$this->assertLessThan( $title, $terms );
		$this->assertLessThan( $meta, $title );
		$this->assertLessThan( $copy, $meta );
	}

	public function test_attributes_show_only_when_turned_on() {
		$html = Display::render_group(
			array(
				'title'        => 'Prayer',
				'is_full'      => 'on',
				'kid_friendly' => '0',
				'accessible'   => '',
			)
		);

		$this->assertStringContainsString( 'cp-group-item--attributes--is-full', $html );
		$this->assertStringNotContainsString( 'kid-friendly', $html );
		$this->assertStringNotContainsString( 'cp-group-item--attributes--accessible', $html );
	}

	public function test_single_group_omits_media_and_actions_when_they_are_absent() {
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

		$this->assertStringContainsString( 'class="cp-group-single"', $html );
		$this->assertStringContainsString( '<h1 class="cp-group-single--title">Prayer</h1>', $html );
		$this->assertStringContainsString( '<h6 class="cp-group-single--leader">Grace Hopper</h6>', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'cp-group-single--thumb', $html );
		$this->assertStringNotContainsString( 'cp-group-single--content', $html );
		$this->assertStringNotContainsString( 'cp-group-single--actions', $html );
		$this->assertStringNotContainsString( 'cp-group-item ', $html );
	}

	public function test_single_group_keeps_the_signup_link_and_full_description() {
		$html = Display::render_group(
			array(
				'title'            => 'Neighborhood Table',
				'permalink'        => 'https://church.example/groups/neighborhood-table',
				'image'            => 'https://church.example/photo.jpg',
				'content'          => '<p>Full description.</p>',
				'registration_url' => 'https://church.example/register',
				'public_url'       => 'https://church.example/details',
			),
			array(
				'summary'    => false,
				'heading'    => 'h1',
				'link_title' => false,
			)
		);

		$this->assertStringContainsString( 'src="https://church.example/photo.jpg"', $html );
		$this->assertStringContainsString( 'alt="Neighborhood Table"', $html );
		$this->assertStringContainsString( 'class="cp-button" href="https://church.example/register"', $html );
		$this->assertStringContainsString( '>Register Now<', $html );
		$this->assertStringContainsString( 'class="cp-button is-large" href="https://church.example/details"', $html );
		$this->assertStringContainsString( 'Full description.', $html );

		$image  = strpos( $html, 'cp-group-single--thumb' );
		$title  = strpos( $html, 'cp-group-single--title' );
		$copy   = strpos( $html, 'cp-group-single--content' );
		$action = strpos( $html, 'cp-group-single--actions' );

		$this->assertLessThan( $title, $image );
		$this->assertLessThan( $copy, $title );
		$this->assertLessThan( $action, $copy );
	}

	public function test_templates_use_the_cp_groups_wrappers_and_no_filter_controls() {
		$archive = (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/groups/archive.php' );
		$single  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/groups/single.php' );

		foreach ( array( $archive, $single ) as $source ) {
			$this->assertStringContainsString( 'cp-pg-template cp-sync-display', $source );
			$this->assertStringNotContainsString( 'cp-groups-filter', $source );
			$this->assertStringNotContainsString( 'Filter by', $source );
		}

		$this->assertStringContainsString( 'cp-groups-archive--list--item', $archive );
		$this->assertLessThan( strpos( $single, 'Display::render_post' ), strpos( $single, 'All groups' ) );
	}

	public function test_stylesheet_is_scoped_and_built_from_the_cp_groups_sources() {
		$index = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/groups-display/index.scss' );
		$root  = dirname( __DIR__, 2 );

		$this->assertMatchesRegularExpression( '/\.cp-sync-display \{\s+@import "group\/list";\s+@import "group\/single";/', $index );
		$this->assertStringContainsString( '../frontend-shared/framework', $index );
		$this->assertFileExists( $root . '/src/groups-display/group/_list.scss' );
		$this->assertFileExists( $root . '/src/groups-display/group/_single.scss' );
		$this->assertStringContainsString( '.cp-group-item', (string) file_get_contents( $root . '/src/groups-display/group/_list.scss' ) );
	}
}
