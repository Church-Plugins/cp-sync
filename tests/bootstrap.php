<?php
/**
 * PHPUnit bootstrap for fast, WordPress-free unit tests.
 *
 * These tests do NOT boot WordPress. They exercise pure logic directly and use
 * Brain Monkey (already installed) to stub any WordPress functions a unit under
 * test happens to call. This keeps the suite sub-second and runnable anywhere,
 * which is the whole point — see ai/1.0-release-plan.md (Phase 0).
 *
 * When you need to test a method that calls WP functions, in your test case:
 *
 *   use Brain\Monkey;
 *
 *   protected function setUp(): void {
 *       parent::setUp();
 *       Monkey\setUp();
 *   }
 *   protected function tearDown(): void {
 *       Monkey\tearDown();
 *       parent::tearDown();
 *   }
 *
 * ...then stub with Monkey\Functions\when('get_option')->justReturn( ... ).
 *
 * @package CP_Sync
 */

// Note on deprecations: a transitive dep (illuminate/support) emits PHP 8.4
// "implicitly nullable parameter" deprecations at autoload time that do not fire
// on the plugin's PHP 7.4 target. They are silenced at process start via
// `-d error_reporting=22527` in composer's "test" script (it must be set before
// phpunit loads the autoloader, so it can't be done from here). Run the suite
// with `composer test`, not a bare `vendor/bin/phpunit`, to get clean output.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
