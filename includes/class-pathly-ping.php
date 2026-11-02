<?php
/**
 * Ping on the site home page.
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates the Ping of the site, or attaches to an existing one, without duplicates.
 */
class Pathly_Ping {

	/** Every five minutes: allowed on every plan, the free one included. */
	const INTERVAL_SEC = 300;

	/** Pages of scenarios read when looking for an existing Ping. */
	const MAX_PAGES = 10;

	/**
	 * API.
	 *
	 * @var Pathly_Api
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param Pathly_Api $api API.
	 */
	public function __construct( Pathly_Api $api ) {
		$this->api = $api;
	}

	/**
	 * Comparable form of an address: host without `www.` and path without trailing slash.
	 *
	 * @param string $url Address.
	 * @return string
	 */
	public static function normalize( $url ) {
		$parts = wp_parse_url( strtolower( trim( (string) $url ) ) );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$host = preg_replace( '/^www\./', '', $parts['host'] );
		$path = isset( $parts['path'] ) ? rtrim( $parts['path'], '/' ) : '';
		return $host . $path;
	}

	/**
	 * Makes sure the site has its Ping and returns its id.
	 *
	 * @param string $site_url Home page address.
	 * @return string Ping id.
	 * @throws Pathly_Api_Error When the API refuses.
	 */
	public function ensure( $site_url ) {
		$known = (string) Pathly_State::get( 'ping_id', '' );
		if ( '' !== $known ) {
			try {
				$this->api->get_scenario( $known );
				return $known;
			} catch ( Pathly_Api_Error $e ) {
				if ( ! $e->is_not_found() ) {
					throw $e;
				}
			}
		}

		$id = $this->find( $site_url );
		if ( '' === $id ) {
			$created = $this->api->create_scenario(
				array(
					'name'        => 'Ping · ' . self::normalize( $site_url ),
					'type'        => 'http',
					'url'         => $site_url,
					'intervalSec' => self::INTERVAL_SEC,
					'tags'        => array( 'wordpress' ),
				),
				'wp-ping-' . substr( hash( 'sha256', self::normalize( $site_url ) ), 0, 40 )
			);
			$id      = isset( $created['id'] ) ? (string) $created['id'] : '';
		}
		Pathly_State::update( array( 'ping_id' => '' === $id ? null : $id ) );
		return $id;
	}

	/**
	 * Id of an existing Ping on the same address, or an empty string.
	 *
	 * @param string $site_url Home page address.
	 * @return string
	 */
	private function find( $site_url ) {
		$target = self::normalize( $site_url );
		$cursor = '';
		for ( $page = 0; $page < self::MAX_PAGES; $page++ ) {
			$result = $this->api->list_scenarios( $cursor );
			$items  = isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
			foreach ( $items as $item ) {
				if ( isset( $item['type'], $item['url'], $item['id'] ) && 'http' === $item['type'] && self::normalize( $item['url'] ) === $target ) {
					return (string) $item['id'];
				}
			}
			$cursor = isset( $result['nextCursor'] ) && is_string( $result['nextCursor'] ) ? $result['nextCursor'] : '';
			if ( '' === $cursor ) {
				break;
			}
		}
		return '';
	}
}
