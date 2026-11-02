<?php
/**
 * Tests of Pathly_Dashboard: data, cache and markup.
 *
 * @package Pathly
 */

namespace Pathly\Tests;

use Brain\Monkey\Functions;

class DashboardTest extends TestCase {

	/**
	 * Mocked API given to the box.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $mock;

	protected function setUp(): void {
		parent::setUp();
		$this->mock = $this->api();
		Functions\when( 'admin_url' )->alias(
			static function ( $path ) {
				return 'https://shop.example/wp-admin/' . $path;
			}
		);
		Functions\when( 'wp_date' )->justReturn( '02/11/2026 10:00' );
	}

	private function box() {
		$mock = $this->mock;
		return new \Pathly_Dashboard(
			static function ( $key ) use ( $mock ) {
				return $mock;
			}
		);
	}

	public function test_register_only_for_administrators() {
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'wp_add_dashboard_widget' )->never();
		$this->box()->register();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'wp_add_dashboard_widget' )->once()->with( 'pathly_dashboard', 'Pathly', \Mockery::type( 'array' ) );
		$this->box()->register();
		$this->addToAssertionCount( 1 );
	}

	public function test_without_key_invites_to_connect_without_any_call() {
		$this->mock->shouldNotReceive( 'account', 'get_scenario' );
		$html = $this->capture( array( $this->box(), 'render' ) );
		$this->assertStringContainsString( 'options-general.php?page=pathly', $html );
	}

	public function test_full_box() {
		$this->with_key();
		\Pathly_State::update(
			array(
				'ping_id' => 'p1',
				'flow_id' => 'f1',
			)
		);
		$this->mock->shouldReceive( 'get_scenario' )->with( 'p1' )->andReturn( array( 'lastStatus' => 'ok' ) );
		$this->mock->shouldReceive( 'get_scenario' )->with( 'f1' )->andReturn( array( 'lastStatus' => 'fail' ) );
		$this->mock->shouldReceive( 'list_runs' )->with( 'f1' )->andReturn(
			array(
				'items' => array(
					array(
						'id'     => 'r0',
						'status' => 'ok',
					),
					array( 'status' => 'fail' ),
					array(
						'id'         => 'r1',
						'status'     => 'fail',
						'created_at' => '2026-11-02T09:00:00Z',
					),
				),
			)
		);
		$this->mock->shouldReceive( 'get_run' )->with( 'r1' )->andReturn(
			array(
				'message'       => 'Bouton « Commander » introuvable',
				'screenshotUrl' => 'https://storage.example/shot.png?sig=1',
			)
		);
		$this->mock->shouldReceive( 'account' )->once()->andReturn(
			array(
				'plan'       => array( 'name' => 'Pro' ),
				'quotas'     => array(
					'ping'  => array(
						'used'      => 3,
						'limit'     => 50,
						'remaining' => 47,
					),
					'flow'  => array(
						'used'      => 10,
						'limit'     => null,
						'remaining' => null,
					),
					'other' => array(
						'used'  => 1,
						'limit' => 2,
					),
					'bad'   => 'x',
					'part'  => array( 'used' => 1 ),
				),
				'billingUrl' => 'https://pathlyhq.com/fr/billing',
			)
		);

		$html = $this->capture( array( $this->box(), 'render' ) );
		$this->assertStringContainsString( 'pathly-pill--ok', $html );
		$this->assertStringContainsString( 'pathly-pill--fail', $html );
		$this->assertStringContainsString( 'Last Flow failure: 02/11/2026 10:00', $html );
		$this->assertStringContainsString( 'introuvable', $html );
		$this->assertStringContainsString( 'https://storage.example/shot.png?sig=1', $html );
		$this->assertStringContainsString( 'Plan: Pro', $html );
		$this->assertStringContainsString( 'Ping: 3 / 50', $html );
		$this->assertStringContainsString( 'Flow: 10 / unlimited', $html );
		$this->assertStringContainsString( 'other: 1 / 2', $html );
		$this->assertStringNotContainsString( 'part:', $html );
		$this->assertStringContainsString( 'pathlyhq.com/fr/billing', $html );
		$this->assertStringContainsString( 'pathlyhq.com/fr/dashboard', $html );

		// Second display: from the cache, no new call (account() is expected once).
		$this->capture( array( $this->box(), 'render' ) );
	}

	public function test_failure_details_fall_back_to_the_list_and_skip_a_non_https_screenshot() {
		$this->with_key();
		\Pathly_State::update( array( 'flow_id' => 'f1' ) );
		$this->mock->shouldReceive( 'get_scenario' )->andReturn( array() );
		$this->mock->shouldReceive( 'list_runs' )->andReturn(
			array(
				'items' => array(
					array(
						'id'     => 'r1',
						'status' => 'error',
					),
				),
			)
		);
		$this->mock->shouldReceive( 'get_run' )->andReturn(
			array(
				'created_at'    => 'not a date',
				'screenshotUrl' => 'javascript:alert(1)',
			)
		);
		$this->mock->shouldReceive( 'account' )->andReturn( array( 'quotas' => 'not a list' ) );

		$html = $this->capture( array( $this->box(), 'render' ) );
		$this->assertStringContainsString( 'Last Flow failure: not a date', $html );
		$this->assertStringNotContainsString( 'javascript', $html );
		$this->assertStringContainsString( 'Waiting for the first run', $html );
		$this->assertStringContainsString( 'Not set up', $html );
	}

	public function test_failure_without_any_date() {
		$this->with_key();
		\Pathly_State::update( array( 'flow_id' => 'f1' ) );
		$this->mock->shouldReceive( 'get_scenario' )->andReturn( array( 'lastStatus' => 'error' ) );
		$this->mock->shouldReceive( 'list_runs' )->andReturn(
			array(
				'items' => array(
					array(
						'id'     => 'r1',
						'status' => 'fail',
					),
				),
			)
		);
		$this->mock->shouldReceive( 'get_run' )->andReturn( array() );
		$this->mock->shouldReceive( 'account' )->andReturn( 'not an array' );

		$data = $this->box()->data( 'k' );
		$this->assertSame(
			array(
				'at'         => '',
				'message'    => '',
				'screenshot' => '',
			),
			$data['failure']
		);
	}

	public function test_empty_quotas_print_no_list() {
		$this->with_key();
		$this->mock->shouldReceive( 'account' )->andReturn( array( 'quotas' => array() ) );
		$html = $this->capture( array( $this->box(), 'render' ) );
		$this->assertStringNotContainsString( 'pathly-quotas', $html );
	}

	public function test_no_failure_and_missing_routes() {
		$this->with_key();
		\Pathly_State::update( array( 'flow_id' => 'f1' ) );
		$this->mock->shouldReceive( 'get_scenario' )->andReturn( array( 'lastStatus' => 'ok' ) );
		$this->mock->shouldReceive( 'list_runs' )->andReturn( array( 'items' => 'x' ) );
		$this->mock->shouldReceive( 'account' )->andThrow( new \Pathly_Api_Error( 'Not found', 404 ) );

		$data = $this->box()->data( 'k' );
		$this->assertNull( $data['failure'] );
		$this->assertNull( $data['account'] );
		$this->assertSame( '', $data['error'] );
	}

	public function test_first_error_is_shown() {
		$this->with_key();
		\Pathly_State::update( array( 'ping_id' => 'p1' ) );
		$this->mock->shouldReceive( 'get_scenario' )->andThrow( new \Pathly_Api_Error( 'Clé refusée', 401 ) );
		$this->mock->shouldReceive( 'account' )->andThrow( new \Pathly_Api_Error( 'Autre', 500 ) );

		$html = $this->capture( array( $this->box(), 'render' ) );
		$this->assertStringContainsString( 'Clé refusée', $html );
		$this->assertStringNotContainsString( 'Autre', $html );
	}

	public function test_default_factory_builds_a_real_client() {
		$this->options['transient_pathly_dashboard'] = null;
		Functions\when( 'get_bloginfo' )->justReturn( '6.6' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 404 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '' );
		Functions\expect( 'wp_remote_request' )->once()->andReturn( array() );

		$data = ( new \Pathly_Dashboard() )->data( 'sp_key' );
		$this->assertNull( $data['account'] );
	}

	public function test_labels() {
		$this->assertSame( array( 'Not set up', 'none' ), \Pathly_Dashboard::label( null ) );
		$this->assertSame( array( 'OK', 'ok' ), \Pathly_Dashboard::label( 'ok' ) );
		$this->assertSame( array( 'Failing', 'fail' ), \Pathly_Dashboard::label( 'error' ) );
		$this->assertSame( array( 'Waiting for the first run', 'none' ), \Pathly_Dashboard::label( '' ) );
	}
}
