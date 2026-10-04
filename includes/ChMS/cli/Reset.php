<?php
/**
 * WP-CLI command for resetting CP-Sync install data.
 *
 * Thin wrapper over \CP_Sync\Setup\Reset, the single supported reset service.
 * Replaces the earlier hand-rolled, partly-broken helpers ( cp-sync ccb
 * clear-store, cp-sync test clear-queue ), which now delegate here.
 *
 * @package CP_Sync
 * @since 1.0.0
 */

namespace CP_Sync\ChMS\cli;

use CP_Sync\Setup\Reset;

// Make the `cp-sync reset` command available to WP-CLI.
if ( defined( '\WP_CLI' ) && \WP_CLI ) {
	\WP_CLI::add_command( 'cp-sync reset', '\CP_Sync\ChMS\cli\Reset_CLI' );
}

/**
 * Reset CP-Sync install data.
 */
class Reset_CLI {

	/**
	 * Reset CP-Sync install data at a chosen level.
	 *
	 * ## OPTIONS
	 *
	 * [--level=<level>]
	 * : How much to reset.
	 * ---
	 * default: state
	 * options:
	 *   - queue
	 *   - state
	 *   - content
	 *   - connection
	 *   - all
	 * ---
	 *
	 * [--yes]
	 * : Answer yes to the confirmation prompt ( required for content, connection and all ).
	 *
	 * ## EXAMPLES
	 *
	 *     # Unstick a stalled sync.
	 *     wp cp-sync reset --level=queue
	 *
	 *     # Forget what has synced so the next pull re-imports everything.
	 *     wp cp-sync reset --level=state
	 *
	 *     # Full uninstall-equivalent wipe.
	 *     wp cp-sync reset --level=all --yes
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args ( unused ).
	 * @param array $assoc_args Associative args.
	 */
	public function __invoke( $args, $assoc_args ) {
		$level = isset( $assoc_args['level'] ) ? $assoc_args['level'] : 'state';

		if ( ! Reset::is_valid_level( $level ) ) {
			\WP_CLI::error( sprintf(
				'Invalid level "%s". Valid levels: %s',
				$level,
				implode( ', ', Reset::levels() )
			) );
		}

		// Destructive levels remove content, credentials or settings — confirm first.
		if ( in_array( $level, [ 'content', 'connection', 'all' ], true ) ) {
			\WP_CLI::confirm(
				sprintf( 'This will permanently remove data ( level: %s ). Continue?', $level ),
				$assoc_args
			);
		}

		$reset   = new Reset();
		$summary = $reset->run( $level );

		\WP_CLI::success( sprintf( 'Reset complete ( level: %s ).', $level ) );
		\WP_CLI::line( wp_json_encode( $summary, JSON_PRETTY_PRINT ) );
	}
}
