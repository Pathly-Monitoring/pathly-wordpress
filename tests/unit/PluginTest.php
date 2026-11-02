<?php
/**
 * Tests of the main file, Pathly_Plugin, uninstall.php and Pathly_Uninstaller.
 *
 * @package Pathly
 */

namespace Pathly\Tests;

use Brain\Monkey\Functions;

class PluginTest extends TestCase {

	public function test_boot_on_the_front_end_does_not_load_the_admin() {
		Functions\when( 'is_admin' )->justReturn( false );
		\Pathly_Plugin::boot();
		$this->assertNotFalse( has_action( 'init', array( 'Pathly_Plugin', 'load_textdomain' ) ) );
		$this->assertNotFalse( has_action( 'init', array( 'Pathly_Badge', 'register' ) ) );
		$this->assertNotFalse( has_action( 'upgrader_process_complete', array( 'Pathly_Replay', 'on_upgrade' ) ) );
		$this->assertNotFalse( has_action( 'pathly_replay_flow', array( 'Pathly_Replay', 'run' ) ) );
		$this->assertFalse( has_action( 'admin_menu' ) );
	}

	public function test_boot_in_the_admin() {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'plugin_basename' )->justReturn( 'pathly/pathly.php' );
		\Pathly_Plugin::boot();
		$this->assertTrue( has_action( 'admin_menu' ) );
		$this->assertTrue( has_action( 'wp_dashboard_setup' ) );
	}

	public function test_textdomain_and_deactivation() {
		Functions\when( 'plugin_basename' )->justReturn( 'pathly/pathly.php' );
		Functions\expect( 'load_plugin_textdomain' )->once()->with( 'pathly', false, 'pathly/languages' );
		Functions\expect( 'wp_clear_scheduled_hook' )->once()->with( 'pathly_replay_flow' );
		\Pathly_Plugin::load_textdomain();
		\Pathly_Plugin::deactivate();
		$this->addToAssertionCount( 1 );
	}

	public function test_uninstaller_single_site() {
		$this->with_key();
		\Pathly_State::update( array( 'ping_id' => 'p1' ) );
		$this->options['transient_pathly_dashboard'] = array();
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\expect( 'wp_clear_scheduled_hook' )->once();

		\Pathly_Uninstaller::run();
		$this->assertSame( array(), $this->options );
	}

	public function test_uninstaller_network() {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\expect( 'get_sites' )->once()->andReturn( array( 1, 2 ) );
		Functions\expect( 'switch_to_blog' )->twice();
		Functions\expect( 'restore_current_blog' )->twice();
		Functions\expect( 'wp_clear_scheduled_hook' )->twice();

		\Pathly_Uninstaller::run();
		$this->addToAssertionCount( 1 );
	}
}
