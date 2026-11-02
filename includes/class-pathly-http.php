<?php
/**
 * The only place where the plugin talks to the network.
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Minimal JSON client for https://api.pathlyhq.com.
 *
 * The host is fixed: no address typed by a user is ever requested from the
 * site. The key travels in the Authorization header only, and nothing here
 * writes to a log.
 */
class Pathly_Http {

	const BASE_URL = 'https://api.pathlyhq.com';

	const TIMEOUT = 10;

	/**
	 * API key (`sp_…`).
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Constructor.
	 *
	 * @param string $key API key.
	 */
	public function __construct( $key ) {
		$this->key = (string) $key;
	}

	/**
	 * User-Agent: plugin and WordPress versions, nothing else.
	 *
	 * @return string
	 */
	public static function user_agent() {
		return sprintf( 'Pathly-WordPress/%s WordPress/%s', PATHLY_VERSION, get_bloginfo( 'version' ) );
	}

	/**
	 * Sends a request and decodes the JSON answer.
	 *
	 * @param string     $method  HTTP method.
	 * @param string     $path    Path under /v1/, with its query string.
	 * @param array|null $body    JSON body.
	 * @param array      $headers Extra headers.
	 * @return array Decoded body (empty array for an empty answer).
	 * @throws Pathly_Api_Error When the path is not under /v1/, on a network error or a non-2xx status.
	 */
	public function request( $method, $path, $body = null, array $headers = array() ) {
		if ( 0 !== strpos( $path, '/v1/' ) ) {
			throw new Pathly_Api_Error( 'Invalid API path.' );
		}
		$args = array(
			'method'             => $method,
			'timeout'            => self::TIMEOUT,
			'redirection'        => 0,
			'reject_unsafe_urls' => true,
			'user-agent'         => self::user_agent(),
			'headers'            => array_merge(
				array(
					'Authorization' => 'Bearer ' . $this->key,
					'Accept'        => 'application/json',
				),
				$headers
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::BASE_URL . $path, $args );
		if ( is_wp_error( $response ) ) {
			throw new Pathly_Api_Error( esc_html__( 'Pathly could not be reached. Try again in a minute.', 'pathly' ) );
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$decoded = is_array( $decoded ) ? $decoded : array();
		if ( $status < 200 || $status >= 300 ) {
			$message = isset( $decoded['error'] ) && is_string( $decoded['error'] )
				? $decoded['error']
				/* translators: %d: HTTP status code. */
				: sprintf( __( 'Pathly answered with status %d.', 'pathly' ), $status );
			$code  = isset( $decoded['code'] ) && is_string( $decoded['code'] ) ? $decoded['code'] : '';
			$error = new Pathly_Api_Error( esc_html( $message ), (int) $status, esc_html( $code ) );
			$error->with_data( $decoded );
			throw $error;
		}
		return $decoded;
	}
}
