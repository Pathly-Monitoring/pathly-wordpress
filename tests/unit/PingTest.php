<?php
/**
 * Tests of Pathly_Ping: create or attach, never a duplicate.
 *
 * @package Pathly
 */

namespace Pathly\Tests;

use Brain\Monkey\Functions;

class PingTest extends TestCase {

	const SITE = 'https://www.shop.example/';

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
	}

	public function test_normalize() {
		$this->assertSame( 'shop.example', \Pathly_Ping::normalize( 'https://WWW.Shop.example/' ) );
		$this->assertSame( 'shop.example/fr', \Pathly_Ping::normalize( 'http://shop.example/fr/' ) );
		$this->assertSame( 'shop.example', \Pathly_Ping::normalize( 'https://shop.example' ) );
		$this->assertSame( '', \Pathly_Ping::normalize( 'not a url' ) );
		$this->assertSame( '', \Pathly_Ping::normalize( 'http:///x' ) );
	}

	public function test_keeps_a_known_ping() {
		\Pathly_State::update( array( 'ping_id' => 'p1' ) );
		$api = $this->api();
		$api->shouldReceive( 'get_scenario' )->once()->with( 'p1' )->andReturn( array( 'id' => 'p1' ) );
		$api->shouldNotReceive( 'list_scenarios', 'create_scenario' );

		$this->assertSame( 'p1', ( new \Pathly_Ping( $api ) )->ensure( self::SITE ) );
	}

	public function test_known_ping_error_other_than_404_is_raised() {
		\Pathly_State::update( array( 'ping_id' => 'p1' ) );
		$api = $this->api();
		$api->shouldReceive( 'get_scenario' )->andThrow( new \Pathly_Api_Error( 'no', 401 ) );

		$this->expectException( \Pathly_Api_Error::class );
		( new \Pathly_Ping( $api ) )->ensure( self::SITE );
	}

	public function test_attaches_to_an_existing_ping_on_a_later_page() {
		\Pathly_State::update( array( 'ping_id' => 'gone' ) );
		$api = $this->api();
		$api->shouldReceive( 'get_scenario' )->andThrow( new \Pathly_Api_Error( 'gone', 404 ) );
		$api->shouldReceive( 'list_scenarios' )->with( '' )->andReturn(
			array(
				'items'      => array(
					array(
						'id'   => 'b1',
						'type' => 'browser',
						'url'  => 'https://shop.example',
					),
					array(
						'id'   => 'h0',
						'type' => 'http',
						'url'  => 'https://other.example',
					),
					array( 'id' => 'broken' ),
				),
				'nextCursor' => 'c2',
			)
		);
		$api->shouldReceive( 'list_scenarios' )->with( 'c2' )->andReturn(
			array(
				'items' => array(
					array(
						'id'   => 'h1',
						'type' => 'http',
						'url'  => 'https://shop.example',
					),
				),
			)
		);
		$api->shouldNotReceive( 'create_scenario' );

		$this->assertSame( 'h1', ( new \Pathly_Ping( $api ) )->ensure( self::SITE ) );
		$this->assertSame( 'h1', \Pathly_State::get( 'ping_id' ) );
	}

	public function test_creates_the_ping_with_an_idempotency_key() {
		$api = $this->api();
		$api->shouldReceive( 'list_scenarios' )->once()->andReturn( array( 'items' => 'not a list' ) );
		$api->shouldReceive( 'create_scenario' )->once()->with(
			array(
				'name'        => 'Ping · shop.example',
				'type'        => 'http',
				'url'         => self::SITE,
				'intervalSec' => 300,
				'tags'        => array( 'wordpress' ),
			),
			'wp-ping-' . substr( hash( 'sha256', 'shop.example' ), 0, 40 )
		)->andReturn( array( 'id' => 'new' ) );

		$this->assertSame( 'new', ( new \Pathly_Ping( $api ) )->ensure( self::SITE ) );
		$this->assertSame( 'new', \Pathly_State::get( 'ping_id' ) );
	}

	public function test_stops_after_ten_pages_and_handles_an_answer_without_id() {
		$api = $this->api();
		$api->shouldReceive( 'list_scenarios' )->times( 10 )->andReturn(
			array(
				'items'      => array(),
				'nextCursor' => 'again',
			)
		);
		$api->shouldReceive( 'create_scenario' )->once()->andReturn( array() );

		$this->assertSame( '', ( new \Pathly_Ping( $api ) )->ensure( self::SITE ) );
		$this->assertNull( \Pathly_State::get( 'ping_id' ) );
	}
}
