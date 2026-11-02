<?php
/**
 * Called by WordPress when the plugin is deleted.
 *
 * @package Pathly
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-pathly-key-store.php';
require_once __DIR__ . '/includes/class-pathly-state.php';
require_once __DIR__ . '/includes/class-pathly-dashboard.php';
require_once __DIR__ . '/includes/class-pathly-replay.php';
require_once __DIR__ . '/includes/class-pathly-uninstaller.php';

Pathly_Uninstaller::run();
