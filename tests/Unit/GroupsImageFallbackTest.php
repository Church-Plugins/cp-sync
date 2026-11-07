<?php
/**
 * The groups image script must see failures that happened before it loaded.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class GroupsImageFallbackTest extends TestCase {

	public function test_groups_image_script_checks_images_that_already_failed() {
		$js = file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/groups-display.js' );

		$this->assertIsString( $js );
		$this->assertStringContainsString( "addEventListener(\n\t\t'error'", $js );
		$this->assertStringContainsString( '.cp-group-item--thumb--canvas', $js );
		$this->assertStringContainsString( 'querySelectorAll', $js );
		$this->assertStringContainsString( 'image.complete', $js );
		$this->assertStringContainsString( 'naturalWidth === 0', $js );
		$this->assertLessThan( strpos( $js, 'querySelectorAll' ), strpos( $js, 'addEventListener' ) );
	}
}
