<?php
/**
 * Pathly box on the WordPress dashboard.
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

/**
 * State of the Ping and the Flow, last failure with its screenshot, plan and
 * quotas. Read from the API at most every five minutes.
 */
class Pathly_Dashboard {

	const CACHE = 'pathly_dashboard';

	const CACHE_TTL = 300;

	/**
	 * API factory, replaced in tests.
	 *
	 * @var callable|null
	 */
	private $factory;

	/**
	 * Constructor.
	 *
	 * @param callable|null $factory Returns a Pathly_Api for a key.
	 */
	public function __construct( $factory = null ) {
		$this->factory = $factory;
	}

	/**
	 * Registers the box for administrators.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'pathly_dashboard', 'Pathly', array( $this, 'render' ) );
	}

	/**
	 * Builds the API client.
	 *
	 * @param string $key API key.
	 * @return Pathly_Api
	 */
	private function api( $key ) {
		return $this->factory ? call_user_func( $this->factory, $key ) : new Pathly_Api( new Pathly_Http( $key ) );
	}

	/**
	 * Data shown in the box, cached for five minutes.
	 *
	 * @param string $key API key.
	 * @return array
	 */
	public function data( $key ) {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$api      = $this->api( $key );
		$ping_id  = (string) Pathly_State::get( 'ping_id', '' );
		$flow_id  = (string) Pathly_State::get( 'flow_id', '' );
		$data     = array(
			'ping'    => null,
			'flow'    => null,
			'failure' => null,
			'account' => null,
			'error'   => '',
		);
		$attempts = array(
			'ping'    => function () use ( $api, $ping_id ) {
				return '' === $ping_id ? null : $this->status_of( $api->get_scenario( $ping_id ) );
			},
			'flow'    => function () use ( $api, $flow_id ) {
				return '' === $flow_id ? null : $this->status_of( $api->get_scenario( $flow_id ) );
			},
			'failure' => function () use ( $api, $flow_id ) {
				return '' === $flow_id ? null : $this->last_failure( $api, $flow_id );
			},
			'account' => function () use ( $api ) {
				return $api->account();
			},
		);
		foreach ( $attempts as $name => $attempt ) {
			try {
				$data[ $name ] = $attempt();
			} catch ( Pathly_Api_Error $e ) {
				// A route not deployed yet (404) leaves its part empty; anything else is shown once.
				if ( ! $e->is_not_found() && '' === $data['error'] ) {
					$data['error'] = $e->getMessage();
				}
			}
		}
		set_transient( self::CACHE, $data, self::CACHE_TTL );
		return $data;
	}

	/**
	 * Last status of a scenario.
	 *
	 * @param array $scenario Scenario.
	 * @return string `ok`, `fail`, `error` or an empty string before the first run.
	 */
	private function status_of( array $scenario ) {
		return isset( $scenario['lastStatus'] ) && is_string( $scenario['lastStatus'] ) ? $scenario['lastStatus'] : '';
	}

	/**
	 * Date, message and screenshot of the last failed run of the Flow.
	 *
	 * @param Pathly_Api $api     API.
	 * @param string     $flow_id Flow id.
	 * @return array|null
	 */
	private function last_failure( Pathly_Api $api, $flow_id ) {
		$runs  = $api->list_runs( $flow_id );
		$items = isset( $runs['items'] ) && is_array( $runs['items'] ) ? $runs['items'] : array();
		foreach ( $items as $run ) {
			if ( isset( $run['id'], $run['status'] ) && in_array( $run['status'], array( 'fail', 'error' ), true ) ) {
				$detail = $api->get_run( (string) $run['id'] );
				$at     = isset( $detail['created_at'] ) ? $detail['created_at'] : ( isset( $run['created_at'] ) ? $run['created_at'] : '' );
				return array(
					'at'         => (string) $at,
					'message'    => isset( $detail['message'] ) ? (string) $detail['message'] : '',
					'screenshot' => Pathly_Links::https_or_empty( isset( $detail['screenshotUrl'] ) ? $detail['screenshotUrl'] : '' ),
				);
			}
		}
		return null;
	}

	/**
	 * Human label of a status.
	 *
	 * @param string|null $status Status.
	 * @return array{0: string, 1: string} Label and CSS modifier.
	 */
	public static function label( $status ) {
		if ( null === $status ) {
			return array( __( 'Not set up', 'pathly' ), 'none' );
		}
		if ( 'ok' === $status ) {
			return array( __( 'OK', 'pathly' ), 'ok' );
		}
		if ( 'fail' === $status || 'error' === $status ) {
			return array( __( 'Failing', 'pathly' ), 'fail' );
		}
		return array( __( 'Waiting for the first run', 'pathly' ), 'none' );
	}

