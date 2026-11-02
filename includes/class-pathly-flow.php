<?php
/**
 * Flow of the site, prepared by Lumi.
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Asks Lumi (the Pathly Flow assistant) to prepare the Flow of the site, then
 * saves the proposal the administrator validates. Lumi replays every proposal
 * once before showing it.
 */
class Pathly_Flow {

	/** Trial states that are still running. */
	const PENDING = array( 'queued', 'exploring', 'waiting', 'replaying' );

	/** Usual slugs of a contact page, in the four Pathly languages. */
	const CONTACT_SLUGS = array( 'contact', 'contact-us', 'contactez-nous', 'nous-contacter', 'contacto', 'kontakt' );

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
	 * Flow worth watching on this site: checkout with WooCommerce, otherwise
	 * the contact form when a contact page exists, otherwise the login.
	 *
	 * @return string `cart`, `contact` or `login`.
	 */
	public static function goal() {
		if ( class_exists( 'WooCommerce' ) ) {
			return 'cart';
		}
		foreach ( self::CONTACT_SLUGS as $slug ) {
			if ( get_page_by_path( $slug ) ) {
				return 'contact';
			}
		}
		return 'login';
	}

	/**
	 * Starts a Lumi trial on the site.
	 *
	 * @param string $site_url Home page address.
	 * @return string Trial id.
	 * @throws Pathly_Api_Error When Lumi is not available or the API refuses.
	 */
	public function start( $site_url ) {
		$detect = $this->api->assistant_detect( $site_url );
		if ( empty( $detect['available'] ) ) {
			$reason = isset( $detect['reason'] ) ? (string) $detect['reason'] : '';
			throw new Pathly_Api_Error( esc_html( self::unavailable_message( $reason ) ), 0, esc_html( $reason ) );
		}
		try {
			$trial = $this->api->assistant_start( $site_url );
		} catch ( Pathly_Api_Error $e ) {
			$reasons = array(
				'assistant_quota' => 'quota',
				'assistant_off'   => 'off',
				'assistant_busy'  => 'busy',
				'url_blocked'     => 'url_blocked',
			);
			if ( ! isset( $reasons[ $e->api_code() ] ) ) {
				throw $e;
			}
			throw new Pathly_Api_Error( esc_html( self::unavailable_message( $reasons[ $e->api_code() ] ) ), (int) $e->status(), esc_html( $e->api_code() ) );
		}
		$id = isset( $trial['id'] ) ? (string) $trial['id'] : '';
		Pathly_State::update( array( 'trial_id' => '' === $id ? null : $id ) );
		return $id;
	}

	/**
	 * Message shown when Lumi cannot start.
	 *
	 * @param string $reason Reason sent by the API.
	 * @return string
	 */
	public static function unavailable_message( $reason ) {
		switch ( $reason ) {
			case 'quota':
				return __( 'Lumi has used all its trials for today on your plan. Try again tomorrow.', 'pathly' );
			case 'busy':
				return __( 'Lumi is very busy right now. Try again in a minute.', 'pathly' );
			case 'unreachable':
			case 'url_blocked':
				return __( 'Pathly cannot reach this site from the internet. A site in maintenance mode, behind a password or on a local address cannot be watched.', 'pathly' );
			default:
				return __( 'Lumi is not available right now. You can create the Flow in Pathly.', 'pathly' );
		}
	}

	/**
	 * State of the current trial, proposals first in the order that suits the site.
	 *
	 * @return array{status: string, proposals: array}|null Null without a trial.
	 * @throws Pathly_Api_Error When the API refuses.
	 */
	public function current() {
		$id = (string) Pathly_State::get( 'trial_id', '' );
		if ( '' === $id ) {
			return null;
		}
		$answer    = $this->api->assistant_trial( $id );
		$item      = isset( $answer['item'] ) && is_array( $answer['item'] ) ? $answer['item'] : $answer;
		$status    = isset( $item['status'] ) ? (string) $item['status'] : 'failed';
		$proposals = isset( $item['proposals'] ) && is_array( $item['proposals'] ) ? $item['proposals'] : array();
		$goal      = self::goal();
		usort(
			$proposals,
			static function ( $a, $b ) use ( $goal ) {
				return self::rank( $a, $goal ) - self::rank( $b, $goal );
			}
		);
		return array(
			'status'    => in_array( $status, self::PENDING, true ) ? 'pending' : $status,
			'proposals' => $proposals,
		);
	}

	/**
	 * Sort rank of a proposal: the Flow that suits the site comes first.
	 *
	 * @param array  $proposal Proposal.
	 * @param string $goal     Preferred kind.
	 * @return int
	 */
	private static function rank( $proposal, $goal ) {
		return isset( $proposal['kind'] ) && $proposal['kind'] === $goal ? 0 : 1;
	}

	/**
	 * Saves one proposal as the Flow of the site.
	 *
	 * @param int    $index    Proposal index.
	 * @param string $site_url Home page address.
	 * @return string Flow id.
	 * @throws Pathly_Api_Error When there is no trial or the API refuses.
	 */
	public function save( $index, $site_url ) {
		$id = (string) Pathly_State::get( 'trial_id', '' );
		if ( '' === $id ) {
			throw new Pathly_Api_Error( esc_html__( 'Ask Lumi to prepare the Flow first.', 'pathly' ) );
		}
		try {
			$saved   = $this->api->assistant_save( $id, $index, 'Flow · ' . Pathly_Ping::normalize( $site_url ) );
			$item    = isset( $saved['item'] ) && is_array( $saved['item'] ) ? $saved['item'] : $saved;
			$flow_id = isset( $item['id'] ) ? (string) $item['id'] : '';
		} catch ( Pathly_Api_Error $e ) {
			// Saved already, from another tab or a second click: keep the existing Flow.
			if ( 'already_saved' !== $e->api_code() || '' === $e->field( 'monitorId' ) ) {
				throw $e;
			}
			$flow_id = $e->field( 'monitorId' );
		}
		Pathly_State::update(
			array(
				'flow_id'  => '' === $flow_id ? null : $flow_id,
				'trial_id' => null,
			)
		);
		return $flow_id;
	}

	/**
	 * Forgets the current trial.
	 *
	 * @return void
	 */
	public static function cancel() {
		Pathly_State::update( array( 'trial_id' => null ) );
	}
}
