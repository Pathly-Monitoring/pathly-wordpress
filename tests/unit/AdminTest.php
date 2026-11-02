<?php
/**
 * Tests of Pathly_Admin: capability, nonce, actions and screen.
 *
 * @package Pathly
 */

namespace Pathly\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

class AdminTest extends TestCase {

	const SITE = 'https://shop.example/';

	/**
	 * Mocked API.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $mock;

	/**
	 * Keys given to the factory.
	 *
	 * @var string[]
	 */
	private $keys = array();

	/**
	 * Nonce actions checked and redirections sent.
	 *
	 * @var array
	 */
	private $calls = array();

	protected function setUp(): void {
		parent::setUp();
		$this->mock = $this->api();
		$_POST      = array();
		Functions\when( 'home_url' )->justReturn( self::SITE );
		Functions\when( 'admin_url' )->alias(
			static function ( $path ) {
				return 'https://shop.example/wp-admin/' . $path;
			}
		);
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		$calls       = &$this->calls;
		$this->calls = array();
		Functions\when( 'check_admin_referer' )->alias(
			static function ( $action ) use ( &$calls ) {
				$calls[] = 'nonce:' . $action;
				return 1;
			}
		);
		Functions\when( 'wp_safe_redirect' )->alias(
			static function ( $url ) use ( &$calls ) {
				$calls[] = 'redirect:' . $url;
				return true;
			}
		);
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'absint' )->alias(
			static function ( $v ) {
				return abs( (int) $v );
			}
		);
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'get_page_by_path' )->justReturn( null );
		Functions\when( 'wp_nonce_field' )->alias(
			static function ( $action ) {
				echo '<input type="hidden" name="_wpnonce" data-action="' . $action . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		);
		Functions\when( 'submit_button' )->alias(
			static function ( $text ) {
				echo '<button>' . $text . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		);
	}

	protected function tearDown(): void {
		$_POST = array();
		parent::tearDown();
	}

	private function admin() {
		$mock = $this->mock;
		$keys = &$this->keys;
		return new \Pathly_Admin(
			static function ( $key ) use ( $mock, &$keys ) {
				$keys[] = $key;
				return $mock;
			}
		);
	}

	private function notice() {
		return $this->options['transient_pathly_notice_7'];
	}

	public function test_hooks() {
		Functions\when( 'plugin_basename' )->justReturn( 'pathly/pathly.php' );
		$admin = $this->admin();
		$admin->hooks();
		$this->assertNotFalse( has_action( 'admin_menu', array( $admin, 'menu' ) ) );
		$this->assertNotFalse( has_action( 'admin_enqueue_scripts', array( $admin, 'assets' ) ) );
		$this->assertNotFalse( has_filter( 'plugin_action_links_pathly/pathly.php', array( $admin, 'action_links' ) ) );
		$this->assertNotFalse( has_action( 'admin_post_pathly_connect', 'function ()' ) );
		$this->assertNotFalse( has_action( 'admin_post_pathly_badge', 'function ()' ) );
	}

	public function test_registered_closure_runs_the_action() {
		Functions\when( 'plugin_basename' )->justReturn( 'pathly/pathly.php' );
		$closure = null;
		Actions\expectAdded( 'admin_post_pathly_flow_cancel' )->once()->whenHappen(
			static function ( $callback ) use ( &$closure ) {
				$closure = $callback;
			}
		);
		$this->admin()->hooks();
		\Pathly_State::update( array( 'trial_id' => 't1' ) );
		$closure();
		$this->assertNull( \Pathly_State::get( 'trial_id' ) );
	}

	public function test_menu() {
		Functions\expect( 'add_options_page' )->once()->with( 'Pathly', 'Pathly', 'manage_options', 'pathly', \Mockery::type( 'array' ) );
		$this->admin()->menu();
		$this->addToAssertionCount( 1 );
	}

	public function test_assets() {
		Functions\when( 'plugins_url' )->alias(
			static function ( $path ) {
				return 'https://shop.example/wp-content/plugins/pathly/' . $path;
			}
		);
		Functions\expect( 'wp_enqueue_style' )->twice();
		Functions\expect( 'wp_enqueue_script' )->once();
		$admin = $this->admin();
		$admin->assets( 'edit.php' );
		$admin->assets( 'index.php' );
		$admin->assets( 'settings_page_pathly' );
		$this->addToAssertionCount( 1 );
	}

	public function test_action_links() {
		$links = $this->admin()->action_links( array( '<a>Deactivate</a>' ) );
		$this->assertCount( 2, $links );
		$this->assertStringContainsString( 'options-general.php?page=pathly', $links[0] );
	}

	public function test_handle_refuses_non_administrators() {
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'wp_die' )->once();
		$this->admin()->handle( 'disconnect' );
		$this->assertSame( array(), $this->calls );
	}

	public function test_handle_checks_the_nonce_and_redirects() {
		$this->options['transient_pathly_dashboard'] = array( 'old' );
		$this->admin()->handle( 'flow_cancel' );
		$this->assertSame( array( 'nonce:pathly_flow_cancel', 'redirect:https://shop.example/wp-admin/options-general.php?page=pathly' ), $this->calls );
		$this->assertArrayNotHasKey( 'transient_pathly_dashboard', $this->options );
	}

	public function test_connect_saves_the_key_after_the_ping() {
		$_POST['pathly_api_key'] = ' sp_live_0123456789abcdef ';
		$this->mock->shouldReceive( 'list_scenarios' )->andReturn( array( 'items' => array() ) );
		$this->mock->shouldReceive( 'create_scenario' )->andReturn( array( 'id' => 'p1' ) );

		$this->admin()->handle( 'connect' );
		$this->assertSame( array( 'sp_live_0123456789abcdef' ), $this->keys );
		$this->assertSame( 'sp_live_0123456789abcdef', \Pathly_Key_Store::get() );
		$this->assertSame( 'p1', \Pathly_State::get( 'ping_id' ) );
		$this->assertSame( 'success', $this->notice()['type'] );
	}

	public function test_connect_again_with_the_saved_key() {
		$this->with_key();
		$this->mock->shouldReceive( 'list_scenarios' )->andReturn( array( 'items' => array() ) );
		$this->mock->shouldReceive( 'create_scenario' )->andReturn( array( 'id' => 'p1' ) );
		$this->admin()->handle( 'connect' );
		$this->assertSame( array( 'sp_test_0123456789abcdef' ), $this->keys );
	}

	public function test_connect_refuses_a_bad_format_without_calling_pathly() {
		$_POST['pathly_api_key'] = 'hello';
		$this->admin()->handle( 'connect' );
		$this->assertSame( array(), $this->keys );
		$this->assertSame( 'error', $this->notice()['type'] );
		$this->assertFalse( \Pathly_Key_Store::has() );
	}

	public function test_connect_refused_key_is_not_saved() {
		$_POST['pathly_api_key'] = 'sp_live_0123456789abcdef';
		$this->mock->shouldReceive( 'list_scenarios' )->andThrow( new \Pathly_Api_Error( 'Unauthorized', 401 ) );
		$this->admin()->handle( 'connect' );
		$this->assertFalse( \Pathly_Key_Store::has() );
		$this->assertStringContainsString( 'refused this key', $this->notice()['message'] );
	}

	public function test_connect_other_error_is_shown_as_is() {
		$_POST['pathly_api_key'] = 'sp_live_0123456789abcdef';
		$this->mock->shouldReceive( 'list_scenarios' )->andThrow( new \Pathly_Api_Error( 'Panne', 503 ) );
		$this->admin()->handle( 'connect' );
		$this->assertSame( 'Panne', $this->notice()['message'] );
	}

	public function test_disconnect_forgets_everything() {
		$this->with_key();
		\Pathly_State::update( array( 'ping_id' => 'p1' ) );
		Functions\expect( 'wp_clear_scheduled_hook' )->once();
		$this->admin()->handle( 'disconnect' );
		$this->assertFalse( \Pathly_Key_Store::has() );
		$this->assertSame( array(), \Pathly_State::all() );
	}

	public function test_flow_actions_need_a_key() {
		$this->admin()->handle( 'flow_start' );
		$this->assertStringContainsString( 'Connect your site', $this->notice()['message'] );
	}

	public function test_flow_start_and_save() {
		$this->with_key();
		$this->mock->shouldReceive( 'assistant_detect' )->andReturn( array( 'available' => true ) );
		$this->mock->shouldReceive( 'assistant_start' )->with( self::SITE )->andReturn( array( 'id' => 't1' ) );
		$this->admin()->handle( 'flow_start' );
		$this->assertSame( 't1', \Pathly_State::get( 'trial_id' ) );

		$_POST['pathly_index'] = '1';
		$this->mock->shouldReceive( 'assistant_save' )->with( 't1', 1, 'Flow · shop.example' )->andReturn( array( 'item' => array( 'id' => 'f1' ) ) );
		$this->admin()->handle( 'flow_save' );
		$this->assertSame( 'f1', \Pathly_State::get( 'flow_id' ) );
		$this->assertSame( 'success', $this->notice()['type'] );
	}

	public function test_badge_on_and_off() {
		$this->with_key();
		Functions\when( 'get_bloginfo' )->justReturn( 'Shop' );
		$this->mock->shouldReceive( 'create_status_page' )->andReturn( array( 'id' => 's1' ) );
		$_POST['pathly_badge'] = '1';
		$this->admin()->handle( 'badge' );
		$this->assertTrue( \Pathly_State::get( 'badge_enabled' ) );

		$this->mock->shouldReceive( 'update_status_page' )->with( 's1', array( 'public' => false ) );
		$_POST['pathly_badge'] = '0';
		$this->admin()->handle( 'badge' );
		$this->assertFalse( \Pathly_State::get( 'badge_enabled' ) );
	}

	public function test_render_for_non_administrators_prints_nothing() {
		Functions\when( 'current_user_can' )->justReturn( false );
		$this->assertSame( '', $this->capture( array( $this->admin(), 'render' ) ) );
	}

	public function test_render_not_connected() {
		$this->options['transient_pathly_notice_7'] = array(
			'type'    => 'error',
			'message' => 'Mauvaise clé',
		);
		$html = $this->capture( array( $this->admin(), 'render' ) );
		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'Mauvaise clé', $html );
		$this->assertStringContainsString( 'name="pathly_api_key"', $html );
		$this->assertStringContainsString( 'type="password"', $html );
		$this->assertStringContainsString( 'ref=wordpress', $html );
		$this->assertStringContainsString( 'legal/cgu', $html );
		$this->assertStringContainsString( 'legal/privacy', $html );
		$this->assertStringNotContainsString( 'Ask Lumi', $html );
		$this->assertArrayNotHasKey( 'transient_pathly_notice_7', $this->options );
	}

	public function test_render_connected_never_prints_the_key() {
		$this->with_key();
		\Pathly_State::update( array( 'ping_id' => 'p1' ) );
		$this->options['transient_pathly_notice_7'] = array(
			'type'    => 'success',
			'message' => 'OK',
		);
		$html = $this->capture( array( $this->admin(), 'render' ) );
		$this->assertStringNotContainsString( 'sp_test_0123456789abcdef', $html );
		$this->assertStringContainsString( 'sp_••••••••cdef', $html );
		$this->assertStringContainsString( 'notice-success', $html );
		$this->assertStringContainsString( 'every five minutes', $html );
		$this->assertStringContainsString( 'Ask Lumi to prepare the Flow', $html );
		$this->assertStringContainsString( 'test@example.com', $html );
		$this->assertStringContainsString( 'Turn the badge on', $html );
	}

	public function test_render_pending_trial_refreshes() {
		$this->with_key();
		\Pathly_State::update( array( 'trial_id' => 't1' ) );
		$this->mock->shouldReceive( 'assistant_trial' )->andReturn( array( 'status' => 'queued' ) );
		$html = $this->capture( array( $this->admin(), 'render' ) );
		$this->assertStringContainsString( 'data-pathly-refresh', $html );
	}

	public function test_render_proposals() {
		$this->with_key();
		\Pathly_State::update( array( 'trial_id' => 't1' ) );
		$this->mock->shouldReceive( 'assistant_trial' )->andReturn(
			array(
				'status'    => 'ready',
				'proposals' => array(
					array(
						'index' => 3,
						'kind'  => 'contact',
						'steps' => array( 1, 2, 3 ),
					),
					array( 'kind' => 'custom' ),
					array( 'index' => 4 ),
				),
			)
		);
		$html = $this->capture( array( $this->admin(), 'render' ) );
		$this->assertStringContainsString( 'Contact form, filled and stopped before sending', $html );
		$this->assertStringContainsString( '3 steps', $html );
		$this->assertStringContainsString( 'Other path', $html );
		$this->assertStringContainsString( 'name="pathly_index" value="3"', $html );
		$this->assertStringContainsString( 'name="pathly_index" value="0"', $html );
		$this->assertStringContainsString( 'Validate this Flow', $html );
		$this->assertStringContainsString( 'Start again', $html );
	}

	public function test_render_trial_without_proposal() {
		$this->with_key();
		\Pathly_State::update( array( 'trial_id' => 't1' ) );
		$this->mock->shouldReceive( 'assistant_trial' )->andReturn( array( 'status' => 'not_found' ) );
		$html = $this->capture( array( $this->admin(), 'render' ) );
		$this->assertStringContainsString( 'found no Flow', $html );
	}

	public function test_render_trial_error_offers_a_new_start() {
		$this->with_key();
		\Pathly_State::update( array( 'trial_id' => 't1' ) );
		$this->mock->shouldReceive( 'assistant_trial' )->andThrow( new \Pathly_Api_Error( 'Essai introuvable', 404 ) );
		$html = $this->capture( array( $this->admin(), 'render' ) );
		$this->assertStringContainsString( 'Essai introuvable', $html );
		$this->assertStringContainsString( 'Ask Lumi to prepare the Flow', $html );
	}

	public function test_render_saved_flow_with_replay_error_and_badge_on() {
		$this->with_key();
		\Pathly_State::update(
			array(
				'flow_id'       => 'f1',
				'replay_error'  => 'Quota atteint',
				'badge_enabled' => true,
				'badge_url'     => 'https://pathlyhq.com/badge/s1.svg',
				'status_url'    => 'https://pathlyhq.com/status/s1',
			)
		);
		$html = $this->capture( array( $this->admin(), 'render' ) );
		$this->assertStringContainsString( 'Your Flow is saved', $html );
		$this->assertStringContainsString( 'Quota atteint', $html );
		$this->assertStringContainsString( 'Turn the badge off', $html );
		$this->assertStringContainsString( 'badge/s1.svg', $html );
	}

	public function test_render_saved_flow_without_error() {
		$this->with_key();
		\Pathly_State::update( array( 'flow_id' => 'f1' ) );
		$html = $this->capture( array( $this->admin(), 'render' ) );
		$this->assertStringNotContainsString( 'could not start', $html );
	}

	public function test_default_factory_and_site_url() {
		$this->assertSame( self::SITE, \Pathly_Admin::site_url() );
		Functions\when( 'get_bloginfo' )->justReturn( '6.6' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 401 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"error":"Unauthorized"}' );
		Functions\expect( 'wp_remote_request' )->once()->andReturn( array() );
		$_POST['pathly_api_key'] = 'sp_live_0123456789abcdef';
		( new \Pathly_Admin() )->handle( 'connect' );
		$this->assertStringContainsString( 'refused this key', $this->notice()['message'] );
	}

}
