<?php
/**
 * Group pages on block themes use the same canvas capture as event pages.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CP_Sync\Groups\Lite;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @covers \CP_Sync\Groups\Lite::template_include
 */
class GroupsBlockCanvasTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @var string
	 */
	private $canvas = '';

	/**
	 * @var string
	 */
	private $theme_php = '';

	/**
	 * @var string
	 */
	private $fixture = '';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		require_once dirname( __DIR__ ) . '/Support/event-template-runtime.php';
		require_once dirname( __DIR__ ) . '/Support/block-canvas-loader.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Templates.php';

		$this->dir       = sys_get_temp_dir() . '/cp-sync-group-canvas-' . uniqid( '', true );
		$this->canvas    = $this->dir . '/template-canvas.php';
		$this->theme_php = $this->dir . '/archive.php';
		$this->fixture   = $this->dir . '/group-fixture.php';

		mkdir( $this->dir );
		file_put_contents( $this->canvas, "<?php echo 'CANVAS';\n" );
		file_put_contents( $this->theme_php, "<?php echo 'THEME_PHP';\n" );
		file_put_contents(
			$this->fixture,
			"<?php\necho '<input value=\"[probe]\">';\necho \$wp_query->get( 'marker' );\n\$template = 'stolen';\n"
		);

		$GLOBALS['wp_query'] = new class() {
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

		$fixture = $this->fixture;

		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'is_singular' )->justReturn( false );
		Functions\when( 'is_post_type_archive' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value = null ) use ( $fixture ) {
				if ( is_string( $tag ) && 0 === strpos( $tag, 'cp_sync_template_' ) && 'cp_sync_template_paths' !== $tag ) {
					return $fixture;
				}
				return $value;
			}
		);

		$this->set_registered( true );
	}

	protected function tearDown(): void {
		$this->set_registered( false );
		unset( $GLOBALS['_wp_current_template_content'], $GLOBALS['wp_query'] );

		foreach ( glob( $this->dir . '/*' ) as $file ) {
			unlink( $file );
		}
		rmdir( $this->dir );

		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_capture_is_protected_and_keeps_loader_scope() {
		Functions\when( 'wp_is_block_theme' )->justReturn( true );
		Functions\when( 'locate_block_template' )->alias(
			static function () {
				throw new \RuntimeException( 'canvas was located again' );
			}
		);

		$returned = Lite::get_instance()->template_include( $this->canvas );

		$this->assertSame( $this->canvas, $returned );
		$this->assertSame( '<input value="&#91;probe]">QUERY_OK', $GLOBALS['_wp_current_template_content'] );
	}

	public function test_php_template_on_a_block_theme_still_gets_the_canvas() {
		$canvas = $this->canvas;

		Functions\when( 'wp_is_block_theme' )->justReturn( true );
		Functions\when( 'locate_block_template' )->alias(
			static function () use ( $canvas ) {
				$GLOBALS['_wp_current_template_content'] = 'THEME_BLOCKS';
				return $canvas;
			}
		);

		$returned = Lite::get_instance()->template_include( $this->theme_php );

		$this->assertSame( $this->canvas, $returned );
		$this->assertStringContainsString( 'QUERY_OK', $GLOBALS['_wp_current_template_content'] );
		$this->assertStringNotContainsString( 'THEME_BLOCKS', $GLOBALS['_wp_current_template_content'] );
	}

	public function test_absent_canvas_loads_the_group_template_directly() {
		$GLOBALS['_wp_current_template_content'] = 'UNTOUCHED';

		Functions\when( 'wp_is_block_theme' )->justReturn( true );
		Functions\when( 'locate_block_template' )->justReturn( $this->theme_php );

		$returned = Lite::get_instance()->template_include( $this->theme_php );

		$this->assertSame( $this->fixture, $returned );
		$this->assertSame( 'UNTOUCHED', $GLOBALS['_wp_current_template_content'] );
	}

	public function test_classic_theme_loads_the_group_template_without_capture() {
		$GLOBALS['_wp_current_template_content'] = 'UNTOUCHED';

		Functions\when( 'wp_is_block_theme' )->justReturn( false );

		$returned = Lite::get_instance()->template_include( $this->theme_php );

		$this->assertSame( $this->fixture, $returned );
		$this->assertSame( 'UNTOUCHED', $GLOBALS['_wp_current_template_content'] );
	}

	/**
	 * @dataProvider group_templates
	 *
	 * @param string $template Template file under templates/groups.
	 */
	public function test_group_template_prints_parts_through_the_template_part_block( $template ) {
		$parts = array();

		Functions\when( 'wp_is_block_theme' )->justReturn( true );
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
		Functions\when( 'is_tax' )->justReturn( false );
		Functions\when( 'post_type_archive_title' )->justReturn( 'Groups' );
		Functions\when( 'get_the_archive_description' )->justReturn( '' );
		Functions\when( 'get_post_type_archive_link' )->justReturn( '' );
		Functions\when( 'wp_strip_all_tags' )->returnArg( 1 );
		Functions\when( 'have_posts' )->justReturn( false );
		Functions\when( 'the_posts_pagination' )->justReturn( null );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_reset_postdata' )->justReturn( null );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_html_e' )->justReturn( null );

		ob_start();
		include dirname( __DIR__, 2 ) . '/templates/groups/' . $template;
		ob_end_clean();

		$this->assertSame(
			array(
				'<!-- wp:template-part {"slug":"header","tagName":"header"} /-->',
				'<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->',
			),
			$parts
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function group_templates() {
		return array(
			'archive' => array( 'archive.php' ),
			'single'  => array( 'single.php' ),
		);
	}

	/**
	 * @param bool $registered Whether this request registered cp_group.
	 * @return void
	 */
	private function set_registered( $registered ) {
		$property = new ReflectionProperty( Lite::class, 'registered' );
		$property->setAccessible( true );
		$property->setValue( null, $registered );
	}
}
