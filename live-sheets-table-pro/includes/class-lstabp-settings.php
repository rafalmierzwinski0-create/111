<?php
/**
 * Pro settings screen.
 *
 * @package LiveSheetsTablePro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings and the private-sheet toggle.
 */
class LSTABP_Settings {

	const PAGE_SLUG = 'live-sheets-table-pro';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_lstabp_save_client', array( $this, 'handle_save_client' ) );
		add_action( 'admin_post_lstabp_save_sources', array( $this, 'handle_save_sources' ) );
	}

	/**
	 * Add the screen under the free plugin's menu.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_submenu_page(
			'live-sheets-table',
			__( 'Pro settings', 'live-sheets-table-pro' ),
			__( 'Pro', 'live-sheets-table-pro' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Reuse the free plugin's admin styling, so Pro does not look bolted on.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'lstab-table' );
		wp_enqueue_style(
			'lstab-admin',
			LSTAB_URL . 'assets/css/lstab-admin.css',
			array( 'lstab-table' ),
			LSTAB_Plugin::asset_version( 'assets/css/lstab-admin.css' )
		);
	}

	/**
	 * Save the OAuth client.
	 *
	 * @return void
	 */
	public function handle_save_client() {
		$this->guard( 'lstabp_save_client' );

		LSTABP_Google_Auth::save_client(
			isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '',
			isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : ''
		);

		$this->redirect_with( 'success', __( 'Google client saved.', 'live-sheets-table-pro' ) );
	}

	/**
	 * Save which sources need the connected account.
	 *
	 * @return void
	 */
	public function handle_save_sources() {
		$this->guard( 'lstabp_save_sources' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- keys and values cast below.
		$submitted = isset( $_POST['private'] ) ? (array) wp_unslash( $_POST['private'] ) : array();

		foreach ( LSTAB_Storage::get_all() as $source ) {
			LSTABP_Private_Sheets::set_private(
				$source['id'],
				! empty( $submitted[ $source['id'] ] )
			);
		}

		$this->redirect_with( 'success', __( 'Sheet settings saved.', 'live-sheets-table-pro' ) );
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'live-sheets-table-pro' ) );
		}

		$client    = LSTABP_Google_Auth::client();
		$connected = LSTABP_Google_Auth::is_connected();
		$sources   = LSTAB_Storage::get_all();

		require LSTABP_PATH . 'includes/views/settings-page.php';
	}

	/**
	 * Print and clear the queued notice.
	 *
	 * @return void
	 */
	public static function print_notice() {
		$key    = 'lstabp_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );

		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			'error' === $notice['type'] ? 'notice-error' : 'notice-success',
			esc_html( $notice['message'] )
		);
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
	 * Redirect back with a message.
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

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}
}
