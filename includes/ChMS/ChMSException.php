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
	 * Caller-supplied code. Numeric codes are also the Exception code ( so a
	 * 429 can be retried ). A string such as `pco_fetch_error` is kept here;
	 * PHP 8 rejects a string as Exception::$code.
	 *
	 * @var int|string
	 */
	protected $error_code = 0;

	/**
	 * Class constructor
	 *
	 * @param int|string $code    Exception code, or a string error code.
	 * @param mixed      $message Exception message. Arrays are JSON-encoded.
	 * @param mixed      $data    Additional data.
	 */
	public function __construct( $code = 0, $message = '', $data = null ) {
		$numeric = 0;

		if ( is_int( $code ) ) {
			$numeric           = $code;
			$this->error_code = $code;
		} elseif ( is_string( $code ) && is_numeric( $code ) ) {
			$numeric           = (int) $code;
			$this->error_code = $numeric;
		} elseif ( is_string( $code ) && '' !== $code ) {
			$this->error_code = $code;
		} else {
			$this->error_code = 0;
		}

		if ( is_array( $message ) || is_object( $message ) ) {
			$encoded = json_encode( $message );
			$message = is_string( $encoded ) ? $encoded : '';
		} elseif ( ! is_string( $message ) ) {
			$message = is_scalar( $message ) ? (string) $message : '';
		}

		parent::__construct( $message, $numeric );

		$this->data = $data;
	}

	/**
	 * Error code as supplied to the constructor.
	 *
	 * String codes stay strings. Numeric codes are ints, matching getCode().
	 *
	 * @return int|string
	 */
	public function getErrorCode() {
		return $this->error_code;
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
