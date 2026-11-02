<?php
/**
 * Tests of Pathly_Flow: Lumi trial, proposals and saved Flow.
 *
 * @package Pathly
 */

namespace Pathly\Tests;

use Brain\Monkey\Functions;

class FlowTest extends TestCase {

	const SITE = 'https://shop.example/';

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'get_page_by_path' )->justReturn( null );
	}

	public function test_goal_is_contact_when_a_contact_page_exists() {
		Functions\when( 'get_page_by_path' )->alias(
			static function ( $slug ) {
				return 'nous-contacter' === $slug ? (object) array( 'ID' => 3 ) : null;
			}
		);
		$this->assertSame( 'contact', \Pathly_Flow::goal() );
	}

	public function test_goal_is_login_otherwise() {
		$this->assertSame( 'login', \Pathly_Flow::goal() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_goal_is_cart_with_woocommerce() {
		eval( 'class WooCommerce {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- defines the WooCommerce class for this isolated test only.
		$this->assertSame( 'cart', \Pathly_Flow::goal() );
	}

	public function test_start_checks_availability_then_starts_a_trial() {
		$api = $this->api();
		$api->shouldReceive( 'assistant_detect' )->once()->with( self::SITE )->andReturn( array( 'available' => true ) );
		$api->shouldReceive( 'assistant_start' )->once()->with( self::SITE )->andReturn( array( 'id' => 't1' ) );

		$this->assertSame( 't1', ( new \Pathly_Flow( $api ) )->start( self::SITE ) );
		$this->assertSame( 't1', \Pathly_State::get( 'trial_id' ) );
	}

	public function test_start_without_id() {
		$api = $this->api();
		$api->shouldReceive( 'assistant_detect' )->andReturn( array( 'available' => true ) );
		$api->shouldReceive( 'assistant_start' )->andReturn( array() );

		$this->assertSame( '', ( new \Pathly_Flow( $api ) )->start( self::SITE ) );
		$this->assertNull( \Pathly_State::get( 'trial_id' ) );
	}

	public function test_start_refused_with_reason() {
		$api = $this->api();
		$api->shouldReceive( 'assistant_detect' )->andReturn(
			array(
				'available' => false,
				'reason'    => 'quota',
			)
		);
		$api->shouldNotReceive( 'assistant_start' );
		try {
			( new \Pathly_Flow( $api ) )->start( self::SITE );
			$this->fail( 'No exception' );
		} catch ( \Pathly_Api_Error $e ) {
			$this->assertSame( 'quota', $e->api_code() );
			$this->assertStringContainsString( 'trials for today', $e->getMessage() );
		}
	}

	/**
	 * @dataProvider trial_refusals
	 *
	 * @param string $code     Code sent by the API.
	 * @param string $expected Part of the message shown.
	 */
	public function test_start_refusals_are_explained( $code, $expected ) {
		$api = $this->api();
		$api->shouldReceive( 'assistant_detect' )->andReturn( array( 'available' => true ) );
		$api->shouldReceive( 'assistant_start' )->andThrow( new \Pathly_Api_Error( 'Refus', 429, $code ) );
		try {
			( new \Pathly_Flow( $api ) )->start( self::SITE );
			$this->fail( 'No exception' );
		} catch ( \Pathly_Api_Error $e ) {
			$this->assertStringContainsString( $expected, $e->getMessage() );
			$this->assertSame( $code, $e->api_code() );
		}
	}

	public function trial_refusals() {
		return array(
			'quota' => array( 'assistant_quota', 'trials for today' ),
			'off'   => array( 'assistant_off', 'not available' ),
			'busy'  => array( 'assistant_busy', 'very busy' ),
			'url'   => array( 'url_blocked', 'cannot reach' ),
		);
	}

	public function test_start_other_refusal_is_raised_as_is() {
		$api = $this->api();
		$api->shouldReceive( 'assistant_detect' )->andReturn( array( 'available' => true ) );
		$api->shouldReceive( 'assistant_start' )->andThrow( new \Pathly_Api_Error( 'Panne', 500 ) );
		$this->expectExceptionMessage( 'Panne' );
		( new \Pathly_Flow( $api ) )->start( self::SITE );
	}

	public function test_save_already_saved_keeps_the_existing_flow() {
		\Pathly_State::update( array( 'trial_id' => 't1' ) );
		$api   = $this->api();
		$error = ( new \Pathly_Api_Error( 'Déjà', 409, 'already_saved' ) )->with_data( array( 'monitorId' => 'f9' ) );
		$api->shouldReceive( 'assistant_save' )->andThrow( $error );

		$this->assertSame( 'f9', ( new \Pathly_Flow( $api ) )->save( 0, self::SITE ) );
		$this->assertSame( 'f9', \Pathly_State::get( 'flow_id' ) );
	}

	/**
	 * @dataProvider save_refusals
	 *
	 * @param \Pathly_Api_Error $error Refusal.
	 */
	public function test_save_other_refusals_are_raised( $error ) {
		\Pathly_State::update( array( 'trial_id' => 't1' ) );
		$api = $this->api();
		$api->shouldReceive( 'assistant_save' )->andThrow( $error );
		$this->expectException( \Pathly_Api_Error::class );
		( new \Pathly_Flow( $api ) )->save( 0, self::SITE );
	}

	public function save_refusals() {
		return array(
			'plan limit'              => array( new \Pathly_Api_Error( 'Limite', 403, 'PLAN_LIMIT' ) ),
			'already saved, no id'    => array( new \Pathly_Api_Error( 'Déjà', 409, 'already_saved' ) ),
		);
	}

	public function test_start_refused_without_reason() {
		$api = $this->api();
		$api->shouldReceive( 'assistant_detect' )->andReturn( array() );
		$this->expectException( \Pathly_Api_Error::class );
		( new \Pathly_Flow( $api ) )->start( self::SITE );
	}

	public function test_unavailable_messages() {
		$this->assertStringContainsString( 'cannot reach', \Pathly_Flow::unavailable_message( 'unreachable' ) );
		$this->assertStringContainsString( 'cannot reach', \Pathly_Flow::unavailable_message( 'url_blocked' ) );
		$this->assertStringContainsString( 'not available', \Pathly_Flow::unavailable_message( 'off' ) );
	}

	public function test_current_without_trial() {
		$this->assertNull( ( new \Pathly_Flow( $this->api() ) )->current() );
	}

	public function test_current_pending() {
		\Pathly_State::update( array( 'trial_id' => 't1' ) );
		$api = $this->api();
		$api->shouldReceive( 'assistant_trial' )->with( 't1' )->andReturn( array( 'item' => array( 'status' => 'exploring' ) ) );

		$this->assertSame(
			array(
				'status'    => 'pending',
				'proposals' => array(),
			),
			( new \Pathly_Flow( $api ) )->current()
		);
	}

	public function test_current_ready_puts_the_goal_first() {
		\Pathly_State::update( array( 'trial_id' => 't1' ) );
		$api = $this->api();
		$api->shouldReceive( 'assistant_trial' )->andReturn(
			array(
				'status'    => 'ready',
				'proposals' => array(
					array(
						'index' => 0,
						'kind'  => 'contact',
					),
					array( 'index' => 1 ),
					array(
						'index' => 2,
						'kind'  => 'login',
					),
				),
			)
		);

		$out = ( new \Pathly_Flow( $api ) )->current();
		$this->assertSame( 'ready', $out['status'] );
		$this->assertSame( 2, $out['proposals'][0]['index'] );
	}

	public function test_current_without_status_or_proposals() {
		\Pathly_State::update( array( 'trial_id' => 't1' ) );
		$api = $this->api();
		$api->shouldReceive( 'assistant_trial' )->andReturn( array( 'item' => array( 'proposals' => 'x' ) ) );

		$this->assertSame(
			array(
				'status'    => 'failed',
				'proposals' => array(),
			),
			( new \Pathly_Flow( $api ) )->current()
		);
	}

	public function test_save_stores_the_flow_and_drops_the_trial() {
		\Pathly_State::update( array( 'trial_id' => 't1' ) );
		$api = $this->api();
		$api->shouldReceive( 'assistant_save' )->once()->with( 't1', 2, 'Flow · shop.example' )->andReturn( array( 'item' => array( 'id' => 'f1' ) ) );

		$this->assertSame( 'f1', ( new \Pathly_Flow( $api ) )->save( 2, self::SITE ) );
		$this->assertSame( 'f1', \Pathly_State::get( 'flow_id' ) );
		$this->assertNull( \Pathly_State::get( 'trial_id' ) );
	}

	public function test_save_with_a_flat_answer_without_id() {
		\Pathly_State::update( array( 'trial_id' => 't1' ) );
		$api = $this->api();
		$api->shouldReceive( 'assistant_save' )->andReturn( array() );

		$this->assertSame( '', ( new \Pathly_Flow( $api ) )->save( 0, self::SITE ) );
		$this->assertNull( \Pathly_State::get( 'flow_id' ) );
	}

	public function test_save_without_trial() {
		$this->expectException( \Pathly_Api_Error::class );
		( new \Pathly_Flow( $this->api() ) )->save( 0, self::SITE );
	}

	public function test_cancel() {
		\Pathly_State::update( array( 'trial_id' => 't1' ) );
		\Pathly_Flow::cancel();
		$this->assertNull( \Pathly_State::get( 'trial_id' ) );
	}
}
