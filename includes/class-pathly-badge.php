<?php
/**
 * Availability badge: shortcode and block.
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Off by default. Once the administrator turns it on, Pathly publishes a
 * status page for the Ping and the Flow, and `[pathly_badge]` or the
 * « Pathly badge » block shows its badge. Turned off, the page is unpublished
 * and the shortcode and block print nothing.
 */
class Pathly_Badge {

	const SHORTCODE = 'pathly_badge';

	/**
	 * Registers the shortcode and the block.
	 *
	 * @return void
	 */
	public static function register() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		register_block_type( PATHLY_DIR . '/blocks/badge', array( 'render_callback' => array( __CLASS__, 'render' ) ) );
	}

	/**
	 * Badge markup, or an empty string while the badge is off.
	 *
	 * @return string
	 */
	public static function render() {
		$badge = Pathly_Links::https_or_empty( Pathly_State::get( 'badge_url', '' ) );
		$page  = Pathly_Links::https_or_empty( Pathly_State::get( 'status_url', '' ) );
		if ( ! Pathly_State::get( 'badge_enabled', false ) || '' === $badge || '' === $page ) {
			return '';
		}
		return sprintf(
			'<a class="pathly-badge" href="%1$s" target="_blank" rel="noopener"><img src="%2$s" alt="%3$s" height="20" loading="lazy"></a>',
			esc_url( $page ),
			esc_url( $badge ),
			esc_attr__( 'Site status, by Pathly', 'pathly' )
		);
	}

	/**
	 * Turns the badge on: creates or republishes the status page.
	 *
	 * @param Pathly_Api $api API.
	 * @return void
	 * @throws Pathly_Api_Error When the API refuses.
	 */
	public static function enable( Pathly_Api $api ) {
		$page_id = (string) Pathly_State::get( 'status_page_id', '' );
		if ( '' === $page_id ) {
			$page = $api->create_status_page( array( 'public' => true ) );
		} else {
			$page = $api->update_status_page( $page_id, array( 'public' => true ) );
		}
		Pathly_State::update(
			array(
				'badge_enabled'  => true,
				'status_page_id' => isset( $page['id'] ) ? (string) $page['id'] : $page_id,
				'status_url'     => Pathly_Links::https_or_empty( isset( $page['url'] ) ? $page['url'] : '' ),
				'badge_url'      => Pathly_Links::https_or_empty( isset( $page['badgeUrl'] ) ? $page['badgeUrl'] : '' ),
			)
		);
	}

	/**
	 * Turns the badge off and unpublishes the status page.
	 *
	 * @param Pathly_Api $api API.
	 * @return void
	 * @throws Pathly_Api_Error When the API refuses.
	 */
	public static function disable( Pathly_Api $api ) {
		$page_id = (string) Pathly_State::get( 'status_page_id', '' );
		Pathly_State::update( array( 'badge_enabled' => false ) );
		if ( '' !== $page_id ) {
			$api->update_status_page( $page_id, array( 'public' => false ) );
		}
	}
}
