<?php
/**
 * WP_Error double for unit tests.
 *
 * @package updatebee
 */

/**
 * Minimal WP_Error stub for unit tests.
 */
class WP_Error {
	/**
	 * Error code.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Constructor.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 */
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	/**
	 * Get error code.
	 *
	 * @return string
	 */
	public function get_error_code() {
		return $this->code;
	}

	/**
	 * Get error message.
	 *
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}

	/**
	 * Get error codes.
	 *
	 * @return array<int, string>
	 */
	public function get_error_codes() {
		return '' === $this->code ? array() : array( $this->code );
	}

	/**
	 * Get error messages.
	 *
	 * @return array<int, string>
	 */
	public function get_error_messages() {
		return '' === $this->message ? array() : array( $this->message );
	}
}
