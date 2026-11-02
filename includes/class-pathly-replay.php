<?php
/**
 * Replays the Flow after an update.
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

/**
 * After WordPress, a plugin or a theme is updated, the Flow is replayed: an
 * update that breaks the checkout is seen in minutes, not by a customer.
 *
 * The run is queued with WP-Cron two minutes later, so the update itself is
 * never slowed down and a bulk update triggers a single run.
 */
class Pathly_Replay {

	const HOOK = 'pathly_replay_flow';

	const DELAY = 120;

	/** Updates that can break a page. Translations cannot. */
	const TYPES = array( 'core', 'plugin', 'theme' );

	/**
	 * Listener of `upgrader_process_complete`.
	 *
	 * @param mixed $upgrader Upgrader instance (unused).
	 * @param mixed $extra    Details of the update.
	 * @return void
	 */
	public static function on_upgrade( $upgrader, $extra ) {
		unset( $upgrader );
		if ( ! is_array( $extra ) || ! isset( $extra['action'], $extra['type'] ) ) {
			return;
		}
		if ( 'update' !== $extra['action'] || ! in_array( $extra['type'], self::TYPES, true ) ) {
			return;
		}
		if ( '' === (string) Pathly_State::get( 'flow_id', '' ) || ! Pathly_Key_Store::has() ) {
			return;
		}
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + self::DELAY, self::HOOK );
		}
	}

	/**
	 * Listener of the scheduled event: queues a run of the Flow in Pathly.
	 *
	 * @param Pathly_Api|null $api API, built from the saved key when null.
	 * @return void
	 */
	public static function run( $api = null ) {
		$flow_id = (string) Pathly_State::get( 'flow_id', '' );
		$key     = Pathly_Key_Store::get();
		if ( '' === $flow_id || '' === $key ) {
			return;
		}
		$api = $api instanceof Pathly_Api ? $api : new Pathly_Api( new Pathly_Http( $key ) );
		try {
			$api->run_scenario( $flow_id );
			Pathly_State::update(
				array(
					'replay_at'    => time(),
					'replay_error' => null,
				)
			);
		} catch ( Pathly_Api_Error $e ) {
			Pathly_State::update(
				array(
					'replay_at'    => time(),
					'replay_error' => $e->getMessage(),
				)
			);
		}
		delete_transient( Pathly_Dashboard::CACHE );
	}

	/**
	 * Removes the scheduled event.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
