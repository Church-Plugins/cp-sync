<?php
/**
 * Characterization tests for CP_Sync\Setup\Convenience string helpers.
 *
 * These pin the *current* behavior of slugify()/de_slugify() — including the
 * bits that are slightly surprising (no collapsing of repeated separators, no
 * trimming, byte-wise regex) — so a future refactor can't change them silently.
 * They are pure functions with no WordPress dependency, hence no Brain Monkey.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\Setup\Convenience;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Setup\Convenience::slugify
 * @covers \CP_Sync\Setup\Convenience::de_slugify
 */
class ConvenienceTest extends TestCase {

	public function test_slugify_lowercases_and_replaces_spaces() {
		$this->assertSame( 'hello-world', Convenience::slugify( 'Hello World' ) );
	}

	public function test_slugify_strips_tags_before_slugifying() {
		$this->assertSame( 'bold', Convenience::slugify( '<b>Bold</b>' ) );
	}

	public function test_slugify_does_not_collapse_repeated_separators() {
		// Documents current behavior: two spaces -> two dashes, not one.
		$this->assertSame( 'a--b', Convenience::slugify( 'A  B' ) );
	}

	public function test_slugify_does_not_trim_trailing_separators() {
		// Documents current behavior: a trailing invalid char becomes a trailing dash.
		$this->assertSame( 'test-', Convenience::slugify( 'Test!' ) );
	}

	public function test_slugify_preserves_existing_dashes_and_digits() {
		$this->assertSame( 'already-slug-9', Convenience::slugify( 'Already-Slug-9' ) );
	}

	public function test_slugify_honors_a_valid_single_char_replacement() {
		$this->assertSame( 'hello_world', Convenience::slugify( 'Hello World', '_' ) );
	}

	public function test_slugify_falls_back_to_dash_for_multi_char_replacement() {
		$this->assertSame( 'a-b', Convenience::slugify( 'A B', '__' ) );
	}

	public function test_slugify_falls_back_to_dash_for_empty_replacement() {
		$this->assertSame( 'a-b', Convenience::slugify( 'A B', '' ) );
	}

	public function test_de_slugify_replaces_dashes_and_underscores_with_spaces() {
		$this->assertSame( 'hello world again', Convenience::de_slugify( 'hello-world_again' ) );
	}

	public function test_de_slugify_honors_a_valid_single_char_replacement() {
		// Underscores become dashes; existing dashes stay dashes.
		$this->assertSame( 'hello-world', Convenience::de_slugify( 'hello_world', '-' ) );
	}

	public function test_de_slugify_falls_back_to_space_for_multi_char_replacement() {
		$this->assertSame( 'a b', Convenience::de_slugify( 'a-b', '__' ) );
	}
}
