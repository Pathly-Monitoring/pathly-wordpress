<?php
/**
 * Clean removal.
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Deletes every option, transient and scheduled event of the plugin, on each
 * site of a network. The Ping and the Flow stay in the Pathly account.
 */
class Pathly_Uninstaller {

	/**
	 * Removes the plugin data of the current site.
	 *
	 * @return void
	 */
	public static function clean_site() {
		Pathly_Key_Store::clear();
		Pathly_State::clear();
		delete_transient( Pathly_Dashboard::CACHE );
		Pathly_Replay::unschedule();
	}

	/**
	 * Removes the plugin data everywhere.
	 *
	 * @return void
	 */
	public static function run() {
		if ( ! is_multisite() ) {
			self::clean_site();
			return;
		}
		foreach ( get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) as $site_id ) {
			switch_to_blog( $site_id );
			self::clean_site();
			restore_current_blog();
		}
	}
}