	/**
	 * Prints the box.
	 *
	 * @return void
	 */
	public function render() {
		$key = Pathly_Key_Store::get();
		echo '<div class="pathly-box">';
		if ( '' === $key ) {
			echo '<p>' . esc_html__( 'Connect your site to Pathly to see its Ping and its Flow here.', 'pathly' ) . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'options-general.php?page=pathly' ) ) . '">' . esc_html__( 'Connect to Pathly', 'pathly' ) . '</a></p>';
			echo '</div>';
			return;
		}
		$data = $this->data( $key );
		if ( '' !== $data['error'] ) {
			echo '<p class="pathly-error">' . esc_html( $data['error'] ) . '</p>';
		}
		echo '<ul class="pathly-status">';
		foreach ( array(
			'Ping' => $data['ping'],
			'Flow' => $data['flow'],
		) as $name => $status ) {
			list( $label, $modifier ) = self::label( $status );
			echo '<li><strong>' . esc_html( $name ) . '</strong> <span class="pathly-pill pathly-pill--' . esc_attr( $modifier ) . '">' . esc_html( $label ) . '</span></li>';
		}
		echo '</ul>';
		$this->render_failure( $data['failure'] );
		$this->render_account( $data['account'] );
		echo '<p><a href="' . esc_url( Pathly_Links::app() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open Pathly', 'pathly' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Prints the last failure.
	 *
	 * @param array|null $failure Last failure.
	 * @return void
	 */
	private function render_failure( $failure ) {
		if ( null === $failure ) {
			return;
		}
		$time = strtotime( $failure['at'] );
		$when = false === $time ? $failure['at'] : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time );
		/* translators: %s: date and time of the failure. */
		echo '<p><strong>' . esc_html( sprintf( __( 'Last Flow failure: %s', 'pathly' ), $when ) ) . '</strong></p>';
		if ( '' !== $failure['message'] ) {
			echo '<p>' . esc_html( $failure['message'] ) . '</p>';
		}
		if ( '' !== $failure['screenshot'] ) {
			echo '<p><img class="pathly-shot" src="' . esc_url( $failure['screenshot'] ) . '" alt="' . esc_attr__( 'Screenshot of the step that failed', 'pathly' ) . '" loading="lazy"></p>';
		}
	}

	/**
	 * Prints plan and quotas.
	 *
	 * @param array|null $account Answer of GET /v1/account.
	 * @return void
	 */
	private function render_account( $account ) {
		if ( ! is_array( $account ) ) {
			return;
		}
		$plan = isset( $account['plan']['name'] ) ? (string) $account['plan']['name'] : '';
		if ( '' !== $plan ) {
			/* translators: %s: name of the Pathly plan. */
			echo '<p>' . esc_html( sprintf( __( 'Plan: %s', 'pathly' ), $plan ) ) . '</p>';
		}
		$quotas = isset( $account['quotas'] ) && is_array( $account['quotas'] ) ? $account['quotas'] : array();
		if ( array() !== $quotas ) {
			echo '<ul class="pathly-quotas">';
			$labels = array(
				'ping'            => 'Ping',
				'flow'            => 'Flow',
				'chain'           => 'Chain',
				'runs'            => __( 'Runs', 'pathly' ),
				'assistantTrials' => __( 'Lumi trials today', 'pathly' ),
				'spaces'          => __( 'Spaces', 'pathly' ),
			);
			foreach ( $quotas as $name => $quota ) {
				if ( ! is_array( $quota ) || ! isset( $quota['used'] ) || ! array_key_exists( 'limit', $quota ) ) {
					continue;
				}
				$label = isset( $labels[ $name ] ) ? $labels[ $name ] : $name;
				// A null limit means unlimited.
				$limit = null === $quota['limit'] ? __( 'unlimited', 'pathly' ) : $quota['limit'];
				/* translators: 1: quota name, 2: amount used, 3: limit. */
				echo '<li>' . esc_html( sprintf( __( '%1$s: %2$s / %3$s', 'pathly' ), $label, $quota['used'], $limit ) ) . '</li>';
			}
			echo '</ul>';
		}
		$billing = Pathly_Links::https_or_empty( isset( $account['billingUrl'] ) ? $account['billingUrl'] : '' );
		if ( '' !== $billing ) {
			echo '<p><a href="' . esc_url( $billing ) . '" target="_blank" rel="noopener">' . esc_html__( 'See plans', 'pathly' ) . '</a></p>';
		}
	}
}
