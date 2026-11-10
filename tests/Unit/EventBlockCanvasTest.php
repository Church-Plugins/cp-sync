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

	/**
	 * @var string|null
	 */
	private $canvas_dir = null;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
		}
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_wp_current_template_content'], $GLOBALS['cp_sync_event_fixture'], $GLOBALS['wp_query'], $GLOBALS['cp_sync_canvas_post_type'], $GLOBALS['cp_sync_canvas_is_singular'] );
		if ( is_string( $this->canvas_dir ) && is_dir( $this->canvas_dir ) ) {
			foreach ( glob( $this->canvas_dir . '/*' ) as $file ) {
				unlink( $file );
			}
			rmdir( $this->canvas_dir );
			$this->canvas_dir = null;
		}
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_block_theme_shell_prints_template_parts_without_a_document() {
		require_once dirname( __DIR__, 2 ) . '/includes/Templates.php';
		Functions\when( 'wp_is_block_theme' )->justReturn( true );
		$parts = array();
		Functions\when( 'do_blocks' )->alias(
			static function ( $blocks ) use ( &$parts ) {
				$parts[] = $blocks;
				return '';
			}
		);
		Functions\when( 'block_template_part' )->alias(
			static function () {
				throw new \RuntimeException( 'part printed without its shortcode pass' );
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

		$this->assertSame(
			array(
				'<!-- wp:template-part {"slug":"header","tagName":"header"} /-->',
				'<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->',
			),
			$parts
		);
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
		Functions\when( 'do_blocks' )->alias(
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

	public function test_template_include_is_registered_at_priority_9() {
		Functions\when( 'add_action' )->justReturn( true );
		$priority = null;
		Functions\when( 'add_filter' )->alias(
			static function ( $hook, $callback, $registered = 10 ) use ( &$priority ) {
				if ( 'template_include' === $hook ) {
					$priority = $registered;
				}
				return true;
			}
		);

		\CP_Sync\Events\Display::boot();

		$this->assertSame( 9, $priority );
	}

	public function test_block_branch_captures_protected_markup_and_keeps_loader_scope() {
		$paths   = $this->canvas_paths();
		$calls   = array();
		$fixture = $paths['fixture'];

		$this->stub_event_archive( $fixture );
		Functions\when( 'wp_is_block_theme' )->justReturn( true );
		Functions\when( 'locate_block_template' )->alias(
			static function ( $template, $type, $templates ) use ( &$calls, $paths ) {
				$calls[] = array( $template, $type, $templates );
				$GLOBALS['_wp_current_template_content'] = 'THEME_BLOCKS';
				return $paths['canvas'];
			}
		);

		$returned = \CP_Sync\Events\Display::template_include( $paths['theme_php'] );

		$this->assertSame( array( array( '', 'index', array( 'index' ) ) ), $calls );
		$this->assertSame( $paths['canvas'], $returned );
		$this->assertSame( $this->protected_fixture( $fixture ), $GLOBALS['_wp_current_template_content'] );
		$this->assertStringContainsString( '&#91;gallery id="1"]', $GLOBALS['_wp_current_template_content'] );
		$this->assertStringNotContainsString( '[gallery', $GLOBALS['_wp_current_template_content'] );
		$this->assertStringContainsString( 'class="cp-group-url"', $GLOBALS['_wp_current_template_content'] );
		$this->assertStringContainsString( 'QUERY_OK', $GLOBALS['_wp_current_template_content'] );
		$this->assertStringNotContainsString( 'THEME_BLOCKS', $GLOBALS['_wp_current_template_content'] );
	}

	public function test_existing_canvas_is_not_located_again() {
		$paths = $this->canvas_paths();

		$this->stub_event_archive( $paths['fixture'] );
		Functions\when( 'wp_is_block_theme' )->justReturn( true );
		Functions\when( 'locate_block_template' )->alias(
			static function () {
				throw new \RuntimeException( 'canvas was located again' );
			}
		);

		$returned = \CP_Sync\Events\Display::template_include( $paths['canvas'] );

		$this->assertSame( $paths['canvas'], $returned );
		$this->assertStringContainsString( 'QUERY_OK', $GLOBALS['_wp_current_template_content'] );
		$this->assertStringContainsString( '&#91;gallery', $GLOBALS['_wp_current_template_content'] );
	}

	public function test_absent_canvas_falls_back_without_capture() {
		$paths = $this->canvas_paths();
		$GLOBALS['_wp_current_template_content'] = 'UNTOUCHED';

		$this->stub_event_archive( $paths['fixture'] );
		Functions\when( 'wp_is_block_theme' )->justReturn( true );
		Functions\when( 'locate_block_template' )->justReturn( $paths['theme_php'] );

		$returned = \CP_Sync\Events\Display::template_include( $paths['theme_php'] );

		$this->assertSame( $paths['fixture'], $returned );
		$this->assertSame( 'UNTOUCHED', $GLOBALS['_wp_current_template_content'] );
	}

	public function test_classic_theme_returns_the_plugin_template_without_capture() {
		$paths = $this->canvas_paths();
		$GLOBALS['_wp_current_template_content'] = 'UNTOUCHED';

		$this->stub_event_archive( $paths['fixture'] );
		Functions\when( 'wp_is_block_theme' )->justReturn( false );
		Functions\when( 'locate_block_template' )->alias(
			static function () {
				throw new \RuntimeException( 'classic theme looked up a canvas' );
			}
		);

		$returned = \CP_Sync\Events\Display::template_include( $paths['theme_php'] );

		$this->assertSame( $paths['fixture'], $returned );
		$this->assertSame( 'UNTOUCHED', $GLOBALS['_wp_current_template_content'] );
	}

	/**
	 * @return array{dir: string, canvas: string, theme_php: string, fixture: string}
	 */
	private function canvas_paths() {
		if ( null !== $this->canvas_dir ) {
			return array(
				'dir'       => $this->canvas_dir,
				'canvas'    => $this->canvas_dir . '/template-canvas.php',
				'theme_php' => $this->canvas_dir . '/archive.php',
				'fixture'   => $this->canvas_dir . '/event-fixture.php',
			);
		}

		$dir = sys_get_temp_dir() . '/cp-sync-canvas-' . uniqid( '', true );
		mkdir( $dir );
		file_put_contents( $dir . '/template-canvas.php', "<?php echo 'CANVAS';\n" );
		file_put_contents( $dir . '/archive.php', "<?php echo 'THEME_PHP';\n" );
		file_put_contents(
			$dir . '/event-fixture.php',
			"<?php\necho \"[gallery id=\\\"1\\\"]\\n\";\necho \"https://example.com/clip.mp4\\n\";\necho \$wp_query->get( 'marker' );\n\$template = 'stolen';\n"
		);
		$this->canvas_dir = $dir;

		return array(
			'dir'       => $dir,
			'canvas'    => $dir . '/template-canvas.php',
			'theme_php' => $dir . '/archive.php',
			'fixture'   => $dir . '/event-fixture.php',
		);
	}

	/**
	 * @param string $fixture Fixture template.
	 * @return void
	 */
	private function stub_event_archive( $fixture ) {
		require_once dirname( __DIR__ ) . '/Support/event-template-runtime.php';
		require_once dirname( __DIR__ ) . '/Support/block-canvas-loader.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Templates.php';

		$GLOBALS['cp_sync_event_fixture']    = $fixture;
		$GLOBALS['cp_sync_canvas_post_type'] = 'cp_event';
		$GLOBALS['wp_query']                 = new class() {
			/**
			 * @var array<string, string>
			 */
			public $query_vars = array();

			/**
			 * @param string $key Query var.
			 * @return string
			 */
			public function get( $key ) {
				unset( $key );
				return 'QUERY_OK';
			}
		};

		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value = null ) {
				if ( is_string( $tag ) && 0 === strpos( $tag, 'cp_sync_template_' ) && 'cp_sync_template_paths' !== $tag ) {
					return $GLOBALS['cp_sync_event_fixture'];
				}
				return $value;
			}
		);
		Functions\when( 'get_option' )->alias(
			static function ( $key ) {
				if ( 'cp_sync_pco_settings' === $key ) {
					return array(
						'connect' => array(
							'sync_events' => '1',
						),
					);
				}
				return array();
			}
		);
	}

	/**
	 * @param string $fixture Fixture template.
	 * @return string
	 */
	private function protected_fixture( $fixture ) {
		ob_start();
		load_template( $fixture, false );
		$raw = ob_get_clean();

		return \CP_Sync\Groups\Display::protect_block_template_html( $raw );
	}

}
