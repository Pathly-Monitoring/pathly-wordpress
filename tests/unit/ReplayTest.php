<?php
/**
 * Tests of Pathly_Replay: the Flow is replayed after an update.
 *
 * @package Pathly
 */

namespace Pathly\Tests;

use Brain\Monkey\Functions;

class ReplayTest extends TestCase {

	private function connected() {
		$this->with_key();
		\Pathly_State::update( array( 'flow_id' => 'f1' ) );
	}

	public function test_plugin_update_schedules_one_replay() {
		$this->connected();
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\expect( 'wp_schedule_single_event' )->once()->with( \Mockery::type( 'int' ), 'pathly_replay_flow' );

		\Pathly_Replay::on_upgrade(
			null,
			array(
				'action' => 'update',
				'type'   => 'plugin',
			)
		);
		$this->addToAssertionCount( 1 );
	}

	public function test_already_scheduled_is_not_scheduled_twice() {
		$this->connected();
		Functions\when( 'wp_next_scheduled' )->justReturn( 12345 );
		Functions\expect( 'wp_schedule_single_event' )->never();

		\Pathly_Replay::on_upgrade(
			null,
			array(
				'action' => 'update',
				'type'   => 'core',
			)
		);
		$this->addToAssertionCount( 1 );
	}

	/**
	 * @dataProvider ignored_updates
	 *
	 * @param mixed $extra Details of the update.
	 * @param bool  $connected Connected site.
	 */
	public function test_ignored( $extra, $connected ) {
		if ( $connected ) {
			$this->connected();
		}
		Functions\expect( 'wp_next_scheduled' )->never();
		Functions\expect( 'wp_schedule_single_event' )->never();

		\Pathly_Replay::on_upgrade( null, $extra );
		$this->addToAssertionCount( 1 );
	}

	public function ignored_updates() {
		$plugin = array(
			'action' => 'update',
			'type'   => 'plugin',
		);
		return array(
			'not an array'    => array( null, true ),
			'no type'         => array( array( 'action' => 'update' ), true ),
			'install'         => array(
				array(
					'action' => 'install',
					'type'   => 'plugin',
				),
				true,
			),
			'translation'     => array(
				array(
					'action' => 'update',
					'type'   => 'translation',
				),
				true,
			),
			'not connected'   => array( $plugin, false ),
		);
	}

	public function test_flow_without_key_is_ignored() {
		\Pathly_State::update( array( 'flow_id' => 'f1' ) );
		Functions\expect( 'wp_schedule_single_event' )->never();
		\Pathly_Replay::on_upgrade(
			null,
			array(
				'action' => 'update',
				'type'   => 'theme',
			)
		);
		$this->addToAssertionCount( 1 );
	}

	public function test_run_queues_the_flow() {
		$this->connected();
		$this->options['transient_pathly_dashboard'] = array( 'old' );
		$api = $this->api();
		$api->shouldReceive( 'run_scenario' )->once()->with( 'f1' )->andReturn( array( 'queued' => true ) );

		\Pathly_Replay::run( $api );
		$this->assertIsInt( \Pathly_State::get( 'replay_at' ) );
		$this->assertNull( \Pathly_State::get( 'replay_error' ) );
		$this->assertArrayNotHasKey( 'transient_pathly_dashboard', $this->options );
	}

	public function test_run_keeps_the_error() {
		$this->connected();
		$api = $this->api();
		$api->shouldReceive( 'run_scenario' )->andThrow( new \Pathly_Api_Error( 'Quota', 403, 'PLAN_LIMIT' ) );

		\Pathly_Replay::run( $api );
		$this->assertSame( 'Quota', \Pathly_State::get( 'replay_error' ) );
	}

	public function test_run_builds_its_own_client() {
		$this->connected();
		Functions\when( 'get_bloginfo' )->justReturn( '6.6' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{}' );
		Functions\expect( 'wp_remote_request' )->once()->with( 'https://api.pathlyhq.com/v1/scenarios/f1/run', \Mockery::type( 'array' ) )->andReturn( array() );

		\Pathly_Replay::run();
		$this->addToAssertionCount( 1 );
	}

	public function test_run_does_nothing_without_flow_or_key() {
		\Pathly_Replay::run( $this->api() );
		$this->with_key();
		\Pathly_Replay::run( $this->api() );
		$this->assertNull( \Pathly_State::get( 'replay_at' ) );
	}

	public function test_unschedule() {
		Functions\expect( 'wp_clear_scheduled_hook' )->once()->with( 'pathly_replay_flow' );
		\Pathly_Replay::unschedule();
		$this->addToAssertionCount( 1 );
	}
}
