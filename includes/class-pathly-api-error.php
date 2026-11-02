<?php
/**
 * Error returned by the Pathly API or by the transport.
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

/**
 * An API call that did not succeed. The message never contains the API key.
 */
class Pathly_Api_Error extends Exception {

	/**
	 * HTTP status, 0 when the request never reached the API.
	 *
	 * @var int
	 */
	private $status;

	/**
	 * Machine-readable code sent by the API (for example `PLAN_LIMIT`), or an empty string.
	 *
	 * @var string
	 */
	private $api_code;

	/**
	 * Decoded body of the refusal, for the fields some refusals carry (for example `monitorId`).
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * Constructor.
	 *
	 * @param string $message  Human-readable message.
	 * @param int    $status   HTTP status, 0 for a network error.
	 * @param string $api_code Code sent by the API.
	 */
	public function __construct( $message, $status = 0, $api_code = '' ) {
		parent::__construct( $message );
		$this->status   = (int) $status;
		$this->api_code = (string) $api_code;
	}

	/**
	 * HTTP status.
	 *
	 * @return int
	 */
	public function status() {
		return $this->status;
	}

	/**
	 * Code sent by the API.
	 *
	 * @return string
	 */
	public function api_code() {
		return $this->api_code;
	}

	/**
	 * Keeps the decoded body of the refusal.
	 *
	 * @param array $data Decoded body.
	 * @return $this
	 */
	public function with_data( array $data ) {
		$this->data = $data;
		return $this;
	}

	/**
	 * One field of the refusal body, as a string.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	public function field( $name ) {
		return isset( $this->data[ $name ] ) && is_scalar( $this->data[ $name ] ) ? (string) $this->data[ $name ] : '';
	}

	/**
	 * True when the key was refused (absent, revoked or missing a scope).
	 *
	 * @return bool
	 */
	public function is_auth() {
		return 401 === $this->status || ( 403 === $this->status && 'PLAN_LIMIT' !== $this->api_code );
	}

	/**
	 * True when the route does not exist yet or the resource is gone.
	 *
	 * @return bool
	 */
	public function is_not_found() {
		return 404 === $this->status;
	}
}
