<?php
/**
 * Tests of pathly.php and uninstall.php, each in its own process.
 *
 * @package Pathly
 */

namespace Pathly\Tests;

use Brain\Monkey\Functions;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class EntryPointsTest extends TestCase {

	/**
	 * The main file defines the constants itself.
	 *
	 * @var bool
	 */
	protected $define_constants = false;

	public function test_main_file_registers_its_hooks() {
		Functions\expect( 'register_deactivation_hook' )->once()->with( \Mockery::type( 'string' ), array( 'Pathly_Plugin', 'deactivate' ) );
		require dirname( __DIR__, 2 ) . '/pathly.php';

		$manifest = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/.release-please-manifest.json' ), true );
		$this->assertSame( $manifest['.'], PATHLY_VERSION );
		$this->assertNotFalse( has_action( 'plugins_loaded', array( 'Pathly_Plugin', 'boot' ) ) );
	}

	public function test_uninstall_file_cleans_up() {
		define( 'WP_UNINSTALL_PLUGIN', 'pathly/pathly.php' );
		$this->options['pathly_state'] = array( 'ping_id' => 'p1' );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\expect( 'wp_clear_scheduled_hook' )->once();

		require dirname( __DIR__, 2 ) . '/uninstall.php';
		$this->assertSame( array(), $this->options );
	}
}
