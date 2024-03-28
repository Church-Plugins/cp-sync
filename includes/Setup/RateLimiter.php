<?php
/**
 * Manages quickly fetching from an api while accounting for rate limiting.
 *
 * @package CP_Connect
 */

namespace CP_Connect\Setup;

use CP_Connect\Admin\Settings;

/**
 * RateLimiter class
 */
class RateLimiter extends \WP_Background_Process {

	/**
	 * The callback to process the data.
	 * If a rate limit is reached, the callback should return an array with the following keys:
	 * - 'wait' (int) The number of seconds to wait before trying again.
	 * 
	 * Otherwise, the callback should return true (successfully processed)
	 *
	 * @var callable
	 */
	protected $process_callback;

	/**
	 * Class constructor
	 *
	 * @param string   $action           The action name.
	 * @param callable $process_callback The callback to process the data.
	 */
	public function __construct( $action, $process_callback ) {
		$this->action           = $action;
		$this->process_callback = $process_callback;

		parent::__construct();
	}


	/**
	 * Processes a single item
	 *
	 * @param mixed $item The item to process.
	 */
	public function task( $item ) {
		$data = call_user_func( $this->process_callback, $item );

		if ( is_wp_error( $data ) ) {
			error_log( "Error processing item: {$data->get_error_message()}" );
			return false; // remove item from the WP_Background_Process queue
		} else if ( true === $data ) {
			return false; // success, remove item from queue
		} else {
			$seconds = $data['wait'];

			$this->pause();

			error_log( sprintf( 'Rate limit reached. Waiting %d seconds before trying again.', $seconds ) );

			sleep( $seconds );

			$this->resume();

			return $item;
		}
	}
}
