<?php
/**
 * Tests for CP_Sync\Setup\Reset — the pure-logic seams of the reset service.
 *
 * The SQL / WordPress-touching methods ( clear_queue, reset_*, delete_options_like,
 * etc. ) are deliberately thin and are not booted here. What is pinned instead is
 * everything a caller ( REST endpoint, WP-CLI command, admin UI ) relies on before
 * any destructive work happens: the level list, the confirm guard, the level ->
 * method dispatch map, and the sync-state option-name prefixes.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use CP_Sync\Setup\Reset;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CP_Sync\Setup\Reset
 */
class ResetTest extends TestCase {

	/* ------------------------------------------------------------------ levels() */

	public function test_levels_are_the_five_documented_levels_in_order() {
		$this->assertSame(
			[ 'queue', 'state', 'content', 'connection', 'all' ],
			Reset::levels()
		);
	}

	public function test_levels_constant_matches_accessor() {
		$this->assertSame( Reset::LEVELS, Reset::levels() );
	}

	/* ---------------------------------------------------------- is_valid_level() */

	/**
	 * @dataProvider valid_levels
	 */
	public function test_is_valid_level_accepts_known_levels( $level ) {
		$this->assertTrue( Reset::is_valid_level( $level ) );
	}

	public function valid_levels() {
		return [ [ 'queue' ], [ 'state' ], [ 'content' ], [ 'connection' ], [ 'all' ] ];
	}

	/**
	 * @dataProvider invalid_levels
	 */
	public function test_is_valid_level_rejects_unknown_values( $level ) {
		$this->assertFalse( Reset::is_valid_level( $level ) );
	}

	public function invalid_levels() {
		return [
			[ '' ],
			[ 'Queue' ],          // case-sensitive
			[ 'everything' ],
			[ ' all ' ],
			[ null ],
			[ 0 ],
			[ [] ],
			[ 'state; DROP' ],
		];
	}

	/* --------------------------------------------------------- confirm_matches() */

	public function test_confirm_matches_requires_exact_string_match() {
		$this->assertTrue( Reset::confirm_matches( 'all', 'all' ) );
		$this->assertTrue( Reset::confirm_matches( 'queue', 'queue' ) );
	}

	public function test_confirm_matches_rejects_mismatch() {
		$this->assertFalse( Reset::confirm_matches( 'all', 'queue' ) );
		$this->assertFalse( Reset::confirm_matches( 'all', 'ALL' ) );
		$this->assertFalse( Reset::confirm_matches( 'all', ' all' ) );
	}

	public function test_confirm_matches_rejects_non_string_confirm() {
		$this->assertFalse( Reset::confirm_matches( 'all', null ) );
		$this->assertFalse( Reset::confirm_matches( 'all', true ) );
		$this->assertFalse( Reset::confirm_matches( 'all', [ 'all' ] ) );
	}

	public function test_confirm_matches_is_strictly_typed() {
		// '0' vs 0 must not loosely match.
		$this->assertFalse( Reset::confirm_matches( 0, '0' ) );
	}

	/* ------------------------------------------------------------ level_method() */

	public function test_level_method_maps_each_level_to_its_method() {
		$this->assertSame( 'clear_queue', Reset::level_method( 'queue' ) );
		$this->assertSame( 'reset_sync_state', Reset::level_method( 'state' ) );
		$this->assertSame( 'reset_content', Reset::level_method( 'content' ) );
		$this->assertSame( 'reset_connection', Reset::level_method( 'connection' ) );
		$this->assertSame( 'reset_all', Reset::level_method( 'all' ) );
	}

	public function test_level_method_returns_null_for_unknown_level() {
		$this->assertNull( Reset::level_method( 'bogus' ) );
		$this->assertNull( Reset::level_method( '' ) );
		$this->assertNull( Reset::level_method( null ) );
	}

	public function test_every_valid_level_maps_to_a_real_public_method() {
		foreach ( Reset::levels() as $level ) {
			$method = Reset::level_method( $level );
			$this->assertNotNull( $method, "Level {$level} has no method" );
			$this->assertTrue(
				method_exists( Reset::class, $method ),
				"Reset::{$method}() does not exist for level {$level}"
			);
		}
	}

	/* -------------------------------------------------- state_option_prefixes() */

	public function test_state_option_prefixes_cover_stores_taxonomies_and_corrupt_batches() {
		$this->assertSame(
			[ 'cp_sync_store_', 'cp_sync_taxonomies_', 'cp_sync_corrupt_batch_' ],
			Reset::state_option_prefixes()
		);
	}

	public function test_state_option_prefixes_match_the_real_option_names() {
		$prefixes = Reset::state_option_prefixes();

		// Representative real option names from Integration.php / report_corrupt_batch().
		$examples = [
			'cp_sync_store_groups',
			'cp_sync_store_groups_cp_group_type',
			'cp_sync_store_events',
			'cp_sync_taxonomies_pco',
			'cp_sync_taxonomies_ccb',
			'cp_sync_corrupt_batch_pull_groups',
			'cp_sync_corrupt_batch_pull_events',
		];

		foreach ( $examples as $name ) {
			$matched = false;
			foreach ( $prefixes as $prefix ) {
				if ( 0 === strpos( $name, $prefix ) ) {
					$matched = true;
					break;
				}
			}
			$this->assertTrue( $matched, "No prefix matches option {$name}" );
		}
	}

	public function test_state_option_prefixes_do_not_match_settings_or_credentials() {
		$prefixes = Reset::state_option_prefixes();

		// These must survive a sync-state reset ( they are connection/global state ).
		$protected = [
			'cp_sync_settings',
			'cp_sync_pco_settings',
			'cp_sync_ccb_settings',
			'cp_settings_message',
		];

		foreach ( $protected as $name ) {
			foreach ( $prefixes as $prefix ) {
				$this->assertNotSame(
					0,
					strpos( $name, $prefix ),
					"Prefix {$prefix} would wrongly match {$name}"
				);
			}
		}
	}

	/* ---------------------------------------------------------------- run() null */

	public function test_run_returns_null_for_invalid_level_without_touching_wordpress() {
		// An unknown level short-circuits before any WP call, so this is safe to run
		// without booting WordPress.
		$this->assertNull( ( new Reset() )->run( 'nope' ) );
	}
}
