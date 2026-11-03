<?php
/**
 * Plugin Name:       Pathly
 * Plugin URI:        https://pathlyhq.com/en/cms-modules
 * Description:       Connects your site to Pathly: a Ping on your home page, a Flow on your checkout, contact form or login, replayed after each update, and their status on your dashboard.
 * Version:           0.2.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Pathly
 * Author URI:        https://pathlyhq.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pathly
 * Domain Path:       /languages
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

define( 'PATHLY_VERSION', '0.2.0' );
define( 'PATHLY_FILE', __FILE__ );
define( 'PATHLY_DIR', __DIR__ );

require_once __DIR__ . '/includes/class-pathly-api-error.php';
require_once __DIR__ . '/includes/class-pathly-http.php';
require_once __DIR__ . '/includes/class-pathly-api.php';
require_once __DIR__ . '/includes/class-pathly-key-store.php';
require_once __DIR__ . '/includes/class-pathly-state.php';
require_once __DIR__ . '/includes/class-pathly-links.php';
require_once __DIR__ . '/includes/class-pathly-ping.php';
require_once __DIR__ . '/includes/class-pathly-flow.php';
require_once __DIR__ . '/includes/class-pathly-replay.php';
require_once __DIR__ . '/includes/class-pathly-dashboard.php';
require_once __DIR__ . '/includes/class-pathly-badge.php';
require_once __DIR__ . '/includes/class-pathly-admin.php';
require_once __DIR__ . '/includes/class-pathly-uninstaller.php';
require_once __DIR__ . '/includes/class-pathly-plugin.php';

register_deactivation_hook( __FILE__, array( 'Pathly_Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'Pathly_Plugin', 'boot' ) );
