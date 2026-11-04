<?php
/**
 * Unit tests: WordPress is simulated with Brain Monkey, the API with Mockery.
 *
 * @package Pathly
 */

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require __DIR__ . '/TestCase.php';

define( 'ABSPATH', '/tmp/wordpress/' );

foreach ( glob( dirname( __DIR__, 2 ) . '/includes/*.php' ) as $pathly_file ) {
	require_once $pathly_file;
}
require __DIR__ . '/TestableAdmin.php';

/**
 * Minimal WP_Error for is_wp_error() in tests.
 */
class WP_Error {
}
