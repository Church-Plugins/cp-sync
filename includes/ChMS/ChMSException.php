<?php
/**
 * ChMS specific exception class
 *
 * @package CP_Sync
 */

namespace CP_Sync\ChMS;

/**
 * ChMS specific exception class
 */
class ChMSException extends \Exception {
	/**
	 * Additional data
	 *
	 * @var mixed
	 */
	protected $data;

	/**
	 * Class constructor
	 *
	 * @param int    $code Exception code.
	 * @param string $message Exception message.
	 * @param mixed  $data Additional data.
	 */
	public function __construct( $code = 0, $message = '', $data = null ) {
		parent::__construct( $message, $code );

		$this->data = $data;
	}

	/**
	 * Get the additional data
	 *
	 * @return mixed The additional data.
	 */
	public function getData() {
		return $this->data;
	}
}
