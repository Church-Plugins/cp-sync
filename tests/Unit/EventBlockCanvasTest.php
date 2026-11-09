<?php
/**
 * Block themes keep the canvas. Classic themes keep the theme header.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class EventBlockCanvasTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_block_theme_shell_prints_template_parts_without_a_document() {
		Functions\when( 'wp_is_block_theme' )->justReturn( true );
		$parts = array();
		Functions\when( 'block_template_part' )->alias(
			static function ( $slug ) use ( &$parts ) {
				$parts[] = $slug;
			}
		);
		Functions\when( 'get_header' )->alias(
			static function () {
				throw new \RuntimeException( 'classic header used' );
			}
		);
		Functions\when( 'get_footer' )->alias(
			static function () {
				throw new \RuntimeException( 'classic footer used' );
			}
		);

		ob_start();
		include dirname( __DIR__, 2 ) . '/templates/events/shell-open.php';
		echo 'MAIN';
		include dirname( __DIR__, 2 ) . '/templates/events/shell-close.php';
		$html = ob_get_clean();

		$this->assertSame( array( 'header', 'footer' ), $parts );
		$this->assertStringContainsString( 'MAIN', $html );
		$this->assertStringNotContainsString( '<!DOCTYPE', $html );
		$this->assertStringNotContainsString( 'wp_head', $html );
		$this->assertStringNotContainsString( 'wp_footer', $html );
	}

	public function test_classic_theme_shell_uses_the_theme_header_and_footer() {
		Functions\when( 'wp_is_block_theme' )->justReturn( false );
		Functions\when( 'get_header' )->alias(
			static function () {
				echo 'THEME_HEADER';
			}
		);
		Functions\when( 'get_footer' )->alias(
			static function () {
				echo 'THEME_FOOTER';
			}
		);
		Functions\when( 'block_template_part' )->alias(
			static function () {
				throw new \RuntimeException( 'block part used' );
			}
		);

		ob_start();
		include dirname( __DIR__, 2 ) . '/templates/events/shell-open.php';
		include dirname( __DIR__, 2 ) . '/templates/events/shell-close.php';
		$html = ob_get_clean();

		$this->assertStringContainsString( 'THEME_HEADER', $html );
		$this->assertStringContainsString( 'THEME_FOOTER', $html );
	}

	public function test_block_theme_template_include_keeps_the_canvas() {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/Events/Display.php' );

		$this->assertIsString( $source );
		$this->assertStringContainsString( '$_wp_current_template_content = ob_get_clean();', $source );
		$this->assertMatchesRegularExpression(
			'/if\s*\(\s*function_exists\(\s*\'wp_is_block_theme\'\s*\)\s*&&\s*wp_is_block_theme\(\)\s*\)\s*\{.*return \$template;\s*\}/s',
			$source
		);
	}
}
