<?php
/**
 * Pathly public API (/v1) calls used by the plugin.
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

/**
 * One method per route. Every route the plugin uses is listed here, so the
 * contract can be checked in a single file.
 */
class Pathly_Api {

	/**
	 * Transport.
	 *
	 * @var Pathly_Http
	 */
	private $http;

	/**
	 * Constructor.
	 *
	 * @param Pathly_Http $http Transport.
	 */
	public function __construct( Pathly_Http $http ) {
		$this->http = $http;
	}

	/**
	 * Organization, plan and quotas. `GET /v1/account`.
	 *
	 * @return array
	 */
	public function account() {
		return $this->http->request( 'GET', '/v1/account' );
	}

	/**
	 * One page of scenarios. `GET /v1/scenarios`.
	 *
	 * @param string $cursor Cursor of the previous page, or an empty string.
	 * @return array{items: array, nextCursor?: string|null}
	 */
	public function list_scenarios( $cursor = '' ) {
		$query = array( 'limit' => 100 );
		if ( '' !== $cursor ) {
			$query['cursor'] = $cursor;
		}
		return $this->http->request( 'GET', '/v1/scenarios?' . http_build_query( $query ) );
	}

	/**
	 * One scenario. `GET /v1/scenarios/{id}`.
	 *
	 * @param string $id Scenario id.
	 * @return array
	 */
	public function get_scenario( $id ) {
		return $this->http->request( 'GET', '/v1/scenarios/' . rawurlencode( $id ) );
	}

	/**
	 * Creates a scenario. `POST /v1/scenarios`, replayed for 24 h with the same idempotency key.
	 *
	 * @param array  $data            Body.
	 * @param string $idempotency_key Idempotency key.
	 * @return array
	 */
	public function create_scenario( array $data, $idempotency_key ) {
		return $this->http->request( 'POST', '/v1/scenarios', $data, array( 'Idempotency-Key' => $idempotency_key ) );
	}

	/**
	 * Queues a run. `POST /v1/scenarios/{id}/run`.
	 *
	 * @param string $id Scenario id.
	 * @return array
	 */
	public function run_scenario( $id ) {
		return $this->http->request( 'POST', '/v1/scenarios/' . rawurlencode( $id ) . '/run', array() );
	}

	/**
	 * Latest runs of a scenario. `GET /v1/runs?scenarioId=`.
	 *
	 * @param string $scenario_id Scenario id.
	 * @param int    $limit       Page size.
	 * @return array{items: array}
	 */
	public function list_runs( $scenario_id, $limit = 20 ) {
		return $this->http->request(
			'GET',
			'/v1/runs?' . http_build_query(
				array(
					'scenarioId' => $scenario_id,
					'limit'      => $limit,
				)
			)
		);
	}

	/**
	 * One run, with the signed URL of the failed step screenshot (#662). `GET /v1/runs/{id}`.
	 *
	 * @param string $id Run id.
	 * @return array
	 */
	public function get_run( $id ) {
		return $this->http->request( 'GET', '/v1/runs/' . rawurlencode( $id ) );
	}

	/**
	 * Lumi: is the assistant available for this address? `POST /v1/flow-assistant/detect` (#662).
	 *
	 * @param string $url Site address.
	 * @return array
	 */
	public function assistant_detect( $url ) {
		return $this->http->request( 'POST', '/v1/flow-assistant/detect', array( 'url' => $url ) );
	}

	/**
	 * Lumi: starts a trial on the site. `POST /v1/flow-assistant/trials` (#662).
	 *
	 * The administrator who installed the plugin manages the site: `confirmOwner`.
	 *
	 * @param string $url Site address.
	 * @return array{id: string}
	 */
	public function assistant_start( $url ) {
		return $this->http->request(
			'POST',
			'/v1/flow-assistant/trials',
			array(
				'url'          => $url,
				'confirmOwner' => true,
			)
		);
	}

	/**
	 * Lumi: trial state and proposals. `GET /v1/flow-assistant/trials/{id}` (#662).
	 *
	 * @param string $id Trial id.
	 * @return array
	 */
	public function assistant_trial( $id ) {
		return $this->http->request( 'GET', '/v1/flow-assistant/trials/' . rawurlencode( $id ) );
	}

	/**
	 * Lumi: saves one proposal as a Flow. `POST /v1/flow-assistant/trials/{id}/save` (#662).
	 *
	 * @param string $id    Trial id.
	 * @param int    $index Proposal index.
	 * @param string $name  Flow name.
	 * @return array
	 */
	public function assistant_save( $id, $index, $name ) {
		return $this->http->request(
			'POST',
			'/v1/flow-assistant/trials/' . rawurlencode( $id ) . '/save',
			array(
				'index' => (int) $index,
				'name'  => $name,
			)
		);
	}

	/**
	 * Creates a status page. `POST /v1/status-pages` (#662), body `{spaceId?, slug?, public?}`.
	 *
	 * @param array $data Body.
	 * @return array
	 */
	public function create_status_page( array $data ) {
		return $this->http->request( 'POST', '/v1/status-pages', $data );
	}

	/**
	 * Publishes or unpublishes a status page. `PATCH /v1/status-pages/{id}` (#662), body `{public?, slug?}`.
	 *
	 * @param string $id   Status page id.
	 * @param array  $data Body.
	 * @return array
	 */
	public function update_status_page( $id, array $data ) {
		return $this->http->request( 'PATCH', '/v1/status-pages/' . rawurlencode( $id ), $data );
	}
}
