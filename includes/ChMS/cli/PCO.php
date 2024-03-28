<?php

namespace CP_Sync\ChMS\cli;

use CP_Sync\ChMS\PCO as PCO;

// Make the `cp` command available to WP-CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'cp-pco', '\CP_Sync\ChMS\cli\PCO_CLI' );
}

/**
 * Command line scripts for Church Plugins PCO integration
 *
 * @author costmo
 */
class PCO_CLI {


	public function __construct() {
	}

	/**
	 * Initial data pull
	 *
	 * @param array $args				Ignored
	 * @param array $assoc_args			Ignored
	 * @return void
	 * @author costmo
	 */
	public function setup( $args, $assoc_args ) {

		$pco = PCO::get_instance();
		$pco->setup_taxonomies( true );
		\WP_CLI::success( "DONE" );
	}


	/**
	 * Initial data pull
	 *
	 * @param array $args				Ignored
	 * @param array $assoc_args			Ignored
	 * @return void
	 * @author costmo
	 */
	public function test_it( $args, $assoc_args ) {

		$pco = PCO::get_instance();

		$items = $pco->pull_events( [], true );
		// $items = $pco->pull_groups( [] );

		\WP_CLI::log( var_export( $items, true ) );
		\WP_CLI::success( "DONE" );
	}
} 