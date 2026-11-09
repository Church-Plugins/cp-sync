<?php
/**
 * The release zip must not ship the framework npm checkout.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ReleasePackageExcludeTest extends TestCase {

	public function test_build_script_removes_framework_node_modules_only() {
		$script = file_get_contents( dirname( __DIR__, 2 ) . '/bin/build-zip.sh' );

		$this->assertIsString( $script );
		$this->assertStringContainsString(
			'${STAGE}/cp-sync/includes/ChurchPlugins/node_modules',
			$script
		);
		$this->assertStringNotContainsString(
			'${STAGE}/cp-sync/includes/ChurchPlugins/assets',
			$script
		);
		$this->assertStringNotContainsString(
			'${STAGE}/cp-sync/build',
			$script
		);
	}
}
