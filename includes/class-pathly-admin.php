<?php
/**
 * Settings screen and its actions.
 *
 * @package Pathly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings → Pathly. Administrators only, every form protected by a nonce.
 */
class Pathly_Admin {

	const PAGE = 'pathly';

	const ACTIONS = array( 'connect', 'disconnect', 'flow_start', 'flow_save', 'flow_cancel', 'badge' );

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
	 * Builds the API client.
	 *
	 * @param string $key API key.
	 * @return Pathly_Api
	 */
	private function api( $key ) {
		return $this->factory ? call_user_func( $this->factory, $key ) : new Pathly_Api( new Pathly_Http( $key ) );
	}

	/**
	 * Address watched by Pathly: the home page.
	 *
	 * @return string
	 */
	public static function site_url() {
		return home_url( '/' );
	}

	/**
	 * Settings screen URL.
	 *
	 * @return string
	 */
	public static function page_url() {
		return admin_url( 'options-general.php?page=' . self::PAGE );
	}

	/**
	 * Hooks of the admin side.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PATHLY_FILE ), array( $this, 'action_links' ) );
		foreach ( self::ACTIONS as $action ) {
			add_action(
				'admin_post_pathly_' . $action,
				function () use ( $action ) {
					$this->handle( $action );
				}
			);
		}
	}

	/**
	 * Adds Settings → Pathly.
	 *
	 * @return void
	 */
	public function menu() {
		add_options_page( 'Pathly', 'Pathly', 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	/**
	 * Styles on the settings screen and the dashboard, script on the settings screen.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( 'index.php' !== $hook && 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'pathly-admin', plugins_url( 'assets/admin.css', PATHLY_FILE ), array(), PATHLY_VERSION );
		if ( 'index.php' !== $hook ) {
			wp_enqueue_script( 'pathly-admin', plugins_url( 'assets/admin.js', PATHLY_FILE ), array(), PATHLY_VERSION, true );
		}
	}

	/**
	 * « Settings » link in the plugin list.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Settings', 'pathly' ) . '</a>' );
		return $links;
	}

	/**
	 * Remembers a message for the next display of the screen.
	 *
	 * @param string $type    `success` or `error`.
	 * @param string $message Message.
	 * @return void
	 */
	private function notice( $type, $message ) {
		set_transient(
			'pathly_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
	}

	/**
	 * Posted value, unslashed and sanitized.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	private static function posted( $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in handle() before any read.
		return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
	}

	/**
	 * Runs one form action: capability, nonce, action, message, redirect.
	 *
	 * @param string $action Action name.
	 * @return void
	 */
	public function handle( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change Pathly settings.', 'pathly' ), 403 );
			return;
		}
		check_admin_referer( 'pathly_' . $action );
		try {
			$this->{'do_' . $action}();
		} catch ( Pathly_Api_Error $e ) {
			$this->notice( 'error', $e->getMessage() );
		}
		delete_transient( Pathly_Dashboard::CACHE );
		wp_safe_redirect( self::page_url() );
	}

	/**
	 * Saves the key after Pathly accepted it, then creates or attaches the Ping.
	 *
	 * @return void
	 * @throws Pathly_Api_Error When the key is refused.
	 */
	private function do_connect() {
		$key = self::posted( 'pathly_api_key' );
		if ( '' === $key ) {
			$key = Pathly_Key_Store::get();
		}
		if ( ! Pathly_Key_Store::is_valid_format( $key ) ) {
			throw new Pathly_Api_Error( esc_html__( 'This is not a Pathly API key. It starts with sp_.', 'pathly' ) );
		}
		try {
			( new Pathly_Ping( $this->api( $key ) ) )->ensure( self::site_url() );
		} catch ( Pathly_Api_Error $e ) {
			if ( $e->is_auth() ) {
				throw new Pathly_Api_Error( esc_html__( 'Pathly refused this key. Check that it is active and has the scopes listed below.', 'pathly' ), (int) $e->status() );
			}
			throw $e;
		}
		Pathly_Key_Store::save( $key );
		$this->notice( 'success', __( 'Connected. Pathly now pings your home page every five minutes.', 'pathly' ) );
	}

	/**
	 * Forgets the key and every identifier. Nothing is deleted in Pathly.
	 *
	 * @return void
	 */
	private function do_disconnect() {
		Pathly_Key_Store::clear();
		Pathly_State::clear();
		Pathly_Replay::unschedule();
		$this->notice( 'success', __( 'Disconnected. Your Ping and Flow are still in your Pathly account.', 'pathly' ) );
	}

	/**
	 * The saved key, or an error asking to connect first.
	 *
	 * @return string
	 * @throws Pathly_Api_Error Without a key.
	 */
	private function key() {
		$key = Pathly_Key_Store::get();
		if ( '' === $key ) {
			throw new Pathly_Api_Error( esc_html__( 'Connect your site to Pathly first.', 'pathly' ) );
		}
		return $key;
	}

	/**
	 * Asks Lumi to prepare the Flow.
	 *
	 * @return void
	 */
	private function do_flow_start() {
		( new Pathly_Flow( $this->api( $this->key() ) ) )->start( self::site_url() );
		$this->notice( 'success', __( 'Lumi is exploring your site. It takes one to three minutes.', 'pathly' ) );
	}

	/**
	 * Saves the proposal the administrator validated.
	 *
	 * @return void
	 */
	private function do_flow_save() {
		( new Pathly_Flow( $this->api( $this->key() ) ) )->save( absint( self::posted( 'pathly_index' ) ), self::site_url() );
		$this->notice( 'success', __( 'Flow saved. Pathly runs it now, then after each update of WordPress, a plugin or a theme.', 'pathly' ) );
	}

	/**
	 * Drops the current Lumi trial.
	 *
	 * @return void
	 */
	private function do_flow_cancel() {
		Pathly_Flow::cancel();
	}

	/**
	 * Turns the badge on or off.
	 *
	 * @return void
	 */
	private function do_badge() {
		$api = $this->api( $this->key() );
		if ( '1' === self::posted( 'pathly_badge' ) ) {
			Pathly_Badge::enable( $api );
			$this->notice( 'success', __( 'Badge on. Add the [pathly_badge] shortcode or the Pathly badge block where you want it.', 'pathly' ) );
		} else {
			Pathly_Badge::disable( $api );
			$this->notice( 'success', __( 'Badge off. Its status page is no longer public.', 'pathly' ) );
		}
	}

	/**
	 * Opening tag of a form posting to admin-post.php, with its nonce.
	 *
	 * @param string $action Action name.
	 * @return void
	 */
	private static function form_open( $action ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( 'pathly_' . $action ) . '">';
		wp_nonce_field( 'pathly_' . $action );
	}

	/**
	 * Prints the settings screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$notice = get_transient( 'pathly_notice_' . get_current_user_id() );
		delete_transient( 'pathly_notice_' . get_current_user_id() );
		$key = Pathly_Key_Store::get();

		echo '<div class="wrap pathly-settings">';
		echo '<h1>Pathly</h1>';
		if ( is_array( $notice ) && isset( $notice['type'], $notice['message'] ) ) {
			echo '<div class="notice notice-' . esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ) . '"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Pathly watches the path of your visitors: a Ping checks that the site answers, a Flow walks your checkout, contact form or login in a real browser.', 'pathly' ) . '</p>';

		$this->render_connection( $key );
		if ( '' !== $key ) {
			$this->render_flow( $key );
			$this->render_badge();
		}
		$this->render_service();
		echo '</div>';
	}

	/**
	 * Connection block.
	 *
	 * @param string $key Saved key.
	 * @return void
	 */
	private function render_connection( $key ) {
		echo '<h2>' . esc_html__( 'Connection', 'pathly' ) . '</h2>';
		if ( '' !== $key ) {
			/* translators: %s: masked API key. */
			echo '<p>' . esc_html( sprintf( __( 'Connected with the key %s.', 'pathly' ), Pathly_Key_Store::mask( $key ) ) ) . '</p>';
			if ( '' !== (string) Pathly_State::get( 'ping_id', '' ) ) {
				echo '<p>' . esc_html__( 'Ping: your home page is checked every five minutes.', 'pathly' ) . '</p>';
			}
			self::form_open( 'disconnect' );
			submit_button( __( 'Disconnect', 'pathly' ), 'secondary', 'submit', false );
			echo '</form>';
			return;
		}
		echo '<p>' . esc_html__( 'No account yet? The Solo plan is free, without a card.', 'pathly' ) . ' <a class="button" href="' . esc_url( Pathly_Links::signup() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Create a free account', 'pathly' ) . '</a></p>';
		self::form_open( 'connect' );
		echo '<p><label for="pathly_api_key">' . esc_html__( 'Pathly API key', 'pathly' ) . '</label><br>';
		echo '<input type="password" id="pathly_api_key" name="pathly_api_key" class="regular-text" autocomplete="off" spellcheck="false" placeholder="sp_…"></p>';
		echo '<p class="description">' . esc_html__( 'Create it in Pathly, Settings, Integrations. Prefer a key limited to one Space, with the scopes org:read, scenarios:read, scenarios:write, runs:read, runs:trigger, assistant:run, assistant:save, status:read and status:write.', 'pathly' ) . ' <a href="' . esc_url( Pathly_Links::api_keys() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open API keys', 'pathly' ) . '</a></p>';
		submit_button( __( 'Connect', 'pathly' ), 'primary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Flow block: Lumi trial, proposals, saved Flow.
	 *
	 * @param string $key Saved key.
	 * @return void
	 */
	private function render_flow( $key ) {
		echo '<h2>Flow</h2>';
		if ( '' !== (string) Pathly_State::get( 'flow_id', '' ) ) {
			echo '<p>' . esc_html__( 'Your Flow is saved. Pathly replays it after each update of WordPress, a plugin or a theme.', 'pathly' ) . '</p>';
			$error = (string) Pathly_State::get( 'replay_error', '' );
			if ( '' !== $error ) {
				/* translators: %s: error message. */
				echo '<p class="pathly-error">' . esc_html( sprintf( __( 'Last replay could not start: %s', 'pathly' ), $error ) ) . '</p>';
			}
			return;
		}
		echo '<p>' . esc_html__( 'Lumi, the Pathly assistant, reads your site and prepares a Flow. It replays each proposal once before showing it. It pays nothing, sends no form and only types test values. You validate the Flow.', 'pathly' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'A Flow on a shop adds a product to a test cart at each run. Abandoned cart tools may count these carts or send reminders for them: exclude test@example.com and the Pathly Test name from them.', 'pathly' ) . '</p>';

		try {
			$trial = ( new Pathly_Flow( $this->api( $key ) ) )->current();
		} catch ( Pathly_Api_Error $e ) {
			echo '<p class="pathly-error">' . esc_html( $e->getMessage() ) . '</p>';
			$trial = null;
		}

		if ( null === $trial ) {
			self::form_open( 'flow_start' );
			submit_button( __( 'Ask Lumi to prepare the Flow', 'pathly' ), 'primary', 'submit', false );
			echo '</form>';
			return;
		}
		if ( 'pending' === $trial['status'] ) {
			echo '<p class="pathly-pending" data-pathly-refresh="10">' . esc_html__( 'Lumi is exploring your site. This page refreshes by itself.', 'pathly' ) . '</p>';
			return;
		}
		if ( array() === $trial['proposals'] ) {
			echo '<p>' . esc_html__( 'Lumi found no Flow it could replay on this site. You can record one in Pathly.', 'pathly' ) . '</p>';
		}
		foreach ( $trial['proposals'] as $proposal ) {
			$this->render_proposal( $proposal );
		}
		self::form_open( 'flow_cancel' );
		submit_button( __( 'Start again', 'pathly' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * One proposal with its « Validate » button.
	 *
	 * @param array $proposal Proposal sent by Lumi.
	 * @return void
	 */
	private function render_proposal( $proposal ) {
		$kinds = array(
			'cart'    => __( 'Add to cart, up to the payment page', 'pathly' ),
			'contact' => __( 'Contact form, filled and stopped before sending', 'pathly' ),
			'login'   => __( 'Login', 'pathly' ),
		);
		$kind  = isset( $proposal['kind'] ) ? (string) $proposal['kind'] : '';
		$steps = isset( $proposal['steps'] ) && is_array( $proposal['steps'] ) ? count( $proposal['steps'] ) : 0;
		echo '<div class="pathly-proposal">';
		echo '<p><strong>' . esc_html( isset( $kinds[ $kind ] ) ? $kinds[ $kind ] : __( 'Other path', 'pathly' ) ) . '</strong>';
		if ( $steps > 0 ) {
			/* translators: %d: number of steps. */
			echo ' — ' . esc_html( sprintf( _n( '%d step', '%d steps', $steps, 'pathly' ), $steps ) );
		}
		echo '</p>';
		self::form_open( 'flow_save' );
		echo '<input type="hidden" name="pathly_index" value="' . esc_attr( isset( $proposal['index'] ) ? (string) absint( $proposal['index'] ) : '0' ) . '">';
		submit_button( __( 'Validate this Flow', 'pathly' ), 'primary', 'submit', false );
		echo '</form></div>';
	}

	/**
	 * Badge block.
	 *
	 * @return void
	 */
	private function render_badge() {
		$enabled = (bool) Pathly_State::get( 'badge_enabled', false );
		echo '<h2>' . esc_html__( 'Status badge', 'pathly' ) . '</h2>';
		echo '<p>' . esc_html__( 'Off by default. Turned on, Pathly publishes a status page for your Ping and Flow, and the [pathly_badge] shortcode or the Pathly badge block shows its badge. The badge image is loaded from Pathly by your visitors\' browsers.', 'pathly' ) . '</p>';
		self::form_open( 'badge' );
		echo '<input type="hidden" name="pathly_badge" value="' . ( $enabled ? '0' : '1' ) . '">';
		submit_button( $enabled ? __( 'Turn the badge off', 'pathly' ) : __( 'Turn the badge on', 'pathly' ), 'secondary', 'submit', false );
		echo '</form>';
		if ( $enabled ) {
			echo '<p>' . Pathly_Badge::render() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Pathly_Badge::render().
		}
	}

	/**
	 * What is sent to Pathly, with the terms and privacy links.
	 *
	 * @return void
	 */
	private function render_service() {
		echo '<h2>' . esc_html__( 'Data sent to Pathly', 'pathly' ) . '</h2>';
		echo '<p>' . esc_html__( 'Nothing is sent before you connect a key. Then the plugin sends to api.pathlyhq.com only the address of your site and the versions of WordPress and of the plugin. No customer, order or visitor data.', 'pathly' ) . '</p>';
		echo '<p><a href="' . esc_url( Pathly_Links::terms() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Terms of service', 'pathly' ) . '</a> · <a href="' . esc_url( Pathly_Links::privacy() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Privacy policy', 'pathly' ) . '</a></p>';
	}
}
