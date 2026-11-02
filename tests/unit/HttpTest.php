<?php
/**
 * Tests of Pathly_Http and Pathly_Api_Error.
 *
 * @package Pathly
 */

namespace Pathly\Tests;

use Brain\Monkey\Functions;

class HttpTest extends TestCase {

	/**
	 * Arguments of the last wp_remote_request call.
	 *
	 * @var array
	 */
	private $sent = array();

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_bloginfo' )->justReturn( '6.6.2' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static function ( $r ) {
				return $r['response']['code'];
			}
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static function ( $r ) {
				return $r['body'];
			}
		);
	}

	private function answer( $code, $body ) {
		$sent = &$this->sent;
		Functions\when( 'wp_remote_request' )->alias(
			static function ( $url, $args ) use ( $code, $body, &$sent ) {
				$sent = array(
					'url'  => $url,
					'args' => $args,
				);
				return array(
					'response' => array( 'code' => $code ),
					'body'     => $body,
				);
			}
		);
	}

	public function test_get_sends_key_and_versions_only_to_the_pathly_api() {
		$this->answer( 200, '{"items":[]}' );
		$out = ( new \Pathly_Http( 'sp_key' ) )->request( 'GET', '/v1/scenarios?limit=1' );

		$this->assertSame( array( 'items' => array() ), $out );
		$this->assertSame( 'https://api.pathlyhq.com/v1/scenarios?limit=1', $this->sent['url'] );
		$this->assertSame( 'Bearer sp_key', $this->sent['args']['headers']['Authorization'] );
		$this->assertSame( 'Pathly-WordPress/0.1.0 WordPress/6.6.2', $this->sent['args']['user-agent'] );
		$this->assertSame( 0, $this->sent['args']['redirection'] );
		$this->assertTrue( $this->sent['args']['reject_unsafe_urls'] );
		$this->assertArrayNotHasKey( 'body', $this->sent['args'] );
	}

	public function test_post_encodes_the_body_and_merges_headers() {
		$this->answer( 201, '{"id":"abc"}' );
		$out = ( new \Pathly_Http( 'sp_key' ) )->request( 'POST', '/v1/scenarios', array( 'name' => 'x' ), array( 'Idempotency-Key' => 'k-123456' ) );

		$this->assertSame( array( 'id' => 'abc' ), $out );
		$this->assertSame( '{"name":"x"}', $this->sent['args']['body'] );
		$this->assertSame( 'application/json', $this->sent['args']['headers']['Content-Type'] );
		$this->assertSame( 'k-123456', $this->sent['args']['headers']['Idempotency-Key'] );
	}

	public function test_empty_or_invalid_body_becomes_an_empty_array() {
		$this->answer( 204, '' );
		$this->assertSame( array(), ( new \Pathly_Http( 'k' ) )->request( 'POST', '/v1/scenarios/1/run', array() ) );
	}

	public function test_refuses_a_path_outside_v1() {
		Functions\expect( 'wp_remote_request' )->never();
		$this->expectException( \Pathly_Api_Error::class );
		( new \Pathly_Http( 'k' ) )->request( 'GET', 'https://evil.example/v1/x' );
	}

	public function test_network_error() {
		Functions\when( 'wp_remote_request' )->justReturn( new \WP_Error() );
		try {
			( new \Pathly_Http( 'k' ) )->request( 'GET', '/v1/account' );
			$this->fail( 'No exception' );
		} catch ( \Pathly_Api_Error $e ) {
			$this->assertSame( 0, $e->status() );
			$this->assertStringContainsString( 'could not be reached', $e->getMessage() );
		}
	}

	public function test_api_error_keeps_message_and_code() {
		$this->answer( 403, '{"error":"Limite de l\'offre","code":"PLAN_LIMIT"}' );
		try {
			( new \Pathly_Http( 'k' ) )->request( 'GET', '/v1/account' );
			$this->fail( 'No exception' );
		} catch ( \Pathly_Api_Error $e ) {
			$this->assertSame( 403, $e->status() );
			$this->assertSame( 'PLAN_LIMIT', $e->api_code() );
			$this->assertSame( 'Limite de l&#039;offre', $e->getMessage() );
			$this->assertFalse( $e->is_auth() );
			$this->assertFalse( $e->is_not_found() );
			$this->assertSame( 'PLAN_LIMIT', $e->field( 'code' ) );
			$this->assertSame( '', $e->field( 'missing' ) );
		}
	}

	public function test_api_error_without_body() {
		$this->answer( 500, 'oops' );
		try {
			( new \Pathly_Http( 'k' ) )->request( 'GET', '/v1/account' );
			$this->fail( 'No exception' );
		} catch ( \Pathly_Api_Error $e ) {
			$this->assertSame( 'Pathly answered with status 500.', $e->getMessage() );
			$this->assertSame( '', $e->api_code() );
		}
	}

	public function test_status_below_200_is_an_error() {
		$this->answer( 101, '{}' );
		$this->expectException( \Pathly_Api_Error::class );
		( new \Pathly_Http( 'k' ) )->request( 'GET', '/v1/account' );
	}

	public function test_error_kinds() {
		$this->assertTrue( ( new \Pathly_Api_Error( 'x', 401 ) )->is_auth() );
		$this->assertTrue( ( new \Pathly_Api_Error( 'x', 403 ) )->is_auth() );
		$this->assertFalse( ( new \Pathly_Api_Error( 'x', 500 ) )->is_auth() );
		$this->assertTrue( ( new \Pathly_Api_Error( 'x', 404 ) )->is_not_found() );
	}
}
