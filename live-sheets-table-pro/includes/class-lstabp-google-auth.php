<?php
/**
 * Google account connection.
 *
 * The site owner supplies their own OAuth client from their own Google Cloud
 * project. That is deliberate: routing every customer's sheets through one
 * shared client would make this plugin a data processor for all of them, and
 * would put every site behind one revocable set of credentials.
 *
 * Only the read-only spreadsheets scope is requested, so a token issued here
 * cannot change or delete anything in the account.
 *
 * @package LiveSheetsTablePro
 */

defined( 'ABSPATH' ) || exit;

/**
 * OAuth client and token store.
 */
class LSTABP_Google_Auth {

	const OPTION_CLIENT = 'lstabp_google_client';
	const OPTION_TOKEN  = 'lstabp_google_token';

	const AUTH_ENDPOINT  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
	const SCOPE          = 'https://www.googleapis.com/auth/spreadsheets.readonly';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_lstabp_google_connect', array( $this, 'handle_connect' ) );
		add_action( 'admin_post_lstabp_google_callback', array( $this, 'handle_callback' ) );
		add_action( 'admin_post_lstabp_google_disconnect', array( $this, 'handle_disconnect' ) );
	}

	/**
	 * Stored client credentials.
	 *
	 * @return array{client_id:string,client_secret:string}
	 */
	public static function client() {
		$stored = get_option( self::OPTION_CLIENT, array() );

		return array(
			'client_id'     => isset( $stored['client_id'] ) ? (string) $stored['client_id'] : '',
			'client_secret' => isset( $stored['client_secret'] ) ? (string) $stored['client_secret'] : '',
		);
	}

	/**
	 * Save client credentials.
	 *
	 * @param string $client_id     OAuth client ID.
	 * @param string $client_secret OAuth client secret.
	 * @return void
	 */
	public static function save_client( $client_id, $client_secret ) {
		update_option(
			self::OPTION_CLIENT,
			array(
				'client_id'     => sanitize_text_field( $client_id ),
				'client_secret' => sanitize_text_field( $client_secret ),
			),
			false
		);
	}

	/**
	 * Whether a client has been configured.
	 *
	 * @return bool
	 */
	public static function has_client() {
		$client = self::client();

		return '' !== $client['client_id'] && '' !== $client['client_secret'];
	}

	/**
	 * Whether an account is connected.
	 *
	 * @return bool
	 */
	public static function is_connected() {
		$token = get_option( self::OPTION_TOKEN, array() );

		return ! empty( $token['refresh_token'] );
	}

	/**
	 * The address Google must be told to send people back to.
	 *
	 * @return string
	 */
	public static function redirect_uri() {
		return admin_url( 'admin-post.php?action=lstabp_google_callback' );
	}

	/**
	 * Build the consent screen URL.
	 *
	 * @param string $state Anti-forgery value echoed back by Google.
	 * @return string
	 */
	public static function consent_url( $state ) {
		$client = self::client();

		return add_query_arg(
			array(
				'client_id'     => rawurlencode( $client['client_id'] ),
				'redirect_uri'  => rawurlencode( self::redirect_uri() ),
				'response_type' => 'code',
				'scope'         => rawurlencode( self::SCOPE ),
				// Offline plus consent is what actually yields a refresh token;
				// without both, access lapses in an hour and never returns.
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'include_granted_scopes' => 'true',
				'state'         => rawurlencode( $state ),
			),
			self::AUTH_ENDPOINT
		);
	}

	/**
	 * Start the connection.
	 *
	 * @return void
	 */
	public function handle_connect() {
		$this->guard( 'lstabp_google_connect' );

		if ( ! self::has_client() ) {
			$this->redirect_with( 'error', __( 'Add your Google client ID and secret first.', 'live-sheets-table-pro' ) );
		}

		$state = wp_generate_password( 24, false );
		set_transient( 'lstabp_oauth_state_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS );

		wp_redirect( self::consent_url( $state ) );
		exit;
	}

	/**
	 * Exchange the returned code for tokens.
	 *
	 * @return void
	 */
	public function handle_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to connect an account.', 'live-sheets-table-pro' ), '', array( 'response' => 403 ) );
		}

		// Google returns here by redirect, so there is no nonce to check. The
		// state value generated at the start is what proves this callback
		// belongs to a flow this user actually started.
		$expected = get_transient( 'lstabp_oauth_state_' . get_current_user_id() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- state parameter serves this purpose.
		$returned = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		delete_transient( 'lstabp_oauth_state_' . get_current_user_id() );

		if ( ! $expected || ! hash_equals( (string) $expected, $returned ) ) {
			$this->redirect_with( 'error', __( 'That sign-in did not match the request that started it, so it was discarded. Please try connecting again.', 'live-sheets-table-pro' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
		if ( isset( $_GET['error'] ) ) {
			$this->redirect_with(
				'error',
				sprintf(
					/* translators: %s: error reported by Google. */
					__( 'Google refused the connection: %s', 'live-sheets-table-pro' ),
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					sanitize_text_field( wp_unslash( $_GET['error'] ) )
				)
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		if ( '' === $code ) {
			$this->redirect_with( 'error', __( 'Google did not return an authorisation code.', 'live-sheets-table-pro' ) );
		}

		$token = self::exchange_code( $code );

		if ( is_wp_error( $token ) ) {
			$this->redirect_with( 'error', $token->get_error_message() );
		}

		$this->redirect_with( 'success', __( 'Google account connected. Private sheets can now be used as sources.', 'live-sheets-table-pro' ) );
	}

	/**
	 * Forget the connection.
	 *
	 * @return void
	 */
	public function handle_disconnect() {
		$this->guard( 'lstabp_google_disconnect' );

		delete_option( self::OPTION_TOKEN );

		$this->redirect_with( 'success', __( 'Google account disconnected. Private sheets will stop updating.', 'live-sheets-table-pro' ) );
	}

	/**
	 * Swap an authorisation code for an access and refresh token.
	 *
	 * @param string $code Authorisation code.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function exchange_code( $code ) {
		$client = self::client();

		$response = wp_remote_post(
			self::TOKEN_ENDPOINT,
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $client['client_id'],
					'client_secret' => $client['client_secret'],
					'redirect_uri'  => self::redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		return self::store_token_response( $response );
	}

	/**
	 * A usable access token, refreshing it when it has expired.
	 *
	 * @return string|WP_Error
	 */
	public static function access_token() {
		$token = get_option( self::OPTION_TOKEN, array() );

		if ( empty( $token['refresh_token'] ) ) {
			return new WP_Error(
				'lstabp_not_connected',
				__( 'No Google account is connected, so private sheets cannot be read.', 'live-sheets-table-pro' )
			);
		}

		// Refresh a minute early: a token that expires mid-request is a failed
		// sync, and the free plugin would report it as an outage.
		if ( ! empty( $token['access_token'] ) && isset( $token['expires_at'] ) && $token['expires_at'] > time() + MINUTE_IN_SECONDS ) {
			return (string) $token['access_token'];
		}

		$refreshed = self::refresh( (string) $token['refresh_token'] );

		if ( is_wp_error( $refreshed ) ) {
			return $refreshed;
		}

		return (string) $refreshed['access_token'];
	}

	/**
	 * Trade the refresh token for a new access token.
	 *
	 * @param string $refresh_token Refresh token.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function refresh( $refresh_token ) {
		$client = self::client();

		$response = wp_remote_post(
			self::TOKEN_ENDPOINT,
			array(
				'timeout' => 20,
				'body'    => array(
					'refresh_token' => $refresh_token,
					'client_id'     => $client['client_id'],
					'client_secret' => $client['client_secret'],
					'grant_type'    => 'refresh_token',
				),
			)
		);

		return self::store_token_response( $response, $refresh_token );
	}

	/**
	 * Validate and persist a token endpoint response.
	 *
	 * @param array<string,mixed>|WP_Error $response       HTTP response.
	 * @param string                       $keep_refresh   Refresh token to retain when Google omits one.
	 * @return array<string,mixed>|WP_Error
	 */
	protected static function store_token_response( $response, $keep_refresh = '' ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'lstabp_token_transport',
				sprintf(
					/* translators: %s: transport error. */
					__( 'Could not reach Google to complete sign-in: %s', 'live-sheets-table-pro' ),
					$response->get_error_message()
				)
			);
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code || ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$detail = is_array( $body ) && ! empty( $body['error_description'] )
				? (string) $body['error_description']
				: sprintf(
					/* translators: %d: HTTP status code. */
					__( 'HTTP %d', 'live-sheets-table-pro' ),
					$code
				);

			return new WP_Error(
				'lstabp_token_rejected',
				sprintf(
					/* translators: %s: reason reported by Google. */
					__( 'Google rejected the sign-in: %s', 'live-sheets-table-pro' ),
					$detail
				)
			);
		}

		// Google only returns a refresh token the first time; keep the one we
		// already hold, or a later refresh would find nothing to refresh with.
		$refresh = ! empty( $body['refresh_token'] ) ? (string) $body['refresh_token'] : $keep_refresh;

		if ( '' === $refresh ) {
			$existing = get_option( self::OPTION_TOKEN, array() );
			$refresh  = isset( $existing['refresh_token'] ) ? (string) $existing['refresh_token'] : '';
		}

		$token = array(
			'access_token'  => (string) $body['access_token'],
			'refresh_token' => $refresh,
			'expires_at'    => time() + ( isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 3600 ),
			'scope'         => isset( $body['scope'] ) ? (string) $body['scope'] : self::SCOPE,
		);

		// autoload off: this is a credential, not something every page needs.
		update_option( self::OPTION_TOKEN, $token, false );

		return $token;
	}

	/**
	 * Capability and nonce check.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	protected function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'live-sheets-table-pro' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Return to the settings screen with a message.
	 *
	 * @param string $type    success or error.
	 * @param string $message Message.
	 * @return void
	 */
	protected function redirect_with( $type, $message ) {
		set_transient(
			'lstabp_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);

		wp_safe_redirect( admin_url( 'admin.php?page=live-sheets-table-pro' ) );
		exit;
	}
}
