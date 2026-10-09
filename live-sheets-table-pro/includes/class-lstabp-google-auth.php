<?php

defined( 'ABSPATH' ) || exit;

class LSTABP_Google_Auth {
	const OPTION_CLIENT = 'lstabp_google_client';
	const OPTION_TOKEN  = 'lstabp_google_token';

	const AUTH_ENDPOINT  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
	const SCOPE          = 'https://www.googleapis.com/auth/spreadsheets.readonly';

	public function register() {
		add_action( 'admin_post_lstabp_google_connect', array( $this, 'handle_connect' ) );
		add_action( 'admin_post_lstabp_google_callback', array( $this, 'handle_callback' ) );
		add_action( 'admin_post_lstabp_google_disconnect', array( $this, 'handle_disconnect' ) );
	}

	public static function client() {
		$stored = get_option( self::OPTION_CLIENT, array() );

		return array(
			'client_id'     => isset( $stored['client_id'] ) ? (string) $stored['client_id'] : '',
			'client_secret' => isset( $stored['client_secret'] ) ? (string) $stored['client_secret'] : '',
		);
	}

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

	public static function has_client() {
		$client = self::client();

		return '' !== $client['client_id'] && '' !== $client['client_secret'];
	}

	public static function is_connected() {
		$token = get_option( self::OPTION_TOKEN, array() );

		return ! empty( $token['refresh_token'] );
	}

	public static function has_expired() {
		$token = get_option( self::OPTION_TOKEN, array() );

		return empty( $token['refresh_token'] ) && ! empty( $token['expired'] );
	}

	public static function expired_message() {
		return __( 'Google ended the connection to your Google account, so private sheets cannot be read. Sign in to Google again on the Pro screen. If this happens every week, set the publishing status of your Google Cloud app to “In production”.', 'live-sheets-table-pro' );
	}

	public static function redirect_uri() {
		return admin_url( 'admin-post.php?action=lstabp_google_callback' );
	}

	public static function consent_url( $state ) {
		$client = self::client();

		return add_query_arg(
			array(
				'client_id'     => rawurlencode( $client['client_id'] ),
				'redirect_uri'  => rawurlencode( self::redirect_uri() ),
				'response_type' => 'code',
				'scope'         => rawurlencode( self::SCOPE ),
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'include_granted_scopes' => 'true',
				'state'         => rawurlencode( $state ),
			),
			self::AUTH_ENDPOINT
		);
	}

	public function handle_connect() {
		$this->guard( 'lstabp_google_connect' );

		if ( ! self::has_client() ) {
			$this->redirect_with( 'error', __( 'Add your Google client ID and secret first.', 'live-sheets-table-pro' ) );
		}

		$state = wp_generate_password( 24, false );
		set_transient( 'lstabp_oauth_state_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS );

		wp_redirect( self::consent_url( $state ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Google's consent screen, a fixed external host.
		exit;
	}

	public function handle_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to connect an account.', 'live-sheets-table-pro' ), '', array( 'response' => 403 ) );
		}

		$expected = get_transient( 'lstabp_oauth_state_' . get_current_user_id() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- state parameter serves this purpose.
		$returned = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		delete_transient( 'lstabp_oauth_state_' . get_current_user_id() );

		if ( ! $expected || ! hash_equals( (string) $expected, $returned ) ) {
			$this->redirect_with( 'error', __( 'That sign-in did not match the request that started it, so it was discarded. Please try connecting again.', 'live-sheets-table-pro' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by the OAuth state value.
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by the OAuth state value.
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

	public function handle_disconnect() {
		$this->guard( 'lstabp_google_disconnect' );

		delete_option( self::OPTION_TOKEN );

		$this->redirect_with( 'success', __( 'Google account disconnected. Private sheets will stop updating.', 'live-sheets-table-pro' ) );
	}

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

	public static function access_token() {
		$token = get_option( self::OPTION_TOKEN, array() );

		if ( empty( $token['refresh_token'] ) ) {
			if ( ! empty( $token['expired'] ) ) {
				return new WP_Error( 'lstabp_expired', self::expired_message() );
			}

			return new WP_Error(
				'lstabp_not_connected',
				__( 'No Google account is connected, so private sheets cannot be read.', 'live-sheets-table-pro' )
			);
		}

		if ( ! empty( $token['access_token'] ) && isset( $token['expires_at'] ) && $token['expires_at'] > time() + MINUTE_IN_SECONDS ) {
			return (string) $token['access_token'];
		}

		$refreshed = self::refresh( (string) $token['refresh_token'] );

		if ( is_wp_error( $refreshed ) ) {
			return $refreshed;
		}

		return (string) $refreshed['access_token'];
	}

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

		if ( '' !== $keep_refresh && is_array( $body ) && isset( $body['error'] ) && 'invalid_grant' === $body['error'] ) {
			update_option( self::OPTION_TOKEN, array( 'expired' => time() ), false );

			return new WP_Error( 'lstabp_expired', self::expired_message() );
		}

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

		update_option( self::OPTION_TOKEN, $token, false );

		return $token;
	}

	protected function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'live-sheets-table-pro' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );
	}

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
