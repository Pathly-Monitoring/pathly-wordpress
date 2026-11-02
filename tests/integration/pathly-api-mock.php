<?php
/**
 * Plugin Name: Pathly API mock (integration tests only)
 * Description: Answers the calls to https://api.pathlyhq.com and records them. Loaded by wp-env only, never shipped.
 *
 * @package Pathly
 */

if ( ! defined( 'PATHLY_API_MOCK' ) || ! PATHLY_API_MOCK ) {
	return;
}

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, 'https://api.pathlyhq.com/' ) ) {
			return $pre;
		}
		$path   = (string) substr( $url, strlen( 'https://api.pathlyhq.com' ) );
		$method = isset( $args['method'] ) ? $args['method'] : 'GET';
		$body   = isset( $args['body'] ) ? json_decode( (string) $args['body'], true ) : null;
		$auth   = isset( $args['headers']['Authorization'] ) ? (string) $args['headers']['Authorization'] : '';

		$calls   = get_option( 'pathly_mock_calls', array() );
		$calls[] = array(
			'method'     => $method,
			'path'       => $path,
			'body'       => $body,
			'bearer'     => 0 === strpos( $auth, 'Bearer sp_' ),
			'user_agent' => isset( $args['user-agent'] ) ? $args['user-agent'] : '',
			'idem'       => isset( $args['headers']['Idempotency-Key'] ) ? $args['headers']['Idempotency-Key'] : '',
		);
		update_option( 'pathly_mock_calls', $calls, false );

		$reply = static function ( $code, $data ) {
			return array(
				'headers'  => array( 'content-type' => 'application/json' ),
				'body'     => wp_json_encode( $data ),
				'response' => array(
					'code'    => $code,
					'message' => '',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		if ( 'Bearer sp_refused_0123456789abcdef' === $auth ) {
			return $reply( 401, array( 'error' => 'Clé d\'API inconnue' ) );
		}

		$route = $method . ' ' . strtok( $path, '?' );
		switch ( $route ) {
			case 'GET /v1/scenarios':
				return $reply( 200, array( 'items' => array() ) );
			case 'POST /v1/scenarios':
				return $reply( 201, array_merge( array( 'id' => 'ping-1' ), (array) $body ) );
			case 'GET /v1/scenarios/ping-1':
				return $reply( 200, array( 'id' => 'ping-1', 'type' => 'http', 'lastStatus' => 'ok' ) );
			case 'GET /v1/scenarios/flow-1':
				return $reply( 200, array( 'id' => 'flow-1', 'type' => 'browser', 'lastStatus' => 'fail' ) );
			case 'POST /v1/scenarios/flow-1/run':
				return $reply( 200, array( 'ok' => true, 'queued' => true, 'jobId' => 'run-2' ) );
			case 'GET /v1/runs':
				return $reply( 200, array( 'items' => array( array( 'id' => 'run-1', 'status' => 'fail', 'created_at' => '2026-11-02T09:00:00Z' ) ) ) );
			case 'GET /v1/runs/run-1':
				return $reply(
					200,
					array(
						'id'            => 'run-1',
						'status'        => 'fail',
						'message'       => 'Step 4: button not found',
						'created_at'    => '2026-11-02T09:00:00Z',
						'screenshotUrl' => 'https://storage.example/run-1.png?signature=test',
					)
				);
			case 'GET /v1/account':
				return $reply(
					200,
					array(
						'organization' => array( 'id' => 'org-1', 'name' => 'Test agency' ),
						'space'        => array( 'id' => 'space-1', 'name' => 'Test shop' ),
						'plan'         => array( 'id' => 'pro', 'name' => 'Pro', 'priceMonthlyEur' => 69 ),
						'billingUrl'   => 'https://pathlyhq.com/fr/billing',
						'quotas'       => array(
							'ping'            => array( 'used' => 1, 'limit' => 50, 'remaining' => 49 ),
							'flow'            => array( 'used' => 1, 'limit' => null, 'remaining' => null ),
							'assistantTrials' => array( 'used' => 1, 'limit' => 15, 'remaining' => 14 ),
						),
					)
				);
			case 'POST /v1/flow-assistant/detect':
				return $reply( 200, array( 'available' => true, 'siteKind' => 'woocommerce' ) );
			case 'POST /v1/flow-assistant/trials':
				if ( array( 'url', 'confirmOwner' ) !== array_keys( (array) $body ) || true !== $body['confirmOwner'] ) {
					return $reply( 400, array( 'error' => 'Paramètres invalides', 'code' => 'confirm_owner' ) );
				}
				return $reply( 202, array( 'id' => 'trial-1' ) );
			case 'GET /v1/flow-assistant/trials/trial-1':
				return $reply(
					200,
					array(
						'item' => array(
							'status'    => 'ready',
							'proposals' => array(
								array( 'index' => 0, 'kind' => 'cart', 'steps' => array( array(), array(), array() ) ),
							),
						),
					)
				);
			case 'POST /v1/flow-assistant/trials/trial-1/save':
				return $reply( 201, array( 'item' => array( 'id' => 'flow-1', 'name' => 'Flow' ) ) );
			case 'POST /v1/status-pages':
				return $reply(
					201,
					array(
						'id'       => 'page-1',
						'kind'     => 'space',
						'spaceId'  => 'space-1',
						'name'     => 'Test shop',
						'slug'     => 'test-shop',
						'public'   => true,
						'url'      => 'https://pathlyhq.com/status/test-shop',
						'badgeUrl' => 'https://api.pathlyhq.com/public/status/test-shop/badge.svg',
					)
				);
			case 'PATCH /v1/status-pages/page-1':
				return $reply( 200, array( 'id' => 'page-1', 'public' => (bool) ( isset( $body['public'] ) ? $body['public'] : false ) ) );
		}
		return $reply( 404, array( 'error' => 'Ressource introuvable' ) );
	},
	10,
	3
);
