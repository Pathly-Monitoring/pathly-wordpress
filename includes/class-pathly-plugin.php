<?php
/**
 * Wiring of the plugin.
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers every hook. Nothing here talks to the network.
 */
class Pathly_Plugin {

	/**
	 * Hooks, called on `plugins_loaded`.
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'init', array( 'Pathly_Badge', 'register' ) );
		add_action( 'upgrader_process_complete', array( 'Pathly_Replay', 'on_upgrade' ), 10, 2 );
		add_action( Pathly_Replay::HOOK, array( 'Pathly_Replay', 'run' ), 10, 0 );
		if ( is_admin() ) {
			( new Pathly_Admin() )->hooks();
			add_action( 'wp_dashboard_setup', array( new Pathly_Dashboard(), 'register' ) );
		}
	}

	/**
	 * Translations shipped with the plugin (French, Spanish, German).
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'pathly', false, dirname( plugin_basename( PATHLY_FILE ) ) . '/languages' );
	}

	/**
	 * Deactivation: no replay is left scheduled.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Pathly_Replay::unschedule();
	}
}
