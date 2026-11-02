<?php
/**
 * What the plugin remembers about the Pathly account.
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

/**
 * One non-autoloaded option holding identifiers only: Ping, Flow, Lumi trial,
 * status page and badge settings. No personal data.
 */
class Pathly_State {

	const OPTION = 'pathly_state';

	/**
	 * Whole state.
	 *
	 * @return array
	 */
	public static function all() {
		$value = get_option( self::OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * One value.
	 *
	 * @param string $name    Name.
	 * @param mixed  $fallback Value when absent.
	 * @return mixed
	 */
	public static function get( $name, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $name, $all ) ? $all[ $name ] : $fallback;
	}

	/**
	 * Sets several values; a null value removes the entry.
	 *
	 * @param array $values Values by name.
	 * @return void
	 */
	public static function update( array $values ) {
		$all = self::all();
		foreach ( $values as $name => $value ) {
			if ( null === $value ) {
				unset( $all[ $name ] );
			} else {
				$all[ $name ] = $value;
			}
		}
		update_option( self::OPTION, $all, false );
	}

	/**
	 * Forgets everything.
	 *
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}
}
