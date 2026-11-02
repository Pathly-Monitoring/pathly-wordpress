<?php
/**
 * Tests of Pathly_Api: each method calls the documented route.
 *
 * @package Pathly
 */

namespace Pathly\Tests;

class ApiTest extends TestCase {

	/**
	 * Expects one request on the transport and returns the API.
	 *
	 * @param string     $method  Method.
	 * @param string     $path    Path.
	 * @param array|null $body    Body.
	 * @param array      $headers Headers.
	 * @return \Pathly_Api
	 */
	private function expecting( $method, $path, $body = null, array $headers = array() ) {
		$http = \Mockery::mock( \Pathly_Http::class );
		$args = array( $method, $path, $body, $headers );
		if ( array() === $headers ) {
			array_pop( $args );
			if ( null === $body ) {
				array_pop( $args );
			}
		}
		$http->shouldReceive( 'request' )->once()->withArgs( $args )->andReturn( array( 'ok' => true ) );
		return new \Pathly_Api( $http );
	}

	public function test_account() {
		$this->assertSame( array( 'ok' => true ), $this->expecting( 'GET', '/v1/account' )->account() );
	}

	public function test_list_scenarios_first_page_and_next() {
		$this->expecting( 'GET', '/v1/scenarios?limit=100' )->list_scenarios();
		$this->expecting( 'GET', '/v1/scenarios?limit=100&cursor=abc' )->list_scenarios( 'abc' );
		$this->addToAssertionCount( 1 );
	}

	public function test_scenario_routes() {
		$this->expecting( 'GET', '/v1/scenarios/a%2Fb' )->get_scenario( 'a/b' );
		$this->expecting( 'POST', '/v1/scenarios', array( 'name' => 'n' ), array( 'Idempotency-Key' => 'idem-key' ) )->create_scenario( array( 'name' => 'n' ), 'idem-key' );
		$this->expecting( 'POST', '/v1/scenarios/s1/run', array() )->run_scenario( 's1' );
		$this->addToAssertionCount( 1 );
	}

	public function test_run_routes() {
		$this->expecting( 'GET', '/v1/runs?scenarioId=s1&limit=20' )->list_runs( 's1' );
		$this->expecting( 'GET', '/v1/runs/r1' )->get_run( 'r1' );
		$this->addToAssertionCount( 1 );
	}

	public function test_assistant_routes() {
		$this->expecting( 'POST', '/v1/flow-assistant/detect', array( 'url' => 'https://shop.example/' ) )->assistant_detect( 'https://shop.example/' );
		$this->expecting(
			'POST',
			'/v1/flow-assistant/trials',
			array(
				'url'          => 'https://shop.example/',
				'confirmOwner' => true,
			)
		)->assistant_start( 'https://shop.example/' );
		$this->expecting( 'GET', '/v1/flow-assistant/trials/t1' )->assistant_trial( 't1' );
		$this->expecting(
			'POST',
			'/v1/flow-assistant/trials/t1/save',
			array(
				'index' => 2,
				'name'  => 'Flow',
			)
		)->assistant_save( 't1', '2', 'Flow' );
		$this->addToAssertionCount( 1 );
	}

	public function test_status_page_routes() {
		$this->expecting( 'POST', '/v1/status-pages', array( 'name' => 'Shop' ) )->create_status_page( array( 'name' => 'Shop' ) );
		$this->expecting( 'PATCH', '/v1/status-pages/p1', array( 'published' => false ) )->update_status_page( 'p1', array( 'published' => false ) );
		$this->addToAssertionCount( 1 );
	}
}
