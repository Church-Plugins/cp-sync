<?php

namespace CP_Sync;

/**
 * Custom Exception class to write to log file while in debug mode
 *
 */
class Exception extends \ChurchPlugins\Exception {

	public function __construct( $message = null, $code = 0 ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $message ) {
			error_log( $message );
		}

		parent::__construct( $message, $code );
	}

}
