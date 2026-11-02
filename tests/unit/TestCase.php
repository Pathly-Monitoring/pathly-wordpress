<?php
/**
 * Base test case.
 *
 * @package Pathly
 */

namespace Pathly\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * Brain Monkey, the plugin constants and an in-memory option store.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Options and transients written during the test.
	 *
	 * @var array
	 */
	protected $options = array();

	/**
	 * False for the tests that load pathly.php, which defines the constants itself.
	 *
	 * @var bool
	 */
	protected $define_constants = true;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		if ( $this->define_constants && ! defined( 'PATHLY_VERSION' ) ) {
			define( 'PATHLY_VERSION', '0.1.0' );
			define( 'PATHLY_FILE', dirname( __DIR__, 2 ) . '/pathly.php' );
			define( 'PATHLY_DIR', dirname( __DIR__, 2 ) );
		}
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		$this->options = array();
		$store         = &$this->options;
		Functions\when( 'get_option' )->alias(
			static function ( $name, $fallback = false ) use ( &$store ) {
				return array_key_exists( $name, $store ) ? $store[ $name ] : $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( $name, $value ) use ( &$store ) {
				$store[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function ( $name ) use ( &$store ) {
				unset( $store[ $name ] );
				return true;
			}
		);
		Functions\when( 'get_transient' )->alias(
			static function ( $name ) use ( &$store ) {
				return array_key_exists( 'transient_' . $name, $store ) ? $store[ 'transient_' . $name ] : false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			static function ( $name, $value ) use ( &$store ) {
				$store[ 'transient_' . $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			static function ( $name ) use ( &$store ) {
				unset( $store[ 'transient_' . $name ] );
				return true;
			}
		);
		Functions\when( 'wp_salt' )->justReturn( 'unit-test-salt' );
		Functions\when( 'get_user_locale' )->justReturn( 'fr_FR' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Saves a valid key in the option store.
	 *
	 * @param string $key Key.
	 * @return void
	 */
	protected function with_key( $key = 'sp_test_0123456789abcdef' ) {
		\Pathly_Key_Store::save( $key );
	}

	/**
	 * Mocked API.
	 *
	 * @return \Mockery\MockInterface|\Pathly_Api
	 */
	protected function api() {
		return \Mockery::mock( \Pathly_Api::class );
	}

	/**
	 * Output of a callable.
	 *
	 * @param callable $callback Callable.
	 * @return string
	 */
	protected function capture( callable $callback ) {
		ob_start();
		$callback();
		return (string) ob_get_clean();
	}
}
